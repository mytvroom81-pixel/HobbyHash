# HOBC Website Stats API Specification

## Purpose

The website API powers public HOBC portal status cards, mining setup pages, stats, explorer readiness, wallet health, reserve transparency, and burn tracking.

All endpoints return JSON. No endpoint may expose RPC credentials, private wallet data, admin-only information, private keys, wallet passphrases, or user balances.

## Honest Status Rule

If data is unavailable, return an honest status:

- `syncing`
- `offline`
- `pending_launch`
- `not_available`

Never return made-up:

- Hashrate.
- Workers.
- Blocks.
- Txids.
- Market price.
- Market cap.
- Burns.
- Wallet balances.

## Common Response Fields

Recommended fields:

```json
{
  "ok": true,
  "status": "online",
  "source": "local_rpc",
  "updated_at": "2026-06-01T00:00:00Z"
}
```

If unavailable:

```json
{
  "ok": false,
  "status": "offline",
  "message": "HOBC node RPC is unavailable."
}
```

## Endpoint: `/api/chain/status`

Tries local HOBC RPC only. Config and credentials stay server-side and outside browser output.

Should return when available:

- `chain`
- `blocks`
- `headers`
- `bestblockhash`
- `difficulty`
- `verificationprogress`
- `initialblockdownload`
- `mediantime`
- `mempool_tx_count` if available
- `networkhashps` if available

Unavailable behavior:

- RPC unavailable: `status: offline`.
- Node syncing: `status: syncing`.
- Optional fields unavailable: field value `not_available`.

## Endpoint: `/api/chain/latest-blocks`

Should return latest real blocks from local RPC when available:

- `height`
- `hash`
- `time`
- `tx_count`

Unavailable behavior:

- RPC unavailable: `status: offline`, `blocks: []`.
- RPC synced but block details unavailable: `status: not_available`, `blocks: []`.

No fake block hashes or fake tx counts.

## Endpoint: `/api/chain/supply`

Should return:

- `total_target_supply`: `84000000.00000000`
- `launch_reserve`: `8400000.00000000`
- `normal_mining_target`: `75600000.00000000`
- `current_height` if available
- `estimated_minted_supply` if calculable from chain height and known subsidy schedule
- `burned_supply` if real burn tracking is available
- `circulating_supply` only if calculable from real chain/reserve/burn data

Unavailable behavior:

- Chain height unavailable: `status: offline` or `not_available`.
- Burn tracking unavailable: `burned_supply: not_available`.
- Reserve balance unavailable: `reserve_balance: not_available`.
- Circulating supply unavailable: `circulating_supply: not_available`.

## Endpoint: `/api/pool/main/status`

Should return:

- `status`: `online`, `offline`, or `not_available`
- `pool`: `main`
- `stratum_url`: `stratum+tcp://pool.hobbyhashcoin.com:5555`
- `stratum_port`: `5555`
- `status_port`: `18763`
- `start_diff`: `5000`
- `solo_only`: `true`
- `workers` if available
- `hashrate` if available
- `accepted_shares` if available
- `rejected_shares` if available
- `last_share` if available
- `last_block` if available

Unavailable behavior:

- Pool status cannot be read: `status: offline` or `not_available`.
- Individual stats unavailable: field value `not_available`.

## Endpoint: `/api/pool/nano/status`

Should return:

- `status`: `online`, `offline`, or `not_available`
- `pool`: `nano`
- `stratum_url`: `stratum+tcp://pool.hobbyhashcoin.com:5556`
- `stratum_port`: `5556`
- `status_port`: `18764`
- `start_diff`: `0.005`
- `solo_only`: `true`
- `workers` if available
- `hashrate` if available
- `accepted_shares` if available
- `rejected_shares` if available
- `last_share` if available
- `last_block` if available

Unavailable behavior matches Main Pool.

## Endpoint: `/api/wallet/status`

Public wallet health only. Never return user balances, addresses, sessions, withdrawals, deposits, or private txids.

Should return:

- `status`: `online` or `offline`
- `deposits`: `enabled` or `paused`
- `withdrawals`: `enabled` or `paused`
- `scanner_status`
- `maintenance_mode`
- `custodial`: `true`
- `risk_notice`

Unavailable behavior:

- DB unavailable: `status: offline`.
- Scanner unknown: `scanner_status: not_available`.

## Endpoint: `/api/explorer/status`

Should return:

- `status`: `online`, `syncing`, `offline`, or `not_available`
- `synced_height` if available
- `chain_height` if available
- `latest_blocks_available`
- `latest_transactions_available`
- `search_available`

Unavailable behavior:

- Explorer not built: `status: syncing` or `not_available`.
- Chain RPC unavailable: `status: offline`.

## Endpoint: `/api/reserve/status`

Should return:

- `status`
- `total_supply`: `84000000.00000000`
- `launch_reserve`: `8400000.00000000`
- `reserve_percent`: `10`
- `reserve_addresses` when known
- `current_balances` when available
- `outgoing_transactions` when available
- `categories`

Unavailable behavior:

- Balances unavailable: `current_balances: not_available`.
- Transactions unavailable: `outgoing_transactions: not_available`.

## Endpoint: `/api/burn/status`

Should return:

- `status`
- `burn_addresses` when known
- `total_burned` when available
- `yearly_burn_plan` when approved
- `burn_transactions` when available

Unavailable behavior:

- No burn tracking yet: `status: pending_launch` or `not_available`.
- No fake burns and no fake burn txids.

## Security Rules

- RPC calls must be localhost only.
- RPC credentials must stay in server-side config outside `public_html` when possible.
- All shell/CLI calls must use fixed executable paths, fixed config paths, argument escaping, and timeouts.
- Public API must sanitize errors and never echo raw config or backend exception traces.
- Wallet status must never reveal user balances or private wallet data.
