<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/admin_view.php';
require_once __DIR__ . '/../app/admin_migrations.php';
require_once __DIR__ . '/../app/analytics.php';
require_once __DIR__ . '/../app/geoip.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/i18n_catalog.php';

$admin = admin_require_user();
$pdo = wallet_db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_validate_or_fail();
    analytics_handle_geoip_backfill($pdo);
    analytics_handle_utm_delete($pdo);
}

function analytics_tabs(): array
{
    return [
        'overview' => 'Overview',
        'realtime' => 'Real-time visitors',
        'pageviews' => 'Page views',
        'visitors' => 'Unique visitors',
        'sessions' => 'Sessions',
        'referrers' => 'Referrers',
        'utm' => 'UTM campaigns',
        'devices' => 'Devices',
        'browsers' => 'Browsers',
        'os' => 'Operating systems',
        'countries' => 'Countries',
        'cities' => 'Cities',
        'regions' => 'Regions',
        'asn' => 'ASN / ISP',
        'status-codes' => 'Status codes',
        'slow-pages' => 'Slow pages',
        '404-pages' => '404 pages',
        'downloads' => 'Downloads',
        'crawlers' => 'Search engine crawlers',
        'suspicious-bots' => 'Suspicious bots',
    ];
}

function admin_analytics_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->query("SHOW FULL TABLES LIKE " . $pdo->quote($table));
    return (bool)$stmt->fetch();
}

function analytics_require_tables(PDO $pdo, array $tables): array
{
    $missing = [];
    foreach ($tables as $table) {
        if (!admin_analytics_table_exists($pdo, $table)) {
            $missing[] = $table;
        }
    }
    return $missing;
}

function analytics_date_filters(): array
{
    $range = (string)($_GET['range'] ?? '7d');
    $allowed = ['today', '24h', '7d', '30d', 'custom'];
    if (!in_array($range, $allowed, true)) {
        $range = '7d';
    }
    $from = trim((string)($_GET['from'] ?? ''));
    $to = trim((string)($_GET['to'] ?? ''));

    if ($range !== 'custom') {
        $to = analytics_local_date('Y-m-d');
        $from = match ($range) {
            'today' => analytics_local_date('Y-m-d'),
            '24h' => analytics_local_date('Y-m-d', time() - 86400),
            '30d' => analytics_local_date('Y-m-d', time() - 2592000),
            default => analytics_local_date('Y-m-d', time() - 604800),
        };
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = analytics_local_date('Y-m-d', time() - 604800);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to = analytics_local_date('Y-m-d');
    }

    return [
        'range' => $range,
        'from' => $from,
        'to' => $to,
        'from_sql' => $from . ' 00:00:00',
        'to_sql' => $to . ' 23:59:59',
        'search' => trim((string)($_GET['q'] ?? '')),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
        'per_page' => 50,
    ];
}

function analytics_active_window_minutes(): int
{
    $minutes = (int)($_GET['active'] ?? 5);

    return in_array($minutes, [5, 10, 60], true) ? $minutes : 5;
}

function analytics_active_window_label(?int $minutes = null): string
{
    $minutes ??= analytics_active_window_minutes();

    return match ($minutes) {
        10 => 'Last 10 minutes',
        60 => 'Last 60 minutes',
        default => 'Last 5 minutes',
    };
}

function analytics_active_visitors_where(string $alias = ''): string
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    $minutes = analytics_active_window_minutes();

    return "{$prefix}last_seen_at >= DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE) AND {$prefix}is_bot = 0";
}

function analytics_base_params(array $filters, string $tab): array
{
    $params = [
        'tab' => $tab,
        'range' => $filters['range'],
        'from' => $filters['from'],
        'to' => $filters['to'],
        'q' => $filters['search'],
    ];
    if (in_array($tab, ['overview', 'realtime'], true)) {
        $params['active'] = analytics_active_window_minutes();
    }
    $sort = trim((string)($_GET['sort'] ?? ''));
    if ($sort !== '') {
        $params['sort'] = $sort;
    }
    $dir = strtolower(trim((string)($_GET['dir'] ?? '')));
    if (in_array($dir, ['asc', 'desc'], true)) {
        $params['dir'] = $dir;
    }

    return $params;
}

function analytics_sort_link_params(array $extra = [], bool $resetPage = true): array
{
    $params = [];
    foreach (['tab', 'range', 'from', 'to', 'q', 'active', 'detail', 'id', 'utm_source', 'utm_medium', 'utm_campaign'] as $key) {
        if (isset($_GET[$key]) && (string)$_GET[$key] !== '') {
            $params[$key] = (string)$_GET[$key];
        }
    }
    if (!$resetPage && isset($_GET['page']) && (int)$_GET['page'] > 0) {
        $params['page'] = (int)$_GET['page'];
    }
    return array_merge($params, $extra);
}

function analytics_default_sort_key(array $columnDefs): string
{
    foreach ($columnDefs as $column) {
        if (isset($column['sort'], $column['default']) && $column['default'] === true) {
            return (string)$column['sort'];
        }
    }
    foreach ($columnDefs as $column) {
        if (isset($column['sort'])) {
            return (string)$column['sort'];
        }
    }

    return '';
}

function analytics_column_default_dir(array $columnDefs, string $sortKey): string
{
    foreach ($columnDefs as $column) {
        if (($column['sort'] ?? '') === $sortKey) {
            $dir = strtolower((string)($column['default_dir'] ?? 'desc'));
            return $dir === 'asc' ? 'asc' : 'desc';
        }
    }

    return 'desc';
}

function analytics_current_sort(array $columnDefs, ?string $defaultSortKey = null): array
{
    $allowed = [];
    foreach ($columnDefs as $column) {
        if (!isset($column['sort'])) {
            continue;
        }
        $key = (string)$column['sort'];
        $allowed[$key] = (string)($column['sql'] ?? $key);
    }

    $defaultSortKey ??= analytics_default_sort_key($columnDefs);
    if ($defaultSortKey === '' || !isset($allowed[$defaultSortKey])) {
        $defaultSortKey = array_key_first($allowed) ?: '';
    }

    $sortKey = trim((string)($_GET['sort'] ?? $defaultSortKey));
    if (!isset($allowed[$sortKey])) {
        $sortKey = $defaultSortKey;
    }

    $dir = strtolower(trim((string)($_GET['dir'] ?? '')));
    if (!in_array($dir, ['asc', 'desc'], true)) {
        $dir = analytics_column_default_dir($columnDefs, $sortKey);
    }

    return [$sortKey, $dir, $allowed[$sortKey] ?? $sortKey];
}

function analytics_order_sql(array $columnDefs, ?string $defaultSortKey = null): string
{
    [, $dir, $sql] = analytics_current_sort($columnDefs, $defaultSortKey);
    if ($sql === '') {
        return '';
    }

    return ' ORDER BY ' . $sql . ' ' . strtoupper($dir);
}

function analytics_apply_order(string $sql, array $columnDefs, ?string $defaultSortKey = null): string
{
    $sql = preg_replace('/\s+ORDER\s+BY\s+.+$/i', '', trim($sql));

    return $sql . analytics_order_sql($columnDefs, $defaultSortKey);
}

function analytics_sort_header_link(array $column, string $currentSort, string $currentDir, array $linkParams): string
{
    $sortKey = (string)$column['sort'];
    $isActive = $currentSort === $sortKey;
    if ($isActive) {
        $nextDir = $currentDir === 'asc' ? 'desc' : 'asc';
    } else {
        $nextDir = analytics_column_default_dir([$column], $sortKey);
    }

    $params = array_merge(analytics_sort_link_params($linkParams), [
        'sort' => $sortKey,
        'dir' => $nextDir,
    ]);
    $arrow = $isActive ? ($currentDir === 'asc' ? ' ↑' : ' ↓') : '';
    $class = 'analytics-sort-link' . ($isActive ? ' is-active' : '');

    return '<a class="' . h($class) . '" href="' . h(admin_url('/analytics.php?' . http_build_query($params))) . '">' . h((string)$column['label']) . $arrow . '</a>';
}

function analytics_render_sortable_table(array $columnDefs, array $rows, string $emptyTitle, string $emptyMessage, array $linkParams = []): void
{
    if ($rows === []) {
        echo admin_empty_state($emptyTitle, $emptyMessage);
        return;
    }

    [$currentSort, $currentDir] = analytics_current_sort($columnDefs);
    echo '<div class="admin-table-wrap"><table class="admin-table" data-admin-filter-table><thead><tr>';
    foreach ($columnDefs as $column) {
        echo '<th>';
        if (isset($column['sort'])) {
            echo analytics_sort_header_link($column, $currentSort, $currentDir, $linkParams);
        } else {
            echo h((string)$column['label']);
        }
        echo '</th>';
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

function analytics_query_count(PDO $pdo, string $sql, array $params): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function analytics_fetch(PDO $pdo, string $sql, array $params): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function analytics_csv_response(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9_.-]+/i', '-', $filename) . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    if ($out === false) {
        exit;
    }
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, array_map(static fn($value): string => strip_tags((string)$value), $row));
    }
    fclose($out);
    exit;
}

function analytics_where(array $filters, string $alias = ''): array
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    $where = ["{$prefix}created_at BETWEEN ? AND ?"];
    $params = [$filters['from_sql'], $filters['to_sql']];
    $q = (string)$filters['search'];
    if ($q !== '') {
        $where[] = "({$prefix}url LIKE ? OR {$prefix}route_name LIKE ? OR {$prefix}page_title LIKE ? OR {$prefix}referrer LIKE ? OR {$prefix}user_agent LIKE ?)";
        array_push($params, "%{$q}%", "%{$q}%", "%{$q}%", "%{$q}%", "%{$q}%");
    }
    return ['sql' => implode(' AND ', $where), 'params' => $params];
}

function analytics_metric(PDO $pdo, string $sql, array $params): string
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return number_format((float)$stmt->fetchColumn(), 0);
}

function analytics_location_cell(array $row): string
{
    return h(geoip_format_location($row));
}

function analytics_asn_cell(array $row): string
{
    return h(geoip_format_asn($row));
}

function analytics_handle_geoip_backfill(PDO $pdo): void
{
    if (($_POST['action'] ?? '') !== 'geoip_backfill') {
        return;
    }
    $result = geoip_maintain_analytics($pdo, 500);
    $updated = array_sum($result['backfilled']);
    $deleted = array_sum($result['deleted']);
    admin_flash_set('success', 'GeoIP maintenance complete: updated ' . number_format($updated) . ' rows, removed ' . number_format($deleted) . ' legacy rows without IP.');
    wallet_redirect(admin_url('/analytics.php?' . http_build_query(['tab' => (string)($_GET['tab'] ?? 'overview')])));
}

function analytics_row_ip(array $row): string
{
    $ip = trim((string)($row['ip_address'] ?? ''));
    if ($ip !== '') {
        return $ip;
    }
    $hash = trim((string)($row['ip_hash'] ?? ''));
    return $hash !== '' ? 'hash:' . substr($hash, 0, 16) . '...' : 'not_available';
}

function analytics_referrer_cell(string $referrer): string
{
    $referrer = trim($referrer);
    if ($referrer === '' || analytics_is_internal_referrer($referrer)) {
        return h('(direct / none)');
    }

    return admin_url_cell($referrer, true);
}

