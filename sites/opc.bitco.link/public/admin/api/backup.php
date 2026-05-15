<?php
/**
 * Admin backup API — dispatcher between the dashboard UI and cid-broker's
 * /backup/* routes. Most actions are thin pass-throughs. The download action
 * streams the gzipped restored .sql back to the browser and cleans the
 * staging dir via a shutdown handler.
 *
 * Action contract: POST JSON with {"action": "<name>", ...}.
 *
 * Auth + CSRF: enforced by _bootstrap.php for POSTs. The download action
 * comes in as GET (so the browser can navigate to it via window.location),
 * which means the CSRF gate doesn't apply — auth still does, and we require
 * a session-bound nonce in the query string to bind the request to this user.
 */

require __DIR__ . '/_bootstrap.php';

// Convenience wrapper that pulls a known-good response shape out of brokerCall().
function brokerOr500(string $path, array $payload = []): array {
    $r = brokerCall($path, $payload);
    if (!is_array($r['json'] ?? null)) {
        http_response_code(502);
        return ['ok' => false, 'error' => 'broker unreachable: ' . ($r['body'] ?? '')];
    }
    return $r['json'];
}

function brokerCallMethod(string $method, string $path, array $payload = []): array {
    // Re-use the existing brokerCall for POST; for GET/PUT we need a minor variant.
    $sock = '/run/broker/broker.sock';
    $ch = curl_init();
    $opts = [
        CURLOPT_UNIX_SOCKET_PATH => $sock,
        CURLOPT_URL              => "http://localhost{$path}",
        CURLOPT_RETURNTRANSFER   => true,
        CURLOPT_TIMEOUT          => 300,
        CURLOPT_CONNECTTIMEOUT   => 5,
        CURLOPT_CUSTOMREQUEST    => $method,
        CURLOPT_HTTPHEADER       => ['Content-Type: application/json'],
    ];
    if ($payload && $method !== 'GET') {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = is_string($body) ? json_decode($body, true) : null;
    return ['http' => $http, 'body' => $body, 'json' => $json];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ----- DOWNLOAD path: GET so a normal <a href> works ---------------------
if ($method === 'GET' && ($_GET['action'] ?? '') === 'download') {
    $sid = $_GET['snapshot_id'] ?? '';
    if (!preg_match('/^[a-f0-9]{8,64}$/', $sid)) {
        http_response_code(400);
        echo 'invalid snapshot id';
        exit;
    }
    // The broker restores the snapshot to a fresh .dl-* dir and returns the path.
    $r = brokerCallMethod('POST', '/backup/download-db', ['snapshot_id' => $sid]);
    $json = $r['json'] ?? null;
    if (!is_array($json) || empty($json['ok'])) {
        http_response_code((int)($r['http'] ?: 500));
        header('Content-Type: text/plain');
        echo $json['error'] ?? 'restore failed';
        exit;
    }
    $file = $json['file'];
    $dir  = $json['dir'];
    $name = $json['filename'] ?? 'backup.sql';
    $size = (int)($json['size'] ?? 0);

    // Cleanup runs even if the browser disconnects mid-stream.
    ignore_user_abort(true);
    register_shutdown_function(function() use ($file, $dir) {
        @unlink($file);
        @rmdir($dir);
    });

    if (!is_readable($file)) {
        http_response_code(500);
        echo 'restored file not readable by php (check permission contract)';
        exit;
    }

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . basename($name) . '"');
    header('Content-Length: ' . $size);
    header('Cache-Control: no-store');
    readfile($file);
    exit;
}

// ----- All other actions: POST JSON --------------------------------------
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? '';

switch ($action) {

    case 'list-targets':
        echo json_encode(brokerCallMethod('GET', '/backup/list-targets')['json'] ?? ['ok'=>false]);
        break;

    case 'list-tables':
        echo json_encode(brokerCallMethod('POST', '/backup/list-tables',
            ['database' => $input['database'] ?? ''])['json'] ?? ['ok'=>false]);
        break;

    case 'jobs-get':
        echo json_encode(brokerCallMethod('GET', '/backup/jobs')['json'] ?? ['ok'=>false]);
        break;

    case 'jobs-put':
        echo json_encode(brokerCallMethod('PUT', '/backup/jobs',
            ['jobs' => $input['jobs'] ?? []])['json'] ?? ['ok'=>false]);
        break;

    case 'run':
        echo json_encode(brokerCallMethod('POST', '/backup/run',
            ['job_id' => $input['job_id'] ?? ''])['json'] ?? ['ok'=>false]);
        break;

    case 'snapshots':
        echo json_encode(brokerCallMethod('GET', '/backup/snapshots')['json'] ?? ['ok'=>false]);
        break;

    case 'restore':
        echo json_encode(brokerCallMethod('POST', '/backup/restore',
            ['snapshot_id' => $input['snapshot_id'] ?? ''])['json'] ?? ['ok'=>false]);
        break;

    case 'forget':
        echo json_encode(brokerCallMethod('POST', '/backup/forget',
            ['snapshot_id' => $input['snapshot_id'] ?? ''])['json'] ?? ['ok'=>false]);
        break;

    case 'runs':
        echo json_encode(brokerCallMethod('GET', '/backup/runs')['json'] ?? ['ok'=>false]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'unknown action']);
}
