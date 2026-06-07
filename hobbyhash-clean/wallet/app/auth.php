<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security_log.php';
require_once __DIR__ . '/throttle.php';

function auth_current_user(): ?array
{
    wallet_start_session();
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }
    $stmt = wallet_db()->prepare("SELECT id, username, email, phone_number, phone_verified_at, sms_2fa_enabled, is_active FROM users WHERE id = ?");
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch();
    if (!$row || !(bool)$row['is_active']) {
        auth_logout();
        return null;
    }
    return $row;
}

function auth_require_user(): array
{
    $u = auth_current_user();
    if (!$u) {
        wallet_redirect(wallet_url('/login.php'));
    }
    return $u;
}

function auth_login_user(array $user): void
{
    wallet_start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    unset($_SESSION['pending_user_2fa_user_id'], $_SESSION['pending_user_2fa_challenge_id']);

    $stmt = wallet_db()->prepare(
        "INSERT INTO sessions (user_id, php_session_id, csrf_token_hash, ip_address, user_agent, expires_at)
         VALUES (?, ?, ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 12 HOUR))"
    );
    $stmt->execute([
        (int)$user['id'],
        session_id(),
        hash('sha256', csrf_token()),
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
    ]);
    $security = wallet_db()->prepare(
        "UPDATE user_security
         SET last_login_at = UTC_TIMESTAMP(), last_login_ip = ?
         WHERE user_id = ?"
    );
    $security->execute([
        $_SERVER['REMOTE_ADDR'] ?? null,
        (int)$user['id'],
    ]);
    security_log_event((int)$user['id'], 'login_success', 'info');
}

function auth_logout(): void
{
    wallet_start_session();
    if (!empty($_SESSION['user_id'])) {
        $stmt = wallet_db()->prepare("UPDATE sessions SET is_revoked = 1 WHERE php_session_id = ?");
        $stmt->execute([session_id()]);
        security_log_event((int)$_SESSION['user_id'], 'logout', 'info');
    }
    unset(
        $_SESSION['user_id'],
        $_SESSION['pending_user_2fa_user_id'],
        $_SESSION['pending_user_2fa_challenge_id'],
        $_SESSION['pending_user_totp_user_id'],
        $_SESSION['pending_withdrawal_sms_challenge_id']
    );
}

function auth_try_login(string $login, string $password): bool
{
    $user = auth_verify_login_password($login, $password);
    if (!$user) {
        return false;
    }

    auth_login_user($user);
    return true;
}

function auth_verify_login_password(string $login, string $password): ?array
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = strtolower(trim($login)) . '|' . $ip;
    $max = (int)(wallet_config()['security']['max_login_attempts_per_15m'] ?? 8);
    if (!throttle_check_and_increment('login', $key, $max, 900)) {
        security_log_event(null, 'login_throttled', 'warning', ['login' => $login]);
        return null;
    }

    $stmt = wallet_db()->prepare("SELECT id, username, email, phone_number, phone_verified_at, sms_2fa_enabled, password_hash, is_active FROM users WHERE email = ? OR username = ? LIMIT 1");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();
    if (!$user || !(bool)$user['is_active']) {
        security_log_event(null, 'login_failed_user_missing', 'warning', ['login' => $login]);
        return null;
    }

    if (!password_verify($password, $user['password_hash'])) {
        security_log_event((int)$user['id'], 'login_failed_bad_password', 'warning');
        return null;
    }

    return $user;
}

function admin_current_user(): ?array
{
    wallet_start_session();
    $id = $_SESSION['admin_user_id'] ?? null;
    if (!$id) {
        return null;
    }
    $stmt = wallet_db()->prepare("SELECT id, username, email, phone_number, sms_2fa_enabled, totp_secret, totp_enabled, is_active FROM admin_users WHERE id = ?");
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch();
    if (!$row || !(bool)$row['is_active']) {
        admin_logout();
        return null;
    }
    return $row;
}

function admin_require_user(): array
{
    $u = admin_current_user();
    if (!$u) {
        wallet_redirect(admin_url('/login.php'));
    }
    return $u;
}

function admin_try_login(string $login, string $password): bool
{
    $admin = admin_verify_login_password($login, $password);
    if (!$admin) {
        return false;
    }

    admin_complete_login($admin);
    return true;
}

function admin_verify_login_password(string $login, string $password): ?array
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = strtolower(trim($login)) . '|' . $ip;
    if (!throttle_check_and_increment('admin_login', $key, 6, 900)) {
        return null;
    }

    $stmt = wallet_db()->prepare("SELECT id, username, email, phone_number, sms_2fa_enabled, totp_secret, totp_enabled, password_hash, is_active FROM admin_users WHERE email = ? OR username = ? LIMIT 1");
    $stmt->execute([$login, $login]);
    $admin = $stmt->fetch();
    if (!$admin || !(bool)$admin['is_active']) {
        return null;
    }
    if (!password_verify($password, $admin['password_hash'])) {
        return null;
    }

    return $admin;
}

function admin_complete_login(array $admin): void
{
    wallet_start_session();
    session_regenerate_id(true);
    $_SESSION['admin_user_id'] = (int)$admin['id'];
    unset($_SESSION['pending_admin_2fa_admin_id'], $_SESSION['pending_admin_2fa_challenge_id'], $_SESSION['pending_admin_totp_admin_id']);
    admin_audit((int)$admin['id'], 'admin_login', 'admin_user', (string)$admin['id']);
}

function admin_logout(): void
{
    wallet_start_session();
    if (!empty($_SESSION['admin_user_id'])) {
        admin_audit((int)$_SESSION['admin_user_id'], 'admin_logout', 'admin_user', (string)$_SESSION['admin_user_id']);
    }
    unset($_SESSION['admin_user_id'], $_SESSION['pending_admin_2fa_admin_id'], $_SESSION['pending_admin_2fa_challenge_id'], $_SESSION['pending_admin_totp_admin_id']);
}
