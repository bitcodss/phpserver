<?php
/**
 * Database & Users Management (RunCloud-style tabs)
 */
$tab = $_GET['tab'] ?? 'databases';

// Get databases
$dbInfo = shell_exec("docker exec cid-mariadb mysql -uroot -pCidMariaDB2026! -se \"
    SELECT table_schema, COUNT(*), ROUND(SUM(data_length+index_length)/1024/1024,2), s.DEFAULT_COLLATION_NAME
    FROM information_schema.tables t
    JOIN information_schema.schemata s ON t.table_schema = s.schema_name
    WHERE table_schema NOT IN ('information_schema','performance_schema','mysql','sys')
    GROUP BY table_schema;
\" 2>/dev/null");
$databases = [];
if ($dbInfo) {
    foreach (explode("\n", trim($dbInfo)) as $line) {
        $parts = preg_split('/\t/', $line);
        if (count($parts) >= 4) {
            $databases[] = ['name' => $parts[0], 'tables' => (int)$parts[1], 'size' => $parts[2] . ' MB', 'collation' => $parts[3]];
        }
    }
}
// Also get empty databases
$emptyDbs = shell_exec("docker exec cid-mariadb mysql -uroot -pCidMariaDB2026! -se \"
    SELECT schema_name, DEFAULT_COLLATION_NAME FROM information_schema.schemata
    WHERE schema_name NOT IN ('information_schema','performance_schema','mysql','sys')
    AND schema_name NOT IN (SELECT DISTINCT table_schema FROM information_schema.tables);
\" 2>/dev/null");
if ($emptyDbs) {
    foreach (explode("\n", trim($emptyDbs)) as $line) {
        $parts = preg_split('/\t/', $line);
        if (count($parts) >= 2) {
            $databases[] = ['name' => $parts[0], 'tables' => 0, 'size' => '0 MB', 'collation' => $parts[1]];
        }
    }
}

// Get users
$userInfo = shell_exec("docker exec cid-mariadb mysql -uroot -pCidMariaDB2026! -se \"
    SELECT u.User, u.Host, GROUP_CONCAT(DISTINCT d.Db ORDER BY d.Db SEPARATOR ', ')
    FROM mysql.user u
    LEFT JOIN mysql.db d ON u.User = d.User AND u.Host = d.Host
    WHERE u.User NOT IN ('root','mariadb.sys','','healthcheck')
    GROUP BY u.User, u.Host;
\" 2>/dev/null");
$users = [];
if ($userInfo) {
    foreach (explode("\n", trim($userInfo)) as $line) {
        $parts = preg_split('/\t/', $line);
        if (count($parts) >= 2) {
            $users[] = ['user' => $parts[0], 'host' => $parts[1], 'databases' => $parts[2] ?? 'None'];
        }
    }
}

// DB status
$dbStatus = shell_exec("docker exec cid-mariadb mysql -uroot -pCidMariaDB2026! -se \"SHOW GLOBAL STATUS WHERE Variable_name IN ('Uptime','Threads_connected','Questions','Slow_queries');\" 2>/dev/null");
$status = [];
if ($dbStatus) {
    foreach (explode("\n", trim($dbStatus)) as $line) {
        $parts = preg_split('/\s+/', $line, 2);
        if (count($parts) === 2) $status[$parts[0]] = $parts[1];
    }
}
$upHours = isset($status['Uptime']) ? round($status['Uptime'] / 3600, 1) : 'N/A';
?>

<h2 class="page-title">🗄️ Database</h2>

