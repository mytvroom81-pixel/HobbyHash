<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/settings.php';
require_once __DIR__ . '/../app/site_status.php';
require_once __DIR__ . '/../app/totp.php';
require_once __DIR__ . '/../app/admin_view.php';
require_once __DIR__ . '/../app/analytics.php';

$admin = admin_require_user();
$pdo = wallet_db();
site_status_ensure_schema();
totp_ensure_schema();

function security_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool)$stmt->fetchColumn();
}

function security_count(PDO $pdo, string $table, string $where = '1=1'): int
{
    if (!security_table_exists($pdo, $table)) {
        return 0;
    }
    return (int)$pdo->query("SELECT COUNT(*) FROM `" . str_replace('`', '``', $table) . "` WHERE {$where}")->fetchColumn();
}

function security_setting_int_clamped(string $key, int $default, int $min, int $max): int
{
    return max($min, min($max, admin_setting_int($key, $default)));
}

function security_badge(bool $ok, string $okText = 'OK', string $badText = 'Review'): string
{
    return $ok ? '<span class="ok">' . h($okText) . '</span>' : '<span class="warn">' . h($badText) . '</span>';
}

function security_admins(PDO $pdo): array
{
    return $pdo->query("SELECT id, username, email, totp_enabled, sms_2fa_enabled, is_active, created_at, updated_at FROM admin_users ORDER BY username")->fetchAll();
}

function security_current_session_hash(): string
{
    return admin_session_hash();
}

function security_redirect_self(): void
{
    wallet_redirect(admin_url('/security.php'));
}

function security_export_csv(PDO $pdo, int $adminId): void
{
    admin_audit($adminId, 'export_security_logs', 'security_center', 'csv');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="hobc-security-logs-' . gmdate('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    if ($out === false) {
        exit;
    }
    fputcsv($out, ['source', 'id', 'created_at', 'admin_or_user', 'event_or_action', 'target_or_username', 'ip_hash_or_ip', 'user_agent_or_details']);

    if (security_table_exists($pdo, 'admin_security_events')) {
        $rows = $pdo->query("SELECT id, created_at, admin_user_id, event_type, username_attempted, ip_hash, user_agent FROM admin_security_events ORDER BY id DESC LIMIT 5000")->fetchAll();
        foreach ($rows as $row) {
            fputcsv($out, ['admin_security_events', $row['id'], $row['created_at'], $row['admin_user_id'], $row['event_type'], $row['username_attempted'], $row['ip_hash'], $row['user_agent']]);
        }
    }
    if (security_table_exists($pdo, 'admin_audit_log')) {
        $rows = $pdo->query("SELECT id, created_at, admin_user_id, action, target_type, target_id, ip_address, details_json FROM admin_audit_log ORDER BY id DESC LIMIT 5000")->fetchAll();
        foreach ($rows as $row) {
            fputcsv($out, ['admin_audit_log', $row['id'], $row['created_at'], $row['admin_user_id'], $row['action'], trim((string)$row['target_type'] . '#' . (string)$row['target_id'], '#'), $row['ip_address'], $row['details_json']]);
        }
    }
    if (security_table_exists($pdo, 'bot_events')) {
        $rows = $pdo->query("SELECT id, created_at, bot_name, event_type, url, ip_hash, user_agent FROM bot_events ORDER BY id DESC LIMIT 5000")->fetchAll();
        foreach ($rows as $row) {
            fputcsv($out, ['bot_events', $row['id'], $row['created_at'], $row['bot_name'], $row['event_type'], $row['url'], $row['ip_hash'], $row['user_agent']]);
        }
    }
    if (security_table_exists($pdo, 'site_errors')) {
        $rows = $pdo->query("SELECT id, created_at, level, message, url, ip_hash, user_agent FROM site_errors ORDER BY id DESC LIMIT 5000")->fetchAll();
        foreach ($rows as $row) {
            fputcsv($out, ['site_errors', $row['id'], $row['created_at'], $row['level'], $row['message'], $row['url'], $row['ip_hash'], $row['user_agent']]);
        }
    }
    fclose($out);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'security_logs') {
    security_export_csv($pdo, (int)$admin['id']);
}

