<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/ledger.php';
require_once __DIR__ . '/../app/rpc.php';
require_once __DIR__ . '/../app/view.php';

$user = auth_require_user();
$settings = wallet_db()->query("SELECT * FROM wallet_settings WHERE id = 1")->fetch();
if ((int)$settings['maintenance_mode'] === 1) {
    wallet_redirect(wallet_url('/maintenance.php'));
}

function wallet_default_receive_label(int $userId): string
{
    return 'Wallet ' . $userId;
}

function wallet_clean_receive_label(string $label): string
{
    $label = trim($label);
    if ($label === '') {
        return 'Receive Wallet';
    }
    return substr($label, 0, 80);
}

$err = '';
$ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $action = (string)($_POST['action'] ?? 'generate');
    try {
        if ($action === 'generate') {
            if ((int)$settings['deposits_paused'] === 1) {
                $err = wallet_te('wallet.error.deposits_paused', [], 'Deposits are currently paused.');
            } else {
                $name = wallet_clean_receive_label((string)($_POST['label'] ?? ''));
                $rpcLabel = 'user_' . (int)$user['id'] . '_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', strtolower($name)) . '_' . time();
                $address = (string)rpc_call('getnewaddress', [$rpcLabel, 'bech32'], wallet_config()['rpc']['wallet']);
                $stmt = wallet_db()->prepare(
                    "INSERT INTO deposit_addresses (user_id, address, label, address_role, is_active)
                     VALUES (?, ?, ?, 'sub', 1)"
                );
                $stmt->execute([(int)$user['id'], $address, $name]);
                $ok = wallet_te('wallet.success.receive_wallet_created', [], 'Receive wallet created.');
            }
        } elseif ($action === 'rename') {
            $addressId = (int)($_POST['address_id'] ?? 0);
            $name = wallet_clean_receive_label((string)($_POST['label'] ?? ''));
            $stmt = wallet_db()->prepare("UPDATE deposit_addresses SET label = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $addressId, (int)$user['id']]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException(wallet_te('wallet.error.receive_wallet_not_found', [], 'Receive wallet not found.'));
            }
            $ok = wallet_te('wallet.success.receive_wallet_renamed', [], 'Receive wallet name updated.');
        } else {
            $err = wallet_te('wallet.error.unknown_receive_action', [], 'Unknown receive wallet action.');
        }
    } catch (Throwable $e) {
        wallet_log_error('receive wallet action failed: ' . $e->getMessage());
        if ($err === '') {
            $err = wallet_te('wallet.error.receive_wallet_action_failed', [], 'Receive wallet action failed.');
        }
    }
}

$addressSql = "
    SELECT
        da.id,
        da.address,
        da.label,
        da.assigned_at,
        COALESCE(SUM(CASE WHEN d.credit_behavior = 'external' AND d.status = 'credited' THEN d.amount ELSE 0 END), 0) AS total_received,
        COALESCE(SUM(CASE WHEN d.credit_behavior = 'external' AND d.status IN ('detected','confirming') THEN d.amount ELSE 0 END), 0) AS pending_received,
        COUNT(CASE WHEN d.credit_behavior = 'external' THEN d.id END) AS deposit_count
    FROM deposit_addresses da
    LEFT JOIN deposits d ON d.deposit_address_id = da.id
    WHERE da.user_id = ? AND da.is_active = 1
    GROUP BY da.id
    ORDER BY da.id ASC
    LIMIT 100";
$addrs = wallet_db()->prepare($addressSql);
$addrs->execute([(int)$user['id']]);
$addrRowsAll = $addrs->fetchAll();

if (!$addrRowsAll && (int)$settings['deposits_paused'] !== 1) {
    try {
        $name = wallet_default_receive_label((int)$user['id']);
        $rpcLabel = 'user_' . (int)$user['id'] . '_primary_' . time();
        $address = (string)rpc_call('getnewaddress', [$rpcLabel, 'bech32'], wallet_config()['rpc']['wallet']);
        $stmt = wallet_db()->prepare(
            "INSERT INTO deposit_addresses (user_id, address, label, address_role, is_active)
             VALUES (?, ?, ?, 'main', 1)"
        );
        $stmt->execute([(int)$user['id'], $address, $name]);
        $addrs->execute([(int)$user['id']]);
        $addrRowsAll = $addrs->fetchAll();
    } catch (Throwable $e) {
        wallet_log_error('auto receive wallet create failed: ' . $e->getMessage());
    }
}

