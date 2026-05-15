"""
Privileged-operations broker for the phpserver admin.

The PHP admin container (cid-php74) used to mount /var/run/docker.sock and run
`docker exec` / `docker run --network=host` directly. Any RCE in PHP meant full
Docker control. This broker takes over those operations behind a unix-socket
API that exposes only the routes the admin actually needs.

Routes:
    GET  /healthz            cheap liveness probe
    POST /mysql              run a SQL statement against cid-mariadb (root)
    POST /mysql-query        same with -se for parseable output
    POST /container          start|stop|restart on the allow-list
    POST /nginx/reload       docker exec cid-nginx nginx -s reload
    POST /caddy/route        POST a route JSON to the host's Caddy admin

Implementation notes:
- All callers reach us over /run/broker/broker.sock (mode 0660, owned by the
  group shared with cid-php74's www-data). No TCP listener exists.
- We never use shell=True. Every subprocess.run() uses an argv list with the
  caller's payload passed as a single argv element, so shell metacharacters in
  the payload cannot escape into a shell.
- SQL passed in /mysql is still SQL — callers remain responsible for
  identifier/string safety. The broker only ensures the *shell* layer is safe.
"""
import datetime
import fcntl
import json
import logging
import os
import re
import secrets
import shutil
import subprocess
import time
import urllib.error
import urllib.request
from typing import Any
from zoneinfo import ZoneInfo

from flask import Flask, jsonify, request

logger = logging.getLogger("broker")
logging.basicConfig(level=logging.INFO, format="%(asctime)s %(name)s %(levelname)s %(message)s")

app = Flask(__name__)

ALLOWED_CONTAINERS = {
    "cid-php74", "cid-nginx", "cid-mariadb", "cid-phpmyadmin", "cid-redis",
}
ALLOWED_ACTIONS = {"start", "stop", "restart"}

CADDY_ADMIN = os.getenv("CADDY_ADMIN_URL", "http://127.0.0.1:2019")


def _err(msg: str, code: int = 400):
    return jsonify({"ok": False, "error": msg}), code


def _run(argv: list[str], timeout: int = 30) -> tuple[int, str, str]:
    """Run a subprocess; return (rc, stdout, stderr). No shell."""
    try:
        p = subprocess.run(argv, capture_output=True, text=True, timeout=timeout)
        return p.returncode, p.stdout, p.stderr
    except subprocess.TimeoutExpired:
        return 124, "", f"timeout after {timeout}s"
    except FileNotFoundError as e:
        return 127, "", str(e)


@app.get("/healthz")
def healthz():
    return jsonify({"ok": True, "service": "broker", "version": 1})


def _mysql(sql: str, scriptable: bool) -> dict[str, Any]:
    pw = os.getenv("MYSQL_ROOT_PASSWORD", "")
    if not pw:
        return {"ok": False, "error": "MYSQL_ROOT_PASSWORD not set in broker env"}
    # MYSQL_PWD env keeps the password off the argv (and out of `ps`).
    argv = [
        "docker", "exec", "-e", f"MYSQL_PWD={pw}", "cid-mariadb",
        "mysql", "-uroot",
    ]
    if scriptable:
        argv.append("-se")
    else:
        argv.append("-e")
    argv.append(sql)
    rc, out, err = _run(argv, timeout=60)
    if rc != 0:
        return {"ok": False, "error": (err or out).strip(), "rc": rc}
    return {"ok": True, "output": out}


@app.post("/mysql")
def mysql_exec():
    data = request.get_json(silent=True) or {}
    sql = data.get("sql", "")
    if not isinstance(sql, str) or not sql.strip():
        return _err("sql required")
    return jsonify(_mysql(sql, scriptable=False))


@app.post("/mysql-query")
def mysql_query():
    data = request.get_json(silent=True) or {}
    sql = data.get("sql", "")
    if not isinstance(sql, str) or not sql.strip():
        return _err("sql required")
    return jsonify(_mysql(sql, scriptable=True))