$msg = '';
$err = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_validate_or_fail();
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'security_settings') {
            $threshold = max(1, min(50, (int)($_POST['admin_failed_login_threshold'] ?? 6)));
            $lockoutSeconds = max(60, min(86400, (int)($_POST['admin_lockout_seconds'] ?? 900)));
            $registrationEnabled = isset($_POST['registration_enabled']);
            $walletSignupsEnabled = isset($_POST['wallet_signups_enabled']);
            $notice = substr(trim((string)($_POST['notice_banner'] ?? '')), 0, 2000);

            admin_setting_set('security.admin_failed_login_threshold', $threshold, 'integer', (int)$admin['id']);
            admin_setting_set('security.admin_lockout_seconds', $lockoutSeconds, 'integer', (int)$admin['id']);
            admin_setting_set('security.registration_enabled', $registrationEnabled, 'boolean', (int)$admin['id']);
            admin_setting_set('security.wallet_signups_enabled', $walletSignupsEnabled, 'boolean', (int)$admin['id']);
            admin_setting_set('security.notice_banner', $notice, 'text', (int)$admin['id']);

            $siteSettings = site_status_settings();
            $maintenanceRequested = isset($_POST['maintenance_mode']);
            if ($maintenanceRequested && (string)$siteSettings['site_mode'] !== 'maintenance') {
                $pdo->exec("UPDATE site_settings SET site_mode = 'maintenance' WHERE id = 1");
            } elseif (!$maintenanceRequested && (string)$siteSettings['site_mode'] === 'maintenance') {
                $pdo->exec("UPDATE site_settings SET site_mode = 'full_launch' WHERE id = 1");
            }

            admin_audit((int)$admin['id'], 'update_security_settings', 'security_center', 'settings', [
                'admin_failed_login_threshold' => $threshold,
                'admin_lockout_seconds' => $lockoutSeconds,
                'registration_enabled' => $registrationEnabled,
                'wallet_signups_enabled' => $walletSignupsEnabled,
                'maintenance_mode' => $maintenanceRequested,
                'notice_banner_set' => $notice !== '',
            ]);
            $msg = 'Security settings updated.';
        } elseif ($action === 'add_watchlist') {
            $targetType = (string)($_POST['target_type'] ?? 'ip_hash');
            $matchType = (string)($_POST['match_type'] ?? 'contains');
            $status = (string)($_POST['status'] ?? 'active');
            $severity = (string)($_POST['severity'] ?? 'medium');
            $pattern = trim((string)($_POST['pattern'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));
            if (!in_array($targetType, ['ip_hash', 'user_agent'], true) || !in_array($matchType, ['contains', 'exact', 'regex'], true) || !in_array($status, ['active', 'inactive'], true) || !in_array($severity, ['info', 'low', 'medium', 'high', 'critical'], true)) {
                throw new RuntimeException('Invalid watchlist option.');
            }
            if ($pattern === '') {
                throw new RuntimeException('Watchlist pattern is required.');
            }
            $stmt = $pdo->prepare("INSERT INTO security_watchlist (target_type, pattern, match_type, status, severity, notes, created_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$targetType, substr($pattern, 0, 512), $matchType, $status, $severity, $notes, (int)$admin['id']]);
            admin_audit((int)$admin['id'], 'add_security_watchlist_entry', 'security_watchlist', (string)$pdo->lastInsertId(), ['target_type' => $targetType, 'match_type' => $matchType, 'severity' => $severity]);
            $msg = 'Watchlist entry added.';
        } elseif ($action === 'delete_watchlist') {
            $id = (int)($_POST['watchlist_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM security_watchlist WHERE id = ?");
            $stmt->execute([$id]);
            admin_audit((int)$admin['id'], 'delete_security_watchlist_entry', 'security_watchlist', (string)$id);
            $msg = 'Watchlist entry removed.';
        } elseif ($action === 'lock_admin' || $action === 'unlock_admin') {
            $targetAdminId = (int)($_POST['admin_id'] ?? 0);
            if ($targetAdminId === (int)$admin['id'] && $action === 'lock_admin') {
                throw new RuntimeException('You cannot lock your own active admin account.');
            }
            if ($action === 'lock_admin') {
                $targetStmt = $pdo->prepare("SELECT role, is_active FROM admin_users WHERE id = ? LIMIT 1");
                $targetStmt->execute([$targetAdminId]);
                $target = $targetStmt->fetch();
                if ($target && (string)($target['role'] ?? '') === 'super_admin' && (int)($target['is_active'] ?? 0) === 1 && admin_super_admin_count($pdo) <= 1) {
                    throw new RuntimeException('Cannot lock the last active Super Admin.');
                }
            }
            $activeValue = $action === 'unlock_admin' ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE admin_users SET is_active = ? WHERE id = ?");
            $stmt->execute([$activeValue, $targetAdminId]);
            if ($action === 'lock_admin' && security_table_exists($pdo, 'admin_sessions')) {
                $revoke = $pdo->prepare("UPDATE admin_sessions SET revoked_at = UTC_TIMESTAMP(), revoked_by_admin_id = ?, revoke_reason = 'admin_account_locked' WHERE admin_user_id = ? AND revoked_at IS NULL");
                $revoke->execute([(int)$admin['id'], $targetAdminId]);
            }
            admin_audit((int)$admin['id'], $action, 'admin_user', (string)$targetAdminId);
            $msg = $action === 'unlock_admin' ? 'Admin account unlocked.' : 'Admin account locked and active sessions revoked.';
        } elseif ($action === 'reset_admin_password') {
            $targetAdminId = (int)($_POST['admin_id'] ?? 0);
            $password = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');
            if (strlen($password) < 12 || !hash_equals($password, $confirm)) {
                throw new RuntimeException('New password must be at least 12 characters and match confirmation.');
            }
            $stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $targetAdminId]);
            if (security_table_exists($pdo, 'admin_sessions')) {
                $revoke = $pdo->prepare("UPDATE admin_sessions SET revoked_at = UTC_TIMESTAMP(), revoked_by_admin_id = ?, revoke_reason = 'admin_password_reset' WHERE admin_user_id = ? AND revoked_at IS NULL");
                $revoke->execute([(int)$admin['id'], $targetAdminId]);
            }
            admin_audit((int)$admin['id'], 'reset_admin_password', 'admin_user', (string)$targetAdminId);
            $msg = 'Admin password reset and active sessions revoked.';
        } elseif ($action === 'change_own_password') {
            $current = (string)($_POST['current_password'] ?? '');
            $password = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');
            $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$admin['id']]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($current, (string)$row['password_hash'])) {
                throw new RuntimeException('Current password is not valid.');
            }
            if (strlen($password) < 12 || !hash_equals($password, $confirm)) {
                throw new RuntimeException('New password must be at least 12 characters and match confirmation.');
            }
            $stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), (int)$admin['id']]);
            admin_audit((int)$admin['id'], 'change_own_admin_password', 'admin_user', (string)$admin['id']);
            $msg = 'Your admin password was changed.';
        } elseif ($action === 'force_logout_session') {
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT session_id_hash FROM admin_sessions WHERE id = ? LIMIT 1");
            $stmt->execute([$sessionId]);
            $row = $stmt->fetch();
            if ($row && hash_equals((string)$row['session_id_hash'], security_current_session_hash())) {
                throw new RuntimeException('Use the normal Logout button to end your current session.');
            }
            $stmt = $pdo->prepare("UPDATE admin_sessions SET revoked_at = UTC_TIMESTAMP(), revoked_by_admin_id = ?, revoke_reason = 'forced_by_security_center' WHERE id = ? AND revoked_at IS NULL");
            $stmt->execute([(int)$admin['id'], $sessionId]);
            admin_audit((int)$admin['id'], 'force_logout_admin_session', 'admin_sessions', (string)$sessionId);
            $msg = 'Admin session revoked.';
        } elseif ($action === 'force_logout_others') {
            $stmt = $pdo->prepare("UPDATE admin_sessions SET revoked_at = UTC_TIMESTAMP(), revoked_by_admin_id = ?, revoke_reason = 'force_logout_all_except_current' WHERE session_id_hash <> ? AND revoked_at IS NULL");
            $stmt->execute([(int)$admin['id'], security_current_session_hash()]);
            admin_audit((int)$admin['id'], 'force_logout_all_admins_except_current', 'admin_sessions', 'all');
            $msg = 'All other active admin sessions were revoked.';
        } elseif ($action === 'clear_expired_sessions') {
            $deleted = $pdo->exec("DELETE FROM admin_sessions WHERE expires_at < UTC_TIMESTAMP() OR revoked_at IS NOT NULL");
            admin_audit((int)$admin['id'], 'clear_expired_admin_sessions', 'admin_sessions', 'expired', ['deleted' => (int)$deleted]);
            $msg = 'Expired/revoked admin sessions cleared.';
        } else {
            throw new RuntimeException('Unknown Security Center action.');
        }
    } catch (Throwable $e) {
        wallet_log_error('security center action failed: ' . $e->getMessage());
        $err = $e->getMessage();
    }
}

