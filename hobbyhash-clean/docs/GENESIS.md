# HobbyHash Coin Genesis Records

Genesis timestamp string for all networks:

`HobbyCash Coin 2026 - solo mining for home hashers`

Output script for all networks:

`51` (OP_TRUE)

Genesis tool path:

`/home/hobbyhashcoin/hobbyhash-clean/scripts/genesis/hobbyhash_genesis`

Build command:

`g++ -O3 -std=c++20 -pthread /home/hobbyhashcoin/hobbyhash-clean/scripts/genesis/hobbyhash_genesis.cpp -lcrypto -o /home/hobbyhashcoin/hobbyhash-clean/scripts/genesis/hobbyhash_genesis`

## Regtest

- network: regtest
- timestamp string: HobbyCash Coin 2026 - solo mining for home hashers
- output script: `51`
- nTime: `1780208105`
- nNonce: `17`
- nBits: `0x207fffff`
- nVersion: `1`
- reward: `50`
- merkle root: `be535086a84506c2fb39f8e77a3065d5005d3a9b2ebcc9c1e3aa3162103b3f12`
- genesis hash: `703694a35a5a93e7c9250ebad17deadbd5a15f2d8d3c998b8e5f632b59ec6f80`
- exact command used: `/home/hobbyhashcoin/hobbyhash-clean/scripts/genesis/hobbyhash_genesis mine regtest 1780208105 207fffff 1 50 51 <threads> 120`
- verification command: `/home/hobbyhashcoin/hobbyhash-clean/scripts/genesis/hobbyhash_genesis verify regtest 1780208105 17 207fffff 1 50 51 be535086a84506c2fb39f8e77a3065d5005d3a9b2ebcc9c1e3aa3162103b3f12 703694a35a5a93e7c9250ebad17deadbd5a15f2d8d3c998b8e5f632b59ec6f80`
- pass/fail result: PASS

## Testnet

- network: testnet
- timestamp string: HobbyCash Coin 2026 - solo mining for home hashers
- output script: `51`
- nTime: `1780208105`
- nNonce: `402482918`
- nBits: `0x1d00ffff`
- nVersion: `1`
- reward: `50`
- merkle root: `be535086a84506c2fb39f8e77a3065d5005d3a9b2ebcc9c1e3aa3162103b3f12`
- genesis hash: `00000000b0c1e63758239b91207c27a9e62636a2d6b3bc3680eb00f13634e33c`
- exact command used: `/home/hobbyhashcoin/hobbyhash-clean/scripts/genesis/hobbyhash_genesis mine testnet 1780208105 1d00ffff 1 50 51 <threads> 900`
- verification command: `/home/hobbyhashcoin/hobbyhash-clean/scripts/genesis/hobbyhash_genesis verify testnet 1780208105 402482918 1d00ffff 1 50 51 be535086a84506c2fb39f8e77a3065d5005d3a9b2ebcc9c1e3aa3162103b3f12 00000000b0c1e63758239b91207c27a9e62636a2d6b3bc3680eb00f13634e33c`
- pass/fail result: PASS

## Mainnet

- network: mainnet
- timestamp string: HobbyCash Coin 2026 - solo mining for home hashers
- output script: `51`
- nTime: `1780208106`
- nNonce: `3854237270`
- nBits: `0x1d00ffff`
- nVersion: `1`
- reward: `50`
- merkle root: `be535086a84506c2fb39f8e77a3065d5005d3a9b2ebcc9c1e3aa3162103b3f12`
- genesis hash: `00000000a746a8a7dba5237b7f9c92cb1b2690cb53ab2958ce76a506b1ea96af`
- exact command used: `/home/hobbyhashcoin/hobbyhash-clean/scripts/genesis/hobbyhash_genesis mine mainnet 1780208106 1d00ffff 1 50 51 <threads> 900`
- verification command: `/home/hobbyhashcoin/hobbyhash-clean/scripts/genesis/hobbyhash_genesis verify mainnet 1780208106 3854237270 1d00ffff 1 50 51 be535086a84506c2fb39f8e77a3065d5005d3a9b2ebcc9c1e3aa3162103b3f12 00000000a746a8a7dba5237b7f9c92cb1b2690cb53ab2958ce76a506b1ea96af`
- pass/fail result: PASS
