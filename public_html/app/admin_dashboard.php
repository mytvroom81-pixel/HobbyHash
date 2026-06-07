<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ledger.php';
require_once __DIR__ . '/rpc.php';
require_once __DIR__ . '/site_status.php';
require_once __DIR__ . '/admin_ops.php';
require_once __DIR__ . '/admin_permissions.php';
require_once __DIR__ . '/admin_datetime.php';

function admin_dashboard_stat(string $id, string $label, string $value, string $tone = '', string $subtext = ''): array
{
    return [
        'id' => $id,
        'label' => $label,
        'value' => $value,
        'tone' => $tone,
        'subtext' => $subtext,
    ];
}

function admin_dashboard_format_hashrate(mixed $value): string
{
    if (!is_numeric($value) || (float)$value <= 0) {
        return 'Not available';
    }
    $rate = (float)$value;
    foreach (['H/s', 'KH/s', 'MH/s', 'GH/s', 'TH/s', 'PH/s'] as $unit) {
        if ($rate < 1000 || $unit === 'PH/s') {
            return number_format($rate, $rate >= 100 ? 1 : 2) . ' ' . $unit;
        }
        $rate /= 1000;
    }

    return 'Not available';
}

function admin_dashboard_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
    $cache[$table] = (bool)$stmt->fetchColumn();

    return $cache[$table];
}

function admin_dashboard_count(PDO $pdo, string $table, string $where = '1=1'): int
{
    if (!admin_dashboard_table_exists($pdo, $table)) {
        return 0;
    }

    return (int)$pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '` WHERE ' . $where)->fetchColumn();
}

function admin_dashboard_active_visitors(PDO $pdo): int
{
    if (!admin_dashboard_table_exists($pdo, 'site_visitors')) {
        return 0;
    }

    return (int)$pdo->query(
        'SELECT COUNT(*) FROM site_visitors WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND is_bot = 0'
    )->fetchColumn();
}

function admin_dashboard_pageviews_today(PDO $pdo): int
{
    if (!admin_dashboard_table_exists($pdo, 'site_pageviews')) {
        return 0;
    }
    $today = admin_local_date('Y-m-d');

    return (int)$pdo->query(
        "SELECT COUNT(*) FROM site_pageviews WHERE created_at >= '{$today} 00:00:00' AND created_at <= '{$today} 23:59:59'"
    )->fetchColumn();
}

function admin_dashboard_pool_cards(string $pool, array $stats): array
{
    $prefix = $pool === 'nano' ? 'nano' : 'main';
    $labelPrefix = $pool === 'nano' ? 'Nano' : 'Main';
    $status = strtolower((string)($stats['status'] ?? 'offline'));
    $statusTone = in_array($status, ['online', 'ok', 'active'], true) ? 'ok' : ($status === 'syncing' ? 'warn' : 'error');

    return [
        admin_dashboard_stat($prefix . '_pool_status', $labelPrefix . ' Pool', ucfirst($status !== '' ? $status : 'offline'), $statusTone),
        admin_dashboard_stat($prefix . '_pool_workers', $labelPrefix . ' Workers', admin_ops_fmt_int($stats['workers'] ?? 0), ((int)($stats['workers'] ?? 0) > 0) ? 'ok' : 'info'),
        admin_dashboard_stat($prefix . '_pool_hashrate', $labelPrefix . ' Hashrate', admin_dashboard_format_hashrate($stats['hashrate'] ?? 0), ((float)($stats['hashrate'] ?? 0) > 0) ? 'ok' : 'info'),
        admin_dashboard_stat($prefix . '_pool_accepted', $labelPrefix . ' Accepted', admin_ops_fmt_int($stats['accepted_shares'] ?? 0), 'ok'),
    ];
}

