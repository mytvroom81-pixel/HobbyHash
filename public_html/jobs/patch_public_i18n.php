<?php
declare(strict_types=1);

/**
 * Patch public PHP pages to use hobc_tp / hobc_t / hobc_metric_card_t etc.
 * Run after build_i18n_catalogs.php: php jobs/patch_public_i18n.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/i18n_catalog.php';

/** @var array<string, string> */
$pageIds = [
    'index.php' => 'home',
    'contact.php' => 'contact',
    'ticket.php' => 'ticket',
    'about/index.php' => 'about',
    'mining/index.php' => 'mining',
    'pool/main/index.php' => 'pool_main',
    'pool/nano/index.php' => 'pool_nano',
    'explorer/index.php' => 'explorer',
    'stats/index.php' => 'stats',
    'downloads/index.php' => 'downloads',
    'launch-reserve/index.php' => 'reserve',
    'burn/index.php' => 'burn',
    'exchange-listing/index.php' => 'exchange_listing',
    'wallet/index.php' => 'wallet',
    'privacy/index.php' => 'privacy',
    'terms/index.php' => 'terms',
    'roadmap/index.php' => 'roadmap',
    'docs/index.php' => 'docs_index',
    'docs/getting-started/index.php' => 'docs_getting_started',
    'docs/wallet-guide/index.php' => 'docs_wallet_guide',
    'docs/linux-node/index.php' => 'docs_linux_node',
    'docs/mining-guide/index.php' => 'docs_mining_guide',
    'docs/pool-stats/index.php' => 'docs_pool_stats',
    'docs/ports-configuration/index.php' => 'docs_ports_configuration',
    'docs/security-guide/index.php' => 'docs_security_guide',
    'docs/explorer-guide/index.php' => 'docs_explorer_guide',
    'docs/cli-rpc/index.php' => 'docs_cli_rpc',
    'docs/faq/index.php' => 'docs_faq',
];

function patch_i18n_bootstrap(string $content, string $rel): string
{
    if (str_contains($content, 'hobc_i18n_bootstrap()') || str_contains($content, "require_once __DIR__ . '/app/i18n.php'")) {
        return $content;
    }

    $depth = substr_count($rel, '/');
    $i18nPath = str_repeat('/..', $depth) . '/app/i18n.php';
    if ($depth === 0) {
        $i18nPath = __DIR__ . '/../app/i18n.php';
        $require = "require_once __DIR__ . '/app/i18n.php';\nhobc_i18n_bootstrap();\n";
    } else {
        $require = "require_once __DIR__ . '{$i18nPath}';\nhobc_i18n_bootstrap();\n";
    }

    if (preg_match('/^<\?php\s*\n(?:declare\(strict_types=1\);\s*\n)?/', $content)) {
        return preg_replace(
            '/^(<\?php\s*\n(?:declare\(strict_types=1\);\s*\n)?)/',
            "$1{$require}",
            $content,
            1
        ) ?? $content;
    }

    return $content;
}

function patch_page_header(string $content, string $pageId): string
{
    if (str_contains($content, "\$pageId = ")) {
        return $content;
    }

    $content = preg_replace(
        '/\$pageTitle\s*=\s*[\'"][^\'"]+[\'"];\s*\n\$pageDescription\s*=\s*[\'"][^\'"]+[\'"];/',
        "\$pageId = '$pageId';\n\$pageTitle = hobc_tp(\$pageId, 'meta.title');\n\$pageDescription = hobc_tp(\$pageId, 'meta.description');",
        $content,
        1
    ) ?? $content;

    if (!str_contains($content, 'hobc_tp(') && preg_match('/\$pageTitle\s*=\s*[\'"][^\'"]+[\'"];/', $content)) {
        $content = preg_replace(
            '/(\<\?php\s*\n(?:declare\(strict_types=1\);\s*\n)?)/',
            "$1\$pageId = '$pageId';\n\$pageTitle = hobc_tp(\$pageId, 'meta.title');\n\$pageDescription = hobc_tp(\$pageId, 'meta.description');\n",
            $content,
            1
        ) ?? $content;
        $content = preg_replace('/\$pageTitle\s*=\s*hobc_tp[^;]+;\s*\n\$pageTitle\s*=\s*[\'"][^\'"]+[\'"];/', '$pageTitle = hobc_tp($pageId, \'meta.title\');', $content) ?? $content;
        $content = preg_replace('/\$pageDescription\s*=\s*[\'"][^\'"]+[\'"];\s*\n/', '', $content) ?? $content;
    }

    return $content;
}

