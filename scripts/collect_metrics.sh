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
METRICS_FILE="${METRICS_FILE:-$SCRIPT_DIR/../sites/opc.bitco.link/public/admin/data/metrics.json}"
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
