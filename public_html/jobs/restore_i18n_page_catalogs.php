<?php
declare(strict_types=1);

/**
 * Restore lang/en/pages/*.json from static meta + hardcoded strings in public PHP.
 * Does NOT overwrite keys that already have real English values (skips PHP snippets).
 * Run: php jobs/restore_i18n_page_catalogs.php && php jobs/patch_public_i18n.php
 */

$root = dirname(__DIR__);
$pagesDir = $root . '/lang/en/pages';
require_once $root . '/app/i18n_catalog.php';

/** @var array<string, array{file:string, id:string, meta:array<string,string>}> */
$pageMap = [
    'index.php' => ['file' => 'index.php', 'id' => 'home', 'meta' => [
        'meta.title' => 'HOBC Nano Miner Command Center',
        'meta.description' => 'HobbyHash Coin command center for nano miners, small SHA-256 miners, Bitaxe-style miners, solo pools, live stats, wallet safety, and HOBC docs.',
    ]],
    'contact.php' => ['file' => 'contact.php', 'id' => 'contact', 'meta' => [
        'meta.title' => 'Contact Support',
        'meta.description' => 'Open a HOBC support ticket.',
    ]],
    'ticket.php' => ['file' => 'ticket.php', 'id' => 'ticket', 'meta' => [
        'meta.title' => 'Ticket',
        'meta.description' => 'Track your HOBC support ticket.',
    ]],
    'about/index.php' => ['file' => 'about/index.php', 'id' => 'about', 'meta' => [
        'meta.title' => 'About HOBC',
        'meta.description' => 'About HobbyHash Coin, the HOBC website, and the home solo mining mission.',
    ]],
    'mining/index.php' => ['file' => 'mining/index.php', 'id' => 'mining', 'meta' => [
        'meta.title' => 'Mine HOBC With Nano and Small SHA-256 Miners',
        'meta.description' => 'Beginner-friendly HobbyHash Coin solo mining setup for nano miners, small SHA-256 miners, Bitaxe-style devices, NerdMiner-style hobby miners, and ASICs.',
    ]],
    'pool/main/index.php' => ['file' => 'pool/main/index.php', 'id' => 'pool_main', 'meta' => [
        'meta.title' => 'Main Pool',
        'meta.description' => 'HobbyHash Coin main solo pool stats, miner sessions, share difficulty, odds, and graphs.',
    ]],
    'pool/nano/index.php' => ['file' => 'pool/nano/index.php', 'id' => 'pool_nano', 'meta' => [
        'meta.title' => 'HOBC Nano Pool for Small SHA-256 Miners',
        'meta.description' => 'HobbyHash Coin Nano Pool for nano miners, small SHA-256 miners, Bitaxe-style devices, NerdMiner-style hobby miners, low share difficulty, solo stats, and live graphs.',
    ]],
    'explorer/index.php' => ['file' => 'explorer/index.php', 'id' => 'explorer', 'meta' => [
        'meta.title' => 'Explorer',
        'meta.description' => 'HOBC explorer search and sync status.',
    ]],
    'stats/index.php' => ['file' => 'stats/index.php', 'id' => 'stats', 'meta' => [
        'meta.title' => 'Stats',
        'meta.description' => 'Real HOBC chain, pool, wallet, and explorer stats.',
    ]],
    'downloads/index.php' => ['file' => 'downloads/index.php', 'id' => 'downloads', 'meta' => [
        'meta.title' => 'Downloads',
        'meta.description' => 'Official HobbyHash Coin downloads for the HOBC Linux node and Windows wallet installer, with SHA256 checksums and private RPC safety notes.',
    ]],
    'launch-reserve/index.php' => ['file' => 'launch-reserve/index.php', 'id' => 'reserve', 'meta' => [
        'meta.title' => 'Launch Reserve',
        'meta.description' => 'HOBC transparent 10 percent launch reserve.',
    ]],
    'burn/index.php' => ['file' => 'burn/index.php', 'id' => 'burn', 'meta' => [
        'meta.title' => 'Burn Tracker',
        'meta.description' => 'HOBC burn tracker with no fake burns.',
    ]],
    'exchange-listing/index.php' => ['file' => 'exchange-listing/index.php', 'id' => 'exchange_listing', 'meta' => [
        'meta.title' => 'Exchange Listing Packet',
        'meta.description' => 'Official HobbyHash Coin (HOBC) exchange listing packet: network parameters, supply, emission, genesis, checksums, seed nodes, and press kit downloads.',
    ]],
    'wallet/index.php' => ['file' => 'wallet/index.php', 'id' => 'wallet', 'meta' => [
        'meta.title' => 'Wallet',
        'meta.description' => 'HOBC custodial web wallet risk notice and links.',
    ]],
    'privacy/index.php' => ['file' => 'privacy/index.php', 'id' => 'privacy', 'meta' => [
        'meta.title' => 'Privacy Policy',
        'meta.description' => 'Privacy policy for HobbyHash Coin and the HOBC web wallet.',
    ]],
    'terms/index.php' => ['file' => 'terms/index.php', 'id' => 'terms', 'meta' => [
        'meta.title' => 'Terms',
        'meta.description' => 'Terms of use for HobbyHash Coin and the HOBC web wallet.',
    ]],
    'roadmap/index.php' => ['file' => 'roadmap/index.php', 'id' => 'roadmap', 'meta' => [
        'meta.title' => 'Roadmap',
        'meta.description' => 'HOBC roadmap and pending portal work.',
    ]],
    'docs/index.php' => ['file' => 'docs/index.php', 'id' => 'docs_index', 'meta' => [
        'meta.title' => 'Docs',
        'meta.description' => 'HOBC documentation for nodes, wallets, mining, pools, and security.',
    ]],
    'docs/getting-started/index.php' => ['file' => 'docs/getting-started/index.php', 'id' => 'docs_getting_started', 'meta' => [
        'meta.title' => 'Getting Started',
        'meta.description' => 'Getting started with HobbyHash Coin nodes, wallets, and mining.',
    ]],
    'docs/wallet-guide/index.php' => ['file' => 'docs/wallet-guide/index.php', 'id' => 'docs_wallet_guide', 'meta' => [
        'meta.title' => 'Wallet Guide',
        'meta.description' => 'HOBC wallet setup, custody, backups, and security.',
    ]],
    'docs/linux-node/index.php' => ['file' => 'docs/linux-node/index.php', 'id' => 'docs_linux_node', 'meta' => [
        'meta.title' => 'Linux Node',
        'meta.description' => 'Install and run the HOBC Linux node safely.',
    ]],
    'docs/mining-guide/index.php' => ['file' => 'docs/mining-guide/index.php', 'id' => 'docs_mining_guide', 'meta' => [
        'meta.title' => 'Mining Guide',
        'meta.description' => 'HOBC solo mining setup for home and nano miners.',
    ]],
    'docs/pool-stats/index.php' => ['file' => 'docs/pool-stats/index.php', 'id' => 'docs_pool_stats', 'meta' => [
        'meta.title' => 'Pool Stats Guide',
        'meta.description' => 'How to read HOBC solo pool stats and graphs.',
    ]],
    'docs/ports-configuration/index.php' => ['file' => 'docs/ports-configuration/index.php', 'id' => 'docs_ports_configuration', 'meta' => [
        'meta.title' => 'Ports Configuration',
        'meta.description' => 'HOBC mainnet, testnet, and regtest port reference.',
    ]],
    'docs/security-guide/index.php' => ['file' => 'docs/security-guide/index.php', 'id' => 'docs_security_guide', 'meta' => [
        'meta.title' => 'Security Guide',
        'meta.description' => 'HOBC wallet, node, and account security practices.',
    ]],
    'docs/explorer-guide/index.php' => ['file' => 'docs/explorer-guide/index.php', 'id' => 'docs_explorer_guide', 'meta' => [
        'meta.title' => 'Explorer Guide',
        'meta.description' => 'How to use the HOBC public explorer.',
    ]],
    'docs/cli-rpc/index.php' => ['file' => 'docs/cli-rpc/index.php', 'id' => 'docs_cli_rpc', 'meta' => [
        'meta.title' => 'CLI / RPC Guide',
        'meta.description' => 'HOBC hobbyhashd and hobbyhash-cli operator commands.',
    ]],
    'docs/faq/index.php' => ['file' => 'docs/faq/index.php', 'id' => 'docs_faq', 'meta' => [
        'meta.title' => 'FAQ',
        'meta.description' => 'Frequently asked questions about HobbyHash Coin.',
    ]],
];

