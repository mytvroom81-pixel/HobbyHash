<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

function hobc_stats_num(mixed $value, float $default = 0.0): float
{
    return is_numeric($value) ? (float)$value : $default;
}

function hobc_stats_int(mixed $value, int $default = 0): int
{
    return is_numeric($value) ? (int)$value : $default;
}

function hobc_stats_hashrate_text_to_hps(mixed $value): float
{
    return hobc_hashrate_to_hps($value);
}

function hobc_stats_iso_or_null(mixed $value): ?string
{
    if (!is_string($value) || $value === '' || $value === 'not_available') {
        return null;
    }
    $ts = strtotime($value);
    return $ts === false ? null : gmdate('c', $ts);
}

function hobc_stats_share_ts(array $share): int
{
    $time = $share['time'] ?? null;
    if (is_numeric($time)) {
        $ts = (int)$time;
        return $ts > 20000000000 ? intdiv($ts, 1000) : $ts;
    }
    if (is_string($time) && $time !== '' && $time !== 'not_available') {
        $ts = strtotime($time);
        return $ts === false ? 0 : $ts;
    }
    return 0;
}

function hobc_stats_window(array $windows, string $key): array
{
    $row = $windows[$key] ?? [];
    return is_array($row) ? $row : [];
}

function hobc_stats_session_by_worker(array $sessions): array
{
    $map = [];
    foreach ($sessions as $session) {
        if (!is_array($session)) {
            continue;
        }
        $worker = (string)($session['workername'] ?? '');
        if ($worker !== '') {
            $map[$worker] = $session;
        }
    }
    return $map;
}

function hobc_stats_recent_miner_fallbacks(array $shares): array
{
    $now = time();
    $windows = [
        5 => 300,
        60 => 3600,
        720 => 43200,
    ];
    $fallbacks = [];

    foreach ($shares as $share) {
        if (!is_array($share)) {
            continue;
        }
        $worker = (string)($share['workername'] ?? '');
        if ($worker === '') {
            continue;
        }
        if (!isset($fallbacks[$worker])) {
            $fallbacks[$worker] = [
                'last_accepted_share' => 0.0,
                'last_accepted_assigned_diff' => 0.0,
                'last_accepted_share_time' => null,
                'last_accepted_ts' => 0,
                'windows' => [],
            ];
            foreach ($windows as $minutes => $_seconds) {
                $fallbacks[$worker]['windows'][$minutes] = [
                    'accepted' => 0,
                    'diff_sum' => 0.0,
                ];
            }
        }

        $accepted = strtolower((string)($share['result'] ?? '')) === 'accepted'
            || ($share['accepted'] ?? false) === true;
        if (!$accepted) {
            continue;
        }

        $ts = hobc_stats_share_ts($share);
        $age = is_numeric($share['age_seconds'] ?? null)
            ? max(0, (int)$share['age_seconds'])
            : ($ts > 0 ? max(0, $now - $ts) : null);
        $shareDiff = hobc_stats_num($share['share_difficulty'] ?? ($share['sdiff'] ?? 0));
        $assignedDiff = hobc_stats_num($share['assigned_difficulty'] ?? ($share['diff'] ?? 0));

        if ($ts >= (int)$fallbacks[$worker]['last_accepted_ts']) {
            $fallbacks[$worker]['last_accepted_ts'] = $ts;
            $fallbacks[$worker]['last_accepted_share'] = $shareDiff;
            $fallbacks[$worker]['last_accepted_assigned_diff'] = $assignedDiff;
            $fallbacks[$worker]['last_accepted_share_time'] = $ts > 0 ? gmdate('c', $ts) : null;
        }

        foreach ($windows as $minutes => $seconds) {
            if ($age !== null && $age <= $seconds) {
                $fallbacks[$worker]['windows'][$minutes]['accepted']++;
                $fallbacks[$worker]['windows'][$minutes]['diff_sum'] += max(0.0, $shareDiff);
            }
        }
    }

    foreach ($fallbacks as &$fallback) {
        foreach ($windows as $minutes => $seconds) {
            $accepted = (int)$fallback['windows'][$minutes]['accepted'];
            $diffSum = (float)$fallback['windows'][$minutes]['diff_sum'];
            $fallback['windows'][$minutes]['hashrate_hs'] = $diffSum > 0
                ? ($diffSum * 4294967296.0) / $seconds
                : 0.0;
            $fallback['windows'][$minutes]['share_rate_per_min'] = $accepted / max(1, $seconds / 60);
        }
    }
    unset($fallback);

    return $fallbacks;
}

