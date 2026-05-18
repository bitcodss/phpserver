<?php
require __DIR__ . '/_bootstrap.php';

$input = json_decode(file_get_contents('php://input'), true);
$settings = $input['settings'] ?? [];

if (empty($settings) || !is_array($settings)) {
    die(json_encode(['ok' => false, 'error' => 'No settings provided']));
}

// Per-key validators. Anything not listed here is silently dropped.
$validators = [
    'memory_limit'             => fn($v) => preg_match('/^\d+[KMG]?$/', $v),
    'max_execution_time'       => fn($v) => ctype_digit((string)$v),
    'max_input_time'           => fn($v) => ctype_digit((string)$v),
    'max_input_vars'           => fn($v) => ctype_digit((string)$v),
    'post_max_size'            => fn($v) => preg_match('/^\d+[KMG]?$/', $v),
    'upload_max_filesize'      => fn($v) => preg_match('/^\d+[KMG]?$/', $v),
    'max_file_uploads'         => fn($v) => ctype_digit((string)$v),
    'display_errors'           => fn($v) => in_array($v, ['On','Off','1','0'], true),
    'error_reporting'          => fn($v) => preg_match('/^[A-Z_0-9& |~]+$/', $v),
    'expose_php'               => fn($v) => in_array($v, ['On','Off'], true),
    'allow_url_fopen'          => fn($v) => in_array($v, ['On','Off'], true),
    'allow_url_include'        => fn($v) => in_array($v, ['On','Off'], true),
    'disable_functions'        => fn($v) => preg_match('/^[a-zA-Z0-9_,]*$/', $v),
    'open_basedir'             => fn($v) => preg_match('/^[A-Za-z0-9:_\/.\-]*$/', $v),
    'session.cookie_httponly'  => fn($v) => in_array($v, ['0','1','On','Off'], true),
    'session.cookie_secure'    => fn($v) => in_array($v, ['0','1','On','Off'], true),
    'session.use_strict_mode'  => fn($v) => in_array($v, ['0','1','On','Off'], true),
    'session.save_handler'     => fn($v) => preg_match('/^[a-zA-Z]+$/', $v),
    'session.save_path'        => fn($v) => preg_match('/^[A-Za-z0-9:_\/.\-]*$/', $v),
    'opcache.enable'           => fn($v) => in_array($v, ['0','1','On','Off'], true),
    'opcache.memory_consumption'    => fn($v) => ctype_digit((string)$v),
    'opcache.max_accelerated_files' => fn($v) => ctype_digit((string)$v),
    'opcache.revalidate_freq'       => fn($v) => ctype_digit((string)$v),
];

$sections = [
    'Core' => ['memory_limit','max_execution_time','max_input_time','max_input_vars','post_max_size','upload_max_filesize','max_file_uploads','display_errors','error_reporting'],
    'Security' => ['expose_php','allow_url_fopen','allow_url_include','disable_functions','open_basedir'],
    'Session' => ['session.cookie_httponly','session.cookie_secure','session.use_strict_mode','session.save_handler','session.save_path'],
    'OPcache' => ['opcache.enable','opcache.memory_consumption','opcache.max_accelerated_files','opcache.revalidate_freq'],
];

$defaults = [
    'log_errors' => 'On',
    'error_log' => '/proc/self/fd/2',
    'date.timezone' => 'Asia/Bangkok',
    'session.cookie_samesite' => 'Lax',
    'opcache.validate_timestamps' => '1',
    'opcache.interned_strings_buffer' => '16',
    'realpath_cache_size' => '4096k',
    'realpath_cache_ttl' => '600',
];

$rejected = [];
$ini  = "; ===================================\n";
$ini .= "; PHP 7.4 Custom Configuration\n";
$ini .= "; Updated via ศ.Cid Dashboard: " . date('Y-m-d H:i:s') . "\n";
$ini .= "; ===================================\n\n[PHP]\n";

foreach ($sections as $section => $keys) {
    $ini .= "\n; $section\n";
    foreach ($keys as $key) {
        if (!isset($settings[$key])) continue;
        $v = (string)$settings[$key];
        // Reject anything with line-breaks or section/comment metacharacters.
        if (preg_match('/[\r\n;\[\]]/', $v)) { $rejected[] = $key; continue; }
        if (isset($validators[$key]) && !$validators[$key]($v)) { $rejected[] = $key; continue; }
        $ini .= "$key = $v\n";
    }
}

$ini .= "\n; Defaults\n";
foreach ($defaults as $k => $v) {
    $ini .= "$k = $v\n";
}

// Both the read-only mount at /usr/local/etc/php/conf.d/99-custom.ini and the
// new rw mount at /usr/local/etc/php-conf/php-custom.ini point at the same host
// file (./docker/php/conf/php-custom.ini). Writing through the rw mount updates
// what FPM will read after restart.
$confPath = '/usr/local/etc/php-conf/php-custom.ini';
if (!is_writable(dirname($confPath))) {
    die(json_encode(['ok' => false, 'error' => "Config dir not writable: " . dirname($confPath) . ". Recreate cid-php74 after the .env / compose update."]));
}
if (file_put_contents($confPath, $ini) === false) {
    die(json_encode(['ok' => false, 'error' => 'Failed to write ' . $confPath]));
}

// Restart PHP-FPM via the broker (which holds the docker socket).
// Fire-and-forget would be ideal but we'd lose the result; instead we tell the
// broker to do it after the function returns by relying on its 60s timeout.
// The restart actually kills this worker, so the HTTP response may not reach
// the client — that's expected for FPM config changes.
brokerCall('/container', ['name' => 'cid-php74', 'action' => 'restart']);

echo json_encode([
    'ok' => true,
    'message' => 'PHP settings saved. cid-php74 will restart shortly.',
    'rejected' => $rejected,
]);
