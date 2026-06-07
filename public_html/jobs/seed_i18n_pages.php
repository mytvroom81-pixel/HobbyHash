<?php
declare(strict_types=1);

/**
 * One-time seed: build lang/en/pages/*.json from current public PHP page sources.
 * Run: php jobs/seed_i18n_pages.php
 */

require_once __DIR__ . '/../app/i18n_catalog.php';

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

/** @var array<string, array<string, string>> */
$manualExtras = require __DIR__ . '/i18n_page_extras.php';

function seed_slugify(string $text): string
{
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/i', '_', strtolower(trim($text))) ?? '';
    return trim($text, '_') ?: 'text';
}

function seed_extract_meta(string $content): array
{
    $meta = [];
    if (preg_match('/\$pageTitle\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
        $meta['meta.title'] = $m[1];
    }
    if (preg_match('/\$pageDescription\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
        $meta['meta.description'] = $m[1];
    }
    return $meta;
}

function seed_extract_strings(string $content): array
{
    $strings = [];
    $seen = [];

    // h3 headings in cards/sections
    if (preg_match_all('/<h[123][^>]*>(.*?)<\/h[123]>/s', $content, $matches)) {
        foreach ($matches[1] as $raw) {
            $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
            if ($text === '' || strlen($text) < 2 || isset($seen[$text])) {
                continue;
            }
            $seen[$text] = true;
            $slug = seed_slugify($text);
            $key = 'heading.' . $slug;
            $i = 2;
            while (isset($strings[$key])) {
                $key = 'heading.' . $slug . '_' . $i;
                $i++;
            }
            $strings[$key] = trim($raw);
        }
    }

    // eyebrow spans
    if (preg_match_all('/<span class="eyebrow">(.*?)<\/span>/s', $content, $matches)) {
        $n = 0;
        foreach ($matches[1] as $raw) {
            $n++;
            $key = $n === 1 ? 'hero.eyebrow' : 'eyebrow.' . $n;
            $strings[$key] = trim($raw);
        }
    }

    // hero lead paragraphs (first p in hero)
    if (preg_match('/<section class="hero[^"]*"[^>]*>.*?<p>(.*?)<\/p>/s', $content, $m)) {
        $strings['hero.lead'] = trim($m[1]);
    }

    // hero h1
    if (preg_match('/<section class="hero[^"]*"[^>]*>.*?<h1>(.*?)<\/h1>/s', $content, $m)) {
        $strings['hero.title'] = trim($m[1]);
    }

    // card paragraphs
    if (preg_match_all('/<article class="card[^"]*"[^>]*>.*?<p>(.*?)<\/p>/s', $content, $matches)) {
        $n = 0;
        foreach ($matches[1] as $raw) {
            if (str_contains($raw, '<?=')) {
                continue;
            }
            $n++;
            $strings['card.' . $n . '.body'] = trim($raw);
        }
    }

    // notice sections
    if (preg_match_all('/<section class="notice"[^>]*>(.*?)<\/section>/s', $content, $matches)) {
        foreach ($matches[1] as $i => $raw) {
            $strings['notice.' . ($i + 1)] = trim($raw);
        }
    }

    // button/link text
    if (preg_match_all('/<a class="button[^"]*"[^>]*>(.*?)<\/a>/s', $content, $matches)) {
        $n = 0;
        foreach ($matches[1] as $raw) {
            $text = trim(strip_tags($raw));
            if ($text === '' || isset($seen[$text])) {
                continue;
            }
            $seen[$text] = true;
            $n++;
            $strings['action.' . seed_slugify($text)] = $text;
        }
    }

    // aria-label attributes
    if (preg_match_all('/aria-label="([^"]+)"/', $content, $matches)) {
        foreach ($matches[1] as $i => $label) {
            if (str_contains($label, '<?')) {
                continue;
            }
            $strings['aria.' . seed_slugify($label)] = $label;
        }
    }

    // $err assignments
    if (preg_match_all('/\$err\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach (array_unique($matches[1]) as $err) {
            $strings['error.' . seed_slugify($err)] = $err;
        }
    }

    return $strings;
}

foreach ($pageMap as $rel => $info) {
    $path = $root . '/' . $info['file'];
    if (!is_file($path)) {
        fwrite(STDERR, "Missing: $path\n");
        continue;
    }
    $content = (string)file_get_contents($path);
    $catalog = seed_extract_meta($content);
    $catalog = array_merge($catalog, seed_extract_strings($content));
    if (isset($manualExtras[$info['id']]) && is_array($manualExtras[$info['id']])) {
        $catalog = array_merge($catalog, $manualExtras[$info['id']]);
    }
    ksort($catalog);
    $json = json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('JSON encode failed for ' . $info['id']);
    }
    $out = $pagesDir . '/' . $info['id'] . '.json';
    file_put_contents($out, $json . "\n");
    echo "Wrote {$info['id']}.json (" . count($catalog) . " keys)\n";
}

echo "Done.\n";
