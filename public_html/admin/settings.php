<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/settings.php';
require_once __DIR__ . '/../app/site_status.php';
require_once __DIR__ . '/../app/sms.php';
require_once __DIR__ . '/../app/totp.php';
require_once __DIR__ . '/../app/security_log.php';
require_once __DIR__ . '/../app/admin_view.php';

$admin = admin_require_user();
$pdo = wallet_db();
site_status_ensure_schema();
sms_ensure_schema();
totp_ensure_schema();
$tabs = [
    'general' => 'General Site Settings',
    'branding' => 'Branding Settings',
    'analytics' => 'Analytics Settings',
    'bot-rate' => 'Bot / Rate Limit Settings',
    'security-2fa' => 'SMS / Authenticator Settings',
    'wallet' => 'Wallet Settings',
    'pool' => 'Mining Pool Settings',
    'node-explorer' => 'Node / Explorer Settings',
    'downloads' => 'Download Settings',
    'docs' => 'Docs Settings',
    'email' => 'Email / Notification Settings',
    'smtp' => 'SMTP Settings',
    'maintenance' => 'Site Status / Maintenance',
    'seo' => 'SEO Settings',
    'social' => 'Social Links',
    'legal' => 'Legal / Risk Notice Settings',
];
$requestedTab = (string)($_GET['tab'] ?? 'general');
$tab = array_key_exists($requestedTab, $tabs) ? $requestedTab : 'general';
$msg = '';
$err = '';

function settings_bool(string $key, bool $default = false): bool
{
    return (bool)getSetting($key, $default);
}

function settings_text(string $key, string $default = ''): string
{
    return (string)getSetting($key, $default);
}

function settings_int_value(string $key, int $default = 0): int
{
    return (int)getSetting($key, $default);
}

function settings_save_fields(array $fields, int $adminId): array
{
    $changed = [];
    $input = is_array($_POST['setting'] ?? null) ? $_POST['setting'] : [];
    foreach ($fields as $key => $config) {
        $type = (string)($config['type'] ?? 'string');
        $old = getSetting($key, $config['default'] ?? null);
        $value = match ($type) {
            'boolean' => isset($input[$key]),
            'integer' => max((int)($config['min'] ?? PHP_INT_MIN), min((int)($config['max'] ?? PHP_INT_MAX), (int)($input[$key] ?? ($config['default'] ?? 0)))),
            'decimal' => (float)($input[$key] ?? ($config['default'] ?? 0)),
            default => substr(trim((string)($input[$key] ?? ($config['default'] ?? ''))), 0, (int)($config['max_length'] ?? 4000)),
        };
        updateSetting($key, $value, $type, $adminId);
        if ((string)$old !== (string)$value) {
            $changed[$key] = ['from' => is_bool($old) ? (int)$old : $old, 'to' => is_bool($value) ? (int)$value : $value];
        }
    }
    return $changed;
}

