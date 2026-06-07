const { collectBlockData, checkBlockMilestones } = require('../collectors/blocks');
const {
  collectMinerData, checkMinerMilestones, checkHashrateMilestone, checkDifficultyMilestone,
} = require('../collectors/miners');
const { collectSiteStats, checkVisitMilestones } = require('../collectors/siteStats');
const {
  collectDownloadData, collectContentUpdates, detectNewReleases,
  detectContentChanges, loadContentState, saveContentState,
} = require('../collectors/downloads');
const {
  getDb, getSetting, recordMilestone, setContentCooldown, isOnContentCooldown,
  hoursSinceLastTopicPost,
} = require('../db');
const logger = require('../logger');

const EVENT_TOPIC_MAP = {
  block_found: 'block_found',
  best_share: 'hashrate',
  block_height: 'block_milestone',
  site_visits: 'traffic_milestone',
  download_release: 'download_release',
  wallet_release: 'download_release',
  node_release: 'node_setup',
  docs_update: 'docs_update',
  announcement: 'mining_general',
  pdf_update: 'docs_update',
  pool_status: 'pool_status',
  miner_count: 'miner_milestone',
  difficulty: 'chain_status',
  hashrate_gh: 'hashrate',
  top_miner: 'pool_status',
};

let lastBestShare = null;

const HEIGHT_TOPICS = new Set(['block_found', 'block_milestone', 'chain_status']);

function getBlockFoundPostSettings() {
  return getSetting('block_found_posts', {
    min_hours_between: 8,
    milestone_modulo: 50,
    max_per_day: 2,
  });
}

function isBlockHeightMilestone(height, modulo = 50) {
  const h = Number(height);
  return modulo > 0 && h > 0 && h % modulo === 0;
}

function shouldAnnounceBlockFound(height) {
  const cfg = getBlockFoundPostSettings();
  const h = Number(height);
  if (!h) return false;

  if (isOnContentCooldown(`block_found_height_${h}`)) {
    return false;
  }

  if (isBlockHeightMilestone(h, cfg.milestone_modulo)) {
    return true;
  }

  if (isOnContentCooldown('block_found_announce')) {
    return false;
  }

  const minHours = cfg.min_hours_between || 8;
  if (hoursSinceLastTopicPost('block_found') < minHours) {
    return false;
  }

  return true;
}

function markBlockFoundAnnounced(height) {
  const cfg = getBlockFoundPostSettings();
  const h = Number(height);
  setContentCooldown(`block_found_height_${h}`, 168);
  if (!isBlockHeightMilestone(h, cfg.milestone_modulo)) {
    setContentCooldown('block_found_announce', cfg.min_hours_between || 8);
  }
}

async function gatherDataContext() {
  const [blocks, miners, siteStats, downloads, content] = await Promise.all([
    collectBlockData(),
    collectMinerData(),
    collectSiteStats(),
    collectDownloadData(),
    collectContentUpdates(),
  ]);

  return {
    height: blocks.height,
    difficulty: blocks.difficulty,
    sync_status: blocks.syncStatus || 'synced',
    miners: miners.totalMiners || miners.main.miners,
    workers: miners.totalWorkers || miners.main.workers,
    hashrate: miners.main.hashrate,
    pool: 'main',
    visits: siteStats.humanPageviews,
    daily_visits: siteStats.dailyPageviews,
    reward: blocks.blockReward || 45,
    best_share: miners.main.bestShare,
    block_hash: blocks.bestBlockHash,
    top_miner: miners.main.topMiner?.workerLabel || miners.topMinerUpdate?.workerLabel,
    doc_title: content.docs[0]?.title,
    release_version: downloads.latest[0]?.version,
    release_platform: downloads.latest[0]?.platform,
  };
}