$walletBalance = ledger_user_balance((int)$user['id']);
$addressPage = max(1, (int)($_GET['addr_page'] ?? 1));
$addressTotalPages = max(1, (int)ceil(count($addrRowsAll) / 10));
$addressPage = min($addressPage, $addressTotalPages);
$addrRowsPage = array_slice($addrRowsAll, ($addressPage - 1) * 10, 10);
$detailsAddressId = (int)($_GET['address_id'] ?? 0);
$selectedAddress = null;
foreach ($addrRowsAll as $row) {
    if ((int)$row['id'] === $detailsAddressId) {
        $selectedAddress = $row;
        break;
    }
}
if (!$selectedAddress && $addrRowsAll) {
    $selectedAddress = $addrRowsAll[0];
    $detailsAddressId = (int)$selectedAddress['id'];
}

$detailsRows = [];
$detailsPage = max(1, (int)($_GET['detail_page'] ?? 1));
$detailsTotal = 0;
$detailsTotalPages = 1;
if ($detailsAddressId > 0) {
    $detailsCount = wallet_db()->prepare(
        "SELECT COUNT(*)
         FROM deposits
         WHERE user_id = ? AND deposit_address_id = ? AND credit_behavior = 'external'"
    );
    $detailsCount->execute([(int)$user['id'], $detailsAddressId]);
    $detailsTotal = (int)$detailsCount->fetchColumn();
    $detailsTotalPages = max(1, (int)ceil($detailsTotal / 10));
    $detailsPage = min($detailsPage, $detailsTotalPages);
    $detailsOffset = ($detailsPage - 1) * 10;
    $details = wallet_db()->prepare(
        "SELECT txid, vout, amount, confirmations, status, created_at
         FROM deposits
         WHERE user_id = ? AND deposit_address_id = ? AND credit_behavior = 'external'
         ORDER BY id DESC
         LIMIT 10 OFFSET {$detailsOffset}"
    );
    $details->execute([(int)$user['id'], $detailsAddressId]);
    $detailsRows = $details->fetchAll();
}

$transactionsByAddress = [];
foreach ($addrRowsAll as $row) {
    $transactionsByAddress[(int)$row['id']] = [
        'label' => wallet_display_receive_label((string)($row['label'] ?? '')),
        'address' => (string)$row['address'],
        'transactions' => [],
    ];
}
if ($addrRowsAll) {
    $addressIds = array_map(static fn(array $row): int => (int)$row['id'], $addrRowsAll);
    $placeholders = implode(',', array_fill(0, count($addressIds), '?'));
    $allDetails = wallet_db()->prepare(
        "SELECT deposit_address_id, txid, vout, amount, confirmations, status, created_at
         FROM deposits
         WHERE user_id = ? AND credit_behavior = 'external' AND deposit_address_id IN ($placeholders)
         ORDER BY id DESC
         LIMIT 250"
    );
    $allDetails->execute(array_merge([(int)$user['id']], $addressIds));
    foreach ($allDetails as $row) {
        $addressId = (int)$row['deposit_address_id'];
        if (!isset($transactionsByAddress[$addressId])) {
            continue;
        }
        $transactionsByAddress[$addressId]['transactions'][] = [
            'txid' => (string)$row['txid'] . ':' . (string)$row['vout'],
            'amount' => (string)$row['amount'],
            'confirmations' => wallet_display_confirmations((int)$row['confirmations']),
            'status' => (string)$row['status'],
            'created_at' => (string)$row['created_at'],
        ];
    }
}

