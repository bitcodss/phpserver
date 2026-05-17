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

| Service | Container | Image | Host port (localhost) | Internal port | Role |
|---|---|---|---|---|---|
| php74 | `cid-php74` | custom build from `php:7.4-fpm-bullseye` | — | 9000 | PHP-FPM runtime |
| nginx | `cid-nginx` | `nginx:1.24-alpine` | **9080** | 80 | Reverse proxy / web server |
| mariadb | `cid-mariadb` | `mariadb:10.11` | — (internal only) | 3306 | Primary DB |
| phpmyadmin | `cid-phpmyadmin` | `phpmyadmin:5.2` | **9081** | 80 | DB UI |
| redis | `cid-redis` | `redis:7-alpine` | — (internal only) | 6379 | Sessions / cache (64 MB, LRU) |
| sftp | `cid-sftp` | `lscr.io/linuxserver/openssh-server` | **2222** (public) | 2222 | File transfer (`webmaster` user) |

Public ingress is via host-level **Caddy** (not in compose) → auto-SSL → `localhost:9080` (nginx).

### PHP image (`docker/php/Dockerfile`)

- Base: `php:7.4-fpm-bullseye`
- Extensions: `gd` (freetype/jpeg/webp), `mysqli`, `pdo_mysql`, `zip`, `intl`, `mbstring`, `xml`, `curl`, `bcmath`, `opcache`, `exif`, `soap`, `pcntl`, plus **`redis 5.3.7`** via PECL.
- Tools: Composer, **Docker CLI 28.0.1** static binary (so admin code inside the container can drive `docker` against the mounted `/var/run/docker.sock`).
- `www-data` is added to a `dockerhost` group (GID 988) to access the socket.

### Volumes & network

- Named volumes: `mariadb-data`, `redis-data`.
- Bind mounts: `../sites → /var/www/sites`, nginx & php config dirs (read-only), `docker/logs/{nginx,mariadb}`, and `/var/run/docker.sock` into the php container.
- Network: bridge `cid-network`.

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
│   ├── .env.example                 # MYSQL_ROOT_PASSWORD / DATABASE / USER / PASSWORD
│   ├── .env                         # local, gitignored
│   ├── php/
│   │   ├── Dockerfile
│   │   └── conf/
│   │       ├── php-custom.ini       # mounted at /usr/local/etc/php/conf.d/99-custom.ini
│   │       └── www.conf             # mounted at /usr/local/etc/php-fpm.d/www.conf
│   ├── nginx/
│   │   ├── nginx.conf
│   │   └── conf.d/
│   │       ├── opc.bitco.link.conf
│   │       └── opc2.bitco.link.conf
│   ├── database/
│   │   ├── all_databases.sql        # full dump (import on first boot)
│   │   └── users_grants.sql         # MySQL user permissions
│   └── logs/
│       ├── nginx/                   # access.log, error.log
│       └── mariadb/                 # slow.log
│
├── scripts/
│   └── collect_metrics.sh           # cron → admin/data/metrics.json
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
    │       ├── index.php            # login + sidebar + page router
    │       ├── data/
    │       │   └── metrics.json     # rolling 7d server metrics
    │       ├── api/
    │       │   ├── server_info.php
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
| `display_errors` / `display_startup_errors` | **On** (dev mode, by request) |
| `error_reporting` | `E_ALL & ~E_DEPRECATED & ~E_STRICT` |
| `expose_php` | Off |
| `allow_url_fopen` / `allow_url_include` | On / **Off** |
| `disable_functions` | `passthru, parse_ini_file, show_source, dl` |
| `open_basedir` | `/var/www/sites:/tmp:/usr/share/php:/usr/local/bin:/proc` |
| `session.save_handler` / `save_path` | `redis` / `tcp://redis:6379` |
| `session.cookie_httponly` / `cookie_secure` / `samesite` / `use_strict_mode` | 1 / 0 / Lax / 1 |
| `date.timezone` | Asia/Bangkok |
| `opcache.memory_consumption` / `max_accelerated_files` / `revalidate_freq` | 128 / 10000 / 2 |
| `realpath_cache_size` / `_ttl` | 4096k / 600 |

### `docker/php/conf/www.conf` (FPM pool)

- User/group: `www-data`
- `pm = dynamic`, `pm.max_children = 20`, `start_servers = 4`, `min_spare = 2`, `max_spare = 8`, `pm.max_requests = 500`
- `request_slowlog_timeout = 5s`, status page at `/fpm-status` (internal)
- `display_errors = On`

### `docker/nginx/nginx.conf`

