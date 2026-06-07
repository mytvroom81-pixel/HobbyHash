<?php
require_once __DIR__ . '/../../app/i18n.php';
hobc_i18n_bootstrap();
$pageId = 'docs_faq';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'faq';
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://hobbyhashcoin.com/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Docs', 'item' => 'https://hobbyhashcoin.com/docs/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'FAQ', 'item' => 'https://hobbyhashcoin.com/docs/faq/'],
        ],
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [
            ['@type' => 'Question', 'name' => 'What is HobbyHash Coin?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'HobbyHash Coin is a SHA256 coin focused on hobby miners, home miners, solo miners, and small mining setups.']],
            ['@type' => 'Question', 'name' => 'What is HOBC?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'HOBC is the ticker for HobbyHash Coin.']],
            ['@type' => 'Question', 'name' => 'What algorithm does it use?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'HOBC uses SHA256 mining.']],
            ['@type' => 'Question', 'name' => 'Can I mine with a small miner?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Yes, small SHA256 miners can try the Nano Pool. Nano miners, Bitaxe-style devices, NerdMiner-style hobby miners, and other low-hashrate setups may submit fewer shares and show jumpy hashrate.']],
            ['@type' => 'Question', 'name' => 'Can I solo mine?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'The current site describes the pools as solo only. Accepted shares show work, but payout depends on finding a real block under pool rules.']],
            ['@type' => 'Question', 'name' => 'What port do I use?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Mainnet node P2P uses 18761. Main Pool stratum uses 5555. Nano Pool stratum uses 5556.']],
            ['@type' => 'Question', 'name' => 'What port do I forward on my router?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Forward mainnet P2P port 18761 only if you want inbound node peers. Do not forward RPC.']],
            ['@type' => 'Question', 'name' => 'Should I open RPC?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'No. Keep RPC private/local only. Mainnet RPC is 18762 and should not be exposed to the internet.']],
            ['@type' => 'Question', 'name' => 'What are accepted shares?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Accepted shares are valid units of mining work accepted by the pool.']],
            ['@type' => 'Question', 'name' => 'Is mining profitable?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Profit depends on hardware, power cost, uptime, difficulty, luck, and market conditions. HOBC docs do not promise profit.']],
            ['@type' => 'Question', 'name' => 'Where do I get support?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Use the official contact form and choose the section that matches your issue.']],
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
    <nav class="subnav" aria-label="Docs navigation"><a class="button" href="/docs/"><?= hobc_tpe($pageId, 'button.docs_home') ?></a><a class="button" href="/mining/"><?= hobc_tpe($pageId, 'button.mining_setup') ?></a><a class="button" href="/pool/nano/"><?= hobc_tpe($pageId, 'button.nano_pool') ?></a><a class="button" href="/contact/"><?= hobc_tpe($pageId, 'button.support') ?></a></nav>

    <section class="grid cards docs-faq-grid">
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.what_is_hobbyhash_coin.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.what_is_hobbyhash_coin.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.what_is_hobc.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.what_is_hobc.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.what_algorithm_does_it_use.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.what_algorithm_does_it_use.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.can_i_mine_with_a_small_miner.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.can_i_mine_with_a_small_miner.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.can_i_solo_mine.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.can_i_solo_mine.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.what_port_do_i_use.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.what_port_do_i_use.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.what_port_do_i_forward_on_my_router.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.what_port_do_i_forward_on_my_router.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.should_i_open_rpc.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.should_i_open_rpc.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.why_does_windows_warn_about_the_wallet.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.why_does_windows_warn_about_the_wallet.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.what_is_cold_storage.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.what_is_cold_storage.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.what_is_a_full_node.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.what_is_a_full_node.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.what_is_a_bootstrap_snapshot.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.what_is_a_bootstrap_snapshot.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.what_are_checksums.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.what_are_checksums.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.what_are_accepted_shares.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.what_are_accepted_shares.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.what_are_rejected_shares.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.what_are_rejected_shares.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.what_is_best_difficulty.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.what_is_best_difficulty.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.does_a_high_share_mean_i_found_a_block.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.does_a_high_share_mean_i_found_a_block.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.can_i_run_my_own_pool.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.can_i_run_my_own_pool.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.can_exchanges_integrate_hobc.question') ?></h3><p><?= hobc_tp($pageId, 'faq.can_exchanges_integrate_hobc.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.is_mining_profitable.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.is_mining_profitable.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.what_happens_if_i_lose_my_wallet_backup.question') ?></h3><p><?= hobc_tpe($pageId, 'faq.what_happens_if_i_lose_my_wallet_backup.answer') ?></p></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'faq.where_do_i_get_support.question') ?></h3><p><?= hobc_tp($pageId, 'faq.where_do_i_get_support.answer') ?></p></article>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