function analytics_sql_external_referrer_only(string $alias = ''): array
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    $column = "{$prefix}referrer";
    $clauses = ["({$column} IS NULL OR {$column} = '' OR {$column} NOT LIKE '/%')"];
    $params = [];

    foreach (analytics_own_site_hosts() as $host) {
        foreach (['https://', 'http://'] as $scheme) {
            $clauses[] = "{$column} NOT LIKE ?";
            $params[] = $scheme . $host . '/%';
            $clauses[] = "{$column} NOT LIKE ?";
            $params[] = $scheme . $host;
        }
    }

    $clauses[] = "{$column} NOT LIKE ?";
    $params[] = 'https://%.hobbyhashcoin.com/%';
    $clauses[] = "{$column} NOT LIKE ?";
    $params[] = 'http://%.hobbyhashcoin.com/%';
    $clauses[] = "{$column} NOT LIKE ?";
    $params[] = 'https://%.hobbyhashcoin.com';
    $clauses[] = "{$column} NOT LIKE ?";
    $params[] = 'http://%.hobbyhashcoin.com';

    return ['sql' => '(' . implode(' AND ', $clauses) . ')', 'params' => $params];
}

function analytics_sql_referrers_tab_hidden_hosts(string $alias = ''): array
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    $column = "{$prefix}referrer";
    $clauses = [];
    $params = [];
    $hiddenHosts = ['pool.hobbyhashcoin.com', 'wallet.hobbyhashcoin.com'];

    foreach ($hiddenHosts as $host) {
        foreach (['https://', 'http://'] as $scheme) {
            $clauses[] = "{$column} NOT LIKE ?";
            $params[] = $scheme . $host . '%';
        }
    }

    return ['sql' => '(' . implode(' AND ', $clauses) . ')', 'params' => $params];
}

function analytics_render_referrers_tab(PDO $pdo, array $filters): void
{
    $where = analytics_where($filters);
    $external = analytics_sql_external_referrer_only();
    $hidden = analytics_sql_referrers_tab_hidden_hosts();
    $whereSql = $where['sql'] . ' AND ' . $external['sql'] . ' AND ' . $hidden['sql'];
    $params = array_merge($where['params'], $external['params'], $hidden['params']);
    $countSql = "SELECT COUNT(*) FROM (SELECT COALESCE(NULLIF(referrer, ''), '(direct / none)') AS label FROM site_pageviews WHERE {$whereSql} GROUP BY label) x";
    $dataSql = "SELECT COALESCE(NULLIF(referrer, ''), '(direct / none)') AS label, COUNT(*) AS total FROM site_pageviews WHERE {$whereSql} GROUP BY label";
    $columnDefs = [
        ['label' => 'Referrer'],
        ['label' => 'Pageviews', 'sort' => 'total', 'sql' => 'total', 'default' => true, 'default_dir' => 'desc'],
    ];
    $total = analytics_query_count($pdo, $countSql, $params);
    $offset = ((int)$filters['page'] - 1) * (int)$filters['per_page'];
    $rows = analytics_fetch($pdo, analytics_apply_order($dataSql, $columnDefs) . ' LIMIT ' . (int)$filters['per_page'] . ' OFFSET ' . $offset, $params);
    if (($_GET['export'] ?? '') === 'csv') {
        analytics_csv_response('hobc-referrers-' . gmdate('Ymd-His') . '.csv', ['Referrer', 'Pageviews'], array_map(static fn(array $row): array => [$row['label'], $row['total']], $rows));
    }
    analytics_bar_chart($rows, 'label', 'total', 'Referrer Breakdown');
    analytics_render_sortable_table($columnDefs, array_map(static function (array $row): array {
        $label = (string)$row['label'];
        $display = $label === '(direct / none)' ? h('(direct / none)') : analytics_detail_link('referrer', $label, $label);
        return [$display, h((string)$row['total'])];
    }, $rows), 'No data yet', 'External referrers will appear here. Same-site navigation is tracked but hidden from this list.', analytics_sort_link_params(['tab' => 'referrers']));
    admin_pagination((int)$filters['page'], max(1, (int)ceil($total / (int)$filters['per_page'])), admin_url('/analytics.php'), analytics_base_params($filters, 'referrers'), $total);
}

function analytics_bar_chart(array $rows, string $labelKey, string $valueKey, string $title): void
{
    if ($rows === []) {
        echo admin_empty_state('No chart data yet', 'Chart data will appear after analytics rows are collected.');
        return;
    }
    $max = 1;
    foreach ($rows as $row) {
        $max = max($max, (int)$row[$valueKey]);
    }
    echo '<div class="admin-card"><h3>' . h($title) . '</h3><div class="analytics-chart">';
    foreach (array_slice($rows, 0, 12) as $row) {
        $value = (int)$row[$valueKey];
        $width = max(2, (int)round(($value / $max) * 100));
        echo '<div class="analytics-bar-row"><span>' . h((string)($row[$labelKey] ?: 'not_available')) . '</span><div><b style="width:' . h((string)$width) . '%"></b></div><strong>' . h((string)$value) . '</strong></div>';
    }
    echo '</div></div>';
}

function analytics_render_active_window_form(array $filters, string $tab): void
{
    $active = analytics_active_window_minutes();
    echo '<div class="admin-card"><form method="get" class="analytics-filter-form">';
    foreach (analytics_base_params($filters, $tab) as $key => $value) {
        if ($key === 'active') {
            continue;
        }
        echo '<input type="hidden" name="' . h($key) . '" value="' . h((string)$value) . '">';
    }
    echo '<label>Active within<select name="active">';
    foreach ([5 => 'Last 5 minutes', 10 => 'Last 10 minutes', 60 => 'Last 60 minutes'] as $value => $label) {
        echo '<option value="' . h((string)$value) . '"' . ($active === $value ? ' selected' : '') . '>' . h($label) . '</option>';
    }
    echo '</select></label>';
    echo '<button type="submit">Apply</button>';
    echo '<p class="admin-muted">Shows visitors with a heartbeat or pageview in the selected window. ' . h(analytics_datetime_note()) . '</p>';
    echo '</form></div>';
}

function analytics_filter_form(array $filters, string $tab, bool $search = true): void
{
    echo '<div class="admin-card"><form method="get" class="analytics-filter-form">';
    echo '<input type="hidden" name="tab" value="' . h($tab) . '">';
    echo '<label>Date range<select name="range">';
    foreach (['today' => 'Today', '24h' => 'Last 24 hours', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'custom' => 'Custom'] as $value => $label) {
        echo '<option value="' . h($value) . '"' . ($filters['range'] === $value ? ' selected' : '') . '>' . h($label) . '</option>';
    }
    echo '</select></label>';
    echo '<label>From<input type="date" name="from" value="' . h($filters['from']) . '"></label>';
    echo '<label>To<input type="date" name="to" value="' . h($filters['to']) . '"></label>';
    if (in_array($tab, ['overview', 'realtime'], true)) {
        $active = analytics_active_window_minutes();
        echo '<label>Active within<select name="active">';
        foreach ([5 => 'Last 5 minutes', 10 => 'Last 10 minutes', 60 => 'Last 60 minutes'] as $value => $label) {
            echo '<option value="' . h((string)$value) . '"' . ($active === $value ? ' selected' : '') . '>' . h($label) . '</option>';
        }
        echo '</select></label>';
    }
    if ($search) {
        echo '<label>Search<input name="q" value="' . h($filters['search']) . '" placeholder="URL, title, referrer, user agent"></label>';
    }
    echo '<button type="submit">Apply Filters</button>';
    echo '<a class="admin-action admin-action-secondary" href="' . h(admin_url('/analytics.php?' . http_build_query(array_merge(analytics_base_params($filters, $tab), ['export' => 'csv'])))) . '">Export CSV</a>';
    echo '<p class="admin-muted">' . h(analytics_datetime_note()) . '</p>';
    echo '</form></div>';
}

function analytics_detail_link(string $type, string $value, string $label): string
{
    return '<a href="' . h(admin_url('/analytics.php?detail=' . rawurlencode($type) . '&id=' . rawurlencode($value))) . '">' . h($label) . '</a>';
}

function analytics_render_paginated(PDO $pdo, array $filters, string $tab, string $countSql, string $dataSql, array $params, array $columnDefs, callable $mapRow, string $emptyTitle = 'No data yet', string $emptyMessage = 'No analytics rows matched this filter.', array $linkParams = []): void
{
    $total = analytics_query_count($pdo, $countSql, $params);
    $offset = ((int)$filters['page'] - 1) * (int)$filters['per_page'];
    $orderedSql = analytics_apply_order($dataSql, $columnDefs);
    $rows = analytics_fetch($pdo, $orderedSql . ' LIMIT ' . (int)$filters['per_page'] . ' OFFSET ' . $offset, $params);
    $mapped = array_map($mapRow, $rows);
    $headerLabels = array_map(static fn(array $column): string => (string)$column['label'], $columnDefs);
    if (($_GET['export'] ?? '') === 'csv') {
        analytics_csv_response('hobc-' . $tab . '-' . gmdate('Ymd-His') . '.csv', $headerLabels, $mapped);
    }
    analytics_render_sortable_table($columnDefs, $mapped, $emptyTitle, $emptyMessage, array_merge(analytics_sort_link_params(['tab' => $tab]), $linkParams));
    admin_pagination((int)$filters['page'], max(1, (int)ceil($total / (int)$filters['per_page'])), admin_url('/analytics.php'), analytics_base_params($filters, $tab), $total);
}

function analytics_render_overview(PDO $pdo, array $filters): void
{
    $where = analytics_where($filters);
    $params = $where['params'];
    $activeLabel = analytics_active_window_label();
    echo '<div class="admin-grid admin-grid-tight">';
    echo admin_stat_card('Page Views', analytics_metric($pdo, 'SELECT COUNT(*) FROM site_pageviews WHERE ' . $where['sql'], $params), 'info');
    echo admin_stat_card('Unique Visitors', analytics_metric($pdo, 'SELECT COUNT(DISTINCT visitor_id) FROM site_pageviews WHERE ' . $where['sql'], $params), 'ok');
    echo admin_stat_card('Sessions', analytics_metric($pdo, 'SELECT COUNT(DISTINCT session_id) FROM site_pageviews WHERE ' . $where['sql'], $params), 'info');
    echo admin_stat_card('Active (' . $activeLabel . ')', analytics_metric($pdo, 'SELECT COUNT(*) FROM site_visitors WHERE ' . analytics_active_visitors_where(), []), 'ok');
    echo admin_stat_card('Bot Views', analytics_metric($pdo, 'SELECT COUNT(*) FROM site_pageviews WHERE is_bot = 1 AND ' . $where['sql'], $params), 'warn');
    echo admin_stat_card('Slow Pages', analytics_metric($pdo, 'SELECT COUNT(*) FROM site_pageviews WHERE load_time_ms >= 1500 AND ' . $where['sql'], $params), 'warn');
    if (analytics_column_exists('site_pageviews', 'city_name')) {
        echo admin_stat_card('Unique Cities', analytics_metric($pdo, 'SELECT COUNT(DISTINCT city_name) FROM site_pageviews WHERE city_name IS NOT NULL AND city_name <> "" AND ' . $where['sql'], $params), 'info');
        echo admin_stat_card('Unique ASNs', analytics_metric($pdo, 'SELECT COUNT(DISTINCT asn_number) FROM site_pageviews WHERE asn_number IS NOT NULL AND ' . $where['sql'], $params), 'info');
    }
    echo '</div>';
    echo '<div class="admin-card"><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="geoip_backfill"><button type="submit" data-confirm="Backfill GeoIP for all stored IPs and remove legacy rows without a raw IP address?">Backfill GeoIP &amp; purge legacy rows</button></form><p class="admin-muted">Uses GeoLite2 databases at /home/hobbyhashcoin/geoip. Removes old hash-only analytics rows that have no stored IP.</p></div>';
    $top = analytics_fetch($pdo, 'SELECT route_name, COUNT(*) AS views FROM site_pageviews WHERE ' . $where['sql'] . ' GROUP BY route_name ORDER BY views DESC LIMIT 12', $params);
    analytics_bar_chart($top, 'route_name', 'views', 'Top Pages');
}

