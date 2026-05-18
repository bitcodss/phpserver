<?php
/**
 * Settings — runtime overrides for env-var-driven values.
 *
 * Input fields show ONLY the DB override (rawDb=true). The effective
 * value (DB → env → default) is shown as helper text so the operator
 * knows what's actually in use when the override is empty.
 *
 * Clear a field + Save → DELETEs the row → next render shows empty,
 * with helper text indicating the env fallback is now active.
 */
require_once __DIR__ . '/../_lib.php';

// Raw DB overrides — empty string when no row / empty row.
$override = [
    'PMA_PUBLIC_URL' => setting('PMA_PUBLIC_URL', '', true),
    'SITE_PUBLIC_IP' => setting('SITE_PUBLIC_IP', '', true),
];
// Effective values (DB → env → default), shown as informational hint.
$effective = [
    'PMA_PUBLIC_URL' => setting('PMA_PUBLIC_URL', '(no fallback set)'),
    'SITE_PUBLIC_IP' => setting('SITE_PUBLIC_IP', '(no fallback set)'),
];

function settingHint(string $override, string $effective): string {
    if ($override !== '') {
        return 'Override active. Clear and save to fall back to env / default.';
    }
    return 'No override set. Currently using: <code>'
        . htmlspecialchars($effective, ENT_QUOTES) . '</code>';
}
?>

<h2 class="page-title">⚙️ Settings</h2>

<p style="color:var(--muted);margin-bottom:24px">
    Runtime overrides for env-var-driven values. Set a value to override the
    <code>docker/.env</code> default without recreating containers. Leave a
    field blank to unset the override — the effective value will fall through
    to the env var, and then to a hard-coded placeholder.
</p>

<div class="card" style="max-width:720px">
    <div class="form-group">
        <label>phpMyAdmin Public URL <span style="color:var(--muted)">(<code>PMA_PUBLIC_URL</code>)</span></label>
        <input type="url" data-key="PMA_PUBLIC_URL"
               value="<?= htmlspecialchars($override['PMA_PUBLIC_URL'], ENT_QUOTES) ?>"
               placeholder="https://pma.example.com">
        <small style="color:var(--muted);font-size:0.8rem">
            Shown on the Database page as the "phpMyAdmin ↗" link.<br>
            <?= settingHint($override['PMA_PUBLIC_URL'], $effective['PMA_PUBLIC_URL']) ?>
        </small>
    </div>

    <div class="form-group">
        <label>Site Public IP <span style="color:var(--muted)">(<code>SITE_PUBLIC_IP</code>)</span></label>
        <input type="text" data-key="SITE_PUBLIC_IP"
               value="<?= htmlspecialchars($override['SITE_PUBLIC_IP'], ENT_QUOTES) ?>"
               placeholder="139.59.119.101">
        <small style="color:var(--muted);font-size:0.8rem">
            Rendered in the Sites page DNS-instructions block.<br>
            <?= settingHint($override['SITE_PUBLIC_IP'], $effective['SITE_PUBLIC_IP']) ?>
        </small>
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
