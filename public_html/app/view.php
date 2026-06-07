<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/site_status.php';
require_once __DIR__ . '/i18n.php';

hobc_i18n_bootstrap();
site_status_gate();

if (!function_exists('hobc_pages')) {
    function hobc_pages(): array
    {
        if (function_exists('hobc_i18n_nav_pages') && hobc_i18n_enabled()) {
            return hobc_i18n_nav_pages();
        }

        return [
            ['key' => 'home', 'label' => 'Home', 'url' => '/'],
            ['key' => 'about', 'label' => 'About HOBC', 'url' => '/about/'],
            ['key' => 'mining', 'label' => 'Mining', 'url' => '/mining/'],
            ['key' => 'main-pool', 'label' => 'Main Pool', 'url' => '/pool/main/'],
            ['key' => 'nano-pool', 'label' => 'Nano Pool', 'url' => '/pool/nano/'],
            ['key' => 'explorer', 'label' => 'Explorer', 'url' => '/explorer/'],
            ['key' => 'wallet', 'label' => 'Wallet', 'url' => '/wallet/'],
            ['key' => 'stats', 'label' => 'Stats', 'url' => '/stats/'],
            ['key' => 'downloads', 'label' => 'Downloads', 'url' => '/downloads/'],
            ['key' => 'docs', 'label' => 'Docs', 'url' => '/docs/'],
            ['key' => 'reserve', 'label' => 'Launch Reserve', 'url' => '/launch-reserve/'],
            ['key' => 'burn', 'label' => 'Burn Tracker', 'url' => '/burn/'],
            ['key' => 'faq', 'label' => 'FAQ', 'url' => '/docs/faq/'],
            ['key' => 'contact', 'label' => 'Contact/Support', 'url' => '/contact/'],
        ];
    }
}

function wallet_short_text(string $value, int $start = 12, int $end = 10): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (strlen($value) <= ($start + $end + 3)) {
        return $value;
    }
    return substr($value, 0, $start) . '...' . substr($value, -$end);
}

function wallet_value_chip(string $value, string $class = 'hash-chip'): string
{
    $value = trim($value);
    if ($value === '') {
        return '<span class="' . h($class) . ' empty" title="' . h(hobc_t('wallet.not_available', [], 'Not available')) . '">' . h(hobc_t('wallet.not_available', [], 'Not available')) . '</span>';
    }
    return '<span class="' . h($class) . '" title="' . h($value) . '">' . h(wallet_short_text($value)) . '</span>';
}

function wallet_address_text(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '<span class="address-full empty">' . h(hobc_t('wallet.not_available', [], 'Not available')) . '</span>';
    }
    return '<span class="address-full">' . h($value) . '</span>';
}

function wallet_display_receive_label(?string $label): string
{
    $label = trim((string)$label);
    if ($label === '' || $label === 'Receive Wallet' || preg_match('/^user_\d+_\d+$/', $label) || preg_match('/^Wallet \d+$/', $label)) {
        return hobc_t('wallet.none', [], 'None');
    }
    return $label;
}

function wallet_edit_receive_label(?string $label): string
{
    $display = wallet_display_receive_label($label);
    return $display === 'None' ? '' : $display;
}

function wallet_display_confirmations(int $confirmations): string
{
    return $confirmations > 20 ? '20+' : (string)$confirmations;
}

function wallet_pagination(string $path, string $pageParam, int $currentPage, int $totalPages, array $params = []): string
{
    $totalPages = max(1, $totalPages);
    $currentPage = max(1, min($currentPage, $totalPages));
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<div class="actions">';
    if ($currentPage > 1) {
        $prev = array_merge($params, [$pageParam => $currentPage - 1]);
        $prevLabel = function_exists('hobc_t') ? hobc_t('wallet.pagination_previous', [], 'Previous 10') : 'Previous 10';
        $html .= '<a class="button" href="' . h(wallet_url($path . '?' . http_build_query($prev))) . '">' . h($prevLabel) . '</a>';
    }
    $pageLabel = function_exists('hobc_t')
        ? hobc_t('wallet.pagination_page', ['current' => (string)$currentPage, 'total' => (string)$totalPages], 'Page ' . $currentPage . ' of ' . $totalPages)
        : 'Page ' . $currentPage . ' of ' . $totalPages;
    $html .= '<span class="button">' . h($pageLabel) . '</span>';
    if ($currentPage < $totalPages) {
        $next = array_merge($params, [$pageParam => $currentPage + 1]);
        $nextLabel = function_exists('hobc_t') ? hobc_t('wallet.pagination_next', [], 'Next 10') : 'Next 10';
        $html .= '<a class="button" href="' . h(wallet_url($path . '?' . http_build_query($next))) . '">' . h($nextLabel) . '</a>';
    }
    $html .= '</div>';
    return $html;
}

