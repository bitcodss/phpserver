# phpserver — Project Specification

Reference document for development work on this repository. The README is the quick-start; this file is the long-form companion that covers stack, layout, features, configuration, and conventions.

---

## 1. Overview

A Docker-based PHP 7.4 hosting stack run locally and exposed to the public internet behind a **host-level Caddy** reverse proxy (which terminates TLS and routes by domain). All application containers listen on `localhost`-bound ports only — Caddy is the single ingress for HTTPS.

The stack hosts two PHP sites today:

| Domain | Purpose |
|---|---|
| `opc.bitco.link` | **Admin dashboard** (ศ.Cid) — manage containers, sites, PHP/FPM settings, databases, users, logs, security audit. |
| `opc2.bitco.link` | **Survey application** (Cressida 2026, Nikkei Research) — Thai-language multi-step questionnaire backed by MariaDB `dw_cressida`. |

Container names are all prefixed `cid-` and share a single bridge network (`cid-network`). Timezone everywhere is `Asia/Bangkok`.

---

## 2. Technology Stack

### Services (`docker/docker-compose.yml`)

| Service | Container | Image | Host port (localhost) | Internal | Role |
|---|---|---|---|---|---|
| php74 | `cid-php74` | custom build from `php:7.4-fpm-bullseye` | — | FPM on `/run/php-fpm/www.sock` (unix) | PHP-FPM runtime |
| nginx | `cid-nginx` | `nginx:1.24-alpine` | **9080** | 80 | Reverse proxy / web server |
| mariadb | `cid-mariadb` | `mariadb:10.11` | — (internal only) | 3306 | Primary DB |
| phpmyadmin | `cid-phpmyadmin` | `phpmyadmin:5.2` | **9081** | 80 | DB UI |
| redis | `cid-redis` | `redis:7-alpine` | — (internal only) | 6379 | Sessions / cache (64 MB, LRU) |
| **broker** | `cid-broker` | custom build from `python:3.12-alpine` (Flask + gunicorn) | — | listens on `/run/broker/broker.sock` (unix) | Privileged-ops sidecar — holds `/var/run/docker.sock`, exposes a tightly scoped HTTP API to cid-php74 |
| sftp | `cid-sftp` | `lscr.io/linuxserver/openssh-server` | **2222** (public) | 2222 | File transfer (`webmaster` user) |

Public ingress is via host-level **Caddy** (not in compose) → auto-SSL → `localhost:9080` (nginx).

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
- Routes: `GET /healthz`, `POST /container/status` (allow-listed names; read-only inspect), `POST /container` (start/stop/restart, narrower allow-list), `POST /mysql` and `POST /mysql-query` (run SQL as MariaDB root, password from env), `POST /logs` (docker logs --tail with stdout/stderr split), `POST /nginx/reload`, `POST /caddy/route` (POST to host Caddy admin via `host.docker.internal:2019`).

### Volumes & network

- Named volumes: `mariadb-data`, `redis-data`.
- Tmpfs volumes: `fpm-socket` (shared by php74 ↔ nginx, holds `www.sock`), `broker-socket` (shared by broker ↔ php74, holds `broker.sock`).
- Bind mounts: `../sites → /var/www/sites`, nginx & php config dirs, `docker/logs/{nginx,mariadb}`. **php74 no longer mounts `/var/run/docker.sock`** — only `cid-broker` does.
- Network: bridge `cid-network`. Broker has `host.docker.internal:host-gateway` extra-host so it can reach the host's Caddy admin without `--network=host`.

---

## 3. Directory Structure

