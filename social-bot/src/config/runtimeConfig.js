const config = require('../config');
const { getSetting } = require('../db');

function envList(key) {
  const v = process.env[key];
  if (!v || !v.trim()) return [];
  return v.split(',').map((s) => s.trim()).filter(Boolean);
}

function mergeSecret(incoming, existing) {
  if (incoming === undefined || incoming === null) return existing || '';
  const s = String(incoming).trim();
  if (s === '' || s === '__KEEP__') return existing || '';
  return s;
}

function getPlatformCredentials() {
  const stored = getSetting('platform_credentials', {});
  return {
    discord: {
      enabled: stored.discord?.enabled ?? config.discord.enabled,
      botToken: stored.discord?.botToken || config.discord.botToken || '',
      webhookUrl: stored.discord?.webhookUrl || config.discord.webhookUrl || '',
      channelId: stored.discord?.channelId || config.discord.channelId || '',
      replyChannelIds: (() => {
        const raw = stored.discord?.replyChannelIds;
        if (Array.isArray(raw)) return raw;
        if (typeof raw === 'string' && raw.trim()) {
          return raw.split(',').map((s) => s.trim()).filter(Boolean);
        }
        return config.discord.replyChannelIds;
      })(),
    },
    x: {
      enabled: stored.x?.enabled ?? config.x.enabled,
      apiKey: stored.x?.apiKey || config.x.apiKey || '',
      apiSecret: stored.x?.apiSecret || config.x.apiSecret || '',
      accessToken: stored.x?.accessToken || config.x.accessToken || '',
      accessSecret: stored.x?.accessSecret || config.x.accessSecret || '',
      bearerToken: stored.x?.bearerToken || config.x.bearerToken || '',
      oauth2ClientId: stored.x?.oauth2ClientId || config.x.oauth2ClientId || '',
      oauth2ClientSecret: stored.x?.oauth2ClientSecret || config.x.oauth2ClientSecret || '',
      oauth2RedirectUri: stored.x?.oauth2RedirectUri || config.x.oauth2RedirectUri || '',
      oauth2AccessToken: stored.x?.oauth2AccessToken || config.x.oauth2AccessToken || '',
      oauth2RefreshToken: stored.x?.oauth2RefreshToken || config.x.oauth2RefreshToken || '',
      oauth2ExpiresAt: stored.x?.oauth2ExpiresAt || config.x.oauth2ExpiresAt || 0,
      replyEnabled: stored.x?.replyEnabled ?? config.x.replyEnabled,
    },
    facebook: {
      enabled: stored.facebook?.enabled ?? config.facebook.enabled,
      pageId: stored.facebook?.pageId || config.facebook.pageId || '',
      pageAccessToken: stored.facebook?.pageAccessToken || config.facebook.pageAccessToken || '',
      replyEnabled: stored.facebook?.replyEnabled ?? config.facebook.replyEnabled,
    },
  };
}

function getAiConfig() {
  const stored = getSetting('ai_config', {});
  const provider = stored.provider || 'none';
  const openaiKey = stored.openai?.apiKey || process.env.OPENAI_API_KEY || process.env.AI_API_KEY || '';
  const anthropicKey = stored.anthropic?.apiKey || process.env.ANTHROPIC_API_KEY || '';

  let activeProvider = provider;
  if (provider === 'openai' && !openaiKey) activeProvider = 'none';
  if (provider === 'anthropic' && !anthropicKey) activeProvider = 'none';
  if (provider !== 'openai' && provider !== 'anthropic') activeProvider = 'none';

  return {
    enabled: !!(stored.enabled && activeProvider !== 'none'),
    provider: activeProvider,
    useProbability: stored.use_probability ?? stored.useProbability ?? 0.35,
    alwaysUseForPosts: !!(stored.always_use_for_posts ?? stored.alwaysUseForPosts),
    repliesUseAi: stored.replies_use_ai ?? stored.repliesUseAi ?? true,
    openai: {
      apiKey: openaiKey,
      model: stored.openai?.model || process.env.OPENAI_MODEL || 'gpt-4o-mini',
    },
    anthropic: {
      apiKey: anthropicKey,
      model: stored.anthropic?.model || process.env.ANTHROPIC_MODEL || 'claude-3-5-haiku-latest',
    },
  };
}

function getSiteUrls() {
  const stored = getSetting('site_urls', {});
  return {
    siteUrl: stored.siteUrl || config.siteUrl,
    docsUrl: stored.docsUrl || config.docsUrl,
    poolUrl: stored.poolUrl || config.poolUrl,
    explorerUrl: stored.explorerUrl || config.explorerUrl,
    walletUrl: stored.walletUrl || config.walletUrl,
    downloadsUrl: stored.downloadsUrl || config.downloadsUrl,
    supportUrl: stored.supportUrl || config.supportUrl,
    contactUrl: stored.contactUrl || config.contactUrl,
  };
}

function getLinkSettings() {
  return getSetting('site_link_settings', { enabled: true, min_posts: 5, max_posts: 10 });
}

function getRuntimeConfig() {
  return {
    discord: getPlatformCredentials().discord,
    x: getPlatformCredentials().x,
    facebook: getPlatformCredentials().facebook,
    ai: getAiConfig(),
    urls: getSiteUrls(),
    linkSettings: getLinkSettings(),
    brand: config.brand,
  };
}

function isPlatformEnabledRuntime(platform) {
  const creds = getPlatformCredentials();
  return !!creds[platform]?.enabled;
}

module.exports = {
  getRuntimeConfig,
  getPlatformCredentials,
  getAiConfig,
  getSiteUrls,
  getLinkSettings,
  isPlatformEnabledRuntime,
  mergeSecret,
  envList,
};