<!-- Stats -->
<div class="grid" style="grid-template-columns: repeat(4,1fr); margin-bottom:24px">
    <div class="card" style="text-align:center">
        <div class="card-title">Uptime</div>
        <div class="card-value ok"><?= $upHours ?>h</div>
    </div>
    <div class="card" style="text-align:center">
        <div class="card-title">Connections</div>
        <div class="card-value"><?= $status['Threads_connected'] ?? 'N/A' ?></div>
    </div>
    <div class="card" style="text-align:center">
        <div class="card-title">Total Queries</div>
        <div class="card-value"><?= number_format((int)($status['Questions'] ?? 0)) ?></div>
    </div>
    <div class="card" style="text-align:center">
        <div class="card-title">Slow Queries</div>
        <div class="card-value <?= ($status['Slow_queries'] ?? 0) > 0 ? 'warn' : 'ok' ?>"><?= $status['Slow_queries'] ?? '0' ?></div>
    </div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:0;margin-bottom:24px;border-bottom:2px solid var(--border)">
    <a href="?page=database&tab=databases" style="padding:12px 24px;text-decoration:none;font-weight:600;border-bottom:2px solid <?= $tab === 'databases' ? 'var(--accent)' : 'transparent' ?>;color:<?= $tab === 'databases' ? 'var(--accent)' : 'var(--muted)' ?>;margin-bottom:-2px">Databases</a>
    <a href="?page=database&tab=users" style="padding:12px 24px;text-decoration:none;font-weight:600;border-bottom:2px solid <?= $tab === 'users' ? 'var(--accent)' : 'transparent' ?>;color:<?= $tab === 'users' ? 'var(--accent)' : 'var(--muted)' ?>;margin-bottom:-2px">Database Users</a>
    <a href="https://db-opc.bitco.link" target="_blank" style="padding:12px 24px;text-decoration:none;font-weight:600;color:var(--muted);margin-bottom:-2px">phpMyAdmin ↗</a>
</div>

<?php if ($tab === 'databases'): ?>
<!-- Databases Tab -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <input type="text" id="dbSearch" placeholder="Search..." oninput="filterTable('dbTable', this.value)" style="padding:8px 14px;width:300px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text)">
    <button class="btn btn-primary" onclick="document.getElementById('createDbModal').classList.add('show')">+ Add New Database</button>
</div>

<div class="table-wrap">
    <table id="dbTable">
        <thead><tr><th>Database Name</th><th>Database User</th><th>Tables</th><th>Size</th><th>Collation</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($databases as $db): 
            // Find users with access to this DB
            $dbUsers = array_filter($users, fn($u) => strpos($u['databases'], $db['name']) !== false);
            $userBadges = '';
            foreach ($dbUsers as $u) {
                $userBadges .= '<span class="badge badge-ok" style="margin-right:4px">' . htmlspecialchars($u['user']) . '</span>';
            }
            if (!$userBadges) $userBadges = '<span style="color:var(--muted);font-size:0.8rem">root only</span>';
        ?>
        <tr>
            <td><strong><?= htmlspecialchars($db['name']) ?></strong></td>
            <td><?= $userBadges ?></td>
            <td><?= $db['tables'] ?></td>
            <td><?= $db['size'] ?></td>
            <td style="color:var(--muted);font-size:0.8rem"><?= htmlspecialchars($db['collation']) ?></td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="dropDatabase('<?= htmlspecialchars($db['name']) ?>')">Drop</button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($databases)): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--muted)">No databases found</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <div style="padding:12px 20px;color:var(--muted);font-size:0.85rem">Showing <?= count($databases) ?> database(s)</div>
</div>

<?php elseif ($tab === 'users'): ?>
<!-- Database Users Tab -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <input type="text" id="userSearch" placeholder="Search..." oninput="filterTable('userTable', this.value)" style="padding:8px 14px;width:300px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text)">
    <button class="btn btn-primary" onclick="document.getElementById('createUserModal').classList.add('show')">+ Add Database User</button>
</div>

<div class="table-wrap">
    <table id="userTable">
        <thead><tr><th>Username</th><th>Host</th><th>Databases</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><strong><?= htmlspecialchars($u['user']) ?></strong></td>
            <td><code style="color:var(--accent)"><?= htmlspecialchars($u['host']) ?></code></td>
            <td>
                <?php 
                $dbs = array_filter(array_map('trim', explode(',', $u['databases'])));
                foreach ($dbs as $d): ?>
                    <span class="badge badge-ok" style="margin:2px"><?= htmlspecialchars($d) ?></span>
                <?php endforeach; 
                if (empty($dbs) || $u['databases'] === 'None'): ?>
                    <span style="color:var(--muted);font-size:0.8rem">No databases</span>
                <?php endif; ?>
            </td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="editUser('<?= htmlspecialchars($u['user']) ?>', '<?= htmlspecialchars($u['host']) ?>')">Edit</button>
                <button class="btn btn-sm btn-danger" onclick="dropUser('<?= htmlspecialchars($u['user']) ?>', '<?= htmlspecialchars($u['host']) ?>')">Drop</button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--muted)">No database users found</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <div style="padding:12px 20px;color:var(--muted);font-size:0.85rem">Showing <?= count($users) ?> user(s)</div>
</div>
<?php endif; ?>

