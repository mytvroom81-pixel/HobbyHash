# HOBC Regtest Proof

Status: PASS

All commands were run with this CLI pattern:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf ...`

## 1) Start hobbyhashd with regtest config

Command:

`/home/hobbyhashcoin/bin/hobbyhashd -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf -daemonwait`

Output:

`HobbyHash Core starting`

## 2) Verify getblockchaininfo

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf getblockchaininfo`

Output:

```json
{
  "chain": "regtest",
  "blocks": 0,
  "headers": 0,
  "bestblockhash": "703694a35a5a93e7c9250ebad17deadbd5a15f2d8d3c998b8e5f632b59ec6f80",
  "difficulty": 4.656542373906925e-10,
  "time": 1780208105,
  "mediantime": 1780208105,
  "verificationprogress": 1,
  "initialblockdownload": false,
  "chainwork": "0000000000000000000000000000000000000000000000000000000000000002",
  "size_on_disk": 208,
  "pruned": false,
  "warnings": ""
}
```

## 3) Verify getnetworkinfo

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf getnetworkinfo`

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

## 4) Verify getmininginfo

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf getmininginfo`

Output:

```json
{
  "blocks": 0,
  "difficulty": 4.656542373906925e-10,
  "networkhashps": 0,
  "pooledtx": 0,
  "chain": "regtest",
  "warnings": ""
}
```

## 5) Verify getblocktemplate

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf getblocktemplate '{"rules":["segwit"]}'`

Output:

```json
{
  "capabilities": [
    "proposal"
  ],
  "version": 536870912,
  "rules": [
    "csv",
    "!segwit",
    "taproot"
  ],
  "vbavailable": {
  },
  "vbrequired": 0,
  "previousblockhash": "703694a35a5a93e7c9250ebad17deadbd5a15f2d8d3c998b8e5f632b59ec6f80",
  "transactions": [
  ],
  "coinbaseaux": {
  },
  "coinbasevalue": 840000000000000,
  "longpollid": "703694a35a5a93e7c9250ebad17deadbd5a15f2d8d3c998b8e5f632b59ec6f801",
  "target": "7fffff0000000000000000000000000000000000000000000000000000000000",
  "mintime": 1780208106,
  "mutable": [
    "time",
    "transactions",
    "prevblock"
  ],
  "noncerange": "00000000ffffffff",
  "sigoplimit": 80000,
  "sizelimit": 4000000,
  "weightlimit": 4000000,
  "curtime": 1780209360,
  "bits": "207fffff",
  "height": 1,
  "default_witness_commitment": "6a24aa21a9ede2f61c3f71d1defd3fa999dfa36953755c690689799962b48bebd836974e8cf9"
}
```

## 6) Create/load wallet `regtestminer`

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf createwallet regtestminer`

Output:

```json
{
  "name": "regtestminer"
}
```

## 7) Get new regtest HOBC address

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf -rpcwallet=regtestminer getnewaddress`

Output:

`rhobc1qtyv6trjx9e4mq0fnzzwl6cse2zm3dtvsrur0v7`

## 8) Mine block 1

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf -rpcwallet=regtestminer generatetoaddress 1 rhobc1qtyv6trjx9e4mq0fnzzwl6cse2zm3dtvsrur0v7`

Output:

```json
[
  "5dd702e0026efd95524232c4f643564a6b6b9940fdf3c36a52469d3357213180"
]
```

## 9) Verify block 1 launch reserve behavior

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf getblock 5dd702e0026efd95524232c4f643564a6b6b9940fdf3c36a52469d3357213180 2`

Output contains block-1 coinbase `vout[0].value = 8400000.00000000` and witness commitment output.

## 10) Mine block 2

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf -rpcwallet=regtestminer generatetoaddress 1 rhobc1q6yqur9ywpjfxzxkdfp45p8len0u9a2s4z0wtsu`

Output:

```json
[
  "0d5f5261bafccd4f86836721bfb1397e8c1498bac3de8a402b0c1bcd38216dc0"
]
```

## 11) Verify block 2 normal reward

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf getblock 0d5f5261bafccd4f86836721bfb1397e8c1498bac3de8a402b0c1bcd38216dc0 2`

Output contains block-2 coinbase `vout[0].value = 45.00000000`.

## 12) Mine 101 blocks

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf -rpcwallet=regtestminer generatetoaddress 101 <regtestminer address>`

Exact output:

