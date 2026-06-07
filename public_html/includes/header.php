<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/site_status.php';
require_once __DIR__ . '/../app/i18n.php';
require_once __DIR__ . '/../app/public_settings.php';
require_once __DIR__ . '/../app/social_links.php';
require_once __DIR__ . '/../app/analytics.php';

hobc_i18n_bootstrap();
analytics_start_public_request();
site_status_gate();

if (!function_exists('hobc_e')) {
    function hobc_e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hobc_pages')) {
    function hobc_pages(): array
    {
        if (function_exists('hobc_i18n_nav_pages') && hobc_i18n_enabled()) {
            return hobc_i18n_nav_pages();
        }

        $pages = [
            ['key' => 'home', 'label' => 'Home', 'url' => '/'],
            ['key' => 'about', 'label' => 'About HOBC', 'url' => '/about/'],
            ['key' => 'mining', 'label' => 'Mining', 'url' => '/mining/'],
            ['key' => 'main-pool', 'label' => 'Main Pool', 'url' => '/pool/main/', 'setting' => 'pool.main_enabled'],
            ['key' => 'nano-pool', 'label' => 'Nano Pool', 'url' => '/pool/nano/', 'setting' => 'pool.nano_enabled'],
            ['key' => 'explorer', 'label' => 'Explorer', 'url' => '/explorer/', 'setting' => 'explorer.public_enabled'],
            ['key' => 'wallet', 'label' => 'Wallet', 'url' => '/wallet/', 'setting' => 'wallet.public_enabled'],
            ['key' => 'stats', 'label' => 'Stats', 'url' => '/stats/'],
            ['key' => 'downloads', 'label' => 'Downloads', 'url' => '/downloads/', 'setting' => 'downloads.public_enabled'],
            ['key' => 'docs', 'label' => 'Docs', 'url' => '/docs/', 'setting' => 'docs.public_enabled'],
            ['key' => 'reserve', 'label' => 'Launch Reserve', 'url' => '/launch-reserve/'],
            ['key' => 'burn', 'label' => 'Burn Tracker', 'url' => '/burn/'],
            ['key' => 'faq', 'label' => 'FAQ', 'url' => '/docs/faq/', 'setting' => 'docs.public_enabled'],
            ['key' => 'contact', 'label' => 'Contact/Support', 'url' => '/contact/'],
        ];
        return array_values(array_filter($pages, static fn(array $page): bool => empty($page['setting']) || hobc_public_setting_bool((string)$page['setting'], true)));
    }
}

if (!function_exists('hobc_status_value')) {
    function hobc_status_value(string $endpoint, string $field, string $fallback = 'Syncing', array $attrs = []): string
    {
        $extra = '';
        foreach ($attrs as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $extra .= ' data-' . hobc_e((string)$key) . '="' . hobc_e((string)$value) . '"';
        }
        return '<span data-api-value="' . hobc_e($endpoint) . '" data-field="' . hobc_e($field) . '" data-fallback="' . hobc_e($fallback) . '"' . $extra . '>' . hobc_e($fallback) . '</span>';
    }
}

if (!function_exists('hobc_metric_card_t')) {
    function hobc_metric_card_t(string $labelKey, string $endpoint, string $field, string $fallbackKey = 'status.syncing', string $href = '', array $attrs = [], ?string $labelFallback = null, ?string $statusFallback = null): string
    {
        $label = function_exists('hobc_t') ? hobc_t($labelKey, [], $labelFallback ?? $labelKey) : ($labelFallback ?? $labelKey);
        $fallback = function_exists('hobc_t') ? hobc_t($fallbackKey, [], $statusFallback ?? 'Syncing') : ($statusFallback ?? 'Syncing');
        return hobc_metric_card($label, $endpoint, $field, $fallback, $href, $attrs);
    }
}

