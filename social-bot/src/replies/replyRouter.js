const { getDb, getSetting, auditLog } = require('../db');
const logger = require('../logger');
const { resolveReply } = require('./resolveReply');
const { formatReplyForPlatform, hasLinks } = require('./formatReply');
const {
  prepareXCreateContent, recordXCreate, contentCountsAsUrl,
} = require('../utils/xBudget');
const { gatherDataContext } = require('../events/eventProcessor');
const discordAdapter = require('../adapters/discord');
const xAdapter = require('../adapters/x');
const facebookAdapter = require('../adapters/facebook');

const REPLY_COOLDOWN_MS = 60000;
const recentReplies = new Map();

function isRateLimited(key) {
  const last = recentReplies.get(key);
  if (last && Date.now() - last < REPLY_COOLDOWN_MS) return true;
  return false;
}

function markReplied(key) {
  recentReplies.set(key, Date.now());
}

function alreadyHandled(platform, messageId) {
  if (!messageId) return false;
  const row = getDb().prepare(
    'SELECT id FROM social_reply_log WHERE platform = ? AND message_id = ? LIMIT 1'
  ).get(platform, messageId);
  return !!row;
}

async function handleIncomingMessage(msg) {
  let { platform, channelId, messageId, authorId, authorName, text } = msg;

  if (platform === 'x' && messageId) {
    const tweet = await xAdapter.getTweetById(messageId);
    if (!tweet?.text) {
      logger.warn('X reply skipped — could not load tweet text', { messageId });
      return;
    }
    const botUserId = await xAdapter.getBotUserId();
    if (botUserId && tweet.authorId === botUserId) {
      logger.info('X reply skipped — will not reply to own tweet', { messageId });
      return;
    }
    text = xAdapter.normalizeMentionText(tweet.text);
    authorId = tweet.authorId || authorId;
    authorName = tweet.authorName || authorName;
    logger.info('X reply to tweet', { messageId, preview: text.slice(0, 120) });
  }

  if (!text || text.trim().length < 2) return;
  if (alreadyHandled(platform, messageId)) {
    logger.debug('Reply skipped — already handled', { platform, messageId });
    return;
  }

  const repliesEnabled = getSetting('replies_enabled', true);
  if (!repliesEnabled) return;

  const cooldownKey = `${platform}:${authorId || authorName}`;
  if (isRateLimited(cooldownKey)) {
    logger.debug('Reply rate limited', { cooldownKey });
    return;
  }

  let dataContext = null;
  try {
    dataContext = await gatherDataContext();
  } catch (err) {
    logger.warn('Reply context load failed', { error: err.message });
  }

  const { intent, confidence, reply, source } = await resolveReply(text, {
    platform,
    authorName,
    dataContext,
  });

  const requireApproval = getSetting('require_reply_approval', false);
  const minConfidence = getSetting('reply_min_confidence', 0.6);

  const db = getDb();
  const result = db.prepare(`
    INSERT INTO social_reply_log
      (platform, channel_id, message_id, author_id, author_name, incoming_text,
       reply_text, intent, confidence, status, requires_approval, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
  `).run(
    platform,
    channelId || null,
    messageId || null,
    authorId || null,
    authorName || null,
    text,
    reply,
    source === 'ai' ? intent : `${intent}:${source}`,
    confidence,
    requireApproval || confidence < minConfidence ? 'pending' : 'approved',
    requireApproval || confidence < minConfidence ? 1 : 0
  );

  if (requireApproval || confidence < minConfidence) {
    logger.info('Reply queued for approval', { id: result.lastInsertRowid, intent, source });
    auditLog('reply_queued', { id: result.lastInsertRowid, intent, platform, source }, 'system');
    return;
  }

  await sendReply(result.lastInsertRowid, platform, channelId, messageId, reply, text);
  markReplied(cooldownKey);
}

async function sendReply(logId, platform, channelId, messageId, replyText, incomingText = '') {
  const db = getDb();
  let outbound = formatReplyForPlatform(replyText, platform);

  if (platform === 'x') {
    if (!incomingText) {
      const row = db.prepare('SELECT incoming_text FROM social_reply_log WHERE id = ?').get(logId);
      incomingText = row?.incoming_text || '';
    }
    const prepared = prepareXCreateContent({
      type: 'reply',
      content: outbound,
      incomingText,
    });
    if (!prepared.allowed) {
      db.prepare(`UPDATE social_reply_log SET status = 'failed' WHERE id = ?`).run(logId);
      logger.warn('X reply blocked by budget', { logId, reason: prepared.reason });
      return;
    }
    outbound = prepared.content;
  }

  if (platform === 'x' && hasLinks(outbound)) {
    logger.debug('X reply includes URL (budgeted)', { logId });
  }
  try {
    let external;
    if (platform === 'discord') {
      external = await discordAdapter.reply(channelId, messageId, outbound);
    } else if (platform === 'x') {
      external = await xAdapter.reply(messageId, outbound);
    } else if (platform === 'facebook') {
      external = await facebookAdapter.reply(messageId, outbound);
    } else {
      throw new Error(`Unknown platform: ${platform}`);
    }

    db.prepare(`
      UPDATE social_reply_log
      SET status = 'sent', external_reply_id = ?, replied_at = datetime('now'), reply_text = ?
      WHERE id = ?
    `).run(external.id, outbound, logId);

    if (platform === 'x') {
      recordXCreate({
        type: 'reply',
        withUrl: contentCountsAsUrl(outbound),
        referenceId: external.id,
      });
    }

    auditLog('reply_sent', { logId, platform, externalId: external.id }, 'system');
    logger.info('Reply sent', { logId, platform });
  } catch (err) {
    db.prepare(`
      UPDATE social_reply_log SET status = 'failed', reply_text = reply_text || ' [ERR: ' || ? || ']'
      WHERE id = ?
    `).run(err.message, logId);
    logger.error('Reply failed', { logId, error: err.message });
  }
}

async function approveReply(logId, actor = 'admin') {
  const db = getDb();
  const row = db.prepare('SELECT * FROM social_reply_log WHERE id = ?').get(logId);
  if (!row || row.status === 'sent') return false;

  await sendReply(logId, row.platform, row.channel_id, row.message_id, row.reply_text, row.incoming_text || '');
  auditLog('reply_approved', { logId }, actor);
  return true;
}

async function rejectReply(logId, actor = 'admin') {
  const db = getDb();
  db.prepare(`UPDATE social_reply_log SET status = 'rejected' WHERE id = ?`).run(logId);
  auditLog('reply_rejected', { logId }, actor);
  return true;
}

function startReplyListeners() {
  const stopFns = [];

  stopFns.push(
    discordAdapter.startDiscordListener(handleIncomingMessage)
  );
  stopFns.push(
    xAdapter.startXMentionListener(handleIncomingMessage)
  );
  stopFns.push(
    facebookAdapter.startFacebookCommentListener(handleIncomingMessage)
  );

  return () => stopFns.filter(Boolean).forEach((fn) => fn());
}

module.exports = {
  handleIncomingMessage,
  sendReply,
  approveReply,
  rejectReply,
  startReplyListeners,
  alreadyHandled,
};