@app.post("/container/status")
def container_status():
    """Read-only state lookup for the dashboard's Container Status panel.
    Takes a list of container names and returns {status, started_at} for each.
    Read-only, so we allow any cid-* name (no risk of mutation here)."""
    data = request.get_json(silent=True) or {}
    names = data.get("names", [])
    if not isinstance(names, list) or not all(isinstance(n, str) for n in names):
        return _err("names must be a list of strings")
    statuses: dict[str, Any] = {}
    for name in names:
        if not re.fullmatch(r"cid-[a-z0-9-]{1,32}", name):
            statuses[name] = {"status": "invalid-name", "started_at": None}
            continue
        rc, out, err = _run(
            ["docker", "inspect", "--format", "{{.State.Status}}|{{.State.StartedAt}}", name],
            timeout=10,
        )
        if rc != 0:
            statuses[name] = {"status": "missing", "started_at": None}
            continue
        parts = out.strip().split("|", 1)
        statuses[name] = {
            "status": parts[0] if parts else "unknown",
            "started_at": parts[1] if len(parts) > 1 else None,
        }
    return jsonify({"ok": True, "statuses": statuses})


@app.post("/container")
def container_action():
    data = request.get_json(silent=True) or {}
    name = data.get("name", "")
    action = data.get("action", "")
    if name not in ALLOWED_CONTAINERS:
        return _err("container not allowed", 403)
    if action not in ALLOWED_ACTIONS:
        return _err("action not allowed", 403)
    rc, out, err = _run(["docker", action, name])
    return jsonify({"ok": rc == 0, "output": (out or err).strip(), "rc": rc})


@app.post("/logs")
def container_logs():
    """Tail container logs. Read-only; returns stdout and stderr separately
    so the caller can pick (nginx-error wants stderr only, etc.)."""
    data = request.get_json(silent=True) or {}
    name = data.get("name", "")
    tail = int(data.get("tail", 200) or 200)
    if name not in ALLOWED_CONTAINERS:
        return _err("container not allowed", 403)
    if tail < 1 or tail > 10000:
        return _err("tail must be 1..10000")
    # docker logs writes to stdout/stderr separately — we capture both.
    rc, out, err = _run(["docker", "logs", "--tail", str(tail), name], timeout=20)
    return jsonify({"ok": rc == 0 or rc == 124, "stdout": out, "stderr": err, "rc": rc})


@app.post("/nginx/reload")
def nginx_reload():
    rc, out, err = _run(["docker", "exec", "cid-nginx", "nginx", "-s", "reload"])
    return jsonify({"ok": rc == 0, "output": (out or err).strip(), "rc": rc})


@app.post("/caddy/route")
def caddy_route():
    data = request.get_json(silent=True) or {}
    payload = data.get("payload")
    if payload is None:
        return _err("payload required")
    # Caddy admin expects a JSON route object. We post it via curl so the
    # broker doesn't need a python http client + we keep the dependency list
    # short. The body comes from a temp file to avoid argv-length issues.
    import tempfile
    with tempfile.NamedTemporaryFile("w", suffix=".json", delete=False) as f:
        json.dump(payload, f)
        body_path = f.name
    try:
        rc, out, err = _run([
            "curl", "-s", "-o", "/dev/null", "-w", "%{http_code}",
            "-X", "POST",
            f"{CADDY_ADMIN}/config/apps/http/servers/srv0/routes",
            "-H", "Content-Type: application/json",
            "--data-binary", f"@{body_path}",
        ], timeout=15)
    finally:
        try:
            os.unlink(body_path)
        except OSError:
            pass
    return jsonify({"ok": rc == 0 and out.strip() == "200", "http": out.strip(), "rc": rc})


# ===================================================================
# Backup engine (Fix 1–13 in backup-v2-plan.md).
# Adds 9 routes under /backup/* on top of the existing privileged-ops
# routes. State files live on a bind-mounted host dir at /admin-data.
# Restore staging at /var/cid-restores (mounted into cid-php74 too).
# ===================================================================

TZ_BKK = ZoneInfo("Asia/Bangkok")
PHP_GID = int(os.getenv("PHP_GID", "33"))
BACKUP_NOTIFY_URL = (os.getenv("BACKUP_NOTIFY_URL") or "").strip() or None
RESTORE_ROOT = "/var/cid-restores"
ADMIN_DATA = "/admin-data"
JOBS_FILE = f"{ADMIN_DATA}/backup-jobs.json"
STATE_FILE = f"{ADMIN_DATA}/backup-state.json"