$siteSettings = site_status_settings();
$loginAttempts = security_count($pdo, 'admin_security_events', "event_type = 'admin_login_attempt'");
$failedLogins = security_count($pdo, 'admin_security_events', "event_type = 'admin_login_failed'");
$successfulLogins = security_count($pdo, 'admin_security_events', "event_type = 'admin_login_success'");
$activeSessions = security_count($pdo, 'admin_sessions', "revoked_at IS NULL AND expires_at >= UTC_TIMESTAMP()");
$adminRows = security_admins($pdo);
$currentSessionHash = security_current_session_hash();
$threshold = security_setting_int_clamped('security.admin_failed_login_threshold', 6, 1, 50);
$lockoutSeconds = security_setting_int_clamped('security.admin_lockout_seconds', 900, 60, 86400);
$registrationEnabled = admin_setting_bool('security.registration_enabled', true);
$walletSignupsEnabled = admin_setting_bool('security.wallet_signups_enabled', true);
$securityNotice = (string)admin_setting_get('security.notice_banner', '');

$securityEvents = security_table_exists($pdo, 'admin_security_events')
    ? $pdo->query("SELECT * FROM admin_security_events ORDER BY id DESC LIMIT 100")->fetchAll()
    : [];
$adminSessions = security_table_exists($pdo, 'admin_sessions')
    ? $pdo->query("SELECT s.*, a.username FROM admin_sessions s LEFT JOIN admin_users a ON a.id = s.admin_user_id ORDER BY s.last_seen_at DESC LIMIT 100")->fetchAll()
    : [];