function patch_catalog_strings(string $content, string $pageId, array $catalog): string
{
    uksort($catalog, static fn(string $a, string $b): int => strlen($catalog[$b]) <=> strlen($catalog[$a]));

    foreach ($catalog as $key => $value) {
        if ($key === 'meta.title' || $key === 'meta.description') {
            continue;
        }
        if ($value === '' || str_contains($value, '<?')) {
            continue;
        }

        $plain = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($plain === '' || strlen($plain) < 4) {
            continue;
        }

        $escaped = preg_quote($plain, '/');
        $phpCall = str_contains($value, '<') && strip_tags($value) !== $value
            ? "<?= hobc_tp(\$pageId, '$key') ?>"
            : "<?= hobc_tpe(\$pageId, '$key') ?>";

        $closingTags = ['', '</p>', '</a>', '</h1>', '</h2>', '</h3>', '</span>', '</strong>', '</label>', '</button>', '</li>'];
        foreach ($closingTags as $close) {
            $pattern = '/>\s*' . $escaped . '\s*' . preg_quote($close, '/') . '/u';
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, '>' . $phpCall . $close, $content, 1) ?? $content;
                continue 2;
            }
        }

        if (preg_match('/>\s*' . $escaped . '\s*</u', $content)) {
            $content = preg_replace('/>\s*' . $escaped . '\s*</u', '>' . $phpCall . '<', $content, 1) ?? $content;
            continue;
        }

        if (str_contains($value, '<strong>') || str_contains($value, '<span')) {
            $escapedHtml = preg_quote($value, '/');
            $escapedHtml = str_replace(['\\"', "\\'"], ['"', "'"], $escapedHtml);
            if (preg_match('/' . $escapedHtml . '/s', $content)) {
                $content = preg_replace('/' . preg_quote($value, '/') . '/s', $phpCall, $content, 1) ?? $content;
            }
        }
    }

    return $content;
}

