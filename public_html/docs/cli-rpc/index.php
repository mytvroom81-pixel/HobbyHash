<?php
require_once __DIR__ . '/../../app/i18n.php';
hobc_i18n_bootstrap();
$pageId = 'docs_cli_rpc';
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
    <nav class="subnav" aria-label="<?= hobc_tpe($pageId, 'aria.docs_navigation') ?>"><a class="button" href="/docs/"><?= hobc_tpe($pageId, 'button.docs_home') ?></a><a class="button" href="/docs/linux-node/"><?= hobc_tpe($pageId, 'button.linux_node') ?></a><a class="button" href="/docs/ports-configuration/"><?= hobc_tpe($pageId, 'button.ports') ?></a><a class="button" href="/docs/security-guide/"><?= hobc_tpe($pageId, 'button.security') ?></a></nav>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.known_binary_names') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p1') ?></p>
      <ul>
        <li><code><?= hobc_e('hobbyhashd') ?></code></li>
        <li><code><?= hobc_e('hobbyhash-cli') ?></code></li>
        <li><code><?= hobc_e('hobbyhash-wallet') ?></code></li>
        <li><code><?= hobc_e('hobbyhash-tx') ?></code></li>
        <li><code><?= hobc_e('hobbyhash-util') ?></code></li>
      </ul>
      <p><?= hobc_tp($pageId, 'body.p2') ?></p>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.config_path') ?></h2>
      <p><?= hobc_tp($pageId, 'body.p3') ?></p>
      <pre id="cli-conf-example">hobbyhashd -conf=/path/to/hobbyhash.conf
hobbyhash-cli -conf=/path/to/hobbyhash.conf getblockchaininfo</pre>
      <button class="copy-button" data-copy-target="#cli-conf-example" data-copy=""><?= hobc_tpe($pageId, 'button.copy_config_examples') ?></button>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.common_node_commands') ?></h2>
      <pre id="node-commands">hobbyhash-cli getblockchaininfo
hobbyhash-cli getnetworkinfo
hobbyhash-cli getpeerinfo
hobbyhash-cli stop</pre>
      <button class="copy-button" data-copy-target="#node-commands" data-copy=""><?= hobc_tpe($pageId, 'button.copy_node_commands') ?></button>
      <p><?= hobc_tp($pageId, 'body.p4') ?></p>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.common_wallet_commands') ?></h2>
      <pre id="wallet-commands">hobbyhash-cli getwalletinfo
hobbyhash-cli getnewaddress
hobbyhash-cli getbalance
hobbyhash-cli listunspent
hobbyhash-cli sendtoaddress ADDRESS AMOUNT</pre>
      <button class="copy-button" data-copy-target="#wallet-commands" data-copy=""><?= hobc_tpe($pageId, 'button.copy_wallet_commands') ?></button>
      <p><?= hobc_tpe($pageId, 'body.p5') ?></p>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.operator_checks') ?></h2>
      <div class="table-like">
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.check_block_height') ?></span><strong><code>hobbyhash-cli getblockchaininfo</code></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.check_peer_count') ?></span><strong><code>hobbyhash-cli getnetworkinfo</code></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.check_wallet_status') ?></span><strong><code>hobbyhash-cli getwalletinfo</code></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.stop_daemon_safely') ?></span><strong><code>hobbyhash-cli stop</code></strong></div>
      </div>
    </section>

    <section class="notice">
      <?= hobc_tp($pageId, 'notice.1') ?>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