function analytics_render_tab(PDO $pdo, string $tab, array $filters): void
{
    if ($tab === 'overview') {
        analytics_filter_form($filters, $tab, false);
    } elseif ($tab !== 'utm' && $tab !== 'realtime') {
        analytics_filter_form($filters, $tab, !in_array($tab, ['devices', 'browsers', 'os', 'countries', 'cities', 'regions', 'asn', 'status-codes'], true));
    }
    $where = analytics_where($filters);
    $params = $where['params'];

    switch ($tab) {
        case 'overview':
            analytics_render_overview($pdo, $filters);
            return;
        case 'realtime':
            analytics_render_active_window_form($filters, 'realtime');
            $activeWhere = analytics_active_visitors_where('v');
            $activeLabel = analytics_active_window_label();
            $currentPageExpr = analytics_column_exists('site_visitors', 'current_url')
                ? 'COALESCE(NULLIF(v.current_url, \'\'), pv.url)'
                : 'pv.url';
            $activeSql = "SELECT v.*, pv.session_id, {$currentPageExpr} AS current_page, pv.route_name AS last_pageview_route, pv.created_at AS last_pageview_at
                FROM site_visitors v
                LEFT JOIN site_pageviews pv ON pv.id = (
                    SELECT pv2.id FROM site_pageviews pv2
                    WHERE pv2.visitor_id = v.visitor_id
                    ORDER BY pv2.created_at DESC, pv2.id DESC
                    LIMIT 1
                )
                WHERE {$activeWhere}";
            analytics_render_paginated($pdo, $filters, $tab, "SELECT COUNT(*) FROM site_visitors v WHERE {$activeWhere}", $activeSql, [], [
                ['label' => 'Last Seen', 'sort' => 'last_seen_at', 'sql' => 'v.last_seen_at', 'default' => true, 'default_dir' => 'desc'],
                ['label' => 'IP'],
                ['label' => 'Location'],
                ['label' => 'ASN'],
                ['label' => 'Visitor'],
                ['label' => 'Session'],
                ['label' => 'Current Page'],
                ['label' => 'Device'],
                ['label' => 'Browser'],
            ], static fn(array $row): array => [
                analytics_h_datetime($row['last_seen_at'] ?? null),
                '<code class="admin-mono-cell">' . h(analytics_row_ip($row)) . '</code>',
                analytics_location_cell($row),
                analytics_asn_cell($row),
                analytics_detail_link('visitor', (string)$row['visitor_id'], substr((string)$row['visitor_id'], 0, 12) . '...'),
                trim((string)($row['session_id'] ?? '')) !== '' ? analytics_detail_link('session', (string)$row['session_id'], substr((string)$row['session_id'], 0, 12) . '...') : 'not_available',
                trim((string)($row['current_page'] ?? '')) !== '' ? admin_url_cell((string)$row['current_page']) : 'not_available',
                h((string)$row['device_type']),
                h((string)$row['browser_name']),
            ], 'No active visitors', 'No visitors were active in the ' . strtolower($activeLabel) . '.');
            return;
        case 'pageviews':
            analytics_render_paginated($pdo, $filters, $tab, 'SELECT COUNT(*) FROM site_pageviews WHERE ' . $where['sql'], 'SELECT * FROM site_pageviews WHERE ' . $where['sql'], $params, [
                ['label' => 'When', 'sort' => 'created_at', 'sql' => 'created_at', 'default' => true, 'default_dir' => 'desc'],
                ['label' => 'URL'],
                ['label' => 'Title'],
                ['label' => 'IP'],
                ['label' => 'Location'],
                ['label' => 'ASN'],
                ['label' => 'Status', 'sort' => 'response_status', 'sql' => 'response_status', 'default_dir' => 'desc'],
                ['label' => 'Load', 'sort' => 'load_time_ms', 'sql' => 'load_time_ms', 'default_dir' => 'desc'],
                ['label' => 'Visitor'],
                ['label' => 'Referrer'],
            ], static fn(array $row): array => [
                analytics_h_datetime($row['created_at'] ?? null),
                admin_url_cell((string)$row['url']),
                h((string)$row['page_title']),
                '<code class="admin-mono-cell">' . h(analytics_row_ip($row)) . '</code>',
                analytics_location_cell($row),
                analytics_asn_cell($row),
                h((string)$row['response_status']),
                h((string)$row['load_time_ms']) . ' ms',
                analytics_detail_link('visitor', (string)$row['visitor_id'], substr((string)$row['visitor_id'], 0, 12) . '...'),
                analytics_referrer_cell((string)$row['referrer']),
            ]);
            return;
        case 'visitors':
            analytics_render_paginated($pdo, $filters, $tab, 'SELECT COUNT(*) FROM site_visitors WHERE last_seen_at BETWEEN ? AND ?', 'SELECT * FROM site_visitors WHERE last_seen_at BETWEEN ? AND ?', [$filters['from_sql'], $filters['to_sql']], [
                ['label' => 'Visitor'],
                ['label' => 'IP'],
                ['label' => 'Location'],
                ['label' => 'ASN'],
                ['label' => 'First Seen', 'sort' => 'first_seen_at', 'sql' => 'first_seen_at', 'default_dir' => 'desc'],
                ['label' => 'Last Seen', 'sort' => 'last_seen_at', 'sql' => 'last_seen_at', 'default' => true, 'default_dir' => 'desc'],
                ['label' => 'Views', 'sort' => 'pageview_count', 'sql' => 'pageview_count', 'default_dir' => 'desc'],
                ['label' => 'Device'],
                ['label' => 'Browser'],
                ['label' => 'OS'],
                ['label' => 'Country'],
                ['label' => 'Bot'],
            ], static fn(array $row): array => [
                analytics_detail_link('visitor', (string)$row['visitor_id'], substr((string)$row['visitor_id'], 0, 12) . '...'),
                '<code class="admin-mono-cell">' . h(analytics_row_ip($row)) . '</code>',
                analytics_location_cell($row),
                analytics_asn_cell($row),
                analytics_h_datetime($row['first_seen_at'] ?? null),
                analytics_h_datetime($row['last_seen_at'] ?? null),
                h((string)$row['pageview_count']),
                h((string)$row['device_type']),
                h((string)$row['browser_name']),
                h((string)$row['os_name']),
                h((string)$row['country_code']),
                ((int)$row['is_bot'] === 1) ? h((string)$row['bot_name']) : 'Human',
            ], 'No visitors yet', 'No visitors matched this date range.');
            return;
        case 'sessions':
            $dataSql = 'SELECT session_id, MIN(created_at) AS first_seen, MAX(created_at) AS last_seen, COUNT(*) AS views, MIN(url) AS sample_url, MAX(visitor_id) AS visitor_id FROM site_pageviews WHERE session_id IS NOT NULL AND ' . $where['sql'] . ' GROUP BY session_id';
            analytics_render_paginated($pdo, $filters, $tab, 'SELECT COUNT(*) FROM (SELECT session_id FROM site_pageviews WHERE session_id IS NOT NULL AND ' . $where['sql'] . ' GROUP BY session_id) x', $dataSql, $params, [
                ['label' => 'Session'],
                ['label' => 'Visitor'],
                ['label' => 'First Seen', 'sort' => 'first_seen', 'sql' => 'first_seen', 'default_dir' => 'desc'],
                ['label' => 'Last Seen', 'sort' => 'last_seen', 'sql' => 'last_seen', 'default' => true, 'default_dir' => 'desc'],
                ['label' => 'Views', 'sort' => 'views', 'sql' => 'views', 'default_dir' => 'desc'],
                ['label' => 'Sample URL'],
            ], static fn(array $row): array => [
                analytics_detail_link('session', (string)$row['session_id'], substr((string)$row['session_id'], 0, 12) . '...'),
                analytics_detail_link('visitor', (string)$row['visitor_id'], substr((string)$row['visitor_id'], 0, 12) . '...'),
                analytics_h_datetime($row['first_seen'] ?? null),
                analytics_h_datetime($row['last_seen'] ?? null),
                h((string)$row['views']),
                admin_url_cell((string)$row['sample_url']),
            ]);
            return;
        case 'referrers':
            analytics_render_referrers_tab($pdo, $filters);
            return;
        case 'utm':
            analytics_render_utm_tab($pdo, $filters);
            return;
        case 'devices':
            analytics_group_tab($pdo, $filters, $tab, 'device_type', 'Device', 'pageviews');
            return;
        case 'browsers':
            analytics_group_tab($pdo, $filters, $tab, 'browser_name', 'Browser', 'pageviews');
            return;
        case 'os':
            analytics_group_tab($pdo, $filters, $tab, 'os_name', 'Operating System', 'pageviews');
            return;
        case 'countries':
            analytics_group_tab($pdo, $filters, $tab, 'country_code', 'Country', 'pageviews');
            return;
        case 'cities':
            analytics_group_tab($pdo, $filters, $tab, 'city_name', 'City', 'pageviews');
            return;
        case 'regions':
            analytics_group_tab($pdo, $filters, $tab, 'region_name', 'Region', 'pageviews');
            return;
        case 'asn':
            analytics_group_tab($pdo, $filters, $tab, 'asn_org', 'ASN / ISP', 'pageviews');
            return;
        case 'status-codes':
            analytics_group_tab($pdo, $filters, $tab, 'response_status', 'Status Code', 'pageviews');
            return;
        case 'slow-pages':
            $slowWhere = $where['sql'] . ' AND load_time_ms >= 1500';
            analytics_render_paginated($pdo, $filters, $tab, 'SELECT COUNT(*) FROM site_pageviews WHERE ' . $slowWhere, 'SELECT url, route_name, page_title, response_status, load_time_ms, created_at FROM site_pageviews WHERE ' . $slowWhere, $params, [
                ['label' => 'When', 'sort' => 'created_at', 'sql' => 'created_at', 'default_dir' => 'desc'],
                ['label' => 'URL'],
                ['label' => 'Title'],
                ['label' => 'Status', 'sort' => 'response_status', 'sql' => 'response_status', 'default_dir' => 'desc'],
                ['label' => 'Load Time', 'sort' => 'load_time_ms', 'sql' => 'load_time_ms', 'default' => true, 'default_dir' => 'desc'],
            ], static fn(array $row): array => [analytics_h_datetime($row['created_at'] ?? null), admin_url_cell((string)$row['url']), h((string)$row['page_title']), h((string)$row['response_status']), h((string)$row['load_time_ms']) . ' ms'], 'No slow pages');
            return;
        case '404-pages':
            $notFoundWhere = $where['sql'] . ' AND response_status = 404';
            analytics_render_paginated($pdo, $filters, $tab, 'SELECT COUNT(*) FROM site_pageviews WHERE ' . $notFoundWhere, 'SELECT url, referrer, user_agent, visitor_id, created_at FROM site_pageviews WHERE ' . $notFoundWhere, $params, [
                ['label' => 'When', 'sort' => 'created_at', 'sql' => 'created_at', 'default' => true, 'default_dir' => 'desc'],
                ['label' => 'URL'],
                ['label' => 'Visitor'],
                ['label' => 'Referrer'],
                ['label' => 'User Agent'],
            ], static fn(array $row): array => [analytics_h_datetime($row['created_at'] ?? null), admin_url_cell((string)$row['url']), analytics_detail_link('visitor', (string)$row['visitor_id'], substr((string)$row['visitor_id'], 0, 12) . '...'), analytics_referrer_cell((string)$row['referrer']), h((string)$row['user_agent'])], 'No 404 pages');
            return;
        case 'downloads':
            analytics_render_downloads($pdo, $filters);
            return;
        case 'crawlers':
            analytics_render_bots($pdo, $filters, true);
            return;
        case 'suspicious-bots':
            analytics_render_bots($pdo, $filters, false);
            return;
    }
}

