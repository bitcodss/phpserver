<?php
/**
 * Backup tab — Jobs + Snapshots + per-job runs viewer.
 * All work is done client-side via /admin/api/backup.php.
 */
?>
<h2 class="page-title">🗄️ Backup</h2>

<div class="card" style="margin-bottom:20px;padding:14px 20px">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
        <div>
            <strong>Integrity check (weekly):</strong>
            <span id="checkStatus" style="color:var(--muted)">…</span>
        </div>
        <button class="btn btn-sm btn-primary" onclick="refreshAll()">🔄 Refresh</button>
    </div>
</div>

<!-- ============================== JOBS ============================== -->
<div style="margin-bottom: 16px;display:flex;justify-content:space-between;align-items:center">
    <h3>Jobs</h3>
    <button class="btn btn-primary" onclick="openJobModal()">+ Add Job</button>
</div>
<div class="table-wrap" style="margin-bottom:24px">
    <table>
        <thead><tr>
            <th>Label</th><th>Kind</th><th>Target</th><th>Schedule</th>
            <th>Last run</th><th>On</th><th style="width:200px">Actions</th>
        </tr></thead>
        <tbody id="jobsBody"><tr><td colspan="7" style="color:var(--muted)">loading…</td></tr></tbody>
    </table>
</div>

<!-- ============================ SNAPSHOTS =========================== -->
<div style="margin-bottom: 12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h3>Snapshots</h3>
    <div style="display:flex;gap:8px">
        <select id="filterJob" onchange="renderSnapshots()" style="padding:6px 10px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text)">
            <option value="">All jobs</option>
        </select>
    </div>
</div>
<div class="table-wrap">
    <table>
        <thead><tr>
            <th>Time</th><th>Job</th><th>Kind</th><th>Size</th><th style="width:260px">Actions</th>
        </tr></thead>
        <tbody id="snapsBody"><tr><td colspan="5" style="color:var(--muted)">loading…</td></tr></tbody>
    </table>
</div>

<!-- =========================== JOB MODAL ============================ -->
<div id="jobModal" class="modal-bg">
  <div class="modal" style="max-width:560px">
    <h3 id="jobModalTitle">Add Job</h3>
    <div class="form-group">
      <label>Kind</label>
      <select id="jobKind" onchange="onKindChange()">
        <option value="db">Database</option>
        <option value="site">Site</option>
      </select>
    </div>
    <div class="form-group">
      <label>Label</label>
      <input id="jobLabel" placeholder="e.g. opc_db daily 01:00">
    </div>
    <div class="form-group" id="dbTargetGroup">
      <label>Database</label>
      <select id="jobDatabase" onchange="loadTables()"><option>(loading…)</option></select>
    </div>
    <div class="form-group" id="dbTablesGroup">
      <label>Tables (leave empty for all)</label>
      <select id="jobTables" multiple size="6" style="height:auto"></select>
      <small style="color:var(--muted)">Hold Ctrl/Cmd to multi-select.</small>
    </div>
    <div class="form-group" id="siteTargetGroup" style="display:none">
      <label>Site</label>
      <select id="jobSite"><option>(loading…)</option></select>
    </div>
    <div class="form-group" id="siteExcludesGroup" style="display:none">
      <label>Extra excludes (one per line; appended to defaults)</label>
      <textarea id="jobExcludes" placeholder="*/storage/cache&#10;*/data/uploads/tmp"></textarea>
      <small style="color:var(--muted)">Defaults: */logs */.git */node_modules */cache */tmp *.log */vendor</small>
    </div>
    <div class="form-group">
      <label>Schedule</label>
      <select id="jobSchedKind" onchange="renderScheduleInputs()">
        <option value="daily">Daily</option>
        <option value="weekly">Weekly</option>
        <option value="monthly">Monthly</option>
      </select>
      <div id="schedRow" style="display:flex;gap:8px;margin-top:8px">
        <select id="jobSchedDay" style="display:none">
          <option>Mon</option><option>Tue</option><option>Wed</option><option>Thu</option>
          <option>Fri</option><option>Sat</option><option>Sun</option>
        </select>
        <input id="jobSchedDom" type="number" min="1" max="28" placeholder="day (1-28)" style="display:none;width:140px">
        <input id="jobSchedTime" type="time" value="01:00" style="width:140px">
      </div>
      <small style="color:var(--muted)">All times Asia/Bangkok.</small>
    </div>
    <div class="form-group">
      <label>Retention (days)</label>
      <input id="jobRetention" type="number" min="1" max="3650" value="30" style="width:140px">
    </div>
    <div class="form-group">
      <label><input id="jobEnabled" type="checkbox" checked> Enabled</label>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end">
      <button class="btn" onclick="closeJobModal()" style="background:var(--border);color:var(--text)">Cancel</button>
      <button class="btn btn-primary" onclick="saveJob()">Save</button>
    </div>
  </div>
