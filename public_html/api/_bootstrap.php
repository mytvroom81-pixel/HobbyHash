<?php
declare(strict_types=1);

const HOBC_TOTAL_SUPPLY = '84000000.00000000';
const HOBC_LAUNCH_RESERVE = '8400000.00000000';
const HOBC_NORMAL_MINING_TARGET = '75600000.00000000';
const HOBC_BLOCK_SUBSIDY = 45.0;
const HOBC_BURN_ADDRESS = 'hobc1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqf9lpf8';
const HOBC_BURN_SCRIPT_PUBKEY = '00140000000000000000000000000000000000000000';
const HOBC_RESERVE_WALLET = 'launchminer';
const HOBC_LAUNCH_RESERVE_ADDRESS = 'hobc1qp6z9335dmnnumrukvkwwrks0s0ul68he2kj4ga';
const HOBC_API_CACHE_DIR = '/home/hobbyhashcoin/hobbyhash-data/mainnet/api-cache';

function hobc_json(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function hobc_now(): string
{
    return gmdate('c');
}

function hobc_status_payload(string $status, string $message = '', array $extra = []): array
{
    return array_merge([
        'ok' => in_array($status, ['online', 'syncing'], true),
        'status' => $status,
        'updated_at' => hobc_now(),
    ], $message !== '' ? ['message' => $message] : [], $extra);
}

function hobc_config(): ?array
{
    $candidates = array_filter([
        getenv('HOBC_WALLET_CONFIG') ?: null,
        '/home/hobbyhashcoin/hobbyhash-wallet-private/config.php',
        '/home/hobbyhashcoin/hobbyhash-clean/wallet/config.php',
        '/home/hobbyhashcoin/public_html/config.php',
    ]);

    foreach ($candidates as $path) {
        if (is_readable($path)) {
            $cfg = require $path;
            if (is_array($cfg)) {
                return $cfg;
            }
        }
    }
    return null;
}

function hobc_rpc(string $method, array $params = [], ?string $wallet = null): array
{
    $cfg = hobc_config();
    if (!$cfg || empty($cfg['rpc']) || !is_array($cfg['rpc'])) {
        return ['ok' => false, 'status' => 'offline'];
    }

    $rpc = $cfg['rpc'];
    $url = rtrim((string)($rpc['url'] ?? ''), '/');
    $host = parse_url($url, PHP_URL_HOST);
    if (!in_array($host, ['127.0.0.1', 'localhost'], true)) {
        return ['ok' => false, 'status' => 'offline'];
    }

    $walletName = $wallet ?? null;
    if ($walletName !== null && $walletName !== '') {
        $url .= '/wallet/' . rawurlencode($walletName);
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 'offline'];
    }

    $payload = json_encode([
        'jsonrpc' => '1.0',
        'id' => 'hobc-public-api',
        'method' => $method,
        'params' => $params,
    ], JSON_UNESCAPED_SLASHES);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => (string)($rpc['username'] ?? '') . ':' . (string)($rpc['password'] ?? ''),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => min(5, max(1, (int)($rpc['timeout_seconds'] ?? 4))),
    ]);

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $body === false || $http < 200 || $http >= 300) {
        return ['ok' => false, 'status' => 'offline'];
    }

    $json = json_decode((string)$body, true);
    if (!is_array($json) || !empty($json['error'])) {
        return ['ok' => false, 'status' => 'not_available'];
    }

    return ['ok' => true, 'status' => 'online', 'result' => $json['result'] ?? null];
}

function hobc_db(): ?PDO
{
    $cfg = hobc_config();
    if (!$cfg || empty($cfg['db']) || !is_array($cfg['db'])) {
        return null;
    }
    $db = $cfg['db'];
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], (int)$db['port'], $db['database'], $db['charset'] ?? 'utf8mb4');
        return new PDO($dsn, (string)$db['username'], (string)$db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        return null;
    }
}

function hobc_read_json_file(string $path): ?array
{
    if (!is_readable($path)) {
        return null;
    }

    $body = file_get_contents($path);
    if ($body === false) {
        return null;
    }

    $json = json_decode($body, true);
    return is_array($json) ? $json : null;
}

function hobc_api_cache_path(string $key): string
{
    $safe = preg_replace('/[^a-z0-9_-]+/i', '_', $key) ?: 'cache';
    return rtrim(HOBC_API_CACHE_DIR, '/') . '/' . $safe . '.json';
}

function hobc_api_cache_read(string $key, int $ttlSeconds): ?array
{
    $path = hobc_api_cache_path($key);
    if (!is_readable($path)) {
        return null;
    }
    $mtime = (int)@filemtime($path);
    if ($mtime <= 0 || (time() - $mtime) > $ttlSeconds) {
        return null;
    }
    $data = hobc_read_json_file($path);
    return is_array($data) ? $data : null;
}

