<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['authenticated'])) { die(json_encode(['ok' => false, 'error' => 'Unauthorized'])); }

$input = json_decode(file_get_contents('php://input'), true);
$name = $input['name'] ?? '';

if (!preg_match('/^[a-z0-9_]{1,64}$/', $name)) {
    die(json_encode(['ok' => false, 'error' => 'Invalid database name']));
}

// Safety: prevent dropping system databases
$protected = ['mysql', 'information_schema', 'performance_schema', 'sys'];
if (in_array($name, $protected)) {
    die(json_encode(['ok' => false, 'error' => 'Cannot drop system database']));
}

$out = shell_exec("docker exec cid-mariadb mysql -uroot -pCidMariaDB2026! -e \"DROP DATABASE IF EXISTS \`$name\`;\" 2>&1");

if (strpos($out, 'ERROR') !== false) {
    echo json_encode(['ok' => false, 'error' => trim($out)]);
} else {
    echo json_encode(['ok' => true]);
}
