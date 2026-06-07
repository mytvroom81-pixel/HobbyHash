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
            admin_audit((int)$admin['id'], 'refresh_node_stats_view', 'node', 'rpc');
            $msg = 'Node stats refreshed from RPC.';
        } elseif ($action === 'maintenance_notice') {
            $notice = substr(trim((string)($_POST['maintenance_notice'] ?? '')), 0, 2000);
            admin_setting_set('ops.maintenance_notice', $notice, 'text', (int)$admin['id']);
            admin_audit((int)$admin['id'], 'update_ops_maintenance_notice', 'admin_settings', 'ops.maintenance_notice', ['notice_set' => $notice !== '']);
            $msg = 'Admin maintenance notice saved.';
        } else {
            throw new RuntimeException('Unknown node admin action.');
        }
    } catch (Throwable $e) {
        wallet_log_error('node admin action failed: ' . $e->getMessage());
        $err = $e->getMessage();
    }
}

$status = admin_ops_node_status();
$chain = $status['chain'];
$network = $status['network'];
$mempool = $status['mempool'];
$peers = $status['peers'];
$inbound = count(array_filter($peers, static fn(array $peer): bool => !empty($peer['inbound'])));
$outbound = count($peers) - $inbound;
$diskPath = '/home/hobbyhashcoin';
$diskFree = @disk_free_space($diskPath);
$diskTotal = @disk_total_space($diskPath);

render_admin_header('Node Admin', ['Nodes']);
?>
<?php if ($msg): ?><?php admin_render_alert('success', $msg); ?><?php endif; ?>
<?php if ($err): ?><?php admin_render_alert('error', $err); ?><?php endif; ?>
<?php if (!$status['online']): ?><?php admin_render_alert('warning', 'Node RPC unavailable: ' . $status['error']); ?><?php endif; ?>
<?php if (admin_ops_maintenance_notice() !== ''): ?><?php admin_render_alert('warning', admin_ops_maintenance_notice()); ?><?php endif; ?>

<div class="admin-grid admin-grid-tight">
  <?= admin_stat_card('Node Status', $status['online'] ? 'Online' : 'Offline', $status['online'] ? 'ok' : 'error') ?>
  <?= admin_stat_card('Block Height', admin_ops_fmt_int($chain['blocks'] ?? 'not_available'), 'ok') ?>
  <?= admin_stat_card('Peer Count', admin_ops_fmt_int($network['connections'] ?? count($peers)), 'info') ?>
  <?= admin_stat_card('Inbound Peers', admin_ops_fmt_int($inbound), 'info') ?>
  <?= admin_stat_card('Outbound Peers', admin_ops_fmt_int($outbound), 'info') ?>
  <?= admin_stat_card('Mempool Count', admin_ops_fmt_int($mempool['size'] ?? 'not_available'), 'warn') ?>
</div>

<div class="admin-card">
  <h3>Admin Controls</h3>
  <div class="admin-actions">
    <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button name="action" value="refresh_stats">Refresh stats</button></form>
  </div>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="maintenance_notice">
    <label>Admin Maintenance Notice<br><textarea name="maintenance_notice" rows="3" maxlength="2000"><?= h(admin_ops_maintenance_notice()) ?></textarea></label><br><br>
    <button type="submit">Save maintenance notice</button>
  </form>
  <?= admin_ops_manual_command('Manual node service check', 'systemctl status hobbyhashd --no-pager') ?>
</div>

<div class="admin-grid">
  <div class="admin-card">
    <h3>Node RPC Status</h3>
    <?php admin_render_table(['Field', 'Value'], [
        ['RPC status', $status['online'] ? '<span class="ok">Online</span>' : '<span class="err">Offline</span>'],
        ['Best block hash', '<code>' . h((string)($chain['bestblockhash'] ?? 'not_available')) . '</code>'],
        ['Network difficulty', h((string)($chain['difficulty'] ?? 'not_available'))],
        ['Verification progress', h(isset($chain['verificationprogress']) ? admin_ops_fmt_number(((float)$chain['verificationprogress']) * 100, 4) . '%' : 'not_available')],
        ['Initial block download', !empty($chain['initialblockdownload']) ? '<span class="warn">Yes</span>' : '<span class="ok">No</span>'],
        ['Version', h((string)($network['subversion'] ?? ($network['version'] ?? 'not_available')))],
        ['Uptime', h(is_numeric($status['uptime']) ? admin_ops_fmt_int($status['uptime']) . ' seconds' : 'not_available')],
    ]); ?>
  </div>
  <div class="admin-card">
    <h3>Disk Usage</h3>
    <?php admin_render_table(['Field', 'Value'], [
        ['Path', h($diskPath)],
        ['Free', h(admin_ops_bytes($diskFree))],
        ['Total', h(admin_ops_bytes($diskTotal))],
        ['Chain size on disk', h(admin_ops_bytes($chain['size_on_disk'] ?? null))],
        ['Pruned', isset($chain['pruned']) ? (!empty($chain['pruned']) ? '<span class="warn">Yes</span>' : '<span class="ok">No</span>') : 'not_available'],
    ]); ?>
  </div>
</div>

<div class="admin-card">
  <h3>Peers</h3>
  <?php admin_filter_box('Filter peers'); ?>
  <?php admin_render_table(['Address', 'Direction', 'Version', 'Subver', 'Starting Height', 'Synced Headers', 'Ping'], array_map(static fn(array $peer): array => [
      h((string)($peer['addr'] ?? 'not_available')),
      !empty($peer['inbound']) ? 'Inbound' : 'Outbound',
      h((string)($peer['version'] ?? '')),
      h((string)($peer['subver'] ?? '')),
      h((string)($peer['startingheight'] ?? '')),
      h((string)($peer['synced_headers'] ?? '')),
      h((string)($peer['pingtime'] ?? '')),
  ], $peers), 'No peer data', 'Peer data is unavailable from RPC.'); ?>
</div>

<?php render_admin_footer(); ?>
