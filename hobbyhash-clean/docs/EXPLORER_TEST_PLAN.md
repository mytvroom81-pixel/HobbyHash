# HOBC Explorer Test Plan

## 1. Node Offline Test

Goal: prove the explorer does not fake chain data when RPC is unavailable.

Steps:

1. Stop or block the local HOBC RPC service in a safe test environment.
2. Open `/api/explorer/status`.
3. Open `/explorer/`.

Expected result:

- API returns `status: offline`.
- Public page shows an offline or unavailable message.
- No fake blocks, txids, address balances, or supply are shown.

## 2. Node Online Test

Goal: prove the explorer reads the real local HOBC node.

Steps:

1. Start the HOBC node.
2. Confirm RPC is bound to localhost.
3. Open `/api/explorer/status`.
4. Open `/explorer/`.

Expected result:

- API returns `status: online` when the node is synced.
- Current height matches `getblockchaininfo.blocks`.
- Data source is local RPC.

## 3. Latest Block Test

Goal: prove latest blocks are real.

Steps:

1. Run `getblockchaininfo` and note the current height.
2. Open `/explorer/`.
3. Compare the first latest block height and hash with `getblockhash HEIGHT`.

Expected result:

- The latest block height matches the node.
- The latest block hash is real.
- No placeholder block hashes appear.

## 4. Block Search Test

Goal: prove height and block-hash search work.

Steps:

1. Search `/explorer/?q=1`.
2. Search for a known block hash from `getblockhash`.

Expected result:

- Height search returns the real block.
- Hash search returns the same real block.
- Block height `1` is labeled `Launch reserve block`.

## 5. Txid Search Test

Goal: prove txid lookup does not fake transaction data.

Steps:

1. Copy a txid from the latest transactions section.
2. Search `/explorer/?q=TXID`.

Expected result:

- If `getrawtransaction TXID true` is available, transaction details are shown.
- If transaction lookup is unavailable, the page says no real txid data was found.
- No fake tx details are shown.

## 6. Address Search Test

Goal: prove address lookup uses real UTXO scans only.

Steps:

1. Search for a valid HOBC address.
2. Search for the configured burn address.
3. Search for an invalid address.

Expected result:

- Valid address returns live `scantxoutset` UTXO data if available.
- Burn address is labeled as `Burn address`.
- Invalid address returns no result.
- Full address history remains `not_available` until the indexer is synced.

## 7. Launch Reserve Block Label Test

Goal: prove reserve labeling is visible and specific.

Steps:

1. Open `/explorer/?q=1`.

Expected result:

- Block height `1` displays `Launch reserve block`.
- The page explains the 8,400,000 HOBC launch reserve subsidy.
- Reserve addresses remain `not_available` unless official tracking is connected.

## 8. Syncing Status Test

Goal: prove incomplete sync is reported honestly.

Steps:

1. Test with a node where `initialblockdownload` is true, or simulate that status in a safe test environment.
2. Open `/api/explorer/status`.
3. Open `/explorer/`.

Expected result:

- API returns `status: syncing`.
- Public page reports syncing or incomplete data.
- Missing data is not replaced with fake data.

## 9. No Fake Data Test

Goal: prove the explorer never invents unavailable data.

Steps:

1. Search for a random 64-character hex string that is not a block hash or txid.
2. Search for a non-HOBC address.
3. Review reserve address output.
4. Review latest transaction output when tx lookup is unavailable.

Expected result:

- Random hash returns no real result.
- Non-HOBC address returns no real result.
- Reserve addresses show `not_available` until connected.
- No fake txids, blocks, address balances, supply, market price, or market cap appear.
