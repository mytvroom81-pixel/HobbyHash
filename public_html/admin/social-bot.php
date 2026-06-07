<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/admin_view.php';
require_once __DIR__ . '/../app/social_bot_admin.php';

$admin = admin_require_user();

if (social_bot_available() && isset($_GET['x_oauth']) && (string)$_GET['x_oauth'] === 'start') {
    try {
        $url = social_bot_x_oauth_pkce_start((string)$admin['username']);
        wallet_redirect($url);
    } catch (Throwable $e) {
        admin_flash_set('error', $e->getMessage());
        social_bot_redirect('platforms');
    }
}

if (social_bot_available() && isset($_GET['facebook_oauth']) && (string)$_GET['facebook_oauth'] === 'start') {
    try {
        $url = social_bot_facebook_oauth_start((string)$admin['username']);
        wallet_redirect($url);
    } catch (Throwable $e) {
        admin_flash_set('error', $e->getMessage());
        social_bot_redirect('platforms');
    }
}

$tabs = [
    'overview' => 'Overview',
    'budget' => 'X Budget',
    'queue' => 'Approval Queue',
    'history' => 'Post History',
    'replies' => 'Replies',
    'templates' => 'Templates',
    'platforms' => 'Platforms',
    'settings' => 'Settings',
    'preview' => 'Preview',
    'test' => 'Test Post',
    'audit' => 'Bot Audit',
];
$requestedTab = (string)($_GET['tab'] ?? 'overview');
$tab = array_key_exists($requestedTab, $tabs) ? $requestedTab : 'overview';
$err = csrf_flash_error();
$msg = '';
$preview = null;

