# Backup v2 — UI + Job Engine

Three review passes. This is the final design — implementation starts after sign-off via ExitPlanMode.

After ExitPlanMode my first action is to mirror this content into `/home/bitcodata/phpserver/backup-v2-plan.md` so the repo has the revised version where you were reading.

## Context

We have a working monolithic restic backup (`cid-backup.sh`) that pushed two snapshots to Backblaze B2 today. The goal is to evolve to:

- A UI on `/admin` (list / restore / delete / download by date).
- Per-DB jobs with independent schedule, retention, and table allowlist.
- Default daily 01:00 Asia/Bangkok, 30-day retention, all tables, all DBs.
- Site backups opt-in only, with labels.
- Configurable later via the UI.

## Decisions locked from review

1. **Timezone:** `Asia/Bangkok` for every schedule. No per-job tz field (YAGNI).
2. **Restore target:** `/var/cid-restores/<short-snapshot-id>/`, persisted on disk, with the cross-container permission contract spelled out below (see Fix 3). 7-day TTL for explicit restores, 1-hour TTL for download staging.
3. **Download streaming:** warn at >100 MB, hard cap at 500 MB (above that, instruct user to `scp` from `/var/cid-restores/`). Current biggest DB (`opc_db`) is empty — caps are very safe.
4. **Legacy `cid-backup.sh`:** keep one release with a `[DEPRECATED] use cid-backup-tick` banner; delete in the next release.
5. **Failure notify:** single sysadmin-set `BACKUP_NOTIFY_URL` env var, NOT per-job (SSRF-safe).
6. **www-data GID** read from env var `PHP_GID` (default `33`) so a future image migration with a different GID doesn't silently break the permission contract.

## The fixes (numbered, linear)

### Fix 1 — Drop gzip, let restic compress

Restic ≥0.14 has zstd content-defined chunking dedup built in. Pre-gzipping defeats the dedup because tiny SQL changes produce wildly different gzip output. New flow:

```python
proc = subprocess.Popen(
    ["docker","exec","-e",f"MYSQL_PWD={pw}","cid-mariadb",
     "mariadb-dump","-uroot","--single-transaction","--quick",
     "--routines","--triggers","--events",
     database, *tables],
    stdout=subprocess.PIPE, stderr=subprocess.PIPE)

subprocess.run(
    ["restic","backup","--stdin",
     "--stdin-filename", f"{database}.sql",
     "--compression","auto",
     "--tag", f"job:{job_id}", "--tag", "kind:db",
     "--tag", f"db:{database}", "--host", "cid"],
    stdin=proc.stdout, check=True)
```

No `gzip` in the pipeline. The dump stays as `.sql` in restic; restic does the zstd. `mariadb-dump` is confirmed (MariaDB 10.11 ships it as the real binary; `mysqldump` is a symlink).

### Fix 2 — Input validation in the broker

All four validators run BEFORE any subprocess invocation.

```python
_DB_NAME_RE     = re.compile(r"^[a-zA-Z0-9_]{1,64}$")
_TABLE_NAME_RE  = re.compile(r"^[a-zA-Z0-9_]{1,64}$")
_SNAPSHOT_ID_RE = re.compile(r"^[a-f0-9]{8,64}$")
_SITE_NAME_RE   = re.compile(r"^[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$")

def _validate_database(name):
    if not _DB_NAME_RE.fullmatch(name): raise BadRequest("invalid database name")
    if name not in _list_databases():  raise BadRequest("database does not exist")
    return name

def _validate_tables(db, tables):
    if not tables: return []
    existing = set(_list_tables(db))
    for t in tables:
        if not _TABLE_NAME_RE.fullmatch(t): raise BadRequest(f"invalid table: {t}")
        if t not in existing: raise BadRequest(f"table not in {db}: {t}")
    return tables

def _validate_snapshot(sid):
    if not _SNAPSHOT_ID_RE.fullmatch(sid): raise BadRequest("invalid snapshot id")
    return sid

def _validate_site(name):
    if not _SITE_NAME_RE.fullmatch(name): raise BadRequest("invalid site name")
    if name not in _list_sites():        raise BadRequest("site does not exist")
    return name

def _list_sites():
    """Recomputed on every call from /var/www/sites/."""
    base = "/var/www/sites"
    return sorted(
        name for name in os.listdir(base)
        if not name.startswith(".") and name != "_config"
        and os.path.isdir(f"{base}/{name}/public")
    )
```

