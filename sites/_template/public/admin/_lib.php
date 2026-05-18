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

/**
 * Read a runtime-overridable setting. Lookup order:
 *   1. DB row in opc_db.site_settings (if non-empty)
 *   2. Environment variable of the same name
 *   3. The supplied $default
 *
 * The lookup table is loaded once per request and cached in static. The
 * CREATE TABLE is idempotent and runs once per request — cheap.
 */
function setting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        mysqlExec(
            "CREATE TABLE IF NOT EXISTS opc_db.site_settings ("
            . "`key` VARCHAR(64) NOT NULL PRIMARY KEY,"
            . "`value` TEXT NULL,"
            . "`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
        );
        $out = mysqlQuery("SELECT `key`,`value` FROM opc_db.site_settings;");
        foreach (explode("\n", $out) as $line) {
            if ($line === '') continue;
            $parts = explode("\t", $line, 2);
            if (count($parts) === 2) $cache[$parts[0]] = $parts[1];
        }
    }
    if (isset($cache[$key]) && $cache[$key] !== '') return $cache[$key];
    $env = getenv($key);
    if ($env !== false && $env !== '') return (string)$env;
    return $default;
}
