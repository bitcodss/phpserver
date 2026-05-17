# phpserver — Project Status & Resume Guide

**Last updated:** 2026-05-17
**Status:** PAUSED — operational, backup running, DR runbook drafted but incomplete
**Resume readiness:** Read §1, §5, §6 before starting any new work.

This document exists so a future me (or a new Claude instance) can pick up where this engagement paused. It is intentionally not a tutorial — it assumes familiarity with the repo's structure and the work that produced it.

---

## 1. TL;DR — Current state

- The `phpserver` Docker stack is running production on the home server, serving `opc.bitco.link` behind Cloudflare Tunnel. The original `opc2.bitco.link` (Cressida survey) has been deleted; its docroot is gone from the repo (commit `54bd8a8`).
- Daily backups to Backblaze B2 have been running unsupervised for ~3 days at the time of this pause. **Pipeline is verified** — Phase 0.1 acceptance drill passed all 9 byte-equal checks (Thai, emoji, CJK, NULL, JSON, large TEXT, FK, SQL-escape).
- Phase 0.1 through 0.2.1 are complete and pushed to GitHub. Phase 0.3 (DR runbook at `docs/disaster-recovery.md`) is drafted but **scope-incorrect** — it assumes a cloud-VPS rebuild, while the deployment is actually a home server behind Cloudflare Tunnel. Finalizing the runbook requires a strategy decision (see §6.1).
- Pause reason: the user wants to use the system as-is rather than continue DR work without first resolving the home-server vs cloud-fallback strategy question.
- All commits pushed to `origin/main` at `fa336c4`. **No unpushed work.**

---

## 2. What's implemented and working

### 2.1 Backup system (Phase 0.2 + Backup v2 UI)

- **Daily DB backups** at 01:00 Asia/Bangkok, 30-day retention. Currently one job (`db-opc_db`).
- **Daily config backups** at 01:30 Asia/Bangkok, 90-day retention. Job id `__system_config_backup__`.
- **Weekly integrity check** Sun 04:00 (5% data subset; full coverage in ~20 weeks). Job id `__system_check__`.
- Admin UI at `/admin/?page=backup` provides:
  - List, restore (to `/var/cid-restores/<sid>/`), download (DB only, gzipped stream), forget (with confirm).
  - Per-job runs history (last 20) with stdout/stderr tails.
  - Add/Edit job (locked Kind on Edit), pagination at 10 snapshots/page, localStorage cache 10-min TTL.
- Per-DB job config supports: schedule (daily/weekly/monthly + time, all Asia/Bangkok), retention days, table allowlist, enabled toggle.
- **Automatic catch-up** for missed schedules within 1.5× the interval (e.g., daily missed at 01:00 still runs by 01:00 next day).
- **Concurrency lock** (`/run/cid-backup.lock` inside broker) — tick blocks, RunNow returns 409 on conflict.
- **Restore staging** at `/var/cid-restores/` mounted into both broker (rw) and cid-php74 (rw) with the cross-container permission contract: dirs `0750 root:www-data` (or `0770` for `.dl-*` download staging), files `0640 root:www-data`. PHP_GID is env-driven (`PHP_GID=33`, configurable).
- **Idempotent system-job seeding** at broker boot. `seeded_dbs` marker prevents re-creating jobs the user has deleted.
- **Input validation** in broker (`_validate_database`, `_validate_tables`, `_validate_snapshot`, `_validate_site`, `_validate_job_id`) before any subprocess. Plus `--default-character-set=utf8mb4` on the `mariadb` invocation in `/mysql` + `/mysql-query` so callers don't need `SET NAMES utf8mb4` prefix.

### 2.2 Acceptance drill (Phase 0.1)

The drill ran 2026-05-16. From `admin/data/drill-history.json` (gitignored):

| Metric | Value (lower bound — single small DB) |
|---|---|
| Wall-clock backup (mariadb-dump → restic → B2 + prune) | 21.2 s |
| Wall-clock restore (B2 → local stage) | 6.6 s |
| Wall-clock import (`cat .sql \| mariadb`) | 0.07 s |
| Snapshot used | `e1214e45` (since forgotten as drill artifact) |
| Oldest snapshot retention test | `a5507197` — restored cleanly |
| Pass result | 9/9 checks byte-equal |

