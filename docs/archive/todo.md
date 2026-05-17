# Security Hardening — Todo

Tasks for the RunCloud-equivalent security baseline. Plan: see this session's
`infra-hardening-plan.md` siblings; canonical reference at
`/home/bitcodata/.claude/plans/noble-strolling-wadler.md`.

Mark `[x]` once the task is done **and** committed. One commit per phase.

## Decisions (locked at kickoff)

- SSH hardening: full drop-in including `PasswordAuthentication no` — user confirmed keys deployed.
- IP allow-list for `/admin/`: no — admin remains reachable from anywhere; rely on auth + CSRF + fail2ban.
- Commit cadence: one commit per phase.

## Phase 0 — Setup

- [x] Save this checklist as `/home/bitcodata/phpserver/todo.md`.

## Phase 1 — Nginx-layer baseline (Tier 1, in-container)

- [x] Add `docker/nginx/conf.d/_8g.conf` (Perishable Press 8G ruleset, adapted).
- [x] Bad-bot UA map — merged into `_8g.conf`'s `$bad_bot` (separate file unnecessary).
- [x] Include 8G + bad-bot check in `docker/nginx/conf.d/opc.bitco.link.conf`.
- [x] Include 8G + bad-bot check in `docker/nginx/conf.d/opc2.bitco.link.conf`.
- [x] Update `sites/opc.bitco.link/public/admin/api/add_site.php` template to inherit the same rules.
- [x] `admin/index.php` — return 401 on bad credentials (signal for fail2ban).
- [x] `docker exec cid-nginx nginx -t && nginx -s reload`.
- [x] Verify: 16-case matrix — all bad UAs/queries/URIs/methods → 403; legit traffic + admin login → 200/302/401 as expected.
- [x] Commit phase 1 (`e78dd22` — bundled with prior session's audit/infra work).

## Phase 2 — Host-level protections (Tier 2)

- [x] Confirm key-based SSH works — verified from `/var/log/auth.log`: every recent login from 192.168.3.51 is `Accepted publickey` with ED25519 `oZv0mow5CUfEA…`.
- [x] `apt install fail2ban` (v1.0.2 installed).
- [x] Drop `/etc/fail2ban/jail.local` with the four jails (sshd, sftp, nginx-admin, nginx-8g).
- [x] Drop `/etc/fail2ban/filter.d/nginx-admin.conf` and `nginx-8g.conf`.
- [x] Add LAN ranges (127.0.0.1/8, ::1, 192.168.0.0/16, 10.0.0.0/8, 172.16.0.0/12) to `ignoreip`.
- [x] `systemctl enable --now fail2ban`. Confirmed `fail2ban-client status` shows 4 jails.
- [x] Trigger 6 fake 401s from 9.9.9.9 → nginx-admin banned 9.9.9.9 ✓.
- [x] Trigger 11 fake 403s from 4.4.4.4 → nginx-8g banned 4.4.4.4 ✓.
- [x] Drop `/etc/ssh/sshd_config.d/99-hardening.conf`.
- [x] `sshd -t` passed.
- [x] `systemctl reload ssh` (Debian unit is `ssh`, not `sshd`).
- [x] Password auth refused: `ssh -o PreferredAuthentications=password localhost` → `Authentications that can continue: publickey` (no `password` in the list any more).
- [x] Commit phase 2 — host-only changes are noted in this file; repo gets the updated `todo.md`.

## Host artifacts deployed (not in repo)

The following files live on the host outside the git tree; mirror them via
`ansible` / `cloud-init` / your config-management of choice for reproducibility.

- `/etc/fail2ban/jail.local` — 4-jail policy
- `/etc/fail2ban/filter.d/nginx-admin.conf` — POST /admin/ + 401
- `/etc/fail2ban/filter.d/nginx-8g.conf` — *.access.log + 403
- `/etc/ssh/sshd_config.d/99-hardening.conf` — SSH hardening drop-in
- `/usr/local/bin/cid-backup` — backup script (source in `scripts/cid-backup.sh`)
- `/etc/cid-backup.env` (0600 root) — restic passphrase + B2 keys
- `/etc/cron.d/cid-backup` — nightly 02:00 Asia/Bangkok
- `/etc/logrotate.d/cid-backup` — weekly rotate, keep 12

To roll back SSH hardening: `sudo rm /etc/ssh/sshd_config.d/99-hardening.conf && sudo systemctl reload ssh`.

## Phase 3 — Backup (restic → Backblaze B2)  ✓ shipped

- [x] Install restic on host (apt, v0.16.4).
- [x] Generate strong passphrase, stored in `/etc/cid-backup.env` (0640 root:docker so compose can read it).
- [x] Write `scripts/cid-backup.sh` (now deprecated, banner added).
- [x] Write `scripts/cid-backup.env.example` (committed template).
- [x] Write `scripts/cid-backup-setup.md` (sign-up walkthrough + ops).
- [x] Install `/etc/cron.d/cid-backup` (every 5 min, runs `cid-backup-tick`).
- [x] Install `/etc/logrotate.d/cid-backup`.
- [x] User signed up at backblaze.com, B2 creds in `/etc/cid-backup.env`, first backup uploaded successfully.

## Phase 4 — Backup v2 (job-driven engine + admin UI)  ✓ shipped

- [x] Broker: `apk add restic` (0.18.1) in `docker/broker/Dockerfile`.
- [x] Broker `app.py`: 9 backup routes, BackupLock, schedule parser, validators (db / table / snapshot / site / job-id regex + existence), `_set_restore_perms` with `writable_by_php` flag, bootstrap (`_ensure_data_files` → `_ensure_system_check_job` → `_ensure_seeded_db_jobs` with `seeded_dbs` marker), tick janitor, notify-on-error POST.
- [x] Compose: broker `env_file: /etc/cid-backup.env`; rw bind `admin/data` + `/var/cid-restores`; ro bind `sites/`. `/var/cid-restores/` mounted into cid-php74 too.
- [x] Host: `install -d -m 0750 -o 0 -g 33 /var/cid-restores`; `/etc/cron.d/cid-backup` calls `/usr/local/bin/cid-backup-tick`.
- [x] `scripts/cid-backup-tick.sh` — curl over broker unix socket.
- [x] `scripts/cid-backup.sh` — deprecation banner added.
- [x] PHP: `admin/api/backup.php` dispatch, `admin/templates/backup.php` UI with Jobs + Snapshots panels + Add/Edit modal + Runs modal.
- [x] `admin/index.php` — 🗄️ Backup nav entry.
- [x] `.gitignore` — exclude runtime `backup-jobs.json` + `backup-state.json`.
- [x] `*.example` files in `admin/data/` committed for first-run bootstrap.
- [x] `open_basedir` extended to include `/var/cid-restores` (else PHP can't readfile downloads).
- [x] End-to-end verification (10 checks): UI render, list-targets, jobs-get, snapshots, RunNow, restore, validation rejection, runs history, janitor sweep, download streaming.
- [x] Commit phase 4.
