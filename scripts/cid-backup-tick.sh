#!/bin/bash
# cid-backup tick — invoked every 5 min by /etc/cron.d/cid-backup.
# All logic lives in the broker; this is just a thin curl over the unix socket.

set -euo pipefail

SOCK="/var/lib/docker/volumes/docker_broker-socket/_data/broker.sock"
# Most installs will have the broker socket on the docker volume above; if your
# layout is different, set CID_BROKER_SOCK in /etc/cid-backup.env to override.
if [[ -r /etc/cid-backup.env ]]; then
    # shellcheck disable=SC1091
    source /etc/cid-backup.env
fi
if [[ -n "${CID_BROKER_SOCK:-}" ]]; then
    SOCK="$CID_BROKER_SOCK"
fi

if [[ ! -S "$SOCK" ]]; then
    # Try the live docker inspect path as a fallback.
    SOCK=$(docker volume inspect docker_broker-socket --format '{{.Mountpoint}}' 2>/dev/null)/broker.sock || true
fi

if [[ ! -S "$SOCK" ]]; then
    echo "[$(date -u '+%FT%TZ')] tick: broker socket not found at $SOCK" >&2
    exit 1
fi

curl -sS --unix-socket "$SOCK" -X POST \
     -H 'Content-Type: application/json' \
     http://broker/backup/tick
echo
