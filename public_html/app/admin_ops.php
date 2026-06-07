<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/rpc.php';
require_once __DIR__ . '/../api/_bootstrap.php';

function admin_ops_h(string $value): string
{
    return h($value);
}

function admin_ops_fmt_int(mixed $value): string
{
    return is_numeric($value) ? number_format((float)$value, 0) : (string)($value ?? 'not_available');
}

function admin_ops_fmt_number(mixed $value, int $decimals = 8): string
{
    return is_numeric($value) ? number_format((float)$value, $decimals, '.', '') : (string)($value ?? 'not_available');
}

function admin_ops_short(mixed $value, int $start = 16, int $end = 10): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }
    return strlen($text) > ($start + $end + 3) ? substr($text, 0, $start) . '...' . substr($text, -$end) : $text;
}

function admin_ops_bytes(mixed $bytes): string
{
    if (!is_numeric($bytes)) {
        return (string)($bytes ?? 'not_available');
    }
    $value = (float)$bytes;
    foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
        if ($value < 1024 || $unit === 'TB') {
            return number_format($value, $unit === 'B' ? 0 : 2) . ' ' . $unit;
        }
        $value /= 1024;
    }
    return 'not_available';
}

function admin_ops_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool)$stmt->fetchColumn();
}

function admin_ops_pool_stats(string $pool, bool $lite = false): array
{
    return hobc_pool_status($pool, $lite);
}

function admin_ops_pool_paths(): array
{
    return [
        'main' => [
            'label' => 'Main Pool',
            'stats_file' => '/home/hobbyhashcoin/hobbyhash-data/mainnet/pool-stats-main.json',
            'log_dir' => '/home/hobbyhashcoin/hobbyhash-logs/ckpool-main',
            'collector_log' => '/home/hobbyhashcoin/hobbyhash-clean/wallet/logs/pool_stats_collector.cron.log',
        ],
        'nano' => [
            'label' => 'Nano Pool',
            'stats_file' => '/home/hobbyhashcoin/hobbyhash-data/mainnet/pool-stats-nano.json',
            'log_dir' => '/home/hobbyhashcoin/hobbyhash-logs/ckpool-nano',
            'collector_log' => '/home/hobbyhashcoin/hobbyhash-clean/wallet/logs/pool_stats_collector.cron.log',
        ],
    ];
}

function admin_ops_pool_public_paused(): bool
{
    return admin_setting_bool('ops.pool_public_stats_paused', false);
}

function admin_ops_maintenance_notice(): string
{
    return (string)admin_setting_get('ops.maintenance_notice', '');
}

function admin_ops_clear_api_cache(?string $prefix = null): int
{
    $dir = HOBC_API_CACHE_DIR;
    if (!is_dir($dir) || !is_readable($dir)) {
        return 0;
    }
    $deleted = 0;
    foreach (glob(rtrim($dir, '/') . '/*.json') ?: [] as $path) {
        $base = basename($path);
        if ($prefix !== null && $prefix !== '' && !str_starts_with($base, $prefix)) {
            continue;
        }
        if (is_file($path) && @unlink($path)) {
            $deleted++;
        }
    }
    return $deleted;
}

function admin_ops_latest_blocks(int $limit = 10): array
{
    $info = hobc_rpc('getblockchaininfo');
    if (!$info['ok'] || !is_array($info['result'])) {
        return [];
    }
    $height = (int)($info['result']['blocks'] ?? 0);
    $blocks = [];
    for ($h = $height; $h > max(0, $height - $limit); $h--) {
        $hash = hobc_rpc('getblockhash', [$h]);
        if (!$hash['ok'] || !is_string($hash['result'])) {
            continue;
        }
        $block = hobc_rpc('getblock', [$hash['result'], 1]);
        if (!$block['ok'] || !is_array($block['result'])) {
            continue;
        }
        $blocks[] = [
            'height' => $h,
            'hash' => $hash['result'],
            'time' => $block['result']['time'] ?? 'not_available',
            'tx_count' => isset($block['result']['tx']) && is_array($block['result']['tx']) ? count($block['result']['tx']) : 'not_available',
        ];
    }
    return $blocks;
}

function admin_ops_latest_transactions(int $limit = 20): array
{
    $info = hobc_rpc('getblockchaininfo');
    if (!$info['ok'] || !is_array($info['result'])) {
        return [];
    }
    $height = (int)($info['result']['blocks'] ?? 0);
    $transactions = [];
    for ($h = $height; $h >= 0 && count($transactions) < $limit; $h--) {
        $hash = hobc_rpc('getblockhash', [$h]);
        if (!$hash['ok'] || !is_string($hash['result'])) {
            continue;
        }
        $block = hobc_rpc('getblock', [$hash['result'], 1]);
        if (!$block['ok'] || !is_array($block['result'])) {
            continue;
        }
        foreach ((array)($block['result']['tx'] ?? []) as $index => $txid) {
            if (!is_string($txid) || $txid === '') {
                continue;
            }
            $transactions[] = [
                'txid' => $txid,
                'height' => $h,
                'blockhash' => $hash['result'],
                'time' => $block['result']['time'] ?? 'not_available',
                'type' => $index === 0 ? 'coinbase' : 'transaction',
            ];
            if (count($transactions) >= $limit) {
                break 2;
            }
        }
    }
    return $transactions;
}

function admin_ops_peer_rows(): array
{
    try {
        $peers = rpc_call('getpeerinfo', [], null);
    } catch (Throwable $e) {
        return [];
    }
    if (!is_array($peers)) {
        return [];
    }
    return array_slice($peers, 0, 100);
}

function admin_ops_node_status(): array
{
    $status = [
        'online' => false,
        'error' => '',
        'chain' => [],
        'network' => [],
        'mempool' => [],
        'uptime' => null,
        'peers' => [],
    ];
    try {
        $status['chain'] = rpc_call('getblockchaininfo', [], null);
        $status['network'] = rpc_call('getnetworkinfo', [], null);
        $status['mempool'] = rpc_call('getmempoolinfo', [], null);
        try {
            $status['uptime'] = rpc_call('uptime', [], null);
        } catch (Throwable $e) {
            $status['uptime'] = null;
        }
        $status['peers'] = admin_ops_peer_rows();
        $status['online'] = true;
    } catch (Throwable $e) {
        $status['error'] = $e->getMessage();
    }
    return $status;
}

function admin_ops_manual_command(string $label, string $command): string
{
    return '<div class="admin-alert admin-alert-warning"><strong>' . h($label) . '</strong><br><code>' . h($command) . '</code></div>';
}
