<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/security_log.php';
require_once __DIR__ . '/../app/admin_view.php';
require_once __DIR__ . '/../app/admin_ops.php';

$admin = admin_require_user();
$requestedPool = (string)($_GET['pool'] ?? 'main');
$pool = in_array($requestedPool, ['main', 'nano'], true) ? $requestedPool : 'main';
$query = trim((string)($_GET['q'] ?? ''));
$paths = admin_ops_pool_paths();

function mining_pool_export_csv(array $stats, string $pool, int $adminId): void
{
    admin_audit($adminId, 'export_miner_share_stats_csv', 'mining_pool', $pool);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="hobc-' . $pool . '-miner-share-stats-' . gmdate('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    if ($out === false) {
        exit;
    }
    fputcsv($out, ['source', 'workername', 'accepted', 'rejected', 'reject_percent', 'best_share', 'last_share_time', 'hashrate_estimate']);
    foreach ((array)($stats['miner_leaderboard'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        fputcsv($out, [
            'leaderboard',
            $row['workername'] ?? '',
            $row['accepted_shares'] ?? '',
            $row['rejected_shares'] ?? '',
            $row['reject_percent'] ?? '',
            $row['best_share'] ?? '',
            $row['last_share_time'] ?? '',
            '',
        ]);
    }
    foreach ((array)($stats['miner_sessions'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        fputcsv($out, [
            'session',
            $row['workername'] ?? '',
            $row['session_accepted'] ?? '',
            $row['session_rejected'] ?? '',
            '',
            $row['session_best_share'] ?? '',
            $row['last_share_age_seconds'] ?? '',
            $row['session_hashrate_estimate'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$stats = admin_ops_pool_stats($pool, false);
if ((string)($_GET['export'] ?? '') === 'miners') {
    mining_pool_export_csv($stats, $pool, (int)$admin['id']);
}

$msg = '';
$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_validate_or_fail();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'pause_public_pool_stats' || $action === 'resume_public_pool_stats') {
            $paused = $action === 'pause_public_pool_stats';
            admin_setting_set('ops.pool_public_stats_paused', $paused, 'boolean', (int)$admin['id']);
            admin_audit((int)$admin['id'], $action, 'admin_settings', 'ops.pool_public_stats_paused', ['paused' => $paused]);
            $msg = $paused ? 'Public pool stats display paused.' : 'Public pool stats display resumed.';
        } elseif ($action === 'maintenance_notice') {
            $notice = substr(trim((string)($_POST['maintenance_notice'] ?? '')), 0, 2000);
            admin_setting_set('ops.maintenance_notice', $notice, 'text', (int)$admin['id']);
            admin_audit((int)$admin['id'], 'update_ops_maintenance_notice', 'admin_settings', 'ops.maintenance_notice', ['notice_set' => $notice !== '']);
            $msg = 'Admin maintenance notice saved.';
        } elseif ($action === 'refresh_stats') {
            admin_audit((int)$admin['id'], 'refresh_pool_stats_view', 'mining_pool', $pool);
            $msg = 'Pool stats refreshed from current collector/API data.';
        } else {
            throw new RuntimeException('Unknown mining pool admin action.');
        }
    } catch (Throwable $e) {
        wallet_log_error('mining pool admin action failed: ' . $e->getMessage());
        $err = $e->getMessage();
    }
    $stats = admin_ops_pool_stats($pool, false);
}

$leaderboard = array_values(array_filter((array)($stats['miner_leaderboard'] ?? []), static function ($row) use ($query): bool {
    if (!is_array($row)) {
        return false;
    }
    return $query === '' || stripos((string)($row['workername'] ?? ''), $query) !== false;
}));
$sessions = array_values(array_filter((array)($stats['miner_sessions'] ?? []), static function ($row) use ($query): bool {
    if (!is_array($row)) {
        return false;
    }
    return $query === '' || stripos((string)($row['workername'] ?? ''), $query) !== false;
}));
$latestShares = array_slice(array_values(array_filter((array)($stats['latest_shares'] ?? []), static fn($row): bool => is_array($row))), 0, 50);
$blocksFound = array_slice(array_values(array_filter((array)($stats['blocks_found'] ?? []), static fn($row): bool => is_array($row))), 0, 50);
$lastShare = is_array($stats['last_share'] ?? null) ? $stats['last_share'] : [];
$logRows = [];
$collectorLog = $paths[$pool]['collector_log'] ?? '';
if (is_readable($collectorLog)) {
    $lines = file($collectorLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach (array_slice($lines, -80) as $line) {
        $logRows[] = [h($line)];
    }
}

render_admin_header('Mining Pool Admin', ['Mining Pool']);
?>
<?php if ($msg): ?><?php admin_render_alert('success', $msg); ?><?php endif; ?>
<?php if ($err): ?><?php admin_render_alert('error', $err); ?><?php endif; ?>
<?php if (admin_ops_pool_public_paused()): ?><?php admin_render_alert('warning', 'Public pool stats display is paused from admin settings.'); ?><?php endif; ?>
<?php if (admin_ops_maintenance_notice() !== ''): ?><?php admin_render_alert('warning', admin_ops_maintenance_notice()); ?><?php endif; ?>

<div class="admin-card">
  <div class="admin-actions">
    <a class="admin-action <?= $pool === 'main' ? 'admin-action-primary' : 'admin-action-secondary' ?>" href="<?= h(admin_url('/mining-pool.php?pool=main')) ?>">Main Pool</a>
    <a class="admin-action <?= $pool === 'nano' ? 'admin-action-primary' : 'admin-action-secondary' ?>" href="<?= h(admin_url('/mining-pool.php?pool=nano')) ?>">Nano Pool</a>
    <a class="admin-action admin-action-secondary" href="<?= h(admin_url('/mining-pool.php?pool=' . rawurlencode($pool) . '&export=miners')) ?>">Export Miner/Share CSV</a>
    <a class="admin-action admin-action-secondary" href="/pool/<?= h($pool) ?>/">Public Pool Page</a>
  </div>
</div>

<div class="admin-grid admin-grid-tight">
  <?= admin_stat_card('Pool Status', (string)($stats['status'] ?? 'not_available'), ($stats['status'] ?? '') === 'online' ? 'ok' : 'warn') ?>
  <?= admin_stat_card('Stratum', (string)($stats['stratum_url'] ?? 'not_available'), 'info') ?>
  <?= admin_stat_card('Workers', admin_ops_fmt_int($stats['workers'] ?? $stats['seen_workers'] ?? 'not_available'), 'info') ?>
  <?= admin_stat_card('Hashrate', (string)($stats['hashrate'] ?? 'not_available'), 'ok') ?>
  <?= admin_stat_card('Accepted Shares', admin_ops_fmt_int($stats['accepted_shares'] ?? 'not_available'), 'ok') ?>
  <?= admin_stat_card('Rejected Shares', admin_ops_fmt_int($stats['rejected_shares'] ?? 'not_available'), 'warn') ?>
  <?= admin_stat_card('Reject %', (string)($stats['reject_percent'] ?? 'not_available'), 'warn') ?>
  <?= admin_stat_card('Best Share', (string)($stats['best_share'] ?? 'not_available'), 'ok') ?>
</div>

<div class="admin-card">
  <h3>Admin Controls</h3>
  <div class="admin-actions">
    <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button name="action" value="refresh_stats">Refresh stats</button></form>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button name="action" value="pause_public_pool_stats" data-confirm="Pause public pool stats display?">Pause public pool stats display</button></form>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button name="action" value="resume_public_pool_stats" data-confirm="Resume public pool stats display?">Resume public pool stats display</button></form>
  </div>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="maintenance_notice">
    <label>Admin Maintenance Notice<br><textarea name="maintenance_notice" rows="3" maxlength="2000"><?= h(admin_ops_maintenance_notice()) ?></textarea></label><br><br>
    <button type="submit">Save maintenance notice</button>
  </form>
  <?= admin_ops_manual_command('Manual collector refresh command', 'cd /home/hobbyhashcoin && python3 hobbyhash-clean/scripts/pool_stats_collector.py') ?>
</div>

<div class="admin-grid">
  <div class="admin-card">
    <h3>Network / Odds</h3>
    <?php admin_render_table(['Metric', 'Value'], [
        ['Current network difficulty', h((string)($stats['network_difficulty'] ?? 'not_available'))],
        ['Chain difficulty', h((string)($stats['chain_difficulty'] ?? 'not_available'))],
        ['Estimated time to hit', h((string)($stats['time_to_hit'] ?? 'not_available'))],
        ['Odds 1h', h((string)($stats['odds_1h'] ?? 'not_available'))],
        ['Odds 24h', h((string)($stats['odds_24h'] ?? 'not_available'))],
        ['Odds 7d', h((string)($stats['odds_7d'] ?? 'not_available'))],
        ['Best share progress', h((string)($stats['best_share_progress'] ?? 'not_available'))],
        ['Latest share vs network', h((string)($stats['latest_share_vs_network'] ?? 'not_available'))],
    ]); ?>
  </div>
  <div class="admin-card">
    <h3>Latest Share</h3>
    <?php admin_render_table(['Field', 'Value'], [
        ['Worker', h((string)($lastShare['workername'] ?? 'not_available'))],
        ['Share difficulty', h((string)($lastShare['share_difficulty'] ?? 'not_available'))],
        ['Assigned difficulty', h((string)($lastShare['difficulty'] ?? 'not_available'))],
        ['Hash', '<code>' . h((string)($lastShare['hash'] ?? 'not_available')) . '</code>'],
        ['Time', h((string)($lastShare['time'] ?? 'not_available'))],
    ]); ?>
  </div>
</div>

<div class="admin-card">
  <h3>Miner Search</h3>
  <form method="get" class="admin-filter-form">
    <input type="hidden" name="pool" value="<?= h($pool) ?>">
    <label>Worker name<input name="q" value="<?= h($query) ?>" placeholder="Search masked worker name"></label>
    <button type="submit">Search</button>
  </form>
</div>

<div class="admin-card">
  <h3>Connected Miners / Worker Names</h3>
  <?php admin_render_table(['Worker', 'Accepted', 'Rejected', 'Reject %', 'Best Share', 'Last Share'], array_map(static fn(array $row): array => [
      h((string)($row['workername'] ?? 'not_available')),
      h((string)($row['accepted_shares'] ?? '0')),
      h((string)($row['rejected_shares'] ?? '0')),
      h((string)($row['reject_percent'] ?? '0.0000%')),
      h((string)($row['best_share'] ?? '0')),
      h((string)($row['last_share_time'] ?? 'not_available')),
  ], $leaderboard), 'No miners found', 'No worker names or shares are available from the collector yet.'); ?>
</div>

<div class="admin-card">
  <h3>Active Miner Sessions</h3>
  <?php admin_render_table(['Worker', 'Accepted', 'Rejected', 'Best Share', 'Hashrate Estimate', 'Share Rate 5m', 'Started', 'Last Share Age'], array_map(static fn(array $row): array => [
      h((string)($row['workername'] ?? 'not_available')),
      h((string)($row['session_accepted'] ?? '0')),
      h((string)($row['session_rejected'] ?? '0')),
      h((string)($row['session_best_share'] ?? '0')),
      h((string)($row['session_hashrate_estimate'] ?? 'not_available')),
      h((string)($row['session_share_rate_5m'] ?? 'not_available')),
      h((string)($row['session_started_at'] ?? 'not_available')),
      h((string)($row['last_share_age_seconds'] ?? 'not_available')),
  ], $sessions), 'No active sessions', 'No active miner sessions are available from current share logs.'); ?>
</div>

<div class="admin-card">
  <h3>Recent Shares</h3>
  <?php admin_render_table(['Time', 'Worker', 'Result', 'Share Difficulty', 'Assigned Difficulty', 'Hash'], array_map(static fn(array $row): array => [
      h((string)($row['time'] ?? 'not_available')),
      h((string)($row['workername'] ?? 'not_available')),
      h((string)($row['result'] ?? 'not_available')),
      h((string)($row['share_difficulty'] ?? 'not_available')),
      h((string)($row['assigned_difficulty'] ?? 'not_available')),
      '<code>' . h(admin_ops_short((string)($row['hash'] ?? ''), 18, 10)) . '</code>',
  ], $latestShares), 'No share data', 'Recent shares will appear after the collector reads share logs.'); ?>
</div>

<div class="admin-card">
  <h3>Blocks Found / Payout Queue</h3>
  <?php admin_render_table(['Height', 'Hash', 'Status', 'Worker', 'Confirmations', 'Time', 'Payout TXID'], array_map(static fn(array $row): array => [
      h((string)($row['height'] ?? '')),
      '<code>' . h(admin_ops_short((string)($row['hash'] ?? ''), 18, 10)) . '</code>',
      h((string)($row['status'] ?? 'not_available')),
      h((string)($row['workername'] ?? 'not_available')),
      h((string)($row['confirmations'] ?? 'not_available')),
      h((string)($row['time'] ?? 'not_available')),
      '<code>' . h(admin_ops_short((string)($row['payout_txid'] ?? ''), 18, 10)) . '</code>',
  ], $blocksFound), 'No blocks found yet', 'Blocks found and payout queue rows will appear when the pool state/logs report them.'); ?>
</div>

<div class="admin-card">
  <h3>Pool Fees</h3>
  <?php admin_render_table(['Fee Type', 'Value'], [
      ['Pool mode', 'Solo only'],
      ['Public fee source', 'No configured fee field found in collector output'],
      ['Admin note', 'Do not enter or display payout private keys in this panel.'],
  ]); ?>
</div>

<div class="admin-card">
  <h3>Pool Logs</h3>
  <?php admin_render_table(['Recent safe log line'], $logRows, 'No safe pool logs readable', 'Collector logs were not readable or empty. Use Webmin Terminal for deeper service logs.'); ?>
</div>

<?php render_admin_footer(); ?>
