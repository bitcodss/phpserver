<?php
/**
 * Dashboard — Server Overview + Monitoring Graphs
 */

// Container status
$containers = ['cid-php74', 'cid-nginx', 'cid-mariadb', 'cid-phpmyadmin', 'cid-redis', 'cid-sftp'];
$statuses = [];
foreach ($containers as $c) {
    $out = shell_exec("docker inspect --format='{{.State.Status}}|{{.State.StartedAt}}' $c 2>&1");
    $parts = explode('|', trim($out ?? ''));
    $dateStr = isset($parts[1]) ? preg_replace('/\.\d+Z$/', 'Z', $parts[1]) : '';
    $ts = strtotime($dateStr);
    $statuses[$c] = [
        'status' => $parts[0] ?? 'unknown',
        'started' => $ts ? date('Y-m-d H:i', $ts) : 'N/A'
    ];
}
$running = count(array_filter($statuses, fn($s) => $s['status'] === 'running'));

// Server info — read from host via metrics.json (collected by host cron)
$metricsFile = dirname(__DIR__) . '/data/metrics.json';
$latest = null;
if (file_exists($metricsFile)) {
    $allMetrics = json_decode(file_get_contents($metricsFile), true) ?: [];
    $latest = end($allMetrics) ?: null;
}

$loadAvg = $latest['load'] ?? '0';

$memTotal = (float)($latest['mem_total'] ?? 0);
$memUsed = (float)($latest['mem_used'] ?? 0);
$memTotalGB = round($memTotal / 1073741824, 2);
$memUsedGB = round($memUsed / 1073741824, 2);
$memPct = $memTotal > 0 ? round(($memUsed / $memTotal) * 100) : 0;

$diskTotal = (float)($latest['disk_total'] ?? 0);
$diskUsed = (float)($latest['disk_used'] ?? 0);
$diskTotalGB = round($diskTotal / 1073741824, 1);
$diskUsedGB = round($diskUsed / 1073741824, 1);
$diskPct = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100) : 0;

$uptimeSec = (int)($latest['uptime'] ?? 0);
$uptimeDays = floor($uptimeSec / 86400);
$uptimeHours = floor(($uptimeSec % 86400) / 3600);
$uptimeStr = $uptimeDays > 0 ? "{$uptimeDays}d {$uptimeHours}h" : "{$uptimeHours}h";

$cpuCores = (int)($latest['cpu_cores'] ?? 4);
$osInfo = 'Ubuntu 24.04 LTS';

// Sites count
$sitesDir = dirname(__DIR__, 2);
$sites = array_filter(glob(dirname($sitesDir) . '/*'), 'is_dir');
$siteCount = count($sites);

// DB count
$dbCountRaw = trim(shell_exec("docker exec cid-mariadb mysql -uroot -pCidMariaDB2026! -se \"SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name NOT IN ('information_schema','performance_schema','mysql','sys');\" 2>/dev/null") ?: '0');
?>

<h2 class="page-title">📊 Dashboard</h2>

<!-- Server Info Bar -->
<div class="card" style="margin-bottom:24px;padding:16px 24px">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
        <strong style="font-size:1.1rem">🖥️ opc-server</strong>
        <span style="color:var(--muted);font-size:0.85rem"><?= $osInfo ?> • <?= $cpuCores ?> cores • 139.59.119.101</span>
    </div>
    <div class="grid" style="grid-template-columns: repeat(4, 1fr); gap:16px; margin:0">
        <!-- Load -->
        <div style="text-align:center">
            <div style="color:var(--muted);font-size:0.75rem;margin-bottom:4px">⚡ Load</div>
            <div style="font-size:1.8rem;font-weight:700;color:<?= $loadAvg > $cpuCores ? 'var(--danger)' : ($loadAvg > $cpuCores * 0.7 ? '#eab308' : 'var(--accent2)') ?>"><?= $loadAvg ?></div>
        </div>
        <!-- Memory -->
        <div style="text-align:center">
            <div style="color:var(--muted);font-size:0.75rem;margin-bottom:4px">🧠 Memory</div>
            <div style="background:var(--bg);border-radius:999px;height:8px;margin:8px 0;overflow:hidden">
                <div style="height:100%;width:<?= $memPct ?>%;background:<?= $memPct > 85 ? 'var(--danger)' : ($memPct > 70 ? '#eab308' : 'var(--accent)') ?>;border-radius:999px;transition:0.3s"></div>
            </div>
            <div style="font-size:0.8rem;color:var(--muted)"><?= $memUsedGB ?> GB / <?= $memTotalGB ?> GB</div>
        </div>
        <!-- Disk -->
        <div style="text-align:center">
            <div style="color:var(--muted);font-size:0.75rem;margin-bottom:4px">💾 Disk</div>
            <div style="background:var(--bg);border-radius:999px;height:8px;margin:8px 0;overflow:hidden">
                <div style="height:100%;width:<?= $diskPct ?>%;background:<?= $diskPct > 85 ? 'var(--danger)' : ($diskPct > 70 ? '#eab308' : 'var(--accent)') ?>;border-radius:999px;transition:0.3s"></div>
            </div>
            <div style="font-size:0.8rem;color:var(--muted)"><?= $diskUsedGB ?> GB / <?= $diskTotalGB ?> GB</div>
        </div>
        <!-- Uptime -->
        <div style="text-align:center">
            <div style="color:var(--muted);font-size:0.75rem;margin-bottom:4px">⏱️ Uptime</div>
            <div style="font-size:1.8rem;font-weight:700;color:var(--accent2)"><?= $uptimeStr ?></div>
            <div style="font-size:0.75rem;color:var(--muted)"><?= date('d M Y') ?></div>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom:24px">
    <div class="card" style="text-align:center">
        <div style="font-size:0.85rem;color:var(--muted)">🌐 Sites</div>
        <div style="font-size:1.5rem;font-weight:700"><?= $siteCount ?></div>
    </div>
    <div class="card" style="text-align:center">
        <div style="font-size:0.85rem;color:var(--muted)">🗄️ Databases</div>
        <div style="font-size:1.5rem;font-weight:700"><?= $dbCountRaw ?></div>
    </div>
    <div class="card" style="text-align:center">
        <div style="font-size:0.85rem;color:var(--muted)">📦 Containers</div>
        <div style="font-size:1.5rem;font-weight:700;color:<?= $running === count($containers) ? 'var(--accent2)' : 'var(--danger)' ?>"><?= $running ?>/<?= count($containers) ?></div>
    </div>
    <div class="card" style="text-align:center">
        <div style="font-size:0.85rem;color:var(--muted)">🐘 PHP</div>
        <div style="font-size:1.5rem;font-weight:700;color:var(--accent2)">7.4</div>
    </div>