async function detectEvents() {
  const db = getDb();
  const events = [];
  const contentState = loadContentState();

  const [blocks, miners, siteStats, downloads, content] = await Promise.all([
    collectBlockData(),
    collectMinerData(),
    collectSiteStats(),
    collectDownloadData(),
    collectContentUpdates(),
  ]);

  for (const m of checkBlockMilestones(blocks.height, recordMilestone)) {
    events.push({ type: m.type, payload: { height: m.value } });
  }

  for (const m of checkMinerMilestones(miners.totalMiners, recordMilestone)) {
    events.push({ type: m.type, payload: { miners: m.value } });
  }

  for (const m of checkHashrateMilestone(miners.main.hashrate, recordMilestone)) {
    events.push({ type: m.type, payload: { hashrate: miners.main.hashrate } });
  }

  for (const m of checkDifficultyMilestone(blocks.difficulty, recordMilestone)) {
    events.push({ type: m.type, payload: { difficulty: m.value } });
  }

  for (const m of checkVisitMilestones(siteStats.humanPageviews, recordMilestone)) {
    events.push({ type: m.type, payload: { visits: m.value } });
  }

  const bestShare = miners.main.bestShare || miners.bestShareEver;
  if (bestShare && lastBestShare !== null && Number(bestShare) > Number(lastBestShare)) {
    const key = `best_share_${bestShare}`;
    if (!isOnContentCooldown(key)) {
      events.push({ type: 'best_share', payload: { bestShare, pool: 'main' } });
      setContentCooldown(key, 6);
    }
  }
  if (bestShare) lastBestShare = bestShare;

  if (blocks.newPoolBlock) {
    const b = blocks.newPoolBlock;
    if (shouldAnnounceBlockFound(b.height)) {
      events.push({
        type: 'block_found',
        payload: {
          height: b.height,
          hash: b.hash,
          workerLabel: b.workerLabel,
          reward: b.reward,
          pool: b.pool,
        },
      });
      markBlockFoundAnnounced(b.height);
    } else {
      logger.debug('block_found skipped — rate limit or duplicate height', { height: b.height });
    }
  }

  const newReleases = detectNewReleases(downloads.latest, contentState.lastDownloadVersions || {});
  for (const rel of newReleases) {
    const type = rel.kind === 'node' ? 'node_release' : rel.kind === 'wallet' ? 'wallet_release' : 'download_release';
    events.push({
      type,
      payload: { platform: rel.platform, version: rel.version, title: rel.title },
    });
    contentState.lastDownloadVersions[`${rel.platform}-${rel.version}`] = rel.updated_at || true;
  }
  for (const dl of downloads.latest) {
    contentState.lastDownloadVersions[`${dl.platform}-${dl.version}`] = dl.updated_at || true;
  }

  const contentChanges = detectContentChanges(content, contentState);
  events.push(...contentChanges.events);
  Object.assign(contentState, contentChanges.state);

  if (miners.topMinerUpdate) {
    const key = `top_miner_${miners.topMinerUpdate.workerLabel}`;
    if (!isOnContentCooldown(key)) {
      events.push({
        type: 'top_miner',
        payload: { workerLabel: miners.topMinerUpdate.workerLabel, pool: 'main' },
      });
      setContentCooldown(key, 12);
    }
  }

  const poolKey = 'pool_status_update';
  if (!isOnContentCooldown(poolKey)) {
    events.push({
      type: 'pool_status',
      payload: {
        miners: miners.totalMiners,
        workers: miners.totalWorkers,
        hashrate: miners.main.hashrate,
        mainMiners: miners.main.miners,
        nanoMiners: miners.nano.miners,
      },
    });
    setContentCooldown(poolKey, 24);
  }

  saveContentState(contentState);

  for (const ev of events) {
    db.prepare(`
      INSERT INTO social_events (event_type, payload, processed, created_at)
      VALUES (?, ?, 0, datetime('now'))
    `).run(ev.type, JSON.stringify(ev.payload));
  }

  if (events.length > 0) {
    logger.info('Events detected', { count: events.length, types: events.map((e) => e.type) });
  }

  return events;
}

function getTopicForEvent(eventType) {
  return EVENT_TOPIC_MAP[eventType] || 'mining_general';
}

async function processPendingEvents(createPostsFn) {
  const db = getDb();
  const pending = db.prepare(`
    SELECT * FROM social_events WHERE processed = 0 ORDER BY created_at ASC LIMIT 10
  `).all();

  for (const event of pending) {
    try {
      const payload = JSON.parse(event.payload);
      const topic = getTopicForEvent(event.event_type);
      const dataContext = await gatherDataContext();
      Object.assign(dataContext, payload);

      if (event.event_type === 'block_found' && !shouldAnnounceBlockFound(payload.height ?? dataContext.height)) {
        logger.info('Skipping stale block_found event — already announced or rate limited', {
          eventId: event.id,
          height: payload.height ?? dataContext.height,
        });
      } else {
        await createPostsFn({
          source: 'event',
          eventId: event.id,
          forceTopic: topic,
          dataContext,
          eventType: event.event_type,
        });
      }

      db.prepare(`
        UPDATE social_events SET processed = 1, processed_at = datetime('now') WHERE id = ?
      `).run(event.id);
    } catch (err) {
      logger.error('Event processing failed', { eventId: event.id, error: err.message });
    }
  }
}

module.exports = {
  gatherDataContext,
  detectEvents,
  processPendingEvents,
  getTopicForEvent,
  shouldAnnounceBlockFound,
  HEIGHT_TOPICS,
};
