<?php
declare(strict_types=1);

date_default_timezone_set('UTC');

const WALLET_ROOT = __DIR__ . '/..';

require_once __DIR__ . '/env.php';
hobc_load_env_file();

set_exception_handler(static function (Throwable $e): void {
    $line = '[' . gmdate('Y-m-d H:i:s') . '] Uncaught: ' . $e->getMessage() . PHP_EOL;
    @file_put_contents(WALLET_ROOT . '/logs/app_errors.log', $line, FILE_APPEND);
    http_response_code(503);
    echo 'Wallet backend unavailable. Please try again later.';
    exit;
});

function wallet_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $configPath = getenv('HOBC_WALLET_CONFIG');
    if (!$configPath) {
        $configPath = WALLET_ROOT . '/config.php';
        if (!is_file($configPath)) {
            $fallback = '/home/hobbyhashcoin/hobbyhash-clean/wallet/config.php';
            if (is_file($fallback)) {
                $configPath = $fallback;
            }
        }
    }

    if (!is_file($configPath)) {
        http_response_code(500);
        echo 'Wallet configuration missing.';
        exit;
    }

    /** @var array $cfg */
    $cfg = require $configPath;
    return $cfg;
}

function wallet_log_error(string $message): void
{
    $cfg = wallet_config();
    $logPath = $cfg['logs']['app_error_log'] ?? (WALLET_ROOT . '/logs/app_errors.log');
    $line = '[' . gmdate('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($logPath, $line, FILE_APPEND);
}

function wallet_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $cfg = wallet_config();
    $app = $cfg['app'];
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443'
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $secureCookie = (bool)($app['session_secure_cookie'] ?? true) && $isHttps;
    session_name($app['session_name'] ?? 'hobc_wallet_sess');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $secureCookie ? '1' : '0');
    ini_set('session.cookie_samesite', $app['session_samesite'] ?? 'Strict');
    ini_set('session.cookie_lifetime', '0');
    session_start();
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function wallet_is_maintenance_mode(): bool
{
    $stmt = wallet_db()->query("SELECT maintenance_mode FROM wallet_settings WHERE id = 1");
    $row = $stmt->fetch();
    return (bool)($row['maintenance_mode'] ?? false);
}

function wallet_redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function wallet_url(string $path = ''): string
{
    $base = rtrim((string)(wallet_config()['app']['base_url'] ?? '/wallet'), '/');
    $suffix = '/' . ltrim($path, '/');
    if ($path === '') {
        return $base;
    }
    return $base . $suffix;
}

function admin_url(string $path = ''): string
{
    $base = '/admin';
    $suffix = '/' . ltrim($path, '/');
    if ($path === '') {
        return $base;
    }
    return $base . $suffix;
}