</div>

<!-- =========================== RUNS MODAL =========================== -->
<div id="runsModal" class="modal-bg">
  <div class="modal" style="max-width:820px">
    <h3 id="runsModalTitle">Recent runs</h3>
    <div id="runsBody" style="max-height:60vh;overflow-y:auto"></div>
    <div style="display:flex;justify-content:flex-end;margin-top:16px">
      <button class="btn" onclick="closeRunsModal()" style="background:var(--border);color:var(--text)">Close</button>
    </div>
  </div>
</div>

<script>
let _jobs = [];
let _state = { runs: {} };
let _snaps = [];
let _editingId = null;
let _targets = { databases: [], sites: [] };

function api(action, payload={}) {
  return apiCall('backup.php', Object.assign({action}, payload));
}
function fmtTime(s) {
  if (!s) return '–';
  const d = new Date(s);
  return d.toLocaleString('en-GB', {hour12:false}).replace(',', '');
}
function fmtSize(n) {
  if (!n && n !== 0) return '–';
  if (n < 1024) return n + ' B';
  if (n < 1024*1024) return (n/1024).toFixed(1) + ' KB';
  if (n < 1024*1024*1024) return (n/1024/1024).toFixed(1) + ' MB';
  return (n/1024/1024/1024).toFixed(2) + ' GB';
}
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function refreshAll() {
  const [jobs, runs, snaps] = await Promise.all([
    api('jobs-get'), api('runs'), api('snapshots'),
  ]);
  _jobs  = jobs?.jobs ?? [];
  _state = { runs: runs?.runs ?? {} };
  _snaps = snaps?.snapshots ?? [];
  renderJobs();
  renderSnapshots();
  renderCheckStatus();
}

function renderCheckStatus() {
  const r = _state.runs?.__system_check__?.history?.slice(-1)[0];
  const el = document.getElementById('checkStatus');
  if (!r) { el.textContent = 'never run yet'; el.style.color='var(--muted)'; return; }
  if (r.status === 'ok') {
    el.innerHTML = `<span class="badge badge-ok">✓ ${esc(fmtTime(r.ended_at))}</span>
      <span title="5% of stored data is verified each week; full coverage in ~20 weeks." style="color:var(--muted);margin-left:6px">5% subset verified weekly</span>`;
  } else {
    el.innerHTML = `<span class="badge badge-err">✗ FAILED at ${esc(fmtTime(r.ended_at))}</span>`;
  }
}

