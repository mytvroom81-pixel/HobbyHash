<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/totp.php';
require_once __DIR__ . '/../app/admin_view.php';

$admin = admin_require_user();
totp_ensure_schema();
$pdo = wallet_db();
$msg = '';
$err = '';

$stmt = $pdo->prepare("SELECT id, username, email, totp_secret, totp_enabled FROM admin_users WHERE id = ? LIMIT 1");
$stmt->execute([(int)$admin['id']]);
$adminRow = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'generate') {
        $secret = totp_generate_secret();
        $stmt = $pdo->prepare("UPDATE admin_users SET totp_secret = ?, totp_enabled = 0 WHERE id = ?");
        $stmt->execute([$secret, (int)$admin['id']]);
        admin_audit((int)$admin['id'], 'admin_totp_secret_generated', 'admin_user', (string)$admin['id']);
        $msg = 'Authenticator secret generated. Scan the QR code and confirm with a code.';
    } elseif ($action === 'confirm') {
        $code = (string)($_POST['code'] ?? '');
        $secret = trim((string)($adminRow['totp_secret'] ?? ''));
        if ($secret === '') {
            $err = 'Generate an authenticator secret first.';
        } elseif (!totp_verify($secret, $code)) {
            $err = 'Invalid authenticator code.';
        } else {
            $stmt = $pdo->prepare("UPDATE admin_users SET totp_enabled = 1 WHERE id = ?");
            $stmt->execute([(int)$admin['id']]);
            admin_audit((int)$admin['id'], 'admin_totp_enabled', 'admin_user', (string)$admin['id']);
            $msg = 'Authenticator app enabled for admin.';
        }
    } elseif ($action === 'disable') {
        $stmt = $pdo->prepare("UPDATE admin_users SET totp_enabled = 0, totp_secret = NULL WHERE id = ?");
        $stmt->execute([(int)$admin['id']]);
        admin_audit((int)$admin['id'], 'admin_totp_disabled', 'admin_user', (string)$admin['id']);
        $msg = 'Authenticator app disabled for admin.';
    }
    $stmt = $pdo->prepare("SELECT id, username, email, totp_secret, totp_enabled FROM admin_users WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$admin['id']]);
    $adminRow = $stmt->fetch();
}

$secret = trim((string)($adminRow['totp_secret'] ?? ''));
$qrSvg = '';
if ($secret !== '') {
    try {
        $qrSvg = totp_qr_svg(totp_otpauth_uri('HOBC Admin', (string)$adminRow['email'], $secret));
    } catch (Throwable $e) {
        wallet_log_error('admin TOTP QR failed: ' . $e->getMessage());
    }
}

render_admin_header('Admin Authenticator');
?>
<?php if ($msg): ?><div class="card"><p class="ok"><?= h($msg) ?></p></div><?php endif; ?>
<?php if ($err): ?><div class="card"><p class="err"><?= h($err) ?></p></div><?php endif; ?>

<div class="card">
  <h3>Admin Authenticator App</h3>
  <p>Status: <b><?= ((int)($adminRow['totp_enabled'] ?? 0) === 1) ? 'Enabled' : 'Disabled' ?></b></p>
  <p>Use Google Authenticator, Authy, 1Password, Microsoft Authenticator, or any TOTP-compatible app.</p>
  <div class="admin-actions">
    <?php if ((int)($adminRow['totp_enabled'] ?? 0) !== 1): ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="generate">
        <button type="submit">Set Up Authenticator</button>
      </form>
    <?php else: ?>
      <button type="button" data-open-modal="admin-totp-disable-modal">Disable Authenticator</button>
    <?php endif; ?>
  </div>
</div>

<div class="modal-backdrop <?= ($action ?? '') === 'generate' && $secret !== '' && (int)($adminRow['totp_enabled'] ?? 0) !== 1 ? 'is-open' : '' ?>" id="admin-totp-setup-modal" aria-hidden="<?= ($action ?? '') === 'generate' && $secret !== '' && (int)($adminRow['totp_enabled'] ?? 0) !== 1 ? 'false' : 'true' ?>">
  <div class="modal-card authenticator-modal" role="dialog" aria-modal="true" aria-labelledby="admin-totp-setup-title">
    <div class="modal-header">
      <div><span class="module-status">Authenticator</span><h3 id="admin-totp-setup-title">Set Up Admin Authenticator</h3></div>
      <button type="button" data-close-modal>Close</button>
    </div>
    <?php if ($secret !== ''): ?>
      <p>Scan this QR code, then enter the 6-digit code from your authenticator app.</p>
      <?php if ($qrSvg !== ''): ?><div class="qr-code-wrap"><?= $qrSvg ?></div><?php endif; ?>
      <p>Manual setup key: <code><?= h($secret) ?></code></p>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="confirm">
        <label>Authenticator Code<br><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label><br><br>
        <button type="submit">Confirm And Enable</button>
      </form>
      <form method="post" style="margin-top:12px">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="generate">
        <button type="submit">Regenerate QR</button>
      </form>
    <?php else: ?>
      <p>Close this window and choose Set Up Authenticator to generate a fresh setup QR code.</p>
    <?php endif; ?>
  </div>
</div>

<?php if ((int)($adminRow['totp_enabled'] ?? 0) === 1): ?>
<div class="modal-backdrop" id="admin-totp-disable-modal" aria-hidden="true">
  <div class="modal-card authenticator-modal" role="dialog" aria-modal="true" aria-labelledby="admin-totp-disable-title">
    <div class="modal-header">
      <div><span class="module-status">Authenticator</span><h3 id="admin-totp-disable-title">Disable Admin Authenticator</h3></div>
      <button type="button" data-close-modal>Close</button>
    </div>
    <p>Only disable this if you are sure you do not want authenticator app protection on this admin account.</p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="disable">
      <button type="submit">Disable Admin Authenticator</button>
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
<?php render_admin_footer(); ?>
