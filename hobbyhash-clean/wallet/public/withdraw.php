<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/ledger.php';
require_once __DIR__ . '/../app/settings.php';
require_once __DIR__ . '/../app/throttle.php';
require_once __DIR__ . '/../app/sms.php';
require_once __DIR__ . '/../app/totp.php';
require_once __DIR__ . '/../app/security_log.php';
require_once __DIR__ . '/../app/view.php';

$user = auth_require_user();
$settings = wallet_settings();
if ((int)$settings['maintenance_mode'] === 1) {
    wallet_redirect(wallet_url('/maintenance.php'));
}

$err = '';
$ok = '';
$withdrawSmsRequired = sms_user_requires_withdrawal_2fa($user);
$withdrawTotpRequired = totp_user_requires_withdrawal($user);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $action = (string)($_POST['action'] ?? 'withdraw');
    if ($action === 'send_withdraw_sms') {
        if (!$withdrawSmsRequired) {
            $err = 'SMS withdrawal verification is not enabled.';
        } else {
            try {
                $challenge = sms_create_challenge('user', (int)$user['id'], 'wallet_withdrawal', (string)$user['phone_number']);
                sms_send_code((int)$challenge['id'], (string)$challenge['phone_number'], (string)$challenge['code'], 'withdrawal');
                $_SESSION['pending_withdrawal_sms_challenge_id'] = (int)$challenge['id'];
                security_log_event((int)$user['id'], 'wallet_withdrawal_sms_code_sent', 'info');
                $ok = 'SMS withdrawal code sent.';
            } catch (Throwable $e) {
                wallet_log_error('withdrawal SMS code send failed: ' . $e->getMessage());
                $err = 'SMS withdrawal code could not be sent.';
            }
        }
    } elseif ((int)$settings['withdrawals_paused'] === 1) {
        $err = 'Withdrawals are currently paused.';
    } else {
        $address = trim((string)($_POST['address'] ?? ''));
        $amountRaw = trim((string)($_POST['amount'] ?? ''));
        $amount = is_numeric($amountRaw) ? (float)$amountRaw : 0.0;
        $fee = 0.0;
        $min = (float)($settings['per_withdrawal_min_amount'] ?? 0.00000001);
        $max = (float)($settings['per_withdrawal_max_amount'] ?? 50000.00000000);
        $threshold = (float)$settings['admin_approval_threshold'];

        $bucketKey = (string)$user['id'] . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $allowed = throttle_check_and_increment(
            'withdraw',
            $bucketKey,
            (int)(wallet_config()['security']['max_withdrawals_per_hour'] ?? 5),
            3600
        );
        if (!$allowed) {
            $err = 'Withdrawal throttled. Please wait.';
        } elseif ($amount <= 0 || $amount < $min || $amount > $max) {
            $err = 'Withdrawal amount outside allowed range. Allowed range: '
                . number_format($min, 8, '.', '')
                . ' to '
                . number_format($max, 8, '.', '')
                . ' HOBC.';
        } elseif (strlen($address) < 20 || strlen($address) > 128) {
            $err = 'Invalid destination address format.';
        } else {
            $ownAddress = wallet_db()->prepare("SELECT id FROM deposit_addresses WHERE user_id = ? AND address = ? AND is_active = 1 LIMIT 1");
            $ownAddress->execute([(int)$user['id'], $address]);
            if ($ownAddress->fetch()) {
                $err = 'That address belongs to your own wallet. Use your dashboard balance instead of sending to yourself.';
            }
        }

        if ($err === '' && $withdrawSmsRequired) {
            $challengeId = (int)($_SESSION['pending_withdrawal_sms_challenge_id'] ?? 0);
            $smsCode = trim((string)($_POST['sms_code'] ?? ''));
            if ($challengeId <= 0 || !sms_verify_challenge($challengeId, 'user', (int)$user['id'], 'wallet_withdrawal', $smsCode)) {
                $err = 'Valid SMS withdrawal code is required.';
            } else {
                unset($_SESSION['pending_withdrawal_sms_challenge_id']);
                security_log_event((int)$user['id'], 'wallet_withdrawal_sms_verified', 'info');
            }
        }

        if ($err === '' && $withdrawTotpRequired) {
            $totpCode = trim((string)($_POST['totp_code'] ?? ''));
            $totpSecret = totp_user_secret((int)$user['id']);
            if ($totpSecret === '' || !totp_verify($totpSecret, $totpCode)) {
                $err = 'Valid authenticator code is required.';
            } else {
                security_log_event((int)$user['id'], 'wallet_withdrawal_totp_verified', 'info');
            }
        }

        if ($err === '') {
            $balance = (float)ledger_user_balance((int)$user['id']);
            if ($balance < ($amount + $fee)) {
                $err = 'Insufficient internal balance.';
            } else {
                $requiresApproval = $amount > $threshold ? 1 : 0;
                $status = $requiresApproval ? 'awaiting_approval' : 'approved';
                try {
                    $pdo = wallet_db();
                    $pdo->beginTransaction();
                    $w = $pdo->prepare(
                        "INSERT INTO withdrawals
                        (user_id, requested_address, requested_amount, fee_amount, status, requires_admin_approval, request_ip, request_user_agent)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $w->execute([
                        (int)$user['id'],
                        $address,
                        number_format($amount, 8, '.', ''),
                        number_format($fee, 8, '.', ''),
                        $status,
                        $requiresApproval,
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
                    ]);
                    $wid = (int)$pdo->lastInsertId();
                    ledger_add(
                        (int)$user['id'],
                        'withdraw_debit',
                        number_format(-1 * ($amount + $fee), 8, '.', ''),
                        'withdrawals',
                        $wid,
                        'user',
                        (int)$user['id'],
                        'Withdrawal request hold'
                    );
                    $pdo->commit();
                    security_log_event((int)$user['id'], 'withdrawal_requested', 'info', ['withdrawal_id' => $wid, 'amount' => $amount]);
                    $ok = $requiresApproval
                        ? 'Withdrawal submitted and awaiting admin approval.'
                        : 'Withdrawal submitted and queued for broadcast.';
                } catch (Throwable $e) {
                    if (wallet_db()->inTransaction()) {
                        wallet_db()->rollBack();
                    }
                    wallet_log_error('withdraw request failed: ' . $e->getMessage());
                    $err = 'Withdrawal request failed.';
                }
            }
        }
    }
}

