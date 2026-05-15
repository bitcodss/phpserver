<?php
/**
 * Backup tab — Jobs grouped by kind, snapshots nested inside each job.
 *
 * UX details:
 * - localStorage cache (10-min TTL) so reopening the tab is instant; manual
 *   "Refresh" bypasses the cache.
 * - Loading state shows a status line + skeleton rows instead of an empty
 *   "loading…" cell.
 * - Per-job expander shows the snapshots that belong to that job (newest
 *   first) plus the last-20 run history.
 * - Separate sections: 📊 Database backups · 🌐 Site backups · 🔍 Integrity
 *   check · 🗄️ Legacy snapshots (no job tag).
 * - Action column: Run / Edit / × (no duplicate "Runs" — history is inside
 *   the expansion).
 */
?>
<h2 class="page-title">🗄️ Backup</h2>

<div class="card" style="margin-bottom:20px;padding:14px 20px">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
        <div>
            <strong>Integrity check (weekly):</strong>
            <span id="checkStatus" style="color:var(--muted)">…</span>
        </div>
        <div style="display:flex;gap:10px;align-items:center">
            <span id="cacheState" style="color:var(--muted);font-size:0.8rem">…</span>
            <button class="btn btn-sm btn-primary" onclick="refreshAll(true)">🔄 Refresh</button>
        </div>
    </div>
</div>

<!-- ============================ DATABASE JOBS ============================ -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <h3>📊 Database backups</h3>
    <button class="btn btn-sm btn-primary" onclick="openJobModal('db')">+ Add DB job</button>
</div>
<div class="table-wrap" style="margin-bottom:24px">
    <table>
        <thead><tr>
            <th style="width:30px"></th>
            <th>Label</th><th>Database</th><th>Schedule</th><th>Retention</th>
            <th>Last run</th><th>On</th><th style="width:180px">Actions</th>
        </tr></thead>
        <tbody id="dbJobsBody"><tr><td colspan="8" style="color:var(--muted)">loading…</td></tr></tbody>
    </table>
</div>

<!-- ============================== SITE JOBS ============================== -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <h3>🌐 Site backups</h3>
    <button class="btn btn-sm btn-primary" onclick="openJobModal('site')">+ Add site job</button>
</div>
<div class="table-wrap" style="margin-bottom:24px">
    <table>
        <thead><tr>
            <th style="width:30px"></th>
            <th>Label</th><th>Site</th><th>Schedule</th><th>Retention</th>
            <th>Last run</th><th>On</th><th style="width:180px">Actions</th>
        </tr></thead>
        <tbody id="siteJobsBody"><tr><td colspan="8" style="color:var(--muted)">loading…</td></tr></tbody>
    </table>
</div>

<!-- ============================= SYSTEM CHECK ============================ -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <h3>🔍 Integrity check</h3>
    <span style="color:var(--muted);font-size:0.8rem" title="restic check --read-data-subset=5% — 5% of stored data is verified each week. Full dataset cycles through in ~20 weeks.">5% subset/week → ~20 weeks for full coverage</span>
</div>
<div class="table-wrap" style="margin-bottom:24px">
    <table>
        <thead><tr>
            <th style="width:30px"></th>
            <th>Label</th><th>Schedule</th><th>Last run</th><th>On</th>
            <th style="width:120px">Actions</th>
        </tr></thead>
        <tbody id="sysJobsBody"><tr><td colspan="6" style="color:var(--muted)">loading…</td></tr></tbody>
    </table>
</div>

<!-- ============================= LEGACY POOL ============================= -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <h3>🗄️ Legacy snapshots <small style="color:var(--muted);font-weight:normal">(not attached to any job)</small></h3>
</div>
<div class="table-wrap" style="margin-bottom:24px">
    <table>
        <thead><tr>
            <th>Time</th><th>Kind</th><th>Snapshot</th><th>Paths</th>
            <th style="width:200px">Actions</th>
        </tr></thead>
        <tbody id="legacyBody"><tr><td colspan="5" style="color:var(--muted)">loading…</td></tr></tbody>
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
      <small id="tablesHint" style="color:var(--muted)">Hold Ctrl/Cmd to multi-select.</small>
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
      <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;color:var(--text);font-size:0.95rem;margin-bottom:0">
        <input id="jobEnabled" type="checkbox" checked style="width:auto;margin:0"> Enabled
      </label>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end">
      <button class="btn" onclick="closeJobModal()" style="background:var(--border);color:var(--text)">Cancel</button>
      <button class="btn btn-primary" onclick="saveJob()">Save</button>
    </div>
  </div>