function hobc_stats_build_miners(array $poolStatus): array
{
    $leaderboard = is_array($poolStatus['miner_leaderboard'] ?? null) ? $poolStatus['miner_leaderboard'] : [];
    $sessions = is_array($poolStatus['miner_sessions'] ?? null) ? $poolStatus['miner_sessions'] : [];
    $sessionMap = hobc_stats_session_by_worker($sessions);
    $recentFallbacks = hobc_stats_recent_miner_fallbacks(is_array($poolStatus['latest_shares'] ?? null) ? $poolStatus['latest_shares'] : []);
    $now = time();
    $miners = [];

    foreach ($leaderboard as $miner) {
        if (!is_array($miner)) {
            continue;
        }
        $worker = (string)($miner['workername'] ?? 'not_available');
        $session = $sessionMap[$worker] ?? [];
        $lastAge = hobc_stats_int($miner['last_share_age_seconds'] ?? ($session['last_share_age_seconds'] ?? 999999), 999999);
        if ($lastAge > 10800) {
            continue;
        }
        $sessionStarted = hobc_stats_iso_or_null($session['session_started_at'] ?? null);
        $sessionAccepted = hobc_stats_int($session['session_accepted'] ?? 0);
        $sessionRejected = hobc_stats_int($session['session_rejected'] ?? 0);
        $allAccepted = hobc_stats_int($miner['accepted_shares'] ?? 0);
        $allRejected = hobc_stats_int($miner['rejected_shares'] ?? 0);
        $sessionRateHs = hobc_stats_hashrate_text_to_hps($session['session_hashrate_estimate'] ?? 0);
        $sessionRate5mHs = hobc_stats_hashrate_text_to_hps($session['session_hashrate_5m'] ?? 0);
        $sessionRate60mHs = hobc_stats_hashrate_text_to_hps($session['session_hashrate_60m'] ?? 0);
        $sessionRate12hHs = hobc_stats_hashrate_text_to_hps($session['session_hashrate_12h'] ?? 0);
        $fallback = $recentFallbacks[$worker] ?? [];
        $fallbackWindows = is_array($fallback['windows'] ?? null) ? $fallback['windows'] : [];
        if ($sessionRate5mHs <= 0 && isset($fallbackWindows[5]['hashrate_hs'])) {
            $sessionRate5mHs = (float)$fallbackWindows[5]['hashrate_hs'];
        }
        if ($sessionRate60mHs <= 0 && isset($fallbackWindows[60]['hashrate_hs'])) {
            $sessionRate60mHs = (float)$fallbackWindows[60]['hashrate_hs'];
        }
        if ($sessionRate12hHs <= 0 && isset($fallbackWindows[720]['hashrate_hs'])) {
            $sessionRate12hHs = (float)$fallbackWindows[720]['hashrate_hs'];
        }
        $sessionMinutes = 0.0;
        if ($sessionStarted !== null) {
            $startedTs = strtotime($sessionStarted);
            if ($startedTs !== false) {
                $sessionMinutes = max(1.0, ($now - $startedTs) / 60.0);
            }
        }
        $shareRate = $sessionMinutes > 0 ? $sessionAccepted / $sessionMinutes : 0.0;
        $shareRate5m = hobc_stats_num($session['session_share_rate_5m'] ?? 0);
        $shareRate60m = hobc_stats_num($session['session_share_rate_60m'] ?? 0);
        $shareRate12h = hobc_stats_num($session['session_share_rate_12h'] ?? 0);
        if ($shareRate5m <= 0 && isset($fallbackWindows[5]['share_rate_per_min'])) {
            $shareRate5m = (float)$fallbackWindows[5]['share_rate_per_min'];
        }
        if ($shareRate60m <= 0 && isset($fallbackWindows[60]['share_rate_per_min'])) {
            $shareRate60m = (float)$fallbackWindows[60]['share_rate_per_min'];
        }
        if ($shareRate12h <= 0 && isset($fallbackWindows[720]['share_rate_per_min'])) {
            $shareRate12h = (float)$fallbackWindows[720]['share_rate_per_min'];
        }
        $bestShare = max(hobc_stats_num($miner['best_share'] ?? 0), hobc_stats_num($session['session_best_share'] ?? 0));
        $lastShare = hobc_stats_num($session['session_last_accepted_share'] ?? ($miner['last_accepted_share'] ?? 0));
        $lastAssignedDiff = hobc_stats_num($session['session_last_accepted_assigned_diff'] ?? ($miner['last_accepted_assigned_diff'] ?? 0));
        $lastAcceptedTime = hobc_stats_iso_or_null($session['session_last_accepted_share_time'] ?? ($miner['last_accepted_share_time'] ?? null));
        if ($lastShare <= 0 && isset($fallback['last_accepted_share'])) {
            $lastShare = (float)$fallback['last_accepted_share'];
        }
        if ($lastAssignedDiff <= 0 && isset($fallback['last_accepted_assigned_diff'])) {
            $lastAssignedDiff = (float)$fallback['last_accepted_assigned_diff'];
        }
        if ($lastAcceptedTime === null && isset($fallback['last_accepted_share_time'])) {
            $lastAcceptedTime = hobc_stats_iso_or_null($fallback['last_accepted_share_time']);
        }
        $isActive = $lastAge <= 10800;

        $miners[] = [
            'worker' => $worker,
            'user' => $worker,
            'name' => $worker,
            'miner' => $worker,
            'users_seen' => [$worker],
            'full_worker' => $worker,
            'worker_alias' => $worker,
            'ip' => '',
            'is_active' => $isActive ? 1 : 0,
            'mining_idle_seconds' => $lastAge,
            'idle_seconds' => $lastAge,
            'session_idle_seconds' => $lastAge,
            'session_started' => $sessionStarted,
            'session_accepted_total' => $sessionAccepted,
            'session_rejected_total' => $sessionRejected,
            'session_best_share' => hobc_stats_num($session['session_best_share'] ?? 0),
            'session_reject_rate_pct' => rtrim((string)($session['session_reject_percent'] ?? '0'), '%'),
            'session_hashrate_accepted_hs' => $sessionRateHs,
            'session_hashrate_accepted_5m_hs' => $sessionRate5mHs,
            'session_hashrate_accepted_60m_hs' => $sessionRate60mHs,
            'session_hashrate_accepted_720m_hs' => $sessionRate12hHs,
            'session_share_rate_per_min' => $shareRate,
            'accepted_total' => $allAccepted,
            'rejected_total' => $allRejected,
            'reject_rate_total_pct' => rtrim((string)($miner['reject_percent'] ?? '0'), '%'),
            'best_share' => $bestShare,
            'last_share' => $lastShare,
            'last_accepted_share' => $lastShare,
            'last_accepted_share_time' => $lastAcceptedTime,
            'last_share_time' => hobc_stats_iso_or_null($miner['last_share_time'] ?? null),
            'last_seen' => hobc_stats_iso_or_null($miner['last_share_time'] ?? null),
            'last_assigned_diff' => $lastAssignedDiff,
            'windows' => [
                '5' => ['hashrate_accepted_hs' => $sessionRate5mHs, 'share_rate_per_min' => $shareRate5m],
                '60' => ['hashrate_accepted_hs' => $sessionRate60mHs, 'share_rate_per_min' => $shareRate60m],
                '720' => ['hashrate_accepted_hs' => $sessionRate12hHs, 'share_rate_per_min' => $shareRate12h],
            ],
        ];
    }

    usort($miners, static function (array $a, array $b): int {
        $ar = hobc_stats_num($a['session_hashrate_accepted_hs'] ?? 0);
        $br = hobc_stats_num($b['session_hashrate_accepted_hs'] ?? 0);
        if ($ar === $br) {
            return hobc_stats_int($b['accepted_total'] ?? 0) <=> hobc_stats_int($a['accepted_total'] ?? 0);
        }
        return $br <=> $ar;
    });

    return $miners;
}