```
/home/bitcodata/phpserver/
├── README.md
├── spec.md                          # this file
├── .gitignore
│
├── docker/
│   ├── docker-compose.yml
│   ├── .env.example                 # full env template incl. admin/survey/SFTP/broker secrets
│   ├── .env                         # local, gitignored
│   ├── php/
│   │   ├── Dockerfile
│   │   └── conf/
│   │       ├── php-custom.ini       # mounted at /usr/local/etc/php/conf.d/99-custom.ini
│   │       └── www.conf             # mounted at /usr/local/etc/php-fpm.d/www.conf
│   ├── broker/                      # privileged-ops sidecar (Python+Flask)
│   │   ├── Dockerfile
│   │   ├── app.py
│   │   └── requirements.txt
│   ├── nginx/
│   │   ├── nginx.conf
│   │   └── conf.d/
│   │       ├── _8g.conf             # 8G firewall detection maps (Phase 1 security)
│   │       ├── opc.bitco.link.conf
│   │       └── opc2.bitco.link.conf
│   ├── database/
│   │   ├── all_databases.sql        # full dump (import on first boot)
│   │   └── users_grants.sql         # MySQL user permissions
│   └── logs/
│       ├── nginx/                   # access.log, error.log (per site)
│       └── mariadb/                 # slow.log
│
├── scripts/
│   ├── collect_metrics.sh           # cron → admin/data/metrics.json
│   ├── cid-backup.sh                # nightly restic backup (installed to /usr/local/bin/cid-backup)
│   ├── cid-backup.env.example       # template for /etc/cid-backup.env
│   └── cid-backup-setup.md          # B2 sign-up + ops doc
│
└── sites/
    ├── _config/
    │   └── nginx/
    │       ├── opc2.bitco.link.conf         # site template (nginx)
    │       └── opc2.bitco.link.caddy.json   # site template (Caddy route)
    │
    ├── opc.bitco.link/public/
    │   ├── index.php                # landing
    │   └── admin/
    │       ├── index.php            # login + sidebar + page router (bcrypt + CSRF + session regen)
    │       ├── _lib.php             # shared brokerCall() / mysqlExec() / mysqlQuery() helpers
    │       ├── data/
    │       │   └── metrics.json     # rolling 7d server metrics (web-denied; served via api/metrics.php)
    │       ├── api/
    │       │   ├── _bootstrap.php   # session auth + CSRF gate, required by all endpoints
    │       │   ├── server_info.php
    │       │   ├── metrics.php      # auth-gated JSON proxy to data/metrics.json
    │       │   ├── add_site.php
    │       │   ├── save_php.php
    │       │   ├── save_fpm.php
    │       │   ├── create_db.php
    │       │   ├── drop_db.php
    │       │   ├── manage_user.php
    │       │   ├── container_action.php
    │       │   └── site_config.php
    │       └── templates/
    │           ├── dashboard.php
    │           ├── sites.php
    │           ├── php.php
    │           ├── database.php
    │           ├── security.php
    │           └── logs.php
    │
    └── opc2.bitco.link/public/
        ├── index.php                # STEP-based survey controller
        ├── routing1.php             # branching/skip logic
        ├── task1.php                # task / question type defs
        ├── label1.php               # Thai labels (~105 KB)
        ├── metadata1.php            # question metadata
        ├── checkval.php             # input validation
        ├── insertcase.php           # persist responses
        ├── phpfunc.php              # shared helpers
        ├── status.php
        ├── redirect.php
        ├── id.html, idauthen.html   # respondent ID entry
        ├── theme.html, event.html, 404.html
        ├── favicon.ico
        ├── api/
        │   ├── config.php           # PDO connection (dw_cressida)
        │   ├── apifunc.php
        │   ├── class.email.php
        │   └── fwprogress/index.php
        ├── xls/                     # bundled PHPExcel + SFTP libs, plus
        │   ├── exportxls.php, pivot.php, option.php
        │   └── Classes/PHPExcel/...
        ├── spss/                    # SPSS-format outputs
        ├── css/, js/, asset/
        └── .htaccess
```

Site root convention inside the php74 container: **`/var/www/sites/<domain>/public/`** (mapped from `./sites/<domain>/public/` on the host).

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

