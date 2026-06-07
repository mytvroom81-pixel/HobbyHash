# HOBC Systemd Services

These service files are staged only. Do not enable autostart until explicitly approved.

## Current Live State Warning

As of the latest service audit, the staged service plan below is not the full live runtime model yet.

Already installed, enabled, and active:

```text
hobbyhash-mainnet-node2.service
hobbyhash-ckpool-main.service
hobbyhash-ckpool-nano.service
hobbyhash-payout-main.service
hobbyhash-payout-nano.service
```

Not installed under the requested service names yet:

```text
hobbyhashd-mainnet.service
hobbyhash-explorer.service
hobbyhash-wallet.service
hobbyhash-wallet-scanner.service
hobbyhash-wallet-withdrawer.service
```

The primary mainnet node on `18761/18762` is currently running, but not under the requested `hobbyhashd-mainnet.service` name. Before reboot/autostart cleanup, decide whether to migrate the live node to `hobbyhashd-mainnet.service` and what to do with the already-enabled `hobbyhash-mainnet-node2.service`.

Wallet jobs are currently running from cron:

```text
deposit_scanner.php
confirmation_updater.php
hot_wallet_balance_checker.php
withdrawal_broadcaster.php
liability_reconciler.php
```

Do not run the staged `hobbyhash-wallet-scanner.service` or `hobbyhash-wallet-withdrawer.service` at the same time as the matching cron jobs. That would duplicate wallet scanning and withdrawal broadcasting.

The withdrawal broadcaster can create real on-chain txids for approved withdrawals. Treat both the cron entry and the staged service as live-money operations.

Staged unit files:

```text
/home/hobbyhashcoin/hobbyhash-clean/docs/systemd/hobbyhashd-mainnet.service
/home/hobbyhashcoin/hobbyhash-clean/docs/systemd/hobbyhash-ckpool-main.service
/home/hobbyhashcoin/hobbyhash-clean/docs/systemd/hobbyhash-ckpool-nano.service
/home/hobbyhashcoin/hobbyhash-clean/docs/systemd/hobbyhash-explorer.service
/home/hobbyhashcoin/hobbyhash-clean/docs/systemd/hobbyhash-wallet.service
/home/hobbyhashcoin/hobbyhash-clean/docs/systemd/hobbyhash-wallet-scanner.service
/home/hobbyhashcoin/hobbyhash-clean/docs/systemd/hobbyhash-wallet-withdrawer.service
```

Do not touch or reuse MBUX, BTC, BCH, DGB, old HOBC, solobitminers, or unrelated service files.

## Install Later, Disabled First

Only after manual approval, copy the staged files into systemd:

```bash
sudo cp /home/hobbyhashcoin/hobbyhash-clean/docs/systemd/hobbyhashd-mainnet.service /etc/systemd/system/
sudo cp /home/hobbyhashcoin/hobbyhash-clean/docs/systemd/hobbyhash-ckpool-main.service /etc/systemd/system/
sudo cp /home/hobbyhashcoin/hobbyhash-clean/docs/systemd/hobbyhash-ckpool-nano.service /etc/systemd/system/
sudo cp /home/hobbyhashcoin/hobbyhash-clean/docs/systemd/hobbyhash-explorer.service /etc/systemd/system/
sudo cp /home/hobbyhashcoin/hobbyhash-clean/docs/systemd/hobbyhash-wallet.service /etc/systemd/system/
sudo cp /home/hobbyhashcoin/hobbyhash-clean/docs/systemd/hobbyhash-wallet-scanner.service /etc/systemd/system/
sudo cp /home/hobbyhashcoin/hobbyhash-clean/docs/systemd/hobbyhash-wallet-withdrawer.service /etc/systemd/system/
sudo systemctl daemon-reload
```

Do not run `systemctl enable` yet.

Before installing these units, clean up or approve the current live conflicts:

- Decide whether existing enabled `hobbyhash-ckpool-main.service` and `hobbyhash-ckpool-nano.service` should be replaced with the staged versions.
- Decide whether existing enabled `hobbyhash-payout-main.service` and `hobbyhash-payout-nano.service` remain separate required pool payout services.
- Decide whether wallet cron jobs should be disabled before systemd wallet scanner/withdrawer services are started.
- Decide whether `hobbyhash-mainnet-node2.service` should stay enabled.

## Start Node

```bash
sudo systemctl start hobbyhashd-mainnet
```

## Stop Node

```bash
sudo systemctl stop hobbyhashd-mainnet
```

## Status Node

```bash
sudo systemctl status hobbyhashd-mainnet --no-pager
```

## Start Main Pool

```bash
sudo systemctl start hobbyhash-ckpool-main
```

## Start Nano Pool

```bash
sudo systemctl start hobbyhash-ckpool-nano
```

## Stop Main Pool

```bash
sudo systemctl stop hobbyhash-ckpool-main
```

## Stop Nano Pool

```bash
sudo systemctl stop hobbyhash-ckpool-nano
```

## Check Ports

```bash
ss -lntup | grep -E '18761|18762|5555|5556|18765|18766'
```

Expected ports when everything is running:

- `18761`: HOBC P2P, if configured.
- `18762`: HOBC local RPC.
- `5555`: Main Pool stratum.
- `5556`: Nano Pool stratum.
- `18765`: Local explorer app on `127.0.0.1`.
- `18766`: Local wallet app on `127.0.0.1`.

## Check Node

```bash
sudo -u hobbyhashcoin /home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-mainnet.conf getblockchaininfo
```

## Check Mining Template

```bash
sudo -u hobbyhashcoin /home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-mainnet.conf getblocktemplate '{"rules":["segwit"]}'
```

## Check Pool Logs

```bash
journalctl -u hobbyhash-ckpool-main -n 100 --no-pager
journalctl -u hobbyhash-ckpool-nano -n 100 --no-pager
```

## Check Wallet Services

```bash
systemctl status hobbyhash-wallet --no-pager
systemctl status hobbyhash-wallet-scanner --no-pager
systemctl status hobbyhash-wallet-withdrawer --no-pager
```

## Check Explorer

```bash
systemctl status hobbyhash-explorer --no-pager
```

## Manual Test Order

Test manually before any autostart approval:

1. Start `hobbyhashd-mainnet`.
2. Check node sync and mining template.
3. Start `hobbyhash-ckpool-main`.
4. Start `hobbyhash-ckpool-nano`.
5. Check pool ports and logs.
6. Start `hobbyhash-explorer`.
7. Check `http://127.0.0.1:18765/status`.
8. Start `hobbyhash-wallet`.
9. Start `hobbyhash-wallet-scanner`.
10. Start `hobbyhash-wallet-withdrawer` only after withdrawal safety is approved, because it can broadcast real withdrawals.

## Important Warnings

- Do not enable services until approval.
- Test start and stop manually first.
- Keep RPC on localhost only.
- Keep wallet config and RPC credentials out of public output.
- The withdrawal service can create real txids for approved withdrawals. Do not start it casually.
- Do not run wallet cron jobs and wallet systemd worker services at the same time.
- `/home/hobbyhashcoin/hobbyhash-clean/wallet/config.php` contains private DB/RPC/SMS settings and should not be world-readable in final production hardening.
- If any service fails, check `journalctl -u SERVICE_NAME -n 100 --no-pager`.