<!-- Connection Info -->
<div class="card" style="margin-top:24px">
    <h3 style="margin-bottom:12px">🔑 Connection Info</h3>
    <table style="width:auto">
        <tr><td style="color:var(--muted);padding:4px 16px 4px 0">Host (from PHP)</td><td><code>cid-mariadb</code></td></tr>
        <tr><td style="color:var(--muted);padding:4px 16px 4px 0">Port</td><td><code>3306</code></td></tr>
        <tr><td style="color:var(--muted);padding:4px 16px 4px 0">Root Password</td><td><code>CidMariaDB2026!</code></td></tr>
    </table>
</div>

<!-- Create DB Modal -->
<div id="createDbModal" class="modal-bg">
    <div class="modal" style="max-width:520px">
        <h3>🗄️ Add New Database</h3>
        <div class="form-group">
            <label>Database Name</label>
            <input type="text" id="newDbName" placeholder="e.g., myapp_db" pattern="[a-z0-9_]+">
            <small style="color:var(--muted)">Lowercase, numbers, underscore only</small>
        </div>
        <div class="form-group">
            <label>Create User?</label>
            <select id="newDbCreateUser" onchange="toggleDbUserFields()">
                <option value="yes">Yes — create dedicated user</option>
                <option value="no">No — root only</option>
            </select>
        </div>
        <div id="newDbUserFields">
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="newDbUsername" placeholder="auto-generated from DB name">
            </div>
            <div class="form-group">
                <label>Password</label>
                <div style="display:flex;gap:8px">
                    <input type="text" id="newDbPassword" style="font-family:monospace" readonly>
                    <button class="btn btn-sm" style="background:var(--border);color:var(--text);white-space:nowrap" onclick="document.getElementById('newDbPassword').value=generatePass()" title="Generate new password">🔄</button>
                    <button class="btn btn-sm" style="background:var(--border);color:var(--text);white-space:nowrap" onclick="copyField('newDbPassword')" title="Copy to clipboard">📋</button>
                </div>
                <small style="color:var(--muted)">⚠️ Copy password before creating — it won't be shown again</small>
            </div>
        </div>
        <div id="createDbResult" style="margin-top:12px;display:none"></div>
        <div style="display:flex;gap:12px;margin-top:20px">
            <button class="btn btn-primary" id="createDbBtn" onclick="createDatabase()">Create Database</button>
            <button class="btn" style="background:var(--border);color:var(--text)" onclick="closeDbModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div id="createUserModal" class="modal-bg">
    <div class="modal">
        <h3>👤 Add Database User</h3>
        <div class="form-group">
            <label>Username</label>
            <input type="text" id="newUserName" placeholder="e.g., app_user">
        </div>
        <div class="form-group">
            <label>Password</label>
            <div style="display:flex;gap:8px">
                <input type="text" id="newUserPass" style="font-family:monospace" readonly>
                <button class="btn btn-sm" style="background:var(--border);color:var(--text);white-space:nowrap" onclick="document.getElementById('newUserPass').value=generatePass()" title="Generate new">🔄</button>
                <button class="btn btn-sm" style="background:var(--border);color:var(--text);white-space:nowrap" onclick="copyField('newUserPass')" title="Copy">📋</button>
            </div>
            <small style="color:var(--muted)">⚠️ Copy password before creating</small>
        </div>
        <div class="form-group">
            <label>Host</label>
            <select id="newUserHost">
                <option value="%">% (all hosts)</option>
                <option value="localhost">localhost only</option>
            </select>
        </div>
        <div class="form-group">
            <label>Grant Access to Database</label>
            <div style="max-height:160px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:8px 12px">
                <?php foreach ($databases as $db): ?>
                <label style="display:flex;align-items:center;gap:8px;padding:5px 0;cursor:pointer">
                    <input type="checkbox" class="newUserDbCheck" value="<?= htmlspecialchars($db['name']) ?>" style="width:18px;height:18px;accent-color:var(--accent)">
                    <span><?= htmlspecialchars($db['name']) ?></span>
                </label>
                <?php endforeach; ?>
                <?php if (empty($databases)): ?>
                <span style="color:var(--muted)">No databases yet</span>
                <?php endif; ?>
            </div>
        </div>
        <div id="createUserResult" style="margin-top:12px;display:none"></div>
        <div style="display:flex;gap:12px;margin-top:20px">
            <button class="btn btn-primary" onclick="createUser()">Create User</button>
            <button class="btn" style="background:var(--border);color:var(--text)" onclick="document.getElementById('createUserModal').classList.remove('show')">Cancel</button>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal-bg">
    <div class="modal" style="max-width:520px">
        <h3>✏️ Edit User: <span id="editUserTitle"></span></h3>
        <div class="form-group">
            <label>New Password (leave empty to keep current)</label>
            <div style="display:flex;gap:8px">
                <input type="text" id="editUserPass">
                <button class="btn btn-sm" style="background:var(--border);color:var(--text);white-space:nowrap" onclick="document.getElementById('editUserPass').value=generatePass()">Generate</button>
                <button class="btn btn-sm" style="background:var(--border);color:var(--text);white-space:nowrap" onclick="copyField('editUserPass')">📋</button>
            </div>
        </div>
        <div class="form-group">
            <label>Database Access</label>
            <div id="editUserDbList" style="max-height:200px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:8px 12px">
                <?php foreach ($databases as $db): ?>
                <label style="display:flex;align-items:center;gap:8px;padding:6px 0;cursor:pointer;border-bottom:1px solid rgba(255,255,255,0.05)">
                    <input type="checkbox" class="editUserDbCheck" value="<?= htmlspecialchars($db['name']) ?>" style="width:18px;height:18px;accent-color:var(--accent)">
                    <span><?= htmlspecialchars($db['name']) ?></span>
                    <span style="color:var(--muted);font-size:0.75rem;margin-left:auto"><?= $db['tables'] ?> tables • <?= $db['size'] ?></span>
                </label>
                <?php endforeach; ?>
                <?php if (empty($databases)): ?>
                <span style="color:var(--muted)">No databases available</span>
                <?php endif; ?>
            </div>
            <small style="color:var(--muted)">Check = GRANT ALL, uncheck = REVOKE ALL</small>
        </div>
        <input type="hidden" id="editUserOrigName">
        <input type="hidden" id="editUserOrigHost">
        <div id="editUserResult" style="margin-top:12px;display:none"></div>
        <div style="display:flex;gap:12px;margin-top:20px">
            <button class="btn btn-primary" onclick="saveEditUser()">Save Changes</button>
            <button class="btn" style="background:var(--border);color:var(--text)" onclick="document.getElementById('editUserModal').classList.remove('show')">Cancel</button>
        </div>
    </div>
