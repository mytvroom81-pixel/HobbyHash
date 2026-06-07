<?php
require_once __DIR__ . '/../app/i18n.php';
hobc_i18n_bootstrap();
$pageId = 'about';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'about';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
require __DIR__ . '/../includes/status-bar.php';
?>
<main id="main-content">
  <div class="page">
    <section class="hero"><div class="hero-content"><span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span><h1><?= hobc_tpe($pageId, 'hero.title') ?></h1><p><?= hobc_tpe($pageId, 'hero.lead') ?></p></div></section>

    <section class="notice"><?= hobc_tp($pageId, 'notice.1') ?></section>

    <section class="grid cards">
      <a class="metric-card metric-link" href="<?= hobc_e(hobc_pp('/mining/')) ?>"><span class="metric-label"><?= hobc_te('metrics.mining_focus') ?></span><strong><?= hobc_tpe($pageId, 'value.home_solo_mining') ?></strong></a>
      <a class="metric-card metric-link" href="<?= hobc_e(hobc_pp('/stats/')) ?>"><span class="metric-label"><?= hobc_te('metrics.target_supply') ?></span><strong><?= hobc_tpe($pageId, 'value.target_supply_amount') ?></strong></a>
      <a class="metric-card metric-link" href="<?= hobc_e(hobc_pp('/launch-reserve/')) ?>"><span class="metric-label"><?= hobc_te('metrics.launch_reserve') ?></span><strong><?= hobc_tpe($pageId, 'value.launch_reserve_amount') ?></strong></a>
    </section>

    <?php require __DIR__ . '/../includes/mining-quick-steps.php'; ?>

    <section class="grid two">
      <article class="card">
        <h2><?= hobc_tpe($pageId, 'heading.the_coin') ?></h2>
        <p><?= hobc_tpe($pageId, 'body.p1') ?></p>
        <p><?= hobc_tpe($pageId, 'body.p2') ?></p>
        <div class="actions"><a class="button" href="/stats/"><?= hobc_tpe($pageId, 'button.view_stats') ?></a><a class="button" href="/launch-reserve/"><?= hobc_tpe($pageId, 'button.reserve_details') ?></a></div>
      </article>
      <article class="card">
        <h2><?= hobc_tpe($pageId, 'heading.the_website') ?></h2>
        <p><?= hobc_tpe($pageId, 'body.p3') ?></p>
        <p><?= hobc_tpe($pageId, 'body.p4') ?></p>
        <div class="actions"><a class="button" href="/docs/"><?= hobc_tpe($pageId, 'button.read_docs') ?></a><a class="button" href="/explorer/"><?= hobc_tpe($pageId, 'button.open_explorer') ?></a></div>
      </article>
    </section>

    <section class="grid two">
      <article class="card">
        <h2><?= hobc_tpe($pageId, 'heading.mining_and_pools') ?></h2>
        <p><?= hobc_tpe($pageId, 'body.p5') ?></p>
        <div class="table-like">
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.main_pool') ?></span><strong>stratum+tcp://pool.hobbyhashcoin.com:5555</strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.main_start_diff') ?></span><strong>5000</strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.nano_pool') ?></span><strong>stratum+tcp://pool.hobbyhashcoin.com:5556</strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.nano_start_diff') ?></span><strong>0.005</strong></div>
        </div>
        <div class="actions"><a class="button" href="/pool/main/"><?= hobc_tpe($pageId, 'label.main_pool') ?></a><a class="button" href="/pool/nano/"><?= hobc_tpe($pageId, 'label.nano_pool') ?></a></div>
      </article>
      <article class="card">
        <h2><?= hobc_tpe($pageId, 'heading.wallet_and_custody') ?></h2>
        <p><?= hobc_tpe($pageId, 'body.p6') ?></p>
        <p><?= hobc_tpe($pageId, 'body.p7') ?></p>
        <div class="actions"><a class="button" href="<?= hobc_e(hobc_pp('/wallet/')) ?>"><?= hobc_te('button.web_wallet') ?></a><a class="button" href="<?= hobc_e(hobc_pp('/downloads/')) ?>"><?= hobc_te('button.windows_wallet') ?></a><a class="button" href="<?= hobc_e(hobc_pp('/downloads/')) ?>"><?= hobc_tpe($pageId, 'button.node_downloads') ?></a></div>
      </article>
    </section>

    <section class="card">
      <h2><?= hobc_tpe($pageId, 'heading.live_project_status') ?></h2>
      <div class="table-like">
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.chain') ?></span><strong data-api-value="/api/chain/status" data-field="status" data-fallback="<?= hobc_te('status.syncing') ?>"><?= hobc_te('status.syncing') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.current_height') ?></span><strong data-api-value="/api/chain/status" data-field="blocks" data-fallback="<?= hobc_te('status.not_available') ?>"><?= hobc_te('status.not_available') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.main_pool') ?></span><strong data-api-value="/api/pool/main/status" data-field="status" data-fallback="<?= hobc_te('status.offline') ?>"><?= hobc_te('status.offline') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.nano_pool') ?></span><strong data-api-value="/api/pool/nano/status" data-field="status" data-fallback="<?= hobc_te('status.offline') ?>"><?= hobc_te('status.offline') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.explorer') ?></span><strong data-api-value="/api/explorer/status" data-field="status" data-fallback="<?= hobc_te('status.syncing') ?>"><?= hobc_te('status.syncing') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.wallet') ?></span><strong data-api-value="/api/wallet/status" data-field="status" data-fallback="<?= hobc_te('status.offline') ?>"><?= hobc_te('status.offline') ?></strong></div>
      </div>
    </section>

    <section class="grid cards">
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.transparency.title') ?></h3><p><?= hobc_tpe($pageId, 'card.transparency.body') ?></p><a class="button" href="/burn/"><?= hobc_tpe($pageId, 'button.burn_tracker') ?></a></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.no_fake_data.title') ?></h3><p><?= hobc_tpe($pageId, 'card.no_fake_data.body') ?></p><a class="button" href="/stats/"><?= hobc_tpe($pageId, 'button.live_stats') ?></a></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.where_we_are_going.title') ?></h3><p><?= hobc_tpe($pageId, 'card.where_we_are_going.body') ?></p><a class="button" href="/docs/"><?= hobc_tpe($pageId, 'button.read_docs') ?></a></article>
    </section>

    <div class="actions"><a class="button primary" href="/mining/"><?= hobc_tpe($pageId, 'button.start_mining') ?></a><a class="button" href="/docs/"><?= hobc_tpe($pageId, 'button.read_docs') ?></a><a class="button" href="/docs/faq/">FAQ</a><a class="button" href="/contact/"><?= hobc_tpe($pageId, 'button.contact_support') ?></a></div>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
