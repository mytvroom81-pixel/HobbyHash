const { getSetting, setSetting, getDb, auditLog } = require('../db');
const logger = require('../logger');
const { stripLinks, hasLinks } = require('../replies/formatReply');
const { getSiteUrls } = require('../config/runtimeConfig');
const { localDateKey } = require('./localTime');

const DEFAULT_SETTINGS = {
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
};

const READ_KINDS = {
  owned_post: { logType: 'read_owned_post', counter: 'reads_owned' },
  public_post: { logType: 'read_public_post', counter: 'reads_public' },
  owned_user: { logType: 'read_owned_user', counter: 'reads_owned' },
  public_user: { logType: 'read_public_user', counter: 'reads_public' },
};

function utcDateKey(d = new Date()) {
  return d.toISOString().slice(0, 10);
}

function getXBudgetSettings() {
  const stored = getSetting('x_budget_settings', {});
  return { ...DEFAULT_SETTINGS, ...stored };
}

function freshDailyUsage(date = localDateKey()) {
  return {
    date,
    spent_usd: 0,
    read_cost_usd: 0,
    create_cost_usd: 0,
    plain_creates: 0,
    url_creates: 0,
    posts_with_url: 0,
    replies_with_url: 0,
    posts_plain: 0,
    replies_plain: 0,
    reads_owned: 0,
    reads_public: 0,
    reads_deduped: 0,
    blocked: 0,
  };
}

function getXDailyUsage() {
  const stored = getSetting('x_daily_usage', {});
  const today = localDateKey();
  if (!stored || typeof stored !== 'object' || stored.date !== today) {
    return freshDailyUsage(today);
  }
  return { ...freshDailyUsage(today), ...stored };
}

function saveXDailyUsage(usage) {
  setSetting('x_daily_usage', usage, 'system');
}

function getReadDedupStore() {
  const stored = getSetting('x_read_dedup', {});
  const todayUtc = utcDateKey();
  if (!stored || typeof stored !== 'object' || stored.date !== todayUtc) {
    return { date: todayUtc, resources: {} };
  }
  return { date: todayUtc, resources: { ...(stored.resources || {}) } };
}

function saveReadDedupStore(store) {
  setSetting('x_read_dedup', store, 'system');
}

