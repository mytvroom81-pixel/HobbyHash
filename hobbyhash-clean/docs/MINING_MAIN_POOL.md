# Mining HOBC on the Main Pool

The HOBC Main Pool is for SHA-256 ASIC miners and higher-hashrate devices. It is a solo pool.

## Main Pool Settings

```text
URL: stratum+tcp://pool.hobbyhashcoin.com:5555
Start diff: 5000
Username: YOUR_HOBC_ADDRESS.worker1
Password: x
Mode: solo only
```

Replace `YOUR_HOBC_ADDRESS` with a real HOBC address from your own wallet.

Example username:

```text
hobc1yourrealhobcaddresshere.worker1
```

The worker name after the dot is only a label. The payout goes to the HOBC address before the dot.

## Solo Mining Meaning

The pool does not split rewards across all miners. Your miner submits shares to prove it is working, but a payout happens only when your worker finds a real HOBC block.

No block found means no payout yet.

## ASIC Setup Example

Most ASIC web panels ask for Pool 1 URL, worker, and password.

```text
Pool URL: stratum+tcp://pool.hobbyhashcoin.com:5555
Worker: YOUR_HOBC_ADDRESS.worker1
Password: x
```

If your miner does not accept `stratum+tcp://`, try entering only:

```text
pool.hobbyhashcoin.com:5555
```

## Multiple ASICs

Use the same payout address with different worker labels:

```text
YOUR_HOBC_ADDRESS.asic1
YOUR_HOBC_ADDRESS.asic2
YOUR_HOBC_ADDRESS.asic3
```

All payouts still go to `YOUR_HOBC_ADDRESS`.

## What to Check

- Your device supports SHA-256 mining.
- You are using port `5555`.
- Your username starts with a real HOBC address.
- Your password is `x`.
- Your miner shows accepted shares.
- You understand that solo payouts happen only when your worker finds a block.

## Common Mistakes

- Using a BTC, BCH, or DGB address instead of a HOBC address.
- Using the Nano Pool port by mistake.
- Expecting shared-pool payouts.
- Expecting a payout before a block is found.
