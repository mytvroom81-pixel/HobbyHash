<?php
require_once __DIR__ . '/../../app/i18n.php';
hobc_i18n_bootstrap();
$pageId = 'docs_wallet_guide';
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
    <nav class="subnav" aria-label="<?= hobc_tpe($pageId, 'aria.docs_navigation') ?>"><a class="button" href="/docs/"><?= hobc_tpe($pageId, 'button.docs_home') ?></a><a class="button" href="/wallet/"><?= hobc_tpe($pageId, 'button.wallet') ?></a><a class="button" href="/downloads/"><?= hobc_tpe($pageId, 'button.downloads') ?></a><a class="button" href="/docs/security-guide/"><?= hobc_tpe($pageId, 'button.security') ?></a></nav>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.what_a_wallet_does') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'body.p2') ?></p>
    </section>

    <section class="grid two">
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.receiving_hobc') ?></h2>
        <ul>
          <li><?= hobc_tpe($pageId, 'list.receive.1') ?></li>
          <li><?= hobc_tpe($pageId, 'list.receive.2') ?></li>
          <li><?= hobc_tpe($pageId, 'list.receive.3') ?></li>
          <li><?= hobc_tp($pageId, 'list.receive.4') ?></li>
        </ul>
      </article>
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.sending_hobc') ?></h2>
        <ul>
          <li><?= hobc_tpe($pageId, 'list.send.1') ?></li>
          <li><?= hobc_tpe($pageId, 'list.send.2') ?></li>
          <li><?= hobc_tpe($pageId, 'list.send.3') ?></li>
          <li><?= hobc_tpe($pageId, 'list.send.4') ?></li>
        </ul>
      </article>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.confirmations') ?></h2>
      <p><?= hobc_tp($pageId, 'body.p3') ?></p>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.encryption_and_backups') ?></h2>
      <ul>
        <li><?= hobc_tpe($pageId, 'list.backup.1') ?></li>
        <li><?= hobc_tpe($pageId, 'list.backup.2') ?></li>
        <li><?= hobc_tp($pageId, 'list.backup.3') ?></li>
        <li><?= hobc_tpe($pageId, 'list.backup.4') ?></li>
        <li><?= hobc_tpe($pageId, 'list.backup.5') ?></li>
      </ul>
    </section>

    <section class="grid two">
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.cold_storage') ?></h2>
        <p><?= hobc_tpe($pageId, 'body.p4') ?></p>
      </article>
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.private_keys_and_recovery_phrases') ?></h2>
        <p><?= hobc_tpe($pageId, 'body.p5') ?></p>
      </article>
    </section>

    <section class="notice">
      <?= hobc_tp($pageId, 'notice.1') ?>
    </section>

    <section class="notice">
      <?= hobc_tp($pageId, 'notice.2') ?>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