function patch_metric_cards(string $content): string
{
    $map = [
        'Current chain height' => 'metrics.chain_height',
        'Network difficulty' => 'metrics.network_difficulty',
        'Network hashrate estimate' => 'metrics.network_hashrate',
        'Latest block' => 'metrics.latest_block',
        'Circulating supply' => 'metrics.circulating_supply',
        'Launch reserve' => 'metrics.launch_reserve',
        'Burn amount' => 'metrics.burn_amount',
        'Main pool status' => 'metrics.main_pool_status',
        'Nano pool status' => 'metrics.nano_pool_status',
        'Wallet status' => 'metrics.wallet_status',
        'Explorer status' => 'metrics.explorer_status',
        'Chain height' => 'metrics.chain_height',
        'Latest block hash' => 'metrics.latest_block_hash',
        'Latest block time' => 'metrics.latest_block_time',
        'Current difficulty' => 'metrics.current_difficulty',
        'Estimated network hashrate' => 'metrics.network_hashrate',
        'Total mined estimate' => 'metrics.total_mined',
        'Launch reserve balance' => 'metrics.launch_reserve_balance',
        'Burned supply' => 'metrics.burned_supply',
        'Active nodes' => 'metrics.active_nodes',
        'Mempool tx count' => 'metrics.mempool_tx',
        'Main pool hashrate' => 'metrics.main_pool_hashrate',
        'Nano pool hashrate' => 'metrics.nano_pool_hashrate',
        'Main pool workers' => 'metrics.main_pool_workers',
        'Nano pool workers' => 'metrics.nano_pool_workers',
        'Main accepted shares' => 'metrics.main_accepted_shares',
        'Nano accepted shares' => 'metrics.nano_accepted_shares',
        'Main rejected shares' => 'metrics.main_rejected_shares',
        'Nano rejected shares' => 'metrics.nano_rejected_shares',
        'Pool last share' => 'metrics.pool_last_share',
        'Wallet backend health' => 'metrics.wallet_backend_health',
        'Explorer sync status' => 'metrics.explorer_sync_status',
        'Status' => 'metrics.status',
        'Total burned' => 'metrics.total_burned',
        'Burn addresses' => 'metrics.burn_addresses',
        'Burn tx outputs' => 'metrics.burn_tx_outputs',
        'Scan height' => 'metrics.scan_height',
        'TXOs scanned' => 'metrics.txos_scanned',
        'Mining focus' => 'metrics.mining_focus',
        'Target supply' => 'metrics.target_supply',
        'ASIC miners' => 'metrics.asic_miners',
        'Small miners' => 'metrics.small_miners',
        'Payout target' => 'metrics.payout_target',
        'Existing user' => 'metrics.existing_user',
        'New user' => 'metrics.new_user',
        'Wallet app' => 'metrics.wallet_app',
        'Current height' => 'metrics.current_height',
        'Data source' => 'metrics.data_source',
        'Explorer DB' => 'metrics.explorer_db',
        'Total supply' => 'metrics.total_supply',
        'Reserve percent' => 'metrics.reserve_percent',
        'Current balance' => 'metrics.current_balance',
        'Outgoing spends' => 'metrics.outgoing_spends',
    ];

    foreach ($map as $label => $key) {
        $pattern = "/hobc_metric_card\\('\\Q{$label}\\E',/";
        if (preg_match($pattern, $content)) {
            $content = preg_replace(
                $pattern,
                "hobc_metric_card_t('{$key}',",
                $content
            ) ?? $content;
        }
    }

    $content = preg_replace(
        "/hobc_metric_card_t\\('([^']+)', '([^']+)', '([^']+)', 'Syncing'/",
        "hobc_metric_card_t('$1', '$2', '$3', 'status.syncing'",
        $content
    ) ?? $content;
    $content = preg_replace(
        "/hobc_metric_card_t\\('([^']+)', '([^']+)', '([^']+)', 'Offline'/",
        "hobc_metric_card_t('$1', '$2', '$3', 'status.offline'",
        $content
    ) ?? $content;
    $content = preg_replace(
        "/hobc_metric_card_t\\('([^']+)', '([^']+)', '([^']+)', 'Not available yet'/",
        "hobc_metric_card_t('$1', '$2', '$3', 'status.not_available'",
        $content
    ) ?? $content;
    $content = preg_replace(
        "/hobc_metric_card_t\\('([^']+)', '([^']+)', '([^']+)', 'Pending launch'/",
        "hobc_metric_card_t('$1', '$2', '$3', 'status.pending_launch'",
        $content
    ) ?? $content;

    return $content;
}

function patch_status_fallbacks(string $content): string
{
    $replacements = [
        'data-fallback="Syncing"' => 'data-fallback="<?= hobc_te(\'status.syncing\') ?>"',
        'data-fallback="Offline"' => 'data-fallback="<?= hobc_te(\'status.offline\') ?>"',
        'data-fallback="Not available yet"' => 'data-fallback="<?= hobc_te(\'status.not_available\') ?>"',
        'data-fallback="Pending launch"' => 'data-fallback="<?= hobc_te(\'status.pending_launch\') ?>"',
        '>Syncing</strong>' => '><?= hobc_te(\'status.syncing\') ?></strong>',
        '>Offline</strong>' => '><?= hobc_te(\'status.offline\') ?></strong>',
        '>Not available yet</strong>' => '><?= hobc_te(\'status.not_available\') ?></strong>',
    ];
    foreach ($replacements as $from => $to) {
        $content = str_replace($from, $to, $content);
    }
    return $content;
}