# Survey app (opc2)
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
```

MariaDB command flags (in compose): `utf8mb4 / utf8mb4_unicode_ci`, `innodb-buffer-pool-size=256M`, `max-connections=100`, slow-query log at `> 2s` to `/var/log/mysql/slow.log`.

---

## 5. Admin Dashboard — `opc.bitco.link/admin/`

### Authentication

- Session-based. User + bcrypt hash come from container env (`ADMIN_USER`, `ADMIN_PASS_HASH`); nothing is hardcoded.
- Login form POSTs to `/admin/`. Credential check uses `hash_equals` + `password_verify`. On success: `session_regenerate_id(true)`, set `$_SESSION['authenticated']`, mint a CSRF token, redirect.
- **Failed login returns HTTP 401** — signal consumed by the fail2ban `nginx-admin` jail on the host.
- Logout: unset `$_SESSION`, clear the session cookie with the correct flags, `session_destroy()`, redirect.
- CSRF token issued at login lives in `$_SESSION['csrf']`; every state-changing API POST must echo it back in `X-CSRF-Token` (enforced by `admin/api/_bootstrap.php`).

### Layout

- Sidebar (dark `#0f172a` background, accent `#38bdf8`) with six nav links and a logout footer. Main pane is included from `templates/<page>.php` based on `?page=…`. Default page is `dashboard`.
- All write actions are AJAX `POST` to `/admin/api/<endpoint>.php` with JSON bodies via the `apiCall()` helper in `index.php`, which automatically attaches `X-CSRF-Token`. Results surface via a `showToast()` notification.

### Modules

| Page | Template | What it does |
|---|---|---|
| Dashboard | `templates/dashboard.php` | Container status for all six services, CPU/mem/disk/uptime, site & DB counts, 7-day historical metrics from `data/metrics.json`. |
| Sites | `templates/sites.php` | Scans `/var/www/sites/*`, reads each site's `site.json`, shows domain / SSL / DB / created. Provides "Add new site" modal. |
| PHP Settings | `templates/php.php` | Live `ini_get_all()` dump. Editable groups: Core (memory, execution, uploads, errors), Session (handler, cookie flags), OPcache (enable, memory, files), Security (expose_php, url include, disable_functions). Lists loaded extensions. |
| Database | `templates/database.php` | Tabs for **Databases** (table count, size MB, collation), **Users** (MySQL users + grants), **Status** (uptime, threads, queries, slow queries). Hides system schemas (`information_schema`, `mysql`, `performance_schema`, `sys`). |
| Security | `templates/security.php` | 10-check audit → percentage score. Checks: `expose_php` off, `display_errors` off, `allow_url_include` off, `disable_functions` populated, `open_basedir` set, session cookie flags, Docker network isolation, nginx rate limiting present, four security headers present. |
| Logs | `templates/logs.php` | Tails nginx access/error per domain, PHP error log (FPM stderr), MariaDB slow log. |

### Admin API (`admin/api/`)

All endpoints require an authenticated session. POST = JSON in / JSON out unless noted.

| Endpoint | Method | Purpose / side effects |
|---|---|---|
| `server_info.php` | GET | JSON: IP (dynamic via `$_SERVER['SERVER_ADDR']`), hostname, PHP version, current time. |
| `metrics.php` | GET | Auth-gated proxy that streams `data/metrics.json` to the dashboard JS. The raw path is denied at nginx; only this endpoint exposes it. |
| `add_site.php` | POST | Provisions a new site end-to-end: creates `sites/<domain>/public` (a static `index.html`, not interpolated PHP), writes nginx vhost into `docker/nginx/conf.d/`, optionally creates DB + MySQL user (passwords validated against a strict regex). Caddy route POST goes through broker `/caddy/route`. |
| `save_php.php` | POST | Per-key validator allow-list; values rejected if they contain `\r\n;[]`. Writes to the rw mount at `/usr/local/etc/php-conf/php-custom.ini`. Restart via broker `/container`. |
| `save_fpm.php` | POST | Validated FPM directives written to `/usr/local/etc/php-conf/www.conf`. Restart via broker `/container`. |
| `create_db.php` | POST | `CREATE DATABASE … utf8mb4_unicode_ci`. Optional `CREATE USER` + grants — passwords must match `[A-Za-z0-9!@#%^&*()_+=\-]{8,64}` to prevent SQL injection via the password value. |
| `drop_db.php` | POST | Requires `confirm` field to match the DB name exactly. System schemas (`mysql`, `information_schema`, `performance_schema`, `sys`) blocked. |
| `manage_user.php` | POST | MySQL user CRUD. `safeUser` strips non-alphanumeric, `safeHost` allow-lists `localhost`/`127.0.0.1`/`::1`/`%`, `safePass` enforces the password regex. |
| `container_action.php` | POST | Calls broker `/container` with name+action both allow-listed. Containers: `cid-php74`, `cid-nginx`, `cid-mariadb`, `cid-phpmyadmin`, `cid-redis`. Actions: `start`, `stop`, `restart`. |
| `site_config.php` | POST | Read & write a per-site `site.json` metadata file. Domain regex-validated. |

