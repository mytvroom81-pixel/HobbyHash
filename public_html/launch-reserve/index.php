<?php
require_once __DIR__ . '/../app/i18n.php';
hobc_i18n_bootstrap();
require_once __DIR__ . '/../api/_bootstrap.php';

$pageId = 'reserve';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'reserve';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
require __DIR__ . '/../includes/status-bar.php';

$reserveStatus = hobc_reserve_status();
$reserveCategories = reserve_page_localize_categories(hobc_reserve_categories());
$reserveTransactions = is_array($reserveStatus['outgoing_transactions'] ?? null) ? $reserveStatus['outgoing_transactions'] : [];

function reserve_page_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function reserve_page_localize_categories(array $categories): array
{
    $localized = [];
    foreach ($categories as $slug => $category) {
        $labelKey = 'category.' . (string)$slug . '.label';
        $entry = [
            'label' => hobc_tp('reserve', $labelKey, [], (string)$category['label']),
            'percentage' => $category['percentage'],
            'covers' => [],
        ];
        foreach ((array)$category['covers'] as $index => $item) {
            $coverKey = 'category.' . (string)$slug . '.cover.' . (string)$index;
            $entry['covers'][] = hobc_tp('reserve', $coverKey, [], (string)$item);
        }
        $localized[$slug] = $entry;
    }

    return $localized;
}

function reserve_page_data_basis(string $value): string
{
    $map = [
        'Live getwalletinfo balance and listtransactions send history from the configured HOBC launch reserve wallet.' => 'data_basis.live_wallet',
        'Live reserve wallet tracking' => 'data_basis.fallback',
    ];

    if (isset($map[$value])) {
        return hobc_tp('reserve', $map[$value], [], $value);
    }

    return $value;
}

function reserve_page_tx_category_label(array $tx): string
{
    $fallback = (string)($tx['reserve_category_label'] ?? hobc_tp('reserve', 'value.uncategorized', [], 'Uncategorized'));
    $slug = (string)($tx['reserve_category'] ?? '');
    if ($slug === '') {
        return $fallback;
    }

    return hobc_tp('reserve', 'category.' . $slug . '.label', [], $fallback);
}

