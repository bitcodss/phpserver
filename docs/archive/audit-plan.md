# Code Review & Audit Plan

Pre-feature-work audit of the phpserver repository. Goal: find correctness, security, and operational bugs *before* adding more surface area.

---

## 1. Goals

- Catalogue every meaningful bug — correctness, security, or operational — across the stack.
- Triage by severity so the user can decide what to fix before new features land.
- Produce one consolidated deliverable: **`audit-findings.md`** at the repo root, one entry per finding.
- **No fixes during the audit.** Collect first, agree on what to fix, then change code.

## 2. Methodology

For each file/area:

1. **Read it** end-to-end (no skimming for risky files).
2. **Grep** the whole repo for dangerous patterns (see §5).
3. **Trace** untrusted input (request → handler → sinks: SQL, shell, filesystem, HTML output, headers).
4. **Cross-check** against the relevant config (e.g. `disable_functions`, `open_basedir`, allow-lists).
5. **Note assumptions** that may not hold (e.g. "session check is at top of file" — verify on every endpoint).

No automated SAST in this pass — manual review only. If time permits at the end, run `phpstan` / `psalm` as a sanity sweep.

## 3. Severity scale

| Level | Meaning |
|---|---|
| **Critical** | Unauthenticated RCE, auth bypass, mass data exposure, destructive ops by an attacker. Fix before any new feature. |
| **High** | Authenticated RCE/SQLi, privilege escalation between admin/non-admin, secret leakage. |
| **Medium** | Limited-impact injection, missing auth on a write endpoint, CSRF on state-changing actions, denial of service. |
| **Low** | Hardening gaps, info leaks, weak defaults. |
| **Info** | Style, dead code, EOL software, missing tests, opportunities. |

Each finding will include: **file:line · severity · title · what · impact · proof / repro idea · recommendation**.

## 4. Phases

### Phase 1 — Stack & build (≤ 30 min)

Files: `docker/docker-compose.yml`, `docker/php/Dockerfile`, `docker/php/conf/*`, `docker/nginx/nginx.conf`, `docker/nginx/conf.d/*`, `docker/.env*`, `.gitignore`.

Checks:
- Is `docker/.env` (with real secrets) committed or in `.gitignore`?
- Containers running as root vs unprivileged user.
- Image tags pinned (yes — `mariadb:10.11`, `redis:7-alpine`, `nginx:1.24-alpine`, `phpmyadmin:5.2`) — flag any `latest`.
- Docker socket bind-mounted into php74 — is the trust model justified? Anyone with code-exec in php74 can control the host's Docker daemon.
- `php-custom.ini`: `display_errors = On` in prod path. Confirm.
- `disable_functions` list — is it complete? `exec`, `shell_exec`, `system`, `popen`, `proc_open`, `eval` are *not* disabled. Document why (admin needs them) or recommend per-site overrides.
- `open_basedir` effectiveness — paths include `/proc` (information leak vector).
- Nginx rate-limit zones declared in `nginx.conf` but **verify they're applied** (`limit_req zone=login …`) on the admin login path. From memory of the per-site vhost: I don't recall a `limit_req` directive — confirm.
- Missing response headers worth adding at nginx (CSP, Permissions-Policy, HSTS if not handled by Caddy).
- `server_tokens off` ✓. `expose_php Off` ✓.
- SFTP container: `PASSWORD_ACCESS=true`, single shared `webmaster` user with password in compose file in repo. Confirm and flag.

### Phase 2 — Admin authentication & session (≤ 30 min)

File: `sites/opc.bitco.link/public/admin/index.php`.

Known issue (already spotted while writing `spec.md`): **line 10 builds a bcrypt hash but line 14 compares the submitted password to a plaintext literal `'Aptx4869&$'`.** The bcrypt construction is dead code. Effectively the password is hardcoded in source. → Critical.

Additional checks:
- `session_start()` configuration: cookie params (`Secure` flag — currently `0` in `php-custom.ini`; SameSite=Lax is fine). With Caddy doing TLS, cookie_secure should be 1.
- `session_regenerate_id()` after login? — almost certainly missing → session fixation. Medium.
- Login attempt throttling — nginx `login` zone (3 r/s) exists; verify it's wired to the admin path.
- Logout: `session_destroy()` only; doesn't unset `$_SESSION` or clear cookie. Low.
- Constant-time compare for credentials (`hash_equals`)? Currently `===`. Low-medium.
- The page router uses `?page=` and switches over a fixed list — confirm no path injection into `include`. (Looked OK in spec.md pass, but reverify.)

### Phase 3 — Admin API endpoints (≤ 2 hours, the biggest phase)

For **every** file in `sites/opc.bitco.link/public/admin/api/`:

A. **Auth gate.** Does it start with `session_start()` + check `$_SESSION['authenticated']`? Any endpoint missing this is a Critical.

