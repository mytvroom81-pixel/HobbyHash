<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/ledger.php';
require_once __DIR__ . '/../app/rpc.php';
require_once __DIR__ . '/../app/security_log.php';
require_once __DIR__ . '/../app/admin_view.php';

$admin = admin_require_user();
$pdo = wallet_db();

function wallet_admin_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool)$stmt->fetchColumn();
}

function wallet_admin_count(PDO $pdo, string $table, string $where = '1=1'): int
{
    if (!wallet_admin_table_exists($pdo, $table)) {
        return 0;
    }
    return (int)$pdo->query("SELECT COUNT(*) FROM `" . str_replace('`', '``', $table) . "` WHERE {$where}")->fetchColumn();
}

function wallet_admin_hobc(float|string|null $value): string
{
    return number_format((float)($value ?? 0), 8, '.', '');
}

function wallet_admin_short(?string $value, int $start = 16, int $end = 10): string
{
    return admin_short_text($value, $start, $end);
}

function wallet_admin_pager(int $totalRows, int $defaultPerPage = 50): array
{
    $state = admin_page_state($defaultPerPage);
    return admin_pagination_meta($state['page'], $state['per_page'], $totalRows);
}

function wallet_admin_fetch_withdrawals(PDO $pdo, string $statusWhere, array $pager): array
{
    $stmt = $pdo->prepare(
        "SELECT w.*, u.username, u.email
         FROM withdrawals w
         JOIN users u ON u.id = w.user_id
         WHERE {$statusWhere}
         ORDER BY w.id DESC
         LIMIT " . (int)$pager['per_page'] . ' OFFSET ' . (int)$pager['offset']
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

function wallet_admin_count_withdrawals(PDO $pdo, string $statusWhere): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM withdrawals w WHERE {$statusWhere}")->fetchColumn();
}

function wallet_admin_tab_configs(): array
{
    return [
        'overview' => 'Wallet overview',
        'balances' => 'User wallet balances',
        'addresses' => 'Deposit addresses',
        'deposits' => 'Deposit history',
        'withdrawals' => 'Withdrawal requests',
        'pending' => 'Pending withdrawals',
        'approved' => 'Approved withdrawals',
        'rejected' => 'Rejected withdrawals',
        'manual-review' => 'Manual review queue',
        'hot-wallet' => 'Hot wallet status',
        'reserve-notes' => 'Cold wallet/reserve notes',
        'node-rpc' => 'Node RPC status',
        'wallet-rpc' => 'Wallet RPC status',
        'broadcast' => 'Transaction broadcast status',
        'failed-ops' => 'Failed wallet operations',
        'audit' => 'Wallet audit logs',
        'reconciliation' => 'Balance reconciliation',
        'suspicious' => 'Suspicious wallet activity',
        'limits' => 'Withdrawal limits',
        'settings' => 'Custodial wallet settings',
    ];
}

function wallet_admin_current_tab(): string
{
    $tab = (string)($_GET['tab'] ?? 'overview');
    return array_key_exists($tab, wallet_admin_tab_configs()) ? $tab : 'overview';
}

function wallet_admin_export_ledger(PDO $pdo, int $adminId): void
{
    admin_audit($adminId, 'export_wallet_ledger_csv', 'ledger_entries', 'csv');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="hobc-wallet-ledger-' . gmdate('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    if ($out === false) {
        exit;
    }
    fputcsv($out, ['id', 'created_at', 'user_id', 'username', 'entry_type', 'amount', 'reference_type', 'reference_id', 'actor_type', 'actor_id', 'note']);
    $rows = $pdo->query(
        "SELECT le.id, le.created_at, le.user_id, u.username, le.entry_type, le.amount, le.reference_type, le.reference_id, le.actor_type, le.actor_id, le.note
         FROM ledger_entries le
         LEFT JOIN users u ON u.id = le.user_id
         ORDER BY le.id DESC
         LIMIT 50000"
    );
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['id'],
            $row['created_at'],
            $row['user_id'],
            $row['username'],
            $row['entry_type'],
            $row['amount'],
            $row['reference_type'],
            $row['reference_id'],
            $row['actor_type'],
            $row['actor_id'],
            $row['note'],
        ]);
    }
    fclose($out);
    exit;
}

function wallet_admin_rpc_statuses(): array
{
    $node = ['ok' => false, 'status' => 'offline', 'message' => '', 'data' => []];
    $wallet = ['ok' => false, 'status' => 'offline', 'message' => '', 'data' => []];

    try {
        $info = rpc_call('getblockchaininfo', [], null);
        $node = [
            'ok' => true,
            'status' => 'online',
            'message' => 'Node RPC reachable',
            'data' => is_array($info) ? $info : [],
        ];
    } catch (Throwable $e) {
        $node['message'] = $e->getMessage();
    }

    try {
        $info = rpc_call('getwalletinfo', [], wallet_config()['rpc']['wallet']);
        $wallet = [
            'ok' => true,
            'status' => 'online',
            'message' => 'Wallet RPC reachable',
            'data' => is_array($info) ? $info : [],
        ];
    } catch (Throwable $e) {
        $wallet['message'] = $e->getMessage();
    }

    return ['node' => $node, 'wallet' => $wallet];
}

