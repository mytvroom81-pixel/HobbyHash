<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/site_status.php';
require_once __DIR__ . '/../app/sms.php';
require_once __DIR__ . '/../app/totp.php';
require_once __DIR__ . '/../app/admin_view.php';

$admin = admin_require_user();
$pdo = wallet_db();
$msg = '';
$err = '';

site_status_ensure_schema();
sms_ensure_schema();
totp_ensure_schema();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_validate_or_fail();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'site_status') {
        $mode = (string)($_POST['site_mode'] ?? 'full_launch');
        if (!in_array($mode, ['pre_launch', 'maintenance', 'full_launch'], true)) {
            $mode = 'full_launch';
        }

        $bypassIp = trim((string)($_POST['bypass_ip'] ?? ''));
        $preLaunchTitle = trim((string)($_POST['pre_launch_title'] ?? ''));
        $preLaunchMessage = trim((string)($_POST['pre_launch_message'] ?? ''));
        $preLaunchEta = trim((string)($_POST['pre_launch_eta'] ?? ''));
        $maintenanceTitle = trim((string)($_POST['maintenance_title'] ?? ''));
        $maintenanceMessage = trim((string)($_POST['maintenance_message'] ?? ''));
        $maintenanceStart = trim((string)($_POST['maintenance_start_at'] ?? ''));
        $maintenanceEnd = trim((string)($_POST['maintenance_end_at'] ?? ''));

        $defaults = site_status_defaults();
        $stmt = $pdo->prepare(
            "UPDATE site_settings
             SET site_mode = ?,
                 bypass_ip = ?,
                 pre_launch_title = ?,
                 pre_launch_message = ?,
                 pre_launch_eta = ?,
                 maintenance_title = ?,
                 maintenance_message = ?,
                 maintenance_start_at = ?,
                 maintenance_end_at = ?
             WHERE id = 1"
        );
        $stmt->execute([
            $mode,
            $bypassIp,
            $preLaunchTitle !== '' ? substr($preLaunchTitle, 0, 190) : $defaults['pre_launch_title'],
            $preLaunchMessage !== '' ? $preLaunchMessage : $defaults['pre_launch_message'],
            substr($preLaunchEta, 0, 190),
            $maintenanceTitle !== '' ? substr($maintenanceTitle, 0, 190) : $defaults['maintenance_title'],
            $maintenanceMessage !== '' ? $maintenanceMessage : $defaults['maintenance_message'],
            substr($maintenanceStart, 0, 190),
            substr($maintenanceEnd, 0, 190),
        ]);
        admin_audit((int)$admin['id'], 'update_site_status', 'site_settings', '1', [
            'site_mode' => $mode,
            'bypass_ip' => $bypassIp,
            'maintenance_start_at' => $maintenanceStart,
            'maintenance_end_at' => $maintenanceEnd,
        ]);
        $msg = 'Site status updated.';
    } elseif ($action === 'sms_settings') {
        $adminSms = isset($_POST['admin_sms_2fa_required']) ? 1 : 0;
        $walletRegistrationSms = isset($_POST['wallet_sms_registration_required']) ? 1 : 0;
        $walletLoginSms = isset($_POST['wallet_sms_login_required']) ? 1 : 0;
        $walletWithdrawalSms = isset($_POST['wallet_sms_withdrawal_required']) ? 1 : 0;

        $stmt = $pdo->prepare(
            "UPDATE wallet_settings
             SET admin_sms_2fa_required = ?,
                 wallet_sms_registration_required = ?,
                 wallet_sms_login_required = ?,
                 wallet_sms_withdrawal_required = ?
             WHERE id = 1"
        );
        $stmt->execute([$adminSms, $walletRegistrationSms, $walletLoginSms, $walletWithdrawalSms]);

        $adminFlag = $pdo->prepare("UPDATE admin_users SET sms_2fa_enabled = ? WHERE id = ?");
        $adminFlag->execute([$adminSms, (int)$admin['id']]);

        admin_audit((int)$admin['id'], 'update_sms_settings', 'wallet_settings', '1', [
            'admin_sms_2fa_required' => $adminSms,
            'wallet_sms_registration_required' => $walletRegistrationSms,
            'wallet_sms_login_required' => $walletLoginSms,
            'wallet_sms_withdrawal_required' => $walletWithdrawalSms,
        ]);
        $msg = 'SMS security settings updated.';
    } elseif ($action === 'totp_settings') {
        $adminTotp = isset($_POST['admin_totp_required']) ? 1 : 0;
        $walletLoginTotp = isset($_POST['wallet_totp_login_required']) ? 1 : 0;
        $walletWithdrawalTotp = isset($_POST['wallet_totp_withdrawal_required']) ? 1 : 0;

        $stmt = $pdo->prepare(
            "UPDATE wallet_settings
             SET admin_totp_required = ?,
                 wallet_totp_login_required = ?,
                 wallet_totp_withdrawal_required = ?
             WHERE id = 1"
        );
        $stmt->execute([$adminTotp, $walletLoginTotp, $walletWithdrawalTotp]);
        admin_audit((int)$admin['id'], 'update_totp_settings', 'wallet_settings', '1', [
            'admin_totp_required' => $adminTotp,
            'wallet_totp_login_required' => $walletLoginTotp,
            'wallet_totp_withdrawal_required' => $walletWithdrawalTotp,
        ]);
        $msg = 'Authenticator app settings updated.';
    } else {
        $err = 'Unknown site config action.';
    }
}

