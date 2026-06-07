# HOBC ckpool Main + Nano

This deploy creates two independent custom ckpool trees for HOBC:

- `/home/hobbyhashcoin/hobbyhash-clean/pool-main/ckpool`
- `/home/hobbyhashcoin/hobbyhash-clean/pool-nano/ckpool`

Both are standalone solo pools (`-A`) and both connect to the local HOBC mainnet RPC node.

## Exact Ports

- Main stratum port: `5555`
- Nano stratum port: `5556`
- HOBC RPC endpoint used by both: `127.0.0.1:18762`

## Exact Difficulty Settings

Main config (`/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-ckpool-main.conf`):

- `mindiff: 1000`
- `startdiff: 5000`
- `maxdiff: 0`
- `stratum_diff_scale: 1.0`
- Effective miner difficulty shown on wire: starts at `5000.0`, vardiff adjusts from share rate
- `ignore_miner_diff: true` (miners cannot override pool difficulty via `mining.suggest_difficulty` or worker defaults)

Nano config (`/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-ckpool-nano.conf`):

- Internal vardiff base: `mindiff: 1`, `startdiff: 1`, `maxdiff: 0`
- Exact wire scaling: `stratum_diff_scale: 0.005`
- Effective miner difficulty shown on wire: starts at `0.005`, vardiff adjusts from share rate (same algorithm as main)
- Minimum wire diff: `0.005` (internal 1 × scale)
- `ignore_miner_diff: true` (miners cannot override pool difficulty)

## Worker Payout Address Parsing

- Username format:
  - `hobc1exampleaddress.worker1`
  - `hobc1exampleaddress`
- Parsing rule: address is the part before the first dot (`.`).
- Worker suffix after dot is label only.
- Rejection:
  - Prefix must match `hobc1`.
  - Non-HOBC prefixes are rejected (`bc1`, `bitcoincash:`, `dgb1`, `mbux1`, random text).
- Address auth result comes back through `mining.authorize`.

## Automated Payout Daemon (Main Pool)

- Daemon script: `/home/hobbyhashcoin/hobbyhash-clean/scripts/payoutd.py`
- Daemon config: `/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-payout-main.json`
- Treasury coinbase address (main pool): `H7nFeRVK7uabesDEcYdFJy9ZUwMN5m4WRN`
- State file: `/home/hobbyhashcoin/hobbyhash-data/mainnet/payoutd-main-state.json`
- Service unit (staged): `/home/hobbyhashcoin/hobbyhash-clean/docs/systemd/hobbyhash-payout-main.service`
- Payout logic:
  - Parse `Solved and confirmed block <height> by <worker>` from main ckpool log.
  - Extract payout address from workername prefix before `.`.
  - Wait for maturity confirmations.
  - Verify coinbase paid treasury address.
  - Send payout from wallet `startupfees`.

## Miner Connection Examples

ASIC (main pool):

```bash
stratum+tcp://<server-ip>:5555
username: hobc1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq.worker1
password: x
```

Nano miner (nano pool):

```bash
stratum+tcp://<server-ip>:5556
username: hobc1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq.nano1
password: x
```

## Start / Stop Commands

Main:

```bash
/home/hobbyhashcoin/hobbyhash-clean/pool-main/ckpool/src/ckpool -A -n hobbyhash-ckpool-main -c /home/hobbyhashcoin/hobbyhash-conf/hobbyhash-ckpool-main.conf -s /home/hobbyhashcoin/hobbyhash-run/ckpool-main/sockdir -L
```

Nano:

```bash
/home/hobbyhashcoin/hobbyhash-clean/pool-nano/ckpool/src/ckpool -A -n hobbyhash-ckpool-nano -c /home/hobbyhashcoin/hobbyhash-conf/hobbyhash-ckpool-nano.conf -s /home/hobbyhashcoin/hobbyhash-run/ckpool-nano/sockdir -L
```

Stop:

```bash
pkill -f "hobbyhash-ckpool-main"
pkill -f "hobbyhash-ckpool-nano"
```

## Logs and Runtime Paths

- Main log dir: `/home/hobbyhashcoin/hobbyhash-logs/ckpool-main`
- Nano log dir: `/home/hobbyhashcoin/hobbyhash-logs/ckpool-nano`
- Main runtime/sockdir: `/home/hobbyhashcoin/hobbyhash-run/ckpool-main/sockdir`
- Nano runtime/sockdir: `/home/hobbyhashcoin/hobbyhash-run/ckpool-nano/sockdir`

Check logs:

```bash
tail -n 50 /home/hobbyhashcoin/hobbyhash-logs/ckpool-main/hobbyhash-ckpool-main.log
tail -n 50 /home/hobbyhashcoin/hobbyhash-logs/ckpool-nano/hobbyhash-ckpool-nano.log
```

## Verify Independence

Checklist:

- Separate tree roots.
- Separate config files.
- Separate runtime/sock directories.
- Separate process names.
- Separate listen ports.
- Separate log trees.
- Either pool can be stopped while the other remains live.

Live listen proof:

```text
COMMAND       PID          USER   FD   TYPE   DEVICE SIZE/OFF NODE NAME
hobbyhash 1726859 hobbyhashcoin   15u  IPv4 44674715      0t0  TCP *:5555 (LISTEN)
COMMAND       PID          USER   FD   TYPE   DEVICE SIZE/OFF NODE NAME
hobbyhash 1726053 hobbyhashcoin   14u  IPv4 44669972      0t0  TCP *:5556 (LISTEN)
```
