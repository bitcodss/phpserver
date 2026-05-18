<?php
/**
 * Settings — runtime overrides for env-var-driven values.
 * Empty field = unset → falls back to env var → falls back to default.
 */
require_once __DIR__ . '/../_lib.php';

$current = [
    'PMA_PUBLIC_URL' => setting('PMA_PUBLIC_URL', ''),
    'SITE_PUBLIC_IP' => setting('SITE_PUBLIC_IP', ''),
];
?>

<h2 class="page-title">⚙️ Settings</h2>

<p style="color:var(--muted);margin-bottom:24px">
    Runtime overrides for env-var-driven values. Set a value to override the
    <code>docker/.env</code> default for this deployment without recreating
    containers. Leave a field blank to unset the override and fall back to the
    env var (or the hard-coded placeholder if the env var is also unset).
</p>

<div class="card" style="max-width:720px">
    <div class="form-group">
        <label>phpMyAdmin Public URL <span style="color:var(--muted)">(<code>PMA_PUBLIC_URL</code>)</span></label>
        <input type="url" data-key="PMA_PUBLIC_URL"
               value="<?= htmlspecialchars($current['PMA_PUBLIC_URL'], ENT_QUOTES) ?>"
               placeholder="https://pma.example.com">
        <small style="color:var(--muted);font-size:0.8rem">Shown on the Database page as the "phpMyAdmin ↗" link.</small>
    </div>

    <div class="form-group">
        <label>Site Public IP <span style="color:var(--muted)">(<code>SITE_PUBLIC_IP</code>)</span></label>
        <input type="text" data-key="SITE_PUBLIC_IP"
               value="<?= htmlspecialchars($current['SITE_PUBLIC_IP'], ENT_QUOTES) ?>"
               placeholder="139.59.119.101">
        <small style="color:var(--muted);font-size:0.8rem">Rendered in the Sites page DNS-instructions block.</small>
    </div>

    <button class="btn btn-primary" onclick="saveSettings()">Save</button>
</div>

<script>
async function saveSettings() {
    const settings = {};
    document.querySelectorAll('[data-key]').forEach(el => {
        settings[el.dataset.key] = el.value.trim();
    });
    const r = await apiCall('save_settings.php', { settings });
    if (r.ok) {
        showToast('Settings saved', 'success');
    } else {
        showToast(r.error || 'Save failed', 'error');
    }
}
</script>