if (!function_exists('hobc_status_value_t')) {
    function hobc_status_value_t(string $endpoint, string $field, string $fallbackKey = 'status.syncing', array $attrs = [], ?string $statusFallback = null): string
    {
        $fallback = function_exists('hobc_t') ? hobc_t($fallbackKey, [], $statusFallback ?? 'Syncing') : ($statusFallback ?? 'Syncing');
        return hobc_status_value($endpoint, $field, $fallback, $attrs);
    }
}

if (!function_exists('hobc_metric_card')) {
    function hobc_metric_card(string $label, string $endpoint, string $field, string $fallback = 'Syncing', string $href = '', array $attrs = []): string
    {
        $value = hobc_status_value($endpoint, $field, $fallback, $attrs);
        $open = $href !== '' ? '<a class="metric-card metric-link" href="' . hobc_e(function_exists('hobc_pp') ? hobc_pp($href) : $href) . '">' : '<div class="metric-card">';
        $close = $href !== '' ? '</a>' : '</div>';
        return $open . '<span class="metric-label">' . hobc_e($label) . '</span><strong>' . $value . '</strong>' . $close;
    }
}

if (!function_exists('hobc_absolute_url')) {
    function hobc_absolute_url(string $path = '/'): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }
        return 'https://hobbyhashcoin.com' . '/' . ltrim($path, '/');
    }
}

if (!function_exists('hobc_current_canonical_path')) {
    function hobc_current_canonical_path(): string
    {
        if (function_exists('hobc_i18n_canonical_site_path') && function_exists('hobc_i18n_prefix_urls_enabled') && hobc_i18n_prefix_urls_enabled()) {
            return hobc_i18n_canonical_site_path();
        }

        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
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
}

if (!function_exists('hobc_json_ld')) {
    function hobc_json_ld(array $data): string
    {
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }
}

$siteName = hobc_public_setting_text('site.name', 'HobbyHash Coin');
$coinName = hobc_public_setting_text('coin.name', 'HobbyHash Coin');
$coinTicker = hobc_public_setting_text('coin.ticker', 'HOBC');
$logoUrl = hobc_public_asset_url('branding.logo_url', '/assets/images/logo-round.png');
$themeColor = hobc_public_setting_text('branding.primary_theme_color', '#f6b928');
$pageTitle = $pageTitle ?? hobc_public_setting_text('seo.default_meta_title', 'HOBC Command Center');
$pageDescription = $pageDescription ?? hobc_public_setting_text('seo.default_meta_description', 'HobbyHash Coin command center for home solo miners.');
$activePage = $activePage ?? 'home';
$canonicalPath = $canonicalPath ?? hobc_current_canonical_path();
$canonicalUrl = hobc_i18n_enabled()
    ? hobc_i18n_canonical_url((string)$canonicalPath)
    : hobc_absolute_url((string)$canonicalPath);
$robotsMeta = $robotsMeta ?? (hobc_public_setting_bool('seo.robots_index', true) ? 'index, follow' : 'noindex, nofollow');
$pageImage = hobc_absolute_url($pageImage ?? '/assets/icons/favicon.svg');
$pageType = $pageType ?? 'website';
$publicNotice = trim(hobc_public_setting_text('site.public_notice', ''));
$siteSchema = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            '@id' => 'https://hobbyhashcoin.com/#organization',
            'name' => $siteName,
            'alternateName' => $coinTicker,
            'url' => 'https://hobbyhashcoin.com/',
            'logo' => hobc_absolute_url($logoUrl),
            'sameAs' => hobc_social_links_same_as(),
        ],
        [
            '@type' => 'WebSite',
            '@id' => 'https://hobbyhashcoin.com/#website',
            'url' => 'https://hobbyhashcoin.com/',
            'name' => $siteName,
            'alternateName' => $coinTicker . ' Command Center',
            'description' => $pageDescription,
            'publisher' => ['@id' => 'https://hobbyhashcoin.com/#organization'],
        ],
    ],
];
$structuredData = $structuredData ?? [];
$structuredData = array_merge([$siteSchema], is_array($structuredData) ? $structuredData : []);
?>
<!doctype html>
<html lang="<?= hobc_e(hobc_i18n_html_lang()) ?>" dir="<?= hobc_e(hobc_i18n_dir()) ?>">
<head>
  <meta charset="utf-8">
  <?php require __DIR__ . '/google-gtag.php'; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= hobc_e($pageDescription) ?>">
  <meta name="robots" content="<?= hobc_e((string)$robotsMeta) ?>">
  <link rel="canonical" href="<?= hobc_e($canonicalUrl) ?>">
  <?php hobc_i18n_render_head_extras((string)$canonicalPath); ?>
  <meta property="og:site_name" content="<?= hobc_e($siteName) ?>">
  <meta property="og:type" content="<?= hobc_e((string)$pageType) ?>">
  <meta property="og:title" content="<?= hobc_e($pageTitle . ' | ' . $siteName) ?>">
  <meta property="og:description" content="<?= hobc_e($pageDescription) ?>">
  <meta property="og:url" content="<?= hobc_e($canonicalUrl) ?>">
  <meta property="og:image" content="<?= hobc_e($pageImage) ?>">
  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="<?= hobc_e($pageTitle . ' | ' . $siteName) ?>">
  <meta name="twitter:description" content="<?= hobc_e($pageDescription) ?>">
  <meta name="twitter:image" content="<?= hobc_e($pageImage) ?>">
  <title><?= hobc_e($pageTitle) ?> | <?= hobc_e($siteName) ?></title>
  <?php require __DIR__ . '/icon-meta.php'; ?>
  <?php $hobcCssVersion = (string)@filemtime(__DIR__ . '/../assets/css/hobc.css'); ?>
  <link rel="stylesheet" href="/assets/css/hobc.css?v=<?= htmlspecialchars($hobcCssVersion !== '' ? $hobcCssVersion : '1', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <style>:root{--gold:<?= hobc_e($themeColor) ?>;}</style>
  <?php if (!empty($statsModule)): ?>
  <?php $hobcStatsCssVersion = (string)@filemtime(__DIR__ . '/../assets/css/hobc-stats-overload.css'); ?>
  <link rel="stylesheet" href="/assets/css/hobc-stats-overload.css?v=<?= htmlspecialchars($hobcStatsCssVersion !== '' ? $hobcStatsCssVersion : '1', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <?php endif; ?>
  <?php foreach ($structuredData as $schema): ?>
  <?= hobc_json_ld($schema) ?>
  <?php endforeach; ?>
