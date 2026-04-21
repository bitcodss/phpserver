<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['authenticated'])) { die(json_encode(['ok' => false, 'error' => 'Unauthorized'])); }

$input = json_decode(file_get_contents('php://input'), true);

$pm = $input['pm'] ?? 'dynamic';
$maxChildren = max(1, (int)($input['max_children'] ?? 20));
$startServers = max(1, (int)($input['start_servers'] ?? 4));
$minSpare = max(1, (int)($input['min_spare'] ?? 2));
$maxSpare = max(1, (int)($input['max_spare'] ?? 8));
$maxRequests = max(0, (int)($input['max_requests'] ?? 500));
$idleTimeout = preg_match('/^\d+s?$/', $input['idle_timeout'] ?? '') ? $input['idle_timeout'] : '10s';

// Validate pm mode
if (!in_array($pm, ['dynamic', 'static', 'ondemand'])) {
    die(json_encode(['ok' => false, 'error' => 'Invalid pm mode']));
}

// Validate: start_servers must be between min and max spare
if ($pm === 'dynamic') {
    if ($startServers < $minSpare) $startServers = $minSpare;
    if ($startServers > $maxSpare) $startServers = $maxSpare;
    if ($maxSpare > $maxChildren) $maxSpare = $maxChildren;
}

$conf = <<<CONF
[www]
user = www-data
group = www-data

listen = 0.0.0.0:9000

pm = $pm
pm.max_children = $maxChildren
pm.start_servers = $startServers
pm.min_spare_servers = $minSpare
pm.max_spare_servers = $maxSpare
pm.max_requests = $maxRequests
pm.process_idle_timeout = $idleTimeout

; Status page (internal only)
pm.status_path = /fpm-status
ping.path = /fpm-ping

; Logging - use stderr (docker logs)
access.log = /proc/self/fd/2
slowlog = /proc/self/fd/2
request_slowlog_timeout = 5s

; Security
security.limit_extensions = .php
CONF;

// Write to the mounted config path
$confPath = '/etc/nginx/conf.d/../../../usr/local/etc/php-fpm.d/www.conf';
// Actually write to the host-mounted path
$hostPaths = [
    '/var/www/sites/_config/www.conf',
];
@mkdir('/var/www/sites/_config', 0755, true);
file_put_contents('/var/www/sites/_config/www.conf', $conf);

// Copy into the container and restart
$tmpFile = tempnam('/tmp', 'fpm_');
file_put_contents($tmpFile, $conf);

// Use docker cp to put the config in place, then restart
shell_exec("docker cp $tmpFile cid-php74:/usr/local/etc/php-fpm.d/www.conf 2>&1");
unlink($tmpFile);

// Restart PHP-FPM
$out = shell_exec("docker restart cid-php74 2>&1");

echo json_encode([
    'ok' => true,
    'message' => "FPM config updated (pm=$pm, max_children=$maxChildren) and PHP-FPM restarted",
    'config' => [
        'pm' => $pm,
        'max_children' => $maxChildren,
        'start_servers' => $startServers,
        'min_spare' => $minSpare,
        'max_spare' => $maxSpare,
        'max_requests' => $maxRequests,
    ]
]);
