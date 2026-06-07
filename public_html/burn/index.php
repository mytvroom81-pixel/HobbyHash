<?php
require_once __DIR__ . '/../app/i18n.php';
require_once __DIR__ . '/../app/i18n_db_content.php';
hobc_i18n_bootstrap();
$pageId = 'burn';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'burn';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
require __DIR__ . '/../includes/status-bar.php';
$managedBurnEvents = hobc_public_table_exists('burn_events')
    ? hobc_public_fetch_all("SELECT id, title, amount, txid, burn_address, proof_url, event_date, public_notes FROM burn_events WHERE is_published = 1 AND status IN ('completed','confirmed') ORDER BY COALESCE(event_date, created_at) DESC LIMIT 50")
    : [];
foreach ($managedBurnEvents as &$managedBurnEvent) {
    $managedBurnEvent = hobc_i18n_db_row('burn_events', $managedBurnEvent);
}
unset($managedBurnEvent);
?>
<main id="main-content">
  <div class="page">
    <section class="hero"><div class="hero-content"><span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span><h1><?= hobc_tpe($pageId, 'hero.title') ?></h1><p><?= hobc_tpe($pageId, 'hero.lead') ?></p></div></section>
    <section class="grid cards">
      <div class="metric-card"><span class="metric-label"><?= hobc_tpe($pageId, 'label.status') ?></span><strong data-api-value="/api/burn/status" data-field="status" data-fallback="<?= hobc_te('status.syncing') ?>"><?= hobc_te('status.syncing') ?></strong></div>
      <div class="metric-card"><span class="metric-label"><?= hobc_tpe($pageId, 'label.total_burned') ?></span><strong data-api-value="/api/burn/status" data-field="total_burned" data-fallback="<?= hobc_te('status.syncing') ?>"><?= hobc_te('status.syncing') ?></strong></div>
      <div class="metric-card"><span class="metric-label"><?= hobc_tpe($pageId, 'label.burn_addresses') ?></span><strong data-api-value="/api/burn/status" data-field="burn_addresses" data-fallback="1">1</strong></div>
      <div class="metric-card"><span class="metric-label"><?= hobc_tpe($pageId, 'label.burn_tx_outputs') ?></span><strong data-api-value="/api/burn/status" data-field="burn_transaction_count" data-fallback="<?= hobc_te('status.syncing') ?>"><?= hobc_te('status.syncing') ?></strong></div>
      <div class="metric-card"><span class="metric-label"><?= hobc_tpe($pageId, 'label.scan_height') ?></span><strong data-api-value="/api/burn/status" data-field="scan_height" data-fallback="<?= hobc_te('status.syncing') ?>"><?= hobc_te('status.syncing') ?></strong></div>
      <div class="metric-card"><span class="metric-label"><?= hobc_tpe($pageId, 'label.txos_scanned') ?></span><strong data-api-value="/api/burn/status" data-field="txouts_scanned" data-fallback="<?= hobc_te('status.syncing') ?>"><?= hobc_te('status.syncing') ?></strong></div>
    </section>
    <section class="card">
      <h2><?= hobc_tpe($pageId, 'heading.primary_burn_address') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p1') ?></p>
      <div class="copy-line">
        <code id="burn-address" data-api-value="/api/burn/status" data-field="primary_burn_address" data-fallback="hobc1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqf9lpf8">hobc1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqf9lpf8</code>
        <button class="copy-button" type="button" data-copy-target="#burn-address"><?= hobc_tpe($pageId, 'button.copy') ?></button>
      </div>
      <div class="table-like">
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.scriptpubkey') ?></span><strong data-api-value="/api/burn/status" data-field="burn_script_pubkey" data-fallback="00140000000000000000000000000000000000000000">00140000000000000000000000000000000000000000</strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.data_basis') ?></span><strong data-api-value="/api/burn/status" data-field="data_basis" data-fallback="Live node scan">Live node scan</strong></div>
      </div>
    </section>
    <section class="card">
      <h2><?= hobc_tpe($pageId, 'heading.burn_tx_list') ?></h2>
      <p><?= hobc_tp($pageId, 'body.p2') ?></p>
      <div id="burn-transactions" class="burn-tx-list" data-api-list="burn-transactions" data-api-endpoint="/api/burn/status">
        <div class="notice"><?= hobc_tpe($pageId, 'notice.loading_burn_transactions') ?></div>
      </div>
    </section>
    <?php if ($managedBurnEvents !== []): ?>
      <section class="card">
        <h2><?= hobc_tpe($pageId, 'heading.admin_published_burn_events') ?></h2>
        <div class="burn-tx-list">
          <?php foreach ($managedBurnEvents as $event): ?>
            <article class="burn-tx-card">
              <div class="burn-tx-field"><span><?= hobc_tpe($pageId, 'label.event') ?></span><strong><?= h((string)$event['title']) ?></strong></div>
              <div class="burn-tx-field"><span><?= hobc_tpe($pageId, 'label.amount') ?></span><strong><?= h((string)$event['amount']) ?> HOBC</strong></div>
              <div class="burn-tx-field burn-txid"><span><?= hobc_tpe($pageId, 'label.txid') ?></span><strong><?= trim((string)$event['txid']) !== '' ? '<a href="/explorer/?q=' . h(rawurlencode((string)$event['txid'])) . '">' . h((string)$event['txid']) . '</a>' : 'not_available' ?></strong></div>
              <div class="burn-tx-field"><span><?= hobc_tpe($pageId, 'label.address') ?></span><strong><?= h((string)$event['burn_address']) ?></strong></div>
              <div class="burn-tx-field"><span><?= hobc_tpe($pageId, 'label.date') ?></span><strong><?= h((string)($event['event_date'] ?? 'not_available')) ?></strong></div>
              <?php if (!empty($event['proof_url'])): ?><div class="burn-tx-field"><span><?= hobc_tpe($pageId, 'label.proof') ?></span><strong><a href="<?= h((string)$event['proof_url']) ?>"><?= hobc_tpe($pageId, 'button.view_proof') ?></a></strong></div><?php endif; ?>
              <?php if (!empty($event['public_notes'])): ?><p><?= nl2br(h((string)$event['public_notes'])) ?></p><?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
