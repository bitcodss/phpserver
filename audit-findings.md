# Audit Findings — phpserver

Date: 2026-05-14
Scope: full repo at `/home/bitcodata/phpserver` (excludes host Caddy config, PHPExcel/SFTP library internals, and SQL dump row contents).
Method: manual code review per `audit-plan.md`, file-by-file, plus targeted greps.

---

## Executive summary

**31 findings** in total: **8 Critical**, **9 High**, **7 Medium**, **5 Low**, **2 Info**.

The most serious problems cluster in three places:

1. **Authentication is effectively defeated by design.** The single admin password (`Aptx4869&$`) is hardcoded in `admin/index.php`, the MariaDB root password (`CidMariaDB2026!`) is hardcoded in ten admin files *and* rendered on screen in the Database tab, and the survey app's `dw_spy` DB password is hardcoded in `api/config.php`. Anyone with git read access has every credential.
2. **The admin → host trust boundary is gone.** `/var/run/docker.sock` is bind-mounted into `cid-php74`. Combined with `add_site.php`'s ability to call `docker run --network=host` against Caddy's local admin API, any RCE in PHP is full host compromise. The webserver IS the Docker host.
3. **The survey app builds SQL by string concatenation in many places** with user input from `$_GET['id']`, `$_REQUEST['qstep']`, and `$_POST` keys. The `$pdo->prepare()` calls do not parameterize these — injection has already happened by the time `prepare()` sees the string.

Beyond those, two admin endpoints (`save_php.php`, `save_fpm.php`) appear to be **silently broken** — they report success but don't actually update the PHP/FPM config because they write to wrong paths or to read-only mounts.

### Recommended fix order

1. F-001, F-002, F-003 — pull every password out of source into `.env` and verify what's actually loaded in prod.
2. F-004 — purge `docker/database/*.sql` from history (the user dump leaks MySQL native-password hashes).
3. F-005, F-006 — fix the admin login (use the bcrypt hash properly; add `session_regenerate_id`; precompute the hash once).
4. F-008 — fix the SQL injection in `dw_spy`-context queries before adding more survey features.
5. F-010, F-011 — fix the broken `save_php.php` / `save_fpm.php`, OR document that they don't work.
6. F-012, F-013 — fix the SQL-injection-via-password in admin user-management endpoints.
7. F-018 — remove or auth-gate `insertcase.php` (unauthenticated DB writer).
8. The rest by severity.

---

## Findings

### F-001 · Critical · `sites/opc.bitco.link/public/admin/index.php:10-14` — Admin password hardcoded; bcrypt path is dead code

**What.** Line 10 builds a bcrypt hash of `'Aptx4869&$'` into `ADMIN_PASS_HASH`. Line 14 then ignores that constant and compares the submitted password to the **plaintext literal** `'Aptx4869&$'`:
```php
if ($_POST['username'] === ADMIN_USER && $_POST['password'] === 'Aptx4869&$') {
```
**Impact.** Anyone with read access to this file has the admin password. Git history makes the password permanent unless rewritten. The bcrypt hashing on every request is also wasteful (~100 ms cost-10 CPU burn per `/admin/` hit).
**Recommendation.** Store a precomputed bcrypt hash in env (`ADMIN_PASS_HASH=$2y$10$...`), read it with `getenv()`, verify with `password_verify($_POST['password'], $hash)`, and use `hash_equals()` for the username compare. Remove `'Aptx4869&$'` from source and rotate the password.

### F-002 · Critical · `sites/opc.bitco.link/public/admin/api/{create_db,drop_db,add_site,manage_user,save_php,save_fpm}.php` and `templates/{sites,database,dashboard}.php` — MariaDB root password hardcoded in 10 files

