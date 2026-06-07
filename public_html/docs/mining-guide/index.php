<?php
require_once __DIR__ . '/../../app/i18n.php';
hobc_i18n_bootstrap();
$pageId = 'docs_mining_guide';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'docs';
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://hobbyhashcoin.com/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Docs', 'item' => 'https://hobbyhashcoin.com/docs/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'Mining Guide', 'item' => 'https://hobbyhashcoin.com/docs/mining-guide/'],
        ],
    ],
];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/nav.php';
require __DIR__ . '/../../includes/status-bar.php';
?>
<main id="main-content">
  <div class="page docs-page">
    <section class="hero"><div class="hero-content"><span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span><h1><?= hobc_tpe($pageId, 'hero.title') ?></h1><p><?= hobc_tpe($pageId, 'hero.lead') ?></p></div></section>
    <nav class="subnav" aria-label="<?= hobc_tpe($pageId, 'aria.docs_navigation') ?>"><a class="button" href="/docs/"><?= hobc_tpe($pageId, 'button.docs_home') ?></a><a class="button" href="/mining/"><?= hobc_tpe($pageId, 'button.mining_page') ?></a><a class="button" href="/pool/main/"><?= hobc_tpe($pageId, 'label.main_pool') ?></a><a class="button" href="/pool/nano/"><?= hobc_tpe($pageId, 'label.nano_pool') ?></a><a class="button" href="/docs/pool-stats/"><?= hobc_tpe($pageId, 'button.pool_stats') ?></a></nav>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.algorithm_and_mining_style') ?></h2>
      <p><?= hobc_tp($pageId, 'body.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'body.p2') ?></p>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.nano_miners_and_small_sha256_devices') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p3') ?></p>
      <p>Use the Nano Pool at <code>stratum+tcp://pool.hobbyhashcoin.com:5556</code> with a worker name like <code>HOBC_ADDRESS.nano1</code>. The pool is still solo only, so accepted shares show valid work but do not guarantee a payout.</p>
    </section>

    <section class="grid two">
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.confirmed_pool_urls') ?></h2>
        <div class="table-like">
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.main_pool') ?></span><strong>stratum+tcp://pool.hobbyhashcoin.com:5555</strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.nano_pool') ?></span><strong>stratum+tcp://pool.hobbyhashcoin.com:5556</strong></div>
        </div>
        <p><?= hobc_tpe($pageId, 'body.p5') ?></p>
      </article>
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.worker_format') ?></h2>
        <p><?= hobc_tpe($pageId, 'text.use_worker_format') ?></p>
        <pre id="worker-format">HOBC_ADDRESS.WORKERNAME</pre>
        <button class="copy-button" data-copy-target="#worker-format" data-copy=""><?= hobc_tpe($pageId, 'button.copy_worker_format') ?></button>
        <p><?= hobc_tpe($pageId, 'body.p6') ?></p>
        <pre id="worker-password">x</pre>
        <button class="copy-button" data-copy-target="#worker-password" data-copy=""><?= hobc_tpe($pageId, 'button.copy_password') ?></button>
      </article>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.main_pool_vs_nano_pool') ?></h2>
      <ul>
        <li><?= hobc_tp($pageId, 'term.main_pool_detail') ?></li>
        <li><?= hobc_tp($pageId, 'term.nano_pool_detail') ?></li>
        <li><?= hobc_tpe($pageId, 'term.low_difficulty') ?></li>
        <li><?= hobc_tpe($pageId, 'term.higher_difficulty') ?></li>
      </ul>
    </section>

    <section class="grid two">
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.shares_and_difficulty') ?></h2>
        <ul>
          <li><?= hobc_tp($pageId, 'term.accepted_share') ?></li>
          <li><?= hobc_tp($pageId, 'term.rejected_share') ?></li>
          <li><?= hobc_tp($pageId, 'term.stale_share') ?></li>
          <li><?= hobc_tp($pageId, 'term.best_difficulty') ?></li>
          <li><?= hobc_tp($pageId, 'term.network_difficulty') ?></li>
        </ul>
      </article>
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.blocks_and_payouts') ?></h2>
        <p><?= hobc_tpe($pageId, 'body.p7') ?></p>
        <p><?= hobc_tpe($pageId, 'body.p8') ?></p>
      </article>
    </section>

    <section class="notice">
      <?= hobc_tp($pageId, 'notice.1') ?>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
