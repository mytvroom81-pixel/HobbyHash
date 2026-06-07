<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/i18n.php';
hobc_i18n_bootstrap();
require_once __DIR__ . '/../api/_bootstrap.php';
require_once __DIR__ . '/../app/exchange_listing.php';

$pageId = 'exchange_listing';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'exchange-listing';
$canonicalPath = '/exchange-listing/';
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://hobbyhashcoin.com/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Exchange Listing Packet', 'item' => 'https://hobbyhashcoin.com/exchange-listing/'],
        ],
    ],
];
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
require __DIR__ . '/../includes/status-bar.php';

$meta = hobc_exchange_listing_meta();
$genesis = hobc_exchange_listing_genesis();
$supply = hobc_exchange_listing_supply();
$emission = hobc_exchange_listing_emission();
$seedNodes = hobc_exchange_listing_seed_nodes();
$pairs = hobc_exchange_listing_pair_preferences();
$pressKit = hobc_exchange_listing_press_kit();
$listingDocuments = hobc_exchange_listing_documents();
$highlightChecksums = hobc_exchange_listing_highlight_checksums();
$checksumFiles = hobc_exchange_listing_checksum_files();

function listing_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function listing_link_or_text(string $value): string
{
    $url = hobc_exchange_listing_external_link($value);
    if ($url !== '') {
        return '<a href="' . listing_e($url) . '" rel="noopener noreferrer">' . listing_e($value) . '</a>';
    }
    return listing_e($value);
}
?>
<main id="main-content">
  <div class="page exchange-listing-page">
    <section class="hero">
      <div class="hero-content">
        <span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span>
        <h1><?= hobc_tpe($pageId, 'hero.title') ?></h1>
        <p>Official listing information for <?= listing_e($meta['project_name']) ?> (<?= listing_e($meta['ticker']) ?>). This packet is prepared for exchange due diligence, wallet integrations, and aggregator submissions. It uses neutral, factual wording and does not promise investment returns.</p>
      </div>
    </section>

    <nav class="listing-toc subnav" aria-label="<?= hobc_tpe($pageId, 'aria.listing_packet_sections') ?>">
      <a class="button" href="#listing-overview"><?= hobc_tpe($pageId, 'button.overview') ?></a>
      <a class="button" href="#listing-genesis"><?= hobc_tpe($pageId, 'button.genesis') ?></a>
      <a class="button" href="#listing-supply"><?= hobc_tpe($pageId, 'button.supply') ?></a>
      <a class="button" href="#listing-emission"><?= hobc_tpe($pageId, 'button.emission') ?></a>
      <a class="button" href="#listing-reserve"><?= hobc_tpe($pageId, 'button.reserve') ?></a>
      <a class="button" href="#listing-guides"><?= hobc_tpe($pageId, 'button.guides') ?></a>
      <a class="button" href="#listing-social"><?= hobc_tpe($pageId, 'button.social') ?></a>
      <a class="button" href="#listing-security"><?= hobc_tpe($pageId, 'button.security') ?></a>
      <a class="button" href="#listing-checksums"><?= hobc_tpe($pageId, 'button.checksums') ?></a>
      <a class="button" href="#listing-seeds"><?= hobc_tpe($pageId, 'button.seed_nodes') ?></a>
      <a class="button" href="#listing-contact"><?= hobc_tpe($pageId, 'button.contact') ?></a>
      <a class="button" href="#listing-pairs"><?= hobc_tpe($pageId, 'button.pairs') ?></a>
      <a class="button" href="#listing-press-kit"><?= hobc_tpe($pageId, 'button.press_kit') ?></a>
    </nav>

    <section class="notice">
      <strong>Legal-safe notice:</strong> <?= listing_e($meta['ticker']) ?> is not promoted with profit guarantees. <?= listing_e($meta['ticker']) ?> is a mineable utility coin for home solo miners. Mining, custody, and market activity involve operational risk. Users and exchanges should perform independent due diligence.
    </section>

    <section id="listing-overview" class="card listing-section">
      <h2><?= hobc_tpe($pageId, 'heading.project_overview') ?></h2>
      <div class="table-like listing-table">
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.project_name') ?></span><strong><?= listing_e($meta['project_name']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.ticker') ?></span><strong><?= listing_e($meta['ticker']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.website') ?></span><strong><a href="<?= listing_e($meta['website']) ?>"><?= listing_e($meta['website']) ?></a></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.explorer') ?></span><strong><a href="<?= listing_e($meta['explorer_url']) ?>"><?= listing_e($meta['explorer_url']) ?></a> <span class="listing-tag">public explorer URL</span></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.wallet_downloads') ?></span><strong><a href="<?= listing_e($meta['downloads_url']) ?>"><?= listing_e($meta['downloads_url']) ?></a></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.mainnet_p2p_port') ?></span><strong><?= listing_e((string)$meta['mainnet_p2p_port']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.mainnet_rpc_port') ?></span><strong><?= listing_e((string)$meta['mainnet_rpc_port']) ?> (local/private only — do not expose)</strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.algorithm') ?></span><strong><?= listing_e($meta['algorithm']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.coin_type') ?></span><strong><?= listing_e($meta['coin_type']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.purpose') ?></span><strong><?= listing_e($meta['purpose']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.address_hrp') ?></span><strong><?= listing_e($genesis['bech32_hrp']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.message_start') ?></span><strong><code><?= listing_e($genesis['message_start']) ?></code></strong></div>
      </div>
      <div class="listing-document-links">
        <h3><?= hobc_tpe($pageId, 'card.official_documents.title') ?></h3>
        <div class="table-like listing-table">
          <?php foreach ($listingDocuments as $doc): ?>
            <div class="table-row">
              <span><?= listing_e($doc['title']) ?></span>
              <strong>
                <a href="<?= listing_e($doc['file']) ?>" download="<?= listing_e($doc['download_name']) ?>"><?= hobc_tpe($pageId, 'button.download') ?></a>
                ·
                <a href="<?= listing_e($doc['file']) ?>" target="_blank" rel="noopener noreferrer"><?= hobc_tpe($pageId, 'button.open_pdf') ?></a>
              </strong>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section id="listing-genesis" class="card listing-section">
      <h2><?= hobc_tpe($pageId, 'heading.genesis_block_information') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p1') ?></p>
      <p class="fine-print"><?= hobc_tp($pageId, 'body.genesis_faq') ?></p>
      <div class="table-like listing-table">
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.network') ?></span><strong><?= listing_e($genesis['network']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.genesis_height') ?></span><strong><?= listing_e($genesis['height']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.genesis_block_hash') ?></span><strong><code class="listing-mono">0x<?= listing_e($genesis['block_hash']) ?></code></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.merkle_root') ?></span><strong><code class="listing-mono">0x<?= listing_e($genesis['merkle_root']) ?></code></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.timestamp_unix') ?></span><strong><?= listing_e($genesis['timestamp_unix']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.timestamp_utc') ?></span><strong><?= listing_e($genesis['timestamp_utc']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.nonce') ?></span><strong><?= listing_e($genesis['nonce']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.bits') ?></span><strong><code><?= listing_e($genesis['bits']) ?></code></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.version') ?></span><strong><?= listing_e($genesis['version']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.coinbase_message') ?></span><strong><?= listing_e($genesis['coinbase_message']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.genesis_subsidy') ?></span><strong><?= listing_e($genesis['genesis_subsidy']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.launch_reserve_block') ?></span><strong>Height <?= listing_e($genesis['launch_reserve_height']) ?> — <?= listing_e($genesis['launch_reserve_subsidy']) ?> to <?= listing_e($genesis['launch_reserve_address']) ?></strong></div>
      </div>
      <div class="actions">
        <a class="button" href="/explorer/?q=0"><?= hobc_tpe($pageId, 'button.view_genesis_in_explorer') ?></a>
        <a class="button" href="/explorer/?q=1"><?= hobc_tpe($pageId, 'button.view_block_1_launch_reserve') ?></a>
      </div>
    </section>

    <section id="listing-supply" class="grid two">
      <article class="card listing-section">
        <h2><?= hobc_tpe($pageId, 'heading.max_supply_total_supply') ?></h2>
        <div class="table-like listing-table">
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.total_target_supply') ?></span><strong><?= listing_e(number_format((float)$supply['total_target_supply'], 0)) ?> HOBC</strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.launch_reserve_startup_allocation') ?></span><strong><?= listing_e(number_format((float)$supply['launch_reserve'], 0)) ?> HOBC (<?= listing_e($supply['launch_reserve_percent']) ?>)</strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.normal_mining_target') ?></span><strong><?= listing_e(number_format((float)$supply['normal_mining_target'], 0)) ?> HOBC</strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.documented_burn_address') ?></span><strong><code><?= listing_e($supply['burn_address']) ?></code></strong></div>
        </div>
        <p><?= hobc_tpe($pageId, 'body.p2') ?></p>
        <div class="actions"><a class="button" href="/stats/"><?= hobc_tpe($pageId, 'button.live_supply_stats') ?></a><a class="button" href="/burn/"><?= hobc_tpe($pageId, 'button.burn_tracker') ?></a></div>
      </article>
      <article id="listing-emission" class="card listing-section">
        <h2><?= hobc_tpe($pageId, 'heading.emission_schedule') ?></h2>
        <div class="table-like listing-table">
          <?php foreach ($emission as $label => $value): ?>
            <div class="table-row"><span><?= listing_e(ucwords(str_replace('_', ' ', $label))) ?></span><strong><?= listing_e($value) ?></strong></div>
          <?php endforeach; ?>
        </div>
        <p><?= hobc_tpe($pageId, 'body.p3') ?></p>
      </article>
    </section>

    <section id="listing-reserve" class="card listing-section">
      <h2><?= hobc_tpe($pageId, 'heading.startup_reserve_premine_transparency') ?></h2>
      <p><?= hobc_tp($pageId, 'body.reserve_intro', ['percent' => listing_e($supply['launch_reserve_percent'])]) ?></p>
      <ul class="listing-list">
        <li><?= hobc_tp($pageId, 'list.reserve.1') ?></li>
        <li><?= hobc_tpe($pageId, 'list.reserve.2') ?></li>
        <li><?= hobc_tpe($pageId, 'list.reserve.3') ?></li>
        <li><?= hobc_tp($pageId, 'list.reserve.4') ?></li>
      </ul>
      <div class="actions"><a class="button primary" href="/launch-reserve/"><?= hobc_tpe($pageId, 'button.launch_reserve_transparency') ?></a></div>
    </section>

    <section id="listing-guides" class="grid cards">
      <article class="card">
        <h3><?= hobc_tpe($pageId, 'card.mining_guide.title') ?></h3>
        <p><?= hobc_tpe($pageId, 'body.p4') ?></p>
        <a class="button primary" href="/docs/mining-guide/"><?= hobc_tpe($pageId, 'button.mining_guide') ?></a>
      </article>
      <article class="card">
        <h3><?= hobc_tpe($pageId, 'card.node_install_guide.title') ?></h3>
        <p><?= hobc_tpe($pageId, 'body.p5') ?></p>
        <a class="button primary" href="/docs/linux-node/"><?= hobc_tpe($pageId, 'button.linux_node_guide') ?></a>
      </article>
      <article class="card">
        <h3>Ports &amp; Configuration</h3>
        <p><?= hobc_tpe($pageId, 'body.p6') ?></p>
        <a class="button" href="/docs/ports-configuration/"><?= hobc_tpe($pageId, 'button.ports_guide') ?></a>
      </article>
      <article class="card">
        <h3><?= hobc_tpe($pageId, 'card.cli_rpc_guide.title') ?></h3>
        <p><?= hobc_tpe($pageId, 'body.p7') ?></p>
        <a class="button" href="/docs/cli-rpc/"><?= hobc_tpe($pageId, 'button.cli_rpc_guide') ?></a>
      </article>
    </section>

    <section id="listing-social" class="card listing-section">
      <h2><?= hobc_tpe($pageId, 'heading.official_social_links') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p8') ?></p>
      <div class="table-like listing-table">
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.x_twitter') ?></span><strong><?= listing_link_or_text($meta['social_x']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.facebook') ?></span><strong><?= listing_link_or_text($meta['social_facebook']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.discord') ?></span><strong><?= listing_link_or_text($meta['social_discord']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.telegram') ?></span><strong><?= listing_link_or_text($meta['social_telegram']) ?></strong></div>
        <div class="table-row"><span><?= hobc_tpe($pageId, 'label.source_repository') ?></span><strong><?= listing_link_or_text($meta['source_repo_url']) ?></strong></div>
      </div>
    </section>

    <section id="listing-security" class="card listing-section">
      <h2><?= hobc_tpe($pageId, 'heading.security_review_notes') ?></h2>
      <ul class="listing-list">
        <li><?= hobc_tp($pageId, 'list.security.1', ['website' => listing_e($meta['website'])]) ?></li>
        <li><?= hobc_tp($pageId, 'list.security.2', ['rpc_port' => listing_e((string)$meta['mainnet_rpc_port'])]) ?></li>
        <li><?= hobc_tpe($pageId, 'list.security.3') ?></li>
        <li><?= hobc_tpe($pageId, 'list.security.4') ?></li>
        <li><?= hobc_tpe($pageId, 'list.security.5') ?></li>
        <li><?= hobc_tp($pageId, 'list.security.6') ?></li>
      </ul>
    </section>

    <section id="listing-checksums" class="card listing-section">
      <h2><?= hobc_tpe($pageId, 'heading.download_checksums') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p9') ?></p>
      <div class="listing-checksum-grid">
        <?php foreach ($highlightChecksums as $row): ?>
          <article class="listing-checksum-card">
            <h3><?= listing_e($row['label']) ?></h3>
            <p><strong>File:</strong> <?= listing_e($row['file']) ?></p>
            <div class="checksum-box"><span>SHA256</span><code><?= listing_e($row['sha256']) ?></code></div>
            <div class="actions"><a class="button" href="<?= listing_e($row['url']) ?>">Download</a></div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php foreach ($checksumFiles as $file): ?>
        <?php $rows = hobc_exchange_listing_parse_checksum_file($file['filesystem']); ?>
        <details class="listing-checksum-details">
          <summary><?= listing_e($file['label']) ?> (<?= count($rows) ?> entries)</summary>
          <?php if ($rows === []): ?>
            <p class="fine-print"><?= hobc_tpe($pageId, 'checksum.unavailable') ?></p>
          <?php else: ?>
            <div class="table-like listing-table listing-table-compact">
              <?php foreach (array_slice($rows, -8) as $row): ?>
                <div class="table-row"><span><?= listing_e($row['file']) ?></span><strong><code><?= listing_e($row['sha256']) ?></code></strong></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="actions"><a class="button" href="<?= listing_e($file['path']) ?>" target="_blank" rel="noopener noreferrer"><?= hobc_tpe($pageId, 'button.open_full_sha256sums_file') ?></a></div>
        </details>
      <?php endforeach; ?>
    </section>

    <section id="listing-seeds" class="card listing-section">
      <h2><?= hobc_tpe($pageId, 'heading.seed_node_list') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p10') ?></p>
      <div class="table-like listing-table">
        <?php foreach ($seedNodes as $node): ?>
          <div class="table-row"><span><?= listing_e($node['role']) ?></span><strong><code><?= listing_e($node['host']) ?>:<?= listing_e($node['port']) ?></code></strong></div>
        <?php endforeach; ?>
      </div>
      <pre class="listing-sample-conf">addnode=hobbyhashcoin.com:18761
