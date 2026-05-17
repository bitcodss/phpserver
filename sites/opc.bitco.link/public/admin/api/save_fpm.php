<?php
require __DIR__ . '/_bootstrap.php';

$input = json_decode(file_get_contents('php://input'), true);

$pm           = $input['pm'] ?? 'dynamic';
$maxChildren  = max(1, (int)($input['max_children'] ?? 20));
$startServers = max(1, (int)($input['start_servers'] ?? 4));
$minSpare     = max(1, (int)($input['min_spare'] ?? 2));
$maxSpare     = max(1, (int)($input['max_spare'] ?? 8));
$maxRequests  = max(0, (int)($input['max_requests'] ?? 500));
$idleTimeout  = preg_match('/^\d+s?$/', $input['idle_timeout'] ?? '') ? $input['idle_timeout'] : '10s';

if (!in_array($pm, ['dynamic', 'static', 'ondemand'], true)) {
    die(json_encode(['ok' => false, 'error' => 'Invalid pm mode']));
}

if ($pm === 'dynamic') {
    if ($startServers < $minSpare)  $startServers = $minSpare;
    if ($startServers > $maxSpare)  $startServers = $maxSpare;
    if ($maxSpare    > $maxChildren) $maxSpare    = $maxChildren;
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

; Display errors (dev mode)
php_admin_value['display_errors'] = 1

; Pass through container env vars to PHP (used for secrets via getenv()).
clear_env = no

; Security
security.limit_extensions = .php
CONF;

// Write through the rw mount at /usr/local/etc/php-conf/www.conf — same host
// file as the existing read-only mount on the FPM config path.
$confPath = '/usr/local/etc/php-conf/www.conf';
if (!is_writable(dirname($confPath))) {
    die(json_encode(['ok' => false, 'error' => "Config dir not writable: " . dirname($confPath) . ". Recreate cid-php74 after the compose update."]));
}
if (file_put_contents($confPath, $conf) === false) {
    die(json_encode(['ok' => false, 'error' => 'Failed to write ' . $confPath]));
}

// Restart PHP-FPM via the broker. Same caveat as save_php.php: the restart
// kills the worker handling this request, so the response may not reach the
// client. The config write has already happened.
brokerCall('/container', ['name' => 'cid-php74', 'action' => 'restart']);

echo json_encode([
    'ok' => true,
    'message' => "FPM config updated (pm=$pm, max_children=$maxChildren). cid-php74 will restart shortly.",
    'config' => [
        'pm' => $pm,
        'max_children' => $maxChildren,
        'start_servers' => $startServers,
        'min_spare' => $minSpare,
        'max_spare' => $maxSpare,
        'max_requests' => $maxRequests,
    ]
]);
