<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/sms.php';
require_once __DIR__ . '/../app/view.php';
require_once __DIR__ . '/../app/security_log.php';

wallet_start_session();

$err = '';
$ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $username = trim((string)($_POST['username'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = sms_normalize_phone((string)($_POST['phone_number'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $phoneRequired = sms_setting_enabled('wallet_sms_registration_required');

    if (!preg_match('/^[A-Za-z0-9_]{3,40}$/', $username)) {
        $err = 'Username must be 3-40 chars using letters, numbers, underscore.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Invalid email.';
    } elseif ($phoneRequired && $phone === '') {
        $err = 'Phone number is required for SMS account security.';
    } elseif ($phone !== '' && !preg_match('/^\+\d{10,15}$/', $phone)) {
        $err = 'Phone number must include country code, for example +15551234567.';
    } elseif (strlen($password) < 12) {
        $err = 'Password must be at least 12 characters.';
    } else {
        try {
            $algo = wallet_config()['security']['password_algo'] ?? PASSWORD_ARGON2ID;
            $opts = wallet_config()['security']['password_options'] ?? [];
            $hash = password_hash($password, $algo, $opts);
            $pdo = wallet_db();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO users (username, email, phone_number, sms_2fa_enabled, password_hash) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $email, $phone !== '' ? $phone : null, $phone !== '' ? 1 : 0, $hash]);
            $userId = (int)$pdo->lastInsertId();
            $sec = $pdo->prepare("INSERT INTO user_security (user_id) VALUES (?)");
            $sec->execute([$userId]);
            $pdo->commit();
            security_log_event($userId, 'register_success', 'info');
            $ok = 'Registration complete. You can log in now.';
        } catch (Throwable $e) {
            if (wallet_db()->inTransaction()) {
                wallet_db()->rollBack();
            }
            wallet_log_error('register failed: ' . $e->getMessage());
            $err = 'Registration failed (username/email may already exist).';
        }
    }
}

render_header('Register');
?>
<div class="card">
  <h3>Create account</h3>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <label>Username<br><input name="username" required maxlength="40"></label><br><br>
    <label>Email<br><input type="email" name="email" required maxlength="320"></label><br><br>
    <label>Phone Number<br><input name="phone_number" maxlength="32" placeholder="+15551234567" <?= sms_setting_enabled('wallet_sms_registration_required') ? 'required' : '' ?>></label>
    <p><small>Phone numbers are used for account security and SMS verification when enabled. Message and data rates may apply.</small></p>
    <label>Password<br><input type="password" name="password" required minlength="12"></label><br><br>
    <button type="submit">Register</button>
  </form>
</div>
<?php render_footer(); ?>
