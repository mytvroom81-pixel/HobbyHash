<?php
declare(strict_types=1);

require_once __DIR__ . '/i18n_catalog.php';
require_once __DIR__ . '/i18n_glossary.php';
require_once __DIR__ . '/i18n_routes.php';

if (!function_exists('hobc_e')) {
    function hobc_e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function hobc_i18n_is_excluded(): bool
{
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === '') {
        return false;
    }

    $excludedPrefixes = ['/admin/', '/jobs/', '/api/', '/app/'];
    foreach ($excludedPrefixes as $prefix) {
        if ($script === rtrim($prefix, '/') || str_starts_with($script, $prefix)) {
            return true;
        }
    }

    return false;
}

function hobc_i18n_enabled(): bool
{
    static $enabled = null;
    if ($enabled === null) {
        $enabled = !hobc_i18n_is_excluded();
    }
    return $enabled;
}

function hobc_i18n_cookie_name(): string
{
    return (string)(hobc_i18n_config()['cookie_name'] ?? 'hobc_lang');
}

function hobc_i18n_local_storage_key(): string
{
    return (string)(hobc_i18n_config()['local_storage_key'] ?? 'hobc_lang');
}

function hobc_i18n_set_cookie(string $locale): void
{
    if (headers_sent()) {
        return;
    }

    $maxAge = (int)(hobc_i18n_config()['cookie_max_age'] ?? 31536000);
    $secure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443'
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    setcookie(hobc_i18n_cookie_name(), $locale, [
        'expires' => time() + max(0, $maxAge),
        'path' => '/',
        'secure' => $secure,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[hobc_i18n_cookie_name()] = $locale;
}

function hobc_i18n_read_cookie(): string
{
    $name = hobc_i18n_cookie_name();
    return isset($_COOKIE[$name]) ? trim((string)$_COOKIE[$name]) : '';
}

function hobc_i18n_query_locale(): string
{
    $raw = trim((string)($_GET['lang'] ?? ''));
    if ($raw === '') {
        return '';
    }
    return hobc_i18n_normalize_locale($raw);
}

function hobc_i18n_accept_language_locale(): string
{
    $header = trim((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    if ($header === '') {
        return '';
    }

    $candidates = [];
    foreach (explode(',', $header) as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $segments = explode(';', $part);
        $tag = trim((string)($segments[0] ?? ''));
        if ($tag === '') {
            continue;
        }
        $quality = 1.0;
        if (isset($segments[1]) && preg_match('/q=([0-9.]+)/', $segments[1], $match) === 1) {
            $quality = (float)$match[1];
        }
        $candidates[] = ['tag' => $tag, 'q' => $quality];
    }

    usort($candidates, static fn(array $a, array $b): int => $b['q'] <=> $a['q']);
    foreach ($candidates as $candidate) {
        $normalized = hobc_i18n_normalize_locale((string)$candidate['tag']);
        if ($normalized !== '') {
            return $normalized;
        }
    }

    return '';
}

function hobc_i18n_language_selector_name(string $locale): string
{
    $meta = hobc_i18n_language_meta($locale);
    return (string)($meta['selector'] ?? $meta['native'] ?? $locale);
}

function hobc_i18n_persist_locale(string $locale): void
{
    $locale = hobc_i18n_normalize_locale($locale);
    if ($locale === '') {
        return;
    }
    hobc_i18n_set_cookie($locale);
}

function hobc_i18n_resolve_locale(): string
{
    $default = hobc_i18n_default_locale();

    $rawLang = trim((string)($_GET['lang'] ?? ''));
    if ($rawLang !== '') {
        $queryLocale = hobc_i18n_normalize_locale($rawLang);
        if ($queryLocale !== '') {
            hobc_i18n_persist_locale($queryLocale);
            return $queryLocale;
        }
        hobc_i18n_persist_locale($default);
        return $default;
    }

    $pathLocale = hobc_i18n_request_path_locale();
    if ($pathLocale !== '') {
        hobc_i18n_persist_locale($pathLocale);
        return $pathLocale;
    }

    $cookieLocale = hobc_i18n_normalize_locale(hobc_i18n_read_cookie());
    if ($cookieLocale !== '') {
        return $cookieLocale;
    }

    $browserLocale = hobc_i18n_accept_language_locale();
    if ($browserLocale !== '') {
        return $browserLocale;
    }

    return $default;
}

function hobc_i18n_bootstrap(): void
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }
    $bootstrapped = true;

    if (!hobc_i18n_enabled()) {
        $GLOBALS['HOBC_I18N_LOCALE'] = hobc_i18n_default_locale();
        return;
    }

    $GLOBALS['HOBC_I18N_LOCALE'] = hobc_i18n_resolve_locale();
    hobc_i18n_maybe_prefix_redirect();
}

function hobc_i18n_locale(): string
{
    if (!isset($GLOBALS['HOBC_I18N_LOCALE'])) {
        hobc_i18n_bootstrap();
    }
    return (string)($GLOBALS['HOBC_I18N_LOCALE'] ?? hobc_i18n_default_locale());
}

function hobc_i18n_language_meta(string $locale): array
{
    $languages = hobc_i18n_config()['languages'] ?? [];
    if (isset($languages[$locale]) && is_array($languages[$locale])) {
        return $languages[$locale];
    }
    return [
        'label' => $locale,
        'native' => $locale,
        'dir' => 'ltr',
        'hreflang' => $locale,
    ];
}

function hobc_i18n_dir(): string
{
    if (!hobc_i18n_enabled()) {
        return 'ltr';
    }
    $dir = (string)(hobc_i18n_language_meta(hobc_i18n_locale())['dir'] ?? 'ltr');
    return $dir === 'rtl' ? 'rtl' : 'ltr';
}

function hobc_i18n_html_lang(): string
{
    if (!hobc_i18n_enabled()) {
        return hobc_i18n_default_locale();
    }
    return hobc_i18n_locale();
}

function hobc_i18n_replace_vars(string $text, array $vars): string
{
    foreach ($vars as $key => $value) {
        $text = str_replace('{' . $key . '}', (string)$value, $text);
    }
    return $text;
}

function hobc_i18n_lookup(string $key, ?string $locale = null): ?string
{
    $locale = $locale ?? hobc_i18n_locale();
    $defaultLocale = hobc_i18n_default_locale();
    $catalogs = [];

    if ($locale !== $defaultLocale) {
        $catalogs[] = hobc_i18n_load_json_catalog($locale, 'ui');
    }
    $catalogs[] = hobc_i18n_load_json_catalog($defaultLocale, 'ui');

    foreach ($catalogs as $catalog) {
        if (array_key_exists($key, $catalog)) {
            return (string)$catalog[$key];
        }
    }

    return null;
}

function hobc_tp(string $page, string $key, array $vars = [], ?string $fallback = null): string
{
    if (!hobc_i18n_enabled()) {
        $value = hobc_i18n_page_lookup($page, $key, hobc_i18n_default_locale());
    } else {
        $value = hobc_i18n_page_lookup($page, $key);
    }

    if ($value === null) {
        $value = $fallback ?? ($page . '.' . $key);
    }

    return hobc_i18n_replace_vars($value, $vars);
}

function hobc_tpe(string $page, string $key, array $vars = [], ?string $fallback = null): string
{
    return hobc_e(hobc_tp($page, $key, $vars, $fallback));
}

function hobc_t(string $key, array $vars = [], ?string $fallback = null): string
{
    if (!hobc_i18n_enabled()) {
        $value = hobc_i18n_lookup($key, hobc_i18n_default_locale());
    } else {
        $value = hobc_i18n_lookup($key);
    }

    if ($value === null) {
        $value = $fallback ?? $key;
    }

    return hobc_i18n_replace_vars($value, $vars);
}

function hobc_te(string $key, array $vars = [], ?string $fallback = null): string
{
    $value = hobc_t($key, $vars, $fallback);
    if (function_exists('hobc_e')) {
        return hobc_e($value);
    }
    if (function_exists('h')) {
        return h($value);
    }
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hobc_i18n_current_path(): string
{
    if (function_exists('hobc_i18n_canonical_site_path')) {
        return hobc_i18n_canonical_site_path();
    }

    if (function_exists('hobc_current_canonical_path')) {
        return hobc_current_canonical_path();
    }

    return hobc_i18n_normalize_site_path((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'));
}

function hobc_i18n_absolute_url(string $path = '/'): string
{
    if (function_exists('hobc_absolute_url')) {
        return hobc_absolute_url($path);
    }
    if (preg_match('#^https?://#i', $path) === 1) {
        return $path;
    }
    return 'https://hobbyhashcoin.com/' . ltrim($path, '/');
}

function hobc_i18n_url_with_lang(string $path, ?string $locale = null): string
{
    $locale = $locale ?? hobc_i18n_locale();
    $default = hobc_i18n_default_locale();
    $path = hobc_i18n_normalize_site_path($path);

    if (hobc_i18n_prefix_urls_enabled() && hobc_i18n_path_supports_locale_prefix($path)) {
        return hobc_i18n_public_url($path, $locale);
    }

    $base = hobc_i18n_absolute_url($path);
    if ($locale === $default) {
        return $base;
    }

    $separator = str_contains($base, '?') ? '&' : '?';
    return $base . $separator . 'lang=' . rawurlencode($locale);
}

function hobc_i18n_canonical_url(string $path): string
{
    if (!hobc_i18n_enabled()) {
        return hobc_i18n_absolute_url($path);
    }
    return hobc_i18n_url_with_lang($path, hobc_i18n_locale());
}

function hobc_i18n_hreflang_tags(string $path): string
{
    if (!hobc_i18n_enabled()) {
        return '';
    }

    $html = '';
    foreach (hobc_i18n_supported_locales() as $locale) {
        $meta = hobc_i18n_language_meta($locale);
        $hreflang = (string)($meta['hreflang'] ?? $locale);
        $url = hobc_i18n_url_with_lang($path, $locale);
        $html .= '<link rel="alternate" hreflang="' . htmlspecialchars($hreflang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . "\n  ";
    }

    $defaultUrl = hobc_i18n_absolute_url($path);
    $html .= '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($defaultUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
    return $html;
}

function hobc_i18n_render_head_extras(string $path): void
{
    if (!hobc_i18n_enabled()) {
        return;
    }

    echo hobc_i18n_hreflang_tags($path), "\n  ";
    hobc_i18n_render_storage_sync_script();
}

function hobc_i18n_render_storage_sync_script(): void
{
    if (!hobc_i18n_enabled()) {
        return;
    }

    $cookieName = hobc_i18n_cookie_name();
    $storageKey = hobc_i18n_local_storage_key();
    $currentLocale = hobc_i18n_locale();
    ?>
<script>
(function () {
  var cookieName = <?= json_encode($cookieName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var storageKey = <?= json_encode($storageKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var currentLocale = <?= json_encode($currentLocale, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var cookieMatch = document.cookie.match(new RegExp('(?:^|; )' + cookieName.replace(/[-[\]/{}()*+?.\\^$|]/g, '\\$&') + '=([^;]*)'));
  var cookieLocale = cookieMatch ? decodeURIComponent(cookieMatch[1]) : '';
  var storedLocale = '';
  try { storedLocale = window.localStorage.getItem(storageKey) || ''; } catch (e) {}
  if (!cookieLocale && storedLocale) {
    document.cookie = cookieName + '=' + encodeURIComponent(storedLocale) + ';path=/;max-age=31536000;samesite=lax';
    if (storedLocale !== currentLocale) { window.location.reload(); return; }
  } else if (cookieLocale && storedLocale !== cookieLocale) {
    try { window.localStorage.setItem(storageKey, cookieLocale); } catch (e) {}
  } else if (cookieLocale && !storedLocale) {
    try { window.localStorage.setItem(storageKey, cookieLocale); } catch (e) {}
  }
  document.documentElement.setAttribute('data-hobc-locale', currentLocale);
  document.documentElement.setAttribute('lang', currentLocale);
  document.documentElement.setAttribute('dir', <?= json_encode(hobc_i18n_dir(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
})();
</script>
    <?php
}

function hobc_i18n_render_js_bootstrap(): void
{
    if (!hobc_i18n_enabled()) {
        return;
    }

    $localeSlugs = [];
    foreach (hobc_i18n_supported_locales() as $code) {
        $slug = hobc_i18n_locale_url_slug($code);
        if ($slug !== '') {
            $localeSlugs[$code] = $slug;
        }
    }

    $payload = [
        'locale' => hobc_i18n_locale(),
        'defaultLocale' => hobc_i18n_default_locale(),
        'dir' => hobc_i18n_dir(),
        'cookieName' => hobc_i18n_cookie_name(),
        'storageKey' => hobc_i18n_local_storage_key(),
        'prefixUrls' => hobc_i18n_prefix_urls_enabled(),
        'canonicalPath' => hobc_i18n_current_path(),
        'localeSlugs' => $localeSlugs,
        'strings' => hobc_i18n_js_catalog(),
        'supported' => array_map(
            static fn(string $code): array => [
                'code' => $code,
                'native' => hobc_i18n_language_selector_name($code),
            ],
            hobc_i18n_supported_locales()
        ),
    ];
    echo '<script>window.HOBC_I18N = ', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ';</script>', "\n";
}

function hobc_i18n_nav_pages(): array
{
    $pages = [
        ['key' => 'home', 'label_key' => 'nav.home', 'label' => 'Home', 'url' => '/'],
        ['key' => 'about', 'label_key' => 'nav.about', 'label' => 'About HOBC', 'url' => '/about/'],
        ['key' => 'mining', 'label_key' => 'nav.mining', 'label' => 'Mining', 'url' => '/mining/'],
        ['key' => 'main-pool', 'label_key' => 'nav.main_pool', 'label' => 'Main Pool', 'url' => '/pool/main/', 'setting' => 'pool.main_enabled'],
        ['key' => 'nano-pool', 'label_key' => 'nav.nano_pool', 'label' => 'Nano Pool', 'url' => '/pool/nano/', 'setting' => 'pool.nano_enabled'],
        ['key' => 'explorer', 'label_key' => 'nav.explorer', 'label' => 'Explorer', 'url' => '/explorer/', 'setting' => 'explorer.public_enabled'],
        ['key' => 'wallet', 'label_key' => 'nav.wallet', 'label' => 'Wallet', 'url' => '/wallet/', 'setting' => 'wallet.public_enabled'],
        ['key' => 'stats', 'label_key' => 'nav.stats', 'label' => 'Stats', 'url' => '/stats/'],
        ['key' => 'downloads', 'label_key' => 'nav.downloads', 'label' => 'Downloads', 'url' => '/downloads/', 'setting' => 'downloads.public_enabled'],
        ['key' => 'docs', 'label_key' => 'nav.docs', 'label' => 'Docs', 'url' => '/docs/', 'setting' => 'docs.public_enabled'],
        ['key' => 'reserve', 'label_key' => 'nav.reserve', 'label' => 'Launch Reserve', 'url' => '/launch-reserve/'],
        ['key' => 'burn', 'label_key' => 'nav.burn', 'label' => 'Burn Tracker', 'url' => '/burn/'],
        ['key' => 'faq', 'label_key' => 'nav.faq', 'label' => 'FAQ', 'url' => '/docs/faq/', 'setting' => 'docs.public_enabled'],
        ['key' => 'contact', 'label_key' => 'nav.contact', 'label' => 'Contact/Support', 'url' => '/contact/'],
    ];

    if (function_exists('hobc_public_setting_bool')) {
        $pages = array_values(array_filter(
            $pages,
            static fn(array $page): bool => empty($page['setting']) || hobc_public_setting_bool((string)$page['setting'], true)
        ));
    }

    if (hobc_i18n_enabled()) {
        foreach ($pages as &$page) {
            $page['label'] = hobc_t((string)$page['label_key'], [], (string)$page['label']);
            $page['url'] = hobc_i18n_public_path((string)$page['url']);
        }
        unset($page);
    }

    return $pages;
}

function hobc_i18n_render_language_switcher(string $variant = 'header'): void
{
    if (!hobc_i18n_enabled()) {
        return;
    }
    $langSwitcherVariant = $variant;
    require __DIR__ . '/../includes/language-switcher.php';
}
