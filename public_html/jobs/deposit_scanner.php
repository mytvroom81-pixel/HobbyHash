<?php
declare(strict_types=1);

require_once __DIR__ . '/job_common.php';

$maxBlocks = 100;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--max-blocks=')) {
        $maxBlocks = max(1, (int)substr($arg, 13));
    }
}

$settings = wallet_settings();
if ((int)$settings['scanner_paused'] === 1) {
    job_set_scan_state('paused', 'ok', 'scanner paused by setting', null);
    job_log('scanner paused');
    exit(0);
}

try {
    $bestHeight = (int)rpc_call('getblockcount', [], null);
} catch (Throwable $e) {
    job_set_scan_state('offline', 'offline', 'scanner cannot reach rpc', $e->getMessage());
    job_log('rpc offline: ' . $e->getMessage());
    exit(1);
}

$state = wallet_db()->query("SELECT last_scanned_height FROM chain_scan_state WHERE id = 1")->fetch();
$start = (int)($state['last_scanned_height'] ?? 0) + 1;
if ($start > $bestHeight) {
    job_set_scan_state('ok', 'ok', null, null);
    job_log('nothing to scan');
    exit(0);
}

$end = min($bestHeight, $start + $maxBlocks - 1);
$requiredConfs = (int)$settings['deposit_confirmations_required'];
$pdo = wallet_db();

