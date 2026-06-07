<?php
declare(strict_types=1);

/**
 * Generate public sitemap.xml with English and locale-prefixed URLs.
 * Run: php jobs/generate_sitemap.php
 */

require_once __DIR__ . '/../app/i18n.php';

$baseUrl = 'https://hobbyhashcoin.com';

/** @var list<array{path:string,priority:string}> */
$routes = [
    ['path' => '/', 'priority' => '1.0'],
    ['path' => '/about/', 'priority' => '0.8'],
    ['path' => '/mining/', 'priority' => '0.95'],
    ['path' => '/pool/nano/', 'priority' => '0.95'],
    ['path' => '/pool/main/', 'priority' => '0.8'],
    ['path' => '/explorer/', 'priority' => '0.75'],
    ['path' => '/stats/', 'priority' => '0.75'],
    ['path' => '/downloads/', 'priority' => '0.8'],
    ['path' => '/wallet/', 'priority' => '0.65'],
    ['path' => '/launch-reserve/', 'priority' => '0.7'],
    ['path' => '/burn/', 'priority' => '0.6'],
    ['path' => '/docs/', 'priority' => '0.9'],
    ['path' => '/docs/getting-started/', 'priority' => '0.85'],
    ['path' => '/docs/wallet-guide/', 'priority' => '0.75'],
    ['path' => '/docs/linux-node/', 'priority' => '0.75'],
    ['path' => '/docs/mining-guide/', 'priority' => '0.95'],
    ['path' => '/docs/pool-stats/', 'priority' => '0.75'],
    ['path' => '/docs/ports-configuration/', 'priority' => '0.7'],
    ['path' => '/docs/security-guide/', 'priority' => '0.7'],
    ['path' => '/docs/explorer-guide/', 'priority' => '0.65'],
    ['path' => '/docs/cli-rpc/', 'priority' => '0.65'],
    ['path' => '/docs/faq/', 'priority' => '0.85'],
    ['path' => '/exchange-listing/', 'priority' => '0.72'],
    ['path' => '/contact/', 'priority' => '0.5'],
    ['path' => '/privacy/', 'priority' => '0.35'],
    ['path' => '/terms/', 'priority' => '0.35'],
];

$locales = hobc_i18n_supported_locales();
$default = hobc_i18n_default_locale();
$prefixEnabled = hobc_i18n_prefix_urls_enabled();

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

foreach ($routes as $route) {
    $path = (string)$route['path'];
    if (!hobc_i18n_path_supports_locale_prefix($path)) {
        continue;
    }

    $variants = [];
    $variants[$default] = $path;
    if ($prefixEnabled) {
        foreach ($locales as $locale) {
            if ($locale === $default) {
                continue;
            }
            $variants[$locale] = hobc_i18n_public_path($path, $locale);
        }
    }

    foreach ($variants as $locale => $localizedPath) {
        $loc = rtrim($baseUrl, '/') . $localizedPath;
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>' . "\n";
        $xml .= '    <priority>' . htmlspecialchars((string)$route['priority'], ENT_XML1) . '</priority>' . "\n";

        foreach ($variants as $altLocale => $altPath) {
            $meta = hobc_i18n_language_meta($altLocale);
            $hreflang = (string)($meta['hreflang'] ?? $altLocale);
            $altLoc = rtrim($baseUrl, '/') . $altPath;
            $xml .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($hreflang, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($altLoc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" />' . "\n";
        }
        $defaultPath = $variants[$default] ?? $path;
        $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars(rtrim($baseUrl, '/') . $defaultPath, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" />' . "\n";
        $xml .= '  </url>' . "\n";
    }
}

$xml .= '</urlset>' . "\n";

$out = dirname(__DIR__) . '/sitemap.xml';
file_put_contents($out, $xml);
echo 'Wrote ' . $out . ' (' . substr_count($xml, '<url>') . " URLs)\n";
