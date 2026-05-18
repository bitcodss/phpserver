<?php
require __DIR__ . '/_bootstrap.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$container = $input['container'] ?? '';

// Allow-list enforcement is duplicated in the broker; we keep the PHP-side
// check too so the admin gets a clear error before the round-trip.
$allowedContainers = ['cid-php74', 'cid-nginx', 'cid-mariadb', 'cid-phpmyadmin', 'cid-redis'];
$allowedActions = ['start', 'stop', 'restart'];

if (!in_array($container, $allowedContainers, true)) {
    die(json_encode(['ok' => false, 'error' => 'Container not allowed']));
}
if (!in_array($action, $allowedActions, true)) {
    die(json_encode(['ok' => false, 'error' => 'Invalid action']));
}

$r = brokerCall('/container', ['name' => $container, 'action' => $action]);
echo json_encode([
    'ok' => $r['ok'],
    'output' => $r['json']['output'] ?? $r['body'],
]);
