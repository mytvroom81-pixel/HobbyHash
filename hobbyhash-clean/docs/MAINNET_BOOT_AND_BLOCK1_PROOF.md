# HOBC Mainnet Boot and Block 1 Proof

Status: PARTIAL PASS (boot checks pass, block-1 mining pending by design safeguard)

## Pre-start controlled checks

### 1) Confirm no old HOBC process running

Command:

`ps -eo pid,user,comm,args | grep -Ei '(^|/)hobbyhashd|old[[:space:]]+h(o)?bc|hobbyhash mainnet' | grep -v grep || true`

Output before start:

`(no output)`

### 2) Confirm no MBUX service touched
### 3) Confirm no BTC/BCH/DGB service touched
### 4) Confirm no solobitminers service touched

Command:

`systemctl list-units --type=service | grep -Ei 'mbux|bitcoin|bch|dgb|solobitminers|hobbyhash|hobc' || true`

Output before start included only existing BTC/BCH/DGB/MBUX/SoloBitMiners services and no HOBC service. No stop/restart commands were run against them.

### 5) Confirm port 18761 is free
### 6) Confirm RPC port 18762 is free

Command:

`ss -lntup | grep -E '(:18761|:18762)\b' || true`

Output before start:

`(no output)`

### 7) Confirm datadir path

Command:

`ls -ld /home/hobbyhashcoin/hobbyhash-data/mainnet`

Output:

`drwxr-xr-x 2 hobbyhashcoin hobbyhashcoin 6 May 30 23:40 /home/hobbyhashcoin/hobbyhash-data/mainnet`

### 8) Confirm config path

Command:

`ls -ld /home/hobbyhashcoin/hobbyhash-conf/hobbyhash-mainnet.conf`

Output:

`-rw-r--r-- 1 hobbyhashcoin hobbyhashcoin 174 May 30 23:40 /home/hobbyhashcoin/hobbyhash-conf/hobbyhash-mainnet.conf`

Mainnet config used:

```ini
server=1
listen=1
daemon=1

datadir=/home/hobbyhashcoin/hobbyhash-data/mainnet

port=18761
rpcport=18762
rpcbind=127.0.0.1
rpcallowip=127.0.0.1

fallbackfee=0.0001
txindex=1
```

This proves RPC is localhost-only by config (`rpcbind=127.0.0.1`, `rpcallowip=127.0.0.1`).

### 9) Confirm final launch reserve addresses/scripts are set (not fake placeholders)

Source check command:

`rg "genesisOutputScript|OP_TRUE|8400000" /home/hobbyhashcoin/hobbyhash-clean/src/src/kernel/chainparams.cpp`

Output evidence:

- `const CScript genesisOutputScript = CScript() << OP_TRUE;`

Result:

- **NOT FINALIZED** for launch reserve split script outputs.
- This is treated as a safeguard blocker for block-1 mining in this proof run.

## Start mainnet

Command:

`/home/hobbyhashcoin/bin/hobbyhashd -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-mainnet.conf`

Output:

`HobbyHash Core starting`

## RPC proofs

### getblockchaininfo

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-mainnet.conf getblockchaininfo`

Output:

```json
{
  "chain": "main",
  "blocks": 0,
  "headers": 0,
  "bestblockhash": "00000000a746a8a7dba5237b7f9c92cb1b2690cb53ab2958ce76a506b1ea96af",
  "difficulty": 1,
  "time": 1780208106,
  "mediantime": 1780208106,
  "verificationprogress": 1,
  "initialblockdownload": false,
  "chainwork": "0000000000000000000000000000000000000000000000000000000100010001",
  "size_on_disk": 208,
  "pruned": false,
  "warnings": ""
}
```

Proof points:
- chain is `main`
- blocks are `0` before mining
- genesis hash is custom HOBC genesis `00000000a746a8a7...`

### getnetworkinfo

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-mainnet.conf getnetworkinfo`

Output:

```json
{
  "version": 270100,
  "subversion": "/Satoshi:27.1.0/",
  "protocolversion": 70016,
  "localservices": "0000000000000c09",
  "localservicesnames": [
    "NETWORK",
    "WITNESS",
    "NETWORK_LIMITED",
    "P2P_V2"
  ],
  "localrelay": true,
  "timeoffset": 0,
  "networkactive": true,
  "connections": 0,
  "connections_in": 0,
  "connections_out": 0,
  "networks": [
    {
      "name": "ipv4",
      "limited": false,
      "reachable": true,
      "proxy": "",
      "proxy_randomize_credentials": false
    },
    {
      "name": "ipv6",
      "limited": false,
      "reachable": true,
      "proxy": "",
      "proxy_randomize_credentials": false
    },
    {
      "name": "onion",
      "limited": true,
      "reachable": false,
      "proxy": "",
      "proxy_randomize_credentials": false
    },
    {
      "name": "i2p",
      "limited": true,
      "reachable": false,
      "proxy": "",
      "proxy_randomize_credentials": false
    },
    {
      "name": "cjdns",
      "limited": true,
      "reachable": false,
      "proxy": "",
      "proxy_randomize_credentials": false
    }
  ],
  "relayfee": 0.00001000,
  "incrementalfee": 0.00001000,
  "localaddresses": [
  ],
  "warnings": ""
}
```

### getmininginfo

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-mainnet.conf getmininginfo`

Output:

```json
{
  "blocks": 0,
  "difficulty": 1,
  "networkhashps": 0,
  "pooledtx": 0,
  "chain": "main",
  "warnings": ""
}
```

### getblocktemplate proof

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-mainnet.conf getblocktemplate '{"rules":["segwit"]}'`

Output:

```text
error code: -9
error message:
HobbyHash Core is not connected!
```

Result:
- Node booted mainnet correctly, but template request failed on isolated node with no peers.

## Create/load wallet launchminer and get mining address

Commands:

- `/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-mainnet.conf createwallet launchminer`
- `/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-mainnet.conf -rpcwallet=launchminer getnewaddress`

Outputs:

```json
{
  "name": "launchminer"
}
```

Mining address:

`hobc1qckfhkp644lrt3y78tt5m2v4lr5624ghuds4dkt`

Proof point:
- Address HRP is `hobc`.

## Block 1 mining and reserve proofs

Block 1 mined:
- **NO (pending)**

Reason:
- Launch reserve addresses/scripts are not finalized and still contain placeholder evidence (`OP_TRUE` in genesis template path). Per instruction, block 1 was not mined until final reserve scripts are correct.

Block 1 hash if mined:
- `PENDING`

Block 1 coinbase proof:
- `PENDING`

Reserve output proof:
- `PENDING`

Block 2 reward proof if mined:
- `PENDING`

## Emission/supply checks

- Normal mining begins at block 2 in current subsidy logic (configured previously), but this mainnet run intentionally did not mine due reserve-script safeguard.
- Genesis remains non-spendable by consensus design.
- Total emission cap verification beyond genesis/height 0 remains `PENDING` until block mining run with finalized reserve split scripts.

## Final pass/fail

- **PARTIAL PASS**
  - PASS: controlled boot, mainnet identity, custom genesis, HRP `hobc`, wallet/address creation, no external service touch.
  - PENDING/FAIL-OPEN: block 1/2 mining and reserve split proof intentionally blocked until reserve scripts are finalized (non-placeholder).
