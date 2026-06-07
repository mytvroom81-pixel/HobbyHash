# HOBC Launch Reserve Proof (Regtest)

Status: PASS

## Block 1 hash

- `5dd702e0026efd95524232c4f643564a6b6b9940fdf3c36a52469d3357213180`

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf -rpcwallet=regtestminer generatetoaddress 1 rhobc1qtyv6trjx9e4mq0fnzzwl6cse2zm3dtvsrur0v7`

Output:

```json
[
  "5dd702e0026efd95524232c4f643564a6b6b9940fdf3c36a52469d3357213180"
]
```

## Block 1 coinbase transaction

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf getblock 5dd702e0026efd95524232c4f643564a6b6b9940fdf3c36a52469d3357213180 2`

Coinbase transaction:

- txid: `d1272953cd71218ad5687e59685e32a400cabf968ab7418e2a3448668ad798b2`
- vin coinbase data: `5100`

## Each reserve output

From block-1 coinbase `vout`:

1) Reserve payout output
- `n=0`
- `value=8400000.00000000`
- `scriptPubKey.address=rhobc1qtyv6trjx9e4mq0fnzzwl6cse2zm3dtvsrur0v7`

2) Witness commitment output (not spendable reserve)
- `n=1`
- `value=0.00000000`
- `scriptPubKey.type=nulldata`
- `asm=OP_RETURN aa21a9ede2f61c3f71d1defd3fa999dfa36953755c690689799962b48bebd836974e8cf9`

## Total reserve amount

- Sum of spendable reserve outputs in block 1 coinbase = `8,400,000.00000000 HOBC`

## Proof no extra hidden outputs

From block-1 coinbase:
- `nTx=1` in block (coinbase only)
- coinbase `vout` length is exactly `2`
- outputs are exactly:
  - one spendable reserve output (`8,400,000`)
  - one zero-value witness commitment OP_RETURN output
- No additional spendable outputs exist in block-1 coinbase.

## Block 2 reward proof

Block 2 hash:
- `0d5f5261bafccd4f86836721bfb1397e8c1498bac3de8a402b0c1bcd38216dc0`

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf getblock 0d5f5261bafccd4f86836721bfb1397e8c1498bac3de8a402b0c1bcd38216dc0 2`

Proof point:
- block-2 coinbase `vout[0].value = 45.00000000`

## Total supply calculation proof

At height `104`, mined blocks are `1..104`:

- Block 1 reserve issuance: `8,400,000`
- Blocks 2..104 normal subsidy: `103 * 45 = 4,635`
- Expected total minted subsidy by height 104:
  - `8,400,000 + 4,635 = 8,404,635 HOBC`

Wallet proof after step 15:

- trusted = `8,400,134.99998590`
- immature = `4,500.00001410`
- trusted + immature = `8,404,635.00000000`

This exactly matches subsidy issuance (`8,404,635`) and separately reflects fee redistribution (`0.00001410`) inside wallet balances.

## Final pass/fail

- PASS