- `worker_processes auto`, `worker_connections 1024`
- `keepalive_timeout 65`, `client_max_body_size 64m`
- Gzip level 6 over text/css/json/js/xml
- Always-on security headers: `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `X-XSS-Protection: 1; mode=block`, `Referrer-Policy: strict-origin-when-cross-origin`
- `server_tokens off`
- Rate-limit zones: `general` 10r/s, `login` 3r/s (10 MB shared mem each)

### `docker/nginx/conf.d/*.conf` (per-site)

- FastCGI pass to `php74:9000`
- `try_files $uri $uri/ /index.php?$query_string` (SPA-friendly)
- Static files: 30-day immutable cache
- 60s FastCGI connect timeout, 300s read/write
- Deny access to dotfiles and `.env / .git / .ini / .log / .sql / .sh / .conf`
- `fastcgi_hide_header X-Powered-By` and per-site `open_basedir` reinforcement via `fastcgi_param PHP_VALUE`

### `docker/.env.example`

```
MYSQL_ROOT_PASSWORD=ChangeMe_RootPass!
MYSQL_DATABASE=opc_db
MYSQL_USER=opc_user
MYSQL_PASSWORD=ChangeMe_UserPass!
```

MariaDB command flags (in compose): `utf8mb4 / utf8mb4_unicode_ci`, `innodb-buffer-pool-size=256M`, `max-connections=100`, slow-query log at `> 2s` to `/var/log/mysql/slow.log`.

---

## 5. Admin Dashboard — `opc.bitco.link/admin/`

### Authentication

- Session-based. Single hardcoded user defined in `admin/index.php` (`admin` / `Aptx4869&$`).
- Login form posts to `/admin/`; success sets `$_SESSION['authenticated']` and redirects.
- Logout via `?logout=1` → `session_destroy()`.
- Note: although the file constructs a bcrypt hash, the actual credential check on line 14 compares the plaintext literal. See §8 Security Posture.

### Layout

- Sidebar (dark `#0f172a` background, accent `#38bdf8`) with six nav links and a logout footer. Main pane is included from `templates/<page>.php` based on `?page=…`. Default page is `dashboard`.
- All write actions are AJAX `POST` to `/admin/api/<endpoint>.php` with JSON bodies (`apiCall()` helper in `index.php`). Results surface via a `showToast()` notification.

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
| `server_info.php` | GET | JSON: IP, hostname, PHP version, current time. |
| `add_site.php` | POST | Provisions a new site end-to-end: creates `sites/<domain>/public`, writes nginx vhost into `docker/nginx/conf.d/`, writes Caddy route template into `sites/_config/nginx/`, optionally creates DB + MySQL user with a generated random password. Returns credentials in response. |
| `save_php.php` | POST | Patches `docker/php/conf/php-custom.ini` and restarts the `cid-php74` container. |
| `save_fpm.php` | POST | Patches `docker/php/conf/www.conf` and reloads FPM. |
| `create_db.php` | POST | `CREATE DATABASE … CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`. Optional `CREATE USER` + grants in the same call. |
| `drop_db.php` | POST | `DROP DATABASE` with a confirm-pattern guard. |
| `manage_user.php` | POST | MySQL user CRUD: create / delete / grant / revoke. |
| `container_action.php` | POST | `docker start|stop|restart <container>` against the mounted socket. **Allow-list** (do not widen without thought): `cid-php74`, `cid-nginx`, `cid-mariadb`, `cid-phpmyadmin`, `cid-redis`. |
| `site_config.php` | GET / POST | Read & write a per-site `site.json` metadata file. |

---

## 6. Survey Application — `opc2.bitco.link/`

### Purpose

Multi-step questionnaire for **Cressida 2026**, an alcohol-beverage market research study by Nikkei Research. UI is Thai. Quotas span gender, SES, age, location, and drinking habits.

### Database

- Engine: MariaDB `dw_cressida` (or whatever `$_CONFIG` in `api/config.php` resolves to).
- Connection: PDO, configured in `sites/opc2.bitco.link/public/api/config.php`. Switches between localhost and a remote host based on the request environment.
- Main table: `survey_main`. Other support tables hold labels, routing rules, and quotas.

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

## 7. Scripts

### `scripts/collect_metrics.sh`

- Designed to be invoked by cron every 5 minutes on the host.
- Collects: CPU load (1m), CPU cores, total/used memory, total/used disk, uptime.
- Writes JSON entry to `sites/opc.bitco.link/public/admin/data/metrics.json`, trimmed to a rolling window of **2016 entries** (= 7 days × 24 h × 12 samples/h).
- Uses Python for the JSON append/trim step.

The Dashboard module reads this file directly to render time-series and historical aggregates.

---

## 8. Security Posture

**In place**

- Bcrypt hashing helper used in admin code path; Redis-backed sessions with `HttpOnly` and `SameSite=Lax`, `use_strict_mode=1`.
- `open_basedir` restricts PHP filesystem access to `/var/www/sites:/tmp:/usr/share/php:/usr/local/bin:/proc`.
- Docker network isolation; only nginx (9080), phpMyAdmin (9081), and SFTP (2222) are published to the host. MariaDB and Redis are reachable only inside `cid-network`.
- Nginx rate limiting (`general` 10r/s, `login` 3r/s) and four always-on security response headers.
- Dangerous PHP functions disabled (`passthru`, `parse_ini_file`, `show_source`, `dl`).
- `expose_php = Off`, `server_tokens off`, `X-Powered-By` stripped at FastCGI.
- MariaDB slow-query logging at 2 s threshold.
- `container_action.php` uses a fixed allow-list of containers it will operate on.

**Caveats worth fixing before hardening for production**

- `display_errors = On` in `php-custom.ini` (kept on per request; flip off for prod).
- `admin/index.php` line 14 effectively stores the admin password as a plaintext literal — the bcrypt construction on line 10 is unused for verification.
- MariaDB root credentials are present in admin scripts that perform DB operations.
- Admin endpoints shell out to `docker` via the mounted socket; input is allow-listed but every endpoint that takes user input should be re-audited if any privilege boundary is added.
- SFTP password access is enabled by default (`PASSWORD_ACCESS=true`).

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

- DB dumps live at `docker/database/all_databases.sql` and `docker/database/users_grants.sql`. Refresh with `mysqldump` inside `cid-mariadb` before checkpointing.

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
