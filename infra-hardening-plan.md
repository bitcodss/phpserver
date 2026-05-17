# Infrastructure Hardening Plan — F-006, F-007, F-023

Follow-up to `audit-findings.md`. The 24 fixes shipped in the first pass closed the credential, SQLi, CSRF, and silent-broken-config issues. This document covers the three architectural items left open:

- **F-006** — `/var/run/docker.sock` bind-mounted into `cid-php74`. PHP RCE = full Docker control.
- **F-007** — `add_site.php` reaches Caddy admin on `127.0.0.1:2019` via `docker run --network=host`.
- **F-023** — PHP-FPM listens on `0.0.0.0:9000` inside `cid-network`. Any peer container can talk to FPM directly, bypassing nginx.

No external services are required. Everything below stays inside this repo and runs on the same host.

---

## Phase A — Move PHP-FPM to a unix socket (F-023)

Smallest, safest change. Do this first to validate the socket-volume pattern before the bigger broker work.

### Design

- New named volume `fpm-socket` with type `tmpfs` (in-memory, no disk persistence needed for a socket).
- `cid-php74` listens on `/run/php-fpm/www.sock`; the volume is mounted there.
- `cid-nginx` mounts the same volume read-only and uses `fastcgi_pass unix:/run/php-fpm/www.sock`.
- TCP 9000 is removed entirely.

### Files to change

| File | Change |
|---|---|
| `docker/docker-compose.yml` | Add `fpm-socket` to `volumes:`. Mount it into `php74` (rw) and `nginx` (ro). Remove the `EXPOSE 9000` reliance. |
| `docker/php/conf/www.conf` | `listen = /run/php-fpm/www.sock`, `listen.owner = www-data`, `listen.group = www-data`, `listen.mode = 0660`. |
| `docker/nginx/conf.d/opc.bitco.link.conf` | `fastcgi_pass unix:/run/php-fpm/www.sock;` |
| `docker/nginx/conf.d/opc2.bitco.link.conf` | Same. |
| `sites/opc.bitco.link/public/admin/api/add_site.php` | The generated vhost template uses `fastcgi_pass unix:/run/php-fpm/www.sock;` so new sites match. |
| `docker/php/Dockerfile` | Optional: drop the `EXPOSE 9000` line. (Cosmetic — no host port was published anyway.) |

### Edge cases to handle

- nginx starts before php74 → the socket file doesn't exist yet → nginx errors. Mitigation: keep the existing `depends_on: php74` in the nginx service (already there). Worst case, add a one-shot init that waits for the file.
- nginx runs as user `nginx` (alpine image). The FPM socket mode `0660` with group `www-data` won't be readable by `nginx`. Either:
  - Set the socket mode to `0666` (less ideal but contained — only containers on the volume can reach it), or
  - Run nginx with the same group / set `listen.group = nginx` and create that group inside cid-php74. **Recommended:** mode `0660`, `listen.group = nginx`, add the group to the FPM container.

### Verification

1. `docker compose up -d --force-recreate php74 nginx`
2. `docker exec cid-php74 ls -la /run/php-fpm/www.sock` → socket exists, mode 0660.
3. `docker exec cid-nginx ls -la /run/php-fpm/www.sock` → readable.
4. `curl -sk -H 'Host: opc2.bitco.link' http://127.0.0.1:9080/` → 200, page renders.
5. `docker exec cid-nginx ss -t -a | grep 9000` → no longer present.
6. Smoke-test admin login + one API call to confirm php74 still works via FastCGI.

### Rollback

Revert `www.conf` and the two vhosts to `listen = 0.0.0.0:9000` / `fastcgi_pass php74:9000;`. Recreate the two containers. Three-line revert per file.

---

## Phase B — Privileged-ops broker sidecar (F-006, F-007)

The bigger change. A small Python service runs in its own container, holds the Docker socket, and exposes a tightly scoped HTTP API over a unix socket shared with `cid-php74`. The PHP container no longer mounts `/var/run/docker.sock` and no longer has the Docker CLI.

### Threat model improvement

Before: any RCE in PHP can run any `docker` command. With `--network=host` it can reach Caddy admin and reconfigure the public edge.

After: any RCE in PHP can only call the broker's allow-listed routes. No arbitrary Docker. No Caddy admin reachability.

### Broker service

- **Image:** `python:3.12-alpine` + Flask (or stdlib `http.server` to avoid the pip step). ~50 MB.
- **Listens on:** `/run/broker/broker.sock` (unix socket, mode 0660).
- **Runs as:** root in its own container (needs Docker socket). The container has no PHP, no website code mounted.
- **Outbound:** mounts `/var/run/docker.sock` rw; joins `cid-network` so it can curl Caddy admin without `--network=host`.

### Broker routes

