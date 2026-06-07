const express = require('express');
const path = require('path');
const {
  getDb, getSetting, setSetting, auditLog, getEnabledPlatforms,
} = require('../db');
const { requireAuth, csrfProtection, attemptLogin, generateCsrfToken } = require('./auth');
const { generatePost } = require('../generator/postGenerator');
const { gatherDataContext } = require('../events/eventProcessor');
const { publishPost, runSchedulerTick } = require('../scheduler');
const { approveReply, rejectReply } = require('../replies/replyRouter');
const { DEFAULT_TEMPLATES } = require('../generator/templates');
const config = require('../config');
const logger = require('../logger');

function createAdminRouter() {
  const router = express.Router();

  router.get('/login', (req, res) => {
    if (req.session?.authenticated) return res.redirect('/admin/');
    generateCsrfToken(req);
    res.render('login', { error: null });
  });

  router.post('/login', csrfProtection, (req, res) => {
    const { username, password } = req.body;
    if (attemptLogin(username, password)) {
      req.session.authenticated = true;
      req.session.adminUser = username;
      auditLog('admin_login', {}, username);
      return res.redirect('/admin/');
    }
    auditLog('admin_login_failed', { username }, 'anonymous');
    res.render('login', { error: 'Invalid credentials' });
  });

  router.post('/logout', requireAuth, csrfProtection, (req, res) => {
    auditLog('admin_logout', {}, req.session.adminUser);
    req.session.destroy(() => res.redirect('/admin/login'));
  });

  router.use(requireAuth);

  router.get('/', (req, res) => {
    const db = getDb();
    const stats = {
      pendingPosts: db.prepare("SELECT COUNT(*) AS c FROM social_posts WHERE status = 'pending'").get().c,
      publishedToday: db.prepare("SELECT COUNT(*) AS c FROM social_posts WHERE status = 'published' AND date(published_at) = date('now')").get().c,
      pendingReplies: db.prepare("SELECT COUNT(*) AS c FROM social_reply_log WHERE status = 'pending'").get().c,
      unprocessedEvents: db.prepare('SELECT COUNT(*) AS c FROM social_events WHERE processed = 0').get().c,
      platforms: getEnabledPlatforms(),
    };
    res.render('dashboard', { stats, config });
  });

  router.get('/platforms', (req, res) => {
    const db = getDb();
    const accounts = db.prepare('SELECT * FROM social_platform_accounts ORDER BY platform').all();
    res.render('platforms', { accounts, config, envInstructions: getEnvInstructions() });
  });

  router.post('/platforms/:platform', csrfProtection, (req, res) => {
    const { platform } = req.params;
    const enabled = req.body.enabled === '1' ? 1 : 0;
    getDb().prepare(`
      UPDATE social_platform_accounts SET enabled = ?, updated_at = datetime('now') WHERE platform = ?
    `).run(enabled, platform);
    auditLog('platform_toggle', { platform, enabled }, req.session.adminUser);
    res.redirect('/admin/platforms');
  });

  router.get('/templates', (req, res) => {
    const templates = getDb().prepare('SELECT * FROM social_templates ORDER BY tone, name').all();
    res.render('templates', { templates });
  });

  router.post('/templates/:id', csrfProtection, (req, res) => {
    const { id } = req.params;
    const { template_body, enabled, weight } = req.body;
    getDb().prepare(`
      UPDATE social_templates
      SET template_body = ?, enabled = ?, weight = ?, updated_at = datetime('now')
      WHERE id = ?
    `).run(template_body, enabled === '1' ? 1 : 0, parseInt(weight, 10) || 1, id);
    auditLog('template_updated', { id }, req.session.adminUser);
    res.redirect('/admin/templates');
  });

  router.post('/templates', csrfProtection, (req, res) => {
    const { name, tone, topic, template_body, weight } = req.body;
    getDb().prepare(`
      INSERT INTO social_templates (name, tone, topic, template_body, weight, enabled)
      VALUES (?, ?, ?, ?, ?, 1)
    `).run(name, tone, topic, template_body, parseInt(weight, 10) || 1);
    auditLog('template_created', { name }, req.session.adminUser);
    res.redirect('/admin/templates');
  });

  router.get('/preview', async (req, res) => {
    let preview = null;
    let error = null;
    if (req.query.generate === '1') {
      try {
        const ctx = await gatherDataContext();
        preview = await generatePost({
          platform: req.query.platform || 'discord',
          forceTopic: req.query.topic || null,
          dataContext: ctx,
        });
      } catch (err) {
        error = err.message;
      }
    }
    res.render('preview', { preview, error, platforms: ['discord', 'x', 'facebook'] });
  });

  router.get('/queue', (req, res) => {
    const posts = getDb().prepare(`
      SELECT * FROM social_posts WHERE status IN ('pending', 'approved')
      ORDER BY created_at DESC LIMIT 50
    `).all();
    res.render('queue', { posts });
  });

  router.post('/queue/:id/approve', csrfProtection, async (req, res) => {
    const { id } = req.params;
    const db = getDb();
    db.prepare(`
      UPDATE social_posts SET status = 'approved', approved_at = datetime('now'), approved_by = ?
      WHERE id = ?
    `).run(req.session.adminUser, id);
    try {
      await publishPost(parseInt(id, 10));
    } catch (err) {
      logger.error('Manual approve publish failed', { id, error: err.message });
    }
    auditLog('post_approved', { id }, req.session.adminUser);
    res.redirect('/admin/queue');
  });

  router.post('/queue/:id/reject', csrfProtection, (req, res) => {
    const { id } = req.params;
    getDb().prepare(`UPDATE social_posts SET status = 'rejected' WHERE id = ?`).run(id);
    auditLog('post_rejected', { id }, req.session.adminUser);
    res.redirect('/admin/queue');
  });

  router.get('/history', (req, res) => {
    const posts = getDb().prepare(`
      SELECT * FROM social_posts ORDER BY COALESCE(published_at, created_at) DESC LIMIT 100
    `).all();
    res.render('history', { posts });
  });

  router.get('/replies', (req, res) => {
    const replies = getDb().prepare(`
      SELECT * FROM social_reply_log ORDER BY created_at DESC LIMIT 100
    `).all();
    res.render('replies', { replies });
  });

  router.post('/replies/:id/approve', csrfProtection, async (req, res) => {
    await approveReply(parseInt(req.params.id, 10), req.session.adminUser);
    res.redirect('/admin/replies');
  });

  router.post('/replies/:id/reject', csrfProtection, async (req, res) => {
    await rejectReply(parseInt(req.params.id, 10), req.session.adminUser);
    res.redirect('/admin/replies');
  });

  router.get('/settings', (req, res) => {
    const settings = {
      posting_schedule: getSetting('posting_schedule'),
      quiet_hours: getSetting('quiet_hours'),
      max_posts_per_day: getSetting('max_posts_per_day'),
      auto_post_mode: getSetting('auto_post_mode'),
      replies_enabled: getSetting('replies_enabled'),
      require_reply_approval: getSetting('require_reply_approval'),
      reply_min_confidence: getSetting('reply_min_confidence'),
      scheduler_cron: getSetting('scheduler_cron'),
    };
    res.render('settings', { settings });
  });

  router.post('/settings', csrfProtection, (req, res) => {
    const actor = req.session.adminUser;
    setSetting('posting_schedule', {
      min_hours: parseFloat(req.body.min_hours) || 3,
      max_hours: parseFloat(req.body.max_hours) || 6,
      post_probability: parseFloat(req.body.post_probability) || 0.25,
      check_interval_minutes: 15,
    }, actor);
    setSetting('quiet_hours', {
      enabled: req.body.quiet_enabled === '1',
      start: parseInt(req.body.quiet_start, 10) || 23,
      end: parseInt(req.body.quiet_end, 10) || 7,
    }, actor);
    setSetting('max_posts_per_day', parseInt(req.body.max_posts_per_day, 10) || 5, actor);
    setSetting('auto_post_mode', req.body.auto_post_mode || 'approval', actor);
    setSetting('replies_enabled', req.body.replies_enabled === '1', actor);
    setSetting('require_reply_approval', req.body.require_reply_approval === '1', actor);
    setSetting('reply_min_confidence', parseFloat(req.body.reply_min_confidence) || 0.6, actor);
    auditLog('settings_updated', {}, actor);
    res.redirect('/admin/settings');
  });

  router.post('/run-scheduler', csrfProtection, async (req, res) => {
    await runSchedulerTick();
    auditLog('scheduler_manual_run', {}, req.session.adminUser);
    res.redirect('/admin/');
  });

  router.get('/audit', (req, res) => {
    const logs = getDb().prepare(`
      SELECT * FROM social_audit_log ORDER BY created_at DESC LIMIT 200
    `).all();
    res.render('audit', { logs });
  });

  return router;
}

function getEnvInstructions() {
  return [
    { platform: 'Discord', vars: ['DISCORD_ENABLED', 'DISCORD_BOT_TOKEN', 'DISCORD_WEBHOOK_URL', 'DISCORD_CHANNEL_ID', 'DISCORD_REPLY_CHANNEL_IDS'] },
    { platform: 'X (Twitter)', vars: ['X_ENABLED', 'X_API_KEY', 'X_API_SECRET', 'X_ACCESS_TOKEN', 'X_ACCESS_SECRET', 'X_BEARER_TOKEN', 'X_REPLY_ENABLED'] },
    { platform: 'Facebook', vars: ['FACEBOOK_ENABLED', 'FACEBOOK_PAGE_ID', 'FACEBOOK_PAGE_ACCESS_TOKEN', 'FACEBOOK_REPLY_ENABLED'] },
    { platform: 'AI (optional)', vars: ['AI_ENABLED', 'AI_API_KEY', 'AI_BASE_URL', 'AI_MODEL'] },
    { platform: 'Admin', vars: ['ADMIN_USERNAME', 'ADMIN_PASSWORD', 'SESSION_SECRET'] },
  ];
}

module.exports = { createAdminRouter };
