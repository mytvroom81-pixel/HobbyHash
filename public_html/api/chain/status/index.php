<?php
require_once __DIR__ . '/../../_bootstrap.php';

if (!(bool)hobc_admin_setting_value('node.show_status_publicly', true)) {
    hobc_json(hobc_status_payload('syncing', 'Public node status is hidden by admin settings.', [
        'source' => 'admin_settings',
        'public_node_status' => false,
        'blocks' => 'not_available',
        'headers' => 'not_available',
        'difficulty' => 'not_available',
        'networkhashps' => 'not_available',
        'connections' => 'not_available',
    ]));
}

$chain = hobc_rpc('getblockchaininfo');
if (!$chain['ok'] || !is_array($chain['result'])) {
    hobc_json(hobc_status_payload('offline', 'HOBC node RPC is unavailable.'));
}

$result = $chain['result'];
$status = !empty($result['initialblockdownload']) ? 'syncing' : 'online';
$mempool = hobc_rpc('getmempoolinfo');
$hashps = hobc_rpc('getnetworkhashps');
$network = hobc_rpc('getnetworkinfo');

hobc_json(hobc_status_payload($status, '', [
    'source' => 'local_rpc',
    'chain' => $result['chain'] ?? 'not_available',
    'blocks' => $result['blocks'] ?? 'not_available',
    'headers' => $result['headers'] ?? 'not_available',
    'bestblockhash' => $result['bestblockhash'] ?? 'not_available',
    'difficulty' => $result['difficulty'] ?? 'not_available',
    'verificationprogress' => $result['verificationprogress'] ?? 'not_available',
    'initialblockdownload' => (bool)($result['initialblockdownload'] ?? false),
    'mediantime' => $result['mediantime'] ?? 'not_available',
    'mempool_tx_count' => ($mempool['ok'] && is_array($mempool['result'])) ? ($mempool['result']['size'] ?? 'not_available') : 'not_available',
    'networkhashps' => $hashps['ok'] ? ($hashps['result'] ?? 'not_available') : 'not_available',
    'connections' => ($network['ok'] && is_array($network['result'])) ? ($network['result']['connections'] ?? 'not_available') : 'not_available',
]));