function analytics_group_tab(PDO $pdo, array $filters, string $tab, string $field, string $label, string $countLabel, string $detailType = ''): void
{
    $where = analytics_where($filters);
    $params = $where['params'];
    $safeField = '`' . str_replace('`', '``', $field) . '`';
    $countSql = "SELECT COUNT(*) FROM (SELECT COALESCE(CAST({$safeField} AS CHAR), 'not_available') AS label FROM site_pageviews WHERE {$where['sql']} GROUP BY label) x";
    $dataSql = "SELECT COALESCE(CAST({$safeField} AS CHAR), 'not_available') AS label, COUNT(*) AS total FROM site_pageviews WHERE {$where['sql']} GROUP BY label";
    $labelSortSql = $tab === 'status-codes'
        ? 'CAST(CASE WHEN label = "not_available" THEN 0 ELSE label END AS UNSIGNED)'
        : 'label';
    $columnDefs = [
        ['label' => $label, 'sort' => 'label', 'sql' => $labelSortSql, 'default_dir' => 'asc'],
        ['label' => ucfirst($countLabel), 'sort' => 'total', 'sql' => 'total', 'default' => true, 'default_dir' => 'desc'],
    ];
    $total = analytics_query_count($pdo, $countSql, $params);
    $offset = ((int)$filters['page'] - 1) * (int)$filters['per_page'];
    $rows = analytics_fetch($pdo, analytics_apply_order($dataSql, $columnDefs) . ' LIMIT ' . (int)$filters['per_page'] . ' OFFSET ' . $offset, $params);
    if (($_GET['export'] ?? '') === 'csv') {
        analytics_csv_response('hobc-' . $tab . '-' . gmdate('Ymd-His') . '.csv', [$label, ucfirst($countLabel)], array_map(static fn(array $row): array => [$row['label'], $row['total']], $rows));
    }
    analytics_bar_chart($rows, 'label', 'total', $label . ' Breakdown');
    analytics_render_sortable_table($columnDefs, array_map(static function (array $row) use ($detailType): array {
        $label = (string)$row['label'];
        return [$detailType !== '' ? analytics_detail_link($detailType, $label, $label) : h($label), h((string)$row['total'])];
    }, $rows), 'No data yet', 'No rows matched this filter.', analytics_sort_link_params(['tab' => $tab]));
    admin_pagination((int)$filters['page'], max(1, (int)ceil($total / (int)$filters['per_page'])), admin_url('/analytics.php'), analytics_base_params($filters, $tab), $total);
}

function analytics_render_downloads(PDO $pdo, array $filters): void
{
    if (!admin_analytics_table_exists($pdo, 'download_events')) {
        echo admin_empty_state('Migration required', 'The `download_events` table is missing.');
        return;
    }
    $dataSql = "SELECT d.id, COALESCE(d.title, CONCAT('Download #', de.download_id)) AS title, d.platform, d.file_url, COUNT(de.id) AS clicks, MAX(de.created_at) AS last_click FROM download_events de LEFT JOIN downloads d ON d.id = de.download_id WHERE de.created_at BETWEEN ? AND ? GROUP BY d.id, d.title, d.platform, d.file_url, de.download_id";
    $columnDefs = [
        ['label' => 'Download'],
        ['label' => 'Platform'],
        ['label' => 'File URL'],
        ['label' => 'Clicks', 'sort' => 'clicks', 'sql' => 'clicks', 'default' => true, 'default_dir' => 'desc'],
        ['label' => 'Last Click', 'sort' => 'last_click', 'sql' => 'last_click', 'default_dir' => 'desc'],
    ];
    $rows = analytics_fetch($pdo, analytics_apply_order($dataSql, $columnDefs) . ' LIMIT 100', [$filters['from_sql'], $filters['to_sql']]);
    if (($_GET['export'] ?? '') === 'csv') {
        analytics_csv_response('hobc-downloads-' . gmdate('Ymd-His') . '.csv', ['Download', 'Platform', 'File URL', 'Clicks', 'Last Click'], array_map(static fn(array $row): array => [$row['title'], $row['platform'], $row['file_url'], $row['clicks'], analytics_format_datetime((string)$row['last_click'])], $rows));
    }
    analytics_bar_chart($rows, 'title', 'clicks', 'Download Clicks');
    analytics_render_sortable_table($columnDefs, array_map(static fn(array $row): array => [analytics_detail_link('download', (string)($row['id'] ?? ''), (string)$row['title']), h((string)$row['platform']), h((string)$row['file_url']), h((string)$row['clicks']), analytics_h_datetime($row['last_click'] ?? null)], $rows), 'No download clicks', 'Download events will appear after users click download links.', analytics_sort_link_params(['tab' => 'downloads']));
}

function analytics_render_bots(PDO $pdo, array $filters, bool $searchEngines): void
{
    if (!admin_analytics_table_exists($pdo, 'bot_events')) {
        echo admin_empty_state('Migration required', 'The `bot_events` table is missing.');
        return;
    }
    $where = 'created_at BETWEEN ? AND ?';
    $params = [$filters['from_sql'], $filters['to_sql']];
    if ($searchEngines) {
        $where .= " AND bot_type = 'search_engine'";
    } else {
        $where .= " AND (bot_type <> 'search_engine' OR threat_level IN ('medium','high','critical'))";
    }
    if ($filters['search'] !== '') {
        $where .= ' AND (bot_name LIKE ? OR url LIKE ? OR user_agent LIKE ?)';
        array_push($params, '%' . $filters['search'] . '%', '%' . $filters['search'] . '%', '%' . $filters['search'] . '%');
    }
    $dataSql = "SELECT bot_name, bot_type, threat_level, COUNT(*) AS events, MAX(created_at) AS last_seen FROM bot_events WHERE {$where} GROUP BY bot_name, bot_type, threat_level";
    $columnDefs = [
        ['label' => 'Bot'],
        ['label' => 'Type'],
        ['label' => 'Threat'],
        ['label' => 'Events', 'sort' => 'events', 'sql' => 'events', 'default' => true, 'default_dir' => 'desc'],
        ['label' => 'Last Seen', 'sort' => 'last_seen', 'sql' => 'last_seen', 'default_dir' => 'desc'],
    ];
    $tab = $searchEngines ? 'crawlers' : 'suspicious-bots';
    $rows = analytics_fetch($pdo, analytics_apply_order($dataSql, $columnDefs) . ' LIMIT 100', $params);
    if (($_GET['export'] ?? '') === 'csv') {
        analytics_csv_response('hobc-bots-' . gmdate('Ymd-His') . '.csv', ['Bot', 'Type', 'Threat', 'Events', 'Last Seen'], array_map(static fn(array $row): array => [$row['bot_name'], $row['bot_type'], $row['threat_level'], $row['events'], analytics_format_datetime((string)$row['last_seen'])], $rows));
    }
    analytics_bar_chart($rows, 'bot_name', 'events', $searchEngines ? 'Search Engine Crawlers' : 'Suspicious Bots');
    analytics_render_sortable_table($columnDefs, array_map(static fn(array $row): array => [analytics_detail_link('bot', (string)$row['bot_name'], (string)$row['bot_name']), h((string)$row['bot_type']), h((string)$row['threat_level']), h((string)$row['events']), analytics_h_datetime($row['last_seen'] ?? null)], $rows), 'No bot events', 'No bot events matched this filter.', analytics_sort_link_params(['tab' => $tab]));
}

function analytics_handle_utm_delete(PDO $pdo): void
{
    if (($_POST['action'] ?? '') !== 'delete_utm_campaign') {
        return;
    }

    $source = (string)($_POST['utm_source'] ?? '');
    $medium = (string)($_POST['utm_medium'] ?? '');
    $campaign = (string)($_POST['utm_campaign'] ?? '');

    $stmt = $pdo->prepare(
        "DELETE FROM site_pageviews
         WHERE COALESCE(utm_source, '') = ?
           AND COALESCE(utm_medium, '') = ?
           AND COALESCE(utm_campaign, '') = ?"
    );
    $stmt->execute([$source, $medium, $campaign]);
    $deleted = $stmt->rowCount();

    $label = trim($campaign !== '' ? $campaign : ($source . ' / ' . $medium), ' /');
    admin_flash_set('success', 'Deleted ' . number_format($deleted) . ' analytics row(s) for campaign "' . $label . '".');
    wallet_redirect(admin_url('/analytics.php?tab=utm'));
}

function analytics_pie_palette(): array
{
    return ['#f6b928', '#8dc7ff', '#7ad99a', '#ff9d76', '#c9a6ff', '#ff7f9f', '#66d9cf', '#ffd166', '#9db4ff', '#b8de6f'];
}

function analytics_pie_prepare(array $rows, string $labelKey, string $valueKey, int $limit = 8): array
{
    if ($rows === []) {
        return ['total' => 0, 'slices' => []];
    }

    $total = 0;
    foreach ($rows as $row) {
        $total += (int)($row[$valueKey] ?? 0);
    }
    if ($total <= 0) {
        return ['total' => 0, 'slices' => []];
    }

    $slices = [];
    $other = 0;
    foreach ($rows as $index => $row) {
        $value = (int)($row[$valueKey] ?? 0);
        if ($value <= 0) {
            continue;
        }
        if ($index < $limit) {
            $slices[] = [
                'label' => (string)($row[$labelKey] ?? 'Unknown'),
                'value' => $value,
            ];
        } else {
            $other += $value;
        }
    }
    if ($other > 0) {
        $slices[] = ['label' => 'Other', 'value' => $other];
    }

    return ['total' => $total, 'slices' => $slices];
}

function analytics_render_pie_chart(array $rows, string $labelKey, string $valueKey, string $title): void
{
    $prepared = analytics_pie_prepare($rows, $labelKey, $valueKey);
    if ($prepared['slices'] === []) {
        echo admin_empty_state('No chart data yet', 'Chart data will appear after this campaign receives traffic.');
        return;
    }

    $total = (int)$prepared['total'];
    $colors = analytics_pie_palette();
    $segments = [];
    $offset = 0.0;
    foreach ($prepared['slices'] as $index => $slice) {
        $pct = ($slice['value'] / $total) * 100;
        $color = $colors[$index % count($colors)];
        $segments[] = h($color) . ' ' . $offset . '% ' . ($offset + $pct) . '%';
        $offset += $pct;
    }

    echo '<div class="analytics-pie-wrap">';
    echo '<div class="analytics-pie" style="background:conic-gradient(' . implode(', ', $segments) . ');"></div>';
    echo '<ul class="analytics-pie-legend">';
    foreach ($prepared['slices'] as $index => $slice) {
        $pct = round(($slice['value'] / $total) * 100, 1);
        $color = $colors[$index % count($colors)];
        echo '<li><span class="analytics-pie-dot" style="background:' . h($color) . ';"></span>';
        echo '<span class="analytics-pie-label">' . h((string)$slice['label']) . '</span>';
        echo '<strong>' . h(number_format((int)$slice['value'])) . '</strong>';
        echo '<em>' . h((string)$pct) . '%</em></li>';
    }
    echo '</ul></div>';
}

