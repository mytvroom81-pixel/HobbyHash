<?php
$pagesDir = dirname(__DIR__) . '/lang/en/pages';
$poolExtras = [
    'pool_main' => [
        'hero.eyebrow_active' => 'HOBC Main Pool',
        'hero.title_active' => 'Main Pool',
        'hero.lead_active' => 'ASIC-focused SHA-256 solo pool. Start difficulty is 5000. Payout goes to the address in your worker name.',
        'connection.note' => 'Solo only. Start diff 5000. Payout goes to the HOBC address before the dot in your worker name.',
        'label.pool_url' => 'Pool URL',
        'label.password' => 'Password',
        'label.workers' => 'Workers',
        'label.hashrate' => 'Hashrate',
        'label.accepted_shares' => 'Accepted shares',
        'label.rejected_shares' => 'Rejected shares',
        'label.last_share' => 'Last share',
        'disabled.eyebrow' => 'Pool unavailable',
        'disabled.title' => 'Main Pool Disabled',
        'disabled.lead' => 'The public Main Pool page is temporarily unavailable by admin setting.',
    ],
    'pool_nano' => [
        'hero.eyebrow_active' => 'HOBC Nano Pool',
        'hero.title_active' => 'HOBC Nano Pool',
        'hero.lead_active' => 'Nano miner friendly SHA-256 solo pool for small miners, Bitaxe-style devices, NerdMiner-style hobby miners, and low-hashrate test rigs. Start difficulty is 0.005. Payout goes to the address in your worker name.',
        'connection.note' => 'Solo only. Start diff 0.005. Payout goes to the HOBC address before the dot in your worker name.',
        'label.pool_url' => 'Pool URL',
        'label.password' => 'Password',
        'label.workers' => 'Workers',
        'label.hashrate' => 'Hashrate',
        'label.accepted_shares' => 'Accepted shares',
        'label.rejected_shares' => 'Rejected shares',
        'label.last_share' => 'Last share',
        'disabled.eyebrow' => 'Pool unavailable',
        'disabled.title' => 'Nano Pool Disabled',
        'disabled.lead' => 'The public Nano Pool page is temporarily unavailable by admin setting.',
    ],
];
foreach ($poolExtras as $id => $extra) {
    $path = $pagesDir . '/' . $id . '.json';
    $existing = json_decode((string)file_get_contents($path), true);
    $merged = array_merge(is_array($existing) ? $existing : [], $extra);
    ksort($merged);
    file_put_contents($path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    echo "$id merged\n";
}
