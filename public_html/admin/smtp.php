<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/security_log.php';
require_once __DIR__ . '/../app/admin_view.php';

$admin = admin_require_user();
$pdo = wallet_db();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $enabled = isset($_POST['is_enabled']) ? 1 : 0;
    $host = trim((string)($_POST['host'] ?? ''));
    $port = max(1, min(65535, (int)($_POST['port'] ?? 587)));
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $encryption = (string)($_POST['encryption'] ?? 'tls');
    $fromEmail = trim((string)($_POST['from_email'] ?? ''));
    $fromName = trim((string)($_POST['from_name'] ?? ''));

    if (!in_array($encryption, ['none', 'tls', 'ssl'], true)) {
        $err = 'Invalid encryption setting.';
    } elseif ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $err = 'Invalid from email.';
    } else {
        if ($password !== '') {
            $stmt = $pdo->prepare(
                "UPDATE smtp_settings
                 SET is_enabled = ?, host = ?, port = ?, username = ?, password_enc = ?, encryption = ?, from_email = ?, from_name = ?
                 WHERE id = 1"
            );
            $stmt->execute([$enabled, $host, $port, $username, base64_encode($password), $encryption, $fromEmail, $fromName]);
        } else {
            $stmt = $pdo->prepare(
                "UPDATE smtp_settings
                 SET is_enabled = ?, host = ?, port = ?, username = ?, encryption = ?, from_email = ?, from_name = ?
                 WHERE id = 1"
            );
            $stmt->execute([$enabled, $host, $port, $username, $encryption, $fromEmail, $fromName]);
        }
        admin_audit((int)$admin['id'], 'smtp_settings_updated', 'smtp_settings', '1', ['enabled' => $enabled, 'host' => $host, 'port' => $port]);
        $msg = 'SMTP settings saved.';
    }
}

$settings = $pdo->query("SELECT * FROM smtp_settings WHERE id = 1")->fetch();

render_admin_header('SMTP Settings');
?>
<div class="card">
  <h3>SMTP Settings</h3>
  <?php if ($msg): ?><p class="ok"><?= h($msg) ?></p><?php endif; ?>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <label><input type="checkbox" name="is_enabled" <?= ((int)($settings['is_enabled'] ?? 0) === 1) ? 'checked' : '' ?>> Enable outgoing support email</label><br><br>
    <label>SMTP Host<br><input name="host" value="<?= h((string)($settings['host'] ?? '')) ?>" maxlength="190"></label><br><br>
    <label>Port<br><input type="number" name="port" value="<?= h((string)($settings['port'] ?? 587)) ?>"></label><br><br>
    <label>Encryption<br>
      <select name="encryption">
        <?php foreach (['none', 'tls', 'ssl'] as $enc): ?>
          <option value="<?= h($enc) ?>" <?= (($settings['encryption'] ?? 'tls') === $enc) ? 'selected' : '' ?>><?= h($enc) ?></option>
        <?php endforeach; ?>
      </select>
    </label><br><br>
    <label>Username<br><input name="username" value="<?= h((string)($settings['username'] ?? '')) ?>" maxlength="190"></label><br><br>
    <label>Password<br><input type="password" name="password" placeholder="Leave blank to keep existing password"></label><br><br>
    <label>From Email<br><input type="email" name="from_email" value="<?= h((string)($settings['from_email'] ?? '')) ?>" maxlength="190"></label><br><br>
    <label>From Name<br><input name="from_name" value="<?= h((string)($settings['from_name'] ?? 'HobbyHashCoin Support')) ?>" maxlength="120"></label><br><br>
    <button type="submit">Save SMTP Settings</button>
  </form>
</div>
<?php render_admin_footer(); ?>
