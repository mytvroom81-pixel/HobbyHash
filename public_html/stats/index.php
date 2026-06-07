<?php
require_once __DIR__ . '/../app/i18n.php';
hobc_i18n_bootstrap();
$pageId = 'stats';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'stats';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
require __DIR__ . '/../includes/status-bar.php';
?>
<main id="main-content">
  <div class="page">
    <section class="hero"><div class="hero-content"><span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span><h1><?= hobc_tpe($pageId, 'hero.title') ?></h1><p><?= hobc_tpe($pageId, 'hero.lead') ?></p></div></section>
    <section class="grid cards">
      <?= hobc_metric_card_t('metrics.chain_height', '/api/chain/status', 'blocks', 'status.syncing', '', ['link' => 'explorer']) ?>
      <?= hobc_metric_card_t('metrics.latest_block_hash', '/api/chain/latest-blocks', 'blocks.0.hash', 'status.syncing', '', ['format' => 'hash', 'link' => 'explorer']) ?>
      <?= hobc_metric_card_t('metrics.latest_block_time', '/api/chain/latest-blocks', 'blocks.0.time', 'status.syncing', '', ['format' => 'unix-time']) ?>
      <?= hobc_metric_card_t('metrics.current_difficulty', '/api/chain/status', 'difficulty', 'status.syncing') ?>
      <?= hobc_metric_card_t('metrics.network_hashrate', '/api/chain/status', 'networkhashps', 'status.not_available', '', ['format' => 'hashrate']) ?>
      <?= hobc_metric_card_t('metrics.circulating_supply', '/api/stats/summary', 'circulating_supply', 'status.not_available') ?>
      <?= hobc_metric_card_t('metrics.total_mined', '/api/stats/summary', 'estimated_minted_supply', 'status.not_available') ?>
      <?= hobc_metric_card_t('metrics.launch_reserve_balance', '/api/stats/summary', 'current_balances', 'status.not_available') ?>
      <?= hobc_metric_card_t('metrics.burned_supply', '/api/stats/summary', 'total_burned', 'status.pending_launch') ?>
      <?= hobc_metric_card_t('metrics.active_nodes', '/api/chain/status', 'connections', 'status.not_available') ?>
      <?= hobc_metric_card_t('metrics.mempool_tx', '/api/chain/status', 'mempool_tx_count', 'status.not_available') ?>
      <?= hobc_metric_card_t('metrics.main_pool_hashrate', '/api/pool/main/status?lite=1', 'hashrate', 'status.not_available', '', ['format' => 'hashrate']) ?>
      <?= hobc_metric_card_t('metrics.nano_pool_hashrate', '/api/pool/nano/status?lite=1', 'hashrate', 'status.not_available', '', ['format' => 'hashrate']) ?>
      <?= hobc_metric_card_t('metrics.main_pool_workers', '/api/pool/main/status?lite=1', 'workers', 'status.not_available') ?>
      <?= hobc_metric_card_t('metrics.nano_pool_workers', '/api/pool/nano/status?lite=1', 'workers', 'status.not_available') ?>
      <?= hobc_metric_card_t('metrics.main_accepted_shares', '/api/pool/main/status?lite=1', 'accepted_shares', 'status.not_available') ?>
      <?= hobc_metric_card_t('metrics.nano_accepted_shares', '/api/pool/nano/status?lite=1', 'accepted_shares', 'status.not_available') ?>
      <?= hobc_metric_card_t('metrics.main_rejected_shares', '/api/pool/main/status?lite=1', 'rejected_shares', 'status.not_available') ?>
      <?= hobc_metric_card_t('metrics.nano_rejected_shares', '/api/pool/nano/status?lite=1', 'rejected_shares', 'status.not_available') ?>
      <?= hobc_metric_card_t('metrics.pool_last_share', '/api/pool/nano/status?lite=1', 'last_share.share_difficulty', 'status.not_available', '', ['format' => 'compact-unit']) ?>
      <?= hobc_metric_card_t('metrics.wallet_backend_health', '/api/wallet/status', 'status', 'status.offline') ?>
      <?= hobc_metric_card_t('metrics.explorer_sync_status', '/api/explorer/status', 'status', 'status.syncing') ?>
    </section>
    <section class="grid two">
      <article class="card"><h2><?= hobc_tpe($pageId, 'heading.recent_blocks') ?></h2><div class="burn-tx-list" data-api-list="latest-blocks" data-api-endpoint="/api/chain/latest-blocks"><div class="notice"><?= hobc_tpe($pageId, 'notice.loading_blocks') ?></div></div></article>
      <article class="card"><h2><?= hobc_tpe($pageId, 'heading.recent_transactions') ?></h2><div class="burn-tx-list" data-api-list="latest-transactions" data-api-endpoint="/api/chain/latest-transactions"><div class="notice"><?= hobc_tpe($pageId, 'notice.loading_transactions') ?></div></div></article>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