function analytics_utm_campaign_url(array $params): string
{
    return admin_url('/analytics.php?' . http_build_query([
        'detail' => 'utm',
        'utm_source' => (string)($params['utm_source'] ?? ''),
        'utm_medium' => (string)($params['utm_medium'] ?? ''),
        'utm_campaign' => (string)($params['utm_campaign'] ?? ''),
    ]));
}

function analytics_utm_public_base_url(): string
{
    return 'https://hobbyhashcoin.com';
}

function analytics_utm_row_key(string $source, string $medium, string $campaign): string
{
    return $source . "\0" . $medium . "\0" . $campaign;
}

function analytics_utm_path_from_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '/';
    }

    if ($url[0] !== '/') {
        $path = parse_url($url, PHP_URL_PATH);
        $url = is_string($path) && $path !== '' ? $path : '/';
    }

    $queryPos = strpos($url, '?');
    if ($queryPos !== false) {
        $url = substr($url, 0, $queryPos);
    }

    return $url !== '' ? $url : '/';
}

function analytics_utm_tagged_public_url(string $path, string $source, string $medium, string $campaign): string
{
    $path = analytics_utm_path_from_url($path);
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    $params = array_filter([
        'utm_source' => $source,
        'utm_medium' => $medium,
        'utm_campaign' => $campaign,
    ], static fn(string $value): bool => $value !== '');

    $query = http_build_query($params);

    return analytics_utm_public_base_url() . $path . ($query !== '' ? '?' . $query : '');
}

function analytics_utm_landing_paths_for_rows(PDO $pdo, array $rows): array
{
    if ($rows === []) {
        return [];
    }

    $seen = [];
    $conditions = [];
    $params = [];
    foreach ($rows as $row) {
        $source = (string)($row['utm_source'] ?? '');
        $medium = (string)($row['utm_medium'] ?? '');
        $campaign = (string)($row['utm_campaign'] ?? '');
        $key = analytics_utm_row_key($source, $medium, $campaign);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $conditions[] = '(COALESCE(utm_source, "") = ? AND COALESCE(utm_medium, "") = ? AND COALESCE(utm_campaign, "") = ?)';
        array_push($params, $source, $medium, $campaign);
    }

    if ($conditions === []) {
        return [];
    }

    $fetched = analytics_fetch(
        $pdo,
        'SELECT
            COALESCE(utm_source, "") AS utm_source,
            COALESCE(utm_medium, "") AS utm_medium,
            COALESCE(utm_campaign, "") AS utm_campaign,
            url,
            COUNT(*) AS total
         FROM site_pageviews
         WHERE ' . implode(' OR ', $conditions) . '
         GROUP BY utm_source, utm_medium, utm_campaign, url
         ORDER BY total DESC',
        $params
    );

    $paths = [];
    foreach ($fetched as $row) {
        $key = analytics_utm_row_key(
            (string)($row['utm_source'] ?? ''),
            (string)($row['utm_medium'] ?? ''),
            (string)($row['utm_campaign'] ?? '')
        );
        if (!isset($paths[$key])) {
            $paths[$key] = analytics_utm_path_from_url((string)($row['url'] ?? ''));
        }
    }

    return $paths;
}

function analytics_utm_landing_path_for_campaign(PDO $pdo, string $source, string $medium, string $campaign): string
{
    $paths = analytics_utm_landing_paths_for_rows($pdo, [[
        'utm_source' => $source,
        'utm_medium' => $medium,
        'utm_campaign' => $campaign,
    ]]);

    $key = analytics_utm_row_key($source, $medium, $campaign);

    return $paths[$key] ?? '/';
}

function analytics_utm_copy_icon_svg(): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
}

function analytics_utm_copy_link_control(string $url): string
{
    return '<button type="button" class="utm-copy-link" data-copy="' . h($url) . '" title="Copy tagged link" aria-label="Copy tagged link">'
        . analytics_utm_copy_icon_svg()
        . '</button>';
}

function analytics_render_utm_copy_script(): void
{
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;

    echo '<script>
(function () {
  document.addEventListener("click", function (event) {
    var button = event.target.closest(".utm-copy-link");
    if (!button) {
      return;
    }
    event.preventDefault();
    var text = button.getAttribute("data-copy") || "";
    if (!text) {
      return;
    }
    navigator.clipboard.writeText(text).then(function () {
      button.classList.add("is-copied");
      button.setAttribute("title", "Copied");
      window.setTimeout(function () {
        button.classList.remove("is-copied");
        button.setAttribute("title", "Copy tagged link");
      }, 1800);
    });
  });
})();
</script>';
}

function analytics_utm_campaign_link(array $row, string $label): string
{
    return '<a href="' . h(analytics_utm_campaign_url([
        'utm_source' => (string)($row['utm_source'] ?? ''),
        'utm_medium' => (string)($row['utm_medium'] ?? ''),
        'utm_campaign' => (string)($row['utm_campaign'] ?? ''),
    ])) . '">' . h($label) . '</a>';
}

function analytics_utm_campaign_where(string $source, string $medium, string $campaign, string $alias = ''): array
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    return [
        'sql' => "COALESCE({$prefix}utm_source, '') = ? AND COALESCE({$prefix}utm_medium, '') = ? AND COALESCE({$prefix}utm_campaign, '') = ?",
        'params' => [$source, $medium, $campaign],
    ];
}

function analytics_utm_has_tags_sql(string $alias = ''): string
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    return "({$prefix}utm_source IS NOT NULL OR {$prefix}utm_medium IS NOT NULL OR {$prefix}utm_campaign IS NOT NULL)";
}

function analytics_utm_delete_form(array $row, string $buttonLabel = 'Delete'): string
{
    return '<form method="post" class="inline-form utm-delete-form">'
        . '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'
        . '<input type="hidden" name="action" value="delete_utm_campaign">'
        . '<input type="hidden" name="utm_source" value="' . h((string)($row['utm_source'] ?? '')) . '">'
        . '<input type="hidden" name="utm_medium" value="' . h((string)($row['utm_medium'] ?? '')) . '">'
        . '<input type="hidden" name="utm_campaign" value="' . h((string)($row['utm_campaign'] ?? '')) . '">'
        . '<button type="submit" class="admin-action admin-action-danger" data-confirm="Delete all analytics rows for this campaign? This cannot be undone.">' . h($buttonLabel) . '</button>'
        . '</form>';
}

function analytics_utm_language_breakdown(PDO $pdo, string $whereSql, array $params): array
{
    if (analytics_column_exists('site_pageviews', 'locale')) {
        $rows = analytics_fetch(
            $pdo,
            'SELECT locale, url, COUNT(*) AS total FROM site_pageviews WHERE ' . $whereSql . ' GROUP BY locale, url',
            $params
        );
        $buckets = [];
        foreach ($rows as $row) {
            $locale = trim((string)($row['locale'] ?? ''));
            if ($locale === '') {
                $locale = analytics_locale_from_url((string)($row['url'] ?? ''));
            }
            $buckets[$locale] = ($buckets[$locale] ?? 0) + (int)($row['total'] ?? 0);
        }
        arsort($buckets);
        $out = [];
        foreach ($buckets as $locale => $total) {
            $out[] = [
                'label' => analytics_utm_locale_label((string)$locale),
                'total' => $total,
            ];
        }
        return array_slice($out, 0, 12);
    }

    $localeExpr = analytics_sql_locale_label();
    $rows = analytics_fetch(
        $pdo,
        'SELECT ' . $localeExpr . ' AS locale_code, COUNT(*) AS total
         FROM site_pageviews WHERE ' . $whereSql . '
         GROUP BY locale_code ORDER BY total DESC LIMIT 12',
        $params
    );

    return array_map(static fn(array $row): array => [
        'label' => analytics_utm_locale_label((string)($row['locale_code'] ?? 'en')),
        'total' => (int)($row['total'] ?? 0),
    ], $rows);
}

function analytics_utm_locale_label(string $locale): string
{
    $locale = trim($locale) !== '' ? $locale : 'en';
    if (function_exists('hobc_i18n_language_selector_name')) {
        return hobc_i18n_language_selector_name($locale);
    }

    return $locale;
}

function analytics_render_utm_link_builder(): void
{
    $paths = [
        '/' => 'Homepage',
        '/mining/' => 'Mining',
        '/wallet/' => 'Wallet',
        '/downloads/' => 'Downloads',
        '/docs/' => 'Docs',
        '/docs/faq/' => 'FAQ',
        '/pool/main/' => 'Main pool',
        '/pool/nano/' => 'Nano pool',
        '/explorer/' => 'Explorer',
        '/contact/' => 'Contact',
    ];
    echo '<div class="utm-builder" id="utm-link-builder">';
    echo '<div class="utm-builder-fields">';
    echo '<label class="utm-field utm-field-wide"><span class="utm-field-label">Destination</span><select id="utm-destination">';
    foreach ($paths as $path => $label) {
        echo '<option value="' . h($path) . '">' . h($label) . '</option>';
    }
    echo '</select></label>';
    echo '<label class="utm-field"><span class="utm-field-label">Custom path</span><input type="text" id="utm-custom-path" placeholder="/optional-path/"></label>';
    echo '<label class="utm-field"><span class="utm-field-label">Source</span><input type="text" id="utm-source" value="google" placeholder="google"></label>';
    echo '<label class="utm-field"><span class="utm-field-label">Medium</span><input type="text" id="utm-medium" value="cpc" placeholder="cpc"></label>';
    echo '<label class="utm-field utm-field-wide"><span class="utm-field-label">Campaign</span><input type="text" id="utm-campaign" placeholder="search_mining_2026-06"></label>';
    echo '</div>';
    echo '<details class="utm-builder-advanced"><summary>Optional tags</summary>';
    echo '<div class="utm-builder-fields utm-builder-fields-advanced">';
    echo '<label class="utm-field"><span class="utm-field-label">Content</span><input type="text" id="utm-content" placeholder="headline_a"></label>';
    echo '<label class="utm-field"><span class="utm-field-label">Term</span><input type="text" id="utm-term" placeholder="{keyword}"></label>';
    echo '</div></details>';
    echo '<div class="utm-output-row">';
    echo '<label class="utm-output-field"><span class="utm-field-label">Generated link</span><input type="text" id="utm-output" readonly placeholder="Enter a campaign name to generate a link."></label>';
    echo '<button type="button" class="admin-action" id="utm-copy-btn" disabled>Copy</button>';
    echo '</div>';
    echo '<p class="admin-muted utm-builder-tip">Tagged visits are remembered for 30 days and attached to every page that visitor views. For Google Ads, use the same tags in the campaign <strong>Final URL suffix</strong>.</p>';
    echo '</div>';
    echo '<script>
(function () {
  var base = ' . json_encode(analytics_utm_public_base_url(), JSON_UNESCAPED_SLASHES) . ';
  var destination = document.getElementById("utm-destination");
  var customPath = document.getElementById("utm-custom-path");
  var output = document.getElementById("utm-output");
  var copyBtn = document.getElementById("utm-copy-btn");
  function field(id) {
    var el = document.getElementById(id);
    return el ? el.value.trim() : "";
  }
  function buildUrl() {
    var path = customPath.value.trim() || destination.value || "/";
    if (path.charAt(0) !== "/") {
      path = "/" + path;
    }
    var params = new URLSearchParams();
    [["utm-source", "utm_source"], ["utm-medium", "utm_medium"], ["utm-campaign", "utm_campaign"], ["utm-content", "utm_content"], ["utm-term", "utm_term"]].forEach(function (pair) {
      var value = field(pair[0]);
      if (value !== "") {
        params.set(pair[1], value);
      }
    });
    var query = params.toString();
    output.value = base + path + (query ? "?" + query : "");
    copyBtn.disabled = output.value === "";
  }
  copyBtn.addEventListener("click", function () {
    if (!output.value) {
      return;
    }
    navigator.clipboard.writeText(output.value).then(function () {
      copyBtn.textContent = "Copied";
      window.setTimeout(function () { copyBtn.textContent = "Copy"; }, 1800);
    });
  });
  ["utm-source", "utm-medium", "utm-campaign", "utm-content", "utm-term", "utm-custom-path"].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) {
      el.addEventListener("input", buildUrl);
    }
  });
  destination.addEventListener("change", buildUrl);
  buildUrl();
})();
</script>';
}