function settings_fields_for_tab(string $tab): array
{
    return match ($tab) {
        'general' => [
            'site.name' => ['type' => 'string', 'default' => 'HobbyHash Coin', 'max_length' => 190],
            'coin.name' => ['type' => 'string', 'default' => 'HobbyHash Coin', 'max_length' => 190],
            'coin.ticker' => ['type' => 'string', 'default' => 'HOBC', 'max_length' => 20],
            'site.public_notice' => ['type' => 'text', 'default' => '', 'max_length' => 2000],
        ],
        'branding' => [
            'branding.logo_url' => ['type' => 'string', 'default' => '/assets/images/logo-round.png', 'max_length' => 512],
            'branding.favicon_url' => ['type' => 'string', 'default' => '/favicon.ico', 'max_length' => 512],
            'branding.primary_theme_color' => ['type' => 'string', 'default' => '#f6b928', 'max_length' => 20],
            'branding.accent_color' => ['type' => 'string', 'default' => '#ffd764', 'max_length' => 20],
            'branding.footer_text' => ['type' => 'text', 'default' => '', 'max_length' => 2000],
        ],
        'analytics' => [
            'analytics.enabled' => ['type' => 'boolean', 'default' => true],
            'analytics.retention_days' => ['type' => 'integer', 'default' => 180, 'min' => 1, 'max' => 3650],
            'analytics.bot_logging_enabled' => ['type' => 'boolean', 'default' => true],
            'analytics.download_tracking_enabled' => ['type' => 'boolean', 'default' => true],
        ],
        'bot-rate' => [
            'rate_limit.enabled' => ['type' => 'boolean', 'default' => true],
            'rate_limit.requests_per_minute' => ['type' => 'integer', 'default' => 120, 'min' => 1, 'max' => 10000],
            'security.admin_failed_login_threshold' => ['type' => 'integer', 'default' => 6, 'min' => 1, 'max' => 50],
            'security.admin_lockout_seconds' => ['type' => 'integer', 'default' => 900, 'min' => 60, 'max' => 86400],
            'bot.block_suspicious_user_agents' => ['type' => 'boolean', 'default' => false],
        ],
        'pool' => [
            'ops.pool_public_stats_paused' => ['type' => 'boolean', 'default' => false],
            'pool.main_enabled' => ['type' => 'boolean', 'default' => true],
            'pool.nano_enabled' => ['type' => 'boolean', 'default' => true],
            'pool.miner_leaderboard_enabled' => ['type' => 'boolean', 'default' => true],
            'pool.show_best_share_enabled' => ['type' => 'boolean', 'default' => true],
        ],
        'node-explorer' => [
            'explorer.public_enabled' => ['type' => 'boolean', 'default' => true],
            'explorer.maintenance_message' => ['type' => 'text', 'default' => '', 'max_length' => 2000],
            'node.show_status_publicly' => ['type' => 'boolean', 'default' => true],
        ],
        'downloads' => [
            'downloads.public_enabled' => ['type' => 'boolean', 'default' => true],
            'downloads.show_checksums' => ['type' => 'boolean', 'default' => true],
            'downloads.require_checksum_before_publish' => ['type' => 'boolean', 'default' => true],
        ],
        'docs' => [
            'docs.public_enabled' => ['type' => 'boolean', 'default' => true],
            'docs.show_search' => ['type' => 'boolean', 'default' => true],
            'docs.show_last_updated' => ['type' => 'boolean', 'default' => true],
        ],
        'email' => [
            'notifications.admin_email' => ['type' => 'string', 'default' => '', 'max_length' => 320],
            'notifications.support_enabled' => ['type' => 'boolean', 'default' => true],
            'notifications.security_alerts_enabled' => ['type' => 'boolean', 'default' => true],
        ],
        'seo' => [
            'seo.default_meta_title' => ['type' => 'string', 'default' => 'HobbyHash Coin', 'max_length' => 190],
            'seo.default_meta_description' => ['type' => 'text', 'default' => 'HobbyHash Coin command center for HOBC mining, wallet, explorer, and stats.', 'max_length' => 320],
            'seo.robots_index' => ['type' => 'boolean', 'default' => true],
            'seo.sitemap_enabled' => ['type' => 'boolean', 'default' => true],
        ],
        'legal' => [
            'legal.risk_notice' => ['type' => 'text', 'default' => 'Cryptocurrency mining and custodial wallets involve risk. Do your own research.', 'max_length' => 4000],
            'legal.wallet_custody_notice' => ['type' => 'text', 'default' => 'The web wallet is custodial. Use a local wallet for larger balances.', 'max_length' => 4000],
            'legal.download_warning' => ['type' => 'text', 'default' => 'Only download HOBC software from official sources and verify checksums.', 'max_length' => 4000],
        ],
        'social' => [
            'listing.social_x' => ['type' => 'string', 'default' => 'https://x.com/HobbyHashCoin', 'max_length' => 512],
            'listing.social_facebook' => ['type' => 'string', 'default' => 'https://www.facebook.com/people/HobbyHash-Coin/61590689639798/', 'max_length' => 512],
            'listing.social_discord' => ['type' => 'string', 'default' => '', 'max_length' => 512],
            'listing.social_telegram' => ['type' => 'string', 'default' => '', 'max_length' => 512],
            'listing.source_repo_url' => ['type' => 'string', 'default' => 'To be confirmed — public source repository URL will be published here.', 'max_length' => 512],
            'listing.contact_email' => ['type' => 'string', 'default' => 'listings@hobbyhashcoin.com', 'max_length' => 320],
        ],
        default => [],
    };
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_validate_or_fail();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_settings') {
            $targetTab = (string)($_POST['settings_tab'] ?? $tab);
            $fields = settings_fields_for_tab($targetTab);
            if ($fields === []) {
                throw new RuntimeException('Unknown settings tab.');
            }
            $changed = settings_save_fields($fields, (int)$admin['id']);
            admin_audit((int)$admin['id'], 'update_admin_settings', 'admin_settings', $targetTab, ['changed' => array_keys($changed)]);
            $msg = 'Settings saved.';
            $tab = array_key_exists($targetTab, $tabs) ? $targetTab : $tab;
        } elseif ($action === 'save_site_status') {
            $mode = (string)($_POST['site_mode'] ?? 'full_launch');
            if (!in_array($mode, ['pre_launch', 'maintenance', 'full_launch'], true)) {
                $mode = 'full_launch';
            }

            $currentSiteSettings = site_status_settings();
            $defaults = site_status_defaults();
            $bypassIp = array_key_exists('bypass_ip', $_POST) ? trim((string)$_POST['bypass_ip']) : (string)$currentSiteSettings['bypass_ip'];
            $preLaunchTitle = array_key_exists('pre_launch_title', $_POST) ? trim((string)$_POST['pre_launch_title']) : (string)$currentSiteSettings['pre_launch_title'];
            $preLaunchMessage = array_key_exists('pre_launch_message', $_POST) ? trim((string)$_POST['pre_launch_message']) : (string)$currentSiteSettings['pre_launch_message'];
            $preLaunchEta = array_key_exists('pre_launch_eta', $_POST) ? trim((string)$_POST['pre_launch_eta']) : (string)$currentSiteSettings['pre_launch_eta'];
            $maintenanceTitle = array_key_exists('maintenance_title', $_POST) ? trim((string)$_POST['maintenance_title']) : (string)$currentSiteSettings['maintenance_title'];
            $maintenanceMessage = array_key_exists('maintenance_message', $_POST) ? trim((string)$_POST['maintenance_message']) : (string)$currentSiteSettings['maintenance_message'];
            $maintenanceStart = array_key_exists('maintenance_start_at', $_POST) ? trim((string)$_POST['maintenance_start_at']) : (string)$currentSiteSettings['maintenance_start_at'];
            $maintenanceEnd = array_key_exists('maintenance_end_at', $_POST) ? trim((string)$_POST['maintenance_end_at']) : (string)$currentSiteSettings['maintenance_end_at'];

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
            admin_audit((int)$admin['id'], 'update_settings_site_status', 'site_settings', '1', [
                'site_mode' => $mode,
                'bypass_ip' => $bypassIp,
                'maintenance_start_at' => $maintenanceStart,
                'maintenance_end_at' => $maintenanceEnd,
            ]);
            $msg = 'Site status and launch/maintenance settings saved.';
            $tab = (string)($_POST['return_tab'] ?? 'maintenance');
            $tab = array_key_exists($tab, $tabs) ? $tab : 'maintenance';
        } elseif ($action === 'save_wallet_settings') {
            $stmt = $pdo->prepare(
                "UPDATE wallet_settings
                 SET maintenance_mode = ?, deposits_paused = ?, withdrawals_paused = ?,
                     per_withdrawal_min_amount = ?, admin_approval_threshold = ?
                 WHERE id = 1"
            );
            $depositsEnabled = isset($_POST['deposits_enabled']);
            $withdrawalsEnabled = isset($_POST['withdrawals_enabled']);
            $stmt->execute([
                isset($_POST['wallet_maintenance_mode']) ? 1 : 0,
                $depositsEnabled ? 0 : 1,
                $withdrawalsEnabled ? 0 : 1,
                number_format(max(0.00000001, (float)($_POST['minimum_withdrawal'] ?? 0.00000001)), 8, '.', ''),
                number_format(max(0.00000001, (float)($_POST['manual_review_threshold'] ?? 5000)), 8, '.', ''),
            ]);
            updateSetting('wallet.public_enabled', isset($_POST['public_wallet_enabled']), 'boolean', (int)$admin['id']);
            updateSetting('security.wallet_signups_enabled', isset($_POST['wallet_signups_enabled']), 'boolean', (int)$admin['id']);
            updateSetting('wallet.maintenance_message', trim((string)($_POST['wallet_maintenance_message'] ?? '')), 'text', (int)$admin['id']);
            admin_audit((int)$admin['id'], 'update_settings_wallet', 'wallet_settings', '1');
            $msg = 'Wallet settings saved.';
            $tab = 'wallet';
        } elseif ($action === 'save_pool_settings') {
            updateSetting('ops.pool_public_stats_paused', !isset($_POST['pool_public_stats_enabled']), 'boolean', (int)$admin['id']);
            updateSetting('pool.main_enabled', isset($_POST['pool_main_enabled']), 'boolean', (int)$admin['id']);
            updateSetting('pool.nano_enabled', isset($_POST['pool_nano_enabled']), 'boolean', (int)$admin['id']);
            updateSetting('pool.miner_leaderboard_enabled', isset($_POST['pool_miner_leaderboard_enabled']), 'boolean', (int)$admin['id']);
            updateSetting('pool.show_best_share_enabled', isset($_POST['pool_show_best_share_enabled']), 'boolean', (int)$admin['id']);
            admin_audit((int)$admin['id'], 'update_settings_pool', 'admin_settings', 'pool');
            $msg = 'Mining pool settings saved.';
            $tab = 'pool';
        } elseif ($action === 'save_sms_settings') {
            $adminSms = isset($_POST['admin_sms_2fa_required']) ? 1 : 0;
            $walletRegistrationSms = isset($_POST['wallet_sms_registration_required']) ? 1 : 0;
            $walletLoginSms = isset($_POST['wallet_sms_login_required']) ? 1 : 0;
            $walletWithdrawalSms = isset($_POST['wallet_sms_withdrawal_required']) ? 1 : 0;
            $smsProviderMode = (string)($_POST['sms_provider_mode'] ?? 'manual');
            if (!in_array($smsProviderMode, ['manual', 'twilio_verify'], true)) {
                $smsProviderMode = 'manual';
            }
            $twilioVerifyServiceSid = trim((string)($_POST['twilio_verify_service_sid'] ?? ''));
            if ($twilioVerifyServiceSid !== '' && !preg_match('/^VA[a-fA-F0-9]{32}$/', $twilioVerifyServiceSid)) {
                throw new RuntimeException('Twilio Verify Service SID must start with VA and contain 34 characters total.');
            }
            $stmt = $pdo->prepare(
                "UPDATE wallet_settings
                 SET admin_sms_2fa_required = ?,
                     wallet_sms_registration_required = ?,
                     wallet_sms_login_required = ?,
                     wallet_sms_withdrawal_required = ?,
                     sms_provider_mode = ?,
                     twilio_verify_service_sid = ?
                 WHERE id = 1"
            );
            $stmt->execute([
                $adminSms,
                $walletRegistrationSms,
                $walletLoginSms,
                $walletWithdrawalSms,
                $smsProviderMode,
                $twilioVerifyServiceSid !== '' ? $twilioVerifyServiceSid : null,
            ]);
            $adminFlag = $pdo->prepare("UPDATE admin_users SET sms_2fa_enabled = ? WHERE id = ?");
            $adminFlag->execute([$adminSms, (int)$admin['id']]);
            admin_audit((int)$admin['id'], 'update_sms_settings', 'wallet_settings', '1', [
                'admin_sms_2fa_required' => $adminSms,
                'wallet_sms_registration_required' => $walletRegistrationSms,
                'wallet_sms_login_required' => $walletLoginSms,
                'wallet_sms_withdrawal_required' => $walletWithdrawalSms,
                'sms_provider_mode' => $smsProviderMode,
                'twilio_verify_service_sid_set' => $twilioVerifyServiceSid !== '',
            ]);
            $msg = 'SMS security settings saved.';
            $tab = 'security-2fa';
        } elseif ($action === 'save_totp_settings') {
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
            $msg = 'Authenticator app settings saved.';
            $tab = 'security-2fa';
        } elseif ($action === 'save_smtp_settings') {
            $enabled = isset($_POST['is_enabled']) ? 1 : 0;
            $host = trim((string)($_POST['host'] ?? ''));
            $port = max(1, min(65535, (int)($_POST['port'] ?? 587)));
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $encryption = (string)($_POST['encryption'] ?? 'tls');
            $fromEmail = trim((string)($_POST['from_email'] ?? ''));
            $fromName = trim((string)($_POST['from_name'] ?? ''));
            if (!in_array($encryption, ['none', 'tls', 'ssl'], true)) {
                throw new RuntimeException('Invalid encryption setting.');
            }
            if ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Invalid from email.');
            }
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
            admin_audit((int)$admin['id'], 'smtp_settings_updated', 'smtp_settings', '1', [
                'enabled' => $enabled,
                'host' => $host,
                'port' => $port,
            ]);
            $msg = 'SMTP settings saved.';
            $tab = 'smtp';
        } else {
            throw new RuntimeException('Unknown settings action.');
        }
    } catch (Throwable $e) {
        wallet_log_error('settings action failed: ' . $e->getMessage());
        $err = $e->getMessage();
    }
}

