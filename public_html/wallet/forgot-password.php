<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/account_security.php';
require_once __DIR__ . '/../app/view.php';

wallet_start_session();
account_security_ensure_schema();

$err = '';
$ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $login = strtolower(trim((string)($_POST['login'] ?? '')));
    if ($login === '') {
        $err = wallet_te('wallet.error.forgot_login_required', [], 'Enter your email or username.');
    } else {
        $stmt = wallet_db()->prepare("SELECT id, username, email, phone_number, sms_2fa_enabled, is_active FROM users WHERE email = ? OR username = ? LIMIT 1");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();
        if ($user && (bool)$user['is_active']) {
            try {
                $request = account_create_recovery_request($user);
                $_SESSION['pending_password_reset_id'] = (int)$request['id'];
                wallet_redirect(wallet_url('/reset-password.php'));
            } catch (Throwable $e) {
                wallet_log_error('password recovery send failed: ' . $e->getMessage());
            }
        }
        $ok = wallet_te('wallet.page.forgot_password.success', [], 'If that account exists, a recovery code has been sent.');
    }
}

render_header('wallet.page.forgot_password.title');
?>
<div class="card">
  <h3><?= h(wallet_te('wallet.page.forgot_password.title', [], 'Lost Password')) ?></h3>
  <p><?= h(wallet_te('wallet.page.forgot_password.intro', [], 'Enter your wallet email or username. Password recovery verifies email first, then SMS, then authenticator if you enabled it.')) ?></p>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <label><?= h(wallet_te('wallet.page.forgot_password.label_login', [], 'Email or Username')) ?><br><input name="login" required autocomplete="username"></label><br><br>
    <button type="submit"><?= h(wallet_te('wallet.page.forgot_password.send', [], 'Send Recovery Code')) ?></button>
  </form>
  <div class="actions"><a class="button" href="<?= h(wallet_url('/login.php')) ?>"><?= h(wallet_te('wallet.page.forgot_password.back_login', [], 'Back to Login')) ?></a></div>
</div>
<?php render_footer(); ?>
