# HOBC Solo Pool Payout Rules

## Payout Model

- Solo only.
- No PPS.
- No PPLNS.
- No shared rewards.
- No fake shares.
- No fake blocks.
- No fake payout stats.
- Main and nano pools mine coinbase to dedicated treasury addresses.
- Automated payout daemon pays the winning worker address after maturity.

## Winner Rule

- Winning block is attributed to the worker recorded by ckpool solve log entry.
- Winner payout address is parsed from that worker username.
- Parser takes substring before first `.` as payout address.
- Suffix after `.` is label only.
- Payout is executed by `scripts/payoutd.py` from wallet `startupfees`.
- Treasury address for main pool coinbase: `H7nFeRVK7uabesDEcYdFJy9ZUwMN5m4WRN`.
- Treasury address for nano pool coinbase: `HSQNwmXkr2iYdDMcZQt2kPBXHX7SVhLmZu`.
- Main daemon config: `/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-payout-main.json`.
- Nano daemon config: `/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-payout-nano.json`.

Examples:

- `hobc1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq.worker1`
- `hobc1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq`

## Fee Policy

- Launch testing default disclosed fee: `0.0`.
- Any production fee change must be explicit and documented before enabling.
- Daemon config key: `pool_fee_percent`.

## Address Rejection Rules

- Required prefix: `hobc1`.
- Reject non-HOBC prefixes and formats:
  - BTC (`bc1...`, `1...`, `3...`)
  - BCH (`bitcoincash:...`)
  - DGB (`dgb1...`)
  - MBUX (`mbux1...`)
  - Random text / malformed username
- Invalid workername patterns return failed `mining.authorize`.
- Daemon also validates payout addresses with node RPC `validateaddress` before sending.

## Maturity and Safety Rules

- Daemon waits for `confirmations_required` (default `100`) before payout.
- Daemon verifies coinbase output includes the configured treasury address before payout.
- Daemon records persistent state in `/home/hobbyhashcoin/hobbyhash-data/mainnet/payoutd-main-state.json`.
- Daemon records paid entries by `blockhash + workername` to prevent double-pay.
- Daemon scans ckpool sharelogs when text logs lack `Solved and confirmed` lines (nano pool).
- Payout spends only the mature coinbase UTXO for that block height (treasury output), not user deposits.
- Miner payout amount is the whole HOBC block subsidy (`45` HOBC at 0% fee); sub-unit coinbase dust stays at treasury.
- If a prior bundled payout already spent the coinbase to the winner, the daemon recovers that txid from wallet history.

Observed auth reject response format:

```text
{"result":false,"error":"Invalid HOBC address prefix in workername","id":2}
```
