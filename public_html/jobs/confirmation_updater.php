<?php
declare(strict_types=1);

require_once __DIR__ . '/job_common.php';

try {
    $bestHeight = (int)rpc_call('getblockcount', [], null);
} catch (Throwable $e) {
    job_set_scan_state('error', 'offline', 'confirmation updater rpc offline', $e->getMessage());
    job_log('rpc offline: ' . $e->getMessage());
    exit(1);
}

$settings = wallet_settings();
$depConfsNeeded = (int)$settings['deposit_confirmations_required'];
$wdConfsNeeded = (int)$settings['withdrawal_confirmations_required'];
$pdo = wallet_db();

function credit_internal_withdrawal_return(PDO $pdo, int $userId, string $txid, string $address): void
{
    $stmt = $pdo->prepare(
        "SELECT id, requested_amount, fee_amount FROM withdrawals
         WHERE txid = ? AND user_id = ? AND requested_address = ?
         LIMIT 1"
    );
    $stmt->execute([$txid, $userId, $address]);
    $withdrawal = $stmt->fetch();
    if (!$withdrawal) {
        return;
    }

    $withdrawalId = (int)$withdrawal['id'];
    $chk = $pdo->prepare("SELECT id FROM ledger_entries WHERE reference_type = 'withdrawals' AND reference_id = ? AND entry_type = 'refund_credit' LIMIT 1");
    $chk->execute([$withdrawalId]);
    if ($chk->fetch()) {
        return;
    }

    ledger_add(
        $userId,
        'refund_credit',
        number_format((float)$withdrawal['requested_amount'] + (float)$withdrawal['fee_amount'], 8, '.', ''),
        'withdrawals',
        $withdrawalId,
        'scanner',
        null,
        'Internal withdrawal returned to same wallet user'
    );
}

// Deposits confirmation refresh.
$depStmt = $pdo->query(
    "SELECT d.id, d.user_id, d.amount, d.txid, d.block_height, d.status, d.credit_behavior, da.address
     FROM deposits d
     JOIN deposit_addresses da ON da.id = d.deposit_address_id
     WHERE d.status IN ('detected','confirming','credited')"
);
foreach ($depStmt as $dep) {
    $depId = (int)$dep['id'];
    $blockHeight = (int)($dep['block_height'] ?? 0);
    if ($blockHeight <= 0) {
        continue;
    }
    $confs = max(0, $bestHeight - $blockHeight + 1);
    $status = (string)$dep['status'];
    $newStatus = $status;
    if ($status !== 'credited' && $confs >= $depConfsNeeded) {
        $newStatus = 'credited';
    } elseif ($status === 'detected' && $confs > 0) {
        $newStatus = 'confirming';
    }

    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare("UPDATE deposits SET confirmations = ?, status = ?, credited_at = IF(?='credited' AND credited_at IS NULL, UTC_TIMESTAMP(), credited_at) WHERE id = ?");
        $upd->execute([$confs, $newStatus, $newStatus, $depId]);

        if ($newStatus === 'credited' && $status !== 'credited' && (string)$dep['credit_behavior'] === 'external') {
            $chk = $pdo->prepare("SELECT id FROM ledger_entries WHERE reference_type = 'deposits' AND reference_id = ? AND entry_type = 'deposit_credit' LIMIT 1");
            $chk->execute([$depId]);
            if (!$chk->fetch()) {
                ledger_add(
                    (int)$dep['user_id'],
                    'deposit_credit',
                    number_format((float)$dep['amount'], 8, '.', ''),
                    'deposits',
                    $depId,
                    'scanner',
                    null,
                    'Deposit credited by confirmation updater'
                );
            }
        } elseif ($newStatus === 'credited' && $status !== 'credited' && (string)$dep['credit_behavior'] === 'internal_withdrawal') {
            credit_internal_withdrawal_return($pdo, (int)$dep['user_id'], (string)$dep['txid'], (string)$dep['address']);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        wallet_log_error('confirmation updater deposits failed: ' . $e->getMessage());
    }
}

// Withdrawal confirmation refresh.
$wdStmt = $pdo->query("SELECT id, user_id, txid, status FROM withdrawals WHERE txid IS NOT NULL AND status IN ('broadcasted','confirming')");
foreach ($wdStmt as $wd) {
    $wid = (int)$wd['id'];
    $txid = (string)$wd['txid'];
    if ($txid === '') {
        continue;
    }

    $confs = 0;
    try {
        $tx = rpc_call('gettransaction', [$txid], wallet_config()['rpc']['wallet']);
        $confs = (int)($tx['confirmations'] ?? 0);
    } catch (Throwable $e) {
        try {
            $rtx = rpc_call('getrawtransaction', [$txid, true], null);
            $confs = (int)($rtx['confirmations'] ?? 0);
        } catch (Throwable $e2) {
            wallet_log_error("withdrawal confirmation check failed wid={$wid} txid={$txid}: " . $e2->getMessage());
            continue;
        }
    }

    $newStatus = $confs >= $wdConfsNeeded ? 'confirmed' : 'confirming';
    $upd = $pdo->prepare(
        "UPDATE withdrawals
         SET chain_confirmations = ?, status = ?, confirmed_at = IF(?='confirmed', UTC_TIMESTAMP(), confirmed_at)
         WHERE id = ?"
    );
    $upd->execute([$confs, $newStatus, $newStatus, $wid]);
}

job_set_scan_state('ok', 'ok', null, null);
job_log('confirmation updater complete');