$watchlistRows = security_table_exists($pdo, 'security_watchlist')
    ? $pdo->query("SELECT * FROM security_watchlist ORDER BY id DESC LIMIT 100")->fetchAll()
    : [];
$suspiciousRows = security_table_exists($pdo, 'bot_events')
    ? $pdo->query("SELECT created_at, bot_name, bot_type, event_type, threat_level, url, ip_hash, user_agent FROM bot_events WHERE threat_level IN ('medium','high','critical') OR event_type IN ('login_probe','404_probe') ORDER BY id DESC LIMIT 100")->fetchAll()
    : [];
$probeRows = security_table_exists($pdo, 'bot_events')
    ? $pdo->query("SELECT created_at, event_type, threat_level, url, ip_hash, user_agent FROM bot_events WHERE event_type IN ('login_probe','404_probe') ORDER BY id DESC LIMIT 100")->fetchAll()
    : [];
$errorRows = security_table_exists($pdo, 'site_errors')
    ? $pdo->query("SELECT created_at, level, message, url, ip_hash FROM site_errors ORDER BY id DESC LIMIT 50")->fetchAll()
    : [];
$auditRows = security_table_exists($pdo, 'admin_audit_log')
    ? $pdo->query("SELECT a.created_at, au.username, a.action, a.target_type, a.target_id, a.ip_address FROM admin_audit_log a LEFT JOIN admin_users au ON au.id = a.admin_user_id ORDER BY a.id DESC LIMIT 100")->fetchAll()
    : [];

$phpVersionOk = version_compare(PHP_VERSION, '8.1.0', '>=');
$mysqlVersion = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
$mysqlVersionOk = preg_match('/(\d+\.\d+\.\d+)/', $mysqlVersion, $m) ? version_compare($m[1], '8.0.0', '>=') || stripos($mysqlVersion, 'mariadb') !== false : true;
$displayErrorsOff = !filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN);
$sessionSecure = ini_get('session.cookie_secure') === '1';
$sessionHttpOnly = ini_get('session.cookie_httponly') === '1';
$sessionSameSite = (string)ini_get('session.cookie_samesite');
$csrfTtl = (int)(wallet_config()['app']['csrf_token_ttl_seconds'] ?? 7200);

