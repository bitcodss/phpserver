<?php
/**
 * opc.bitco.link — Landing Page
 * Managed by ศ.Cid
 */

$phpVersion = phpversion();
$serverTime = date('Y-m-d H:i:s T');
$extensions = get_loaded_extensions();
sort($extensions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OPC — PHP 7.4</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { background: #1e293b; border-radius: 16px; padding: 48px; max-width: 600px; width: 90%; box-shadow: 0 25px 50px rgba(0,0,0,0.3); }
        h1 { font-size: 2rem; margin-bottom: 8px; }
        h1 span { color: #38bdf8; }
        .subtitle { color: #94a3b8; margin-bottom: 32px; }
        .info { display: grid; gap: 12px; }
        .info-row { display: flex; justify-content: space-between; padding: 12px 16px; background: #0f172a; border-radius: 8px; }
        .info-label { color: #94a3b8; }
        .info-value { color: #38bdf8; font-weight: 600; }
        .status { display: inline-block; width: 8px; height: 8px; background: #22c55e; border-radius: 50%; margin-right: 8px; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .footer { margin-top: 32px; text-align: center; color: #475569; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔧 <span>OPC</span> Server</h1>
        <p class="subtitle"><span class="status"></span>Running — Managed by ศ.Cid</p>
        <div class="info">
            <div class="info-row">
                <span class="info-label">PHP Version</span>
                <span class="info-value"><?= $phpVersion ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Server Time</span>
                <span class="info-value"><?= $serverTime ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Extensions</span>
                <span class="info-value"><?= count($extensions) ?> loaded</span>
            </div>
            <div class="info-row">
                <span class="info-label">Database</span>
                <span class="info-value">MariaDB 10.11</span>
            </div>
            <div class="info-row">
                <span class="info-label">Cache</span>
                <span class="info-value">Redis 7</span>
            </div>
        </div>
        <div class="footer">BITCO Datasciences Solutions &copy; <?= date('Y') ?></div>
    </div>
</body>
</html>
