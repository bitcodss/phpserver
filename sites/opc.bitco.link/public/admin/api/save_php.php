<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['authenticated'])) { die(json_encode(['ok' => false, 'error' => 'Unauthorized'])); }

$input = json_decode(file_get_contents('php://input'), true);
$settings = $input['settings'] ?? [];

if (empty($settings)) {
    die(json_encode(['ok' => false, 'error' => 'No settings provided']));
}

// Build php.ini content
$ini = "; ===================================\n";
$ini .= "; PHP 7.4 Custom Configuration\n";
$ini .= "; Updated via ศ.Cid Dashboard: " . date('Y-m-d H:i:s') . "\n";
$ini .= "; ===================================\n\n[PHP]\n";

// Map settings to ini format
$sections = [
    'Core' => ['memory_limit', 'max_execution_time', 'max_input_time', 'max_input_vars', 
               'post_max_size', 'upload_max_filesize', 'max_file_uploads', 'display_errors',
               'error_reporting'],
    'Security' => ['expose_php', 'allow_url_fopen', 'allow_url_include', 'disable_functions', 'open_basedir'],
    'Session' => ['session.cookie_httponly', 'session.cookie_secure', 'session.use_strict_mode',
                  'session.save_handler', 'session.save_path'],
    'OPcache' => ['opcache.enable', 'opcache.memory_consumption', 'opcache.max_accelerated_files',
                  'opcache.revalidate_freq'],
];

// Always include these
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

foreach ($sections as $section => $keys) {
    $ini .= "\n; $section\n";
    foreach ($keys as $key) {
        if (isset($settings[$key])) {
            $ini .= "$key = {$settings[$key]}\n";
        }
    }
}

$ini .= "\n; Defaults\n";
foreach ($defaults as $k => $v) {
    $ini .= "$k = $v\n";
}

// Write to the config file (mounted into container)
$confPath = '/var/www/sites/_config/php-custom.ini';
@mkdir(dirname($confPath), 0755, true);
file_put_contents($confPath, $ini);

// Also write to the actual mount path  
$hostPath = dirname(__DIR__, 4) . '/../docker/php/conf/php-custom.ini';
if (is_writable(dirname($hostPath))) {
    file_put_contents($hostPath, $ini);
}

// Restart PHP-FPM
$out = shell_exec("docker restart cid-php74 2>&1");

echo json_encode(['ok' => true, 'message' => 'PHP settings saved and PHP-FPM restarted']);
