<?php
/**
 * API: Add new PHP site
 * Supports: new/existing database, new/existing user, site name/description
 */
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['authenticated'])) { die(json_encode(['ok' => false, 'error' => 'Unauthorized'])); }

$input = json_decode(file_get_contents('php://input'), true);
$domain = strtolower($input['domain'] ?? '');
$label = $input['label'] ?? $domain;
$description = $input['description'] ?? '';
$dbOption = $input['dbOption'] ?? 'none'; // none, new, existing
$dbName = $input['dbName'] ?? '';
$userOption = $input['userOption'] ?? 'new'; // new, existing
$userName = $input['userName'] ?? '';
$userPass = $input['userPass'] ?? '';

// Validate domain
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
// Ensure www-data can write inside
@chmod($siteDir, 0777);
@chmod("$siteDir/public", 0777);

// 2. Create default index.php
$escapedLabel = htmlspecialchars($label);
$indexContent = "<?php\necho \"<h1>$escapedLabel</h1>\";\necho \"<p>PHP \" . phpversion() . \" — Managed by ศ.Cid</p>\";\necho \"<p>\" . date('Y-m-d H:i:s T') . \"</p>\";\n";
file_put_contents("$siteDir/public/index.php", $indexContent);

// 3. Create Nginx vhost config
$nginxConf = "server {
    listen 80;
    server_name $domain;

    root /var/www/sites/$domain/public;
    index index.php index.html;

    access_log /var/log/nginx/$domain.access.log main;
    error_log /var/log/nginx/$domain.error.log warn;

    limit_req zone=general burst=20 nodelay;

    location ~ /\\. { deny all; return 404; }
    location ~* \\.(env|git|htaccess|htpasswd|ini|log|sh|sql|bak|conf)\$ { deny all; return 404; }

    location ~ \\.php\$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\\.php)(/.+)\$;
        fastcgi_pass php74:9000;
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

// Save nginx conf to sites dir first (open_basedir allows this)
@mkdir("$sitesBase/_config/nginx", 0755, true);
file_put_contents("$sitesBase/_config/nginx/$domain.conf", $nginxConf);

// Copy to nginx conf.d via shell (open_basedir blocks direct write to /etc/nginx)
shell_exec("cp " . escapeshellarg("$sitesBase/_config/nginx/$domain.conf") . " " . escapeshellarg("/etc/nginx/conf.d/$domain.conf") . " 2>&1");

// 4. Handle Database
$dbInfo = null;
if ($dbOption !== 'none' && $dbName) {
    $safeDbName = preg_replace('/[^a-z0-9_]/', '_', $dbName);
    
    if ($dbOption === 'new') {
        // Create new database
        $sql = "CREATE DATABASE IF NOT EXISTS `$safeDbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
        shell_exec("docker exec cid-mariadb mysql -uroot -pCidMariaDB2026! -e " . escapeshellarg($sql) . " 2>&1");
    }
    
    $dbInfo = ['database' => $safeDbName];
    
    // Handle user
    if ($userOption === 'new' && $userName) {
        $safeUser = preg_replace('/[^a-z0-9_]/', '', $userName);
        $pass = $userPass ?: bin2hex(random_bytes(8));
        $sql = "CREATE USER IF NOT EXISTS '$safeUser'@'%' IDENTIFIED BY '$pass'; " .
               "GRANT ALL PRIVILEGES ON `$safeDbName`.* TO '$safeUser'@'%'; " .
               "FLUSH PRIVILEGES;";
        shell_exec("docker exec cid-mariadb mysql -uroot -pCidMariaDB2026! -e " . escapeshellarg($sql) . " 2>&1");
        $dbInfo['user'] = $safeUser;
        $dbInfo['password'] = $pass;
    } elseif ($userOption === 'existing' && $userName) {
        // Grant existing user access to the database
        $parts = explode('@', $userName);
        $existUser = $parts[0];
        $existHost = $parts[1] ?? '%';
        $sql = "GRANT ALL PRIVILEGES ON `$safeDbName`.* TO '$existUser'@'$existHost'; FLUSH PRIVILEGES;";
        shell_exec("docker exec cid-mariadb mysql -uroot -pCidMariaDB2026! -e " . escapeshellarg($sql) . " 2>&1");
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

// Save caddy config for reference
file_put_contents("$sitesBase/_config/nginx/$domain.caddy.json", $caddyPayload);

// 5b. Add Caddy route via API (auto-SSL) — use docker to curl host's Caddy API
$enableSsl = $input['enableSsl'] ?? true;
$sslStatus = 'disabled';
if ($enableSsl) {
    // Use docker run --network=host with inline JSON (no file mount needed)
    $escapedPayload = escapeshellarg($caddyPayload);
    $cmd = "docker run --rm --network=host curlimages/curl:latest " .
           "-s -o /dev/null -w '%{http_code}' -X POST http://127.0.0.1:2019/config/apps/http/servers/srv0/routes " .
           "-H 'Content-Type: application/json' " .
           "-d $escapedPayload 2>&1";
    $caddyOut = trim(shell_exec($cmd));
    
    $sslStatus = ($caddyOut === '200') ? 'auto (Caddy)' : 'pending (Caddy: ' . $caddyOut . ')';
}

// 6. Reload Nginx to pick up new config
shell_exec("docker exec cid-nginx nginx -s reload 2>&1");

// 7. Save site metadata
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
    'serverIp' => '139.59.119.101',
    'database' => $dbInfo,
    'ssl' => $sslStatus,
    'note' => $enableSsl 
        ? "Site created with auto-SSL. Add DNS A record: $domain → 139.59.119.101"
        : "Site created (SSL disabled). Add DNS A record: $domain → 139.59.119.101"
]);