</div>

<script>
// Auto-generate username from DB name
document.getElementById('newDbName')?.addEventListener('input', function() {
    const u = document.getElementById('newDbUsername');
    if (!u.dataset.edited) u.value = this.value.substring(0, 16) + '_u';
});
document.getElementById('newDbUsername')?.addEventListener('input', function() { this.dataset.edited = '1'; });

function generatePass() {
    const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$';
    return Array.from({length: 16}, () => chars[Math.floor(Math.random() * chars.length)]).join('');
}

// Pre-generate password on page load
if (document.getElementById('newDbPassword')) document.getElementById('newDbPassword').value = generatePass();
if (document.getElementById('newUserPass')) document.getElementById('newUserPass').value = generatePass();

function copyField(id) {
    const el = document.getElementById(id);
    if (!el || !el.value) { showToast('Nothing to copy', 'error'); return; }
    navigator.clipboard.writeText(el.value).then(
        () => showToast('📋 Copied to clipboard!'),
        () => { el.select(); document.execCommand('copy'); showToast('📋 Copied!'); }
    );
}

function toggleDbUserFields() {
    const show = document.getElementById('newDbCreateUser').value === 'yes';
    document.getElementById('newDbUserFields').style.display = show ? '' : 'none';
}

function closeDbModal() {
    document.getElementById('createDbModal').classList.remove('show');
    document.getElementById('createDbResult').style.display = 'none';
}

function filterTable(tableId, query) {
    const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
    query = query.toLowerCase();
    rows.forEach(r => { r.style.display = r.textContent.toLowerCase().includes(query) ? '' : 'none'; });
}

