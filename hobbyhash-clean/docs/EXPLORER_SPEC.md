# HOBC Explorer Specification

## Purpose

The HOBC explorer shows public HobbyHash Coin chain data without inventing missing data. Blocks, txids, address scans, reserve labels, burn labels, and supply-related values must come from the local HOBC node or the dedicated HOBC explorer database.

## Data Source

Primary source for the basic explorer:

- Local HOBC node RPC on `127.0.0.1`.
- Public PHP page: `/home/hobbyhashcoin/public_html/explorer/index.php`.
- Public status API: `/api/explorer/status`.

Future indexed source:

- Local explorer app bound to `127.0.0.1:18765`.
- Dedicated database: `hobbyhash_explorer`.
- Local app router: `/home/hobbyhashcoin/hobbyhash-clean/explorer/app.php`.

Do not use any MBUX, BTC, BCH, DGB, old HOBC, or shared explorer database.

## Database Name

The only explorer database for this site is:

```text
hobbyhash_explorer
```

Initial schema file:

```text
/home/hobbyhashcoin/hobbyhash-clean/explorer/schema.sql
```

The schema includes:

- `explorer_sync_state`
- `blocks`
- `transactions`
- `addresses`
- `tx_outputs`
- `tx_inputs`
- `known_labels`

## Sync Method

The indexer should sync forward from the last indexed height in `explorer_sync_state`.

Basic flow:

1. Call `getblockchaininfo`.
2. If RPC is unavailable, set explorer status to `offline`.
3. If `initialblockdownload` is true or indexed height is behind node height, set status to `syncing`.
4. Fetch block hashes with `getblockhash`.
5. Fetch block data with `getblock`.
6. Fetch transaction details with `getrawtransaction` when transaction indexing is available.
7. Store only real decoded blocks, txids, addresses, and outputs.

The public page may use direct local RPC until the full indexer is ready.

## RPC Methods

Allowed local RPC methods for the basic explorer:

- `getblockchaininfo`
- `getblockhash`
- `getblock`
- `getrawtransaction`
- `scantxoutset`

RPC must stay localhost-only. Credentials must stay in server-side config outside `public_html` when possible. Raw RPC errors must not be exposed to the browser.

## Local App Binding

The local explorer app must bind only to:

```text
127.0.0.1:18765
```

It must not listen publicly. Public traffic should go through the website page or a controlled reverse proxy route.

Basic local run command:

```bash
php -S 127.0.0.1:18765 /home/hobbyhashcoin/hobbyhash-clean/explorer/app.php
```

Local app JSON routes:

- `GET /status`
- `GET /blocks/latest`
- `GET /search?q=VALUE`

## Public Routes

Current public route:

```text
/explorer/
```

Search route:

```text
/explorer/?q=SEARCH_VALUE
```

Supported searches:

- Block height.
- Block hash.
- Txid.
- HOBC address.

Status route:

```text
/api/explorer/status
```

## Search Behavior

Search by height:

- If numeric, call `getblockhash HEIGHT`.
- Then call `getblock HASH`.
- If no real block exists, show no result.

Search by block hash:

- If value is 64 hex characters, try `getblock HASH`.
- If a block is found, render block details and txids.

Search by txid:

- If block hash lookup fails, try `getrawtransaction TXID true`.
- If transaction data is unavailable, show no result.
- Never invent txids or transaction details.

Search by address:

- If value looks like a HOBC address, use `scantxoutset`.
- Show current UTXO amount only if the scan succeeds.
- Full address history remains `not_available` until the explorer index is ready.
- Never fake address balances or transaction history.

## Launch Reserve Labeling

HOBC consensus assigns the 8,400,000 HOBC launch reserve subsidy at block height `1`.

The explorer must label block height `1` as:

```text
Launch reserve block
```

Reserve addresses must be labeled only when official reserve address tracking is connected. Until then, reserve addresses and balances must show `not_available`.

## Burn Address Labeling

The configured public burn address is:

```text
hobc1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqf9lpf8
```

The configured burn scriptPubKey is:

```text
00140000000000000000000000000000000000000000
```

The explorer must label this address as a burn address when it appears in address or transaction output results.

## No Fake Data Rule

The explorer must never fake:

- Blocks.
- Txids.
- Address balances.
- Address history.
- Supply.
- Reserve balances.
- Burn transactions.
- Market price.
- Market cap.

Unavailable data must be shown as one of:

- `offline`
- `syncing`
- `pending_launch`
- `not_available`

## Current Limitations

The current public page is a basic RPC-backed explorer. It can show current height, latest blocks, latest txids from block tx lists, block search, txid search when `getrawtransaction` is available, and address UTXO scans.

Full address history requires the `hobbyhash_explorer` indexer to be implemented and synced.