function social_bot_redirect(string $tab): void
{
    wallet_redirect(admin_url('/social-bot.php?tab=' . rawurlencode($tab)));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_validate_or_fail();
    $action = (string)($_POST['action'] ?? '');
    try {
        if (!social_bot_available()) {
            throw new RuntimeException('Social bot database is not available.');
        }

        if ($action === 'approve_post') {
            $id = (int)($_POST['id'] ?? 0);
            social_bot_publish_post($id, (string)$admin['username']);
            admin_audit((int)$admin['id'], 'social_bot_post_approve', 'social_post', (string)$id, []);
            admin_flash_set('success', 'Post approved and published.');
            social_bot_redirect('queue');
        } elseif ($action === 'reject_post') {
            $id = (int)($_POST['id'] ?? 0);
            social_bot_reject_post($id);
            admin_audit((int)$admin['id'], 'social_bot_post_reject', 'social_post', (string)$id, []);
            admin_flash_set('success', 'Post rejected.');
            social_bot_redirect('queue');
        } elseif ($action === 'approve_reply') {
            $id = (int)($_POST['id'] ?? 0);
            social_bot_approve_reply($id, (string)$admin['username']);
            admin_audit((int)$admin['id'], 'social_bot_reply_approve', 'social_reply', (string)$id, []);
            admin_flash_set('success', 'Reply sent.');
            social_bot_redirect('replies');
        } elseif ($action === 'reject_reply') {
            $id = (int)($_POST['id'] ?? 0);
            social_bot_reject_reply($id);
            admin_audit((int)$admin['id'], 'social_bot_reply_reject', 'social_reply', (string)$id, []);
            admin_flash_set('success', 'Reply rejected.');
            social_bot_redirect('replies');
        } elseif ($action === 'toggle_platform') {
            $platform = (string)($_POST['platform'] ?? '');
            $enabled = ($_POST['enabled'] ?? '') === '1';
            if ($platform === '') {
                throw new RuntimeException('Platform is required.');
            }
            social_bot_toggle_platform($platform, $enabled);
            admin_audit((int)$admin['id'], 'social_bot_platform_toggle', 'social_platform', $platform, ['enabled' => $enabled]);
            admin_flash_set('success', ucfirst($platform) . ' ' . ($enabled ? 'enabled' : 'disabled') . '.');
            social_bot_redirect('platforms');
        } elseif ($action === 'save_template') {
            $id = (int)($_POST['id'] ?? 0);
            $body = trim((string)($_POST['template_body'] ?? ''));
            $enabled = isset($_POST['enabled']);
            $weight = max(1, (int)($_POST['weight'] ?? 1));
            if ($id <= 0 || $body === '') {
                throw new RuntimeException('Template id and body are required.');
            }
            social_bot_update_template($id, $body, $enabled, $weight);
            admin_audit((int)$admin['id'], 'social_bot_template_update', 'social_template', (string)$id, []);
            admin_flash_set('success', 'Template updated.');
            social_bot_redirect('templates');
        } elseif ($action === 'save_platform_keys') {
            social_bot_save_platform_credentials($_POST, (string)$admin['username']);
            admin_audit((int)$admin['id'], 'social_bot_platform_keys_update', 'social_bot', 'platforms', []);
            admin_flash_set('success', 'Platform credentials saved.');
            social_bot_redirect('platforms');
        } elseif ($action === 'x_oauth_manual') {
            social_bot_x_oauth_handle_manual_code((string)($_POST['x_oauth_code'] ?? ''), (string)$admin['username']);
            admin_audit((int)$admin['id'], 'social_bot_x_oauth_connect', 'social_bot', 'x', ['manual' => true]);
            admin_flash_set('success', 'X OAuth 2.0 connected manually.');
            social_bot_redirect('platforms');
        } elseif ($action === 'save_settings') {
            $actor = (string)$admin['username'];
            social_bot_set_setting('posting_schedule', [
                'min_hours' => (float)($_POST['min_hours'] ?? 3),
                'max_hours' => (float)($_POST['max_hours'] ?? 6),
                'post_probability' => (float)($_POST['post_probability'] ?? 0.25),
                'check_interval_minutes' => 15,
            ], $actor);
            social_bot_set_setting('quiet_hours', [
                'enabled' => isset($_POST['quiet_enabled']),
                'start' => (int)($_POST['quiet_start'] ?? 23),
                'end' => (int)($_POST['quiet_end'] ?? 7),
            ], $actor);
            social_bot_set_setting('max_posts_per_day', (int)($_POST['max_posts_per_day'] ?? 5), $actor);
            social_bot_set_setting('auto_post_mode', in_array((string)($_POST['auto_post_mode'] ?? 'approval'), ['approval', 'auto'], true) ? (string)$_POST['auto_post_mode'] : 'approval', $actor);
            social_bot_set_setting('dry_run_mode', isset($_POST['dry_run_mode']), $actor);
            social_bot_set_setting('replies_enabled', isset($_POST['replies_enabled']), $actor);
            social_bot_set_setting('require_reply_approval', isset($_POST['require_reply_approval']), $actor);
            social_bot_set_setting('reply_min_confidence', (float)($_POST['reply_min_confidence'] ?? 0.6), $actor);
            social_bot_save_ai_config($_POST, $actor);
            social_bot_set_setting('site_urls', [
                'siteUrl' => trim((string)($_POST['site_url'] ?? '')),
                'docsUrl' => trim((string)($_POST['docs_url'] ?? '')),
                'poolUrl' => trim((string)($_POST['pool_url'] ?? '')),
                'explorerUrl' => trim((string)($_POST['explorer_url'] ?? '')),
                'walletUrl' => trim((string)($_POST['wallet_url'] ?? '')),
                'downloadsUrl' => trim((string)($_POST['downloads_url'] ?? '')),
                'supportUrl' => trim((string)($_POST['support_url'] ?? '')),
            ], $actor);
            social_bot_set_setting('site_link_settings', [
                'enabled' => isset($_POST['site_link_enabled']),
                'min_posts' => max(1, (int)($_POST['site_link_min'] ?? 5)),
                'max_posts' => max(1, (int)($_POST['site_link_max'] ?? 10)),
            ], $actor);
            social_bot_set_setting('utm_settings', [
                'enabled' => isset($_POST['utm_enabled']),
                'medium' => trim((string)($_POST['utm_medium'] ?? 'social')) ?: 'social',
                'campaign' => trim((string)($_POST['utm_campaign'] ?? 'hobc_update_bot')) ?: 'hobc_update_bot',
                'sources' => [
                    'discord' => trim((string)($_POST['utm_source_discord'] ?? 'discord')) ?: 'discord',
                    'x' => trim((string)($_POST['utm_source_x'] ?? 'twitter')) ?: 'twitter',
                    'facebook' => trim((string)($_POST['utm_source_facebook'] ?? 'facebook')) ?: 'facebook',
                ],
            ], $actor);
            admin_audit((int)$admin['id'], 'social_bot_settings_update', 'social_bot', 'settings', []);
            admin_flash_set('success', 'Social bot settings saved.');
            social_bot_redirect('settings');
        } elseif ($action === 'save_x_budget') {
            $actor = (string)$admin['username'];
            social_bot_save_x_budget_settings($_POST, $actor);
            admin_audit((int)$admin['id'], 'social_bot_x_budget_update', 'social_bot', 'x_budget', []);
            admin_flash_set('success', 'X budget settings saved.');
            social_bot_redirect('budget');
        } elseif ($action === 'run_scheduler') {
            social_bot_run_scheduler((string)$admin['username']);
            admin_audit((int)$admin['id'], 'social_bot_scheduler_run', 'social_bot', 'scheduler', []);
            admin_flash_set('success', 'Scheduler tick completed.');
            social_bot_redirect('overview');
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        admin_flash_set('error', $e->getMessage());
        social_bot_redirect($tab);
    }
}

$botAvailable = social_bot_available();
$health = social_bot_service_health();
$stats = $botAvailable ? social_bot_stats() : [];
$collectors = ($botAvailable && $tab === 'overview') ? social_bot_collector_status() : null;
$xBudget = ($botAvailable && $tab === 'overview') ? social_bot_x_budget_summary() : null;
$xBudgetDashboard = ($botAvailable && $tab === 'budget') ? social_bot_x_budget_dashboard(14) : null;
$dryRun = $botAvailable ? social_bot_dry_run_enabled() : true;

if ($tab === 'preview' && $botAvailable && ($_GET['generate'] ?? '') === '1') {
    try {
        $useAi = ($_GET['use_ai'] ?? '') === '1';
        $result = social_bot_preview_post(
            (string)($_GET['platform'] ?? 'discord'),
            ($_GET['topic'] ?? '') !== '' ? (string)$_GET['topic'] : null,
            $useAi
        );
        $preview = $result['preview'] ?? null;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

render_admin_header('Social Bot', ['Social Bot']);
?>
<div class="admin-card">
  <p>Official <strong>HobbyHash Update Bot</strong> — automated posts to Discord, X, and Facebook. Platform keys, AI settings, and URLs are editable below (stored in the bot database).</p>
  <p class="admin-muted" style="margin-top:0.5rem"><?= h(social_bot_datetime_note()) ?></p>
  <?php if ($dryRun): ?>
    <?php admin_render_alert('warning', 'Dry-run mode is ON — posts are generated and logged but not published to social platforms. Turn off in Settings when ready to go live.'); ?>
  <?php endif; ?>
  <?php if (!$botAvailable): ?>
    <?php admin_render_alert('warning', 'Social bot database not found. Install the bot: cd /home/hobbyhashcoin/social-bot && npm install && npm run migrate && npm run seed'); ?>
  <?php elseif (!$health['ok']): ?>
    <?php admin_render_alert('warning', 'Node bot service is offline. Preview still works via local CLI; publish and scheduler actions need the service running (systemctl start hobbyhash-social-bot).'); ?>
  <?php endif; ?>
</div>

<nav class="admin-tabs" aria-label="Social bot sections">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="admin-tab<?= $tab === $key ? ' is-active' : '' ?>" href="<?= h(admin_url('/social-bot.php?tab=' . rawurlencode($key))) ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($err !== ''): ?><?php admin_render_alert('error', $err); ?><?php endif; ?>

<?php if ($tab === 'overview' && $botAvailable): ?>
<div class="admin-grid admin-grid-tight">
  <?= admin_stat_card('Pending Posts', (string)($stats['pending_posts'] ?? 0), ($stats['pending_posts'] ?? 0) > 0 ? 'warn' : 'ok') ?>
  <?= admin_stat_card('Published Today', (string)($stats['published_today'] ?? 0), 'info') ?>
  <?= admin_stat_card('Pending Replies', (string)($stats['pending_replies'] ?? 0), ($stats['pending_replies'] ?? 0) > 0 ? 'warn' : 'ok') ?>
  <?= admin_stat_card('Node Service', ($stats['service_ok'] ?? false) ? 'Online' : 'Offline', ($stats['service_ok'] ?? false) ? 'ok' : 'error') ?>
</div>
<?php if ($xBudget): ?>
<?php
  $xb = $xBudget;
  $xUsage = $xb['usage'] ?? [];
?>
<div class="admin-card">
  <h3>X API budget</h3>
  <p style="color:var(--muted);font-size:0.9rem;margin-top:0">
    Spent <strong>$<?= h(number_format((float)($xb['spent_usd'] ?? 0), 2)) ?></strong>
    of <strong>$<?= h(number_format((float)($xb['budget_usd'] ?? 0), 2)) ?></strong> today.
    <?= (int)($xUsage['blocked'] ?? 0) > 0 ? h((string)(int)$xUsage['blocked']) . ' blocked by cap.' : '' ?>
  </p>
  <div class="admin-actions" style="margin-top:0.75rem">
    <?= admin_action_button('Open X Budget charts', admin_url('/social-bot.php?tab=budget')) ?>
  </div>
</div>
<?php endif; ?>
<div class="admin-card">
  <h3>Quick actions</h3>
  <div class="admin-actions">
    <?= admin_action_button('Approval Queue', admin_url('/social-bot.php?tab=queue')) ?>
    <?= admin_action_button('Generate Preview', admin_url('/social-bot.php?tab=preview')) ?>
    <?= admin_action_button('X Budget', admin_url('/social-bot.php?tab=budget'), 'secondary') ?>
    <?= admin_action_button('Settings', admin_url('/social-bot.php?tab=settings'), 'secondary') ?>
  </div>
  <form method="POST" action="<?= h(admin_url('/social-bot.php?tab=overview')) ?>" style="margin-top:1rem">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="run_scheduler">
    <button type="submit" class="admin-action admin-action-secondary">Run scheduler tick now</button>
  </form>
</div>
<?php if ($collectors): ?>
<div class="admin-card">
  <h3>Live data sources</h3>
  <?php admin_render_table(['Source', 'Value'], [
    ['Chain height', h((string)($collectors['blocks']['height'] ?? '—'))],
    ['Pool miners', h((string)($collectors['miners']['totalMiners'] ?? '—'))],
    ['Pool workers', h((string)($collectors['miners']['totalWorkers'] ?? '—'))],
    ['Pool hashrate', h((string)($collectors['miners']['hashrate'] ?? '—'))],
    ['Human pageviews', h(number_format((int)($collectors['site']['humanPageviews'] ?? 0)))],
    ['Today pageviews', h(number_format((int)($collectors['site']['dailyPageviews'] ?? 0)))],
    ['Published downloads', h((string)($collectors['downloads']['count'] ?? '0'))],
    ['Docs / announcements', h(($collectors['content']['docs'] ?? 0) . ' / ' . ($collectors['content']['announcements'] ?? 0))],
  ], 'No collector data', 'Start the Node service to load live collector data.'); ?>
  <p style="color:var(--muted);font-size:0.9rem">CLI: <code>npm run test-generate</code> for a full data + sample post report.</p>
</div>
<?php endif; ?>

<?php elseif ($tab === 'budget' && $botAvailable): ?>
<?php
  $dash = $xBudgetDashboard ?? social_bot_x_budget_dashboard(14);
  $summary = $dash['summary'] ?? social_bot_x_budget_summary();
  $settings = $dash['settings'] ?? social_bot_x_budget_settings();
  $usage = $dash['usage'] ?? social_bot_x_daily_usage();
  $spent = (float)($summary['spent_usd'] ?? 0);
  $remaining = (float)($summary['remaining_usd'] ?? 0);
  $budget = (float)($summary['budget_usd'] ?? 0);
?>
<div class="admin-card">
  <p>Track X API spend for <strong>reads</strong> (mention polling) and <strong>creates</strong> (posts/replies). Owned reads (your mentions, your profile) are cheap; public search reads cost more. Replies with URLs are the expensive create type.</p>
  <p class="admin-muted" style="margin-top:0.5rem">
    Example: 100 owned mention reads ≈ $0.10, 20 plain replies ≈ $0.30 → about $0.40/day without links.
    Same post fetched twice in one UTC day is deduped to a single read charge when deduplication is enabled.
  </p>
  <?php if (empty($settings['enabled'])): ?>
    <?php admin_render_alert('warning', 'Budget enforcement is currently OFF — usage is tracked but creates are not blocked.'); ?>
  <?php endif; ?>
  <?php if ($dryRun): ?>
    <?php admin_render_alert('warning', 'Dry-run mode is ON — live X creates are not sent, so usage will stay at zero until dry-run is disabled.'); ?>
  <?php endif; ?>
</div>

<div class="admin-grid admin-grid-tight">
  <?= admin_stat_card('Spent Today', '$' . h(number_format($spent, 2)), 'info') ?>
  <?= admin_stat_card('Remaining', '$' . h(number_format($remaining, 2)), $remaining > 0 ? 'ok' : 'error') ?>
  <?= admin_stat_card('Daily Budget', '$' . h(number_format($budget, 2)), 'info') ?>
  <?= admin_stat_card('Blocked Today', (string)(int)($usage['blocked'] ?? 0), ((int)($usage['blocked'] ?? 0) > 0) ? 'warn' : 'ok') ?>
  <?= admin_stat_card('Read Spend', '$' . h(number_format((float)($summary['read_cost_usd'] ?? 0), 3)), 'info') ?>
  <?= admin_stat_card('Create Spend', '$' . h(number_format((float)($summary['create_cost_usd'] ?? 0), 3)), 'info') ?>
</div>

<div class="admin-grid" style="grid-template-columns:repeat(auto-fit,minmax(18rem,1fr));gap:1rem">
  <?php social_bot_render_x_budget_meter($spent, $budget); ?>
  <?php social_bot_render_x_budget_pie([
    ['label' => 'Spent', 'value' => $spent],
    ['label' => 'Remaining', 'value' => $remaining],
  ], 'Budget split ($)'); ?>
</div>

<div class="admin-grid" style="grid-template-columns:repeat(auto-fit,minmax(18rem,1fr));gap:1rem">
  <?php social_bot_render_x_budget_bar_chart($dash['daily'] ?? [], 'label', 'spent_usd', 'Daily spend (last 14 days)', true); ?>
  <?php social_bot_render_x_budget_bar_chart($dash['hourly'] ?? [], 'label', 'spent_usd', 'Today by hour', true); ?>
</div>

<div class="admin-grid" style="grid-template-columns:repeat(auto-fit,minmax(18rem,1fr));gap:1rem">
  <?php social_bot_render_x_budget_pie($dash['breakdown'] ?? [], 'Usage today (counts)'); ?>
  <?php social_bot_render_x_budget_pie($dash['cost_breakdown'] ?? [], 'Spend today ($)'); ?>
</div>

<div class="admin-grid" style="grid-template-columns:repeat(auto-fit,minmax(18rem,1fr));gap:1rem">
  <?php social_bot_render_x_budget_cap_chart($dash['url_caps'] ?? [], 'URL caps used today'); ?>
</div>

<div class="admin-card">
  <h3>Estimates at current remaining budget</h3>
  <?php admin_render_table(['Rate', 'Price', 'Approx. left today'], [
    ['Owned read (mentions)', '$' . h(number_format((float)($settings['owned_read_usd'] ?? 0.001), 3)), h((string)(int)($summary['estimated_owned_reads_left'] ?? 0)) . ' reads'],
    ['Public post read (search)', '$' . h(number_format((float)($settings['post_read_usd'] ?? 0.005), 3)), 'Avoid — use mentions poll'],
    ['Plain create (post/reply)', '$' . h(number_format((float)($settings['plain_create_usd'] ?? 0.015), 3)), h((string)(int)($summary['estimated_plain_left'] ?? 0)) . ' creates'],
    ['Create with URL', '$' . h(number_format((float)($settings['url_create_usd'] ?? 0.2), 3)), h((string)(int)($summary['estimated_url_left'] ?? 0)) . ' creates'],
    ['Posts with URL cap', h((string)(int)($settings['max_posts_with_url_per_day'] ?? 0)) . '/day', h((string)(int)($summary['posts_with_url_left'] ?? 0)) . ' left'],
    ['Replies with URL cap', h((string)(int)($settings['max_replies_with_url_per_day'] ?? 0)) . '/day', h((string)(int)($summary['replies_with_url_left'] ?? 0)) . ' left'],
    ['Reads deduped today', 'UTC day (logged as $0)', h((string)(int)($usage['reads_deduped'] ?? 0)) . ' in log'],
  ], 'No estimates', 'Set a daily budget below to see estimates.'); ?>
</div>

<div class="admin-card">
  <h3>Recent X API activity</h3>
  <p class="admin-muted" style="margin-top:0">Every read, deduped read ($0), poll, and create is logged. Budget totals only include charged rows — deduped rows match X billing (same resource, same UTC day).</p>
  <?php admin_render_table(['Time', 'Type', 'Notes', 'Cost', 'Reference'], array_map(static function (array $row): array {
      $cost = (float)($row['cost_usd'] ?? 0);
      return [
          social_bot_h_datetime((string)($row['created_at'] ?? '')),
          h(social_bot_format_x_action_type((string)($row['action_type'] ?? ''))),
          h(social_bot_x_usage_row_notes($row)),
          $cost > 0 ? ('$' . h(number_format($cost, 3))) : '$0',
          h((string)($row['reference_id'] ?? '—')),
      ];
  }, $dash['recent_log'] ?? []), 'No activity logged yet', 'Reads, polls, and creates appear here after the bot runs.'); ?>
  <p class="admin-muted" style="margin-top:0.75rem"><?= h(social_bot_datetime_note()) ?></p>
</div>

<div class="admin-card">
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_x_budget">
    <h3>Edit daily budget &amp; limits</h3>
    <label><input type="checkbox" name="x_budget_enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : '' ?>> Enforce daily X API budget</label>
    <label>Daily budget (USD)</label>
    <input type="number" name="x_daily_budget_usd" step="0.01" min="0" value="<?= h((string)($settings['daily_budget_usd'] ?? 5)) ?>">
    <label>Max posts with URL per day</label>
    <input type="number" name="x_max_posts_with_url" min="0" value="<?= h((string)($settings['max_posts_with_url_per_day'] ?? 3)) ?>">
    <label>Max replies with URL per day</label>
    <input type="number" name="x_max_replies_with_url" min="0" value="<?= h((string)($settings['max_replies_with_url_per_day'] ?? 2)) ?>">
    <label>Reply link probability (0–1) when user asks for wallet/docs/link</label>
    <input type="number" name="x_reply_link_probability" step="0.05" min="0" max="1" value="<?= h((string)($settings['reply_link_probability'] ?? 0.15)) ?>">
    <label>Plain create price (USD)</label>
    <input type="number" name="x_plain_create_usd" step="0.001" min="0" value="<?= h((string)($settings['plain_create_usd'] ?? 0.015)) ?>">
    <label>URL create price (USD)</label>
    <input type="number" name="x_url_create_usd" step="0.01" min="0" value="<?= h((string)($settings['url_create_usd'] ?? 0.2)) ?>">

    <h4>Read pricing</h4>
    <label>Owned read price (USD) — your mentions, profile, owned resources</label>
    <input type="number" name="x_owned_read_usd" step="0.0001" min="0" value="<?= h((string)($settings['owned_read_usd'] ?? 0.001)) ?>">
    <label>Public post read price (USD) — search / third-party posts</label>
    <input type="number" name="x_post_read_usd" step="0.001" min="0" value="<?= h((string)($settings['post_read_usd'] ?? 0.005)) ?>">
    <label>User profile read price (USD) — non-owned lookups</label>
    <input type="number" name="x_user_read_usd" step="0.001" min="0" value="<?= h((string)($settings['user_read_usd'] ?? 0.01)) ?>">
    <label><input type="checkbox" name="x_mention_search_fallback" value="1" <?= !empty($settings['mention_search_fallback']) ? 'checked' : '' ?>> Enable search fallback for mentions (costs ~5× more per post — leave off for cheap polling)</label>
    <label><input type="checkbox" name="x_dedupe_reads_utc" value="1" <?= ($settings['dedupe_reads_utc'] ?? true) ? 'checked' : '' ?>> Dedupe reads per resource per UTC day (matches X billing)</label>

    <button type="submit" class="admin-action">Save X budget settings</button>
  </form>
</div>

<?php elseif ($tab === 'queue' && $botAvailable): ?>
<?php $queue = social_bot_queue_posts(); ?>
<div class="admin-card">
  <h3>Posts awaiting approval</h3>
  <?php if ($queue === []): ?>
    <?= admin_empty_state('Queue empty', 'No posts waiting for approval. Enable platforms and run the scheduler, or switch to full-auto in Settings.') ?>
  <?php else: ?>
    <?php foreach ($queue as $post): ?>
    <div class="admin-card" style="margin-bottom:1rem">
      <p><?= social_bot_status_badge((string)$post['status']) ?> <strong><?= h((string)$post['platform']) ?></strong> — <?= h((string)$post['topic']) ?> / <?= h((string)$post['tone']) ?></p>
      <pre class="admin-mono-cell" style="white-space:pre-wrap;margin:0.75rem 0"><?= h((string)$post['content']) ?></pre>
      <small><?= social_bot_h_datetime((string)$post['created_at']) ?></small>
      <?php if ($post['status'] === 'pending'): ?>
      <div class="admin-actions" style="margin-top:0.75rem">
        <form method="POST" class="inline">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="approve_post">
          <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
          <button type="submit" class="admin-action">Approve &amp; publish</button>
        </form>
        <form method="POST" class="inline">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="reject_post">
          <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
          <button type="submit" class="admin-action admin-action-danger">Reject</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'history' && $botAvailable): ?>
<?php admin_render_table(['Time', 'Platform', 'Status', 'Topic', 'Content'], array_map(static fn(array $p): array => [
    social_bot_h_datetime((string)($p['published_at'] ?: $p['created_at'])),
    h((string)$p['platform']),
    social_bot_status_badge((string)$p['status']),
    h((string)$p['topic']),
    h(mb_strimwidth((string)$p['content'], 0, 120, '…')),
], social_bot_posts(100)), 'No posts yet', 'Published posts will appear here.'); ?>

<?php elseif ($tab === 'replies' && $botAvailable): ?>
<?php $replies = social_bot_replies(100); ?>
<?php if ($replies === []): ?>
  <?= admin_empty_state('No replies yet', 'FAQ auto-replies will be logged here when Discord reply channels are configured.') ?>
<?php else: ?>
  <?php foreach ($replies as $reply): ?>
  <div class="admin-card">
    <p><?= social_bot_status_badge((string)$reply['status']) ?> <strong><?= h((string)$reply['platform']) ?></strong> — <?= h((string)$reply['intent']) ?> (<?= h(number_format((float)$reply['confidence'] * 100, 0)) ?>%)</p>
    <p><strong>In:</strong> <?= h((string)$reply['incoming_text']) ?></p>
    <p><strong>Reply:</strong> <?= h((string)$reply['reply_text']) ?></p>
    <p class="admin-muted"><small>Created <?= social_bot_h_datetime((string)$reply['created_at']) ?><?php if (!empty($reply['replied_at'])): ?> · Sent <?= social_bot_h_datetime((string)$reply['replied_at']) ?><?php endif; ?></small></p>
    <?php if ($reply['status'] === 'pending'): ?>
    <div class="admin-actions">
      <form method="POST" class="inline">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="approve_reply">
        <input type="hidden" name="id" value="<?= (int)$reply['id'] ?>">
        <button type="submit" class="admin-action">Approve send</button>
      </form>
      <form method="POST" class="inline">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="reject_reply">
        <input type="hidden" name="id" value="<?= (int)$reply['id'] ?>">
        <button type="submit" class="admin-action admin-action-danger">Reject</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php elseif ($tab === 'templates' && $botAvailable): ?>
<?php foreach (social_bot_templates() as $tpl): ?>
<div class="admin-card">
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_template">
    <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">
    <h4><?= h((string)$tpl['name']) ?> <small>(<?= h((string)$tpl['tone']) ?> / <?= h((string)$tpl['topic']) ?>)</small></h4>
    <label>Template body</label>
    <textarea name="template_body" rows="3"><?= h((string)$tpl['template_body']) ?></textarea>
    <label>Weight <input type="number" name="weight" value="<?= (int)$tpl['weight'] ?>" min="1" max="10" style="width:5rem"></label>
    <label><input type="checkbox" name="enabled" value="1" <?= (int)$tpl['enabled'] === 1 ? 'checked' : '' ?>> Enabled</label>
    <button type="submit" class="admin-action">Save template</button>
  </form>
</div>
<?php endforeach; ?>

<?php elseif ($tab === 'platforms' && $botAvailable): ?>
<?php $creds = social_bot_platform_credentials(); ?>
<div class="admin-card">
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_platform_keys">
    <h3>Discord</h3>
    <label><input type="checkbox" name="discord_enabled" value="1" <?= !empty($creds['discord']['enabled']) ? 'checked' : '' ?>> Enabled</label>
    <label>Bot token <?= h(social_bot_secret_hint((string)$creds['discord']['botToken'])) ?></label>
    <input type="password" name="discord_bot_token" placeholder="Leave blank to keep saved value" autocomplete="new-password">
    <label>Webhook URL <?= h(social_bot_secret_hint((string)$creds['discord']['webhookUrl'])) ?></label>
    <input type="password" name="discord_webhook_url" placeholder="https://discord.com/api/webhooks/..." autocomplete="new-password">
    <label>Channel ID</label>
    <input type="text" name="discord_channel_id" value="<?= h((string)$creds['discord']['channelId']) ?>">
    <label>Reply channel IDs (comma-separated)</label>
    <input type="text" name="discord_reply_channel_ids" value="<?= h(is_array($creds['discord']['replyChannelIds']) ? implode(',', $creds['discord']['replyChannelIds']) : (string)$creds['discord']['replyChannelIds']) ?>">

    <h3>X (Twitter)</h3>
    <label><input type="checkbox" name="x_enabled" value="1" <?= !empty($creds['x']['enabled']) ? 'checked' : '' ?>> Enabled</label>
    <label><input type="checkbox" name="x_reply_enabled" value="1" <?= !empty($creds['x']['replyEnabled']) ? 'checked' : '' ?>> Reply to mentions</label>
    <label>API key <?= h(social_bot_secret_hint((string)$creds['x']['apiKey'])) ?></label>
    <input type="password" name="x_api_key" autocomplete="new-password">
    <label>API secret <?= h(social_bot_secret_hint((string)$creds['x']['apiSecret'])) ?></label>
    <input type="password" name="x_api_secret" autocomplete="new-password">
    <label>Access token <?= h(social_bot_secret_hint((string)$creds['x']['accessToken'])) ?></label>
    <input type="password" name="x_access_token" autocomplete="new-password">
    <label>Access secret <?= h(social_bot_secret_hint((string)$creds['x']['accessSecret'])) ?></label>
    <input type="password" name="x_access_secret" autocomplete="new-password">
    <label>Bearer token <?= h(social_bot_secret_hint((string)$creds['x']['bearerToken'])) ?></label>
    <input type="password" name="x_bearer_token" autocomplete="new-password">

    <h4>OAuth 2.0 (recommended for posting)</h4>
    <p class="admin-help">Add this callback URL in your X app under <strong>User authentication settings → Callback URI</strong>:</p>
    <p><code><?= h(social_bot_x_oauth_redirect_uri()) ?></code></p>
    <label>OAuth 2.0 Client ID</label>
    <input type="text" name="x_oauth2_client_id" value="<?= h((string)($creds['x']['oauth2ClientId'] ?? '')) ?>" autocomplete="off">
    <label>OAuth 2.0 Client Secret <?= h(social_bot_secret_hint((string)($creds['x']['oauth2ClientSecret'] ?? ''))) ?></label>
    <input type="password" name="x_oauth2_client_secret" autocomplete="new-password">
    <label>OAuth 2.0 Redirect URI</label>
    <input type="text" name="x_oauth2_redirect_uri" value="<?= h((string)($creds['x']['oauth2RedirectUri'] ?? social_bot_x_oauth_redirect_uri())) ?>">
    <?php if (!empty($creds['x']['oauth2AccessToken'])): ?>
    <p class="admin-help">OAuth 2.0 user token: connected<?= !empty($creds['x']['oauth2Scope']) ? ' (' . h((string)$creds['x']['oauth2Scope']) . ')' : '' ?>.</p>
    <?php else: ?>
    <p class="admin-help">Save keys above, then connect your @HobbyHashCoin account:</p>
    <?php endif; ?>
    <p><a class="admin-action" href="<?= h(admin_url('/social-bot.php?tab=platforms&x_oauth=start')) ?>">Connect X (OAuth 2.0)</a></p>
    <details style="margin-top:12px">
      <summary>Manual connect (if X login loops)</summary>
      <p class="admin-help">1) Click Connect above and approve on X.<br>2) If the browser stops on an error page, copy the full URL from the address bar.<br>3) Paste it below within 10 minutes.</p>
      <form method="POST" style="margin-top:8px">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="x_oauth_manual">
        <label>Redirect URL or authorization code</label>
        <input type="text" name="x_oauth_code" placeholder="https://hobbyhashcoin.com/admin/social-bot-x-oauth.php?code=..." style="width:100%">
        <button type="submit" class="admin-action" style="margin-top:8px">Complete X connection</button>
      </form>
    </details>

    <h3>Facebook Page</h3>
    <label><input type="checkbox" name="facebook_enabled" value="1" <?= !empty($creds['facebook']['enabled']) ? 'checked' : '' ?>> Enabled</label>
    <label><input type="checkbox" name="facebook_reply_enabled" value="1" <?= !empty($creds['facebook']['replyEnabled']) ? 'checked' : '' ?>> Reply to comments</label>
    <label>App ID</label>
    <input type="text" name="facebook_app_id" value="<?= h((string)($creds['facebook']['appId'] ?? '')) ?>" autocomplete="off">
    <label>App Secret <?= h(social_bot_secret_hint((string)($creds['facebook']['appSecret'] ?? ''))) ?></label>
    <input type="password" name="facebook_app_secret" autocomplete="new-password">
    <p class="admin-help">OAuth redirect URI (add this under Facebook Login → Valid OAuth Redirect URIs in your Meta app):</p>
    <p><code><?= h(social_bot_facebook_oauth_redirect_uri()) ?></code></p>
    <label>Page ID</label>
    <input type="text" name="facebook_page_id" value="<?= h((string)$creds['facebook']['pageId']) ?>" placeholder="1122654560933297">
    <?php if (!empty($creds['facebook']['pageAccessToken'])): ?>
    <p class="admin-help">Page token: connected<?= !empty($creds['facebook']['pageName']) ? ' (' . h((string)$creds['facebook']['pageName']) . ')' : '' ?>.</p>
    <?php endif; ?>
    <p><a class="admin-action" href="<?= h(admin_url('/social-bot.php?tab=platforms&facebook_oauth=start')) ?>">Connect Facebook (long-lived Page token)</a></p>
    <label>Page access token (manual override) <?= h(social_bot_secret_hint((string)$creds['facebook']['pageAccessToken'])) ?></label>
    <input type="password" name="facebook_page_access_token" autocomplete="new-password">

    <button type="submit" class="admin-action">Save platform keys</button>
  </form>