function analytics_utm_tag(string $label, string $value): string
{
    $display = $value !== '' ? $value : '(empty)';
    return '<span class="utm-tag"><span class="utm-tag-label">' . h($label) . '</span><strong>' . h($display) . '</strong></span>';
}

function analytics_render_utm_tab(PDO $pdo, array $filters): void
{
    $campaignFilter = ' AND ' . analytics_utm_has_tags_sql();
    $ipExpr = analytics_column_exists('site_pageviews', 'ip_address')
        ? 'COUNT(DISTINCT COALESCE(NULLIF(ip_address, ""), ip_hash))'
        : 'COUNT(DISTINCT ip_hash)';

    $countSql = 'SELECT COUNT(*) FROM (
        SELECT utm_source, utm_medium, utm_campaign
        FROM site_pageviews
        WHERE 1=1' . $campaignFilter . '
        GROUP BY utm_source, utm_medium, utm_campaign
    ) x';

    $dataSql = 'SELECT
            COALESCE(utm_source, "") AS utm_source,
            COALESCE(utm_medium, "") AS utm_medium,
            COALESCE(utm_campaign, "") AS utm_campaign,
            COUNT(*) AS views,
            COUNT(DISTINCT visitor_id) AS visitors,
            COUNT(DISTINCT session_id) AS sessions,
            ' . $ipExpr . ' AS unique_ips
        FROM site_pageviews
        WHERE 1=1' . $campaignFilter . '
        GROUP BY utm_source, utm_medium, utm_campaign';

    $columnDefs = [
        ['label' => 'Source'],
        ['label' => 'Medium'],
        ['label' => 'Campaign'],
        ['label' => 'Views', 'sort' => 'views', 'sql' => 'views', 'default' => true, 'default_dir' => 'desc'],
        ['label' => 'Visitors', 'sort' => 'visitors', 'sql' => 'visitors', 'default_dir' => 'desc'],
        ['label' => 'Sessions', 'sort' => 'sessions', 'sql' => 'sessions', 'default_dir' => 'desc'],
        ['label' => 'IPs', 'sort' => 'unique_ips', 'sql' => 'unique_ips', 'default_dir' => 'desc'],
        ['label' => 'Actions'],
    ];

    $total = analytics_query_count($pdo, $countSql, []);
    $offset = ((int)$filters['page'] - 1) * (int)$filters['per_page'];
    $rows = analytics_fetch($pdo, analytics_apply_order($dataSql, $columnDefs) . ' LIMIT ' . (int)$filters['per_page'] . ' OFFSET ' . $offset, []);

    if (($_GET['export'] ?? '') === 'csv') {
        $csvRows = array_map(static fn(array $row): array => [
            (string)$row['utm_source'],
            (string)$row['utm_medium'],
            (string)$row['utm_campaign'],
            (string)$row['views'],
            (string)$row['visitors'],
            (string)$row['sessions'],
            (string)$row['unique_ips'],
        ], $rows);
        analytics_csv_response('hobc-utm-' . gmdate('Ymd-His') . '.csv', array_map(static fn(array $column): string => (string)$column['label'], array_slice($columnDefs, 0, 7)), $csvRows);
    }

    $landingPaths = analytics_utm_landing_paths_for_rows($pdo, $rows);

    $mapped = array_map(static function (array $row) use ($landingPaths): array {
        $source = (string)($row['utm_source'] ?? '');
        $medium = (string)($row['utm_medium'] ?? '');
        $campaign = (string)($row['utm_campaign'] ?? '');
        $key = analytics_utm_row_key($source, $medium, $campaign);
        $taggedUrl = analytics_utm_tagged_public_url($landingPaths[$key] ?? '/', $source, $medium, $campaign);

        return [
            h($source),
            h($medium),
            '<strong>' . h($campaign) . '</strong>',
            h((string)$row['views']),
            h((string)$row['visitors']),
            h((string)$row['sessions']),
            h((string)$row['unique_ips']),
            '<div class="utm-row-actions">'
                . analytics_utm_copy_link_control($taggedUrl)
                . analytics_utm_campaign_link($row, 'Track')
                . analytics_utm_delete_form($row)
                . '</div>',
        ];
    }, $rows);

    echo '<div class="admin-card utm-results-card">';
    echo '<div class="utm-results-head"><div><h3>Recorded campaigns</h3><p class="admin-muted">' . h(number_format($total)) . ' campaign' . ($total === 1 ? '' : 's') . ' tracked.</p></div>';
    echo '<a class="admin-action admin-action-secondary" href="' . h(admin_url('/analytics.php?' . http_build_query(['tab' => 'utm', 'export' => 'csv']))) . '">Export CSV</a>';
    admin_filter_box('Filter table', 'Source, medium, or campaign');
    echo '</div>';
    analytics_render_sortable_table($columnDefs, $mapped, 'No UTM campaigns yet', 'Build a tagged link below, send traffic to it, then campaign rows will appear here.', analytics_sort_link_params(['tab' => 'utm']));
    admin_pagination((int)$filters['page'], max(1, (int)ceil($total / (int)$filters['per_page'])), admin_url('/analytics.php'), analytics_base_params($filters, 'utm'), $total);
    echo '</div>';

    echo '<div class="admin-card utm-workspace">';
    echo '<div class="utm-workspace-head">';
    echo '<div><h3>Link builder</h3><p class="admin-muted">Create tagged links for Google Ads, email, Discord, or social posts. All campaign stats above are lifetime totals.</p></div>';
    echo '</div>';
    analytics_render_utm_link_builder();
    echo '</div>';
    analytics_render_utm_copy_script();
}

