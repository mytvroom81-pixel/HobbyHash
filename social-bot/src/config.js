require('dotenv').config();

const fs = require('fs');
const path = require('path');

function envBool(key, fallback = false) {
  const v = process.env[key];
  if (v === undefined || v === '') return fallback;
  return ['1', 'true', 'yes', 'on'].includes(String(v).toLowerCase());
}

function envInt(key, fallback) {
  const v = parseInt(process.env[key], 10);
  return Number.isFinite(v) ? v : fallback;
}

function envList(key) {
  const v = process.env[key];
  if (!v || !v.trim()) return [];
  return v.split(',').map((s) => s.trim()).filter(Boolean);
}

function readInternalTokenFile() {
  const tokenPath = path.join(__dirname, '..', 'data', 'internal-token');
  try {
    if (fs.existsSync(tokenPath)) {
      return fs.readFileSync(tokenPath, 'utf8').trim();
    }
  } catch {
    // ignore
  }
  return '';
}

const config = {
  port: envInt('PORT', 3847),
  nodeEnv: process.env.NODE_ENV || 'development',
  sessionSecret: process.env.SESSION_SECRET || 'dev-insecure-secret-change-me',
  adminUsername: process.env.ADMIN_USERNAME || 'admin',
  adminPassword: process.env.ADMIN_PASSWORD || '',

  siteUrl: process.env.SITE_URL || 'https://hobbyhashcoin.com',
  docsUrl: process.env.DOCS_URL || 'https://hobbyhashcoin.com/docs/',
  poolUrl: process.env.POOL_URL || 'https://hobbyhashcoin.com/pool/',
  explorerUrl: process.env.EXPLORER_URL || 'https://hobbyhashcoin.com/explorer/',
  walletUrl: process.env.WALLET_URL || 'https://hobbyhashcoin.com/wallet/',
  downloadsUrl: process.env.DOWNLOADS_URL || 'https://hobbyhashcoin.com/downloads/',
  supportUrl: process.env.SUPPORT_URL || 'https://hobbyhashcoin.com/support/',
  contactUrl: process.env.CONTACT_URL || 'https://hobbyhashcoin.com/contact/',
  adminTimezone: process.env.HOBC_ADMIN_TIMEZONE || process.env.HOBC_ANALYTICS_TIMEZONE || '',

  apiBase: process.env.HOBC_API_BASE || 'https://hobbyhashcoin.com/api',
  poolStatsMain: process.env.POOL_STATS_MAIN || '/home/hobbyhashcoin/hobbyhash-data/mainnet/pool-stats-main.json',
  poolStatsNano: process.env.POOL_STATS_NANO || '/home/hobbyhashcoin/hobbyhash-data/mainnet/pool-stats-nano.json',
  poolStatusMain: process.env.POOL_STATUS_MAIN || '/home/hobbyhashcoin/hobbyhash-logs/ckpool-main/pool/pool.status',
  poolStatusNano: process.env.POOL_STATUS_NANO || '/home/hobbyhashcoin/hobbyhash-logs/ckpool-nano/pool/pool.status',
  payoutStateMain: process.env.PAYOUT_STATE_MAIN || '/home/hobbyhashcoin/hobbyhash-data/mainnet/payoutd-main-state.json',
  payoutStateNano: process.env.PAYOUT_STATE_NANO || '/home/hobbyhashcoin/hobbyhash-data/mainnet/payoutd-nano-state.json',

  mysql: {
    host: process.env.MYSQL_HOST || '127.0.0.1',
    port: envInt('MYSQL_PORT', 3306),
    user: process.env.MYSQL_USER || '',
    password: process.env.MYSQL_PASSWORD || '',
    database: process.env.MYSQL_DATABASE || 'hobbyhash_wallet',
  },

  hobbyhashCli: process.env.HOBBYHASH_CLI || 'hobbyhash-cli',
  hobbyhashCliConf: process.env.HOBBYHASH_CLI_CONF || '',

  dbPath: process.env.DB_PATH || path.join(__dirname, '..', 'data', 'social-bot.db'),
  internalToken: process.env.SOCIAL_BOT_INTERNAL_TOKEN || readInternalTokenFile(),

  discord: {
    enabled: envBool('DISCORD_ENABLED'),
    botToken: process.env.DISCORD_BOT_TOKEN || '',
    webhookUrl: process.env.DISCORD_WEBHOOK_URL || '',
    channelId: process.env.DISCORD_CHANNEL_ID || '',
    replyChannelIds: envList('DISCORD_REPLY_CHANNEL_IDS'),
  },

  x: {
    enabled: envBool('X_ENABLED'),
    apiKey: process.env.X_API_KEY || '',
    apiSecret: process.env.X_API_SECRET || '',
    accessToken: process.env.X_ACCESS_TOKEN || '',
    accessSecret: process.env.X_ACCESS_SECRET || '',
    bearerToken: process.env.X_BEARER_TOKEN || '',
    oauth2ClientId: process.env.X_OAUTH2_CLIENT_ID || '',
    oauth2ClientSecret: process.env.X_OAUTH2_CLIENT_SECRET || '',
    oauth2RedirectUri: process.env.X_OAUTH2_REDIRECT_URI || 'https://hobbyhashcoin.com/admin/social-bot-x-oauth.php',
    oauth2AccessToken: process.env.X_OAUTH2_ACCESS_TOKEN || '',
    oauth2RefreshToken: process.env.X_OAUTH2_REFRESH_TOKEN || '',
    oauth2ExpiresAt: 0,
    replyEnabled: envBool('X_REPLY_ENABLED'),
  },

  facebook: {
    enabled: envBool('FACEBOOK_ENABLED'),
    pageId: process.env.FACEBOOK_PAGE_ID || '',
    pageAccessToken: process.env.FACEBOOK_PAGE_ACCESS_TOKEN || '',
    replyEnabled: envBool('FACEBOOK_REPLY_ENABLED'),
  },

  ai: {
    enabled: envBool('AI_ENABLED') && !!process.env.AI_API_KEY,
    apiKey: process.env.AI_API_KEY || '',
    baseUrl: process.env.AI_BASE_URL || 'https://api.openai.com/v1',
    model: process.env.AI_MODEL || 'gpt-4o-mini',
  },

  brand: {
    name: 'HobbyHash Coin',
    ticker: 'HOBC',
    botName: 'HobbyHash Update Bot',
    tagline: 'community-driven SHA-256 mining',
  },
};

module.exports = config;
