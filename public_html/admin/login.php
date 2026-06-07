<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/sms.php';
require_once __DIR__ . '/../app/totp.php';
require_once __DIR__ . '/../app/admin_view.php';
require_once __DIR__ . '/../app/security_log.php';
require_once __DIR__ . '/../app/analytics.php';

wallet_start_session();
if (admin_current_user()) {
    wallet_redirect(admin_url('/index.php'));
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $login = trim((string)($_POST['admin_login'] ?? $_POST['login'] ?? ''));
    $password = (string)($_POST['admin_password'] ?? $_POST['password'] ?? '');
    recordSecurityEvent('admin_login_attempt', null, $login);
    $admin = admin_verify_login_password($login, $password);
    if (!$admin) {
        recordSecurityEvent('admin_login_failed', null, $login);
        admin_audit(null, 'admin_login_failed', 'admin_user', null, ['login' => substr($login, 0, 120)]);
        $err = 'Invalid credentials or login temporarily throttled.';
    } elseif (sms_admin_requires_2fa($admin)) {
        recordSecurityEvent('admin_login_password_ok_sms_required', (int)$admin['id'], $login);
        try {
            $challenge = sms_create_challenge('admin', (int)$admin['id'], 'admin_login', (string)$admin['phone_number']);
            sms_send_code((int)$challenge['id'], (string)$challenge['phone_number'], (string)$challenge['code'], 'admin login');
            $_SESSION['pending_admin_2fa_admin_id'] = (int)$admin['id'];
            $_SESSION['pending_admin_2fa_challenge_id'] = (int)$challenge['id'];
            admin_audit((int)$admin['id'], 'admin_sms_code_sent', 'admin_user', (string)$admin['id']);
            wallet_redirect(admin_url('/verify-sms.php'));
        } catch (Throwable $e) {
            wallet_log_error('admin SMS code send failed: ' . $e->getMessage());
            admin_audit((int)$admin['id'], 'admin_sms_code_failed', 'admin_user', (string)$admin['id']);
            $err = 'Password accepted, but SMS verification could not be sent. Please try again.';
        }
    } elseif (totp_admin_requires($admin)) {
        recordSecurityEvent('admin_login_password_ok_totp_required', (int)$admin['id'], $login);
        $_SESSION['pending_admin_totp_admin_id'] = (int)$admin['id'];
        wallet_redirect(admin_url('/verify-authenticator.php'));
    } else {
        recordSecurityEvent('admin_login_success', (int)$admin['id'], $login);
        admin_complete_login($admin);
        wallet_redirect(admin_url('/index.php'));
    }
}

admin_security_headers();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>HOBC Admin Login</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <?php require __DIR__ . '/../includes/icon-meta.php'; ?>
  <style>
    :root{--bg:#050708;--panel:#101417;--gold:#f6b928;--gold-2:#ffd764;--text:#f7f5ed;--muted:#b9b5a6;--line:rgba(246,185,40,.32);--bad:#ff7a7a;--shadow:0 18px 48px rgba(0,0,0,.45)}
    *{box-sizing:border-box}
    body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at top left,rgba(246,185,40,.16),transparent 28rem),#050708;color:var(--text);margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:18px}
    .box{background:linear-gradient(180deg,rgba(255,255,255,.055),rgba(255,255,255,.02));border:1px solid rgba(246,185,40,.22);border-radius:18px;padding:24px;max-width:420px;width:100%;box-shadow:var(--shadow)}
    .brand{display:flex;align-items:center;gap:12px;margin-bottom:18px}.brand img{width:58px;height:58px;object-fit:contain}.brand h2{font-size:24px;letter-spacing:.08em;margin:0;text-transform:uppercase}.brand small{color:var(--muted);display:block;letter-spacing:.12em;text-transform:uppercase;font-size:11px;margin-top:4px}
    p,label{color:var(--muted);line-height:1.4}.err{color:var(--bad);border:1px solid rgba(255,122,122,.35);border-radius:12px;background:rgba(255,122,122,.08);padding:10px 12px}form{display:grid;gap:10px}form>br{display:none}label{display:grid;gap:5px}
    input{width:100%;padding:11px 12px;box-sizing:border-box;border:1px solid rgba(246,185,40,.25);border-radius:12px;background:#090d10;color:var(--text);margin-top:5px}button{border:1px solid #ffd45d;border-radius:999px;color:#110d05;padding:11px 14px;width:100%;background:linear-gradient(180deg,#ffd45d,#b77906);font-weight:900;cursor:pointer}button:hover{filter:brightness(1.08)}a{color:var(--gold-2);text-decoration:none}.legal{display:flex;gap:12px;justify-content:center;margin-top:18px}
    input:-webkit-autofill,input:-webkit-autofill:hover,input:-webkit-autofill:focus{-webkit-box-shadow:0 0 0 1000px #090d10 inset;-webkit-text-fill-color:var(--text);caret-color:var(--text);transition:background-color 9999s ease-in-out 0s}
  </style>
</head>
<body>
  <div class="box">
    <div class="brand"><img src="/assets/images/logo-round.png" alt="HOBC logo"><div><h2>HOBC Admin</h2><small>Master control center</small></div></div>
    <p>Restricted control center for site, wallet, pool, node, and explorer operations.</p>
    <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <label>Email or Username<br><input name="admin_login" required autocomplete="section-admin username"></label><br><br>
      <label>Password<br><input type="password" name="admin_password" required autocomplete="section-admin current-password"></label><br><br>
      <button type="submit">Sign In</button>
    </form>
    <div class="legal"><a href="/privacy/">Privacy Policy</a><a href="/terms/">Terms</a></div>
  </div>
</body>
</html>