function analytics_render_utm_detail(PDO $pdo, array $filters, string $source, string $medium, string $campaign): void
{
    $where = analytics_utm_campaign_where($source, $medium, $campaign);
    $params = $where['params'];
    $whereSql = $where['sql'];
    $ipExpr = analytics_column_exists('site_pageviews', 'ip_address')
        ? 'COUNT(DISTINCT COALESCE(NULLIF(ip_address, ""), ip_hash))'
        : 'COUNT(DISTINCT ip_hash)';

    echo '<div class="admin-card utm-detail-hero">';
    echo '<div class="utm-detail-top">';
    echo '<div class="admin-actions utm-detail-actions">';
    echo admin_action_button('Back to UTM campaigns', admin_url('/analytics.php?tab=utm'), 'secondary');
    echo analytics_utm_delete_form([
        'utm_source' => $source,
        'utm_medium' => $medium,
        'utm_campaign' => $campaign,
    ], 'Delete campaign');
    echo '</div>';
    echo '</div>';
    echo '<div class="utm-tag-row">';
    echo analytics_utm_tag('Source', $source);
    echo analytics_utm_tag('Medium', $medium);
    echo analytics_utm_tag('Campaign', $campaign);
    echo analytics_utm_copy_link_control(analytics_utm_tagged_public_url(
        analytics_utm_landing_path_for_campaign($pdo, $source, $medium, $campaign),
        $source,
        $medium,
        $campaign
    ));
    echo '</div>';
    echo '<p class="admin-muted">Lifetime stats for this campaign.</p>';
    echo '</div>';

    echo '<div class="admin-grid admin-grid-tight">';
    echo admin_stat_card('Page Views', analytics_metric($pdo, 'SELECT COUNT(*) FROM site_pageviews WHERE ' . $whereSql, $params), 'info');
    echo admin_stat_card('Unique Visitors', analytics_metric($pdo, 'SELECT COUNT(DISTINCT visitor_id) FROM site_pageviews WHERE ' . $whereSql, $params), 'ok');
    echo admin_stat_card('Sessions', analytics_metric($pdo, 'SELECT COUNT(DISTINCT session_id) FROM site_pageviews WHERE ' . $whereSql, $params), 'info');
    echo admin_stat_card('Unique IPs', analytics_metric($pdo, 'SELECT ' . $ipExpr . ' FROM site_pageviews WHERE ' . $whereSql, $params), 'info');
    echo admin_stat_card('Human Views', analytics_metric($pdo, 'SELECT COUNT(*) FROM site_pageviews WHERE is_bot = 0 AND ' . $whereSql, $params), 'ok');
    echo admin_stat_card('Bot Views', analytics_metric($pdo, 'SELECT COUNT(*) FROM site_pageviews WHERE is_bot = 1 AND ' . $whereSql, $params), 'warn');
    echo '</div>';

    $locationRows = analytics_fetch(
        $pdo,
        'SELECT COALESCE(NULLIF(country_code, ""), "Unknown") AS label, COUNT(*) AS total
         FROM site_pageviews WHERE ' . $whereSql . '
         GROUP BY label ORDER BY total DESC LIMIT 12',
        $params
    );
    $languageRows = analytics_utm_language_breakdown($pdo, $whereSql, $params);
    $pageRows = analytics_fetch(
        $pdo,
        'SELECT COALESCE(NULLIF(route_name, ""), "unknown") AS label, COUNT(*) AS total
         FROM site_pageviews WHERE ' . $whereSql . '
         GROUP BY label ORDER BY total DESC LIMIT 12',
        $params
    );

    echo '<div class="admin-grid utm-pie-grid">';
    echo '<div class="admin-card utm-detail-section"><h3>Locations</h3>';
    analytics_render_pie_chart($locationRows, 'label', 'total', 'Locations');
    echo '</div>';
    echo '<div class="admin-card utm-detail-section"><h3>Languages</h3>';
    analytics_render_pie_chart($languageRows, 'label', 'total', 'Languages');
    echo '</div>';
    echo '<div class="admin-card utm-detail-section"><h3>Pages viewed</h3>';
    analytics_render_pie_chart($pageRows, 'label', 'total', 'Pages viewed');
    echo '</div>';
    echo '</div>';

    $topPagesSql = 'SELECT url, route_name, page_title, COUNT(*) AS views FROM site_pageviews WHERE ' . $whereSql . ' GROUP BY url, route_name, page_title';
    $topPagesColumns = [
        ['label' => 'Page'],
        ['label' => 'Route'],
        ['label' => 'Title'],
        ['label' => 'Views', 'sort' => 'views', 'sql' => 'views', 'default' => true, 'default_dir' => 'desc'],
    ];
    $topPages = analytics_fetch($pdo, analytics_apply_order($topPagesSql, $topPagesColumns) . ' LIMIT 15', $params);
    echo '<div class="admin-card utm-detail-section"><h3>Top pages</h3>';
    analytics_render_sortable_table($topPagesColumns, array_map(static fn(array $row): array => [
        admin_url_cell((string)$row['url']),
        h((string)$row['route_name']),
        h((string)$row['page_title']),
        h((string)$row['views']),
    ], $topPages), 'No pages yet', 'No pageviews matched this campaign yet.', analytics_sort_link_params([
        'detail' => 'utm',
        'utm_source' => $source,
        'utm_medium' => $medium,
        'utm_campaign' => $campaign,
    ]));
    echo '</div>';

    $externalReferrers = analytics_sql_external_referrer_only();
    $topReferrerParams = array_merge($params, $externalReferrers['params']);
    $topReferrersSql = 'SELECT COALESCE(NULLIF(referrer, ""), "(direct / none)") AS referrer, COUNT(*) AS views FROM site_pageviews WHERE ' . $whereSql . ' AND ' . $externalReferrers['sql'] . ' GROUP BY referrer';
    $topReferrerColumns = [
        ['label' => 'Referrer'],
        ['label' => 'Views', 'sort' => 'views', 'sql' => 'views', 'default' => true, 'default_dir' => 'desc'],
    ];
    $topReferrers = analytics_fetch($pdo, analytics_apply_order($topReferrersSql, $topReferrerColumns) . ' LIMIT 15', $topReferrerParams);
    echo '<div class="admin-card utm-detail-section"><h3>Top referrers</h3>';
    analytics_render_sortable_table($topReferrerColumns, array_map(static fn(array $row): array => [
        (string)$row['referrer'] === '(direct / none)' ? h('(direct / none)') : analytics_referrer_cell((string)$row['referrer']),
        h((string)$row['views']),
    ], $topReferrers), 'No referrers yet', 'External referrers will appear after tagged traffic arrives. Same-site navigation is hidden.', analytics_sort_link_params([
        'detail' => 'utm',
        'utm_source' => $source,
        'utm_medium' => $medium,
        'utm_campaign' => $campaign,
    ]));
    echo '</div>';

    $visitorsSql = 'SELECT
            visitor_id,
            MAX(session_id) AS session_id,
            MIN(created_at) AS first_seen,
            MAX(created_at) AS last_seen,
            COUNT(*) AS views,
            MAX(ip_address) AS ip_address,
            MAX(ip_hash) AS ip_hash,
            MAX(country_code) AS country_code,
            MAX(city_name) AS city_name,
            MAX(region_name) AS region_name,
            MAX(asn_number) AS asn_number,
            MAX(asn_org) AS asn_org,
            MAX(device_type) AS device_type,
            MAX(browser_name) AS browser_name,
            MAX(os_name) AS os_name,
            MAX(is_bot) AS is_bot,
            MAX(bot_name) AS bot_name,
            MAX(referrer) AS last_referrer
        FROM site_pageviews
        WHERE ' . $whereSql . '
        GROUP BY visitor_id';
    $visitorColumns = [
        ['label' => 'Visitor'],
        ['label' => 'IP'],
        ['label' => 'Location'],
        ['label' => 'ASN'],
        ['label' => 'First Seen', 'sort' => 'first_seen', 'sql' => 'first_seen', 'default_dir' => 'desc'],
        ['label' => 'Last Seen', 'sort' => 'last_seen', 'sql' => 'last_seen', 'default' => true, 'default_dir' => 'desc'],
        ['label' => 'Views', 'sort' => 'views', 'sql' => 'views', 'default_dir' => 'desc'],
        ['label' => 'Device'],
        ['label' => 'Browser'],
        ['label' => 'OS'],
        ['label' => 'Last Referrer'],
        ['label' => 'Bot'],
    ];
    $visitors = analytics_fetch($pdo, analytics_apply_order($visitorsSql, $visitorColumns) . ' LIMIT 200', $params);

    echo '<div class="admin-card utm-detail-section"><div class="utm-results-head"><h3>Visitors</h3>';
    admin_filter_box('Filter table', 'IP, location, browser, referrer');
    echo '</div>';
    analytics_render_sortable_table($visitorColumns, array_map(static function (array $row): array {
            return [
                analytics_detail_link('visitor', (string)$row['visitor_id'], substr((string)$row['visitor_id'], 0, 12) . '...'),
                '<code class="admin-mono-cell">' . h(analytics_row_ip($row)) . '</code>',
                analytics_location_cell($row),
                analytics_asn_cell($row),
                analytics_h_datetime($row['first_seen'] ?? null),
                analytics_h_datetime($row['last_seen'] ?? null),
                h((string)$row['views']),
                h((string)$row['device_type']),
                h((string)$row['browser_name']),
                h((string)$row['os_name']),
                analytics_referrer_cell((string)$row['last_referrer']),
                ((int)$row['is_bot'] === 1) ? h((string)$row['bot_name']) : h('Human'),
            ];
        }, $visitors),
        'No visitors yet',
        'Visitors from this campaign will appear here after tagged traffic is recorded.',
        analytics_sort_link_params([
            'detail' => 'utm',
            'utm_source' => $source,
            'utm_medium' => $medium,
            'utm_campaign' => $campaign,
        ])
    );
    echo '</div>';

    $sessionsSql = 'SELECT
            session_id,
            MAX(visitor_id) AS visitor_id,
            MIN(created_at) AS first_seen,
            MAX(created_at) AS last_seen,
            COUNT(*) AS views,
            MIN(url) AS entry_url,
            MAX(url) AS exit_url
        FROM site_pageviews
        WHERE session_id IS NOT NULL AND ' . $whereSql . '
        GROUP BY session_id';
    $sessionColumns = [
        ['label' => 'Session'],
        ['label' => 'Visitor'],
        ['label' => 'First Seen', 'sort' => 'first_seen', 'sql' => 'first_seen', 'default_dir' => 'desc'],
        ['label' => 'Last Seen', 'sort' => 'last_seen', 'sql' => 'last_seen', 'default' => true, 'default_dir' => 'desc'],
        ['label' => 'Views', 'sort' => 'views', 'sql' => 'views', 'default_dir' => 'desc'],
        ['label' => 'Entry URL'],
        ['label' => 'Exit URL'],
    ];
    $sessions = analytics_fetch($pdo, analytics_apply_order($sessionsSql, $sessionColumns) . ' LIMIT 100', $params);

    echo '<div class="admin-card utm-detail-section"><div class="utm-results-head"><h3>Sessions</h3>';
    admin_filter_box('Filter table', 'Session, visitor, or URL');
    echo '</div>';
    analytics_render_sortable_table($sessionColumns, array_map(static fn(array $row): array => [
        analytics_detail_link('session', (string)$row['session_id'], substr((string)$row['session_id'], 0, 12) . '...'),
        analytics_detail_link('visitor', (string)$row['visitor_id'], substr((string)$row['visitor_id'], 0, 12) . '...'),
        analytics_h_datetime($row['first_seen'] ?? null),
        analytics_h_datetime($row['last_seen'] ?? null),
        h((string)$row['views']),
        admin_url_cell((string)$row['entry_url']),
        admin_url_cell((string)$row['exit_url']),
    ], $sessions), 'No sessions yet', 'Session rows for this campaign will appear here after visitors browse the site.', analytics_sort_link_params([
        'detail' => 'utm',
        'utm_source' => $source,
        'utm_medium' => $medium,
        'utm_campaign' => $campaign,
    ]));
    echo '</div>';

    $timelineSql = 'SELECT created_at, url, page_title, visitor_id, referrer, ip_address, ip_hash, response_status, load_time_ms, device_type, browser_name FROM site_pageviews WHERE ' . $whereSql;
    $timelineColumns = [
        ['label' => 'When', 'sort' => 'created_at', 'sql' => 'created_at', 'default' => true, 'default_dir' => 'desc'],
        ['label' => 'URL'],
        ['label' => 'Title'],
        ['label' => 'Visitor'],
        ['label' => 'IP'],
        ['label' => 'Referrer'],
        ['label' => 'Status', 'sort' => 'response_status', 'sql' => 'response_status', 'default_dir' => 'desc'],
        ['label' => 'Load', 'sort' => 'load_time_ms', 'sql' => 'load_time_ms', 'default_dir' => 'desc'],
        ['label' => 'Device'],
    ];
    $timeline = analytics_fetch($pdo, analytics_apply_order($timelineSql, $timelineColumns) . ' LIMIT 300', $params);
    echo '<div class="admin-card utm-detail-section"><div class="utm-results-head"><h3>Recent pageviews</h3>';
    admin_filter_box('Filter table', 'URL, IP, referrer, visitor');
    echo '</div>';
    analytics_render_sortable_table($timelineColumns, array_map(static fn(array $row): array => [
        analytics_h_datetime($row['created_at'] ?? null),
        admin_url_cell((string)$row['url']),
        h((string)$row['page_title']),
        analytics_detail_link('visitor', (string)$row['visitor_id'], substr((string)$row['visitor_id'], 0, 12) . '...'),
        '<code class="admin-mono-cell">' . h(analytics_row_ip($row)) . '</code>',
        analytics_referrer_cell((string)$row['referrer']),
        h((string)$row['response_status']),
        h((string)$row['load_time_ms']) . ' ms',
        h((string)$row['device_type'] . ' / ' . (string)$row['browser_name']),
    ], $timeline), 'No pageviews yet', 'Recent pageviews for this campaign will appear here.', analytics_sort_link_params([
        'detail' => 'utm',
        'utm_source' => $source,
        'utm_medium' => $medium,
        'utm_campaign' => $campaign,
    ]));
    echo '</div>';
    analytics_render_utm_copy_script();
}

