# HOBC Node Setup on Windows

This guide explains the Windows wallet status and what Windows users should expect.

## Windows Wallet Status

A public Windows HOBC wallet binary may not be built yet. If the official Windows binary is not available, it is coming soon.

Do not use fake download links, unofficial wallet links, or files sent by strangers. A wallet program controls funds, so only use official HOBC releases.

## What the Windows Wallet Will Do

The Windows wallet will let you:

- Run a HOBC node.
- Sync the HOBC blockchain.
- Create local wallet addresses.
- Send and receive HOBC.
- Back up your own wallet file.

A local wallet means you control your funds. This is different from the HOBC website wallet, which is custodial.

## Expected Config Location

When the Windows wallet is available, the config file will normally be in the HOBC data folder under your Windows user profile.

Common Bitcoin-style location:

```text
C:\Users\YOUR_WINDOWS_USERNAME\AppData\Roaming\HobbyHash\hobbyhash.conf
```

The final folder name may depend on the released HOBC wallet build. Check the official release notes before creating the file.

Sample config when RPC is needed locally:

```ini
server=1
listen=1
txindex=1
rpcbind=127.0.0.1
rpcallowip=127.0.0.1
rpcport=18762
rpcuser=change_this_rpc_user
rpcpassword=change_this_long_random_rpc_password
```

Most normal wallet users do not need RPC enabled. Only enable it if you know why you need it.

## Basic Setup Steps

1. Download the official Windows wallet when it is released.
2. Verify that the download comes from an official HOBC source.
3. Install or unzip it into a folder you trust.
4. Start the wallet and allow it to sync.
5. Create a receiving address.
6. Back up your wallet before receiving large balances.

## Checking Sync

In the graphical wallet, look for sync progress, block height, or network status. If the wallet is still syncing, received funds may not appear as fully confirmed yet.

If command-line tools are included, the sync command should be similar to:

```powershell
hobbyhash-cli.exe getblockchaininfo
```

## Backups

Back up your wallet file from inside the wallet menu when possible. Store the backup somewhere safe and offline.

Never share:

- Wallet files.
- Private keys.
- Seed words, if the wallet adds seed support.
- RPC passwords.

## Important Notes

- If the Windows binary is not built yet, wait for the official release.
- There is no guaranteed market price for HOBC.
- There may not be exchanges yet.
- For larger balances, use a local wallet instead of leaving funds in the custodial website wallet.
