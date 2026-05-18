<?php
require __DIR__ . '/_bootstrap.php';

$input = json_decode(file_get_contents('php://input'), true);
$name = $input['name'] ?? '';
$createUser = (bool)($input['createUser'] ?? false);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (!preg_match('/^[a-z0-9_]{1,64}$/', $name)) {
    die(json_encode(['ok' => false, 'error' => 'Invalid database name']));
}

$sql1 = "CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
$out1 = mysqlExec($sql1);
if (strpos($out1, 'ERROR') !== false) {
    die(json_encode(['ok' => false, 'error' => 'DB: ' . trim($out1)]));
}

$userInfo = null;
if ($createUser) {
    $user = preg_replace('/[^a-z0-9_]/', '', $username ?: substr($name, 0, 16) . '_u');
    if (!$user) {
        die(json_encode(['ok' => false, 'error' => 'Invalid username']));
    }

    // Auto-generate if blank; otherwise enforce a safe password shape.
    if ($password === '') {
        $pass = bin2hex(random_bytes(8));
    } else {
        if (!preg_match('/^[A-Za-z0-9!@#%^&*()_+=\-]{8,64}$/', $password)) {
            die(json_encode(['ok' => false, 'error' => 'Password must be 8-64 chars, no quotes/backslash/semicolons']));
        }
        $pass = $password;
    }

    $sql2 = "CREATE USER IF NOT EXISTS '$user'@'%' IDENTIFIED BY '$pass';";
    $out2 = mysqlExec($sql2);
    if (strpos($out2, 'ERROR') !== false) {
        die(json_encode(['ok' => false, 'error' => 'User: ' . trim($out2)]));
    }

    $sql3 = "GRANT ALL PRIVILEGES ON `$name`.* TO '$user'@'%'; FLUSH PRIVILEGES;";
    $out3 = mysqlExec($sql3);
    if (strpos($out3, 'ERROR') !== false) {
        die(json_encode(['ok' => false, 'error' => 'Grant: ' . trim($out3)]));
    }

    $userInfo = ['user' => $user, 'password' => $pass];
}

echo json_encode(['ok' => true, 'database' => $name, 'user' => $userInfo]);
