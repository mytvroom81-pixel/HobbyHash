<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/i18n.php';
hobc_i18n_bootstrap();
require_once __DIR__ . '/../api/_bootstrap.php';

const HOBC_LAUNCH_RESERVE_HEIGHT = 1;

function explorer_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function explorer_short(string $value, int $start = 14, int $end = 10): string
{
    $value = trim($value);
    if ($value === '' || strlen($value) <= ($start + $end + 3)) {
        return $value;
    }
    return substr($value, 0, $start) . '...' . substr($value, -$end);
}

function explorer_time(mixed $timestamp): string
{
    return is_numeric($timestamp) ? gmdate('Y-m-d H:i:s', (int)$timestamp) . ' UTC' : 'not_available';
}

function explorer_amount(mixed $amount): string
{
    return is_numeric($amount) ? number_format((float)$amount, 8, '.', '') : 'not_available';
}

function explorer_block_by_height(int $height): ?array
{
    if ($height < 0) {
        return null;
    }

    $hash = hobc_rpc('getblockhash', [$height]);
    if (!$hash['ok'] || !is_string($hash['result'])) {
        return null;
    }

    return explorer_block_by_hash($hash['result']);
}

function explorer_block_by_hash(string $hash): ?array
{
    $block = hobc_rpc('getblock', [$hash, 1]);
    return ($block['ok'] && is_array($block['result'])) ? $block['result'] : null;
}

function explorer_tx(string $txid): ?array
{
    $tx = hobc_rpc('getrawtransaction', [$txid, true]);
    return ($tx['ok'] && is_array($tx['result'])) ? $tx['result'] : null;
}

function explorer_address_scan(string $address): ?array
{
    if (strlen($address) > 128 || !str_starts_with($address, 'hobc')) {
        return null;
    }

    $scan = hobc_rpc('scantxoutset', ['start', ['addr(' . $address . ')']]);
    return ($scan['ok'] && is_array($scan['result'])) ? $scan['result'] : null;
}

function explorer_latest_blocks(int $count = 10): array
{
    $chain = hobc_rpc('getblockchaininfo');
    if (!$chain['ok'] || !is_array($chain['result'])) {
        return ['status' => 'offline', 'height' => 'not_available', 'blocks' => []];
    }

    $height = (int)($chain['result']['blocks'] ?? 0);
    $blocks = [];
    for ($h = $height; $h >= 0 && count($blocks) < $count; $h--) {
        $block = explorer_block_by_height($h);
        if ($block !== null) {
            $blocks[] = $block;
        }
    }

    return [
        'status' => !empty($chain['result']['initialblockdownload']) ? 'syncing' : 'online',
        'height' => $height,
        'blocks' => $blocks,
    ];
}

function explorer_latest_transactions(array $blocks, int $count = 12): array
{
    $transactions = [];
    foreach ($blocks as $block) {
        $txids = is_array($block['tx'] ?? null) ? $block['tx'] : [];
        foreach ($txids as $txid) {
            if (!is_string($txid) || $txid === '') {
                continue;
            }
            $transactions[] = [
                'txid' => $txid,
                'height' => $block['height'] ?? 'not_available',
                'blockhash' => $block['hash'] ?? 'not_available',
                'time' => $block['time'] ?? 'not_available',
            ];
            if (count($transactions) >= $count) {
                return $transactions;
            }
        }
    }
    return $transactions;
}

function explorer_label_for_block(array $block): string
{
    return ((int)($block['height'] ?? -1) === HOBC_LAUNCH_RESERVE_HEIGHT) ? 'Launch reserve block' : 'HOBC block';
}

function explorer_label_for_address(string $address): string
{
    if ($address === HOBC_BURN_ADDRESS) {
        return 'Burn address';
    }
    return 'HOBC address';
}

function explorer_render_hash_link(string $value): string
{
    if ($value === '' || $value === 'not_available') {
        return '<span class="hash-chip empty">not_available</span>';
    }
    $href = '/explorer/?q=' . rawurlencode($value);
    return '<a class="hash-chip" href="' . explorer_h($href) . '" title="' . explorer_h($value) . '">' . explorer_h(explorer_short($value)) . '</a>';
}