---

## 6. Survey Application — `opc2.bitco.link/`

### Purpose

Multi-step questionnaire for **Cressida 2026**, an alcohol-beverage market research study by Nikkei Research. UI is Thai. Quotas span gender, SES, age, location, and drinking habits.

### Database

- Engine: MariaDB `dw_cressida`. All credentials come from container env (`OPC2_DB_HOST`, `OPC2_DB_NAME`, `OPC2_DB_USER`, `OPC2_DB_PASSWORD`) — `api/config.php` reads them via `getenv()`. No secrets in source.
- Connection: PDO, host `mariadb` (service name on `cid-network`).
- Main table: `survey_main`. Other support tables hold labels, routing rules, and quotas.
- All survey UPDATEs are parameterized; the column-name allow-list is derived at runtime from `INFORMATION_SCHEMA.COLUMNS` plus an explicit deny-list of server-managed columns (`status`, `idqr`, `enddate`, `lastq`, `hist`, etc.).

### File responsibilities

| File | Role |
|---|---|
| `index.php` | Top-level session controller. Reads `STEP` from session / query, dispatches to the right question or section. |
| `routing1.php` | Branching/skip logic (~52 KB). Decides next STEP based on prior answers. |
| `task1.php` | Definitions of question types and tasks (~11 KB). |
| `metadata1.php` | Question metadata (~17 KB). |
| `label1.php` | Thai-language label/text strings (~105 KB). |
| `checkval.php` | Input validation. |
| `insertcase.php` | Persists a respondent's answers to the DB (~42 KB). |
| `phpfunc.php` | Shared helper library (~62 KB). |
| `status.php` | Run-time status / progress indicator. |
| `id.html`, `idauthen.html` | Respondent ID entry and authentication landing. |
| `theme.html`, `event.html`, `404.html` | Static templates. |
| `api/apifunc.php` | API helpers. |
| `api/class.email.php` | Email notifications. |
| `api/fwprogress/index.php` | Progress-tracking endpoint. |
| `xls/` | Bundled **PHPExcel** plus `exportxls.php`, `pivot.php`, `option.php`, and an SFTP library — used for Excel export and uploading. |
| `spss/` | SPSS-format export outputs. |
| `css/`, `js/`, `asset/` | Front-end resources. |

---

## 7. Scripts & host services

### `scripts/collect_metrics.sh`

- Designed to be invoked by cron every 5 minutes on the host.
- Collects: CPU load (1m), CPU cores, total/used memory, total/used disk, uptime.
- Writes JSON entry to `sites/opc.bitco.link/public/admin/data/metrics.json`, trimmed to a rolling window of **2016 entries** (= 7 days × 24 h × 12 samples/h).
- Path is derived from `$SCRIPT_DIR/../sites/…` so it works regardless of where the repo lives.
- The Dashboard JS fetches `/admin/api/metrics.php` (auth-gated), not the raw file.

### `scripts/cid-backup.sh` (+ `cid-backup.env.example`, `cid-backup-setup.md`)

- Installed on the host as `/usr/local/bin/cid-backup`.
- Nightly cron at 02:00 Asia/Bangkok (`/etc/cron.d/cid-backup`).
- **What's backed up:** mysqldump of all DBs (streamed via `docker exec cid-mariadb`, never hits disk), `sites/`, and `docker/.env`.
- **Where:** Backblaze B2 by default (`b2:<bucket>:cid`); the script accepts any restic backend via `RESTIC_REPOSITORY` (S3, R2, local dir, etc.).
- **Encryption:** restic encrypts everything client-side with the passphrase from `/etc/cid-backup.env`. Lose the passphrase → backups are unreadable.
- **Retention:** 7 daily / 4 weekly / 6 monthly; pruned via `restic forget --prune`.
- **Integrity:** quick `restic check` every run, full 10% data-subset check on Sundays.
- Logs: `/var/log/cid-backup.log`, rotated weekly by `/etc/logrotate.d/cid-backup`.
- Restore: `restic restore latest --tag db|files --target /tmp/restore`. Full walkthrough in `scripts/cid-backup-setup.md`.

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