function renderJobs() {
  const tbody = document.getElementById('jobsBody');
  if (!_jobs.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="color:var(--muted)">No jobs yet. Add one.</td></tr>`;
    return;
  }
  tbody.innerHTML = _jobs.map(j => {
    const last = _state.runs?.[j.id]?.history?.slice(-1)[0];
    const lastBadge = !last ? '<span style="color:var(--muted)">never</span>'
      : last.status === 'ok'
        ? `<span class="badge badge-ok">${esc(fmtTime(last.ended_at))}</span>`
        : `<span class="badge badge-err">✗ ${esc(fmtTime(last.ended_at))}</span>`;
    const target = j.kind === 'db' ? esc(j.database)
                 : j.kind === 'site' ? esc(j.site)
                 : '<i>—</i>';
    const sys = j.kind === 'system_check';
    return `<tr>
      <td><strong>${esc(j.label)}</strong>${sys ? ' <span style="color:var(--muted)">(system)</span>' : ''}</td>
      <td>${esc(j.kind)}</td>
      <td>${target}</td>
      <td><code>${esc(j.schedule)}</code></td>
      <td>${lastBadge}</td>
      <td>${j.enabled ? '<span class="badge badge-ok">on</span>' : '<span class="badge badge-warn">off</span>'}</td>
      <td>
        <button class="btn btn-sm btn-primary" onclick="runJob('${esc(j.id)}')" ${sys ? '' : ''}>Run</button>
        <button class="btn btn-sm" style="background:var(--border);color:var(--text)" onclick="viewRuns('${esc(j.id)}')">Runs</button>
        ${sys ? '' : `<button class="btn btn-sm" style="background:var(--border);color:var(--text)" onclick="editJob('${esc(j.id)}')">Edit</button>`}
        ${sys ? '' : `<button class="btn btn-sm btn-danger" onclick="deleteJob('${esc(j.id)}')">×</button>`}
      </td>
    </tr>`;
  }).join('');
  // Populate filter dropdown
  const sel = document.getElementById('filterJob');
  const cur = sel.value;
  sel.innerHTML = `<option value="">All jobs</option>` + _jobs.map(j => `<option value="${esc(j.id)}">${esc(j.label)}</option>`).join('');
  sel.value = cur;
}

function renderSnapshots() {
  const filter = document.getElementById('filterJob').value;
  const tbody = document.getElementById('snapsBody');
  const list = _snaps
    .filter(s => !filter || s.job_id === filter)
    .sort((a,b) => (b.time||'').localeCompare(a.time||''));
  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="5" style="color:var(--muted)">No snapshots yet.</td></tr>`;
    return;
  }
  tbody.innerHTML = list.map(s => {
    const jobLabel = (_jobs.find(j => j.id === s.job_id) || {}).label
                  || (s.job_id ? esc(s.job_id) : '<i>(legacy)</i>');
    const isDb = s.kind === 'db';
    const lastSize = _state.runs?.[s.job_id]?.history?.slice(-1)[0]?.size_bytes;
    const downloadBtn = isDb
      ? `<a class="btn btn-sm btn-primary" href="/admin/api/backup.php?action=download&snapshot_id=${esc(s.id)}" download>Download</a>`
      : '';
    return `<tr>
      <td>${esc(fmtTime(s.time))}<br><small style="color:var(--muted)"><code>${esc(s.id)}</code></small></td>
      <td>${jobLabel}</td>
      <td>${esc(s.kind ?? '–')}</td>
      <td>${lastSize ? esc(fmtSize(lastSize)) : '<span style="color:var(--muted)">?</span>'}</td>
      <td>
        <button class="btn btn-sm" style="background:var(--border);color:var(--text)" onclick="restoreSnap('${esc(s.id)}')">Restore</button>
        ${downloadBtn}
        <button class="btn btn-sm btn-danger" onclick="forgetSnap('${esc(s.id)}')">×</button>
      </td>
    </tr>`;
  }).join('');
}

async function runJob(id) {
  const r = await api('run', {job_id: id});
  if (r.ok) showToast('Job started: ' + (r.run?.status || 'ok'));
  else showToast('Run failed: ' + (r.error || 'unknown'), 'error');
  refreshAll();
}

async function deleteJob(id) {
  if (!confirm('Delete this job? Past snapshots remain in B2.')) return;
  const newJobs = _jobs.filter(j => j.id !== id);
  const r = await api('jobs-put', {jobs: newJobs});
  if (r.ok) { showToast('Deleted'); refreshAll(); }
  else showToast('Delete failed: ' + (r.error || ''), 'error');
}

async function restoreSnap(id) {
  if (!confirm('Restore snapshot ' + id + ' into /var/cid-restores/' + id + '/?\n\nNothing in /var/www/sites or the database is overwritten — you copy from the staging dir manually.')) return;
  const r = await api('restore', {snapshot_id: id});
  if (r.ok) alert('Restored to: ' + r.path + '\n\nCopy from there manually.');
  else showToast('Restore failed: ' + (r.error || ''), 'error');
}

