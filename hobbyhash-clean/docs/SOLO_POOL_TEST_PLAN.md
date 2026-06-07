# HOBC Solo Pool Test Plan

This document records real validation done against both pool instances.

## 1) Build custom ckpool

Commands:

```bash
make -C /home/hobbyhashcoin/hobbyhash-clean/pool-main/ckpool -j"$(nproc)"
make -C /home/hobbyhashcoin/hobbyhash-clean/pool-nano/ckpool -j"$(nproc)"
```

Result: both builds completed successfully.

## 2) Main config validation

Command:

```bash
/home/hobbyhashcoin/hobbyhash-clean/pool-main/ckpool/src/ckpool -A -n hobbyhash-ckpool-main -c /home/hobbyhashcoin/hobbyhash-conf/hobbyhash-ckpool-main.conf -s /home/hobbyhashcoin/hobbyhash-run/ckpool-main/sockdir -L
```

Startup output snippet:

```text
[2026-05-31 00:25:50.661] Connected to bitcoind: 127.0.0.1:38762
[2026-05-31 00:25:50.672] hobbyhash-ckpool-main stratifier ready
```

## 3) Nano config validation

Command:

```bash
/home/hobbyhashcoin/hobbyhash-clean/pool-nano/ckpool/src/ckpool -A -n hobbyhash-ckpool-nano -c /home/hobbyhashcoin/hobbyhash-conf/hobbyhash-ckpool-nano.conf -s /home/hobbyhashcoin/hobbyhash-run/ckpool-nano/sockdir -L
```

Startup output snippet:

```text
[2026-05-31 00:22:02.236] Connected to bitcoind: 127.0.0.1:38762
[2026-05-31 00:22:02.246] hobbyhash-ckpool-nano stratifier ready
```

## 4-16 Validation Steps and Results

4. Start main only: PASS  
5. Confirm listen `5555`: PASS

```text
COMMAND       PID          USER   FD   TYPE   DEVICE SIZE/OFF NODE NAME
hobbyhash 1726859 hobbyhashcoin   15u  IPv4 44674715      0t0  TCP *:5555 (LISTEN)
```

6. Stop main: PASS  
7. Start nano only: PASS  
8. Confirm listen `5556`: PASS

```text
COMMAND       PID          USER   FD   TYPE   DEVICE SIZE/OFF NODE NAME
hobbyhash 1726053 hobbyhashcoin   14u  IPv4 44669972      0t0  TCP *:5556 (LISTEN)
```

9. Stop nano: PASS  
10. Start both together: PASS  
11. Confirm separate processes: PASS  
12. Confirm both get work from HOBC node: PASS (`Connected to bitcoind: 127.0.0.1:38762` in both logs)

13. Invalid address worker rejected: PASS

```text
{"result":false,"error":"Invalid HOBC address prefix in workername","id":2}
```

14. Valid HOBC worker accepted: PASS

```text
{"result":true,"error":null,"id":2}
```

15. Nano difficulty truly `0.005`: PASS

```text
{"params":[0.0050000000000000001],"id":null,"method":"mining.set_difficulty"}
```

16. Main difficulty `5000`: PASS

```text
{"params":[5000.0],"id":null,"method":"mining.set_difficulty"}
```

## Required Address Tests

- Valid HOBC address test: PASS
- Invalid BTC address test: PASS
- Invalid BCH address test: PASS
- Invalid DGB address test: PASS
- Invalid MBUX address test: PASS
- Invalid random text test: PASS
- Username with suffix test: PASS
- Username without suffix test: PASS
- Main diff `5000` test: PASS
- Nano diff `0.005` test: PASS
- Both pools running together test: PASS
- No cross-log pollution test: PASS
- No cross-service dependency test: PASS

Cross-log check output:

```text
main_log_contains_nano:
nano_log_contains_main:
```

## Automated Payout Daemon Tests

### A) Dedicated treasury address creation

Command:

```bash
/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-mainnet.conf -rpcwallet=startupfees getnewaddress "pool_treasury_mainnet" legacy
```

Observed:

```text
H7nFeRVK7uabesDEcYdFJy9ZUwMN5m4WRN
```

Result: PASS

### B) Daemon syntax validation

Command:

```bash
python3 -m py_compile /home/hobbyhashcoin/hobbyhash-clean/scripts/payoutd.py
```

Result: PASS

### C) Daemon self-test

Command:

```bash
python3 /home/hobbyhashcoin/hobbyhash-clean/scripts/payoutd.py --self-test
```

Observed:

```text
self-test passed
```

Result: PASS

### D) Live dry-run cycle with production config

Command:

```bash
python3 /home/hobbyhashcoin/hobbyhash-clean/scripts/payoutd.py --config /home/hobbyhashcoin/hobbyhash-conf/hobbyhash-payout-main.json --once --dry-run --verbose
```

State output:

```json
{
  "candidates": [],
  "last_log_offset": 204754,
  "last_run": 1780216061,
  "paid": [],
  "version": 1
}
```

Result: PASS

### E) Mock solve-line integration test

Command:

```bash
python3 - <<'PY'
import json, tempfile, pathlib, subprocess
base_cfg_path = '/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-payout-main.json'
with open(base_cfg_path,'r',encoding='utf-8') as f:
    cfg = json.load(f)
with tempfile.TemporaryDirectory() as td:
    tdp = pathlib.Path(td)
    logp = tdp/'mock.log'
    statep = tdp/'state.json'
    cfgp = tdp/'cfg.json'
    logp.write_text('[2026-05-31 01:30:00.000] Solved and confirmed block 12 by hobc1qtestwinner.workerA\n', encoding='utf-8')
    cfg['pool_log_file'] = str(logp)
    cfg['state_file'] = str(statep)
    with open(cfgp,'w',encoding='utf-8') as f:
        json.dump(cfg,f)
    subprocess.run(['python3','/home/hobbyhashcoin/hobbyhash-clean/scripts/payoutd.py','--config',str(cfgp),'--once','--dry-run'],check=True)
    print(statep.read_text(encoding='utf-8'))
PY
```

Observed:

```text
[payoutd] Detected solved block height=12 worker=hobc1qtestwinner.workerA
[payoutd] Skipping height=12, invalid winner address hobc1qtestwinner
```

Result: PASS (solve parsing and safety rejection verified)