function hobc_stats_build_recent_shares(array $poolStatus): array
{
    $shares = is_array($poolStatus['latest_shares'] ?? null) ? $poolStatus['latest_shares'] : [];
    $out = [];
    foreach ($shares as $share) {
        if (!is_array($share)) {
            continue;
        }
        $ts = hobc_stats_share_ts($share);
        $accepted = strtolower((string)($share['result'] ?? '')) === 'accepted';
        $out[] = [
            'time' => $ts > 0 ? gmdate('c', $ts) : null,
            '_display_time_ts' => $ts,
            '_time_ts' => $ts,
            'worker' => (string)($share['workername'] ?? 'not_available'),
            'sdiff' => hobc_stats_num($share['share_difficulty'] ?? 0),
            'diff' => hobc_stats_num($share['assigned_difficulty'] ?? 0),
            'accepted' => $accepted,
            'result' => $accepted ? 'accepted' : 'rejected',
            'hash' => (string)($share['hash'] ?? ''),
        ];
    }
    usort($out, static fn (array $a, array $b): int => hobc_stats_int($b['_time_ts'] ?? 0) <=> hobc_stats_int($a['_time_ts'] ?? 0));
    return $out;
}

function hobc_stats_build_blocks(array $poolStatus): array
{
    $blocks = is_array($poolStatus['blocks_found'] ?? null) ? $poolStatus['blocks_found'] : [];
    $out = [];
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $out[] = [
            'time' => hobc_stats_iso_or_null($block['time'] ?? null),
            'event_time' => hobc_stats_iso_or_null($block['event_time'] ?? ($block['time'] ?? null)),
            'block_time' => hobc_stats_iso_or_null($block['block_time'] ?? null),
            'height' => $block['height'] ?? null,
            'hash' => (string)($block['hash'] ?? ''),
            'status' => (string)($block['status'] ?? 'not_available'),
            'worker' => (string)($block['workername'] ?? 'not_available'),
        ];
    }
    return $out;
}

