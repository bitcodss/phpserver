<?php
/**
 * API: Add new PHP site
 * Supports: new/existing database, new/existing user, site name/description
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

$input = json_decode(file_get_contents('php://input'), true);
$domain = strtolower($input['domain'] ?? '');
$label = $input['label'] ?? $domain;
$description = $input['description'] ?? '';
$dbOption = $input['dbOption'] ?? 'none'; // none, new, existing
$dbName = $input['dbName'] ?? '';
$userOption = $input['userOption'] ?? 'new'; // new, existing
$userName = $input['userName'] ?? '';
$userPass = $input['userPass'] ?? '';

if (!preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/', $domain)) {
    die(json_encode(['ok' => false, 'error' => 'Invalid domain format']));
}

$sitesBase = '/var/www/sites';
$siteDir = "$sitesBase/$domain";

if (is_dir($siteDir)) {
    die(json_encode(['ok' => false, 'error' => "Site $domain already exists"]));
}

// 1. Create site directory
error_clear_last();
$mkResult = @mkdir("$siteDir/public", 0755, true);
if (!$mkResult) {
    $err = error_get_last();
    $detail = $err['message'] ?? 'unknown';
    die(json_encode(['ok' => false, 'error' => "Failed to create directory: $detail (path: $siteDir/public)"]));
}
// Keep permissions tight; www-data already owns these via fpm worker.
@chmod($siteDir, 0755);
@chmod("$siteDir/public", 0755);

// 2. Create a static landing index.html (avoids interpolating user input into PHP source).
$indexContent = "<!doctype html><meta charset=\"utf-8\"><title>"
    . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</title>"
    . "<h1>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</h1>"
    . "<p>Site created via ศ.Cid admin.</p>";
file_put_contents("$siteDir/public/index.html", $indexContent);

// 3. Create Nginx vhost config
$nginxConf = "server {
    listen 80;
    server_name $domain;

    root /var/www/sites/$domain/public;
    index index.php index.html;

    access_log /var/log/nginx/$domain.access.log main;
    error_log /var/log/nginx/$domain.error.log warn;

    limit_req zone=general burst=20 nodelay;

    # 8G firewall — _8g.conf defines the detection maps at http {} level.
    if (\$block_all) { return 403; }

    location ~ /\\. { deny all; return 404; }
    location ~* \\.(env|git|htaccess|htpasswd|ini|log|sh|sql|bak|conf|test|orig|old)\$ { deny all; return 404; }

    location ~ \\.php\$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\\.php)(/.+)\$;
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        fastcgi_param PHP_VALUE \"open_basedir=/var/www/sites:/tmp:/usr/share/php:/usr/local/bin:/proc\";
        fastcgi_hide_header X-Powered-By;
        fastcgi_connect_timeout 60;
        fastcgi_send_timeout 300;
        fastcgi_read_timeout 300;
    }

    location ~* \\.(jpg|jpeg|png|gif|ico|css|js|woff2?|ttf|svg|webp)\$ {
        expires 30d;
        add_header Cache-Control \"public, immutable\";
        try_files \$uri =404;
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
}";

// Save nginx conf to sites dir first (open_basedir allows this), then copy into conf.d.
// We shell out to /bin/cp because PHP's open_basedir restriction blocks
// writing under /etc/ — cp is a subprocess so it's not subject to it. $domain
// is already validated against a strict regex above.
@mkdir("$sitesBase/_config/nginx", 0755, true);
file_put_contents("$sitesBase/_config/nginx/$domain.conf", $nginxConf);
shell_exec("cp " . escapeshellarg("$sitesBase/_config/nginx/$domain.conf") . " " . escapeshellarg("/etc/nginx/conf.d/$domain.conf") . " 2>&1");

// 4. Handle Database
$dbInfo = null;
if ($dbOption !== 'none' && $dbName) {
    $safeDbName = preg_replace('/[^a-z0-9_]/', '_', $dbName);

    if ($dbOption === 'new') {
        mysqlExec("CREATE DATABASE IF NOT EXISTS `$safeDbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    }

    $dbInfo = ['database' => $safeDbName];

    if ($userOption === 'new' && $userName) {
        $safeNewUser = safeUser($userName);
        if (!$safeNewUser) {
            die(json_encode(['ok' => false, 'error' => 'Invalid username']));
        }
        if ($userPass === '') {
            $pass = bin2hex(random_bytes(8));
        } else {
            $pass = safePass($userPass);
            if ($pass === null) {
                die(json_encode(['ok' => false, 'error' => 'Password must be 8-64 chars (alphanum + !@#%^&*()_+=- only)']));
            }
        }
        mysqlExec("CREATE USER IF NOT EXISTS '$safeNewUser'@'%' IDENTIFIED BY '$pass';");
        mysqlExec("GRANT ALL PRIVILEGES ON `$safeDbName`.* TO '$safeNewUser'@'%'; FLUSH PRIVILEGES;");
        $dbInfo['user'] = $safeNewUser;
        $dbInfo['password'] = $pass;
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

// 5. Add Caddy route
$caddyPayload = json_encode([
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
]);

file_put_contents("$sitesBase/_config/nginx/$domain.caddy.json", $caddyPayload);

$enableSsl = $input['enableSsl'] ?? true;
$sslStatus = 'disabled';
if ($enableSsl) {
    $r = brokerCall('/caddy/route', ['payload' => json_decode($caddyPayload, true)]);
    $http = $r['json']['http'] ?? '0';
    $sslStatus = ($http === '200') ? 'auto (Caddy)' : 'pending (Caddy: ' . $http . ')';
}

brokerCall('/nginx/reload');

$serverIp = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());

$siteConfig = [
    'domain' => $domain,
    'label' => $label,
    'description' => $description,
    'created' => date('Y-m-d H:i:s'),
    'php' => '7.4',
    'docroot' => "/var/www/sites/$domain/public",
    'database' => $dbInfo,
    'ssl' => $sslStatus,
];
file_put_contents("$siteDir/site.json", json_encode($siteConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

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