$withdrawPage = max(1, (int)($_GET['withdraw_page'] ?? 1));
$withdrawTotalStmt = wallet_db()->prepare("SELECT COUNT(*) FROM withdrawals WHERE user_id = ?");
$withdrawTotalStmt->execute([(int)$user['id']]);
$withdrawTotal = (int)$withdrawTotalStmt->fetchColumn();
$withdrawTotalPages = max(1, (int)ceil($withdrawTotal / 10));
$withdrawPage = min($withdrawPage, $withdrawTotalPages);
$withdrawOffset = ($withdrawPage - 1) * 10;
$recent = wallet_db()->prepare("SELECT id, requested_address, requested_amount, fee_amount, status, txid, created_at FROM withdrawals WHERE user_id = ? ORDER BY id DESC LIMIT 10 OFFSET {$withdrawOffset}");
$recent->execute([(int)$user['id']]);
$withdrawMin = (float)($settings['per_withdrawal_min_amount'] ?? 0.00000001);
$withdrawMax = (float)($settings['per_withdrawal_max_amount'] ?? 50000.00000000);

render_header('Withdraw');
?>
<div class="card">
  <h3>Withdraw HOBC</h3>
  <p>Allowed withdrawal range: <?= h(number_format($withdrawMin, 8, '.', '')) ?> to <?= h(number_format($withdrawMax, 8, '.', '')) ?> HOBC.</p>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
  <?php if ($withdrawSmsRequired): ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="send_withdraw_sms">
      <button type="submit">Send SMS Withdrawal Code</button>
    </form>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="withdraw">
    <label>Destination Address<br><input name="address" required style="width:100%"></label><br><br>
    <label>Amount<br><input name="amount" type="number" step="0.00000001" min="<?= h(number_format($withdrawMin, 8, '.', '')) ?>" max="<?= h(number_format($withdrawMax, 8, '.', '')) ?>" required></label><br><br>
    <?php if ($withdrawSmsRequired): ?>
      <label>SMS Code<br><input name="sms_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label><br><br>
    <?php endif; ?>
    <?php if ($withdrawTotpRequired): ?>
      <label>Authenticator Code<br><input name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label><br><br>
    <?php endif; ?>
    <button type="submit">Create Withdrawal Request</button>
  </form>
</div>

<div class="card">
  <h3>Recent Withdrawals</h3>
  <table>
    <tr><th>ID</th><th>Address</th><th>Amount</th><th>Fee</th><th>Status</th><th>TXID</th><th>Created</th></tr>
    <?php foreach ($recent as $row): ?>
      <tr>
        <td><?= h((string)$row['id']) ?></td>
        <td><?= wallet_address_text((string)$row['requested_address']) ?></td>
        <td><?= h($row['requested_amount']) ?></td>
        <td><?= h($row['fee_amount']) ?></td>
        <td><?= h($row['status']) ?></td>
        <td><?= wallet_value_chip((string)($row['txid'] ?? '')) ?></td>
        <td><?= h($row['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($withdrawTotal === 0): ?>
      <tr><td colspan="7">No withdrawals found yet.</td></tr>
    <?php endif; ?>
  </table>
  <?= wallet_pagination('/withdraw.php', 'withdraw_page', $withdrawPage, $withdrawTotalPages) ?>
</div>
<?php render_footer(); ?>
