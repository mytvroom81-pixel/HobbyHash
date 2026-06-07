<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/totp.php';
require_once __DIR__ . '/../app/security_log.php';
require_once __DIR__ . '/../app/view.php';

$user = auth_require_user();
totp_ensure_schema();
$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'change_password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');

        $stmt = wallet_db()->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([(int)$user['id']]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($current, $row['password_hash'])) {
            $err = 'Current password is incorrect.';
        } elseif (strlen($new) < 12) {
            $err = 'New password must be at least 12 characters.';
        } else {
            $algo = wallet_config()['security']['password_algo'] ?? PASSWORD_ARGON2ID;
            $opts = wallet_config()['security']['password_options'] ?? [];
            $hash = password_hash($new, $algo, $opts);
            $u = wallet_db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $u->execute([$hash, (int)$user['id']]);
            security_log_event((int)$user['id'], 'password_changed', 'info');
            $ok = 'Password updated.';
        }
    } elseif ($action === 'generate_totp') {
        $secret = totp_generate_secret();
        $u = wallet_db()->prepare("UPDATE user_security SET twofa_secret_encrypted = ?, twofa_enabled = 0 WHERE user_id = ?");
        $u->execute([$secret, (int)$user['id']]);
        security_log_event((int)$user['id'], 'wallet_totp_secret_generated', 'info');
        $ok = 'Authenticator secret generated. Scan the QR code and confirm with a code.';
    } elseif ($action === 'confirm_totp') {
        $code = (string)($_POST['code'] ?? '');
        $secret = totp_user_secret((int)$user['id']);
        if ($secret === '') {
            $err = 'Generate an authenticator secret first.';
        } elseif (!totp_verify($secret, $code)) {
            $err = 'Invalid authenticator code.';
        } else {
            $u = wallet_db()->prepare("UPDATE user_security SET twofa_enabled = 1 WHERE user_id = ?");
            $u->execute([(int)$user['id']]);
            security_log_event((int)$user['id'], 'wallet_totp_enabled', 'info');
            $ok = 'Authenticator app enabled.';
        }
    } elseif ($action === 'disable_totp') {
        $u = wallet_db()->prepare("UPDATE user_security SET twofa_enabled = 0, twofa_secret_encrypted = NULL WHERE user_id = ?");
        $u->execute([(int)$user['id']]);
        security_log_event((int)$user['id'], 'wallet_totp_disabled', 'info');
        $ok = 'Authenticator app disabled.';
    }
}

$sec = wallet_db()->prepare("SELECT twofa_enabled, twofa_secret_encrypted, last_login_at, last_login_ip FROM user_security WHERE user_id = ?");
$sec->execute([(int)$user['id']]);
$secRow = $sec->fetch() ?: ['twofa_enabled' => 0, 'twofa_secret_encrypted' => '', 'last_login_at' => null, 'last_login_ip' => null];
$secret = trim((string)($secRow['twofa_secret_encrypted'] ?? ''));
$qrSvg = '';
if ($secret !== '') {
    try {
        $qrSvg = totp_qr_svg(totp_otpauth_uri('HOBC Wallet', (string)$user['email'], $secret));
    } catch (Throwable $e) {
        wallet_log_error('wallet TOTP QR failed: ' . $e->getMessage());
    }
}

$eventPage = max(1, (int)($_GET['event_page'] ?? 1));
$eventLimit = 10;
$eventOffset = ($eventPage - 1) * $eventLimit;
$eventTotalStmt = wallet_db()->prepare("SELECT COUNT(*) FROM security_event_log WHERE user_id = ?");
$eventTotalStmt->execute([(int)$user['id']]);
$eventTotal = (int)$eventTotalStmt->fetchColumn();
$eventTotalPages = max(1, (int)ceil($eventTotal / $eventLimit));
$eventPage = min($eventPage, $eventTotalPages);
$eventOffset = ($eventPage - 1) * $eventLimit;
$events = wallet_db()->prepare(
    "SELECT event_type, severity, details_json, ip_address, created_at
     FROM security_event_log
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT {$eventLimit} OFFSET {$eventOffset}"
);
$events->execute([(int)$user['id']]);

function wallet_security_event_details(?string $json): string
{
    $json = trim((string)$json);
    if ($json === '' || $json === '[]' || $json === '{}') {
        return '-';
    }
    $decoded = json_decode($json, true);
    if (is_array($decoded) && $decoded === []) {
        return '-';
    }
    if (is_array($decoded)) {
        return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: $json;
    }
    return $json;
}

render_header('Security Settings');
?>
<div class="card">
  <h3>Security Settings</h3>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
  <p>Authenticator app enabled: <b><?= ((int)$secRow['twofa_enabled'] === 1) ? 'yes' : 'no' ?></b></p>
  <p>Last login: <?= h((string)($secRow['last_login_at'] ?? 'n/a')) ?> from <?= h((string)($secRow['last_login_ip'] ?? 'n/a')) ?></p>
</div>

