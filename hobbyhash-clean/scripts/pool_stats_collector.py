#!/usr/bin/env python3
"""Collect public HOBC pool stats from real ckpool logs.

This script does not talk to miners and does not invent missing data. It reads
ckpool sharelog files, pool.status, and payout daemon state, then writes a
sanitized public JSON cache for the website API.
"""

from __future__ import annotations

import argparse
import json
import math
import os
import time
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any


SESSION_GAP_SECONDS = 3 * 60 * 60
WINDOWS = {
    "5m": 5 * 60,
    "30m": 30 * 60,
    "60m": 60 * 60,
    "12h": 12 * 60 * 60,
}


@dataclass
class WorkerStats:
    workername: str
    accepted_shares: int = 0
    rejected_shares: int = 0
    best_share: float = 0.0
    last_share_ts: int = 0
    session_start_ts: int = 0
    session_accepted: int = 0
    session_rejected: int = 0
    session_best_share: float = 0.0
    session_diff_sum: float = 0.0
    session_last_accepted_share: float = 0.0
    session_last_accepted_assigned_diff: float = 0.0
    session_last_accepted_share_ts: int = 0
    session_window_accepted: dict[str, int] = field(default_factory=lambda: {name: 0 for name in WINDOWS})
    session_window_diff_sum: dict[str, float] = field(default_factory=lambda: {name: 0.0 for name in WINDOWS})


@dataclass
class WindowStats:
    seconds: int
    accepted: int = 0
    rejected: int = 0
    best_share: float = 0.0
    diff_sum: float = 0.0


def parse_createdate(value: Any) -> int:
    text = str(value or "")
    if not text:
        return 0
    try:
        return int(float(text.replace(",", ".")))
    except ValueError:
        return 0


def mask_worker(workername: str) -> str:
    address, dot, suffix = workername.partition(".")
    if len(address) > 14:
        address = f"{address[:8]}...{address[-6:]}"
    return f"{address}{dot}{suffix}" if dot else address


def fmt_time(ts: int) -> str:
    if ts <= 0:
        return "not_available"
    return time.strftime("%Y-%m-%dT%H:%M:%S+00:00", time.gmtime(ts))


def reject_percent(accepted: int, rejected: int) -> str:
    total = accepted + rejected
    if total <= 0:
        return "0.0000%"
    return f"{(rejected / total) * 100:.4f}%"


def hashrate_from_diff(diff_sum: float, seconds: int) -> float:
    if diff_sum <= 0 or seconds <= 0:
        return 0.0
    return diff_sum * 4294967296.0 / seconds


def human_hashrate(hps: float) -> str:
    units = ["H/s", "KH/s", "MH/s", "GH/s", "TH/s", "PH/s", "EH/s"]
    value = float(hps)
    idx = 0
    while value >= 1000 and idx < len(units) - 1:
        value /= 1000
        idx += 1
    if idx == 0:
        return f"{value:.2f} {units[idx]}"
    return f"{value:.2f} {units[idx]}"


def read_json(path: Path) -> Any:
    try:
        return json.loads(path.read_text())
    except Exception:
        return None


def iter_share_rows(log_dir: Path):
    for path in log_dir.rglob("*.sharelog"):
        try:
            with path.open("r", encoding="utf-8", errors="ignore") as handle:
                for line in handle:
                    try:
                        row = json.loads(line)
                    except json.JSONDecodeError:
                        continue
                    if isinstance(row, dict) and "result" in row:
                        yield row
        except OSError:
            continue


