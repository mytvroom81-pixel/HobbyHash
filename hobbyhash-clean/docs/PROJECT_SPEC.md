# HobbyHash Coin Project Specification

## Final coin identity
- Name: HobbyHash Coin
- Ticker: HOBC
- Brand: HOBC
- Address HRP: hobc
- SHA-256 PoW
- Home solo miner focused
- Nano miner friendly
- Full custodial web wallet
- Two solo-only ckpool instances
- One unified information-rich website portal

## Supply model
- Total supply target: 84,000,000 HOBC
- Launch reserve: 8,400,000 HOBC
- Launch reserve percentage: 10%
- Normal mining target: 75,600,000 HOBC
- Genesis block is custom but not treated as spendable
- Block 1 creates the public launch reserve
- Block 2 onward uses normal mining subsidy
- Starting normal subsidy after block 1: 45 HOBC
- Halving interval: 840,000 blocks
- Coinbase maturity: 100 blocks

## Launch reserve split
- Development/build fund: 2,940,000 HOBC
- Exchange/liquidity fund: 2,100,000 HOBC
- Infrastructure/security fund: 1,260,000 HOBC
- Home-miner rewards fund: 1,260,000 HOBC
- Annual burn reserve: 840,000 HOBC

## Ports
- Mainnet P2P: 18761
- Mainnet RPC: 18762 localhost only
- Testnet P2P: 28761
- Testnet RPC: 28762 localhost only
- Regtest P2P: 38761
- Regtest RPC: 38762 localhost only
- Main pool stratum: 5555
- Nano pool stratum: 5556
- Explorer local app: 18765 localhost only
- Custodial wallet local app: 18766 localhost only

## Pool requirements
- Main pool start difficulty(port: 5555): 5000
- Nano pool start difficulty(port: 5556): 0.005
- Both pools solo only
- Payout to address in worker name
- Worker format: hobc_ADDRESS.workername
- Invalid BTC/BCH/DGB/MBUX addresses rejected
- Invalid random text rejected
- No fake shares
- No fake blocks

## Wallet requirements
- Full custodial wallet
- Server controls hot wallet funds
- User balances stored in internal ledger
- Deposits credited after confirmations
- Withdrawals create real txids only after real broadcast
- Visible custodial risk notice
- No fake balances
- No fake transactions
- No RPC credentials in browser

## Website requirements
- One unified HOBC portal
- Shared nav/header/footer/status bar
- User friendly
- Mobile friendly
- Lots of info and stats
- No fake stats
- Unavailable data must say syncing/offline/pending/not available