</div>

<script>
const CACHE_KEY = 'cid-backup-cache-v1';
const CACHE_TTL_MS = 10 * 60 * 1000;

let _jobs = [];
let _state = { runs: {} };
let _snaps = [];
let _editingId = null;
let _targets = { databases: [], sites: [] };
let _expanded = new Set(); // job ids whose expansion panel is open

function api(action, payload={}) {
  return apiCall('backup.php', Object.assign({action}, payload));
}
function fmtTime(s) {
  if (!s) return '–';
  const d = new Date(s);
  return d.toLocaleString('en-GB', {hour12:false}).replace(',', '');
}
function fmtSize(n) {
  if (n == null) return '–';
  if (n < 1024) return n + ' B';
  if (n < 1024*1024) return (n/1024).toFixed(1) + ' KB';
  if (n < 1024*1024*1024) return (n/1024/1024).toFixed(1) + ' MB';
  return (n/1024/1024/1024).toFixed(2) + ' GB';
}
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function setCacheLabel(msg) {
  const el = document.getElementById('cacheState');
  if (el) el.textContent = msg;
}

// ----- cache ------------------------------------------------------------
function loadCache() {
  try {
    const raw = localStorage.getItem(CACHE_KEY);
    if (!raw) return null;
    const obj = JSON.parse(raw);
    if (!obj || typeof obj.ts !== 'number') return null;
    if (Date.now() - obj.ts > CACHE_TTL_MS) return null;
    return obj;
  } catch (e) { return null; }
}
function saveCache(jobs, state, snaps) {
  try {
    localStorage.setItem(CACHE_KEY, JSON.stringify({
      ts: Date.now(),
      jobs, state, snaps,
    }));
  } catch (e) {}
}

function applyData(jobs, state, snaps) {
  _jobs = jobs ?? [];
  _state = { runs: (state && state.runs) || {} };
  _snaps = snaps ?? [];
  render();
}

async function refreshAll(force=false) {
  if (!force) {
    const c = loadCache();
    if (c) {
      applyData(c.jobs, c.state, c.snaps);
      const ageMin = Math.floor((Date.now() - c.ts) / 60000);
      setCacheLabel(`cached (${ageMin}m ago)`);
      return;
    }
  }
  setCacheLabel('fetching…');
  try {
    const [jr, rr, sr] = await Promise.all([
      api('jobs-get'), api('runs'), api('snapshots'),
    ]);
    const jobs = jr?.jobs ?? [];
    const state = { runs: rr?.runs ?? {} };
    const snaps = sr?.snapshots ?? [];
    applyData(jobs, state.runs ? state : {runs:{}}, snaps);
    saveCache(jobs, state, snaps);
    setCacheLabel('just now');
  } catch (e) {
    setCacheLabel('fetch failed');
    showToast('refresh failed: ' + (e?.message || e), 'error');
  }
}

// ----- rendering --------------------------------------------------------
function lastRunOf(jobId) {
  return _state.runs?.[jobId]?.history?.slice(-1)[0] ?? null;
}
function isMissed(job) {
  const last = lastRunOf(job.id);
  if (!last || !job.enabled || !job.schedule) return false;
  const parts = job.schedule.split(' ');
  const kind = parts[0];
  const intervalH = {daily:24, weekly:24*7, monthly:24*30}[kind] || 24;
  // Very rough — server computes the authoritative version; UI just hints.
  const lastTs = new Date(last.started_at).getTime();
  return Date.now() - lastTs > intervalH * 3600 * 1000 * 1.5;
}
function lastRunBadge(jobId) {
  const last = lastRunOf(jobId);
  if (!last) return '<span style="color:var(--muted)">never</span>';
  return last.status === 'ok'
    ? `<span class="badge badge-ok" title="${esc(last.started_at)}">${esc(fmtTime(last.ended_at))}</span>`
    : `<span class="badge badge-err" title="${esc(last.stderr_tail||'')}">✗ ${esc(fmtTime(last.ended_at))}</span>`;
}