function internal_withdrawal_for_deposit(PDO $pdo, string $txid, int $userId, string $address): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, requested_amount, fee_amount FROM withdrawals
         WHERE txid = ? AND user_id = ? AND requested_address = ?
         LIMIT 1"
    );
    $stmt->execute([$txid, $userId, $address]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function credit_internal_withdrawal_return(PDO $pdo, int $userId, array $withdrawal): void
{
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

// Also ingest unconfirmed receives so dashboard can show pending deposits honestly.
try {
    $recent = rpc_call('listtransactions', ['*', 200, 0, true], wallet_config()['rpc']['wallet']);
    if (is_array($recent)) {
        foreach ($recent as $txe) {
            if (($txe['category'] ?? '') !== 'receive') {
                continue;
            }
            $confs = (int)($txe['confirmations'] ?? 0);
            if ($confs > 0) {
                continue;
            }
            $address = (string)($txe['address'] ?? '');
            $txid = (string)($txe['txid'] ?? '');
            $vout = isset($txe['vout']) ? (int)$txe['vout'] : -1;
            $amount = number_format((float)($txe['amount'] ?? 0), 8, '.', '');
            if ($address === '' || $txid === '' || $vout < 0 || (float)$amount <= 0) {
                continue;
            }

            $a = $pdo->prepare("SELECT id, user_id FROM deposit_addresses WHERE address = ? AND is_active = 1 LIMIT 1");
            $a->execute([$address]);
            $addrRow = $a->fetch();
            if (!$addrRow) {
                continue;
            }

            $creditBehavior = internal_withdrawal_for_deposit($pdo, $txid, (int)$addrRow['user_id'], $address)
                ? 'internal_withdrawal'
                : 'external';
            $chk = $pdo->prepare("SELECT id FROM deposits WHERE txid = ? AND vout = ? LIMIT 1");
            $chk->execute([$txid, $vout]);
            if (!$chk->fetch()) {
                $ins = $pdo->prepare(
                    "INSERT INTO deposits
                    (user_id, deposit_address_id, txid, vout, amount, confirmations, status, credit_behavior)
                     VALUES (?, ?, ?, ?, ?, 0, 'detected', ?)"
                );
                $ins->execute([
                    (int)$addrRow['user_id'],
                    (int)$addrRow['id'],
                    $txid,
                    $vout,
                    $amount,
                    $creditBehavior,
                ]);
                job_log("detected unconfirmed deposit txid={$txid} vout={$vout} user_id=" . (int)$addrRow['user_id'] . " behavior={$creditBehavior}");
            }
        }
    }
} catch (Throwable $e) {
    wallet_log_error('deposit scanner mempool ingest failed: ' . $e->getMessage());
}

for ($h = $start; $h <= $end; $h++) {
    $blockHash = (string)rpc_call('getblockhash', [$h], null);
    $block = rpc_call('getblock', [$blockHash, 2], null);
    $txs = $block['tx'] ?? [];

    foreach ($txs as $tx) {
        $txid = (string)($tx['txid'] ?? '');
        if ($txid === '') {
            continue;
        }
        foreach (($tx['vout'] ?? []) as $vout) {
            $n = (int)($vout['n'] ?? -1);
            if ($n < 0) {
                continue;
            }
            $spk = $vout['scriptPubKey'] ?? [];
            $address = (string)($spk['address'] ?? '');
            if ($address === '' && !empty($spk['addresses']) && is_array($spk['addresses'])) {
                $address = (string)$spk['addresses'][0];
            }
            if ($address === '') {
                continue;
            }

            $a = $pdo->prepare("SELECT id, user_id FROM deposit_addresses WHERE address = ? AND is_active = 1 LIMIT 1");
            $a->execute([$address]);
            $addrRow = $a->fetch();
            if (!$addrRow) {
                continue;
            }

            $amount = number_format((float)($vout['value'] ?? 0), 8, '.', '');
            if ((float)$amount <= 0) {
                continue;
            }
            $confs = max(0, $bestHeight - $h + 1);

            $pdo->beginTransaction();
            try {
                $internalWithdrawal = internal_withdrawal_for_deposit($pdo, $txid, (int)$addrRow['user_id'], $address);
                $creditBehavior = $internalWithdrawal ? 'internal_withdrawal' : 'external';

                $d = $pdo->prepare("SELECT id, status, credit_behavior FROM deposits WHERE txid = ? AND vout = ? FOR UPDATE");
                $d->execute([$txid, $n]);
                $dep = $d->fetch();
                if (!$dep) {
                    $ins = $pdo->prepare(
                        "INSERT INTO deposits
                        (user_id, deposit_address_id, txid, vout, amount, block_hash, block_height, confirmations, status, credit_behavior)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'detected', ?)"
                    );
                    $ins->execute([
                        (int)$addrRow['user_id'],
                        (int)$addrRow['id'],
                        $txid,
                        $n,
                        $amount,
                        $blockHash,
                        $h,
                        $confs,
                        $creditBehavior,
                    ]);
                    $depId = (int)$pdo->lastInsertId();
                    $depStatus = 'detected';
                    $depCreditBehavior = $creditBehavior;
                    job_log("detected deposit txid={$txid} vout={$n} user_id=" . (int)$addrRow['user_id'] . " behavior={$creditBehavior}");
                } else {
                    $depId = (int)$dep['id'];
                    $depStatus = (string)$dep['status'];
                    $depCreditBehavior = (string)$dep['credit_behavior'];
                    if ($depCreditBehavior === 'external' && $creditBehavior !== 'external') {
                        $depCreditBehavior = $creditBehavior;
                    }
                    $upd = $pdo->prepare(
                        "UPDATE deposits
                         SET block_hash = ?, block_height = ?, confirmations = ?,
                             status = IF(status='credited', status, IF(? >= ?, 'confirming', status)),
                             credit_behavior = ?
                         WHERE id = ?"
                    );
                    $upd->execute([$blockHash, $h, $confs, $confs, $requiredConfs, $depCreditBehavior, $depId]);
                }

                if ($confs >= $requiredConfs && $depStatus !== 'credited') {
                    if ($depCreditBehavior === 'external') {
                        $chk = $pdo->prepare("SELECT id FROM ledger_entries WHERE reference_type = 'deposits' AND reference_id = ? AND entry_type = 'deposit_credit' LIMIT 1");
                        $chk->execute([$depId]);
                        if (!$chk->fetch()) {
                            ledger_add(
                                (int)$addrRow['user_id'],
                                'deposit_credit',
                                $amount,
                                'deposits',
                                $depId,
                                'scanner',
                                null,
                                'Deposit confirmed and credited'
                            );
                        }
                    } elseif ($depCreditBehavior === 'internal_withdrawal' && $internalWithdrawal) {
                        credit_internal_withdrawal_return($pdo, (int)$addrRow['user_id'], $internalWithdrawal);
                    } else {
                        job_log("internal deposit id={$depId} txid={$txid} behavior={$depCreditBehavior} marked without ledger credit");
                    }
                    $mark = $pdo->prepare("UPDATE deposits SET status = 'credited', credited_at = UTC_TIMESTAMP(), confirmations = ? WHERE id = ?");
                    $mark->execute([$confs, $depId]);
                    job_log("credited deposit id={$depId} txid={$txid}");
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                wallet_log_error('deposit scanner deposit process failed: ' . $e->getMessage());
            }
        }
    }

    $u = $pdo->prepare("UPDATE chain_scan_state SET last_scanned_height = ?, last_scanned_blockhash = ? WHERE id = 1");
    $u->execute([$h, $blockHash]);
}

job_set_scan_state('ok', 'ok', null, null);
job_log("scanner complete range {$start}-{$end}");