**What.** Every admin script that talks to MariaDB does:
```php
shell_exec("docker exec cid-mariadb mysql -uroot -pCidMariaDB2026! -e …")
```
And `templates/database.php:183` literally renders `<code>CidMariaDB2026!</code>` in the admin UI as "Root Password".
**Impact.** (a) Credential is checked into git. (b) If `.env` overrides `MYSQL_ROOT_PASSWORD`, every admin DB feature is broken (mysql auth fails). So either (1) the prod root password is the in-repo value, or (2) the admin panel is non-functional in prod. (c) Any admin screenshot leaks the root password.
**Recommendation.** Load root password from env (`getenv('MYSQL_ROOT_PASSWORD')`) at request time. Never echo it to HTML. Centralize the mysql-exec wrapper in one file. Rotate the password after the audit.

### F-003 · Critical · `sites/opc2.bitco.link/public/api/config.php:45` — Survey DB password hardcoded in webroot

**What.**
```php
$_CONFIG['psw'] = "31l1SwBuXvyqYLdKwUZLbC1qCTJw0C8e";
```
…and the user (`dw_spy`) and DB (`dw_cressida`) are similarly hardcoded.
**Impact.** Credential leakage via repo / SFTP read. The `dw_spy` user has `ALL PRIVILEGES` on `dw_cressida`, so this is full survey-DB compromise.
**Recommendation.** Move to env; `getenv()` at runtime; remove from source and rotate. While there, also remove the dead `if(!in_array($_SERVER['REMOTE_ADDR'], ...))` branch on lines 40-46 — it's confusingly written and leaves `$_CONFIG['host']` undefined for localhost requests (see F-019).

### F-004 · Critical · `docker/database/users_grants.sql` — MySQL native-password hashes committed to repo

**What.** This file (12 lines, committed) contains:
```
GRANT USAGE ON *.* TO `dw_spy`@`%` IDENTIFIED BY PASSWORD '*198A3DAF…';
GRANT USAGE ON *.* TO `opc_user`@`%` IDENTIFIED BY PASSWORD '*3DB2E221…';
GRANT USAGE ON *.* TO `test`@`%` IDENTIFIED BY PASSWORD '*72963D88…';
```
The `*` prefix means `mysql_native_password` = `SHA1(SHA1(password))`. These are crackable on commodity GPU hardware in minutes for typical passwords.
**Impact.** Anyone with repo read access can offline-crack the hashes. Combined with F-003 the `dw_spy` hash corresponds to the cleartext already in source — i.e., it's redundantly leaked.
**Recommendation.** Remove from git history (BFG or `git filter-repo`). Distribute user grants out-of-band (encrypted, password manager, vault). Add `docker/database/` to `.gitignore` or at least exclude the grants file. Rotate all three passwords.

### F-005 · Critical · `docker-compose.yml:99-105` — SFTP container ships a default password committed to git

**What.**
```yaml
USER_NAME=webmaster
USER_PASSWORD=CidSFTP2026!
PASSWORD_ACCESS=true
```
And the SFTP container bind-mounts `../sites:/config/sites` — i.e., the SFTP user has read/write access to **every site's source**, including `admin/index.php`, `admin/api/*`, and `api/config.php`.
**Impact.** Anyone with the in-repo SFTP password gets a writable shell on every site. They can then modify `admin/api/container_action.php` to lift the allow-list, or modify any admin endpoint to ship credentials back, or simply read `index.php` and `config.php` for the rest of the passwords. Public-internet SFTP at `:2222`. The container also runs as host UID 1000, so files created via SFTP are owned by the host `bitcodata` user.
**Recommendation.** Move `USER_PASSWORD` to `.env`, prefer key-based auth (`PASSWORD_ACCESS=false`, `PUBLIC_KEY_DIR=…`), restrict the bind mount to specific sites that need SFTP rather than the whole `sites/` tree. Rotate.

### F-006 · Critical · `docker-compose.yml:13` and admin API — Docker socket is the ambient trust boundary