```json
[
  "3160d67faa0665ea7af59585d8fc47b3652bef0ace5b962fe8f12f7b842ea017",
  "30fe257bdbc9e861f4829c0126bb2f578b1d886db693302f25476bfd5ac996f2",
  "788db06c3850de53bf888dd72ceec0f7bf4b4db2beb25cefb70f0de9e180110f",
  "4d1fb0e25ca052a2693f5cd36dd25e81ff4e38f9aaa0f604bc261a4c68658921",
  "6bc94adff4bfa1d0baeecc28753cb8cf23ba9a82ff5c78c7cab307ab67235b41",
  "376ef760c0539ff6d63e2c2394bd07fd3564495b517e5ad72286d461805bb496",
  "7103a094efa31d7c4582d3c7656b4f07260b45d7928339eff304be0eec5fcd88",
  "018a1006ed13e1ee38e45b177e5d59f20927e083292836d9adfef2f6b8fd801a",
  "7630868ef1961320af934aaa306a90e8ed480b9f81734cfaf78b701ba62d0eee",
  "39cb1e9f5ad032830c73ca8463e0ad30acab9c4da947d4de0d2f9addfeb25a2c",
  "5894a957364e712c7169ee4d310c3d0d01fbf105eec33c36ea75f4ad8b904f89",
  "24e59dcdfd621f7be5409ce8b4e422a43bc42159fbae0bed8a2486757ce418fc",
  "6dd4af8a82af98d739e2c5fef20d3209cf381f9d6d5d8770cdaa1a6e74d0cedc",
  "0b98a8e4926a652d6ed8f8165925c65713a5b08b47637e898ac365d19f67eade",
  "4a9bc6017fe78872d498461d808b92ee284472bee274461b2fb09f6f7176b432",
  "35a6ab14bbc804c51e4e7cf9f2eb8f057339170be873da6a49b70a090be8bb00",
  "715e15a29206b3dbe022fb23c3ad2261e43cfff780b4a02aa20e1c395c84b1f1",
  "235f88370921fe61db51f7ecacd7b749ce4bd716623ee2637560feac41ef6c97",
  "7a4157fd4ae00b3b07f32a7fbd17546bd543beb9068a6016d714e3e3534e2ce8",
  "5a5ed099832bc90a4a91478922461d065cee072036150e7b9d5016af0dcbe8a5",
  "106f179a717c120b9f177e201de0a160832be6f29fbe9cd1e4a0fed9fc104946",
  "5725c41483f41150e468b85f66f6740930b56d8848334df2a10fd93d554121dc",
  "66447637afab3707de660642bc84c1d190ef282ba97c4ca769f52b3d16e96efe",
  "496dfb9cb496c8456f328da7b487b93d1c908dfdba7481b57643d45f0b80a742",
  "20027a8d9d81eb14da32eb686eb1f4d3a641154db3b1e2049061c65934b06cb3",
  "69ae3db91a5789da2af1594a8e7d573f9c72e4eff5d7ccaeac70bb40922a915d",
  "7cfe8566e1e16abc6c47f1373d2e002c6391ad0259073ad4ed703c9a32d2fb7e",
  "46429bd7fd049e2514154dc3902aef69adfc0ce39b2ef1add46dd3767104f235",
  "75e7c69939090606d79cce3d62cb082d15c627a5bbb2f31a431c34381d34796f",
  "2f7d900539285ba78a4ef7ccf58edce80574c27a1da02b45efe43ce733e4e547",
  "28dd5b87bd86e97f4c40dc205c37b95c4a7b1eb4b2ae4fc2c0dc796fe847d97d",
  "2b53f87bf0b07b6901846ecfa4db77443b6c624d04bebcbfce1b84217ce68d95",
  "25548e354653da45150951c657a208c70ea6493f43bd1c1f46fd73bcc35fcfb7",
  "2e1aaad1155696df1fbca582b0273c42c4c71cc6e55158103cf58fa3a61df09f",
  "7c4c5dec718ec023fc3eaa37cc0c5bbc1a988a97c6a124fa506ce24472b857a5",
  "12692b711e480faef04f8870683e32726070ca8c0f0d7e0ac462670f3abe22f5",
  "34cb02ce3a1ac4228fb35cc0341b90f0a3016333d73155bd48c8844980c6c5a9",
  "22b89649d356a1d9e05c4fc9bb96880a2fcdcda1119fded5930fee0f8368148d",
  "53fd0e38fba2478c745fd8c1c8d2b14077b44428b089e6af9bbddeaf5f293541",
  "1812af6d58804bce71a63dc128c2c9c831636c9476bbe14490cdbbea77238b18",
  "19e018d8feb1aadfcec13b5c24bdfdbbc174e1d73bf09845aef4258dd9f7e3fb",
  "7360bdc42f6bacdb326940aaa0bea7c9c939df4d071b959d4be6b5401c449864",
  "1286b878dfadb71e4fcae844ce6a0f4c61783c0379c33c878ab31c8736fc7d7e",
  "5dfb622276253698fc3571da9405dbf3499928d9ff99857896f0c0b36954b724",
  "38e69ddbe4fd139ac98177ef1746a36f442a13b94dee9b3db13c30afe464ad2d",
  "5a1c0c9ce5e5d82b831cad18b1dd6c4387ca9eae01db3c020fe02a9d6fe84a57",
  "103b546f7f56e2ee84698fe92895a810e53f14d726db3efd2b8d1656568bd15d",
  "1a66136ee4dbaa97979d13beea2418f84990c3cd0753f0c6afda3b7191bef484",
  "662ee9475291dffab2ffe1b6bd2ef5f64e32c5ea4c8415598e6eb155a50c5ed4",
  "3f1df0b9e10a734488e1f49a308465168c0e4fc514624d8dc912a21bd161b641",
  "500c1b9f6fd836a7f55f4b7a19b68de56c9c8392399a4a6457e384bd4c122f6a",
  "4d11600f13e1d11fb1f21455ee066509006c1f446cc07c5cf10dce8f455062f9",
  "63293df1cadf3faba76cb90ccfc10bf8d0a8fa1753d759d631a87c9190547fd2",
  "3739d60858d960e9b53dfad70e52d1f321a40256f26149324d86b4e4ed2dadc4",
  "1fd3d8150b9585d5c298741e6fe11d03e6afdfe61a3a253a2708d4e76da031b6",
  "697d0c1460c3395170d3ea99d0a09af21a316348ee3205f9652d8638640c3d7a",
  "6ad768e4c40408458d7e61f4869a3d01efb151fbc85c9846998eaf3704c73478",
  "64b69e454f944c855df41c75d935697659f372b025676dbf5aa724e93736e066",
  "03309be7d36585c631bd1ac47e2adff8a0ab97e84a1be467ef732d796a284c3c",
  "5db0fb676c23f32ffcf461b4771a9bc5f8b320f45f8210c2e004b362ba70458d",
  "0c072a2192618898585e2133f2b8b8b1ddccb32beeed5f757eb86af1f9e2105a",
  "6e7d9245a7447cfe7f0f5e4cd75eed3cb42cec6dbf98346417c7b97d012a4d40",
  "1ea5c4355ac614a993a1cd4ba690adcdd23093dd00d9f3a510d7b53737e4ff88",
  "61389366210a277dd9c689e305cbb5680496c636d3c9b46d0ed71ac420d5039a",
  "6ad71424fd7764ca4fd6173a793a7ecb7b6aa2e7e1b39e9c99d7ea9077648f3e",
  "2ed816ae486eaf2a5b799fed2b8ab9d6a74507cc470e2b3ddb584822cdf94882",
  "20b25fc4de7d1239c3d127ce717f00192aa405ee0ead90fde8f525483f82b79a",
  "6c31af662bc6a5971080862c11ed6b5ba113231be0c4558bc4cf7e20b10b3932",
  "23d4f449716dc50a002e6fde7ba00fcbb941af6f98fc1b505a3d23847bb11c6f",
  "68f18a25e77f83122707b7a476c4244d90d80ed8154d0e0934955f502b0c8a26",
  "3126483798e8b80fab715b2d776ed995371faee6ebaabe43266823a6ecc085fb",
  "4ef21712baa307b2ca299149e7fab7cc906f75dcd8c0a2667ad5a22bbaafe3f5",
  "3a518c370d984f6befdb262be4d0eb2ce2b219eec26ead1224567a51f2c6bf4c",
  "69c77ade09ded8f53e861356ef41ea1fa82d2d37626a05a5ce610c0ef35e59a3",
  "5e4b45df4e4cec5f9f59e8178d503f8efeda466b59a235995bac3bb02e24b0ff",
  "5a87c284041d19561971c8474d8499480128371ecdf71b9ef86ea158d9386b6c",
  "2db7ad26b33cfb7c55dabb233140d35d997193c1dc3b43d1dc739aa7f032365a",
  "1ce3fc29962439ef34855abe37122310096c2f711e42d0383c759b3c64adedac",
  "1b42e9cbac91d1a0bf0964743b00484ddd5b9be177301504381b49f67beec286",
  "75e8e65bd4d3c04c44a6d867d329999f23d4e5dbc45eb379eb7394a52b67e526",
  "67ac24eb15894849d394fe0973209b0b8708c97c7009793268025152072455e2",
  "6a23ad15fe818a077c25a2341d14f24e54690223c0972f45530ad6bab521c55f",
  "7e4b51e47bbadc7fa345539b6f748efdd1a84f62dc5b5e25a1254c1e5ae4862c",
  "590fa40e8daff1cfedddc49e642af48b5aed647457efd2fd2b90cb9dc2ca0a95",
  "00fed8fb86fde77149aa6de7fecdea9c30681ce1882d1bde2dbad6703bcc76f8",
  "0e86672f30b7b233112b3e6de17e574b51179b34abd713f0f25885fde2a848cd",
  "384b0b1676296c515b788a95b8f1b6e4477bbbc9b798139076966abe3a247f21",
  "39269194c96e9622f7f75ca385f3c6fb4fbd138f9f832f911564e55c79bca1c9",
  "5983598ffd9bc57572acc281172c692767d3eaecaab84ee422b79231647c9d63",
  "0e1513fbb419f568fd74516c529e64b79cae7829071ae4979257be04d3e078d6",
  "268a0fad775a37d0a6d90157e9ffc05a93d986c29b373cfe963f67be9ad96050",
  "67d343b7f785071ef6c0f9029c101cc5d756f5237365d39652a2a7c69c56b7c9",
  "77137f466b6924ddeb0716790ef22e75c3aeea047256314a09c8af92004ba619",
  "318b3368ca5a7eca2c2054614e444c2096b950be90956ceb7bbdb61fbce508d4",
  "36eee284c6b9904015ba5ff225e937e7ae63da0020290f4473bed6ae83ff2630",
  "754a015db23124c293adf6f1c78206d0aeba6315574ec3230c1978a509f16741",
  "580ffd06e5e21bc98024a03f639aee268367a56546ddd496eeae21bc69f6d214",
  "0367ecc2887313d10d49c8db9de54a7cfb3b8b3ef1a5e805417bdba45a991f1c",
  "5eee4e70154e79c3fd7a7d59624729b0e8b0d594cd5110f5f3eccdcc305da40d",
  "2f1fbf8b152b2a6497aba3a26d2e7457cca39d1ed830a3b70197e94f207e4faa",
  "2e9d2c1dc20a33207f0a56e9f7c1d22f7dbb63234210b68eb94ef19405865317"
]
```

