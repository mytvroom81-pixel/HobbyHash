<?php
declare(strict_types=1);

require_once __DIR__ . '/job_common.php';

$liabilities = (float)ledger_total_liabilities();
$snap = wallet_db()->query("SELECT trusted_balance FROM wallet_hot_balance_snapshots ORDER BY id DESC LIMIT 1")->fetch();
$trusted = (float)($snap['trusted_balance'] ?? 0.0);
$delta = $trusted - $liabilities;
$status = $delta < 0 ? 'warning' : 'ok';

$details = [
    'liabilities' => number_format($liabilities, 8, '.', ''),
    'trusted_balance' => number_format($trusted, 8, '.', ''),
    'delta_hot_minus_liabilities' => number_format($delta, 8, '.', ''),
];

$ins = wallet_db()->prepare(
    "INSERT INTO reconciliation_reports
    (liabilities_total, trusted_balance, delta_hot_minus_liabilities, status, details_json)
     VALUES (?, ?, ?, ?, ?)"
);
$ins->execute([
    $details['liabilities'],
    $details['trusted_balance'],
    $details['delta_hot_minus_liabilities'],
    $status,
    json_encode($details, JSON_UNESCAPED_SLASHES),
]);

job_log('liability reconciler complete status=' . $status);
