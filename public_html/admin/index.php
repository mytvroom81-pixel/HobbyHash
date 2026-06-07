<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/admin_view.php';
require_once __DIR__ . '/../app/admin_dashboard.php';

$admin = admin_require_user();
$snapshot = admin_dashboard_snapshot($admin);
$dashboardUrl = admin_url('/api/dashboard.php');

render_admin_header('Dashboard');
?>
<div
  class="admin-dashboard"
  data-admin-dashboard
  data-admin-dashboard-url="<?= h($dashboardUrl) ?>"
  data-refresh-ms="15000">
  <header class="admin-dashboard-header">
    <div>
      <span class="admin-live-pulse" aria-hidden="true"></span>
      <span class="admin-kicker">Live operations board</span>
      <h2 class="admin-dashboard-title">Master control snapshot</h2>
      <p class="admin-dashboard-lead">Wallet solvency, chain health, pool activity, traffic, and queue alerts in one place. Refreshes automatically every 15 seconds.</p>
    </div>
    <div class="admin-dashboard-meta">
      <span class="admin-dashboard-updated">Updated <strong data-admin-dashboard-updated><?= h((string)$snapshot['updated_at']) ?></strong></span>
      <button type="button" class="admin-action admin-action-secondary" data-admin-dashboard-refresh>Refresh now</button>
    </div>
  </header>

  <div data-admin-dashboard-alerts>
    <?php admin_render_dashboard_alerts(is_array($snapshot['alerts'] ?? null) ? $snapshot['alerts'] : []); ?>
  </div>

  <div data-admin-dashboard-sections>
    <?php foreach ((array)($snapshot['sections'] ?? []) as $section): ?>
      <?php if (is_array($section)): admin_render_dashboard_section($section); endif; ?>
    <?php endforeach; ?>
  </div>

  <div class="admin-dashboard-split">
    <div class="admin-card">
      <div class="admin-dashboard-section-head">
        <div>
          <h3>Recent Admin Events</h3>
          <p>Latest actions recorded in the admin audit log.</p>
        </div>
        <?php if (admin_can($admin, 'audit_logs')): ?>
          <?= admin_action_button('Audit Log', admin_url('/audit.php'), 'secondary') ?>
        <?php endif; ?>
      </div>
      <div data-admin-dashboard-events>
        <?php
        $eventRows = array_map(
            static fn(array $event): array => [h((string)$event['action']), h((string)$event['created_at'])],
            (array)($snapshot['recent_events'] ?? [])
        );
        admin_render_table(['Action', 'When'], $eventRows, 'No admin events yet', 'No admin audit events have been recorded yet.');
        ?>
      </div>
    </div>

    <div class="admin-card">
      <div class="admin-dashboard-section-head">
        <div>
          <h3>Latest Blocks</h3>
          <p>Most recent blocks from the local node RPC feed.</p>
        </div>
        <?php if (admin_can($admin, 'explorer')): ?>
          <?= admin_action_button('Blockchain Stats', admin_url('/blockchain.php'), 'secondary') ?>
        <?php endif; ?>
      </div>
      <div data-admin-dashboard-blocks>
        <?php
        $blockRows = array_map(
            static fn(array $block): array => [
                h((string)$block['height']),
                admin_hash_cell((string)($block['hash'] ?? ''), true),
                h((string)$block['tx_count']),
                h((string)$block['time']),
            ],
            (array)($snapshot['recent_blocks'] ?? [])
        );
        admin_render_table(['Height', 'Hash', 'TX', 'When'], $blockRows, 'No blocks yet', 'Node RPC has not returned recent blocks yet.');
        ?>
      </div>
    </div>
  </div>

  <div class="admin-card admin-dashboard-actions">
    <h3>Quick Actions</h3>
    <p>Jump straight to the most common operational tasks.</p>
    <div class="admin-actions">
      <?php if (admin_can($admin, 'settings')): ?><?= admin_action_button('Site Config', admin_url('/site-config.php')) ?><?php endif; ?>
      <?php if (admin_can($admin, 'wallet_controls')): ?><?= admin_action_button('Wallet Ops', admin_url('/wallet.php')) ?><?php endif; ?>
      <?php if (admin_can($admin, 'withdrawals')): ?><?= admin_action_button('Withdrawals', admin_url('/withdrawals.php')) ?><?php endif; ?>
      <?php if (admin_can($admin, 'treasury_reserve')): ?><?= admin_action_button('Launch Reserve', admin_url('/reserve.php')) ?><?php endif; ?>
      <?php if (admin_can($admin, 'support_messages')): ?><?= admin_action_button('Support Tickets', admin_url('/tickets.php')) ?><?php endif; ?>
      <?php if (admin_can($admin, 'mining_pool')): ?><?= admin_action_button('Mining Pool', admin_url('/mining-pool.php'), 'secondary') ?><?php endif; ?>
      <?php if (admin_can($admin, 'analytics')): ?><?= admin_action_button('Analytics', admin_url('/analytics.php'), 'secondary') ?><?php endif; ?>
      <?php if (admin_can($admin, 'social_bot')): ?><?= admin_action_button('Social Bot', admin_url('/social-bot.php'), 'secondary') ?><?php endif; ?>
    </div>
  </div>
</div>
<?php render_admin_footer(); ?>