addnode=162.254.37.69:18761</pre>
    </section>

    <section id="listing-contact" class="grid two">
      <article class="card listing-section">
        <h2><?= hobc_tpe($pageId, 'heading.exchange_contact_email') ?></h2>
        <p><?= hobc_tpe($pageId, 'body.p11') ?></p>
        <p class="listing-contact-email"><a href="mailto:<?= listing_e($meta['contact_email']) ?>"><?= listing_e($meta['contact_email']) ?></a></p>
        <p class="fine-print"><?= hobc_tp($pageId, 'contact.support_note') ?></p>
      </article>
      <article id="listing-pairs" class="card listing-section">
        <h2><?= hobc_tpe($pageId, 'heading.listing_pair_preference') ?></h2>
        <div class="table-like listing-table">
          <?php foreach ($pairs as $pair): ?>
            <div class="table-row"><span><?= listing_e($pair['pair']) ?></span><strong><?= listing_e($pair['priority']) ?></strong></div>
          <?php endforeach; ?>
        </div>
      </article>
    </section>

    <section id="listing-press-kit" class="card listing-section">
      <h2><?= hobc_tpe($pageId, 'heading.press_kit_downloads') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p12') ?></p>

      <h3 class="listing-press-subhead"><?= hobc_tpe($pageId, 'heading.official_documents') ?></h3>
      <div class="grid two listing-press-docs">
        <?php foreach ($listingDocuments as $doc): ?>
          <article class="card listing-press-card listing-press-doc-card">
            <span class="eyebrow"><?= listing_e($doc['badge']) ?></span>
            <h3><?= listing_e($doc['title']) ?></h3>
            <p><?= listing_e($doc['description']) ?></p>
            <div class="actions">
              <a class="button primary" href="<?= listing_e($doc['file']) ?>" download="<?= listing_e($doc['download_name']) ?>">Download</a>
              <a class="button" href="<?= listing_e($doc['file']) ?>" target="_blank" rel="noopener noreferrer">Open PDF</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <h3 class="listing-press-subhead"><?= hobc_tpe($pageId, 'heading.brand_images') ?></h3>
      <div class="grid cards listing-press-grid">
        <?php foreach ($pressKit as $asset): ?>
          <article class="card listing-press-card">
            <h3><?= listing_e($asset['label']) ?></h3>
            <p><?= listing_e($asset['note']) ?></p>
            <div class="actions">
              <a class="button primary" href="<?= listing_e($asset['file']) ?>" download="<?= listing_e(basename($asset['file'])) ?>"><?= hobc_tpe($pageId, 'button.download') ?></a>
              <a class="button" href="<?= listing_e($asset['file']) ?>" target="_blank" rel="noopener noreferrer"><?= hobc_tpe($pageId, 'button.preview') ?></a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="notice listing-disclaimer">
      <h2><?= hobc_tpe($pageId, 'heading.disclaimer') ?></h2>
      <ul class="listing-list">
        <li><?= listing_e($meta['ticker']) ?> is not promoted with profit guarantees.</li>
        <li><?= listing_e($meta['ticker']) ?> is a mineable utility coin for home solo miners.</li>
        <li>Users, integrators, and exchanges should do their own research before listing, trading, mining, or holding <?= listing_e($meta['ticker']) ?>.</li>
        <li><?= hobc_tpe($pageId, 'disclaimer.no_advice') ?></li>
      </ul>
    </section>

    <div class="actions">
      <a class="button primary" href="mailto:<?= listing_e($meta['contact_email']) ?>?subject=<?= rawurlencode($meta['ticker'] . ' exchange listing inquiry') ?>">Contact listings team</a>
      <a class="button" href="/docs/faq/">FAQ</a>
      <a class="button" href="/downloads/"><?= hobc_tpe($pageId, 'button.downloads') ?></a>
    </div>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
