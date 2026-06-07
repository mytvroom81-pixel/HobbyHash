# HobbyHash Clean Rebrand Log

## Source version used
- Upstream base: Bitcoin Core `v27.1`
- Base commit: `1088a98f5aad080cc6cca2da174f206509fcda6c`
- Source origin: clean upstream `bitcoin/bitcoin` clone into `/home/hobbyhashcoin/hobbyhash-clean/src`

## Files renamed
- Added HOBC manpages from clean upstream templates:
  - `doc/man/hobbyhashd.1`
  - `doc/man/hobbyhash-cli.1`
  - `doc/man/hobbyhash-tx.1`
  - `doc/man/hobbyhash-util.1`
  - `doc/man/hobbyhash-wallet.1`
- Added HOBC sample config from clean upstream template:
  - `share/examples/hobbyhash.conf`

## Binaries renamed
- `bitcoind` -> `hobbyhashd`
- `bitcoin-cli` -> `hobbyhash-cli`
- `bitcoin-wallet` -> `hobbyhash-wallet`
- `bitcoin-tx` -> `hobbyhash-tx`
- `bitcoin-util` -> `hobbyhash-util`

## Config names changed
- Default config filename changed:
  - `bitcoin.conf` -> `hobbyhash.conf`
- Default data directory changed:
  - `.bitcoin` -> `.hobbyhash`
  - Windows default path segment `Bitcoin` -> `HobbyHash`
  - macOS default path segment `Bitcoin` -> `HobbyHash`

## User-facing strings changed
- Build/package public name:
  - `Bitcoin Core` -> `HobbyHash Core` (configure package name and generated man/sample-conf branding)
- Public unit text in Qt money units:
  - `BTC` -> `HOBC`
  - `mBTC` -> `mHOBC`
  - `uBTC` -> `uHOBC`
  - `Bitcoins` -> `HobbyHash Coin`
  - `Milli-Bitcoins` -> `Milli-HOBC`
  - `Micro-Bitcoins` -> `Micro-HOBC`

## Constants changed
- `src/common/args.cpp`
  - `BITCOIN_CONF_FILENAME` now uses `hobbyhash.conf`
  - Default datadir return values now use `HobbyHash` / `.hobbyhash`
- `src/qt/bitcoinunits.cpp`
  - `MAX_DIGITS_BTC` symbol renamed to `MAX_DIGITS_HOBC`
- `configure.ac`
  - `AC_INIT` package public name now `HobbyHash Core`
  - daemon/CLI/wallet/tx/util binary name variables now use `hobbyhash*`

## Places intentionally left alone (license/history/build safety)
- Copyright headers and license files (`COPYING`, source file copyright blocks) were not rewritten.
- Internal library names and broad `BITCOIN_*` identifiers used for build plumbing were left intact unless required for requested output binary names.
- Test suites, wallet support, RPC, and mining/getblocktemplate RPC paths were not removed.
- Historical release notes and legacy upstream documentation corpus were not mass-rewritten to avoid unsafe broad edits.
- `bitcoin-node`, `bitcoin-chainstate`, and `bitcoin-qt` targets were intentionally left unchanged in this step because they were not part of the requested rename list.
