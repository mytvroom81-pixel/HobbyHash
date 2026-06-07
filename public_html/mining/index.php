<?php
require_once __DIR__ . '/../app/i18n.php';
hobc_i18n_bootstrap();
$pageId = 'mining';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'mining';
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://hobbyhashcoin.com/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Mining', 'item' => 'https://hobbyhashcoin.com/mining/'],
        ],
    ],
];
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
require __DIR__ . '/../includes/status-bar.php';
?>
<main id="main-content">
  <div class="page">
    <section class="hero"><div class="hero-content"><span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span><h1><?= hobc_tpe($pageId, 'hero.title') ?></h1><p><?= hobc_tpe($pageId, 'hero.lead') ?></p></div></section>
    <nav class="subnav" aria-label="Mining navigation"><a class="button" href="<?= hobc_e(hobc_pp('/pool/main/')) ?>"><?= hobc_tpe($pageId, 'label.main_pool') ?></a><a class="button" href="<?= hobc_e(hobc_pp('/pool/nano/')) ?>"><?= hobc_tpe($pageId, 'label.nano_pool') ?></a><a class="button" href="<?= hobc_e(hobc_pp('/stats/')) ?>"><?= hobc_tpe($pageId, 'button.stats') ?></a><a class="button" href="<?= hobc_e(hobc_pp('/docs/')) ?>"><?= hobc_tpe($pageId, 'button.docs') ?></a></nav>

    <section class="notice"><?= hobc_tp($pageId, 'notice.1') ?></section>

    <section class="grid cards">
      <a class="metric-card metric-link" href="<?= hobc_e(hobc_pp('/pool/main/')) ?>"><span class="metric-label"><?= hobc_tpe($pageId, 'label.metric.asic_miners') ?></span><strong><?= hobc_tpe($pageId, 'label.main_pool') ?></strong></a>
      <a class="metric-card metric-link" href="<?= hobc_e(hobc_pp('/pool/nano/')) ?>"><span class="metric-label"><?= hobc_tpe($pageId, 'label.metric.small_miners') ?></span><strong><?= hobc_tpe($pageId, 'label.nano_pool') ?></strong></a>
      <a class="metric-card metric-link" href="<?= hobc_e(hobc_pp('/wallet/')) ?>"><span class="metric-label"><?= hobc_tpe($pageId, 'label.payout_target') ?></span><strong><?= hobc_tpe($pageId, 'label.payout_target_value') ?></strong></a>
    </section>

    <section class="grid two">
      <article class="setup-box">
        <h2><?= hobc_tpe($pageId, 'heading.main_pool_for_asics') ?></h2>
        <p><?= hobc_tpe($pageId, 'body.p1') ?></p>
        <div class="copy-line"><code>stratum+tcp://pool.hobbyhashcoin.com:5555</code><button class="copy-button" data-copy="stratum+tcp://pool.hobbyhashcoin.com:5555"><?= hobc_tpe($pageId, 'button.copy_url') ?></button></div>
        <div class="copy-line"><code>YOUR_HOBC_ADDRESS.worker1</code><button class="copy-button" data-copy="YOUR_HOBC_ADDRESS.worker1"><?= hobc_tpe($pageId, 'button.copy_worker') ?></button></div>
        <p><?= hobc_tpe($pageId, 'label.password') ?>: <code class="pool-static-code">x</code></p>
        <p><?= hobc_tpe($pageId, 'body.p2') ?></p>
        <a class="button primary" href="<?= hobc_e(hobc_pp('/pool/main/')) ?>"><?= hobc_tpe($pageId, 'button.open_main_pool') ?></a>
      </article>
      <article class="setup-box">
        <h2><?= hobc_tpe($pageId, 'heading.nano_pool_for_small_miners') ?></h2>
        <p><?= hobc_tpe($pageId, 'body.p3') ?></p>
        <div class="copy-line"><code>stratum+tcp://pool.hobbyhashcoin.com:5556</code><button class="copy-button" data-copy="stratum+tcp://pool.hobbyhashcoin.com:5556"><?= hobc_tpe($pageId, 'button.copy_url') ?></button></div>
        <div class="copy-line"><code>YOUR_HOBC_ADDRESS.nano1</code><button class="copy-button" data-copy="YOUR_HOBC_ADDRESS.nano1"><?= hobc_tpe($pageId, 'button.copy_worker') ?></button></div>
        <p><?= hobc_tpe($pageId, 'label.password') ?>: <code class="pool-static-code">x</code></p>
        <p><?= hobc_tpe($pageId, 'body.p4') ?></p>
        <a class="button primary" href="<?= hobc_e(hobc_pp('/pool/nano/')) ?>"><?= hobc_tpe($pageId, 'button.open_nano_pool') ?></a>
      </article>
    </section>

    <section class="card">
      <h2><?= hobc_tpe($pageId, 'heading.nano_miner_and_small_miner_setup') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p5') ?></p>
      <p><?= hobc_tpe($pageId, 'body.p6') ?></p>
      <div class="actions"><a class="button primary" href="<?= hobc_e(hobc_pp('/pool/nano/')) ?>"><?= hobc_tpe($pageId, 'button.open_hobc_nano_pool') ?></a><a class="button" href="<?= hobc_e(hobc_pp('/docs/mining-guide/')) ?>"><?= hobc_tpe($pageId, 'button.read_mining_guide') ?></a><a class="button" href="<?= hobc_e(hobc_pp('/docs/faq/')) ?>"><?= hobc_tpe($pageId, 'button.nano_miner_faq') ?></a></div>
    </section>

    <section class="grid two">
      <article class="card">
        <h2><?= hobc_tpe($pageId, 'heading.start_here') ?></h2>
        <div class="table-like">
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.1_get_an_address') ?></span><strong><?= hobc_tpe($pageId, 'value.1_get_an_address') ?></strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.2_pick_a_pool') ?></span><strong><?= hobc_tpe($pageId, 'value.2_pick_a_pool') ?></strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.3_set_username') ?></span><strong><?= hobc_tpe($pageId, 'value.3_set_username') ?></strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.4_set_password') ?></span><strong><?= hobc_tpe($pageId, 'value.4_set_password') ?></strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.5_watch_status') ?></span><strong><?= hobc_tpe($pageId, 'value.5_watch_status') ?></strong></div>
        </div>
        <div class="actions"><a class="button" href="<?= hobc_e(hobc_pp('/downloads/')) ?>"><?= hobc_te('button.windows_wallet') ?></a><a class="button" href="<?= hobc_e(hobc_pp('/wallet/')) ?>"><?= hobc_tpe($pageId, 'button.open_wallet') ?></a><a class="button" href="<?= hobc_e(hobc_pp('/downloads/')) ?>"><?= hobc_tpe($pageId, 'button.download_node') ?></a></div>
      </article>
      <article class="card">
        <h2><?= hobc_tpe($pageId, 'heading.what_solo_mining_means') ?></h2>
        <p><?= hobc_tpe($pageId, 'body.p7') ?></p>
        <p><?= hobc_tpe($pageId, 'body.p8') ?></p>
        <p><?= hobc_tpe($pageId, 'body.p9') ?></p>
        <pre>YOUR_HOBC_ADDRESS.worker1</pre>
      </article>
    </section>

    <section class="card">
      <h2><?= hobc_tpe($pageId, 'heading.live_mining_status') ?></h2>
      <div class="table-like">
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.chain_status') ?></span><strong data-api-value="/api/chain/status" data-field="status" data-fallback="<?= hobc_te('status.syncing') ?>"><?= hobc_te('status.syncing') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.current_height') ?></span><strong data-api-value="/api/chain/status" data-field="blocks" data-fallback="<?= hobc_te('status.not_available') ?>"><?= hobc_te('status.not_available') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.main_pool') ?></span><strong data-api-value="/api/pool/main/status" data-field="status" data-fallback="<?= hobc_te('status.offline') ?>"><?= hobc_te('status.offline') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.main_hashrate') ?></span><strong data-api-value="/api/pool/main/status" data-field="hashrate" data-format="hashrate" data-fallback="<?= hobc_te('status.not_available') ?>"><?= hobc_te('status.not_available') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.main_accepted_shares') ?></span><strong data-api-value="/api/pool/main/status" data-field="accepted_shares" data-fallback="<?= hobc_te('status.not_available') ?>"><?= hobc_te('status.not_available') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.main_rejected_shares') ?></span><strong data-api-value="/api/pool/main/status" data-field="rejected_shares" data-fallback="<?= hobc_te('status.not_available') ?>"><?= hobc_te('status.not_available') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.nano_pool') ?></span><strong data-api-value="/api/pool/nano/status" data-field="status" data-fallback="<?= hobc_te('status.offline') ?>"><?= hobc_te('status.offline') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.nano_hashrate') ?></span><strong data-api-value="/api/pool/nano/status" data-field="hashrate" data-format="hashrate" data-fallback="<?= hobc_te('status.not_available') ?>"><?= hobc_te('status.not_available') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.nano_accepted_shares') ?></span><strong data-api-value="/api/pool/nano/status" data-field="accepted_shares" data-fallback="<?= hobc_te('status.not_available') ?>"><?= hobc_te('status.not_available') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.nano_rejected_shares') ?></span><strong data-api-value="/api/pool/nano/status" data-field="rejected_shares" data-fallback="<?= hobc_te('status.not_available') ?>"><?= hobc_te('status.not_available') ?></strong></div>
      </div>
    </section>

    <section class="grid cards">
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.need_a_wallet.title') ?></h3><p><?= hobc_tpe($pageId, 'body.p10') ?></p><div class="actions"><a class="button" href="<?= hobc_e(hobc_pp('/downloads/')) ?>"><?= hobc_te('button.windows_wallet') ?></a><a class="button" href="<?= hobc_e(hobc_pp('/wallet/')) ?>"><?= hobc_tpe($pageId, 'button.open_wallet') ?></a><a class="button" href="<?= hobc_e(hobc_pp('/docs/')) ?>"><?= hobc_tpe($pageId, 'button.wallet_docs') ?></a></div></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.miner_not_connecting.title') ?></h3><p><?= hobc_tpe($pageId, 'body.p11') ?></p><a class="button" href="<?= hobc_e(hobc_pp('/contact/')) ?>"><?= hobc_tpe($pageId, 'button.get_support') ?></a></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.want_to_verify_blocks.title') ?></h3><p><?= hobc_tpe($pageId, 'body.p12') ?></p><a class="button" href="<?= hobc_e(hobc_pp('/explorer/')) ?>"><?= hobc_tpe($pageId, 'button.open_explorer') ?></a></article>
    </section>

    <section class="card">
      <h2><?= hobc_tpe($pageId, 'heading.common_beginner_questions') ?></h2>
      <div class="table-like">
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.can_i_mine_hobc_with_asics') ?></span><strong><?= hobc_tpe($pageId, 'answer.can_i_mine_hobc_with_asics') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.can_small_miners_try') ?></span><strong><?= hobc_tpe($pageId, 'answer.can_small_miners_try') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.does_the_pool_split_rewards') ?></span><strong><?= hobc_tpe($pageId, 'answer.does_the_pool_split_rewards') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.can_i_use_a_btc_address') ?></span><strong><?= hobc_tpe($pageId, 'answer.can_i_use_a_btc_address') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.is_mining_guaranteed_income') ?></span><strong><?= hobc_tpe($pageId, 'answer.is_mining_guaranteed_income') ?></strong></div>
      </div>
      <div class="actions"><a class="button" href="<?= hobc_e(hobc_pp('/docs/faq/')) ?>"><?= hobc_tpe($pageId, 'button.read_faq') ?></a><a class="button" href="<?= hobc_e(hobc_pp('/stats/')) ?>"><?= hobc_tpe($pageId, 'button.view_stats') ?></a><a class="button" href="<?= hobc_e(hobc_pp('/docs/mining-guide/')) ?>"><?= hobc_tpe($pageId, 'button.read_mining_docs') ?></a></div>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