`_list_databases()` / `_list_tables()` reuse the existing `/mysql-query` plumbing.

### Fix 3 — `/var/cid-restores/` with cross-container-readable perms

Docker doesn't translate UIDs — every container sees host UIDs/GIDs directly. The broker runs as root (uid 0) while `cid-php74` PHP-FPM runs as `www-data` (uid/gid 33). Verified empirically; also driven by `PHP_GID` env var so a future image change with a different GID is a one-line config update.

**Host setup (one-time):**

```bash
# Use numeric GID so this doesn't silently fail on a host without www-data group.
sudo install -d -m 0750 -o 0 -g 33 /var/cid-restores
```

**Broker contract after every restore:**

```python
PHP_GID = int(os.getenv("PHP_GID", "33"))

def _set_restore_perms(root_dir):
    """Make restored files readable by cid-php74's www-data."""
    os.chown(root_dir, 0, PHP_GID)
    os.chmod(root_dir, 0o750)
    for dirpath, dirnames, filenames in os.walk(root_dir):
        for d in dirnames:
            p = os.path.join(dirpath, d)
            os.chown(p, 0, PHP_GID); os.chmod(p, 0o750)
        for f in filenames:
            p = os.path.join(dirpath, f)
            os.chown(p, 0, PHP_GID); os.chmod(p, 0o640)
```

Directory layout:

```
/var/cid-restores/                          # 0750 root:www-data
├── <snapshot-id>/                          # 7-day TTL
│   └── …                                   # files 0640 root:www-data
└── .dl-<snapshot-id>-<random>/             # 1-hour TTL
    └── opc_db.sql                          # 0640 root:www-data
```

`/var/cid-restores/` is mounted **rw into both** `cid-broker` AND `cid-php74` (same host dir, different mount specs in compose). Tick janitor sweeps `.dl-*` after 1h, plain `<id>` after 7d.

### Fix 4 — Concurrency lock

```python
import fcntl
class BackupLock:
    PATH = "/run/cid-backup.lock"     # tmpfs inside the broker container
    def __init__(self, blocking):
        self.fh = open(self.PATH, "w")
        flags = fcntl.LOCK_EX | (0 if blocking else fcntl.LOCK_NB)
        try: fcntl.flock(self.fh, flags)
        except BlockingIOError:
            self.fh.close(); self.fh = None
            raise

    def release(self):
        if self.fh:
            fcntl.flock(self.fh, fcntl.LOCK_UN); self.fh.close()
```

- `/backup/tick` → `BackupLock(blocking=True)` — tick can wait.
- `/backup/run` (Run Now) → `BackupLock(blocking=False)` → 409 on conflict.

Lock scope is intentionally the broker tmpfs only. Legacy `cid-backup.sh` on the host does NOT see this lock; the deprecation banner says so loudly:

> ⚠️  DEPRECATED — use cid-backup-tick (broker-based job engine).
> This script does NOT coordinate with the broker. Do NOT run it while
> v2 is active or you may end up with concurrent mysqldumps.

### Fix 5 — Single source of truth for secrets

`/etc/cid-backup.env` (0600 root) is the only file with restic + B2 creds + optional `BACKUP_NOTIFY_URL` + `PHP_GID`.

```yaml
# docker-compose.yml — broker
broker:
  …
  env_file:
    - /etc/cid-backup.env
  environment:
    MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
    CADDY_ADMIN_URL: ${CADDY_ADMIN_URL:-…}
```

Tick script sources the same file. `docker/.env` no longer holds RESTIC_* / B2_*.

### Fix 6 — Built-in weekly integrity check

Reserved job id `__system_check__`, schedule `weekly Sun 04:00`, not editable from UI (greyed out). Idempotent seed at broker boot — see Fix 11 for the bootstrap pipeline.

```python
def run_system_check():
    subprocess.run(["restic","check","--read-data-subset=5%"], check=True)
```

State stored under `runs.__system_check__`. On failure, broker POSTs to `BACKUP_NOTIFY_URL` if set.

