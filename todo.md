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

## Phase 3 — Backup (restic → Backblaze B2)

- [x] Install restic on host (apt, v0.16.4).
- [x] Generate strong passphrase, stored in `/etc/cid-backup.env` (0600 root).
- [x] Write `scripts/cid-backup.sh` + install at `/usr/local/bin/cid-backup`.
- [x] Write `scripts/cid-backup.env.example` (committed template).
- [x] Write `scripts/cid-backup-setup.md` (sign-up walkthrough + ops).
- [x] Install `/etc/cron.d/cid-backup` (nightly 02:00 Asia/Bangkok).
- [x] Install `/etc/logrotate.d/cid-backup`.
- [x] Smoke-test against a local restic repo — DB dump (1.1 MB) + files snapshot OK, restore verified, structural check passed.
- [ ] **YOU:** sign up at backblaze.com/b2, create bucket + application key, paste into `/etc/cid-backup.env` (steps in `scripts/cid-backup-setup.md`).
- [ ] Run `sudo /usr/local/bin/cid-backup` once manually to init the B2 repo and confirm first upload.
- [ ] Save the restic passphrase from `/etc/cid-backup.env` into your password manager.
- [ ] Commit phase 3.