<div class="card">
  <h4>Change Password</h4>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="change_password">
    <label>Current Password<br><input type="password" name="current_password" required></label><br><br>
    <label>New Password<br><input type="password" name="new_password" minlength="12" required></label><br><br>
    <button type="submit">Update Password</button>
  </form>
</div>

<div class="card">
  <h4>Authenticator App</h4>
  <p>Use Google Authenticator, Authy, 1Password, Microsoft Authenticator, or another TOTP app.</p>
  <div class="qr-actions">
    <?php if ((int)$secRow['twofa_enabled'] !== 1): ?>
      <button type="button" class="small-button" data-open-modal="wallet-totp-setup-modal"><?= $secret !== '' ? 'Continue Setup' : 'Set Up Authenticator' ?></button>
    <?php else: ?>
      <button type="button" class="small-button" data-open-modal="wallet-totp-disable-modal">Disable Authenticator</button>
    <?php endif; ?>
  </div>
</div>

<div class="modal-backdrop <?= ($secret !== '' && (int)$secRow['twofa_enabled'] !== 1) ? 'is-open' : '' ?>" id="wallet-totp-setup-modal" aria-hidden="<?= ($secret !== '' && (int)$secRow['twofa_enabled'] !== 1) ? 'false' : 'true' ?>">
  <div class="modal-card authenticator-modal" role="dialog" aria-modal="true" aria-labelledby="wallet-totp-setup-title">
    <div class="modal-header">
      <div><span class="eyebrow">Authenticator</span><h3 id="wallet-totp-setup-title">Set Up Authenticator App</h3></div>
      <button type="button" class="small-button modal-close" data-close-modal>Close</button>
    </div>
    <?php if ($secret === ''): ?>
      <p>Start by generating a setup QR code. Then scan it with your authenticator app and confirm the first 6-digit code.</p>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="generate_totp">
        <button type="submit">Generate Setup QR</button>
      </form>
    <?php else: ?>
      <p>Scan this QR code, then enter the 6-digit code from your authenticator app.</p>
      <?php if ($qrSvg !== ''): ?><div class="qr-code-wrap"><?= $qrSvg ?></div><?php endif; ?>
      <p>Manual setup key: <code><?= h($secret) ?></code></p>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="confirm_totp">
        <label>Authenticator Code<br><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label><br><br>
        <button type="submit">Confirm And Enable</button>
      </form>
      <form method="post" style="margin-top:12px">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="generate_totp">
        <button type="submit">Regenerate QR</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ((int)$secRow['twofa_enabled'] === 1): ?>
<div class="modal-backdrop" id="wallet-totp-disable-modal" aria-hidden="true">
  <div class="modal-card authenticator-modal" role="dialog" aria-modal="true" aria-labelledby="wallet-totp-disable-title">
    <div class="modal-header">
      <div><span class="eyebrow">Authenticator</span><h3 id="wallet-totp-disable-title">Disable Authenticator</h3></div>
      <button type="button" class="small-button modal-close" data-close-modal>Close</button>
    </div>
    <p>Only disable this if you are sure you do not want authenticator app protection on this wallet account.</p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="disable_totp">
      <button type="submit">Disable Authenticator</button>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
(() => {
  const closeModal = (modal) => {
    modal?.classList.remove('is-open');
    modal?.setAttribute('aria-hidden', 'true');
  };
  document.querySelectorAll('[data-open-modal]').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = document.getElementById(button.dataset.openModal || '');
      modal?.classList.add('is-open');
      modal?.setAttribute('aria-hidden', 'false');
    });
  });
  document.querySelectorAll('[data-close-modal]').forEach((button) => {
    button.addEventListener('click', () => closeModal(button.closest('.modal-backdrop')));
  });
  document.querySelectorAll('.modal-backdrop').forEach((modal) => {
    modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(modal); });
  });
})();
</script>

<div class="card">
  <h4>Security Events</h4>
  <p>Showing page <?= h((string)$eventPage) ?> of <?= h((string)$eventTotalPages) ?>.</p>
  <table>
    <tr><th>Time</th><th>Event</th><th>Severity</th><th>IP</th><th>Details</th></tr>
    <?php foreach ($events as $row): ?>
      <tr>
        <td><?= h($row['created_at']) ?></td>
        <td><?= h($row['event_type']) ?></td>
        <td><?= h($row['severity']) ?></td>
        <td><?= h((string)($row['ip_address'] ?? '-')) ?></td>
        <td><pre><?= h(wallet_security_event_details((string)$row['details_json'])) ?></pre></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($eventTotal === 0): ?>
      <tr><td colspan="5">No security events yet.</td></tr>
    <?php endif; ?>
  </table>
  <div class="actions">
    <?php if ($eventPage > 1): ?><a class="button" href="<?= h(wallet_url('/security.php?event_page=' . ($eventPage - 1))) ?>">Previous 10</a><?php endif; ?>
    <?php if ($eventPage < $eventTotalPages): ?><a class="button" href="<?= h(wallet_url('/security.php?event_page=' . ($eventPage + 1))) ?>">Next 10</a><?php endif; ?>
  </div>
</div>
<?php render_footer(); ?>
