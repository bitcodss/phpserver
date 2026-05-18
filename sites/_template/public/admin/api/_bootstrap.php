<?php
/**
 * Shared bootstrap for /admin/api/*.php endpoints.
 * Enforces session auth + CSRF, then pulls in the shared mysql helpers.
 */

require __DIR__ . '/../_lib.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['authenticated'])) {
    http_response_code(401);
    die(json_encode(['ok' => false, 'error' => 'Unauthorized']));
}

// CSRF: any state-changing call must echo the session token in X-CSRF-Token.
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf'] ?? '';
    if ($expected === '' || !is_string($sent) || !hash_equals($expected, $sent)) {
        http_response_code(403);
        die(json_encode(['ok' => false, 'error' => 'Bad CSRF token']));
    }
}
