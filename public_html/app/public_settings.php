<?php
declare(strict_types=1);

require_once __DIR__ . '/settings.php';

function hobc_public_setting(string $key, mixed $default = null): mixed
{
    try {
        return getSetting($key, $default);
    } catch (Throwable $e) {
        wallet_log_error('public setting read failed: ' . $e->getMessage());
        return $default;
    }
}

function hobc_public_setting_bool(string $key, bool $default = true): bool
{
    return (bool)hobc_public_setting($key, $default);
}

function hobc_public_setting_text(string $key, string $default = ''): string
{
    return (string)hobc_public_setting($key, $default);
}

function hobc_public_asset_url(string $key, string $default): string
{
    $value = trim(hobc_public_setting_text($key, $default));
    if ($value === '') {
        return $default;
    }
    if (preg_match('#^(https?://|/)#i', $value) !== 1) {
        return $default;
    }
    return $value;
}

function hobc_public_feature_disabled(string $key): bool
{
    return !hobc_public_setting_bool($key, true);
}

function hobc_public_table_exists(string $table): bool
{
    try {
        $stmt = wallet_db()->query("SHOW TABLES LIKE " . wallet_db()->quote($table));
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function hobc_public_fetch_all(string $sql, array $params = []): array
{
    try {
        $stmt = wallet_db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        wallet_log_error('public content read failed: ' . $e->getMessage());
        return [];
    }
}
