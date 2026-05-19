#!/bin/bash
# Metrics collector for ศ.Cid Dashboard
# Runs every 5 minutes via cron, appends to JSON file.
#
# Install:
#   crontab -e
#   */5 * * * * /home/bitcodata/phpserver/scripts/collect_metrics.sh
#
# Override METRICS_FILE in the env if the repo lives elsewhere.

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
# Pick the most-recently-modified non-template site folder under sites/.
# During a rename migration, the new folder is freshly mtimed (cp -r
# stamps it) and wins over the old one; in steady state with one site,
# there's only one option anyway. Folder mtime is stable because nested
# file writes don't propagate up. Override via METRICS_FILE env if a
# host runs multiple non-template sites and this heuristic doesn't fit.
SITE_DIR="$(find "$SCRIPT_DIR/../sites" -maxdepth 1 -mindepth 1 -type d \
            ! -name '_*' -printf '%T@ %p\n' | sort -rn | head -1 \
            | cut -d' ' -f2-)"
METRICS_FILE="${METRICS_FILE:-$SITE_DIR/public/admin/data/metrics.json}"
mkdir -p "$(dirname "$METRICS_FILE")"

# Collect metrics
TIMESTAMP=$(date +%s)
LOAD=$(cat /proc/loadavg | awk '{print $1}')
MEM_TOTAL=$(free -b | awk '/Mem:/{print $2}')
MEM_USED=$(free -b | awk '/Mem:/{print $3}')
DISK_TOTAL=$(df -B1 / | awk 'NR==2{print $2}')
DISK_USED=$(df -B1 / | awk 'NR==2{print $3}')
CPU_CORES=$(nproc)
UPTIME_SEC=$(cat /proc/uptime | awk '{print int($1)}')

# Build JSON entry
ENTRY="{\"ts\":$TIMESTAMP,\"load\":$LOAD,\"mem_total\":$MEM_TOTAL,\"mem_used\":$MEM_USED,\"disk_total\":$DISK_TOTAL,\"disk_used\":$DISK_USED,\"cpu_cores\":$CPU_CORES,\"uptime\":$UPTIME_SEC}"

# Append to file (keep last 2016 entries = 7 days at 5min intervals)
if [ -f "$METRICS_FILE" ]; then
    # Read existing, append new, keep last 2016
    python3 -c "
import json, sys
try:
    with open('$METRICS_FILE') as f:
        data = json.load(f)
except:
    data = []
data.append($ENTRY)
data = data[-2016:]
with open('$METRICS_FILE', 'w') as f:
    json.dump(data, f)
"
else
    echo "[$ENTRY]" > "$METRICS_FILE"
fi