function explorer_render_block(array $block): string
{
    $txids = is_array($block['tx'] ?? null) ? $block['tx'] : [];
    $height = (int)($block['height'] ?? 0);
    $label = explorer_label_for_block($block);
    $reserveNote = $height === HOBC_LAUNCH_RESERVE_HEIGHT
        ? '<p>' . hobc_tp('explorer', 'block.launch_reserve_note') . '</p>'
        : '';

    $html = '<article class="card"><h2>' . explorer_h($label) . '</h2>' . $reserveNote;
    $html .= '<div class="table-like">';
    $html .= '<div class="table-row"><span>Height</span><strong>' . explorer_h((string)$height) . '</strong></div>';
    $html .= '<div class="table-row"><span>Hash</span><strong>' . explorer_render_hash_link((string)($block['hash'] ?? 'not_available')) . '</strong></div>';
    $html .= '<div class="table-row"><span>Confirmations</span><strong>' . explorer_h((string)($block['confirmations'] ?? 'not_available')) . '</strong></div>';
    $html .= '<div class="table-row"><span>Time</span><strong>' . explorer_h(explorer_time($block['time'] ?? null)) . '</strong></div>';
    $html .= '<div class="table-row"><span>Transactions</span><strong>' . explorer_h((string)count($txids)) . '</strong></div>';
    $html .= '<div class="table-row"><span>Difficulty</span><strong>' . explorer_h((string)($block['difficulty'] ?? 'not_available')) . '</strong></div>';
    $html .= '</div>';

    if ($txids !== []) {
        $html .= '<h3>Block transactions</h3><div class="burn-tx-list">';
        foreach ($txids as $txid) {
            if (is_string($txid)) {
                $html .= '<article class="burn-tx-card"><div class="burn-tx-field burn-txid"><span>TXID</span><strong>' . explorer_render_hash_link($txid) . '</strong></div><div class="burn-tx-field"><span>Height</span><strong>' . explorer_h((string)$height) . '</strong></div><div class="burn-tx-field"><span>Source</span><strong>local RPC</strong></div><div class="burn-tx-field"><span>Status</span><strong>real txid</strong></div></article>';
            }
        }
        $html .= '</div>';
    }

    return $html . '</article>';
}

function explorer_render_tx(array $tx): string
{
    $vin = is_array($tx['vin'] ?? null) ? $tx['vin'] : [];
    $vout = is_array($tx['vout'] ?? null) ? $tx['vout'] : [];
    $html = '<article class="card"><h2>Transaction</h2><div class="table-like">';
    $html .= '<div class="table-row"><span>TXID</span><strong>' . explorer_render_hash_link((string)($tx['txid'] ?? 'not_available')) . '</strong></div>';
    $html .= '<div class="table-row"><span>Block hash</span><strong>' . explorer_render_hash_link((string)($tx['blockhash'] ?? 'not_available')) . '</strong></div>';
    $html .= '<div class="table-row"><span>Confirmations</span><strong>' . explorer_h((string)($tx['confirmations'] ?? 'not_available')) . '</strong></div>';
    $html .= '<div class="table-row"><span>Time</span><strong>' . explorer_h(explorer_time($tx['time'] ?? null)) . '</strong></div>';
    $html .= '<div class="table-row"><span>Inputs</span><strong>' . explorer_h((string)count($vin)) . '</strong></div>';
    $html .= '<div class="table-row"><span>Outputs</span><strong>' . explorer_h((string)count($vout)) . '</strong></div>';
    $html .= '</div>';

    if ($vout !== []) {
        $html .= '<h3>Outputs</h3><div class="burn-tx-list">';
        foreach ($vout as $out) {
            if (!is_array($out)) {
                continue;
            }
            $script = is_array($out['scriptPubKey'] ?? null) ? $out['scriptPubKey'] : [];
            $address = (string)($script['address'] ?? 'not_available');
            $label = $address !== 'not_available' ? explorer_label_for_address($address) : 'not_available';
            $html .= '<article class="burn-tx-card"><div class="burn-tx-field burn-txid"><span>Address</span><strong>' . ($address !== 'not_available' ? explorer_render_hash_link($address) : 'not_available') . '</strong></div><div class="burn-tx-field"><span>Amount</span><strong>' . explorer_h(explorer_amount($out['value'] ?? null)) . ' HOBC</strong></div><div class="burn-tx-field"><span>vout</span><strong>' . explorer_h((string)($out['n'] ?? 'not_available')) . '</strong></div><div class="burn-tx-field"><span>Label</span><strong>' . explorer_h($label) . '</strong></div></article>';
        }
        $html .= '</div>';
    }

    return $html . '</article>';
}

