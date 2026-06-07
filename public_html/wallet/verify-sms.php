<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/sms.php';
require_once __DIR__ . '/../app/totp.php';
require_once __DIR__ . '/../app/security_log.php';
require_once __DIR__ . '/../app/view.php';

wallet_start_session();
if (auth_current_user()) {
    wallet_redirect(wallet_url('/dashboard.php'));
}

$pendingUserId = (int)($_SESSION['pending_user_2fa_user_id'] ?? 0);
$challengeId = (int)($_SESSION['pending_user_2fa_challenge_id'] ?? 0);
if ($pendingUserId <= 0 || $challengeId <= 0) {
    wallet_redirect(wallet_url('/login.php'));
}

$stmt = wallet_db()->prepare("SELECT id, username, email, phone_number, phone_verified_at, sms_2fa_enabled, is_active FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$pendingUserId]);
$user = $stmt->fetch();
if (!$user || !(bool)$user['is_active']) {
    unset($_SESSION['pending_user_2fa_user_id'], $_SESSION['pending_user_2fa_challenge_id']);
    wallet_redirect(wallet_url('/login.php'));
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $code = trim((string)($_POST['code'] ?? ''));
    try {
        if (sms_verify_challenge($challengeId, 'user', $pendingUserId, 'wallet_login', $code)) {
            security_log_event($pendingUserId, 'wallet_sms_login_verified', 'info');
            unset($_SESSION['pending_user_2fa_user_id'], $_SESSION['pending_user_2fa_challenge_id']);
            if (totp_user_requires_login($user)) {
                $_SESSION['pending_user_totp_user_id'] = $pendingUserId;
                wallet_redirect(wallet_url('/verify-authenticator.php'));
            }
            auth_login_user($user);
            wallet_redirect(wallet_url('/dashboard.php'));
        }
        security_log_event($pendingUserId, 'wallet_sms_login_failed_verify', 'warning');
        $err = wallet_te('wallet.error.verify_sms_code', [], 'Invalid or expired verification code.');
    } catch (Throwable $e) {
        wallet_log_error('wallet SMS login verification failed: ' . $e->getMessage());
        $err = wallet_te('wallet.error.verify_sms', [], 'SMS verification could not be completed. Please try again.');
    }
}

render_header('wallet.page.verify_sms.title');
?>
<div class="card">
  <h3><?= h(wallet_te('wallet.page.verify_sms.title', [], 'SMS Verification')) ?></h3>
  <p><?= h(wallet_te('wallet.page.verify_sms.intro', [], 'Enter the 6-digit code sent to your phone. The code expires in 10 minutes.')) ?></p>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <label><?= h(wallet_te('wallet.page.verify_sms.label_code', [], 'Verification Code')) ?><br><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus></label><br><br>
    <button type="submit"><?= h(wallet_te('wallet.page.verify_sms.submit', [], 'Verify and Login')) ?></button>
  </form>
</div>
<?php render_footer(); ?>