function analytics_render_detail(PDO $pdo, string $type, string $id): void
{
    echo '<div class="admin-actions">' . admin_action_button('Back to Site Analytics', admin_url('/analytics.php'), 'secondary') . '</div>';
    if ($type === 'visitor' || $type === 'session') {
        $field = $type === 'visitor' ? 'visitor_id' : 'session_id';
        $statsRows = analytics_fetch($pdo, "SELECT * FROM site_pageviews WHERE {$field} = ? ORDER BY created_at ASC LIMIT 500", [$id]);
        if ($statsRows === []) {
            echo admin_empty_state('No detail found', 'No pageview rows were found for this ' . $type . '.');
            return;
        }
        $first = $statsRows[0];
        $last = $statsRows[count($statsRows) - 1];
        $detailParams = analytics_sort_link_params(['detail' => $type, 'id' => $id]);
        $timelineColumns = [
            ['label' => 'When', 'sort' => 'created_at', 'sql' => 'created_at', 'default' => true, 'default_dir' => 'asc'],
            ['label' => 'URL'],
            ['label' => 'Title'],
            ['label' => 'IP'],
            ['label' => 'Location'],
            ['label' => 'Referrer'],
            ['label' => 'Campaign'],
            ['label' => 'Status', 'sort' => 'response_status', 'sql' => 'response_status', 'default_dir' => 'desc'],
            ['label' => 'Load', 'sort' => 'load_time_ms', 'sql' => 'load_time_ms', 'default_dir' => 'desc'],
        ];
        $rows = analytics_fetch($pdo, analytics_apply_order("SELECT * FROM site_pageviews WHERE {$field} = ?", $timelineColumns) . ' LIMIT 500', [$id]);
        echo '<div class="admin-grid admin-grid-tight">';
        echo admin_stat_card('First Seen', analytics_format_datetime((string)$first['created_at']), 'info');
        echo admin_stat_card('Last Seen', analytics_format_datetime((string)$last['created_at']), 'info');
        echo admin_stat_card('Total Page Views', (string)count($statsRows), 'ok');
        echo admin_stat_card('Device', (string)$last['device_type'], 'info');
        echo admin_stat_card('Browser', (string)$last['browser_name'], 'info');
        echo admin_stat_card('OS', (string)$last['os_name'], 'info');
        echo admin_stat_card('IP Address', analytics_row_ip($last), 'info');
        echo admin_stat_card('Location', geoip_format_location($last), 'info');
        echo admin_stat_card('ASN / ISP', geoip_format_asn($last), 'info');
        echo admin_stat_card('Country', (string)($last['country_code'] ?? 'not_available'), 'info');
        echo admin_stat_card('Bot Classification', ((int)$last['is_bot'] === 1) ? (string)$last['bot_name'] : 'Human', ((int)$last['is_bot'] === 1) ? 'warn' : 'ok');
        echo '</div>';
        analytics_render_sortable_table($timelineColumns, array_map(static fn(array $row): array => [analytics_h_datetime($row['created_at'] ?? null), admin_url_cell((string)$row['url']), h((string)$row['page_title']), '<code class="admin-mono-cell">' . h(analytics_row_ip($row)) . '</code>', analytics_location_cell($row), analytics_referrer_cell((string)$row['referrer']), h(trim(implode(' / ', array_filter([(string)($row['utm_source'] ?? ''), (string)($row['utm_medium'] ?? ''), (string)($row['utm_campaign'] ?? '')])))), h((string)$row['response_status']), h((string)$row['load_time_ms']) . ' ms'], $rows), 'No timeline', 'No timeline rows found.', $detailParams);
        $ipHashes = array_values(array_unique(array_filter(array_map(static fn(array $row): string => (string)$row['ip_hash'], $statsRows))));
        if ($ipHashes !== []) {
            $in = implode(',', array_fill(0, count($ipHashes), '?'));
            if (admin_analytics_table_exists($pdo, 'download_events')) {
                $downloadColumns = [
                    ['label' => 'When', 'sort' => 'created_at', 'sql' => 'de.created_at', 'default' => true, 'default_dir' => 'desc'],
                    ['label' => 'Download'],
                    ['label' => 'File'],
                ];
                $downloads = analytics_fetch($pdo, analytics_apply_order("SELECT de.created_at, d.title, d.file_url FROM download_events de LEFT JOIN downloads d ON d.id = de.download_id WHERE de.ip_hash IN ({$in})", $downloadColumns) . ' LIMIT 100', $ipHashes);
                analytics_render_sortable_table($downloadColumns, array_map(static fn(array $row): array => [analytics_h_datetime($row['created_at'] ?? null), h((string)($row['title'] ?? 'Download')), h((string)($row['file_url'] ?? ''))], $downloads), 'No download clicks', 'No download clicks are linked by hashed IP.', $detailParams);
            }
            if (admin_analytics_table_exists($pdo, 'site_errors')) {
                $errorColumns = [
                    ['label' => 'When', 'sort' => 'created_at', 'sql' => 'created_at', 'default' => true, 'default_dir' => 'desc'],
                    ['label' => 'Level'],
                    ['label' => 'Message'],
                    ['label' => 'URL'],
                ];
                $errors = analytics_fetch($pdo, analytics_apply_order("SELECT created_at, level, message, url FROM site_errors WHERE ip_hash IN ({$in})", $errorColumns) . ' LIMIT 100', $ipHashes);
                analytics_render_sortable_table($errorColumns, array_map(static fn(array $row): array => [analytics_h_datetime($row['created_at'] ?? null), h((string)$row['level']), h((string)$row['message']), h((string)$row['url'])], $errors), 'No linked errors', 'No site errors are linked by hashed IP.', $detailParams);
            }
            if (admin_analytics_table_exists($pdo, 'admin_security_events')) {
                $securityColumns = [
                    ['label' => 'When', 'sort' => 'created_at', 'sql' => 'created_at', 'default' => true, 'default_dir' => 'desc'],
                    ['label' => 'Event'],
                    ['label' => 'Username Attempted'],
                    ['label' => 'Details'],
                ];
                $security = analytics_fetch($pdo, analytics_apply_order("SELECT created_at, event_type, username_attempted, details_json FROM admin_security_events WHERE ip_hash IN ({$in})", $securityColumns) . ' LIMIT 100', $ipHashes);
                analytics_render_sortable_table($securityColumns, array_map(static fn(array $row): array => [analytics_h_datetime($row['created_at'] ?? null), h((string)$row['event_type']), h((string)($row['username_attempted'] ?? '')), '<pre>' . h((string)($row['details_json'] ?? '')) . '</pre>'], $security), 'No linked security events', 'No admin security events are linked by hashed IP.', $detailParams);
            }
        }
        return;
    }
    if ($type === 'page') {
        $detailParams = analytics_sort_link_params(['detail' => $type, 'id' => $id]);
        $columns = [
            ['label' => 'When', 'sort' => 'created_at', 'sql' => 'created_at', 'default' => true, 'default_dir' => 'desc'],
            ['label' => 'Visitor'],
            ['label' => 'Status', 'sort' => 'response_status', 'sql' => 'response_status', 'default_dir' => 'desc'],
            ['label' => 'Referrer'],
            ['label' => 'Load', 'sort' => 'load_time_ms', 'sql' => 'load_time_ms', 'default_dir' => 'desc'],
            ['label' => 'User Agent'],
        ];
        $rows = analytics_fetch($pdo, analytics_apply_order('SELECT * FROM site_pageviews WHERE url = ?', $columns) . ' LIMIT 500', [$id]);
        analytics_render_sortable_table($columns, array_map(static fn(array $row): array => [analytics_h_datetime($row['created_at'] ?? null), analytics_detail_link('visitor', (string)$row['visitor_id'], substr((string)$row['visitor_id'], 0, 12) . '...'), h((string)$row['response_status']), analytics_referrer_cell((string)$row['referrer']), h((string)$row['load_time_ms']) . ' ms', h((string)$row['user_agent'])], $rows), 'No page detail', 'No pageviews were found for this URL.', $detailParams);
        return;
    }
    if ($type === 'referrer') {
        $detailParams = analytics_sort_link_params(['detail' => $type, 'id' => $id]);
        $columns = [
            ['label' => 'When', 'sort' => 'created_at', 'sql' => 'created_at', 'default' => true, 'default_dir' => 'desc'],
            ['label' => 'Page'],
            ['label' => 'Visitor'],
            ['label' => 'Campaign'],
            ['label' => 'Status', 'sort' => 'response_status', 'sql' => 'response_status', 'default_dir' => 'desc'],
        ];
        $rows = analytics_fetch($pdo, analytics_apply_order('SELECT * FROM site_pageviews WHERE referrer = ?', $columns) . ' LIMIT 500', [$id]);
        analytics_render_sortable_table($columns, array_map(static fn(array $row): array => [analytics_h_datetime($row['created_at'] ?? null), analytics_detail_link('page', (string)$row['url'], (string)$row['url']), analytics_detail_link('visitor', (string)$row['visitor_id'], substr((string)$row['visitor_id'], 0, 12) . '...'), h(trim((string)$row['utm_source'] . ' ' . (string)$row['utm_campaign'])), h((string)$row['response_status'])], $rows), 'No referrer detail', 'No pageviews were found for this referrer.', $detailParams);
        return;
    }
    if ($type === 'bot') {
        $detailParams = analytics_sort_link_params(['detail' => $type, 'id' => $id]);
        $columns = [
            ['label' => 'When', 'sort' => 'created_at', 'sql' => 'created_at', 'default' => true, 'default_dir' => 'desc'],
            ['label' => 'Type'],
            ['label' => 'Threat'],
            ['label' => 'Event'],
            ['label' => 'URL'],
            ['label' => 'User Agent'],
        ];
        $rows = analytics_fetch($pdo, analytics_apply_order('SELECT * FROM bot_events WHERE bot_name = ?', $columns) . ' LIMIT 500', [$id]);
        analytics_render_sortable_table($columns, array_map(static fn(array $row): array => [analytics_h_datetime($row['created_at'] ?? null), h((string)$row['bot_type']), h((string)$row['threat_level']), h((string)$row['event_type']), h((string)$row['url']), h((string)$row['user_agent'])], $rows), 'No bot detail', 'No events were found for this bot.', $detailParams);
        return;
    }
    if ($type === 'download') {
        $detailParams = analytics_sort_link_params(['detail' => $type, 'id' => $id]);
        $columns = [
            ['label' => 'When', 'sort' => 'created_at', 'sql' => 'de.created_at', 'default' => true, 'default_dir' => 'desc'],
            ['label' => 'Title'],
            ['label' => 'Platform'],
            ['label' => 'Version'],
            ['label' => 'File'],
            ['label' => 'Referrer'],
            ['label' => 'User Agent'],
        ];
        $rows = analytics_fetch($pdo, analytics_apply_order('SELECT de.created_at, de.referrer, de.user_agent, d.title, d.platform, d.file_url, d.version, d.checksum_sha256 FROM download_events de LEFT JOIN downloads d ON d.id = de.download_id WHERE d.id = ?', $columns) . ' LIMIT 500', [(int)$id]);
        analytics_render_sortable_table($columns, array_map(static fn(array $row): array => [analytics_h_datetime($row['created_at'] ?? null), h((string)$row['title']), h((string)$row['platform']), h((string)$row['version']), h((string)$row['file_url']), analytics_referrer_cell((string)$row['referrer']), h((string)$row['user_agent'])], $rows), 'No download detail', 'No events were found for this download.', $detailParams);
        return;
    }
    echo admin_empty_state('Unknown detail type', 'This analytics detail view is not registered.');
}

function analytics_render_auto_refresh(int $seconds = 15): void
{
    $seconds = max(5, $seconds);
    echo '<div class="admin-card admin-muted" data-analytics-autorefresh="1">Auto-updating every ' . h((string)$seconds) . ' seconds while this tab is open.</div>';
    echo '<script>
(function () {
  var seconds = ' . (int)$seconds . ';
  window.setInterval(function () {
    var active = document.activeElement;
    var tag = active && active.tagName ? active.tagName.toLowerCase() : "";
    if (document.hidden || tag === "input" || tag === "select" || tag === "textarea") {
      return;
    }
    window.location.reload();
  }, seconds * 1000);
})();
</script>';
}

$missing = analytics_require_tables($pdo, ['site_pageviews', 'site_visitors', 'bot_events', 'download_events', 'downloads']);
$detailType = (string)($_GET['detail'] ?? '');
$detailId = (string)($_GET['id'] ?? '');
$utmDetailSource = (string)($_GET['utm_source'] ?? '');
$utmDetailMedium = (string)($_GET['utm_medium'] ?? '');
$utmDetailCampaign = (string)($_GET['utm_campaign'] ?? '');
$tab = (string)($_GET['tab'] ?? 'overview');
$tabs = analytics_tabs();
if (!isset($tabs[$tab])) {
    $tab = 'overview';
}
$filters = analytics_date_filters();

render_admin_header($detailType !== '' ? 'Analytics Detail' : 'Site Analytics', ['Site Analytics']);

if ($missing !== []) {
    admin_render_alert('warning', 'Analytics tables missing: ' . implode(', ', $missing) . '. Run php /home/hobbyhashcoin/hobbyhash-clean/wallet/run_migrations.php.');
}

if ($detailType === 'utm') {
    analytics_render_utm_detail($pdo, $filters, $utmDetailSource, $utmDetailMedium, $utmDetailCampaign);
    render_admin_footer();
    exit;
}

if ($detailType !== '' && $detailId !== '') {
    analytics_render_detail($pdo, $detailType, $detailId);
    render_admin_footer();
    exit;
}

echo '<div class="admin-tabs" data-admin-tabs>';
foreach ($tabs as $key => $label) {
    echo '<a class="admin-tab ' . ($key === $tab ? 'is-active' : '') . '" href="' . h(admin_url('/analytics.php?' . http_build_query(array_merge(analytics_base_params($filters, $key), ['page' => 1])))) . '">' . h($label) . '</a>';
}
echo '</div>';

if ($missing === []) {
    analytics_render_tab($pdo, $tab, $filters);
    if (in_array($tab, ['overview', 'realtime'], true) && (string)($_GET['export'] ?? '') === '') {
        analytics_render_auto_refresh();
    }
} else {
    echo admin_empty_state('Migration required', 'Apply the admin analytics migration before using this section.');
}

render_admin_footer();
