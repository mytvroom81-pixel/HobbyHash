<?php
declare(strict_types=1);

const HOBC_GEOIP_DIR = '/home/hobbyhashcoin/geoip';

function geoip_client_ip(): string
{
    $candidates = [
        (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
        (string)($_SERVER['HTTP_X_REAL_IP'] ?? ''),
        (string)($_SERVER['REMOTE_ADDR'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim(explode(',', $candidate)[0]);
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return '0.0.0.0';
}

function geoip_cloudflare_country(): ?string
{
    $country = strtoupper(trim((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')));
    return preg_match('/^[A-Z]{2}$/', $country) ? $country : null;
}

function geoip_autoload(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
    $loaded = true;
}

function geoip_db_path(string $name): ?string
{
    $paths = [
        'city' => HOBC_GEOIP_DIR . '/GeoLite2-City.mmdb',
        'country' => HOBC_GEOIP_DIR . '/GeoLite2-Country.mmdb',
        'asn' => HOBC_GEOIP_DIR . '/GeoLite2-ASN.mmdb',
    ];
    $path = $paths[$name] ?? null;
    return ($path !== null && is_readable($path)) ? $path : null;
}

function geoip_empty_result(): array
{
    return [
        'country_code' => null,
        'city_name' => null,
        'region_name' => null,
        'asn_number' => null,
        'asn_org' => null,
    ];
}

function geoip_lookup(?string $ip = null): array
{
    static $cache = [];
    $ip = trim($ip ?? geoip_client_ip());
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return geoip_empty_result();
    }
    if (isset($cache[$ip])) {
        return $cache[$ip];
    }

    $result = geoip_empty_result();
    $cfCountry = geoip_cloudflare_country();
    if ($cfCountry !== null) {
        $result['country_code'] = $cfCountry;
    }

    geoip_autoload();
    if (!class_exists('GeoIp2\\Database\\Reader')) {
        $cache[$ip] = $result;
        return $result;
    }

    try {
        $cityPath = geoip_db_path('city');
        if ($cityPath !== null) {
            $cityReader = new GeoIp2\Database\Reader($cityPath);
            $record = $cityReader->city($ip);
            $result['country_code'] = $record->country->isoCode ?: $result['country_code'];
            $result['city_name'] = $record->city->name ?: null;
            $subdivisions = $record->subdivisions;
            if ($subdivisions !== []) {
                $result['region_name'] = $subdivisions[0]->name ?: $subdivisions[0]->isoCode ?: null;
            }
            $cityReader->close();
        } elseif (($countryPath = geoip_db_path('country')) !== null) {
            $countryReader = new GeoIp2\Database\Reader($countryPath);
            $record = $countryReader->country($ip);
            $result['country_code'] = $record->country->isoCode ?: $result['country_code'];
            $countryReader->close();
        }
    } catch (Throwable $e) {
        wallet_log_error('geoip city lookup skipped: ' . $e->getMessage());
    }

    try {
        $asnPath = geoip_db_path('asn');
        if ($asnPath !== null) {
            $asnReader = new GeoIp2\Database\Reader($asnPath);
            $record = $asnReader->asn($ip);
            $result['asn_number'] = $record->autonomousSystemNumber !== null ? (int)$record->autonomousSystemNumber : null;
            $result['asn_org'] = $record->autonomousSystemOrganization ?: null;
            $asnReader->close();
        }
    } catch (Throwable $e) {
        wallet_log_error('geoip asn lookup skipped: ' . $e->getMessage());
    }

    if ($result['country_code'] !== null) {
        $result['country_code'] = strtoupper((string)$result['country_code']);
    }
    if ($result['city_name'] !== null) {
        $result['city_name'] = substr((string)$result['city_name'], 0, 120);
    }
    if ($result['region_name'] !== null) {
        $result['region_name'] = substr((string)$result['region_name'], 0, 120);
    }
    if ($result['asn_org'] !== null) {
        $result['asn_org'] = substr((string)$result['asn_org'], 0, 255);
    }

    $cache[$ip] = $result;
    return $result;
}

function geoip_analytics_payload(?string $ip = null): array
{
    $geo = geoip_lookup($ip);
    return [
        'country_code' => $geo['country_code'],
        'city_name' => $geo['city_name'],
        'region_name' => $geo['region_name'],
        'asn_number' => $geo['asn_number'],
        'asn_org' => $geo['asn_org'],
    ];
}

function geoip_columns_enabled(string $table): bool
{
    return analytics_column_exists($table, 'city_name')
        && analytics_column_exists($table, 'region_name')
        && analytics_column_exists($table, 'asn_number')
        && analytics_column_exists($table, 'asn_org');
}

function geoip_format_location(array $row): string
{
    $parts = array_filter([
        trim((string)($row['city_name'] ?? '')),
        trim((string)($row['region_name'] ?? '')),
        trim((string)($row['country_code'] ?? '')),
    ], static fn(string $part): bool => $part !== '');
    return $parts !== [] ? implode(', ', $parts) : 'not_available';
}

function geoip_format_asn(array $row): string
{
    $asn = (int)($row['asn_number'] ?? 0);
    $org = trim((string)($row['asn_org'] ?? ''));
    if ($asn <= 0 && $org === '') {
        return 'not_available';
    }
    if ($asn > 0 && $org !== '') {
        return 'AS' . $asn . ' · ' . $org;
    }
    return $asn > 0 ? 'AS' . $asn : $org;
}

function geoip_backfill_table(PDO $pdo, string $table, int $limit = 500): int
{
    require_once __DIR__ . '/analytics.php';
    if (!analytics_table_exists($table) || !geoip_columns_enabled($table)) {
        return 0;
    }
    if (!analytics_column_exists($table, 'ip_address')) {
        return 0;
    }

    $idColumn = $table === 'site_visitors' ? 'visitor_id' : 'id';
    $orderColumn = $table === 'site_visitors' ? 'last_seen_at' : 'id';
    $limit = max(1, min($limit, 5000));
    $updated = 0;

    $update = $pdo->prepare(
        "UPDATE `{$table}`
         SET country_code = ?,
             city_name = ?,
             region_name = ?,
             asn_number = ?,
             asn_org = ?
         WHERE {$idColumn} = ?"
    );

    while (true) {
        $stmt = $pdo->prepare(
            "SELECT {$idColumn} AS row_key, ip_address
             FROM `{$table}`
             WHERE ip_address IS NOT NULL
               AND ip_address <> ''
               AND (country_code IS NULL AND city_name IS NULL AND asn_number IS NULL)
             ORDER BY {$orderColumn} DESC
             LIMIT {$limit}"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if ($rows === []) {
            break;
        }

        foreach ($rows as $row) {
            $geo = geoip_lookup((string)$row['ip_address']);
            $update->execute([
                $geo['country_code'],
                $geo['city_name'],
                $geo['region_name'],
                $geo['asn_number'],
                $geo['asn_org'],
                $row['row_key'],
            ]);
            $updated++;
        }

        if (count($rows) < $limit) {
            break;
        }
    }

    return $updated;
}

function geoip_backfill_all(PDO $pdo, int $batchSize = 500): array
{
    require_once __DIR__ . '/analytics.php';
    $summary = [];
    foreach (['site_visitors', 'site_pageviews', 'bot_events'] as $table) {
        $summary[$table] = geoip_backfill_table($pdo, $table, $batchSize);
    }
    return $summary;
}

function geoip_purge_rows_without_ip(PDO $pdo): array
{
    require_once __DIR__ . '/analytics.php';
    $deleted = [];
    foreach (['site_visitors', 'site_pageviews', 'bot_events', 'download_events'] as $table) {
        if (!analytics_table_exists($table) || !analytics_column_exists($table, 'ip_address')) {
            continue;
        }
        $count = (int)$pdo->query(
            "SELECT COUNT(*) FROM `{$table}` WHERE ip_address IS NULL OR ip_address = ''"
        )->fetchColumn();
        if ($count > 0) {
            $pdo->exec("DELETE FROM `{$table}` WHERE ip_address IS NULL OR ip_address = ''");
        }
        $deleted[$table] = $count;
    }
    return $deleted;
}

function geoip_maintain_analytics(PDO $pdo, int $batchSize = 500): array
{
    $backfilled = geoip_backfill_all($pdo, $batchSize);
    $deleted = geoip_purge_rows_without_ip($pdo);
    return ['backfilled' => $backfilled, 'deleted' => $deleted];
}
