<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/account_security.php';
require_once __DIR__ . '/../app/view.php';

wallet_start_session();
account_security_ensure_schema();

$requestId = (int)($_SESSION['pending_password_reset_id'] ?? ($_GET['rid'] ?? 0));
if ($requestId <= 0) {
    wallet_redirect(wallet_url('/forgot-password.php'));
}

$stmt = wallet_db()->prepare(
    "SELECT r.*, u.email, u.phone_number
     FROM account_recovery_requests r
     JOIN users u ON u.id = r.user_id
     WHERE r.id = ? AND r.consumed_at IS NULL
     LIMIT 1"
);
$stmt->execute([$requestId]);
$request = $stmt->fetch();
if (!$request) {
    unset($_SESSION['pending_password_reset_id']);
    wallet_redirect(wallet_url('/forgot-password.php'));
}

$err = '';
$ok = '';
$complete = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $code = trim((string)($_POST['code'] ?? ''));
    $totpCode = trim((string)($_POST['totp_code'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');
    if ($password !== $confirm) {
        $err = wallet_te('wallet.error.reset_password_match', [], 'Passwords do not match.');
    } elseif (strlen($password) < 12) {
        $err = wallet_te('wallet.error.reset_password_min', [], 'New password must be at least 12 characters.');
    } else {
        try {
            $result = account_complete_recovery_step($requestId, $code, $totpCode, $password);
            if ($result === 'complete') {
                unset($_SESSION['pending_password_reset_id']);
                $ok = wallet_te('wallet.page.reset_password.success', [], 'Password reset complete. You can log in now.');
                $complete = true;
            } elseif ($result === 'sms_sent') {
                $ok = wallet_te('wallet.page.reset_password.success_sms', [], 'Email verified. We sent an SMS recovery code for the next security step.');
                $stmt->execute([$requestId]);
                $request = $stmt->fetch();
            } elseif ($result === 'totp_needed') {
                $err = wallet_te('wallet.error.reset_totp_required', [], 'Enter your authenticator code to complete password recovery.');
                $stmt->execute([$requestId]);
                $request = $stmt->fetch();
            } else {
                $err = wallet_te('wallet.error.reset_invalid', [], 'Invalid or expired recovery details.');
            }
        } catch (Throwable $e) {
            wallet_log_error('password reset failed: ' . $e->getMessage());
            $err = wallet_te('wallet.error.reset_failed', [], 'Password reset could not be completed.');
        }
    }
}

render_header('wallet.page.reset_password.title');
$recoveryStep = 'email';
if ((string)($request['email_verified_at'] ?? '') !== '') {
    $recoveryStep = (account_recovery_requires_sms($request) && (string)($request['sms_verified_at'] ?? '') === '') ? 'sms' : 'password';
}
if ($recoveryStep === 'password' && (int)$request['totp_required'] === 1 && (string)($request['totp_verified_at'] ?? '') === '') {
    $recoveryStep = 'authenticator';
}
?>
<div class="card">
  <h3><?= h(wallet_te('wallet.page.reset_password.title', [], 'Reset Password')) ?></h3>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <?php if ($complete): ?>
    <p class="ok"><?= h($ok) ?></p>
    <div class="actions"><a class="button primary" href="<?= h(wallet_url('/login.php')) ?>"><?= h(wallet_te('wallet.page.reset_password.login', [], 'Login')) ?></a></div>
  <?php else: ?>
    <?php if ($ok): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
    <?php if ($recoveryStep === 'email'): ?>
      <p><?= h(wallet_te('wallet.page.reset_password.step_email', [], 'Step 1: enter the 6-digit recovery code sent to your email.')) ?></p>
    <?php elseif ($recoveryStep === 'sms'): ?>
      <p><?= h(wallet_te('wallet.page.reset_password.step_sms', [], 'Step 2: enter the 6-digit recovery code sent by SMS.')) ?></p>
    <?php elseif ($recoveryStep === 'authenticator'): ?>
      <p><?= h(wallet_te('wallet.page.reset_password.step_auth', [], 'Step 3: this account chose to enable authenticator security. Enter a current authenticator code to complete recovery.')) ?></p>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <?php if ($recoveryStep === 'email' || $recoveryStep === 'sms'): ?>
        <label><?= h(wallet_te('wallet.page.reset_password.label_code', [], 'Recovery Code')) ?><br><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus></label><br><br>
      <?php endif; ?>
      <?php if ($recoveryStep === 'authenticator'): ?>
        <label><?= h(wallet_te('wallet.page.reset_password.label_auth', [], 'Authenticator Code')) ?><br><input name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label><br><br>
      <?php endif; ?>
      <label><?= h(wallet_te('wallet.page.reset_password.label_password', [], 'New Password')) ?><br><input type="password" name="password" required minlength="12" autocomplete="new-password"></label><br><br>
      <label><?= h(wallet_te('wallet.page.reset_password.confirm_password', [], 'Confirm New Password')) ?><br><input type="password" name="password_confirm" required minlength="12" autocomplete="new-password"></label><br><br>
      <button type="submit"><?= h(wallet_te('wallet.page.reset_password.submit', [], 'Reset Password')) ?></button>
    </form>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