function admin_dashboard_snapshot(array $admin): array
{
    $pdo = wallet_db();
    $alerts = [];
    $sections = [];
    $statsMap = [];

    $appendSection = static function (string $id, string $title, string $description, array $stats, string $href = '', string $hrefLabel = '') use (&$sections, &$statsMap): void {
        if ($stats === []) {
            return;
        }
        foreach ($stats as $stat) {
            if (!is_array($stat) || ($stat['id'] ?? '') === '') {
                continue;
            }
            $statsMap[(string)$stat['id']] = [
                'value' => (string)($stat['value'] ?? ''),
                'tone' => (string)($stat['tone'] ?? ''),
                'subtext' => (string)($stat['subtext'] ?? ''),
            ];
        }
        $sections[] = [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'href' => $href,
            'href_label' => $hrefLabel,
            'stats' => $stats,
        ];
    };

    $siteSettings = site_status_settings();
    $settings = $pdo->query('SELECT * FROM wallet_settings WHERE id = 1')->fetch() ?: [];
    $scan = $pdo->query('SELECT * FROM chain_scan_state WHERE id = 1')->fetch() ?: [];
    $liabilities = ledger_total_liabilities();
    $pendingWithdrawals = admin_dashboard_count($pdo, 'withdrawals', "status IN ('pending','awaiting_approval','approved')");
    $manualWithdrawals = admin_dashboard_count($pdo, 'withdrawals', "status = 'manual_review'");
    $activeHolds = admin_dashboard_count($pdo, 'wallet_user_holds', "status = 'active'");
    $openSupportTickets = admin_dashboard_count($pdo, 'support_tickets', "status IN ('open', 'waiting_admin', 'waiting_user')");
    $users = admin_dashboard_count($pdo, 'users');

    $hot = ['trusted' => 0.0, 'untrusted_pending' => 0.0, 'immature' => 0.0];
    $rpcErr = '';
    try {
        $bal = rpc_call('getbalances');
        $hot = [
            'trusted' => (float)($bal['mine']['trusted'] ?? 0),
            'untrusted_pending' => (float)($bal['mine']['untrusted_pending'] ?? 0),
            'immature' => (float)($bal['mine']['immature'] ?? 0),
        ];
    } catch (Throwable $e) {
        $rpcErr = $e->getMessage();
    }
    $delta = $hot['trusted'] - (float)$liabilities;

    if ($rpcErr !== '') {
        $alerts[] = ['type' => 'error', 'message' => 'Wallet RPC issue: ' . $rpcErr];
    }
    if ($delta < 0) {
        $alerts[] = ['type' => 'warning', 'message' => 'Hot wallet trusted balance is below recorded liabilities.'];
    }
    if ($pendingWithdrawals > 0) {
        $alerts[] = ['type' => 'warning', 'message' => number_format($pendingWithdrawals) . ' withdrawal(s) waiting in the queue.'];
    }
    if ($openSupportTickets > 0) {
        $alerts[] = ['type' => 'warning', 'message' => number_format($openSupportTickets) . ' support ticket(s) need attention.'];
    }

    $siteMode = str_replace('_', ' ', (string)($siteSettings['site_mode'] ?? 'unknown'));
    $scannerStatus = (string)($scan['scanner_status'] ?? 'error');
    $rpcStatus = (string)($scan['rpc_status'] ?? 'unknown');
    $depositsPaused = ((int)($settings['deposits_paused'] ?? 0) === 1);
    $withdrawalsPaused = ((int)($settings['withdrawals_paused'] ?? 0) === 1);

    $appendSection(
        'operations',
        'Operations',
        'Wallet solvency, queues, and site controls that need immediate attention.',
        [
            admin_dashboard_stat('site_mode', 'Site Mode', $siteMode, ($siteSettings['site_mode'] ?? '') === 'full_launch' ? 'ok' : 'warn'),
            admin_dashboard_stat('hot_minus_liabilities', 'Hot Minus Liabilities', number_format($delta, 8, '.', ''), $delta < 0 ? 'error' : 'ok', 'Trusted hot wallet minus user liabilities'),
            admin_dashboard_stat('pending_withdrawals', 'Pending Withdrawals', (string)$pendingWithdrawals, $pendingWithdrawals > 0 ? 'warn' : 'ok'),
            admin_dashboard_stat('manual_review', 'Manual Review', (string)$manualWithdrawals, $manualWithdrawals > 0 ? 'warn' : 'ok'),
            admin_dashboard_stat('open_tickets', 'Open Tickets', (string)$openSupportTickets, $openSupportTickets > 0 ? 'warn' : 'ok'),
            admin_dashboard_stat('scanner_status', 'Chain Scanner', $scannerStatus, $scannerStatus === 'ok' ? 'ok' : 'warn', 'Last height ' . admin_ops_fmt_int($scan['last_scanned_height'] ?? 0)),
            admin_dashboard_stat('wallet_rpc', 'Wallet RPC', $rpcStatus !== '' ? $rpcStatus : 'unknown', $rpcStatus === 'ok' ? 'ok' : 'warn'),
            admin_dashboard_stat('wallet_controls', 'Deposits / Withdrawals', ($depositsPaused ? 'Deposits paused' : 'Deposits live') . ' · ' . ($withdrawalsPaused ? 'Withdrawals paused' : 'Withdrawals live'), ($depositsPaused || $withdrawalsPaused) ? 'warn' : 'ok'),
        ],
        admin_url('/wallet.php'),
        'Wallet Ops'
    );

    $node = admin_ops_node_status();
    $chain = is_array($node['chain'] ?? null) ? $node['chain'] : [];
    $network = is_array($node['network'] ?? null) ? $node['network'] : [];
    $mempool = is_array($node['mempool'] ?? null) ? $node['mempool'] : [];
    $chainOnline = (bool)($node['online'] ?? false);
    $chainHeight = (int)($chain['blocks'] ?? 0);
    $headers = (int)($chain['headers'] ?? 0);
    $syncTone = $chainOnline ? (($headers > 0 && $chainHeight < $headers) ? 'warn' : 'ok') : 'error';

    if (($node['error'] ?? '') !== '') {
        $alerts[] = ['type' => 'error', 'message' => 'Node RPC issue: ' . (string)$node['error']];
    }

    $appendSection(
        'chain',
        'Chain & Node',
        'Live node RPC data for block height, mempool, peers, and network hashrate.',
        [
            admin_dashboard_stat('node_status', 'Node Status', $chainOnline ? 'Online' : 'Offline', $chainOnline ? 'ok' : 'error'),
            admin_dashboard_stat('chain_height', 'Chain Height', admin_ops_fmt_int($chainHeight), $syncTone),
            admin_dashboard_stat('chain_headers', 'Headers', admin_ops_fmt_int($headers), $syncTone),
            admin_dashboard_stat('network_peers', 'Peers', admin_ops_fmt_int($network['connections'] ?? 0), ((int)($network['connections'] ?? 0) > 0) ? 'ok' : 'warn'),
            admin_dashboard_stat('mempool_tx', 'Mempool TX', admin_ops_fmt_int($mempool['size'] ?? 0), 'info'),
            admin_dashboard_stat('network_hashrate', 'Network Hashrate', admin_dashboard_format_hashrate($chain['networkhashps'] ?? 0), 'info'),
            admin_dashboard_stat('chain_difficulty', 'Difficulty', admin_ops_fmt_number($chain['difficulty'] ?? 0, 2), 'info'),
        ],
        admin_url('/blockchain.php'),
        'Blockchain Stats'
    );

    $mainPool = admin_ops_pool_stats('main', true);
    $nanoPool = admin_ops_pool_stats('nano', true);
    $poolStats = array_merge(
        admin_dashboard_pool_cards('main', $mainPool),
        admin_dashboard_pool_cards('nano', $nanoPool)
    );
    $appendSection(
        'pools',
        'Mining Pools',
        'Main and nano pool collector status, workers, hashrate, and accepted shares.',
        $poolStats,
        admin_url('/mining-pool.php'),
        'Mining Pool'
    );

    $supply = hobc_stats_supply_summary();
    $explorerStatus = 'offline';
    $explorerHeight = 0;
    $chainRpc = hobc_rpc('getblockchaininfo');
    if ($chainRpc['ok'] && is_array($chainRpc['result'])) {
        $explorerHeight = (int)($chainRpc['result']['blocks'] ?? 0);
        $explorerStatus = !empty($chainRpc['result']['initialblockdownload']) ? 'syncing' : 'online';
    }
    $explorerTone = in_array($explorerStatus, ['online', 'syncing'], true) ? 'ok' : 'warn';

    $appendSection(
        'treasury',
        'Supply & Explorer',
        'Circulating supply, reserve, burn totals, and explorer sync state.',
        [
            admin_dashboard_stat('circulating_supply', 'Circulating Supply', admin_ops_fmt_number($supply['circulating_supply'] ?? 0, 8), 'info'),
            admin_dashboard_stat('launch_reserve', 'Launch Reserve', admin_ops_fmt_number($supply['current_balances'] ?? 0, 8), 'ok'),
            admin_dashboard_stat('total_burned', 'Total Burned', admin_ops_fmt_number($supply['total_burned'] ?? 0, 8), 'warn'),
            admin_dashboard_stat('explorer_status', 'Explorer', ucfirst($explorerStatus), $explorerTone, 'Height ' . admin_ops_fmt_int($explorerHeight)),
        ],
        admin_url('/explorer.php'),
        'Explorer Stats'
    );

    if (admin_can($admin, 'wallet_controls') || admin_can($admin, 'users')) {
        $appendSection(
            'wallet_users',
            'Wallet & Users',
            'Custodial wallet balances and registered user counts.',
            [
                admin_dashboard_stat('wallet_liabilities', 'Liabilities', number_format((float)$liabilities, 8, '.', ''), 'info'),
                admin_dashboard_stat('hot_trusted', 'Hot Trusted', number_format($hot['trusted'], 8, '.', ''), $delta < 0 ? 'error' : 'ok'),
                admin_dashboard_stat('hot_untrusted', 'Untrusted Pending', number_format($hot['untrusted_pending'], 8, '.', ''), 'warn'),
                admin_dashboard_stat('users_total', 'Users', (string)$users, 'info'),
                admin_dashboard_stat('active_holds', 'Active Holds', (string)$activeHolds, $activeHolds > 0 ? 'warn' : 'ok'),
            ],
            admin_url('/wallet.php'),
            'Wallet Controls'
        );
    }

    if (admin_can($admin, 'analytics')) {
        $activeVisitors = admin_dashboard_active_visitors($pdo);
        $pageviewsToday = admin_dashboard_pageviews_today($pdo);
        $appendSection(
            'traffic',
            'Site Traffic',
            'Live visitor activity from the analytics heartbeat pipeline.',
            [
                admin_dashboard_stat('active_visitors', 'Active Now (5m)', (string)$activeVisitors, $activeVisitors > 0 ? 'ok' : 'info'),
                admin_dashboard_stat('pageviews_today', 'Page Views Today', (string)$pageviewsToday, 'info'),
                admin_dashboard_stat('bot_views_today', 'Bot Views Today', (string)admin_dashboard_count($pdo, 'site_pageviews', "is_bot = 1 AND created_at >= '" . admin_local_date('Y-m-d') . " 00:00:00'"), 'warn'),
            ],
            admin_url('/analytics.php'),
            'Site Analytics'
        );
    }

    if (admin_can($admin, 'security_center')) {
        $failedLogins = admin_dashboard_count(
            $pdo,
            'admin_security_events',
            "event_type = 'admin_login_failed' AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)"
        );
        $activeSessions = admin_dashboard_count(
            $pdo,
            'admin_sessions',
            'revoked_at IS NULL AND expires_at >= UTC_TIMESTAMP()'
        );
        $appendSection(
            'security',
            'Security',
            'Admin login activity and active admin sessions.',
            [
                admin_dashboard_stat('failed_logins_24h', 'Failed Logins (24h)', (string)$failedLogins, $failedLogins > 0 ? 'warn' : 'ok'),
                admin_dashboard_stat('admin_sessions', 'Active Admin Sessions', (string)$activeSessions, 'info'),
            ],
            admin_url('/security.php'),
            'Security Center'
        );
    }

    if (admin_can($admin, 'social_bot')) {
        try {
            require_once __DIR__ . '/social_bot_admin.php';
            $botStats = social_bot_stats();
            $appendSection(
                'social_bot',
                'Social Bot',
                'Official posting queue and node service health.',
                [
                    admin_dashboard_stat('bot_pending_posts', 'Pending Posts', (string)($botStats['pending_posts'] ?? 0), ((int)($botStats['pending_posts'] ?? 0) > 0) ? 'warn' : 'ok'),
                    admin_dashboard_stat('bot_pending_replies', 'Pending Replies', (string)($botStats['pending_replies'] ?? 0), ((int)($botStats['pending_replies'] ?? 0) > 0) ? 'warn' : 'ok'),
                    admin_dashboard_stat('bot_published_today', 'Published Today', (string)($botStats['published_today'] ?? 0), 'info'),
                    admin_dashboard_stat('bot_service', 'Node Service', !empty($botStats['service_ok']) ? 'Online' : 'Offline', !empty($botStats['service_ok']) ? 'ok' : 'error'),
                ],
                admin_url('/social-bot.php'),
                'Social Bot'
            );
        } catch (Throwable $e) {
            $alerts[] = ['type' => 'warning', 'message' => 'Social bot stats unavailable.'];
        }
    }

    $recentEvents = [];
    if (admin_dashboard_table_exists($pdo, 'admin_audit_log')) {
        $recentEvents = $pdo->query('SELECT action, created_at FROM admin_audit_log ORDER BY id DESC LIMIT 8')->fetchAll() ?: [];
    }

    $recentBlocks = admin_ops_latest_blocks(5);

    return [
        'ok' => true,
        'updated_at' => admin_h_utc_datetime(gmdate('Y-m-d H:i:s')),
        'updated_at_iso' => gmdate('c'),
        'alerts' => $alerts,
        'sections' => $sections,
        'stats' => $statsMap,
        'recent_events' => array_map(static fn(array $row): array => [
            'action' => (string)($row['action'] ?? ''),
            'created_at' => admin_h_utc_datetime($row['created_at'] ?? null),
        ], $recentEvents),
        'recent_blocks' => array_map(static fn(array $row): array => [
            'height' => admin_ops_fmt_int($row['height'] ?? 0),
            'hash' => admin_ops_short($row['hash'] ?? ''),
            'tx_count' => admin_ops_fmt_int($row['tx_count'] ?? 0),
            'time' => admin_h_utc_datetime(isset($row['time']) && is_numeric($row['time']) ? gmdate('Y-m-d H:i:s', (int)$row['time']) : null),
        ], $recentBlocks),
    ];
}