function explorer_render_address(string $address, array $scan): string
{
    $unspents = is_array($scan['unspents'] ?? null) ? $scan['unspents'] : [];
    $html = '<article class="card"><h2>' . explorer_h(explorer_label_for_address($address)) . '</h2>';
    $html .= '<p>This is a live UTXO scan from the local HOBC node. Full address history requires the explorer index and is not fabricated.</p>';
    $html .= '<div class="table-like">';
    $html .= '<div class="table-row"><span>Address</span><strong>' . explorer_h($address) . '</strong></div>';
    $html .= '<div class="table-row"><span>Current UTXO amount</span><strong>' . explorer_h(explorer_amount($scan['total_amount'] ?? null)) . ' HOBC</strong></div>';
    $html .= '<div class="table-row"><span>UTXOs</span><strong>' . explorer_h((string)count($unspents)) . '</strong></div>';
    $html .= '<div class="table-row"><span>Scanned height</span><strong>' . explorer_h((string)($scan['height'] ?? 'not_available')) . '</strong></div>';
    $html .= '<div class="table-row"><span>Address history</span><strong>not_available</strong></div>';
    $html .= '</div>';

    if ($unspents !== []) {
        $html .= '<h3>Unspent outputs</h3><div class="burn-tx-list">';
        foreach ($unspents as $row) {
            if (!is_array($row)) {
                continue;
            }
            $txid = (string)($row['txid'] ?? 'not_available');
            $html .= '<article class="burn-tx-card"><div class="burn-tx-field burn-txid"><span>TXID</span><strong>' . explorer_render_hash_link($txid) . '</strong></div><div class="burn-tx-field"><span>Amount</span><strong>' . explorer_h(explorer_amount($row['amount'] ?? null)) . ' HOBC</strong></div><div class="burn-tx-field"><span>vout</span><strong>' . explorer_h((string)($row['vout'] ?? 'not_available')) . '</strong></div><div class="burn-tx-field"><span>Height</span><strong>' . explorer_h((string)($row['height'] ?? 'not_available')) . '</strong></div></article>';
        }
        $html .= '</div>';
    }

    return $html . '</article>';
}

$query = trim((string)($_GET['q'] ?? ''));
$query = strlen($query) > 128 ? substr($query, 0, 128) : $query;
$explorerPublicEnabled = (bool)hobc_admin_setting_value('explorer.public_enabled', true);
$explorerMaintenanceMessage = (string)hobc_admin_setting_value('explorer.maintenance_message', 'The public explorer is temporarily unavailable.');
$latest = $explorerPublicEnabled ? explorer_latest_blocks() : ['status' => 'offline', 'height' => 'not_available', 'blocks' => []];
$latestTransactions = $explorerPublicEnabled ? explorer_latest_transactions($latest['blocks']) : [];
$searchResult = '';

if ($explorerPublicEnabled && $query !== '') {
    if (ctype_digit($query)) {
        $block = explorer_block_by_height((int)$query);
        $searchResult = $block !== null ? explorer_render_block($block) : '<section class="notice"><strong>No result:</strong> No real HOBC block was found at that height.</section>';
    } elseif (preg_match('/^[a-fA-F0-9]{64}$/', $query) === 1) {
        $block = explorer_block_by_hash(strtolower($query));
        if ($block !== null) {
            $searchResult = explorer_render_block($block);
        } else {
            $tx = explorer_tx(strtolower($query));
            $searchResult = $tx !== null ? explorer_render_tx($tx) : '<section class="notice"><strong>No result:</strong> No real block hash or txid was found for that search.</section>';
        }
    } else {
        $scan = explorer_address_scan($query);
        $searchResult = $scan !== null ? explorer_render_address($query, $scan) : '<section class="notice"><strong>No result:</strong> Address lookup is unavailable for this value. Use a real HOBC address.</section>';
    }
}

