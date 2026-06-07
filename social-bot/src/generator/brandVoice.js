/**
 * HobbyHash Coin official brand voice guidelines.
 * Used by template generator and optional AI adapter.
 */

const config = require('../config');

const BRAND_VOICE = {
  identity: {
    name: config.brand.name,
    ticker: config.brand.ticker,
    botName: config.brand.botName,
    tagline: config.brand.tagline,
  },

  personality: [
    'Friendly community miner, not a hype machine',
    'Informative but approachable — like a helpful pool operator',
    'Proud of milestones without sounding like a shill',
    'Transparent about custodial web wallet vs self-hosted options',
    'Never promises price, investment returns, or "moon" language',
  ],

  toneBuckets: {
    casual_miner: {
      description: 'Relaxed update from someone who actually mines HOBC',
      emojiChance: 0.3,
      maxEmoji: 2,
    },
    technical_network: {
      description: 'Factual network/pool stats with light context',
      emojiChance: 0.1,
      maxEmoji: 1,
    },
    milestone_celebration: {
      description: 'Genuine excitement for community wins',
      emojiChance: 0.5,
      maxEmoji: 3,
    },
    safety_reminder: {
      description: 'Helpful security/custody reminders, never alarmist',
      emojiChance: 0.15,
      maxEmoji: 1,
    },
    wallet_download: {
      description: 'Practical nudge toward wallet or node setup',
      emojiChance: 0.2,
      maxEmoji: 2,
    },
    community_question: {
      description: 'Open-ended question to spark discussion',
      emojiChance: 0.25,
      maxEmoji: 2,
    },
    educational_fact: {
      description: 'Short mining or network fact about HOBC',
      emojiChance: 0.15,
      maxEmoji: 1,
    },
  },

  signOffs: [
    '— HobbyHash Update Bot',
    '— official HOBC updates',
    '— HobbyHash Update Bot · hobbyhashcoin.com',
    '',
  ],

  hashtags: {
    x: ['#HOBC', '#HobbyHashCoin', '#Mining'],
    facebook: [],
    discord: [],
  },

  blockedPhrases: [
    'guaranteed returns',
    'get rich',
    'to the moon',
    '100x',
    'not financial advice but',
    'buy now',
    'last chance',
    'don\'t miss out',
    'pump',
    'insider',
    'airdrop scam',
    'free coins',
    'double your',
    'risk-free',
    'financial advice',
    'investment opportunity',
    'lambo',
    'wen lambo',
    'gem alert',
    'secret alpha',
  ],

  preferredTerms: {
    coin: 'HobbyHash Coin',
    ticker: 'HOBC',
    site: 'hobbyhashcoin.com',
    mining: 'SHA-256 mining',
    wallet: 'web wallet or standalone wallet',
  },

  urls: {
    site: config.siteUrl,
    docs: config.docsUrl,
    pool: config.poolUrl,
    explorer: config.explorerUrl,
    wallet: config.walletUrl,
    downloads: config.downloadsUrl,
    support: config.supportUrl,
  },
};

function getSignOff() {
  const options = BRAND_VOICE.signOffs;
  return options[Math.floor(Math.random() * options.length)];
}

function getHashtags(platform) {
  const tags = BRAND_VOICE.hashtags[platform] || [];
  if (tags.length === 0) return '';
  const count = Math.min(tags.length, 1 + Math.floor(Math.random() * 2));
  const shuffled = [...tags].sort(() => Math.random() - 0.5);
  return shuffled.slice(0, count).join(' ');
}

function containsBlockedPhrase(text) {
  const lower = text.toLowerCase();
  return BRAND_VOICE.blockedPhrases.some((phrase) => lower.includes(phrase.toLowerCase()));
}

function sanitizeBrandText(text) {
  let result = text;
  for (const phrase of BRAND_VOICE.blockedPhrases) {
    const re = new RegExp(phrase.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
    result = result.replace(re, '');
  }
  result = result.replace(/\s{2,}/g, ' ').trim();
  return result;
}

module.exports = {
  BRAND_VOICE,
  getSignOff,
  getHashtags,
  containsBlockedPhrase,
  sanitizeBrandText,
};
