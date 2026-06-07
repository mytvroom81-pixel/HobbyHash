# HobbyHash Coin — Node Source

Git tracks **only** the HOBC full-node source tree. Build these binaries locally:

| Binary | Purpose |
|--------|---------|
| `hobbyhashd` | Full node daemon |
| `hobbyhash-cli` | RPC command-line client |
| `hobbyhash-tx` | Transaction utility |
| `hobbyhash-util` | Network / chain utilities |
| `hobbyhash-wallet` | Wallet tool |

Compiled binaries are **not** in Git — run `make` on each machine.

## Build (Linux)

```bash
cd hobbyhash-clean/src
./autogen.sh
./configure --without-gui
make -j"$(nproc)"
```

Binaries appear in `src/` after a successful build.

## Before pushing

```bash
./scripts/check-secrets.sh
git status
```

## Not in this repo

- Website (`public_html/`), social bot, desktop wallets, web wallet, pools
- `config.php`, `.env`, RPC passwords, live credentials
- Build artifacts (`release-*`, `*.o`, compiled binaries)

## License

See `hobbyhash-clean/src/COPYING`.
