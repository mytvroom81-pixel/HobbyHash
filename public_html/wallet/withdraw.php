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

function wallet_user_has_active_hold(int $userId): bool
{
    try {
        $exists = wallet_db()->query("SHOW TABLES LIKE 'wallet_user_holds'")->fetchColumn();
        if (!$exists) {
            return false;
        }
        $stmt = wallet_db()->prepare("SELECT id FROM wallet_user_holds WHERE user_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$userId]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        wallet_log_error('wallet hold check failed: ' . $e->getMessage());
        return true;
    }
}

$err = '';
$ok = '';
$addressValue = '';
$amountValue = '';
$openWithdrawModal = false;
$withdrawSmsRequired = sms_user_requires_withdrawal_2fa($user);
$withdrawTotpRequired = totp_user_requires_withdrawal($user);
$walletOnHold = wallet_user_has_active_hold((int)$user['id']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $action = (string)($_POST['action'] ?? 'withdraw');
    $addressValue = trim((string)($_POST['address'] ?? ''));
    $amountValue = trim((string)($_POST['amount'] ?? ''));
    if ($action === 'send_withdraw_sms') {
        if (!$withdrawSmsRequired) {
            $err = wallet_te('wallet.error.sms_not_enabled', [], 'SMS withdrawal verification is not enabled.');
        } else {
            try {
                $challenge = sms_create_challenge('user', (int)$user['id'], 'wallet_withdrawal', (string)$user['phone_number']);
                sms_send_code((int)$challenge['id'], (string)$challenge['phone_number'], (string)$challenge['code'], 'withdrawal');
                $_SESSION['pending_withdrawal_sms_challenge_id'] = (int)$challenge['id'];
                security_log_event((int)$user['id'], 'wallet_withdrawal_sms_code_sent', 'info');
                $ok = wallet_te('wallet.success.sms_sent', [], 'SMS withdrawal code sent.');
                $openWithdrawModal = true;
            } catch (Throwable $e) {
                wallet_log_error('withdrawal SMS code send failed: ' . $e->getMessage());
                $err = wallet_te('wallet.error.sms_send_failed', [], 'SMS withdrawal code could not be sent.');
                $openWithdrawModal = true;
            }
        }
    } elseif ($walletOnHold) {
        $err = wallet_te('wallet.error.admin_hold', [], 'Your wallet is currently on administrative hold. Please contact support.');
    } elseif ((int)$settings['withdrawals_paused'] === 1) {
        $err = wallet_te('wallet.error.withdrawals_paused', [], 'Withdrawals are currently paused.');
    } else {
        $address = $addressValue;
        $amountRaw = $amountValue;
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
            $err = wallet_te('wallet.error.withdraw_throttled', [], 'Withdrawal throttled. Please wait.');
        } elseif ($amount <= 0 || $amount < $min || $amount > $max) {
            $err = wallet_te('wallet.error.withdraw_range', [
                'min' => number_format($min, 8, '.', ''),
                'max' => number_format($max, 8, '.', ''),
            ], 'Withdrawal amount outside allowed range. Allowed range: {min} – {max} HOBC.');
        } elseif (strlen($address) < 20 || strlen($address) > 128) {
            $err = wallet_te('wallet.error.invalid_address', [], 'Invalid destination address format.');
        } else {
            $ownAddress = wallet_db()->prepare("SELECT id FROM deposit_addresses WHERE user_id = ? AND address = ? AND is_active = 1 LIMIT 1");
            $ownAddress->execute([(int)$user['id'], $address]);
            if ($ownAddress->fetch()) {
                $err = wallet_te('wallet.error.self_withdraw', [], 'That address belongs to your own wallet. Use your dashboard balance instead of sending to yourself.');
            }
        }

        if ($err === '' && $withdrawSmsRequired) {
            $challengeId = (int)($_SESSION['pending_withdrawal_sms_challenge_id'] ?? 0);
            $smsCode = trim((string)($_POST['sms_code'] ?? ''));
            if ($challengeId <= 0 || !sms_verify_challenge($challengeId, 'user', (int)$user['id'], 'wallet_withdrawal', $smsCode)) {
                $err = wallet_te('wallet.error.sms_required', [], 'Valid SMS withdrawal code is required.');
            } else {
                unset($_SESSION['pending_withdrawal_sms_challenge_id']);
                security_log_event((int)$user['id'], 'wallet_withdrawal_sms_verified', 'info');
            }
        }

        if ($err === '' && $withdrawTotpRequired) {
            $totpCode = trim((string)($_POST['totp_code'] ?? ''));
            $totpSecret = totp_user_secret((int)$user['id']);
            if ($totpSecret === '' || !totp_verify($totpSecret, $totpCode)) {
                $err = wallet_te('wallet.error.totp_required', [], 'Valid authenticator code is required.');
            } else {
                security_log_event((int)$user['id'], 'wallet_withdrawal_totp_verified', 'info');
            }
        }

        if ($err === '') {
            $balance = (float)ledger_user_balance((int)$user['id']);
            if ($balance < ($amount + $fee)) {
                $err = wallet_te('wallet.error.insufficient_balance', [], 'Insufficient internal balance.');
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
                        ? wallet_te('wallet.success.withdraw_pending_approval', [], 'Withdrawal submitted and awaiting admin approval.')
                        : wallet_te('wallet.success.withdraw_submitted', [], 'Withdrawal submitted and queued for broadcast.');
                    $addressValue = '';
                    $amountValue = '';
                } catch (Throwable $e) {
                    if (wallet_db()->inTransaction()) {
                        wallet_db()->rollBack();
                    }
                    wallet_log_error('withdraw request failed: ' . $e->getMessage());
                    $err = wallet_te('wallet.error.withdraw_failed', [], 'Withdrawal request failed.');
                }
            }
        }
        if ($err !== '') {
            $openWithdrawModal = true;
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

render_header('wallet.page.withdraw.title');
?>
<div class="card">
  <h3><?= h(wallet_te('wallet.page.withdraw.title', [], 'Withdraw HOBC')) ?></h3>
  <p><?= h(wallet_te('wallet.page.withdraw.allowed_range', [
    'min' => number_format($withdrawMin, 8, '.', ''),
    'max' => number_format($withdrawMax, 8, '.', ''),
  ], 'Allowed withdrawal range: {min} to {max} HOBC.')) ?></p>
  <?php if ($walletOnHold): ?><p class="err"><?= h(wallet_te('wallet.page.withdraw.on_hold_banner', [], 'This wallet is currently on administrative hold. Withdrawals are disabled until the hold is removed.')) ?></p><?php endif; ?>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
  <form method="post" id="withdraw-start-form">
    <label><?= h(wallet_te('wallet.page.withdraw.destination_address', [], 'Destination Address')) ?><br><input name="address" id="withdraw-address" value="<?= h($addressValue) ?>" required style="width:100%"></label><br><br>
    <label><?= h(wallet_te('wallet.page.withdraw.amount', [], 'Amount')) ?><br><input name="amount" id="withdraw-amount" value="<?= h($amountValue) ?>" type="number" step="0.00000001" min="<?= h(number_format($withdrawMin, 8, '.', '')) ?>" max="<?= h(number_format($withdrawMax, 8, '.', '')) ?>" required></label><br><br>
    <button type="button" data-open-withdraw-modal><?= h(wallet_te('wallet.page.withdraw.send', [], 'Send')) ?></button>
  </form>
</div>

<div class="modal-backdrop <?= $openWithdrawModal ? 'is-open' : '' ?>" id="withdraw-confirm-modal" aria-hidden="<?= $openWithdrawModal ? 'false' : 'true' ?>">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="withdraw-confirm-title">
    <div class="modal-header">
      <div>
        <span class="eyebrow"><?= h(wallet_te('wallet.page.withdraw.confirm_heading', [], 'Confirm Withdrawal')) ?></span>
        <h3 id="withdraw-confirm-title"><?= h(wallet_te('wallet.page.withdraw.send_heading', [], 'Send HOBC')) ?></h3>
      </div>
      <button type="button" class="small-button modal-close" data-close-modal><?= h(wallet_te('wallet.button.close', [], 'Close')) ?></button>
    </div>
    <p><?= h(wallet_te('wallet.page.withdraw.review_prompt', [], 'Review the destination and amount. Complete SMS verification before creating the withdrawal request.')) ?></p>
    <div class="table-like">
      <div class="table-row"><span><?= h(wallet_te('wallet.page.withdraw.destination', [], 'Destination')) ?></span><strong id="withdraw-modal-address"><?= h($addressValue !== '' ? $addressValue : wallet_te('wallet.page.withdraw.not_entered', [], 'Not entered')) ?></strong></div>
      <div class="table-row"><span><?= h(wallet_te('wallet.page.withdraw.amount', [], 'Amount')) ?></span><strong><span id="withdraw-modal-amount"><?= h($amountValue !== '' ? $amountValue : '0') ?></span> <?= h(wallet_te('wallet.currency_suffix', [], 'HOBC')) ?></strong></div>
    </div>
    <?php if ($withdrawSmsRequired): ?>
      <form method="post" class="modal-security-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="send_withdraw_sms">
        <input type="hidden" name="address" class="withdraw-modal-address-input" value="<?= h($addressValue) ?>">
        <input type="hidden" name="amount" class="withdraw-modal-amount-input" value="<?= h($amountValue) ?>">
        <button type="submit"><?= h(wallet_te('wallet.page.withdraw.send_sms_code', [], 'Send SMS Code')) ?></button>
      </form>
    <?php endif; ?>
    <form method="post" class="modal-security-form">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="withdraw">
      <input type="hidden" name="address" class="withdraw-modal-address-input" value="<?= h($addressValue) ?>">
      <input type="hidden" name="amount" class="withdraw-modal-amount-input" value="<?= h($amountValue) ?>">
      <?php if ($withdrawSmsRequired): ?>
        <label><?= h(wallet_te('wallet.page.withdraw.sms_code_label', [], 'SMS Code')) ?><br><input name="sms_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label><br><br>
      <?php endif; ?>
      <?php if ($withdrawTotpRequired): ?>
        <label><?= h(wallet_te('wallet.page.withdraw.auth_code_label', [], 'Authenticator Code')) ?><br><input name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label><br><br>
      <?php endif; ?>
      <button type="submit"><?= h(wallet_te('wallet.page.withdraw.confirm_send', [], 'Confirm and Send')) ?></button>
    </form>
  </div>
</div>

<div class="card">
  <h3><?= h(wallet_te('wallet.page.withdraw.recent_heading', [], 'Recent Withdrawals')) ?></h3>
  <table>
    <tr><th><?= h(wallet_te('wallet.table.id', [], 'ID')) ?></th><th><?= h(wallet_te('wallet.table.address', [], 'Address')) ?></th><th><?= h(wallet_te('wallet.table.amount', [], 'Amount')) ?></th><th><?= h(wallet_te('wallet.table.fee', [], 'Fee')) ?></th><th><?= h(wallet_te('wallet.table.status', [], 'Status')) ?></th><th><?= h(wallet_te('wallet.table.txid', [], 'TXID')) ?></th><th><?= h(wallet_te('wallet.table.created', [], 'Created')) ?></th></tr>
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
      <tr><td colspan="7"><?= h(wallet_te('wallet.page.withdraw.no_withdrawals', [], 'No withdrawals found yet.')) ?></td></tr>
    <?php endif; ?>
  </table>
  <?= wallet_pagination('/withdraw.php', 'withdraw_page', $withdrawPage, $withdrawTotalPages) ?>
</div>
<script>
(() => {
  const i18n = <?= wallet_js_i18n([
    'wallet.page.withdraw.not_entered' => 'Not entered',
  ]) ?>;
  const modal = document.getElementById('withdraw-confirm-modal');
  const openButton = document.querySelector('[data-open-withdraw-modal]');
  const addressInput = document.getElementById('withdraw-address');
  const amountInput = document.getElementById('withdraw-amount');
  const modalAddress = document.getElementById('withdraw-modal-address');
  const modalAmount = document.getElementById('withdraw-modal-amount');
  const hiddenAddresses = document.querySelectorAll('.withdraw-modal-address-input');
  const hiddenAmounts = document.querySelectorAll('.withdraw-modal-amount-input');

  const closeModal = () => {
    modal?.classList.remove('is-open');
    modal?.setAttribute('aria-hidden', 'true');
  };

  const openModal = () => {
    if (!modal || !addressInput || !amountInput) return;
    if (!addressInput.reportValidity() || !amountInput.reportValidity()) return;
    const address = addressInput.value.trim();
    const amount = amountInput.value.trim();
    modalAddress.textContent = address || i18n['wallet.page.withdraw.not_entered'];
    modalAmount.textContent = amount;
    hiddenAddresses.forEach((input) => { input.value = address; });
    hiddenAmounts.forEach((input) => { input.value = amount; });
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
  };

  openButton?.addEventListener('click', openModal);
  document.querySelectorAll('[data-close-modal]').forEach((button) => {
    button.addEventListener('click', closeModal);
  });
  modal?.addEventListener('click', (event) => {
    if (event.target === modal) closeModal();
  });
})();
</script>
<?php render_footer(); ?>
