const Database = require('better-sqlite3');
const fs = require('fs');
const path = require('path');
const config = require('./config');
const logger = require('./logger');

let db = null;

function getDb() {
  if (db) return db;

  const dbPath = path.resolve(config.dbPath);
  const dir = path.dirname(dbPath);
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }

  db = new Database(dbPath);
  db.pragma('journal_mode = WAL');
  db.pragma('foreign_keys = ON');
  logger.info('SQLite database opened', { path: dbPath });
  return db;
}

function closeDb() {
  if (db) {
    db.close();
    db = null;
  }
}

function getSetting(key, fallback = null) {
  const row = getDb().prepare('SELECT value FROM social_settings WHERE key = ?').get(key);
  if (!row) return fallback;
  try {
    return JSON.parse(row.value);
  } catch {
    return row.value;
  }
}

function setSetting(key, value, updatedBy = 'system') {
  const json = typeof value === 'string' ? value : JSON.stringify(value);
  getDb().prepare(`
    INSERT INTO social_settings (key, value, updated_at, updated_by)
    VALUES (?, ?, datetime('now'), ?)
    ON CONFLICT(key) DO UPDATE SET
      value = excluded.value,
      updated_at = excluded.updated_at,
      updated_by = excluded.updated_by
  `).run(key, json, updatedBy);
}

function auditLog(action, details = {}, actor = 'system') {
  getDb().prepare(`
    INSERT INTO social_audit_log (action, details, actor, created_at)
    VALUES (?, ?, ?, datetime('now'))
  `).run(action, JSON.stringify(details), actor);
}

function getPlatformAccount(platform) {
  return getDb().prepare('SELECT * FROM social_platform_accounts WHERE platform = ?').get(platform);
}

function isPlatformEnabled(platform) {
  const account = getPlatformAccount(platform);
  if (!account || account.enabled !== 1) return false;
  const creds = getSetting('platform_credentials', {});
  if (creds[platform] && creds[platform].enabled === false) return false;
  return true;
}

function getEnabledPlatforms() {
  return getDb().prepare('SELECT * FROM social_platform_accounts WHERE enabled = 1').all();
}

const { localDayUtcBounds } = require('./utils/localTime');

function countPostsToday(platform) {
  const { startUtc, endUtc } = localDayUtcBounds();
  const row = getDb().prepare(`
    SELECT COUNT(*) AS cnt FROM social_posts
    WHERE platform = ?
      AND status = 'published'
      AND published_at >= ?
      AND published_at < ?
  `).get(platform, startUtc, endUtc);
  return row?.cnt || 0;
}

function getLastPostTopic(platform) {
  const row = getDb().prepare(`
    SELECT topic FROM social_posts
    WHERE platform = ? AND status IN ('published', 'approved', 'pending')
    ORDER BY COALESCE(published_at, created_at) DESC
    LIMIT 1
  `).get(platform);
  return row?.topic || null;
}

function getRecentPostContents(limit = 50) {
  return getDb().prepare(`
    SELECT content, topic, platform FROM social_posts
    WHERE status = 'published'
    ORDER BY published_at DESC
    LIMIT ?
  `).all(limit);
}

function getLastPublishedAtForTopic(topic) {
  const row = getDb().prepare(`
    SELECT published_at FROM social_posts
    WHERE status = 'published' AND topic = ?
    ORDER BY published_at DESC
    LIMIT 1
  `).get(topic);
  return row?.published_at ? new Date(`${row.published_at}Z`) : null;
}

function hoursSinceLastTopicPost(topic) {
  const last = getLastPublishedAtForTopic(topic);
  if (!last) return Infinity;
  return (Date.now() - last.getTime()) / 3600000;
}

function isOnContentCooldown(contentKey) {
  const row = getDb().prepare(`
    SELECT expires_at FROM social_content_cooldowns
    WHERE content_key = ? AND expires_at > datetime('now')
  `).get(contentKey);
  return !!row;
}

function setContentCooldown(contentKey, hours = 24) {
  getDb().prepare(`
    INSERT INTO social_content_cooldowns (content_key, expires_at, created_at)
    VALUES (?, datetime('now', ?), datetime('now'))
    ON CONFLICT(content_key) DO UPDATE SET
      expires_at = excluded.expires_at
  `).run(contentKey, `+${hours} hours`);
}

function recordMilestone(type, value) {
  const existing = getDb().prepare(`
    SELECT id FROM social_milestones WHERE milestone_type = ? AND milestone_value = ?
  `).get(type, String(value));
  if (existing) return false;

  getDb().prepare(`
    INSERT INTO social_milestones (milestone_type, milestone_value, reached_at, posted)
    VALUES (?, ?, datetime('now'), 0)
  `).run(type, String(value));
  return true;
}

function getUnpostedMilestones() {
  return getDb().prepare(`
    SELECT * FROM social_milestones WHERE posted = 0 ORDER BY reached_at ASC
  `).all();
}

function markMilestonePosted(id) {
  getDb().prepare('UPDATE social_milestones SET posted = 1 WHERE id = ?').run(id);
}

module.exports = {
  getDb,
  closeDb,
  getSetting,
  setSetting,
  auditLog,
  getPlatformAccount,
  isPlatformEnabled,
  getEnabledPlatforms,
  countPostsToday,
  getLastPostTopic,
  getRecentPostContents,
  getLastPublishedAtForTopic,
  hoursSinceLastTopicPost,
  isOnContentCooldown,
  setContentCooldown,
  recordMilestone,
  getUnpostedMilestones,
  markMilestonePosted,
};