What this proved: restic→B2 round-trip integrity for Thai unicode, emoji+CJK, NULL, JSON column (via `CAST(data AS CHAR)` checksum), 1000-char TEXT, FOREIGN KEY survival, SQL escape edge cases (`O'Brien "the boss"`).

What it did **not** prove: large-DB behaviour (GB scale), concurrent write contention, site-file restore. Out-of-scope items deferred to Phase 0.3.5 (the Live DR test, also paused).

### 2.3 Configuration paths backed up (`CONFIG_BACKUP_PATHS` in `docker/broker/app.py`)

```
/etc/cid-backup.env             /etc/cron.d/
docker/.env                     /etc/ssh/sshd_config.d/
docker/docker-compose.yml       /etc/fail2ban/
/home/bitcodata/.ssh/authorized_keys    /etc/docker/daemon.json
/var/spool/cron/crontabs/       /var/lib/dpkg/   (for dpkg --get-selections)
```

The tar also includes top-level `RESTORE-NOTES.txt`, `user-crontab-README.txt`, and `dpkg-selections.txt`.

Explicitly **not** in scope:
- `/var/lib/caddy/` — Caddy runs in the `openclaw-caddy-1` container, owned by the separate `openclaw` project.
- `/etc/netplan/` — host-specific (interface names + MACs); to be documented in the DR runbook as literal reference values once scope is decided.

### 2.4 Security baseline (already pushed)

- 8G nginx WAF on all per-site vhosts (`docker/nginx/conf.d/_8g.conf`) — detection maps for bad UAs, malicious queries/URIs/methods. `if ($block_all) { return 403; }` in each vhost.
- Admin login emits HTTP 401 on bad credentials (signal for fail2ban `nginx-admin` jail).
- `fail2ban` on host, 4 jails active: `sshd`, `sftp`, `nginx-admin`, `nginx-8g`. `ignoreip` includes loopback + RFC1918 LANs.
- SSH hardening drop-in at `/etc/ssh/sshd_config.d/99-hardening.conf`: `PasswordAuthentication no`, root login disabled, modern crypto baseline, idle-disconnect, all forwarding off.
- Phase 1 audit fixes (24 of 33 findings) already in the repo: bcrypt + CSRF + session_regenerate on admin login, env-based secrets, SQLi parameterization (now moot for the deleted opc2 app but the patterns are documented), mass-assignment allow-lists, etc.

### 2.5 Broker (`cid-broker`)

Privileged-ops sidecar that holds `/var/run/docker.sock`. Routes:

- Privileged ops: `/mysql`, `/mysql-query`, `/container`, `/container/status`, `/logs`, `/nginx/reload`, `/caddy/route`.
- Backup: `/backup/jobs` (GET/PUT), `/backup/list-targets`, `/backup/list-tables`, `/backup/snapshots`, `/backup/run`, `/backup/restore`, `/backup/forget`, `/backup/download-db`, `/backup/runs`, `/backup/tick`.
- Health: `/healthz`.