UI shows in page header: "Last integrity check: 2026-05-17 04:01 ✓" with tooltip:
> "5% of stored data is verified each week. The full dataset cycles through in about 20 weeks (~5 months) of consecutive checks."

### Fix 7 — Catch-up logic

`schedule_is_due(job, now, last_run_at)` returns True when:

1. The job has an `ideal_run_at` for the current period.
2. `now >= ideal_run_at`.
3. `last_run_at < ideal_run_at` (the period hasn't been satisfied).
4. `now <= ideal_run_at + 1.5 × interval`.

Daily missed at 01:00 → still runs anytime within 36h. UI shows a red "missed" pill if `now > ideal_run_at + 1.5 × interval AND last_run_at < ideal_run_at`.

### Fix 8 — Robust download cleanup (file + dir)

```php
// Broker returns {file: "/var/cid-restores/.dl-xyz/opc_db.sql",
//                 dir:  "/var/cid-restores/.dl-xyz"}
ignore_user_abort(true);
register_shutdown_function(function() use ($stage) {
    @unlink($stage["file"]);
    @rmdir($stage["dir"]);          // dir is empty after unlink
});
readfile($stage["file"]);
```

Plus broker tick janitor sweeps `.dl-*` >1h and `<id>` >7d (recursive). Belt + suspenders.

### Fix 9 — Expanded + configurable site excludes

Default excludes for site jobs:
```
*/logs   */.git   */node_modules   */cache   */tmp   *.log   */vendor
```

`excludes: []` in the job → empty means defaults only; non-empty means defaults + job's additions (append, never replace).

### Fix 10 — Observability: per-job history + sysadmin notify

`backup-state.json`:
```json
{
  "runs": {
    "db-opc_db": {
      "history": [
        {
          "started_at":  "2026-05-15T01:00:42+07:00",
          "ended_at":    "2026-05-15T01:00:47+07:00",
          "status":      "ok",
          "snapshot_id": "f095b929",
          "size_bytes":  1118692,
          "stdout_tail": "…",
          "stderr_tail": ""
        }
        // up to 20 entries, FIFO eviction
      ]
    }
  },
  "seeded_dbs": ["opc_db"]
}
```

Atomic writes (tmp + rename) for both `backup-jobs.json` AND `backup-state.json` since state is mutated every run.

UI per-job "View runs" → table of last 20 with collapsible stdout/stderr.

**Notify on error:** single env-var `BACKUP_NOTIFY_URL`. Broker POSTs JSON on any job failure with `timeout=5s`, `allow_redirects=False`. URL is sysadmin-controlled, NEVER user-supplied → no SSRF surface.

### Fix 11 — `jobs.json` / `state.json` as runtime state + idempotent bootstrap

Both files live under `sites/opc.bitco.link/public/admin/data/` but they're runtime state and should NOT be committed:

- `backup-state.json` changes every run.
- `backup-jobs.json` is user-edited via the UI.

`.gitignore` adds:
```
sites/opc.bitco.link/public/admin/data/backup-jobs.json
sites/opc.bitco.link/public/admin/data/backup-state.json
```

Committed instead: `backup-jobs.json.example` and `backup-state.json.example`. Broker bootstrap on every start:

```python
def _ensure_data_files():
    for name in ("backup-jobs.json", "backup-state.json"):
        real    = f"/admin-data/{name}"
        example = f"{real}.example"
        if os.path.exists(real):
            continue
        if os.path.exists(example):
            shutil.copy(example, real)
        elif name == "backup-jobs.json":
            _atomic_write(real, {"version": 2, "jobs": []})
        elif name == "backup-state.json":
            _atomic_write(real, {"runs": {}, "seeded_dbs": []})
    _ensure_system_check_job()
    _ensure_seeded_db_jobs()
```

### Fix 12 — Download size gate (hybrid)

Pre-restore (optimistic) + post-restore (hard) gates:

1. **Pre-restore:** read `last_size_bytes` from `backup-state.json` for the job that owns this snapshot.
   - Unknown size (legacy snapshot, or job has never succeeded) → allow; UI flags "size unknown".
   - When `last_size_bytes > 100 MB` → UI shows confirmation modal before proceeding.
2. **Hard gate (post-restore):** `os.stat` the restored `.sql` file.
   - When `size > 500 MB` → broker deletes staging dir, returns HTTP 413 "Payload too large — fetch via scp from the host".
3. **UI warning:** if `last_size_bytes > 100 MB`, modal warns the user before "Download" click.

`restic stats` was considered but rejected: extra B2 round-trip per click. Optimistic + hard gate covers both error directions.

### Fix 13 — DB job seeding policy: seed once, then leave alone

`_ensure_seeded_db_jobs()` is idempotent across restarts AND respects user intent to delete a job. It also handles the bootstrap race where MariaDB may not be ready yet when broker boots — `_list_databases()` will fail, we log a warning and defer. The tick endpoint also calls this function so deferred seeding catches up within 5 minutes.

```python
SYSTEM_DBS = {"information_schema", "performance_schema", "mysql", "sys"}

def _ensure_seeded_db_jobs():
    try:
        databases = _list_databases()
    except Exception as e:
        logger.warning(f"DB seeding deferred: mariadb not ready ({e})")
        return

    state  = _load_state()
    seeded = set(state.get("seeded_dbs", []))
    jobs   = _load_jobs()
    have_jobs = {j["id"] for j in jobs["jobs"]}

    for db in databases:
        if db in SYSTEM_DBS: continue
        if db in seeded:     continue           # user may have deleted it; respect that
        job_id = f"db-{db}"
        if job_id in have_jobs: continue
        jobs["jobs"].append({
            "id":             job_id,
            "label":          f"{db} daily 01:00",
            "kind":           "db",
            "database":       db,
            "tables":         [],
            "schedule":       "daily 01:00",
            "retention_days": 30,
            "enabled":        True,
            "created_at":     _now_iso(),
        })
        seeded.add(db)

    state["seeded_dbs"] = sorted(seeded)
    _save_state_atomic(state)
    _save_jobs_atomic(jobs)
```

**Call sites:**
- Broker boot (`_ensure_data_files()` → `_ensure_seeded_db_jobs()`).
- Tick endpoint, at the top, before scheduling decisions.

Both call sites are idempotent because `seeded_dbs` tracks what we've already done.

Behaviours:
- **First boot, fresh DB, mariadb ready:** seeded; job appears in UI.
- **First boot, mariadb still starting:** boot continues without seeding; first tick (within 5 min) catches up.
- **User deletes the job:** id stays in `seeded_dbs`, so neither boot nor tick re-creates it.
- **New DB appears later (CREATE DATABASE foo):** not in `seeded_dbs`, gets a default job at next tick. User can immediately disable/edit.
- **System DBs:** never seeded.

---

## Architecture

```
                                    ┌─────────────────────────────────┐
   Admin browser ─────► nginx ──►  PHP (cid-php74)
                                   │  /admin/?page=backup (UI)
                                   │  /admin/api/backup.php (dispatch)
                                   └──────────┬──────────────────────┘
                                              │ brokerCall('/backup/…')
                                              ▼
   ┌─────────────────────────────────────────────────────────────────┐
   │  cid-broker  (Python+Flask, +restic)                            │
   │                                                                 │
   │    /backup/list-targets   /backup/jobs (GET/PUT)                │
   │    /backup/list-tables    /backup/run     (LOCK_NB)             │
   │    /backup/snapshots      /backup/restore                       │
   │    /backup/forget         /backup/download-db                   │
   │    /backup/tick           (LOCK_EX, blocking)                   │
   │    /backup/runs           ← per-job history for UI              │
   │                                                                 │
   │  Reads/writes (mounted rw):                                     │
   │    /admin-data/backup-jobs.json    ← runtime config             │
   │    /admin-data/backup-state.json   ← per-job history + seeded   │
   │    /var/cid-restores/              ← restore + download staging │
   │                                                                 │
   │  Reads (mounted ro):                                            │
   │    /var/www/sites/<domain>/        ← snapshotted by site jobs   │
   │                                                                 │
   │  Writes:                                                        │
   │    /run/cid-backup.lock            ← concurrency guard          │
   │                                                                 │
   │  Subprocesses:                                                  │
   │    docker exec cid-mariadb mariadb-dump …                       │
   │    restic backup --stdin --compression auto                     │
   │    restic forget --tag job:<id> --keep-within Nd --prune        │
   │    restic check --read-data-subset=5%   (weekly system job)     │
   │    restic restore <id> --target /var/cid-restores/<id>/         │
   │                                                                 │
   │  Env (from /etc/cid-backup.env via env_file:):                  │
   │    RESTIC_REPOSITORY, RESTIC_PASSWORD,                          │
   │    B2_ACCOUNT_ID, B2_ACCOUNT_KEY,                               │
   │    BACKUP_NOTIFY_URL (optional, sysadmin-set),                  │
   │    PHP_GID (default "33")                                       │
   └─────────────────────────────────────────────────────────────────┘

                            ▲
                            │  PHP also mounts /var/cid-restores/ rw so the
                            │  download endpoint can readfile() and unlink()
                            │  via shutdown handler.
   ┌────────────────────────┴────────────────────────────────────────┐
   │  cid-php74                                                      │
   │  - admin/api/backup.php dispatches to broker; download streams  │
   │    files out of the rw /var/cid-restores/ mount.                │
   └─────────────────────────────────────────────────────────────────┘

   Host cron (every 5 min)  ─►  /usr/local/bin/cid-backup-tick
       └─ curl --unix-socket /run/broker/broker.sock POST /backup/tick
```

## Data model

### `admin/data/backup-jobs.json`

```json
{
  "version": 2,
  "jobs": [
    {
      "id":             "db-opc_db",
      "label":          "opc_db daily 01:00",
      "kind":           "db",
      "database":       "opc_db",
      "tables":         [],
      "schedule":       "daily 01:00",
      "retention_days": 30,
      "enabled":        true,
      "created_at":     "2026-05-15T12:34:56+07:00"
    },
    {
      "id":             "site-opc",
      "label":          "opc.bitco.link weekly",
      "kind":           "site",
      "site":           "opc.bitco.link",
      "excludes":       [],
      "schedule":       "weekly Sun 03:00",
      "retention_days": 60,
      "enabled":        false,
      "created_at":     "2026-05-15T12:35:00+07:00"
    },
    {
      "id":             "__system_check__",
      "label":          "Integrity check (weekly)",
      "kind":           "system_check",
      "schedule":       "weekly Sun 04:00",
      "enabled":        true,
      "created_at":     "<install-time>"
    }
  ]
}
```

### `admin/data/backup-state.json`

See Fix 10 schema. `seeded_dbs` array added per Fix 13.

### Snapshot tagging

`job:<id>`, `kind:db|site`, `db:<name>` or `site:<name>`, `--host cid`.

## Files to create / modify

| Path | New? | Change |
|---|---|---|
| `docker/broker/Dockerfile` | mod | `apk add restic` (≥0.14, alpine community) |
| `docker/broker/app.py` | mod | +9 routes; +validators; +`BackupLock`; +scheduler eval; +`_ensure_data_files / _ensure_system_check_job / _ensure_seeded_db_jobs`; +`_set_restore_perms`; +`BACKUP_NOTIFY_URL` poster; +tick janitor |
| `docker/docker-compose.yml` | mod | broker `env_file: /etc/cid-backup.env`; rw bind `admin/data` + `/var/cid-restores`; ro bind `sites/`. **Also: bind `/var/cid-restores/` rw into cid-php74.** |
| `docker/.env.example` | mod | remove RESTIC_*/B2_* / BACKUP_NOTIFY_URL / PHP_GID (all in /etc/cid-backup.env exclusively) |
| `docker/.env` (host) | mod | drop RESTIC_*/B2_* lines |
| `scripts/cid-backup-tick.sh` | NEW | thin wrapper: curl broker /backup/tick |
| `scripts/cid-backup.sh` | mod | demote to legacy with deprecation banner (see Fix 4) |
| `.gitignore` | mod | ignore `…/admin/data/backup-jobs.json` and `…/backup-state.json` |
| `sites/opc.bitco.link/public/admin/data/backup-jobs.json.example` | NEW | empty job list |
| `sites/opc.bitco.link/public/admin/data/backup-state.json.example` | NEW | `{"runs":{},"seeded_dbs":[]}` |
| `sites/opc.bitco.link/public/admin/data/backup-jobs.json` | runtime | NOT in git; bootstrapped by broker |
| `sites/opc.bitco.link/public/admin/data/backup-state.json` | runtime | NOT in git; bootstrapped by broker |
| `sites/opc.bitco.link/public/admin/api/backup.php` | NEW | dispatches to broker; download endpoint with shutdown-handler cleanup |
| `sites/opc.bitco.link/public/admin/templates/backup.php` | NEW | UI |
| `sites/opc.bitco.link/public/admin/index.php` | mod | add 🗄️ Backup nav link + page dispatch |
| `/etc/cid-backup.env` (host) | mod | add optional `BACKUP_NOTIFY_URL`, optional `PHP_GID` |
| `/etc/cron.d/cid-backup` (host) | mod | swap command to `cid-backup-tick` |
| `/usr/local/bin/cid-backup-tick` (host) | NEW | installed copy of `scripts/cid-backup-tick.sh` |
| `/var/cid-restores/` (host) | NEW | `install -d -m 0750 -o 0 -g 33 /var/cid-restores`. Mounted rw into both broker and cid-php74. |

## Verification

- `docker exec cid-broker restic version` shows ≥0.14.
- `curl --unix-socket /run/broker/broker.sock POST /backup/list-targets` returns `{databases:[…], sites:[…]}`.
- Invalid input rejected: `POST /backup/run {"job_id":"db-opc_db; rm -rf /"}` → 400.
- Snapshot id validation: `POST /backup/restore {"snapshot_id":"; cat /etc/passwd"}` → 400.
- Concurrency: open two simultaneous `/backup/run` calls — the second returns HTTP 409.
- Catch-up: stop broker for 1 hour past a scheduled run, restart, watch tick fire the missed job within 5 min.
- Permission contract: after a download click, staging file is `-rw-r----- root www-data` (mode 0640, GID 33). `docker exec cid-php74 cat <path>` succeeds; `docker exec cid-php74 sh -c "echo hi >> <path>"` fails with EACCES.
- UI: render `/admin/?page=backup`, see one DB job pre-seeded (`db-opc_db`), click Run Now, snapshot appears in B2, Restore restores into `/var/cid-restores/<id>/`, Download streams the dump back.
- Cleanup: leave a fake `/var/cid-restores/.dl-old/` with mtime 2h ago, run /backup/tick, verify it's gone.
- Integrity check runs Sunday 04:00 and its status appears in the UI header.
- Seeding idempotence: delete `db-opc_db` via the UI, restart broker, verify it does NOT come back (because `seeded_dbs: ["opc_db"]` remembers the deletion).
- `BACKUP_NOTIFY_URL` validation: not set in env → no POST attempted on failure. Set to a non-200 endpoint → broker logs the failure but doesn't crash.

## Known limitations

- **Tick endpoint runs jobs sequentially in a single HTTP request.** For the current install (1 empty DB) this is microseconds. If the install grows to ≥5 DBs AND the server is down through 01:00, the catch-up tick could run 5–30 min in one request — host cron's curl will hit its 60s default timeout and drop the connection (broker keeps working, but curl-side status reporting is confusing). Future fix when needed: split tick into `POST /backup/tick` that fires jobs in a `threading.Thread` and returns 202 immediately. Documented in `scripts/cid-backup-setup.md`.
- **Schedule edge cases** (e.g. `monthly 31` in February): silently skipped with a warning logged.
- **Per-table dumps lack `CREATE DATABASE`** — the UI's restore modal documents that restoring a per-table snapshot needs `mysql -u… dbname < dump.sql`.

## Order of implementation

1. Broker: Dockerfile + restic install + env_file + 9 routes + all validators + lock + scheduler + bootstrap funcs + perm contract + notify. (~2 h)
2. Tick script + cron change + `/var/cid-restores/` host setup. (~20 min)
3. PHP dispatch endpoint at `admin/api/backup.php`. (~30 min)
4. UI template `admin/templates/backup.php` + nav link + JS. (~1 h)
5. `.gitignore` + `.example` files. (~10 min)
6. End-to-end testing per "Verification". (~45 min)
7. Mirror this plan into `/home/bitcodata/phpserver/backup-v2-plan.md`.
8. Commit as one logical unit.

Estimated total: 4–5 hours.

## Next step

ExitPlanMode for sign-off.