function hobc_stats_pool_model(string $pool = 'main'): array
{
    $poolStatus = hobc_pool_status($pool);
    $windows = is_array($poolStatus['graph_windows'] ?? null) ? $poolStatus['graph_windows'] : [];
    $w5 = hobc_stats_window($windows, '5m');
    $w30 = hobc_stats_window($windows, '30m');
    $w60 = hobc_stats_window($windows, '60m');
    $w720 = hobc_stats_window($windows, '12h');
    $accepted = hobc_stats_int($poolStatus['accepted_shares'] ?? 0);
    $rejected = hobc_stats_int($poolStatus['rejected_shares'] ?? 0);
    $bestShare = hobc_stats_num($poolStatus['best_share'] ?? 0);
    $lastShare = is_array($poolStatus['last_share'] ?? null) ? hobc_stats_num($poolStatus['last_share']['share_difficulty'] ?? 0) : 0.0;
    $networkDiff = hobc_stats_num($poolStatus['network_difficulty'] ?? 0);
    $poolHashrate = hobc_stats_hashrate_text_to_hps($poolStatus['hashrate'] ?? 0);
    $miners = hobc_stats_build_miners($poolStatus);
    $recentShares = hobc_stats_build_recent_shares($poolStatus);

    $poolWindows = [
        '5' => [
            'hashrate_accepted_hs' => hobc_stats_hashrate_text_to_hps($w5['hashrate_estimate'] ?? ($poolStatus['hashrate_5m'] ?? 0)),
            'share_rate_per_min' => hobc_stats_num($w5['accepted_per_minute'] ?? 0),
            'submitted_rate_per_min' => hobc_stats_num($w5['accepted_per_minute'] ?? 0) + hobc_stats_num($w5['rejected_per_minute'] ?? 0),
        ],
        '30' => [
            'hashrate_accepted_hs' => hobc_stats_hashrate_text_to_hps($w30['hashrate_estimate'] ?? 0),
            'share_rate_per_min' => hobc_stats_num($w30['accepted_per_minute'] ?? 0),
            'submitted_rate_per_min' => hobc_stats_num($w30['accepted_per_minute'] ?? 0) + hobc_stats_num($w30['rejected_per_minute'] ?? 0),
        ],
        '60' => [
            'hashrate_accepted_hs' => hobc_stats_hashrate_text_to_hps($w60['hashrate_estimate'] ?? ($poolStatus['hashrate_1hr'] ?? 0)),
            'share_rate_per_min' => hobc_stats_num($w60['accepted_per_minute'] ?? 0),
            'submitted_rate_per_min' => hobc_stats_num($w60['accepted_per_minute'] ?? 0) + hobc_stats_num($w60['rejected_per_minute'] ?? 0),
        ],
        '720' => [
            'hashrate_accepted_hs' => hobc_stats_hashrate_text_to_hps($w720['hashrate_estimate'] ?? ($poolStatus['hashrate_12hr'] ?? 0)),
            'share_rate_per_min' => hobc_stats_num($w720['accepted_per_minute'] ?? 0),
            'submitted_rate_per_min' => hobc_stats_num($w720['accepted_per_minute'] ?? 0) + hobc_stats_num($w720['rejected_per_minute'] ?? 0),
        ],
    ];

    $generatedAt = !empty($poolStatus['stats_collector_cache_stale'])
        ? (string)($poolStatus['updated_at'] ?? gmdate('c'))
        : (string)($poolStatus['stats_collected_at'] ?? ($poolStatus['updated_at'] ?? gmdate('c')));

    return [
        '_ok' => true,
        'stats_version' => 'hobc-stats-overload-v1',
        'generated_at' => $generatedAt,
        'session_gap_seconds' => hobc_stats_int($poolStatus['session_gap_seconds'] ?? 10800, 10800),
        'scope' => 'pool',
        'graph_scope' => 'whole_pool',
        'coin' => [
            'symbol' => 'HOBC',
            'name' => 'HobbyHash Coin',
            'network' => 'HobbyHash Coin',
            'algo' => 'SHA-256',
            'height' => $poolStatus['chain_height'] ?? null,
            'headers' => $poolStatus['chain_headers'] ?? null,
            'difficulty' => $networkDiff,
            'chain_difficulty' => hobc_stats_num($poolStatus['chain_difficulty'] ?? $networkDiff),
            'mining_difficulty' => hobc_stats_num($poolStatus['mining_difficulty'] ?? $networkDiff),
            'bestblockhash' => $poolStatus['bestblockhash'] ?? null,
            'block_reward' => HOBC_BLOCK_SUBSIDY,
            'blocks' => hobc_stats_build_blocks($poolStatus),
        ],
        'pool' => [
            'best_share' => $bestShare,
            'last_share' => $lastShare,
            'accepted_total' => $accepted,
            'rejected_total' => $rejected,
            'submitted_total' => $accepted + $rejected,
            'active_miners' => hobc_stats_int($poolStatus['workers'] ?? ($poolStatus['active_sessions'] ?? 0)),
            'miner_count' => hobc_stats_int($poolStatus['seen_workers'] ?? count($miners)),
            'active_sessions' => hobc_stats_int($poolStatus['active_sessions'] ?? 0),
            'hashrate_accepted_hs' => $poolHashrate,
            'hashrate_accepted_5m_hs' => hobc_stats_hashrate_text_to_hps($poolStatus['hashrate_5m'] ?? 0),
            'hashrate_accepted_60m_hs' => hobc_stats_hashrate_text_to_hps($poolStatus['hashrate_1hr'] ?? 0),
            'hashrate_accepted_720m_hs' => hobc_stats_hashrate_text_to_hps($poolStatus['hashrate_12hr'] ?? 0),
            'share_rate_per_min' => hobc_stats_num($w60['accepted_per_minute'] ?? 0),
            'submitted_rate_per_min' => hobc_stats_num($w60['accepted_per_minute'] ?? 0) + hobc_stats_num($w60['rejected_per_minute'] ?? 0),
            'reject_rate_total_pct' => ($accepted + $rejected) > 0 ? ($rejected / ($accepted + $rejected)) * 100 : 0,
            'reject_rate_recent_pct' => hobc_stats_num($poolStatus['reject_percent'] ?? 0),
            'windows' => $poolWindows,
        ],
        'miners' => $miners,
        'recent_shares' => $recentShares,
        'network' => [
            'ok' => ($poolStatus['status'] ?? '') === 'online',
            'chain' => $poolStatus['chain'] ?? 'main',
            'height' => $poolStatus['chain_height'] ?? null,
            'headers' => $poolStatus['chain_headers'] ?? null,
            'bestblockhash' => $poolStatus['bestblockhash'] ?? null,
            'difficulty' => $networkDiff,
            'chain_difficulty' => hobc_stats_num($poolStatus['chain_difficulty'] ?? $networkDiff),
            'mining_difficulty' => hobc_stats_num($poolStatus['mining_difficulty'] ?? $networkDiff),
            'networkhashps' => hobc_stats_num($poolStatus['networkhashps'] ?? 0),
            'verificationprogress' => is_string($poolStatus['verificationprogress'] ?? null)
                ? hobc_stats_num(str_replace('%', '', (string)$poolStatus['verificationprogress'])) / 100
                : hobc_stats_num($poolStatus['verificationprogress'] ?? 0),
            'initialblockdownload' => ($poolStatus['node_status'] ?? '') === 'syncing',
            'mempool_size' => $poolStatus['mempool_tx_count'] ?? null,
            'mempool_bytes' => $poolStatus['mempool_bytes'] ?? null,
            'size_on_disk' => $poolStatus['chain_size_on_disk'] ?? null,
            'pruned' => $poolStatus['pruned'] ?? false,
        ],
        '_source_status' => $poolStatus,
    ];
}
