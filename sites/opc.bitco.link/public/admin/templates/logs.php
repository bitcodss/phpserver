<?php
/**
 * Log Viewer
 */
require_once __DIR__ . '/../_lib.php';

$source = $_GET['source'] ?? 'nginx-access';
$lines = max(1, min(10000, (int)($_GET['lines'] ?? 50)));

// Map UI source -> (container, stream). stream is 'stdout', 'stderr', or 'both'.
$sourceMap = [
    'nginx-access' => ['cid-nginx',   'stdout'],
    'nginx-error'  => ['cid-nginx',   'stderr'],
    'php-fpm'      => ['cid-php74',   'both'],
    'mariadb'      => ['cid-mariadb', 'both'],
    'redis'        => ['cid-redis',   'both'],
];

$logOutput = '(no output)';
if (isset($sourceMap[$source])) {
    [$container, $stream] = $sourceMap[$source];
    $r = brokerCall('/logs', ['name' => $container, 'tail' => $lines]);
    if ($r['ok'] && isset($r['json'])) {
        $stdout = (string)($r['json']['stdout'] ?? '');
        $stderr = (string)($r['json']['stderr'] ?? '');
        if     ($stream === 'stdout') $logOutput = $stdout !== '' ? $stdout : '(no output)';
        elseif ($stream === 'stderr') $logOutput = $stderr !== '' ? $stderr : '(no output)';
        else                          $logOutput = trim($stdout . $stderr) ?: '(no output)';
    } else {
        $logOutput = '(broker error: ' . ($r['json']['error'] ?? $r['body'] ?? 'unknown') . ')';
    }
}
?>

<h2 class="page-title">📋 Logs</h2>

<div style="display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; align-items:center;">
    <select id="logSource" onchange="changeLog()" style="padding:8px 14px;background:var(--card);border:1px solid var(--border);border-radius:8px;color:var(--text)">
        <option value="nginx-access" <?= $source === 'nginx-access' ? 'selected' : '' ?>>Nginx Access</option>
        <option value="nginx-error" <?= $source === 'nginx-error' ? 'selected' : '' ?>>Nginx Error</option>
        <option value="php-fpm" <?= $source === 'php-fpm' ? 'selected' : '' ?>>PHP-FPM</option>
        <option value="mariadb" <?= $source === 'mariadb' ? 'selected' : '' ?>>MariaDB</option>
        <option value="redis" <?= $source === 'redis' ? 'selected' : '' ?>>Redis</option>
    </select>
    <select id="logLines" onchange="changeLog()" style="padding:8px 14px;background:var(--card);border:1px solid var(--border);border-radius:8px;color:var(--text)">
        <option value="25" <?= $lines === 25 ? 'selected' : '' ?>>25 lines</option>
        <option value="50" <?= $lines === 50 ? 'selected' : '' ?>>50 lines</option>
        <option value="100" <?= $lines === 100 ? 'selected' : '' ?>>100 lines</option>
        <option value="200" <?= $lines === 200 ? 'selected' : '' ?>>200 lines</option>
    </select>
    <button class="btn btn-sm btn-primary" onclick="changeLog()">🔄 Refresh</button>
</div>

<div class="log-output"><?= htmlspecialchars($logOutput) ?></div>

<script>
function changeLog() {
    const source = document.getElementById('logSource').value;
    const lines = document.getElementById('logLines').value;
    window.location.href = '?page=logs&source=' + source + '&lines=' + lines;
}
</script>
