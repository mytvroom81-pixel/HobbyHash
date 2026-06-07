<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/ledger.php';
require_once __DIR__ . '/../app/rpc.php';
require_once __DIR__ . '/../app/view.php';

$user = auth_require_user();
$settings = wallet_db()->query("SELECT * FROM wallet_settings WHERE id = 1")->fetch();
if ((int)$settings['maintenance_mode'] === 1) {
    wallet_redirect(wallet_url('/maintenance.php'));
}

$pdo = wallet_db();
$scan = $pdo->query("SELECT * FROM chain_scan_state WHERE id = 1")->fetch();
$balance = ledger_user_balance((int)$user['id']);

$pendingDeposits = $pdo->prepare("SELECT COUNT(*) c FROM deposits WHERE user_id = ? AND credit_behavior = 'external' AND status IN ('detected','confirming')");
$pendingDeposits->execute([(int)$user['id']]);
$pdCount = (int)$pendingDeposits->fetch()['c'];

$pendingWithdrawals = $pdo->prepare("SELECT COUNT(*) c FROM withdrawals WHERE user_id = ? AND status IN ('pending','awaiting_approval','approved','broadcasted','confirming')");
$pendingWithdrawals->execute([(int)$user['id']]);
$pwCount = (int)$pendingWithdrawals->fetch()['c'];

$receive = $pdo->prepare(
    "SELECT id, label, address
     FROM deposit_addresses
     WHERE user_id = ? AND is_active = 1
     ORDER BY id ASC
     LIMIT 1"
);
$receive->execute([(int)$user['id']]);
$primaryReceive = $receive->fetch();

$recentRows = [];
$deposits = $pdo->prepare(
    "SELECT d.txid, d.vout, d.amount, d.confirmations, d.status, d.created_at,
            da.label AS wallet_name, da.address
     FROM deposits d
     JOIN deposit_addresses da ON da.id = d.deposit_address_id
     WHERE d.user_id = ? AND d.credit_behavior = 'external'
     ORDER BY d.id DESC
     LIMIT 10"
);
$deposits->execute([(int)$user['id']]);
foreach ($deposits as $row) {
    $recentRows[] = [
        'sort_time' => (string)$row['created_at'],
        'type' => 'Deposit',
        'amount' => (string)$row['amount'],
        'status' => (string)$row['status'],
        'detail' => wallet_display_receive_label((string)($row['wallet_name'] ?? '')),
        'txid' => (string)$row['txid'] . ':' . (string)$row['vout'],
    ];
}

$withdrawals = $pdo->prepare(
    "SELECT requested_address, requested_amount, fee_amount, status, txid, created_at
     FROM withdrawals
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 10"
);
$withdrawals->execute([(int)$user['id']]);
foreach ($withdrawals as $row) {
    $recentRows[] = [
        'sort_time' => (string)$row['created_at'],
        'type' => 'Withdrawal',
        'amount' => '-' . number_format((float)$row['requested_amount'] + (float)$row['fee_amount'], 8, '.', ''),
        'status' => (string)$row['status'],
        'detail' => (string)$row['requested_address'],
        'txid' => (string)($row['txid'] ?? ''),
    ];
}
usort($recentRows, static function (array $a, array $b): int {
    return strcmp($b['sort_time'], $a['sort_time']);
});
$recentRows = array_slice($recentRows, 0, 10);

$rpcOnline = rpc_is_online();