$publicConfigWarnings = [];
foreach (['/config.php', '/.env', '/config.example.php', '/wallet/config.php'] as $relative) {
    if (is_file(__DIR__ . '/..' . $relative)) {
        $publicConfigWarnings[] = $relative;
    }
}
$installerWarnings = [];
foreach (['/install.php', '/install.sql', '/run_migrations.php', '/migrations/001_admin_panel_foundation.sql'] as $relative) {
    if (is_file(__DIR__ . '/..' . $relative)) {
        $installerWarnings[] = $relative;
    }
}
$adminRouteWarnings = [];
foreach (glob(__DIR__ . '/*.php') ?: [] as $adminFile) {
    $base = basename($adminFile);
    if (in_array($base, ['login.php', 'verify-sms.php', 'verify-authenticator.php', 'logout.php'], true)) {
        continue;
    }
    $contents = (string)file_get_contents($adminFile);
    if (!str_contains($contents, 'admin_require_user()')) {
        $adminRouteWarnings[] = '/admin/' . $base;
    }
}
$permissionWarnings = [];
$privateConfig = '/home/hobbyhashcoin/hobbyhash-clean/wallet/config.php';
if (is_file($privateConfig) && (fileperms($privateConfig) & 0x0004)) {
    $permissionWarnings[] = 'Private wallet config is world-readable.';
}
$logDir = __DIR__ . '/../logs';
if (is_dir($logDir) && (fileperms($logDir) & 0x0002)) {
    $permissionWarnings[] = 'Public logs directory is world-writable.';
}

render_admin_header('Security Center', ['Security Center']);
?>
<?php if ($msg): ?><?php admin_render_alert('success', $msg); ?><?php endif; ?>
<?php if ($err): ?><?php admin_render_alert('error', $err); ?><?php endif; ?>

<div class="admin-grid admin-grid-tight">
  <?= admin_stat_card('Admin Login Attempts', number_format($loginAttempts), 'info') ?>
  <?= admin_stat_card('Failed Logins', number_format($failedLogins), $failedLogins > 0 ? 'warn' : 'ok') ?>
  <?= admin_stat_card('Successful Logins', number_format($successfulLogins), 'ok') ?>
  <?= admin_stat_card('Active Admin Sessions', number_format($activeSessions), $activeSessions > 1 ? 'warn' : 'info') ?>
  <?= admin_stat_card('Admin TOTP Policy', totp_setting_enabled('admin_totp_required') ? 'Required when enabled' : 'Optional', totp_setting_enabled('admin_totp_required') ? 'ok' : 'warn') ?>
  <?= admin_stat_card('Wallet Signups', $walletSignupsEnabled ? 'Enabled' : 'Disabled', $walletSignupsEnabled ? 'ok' : 'warn') ?>
</div>

<div class="admin-card">
  <h3>Security Controls</h3>
  <p>These controls preserve the existing login flow. TOTP remains optional per admin unless the site-wide admin authenticator requirement is enabled.</p>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="security_settings">
    <label>Failed Admin Login Threshold<br><input type="number" name="admin_failed_login_threshold" min="1" max="50" value="<?= h((string)$threshold) ?>"></label><br><br>
    <label>Lockout Duration Seconds<br><input type="number" name="admin_lockout_seconds" min="60" max="86400" value="<?= h((string)$lockoutSeconds) ?>"></label><br><br>
    <label><input type="checkbox" name="maintenance_mode" <?= (string)$siteSettings['site_mode'] === 'maintenance' ? 'checked' : '' ?>> Enable maintenance mode</label><br>
    <label><input type="checkbox" name="registration_enabled" <?= $registrationEnabled ? 'checked' : '' ?>> Enable registration globally</label><br>
    <label><input type="checkbox" name="wallet_signups_enabled" <?= $walletSignupsEnabled ? 'checked' : '' ?>> Enable public custodial wallet signups</label><br><br>
    <label>Security Notice / Banner Text<br><textarea name="notice_banner" rows="3" maxlength="2000"><?= h($securityNotice) ?></textarea></label><br><br>
    <button type="submit" data-confirm="Save Security Center settings? These settings affect admin lockouts and public signup availability.">Save Security Settings</button>
  </form>