</div>

<?php elseif ($tab === 'settings' && $botAvailable): ?>
<?php
$schedule = social_bot_setting('posting_schedule', []);
$quiet = social_bot_setting('quiet_hours', []);
$ai = social_bot_ai_config();
$urls = social_bot_site_urls();
$link = social_bot_setting('site_link_settings', ['enabled' => true, 'min_posts' => 5, 'max_posts' => 10]);
$utm = social_bot_utm_settings();
?>
<div class="admin-card">
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_settings">
    <h3>Posting schedule</h3>
    <label>Min hours between posts</label>
    <input type="number" name="min_hours" step="0.5" value="<?= h((string)($schedule['min_hours'] ?? 3)) ?>">
    <label>Max hours between posts</label>
    <input type="number" name="max_hours" step="0.5" value="<?= h((string)($schedule['max_hours'] ?? 6)) ?>">
    <label>Post probability per 15-min tick (0–1)</label>
    <input type="number" name="post_probability" step="0.05" min="0" max="1" value="<?= h((string)($schedule['post_probability'] ?? 0.25)) ?>">
    <label>Max posts per day per platform</label>
    <input type="number" name="max_posts_per_day" value="<?= h((string)social_bot_setting('max_posts_per_day', 5)) ?>">
    <h3>Quiet hours (<?= h(admin_timezone_label()) ?>)</h3>
    <label><input type="checkbox" name="quiet_enabled" value="1" <?= !empty($quiet['enabled']) ? 'checked' : '' ?>> Enable quiet hours</label>
    <label>Start hour</label>
    <input type="number" name="quiet_start" min="0" max="23" value="<?= h((string)($quiet['start'] ?? 23)) ?>">
    <label>End hour</label>
    <input type="number" name="quiet_end" min="0" max="23" value="<?= h((string)($quiet['end'] ?? 7)) ?>">
    <p class="admin-muted" style="margin-top:0.25rem">Hours use <?= h(admin_timezone_label()) ?> local time, same as the rest of this admin.</p>
    <h3>Modes</h3>
    <label><input type="checkbox" name="dry_run_mode" value="1" <?= social_bot_dry_run_enabled() ? 'checked' : '' ?>> Dry-run mode (generate &amp; log only — recommended until go-live)</label>
    <label>Auto post mode</label>
    <select name="auto_post_mode">
      <option value="approval" <?= social_bot_setting('auto_post_mode') === 'approval' ? 'selected' : '' ?>>Require approval</option>
      <option value="auto" <?= social_bot_setting('auto_post_mode') === 'auto' ? 'selected' : '' ?>>Full auto</option>
    </select>
    <h3>Replies</h3>
    <label><input type="checkbox" name="replies_enabled" value="1" <?= social_bot_setting('replies_enabled', true) ? 'checked' : '' ?>> Enable auto-replies</label>
    <label><input type="checkbox" name="require_reply_approval" value="1" <?= social_bot_setting('require_reply_approval') ? 'checked' : '' ?>> Require approval for all replies</label>
    <label>Min confidence to auto-reply</label>
    <input type="number" name="reply_min_confidence" step="0.05" min="0" max="1" value="<?= h((string)social_bot_setting('reply_min_confidence', 0.6)) ?>">

    <h3>AI generation (choose one provider — only the selected provider is used)</h3>
    <label><input type="checkbox" name="ai_enabled" value="1" <?= !empty($ai['enabled']) ? 'checked' : '' ?>> Enable AI</label>
    <label><input type="checkbox" name="ai_always_posts" value="1" <?= !empty($ai['always_use_for_posts']) ? 'checked' : '' ?>> Use AI for every scheduled post</label>
    <label><input type="checkbox" name="ai_replies" value="1" <?= ($ai['replies_use_ai'] ?? true) ? 'checked' : '' ?>> Use AI for replies (reads the message and responds)</label>
    <label>Provider</label>
    <select name="ai_provider" id="ai-provider-select">
      <option value="none" <?= ($ai['provider'] ?? 'none') === 'none' ? 'selected' : '' ?>>None — templates only</option>
      <option value="openai" <?= ($ai['provider'] ?? '') === 'openai' ? 'selected' : '' ?>>ChatGPT (OpenAI)</option>
      <option value="anthropic" <?= ($ai['provider'] ?? '') === 'anthropic' ? 'selected' : '' ?>>Claude (Anthropic)</option>
    </select>
    <label>AI use probability (0–1; use 1 or check “every scheduled post” for always)</label>
    <input type="number" name="ai_use_probability" step="0.05" min="0" max="1" value="<?= h((string)($ai['use_probability'] ?? 1)) ?>">
    <h4>ChatGPT / OpenAI</h4>
    <div id="ai-openai-fields">
    <label>API key <?= h(social_bot_secret_hint((string)($ai['openai']['apiKey'] ?? ''))) ?></label>
    <input type="password" name="openai_api_key" autocomplete="new-password">
    <label>Model</label>
    <input type="text" name="openai_model" value="<?= h((string)($ai['openai']['model'] ?? 'gpt-4o-mini')) ?>">
    </div>
    <h4>Claude / Anthropic</h4>
    <div id="ai-anthropic-fields">
    <label>API key <?= h(social_bot_secret_hint((string)($ai['anthropic']['apiKey'] ?? ''))) ?></label>
    <input type="password" name="anthropic_api_key" autocomplete="new-password">
    <label>Model</label>
    <input type="text" name="anthropic_model" value="<?= h((string)($ai['anthropic']['model'] ?? 'claude-3-5-haiku-latest')) ?>">
    </div>
    <script>
    (function(){
      var sel = document.getElementById('ai-provider-select');
      function sync(){
        var p = sel ? sel.value : 'none';
        var o = document.getElementById('ai-openai-fields');
        var a = document.getElementById('ai-anthropic-fields');
        if (o) o.style.opacity = (p === 'openai') ? '1' : '0.45';
        if (a) a.style.opacity = (p === 'anthropic') ? '1' : '0.45';
      }
      if (sel) { sel.addEventListener('change', sync); sync(); }
    })();
    </script>

    <h3>Site URLs (used in posts)</h3>
    <label>Main site</label><input type="url" name="site_url" value="<?= h($urls['siteUrl']) ?>">
    <label>Docs</label><input type="url" name="docs_url" value="<?= h($urls['docsUrl']) ?>">
    <label>Pool</label><input type="url" name="pool_url" value="<?= h($urls['poolUrl']) ?>">
    <label>Explorer</label><input type="url" name="explorer_url" value="<?= h($urls['explorerUrl']) ?>">
    <label>Wallet</label><input type="url" name="wallet_url" value="<?= h($urls['walletUrl']) ?>">
    <label>Downloads</label><input type="url" name="downloads_url" value="<?= h($urls['downloadsUrl']) ?>">
    <label>Support</label><input type="url" name="support_url" value="<?= h($urls['supportUrl']) ?>">

    <h3>Website link in posts</h3>
    <label><input type="checkbox" name="site_link_enabled" value="1" <?= !empty($link['enabled']) ? 'checked' : '' ?>> Include hobbyhashcoin.com link every 5–10 posts</label>
    <label>Min posts between links</label><input type="number" name="site_link_min" min="1" value="<?= h((string)($link['min_posts'] ?? 5)) ?>">
    <label>Max posts between links</label><input type="number" name="site_link_max" min="1" value="<?= h((string)($link['max_posts'] ?? 10)) ?>">

    <h3>UTM tracking (one tag set per platform)</h3>
    <label><input type="checkbox" name="utm_enabled" value="1" <?= !empty($utm['enabled']) ? 'checked' : '' ?>> Append UTM parameters to site links in posts</label>
    <label>Campaign (utm_campaign)</label>
    <input type="text" name="utm_campaign" value="<?= h((string)($utm['campaign'] ?? 'hobc_update_bot')) ?>">
    <label>Medium (utm_medium)</label>
    <input type="text" name="utm_medium" value="<?= h((string)($utm['medium'] ?? 'social')) ?>">
    <label>Discord source (utm_source)</label>
    <input type="text" name="utm_source_discord" value="<?= h((string)($utm['sources']['discord'] ?? 'discord')) ?>">
    <label>X source (utm_source)</label>
    <input type="text" name="utm_source_x" value="<?= h((string)($utm['sources']['x'] ?? 'twitter')) ?>">
    <label>Facebook source (utm_source)</label>
    <input type="text" name="utm_source_facebook" value="<?= h((string)($utm['sources']['facebook'] ?? 'facebook')) ?>">
    <p style="color:var(--muted);font-size:0.9rem">Example Discord link: <code>https://hobbyhashcoin.com?utm_source=discord&amp;utm_medium=social&amp;utm_campaign=hobc_update_bot</code></p>
    <p style="color:var(--muted);font-size:0.9rem">X API budget, spend charts, and URL caps are on the <a href="<?= h(admin_url('/social-bot.php?tab=budget')) ?>">X Budget</a> tab.</p>

    <button type="submit" class="admin-action">Save settings</button>
  </form>
