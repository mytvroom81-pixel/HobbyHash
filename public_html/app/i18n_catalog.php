<?php
declare(strict_types=1);

function hobc_i18n_config(): array
{
    static $config = null;
    if ($config === null) {
        /** @var array $loaded */
        $loaded = require __DIR__ . '/../config/i18n.php';
        $config = $loaded;
    }
    return $config;
}

function hobc_i18n_lang_root(): string
{
    return dirname(__DIR__) . '/lang';
}

function hobc_i18n_supported_locales(): array
{
    $languages = hobc_i18n_config()['languages'] ?? [];
    return array_keys($languages);
}

function hobc_i18n_default_locale(): string
{
    return (string)(hobc_i18n_config()['default_locale'] ?? 'en');
}

function hobc_i18n_normalize_locale(string $locale): string
{
    $locale = str_replace('_', '-', trim($locale));
    if ($locale === '') {
        return '';
    }

    $supported = hobc_i18n_supported_locales();
    if (in_array($locale, $supported, true)) {
        return $locale;
    }

    $lower = strtolower($locale);
    foreach ($supported as $code) {
        if (strtolower($code) === $lower) {
            return $code;
        }
    }

    $primary = strtolower(explode('-', $locale)[0]);
    $region = strtolower(explode('-', $locale)[1] ?? '');
    $tagMap = [
        'zh-hans' => 'zh-CN',
        'zh-hant' => 'zh-TW',
        'zh-cn' => 'zh-CN',
        'zh-tw' => 'zh-TW',
        'pt-br' => 'pt-BR',
    ];
    $tagKey = strtolower($locale);
    if (isset($tagMap[$tagKey]) && in_array($tagMap[$tagKey], $supported, true)) {
        return $tagMap[$tagKey];
    }
    if ($primary === 'zh' && $region !== '') {
        if (in_array($region, ['tw', 'hk', 'mo', 'hant'], true) && in_array('zh-TW', $supported, true)) {
            return 'zh-TW';
        }
        if (in_array($region, ['cn', 'sg', 'hans'], true) && in_array('zh-CN', $supported, true)) {
            return 'zh-CN';
        }
    }

    $primaryMap = [
        'pt' => 'pt-BR',
        'zh' => 'zh-CN',
        'fil' => 'tl',
        'tl' => 'tl',
    ];
    if (isset($primaryMap[$primary]) && in_array($primaryMap[$primary], $supported, true)) {
        return $primaryMap[$primary];
    }

    foreach ($supported as $code) {
        if (strtolower(explode('-', $code)[0]) === $primary) {
            return $code;
        }
    }

    return '';
}

function hobc_i18n_load_page_catalog(string $locale, string $page): array
{
    $path = hobc_i18n_lang_root() . '/' . $locale . '/pages/' . $page . '.json';
    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? array_map(static fn($v): string => (string)$v, $decoded) : [];
}

function hobc_i18n_page_lookup(string $page, string $key, ?string $locale = null): ?string
{
    $locale = $locale ?? hobc_i18n_locale();
    $defaultLocale = hobc_i18n_default_locale();
    $catalogs = [];

    if ($locale !== $defaultLocale) {
        $catalogs[] = hobc_i18n_load_page_catalog($locale, $page);
    }
    $catalogs[] = hobc_i18n_load_page_catalog($defaultLocale, $page);

    foreach ($catalogs as $catalog) {
        if (array_key_exists($key, $catalog)) {
            return (string)$catalog[$key];
        }
    }

    return null;
}

function hobc_i18n_all_catalog_keys(string $catalogType, ?string $page = null): array
{
    $root = hobc_i18n_lang_root() . '/en';
    if ($catalogType === 'ui') {
        return array_keys(hobc_i18n_load_json_catalog('en', 'ui'));
    }
    if ($catalogType === 'js') {
        return array_keys(hobc_i18n_load_json_catalog('en', 'js'));
    }
    if ($catalogType === 'page' && $page !== null) {
        return array_keys(hobc_i18n_load_page_catalog('en', $page));
    }
    if ($catalogType === 'pages') {
        $keys = [];
        $dir = $root . '/pages';
        if (!is_dir($dir)) {
            return [];
        }
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $pageId = basename($file, '.json');
            foreach (array_keys(hobc_i18n_load_page_catalog('en', $pageId)) as $key) {
                $keys[] = $pageId . '.' . $key;
            }
        }
        sort($keys);
        return $keys;
    }
    return [];
}

function hobc_i18n_load_json_catalog(string $locale, string $catalog): array
{
    $paths = hobc_i18n_config()['catalog_paths'] ?? [];
    $fileName = (string)($paths[$catalog] ?? ($catalog . '.json'));
    $path = hobc_i18n_lang_root() . '/' . $locale . '/' . $fileName;
    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function hobc_i18n_catalog(string $catalog = 'ui'): array
{
    static $cache = [];
    $locale = hobc_i18n_locale();
    $cacheKey = $locale . ':' . $catalog;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $defaultLocale = hobc_i18n_default_locale();
    $base = hobc_i18n_load_json_catalog($defaultLocale, $catalog);
    if ($locale !== $defaultLocale) {
        $base = array_merge($base, hobc_i18n_load_json_catalog($locale, $catalog));
    }

    $cache[$cacheKey] = $base;
    return $base;
}

function hobc_i18n_js_catalog(): array
{
    return hobc_i18n_catalog('js');
}
