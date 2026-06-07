<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function support_context_ensure_schema(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    try {
        $stmt = wallet_db()->query("SHOW COLUMNS FROM support_tickets LIKE 'source_context'");
        if (!$stmt->fetch()) {
            wallet_db()->exec("ALTER TABLE support_tickets ADD COLUMN source_context VARCHAR(190) NULL AFTER source");
        }
    } catch (Throwable $e) {
        wallet_log_error('support context schema check failed: ' . $e->getMessage());
    }

    $ensured = true;
}

function support_context_from_request(string $fallback): string
{
    $section = trim((string)($_POST['source_context'] ?? $_GET['section'] ?? $fallback));
    if ($section === '') {
        $section = $fallback;
    }
    return substr($section, 0, 190);
}