$siteSettings = site_status_settings();
$walletSettings = [
    'maintenance_mode' => 0,
    'deposits_paused' => 0,
    'withdrawals_paused' => 0,
    'per_withdrawal_min_amount' => '0.00000001',
    'admin_approval_threshold' => '5000.00000000',
];
if (in_array($tab, ['wallet', 'security-2fa'], true)) {
    try {
        $walletSettings = wallet_settings();
    } catch (Throwable $e) {
        $err = $err !== '' ? $err : 'Wallet settings row is missing; wallet controls are showing defaults.';
    }
}
$smtpSettings = ['is_enabled' => 0, 'host' => '', 'port' => 587, 'username' => '', 'encryption' => 'tls', 'from_email' => '', 'from_name' => 'HobbyHashCoin Support'];
if ($tab === 'smtp') {
    try {
        $row = $pdo->query("SELECT * FROM smtp_settings WHERE id = 1")->fetch();
        if (is_array($row)) {
            $smtpSettings = array_merge($smtpSettings, $row);
        }
    } catch (Throwable $e) {
        $err = $err !== '' ? $err : 'SMTP settings row is missing; SMTP controls are showing defaults.';
    }
}

render_admin_header('Settings', ['Settings']);
?>
<?php if ($msg): ?><?php admin_render_alert('success', $msg); ?><?php endif; ?>
<?php if ($err): ?><?php admin_render_alert('error', $err); ?><?php endif; ?>

