<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/sms.php';
require_once __DIR__ . '/../app/totp.php';
require_once __DIR__ . '/../app/view.php';

wallet_start_session();
if (auth_current_user()) {
    wallet_redirect(wallet_url('/dashboard.php'));
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $login = trim((string)($_POST['wallet_login'] ?? $_POST['login'] ?? ''));
    $password = (string)($_POST['wallet_password'] ?? $_POST['password'] ?? '');
    $user = auth_verify_login_password($login, $password);
    if (!$user) {
        $err = 'Invalid login or temporarily throttled.';
    } elseif (sms_user_requires_login_2fa($user)) {
        try {
            $challenge = sms_create_challenge('user', (int)$user['id'], 'wallet_login', (string)$user['phone_number']);
            sms_send_code((int)$challenge['id'], (string)$challenge['phone_number'], (string)$challenge['code'], 'wallet login');
            $_SESSION['pending_user_2fa_user_id'] = (int)$user['id'];
            $_SESSION['pending_user_2fa_challenge_id'] = (int)$challenge['id'];
            security_log_event((int)$user['id'], 'wallet_sms_login_code_sent', 'info');
            wallet_redirect(wallet_url('/verify-sms.php'));
        } catch (Throwable $e) {
            wallet_log_error('wallet SMS login code send failed: ' . $e->getMessage());
            $err = 'Password accepted, but SMS verification could not be sent. Please try again.';
        }
    } elseif (totp_user_requires_login($user)) {
        $_SESSION['pending_user_totp_user_id'] = (int)$user['id'];
        wallet_redirect(wallet_url('/verify-authenticator.php'));
    } else {
        auth_login_user($user);
        wallet_redirect(wallet_url('/dashboard.php'));
    }
}

render_header('Login');
?>
<div class="card">
  <h3>Login</h3>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <label>Email or Username<br><input name="wallet_login" required autocomplete="section-wallet username"></label><br><br>
    <label>Password<br><input type="password" name="wallet_password" required autocomplete="section-wallet current-password"></label><br><br>
    <button type="submit">Login</button>
  </form>
</div>
<?php render_footer(); ?>
