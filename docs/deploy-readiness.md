# Deploy-Readiness Snapshot

**Snapshot date:** 2026-05-19
**Last commit:** `c8ac5c6` (origin/main, fully pushed)
**Active site:** `https://php.bitco.space/admin/` — serving 200
**Stack:** all `cid-*` containers Up, no background processes, no in-flight work

This doc is the starting point for the next work cycle. Read §6 ("Next session starts here") first.

---

## 1. What shipped this cycle (11 commits)

`0acc7ef` → `c8ac5c6`. Newest first:

| Commit | Title | Closes |
|---|---|---|
| `c8ac5c6` | docs: bring spec.md in line with current state | — |
| `7196cdb` | fix: route admin "Add New Site" through broker /site/create | **#36** |
| `e4d56f8` | chore: remove opc.bitco.link placeholder site + vhost after php.bitco.space cutover | **#39** part 3 |
| `41012e2` | fix: cut metrics writer + broker mount over to php.bitco.space site folder | **#39** part 2 |
| `0a6af53` | feat(nginx): add php.bitco.space vhost + site folder parallel to opc.bitco.link | **#39** part 1 |
| `29a1567` | fix(settings): input shows only the DB override, not the env fallback | **#38** polish |
| `141f295` | feat: settings page for runtime overrides of PMA_PUBLIC_URL + SITE_PUBLIC_IP | **#38** |
| `e58c822` | feat: extract sites/_template + scripts/new-site.sh + nginx vhost template | **#34** part 3 |
| `a0b13e3` | feat: parameterize nginx + phpmyadmin port bindings via .env | **#34** part 2 |
| `8ed8285` | chore: gitignore metrics.json + remove orphan opc2 vhost | **#34** part 1 |
| `0acc7ef` | refactor: remove hardcoded domain/IP from admin templates | **#33** |

### Tickets closed: 5

- **#33** Refactor hardcoded domain/IP from admin templates
- **#34** Portability work — gitignore, port-binding env, sites/_template extraction
- **#36** Refactor add_site.php to read from sites/_template/ (via broker `/site/create`)
- **#38** Settings page MVP — PMA_PUBLIC_URL + SITE_PUBLIC_IP runtime overrides
- **#39** Site migration opc.bitco.link → php.bitco.space (3-phase parallel-then-cutover)

### Tickets still open: 3

- **#35** Move cloudflared into `cid-network` for stricter home-host isolation — *defense-in-depth on home; not blocking deployment.*
- **#37** Rotate plaintext DB password in `sites/php.bitco.space/site.json` — *security; F-004-adjacent leak in tracked file + git history. Blocks tightening of credential discipline.*
- **#40** Cosmetic: `sites/php.bitco.space/public/index.php` still says "OPC" instead of "PHP" — *visual only; landing page brand pre-dates the `_template/` system.*

---

## 2. Architectural decisions locked this cycle

These shape future work; rejecting them is a deliberate choice.

### 2.1 Broker pattern extended to filesystem ops

`cid-broker` now owns all privileged writes to `/var/www/sites` and `/etc/nginx/conf.d`. PHP-FPM (www-data uid 33) has no group access to either bind mount on the host; `add_site.php` is a thin orchestrator that calls `brokerCall('/site/create', ...)`. The broker container is the only writer to those paths.

