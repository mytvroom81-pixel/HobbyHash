# HOBC Custodial Wallet Risk Notice

The HOBC web wallet is custodial.

That means the website controls the wallet keys and sends withdrawals for users. Users have website account balances, but they do not directly control the private keys for funds held inside the web wallet.

## Main Risks

Server compromise can risk funds. If an attacker gains access to the website server, wallet backend, admin tools, database, or signing environment, funds may be stolen or delayed.

Operational mistakes can also risk funds. Bugs, wrong settings, failed backups, failed withdrawals, or downtime can affect access to the web wallet.

## Use a Local Wallet for Larger Balances

For larger balances, use a local HOBC wallet that you control. A local wallet keeps the keys on your own computer and reduces custody risk from the website.

You are responsible for protecting and backing up a local wallet.

## Deposits

Deposits require confirmations before they can be credited. A deposit may appear as detected or pending before it is final.

The website must not credit fake deposits or fake confirmations.

## Withdrawals

Withdrawals must create real on-chain transactions. A public withdrawal txid must be a real txid from the HOBC network.

The website must not show fake txids.

Withdrawals can be paused for maintenance, security review, hot-wallet funding, scanner problems, or RPC problems.

## Market Price

There is no guaranteed HOBC market price.

The website must not invent a market price or market cap. If no reliable market data is available, the correct value is `not_available`.

## Use at Your Own Risk

By using the HOBC web wallet, you accept custodial risk. Keep only the amount you are comfortable leaving in a website-controlled wallet.

For mining payouts and larger balances, a local wallet is recommended.
