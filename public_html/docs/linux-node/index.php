<?php
require_once __DIR__ . '/../../app/i18n.php';
hobc_i18n_bootstrap();
$pageId = 'docs_linux_node';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'docs';
$al9Package = '/downloads/linux/HobbyHash-Linux-Node-AL9-x86_64.tar.gz';
$al9Exists = is_file(__DIR__ . '/../../downloads/linux/HobbyHash-Linux-Node-AL9-x86_64.tar.gz');
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/nav.php';
require __DIR__ . '/../../includes/status-bar.php';
?>
<main id="main-content">
  <div class="page docs-page">
    <section class="hero"><div class="hero-content"><span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span><h1><?= hobc_tpe($pageId, 'hero.title') ?></h1><p><?= hobc_tpe($pageId, 'hero.lead') ?></p></div></section>
    <nav class="subnav" aria-label="<?= hobc_tpe($pageId, 'aria.docs_navigation') ?>"><a class="button" href="/docs/"><?= hobc_tpe($pageId, 'button.docs_home') ?></a><a class="button" href="/downloads/"><?= hobc_tpe($pageId, 'button.downloads') ?></a><a class="button" href="/docs/ports-configuration/"><?= hobc_tpe($pageId, 'button.ports') ?></a><a class="button" href="/docs/cli-rpc/"><?= hobc_tpe($pageId, 'button.cli_rpc') ?></a></nav>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.what_a_full_node_is') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'body.p2') ?></p>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.download_status') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p3') ?></p>
      <p><code><?= hobc_e($al9Package) ?></code></p>
      <?php if ($al9Exists): ?>
        <p><a class="button primary" href="<?= hobc_e($al9Package) ?><?= hobc_tpe($pageId, 'button.download_al9_rhel_9_linux_node') ?></a></p>
      <?php else: ?>
        <p><?= hobc_tp($pageId, 'body.p4') ?></p>
      <?php endif; ?>
      <p><?= hobc_tp($pageId, 'body.p10') ?></p>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.recommended_setup') ?></h2>
      <ul>
        <li><?= hobc_tpe($pageId, 'list.recommended.1') ?></li>
        <li><?= hobc_tpe($pageId, 'list.recommended.2') ?></li>
        <li><?= hobc_tpe($pageId, 'list.recommended.3') ?></li>
        <li><?= hobc_tp($pageId, 'list.recommended.4') ?></li>
        <li><?= hobc_tpe($pageId, 'list.recommended.5') ?></li>
      </ul>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.example_mainnet_config') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p5') ?></p>
      <pre id="linux-node-conf">server=1
listen=1
daemon=1
rpcuser=CHANGE_THIS_USERNAME
rpcpassword=CHANGE_THIS_LONG_RANDOM_PASSWORD
rpcbind=127.0.0.1
rpcallowip=127.0.0.1
port=18761
rpcport=18762
addnode=hobbyhashcoin.com:18761</pre>
      <button class="copy-button" data-copy-target="#linux-node-conf" data-copy=""><?= hobc_tpe($pageId, 'button.copy_config') ?></button>
    </section>

    <section class="grid two">
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.basic_install_flow') ?></h2>
        <ol>
          <li><?= hobc_tp($pageId, 'list.install.1') ?></li>
          <li><?= hobc_tpe($pageId, 'list.install.2') ?></li>
          <li><?= hobc_tpe($pageId, 'list.install.3') ?></li>
          <li><?= hobc_tpe($pageId, 'list.install.4') ?></li>
          <li>Start <code>hobbyhashd</code>.</li>
          <li><?= hobc_tpe($pageId, 'list.install.6') ?></li>
        </ol>
      </article>
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.firewall_and_router') ?></h2>
        <ul>
          <li><?= hobc_tp($pageId, 'list.firewall.1') ?></li>
          <li><?= hobc_tp($pageId, 'list.firewall.2') ?></li>
          <li><?= hobc_tpe($pageId, 'list.firewall.3') ?></li>
          <li><?= hobc_tp($pageId, 'list.firewall.4') ?></li>
        </ul>
      </article>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.useful_commands') ?></h2>
      <pre id="linux-node-commands">hobbyhashd -conf=/path/to/hobbyhash.conf
hobbyhash-cli -conf=/path/to/hobbyhash.conf getblockchaininfo
hobbyhash-cli -conf=/path/to/hobbyhash.conf getnetworkinfo
hobbyhash-cli -conf=/path/to/hobbyhash.conf stop</pre>
      <button class="copy-button" data-copy-target="#linux-node-commands" data-copy=""><?= hobc_tpe($pageId, 'button.copy_commands') ?></button>
      <p><?= hobc_tp($pageId, 'body.p6') ?></p>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.logs_resync_and_bootstrap') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p7') ?></p>
      <p><?= hobc_tpe($pageId, 'body.p8') ?></p>
      <p><?= hobc_tp($pageId, 'body.p9') ?></p>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
