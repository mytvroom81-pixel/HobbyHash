# HOBC Pool Troubleshooting

Use this guide when your miner will not connect, shows rejected shares, or does not receive a payout yet.

## Invalid Address

Your username must start with a real HOBC address.

Correct format:

```text
YOUR_HOBC_ADDRESS.worker1
```

The address is the part before the first dot. The worker label after the dot can be anything simple, like `worker1`, `asic1`, or `nano1`.

## Wrong Pool Port

Main Pool:

```text
stratum+tcp://pool.hobbyhashcoin.com:5555
```

Nano Pool:

```text
stratum+tcp://pool.hobbyhashcoin.com:5556
```

Use Main Pool for ASICs and higher-hashrate miners. Use Nano Pool for very small miners and low-difficulty testing.

## Wrong Ticker or Wrong Address Type

HOBC is not BTC, BCH, or DGB. Do not use addresses from those wallets.

Common mistakes:

- BTC address by mistake.
- Bitcoin Cash address by mistake.
- DigiByte address by mistake.
- Exchange deposit address for another coin.
- Random text instead of a HOBC address.

If the pool rejects your worker, generate a fresh HOBC address from your HOBC wallet and try again.

## High Reject Rate

A high reject rate can come from:

- Unstable internet connection.
- Miner clock or firmware problems.
- Wrong pool URL.
- Miner pointed at the wrong port.
- Too much latency.
- Device overheating or hardware errors.

Try restarting the miner, checking temperatures, checking the pool URL, and using the pool closest to your miner setup.

## Low Difficulty Nano Setup

Nano Pool starts at difficulty `0.005`. This is for small miners. If your nano miner still cannot submit shares:

- Confirm it supports SHA-256.
- Confirm it is pointed at port `5556`.
- Confirm the worker name starts with a HOBC address.
- Confirm password is `x`.
- Check whether your miner requires host and port in separate fields.

## ASIC Setup

For normal SHA-256 ASIC miners, use:

```text
Pool URL: stratum+tcp://pool.hobbyhashcoin.com:5555
Worker: YOUR_HOBC_ADDRESS.asic1
Password: x
```

If your ASIC does not accept the full URL, use:

```text
pool.hobbyhashcoin.com:5555
```

## No Payout Yet

HOBC pools are solo pools. The pool does not split rewards across all connected miners.

You only receive a payout when your worker finds a real HOBC block. Accepted shares mean your miner is working, but they do not guarantee a payout.

## Pool Status Unavailable

If the website says a pool is `offline` or `not_available`, the status file or pool service may not be readable by the website. Do not assume hashrate, workers, shares, blocks, or payouts unless the pool reports real data.
