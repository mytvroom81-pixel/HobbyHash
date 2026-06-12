# HobbyHash Coin — Linux Node Source

Official source repo: <https://github.com/HobbyHash-Coin-LLC/HobbyHash>

This repository tracks **only** the HobbyHash Coin Linux node source needed for the standard Linux/AlmaLinux 10 and AlmaLinux 9/RHEL 9 builds. It does not publish website code, pool code, wallet app code, deployment files, credentials, or build outputs.

## Built Binaries

The Linux node build produces:

| Binary | Purpose |
|--------|---------|
| `hobbyhashd` | Full node daemon |
| `hobbyhash-cli` | RPC command-line client |
| `hobbyhash-tx` | Transaction utility |
| `hobbyhash-util` | Network / chain utilities |
| `hobbyhash-wallet` | Node wallet utility |

Compiled binaries are **not** committed to Git. Build locally or download the official release packages.

## Official Linux Packages

The current release provides:

| Package | Purpose |
|---------|---------|
| `HobbyHash-Linux-Node-x86_64.tar.gz` | Standard Linux / AlmaLinux 10 x86_64 node package |
| `HobbyHash-Linux-Node-AL9-x86_64.tar.gz` | AlmaLinux 9 / RHEL 9 x86_64 compatibility node package |
| `HobbyHash-Linux-SHA256SUMS.txt` | SHA256 checksums for the Linux packages |

Release page: <https://github.com/HobbyHash-Coin-LLC/HobbyHash/releases>

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
git status
git ls-files
```

## Not in this repo

- Website (`public_html/`), social bot, desktop wallet apps, web wallet, pools
- `config.php`, `.env`, RPC passwords, live credentials
- Build artifacts (`release-*`, `*.o`, compiled binaries)

## License

See `hobbyhash-clean/src/COPYING`.
