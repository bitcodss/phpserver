# phpserver — Project Specification

Reference document for development work on this repository. The README is the quick-start; this file is the long-form companion that covers stack, layout, features, configuration, and conventions.

---

## 1. Overview

A Docker-based PHP 7.4 hosting stack. The compose stack is ingress-agnostic — `cid-nginx` listens on a localhost-bound port and a separate frontend terminates TLS. Two deployments are supported today:

- **Home server** — Cloudflare Tunnel (`cloudflared` daemon in its own compose project at `/home/bitcodata/cloudflare/`) maps `php.bitco.space` to the host loopback. No public-facing port on the home router. `NGINX_BIND` / `PMA_BIND` stay at the `0.0.0.0` default so the tunnel can reach via the docker-bridge gateway.
- **Cloud-deploy track** (recipe in `docs/archive/`) — Caddy + Let's Encrypt directly on a VPS, with `NGINX_BIND=127.0.0.1` / `PMA_BIND=127.0.0.1` so Caddy is the only public listener.

Current active site:

| Domain | Purpose |
|---|---|
| `php.bitco.space` | **Admin dashboard** (ศ.Cid) — manage containers, sites, PHP/FPM settings, databases, users, logs, security audit, backup engine, runtime settings. Hosts the only admin codebase; any additional sites added via the admin UI inherit the same template tree. |

Container names are all prefixed `cid-` and share a single bridge network (`cid-network`). Timezone everywhere is `Asia/Bangkok`. For shipped-work history see `CHANGELOG.md`; for current operational state and open follow-ups see `docs/project-status.md`.

---

## 2. Technology Stack

### Services (`docker/docker-compose.yml`)

| Service | Container | Image | Host port | Internal | Role |
|---|---|---|---|---|---|
| php74 | `cid-php74` | custom build from `php:7.4-fpm-bullseye` | — | FPM on `/run/php-fpm/www.sock` (unix) | PHP-FPM runtime |
| nginx | `cid-nginx` | `nginx:1.24-alpine` | **`${NGINX_BIND:-0.0.0.0}:9080`** | 80 | Reverse proxy / web server |
| mariadb | `cid-mariadb` | `mariadb:10.11` | — (internal only) | 3306 | Primary DB |
| phpmyadmin | `cid-phpmyadmin` | `phpmyadmin:5.2` | **`${PMA_BIND:-0.0.0.0}:9081`** | 80 | DB UI |
| redis | `cid-redis` | `redis:7-alpine` | — (internal only) | 6379 | Sessions / cache (64 MB, LRU) |
| **broker** | `cid-broker` | custom build from `python:3.12-alpine` (Flask + gunicorn) | — | listens on `/run/broker/broker.sock` (unix) | Privileged-ops sidecar — holds `/var/run/docker.sock`, exposes a tightly scoped HTTP API to cid-php74 |
| sftp | `cid-sftp` | `lscr.io/linuxserver/openssh-server` | **2222** (public) | 2222 | File transfer (`webmaster` user) |

`NGINX_BIND` / `PMA_BIND` are env-driven so the same compose file runs on home (default `0.0.0.0`) and behind a host-level Caddy (override to `127.0.0.1`). Public ingress is whichever frontend the host runs — see §1.

### PHP image (`docker/php/Dockerfile`)

- Base: `php:7.4-fpm-bullseye`
- Extensions: `gd` (freetype/jpeg/webp), `mysqli`, `pdo_mysql`, `zip`, `intl`, `mbstring`, `xml`, `curl`, `bcmath`, `opcache`, `exif`, `soap`, `pcntl`, plus **`redis 5.3.7`** via PECL.
- Tools: Composer. **Docker CLI is intentionally NOT installed** — privileged ops route through `cid-broker` instead.
- `nginx` group (GID 101) added so the FPM unix socket created with `listen.group=nginx` is readable by `cid-nginx` over the shared volume.
- Base image's `zz-docker.conf` is overwritten to keep only `daemonize = no` (its default `listen = 9000` would override our socket-mode `listen` directive).

### Broker image (`docker/broker/`)

