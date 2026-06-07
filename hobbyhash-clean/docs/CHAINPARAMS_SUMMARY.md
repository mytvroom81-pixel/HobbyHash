# HobbyHash Coin Chain Parameters Summary

## Final identity
- Coin name: HobbyHash Coin
- Ticker: HOBC
- Address HRP (mainnet): `hobc`
- Address HRP (testnet): `thobc`
- Address HRP (regtest): `rhobc`
- PoW: SHA-256

## Ports
- Mainnet P2P: `18761`
- Mainnet RPC: `18762` (localhost use)
- Testnet P2P: `28761`
- Testnet RPC: `28762` (localhost use)
- Regtest P2P: `38761`
- Regtest RPC: `38762` (localhost use)

## Network magic bytes
- Mainnet message start: `c1 a0 f1 ce` (`0xc1 0xa0 0xf1 0xce`)
- Testnet message start: `fc c1 b7 dc` (`0xfc 0xc1 0xb7 0xdc`)
- Regtest message start: `da b5 c3 f1` (`0xda 0xb5 0xc3 0xf1`)

## Base58 prefixes
- Mainnet:
  - PUBKEY_ADDRESS: `40` (`0x28`)
  - SCRIPT_ADDRESS: `95` (`0x5f`)
  - SECRET_KEY: `212` (`0xd4`)
- Testnet:
  - PUBKEY_ADDRESS: `65` (`0x41`)
  - SCRIPT_ADDRESS: `125` (`0x7d`)
  - SECRET_KEY: `191` (`0xbf`)
- Regtest:
  - PUBKEY_ADDRESS: `66` (`0x42`)
  - SCRIPT_ADDRESS: `126` (`0x7e`)
  - SECRET_KEY: `192` (`0xc0`)

## Consensus timing and maturity
- Block target: `150` seconds
- Coinbase maturity: `100` blocks
- Halving interval: `840,000` blocks

## Supply model
- Total target supply: `84,000,000 HOBC`
- Launch reserve at block `1`: `8,400,000 HOBC`
- Normal mining target: `75,600,000 HOBC`
- Normal subsidy from block `2` onward: `45 HOBC`, halved every `840,000` blocks
- Genesis block remains non-spendable by design and is not used for the launch reserve.

## Launch reserve and subsidy behavior
- Block `0`: subsidy forced to `0` in consensus subsidy logic.
- Block `1`: fixed subsidy `8,400,000 * COIN` (public launch reserve issuance).
- Block `2+`: starts at `45 * COIN` and follows halving schedule.