<div class="admin-card">
  <div class="admin-actions">
    <?php foreach ($tabs as $key => $label): ?>
      <a class="admin-action <?= $tab === $key ? 'admin-action-primary' : 'admin-action-secondary' ?>" href="<?= h(admin_url('/settings.php?tab=' . rawurlencode($key))) ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($tab === 'maintenance'): ?>
  <div class="admin-card">
    <h3>Site Status / Maintenance</h3>
    <p>These are the old Site Config launch controls, now inside the new Settings setup. Admin pages remain available while public visitors see pre-launch or maintenance screens.</p>
    <p><b>Your detected IP:</b> <code><?= h(site_status_client_ip() !== '' ? site_status_client_ip() : 'unknown') ?></code></p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_site_status">
      <label>Public Launch Status<br>
        <select name="site_mode">
          <option value="pre_launch" <?= $siteSettings['site_mode'] === 'pre_launch' ? 'selected' : '' ?>>Pre-launch: show coming soon page to everyone except bypass IP</option>
          <option value="maintenance" <?= $siteSettings['site_mode'] === 'maintenance' ? 'selected' : '' ?>>Maintenance: show maintenance page to everyone except bypass IP</option>
          <option value="full_launch" <?= $siteSettings['site_mode'] === 'full_launch' ? 'selected' : '' ?>>Full launch: normal website open to everyone</option>
        </select>
      </label><br><br>
      <label>Bypass IP Address<br><input name="bypass_ip" value="<?= h((string)$siteSettings['bypass_ip']) ?>" maxlength="64" placeholder="<?= h(site_status_client_ip() !== '' ? site_status_client_ip() : 'Your IP address') ?>"></label>
      <p><small>Set this to your current IP if you want to preview the full website while others see the pre-launch or maintenance page.</small></p>
      <h4>Pre-launch Page</h4>
      <label>Title<br><input name="pre_launch_title" value="<?= h((string)$siteSettings['pre_launch_title']) ?>" maxlength="190"></label><br><br>
      <label>Launch Date / ETA Text<br><input name="pre_launch_eta" value="<?= h((string)$siteSettings['pre_launch_eta']) ?>" maxlength="190" placeholder="Example: Public launch target: July 2026"></label><br><br>
      <label>Pre-launch Message<br><textarea name="pre_launch_message" rows="5"><?= h((string)$siteSettings['pre_launch_message']) ?></textarea></label>
      <h4>Maintenance Page</h4>
      <label>Title<br><input name="maintenance_title" value="<?= h((string)$siteSettings['maintenance_title']) ?>" maxlength="190"></label><br><br>
      <label>Maintenance Start<br><input name="maintenance_start_at" value="<?= h((string)$siteSettings['maintenance_start_at']) ?>" maxlength="190" placeholder="Example: June 5, 2026 10:00 PM UTC"></label><br><br>
      <label>Maintenance End<br><input name="maintenance_end_at" value="<?= h((string)$siteSettings['maintenance_end_at']) ?>" maxlength="190" placeholder="Example: June 6, 2026 2:00 AM UTC"></label><br><br>
      <label>Maintenance Message<br><textarea name="maintenance_message" rows="4"><?= h((string)$siteSettings['maintenance_message']) ?></textarea></label><br><br>
      <button type="submit" data-confirm="Save public site status and visitor-facing messages?">Save Site Status</button>
    </form>
  </div>
