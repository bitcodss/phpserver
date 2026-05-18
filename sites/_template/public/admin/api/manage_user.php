<?php
require __DIR__ . '/_bootstrap.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

function safeUser($s) { return preg_replace('/[^a-zA-Z0-9_]/', '', (string)$s); }
function safeHost($s) {
    $s = (string)$s;
    return ($s === 'localhost' || $s === '127.0.0.1' || $s === '::1') ? $s : '%';
}
function safeDb($s) { return preg_replace('/[^a-zA-Z0-9_]/', '', (string)$s); }
function safePass($s) {
    $s = (string)$s;
    if (!preg_match('/^[A-Za-z0-9!@#%^&*()_+=\-]{8,64}$/', $s)) return null;
    return $s;
}
function dbExec($sql) {
    $out = mysqlExec($sql);
    if (strpos($out, 'ERROR') !== false) return ['ok' => false, 'error' => trim($out)];
    return ['ok' => true];
}

switch ($action) {
    case 'grants':
        $user = safeUser($input['user'] ?? '');
        $host = safeHost($input['host'] ?? '%');
        if (!$user) { die(json_encode(['ok' => false, 'error' => 'Username required'])); }

        $out = mysqlQuery("SELECT DISTINCT Db FROM mysql.db WHERE User='$user' AND Host='$host';");
        $dbs = $out !== '' ? array_filter(array_map('trim', explode("\n", trim($out)))) : [];
        $dbs = array_map(fn($d) => str_replace('\\', '', $d), $dbs);
        echo json_encode(['ok' => true, 'databases' => array_values($dbs)]);
        break;

    case 'create':
        $user = safeUser($input['user'] ?? '');
        $pass = safePass($input['password'] ?? '');
        $host = safeHost($input['host'] ?? '%');
        $dbs = $input['databases'] ?? [];
        if (empty($dbs) && !empty($input['database'])) $dbs = [$input['database']];

        if (!$user) { die(json_encode(['ok' => false, 'error' => 'Username required'])); }
        if ($pass === null) { die(json_encode(['ok' => false, 'error' => 'Password must be 8-64 chars (alphanum + !@#%^&*()_+=- only)'])); }
        if (strlen($user) > 32) { die(json_encode(['ok' => false, 'error' => 'Username too long (max 32)'])); }

        $result = dbExec("CREATE USER IF NOT EXISTS '$user'@'$host' IDENTIFIED BY '$pass';");
        if (!$result['ok']) { echo json_encode($result); break; }

        foreach ($dbs as $db) {
            $db = safeDb($db);
            if ($db) dbExec("GRANT ALL PRIVILEGES ON `$db`.* TO '$user'@'$host';");
        }
        dbExec("FLUSH PRIVILEGES;");
        echo json_encode(['ok' => true]);
        break;

    case 'edit':
        $user = safeUser($input['user'] ?? '');
        $host = safeHost($input['host'] ?? '%');
        $passInput = $input['password'] ?? null;
        $grantDbs = $input['grantDbs'] ?? [];
        $revokeDbs = $input['revokeDbs'] ?? [];

        if (!$user) { die(json_encode(['ok' => false, 'error' => 'Username required'])); }

        if ($passInput !== null && $passInput !== '') {
            $pass = safePass($passInput);
            if ($pass === null) { die(json_encode(['ok' => false, 'error' => 'Password must be 8-64 chars (alphanum + !@#%^&*()_+=- only)'])); }
            $result = dbExec("ALTER USER '$user'@'$host' IDENTIFIED BY '$pass';");
            if (!$result['ok']) { echo json_encode($result); break; }
        }

        foreach ($revokeDbs as $db) {
            $db = safeDb($db);
            if ($db) dbExec("REVOKE ALL PRIVILEGES ON `$db`.* FROM '$user'@'$host';");
        }

        foreach ($grantDbs as $db) {
            $db = safeDb($db);
            if ($db) dbExec("GRANT ALL PRIVILEGES ON `$db`.* TO '$user'@'$host';");
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
