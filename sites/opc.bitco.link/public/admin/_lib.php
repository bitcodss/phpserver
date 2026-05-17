<?php
/**
 * Shared helpers for the admin dashboard.
 *
 * Every privileged operation (mysql, container start/stop, nginx reload,
 * Caddy route POST) goes through cid-broker over its unix socket at
 * /run/broker/broker.sock. cid-php74 itself no longer has access to the
 * Docker daemon.
 */

/**
 * POST a JSON payload to the broker.
 * Returns ['ok' => bool, 'http' => int, 'body' => string, 'json' => array|null].
 */
function brokerCall(string $path, array $payload = []): array {
    $sock = '/run/broker/broker.sock';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_UNIX_SOCKET_PATH => $sock,
        CURLOPT_URL              => "http://localhost{$path}",
        CURLOPT_POST             => true,
        CURLOPT_POSTFIELDS       => json_encode($payload),
        CURLOPT_HTTPHEADER       => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER   => true,
        CURLOPT_TIMEOUT          => 60,
        CURLOPT_CONNECTTIMEOUT   => 5,
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        return ['ok' => false, 'http' => 0, 'body' => $err, 'json' => null];
    }
    $json = json_decode((string)$body, true);
    return [
        'ok'   => $http >= 200 && $http < 300 && is_array($json) && !empty($json['ok']),
        'http' => $http,
        'body' => (string)$body,
        'json' => $json,
    ];
}

function mysqlExec(string $sql, bool $silent = false): string {
    $r = brokerCall('/mysql', ['sql' => $sql]);
    if (!$r['ok']) {
        $msg = $r['json']['error'] ?? $r['body'] ?? 'broker call failed';
        return "ERROR via broker: $msg";
    }
    return (string)($r['json']['output'] ?? '');
}

function mysqlQuery(string $sql, bool $silent = true): string {
    $r = brokerCall('/mysql-query', ['sql' => $sql]);
    if (!$r['ok']) return '';
    return (string)($r['json']['output'] ?? '');
}
