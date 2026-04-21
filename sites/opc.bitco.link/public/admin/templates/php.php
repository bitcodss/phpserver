<?php
/**
 * PHP Settings Management
 */
$phpInfo = [];
$rawIni = shell_exec("docker exec cid-php74 php -r \"echo json_encode(ini_get_all(null, false));\" 2>/dev/null");
$allSettings = $rawIni ? json_decode($rawIni, true) : [];

// Key settings to display/edit
$editableSettings = [
    'Core' => [
        'memory_limit' => ['desc' => 'Maximum memory per script', 'type' => 'text'],
        'max_execution_time' => ['desc' => 'Max execution time (seconds)', 'type' => 'number'],
        'max_input_time' => ['desc' => 'Max input parsing time (seconds)', 'type' => 'number'],
        'max_input_vars' => ['desc' => 'Max number of input variables', 'type' => 'number'],
        'post_max_size' => ['desc' => 'Max POST data size', 'type' => 'text'],
        'upload_max_filesize' => ['desc' => 'Max upload file size', 'type' => 'text'],
        'max_file_uploads' => ['desc' => 'Max simultaneous uploads', 'type' => 'number'],
        'display_errors' => ['desc' => 'Display errors (Off for production)', 'type' => 'select', 'options' => ['Off', 'On']],
        'error_reporting' => ['desc' => 'Error reporting level', 'type' => 'text'],
    ],
    'Session' => [
        'session.cookie_httponly' => ['desc' => 'HTTP-only session cookies', 'type' => 'select', 'options' => ['1', '0']],
        'session.cookie_secure' => ['desc' => 'Secure (HTTPS) cookies only', 'type' => 'select', 'options' => ['1', '0']],
        'session.use_strict_mode' => ['desc' => 'Strict session mode', 'type' => 'select', 'options' => ['1', '0']],
        'session.save_handler' => ['desc' => 'Session handler', 'type' => 'text'],
        'session.save_path' => ['desc' => 'Session save path', 'type' => 'text'],
    ],
    'OPcache' => [
        'opcache.enable' => ['desc' => 'Enable OPcache', 'type' => 'select', 'options' => ['1', '0']],
        'opcache.memory_consumption' => ['desc' => 'OPcache memory (MB)', 'type' => 'number'],
        'opcache.max_accelerated_files' => ['desc' => 'Max cached files', 'type' => 'number'],
        'opcache.revalidate_freq' => ['desc' => 'Revalidation frequency (seconds)', 'type' => 'number'],
    ],
    'Security' => [
        'expose_php' => ['desc' => 'Expose PHP version in headers', 'type' => 'select', 'options' => ['Off', 'On']],
        'allow_url_fopen' => ['desc' => 'Allow URL fopen', 'type' => 'select', 'options' => ['On', 'Off']],
        'allow_url_include' => ['desc' => 'Allow URL include', 'type' => 'select', 'options' => ['Off', 'On']],
        'disable_functions' => ['desc' => 'Disabled functions', 'type' => 'textarea'],
        'open_basedir' => ['desc' => 'Open basedir restriction', 'type' => 'text'],
    ],
];

$extensions = [];
$rawExt = shell_exec("docker exec cid-php74 php -r \"echo json_encode(get_loaded_extensions());\" 2>/dev/null");
if ($rawExt) $extensions = json_decode($rawExt, true) ?: [];
sort($extensions);
?>

<h2 class="page-title">🐘 PHP Settings</h2>

<div class="grid" style="grid-template-columns: 1fr 1fr;">
    <div class="card">
        <div class="card-title">PHP Version</div>
        <div class="card-value ok"><?= htmlspecialchars($allSettings['PHP_VERSION'] ?? '7.4') ?></div>
    </div>
    <div class="card">
        <div class="card-title">Extensions Loaded</div>
        <div class="card-value"><?= count($extensions) ?></div>
    </div>
</div>

