<?php
require_once __DIR__ . '/../../app/i18n.php';
hobc_i18n_bootstrap();
$pageId = 'docs_explorer_guide';
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
    <nav class="subnav" aria-label="<?= hobc_tpe($pageId, 'aria.docs_navigation') ?>"><a class="button" href="/docs/"><?= hobc_tpe($pageId, 'button.docs_home') ?></a><a class="button" href="/explorer/"><?= hobc_tpe($pageId, 'button.open_explorer') ?></a><a class="button" href="/stats/"><?= hobc_tpe($pageId, 'button.stats') ?></a></nav>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.what_a_block_explorer_is') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p1') ?></p>
      <p><?= hobc_tp($pageId, 'body.p3') ?></p>
    </section>

    <section class="grid two">
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.search_a_transaction_id') ?></h2>
        <ol>
          <li><?= hobc_tpe($pageId, 'step.tx.1') ?></li>
          <li><?= hobc_tp($pageId, 'step.tx.2') ?></li>
          <li><?= hobc_tpe($pageId, 'step.tx.3') ?></li>
          <li><?= hobc_tpe($pageId, 'step.tx.4') ?></li>
        </ol>
      </article>
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.search_a_block') ?></h2>
        <ol>
          <li><?= hobc_tpe($pageId, 'step.block.1') ?></li>
          <li><?= hobc_tpe($pageId, 'step.block.2') ?></li>
          <li><?= hobc_tpe($pageId, 'step.block.3') ?></li>
        </ol>
      </article>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.search_an_address') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p2') ?></p>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.terms_you_will_see') ?></h2>
      <div class="table-like">
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.confirmations') ?></span><strong><?= hobc_tpe($pageId, 'desc.confirmations') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.block_height') ?></span><strong><?= hobc_tpe($pageId, 'desc.block_height') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.difficulty') ?></span><strong><?= hobc_tpe($pageId, 'desc.difficulty') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.mempool') ?></span><strong><?= hobc_tpe($pageId, 'desc.mempool') ?></strong></div>
      </div>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
