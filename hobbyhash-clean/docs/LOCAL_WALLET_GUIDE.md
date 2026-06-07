# HOBC Local Wallet Guide

A local HOBC wallet means you control the wallet on your own computer. The private keys stay with you, not inside the HOBC website wallet.

## Local Wallet vs Web Wallet

Local wallet:

- You control the funds.
- You are responsible for backups.
- You can receive mining payouts directly.
- Larger balances should be stored here when possible.

HOBC web wallet:

- The website controls the wallet keys.
- Your website balance is an internal account balance.
- Server compromise, admin compromise, bugs, downtime, or operational mistakes can risk or delay funds.
- It is convenient, but it is custodial.

## Create a Wallet

Install and start the HOBC wallet or node software. On Linux, the node program is usually `hobbyhashd`; the command tool is usually `hobbyhash-cli`.

Start the node:

```bash
hobbyhashd
```

If your setup uses a custom config and data folder:

```bash
hobbyhashd -conf="$HOME/.hobbyhash/hobbyhash.conf" -datadir="$HOME/.hobbyhash"
```

The first start may create a wallet automatically. If wallet creation is manual in your build, use the wallet menu or the documented `createwallet` command for that release.

## Get a Receiving Address

Command-line example:

```bash
hobbyhash-cli getnewaddress
```

Use the address shown by your own wallet. For mining, the payout address goes before the worker name dot:

```text
YOUR_HOBC_ADDRESS.worker1
```

## Receive HOBC

Give your HOBC address to the sender or use it as your mining pool username. After a transaction is sent, your wallet must be synced before it can show the latest confirmations.

Deposits should be treated as final only after enough confirmations.

## Send HOBC

Use the graphical wallet send screen or a command like:

```bash
hobbyhash-cli sendtoaddress "RECIPIENT_HOBC_ADDRESS" 1.00000000
```

Always verify the address and amount before sending. Blockchain transactions cannot normally be reversed.

## Back Up Your Wallet

Back up before you store meaningful funds.

Command-line example:

```bash
hobbyhash-cli backupwallet "$HOME/hobbyhash-wallet-backup.dat"
```

Keep more than one backup in safe places. If your computer fails and you have no backup, you may lose your HOBC.

## Why Use a Local Wallet for Larger Balances?

The HOBC website wallet is custodial. That means the website controls the wallet keys and broadcasts withdrawals for users. If the website server is compromised or offline, funds can be at risk or delayed.

A local wallet reduces that custody risk because you hold the keys. You still must protect your computer and backups.

## Simple Safety Checklist

- Use official HOBC wallet software only.
- Back up your wallet.
- Keep backups private.
- Keep larger balances in your local wallet.
- Never enter a BTC, BCH, or DGB address when sending HOBC.
- Do not trust fake market prices or fake exchange claims.