async function createDatabase() {
    const name = document.getElementById('newDbName').value.trim();
    const createUser = document.getElementById('newDbCreateUser').value === 'yes';
    const password = document.getElementById('newDbPassword')?.value || '';
    if (!name || !/^[a-z0-9_]+$/.test(name)) { showToast('Invalid name (a-z, 0-9, _ only)', 'error'); return; }
    if (createUser && !password) { showToast('Generate a password first', 'error'); return; }
    
    const username = document.getElementById('newDbUsername')?.value.trim() || '';
    const r = await apiCall('create_db.php', { name, createUser, username, password });
    const el = document.getElementById('createDbResult');
    el.style.display = 'block';
    if (r.ok) {
        let msg = '✅ Database <strong>' + name + '</strong> created!';
        if (r.user) {
            msg += '<br><br><strong>Credentials:</strong>';
            msg += '<br>User: <code>' + r.user.user + '</code>';
            msg += '<br>Password: <code>' + r.user.password + '</code>';
            msg += '<br><br><button class="btn btn-sm btn-primary" onclick="copyField(\'newDbPassword\')" style="margin-right:8px">📋 Copy Password</button>';
        }
        msg += '<button class="btn btn-sm" style="background:var(--border);color:var(--text)" onclick="location.reload()">Done — Close</button>';
        el.innerHTML = '<div style="color:var(--accent2)">' + msg + '</div>';
        showToast('Database created!');
        // NO auto-reload — let user copy credentials first
    } else { el.innerHTML = '<div style="color:var(--danger)">❌ ' + r.error + '</div>'; }
}

async function dropDatabase(name) {
    if (!confirm('⚠️ DROP database "' + name + '"? This cannot be undone!')) return;
    if (!confirm('REALLY drop "' + name + '"?')) return;
    const r = await apiCall('drop_db.php', { name });
    if (r.ok) { showToast('Database dropped'); location.reload(); }
    else showToast(r.error, 'error');
}

async function createUser() {
    const user = document.getElementById('newUserName').value.trim();
    const pass = document.getElementById('newUserPass').value;
    const host = document.getElementById('newUserHost').value;
    const dbs = [...document.querySelectorAll('.newUserDbCheck:checked')].map(cb => cb.value);
    if (!user || !pass) { showToast('Username and password required', 'error'); return; }
    const r = await apiCall('manage_user.php', { action: 'create', user, password: pass, host, databases: dbs });
    const el = document.getElementById('createUserResult');
    el.style.display = 'block';
    if (r.ok) {
        el.innerHTML = '<div style="color:var(--accent2)">✅ User <strong>' + user + '</strong> created!<br><br><button class="btn btn-sm" style="background:var(--border);color:var(--text)" onclick="location.reload()">Done — Close</button></div>';
        showToast('User created!');
    } else { el.innerHTML = '<div style="color:var(--danger)">❌ ' + r.error + '</div>'; }
}

async function editUser(user, host) {
    document.getElementById('editUserTitle').textContent = user + '@' + host;
    document.getElementById('editUserOrigName').value = user;
    document.getElementById('editUserOrigHost').value = host;
    document.getElementById('editUserPass').value = '';
    document.getElementById('editUserResult').style.display = 'none';

    // Reset all checkboxes
    document.querySelectorAll('.editUserDbCheck').forEach(cb => cb.checked = false);

    // Fetch current grants and pre-check
    const r = await apiCall('manage_user.php', { action: 'grants', user, host });
    if (r.ok && r.databases) {
        r.databases.forEach(db => {
            const cb = document.querySelector('.editUserDbCheck[value="' + db + '"]');
            if (cb) cb.checked = true;
        });
    }

    document.getElementById('editUserModal').classList.add('show');
}

async function saveEditUser() {
    const user = document.getElementById('editUserOrigName').value;
    const host = document.getElementById('editUserOrigHost').value;
    const pass = document.getElementById('editUserPass').value;
    
    // Collect checked databases
    const grantDbs = [];
    const revokeDbs = [];
    document.querySelectorAll('.editUserDbCheck').forEach(cb => {
        if (cb.checked) grantDbs.push(cb.value);
        else revokeDbs.push(cb.value);
    });

    const r = await apiCall('manage_user.php', { action: 'edit', user, host, password: pass || null, grantDbs, revokeDbs });
    const el = document.getElementById('editUserResult');
    el.style.display = 'block';
    if (r.ok) {
        el.innerHTML = '<div style="color:var(--accent2)">✅ User updated!<br><br><button class="btn btn-sm" style="background:var(--border);color:var(--text)" onclick="location.reload()">Done — Close</button></div>';
        showToast('User updated!');
    } else { el.innerHTML = '<div style="color:var(--danger)">❌ ' + r.error + '</div>'; }
}

async function dropUser(user, host) {
    if (!confirm('Drop user "' + user + '"@"' + host + '"?')) return;
    const r = await apiCall('manage_user.php', { action: 'drop', user, host });
    if (r.ok) { showToast('User dropped'); location.reload(); }
    else showToast(r.error, 'error');
}
</script>
