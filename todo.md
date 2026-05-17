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
- [ ] Commit phase 1.

## Phase 2 — Host-level protections (Tier 2)

- [ ] Confirm key-based SSH works in a second session before touching sshd.
- [ ] `apt install fail2ban`.
- [ ] Drop `/etc/fail2ban/jail.local` with the four jails (sshd, sftp, nginx-admin, nginx-8g).
- [ ] Drop `/etc/fail2ban/filter.d/nginx-admin.conf` and `nginx-8g.conf`.
- [ ] Add the user's current IP to `ignoreip`.
- [ ] `systemctl enable --now fail2ban`. Confirm `fail2ban-client status` shows 4 jails.
- [ ] Trigger 5 bad logins against sshd from a test IP, confirm ban (`fail2ban-client status sshd`).
- [ ] Trigger 5 bad sftp logins from a test IP, confirm ban (`fail2ban-client status sftp`).
- [ ] Drop `/etc/ssh/sshd_config.d/99-hardening.conf`.
- [ ] `sshd -t` — confirm config syntax.
- [ ] `systemctl reload sshd`.
- [ ] In a third SSH window, attempt to log in with password — must fail; key login must succeed.
- [ ] Commit phase 2 (or note as host-only, not in repo).
