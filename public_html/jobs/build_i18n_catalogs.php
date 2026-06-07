<?php
declare(strict_types=1);

/**
 * Build lang/en/pages/*.json from public PHP sources.
 * Run: php jobs/build_i18n_catalogs.php
 */

$root = dirname(__DIR__);
$pagesDir = $root . '/lang/en/pages';
if (!is_dir($pagesDir)) {
    mkdir($pagesDir, 0755, true);
}

/** @var array<string, array{file:string, id:string}> */
$pageMap = [
    'index.php' => ['file' => 'index.php', 'id' => 'home'],
    'contact.php' => ['file' => 'contact.php', 'id' => 'contact'],
    'ticket.php' => ['file' => 'ticket.php', 'id' => 'ticket'],
    'about/index.php' => ['file' => 'about/index.php', 'id' => 'about'],
    'mining/index.php' => ['file' => 'mining/index.php', 'id' => 'mining'],
    'pool/main/index.php' => ['file' => 'pool/main/index.php', 'id' => 'pool_main'],
    'pool/nano/index.php' => ['file' => 'pool/nano/index.php', 'id' => 'pool_nano'],
    'explorer/index.php' => ['file' => 'explorer/index.php', 'id' => 'explorer'],
    'stats/index.php' => ['file' => 'stats/index.php', 'id' => 'stats'],
    'downloads/index.php' => ['file' => 'downloads/index.php', 'id' => 'downloads'],
    'launch-reserve/index.php' => ['file' => 'launch-reserve/index.php', 'id' => 'reserve'],
    'burn/index.php' => ['file' => 'burn/index.php', 'id' => 'burn'],
    'exchange-listing/index.php' => ['file' => 'exchange-listing/index.php', 'id' => 'exchange_listing'],
    'wallet/index.php' => ['file' => 'wallet/index.php', 'id' => 'wallet'],
    'privacy/index.php' => ['file' => 'privacy/index.php', 'id' => 'privacy'],
    'terms/index.php' => ['file' => 'terms/index.php', 'id' => 'terms'],
    'roadmap/index.php' => ['file' => 'roadmap/index.php', 'id' => 'roadmap'],
    'docs/index.php' => ['file' => 'docs/index.php', 'id' => 'docs_index'],
    'docs/getting-started/index.php' => ['file' => 'docs/getting-started/index.php', 'id' => 'docs_getting_started'],
    'docs/wallet-guide/index.php' => ['file' => 'docs/wallet-guide/index.php', 'id' => 'docs_wallet_guide'],
    'docs/linux-node/index.php' => ['file' => 'docs/linux-node/index.php', 'id' => 'docs_linux_node'],
    'docs/mining-guide/index.php' => ['file' => 'docs/mining-guide/index.php', 'id' => 'docs_mining_guide'],
    'docs/pool-stats/index.php' => ['file' => 'docs/pool-stats/index.php', 'id' => 'docs_pool_stats'],
    'docs/ports-configuration/index.php' => ['file' => 'docs/ports-configuration/index.php', 'id' => 'docs_ports_configuration'],
    'docs/security-guide/index.php' => ['file' => 'docs/security-guide/index.php', 'id' => 'docs_security_guide'],
    'docs/explorer-guide/index.php' => ['file' => 'docs/explorer-guide/index.php', 'id' => 'docs_explorer_guide'],
    'docs/cli-rpc/index.php' => ['file' => 'docs/cli-rpc/index.php', 'id' => 'docs_cli_rpc'],
    'docs/faq/index.php' => ['file' => 'docs/faq/index.php', 'id' => 'docs_faq'],
];

function slug(string $text): string
{
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/i', '_', strtolower(trim($text))) ?? '';
    return trim($text, '_') ?: 'text';
}

