const config = require('../config');
const logger = require('../logger');
const { BRAND_VOICE, getSignOff, getHashtags, containsBlockedPhrase, sanitizeBrandText } = require('./brandVoice');
const {
  getTemplatesFromDb, selectTemplate, applyPlaceholders, maybeAddEmoji,
} = require('./templates');
const { isTooSimilar, isExactDuplicate } = require('./similarity');
const { getDb, getRecentPostContents, getLastPostTopic, hoursSinceLastTopicPost } = require('../db');
const { HEIGHT_TOPICS } = require('../events/eventProcessor');
const { generateWithAI } = require('./aiAdapter');
const { applySiteLinkPolicy } = require('./siteLink');
const { getAiConfig, getSiteUrls } = require('../config/runtimeConfig');
const { withUtmTracking, applyUrlTracking, getTrackedSiteUrl } = require('./utmLinks');

function truncateForPlatform(content, platform) {
  const limits = { x: 280, facebook: 63206, discord: 2000 };
  const max = limits[platform] || 2000;
  if (content.length <= max) return content;
  const cut = content.slice(0, max - 1).replace(/\s+\S*$/, '');
  return `${cut}…`;
}

function cleanupGrammar(text) {
  let result = text;
  result = result.replace(/\s{2,}/g, ' ');
  result = result.replace(/\s+([.,!?;:])/g, '$1');
  result = result.replace(/(^|[.!?]\s+)([a-z])/g, (m, p1, p2) => p1 + p2.toUpperCase());
  const fixes = [
    [/\bteh\b/gi, 'the'], [/\brecieve\b/gi, 'receive'], [/\boccured\b/gi, 'occurred'],
    [/\bseperate\b/gi, 'separate'], [/\bdefinately\b/gi, 'definitely'],
    [/\bwaht\b/gi, 'what'], [/\bminig\b/gi, 'mining'], [/\bblokc\b/gi, 'block'],
    [/\bhashratee\b/gi, 'hashrate'],
  ];
  for (const [re, rep] of fixes) result = result.replace(re, rep);
  return result.trim();
}

function buildDefaultVars(dataContext = {}, platform = 'discord') {
  const urls = getSiteUrls();
  const track = (url) => withUtmTracking(url, platform);
  return {
    height: dataContext.height ?? '—',
    miners: dataContext.miners ?? '—',
    hashrate: dataContext.hashrate ?? '—',
    difficulty: dataContext.difficulty ?? '—',
    blocks: dataContext.blocks ?? '—',
    visits: dataContext.visits ?? '—',
    version: dataContext.version ?? '—',
    platform: dataContext.platform ?? '—',
    pool: dataContext.pool ?? 'main',
    reward: dataContext.reward ?? '45',
    daily_visits: dataContext.daily_visits ?? '—',
    workers: dataContext.workers ?? '—',
    best_share: dataContext.best_share ?? '—',
    doc_title: dataContext.doc_title ?? 'HOBC docs',
    release_version: dataContext.version ?? dataContext.release_version ?? '—',
    ticker: config.brand.ticker,
    sync_status: dataContext.sync_status ?? 'synced',
    site_url: track(urls.siteUrl),
    docs_url: track(urls.docsUrl),
    pool_url: track(urls.poolUrl),
    explorer_url: track(urls.explorerUrl),
    wallet_url: track(urls.walletUrl),
    downloads_url: track(urls.downloadsUrl),
    support_url: track(urls.supportUrl),
  };
}

function generateFromTemplate(template, vars, platform) {
  let body = applyPlaceholders(template.body, vars);
  body = maybeAddEmoji(body, template.tone);
  body = cleanupGrammar(sanitizeBrandText(body));

  const signOff = getSignOff();
  if (signOff && Math.random() < 0.4) {
    body = `${body}\n${signOff}`;
  }

  const hashtags = getHashtags(platform);
  if (hashtags && platform === 'x') {
    body = `${body}\n${hashtags}`;
  }

  return body;
}

function buildFactsForTopic(topic, vars) {
  const parts = [];
  if (HEIGHT_TOPICS.has(topic)) {
    parts.push(`height=${vars.height}`);
  }
  if (topic === 'hashrate' || topic === 'pool_status') {
    parts.push(`hashrate=${vars.hashrate}`, `miners=${vars.miners}`, `workers=${vars.workers}`);
  } else if (topic === 'miner_milestone') {
    parts.push(`miners=${vars.miners}`);
  } else if (topic === 'traffic_milestone') {
    parts.push(`visits=${vars.visits}`);
  } else {
    parts.push(`miners=${vars.miners}`, `hashrate=${vars.hashrate}`, `pool=${vars.pool}`);
  }
  parts.push(`site=${vars.site_url}`);
  return parts.join(', ');
}

function buildTopicGuidance(topic) {
  if (HEIGHT_TOPICS.has(topic)) {
    return 'Mention the block height naturally once. Do not call every post a "milestone" unless it is a round-number height.';
  }
  return 'Do NOT mention block height, "block found", or chain height — focus on the topic angle (community, docs, wallet, mining tips, pool vibe).';
}