foreach ($pageIds as $rel => $pageId) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        continue;
    }
    $catalog = hobc_i18n_load_page_catalog('en', $pageId);
    $content = (string)file_get_contents($path);
    $content = patch_i18n_bootstrap($content, $rel);
    $content = patch_page_header($content, $pageId);
    $content = patch_catalog_strings($content, $pageId, $catalog);
    $content = patch_metric_cards($content);
    $content = patch_status_fallbacks($content);
    file_put_contents($path, $content);
    echo "Patched $rel\n";
}

echo "Patching includes...\n";

// nav.php
$nav = (string)file_get_contents($root . '/includes/nav.php');
$nav = str_replace('aria-label="Main navigation"', 'aria-label="<?= hobc_te(\'nav.aria_main\') ?>"', $nav);
file_put_contents($root . '/includes/nav.php', $nav);

// status-bar.php - simplify to use hobc_te consistently
$statusBar = <<<'PHP'
<?php declare(strict_types=1); ?>
<section class="status-bar" aria-label="<?= hobc_te('wallet.live_status') ?>">
  <a href="/stats/" class="status-pill"><span><?= hobc_te('status.chain') ?></span><strong data-api-value="/api/chain/status" data-field="status" data-fallback="<?= hobc_te('status.syncing') ?>"><?= hobc_te('status.syncing') ?></strong></a>
  <a href="/stats/" class="status-pill"><span><?= hobc_te('status.height') ?></span><strong data-api-value="/api/chain/status" data-field="blocks" data-fallback="<?= hobc_te('status.syncing') ?>"><?= hobc_te('status.syncing') ?></strong></a>
  <a href="/pool/main/" class="status-pill"><span><?= hobc_te('status.main_pool') ?></span><strong data-api-value="/api/pool/main/status?lite=1" data-field="status" data-fallback="<?= hobc_te('status.offline') ?>"><?= hobc_te('status.offline') ?></strong></a>
  <a href="/pool/nano/" class="status-pill"><span><?= hobc_te('status.nano_pool') ?></span><strong data-api-value="/api/pool/nano/status?lite=1" data-field="status" data-fallback="<?= hobc_te('status.offline') ?>"><?= hobc_te('status.offline') ?></strong></a>
  <a href="/wallet/" class="status-pill"><span><?= hobc_te('status.wallet') ?></span><strong data-api-value="/api/wallet/status" data-field="status" data-fallback="<?= hobc_te('status.offline') ?>"><?= hobc_te('status.offline') ?></strong></a>
  <a href="/explorer/" class="status-pill"><span><?= hobc_te('status.explorer') ?></span><strong data-api-value="/api/explorer/status" data-field="status" data-fallback="<?= hobc_te('status.syncing') ?>"><?= hobc_te('status.syncing') ?></strong></a>
</section>

PHP;
file_put_contents($root . '/includes/status-bar.php', $statusBar);

// header.php brand aria
$header = (string)file_get_contents($root . '/includes/header.php');
$header = str_replace(
    'aria-label="<?= hobc_e($coinTicker) ?> home dashboard"',
    'aria-label="<?= hobc_te(\'header.home_dashboard\', [], $coinTicker . \' home dashboard\') ?>"',
    $header
);
if (!str_contains($header, "hobc_te('header.home_dashboard'")) {
    $header = str_replace(
        'aria-label="<?= hobc_e($coinTicker) ?> home dashboard"',
        'aria-label="<?= hobc_te(\'header.home_dashboard\') ?>"',
        $header
    );
}
$header = preg_replace(
    '/aria-label="<\?= hobc_e\(\$coinTicker\) \?> home dashboard"/',
    'aria-label="<?= hobc_te(\'header.home_dashboard\') ?>"',
    $header
) ?? $header;
$header = str_replace(
    '<a class="brand" href="/" aria-label="<?= hobc_e($coinTicker) ?> home dashboard">',
    '<a class="brand" href="/" aria-label="<?= hobc_te(\'header.home_dashboard\') ?>">',
    $header
);
file_put_contents($root . '/includes/header.php', $header);

echo "Done patching.\n";
