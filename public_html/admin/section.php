<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/ledger.php';
require_once __DIR__ . '/../app/rpc.php';
require_once __DIR__ . '/../app/site_status.php';
require_once __DIR__ . '/../app/admin_view.php';
require_once __DIR__ . '/../app/admin_migrations.php';
require_once __DIR__ . '/../app/analytics.php';
require_once __DIR__ . '/../app/geoip.php';
require_once __DIR__ . '/../api/_bootstrap.php';

$admin = admin_require_user();
$pdo = wallet_db();

function admin_section_configs(): array
{
    return [
        'site-analytics' => ['title' => 'Site Analytics', 'description' => 'Real website event totals once the analytics collector is installed.'],
        'visitors' => ['title' => 'Visitors', 'description' => 'Privacy-conscious visitor records and daily rollups.'],
        'bots-crawlers' => ['title' => 'Bots & Crawlers', 'description' => 'Crawler and bot traffic detected by the analytics collector.'],
        'traffic-sources' => ['title' => 'Traffic Sources', 'description' => 'Referrer/source rollups from stored analytics events.'],
        'pages-content' => ['title' => 'Pages & Content', 'description' => 'Current public pages, docs routes, support entry points, and FAQ data.'],
        'downloads' => ['title' => 'Downloads', 'description' => 'Current download manifests and checksum files served by the public website.'],
        'docs' => ['title' => 'Docs', 'description' => 'Current public documentation routes.'],
        'wallets' => ['title' => 'Wallets', 'description' => 'Wallet users, receive addresses, deposits, withdrawals, and ledger totals.'],
        'users' => ['title' => 'Users', 'description' => 'Registered wallet users from the current `users` table.'],
        'mining-pool' => ['title' => 'Mining Pool', 'description' => 'Main and Nano pool data from real ckpool collector JSON.'],
        'nodes' => ['title' => 'Nodes', 'description' => 'Read-only node status from safe local RPC calls.'],
        'blockchain-stats' => ['title' => 'Blockchain Stats', 'description' => 'Read-only chain statistics from RPC and public API helpers.'],
        'explorer-stats' => ['title' => 'Explorer Stats', 'description' => 'Explorer status and latest indexed chain data.'],
        'burn-events' => ['title' => 'Burn Events', 'description' => 'Burn tracking from the public burn status helper.'],
        'announcements' => ['title' => 'Announcements', 'description' => 'Current site status messages that are shown to visitors.'],
        'security-center' => ['title' => 'Security Center', 'description' => 'Security events, rate limits, 2FA settings, and admin login controls.'],
        'admin-users-roles' => ['title' => 'Admin Users & Roles', 'description' => 'Current admin users and the role fields available today.'],
        'system-health' => ['title' => 'System Health', 'description' => 'Current job, wallet, RPC, SMTP, SMS, and scanner health signals.'],
    ];
}

function admin_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool)$stmt->fetchColumn();
}

function admin_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->query("SHOW COLUMNS FROM `" . str_replace('`', '``', $table) . "` LIKE " . $pdo->quote($column));
    return (bool)$stmt->fetch();
}

function admin_count(PDO $pdo, string $table, string $where = '1=1'): int
{
    if (!admin_table_exists($pdo, $table)) {
        return 0;
    }
    return (int)$pdo->query("SELECT COUNT(*) FROM `" . str_replace('`', '``', $table) . "` WHERE {$where}")->fetchColumn();
}

function admin_fmt_int(int|float|string $value): string
{
    return number_format((float)$value, 0);
}

function admin_fmt_hobc(float|string $value): string
{
    return number_format((float)$value, 8, '.', '');
}

function admin_read_json(string $path): ?array
{
    $data = hobc_read_json_file($path);
    return is_array($data) ? $data : null;
}

function admin_public_page_rows(): array
{
    $pages = [
        ['Home', '/', 'Main public dashboard'],
        ['About HOBC', '/about/', 'About page'],
        ['Mining', '/mining/', 'Mining overview'],
        ['Main Pool', '/pool/main/', 'Main pool public page'],
        ['Nano Pool', '/pool/nano/', 'Nano pool public page'],
        ['Explorer', '/explorer/', 'Explorer route'],
        ['Wallet', '/wallet/', 'Wallet entry'],
        ['Stats', '/stats/', 'Stats page'],
        ['Downloads', '/downloads/', 'Downloads page'],
        ['Docs', '/docs/', 'Docs index'],
        ['Launch Reserve', '/launch-reserve/', 'Reserve transparency'],
        ['Burn Tracker', '/burn/', 'Burn tracking'],
        ['Contact/Support', '/contact/', 'Support entry'],
        ['Privacy', '/privacy/', 'Legal page'],
        ['Terms', '/terms/', 'Legal page'],
    ];

    return array_map(static fn(array $page): array => [
        h($page[0]),
        '<a href="' . h($page[1]) . '">' . h($page[1]) . '</a>',
        h($page[2]),
    ], $pages);
}