def collect(pool: str, log_dir: Path, status_file: Path, payout_state_file: Path, output_file: Path) -> dict[str, Any]:
    now = int(time.time())
    workers: dict[str, WorkerStats] = {}
    windows = {name: WindowStats(seconds=seconds) for name, seconds in WINDOWS.items()}
    latest: list[dict[str, Any]] = []
    accepted = 0
    rejected = 0
    best_share = 0.0
    last_share_ts = 0

    for row in iter_share_rows(log_dir):
        workername = str(row.get("workername") or "not_available")
        ts = parse_createdate(row.get("createdate"))
        is_accepted = bool(row.get("result"))
        share_diff = float(row.get("sdiff") or 0.0)
        assigned_diff = float(row.get("diff") or 0.0)
        age = max(0, now - ts) if ts else None

        worker = workers.setdefault(workername, WorkerStats(workername=workername))
        if is_accepted:
            accepted += 1
            worker.accepted_shares += 1
        else:
            rejected += 1
            worker.rejected_shares += 1
        worker.best_share = max(worker.best_share, share_diff)
        best_share = max(best_share, share_diff)

        if ts >= worker.last_share_ts:
            if worker.last_share_ts <= 0 or ts - worker.last_share_ts > SESSION_GAP_SECONDS:
                worker.session_start_ts = ts
                worker.session_accepted = 0
                worker.session_rejected = 0
                worker.session_best_share = 0.0
                worker.session_diff_sum = 0.0
                worker.session_last_accepted_share = 0.0
                worker.session_last_accepted_assigned_diff = 0.0
                worker.session_last_accepted_share_ts = 0
                worker.session_window_accepted = {name: 0 for name in WINDOWS}
                worker.session_window_diff_sum = {name: 0.0 for name in WINDOWS}
            worker.last_share_ts = ts
        if ts >= worker.session_start_ts:
            if is_accepted:
                worker.session_accepted += 1
            else:
                worker.session_rejected += 1
            worker.session_best_share = max(worker.session_best_share, share_diff)
            if is_accepted:
                worker.session_diff_sum += max(share_diff, 0.0)
                if ts >= worker.session_last_accepted_share_ts:
                    worker.session_last_accepted_share_ts = ts
                    worker.session_last_accepted_share = share_diff
                    worker.session_last_accepted_assigned_diff = assigned_diff
                for name, seconds in WINDOWS.items():
                    if age is not None and age <= seconds:
                        worker.session_window_accepted[name] += 1
                        worker.session_window_diff_sum[name] += max(share_diff, 0.0)

        if ts >= last_share_ts:
            last_share_ts = ts

        for window in windows.values():
            if age is not None and age <= window.seconds:
                if is_accepted:
                    window.accepted += 1
                    window.diff_sum += max(share_diff, 0.0)
                else:
                    window.rejected += 1
                window.best_share = max(window.best_share, share_diff)

        latest.append({
            "time": fmt_time(ts),
            "age_seconds": age if age is not None else "not_available",
            "workername": mask_worker(workername),
            "share_difficulty": share_diff,
            "assigned_difficulty": assigned_diff,
            "result": "accepted" if is_accepted else "rejected",
            "hash": str(row.get("hash") or "not_available"),
            "reject_reason": "" if is_accepted else str(row.get("reject-reason") or "not_available"),
        })

    latest.sort(key=lambda item: item["age_seconds"] if isinstance(item["age_seconds"], int) else 10**18)

    leaderboard = sorted(workers.values(), key=lambda item: item.accepted_shares, reverse=True)
    active_sessions = [w for w in leaderboard if w.last_share_ts and now - w.last_share_ts <= SESSION_GAP_SECONDS]
    session_rows = sorted(active_sessions, key=lambda item: item.session_diff_sum, reverse=True)

    pool_status_lines = []
    if status_file.is_file():
        pool_status_lines = status_file.read_text(errors="ignore").splitlines()
    pool_summary = read_json(status_file) if False else {}
    if pool_status_lines:
        try:
            pool_summary = json.loads(pool_status_lines[0])
        except json.JSONDecodeError:
            pool_summary = {}

    payout_state = read_json(payout_state_file) or {}
    paid_blocks = payout_state.get("paid") if isinstance(payout_state, dict) else []
    candidates = payout_state.get("candidates") if isinstance(payout_state, dict) else []
    blocks_found = []
    for row in (paid_blocks or []) + (candidates or []):
        if not isinstance(row, dict):
            continue
        blocks_found.append({
            "height": row.get("height", "not_available"),
            "hash": row.get("blockhash", "not_available"),
            "workername": mask_worker(str(row.get("workername") or "not_available")),
            "status": row.get("status", "not_available"),
            "time": fmt_time(int(row.get("seen_at") or row.get("paid_at") or 0)),
        })
    blocks_found.sort(key=lambda item: int(item["height"]) if isinstance(item["height"], int) else -1, reverse=True)

    graph_windows = {}
    for name, window in windows.items():
        graph_windows[name] = {
            "seconds": window.seconds,
            "accepted": window.accepted,
            "rejected": window.rejected,
            "best_share": window.best_share,
            "accepted_per_minute": round(window.accepted / max(1, window.seconds / 60), 6),
            "hashrate_estimate": human_hashrate(hashrate_from_diff(window.diff_sum, window.seconds)),
        }

    data = {
        "ok": True,
        "pool": pool,
        "source": "ckpool_sharelogs",
        "collector": "pool_stats_collector.py",
        "collector_version": 1,
        "collected_at": fmt_time(now),
        "session_gap_seconds": SESSION_GAP_SECONDS,
        "accepted_shares": accepted,
        "rejected_shares": rejected,
        "reject_percent": reject_percent(accepted, rejected),
        "best_share": best_share,
        "last_share_time": fmt_time(last_share_ts),
        "active_sessions": len(active_sessions),
        "seen_workers": len(workers),
        "pool_status_updated_at": fmt_time(int(pool_summary.get("lastupdate") or 0)) if isinstance(pool_summary, dict) else "not_available",
        "graph_windows": graph_windows,
        "latest_shares": latest[:50],
        "miner_leaderboard": [
            {
                "workername": mask_worker(w.workername),
                "accepted_shares": w.accepted_shares,
                "rejected_shares": w.rejected_shares,
                "reject_percent": reject_percent(w.accepted_shares, w.rejected_shares),
                "best_share": w.best_share,
                "last_share_time": fmt_time(w.last_share_ts),
                "last_share_age_seconds": max(0, now - w.last_share_ts) if w.last_share_ts else "not_available",
            }
            for w in leaderboard[:25]
        ],
        "miner_sessions": [
            {
                "workername": mask_worker(w.workername),
                "session_accepted": w.session_accepted,
                "session_rejected": w.session_rejected,
                "session_reject_percent": reject_percent(w.session_accepted, w.session_rejected),
                "session_best_share": w.session_best_share,
                "session_last_accepted_share": w.session_last_accepted_share,
                "session_last_accepted_assigned_diff": w.session_last_accepted_assigned_diff,
                "session_last_accepted_share_time": fmt_time(w.session_last_accepted_share_ts),
                "session_started_at": fmt_time(w.session_start_ts),
                "last_share_time": fmt_time(w.last_share_ts),
                "last_share_age_seconds": max(0, now - w.last_share_ts) if w.last_share_ts else "not_available",
                "session_hashrate_estimate": human_hashrate(hashrate_from_diff(w.session_diff_sum, max(1, now - w.session_start_ts))),
                "session_hashrate_5m": human_hashrate(hashrate_from_diff(w.session_window_diff_sum["5m"], WINDOWS["5m"])),
                "session_hashrate_60m": human_hashrate(hashrate_from_diff(w.session_window_diff_sum["60m"], WINDOWS["60m"])),
                "session_hashrate_12h": human_hashrate(hashrate_from_diff(w.session_window_diff_sum["12h"], WINDOWS["12h"])),
                "session_share_rate_5m": round(w.session_window_accepted["5m"] / 5, 4),
                "session_share_rate_60m": round(w.session_window_accepted["60m"] / 60, 4),
                "session_share_rate_12h": round(w.session_window_accepted["12h"] / 720, 4),
            }
            for w in session_rows[:25]
        ],
        "blocks_found": blocks_found[:25],
    }

    output_file.parent.mkdir(parents=True, exist_ok=True)
    tmp = output_file.with_suffix(output_file.suffix + ".tmp")
    tmp.write_text(json.dumps(data, indent=2, sort_keys=True) + "\n")
    os.replace(tmp, output_file)
    return data


