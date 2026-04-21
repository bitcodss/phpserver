<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['authenticated'])) { die(json_encode(['ok' => false, 'error' => 'Unauthorized'])); }

$input = json_decode(file_get_contents('php://input'), true);
$domain = $input['domain'] ?? '';

if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
    die(json_encode(['ok' => false, 'error' => 'Invalid domain']));
}

// Read nginx config from the mounted conf.d directory
$confPath = "/etc/nginx/conf.d/$domain.conf";
if (file_exists($confPath)) {
    echo json_encode(['ok' => true, 'config' => file_get_contents($confPath)]);
    exit;
}

// Fallback: check the main opc.bitco.link config
$mainConf = "/etc/nginx/conf.d/opc.bitco.link.conf";
if (file_exists($mainConf) && $domain === 'opc.bitco.link') {
    echo json_encode(['ok' => true, 'config' => file_get_contents($mainConf)]);
    exit;
}

// Try to read site.json instead
$siteJson = "/var/www/sites/$domain/site.json";
if (file_exists($siteJson)) {
    $config = "# site.json for $domain\n" . file_get_contents($siteJson);
    
    // Also show nginx conf if it exists in _config
    $nginxAlt = "/var/www/sites/_config/nginx/$domain.conf";
    if (file_exists($nginxAlt)) {
        $config .= "\n\n# --- Nginx Config ---\n" . file_get_contents($nginxAlt);
    }
    echo json_encode(['ok' => true, 'config' => $config]);
    exit;
}

echo json_encode(['ok' => false, 'error' => "Config not found for $domain. Nginx conf should be at: $confPath"]);
