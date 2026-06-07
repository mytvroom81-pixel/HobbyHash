<?php
require_once __DIR__ . '/../../app/i18n.php';
hobc_i18n_bootstrap();
$pageId = 'docs_pool_stats';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'docs';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/nav.php';
require __DIR__ . '/../../includes/status-bar.php';
?>
<main id="main-content">
  <div class="page docs-page">
    <section class="hero"><div class="hero-content"><span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span><h1><?= hobc_tpe($pageId, 'hero.title') ?></h1><p><?= hobc_tpe($pageId, 'hero.lead') ?></p></div></section>
    <nav class="subnav" aria-label="<?= hobc_tpe($pageId, 'aria.docs_navigation') ?>"><a class="button" href="/docs/"><?= hobc_tpe($pageId, 'button.docs_home') ?></a><a class="button" href="/pool/main/"><?= hobc_tpe($pageId, 'button.main_pool') ?></a><a class="button" href="/pool/nano/"><?= hobc_tpe($pageId, 'button.nano_pool') ?></a><a class="button" href="/stats/"><?= hobc_tpe($pageId, 'button.stats') ?></a></nav>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.core_pool_stats') ?></h2>
      <div class="table-like">
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.active_workers') ?></span><strong><?= hobc_tpe($pageId, 'desc.active_workers') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.hashrate') ?></span><strong><?= hobc_tpe($pageId, 'desc.hashrate') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.accepted_shares') ?></span><strong><?= hobc_tpe($pageId, 'desc.accepted_shares') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.rejected_shares') ?></span><strong><?= hobc_tpe($pageId, 'desc.rejected_shares') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.rejection_percentage') ?></span><strong><?= hobc_tpe($pageId, 'desc.rejection_percentage') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.last_share') ?></span><strong><?= hobc_tpe($pageId, 'desc.last_share') ?></strong></div>
      </div>
    </section>

    <section class="grid two">
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.difficulty_stats') ?></h2>
        <ul>
          <li><?= hobc_tp($pageId, 'term.pool_difficulty') ?></li>
          <li><?= hobc_tp($pageId, 'term.best_difficulty') ?></li>
          <li><?= hobc_tp($pageId, 'term.network_difficulty') ?></li>
          <li><?= hobc_tpe($pageId, 'term.share_becomes_block') ?></li>
        </ul>
      </article>
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.worker_identity') ?></h2>
        <ul>
          <li><?= hobc_tp($pageId, 'term.worker_name') ?></li>
          <li><?= hobc_tp($pageId, 'term.miner_address') ?></li>
          <li><?= hobc_tpe($pageId, 'term.worker_real_address') ?></li>
        </ul>
      </article>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.blocks_and_payout_stats') ?></h2>
      <ul>
        <li><?= hobc_tp($pageId, 'term.blocks_found') ?></li>
        <li><?= hobc_tp($pageId, 'term.pending_payout') ?></li>
        <li><?= hobc_tp($pageId, 'term.confirmed_payout') ?></li>
        <li><?= hobc_tp($pageId, 'term.luck') ?></li>
        <li><?= hobc_tp($pageId, 'term.estimated_odds') ?></li>
      </ul>
    </section>

    <section class="grid two">
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.why_hashrate_jumps_around') ?></h2>
        <p><?= hobc_tpe($pageId, 'body.p1') ?></p>
      </article>
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.why_tiny_miners_show_low_counts') ?></h2>
        <p><?= hobc_tpe($pageId, 'body.p2') ?></p>
      </article>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.what_to_check_first_when_shares_reject') ?></h2>
      <ul>
        <li><?= hobc_tpe($pageId, 'troubleshoot.wrong_algorithm') ?></li>
        <li><?= hobc_tpe($pageId, 'troubleshoot.pool_url') ?></li>
        <li><?= hobc_tpe($pageId, 'troubleshoot.wallet_address') ?></li>
        <li><?= hobc_tpe($pageId, 'troubleshoot.worker_format') ?></li>
        <li><?= hobc_tpe($pageId, 'troubleshoot.latency') ?></li>
        <li><?= hobc_tpe($pageId, 'troubleshoot.miner_difficulty') ?></li>
        <li><?= hobc_tpe($pageId, 'troubleshoot.firmware') ?></li>
      </ul>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