$siteSettings = site_status_settings();
$settings = $pdo->query("SELECT * FROM wallet_settings WHERE id = 1")->fetch();

render_admin_header('Site Config');
?>
<?php if ($msg): ?><div class="card"><p class="ok"><?= h($msg) ?></p></div><?php endif; ?>
<?php if ($err): ?><div class="card"><p class="err"><?= h($err) ?></p></div><?php endif; ?>

<div class="grid">
  <div class="card"><div>Site Mode</div><div class="metric <?= $siteSettings['site_mode'] === 'full_launch' ? 'ok' : 'warn' ?>"><?= h(str_replace('_', ' ', (string)$siteSettings['site_mode'])) ?></div></div>
  <div class="card"><div>Admin SMS</div><div class="metric <?= ((int)($settings['admin_sms_2fa_required'] ?? 0) === 1) ? 'ok' : 'warn' ?>"><?= ((int)($settings['admin_sms_2fa_required'] ?? 0) === 1) ? 'On' : 'Off' ?></div></div>
  <div class="card"><div>Admin Authenticator</div><div class="metric <?= ((int)($settings['admin_totp_required'] ?? 0) === 1) ? 'ok' : 'warn' ?>"><?= ((int)($settings['admin_totp_required'] ?? 0) === 1) ? 'On' : 'Off' ?></div></div>
  <div class="card"><div>Wallet Login SMS</div><div class="metric <?= ((int)($settings['wallet_sms_login_required'] ?? 0) === 1) ? 'ok' : 'warn' ?>"><?= ((int)($settings['wallet_sms_login_required'] ?? 0) === 1) ? 'On' : 'Off' ?></div></div>
  <div class="card"><div>Withdrawal SMS</div><div class="metric <?= ((int)($settings['wallet_sms_withdrawal_required'] ?? 0) === 1) ? 'ok' : 'warn' ?>"><?= ((int)($settings['wallet_sms_withdrawal_required'] ?? 0) === 1) ? 'On' : 'Off' ?></div></div>
</div>

