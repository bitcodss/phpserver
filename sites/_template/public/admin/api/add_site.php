<?php
/**
 * API: Add new PHP site
 *
 * Privileged filesystem ops (mkdir under /var/www/sites, write to
 * /etc/nginx/conf.d) go through cid-broker's POST /site/create — PHP-FPM
 * (www-data) cannot write to either path directly, and routing site
 * creation through PHP would re-open the F-006 hole. PHP keeps
 * orchestrating: validate inputs, call broker for FS, call broker for
 * DB, call broker for Caddy route + nginx reload.
 */
require __DIR__ . '/_bootstrap.php';

function safePass($s) {
    $s = (string)$s;
    if (!preg_match('/^[A-Za-z0-9!@#%^&*()_+=\-]{8,64}$/', $s)) return null;
    return $s;
}
function safeUser($s) { return preg_replace('/[^a-zA-Z0-9_]/', '', (string)$s); }
function safeHost($s) {
    $s = (string)$s;
    return ($s === 'localhost' || $s === '127.0.0.1' || $s === '::1') ? $s : '%';
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$domain      = strtolower($input['domain'] ?? '');
$label       = $input['label'] ?? $domain;
$description = $input['description'] ?? '';
$dbOption    = $input['dbOption'] ?? 'none';   // none | new | existing
$dbName      = $input['dbName'] ?? '';
$userOption  = $input['userOption'] ?? 'new';  // new | existing
$userName    = $input['userName'] ?? '';
$userPass    = $input['userPass'] ?? '';

if (!preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/', $domain)) {
    die(json_encode(['ok' => false, 'error' => 'Invalid domain format']));
}

// Compute DB-name and DB-user up front so they can be embedded in
// site.json by the broker. Password (if generated) is intentionally
// NOT persisted — it's surfaced once via the response.
$safeDbName = '';
$safeDbUser = '';
if ($dbOption !== 'none' && $dbName) {
    $safeDbName = preg_replace('/[^a-z0-9_]/', '_', $dbName);
    if ($userOption === 'new' && $userName) {
        $safeDbUser = safeUser($userName);
        if (!$safeDbUser) {
            die(json_encode(['ok' => false, 'error' => 'Invalid username']));
        }
    } elseif ($userOption === 'existing' && $userName) {
        $parts = explode('@', $userName);
        $safeDbUser = safeUser($parts[0] ?? '');
    }
}

// 1. Site folder + nginx vhost — broker writes both from
//    sites/_template/ + docker/nginx/conf.d/_template.conf.example.
$createResult = brokerCall('/site/create', [
    'domain'      => $domain,
    'label'       => $label,
    'description' => $description,
    'db_name'     => $safeDbName,
    'db_user'     => $safeDbUser,
    'short_name'  => strtoupper(explode('.', $domain)[0]),
]);
if (empty($createResult['json']['ok'])) {
    $err = $createResult['json']['error'] ?? $createResult['body'] ?? 'broker call failed';
    die(json_encode(['ok' => false, 'error' => "Failed to create site: $err"]));
}

// 2. Database — broker /mysql (already in use elsewhere).
$dbInfo = null;
if ($dbOption !== 'none' && $dbName) {
    $dbInfo = ['database' => $safeDbName];
    if ($dbOption === 'new') {
        mysqlExec("CREATE DATABASE IF NOT EXISTS `$safeDbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    }
    if ($userOption === 'new' && $safeDbUser) {
        if ($userPass === '') {
            $pass = bin2hex(random_bytes(8));
        } else {
            $pass = safePass($userPass);
            if ($pass === null) {
                die(json_encode(['ok' => false, 'error' => 'Password must be 8-64 chars (alphanum + !@#%^&*()_+=- only)']));
            }
        }
        mysqlExec("CREATE USER IF NOT EXISTS '$safeDbUser'@'%' IDENTIFIED BY '$pass';");
        mysqlExec("GRANT ALL PRIVILEGES ON `$safeDbName`.* TO '$safeDbUser'@'%'; FLUSH PRIVILEGES;");
        $dbInfo['user'] = $safeDbUser;
        $dbInfo['password'] = $pass; // surfaced to UI once; not persisted
    } elseif ($userOption === 'existing' && $userName) {
        $parts = explode('@', $userName);
        $existUser = safeUser($parts[0] ?? '');
        $existHost = safeHost($parts[1] ?? '%');
        if (!$existUser) {
            die(json_encode(['ok' => false, 'error' => 'Invalid existing username']));
        }
        mysqlExec("GRANT ALL PRIVILEGES ON `$safeDbName`.* TO '$existUser'@'$existHost'; FLUSH PRIVILEGES;");
        $dbInfo['user'] = $existUser;
        $dbInfo['password'] = '(existing)';
    }
}

// 3. Caddy route (auto-SSL on the host frontend).
$enableSsl = $input['enableSsl'] ?? true;
$sslStatus = 'disabled';
if ($enableSsl) {
    $caddyPayload = [
        'match' => [['host' => [$domain]]],
        'handle' => [[
            'handler' => 'subroute',
            'routes' => [[
                'handle' => [[
                    'handler' => 'reverse_proxy',
                    'upstreams' => [['dial' => '127.0.0.1:9080']],
                    'headers' => ['request' => ['set' => [
                        'X-Forwarded-Proto' => ['https'],
                        'X-Real-IP' => ['{http.request.remote.host}']
                    ]]]
                ]]
            ]]
        ]],
        'terminal' => true
    ];
    $r = brokerCall('/caddy/route', ['payload' => $caddyPayload]);
    $http = $r['json']['http'] ?? '0';
    $sslStatus = ($http === '200') ? 'auto (Caddy)' : 'pending (Caddy: ' . $http . ')';
}

// 4. nginx reload so the new vhost takes effect.
brokerCall('/nginx/reload');

$serverIp = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());

echo json_encode([
    'ok' => true,
    'domain' => $domain,
    'serverIp' => $serverIp,
    'database' => $dbInfo,
    'ssl' => $sslStatus,
    'note' => $enableSsl
        ? "Site created with auto-SSL. Add DNS A record: $domain → $serverIp"
        : "Site created (SSL disabled). Add DNS A record: $domain → $serverIp"
]);
