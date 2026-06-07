<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_permissions.php';
require_once __DIR__ . '/admin_datetime.php';

function admin_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

function admin_nav_groups(): array
{
    $sectionUrl = static fn(string $key): string => admin_url('/section.php?section=' . rawurlencode($key));

    return [
        [
            'label' => 'Overview',
            'items' => [
                ['label' => 'Dashboard', 'url' => admin_url('/index.php'), 'match' => ['/admin/index.php'], 'permission' => 'dashboard'],
            ],
        ],
        [
            'label' => 'Analytics',
            'items' => [
                ['label' => 'Site Analytics', 'url' => admin_url('/analytics.php'), 'match' => ['/admin/analytics.php'], 'tab' => 'overview', 'permission' => 'analytics'],
                ['label' => 'Visitors', 'url' => admin_url('/analytics.php?tab=visitors'), 'match' => ['/admin/analytics.php'], 'tab' => 'visitors', 'permission' => 'visitors'],
                ['label' => 'Bots & Crawlers', 'url' => admin_url('/bots.php'), 'match' => ['/admin/bots.php'], 'permission' => 'bots'],
                ['label' => 'Traffic Sources', 'url' => admin_url('/analytics.php?tab=referrers'), 'match' => ['/admin/analytics.php'], 'tab' => 'referrers', 'permission' => 'analytics'],
            ],
        ],
        [
            'label' => 'Content',
            'items' => [
                ['label' => 'Pages & Content', 'url' => admin_url('/content.php?tab=pages'), 'match' => ['/admin/content.php'], 'content_tab' => 'pages', 'permission' => 'docs'],
                ['label' => 'Downloads', 'url' => admin_url('/content.php?tab=downloads'), 'match' => ['/admin/content.php'], 'content_tab' => 'downloads', 'permission' => 'downloads'],
                ['label' => 'Docs', 'url' => admin_url('/content.php?tab=docs'), 'match' => ['/admin/content.php'], 'content_tab' => 'docs', 'permission' => 'docs'],
                ['label' => 'Announcements', 'url' => admin_url('/content.php?tab=announcements'), 'match' => ['/admin/content.php'], 'content_tab' => 'announcements', 'permission' => 'announcements'],
                ['label' => 'Burn Events', 'url' => admin_url('/content.php?tab=burn'), 'match' => ['/admin/content.php'], 'content_tab' => 'burn', 'permission' => 'burn_events'],
                ['label' => 'Exchange Listing', 'url' => admin_url('/exchange-listing.php'), 'match' => ['/admin/exchange-listing.php'], 'permission' => 'exchange_listing'],
                ['label' => 'Support Messages', 'url' => admin_url('/content.php?tab=support'), 'match' => ['/admin/content.php'], 'content_tab' => 'support', 'permission' => 'support_messages'],
            ],
        ],
        [
            'label' => 'Wallets & Users',
            'items' => [
                ['label' => 'Wallets', 'url' => $sectionUrl('wallets'), 'section' => 'wallets', 'permission' => 'wallet_controls'],
                ['label' => 'Custodial Wallet Controls', 'url' => admin_url('/wallet.php'), 'match' => ['/admin/wallet.php'], 'permission' => 'wallet_controls'],
                ['label' => 'Withdrawals', 'url' => admin_url('/withdrawals.php'), 'match' => ['/admin/withdrawals.php'], 'permission' => 'withdrawals'],
                ['label' => 'Users', 'url' => $sectionUrl('users'), 'section' => 'users', 'permission' => 'users'],
            ],
        ],
        [
            'label' => 'Mining & Chain',
            'items' => [
                ['label' => 'Mining Pool', 'url' => admin_url('/mining-pool.php'), 'match' => ['/admin/mining-pool.php'], 'permission' => 'mining_pool'],
                ['label' => 'Nodes', 'url' => admin_url('/node.php'), 'match' => ['/admin/node.php'], 'permission' => 'nodes'],
                ['label' => 'Blockchain Stats', 'url' => admin_url('/blockchain.php'), 'match' => ['/admin/blockchain.php'], 'permission' => 'explorer'],
                ['label' => 'Explorer Stats', 'url' => admin_url('/explorer.php'), 'match' => ['/admin/explorer.php'], 'permission' => 'explorer'],
                ['label' => 'Reserve / Treasury', 'url' => admin_url('/reserve.php'), 'match' => ['/admin/reserve.php'], 'permission' => 'treasury_reserve'],
            ],
        ],
        [
            'label' => 'Integrations',
            'items' => [
                ['label' => 'Social Bot', 'url' => admin_url('/social-bot.php'), 'match' => ['/admin/social-bot.php'], 'permission' => 'social_bot'],
            ],
        ],
        [
            'label' => 'Security & Support',
            'items' => [
                ['label' => 'Security Center', 'url' => admin_url('/security.php'), 'match' => ['/admin/security.php'], 'permission' => 'security_center'],
                ['label' => 'Support Tickets', 'url' => admin_url('/tickets.php'), 'match' => ['/admin/tickets.php'], 'permission' => 'support_messages'],
                ['label' => 'Admin Users & Roles', 'url' => admin_url('/admin-users.php'), 'match' => ['/admin/admin-users.php'], 'permission' => 'admin_users'],
                ['label' => 'Audit Logs', 'url' => admin_url('/audit.php'), 'match' => ['/admin/audit.php'], 'permission' => 'audit_logs'],
            ],
        ],
        [
            'label' => 'System',
            'items' => [
                ['label' => 'System Health', 'url' => $sectionUrl('system-health'), 'section' => 'system-health', 'permission' => 'settings'],
                ['label' => 'Settings', 'url' => admin_url('/settings.php'), 'match' => ['/admin/settings.php', '/admin/site-config.php', '/admin/smtp.php', '/admin/authenticator.php'], 'permission' => 'settings'],
            ],
        ],
    ];
}