function wallet_te(string $key, array $params = [], string $fallback = ''): string
{
    return hobc_t($key, $params, $fallback !== '' ? $fallback : $key);
}

function wallet_pp(string $path): string
{
    return function_exists('hobc_pp') ? hobc_pp($path) : $path;
}

/** @param array<string, string> $keys key => English fallback */
function wallet_js_i18n(array $keys): string
{
    $out = [];
    foreach ($keys as $key => $fallback) {
        $out[$key] = wallet_te($key, [], $fallback);
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function render_header(string $titleKey, array $titleParams = [], string $titleFallback = ''): void
{
    $title = str_contains($titleKey, '.')
        ? wallet_te($titleKey, $titleParams, $titleFallback !== '' ? $titleFallback : $titleKey)
        : $titleKey;
    $user = auth_current_user();
    $walletPath = '/wallet/';
    $canonicalUrl = hobc_i18n_enabled() ? hobc_i18n_canonical_url($walletPath) : 'https://hobbyhashcoin.com/wallet/';
    echo '<!doctype html><html lang="' . h(hobc_i18n_html_lang()) . '" dir="' . h(hobc_i18n_dir()) . '"><head><meta charset="utf-8">';
    require __DIR__ . '/../includes/google-gtag.php';
    echo '<title>' . h($title) . ' ' . h(hobc_t('wallet.meta.title_suffix', [], '| HOBC Web Wallet')) . '</title>';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<meta name="description" content="' . h(hobc_t('wallet.meta.description', [], 'HobbyHash Coin custodial web wallet account area for HOBC deposits, withdrawals, and wallet security.')) . '">';
    echo '<meta name="robots" content="noindex, follow">';
    echo '<link rel="canonical" href="' . h($canonicalUrl) . '">';
    if (hobc_i18n_enabled()) {
        echo hobc_i18n_hreflang_tags($walletPath), "\n";
        hobc_i18n_render_storage_sync_script();
    }
    echo '<meta property="og:site_name" content="HobbyHash Coin">';
    echo '<meta property="og:type" content="website">';
    echo '<meta property="og:title" content="' . h($title) . ' | HOBC Web Wallet">';
    echo '<meta property="og:description" content="HobbyHash Coin custodial web wallet account area for HOBC deposits, withdrawals, and wallet security.">';
    echo '<meta property="og:url" content="' . h($canonicalUrl) . '">';
    echo '<meta property="og:image" content="https://hobbyhashcoin.com/assets/icons/favicon.svg">';
    echo '<meta name="twitter:card" content="summary">';
    echo '<meta name="twitter:title" content="' . h($title) . ' | HOBC Web Wallet">';
    echo '<meta name="twitter:description" content="HobbyHash Coin custodial web wallet account area for HOBC deposits, withdrawals, and wallet security.">';
    echo '<meta name="twitter:image" content="https://hobbyhashcoin.com/assets/icons/favicon.svg">';
    require __DIR__ . '/../includes/icon-meta.php';
    echo '<link rel="stylesheet" href="/assets/css/hobc.css">';
    echo '<link rel="stylesheet" href="/assets/css/hobc-wallet.css">';
    echo '</head><body class="wallet-app">';
    $skipLabel = function_exists('hobc_t') ? hobc_t('wallet.skip_to_content', [], 'Skip to wallet content') : 'Skip to wallet content';
    echo '<a class="skip-link" href="#wallet-content">' . h($skipLabel) . '</a>';
    echo '<header class="site-header wallet-header">';
    echo '<a class="brand" href="/" aria-label="' . h(hobc_t('header.home_dashboard', [], 'Home/Dashboard')) . '">';
    echo '<img src="/assets/images/logo-round.png" alt="HOBC logo" class="brand-logo">';
    $custodialLabel = function_exists('hobc_t') ? hobc_t('wallet.custodial_label', [], 'Custodial web wallet') : 'Custodial web wallet';
    echo '<span><strong>HobbyHash Coin</strong><small>' . h($custodialLabel) . '</small></span></a>';
    echo '<img src="/assets/images/wordmark-wide.png" alt="HOBC Hobby Hash Coin wordmark" class="header-wordmark">';
    echo '<div class="header-actions">';
    if (function_exists('hobc_i18n_render_language_switcher')) {
        hobc_i18n_render_language_switcher();
    }
    $menuLabel = function_exists('hobc_t') ? hobc_t('header.menu', [], 'Menu') : 'Menu';
    echo '<button type="button" class="mobile-menu-toggle" data-mobile-menu-toggle aria-expanded="false" hidden>' . h($menuLabel) . '</button>';
    echo '</div>';
    echo '</header>';
    $portalNavLabel = function_exists('hobc_t') ? hobc_t('wallet.portal_nav', [], 'Portal navigation') : 'Portal navigation';
    echo '<nav class="site-nav wallet-portal-nav" aria-label="' . h($portalNavLabel) . '">';
    foreach (hobc_pages() as $page) {
        $class = $page['key'] === 'wallet' ? ' class="active"' : '';
        echo '<a href="' . h($page['url']) . '"' . $class . '>' . h($page['label']) . '</a>';
    }
    echo '</nav>';
    $liveStatusLabel = function_exists('hobc_t') ? hobc_t('wallet.live_status', [], 'HOBC live status') : 'HOBC live status';
    echo '<section class="status-bar" aria-label="' . h($liveStatusLabel) . '">';
    $syncing = function_exists('hobc_t') ? hobc_t('status.syncing', [], 'Syncing') : 'Syncing';
    $offline = function_exists('hobc_t') ? hobc_t('status.offline', [], 'Offline') : 'Offline';
    echo '<a href="' . h(wallet_pp('/stats/')) . '" class="status-pill"><span>' . h(function_exists('hobc_t') ? hobc_t('status.chain', [], 'Chain') : 'Chain') . '</span><strong data-api-value="/api/chain/status" data-field="status" data-fallback="' . h($syncing) . '">' . h($syncing) . '</strong></a>';
    echo '<a href="' . h(wallet_pp('/stats/')) . '" class="status-pill"><span>' . h(function_exists('hobc_t') ? hobc_t('status.height', [], 'Height') : 'Height') . '</span><strong data-api-value="/api/chain/status" data-field="blocks" data-fallback="' . h($syncing) . '">' . h($syncing) . '</strong></a>';
    echo '<a href="' . h(wallet_pp('/pool/main/')) . '" class="status-pill"><span>' . h(function_exists('hobc_t') ? hobc_t('status.main_pool', [], 'Main Pool') : 'Main Pool') . '</span><strong data-api-value="/api/pool/main/status" data-field="status" data-fallback="' . h($offline) . '">' . h($offline) . '</strong></a>';
    echo '<a href="' . h(wallet_pp('/pool/nano/')) . '" class="status-pill"><span>' . h(function_exists('hobc_t') ? hobc_t('status.nano_pool', [], 'Nano Pool') : 'Nano Pool') . '</span><strong data-api-value="/api/pool/nano/status" data-field="status" data-fallback="' . h($offline) . '">' . h($offline) . '</strong></a>';
    echo '<a href="' . h(wallet_url('/')) . '" class="status-pill"><span>' . h(function_exists('hobc_t') ? hobc_t('status.wallet', [], 'Wallet') : 'Wallet') . '</span><strong data-api-value="/api/wallet/status" data-field="status" data-fallback="' . h($offline) . '">' . h($offline) . '</strong></a>';
    echo '<a href="' . h(wallet_pp('/explorer/')) . '" class="status-pill"><span>' . h(function_exists('hobc_t') ? hobc_t('status.explorer', [], 'Explorer') : 'Explorer') . '</span><strong data-api-value="/api/explorer/status" data-field="status" data-fallback="' . h($syncing) . '">' . h($syncing) . '</strong></a>';
    echo '</section>';
    echo '<main id="wallet-content" class="wallet-main"><div class="page">';
    $webWalletLabel = function_exists('hobc_t') ? hobc_t('wallet.web_wallet', [], 'HOBC Web Wallet') : 'HOBC Web Wallet';
    echo '<section class="wallet-title card"><span class="eyebrow">' . h($webWalletLabel) . '</span><h1>' . h($title) . '</h1>';
    echo '<p class="wallet-risk">' . hobc_t('wallet.risk_notice', [], '<strong>Custodial risk notice:</strong> The HOBC web wallet is custodial. The website controls wallet keys and funds until you withdraw. Use a local wallet for larger balances.') . '</p></section>';
    echo '<nav class="subnav wallet-nav" aria-label="' . h(hobc_t('wallet.nav.aria', [], 'Wallet navigation')) . '">';
    if ($user) {
        echo '<a class="button" href="' . h(wallet_url('/dashboard.php')) . '">' . h(hobc_t('wallet.nav.dashboard', [], 'Dashboard')) . '</a>';
        echo '<a class="button" href="' . h(wallet_url('/deposit.php')) . '">' . h(hobc_t('wallet.nav.receive', [], 'Receive')) . '</a>';
        echo '<a class="button" href="' . h(wallet_url('/withdraw.php')) . '">' . h(hobc_t('wallet.nav.withdraw', [], 'Withdraw')) . '</a>';
        echo '<a class="button" href="' . h(wallet_url('/transactions.php')) . '">' . h(hobc_t('wallet.nav.transactions', [], 'Transactions')) . '</a>';
        echo '<a class="button" href="' . h(wallet_pp('/docs/faq/')) . '">' . h(hobc_t('wallet.nav.faq', [], 'FAQ')) . '</a>';
        echo '<a class="button" href="' . h(wallet_url('/support.php?section=Wallet')) . '">' . h(hobc_t('wallet.nav.support', [], 'Support')) . '</a>';
        echo '<a class="button" href="' . h(wallet_url('/security.php')) . '">' . h(hobc_t('wallet.nav.security', [], 'Security')) . '</a>';
        echo '<a class="button" href="' . h(wallet_url('/logout.php')) . '">' . h(hobc_t('wallet.nav.logout', [], 'Logout')) . '</a>';
    } else {
        echo '<a class="button primary" href="' . h(wallet_url('/login.php')) . '">' . h(hobc_t('wallet.nav.login', [], 'Login')) . '</a>';
        echo '<a class="button" href="' . h(wallet_url('/register.php')) . '">' . h(hobc_t('wallet.nav.register', [], 'Register')) . '</a>';
        echo '<a class="button" href="' . h(wallet_pp('/docs/faq/')) . '">' . h(hobc_t('wallet.nav.faq', [], 'FAQ')) . '</a>';
        echo '<a class="button" href="' . h(wallet_pp('/contact/')) . '">' . h(hobc_t('wallet.nav.contact', [], 'Contact')) . '</a>';
    }
    echo '</nav>';
}

function render_footer(): void
{
    echo '</div></main>';
    echo '<footer class="site-footer wallet-footer"><div><img src="/assets/images/logo-medallion.png" alt="HOBC coin logo" class="footer-logo"><p><strong>' . h(hobc_t('wallet.footer.title', [], 'HOBC Web Wallet')) . '</strong><br>' . h(hobc_t('wallet.footer.blurb', [], 'Custodial wallet for convenient HOBC deposits and withdrawals.')) . '</p></div>';
    echo '<div class="footer-links"><a href="' . h(wallet_pp('/')) . '">' . h(hobc_t('wallet.footer.portal_home', [], 'Portal home')) . '</a><a href="' . h(wallet_url('/')) . '">' . h(hobc_t('wallet.footer.overview', [], 'Wallet overview')) . '</a><a href="' . h(wallet_pp('/docs/')) . '">' . h(hobc_t('wallet.footer.docs', [], 'Wallet docs')) . '</a><a href="' . h(wallet_pp('/contact/')) . '">' . h(hobc_t('wallet.footer.support', [], 'Support')) . '</a><a href="' . h(wallet_pp('/privacy/')) . '">' . h(hobc_t('wallet.footer.privacy', [], 'Privacy Policy')) . '</a><a href="' . h(wallet_pp('/terms/')) . '">' . h(hobc_t('wallet.footer.terms', [], 'Terms')) . '</a></div>';
    if (function_exists('hobc_i18n_render_language_switcher') && hobc_i18n_enabled()) {
        echo '<div class="footer-lang">';
        hobc_i18n_render_language_switcher('footer');
        echo '</div>';
    }
    echo '<p class="fine-print">' . h(hobc_t('wallet.footer.notice', [], 'Custodial wallet. Do not store large balances. No fake balances, deposits, withdrawals, or txids are shown.')) . '</p></footer>';
    if (function_exists('hobc_i18n_render_js_bootstrap')) {
        hobc_i18n_render_js_bootstrap();
    }
    $langVersion = (string)@filemtime(__DIR__ . '/../assets/js/hobc-lang.js');
    echo '<script src="/assets/js/hobc-lang.js?v=' . h($langVersion !== '' ? $langVersion : '1') . '" defer></script>';
    echo '<script src="/assets/js/hobc.js" defer></script></body></html>';
}
