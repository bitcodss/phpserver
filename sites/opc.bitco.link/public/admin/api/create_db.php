<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['authenticated'])) { die(json_encode(['ok' => false, 'error' => 'Unauthorized'])); }

$input = json_decode(file_get_contents('php://input'), true);
$name = $input['name'] ?? '';
$createUser = $input['createUser'] ?? false;
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (!preg_match('/^[a-z0-9_]{1,64}$/', $name)) {
    die(json_encode(['ok' => false, 'error' => 'Invalid database name']));
}

// Step 1: Create database
$sql1 = "CREATE DATABASE IF NOT EXISTS $name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
$out1 = shell_exec("docker exec cid-mariadb mysql -uroot -pCidMariaDB2026! -e " . escapeshellarg($sql1) . " 2>&1");
if (strpos($out1, 'ERROR') !== false) {
    die(json_encode(['ok' => false, 'error' => 'DB: ' . trim($out1)]));
}

$userInfo = null;
if ($createUser) {
    $user = preg_replace('/[^a-z0-9_]/', '', $username ?: substr($name, 0, 16) . '_u');
    $pass = $password ?: bin2hex(random_bytes(8));
    if (!$user) { die(json_encode(['ok' => false, 'error' => 'Invalid username'])); }

    // Step 2: Create user (separate command to avoid escaping hell)
    $sql2 = "CREATE USER IF NOT EXISTS '$user'@'%' IDENTIFIED BY '$pass';";
    $out2 = shell_exec("docker exec cid-mariadb mysql -uroot -pCidMariaDB2026! -e " . escapeshellarg($sql2) . " 2>&1");
    if (strpos($out2, 'ERROR') !== false) {
        die(json_encode(['ok' => false, 'error' => 'User: ' . trim($out2)]));
    }

    // Step 3: Grant privileges
    $sql3 = "GRANT ALL PRIVILEGES ON $name.* TO '$user'@'%'; FLUSH PRIVILEGES;";
    $out3 = shell_exec("docker exec cid-mariadb mysql -uroot -pCidMariaDB2026! -e " . escapeshellarg($sql3) . " 2>&1");
    if (strpos($out3, 'ERROR') !== false) {
        die(json_encode(['ok' => false, 'error' => 'Grant: ' . trim($out3)]));
    }

    $userInfo = ['user' => $user, 'password' => $pass];
}

echo json_encode(['ok' => true, 'database' => $name, 'user' => $userInfo]);
