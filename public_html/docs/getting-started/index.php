<?php
require_once __DIR__ . '/../../app/i18n.php';
hobc_i18n_bootstrap();
$pageId = 'docs_getting_started';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'docs';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/nav.php';
require __DIR__ . '/../../includes/status-bar.php';
?>
<main id="main-content">
  <div class="page docs-page">
    <section class="hero">
      <div class="hero-content">
        <span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span>
        <h1><?= hobc_tpe($pageId, 'hero.title') ?></h1>
        <p><?= hobc_tpe($pageId, 'hero.lead') ?></p>
      </div>
    </section>

    <nav class="subnav" aria-label="<?= hobc_tpe($pageId, 'aria.docs_navigation') ?>"><a class="button" href="/docs/"><?= hobc_tpe($pageId, 'button.docs_home') ?></a><a class="button" href="/docs/wallet-guide/"><?= hobc_tpe($pageId, 'button.wallet_guide') ?></a><a class="button" href="/docs/linux-node/"><?= hobc_tpe($pageId, 'button.linux_node') ?></a><a class="button" href="/docs/mining-guide/"><?= hobc_tpe($pageId, 'button.mining') ?></a></nav>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.what_hobbyhash_coin_is') ?></h2>
      <p><?= hobc_tp($pageId, 'body.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'body.p2') ?></p>
    </section>

    <section class="grid two">
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.what_you_can_do') ?></h2>
        <ul>
          <li><?= hobc_tp($pageId, 'list.can_do.1') ?></li>
          <li><?= hobc_tpe($pageId, 'list.can_do.2') ?></li>
          <li><?= hobc_tpe($pageId, 'list.can_do.3') ?></li>
          <li><?= hobc_tpe($pageId, 'list.can_do.4') ?></li>
          <li><?= hobc_tpe($pageId, 'list.can_do.5') ?></li>
        </ul>
      </article>

      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.what_to_avoid') ?></h2>
        <ul>
          <li><?= hobc_tpe($pageId, 'list.avoid.1') ?></li>
          <li><?= hobc_tpe($pageId, 'list.avoid.2') ?></li>
          <li><?= hobc_tpe($pageId, 'list.avoid.3') ?></li>
          <li><?= hobc_tpe($pageId, 'list.avoid.4') ?></li>
          <li><?= hobc_tpe($pageId, 'list.avoid.5') ?></li>
        </ul>
      </article>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.first_steps') ?></h2>
      <ol>
        <li><?= hobc_tp($pageId, 'step.1') ?></li>
        <li><?= hobc_tp($pageId, 'step.2') ?></li>
        <li><?= hobc_tp($pageId, 'step.3') ?></li>
        <li><?= hobc_tp($pageId, 'step.4') ?></li>
        <li><?= hobc_tp($pageId, 'step.5') ?></li>
      </ol>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
