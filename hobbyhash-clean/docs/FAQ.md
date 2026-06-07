# HOBC FAQ

## What is HOBC?

HOBC means HobbyHash Coin. It is a hobby-focused SHA-256 coin project with local node software, mining pools, explorer/status pages, docs, and a custodial web wallet.

## Is this Bitcoin?

No. HOBC is not Bitcoin. Do not send HOBC to a Bitcoin address.

## Is this Bitcoin Cash?

No. HOBC is not Bitcoin Cash. Do not send HOBC to a Bitcoin Cash address.

## Can I Mine with SHA-256 ASICs?

Yes. HOBC mining is for SHA-256 miners. Normal ASIC miners should use the Main Pool.

Main Pool:

```text
stratum+tcp://pool.hobbyhashcoin.com:5555
```

## Can Nano Miners Mine?

Yes. Nano miners and very small SHA-256 devices can use the Nano Pool.

Nano Pool:

```text
stratum+tcp://pool.hobbyhashcoin.com:5556
```

## Why Two Pools?

The Main Pool uses higher starting difficulty for ASICs. The Nano Pool uses very low starting difficulty so tiny miners can submit shares more easily.

Both pools are solo pools.

## What is Solo Mining?

Solo mining means the miner who finds a real block gets the block reward. Shares prove your miner is working, but shares do not create shared payouts.

## Does the Pool Split Rewards?

No. The HOBC pools are solo only. The pool does not split each block reward among all miners.

## What is the 10% Launch Reserve?

HOBC has a target supply of `84,000,000 HOBC`. The launch reserve is `8,400,000 HOBC`, which is 10% of the target supply.

The normal mining target is `75,600,000 HOBC`.

Reserve tracking should be transparent when reserve addresses and balances are ready. Until then, status should be `pending_launch` or `not_available`.

## Is the Web Wallet Custodial?

Yes. The HOBC web wallet is custodial. The website controls the wallet keys and users have website account balances.

For larger balances, use a local wallet that you control.

## How Do I Run My Own Node?

Install the HOBC node software, create a `hobbyhash.conf`, start `hobbyhashd`, and let it sync.

Linux users can start with:

```text
docs/NODE_SETUP_LINUX.md
```

Windows users can start with:

```text
docs/NODE_SETUP_WINDOWS.md
```

## Is There a Market Price?

There may be no reliable market price yet. The website should not invent a price.

If real market data is unavailable, the correct answer is `not_available`.

## Are There Exchanges Yet?

There may be no exchanges yet. Do not trust fake exchange listings or fake prices.

Use only official HOBC announcements for exchange information.
