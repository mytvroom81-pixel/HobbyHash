<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function wallet_settings(): array
{
    $stmt = wallet_db()->query("SELECT * FROM wallet_settings WHERE id = 1");
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('wallet_settings row missing');
    }
    return $row;
}

function admin_settings_table_exists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = wallet_db()->query("SHOW TABLES LIKE 'admin_settings'");
        $exists = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $exists = false;
    }

    return $exists;
}

function admin_setting_get(string $key, mixed $default = null): mixed
{
    if (!isset($GLOBALS['admin_setting_runtime_cache']) || !is_array($GLOBALS['admin_setting_runtime_cache'])) {
        $GLOBALS['admin_setting_runtime_cache'] = [];
    }
    $cache = &$GLOBALS['admin_setting_runtime_cache'];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    if (!admin_settings_table_exists()) {
        return $default;
    }

    try {
        $stmt = wallet_db()->prepare("SELECT setting_value, setting_type FROM admin_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) {
            return $default;
        }

        $value = $row['setting_value'];
        $cache[$key] = match ((string)$row['setting_type']) {
            'boolean' => in_array((string)$value, ['1', 'true', 'yes', 'on'], true),
            'integer' => (int)$value,
            'decimal' => (float)$value,
            'json' => json_decode((string)$value, true) ?? $default,
            default => $value,
        };
        return $cache[$key];
    } catch (Throwable $e) {
        wallet_log_error('admin setting read failed: ' . $e->getMessage());
        return $default;
    }
}

function admin_setting_bool(string $key, bool $default = false): bool
{
    return (bool)admin_setting_get($key, $default);
}

function admin_setting_int(string $key, int $default = 0): int
{
    return (int)admin_setting_get($key, $default);
}

function admin_setting_set(string $key, mixed $value, string $type = 'string', ?int $adminId = null): void
{
    if (!admin_settings_table_exists()) {
        throw new RuntimeException('admin_settings table missing');
    }

    $stored = match ($type) {
        'boolean' => (bool)$value ? '1' : '0',
        'integer' => (string)(int)$value,
        'decimal' => (string)(float)$value,
        'json' => json_encode($value, JSON_UNESCAPED_SLASHES),
        default => (string)$value,
    };

    $stmt = wallet_db()->prepare(
        "INSERT INTO admin_settings (setting_key, setting_value, setting_type, updated_by)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            setting_type = VALUES(setting_type),
            updated_by = VALUES(updated_by)"
    );
    $stmt->execute([$key, $stored, $type, $adminId]);

    if (isset($GLOBALS['admin_setting_runtime_cache']) && is_array($GLOBALS['admin_setting_runtime_cache'])) {
        unset($GLOBALS['admin_setting_runtime_cache'][$key]);
    }
}

function getSetting(string $key, mixed $default = null): mixed
{
    return admin_setting_get($key, $default);
}

function updateSetting(string $key, mixed $value, string $type = 'string', ?int $adminId = null): void
{
    admin_setting_set($key, $value, $type, $adminId);
}
