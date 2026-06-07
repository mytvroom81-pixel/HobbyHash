<?php
require_once __DIR__ . '/../../_bootstrap.php';

$info = hobc_rpc('getblockchaininfo');
if (!$info['ok'] || !is_array($info['result'])) {
    hobc_json(hobc_status_payload('offline', 'HOBC node RPC is unavailable.', ['transactions' => []]));
}

$height = (int)($info['result']['blocks'] ?? 0);
$transactions = [];
$limit = 5;

for ($h = $height; $h >= 0 && count($transactions) < $limit; $h--) {
    $hash = hobc_rpc('getblockhash', [$h]);
    if (!$hash['ok'] || !is_string($hash['result'])) {
        continue;
    }

    $block = hobc_rpc('getblock', [$hash['result'], 1]);
    if (!$block['ok'] || !is_array($block['result'])) {
        continue;
    }

    $txids = is_array($block['result']['tx'] ?? null) ? $block['result']['tx'] : [];
    foreach ($txids as $index => $txid) {
        if (!is_string($txid) || $txid === '') {
            continue;
        }
        $transactions[] = [
            'txid' => $txid,
            'height' => $h,
            'blockhash' => $hash['result'],
            'time' => $block['result']['time'] ?? 'not_available',
            'type' => $index === 0 ? 'coinbase' : 'transaction',
        ];
        if (count($transactions) >= $limit) {
            break 2;
        }
    }
}

hobc_json(hobc_status_payload(empty($transactions) ? 'not_available' : 'online', '', [
    'transactions' => $transactions,
]));