Listens on `/run/broker/broker.sock` (unix socket, mode `0660 root:phpaccess` where `phpaccess` GID = 33 matches cid-php74's www-data). cid-php74 itself **no longer mounts docker.sock** and has no Docker CLI installed — F-006 / F-007 mitigation.

---

## 3. Git history (Phase 0.x commits — all on origin/main)

```
fa336c4 phase 0.2.1: close config backup gaps + scope clarification
dafd0b7 backup phase 0.2: config backup system job
2d958f2 broker: default to utf8mb4 charset for /mysql and /mysql-query
4ba6140 backup ui v2.2: showToast bug fix + 5 polish items
2888c28 backup ui v2.1: cache + per-job snapshots + sections + polish
67c5aec backup v2: job engine + admin UI (restic via broker, daily DB jobs)
3dec623 backup: nightly restic → Backblaze B2 + updated spec
4bb1da4 security phase 2: fail2ban (4 jails) + SSH hardening
e777c5d security: audit fixes + infra hardening + 8G firewall (phase 1)
54bd8a8 Remove opc2.bitco.link site data
```

Push status: **all pushed to origin/main at fa336c4.** Verified `ahead=0 behind=0`.

Hashes above are the *post-rebase* values. The 9 commits authored during this engagement were originally on top of `cbf740e`; rebased onto `54bd8a8` (the user's manual opc2 deletion commit) on 2026-05-17 with 6 conflicts resolved by accepting the deletions for opc2 site files. The pre-rebase HEAD was tagged `pre-rebase-1779030863` for safety; can be deleted with `git tag -d pre-rebase-1779030863` when no longer useful.

---

## 4. Files of interest

| Path | Purpose |
|---|---|
| `docker/broker/app.py` | Broker logic — privileged ops, backup engine, schedule eval, idempotent seeding, input validators |
| `docker/broker/Dockerfile` | python:3.12-alpine + docker CLI + restic + dpkg + tar |
| `docker/docker-compose.yml` | Stack definition (7 services) + bind mounts (incl. host-config for backup) |
| `docker/nginx/conf.d/_8g.conf` | 8G WAF detection maps |
| `docker/nginx/conf.d/opc.bitco.link.conf` | Per-site vhost (production) |
| `docker/nginx/conf.d/opc2.bitco.link.conf` | Per-site vhost (**orphan** — points at deleted docroot, see §6) |
| `docker/php/conf/php-custom.ini` | display_errors=Off, session.cookie_secure=1, open_basedir incl. /var/cid-restores |
| `docker/php/conf/www.conf` | FPM unix socket, clear_env=no |
| `docker/.env` | Secrets — gitignored (MYSQL_ROOT_PASSWORD, ADMIN_PASS_HASH, OPC2_* tokens, SFTP_*) |
| `sites/opc.bitco.link/public/admin/templates/backup.php` | Backup UI |
| `sites/opc.bitco.link/public/admin/api/backup.php` | Backup UI ↔ broker dispatcher |
| `sites/opc.bitco.link/public/admin/data/backup-jobs.json` | Runtime job config (**gitignored**) |
| `sites/opc.bitco.link/public/admin/data/backup-state.json` | Run history per job (**gitignored**) |
| `sites/opc.bitco.link/public/admin/data/drill-history.json` | Drill timings (**gitignored**) |
| `docs/disaster-recovery.md` | DR runbook **DRAFT** — incomplete, see §6.1 |
| `docs/project-status.md` | this file |
| `scripts/cid-backup-tick.sh` | Host cron entry point, curls broker /backup/tick |
| `scripts/cid-backup.sh` | Legacy monolithic backup (deprecation banner; kept one release) |
| `scripts/cid-backup-setup.md` | Earlier B2-signup walkthrough (now superseded by §5 of this doc + DR runbook) |
| `/etc/cid-backup.env` (host) | RESTIC_PASSWORD, B2 keys, BACKUP_NOTIFY_URL, PHP_GID. Mode `0640 root:docker`. |
| `/etc/cron.d/cid-backup` (host) | `*/5 * * * * root /usr/local/bin/cid-backup-tick …` |
| `/etc/fail2ban/jail.local` (host) | 4-jail policy. Not in repo — captured by config backup. |
| `/etc/ssh/sshd_config.d/99-hardening.conf` (host) | SSH drop-in. Not in repo — captured by config backup. |

---

## 5. Operational reality check

This section captures the parts of the deployment that the *code* alone doesn't reveal — context a future resumer needs to make correct decisions in §6.

### 5.1 Deployment topology

- **Home server, NOT a cloud VPS.** Hardware lives in the user's space.
- **Public exposure via Cloudflare Tunnel** (`cloudflared` process running on host), not port forwarding. No static public IP; the tunnel handles ingress without exposing a listener on the home router.
- Internal LAN: 192.168.x.x. Docker bridges run on additional 192.168.{16,32,48}.0/20 ranges.
- **Implication for DR**: the runbook draft at `docs/disaster-recovery.md` was written assuming "provision a new VPS" recovery. That's wrong for this deployment. See §6.1.

### 5.2 Domains

- **Active**: `opc.bitco.link` (admin dashboard at `/admin/`).
- **Deleted**: `opc2.bitco.link` — survey app docroot removed in `54bd8a8`. The nginx vhost `docker/nginx/conf.d/opc2.bitco.link.conf` is still present in the repo but its document root no longer exists. Not actively requested via DNS so no error surfaced. Marked as a side-finding to clean up if/when the runbook is finalized.
- **Migration in flight**: user mentioned plans to migrate `opc.bitco.link → php.bitco.space`. **Not yet started.** DNS still points at the existing domain. Out of scope until resume.

### 5.3 Backup storage

- Provider: **Backblaze B2**
- Bucket: `phpserver-backup`
- Encryption: client-side via restic (zstd compression, ~2.31× ratio observed)
- Current usage (as of pause): **8 snapshots, 45.3 MiB raw, 19.6 MiB stored**
- Endpoint URL: stored in `/etc/cid-backup.env` as `RESTIC_REPOSITORY`. Format `b2:phpserver-backup:cid`.

### 5.4 Credentials storage

- **Primary**: Bitwarden vault (user's account). The runbook (§2.1) assumes the four backup credentials live here: `RESTIC_REPOSITORY`, `RESTIC_PASSWORD`, `B2_ACCOUNT_ID`, `B2_ACCOUNT_KEY`.
- **Tertiary**: paper backup in a secure physical location (user-managed).
- ⚠️ **Vault completeness has not been audited.** See §6.3.

### 5.5 Admin users on phpserver

- Only `bitcodata` (single admin). Hardcoded creds replaced with env-loaded bcrypt hash in `docker/.env`.
- 2FA on the admin panel: **not implemented**. Was on the roadmap as a Phase 2.x item; deferred.

### 5.6 Open audit residuals (carried over from archived `audit-findings.md`)

The original audit (2026-05-14) catalogued 33 findings. Phase 1 closed 24 of them; Phase 1.5 / F-006/007/023 closed the remaining critical infra items. The following are **still open** and not tracked elsewhere — re-evaluate at resume:

- **F-004 · Critical · DB dump + native-password hashes committed to repo.** `docker/database/all_databases.sql` (7.5 MB) and `docker/database/users_grants.sql` are both still in the working tree **and** in git history (initial commit `8fffd11`). The grants file leaks SHA1(SHA1(pw)) hashes for `dw_spy`, `opc_user`, `test`. Crackable on commodity GPU.
  - **Fix:** purge from history with `git filter-repo --path docker/database --invert-paths`, force-push (destructive — coordinate with all clones). Rotate the three DB user passwords. Add `docker/database/` to `.gitignore`.
  - **Caveat:** `dw_spy` was bound to the deleted `opc2` survey app, so likely moot. `opc_user` is still live (powers `opc.bitco.link`'s `opc_db`). Rotate at minimum.
  - **Bonus item from the audit's "deferred" list:** what PII is actually inside `all_databases.sql`? If real respondent data, F-004 escalates from "leaked hashes" to "leaked personal data". Audit before purging history.

- **F-005 partial · Medium · SFTP container still uses password auth.** The credential itself is fixed (now `${SFTP_PASSWORD}` from `docker/.env`, not in git), but `PASSWORD_ACCESS=true` remains on. Public-internet listener at `:2222`.
  - **Fix:** flip to `PASSWORD_ACCESS=false`, mount an authorized_keys volume with the operator's pubkey, document the rotation in `docker/.env.example`.

- **F-032 · Info · PHP 7.4 is EOL** (since 2022-11-28). Base image `php:7.4-fpm-bullseye` still in use. No upstream security patches for 3+ years.
  - **Fix:** plan a 7.4 → 8.2 (or 8.3) migration as a separate work item. The admin codebase uses 7.4-isms (notice suppression, lax type juggling) that will need attention.

- **F-033 · Info · PHPExcel deprecated.** **Resolved automatically** when `sites/opc2.bitco.link/` was deleted — PHPExcel only lived in `xls/` there. No action needed.

- **Deferred review** (from audit's "deferred for follow-up" list): full review of `routing1.php` / `task1.php` / `phpfunc.php`. **Resolved automatically** — these files were under opc2, now deleted.

- **Host Caddy config** — out of scope, runs in the separate `openclaw-caddy-1` container.

Detail for each finding (impact, repro, original wording) lives at `docs/archive/audit-findings.md`.

---

## 6. Open questions — must answer before resuming Phase 0.3+

### 6.1 DR strategy (BLOCKING — required to finalize the runbook)

**Question:** if the home server is destroyed, what's the recovery target?

| Option | Description | Implications |
|---|---|---|
| **A — Home-only** | Buy replacement hardware, restore on-premise | RTO measured in days. Runbook §5.1 changes from "provision VPS" to "boot a fresh Ubuntu install on new hardware". §4 reference data drops VPS provider, adds router config + Cloudflare Tunnel re-pairing. |
| **B — Cloud-fallback** | Standby VPS at a provider (DigitalOcean, Hetzner, etc.) | RTO measured in hours. Runbook needs: provider name, account credentials in Bitwarden, optional standby VM if pre-provisioned. Cost: ~$5-20/mo for an always-on standby, or $0 if provisioned-on-demand. |
| **C — Both** | Home primary, cloud documented as fallback | Most complete; runbook gets two branches. Highest documentation cost. |

**Decision needed:** A / B / C.

Sections of `docs/disaster-recovery.md` that depend on this:
- §1 (at-a-glance) — RTO target
- §3 (external dependencies) — Cloudflare Tunnel needs to be on the new host running
- §4 (reference data) — what to fill in
- §5.1 (provision step) — totally different procedure
- §5.13 (DNS) — Cloudflare Tunnel routing changes vs DNS A record changes

Until this is decided, the runbook stays in its current state (cloud-VPS scope, will mislead a future operator).

### 6.2 Domain migration timing (`opc.bitco.link → php.bitco.space`)

**Question:** when does the migration happen relative to runbook finalization?

| Option | Description |
|---|---|
| **A** | Complete migration first, runbook documents end state only |
| **B** | Document dual-domain transition in the runbook |
| **C** | Defer migration; runbook captures current state |

**Decision needed:** A / B / C.

If A or B, the migration also implies renaming/replacing the directory `sites/opc.bitco.link/` → `sites/php.bitco.space/` (or running them in parallel), updating all nginx vhosts, updating `add_site.php` defaults, updating the Caddy route via openclaw, and re-issuing TLS.

### 6.3 Bitwarden vault completeness audit

**Question:** does the vault currently hold every credential needed for a 3 AM rebuild?

Recommended minimum entries (assumes Strategy B/C for VPS; trim if A):

1. ⭐ **Backup credentials** — `RESTIC_REPOSITORY`, `RESTIC_PASSWORD`, `B2_ACCOUNT_ID`, `B2_ACCOUNT_KEY`
2. **`docker/.env` secrets** — `MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`, `ADMIN_PASS_HASH`, `OPC2_*` tokens (if survey ever returns), `SFTP_*`
3. **Backblaze console login** — for B2 dashboard access (key rotation, etc.)
4. **Cloudflare account login** — for DNS + Tunnel management
5. ⭐ **Google account** (if Cloudflare uses Google SSO)
6. ⭐ **Google account backup codes** — last-resort recovery
7. **Cloudflare API token** — break-glass for tunnel/DNS automation
8. **VPS provider console** (only needed for Strategy B/C)
9. **GitHub account** — for cloning the repo on a fresh host
10. **SSH private key passphrase** (if your `~/.ssh/id_ed25519` has one)

**Action needed:** user audit the vault against this list, fill gaps. ⭐ items are critical.

### 6.4 Hardware documentation

Currently **not in the repo**. If Strategy A or C is chosen, the runbook needs hardware facts:

- CPU / RAM / storage / GPU (if any)
- Network: NIC model, switch, router model
- Cloudflare Tunnel: tunnel ID, route configuration
- Any UPS, IPMI, console access

Suggest a new file `docs/hardware.md` if Strategy A or C wins.

---

## 7. What NOT to touch when resuming

Hard rules for the next session:

- ❌ **Don't modify the backup engine** (`docker/broker/app.py` backup routes, `docker-compose.yml` broker mounts, anything in `admin/templates/backup.php`). It's production, drill-passed, and a survey database depends on it.
- ❌ **Don't amend old commits** (anything before `fa336c4`). The history is now public; rewriting would force-push and risk losing data on other clones.
- ❌ **Don't finalize the DR runbook** until §6.1 is answered. A runbook with the wrong recovery target is worse than no runbook.
- ❌ **Don't add new features** (2FA, audit log, monitoring, etc.) before DR is complete. The earlier roadmap explicitly said "data loss prevention > security > productivity" and that ordering still holds.
- ❌ **Don't start the domain migration** as a casual side-task during a resume session. It deserves its own focused engagement.
- ❌ **Don't push from a different machine** without first `git pull --rebase` — the repo is now active on GitHub and divergence is possible.

---

## 8. What's safe to do when resuming

Without any user input or §6 answers:

- ✅ Read this doc and `git log` to refresh context.
- ✅ Read `docs/disaster-recovery.md` to see what's drafted (and what's wrong about it).
- ✅ Verify backup pipeline is still alive (commands in §10.1).
- ✅ Verify backup freshness — latest snapshot < 48 h old.
- ✅ Smoke-test admin UI: login, view jobs, view snapshots.
- ✅ Run an ad-hoc backup via the "Run Now" button on a DB job.
- ✅ Ask the user for the §6 decisions.

---

## 9. Re-engagement sequence

When this engagement resumes:

1. Read this doc (5 min).
2. Run §10.1 weekly verification commands (5 min). Confirm pipeline is alive.
3. Ask the user: "Since pause, anything changed? Do you have answers to §6.1, 6.2, 6.3?"
4. **If yes** to all of §6 → resume Phase 0.3 finalization with the chosen strategy. Then Phase 0.3.5 (Live DR test).
5. **If partial** → help the user think through the trade-offs for the unanswered question. Don't proceed to runbook edits until decided.
6. **If no** → stop. Don't drift into "while we're at it" work. Pause again.

---

## 10. Routine maintenance — user self-service during pause

Things to do **without Claude help** while paused.

### 10.1 Weekly (1 minute)

Verify the backup pipeline is alive:

```bash
sudo bash -c 'set -a; . /etc/cid-backup.env; set +a; \
  restic snapshots --tag kind:db | tail -3; \
  echo; \
  restic snapshots --tag kind:config | tail -3'
```

Latest snapshot of each kind must be < 48 h old. If older → backup cron stopped, investigate before proceeding.

### 10.2 Monthly (5 minutes)

```bash
df -h                              # disk usage
docker system df                   # docker disk usage
sudo fail2ban-client status        # 4 jails should be listed
sudo cat /home/bitcodata/phpserver/sites/opc.bitco.link/public/admin/data/backup-state.json \
  | python3 -c "import json,sys; d=json.load(sys.stdin); \
    [print(k, 'last:', v.get('history',[{}])[-1].get('status')) \
     for k,v in d.get('runs',{}).items()]"
```

Any non-`ok` status in the last run of any job → investigate.

### 10.3 Quarterly (15 minutes)

Manual restore drill (Level 1) — same procedure as Phase 0.1 (the one already in `drill-history.json`):

1. Seed a small fixture into a database (Thai/emoji/NULL/JSON edge cases).
2. Trigger a backup.
3. Restore the new snapshot to a `drill_test` database.
4. Compare checksums.
5. Append the result to `admin/data/drill-history.json`.
6. Drop the fixture and the `drill_test` database.

Plus: audit the Bitwarden vault. Try logging into B2 console with the stored credentials — does it still work? (Application keys can be revoked silently.)

### 10.4 If something breaks during pause

1. **Backup stopped firing**: `sudo /usr/local/bin/cid-backup-tick` runs it manually; check `/var/log/cid-backup.log` for the error.
2. **Admin UI inaccessible**: `docker ps` to confirm containers running; `docker compose -f docker/docker-compose.yml restart php74 nginx` if needed.
3. **Disk full**: `docker system prune -af` (be careful — removes unused images).
4. **Lost SSH access**: use Cloudflare Tunnel's console / physical access. The SSH hardening drop-in can be removed by `rm /etc/ssh/sshd_config.d/99-hardening.conf && systemctl reload ssh`.

---

## Appendix A — Glossary (brief)

- **Broker** (`cid-broker`) — Python+Flask sidecar that holds `/var/run/docker.sock` so PHP doesn't have to. Hosts the backup engine.
- **Drill** — synthetic verification that the restore pipeline actually works end-to-end. Phase 0.1.
- **Restic** — encrypted-incremental-backup tool. Stores snapshots in B2; passphrase-encrypted client-side.
- **B2** — Backblaze B2. Object storage; S3-compatible API.
- **8G** — Perishable Press's nginx WAF rule set (8G generation). Pattern-matches bad UAs, queries, URIs, methods.
- **Cloudflare Tunnel** — `cloudflared` outbound tunnel that exposes a local service to the internet without opening an inbound port on the home router. This is how `opc.bitco.link` is reachable.

## Appendix B — Commit-message conventions used

- `backup vN: …` — feature work on the backup engine
- `backup ui v2.x: …` — UI tweaks
- `phase 0.x: …` — disaster-recovery phases
- `broker: …` — broker-level bugs / improvements
- `docs: …` — documentation only
- `security phase N: …` — security baseline work

---

*End of project status. Resume sequence: §9. Verification while paused: §10.*