<?php elseif ($tab === 'security-2fa'): ?>
  <div class="admin-grid admin-grid-tight">
    <?= admin_stat_card('SMS Service', sms_provider_mode() === 'twilio_verify' ? 'Verify' : 'Manual', sms_is_enabled() ? 'ok' : 'warn') ?>
    <?= admin_stat_card('Admin SMS', ((int)($walletSettings['admin_sms_2fa_required'] ?? 0) === 1) ? 'On' : 'Off', ((int)($walletSettings['admin_sms_2fa_required'] ?? 0) === 1) ? 'ok' : 'warn') ?>
    <?= admin_stat_card('Admin Authenticator', ((int)($walletSettings['admin_totp_required'] ?? 0) === 1) ? 'On' : 'Off', ((int)($walletSettings['admin_totp_required'] ?? 0) === 1) ? 'ok' : 'warn') ?>
    <?= admin_stat_card('Wallet Login SMS', ((int)($walletSettings['wallet_sms_login_required'] ?? 0) === 1) ? 'On' : 'Off', ((int)($walletSettings['wallet_sms_login_required'] ?? 0) === 1) ? 'ok' : 'warn') ?>
  </div>
  <div class="admin-card">
    <h3>SMS Security Controls</h3>
    <p>Email verification is the first account security step. SMS is the second step for wallet registration, login, recovery, and protected wallet actions. Choose whether SMS uses manual HOBC code sending or Twilio Verify.</p>
    <p><b>Provider status:</b> <?= sms_is_enabled() ? '<span class="ok">Configured</span>' : '<span class="warn">Not configured</span>' ?></p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_sms_settings">
      <h4>SMS Service</h4>
      <label>Verification Service<br>
        <select name="sms_provider_mode">
          <option value="manual" <?= sms_provider_mode() === 'manual' ? 'selected' : '' ?>>Manual HOBC codes through Twilio Messaging Service</option>
          <option value="twilio_verify" <?= sms_provider_mode() === 'twilio_verify' ? 'selected' : '' ?>>Twilio Verify Service</option>
        </select>
      </label>
      <p><small>Manual mode uses the Twilio Messaging Service SID or from-number in the private config. Twilio Verify mode uses the Verify Service SID below and Twilio handles the code text/check.</small></p>
      <label>Twilio Verify Service SID<br><input name="twilio_verify_service_sid" value="<?= h((string)($walletSettings['twilio_verify_service_sid'] ?? '')) ?>" maxlength="64" placeholder="VAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"></label>
      <p><small>Twilio account SID, API key SID, API key secret, messaging service SID, and from-number stay in private server config and are not exposed here.</small></p>
      <h4>SMS Requirement Coverage</h4>
      <label><input type="checkbox" name="admin_sms_2fa_required" <?= ((int)($walletSettings['admin_sms_2fa_required'] ?? 0) === 1) ? 'checked' : '' ?>> Require SMS verification for master admin login</label>
      <label><input type="checkbox" name="wallet_sms_registration_required" <?= ((int)($walletSettings['wallet_sms_registration_required'] ?? 0) === 1) ? 'checked' : '' ?>> Keep SMS registration enabled for wallet users</label>
      <label><input type="checkbox" name="wallet_sms_login_required" <?= ((int)($walletSettings['wallet_sms_login_required'] ?? 0) === 1) ? 'checked' : '' ?>> Require SMS verification for wallet user login</label>
      <label><input type="checkbox" name="wallet_sms_withdrawal_required" <?= ((int)($walletSettings['wallet_sms_withdrawal_required'] ?? 0) === 1) ? 'checked' : '' ?>> Require SMS verification before wallet withdrawals</label><br>
      <button type="submit" data-confirm="Save SMS provider and requirement settings? This affects admin and wallet verification flows.">Save SMS Settings</button>
    </form>
  </div>
  <div class="admin-card">
    <h3>Authenticator App Controls</h3>
    <p>Authenticator app security is an extra user-chosen double-check. If a wallet user enables it, password recovery asks for the authenticator code too.</p>
    <div class="admin-actions"><a class="admin-action admin-action-secondary" href="<?= h(admin_url('/authenticator.php')) ?>">Set Up Admin Authenticator</a></div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_totp_settings">
      <label><input type="checkbox" name="admin_totp_required" <?= ((int)($walletSettings['admin_totp_required'] ?? 0) === 1) ? 'checked' : '' ?>> Require authenticator app for master admin login</label>
      <label><input type="checkbox" name="wallet_totp_login_required" <?= ((int)($walletSettings['wallet_totp_login_required'] ?? 0) === 1) ? 'checked' : '' ?>> Require authenticator app for wallet user login when user has it enabled</label>
      <label><input type="checkbox" name="wallet_totp_withdrawal_required" <?= ((int)($walletSettings['wallet_totp_withdrawal_required'] ?? 0) === 1) ? 'checked' : '' ?>> Require authenticator app before wallet withdrawals when user has it enabled</label><br>
      <button type="submit" data-confirm="Save authenticator app requirement settings? This affects admin and wallet verification flows.">Save Authenticator Settings</button>
    </form>
  </div>
