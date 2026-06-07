<?php
header('Location: /docs/faq/', true, 301);
exit;

$section = trim((string)($_GET['section'] ?? 'General'));
$section = $section !== '' ? substr($section, 0, 80) : 'General';
$pageTitle = $section === 'General' ? 'FAQ' : $section . ' FAQ';
$pageDescription = 'HOBC section FAQ.';
$activePage = 'faq';

$generalFaq = [
    ['What is HOBC?', 'HOBC is HobbyHash Coin, a SHA-256 coin focused on home solo mining.'],
    ['Are the pools solo only?', 'Yes. Main Pool and Nano Pool are solo pools only.'],
    ['Where is market price?', 'Market price and market cap are not shown until a real source exists.'],
    ['Why are values pending?', 'HOBC does not show fake data. Missing stats stay syncing, offline, pending launch, or not available.'],
];
$sectionFaqs = [
    'wallet' => [
        ['Is the web wallet custodial?', 'Yes. The website controls wallet keys and funds until you withdraw. Use a local wallet for larger balances.'],
        ['Why do balances and txids matter?', 'The wallet must only show real balances, real deposits, real withdrawals, and real txids.'],
        ['How do receive wallets work?', 'Receive wallets are labels for organizing incoming payments. They do not have separate spendable balances.'],
    ],
    'mining' => [
        ['What does solo mining mean?', 'Solo mining means a payout happens only when your worker finds a real block.'],
        ['Which worker format should I use?', 'Use YOUR_HOBC_ADDRESS.worker1 for Main Pool or YOUR_HOBC_ADDRESS.nano1 for Nano Pool.'],
        ['What algorithm is HOBC?', 'HOBC uses SHA-256 proof-of-work.'],
    ],
    'main pool' => [
        ['What is the Main Pool URL?', 'stratum+tcp://pool.hobbyhashcoin.com:5555'],
        ['What is the Main Pool start difficulty?', 'Main Pool starts at difficulty 5000.'],
        ['Is Main Pool solo only?', 'Yes. Payout goes to the address in the worker name.'],
    ],
    'nano pool' => [
        ['What is the Nano Pool URL?', 'stratum+tcp://pool.hobbyhashcoin.com:5556'],
        ['What is the Nano Pool start difficulty?', 'Nano Pool starts at difficulty 0.005.'],
        ['Who should use Nano Pool?', 'Small SHA-256 miners and nano miners should use the Nano Pool.'],
    ],
    'explorer' => [
        ['Why does explorer data say syncing?', 'Explorer data stays syncing or not available until a real index is ready.'],
        ['Can I search txids and addresses?', 'The search UI exists, but full search should only show real indexed data.'],
    ],
    'launch reserve' => [
        ['How big is the launch reserve?', 'The launch reserve is 8,400,000 HOBC, which is 10% of the 84,000,000 HOBC total supply.'],
        ['Will reserve balances be live?', 'Reserve balances and outgoing transactions must come from real chain tracking.'],
    ],
    'burn' => [
        ['Are burns shown before they happen?', 'No. The burn tracker shows the configured burn address and reports totals from live node scans only.'],
        ['What if no one has burned coins yet?', 'The burn total shows 0.00000000 until real transactions are confirmed to the burn address.'],
    ],
    'downloads' => [
        ['Where are checksums?', 'Checksums are only shown for real downloadable files.'],
        ['Why are downloads pending?', 'The downloads page stays not available until real builds exist.'],
    ],
    'docs' => [
        ['What do the docs cover?', 'Node setup, wallet setup, main pool mining, nano pool mining, troubleshooting, solo mining, wallet risk, and launch reserve.'],
        ['Are docs beginner friendly?', 'Yes. Docs should explain the safe setup path without assuming advanced mining experience.'],
    ],
];
$key = strtolower($section);
$items = $sectionFaqs[$key] ?? $generalFaq;
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
require __DIR__ . '/../includes/status-bar.php';
?>
<main id="main-content">
  <div class="page">
    <section class="hero"><div class="hero-content"><span class="eyebrow">FAQ</span><h1><?= hobc_e($pageTitle) ?></h1><p>Help for the <?= hobc_e($section) ?> section.</p></div></section>
    <section class="grid cards">
      <?php foreach ($items as $item): ?>
        <article class="card"><h3><?= hobc_e($item[0]) ?></h3><p><?= hobc_e($item[1]) ?></p></article>
      <?php endforeach; ?>
    </section>
    <div class="actions"><a class="button" href="/docs/">Read Docs</a><a class="button primary" href="/contact.php?section=<?= rawurlencode($section) ?>">Open <?= hobc_e($section) ?> Ticket</a></div>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