</div>

<?php elseif ($tab === 'preview' && $botAvailable): ?>
<div class="admin-card">
  <form method="GET" action="<?= h(admin_url('/social-bot.php')) ?>">
    <input type="hidden" name="tab" value="preview">
    <input type="hidden" name="generate" value="1">
    <label>Platform</label>
    <select name="platform"><option value="discord">discord</option><option value="x">x</option><option value="facebook">facebook</option></select>
    <label>Topic (optional)</label>
    <input type="text" name="topic" placeholder="pool_status">
    <label><input type="checkbox" name="use_ai" value="1"> Use AI if enabled in Settings</label>
    <button type="submit" class="admin-action">Generate preview</button>
  </form>
  <p style="color:var(--muted);font-size:0.9rem;margin-top:0.75rem">Preview runs locally if the Node service is offline (no 404).</p>
</div>
<?php if ($preview): ?>
<div class="admin-card">
  <p><strong>Topic:</strong> <?= h((string)$preview['topic']) ?> | <strong>Tone:</strong> <?= h((string)$preview['tone']) ?> | <strong>Source:</strong> <?= h((string)($preview['source'] ?? '')) ?></p>
  <pre style="white-space:pre-wrap"><?= h((string)$preview['content']) ?></pre>
</div>
<?php endif; ?>