<?php elseif ($tab === 'smtp'): ?>
  <div class="admin-card">
    <h3>SMTP Settings</h3>
    <p>These are the old SMTP controls, now inside the new Settings setup. Leave password blank to keep the existing saved password.</p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_smtp_settings">
      <label><input type="checkbox" name="is_enabled" <?= ((int)($smtpSettings['is_enabled'] ?? 0) === 1) ? 'checked' : '' ?>> Enable outgoing support email</label>
      <label>SMTP Host<br><input name="host" value="<?= h((string)($smtpSettings['host'] ?? '')) ?>" maxlength="190"></label><br><br>
      <label>Port<br><input type="number" name="port" value="<?= h((string)($smtpSettings['port'] ?? 587)) ?>"></label><br><br>
      <label>Encryption<br>
        <select name="encryption">
          <?php foreach (['none', 'tls', 'ssl'] as $enc): ?>
            <option value="<?= h($enc) ?>" <?= (($smtpSettings['encryption'] ?? 'tls') === $enc) ? 'selected' : '' ?>><?= h($enc) ?></option>
          <?php endforeach; ?>
        </select>
      </label><br><br>
      <label>Username<br><input name="username" value="<?= h((string)($smtpSettings['username'] ?? '')) ?>" maxlength="190"></label><br><br>
      <label>Password<br><input type="password" name="password" placeholder="Leave blank to keep existing password"></label><br><br>
      <label>From Email<br><input type="email" name="from_email" value="<?= h((string)($smtpSettings['from_email'] ?? '')) ?>" maxlength="190"></label><br><br>
      <label>From Name<br><input name="from_name" value="<?= h((string)($smtpSettings['from_name'] ?? 'HobbyHashCoin Support')) ?>" maxlength="120"></label><br><br>
      <button type="submit" data-confirm="Save SMTP settings?">Save SMTP Settings</button>
    </form>
  </div>