## 13) Confirm coinbase maturity

Commands:

- `/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf -rpcwallet=regtestminer getbalances`
- `/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf -rpcwallet=regtestminer getwalletinfo`

Outputs:

```json
{
  "mine": {
    "trusted": 8400090.00000000,
    "untrusted_pending": 0.00000000,
    "immature": 4500.00000000
  },
  "lastprocessedblock": {
    "hash": "2e9d2c1dc20a33207f0a56e9f7c1d22f7dbb63234210b68eb94ef19405865317",
    "height": 103
  }
}
```

```json
{
  "walletname": "regtestminer",
  "walletversion": 169900,
  "format": "sqlite",
  "balance": 8400090.00000000,
  "unconfirmed_balance": 0.00000000,
  "immature_balance": 4500.00000000,
  "txcount": 103,
  "keypoolsize": 4000,
  "keypoolsize_hd_internal": 4000,
  "paytxfee": 0.00000000,
  "private_keys_enabled": true,
  "avoid_reuse": false,
  "scanning": false,
  "descriptors": true,
  "external_signer": false,
  "blank": false,
  "birthtime": 1780209366,
  "lastprocessedblock": {
    "hash": "2e9d2c1dc20a33207f0a56e9f7c1d22f7dbb63234210b68eb94ef19405865317",
    "height": 103
  }
}
```

