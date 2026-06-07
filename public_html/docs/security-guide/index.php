<?php
require_once __DIR__ . '/../../app/i18n.php';
hobc_i18n_bootstrap();
$pageId = 'docs_security_guide';
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
    <nav class="subnav" aria-label="<?= hobc_tpe($pageId, 'aria.docs_navigation') ?>"><a class="button" href="/docs/"><?= hobc_tpe($pageId, 'button.docs_home') ?></a><a class="button" href="/downloads/"><?= hobc_tpe($pageId, 'button.downloads') ?></a><a class="button" href="/docs/wallet-guide/"><?= hobc_tpe($pageId, 'button.wallet_guide') ?></a><a class="button" href="/docs/ports-configuration/"><?= hobc_tpe($pageId, 'button.ports') ?></a></nav>

    <section class="grid two">
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.download_safety') ?></h2>
        <ul>
          <li><?= hobc_tp($pageId, 'list.download.1') ?></li>
          <li><?= hobc_tpe($pageId, 'list.download.2') ?></li>
          <li><?= hobc_tpe($pageId, 'list.download.3') ?></li>
          <li><?= hobc_tpe($pageId, 'list.download.4') ?></li>
        </ul>
      </article>
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.wallet_safety') ?></h2>
        <ul>
          <li><?= hobc_tpe($pageId, 'list.wallet.1') ?></li>
          <li><?= hobc_tpe($pageId, 'list.wallet.2') ?></li>
          <li><?= hobc_tpe($pageId, 'list.wallet.3') ?></li>
          <li><?= hobc_tpe($pageId, 'list.wallet.4') ?></li>
          <li><?= hobc_tpe($pageId, 'list.wallet.5') ?></li>
        </ul>
      </article>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.node_and_rpc_safety') ?></h2>
      <ul>
        <li><?= hobc_tpe($pageId, 'list.node_rpc.1') ?></li>
        <li><?= hobc_tp($pageId, 'list.node_rpc.2') ?></li>
        <li><?= hobc_tp($pageId, 'list.node_rpc.3') ?></li>
        <li><?= hobc_tp($pageId, 'list.node_rpc.4') ?></li>
        <li><?= hobc_tp($pageId, 'list.node_rpc.5') ?></li>
      </ul>
    </section>

    <section class="grid two">
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.server_ssh_safety') ?></h2>
        <ul>
          <li><?= hobc_tpe($pageId, 'list.server_ssh.1') ?></li>
          <li><?= hobc_tpe($pageId, 'list.server_ssh.2') ?></li>
          <li><?= hobc_tpe($pageId, 'list.server_ssh.3') ?></li>
          <li><?= hobc_tpe($pageId, 'list.server_ssh.4') ?></li>
          <li><?= hobc_tpe($pageId, 'list.server_ssh.5') ?></li>
        </ul>
      </article>
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.fake_support_warning') ?></h2>
        <ul>
          <li><?= hobc_tpe($pageId, 'list.fake_support.1') ?></li>
          <li><?= hobc_tpe($pageId, 'list.fake_support.2') ?></li>
          <li><?= hobc_tpe($pageId, 'list.fake_support.3') ?></li>
          <li><?= hobc_tp($pageId, 'list.fake_support.4') ?></li>
        </ul>
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
