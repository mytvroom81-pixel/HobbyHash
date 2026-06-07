<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/security_log.php';
require_once __DIR__ . '/../app/admin_view.php';
require_once __DIR__ . '/../app/admin_ops.php';

$admin = admin_require_user();
$msg = '';
$err = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_validate_or_fail();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'refresh_stats') {
            admin_audit((int)$admin['id'], 'refresh_explorer_stats_view', 'explorer', 'rpc');
            $msg = 'Explorer stats refreshed from current RPC/API data.';
        } elseif ($action === 'clear_explorer_cache') {
            $deleted = admin_ops_clear_api_cache();
            admin_audit((int)$admin['id'], 'clear_explorer_cache', 'api_cache', 'all', ['deleted_cache_files' => $deleted]);
            $msg = 'Explorer/API cache cleared.';
        } elseif ($action === 'rebuild_derived_stats') {
            $deleted = admin_ops_clear_api_cache();
            admin_audit((int)$admin['id'], 'rebuild_explorer_derived_stats', 'api_cache', 'all', ['deleted_cache_files' => $deleted]);
            $msg = 'Derived explorer stats cache cleared. Data will rebuild from node RPC.';
        } else {
            throw new RuntimeException('Unknown explorer admin action.');
        }
    } catch (Throwable $e) {
        wallet_log_error('explorer admin action failed: ' . $e->getMessage());
        $err = $e->getMessage();
    }
}

