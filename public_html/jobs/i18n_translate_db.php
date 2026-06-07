#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Batch-translate DB-backed public content into i18n_content_translations.
 *
 * Usage:
 *   php jobs/i18n_translate_db.php --table=announcements --locale=es --limit=50
 *   php jobs/i18n_translate_db.php --table=announcements --all-locales
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/i18n_db_content.php';

$table = 'announcements';
$locale = '';
$limit = 50;
$allLocales = false;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--table=')) {
        $table = substr($arg, 8);
    } elseif (str_starts_with($arg, '--locale=')) {
        $locale = substr($arg, 9);
    } elseif (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int)substr($arg, 8));
    } elseif ($arg === '--all-locales') {
        $allLocales = true;
    }
}

if (!isset(HobcI18nDbContent::TRANSLATABLE_TABLES[$table])) {
    fwrite(STDERR, "Unsupported table: {$table}\n");
    exit(1);
}

try {
    HobcGoogleTranslateClient::validateConfiguration();
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

HobcI18nDbContent::ensureSchema();

$pdo = wallet_db();
$rows = $pdo->query('SELECT * FROM `' . str_replace('`', '', $table) . '` ORDER BY id DESC LIMIT ' . (int)$limit)->fetchAll();
if ($rows === []) {
    echo "No rows in {$table}.\n";
    exit(0);
}

$locales = $allLocales || $locale === ''
    ? array_values(array_filter(
        hobc_i18n_supported_locales(),
        static fn(string $code): bool => $code !== hobc_i18n_default_locale()
    ))
    : [$locale];

$written = 0;
foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }
    if (!HobcI18nDbContent::isRowPublic($table, $row)) {
        continue;
    }
    $written += HobcI18nDbContent::syncRowTranslations($table, (int)$row['id'], $row, $locales);
    echo 'Synced ' . $table . ' id=' . (int)$row['id'] . PHP_EOL;
}

echo "Done. translation fields written/updated: {$written}\n";
