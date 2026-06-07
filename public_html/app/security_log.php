<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function audit_client_ip_address(): ?string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return $ip !== '' ? $ip : null;
}

function admin_audit_sanitize(mixed $value): mixed
{
    if (is_array($value)) {
        $clean = [];
        foreach ($value as $key => $item) {
            $keyText = strtolower((string)$key);
            if (preg_match('/(password|secret|seed|private[_-]?key|mnemonic|token|csrf|code|otp|totp|sms)/', $keyText)) {
                $clean[$key] = '[redacted]';
                continue;
            }
            $clean[$key] = admin_audit_sanitize($item);
        }
        return $clean;
    }

    if (is_string($value)) {
        return substr($value, 0, 2000);
    }

    return $value;
}

function security_log_event(?int $userId, string $eventType, string $severity = 'info', array $details = []): void
{
    $stmt = wallet_db()->prepare(
        "INSERT INTO security_event_log (user_id, event_type, severity, details_json, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $userId,
        $eventType,
        $severity,
        json_encode($details, JSON_UNESCAPED_SLASHES),
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
    ]);
}

function admin_audit(?int $adminId, string $action, ?string $targetType = null, ?string $targetId = null, array $details = []): void
{
    try {
        $stmt = wallet_db()->prepare(
            "INSERT INTO admin_audit_log (admin_user_id, action, target_type, target_id, details_json, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $adminId,
            substr($action, 0, 128),
            $targetType !== null ? substr($targetType, 0, 64) : null,
            $targetId !== null ? substr($targetId, 0, 128) : null,
            json_encode(admin_audit_sanitize($details), JSON_UNESCAPED_SLASHES),
            audit_client_ip_address(),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
        ]);
    } catch (Throwable $e) {
        wallet_log_error('admin audit write failed: ' . $e->getMessage());
    }
}
