<?php
declare(strict_types=1);

require_once __DIR__ . '/public_settings.php';
require_once __DIR__ . '/social_links.php';

function hobc_exchange_listing_defaults(): array
{
    return [
        'project_name' => 'HobbyHash Coin',
        'ticker' => 'HOBC',
        'website' => 'https://hobbyhashcoin.com',
        'explorer_url' => 'https://explorer.hobbyhashcoin.com',
        'downloads_url' => 'https://hobbyhashcoin.com/downloads',
        'mainnet_p2p_port' => 18761,
        'mainnet_rpc_port' => 18762,
        'algorithm' => 'SHA256',
        'coin_type' => 'Native mineable UTXO coin',
        'purpose' => 'Home solo miner focused SHA256 coin',
        'contact_email' => 'listings@hobbyhashcoin.com',
        'source_repo_url' => 'To be confirmed — public source repository URL will be published here.',
        'social_x' => 'https://x.com/HobbyHashCoin',
        'social_facebook' => 'https://www.facebook.com/people/HobbyHash-Coin/61590689639798/',
        'social_discord' => 'To be confirmed',
        'social_telegram' => 'To be confirmed',
    ];
}

function hobc_exchange_listing_meta(): array
{
    $defaults = hobc_exchange_listing_defaults();
    return [
        'project_name' => hobc_public_setting_text('coin.name', $defaults['project_name']),
        'ticker' => hobc_public_setting_text('coin.ticker', $defaults['ticker']),
        'website' => $defaults['website'],
        'explorer_url' => $defaults['explorer_url'],
        'downloads_url' => $defaults['downloads_url'],
        'mainnet_p2p_port' => $defaults['mainnet_p2p_port'],
        'mainnet_rpc_port' => $defaults['mainnet_rpc_port'],
        'algorithm' => $defaults['algorithm'],
        'coin_type' => $defaults['coin_type'],
        'purpose' => $defaults['purpose'],
        'contact_email' => hobc_public_setting_text('listing.contact_email', $defaults['contact_email']),
        'source_repo_url' => hobc_public_setting_text('listing.source_repo_url', $defaults['source_repo_url']),
        'social_x' => hobc_public_setting_text('listing.social_x', $defaults['social_x']),
        'social_facebook' => hobc_public_setting_text('listing.social_facebook', $defaults['social_facebook']),
        'social_discord' => hobc_public_setting_text('listing.social_discord', $defaults['social_discord']),
        'social_telegram' => hobc_public_setting_text('listing.social_telegram', $defaults['social_telegram']),
    ];
}

function hobc_exchange_listing_genesis(): array
{
    return [
        'network' => 'HOBC mainnet',
        'height' => '0',
        'block_hash' => '00000000a746a8a7dba5237b7f9c92cb1b2690cb53ab2958ce76a506b1ea96af',
        'merkle_root' => 'be535086a84506c2fb39f8e77a3065d5005d3a9b2ebcc9c1e3aa3162103b3f12',
        'timestamp_unix' => '1780208106',
        'timestamp_utc' => '2026-05-31 06:15:06 UTC',
        'nonce' => '3854237270',
        'bits' => '1d00ffff',
        'version' => '1',
        'coinbase_message' => 'HobbyCash Coin 2026 - solo mining for home hashers',
        'genesis_subsidy' => 'Unspendable genesis coinbase (consensus standard)',
        'launch_reserve_height' => '1',
        'launch_reserve_subsidy' => '8,400,000 HOBC',
        'launch_reserve_address' => defined('HOBC_LAUNCH_RESERVE_ADDRESS') ? HOBC_LAUNCH_RESERVE_ADDRESS : 'hobc1qp6z9335dmnnumrukvkwwrks0s0ul68he2kj4ga',
        'bech32_hrp' => 'hobc',
        'message_start' => 'c1 a0 f1 ce',
    ];
}

function hobc_exchange_listing_supply(): array
{
    return [
        'total_target_supply' => defined('HOBC_TOTAL_SUPPLY') ? HOBC_TOTAL_SUPPLY : '84000000.00000000',
        'launch_reserve' => defined('HOBC_LAUNCH_RESERVE') ? HOBC_LAUNCH_RESERVE : '8400000.00000000',
        'normal_mining_target' => defined('HOBC_NORMAL_MINING_TARGET') ? HOBC_NORMAL_MINING_TARGET : '75600000.00000000',
        'launch_reserve_percent' => '10%',
        'burn_address' => defined('HOBC_BURN_ADDRESS') ? HOBC_BURN_ADDRESS : 'hobc1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqf9lpf8',
    ];
}