B. **CSRF.** All admin POSTs are JSON via `fetch()` from same-origin pages. Are there any CSRF tokens, or does the code rely on `Content-Type: application/json` as a soft barrier? Likely no tokens → Medium (every state-changing endpoint).

C. **Input validation & sinks.** Per file:

| File | Sinks to inspect | Specific concerns |
|---|---|---|
| `add_site.php` | filesystem writes, `docker` shell, `CREATE USER`/`GRANT`, nginx config generation | Domain string → file paths (path traversal `../`), → nginx config (config injection), → SQL identifiers (cannot be parameterized, must be sanitized). MySQL user/host injection. Generated password entropy. |
| `container_action.php` | `docker <action> <container>` shell exec | Verify allow-list applied **before** shell-out, and that action is also whitelisted (start/stop/restart only, no `exec`, `cp`, `inspect --format`, etc.). |
| `save_php.php` | writes `php-custom.ini`, restarts `cid-php74` | Key/value coming from user → INI file. Newline injection (`\n` in value lets attacker add directives). Reject unknown keys. |
| `save_fpm.php` | writes `www.conf`, reloads FPM | Same INI-injection concerns as above. Also FPM lets you set `php_admin_value[disable_functions]` etc. — if writable, attacker can lift `disable_functions`. |
| `create_db.php` | `CREATE DATABASE`, optional `CREATE USER` + `GRANT` | DB name / user name cannot be bound — must use a strict regex. Password generation strength. |
| `drop_db.php` | `DROP DATABASE` | Same. "Confirm pattern" — verify it's actually compared, not just collected. |
| `manage_user.php` | `CREATE/DROP USER`, `GRANT`, `REVOKE` | Identifier validation. Host-part injection (e.g. `'%' WITH GRANT OPTION`). |
| `site_config.php` | reads/writes `site.json` per domain | Path traversal on domain. JSON parse errors. |
| `server_info.php` | read-only | Check it doesn't leak something it shouldn't (env vars, full filesystem paths). |

D. **Error handling.** Does any endpoint leak stack traces, raw SQL errors, or file paths to the response?

E. **Logging.** Are state-changing actions logged anywhere? Probably no → Info.

### Phase 4 — Survey application (≤ 1.5 hours)

Files under `sites/opc2.bitco.link/public/`. Large code (label1.php 105 KB, routing1.php 52 KB, insertcase.php 42 KB) — sample, then deep-dive on hot files.

Checks:

- **`api/config.php`** — credentials in source / in env? Switch logic between localhost & remote — does it leak credentials in error messages?
- **SQL injection sweep.** Grep for SQL built by string concatenation: `"SELECT … '".$_`, `$db->query("...$"`. PDO with `prepare()` & `execute()` should be the norm — flag every place it isn't, especially in `insertcase.php` and `routing1.php`.
- **XSS sweep.** Grep for `echo $_`, `print $_`, ` <?= $_` and any output of survey-state variables that came from user input.
- **Session handling.** STEP parameter — can a respondent skip steps, replay POSTs, or land on a later step without prior answers?
- **CSRF on survey forms.** Likely none. Risk is lower for survey, but note.
- **`checkval.php`** — is server-side validation actually enforced, or only client-side?
- **`xls/`** — bundled **PHPExcel** is end-of-life (replaced by PhpSpreadsheet in 2017). Known CVEs (e.g. CVE-2023-39745 area, XXE in older versions). Document if it parses any user-supplied spreadsheets.
- **File uploads.** Anywhere user files are accepted? Check destination, extension allow-list, MIME validation, randomized filenames.
- **Email** (`class.email.php`) — header injection on To/From/Subject if user-controlled.
- **SFTP code under `xls/sftp/`** — host/key handling, host-key verification on/off.

### Phase 5 — Cross-cutting sweeps (≤ 45 min)

Repo-wide greps to catch anything missed:

