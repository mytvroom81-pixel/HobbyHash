<?php
require_once __DIR__ . '/../app/i18n.php';
require_once __DIR__ . '/../app/i18n_db_content.php';
hobc_i18n_bootstrap();
$pageId = 'docs_index';
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
        ],
    ],
];
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
require __DIR__ . '/../includes/status-bar.php';
$managedDocs = hobc_public_table_exists('docs_pages')
    ? hobc_public_fetch_all("SELECT id, title, slug, category, body, updated_at FROM docs_pages WHERE status = 'published' ORDER BY sort_order ASC, title ASC LIMIT 50")
    : [];
$managedDocs = hobc_i18n_db_rows('docs_pages', $managedDocs);
?>
<main id="main-content">
  <div class="page docs-page">
    <?php if (hobc_public_feature_disabled('docs.public_enabled')): ?>
      <section class="hero"><div class="hero-content"><span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span><h1><?= hobc_tpe($pageId, 'hero.title') ?></h1><p><?= hobc_tpe($pageId, 'hero.lead') ?></p></div></section>
    <?php else: ?>
    <section class="hero">
      <div class="hero-content">
        <span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span>
        <h1><?= hobc_tpe($pageId, 'hero.title') ?></h1>
        <p><?= hobc_tpe($pageId, 'body.p1') ?></p>
      </div>
    </section>

    <section class="notice">
      <?= hobc_tp($pageId, 'notice.1') ?>
    </section>

    <section class="grid cards docs-card-grid" aria-label="<?= hobc_tpe($pageId, 'aria.hobbyhash_coin_documentation_sections') ?>">
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.getting_started.title') ?></h3><p><?= hobc_tpe($pageId, 'body.p2') ?></p><a class="button primary" href="<?= hobc_pp('/docs/getting-started/') ?>"><?= hobc_tpe($pageId, 'button.read_guide') ?></a></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.wallet_guide.title') ?></h3><p><?= hobc_tpe($pageId, 'body.p3') ?></p><a class="button primary" href="<?= hobc_pp('/docs/wallet-guide/') ?>"><?= hobc_tpe($pageId, 'button.read_guide') ?></a></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.linux_node_guide.title') ?></h3><p><?= hobc_tpe($pageId, 'body.p4') ?></p><a class="button primary" href="<?= hobc_pp('/docs/linux-node/') ?>"><?= hobc_tpe($pageId, 'button.read_guide') ?></a></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.mining_guide.title') ?></h3><p><?= hobc_tpe($pageId, 'body.p5') ?></p><a class="button primary" href="<?= hobc_pp('/docs/mining-guide/') ?>"><?= hobc_tpe($pageId, 'button.read_guide') ?></a></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.pool_stats_guide.title') ?></h3><p><?= hobc_tpe($pageId, 'body.p6') ?></p><a class="button primary" href="<?= hobc_pp('/docs/pool-stats/') ?>"><?= hobc_tpe($pageId, 'button.read_guide') ?></a></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.ports_configuration.title') ?></h3><p><?= hobc_tpe($pageId, 'body.p7') ?></p><a class="button primary" href="<?= hobc_pp('/docs/ports-configuration/') ?>"><?= hobc_tpe($pageId, 'button.read_guide') ?></a></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.security_guide.title') ?></h3><p><?= hobc_tpe($pageId, 'body.p8') ?></p><a class="button primary" href="<?= hobc_pp('/docs/security-guide/') ?>"><?= hobc_tpe($pageId, 'button.read_guide') ?></a></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.explorer_guide.title') ?></h3><p><?= hobc_tpe($pageId, 'body.p9') ?></p><a class="button primary" href="<?= hobc_pp('/docs/explorer-guide/') ?>"><?= hobc_tpe($pageId, 'button.read_guide') ?></a></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.cli_rpc_guide.title') ?></h3><p><?= hobc_tpe($pageId, 'body.p10') ?></p><a class="button primary" href="<?= hobc_pp('/docs/cli-rpc/') ?>"><?= hobc_tpe($pageId, 'button.read_guide') ?></a></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.faq.title') ?></h3><p><?= hobc_tpe($pageId, 'body.p11') ?></p><a class="button primary" href="<?= hobc_pp('/docs/faq/') ?>"><?= hobc_tpe($pageId, 'button.read_faq') ?></a></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.exchange_listing_packet.title') ?></h3><p><?= hobc_tpe($pageId, 'body.p12') ?></p><a class="button primary" href="<?= hobc_pp('/exchange-listing/') ?>"><?= hobc_tpe($pageId, 'button.open_packet') ?></a></article>
    </section>

    <section class="card docs-quick-reference">
      <h2><?= hobc_tpe($pageId, 'heading.quick_reference') ?></h2>
      <div class="table-like">
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.coin') ?></span><strong><?= hobc_tpe($pageId, 'value.coin_name') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.ticker') ?></span><strong>HOBC</strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.focus') ?></span><strong><?= hobc_tpe($pageId, 'value.focus') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.official_domain') ?></span><strong>hobbyhashcoin.com</strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.mainnet_ports') ?></span><strong><?= hobc_tpe($pageId, 'value.mainnet_ports') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.main_pool') ?></span><strong><?= hobc_notranslate_e('stratum+tcp://pool.hobbyhashcoin.com:5555') ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.nano_pool') ?></span><strong><?= hobc_notranslate_e('stratum+tcp://pool.hobbyhashcoin.com:5556') ?></strong></div>
      </div>
    </section>
    <?php if ($managedDocs !== []): ?>
      <section class="card">
        <h2><?= hobc_tpe($pageId, 'heading.managed_docs') ?></h2>
        <?php if (hobc_public_setting_bool('docs.show_search', true)): ?><p><?= hobc_tpe($pageId, 'body.p13') ?></p><?php endif; ?>
        <div class="grid cards">
          <?php foreach ($managedDocs as $doc): ?>
            <article class="card">
              <span class="eyebrow"><?= h((string)($doc['category'] ?: 'Docs')) ?></span>
              <h3><?= h((string)$doc['title']) ?></h3>
              <p><?= nl2br(h(substr(strip_tags((string)$doc['body']), 0, 420))) ?></p>
              <?php if (hobc_public_setting_bool('docs.show_last_updated', true)): ?><p class="fine-print"><?= hobc_tpe($pageId, 'label.updated') ?> <?= h((string)$doc['updated_at']) ?> <?= hobc_tpe($pageId, 'label.updated_suffix') ?></p><?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