function jobRowHTML(j, kind) {
  // kind: 'db' | 'site' | 'system_check' — controls column shape.
  const expanded = _expanded.has(j.id);
  const expander = `<span style="cursor:pointer;font-size:0.9rem" onclick="toggleExpand('${esc(j.id)}')" title="show snapshots + run history">${expanded ? '▼' : '▶'}</span>`;
  const last = lastRunBadge(j.id);
  const missed = isMissed(j) ? ' <span class="badge badge-warn" title="last run is older than 1.5× the schedule interval">missed</span>' : '';
  const onBadge = j.enabled
    ? '<span class="badge badge-ok">on</span>'
    : '<span class="badge badge-warn">off</span>';

  let actions = `<button class="btn btn-sm btn-primary" onclick="runJob('${esc(j.id)}')">Run</button>`;
  if (kind !== 'system_check') {
    actions += ` <button class="btn btn-sm" style="background:var(--border);color:var(--text)" onclick="editJob('${esc(j.id)}')">Edit</button>`;
    actions += ` <button class="btn btn-sm btn-danger" onclick="deleteJob('${esc(j.id)}')" title="Delete job">×</button>`;
  }

  let mainRow;
  if (kind === 'system_check') {
    mainRow = `<tr>
      <td>${expander}</td>
      <td><strong>${esc(j.label)}</strong></td>
      <td><code>${esc(j.schedule)}</code></td>
      <td>${last}${missed}</td>
      <td>${onBadge}</td>
      <td>${actions}</td>
    </tr>`;
  } else {
    const target = kind === 'db' ? esc(j.database) : esc(j.site);
    mainRow = `<tr>
      <td>${expander}</td>
      <td><strong>${esc(j.label)}</strong></td>
      <td>${target}${kind === 'db' && j.tables?.length ? `<br><small style="color:var(--muted)">${j.tables.length} table${j.tables.length===1?'':'s'} only</small>` : ''}</td>
      <td><code>${esc(j.schedule)}</code></td>
      <td>${esc(j.retention_days)} d</td>
      <td>${last}${missed}</td>
      <td>${onBadge}</td>
      <td>${actions}</td>
    </tr>`;
  }

  if (!expanded) return mainRow;
  return mainRow + expandedRowHTML(j, kind);
}

function expandedRowHTML(j, kind) {
  const colspan = kind === 'system_check' ? 6 : 8;
  // Snapshots belonging to this job, newest first
  let snapsHTML = '';
  if (kind !== 'system_check') {
    const mine = _snaps
      .filter(s => s.job_id === j.id)
      .sort((a,b) => (b.time||'').localeCompare(a.time||''));
    if (!mine.length) {
      snapsHTML = '<p style="color:var(--muted);margin:0">No snapshots yet for this job.</p>';
    } else {
      snapsHTML = `<table style="width:100%">
        <thead><tr><th>Time</th><th>Snapshot</th><th>Size</th><th style="width:240px">Actions</th></tr></thead>
        <tbody>` + mine.map(s => {
          // Try to find the size for this snapshot from history.
          const hist = _state.runs?.[j.id]?.history || [];
          const match = hist.find(h => h.snapshot_id === s.id);
          const size = match?.size_bytes;
          const isDb = kind === 'db';
          return `<tr>
            <td>${esc(fmtTime(s.time))}</td>
            <td><code>${esc(s.id)}</code></td>
            <td>${size != null ? esc(fmtSize(size)) : '<span style="color:var(--muted)">?</span>'}</td>
            <td>
              <button class="btn btn-sm" style="background:var(--border);color:var(--text)" onclick="restoreSnap('${esc(s.id)}')">Restore</button>
              ${isDb ? `<a class="btn btn-sm btn-primary" href="/admin/api/backup.php?action=download&snapshot_id=${esc(s.id)}" download>Download</a>` : ''}
              <button class="btn btn-sm btn-danger" onclick="forgetSnap('${esc(s.id)}')">×</button>
            </td>
          </tr>`;
        }).join('') + '</tbody></table>';
    }
  }

  // Run history
  const hist = (_state.runs?.[j.id]?.history || []).slice().reverse();
  let histHTML;
  if (!hist.length) {
    histHTML = '<p style="color:var(--muted);margin:0">No runs yet.</p>';
  } else {
    histHTML = hist.map(r => `
      <details style="border:1px solid var(--border);border-radius:8px;padding:8px 12px;margin-bottom:6px">
        <summary style="cursor:pointer">
          ${r.status === 'ok' ? '<span class="badge badge-ok">ok</span>' : '<span class="badge badge-err">error</span>'}
          ${esc(fmtTime(r.started_at))} → ${esc(fmtTime(r.ended_at))}
          ${r.size_bytes != null ? ' · ' + esc(fmtSize(r.size_bytes)) : ''}
          ${r.snapshot_id ? ' · <code>' + esc(r.snapshot_id) + '</code>' : ''}
        </summary>
        ${r.stdout_tail ? '<details style="margin-top:6px"><summary>stdout</summary><pre class="log-output">' + esc(r.stdout_tail) + '</pre></details>' : ''}
        ${r.stderr_tail ? '<details style="margin-top:4px"><summary>stderr</summary><pre class="log-output">' + esc(r.stderr_tail) + '</pre></details>' : ''}
      </details>`).join('');
  }

  const incrementalNote = kind === 'db'
    ? '<small style="color:var(--muted);display:block;margin-top:6px">DB dumps are full each run, but restic\'s content-defined chunking deduplicates unchanged data — so on B2 storage the effect is incremental.</small>'
    : '';

  return `<tr><td colspan="${colspan}" style="background:var(--bg);padding:14px 20px 18px">
    ${snapsHTML ? `<h4 style="margin:0 0 8px">Snapshots (newest first)</h4>${snapsHTML}${incrementalNote}` : ''}
    <h4 style="margin:${snapsHTML ? '16px' : '0'} 0 8px">Run history (last 20)</h4>
    ${histHTML}
  </td></tr>`;
}