**What.** The php74 container mounts `/var/run/docker.sock`, runs the Docker CLI, and the admin API does `docker exec cid-mariadb …`, `docker restart …`, `docker run --rm --network=host …`. Any RCE inside PHP (e.g., via F-008, F-013, F-018, F-027, etc.) yields full Docker control — equivalent to host root.
**Impact.** The webserver is the Docker host. Privilege escalation chain from any auth-bypass or injection is one step long.
**Recommendation.** Two practical options:
  (a) Accept the risk explicitly, but treat **any** admin endpoint as a privileged surface and protect it (see F-016 CSRF, F-015 throttling, mTLS, IP allow-list at Caddy, etc.). Stop adding shell execution beyond what's strictly needed.
  (b) Better: move Docker control to a small sidecar/daemon that exposes a minimal API over a unix socket, and have PHP talk to that. Sidecar uses the socket; PHP never does.
At minimum, drop the socket from `php74` for the survey-app FPM workers (split the FPM pool so only the admin site can use Docker, or run admin in its own container).

### F-007 · Critical · `sites/opc.bitco.link/public/admin/api/add_site.php:115-118, 126-127` — Caddy admin API reachable from any container via `--network=host`

**What.**
```php
$cmd = "docker run --rm --network=host curlimages/curl:latest " .
       "-X POST http://127.0.0.1:2019/config/apps/http/servers/srv0/routes ...";
```
The Caddy admin endpoint is open on the host's loopback. Any container can join host networking via the mounted Docker socket and reconfigure all of Caddy.
**Impact.** Combined with F-006: a compromised PHP worker can not only run Docker but also rewrite the public TLS frontend — point any domain anywhere, install new certs, downgrade TLS, expose internal services to the internet.
**Recommendation.** Bind Caddy's admin API to a unix socket or to a private Docker network the php74 container is *not* on. Authenticate the Caddy admin endpoint. Or treat F-007 as a corollary of F-006 and fix at that level.

### F-008 · Critical · `sites/opc2.bitco.link/public/index.php:389, 390, 401` — SQL injection in survey UPDATE statements

**What.** Despite using `$pdo->prepare()`, three update queries build the SQL by string concatenation **before** prepare:
```php
$sql = 'UPDATE '.$tbl.' SET `lastq`="'.$STEP.'",`lastlogic`="'.$ROUTING[$STEP].'" WHERE `idqr`="'.$id.'" ;';
$result = $pdo->prepare($sql);
$result->execute();
```
`$STEP` is `$_REQUEST['qstep']` (line 43), `$id` is `$_GET['id']` (line 51), `$hist` (line 390) and `$ROUTING[$STEP]` derive from the same inputs. `prepare()` does nothing here — it just sends the literal pre-injected SQL.
**Impact.** Full SQL injection as the `dw_spy` user. That user has `ALL PRIVILEGES` on `dw_cressida` per F-004 — so the entire survey database (responses, IDs, anything) can be read or destroyed by any unauthenticated request to `index.php?id=…&qstep=…`. Survey app is public.
**Recommendation.** Rewrite as parameterized: `'UPDATE survey_main SET lastq=:step, lastlogic=:logic WHERE idqr=:id'` with `[':step'=>$STEP, ':logic'=>$ROUTING[$STEP], ':id'=>$id]`. Same for lines 390 and 401. Also validate `$STEP` against `array_keys($ROUTING)` to constrain it to known steps.

---

### F-009 · High · `docker/php/conf/php-custom.ini:17-18` — `display_errors = On` in shared config (dev mode leaked to prod)

**What.**
```ini
display_errors = On
display_startup_errors = On
```
Comment says "dev mode — display_errors ON per boss request". Same setting echoed at `www.conf:25` (`php_admin_value['display_errors'] = 1`).
**Impact.** Stack traces, file paths, SQL errors, and credential-bearing values can leak to anyone who can trigger an error. F-008 SQLi attempts that fail will return raw mysql errors that aid the attacker.
**Recommendation.** `display_errors = Off`, keep `log_errors = On`. If a developer wants errors in-browser, restrict by `auto_prepend_file` to check `$_SERVER['REMOTE_ADDR']` against an allow-list.

### F-010 · High · `sites/opc.bitco.link/public/admin/api/save_php.php:58-66` — Endpoint silently fails to update PHP config

