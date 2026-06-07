<?php
require_once __DIR__ . '/../../_bootstrap.php';

$pdo = hobc_db();
if (!(bool)hobc_admin_setting_value('wallet.public_enabled', true)) {
    hobc_json(hobc_status_payload('offline', (string)hobc_admin_setting_value('wallet.maintenance_message', 'Public wallet is temporarily unavailable.'), [
        'custodial' => true,
        'public_enabled' => false,
        'deposits' => 'paused',
        'withdrawals' => 'paused',
        'scanner_status' => 'not_available',
        'risk_notice' => (string)hobc_admin_setting_value('legal.wallet_custody_notice', 'The HOBC web wallet is custodial. The website controls wallet keys and funds until withdrawal.'),
    ]));
}
if (!$pdo) {
    hobc_json(hobc_status_payload('offline', 'Wallet database is unavailable.', [
        'custodial' => true,
        'deposits' => 'not_available',
        'withdrawals' => 'not_available',
        'scanner_status' => 'not_available',
        'risk_notice' => (string)hobc_admin_setting_value('legal.wallet_custody_notice', 'The HOBC web wallet is custodial. The website controls wallet keys and funds until withdrawal.'),
    ]));
}

try {
    $settings = $pdo->query('SELECT maintenance_mode, deposits_paused, withdrawals_paused, scanner_paused FROM wallet_settings WHERE id = 1')->fetch() ?: [];
    $scan = $pdo->query('SELECT scanner_status, rpc_status, last_scanned_height, updated_at FROM chain_scan_state WHERE id = 1')->fetch() ?: [];
    hobc_json(hobc_status_payload('online', '', [
        'custodial' => true,
        'public_enabled' => true,
        'maintenance_mode' => !empty($settings['maintenance_mode']),
        'deposits' => !empty($settings['deposits_paused']) ? 'paused' : 'enabled',
        'withdrawals' => !empty($settings['withdrawals_paused']) ? 'paused' : 'enabled',
        'scanner_status' => !empty($settings['scanner_paused']) ? 'paused' : ($scan['scanner_status'] ?? 'not_available'),
        'rpc_status' => $scan['rpc_status'] ?? 'not_available',
        'last_scanned_height' => isset($scan['last_scanned_height']) ? (int)$scan['last_scanned_height'] : 'not_available',
        'scanner_updated_at' => $scan['updated_at'] ?? 'not_available',
        'risk_notice' => (string)hobc_admin_setting_value('legal.wallet_custody_notice', 'The HOBC web wallet is custodial. The website controls wallet keys and funds until withdrawal.'),
    ]));
} catch (Throwable $e) {
    hobc_json(hobc_status_payload('offline', 'Wallet status is unavailable.', [
        'custodial' => true,
        'deposits' => 'not_available',
        'withdrawals' => 'not_available',
        'scanner_status' => 'not_available',
    ]));
}
