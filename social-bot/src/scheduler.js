const cron = require('node-cron');
const {
  getDb, getSetting, isPlatformEnabled, countPostsToday,
  getLastPostTopic, auditLog,
} = require('./db');
const logger = require('./logger');
const { generatePost, buildDeprioritizedTopics } = require('./generator/postGenerator');
const { gatherDataContext, detectEvents, processPendingEvents, shouldAnnounceBlockFound } = require('./events/eventProcessor');
const discordAdapter = require('./adapters/discord');
const xAdapter = require('./adapters/x');
const facebookAdapter = require('./adapters/facebook');
const { isDryRun, logDryRunPost } = require('./utils/dryRun');
const { localHour } = require('./utils/localTime');
const {
  prepareXCreateContent, recordXCreate, xBudgetBlocksPlatform, contentCountsAsUrl,
} = require('./utils/xBudget');
const {
  isFacebookAuthError, isFacebookPostingPaused, pauseFacebookPosting, clearFacebookPostingPause,
} = require('./utils/platformPause');

const ADAPTERS = {
  discord: discordAdapter,
  x: xAdapter,
  facebook: facebookAdapter,
};

function isQuietHours() {
  const quiet = getSetting('quiet_hours', { enabled: true, start: 23, end: 7 });
  if (!quiet.enabled) return false;
  const hour = localHour();
  const { start, end } = quiet;
  if (start > end) return hour >= start || hour < end;
  return hour >= start && hour < end;
}

function shouldPostNow(platform) {
  const settings = getSetting('posting_schedule', {
    min_hours: 3,
    max_hours: 6,
    post_probability: 0.25,
  });
  const maxDaily = getSetting('max_posts_per_day', 5);
  if (countPostsToday(platform) >= maxDaily) return false;
  if (platform === 'x' && xBudgetBlocksPlatform('x')) {
    logger.info('X post skipped — daily API budget exhausted');
    return false;
  }
  if (isQuietHours()) return false;

  const db = getDb();
  const account = db.prepare('SELECT last_post_at FROM social_platform_accounts WHERE platform = ?').get(platform);
  if (account?.last_post_at) {
    const elapsed = Date.now() - new Date(account.last_post_at).getTime();
    const minMs = (settings.min_hours || 3) * 3600000;
    if (elapsed < minMs) return false;
    const maxMs = (settings.max_hours || 6) * 3600000;
    if (elapsed >= maxMs) return true;
  }
  return Math.random() < (settings.post_probability || 0.25);
}

async function publishPost(postId, options = {}) {
  const { forceLive = false } = options;
  const db = getDb();
  const post = db.prepare('SELECT * FROM social_posts WHERE id = ?').get(postId);
  if (!post) throw new Error(`Post ${postId} not found`);

  if (isDryRun(forceLive)) {
    logDryRunPost(post.platform, post.content, { postId, topic: post.topic });
    db.prepare(`
      UPDATE social_posts
      SET status = 'dry_run', external_id = 'dry-run', published_at = datetime('now'), error_message = NULL
      WHERE id = ?
    `).run(postId);
    auditLog('post_dry_run', { postId, platform: post.platform }, 'system');
    return { id: 'dry-run', platform: post.platform, dryRun: true };
  }

  const adapter = ADAPTERS[post.platform];
  if (!adapter) throw new Error(`No adapter for ${post.platform}`);

  try {
    let content = post.content;
    if (post.platform === 'x') {
      const prepared = prepareXCreateContent({ type: 'post', content: post.content });
      if (!prepared.allowed) {
        throw new Error(`X daily budget limit: ${prepared.reason}`);
      }
      content = prepared.content;
    }

    const result = await adapter.post(content);
    db.prepare(`
      UPDATE social_posts
      SET status = 'published', external_id = ?, published_at = datetime('now'), error_message = NULL, content = ?
      WHERE id = ?
    `).run(result.id, content, postId);

    if (post.platform === 'x') {
      recordXCreate({
        type: 'post',
        withUrl: contentCountsAsUrl(content),
        referenceId: result.id,
      });
    }
    db.prepare(`
      UPDATE social_platform_accounts SET last_post_at = datetime('now'), updated_at = datetime('now')
      WHERE platform = ?
    `).run(post.platform);
    auditLog('post_published', { postId, platform: post.platform, externalId: result.id }, 'system');
    logger.info('Post published', { postId, platform: post.platform });
    if (post.platform === 'facebook') clearFacebookPostingPause();
    return result;
  } catch (err) {
    const detail = err.response?.data?.error?.message || err.response?.data?.detail || err.response?.data?.title || err.message;
    db.prepare(`UPDATE social_posts SET status = 'failed', error_message = ? WHERE id = ?`).run(detail, postId);
    if (post.platform === 'facebook' && isFacebookAuthError(detail)) {
      pauseFacebookPosting(detail);
    }
    logger.error('Post publish failed', { postId, error: detail });
    throw err;
  }
}