SYSTEM_DBS = {"information_schema", "performance_schema", "mysql", "sys"}

_DB_NAME_RE     = re.compile(r"^[a-zA-Z0-9_]{1,64}$")
_TABLE_NAME_RE  = re.compile(r"^[a-zA-Z0-9_]{1,64}$")
_SNAPSHOT_ID_RE = re.compile(r"^[a-f0-9]{8,64}$")
_SITE_NAME_RE   = re.compile(r"^[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$")
_JOB_ID_RE      = re.compile(r"^[a-zA-Z0-9_-]{1,80}$")


class BadRequest(Exception):
    """Validation error → HTTP 400."""

class Conflict(Exception):
    """Lock held → HTTP 409."""


# --- Atomic file I/O (Fix 10/11) ---
def _atomic_write(path, data):
    tmp = f"{path}.tmp.{os.getpid()}"
    with open(tmp, "w") as f:
        json.dump(data, f, indent=2)
    os.rename(tmp, path)


def _load_jobs():
    with open(JOBS_FILE) as f:
        return json.load(f)


def _save_jobs_atomic(jobs):
    _atomic_write(JOBS_FILE, jobs)


def _load_state():
    with open(STATE_FILE) as f:
        return json.load(f)


def _save_state_atomic(state):
    _atomic_write(STATE_FILE, state)


def _now_iso():
    return datetime.datetime.now(TZ_BKK).isoformat(timespec="seconds")


# --- DB / site / snapshot listing + validation (Fix 2) ---
def _list_databases():
    pw = os.getenv("MYSQL_ROOT_PASSWORD", "")
    if not pw:
        raise RuntimeError("MYSQL_ROOT_PASSWORD not set")
    rc, out, err = _run(
        ["docker", "exec", "-e", f"MYSQL_PWD={pw}", "cid-mariadb",
         "mariadb", "-uroot", "-Nse", "SHOW DATABASES"],
        timeout=10,
    )
    if rc != 0:
        raise RuntimeError((err or out).strip() or "mariadb not ready")
    return [d.strip() for d in out.splitlines() if d.strip()]


def _list_tables(db):
    pw = os.getenv("MYSQL_ROOT_PASSWORD", "")
    if not pw:
        return []
    rc, out, _err = _run(
        ["docker", "exec", "-e", f"MYSQL_PWD={pw}", "cid-mariadb",
         "mariadb", "-uroot", "-Nse", f"SHOW TABLES FROM `{db}`"],
        timeout=10,
    )
    if rc != 0:
        return []
    return [t.strip() for t in out.splitlines() if t.strip()]


def _list_sites():
    """Recomputed on every call from /var/www/sites/."""
    base = "/var/www/sites"
    if not os.path.isdir(base):
        return []
    return sorted(
        name for name in os.listdir(base)
        if not name.startswith(".") and name != "_config"
        and os.path.isdir(f"{base}/{name}/public")
    )


def _validate_database(name):
    if not isinstance(name, str) or not _DB_NAME_RE.fullmatch(name):
        raise BadRequest("invalid database name")
    if name not in _list_databases():
        raise BadRequest("database does not exist")
    return name


def _validate_tables(db, tables):
    if not tables:
        return []
    if not isinstance(tables, list):
        raise BadRequest("tables must be a list")
    existing = set(_list_tables(db))
    out = []
    for t in tables:
        if not isinstance(t, str) or not _TABLE_NAME_RE.fullmatch(t):
            raise BadRequest(f"invalid table: {t!r}")
        if t not in existing:
            raise BadRequest(f"table not in {db}: {t}")
        out.append(t)
    return out


def _validate_snapshot(sid):
    if not isinstance(sid, str) or not _SNAPSHOT_ID_RE.fullmatch(sid):
        raise BadRequest("invalid snapshot id")
    return sid


def _validate_site(name):
    if not isinstance(name, str) or not _SITE_NAME_RE.fullmatch(name):
        raise BadRequest("invalid site name")
    if name not in _list_sites():
        raise BadRequest("site does not exist")
    return name


