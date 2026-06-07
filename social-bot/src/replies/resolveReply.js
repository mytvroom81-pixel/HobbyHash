const { getAiConfig, getSiteUrls } = require('../config/runtimeConfig');
const { withUtmTracking } = require('../generator/utmLinks');
const { generateReplyWithAI } = require('../generator/aiAdapter');
const { BRAND_VOICE } = require('../generator/brandVoice');
const { formatReplyForPlatform } = require('./formatReply');

function getContactFallbackReply(platform) {
  if (platform === 'x') {
    return "I'm not sure how to answer that here. Please reach out through the Contact page on the HobbyHash Coin website and our team will help.";
  }
  const urls = getSiteUrls();
  const base = (urls.contactUrl || `${urls.siteUrl.replace(/\/$/, '')}/contact/`).replace(/\/$/, '');
  const contact = withUtmTracking(`${base}/`, platform);
  return `I'm not sure how to answer that here. Please contact our team and we'll help: ${contact}`;
}

function finalizeReply(reply, platform) {
  return formatReplyForPlatform(reply, platform);
}

function messageWantsLiveStats(text) {
  return /(height|block|hashrate|hash rate|miners?|workers?|difficulty|pool stats|network|sync|chain)/i.test(text);
}

function buildLiveFacts(ctx) {
  if (!ctx) return '';
  return `height=${ctx.height}, miners=${ctx.miners}, workers=${ctx.workers}, hashrate=${ctx.hashrate}, pool=${ctx.pool}`;
}

async function resolveReply(text, meta = {}) {
  const trimmed = (text || '').trim();
  if (trimmed.length < 2) {
    return { intent: 'empty', confidence: 0, reply: finalizeReply(getContactFallbackReply(meta.platform), meta.platform), source: 'fallback' };
  }

  if (BRAND_VOICE.blockedPhrases.some((p) => trimmed.toLowerCase().includes(p))) {
    return {
      intent: 'blocked',
      confidence: 1,
      reply: finalizeReply(getContactFallbackReply(meta.platform), meta.platform),
      source: 'fallback',
    };
  }

  const aiCfg = getAiConfig();
  const useAiOnly = aiCfg.enabled && aiCfg.provider !== 'none' && aiCfg.repliesUseAi !== false;

  if (!useAiOnly) {
    const { matchIntent } = require('./faq');
    const faq = matchIntent(trimmed);
    return { ...faq, reply: finalizeReply(faq.reply, meta.platform), source: 'faq' };
  }

  let facts = '';
  if (messageWantsLiveStats(trimmed) && meta.dataContext) {
    facts = buildLiveFacts(meta.dataContext);
  }

  const aiResult = await generateReplyWithAI(trimmed, {
    platform: meta.platform,
    authorName: meta.authorName,
    facts,
  });

  if (aiResult?.reply) {
    return {
      intent: 'ai_reply',
      confidence: 0.95,
      reply: finalizeReply(aiResult.reply, meta.platform),
      source: 'ai',
    };
  }

  return {
    intent: 'contact_fallback',
    confidence: 0.95,
    reply: finalizeReply(getContactFallbackReply(meta.platform), meta.platform),
    source: 'fallback',
  };
}

module.exports = {
  resolveReply,
  getContactFallbackReply,
  messageWantsLiveStats,
};
