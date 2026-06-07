<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/admin_view.php';
require_once __DIR__ . '/../app/exchange_listing.php';

$admin = admin_require_user();
$items = hobc_exchange_listing_checklist_items();
$checklist = hobc_exchange_listing_checklist_load();
$progress = hobc_exchange_listing_checklist_progress($checklist);
$meta = hobc_exchange_listing_meta();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_validate_or_fail();
    $submitted = [];
    foreach (array_keys($items) as $key) {
        $submitted[$key] = isset($_POST[$key]) && (string)$_POST[$key] === '1';
    }
    hobc_exchange_listing_checklist_save($submitted, (int)$admin['id']);
    admin_audit((int)$admin['id'], 'exchange_listing_checklist_update', 'admin_settings', 'listing.checklist', [
        'done' => hobc_exchange_listing_checklist_progress($submitted)['done'],
        'total' => count($items),
    ]);
    admin_flash_set('success', 'Exchange listing checklist saved.');
    header('Location: ' . admin_url('/exchange-listing.php'));
    exit;
}

$grouped = [];
foreach ($items as $key => $item) {
    $group = (string)$item['group'];
    $grouped[$group][$key] = $item;
}

render_admin_header('Exchange Listing Checklist');
?>
<div class="admin-grid admin-grid-tight">
  <?= admin_stat_card('Checklist progress', $progress['done'] . ' / ' . $progress['total'], $progress['percent'] >= 100 ? 'ok' : 'info') ?>
  <?= admin_stat_card('Completion', $progress['percent'] . '%', $progress['percent'] >= 75 ? 'ok' : 'warn') ?>
  <?= admin_stat_card('Listing email', $meta['contact_email'], 'info') ?>
  <?= admin_stat_card('Public packet', '/exchange-listing/', 'ok') ?>
</div>

<div class="admin-card">
  <h3>Public listing packet</h3>
  <p>Review teams use the public packet for due diligence. Keep checklist status internal; update the public page content through docs/settings when URLs or social accounts go live.</p>
  <div class="admin-actions">
    <?= admin_action_button('Open public packet', 'https://hobbyhashcoin.com/exchange-listing/', 'secondary', ['target' => '_blank', 'rel' => 'noopener noreferrer']) ?>
    <?= admin_action_button('Launch Reserve', admin_url('/reserve.php'), 'secondary') ?>
    <?= admin_action_button('Downloads admin', admin_url('/content.php?tab=downloads'), 'secondary') ?>
  </div>
</div>

<form method="post" class="admin-card listing-checklist-form">
  <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
  <div class="listing-checklist-head">
    <h3>Listing readiness checklist</h3>
    <p>Track exchange listing preparation. Saved state is stored in admin settings (<code>listing.checklist</code>).</p>
  </div>

  <?php foreach ($grouped as $groupLabel => $groupItems): ?>
    <section class="listing-checklist-group">
      <h4><?= h($groupLabel) ?></h4>
      <div class="listing-checklist-grid">
        <?php foreach ($groupItems as $key => $item): ?>
          <label class="listing-checklist-item">
            <input type="checkbox" name="<?= h($key) ?>" value="1"<?= !empty($checklist[$key]) ? ' checked' : '' ?>>
            <span><?= h((string)$item['label']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <div class="admin-actions">
    <button type="submit" class="admin-action">Save checklist</button>
    <a class="admin-action admin-action-secondary" href="<?= h(admin_url('/exchange-listing.php')) ?>">Reset form</a>
  </div>
</form>

<div class="admin-card">
  <h3>Notes for operators</h3>
  <ul class="listing-admin-notes">
    <li>Mark <strong>MEXC submitted later</strong> and <strong>OKX submitted later</strong> only when those applications are actually sent.</li>
    <li>Update public social URLs in <a href="<?= h(admin_url('/settings.php?tab=social')) ?>">Settings → Social Links</a> (<code>listing.social_x</code>, <code>listing.social_facebook</code>, <code>listing.social_discord</code>, <code>listing.social_telegram</code>, plus <code>listing.source_repo_url</code> and <code>listing.contact_email</code>).</li>
    <li>Do not add profit language to the public listing packet. Use transparent reserve policy and ecosystem support wording only.</li>
  </ul>
</div>
<?php render_admin_footer(); ?>
