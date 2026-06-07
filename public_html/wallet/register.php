<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/settings.php';
require_once __DIR__ . '/../app/sms.php';
require_once __DIR__ . '/../app/account_security.php';
require_once __DIR__ . '/../app/view.php';

wallet_start_session();
account_security_ensure_schema();
$registrationOpen = admin_setting_bool('security.registration_enabled', true)
    && admin_setting_bool('security.wallet_signups_enabled', true)
    && admin_setting_bool('wallet.public_enabled', true);

$err = '';
$ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $username = trim((string)($_POST['username'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = sms_normalize_phone((string)($_POST['phone_number'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $phoneRequired = sms_is_enabled();

    if (!$registrationOpen) {
        $err = wallet_te('wallet.error.register_disabled', [], 'Public wallet signups are temporarily disabled.');
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,40}$/', $username)) {
        $err = wallet_te('wallet.error.register_username', [], 'Username must be 3-40 chars using letters, numbers, underscore.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = wallet_te('wallet.error.register_email', [], 'Invalid email.');
    } elseif ($phoneRequired && $phone === '') {
        $err = wallet_te('wallet.error.register_phone_required', [], 'Phone number is required for SMS account security.');
    } elseif ($phone !== '' && !preg_match('/^\+\d{10,15}$/', $phone)) {
        $err = wallet_te('wallet.error.register_phone_format', [], 'Phone number must include country code, for example +15551234567.');
    } elseif (strlen($password) < 12) {
        $err = wallet_te('wallet.error.register_password_min', [], 'Password must be at least 12 characters.');
    } else {
        $stmt = wallet_db()->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $err = wallet_te('wallet.error.register_duplicate', [], 'Registration failed (username/email may already exist).');
        } elseif ($phone !== '' && account_phone_in_use($phone)) {
            $err = wallet_te('wallet.error.register_phone_in_use', [], 'That phone number is already attached to an account.');
        } elseif (!account_email_domain_valid($email)) {
            $err = wallet_te('wallet.error.register_domain', [], 'That email domain does not appear to accept mail.');
        } else {
            try {
                $pending = account_create_pending_registration($username, $email, $phone, $password);
                $_SESSION['pending_registration_id'] = (int)$pending['id'];
                wallet_redirect(wallet_url('/verify-registration.php'));
            } catch (Throwable $e) {
                wallet_log_error('register verification send failed: ' . $e->getMessage());
                $err = wallet_te('wallet.error.register_verify_send', [], 'Verification code could not be sent. Please try again.');
            }
        }
    }
}

render_header('wallet.page.register.title');
?>
<div class="card">
  <h3><?= h(wallet_te('wallet.page.register.title', [], 'Create account')) ?></h3>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
  <?php if (!$registrationOpen): ?>
    <p class="notice"><?= h(wallet_te('wallet.page.register.disabled', [], 'Public wallet signups are temporarily disabled. Existing users can still log in.')) ?></p>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <label><?= h(wallet_te('wallet.page.register.label_username', [], 'Username')) ?><br><input name="username" required maxlength="40"></label><br><br>
    <label><?= h(wallet_te('wallet.page.register.label_email', [], 'Email')) ?><br><input type="email" name="email" required maxlength="320" data-email-check="/wallet/check-email.php"></label>
    <p><small data-email-check-message><?= h(wallet_te('wallet.page.register.email_hint', [], 'Email verification is the first account security step.')) ?></small></p>
    <label><?= h(wallet_te('wallet.page.register.label_phone', [], 'Phone Number')) ?><br><input name="phone_number" maxlength="32" placeholder="<?= h(wallet_te('wallet.page.register.phone_placeholder', [], '+15551234567')) ?>" <?= sms_is_enabled() ? 'required' : '' ?>></label>
    <p><small><?= h(wallet_te('wallet.page.register.phone_hint', [], 'After email verification, SMS verification is the next account security step. Message and data rates may apply.')) ?></small></p>
    <label><?= h(wallet_te('wallet.page.register.label_password', [], 'Password')) ?><br><input type="password" name="password" required minlength="12"></label><br><br>
    <button type="submit"><?= h(wallet_te('wallet.page.register.submit', [], 'Register')) ?></button>
  </form>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
