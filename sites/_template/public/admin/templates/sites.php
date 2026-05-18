<?php
/**
 * Sites Management — Add/remove PHP sites + domains
 */
require_once __DIR__ . '/../_lib.php';

// Public IP shown in DNS instructions. Overridable via /admin/?page=settings
// (writes opc_db.site_settings); falls back to SITE_PUBLIC_IP env var; falls
// back to placeholder so the UI never embeds a stale literal.
$publicIp = setting('SITE_PUBLIC_IP', 'YOUR.SERVER.IP');

// Scan site directories (sites are at /var/www/sites/*)
$sitesBase = '/var/www/sites';
$existingSites = [];
foreach (glob($sitesBase . '/*') as $d) {
    if (!is_dir($d)) continue;
    $name = basename($d);
    if ($name === '_config' || $name[0] === '.') continue;
    
    // Read site.json if exists
    $meta = [];
    $metaFile = "$d/site.json";
    if (file_exists($metaFile)) {
        $meta = json_decode(file_get_contents($metaFile), true) ?: [];
    }
    $existingSites[] = [
        'domain' => $name,
        'label' => $meta['label'] ?? $name,
        'description' => $meta['description'] ?? '',
        'created' => $meta['created'] ?? 'Unknown',
        'database' => $meta['database']['database'] ?? null,
        'ssl' => $meta['ssl'] ?? 'unknown',
        'docroot' => "/var/www/sites/$name/public",
    ];
}

// Get existing databases for the modal
$dbList = [];
$dbRaw = mysqlQuery("SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT IN ('information_schema','performance_schema','mysql','sys');");
if ($dbRaw !== '') $dbList = array_filter(array_map('trim', explode("\n", trim($dbRaw))));

// Get existing users for the modal
$userList = [];
$userRaw = mysqlQuery("SELECT CONCAT(User,'@',Host) FROM mysql.user WHERE User NOT IN ('root','mariadb.sys','');");
if ($userRaw !== '') $userList = array_filter(array_map('trim', explode("\n", trim($userRaw))));
?>

<h2 class="page-title">🌐 Sites Management</h2>

<div style="margin-bottom: 20px;">
    <button class="btn btn-primary" onclick="document.getElementById('addSiteModal').classList.add('show')">+ Add New Site</button>
</div>

<div class="table-wrap">
    <h3>Active Sites (<?= count($existingSites) ?>)</h3>
    <table>
        <thead><tr><th>Site</th><th>Domain</th><th>SSL</th><th>Database</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (empty($existingSites)): ?>
            <tr><td colspan="6" style="text-align:center; color:var(--muted)">No sites configured</td></tr>
        <?php else: ?>
            <?php foreach ($existingSites as $site): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($site['label']) ?></strong>
                    <?php if ($site['description']): ?>
                        <div style="color:var(--muted);font-size:0.8rem;margin-top:2px"><?= htmlspecialchars($site['description']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="https://<?= htmlspecialchars($site['domain']) ?>" target="_blank" style="color:var(--accent);text-decoration:none">
                        <?= htmlspecialchars($site['domain']) ?> ↗
                    </a>
                    <div style="color:var(--muted);font-family:monospace;font-size:0.75rem"><?= $site['docroot'] ?></div>
                </td>
                <td>
                    <?php if (strpos($site['ssl'], 'auto') !== false): ?>
                        <span class="badge badge-ok">🔒 Auto</span>
                    <?php elseif ($site['ssl'] === 'disabled'): ?>
                        <span class="badge badge-err">Off</span>
                    <?php else: ?>
                        <span class="badge" style="background:rgba(234,179,8,0.15);color:#eab308">⏳ Pending</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($site['database']): ?>
                        <span class="badge badge-ok"><?= htmlspecialchars($site['database']) ?></span>
                    <?php else: ?>
                        <span style="color:var(--muted);font-size:0.8rem">—</span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--muted);font-size:0.85rem"><?= htmlspecialchars($site['created']) ?></td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="viewSiteConfig('<?= htmlspecialchars($site['domain']) ?>')">Config</button>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card" style="margin-top:24px">
    <h3 style="margin-bottom:16px">📖 How to Add a New Site</h3>
    <ol style="padding-left: 20px; line-height: 2; color: var(--muted);">
        <li>Click <strong>"+ Add New Site"</strong> above</li>
        <li>Fill in domain, name, description, and database options</li>
        <li>The system will: create directory, Nginx vhost, Caddy route</li>
        <li><strong>Add DNS A record:</strong> <code>your-domain</code> → <code><?= htmlspecialchars($publicIp, ENT_QUOTES) ?></code></li>
        <li>Caddy auto-provisions SSL once DNS resolves</li>
    </ol>
