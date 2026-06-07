<?php
require_once __DIR__ . '/../../_bootstrap.php';

$info = hobc_rpc('getblockchaininfo');
if (!$info['ok'] || !is_array($info['result'])) {
    hobc_json(hobc_status_payload('offline', 'HOBC node RPC is unavailable.', ['blocks' => []]));
}

$height = (int)($info['result']['blocks'] ?? 0);
$blocks = [];
for ($h = $height; $h > max(0, $height - 5); $h--) {
    $hash = hobc_rpc('getblockhash', [$h]);
    if (!$hash['ok'] || !is_string($hash['result'])) {
        continue;
    }
    $block = hobc_rpc('getblock', [$hash['result'], 1]);
    if (!$block['ok'] || !is_array($block['result'])) {
        continue;
    }
    $blocks[] = [
        'height' => $h,
        'hash' => $hash['result'],
        'time' => $block['result']['time'] ?? 'not_available',
        'tx_count' => isset($block['result']['tx']) && is_array($block['result']['tx']) ? count($block['result']['tx']) : 'not_available',
    ];
}

hobc_json(hobc_status_payload(empty($blocks) ? 'not_available' : 'online', '', ['blocks' => $blocks]));