## 14) Send a small transaction to a second address

Commands:

- `/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf -rpcwallet=regtestminer getnewaddress`
- `/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf -rpcwallet=regtestminer sendtoaddress rhobc1qllp8n342v5g25e3vqj44cptwgsgvumsjk72yju 1.2345`

Outputs:

- second address: `rhobc1qllp8n342v5g25e3vqj44cptwgsgvumsjk72yju`
- txid: `0c8d97bbbadf704384a0b89c4c8d66498cd5a32e1a9feb48defd519e1a3ab16b`

## 15) Mine another block

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf -rpcwallet=regtestminer generatetoaddress 1 <regtestminer address>`

Output:

```json
[
  "4d493f41543ce56327a8d1911799067580ffd3093e24e238c281ae6438f116c5"
]
```

## 16) Confirm transaction appears

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf -rpcwallet=regtestminer gettransaction 0c8d97bbbadf704384a0b89c4c8d66498cd5a32e1a9feb48defd519e1a3ab16b`

Output (confirmed):

```json
{
  "amount": 0.00000000,
  "fee": -0.00001410,
  "confirmations": 1,
  "blockhash": "4d493f41543ce56327a8d1911799067580ffd3093e24e238c281ae6438f116c5",
  "blockheight": 104,
  "blockindex": 1,
  "blocktime": 1780209405,
  "txid": "0c8d97bbbadf704384a0b89c4c8d66498cd5a32e1a9feb48defd519e1a3ab16b",
  "wtxid": "a68194787e074e2010a6dfe4850341226aee9255a13aaf5018ee83530d4e4bbf",
  "walletconflicts": [
  ],
  "time": 1780209398,
  "timereceived": 1780209398,
  "bip125-replaceable": "no",
  "details": [
    {
      "address": "rhobc1qllp8n342v5g25e3vqj44cptwgsgvumsjk72yju",
      "category": "send",
      "amount": -1.23450000,
      "label": "",
      "vout": 1,
      "fee": -0.00001410,
      "abandoned": false
    },
    {
      "address": "rhobc1qllp8n342v5g25e3vqj44cptwgsgvumsjk72yju",
      "parent_descs": [
        "wpkh(upxkMmPaw7eegBhT1PPvwzKnBxhnFAB4swyGQxEqKvqVAqdYwXvD7LmeFfoDqt1e4Yxdidi7tvkauNhVkhnC4QPNGsPVD2CL59oS2yhdd5dCJNN/84h/1h/0h/0/*)#0akn5kkk"
      ],
      "category": "receive",
      "amount": 1.23450000,
      "label": "",
      "vout": 1,
      "abandoned": false
    }
  ],
  "hex": "020000000001018611f24255fe0ee2ceb3e06da8192d9c0a93333a17a744e28b4bb3789b1b977d0000000000fdffffff02eed4dc04010000001600141bd79ae9d2f6dc5f33f04a9f99a64c06a8b2afe190b25b0700000000160014ffc279c6aa6510aa662c04ab5c056e4410ce6e1202473044022052017583a5199c959d5cff19757b9209d1c6316d5a9ec320067c21257a8af13502200c61064468f907671b6c290e38ac19b7709508a190cd2ca1406cc8ff7d3e02910121036707a11810975404be2d19fc6a202932b2769823b878d68315861eae65eda25e67000000",
  "lastprocessedblock": {
    "hash": "4d493f41543ce56327a8d1911799067580ffd3093e24e238c281ae6438f116c5",
    "height": 104
  }
}
```

