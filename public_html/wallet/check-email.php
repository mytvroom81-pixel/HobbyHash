<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/account_security.php';

header('Content-Type: application/json; charset=utf-8');

$email = strtolower(trim((string)($_GET['email'] ?? '')));
try {
    $status = account_email_status($email);
    echo json_encode($status, JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    wallet_log_error('email check failed: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Email check is temporarily unavailable.'], JSON_UNESCAPED_SLASHES);
}