async function queuePost(options) {
  const {
    platform, content, topic, tone, source = 'scheduler', eventId = null, requiresApproval = null, forceLive = false,
  } = options;

  const autoMode = getSetting('auto_post_mode', 'approval');
  const dryRun = isDryRun(forceLive);
  const needsApproval = dryRun ? false : (requiresApproval ?? (autoMode === 'approval'));

  const db = getDb();
  const result = db.prepare(`
    INSERT INTO social_posts
      (platform, content, topic, tone, status, source, event_id, requires_approval, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
  `).run(
    platform,
    content,
    topic,
    tone,
    needsApproval ? 'pending' : (dryRun ? 'approved' : 'approved'),
    source,
    eventId,
    needsApproval ? 1 : 0
  );

  const postId = result.lastInsertRowid;

  if (needsApproval) {
    auditLog('post_queued', { postId, platform, topic }, 'system');
    logger.info('Post queued for approval', { postId, platform });
  } else {
    await publishPost(postId, { forceLive });
  }

  return postId;
}

async function createPostsForPlatforms(options = {}) {
  const {
    source = 'scheduler',
    eventId = null,
    forceTopic = null,
    dataContext = null,
    forceLive = false,
    eventType = null,
  } = options;
  const ctx = dataContext || await gatherDataContext();
  const platforms = ['discord', 'x', 'facebook'].filter((p) => isPlatformEnabled(p));
  const deprioritizedTopics = buildDeprioritizedTopics();

  if (eventType === 'block_found' && !shouldAnnounceBlockFound(ctx.height)) {
    logger.info('Skipping block_found posts — rate limit active', { height: ctx.height });
    return;
  }

  for (const platform of platforms) {
    if (platform === 'facebook' && isFacebookPostingPaused()) {
      logger.info('Skipping Facebook post — token invalid, reconnect in admin');
      continue;
    }
    if (source === 'scheduler' && !shouldPostNow(platform)) continue;
    const lastTopic = getLastPostTopic(platform);
    if (forceTopic && lastTopic === forceTopic && source === 'scheduler') continue;

    try {
      let generated = await generatePost({
        platform,
        forceTopic,
        dataContext: ctx,
        excludeTopics: source === 'scheduler' ? deprioritizedTopics : [],
      });
      if (lastTopic === generated.topic && source === 'scheduler') {
        generated = await generatePost({
          platform,
          dataContext: ctx,
          excludeTopics: [...deprioritizedTopics, generated.topic],
        });
      }
      if (source === 'scheduler' && deprioritizedTopics.includes(generated.topic)) {
        generated = await generatePost({
          platform,
          dataContext: ctx,
          excludeTopics: [...deprioritizedTopics, generated.topic],
        });
      }
      await queuePost({
        platform,
        content: generated.content,
        topic: generated.topic,
        tone: generated.tone,
        source,
        eventId,
        forceLive,
      });
    } catch (err) {
      logger.error('Post creation failed', { platform, error: err.message });
    }
  }
}

async function runSchedulerTick() {
  logger.debug('Scheduler tick', { dryRun: isDryRun() });
  try {
    await detectEvents();
    await processPendingEvents(createPostsForPlatforms);
    await createPostsForPlatforms({ source: 'scheduler' });
  } catch (err) {
    logger.error('Scheduler tick failed', { error: err.message, stack: err.stack });
  }
}

function startScheduler() {
  const cronExpr = getSetting('scheduler_cron', '*/15 * * * *');
  logger.info('Starting scheduler', { cron: cronExpr, dryRun: isDryRun() });
  cron.schedule(cronExpr, runSchedulerTick);
  setTimeout(runSchedulerTick, 30000);
}

module.exports = {
  startScheduler,
  runSchedulerTick,
  queuePost,
  publishPost,
  shouldPostNow,
  isQuietHours,
  createPostsForPlatforms,
};
