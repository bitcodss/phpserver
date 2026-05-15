# ศ.Cid backup — setup & operations

Encrypted, deduplicated, incremental backups via **restic** → **Backblaze B2**.
Nightly cron at 02:00 Asia/Bangkok. Retention: 7 daily / 4 weekly / 6 monthly.

## What's already installed on this host

| Item | Path | Purpose |
|---|---|---|
| restic binary | `/usr/bin/restic` (apt, v0.16.4) | the backup tool |
| backup script | `/usr/local/bin/cid-backup` | runs the dump + snapshot |
| script source | `scripts/cid-backup.sh` | in repo, the canonical version |
| env template | `scripts/cid-backup.env.example` | in repo, no secrets |
| env (real) | `/etc/cid-backup.env` (0600 root) | host-only, holds passphrase + B2 keys |
| cron entry | `/etc/cron.d/cid-backup` | 02:00 Asia/Bangkok daily |
| log | `/var/log/cid-backup.log` | rotated weekly, keep 12 |
| logrotate cfg | `/etc/logrotate.d/cid-backup` | |

Restic encryption passphrase has been **generated and stored** in
`/etc/cid-backup.env` already. Take a moment now to copy it into your
password manager before continuing — if the host disk dies and you don't
have it, every offsite backup becomes unrecoverable.

```bash
sudo grep ^RESTIC_PASSWORD /etc/cid-backup.env
```

## Step 1 — Create the Backblaze B2 account

1. Go to <https://www.backblaze.com/b2/sign-up.html> and sign up.
2. Verify your email.
3. **Enable two-factor auth** when prompted — B2 requires it for API access.
   Save the recovery codes somewhere safe.
4. After login, find the **B2 Cloud Storage** section in the left sidebar.

## Step 2 — Create a bucket

In the B2 dashboard → **Buckets** → **Create a Bucket**:

| Setting | Value |
|---|---|
| Bucket Unique Name | `cid-backups-<some-random-suffix>` (must be globally unique) |
| Files in Bucket are | **Private** |
| Default Encryption | Disable (we encrypt client-side with restic) |
| Object Lock | Disable (or Governance if you want WORM immutability — optional) |

Take note of the exact bucket name you typed.

## Step 3 — Create an application key scoped to that bucket

B2 dashboard → **Application Keys** → **Add a New Application Key**:

| Setting | Value |
|---|---|
| Name | `cid-backup-host` |
| Allow access to Bucket(s) | the bucket you just created |
| Type of Access | **Read and Write** |
| Allow List All Bucket Names | leave unchecked (least privilege) |
| File name prefix | leave blank |
| Duration (seconds) | leave blank (= never expires) |

Click **Create New Key**. You'll be shown:

- **keyID** — short, e.g. `0026e...0001`
- **applicationKey** — longer string starting with `K002...`

**This is the only time the applicationKey is shown.** Copy both to your
password manager NOW, then paste them into the host's env file as below.

## Step 4 — Wire the credentials into the host

```bash
sudo nano /etc/cid-backup.env
```

Set:

```
RESTIC_REPOSITORY=b2:<your-bucket-name>:cid
RESTIC_PASSWORD=<keep what's already there — do not change!>
B2_ACCOUNT_ID=<keyID from step 3>
B2_ACCOUNT_KEY=<applicationKey from step 3>
```

Save. The file must stay mode `0600 root:root`.

## Step 5 — First-run init + test

```bash
sudo /usr/local/bin/cid-backup
```

On the first run, restic will:
1. Initialise the encrypted repo at `b2:<bucket>:cid` (one-time).
2. Take the DB dump + file snapshot.
3. Push to B2.
4. Apply retention (no-op on first run — nothing old to prune).
5. Run a structural integrity check.

Expected runtime on this stack: 30s – 2 min, depending on B2 upload speed.
Expected first upload size: ~50–100 MB after dedup.

If anything goes wrong, the output of the run is appended to
`/var/log/cid-backup.log`. Tail it: `sudo tail -100 /var/log/cid-backup.log`.