function extractMeta(string $content): array
{
    $out = [];
    if (preg_match('/\$pageTitle\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
        $out['meta.title'] = $m[1];
    }
    if (preg_match('/\$pageDescription\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
        $out['meta.description'] = $m[1];
    }
    return $out;
}

function extractHero(string $html): array
{
    $out = [];
    if (preg_match('/<section class="hero[^"]*"[^>]*>.*?<span class="eyebrow">(.*?)<\/span>/s', $html, $m)) {
        $out['hero.eyebrow'] = trim($m[1]);
    }
    if (preg_match('/<section class="hero[^"]*"[^>]*>.*?<h1>(.*?)<\/h1>/s', $html, $m)) {
        $out['hero.title'] = trim($m[1]);
    }
    if (preg_match('/<section class="hero[^"]*"[^>]*>.*?<h1>.*?<\/h1>\s*<p>(.*?)<\/p>/s', $html, $m)) {
        $out['hero.lead'] = trim($m[1]);
    }
    return $out;
}

function extractCardSections(string $html): array
{
    $out = [];
    if (preg_match_all('/<section class="card">\s*<h3>(.*?)<\/h3>(.*?)(?=<\/section>)/s', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $title = trim(strip_tags($match[1]));
            $key = 'section.' . slug($title);
            $out[$key . '.title'] = trim($match[1]);
            if (preg_match_all('/<p>(.*?)<\/p>/s', $match[2], $ps)) {
                $n = 0;
                foreach ($ps[1] as $p) {
                    if (str_contains($p, '<?=')) {
                        continue;
                    }
                    $n++;
                    $out[$key . '.p' . $n] = trim($p);
                }
            }
            if (preg_match('/<div class="actions">(.*?)<\/div>/s', $match[2], $actions)) {
                if (preg_match_all('/<a[^>]*>(.*?)<\/a>/s', $actions[1], $links)) {
                    $i = 0;
                    foreach ($links[1] as $label) {
                        $i++;
                        $out[$key . '.link' . $i] = trim(strip_tags($label));
                    }
                }
            }
        }
    }
    return $out;
}

function extractFaqCards(string $html): array
{
    $out = [];
    if (preg_match_all('/<article class="card">\s*<h3>(.*?)<\/h3>\s*<p>(.*?)<\/p>/s', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $q = trim(strip_tags($match[1]));
            $key = 'faq.' . slug($q);
            $out[$key . '.question'] = trim($match[1]);
            $out[$key . '.answer'] = trim($match[2]);
        }
    }
    return $out;
}

function extractNotices(string $html): array
{
    $out = [];
    if (preg_match_all('/<section class="notice"[^>]*>(.*?)<\/section>/s', $html, $matches)) {
        foreach ($matches[1] as $i => $raw) {
            if (str_contains($raw, '<?=')) {
                continue;
            }
            $out['notice.' . ($i + 1)] = trim($raw);
        }
    }
    return $out;
}

function extractErrors(string $content): array
{
    $out = [];
    if (preg_match_all('/\$err\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach (array_unique($matches[1]) as $err) {
            $out['error.' . slug($err)] = $err;
        }
    }
    if (preg_match_all('/\$ok\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach (array_unique($matches[1]) as $ok) {
            $out['success.' . slug($ok)] = $ok;
        }
    }
    return $out;
}

function extractHeadings(string $html): array
{
    $out = [];
    if (preg_match_all('/<h2>(.*?)<\/h2>/s', $html, $matches)) {
        $seen = [];
        foreach ($matches[1] as $raw) {
            $text = trim(strip_tags($raw));
            if ($text === '' || isset($seen[$text])) {
                continue;
            }
            $seen[$text] = true;
            $out['heading.' . slug($text)] = trim($raw);
        }
    }
    return $out;
}

function extractTableLabels(string $html): array
{
    $out = [];
    if (preg_match_all('/<div class="table-row"><span>(.*?)<\/span>/s', $html, $matches)) {
        $seen = [];
        foreach ($matches[1] as $label) {
            $label = trim(strip_tags($label));
            if ($label === '' || isset($seen[$label]) || str_contains($label, '<?')) {
                continue;
            }
            $seen[$label] = true;
            $out['label.' . slug($label)] = $label;
        }
    }
    return $out;
}

function extractPlainText(string $html, string $pattern, string $prefix): array
{
    $out = [];
    if (preg_match_all($pattern, $html, $matches)) {
        foreach ($matches[1] as $raw) {
            if (str_contains($raw, '<?=')) {
                continue;
            }
            $text = trim(strip_tags($raw));
            if ($text === '') {
                continue;
            }
            $out[$prefix . slug($text)] = trim($raw);
        }
    }
    return $out;
}

/** @var array<string, array<string, string>> */
$manual = [
    'site_gate' => [
        'meta.title_prelaunch' => 'HOBC is getting ready to launch',
        'meta.title_maintenance' => 'HOBC is under maintenance',
        'meta.description_prelaunch' => 'The HobbyHash Coin command center is being prepared. Coming soon: home solo mining guides, main and nano solo pools, explorer, stats, downloads, launch reserve transparency, burn tracking, docs, and custodial wallet access with clear risk notices.',
        'meta.description_maintenance' => 'The HOBC website is temporarily unavailable while maintenance is completed.',
        'status.pre_launch' => 'Pre-launch',
        'status.maintenance' => 'Maintenance mode',
        'expected_launch' => 'Expected launch:',
        'maintenance_window.title' => 'Maintenance window',
        'maintenance_window.start' => 'Start:',
        'maintenance_window.end' => 'End:',
        'maintenance_window.not_set' => 'Not set',
        'card.home_mining.title' => 'Home solo mining',
        'card.home_mining.body' => 'HOBC is built around SHA-256 home solo mining with simple setup guides.',
        'card.pools.title' => 'Main and Nano Pools',
        'card.pools.body' => 'Main Pool for ASIC miners and Nano Pool for small SHA-256 miners. Solo pools only.',
        'card.transparent.title' => 'Transparent launch',
        'card.transparent.body' => 'The portal will show honest chain, pool, reserve, burn, explorer, and wallet status. No fake data.',
        'link.privacy' => 'Privacy Policy',
        'link.terms' => 'Terms',
    ],
];

foreach ($pageMap as $info) {
    $path = $root . '/' . $info['file'];
    $content = (string)file_get_contents($path);
    $html = $content;
    if (($pos = strpos($content, '<main')) !== false) {
        $html = substr($content, $pos);
    }

    $catalog = extractMeta($content);
    $catalog = array_merge($catalog, extractHero($html));
    $catalog = array_merge($catalog, extractNotices($html));
    $catalog = array_merge($catalog, extractHeadings($html));
    $catalog = array_merge($catalog, extractTableLabels($html));
    $catalog = array_merge($catalog, extractErrors($content));

    if (str_contains($info['file'], 'privacy') || str_contains($info['file'], 'terms')) {
        $catalog = array_merge($catalog, extractCardSections($html));
    }
    if (str_contains($info['file'], 'faq')) {
        $catalog = array_merge($catalog, extractFaqCards($html));
    }

    ksort($catalog);
    $json = json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents($pagesDir . '/' . $info['id'] . '.json', ($json ?: '{}') . "\n");
    echo $info['id'] . ': ' . count($catalog) . " keys\n";
}

foreach ($manual as $id => $catalog) {
    ksort($catalog);
    $json = json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents($pagesDir . '/' . $id . '.json', ($json ?: '{}') . "\n");
    echo $id . ': ' . count($catalog) . " keys (manual)\n";
}

echo "Done.\n";
