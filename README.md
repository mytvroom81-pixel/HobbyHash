# HobbyHash Coin — Linux Node & Wallet Source

Source for the HOBC chain node, desktop/cold/watch wallets, web wallet backend, mining pools, and related Linux tooling.

**Not included:** the public website (`public_html/`), social bot, live configs, or build artifacts. Those stay on the server.

## Repository layout

| Path | Purpose |
|------|---------|
| `hobbyhash-clean/src/` | HOBC full node (`hobbyhashd`, CLI, wallet tools) — C++ source |
| `hobbyhash-clean/wallet/` | Custodial web wallet backend (PHP app + jobs + schema) |
| `hobbyhash-clean/apps/` | Desktop, cold, and watch wallet apps (Electron/Node) |
| `hobbyhash-clean/pool-main/` | Main pool (`ckpool`) |
| `hobbyhash-clean/pool-nano/` | Nano pool (`ckpool`) |
| `hobbyhash-clean/scripts/` | Payout daemon, pool stats, audit scripts |
| `hobbyhash-clean/packages/` | Chain package metadata |
| `hobbyhash-clean/docs/` | Chain specs, genesis proofs, systemd unit files |

## Build the Linux node

```bash
cd hobbyhash-clean/src
./autogen.sh
./configure --without-gui
make -j"$(nproc)"
```

Binaries land under `src/` (e.g. `src/hobbyhashd`, `src/hobbyhash-cli` after rebrand build).

See `hobbyhash-clean/docs/` for mainnet boot, genesis, and systemd examples.

## Web wallet backend

```bash
cd hobbyhash-clean/wallet
cp config.example.php /path/to/private/config.php
chmod 600 /path/to/private/config.php
# Edit DB + RPC credentials, then apply install.sql / migrations
```

Full steps: `hobbyhash-clean/wallet/README_WALLET_INSTALL.md`

## Desktop wallets

```bash
cd hobbyhash-clean/apps/hobbyhash-wallet-desktop
npm install
npm run build   # or npm start for dev
```

## Before pushing to GitHub

```bash
./scripts/check-secrets.sh
git status
```

Do **not** push if the secret scan fails.

## Security

- Never commit `config.php`, `.env`, RPC cookies, DB passwords, or API keys.
- `config.example.php` uses placeholders only — copy it to a private path on the server.
- Rotate any credential that was ever stored in an example or tracked file.

## License

See `hobbyhash-clean/src/COPYING` (chain node) and project docs for component licenses.