function reserve_page_explorer_link(string $value, int $start = 14, int $end = 10): string
{
    $value = trim($value);
    if ($value === '' || $value === 'not_available') {
        return 'not_available';
    }
    $label = strlen($value) > ($start + $end + 3) ? substr($value, 0, $start) . '...' . substr($value, -$end) : $value;
    return '<a class="hash-chip" href="/explorer/?q=' . rawurlencode($value) . '" title="' . reserve_page_e($value) . '">' . reserve_page_e($label) . '</a>';
}
?>
<main id="main-content">
  <div class="page reserve-page">
    <section class="hero"><div class="hero-content"><span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span><h1><?= hobc_tpe($pageId, 'hero.title') ?></h1><p><?= hobc_tpe($pageId, 'hero.lead') ?></p></div></section>

    <section class="grid cards">
      <div class="metric-card"><span class="metric-label"><?= hobc_tpe($pageId, 'label.total_supply') ?></span><strong><?= reserve_page_e(number_format((float)HOBC_TOTAL_SUPPLY, 0)) ?> HOBC</strong></div>
      <div class="metric-card"><span class="metric-label"><?= hobc_tpe($pageId, 'label.launch_reserve') ?></span><strong><?= reserve_page_e(number_format((float)HOBC_LAUNCH_RESERVE, 0)) ?> HOBC</strong></div>
      <div class="metric-card"><span class="metric-label"><?= hobc_tpe($pageId, 'label.reserve_percent') ?></span><strong>10%</strong></div>
      <div class="metric-card"><span class="metric-label"><?= hobc_tpe($pageId, 'label.current_balance') ?></span><strong data-api-value="/api/reserve/status" data-field="current_balances" data-fallback="<?= reserve_page_e(hobc_tp($pageId, 'value.not_available_yet', [], 'Not available yet')) ?>"><?= reserve_page_e((string)($reserveStatus['current_balances'] ?? hobc_tp($pageId, 'value.not_available_yet', [], 'Not available yet'))) ?></strong></div>
      <div class="metric-card"><span class="metric-label"><?= hobc_tpe($pageId, 'label.outgoing_spends') ?></span><strong><?= reserve_page_e((string)($reserveStatus['outgoing_transaction_count'] ?? count($reserveTransactions))) ?></strong></div>
    </section>

    <section class="grid two">
      <article class="card">
        <h2><?= hobc_tpe($pageId, 'heading.reserve_address') ?></h2>
        <p><?= hobc_tpe($pageId, 'body.p1') ?></p>
        <div class="table-like">
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.wallet') ?></span><strong><?= reserve_page_e((string)($reserveStatus['reserve_wallet'] ?? HOBC_RESERVE_WALLET)) ?></strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.address') ?></span><strong><?= reserve_page_explorer_link((string)($reserveStatus['primary_reserve_address'] ?? HOBC_LAUNCH_RESERVE_ADDRESS), 18, 12) ?></strong></div>
          <div class="table-row"><span><?= hobc_tpe($pageId, 'label.data_basis') ?></span><strong><?= reserve_page_e(reserve_page_data_basis((string)($reserveStatus['data_basis'] ?? hobc_tp($pageId, 'data_basis.fallback', [], 'Live reserve wallet tracking')))) ?></strong></div>
        </div>
      </article>

      <article class="card">
        <h2><?= hobc_tpe($pageId, 'heading.transparency_rules') ?></h2>
        <p><?= hobc_tpe($pageId, 'body.p2') ?></p>
        <p><?= hobc_tpe($pageId, 'body.p3') ?></p>
      </article>
    </section>

    <section class="card">
      <h2><?= hobc_tpe($pageId, 'heading.reserve_allocation') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p4') ?></p>
      <div class="reserve-category-grid">
        <?php foreach ($reserveCategories as $category): ?>
          <article class="reserve-category-card">
            <div><span><?= reserve_page_e((string)$category['percentage']) ?>%</span><h3><?= reserve_page_e((string)$category['label']) ?></h3></div>
            <ul>
              <?php foreach ((array)$category['covers'] as $item): ?>
                <li><?= reserve_page_e((string)$item) ?></li>
              <?php endforeach; ?>
            </ul>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card">
      <h2><?= hobc_tpe($pageId, 'heading.outgoing_reserve_transactions') ?></h2>
      <p><?= hobc_tpe($pageId, 'body.p5') ?></p>
      <?php if ($reserveTransactions === []): ?>
        <div class="notice"><?= hobc_tp($pageId, 'notice.no_outgoing_transactions') ?></div>
      <?php else: ?>
        <div class="reserve-tx-list">
          <?php foreach ($reserveTransactions as $tx): ?>
            <article class="reserve-tx-card">
              <div class="reserve-tx-main">
                <span><?= hobc_tpe($pageId, 'label.txid') ?></span>
                <strong><?= reserve_page_explorer_link((string)$tx['txid']) ?></strong>
              </div>
              <div><span><?= hobc_tpe($pageId, 'label.amount') ?></span><strong><?= reserve_page_e((string)$tx['amount']) ?> HOBC</strong></div>
              <div><span><?= hobc_tpe($pageId, 'label.destination') ?></span><strong><?= reserve_page_explorer_link((string)($tx['address'] ?? 'not_available'), 12, 8) ?></strong></div>
              <div><span><?= hobc_tpe($pageId, 'label.category') ?></span><strong><?= reserve_page_e(reserve_page_tx_category_label($tx)) ?></strong></div>
              <div><span><?= hobc_tpe($pageId, 'label.confirmations') ?></span><strong><?= reserve_page_e((string)($tx['confirmations'] ?? 'not_available')) ?></strong></div>
              <div><span><?= hobc_tpe($pageId, 'label.time') ?></span><strong><?= reserve_page_e((string)($tx['time_utc'] ?? 'not_available')) ?></strong></div>
              <div class="reserve-tx-description">
                <span><?= hobc_tpe($pageId, 'label.description') ?></span>
                <p><?= trim((string)($tx['description'] ?? '')) !== '' ? nl2br(reserve_page_e((string)$tx['description'])) : reserve_page_e(hobc_tp($pageId, 'value.no_public_description', [], 'No public description added yet.')) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="notice"><?= hobc_tp($pageId, 'notice.1') ?></section>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