function wallet_admin_reconciliation(PDO $pdo, int $adminId): array
{
    $liabilities = (float)ledger_total_liabilities();
    $balances = rpc_call('getbalances', [], wallet_config()['rpc']['wallet']);
    $trusted = (float)($balances['mine']['trusted'] ?? 0.0);
    $pending = (float)($balances['mine']['untrusted_pending'] ?? 0.0);
    $immature = (float)($balances['mine']['immature'] ?? 0.0);
    $height = 0;
    try {
        $height = (int)rpc_call('getblockcount', [], null);
    } catch (Throwable $e) {
        $height = null;
    }
    $delta = $trusted - $liabilities;
    $status = $delta < 0 ? 'warning' : 'ok';

    $snap = $pdo->prepare(
        "INSERT INTO wallet_hot_balance_snapshots
        (trusted_balance, untrusted_pending, immature_balance, liabilities_total, delta_hot_minus_liabilities, warning_flag, block_height)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $snap->execute([
        wallet_admin_hobc($trusted),
        wallet_admin_hobc($pending),
        wallet_admin_hobc($immature),
        wallet_admin_hobc($liabilities),
        wallet_admin_hobc($delta),
        $delta < 0 ? 1 : 0,
        $height,
    ]);

    $details = [
        'liabilities_total' => wallet_admin_hobc($liabilities),
        'trusted_balance' => wallet_admin_hobc($trusted),
        'untrusted_pending' => wallet_admin_hobc($pending),
        'immature_balance' => wallet_admin_hobc($immature),
        'delta_hot_minus_liabilities' => wallet_admin_hobc($delta),
        'block_height' => $height,
    ];
    $report = $pdo->prepare(
        "INSERT INTO reconciliation_reports
        (liabilities_total, trusted_balance, delta_hot_minus_liabilities, status, details_json)
         VALUES (?, ?, ?, ?, ?)"
    );
    $report->execute([
        $details['liabilities_total'],
        $details['trusted_balance'],
        $details['delta_hot_minus_liabilities'],
        $status,
        json_encode($details, JSON_UNESCAPED_SLASHES),
    ]);
    admin_audit($adminId, 'run_wallet_reconciliation_check', 'reconciliation_reports', (string)$pdo->lastInsertId(), $details);
    return ['status' => $status, 'details' => $details];
}

if ((string)($_GET['export'] ?? '') === 'ledger') {
    wallet_admin_export_ledger($pdo, (int)$admin['id']);
}

$msg = '';
$err = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_validate_or_fail();
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'settings') {
            $maintenance = isset($_POST['maintenance_mode']) ? 1 : 0;
            $depositsPaused = isset($_POST['deposits_paused']) ? 1 : 0;
            $withdrawalsPaused = isset($_POST['withdrawals_paused']) ? 1 : 0;
            $scannerPaused = isset($_POST['scanner_paused']) ? 1 : 0;
            $minWithdrawal = max(0.00000001, (float)($_POST['per_withdrawal_min_amount'] ?? 0.00000001));
            $maxWithdrawal = max($minWithdrawal, (float)($_POST['per_withdrawal_max_amount'] ?? 50000));
            $dailyLimit = max($maxWithdrawal, (float)($_POST['daily_hot_wallet_broadcast_limit'] ?? 2000000));
            $approvalThreshold = max($minWithdrawal, (float)($_POST['admin_approval_threshold'] ?? 5000));
            $depositConfirmations = max(1, (int)($_POST['deposit_confirmations_required'] ?? 6));
            $withdrawalConfirmations = max(1, (int)($_POST['withdrawal_confirmations_required'] ?? 1));

            $upd = $pdo->prepare(
                "UPDATE wallet_settings
                 SET maintenance_mode = ?,
                     deposits_paused = ?,
                     withdrawals_paused = ?,
                     scanner_paused = ?,
                     per_withdrawal_min_amount = ?,
                     per_withdrawal_max_amount = ?,
                     daily_hot_wallet_broadcast_limit = ?,
                     admin_approval_threshold = ?,
                     deposit_confirmations_required = ?,
                     withdrawal_confirmations_required = ?
                 WHERE id = 1"
            );
            $upd->execute([
                $maintenance,
                $depositsPaused,
                $withdrawalsPaused,
                $scannerPaused,
                wallet_admin_hobc($minWithdrawal),
                wallet_admin_hobc($maxWithdrawal),
                wallet_admin_hobc($dailyLimit),
                wallet_admin_hobc($approvalThreshold),
                $depositConfirmations,
                $withdrawalConfirmations,
            ]);
            admin_audit((int)$admin['id'], 'update_wallet_admin_settings', 'wallet_settings', '1', [
                'maintenance_mode' => $maintenance,
                'deposits_paused' => $depositsPaused,
                'withdrawals_paused' => $withdrawalsPaused,
                'scanner_paused' => $scannerPaused,
                'per_withdrawal_min_amount' => wallet_admin_hobc($minWithdrawal),
                'per_withdrawal_max_amount' => wallet_admin_hobc($maxWithdrawal),
                'daily_hot_wallet_broadcast_limit' => wallet_admin_hobc($dailyLimit),
                'admin_approval_threshold' => wallet_admin_hobc($approvalThreshold),
            ]);
            $msg = 'Custodial wallet settings updated.';
        } elseif ($action === 'pause_withdrawals' || $action === 'resume_withdrawals' || $action === 'pause_deposits' || $action === 'resume_deposits') {
            $column = str_contains($action, 'withdrawals') ? 'withdrawals_paused' : 'deposits_paused';
            $value = str_starts_with($action, 'pause') ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE wallet_settings SET {$column} = ? WHERE id = 1");
            $stmt->execute([$value]);
            admin_audit((int)$admin['id'], $action, 'wallet_settings', '1', [$column => $value]);
            $msg = str_replace('_', ' ', ucfirst($action)) . ' saved.';
        } elseif ($action === 'place_hold') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $reason = substr(trim((string)($_POST['hold_reason'] ?? '')), 0, 255);
            if ($userId <= 0 || $reason === '') {
                throw new RuntimeException('User and hold reason are required.');
            }
            $stmt = $pdo->prepare("INSERT INTO wallet_user_holds (user_id, hold_reason, placed_by_admin_id) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $reason, (int)$admin['id']]);
            admin_audit((int)$admin['id'], 'place_wallet_user_hold', 'user', (string)$userId, ['reason' => $reason]);
            $msg = 'User wallet hold placed.';
        } elseif ($action === 'release_hold') {
            $holdId = (int)($_POST['hold_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE wallet_user_holds SET status = 'released', released_by_admin_id = ?, released_at = UTC_TIMESTAMP() WHERE id = ? AND status = 'active'");
            $stmt->execute([(int)$admin['id'], $holdId]);
            admin_audit((int)$admin['id'], 'release_wallet_user_hold', 'wallet_user_holds', (string)$holdId);
            $msg = 'User wallet hold released.';
        } elseif ($action === 'add_note') {
            $noteType = (string)($_POST['note_type'] ?? 'user');
            if (!in_array($noteType, ['user', 'withdrawal', 'reserve', 'operation'], true)) {
                $noteType = 'user';
            }
            $userId = (int)($_POST['note_user_id'] ?? 0);
            $withdrawalId = (int)($_POST['note_withdrawal_id'] ?? 0);
            $note = trim((string)($_POST['note'] ?? ''));
            if ($note === '') {
                throw new RuntimeException('Admin note is required.');
            }
            $stmt = $pdo->prepare("INSERT INTO wallet_admin_notes (user_id, withdrawal_id, note_type, note, created_by_admin_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId > 0 ? $userId : null, $withdrawalId > 0 ? $withdrawalId : null, $noteType, $note, (int)$admin['id']]);
            admin_audit((int)$admin['id'], 'add_wallet_admin_note', 'wallet_admin_notes', (string)$pdo->lastInsertId(), ['note_type' => $noteType, 'user_id' => $userId ?: null, 'withdrawal_id' => $withdrawalId ?: null]);
            $msg = 'Admin note added.';
        } elseif (in_array($action, ['approve_withdrawal', 'reject_withdrawal', 'manual_review_withdrawal'], true)) {
            $withdrawalId = (int)($_POST['withdrawal_id'] ?? 0);
            $note = substr(trim((string)($_POST['review_note'] ?? '')), 0, 500);
            if ($withdrawalId <= 0) {
                throw new RuntimeException('Withdrawal id is required.');
            }
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE id = ? FOR UPDATE");
                $stmt->execute([$withdrawalId]);
                $withdrawal = $stmt->fetch();
                if (!$withdrawal) {
                    throw new RuntimeException('Withdrawal not found.');
                }
                $status = (string)$withdrawal['status'];
                if (!in_array($status, ['pending', 'awaiting_approval', 'manual_review'], true)) {
                    throw new RuntimeException('Withdrawal is not in an admin-reviewable status.');
                }

                if ($action === 'approve_withdrawal') {
                    $upd = $pdo->prepare("UPDATE withdrawals SET status = 'approved', approved_by_admin_id = ?, approved_at = UTC_TIMESTAMP(), failure_reason = NULL WHERE id = ?");
                    $upd->execute([(int)$admin['id'], $withdrawalId]);
                    admin_audit((int)$admin['id'], 'withdrawal_approved', 'withdrawal', (string)$withdrawalId, ['note' => $note !== '' ? $note : null]);
                    $msg = 'Withdrawal approved. It remains queued for the existing broadcaster job; no transaction was broadcast by this admin page.';
                } elseif ($action === 'reject_withdrawal') {
                    $upd = $pdo->prepare("UPDATE withdrawals SET status = 'rejected', rejected_by_admin_id = ?, rejected_at = UTC_TIMESTAMP(), failure_reason = ? WHERE id = ?");
                    $upd->execute([(int)$admin['id'], $note !== '' ? $note : 'Rejected by admin', $withdrawalId]);
                    $alreadyRefunded = $pdo->prepare("SELECT id FROM ledger_entries WHERE reference_type = 'withdrawals' AND reference_id = ? AND entry_type = 'refund_credit' LIMIT 1");
                    $alreadyRefunded->execute([$withdrawalId]);
                    if (!$alreadyRefunded->fetch()) {
                        ledger_add(
                            (int)$withdrawal['user_id'],
                            'refund_credit',
                            wallet_admin_hobc((float)$withdrawal['requested_amount'] + (float)$withdrawal['fee_amount']),
                            'withdrawals',
                            $withdrawalId,
                            'admin',
                            (int)$admin['id'],
                            'Admin rejected withdrawal'
                        );
                    }
                    admin_audit((int)$admin['id'], 'withdrawal_rejected', 'withdrawal', (string)$withdrawalId, ['note' => $note !== '' ? $note : null]);
                    $msg = 'Withdrawal rejected and ledger refund recorded if needed.';
                } else {
                    $upd = $pdo->prepare("UPDATE withdrawals SET status = 'manual_review', failure_reason = ? WHERE id = ?");
                    $upd->execute([$note !== '' ? $note : 'Marked for manual review', $withdrawalId]);
                    admin_audit((int)$admin['id'], 'withdrawal_marked_manual_review', 'withdrawal', (string)$withdrawalId, ['note' => $note !== '' ? $note : null]);
                    $msg = 'Withdrawal marked for manual review.';
                }

                if ($note !== '') {
                    $noteStmt = $pdo->prepare("INSERT INTO wallet_admin_notes (user_id, withdrawal_id, note_type, note, created_by_admin_id) VALUES (?, ?, 'withdrawal', ?, ?)");
                    $noteStmt->execute([(int)$withdrawal['user_id'], $withdrawalId, $note, (int)$admin['id']]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        } elseif ($action === 'run_reconciliation') {
            $result = wallet_admin_reconciliation($pdo, (int)$admin['id']);
            $msg = 'Balance reconciliation completed with status: ' . $result['status'] . '.';
        } elseif ($action === 'refresh_status') {
            $statuses = wallet_admin_rpc_statuses();
            $scanStatus = $statuses['node']['ok'] ? 'ok' : 'offline';
            $rpcStatus = ($statuses['node']['ok'] && $statuses['wallet']['ok']) ? 'ok' : 'error';
            $lastError = trim(($statuses['node']['ok'] ? '' : $statuses['node']['message']) . ' ' . ($statuses['wallet']['ok'] ? '' : $statuses['wallet']['message']));
            $stmt = $pdo->prepare("UPDATE chain_scan_state SET scanner_status = ?, rpc_status = ?, rpc_last_error = ? WHERE id = 1");
            $stmt->execute([$scanStatus, $rpcStatus, $lastError !== '' ? substr($lastError, 0, 255) : null]);
            admin_audit((int)$admin['id'], 'refresh_wallet_node_status', 'chain_scan_state', '1', ['node' => $statuses['node']['status'], 'wallet' => $statuses['wallet']['status']]);
            $msg = 'Wallet/node status refreshed.';
        } else {
            throw new RuntimeException('Unknown wallet admin action.');
        }
    } catch (Throwable $e) {
        wallet_log_error('admin wallet action failed: ' . $e->getMessage());
        $err = $e->getMessage();
    }
}

$tab = wallet_admin_current_tab();
$tabs = wallet_admin_tab_configs();
$settings = $pdo->query("SELECT * FROM wallet_settings WHERE id = 1")->fetch();
$scan = $pdo->query("SELECT * FROM chain_scan_state WHERE id = 1")->fetch() ?: [];
$liabilities = (float)ledger_total_liabilities();
$rpcStatuses = wallet_admin_rpc_statuses();
$hot = ['trusted' => 0.0, 'untrusted_pending' => 0.0, 'immature' => 0.0];
$rpcErr = '';
try {
    $balances = rpc_call('getbalances', [], wallet_config()['rpc']['wallet']);
    $hot = [
        'trusted' => (float)($balances['mine']['trusted'] ?? 0.0),
        'untrusted_pending' => (float)($balances['mine']['untrusted_pending'] ?? 0.0),
        'immature' => (float)($balances['mine']['immature'] ?? 0.0),
    ];
} catch (Throwable $e) {
    $rpcErr = $e->getMessage();
}
$delta = $hot['trusted'] - $liabilities;
$pageParams = ['tab' => $tab];

$pendingCount = wallet_admin_count($pdo, 'withdrawals', "status IN ('pending', 'awaiting_approval')");
$manualCount = wallet_admin_count($pdo, 'withdrawals', "status = 'manual_review'");
$approvedCount = wallet_admin_count($pdo, 'withdrawals', "status IN ('approved', 'broadcasted', 'confirming', 'confirmed')");
$rejectedCount = wallet_admin_count($pdo, 'withdrawals', "status = 'rejected'");
$failedCount = wallet_admin_count($pdo, 'withdrawals', "status = 'failed'");
$allWithdrawalCount = wallet_admin_count($pdo, 'withdrawals', '1=1');

$users = [];
$usersPager = wallet_admin_pager((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(), 50);
$withdrawalRows = [];
$withdrawalsPager = wallet_admin_pager($allWithdrawalCount, 50);
$pendingRows = [];
$pendingPager = wallet_admin_pager($pendingCount, 50);
$approvedRows = [];
$approvedPager = wallet_admin_pager($approvedCount, 50);
$rejectedRows = [];
$rejectedPager = wallet_admin_pager($rejectedCount, 50);
$manualRows = [];
$manualPager = wallet_admin_pager($manualCount, 50);
$failedRows = [];
$failedPager = wallet_admin_pager($failedCount, 50);
$depositAddresses = [];
$addressesPager = wallet_admin_pager(wallet_admin_count($pdo, 'deposit_addresses'), 50);
$deposits = [];
$depositsPager = wallet_admin_pager(wallet_admin_count($pdo, 'deposits'), 50);
$notes = [];
$notesPager = wallet_admin_pager(wallet_admin_count($pdo, 'wallet_admin_notes'), 50);
$holds = [];
$holdsPager = wallet_admin_pager(wallet_admin_count($pdo, 'wallet_user_holds'), 50);
$snapshots = [];
$snapshotsPager = wallet_admin_pager(wallet_admin_count($pdo, 'wallet_hot_balance_snapshots'), 50);
$reports = [];
$reportsPager = wallet_admin_pager(wallet_admin_count($pdo, 'reconciliation_reports'), 50);
$audit = [];
$auditPager = wallet_admin_pager((int)$pdo->query("SELECT COUNT(*) FROM admin_audit_log a WHERE a.action LIKE '%wallet%' OR a.action LIKE '%withdrawal%' OR a.target_type IN ('withdrawal','wallet_settings','ledger_entries','reconciliation_reports')")->fetchColumn(), 50);
$suspicious = [];
$suspiciousPager = wallet_admin_pager((int)$pdo->query("SELECT COUNT(*) FROM security_event_log se WHERE se.event_type LIKE '%withdraw%' OR se.event_type LIKE '%login_failed%' OR se.severity IN ('warning','critical')")->fetchColumn(), 50);
$ledgerRecent = [];
$ledgerPager = wallet_admin_pager(wallet_admin_count($pdo, 'ledger_entries'), 50);

if ($tab === 'balances') {
    $users = $pdo->query(
        "SELECT u.id, u.username, u.email, u.is_active, u.created_at,
                COALESCE(SUM(le.amount), 0) AS balance,
                MAX(CASE WHEN h.status = 'active' THEN h.id ELSE NULL END) AS active_hold_id,
                MAX(CASE WHEN h.status = 'active' THEN h.hold_reason ELSE NULL END) AS active_hold_reason
         FROM users u
         LEFT JOIN ledger_entries le ON le.user_id = u.id
         LEFT JOIN wallet_user_holds h ON h.user_id = u.id AND h.status = 'active'
         GROUP BY u.id
         ORDER BY balance DESC, u.id DESC
         LIMIT {$usersPager['per_page']} OFFSET {$usersPager['offset']}"
    )->fetchAll();
} elseif ($tab === 'addresses') {
    $depositAddresses = $pdo->query(
        "SELECT da.id, da.address, da.label, da.address_role, da.is_active, da.assigned_at, u.username, u.email,
                COALESCE(SUM(CASE WHEN d.credit_behavior = 'external' THEN d.amount ELSE 0 END), 0) AS total_received,
                COUNT(d.id) AS deposit_count
         FROM deposit_addresses da
         JOIN users u ON u.id = da.user_id
         LEFT JOIN deposits d ON d.deposit_address_id = da.id
         GROUP BY da.id
         ORDER BY da.id DESC
         LIMIT {$addressesPager['per_page']} OFFSET {$addressesPager['offset']}"
    )->fetchAll();
} elseif ($tab === 'deposits') {
    $deposits = $pdo->query(
        "SELECT d.*, u.username, da.address
         FROM deposits d
         JOIN users u ON u.id = d.user_id
         JOIN deposit_addresses da ON da.id = d.deposit_address_id
         ORDER BY d.id DESC
         LIMIT {$depositsPager['per_page']} OFFSET {$depositsPager['offset']}"
    )->fetchAll();
} elseif ($tab === 'withdrawals') {
    $withdrawalRows = wallet_admin_fetch_withdrawals($pdo, '1=1', $withdrawalsPager);
} elseif ($tab === 'pending') {
    $pendingRows = wallet_admin_fetch_withdrawals($pdo, "w.status IN ('pending', 'awaiting_approval')", $pendingPager);
} elseif ($tab === 'approved') {
    $approvedRows = wallet_admin_fetch_withdrawals($pdo, "w.status IN ('approved', 'broadcasted', 'confirming', 'confirmed')", $approvedPager);
} elseif ($tab === 'broadcast') {
    $approvedRows = wallet_admin_fetch_withdrawals($pdo, "w.status IN ('approved', 'broadcasted', 'confirming', 'confirmed')", $approvedPager);
    $failedRows = wallet_admin_fetch_withdrawals($pdo, "w.status = 'failed'", $failedPager);
} elseif ($tab === 'rejected') {
    $rejectedRows = wallet_admin_fetch_withdrawals($pdo, "w.status = 'rejected'", $rejectedPager);
} elseif ($tab === 'manual-review') {
    $manualRows = wallet_admin_fetch_withdrawals($pdo, "w.status = 'manual_review'", $manualPager);
} elseif ($tab === 'failed-ops') {
    $failedRows = wallet_admin_fetch_withdrawals($pdo, "w.status = 'failed'", $failedPager);
    $suspicious = $pdo->query("SELECT se.*, u.username FROM security_event_log se LEFT JOIN users u ON u.id = se.user_id WHERE se.event_type LIKE '%withdraw%' OR se.event_type LIKE '%login_failed%' OR se.severity IN ('warning','critical') ORDER BY se.id DESC LIMIT {$suspiciousPager['per_page']} OFFSET {$suspiciousPager['offset']}")->fetchAll();
} elseif ($tab === 'hot-wallet') {
    $snapshots = $pdo->query("SELECT * FROM wallet_hot_balance_snapshots ORDER BY id DESC LIMIT {$snapshotsPager['per_page']} OFFSET {$snapshotsPager['offset']}")->fetchAll();
} elseif ($tab === 'reserve-notes' && wallet_admin_table_exists($pdo, 'wallet_admin_notes')) {
    $notes = $pdo->query("SELECT n.*, u.username, au.username AS admin_name FROM wallet_admin_notes n LEFT JOIN users u ON u.id = n.user_id LEFT JOIN admin_users au ON au.id = n.created_by_admin_id ORDER BY n.id DESC LIMIT {$notesPager['per_page']} OFFSET {$notesPager['offset']}")->fetchAll();
} elseif ($tab === 'audit') {
    $audit = $pdo->query("SELECT a.*, au.username AS admin_name FROM admin_audit_log a LEFT JOIN admin_users au ON au.id = a.admin_user_id WHERE a.action LIKE '%wallet%' OR a.action LIKE '%withdrawal%' OR a.target_type IN ('withdrawal','wallet_settings','ledger_entries','reconciliation_reports') ORDER BY a.id DESC LIMIT {$auditPager['per_page']} OFFSET {$auditPager['offset']}")->fetchAll();
    $ledgerRecent = $pdo->query("SELECT le.*, u.username FROM ledger_entries le JOIN users u ON u.id = le.user_id ORDER BY le.id DESC LIMIT {$ledgerPager['per_page']} OFFSET {$ledgerPager['offset']}")->fetchAll();
} elseif ($tab === 'reconciliation') {
    $reports = $pdo->query("SELECT * FROM reconciliation_reports ORDER BY id DESC LIMIT {$reportsPager['per_page']} OFFSET {$reportsPager['offset']}")->fetchAll();
} elseif ($tab === 'suspicious') {
    $suspicious = $pdo->query("SELECT se.*, u.username FROM security_event_log se LEFT JOIN users u ON u.id = se.user_id WHERE se.event_type LIKE '%withdraw%' OR se.event_type LIKE '%login_failed%' OR se.severity IN ('warning','critical') ORDER BY se.id DESC LIMIT {$suspiciousPager['per_page']} OFFSET {$suspiciousPager['offset']}")->fetchAll();
    if (wallet_admin_table_exists($pdo, 'wallet_user_holds')) {
        $holds = $pdo->query("SELECT h.*, u.username, au.username AS placed_by_name FROM wallet_user_holds h JOIN users u ON u.id = h.user_id LEFT JOIN admin_users au ON au.id = h.placed_by_admin_id ORDER BY h.id DESC LIMIT {$holdsPager['per_page']} OFFSET {$holdsPager['offset']}")->fetchAll();
    }
}

function wallet_admin_withdrawal_actions(array $row): string
{
    $status = (string)$row['status'];
    if (!in_array($status, ['pending', 'awaiting_approval', 'manual_review'], true)) {
        return '<span class="muted">No admin action</span>';
    }
    $id = (int)$row['id'];
    $html = '<form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '"><input type="hidden" name="withdrawal_id" value="' . h((string)$id) . '">';
    $html .= '<label>Review note <input name="review_note" maxlength="500" placeholder="Optional admin note"></label>';
    $html .= '<button name="action" value="approve_withdrawal" data-confirm="Approve withdrawal #' . h((string)$id) . '? It will be queued for the existing broadcaster job, not broadcast directly by this page.">Approve</button>';
    $html .= '<button name="action" value="manual_review_withdrawal" data-confirm="Mark withdrawal #' . h((string)$id) . ' for manual review?">Manual review</button>';
    $html .= '<button name="action" value="reject_withdrawal" data-confirm="Reject withdrawal #' . h((string)$id) . ' and refund the user ledger hold if needed?">Reject</button>';
    $html .= '</form>';
    return $html;
}

function wallet_admin_render_withdrawals(array $rows, string $emptyTitle, array $pager, array $pageParams): void
{
    admin_filter_box('Filter withdrawals');
    admin_render_table(['ID', 'User', 'Address', 'Amount', 'Status', 'TXID', 'Failure/Note', 'Created', 'Action'], array_map(static fn(array $row): array => [
        h((string)$row['id']),
        h((string)$row['username']),
        admin_hash_cell((string)$row['requested_address']),
        h(wallet_admin_hobc($row['requested_amount'])),
        h((string)$row['status']),
        admin_hash_cell((string)($row['txid'] ?? ''), (string)($row['txid'] ?? '') !== ''),
        h((string)($row['failure_reason'] ?? '')),
        admin_h_datetime($row['created_at'] ?? null),
        wallet_admin_withdrawal_actions($row),
    ], $rows), $emptyTitle, 'No matching withdrawal records exist yet.');
    admin_pagination($pager['page'], $pager['total_pages'], admin_url('/wallet.php'), $pageParams, $pager['total_rows']);
}

render_admin_header('Custodial Wallet Controls', ['Wallets']);
?>
<?php if ($msg): ?><?php admin_render_alert('success', $msg); ?><?php endif; ?>
<?php if ($err): ?><?php admin_render_alert('error', $err); ?><?php endif; ?>
<?php if ($rpcErr): ?><?php admin_render_alert('warning', 'Wallet RPC read issue: ' . $rpcErr); ?><?php endif; ?>

<div class="admin-alert admin-alert-warning">
  Custodial wallet controls can affect real user balances and withdrawal queues. This page never shows private keys, seed phrases, or RPC passwords, and it does not broadcast transactions directly.
</div>

<div class="admin-grid admin-grid-tight">
  <?= admin_stat_card('Total Liabilities', wallet_admin_hobc($liabilities), 'info') ?>
  <?= admin_stat_card('Trusted Hot Wallet', wallet_admin_hobc($hot['trusted']), $delta < 0 ? 'error' : 'ok') ?>
  <?= admin_stat_card('Hot Minus Liabilities', wallet_admin_hobc($delta), $delta < 0 ? 'error' : 'ok') ?>
  <?= admin_stat_card('Pending Withdrawals', (string)$pendingCount, $pendingCount > 0 ? 'warn' : 'ok') ?>
  <?= admin_stat_card('Manual Review', (string)$manualCount, $manualCount > 0 ? 'warn' : 'ok') ?>
  <?= admin_stat_card('Active Holds', (string)wallet_admin_count($pdo, 'wallet_user_holds', "status = 'active'"), wallet_admin_count($pdo, 'wallet_user_holds', "status = 'active'") > 0 ? 'warn' : 'ok') ?>
</div>

<div class="admin-card">
  <div class="admin-actions">
    <?php foreach ($tabs as $key => $label): ?>
      <a class="admin-action <?= $tab === $key ? 'admin-action-primary' : 'admin-action-secondary' ?>" href="<?= h(admin_url('/wallet.php?tab=' . rawurlencode($key))) ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($tab === 'overview'): ?>
  <div class="admin-card">
    <h3>Wallet Overview</h3>
    <p>Real totals come from immutable ledger entries, withdrawal records, deposit records, hot wallet RPC, and scanner state. Empty areas mean no rows have been collected yet.</p>
    <div class="admin-actions">
      <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button name="action" value="pause_withdrawals" data-confirm="Pause all withdrawals?">Pause all withdrawals</button></form>
      <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button name="action" value="resume_withdrawals" data-confirm="Resume withdrawals?">Resume withdrawals</button></form>
      <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button name="action" value="pause_deposits" data-confirm="Pause deposit address display/creation?">Pause deposits display</button></form>
      <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button name="action" value="resume_deposits" data-confirm="Resume deposit address display/creation?">Resume deposits display</button></form>
      <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button name="action" value="refresh_status">Refresh wallet/node status</button></form>
      <a class="admin-action admin-action-secondary" href="<?= h(admin_url('/wallet.php?export=ledger')) ?>">Export wallet ledger CSV</a>
    </div>
  </div>
  <div class="admin-grid">
    <div class="admin-card"><h3>Operational Flags</h3><p>Maintenance: <b><?= ((int)$settings['maintenance_mode'] === 1) ? 'On' : 'Off' ?></b></p><p>Deposits paused: <b><?= ((int)$settings['deposits_paused'] === 1) ? 'Yes' : 'No' ?></b></p><p>Withdrawals paused: <b><?= ((int)$settings['withdrawals_paused'] === 1) ? 'Yes' : 'No' ?></b></p><p>Scanner paused: <b><?= ((int)$settings['scanner_paused'] === 1) ? 'Yes' : 'No' ?></b></p></div>
    <div class="admin-card"><h3>Scanner</h3><p>Status: <b><?= h((string)($scan['scanner_status'] ?? 'unknown')) ?></b></p><p>RPC: <b><?= h((string)($scan['rpc_status'] ?? 'unknown')) ?></b></p><p>Last height: <?= h((string)($scan['last_scanned_height'] ?? '0')) ?></p><?php if (!empty($scan['scanner_last_error']) || !empty($scan['rpc_last_error'])): ?><p class="err"><?= h(trim((string)($scan['scanner_last_error'] ?? '') . ' ' . (string)($scan['rpc_last_error'] ?? ''))) ?></p><?php endif; ?></div>
  </div>
<?php elseif ($tab === 'balances'): ?>
  <div class="admin-card">
    <h3>User Wallet Balances</h3>
    <?php admin_filter_box('Filter users'); ?>
    <?php admin_render_table(['User', 'Email', 'Balance', 'Status', 'Hold', 'Controls'], array_map(static function (array $row): array {
        $holdId = (int)($row['active_hold_id'] ?? 0);
        $controls = $holdId > 0
            ? '<form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="release_hold"><input type="hidden" name="hold_id" value="' . h((string)$holdId) . '"><button type="submit" data-confirm="Remove this user wallet hold?">Remove hold</button></form>'
            : '<form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="place_hold"><input type="hidden" name="user_id" value="' . h((string)$row['id']) . '"><input name="hold_reason" maxlength="255" placeholder="Hold reason" required><button type="submit" data-confirm="Put this user wallet on hold?">Put on hold</button></form>';
        return [h((string)$row['username']), h((string)$row['email']), h(wallet_admin_hobc($row['balance'])), ((int)$row['is_active'] === 1) ? '<span class="ok">Active</span>' : '<span class="warn">Inactive</span>', $holdId > 0 ? '<span class="warn">' . h((string)$row['active_hold_reason']) . '</span>' : '<span class="ok">None</span>', $controls];
    }, $users), 'No wallet users', 'No wallet users exist yet.'); ?>
    <?php admin_pagination($usersPager['page'], $usersPager['total_pages'], admin_url('/wallet.php'), $pageParams, $usersPager['total_rows']); ?>
  </div>
<?php elseif ($tab === 'addresses'): ?>
  <div class="admin-card">
    <h3>Deposit Addresses</h3>
    <?php admin_filter_box('Filter addresses'); ?>
    <?php admin_render_table(['ID', 'User', 'Label', 'Address', 'Role', 'Active', 'Total Received', 'Deposits', 'Assigned'], array_map(static fn(array $row): array => [h((string)$row['id']), h((string)$row['username']), h((string)($row['label'] ?? '')), admin_hash_cell((string)$row['address']), h((string)$row['address_role']), ((int)$row['is_active'] === 1) ? '<span class="ok">Yes</span>' : '<span class="warn">No</span>', h(wallet_admin_hobc($row['total_received'])), h((string)$row['deposit_count']), h((string)$row['assigned_at']) !== '' ? admin_h_datetime((string)$row['assigned_at']) : '—'], $depositAddresses), 'No deposit addresses', 'Deposit addresses will appear after users create receive wallets.'); ?>
    <?php admin_pagination($addressesPager['page'], $addressesPager['total_pages'], admin_url('/wallet.php'), $pageParams, $addressesPager['total_rows']); ?>
  </div>
<?php elseif ($tab === 'deposits'): ?>
  <div class="admin-card">
    <h3>Deposit History</h3>
    <?php admin_filter_box('Filter deposits'); ?>
    <?php admin_render_table(['ID', 'User', 'TXID:VOUT', 'Amount', 'Confirmations', 'Status', 'Behavior', 'Address', 'Created'], array_map(static fn(array $row): array => [h((string)$row['id']), h((string)$row['username']), admin_hash_cell((string)$row['txid'] . ':' . (string)$row['vout'], true), h(wallet_admin_hobc($row['amount'])), h((string)$row['confirmations']), h((string)$row['status']), h((string)$row['credit_behavior']), admin_hash_cell((string)$row['address']), admin_h_datetime($row['created_at'] ?? null)], $deposits), 'No deposits', 'Deposit history will appear after the scanner records real deposits.'); ?>
    <?php admin_pagination($depositsPager['page'], $depositsPager['total_pages'], admin_url('/wallet.php'), $pageParams, $depositsPager['total_rows']); ?>
  </div>
<?php elseif ($tab === 'withdrawals'): ?>
  <div class="admin-card"><h3>Withdrawal Requests</h3><?php wallet_admin_render_withdrawals($withdrawalRows, 'No withdrawals', $withdrawalsPager, $pageParams); ?></div>
<?php elseif ($tab === 'pending'): ?>
  <div class="admin-card"><h3>Pending Withdrawals</h3><?php wallet_admin_render_withdrawals($pendingRows, 'No pending withdrawals', $pendingPager, $pageParams); ?></div>
<?php elseif ($tab === 'approved'): ?>
  <div class="admin-card"><h3>Approved / Broadcasted Withdrawals</h3><?php wallet_admin_render_withdrawals($approvedRows, 'No approved withdrawals', $approvedPager, $pageParams); ?></div>
<?php elseif ($tab === 'rejected'): ?>
  <div class="admin-card"><h3>Rejected Withdrawals</h3><?php wallet_admin_render_withdrawals($rejectedRows, 'No rejected withdrawals', $rejectedPager, $pageParams); ?></div>
<?php elseif ($tab === 'manual-review'): ?>
  <div class="admin-card"><h3>Manual Review Queue</h3><?php wallet_admin_render_withdrawals($manualRows, 'No withdrawals in manual review', $manualPager, $pageParams); ?></div>
<?php elseif ($tab === 'hot-wallet'): ?>
  <div class="admin-card">
    <h3>Hot Wallet Status</h3>
    <p>No private keys or seed phrases are displayed. Balances are read from safe wallet RPC calls only.</p>
    <div class="admin-grid admin-grid-tight">
      <?= admin_stat_card('Trusted', wallet_admin_hobc($hot['trusted']), 'ok') ?>
      <?= admin_stat_card('Untrusted Pending', wallet_admin_hobc($hot['untrusted_pending']), 'warn') ?>
      <?= admin_stat_card('Immature', wallet_admin_hobc($hot['immature']), 'info') ?>
      <?= admin_stat_card('Liabilities', wallet_admin_hobc($liabilities), 'info') ?>
    </div>
    <?php admin_render_table(['When', 'Trusted', 'Pending', 'Immature', 'Liabilities', 'Delta', 'Warning', 'Height'], array_map(static fn(array $row): array => [admin_h_datetime($row['created_at'] ?? null), h(wallet_admin_hobc($row['trusted_balance'])), h(wallet_admin_hobc($row['untrusted_pending'])), h(wallet_admin_hobc($row['immature_balance'])), h(wallet_admin_hobc($row['liabilities_total'])), h(wallet_admin_hobc($row['delta_hot_minus_liabilities'])), ((int)$row['warning_flag'] === 1) ? '<span class="warn">Yes</span>' : '<span class="ok">No</span>', h((string)($row['block_height'] ?? ''))], $snapshots), 'No hot wallet snapshots', 'Run the hot wallet balance checker or reconciliation to create snapshots.'); ?>
    <?php admin_pagination($snapshotsPager['page'], $snapshotsPager['total_pages'], admin_url('/wallet.php'), $pageParams, $snapshotsPager['total_rows']); ?>
  </div>
<?php elseif ($tab === 'reserve-notes'): ?>
  <div class="admin-card">
    <h3>Cold Wallet / Reserve Notes</h3>
    <p>Use notes for operational context only. Do not store seed phrases, private keys, RPC passwords, or recovery words.</p>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="add_note"><input type="hidden" name="note_type" value="reserve"><label>Reserve note<br><textarea name="note" rows="4" required></textarea></label><br><br><button type="submit" data-confirm="Add this reserve note? Do not include secrets.">Add Reserve Note</button></form>
    <?php admin_render_table(['When', 'Type', 'User', 'Withdrawal', 'Admin', 'Note'], array_map(static fn(array $row): array => [admin_h_datetime($row['created_at'] ?? null), h((string)$row['note_type']), h((string)($row['username'] ?? '')), h((string)($row['withdrawal_id'] ?? '')), h((string)($row['admin_name'] ?? '')), h(substr((string)$row['note'], 0, 240))], array_values(array_filter($notes, static fn(array $row): bool => (string)$row['note_type'] === 'reserve'))), 'No reserve notes', 'Cold wallet/reserve notes can be added above.'); ?>
  </div>
<?php elseif ($tab === 'node-rpc'): ?>
  <div class="admin-card"><h3>Node RPC Status</h3><p>Status: <?= $rpcStatuses['node']['ok'] ? '<span class="ok">Online</span>' : '<span class="warn">Offline</span>' ?></p><p><?= h($rpcStatuses['node']['message']) ?></p><?php admin_render_table(['Field', 'Value'], array_map(static fn($key, $value): array => [h((string)$key), h(is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_SLASHES))], array_keys($rpcStatuses['node']['data']), $rpcStatuses['node']['data']), 'No node RPC data', 'Node RPC data is unavailable.'); ?></div>
<?php elseif ($tab === 'wallet-rpc'): ?>
  <div class="admin-card"><h3>Wallet RPC Status</h3><p>Status: <?= $rpcStatuses['wallet']['ok'] ? '<span class="ok">Online</span>' : '<span class="warn">Offline</span>' ?></p><p><?= h($rpcStatuses['wallet']['message']) ?></p><?php admin_render_table(['Field', 'Value'], array_map(static fn($key, $value): array => [h((string)$key), h(is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_SLASHES))], array_keys($rpcStatuses['wallet']['data']), $rpcStatuses['wallet']['data']), 'No wallet RPC data', 'Wallet RPC data is unavailable.'); ?></div>
<?php elseif ($tab === 'broadcast'): ?>
  <div class="admin-card"><h3>Transaction Broadcast Status</h3><p>Approved withdrawals are broadcast only by the existing background broadcaster job. This admin page does not call `sendtoaddress`.</p><?php admin_render_table(['Status', 'Count'], array_map(static fn(array $row): array => [h((string)$row['status']), h((string)$row['count'])], $pdo->query("SELECT status, COUNT(*) AS count FROM withdrawals GROUP BY status ORDER BY status")->fetchAll()), 'No withdrawal statuses', 'No withdrawals exist yet.'); ?><?php wallet_admin_render_withdrawals(array_merge($approvedRows, $failedRows), 'No broadcastable or failed withdrawals', $approvedPager, $pageParams); ?></div>
<?php elseif ($tab === 'failed-ops'): ?>
  <div class="admin-card"><h3>Failed Wallet Operations</h3><?php wallet_admin_render_withdrawals($failedRows, 'No failed withdrawals', $failedPager, $pageParams); ?><?php admin_render_table(['When', 'User', 'Event', 'Severity', 'Details'], array_map(static fn(array $row): array => [admin_h_datetime($row['created_at'] ?? null), h((string)($row['username'] ?? '')), h((string)$row['event_type']), h((string)$row['severity']), h(substr((string)$row['details_json'], 0, 220))], array_values(array_filter($suspicious, static fn(array $row): bool => str_contains((string)$row['event_type'], 'withdraw')))), 'No failed wallet security events', 'Wallet security events will appear when recorded.'); ?><?php admin_pagination($suspiciousPager['page'], $suspiciousPager['total_pages'], admin_url('/wallet.php'), $pageParams, $suspiciousPager['total_rows']); ?></div>
<?php elseif ($tab === 'audit'): ?>
  <div class="admin-card">
    <h3>Wallet Audit Logs</h3>
    <div class="admin-actions"><a class="admin-action admin-action-secondary" href="<?= h(admin_url('/wallet.php?export=ledger')) ?>">Export Wallet Ledger CSV</a></div>
    <?php admin_render_table(['When', 'Admin', 'Action', 'Target', 'IP', 'Details'], array_map(static fn(array $row): array => [admin_h_datetime($row['created_at'] ?? null), h((string)($row['admin_name'] ?? 'system')), h((string)$row['action']), h(trim((string)$row['target_type'] . '#' . (string)$row['target_id'], '#')), h((string)$row['ip_address']), h(substr((string)$row['details_json'], 0, 220))], $audit), 'No wallet audit logs', 'Wallet admin actions will appear here.'); ?>
    <?php admin_pagination($auditPager['page'], $auditPager['total_pages'], admin_url('/wallet.php'), $pageParams, $auditPager['total_rows']); ?>
    <h4>Recent Immutable Ledger Entries</h4>
    <?php admin_render_table(['ID', 'When', 'User', 'Type', 'Amount', 'Reference', 'Actor', 'Note'], array_map(static fn(array $row): array => [h((string)$row['id']), admin_h_datetime($row['created_at'] ?? null), h((string)$row['username']), h((string)$row['entry_type']), h(wallet_admin_hobc($row['amount'])), h((string)$row['reference_type'] . '#' . (string)$row['reference_id']), h((string)$row['actor_type']), h((string)$row['note'])], $ledgerRecent), 'No ledger entries', 'Ledger entries are immutable and will appear after real deposits/withdrawals.'); ?>
    <?php admin_pagination($ledgerPager['page'], $ledgerPager['total_pages'], admin_url('/wallet.php'), $pageParams, $ledgerPager['total_rows']); ?>
  </div>
<?php elseif ($tab === 'reconciliation'): ?>
  <div class="admin-card">
    <h3>Balance Reconciliation</h3>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button name="action" value="run_reconciliation" data-confirm="Run a balance reconciliation check using wallet RPC and immutable ledger totals?">Run balance reconciliation check</button></form>
    <?php admin_render_table(['When', 'Liabilities', 'Trusted', 'Delta', 'Status', 'Details'], array_map(static fn(array $row): array => [admin_h_datetime($row['created_at'] ?? null), h(wallet_admin_hobc($row['liabilities_total'])), h(wallet_admin_hobc($row['trusted_balance'])), h(wallet_admin_hobc($row['delta_hot_minus_liabilities'])), ((string)$row['status'] === 'ok') ? '<span class="ok">OK</span>' : '<span class="warn">Warning</span>', h(substr((string)$row['details_json'], 0, 240))], $reports), 'No reconciliation reports', 'Run a reconciliation check to create the first report.'); ?>
    <?php admin_pagination($reportsPager['page'], $reportsPager['total_pages'], admin_url('/wallet.php'), $pageParams, $reportsPager['total_rows']); ?>
  </div>
<?php elseif ($tab === 'suspicious'): ?>
  <div class="admin-card">
    <h3>Suspicious Wallet Activity</h3>
    <?php admin_render_table(['When', 'User', 'Event', 'Severity', 'IP', 'User Agent', 'Details'], array_map(static fn(array $row): array => [admin_h_datetime($row['created_at'] ?? null), h((string)($row['username'] ?? '')), h((string)$row['event_type']), h((string)$row['severity']), h((string)$row['ip_address']), h(substr((string)$row['user_agent'], 0, 140)), h(substr((string)$row['details_json'], 0, 220))], $suspicious), 'No suspicious wallet activity', 'Warnings, critical security events, and withdrawal/login anomalies appear here.'); ?>
    <?php admin_pagination($suspiciousPager['page'], $suspiciousPager['total_pages'], admin_url('/wallet.php'), $pageParams, $suspiciousPager['total_rows']); ?>
    <h4>Active Wallet Holds</h4>
    <?php admin_render_table(['ID', 'User', 'Reason', 'Placed By', 'Placed', 'Control'], array_map(static fn(array $row): array => [h((string)$row['id']), h((string)$row['username']), h((string)$row['hold_reason']), h((string)($row['placed_by_name'] ?? '')), admin_h_datetime($row['placed_at'] ?? null), (string)$row['status'] === 'active' ? '<form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="release_hold"><input type="hidden" name="hold_id" value="' . h((string)$row['id']) . '"><button type="submit" data-confirm="Release this wallet hold?">Release</button></form>' : h((string)$row['status'])], array_values(array_filter($holds, static fn(array $row): bool => (string)$row['status'] === 'active'))), 'No active holds', 'Active wallet holds will appear here.'); ?>
  </div>
<?php elseif ($tab === 'limits' || $tab === 'settings'): ?>
  <div class="admin-card">
    <h3><?= $tab === 'limits' ? 'Withdrawal Limits' : 'Custodial Wallet Settings' ?></h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="settings">
      <label><input type="checkbox" name="maintenance_mode" <?= ((int)$settings['maintenance_mode'] === 1) ? 'checked' : '' ?>> Wallet maintenance mode</label><br>
      <label><input type="checkbox" name="deposits_paused" <?= ((int)$settings['deposits_paused'] === 1) ? 'checked' : '' ?>> Pause deposits display / address creation</label><br>
      <label><input type="checkbox" name="withdrawals_paused" <?= ((int)$settings['withdrawals_paused'] === 1) ? 'checked' : '' ?>> Pause all withdrawals</label><br>
      <label><input type="checkbox" name="scanner_paused" <?= ((int)$settings['scanner_paused'] === 1) ? 'checked' : '' ?>> Pause deposit scanner</label><br><br>
      <label>Minimum Withdrawal<br><input type="number" step="0.00000001" min="0.00000001" name="per_withdrawal_min_amount" value="<?= h(wallet_admin_hobc($settings['per_withdrawal_min_amount'])) ?>"></label><br><br>
      <label>Maximum Per Withdrawal<br><input type="number" step="0.00000001" min="0.00000001" name="per_withdrawal_max_amount" value="<?= h(wallet_admin_hobc($settings['per_withdrawal_max_amount'])) ?>"></label><br><br>
      <label>Maximum Daily Hot Wallet Broadcast Limit<br><input type="number" step="0.00000001" min="0.00000001" name="daily_hot_wallet_broadcast_limit" value="<?= h(wallet_admin_hobc($settings['daily_hot_wallet_broadcast_limit'])) ?>"></label><br><br>
      <label>Admin Approval Threshold<br><input type="number" step="0.00000001" min="0.00000001" name="admin_approval_threshold" value="<?= h(wallet_admin_hobc($settings['admin_approval_threshold'])) ?>"></label><br><br>
      <label>Deposit Confirmations Required<br><input type="number" min="1" name="deposit_confirmations_required" value="<?= h((string)$settings['deposit_confirmations_required']) ?>"></label><br><br>
      <label>Withdrawal Confirmations Required<br><input type="number" min="1" name="withdrawal_confirmations_required" value="<?= h((string)$settings['withdrawal_confirmations_required']) ?>"></label><br><br>
      <button type="submit" data-confirm="Save custodial wallet settings and limits?">Save Wallet Settings</button>
    </form>
  </div>
<?php endif; ?>

<?php if (!in_array($tab, ['reserve-notes'], true)): ?>
<div class="admin-card">
  <h3>Add Admin Note</h3>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="add_note">
    <label>Note Type<br><select name="note_type"><option value="user">User</option><option value="withdrawal">Withdrawal</option><option value="operation">Operation</option><option value="reserve">Reserve</option></select></label><br><br>
    <label>User ID (optional)<br><input type="number" name="note_user_id" min="1"></label><br><br>
    <label>Withdrawal ID (optional)<br><input type="number" name="note_withdrawal_id" min="1"></label><br><br>
    <label>Note<br><textarea name="note" rows="3" required></textarea></label><br><br>
    <button type="submit" data-confirm="Add this wallet admin note? Do not include secrets.">Add Admin Note</button>
  </form>
</div>
<?php endif; ?>

<?php render_admin_footer(); ?>