function slug(string $text): string
{
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/i', '_', strtolower(trim($text))) ?? '';
    return trim($text, '_') ?: 'text';
}

function isPhpSnippet(string $text): bool
{
    return str_contains($text, '<?') || str_contains($text, 'hobc_t') || str_contains($text, '$pageId');
}

function loadExistingGood(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    if (!is_array($decoded)) {
        return [];
    }
    $good = [];
    foreach ($decoded as $key => $value) {
        $value = (string)$value;
        if ($value === '' || isPhpSnippet($value)) {
            continue;
        }
        $good[$key] = $value;
    }
    return $good;
}

function extractFromPhp(string $content): array
{
    $catalog = [];
    $html = $content;
    if (($pos = strpos($content, '<main')) !== false) {
        $html = substr($content, $pos);
    }

    if (preg_match('/<section class="hero[^"]*"[^>]*>.*?<span class="eyebrow">(.*?)<\/span>/s', $html, $m) && !isPhpSnippet($m[1])) {
        $catalog['hero.eyebrow'] = trim($m[1]);
    }
    if (preg_match('/<section class="hero[^"]*"[^>]*>.*?<h1>(.*?)<\/h1>/s', $html, $m) && !isPhpSnippet($m[1])) {
        $catalog['hero.title'] = trim($m[1]);
    }
    if (preg_match('/<section class="hero[^"]*"[^>]*>.*?<h1>.*?<\/h1>\s*<p>(.*?)<\/p>/s', $html, $m) && !isPhpSnippet($m[1])) {
        $catalog['hero.lead'] = trim($m[1]);
    }

    if (preg_match_all('/<section class="notice"[^>]*>(.*?)<\/section>/s', $html, $matches)) {
        foreach ($matches[1] as $i => $raw) {
            if (isPhpSnippet($raw)) {
                continue;
            }
            $catalog['notice.' . ($i + 1)] = trim($raw);
        }
    }

    if (preg_match_all('/<h2>(.*?)<\/h2>/s', $html, $matches)) {
        $seen = [];
        foreach ($matches[1] as $raw) {
            if (isPhpSnippet($raw)) {
                continue;
            }
            $text = trim(strip_tags($raw));
            if ($text === '' || isset($seen[$text])) {
                continue;
            }
            $seen[$text] = true;
            $catalog['heading.' . slug($text)] = trim($raw);
        }
    }

    if (preg_match_all('/<h3>(.*?)<\/h3>/s', $html, $matches)) {
        $seen = [];
        foreach ($matches[1] as $raw) {
            if (isPhpSnippet($raw)) {
                continue;
            }
            $text = trim(strip_tags($raw));
            if ($text === '' || isset($seen[$text])) {
                continue;
            }
            $seen[$text] = true;
            $catalog['card.' . slug($text) . '.title'] = trim($raw);
        }
    }

    if (preg_match_all('/<article class="card">\s*<h3>(.*?)<\/h3>\s*<p>(.*?)<\/p>/s', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            if (isPhpSnippet($match[1] . $match[2])) {
                continue;
            }
            $q = trim(strip_tags($match[1]));
            if ($q === '') {
                continue;
            }
            if (str_contains($html, 'docs/faq') || str_contains($html, 'faq')) {
                $catalog['faq.' . slug($q) . '.question'] = trim($match[1]);
                $catalog['faq.' . slug($q) . '.answer'] = trim($match[2]);
            } else {
                $catalog['card.' . slug($q) . '.body'] = trim($match[2]);
            }
        }
    }

    if (preg_match_all('/<div class="table-row"><span>(.*?)<\/span>/s', $html, $matches)) {
        $seen = [];
        foreach ($matches[1] as $label) {
            if (isPhpSnippet($label)) {
                continue;
            }
            $label = trim(strip_tags($label));
            if ($label === '' || isset($seen[$label])) {
                continue;
            }
            $seen[$label] = true;
            $catalog['label.' . slug($label)] = $label;
        }
    }

    if (preg_match_all('/<p>(.*?)<\/p>/s', $html, $matches)) {
        $pi = 0;
        foreach ($matches[1] as $raw) {
            if (isPhpSnippet($raw) || str_contains($raw, '<a ') || str_contains($raw, 'data-api')) {
                continue;
            }
            $plain = trim(strip_tags($raw));
            if ($plain === '' || strlen($plain) < 20) {
                continue;
            }
            $pi++;
            $key = 'body.p' . $pi;
            if (!isset($catalog[$key])) {
                $catalog[$key] = trim($raw);
            }
        }
    }

    if (preg_match_all('/<a class="button[^"]*"[^>]*>(.*?)<\/a>/s', $html, $matches)) {
        $bi = 0;
        foreach ($matches[1] as $raw) {
            if (isPhpSnippet($raw)) {
                continue;
            }
            $text = trim(strip_tags($raw));
            if ($text === '') {
                continue;
            }
            $bi++;
            $catalog['button.' . slug($text)] = $text;
        }
    }

    if (preg_match_all('/aria-label="([^"]{4,})"/', $html, $matches)) {
        foreach ($matches[1] as $label) {
            if (isPhpSnippet($label)) {
                continue;
            }
            $catalog['aria.' . slug($label)] = $label;
        }
    }

    if (preg_match_all('/alt="([^"]{4,})"/', $html, $matches)) {
        foreach ($matches[1] as $alt) {
            if (isPhpSnippet($alt)) {
                continue;
            }
            $catalog['alt.' . slug($alt)] = $alt;
        }
    }

    if (preg_match_all('/\$err\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach (array_unique($matches[1]) as $err) {
            $catalog['error.' . slug($err)] = $err;
        }
    }
    if (preg_match_all('/\$ok\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach (array_unique($matches[1]) as $ok) {
            $catalog['success.' . slug($ok)] = $ok;
        }
    }

    return $catalog;
}

/** @var array<string, array<string, string>> */
$manual = json_decode((string)file_get_contents($root . '/jobs/i18n_manual_pages.json'), true);
if (!is_array($manual)) {
    $manual = [];
}

$manual['site_gate'] = [
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
];

foreach ($pageMap as $info) {
    $path = $root . '/' . $info['file'];
    $jsonPath = $pagesDir . '/' . $info['id'] . '.json';
    $existing = loadExistingGood($jsonPath);
    $extracted = is_file($path) ? extractFromPhp((string)file_get_contents($path)) : [];
    $extra = $manual[$info['id']] ?? [];
    $catalog = array_merge($extracted, $existing, $extra, $info['meta']);
    ksort($catalog);
    file_put_contents(
        $jsonPath,
        json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
    echo $info['id'] . ': ' . count($catalog) . " keys\n";
}

foreach ($manual as $id => $catalog) {
    if (isset($pageMap[$id])) {
        continue;
    }
    ksort($catalog);
    file_put_contents(
        $pagesDir . '/' . $id . '.json',
        json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
    echo $id . ': ' . count($catalog) . " keys (manual only)\n";
}

echo "Restore complete.\n";