</head>
<body>
<a class="skip-link" href="#main-content"><?= hobc_te('header.skip_to_content', [], 'Skip to content') ?></a>
<header class="site-header">
  <a class="brand" href="/" aria-label="<?= hobc_te('header.home_dashboard', [], $coinTicker . ' home dashboard') ?>">
    <img src="<?= hobc_e($logoUrl) ?>" alt="<?= hobc_e($coinTicker) ?> logo" class="brand-logo">
    <span>
      <strong><?= hobc_e($siteName) ?></strong>
      <small><?= hobc_e($coinTicker) ?> <?= hobc_te('header.command_center', [], 'command center') ?></small>
    </span>
  </a>
  <img src="/assets/images/wordmark-wide.png" alt="<?= hobc_e($coinTicker) ?> <?= hobc_e($coinName) ?> wordmark" class="header-wordmark">
  <div class="header-actions">
    <?php hobc_i18n_render_language_switcher(); ?>
    <button type="button" class="mobile-menu-toggle" data-mobile-menu-toggle aria-expanded="false" hidden><?= hobc_te('header.menu', [], 'Menu') ?></button>
  </div>
</header>
<?php if ($publicNotice !== ''): ?>
<div class="notice site-notice" role="status"><?= nl2br(hobc_e($publicNotice)) ?></div>
<?php endif; ?>
