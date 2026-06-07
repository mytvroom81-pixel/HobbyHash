<?php
declare(strict_types=1);

require_once __DIR__ . '/i18n_catalog.php';
require_once __DIR__ . '/i18n_translate_generator.php';

final class HobcI18nAudit
{
    /** @var list<string> */
    private array $publicRoots = [];

    /** @var list<string> */
    private array $adminRoots = [];

    public function __construct()
    {
        $root = dirname(__DIR__);
        $this->publicRoots = [
            $root,
            $root . '/about',
            $root . '/mining',
            $root . '/pool/main',
            $root . '/pool/nano',
            $root . '/explorer',
            $root . '/stats',
            $root . '/downloads',
            $root . '/docs',
            $root . '/launch-reserve',
            $root . '/burn',
            $root . '/exchange-listing',
            $root . '/contact',
            $root . '/privacy',
            $root . '/terms',
            $root . '/wallet',
            $root . '/roadmap',
        ];
        $this->adminRoots = [
            $root . '/admin',
        ];
    }

    /**
     * @return array{
     *   duplicate_ui_keys:list<string>,
     *   duplicate_js_keys:list<string>,
     *   duplicate_page_keys:list<string>,
     *   unused_ui_keys:list<string>,
     *   unused_js_keys:list<string>,
     *   unused_page_keys:list<string>,
     *   missing_locale_keys:array<string,array<string,int>>,
     *   hardcoded_findings:list<array{file:string,line:int,text:string}>,
     *   admin_accidental:list<string>
     * }
     */
    public function run(): array
    {
        return [
            'duplicate_ui_keys' => $this->duplicateKeys(hobc_i18n_load_json_catalog('en', 'ui')),
            'duplicate_js_keys' => $this->duplicateKeys(hobc_i18n_load_json_catalog('en', 'js')),
            'duplicate_page_keys' => $this->duplicatePageKeys(),
            'unused_ui_keys' => $this->unusedUiKeys(),
            'unused_js_keys' => $this->unusedJsKeys(),
            'unused_page_keys' => $this->unusedPageKeys(),
            'missing_locale_keys' => $this->missingLocaleKeys(),
            'hardcoded_findings' => $this->scanHardcodedPublicStrings(),
            'admin_accidental' => $this->scanAdminForI18nHooks(),
        ];
    }

    /**
     * @param array<string,string> $catalog
     * @return list<string>
     */
    private function duplicateKeys(array $catalog): array
    {
        $values = [];
        $dupes = [];
        foreach ($catalog as $key => $value) {
            $norm = strtolower(trim($value));
            if ($norm === '') {
                continue;
            }
            if (isset($values[$norm]) && $values[$norm] !== $key) {
                $dupes[] = $key . ' ~= ' . $values[$norm];
            }
            $values[$norm] = $key;
        }
        return array_values(array_unique($dupes));
    }

