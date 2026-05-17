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
import json
import os
import re
import subprocess
from typing import Any

from flask import Flask, jsonify, request

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
