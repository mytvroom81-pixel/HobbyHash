<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/rpc.php';
require_once __DIR__ . '/../app/ledger.php';
require_once __DIR__ . '/../app/settings.php';

function job_log(string $message): void
{
    echo '[' . gmdate('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

function job_set_scan_state(string $scannerStatus, string $rpcStatus, ?string $scannerErr = null, ?string $rpcErr = null): void
{
    $stmt = wallet_db()->prepare(
        "UPDATE chain_scan_state
         SET scanner_status = ?, rpc_status = ?, scanner_last_error = ?, rpc_last_error = ?, updated_at = CURRENT_TIMESTAMP
         WHERE id = 1"
    );
    $stmt->execute([$scannerStatus, $rpcStatus, $scannerErr, $rpcErr]);
}
