<?php
require_once __DIR__ . '/../../_bootstrap.php';

if (!(bool)hobc_admin_setting_value('explorer.public_enabled', true)) {
    hobc_json(hobc_status_payload('offline', (string)hobc_admin_setting_value('explorer.maintenance_message', 'Public explorer is temporarily unavailable.'), [
        'source' => 'admin_settings',
        'public_enabled' => false,
        'search_available' => false,
        'latest_blocks_available' => false,
        'latest_transactions_available' => false,
    ]));
}

$chain = hobc_rpc('getblockchaininfo');
if (!$chain['ok'] || !is_array($chain['result'])) {
    hobc_json(hobc_status_payload('offline', 'Explorer cannot read chain status yet.', [
        'search_available' => false,
        'latest_blocks_available' => false,
        'latest_transactions_available' => false,
    ]));
}

$result = $chain['result'];
$status = !empty($result['initialblockdownload']) ? 'syncing' : 'online';
$message = $status === 'syncing'
    ? 'HOBC node is syncing. Explorer data may be incomplete.'
    : 'Basic HOBC explorer is online using local node RPC. Full address history indexing is not available yet.';

hobc_json(hobc_status_payload($status, $message, [
    'source' => 'local_rpc',
    'app_port' => 18765,
    'app_bind' => '127.0.0.1',
    'database' => 'hobbyhash_explorer',
    'indexed' => false,
    'chain_height' => $result['blocks'] ?? 'not_available',
    'headers' => $result['headers'] ?? 'not_available',
    'synced_height' => $result['blocks'] ?? 'not_available',
    'search_available' => $status !== 'syncing',
    'latest_blocks_available' => true,
    'latest_transactions_available' => true,
    'address_history_available' => false,
]));
