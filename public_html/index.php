<?php
require_once __DIR__ . '/app/i18n.php';
require_once __DIR__ . '/app/i18n_db_content.php';
hobc_i18n_bootstrap();
$pageId = 'home';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'home';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
require __DIR__ . '/includes/status-bar.php';
$homeAnnouncements = hobc_public_table_exists('announcements')
    ? hobc_public_fetch_all("SELECT id, title, body, published_at FROM announcements WHERE status = 'published' AND show_on_homepage = 1 ORDER BY pinned DESC, COALESCE(published_at, created_at) DESC LIMIT 3")
    : [];
foreach ($homeAnnouncements as &$homeAnnouncement) {
    $homeAnnouncement = hobc_i18n_db_row('announcements', $homeAnnouncement);
}
unset($homeAnnouncement);
?>
<main id="main-content">
  <div class="page">
    <section class="hero visual home-hero">
      <picture class="hero-bg" aria-hidden="true">
        <img
          src="/assets/images/hero-wide.png?v=<?= (int)@filemtime(__DIR__ . '/assets/images/hero-wide.png') ?>"
          srcset="/assets/images/hero-wide.png?v=<?= (int)@filemtime(__DIR__ . '/assets/images/hero-wide.png') ?> 1180w, /assets/images/hero-wide@2x.png?v=<?= (int)@filemtime(__DIR__ . '/assets/images/hero-wide@2x.png') ?> 2360w"
          sizes="(min-width: 71.25rem) 71.25rem, 100vw"
          alt=""
          width="1180"
          height="534"
          decoding="async"
          fetchpriority="high">
      </picture>
      <div class="hero-scrim" aria-hidden="true"></div>
      <div class="hero-content">
        <span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span>
        <h1><?= hobc_tp($pageId, 'hero.title') ?></h1>
        <p><?= hobc_tpe($pageId, 'hero.lead') ?></p>
        <div class="actions">
          <a class="button primary" href="<?= hobc_e(hobc_pp('/mining/')) ?>"><?= hobc_tpe($pageId, 'actions.start_mining') ?></a>
          <a class="button" href="<?= hobc_e(hobc_pp('/wallet/')) ?>"><?= hobc_tpe($pageId, 'actions.open_wallet') ?></a>
          <a class="button" href="#live-dashboard"><?= hobc_tpe($pageId, 'actions.view_live_dashboard') ?></a>
        </div>
      </div>
    </section>

    <section id="live-dashboard" class="live-dashboard" aria-label="<?= hobc_tpe($pageId, 'dashboard.live_aria_label') ?>">
      <header class="live-dashboard-header">
        <div>
          <span class="live-pulse" aria-hidden="true"></span>
          <span class="eyebrow"><?= hobc_tpe($pageId, 'dashboard.live_eyebrow') ?></span>
          <h2><?= hobc_tpe($pageId, 'dashboard.live_title') ?></h2>
          <p><?= hobc_tpe($pageId, 'dashboard.live_lead') ?></p>
        </div>
        <a class="button" href="<?= hobc_e(hobc_pp('/stats/')) ?>"><?= hobc_tpe($pageId, 'dashboard.view_full_stats') ?></a>
      </header>

      <div class="dashboard-section">
        <h3><?= hobc_tpe($pageId, 'dashboard.section_network') ?></h3>
        <div class="grid cards">
          <?= hobc_metric_card_t('metrics.chain_height', '/api/chain/status', 'blocks', 'status.syncing', '/stats/') ?>
          <?= hobc_metric_card_t('metrics.current_difficulty', '/api/chain/status', 'difficulty', 'status.syncing', '/stats/') ?>
          <?= hobc_metric_card_t('metrics.network_hashrate', '/api/chain/status', 'networkhashps', 'status.not_available', '/stats/', ['format' => 'hashrate']) ?>
          <?= hobc_metric_card_t('metrics.latest_block_hash', '/api/chain/latest-blocks', 'blocks.0.hash', 'status.syncing', '/explorer/', ['format' => 'hash', 'link' => 'explorer']) ?>
          <?= hobc_metric_card_t('metrics.latest_block_time', '/api/chain/latest-blocks', 'blocks.0.time', 'status.syncing', '/explorer/', ['format' => 'unix-time']) ?>
          <?= hobc_metric_card_t('metrics.mempool_tx', '/api/chain/status', 'mempool_tx_count', 'status.not_available', '/stats/') ?>
          <?= hobc_metric_card_t('metrics.active_nodes', '/api/chain/status', 'connections', 'status.not_available', '/stats/') ?>
        </div>
      </div>

      <div class="dashboard-section">
        <h3><?= hobc_tpe($pageId, 'dashboard.section_pools') ?></h3>
        <div class="grid cards">
          <?= hobc_metric_card_t('metrics.main_pool_status', '/api/pool/main/status?lite=1', 'status', 'status.offline', '/pool/main/') ?>
          <?= hobc_metric_card_t('metrics.main_pool_hashrate', '/api/pool/main/status?lite=1', 'hashrate', 'status.not_available', '/pool/main/', ['format' => 'hashrate']) ?>
          <?= hobc_metric_card_t('metrics.main_pool_workers', '/api/pool/main/status?lite=1', 'workers', 'status.not_available', '/pool/main/') ?>
          <?= hobc_metric_card_t('metrics.main_accepted_shares', '/api/pool/main/status?lite=1', 'accepted_shares', 'status.not_available', '/pool/main/') ?>
          <?= hobc_metric_card_t('metrics.nano_pool_status', '/api/pool/nano/status?lite=1', 'status', 'status.offline', '/pool/nano/') ?>
          <?= hobc_metric_card_t('metrics.nano_pool_hashrate', '/api/pool/nano/status?lite=1', 'hashrate', 'status.not_available', '/pool/nano/', ['format' => 'hashrate']) ?>
          <?= hobc_metric_card_t('metrics.nano_pool_workers', '/api/pool/nano/status?lite=1', 'workers', 'status.not_available', '/pool/nano/') ?>
          <?= hobc_metric_card_t('metrics.nano_accepted_shares', '/api/pool/nano/status?lite=1', 'accepted_shares', 'status.not_available', '/pool/nano/') ?>
        </div>
      </div>

      <div class="dashboard-section">
        <h3><?= hobc_tpe($pageId, 'dashboard.section_services') ?></h3>
        <div class="grid cards">
          <?= hobc_metric_card_t('metrics.wallet_backend_health', '/api/wallet/status', 'status', 'status.offline', '/wallet/') ?>
          <?= hobc_metric_card_t('metrics.explorer_sync_status', '/api/explorer/status', 'status', 'status.syncing', '/explorer/') ?>
        </div>
      </div>

      <div class="dashboard-section">
        <h3><?= hobc_tpe($pageId, 'dashboard.section_supply') ?></h3>
        <div class="grid cards">
          <?= hobc_metric_card_t('metrics.circulating_supply', '/api/stats/summary', 'circulating_supply', 'status.not_available', '/stats/') ?>
          <?= hobc_metric_card_t('metrics.launch_reserve_balance', '/api/stats/summary', 'current_balances', 'status.not_available', '/launch-reserve/') ?>
          <?= hobc_metric_card_t('metrics.burned_supply', '/api/stats/summary', 'total_burned', 'status.pending_launch', '/burn/') ?>
        </div>
      </div>

      <div class="dashboard-section">
        <h3><?= hobc_tpe($pageId, 'dashboard.section_activity') ?></h3>
        <div class="grid two live-feed-grid">
          <article class="card">
            <h4><?= hobc_tpe($pageId, 'feed.recent_blocks') ?></h4>
            <div class="burn-tx-list" data-api-list="latest-blocks" data-api-endpoint="/api/chain/latest-blocks">
              <div class="notice"><?= hobc_tpe($pageId, 'feed.loading_blocks') ?></div>
            </div>
          </article>
          <article class="card">
            <h4><?= hobc_tpe($pageId, 'feed.recent_transactions') ?></h4>
            <div class="burn-tx-list" data-api-list="latest-transactions" data-api-endpoint="/api/chain/latest-transactions">
              <div class="notice"><?= hobc_tpe($pageId, 'feed.loading_transactions') ?></div>
            </div>
          </article>
        </div>
      </div>
    </section>

    <?php if ($homeAnnouncements !== []): ?>
      <section class="grid cards home-announcements" aria-label="<?= hobc_tpe($pageId, 'announcements.aria_label') ?>">
        <?php foreach ($homeAnnouncements as $announcement): ?>
          <article class="card">
            <span class="eyebrow"><?= hobc_tpe($pageId, 'announcements.eyebrow') ?></span>
            <h3><?= h((string)$announcement['title']) ?></h3>
            <p><?= nl2br(h(substr((string)$announcement['body'], 0, 500))) ?></p>
            <?php if (!empty($announcement['published_at'])): ?><p class="fine-print"><?= hobc_tp($pageId, 'announcements.published', ['date' => h((string)$announcement['published_at'])]) ?></p><?php endif; ?>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php require __DIR__ . '/includes/mining-quick-steps.php'; ?>

    <section class="grid cards home-highlights" aria-label="<?= hobc_tpe($pageId, 'highlights.aria_label') ?>">
      <article class="card icon-card"><img src="/assets/images/icon-home-miners.png" alt="<?= hobc_tpe($pageId, 'cards.home_miners.alt') ?>"><div><h3><?= hobc_tpe($pageId, 'cards.home_miners.title') ?></h3><p><?= hobc_tpe($pageId, 'cards.home_miners.body') ?></p></div></article>
      <article class="card icon-card"><img src="/assets/images/icon-nano.png" alt="<?= hobc_tpe($pageId, 'cards.nano.alt') ?>"><div><h3><?= hobc_tpe($pageId, 'cards.nano.title') ?></h3><p><?= hobc_tpe($pageId, 'cards.nano.body') ?></p></div></article>
      <article class="card icon-card"><img src="/assets/images/icon-transparent.png" alt="<?= hobc_tpe($pageId, 'cards.transparent.alt') ?>"><div><h3><?= hobc_tpe($pageId, 'cards.transparent.title') ?></h3><p><?= hobc_tpe($pageId, 'cards.transparent.body') ?></p></div></article>
    </section>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