function admin_nav_items(): array
{
    $items = [];
    foreach (admin_nav_groups() as $group) {
        foreach ($group['items'] as $item) {
            $items[] = $item;
        }
    }

    return $items;
}

function admin_nav_item_is_active(array $item, string $currentScript, string $currentSection, string $currentTab): bool
{
    $isContentTabActive = (($item['content_tab'] ?? '') !== '' && $currentScript === '/admin/content.php' && $currentTab === $item['content_tab']);
    $isSectionActive = (($item['section'] ?? '') !== '' && $currentSection === $item['section']);
    $isTabActive = (($item['tab'] ?? '') !== '' && $currentScript === '/admin/analytics.php' && $currentTab === $item['tab'])
        || (($item['tab'] ?? '') === 'overview' && $currentScript === '/admin/analytics.php' && !in_array($currentTab, ['visitors', 'crawlers', 'referrers'], true));
    $isRouteActive = in_array($currentScript, (array)($item['match'] ?? []), true) && ($item['tab'] ?? '') === '' && ($item['content_tab'] ?? '') === '';

    return $isContentTabActive || $isSectionActive || $isTabActive || $isRouteActive;
}

function admin_current_nav_label(): string
{
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $section = (string)($_GET['section'] ?? '');
    $tab = (string)($_GET['tab'] ?? 'overview');
    foreach (admin_nav_items() as $item) {
        if (($item['content_tab'] ?? '') !== '' && $script === '/admin/content.php' && $tab === $item['content_tab']) {
            return (string)$item['label'];
        }
        if (($item['section'] ?? '') !== '' && $section === $item['section']) {
            return (string)$item['label'];
        }
        if (($item['tab'] ?? '') === 'overview' && $script === '/admin/analytics.php' && !in_array($tab, ['visitors', 'crawlers', 'referrers'], true)) {
            return (string)$item['label'];
        }
        if (($item['tab'] ?? '') !== '' && $script === '/admin/analytics.php' && $tab === $item['tab']) {
            return (string)$item['label'];
        }
        if (in_array($script, (array)($item['match'] ?? []), true)) {
            return (string)$item['label'];
        }
    }
    return 'Dashboard';
}

