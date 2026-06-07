<?php
declare(strict_types=1);

require_once __DIR__ . '/i18n_catalog.php';
require_once __DIR__ . '/i18n_translate_mask.php';
require_once __DIR__ . '/i18n_translate_google.php';

/**
 * Server-side translation file generator for public i18n catalogs.
 *
 * PHP CLI was chosen because the site is PHP-first, shares glossary/catalog helpers,
 * and avoids adding a Node build chain for a PHP include-based portal.
 */
final class HobcI18nTranslateGenerator
{
    private bool $force;
    private bool $dryRun;
    private ?HobcGoogleTranslateClient $client = null;
    /** @var array<string, array{translated:int,skipped:int,failed:int}> */
    private array $stats = [];

    public function __construct(bool $force = false, bool $dryRun = false)
    {
        $this->force = $force;
        $this->dryRun = $dryRun;
    }

    /**
     * @return list<string> Relative catalog paths like ui.json or pages/home.json
     */
    public function discoverSourceCatalogs(): array
    {
        $root = hobc_i18n_lang_root() . '/en';
        $catalogs = [];

        foreach (['ui.json', 'js.json'] as $file) {
            if (is_file($root . '/' . $file)) {
                $catalogs[] = $file;
            }
        }

        $pagesDir = $root . '/pages';
        if (is_dir($pagesDir)) {
            $files = glob($pagesDir . '/*.json') ?: [];
            sort($files);
            foreach ($files as $file) {
                $catalogs[] = 'pages/' . basename($file);
            }
        }

        return $catalogs;
    }

