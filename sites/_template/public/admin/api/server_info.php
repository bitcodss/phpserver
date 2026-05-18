<?php
require __DIR__ . '/_bootstrap.php';

$ip = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());

echo json_encode([
    'ok' => true,
    'ip' => $ip,
    'hostname' => gethostname(),
    'php' => PHP_VERSION,
    'time' => date('Y-m-d H:i:s T'),
]);