<?php foreach ($editableSettings as $section => $settings): ?>
<div class="table-wrap">
    <h3><?= $section ?></h3>
    <table>
        <thead><tr><th>Setting</th><th>Current Value</th><th>Description</th></tr></thead>
        <tbody>
        <?php foreach ($settings as $key => $meta): 
            $current = $allSettings[$key] ?? 'N/A';
        ?>
        <tr>
            <td><code style="color:var(--accent)"><?= htmlspecialchars($key) ?></code></td>
            <td>
                <?php if ($meta['type'] === 'select'): ?>
                    <select class="php-setting" data-key="<?= htmlspecialchars($key) ?>" style="padding:6px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text)">
                        <?php foreach ($meta['options'] as $opt): ?>
                            <option <?= (string)$current === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($meta['type'] === 'textarea'): ?>
                    <textarea class="php-setting" data-key="<?= htmlspecialchars($key) ?>" style="width:100%;min-height:40px;padding:6px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);font-family:monospace;font-size:0.75rem"><?= htmlspecialchars($current) ?></textarea>
                <?php else: ?>
                    <input type="<?= $meta['type'] ?>" class="php-setting" data-key="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($current) ?>" style="padding:6px;width:180px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text)">
                <?php endif; ?>
            </td>
            <td style="color:var(--muted);font-size:0.8rem"><?= $meta['desc'] ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<div style="margin-bottom:24px;">
    <button class="btn btn-primary" onclick="savePhpSettings()">💾 Save & Restart PHP</button>
    <span id="saveStatus" style="margin-left:12px;color:var(--muted)"></span>
</div>

<?php
// Read current FPM config
$fpmRaw = shell_exec("docker exec cid-php74 cat /usr/local/etc/php-fpm.d/www.conf 2>/dev/null") ?: '';
$fpmSettings = [];
foreach (['pm', 'pm.max_children', 'pm.start_servers', 'pm.min_spare_servers', 'pm.max_spare_servers', 'pm.max_requests', 'pm.process_idle_timeout', 'request_slowlog_timeout'] as $key) {
    if (preg_match('/^' . preg_quote($key, '/') . '\s*=\s*(.+)$/m', $fpmRaw, $m)) {
        $fpmSettings[$key] = trim($m[1]);
    }
}
?>

<div class="table-wrap">
    <h3>⚙️ FPM Config (Process Manager)</h3>
    <table>
        <thead><tr><th>Setting</th><th>Value</th><th>Description</th></tr></thead>
        <tbody>
        <tr>
            <td><code style="color:var(--accent)">pm</code></td>
            <td>
                <select id="fpm_pm" style="padding:6px;width:180px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text)">
                    <option value="dynamic" <?= ($fpmSettings['pm'] ?? '') === 'dynamic' ? 'selected' : '' ?>>dynamic</option>
                    <option value="static" <?= ($fpmSettings['pm'] ?? '') === 'static' ? 'selected' : '' ?>>static</option>
                    <option value="ondemand" <?= ($fpmSettings['pm'] ?? '') === 'ondemand' ? 'selected' : '' ?>>ondemand</option>
                </select>
            </td>
            <td style="color:var(--muted);font-size:0.8rem">Process manager mode — dynamic (recommended), static (fixed), ondemand (spawn on request)</td>
        </tr>
        <tr>
            <td><code style="color:var(--accent)">pm.max_children</code></td>
            <td><input type="number" id="fpm_max_children" value="<?= htmlspecialchars($fpmSettings['pm.max_children'] ?? '20') ?>" style="padding:6px;width:180px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text)"></td>
            <td style="color:var(--muted);font-size:0.8rem">Max number of child processes (higher = more concurrent requests, more RAM)</td>
        </tr>
        <tr>
            <td><code style="color:var(--accent)">pm.start_servers</code></td>
            <td><input type="number" id="fpm_start_servers" value="<?= htmlspecialchars($fpmSettings['pm.start_servers'] ?? '4') ?>" style="padding:6px;width:180px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text)"></td>
            <td style="color:var(--muted);font-size:0.8rem">Workers to start with (dynamic mode only)</td>
        </tr>
        <tr>
            <td><code style="color:var(--accent)">pm.min_spare_servers</code></td>
            <td><input type="number" id="fpm_min_spare" value="<?= htmlspecialchars($fpmSettings['pm.min_spare_servers'] ?? '2') ?>" style="padding:6px;width:180px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text)"></td>
            <td style="color:var(--muted);font-size:0.8rem">Min idle workers (dynamic mode only)</td>
        </tr>
        <tr>
            <td><code style="color:var(--accent)">pm.max_spare_servers</code></td>
            <td><input type="number" id="fpm_max_spare" value="<?= htmlspecialchars($fpmSettings['pm.max_spare_servers'] ?? '8') ?>" style="padding:6px;width:180px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text)"></td>
            <td style="color:var(--muted);font-size:0.8rem">Max idle workers (dynamic mode only)</td>
        </tr>
        <tr>
            <td><code style="color:var(--accent)">pm.max_requests</code></td>
            <td><input type="number" id="fpm_max_requests" value="<?= htmlspecialchars($fpmSettings['pm.max_requests'] ?? '500') ?>" style="padding:6px;width:180px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text)"></td>
            <td style="color:var(--muted);font-size:0.8rem">Requests before worker respawns (0 = unlimited, prevents memory leaks)</td>
        </tr>
        <tr>
            <td><code style="color:var(--accent)">pm.process_idle_timeout</code></td>
            <td><input type="text" id="fpm_idle_timeout" value="<?= htmlspecialchars($fpmSettings['pm.process_idle_timeout'] ?? '10s') ?>" style="padding:6px;width:180px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text)"></td>
            <td style="color:var(--muted);font-size:0.8rem">Idle timeout before killing worker (ondemand mode)</td>
        </tr>
        </tbody>
    </table>
    <div style="padding:16px 20px;">
        <button class="btn btn-primary" onclick="saveFpmConfig()">💾 Update FPM Config</button>
        <span id="fpmSaveStatus" style="margin-left:12px;color:var(--muted)"></span>
    </div>