    /** @return list<string> */
    private function duplicatePageKeys(): array
    {
        $dupes = [];
        $dir = hobc_i18n_lang_root() . '/en/pages';
        if (!is_dir($dir)) {
            return [];
        }
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $page = basename($file, '.json');
            foreach ($this->duplicateKeys(hobc_i18n_load_page_catalog('en', $page)) as $dupe) {
                $dupes[] = $page . ': ' . $dupe;
            }
        }
        return $dupes;
    }

    /** @return list<string> */
    private function unusedUiKeys(): array
    {
        $keys = array_keys(hobc_i18n_load_json_catalog('en', 'ui'));
        $used = $this->collectUsedKeys('/\bhobc_t(?:e)?\(\s*[\'"]([^\'"]+)/');
        return array_values(array_diff($keys, $used));
    }

    /** @return list<string> */
    private function unusedJsKeys(): array
    {
        $keys = array_keys(hobc_i18n_load_json_catalog('en', 'js'));
        $used = $this->collectUsedKeys('/HOBC_I18N\.strings(?:\[[\'"]([^\'"]+)[\'"]\]|\.([a-zA-Z0-9_.]+))/');
        $used = array_merge($used, $this->collectUsedKeys('/\bhobcI18n\(\s*[\'"]([^\'"]+)/'));
        return array_values(array_diff($keys, array_unique($used)));
    }

    /** @return list<string> */
    private function unusedPageKeys(): array
    {
        $unused = [];
        $dir = hobc_i18n_lang_root() . '/en/pages';
        if (!is_dir($dir)) {
            return [];
        }
        $used = $this->collectUsedPageKeys();
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $page = basename($file, '.json');
            foreach (array_keys(hobc_i18n_load_page_catalog('en', $page)) as $key) {
                $full = $page . '.' . $key;
                if (!in_array($full, $used, true) && !in_array($key, $used, true)) {
                    $unused[] = $full;
                }
            }
        }
        sort($unused);
        return $unused;
    }

    /** @return list<string> */
    private function collectUsedKeys(string $pattern): array
    {
        $used = [];
        foreach ($this->publicPhpFiles() as $file) {
            $content = (string)@file_get_contents($file);
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $key) {
                    if ($key !== '') {
                        $used[] = $key;
                    }
                }
                if (isset($matches[2])) {
                    foreach ($matches[2] as $key) {
                        if ($key !== '') {
                            $used[] = $key;
                        }
                    }
                }
            }
        }
        return array_values(array_unique($used));
    }

    /** @return list<string> */
    private function collectUsedPageKeys(): array
    {
        $used = [];
        $literalPattern = '/\bhobc_tp(?:e)?\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/';
        $variablePattern = '/\bhobc_tp(?:e)?\(\s*\$pageId\s*,\s*[\'"]([^\'"]+)[\'"]/';
        foreach ($this->publicPhpFiles() as $file) {
            $content = (string)@file_get_contents($file);
            if (preg_match_all($literalPattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $used[] = $match[1] . '.' . $match[2];
                    $used[] = $match[2];
                }
            }
            $pageId = null;
            if (preg_match('/\$pageId\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $pageMatch) === 1) {
                $pageId = $pageMatch[1];
            }
            if ($pageId !== null && preg_match_all($variablePattern, $content, $varMatches)) {
                foreach ($varMatches[1] as $key) {
                    $used[] = $pageId . '.' . $key;
                    $used[] = $key;
                }
            }
        }
        return array_values(array_unique($used));
    }

    /** @return array<string,array<string,int>> */
    private function missingLocaleKeys(): array
    {
        $catalogs = (new HobcI18nTranslateGenerator())->discoverSourceCatalogs();
        $missing = [];
        foreach (hobc_i18n_supported_locales() as $locale) {
            if ($locale === hobc_i18n_default_locale()) {
                continue;
            }
            $count = 0;
            foreach ($catalogs as $relativePath) {
                $english = hobc_i18n_load_json_catalog('en', str_starts_with($relativePath, 'pages/') ? 'ui' : explode('/', $relativePath)[0]);
                if (str_starts_with($relativePath, 'pages/')) {
                    $page = basename($relativePath, '.json');
                    $english = hobc_i18n_load_page_catalog('en', $page);
                    $existing = hobc_i18n_load_page_catalog($locale, $page);
                } else {
                    $english = hobc_i18n_load_json_catalog('en', basename($relativePath, '.json'));
                    $existing = hobc_i18n_load_json_catalog($locale, basename($relativePath, '.json'));
                }
                foreach ($english as $key => $value) {
                    if (!array_key_exists($key, $existing) || trim((string)$existing[$key]) === '') {
                        $count++;
                    }
                }
            }
            $missing[$locale] = ['missing' => $count];
        }
        return $missing;
    }

    /** @return list<array{file:string,line:int,text:string}> */
    private function scanHardcodedPublicStrings(): array
    {
        $findings = [];
        $patterns = [
            '/>([^<>{}\n][^<>{}\n]{3,})</',
            '/(?:title|placeholder|aria-label|alt)=["\']([^"\']{4,})["\']/i',
            '/\$pageTitle\s*=\s*[\'"]([^\'"]+)[\'"]/',
            '/\$pageDescription\s*=\s*[\'"]([^\'"]+)[\'"]/',
            '/\$err\s*=\s*[\'"]([^\'"]+)[\'"]/',
        ];
        foreach ($this->publicPhpFiles() as $file) {
            $lines = file($file) ?: [];
            foreach ($lines as $i => $line) {
                if (str_contains($line, 'hobc_t(') || str_contains($line, 'hobc_tp(') || str_contains($line, 'hobc_te(') || str_contains($line, 'hobc_tpe(')) {
                    continue;
                }
                if (preg_match('/require|include|function |class |\/\/|\$/', $line) === 1 && !preg_match('/\$pageTitle|\$pageDescription|\$err/', $line)) {
                    continue;
                }
                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $line, $matches)) {
                        foreach ($matches[1] as $text) {
                            $text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                            if ($this->isLikelyHardcodedPublicText($text, $line)) {
                                $findings[] = [
                                    'file' => str_replace(dirname(__DIR__) . '/', '', $file),
                                    'line' => $i + 1,
                                    'text' => mb_substr($text, 0, 120),
                                ];
                            }
                        }
                    }
                }
            }
        }
        return $findings;
    }

    private function isLikelyHardcodedPublicText(string $text, string $line): bool
    {
        if ($text === '' || strlen($text) < 4) {
            return false;
        }
        if (preg_match('/^(hobc1|bc1|https?:\/\/|\/api\/|\/admin\/|index\.php|[a-f0-9]{16,}|\d+)$/i', $text) === 1) {
            return false;
        }
        if (preg_match('/^(HOBC|hobbyhashd|hobbyhash-cli|SHA-?256|stratum\+tcp|mysql|SELECT|INSERT|require_once)/i', $text) === 1) {
            return false;
        }
        if (preg_match('/hobc_t|hobc_tp|hobc_public_setting|admin_|wallet_db|SELECT |INSERT |mailer_support_html|adminNotify|adminText|adminHtml/', $line) === 1) {
            return false;
        }
        if (str_contains($text, 'A new support ticket was created from the public website')) {
            return false;
        }
        if (!preg_match('/[A-Za-z]{3,}/', $text)) {
            return false;
        }
        return preg_match('/\b(the|and|for|with|your|wallet|pool|support|download|guide|status|home|mining|explorer)\b/i', $text) === 1
            || preg_match('/^[A-Z][a-z]+(\s+[A-Za-z]+){1,}/', $text) === 1;
    }

    /** @return list<string> */
    private function scanAdminForI18nHooks(): array
    {
        $hits = [];
        foreach (glob(dirname(__DIR__) . '/admin/**/*.php') ?: [] as $file) {
            $content = (string)@file_get_contents($file);
            if (str_contains($content, 'hobc_t(') || str_contains($content, 'hobc_tp(') || str_contains($content, 'hobc_i18n_bootstrap')) {
                $hits[] = str_replace(dirname(__DIR__) . '/', '', $file);
            }
        }
        return $hits;
    }

    /** @return list<string> */
    private function publicPhpFiles(): array
    {
        $files = [];
        $root = dirname(__DIR__);
        $candidates = [
            $root . '/index.php',
            $root . '/contact.php',
            $root . '/ticket.php',
            $root . '/includes/header.php',
            $root . '/includes/footer.php',
            $root . '/includes/nav.php',
            $root . '/includes/status-bar.php',
            $root . '/includes/language-switcher.php',
            $root . '/app/view.php',
            $root . '/app/site_status.php',
        ];
        foreach ($this->publicRoots as $dir) {
            if (is_file($dir . '/index.php')) {
                $candidates[] = $dir . '/index.php';
            }
            if ($dir === $root . '/docs' && is_dir($dir)) {
                foreach (glob($dir . '/*/index.php') ?: [] as $docFile) {
                    $candidates[] = $docFile;
                }
            }
        }
        foreach ($candidates as $file) {
            if (is_file($file)) {
                $files[] = $file;
            }
        }
        sort($files);
        return array_values(array_unique($files));
    }

    public function renderReport(array $report): void
    {
        $this->log('Duplicate UI value keys: ' . count($report['duplicate_ui_keys']));
        foreach (array_slice($report['duplicate_ui_keys'], 0, 20) as $line) {
            $this->log('  duplicate-ui: ' . $line);
        }
        $this->log('Duplicate JS value keys: ' . count($report['duplicate_js_keys']));
        $this->log('Duplicate page value keys: ' . count($report['duplicate_page_keys']));
        $this->log('Unused UI keys: ' . count($report['unused_ui_keys']));
        foreach (array_slice($report['unused_ui_keys'], 0, 30) as $key) {
            $this->log('  unused-ui: ' . $key);
        }
        $this->log('Unused JS keys: ' . count($report['unused_js_keys']));
        $this->log('Unused page keys: ' . count($report['unused_page_keys']));
        foreach (array_slice($report['unused_page_keys'], 0, 30) as $key) {
            $this->log('  unused-page: ' . $key);
        }
        foreach ($report['missing_locale_keys'] as $locale => $data) {
            $this->log('[' . $locale . '] missing translated keys=' . ($data['missing'] ?? 0));
        }
        $this->log('Hardcoded public strings still found: ' . count($report['hardcoded_findings']));
        foreach (array_slice($report['hardcoded_findings'], 0, 40) as $finding) {
            $this->log('  hardcoded: ' . $finding['file'] . ':' . $finding['line'] . ' -> ' . $finding['text']);
        }
        $this->log('Admin files accidentally including i18n: ' . count($report['admin_accidental']));
        foreach ($report['admin_accidental'] as $file) {
            $this->log('  admin-accidental: ' . $file);
        }
    }

    private function log(string $message): void
    {
        fwrite(STDOUT, '[' . gmdate('Y-m-d H:i:s') . '] ' . $message . PHP_EOL);
    }
}