## 8. Security Posture

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

**Remaining caveats**

- `docker/database/users_grants.sql` is committed to git and contains MySQL native-password hashes — purge with `git filter-repo` if you ever rotate the affected users.
- PHP 7.4 is end-of-life; migration to 8.x is a separate project.
- Bundled PHPExcel in `opc2/xls/` is deprecated (replaced by PhpSpreadsheet in 2017).
- SFTP container still allows password auth (its sshd is separate from the host's hardened sshd); fail2ban protects it but rotating to key-based access would be stronger.

---

## 9. Operations

### Quick start (see README for full version)

```bash
cp docker/.env.example docker/.env       # edit passwords
cd docker && docker compose up -d --build
# optional: import dumps
docker exec -i cid-mariadb mysql -u root -p"$MYSQL_ROOT_PASSWORD" < database/all_databases.sql
docker exec -i cid-mariadb mysql -u root -p"$MYSQL_ROOT_PASSWORD" < database/users_grants.sql
```

### Metrics cron (host)

```cron
*/5 * * * *  /home/bitcodata/phpserver/scripts/collect_metrics.sh
```

### Log locations

| Source | Path |
|---|---|
| Nginx access / error | `docker/logs/nginx/` |
| MariaDB slow query | `docker/logs/mariadb/slow.log` |
| PHP / FPM errors | container stderr → `docker logs cid-php74` |

### Backups

Automated nightly via `/usr/local/bin/cid-backup` → restic → Backblaze B2. See
`scripts/cid-backup-setup.md` for B2 sign-up, configuration, restore commands,
and disaster-recovery walkthrough.

The seed dumps committed at `docker/database/all_databases.sql` and
`docker/database/users_grants.sql` are for **initial cluster bootstrap only**.
Day-to-day backups live in B2 (encrypted client-side) and rotate on the
7d/4w/6m retention policy.

---

## 10. Conventions for Future Development

- **Site root**: always `/var/www/sites/<domain>/public/`. The whole `sites/` tree is bind-mounted, so editing on the host is editing in the container.
- **Per-site metadata**: `site.json` at the site root. Read/written by `admin/api/site_config.php`.
- **Adding a site**: use `admin/api/add_site.php`. It templates both the nginx vhost and the Caddy route — don't hand-edit them, or the next API call may clobber your changes.
- **PHP setting changes**: go through `admin/api/save_php.php` so the FPM container is restarted; ad-hoc edits to `php-custom.ini` won't take effect until the next restart.
- **FPM tuning**: similarly, route through `admin/api/save_fpm.php`.
- **Container ops**: all containers are prefixed `cid-`. Anything new should follow the same prefix and be added to the allow-list in `admin/api/container_action.php` if it should be controllable from the dashboard.
- **Timezone**: every service is `Asia/Bangkok`. Match that in any new container.
- **No host port for MariaDB/Redis**: keep it that way. Talk to them by service name (`mariadb`, `redis`) over `cid-network`.
- **Caddy is the only TLS endpoint.** Application containers should not try to terminate HTTPS themselves.
- **Privileged ops route through `cid-broker`.** Don't add `shell_exec("docker …")` back into `cid-php74`; add a new broker route instead. The broker container is the only one that should hold `/var/run/docker.sock`.
- **Secrets stay in env.** `docker/.env` (gitignored) is the source of truth; reference values from compose with `${VAR}` and from PHP with `getenv()`. Never paste a credential into a `.php`, `.conf`, or template file.
- **Generated nginx vhosts** (via `admin/api/add_site.php`) inherit `if ($block_all) { return 403; }` automatically. If you hand-write a vhost, include that line below `limit_req`.