function buildAntiRepeatHint(recentPosts) {
  const snippets = recentPosts
    .slice(0, 5)
    .map((p) => p.content.replace(/\s+/g, ' ').trim().slice(0, 90))
    .filter(Boolean);
  if (!snippets.length) return '';
  return `\nRecent posts to avoid echoing (fresh wording and angle):\n- ${snippets.join('\n- ')}`;
}

function buildDeprioritizedTopics() {
  const blocked = [];
  if (hoursSinceLastTopicPost('block_found') < 24) {
    blocked.push('block_found');
  }
  const recent = getRecentPostContents(8);
  const blockCount = recent.filter((p) => p.topic === 'block_found' || p.topic === 'chain_status').length;
  if (blockCount >= 3) {
    blocked.push('block_found', 'chain_status', 'block_milestone');
  }
  return [...new Set(blocked)];
}

async function generatePost(options = {}) {
  const {
    platform = 'discord',
    topic = null,
    tone = null,
    dataContext = {},
    forceTopic = null,
    maxAttempts = 8,
    skipSiteLink = false,
    forceAi = false,
    excludeTopics = null,
  } = options;

  const aiCfg = getAiConfig();
  const db = getDb();
  const templates = getTemplatesFromDb(db);
  const vars = buildDefaultVars(dataContext, platform);
  const recentPosts = getRecentPostContents(100);
  const lastTopic = getLastPostTopic(platform);
  const topicBlocklist = excludeTopics ?? (forceTopic ? [] : buildDeprioritizedTopics());
  const excludeTopic = forceTopic ? null : lastTopic;

  for (let attempt = 0; attempt < maxAttempts; attempt++) {
    let selectedTemplate;
    if (topic || forceTopic) {
      const targetTopic = forceTopic || topic;
      const matches = templates.filter((t) => t.topic === targetTopic);
      selectedTemplate = matches.length > 0
        ? selectTemplate(matches, excludeTopic, topicBlocklist)
        : selectTemplate(templates, excludeTopic, topicBlocklist);
    } else if (tone) {
      const matches = templates.filter((t) => t.tone === tone);
      selectedTemplate = matches.length > 0
        ? selectTemplate(matches, excludeTopic, topicBlocklist)
        : selectTemplate(templates, excludeTopic, topicBlocklist);
    } else {
      selectedTemplate = selectTemplate(templates, excludeTopic, topicBlocklist);
    }

    let content = null;
    let source = 'template';

    const useAi = aiCfg.enabled && (
      forceAi
      || aiCfg.alwaysUseForPosts
      || aiCfg.useProbability >= 1
      || Math.random() < aiCfg.useProbability
    );
    if (useAi) {
      const facts = buildFactsForTopic(selectedTemplate.topic, vars);
      const aiPrompt = `Tone: ${selectedTemplate.tone}. Topic: ${selectedTemplate.topic}.
Facts: ${facts}.
${buildTopicGuidance(selectedTemplate.topic)}
Write a unique post similar in spirit to: "${selectedTemplate.body.slice(0, 120)}..."
${buildAntiRepeatHint(recentPosts)}
${platform === 'x' ? 'Hard limit: 240 characters total (X/Twitter max 280; leave room for links/hashtags).' : ''}`;
      content = await generateWithAI(aiPrompt);
      if (content) source = `ai:${aiCfg.provider}`;
    }

    if (!content) {
      content = generateFromTemplate(selectedTemplate, vars, platform);
    }

    if (containsBlockedPhrase(content)) {
      content = sanitizeBrandText(content);
    }

    if (isExactDuplicate(content, recentPosts)) continue;
    const simCheck = isTooSimilar(content, recentPosts, 0.65);
    if (simCheck.similar) continue;

    if (!skipSiteLink) {
      content = applySiteLinkPolicy(content, platform);
    }

    content = applyUrlTracking(content, platform);
    content = truncateForPlatform(content, platform);

    if (content.length > 2000) {
      content = content.slice(0, 1997) + '...';
    }

    return {
      content,
      topic: selectedTemplate.topic,
      tone: selectedTemplate.tone,
      templateName: selectedTemplate.name,
      source,
    };
  }

  let fallback = cleanupGrammar(
    `${config.brand.name} (${config.brand.ticker}) update — community mining at ${getTrackedSiteUrl(platform)}. ${getSignOff()}`
  );
  if (!skipSiteLink) fallback = applySiteLinkPolicy(fallback, platform, true);
  fallback = applyUrlTracking(fallback, platform);

  return {
    content: fallback,
    topic: 'mining_general',
    tone: 'casual_miner',
    templateName: 'fallback',
    source: 'fallback',
  };
}

module.exports = {
  generatePost,
  cleanupGrammar,
  buildDefaultVars,
  generateFromTemplate,
  buildDeprioritizedTopics,
  buildFactsForTopic,
};
