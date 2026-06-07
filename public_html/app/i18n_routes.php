<?php
declare(strict_types=1);

function hobc_i18n_prefix_urls_enabled(): bool
{
    return (bool)(hobc_i18n_config()['prefix_urls'] ?? false);
}

function hobc_i18n_excluded_path_prefixes(): array
{
    return [
        'admin',
        'api',
        'jobs',
        'app',
        'assets',
        'vendor',
        'lang',
        'config',
        'includes',
    ];
}

function hobc_i18n_locale_url_slug(string $locale): string
{
    if ($locale === hobc_i18n_default_locale()) {
        return '';
    }

    $meta = hobc_i18n_language_meta($locale);
    if (!empty($meta['url_slug'])) {
        return strtolower((string)$meta['url_slug']);
    }

    return strtolower(str_replace('_', '-', $locale));
}

function hobc_i18n_locale_url_slug_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = [];
    foreach (hobc_i18n_supported_locales() as $locale) {
        if ($locale === hobc_i18n_default_locale()) {
            continue;
        }
        $slug = hobc_i18n_locale_url_slug($locale);
        if ($slug !== '') {
            $map[$slug] = $locale;
        }
    }

    return $map;
}

function hobc_i18n_locale_from_url_slug(string $slug): string
{
    $slug = strtolower(trim($slug));
    if ($slug === '') {
        return '';
    }

    return (string)(hobc_i18n_locale_url_slug_map()[$slug] ?? '');
}

