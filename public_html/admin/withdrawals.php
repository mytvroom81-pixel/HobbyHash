<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/ledger.php';
require_once __DIR__ . '/../app/security_log.php';
require_once __DIR__ . '/../app/admin_view.php';

$admin = admin_require_user();
$msg = '';
$err = '';
$pdo = wallet_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $id = (int)($_POST['withdrawal_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        $err = 'Invalid action.';
    } else {
        $pdo->beginTransaction();
        try {
            $s = $pdo->prepare("SELECT * FROM withdrawals WHERE id = ? FOR UPDATE");
            $s->execute([$id]);
            $w = $s->fetch();
            if (!$w) {
                throw new RuntimeException('Withdrawal not found.');
            }
            if (!in_array($w['status'], ['awaiting_approval', 'pending'], true)) {
                throw new RuntimeException('Withdrawal is not awaiting admin action.');
            }

            if ($action === 'approve') {
                $u = $pdo->prepare("UPDATE withdrawals SET status = 'approved', approved_by_admin_id = ?, approved_at = UTC_TIMESTAMP() WHERE id = ?");
                $u->execute([(int)$admin['id'], $id]);
                admin_audit((int)$admin['id'], 'withdrawal_approved', 'withdrawal', (string)$id);
                $msg = 'Withdrawal approved.';
            } else {
                $u = $pdo->prepare("UPDATE withdrawals SET status = 'rejected', rejected_by_admin_id = ?, rejected_at = UTC_TIMESTAMP() WHERE id = ?");
                $u->execute([(int)$admin['id'], $id]);
                ledger_add(
                    (int)$w['user_id'],
                    'refund_credit',
                    number_format((float)$w['requested_amount'] + (float)$w['fee_amount'], 8, '.', ''),
                    'withdrawals',
                    $id,
                    'admin',
                    (int)$admin['id'],
                    'Admin rejected withdrawal'
                );
                admin_audit((int)$admin['id'], 'withdrawal_rejected', 'withdrawal', (string)$id);
                $msg = 'Withdrawal rejected and refunded.';
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $err = $e->getMessage();
            wallet_log_error('admin withdrawal action failed: ' . $e->getMessage());
        }
    }
}

$pending = $pdo->query(
    "SELECT w.id, u.username, w.requested_address, w.requested_amount, w.status, w.requires_admin_approval, w.created_at
     FROM withdrawals w JOIN users u ON u.id = w.user_id
     WHERE w.status IN ('awaiting_approval','pending')
     ORDER BY w.id ASC"
);

$recent = $pdo->query(
    "SELECT w.id, u.username, w.requested_amount, w.status, w.txid, w.updated_at
     FROM withdrawals w JOIN users u ON u.id = w.user_id
     ORDER BY w.id DESC LIMIT 50"
);

render_admin_header('Withdrawals');
?>
<div class="admin-card">
  <h3>Pending Withdrawal Queue</h3>
  <?php if ($msg): ?><?php admin_render_alert('success', $msg); ?><?php endif; ?>
  <?php if ($err): ?><?php admin_render_alert('error', $err); ?><?php endif; ?>
  <?php
    $pendingRows = [];
    foreach ($pending as $row) {
        $actions = '<div class="admin-actions">'
            . '<form method="post" style="display:inline">'
            . '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'
            . '<input type="hidden" name="withdrawal_id" value="' . h((string)$row['id']) . '">'
            . '<input type="hidden" name="action" value="approve">'
            . '<button type="submit" data-confirm="Approve withdrawal #' . h((string)$row['id']) . ' for ' . h((string)$row['requested_amount']) . ' HOBC?">Approve</button>'
            . '</form>'
            . '<form method="post" style="display:inline">'
            . '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'
            . '<input type="hidden" name="withdrawal_id" value="' . h((string)$row['id']) . '">'
            . '<input type="hidden" name="action" value="reject">'
            . '<button type="submit" data-confirm="Reject withdrawal #' . h((string)$row['id']) . ' and refund the user ledger balance?">Reject</button>'
            . '</form></div>';
        $pendingRows[] = [
            h((string)$row['id']),
            h($row['username']),
            '<code>' . h($row['requested_address']) . '</code>',
            h($row['requested_amount']),
            h($row['status']),
            $actions,
        ];
    }
    admin_render_table(['ID', 'User', 'Address', 'Amount', 'Status', 'Action'], $pendingRows, 'No pending withdrawals', 'There are no withdrawals awaiting admin action.');
  ?>
</div>

<div class="admin-card">
  <h3>Recent Withdrawals</h3>
  <?php
    $recentRows = [];
    foreach ($recent as $row) {
        $recentRows[] = [
            h((string)$row['id']),
            h($row['username']),
            h($row['requested_amount']),
            h($row['status']),
            '<code>' . h((string)($row['txid'] ?? '')) . '</code>',
            admin_h_datetime($row['updated_at'] ?? null),
        ];
    }
    admin_filter_box('Filter recent withdrawals');
    admin_render_table(['ID', 'User', 'Amount', 'Status', 'TXID', 'Updated'], $recentRows, 'No withdrawals yet', 'No withdrawal records are available yet.');
  ?>
</div>
<?php render_admin_footer(); ?>
