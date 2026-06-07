<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/security_log.php';
require_once __DIR__ . '/../app/admin_view.php';
require_once __DIR__ . '/../api/_bootstrap.php';

$admin = admin_require_user();
$msg = '';
$err = '';
$categories = hobc_reserve_categories();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_validate_or_fail();
    $txid = strtolower(trim((string)($_POST['txid'] ?? '')));
    $category = trim((string)($_POST['category'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));

    if (hobc_reserve_save_spend_description($txid, $category, $description, (int)$admin['id'])) {
        admin_audit((int)$admin['id'], 'reserve_spend_description_update', 'reserve_spend', $txid, [
            'category' => $category,
            'has_description' => $description !== '',
        ]);
        $msg = 'Reserve spend description updated.';
    } else {
        $err = 'Unable to save that reserve spend description. Check the txid and category, then try again.';
    }
}

$reserveStatus = hobc_reserve_status(true);
$transactions = is_array($reserveStatus['outgoing_transactions'] ?? null) ? $reserveStatus['outgoing_transactions'] : [];

render_admin_header('Launch Reserve');
?>
<?php if ($msg): ?><div class="card"><p class="ok"><?= h($msg) ?></p></div><?php endif; ?>
<?php if ($err): ?><div class="card"><p class="err"><?= h($err) ?></p></div><?php endif; ?>

<div class="grid">
  <div class="card"><div>Reserve Wallet</div><div class="metric"><?= h((string)($reserveStatus['reserve_wallet'] ?? HOBC_RESERVE_WALLET)) ?></div></div>
  <div class="card"><div>Reserve Address</div><div class="metric"><code><?= h((string)($reserveStatus['primary_reserve_address'] ?? HOBC_LAUNCH_RESERVE_ADDRESS)) ?></code></div></div>
  <div class="card"><div>Current Balance</div><div class="metric"><?= h((string)($reserveStatus['current_balances'] ?? 'not_available')) ?></div></div>
  <div class="card"><div>Outgoing Spends</div><div class="metric"><?= h((string)count($transactions)) ?></div></div>
</div>

<div class="card">
  <h3>Reserve Allocation Percentages</h3>
  <p>These categories are shown on the public reserve page and can be assigned to each outgoing reserve spend.</p>
  <div class="table-wrap">
    <table>
      <tr><th>Category</th><th>Percent</th><th>Covers</th></tr>
      <?php foreach ($categories as $category): ?>
        <tr>
          <td><?= h((string)$category['label']) ?></td>
          <td><?= h((string)$category['percentage']) ?>%</td>
          <td><?= h(implode(', ', (array)$category['covers'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="card">
  <h3>Outgoing Reserve Transactions</h3>
  <p>Add a category and public description for every reserve spend. The transaction data comes from the configured reserve wallet; the description is your public explanation for that spend.</p>
  <?php if ($transactions === []): ?>
    <p class="warn">No outgoing reserve transactions are available from the reserve wallet right now.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <tr><th>Transaction</th><th>Amount</th><th>Destination</th><th>When</th><th>Public Explanation</th></tr>
        <?php foreach ($transactions as $tx): ?>
          <tr>
            <td>
              <code><?= h((string)$tx['txid']) ?></code><br>
              <small>Confirmations: <?= h((string)($tx['confirmations'] ?? 'not_available')) ?></small>
            </td>
            <td><?= h((string)$tx['amount']) ?> HOBC<br><small>Fee: <?= h((string)($tx['fee'] ?? '0.00000000')) ?> HOBC</small></td>
            <td><code><?= h((string)($tx['address'] ?? 'not_available')) ?></code></td>
            <td><?= h((string)($tx['time_utc'] ?? 'not_available')) ?></td>
            <td>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="txid" value="<?= h((string)$tx['txid']) ?>">
                <label>
                  Category
                  <select name="category">
                    <option value="">Uncategorized</option>
                    <?php foreach ($categories as $key => $category): ?>
                      <option value="<?= h((string)$key) ?>" <?= ((string)($tx['reserve_category'] ?? '') === (string)$key) ? 'selected' : '' ?>><?= h((string)$category['label']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label>
                  Public description
                  <textarea name="description" maxlength="4000" placeholder="Explain what this reserve spend was for."><?= h((string)($tx['description'] ?? '')) ?></textarea>
                </label>
                <button type="submit">Save Explanation</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php render_admin_footer(); ?>