- Dangerous sinks: `\b(eval|assert|create_function|preg_replace.*\/e|unserialize|exec|shell_exec|system|popen|proc_open|passthru)\s*\(`
- Dynamic includes: `\b(include|require|include_once|require_once)\s*\(\s*\$`
- SQL building: `"\s*(SELECT|INSERT|UPDATE|DELETE|DROP).*\$_`
- Direct echo of user input: `echo\s+\$_(GET|POST|REQUEST|COOKIE|SERVER)`
- `@` error suppression — masks bugs.
- Hardcoded credentials: grep for `password`, `passwd`, `secret`, `api_key`, `token` outside `.env.example`.
- `TODO`, `FIXME`, `XXX`, `HACK` markers.
- Backup / scratch files in webroot (we already saw `index.php.bak.test` in `opc2.bitco.link/public/` — confirm it's not served).
- `composer.lock` / dependencies — none expected (no `composer.json` at root from spec.md exploration), confirm.
- PHP 7.4 is **EOL since 28 Nov 2022**. Document as a project-level finding; recommend a 8.2/8.3 migration plan as separate work.

### Phase 6 — Operations & infra (≤ 30 min)

- **Backups**: `docker/database/all_databases.sql` checked into git? If yes, it leaks every row + user grants → Critical (depending on what's in it). Verify.
- **Logs in repo**: `docker/logs/` is bind-mounted; confirm `.gitignore` covers it.
- **`metrics.json`** path under `public/admin/data/` — directly web-accessible? Should be denied by nginx or moved outside webroot.
- **Caddy config** lives on the host (out of repo) — note that this audit can't cover it; flag as a follow-up.
- **`cid-mariadb` root password** in `docker-compose.yml` default `CidMariaDB2026!`. Verify `.env` overrides it in prod.
- **Cron**: `collect_metrics.sh` — does it `set -e`? Does it sanitize before writing JSON? Race condition between concurrent runs?

## 5. Grep patterns (single-source list)

Run from repo root. Output into a scratch file per pattern.

```bash
# Dangerous PHP sinks
grep -rEn '\b(eval|assert|create_function|unserialize|exec|shell_exec|system|popen|proc_open|passthru)\s*\(' sites docker scripts

# Dynamic include of user input
grep -rEn '\b(include|require|include_once|require_once)\s*\(?\s*\$_' sites

# SQL string concatenation with superglobals
grep -rEn '"(SELECT|INSERT|UPDATE|DELETE|DROP)[^"]*\$_' sites

# Direct echo of user input (XSS)
grep -rEn 'echo[[:space:]]+\$_(GET|POST|REQUEST|COOKIE|SERVER)' sites
grep -rEn '<\?=[[:space:]]*\$_(GET|POST|REQUEST|COOKIE|SERVER)' sites

# Hardcoded creds
grep -rEniI '(password|passwd|secret|api[_-]?key|token)\s*[:=]\s*["'\'']' sites docker | grep -v example

# Error suppression
grep -rEn '@[a-zA-Z_]' sites | grep -v '@param\|@return\|@var\|@throws'

# Markers
grep -rEn '\b(TODO|FIXME|XXX|HACK)\b' sites docker scripts

# Auth gate presence in admin API
grep -L "SESSION\['authenticated'\]" sites/opc.bitco.link/public/admin/api/*.php
```

## 6. Seed findings (already suspected, to confirm in audit)

These will be the first entries in `audit-findings.md` once verified:

1. **Critical** — `admin/index.php:14` compares password to a plaintext literal; bcrypt hash on line 10 is unused. Hardcoded admin password in repo.
2. **High** — Docker socket mounted into `cid-php74`; any RCE in PHP code = full host control via `docker` CLI.
3. **High** — `display_errors = On` in `php-custom.ini` exposes stack traces to clients.
4. **High** — PHP 7.4 is EOL; no security patches since Nov 2022.
5. **High** — PHPExcel (bundled in `xls/`) is unmaintained; replaced by PhpSpreadsheet in 2017.
6. **Medium** — `session.cookie_secure = 0` while public access is via TLS (Caddy). Cookies could be sent over plaintext if anyone bypasses Caddy.
7. **Medium** — Likely no CSRF tokens on admin state-changing endpoints (to confirm in Phase 3).
8. **Medium** — `disable_functions` allows `exec`, `shell_exec`, `system`, `proc_open`, `eval` — necessary for admin, but no per-site lockdown for `opc2.bitco.link`.
9. **Low** — SFTP container ships with default password in `docker-compose.yml`; `PASSWORD_ACCESS=true`.
10. **Low** — `admin/data/metrics.json` lives under webroot; verify nginx denies it or move it out.
11. **Info** — `sites/opc2.bitco.link/public/index.php.bak.test` looks like a leftover backup in webroot; remove.

## 7. Deliverable

A single file at the repo root: **`audit-findings.md`**, structured as:

```markdown
## Findings

### F-001 · Critical · admin/index.php:14 · Hardcoded admin password / unused bcrypt hash
**What.** …
**Impact.** …
**Repro.** …
**Recommendation.** …

### F-002 · High · …
…
```

Plus a short executive summary at the top: counts by severity, and a recommended fix order.

## 8. Out of scope (for this pass)

- The host Caddy config (lives outside the repo).
- The MariaDB SQL dump contents (`docker/database/all_databases.sql`) — we'll note its presence/sensitivity but not review schema in detail.
- The bulk of survey label/text content in `label1.php` — content review, not code review.
- PHPExcel internals — we'll note its EOL status but not audit hundreds of library files.
- Performance profiling, load testing.
- Writing fixes. (Audit only — fixes are a follow-up engagement.)

## 9. Estimated time

~5 hours of focused review. Phase 3 (admin API) is the longest single chunk; the survey app (Phase 4) is large by line count but lower per-line risk than the admin API.

## 10. Next step

On approval of this plan, the next action is: start Phase 1, take notes inline, then write up phases incrementally into `audit-findings.md`. I'll pause at the end of Phase 3 for a checkpoint before continuing to the survey app.