**What.** The script writes the new ini to:
  (1) `/var/www/sites/_config/php-custom.ini` — **not the file PHP reads.**
  (2) `dirname(__DIR__, 4) . '/../docker/php/conf/php-custom.ini'`, which from `/var/www/sites/opc.bitco.link/public/admin/api` resolves to `/var/docker/php/conf/php-custom.ini` — **doesn't exist inside the container.**
The actual file PHP reads is mounted read-only at `/usr/local/etc/php/conf.d/99-custom.ini` (compose line 11), and is never written. Then the script restarts FPM and reports success.
**Impact.** Every "save PHP setting" action from the dashboard is a no-op. The user sees a green toast but nothing changes. Could mask real bugs (e.g., admin thinks they hardened `disable_functions`, attacker still has access).
**Recommendation.** Either (a) write directly to the host-mounted path via the bind mount (mount `docker/php/conf/` writable into `cid-php74`), or (b) follow the `save_fpm.php` model and `docker cp` into the container — but read-write, not mounted `:ro`. Whichever you pick, verify by reading the live `ini_get()` after restart.

### F-011 · High · `sites/opc.bitco.link/public/admin/api/save_fpm.php:70` — `docker cp` into a read-only bind-mounted path

**What.**
```php
shell_exec("docker cp $tmpFile cid-php74:/usr/local/etc/php-fpm.d/www.conf 2>&1");
```
But `www.conf` is mounted from the host as **read-only** (compose line 12: `./php/conf/www.conf:/usr/local/etc/php-fpm.d/www.conf:ro`). `docker cp` to a read-only mount will fail. Output is captured but ignored on line 70; line 76 reports "ok" regardless.
**Impact.** Same as F-010: silent no-op, dashboard lies about success.
**Recommendation.** Write to the host bind-mount source path (`docker/php/conf/www.conf`) directly, or change the mount to read-write. Check the `docker cp` return code and surface failures.

### F-012 · High · `sites/opc.bitco.link/public/admin/api/create_db.php:30`, `add_site.php:115`, `manage_user.php:45, 67` — SQL injection via MySQL user password

**What.** Several endpoints accept a `password` from the admin and interpolate it directly into SQL:
```php
$sql2 = "CREATE USER IF NOT EXISTS '$user'@'%' IDENTIFIED BY '$pass';";
```
`escapeshellarg` only protects the *shell*. SQL is still concatenated.
**Impact.** An admin (or anyone with admin session — see F-016 CSRF) can supply `$pass = "x'; DROP USER 'root'@'localhost'; --"` and execute arbitrary SQL as MariaDB root. Authenticated, but the box-shaped escalation matters because the admin user otherwise can't drop the root user.
**Recommendation.** Reject any password containing `'`, `"`, `\`, `;`, newline, or NUL. Better, base64-encode the password before embedding (then `IDENTIFIED BY PASSWORD '<hex>'`, computing the hash yourself). Best, talk to MySQL via PHP's `mysqli` over the docker network instead of shelling out — then use prepared statements where applicable (note: `CREATE USER` doesn't support binds for password, so still need an allow-list regex on `$pass`).

### F-013 · High · `sites/opc.bitco.link/public/admin/api/add_site.php:126` — SQL identifier injection via `existing-user` host

**What.**
```php
$parts = explode('@', $userName);
$existUser = $parts[0];
$existHost = $parts[1] ?? '%';
$sql = "GRANT ALL PRIVILEGES ON `$safeDbName`.* TO '$existUser'@'$existHost'; FLUSH PRIVILEGES;";
```
Neither `$existUser` nor `$existHost` is filtered (unlike the "new user" branch on line 113). A `userName` of `attacker'@'%' WITH GRANT OPTION ON *.* TO 'x'@'%'; --` becomes part of the GRANT statement.
**Impact.** Authenticated SQL injection as MariaDB root. Admin can self-escalate granted privileges across the whole instance.
**Recommendation.** Apply `safeUser()` / `safeHost()` from `manage_user.php` (lines 15-16) to both halves. Reuse that helper across endpoints — currently it lives only in `manage_user.php`.

