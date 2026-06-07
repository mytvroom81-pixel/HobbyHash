<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/account_security.php';
require_once __DIR__ . '/../app/view.php';

wallet_start_session();
account_security_ensure_schema();

$pendingId = (int)($_SESSION['pending_registration_id'] ?? ($_GET['rid'] ?? 0));
if ($pendingId <= 0) {
    wallet_redirect(wallet_url('/register.php'));
}

$stmt = wallet_db()->prepare("SELECT email, phone_number, verification_method, email_verified_at, sms_verified_at, expires_at FROM pending_registrations WHERE id = ? AND consumed_at IS NULL LIMIT 1");
$stmt->execute([$pendingId]);
$pending = $stmt->fetch();
if (!$pending) {
    unset($_SESSION['pending_registration_id']);
    wallet_redirect(wallet_url('/register.php'));
}

$err = '';
$ok = '';
$complete = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $code = trim((string)($_POST['code'] ?? ''));
    try {
        $result = account_verify_pending_registration_step($pendingId, $code);
        if ($result === 'complete') {
            unset($_SESSION['pending_registration_id']);
            $ok = wallet_te('wallet.page.verify_registration.complete', [], 'Account verified and created. You can log in now.');
            $complete = true;
        } elseif ($result === 'sms_sent') {
            $ok = wallet_te('wallet.page.verify_registration.success_sms', [], 'Email verified. We sent an SMS code for the next security step.');
            $stmt->execute([$pendingId]);
            $pending = $stmt->fetch();
        } else {
            $err = wallet_te('wallet.error.verify_registration_code', [], 'Invalid or expired verification code.');
        }
    } catch (Throwable $e) {
        wallet_log_error('registration verification failed: ' . $e->getMessage());
        $err = wallet_te('wallet.error.verify_registration', [], 'Registration verification could not be completed.');
    }
}

render_header('wallet.page.verify_registration.title');
$stepLabel = ((string)($pending['email_verified_at'] ?? '') === '') ? 'email' : 'SMS';
$destination = $stepLabel === 'SMS'
    ? wallet_short_text((string)$pending['phone_number'], 5, 4)
    : (string)$pending['email'];
?>
<div class="card">
  <h3><?= h(wallet_te('wallet.page.verify_registration.title', [], 'Verify Registration')) ?></h3>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <?php if ($complete): ?>
    <p class="ok"><?= h($ok) ?></p>
    <div class="actions"><a class="button primary" href="<?= h(wallet_url('/login.php')) ?>"><?= h(wallet_te('wallet.page.verify_registration.login', [], 'Login')) ?></a></div>
  <?php else: ?>
    <?php if ($ok): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
    <p><?= h($stepLabel === 'email'
        ? wallet_te('wallet.page.verify_registration.step_email', [], 'Step 1: enter the 6-digit email verification code.')
        : wallet_te('wallet.page.verify_registration.step_sms', [], 'Step 2: enter the 6-digit SMS verification code.')) ?></p>
    <p><small><?= h(wallet_te('wallet.page.verify_registration.destination', [], 'Destination:')) ?> <?= h($destination) ?></small></p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <label><?= h(wallet_te('wallet.page.verify_registration.label_code', [], 'Verification Code')) ?><br><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus></label><br><br>
      <button type="submit"><?= h(wallet_te('wallet.page.verify_registration.submit', [], 'Verify and Create Account')) ?></button>
    </form>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
