<?php
/**
 * ศ.Cid Admin Dashboard
 * PHP Infrastructure Manager
 */
session_start();

$adminUser = getenv('ADMIN_USER') ?: '';
$adminHash = getenv('ADMIN_PASS_HASH') ?: '';
if ($adminUser === '' || $adminHash === '') {
    http_response_code(500);
    exit('Admin credentials not configured (set ADMIN_USER and ADMIN_PASS_HASH in .env, then restart cid-php74).');
}

if (!isset($_SESSION['authenticated'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
        $userOk = hash_equals($adminUser, (string)$_POST['username']);
        $passOk = password_verify((string)$_POST['password'], $adminHash);
        if ($userOk && $passOk) {
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            header('Location: /admin/');
            exit;
        }
        // Emit 401 on failed login so fail2ban can match cleanly on the
        // nginx access log. The form still renders below so the human
        // experience is unchanged.
        http_response_code(401);
        $error = 'Invalid credentials';
    }
    showLogin($error ?? null);
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: /admin/');
    exit;
}

// Route
$page = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 ศ.Cid — Admin Dashboard</title>
    <style>
        :root { --bg: #0f172a; --card: #1e293b; --accent: #38bdf8; --accent2: #22c55e; --danger: #ef4444; --text: #e2e8f0; --muted: #94a3b8; --border: #334155; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }
        
        /* Sidebar */
        .sidebar { width: 240px; background: var(--card); border-right: 1px solid var(--border); padding: 24px 0; display: flex; flex-direction: column; min-height: 100vh; }
        .sidebar-logo { padding: 0 24px 24px; border-bottom: 1px solid var(--border); margin-bottom: 16px; }
        .sidebar-logo h1 { font-size: 1.2rem; color: var(--accent); }
        .sidebar-logo small { color: var(--muted); font-size: 0.75rem; }
        .nav-item { display: block; padding: 12px 24px; color: var(--muted); text-decoration: none; transition: all 0.2s; border-left: 3px solid transparent; }
        .nav-item:hover, .nav-item.active { color: var(--text); background: rgba(56,189,248,0.1); border-left-color: var(--accent); }
        .nav-item .icon { margin-right: 8px; }
        .sidebar-footer { margin-top: auto; padding: 16px 24px; border-top: 1px solid var(--border); }
        .sidebar-footer a { color: var(--danger); text-decoration: none; font-size: 0.85rem; }
        
        /* Main */
        .main { flex: 1; padding: 32px; overflow-y: auto; }
        .page-title { font-size: 1.5rem; margin-bottom: 24px; }
        
        /* Cards */
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 32px; }
        .card { background: var(--card); border-radius: 12px; padding: 24px; border: 1px solid var(--border); }
        .card-title { color: var(--muted); font-size: 0.85rem; margin-bottom: 8px; }
        .card-value { font-size: 1.5rem; font-weight: 700; }
        .card-value.ok { color: var(--accent2); }
        .card-value.warn { color: #eab308; }
        .card-value.err { color: var(--danger); }
        
        /* Table */
        .table-wrap { background: var(--card); border-radius: 12px; border: 1px solid var(--border); overflow: hidden; margin-bottom: 24px; }
        .table-wrap h3 { padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 20px; text-align: left; border-bottom: 1px solid var(--border); }
        th { color: var(--muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }
        td { font-size: 0.9rem; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .badge-ok { background: rgba(34,197,94,0.15); color: #22c55e; }
        .badge-err { background: rgba(239,68,68,0.15); color: #ef4444; }
        .badge-warn { background: rgba(234,179,8,0.15); color: #eab308; }
        
        /* Form */
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; color: var(--muted); font-size: 0.85rem; margin-bottom: 6px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 0.9rem; }
        .form-group textarea { min-height: 80px; font-family: monospace; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: 0.2s; }
        .btn-primary { background: var(--accent); color: #0f172a; }
        .btn-primary:hover { opacity: 0.9; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-sm { padding: 6px 14px; font-size: 0.8rem; }
        
        /* Logs */
        .log-output { background: #000; color: #22c55e; padding: 16px; border-radius: 8px; font-family: monospace; font-size: 0.8rem; max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
        
        /* Modal */
        .modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 100; align-items: center; justify-content: center; }
        .modal-bg.show { display: flex; }
        .modal { background: var(--card); border-radius: 16px; padding: 32px; max-width: 500px; width: 90%; border: 1px solid var(--border); }
        .modal h3 { margin-bottom: 20px; }
        
        /* Toast */
        .toast { position: fixed; top: 20px; right: 20px; padding: 12px 24px; border-radius: 8px; font-weight: 600; z-index: 200; display: none; }
        .toast.success { background: var(--accent2); color: #000; }
        .toast.error { background: var(--danger); color: #fff; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">
            <h1>🔧 ศ.Cid</h1>
            <small>PHP Infrastructure Manager</small>
        </div>
        <a href="?page=dashboard" class="nav-item <?= $page === 'dashboard' ? 'active' : '' ?>"><span class="icon">📊</span> Dashboard</a>
        <a href="?page=sites" class="nav-item <?= $page === 'sites' ? 'active' : '' ?>"><span class="icon">🌐</span> Sites</a>
        <a href="?page=php" class="nav-item <?= $page === 'php' ? 'active' : '' ?>"><span class="icon">🐘</span> PHP Settings</a>
        <a href="?page=database" class="nav-item <?= $page === 'database' ? 'active' : '' ?>"><span class="icon">🗄️</span> Database</a>
        <a href="?page=security" class="nav-item <?= $page === 'security' ? 'active' : '' ?>"><span class="icon">🛡️</span> Security</a>
        <a href="?page=logs" class="nav-item <?= $page === 'logs' ? 'active' : '' ?>"><span class="icon">📋</span> Logs</a>
        <div class="sidebar-footer">
            <a href="?logout=1">🚪 Logout</a>
        </div>
    </div>
    <div class="main">
        <?php
        switch ($page) {
            case 'dashboard': include __DIR__ . '/templates/dashboard.php'; break;
            case 'sites': include __DIR__ . '/templates/sites.php'; break;
            case 'php': include __DIR__ . '/templates/php.php'; break;
            case 'database': include __DIR__ . '/templates/database.php'; break;
            case 'security': include __DIR__ . '/templates/security.php'; break;
            case 'logs': include __DIR__ . '/templates/logs.php'; break;
            default: echo '<p>Page not found</p>';
        }
        ?>
    </div>
    <div id="toast" class="toast"></div>
    <script>
    const CSRF_TOKEN = <?= json_encode($_SESSION['csrf']) ?>;
    function showToast(msg, type='success') {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast ' + type;
        t.style.display = 'block';
        setTimeout(() => t.style.display = 'none', 3000);
    }
    async function apiCall(endpoint, data={}) {
        const res = await fetch('/admin/api/' + endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN},
            body: JSON.stringify(data)
        });
        return res.json();
    }
    </script>
</body>
</html>

<?php
function showLogin($error = null) {
?>
<!DOCTYPE html>
<html><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 ศ.Cid — Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login { background: #1e293b; padding: 48px; border-radius: 16px; width: 360px; }
        h1 { text-align: center; margin-bottom: 32px; color: #38bdf8; }
        .error { background: rgba(239,68,68,0.15); color: #ef4444; padding: 10px; border-radius: 8px; margin-bottom: 16px; text-align: center; font-size: 0.9rem; }
        label { display: block; color: #94a3b8; font-size: 0.85rem; margin-bottom: 6px; }
        input { width: 100%; padding: 10px 14px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #e2e8f0; font-size: 0.9rem; margin-bottom: 16px; }
        button { width: 100%; padding: 12px; background: #38bdf8; color: #0f172a; border: none; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; }
    </style>
</head><body>
    <form class="login" method="POST">
        <h1>🔧 ศ.Cid</h1>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <label>Username</label><input name="username" required autofocus>
        <label>Password</label><input name="password" type="password" required>
        <button type="submit">Login</button>
    </form>
</body></html>
<?php } ?>