### F-014 · High · `sites/opc.bitco.link/public/admin/api/save_php.php:47` — INI directive injection via setting values

**What.**
```php
$ini .= "$key = {$settings[$key]}\n";
```
The key is allow-listed (line 21-29) but the value is not validated. A value like `256M\ndisable_functions =\nopen_basedir =` injects new directives that disable PHP hardening.
**Impact.** Authenticated bypass of `disable_functions` and `open_basedir`. The admin who can save settings can already restart FPM, but combined with F-006 this expands what an admin-session-hijacker can do.
**Recommendation.** Per-key regex validation (numerics, sizes like `\d+[KMG]?`, on/off, named values). Reject any value containing `\n`, `\r`, `;`, `[`, `]`. Re-emit only the recognized parsed form, never the raw input.

### F-015 · High · `docker/nginx/nginx.conf:48` + `docker/nginx/conf.d/opc.bitco.link.conf` — `login` rate-limit zone is declared but never applied

**What.** `nginx.conf` defines `limit_req_zone … zone=login:10m rate=3r/s;`. The per-site vhost only applies `limit_req zone=general burst=20 nodelay;`. Nothing references the `login` zone. The admin login POSTs to `/admin/` itself, sharing the general bucket (~36k req/hr from a single IP).
**Impact.** Brute force the admin login URL at general-zone rates. With F-001 already breaking auth this is moot, but once F-001 is fixed this becomes the next line of defense.
**Recommendation.** Add a `location = /admin/` block with `limit_req zone=login burst=5 nodelay` *conditional on POST*, or use `map $request_method` to pick the zone. Consider also adding a fail2ban-style block on repeated 401s.

### F-016 · High · all admin API endpoints — No CSRF tokens; soft defense via JSON-only handlers

**What.** Every endpoint reads `php://input` and `json_decode`s it. There are no CSRF tokens. The implicit defense is that a cross-origin form POST can't set `Content-Type: application/json`, so the body wouldn't parse — but with `simple-request` form encoding plus `text/plain`, a malicious page can deliver a JSON-shaped body that PHP will accept after json_decode.
**Impact.** A logged-in admin visiting a malicious page (or even an XSS in the survey app sharing the parent domain) can be made to issue `add_site.php` / `drop_db.php` / `container_action.php` requests.
**Recommendation.** Issue a CSRF token in a cookie or in the `/admin/` HTML, require it as an `X-CSRF-Token` header on every API POST, and verify with `hash_equals`. Also set `SameSite=Strict` on the session cookie (currently `Lax`).

### F-017 · High · `sites/opc2.bitco.link/public/index.php:285-300` — Mass-assignment via `$_POST` keys driving column names

**What.** The UPDATE in the survey controller is built by iterating over `$_POST`:
```php
foreach($POST as $k => $v){
    if(!in_array($k, array("qid","qstep","button"))){
        $upsql .= '`'.$k.'`=:'.$k.' ';
        $upparam[':'.$k] = $v;
    }
}
```
Any POST key (except the three blacklisted) becomes a writable column on `survey_main`.
**Impact.** Respondents can set arbitrary columns — including `status`, `idqr`, `enddate`, `lastq`, `hist` — bypassing quotas, marking themselves complete, overwriting other respondents' IDs (combined with F-008's `id` injection), or corrupting routing state.
**Recommendation.** Convert from blacklist to allow-list: define the set of columns each step may touch (probably in `metadata1.php`), and accept only those keys. Throw away unknown keys silently.

---

### F-018 · High · `sites/opc2.bitco.link/public/insertcase.php` — Unauthenticated data-seeder web-exposed