def _validate_job_id(jid):
    if not isinstance(jid, str) or not _JOB_ID_RE.fullmatch(jid):
        raise BadRequest("invalid job id")
    return jid


# --- Permission contract (Fix 3) ---
def _set_restore_perms(root_dir, *, writable_by_php=False):
    """Make restored files readable by cid-php74's www-data (PHP_GID).

    writable_by_php=True is used for download staging dirs (.dl-*) so the PHP
    shutdown handler can unlink the file + rmdir the parent.  Persistent restore
    dirs stay read-only-for-group.
    """
    dir_mode = 0o770 if writable_by_php else 0o750
    file_mode = 0o660 if writable_by_php else 0o640
    try:
        os.chown(root_dir, 0, PHP_GID); os.chmod(root_dir, dir_mode)
    except OSError as e:
        logger.warning(f"chown {root_dir}: {e}")
    for dirpath, dirnames, filenames in os.walk(root_dir):
        for d in dirnames:
            p = os.path.join(dirpath, d)
            try: os.chown(p, 0, PHP_GID); os.chmod(p, dir_mode)
            except OSError: pass
        for f in filenames:
            p = os.path.join(dirpath, f)
            try: os.chown(p, 0, PHP_GID); os.chmod(p, file_mode)
            except OSError: pass


# --- Concurrency lock (Fix 4) ---
class BackupLock:
    PATH = "/run/cid-backup.lock"
    def __init__(self, blocking):
        self.fh = open(self.PATH, "w")
        flags = fcntl.LOCK_EX | (0 if blocking else fcntl.LOCK_NB)
        try:
            fcntl.flock(self.fh, flags)
        except BlockingIOError:
            self.fh.close(); self.fh = None
            raise Conflict("another backup is in progress")
    def __enter__(self): return self
    def __exit__(self, *a):
        if self.fh:
            fcntl.flock(self.fh, fcntl.LOCK_UN); self.fh.close()


# --- Schedule parser (Fix 7) ---
_DAYS = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"]
_INTERVAL_HOURS = {"daily": 24, "weekly": 24 * 7, "monthly": 24 * 30}


def _parse_hhmm(s):
    h, m = s.split(":")
    return int(h), int(m)


def _ideal_run_at(schedule, now_bkk):
    """Return the ideal datetime for this schedule period, or None."""
    parts = (schedule or "").split()
    if not parts: return None
    kind = parts[0]
    try:
        if kind == "daily" and len(parts) == 2:
            h, m = _parse_hhmm(parts[1])
            return now_bkk.replace(hour=h, minute=m, second=0, microsecond=0)
        if kind == "weekly" and len(parts) == 3 and parts[1] in _DAYS:
            h, m = _parse_hhmm(parts[2])
            target_dow = _DAYS.index(parts[1])
            delta = (now_bkk.weekday() - target_dow) % 7
            d = now_bkk - datetime.timedelta(days=delta)
            return d.replace(hour=h, minute=m, second=0, microsecond=0)
        if kind == "monthly" and len(parts) == 3:
            dd = int(parts[1]); h, m = _parse_hhmm(parts[2])
            return now_bkk.replace(day=dd, hour=h, minute=m, second=0, microsecond=0)
    except (ValueError, KeyError):
        return None
    return None


def _is_due(job, now_bkk, last_run_at):
    if not job.get("enabled"): return False
    sched = job.get("schedule") or ""
    ideal = _ideal_run_at(sched, now_bkk)
    if ideal is None: return False
    kind = sched.split()[0]
    interval_h = _INTERVAL_HOURS.get(kind, 24)
    if now_bkk < ideal: return False
    if last_run_at and last_run_at >= ideal: return False
    if (now_bkk - ideal).total_seconds() > interval_h * 3600 * 1.5: return False
    return True


