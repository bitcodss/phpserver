<?php
/**
 * API: persist runtime overrides to opc_db.site_settings.
 * Whitelist of allowed keys + per-key validation. Empty value deletes the row
 * (so reads fall back to env → default).
 */
require __DIR__ . '/_bootstrap.php';

$input = json_decode(file_get_contents('php://input'), true);
$settings = $input['settings'] ?? [];

if (!is_array($settings)) {
    die(json_encode(['ok' => false, 'error' => 'settings must be an object']));
}

$ALLOWED = ['PMA_PUBLIC_URL', 'SITE_PUBLIC_IP'];

foreach ($settings as $key => $value) {
    if (!in_array($key, $ALLOWED, true)) continue;
    $value = (string)$value;

    if ($value !== '') {
        if ($key === 'PMA_PUBLIC_URL') {
            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                die(json_encode(['ok' => false, 'error' => "Invalid URL for $key"]));
            }
        } elseif ($key === 'SITE_PUBLIC_IP') {
            if (!filter_var($value, FILTER_VALIDATE_IP)) {
                die(json_encode(['ok' => false, 'error' => "Invalid IP for $key"]));
            }
        }
    }

    // SQL-standard single-quote doubling. Safe regardless of NO_BACKSLASH_ESCAPES.
    // $key is from the hardcoded ALLOWED list — no escaping needed.
    $escValue = str_replace("'", "''", $value);

    if ($value === '') {
        $r = mysqlExec("DELETE FROM opc_db.site_settings WHERE `key`='$key';");
    } else {
        $r = mysqlExec(
            "INSERT INTO opc_db.site_settings (`key`,`value`) "
            . "VALUES('$key','$escValue') "
            . "ON DUPLICATE KEY UPDATE `value`='$escValue', `updated_at`=CURRENT_TIMESTAMP;"
        );
    }
    if (strpos($r, 'ERROR') === 0) {
        die(json_encode(['ok' => false, 'error' => "DB write failed: $r"]));
    }
}

echo json_encode(['ok' => true]);