<?php elseif ($tab === 'wallet'): ?>
  <div class="admin-card">
    <h3>Wallet Settings</h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_wallet_settings">
      <label><input type="checkbox" name="public_wallet_enabled" <?= settings_bool('wallet.public_enabled', true) ? 'checked' : '' ?>> Public wallet enabled</label>
      <label><input type="checkbox" name="wallet_signups_enabled" <?= settings_bool('security.wallet_signups_enabled', true) ? 'checked' : '' ?>> New wallet signups enabled</label>
      <label><input type="checkbox" name="deposits_enabled" <?= ((int)$walletSettings['deposits_paused'] !== 1) ? 'checked' : '' ?>> Deposits enabled</label>
      <label><input type="checkbox" name="withdrawals_enabled" <?= ((int)$walletSettings['withdrawals_paused'] !== 1) ? 'checked' : '' ?>> Withdrawals enabled</label>
      <label><input type="checkbox" name="wallet_maintenance_mode" <?= ((int)$walletSettings['maintenance_mode'] === 1) ? 'checked' : '' ?>> Wallet maintenance mode</label><br>
      <label>Minimum Withdrawal<br><input type="number" step="0.00000001" name="minimum_withdrawal" value="<?= h((string)$walletSettings['per_withdrawal_min_amount']) ?>"></label><br><br>
      <label>Manual Review Threshold<br><input type="number" step="0.00000001" name="manual_review_threshold" value="<?= h((string)$walletSettings['admin_approval_threshold']) ?>"></label><br><br>
      <label>Wallet Maintenance Message<br><textarea name="wallet_maintenance_message" rows="3"><?= h(settings_text('wallet.maintenance_message', '')) ?></textarea></label><br><br>
      <button type="submit" data-confirm="Save wallet settings?">Save Wallet Settings</button>
    </form>
  </div>
