<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

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
    $stmt = wallet_db()->prepare(
        "INSERT INTO admin_audit_log (admin_user_id, action, target_type, target_id, details_json, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $adminId,
        $action,
        $targetType,
        $targetId,
        json_encode($details, JSON_UNESCAPED_SLASHES),
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
    ]);
}
