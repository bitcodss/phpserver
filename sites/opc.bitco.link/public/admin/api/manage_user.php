<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['authenticated'])) { die(json_encode(['ok' => false, 'error' => 'Unauthorized'])); }

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

function dbExec($sql) {
    $out = shell_exec("docker exec cid-mariadb mysql -uroot -pCidMariaDB2026! -e " . escapeshellarg($sql) . " 2>&1");
    if (strpos($out, 'ERROR') !== false) return ['ok' => false, 'error' => trim($out)];
    return ['ok' => true];
}

function safeUser($s) { return preg_replace('/[^a-zA-Z0-9_]/', '', $s); }
function safeHost($s) { return ($s === 'localhost' || $s === '127.0.0.1' || $s === '::1') ? $s : '%'; }
function safeDb($s) { return preg_replace('/[^a-zA-Z0-9_]/', '', $s); }

switch ($action) {
    case 'grants':
        // Get current database grants for a user
        $user = safeUser($input['user'] ?? '');
        $host = safeHost($input['host'] ?? '%');
        if (!$user) { die(json_encode(['ok' => false, 'error' => 'Username required'])); }
        
        $out = shell_exec("docker exec cid-mariadb mysql -uroot -pCidMariaDB2026! -se " . 
            escapeshellarg("SELECT DISTINCT Db FROM mysql.db WHERE User='$user' AND Host='$host';") . " 2>/dev/null");
        $dbs = $out ? array_filter(array_map('trim', explode("\n", trim($out)))) : [];
        // Unescape mysql db names (backslash-escaped)
        $dbs = array_map(fn($d) => str_replace('\\', '', $d), $dbs);
        echo json_encode(['ok' => true, 'databases' => array_values($dbs)]);
        break;

    case 'create':
        $user = safeUser($input['user'] ?? '');
        $pass = $input['password'] ?? '';
        $host = safeHost($input['host'] ?? '%');
        $dbs = $input['databases'] ?? [];
        // Legacy single db support
        if (empty($dbs) && !empty($input['database'])) $dbs = [$input['database']];
        
        if (!$user || !$pass) { die(json_encode(['ok' => false, 'error' => 'Username and password required'])); }
        if (strlen($user) > 32) { die(json_encode(['ok' => false, 'error' => 'Username too long (max 32)'])); }
        
        $result = dbExec("CREATE USER IF NOT EXISTS '$user'@'$host' IDENTIFIED BY '$pass';");
        if (!$result['ok']) { echo json_encode($result); break; }
        
        foreach ($dbs as $db) {
            $db = safeDb($db);
            if ($db) dbExec("GRANT ALL PRIVILEGES ON $db.* TO '$user'@'$host';");
        }
        dbExec("FLUSH PRIVILEGES;");
        echo json_encode(['ok' => true]);
        break;
        
    case 'edit':
        $user = safeUser($input['user'] ?? '');
        $host = safeHost($input['host'] ?? '%');
        $pass = $input['password'] ?? null;
        $grantDbs = $input['grantDbs'] ?? [];
        $revokeDbs = $input['revokeDbs'] ?? [];
        
        if (!$user) { die(json_encode(['ok' => false, 'error' => 'Username required'])); }
        
        // Change password if provided
        if ($pass) {
            $result = dbExec("ALTER USER '$user'@'$host' IDENTIFIED BY '$pass';");
            if (!$result['ok']) { echo json_encode($result); break; }
        }
        
        // Revoke databases
        foreach ($revokeDbs as $db) {
            $db = safeDb($db);
            if ($db) dbExec("REVOKE ALL PRIVILEGES ON $db.* FROM '$user'@'$host';");
        }
        
        // Grant databases
        foreach ($grantDbs as $db) {
            $db = safeDb($db);
            if ($db) dbExec("GRANT ALL PRIVILEGES ON $db.* TO '$user'@'$host';");
        }
        
        dbExec("FLUSH PRIVILEGES;");
        echo json_encode(['ok' => true]);
        break;
        
    case 'drop':
        $user = safeUser($input['user'] ?? '');
        $host = safeHost($input['host'] ?? '%');
        
        if (!$user) { die(json_encode(['ok' => false, 'error' => 'Username required'])); }
        if ($user === 'root') { die(json_encode(['ok' => false, 'error' => 'Cannot drop root user'])); }
        
        $result = dbExec("DROP USER IF EXISTS '$user'@'$host'; FLUSH PRIVILEGES;");
        echo json_encode($result);
        break;
        
    default:
        echo json_encode(['ok' => false, 'error' => 'Invalid action']);
}
