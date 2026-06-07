const { migrate } = require('./migrate');
const { getDb, setSetting } = require('../db');
const { DEFAULT_TEMPLATES } = require('../generator/templates');
const config = require('../config');
const logger = require('../logger');

function seed() {
  migrate();
  const db = getDb();

  const platforms = [
    { platform: 'discord', enabled: config.discord.enabled ? 1 : 0, display_name: 'Discord' },
    { platform: 'x', enabled: config.x.enabled ? 1 : 0, display_name: 'X (Twitter)' },
    { platform: 'facebook', enabled: config.facebook.enabled ? 1 : 0, display_name: 'Facebook Page' },
  ];

  const insertPlatform = db.prepare(`
    INSERT INTO social_platform_accounts (platform, enabled, display_name)
    VALUES (@platform, @enabled, @display_name)
    ON CONFLICT(platform) DO NOTHING
  `);
  for (const p of platforms) {
    insertPlatform.run(p);
  }

  const insertTemplate = db.prepare(`
    INSERT INTO social_templates (name, tone, topic, template_body, weight, enabled)
    VALUES (@name, @tone, @topic, @body, @weight, 1)
    ON CONFLICT(name) DO NOTHING
  `);
  for (const t of DEFAULT_TEMPLATES) {
    insertTemplate.run({
      name: t.name,
      tone: t.tone,
      topic: t.topic,
      body: t.body,
      weight: t.weight,
    });
  }

function setSettingIfMissing(key, value) {
  const row = db.prepare('SELECT 1 FROM social_settings WHERE key = ?').get(key);
  if (!row) {
    setSetting(key, value);
  }
}

  setSettingIfMissing('posting_schedule', {
    min_hours: 3,
    max_hours: 6,
    post_probability: 0.25,
    check_interval_minutes: 15,
  });

  setSettingIfMissing('quiet_hours', { enabled: true, start: 23, end: 7 });
  setSettingIfMissing('max_posts_per_day', 5);
  setSettingIfMissing('block_found_posts', {
    min_hours_between: 8,
    milestone_modulo: 50,
    max_per_day: 2,
  });
  setSettingIfMissing('auto_post_mode', 'approval');
  setSettingIfMissing('dry_run_mode', true);
  setSettingIfMissing('ai_config', {
    enabled: false,
    provider: 'none',
    use_probability: 1,
    always_use_for_posts: true,
    replies_use_ai: true,
    openai: { apiKey: '', model: 'gpt-4o-mini' },
    anthropic: { apiKey: '', model: 'claude-3-5-haiku-latest' },
  });
  setSettingIfMissing('site_link_settings', { enabled: true, min_posts: 5, max_posts: 10 });
  setSettingIfMissing('utm_settings', {
    enabled: true,
    medium: 'social',
    campaign: 'hobc_update_bot',
    sources: { discord: 'discord', x: 'twitter', facebook: 'facebook' },
  });
  setSettingIfMissing('link_post_counter', 0);
  setSettingIfMissing('site_urls', {
    siteUrl: config.siteUrl,
    docsUrl: config.docsUrl,
    poolUrl: config.poolUrl,
    explorerUrl: config.explorerUrl,
    walletUrl: config.walletUrl,
    downloadsUrl: config.downloadsUrl,
    supportUrl: config.supportUrl,
  });
  setSettingIfMissing('platform_credentials', {
    discord: { enabled: false, botToken: '', webhookUrl: '', channelId: '', replyChannelIds: '' },
    x: { enabled: false, apiKey: '', apiSecret: '', accessToken: '', accessSecret: '', bearerToken: '', replyEnabled: false },
    facebook: { enabled: false, pageId: '', pageAccessToken: '', replyEnabled: false },
  });

  setSettingIfMissing('replies_enabled', true);
  setSettingIfMissing('require_reply_approval', false);
  setSettingIfMissing('reply_min_confidence', 0.6);
  setSettingIfMissing('scheduler_cron', '*/15 * * * *');
  setSettingIfMissing('x_budget_settings', {
    enabled: true,
    daily_budget_usd: 5,
    max_posts_with_url_per_day: 3,
    max_replies_with_url_per_day: 2,
    reply_link_probability: 0.15,
    plain_create_usd: 0.015,
    url_create_usd: 0.2,
    owned_read_usd: 0.001,
    post_read_usd: 0.005,
    user_read_usd: 0.01,
    mention_search_fallback: false,
    dedupe_reads_utc: true,
  });

  logger.info('Seed completed', {
    templates: DEFAULT_TEMPLATES.length,
    platforms: platforms.length,
  });

  console.log('Seed complete.');
  console.log('  Platforms:', platforms.map((p) => p.platform).join(', '));
  console.log('  Templates:', DEFAULT_TEMPLATES.length);
  console.log('  Default mode: approval (posts require admin OK)');
}

seed();