function contentCountsAsUrl(text) {
  const s = String(text || '');
  if (hasLinks(s)) return true;
  if (/hobbyhashcoin\.com/i.test(s)) return true;
  if (/\bt\.co\//i.test(s)) return true;
  return false;
}

function getReadCost(kind, settings = getXBudgetSettings()) {
  switch (kind) {
    case 'owned_post':
    case 'owned_user':
      return Number(settings.owned_read_usd);
    case 'public_post':
      return Number(settings.post_read_usd);
    case 'public_user':
      return Number(settings.user_read_usd);
    default:
      return Number(settings.post_read_usd);
  }
}

function getCreateCost(withUrl, settings = getXBudgetSettings()) {
  return withUrl ? Number(settings.url_create_usd) : Number(settings.plain_create_usd);
}

function canAffordCost(additionalCost, settings = getXBudgetSettings(), usage = getXDailyUsage()) {
  if (!settings.enabled) return true;
  return Number(usage.spent_usd || 0) + Number(additionalCost) <= Number(settings.daily_budget_usd) + 1e-9;
}

function canAffordXCreate({ type, withUrl }, settings = getXBudgetSettings(), usage = getXDailyUsage()) {
  if (!settings.enabled) {
    return { ok: true, cost: 0, reason: null };
  }

  const cost = getCreateCost(withUrl, settings);
  if (!canAffordCost(cost, settings, usage)) {
    return { ok: false, cost, reason: 'daily_budget_exceeded' };
  }

  if (withUrl && type === 'post' && usage.posts_with_url >= Number(settings.max_posts_with_url_per_day)) {
    return { ok: false, cost, reason: 'post_url_cap_reached' };
  }

  if (withUrl && type === 'reply' && usage.replies_with_url >= Number(settings.max_replies_with_url_per_day)) {
    return { ok: false, cost, reason: 'reply_url_cap_reached' };
  }

  return { ok: true, cost, reason: null };
}

function canAffordXReads(kind, count, settings = getXBudgetSettings(), usage = getXDailyUsage()) {
  if (!settings.enabled || count <= 0) {
    return { ok: true, cost: 0, reason: null };
  }
  const cost = getReadCost(kind, settings) * count;
  if (!canAffordCost(cost, settings, usage)) {
    return { ok: false, cost, reason: 'daily_budget_exceeded' };
  }
  return { ok: true, cost, reason: null };
}

function logXUsageRow({ usageDate, actionType, costUsd, referenceId = null, hasUrl = false }) {
  try {
    getDb().prepare(`
      INSERT INTO social_x_usage_log (usage_date, action_type, has_url, cost_usd, reference_id, created_at)
      VALUES (?, ?, ?, ?, ?, datetime('now'))
    `).run(usageDate, actionType, hasUrl ? 1 : 0, costUsd, referenceId ? String(referenceId) : null);
  } catch (err) {
    logger.warn('X usage log insert failed', { error: err.message, actionType });
  }
}

function recordXReads(kind, resourceIds = [], options = {}) {
  const settings = getXBudgetSettings();
  const meta = READ_KINDS[kind];
  if (!meta) {
    logger.warn('Unknown X read kind', { kind });
    return { charged: 0, deduped: 0, cost: 0 };
  }

  const ids = [...new Set((Array.isArray(resourceIds) ? resourceIds : [resourceIds])
    .filter(Boolean)
    .map(String))];
  if (ids.length === 0) {
    return { charged: 0, deduped: 0, cost: 0 };
  }

  const usage = getXDailyUsage();
  const dedup = getReadDedupStore();
  const unitCost = getReadCost(kind, settings);
  let charged = 0;
  let deduped = 0;
  let totalCost = 0;

  for (const id of ids) {
    const dedupKey = `${kind}:${id}`;
    if (settings.dedupe_reads_utc !== false && dedup.resources[dedupKey]) {
      deduped += 1;
      logXUsageRow({
        usageDate: usage.date,
        actionType: `${meta.logType}_dedup`,
        costUsd: 0,
        referenceId: id,
      });
      continue;
    }

    if (settings.enabled && !canAffordCost(unitCost, settings, usage)) {
      logger.info('X read skipped — daily budget exhausted', { kind, id });
      logXUsageRow({
        usageDate: usage.date,
        actionType: `${meta.logType}_blocked`,
        costUsd: 0,
        referenceId: id,
      });
      continue;
    }

    usage.spent_usd = Math.round((Number(usage.spent_usd) + unitCost) * 10000) / 10000;
    usage.read_cost_usd = Math.round((Number(usage.read_cost_usd || 0) + unitCost) * 10000) / 10000;
    usage[meta.counter] = (usage[meta.counter] || 0) + 1;
    dedup.resources[dedupKey] = true;
    charged += 1;
    totalCost += unitCost;

    logXUsageRow({
      usageDate: usage.date,
      actionType: meta.logType,
      costUsd: unitCost,
      referenceId: id,
    });
  }

  usage.reads_deduped = (usage.reads_deduped || 0) + deduped;
  saveXDailyUsage(usage);
  saveReadDedupStore(dedup);

  if (charged > 0 || deduped > 0) {
    auditLog('x_budget_read_charge', {
      kind,
      charged,
      deduped,
      cost: totalCost,
      source: options.source || null,
    }, 'system');
  }

  return { charged, deduped, cost: totalCost };
}

function recordXMentionPoll({ timelineCount = 0, searchCount = 0, source = 'users/mentions', blocked = false } = {}) {
  const usage = getXDailyUsage();
  const parts = blocked
    ? ['budget_blocked', source]
    : [`${source}`, `timeline:${timelineCount}`];
  if (!blocked && searchCount > 0) parts.push(`search:${searchCount}`);

  logXUsageRow({
    usageDate: usage.date,
    actionType: blocked ? 'read_poll_blocked' : 'read_poll',
    costUsd: 0,
    referenceId: parts.join('|'),
  });

  return usage;
}

function incomingWantsLink(text) {
  return /(wallet|download|docs|documentation|website|web site|link|url|how do i|how can i|where (can|do) i|get started|start mining|join|pool|support|contact|explorer)/i.test(String(text || ''));
}

function pickLinkForIncoming(incomingText, platform = 'x') {
  const urls = getSiteUrls();
  const t = String(incomingText || '').toLowerCase();
  let url = urls.siteUrl;
  if (/wallet/.test(t)) url = urls.walletUrl;
  else if (/download|windows|wallet app/.test(t)) url = urls.downloadsUrl;
  else if (/doc|guide|setup|how/.test(t)) url = urls.docsUrl;
  else if (/pool|min(e|ing)/.test(t)) url = urls.poolUrl;
  else if (/explorer|block|height/.test(t)) url = urls.explorerUrl;
  else if (/support|contact|help/.test(t)) url = urls.contactUrl || urls.supportUrl || urls.siteUrl;

  const { withUtmTracking } = require('../generator/utmLinks');
  return withUtmTracking(url, platform);
}

function shouldOfferLinkInReply(incomingText, settings = getXBudgetSettings()) {
  if (!incomingWantsLink(incomingText)) return false;
  if (Math.random() >= Number(settings.reply_link_probability)) return false;
  return canAffordXCreate({ type: 'reply', withUrl: true }, settings).ok;
}

function appendLinkLine(content, link) {
  const base = String(content || '').trim();
  const line = `More here: ${link}`;
  const combined = base ? `${base} ${line}` : line;
  if (combined.length <= 280) return combined;
  return `${base.slice(0, 277 - line.length).replace(/\s+\S*$/, '')}… ${line}`.slice(0, 280);
}

function prepareXCreateContent({ type, content, incomingText = '' }) {
  const settings = getXBudgetSettings();
  let text = String(content || '').trim();
  let withUrl = contentCountsAsUrl(text);

  if (type === 'reply' && !withUrl && shouldOfferLinkInReply(incomingText, settings)) {
    text = appendLinkLine(text, pickLinkForIncoming(incomingText, 'x'));
    withUrl = contentCountsAsUrl(text);
  }

  if (withUrl && !canAffordXCreate({ type, withUrl: true }, settings).ok) {
    text = stripLinks(text).replace(/\bhobbyhashcoin\.com\S*/gi, '').replace(/\s{2,}/g, ' ').trim();
    withUrl = false;
    logger.info('X content downgraded to plain (no URL)', { type, reason: 'cap_or_budget' });
  }

  const check = canAffordXCreate({ type, withUrl }, settings);
  if (!check.ok) {
    const usage = getXDailyUsage();
    usage.blocked = (usage.blocked || 0) + 1;
    saveXDailyUsage(usage);
    auditLog('x_budget_blocked', { type, withUrl, reason: check.reason }, 'system');
    return { allowed: false, content: text, withUrl, cost: check.cost, reason: check.reason };
  }

  if (withUrl) {
    text = text.slice(0, 280);
  } else {
    text = stripLinks(text);
    if (text.length > 280) text = `${text.slice(0, 277).replace(/\s+\S*$/, '')}…`;
  }

  return {
    allowed: true,
    content: text,
    withUrl,
    cost: check.cost,
    reason: null,
  };
}

function recordXCreate({ type, withUrl, referenceId = null, cost = null }) {
  const settings = getXBudgetSettings();
  const usage = getXDailyUsage();
  const finalCost = cost ?? getCreateCost(withUrl, settings);

  usage.spent_usd = Math.round((Number(usage.spent_usd) + finalCost) * 10000) / 10000;
  usage.create_cost_usd = Math.round((Number(usage.create_cost_usd || 0) + finalCost) * 10000) / 10000;
  if (withUrl) {
    usage.url_creates = (usage.url_creates || 0) + 1;
    if (type === 'post') usage.posts_with_url = (usage.posts_with_url || 0) + 1;
    if (type === 'reply') usage.replies_with_url = (usage.replies_with_url || 0) + 1;
  } else {
    usage.plain_creates = (usage.plain_creates || 0) + 1;
    if (type === 'post') usage.posts_plain = (usage.posts_plain || 0) + 1;
    if (type === 'reply') usage.replies_plain = (usage.replies_plain || 0) + 1;
  }

  saveXDailyUsage(usage);

  logXUsageRow({
    usageDate: usage.date,
    actionType: type,
    costUsd: finalCost,
    referenceId,
    hasUrl: withUrl,
  });

  if (settings.enabled) {
    auditLog('x_budget_charge', { type, withUrl, cost: finalCost, referenceId }, 'system');
  }
  return usage;
}

function getXBudgetSummary() {
  const settings = getXBudgetSettings();
  const usage = getXDailyUsage();
  const budget = Number(settings.daily_budget_usd);
  const spent = Number(usage.spent_usd || 0);
  const remaining = Math.max(0, budget - spent);
  const plainLeft = Math.floor(remaining / Number(settings.plain_create_usd));
  const urlLeft = Math.floor(remaining / Number(settings.url_create_usd));
  const ownedReadLeft = Math.floor(remaining / Number(settings.owned_read_usd));

  return {
    settings,
    usage,
    spent_usd: spent,
    read_cost_usd: Number(usage.read_cost_usd || 0),
    create_cost_usd: Number(usage.create_cost_usd || 0),
    remaining_usd: Math.round(remaining * 100) / 100,
    budget_usd: budget,
    posts_with_url_left: Math.max(0, Number(settings.max_posts_with_url_per_day) - Number(usage.posts_with_url || 0)),
    replies_with_url_left: Math.max(0, Number(settings.max_replies_with_url_per_day) - Number(usage.replies_with_url || 0)),
    estimated_plain_left: plainLeft,
    estimated_url_left: urlLeft,
    estimated_owned_reads_left: ownedReadLeft,
    can_post_plain: canAffordXCreate({ type: 'post', withUrl: false }).ok,
    can_post_url: canAffordXCreate({ type: 'post', withUrl: true }).ok,
    can_reply_plain: canAffordXCreate({ type: 'reply', withUrl: false }).ok,
    can_reply_url: canAffordXCreate({ type: 'reply', withUrl: true }).ok,
  };
}

function xBudgetBlocksPlatform(platform) {
  if (platform !== 'x') return false;
  const settings = getXBudgetSettings();
  if (!settings.enabled) return false;
  return !canAffordXCreate({ type: 'post', withUrl: false }).ok;
}

function xBudgetBlocksMentionPoll() {
  const settings = getXBudgetSettings();
  if (!settings.enabled) return false;
  return !canAffordCost(getReadCost('owned_post', settings), settings);
}

function mentionSearchFallbackEnabled(settings = getXBudgetSettings()) {
  return !!settings.mention_search_fallback;
}

module.exports = {
  getXBudgetSettings,
  getXDailyUsage,
  contentCountsAsUrl,
  canAffordXCreate,
  canAffordXReads,
  prepareXCreateContent,
  recordXCreate,
  recordXReads,
  recordXMentionPoll,
  getXBudgetSummary,
  xBudgetBlocksPlatform,
  xBudgetBlocksMentionPoll,
  mentionSearchFallbackEnabled,
  incomingWantsLink,
  getCreateCost,
  getReadCost,
  utcDateKey,
};