</div>

<!-- Monitoring Graphs -->
<div class="grid" style="grid-template-columns: 1fr 1fr; margin-bottom:24px">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <h3 style="font-size:0.95rem">⚡ Load Average</h3>
            <select id="loadRange" onchange="updateCharts()" style="padding:4px 8px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:0.8rem">
                <option value="1">1 Hour</option>
                <option value="6">6 Hours</option>
                <option value="24" selected>24 Hours</option>
                <option value="168">7 Days</option>
            </select>
        </div>
        <div style="position:relative;height:200px"><canvas id="loadChart"></canvas></div>
    </div>
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <h3 style="font-size:0.95rem">🧠 Memory Usage</h3>
        </div>
        <div style="position:relative;height:200px"><canvas id="memChart"></canvas></div>
    </div>
</div>
<div class="grid" style="grid-template-columns: 1fr 1fr; margin-bottom:24px">
    <div class="card">
        <div style="margin-bottom:12px"><h3 style="font-size:0.95rem">💾 Disk Usage</h3></div>
        <div style="position:relative;height:200px"><canvas id="diskChart"></canvas></div>
    </div>
    <div class="card">
        <div style="margin-bottom:12px"><h3 style="font-size:0.95rem">📊 Load + Memory Combined</h3></div>
        <div style="position:relative;height:200px"><canvas id="combinedChart"></canvas></div>
    </div>
</div>

<!-- Container Status -->
<div class="table-wrap">
    <h3>Container Status</h3>
    <table>
        <thead><tr><th>Container</th><th>Status</th><th>Started</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($statuses as $name => $info): ?>
            <tr>
                <td><strong><?= htmlspecialchars($name) ?></strong></td>
                <td><span class="badge <?= $info['status'] === 'running' ? 'badge-ok' : 'badge-err' ?>"><?= htmlspecialchars($info['status']) ?></span></td>
                <td style="color:var(--muted)"><?= htmlspecialchars($info['started']) ?></td>
                <td><button class="btn btn-sm btn-primary" onclick="restartContainer('<?= $name ?>')">Restart</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
let metricsData = [];
let loadChart, memChart, diskChart, combinedChart;
let refreshTimer = null;

// Downsample: keep max 60 points for smooth rendering
function downsample(arr, maxPoints) {
    if (arr.length <= maxPoints) return arr;
    const step = arr.length / maxPoints;
    const result = [];
    for (let i = 0; i < maxPoints; i++) {
        result.push(arr[Math.floor(i * step)]);
    }
    return result;
}

async function loadMetrics() {
    try {
        const r = await fetch('/admin/data/metrics.json?_=' + Date.now());
        metricsData = await r.json();
    } catch(e) { metricsData = []; }
    updateCharts();
}

function filterByHours(hours) {
    const cutoff = Date.now()/1000 - hours * 3600;
    return metricsData.filter(m => m.ts > cutoff);
}

function formatTime(ts) {
    const d = new Date(ts * 1000);
    return d.toLocaleTimeString('en-GB', {hour:'2-digit',minute:'2-digit',hour12:false});
}