if (isset($_GET['live']) && $_GET['live'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'balance' => $balance,
        'pending_deposits' => $pdCount,
        'pending_withdrawals' => $pwCount,
        'rpc_online' => $rpcOnline,
        'scanner_status' => (string)($scan['scanner_status'] ?? 'error'),
        'last_scanned_height' => (int)($scan['last_scanned_height'] ?? 0),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

render_header('Dashboard');
?>
<div class="card">
  <h3>Welcome, <?= h($user['username']) ?></h3>
  <p><b>Total HOBC Balance:</b> <span id="live-balance"><?= h($balance) ?></span></p>
  <p>Pending deposits: <span id="live-pd"><?= h((string)$pdCount) ?></span> | Pending withdrawals: <span id="live-pw"><?= h((string)$pwCount) ?></span></p>
  <p>
    <a href="<?= h(wallet_url('/deposit.php')) ?>">Receive</a> |
    <a href="<?= h(wallet_url('/withdraw.php')) ?>">Withdraw</a> |
    <a href="<?= h(wallet_url('/transactions.php')) ?>">Transactions</a>
  </p>
</div>

<div class="card">
  <h3>Primary Receive Wallet</h3>
  <?php if ($primaryReceive): ?>
    <p><b><?= h(wallet_display_receive_label((string)($primaryReceive['label'] ?? ''))) ?></b></p>
    <p><?= wallet_address_text((string)$primaryReceive['address']) ?></p>
    <div class="qr-actions">
      <button type="button" class="small-button show-dashboard-qr" data-address-id="<?= h((string)$primaryReceive['id']) ?>" data-address="<?= h((string)$primaryReceive['address']) ?>" data-label="<?= h(wallet_display_receive_label((string)($primaryReceive['label'] ?? ''))) ?>">Show QR</button>
      <a class="button" href="<?= h(wallet_url('/deposit.php?address_id=' . (int)$primaryReceive['id'])) ?>">Manage receive wallets</a>
    </div>
  <?php else: ?>
    <p>No receive wallet has been created yet.</p>
    <p><a href="<?= h(wallet_url('/deposit.php')) ?>">Create receive wallet</a></p>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Recent Activity</h3>
  <table>
    <tr><th>Type</th><th>Amount</th><th>Status</th><th>Wallet / Address</th><th>TXID</th></tr>
    <?php foreach ($recentRows as $row): ?>
      <tr>
        <td><?= h($row['type']) ?></td>
        <td><?= h($row['amount']) ?></td>
        <td><?= h($row['status']) ?></td>
        <td><?= str_starts_with((string)$row['detail'], 'hobc') ? wallet_address_text((string)$row['detail']) : h($row['detail']) ?></td>
        <td><?= wallet_value_chip((string)$row['txid']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$recentRows): ?>
      <tr><td colspan="5">No activity yet.</td></tr>
    <?php endif; ?>
  </table>
</div>

<div class="card">
  <h3>Wallet Status</h3>
  <p>Backend: <?php if ($rpcOnline): ?><span class="ok" id="live-rpc-status">online</span><?php else: ?><span class="err" id="live-rpc-status">offline</span><?php endif; ?></p>
  <p>Scanner: <span id="live-scanner-status" class="<?= (($scan['scanner_status'] ?? '') === 'ok') ? 'ok' : 'warn' ?>"><?= h((string)($scan['scanner_status'] ?? 'error')) ?></span></p>
  <p>Last scanned height: <span id="live-height"><?= h((string)($scan['last_scanned_height'] ?? '0')) ?></span></p>
</div>

<?php if ($primaryReceive): ?>
<div class="modal-backdrop" id="dashboard-qr-modal" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="dashboard-qr-title">
    <div class="modal-header">
      <div>
        <span class="eyebrow">Primary receive wallet</span>
        <h3 id="dashboard-qr-title">Receive QR Code</h3>
      </div>
      <button type="button" class="small-button modal-close" data-close-dashboard-qr>Close</button>
    </div>
    <p id="dashboard-qr-label"></p>
    <div class="qr-code-wrap"><img id="dashboard-qr-image" src="" alt="Primary receive address QR code"></div>
    <p class="qr-address" id="dashboard-qr-address"></p>
  </div>
</div>
<?php endif; ?>
<script>
(() => {
  const esc = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
  const addressText = (value) => `<span class="address-full">${esc(value || 'Not available')}</span>`;
  const qrModal = document.getElementById('dashboard-qr-modal');
  const qrImage = document.getElementById('dashboard-qr-image');
  const qrLabel = document.getElementById('dashboard-qr-label');
  const qrAddress = document.getElementById('dashboard-qr-address');
  document.querySelectorAll('.show-dashboard-qr').forEach((button) => {
    button.addEventListener('click', () => {
      if (!qrModal || !qrImage || !qrLabel || !qrAddress) return;
      qrLabel.innerHTML = `<b>${esc(button.dataset.label || 'Primary receive address')}</b>`;
      qrAddress.innerHTML = addressText(button.dataset.address || '');
      qrImage.src = `qr.php?address_id=${encodeURIComponent(button.dataset.addressId || '')}`;
      qrModal.classList.add('is-open');
      qrModal.setAttribute('aria-hidden', 'false');
    });
  });
  document.querySelectorAll('[data-close-dashboard-qr]').forEach((button) => {
    button.addEventListener('click', () => {
      qrModal?.classList.remove('is-open');
      qrModal?.setAttribute('aria-hidden', 'true');
      if (qrImage) qrImage.src = '';
    });
  });
  qrModal?.addEventListener('click', (event) => {
    if (event.target === qrModal) {
      qrModal.classList.remove('is-open');
      qrModal.setAttribute('aria-hidden', 'true');
      if (qrImage) qrImage.src = '';
    }
  });
  const setText = (id, value) => {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  };
  const refresh = async () => {
    try {
      const res = await fetch('dashboard.php?live=1', { credentials: 'same-origin', cache: 'no-store' });
      if (!res.ok) return;
      const data = await res.json();
      setText('live-balance', data.balance ?? '0.00000000');
      setText('live-pd', String(data.pending_deposits ?? 0));
      setText('live-pw', String(data.pending_withdrawals ?? 0));
      setText('live-height', String(data.last_scanned_height ?? 0));
      const rpc = document.getElementById('live-rpc-status');
      if (rpc) {
        rpc.textContent = data.rpc_online ? 'online' : 'offline';
        rpc.className = data.rpc_online ? 'ok' : 'err';
      }
      const scanner = document.getElementById('live-scanner-status');
      if (scanner) {
        scanner.textContent = String(data.scanner_status ?? 'error');
        scanner.className = data.scanner_status === 'ok' ? 'ok' : 'warn';
      }
    } catch (_) {}
  };
  setInterval(refresh, 10000);
})();
</script>
<?php render_footer(); ?>
