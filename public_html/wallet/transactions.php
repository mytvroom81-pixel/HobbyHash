<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/view.php';

$user = auth_require_user();
$pdo = wallet_db();

$rows = [];
$deposits = $pdo->prepare(
    "SELECT d.id, d.txid, d.vout, d.amount, d.confirmations, d.status, d.created_at,
            da.label AS wallet_name, da.address
     FROM deposits d
     JOIN deposit_addresses da ON da.id = d.deposit_address_id
     WHERE d.user_id = ? AND d.credit_behavior = 'external'
     ORDER BY d.id DESC
     LIMIT 100"
);
$deposits->execute([(int)$user['id']]);
foreach ($deposits as $row) {
    $rows[] = [
        'sort_time' => (string)$row['created_at'],
        'type' => wallet_te('wallet.type.deposit', [], 'Deposit'),
        'amount' => (string)$row['amount'],
        'status' => (string)$row['status'],
        'txid' => (string)$row['txid'] . ':' . (string)$row['vout'],
        'confirmations' => wallet_display_confirmations((int)$row['confirmations']),
        'wallet' => wallet_display_receive_label((string)($row['wallet_name'] ?? '')),
        'address' => (string)$row['address'],
        'created_at' => (string)$row['created_at'],
    ];
}

$withdrawals = $pdo->prepare(
    "SELECT id, requested_address, requested_amount, fee_amount, status, txid, chain_confirmations, created_at
     FROM withdrawals
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 100"
);
$withdrawals->execute([(int)$user['id']]);
foreach ($withdrawals as $row) {
    $rows[] = [
        'sort_time' => (string)$row['created_at'],
        'type' => wallet_te('wallet.type.withdrawal', [], 'Withdrawal'),
        'amount' => '-' . number_format((float)$row['requested_amount'] + (float)$row['fee_amount'], 8, '.', ''),
        'status' => (string)$row['status'],
        'txid' => (string)($row['txid'] ?? ''),
        'confirmations' => wallet_display_confirmations((int)$row['chain_confirmations']),
        'wallet' => wallet_te('wallet.page.transactions.external_address', [], 'External address'),
        'address' => (string)$row['requested_address'],
        'created_at' => (string)$row['created_at'],
    ];
}

usort($rows, static function (array $a, array $b): int {
    return strcmp($b['sort_time'], $a['sort_time']);
});
$rows = array_slice($rows, 0, 100);
$txPage = max(1, (int)($_GET['tx_page'] ?? 1));
$txTotalPages = max(1, (int)ceil(count($rows) / 10));
$txPage = min($txPage, $txTotalPages);
$rowsPage = array_slice($rows, ($txPage - 1) * 10, 10);

render_header('wallet.page.transactions.title');
?>
<div class="card">
  <h3><?= h(wallet_te('wallet.page.transactions.title', [], 'Transactions')) ?></h3>
  <p><?= h(wallet_te('wallet.page.transactions.intro', [], 'Deposits show the receive wallet/address they came through. Withdrawals show the destination address.')) ?></p>
  <table>
    <tr><th><?= h(wallet_te('wallet.table.type', [], 'Type')) ?></th><th><?= h(wallet_te('wallet.table.amount', [], 'Amount')) ?></th><th><?= h(wallet_te('wallet.table.status', [], 'Status')) ?></th><th><?= h(wallet_te('wallet.table.wallet_address', [], 'Wallet / Address')) ?></th><th><?= h(wallet_te('wallet.table.txid', [], 'TXID')) ?></th><th><?= h(wallet_te('wallet.table.confs', [], 'Confs')) ?></th><th><?= h(wallet_te('wallet.table.when', [], 'When')) ?></th><th><?= h(wallet_te('wallet.table.details', [], 'Details')) ?></th></tr>
    <?php foreach ($rowsPage as $row): ?>
      <tr>
        <td><?= h($row['type']) ?></td>
        <td><?= h($row['amount']) ?></td>
        <td><?= h($row['status']) ?></td>
        <td><?= h($row['wallet']) ?><br><small><?= wallet_address_text($row['address']) ?></small></td>
        <td><?= wallet_value_chip($row['txid']) ?></td>
        <td><?= h((string)$row['confirmations']) ?></td>
        <td><?= h($row['created_at']) ?></td>
        <td><button type="button" class="small-button tx-detail-button" data-transaction="<?= h(json_encode($row, JSON_UNESCAPED_SLASHES)) ?>"><?= h(wallet_te('wallet.table.view', [], 'View')) ?></button></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rowsPage): ?>
      <tr><td colspan="8"><?= h(wallet_te('wallet.page.transactions.empty', [], 'No transactions found yet.')) ?></td></tr>
    <?php endif; ?>
  </table>
  <?= wallet_pagination('/transactions.php', 'tx_page', $txPage, $txTotalPages) ?>
</div>

<div class="modal-backdrop" id="transaction-modal" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="transaction-modal-title">
    <div class="modal-header">
      <div>
        <span class="eyebrow"><?= h(wallet_te('wallet.page.transactions.wallet_tx', [], 'Wallet transaction')) ?></span>
        <h3 id="transaction-modal-title"><?= h(wallet_te('wallet.page.transactions.details', [], 'Transaction Details')) ?></h3>
      </div>
      <button type="button" class="small-button modal-close" data-close-modal><?= h(wallet_te('wallet.button.close', [], 'Close')) ?></button>
    </div>
    <div class="detail-grid" id="transaction-detail-grid"></div>
  </div>
</div>

<script>
(() => {
  const i18n = <?= wallet_js_i18n([
    'wallet.not_available' => 'Not available',
    'wallet.table.type' => 'Type',
    'wallet.table.amount' => 'Amount',
    'wallet.table.status' => 'Status',
    'wallet.table.wallet' => 'Wallet',
    'wallet.table.address' => 'Address',
    'wallet.table.txid' => 'TXID',
    'wallet.table.confirmations' => 'Confirmations',
    'wallet.table.when' => 'When',
  ]) ?>;
  const t = (key) => i18n[key] || key;
  const modal = document.getElementById('transaction-modal');
  const grid = document.getElementById('transaction-detail-grid');
  const esc = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
  const row = (label, value) => `<div class="detail-row"><span>${esc(label)}</span><span>${esc(value || t('wallet.not_available'))}</span></div>`;
  const openModal = (data) => {
    if (!modal || !grid) return;
    grid.innerHTML = [
      row(t('wallet.table.type'), data.type),
      row(t('wallet.table.amount'), data.amount),
      row(t('wallet.table.status'), data.status),
      row(t('wallet.table.wallet'), data.wallet),
      row(t('wallet.table.address'), data.address),
      row(t('wallet.table.txid'), data.txid),
      row(t('wallet.table.confirmations'), data.confirmations),
      row(t('wallet.table.when'), data.created_at),
    ].join('');
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
  };
  document.querySelectorAll('.tx-detail-button').forEach((button) => {
    button.addEventListener('click', () => {
      try {
        openModal(JSON.parse(button.dataset.transaction || '{}'));
      } catch (_) {}
    });
  });
  document.querySelectorAll('[data-close-modal]').forEach((button) => {
    button.addEventListener('click', () => {
      modal?.classList.remove('is-open');
      modal?.setAttribute('aria-hidden', 'true');
    });
  });
  modal?.addEventListener('click', (event) => {
    if (event.target === modal) {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    }
  });
})();
</script>
<?php render_footer(); ?>
