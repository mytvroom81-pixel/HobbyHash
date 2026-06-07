# HOBC Node Setup on Linux

This guide is for running your own HobbyHash Coin (HOBC) node on Linux. A local node helps you verify the chain yourself, receive funds to your own wallet, and avoid depending only on the website.

## 1. Create an Install Folder

Choose a folder for the HOBC software and a separate folder for chain data.

```bash
mkdir -p "$HOME/hobbyhash/bin"
mkdir -p "$HOME/.hobbyhash"
```

Put the HOBC programs in:

```text
$HOME/hobbyhash/bin
```

Common program names are:

```text
hobbyhashd
hobbyhash-cli
hobbyhash-qt
```

If a public Linux download has not been published yet, build from the official HOBC source tree or wait for the official release. Do not download wallet binaries from random links.

## 2. Create `hobbyhash.conf`

Create this file:

```text
$HOME/.hobbyhash/hobbyhash.conf
```

Sample config:

```ini
server=1
daemon=1
listen=1
txindex=1
rpcbind=127.0.0.1
rpcallowip=127.0.0.1
rpcport=18762
rpcuser=change_this_rpc_user
rpcpassword=change_this_long_random_rpc_password
```

Keep `rpcuser` and `rpcpassword` private. Do not put this file inside a public website folder.

## 3. Start the Node

From your install folder:

```bash
$HOME/hobbyhash/bin/hobbyhashd -conf="$HOME/.hobbyhash/hobbyhash.conf" -datadir="$HOME/.hobbyhash"
```

If `hobbyhashd` is already in your `PATH`, you can run:

```bash
hobbyhashd -conf="$HOME/.hobbyhash/hobbyhash.conf" -datadir="$HOME/.hobbyhash"
```

## 4. Check Sync

Use:

```bash
$HOME/hobbyhash/bin/hobbyhash-cli -conf="$HOME/.hobbyhash/hobbyhash.conf" -datadir="$HOME/.hobbyhash" getblockchaininfo
```

Important fields:

- `blocks`: how many blocks your node has.
- `headers`: how many block headers your node knows about.
- `verificationprogress`: sync progress.
- `initialblockdownload`: `true` means the node is still catching up.

If `blocks` is lower than `headers`, let the node keep running.

## 5. Get a New Address

Use:

```bash
$HOME/hobbyhash/bin/hobbyhash-cli -conf="$HOME/.hobbyhash/hobbyhash.conf" -datadir="$HOME/.hobbyhash" getnewaddress
```

Use this address for receiving HOBC. Always double-check that the address is a HOBC address, not a BTC, BCH, or DGB address.

## 6. Back Up Your Local Wallet

Your local wallet controls your funds. Back it up before storing serious value.

Use:

```bash
$HOME/hobbyhash/bin/hobbyhash-cli -conf="$HOME/.hobbyhash/hobbyhash.conf" -datadir="$HOME/.hobbyhash" backupwallet "$HOME/hobbyhash-wallet-backup.dat"
```

Store backups somewhere safe, preferably offline. Anyone with your wallet file and password can potentially spend your funds.

## 7. Stop the Node Safely

Use:

```bash
$HOME/hobbyhash/bin/hobbyhash-cli -conf="$HOME/.hobbyhash/hobbyhash.conf" -datadir="$HOME/.hobbyhash" stop
```

Wait a few seconds for the process to shut down cleanly before rebooting or copying wallet files.

## 8. View Logs

The debug log is usually here:

```text
$HOME/.hobbyhash/debug.log
```

View recent log lines:

```bash
tail -n 100 "$HOME/.hobbyhash/debug.log"
```

Logs are useful for checking peers, sync progress, and startup errors.

## Safety Notes

- Keep RPC bound to `127.0.0.1`.
- Keep your config outside any website folder.
- Back up your wallet before mining to it or receiving large payments.
- The web wallet is custodial; a local wallet is better for larger balances because you control the keys.