# --- Cleanup janitor (Fix 8) ---
def _cleanup_old_restores():
    if not os.path.isdir(RESTORE_ROOT):
        return
    now = time.time()
    for name in os.listdir(RESTORE_ROOT):
        path = os.path.join(RESTORE_ROOT, name)
        if not os.path.isdir(path): continue
        try:
            age = now - os.path.getmtime(path)
        except OSError:
            continue
        if name.startswith(".dl-") and age > 3600:
            shutil.rmtree(path, ignore_errors=True)
        elif _SNAPSHOT_ID_RE.fullmatch(name) and age > 7 * 86400:
            shutil.rmtree(path, ignore_errors=True)


# --- Failure notify (Fix 10) ---
def _post_notify(job_id, label, status, error, snapshot_id=None):
    if not BACKUP_NOTIFY_URL: return
    try:
        payload = json.dumps({
            "job_id": job_id, "label": label, "status": status,
            "error": (str(error)[:1000] if error else None),
            "snapshot_id": snapshot_id, "host": "cid",
        }).encode()
        req = urllib.request.Request(
            BACKUP_NOTIFY_URL, data=payload, method="POST",
            headers={"Content-Type": "application/json"},
        )
        with urllib.request.urlopen(req, timeout=5) as resp:
            resp.read(1024)
    except Exception as e:
        logger.warning(f"BACKUP_NOTIFY_URL POST failed: {e}")


# --- Bootstrap (Fix 11 + 13) ---
def _ensure_system_check_job():
    jobs = _load_jobs()
    if any(j.get("id") == "__system_check__" for j in jobs.get("jobs", [])):
        return
    jobs.setdefault("jobs", []).append({
        "id": "__system_check__",
        "label": "Integrity check (weekly)",
        "kind": "system_check",
        "schedule": "weekly Sun 04:00",
        "enabled": True,
        "created_at": _now_iso(),
    })
    _save_jobs_atomic(jobs)


def _ensure_seeded_db_jobs():
    try:
        databases = _list_databases()
    except Exception as e:
        logger.warning(f"DB seeding deferred: mariadb not ready ({e})")
        return
    state = _load_state()
    seeded = set(state.get("seeded_dbs", []))
    jobs = _load_jobs()
    have_jobs = {j["id"] for j in jobs.get("jobs", [])}
    changed = False
    for db in databases:
        if db in SYSTEM_DBS: continue
        if db in seeded: continue
        job_id = f"db-{db}"
        if job_id not in have_jobs:
            jobs["jobs"].append({
                "id": job_id,
                "label": f"{db} daily 01:00",
                "kind": "db",
                "database": db,
                "tables": [],
                "schedule": "daily 01:00",
                "retention_days": 30,
                "enabled": True,
                "created_at": _now_iso(),
            })
            changed = True
        seeded.add(db)
    state["seeded_dbs"] = sorted(seeded)
    _save_state_atomic(state)
    if changed:
        _save_jobs_atomic(jobs)


def _ensure_data_files():
    os.makedirs(ADMIN_DATA, exist_ok=True)
    for name in ("backup-jobs.json", "backup-state.json"):
        real = f"{ADMIN_DATA}/{name}"
        example = f"{real}.example"
        if os.path.exists(real): continue
        if os.path.exists(example):
            shutil.copy(example, real)
        elif name == "backup-jobs.json":
            _atomic_write(real, {"version": 2, "jobs": []})
        elif name == "backup-state.json":
            _atomic_write(real, {"runs": {}, "seeded_dbs": []})
    _ensure_system_check_job()
    _ensure_seeded_db_jobs()


def _restic_env():
    keys = ("RESTIC_REPOSITORY", "RESTIC_PASSWORD",
            "B2_ACCOUNT_ID", "B2_ACCOUNT_KEY",
            "AWS_ACCESS_KEY_ID", "AWS_SECRET_ACCESS_KEY")
    env = os.environ.copy()
    for k in keys:
        v = os.environ.get(k)
        if v: env[k] = v
    return env


