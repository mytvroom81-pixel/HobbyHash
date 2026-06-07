<?php
require_once __DIR__ . '/../../app/i18n.php';
hobc_i18n_bootstrap();
$pageId = 'pool_main';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'main-pool';
$statsModule = true;
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/nav.php';
require __DIR__ . '/../../includes/status-bar.php';
?>
<main id="main-content">
  <div class="page hobc-stats-page">
    <?php if (hobc_public_feature_disabled('pool.main_enabled')): ?>
      <section class="hero"><div class="hero-content"><span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span><h1><?= hobc_tpe($pageId, 'hero.title') ?></h1><p><?= hobc_tpe($pageId, 'hero.lead') ?></p></div></section>
    <?php else: ?>
    <section class="hero">
      <div class="hero-content">
        <span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow_active', [], hobc_tp($pageId, 'hero.eyebrow')) ?></span>
        <h1><?= hobc_tpe($pageId, 'hero.title_active', [], hobc_tp($pageId, 'hero.title')) ?></h1>
        <p><?= hobc_tpe($pageId, 'hero.lead_active', [], hobc_tp($pageId, 'hero.lead')) ?></p>
      </div>
    </section>
    <nav class="subnav" aria-label="<?= hobc_te('nav.aria_main') ?>"><a class="button" href="<?= hobc_e(hobc_pp('/pool/main/')) ?>"><?= hobc_te('nav.main_pool') ?></a><a class="button" href="<?= hobc_e(hobc_pp('/pool/nano/')) ?>"><?= hobc_te('nav.nano_pool') ?></a></nav>
    <section class="grid two">
      <article class="setup-box pool-connection-card">
        <h2><?= hobc_tpe($pageId, 'heading.connection') ?></h2>
        <div class="pool-copy-list">
          <div class="pool-copy-row">
            <span><?= hobc_tpe($pageId, 'label.pool_url', [], 'Pool URL') ?></span>
            <div class="copy-line"><code>stratum+tcp://pool.hobbyhashcoin.com:5555</code><button class="copy-button" data-copy="stratum+tcp://pool.hobbyhashcoin.com:5555"><?= hobc_te('copy.copy') ?></button></div>
          </div>
          <div class="pool-copy-row">
            <span><?= hobc_te('label.worker') ?></span>
            <div class="copy-line"><code>YOUR_HOBC_ADDRESS.worker1</code><button class="copy-button" data-copy="YOUR_HOBC_ADDRESS.worker1"><?= hobc_te('copy.copy_worker') ?></button></div>
          </div>
          <div class="pool-copy-row">
            <span><?= hobc_te('label.password') ?></span>
            <code class="pool-static-code">x</code>
          </div>
        </div>
        <p><?= hobc_tpe($pageId, 'connection.note') ?></p>
      </article>
      <article class="card">
        <h2><?= hobc_tpe($pageId, 'heading.status') ?></h2>
        <div class="table-like">
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.status') ?></span><strong data-api-value="/api/pool/main/status" data-field="status" data-fallback="<?= hobc_te('status.offline') ?>"><?= hobc_te('status.offline') ?></strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.workers') ?></span><strong data-api-value="/api/pool/main/status" data-field="workers" data-fallback="<?= hobc_te('status.not_available') ?>"><?= hobc_te('status.not_available') ?></strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.hashrate') ?></span><strong data-api-value="/api/pool/main/status" data-field="hashrate" data-format="hashrate" data-fallback="<?= hobc_te('status.not_available') ?>"><?= hobc_te('status.not_available') ?></strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.accepted_shares') ?></span><strong data-api-value="/api/pool/main/status" data-field="accepted_shares" data-fallback="<?= hobc_te('status.not_available') ?>"><?= hobc_te('status.not_available') ?></strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.rejected_shares') ?></span><strong data-api-value="/api/pool/main/status" data-field="rejected_shares" data-fallback="<?= hobc_te('status.not_available') ?>"><?= hobc_te('status.not_available') ?></strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.last_share') ?></span><strong data-api-value="/api/pool/main/status" data-field="last_share.time" data-fallback="<?= hobc_te('status.not_available') ?>"><?= hobc_te('status.not_available') ?></strong></div>
        </div>
      </article>
    </section>
    <?php
      $poolStatsApiUrl = '/api/pool/main/overload/';
      require __DIR__ . '/../../includes/pool-stats-module.php';
    ?>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