**What.** This is a one-shot DB seeder that creates rows from `from` to `to` (defaults 1001–1336). It's in the public webroot, has no auth, and accepts `from`/`to` from `$_GET`:
```php
if(!empty($_GET['from'])) $from = $_GET['from'];
…
for($i=$from;$i<=$to;$i++){
    $sqlsyntax[] = "INSERT INTO survey_main (idqr, status, …) VALUES ('{$i}', 'FRESH', …);";
}
```
**Impact.** Anyone can hit `https://opc2.bitco.link/insertcase.php?from=1&to=999999` and force the app to build a billion-statement SQL string → DB exhaustion / DoS. Even default invocation creates 336 rows in `survey_main` and clobbers existing IDs in that range. The script also contains tens of thousands of pre-seeded INSERTs that run on every request.
**Recommendation.** Move out of webroot (CLI-only). If it must be webroot, gate behind `if (PHP_SAPI === 'cli')`. At minimum require a session check or a one-time token in env. Coerce `from`/`to` to ints with bounds. This script likely should be deleted entirely after initial setup.

### F-019 · Medium · `sites/opc2.bitco.link/public/api/config.php:39-46` — Localhost branch leaves `$_CONFIG` undefined

**What.**
```php
if(!in_array($_SERVER['REMOTE_ADDR'], $_CONFIG['whitelist'])){
    $_CONFIG['host'] = "localhost";
    $_CONFIG['db'] = "dw_cressida";
    …
}
```
There is no `else`. If the request *is* from localhost (e.g., a local curl, healthcheck, or cron), `$_CONFIG['host']` etc. are never set, and the PDO line in `index.php`/`insertcase.php` dies on `Cannot connect mySQL`.
**Impact.** Functional bug: any local-origin request breaks. Also the comment "Check if not localhost" suggests the developer intended different creds per environment but only wrote one half.
**Recommendation.** Either remove the conditional (always set the config) or add the `else` branch with the appropriate localhost creds, depending on intent. While there, set host to `mariadb` (the service name) rather than `localhost` — they're different inside Docker.

### F-020 · Medium · `sites/opc.bitco.link/public/admin/index.php:12-22` — No `session_regenerate_id()` after login

**What.** After successful auth, `$_SESSION['authenticated'] = true` is set against the *existing* session ID. Combined with `session.use_strict_mode = 1` this is partially mitigated, but session fixation is still feasible if an attacker can set a victim's session cookie before login (e.g., via XSS in the survey app on a sibling domain that shares cookies — depends on Caddy host config).
**Impact.** Session fixation under the right preconditions.
**Recommendation.** `session_regenerate_id(true);` immediately after the credential check passes.

### F-021 · Medium · `sites/opc.bitco.link/public/admin/index.php:14` — Non-constant-time password compare

**What.** Plain `===` for password comparison.
**Impact.** Theoretical timing side-channel. Low practical risk given the password is in source (F-001), but worth fixing alongside F-001.
**Recommendation.** Use `hash_equals(ADMIN_PASS_HASH_FROM_ENV, …)` against a `password_verify` result or against a bcrypt hash directly.

### F-022 · Medium · `docker/php/conf/php-custom.ini:32` — `session.cookie_secure = 0` while site is served over HTTPS

**What.** Caddy terminates TLS at the host edge; cookies are transported over HTTPS from clients but PHP sends `Set-Cookie` without the `Secure` flag.
**Impact.** If a request ever reaches the app over plain HTTP (e.g., misconfiguration, internal probe via SSH tunnel, or a non-HTTPS subdomain pointed at the same Caddy), the cookie is sent in clear.
**Recommendation.** `session.cookie_secure = 1`. Ensure Caddy redirects HTTP→HTTPS so this doesn't break local dev.

### F-023 · Medium · `docker/php/conf/www.conf:5` — FPM listens on `0.0.0.0:9000` inside the cid-network bridge

**What.** Any container on `cid-network` can talk directly to PHP-FPM, bypassing nginx. That includes `cid-sftp` (which has a public port and a known password — see F-005).
**Impact.** Pre-auth code execution from a peer container if an attacker chains through SFTP or any future container added to the network.
**Recommendation.** Either bind FPM to a unix socket (and mount it into the nginx container), or listen only on `127.0.0.1:9000` and run nginx in the same container/pod. If keeping TCP, restrict via iptables on the bridge.

### F-024 · Medium · `sites/opc2.bitco.link/public/status.php:41, ~150` — Hardcoded long-lived API tokens

