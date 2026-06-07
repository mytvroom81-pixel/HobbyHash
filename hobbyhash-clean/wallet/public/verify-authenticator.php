<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/totp.php';
require_once __DIR__ . '/../app/security_log.php';
require_once __DIR__ . '/../app/view.php';

wallet_start_session();
if (auth_current_user()) {
    wallet_redirect(wallet_url('/dashboard.php'));
}

$pendingUserId = (int)($_SESSION['pending_user_totp_user_id'] ?? 0);
if ($pendingUserId <= 0) {
    wallet_redirect(wallet_url('/login.php'));
}

$stmt = wallet_db()->prepare("SELECT id, username, email, phone_number, phone_verified_at, sms_2fa_enabled, is_active FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$pendingUserId]);
$user = $stmt->fetch();
if (!$user || !(bool)$user['is_active']) {
    unset($_SESSION['pending_user_totp_user_id']);
    wallet_redirect(wallet_url('/login.php'));
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $code = (string)($_POST['code'] ?? '');
    $secret = totp_user_secret($pendingUserId);
    if ($secret !== '' && totp_verify($secret, $code)) {
        security_log_event($pendingUserId, 'wallet_totp_login_verified', 'info');
        auth_login_user($user);
        wallet_redirect(wallet_url('/dashboard.php'));
    }
    security_log_event($pendingUserId, 'wallet_totp_login_failed', 'warning');
    $err = 'Invalid authenticator code.';
}

render_header('Authenticator Verify');
?>
<div class="card">
  <h3>Authenticator Verification</h3>
  <p>Enter the 6-digit code from your authenticator app.</p>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <label>Authenticator Code<br><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus></label><br><br>
    <button type="submit">Verify and Login</button>
  </form>
</div>
<?php render_footer(); ?>