def main() -> int:
    parser = argparse.ArgumentParser(description="Collect HOBC pool stats")
    parser.add_argument("--pool", choices=["main", "nano", "all"], default="all")
    args = parser.parse_args()

    configs = {
        "main": {
            "log_dir": Path("/home/hobbyhashcoin/hobbyhash-logs/ckpool-main"),
            "status_file": Path("/home/hobbyhashcoin/hobbyhash-logs/ckpool-main/pool/pool.status"),
            "payout_state": Path("/home/hobbyhashcoin/hobbyhash-data/mainnet/payoutd-main-state.json"),
            "output": Path("/home/hobbyhashcoin/hobbyhash-data/mainnet/pool-stats-main.json"),
        },
        "nano": {
            "log_dir": Path("/home/hobbyhashcoin/hobbyhash-logs/ckpool-nano"),
            "status_file": Path("/home/hobbyhashcoin/hobbyhash-logs/ckpool-nano/pool/pool.status"),
            "payout_state": Path("/home/hobbyhashcoin/hobbyhash-data/mainnet/payoutd-nano-state.json"),
            "output": Path("/home/hobbyhashcoin/hobbyhash-data/mainnet/pool-stats-nano.json"),
        },
    }

    pools = configs.keys() if args.pool == "all" else [args.pool]
    for pool in pools:
        cfg = configs[pool]
        data = collect(pool, cfg["log_dir"], cfg["status_file"], cfg["payout_state"], cfg["output"])
        print(f"{pool}: accepted={data['accepted_shares']} rejected={data['rejected_shares']} sessions={data['active_sessions']} output={cfg['output']}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