**What.** Two tokens in source:
- `rDhs2NpYw2nrTyzq` — gates access to the FW-progress dashboard.
- `1D7A6E11085351298CC890625C16D68ABD6C85125C513FDE614B0B49533BF2A7` — embedded in the rendered HTML on the rawdata download link, gating `xls/exportxls.php`.
Both compared with `!=` (non-constant-time).
**Impact.** Anyone with repo read access (or who fetches `status.php` with the right `token` and `type`) can download survey rawdata.
**Recommendation.** Move tokens to env, rotate them, scope them per-action, and use `hash_equals`. Consider that this is a downloadable dataset — if it's sensitive, the gating model needs more than a static token.

### F-025 · Medium · `sites/opc2.bitco.link/public/status.php` (~`{$TYPE}` interpolation; `gengrid`) — Reflected XSS

**What.** `$type` from `$_GET['type']` is uppercased and emitted directly into HTML:
```php
$TYPE = strtoupper($type);
$output = "<div id='proj-title'>FW Progress - {$TYPE}</div>";
```
`gengrid()` and `genDaily()` similarly inject values from the upstream JSON into HTML without `htmlspecialchars`.
**Impact.** XSS, gated only by the static token (F-024 — token is in source). Could pivot to session theft on `opc2.bitco.link` and lead into survey-app abuse (or, depending on cookie scoping, the admin session on the sibling domain).
**Recommendation.** `htmlspecialchars()` on every value emitted to HTML. Set `Content-Security-Policy` headers.

### F-026 · Medium · `sites/opc.bitco.link/public/admin/api/add_site.php:41-42` — Newly created site directories are world-writable

**What.** After `mkdir($siteDir/public, 0755)`, the code does `@chmod($siteDir, 0777); @chmod("$siteDir/public", 0777);`.
**Impact.** Any user in the container — including the SFTP user (F-005) and any future low-privilege container that mounts `sites/` — can write to or replace files in the newly created site.
**Recommendation.** Drop the `chmod 0777`. The container's php worker runs as `www-data`; if `www-data` needs write access, `chown -R www-data:www-data` and use `0755`/`0644`. Don't rely on world-writable as a workaround for ownership.

### F-027 · Medium · `sites/opc.bitco.link/public/admin/api/add_site.php:46` — Generated `index.php` can execute crafted label content (limited)

**What.** The label is passed through `htmlspecialchars()` and then interpolated into a PHP double-quoted string written to a `.php` file:
```php
$indexContent = "<?php\necho \"<h1>$escapedLabel</h1>\";\n…";
```
`htmlspecialchars` escapes `"` and `<` etc., which blocks the obvious quote break-out. But `$`, `\\`, and `{}` are not escaped — PHP double-quoted strings interpret `${var}` and `{${"..."}}` constructs. Exploiting to a clean RCE is limited (no direct function calls in `${}` in 7.4), but the design is fragile.
**Impact.** Hard-to-clean injection into a generated PHP file by an authenticated admin. Combined with F-016 CSRF, a victim admin could be tricked into generating a backdoor.
**Recommendation.** Don't interpolate user input into PHP source. Write a templated static HTML/PHP file and put the label into a JSON file alongside it that the index reads at runtime. Or just write a plain HTML file, no PHP.

---

### F-028 · Low · `sites/opc2.bitco.link/public/index.php.bak.test` — PHP source served as plaintext

**What.** Backup file in webroot with extension `.test`. The vhost deny rule only matches a fixed extension list (`env|git|htaccess|htpasswd|ini|log|sh|sql|bak|conf`) so `.test` isn't denied. Nginx serves the file as text/plain, exposing the PHP source.
**Impact.** Source leak. Current content is trivial (a 140-byte test page), but the *pattern* will bite when someone leaves `index.php.bak.old` or similar.
**Recommendation.** Remove the backup file. Update the deny rule to also catch `.bak.*`, `.old`, `.orig`, `.test`, `~` suffixes. Better: deny everything not explicitly allowed.

