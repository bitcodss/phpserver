# Changelog

Notable shipped work, newest first. Detail for each phase lives at `docs/archive/`.
Open work and operational state live at [`docs/project-status.md`](docs/project-status.md).

Format: one bullet per shipped phase. `commit-hash` is on `origin/main`.

---

## 2026-05-17 · Pause + project status doc

- **Commit:** `22b77cc` — `docs: project status and resume guide for future work`
- Pause point captured at [`docs/project-status.md`](docs/project-status.md). No code changes.
- DR runbook drafted at `docs/disaster-recovery.md` but **uncommitted** — scope assumes cloud-VPS rebuild, actual deployment is home-server + Cloudflare Tunnel. Blocked on §6.1 decision.

## 2026-05-16 · Phase 0.2.1 — config backup gap closure

- **Commit:** `fa336c4` — `phase 0.2.1: close config backup gaps + scope clarification`
- Added to `CONFIG_BACKUP_PATHS`: fail2ban, authorized_keys, user-crontabs, docker-daemon. Removed `/var/lib/caddy/` (Caddy runs in separate `openclaw-caddy-1`).
- Top-level README files (`RESTORE-NOTES.txt`, `user-crontab-README.txt`, `dpkg-selections.txt`) staged via two `-C` anchors so they land at archive root.

## 2026-05-16 · Phase 0.2 — config backup system job

- **Commit:** `dafd0b7` — `backup phase 0.2: config backup system job`
- New system job `__system_config_backup__` — tarball of host config files + `dpkg --get-selections`, daily 01:30, 90-day retention.
- Broker Dockerfile: `apk add tar dpkg`. Three `/host-config/...` bind mounts in compose.

## 2026-05-16 · Phase 0.1.5 — broker utf8mb4 default

- **Commit:** `2d958f2` — `broker: default to utf8mb4 charset for /mysql and /mysql-query`
- Fix for emoji insert failing during Phase 0.1 drill (`Incorrect string value: '\xF0\x9F\x8E\x89'`). `mariadb -e` defaulted to 3-byte utf8; now invoked with `--default-character-set=utf8mb4`.
- Phase 0.1 acceptance drill 9/9 byte-equal checks (Thai / emoji / CJK / NULL / JSON / large TEXT / FK / SQL-escape). Drill timings recorded in `admin/data/drill-history.json` (gitignored).

## 2026-05-15 · Backup v2 UI v2.2 — polish

- **Commit:** `4ba6140` — `backup ui v2.2: showToast bug fix + 5 polish items`
- Wrapped initial `refreshAll(false)` in `DOMContentLoaded` listener with `typeof showToast === 'function'` guard — fixes ReferenceError when template scripts ran before global `<script>` was parsed.
- Action button nowrap, kind disabled on Edit, 10/page pagination, hidden empty sections.

## 2026-05-15 · Backup v2 UI v2.1 — cache + per-job snapshots

- **Commit:** `2888c28` — `backup ui v2.1: cache + per-job snapshots + sections + polish`
- localStorage cache `cid-backup-cache-v1` with 10-min TTL.
- Snapshots nested inside Jobs (per user feedback "snapshots ควรอยู่ด้านใน Jobs").

## 2026-05-15 · Backup v2 — job engine + admin UI

- **Commit:** `67c5aec` — `backup v2: job engine + admin UI (restic via broker, daily DB jobs)`
- Plan: [`docs/archive/backup-v2-plan.md`](docs/archive/backup-v2-plan.md).
- 9 broker backup routes, BackupLock (fcntl), schedule parser, validators, `_set_restore_perms(writable_by_php)`, BACKUP_NOTIFY_URL POST, tick janitor, idempotent system-job seeding via `seeded_dbs` marker.
- Admin UI: `admin/templates/backup.php` (jobs + snapshots + add/edit + runs history + restore + download + forget).
- Restore staging at `/var/cid-restores/` (0750 root:www-data via `PHP_GID` env).

## 2026-05-15 · Backup Phase 3 — restic → Backblaze B2

- **Commit:** `3dec623` — `backup: nightly restic → Backblaze B2 + updated spec`
- Monolithic `cid-backup.sh` (now superseded by Backup v2; deprecation banner present).
- Cron `*/5 * * * *` calling `cid-backup-tick`.

## 2026-05-15 · Security Phase 2 — fail2ban + SSH hardening

- **Commit:** `4bb1da4` — `security phase 2: fail2ban (4 jails) + SSH hardening`
- 4 jails: `sshd`, `sftp` (journald `CONTAINER_NAME=cid-sftp`), `nginx-admin`, `nginx-8g`. LAN ranges in `ignoreip`.
- SSH drop-in `/etc/ssh/sshd_config.d/99-hardening.conf`: `PasswordAuthentication no`, no root login, modern crypto baseline.
- Host artifacts not in repo — captured by Phase 0.2 config backup.

## 2026-05-14 · Security Phase 1 — audit fixes + infra hardening + 8G WAF

- **Commit:** `e777c5d` — `security: audit fixes + infra hardening + 8G firewall (phase 1)`
- Plan: [`docs/archive/audit-plan.md`](docs/archive/audit-plan.md). Findings: [`docs/archive/audit-findings.md`](docs/archive/audit-findings.md). Infra: [`docs/archive/infra-hardening-plan.md`](docs/archive/infra-hardening-plan.md).
- Closed 24 of 33 audit findings — credentials moved to env, bcrypt + CSRF + session_regenerate on admin login, SQLi parameterization, mass-assignment allow-lists, etc.
- Closed F-006 / F-007 / F-023 — moved Docker socket out of `cid-php74` into new `cid-broker` Python+Flask sidecar; FPM migrated to unix socket `/run/php-fpm/www.sock`.
- 8G WAF (`docker/nginx/conf.d/_8g.conf`) on all per-site vhosts; admin returns HTTP 401 on bad creds.
- Residual open findings: see [`docs/project-status.md`](docs/project-status.md) §5.6.

## 2026-04-22 · Initial commit

- **Commit:** `8fffd11` — `Initial commit: PHP 7.4 Docker stack with Admin Dashboard`