if (isset($_GET['live']) && $_GET['live'] === '1') {
    header('Content-Type: application/json');
    $addrRows = [];
    foreach ($addrRowsPage as $row) {
        $addrRows[] = [
            'id' => (int)$row['id'],
            'address' => (string)$row['address'],
            'label' => wallet_display_receive_label((string)($row['label'] ?? '')),
            'edit_label' => wallet_edit_receive_label((string)($row['label'] ?? '')),
            'assigned_at' => (string)$row['assigned_at'],
            'total_received' => number_format((float)$row['total_received'], 8, '.', ''),
            'pending_received' => number_format((float)$row['pending_received'], 8, '.', ''),
            'deposit_count' => (int)$row['deposit_count'],
        ];
    }
    echo json_encode([
        'wallet_balance' => $walletBalance,
        'addresses' => $addrRows,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

render_header('wallet.page.deposit.title');
?>
<div class="card">
  <h3><?= h(wallet_te('wallet.page.deposit.title', [], 'Receive HOBC')) ?></h3>
  <p><b><?= h(wallet_te('wallet.page.deposit.total_balance', [], 'Total Wallet Balance:')) ?></b> <span id="live-wallet-balance"><?= h($walletBalance) ?></span> <?= h(wallet_te('wallet.currency_suffix', [], 'HOBC')) ?></p>
  <p><?= h(wallet_te('wallet.page.deposit.intro', [], 'Receive wallets are labels for organizing incoming payments. They do not have separate spendable balances.')) ?></p>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="generate">
    <label><?= h(wallet_te('wallet.page.deposit.new_wallet_name', [], 'New Receive Wallet Name')) ?><br><input name="label" maxlength="80" placeholder="<?= h(wallet_te('wallet.page.deposit.name_placeholder', [], 'Mining, Savings, Exchange deposits')) ?>"></label>
    <button type="submit"><?= h(wallet_te('wallet.page.deposit.create_wallet', [], 'Create receive wallet')) ?></button>
  </form>
</div>

<div class="card">
  <h3><?= h(wallet_te('wallet.page.deposit.wallets_heading', [], 'Your Receive Wallets')) ?></h3>
  <table>
    <tr><th><?= h(wallet_te('wallet.table.name', [], 'Name')) ?></th><th><?= h(wallet_te('wallet.table.address', [], 'Address')) ?></th><th><?= h(wallet_te('wallet.table.total_received', [], 'Total Received')) ?></th><th><?= h(wallet_te('wallet.table.pending', [], 'Pending')) ?></th><th><?= h(wallet_te('wallet.table.deposits', [], 'Deposits')) ?></th><th><?= h(wallet_te('wallet.table.actions', [], 'Actions')) ?></th></tr>
    <tbody id="live-addresses-tbody">
    <?php foreach ($addrRowsPage as $row): ?>
      <tr>
        <td>
          <span class="wallet-name-cell">
            <strong><?= h(wallet_display_receive_label((string)($row['label'] ?? ''))) ?></strong>
            <button type="button" class="small-button edit-wallet-button" data-address-id="<?= h((string)$row['id']) ?>" data-label="<?= h(wallet_edit_receive_label((string)($row['label'] ?? ''))) ?>"><?= h(wallet_te('wallet.button.edit', [], 'Edit')) ?></button>
          </span>
        </td>
        <td>
          <?= wallet_address_text((string)$row['address']) ?>
          <button type="button" class="small-button qr-small-button show-qr-button" data-address-id="<?= h((string)$row['id']) ?>" data-address="<?= h((string)$row['address']) ?>" data-label="<?= h(wallet_display_receive_label((string)($row['label'] ?? ''))) ?>"><?= h(wallet_te('wallet.button.qr', [], 'QR')) ?></button>
        </td>
        <td><?= h(number_format((float)$row['total_received'], 8, '.', '')) ?></td>
        <td><?= h(number_format((float)$row['pending_received'], 8, '.', '')) ?></td>
        <td><?= h((string)$row['deposit_count']) ?></td>
        <td><button type="button" class="small-button view-wallet-transactions" data-address-id="<?= h((string)$row['id']) ?>"><?= h(wallet_te('wallet.page.deposit.view_transactions', [], 'View transactions')) ?></button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= wallet_pagination('/deposit.php', 'addr_page', $addressPage, $addressTotalPages, ['address_id' => $detailsAddressId, 'detail_page' => $detailsPage]) ?>
</div>

<div class="modal-backdrop" id="edit-wallet-modal" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="edit-wallet-title">
    <div class="modal-header">
      <div>
        <span class="eyebrow"><?= h(wallet_te('wallet.page.deposit.eyebrow', [], 'Receive wallet')) ?></span>
        <h3 id="edit-wallet-title"><?= h(wallet_te('wallet.page.deposit.edit_name', [], 'Edit Wallet Name')) ?></h3>
      </div>
      <button type="button" class="small-button modal-close" data-close-modal><?= h(wallet_te('wallet.button.close', [], 'Close')) ?></button>
    </div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="rename">
      <input type="hidden" name="address_id" id="edit-wallet-address-id">
      <label><?= h(wallet_te('wallet.page.deposit.wallet_name', [], 'Wallet Name')) ?><br><input name="label" id="edit-wallet-label" maxlength="80" required></label>
      <button type="submit"><?= h(wallet_te('wallet.page.deposit.save_wallet_name', [], 'Save Wallet Name')) ?></button>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="receive-transactions-modal" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="receive-transactions-title">
    <div class="modal-header">
      <div>
        <span class="eyebrow"><?= h(wallet_te('wallet.page.deposit.eyebrow', [], 'Receive wallet')) ?></span>
        <h3 id="receive-transactions-title"><?= h(wallet_te('wallet.page.deposit.transactions_heading', [], 'Wallet Transactions')) ?></h3>
      </div>
      <button type="button" class="small-button modal-close" data-close-modal><?= h(wallet_te('wallet.button.close', [], 'Close')) ?></button>
    </div>
    <div class="detail-grid" id="receive-wallet-summary"></div>
    <div id="receive-wallet-transactions"></div>
  </div>
</div>

<div class="modal-backdrop" id="qr-code-modal" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="qr-code-title">
    <div class="modal-header">
      <div>
        <span class="eyebrow"><?= h(wallet_te('wallet.page.deposit.eyebrow', [], 'Receive wallet')) ?></span>
        <h3 id="qr-code-title"><?= h(wallet_te('wallet.page.deposit.qr_title', [], 'Receive QR Code')) ?></h3>
      </div>
      <button type="button" class="small-button modal-close" data-close-modal><?= h(wallet_te('wallet.button.close', [], 'Close')) ?></button>
    </div>
    <p id="qr-code-label"></p>
    <div class="qr-code-wrap"><img id="qr-code-image" src="" alt="<?= h(wallet_te('wallet.page.deposit.qr_alt', [], 'Receive address QR code')) ?>"></div>
    <p class="qr-address" id="qr-code-address"></p>
  </div>
</div>

<?php if ($selectedAddress): ?>
<div class="card">
  <h3><?= h(wallet_te('wallet.page.deposit.transactions_for', ['name' => wallet_display_receive_label((string)($selectedAddress['label'] ?? ''))], 'Transactions for {name}')) ?></h3>
  <p><?= wallet_address_text((string)$selectedAddress['address']) ?></p>
  <table>
    <tr><th><?= h(wallet_te('wallet.table.txid', [], 'TXID')) ?></th><th><?= h(wallet_te('wallet.table.amount', [], 'Amount')) ?></th><th><?= h(wallet_te('wallet.table.confs_short', [], 'Confs')) ?></th><th><?= h(wallet_te('wallet.table.status', [], 'Status')) ?></th><th><?= h(wallet_te('wallet.table.detected', [], 'Detected')) ?></th></tr>
    <?php foreach ($detailsRows as $row): ?>
      <tr>
        <td><?= wallet_value_chip((string)$row['txid'] . ':' . (string)$row['vout']) ?></td>
        <td><?= h($row['amount']) ?></td>
        <td><?= h(wallet_display_confirmations((int)$row['confirmations'])) ?></td>
        <td><?= h($row['status']) ?></td>
        <td><?= h($row['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$detailsRows): ?>
      <tr><td colspan="5"><?= h(wallet_te('wallet.page.deposit.no_deposits', [], 'No external deposits found for this receive wallet yet.')) ?></td></tr>
    <?php endif; ?>
  </table>
  <?= wallet_pagination('/deposit.php', 'detail_page', $detailsPage, $detailsTotalPages, ['address_id' => $detailsAddressId, 'addr_page' => $addressPage]) ?>
</div>
<?php endif; ?>

<script>
(() => {
  const i18n = <?= wallet_js_i18n([
    'wallet.not_available' => 'Not available',
    'wallet.table.wallet' => 'Wallet',
    'wallet.table.address' => 'Address',
    'wallet.table.transactions' => 'Transactions',
    'wallet.page.deposit.no_deposits' => 'No external deposits found for this receive wallet yet.',
    'wallet.table.txid' => 'TXID',
    'wallet.table.amount' => 'Amount',
    'wallet.table.confs_short' => 'Confs',
    'wallet.table.status' => 'Status',
    'wallet.table.detected' => 'Detected',
    'wallet.page.deposit.receive_address' => 'Receive address',
    'wallet.button.edit' => 'Edit',
    'wallet.button.qr' => 'QR',
    'wallet.page.deposit.view_transactions' => 'View transactions',
  ]) ?>;
  const editModal = document.getElementById('edit-wallet-modal');
  const editId = document.getElementById('edit-wallet-address-id');
  const editLabel = document.getElementById('edit-wallet-label');
  const receiveTxModal = document.getElementById('receive-transactions-modal');
  const receiveSummary = document.getElementById('receive-wallet-summary');
  const receiveTransactions = document.getElementById('receive-wallet-transactions');
  const qrModal = document.getElementById('qr-code-modal');
  const qrImage = document.getElementById('qr-code-image');
  const qrLabel = document.getElementById('qr-code-label');
  const qrAddress = document.getElementById('qr-code-address');
  const transactionsByAddress = <?= json_encode($transactionsByAddress, JSON_UNESCAPED_SLASHES) ?>;
  const esc = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
  const shortText = (value) => {
    const text = String(value || '');
    return text.length > 25 ? `${text.slice(0, 12)}...${text.slice(-10)}` : text;
  };
  const chip = (value) => {
    const text = String(value || '');
    return `<span class="hash-chip" title="${esc(text)}">${esc(shortText(text))}</span>`;
  };
  const addressText = (value) => `<span class="address-full">${esc(value || i18n['wallet.not_available'])}</span>`;
  const detailRow = (label, value) => `<div class="detail-row"><span>${esc(label)}</span><span>${value}</span></div>`;
  const openEditModal = (id, label) => {
    if (!editModal || !editId || !editLabel) return;
    editId.value = id;
    editLabel.value = label;
    editModal.classList.add('is-open');
    editModal.setAttribute('aria-hidden', 'false');
    editLabel.focus();
  };
  const openReceiveTransactions = (id) => {
    const wallet = transactionsByAddress[String(id)];
    if (!wallet || !receiveTxModal || !receiveSummary || !receiveTransactions) return;
    receiveSummary.innerHTML = [
      detailRow(i18n['wallet.table.wallet'], esc(wallet.label)),
      detailRow(i18n['wallet.table.address'], addressText(wallet.address)),
      detailRow(i18n['wallet.table.transactions'], esc(String(wallet.transactions.length))),
    ].join('');
    if (!wallet.transactions.length) {
      receiveTransactions.innerHTML = `<p>${esc(i18n['wallet.page.deposit.no_deposits'])}</p>`;
    } else {
      receiveTransactions.innerHTML = `<table class="modal-table">
        <tr><th>${esc(i18n['wallet.table.txid'])}</th><th>${esc(i18n['wallet.table.amount'])}</th><th>${esc(i18n['wallet.table.confs_short'])}</th><th>${esc(i18n['wallet.table.status'])}</th><th>${esc(i18n['wallet.table.detected'])}</th></tr>
        ${wallet.transactions.map((tx) => `<tr>
          <td>${chip(tx.txid)}</td>
          <td>${esc(tx.amount)}</td>
          <td>${esc(tx.confirmations)}</td>
          <td>${esc(tx.status)}</td>
          <td>${esc(tx.created_at)}</td>
        </tr>`).join('')}
      </table>`;
    }
    receiveTxModal.classList.add('is-open');
    receiveTxModal.setAttribute('aria-hidden', 'false');
  };
  const openQrModal = (id, address, label) => {
    if (!qrModal || !qrImage || !qrLabel || !qrAddress) return;
    qrLabel.innerHTML = `<b>${esc(label || i18n['wallet.page.deposit.receive_address'])}</b>`;
    qrAddress.innerHTML = addressText(address);
    qrImage.src = `qr.php?address_id=${encodeURIComponent(id)}`;
    qrModal.classList.add('is-open');
    qrModal.setAttribute('aria-hidden', 'false');
  };
  const bindEditButtons = () => {
    document.querySelectorAll('.edit-wallet-button').forEach((button) => {
      button.addEventListener('click', () => openEditModal(button.dataset.addressId || '', button.dataset.label || ''));
    });
    document.querySelectorAll('.view-wallet-transactions').forEach((button) => {
      button.addEventListener('click', () => openReceiveTransactions(button.dataset.addressId || ''));
    });
    document.querySelectorAll('.show-qr-button').forEach((button) => {
      button.addEventListener('click', () => openQrModal(button.dataset.addressId || '', button.dataset.address || '', button.dataset.label || ''));
    });
  };
  document.querySelectorAll('[data-close-modal]').forEach((button) => {
    button.addEventListener('click', () => {
      editModal?.classList.remove('is-open');
      editModal?.setAttribute('aria-hidden', 'true');
      receiveTxModal?.classList.remove('is-open');
      receiveTxModal?.setAttribute('aria-hidden', 'true');
      qrModal?.classList.remove('is-open');
      qrModal?.setAttribute('aria-hidden', 'true');
      if (qrImage) qrImage.src = '';
    });
  });
  editModal?.addEventListener('click', (event) => {
    if (event.target === editModal) {
      editModal.classList.remove('is-open');
      editModal.setAttribute('aria-hidden', 'true');
    }
  });
  receiveTxModal?.addEventListener('click', (event) => {
    if (event.target === receiveTxModal) {
      receiveTxModal.classList.remove('is-open');
      receiveTxModal.setAttribute('aria-hidden', 'true');
    }
  });
  qrModal?.addEventListener('click', (event) => {
    if (event.target === qrModal) {
      qrModal.classList.remove('is-open');
      qrModal.setAttribute('aria-hidden', 'true');
      if (qrImage) qrImage.src = '';
    }
  });
  bindEditButtons();
  const refresh = async () => {
    try {
      const res = await fetch('deposit.php?live=1&addr_page=<?= h((string)$addressPage) ?>', { credentials: 'same-origin', cache: 'no-store' });
      if (!res.ok) return;
      const data = await res.json();
      const balance = document.getElementById('live-wallet-balance');
      if (balance) balance.textContent = data.wallet_balance || '0.00000000';
      const addrBody = document.getElementById('live-addresses-tbody');
      if (addrBody && Array.isArray(data.addresses)) {
        addrBody.innerHTML = data.addresses.map((row) =>
          `<tr>
            <td>
              <span class="wallet-name-cell">
                <strong>${esc(row.label)}</strong>
                <button type="button" class="small-button edit-wallet-button" data-address-id="${esc(row.id)}" data-label="${esc(row.edit_label || '')}">${esc(i18n['wallet.button.edit'])}</button>
              </span>
            </td>
            <td>${addressText(row.address)} <button type="button" class="small-button qr-small-button show-qr-button" data-address-id="${esc(row.id)}" data-address="${esc(row.address)}" data-label="${esc(row.label)}">${esc(i18n['wallet.button.qr'])}</button></td>
            <td>${esc(row.total_received)}</td>
            <td>${esc(row.pending_received)}</td>
            <td>${esc(row.deposit_count)}</td>
            <td><button type="button" class="small-button view-wallet-transactions" data-address-id="${esc(row.id)}">${esc(i18n['wallet.page.deposit.view_transactions'])}</button></td>
          </tr>`
        ).join('');
        bindEditButtons();
      }
    } catch (_) {}
  };
  setInterval(refresh, 10000);
})();
</script>
<?php render_footer(); ?>
