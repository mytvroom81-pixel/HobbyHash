<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function csrf_token(): string
{
    wallet_start_session();
    $created = (int)($_SESSION['csrf_created_at'] ?? 0);
    $ttl = (int)(wallet_config()['app']['csrf_token_ttl_seconds'] ?? 7200);
    if (empty($_SESSION['csrf_token']) || $created <= 0 || (time() - $created) > $ttl) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_created_at'] = time();
    }
    return $_SESSION['csrf_token'];
}

function csrf_validate_or_fail(): void
{
    wallet_start_session();
    $token = $_POST['csrf_token'] ?? '';
    $known = $_SESSION['csrf_token'] ?? '';
    $created = (int)($_SESSION['csrf_created_at'] ?? 0);
    $ttl = (int)(wallet_config()['app']['csrf_token_ttl_seconds'] ?? 7200);
    $valid = is_string($token) && is_string($known) && hash_equals($known, $token);

    if (!$valid || $created <= 0 || (time() - $created) > $ttl) {
        wallet_log_error('csrf validation failed: posted=' . ($token !== '' ? 'yes' : 'no')
            . ' session=' . ($known !== '' ? 'yes' : 'no')
            . ' created=' . ($created > 0 ? 'yes' : 'no')
            . ' age=' . ($created > 0 ? (string)(time() - $created) : 'none')
            . ' path=' . (string)($_SERVER['SCRIPT_NAME'] ?? 'unknown'));
        http_response_code(403);
        echo 'CSRF validation failed. Please go back, refresh the page, and try again.';
        exit;
    }
}
