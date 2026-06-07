<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

function totp_ensure_schema(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo = wallet_db();
    $adminColumns = [
        'totp_secret' => "ALTER TABLE admin_users ADD COLUMN totp_secret VARCHAR(64) NULL AFTER sms_2fa_enabled",
        'totp_enabled' => "ALTER TABLE admin_users ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret",
    ];
    foreach ($adminColumns as $column => $sql) {
        $stmt = $pdo->query("SHOW COLUMNS FROM admin_users LIKE " . $pdo->quote($column));
        if (!$stmt->fetch()) {
            $pdo->exec($sql);
        }
    }

    $settingColumns = [
        'admin_totp_required' => "ALTER TABLE wallet_settings ADD COLUMN admin_totp_required TINYINT(1) NOT NULL DEFAULT 0 AFTER wallet_sms_withdrawal_required",
        'wallet_totp_login_required' => "ALTER TABLE wallet_settings ADD COLUMN wallet_totp_login_required TINYINT(1) NOT NULL DEFAULT 0 AFTER admin_totp_required",
        'wallet_totp_withdrawal_required' => "ALTER TABLE wallet_settings ADD COLUMN wallet_totp_withdrawal_required TINYINT(1) NOT NULL DEFAULT 0 AFTER wallet_totp_login_required",
    ];
    foreach ($settingColumns as $column => $sql) {
        $stmt = $pdo->query("SHOW COLUMNS FROM wallet_settings LIKE " . $pdo->quote($column));
        if (!$stmt->fetch()) {
            $pdo->exec($sql);
        }
    }

    $ensured = true;
}

function totp_base32_encode(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    for ($i = 0, $len = strlen($bytes); $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    for ($i = 0, $len = strlen($bits); $i < $len; $i += 5) {
        $chunk = substr($bits, $i, 5);
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        }
        $out .= $alphabet[bindec($chunk)];
    }
    return $out;
}

function totp_base32_decode(string $secret): string
{
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret));
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
        $pos = strpos($alphabet, $secret[$i]);
        if ($pos === false) {
            continue;
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    for ($i = 0, $len = strlen($bits) - 7; $i < $len; $i += 8) {
        $out .= chr(bindec(substr($bits, $i, 8)));
    }
    return $out;
}

function totp_generate_secret(): string
{
    return totp_base32_encode(random_bytes(20));
}

function totp_code(string $secret, ?int $time = null): string
{
    $time = $time ?? time();
    $counter = intdiv($time, 30);
    $key = totp_base32_decode($secret);
    $binCounter = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $binCounter, $key, true);
    $offset = ord($hash[19]) & 0x0f;
    $value = (
        ((ord($hash[$offset]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    ) % 1000000;
    return str_pad((string)$value, 6, '0', STR_PAD_LEFT);
}

function totp_verify(string $secret, string $code, int $window = 1): bool
{
    $code = preg_replace('/\D+/', '', $code);
    if ($code === '' || strlen($code) !== 6) {
        return false;
    }
    $now = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($secret, $now + ($i * 30)), $code)) {
            return true;
        }
    }
    return false;
}

function totp_setting_enabled(string $setting): bool
{
    totp_ensure_schema();
    $allowed = ['admin_totp_required', 'wallet_totp_login_required', 'wallet_totp_withdrawal_required'];
    if (!in_array($setting, $allowed, true)) {
        return false;
    }
    $stmt = wallet_db()->query("SELECT {$setting} FROM wallet_settings WHERE id = 1");
    $row = $stmt->fetch();
    return (bool)($row[$setting] ?? false);
}

function totp_admin_requires(array $admin): bool
{
    return totp_setting_enabled('admin_totp_required')
        && (bool)($admin['totp_enabled'] ?? false)
        && trim((string)($admin['totp_secret'] ?? '')) !== '';
}

function totp_user_requires_login(array $user): bool
{
    if (!totp_setting_enabled('wallet_totp_login_required')) {
        return false;
    }
    $stmt = wallet_db()->prepare("SELECT twofa_enabled, twofa_secret_encrypted FROM user_security WHERE user_id = ?");
    $stmt->execute([(int)$user['id']]);
    $row = $stmt->fetch();
    return (bool)($row['twofa_enabled'] ?? false) && trim((string)($row['twofa_secret_encrypted'] ?? '')) !== '';
}

function totp_user_requires_withdrawal(array $user): bool
{
    if (!totp_setting_enabled('wallet_totp_withdrawal_required')) {
        return false;
    }
    $stmt = wallet_db()->prepare("SELECT twofa_enabled, twofa_secret_encrypted FROM user_security WHERE user_id = ?");
    $stmt->execute([(int)$user['id']]);
    $row = $stmt->fetch();
    return (bool)($row['twofa_enabled'] ?? false) && trim((string)($row['twofa_secret_encrypted'] ?? '')) !== '';
}

function totp_user_secret(int $userId): string
{
    $stmt = wallet_db()->prepare("SELECT twofa_secret_encrypted FROM user_security WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return trim((string)($row['twofa_secret_encrypted'] ?? ''));
}

function totp_otpauth_uri(string $issuer, string $account, string $secret): string
{
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
        . '?secret=' . rawurlencode($secret)
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}

function totp_qr_svg(string $payload): string
{
    $qrencode = '/usr/bin/qrencode';
    if (!is_file($qrencode) || !is_executable($qrencode)) {
        throw new RuntimeException('QR generator unavailable.');
    }
    $process = proc_open([$qrencode, '-t', 'SVG', '-o', '-', '-m', '1', '-s', '5', $payload], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('QR generator unavailable.');
    }
    fclose($pipes[0]);
    $svg = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0 || !is_string($svg) || trim($svg) === '') {
        throw new RuntimeException('QR generator failed: ' . trim((string)$error));
    }
    return $svg;
}