function hobc_exchange_listing_emission(): array
{
    return [
        'block_spacing_seconds' => '150 (2.5 minutes)',
        'halving_interval_blocks' => '840,000',
        'block_0_subsidy' => '0 HOBC (genesis)',
        'block_1_subsidy' => '8,400,000 HOBC (documented launch reserve block)',
        'block_2_plus_subsidy' => '45 HOBC, halved every 840,000 blocks from height 2',
        'retarget' => 'Difficulty retargets on mainnet per consensus (100-block and 1-block windows documented in node source)',
        'fee_policy' => 'Standard transaction fees apply; no profit guarantees from supply policy',
    ];
}

function hobc_exchange_listing_seed_nodes(): array
{
    return [
        ['host' => 'hobbyhashcoin.com', 'port' => '18761', 'role' => 'Public DNS / seed peer'],
        ['host' => '162.254.37.69', 'port' => '18761', 'role' => 'Public seed peer (IPv4)'],
    ];
}

function hobc_exchange_listing_pair_preferences(): array
{
    return [
        ['pair' => 'HOBC/BTC', 'priority' => 'Primary listing preference'],
        ['pair' => 'HOBC/DOGE', 'priority' => 'Primary listing preference'],
        ['pair' => 'HOBC/USDT', 'priority' => 'Optional — subject to exchange policy and liquidity planning'],
    ];
}

function hobc_exchange_listing_documents(): array
{
    return [
        [
            'badge' => 'PDF',
            'title' => 'HobbyHash Coin Whitepaper',
            'description' => 'Full project whitepaper covering HOBC network details, mining model, infrastructure, exchange readiness, reserve policy, and risk notices.',
            'file' => '/assets/docs/hobc-whitepaper.pdf',
            'download_name' => 'hobc-whitepaper.pdf',
        ],
        [
            'badge' => 'PDF',
            'title' => 'Exchange Listing Factsheet',
            'description' => 'One-page exchange review factsheet with HOBC chain details, links, preferred trading pairs, and integration notes.',
            'file' => '/assets/docs/hobc-listing-factsheet.pdf',
            'download_name' => 'hobc-listing-factsheet.pdf',
        ],
    ];
}

function hobc_exchange_listing_press_kit(): array
{
    return [
        [
            'label' => 'Logo (PNG)',
            'file' => '/exchange-listing/press-kit/hobc-logo.png',
            'mime' => 'image/png',
            'note' => 'Round HOBC brand logo for exchange media kits.',
        ],
        [
            'label' => 'Icon (PNG)',
            'file' => '/exchange-listing/press-kit/hobc-icon.png',
            'mime' => 'image/png',
            'note' => 'Medallion-style icon suitable for favicons and compact listings.',
        ],
    ];
}

function hobc_exchange_listing_checksum_files(): array
{
    return [
        [
            'label' => 'Linux node SHA256 sums',
            'path' => '/downloads/linux/HobbyHash-Linux-SHA256SUMS.txt',
            'filesystem' => __DIR__ . '/../downloads/linux/HobbyHash-Linux-SHA256SUMS.txt',
        ],
        [
            'label' => 'Windows wallet SHA256 sums',
            'path' => '/downloads/windows/HobbyHash-Wallets-SHA256SUMS.txt',
            'filesystem' => __DIR__ . '/../downloads/windows/HobbyHash-Wallets-SHA256SUMS.txt',
        ],
    ];
}

function hobc_exchange_listing_parse_checksum_file(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }
    $rows = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (preg_match('/^([a-f0-9]{64})\s{2,}(.+)$/i', $line, $matches) !== 1) {
            continue;
        }
        $filePath = trim($matches[2]);
        $basename = basename($filePath);
        $rows[] = [
            'sha256' => strtolower($matches[1]),
            'file' => $basename,
            'path' => $filePath,
        ];
    }
    return $rows;
}