    /**
     * @return array<string, string>
     */
    public function loadEnglishCatalog(string $relativePath): array
    {
        $path = hobc_i18n_lang_root() . '/en/' . $relativePath;
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid English catalog JSON: ' . $relativePath);
        }
        /** @var array<string, string> $decoded */
        return array_map(static fn($v): string => (string)$v, $decoded);
    }

    /**
     * @return array<string, string>
     */
    public function loadLocaleCatalog(string $locale, string $relativePath): array
    {
        $path = hobc_i18n_lang_root() . '/' . $locale . '/' . $relativePath;
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '' || trim($raw) === '{}') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_map(static fn($v): string => (string)$v, $decoded) : [];
    }

    /**
     * @param array<string, string> $english
     * @param array<string, string> $existing
     * @return array<string, string>
     */
    public function keysNeedingTranslation(array $english, array $existing, string $relativePath = ''): array
    {
        if ($this->force) {
            return $english;
        }

        $checkPagePlaceholders = str_starts_with($relativePath, 'pages/');
        $checkUiPlaceholders = $relativePath === 'ui.json';
        $needed = [];
        foreach ($english as $key => $value) {
            $englishValue = trim($value);
            if (hobc_i18n_is_preserved_literal($englishValue)) {
                continue;
            }
            if (!array_key_exists($key, $existing) || trim((string)$existing[$key]) === '') {
                $needed[$key] = $value;
                continue;
            }

            $existingValue = (string)$existing[$key];
            if (str_starts_with($existingValue, '">') || str_starts_with($existingValue, '""')) {
                $needed[$key] = $value;
                continue;
            }

            $isEnPlaceholder = $englishValue !== '' && $existingValue === $value;
            if ($checkPagePlaceholders && strlen($englishValue) >= 3 && $isEnPlaceholder) {
                $needed[$key] = $value;
                continue;
            }
            if ($checkUiPlaceholders && str_starts_with($key, 'wallet.') && strlen($englishValue) >= 3 && $isEnPlaceholder) {
                $needed[$key] = $value;
            }
        }
        return $needed;
    }

    /**
     * @return list<string>
     */
    public function targetLocales(): array
    {
        $locales = hobc_i18n_supported_locales();
        $default = hobc_i18n_default_locale();
        return array_values(array_filter($locales, static fn(string $code): bool => $code !== $default));
    }

    public function runMissing(): int
    {
        return $this->runGeneration(false);
    }

    public function runForce(): int
    {
        return $this->runGeneration(true);
    }

    public function runDryRun(): int
    {
        $this->dryRun = true;
        return $this->runGeneration($this->force);
    }

    private function runGeneration(bool $force): int
    {
        $this->force = $force;
        $this->stats = [];
        $catalogs = $this->discoverSourceCatalogs();
        if ($catalogs === []) {
            $this->log('No English source catalogs found under lang/en/.');
            return 1;
        }

        if (!$this->dryRun) {
            HobcGoogleTranslateClient::validateConfiguration();
            $this->client = new HobcGoogleTranslateClient();
            $this->log('Auth mode: ' . $this->client->authMode() . ' | project=' . $this->client->projectId() . ' | location=' . $this->client->location());
        } else {
            $this->log('Dry-run mode: no Google API calls and no files will be written.');
        }

        $exitCode = 0;
        foreach ($this->targetLocales() as $locale) {
            foreach ($catalogs as $relativePath) {
                try {
                    $this->processCatalog($locale, $relativePath);
                } catch (Throwable $e) {
                    $exitCode = 1;
                    $this->stats[$locale . ':' . $relativePath] = [
                        'translated' => 0,
                        'skipped' => 0,
                        'failed' => 1,
                    ];
                    $this->log('[ERROR] ' . $locale . ' ' . $relativePath . ': ' . $e->getMessage());
                }
            }
        }

        $this->printSummary();
        return $exitCode;
    }

    /**
     * @param array<string, string> $english
     * @param array<string, string> $existing
     */
    private function processCatalog(string $locale, string $relativePath): void
    {
        $english = $this->loadEnglishCatalog($relativePath);
        $existing = $this->loadLocaleCatalog($locale, $relativePath);
        $needed = $this->keysNeedingTranslation($english, $existing, $relativePath);
        $skipped = count($english) - count($needed);

        $statKey = $locale . ':' . $relativePath;
        $this->stats[$statKey] = ['translated' => 0, 'skipped' => max(0, $skipped), 'failed' => 0];

        if ($needed === []) {
            $this->log('[' . $locale . '] ' . $relativePath . ': translated=0 skipped=' . $skipped . ' failed=0');
            return;
        }

        if ($this->dryRun) {
            $this->stats[$statKey]['translated'] = count($needed);
            $this->log('[' . $locale . '] ' . $relativePath . ': would_translate=' . count($needed) . ' skipped=' . $skipped . ' failed=0');
            return;
        }

        $translatedMap = $existing;
        foreach ($english as $key => $value) {
            if (!array_key_exists($key, $translatedMap)) {
                $translatedMap[$key] = '';
            }
        }

        $batchKeys = array_keys($needed);
        $batchSize = 50;
        for ($offset = 0; $offset < count($batchKeys); $offset += $batchSize) {
            $sliceKeys = array_slice($batchKeys, $offset, $batchSize);
            $maskedTexts = [];
            $tokenMaps = [];
            foreach ($sliceKeys as $key) {
                [$masked, $tokens] = hobc_translate_mask_string($needed[$key]);
                $maskedTexts[] = $masked;
                $tokenMaps[$key] = $tokens;
            }

            assert($this->client instanceof HobcGoogleTranslateClient);
            $results = $this->client->translateBatch($maskedTexts, $locale, hobc_i18n_default_locale());

            foreach ($sliceKeys as $i => $key) {
                $unmasked = hobc_translate_unmask_string($results[$i] ?? '', $tokenMaps[$key]);
                if ($unmasked === '') {
                    $this->stats[$statKey]['failed']++;
                    continue;
                }
                $translatedMap[$key] = $unmasked;
                $this->stats[$statKey]['translated']++;
            }
        }

        $this->writeCatalog($locale, $relativePath, $english, $translatedMap);
        $this->log(
            '[' . $locale . '] ' . $relativePath
            . ': translated=' . $this->stats[$statKey]['translated']
            . ' skipped=' . $this->stats[$statKey]['skipped']
            . ' failed=' . $this->stats[$statKey]['failed']
        );
    }

    /**
     * @param array<string, string> $englishOrder
     * @param array<string, string> $translated
     */
    private function writeCatalog(string $locale, string $relativePath, array $englishOrder, array $translated): void
    {
        $ordered = [];
        foreach ($englishOrder as $key => $englishValue) {
            if (array_key_exists($key, $translated) && trim($translated[$key]) !== '') {
                $ordered[$key] = hobc_i18n_is_preserved_literal((string)$englishValue)
                    ? (string)$englishValue
                    : $translated[$key];
            }
        }

        $path = hobc_i18n_lang_root() . '/' . $locale . '/' . $relativePath;
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create translation directory: ' . $dir);
        }

        $json = json_encode($ordered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Unable to encode translation JSON for ' . $locale . '/' . $relativePath);
        }
        $json .= "\n";

        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, $json) === false) {
            throw new RuntimeException('Unable to write translation file: ' . $path);
        }
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to finalize translation file: ' . $path);
        }
    }

    public function runCheck(): int
    {
        $catalogs = $this->discoverSourceCatalogs();
        $this->log('Translation check (public catalogs only)');
        $this->log('English source files: ' . implode(', ', $catalogs));

        $credOk = true;
        try {
            HobcGoogleTranslateClient::validateConfiguration();
            $client = new HobcGoogleTranslateClient();
            $this->log('Google credentials: OK (' . $client->authMode() . ')');
            $this->log('Project ID: ' . ($client->projectId() !== '' ? $client->projectId() : '(not set; required for v3 service-account translateText)'));
            $this->log('Location: ' . $client->location());
        } catch (Throwable $e) {
            $credOk = false;
            $this->log('[WARN] Google credentials: ' . $e->getMessage());
        }

        $totalMissing = 0;
        foreach ($this->targetLocales() as $locale) {
            $localeMissing = 0;
            foreach ($catalogs as $relativePath) {
                $english = $this->loadEnglishCatalog($relativePath);
                $existing = $this->loadLocaleCatalog($locale, $relativePath);
                $missing = count($this->keysNeedingTranslation($english, $existing, $relativePath));
                $localeMissing += $missing;
                $this->log('[' . $locale . '] ' . $relativePath . ': missing=' . $missing . ' total=' . count($english));
            }
            $totalMissing += $localeMissing;
            $this->log('[' . $locale . '] total missing=' . $localeMissing);
        }

        $this->log('All locales missing total=' . $totalMissing);

        require_once __DIR__ . '/i18n_audit.php';
        $audit = new HobcI18nAudit();
        $report = $audit->run();
        $audit->renderReport($report);

        $auditFailed = ($report['hardcoded_findings'] ?? []) !== []
            || ($report['admin_accidental'] ?? []) !== [];

        if ($auditFailed) {
            return 1;
        }

        if (!$credOk) {
            $this->log('Catalog checks passed. Google credentials not configured (translation commands require env vars).');
        }

        return 0;
    }

    public function exportKeys(?string $outputPath = null): int
    {
        $catalogs = $this->discoverSourceCatalogs();
        $export = [
            'generated_at' => gmdate('c'),
            'source_locale' => hobc_i18n_default_locale(),
            'catalogs' => [],
        ];

        foreach ($catalogs as $relativePath) {
            $export['catalogs'][$relativePath] = $this->loadEnglishCatalog($relativePath);
        }

        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Unable to encode export-keys JSON.');
        }
        $json .= "\n";

        if ($outputPath !== null && $outputPath !== '') {
            if (file_put_contents($outputPath, $json) === false) {
                throw new RuntimeException('Unable to write export file: ' . $outputPath);
            }
            $this->log('Exported English keys to ' . $outputPath);
            return 0;
        }

        echo $json;
        return 0;
    }

    private function printSummary(): void
    {
        $translated = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($this->stats as $row) {
            $translated += $row['translated'];
            $skipped += $row['skipped'];
            $failed += $row['failed'];
        }
        $this->log('Summary: translated=' . $translated . ' skipped=' . $skipped . ' failed=' . $failed);
    }

    private function log(string $message): void
    {
        fwrite(STDOUT, '[' . gmdate('Y-m-d H:i:s') . '] ' . $message . PHP_EOL);
    }
}
