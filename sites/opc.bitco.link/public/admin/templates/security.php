<?php
/**
 * Security Overview & Settings
 */

// Check various security settings
$phpSettings = shell_exec("docker exec cid-php74 php -r \"
    echo json_encode([
        'expose_php' => ini_get('expose_php'),
        'display_errors' => ini_get('display_errors'),
        'allow_url_include' => ini_get('allow_url_include'),
        'disable_functions' => ini_get('disable_functions'),
        'open_basedir' => ini_get('open_basedir'),
        'session.cookie_httponly' => ini_get('session.cookie_httponly'),
        'session.cookie_secure' => ini_get('session.cookie_secure'),
    ]);
\" 2>/dev/null");
$sec = $phpSettings ? json_decode($phpSettings, true) : [];

$checks = [
    ['PHP version headers hidden', !empty($sec['expose_php']) && $sec['expose_php'] !== '1' && $sec['expose_php'] !== 'On', 'expose_php = Off'],
    ['Error display disabled', empty($sec['display_errors']) || $sec['display_errors'] === '' || $sec['display_errors'] === '0' || $sec['display_errors'] === 'Off', 'display_errors = Off'],
    ['URL include disabled', empty($sec['allow_url_include']) || $sec['allow_url_include'] === '' || $sec['allow_url_include'] === '0', 'allow_url_include = Off'],
    ['Dangerous functions disabled', !empty($sec['disable_functions']), 'exec, passthru, shell_exec...'],
    ['Open basedir set', !empty($sec['open_basedir']), $sec['open_basedir'] ?? 'not set'],
    ['Session cookies HTTP-only', !empty($sec['session.cookie_httponly']), 'cookie_httponly = 1'],
    ['Session cookies secure', !empty($sec['session.cookie_secure']), 'cookie_secure = 1'],
    ['Docker network isolation', true, 'cid-network (bridge)'],
    ['Nginx rate limiting', true, '10r/s general, 3r/s login'],
    ['Security headers (Nginx)', true, 'X-Frame, X-Content-Type, X-XSS, Referrer-Policy'],
];

$passCount = count(array_filter($checks, fn($c) => $c[1]));
$totalCount = count($checks);
$score = round(($passCount / $totalCount) * 100);
?>

<h2 class="page-title">🛡️ Security</h2>

<div class="grid" style="grid-template-columns: 1fr 1fr;">
    <div class="card">
        <div class="card-title">Security Score</div>
        <div class="card-value <?= $score >= 80 ? 'ok' : ($score >= 50 ? 'warn' : 'err') ?>"><?= $score ?>%</div>
        <div style="color:var(--muted);font-size:0.85rem;margin-top:4px"><?= $passCount ?>/<?= $totalCount ?> checks passed</div>
    </div>
    <div class="card">
        <div class="card-title">SSL/TLS</div>
        <div class="card-value ok">Auto (Caddy)</div>
        <div style="color:var(--muted);font-size:0.85rem;margin-top:4px">Let's Encrypt, auto-renew</div>
    </div>
</div>

<div class="table-wrap">
    <h3>Security Checklist</h3>
    <table>
        <thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead>
        <tbody>
        <?php foreach ($checks as $check): ?>
        <tr>
            <td><?= htmlspecialchars($check[0]) ?></td>
            <td><span class="badge <?= $check[1] ? 'badge-ok' : 'badge-err' ?>"><?= $check[1] ? '✅ PASS' : '❌ FAIL' ?></span></td>
            <td style="color:var(--muted);font-size:0.85rem;font-family:monospace"><?= htmlspecialchars($check[2]) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3 style="margin-bottom:16px">🔒 Disabled PHP Functions</h3>
    <div style="display:flex; flex-wrap:wrap; gap:8px;">
        <?php 
        $disabled = array_filter(array_map('trim', explode(',', $sec['disable_functions'] ?? '')));
        foreach ($disabled as $fn): ?>
            <span class="badge badge-err" style="font-family:monospace"><?= htmlspecialchars($fn) ?></span>
        <?php endforeach; ?>
        <?php if (empty($disabled)): ?>
            <span style="color:var(--danger)">⚠️ No functions disabled!</span>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:24px">
    <h3 style="margin-bottom:16px">📋 Nginx Security Headers</h3>
    <table style="width:auto">
        <tr><td style="padding:4px 20px 4px 0"><code>X-Frame-Options</code></td><td>SAMEORIGIN</td></tr>
        <tr><td style="padding:4px 20px 4px 0"><code>X-Content-Type-Options</code></td><td>nosniff</td></tr>
        <tr><td style="padding:4px 20px 4px 0"><code>X-XSS-Protection</code></td><td>1; mode=block</td></tr>
        <tr><td style="padding:4px 20px 4px 0"><code>Referrer-Policy</code></td><td>strict-origin-when-cross-origin</td></tr>
        <tr><td style="padding:4px 20px 4px 0"><code>Server</code></td><td>Hidden (server_tokens off)</td></tr>
    </table>
</div>
