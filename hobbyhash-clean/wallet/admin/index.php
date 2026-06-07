<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/ledger.php';
require_once __DIR__ . '/../app/rpc.php';
require_once __DIR__ . '/../app/site_status.php';
require_once __DIR__ . '/../app/admin_view.php';

$admin = admin_require_user();
$pdo = wallet_db();
$siteSettings = site_status_settings();
$settings = $pdo->query("SELECT * FROM wallet_settings WHERE id = 1")->fetch();
$scan = $pdo->query("SELECT * FROM chain_scan_state WHERE id = 1")->fetch();
$liabilities = ledger_total_liabilities();
$pendingWithdrawals = (int)$pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status IN ('pending','awaiting_approval','approved')")->fetchColumn();
$users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$adminEvents = $pdo->query("SELECT action, created_at FROM admin_audit_log ORDER BY id DESC LIMIT 8")->fetchAll();

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

render_admin_header('Control Center');
?>
<div class="grid">
  <div class="card"><div>Site Mode</div><div class="metric <?= $siteSettings['site_mode'] === 'full_launch' ? 'ok' : 'warn' ?>"><?= h(str_replace('_', ' ', (string)$siteSettings['site_mode'])) ?></div></div>
  <div class="card"><div>Wallet Liabilities</div><div class="metric"><?= h($liabilities) ?></div></div>
  <div class="card"><div>Hot Wallet Trusted</div><div class="metric"><?= h(number_format($hot['trusted'], 8, '.', '')) ?></div></div>
  <div class="card"><div>Hot Minus Liabilities</div><div class="metric <?= $delta < 0 ? 'err' : 'ok' ?>"><?= h(number_format($delta, 8, '.', '')) ?></div></div>
  <div class="card"><div>Users</div><div class="metric"><?= h((string)$users) ?></div></div>
  <div class="card"><div>Pending Withdrawals</div><div class="metric"><?= h((string)$pendingWithdrawals) ?></div></div>
  <div class="card"><div>Scanner</div><div class="metric <?= (($scan['scanner_status'] ?? '') === 'ok') ? 'ok' : 'warn' ?>"><?= h((string)($scan['scanner_status'] ?? 'error')) ?></div></div>
</div>

<?php if ($rpcErr): ?><div class="card"><p class="err">RPC issue: <?= h($rpcErr) ?></p></div><?php endif; ?>

<div class="card">
  <h3>Operations</h3>
  <p>Current live controls for the master admin panel.</p>
  <div class="admin-actions">
    <a class="button" href="<?= h(admin_url('/site-config.php')) ?>">Site Config</a>
    <a class="button" href="<?= h(admin_url('/wallet.php')) ?>">Wallet Ops</a>
    <a class="button" href="<?= h(admin_url('/withdrawals.php')) ?>">Withdrawals</a>
    <a class="button" href="<?= h(admin_url('/tickets.php')) ?>">Support Tickets</a>
    <a class="button" href="<?= h(admin_url('/smtp.php')) ?>">SMTP Settings</a>
    <a class="button" href="<?= h(admin_url('/audit.php')) ?>">Audit Log</a>
  </div>
</div>

<div class="card">
  <h3>Master Admin Modules</h3>
  <p>This is the master admin home. These cards show the control sections planned for the full HOBC command center. Only live modules should receive real buttons; future modules stay clearly marked until they are built.</p>
  <div class="grid">
    <div class="card module-card">
      <span class="module-status">Live</span>
      <h4>Site Control</h4>
      <p>Pre-launch, maintenance, full launch, bypass IP, public notices, legal visibility, and SMS feature toggles.</p>
      <div class="admin-actions"><a class="button" href="<?= h(admin_url('/site-config.php')) ?>">Open Site Config</a></div>
    </div>
    <div class="card module-card">
      <span class="module-status">Live</span>
      <h4>Wallet Control</h4>
      <p>Custodial wallet health, deposits, withdrawals, scanner status, liabilities, and support flow.</p>
      <div class="admin-actions"><a class="button" href="<?= h(admin_url('/wallet.php')) ?>">Open Wallet Ops</a></div>
    </div>
    <div class="card module-card">
      <span class="module-status">Planned</span>
      <h4>Pool Control</h4>
      <p>Main Pool and Nano Pool status, ports, start difficulty, workers, shares, and last block details.</p>
    </div>
    <div class="card module-card">
      <span class="module-status">Planned</span>
      <h4>Node Control</h4>
      <p>HOBC node RPC health, block height, peer count, mempool, sync status, and safe service notices.</p>
    </div>
    <div class="card module-card">
      <span class="module-status">Planned</span>
      <h4>Explorer Control</h4>
      <p>Explorer index status, latest block feed, address lookup health, reserve tracking, and burn tracking.</p>
    </div>
    <div class="card module-card">
      <span class="module-status">Live</span>
      <h4>Support Control</h4>
      <p>Public and wallet tickets, source sections, requester IP, logged-in user details, and email replies.</p>
      <div class="admin-actions"><a class="button" href="<?= h(admin_url('/tickets.php')) ?>">Open Tickets</a></div>
    </div>
  </div>
</div>

<div class="card">
  <h3>Recent Admin Events</h3>
  <table>
    <tr><th>Action</th><th>When</th></tr>
    <?php foreach ($adminEvents as $event): ?>
      <tr><td><?= h($event['action']) ?></td><td><?= h($event['created_at']) ?></td></tr>
    <?php endforeach; ?>
  </table>
</div>
<?php render_admin_footer(); ?>