<div class="card">
  <h3>Site Status</h3>
  <p>Controls what normal visitors see. The bypass IP can view the normal website while pre-launch or maintenance mode is active. Admin pages remain available. Privacy Policy and Terms remain public.</p>
  <p><b>Your detected IP:</b> <code><?= h(site_status_client_ip() !== '' ? site_status_client_ip() : 'unknown') ?></code></p>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="site_status">

    <label>Website Mode<br>
      <select name="site_mode">
        <option value="pre_launch" <?= $siteSettings['site_mode'] === 'pre_launch' ? 'selected' : '' ?>>Pre-launch: show coming soon page to everyone except bypass IP</option>
        <option value="maintenance" <?= $siteSettings['site_mode'] === 'maintenance' ? 'selected' : '' ?>>Maintenance: show maintenance page to everyone except bypass IP</option>
        <option value="full_launch" <?= $siteSettings['site_mode'] === 'full_launch' ? 'selected' : '' ?>>Full launch: normal website open to everyone</option>
      </select>
    </label><br><br>

    <label>Bypass IP Address<br>
      <input name="bypass_ip" value="<?= h((string)$siteSettings['bypass_ip']) ?>" maxlength="64" placeholder="<?= h(site_status_client_ip() !== '' ? site_status_client_ip() : 'Your IP address') ?>">
    </label>
    <p><small>Set this to your current IP if you want to preview the full website while other users see the pre-launch or maintenance page.</small></p>

    <h4>Pre-launch Page</h4>
    <label>Title<br><input name="pre_launch_title" value="<?= h((string)$siteSettings['pre_launch_title']) ?>" maxlength="190"></label><br><br>
    <label>Launch Date / ETA Text<br><input name="pre_launch_eta" value="<?= h((string)$siteSettings['pre_launch_eta']) ?>" maxlength="190" placeholder="Example: Public launch target: July 2026"></label><br><br>
    <label>Pre-launch Message<br><textarea name="pre_launch_message" rows="5"><?= h((string)$siteSettings['pre_launch_message']) ?></textarea></label>

    <h4>Maintenance Page</h4>
    <label>Title<br><input name="maintenance_title" value="<?= h((string)$siteSettings['maintenance_title']) ?>" maxlength="190"></label><br><br>
    <label>Maintenance Start<br><input name="maintenance_start_at" value="<?= h((string)$siteSettings['maintenance_start_at']) ?>" maxlength="190" placeholder="Example: June 5, 2026 10:00 PM UTC"></label><br><br>
    <label>Maintenance End<br><input name="maintenance_end_at" value="<?= h((string)$siteSettings['maintenance_end_at']) ?>" maxlength="190" placeholder="Example: June 6, 2026 2:00 AM UTC"></label><br><br>
    <label>Maintenance Message<br><textarea name="maintenance_message" rows="4"><?= h((string)$siteSettings['maintenance_message']) ?></textarea></label><br><br>

    <button type="submit">Save Site Status</button>
  </form>
</div>

<div class="card">
  <h3>SMS Security Controls</h3>
  <p>Twilio is configured, but SMS requirements should stay disabled until your A2P campaign is verified. Enable these controls after SMS delivery is approved.</p>
  <p><b>Provider status:</b> <?= sms_is_enabled() ? '<span class="ok">Configured</span>' : '<span class="warn">Not configured</span>' ?></p>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="sms_settings">
    <label><input type="checkbox" name="admin_sms_2fa_required" <?= ((int)($settings['admin_sms_2fa_required'] ?? 0) === 1) ? 'checked' : '' ?>> Require SMS verification for master admin login</label><br>
    <label><input type="checkbox" name="wallet_sms_registration_required" <?= ((int)($settings['wallet_sms_registration_required'] ?? 0) === 1) ? 'checked' : '' ?>> Require phone number when users register</label><br>
    <label><input type="checkbox" name="wallet_sms_login_required" <?= ((int)($settings['wallet_sms_login_required'] ?? 0) === 1) ? 'checked' : '' ?>> Require SMS verification for wallet user login</label><br>
    <label><input type="checkbox" name="wallet_sms_withdrawal_required" <?= ((int)($settings['wallet_sms_withdrawal_required'] ?? 0) === 1) ? 'checked' : '' ?>> Require SMS verification before wallet withdrawals</label><br><br>
    <button type="submit">Save SMS Settings</button>
  </form>
</div>

<div class="card">
  <h3>Authenticator App Controls</h3>
  <p>Controls Google Authenticator/Authy/1Password style TOTP codes. Set up your admin authenticator before requiring it for admin login.</p>
  <div class="admin-actions"><a class="button" href="<?= h(admin_url('/authenticator.php')) ?>">Set Up Admin Authenticator</a></div>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="totp_settings">
    <label><input type="checkbox" name="admin_totp_required" <?= ((int)($settings['admin_totp_required'] ?? 0) === 1) ? 'checked' : '' ?>> Require authenticator app for master admin login</label><br>
    <label><input type="checkbox" name="wallet_totp_login_required" <?= ((int)($settings['wallet_totp_login_required'] ?? 0) === 1) ? 'checked' : '' ?>> Require authenticator app for wallet user login when user has it enabled</label><br>
    <label><input type="checkbox" name="wallet_totp_withdrawal_required" <?= ((int)($settings['wallet_totp_withdrawal_required'] ?? 0) === 1) ? 'checked' : '' ?>> Require authenticator app before wallet withdrawals when user has it enabled</label><br><br>
    <button type="submit">Save Authenticator Settings</button>
  </form>
</div>
<?php render_admin_footer(); ?>
