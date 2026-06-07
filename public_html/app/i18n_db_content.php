<?php
declare(strict_types=1);

/**
 * DB-backed public content translation (announcements, CMS docs, support bodies).
 *
 * Admin continues to edit English in primary tables. On publish, translations are
 * generated and cached in i18n_content_translations. Public pages read locale rows.
 *
 * Batch backfill: php jobs/i18n_translate_db.php --table=announcements --locale=es
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n_catalog.php';
require_once __DIR__ . '/i18n_translate_google.php';
require_once __DIR__ . '/i18n_translate_mask.php';

final class HobcI18nDbContent
{
    /** @var array<string, list<string>> */
    public const TRANSLATABLE_TABLES = [
        'announcements' => ['title', 'body', 'seo_title', 'seo_description'],
        'docs_pages' => ['title', 'body', 'seo_title', 'seo_description', 'category'],
        'downloads' => ['title', 'description'],
        'burn_events' => ['title', 'public_notes'],
        'treasury_reserve_categories' => ['name', 'notes'],
        'treasury_reserve_movements' => ['notes'],
    ];

    private static bool $schemaReady = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        try {
            $pdo = wallet_db();
            $exists = $pdo->query("SHOW TABLES LIKE 'i18n_content_translations'")->fetchColumn();
            if (!$exists) {
                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS i18n_content_translations (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        entity_table VARCHAR(64) NOT NULL,
                        entity_id BIGINT UNSIGNED NOT NULL,
                        locale VARCHAR(16) NOT NULL,
                        field_name VARCHAR(64) NOT NULL,
                        field_value MEDIUMTEXT NOT NULL,
                        source_hash CHAR(64) NOT NULL DEFAULT \'\',
                        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY uq_i18n_content (entity_table, entity_id, locale, field_name),
                        KEY idx_i18n_content_lookup (entity_table, entity_id, locale)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
            }
            self::$schemaReady = true;
        } catch (Throwable $e) {
            wallet_log_error('i18n_content_translations schema ensure failed: ' . $e->getMessage());
        }
    }

    public static function sourceHash(string $text): string
    {
        return hash('sha256', trim($text));
    }

    /**
     * Read translated field for current (or given) locale; falls back to English source.
     */
    public static function field(string $table, int $entityId, string $field, string $sourceText, ?string $locale = null): string
    {
        $sourceText = trim($sourceText);
        if ($sourceText === '') {
            return '';
        }

        $locale = $locale ?? (function_exists('hobc_i18n_locale') ? hobc_i18n_locale() : 'en');
        $default = function_exists('hobc_i18n_default_locale') ? hobc_i18n_default_locale() : 'en';
        if ($locale === $default) {
            return $sourceText;
        }

        if (!isset(self::TRANSLATABLE_TABLES[$table]) || !in_array($field, self::TRANSLATABLE_TABLES[$table], true)) {
            return $sourceText;
        }

        self::ensureSchema();

        try {
            $stmt = wallet_db()->prepare(
                'SELECT field_value, source_hash FROM i18n_content_translations
                 WHERE entity_table = ? AND entity_id = ? AND locale = ? AND field_name = ?
                 LIMIT 1'
            );
            $stmt->execute([$table, $entityId, $locale, $field]);
            $row = $stmt->fetch();
            if ($row && trim((string)$row['field_value']) !== '') {
                $hash = (string)($row['source_hash'] ?? '');
                if ($hash === '' || $hash === self::sourceHash($sourceText)) {
                    return (string)$row['field_value'];
                }
            }
        } catch (Throwable $e) {
            wallet_log_error('i18n db field read failed: ' . $e->getMessage());
        }

        return $sourceText;
    }

    /**
     * @param array<string, mixed> $row Must include `id` and translatable fields.
     */
    public static function localizeRow(string $table, array $row, ?string $locale = null): array
    {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0 || !isset(self::TRANSLATABLE_TABLES[$table])) {
            return $row;
        }

        foreach (self::TRANSLATABLE_TABLES[$table] as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }
            $source = (string)$row[$field];
            $row[$field] = self::field($table, $id, $field, $source, $locale);
        }

        return $row;
    }

    public static function translateField(string $text, string $targetLocale, string $sourceLocale = 'en'): string
    {
        $text = trim($text);
        if ($text === '' || $targetLocale === $sourceLocale) {
            return $text;
        }

        try {
            [$masked, $tokens] = hobc_translate_mask_string($text);
            $client = new HobcGoogleTranslateClient();
            $translated = $client->translateBatch([$masked], $targetLocale, $sourceLocale)[0] ?? '';
            $unmasked = hobc_translate_unmask_string($translated, $tokens);

            return $unmasked !== '' ? $unmasked : $text;
        } catch (Throwable $e) {
            wallet_log_error('i18n db translateField failed: ' . $e->getMessage());
            return $text;
        }
    }

    public static function upsertTranslation(string $table, int $entityId, string $locale, string $field, string $sourceText, string $translatedText): void
    {
        self::ensureSchema();

        $stmt = wallet_db()->prepare(
            'INSERT INTO i18n_content_translations (entity_table, entity_id, locale, field_name, field_value, source_hash)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE field_value = VALUES(field_value), source_hash = VALUES(source_hash), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            $table,
            $entityId,
            $locale,
            $field,
            $translatedText,
            self::sourceHash($sourceText),
        ]);
    }

    /**
     * Whether a DB row is visible on the public site and should be translated.
     *
     * @param array<string, mixed> $row
     */
    public static function isRowPublic(string $table, array $row): bool
    {
        return match ($table) {
            'announcements' => (string)($row['status'] ?? '') === 'published',
            'docs_pages' => (string)($row['status'] ?? '') === 'published',
            'downloads' => (string)($row['status'] ?? '') === 'published',
            'burn_events' => !empty($row['is_published'])
                && in_array((string)($row['status'] ?? ''), ['completed', 'confirmed'], true),
            'treasury_reserve_categories' => !empty($row['is_public']),
            'treasury_reserve_movements' => !empty($row['is_public']),
            default => false,
        };
    }

    /**
     * After admin save: fetch row and translate if public.
     */
    public static function adminPublish(string $table, int $entityId): void
    {
        if ($entityId <= 0 || !isset(self::TRANSLATABLE_TABLES[$table])) {
            return;
        }

        try {
            $stmt = wallet_db()->prepare('SELECT * FROM `' . str_replace('`', '', $table) . '` WHERE id = ? LIMIT 1');
            $stmt->execute([$entityId]);
            $row = $stmt->fetch();
            if (!is_array($row)) {
                return;
            }
            self::onContentPublished($table, $row);
        } catch (Throwable $e) {
            wallet_log_error('i18n adminPublish failed: ' . $e->getMessage());
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function localizeRows(string $table, array $rows, ?string $locale = null): array
    {
        foreach ($rows as $i => $row) {
            if (is_array($row)) {
                $rows[$i] = self::localizeRow($table, $row, $locale);
            }
        }

        return $rows;
    }

    public static function onContentPublished(string $table, array $row): void
    {
        if (!isset(self::TRANSLATABLE_TABLES[$table])) {
            return;
        }

        $entityId = (int)($row['id'] ?? 0);
        if ($entityId <= 0 || !self::isRowPublic($table, $row)) {
            return;
        }

        try {
            HobcGoogleTranslateClient::validateConfiguration();
        } catch (Throwable $e) {
            wallet_log_error('i18n db publish skipped (no Google creds): ' . $e->getMessage());
            return;
        }

        self::syncRowTranslations($table, $entityId, $row);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function syncRowTranslations(string $table, int $entityId, array $row, ?array $locales = null): int
    {
        if (!isset(self::TRANSLATABLE_TABLES[$table])) {
            return 0;
        }

        self::ensureSchema();

        $locales = $locales ?? array_values(array_filter(
            hobc_i18n_supported_locales(),
            static fn(string $code): bool => $code !== hobc_i18n_default_locale()
        ));

        $default = hobc_i18n_default_locale();
        $written = 0;

        foreach ($locales as $locale) {
            if ($locale === $default) {
                continue;
            }
            foreach (self::TRANSLATABLE_TABLES[$table] as $field) {
                $source = trim((string)($row[$field] ?? ''));
                if ($source === '') {
                    continue;
                }
                $translated = self::translateField($source, $locale, $default);
                if ($translated !== '' && $translated !== $source) {
                    self::upsertTranslation($table, $entityId, $locale, $field, $source, $translated);
                    $written++;
                }
            }
        }

        return $written;
    }
}

function hobc_i18n_db_field(string $table, int $entityId, string $field, string $sourceText, ?string $locale = null): string
{
    return HobcI18nDbContent::field($table, $entityId, $field, $sourceText, $locale);
}

function hobc_i18n_db_row(string $table, array $row, ?string $locale = null): array
{
    return HobcI18nDbContent::localizeRow($table, $row, $locale);
}

function hobc_i18n_db_rows(string $table, array $rows, ?string $locale = null): array
{
    return HobcI18nDbContent::localizeRows($table, $rows, $locale);
}

function hobc_i18n_admin_publish(string $table, int $entityId): void
{
    HobcI18nDbContent::adminPublish($table, $entityId);
}
