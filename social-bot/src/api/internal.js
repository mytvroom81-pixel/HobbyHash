const express = require('express');
const config = require('../config');
const logger = require('../logger');
const { auditLog } = require('../db');
const { publishPost, runSchedulerTick } = require('../scheduler');
const { approveReply } = require('../replies/replyRouter');
const { generatePost } = require('../generator/postGenerator');
const { gatherDataContext } = require('../events/eventProcessor');
const { collectBlockData } = require('../collectors/blocks');
const { collectMinerData } = require('../collectors/miners');
const { collectSiteStats } = require('../collectors/siteStats');
const { collectDownloadData, collectContentUpdates } = require('../collectors/downloads');
const { isDryRun } = require('../utils/dryRun');

function internalAuth(req, res, next) {
  const token = config.internalToken;
  if (!token) {
    return res.status(503).json({ error: 'Internal API disabled — set SOCIAL_BOT_INTERNAL_TOKEN' });
  }

  const provided = req.headers['x-social-bot-token'] || '';
  if (provided !== token) {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  const ip = req.ip || req.connection?.remoteAddress || '';
  const local = ip === '127.0.0.1' || ip === '::1' || ip === '::ffff:127.0.0.1';
  if (!local && config.nodeEnv === 'production') {
    logger.warn('Internal API blocked non-local request', { ip });
    return res.status(403).json({ error: 'Forbidden' });
  }

  next();
}

function createInternalRouter() {
  const router = express.Router();
  router.use(internalAuth);

  router.post('/posts/:id/publish', async (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const actor = req.body?.actor || 'admin';
      const { getDb } = require('../db');
      const db = getDb();
      db.prepare(`
        UPDATE social_posts SET status = 'approved', approved_at = datetime('now'), approved_by = ?, error_message = NULL
        WHERE id = ? AND status IN ('pending', 'approved', 'failed')
      `).run(actor, id);
      const result = await publishPost(id, { forceLive: true });
      auditLog('post_published_via_php_admin', { postId: id }, actor);
      res.json({ ok: true, externalId: result.id, platform: result.platform });
    } catch (err) {
      logger.error('Internal publish failed', { error: err.message });
      res.status(500).json({ error: err.message });
    }
  });

  router.post('/preview', async (req, res) => {
    try {
      const ctx = await gatherDataContext();
      const preview = await generatePost({
        platform: req.body?.platform || 'discord',
        forceTopic: req.body?.topic || null,
        dataContext: ctx,
        forceAi: !!req.body?.useAi,
        skipSiteLink: req.body?.skipSiteLink !== false,
      });
      res.json({ ok: true, preview });
    } catch (err) {
      res.status(500).json({ error: err.message });
    }
  });

  router.post('/scheduler/run', async (req, res) => {
    try {
      const actor = req.body?.actor || 'admin';
      await runSchedulerTick();
      auditLog('scheduler_manual_run_via_php_admin', {}, actor);
      res.json({ ok: true, dryRun: isDryRun() });
    } catch (err) {
      res.status(500).json({ error: err.message });
    }
  });

  router.get('/x-budget', (req, res) => {
    try {
      const { getXBudgetSummary } = require('../utils/xBudget');
      res.json({ ok: true, summary: getXBudgetSummary() });
    } catch (err) {
      res.status(500).json({ error: err.message });
    }
  });

  router.get('/collectors', async (req, res) => {
    try {
      const [blocks, miners, site, downloads, content] = await Promise.all([
        collectBlockData(),
        collectMinerData(),
        collectSiteStats(),
        collectDownloadData(),
        collectContentUpdates(),
      ]);
      res.json({
        ok: true,
        dryRun: isDryRun(),
        blocks: {
          height: blocks.height,
          difficulty: blocks.difficulty,
          blockReward: blocks.blockReward,
          poolBlocks: blocks.poolBlocksFound?.length || 0,
          sources: blocks.sources,
        },
        miners: {
          totalMiners: miners.totalMiners,
          totalWorkers: miners.totalWorkers,
          hashrate: miners.main?.hashrate,
          bestShare: miners.main?.bestShare,
          sources: miners.sources,
        },
        site: {
          humanPageviews: site.humanPageviews,
          dailyPageviews: site.dailyPageviews,
          uniqueVisitors: site.uniqueVisitors,
          sources: site.sources,
        },
        downloads: {
          count: downloads.latest?.length || 0,
          sources: downloads.sources,
        },
        content: {
          docs: content.docs?.length || 0,
          announcements: content.announcements?.length || 0,
          pdfs: content.pdfUpdates?.map((p) => p.label) || [],
          sources: content.sources,
        },
      });
    } catch (err) {
      res.status(500).json({ error: err.message });
    }
  });

  router.post('/replies/:id/approve', async (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const actor = req.body?.actor || 'admin';
      const ok = await approveReply(id, actor);
      if (!ok) {
        return res.status(404).json({ error: 'Reply not found or already sent' });
      }
      res.json({ ok: true });
    } catch (err) {
      res.status(500).json({ error: err.message });
    }
  });

  return router;
}

module.exports = { createInternalRouter };