## Step 6 — Confirm cron will run it nightly

```bash
sudo cat /etc/cron.d/cid-backup
```

You should see one line: `0 19 * * * root /usr/local/bin/cid-backup ...`.
(19:00 UTC = 02:00 Asia/Bangkok.)

Tomorrow morning, check the log:
```bash
sudo tail /var/log/cid-backup.log
```

## Listing & restoring

### List snapshots
```bash
sudo bash -c 'source /etc/cid-backup.env; restic snapshots'
```

Each backup produces TWO snapshots — one tagged `db` (the `mysql-dump.sql.gz`)
and one tagged `files` (the site tree + `docker/.env`).

### Restore the most recent DB dump
```bash
sudo bash -c '
  source /etc/cid-backup.env
  restic restore latest --tag db --target /tmp/restore
'
ls -la /tmp/restore/mysql-dump.sql.gz
zcat /tmp/restore/mysql-dump.sql.gz | docker exec -i cid-mariadb mysql -uroot -p"$(grep ^MYSQL_ROOT_PASSWORD docker/.env | cut -d= -f2-)"
```

### Restore the file tree
```bash
sudo bash -c '
  source /etc/cid-backup.env
  restic restore latest --tag files --target /tmp/restore
'
# Files are under /tmp/restore/home/bitcodata/phpserver/sites/
# Compare against current state before overwriting:
sudo diff -r /tmp/restore/home/bitcodata/phpserver/sites /home/bitcodata/phpserver/sites
```

### Restore a specific snapshot by ID
```bash
sudo bash -c '
  source /etc/cid-backup.env
  restic snapshots                 # find the ID you want
  restic restore <short-id> --target /tmp/restore
'
```

## Disaster recovery (host gone entirely)

You need three things to restore from scratch on a new machine:

1. The B2 `keyID` + `applicationKey` (or you can generate fresh ones from the
   B2 dashboard, scoped to the same bucket — your encrypted backups in B2 are
   accessible with any valid key on the bucket).
2. The bucket name.
3. The `RESTIC_PASSWORD` you stored in your password manager.

With those three, on the new host:

```bash
apt-get install -y restic
export RESTIC_REPOSITORY=b2:<bucket>:cid
export RESTIC_PASSWORD=<from password manager>
export B2_ACCOUNT_ID=<keyID>
export B2_ACCOUNT_KEY=<applicationKey>
restic snapshots                                                  # see your history
restic restore latest --tag files --target /restore-files
restic restore latest --tag db    --target /restore-db
```

## Monitoring

The script logs every run to `/var/log/cid-backup.log`. To surface failures
proactively, wire up one of:

- **Email**: `apt install mailutils`, then edit `/etc/cron.d/cid-backup` to
  add `MAILTO=you@example.com` at the top — cron mails the script's stderr
  on non-zero exit.
- **ntfy / Slack / Discord webhook**: append a `curl` failure callback to
  the script's `trap '... ERR'` line.
- **Healthchecks.io / Cronitor**: free uptime-monitoring; the script GETs a
  unique URL on success; the service alerts you when a "ping" stops arriving.

Pick one when you're ready and I can wire it in.

## Rolling the encryption passphrase

If the host is ever compromised and you have to assume the passphrase leaked:

```bash
sudo bash -c '
  source /etc/cid-backup.env
  restic key add               # interactive — type a new passphrase twice
  restic key list              # see all keys, note the OLD id
  restic key remove <old-id>   # revoke the old one
'
sudo nano /etc/cid-backup.env  # update RESTIC_PASSWORD to the new value
```

Existing backups stay readable; only the way to unlock them rotates.

## Costs

For this stack (~200 MB unique data, ~10 MB daily change):
- Storage in B2: ~$0.005/month
- API calls: <$0.01/month
- **Total: roughly $0.01–0.05/month.**

If you ever do a full restore (rare), egress is metered: B2 gives you 3× your
average storage for free per month, then $0.01/GB. A full restore of 200 MB
is well within the free tier.