function makeOpts(extra) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 300 },
        plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
        scales: {
            x: { ticks: { color: '#94a3b8', font: {size:10}, maxTicksLimit: 8, maxRotation: 0 }, grid: { color: '#1e293b' } },
            y: { ticks: { color: '#94a3b8', font: {size:10} }, grid: { color: '#1e293b' }, beginAtZero: true }
        },
        elements: { point: { radius: 0 }, line: { tension: 0.3, borderWidth: 2 } },
        ...extra
    };
}

function destroyAll() {
    [loadChart, memChart, diskChart, combinedChart].forEach(c => { if(c) c.destroy(); });
    loadChart = memChart = diskChart = combinedChart = null;
}

function updateCharts() {
    destroyAll();
    const hours = parseInt(document.getElementById('loadRange').value);
    const raw = filterByHours(hours);
    const data = downsample(raw, 60);
    if (!data.length) return;

    const labels = data.map(m => formatTime(m.ts));

    loadChart = new Chart(document.getElementById('loadChart'), {
        type: 'line',
        data: { labels, datasets: [{ data: data.map(m => m.load), borderColor: '#38bdf8', backgroundColor: 'rgba(56,189,248,0.1)', fill: true }] },
        options: makeOpts({ scales: { x: { ticks: { color:'#94a3b8', font:{size:10}, maxTicksLimit:8, maxRotation:0 }, grid:{color:'#1e293b'} }, y: { ticks:{color:'#94a3b8',font:{size:10}}, grid:{color:'#1e293b'}, beginAtZero:true, suggestedMax: Math.max(1, ...data.map(m=>m.load))*1.3 } } })
    });

    memChart = new Chart(document.getElementById('memChart'), {
        type: 'line',
        data: { labels, datasets: [{ data: data.map(m => +(m.mem_used/1073741824).toFixed(2)), borderColor: '#a78bfa', backgroundColor: 'rgba(167,139,250,0.1)', fill: true }] },
        options: makeOpts({ scales: { x: { ticks:{color:'#94a3b8',font:{size:10},maxTicksLimit:8,maxRotation:0}, grid:{color:'#1e293b'} }, y: { ticks:{color:'#94a3b8',font:{size:10}}, grid:{color:'#1e293b'}, beginAtZero:true, suggestedMax: +(data[0].mem_total/1073741824*1.1).toFixed(1), title:{display:true,text:'GB',color:'#94a3b8'} } } })
    });

    diskChart = new Chart(document.getElementById('diskChart'), {
        type: 'line',
        data: { labels, datasets: [{ data: data.map(m => +(m.disk_used/1073741824).toFixed(1)), borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.1)', fill: true }] },
        options: makeOpts({ scales: { x: { ticks:{color:'#94a3b8',font:{size:10},maxTicksLimit:8,maxRotation:0}, grid:{color:'#1e293b'} }, y: { ticks:{color:'#94a3b8',font:{size:10}}, grid:{color:'#1e293b'}, beginAtZero:true, suggestedMax: +(data[0].disk_total/1073741824).toFixed(0), title:{display:true,text:'GB',color:'#94a3b8'} } } })
    });

    combinedChart = new Chart(document.getElementById('combinedChart'), {
        type: 'line',
        data: { labels, datasets: [
            { label: 'Load', data: data.map(m => m.load), borderColor: '#38bdf8', backgroundColor: 'transparent', yAxisID: 'y' },
            { label: 'Mem %', data: data.map(m => +(m.mem_used/m.mem_total*100).toFixed(1)), borderColor: '#a78bfa', backgroundColor: 'transparent', yAxisID: 'y1' },
        ]},
        options: makeOpts({
            plugins: { legend: { display:true, labels:{color:'#94a3b8',font:{size:11}} }, tooltip:{mode:'index',intersect:false} },
            scales: {
                x: { ticks:{color:'#94a3b8',font:{size:10},maxTicksLimit:8,maxRotation:0}, grid:{color:'#1e293b'} },
                y: { position:'left', ticks:{color:'#94a3b8',font:{size:10}}, grid:{color:'#1e293b'}, beginAtZero:true, title:{display:true,text:'Load',color:'#38bdf8'} },
                y1: { position:'right', ticks:{color:'#94a3b8',font:{size:10}}, grid:{drawOnChartArea:false}, beginAtZero:true, suggestedMax:100, title:{display:true,text:'Mem %',color:'#a78bfa'} }
            }
        })
    });
}

// Load once, then refresh every 5 minutes (not every minute)
loadMetrics();
refreshTimer = setInterval(loadMetrics, 300000);

async function restartContainer(name) {
    if (!confirm('Restart ' + name + '?')) return;
    const r = await apiCall('container_action.php', {action: 'restart', container: name});
    showToast(r.ok ? name + ' restarted!' : 'Error: ' + r.error, r.ok ? 'success' : 'error');
    setTimeout(() => location.reload(), 2000);
}
</script>
