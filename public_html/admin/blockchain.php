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
            admin_audit((int)$admin['id'], 'refresh_blockchain_stats_view', 'blockchain', 'rpc');
            $msg = 'Blockchain stats refreshed from RPC.';
        } elseif ($action === 'rebuild_derived_stats') {
            $deleted = admin_ops_clear_api_cache();
            admin_audit((int)$admin['id'], 'rebuild_derived_blockchain_stats', 'api_cache', 'all', ['deleted_cache_files' => $deleted]);
            $msg = 'Derived stats cache cleared. Public APIs will rebuild from real RPC data.';
        } else {
            throw new RuntimeException('Unknown blockchain admin action.');
        }
    } catch (Throwable $e) {
        wallet_log_error('blockchain admin action failed: ' . $e->getMessage());
        $err = $e->getMessage();
    }
}

$status = admin_ops_node_status();
$chain = $status['chain'];
$network = $status['network'];
$mempool = $status['mempool'];
$hashps = 'not_available';
try {
    $hashps = rpc_call('getnetworkhashps', [], null);
} catch (Throwable $e) {
    $hashps = 'not_available';
}
$latestBlocks = admin_ops_latest_blocks(15);
$latestTransactions = admin_ops_latest_transactions(20);

render_admin_header('Blockchain Stats Admin', ['Blockchain Stats']);
?>
<?php if ($msg): ?><?php admin_render_alert('success', $msg); ?><?php endif; ?>
<?php if ($err): ?><?php admin_render_alert('error', $err); ?><?php endif; ?>
<?php if (!$status['online']): ?><?php admin_render_alert('warning', 'Blockchain RPC unavailable: ' . $status['error']); ?><?php endif; ?>

<div class="admin-grid admin-grid-tight">
  <?= admin_stat_card('Status', $status['online'] ? 'Online' : 'Offline', $status['online'] ? 'ok' : 'error') ?>
  <?= admin_stat_card('Block Height', admin_ops_fmt_int($chain['blocks'] ?? 'not_available'), 'ok') ?>
  <?= admin_stat_card('Headers', admin_ops_fmt_int($chain['headers'] ?? 'not_available'), 'ok') ?>
  <?= admin_stat_card('Difficulty', (string)($chain['difficulty'] ?? 'not_available'), 'info') ?>
  <?= admin_stat_card('Mempool TX', admin_ops_fmt_int($mempool['size'] ?? 'not_available'), 'warn') ?>
  <?= admin_stat_card('Peers', admin_ops_fmt_int($network['connections'] ?? 'not_available'), 'info') ?>
</div>

<div class="admin-card">
  <h3>Admin Controls</h3>
  <div class="admin-actions">
    <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button name="action" value="refresh_stats">Refresh stats</button></form>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button name="action" value="rebuild_derived_stats" data-confirm="Clear derived API cache so stats rebuild from RPC?">Rebuild derived stats</button></form>
  </div>
</div>

<div class="admin-card">
  <h3>Chain Verification</h3>
  <?php admin_render_table(['Field', 'Value'], [
      ['Best block hash', '<code>' . h((string)($chain['bestblockhash'] ?? 'not_available')) . '</code>'],
      ['Verification progress', h(isset($chain['verificationprogress']) ? admin_ops_fmt_number(((float)$chain['verificationprogress']) * 100, 4) . '%' : 'not_available')],
      ['Initial block download', !empty($chain['initialblockdownload']) ? '<span class="warn">Yes</span>' : '<span class="ok">No</span>'],
      ['Network hashrate', h(is_numeric($hashps) ? admin_ops_fmt_number($hashps, 2) . ' H/s' : 'not_available')],
      ['Median time', h((string)($chain['mediantime'] ?? 'not_available'))],
      ['Size on disk', h(admin_ops_bytes($chain['size_on_disk'] ?? null))],
      ['Pruned', isset($chain['pruned']) ? (!empty($chain['pruned']) ? '<span class="warn">Yes</span>' : '<span class="ok">No</span>') : 'not_available'],
  ]); ?>
</div>

<div class="admin-card">
  <h3>Latest Blocks</h3>
  <?php admin_render_table(['Height', 'Hash', 'Time', 'TX Count'], array_map(static fn(array $row): array => [
      h((string)$row['height']),
      '<code>' . h(admin_ops_short((string)$row['hash'], 18, 10)) . '</code>',
      h((string)$row['time']),
      h((string)$row['tx_count']),
  ], $latestBlocks), 'No latest blocks', 'Latest block data is unavailable from RPC.'); ?>
</div>

<div class="admin-card">
  <h3>Latest Transactions</h3>
  <?php admin_render_table(['TXID', 'Height', 'Type', 'Time', 'Block'], array_map(static fn(array $row): array => [
      '<code>' . h(admin_ops_short((string)$row['txid'], 18, 10)) . '</code>',
      h((string)$row['height']),
      h((string)$row['type']),
      h((string)$row['time']),
      '<code>' . h(admin_ops_short((string)$row['blockhash'], 18, 10)) . '</code>',
  ], $latestTransactions), 'No latest transactions', 'Latest transaction data is unavailable from RPC.'); ?>
</div>

<?php render_admin_footer(); ?>
