<?php
declare(strict_types=1);

require_once __DIR__ . '/job_common.php';

$settings = wallet_settings();
if ((int)$settings['maintenance_mode'] === 1 || (int)$settings['withdrawals_paused'] === 1) {
    job_log('withdrawal broadcaster paused by settings');
    exit(0);
}

try {
    $balances = rpc_call('getbalances', [], wallet_config()['rpc']['wallet']);
    $trusted = (float)($balances['mine']['trusted'] ?? 0.0);
} catch (Throwable $e) {
    job_log('rpc offline: ' . $e->getMessage());
    wallet_log_error('withdrawal broadcaster rpc error: ' . $e->getMessage());
    exit(1);
}

$pdo = wallet_db();
$stmt = $pdo->query("SELECT * FROM withdrawals WHERE status = 'approved' ORDER BY id ASC LIMIT 25");
foreach ($stmt as $w) {
    $wid = (int)$w['id'];
    $userId = (int)$w['user_id'];
    $amount = (float)$w['requested_amount'];
    $fee = (float)$w['fee_amount'];
    $needed = $amount + $fee;

    try {
        $hold = $pdo->prepare("SELECT id FROM wallet_user_holds WHERE user_id = ? AND status = 'active' LIMIT 1");
        $hold->execute([$userId]);
        if ($hold->fetch()) {
            $review = $pdo->prepare("UPDATE withdrawals SET status = 'manual_review', failure_reason = ? WHERE id = ? AND status = 'approved'");
            $review->execute(['User wallet is on administrative hold', $wid]);
            job_log("withdrawal {$wid} moved to manual_review: user wallet hold active");
            continue;
        }
    } catch (Throwable $e) {
        wallet_log_error('withdrawal broadcaster hold check failed: ' . $e->getMessage());
        job_log("withdrawal {$wid} skipped: hold check failed");
        continue;
    }

    if ($trusted < $needed) {
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare("UPDATE withdrawals SET status = 'failed', failure_reason = ? WHERE id = ?");
            $upd->execute(['Insufficient hot wallet balance', $wid]);
            $chk = $pdo->prepare("SELECT id FROM ledger_entries WHERE reference_type = 'withdrawals' AND reference_id = ? AND entry_type = 'refund_credit' LIMIT 1");
            $chk->execute([$wid]);
            if (!$chk->fetch()) {
                ledger_add(
                    $userId,
                    'refund_credit',
                    number_format($needed, 8, '.', ''),
                    'withdrawals',
                    $wid,
                    'withdrawal_worker',
                    null,
                    'Refund due to insufficient hot wallet balance'
                );
            }
            $pdo->commit();
            job_log("withdrawal {$wid} failed: insufficient hot wallet balance");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            wallet_log_error('withdrawal broadcaster balance fail handling error: ' . $e->getMessage());
        }
        continue;
    }

    try {
        $txid = (string)rpc_call(
            'sendtoaddress',
            [
                $w['requested_address'],
                number_format($amount, 8, '.', ''),
                'HOBC Web Wallet withdrawal #' . $wid,
                '',
                false,
                true
            ],
            wallet_config()['rpc']['wallet']
        );
        $trusted -= $needed;
        $upd = $pdo->prepare("UPDATE withdrawals SET status = 'broadcasted', txid = ?, chain_confirmations = 0 WHERE id = ?");
        $upd->execute([$txid, $wid]);
        job_log("withdrawal {$wid} broadcasted txid={$txid}");
    } catch (Throwable $e) {
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare("UPDATE withdrawals SET status = 'failed', failure_reason = ? WHERE id = ?");
            $upd->execute([substr($e->getMessage(), 0, 255), $wid]);
            $chk = $pdo->prepare("SELECT id FROM ledger_entries WHERE reference_type = 'withdrawals' AND reference_id = ? AND entry_type = 'refund_credit' LIMIT 1");
            $chk->execute([$wid]);
            if (!$chk->fetch()) {
                ledger_add(
                    $userId,
                    'refund_credit',
                    number_format($needed, 8, '.', ''),
                    'withdrawals',
                    $wid,
                    'withdrawal_worker',
                    null,
                    'Refund due to broadcast failure'
                );
            }
            $pdo->commit();
            wallet_log_error("withdrawal {$wid} broadcast failed: " . $e->getMessage());
            job_log("withdrawal {$wid} failed");
        } catch (Throwable $e2) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            wallet_log_error('withdrawal broadcaster failure fallback error: ' . $e2->getMessage());
        }
    }
}

job_log('withdrawal broadcaster complete');
