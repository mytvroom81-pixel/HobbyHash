<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/site_status.php';

site_status_gate();

if (!function_exists('hobc_pages')) {
    function hobc_pages(): array
    {
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
            ['key' => 'roadmap', 'label' => 'Roadmap', 'url' => '/roadmap/'],
            ['key' => 'faq', 'label' => 'FAQ', 'url' => '/faq.php?section=Wallet'],
            ['key' => 'contact', 'label' => 'Contact/Support', 'url' => '/contact.php?section=Wallet'],
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
        return '<span class="' . h($class) . ' empty" title="Not available">Not available</span>';
    }
    return '<span class="' . h($class) . '" title="' . h($value) . '">' . h(wallet_short_text($value)) . '</span>';
}

function wallet_address_text(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '<span class="address-full empty">Not available</span>';
    }
    return '<span class="address-full">' . h($value) . '</span>';
}

function wallet_display_receive_label(?string $label): string
{
    $label = trim((string)$label);
    if ($label === '' || $label === 'Receive Wallet' || preg_match('/^user_\d+_\d+$/', $label) || preg_match('/^Wallet \d+$/', $label)) {
        return 'None';
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
        $html .= '<a class="button" href="' . h(wallet_url($path . '?' . http_build_query($prev))) . '">Previous 10</a>';
    }
    $html .= '<span class="button">Page ' . h((string)$currentPage) . ' of ' . h((string)$totalPages) . '</span>';
    if ($currentPage < $totalPages) {
        $next = array_merge($params, [$pageParam => $currentPage + 1]);
        $html .= '<a class="button" href="' . h(wallet_url($path . '?' . http_build_query($next))) . '">Next 10</a>';
    }
    $html .= '</div>';
    return $html;
}

function render_header(string $title): void
{
    $user = auth_current_user();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . h($title) . ' | HOBC Web Wallet</title>';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<link rel="stylesheet" href="/assets/css/hobc.css">';
    echo '<link rel="stylesheet" href="/assets/css/hobc-wallet.css">';
    echo '</head><body class="wallet-app">';
    echo '<a class="skip-link" href="#wallet-content">Skip to wallet content</a>';
    echo '<header class="site-header wallet-header">';
    echo '<a class="brand" href="/" aria-label="HOBC home dashboard">';
    echo '<img src="/assets/images/logo-round.png" alt="HOBC logo" class="brand-logo">';
    echo '<span><strong>HobbyHash Coin</strong><small>Custodial web wallet</small></span></a>';
    echo '<img src="/assets/images/wordmark-wide.png" alt="HOBC Hobby Hash Coin wordmark" class="header-wordmark">';
    echo '<a class="dashboard-link" href="/">Home/Dashboard</a>';
    echo '<button type="button" class="mobile-menu-toggle" data-mobile-menu-toggle aria-expanded="false">Menu</button>';
    echo '</header>';
    echo '<section class="status-bar" aria-label="HOBC live status">';
    echo '<a href="/stats/" class="status-pill"><span>Chain</span><strong data-api-value="/api/chain/status" data-field="status" data-fallback="Syncing">Syncing</strong></a>';
    echo '<a href="/stats/" class="status-pill"><span>Height</span><strong data-api-value="/api/chain/status" data-field="blocks" data-fallback="Syncing">Syncing</strong></a>';
    echo '<a href="/pool/main/" class="status-pill"><span>Main Pool</span><strong data-api-value="/api/pool/main/status" data-field="status" data-fallback="Offline">Offline</strong></a>';
    echo '<a href="/pool/nano/" class="status-pill"><span>Nano Pool</span><strong data-api-value="/api/pool/nano/status" data-field="status" data-fallback="Offline">Offline</strong></a>';
    echo '<a href="/wallet/" class="status-pill"><span>Wallet</span><strong data-api-value="/api/wallet/status" data-field="status" data-fallback="Offline">Offline</strong></a>';
    echo '<a href="/explorer/" class="status-pill"><span>Explorer</span><strong data-api-value="/api/explorer/status" data-field="status" data-fallback="Syncing">Syncing</strong></a>';
    echo '</section>';
    echo '<nav class="site-nav wallet-portal-nav" aria-label="Portal navigation">';
    foreach (hobc_pages() as $page) {
        $class = $page['key'] === 'wallet' ? ' class="active"' : '';
        echo '<a href="' . h($page['url']) . '"' . $class . '>' . h($page['label']) . '</a>';
    }
    echo '</nav>';
    echo '<main id="wallet-content" class="wallet-main"><div class="page">';
    echo '<section class="wallet-title card"><span class="eyebrow">HOBC Web Wallet</span><h1>' . h($title) . '</h1>';
    echo '<p class="wallet-risk"><strong>Custodial risk notice:</strong> The HOBC web wallet is custodial. The website controls wallet keys and funds until you withdraw. Use a local wallet for larger balances.</p></section>';
    echo '<nav class="subnav wallet-nav" aria-label="Wallet navigation">';
    if ($user) {
        echo '<a class="button" href="' . h(wallet_url('/dashboard.php')) . '">Dashboard</a>';
        echo '<a class="button" href="' . h(wallet_url('/deposit.php')) . '">Receive</a>';
        echo '<a class="button" href="' . h(wallet_url('/withdraw.php')) . '">Withdraw</a>';
        echo '<a class="button" href="' . h(wallet_url('/transactions.php')) . '">Transactions</a>';
        echo '<a class="button" href="' . h('/faq.php?section=Wallet') . '">FAQ</a>';
        echo '<a class="button" href="' . h(wallet_url('/support.php?section=Wallet')) . '">Support</a>';
        echo '<a class="button" href="' . h(wallet_url('/security.php')) . '">Security</a>';
        echo '<a class="button" href="' . h(wallet_url('/logout.php')) . '">Logout</a>';
    } else {
        echo '<a class="button primary" href="' . h(wallet_url('/login.php')) . '">Login</a>';
        echo '<a class="button" href="' . h(wallet_url('/register.php')) . '">Register</a>';
        echo '<a class="button" href="' . h('/faq.php?section=Wallet') . '">FAQ</a>';
        echo '<a class="button" href="' . h('/contact.php?section=Wallet') . '">Contact</a>';
    }
    echo '</nav>';
}

function render_footer(): void
{
    echo '</div></main>';
    echo '<footer class="site-footer wallet-footer"><div><img src="/assets/images/logo-medallion.png" alt="HOBC coin logo" class="footer-logo"><p><strong>HOBC Web Wallet</strong><br>Custodial wallet for convenient HOBC deposits and withdrawals.</p></div>';
    echo '<div class="footer-links"><a href="/">Portal home</a><a href="/wallet/">Wallet overview</a><a href="/docs/">Wallet docs</a><a href="/contact/">Support</a><a href="/privacy/">Privacy Policy</a><a href="/terms/">Terms</a></div>';
    echo '<p class="fine-print">Custodial wallet. Do not store large balances. No fake balances, deposits, withdrawals, or txids are shown.</p></footer>';
    echo '<script src="/assets/js/hobc.js" defer></script></body></html>';
}