function toggleExpand(jobId) {
  if (_expanded.has(jobId)) _expanded.delete(jobId);
  else _expanded.add(jobId);
  render();
}

function render() {
  renderCheckStatus();
  renderSection('dbJobsBody',   _jobs.filter(j => j.kind === 'db'),           'db',           8, 'No DB jobs yet. Add one with the button above.');
  renderSection('siteJobsBody', _jobs.filter(j => j.kind === 'site'),         'site',         8, 'No site jobs yet. Site backups are opt-in.');
  renderSection('sysJobsBody',  _jobs.filter(j => j.kind === 'system_check'), 'system_check', 6, '');
  renderLegacy();
}

function renderSection(tbodyId, jobs, kind, colspan, emptyMsg) {
  const tbody = document.getElementById(tbodyId);
  if (!tbody) return;
  if (!jobs.length) {
    tbody.innerHTML = `<tr><td colspan="${colspan}" style="color:var(--muted)">${esc(emptyMsg)}</td></tr>`;
    return;
  }
  tbody.innerHTML = jobs.map(j => jobRowHTML(j, kind)).join('');
}

function renderLegacy() {
  const tbody = document.getElementById('legacyBody');
  const orphans = _snaps
    .filter(s => !s.job_id)
    .sort((a,b) => (b.time||'').localeCompare(a.time||''));
  if (!orphans.length) {
    tbody.innerHTML = `<tr><td colspan="5" style="color:var(--muted)">No legacy snapshots.</td></tr>`;
    return;
  }
  tbody.innerHTML = orphans.map(s => `
    <tr>
      <td>${esc(fmtTime(s.time))}</td>
      <td>${esc((s.tags && s.tags[0]) || '–')}</td>
      <td><code>${esc(s.id)}</code></td>
      <td><small style="color:var(--muted)">${esc((s.paths || []).join(', '))}</small></td>
      <td>
        <button class="btn btn-sm" style="background:var(--border);color:var(--text)" onclick="restoreSnap('${esc(s.id)}')">Restore</button>
        <button class="btn btn-sm btn-danger" onclick="forgetSnap('${esc(s.id)}')">×</button>
      </td>
    </tr>`).join('');
}

function renderCheckStatus() {
  const r = _state.runs?.__system_check__?.history?.slice(-1)[0];
  const el = document.getElementById('checkStatus');
  if (!el) return;
  if (!r) { el.textContent = 'never run yet'; el.style.color='var(--muted)'; return; }
  if (r.status === 'ok') {
    el.innerHTML = `<span class="badge badge-ok">✓ ${esc(fmtTime(r.ended_at))}</span>`;
  } else {
    el.innerHTML = `<span class="badge badge-err">✗ FAILED at ${esc(fmtTime(r.ended_at))}</span>`;
  }
}

// ----- actions ----------------------------------------------------------
async function runJob(id) {
  showToast('Running ' + id + '…');
  const r = await api('run', {job_id: id});
  if (r.ok) showToast('Run completed: ' + (r.run?.status || 'ok'));
  else showToast('Run failed: ' + (r.error || 'unknown'), 'error');
  await refreshAll(true);
}

async function deleteJob(id) {
  if (!confirm('Delete this job?\nPast snapshots remain in B2.')) return;
  const newJobs = _jobs.filter(j => j.id !== id);
  const r = await api('jobs-put', {jobs: newJobs});
  if (r.ok) { showToast('Deleted'); await refreshAll(true); }
  else showToast('Delete failed: ' + (r.error || ''), 'error');
}

