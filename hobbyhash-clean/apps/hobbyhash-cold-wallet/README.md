# HobbyHash Cold Wallet

Offline, non-custodial Windows cold storage wallet for HobbyHash Coin (HOBC).

Version: `0.1.0-beta`

## Security Model

- Runs as a local Electron desktop app.
- Loads no external URLs.
- Blocks HTTP, HTTPS, FTP, WS, and WSS requests in the Electron session.
- Uses `contextBridge` through `src/preload.js`; renderer `nodeIntegration` is disabled.
- Does not include analytics, auto-updaters, RPC clients, explorer calls, or broadcasting.
- Does not send, upload, log, or store plaintext recovery phrases or private keys on any server.
- Optional local storage encrypts the mnemonic with `scrypt` and `aes-256-gcm` before writing to the Electron user profile folder.

## HOBC Network Values

The active config lives in `src/hobc-network.json`.

- Mainnet bech32 HRP: `hobc`
- Mainnet pubkey prefix: `40`
- Mainnet script prefix: `95`
- Mainnet WIF prefix: `212`
- Receive derivation path: `m/84'/8761'/0'/0/index`

Confirm final address prefixes, xpub/xprv versions, and BIP44 coin type before mainnet wallet release.

## Commands

```bash
npm run start
npm run dist:portable
npm run dist:win
```

## Offline Signing Input

The signing screen accepts raw PSBT base64, JSON with `psbtBase64` or `psbtHex`, or custom unsigned transaction JSON:

```json
{
  "format": "hobc-offline-transaction-v1",
  "inputs": [
    {
      "txid": "previous_transaction_id",
      "vout": 0,
      "amountSats": "100000000",
      "address": "hobc1...",
      "addressIndex": 0
    }
  ],
  "outputs": [
    {
      "address": "hobc1...",
      "amountSats": "99000000"
    }
  ]
}
```

The app signs locally and exports a signed PSBT or signed transaction JSON. Broadcasting belongs to a separate online wallet/explorer flow.