<?php elseif ($tab === 'pool'): ?>
  <div class="admin-card">
    <h3>Mining Pool Settings</h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_pool_settings">
      <label><input type="checkbox" name="pool_public_stats_enabled" <?= !settings_bool('ops.pool_public_stats_paused', false) ? 'checked' : '' ?>> Public pool stats enabled</label>
      <label><input type="checkbox" name="pool_main_enabled" <?= settings_bool('pool.main_enabled', true) ? 'checked' : '' ?>> Main pool enabled</label>
      <label><input type="checkbox" name="pool_nano_enabled" <?= settings_bool('pool.nano_enabled', true) ? 'checked' : '' ?>> Nano pool enabled</label>
      <label><input type="checkbox" name="pool_miner_leaderboard_enabled" <?= settings_bool('pool.miner_leaderboard_enabled', true) ? 'checked' : '' ?>> Miner leaderboard enabled</label>
      <label><input type="checkbox" name="pool_show_best_share_enabled" <?= settings_bool('pool.show_best_share_enabled', true) ? 'checked' : '' ?>> Show best share enabled</label><br>
      <button type="submit" data-confirm="Save mining pool settings?">Save Pool Settings</button>
    </form>
  </div>
<?php else: ?>
  <?php $fields = settings_fields_for_tab($tab); ?>
  <div class="admin-card">
    <h3><?= h($tabs[$tab]) ?></h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_settings">
      <input type="hidden" name="settings_tab" value="<?= h($tab) ?>">
      <?php foreach ($fields as $key => $config): ?>
        <?php
          $type = (string)($config['type'] ?? 'string');
          $label = ucwords(str_replace(['.', '_'], ' ', $key));
          $value = getSetting($key, $config['default'] ?? '');
        ?>
        <?php if ($type === 'boolean'): ?>
          <label><input type="checkbox" name="setting[<?= h($key) ?>]" <?= (bool)$value ? 'checked' : '' ?>> <?= h($label) ?></label>
        <?php elseif ($type === 'integer' || $type === 'decimal'): ?>
          <label><?= h($label) ?><br><input type="number" name="setting[<?= h($key) ?>]" value="<?= h((string)$value) ?>" <?= $type === 'decimal' ? 'step="0.00000001"' : '' ?>></label><br><br>
        <?php elseif (($config['type'] ?? '') === 'text'): ?>
          <label><?= h($label) ?><br><textarea name="setting[<?= h($key) ?>]" rows="4"><?= h((string)$value) ?></textarea></label><br><br>
        <?php else: ?>
          <label><?= h($label) ?><br><input name="setting[<?= h($key) ?>]" value="<?= h((string)$value) ?>"></label><br><br>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($tab === 'downloads'): ?><p><small>Checksum requirement is enforced by admin workflow settings only until public publish templates consume it.</small></p><?php endif; ?>
      <?php if ($tab === 'email'): ?><p><small>Notification recipients and alert toggles are managed here. SMTP server credentials are managed in the SMTP Settings tab.</small></p><?php endif; ?>
      <?php if ($tab === 'social'): ?><p><small>Full URLs or handles like <code>@HobbyHashCoin</code> work. Leave blank or use “To be confirmed” to hide a platform from the public site footer.</small></p><?php endif; ?>
      <button type="submit" data-confirm="Save <?= h($tabs[$tab]) ?>?">Save Settings</button>
    </form>
  </div>
  <?php if ($tab === 'general'): ?>
    <div class="admin-card">
      <h3>Public Launch Status</h3>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_site_status">
        <input type="hidden" name="return_tab" value="general">
        <label>Public Launch / Maintenance Mode<br>
          <select name="site_mode">
            <option value="pre_launch" <?= $siteSettings['site_mode'] === 'pre_launch' ? 'selected' : '' ?>>Pre-launch</option>
            <option value="maintenance" <?= $siteSettings['site_mode'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
            <option value="full_launch" <?= $siteSettings['site_mode'] === 'full_launch' ? 'selected' : '' ?>>Full launch</option>
          </select>
        </label><br><br>
        <button type="submit" data-confirm="Save public launch status?">Save Launch Status</button>
      </form>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php render_admin_footer(); ?>
