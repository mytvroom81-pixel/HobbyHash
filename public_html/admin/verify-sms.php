<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/sms.php';
require_once __DIR__ . '/../app/totp.php';
require_once __DIR__ . '/../app/admin_view.php';

wallet_start_session();
if (admin_current_user()) {
    wallet_redirect(admin_url('/index.php'));
}

$pendingAdminId = (int)($_SESSION['pending_admin_2fa_admin_id'] ?? 0);
$challengeId = (int)($_SESSION['pending_admin_2fa_challenge_id'] ?? 0);
if ($pendingAdminId <= 0 || $challengeId <= 0) {
    wallet_redirect(admin_url('/login.php'));
}

$stmt = wallet_db()->prepare("SELECT id, username, email, phone_number, sms_2fa_enabled, is_active FROM admin_users WHERE id = ? LIMIT 1");
$stmt->execute([$pendingAdminId]);
$admin = $stmt->fetch();
if (!$admin || !(bool)$admin['is_active']) {
    unset($_SESSION['pending_admin_2fa_admin_id'], $_SESSION['pending_admin_2fa_challenge_id']);
    wallet_redirect(admin_url('/login.php'));
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $code = trim((string)($_POST['code'] ?? ''));
    try {
        if (sms_verify_challenge($challengeId, 'admin', $pendingAdminId, 'admin_login', $code)) {
            admin_audit($pendingAdminId, 'admin_sms_code_verified', 'admin_user', (string)$pendingAdminId);
            unset($_SESSION['pending_admin_2fa_admin_id'], $_SESSION['pending_admin_2fa_challenge_id']);
            if (totp_admin_requires($admin)) {
                $_SESSION['pending_admin_totp_admin_id'] = $pendingAdminId;
                wallet_redirect(admin_url('/verify-authenticator.php'));
            }
            admin_complete_login($admin);
            wallet_redirect(admin_url('/index.php'));
        }
        admin_audit($pendingAdminId, 'admin_sms_code_failed_verify', 'admin_user', (string)$pendingAdminId);
        $err = 'Invalid or expired verification code.';
    } catch (Throwable $e) {
        wallet_log_error('admin SMS verification failed: ' . $e->getMessage());
        $err = 'SMS verification could not be completed. Please try again.';
    }
}

admin_security_headers();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>HOBC Admin SMS Verification</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <?php require __DIR__ . '/../includes/icon-meta.php'; ?>
  <style>
    :root{--bg:#050708;--panel:#101417;--gold:#f6b928;--gold-2:#ffd764;--text:#f7f5ed;--muted:#b9b5a6;--line:rgba(246,185,40,.32);--bad:#ff7a7a;--shadow:0 18px 48px rgba(0,0,0,.45)}
    *{box-sizing:border-box}
    body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at top left,rgba(246,185,40,.16),transparent 28rem),#050708;color:var(--text);margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:18px}
    .box{background:linear-gradient(180deg,rgba(255,255,255,.055),rgba(255,255,255,.02));border:1px solid rgba(246,185,40,.22);border-radius:18px;padding:24px;max-width:420px;width:100%;box-shadow:var(--shadow)}
    .brand{display:flex;align-items:center;gap:12px;margin-bottom:18px}.brand img{width:58px;height:58px;object-fit:contain}.brand h2{font-size:24px;letter-spacing:.08em;margin:0;text-transform:uppercase}.brand small{color:var(--muted);display:block;letter-spacing:.12em;text-transform:uppercase;font-size:11px;margin-top:4px}
    p,label{color:var(--muted);line-height:1.55}.err{color:var(--bad);border:1px solid rgba(255,122,122,.35);border-radius:12px;background:rgba(255,122,122,.08);padding:10px 12px}
    input{width:100%;padding:11px 12px;box-sizing:border-box;border:1px solid rgba(246,185,40,.25);border-radius:12px;background:#090d10;color:var(--text);font-size:22px;letter-spacing:.18em;margin-top:5px;text-align:center}button{border:1px solid #ffd45d;border-radius:999px;color:#110d05;padding:11px 14px;width:100%;background:linear-gradient(180deg,#ffd45d,#b77906);font-weight:900;cursor:pointer}button:hover{filter:brightness(1.08)}a{color:var(--gold-2);text-decoration:none}.legal{display:flex;gap:12px;justify-content:center;margin-top:18px}
    input:-webkit-autofill,input:-webkit-autofill:hover,input:-webkit-autofill:focus{-webkit-box-shadow:0 0 0 1000px #090d10 inset;-webkit-text-fill-color:var(--text);caret-color:var(--text);transition:background-color 9999s ease-in-out 0s}
  </style>
</head>
<body>
  <div class="box">
    <div class="brand"><img src="/assets/images/logo-round.png" alt="HOBC logo"><div><h2>SMS Verify</h2><small>Master admin login</small></div></div>
    <p>Enter the 6-digit code sent to the master admin phone number. The code expires in 10 minutes.</p>
    <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <label>Verification Code<br><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus></label><br><br>
      <button type="submit">Verify and Sign In</button>
    </form>
    <p><a href="<?= h(admin_url('/login.php')) ?>">Return to login</a></p>
    <div class="legal"><a href="/privacy/">Privacy Policy</a><a href="/terms/">Terms</a></div>
  </div>
</body>
</html>
