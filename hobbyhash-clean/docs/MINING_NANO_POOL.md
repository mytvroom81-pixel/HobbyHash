# Mining HOBC on the Nano Pool

The HOBC Nano Pool is for very small SHA-256 miners, test miners, and low-hashrate devices. It is a solo pool with very low starting difficulty.

## Nano Pool Settings

```text
URL: stratum+tcp://pool.hobbyhashcoin.com:5556
Start diff: 0.005
Username: YOUR_HOBC_ADDRESS.nano1
Password: x
Mode: solo only
```

Replace `YOUR_HOBC_ADDRESS` with a real HOBC address from your own wallet.

The payout goes to the HOBC address before the dot. The `.nano1` part is just your worker label.

## Nano Miner Example

Use these values in your miner app or device settings:

```text
Pool URL: stratum+tcp://pool.hobbyhashcoin.com:5556
Worker: YOUR_HOBC_ADDRESS.nano1
Password: x
```

If the app has separate host and port fields:

```text
Host: pool.hobbyhashcoin.com
Port: 5556
Worker: YOUR_HOBC_ADDRESS.nano1
Password: x
```

## Multiple Nano Miners

Use the same payout address with different labels:

```text
YOUR_HOBC_ADDRESS.nano1
YOUR_HOBC_ADDRESS.nano2
YOUR_HOBC_ADDRESS.nano3
```

## Solo Mining Meaning

The Nano Pool is still solo mining. It does not split rewards among everyone. The low difficulty helps tiny miners submit shares, but a payout only happens if your worker finds a real HOBC block.

## What to Check

- Your miner supports SHA-256.
- You are using port `5556`.
- Your start difficulty is `0.005`.
- Your username begins with a real HOBC address.
- Your password is `x`.
- Your miner is submitting accepted shares.

## When to Use the Main Pool Instead

Use the Main Pool if you have a normal SHA-256 ASIC or a high-hashrate setup. The Nano Pool is meant for very small devices and testing.
