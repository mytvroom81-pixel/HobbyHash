<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function admin_required_database_objects(): array
{
    return [
        'schema_migrations',
        'admin_audit_log',
        'site_pageviews',
        'site_visitors',
        'bot_events',
        'admin_security_events',
        'site_errors',
        'system_health_snapshots',
        'admin_settings',
        'announcements',
        'burn_events',
        'treasury_reserve_categories',
        'treasury_reserve_movements',
        'downloads',
        'download_events',
        'support_messages',
        'rate_limit_events',
        'bot_rules',
        'bot_notes',
        'bot_rule_hits',
        'admin_sessions',
        'security_watchlist',
        'wallet_user_holds',
        'wallet_admin_notes',
        'docs_pages',
    ];
}

function admin_database_object_exists(PDO $pdo, string $name): bool
{
    $stmt = $pdo->query("SHOW FULL TABLES LIKE " . $pdo->quote($name));
    return (bool)$stmt->fetch();
}

function admin_missing_database_objects(PDO $pdo): array
{
    $missing = [];
    foreach (admin_required_database_objects() as $object) {
        if (!admin_database_object_exists($pdo, $object)) {
            $missing[] = $object;
        }
    }
    return $missing;
}

function admin_migration_status(PDO $pdo): array
{
    $missing = admin_missing_database_objects($pdo);
    $applied = [];
    if (admin_database_object_exists($pdo, 'schema_migrations')) {
        $rows = $pdo->query("SELECT migration, applied_at FROM schema_migrations ORDER BY id ASC")->fetchAll();
        foreach ($rows as $row) {
            $applied[] = [
                'migration' => (string)$row['migration'],
                'applied_at' => (string)$row['applied_at'],
            ];
        }
    }

    return [
        'ok' => $missing === [],
        'missing' => $missing,
        'applied' => $applied,
        'required_count' => count(admin_required_database_objects()),
        'present_count' => count(admin_required_database_objects()) - count($missing),
    ];
}