</div>

<!-- Add Site Modal -->
<div id="addSiteModal" class="modal-bg">
    <div class="modal" style="max-width:560px">
        <h3>🌐 Add New Site</h3>
        
        <div class="form-group">
            <label>Domain Name *</label>
            <input type="text" id="newDomain" placeholder="e.g., myapp.bitco.link">
        </div>
        <div class="form-group">
            <label>Site Name</label>
            <input type="text" id="newSiteName" placeholder="e.g., My App (optional)">
        </div>
        <div class="form-group">
            <label>Description</label>
            <input type="text" id="newSiteDesc" placeholder="e.g., Customer portal (optional)">
        </div>
        <div style="display:flex;gap:24px">
            <div class="form-group" style="flex:1">
                <label>PHP Version</label>
                <select id="newPhpVersion"><option value="7.4" selected>PHP 7.4 (current)</option></select>
            </div>
            <div class="form-group" style="flex:1">
                <label>SSL / HTTPS</label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px 0">
                    <input type="checkbox" id="newEnableSsl" checked style="width:20px;height:20px;accent-color:var(--accent)">
                    <span>Auto SSL (Let's Encrypt via Caddy)</span>
                </label>
                <small style="color:var(--muted)">DNS A record ต้องชี้มาที่ <?= htmlspecialchars($publicIp, ENT_QUOTES) ?> ก่อน</small>
            </div>
        </div>

        <hr style="border-color:var(--border);margin:20px 0">
        <h4 style="margin-bottom:12px;font-size:0.95rem">🗄️ Database</h4>

        <div class="form-group">
            <label>Database</label>
            <select id="newDbOption" onchange="toggleDbFields()">
                <option value="none">No database</option>
                <option value="new">Create new database</option>
                <option value="existing">Use existing database</option>
            </select>
        </div>
        <div id="dbNewFields" style="display:none">
            <div class="form-group">
                <label>New Database Name</label>
                <input type="text" id="newDbName" placeholder="e.g., myapp_db">
            </div>
        </div>
        <div id="dbExistFields" style="display:none">
            <div class="form-group">
                <label>Select Database</label>
                <select id="existDbSelect">
                    <?php foreach ($dbList as $db): ?>
                    <option value="<?= htmlspecialchars($db) ?>"><?= htmlspecialchars($db) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="dbUserSection" style="display:none">
            <div class="form-group">
                <label>Database User</label>
                <select id="newUserOption" onchange="toggleUserFields()">
                    <option value="new">Create new user</option>
                    <option value="existing">Use existing user</option>
                </select>
            </div>
            <div id="userNewFields">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="newUserName" placeholder="auto-generated">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div style="display:flex;gap:8px">
                        <input type="text" id="newUserPass" placeholder="auto-generated">
                        <button class="btn btn-sm" style="background:var(--border);color:var(--text);white-space:nowrap" onclick="document.getElementById('newUserPass').value=genPass()">Generate</button>
                    </div>
                </div>
            </div>
            <div id="userExistFields" style="display:none">
                <div class="form-group">
                    <label>Select User</label>
                    <select id="existUserSelect">
                        <?php foreach ($userList as $u): ?>
                        <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div id="addSiteResult" style="margin-top:12px;display:none"></div>
        <div style="display:flex;gap:12px;margin-top:20px;">
            <button class="btn btn-primary" onclick="addSite()">Create Site</button>
            <button class="btn" style="background:var(--border);color:var(--text)" onclick="document.getElementById('addSiteModal').classList.remove('show')">Cancel</button>
        </div>
    </div>
</div>

<!-- Config Modal -->
<div id="configModal" class="modal-bg">
    <div class="modal" style="max-width:700px">
        <h3>⚙️ Site Config: <span id="configDomain"></span></h3>
        <div id="configContent" style="color:var(--muted)">Loading...</div>
        <div style="margin-top:24px;">
            <button class="btn" style="background:var(--border);color:var(--text)" onclick="document.getElementById('configModal').classList.remove('show')">Close</button>
        </div>
    </div>
</div>

<script>
function genPass() {
    const c = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$';
    return Array.from({length:16}, ()=>c[Math.floor(Math.random()*c.length)]).join('');
}

function toggleDbFields() {
    const v = document.getElementById('newDbOption').value;
    document.getElementById('dbNewFields').style.display = v === 'new' ? '' : 'none';
    document.getElementById('dbExistFields').style.display = v === 'existing' ? '' : 'none';
    document.getElementById('dbUserSection').style.display = v !== 'none' ? '' : 'none';
}

function toggleUserFields() {
    const v = document.getElementById('newUserOption').value;
    document.getElementById('userNewFields').style.display = v === 'new' ? '' : 'none';
    document.getElementById('userExistFields').style.display = v === 'existing' ? '' : 'none';
}

// Auto-fill username when DB name changes
document.getElementById('newDbName')?.addEventListener('input', function() {
    const un = document.getElementById('newUserName');
    if (!un.dataset.edited) un.value = this.value.substring(0,16) + '_u';
});
document.getElementById('newDomain')?.addEventListener('input', function() {
    const db = document.getElementById('newDbName');
    if (!db.dataset.edited) db.value = this.value.replace(/[^a-z0-9]/g, '_').substring(0,32);
    db.dispatchEvent(new Event('input'));
});

// Auto-generate password on load
document.getElementById('newUserPass').value = genPass();

async function addSite() {
    const domain = document.getElementById('newDomain').value.trim().toLowerCase();
    if (!domain || !/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/.test(domain)) {
        showToast('Invalid domain format', 'error'); return;
    }

    const data = {
        domain,
        label: document.getElementById('newSiteName').value.trim() || domain,
        description: document.getElementById('newSiteDesc').value.trim(),
        enableSsl: document.getElementById('newEnableSsl').checked,
        dbOption: document.getElementById('newDbOption').value,
        dbName: document.getElementById('newDbOption').value === 'new' 
            ? document.getElementById('newDbName').value.trim()
            : document.getElementById('existDbSelect')?.value || '',
        userOption: document.getElementById('newUserOption')?.value || 'new',
        userName: document.getElementById('newUserOption')?.value === 'existing'
            ? document.getElementById('existUserSelect')?.value || ''
            : document.getElementById('newUserName')?.value.trim() || '',
        userPass: document.getElementById('newUserPass')?.value || '',
    };

    const result = document.getElementById('addSiteResult');
    result.style.display = 'block';
    result.innerHTML = '<div style="color:var(--accent)">⏳ Creating site...</div>';

    const r = await apiCall('add_site.php', data);
    if (r.ok) {
        let msg = `✅ Site <strong>${domain}</strong> created!<br><br>`;
        if (r.ssl && r.ssl.includes('auto')) {
            msg += `<strong>SSL:</strong> 🔒 Auto (Caddy Let's Encrypt)<br>`;
        } else if (r.ssl) {
            msg += `<strong>SSL:</strong> ⚠️ ${r.ssl}<br>`;
        }
        msg += `<strong>DNS:</strong> Add A record: <code>${domain}</code> → <code><?= htmlspecialchars($publicIp, ENT_QUOTES) ?></code>`;
        if (r.database) {
            msg += `<br><br><strong>Database:</strong> <code>${r.database.database}</code>`;
            if (r.database.user) msg += `<br><strong>User:</strong> <code>${r.database.user}</code> / <code>${r.database.password}</code>`;
        }
        msg += '<br><br><button class="btn btn-sm" style="background:var(--border);color:var(--text)" onclick="location.reload()">Done — Close</button>';
        result.innerHTML = '<div style="color:var(--accent2)">' + msg + '</div>';
        showToast('Site created!');
    } else {
        result.innerHTML = '<div style="color:var(--danger)">❌ ' + r.error + '</div>';
    }
}

async function viewSiteConfig(domain) {
    document.getElementById('configDomain').textContent = domain;
    document.getElementById('configModal').classList.add('show');
    document.getElementById('configContent').innerHTML = 'Loading...';
    const r = await apiCall('site_config.php', { domain });
    document.getElementById('configContent').innerHTML = r.ok
        ? `<pre style="background:#000;padding:16px;border-radius:8px;overflow:auto;max-height:400px;font-size:0.8rem;color:#22c55e">${r.config.replace(/</g,'&lt;')}</pre>`
        : `<div style="color:var(--danger)">${r.error}</div>`;
}
</script>