function hobc_exchange_listing_highlight_checksums(): array
{
    return [
        [
            'label' => 'Linux node (standard x86_64)',
            'file' => 'HobbyHash-Linux-Node-x86_64.tar.gz',
            'sha256' => '0d81a44e26e0b23db47073ad274debd97604129b9d5176b164fd8b2d79b22a51',
            'url' => 'https://hobbyhashcoin.com/downloads/linux/HobbyHash-Linux-Node-x86_64.tar.gz',
        ],
        [
            'label' => 'Linux node (AL9 / RHEL 9)',
            'file' => 'HobbyHash-Linux-Node-AL9-x86_64.tar.gz',
            'sha256' => 'f4c1e3da630b91459795da0a26dff6a0645bfd68202b6e7cb9fb8a3abe23c35a',
            'url' => 'https://hobbyhashcoin.com/downloads/linux/HobbyHash-Linux-Node-AL9-x86_64.tar.gz',
        ],
        [
            'label' => 'Windows wallet installer',
            'file' => 'HobbyHash-Wallet-Setup-0.1.25.exe',
            'sha256' => '6441bbbe8c6ffb7fd42764e219d3aa67471de9e3304a9a6070b4ed38c53017c6',
            'url' => 'https://hobbyhashcoin.com/downloads/windows/HobbyHash-Wallet-Setup-0.1.25.exe',
        ],
    ];
}

function hobc_exchange_listing_checklist_items(): array
{
    return [
        'mainnet_live' => ['label' => 'Mainnet live', 'group' => 'Network & software'],
        'explorer_live' => ['label' => 'Explorer live', 'group' => 'Network & software'],
        'wallet_downloads_live' => ['label' => 'Wallet downloads live', 'group' => 'Network & software'],
        'source_repo_public' => ['label' => 'Source repo public', 'group' => 'Network & software'],
        'checksums_published' => ['label' => 'Checksums published', 'group' => 'Network & software'],
        'x_account_live' => ['label' => 'X account live', 'group' => 'Community & comms'],
        'discord_telegram_live' => ['label' => 'Discord/Telegram live', 'group' => 'Community & comms'],
        'listing_email_created' => ['label' => 'Listing email created', 'group' => 'Community & comms'],
        'xeggex_application_submitted' => ['label' => 'XeggeX application submitted', 'group' => 'Exchange pipeline'],
        'liquidity_funded' => ['label' => 'Liquidity funded', 'group' => 'Exchange pipeline'],
        'coingecko_submitted' => ['label' => 'CoinGecko submitted', 'group' => 'Aggregators'],
        'coinmarketcap_submitted' => ['label' => 'CoinMarketCap submitted', 'group' => 'Aggregators'],
        'mexc_submitted_later' => ['label' => 'MEXC submitted later', 'group' => 'Later-stage CEX'],
        'okx_submitted_later' => ['label' => 'OKX submitted later', 'group' => 'Later-stage CEX'],
    ];
}

function hobc_exchange_listing_checklist_defaults(): array
{
    $defaults = [];
    foreach (array_keys(hobc_exchange_listing_checklist_items()) as $key) {
        $defaults[$key] = false;
    }
    return $defaults;
}

function hobc_exchange_listing_checklist_load(): array
{
    require_once __DIR__ . '/settings.php';
    $stored = admin_setting_get('listing.checklist', hobc_exchange_listing_checklist_defaults());
    if (!is_array($stored)) {
        $stored = [];
    }
    return array_merge(hobc_exchange_listing_checklist_defaults(), $stored);
}

function hobc_exchange_listing_checklist_save(array $values, ?int $adminId = null): void
{
    require_once __DIR__ . '/settings.php';
    $normalized = hobc_exchange_listing_checklist_defaults();
    foreach ($normalized as $key => $default) {
        $normalized[$key] = !empty($values[$key]);
    }
    admin_setting_set('listing.checklist', $normalized, 'json', $adminId);
}

function hobc_exchange_listing_checklist_progress(array $checklist): array
{
    $items = hobc_exchange_listing_checklist_items();
    $total = count($items);
    $done = 0;
    foreach (array_keys($items) as $key) {
        if (!empty($checklist[$key])) {
            $done++;
        }
    }
    return [
        'done' => $done,
        'total' => $total,
        'percent' => $total > 0 ? (int)round(($done / $total) * 100) : 0,
    ];
}

function hobc_exchange_listing_external_link(string $value): string
{
    return hobc_social_normalize_url($value);
}
