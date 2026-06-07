<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/ledger.php';
require_once __DIR__ . '/../app/rpc.php';
require_once __DIR__ . '/../app/security_log.php';
require_once __DIR__ . '/../app/admin_view.php';

$admin = admin_require_user();
$pdo = wallet_db();
$settings = $pdo->query("SELECT * FROM wallet_settings WHERE id = 1")->fetch();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $maintenance = isset($_POST['maintenance_mode']) ? 1 : 0;
    $depositsPaused = isset($_POST['deposits_paused']) ? 1 : 0;
    $withdrawalsPaused = isset($_POST['withdrawals_paused']) ? 1 : 0;
    $scannerPaused = isset($_POST['scanner_paused']) ? 1 : 0;
    $upd = $pdo->prepare(
        "UPDATE wallet_settings
         SET maintenance_mode = ?, deposits_paused = ?, withdrawals_paused = ?, scanner_paused = ?
         WHERE id = 1"
    );
    $upd->execute([$maintenance, $depositsPaused, $withdrawalsPaused, $scannerPaused]);
    admin_audit((int)$admin['id'], 'update_wallet_flags', 'wallet_settings', '1', [
        'maintenance_mode' => $maintenance,
        'deposits_paused' => $depositsPaused,
        'withdrawals_paused' => $withdrawalsPaused,
        'scanner_paused' => $scannerPaused,
    ]);
    $settings = $pdo->query("SELECT * FROM wallet_settings WHERE id = 1")->fetch();
    $msg = 'Wallet operation flags updated.';
}

$liabilities = ledger_total_liabilities();
$scan = $pdo->query("SELECT * FROM chain_scan_state WHERE id = 1")->fetch();
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

render_admin_header('Wallet Operations');
?>
<?php if ($msg): ?><div class="card"><p class="ok"><?= h($msg) ?></p></div><?php endif; ?>
<div class="grid">
  <div class="card"><div>Liabilities</div><div class="metric"><?= h($liabilities) ?></div></div>
  <div class="card"><div>Trusted Hot Wallet</div><div class="metric"><?= h(number_format($hot['trusted'], 8, '.', '')) ?></div></div>
  <div class="card"><div>Untrusted Pending</div><div class="metric"><?= h(number_format($hot['untrusted_pending'], 8, '.', '')) ?></div></div>
  <div class="card"><div>Delta</div><div class="metric <?= $delta < 0 ? 'err' : 'ok' ?>"><?= h(number_format($delta, 8, '.', '')) ?></div></div>
</div>

<?php if ($rpcErr): ?><div class="card"><p class="err">RPC offline: <?= h($rpcErr) ?></p></div><?php endif; ?>

<div class="card">
  <h3>Operational Flags</h3>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <label><input type="checkbox" name="maintenance_mode" <?= ((int)$settings['maintenance_mode'] === 1) ? 'checked' : '' ?>> Maintenance mode</label><br>
    <label><input type="checkbox" name="deposits_paused" <?= ((int)$settings['deposits_paused'] === 1) ? 'checked' : '' ?>> Deposits paused</label><br>
    <label><input type="checkbox" name="withdrawals_paused" <?= ((int)$settings['withdrawals_paused'] === 1) ? 'checked' : '' ?>> Withdrawals paused</label><br>
    <label><input type="checkbox" name="scanner_paused" <?= ((int)$settings['scanner_paused'] === 1) ? 'checked' : '' ?>> Scanner paused</label><br><br>
    <button type="submit">Save Wallet Flags</button>
  </form>
</div>

<div class="card">
  <h3>Scanner</h3>
  <p>Status: <b><?= h((string)($scan['scanner_status'] ?? 'error')) ?></b></p>
  <p>Last scanned height: <?= h((string)($scan['last_scanned_height'] ?? '0')) ?></p>
  <?php if (!empty($scan['scanner_last_error'])): ?><p class="err"><?= h((string)$scan['scanner_last_error']) ?></p><?php endif; ?>
</div>
<?php render_admin_footer(); ?>