function hobc_api_cache_write(string $key, array $payload): void
{
    $path = hobc_api_cache_path($key);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function hobc_api_cache_delete(string $key): void
{
    $path = hobc_api_cache_path($key);
    if (is_file($path)) {
        @unlink($path);
    }
}

function hobc_admin_setting_value(string $key, mixed $default = null): mixed
{
    $pdo = hobc_db();
    if (!$pdo instanceof PDO) {
        return $default;
    }
    try {
        $exists = $pdo->query("SHOW TABLES LIKE 'admin_settings'")->fetchColumn();
        if (!$exists) {
            return $default;
        }
        $stmt = $pdo->prepare("SELECT setting_value, setting_type FROM admin_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) {
            return $default;
        }
        return match ((string)$row['setting_type']) {
            'boolean' => in_array((string)$row['setting_value'], ['1', 'true', 'yes', 'on'], true),
            'integer' => (int)$row['setting_value'],
            'decimal' => (float)$row['setting_value'],
            'json' => json_decode((string)$row['setting_value'], true) ?? $default,
            default => (string)$row['setting_value'],
        };
    } catch (Throwable $e) {
        return $default;
    }
}

function hobc_reserve_wallet_name(): string
{
    $cfg = hobc_config();
    if (is_array($cfg) && !empty($cfg['reserve']['wallet']) && is_string($cfg['reserve']['wallet'])) {
        return $cfg['reserve']['wallet'];
    }
    return HOBC_RESERVE_WALLET;
}

function hobc_reserve_categories(): array
{
    $fallback = [
        'liquidity_and_listings' => [
            'label' => 'Liquidity & Listings',
            'percentage' => 25,
            'covers' => [
                'Exchange listing fees',
                'Market-making liquidity',
                'DEX liquidity pools',
                'Trading integrations',
                'Explorer hosting tied to exchange requirements',
            ],
        ],
        'miner_growth' => [
            'label' => 'Miner Growth',
            'percentage' => 35,
            'covers' => [
                'Mining competitions',
                'Home miner incentives',
                'Node incentives',
                'Pool development',
                'Bitaxe/NerdMiner/community miner promotions',
                'Giveaways to attract small miners',
            ],
        ],
        'development_and_infrastructure' => [
            'label' => 'Development & Infrastructure',
            'percentage' => 30,
            'covers' => [
                'Core wallet development',
                'Android wallet',
                'Windows wallet',
                'Website development',
                'Explorer servers',
                'Seed nodes',
                'Security audits',
                'Hosting and backups',
            ],
        ],
        'community_and_support' => [
            'label' => 'Community & Support',
            'percentage' => 10,
            'covers' => [
                'Discord/community moderation',
                'Documentation',
                'Tutorials',
                'Support resources',
                'Promotional materials',
                'Community events',
            ],
        ],
    ];

    $pdo = hobc_db();
    if (!$pdo instanceof PDO) {
        return $fallback;
    }

    try {
        $exists = $pdo->query("SHOW TABLES LIKE 'treasury_reserve_categories'")->fetchColumn();
        if (!$exists) {
            return $fallback;
        }
        $rows = $pdo->query("SELECT id, slug, name, percentage, notes FROM treasury_reserve_categories WHERE is_public = 1 AND status IN ('active','pending_launch','completed') ORDER BY percentage DESC, name ASC")->fetchAll();
        if ($rows === []) {
            return $fallback;
        }
        $categories = [];
        foreach ($rows as $row) {
            $slug = trim((string)($row['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            if (is_file(__DIR__ . '/i18n_db_content.php')) {
                require_once __DIR__ . '/i18n_db_content.php';
                $row = hobc_i18n_db_row('treasury_reserve_categories', $row);
            }
            $notes = trim((string)($row['notes'] ?? ''));
            $categories[$slug] = [
                'label' => (string)$row['name'],
                'percentage' => (float)$row['percentage'],
                'covers' => $notes !== '' ? preg_split('/\r\n|\r|\n/', $notes) : ['Admin-managed reserve category'],
            ];
        }
        return $categories !== [] ? $categories : $fallback;
    } catch (Throwable $e) {
        return $fallback;
    }
}

function hobc_pool_last_block(string $path): array|string
{
    $state = hobc_read_json_file($path);
    if ($state === null) {
        return 'not_available';
    }

    $rows = [];
    foreach (['paid', 'candidates'] as $key) {
        if (isset($state[$key]) && is_array($state[$key])) {
            $rows = array_merge($rows, array_filter($state[$key], 'is_array'));
        }
    }

    if ($rows === []) {
        return 'not_available';
    }

    usort($rows, static fn (array $a, array $b): int => ((int)($b['height'] ?? -1)) <=> ((int)($a['height'] ?? -1)));
    $row = $rows[0];

    return [
        'height' => isset($row['height']) ? (int)$row['height'] : 'not_available',
        'status' => (string)($row['status'] ?? 'not_available'),
        'confirmations' => isset($row['confirmations']) ? (int)$row['confirmations'] : 'not_available',
        'blockhash' => (string)($row['blockhash'] ?? 'not_available'),
        'seen_at' => isset($row['seen_at']) ? gmdate('c', (int)$row['seen_at']) : 'not_available',
        'paid_at' => isset($row['paid_at']) ? gmdate('c', (int)$row['paid_at']) : 'not_available',
    ];
}

function hobc_pool_blocks_found(string $path, string $logDir = '', int $limit = 20): array
{
    $state = hobc_read_json_file($path);
    $byHeight = [];

    if ($state !== null) {
        foreach (['candidates', 'paid'] as $section) {
            if (!isset($state[$section]) || !is_array($state[$section])) {
                continue;
            }
            foreach ($state[$section] as $row) {
                if (!is_array($row) || !isset($row['height']) || !is_numeric($row['height'])) {
                    continue;
                }
                $height = (int)$row['height'];
                $existing = $byHeight[$height] ?? [];
                $hash = (string)($row['blockhash'] ?? ($existing['hash'] ?? 'not_available'));
                if ($hash === '') {
                    $hash = 'not_available';
                }
                $seenAt = isset($row['seen_at']) && is_numeric($row['seen_at'])
                    ? (int)$row['seen_at']
                    : (isset($existing['seen_ts']) ? (int)$existing['seen_ts'] : 0);
                $paidAt = isset($row['paid_at']) && is_numeric($row['paid_at'])
                    ? (int)$row['paid_at']
                    : (isset($existing['paid_ts']) ? (int)$existing['paid_ts'] : 0);
                $eventTs = $paidAt > 0 ? $paidAt : ($seenAt > 0 ? $seenAt : (int)($existing['event_ts'] ?? 0));
                $payoutStatus = (string)($row['status'] ?? ($section === 'paid' ? 'paid' : ($existing['payout_status'] ?? $section)));

                $byHeight[$height] = [
                    'height' => $height,
                    'hash' => $hash !== 'not_available' ? $hash : (string)($existing['hash'] ?? 'not_available'),
                    'status' => (string)($existing['status'] ?? 'hit'),
                    'payout_status' => $payoutStatus,
                    'time' => (string)($existing['time'] ?? 'not_available'),
                    'event_time' => $eventTs > 0 ? gmdate('c', $eventTs) : (string)($existing['event_time'] ?? 'not_available'),
                    'event_ts' => $eventTs,
                    'block_time' => (string)($existing['block_time'] ?? 'not_available'),
                    'block_time_ts' => (int)($existing['block_time_ts'] ?? 0),
                    'seen_ts' => $seenAt,
                    'paid_ts' => $paidAt,
                    'workername' => hobc_mask_worker_name((string)($row['workername'] ?? ($existing['workername_raw'] ?? 'not_available'))),
                    'workername_raw' => (string)($row['workername'] ?? ($existing['workername_raw'] ?? 'not_available')),
                    'payout_txid' => (string)($row['payout_txid'] ?? ($existing['payout_txid'] ?? 'not_available')),
                    'confirmations' => isset($row['confirmations']) ? (int)$row['confirmations'] : ($existing['confirmations'] ?? 'not_available'),
                ];
            }
        }
    }

    if ($logDir !== '') {
        foreach (hobc_pool_blocks_from_ckpool_log($logDir, $limit) as $hitBlock) {
            $height = isset($hitBlock['height']) && is_numeric($hitBlock['height']) ? (int)$hitBlock['height'] : 0;
            if ($height <= 0) {
                continue;
            }
            $existing = $byHeight[$height] ?? [];
            $byHeight[$height] = array_merge($existing, $hitBlock, [
                'status' => 'hit',
                'payout_status' => (string)($existing['payout_status'] ?? 'not_available'),
                'payout_txid' => (string)($existing['payout_txid'] ?? 'not_available'),
                'confirmations' => $existing['confirmations'] ?? ($hitBlock['confirmations'] ?? 'not_available'),
            ]);
        }

        foreach (hobc_pool_blocks_from_sharelogs($logDir, $limit) as $liveBlock) {
            $height = isset($liveBlock['height']) && is_numeric($liveBlock['height']) ? (int)$liveBlock['height'] : 0;
            if ($height <= 0) {
                continue;
            }
            $existing = $byHeight[$height] ?? [];
            $byHeight[$height] = array_merge($liveBlock, [
                'status' => (string)($existing['status'] ?? $liveBlock['status']),
                'payout_status' => (string)($existing['payout_status'] ?? 'not_available'),
                'event_time' => (string)($existing['event_time'] ?? $liveBlock['event_time']),
                'event_ts' => (int)($existing['event_ts'] ?? $liveBlock['event_ts']),
                'payout_txid' => (string)($existing['payout_txid'] ?? 'not_available'),
                'confirmations' => $liveBlock['confirmations'] ?? ($existing['confirmations'] ?? 'not_available'),
            ]);
        }
    }

    $blocks = array_values($byHeight);
    usort($blocks, static function (array $a, array $b): int {
        $heightCompare = ((int)($b['height'] ?? 0)) <=> ((int)($a['height'] ?? 0));
        return $heightCompare !== 0 ? $heightCompare : (((int)($b['block_time_ts'] ?? 0)) <=> ((int)($a['block_time_ts'] ?? 0)));
    });
    $blocks = array_slice($blocks, 0, $limit);

    foreach ($blocks as &$block) {
        $height = isset($block['height']) && is_numeric($block['height']) ? (int)$block['height'] : 0;
        if ($height > 0) {
            $hash = hobc_rpc('getblockhash', [$height]);
            if (!empty($hash['ok']) && is_string($hash['result'] ?? null) && $hash['result'] !== '') {
                $block['hash'] = $hash['result'];
                $blockData = hobc_rpc('getblock', [$hash['result'], 1]);
                if (!empty($blockData['ok']) && is_array($blockData['result'] ?? null) && isset($blockData['result']['time'])) {
                    $block['block_time'] = gmdate('c', (int)$blockData['result']['time']);
                    $block['block_time_ts'] = (int)$blockData['result']['time'];
                    $block['time'] = $block['block_time'];
                }
            }
        }
        if (($block['time'] ?? 'not_available') === 'not_available' && ($block['block_time'] ?? 'not_available') !== 'not_available') {
            $block['time'] = $block['block_time'];
        }
    }
    unset($block);

    foreach ($blocks as &$block) {
        unset($block['event_ts'], $block['seen_ts'], $block['paid_ts'], $block['block_time_ts'], $block['workername_raw']);
    }
    unset($block);

    return $blocks;
}

function hobc_mask_worker_name(string $workername): string
{
    $parts = explode('.', $workername, 2);
    $address = $parts[0] ?? '';
    $suffix = $parts[1] ?? '';
    if (strlen($address) > 14) {
        $address = substr($address, 0, 8) . '...' . substr($address, -6);
    }
    return $suffix !== '' ? $address . '.' . $suffix : $address;
}

function hobc_hashrate_to_hps(mixed $value): float
{
    if (is_numeric($value)) {
        return (float)$value;
    }
    $text = trim((string)$value);
    if ($text === '' || !preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([KMGTPE]?)(?:H|H\/s)?$/i', $text, $m)) {
        return 0.0;
    }
    $scale = [
        '' => 1.0,
        'K' => 1.0e3,
        'M' => 1.0e6,
        'G' => 1.0e9,
        'T' => 1.0e12,
        'P' => 1.0e15,
        'E' => 1.0e18,
    ];
    return (float)$m[1] * ($scale[strtoupper($m[2])] ?? 1.0);
}

function hobc_format_hashrate_hps(float $hps): string
{
    if ($hps <= 0 || !is_finite($hps)) {
        return '0';
    }
    $units = [
        1.0e18 => 'E',
        1.0e15 => 'P',
        1.0e12 => 'T',
        1.0e9 => 'G',
        1.0e6 => 'M',
        1.0e3 => 'K',
    ];
    foreach ($units as $scale => $suffix) {
        if ($hps >= $scale) {
            return number_format($hps / $scale, 2, '.', '') . $suffix;
        }
    }
    return number_format($hps, 2, '.', '');
}

function hobc_pool_hashrate_candidate_hps(mixed $value): float
{
    if ($value === null || $value === '' || $value === 'not_available') {
        return 0.0;
    }
    if (is_numeric($value)) {
        return (float)$value;
    }
    return hobc_hashrate_to_hps($value);
}

function hobc_pool_resolve_hashrate(array $hashrateLine, array $graphWindows, int $workers = 0): array
{
    $shortPoolAttempts = [
        $hashrateLine['hashrate5m'] ?? null,
        $hashrateLine['hashrate1m'] ?? null,
        $hashrateLine['hashrate15m'] ?? null,
        $hashrateLine['hashrate1hr'] ?? null,
    ];
    $graphAttempts = [
        $graphWindows['5m']['hashrate_estimate'] ?? null,
        $graphWindows['60m']['hashrate_estimate'] ?? null,
        $graphWindows['30m']['hashrate_estimate'] ?? null,
        $graphWindows['12h']['hashrate_estimate'] ?? null,
    ];
    $longPoolAttempts = [
        $hashrateLine['hashrate6hr'] ?? null,
        $hashrateLine['hashrate1d'] ?? null,
        $hashrateLine['hashrate7d'] ?? null,
    ];

    $pick = static function (array $attempts, bool $ignoreTinyPoolValues = false): ?array {
        foreach ($attempts as $candidate) {
            $hps = hobc_pool_hashrate_candidate_hps($candidate);
            if ($hps <= 0) {
                continue;
            }
            if ($ignoreTinyPoolValues && $hps < 1000) {
                continue;
            }
            return ['hps' => $hps, 'display' => hobc_format_hashrate_hps($hps)];
        }
        return null;
    };

    $resolved = $pick($shortPoolAttempts, true);
    if ($resolved !== null) {
        return $resolved;
    }
    $resolved = $pick($graphAttempts);
    if ($resolved !== null) {
        return $resolved;
    }
    if ($workers <= 0) {
        return ['hps' => 0.0, 'display' => '0'];
    }
    $resolved = $pick($longPoolAttempts);
    if ($resolved !== null) {
        return $resolved;
    }

    return ['hps' => 0.0, 'display' => '0'];
}

function hobc_pool_resolve_window_hashrate(mixed $poolValue, array $graphWindow): string
{
    $hps = hobc_pool_hashrate_candidate_hps($poolValue);
    if ($hps > 0 && $hps < 1000) {
        $hps = 0.0;
    }
    if ($hps <= 0) {
        $hps = hobc_pool_hashrate_candidate_hps($graphWindow['hashrate_estimate'] ?? null);
    }
    return $hps > 0 ? hobc_format_hashrate_hps($hps) : '0';
}

function hobc_compact_bits_to_difficulty(mixed $bits): ?float
{
    $bits = is_string($bits) ? strtolower(trim($bits)) : '';
    if (!preg_match('/^[0-9a-f]{8}$/', $bits)) {
        return null;
    }

    $exponent = hexdec(substr($bits, 0, 2));
    $mantissa = hexdec(substr($bits, 2));
    if ($mantissa <= 0) {
        return null;
    }

    $difficulty = (65535.0 / (float)$mantissa) * pow(256.0, 0x1d - $exponent);
    return is_finite($difficulty) && $difficulty > 0 ? $difficulty : null;
}

function hobc_next_work_difficulty(): ?float
{
    $template = hobc_rpc('getblocktemplate', [['rules' => ['segwit']]]);
    if (empty($template['ok']) || !is_array($template['result'] ?? null)) {
        return null;
    }

    return hobc_compact_bits_to_difficulty($template['result']['bits'] ?? null);
}

function hobc_format_duration(float $seconds): string
{
    if ($seconds <= 0 || !is_finite($seconds)) {
        return 'not_available';
    }
    $days = $seconds / 86400;
    if ($days >= 365) {
        return number_format($days / 365, 2) . 'y';
    }
    if ($days >= 1) {
        return number_format($days, 2) . 'd';
    }
    $hours = $seconds / 3600;
    if ($hours >= 1) {
        return number_format($hours, 2) . 'h';
    }
    if ($seconds < 60) {
        return number_format($seconds, 2) . 's';
    }
    return number_format($seconds / 60, 2) . 'm';
}

function hobc_probability(float $windowSeconds, float $expectedSeconds): string
{
    if ($windowSeconds <= 0 || $expectedSeconds <= 0 || !is_finite($expectedSeconds)) {
        return 'not_available';
    }
    $probability = 1 - exp(-$windowSeconds / $expectedSeconds);
    return number_format($probability * 100, 6, '.', '') . '%';
}

function hobc_share_created_ts(mixed $created): int
{
    if (is_numeric($created)) {
        $ts = (float)$created;
        return $ts > 20000000000 ? (int)floor($ts / 1000) : (int)floor($ts);
    }
    if (!is_string($created) || $created === '') {
        return 0;
    }
    $ts = (float)str_replace(',', '.', $created);
    return $ts > 0 ? (int)floor($ts) : 0;
}

function hobc_pool_share_stats(string $logDir): array
{
    if (!is_dir($logDir) || !is_readable($logDir)) {
        return [
            'accepted_shares' => 'not_available',
            'rejected_shares' => 'not_available',
            'last_share' => 'not_available',
            'latest_shares' => [],
            'miner_leaderboard' => [],
        ];
    }

    $accepted = 0;
    $rejected = 0;
    $lastShare = null;
    $lastTimestamp = 0.0;
    $bestShare = 0.0;
    $latestShares = [];
    $workers = [];
    $sessions = [];
    $now = time();
    $windows = [
        '5m' => ['seconds' => 300, 'accepted' => 0, 'rejected' => 0, 'best_share' => 0.0, 'accepted_diff_sum' => 0.0],
        '30m' => ['seconds' => 1800, 'accepted' => 0, 'rejected' => 0, 'best_share' => 0.0, 'accepted_diff_sum' => 0.0],
        '60m' => ['seconds' => 3600, 'accepted' => 0, 'rejected' => 0, 'best_share' => 0.0, 'accepted_diff_sum' => 0.0],
        '12h' => ['seconds' => 43200, 'accepted' => 0, 'rejected' => 0, 'best_share' => 0.0, 'accepted_diff_sum' => 0.0],
    ];

    try {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($logDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || !str_ends_with($file->getFilename(), '.sharelog')) {
                continue;
            }

            $handle = @fopen($file->getPathname(), 'r');
            if ($handle === false) {
                continue;
            }

            while (($line = fgets($handle)) !== false) {
                $row = json_decode(trim($line), true);
                if (!is_array($row) || !array_key_exists('result', $row)) {
                    continue;
                }

                $isAccepted = (bool)$row['result'];
                $workername = (string)($row['workername'] ?? 'not_available');
                $maskedWorker = hobc_mask_worker_name($workername);
                $assignedDiff = isset($row['diff']) ? (float)$row['diff'] : 0.0;
                $shareDiff = isset($row['sdiff']) ? (float)$row['sdiff'] : 0.0;
                $bestShare = max($bestShare, $shareDiff);
                if ($isAccepted) {
                    $accepted++;
                } else {
                    $rejected++;
                }

                $timestamp = (float)hobc_share_created_ts($row['createdate'] ?? '');
                $ageSeconds = $timestamp > 0 ? max(0, $now - (int)$timestamp) : null;

                if (!isset($workers[$workername])) {
                    $workers[$workername] = [
                        'workername' => $maskedWorker,
                        'accepted_shares' => 0,
                        'rejected_shares' => 0,
                        'best_share' => 0.0,
                        'last_accepted_share' => 0.0,
                        'last_accepted_assigned_diff' => 0.0,
                        'last_accepted_share_time' => 'not_available',
                        'last_share_time' => 'not_available',
                        'last_share_age_seconds' => 'not_available',
                    ];
                }
                if ($isAccepted) {
                    $workers[$workername]['accepted_shares']++;
                    if ($timestamp >= (float)($workers[$workername]['last_accepted_share_timestamp'] ?? 0)) {
                        $workers[$workername]['last_accepted_share_timestamp'] = $timestamp;
                        $workers[$workername]['last_accepted_share'] = $shareDiff;
                        $workers[$workername]['last_accepted_assigned_diff'] = $assignedDiff;
                        $workers[$workername]['last_accepted_share_time'] = $timestamp > 0 ? gmdate('c', (int)$timestamp) : 'not_available';
                    }
                } else {
                    $workers[$workername]['rejected_shares']++;
                }
                $workers[$workername]['best_share'] = max((float)$workers[$workername]['best_share'], $shareDiff);
                if ($timestamp >= (float)($workers[$workername]['last_share_timestamp'] ?? 0)) {
                    $workers[$workername]['last_share_timestamp'] = $timestamp;
                    $workers[$workername]['last_share_time'] = $timestamp > 0 ? gmdate('c', (int)$timestamp) : 'not_available';
                    $workers[$workername]['last_share_age_seconds'] = $ageSeconds ?? 'not_available';
                }

                foreach ($windows as $key => $window) {
                    if ($ageSeconds !== null && $ageSeconds <= $window['seconds']) {
                        if ($isAccepted) {
                            $windows[$key]['accepted']++;
                            $windows[$key]['accepted_diff_sum'] += $assignedDiff;
                        } else {
                            $windows[$key]['rejected']++;
                        }
                        $windows[$key]['best_share'] = max((float)$windows[$key]['best_share'], $shareDiff);
                    }
                }

                if ($ageSeconds !== null && $ageSeconds <= 10800) {
                    if (!isset($sessions[$workername])) {
                        $sessions[$workername] = [
                            'workername' => $maskedWorker,
                            'session_accepted' => 0,
                            'session_rejected' => 0,
                            'session_best_share' => 0.0,
                            'session_last_accepted_share' => 0.0,
                            'session_last_accepted_assigned_diff' => 0.0,
                            'session_last_accepted_share_timestamp' => 0.0,
                            'session_started_timestamp' => $timestamp > 0 ? $timestamp : (float)$now,
                            'last_share_timestamp' => 0.0,
                            'accepted_diff_sum' => 0.0,
                            'window_5m_accepted' => 0,
                            'window_5m_accepted_diff_sum' => 0.0,
                            'window_60m_accepted' => 0,
                            'window_60m_accepted_diff_sum' => 0.0,
                            'window_12h_accepted' => 0,
                            'window_12h_accepted_diff_sum' => 0.0,
                        ];
                    }
                    if ($isAccepted) {
                        $sessions[$workername]['session_accepted']++;
                        $sessions[$workername]['accepted_diff_sum'] += $assignedDiff;
                        if ($ageSeconds !== null && $ageSeconds <= 300) {
                            $sessions[$workername]['window_5m_accepted']++;
                            $sessions[$workername]['window_5m_accepted_diff_sum'] += $assignedDiff;
                        }
                        if ($ageSeconds !== null && $ageSeconds <= 3600) {
                            $sessions[$workername]['window_60m_accepted']++;
                            $sessions[$workername]['window_60m_accepted_diff_sum'] += $assignedDiff;
                        }
                        if ($ageSeconds !== null && $ageSeconds <= 43200) {
                            $sessions[$workername]['window_12h_accepted']++;
                            $sessions[$workername]['window_12h_accepted_diff_sum'] += $assignedDiff;
                        }
                        if ($timestamp >= (float)($sessions[$workername]['session_last_accepted_share_timestamp'] ?? 0)) {
                            $sessions[$workername]['session_last_accepted_share_timestamp'] = $timestamp;
                            $sessions[$workername]['session_last_accepted_share'] = $shareDiff;
                            $sessions[$workername]['session_last_accepted_assigned_diff'] = $assignedDiff;
                        }
                    } else {
                        $sessions[$workername]['session_rejected']++;
                    }
                    $sessions[$workername]['session_best_share'] = max((float)$sessions[$workername]['session_best_share'], $shareDiff);
                    if ($timestamp > 0) {
                        $sessions[$workername]['session_started_timestamp'] = min((float)$sessions[$workername]['session_started_timestamp'], $timestamp);
                        $sessions[$workername]['last_share_timestamp'] = max((float)$sessions[$workername]['last_share_timestamp'], $timestamp);
                    }
                }

                $latestShares[] = [
                    'time' => $timestamp > 0 ? gmdate('c', (int)$timestamp) : 'not_available',
                    'age_seconds' => $ageSeconds ?? 'not_available',
                    'workername' => $maskedWorker,
                    'share_difficulty' => $shareDiff,
                    'assigned_difficulty' => $assignedDiff,
                    'result' => $isAccepted ? 'accepted' : 'rejected',
                    'hash' => (string)($row['hash'] ?? 'not_available'),
                    'reject_reason' => $isAccepted ? '' : (string)($row['reject-reason'] ?? 'not_available'),
                ];

                if ($isAccepted && $timestamp >= $lastTimestamp) {
                    $lastTimestamp = $timestamp;
                    $lastShare = [
                        'workername' => $maskedWorker,
                        'difficulty' => $assignedDiff ?: 'not_available',
                        'share_difficulty' => $shareDiff ?: 'not_available',
                        'hash' => (string)($row['hash'] ?? 'not_available'),
                        'time' => $timestamp > 0 ? gmdate('c', (int)$timestamp) : 'not_available',
                        'age_seconds' => $ageSeconds ?? 'not_available',
                    ];
                }
            }
            fclose($handle);
        }
    } catch (Throwable $e) {
        return [
            'accepted_shares' => 'not_available',
            'rejected_shares' => 'not_available',
            'last_share' => 'not_available',
            'latest_shares' => [],
            'miner_leaderboard' => [],
        ];
    }

    usort($latestShares, static fn (array $a, array $b): int => ((int)($a['age_seconds'] ?? PHP_INT_MAX)) <=> ((int)($b['age_seconds'] ?? PHP_INT_MAX)));
    $leaderboard = array_values($workers);
    usort($leaderboard, static fn (array $a, array $b): int => ((int)$b['accepted_shares']) <=> ((int)$a['accepted_shares']));
    foreach ($leaderboard as &$worker) {
        unset($worker['last_share_timestamp']);
        unset($worker['last_accepted_share_timestamp']);
        $worker['reject_percent'] = ((int)$worker['accepted_shares'] + (int)$worker['rejected_shares']) > 0
            ? number_format(((int)$worker['rejected_shares'] / ((int)$worker['accepted_shares'] + (int)$worker['rejected_shares'])) * 100, 4, '.', '') . '%'
            : '0.0000%';
    }
    unset($worker);

    foreach ($windows as &$window) {
        $seconds = max(1, (int)$window['seconds']);
        $window['accepted_per_minute'] = number_format(((int)$window['accepted'] / ($seconds / 60)), 4, '.', '');
        $window['rejected_per_minute'] = number_format(((int)$window['rejected'] / ($seconds / 60)), 4, '.', '');
        $hps = ((float)$window['accepted_diff_sum'] * 4294967296.0) / $seconds;
        $window['hashrate_estimate'] = $hps;
        unset($window['seconds'], $window['accepted_diff_sum']);
    }
    unset($window);

    $minerSessions = array_values(array_map(static function (array $session) use ($now): array {
        $started = (float)($session['session_started_timestamp'] ?? $now);
        $last = (float)($session['last_share_timestamp'] ?? 0);
        $elapsed = max(60.0, ($last > 0 ? $last : (float)$now) - $started);
        $accepted = (int)($session['session_accepted'] ?? 0);
        $rejected = (int)($session['session_rejected'] ?? 0);
        $total = $accepted + $rejected;
        $hps = ((float)($session['accepted_diff_sum'] ?? 0.0) * 4294967296.0) / $elapsed;
        $hps5m = ((float)($session['window_5m_accepted_diff_sum'] ?? 0.0) * 4294967296.0) / 300.0;
        $hps60m = ((float)($session['window_60m_accepted_diff_sum'] ?? 0.0) * 4294967296.0) / 3600.0;
        $hps12h = ((float)($session['window_12h_accepted_diff_sum'] ?? 0.0) * 4294967296.0) / 43200.0;
        $shares5m = (int)($session['window_5m_accepted'] ?? 0);
        $shares60m = (int)($session['window_60m_accepted'] ?? 0);
        $shares12h = (int)($session['window_12h_accepted'] ?? 0);

        return [
            'workername' => (string)$session['workername'],
            'session_accepted' => $accepted,
            'session_rejected' => $rejected,
            'session_best_share' => (float)($session['session_best_share'] ?? 0.0),
            'session_last_accepted_share' => (float)($session['session_last_accepted_share'] ?? 0.0),
            'session_last_accepted_assigned_diff' => (float)($session['session_last_accepted_assigned_diff'] ?? 0.0),
            'session_last_accepted_share_time' => (float)($session['session_last_accepted_share_timestamp'] ?? 0) > 0 ? gmdate('c', (int)$session['session_last_accepted_share_timestamp']) : 'not_available',
            'session_reject_percent' => $total > 0 ? number_format(($rejected / $total) * 100, 4, '.', '') . '%' : '0.0000%',
            'session_hashrate_estimate' => $hps,
            'session_hashrate_5m' => $hps5m,
            'session_hashrate_60m' => $hps60m,
            'session_hashrate_12h' => $hps12h,
            'session_share_rate_5m' => number_format($shares5m / 5, 4, '.', ''),
            'session_share_rate_60m' => number_format($shares60m / 60, 4, '.', ''),
            'session_share_rate_12h' => number_format($shares12h / 720, 4, '.', ''),
            'session_started_at' => $started > 0 ? gmdate('c', (int)$started) : 'not_available',
            'last_share_age_seconds' => $last > 0 ? max(0, $now - (int)$last) : 'not_available',
        ];
    }, $sessions));

    usort($minerSessions, static fn (array $a, array $b): int => ((float)($b['session_hashrate_estimate'] ?? 0)) <=> ((float)($a['session_hashrate_estimate'] ?? 0)));

    return [
        'accepted_shares' => $accepted,
        'rejected_shares' => $rejected,
        'last_share' => $lastShare ?? 'not_available',
        'best_share' => $bestShare,
        'recent_windows' => $windows,
        'graph_windows' => $windows,
        'latest_shares' => array_slice($latestShares, 0, 50),
        'miner_leaderboard' => array_slice($leaderboard, 0, 25),
        'miner_sessions' => array_slice($minerSessions, 0, 25),
        'active_sessions' => count($minerSessions),
        'seen_workers' => count($leaderboard),
    ];
}

function hobc_pool_collected_stats(string $path): array
{
    $mtime = is_readable($path) ? @filemtime($path) : false;
    $ageSeconds = $mtime ? max(0, time() - (int)$mtime) : null;
    $data = hobc_read_json_file($path);
    if ($data === null || empty($data['ok'])) {
        return [];
    }

    $stale = $ageSeconds !== null && $ageSeconds > 60;
    $latestShares = is_array($data['latest_shares'] ?? null) ? $data['latest_shares'] : [];
    $lastShare = $data['last_share'] ?? null;
    if (!is_array($lastShare) && isset($latestShares[0]) && is_array($latestShares[0])) {
        $latest = $latestShares[0];
        $lastShare = [
            'workername' => (string)($latest['workername'] ?? 'not_available'),
            'difficulty' => $latest['assigned_difficulty'] ?? 'not_available',
            'share_difficulty' => $latest['share_difficulty'] ?? 'not_available',
            'hash' => (string)($latest['hash'] ?? 'not_available'),
            'time' => $latest['time'] ?? 'not_available',
            'age_seconds' => $latest['age_seconds'] ?? 'not_available',
        ];
    }

    return [
        'stats_collected_at' => $data['collected_at'] ?? 'not_available',
        'stats_collector' => $data['collector'] ?? 'not_available',
        'stats_collector_cache_age_seconds' => $ageSeconds ?? 'not_available',
        'stats_collector_cache_stale' => $stale,
        'session_gap_seconds' => $data['session_gap_seconds'] ?? 'not_available',
        'session_gap_label' => is_numeric($data['session_gap_seconds'] ?? null) ? hobc_format_duration((float)$data['session_gap_seconds']) : 'not_available',
        'accepted_shares' => $data['accepted_shares'] ?? 'not_available',
        'rejected_shares' => $data['rejected_shares'] ?? 'not_available',
        'reject_percent' => $data['reject_percent'] ?? 'not_available',
        'best_share' => $data['best_share'] ?? 'not_available',
        'active_sessions' => $data['active_sessions'] ?? 'not_available',
        'seen_workers' => $data['seen_workers'] ?? 'not_available',
        'graph_windows' => $data['graph_windows'] ?? [],
        'latest_shares' => $latestShares,
        'miner_leaderboard' => $data['miner_leaderboard'] ?? [],
        'miner_sessions' => $data['miner_sessions'] ?? [],
        'blocks_found' => $data['blocks_found'] ?? [],
        'last_share' => $lastShare ?? 'not_available',
    ];
}

function hobc_pool_blocks_from_ckpool_log(string $logDir, int $limit = 20): array
{
    if (!is_dir($logDir) || !is_readable($logDir)) {
        return [];
    }

    $logs = glob(rtrim($logDir, '/') . '/*.log') ?: [];
    if ($logs === []) {
        return [];
    }

    usort($logs, static fn (string $a, string $b): int => ((int)@filemtime($b)) <=> ((int)@filemtime($a)));
    $blocks = [];

    foreach ($logs as $logPath) {
        $handle = @fopen($logPath, 'r');
        if ($handle === false) {
            continue;
        }

        while (($line = fgets($handle)) !== false) {
            if (!preg_match('/^\[([0-9-]+\s+[0-9:.]+)\].*Solved and confirmed block\s+(\d+)\s+by\s+(\S+)/', $line, $m)) {
                continue;
            }

            $height = (int)$m[2];
            if ($height <= 0) {
                continue;
            }

            $seenTs = strtotime($m[1]);
            $blocks[$height] = [
                'height' => $height,
                'hash' => 'not_available',
                'status' => 'hit',
                'time' => $seenTs !== false ? gmdate('c', $seenTs) : 'not_available',
                'event_time' => $seenTs !== false ? gmdate('c', $seenTs) : 'not_available',
                'event_ts' => $seenTs !== false ? $seenTs : 0,
                'block_time' => 'not_available',
                'block_time_ts' => 0,
                'seen_ts' => $seenTs !== false ? $seenTs : 0,
                'workername' => hobc_mask_worker_name((string)$m[3]),
                'workername_raw' => (string)$m[3],
                'confirmations' => 'not_available',
            ];
        }
        fclose($handle);
    }

    krsort($blocks, SORT_NUMERIC);
    return array_slice(array_values($blocks), 0, $limit);
}

function hobc_pool_blocks_from_sharelogs(string $logDir, int $limit = 20): array
{
    if (!is_dir($logDir) || !is_readable($logDir)) {
        return [];
    }

    $info = hobc_rpc('getblockchaininfo');
    $tipHeight = is_array($info['result'] ?? null) && isset($info['result']['blocks']) ? (int)$info['result']['blocks'] : 0;
    if ($tipHeight <= 0) {
        return [];
    }

    $dirs = [];
    try {
        $iterator = new DirectoryIterator($logDir);
        foreach ($iterator as $entry) {
            if (!$entry->isDir() || $entry->isDot()) {
                continue;
            }
            $name = $entry->getFilename();
            if (!preg_match('/^[0-9a-fA-F]{8}$/', $name)) {
                continue;
            }
            $height = hexdec($name);
            if ($height <= 0 || $height > $tipHeight) {
                continue;
            }
            $dirs[$height] = $entry->getPathname();
        }
    } catch (Throwable $e) {
        return [];
    }

    krsort($dirs, SORT_NUMERIC);
    $blocks = [];

    foreach ($dirs as $height => $dir) {
        if (count($blocks) >= $limit) {
            break;
        }

        $hash = hobc_rpc('getblockhash', [$height]);
        if (empty($hash['ok']) || !is_string($hash['result'] ?? null)) {
            continue;
        }
        $blockHash = (string)$hash['result'];
        $blockData = hobc_rpc('getblock', [$blockHash, 1]);
        $blockTs = (!empty($blockData['ok']) && is_array($blockData['result'] ?? null) && isset($blockData['result']['time']))
            ? (int)$blockData['result']['time']
            : 0;

        try {
            $files = new DirectoryIterator($dir);
        } catch (Throwable $e) {
            continue;
        }

        $match = null;
        $fallbackShare = null;
        $fallbackTs = 0;
        foreach ($files as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.sharelog')) {
                continue;
            }
            $handle = @fopen($file->getPathname(), 'r');
            if ($handle === false) {
                continue;
            }
            while (($line = fgets($handle)) !== false) {
                $row = json_decode(trim($line), true);
                if (!is_array($row) || empty($row['result'])) {
                    continue;
                }
                $createdTs = hobc_share_created_ts($row['createdate'] ?? '');
                if ($createdTs >= $fallbackTs) {
                    $fallbackShare = $row;
                    $fallbackTs = $createdTs;
                }
                if (!hash_equals($blockHash, (string)($row['hash'] ?? ''))) {
                    continue;
                }
                $match = [
                    'height' => (int)$height,
                    'hash' => $blockHash,
                    'status' => 'waiting_maturity',
                    'time' => $blockTs > 0 ? gmdate('c', $blockTs) : ($createdTs > 0 ? gmdate('c', $createdTs) : 'not_available'),
                    'event_time' => $createdTs > 0 ? gmdate('c', $createdTs) : 'not_available',
                    'block_time' => $blockTs > 0 ? gmdate('c', $blockTs) : 'not_available',
                    'event_ts' => $createdTs,
                    'block_time_ts' => $blockTs,
                    'workername' => hobc_mask_worker_name((string)($row['workername'] ?? 'not_available')),
                    'workername_raw' => (string)($row['workername'] ?? 'not_available'),
                    'confirmations' => isset($blockData['result']['confirmations']) ? (int)$blockData['result']['confirmations'] : 'not_available',
                ];
                break;
            }
            fclose($handle);
            if ($match !== null) {
                break;
            }
        }

        if ($match === null && $fallbackShare !== null) {
            $match = [
                'height' => (int)$height,
                'hash' => $blockHash,
                'status' => 'waiting_maturity',
                'time' => $blockTs > 0 ? gmdate('c', $blockTs) : ($fallbackTs > 0 ? gmdate('c', $fallbackTs) : 'not_available'),
                'event_time' => $fallbackTs > 0 ? gmdate('c', $fallbackTs) : 'not_available',
                'block_time' => $blockTs > 0 ? gmdate('c', $blockTs) : 'not_available',
                'event_ts' => $fallbackTs,
                'block_time_ts' => $blockTs,
                'workername' => hobc_mask_worker_name((string)($fallbackShare['workername'] ?? 'not_available')),
                'workername_raw' => (string)($fallbackShare['workername'] ?? 'not_available'),
                'confirmations' => isset($blockData['result']['confirmations']) ? (int)$blockData['result']['confirmations'] : 'not_available',
            ];
        }

        if ($match !== null) {
            $blocks[] = $match;
        }
    }

    return $blocks;
}

function hobc_pool_status(string $pool, bool $lite = false): array
{
    $map = [
        'main' => [
            'file' => '/home/hobbyhashcoin/hobbyhash-logs/ckpool-main/pool/pool.status',
            'log_dir' => '/home/hobbyhashcoin/hobbyhash-logs/ckpool-main',
            'state_file' => '/home/hobbyhashcoin/hobbyhash-data/mainnet/payoutd-main-state.json',
            'stats_file' => '/home/hobbyhashcoin/hobbyhash-data/mainnet/pool-stats-main.json',
            'stratum_url' => 'stratum+tcp://pool.hobbyhashcoin.com:5555',
            'stratum_port' => 5555,
            'status_port' => 18763,
            'min_diff' => '1000',
            'start_diff' => '5000',
        ],
        'nano' => [
            'file' => '/home/hobbyhashcoin/hobbyhash-logs/ckpool-nano/pool/pool.status',
            'log_dir' => '/home/hobbyhashcoin/hobbyhash-logs/ckpool-nano',
            'state_file' => '/home/hobbyhashcoin/hobbyhash-data/mainnet/payoutd-nano-state.json',
            'stats_file' => '/home/hobbyhashcoin/hobbyhash-data/mainnet/pool-stats-nano.json',
            'stratum_url' => 'stratum+tcp://pool.hobbyhashcoin.com:5556',
            'stratum_port' => 5556,
            'status_port' => 18764,
            'min_diff' => '0.005',
            'start_diff' => '0.005',
        ],
    ];

    $meta = $map[$pool] ?? null;
    if ($meta === null) {
        return hobc_status_payload('not_available');
    }

    $poolEnabledKey = $pool === 'main' ? 'pool.main_enabled' : 'pool.nano_enabled';
    if (!(bool)hobc_admin_setting_value($poolEnabledKey, true)) {
        return hobc_status_payload('offline', ucfirst($pool) . ' pool is disabled by admin settings.', [
            'pool' => $pool,
            'stratum_url' => $meta['stratum_url'],
            'stratum_port' => $meta['stratum_port'],
            'status_port' => $meta['status_port'],
            'min_diff' => $meta['min_diff'],
            'start_diff' => $meta['start_diff'],
            'solo_only' => true,
            'public_enabled' => false,
        ]);
    }

    $notice = (string)hobc_admin_setting_value('ops.maintenance_notice', '');
    if ((bool)hobc_admin_setting_value('ops.pool_public_stats_paused', false)) {
        return hobc_status_payload('syncing', $notice !== '' ? $notice : 'Public pool stats display is temporarily paused by admin.', [
            'pool' => $pool,
            'stratum_url' => $meta['stratum_url'],
            'stratum_port' => $meta['stratum_port'],
            'status_port' => $meta['status_port'],
            'min_diff' => $meta['min_diff'],
            'start_diff' => $meta['start_diff'],
            'solo_only' => true,
            'public_stats_paused' => true,
        ]);
    }

    $collectedStats = hobc_pool_collected_stats($meta['stats_file']);
    $collectorFresh = is_numeric($collectedStats['accepted_shares'] ?? null)
        && empty($collectedStats['stats_collector_cache_stale']);
    if ($collectorFresh) {
        $shareStats = $collectedStats;
    } else {
        $shareStats = array_replace(hobc_pool_share_stats($meta['log_dir']), $collectedStats);
    }
    $base = [
        'pool' => $pool,
        'stratum_url' => $meta['stratum_url'],
        'stratum_port' => $meta['stratum_port'],
        'status_port' => $meta['status_port'],
        'min_diff' => $meta['min_diff'],
        'start_diff' => $meta['start_diff'],
        'solo_only' => true,
        'last_block' => $lite ? 'not_available' : hobc_pool_last_block($meta['state_file']),
        'blocks_found' => $lite ? [] : hobc_pool_blocks_found($meta['state_file'], $meta['log_dir']),
    ] + $shareStats;

    if (!is_readable($meta['file'])) {
        return hobc_status_payload('offline', 'Pool status file is unavailable.', $base + ['workers' => 'not_available', 'hashrate' => 'not_available']);
    }

    $lines = file($meta['file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $summary = isset($lines[0]) ? json_decode($lines[0], true) : [];
    $hashrate = isset($lines[1]) ? json_decode($lines[1], true) : [];
    if (!is_array($summary)) {
        $summary = [];
    }
    if (!is_array($hashrate)) {
        $hashrate = [];
    }

    $graphWindows = is_array($shareStats['graph_windows'] ?? null) ? $shareStats['graph_windows'] : [];
    $connectedWorkers = isset($summary['Workers']) ? (int)$summary['Workers'] : 0;
    $resolvedHashrate = hobc_pool_resolve_hashrate($hashrate, $graphWindows, $connectedWorkers);
    $poolHashrate = $resolvedHashrate['display'];
    $chain = hobc_rpc('getblockchaininfo');
    $network = is_array($chain['result'] ?? null) ? $chain['result'] : [];
    $chainDiff = isset($network['difficulty']) ? (float)$network['difficulty'] : null;
    $nextWorkDiff = $lite ? null : hobc_next_work_difficulty();
    $networkDiff = $nextWorkDiff ?? $chainDiff;
    $poolHps = $resolvedHashrate['hps'];
    $expectedSeconds = ($networkDiff !== null && $networkDiff > 0 && $poolHps > 0)
        ? ($networkDiff * 4294967296.0) / $poolHps
        : 0.0;
    $bestShare = is_numeric($shareStats['best_share'] ?? null) ? (float)$shareStats['best_share'] : 0.0;
    $bestProgress = ($networkDiff !== null && $networkDiff > 0 && $bestShare > 0)
        ? number_format(($bestShare / $networkDiff) * 100, 6, '.', '') . '%'
        : 'not_available';
    $needMultiplier = ($networkDiff !== null && $networkDiff > 0 && $bestShare > 0)
        ? number_format($networkDiff / $bestShare, 2, '.', '') . 'x'
        : 'not_available';
    $latestShareDiff = is_array($shareStats['last_share'] ?? null) && is_numeric($shareStats['last_share']['share_difficulty'] ?? null)
        ? (float)$shareStats['last_share']['share_difficulty']
        : 0.0;
    $latestShareVsNetwork = ($networkDiff !== null && $networkDiff > 0 && $latestShareDiff > 0)
        ? number_format(($latestShareDiff / $networkDiff) * 100, 8, '.', '') . '%'
        : 'not_available';
    $networkHashps = $lite ? ['ok' => false] : hobc_rpc('getnetworkhashps');
    $networkHps = ($networkHashps['ok'] && is_numeric($networkHashps['result'] ?? null)) ? (float)$networkHashps['result'] : 0.0;
    $poolVsNetwork = ($networkHps > 0 && $poolHps > 0)
        ? number_format(($poolHps / $networkHps) * 100, 8, '.', '') . '%'
        : 'not_available';
    $mempool = $lite ? ['ok' => false] : hobc_rpc('getmempoolinfo');
    $mempoolInfo = is_array($mempool['result'] ?? null) ? $mempool['result'] : [];
    $w5 = is_array($graphWindows['5m'] ?? null) ? $graphWindows['5m'] : [];
    $w30 = is_array($graphWindows['30m'] ?? null) ? $graphWindows['30m'] : [];
    $w60 = is_array($graphWindows['60m'] ?? null) ? $graphWindows['60m'] : [];
    $w12h = is_array($graphWindows['12h'] ?? null) ? $graphWindows['12h'] : [];
    $accepted60 = is_numeric($w60['accepted'] ?? null) ? (int)$w60['accepted'] : 0;
    $sharePressure60 = is_numeric($w60['accepted_per_minute'] ?? null) ? (float)$w60['accepted_per_minute'] : 0.0;
    $leaderboard = is_array($shareStats['miner_leaderboard'] ?? null) ? $shareStats['miner_leaderboard'] : [];
    $topAccepted = 0;
    foreach ($leaderboard as $miner) {
        if (is_array($miner) && is_numeric($miner['accepted_shares'] ?? null)) {
            $topAccepted = max($topAccepted, (int)$miner['accepted_shares']);
        }
    }
    $acceptedTotal = is_numeric($shareStats['accepted_shares'] ?? null) ? (int)$shareStats['accepted_shares'] : 0;
    $topMinerShare = ($acceptedTotal > 0 && $topAccepted > 0) ? number_format(($topAccepted / $acceptedTotal) * 100, 2, '.', '') . '%' : 'not_available';

    return hobc_status_payload('online', $notice, array_merge($base, [
        'coin' => 'HOBC',
        'coin_name' => 'HobbyHash Coin',
        'node_status' => !empty($network['initialblockdownload']) ? 'syncing' : 'ready',
        'users' => isset($summary['Users']) ? (int)$summary['Users'] : 'not_available',
        'workers' => isset($summary['Workers']) ? (int)$summary['Workers'] : 'not_available',
        'idle_workers' => isset($summary['Idle']) ? (int)$summary['Idle'] : 'not_available',
        'disconnected_workers' => isset($summary['Disconnected']) ? (int)$summary['Disconnected'] : 'not_available',
        'hashrate' => $poolHashrate,
        'hashrate_1m' => hobc_pool_resolve_window_hashrate($hashrate['hashrate1m'] ?? null, $w5),
        'hashrate_5m' => hobc_pool_resolve_window_hashrate($hashrate['hashrate5m'] ?? null, $w5),
        'hashrate_1hr' => hobc_pool_resolve_window_hashrate($hashrate['hashrate1hr'] ?? null, $w60),
        'hashrate_12hr' => hobc_pool_resolve_window_hashrate($hashrate['hashrate12hr'] ?? ($hashrate['hashrate6hr'] ?? null), $w12h),
        'network_difficulty' => $networkDiff ?? 'not_available',
        'mining_difficulty' => $networkDiff ?? 'not_available',
        'chain_difficulty' => $chainDiff ?? 'not_available',
        'networkhashps' => $networkHps > 0 ? $networkHps : 'not_available',
        'pool_vs_network' => $poolVsNetwork,
        'chain_height' => isset($network['blocks']) ? (int)$network['blocks'] : 'not_available',
        'chain_headers' => isset($network['headers']) ? (int)$network['headers'] : 'not_available',
        'verificationprogress' => isset($network['verificationprogress']) ? number_format(((float)$network['verificationprogress']) * 100, 4, '.', '') . '%' : 'not_available',
        'mempool_tx_count' => isset($mempoolInfo['size']) ? (int)$mempoolInfo['size'] : 'not_available',
        'mempool_bytes' => isset($mempoolInfo['bytes']) ? (int)$mempoolInfo['bytes'] : 'not_available',
        'chain_size_on_disk' => isset($network['size_on_disk']) ? (int)$network['size_on_disk'] : 'not_available',
        'chain' => $network['chain'] ?? 'not_available',
        'pruned' => isset($network['pruned']) ? (bool)$network['pruned'] : 'not_available',
        'bestblockhash' => $network['bestblockhash'] ?? 'not_available',
        'time_to_hit' => $expectedSeconds > 0 ? hobc_format_duration($expectedSeconds) : 'not_available',
        'median_hit_window' => $expectedSeconds > 0 ? hobc_format_duration($expectedSeconds * log(2)) : 'not_available',
        'p90_hit_window' => $expectedSeconds > 0 ? hobc_format_duration($expectedSeconds * log(10)) : 'not_available',
        'odds_1h' => hobc_probability(3600, $expectedSeconds),
        'odds_6h' => hobc_probability(21600, $expectedSeconds),
        'odds_12h' => hobc_probability(43200, $expectedSeconds),
        'odds_24h' => hobc_probability(86400, $expectedSeconds),
        'odds_7d' => hobc_probability(604800, $expectedSeconds),
        'odds_30d' => hobc_probability(2592000, $expectedSeconds),
        'expected_blocks_per_day' => $expectedSeconds > 0 ? number_format(86400 / $expectedSeconds, 8, '.', '') : 'not_available',
        'expected_blocks_per_month' => $expectedSeconds > 0 ? number_format(2592000 / $expectedSeconds, 8, '.', '') : 'not_available',
        'expected_blocks_per_year' => $expectedSeconds > 0 ? number_format(31536000 / $expectedSeconds, 8, '.', '') : 'not_available',
        'best_share_progress' => $bestProgress,
        'best_share_need_multiplier' => $needMultiplier,
        'latest_share_vs_network' => $latestShareVsNetwork,
        'top_miner_share' => $topMinerShare,
        'recent_best_hits' => [
            '5m' => $w5['best_share'] ?? 'not_available',
            '30m' => $w30['best_share'] ?? 'not_available',
            '60m' => $w60['best_share'] ?? 'not_available',
            'shares_5m' => $w5['accepted'] ?? 'not_available',
            'shares_30m' => $w30['accepted'] ?? 'not_available',
            'shares_60m' => $accepted60 ?: 'not_available',
        ],
        'share_pressure' => [
            'per_minute_60m' => $sharePressure60 > 0 ? number_format($sharePressure60, 4, '.', '') : 'not_available',
            'accepted_60m' => $accepted60 ?: 'not_available',
        ],
        'block_reward' => '45.00000000 HOBC',
        'block_value' => 'not_available',
        'market_price' => 'not_available',
        'status_updated_at' => isset($summary['lastupdate']) ? gmdate('c', (int)$summary['lastupdate']) : 'not_available',
    ]));
}

function hobc_latest_block_summary(): array
{
    $info = hobc_rpc('getblockchaininfo');
    if (!$info['ok'] || !is_array($info['result'])) {
        return ['ok' => false, 'status' => 'offline'];
    }
    $height = (int)($info['result']['blocks'] ?? 0);
    if ($height <= 0) {
        return ['ok' => false, 'status' => 'syncing'];
    }
    $hash = hobc_rpc('getblockhash', [$height]);
    if (!$hash['ok'] || !is_string($hash['result'])) {
        return ['ok' => false, 'status' => 'not_available'];
    }
    $block = hobc_rpc('getblock', [$hash['result'], 1]);
    if (!$block['ok'] || !is_array($block['result'])) {
        return ['ok' => false, 'status' => 'not_available'];
    }
    return ['ok' => true, 'status' => 'online', 'height' => $height, 'hash' => $hash['result'], 'time' => $block['result']['time'] ?? 'not_available', 'tx_count' => isset($block['result']['tx']) && is_array($block['result']['tx']) ? count($block['result']['tx']) : 'not_available'];
}

function hobc_format_amount(mixed $amount): string
{
    if (!is_numeric($amount)) {
        return '0.00000000';
    }
    return number_format((float)$amount, 8, '.', '');
}

function hobc_reserve_ensure_description_table(?PDO $pdo = null): bool
{
    $pdo = $pdo ?? hobc_db();
    if (!$pdo instanceof PDO) {
        return false;
    }

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS reserve_spend_descriptions (
                txid VARCHAR(128) NOT NULL PRIMARY KEY,
                category VARCHAR(64) NULL,
                description TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by_admin_id INT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function hobc_reserve_spend_descriptions(array $txids = []): array
{
    $pdo = hobc_db();
    if (!$pdo instanceof PDO || !hobc_reserve_ensure_description_table($pdo)) {
        return [];
    }

    try {
        if ($txids === []) {
            $stmt = $pdo->query("SELECT txid, category, description, updated_at FROM reserve_spend_descriptions");
        } else {
            $txids = array_values(array_unique(array_filter(array_map('strval', $txids))));
            if ($txids === []) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($txids), '?'));
            $stmt = $pdo->prepare("SELECT txid, category, description, updated_at FROM reserve_spend_descriptions WHERE txid IN ($placeholders)");
            $stmt->execute($txids);
        }
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[(string)$row['txid']] = [
                'category' => (string)($row['category'] ?? ''),
                'description' => (string)($row['description'] ?? ''),
                'updated_at' => (string)($row['updated_at'] ?? ''),
            ];
        }
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

function hobc_reserve_save_spend_description(string $txid, string $category, string $description, ?int $adminId = null): bool
{
    $txid = strtolower(trim($txid));
    if (!preg_match('/^[a-f0-9]{64}$/', $txid)) {
        return false;
    }

    $categories = hobc_reserve_categories();
    $category = trim($category);
    if ($category !== '' && !array_key_exists($category, $categories)) {
        return false;
    }

    $description = trim($description);
    if (strlen($description) > 4000) {
        $description = substr($description, 0, 4000);
    }

    $pdo = hobc_db();
    if (!$pdo instanceof PDO || !hobc_reserve_ensure_description_table($pdo)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO reserve_spend_descriptions (txid, category, description, updated_by_admin_id)
             VALUES (?, NULLIF(?, ''), NULLIF(?, ''), ?)
             ON DUPLICATE KEY UPDATE
                category = VALUES(category),
                description = VALUES(description),
                updated_by_admin_id = VALUES(updated_by_admin_id)"
        );
        $stmt->execute([$txid, $category, $description, $adminId]);
        hobc_api_cache_delete('reserve_status');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function hobc_reserve_outgoing_transactions(string $walletName, int $limit = 1000): array
{
    $limit = max(1, min(5000, $limit));
    $list = hobc_rpc('listtransactions', ['*', $limit, 0, true], $walletName);
    if (!$list['ok']) {
        hobc_rpc('loadwallet', [$walletName]);
        $list = hobc_rpc('listtransactions', ['*', $limit, 0, true], $walletName);
    }
    if (!$list['ok'] || !is_array($list['result'])) {
        return [];
    }

    $grouped = [];
    foreach ($list['result'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $category = (string)($row['category'] ?? '');
        $amount = (float)($row['amount'] ?? 0);
        if ($category !== 'send' && $amount >= 0) {
            continue;
        }

        $txid = strtolower((string)($row['txid'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $txid)) {
            continue;
        }

        if (!isset($grouped[$txid])) {
            $grouped[$txid] = [
                'txid' => $txid,
                'amount' => 0.0,
                'fee' => isset($row['fee']) && is_numeric($row['fee']) ? abs((float)$row['fee']) : 0.0,
                'addresses' => [],
                'confirmations' => isset($row['confirmations']) ? (int)$row['confirmations'] : 0,
                'blockhash' => (string)($row['blockhash'] ?? ''),
                'blockheight' => isset($row['blockheight']) ? (int)$row['blockheight'] : 'not_available',
                'time' => isset($row['time']) ? (int)$row['time'] : 0,
                'timereceived' => isset($row['timereceived']) ? (int)$row['timereceived'] : 0,
                'abandoned' => !empty($row['abandoned']),
            ];
        }

        $grouped[$txid]['amount'] += abs($amount);
        if (isset($row['address']) && is_string($row['address']) && $row['address'] !== '') {
            $grouped[$txid]['addresses'][] = $row['address'];
        }
        if (isset($row['confirmations'])) {
            $grouped[$txid]['confirmations'] = max((int)$grouped[$txid]['confirmations'], (int)$row['confirmations']);
        }
        if (empty($grouped[$txid]['blockhash']) && !empty($row['blockhash'])) {
            $grouped[$txid]['blockhash'] = (string)$row['blockhash'];
        }
        if ($grouped[$txid]['blockheight'] === 'not_available' && isset($row['blockheight'])) {
            $grouped[$txid]['blockheight'] = (int)$row['blockheight'];
        }
        if (isset($row['time'])) {
            $grouped[$txid]['time'] = max((int)$grouped[$txid]['time'], (int)$row['time']);
        }
        if (isset($row['timereceived'])) {
            $grouped[$txid]['timereceived'] = max((int)$grouped[$txid]['timereceived'], (int)$row['timereceived']);
        }
    }

    $txids = array_keys($grouped);
    $descriptions = hobc_reserve_spend_descriptions($txids);
    $categories = hobc_reserve_categories();
    $transactions = [];
    foreach ($grouped as $txid => $row) {
        $meta = $descriptions[$txid] ?? [];
        $categoryKey = (string)($meta['category'] ?? '');
        $addresses = array_values(array_unique(array_filter($row['addresses'], 'is_string')));
        $transactions[] = [
            'txid' => $txid,
            'amount' => hobc_format_amount($row['amount']),
            'fee' => hobc_format_amount($row['fee']),
            'addresses' => $addresses,
            'address' => $addresses[0] ?? 'not_available',
            'confirmations' => (int)$row['confirmations'],
            'blockhash' => $row['blockhash'] !== '' ? $row['blockhash'] : 'not_available',
            'blockheight' => $row['blockheight'],
            'time' => $row['time'] > 0 ? (int)$row['time'] : 'not_available',
            'time_utc' => $row['time'] > 0 ? gmdate('Y-m-d H:i:s', (int)$row['time']) . ' UTC' : 'not_available',
            'abandoned' => (bool)$row['abandoned'],
            'reserve_category' => $categoryKey,
            'reserve_category_label' => isset($categories[$categoryKey]) ? (string)$categories[$categoryKey]['label'] : 'Uncategorized',
            'description' => (string)($meta['description'] ?? ''),
            'description_updated_at' => (string)($meta['updated_at'] ?? ''),
        ];
    }

    usort($transactions, static fn (array $a, array $b): int => (int)($b['time'] === 'not_available' ? 0 : $b['time']) <=> (int)($a['time'] === 'not_available' ? 0 : $a['time']));
    return $transactions;
}

function hobc_burn_status(): array
{
    $cached = hobc_api_cache_read('burn_status', 45);
    if ($cached !== null) {
        return $cached;
    }

    $scan = hobc_rpc('scantxoutset', ['start', ['addr(' . HOBC_BURN_ADDRESS . ')']]);
    if (!$scan['ok'] || !is_array($scan['result'])) {
        return hobc_status_payload('offline', 'Burn address scan is unavailable.', [
            'primary_burn_address' => HOBC_BURN_ADDRESS,
            'burn_script_pubkey' => HOBC_BURN_SCRIPT_PUBKEY,
            'burn_addresses' => 1,
            'total_burned' => 'not_available',
            'burn_transactions' => [],
        ]);
    }

    $result = $scan['result'];
    $unspents = is_array($result['unspents'] ?? null) ? $result['unspents'] : [];
    $transactions = [];
    foreach ($unspents as $row) {
        if (!is_array($row)) {
            continue;
        }
        $transactions[] = [
            'txid' => (string)($row['txid'] ?? ''),
            'vout' => isset($row['vout']) ? (int)$row['vout'] : 0,
            'amount' => hobc_format_amount($row['amount'] ?? 0),
            'height' => isset($row['height']) ? (int)$row['height'] : 'not_available',
        ];
    }

    $payload = hobc_status_payload('online', 'Burn address tracking is active.', [
        'primary_burn_address' => HOBC_BURN_ADDRESS,
        'burn_script_pubkey' => HOBC_BURN_SCRIPT_PUBKEY,
        'burn_addresses' => 1,
        'total_burned' => hobc_format_amount($result['total_amount'] ?? 0),
        'burn_transaction_count' => count($transactions),
        'burn_transactions' => $transactions,
        'scan_height' => isset($result['height']) ? (int)$result['height'] : 'not_available',
        'txouts_scanned' => isset($result['txouts']) ? (int)$result['txouts'] : 'not_available',
        'bestblock' => (string)($result['bestblock'] ?? 'not_available'),
        'data_basis' => 'Live scantxoutset scan of the configured HOBC burn address.',
    ]);
    hobc_api_cache_write('burn_status', $payload);
    return $payload;
}

function hobc_reserve_status(bool $forceRefresh = false): array
{
    $cached = $forceRefresh ? null : hobc_api_cache_read('reserve_status', 45);
    if ($cached !== null) {
        return $cached;
    }

    $walletName = hobc_reserve_wallet_name();
    $wallet = hobc_rpc('getwalletinfo', [], $walletName);
    if (!$wallet['ok']) {
        hobc_rpc('loadwallet', [$walletName]);
        $wallet = hobc_rpc('getwalletinfo', [], $walletName);
    }

    $currentBalance = 'not_available';
    $status = 'offline';
    $message = 'Launch reserve wallet balance is unavailable.';
    if ($wallet['ok'] && is_array($wallet['result'] ?? null)) {
        $trusted = (float)($wallet['result']['balance'] ?? 0);
        $unconfirmed = (float)($wallet['result']['unconfirmed_balance'] ?? 0);
        $currentBalance = hobc_format_amount($trusted + $unconfirmed);
        $status = 'online';
        $message = 'Launch reserve wallet balance from local HOBC node.';
    }

    $outgoingTransactions = $status === 'online' ? hobc_reserve_outgoing_transactions($walletName) : [];

    $payload = hobc_status_payload($status, $message, [
        'total_supply' => HOBC_TOTAL_SUPPLY,
        'launch_reserve' => HOBC_LAUNCH_RESERVE,
        'reserve_percent' => 10,
        'reserve_wallet' => $walletName,
        'reserve_addresses' => [HOBC_LAUNCH_RESERVE_ADDRESS],
        'primary_reserve_address' => HOBC_LAUNCH_RESERVE_ADDRESS,
        'current_balances' => $currentBalance,
        'outgoing_transaction_count' => count($outgoingTransactions),
        'outgoing_transactions' => $outgoingTransactions,
        'categories' => hobc_reserve_categories(),
        'data_basis' => 'Live getwalletinfo balance and listtransactions send history from the configured HOBC launch reserve wallet.',
    ]);
    if ($status === 'online') {
        hobc_api_cache_write('reserve_status', $payload);
    }
    return $payload;
}

function hobc_stats_supply_summary(): array
{
    $cached = hobc_api_cache_read('stats_supply_summary', 45);
    if ($cached !== null) {
        return $cached;
    }

    $info = hobc_rpc('getblockchaininfo');
    $height = ($info['ok'] && is_array($info['result'])) ? (int)($info['result']['blocks'] ?? 0) : null;
    $estimatedMinted = 'not_available';
    if ($height !== null && $height >= 1) {
        $estimatedMinted = number_format(8400000.0 + max(0, $height - 1) * HOBC_BLOCK_SUBSIDY, 8, '.', '');
    }

    $burn = hobc_burn_status();
    $reserve = hobc_reserve_status();
    $burnedSupply = $burn['total_burned'] ?? 'not_available';
    $circulatingSupply = 'not_available';
    if (is_numeric($estimatedMinted) && is_numeric($burnedSupply)) {
        $circulatingSupply = number_format(max(0.0, (float)$estimatedMinted - (float)$burnedSupply), 8, '.', '');
    }

    $payload = hobc_status_payload($height === null ? 'offline' : 'online', $height === null ? 'HOBC node RPC is unavailable.' : '', [
        'total_target_supply' => HOBC_TOTAL_SUPPLY,
        'launch_reserve' => HOBC_LAUNCH_RESERVE,
        'normal_mining_target' => HOBC_NORMAL_MINING_TARGET,
        'current_height' => $height ?? 'not_available',
        'estimated_minted_supply' => $estimatedMinted,
        'burned_supply' => $burnedSupply,
        'total_burned' => $burnedSupply,
        'reserve_balance' => $reserve['current_balances'] ?? 'not_available',
        'current_balances' => $reserve['current_balances'] ?? 'not_available',
        'circulating_supply' => $circulatingSupply,
        'data_basis' => $height === null ? 'not_available' : 'Block 1 reserve plus known 45 HOBC subsidy estimate minus live burn address scan. Reserve balance from launch reserve wallet.',
    ]);
    hobc_api_cache_write('stats_supply_summary', $payload);
    return $payload;
}
