<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['authenticated'])) { die(json_encode(['ok' => false, 'error' => 'Unauthorized'])); }

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$container = $input['container'] ?? '';

// Only allow cid- containers
$allowed = ['cid-php74', 'cid-nginx', 'cid-mariadb', 'cid-phpmyadmin', 'cid-redis'];
if (!in_array($container, $allowed)) {
    die(json_encode(['ok' => false, 'error' => 'Container not allowed']));
}

if ($action === 'restart') {
    $out = shell_exec("docker restart $container 2>&1");
    echo json_encode(['ok' => true, 'output' => trim($out)]);
} elseif ($action === 'stop') {
    $out = shell_exec("docker stop $container 2>&1");
    echo json_encode(['ok' => true, 'output' => trim($out)]);
} elseif ($action === 'start') {
    $out = shell_exec("docker start $container 2>&1");
    echo json_encode(['ok' => true, 'output' => trim($out)]);
} else {
    echo json_encode(['ok' => false, 'error' => 'Invalid action']);
}
