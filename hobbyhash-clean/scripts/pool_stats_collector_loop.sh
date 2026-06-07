#!/usr/bin/env bash
set -euo pipefail

collector="/home/hobbyhashcoin/hobbyhash-clean/scripts/pool_stats_collector.py"
log="/home/hobbyhashcoin/hobbyhash-clean/wallet/logs/pool_stats_collector.cron.log"
interval_seconds=2
run_seconds=56
end_at=$((SECONDS + run_seconds))

while (( SECONDS < end_at )); do
  /usr/bin/python3 "$collector" --pool all >> "$log" 2>&1 || true
  remaining=$((end_at - SECONDS))
  if (( remaining <= interval_seconds )); then
    break
  fi
  sleep "$interval_seconds"
done