function admin_flash_set(string $type, string $message): void
{
    wallet_start_session();
    $_SESSION['admin_flash'][] = ['type' => $type, 'message' => $message];
}

function admin_flash_take(): array
{
    wallet_start_session();
    $messages = $_SESSION['admin_flash'] ?? [];
    unset($_SESSION['admin_flash']);
    return is_array($messages) ? $messages : [];
}

function admin_render_alert(string $type, string $message): void
{
    $class = $type === 'error' ? 'admin-alert-error' : ($type === 'warning' ? 'admin-alert-warning' : 'admin-alert-success');
    echo '<div class="admin-alert ' . h($class) . '" role="status">' . h($message) . '</div>';
}

function admin_render_flash_messages(): void
{
    foreach (admin_flash_take() as $message) {
        if (!is_array($message)) {
            continue;
        }
        admin_render_alert((string)($message['type'] ?? 'success'), (string)($message['message'] ?? ''));
    }
}

function admin_breadcrumbs(array $breadcrumbs, string $title): array
{
    $items = [['label' => 'Admin', 'url' => admin_url('/index.php')]];
    foreach ($breadcrumbs as $breadcrumb) {
        if (is_array($breadcrumb)) {
            $items[] = [
                'label' => (string)($breadcrumb['label'] ?? ''),
                'url' => (string)($breadcrumb['url'] ?? ''),
            ];
        } else {
            $items[] = ['label' => (string)$breadcrumb, 'url' => ''];
        }
    }
    if ($breadcrumbs === [] || end($items)['label'] !== $title) {
        $items[] = ['label' => $title, 'url' => ''];
    }
    return $items;
}