</div>

<div class="admin-grid">
  <div class="admin-card">
    <h3>Admin Password Change</h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="change_own_password">
      <label>Current Password<br><input type="password" name="current_password" required autocomplete="current-password"></label><br><br>
      <label>New Password<br><input type="password" name="new_password" required minlength="12" autocomplete="new-password"></label><br><br>
      <label>Confirm New Password<br><input type="password" name="confirm_password" required minlength="12" autocomplete="new-password"></label><br><br>
      <button type="submit" data-confirm="Change your admin password?">Change My Password</button>
    </form>
  </div>
  <div class="admin-card">
    <h3>Reset Admin Password Flow</h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="reset_admin_password">
      <label>Admin Account<br><select name="admin_id">
        <?php foreach ($adminRows as $row): ?><option value="<?= h((string)$row['id']) ?>"><?= h((string)$row['username']) ?> (<?= h((string)$row['email']) ?>)</option><?php endforeach; ?>
      </select></label><br><br>
      <label>New Temporary Password<br><input type="password" name="new_password" required minlength="12" autocomplete="new-password"></label><br><br>
      <label>Confirm Password<br><input type="password" name="confirm_password" required minlength="12" autocomplete="new-password"></label><br><br>
      <button type="submit" data-confirm="Reset this admin password and revoke that admin's active sessions?">Reset Password</button>
    </form>
  </div>
</div>

<div class="admin-card">
  <h3>Admin Accounts</h3>
  <?php admin_filter_box('Filter admins'); ?>
  <?php admin_render_table(['Admin', 'Email', 'Status', 'SMS', 'TOTP', 'Controls'], array_map(static function (array $row) use ($admin): array {
      $id = (int)$row['id'];
      $active = (int)$row['is_active'] === 1;
      $buttonAction = $active ? 'lock_admin' : 'unlock_admin';
      $buttonLabel = $active ? 'Lock account' : 'Unlock account';
      $disabled = $id === (int)$admin['id'] && $active ? ' disabled' : '';
      $form = '<form method="post" class="inline-form">'
          . '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'
          . '<input type="hidden" name="action" value="' . h($buttonAction) . '">'
          . '<input type="hidden" name="admin_id" value="' . h((string)$id) . '">'
          . '<button type="submit"' . $disabled . ' data-confirm="' . h($buttonLabel . ' for ' . (string)$row['username'] . '?') . '">' . h($buttonLabel) . '</button></form>';
      return [
          h((string)$row['username']),
          h((string)$row['email']),
          $active ? '<span class="ok">Active</span>' : '<span class="warn">Locked</span>',
          ((int)$row['sms_2fa_enabled'] === 1) ? '<span class="ok">Enabled</span>' : '<span class="warn">Off</span>',
          ((int)$row['totp_enabled'] === 1) ? '<span class="ok">Enabled</span>' : '<span class="warn">Optional/Off</span>',
          $form,
      ];
  }, $adminRows), 'No admins found', 'No admin users exist.'); ?>
</div>

<div class="admin-card">
  <h3>Active Admin Sessions</h3>
  <div class="admin-actions">
    <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="force_logout_others"><button type="submit" data-confirm="Force logout all other admin sessions?">Force Logout All Except Current</button></form>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="clear_expired_sessions"><button type="submit" data-confirm="Clear expired and revoked admin sessions?">Clear Expired Sessions</button></form>
  </div>
  <?php admin_render_table(['Admin', 'Created', 'Last Seen', 'Expires', 'IP Hash', 'User Agent', 'Status', 'Control'], array_map(static function (array $row) use ($currentSessionHash): array {
      $current = hash_equals((string)$row['session_id_hash'], $currentSessionHash);
      $revoked = !empty($row['revoked_at']);
      $form = $current || $revoked ? ($current ? '<span class="ok">Current session</span>' : '<span class="warn">Revoked</span>') :
          '<form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="force_logout_session"><input type="hidden" name="session_id" value="' . h((string)$row['id']) . '"><button type="submit" data-confirm="Force logout this admin session?">Force logout</button></form>';
      return [
          h((string)($row['username'] ?? 'unknown')),
          admin_h_datetime($row['created_at'] ?? null),
          admin_h_utc_datetime($row['last_seen_at'] ?? null),
          admin_h_utc_datetime($row['expires_at'] ?? null),
          '<code>' . h(substr((string)$row['ip_hash'], 0, 16)) . '...</code>',
          h(substr((string)$row['user_agent'], 0, 120)),
          $revoked ? '<span class="warn">Revoked</span>' : '<span class="ok">Active</span>',
          $form,
      ];
  }, $adminSessions), 'No tracked admin sessions', 'Admin session tracking starts after the Security Center migration is applied and admins log in.'); ?>
