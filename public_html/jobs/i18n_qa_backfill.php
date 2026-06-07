<?php
declare(strict_types=1);

/**
 * QA backfill: merge missing English page keys, fix corrupted prefixes, sync new keys to locales.
 * Run: php jobs/i18n_qa_backfill.php
 */

require_once __DIR__ . '/../app/i18n_catalog.php';

$root = dirname(__DIR__);
$pagesDir = $root . '/lang';

function qa_clean_value(string $value): string
{
    $value = preg_replace('/^">+/', '', $value) ?? $value;
    $value = preg_replace('/^""+/', '', $value) ?? $value;
    return $value;
}

function qa_merge_catalog(string $path, array $extra): int
{
    $existing = [];
    if (is_file($path)) {
        $decoded = json_decode((string)file_get_contents($path), true);
        if (is_array($decoded)) {
            foreach ($decoded as $key => $value) {
                $existing[$key] = qa_clean_value((string)$value);
            }
        }
    }

    $added = 0;
    foreach ($extra as $key => $value) {
        $value = qa_clean_value((string)$value);
        if (!array_key_exists($key, $existing) || $existing[$key] === '' || str_starts_with($existing[$key], '">') || str_starts_with($existing[$key], '""')) {
            if (!array_key_exists($key, $existing)) {
                $added++;
            }
            $existing[$key] = $value;
        }
    }

    ksort($existing);
    file_put_contents($path, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    return $added;
}

/** @var array<string, array<string, string>> $manual */
$manual = [];
$walletUiFile = __DIR__ . '/manual/wallet_ui.php';
foreach (glob(__DIR__ . '/manual/*.php') ?: [] as $file) {
    $pageId = basename($file, '.php');
    if (in_array($pageId, ['wallet_ui', 'pool_stats_i18n', 'pool_stats_js', 'wallet_auth', 'ticket'], true)) {
        continue;
    }
    $data = require $file;
    if (is_array($data)) {
        $manual[$pageId] = $data;
    }
}

$enAdded = 0;
foreach (['wallet_ui', 'pool_stats_i18n', 'wallet_auth'] as $uiBundle) {
    $uiFile = __DIR__ . '/manual/' . $uiBundle . '.php';
    if (!is_file($uiFile)) {
        continue;
    }
    $uiKeys = require $uiFile;
    if (is_array($uiKeys)) {
        $enAdded += qa_merge_catalog($pagesDir . '/en/ui.json', $uiKeys);
        echo 'EN ui.json: merged ' . count($uiKeys) . " $uiBundle keys\n";
    }
}

$ticketPageFile = __DIR__ . '/manual/ticket.php';
if (is_file($ticketPageFile)) {
    $ticketKeys = require $ticketPageFile;
    if (is_array($ticketKeys)) {
        $enAdded += qa_merge_catalog($pagesDir . '/en/pages/ticket.json', $ticketKeys);
        echo 'EN pages/ticket.json: merged ' . count($ticketKeys) . " ticket keys\n";
    }
}

$jsBundleFile = __DIR__ . '/manual/pool_stats_js.php';
if (is_file($jsBundleFile)) {
    $jsKeys = require $jsBundleFile;
    if (is_array($jsKeys)) {
        $enAdded += qa_merge_catalog($pagesDir . '/en/js.json', $jsKeys);
        echo 'EN js.json: merged ' . count($jsKeys) . " pool_stats_js keys\n";
    }
}

foreach ($manual as $pageId => $keys) {
    $path = $pagesDir . '/en/pages/' . $pageId . '.json';
    $enAdded += qa_merge_catalog($path, $keys);
    echo "EN $pageId: merged " . count($keys) . " manual keys\n";
}

require __DIR__ . '/merge_pool_page_keys.php';

$corrupted = 0;
$localeAdded = 0;
foreach (hobc_i18n_supported_locales() as $locale) {
    if ($locale === hobc_i18n_default_locale()) {
        continue;
    }
    $localeDir = $pagesDir . '/' . $locale . '/pages';
    if (!is_dir($localeDir)) {
        continue;
    }
    foreach (glob($localeDir . '/*.json') ?: [] as $file) {
        $pageId = basename($file, '.json');
        $enPath = $pagesDir . '/en/pages/' . $pageId . '.json';
        if (!is_file($enPath)) {
            continue;
        }
        $en = json_decode((string)file_get_contents($enPath), true);
        $loc = json_decode((string)file_get_contents($file), true);
        if (!is_array($en) || !is_array($loc)) {
            continue;
        }
        $changed = false;
        foreach ($loc as $key => $value) {
            $clean = qa_clean_value((string)$value);
            if ($clean !== $value) {
                $loc[$key] = $clean;
                $corrupted++;
                $changed = true;
            }
        }
        foreach ($en as $key => $value) {
            if (!array_key_exists($key, $loc) || trim((string)$loc[$key]) === '' || str_starts_with((string)$loc[$key], '">')) {
                $loc[$key] = (string)$value;
                if ($locale !== 'en') {
                    $localeAdded++;
                }
                $changed = true;
            }
        }
        if ($changed) {
            ksort($loc);
            file_put_contents($file, json_encode($loc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }
    }
}

// Sync new ui.json keys to locales (English placeholders until translate:missing).
$enUiPath = $pagesDir . '/en/ui.json';
if (is_file($enUiPath)) {
    $enUi = json_decode((string)file_get_contents($enUiPath), true);
    if (is_array($enUi)) {
        foreach (hobc_i18n_supported_locales() as $locale) {
            if ($locale === hobc_i18n_default_locale()) {
                continue;
            }
            $locUiPath = $pagesDir . '/' . $locale . '/ui.json';
            if (!is_file($locUiPath)) {
                continue;
            }
            $locUi = json_decode((string)file_get_contents($locUiPath), true);
            if (!is_array($locUi)) {
                continue;
            }
            $changed = false;
            foreach ($enUi as $key => $value) {
                if (!array_key_exists($key, $locUi) || trim((string)$locUi[$key]) === '') {
                    $locUi[$key] = (string)$value;
                    $localeAdded++;
                    $changed = true;
                }
            }
            if ($changed) {
                ksort($locUi);
                file_put_contents($locUiPath, json_encode($locUi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            }
        }
    }
}

echo "EN keys added/updated: $enAdded\n";
echo "Corrupted values cleaned: $corrupted\n";
echo "Locale placeholder keys added: $localeAdded (re-run composer translate:missing to translate)\n";
echo "QA backfill complete.\n";
