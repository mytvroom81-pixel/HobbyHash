const config = require('../config');
const { BRAND_VOICE } = require('../generator/brandVoice');

const FAQ_INTENTS = [
  {
    intent: 'what_is_hobbyhash',
    patterns: [
      /what\s+is\s+(hobby\s*hash|hobc|hobbyhash\s*coin)/i,
      /tell\s+me\s+about\s+(hobby\s*hash|hobc)/i,
      /explain\s+(hobby\s*hash|hobc)/i,
    ],
    confidence: 0.9,
    reply: () =>
      `${config.brand.name} (${config.brand.ticker}) is a community SHA-256 mineable coin. Site: ${config.siteUrl} — docs cover mining, wallets, and the pool.`,
  },
  {
    intent: 'how_to_mine',
    patterns: [
      /how\s+(do\s+i|to)\s+mine/i,
      /start\s+mining/i,
      /mining\s+setup/i,
      /connect\s+(to\s+)?pool/i,
    ],
    confidence: 0.85,
    reply: () =>
      `Point your SHA-256 miner at our pool (${config.poolUrl}), use a HOBC payout address from the web wallet or your node. Full guide: ${config.docsUrl}`,
  },
  {
    intent: 'wallet_link',
    patterns: [
      /wallet/i,
      /download/i,
      /where\s+(can\s+i\s+)?get\s+(a\s+)?wallet/i,
      /standalone/i,
    ],
    confidence: 0.75,
    reply: () =>
      `Web wallet: ${config.walletUrl} | Standalone downloads: ${config.downloadsUrl}. Web wallet is custodial; standalone gives you key control.`,
  },
  {
    intent: 'pool_link',
    patterns: [
      /pool\s*(url|address|link)?/i,
      /stratum/i,
      /which\s+pool/i,
    ],
    confidence: 0.85,
    reply: () =>
      `HOBC mining pool: ${config.poolUrl} — main and nano pools available. Stratum details on the pool page.`,
  },
  {
    intent: 'explorer_link',
    patterns: [
      /explorer/i,
      /block\s*explorer/i,
      /view\s+(on\s+)?chain/i,
      /check\s+(a\s+)?transaction/i,
    ],
    confidence: 0.85,
    reply: () =>
      `Block explorer: ${config.explorerUrl}`,
  },
  {
    intent: 'node_setup',
    patterns: [
      /node\s+setup/i,
      /run\s+(a\s+)?node/i,
      /hobbyhashd/i,
      /sync\s+node/i,
    ],
    confidence: 0.85,
    reply: () =>
      `Node setup guide: ${config.docsUrl} — includes hobbyhashd install, sync, and RPC config.`,
  },
  {
    intent: 'block_reward',
    patterns: [
      /block\s+reward/i,
      /how\s+much\s+per\s+block/i,
      /subsidy/i,
      /reward\s+per\s+block/i,
    ],
    confidence: 0.9,
    reply: () =>
      `Current block subsidy: 45 ${config.brand.ticker}. Total supply cap: 84M coins.`,
  },
  {
    intent: 'ticker',
    patterns: [
      /\bticker\b/i,
      /\bsymbol\b/i,
      /what\s+is\s+the\s+coin\s+called/i,
    ],
    confidence: 0.9,
    reply: () =>
      `Ticker: ${config.brand.ticker} (${config.brand.name}).`,
  },
  {
    intent: 'custodial',
    patterns: [
      /custodial/i,
      /non[\s-]?custodial/i,
      /who\s+holds\s+(my\s+)?keys/i,
      /do\s+you\s+control\s+my/i,
    ],
    confidence: 0.85,
    reply: () =>
      `Web wallet = custodial (we hold keys for convenience). Standalone wallet / your own node = non-custodial. Details in docs: ${config.docsUrl}`,
  },
  {
    intent: 'support',
    patterns: [
      /support/i,
      /contact/i,
      /help\s+me/i,
      /report/i,
      /problem/i,
    ],
    confidence: 0.7,
    reply: () =>
      `Support tickets: ${config.supportUrl} — please don't share passwords or seed phrases anywhere.`,
  },
];

const LOW_CONFIDENCE_REPLY = () =>
  `I'm the official ${config.brand.botName}. Not sure I follow — check the docs at ${config.docsUrl} or open a support ticket: ${config.supportUrl}`;

function matchIntent(text) {
  if (!text || text.length < 3) {
    return { intent: 'unknown', confidence: 0, reply: LOW_CONFIDENCE_REPLY() };
  }

  if (BRAND_VOICE.blockedPhrases.some((p) => text.toLowerCase().includes(p))) {
    return {
      intent: 'blocked',
      confidence: 1,
      reply: `I can't help with that. Official info only at ${config.siteUrl}.`,
    };
  }

  let best = { intent: 'unknown', confidence: 0, reply: LOW_CONFIDENCE_REPLY() };

  for (const faq of FAQ_INTENTS) {
    for (const pattern of faq.patterns) {
      if (pattern.test(text)) {
        const conf = faq.confidence;
        if (conf > best.confidence) {
          best = {
            intent: faq.intent,
            confidence: conf,
            reply: faq.reply(),
          };
        }
      }
    }
  }

  return best;
}

module.exports = {
  FAQ_INTENTS,
  matchIntent,
  LOW_CONFIDENCE_REPLY,
};
