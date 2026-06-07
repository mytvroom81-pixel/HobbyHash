<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function site_status_defaults(): array
{
    return [
        'id' => 1,
        'site_mode' => 'full_launch',
        'bypass_ip' => '',
        'pre_launch_title' => 'HOBC is getting ready to launch',
        'pre_launch_message' => 'The HobbyHash Coin command center is being prepared. Coming soon: home solo mining guides, main and nano solo pools, explorer, stats, downloads, launch reserve transparency, burn tracking, docs, and custodial wallet access with clear risk notices.',
        'pre_launch_eta' => '',
        'maintenance_title' => 'HOBC is under maintenance',
        'maintenance_message' => 'The HOBC website is temporarily unavailable while maintenance is completed.',
        'maintenance_start_at' => '',
        'maintenance_end_at' => '',
        'updated_at' => '',
    ];
}

function site_status_ensure_schema(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo = wallet_db();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS site_settings (
            id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            site_mode ENUM('pre_launch','maintenance','full_launch') NOT NULL DEFAULT 'full_launch',
            bypass_ip VARCHAR(64) NULL,
            pre_launch_title VARCHAR(190) NOT NULL DEFAULT 'HOBC is getting ready to launch',
            pre_launch_message TEXT NULL,
            pre_launch_eta VARCHAR(190) NULL,
            maintenance_title VARCHAR(190) NOT NULL DEFAULT 'HOBC is under maintenance',
            maintenance_message TEXT NULL,
            maintenance_start_at VARCHAR(190) NULL,
            maintenance_end_at VARCHAR(190) NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "INSERT INTO site_settings
            (id, site_mode, pre_launch_message, maintenance_message)
         VALUES
            (1, 'full_launch',
             'The HobbyHash Coin command center is being prepared. Coming soon: home solo mining guides, main and nano solo pools, explorer, stats, downloads, launch reserve transparency, burn tracking, docs, and custodial wallet access with clear risk notices.',
             'The HOBC website is temporarily unavailable while maintenance is completed.')
         ON DUPLICATE KEY UPDATE id = id"
    );

    $ensured = true;
}

function site_status_settings(): array
{
    try {
        site_status_ensure_schema();
        $row = wallet_db()->query('SELECT * FROM site_settings WHERE id = 1')->fetch();
        if (is_array($row)) {
            return array_merge(site_status_defaults(), array_map(static fn($value) => $value === null ? '' : $value, $row));
        }
    } catch (Throwable $e) {
        wallet_log_error('site status read failed: ' . $e->getMessage());
    }
    return site_status_defaults();
}

function site_status_client_ip(): string
{
    return trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
}

function site_status_is_bypass_ip(array $settings): bool
{
    $bypassIp = trim((string)($settings['bypass_ip'] ?? ''));
    return $bypassIp !== '' && hash_equals($bypassIp, site_status_client_ip());
}

function site_status_is_public_gate_path(): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }

    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $excludedPrefixes = ['/admin/', '/api/', '/app/', '/jobs/'];
    foreach ($excludedPrefixes as $prefix) {
        if (str_starts_with($script, $prefix)) {
            return false;
        }
    }

    $excludedPublicPaths = ['/privacy/', '/privacy/index.php', '/terms/', '/terms/index.php'];
    foreach ($excludedPublicPaths as $path) {
        if ($script === $path) {
            return false;
        }
    }

    return true;
}

function site_status_gate(): void
{
    if (!site_status_is_public_gate_path()) {
        return;
    }

    $settings = site_status_settings();
    $mode = (string)($settings['site_mode'] ?? 'full_launch');
    if ($mode === 'full_launch' || site_status_is_bypass_ip($settings)) {
        return;
    }

    site_status_render_gate_page($settings, $mode);
    exit;
}

function site_status_render_gate_page(array $settings, string $mode): void
{
    $isMaintenance = $mode === 'maintenance';
    $title = $isMaintenance ? (string)$settings['maintenance_title'] : (string)$settings['pre_launch_title'];
    $message = $isMaintenance ? (string)$settings['maintenance_message'] : (string)$settings['pre_launch_message'];
    $statusLabel = $isMaintenance ? 'Maintenance mode' : 'Pre-launch';
    $windowStart = trim((string)($settings['maintenance_start_at'] ?? ''));
    $windowEnd = trim((string)($settings['maintenance_end_at'] ?? ''));
    $eta = trim((string)($settings['pre_launch_eta'] ?? ''));

    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Retry-After: 3600');
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> | HobbyHash Coin</title>
  <link rel="stylesheet" href="/assets/css/hobc.css">
</head>
<body>
<main id="main-content">
  <div class="page">
    <section class="hero visual">
      <div class="hero-content">
        <span class="eyebrow"><?= h($statusLabel) ?></span>
        <h1><?= h($title) ?></h1>
        <p><?= nl2br(h($message)) ?></p>
        <?php if (!$isMaintenance && $eta !== ''): ?>
          <p><strong class="gold">Expected launch:</strong> <?= h($eta) ?></p>
        <?php endif; ?>
        <?php if ($isMaintenance): ?>
          <div class="notice">
            <strong>Maintenance window</strong>
            <p>Start: <?= h($windowStart !== '' ? $windowStart : 'Not set') ?><br>End: <?= h($windowEnd !== '' ? $windowEnd : 'Not set') ?></p>
          </div>
        <?php endif; ?>
      </div>
    </section>
    <section class="grid cards">
      <article class="card"><h3>Home solo mining</h3><p>HOBC is built around SHA-256 home solo mining with simple setup guides.</p></article>
      <article class="card"><h3>Main and Nano Pools</h3><p>Main Pool for ASIC miners and Nano Pool for small SHA-256 miners. Solo pools only.</p></article>
      <article class="card"><h3>Transparent launch</h3><p>The portal will show honest chain, pool, reserve, burn, explorer, and wallet status. No fake data.</p></article>
    </section>
    <div class="actions">
      <a class="button" href="/privacy/">Privacy Policy</a>
      <a class="button" href="/terms/">Terms</a>
    </div>
  </div>
</main>
</body>
</html>
    <?php
}