# --- Job runner (Fix 1 + 6) ---
def _run_db_job(job):
    pw = os.getenv("MYSQL_ROOT_PASSWORD", "")
    if not pw: raise RuntimeError("MYSQL_ROOT_PASSWORD not set")
    db = _validate_database(job["database"])
    tables = _validate_tables(db, job.get("tables", []))

    dump_cmd = ["docker", "exec", "-e", f"MYSQL_PWD={pw}", "cid-mariadb",
                "mariadb-dump", "-uroot",
                "--single-transaction", "--quick",
                "--routines", "--triggers", "--events",
                db, *tables]
    backup_cmd = ["restic", "backup", "--stdin",
                  "--stdin-filename", f"{db}.sql",
                  "--compression", "auto",
                  "--tag", f"job:{job['id']}",
                  "--tag", "kind:db",
                  "--tag", f"db:{db}",
                  "--host", "cid"]

    dump = subprocess.Popen(dump_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    try:
        rproc = subprocess.run(
            backup_cmd, stdin=dump.stdout, env=_restic_env(),
            capture_output=True, timeout=1800,
        )
    finally:
        if dump.stdout:
            dump.stdout.close()
        try:
            _, dump_err = dump.communicate(timeout=30)
        except subprocess.TimeoutExpired:
            dump.kill(); dump_err = b""
    if dump.returncode != 0:
        raise RuntimeError(f"mariadb-dump failed: {dump_err.decode('utf-8','replace').strip()}")
    if rproc.returncode != 0:
        raise RuntimeError(f"restic backup failed: {rproc.stderr.decode('utf-8','replace').strip()}")
    out = rproc.stdout.decode("utf-8", "replace")
    m = re.search(r"snapshot ([a-f0-9]{8}) saved", out)
    snap_id = m.group(1) if m else None
    _apply_retention(job)
    return snap_id, out


def _run_site_job(job):
    site = _validate_site(job["site"])
    base = f"/var/www/sites/{site}/public"
    if not os.path.isdir(base):
        raise RuntimeError(f"site path does not exist: {base}")
    excludes_default = ["*/logs", "*/.git", "*/node_modules",
                        "*/cache", "*/tmp", "*.log", "*/vendor"]
    extra = job.get("excludes") or []
    if not isinstance(extra, list): extra = []
    excludes = excludes_default + [str(e) for e in extra]
    args = ["restic", "backup",
            "--tag", f"job:{job['id']}",
            "--tag", "kind:site",
            "--tag", f"site:{site}",
            "--host", "cid"]
    for ex in excludes:
        args += ["--exclude", ex]
    args.append(base)
    proc = subprocess.run(args, env=_restic_env(), capture_output=True, text=True, timeout=1800)
    if proc.returncode != 0:
        raise RuntimeError(f"restic backup failed: {proc.stderr.strip()}")
    m = re.search(r"snapshot ([a-f0-9]{8}) saved", proc.stdout)
    snap_id = m.group(1) if m else None
    _apply_retention(job)
    return snap_id, proc.stdout


def _run_system_check():
    proc = subprocess.run(
        ["restic", "check", "--read-data-subset=5%"],
        env=_restic_env(), capture_output=True, text=True, timeout=1800,
    )
    if proc.returncode != 0:
        raise RuntimeError(f"restic check failed: {proc.stderr.strip()}")
    return None, proc.stdout


def _apply_retention(job):
    retention = int(job.get("retention_days", 30))
    subprocess.run(
        ["restic", "forget", "--tag", f"job:{job['id']}",
         "--keep-within", f"{retention}d", "--prune"],
        env=_restic_env(), capture_output=True, text=True, timeout=600,
    )


def _snapshot_size(snap_id):
    if not snap_id: return None
    sp = subprocess.run(
        ["restic", "stats", snap_id, "--mode", "raw-data", "--json"],
        env=_restic_env(), capture_output=True, text=True, timeout=60,
    )
    if sp.returncode != 0: return None
    try:
        return int(json.loads(sp.stdout).get("total_size", 0))
    except (ValueError, KeyError):
        return None


def _append_history(job_id, entry):
    state = _load_state()
    runs = state.setdefault("runs", {})
    rec = runs.setdefault(job_id, {"history": []})
    rec.setdefault("history", []).append(entry)
    rec["history"] = rec["history"][-20:]
    _save_state_atomic(state)


def _parse_last_run_at(job_id):
    state = _load_state()
    hist = state.get("runs", {}).get(job_id, {}).get("history", [])
    if not hist: return None
    try:
        return datetime.datetime.fromisoformat(hist[-1]["started_at"])
    except (ValueError, KeyError, TypeError):
        return None


def _run_job(job):
    started_at = _now_iso()
    try:
        kind = job.get("kind")
        if kind == "db":
            snap_id, out = _run_db_job(job)
        elif kind == "site":
            snap_id, out = _run_site_job(job)
        elif kind == "system_check":
            snap_id, out = _run_system_check()
        else:
            raise RuntimeError(f"unknown job kind: {kind!r}")
        size = _snapshot_size(snap_id)
        entry = {
            "started_at": started_at, "ended_at": _now_iso(),
            "status": "ok", "snapshot_id": snap_id, "size_bytes": size,
            "stdout_tail": (out or "")[-2000:], "stderr_tail": "",
        }
        _append_history(job["id"], entry)
        return entry
    except Exception as e:
        entry = {
            "started_at": started_at, "ended_at": _now_iso(),
            "status": "error", "snapshot_id": None, "size_bytes": None,
            "stdout_tail": "", "stderr_tail": str(e)[-2000:],
        }
        _append_history(job["id"], entry)
        _post_notify(job["id"], job.get("label", ""), "error", e)
        return entry


# --- Routes ---
@app.errorhandler(BadRequest)
def _h_bad(e): return jsonify({"ok": False, "error": str(e)}), 400


@app.errorhandler(Conflict)
def _h_conflict(e): return jsonify({"ok": False, "error": str(e)}), 409


@app.get("/backup/list-targets")
def backup_list_targets():
    try:
        dbs = [d for d in _list_databases() if d not in SYSTEM_DBS]
    except Exception as e:
        logger.warning(f"list-targets: db list deferred: {e}")
        dbs = []
    return jsonify({"ok": True, "databases": dbs, "sites": _list_sites()})


@app.post("/backup/list-tables")
def backup_list_tables():
    data = request.get_json(silent=True) or {}
    db = _validate_database(data.get("database", ""))
    return jsonify({"ok": True, "tables": _list_tables(db)})


@app.get("/backup/jobs")
def backup_jobs_get():
    return jsonify({"ok": True, **_load_jobs()})


@app.put("/backup/jobs")
def backup_jobs_put():
    data = request.get_json(silent=True) or {}
    if not isinstance(data.get("jobs"), list):
        raise BadRequest("jobs must be a list")
    for j in data["jobs"]:
        _validate_job_id(j.get("id", ""))
        kind = j.get("kind")
        if kind == "db":
            _validate_database(j.get("database", ""))
            _validate_tables(j["database"], j.get("tables", []))
        elif kind == "site":
            _validate_site(j.get("site", ""))
        elif kind == "system_check":
            pass
        else:
            raise BadRequest(f"unknown kind: {kind!r}")
    _save_jobs_atomic({"version": 2, "jobs": data["jobs"]})
    return jsonify({"ok": True})


@app.post("/backup/run")
def backup_run():
    data = request.get_json(silent=True) or {}
    job_id = _validate_job_id(data.get("job_id", ""))
    jobs = _load_jobs()
    job = next((j for j in jobs.get("jobs", []) if j["id"] == job_id), None)
    if not job:
        raise BadRequest(f"unknown job: {job_id}")
    with BackupLock(blocking=False):
        entry = _run_job(job)
    return jsonify({"ok": entry["status"] == "ok", "run": entry})


@app.get("/backup/snapshots")
def backup_snapshots():
    proc = subprocess.run(
        ["restic", "snapshots", "--json"],
        env=_restic_env(), capture_output=True, text=True, timeout=120,
    )
    if proc.returncode != 0:
        return _err(f"restic: {proc.stderr.strip()}", 500)
    try:
        snaps = json.loads(proc.stdout or "[]")
    except json.JSONDecodeError:
        snaps = []
    out = []
    for s in snaps:
        tags = s.get("tags") or []
        out.append({
            "id": s.get("short_id"),
            "long_id": s.get("id"),
            "time": s.get("time"),
            "host": s.get("hostname"),
            "tags": tags,
            "paths": s.get("paths") or [],
            "job_id": next((t.split(":", 1)[1] for t in tags if t.startswith("job:")), None),
            "kind":   next((t.split(":", 1)[1] for t in tags if t.startswith("kind:")), None),
        })
    return jsonify({"ok": True, "snapshots": out})


@app.post("/backup/restore")
def backup_restore():
    data = request.get_json(silent=True) or {}
    sid = _validate_snapshot(data.get("snapshot_id", ""))
    target = os.path.join(RESTORE_ROOT, sid)
    if os.path.exists(target):
        shutil.rmtree(target, ignore_errors=True)
    os.makedirs(target, exist_ok=True)
    proc = subprocess.run(
        ["restic", "restore", sid, "--target", target],
        env=_restic_env(), capture_output=True, text=True, timeout=900,
    )
    if proc.returncode != 0:
        shutil.rmtree(target, ignore_errors=True)
        return _err(f"restic restore: {proc.stderr.strip()}", 500)
    _set_restore_perms(target)
    return jsonify({"ok": True, "path": target})


@app.post("/backup/forget")
def backup_forget():
    data = request.get_json(silent=True) or {}
    sid = _validate_snapshot(data.get("snapshot_id", ""))
    proc = subprocess.run(
        ["restic", "forget", sid, "--prune"],
        env=_restic_env(), capture_output=True, text=True, timeout=300,
    )
    if proc.returncode != 0:
        return _err(f"restic forget: {proc.stderr.strip()}", 500)
    return jsonify({"ok": True})


@app.post("/backup/download-db")
def backup_download_db():
    """Restore a DB snapshot into a fresh .dl-* dir; return path for PHP to stream."""
    data = request.get_json(silent=True) or {}
    sid = _validate_snapshot(data.get("snapshot_id", ""))
    nonce = secrets.token_hex(6)
    stage_dir = os.path.join(RESTORE_ROOT, f".dl-{sid}-{nonce}")
    os.makedirs(stage_dir, exist_ok=True)
    proc = subprocess.run(
        ["restic", "restore", sid, "--target", stage_dir],
        env=_restic_env(), capture_output=True, text=True, timeout=600,
    )
    if proc.returncode != 0:
        shutil.rmtree(stage_dir, ignore_errors=True)
        return _err(f"restic restore: {proc.stderr.strip()}", 500)
    sql_files = []
    for root, _, files in os.walk(stage_dir):
        for f in files:
            if f.endswith(".sql"):
                sql_files.append(os.path.join(root, f))
    if not sql_files:
        shutil.rmtree(stage_dir, ignore_errors=True)
        return _err("no .sql in snapshot (not a db backup?)", 400)
    sql_path = sql_files[0]
    size = os.path.getsize(sql_path)
    HARD_CAP = 500 * 1024 * 1024
    if size > HARD_CAP:
        shutil.rmtree(stage_dir, ignore_errors=True)
        return _err(f"snapshot too large ({size} bytes); fetch via scp from {RESTORE_ROOT}/", 413)
    _set_restore_perms(stage_dir, writable_by_php=True)
    return jsonify({"ok": True, "file": sql_path, "dir": stage_dir,
                    "size": size, "filename": os.path.basename(sql_path)})


@app.get("/backup/runs")
def backup_runs():
    state = _load_state()
    return jsonify({"ok": True, "runs": state.get("runs", {})})


@app.post("/backup/tick")
def backup_tick():
    """Cron-driven scheduler. Idempotent."""
    _cleanup_old_restores()
    _ensure_seeded_db_jobs()  # catch up if mariadb wasn't ready at boot
    jobs = _load_jobs()
    now_bkk = datetime.datetime.now(TZ_BKK)
    ran = []
    with BackupLock(blocking=True):
        for job in jobs.get("jobs", []):
            last_run_at = _parse_last_run_at(job["id"])
            if _is_due(job, now_bkk, last_run_at):
                entry = _run_job(job)
                ran.append({"job_id": job["id"], "status": entry["status"]})
    return jsonify({"ok": True, "ran": ran})


# --- Bootstrap at import (gunicorn calls this once per worker) ---
try:
    _ensure_data_files()
except Exception as _e:
    logger.warning(f"bootstrap deferred: {_e}")