$pageId = 'explorer';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'explorer';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
require __DIR__ . '/../includes/status-bar.php';
?>
<main id="main-content">
  <div class="page">
    <?php if (!$explorerPublicEnabled): ?>
      <section class="hero"><div class="hero-content"><span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span><h1><?= hobc_tpe($pageId, 'hero.title') ?></h1><p><?= explorer_h($explorerMaintenanceMessage) ?></p></div></section>
    <?php else: ?>
    <section class="hero"><div class="hero-content"><span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span><h1><?= hobc_tpe($pageId, 'hero.title') ?></h1><p><?= hobc_tpe($pageId, 'body.p1') ?></p></div></section>
    <section class="grid cards">
      <div class="metric-card"><span class="metric-label"><?= hobc_tpe($pageId, 'label.explorer_status') ?></span><strong data-api-value="/api/explorer/status" data-field="status" data-fallback="<?= hobc_te('status.syncing') ?>"><?= hobc_te('status.syncing') ?></strong></div>
      <div class="metric-card"><span class="metric-label"><?= hobc_tpe($pageId, 'label.current_height') ?></span><strong><?= explorer_h((string)$latest['height']) ?></strong></div>
      <div class="metric-card"><span class="metric-label"><?= hobc_tpe($pageId, 'label.data_source') ?></span><strong><?= hobc_tpe($pageId, 'label.data_source_value') ?></strong></div>
      <div class="metric-card"><span class="metric-label"><?= hobc_tpe($pageId, 'label.explorer_db') ?></span><strong>hobbyhash_explorer</strong></div>
    </section>
    <section class="notice"><?= hobc_tp($pageId, 'notice.1') ?></section>
    <form class="search-box" action="/explorer/" method="get"><input name="q" value="<?= explorer_h($query) ?>" placeholder="<?= hobc_tpe($pageId, 'search.placeholder') ?>"><button class="button primary" type="submit"><?= hobc_te('button.search') ?></button></form>
    <?php if ($searchResult !== ''): ?>
      <section>
        <?= $searchResult ?>
      </section>
    <?php endif; ?>
    <section class="grid two">
      <article class="card">
        <h2><?= hobc_tpe($pageId, 'heading.latest_blocks') ?></h2>
        <?php if ($latest['blocks'] === []): ?>
          <div class="notice"><?= hobc_tpe($pageId, 'notice.latest_blocks_unavailable') ?></div>
        <?php else: ?>
          <div class="burn-tx-list">
            <?php foreach ($latest['blocks'] as $block): ?>
              <article class="burn-tx-card">
                <div class="burn-tx-field burn-txid"><span><?= explorer_h(explorer_label_for_block($block)) ?></span><strong><?= explorer_render_hash_link((string)($block['hash'] ?? 'not_available')) ?></strong></div>
                <div class="burn-tx-field"><span><?= hobc_te('label.height') ?></span><strong><a href="/explorer/?q=<?= explorer_h((string)($block['height'] ?? '')) ?>"><?= explorer_h((string)($block['height'] ?? 'not_available')) ?></a></strong></div>
                <div class="burn-tx-field"><span><?= hobc_te('label.transactions') ?></span><strong><?= explorer_h((string)count(is_array($block['tx'] ?? null) ? $block['tx'] : [])) ?></strong></div>
                <div class="burn-tx-field"><span><?= hobc_te('label.time') ?></span><strong><?= explorer_h(explorer_time($block['time'] ?? null)) ?></strong></div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </article>
      <article class="card">
        <h2><?= hobc_tpe($pageId, 'heading.latest_transactions') ?></h2>
        <?php if ($latestTransactions === []): ?>
          <p><?= hobc_tpe($pageId, 'body.p2') ?></p>
        <?php else: ?>
          <div class="burn-tx-list">
            <?php foreach ($latestTransactions as $tx): ?>
              <article class="burn-tx-card">
                <div class="burn-tx-field burn-txid"><span><?= hobc_te('label.txid') ?></span><strong><?= explorer_render_hash_link($tx['txid']) ?></strong></div>
                <div class="burn-tx-field"><span><?= hobc_te('label.height') ?></span><strong><a href="/explorer/?q=<?= explorer_h((string)$tx['height']) ?>"><?= explorer_h((string)$tx['height']) ?></a></strong></div>
                <div class="burn-tx-field"><span><?= hobc_te('label.time') ?></span><strong><?= explorer_h(explorer_time($tx['time'])) ?></strong></div>
                <div class="burn-tx-field"><span><?= hobc_te('label.source') ?></span><strong><?= hobc_tpe($pageId, 'label.source_block_tx_list') ?></strong></div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </article>
    </section>
    <section class="grid cards">
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.launch_reserve_block.title') ?></h3><p><?= hobc_tp($pageId, 'card.launch_reserve_block.body') ?></p><a class="button" href="/explorer/?q=1"><?= hobc_tpe($pageId, 'button.view_block_1') ?></a></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.reserve_addresses.title') ?></h3><p><?= hobc_tp($pageId, 'body.p3') ?></p><a class="button" href="/launch-reserve/"><?= hobc_tpe($pageId, 'button.reserve_details') ?></a></article>
      <article class="card"><h3><?= hobc_tpe($pageId, 'card.burn_address.title') ?></h3><p><?= hobc_tpe($pageId, 'body.p4') ?></p><p class="code-row"><?= explorer_h(HOBC_BURN_ADDRESS) ?></p><a class="button" href="/explorer/?q=<?= explorer_h(rawurlencode(HOBC_BURN_ADDRESS)) ?>"><?= hobc_tpe($pageId, 'button.search_burn_address') ?></a></article>
    </section>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