</div>

<div class="admin-card">
  <h3>IP Hash / User-Agent Watchlist</h3>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="add_watchlist">
    <label>Target Type<br><select name="target_type"><option value="ip_hash">IP hash</option><option value="user_agent">User-agent</option></select></label><br><br>
    <label>Match Type<br><select name="match_type"><option value="contains">Contains</option><option value="exact">Exact</option><option value="regex">Regex</option></select></label><br><br>
    <label>Severity<br><select name="severity"><option>info</option><option>low</option><option selected>medium</option><option>high</option><option>critical</option></select></label><br><br>
    <label>Status<br><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></label><br><br>
    <label>Pattern<br><input name="pattern" maxlength="512" required></label><br><br>
    <label>Notes<br><textarea name="notes" rows="2"></textarea></label><br><br>
    <button type="submit">Add Watchlist Entry</button>
  </form>
  <?php admin_render_table(['Type', 'Match', 'Pattern', 'Severity', 'Status', 'Notes', 'Control'], array_map(static function (array $row): array {
      return [
          h((string)$row['target_type']),
          h((string)$row['match_type']),
          '<code>' . h((string)$row['pattern']) . '</code>',
          h((string)$row['severity']),
          h((string)$row['status']),
          h(substr((string)$row['notes'], 0, 160)),
          '<form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="delete_watchlist"><input type="hidden" name="watchlist_id" value="' . h((string)$row['id']) . '"><button type="submit" data-confirm="Delete this watchlist entry?">Delete</button></form>',
      ];
  }, $watchlistRows), 'No watchlist entries', 'Add IP hash or user-agent watchlist entries above.'); ?>
</div>

<div class="admin-grid">
  <div class="admin-card">
    <h3>Protection Status</h3>
    <div class="admin-table-wrap"><table class="admin-table"><tbody>
      <tr><th>CSRF Protection</th><td><?= security_badge($csrfTtl > 0, 'Enabled') ?> token TTL <?= h((string)$csrfTtl) ?> seconds</td></tr>
      <tr><th>Session Cookie Secure</th><td><?= security_badge($sessionSecure, 'Secure', 'Not secure') ?></td></tr>
      <tr><th>Session Cookie HttpOnly</th><td><?= security_badge($sessionHttpOnly, 'HttpOnly', 'Review') ?></td></tr>
      <tr><th>Session SameSite</th><td><?= h($sessionSameSite !== '' ? $sessionSameSite : 'not set') ?></td></tr>
      <tr><th>Error Display</th><td><?= security_badge($displayErrorsOff, 'Off', 'On') ?></td></tr>
      <tr><th>PHP Version</th><td><?= security_badge($phpVersionOk, PHP_VERSION, PHP_VERSION . ' review') ?></td></tr>
      <tr><th>MySQL Version</th><td><?= security_badge($mysqlVersionOk, $mysqlVersion, $mysqlVersion . ' review') ?></td></tr>
    </tbody></table></div>
  </div>
  <div class="admin-card">
    <h3>Exposure Checks</h3>
    <div class="admin-table-wrap"><table class="admin-table"><tbody>
      <tr><th>Public Config Files</th><td><?= $publicConfigWarnings === [] ? '<span class="ok">No exposed public config files detected</span>' : '<span class="warn">' . h(implode(', ', $publicConfigWarnings)) . '</span>' ?></td></tr>
      <tr><th>Installer/Migration Files</th><td><?= $installerWarnings === [] ? '<span class="ok">No public installer/migration files detected</span>' : '<span class="warn">' . h(implode(', ', $installerWarnings)) . '</span>' ?></td></tr>
      <tr><th>Admin Route Guards</th><td><?= $adminRouteWarnings === [] ? '<span class="ok">Admin pages checked for auth guard</span>' : '<span class="warn">' . h(implode(', ', $adminRouteWarnings)) . '</span>' ?></td></tr>
      <tr><th>File Permissions</th><td><?= $permissionWarnings === [] ? '<span class="ok">No obvious warnings detected</span>' : '<span class="warn">' . h(implode(' ', $permissionWarnings)) . '</span>' ?></td></tr>
    </tbody></table></div>
  </div>
