<?php
require __DIR__ . '/_bootstrap.php';

$input = json_decode(file_get_contents('php://input'), true);
$name = $input['name'] ?? '';
$confirm = $input['confirm'] ?? '';

if (!preg_match('/^[a-z0-9_]{1,64}$/', $name)) {
    die(json_encode(['ok' => false, 'error' => 'Invalid database name']));
}

$protected = ['mysql', 'information_schema', 'performance_schema', 'sys'];
if (in_array($name, $protected, true)) {
    die(json_encode(['ok' => false, 'error' => 'Cannot drop system database']));
}

// Require the client to echo the DB name back as a typed confirmation.
if ($confirm !== $name) {
    die(json_encode(['ok' => false, 'error' => 'Confirmation mismatch: type the database name to confirm']));
}

$out = mysqlExec("DROP DATABASE IF EXISTS `$name`;");

if (strpos($out, 'ERROR') !== false) {
    echo json_encode(['ok' => false, 'error' => trim($out)]);
} else {
    echo json_encode(['ok' => true]);
}