### F-029 · Low · `sites/opc.bitco.link/public/admin/data/metrics.json` — Web-accessible historical metrics

**What.** `data/metrics.json` lives under the webroot and the vhost deny rules don't cover `.json`. Anyone can `GET /admin/data/metrics.json` and read 7 days of CPU/mem/disk samples.
**Impact.** Information disclosure (server load patterns; helps an attacker time DoS). Low severity, but unnecessary.
**Recommendation.** Either add a `location ^~ /admin/data/ { deny all; }` block (place above the PHP handler), or move `data/` outside the webroot and have the dashboard read it from a non-served path. Update `collect_metrics.sh` accordingly.

### F-030 · Low · `sites/opc.bitco.link/public/admin/api/server_info.php:8` and `add_site.php:189, 193` — Production server IP hardcoded

**What.** `'139.59.119.101'` appears in three places.
**Impact.** Server identity tied to source; moving servers requires code changes; also leaked in `add_site.php` response messages to whoever requested the action.
**Recommendation.** `$_SERVER['SERVER_ADDR']` or env. Or remove from response payloads entirely and let DNS handle the announcement.

### F-031 · Low · `sites/opc2.bitco.link/public/test.php` — Dev-only file in webroot emits PHP notice

**What.** Contents: `<?php echo asdfasf; ?>`. With `display_errors = On` (F-009), hitting `/test.php` returns a PHP notice + PHP version banner.
**Impact.** Minor info leak; sign of forgotten dev files.
**Recommendation.** Remove. Sweep webroots for similar leftovers (`test.php`, `info.php`, `phpinfo.php`).

### F-032 · Info · PHP 7.4 is end-of-life (since 2022-11-28)

**What.** No upstream security patches for over three years. The base image `php:7.4-fpm-bullseye` will keep working but receive no PHP-level CVE fixes.
**Impact.** Cumulative drift; specific CVEs in 7.4-only code paths won't be backported.
**Recommendation.** Plan a 7.4 → 8.2 (or 8.3) migration as a separate work item. The survey app uses 7.4-isms (notice suppression patterns, lax type juggling) that will need attention.

### F-033 · Info · `sites/opc2.bitco.link/public/xls/` bundles PHPExcel (deprecated 2017)

**What.** PHPExcel was superseded by PhpSpreadsheet years ago and no longer receives fixes. The library has historical CVEs (XXE in older versions of XML parsing).
**Impact.** Latent risk if the export path ever parses untrusted spreadsheets. Current usage looks like export-only (writing xlsx for download), so impact is mostly forward-looking.
**Recommendation.** Schedule a migration to PhpSpreadsheet (`composer require phpoffice/phpspreadsheet`). Same API shape, mostly drop-in.

---

## Notes on what was *not* findings

A few items the plan flagged that turned out to be OK:

- **`container_action.php` allow-list** is correctly enforced *before* the shell exec; container names are equality-compared, so no shell-metacharacter risk from that input.
- **`drop_db.php`** correctly uses a strict regex on the DB name and blocks system schemas. (It doesn't block application DBs, but that's a UX/operational concern, not a security one.)
- **`site_config.php`** domain regex is tight enough to prevent path traversal.
- **`add_site.php` `safeDbName`** sanitizes with `preg_replace`, and `$domain` is regex-validated before any file writes — so the nginx vhost path and the DB name itself are safe.
- **`save_fpm.php`** numeric validation (int cast, bounds, regex for `idle_timeout`, allow-list for `pm` mode) is well done — the bug is purely the read-only mount issue (F-011).
- **`checkval.php:35`** does use a properly parameterized SELECT — good example of how the survey app *should* look everywhere.

---

## Items deferred for a follow-up engagement

- Full review of `routing1.php` / `task1.php` / `phpfunc.php` logic (large files; static analysis would help).
- Contents of `docker/database/all_databases.sql` (7.5 MB) — what PII does it contain? If it has real respondent data, F-004 escalates.
- The host-level Caddy configuration (outside this repo).
- PHPExcel internals.