function admin_docs_rows(): array
{
    $base = realpath(__DIR__ . '/../docs');
    if ($base === false) {
        return [];
    }
    $rows = [];
    foreach (scandir($base) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $base . '/' . $entry;
        if (!is_dir($path) || !is_file($path . '/index.php')) {
            continue;
        }
        $title = ucwords(str_replace('-', ' ', $entry));
        $url = '/docs/' . $entry . '/';
        $rows[] = [h($title), '<a href="' . h($url) . '">' . h($url) . '</a>', admin_h_timestamp((int)filemtime($path . '/index.php'))];
    }
    usort($rows, static fn(array $a, array $b): int => strcmp(strip_tags($a[0]), strip_tags($b[0])));
    return $rows;
}

function admin_download_rows(): array
{
    $rows = [];
    $windowsManifest = __DIR__ . '/../downloads/windows/latest.json';
    $windowsSums = __DIR__ . '/../downloads/windows/HobbyHash-Wallets-SHA256SUMS.txt';
    $linuxSums = __DIR__ . '/../downloads/linux/HobbyHash-Linux-SHA256SUMS.txt';

    if (is_file($windowsManifest)) {
        $json = admin_read_json($windowsManifest) ?? [];
        $rows[] = [
            'Windows latest manifest',
            '<code>/downloads/windows/latest.json</code>',
            h((string)($json['version'] ?? $json['name'] ?? 'manifest present')),
            admin_h_timestamp((int)filemtime($windowsManifest)),
        ];
    }
    foreach ([['Windows checksums', $windowsSums], ['Linux checksums', $linuxSums]] as [$label, $path]) {
        if (is_file($path)) {
            $rows[] = [
                h($label),
                '<code>' . h(str_replace(__DIR__ . '/..', '', $path)) . '</code>',
                h(number_format((float)filesize($path)) . ' bytes'),
                admin_h_timestamp((int)filemtime($path)),
            ];
        }
    }
    return $rows;
}

function admin_pool_rows(): array
{
    $paths = [
        'Main Pool' => '/home/hobbyhashcoin/hobbyhash-data/mainnet/pool-stats-main.json',
        'Nano Pool' => '/home/hobbyhashcoin/hobbyhash-data/mainnet/pool-stats-nano.json',
    ];
    $rows = [];
    foreach ($paths as $label => $path) {
        $data = admin_read_json($path);
        if ($data === null) {
            continue;
        }
        $rows[] = [
            h($label),
            h((string)($data['collected_at'] ?? $data['generated_at'] ?? 'not_available')),
            h(admin_fmt_int((int)($data['accepted_shares'] ?? 0))),
            h(admin_fmt_int((int)($data['rejected_shares'] ?? 0))),
            h(admin_fmt_int((int)($data['active_sessions'] ?? 0))),
            h(admin_fmt_int((int)($data['seen_workers'] ?? 0))),
            h((string)($data['best_share'] ?? '0')),
        ];
    }
    return $rows;
}