</div>

<div class="admin-card">
  <h3>Admin Login Attempts</h3>
  <?php admin_render_table(['When', 'Event', 'Admin ID', 'Username Attempted', 'IP Hash', 'User Agent'], array_map(static fn(array $row): array => [
      admin_h_datetime($row['created_at'] ?? null),
      h((string)$row['event_type']),
      h((string)($row['admin_user_id'] ?? '')),
      h((string)($row['username_attempted'] ?? '')),
      '<code>' . h(substr((string)($row['ip_hash'] ?? ''), 0, 16)) . '...</code>',
      h(substr((string)($row['user_agent'] ?? ''), 0, 140)),
  ], $securityEvents), 'No admin security events', 'Admin login attempts will appear after login activity.'); ?>
</div>

<div class="admin-card">
  <h3>Suspicious Request Log</h3>
  <?php admin_render_table(['When', 'Bot', 'Type', 'Event', 'Threat', 'URL', 'IP Hash', 'User Agent'], array_map(static fn(array $row): array => [
      admin_h_datetime($row['created_at'] ?? null),
      h((string)($row['bot_name'] ?? '')),
      h((string)($row['bot_type'] ?? '')),
      h((string)$row['event_type']),
      h((string)$row['threat_level']),
      h(substr((string)$row['url'], 0, 160)),
      '<code>' . h(substr((string)$row['ip_hash'], 0, 16)) . '...</code>',
      h(substr((string)$row['user_agent'], 0, 140)),
  ], $suspiciousRows), 'No suspicious requests', 'Suspicious bot and probe events will appear here when detected.'); ?>
</div>

<div class="admin-card">
  <h3>404 / Probe Log</h3>
  <?php admin_render_table(['When', 'Event', 'Threat', 'URL', 'IP Hash', 'User Agent'], array_map(static fn(array $row): array => [
      admin_h_datetime($row['created_at'] ?? null),
      h((string)$row['event_type']),
      h((string)$row['threat_level']),
      h(substr((string)$row['url'], 0, 180)),
      '<code>' . h(substr((string)$row['ip_hash'], 0, 16)) . '...</code>',
      h(substr((string)$row['user_agent'], 0, 160)),
  ], $probeRows), 'No 404/probe events', '404 probes and login probes will appear here from bot analytics.'); ?>
</div>

<div class="admin-card">
  <h3>Recent Site Errors</h3>
  <?php admin_render_table(['When', 'Level', 'Message', 'URL', 'IP Hash'], array_map(static fn(array $row): array => [
      admin_h_datetime($row['created_at'] ?? null),
      h((string)$row['level']),
      h(substr((string)$row['message'], 0, 180)),
      h(substr((string)$row['url'], 0, 160)),
      '<code>' . h(substr((string)$row['ip_hash'], 0, 16)) . '...</code>',
  ], $errorRows), 'No site errors logged', 'Server-side error records will appear when captured.'); ?>
</div>

<div class="admin-card">
  <h3>Security Audit Timeline</h3>
  <div class="admin-actions"><a class="admin-action admin-action-secondary" href="<?= h(admin_url('/security.php?export=security_logs')) ?>">Export Security Logs CSV</a></div>
  <?php admin_render_table(['When', 'Admin', 'Action', 'Target', 'IP'], array_map(static fn(array $row): array => [
      admin_h_datetime($row['created_at'] ?? null),
      h((string)($row['username'] ?? 'system')),
      h((string)$row['action']),
      h(trim((string)$row['target_type'] . '#' . (string)$row['target_id'], '#')),
      h((string)$row['ip_address']),
  ], $auditRows), 'No audit rows', 'Admin actions will appear here.'); ?>
</div>

<?php render_admin_footer(); ?>