**Implication:** any new admin endpoint that needs to write under `sites/` or `nginx/conf.d/` must add a broker route, not a PHP `mkdir`/`file_put_contents`. (Lesson learned from #36: PHP-direct mkdir was dead-on-arrival for years and we didn't notice because the only sites in existence were created outside the admin UI.)

### 2.2 `setting($key, $default, $rawDb=false)` helper — DB → env → default

Added to `admin/_lib.php`. Idempotently creates `opc_db.site_settings`, caches per request, returns the first non-empty value in order. The `$rawDb=true` flag returns ONLY the DB override (used by the Settings page's input value to distinguish "override active" from "falling back to env").

**Implication:** prefer `setting()` over `getenv()` for values an operator might want to flip at runtime. Adding a new override = add the key to `save_settings.php`'s whitelist + form field in `templates/settings.php`. No restart needed.

### 2.3 mtime-based "active site" discovery

`scripts/collect_metrics.sh` picks `sites/*` by `find ... -printf '%T@ %p\n' | sort -rn | head -1`. During a rename migration, the new folder is freshly mtimed (cp -r stamps it) and wins. In steady state with one site, there's only one option.

**Known limitation (now confirmed in practice — see §5):** if the operator creates a second site but never serves it via nginx, the heuristic picks the unused one and metrics divert. Two safety levers exist:
- `METRICS_FILE` env override in the cron line (explicit, ugly).
- Remove unused sites promptly. Sites that exist but don't have a vhost aren't "real" yet.

### 2.4 `sites/_template/` is the single source of truth for new sites

Both paths consume it:
- `scripts/new-site.sh` (host-side bootstrap, runs pre-stack-up)
- Broker `POST /site/create` (in-stack, runs from admin UI)

Placeholder set: `{{DOMAIN}}`, `{{LABEL}}`, `{{DESCRIPTION}}`, `{{DB_NAME}}`, `{{DB_USER}}`, `{{SHORT_NAME}}`, `{{CREATED}}`.

**Implication:** if a new placeholder is added, both writers must learn it. Bounded fragility — same template files = naturally aligned.

### 2.5 Port bindings via `.env` (`NGINX_BIND`, `PMA_BIND`)

Default `0.0.0.0` (home, where cloudflared reaches via docker bridge gateway). Override `127.0.0.1` on cloud-deploy hosts where a host-level Caddy is the only legitimate caller.

**Implication:** the same compose file serves both deployment models. No branch divergence.

### 2.6 No DB passwords in `site.json`

The refactor of `add_site.php` (commit `7196cdb`) surfaces newly-generated DB passwords once in the HTTP response and never writes them to disk. Pre-existing `site.json` files (including php.bitco.space's) still have the legacy plaintext password — that's #37.

**Implication:** new sites created via the admin UI from now on do not accrete this leak. Cleaning up the historical artifact is a separate job.

### 2.7 Runtime state files gitignored by glob

`.gitignore` uses `sites/*/public/admin/data/X` patterns, so renames or new sites don't require `.gitignore` edits. Files: `backup-jobs.json`, `backup-state.json`, `drill-history.json`, `metrics.json`. The `.example` placeholders stay tracked.

---

## 3. What's deploy-ready as of `c8ac5c6`

| Capability | State |
|---|---|
| **Repo portability** | ✓ Domain hardcoded nowhere; site name derives from `sites/<DOMAIN>/`; first deploy = `scripts/new-site.sh --domain <D>` + `.env` + Caddyfile + `docker compose up -d`. |
| **Active production site** | ✓ `php.bitco.space` admin works end-to-end. Login, Sites, Database, PHP, Security, Backup, Logs, Settings all render. |
| **Backup pipeline** | ✓ Daily DB job (`db-opc_db`, 01:00, 30-day retention), daily config tarball (01:30, 90-day), weekly integrity check (Sun 04:00). 9/9 Phase 0.1 drill checks passed (Thai/emoji/CJK/NULL/JSON/large TEXT/FK/SQL-escape). |
| **Security baseline** | ✓ 8G WAF, fail2ban (4 jails), SSH hardening drop-in, bcrypt admin auth, CSRF, broker pattern, FPM on unix socket, `display_errors=Off`, `expose_php=Off`. 24 of 33 audit findings closed; residuals in `project-status.md §5.6`. |
| **Settings page** | ✓ Runtime overrides for `PMA_PUBLIC_URL` + `SITE_PUBLIC_IP` without container recreate. |
| **Add-site from UI** | ✓ Broker `POST /site/create` is wired and verified (magellanic test fixture worked end-to-end). |
| **Metrics dashboard** | ⚠ See §5.1 below — cron is firing but writing to the wrong site folder during the magellanic-pending state. |
| **DR runbook** | ✗ `docs/disaster-recovery.md` exists as a draft but is cloud-VPS-scoped, not home-server-scoped. Uncommitted. Out-of-scope until DR strategy decided. |

---

## 4. Pending for cloud deployment

In recommended priority order:

1. **#37 — Rotate plaintext DB password in `site.json`** (security). The `password: "OpcUser2026!"` literal lives in `sites/php.bitco.space/site.json` and the git history. Fix: rotate the actual DB user password, remove the field from `site.json`, run `git filter-repo` to purge the historical paths, force-push (coordinate with all clones). Same approach as F-004 for `docker/database/users_grants.sql`. **This is the highest-value next ticket** — see §6.
2. **Cleanup `sites/magellanic.bitco.space/`** (operational). Either fully adopt it (git-track + DNS + Caddy/tunnel route) or remove it. The pending state is causing the metrics drift in §5.1.
3. **#40 — Fix `php.bitco.space/public/index.php` "OPC" → "PHP" brand** (cosmetic). One-line fix; re-derive from `sites/_template/public/index.php` with `{{SHORT_NAME=PHP}}` substitution.
4. **DR runbook** (resilience). Blocked on a strategy decision (home-only / cloud-fallback / both) per `project-status.md §6.1`.
5. **#35 — Move cloudflared into `cid-network`** (defense-in-depth, home-only). Would let `NGINX_BIND` go to `127.0.0.1` on home too. Not urgent.
6. **F-004 — Purge `docker/database/*.sql` from git history** (security, F-004-adjacent overlap with #37). Coordinate with the #37 rewrite so both happen in one history pass.
7. **F-032 — Plan PHP 7.4 → 8.x migration** (technical debt). Separate project; not in the cloud-deploy critical path.

---

## 5. Clean state verification

### 5.1 Working tree (3 untracked items, all known)

```
?? docker/nginx/conf.d/magellanic.bitco.space.conf
?? docs/disaster-recovery.md
?? sites/magellanic.bitco.space/
```

- `docs/disaster-recovery.md` — paused DR runbook draft (deliberately uncommitted).
- `sites/magellanic.bitco.space/` + its nginx vhost — a real site added via admin UI at 13:52:37 today ("DWork - Magellanic"). Operator-decision pending. **This existence is currently causing the metrics drift below — auto-discover picks this folder over `php.bitco.space` because it's mtime-newer.**

### 5.2 Push status

- `c8ac5c6` is HEAD; ahead 0 / behind 0 vs `origin/main`.

### 5.3 Containers (all expected, all Up)

```
cid-broker         Up 4 hours    (recreated after #36 ship; mounts: sites:rw, nginx/conf.d:rw)
cid-mariadb        Up 29 hours
cid-nginx          Up 27 hours
cid-phpmyadmin     Up 27 hours
cid-php74          Up 29 hours
cid-redis          Up 4 days
cid-sftp           Up 3 weeks
cid-openclaw-admin Up 3 weeks   (separate compose project — adjacent, not part of this stack)
```

### 5.4 Background processes

None. All three in-session waits (`bdlle08ow`, `brj0k0ihk`, `bt45iciet`) completed cleanly during their respective phases.

### 5.5 Live service

- `curl -H "Host: php.bitco.space" http://127.0.0.1:9080/admin/` → 200 OK.
- Backup engine `cid-broker` `GET /backup/jobs` → 3 jobs returned (`__system_check__`, `db-opc_db`, `__system_config_backup__`).
- **⚠ Metrics drift:** the Dashboard's metrics are stale because of §5.1 — `collect_metrics.sh` picks `sites/magellanic.bitco.space/` (newer dir mtime) and writes there, but nginx serves the Dashboard from `sites/php.bitco.space/`. Cron is healthy (syslog confirms 5-min ticks at 13:50, 13:55, 14:00, 14:05, 14:10, 14:15, 14:20, 14:25...); the divergence is purely the mtime-based heuristic biting on the pending magellanic state. Fixes itself the moment magellanic is either removed (auto-discover returns to php.bitco.space) or has nginx routed to it. **Documented but NOT fixed this cycle per scope.**

---

## 6. Next session starts here

**Highest-value next ticket: #37 — rotate plaintext DB password in `site.json`.**

### Why it's the right starting point

1. **Security:** the password is in a tracked file AND in git history. Any clone leaks it. Every commit in history that touched `site.json` carries it. The cloud-deploy track inherits the leak the moment someone clones for a new deployment.
2. **Blocks the "no DB passwords in tracked files" invariant** that commit `7196cdb` established for new sites. We can't claim that invariant holds until the historical leak is purged.
3. **Pairs naturally with F-004** (`docker/database/users_grants.sql` purge) — both want a coordinated `git filter-repo` history rewrite. Doing one without the other is wasted effort.
4. **Independent of operator decisions still pending** (DR strategy, magellanic disposition, domain migration). Can ship without resolving anything else first.

### Recommended starting prompt for next session

> "Resume on #37: plaintext DB password leak in `sites/php.bitco.space/site.json`. Plan a single history-rewrite that also handles F-004 (`docker/database/*.sql`). Cover: (a) password rotation for MariaDB user `opc_user`, (b) `site.json` schema change (drop the `password` field; document where the value lives instead), (c) `git filter-repo --invert-paths` strategy across both files, (d) coordination with anyone else who has cloned the repo. Plan mode first."

### Before starting #37 — recommended quick cleanup (~5 min)

To avoid the metrics drift noted in §5.5 carrying into the next session, the next operator (you or future Claude) should pick one:

- **Option A** — remove magellanic if it was experimental: `rm -rf sites/magellanic.bitco.space docker/nginx/conf.d/magellanic.bitco.space.conf && docker exec cid-nginx nginx -s reload`. Metrics writer reverts to `php.bitco.space` on the next cron tick.
- **Option B** — adopt magellanic: `git add sites/magellanic.bitco.space docker/nginx/conf.d/magellanic.bitco.space.conf`, set up DNS, plus pin the metrics writer to one site via `METRICS_FILE=/home/.../php.bitco.space/.../metrics.json` in the cron line (or extend the heuristic to prefer the alphabetically-first vhost-mapped folder — small `collect_metrics.sh` refactor).

This is a cleanup, not a new feature. ~5 min either way.