function admin_render_analytics_section(PDO $pdo, string $section, string $title, string $description): void
{
    echo '<div class="admin-card"><p>' . h($description) . '</p></div>';

    $table = match ($section) {
        'visitors' => 'site_visitors',
        'bots-crawlers' => 'bot_events',
        default => 'site_pageviews',
    };

    if (!admin_table_exists($pdo, $table)) {
        echo admin_empty_state('Migration required', 'The `' . $table . '` table is missing. Run `php /home/hobbyhashcoin/hobbyhash-clean/wallet/run_migrations.php` first.');
        return;
    }

    if ($section === 'site-analytics') {
        $activeNowSql = admin_table_exists($pdo, 'site_visitors')
            ? "SELECT COUNT(*) FROM site_visitors WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND is_bot = 0"
            : "SELECT COUNT(DISTINCT visitor_id) FROM site_pageviews WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
        $stats = [
            'Active Now' => $activeNowSql,
            'Visitors Today' => "SELECT COUNT(DISTINCT visitor_id) FROM site_pageviews WHERE created_at >= CONCAT(CURDATE(), ' 00:00:00')",
            'Last 24 Hours' => "SELECT COUNT(DISTINCT visitor_id) FROM site_pageviews WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            'Last 7 Days' => "SELECT COUNT(DISTINCT visitor_id) FROM site_pageviews WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'Last 30 Days' => "SELECT COUNT(DISTINCT visitor_id) FROM site_pageviews WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            'Human Pageviews' => "SELECT COUNT(*) FROM site_pageviews WHERE is_bot = 0",
            'Bot Pageviews' => "SELECT COUNT(*) FROM site_pageviews WHERE is_bot = 1",
            'Slow Pages' => "SELECT COUNT(*) FROM site_pageviews WHERE load_time_ms >= 1500",
        ];
        echo '<div class="admin-grid admin-grid-tight">';
        foreach ($stats as $label => $sql) {
            echo admin_stat_card($label, (string)(int)$pdo->query($sql)->fetchColumn(), str_contains($label, 'Bot') || str_contains($label, 'Slow') ? 'warn' : 'info');
        }
        echo '</div>';
        $topPages = $pdo->query("SELECT route_name, COUNT(*) AS views, MAX(created_at) AS last_seen FROM site_pageviews GROUP BY route_name ORDER BY views DESC LIMIT 25")->fetchAll();
        admin_render_table(['Top Page', 'Views', 'Last Seen'], array_map(static fn(array $row): array => [h((string)$row['route_name']), h((string)$row['views']), admin_h_datetime($row['last_seen'] ?? null)], $topPages), 'No pageviews yet', 'Pageview collection has not recorded any rows yet.');
        $entryPages = $pdo->query("SELECT pv.route_name, COUNT(*) AS entries FROM site_pageviews pv JOIN (SELECT session_id, MIN(id) AS first_id FROM site_pageviews WHERE session_id IS NOT NULL GROUP BY session_id) firsts ON firsts.first_id = pv.id GROUP BY pv.route_name ORDER BY entries DESC LIMIT 25")->fetchAll();
        admin_render_table(['Entry Page', 'Sessions'], array_map(static fn(array $row): array => [h((string)$row['route_name']), h((string)$row['entries'])], $entryPages), 'No entry pages yet', 'Session entry pages will appear after pageviews are collected.');
        $exitPages = $pdo->query("SELECT pv.route_name, COUNT(*) AS exits FROM site_pageviews pv JOIN (SELECT session_id, MAX(id) AS last_id FROM site_pageviews WHERE session_id IS NOT NULL GROUP BY session_id) lasts ON lasts.last_id = pv.id GROUP BY pv.route_name ORDER BY exits DESC LIMIT 25")->fetchAll();
        admin_render_table(['Exit Page', 'Sessions'], array_map(static fn(array $row): array => [h((string)$row['route_name']), h((string)$row['exits'])], $exitPages), 'No exit pages yet', 'Session exit pages will appear after pageviews are collected.');
        return;
    }

    if ($section === 'visitors') {
        $pageState = admin_page_state(50);
        $count = (int)$pdo->query('SELECT COUNT(*) FROM site_visitors')->fetchColumn();
        $pager = admin_pagination_meta($pageState['page'], $pageState['per_page'], $count);
        $stmt = $pdo->prepare('SELECT visitor_id, ip_address, first_seen_at, last_seen_at, pageview_count, is_bot, bot_name, country_code, city_name, region_name, asn_number, asn_org, device_type, browser_name, os_name FROM site_visitors ORDER BY last_seen_at DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $pager['per_page'], PDO::PARAM_INT);
        $stmt->bindValue(2, $pager['offset'], PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        admin_filter_box('Filter visitors');
        admin_render_table(['Visitor', 'IP', 'Location', 'ASN', 'First Seen', 'Last Seen', 'Views', 'Bot', 'Device', 'Browser', 'OS'], array_map(static fn(array $row): array => [
            h(substr((string)$row['visitor_id'], 0, 12) . '...'),
            '<code class="admin-mono-cell">' . h((string)($row['ip_address'] ?? '')) . '</code>',
            h(geoip_format_location($row)),
            h(geoip_format_asn($row)),
            admin_h_datetime($row['first_seen_at'] ?? null),
            admin_h_datetime($row['last_seen_at'] ?? null),
            h((string)$row['pageview_count']),
            ((int)$row['is_bot'] === 1) ? '<span class="warn">' . h((string)($row['bot_name'] ?? 'Bot')) . '</span>' : '<span class="ok">Human</span>',
            h((string)($row['device_type'] ?? '')),
            h((string)($row['browser_name'] ?? '')),
            h((string)($row['os_name'] ?? '')),
        ], $rows), 'No visitors yet', 'Unique visitors will appear after pageviews are collected.');
        admin_pagination($pager['page'], $pager['total_pages'], admin_url('/section.php'), ['section' => 'visitors'], $pager['total_rows']);
        return;
    }

    if ($section === 'bots-crawlers') {
        $known = $pdo->query("SELECT bot_name, bot_type, COUNT(*) AS events, MAX(created_at) AS last_seen FROM bot_events GROUP BY bot_name, bot_type ORDER BY events DESC LIMIT 50")->fetchAll();
        admin_render_table(['Bot', 'Type', 'Events', 'Last Seen'], array_map(static fn(array $row): array => [h((string)$row['bot_name']), h((string)$row['bot_type']), h((string)$row['events']), admin_h_datetime($row['last_seen'] ?? null)], $known), 'No bot events yet', 'Known and suspicious bot events will appear after collection starts.');
        $probes = $pdo->query("SELECT event_type, threat_level, url, COUNT(*) AS hits, MAX(created_at) AS last_seen FROM bot_events WHERE event_type IN ('login_probe','404_probe') OR threat_level IN ('high','critical') GROUP BY event_type, threat_level, url ORDER BY hits DESC LIMIT 50")->fetchAll();
        admin_render_table(['Probe Type', 'Threat', 'URL', 'Hits', 'Last Seen'], array_map(static fn(array $row): array => [h((string)$row['event_type']), h((string)$row['threat_level']), h((string)$row['url']), h((string)$row['hits']), admin_h_datetime($row['last_seen'] ?? null)], $probes), 'No probes yet', 'Login probes and 404 probes will appear here when detected.');
        return;
    }

    if ($section === 'traffic-sources') {
        $rows = $pdo->query(
            "SELECT COALESCE(NULLIF(utm_source, ''), NULLIF(referrer, ''), 'direct') AS source,
                    COUNT(*) AS pageviews,
                    MAX(created_at) AS last_seen
             FROM site_pageviews
             GROUP BY source
             ORDER BY pageviews DESC
             LIMIT 50"
        )->fetchAll();
        admin_render_table(['Source', 'Pageviews', 'Last Seen'], array_map(static fn(array $row): array => [
            h((string)$row['source']),
            h((string)$row['pageviews']),
            admin_h_datetime($row['last_seen'] ?? null),
        ], $rows), 'No data yet', 'Traffic source data has not been collected yet.');
        $campaigns = $pdo->query("SELECT utm_source, utm_medium, utm_campaign, COUNT(*) AS views FROM site_pageviews WHERE utm_source IS NOT NULL OR utm_medium IS NOT NULL OR utm_campaign IS NOT NULL GROUP BY utm_source, utm_medium, utm_campaign ORDER BY views DESC LIMIT 50")->fetchAll();
        admin_render_table(['UTM Source', 'Medium', 'Campaign', 'Views'], array_map(static fn(array $row): array => [h((string)($row['utm_source'] ?? '')), h((string)($row['utm_medium'] ?? '')), h((string)($row['utm_campaign'] ?? '')), h((string)$row['views'])], $campaigns), 'No UTM campaigns yet', 'UTM campaign rows will appear after tagged URLs are visited.');
        return;
    }

    $rows = $pdo->query("SELECT * FROM `" . str_replace('`', '``', $table) . "` ORDER BY id DESC LIMIT 50")->fetchAll();
    admin_filter_box('Filter ' . $title);
    admin_render_table(array_map('strval', array_keys($rows[0] ?? [])), array_map(static fn(array $row): array => array_map(static fn($value): string => h((string)$value), $row), $rows));
}

function admin_render_users(PDO $pdo): void
{
    $q = trim((string)($_GET['q'] ?? ''));
    $pageState = admin_page_state(25);
    $where = '1=1';
    $params = [];
    if ($q !== '') {
        $where = '(u.username LIKE ? OR u.email LIKE ?)';
        $params = ['%' . $q . '%', '%' . $q . '%'];
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$where}");
    $countStmt->execute($params);
    $pager = admin_pagination_meta($pageState['page'], $pageState['per_page'], (int)$countStmt->fetchColumn());

    $sql = "SELECT u.id, u.username, u.email, u.phone_verified_at, u.sms_2fa_enabled, u.is_active, u.created_at,
                   COALESCE(SUM(le.amount), 0) AS balance,
                   us.last_login_at, us.last_login_ip, us.twofa_enabled,
                   (SELECT COUNT(*) FROM deposits d WHERE d.user_id = u.id) AS deposit_count,
                   (SELECT COUNT(*) FROM withdrawals w WHERE w.user_id = u.id) AS withdrawal_count,
                   (SELECT COUNT(*) FROM deposit_addresses da WHERE da.user_id = u.id) AS address_count,
                   (SELECT COUNT(*) FROM wallet_user_holds h WHERE h.user_id = u.id AND h.status = 'active') AS active_holds
            FROM users u
            LEFT JOIN ledger_entries le ON le.user_id = u.id
            LEFT JOIN user_security us ON us.user_id = u.id
            WHERE {$where}
            GROUP BY u.id, u.username, u.email, u.phone_verified_at, u.sms_2fa_enabled, u.is_active, u.created_at,
                     us.last_login_at, us.last_login_ip, us.twofa_enabled
            ORDER BY balance DESC, u.id DESC
            LIMIT {$pager['per_page']} OFFSET {$pager['offset']}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo '<div class="admin-card"><form method="get" class="admin-filter-form"><input type="hidden" name="section" value="users"><label>Search users<input name="q" value="' . h($q) . '" placeholder="Username or email"></label><button type="submit">Search</button></form></div>';
    echo '<div class="admin-grid admin-grid-tight">';
    echo admin_stat_card('Total Users', (string)$pager['total_rows'], 'info');
    echo admin_stat_card('Active Users', (string)admin_count($pdo, 'users', 'is_active = 1'), 'ok');
    echo admin_stat_card('SMS Enabled', (string)admin_count($pdo, 'users', 'sms_2fa_enabled = 1'), 'warn');
    echo admin_stat_card('With Balance', (string)(int)$pdo->query('SELECT COUNT(*) FROM (SELECT user_id FROM ledger_entries GROUP BY user_id HAVING SUM(amount) > 0) x')->fetchColumn(), 'ok');
    echo '</div>';
    echo '<div class="admin-actions">' . admin_action_button('Wallet Balances', admin_url('/wallet.php?tab=balances'), 'secondary') . '</div>';

    $tableRows = array_map(static fn(array $row): array => [
        h((string)$row['id']),
        h((string)$row['username']),
        h((string)$row['email']),
        h(admin_fmt_hobc($row['balance'])),
        h((string)$row['deposit_count']),
        h((string)$row['withdrawal_count']),
        h((string)$row['address_count']),
        !empty($row['last_login_at']) ? admin_h_utc_datetime((string)$row['last_login_at']) : 'Never',
        '<code class="admin-mono-cell">' . h((string)($row['last_login_ip'] ?? '')) . '</code>',
        ((int)($row['twofa_enabled'] ?? 0) === 1 || (int)$row['sms_2fa_enabled'] === 1) ? '<span class="ok">On</span>' : '<span class="warn">Off</span>',
        ((int)$row['is_active'] === 1) ? '<span class="ok">Active</span>' : '<span class="err">Inactive</span>',
        ((int)$row['active_holds'] > 0) ? '<span class="warn">' . h((string)$row['active_holds']) . ' hold(s)</span>' : '<span class="ok">None</span>',
        admin_h_datetime($row['created_at'] ?? null),
    ], $rows);
    admin_render_table(['ID', 'Username', 'Email', 'Balance', 'Deposits', 'Withdrawals', 'Addresses', 'Last Login', 'Last IP', '2FA', 'Status', 'Holds', 'Created'], $tableRows, 'No users found', 'No users matched the current filter.');
    admin_pagination($pager['page'], $pager['total_pages'], admin_url('/section.php'), ['section' => 'users', 'q' => $q], $pager['total_rows']);
}

function admin_render_section(PDO $pdo, string $section, array $config): void
{
    echo '<div class="admin-card"><p>' . h((string)$config['description']) . '</p></div>';

    switch ($section) {
        case 'pages-content':
            echo '<div class="admin-grid admin-grid-tight">';
            echo admin_stat_card('Public Pages', (string)count(admin_public_page_rows()), 'info');
            echo admin_stat_card('FAQ Items', (string)admin_count($pdo, 'faq_items'), 'ok');
            echo admin_stat_card('Support Tickets', (string)admin_count($pdo, 'support_tickets'), 'warn');
            echo '</div>';
            admin_filter_box('Filter pages');
            admin_render_table(['Page', 'Route', 'Purpose'], admin_public_page_rows());
            break;

        case 'downloads':
            admin_render_table(['Artifact', 'File', 'Version / Size', 'Updated'], admin_download_rows(), 'No download metadata found', 'No download manifest or checksum files were found in the public downloads folder.');
            break;

        case 'docs':
            admin_filter_box('Filter docs');
            admin_render_table(['Doc', 'Route', 'Updated'], admin_docs_rows(), 'No docs found', 'No documentation index files were found under the public docs folder.');
            break;

        case 'wallets':
            $liabilities = ledger_total_liabilities();
            echo '<div class="admin-grid admin-grid-tight">';
            echo admin_stat_card('Users', (string)admin_count($pdo, 'users'), 'info');
            echo admin_stat_card('Deposit Addresses', (string)admin_count($pdo, 'deposit_addresses'), 'info');
            echo admin_stat_card('Deposits', (string)admin_count($pdo, 'deposits'), 'ok');
            echo admin_stat_card('Withdrawals', (string)admin_count($pdo, 'withdrawals'), 'warn');
            echo admin_stat_card('Ledger Liabilities', $liabilities, 'ok');
            echo '</div>';
            echo '<div class="admin-actions">' . admin_action_button('Open Custodial Controls', admin_url('/wallet.php')) . admin_action_button('Open Withdrawals', admin_url('/withdrawals.php'), 'secondary') . '</div>';
            break;

        case 'users':
            admin_render_users($pdo);
            break;

        case 'mining-pool':
            echo '<div class="admin-actions">' . admin_action_button('View Public Main Pool', '/pool/main/', 'secondary') . admin_action_button('View Public Nano Pool', '/pool/nano/', 'secondary') . '</div>';
            admin_filter_box('Filter pools');
            admin_render_table(['Pool', 'Collected', 'Accepted', 'Rejected', 'Active Sessions', 'Seen Workers', 'Best Share'], admin_pool_rows(), 'No pool data yet', 'The pool collector has not written readable JSON stats yet.');
            break;

        case 'nodes':
        case 'blockchain-stats':
            try {
                $chain = rpc_call('getblockchaininfo');
                $network = rpc_call('getnetworkinfo');
                $mempool = rpc_call('getmempoolinfo');
                echo '<div class="admin-grid admin-grid-tight">';
                echo admin_stat_card('Chain', (string)($chain['chain'] ?? 'not_available'), 'info');
                echo admin_stat_card('Blocks', admin_fmt_int((int)($chain['blocks'] ?? 0)), 'ok');
                echo admin_stat_card('Headers', admin_fmt_int((int)($chain['headers'] ?? 0)), 'ok');
                echo admin_stat_card('Peers', admin_fmt_int((int)($network['connections'] ?? 0)), 'info');
                echo admin_stat_card('Mempool TX', admin_fmt_int((int)($mempool['size'] ?? 0)), 'warn');
                echo '</div>';
                admin_render_table(['Field', 'Value'], [
                    ['Difficulty', h((string)($chain['difficulty'] ?? 'not_available'))],
                    ['Initial Block Download', !empty($chain['initialblockdownload']) ? '<span class="warn">Yes</span>' : '<span class="ok">No</span>'],
                    ['Verification Progress', h((string)($chain['verificationprogress'] ?? 'not_available'))],
                    ['Subversion', h((string)($network['subversion'] ?? 'not_available'))],
                ]);
            } catch (Throwable $e) {
                echo admin_empty_state('Node data unavailable', 'RPC did not return node data: ' . $e->getMessage());
            }
            break;

        case 'explorer-stats':
            $status = hobc_status_payload('not_available', 'Explorer storage is not directly configured for this admin page yet.');
            try {
                $latest = hobc_latest_block_summary();
                echo '<div class="admin-grid admin-grid-tight">';
                echo admin_stat_card('Latest Height', h((string)($latest['height'] ?? 'not_available')), 'info');
                echo admin_stat_card('Latest TX Count', h((string)($latest['tx_count'] ?? 'not_available')), 'ok');
                echo admin_stat_card('Status', h((string)($status['status'] ?? 'not_available')), 'warn');
                echo '</div>';
                admin_render_table(['Field', 'Value'], [
                    ['Latest Hash', admin_hash_cell((string)($latest['hash'] ?? 'not_available'), true)],
                    ['Latest Time', h((string)($latest['time'] ?? 'not_available'))],
                ]);
            } catch (Throwable $e) {
                echo admin_empty_state('No explorer data yet', 'Explorer helpers did not return data: ' . $e->getMessage());
            }
            break;

        case 'burn-events':
            $burn = hobc_burn_status();
            $transactions = is_array($burn['burn_transactions'] ?? null) ? $burn['burn_transactions'] : [];
            echo '<div class="admin-grid admin-grid-tight">';
            echo admin_stat_card('Burn Address', (string)($burn['burn_address'] ?? HOBC_BURN_ADDRESS), 'info');
            echo admin_stat_card('Burned Total', (string)($burn['total_burned'] ?? '0.00000000'), 'warn');
            echo admin_stat_card('Events', (string)count($transactions), 'ok');
            echo '</div>';
            $rows = array_map(static fn(array $tx): array => [
                admin_hash_cell((string)($tx['txid'] ?? 'not_available'), true),
                h((string)($tx['amount'] ?? '0.00000000')),
                admin_explorer_link((string)($tx['height'] ?? ''), (string)($tx['height'] ?? 'not_available')),
                h((string)($tx['time'] ?? 'not_available')),
            ], $transactions);
            admin_render_table(['TXID', 'Amount', 'Height', 'Time'], $rows, 'No burn events yet', 'No burn transactions were found by the burn tracker.');
            break;

        case 'announcements':
            $settings = site_status_settings();
            echo '<div class="admin-grid admin-grid-tight">';
            echo admin_stat_card('Site Mode', str_replace('_', ' ', (string)$settings['site_mode']), $settings['site_mode'] === 'full_launch' ? 'ok' : 'warn');
            echo admin_stat_card('Bypass IP', (string)($settings['bypass_ip'] ?: 'Not set'), 'info');
            echo '</div>';
            admin_render_table(['Message Type', 'Title', 'Message'], [
                ['Pre-launch', h((string)$settings['pre_launch_title']), nl2br(h((string)$settings['pre_launch_message']))],
                ['Maintenance', h((string)$settings['maintenance_title']), nl2br(h((string)$settings['maintenance_message']))],
            ]);
            echo '<div class="admin-actions">' . admin_action_button('Edit Site Messages', admin_url('/site-config.php')) . '</div>';
            break;

        case 'security-center':
            echo '<div class="admin-grid admin-grid-tight">';
            echo admin_stat_card('Security Events', (string)admin_count($pdo, 'security_event_log'), 'warn');
            echo admin_stat_card('Admin Security Events', (string)admin_count($pdo, 'admin_security_events'), 'warn');
            echo admin_stat_card('Rate Limit Events', (string)admin_count($pdo, 'rate_limit_events'), 'info');
            echo admin_stat_card('Admin Audit Events', (string)admin_count($pdo, 'admin_audit_log'), 'ok');
            echo admin_stat_card('SMS Challenges', (string)admin_count($pdo, 'sms_challenges'), 'info');
            echo '</div>';
            if (admin_table_exists($pdo, 'security_event_log')) {
                $events = $pdo->query("SELECT event_type, severity, ip_address, created_at FROM security_event_log ORDER BY id DESC LIMIT 50")->fetchAll();
                admin_filter_box('Filter security events');
                admin_render_table(['Event', 'Severity', 'IP', 'When'], array_map(static fn(array $row): array => [
                    h((string)$row['event_type']),
                    h((string)$row['severity']),
                    h((string)($row['ip_address'] ?? '')),
                    admin_h_datetime($row['created_at'] ?? null),
                ], $events), 'No security events yet', 'No security events have been recorded.');
            }
            break;

        case 'admin-users-roles':
            $roleColumn = admin_column_exists($pdo, 'admin_users', 'role') ? ', role' : '';
            $rows = $pdo->query("SELECT id, username, email{$roleColumn}, sms_2fa_enabled, totp_enabled, is_active, created_at FROM admin_users ORDER BY id ASC")->fetchAll();
            admin_render_table(['ID', 'Username', 'Email', 'Role', 'SMS 2FA', 'TOTP', 'Status', 'Created'], array_map(static function (array $row): array {
                return [
                    h((string)$row['id']),
                    h((string)$row['username']),
                    h((string)$row['email']),
                    h((string)($row['role'] ?? 'admin')),
                    ((int)($row['sms_2fa_enabled'] ?? 0) === 1) ? '<span class="ok">On</span>' : '<span class="warn">Off</span>',
                    ((int)($row['totp_enabled'] ?? 0) === 1) ? '<span class="ok">On</span>' : '<span class="warn">Off</span>',
                    ((int)$row['is_active'] === 1) ? '<span class="ok">Active</span>' : '<span class="err">Inactive</span>',
                    admin_h_datetime($row['created_at'] ?? null),
                ];
            }, $rows));
            echo '<div class="admin-actions">' . admin_action_button('Set Up Authenticator', admin_url('/authenticator.php'), 'secondary') . '</div>';
            break;

        case 'system-health':
            $migrationStatus = admin_migration_status($pdo);
            $scan = admin_table_exists($pdo, 'chain_scan_state') ? ($pdo->query("SELECT * FROM chain_scan_state WHERE id = 1")->fetch() ?: []) : [];
            $smtp = admin_table_exists($pdo, 'smtp_settings') ? ($pdo->query("SELECT * FROM smtp_settings WHERE id = 1")->fetch() ?: []) : [];
            if (!$migrationStatus['ok']) {
                admin_render_alert('warning', 'Admin database migration is incomplete. Missing: ' . implode(', ', $migrationStatus['missing']) . '. Run php /home/hobbyhashcoin/hobbyhash-clean/wallet/run_migrations.php.');
            }
            echo '<div class="admin-grid admin-grid-tight">';
            echo admin_stat_card('Admin DB Objects', (string)$migrationStatus['present_count'] . ' / ' . (string)$migrationStatus['required_count'], $migrationStatus['ok'] ? 'ok' : 'warn');
            echo admin_stat_card('Scanner', (string)($scan['scanner_status'] ?? 'not_available'), (($scan['scanner_status'] ?? '') === 'ok') ? 'ok' : 'warn');
            echo admin_stat_card('RPC', (string)($scan['rpc_status'] ?? (rpc_is_online() ? 'ok' : 'offline')), rpc_is_online() ? 'ok' : 'warn');
            echo admin_stat_card('SMTP', ((int)($smtp['is_enabled'] ?? 0) === 1) ? 'Enabled' : 'Disabled', ((int)($smtp['is_enabled'] ?? 0) === 1) ? 'ok' : 'warn');
            echo admin_stat_card('Wallet Snapshots', (string)admin_count($pdo, 'wallet_hot_balance_snapshots'), 'info');
            echo admin_stat_card('Reconciliations', (string)admin_count($pdo, 'reconciliation_reports'), 'info');
            echo '</div>';
            admin_render_table(['Health Item', 'Value'], [
                ['Last Scanned Height', h((string)($scan['last_scanned_height'] ?? '0'))],
                ['Scanner Error', h((string)($scan['scanner_last_error'] ?? ''))],
                ['RPC Error', h((string)($scan['rpc_last_error'] ?? ''))],
                ['SMTP Host', h((string)($smtp['host'] ?? 'not configured'))],
                ['Missing Admin DB Objects', $migrationStatus['missing'] === [] ? '<span class="ok">None</span>' : h(implode(', ', $migrationStatus['missing']))],
            ]);
            echo '<div class="admin-actions">' . admin_action_button('Wallet Ops', admin_url('/wallet.php')) . admin_action_button('SMTP Settings', admin_url('/smtp.php'), 'secondary') . '</div>';
            break;

        default:
            admin_render_analytics_section($pdo, $section, (string)$config['title'], (string)$config['description']);
            break;
    }
}

$configs = admin_section_configs();
$section = (string)($_GET['section'] ?? 'site-analytics');
if (!isset($configs[$section])) {
    http_response_code(404);
    $configs[$section] = ['title' => 'Admin Section Not Found', 'description' => 'The requested admin section is not registered.'];
}

render_admin_header((string)$configs[$section]['title']);
admin_render_section($pdo, $section, $configs[$section]);
render_admin_footer();
