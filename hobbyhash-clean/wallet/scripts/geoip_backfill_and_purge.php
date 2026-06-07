<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once __DIR__ . '/../../../public_html/app/bootstrap.php';
require_once __DIR__ . '/../../../public_html/app/db.php';
require_once __DIR__ . '/../../../public_html/app/analytics.php';
require_once __DIR__ . '/../../../public_html/app/geoip.php';

$pdo = wallet_db();
$batchSize = max(100, min(5000, (int)($argv[1] ?? 500)));
$dryRun = in_array('--dry-run', $argv, true);

$geoTables = ['site_visitors', 'site_pageviews', 'bot_events'];
$purgeTables = ['site_visitors', 'site_pageviews', 'bot_events', 'download_events'];

function geoip_table_stats(PDO $pdo, string $table): array
{
    if (!analytics_table_exists($table)) {
        return ['exists' => false];
    }
    $hasIp = analytics_column_exists($table, 'ip_address');
    $total = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    $withIp = $hasIp
        ? (int)$pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE ip_address IS NOT NULL AND ip_address <> ''")->fetchColumn()
        : 0;
    $withoutIp = $hasIp ? $total - $withIp : $total;
    $needsGeo = 0;
    if ($hasIp && geoip_columns_enabled($table)) {
        $needsGeo = (int)$pdo->query(
            "SELECT COUNT(*) FROM `{$table}` WHERE ip_address IS NOT NULL AND ip_address <> '' AND country_code IS NULL AND city_name IS NULL AND asn_number IS NULL"
        )->fetchColumn();
    }
    return [
        'exists' => true,
        'total' => $total,
        'with_ip' => $withIp,
        'without_ip' => $withoutIp,
        'needs_geo' => $needsGeo,
        'has_ip_column' => $hasIp,
    ];
}

echo "GeoIP backfill & purge\n";
echo "Batch size: {$batchSize}\n";
echo $dryRun ? "Mode: DRY RUN (no writes)\n" : "Mode: LIVE\n";
echo str_repeat('-', 60) . "\n";

echo "Before:\n";
foreach (array_merge($geoTables, ['download_events']) as $table) {
    $stats = geoip_table_stats($pdo, $table);
    if (!$stats['exists']) {
        echo "  {$table}: missing\n";
        continue;
    }
    echo "  {$table}: total={$stats['total']} with_ip={$stats['with_ip']} without_ip={$stats['without_ip']} needs_geo={$stats['needs_geo']}\n";
}

$distinctIps = (int)$pdo->query(
    "SELECT COUNT(*) FROM (
        SELECT ip_address FROM site_visitors WHERE ip_address IS NOT NULL AND ip_address <> ''
        UNION
        SELECT ip_address FROM site_pageviews WHERE ip_address IS NOT NULL AND ip_address <> ''
        UNION
        SELECT ip_address FROM bot_events WHERE ip_address IS NOT NULL AND ip_address <> ''
        UNION
        SELECT ip_address FROM download_events WHERE ip_address IS NOT NULL AND ip_address <> ''
    ) ips"
)->fetchColumn();
echo "Distinct IPs stored: {$distinctIps}\n\n";

if ($dryRun) {
    $wouldDelete = [];
    foreach ($purgeTables as $table) {
        if (!analytics_table_exists($table) || !analytics_column_exists($table, 'ip_address')) {
            continue;
        }
        $wouldDelete[$table] = (int)$pdo->query(
            "SELECT COUNT(*) FROM `{$table}` WHERE ip_address IS NULL OR ip_address = ''"
        )->fetchColumn();
    }
    echo "Would delete rows without IP:\n";
    foreach ($wouldDelete as $table => $count) {
        echo "  {$table}: {$count}\n";
    }
    echo "\nWould backfill geo on remaining rows (run without --dry-run to apply).\n";
    exit(0);
}

echo "Step 1: Backfill GeoIP on all rows with stored IPs...\n";
$backfilled = geoip_backfill_all($pdo, $batchSize);
foreach ($backfilled as $table => $count) {
    echo "  {$table}: updated {$count} rows\n";
}

echo "\nStep 2: Remove legacy rows without raw IP address...\n";
$deleted = geoip_purge_rows_without_ip($pdo);
foreach ($deleted as $table => $count) {
    echo "  {$table}: deleted {$count} rows\n";
}

echo "\nAfter:\n";
foreach (array_merge($geoTables, ['download_events']) as $table) {
    $stats = geoip_table_stats($pdo, $table);
    if (!$stats['exists']) {
        continue;
    }
    echo "  {$table}: total={$stats['total']} with_ip={$stats['with_ip']} without_ip={$stats['without_ip']} needs_geo={$stats['needs_geo']}\n";
}

echo "\nDone.\n";