async function forgetSnap(id) {
  const confirmText = id;
  const ans = prompt('Permanently delete snapshot from B2. Type the snapshot id "' + id + '" to confirm:');
  if (ans !== confirmText) return;
  const r = await api('forget', {snapshot_id: id});
  if (r.ok) { showToast('Forgotten'); refreshAll(); }
  else showToast('Forget failed: ' + (r.error || ''), 'error');
}

// --- Modal: add/edit job ---
async function openJobModal() {
  _editingId = null;
  document.getElementById('jobModalTitle').textContent = 'Add Job';
  document.getElementById('jobLabel').value = '';
  document.getElementById('jobKind').value = 'db';
  document.getElementById('jobSchedKind').value = 'daily';
  document.getElementById('jobSchedTime').value = '01:00';
  document.getElementById('jobRetention').value = 30;
  document.getElementById('jobEnabled').checked = true;
  document.getElementById('jobExcludes').value = '';
  renderScheduleInputs();
  onKindChange();
  if (!_targets.databases.length && !_targets.sites.length) {
    const t = await api('list-targets');
    _targets = {databases: t.databases || [], sites: t.sites || []};
  }
  document.getElementById('jobDatabase').innerHTML = _targets.databases.map(d => `<option value="${esc(d)}">${esc(d)}</option>`).join('');
  document.getElementById('jobSite').innerHTML = _targets.sites.map(s => `<option value="${esc(s)}">${esc(s)}</option>`).join('');
  loadTables();
  document.getElementById('jobModal').classList.add('show');
}
function editJob(id) {
  const j = _jobs.find(x => x.id === id);
  if (!j) return;
  openJobModal();
  setTimeout(() => {
    _editingId = id;
    document.getElementById('jobModalTitle').textContent = 'Edit: ' + j.label;
    document.getElementById('jobLabel').value = j.label || '';
    document.getElementById('jobKind').value = j.kind;
    onKindChange();
    document.getElementById('jobRetention').value = j.retention_days || 30;
    document.getElementById('jobEnabled').checked = !!j.enabled;
    if (j.kind === 'db') {
      document.getElementById('jobDatabase').value = j.database;
      loadTables().then(() => {
        const sel = document.getElementById('jobTables');
        Array.from(sel.options).forEach(o => o.selected = (j.tables || []).includes(o.value));
      });
    } else if (j.kind === 'site') {
      document.getElementById('jobSite').value = j.site;
      document.getElementById('jobExcludes').value = (j.excludes || []).join('\n');
    }
    // Parse schedule
    const parts = (j.schedule || '').split(' ');
    document.getElementById('jobSchedKind').value = parts[0] || 'daily';
    renderScheduleInputs();
    if (parts[0] === 'daily') {
      document.getElementById('jobSchedTime').value = parts[1] || '01:00';
    } else if (parts[0] === 'weekly') {
      document.getElementById('jobSchedDay').value  = parts[1] || 'Mon';
      document.getElementById('jobSchedTime').value = parts[2] || '01:00';
    } else if (parts[0] === 'monthly') {
      document.getElementById('jobSchedDom').value  = parts[1] || '1';
      document.getElementById('jobSchedTime').value = parts[2] || '01:00';
    }
  }, 50);
}
function closeJobModal() { document.getElementById('jobModal').classList.remove('show'); }

function onKindChange() {
  const kind = document.getElementById('jobKind').value;
  document.getElementById('dbTargetGroup').style.display      = kind === 'db' ? '' : 'none';
  document.getElementById('dbTablesGroup').style.display      = kind === 'db' ? '' : 'none';
  document.getElementById('siteTargetGroup').style.display    = kind === 'site' ? '' : 'none';
  document.getElementById('siteExcludesGroup').style.display  = kind === 'site' ? '' : 'none';
}

async function loadTables() {
  const db = document.getElementById('jobDatabase').value;
  const sel = document.getElementById('jobTables');
  sel.innerHTML = '<option disabled>loading…</option>';
  if (!db) { sel.innerHTML = ''; return; }
  const r = await api('list-tables', {database: db});
  sel.innerHTML = (r.tables || []).map(t => `<option value="${esc(t)}">${esc(t)}</option>`).join('');
}