$nodeStatus = admin_ops_node_status();
$chain = $nodeStatus['chain'];
$explorerStatus = hobc_status_payload('offline', 'Explorer cannot read node status yet.');
try {
    $rpc = hobc_rpc('getblockchaininfo');
    if ($rpc['ok'] && is_array($rpc['result'])) {
        $result = $rpc['result'];
        $status = !empty($result['initialblockdownload']) ? 'syncing' : 'online';
        $explorerStatus = hobc_status_payload($status, $status === 'syncing' ? 'HOBC node is syncing. Explorer data may be incomplete.' : 'Basic HOBC explorer is online using local node RPC.', [
            'source' => 'local_rpc',
            'app_port' => 18765,
            'app_bind' => '127.0.0.1',
            'database' => 'hobbyhash_explorer',
            'indexed' => false,
            'chain_height' => $result['blocks'] ?? 'not_available',
            'headers' => $result['headers'] ?? 'not_available',
            'synced_height' => $result['blocks'] ?? 'not_available',
            'search_available' => $status !== 'syncing',
            'latest_blocks_available' => true,
            'latest_transactions_available' => true,
            'address_history_available' => false,
        ]);
    }
} catch (Throwable $e) {
    $explorerStatus = hobc_status_payload('offline', $e->getMessage());
}
$nodeHeight = is_numeric($chain['blocks'] ?? null) ? (int)$chain['blocks'] : null;
$indexedHeight = is_numeric($explorerStatus['synced_height'] ?? null) ? (int)$explorerStatus['synced_height'] : null;
$lag = ($nodeHeight !== null && $indexedHeight !== null) ? max(0, $nodeHeight - $indexedHeight) : 'not_available';
$latestBlocks = admin_ops_latest_blocks(15);
$latestTransactions = admin_ops_latest_transactions(20);
$failedJobs = [];
$logCandidates = [
    '/home/hobbyhashcoin/hobbyhash-clean/logs/explorer.log',
    '/home/hobbyhashcoin/hobbyhash-clean/wallet/logs/app_errors.log',
];
foreach ($logCandidates as $path) {
    if (!is_readable($path)) {
        continue;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach (array_slice($lines, -200) as $line) {
        if (stripos($line, 'explorer') !== false && preg_match('/fail|error|exception/i', $line)) {
            $failedJobs[] = [h(basename($path)), h($line)];
        }
    }
}

render_admin_header('Explorer Stats Admin', ['Explorer Stats']);
?>
<?php if ($msg): ?><?php admin_render_alert('success', $msg); ?><?php endif; ?>
<?php if ($err): ?><?php admin_render_alert('error', $err); ?><?php endif; ?>

<div class="admin-grid admin-grid-tight">
  <?= admin_stat_card('Indexer Status', (string)($explorerStatus['status'] ?? 'not_available'), in_array(($explorerStatus['status'] ?? ''), ['online', 'syncing'], true) ? 'ok' : 'warn') ?>
  <?= admin_stat_card('Indexed Height', admin_ops_fmt_int($indexedHeight ?? 'not_available'), 'info') ?>
  <?= admin_stat_card('Node Height', admin_ops_fmt_int($nodeHeight ?? 'not_available'), 'ok') ?>
  <?= admin_stat_card('Index Lag', admin_ops_fmt_int($lag), $lag === 0 ? 'ok' : 'warn') ?>
  <?= admin_stat_card('Latest Blocks', !empty($explorerStatus['latest_blocks_available']) ? 'Available' : 'Unavailable', !empty($explorerStatus['latest_blocks_available']) ? 'ok' : 'warn') ?>
  <?= admin_stat_card('Address History', !empty($explorerStatus['address_history_available']) ? 'Available' : 'Not indexed', !empty($explorerStatus['address_history_available']) ? 'ok' : 'warn') ?>
</div>

<div class="admin-card">
  <h3>Admin Controls</h3>
  <div class="admin-actions">
    <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button name="action" value="refresh_stats">Refresh stats</button></form>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button name="action" value="clear_explorer_cache" data-confirm="Clear explorer/API cache?">Clear explorer cache</button></form>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button name="action" value="rebuild_derived_stats" data-confirm="Clear derived explorer stats so they rebuild from RPC?">Rebuild derived stats</button></form>
  </div>
  <?= admin_ops_manual_command('Manual explorer reindex command', 'sudo systemctl restart hobbyhash-explorer') ?>
  <?= admin_ops_manual_command('Manual explorer service status', 'systemctl status hobbyhash-explorer --no-pager') ?>
</div>

<div class="admin-card">
  <h3>Explorer Status</h3>
  <?php admin_render_table(['Field', 'Value'], [
      ['Message', h((string)($explorerStatus['message'] ?? ''))],
      ['Source', h((string)($explorerStatus['source'] ?? 'not_available'))],
      ['App bind', h((string)($explorerStatus['app_bind'] ?? 'not_available'))],
      ['App port', h((string)($explorerStatus['app_port'] ?? 'not_available'))],
      ['Database label', h((string)($explorerStatus['database'] ?? 'not_available'))],
      ['Search available', !empty($explorerStatus['search_available']) ? '<span class="ok">Yes</span>' : '<span class="warn">No</span>'],
  ]); ?>
</div>

<div class="admin-card">
  <h3>Latest Blocks</h3>
  <?php admin_render_table(['Height', 'Hash', 'Time', 'TX Count'], array_map(static fn(array $row): array => [
      admin_explorer_link((string)$row['height'], (string)$row['height']),
      admin_hash_cell((string)$row['hash'], true),
      h((string)$row['time']),
      h((string)$row['tx_count']),
  ], $latestBlocks), 'No latest blocks', 'Latest blocks are unavailable from node RPC.'); ?>
</div>

<div class="admin-card">
  <h3>Latest Transactions</h3>
  <?php admin_render_table(['TXID', 'Height', 'Type', 'Time', 'Block'], array_map(static fn(array $row): array => [
      admin_hash_cell((string)$row['txid'], true),
      admin_explorer_link((string)$row['height'], (string)$row['height']),
      h((string)$row['type']),
      h((string)$row['time']),
      admin_hash_cell((string)$row['blockhash'], true),
  ], $latestTransactions), 'No latest transactions', 'Latest transactions are unavailable from node RPC.'); ?>
</div>

<div class="admin-card">
  <h3>Failed Index Jobs</h3>
  <?php admin_render_table(['Source', 'Log Line'], $failedJobs, 'No failed index jobs detected', 'No safely readable explorer failure logs were found.'); ?>
</div>

<?php render_admin_footer(); ?>
