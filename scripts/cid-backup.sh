#!/bin/bash
# ===================================================================
# ศ.Cid backup script
#
# Backs up:
#   - MariaDB (mysqldump --all-databases, streamed via docker exec)
#   - /home/bitcodata/phpserver/sites/  (web roots)
#   - /home/bitcodata/phpserver/docker/.env  (secrets needed for restore)
#
# To: a restic repository (default: Backblaze B2). Encryption is
# client-side; the repo password lives in /etc/cid-backup.env.
#
# Retention: keep last 7 daily, 4 weekly, 6 monthly snapshots.
#
# Cron entry (separate file):
#   /etc/cron.d/cid-backup
#     0 2 * * * root /usr/local/bin/cid-backup >> /var/log/cid-backup.log 2>&1
#
# Restore:
#   source /etc/cid-backup.env
#   restic snapshots
#   restic restore <snapshot-id> --target /tmp/restore
#   # DB dump is at /tmp/restore/mysql-dump.sql.gz
#   # site tree   is at /tmp/restore/home/bitcodata/phpserver/sites/
# ===================================================================

set -euo pipefail

REPO_ROOT="/home/bitcodata/phpserver"
ENV_FILE="/etc/cid-backup.env"

if [[ ! -r "$ENV_FILE" ]]; then
    echo "FATAL: $ENV_FILE not readable. See scripts/cid-backup-setup.md." >&2
    exit 1
fi
# shellcheck disable=SC1090
source "$ENV_FILE"

# Required vars from the env file:
: "${RESTIC_REPOSITORY:?RESTIC_REPOSITORY not set in $ENV_FILE}"
: "${RESTIC_PASSWORD:?RESTIC_PASSWORD not set in $ENV_FILE}"
# For B2: B2_ACCOUNT_ID and B2_ACCOUNT_KEY (or AWS_ACCESS_KEY_ID/AWS_SECRET_ACCESS_KEY for S3).
export RESTIC_REPOSITORY RESTIC_PASSWORD
[[ -n "${B2_ACCOUNT_ID:-}" ]]  && export B2_ACCOUNT_ID
[[ -n "${B2_ACCOUNT_KEY:-}" ]] && export B2_ACCOUNT_KEY
[[ -n "${AWS_ACCESS_KEY_ID:-}" ]]     && export AWS_ACCESS_KEY_ID
[[ -n "${AWS_SECRET_ACCESS_KEY:-}" ]] && export AWS_SECRET_ACCESS_KEY

ts() { date -u '+%Y-%m-%dT%H:%M:%SZ'; }
log() { printf '[%s] %s\n' "$(ts)" "$*"; }

trap 'log "BACKUP FAILED at line $LINENO (exit $?)"' ERR

log "starting backup run"

# --- 1. ensure the repo is initialised (idempotent) ---
if ! restic snapshots --no-lock --quiet >/dev/null 2>&1; then
    log "initialising new restic repository at $RESTIC_REPOSITORY"
    restic init
fi

# --- 2. take the DB dump and stream it into restic ---
log "dumping MariaDB via cid-mariadb"
DUMP_ROOT_PW=$(grep '^MYSQL_ROOT_PASSWORD=' "$REPO_ROOT/docker/.env" | cut -d= -f2-)
if [[ -z "$DUMP_ROOT_PW" ]]; then
    log "could not read MYSQL_ROOT_PASSWORD from $REPO_ROOT/docker/.env"
    exit 1
fi

# Use --stdin so restic stores the dump as one logical file inside the snapshot
# at /mysql-dump.sql.gz. mysqldump streams into gzip and into restic.
docker exec -e "MYSQL_PWD=$DUMP_ROOT_PW" cid-mariadb \
    mysqldump -uroot --all-databases --single-transaction --quick --routines --triggers --events 2>/dev/null \
    | gzip -c \
    | restic backup --stdin --stdin-filename mysql-dump.sql.gz --tag db --host cid

# --- 3. snapshot the site tree + .env ---
log "snapshotting sites/ and docker/.env"
restic backup \
    --tag files --host cid \
    --exclude="$REPO_ROOT/docker/logs" \
    --exclude='*/.git' \
    --exclude='*/.openclaw' \
    --exclude='*/node_modules' \
    "$REPO_ROOT/sites" \
    "$REPO_ROOT/docker/.env"

# --- 4. apply retention policy ---
log "applying retention (7d / 4w / 6m)"
restic forget --prune \
    --keep-daily 7 \
    --keep-weekly 4 \
    --keep-monthly 6

# --- 5. integrity check (cheap variant: just metadata; full once a week) ---
DAY=$(date +%u)
if [[ "$DAY" == "7" ]]; then
    log "weekly: running full data check (this can take a while)"
    restic check --read-data-subset=10%
else
    log "running quick structural check"
    restic check --no-lock
fi

log "backup run complete"