function renderScheduleInputs() {
  const kind = document.getElementById('jobSchedKind').value;
  document.getElementById('jobSchedDay').style.display = (kind === 'weekly') ? '' : 'none';
  document.getElementById('jobSchedDom').style.display = (kind === 'monthly') ? '' : 'none';
}

function buildSchedule() {
  const kind = document.getElementById('jobSchedKind').value;
  const time = document.getElementById('jobSchedTime').value || '01:00';
  if (kind === 'daily') return 'daily ' + time;
  if (kind === 'weekly') return 'weekly ' + document.getElementById('jobSchedDay').value + ' ' + time;
  if (kind === 'monthly') {
    const d = parseInt(document.getElementById('jobSchedDom').value || '1', 10);
    return 'monthly ' + d + ' ' + time;
  }
  return 'daily ' + time;
}

function buildJob() {
  const kind = document.getElementById('jobKind').value;
  const label = document.getElementById('jobLabel').value.trim() || 'untitled';
  const schedule = buildSchedule();
  const retention_days = parseInt(document.getElementById('jobRetention').value || '30', 10);
  const enabled = document.getElementById('jobEnabled').checked;
  const baseId = _editingId
    || (kind === 'db' ? 'db-' + document.getElementById('jobDatabase').value
        : 'site-' + (document.getElementById('jobSite').value || 'x'));
  const j = {
    id: baseId.replace(/[^A-Za-z0-9_-]/g, '_'),
    label, kind, schedule, retention_days, enabled,
    created_at: new Date().toISOString(),
  };
  if (kind === 'db') {
    j.database = document.getElementById('jobDatabase').value;
    j.tables   = Array.from(document.getElementById('jobTables').selectedOptions).map(o => o.value);
  } else if (kind === 'site') {
    j.site     = document.getElementById('jobSite').value;
    j.excludes = document.getElementById('jobExcludes').value.split('\n').map(s => s.trim()).filter(Boolean);
  }
  return j;
}

async function saveJob() {
  const j = buildJob();
  let newJobs;
  if (_editingId) {
    newJobs = _jobs.map(x => x.id === _editingId ? j : x);
  } else {
    // Don't allow duplicate ids
    if (_jobs.some(x => x.id === j.id)) {
      showToast('A job with this target already exists. Edit it instead.', 'error');
      return;
    }
    newJobs = [..._jobs, j];
  }
  const r = await api('jobs-put', {jobs: newJobs});
  if (r.ok) { showToast('Saved'); closeJobModal(); refreshAll(); }
  else showToast('Save failed: ' + (r.error || ''), 'error');
}

// --- Modal: per-job runs ---
function viewRuns(id) {
  const j = _jobs.find(x => x.id === id);
  const hist = _state.runs?.[id]?.history || [];
  document.getElementById('runsModalTitle').textContent = 'Runs: ' + (j ? j.label : id);
  const body = document.getElementById('runsBody');
  if (!hist.length) {
    body.innerHTML = '<p style="color:var(--muted)">No runs yet.</p>';
  } else {
    body.innerHTML = hist.slice().reverse().map(r => `
      <details style="border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-bottom:8px">
        <summary>
          ${r.status === 'ok'
            ? '<span class="badge badge-ok">ok</span>'
            : '<span class="badge badge-err">error</span>'}
          ${esc(fmtTime(r.started_at))} → ${esc(fmtTime(r.ended_at))}
          ${r.size_bytes ? ' · ' + esc(fmtSize(r.size_bytes)) : ''}
          ${r.snapshot_id ? ' · <code>' + esc(r.snapshot_id) + '</code>' : ''}
        </summary>
        ${r.stdout_tail ? '<details style="margin-top:8px"><summary>stdout</summary><pre class="log-output">' + esc(r.stdout_tail) + '</pre></details>' : ''}
        ${r.stderr_tail ? '<details style="margin-top:4px"><summary>stderr</summary><pre class="log-output">' + esc(r.stderr_tail) + '</pre></details>' : ''}
      </details>`).join('');
  }
  document.getElementById('runsModal').classList.add('show');
}
function closeRunsModal() { document.getElementById('runsModal').classList.remove('show'); }

refreshAll();
</script>
