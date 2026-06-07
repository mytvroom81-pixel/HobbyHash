<?php
declare(strict_types=1);

require_once __DIR__ . '/job_common.php';

try {
    $balances = rpc_call('getbalances', [], wallet_config()['rpc']['wallet']);
    $trusted = (float)($balances['mine']['trusted'] ?? 0.0);
    $pending = (float)($balances['mine']['untrusted_pending'] ?? 0.0);
    $immature = (float)($balances['mine']['immature'] ?? 0.0);
    $height = (int)rpc_call('getblockcount', [], null);
} catch (Throwable $e) {
    job_log('rpc offline: ' . $e->getMessage());
    wallet_log_error('hot balance checker rpc error: ' . $e->getMessage());
    exit(1);
}

$liabilities = (float)ledger_total_liabilities();
$delta = $trusted - $liabilities;
$warn = $delta < 0 ? 1 : 0;

$stmt = wallet_db()->prepare(
    "INSERT INTO wallet_hot_balance_snapshots
    (trusted_balance, untrusted_pending, immature_balance, liabilities_total, delta_hot_minus_liabilities, warning_flag, block_height)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$stmt->execute([
    number_format($trusted, 8, '.', ''),
    number_format($pending, 8, '.', ''),
    number_format($immature, 8, '.', ''),
    number_format($liabilities, 8, '.', ''),
    number_format($delta, 8, '.', ''),
    $warn,
    $height,
]);

job_log('hot wallet balance checker complete');