- Base: `python:3.12-alpine` + Flask 3 + gunicorn 23.
- Includes a Docker CLI static binary (28.0.1) — that's the only container in the stack that can talk to the Docker daemon.
- Listens on `/run/broker/broker.sock` (gunicorn `--group phpaccess --umask 0117` → socket is `root:phpaccess` mode 0660; `phpaccess` is GID 33, matching `www-data` in `cid-php74`).
- Routes:
  - **Privileged ops**: `GET /healthz`, `POST /container/status` (allow-listed read-only inspect), `POST /container` (start/stop/restart, narrower allow-list), `POST /mysql` + `POST /mysql-query` (run SQL as MariaDB root with `--default-character-set=utf8mb4`; password from env), `POST /logs` (docker logs --tail with stdout/stderr split), `POST /nginx/reload`, `POST /caddy/route` (POST a route JSON to host Caddy admin via `host.docker.internal:2019` — used by deployments that put Caddy in front).
  - **Site creation**: `POST /site/create` — copies `/var/www/sites/_template/` → new site folder with `{{PLACEHOLDER}}` substitution, writes nginx vhost from `/etc/nginx/conf.d/_template.conf.example`, chowns to match parent dir's owner. PHP can't write under `/var/www/sites` or `/etc/nginx/conf.d` (www-data has no group access on either bind mount) — this route is how `admin/api/add_site.php` provisions new sites.
  - **Backup engine (v2)**: `GET /backup/list-targets`, `POST /backup/list-tables`, `GET /backup/jobs`, `PUT /backup/jobs`, `POST /backup/run`, `GET /backup/snapshots`, `POST /backup/restore`, `POST /backup/forget`, `POST /backup/download-db`, `GET /backup/runs`, `POST /backup/tick`. State persists at `/admin-data/backup-jobs.json` + `/admin-data/backup-state.json` (bind-mounted from the active site's `admin/data/`). Tick is invoked by host cron `*/5 * * * *` so missed schedules catch up within 1.5× the interval.

### Volumes & network

- Named volumes: `mariadb-data`, `redis-data`.
- Tmpfs volumes: `fpm-socket` (shared by php74 ↔ nginx, holds `www.sock`), `broker-socket` (shared by broker ↔ php74, holds `broker.sock`).
- Bind mounts: `../sites → /var/www/sites` (rw for broker, rw for php74 + nginx), `docker/nginx/conf.d → /etc/nginx/conf.d` (ro for nginx, rw for broker — broker is the only writer), php config dirs, `docker/logs/{nginx,mariadb}`. **php74 no longer mounts `/var/run/docker.sock`** — only `cid-broker` does.
- Broker also mounts the active site's `admin/data/` directly as `/admin-data` (rw) for backup state; the file paths of `metrics.json` / `backup-state.json` / `backup-jobs.json` resolve through that single source of truth.
- Network: bridge `cid-network`. Broker has `host.docker.internal:host-gateway` extra-host so it can reach the host's Caddy admin (when present) without `--network=host`.

---

## 3. Directory Structure

```
/home/bitcodata/phpserver/
├── README.md
├── spec.md                          # this file — long-form reference
├── CHANGELOG.md                     # shipped-work history, one bullet per phase
├── .gitignore
│
├── docker/
│   ├── docker-compose.yml
│   ├── .env.example                 # full env template (see §4 for the keys)
│   ├── .env                         # local, gitignored
│   ├── php/
│   │   ├── Dockerfile
│   │   └── conf/
│   │       ├── php-custom.ini       # mounted at /usr/local/etc/php/conf.d/99-custom.ini
│   │       └── www.conf             # mounted at /usr/local/etc/php-fpm.d/www.conf
│   ├── broker/                      # privileged-ops sidecar (Python+Flask)
│   │   ├── Dockerfile
│   │   ├── app.py                   # Flask routes (privileged ops + backup engine + /site/create)
│   │   └── requirements.txt
│   ├── nginx/
│   │   ├── nginx.conf
│   │   └── conf.d/
│   │       ├── _8g.conf                       # 8G firewall detection maps
│   │       ├── _template.conf.example         # nginx vhost template (consumed by scripts/new-site.sh + broker /site/create)
│   │       └── php.bitco.space.conf           # active vhost
│   ├── database/
│   │   ├── all_databases.sql        # seed dump for first-time bring-up only (F-004 — see project-status §5.6)
│   │   └── users_grants.sql         # seed grants (same caveat)
│   └── logs/
│       ├── nginx/                   # access.log, error.log per site (rotated by host)
│       └── mariadb/                 # slow.log
│
├── docs/
│   ├── project-status.md            # current-state resume guide (read this first when picking work back up)
│   ├── disaster-recovery.md         # drafted runbook (uncommitted as of writing — see project-status §6)
│   └── archive/                     # shipped plans + audit findings kept for design rationale
│       ├── audit-plan.md
│       ├── audit-findings.md
│       ├── infra-hardening-plan.md
│       ├── backup-v2-plan.md
│       └── todo.md
│
├── scripts/
│   ├── collect_metrics.sh           # cron → metrics.json (auto-discovers the active site folder by mtime)
│   ├── new-site.sh                  # bootstrap CLI for the FIRST site of a fresh deployment
│   ├── cid-backup.sh                # legacy monolithic backup — deprecation banner; superseded by Backup v2 inside broker
│   ├── cid-backup-tick.sh           # host cron → broker /backup/tick (every 5 min)
│   ├── cid-backup.env.example       # template for /etc/cid-backup.env
│   └── cid-backup-setup.md          # B2 sign-up + ops doc
│
└── sites/
    ├── _config/
    │   └── nginx/                   # archival copies of generated per-site nginx + caddy configs (historical)
    ├── _template/                   # ★ canonical admin-host template tree, consumed by both
    │   │                            #   scripts/new-site.sh (host bootstrap) and broker POST /site/create
    │   │                            #   (admin UI "+ Add New Site"). Placeholders: {{DOMAIN}},
    │   │                            #   {{LABEL}}, {{DESCRIPTION}}, {{DB_NAME}}, {{DB_USER}},
    │   │                            #   {{SHORT_NAME}}, {{CREATED}}.
    │   ├── site.json.template
    │   └── public/
    │       ├── index.php            # landing page (uses {{SHORT_NAME}})
    │       └── admin/               # full admin tree (see below for module list)
    │
    └── php.bitco.space/public/      # active admin host — instantiation of _template/, served by the only vhost
        ├── index.php                # landing
        └── admin/
            ├── index.php            # login + sidebar (7 nav items) + page router; renders CSRF_TOKEN JS const
            ├── _lib.php             # shared brokerCall() / mysqlExec() / mysqlQuery() / setting() helpers
            ├── data/                # runtime state, gitignored via sites/*/public/admin/data/* glob
            │   ├── backup-jobs.json          # broker-managed, mirrored from .example on first run
            │   ├── backup-jobs.json.example  # committed
            │   ├── backup-state.json         # broker-managed
            │   ├── backup-state.json.example # committed
            │   ├── drill-history.json        # operator-appended at each restore drill
            │   └── metrics.json              # collect_metrics.sh writes here every 5 min
            ├── api/
            │   ├── _bootstrap.php       # session auth + CSRF gate, required by every state-changing endpoint
            │   ├── server_info.php
            │   ├── metrics.php          # auth-gated JSON proxy to data/metrics.json
            │   ├── add_site.php         # orchestrates broker /site/create + /mysql + /caddy/route + /nginx/reload
            │   ├── save_php.php
            │   ├── save_fpm.php
            │   ├── save_settings.php    # writes opc_db.site_settings via broker /mysql
            │   ├── create_db.php
            │   ├── drop_db.php
            │   ├── manage_user.php
            │   ├── backup.php           # dispatcher to broker /backup/* (including the GET download-by-link)
            │   ├── container_action.php
            │   └── site_config.php
            └── templates/
                ├── dashboard.php        # metrics + container status + counts
                ├── sites.php            # site list + "+ Add New Site" modal (calls add_site.php)
                ├── php.php              # PHP/FPM ini editor
                ├── database.php         # DB/user/status tabs + phpMyAdmin link (from setting('PMA_PUBLIC_URL'))
                ├── security.php         # 10-check audit + score
                ├── logs.php             # tails per-site nginx logs + FPM stderr + slow.log
                ├── backup.php           # job list, snapshots, restore, download, run-now, integrity check
                └── settings.php         # runtime overrides for PMA_PUBLIC_URL + SITE_PUBLIC_IP
```

Site root convention inside the php74 container: **`/var/www/sites/<domain>/public/`** (mapped from `./sites/<domain>/public/` on the host). The historical `sites/opc.bitco.link/` was renamed to `sites/php.bitco.space/` in commit `e4d56f8`; `sites/opc2.bitco.link/` (Cressida 2026 survey app) was removed in `54bd8a8`.

---

## 4. Configuration Reference

### `docker/php/conf/php-custom.ini`

| Setting | Value |
|---|---|
| `memory_limit` | 256M |
| `max_execution_time` / `max_input_time` | 300 |
| `max_input_vars` | 3000 |
| `post_max_size` / `upload_max_filesize` | 64M |
| `max_file_uploads` | 20 |
| `display_errors` / `display_startup_errors` | **Off** (errors go to FPM stderr → docker logs) |
| `error_reporting` | `E_ALL & ~E_DEPRECATED & ~E_STRICT` |
| `expose_php` | Off |
| `allow_url_fopen` / `allow_url_include` | On / **Off** |
| `disable_functions` | `passthru, parse_ini_file, show_source, dl` |
| `open_basedir` | `/var/www/sites:/tmp:/usr/share/php:/usr/local/bin:/proc` |
| `session.save_handler` / `save_path` | `redis` / `tcp://redis:6379` |
| `session.cookie_httponly` / `cookie_secure` / `samesite` / `use_strict_mode` | 1 / **1** / Lax / 1 |
| `date.timezone` | Asia/Bangkok |
| `opcache.memory_consumption` / `max_accelerated_files` / `revalidate_freq` | 128 / 10000 / 2 |
| `realpath_cache_size` / `_ttl` | 4096k / 600 |

### `docker/php/conf/www.conf` (FPM pool)

- User/group: `www-data`
- `listen = /run/php-fpm/www.sock`, `listen.owner = www-data`, `listen.group = nginx`, `listen.mode = 0660`
- `pm = dynamic`, `pm.max_children = 20`, `start_servers = 4`, `min_spare = 2`, `max_spare = 8`, `pm.max_requests = 500`
- `request_slowlog_timeout = 5s`, status page at `/fpm-status` (internal)
- `clear_env = no` — pass through container env vars so `getenv('MYSQL_ROOT_PASSWORD')` etc. work in PHP

### `docker/nginx/nginx.conf`

- `worker_processes auto`, `worker_connections 1024`
- `keepalive_timeout 65`, `client_max_body_size 64m`
- Gzip level 6 over text/css/json/js/xml
- Always-on security headers: `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `X-XSS-Protection: 1; mode=block`, `Referrer-Policy: strict-origin-when-cross-origin`
- `server_tokens off`
- Rate-limit zones: `general` 10r/s, `login` 3r/s (10 MB shared mem each)

### `docker/nginx/conf.d/_8g.conf` (Phase 1 security)

Detection-only `map` directives at `http {}` level (file is auto-included by
nginx because `conf.d/*.conf` is read into the http context). Sets a single
`$block_all` variable to 1 when any rule fires. Per-site vhosts decide what to
do — currently `if ($block_all) { return 403; }`.

Detects: scanner UAs (sqlmap, nikto, masscan, Censys, ZGrab, …), empty UA,
suspicious referers, XSS / SQLi / RCE patterns in query strings (literal + URL-encoded variants), file-read probes (`/etc/passwd`, `proc/self/environ`, `.ssh`), dangerous URIs (`/wp-config.php`, `/.env`, `/xmlrpc.php`, sensitive extensions, `.git`/`.svn`), and dangerous HTTP methods (TRACE/TRACK/CONNECT/MOVE/PROPFIND/etc).

### `docker/nginx/conf.d/*.conf` (per-site)

- FastCGI pass to **`unix:/run/php-fpm/www.sock`** (via the shared `fpm-socket` tmpfs volume)
- `if ($block_all) { return 403; }` immediately after `limit_req`
- `try_files $uri $uri/ /index.php?$query_string` (SPA-friendly)
- Static files: 30-day immutable cache
- 60s FastCGI connect timeout, 300s read/write
- Deny access to dotfiles and `.env / .git / .ini / .log / .sql / .sh / .conf / .bak / .test / .orig / .old`
- `fastcgi_hide_header X-Powered-By` and per-site `open_basedir` reinforcement via `fastcgi_param PHP_VALUE`
- `location ^~ /admin/data/ { deny all; return 404; }` (metrics.json is served via auth-gated PHP, not directly)
- `location = /admin/ { limit_req zone=login burst=5 nodelay; }` on the admin site for tighter throttle on the login endpoint

### `docker/.env.example`

```
# MariaDB
MYSQL_ROOT_PASSWORD=…
MYSQL_DATABASE=opc_db
MYSQL_USER=opc_user
MYSQL_PASSWORD=…

# Survey app — historical; opc2 site removed in 54bd8a8 but env keys
# linger in .env.example for back-compat. Safe to leave unset.
OPC2_DB_HOST=mariadb
OPC2_DB_NAME=dw_cressida
OPC2_DB_USER=dw_spy
OPC2_DB_PASSWORD=…
OPC2_STATUS_TOKEN=…
OPC2_RAWDATA_TOKEN=…

# Admin dashboard
ADMIN_USER=admin
ADMIN_PASS_HASH=$$2y$$10$$…   # bcrypt hash; $ doubled so compose doesn't expand

# SFTP
SFTP_USER=webmaster
SFTP_PASSWORD=…

# Public-facing values shown in the admin UI. Both can be overridden at
# runtime via /admin/?page=settings (writes opc_db.site_settings). Read
# order in PHP via setting(): DB → env → hard-coded default.
SITE_PUBLIC_IP=                  # rendered in Sites page DNS instructions
PMA_PUBLIC_URL=                  # rendered as the phpMyAdmin link on Database page

# Host port bindings. 0.0.0.0 for home/tunnel deployments (cloudflared
# reaches via docker bridge gateway). 127.0.0.1 for cloud deployments
# where host-level Caddy is the only legitimate caller.
NGINX_BIND=0.0.0.0
PMA_BIND=0.0.0.0
```

**Runtime overrides** — the admin Settings page persists `PMA_PUBLIC_URL` and `SITE_PUBLIC_IP` into `opc_db.site_settings` (idempotently created by `_lib.php::setting()` on first call). Clearing a field in the UI deletes the row and the value falls back through to the env var (or the hard-coded placeholder if the env var is also unset).

MariaDB command flags (in compose): `utf8mb4 / utf8mb4_unicode_ci`, `innodb-buffer-pool-size=256M`, `max-connections=100`, slow-query log at `> 2s` to `/var/log/mysql/slow.log`.

---

## 5. Admin Dashboard — `php.bitco.space/admin/`

### Authentication

- Session-based. User + bcrypt hash come from container env (`ADMIN_USER`, `ADMIN_PASS_HASH`); nothing is hardcoded.
- Login form POSTs to `/admin/`. Credential check uses `hash_equals` + `password_verify`. On success: `session_regenerate_id(true)`, set `$_SESSION['authenticated']`, mint a CSRF token, redirect.
- **Failed login returns HTTP 401** — signal consumed by the fail2ban `nginx-admin` jail on the host.
- Logout: unset `$_SESSION`, clear the session cookie with the correct flags, `session_destroy()`, redirect.
- CSRF token issued at login lives in `$_SESSION['csrf']`; every state-changing API POST must echo it back in `X-CSRF-Token` (enforced by `admin/api/_bootstrap.php`).

### Layout

- Sidebar (dark `#0f172a` background, accent `#38bdf8`) with eight nav links (Dashboard / Sites / PHP Settings / Database / Security / Backup / Logs / Settings) and a logout footer. Main pane is included from `templates/<page>.php` based on `?page=…`. Default page is `dashboard`.
- All write actions are AJAX `POST` to `/admin/api/<endpoint>.php` with JSON bodies via the `apiCall()` helper in `index.php`, which automatically attaches `X-CSRF-Token`. Results surface via a `showToast()` notification.

### Modules

| Page | Template | What it does |
|---|---|---|
| Dashboard | `templates/dashboard.php` | Container status for all seven services, CPU/mem/disk/uptime, site & DB counts, 7-day historical metrics from `data/metrics.json` (written by host cron `collect_metrics.sh`; falls back to a "no data yet" message + cron-install hint when the file is missing). |
| Sites | `templates/sites.php` | Scans `/var/www/sites/*` (excluding `_template` / `_config`), reads each site's `site.json`, shows domain / SSL / DB / created. "Add new site" modal POSTs to `api/add_site.php`. DNS instructions render `setting('SITE_PUBLIC_IP')`. |
| PHP Settings | `templates/php.php` | Live `ini_get_all()` dump. Editable groups: Core (memory, execution, uploads, errors), Session (handler, cookie flags), OPcache (enable, memory, files), Security (expose_php, url include, disable_functions). Lists loaded extensions. |
| Database | `templates/database.php` | Tabs for **Databases** (table count, size MB, collation), **Users** (MySQL users + grants), **Status** (uptime, threads, queries, slow queries). Hides system schemas. The phpMyAdmin link uses `setting('PMA_PUBLIC_URL')` with a `https://pma.<HOST>` fallback. |
| Security | `templates/security.php` | 10-check audit → percentage score. Checks: `expose_php` off, `display_errors` off, `allow_url_include` off, `disable_functions` populated, `open_basedir` set, session cookie flags, Docker network isolation, nginx rate limiting present, four security headers present. |
| Backup | `templates/backup.php` | Job list (per-DB + system_check + config_backup), per-job last-run status + history (last 20), snapshots, restore-to-stage, download-as-gzip, forget, run-now, add/edit job modal. Talks exclusively to broker `/backup/*`. localStorage cache with 10-min TTL. |
| Logs | `templates/logs.php` | Tails nginx access/error per domain, PHP error log (FPM stderr), MariaDB slow log. |
| Settings | `templates/settings.php` | Runtime overrides for `PMA_PUBLIC_URL` + `SITE_PUBLIC_IP`. Input value shows the DB override (raw mode); a helper line below shows the effective fallback ("No override set. Currently using: …"). Clear-and-save deletes the DB row → falls through to env. |

### Admin API (`admin/api/`)

All endpoints require an authenticated session. POST = JSON in / JSON out unless noted.

| Endpoint | Method | Purpose / side effects |
|---|---|---|
| `server_info.php` | GET | JSON: IP (dynamic via `$_SERVER['SERVER_ADDR']`), hostname, PHP version, current time. |
| `metrics.php` | GET | Auth-gated proxy that streams `data/metrics.json` to the dashboard JS. The raw path is denied at nginx; only this endpoint exposes it. |
| `add_site.php` | POST | Provisions a new site end-to-end. Filesystem ops go through broker `POST /site/create` (which copies `sites/_template/` + writes the nginx vhost from `_template.conf.example` with `{{PLACEHOLDER}}` substitution + chowns to the parent dir's owner). DB ops via broker `/mysql`, Caddy route via `/caddy/route`, nginx reload via `/nginx/reload`. Passwords for new MySQL users surfaced once in the response — never persisted to `site.json`. |
| `save_php.php` | POST | Per-key validator allow-list; values rejected if they contain `\r\n;[]`. Writes to the rw mount at `/usr/local/etc/php-conf/php-custom.ini`. Restart via broker `/container`. |
| `save_fpm.php` | POST | Validated FPM directives written to `/usr/local/etc/php-conf/www.conf`. Restart via broker `/container`. |
| `save_settings.php` | POST | Persists runtime overrides to `opc_db.site_settings` via broker `/mysql`. Whitelist of two keys (`PMA_PUBLIC_URL`, `SITE_PUBLIC_IP`); per-key validation (`FILTER_VALIDATE_URL` / `FILTER_VALIDATE_IP`); empty value DELETEs the row. |
| `create_db.php` | POST | `CREATE DATABASE … utf8mb4_unicode_ci`. Optional `CREATE USER` + grants — passwords must match `[A-Za-z0-9!@#%^&*()_+=\-]{8,64}` to prevent SQL injection via the password value. |
| `drop_db.php` | POST | Requires `confirm` field to match the DB name exactly. System schemas (`mysql`, `information_schema`, `performance_schema`, `sys`) blocked. |
| `manage_user.php` | POST | MySQL user CRUD. `safeUser` strips non-alphanumeric, `safeHost` allow-lists `localhost`/`127.0.0.1`/`::1`/`%`, `safePass` enforces the password regex. |
| `backup.php` | POST + GET | Dispatcher for the broker's 9 backup routes. Action-keyed JSON for POSTs (`list-targets`, `list-tables`, `jobs`, `jobs-put`, `run`, `snapshots`, `restore`, `forget`, `runs`); GET `?action=download&snapshot_id=…` streams the restored gzip with a shutdown handler that cleans the staging dir even if the browser disconnects. |
| `container_action.php` | POST | Calls broker `/container` with name+action both allow-listed. Containers: `cid-php74`, `cid-nginx`, `cid-mariadb`, `cid-phpmyadmin`, `cid-redis`. Actions: `start`, `stop`, `restart`. |
| `site_config.php` | POST | Read & write a per-site `site.json` metadata file. Domain regex-validated. |

---

## 6. Scripts & host services

### `scripts/collect_metrics.sh`

- Designed to be invoked by cron every 5 minutes on the host.
- Collects: CPU load (1m), CPU cores, total/used memory, total/used disk, uptime.
- **Auto-discovers the active site folder by mtime** — `find sites -maxdepth 1 ! -name '_*' -printf '%T@ %p\n' | sort -rn | head -1`. Freshly-renamed folders (which inherit `cp -r`'s timestamp) win over older ones. Override via `METRICS_FILE` env in the cron line if multi-site coexistence ever happens.
- Writes JSON entries trimmed to a rolling window of **2016 entries** (= 7 days × 24 h × 12 samples/h).
- The Dashboard JS fetches `/admin/api/metrics.php` (auth-gated), not the raw file.

### `scripts/new-site.sh`

- Bootstrap CLI for the **first** site of a fresh deployment (the admin host has to exist before the admin UI can add subsequent sites — chicken-and-egg).
- Reads `sites/_template/` + `docker/nginx/conf.d/_template.conf.example`, substitutes `{{DOMAIN}}` / `{{LABEL}}` / `{{DESCRIPTION}}` / `{{DB_NAME}}` / `{{DB_USER}}` / `{{SHORT_NAME}}` / `{{CREATED}}` via perl (env-passed for shell safety).
- Validates the domain against the same regex `admin/api/add_site.php` uses.
- Prints the `.env` entries the operator must add (`SITE_PUBLIC_IP`, `PMA_PUBLIC_URL`, `NGINX_BIND`, `PMA_BIND`).
- Does NOT touch `docker/.env`, create the DB, or run docker compose — those are explicit operator actions.
- For subsequent sites on a running deployment, use the admin UI's "+ Add New Site" instead — which goes through broker `POST /site/create` (same template, different code path; eventually the two paths could converge, but they serve disjoint lifecycle stages today).

### Backup engine v2 — `cid-broker` + host cron tick

- The backup pipeline used to be a monolithic `scripts/cid-backup.sh` invoked nightly; it's now job-driven inside `cid-broker`. The old script remains in the repo with a deprecation banner so existing host installs don't break, but new deployments should not install it.
- **Host cron**: `*/5 * * * * /usr/local/bin/cid-backup-tick` calls broker `POST /backup/tick`. Broker evaluates schedules, fires due jobs, persists state.
- **System jobs** (idempotently seeded at broker boot, suppressible via the `seeded_dbs` marker once the operator deletes them):
  - `__system_check__` — weekly Sun 04:00 `restic check` over a 5% data subset; full coverage in ~20 weeks.
  - `__system_config_backup__` — daily 01:30, 90-day retention. Tars host config files (`/etc/cid-backup.env`, `docker/.env`, `docker-compose.yml`, fail2ban, SSH drop-in, authorized_keys, user crontabs, `docker/daemon.json`) plus `dpkg --get-selections`.
- **DB jobs** — one per database, default daily 01:00 + 30-day retention. Streams `mariadb-dump` to restic.
- **Concurrency**: `fcntl.flock` on `/run/cid-backup.lock`. RunNow blocks waiting for the lock; tick returns immediately if locked.
- **Catch-up**: missed schedules fire on the next tick within 1.5× the interval.
- **Storage**: Backblaze B2 by default; restic accepts any backend via `RESTIC_REPOSITORY`. Encryption is client-side via the passphrase in `/etc/cid-backup.env`. Lose the passphrase → backups are unreadable.
- **Acceptance test**: Phase 0.1 drill (recorded in `admin/data/drill-history.json`) — 9/9 byte-equal checks across Thai unicode, emoji, CJK, NULL, JSON, large TEXT, FK survival, and SQL escape edge cases.
- Logs: `/var/log/cid-backup.log` (host-side cron output) + per-run stdout/stderr tails inside `backup-state.json`.
- Setup walkthrough: `scripts/cid-backup-setup.md`.

### Host-only artifacts (not in git)

Mirror these via your config-management tool if you ever rebuild the box:

| Path | Purpose |
|---|---|
| `/etc/fail2ban/jail.local` | 4 jails (sshd, sftp, nginx-admin, nginx-8g) |
| `/etc/fail2ban/filter.d/nginx-admin.conf` | matches POST /admin/ + 401 |
| `/etc/fail2ban/filter.d/nginx-8g.conf` | matches *.access.log + 403 |
| `/etc/ssh/sshd_config.d/99-hardening.conf` | SSH drop-in: `PasswordAuthentication no` + 14 other directives |
| `/usr/local/bin/cid-backup` | backup script |
| `/etc/cid-backup.env` | restic passphrase + B2 keys (0600 root) |
| `/etc/cron.d/cid-backup` | nightly 02:00 Asia/Bangkok |
| `/etc/logrotate.d/cid-backup` | weekly rotate, keep 12 |

---

## 7. Security Posture

**In place**

- Bcrypt hashing helper used in admin code path; Redis-backed sessions with `HttpOnly` and `SameSite=Lax`, `use_strict_mode=1`.
- `open_basedir` restricts PHP filesystem access to `/var/www/sites:/tmp:/usr/share/php:/usr/local/bin:/proc`.
- Docker network isolation; only nginx (9080), phpMyAdmin (9081), and SFTP (2222) are published to the host. MariaDB, Redis, and the broker are reachable only inside `cid-network` (broker not even on TCP — unix socket only).
- **No Docker socket in `cid-php74`**, no Docker CLI either — privileged ops go through `cid-broker`'s tight HTTP API. PHP RCE no longer escalates to host.
- **PHP-FPM listens on a unix socket** (`/run/php-fpm/www.sock`, mode 0660 `www-data:nginx`), not `0.0.0.0:9000`. No cross-container TCP path to FPM.
- Nginx rate limiting (`general` 10r/s, `login` 3r/s on `/admin/` POST), four always-on security response headers, and the 8G detection ruleset blocking malicious request patterns.
- Dangerous PHP functions disabled (`passthru`, `parse_ini_file`, `show_source`, `dl`).
- `expose_php = Off`, `display_errors = Off`, `server_tokens off`, `X-Powered-By` stripped at FastCGI.
- All credentials in container env (admin bcrypt hash, MariaDB root, survey DB, SFTP, status tokens). Nothing hardcoded in source.
- Admin auth: bcrypt `password_verify` + `hash_equals` + `session_regenerate_id(true)` + CSRF token enforced on every state-changing POST.
- Survey SQL: all UPDATEs parameterized; column writes restricted to a `INFORMATION_SCHEMA`-driven allow-list with a deny-list overlay for server-managed columns.
- Host-level **fail2ban** (4 jails: sshd, sftp, nginx-admin, nginx-8g) bans abusive IPs at iptables level.
- Host-level **SSH hardened** (`PasswordAuthentication no`, key-only, root login disabled, all forwarding off, idle timeout, modern crypto baseline).
- `container_action.php` and broker `/container` both enforce the same container + action allow-lists.
- MariaDB slow-query logging at 2 s threshold.
- Nightly encrypted backups to Backblaze B2 via restic.

**Remaining caveats** (see `docs/project-status.md §5.6` for the full open-audit list)

- `docker/database/users_grants.sql` is committed to git and contains MySQL native-password hashes — purge with `git filter-repo` if you ever rotate the affected users. Filed as **F-004**.
- PHP 7.4 is end-of-life; migration to 8.2/8.3 is a separate project. Filed as **F-032**.
- `sites/php.bitco.space/site.json` still carries the plaintext DB password from the original opc.bitco.link era (committed in git history). New sites created via the admin UI no longer add to this leak — `add_site.php` surfaces the password once in the response and never writes it to disk. Filed as **#37**.
- SFTP container still allows password auth (its sshd is separate from the host's hardened sshd); fail2ban protects it but rotating to key-based access would be stronger. **F-005 partial.**

---

## 8. Operations

### Quick start — fresh deployment

```bash
git clone https://github.com/bitcodss/phpserver.git
cd phpserver

# 1. Bootstrap the admin host site from the template
scripts/new-site.sh --domain <your.domain>

# 2. Set host secrets
cp docker/.env.example docker/.env       # fill MYSQL_*, ADMIN_PASS_HASH, SFTP_*, etc.
                                         # plus SITE_PUBLIC_IP, PMA_PUBLIC_URL,
                                         # NGINX_BIND, PMA_BIND if non-default

# 3. Bring up the stack
cd docker && docker compose up -d --build

# 4. (optional, first cluster bring-up only) import seed dumps
docker exec -i cid-mariadb mysql -u root -p"$MYSQL_ROOT_PASSWORD" < database/all_databases.sql
docker exec -i cid-mariadb mysql -u root -p"$MYSQL_ROOT_PASSWORD" < database/users_grants.sql

# 5. Wire up host-level frontend (Cloudflare Tunnel OR Caddy) to localhost:9080
# 6. Install the host crons (see below)
```

### Host crons

```cron
# Metrics collector (writes to active site's admin/data/metrics.json every 5 min)
*/5 * * * *  /home/bitcodata/phpserver/scripts/collect_metrics.sh

# Backup engine tick (broker evaluates schedules + fires due jobs)
*/5 * * * *  /usr/local/bin/cid-backup-tick
```

### Log locations

| Source | Path |
|---|---|
| Nginx access / error | `docker/logs/nginx/` |
| MariaDB slow query | `docker/logs/mariadb/slow.log` |
| PHP / FPM errors | container stderr → `docker logs cid-php74` |

### Backups

Day-to-day backups are driven by `cid-broker`'s Backup v2 engine (see §6). The host cron `cid-backup-tick` runs every 5 min; the broker fires due jobs.

Setup walkthrough (B2 sign-up, restic passphrase, restore commands, drill procedure): `scripts/cid-backup-setup.md`.

The seed dumps committed at `docker/database/all_databases.sql` and `docker/database/users_grants.sql` are for **initial cluster bootstrap only** — they're how a fresh `docker compose up -d` finds the schema. Live backups are encrypted client-side and rotate on the 7d/4w/6m retention policy in B2.

---

## 9. Conventions for Future Development

- **Site root**: always `/var/www/sites/<domain>/public/`. The whole `sites/` tree is bind-mounted, so editing on the host is editing in the container.
- **Per-site metadata**: `site.json` at the site root. Read/written by `admin/api/site_config.php`. **Do not put secrets in `site.json`** — it's a tracked file. Passwords flow via env (compose) and DB user creation (broker).
- **Adding a site**:
  - First site of a fresh deployment → `scripts/new-site.sh --domain <D>` (the admin UI doesn't exist yet).
  - Subsequent sites on a running stack → admin UI "+ Add New Site" → `admin/api/add_site.php` → broker `POST /site/create`.
  - Both paths consume `sites/_template/` + `docker/nginx/conf.d/_template.conf.example`. **Don't hand-edit generated vhosts** — the next API call may clobber them. If a vhost needs a one-off tweak, copy it under a new name and rewire the nginx config there.
- **Runtime configuration**: prefer `setting($key, $default)` in `_lib.php` over `getenv()` for values an operator might want to flip at runtime — it gives a free DB-override layer via the Settings page. Add new keys to the whitelist in `save_settings.php` + the form in `templates/settings.php`.
- **PHP setting changes**: go through `admin/api/save_php.php` so the FPM container is restarted; ad-hoc edits to `php-custom.ini` won't take effect until the next restart.
- **FPM tuning**: similarly, route through `admin/api/save_fpm.php`.
- **Container ops**: all containers are prefixed `cid-`. Anything new should follow the same prefix and be added to the allow-list in `admin/api/container_action.php` + the broker's `ALLOWED_CONTAINERS` set if it should be controllable from the dashboard.
- **Timezone**: every service is `Asia/Bangkok`. Match that in any new container.
- **No host port for MariaDB/Redis**: keep it that way. Talk to them by service name (`mariadb`, `redis`) over `cid-network`. nginx + phpMyAdmin are published but bind-controllable via `NGINX_BIND` / `PMA_BIND`.
- **TLS lives at the frontend** (Cloudflare Tunnel on home, Caddy on cloud-deploy). Application containers should not terminate HTTPS themselves.
- **Privileged ops route through `cid-broker`.** Don't add `shell_exec("docker …")` back into `cid-php74`; don't add `mkdir`/`file_put_contents` against `/var/www/sites` or `/etc/nginx/conf.d` from PHP either (www-data lacks group access on both bind mounts — see ticket #36 for the lesson). Add a new broker route instead. The broker container is the only one that should hold `/var/run/docker.sock`, the only writer to `nginx/conf.d/`, and the only privileged writer to the site folder tree.
- **Secrets stay in env.** `docker/.env` (gitignored) is the source of truth; reference values from compose with `${VAR}` and from PHP with `getenv()` (or `setting()` for operator-overridable values). Never paste a credential into a `.php`, `.conf`, or template file. New DB user passwords are surfaced once in the API response and not persisted.
- **Generated nginx vhosts** inherit `if ($block_all) { return 403; }` from `_template.conf.example`. If you hand-write a vhost, include that line below `limit_req`.