## 17) Confirm balance changes correctly

Commands:

- `/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf -rpcwallet=regtestminer getbalances`
- `/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf -rpcwallet=regtestminer getwalletinfo`

Outputs:

```json
{
  "mine": {
    "trusted": 8400134.99998590,
    "untrusted_pending": 0.00000000,
    "immature": 4500.00001410
  },
  "lastprocessedblock": {
    "hash": "4d493f41543ce56327a8d1911799067580ffd3093e24e238c281ae6438f116c5",
    "height": 104
  }
}
```

```json
{
  "walletname": "regtestminer",
  "walletversion": 169900,
  "format": "sqlite",
  "balance": 8400134.99998590,
  "unconfirmed_balance": 0.00000000,
  "immature_balance": 4500.00001410,
  "txcount": 105,
  "keypoolsize": 4000,
  "keypoolsize_hd_internal": 4000,
  "paytxfee": 0.00000000,
  "private_keys_enabled": true,
  "avoid_reuse": false,
  "scanning": false,
  "descriptors": true,
  "external_signer": false,
  "blank": false,
  "birthtime": 1780209366,
  "lastprocessedblock": {
    "hash": "4d493f41543ce56327a8d1911799067580ffd3093e24e238c281ae6438f116c5",
    "height": 104
  }
}
```

## 18) Stop regtest cleanly

Command:

`/home/hobbyhashcoin/bin/hobbyhash-cli -conf=/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-regtest.conf stop`

Output:

`HobbyHash Core stopping`

## Final pass/fail

- PASS
