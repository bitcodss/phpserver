<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['authenticated'])) { die(json_encode(['ok' => false, 'error' => 'Unauthorized'])); }

echo json_encode([
    'ok' => true,
    'ip' => '139.59.119.101',
    'hostname' => gethostname(),
    'php' => PHP_VERSION,
    'time' => date('Y-m-d H:i:s T'),
]);