function render_admin_header(string $title, array $breadcrumbs = []): void
{
    admin_security_headers();
    $admin = admin_current_user();
    $currentScript = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $currentSection = (string)($_GET['section'] ?? '');
    $currentTab = (string)($_GET['tab'] ?? 'overview');
    $activeLabel = admin_current_nav_label();
    $visibleNavGroups = [];
    foreach (admin_nav_groups() as $group) {
        $visibleItems = array_values(array_filter(
            $group['items'],
            static fn(array $item): bool => !$admin || admin_can($admin, (string)($item['permission'] ?? 'dashboard'))
        ));
        if ($visibleItems === []) {
            continue;
        }
        $visibleNavGroups[] = [
            'label' => (string)$group['label'],
            'items' => $visibleItems,
        ];
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . h($title) . ' | HOBC Master Admin</title>';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    require __DIR__ . '/../includes/icon-meta.php';
    echo '<link rel="stylesheet" href="/assets/css/hobc-admin.css">';
    echo '<script src="/assets/js/hobc-admin.js" defer></script>';
    echo '</head><body class="admin-body">';
    echo '<div class="admin-shell" data-admin-shell>';
    echo '<div class="admin-mobile-backdrop" data-admin-menu-close></div>';
    echo '<aside class="admin-sidebar" data-admin-sidebar aria-label="Admin navigation">';
    echo '<div class="admin-brand"><img src="/assets/images/logo-round.png" alt="HOBC logo"><div><h2>HOBC Admin</h2><small>Master control center</small></div></div>';
    echo '<nav class="admin-nav">';
    foreach ($visibleNavGroups as $group) {
        echo '<div class="admin-nav-group">';
        echo '<div class="admin-nav-group-label">' . h((string)$group['label']) . '</div>';
        echo '<div class="admin-nav-group-links">';
        foreach ($group['items'] as $item) {
            $class = admin_nav_item_is_active($item, $currentScript, $currentSection, $currentTab) ? ' class="is-active"' : '';
            echo '<a' . $class . ' href="' . h((string)$item['url']) . '">' . h((string)$item['label']) . '</a>';
        }
        echo '</div></div>';
    }
    echo '</nav>';
    echo '<div class="admin-note"><b>Protected Admin</b><br>Use real collectors and stored data only. Empty sections show "No data yet" until they are measured.</div>';
    echo '</aside>';
    echo '<main class="admin-main">';
    echo '<header class="admin-topbar">';
    echo '<button type="button" class="admin-menu-button" data-admin-menu-toggle aria-expanded="false">Menu</button>';
    echo '<div class="admin-topbar-title"><span>' . h($activeLabel) . '</span><strong>' . h($title) . '</strong></div>';
    echo '<div class="admin-topbar-actions">';
    echo '<span class="admin-user-pill">' . ($admin ? 'Signed in as ' . h((string)$admin['username']) : 'Restricted access') . '</span>';
    echo '<a class="admin-action admin-action-secondary" href="' . h(wallet_url('/dashboard.php')) . '">User Wallet</a>';
    echo '<a class="admin-action admin-action-danger" href="' . h(admin_url('/logout.php')) . '">Logout</a>';
    echo '</div></header>';
    echo '<div class="admin-content">';
    echo '<nav class="admin-breadcrumbs" aria-label="Breadcrumbs">';
    $breadcrumbItems = admin_breadcrumbs($breadcrumbs, $title);
    $breadcrumbCount = count($breadcrumbItems);
    foreach ($breadcrumbItems as $index => $breadcrumb) {
        if ($index > 0) {
            echo '<span aria-hidden="true">/</span>';
        }
        $label = (string)$breadcrumb['label'];
        $url = (string)$breadcrumb['url'];
        if ($url !== '' && $index < $breadcrumbCount - 1) {
            echo '<a href="' . h($url) . '">' . h($label) . '</a>';
        } else {
            echo '<span>' . h($label) . '</span>';
        }
    }
    echo '</nav>';
    echo '<div class="admin-page-heading"><h1>' . h($title) . '</h1></div>';
    admin_render_flash_messages();
}

function render_admin_footer(): void
{
    echo '</div></main></div>';
    echo '<div class="admin-confirm-modal" data-admin-confirm-modal aria-hidden="true">';
    echo '<div class="admin-confirm-card" role="dialog" aria-modal="true" aria-labelledby="admin-confirm-title">';
    echo '<h3 id="admin-confirm-title">Confirm Action</h3>';
    echo '<p data-admin-confirm-message>This action needs confirmation.</p>';
    echo '<div class="admin-actions">';
    echo '<button type="button" class="admin-action admin-action-secondary" data-admin-confirm-cancel>Cancel</button>';
    echo '<button type="button" class="admin-action admin-action-danger" data-admin-confirm-accept>Confirm</button>';
    echo '</div></div></div>';
    echo '</body></html>';
}

function admin_stat_card(string $label, string $value, string $tone = '', string $subtext = ''): string
{
    $toneClass = $tone !== '' ? ' admin-stat-' . preg_replace('/[^a-z0-9_-]/i', '', $tone) : '';
    return '<div class="admin-stat-card' . h($toneClass) . '"><span>' . h($label) . '</span><strong>' . h($value) . '</strong>' . ($subtext !== '' ? '<small>' . h($subtext) . '</small>' : '') . '</div>';
}

function admin_dashboard_stat_card(array $stat, string $href = ''): string
{
    $id = (string)($stat['id'] ?? '');
    $label = (string)($stat['label'] ?? '');
    $value = (string)($stat['value'] ?? '');
    $tone = (string)($stat['tone'] ?? '');
    $subtext = (string)($stat['subtext'] ?? '');
    $toneClass = $tone !== '' ? ' admin-stat-' . preg_replace('/[^a-z0-9_-]/i', '', $tone) : '';
    $attrs = $id !== '' ? ' data-admin-stat="' . h($id) . '"' : '';
    $inner = '<span>' . h($label) . '</span><strong data-admin-stat-value>' . h($value) . '</strong>';
    if ($subtext !== '') {
        $inner .= '<small data-admin-stat-subtext>' . h($subtext) . '</small>';
    }
    if ($href !== '') {
        return '<a class="admin-stat-card admin-stat-link' . h($toneClass) . '"' . $attrs . ' href="' . h($href) . '">' . $inner . '</a>';
    }

    return '<div class="admin-stat-card' . h($toneClass) . '"' . $attrs . '>' . $inner . '</div>';
}

function admin_render_dashboard_section(array $section): void
{
    $stats = is_array($section['stats'] ?? null) ? $section['stats'] : [];
    if ($stats === []) {
        return;
    }
    $href = (string)($section['href'] ?? '');
    $hrefLabel = (string)($section['href_label'] ?? 'Open section');
    echo '<section class="admin-dashboard-section">';
    echo '<div class="admin-dashboard-section-head">';
    echo '<div><h3>' . h((string)($section['title'] ?? 'Section')) . '</h3>';
    if (($section['description'] ?? '') !== '') {
        echo '<p>' . h((string)$section['description']) . '</p>';
    }
    echo '</div>';
    if ($href !== '') {
        echo admin_action_button($hrefLabel, $href, 'secondary');
    }
    echo '</div>';
    echo '<div class="admin-grid admin-grid-tight">';
    foreach ($stats as $stat) {
        if (!is_array($stat)) {
            continue;
        }
        echo admin_dashboard_stat_card($stat);
    }
    echo '</div></section>';
}

function admin_render_dashboard_alerts(array $alerts): void
{
    if ($alerts === []) {
        return;
    }
    echo '<div class="admin-dashboard-alerts">';
    foreach ($alerts as $alert) {
        if (!is_array($alert)) {
            continue;
        }
        admin_render_alert((string)($alert['type'] ?? 'warning'), (string)($alert['message'] ?? ''));
    }
    echo '</div>';
}

function admin_empty_state(string $title, string $message, string $actionHtml = ''): string
{
    return '<div class="admin-empty"><h3>' . h($title) . '</h3><p>' . h($message) . '</p>' . $actionHtml . '</div>';
}

function admin_action_button(string $label, string $href, string $tone = 'primary', array $attrs = []): string
{
    $attr = '';
    foreach ($attrs as $key => $value) {
        if ($value === null) {
            continue;
        }
        $attr .= ' ' . h((string)$key) . '="' . h((string)$value) . '"';
    }
    return '<a class="admin-action admin-action-' . h($tone) . '" href="' . h($href) . '"' . $attr . '>' . h($label) . '</a>';
}

function admin_render_table(array $headers, array $rows, string $emptyTitle = 'No data yet', string $emptyMessage = 'Collector/storage has not recorded data for this section yet.'): void
{
    if ($rows === []) {
        echo admin_empty_state($emptyTitle, $emptyMessage);
        return;
    }
    echo '<div class="admin-table-wrap"><table class="admin-table" data-admin-filter-table><thead><tr>';
    foreach ($headers as $header) {
        echo '<th>' . h((string)$header) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . (string)$cell . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function admin_filter_box(string $label = 'Filter table', string $placeholder = 'Search this section'): void
{
    echo '<div class="admin-filter-box">';
    echo '<label>' . h($label) . '<input type="search" data-admin-table-filter placeholder="' . h($placeholder) . '"></label>';
    echo '</div>';
}

function admin_pagination(int $page, int $totalPages, string $baseUrl, array $params = [], ?int $totalRows = null): void
{
    if ($totalPages <= 1) {
        return;
    }
    $page = max(1, min($page, $totalPages));
    echo '<nav class="admin-pagination" aria-label="Pagination">';
    if ($totalRows !== null) {
        echo '<span class="admin-pagination-meta">' . h(number_format($totalRows)) . ' total</span>';
    }
    if ($page > 1) {
        $query = array_merge($params, ['page' => $page - 1]);
        echo '<a class="admin-pagination-nav" href="' . h($baseUrl . '?' . http_build_query($query)) . '">Prev</a>';
    }
    $window = 2;
    $start = max(1, $page - $window);
    $end = min($totalPages, $page + $window);
    if ($start > 1) {
        $query = array_merge($params, ['page' => 1]);
        echo '<a href="' . h($baseUrl . '?' . http_build_query($query)) . '">1</a>';
        if ($start > 2) {
            echo '<span class="admin-pagination-ellipsis">…</span>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $query = array_merge($params, ['page' => $i]);
        $href = $baseUrl . '?' . http_build_query($query);
        $class = $i === $page ? ' class="is-current"' : '';
        echo '<a' . $class . ' href="' . h($href) . '">' . h((string)$i) . '</a>';
    }
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            echo '<span class="admin-pagination-ellipsis">…</span>';
        }
        $query = array_merge($params, ['page' => $totalPages]);
        echo '<a href="' . h($baseUrl . '?' . http_build_query($query)) . '">' . h((string)$totalPages) . '</a>';
    }
    if ($page < $totalPages) {
        $query = array_merge($params, ['page' => $page + 1]);
        echo '<a class="admin-pagination-nav" href="' . h($baseUrl . '?' . http_build_query($query)) . '">Next</a>';
    }
    echo '</nav>';
}

function admin_page_state(int $defaultPerPage = 50): array
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(10, min(200, (int)($_GET['per_page'] ?? $defaultPerPage)));
    return ['page' => $page, 'per_page' => $perPage];
}

function admin_pagination_meta(int $page, int $perPage, int $totalRows): array
{
    $totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
    $page = min($page, $totalPages);
    return [
        'page' => $page,
        'per_page' => $perPage,
        'total_rows' => $totalRows,
        'total_pages' => $totalPages,
        'offset' => ($page - 1) * $perPage,
    ];
}

function admin_short_text(?string $value, int $start = 16, int $end = 10): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }
    return strlen($text) > ($start + $end + 3) ? substr($text, 0, $start) . '...' . substr($text, -$end) : $text;
}