function hobc_i18n_locale_url_slug_pattern(): string
{
    static $pattern = null;
    if ($pattern !== null) {
        return $pattern;
    }

    $slugs = array_keys(hobc_i18n_locale_url_slug_map());
    usort($slugs, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
    $pattern = implode('|', array_map(static fn(string $slug): string => preg_quote($slug, '#'), $slugs));

    return $pattern;
}

function hobc_i18n_normalize_site_path(string $path): string
{
    $path = parse_url($path, PHP_URL_PATH);
    $path = is_string($path) && $path !== '' ? $path : '/';
    if (str_ends_with($path, '/index.php')) {
        $path = substr($path, 0, -9);
    }
    if ($path === '') {
        $path = '/';
    }
    if ($path !== '/' && !str_ends_with($path, '/') && pathinfo($path, PATHINFO_EXTENSION) === '') {
        $path .= '/';
    }

    return $path;
}

function hobc_i18n_is_wallet_action_path(string $path): bool
{
    $path = hobc_i18n_normalize_site_path($path);
    if (!preg_match('#^/wallet/([^/]+\.php)$#', $path, $match)) {
        return false;
    }

    return strtolower((string)$match[1]) !== 'index.php';
}

function hobc_i18n_path_supports_locale_prefix(string $path): bool
{
    $path = hobc_i18n_normalize_site_path($path);

    foreach (hobc_i18n_excluded_path_prefixes() as $prefix) {
        if ($path === '/' . $prefix . '/' || str_starts_with($path, '/' . $prefix . '/')) {
            return false;
        }
    }

    if (hobc_i18n_is_wallet_action_path($path)) {
        return false;
    }

    if (preg_match('#\.(?:css|js|json|xml|ico|png|jpe?g|gif|svg|webp|woff2?|exe|zip|tar|gz|pdf|map|txt|webmanifest)$#i', $path)) {
        return false;
    }

    return true;
}

function hobc_i18n_split_path_locale(string $requestPath): array
{
    $requestPath = hobc_i18n_normalize_site_path($requestPath);
    if (!hobc_i18n_prefix_urls_enabled()) {
        return ['locale' => '', 'path' => $requestPath];
    }

    $pattern = hobc_i18n_locale_url_slug_pattern();
    if ($pattern === '') {
        return ['locale' => '', 'path' => $requestPath];
    }

    if (!preg_match('#^/(' . $pattern . ')(?:/(.*))?$#', $requestPath, $match)) {
        return ['locale' => '', 'path' => $requestPath];
    }

    $locale = hobc_i18n_locale_from_url_slug((string)$match[1]);
    if ($locale === '') {
        return ['locale' => '', 'path' => $requestPath];
    }

    $remainder = (string)($match[2] ?? '');
    if ($remainder === '') {
        return ['locale' => $locale, 'path' => '/'];
    }

    $innerPath = '/' . ltrim($remainder, '/');
    if (!str_ends_with($innerPath, '/') && pathinfo($innerPath, PATHINFO_EXTENSION) === '') {
        $innerPath .= '/';
    }

    return ['locale' => $locale, 'path' => $innerPath];
}

function hobc_i18n_request_path_locale(): string
{
    if (!hobc_i18n_prefix_urls_enabled()) {
        return '';
    }

    if (isset($GLOBALS['HOBC_I18N_REQUEST_PATH_LOCALE'])) {
        return (string)$GLOBALS['HOBC_I18N_REQUEST_PATH_LOCALE'];
    }

    foreach (['REDIRECT_HOBC_PATH_LOCALE', 'HOBC_PATH_LOCALE'] as $envKey) {
        $envLocale = hobc_i18n_locale_from_url_slug((string)($_SERVER[$envKey] ?? ''));
        if ($envLocale !== '') {
            $GLOBALS['HOBC_I18N_REQUEST_PATH_LOCALE'] = $envLocale;
            return $envLocale;
        }
    }

    $uriPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    $split = hobc_i18n_split_path_locale($uriPath);
    $GLOBALS['HOBC_I18N_REQUEST_PATH_LOCALE'] = (string)$split['locale'];
    $GLOBALS['HOBC_I18N_CANONICAL_SITE_PATH'] = (string)$split['path'];

    return (string)$split['locale'];
}

function hobc_i18n_canonical_site_path(?string $requestPath = null): string
{
    if (isset($GLOBALS['HOBC_I18N_CANONICAL_SITE_PATH'])) {
        return (string)$GLOBALS['HOBC_I18N_CANONICAL_SITE_PATH'];
    }

    if ($requestPath === null) {
        $requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    }

    $split = hobc_i18n_split_path_locale($requestPath);
    $GLOBALS['HOBC_I18N_CANONICAL_SITE_PATH'] = (string)$split['path'];
    if ($split['locale'] !== '') {
        $GLOBALS['HOBC_I18N_REQUEST_PATH_LOCALE'] = (string)$split['locale'];
    }

    return (string)$split['path'];
}

function hobc_i18n_public_path(string $path, ?string $locale = null): string
{
    $path = hobc_i18n_normalize_site_path($path);
    $locale = $locale ?? hobc_i18n_locale();
    $default = hobc_i18n_default_locale();

    if (!hobc_i18n_prefix_urls_enabled() || !hobc_i18n_path_supports_locale_prefix($path) || $locale === $default) {
        return $path;
    }

    $slug = hobc_i18n_locale_url_slug($locale);
    if ($slug === '') {
        return $path;
    }

    if ($path === '/') {
        return '/' . $slug . '/';
    }

    return '/' . $slug . $path;
}

function hobc_i18n_public_url(string $path, ?string $locale = null): string
{
    return hobc_i18n_absolute_url(hobc_i18n_public_path($path, $locale));
}

/** Locale-aware public path for templates (prefix URLs when enabled). */
function hobc_pp(string $path, ?string $locale = null): string
{
    if (!function_exists('hobc_i18n_enabled') || !hobc_i18n_enabled()) {
        return $path;
    }
    return hobc_i18n_public_path($path, $locale);
}

function hobc_i18n_should_redirect_to_prefixed_url(string $path, string $locale): bool
{
    if (!hobc_i18n_prefix_urls_enabled() || !hobc_i18n_path_supports_locale_prefix($path)) {
        return false;
    }

    if ($locale === hobc_i18n_default_locale()) {
        return false;
    }

    return hobc_i18n_request_path_locale() === '';
}

function hobc_i18n_should_strip_lang_query(string $path, string $locale): bool
{
    if (!hobc_i18n_prefix_urls_enabled() || trim((string)($_GET['lang'] ?? '')) === '') {
        return false;
    }

    if (!hobc_i18n_path_supports_locale_prefix($path)) {
        return false;
    }

    $queryLocale = hobc_i18n_normalize_locale((string)$_GET['lang']);
    if ($queryLocale === '') {
        return true;
    }

    if ($locale === hobc_i18n_default_locale()) {
        return true;
    }

    return hobc_i18n_request_path_locale() !== '';
}

function hobc_i18n_build_redirect_url(string $path, string $locale, array $query = []): string
{
    unset($query['lang']);
    $targetPath = hobc_i18n_public_path($path, $locale);
    $queryString = http_build_query($query);
    if ($queryString === '') {
        return $targetPath;
    }

    return $targetPath . '?' . $queryString;
}

function hobc_i18n_maybe_prefix_redirect(): void
{
    if (!hobc_i18n_enabled() || !hobc_i18n_prefix_urls_enabled() || headers_sent()) {
        return;
    }

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        return;
    }

    $path = hobc_i18n_canonical_site_path();
    $locale = hobc_i18n_locale();
    $query = $_GET;
    $pathLocale = hobc_i18n_request_path_locale();

    if ($pathLocale !== '' && $pathLocale !== $locale && trim((string)($_GET['lang'] ?? '')) === '') {
        $target = hobc_i18n_build_redirect_url($path, $locale, $query);
        header('Location: ' . $target, true, 301);
        exit;
    }

    if (hobc_i18n_should_strip_lang_query($path, $locale)) {
        $target = hobc_i18n_build_redirect_url($path, $locale, $query);
        header('Location: ' . $target, true, 301);
        exit;
    }

    if (trim((string)($_GET['lang'] ?? '')) !== '' && hobc_i18n_should_redirect_to_prefixed_url($path, $locale)) {
        $target = hobc_i18n_build_redirect_url($path, $locale, $query);
        header('Location: ' . $target, true, 301);
        exit;
    }
}