<?php elseif ($tab === 'test' && $botAvailable): ?>
<div class="admin-card">
  <h3>CLI test commands</h3>
  <p>Run on the server from <code>/home/hobbyhashcoin/social-bot</code>. Dry-run is ON by default — add <code>--live</code> to platform tests to publish for real.</p>
  <pre class="admin-mono-cell">npm run test-generate    # Report live data + sample posts (no publish)
npm run test-discord     # Test Discord (dry-run unless --live)
npm run test-x           # Test X (dry-run unless --live)
npm run test-facebook    # Test Facebook (dry-run unless --live)
npm run test-discord -- --live   # Actually publish to Discord</pre>
  <p>Dry-run output is logged to <code>/social-bot/logs/dry-run.log</code>.</p>
  <div class="admin-actions">
    <?= admin_action_button('Generate preview in browser', admin_url('/social-bot.php?tab=preview&generate=1')) ?>
    <?= admin_action_button('Approval queue', admin_url('/social-bot.php?tab=queue'), 'secondary') ?>
  </div>
</div>

<?php elseif ($tab === 'audit' && $botAvailable): ?>
<?php admin_render_table(['Time', 'Action', 'Actor', 'Details'], array_map(static fn(array $l): array => [
    social_bot_h_datetime((string)$l['created_at']),
    h((string)$l['action']),
    h((string)$l['actor']),
    h((string)$l['details']),
], social_bot_audit()), 'No audit entries', 'Bot audit log is empty.'); ?>

<?php endif; ?>

<?php render_admin_footer(); ?>