function admin_explorer_url(string $value): string
{
    return '/explorer/?q=' . rawurlencode(trim($value));
}

function admin_explorer_link(string $value, ?string $label = null): string
{
    $text = trim($value);
    if ($text === '') {
        return '';
    }
    $display = $label ?? admin_short_text($text);
    return '<a class="admin-explorer-link" href="' . h(admin_explorer_url($text)) . '" target="_blank" rel="noopener noreferrer" title="' . h($text) . '">' . h($display) . '</a>';
}

function admin_hash_cell(string $value, bool $linkExplorer = false): string
{
    $text = trim($value);
    if ($text === '') {
        return '<span class="admin-mono-empty">—</span>';
    }
    if ($linkExplorer) {
        return '<span class="admin-mono-cell">' . admin_explorer_link($text) . '</span>';
    }
    $short = admin_short_text($text);
    return '<code class="admin-mono-cell" title="' . h($text) . '">' . h($short) . '</code>';
}

function admin_url_cell(string $url, bool $link = true): string
{
    $text = trim($url);
    if ($text === '') {
        return '<span class="admin-mono-empty">—</span>';
    }
    $short = admin_short_text($text, 40, 24);
    if (!$link) {
        return '<span class="admin-url-cell" title="' . h($text) . '">' . h($short) . '</span>';
    }
    return '<a class="admin-url-cell" href="' . h($text) . '" target="_blank" rel="noopener noreferrer" title="' . h($text) . '">' . h($short) . '</a>';
}