async function restoreSnap(id) {
  if (!confirm('Restore snapshot ' + id + ' into /var/cid-restores/' + id + '/?\n\nNothing in /var/www/sites or the database is overwritten — you copy from the staging dir manually.')) return;
  showToast('Restoring…');
  const r = await api('restore', {snapshot_id: id});
  if (r.ok) alert('Restored to: ' + r.path + '\n\nCopy from there manually.');
  else showToast('Restore failed: ' + (r.error || ''), 'error');
}

async function forgetSnap(id) {
  const ans = prompt('Permanently delete snapshot from B2.\nType the snapshot id "' + id + '" to confirm:');
  if (ans !== id) return;
  const r = await api('forget', {snapshot_id: id});
  if (r.ok) { showToast('Forgotten'); await refreshAll(true); }
  else showToast('Forget failed: ' + (r.error || ''), 'error');
}

// ----- modal ------------------------------------------------------------
async function openJobModal(kind='db') {
  _editingId = null;
  document.getElementById('jobModalTitle').textContent = 'Add Job';
  document.getElementById('jobLabel').value = '';
  document.getElementById('jobKind').value = kind;
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
  document.getElementById('jobSite').innerHTML     = _targets.sites.map(s => `<option value="${esc(s)}">${esc(s)}</option>`).join('');
  if (kind === 'db') loadTables();
  document.getElementById('jobModal').classList.add('show');
}
function editJob(id) {
  const j = _jobs.find(x => x.id === id);
  if (!j) return;
  openJobModal(j.kind).then(() => {
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
    const parts = (j.schedule || '').split(' ');
    document.getElementById('jobSchedKind').value = parts[0] || 'daily';
    renderScheduleInputs();
    if (parts[0] === 'daily') document.getElementById('jobSchedTime').value = parts[1] || '01:00';
    else if (parts[0] === 'weekly') {
      document.getElementById('jobSchedDay').value  = parts[1] || 'Mon';
      document.getElementById('jobSchedTime').value = parts[2] || '01:00';
    } else if (parts[0] === 'monthly') {
      document.getElementById('jobSchedDom').value  = parts[1] || '1';
      document.getElementById('jobSchedTime').value = parts[2] || '01:00';
    }
  });
}
function closeJobModal() { document.getElementById('jobModal').classList.remove('show'); }
function onKindChange() {
  const kind = document.getElementById('jobKind').value;
  document.getElementById('dbTargetGroup').style.display     = kind === 'db'   ? '' : 'none';
  document.getElementById('dbTablesGroup').style.display     = kind === 'db'   ? '' : 'none';
  document.getElementById('siteTargetGroup').style.display   = kind === 'site' ? '' : 'none';
  document.getElementById('siteExcludesGroup').style.display = kind === 'site' ? '' : 'none';
}
async function loadTables() {
  const db = document.getElementById('jobDatabase').value;
  const sel = document.getElementById('jobTables');
  const hint = document.getElementById('tablesHint');
  sel.innerHTML = '<option disabled>loading…</option>';
  if (!db) { sel.innerHTML = ''; return; }
  const r = await api('list-tables', {database: db});
  const tables = r?.tables || [];
  if (!tables.length) {
    sel.innerHTML = '';
    sel.disabled = true;
    hint.style.color = 'var(--accent)';
    hint.textContent = `(no tables in ${db} yet — whole DB will be dumped when tables appear)`;
  } else {
    sel.disabled = false;
    sel.innerHTML = tables.map(t => `<option value="${esc(t)}">${esc(t)}</option>`).join('');
    hint.style.color = 'var(--muted)';
    hint.textContent = 'Empty selection = all tables. Ctrl/Cmd-click to multi-select.';
  }
}
function renderScheduleInputs() {
  const kind = document.getElementById('jobSchedKind').value;
  document.getElementById('jobSchedDay').style.display = (kind === 'weekly')  ? '' : 'none';
  document.getElementById('jobSchedDom').style.display = (kind === 'monthly') ? '' : 'none';
}
function buildSchedule() {
  const kind = document.getElementById('jobSchedKind').value;
  const time = document.getElementById('jobSchedTime').value || '01:00';
  if (kind === 'daily')   return 'daily ' + time;
  if (kind === 'weekly')  return 'weekly ' + document.getElementById('jobSchedDay').value + ' ' + time;
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
    if (_jobs.some(x => x.id === j.id)) {
      showToast('A job with this target already exists. Edit it instead.', 'error');
      return;
    }
    newJobs = [..._jobs, j];
  }
  const r = await api('jobs-put', {jobs: newJobs});
  if (r.ok) { showToast('Saved'); closeJobModal(); await refreshAll(true); }
  else showToast('Save failed: ' + (r.error || ''), 'error');
}

refreshAll(false);
</script>