| Method | Path | Body | Action |
|---|---|---|---|
| POST | `/container` | `{ name, action }` | `docker <action> <name>` — name must match allow-list `cid-(php74|nginx|mariadb|phpmyadmin|redis)`, action in `start|stop|restart`. |
| POST | `/mysql` | `{ sql }` | `docker exec -e MYSQL_PWD=… cid-mariadb mysql -uroot -e <sql>`. Password lives in the broker's env (same `MYSQL_ROOT_PASSWORD` from `.env`). |
| POST | `/mysql-query` | `{ sql }` | Same but with `-se` (raw, scriptable). |
| POST | `/nginx/reload` | `{}` | `docker exec cid-nginx nginx -s reload`. |
| POST | `/caddy/route` | `{ payload }` | `curl http://caddy:2019/...` from the broker's network position. |

Every route logs the caller + payload to broker stdout (= `docker logs cid-broker`). Optional later: HMAC signing of requests using a shared secret in env.

### PHP-side changes

Add a helper in `sites/opc.bitco.link/public/admin/_lib.php`:

```php
function broker(string $path, array $payload = []): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_UNIX_SOCKET_PATH => '/run/broker/broker.sock',
        CURLOPT_URL              => "http://localhost{$path}",
        CURLOPT_POST             => true,
        CURLOPT_POSTFIELDS       => json_encode($payload),
        CURLOPT_HTTPHEADER       => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER   => true,
        CURLOPT_TIMEOUT          => 30,
    ]);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http' => $http, 'body' => $body, 'json' => json_decode((string)$body, true)];
}
```

Then `mysqlExec()` / `mysqlQuery()` become thin wrappers around `broker('/mysql', ...)`. `container_action.php` calls `broker('/container', …)`. `add_site.php` calls `broker('/caddy/route', …)` and `broker('/nginx/reload', …)`.

### Compose changes

Add a new service:

```yaml
  broker:
    build: ./broker
    container_name: cid-broker
    restart: unless-stopped
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - broker-socket:/run/broker
    networks:
      - cid-network
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
```

`cid-php74` updates:
- **Drop** `- /var/run/docker.sock:/var/run/docker.sock`.
- **Add** `- broker-socket:/run/broker:ro`.
- Drop the Docker CLI install from `docker/php/Dockerfile` (saves ~50 MB and removes the attack tool from the container).

New top-level volume:

```yaml
volumes:
  mariadb-data:
  redis-data:
  broker-socket:
```

### Broker file layout

```
docker/
└── broker/
    ├── Dockerfile        # python:3.12-alpine + uvicorn + a tiny app.py
    ├── app.py            # ~150 lines, the routes above
    └── requirements.txt  # flask + gunicorn (or nothing if using http.server)
```

### Verification

1. `docker compose up -d --build broker`
2. `docker exec cid-php74 curl --unix-socket /run/broker/broker.sock http://x/healthz` → 200.
3. Each admin panel works exactly as before:
   - Database tab loads (broker `/mysql-query`).
   - Container restart from Dashboard works (broker `/container`).
   - Add Site succeeds, Caddy route POSTs through (broker `/caddy/route`).
4. `docker exec cid-php74 which docker` → not found. `docker exec cid-php74 ls /var/run/docker.sock` → no such file.

### Rollback

Re-add the Docker socket mount and Docker CLI to `cid-php74`; revert the admin helper to its current `shell_exec` form. The broker container can stay deployed but unused, or be stopped.

### Risks

- **Single point of failure**: if the broker is down, admin DB/container/Caddy features all fail. Mitigated by `restart: unless-stopped` and the fact that a broker outage doesn't affect the websites themselves.
- **Broker bug surface**: the broker becomes a privileged target. Mitigation: keep it small, allow-list every input, never `eval`/`shell=True`, log every call.
- **Initial bring-up complexity**: more containers to coordinate. Mitigation: bring up behind a feature flag — keep the existing code paths in place under an `if (getenv('USE_BROKER'))` for one release, switch over after validation.

---

## Sequencing

1. **Phase A first** (FPM socket). Small, easy to verify, builds confidence with the shared-volume pattern.
2. **Phase B broker** in two PRs:
   - PR 1: ship the broker container + `/healthz` + `/mysql-query`; convert read-only dashboard queries to use it. Both code paths coexist behind an env flag.
   - PR 2: convert the write paths (`/mysql` writes, `/container`, `/caddy/route`, `/nginx/reload`); flip the flag default; remove the Docker socket and CLI from `cid-php74`.

Each PR is reversible on its own.

---

## What this does NOT address

- **F-004** — DB dump + native-password hashes in git history. Still needs `git filter-repo`; destructive, awaits your go-ahead.
- **F-032 / F-033** — PHP 7.4 EOL + PHPExcel deprecated. Separate migration projects.
- The SFTP container's blanket access to `sites/` (F-005 partial). Closer to a tooling question — restrict via per-site chroot or replace with key-based deploy.

---

## Open questions for you

1. **Broker language**: stick with Python (recommended for readability), or do you prefer Go for the smaller image?
2. **Feature flag**: do the two-PR rollout, or is one big switchover fine?
3. **Phase A socket permissions**: OK with `listen.group = nginx` requiring a group add in cid-php74, or prefer the looser `mode = 0666`?

I'll wait for your call on those before writing any code.