</div>

<div class="card" style="margin-bottom:24px">
    <h3 style="margin-bottom:12px">💡 FPM Mode Guide</h3>
    <table style="width:100%">
        <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:8px;color:var(--accent);font-weight:600;width:100px">dynamic</td>
            <td style="padding:8px;color:var(--muted);font-size:0.85rem">แนะนำ — ปรับ workers อัตโนมัติตาม traffic (ระหว่าง min_spare กับ max_spare)</td>
        </tr>
        <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:8px;color:var(--accent);font-weight:600">static</td>
            <td style="padding:8px;color:var(--muted);font-size:0.85rem">Fixed จำนวน workers = max_children ตลอด — ใช้ RAM มากแต่ response เร็ว</td>
        </tr>
        <tr>
            <td style="padding:8px;color:var(--accent);font-weight:600">ondemand</td>
            <td style="padding:8px;color:var(--muted);font-size:0.85rem">สร้าง workers เมื่อมี request เท่านั้น — ประหยัด RAM แต่ first request ช้ากว่า</td>
        </tr>
    </table>
</div>

<div class="table-wrap">
    <h3>Loaded Extensions (<?= count($extensions) ?>)</h3>
    <div style="padding:16px 20px; display:flex; flex-wrap:wrap; gap:8px;">
        <?php foreach ($extensions as $ext): ?>
            <span class="badge badge-ok"><?= htmlspecialchars($ext) ?></span>
        <?php endforeach; ?>
    </div>
</div>

<script>
async function saveFpmConfig() {
    const data = {
        pm: document.getElementById('fpm_pm').value,
        max_children: document.getElementById('fpm_max_children').value,
        start_servers: document.getElementById('fpm_start_servers').value,
        min_spare: document.getElementById('fpm_min_spare').value,
        max_spare: document.getElementById('fpm_max_spare').value,
        max_requests: document.getElementById('fpm_max_requests').value,
        idle_timeout: document.getElementById('fpm_idle_timeout').value,
    };
    document.getElementById('fpmSaveStatus').textContent = 'Saving...';
    const r = await apiCall('save_fpm.php', data);
    if (r.ok) {
        showToast('FPM config updated! PHP-FPM restarting...');
        document.getElementById('fpmSaveStatus').textContent = '✅ Saved & restarted';
        setTimeout(() => location.reload(), 3000);
    } else {
        showToast('Error: ' + r.error, 'error');
        document.getElementById('fpmSaveStatus').textContent = '❌ Failed';
    }
}

async function savePhpSettings() {
    const settings = {};
    document.querySelectorAll('.php-setting').forEach(el => {
        settings[el.dataset.key] = el.value;
    });
    document.getElementById('saveStatus').textContent = 'Saving...';
    const r = await apiCall('save_php.php', { settings });
    if (r.ok) {
        showToast('PHP settings saved! Restarting PHP-FPM...');
        document.getElementById('saveStatus').textContent = '✅ Saved & restarted';
    } else {
        showToast('Error: ' + r.error, 'error');
        document.getElementById('saveStatus').textContent = '❌ Failed';
    }
}
</script>
