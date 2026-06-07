<?php
require_once __DIR__ . '/../../app/i18n.php';
hobc_i18n_bootstrap();
$pageId = 'docs_ports_configuration';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'docs';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/nav.php';
require __DIR__ . '/../../includes/status-bar.php';
?>
<main id="main-content">
  <div class="page docs-page">
    <section class="hero"><div class="hero-content"><span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow', [], 'Ports') ?></span><h1><?= hobc_tpe($pageId, 'hero.title') ?></h1><p><?= hobc_tpe($pageId, 'hero.lead') ?></p></div></section>
    <nav class="subnav" aria-label="<?= hobc_tpe($pageId, 'aria.docs_navigation') ?>"><a class="button" href="/docs/"><?= hobc_tpe($pageId, 'button.docs_home') ?></a><a class="button" href="/docs/linux-node/"><?= hobc_tpe($pageId, 'button.linux_node') ?></a><a class="button" href="/docs/security-guide/"><?= hobc_tpe($pageId, 'button.security') ?></a></nav>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.port_reference') ?></h2>
      <div class="table-like">
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.mainnet_p2p') ?></span><strong>18761</strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.mainnet_rpc') ?></span><strong>18762 private/local only</strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.testnet_p2p') ?></span><strong>28761</strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.testnet_rpc') ?></span><strong>28762 private/local only</strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.regtest_p2p') ?></span><strong>38761</strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.regtest_rpc') ?></span><strong>38762 private/local only</strong></div>
      </div>
    </section>

    <section class="grid two">
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.p2p_ports') ?></h2>
        <p><?= hobc_tpe($pageId, 'body.p1') ?></p>
        <p><?= hobc_tp($pageId, 'body.p2') ?></p>
      </article>
      <article class="card docs-article">
        <h2><?= hobc_tpe($pageId, 'heading.rpc_ports') ?></h2>
        <p><?= hobc_tpe($pageId, 'body.p3') ?></p>
        <p><?= hobc_tp($pageId, 'body.p4') ?></p>
      </article>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.safe_mainnet_config_example') ?></h2>
      <pre id="ports-conf">server=1
listen=1
daemon=1
rpcuser=CHANGE_THIS_USERNAME
rpcpassword=CHANGE_THIS_LONG_RANDOM_PASSWORD
rpcbind=127.0.0.1
rpcallowip=127.0.0.1
port=18761
rpcport=18762
addnode=hobbyhashcoin.com:18761</pre>
      <button class="copy-button" data-copy-target="#ports-conf" data-copy=""><?= hobc_tpe($pageId, 'button.copy_config') ?></button>
    </section>

    <section class="card docs-article">
      <h2><?= hobc_tpe($pageId, 'heading.careful_firewall_examples') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p5') ?></p>
      <pre id="firewall-examples"># Allow inbound mainnet P2P only.
# Example for ufw-based systems:
sudo ufw allow 18761/tcp

# Do not allow public RPC:
# sudo ufw allow 18762/tcp   # Do not run this for public access.</pre>
      <button class="copy-button" data-copy-target="#firewall-examples" data-copy=""><?= hobc_tpe($pageId, 'button.copy_examples') ?></button>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
