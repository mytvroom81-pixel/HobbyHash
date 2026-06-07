const { BRAND_VOICE } = require('./brandVoice');

/**
 * Default template library — used when AI is disabled or fails.
 * Placeholders: {height}, {miners}, {hashrate}, {difficulty}, {blocks}, {visits}, {version}, {platform}, {pool}, {url}, {ticker}, {reward}
 */

const DEFAULT_TEMPLATES = [
  // casual_miner
  {
    name: 'casual_pool_check',
    tone: 'casual_miner',
    topic: 'pool_status',
    weight: 3,
    body: 'Quick pool check — {miners} miners connected on {pool} right now. Shares are flowing. If you\'ve been thinking about pointing a rig at HOBC, docs are at {docs_url}.',
  },
  {
    name: 'casual_mining_vibe',
    tone: 'casual_miner',
    topic: 'mining_general',
    weight: 2,
    body: 'Another block interval in the books. HobbyHash keeps ticking along — community SHA-256 mining without the noise. Pool: {pool_url}',
  },
  {
    name: 'casual_weekend',
    tone: 'casual_miner',
    topic: 'community',
    weight: 1,
    body: 'Weekend mining session? HOBC pool is live. Grab a payout address from the web wallet or run your own node — either way, happy hashing.',
  },

  // technical_network
  {
    name: 'tech_chain_height',
    tone: 'technical_network',
    topic: 'chain_status',
    weight: 3,
    body: 'Chain height: {height}. Network difficulty: {difficulty}. Explorer has the full ledger at {explorer_url}.',
  },
  {
    name: 'tech_hashrate',
    tone: 'technical_network',
    topic: 'hashrate',
    weight: 2,
    body: '{pool} pool hashrate sitting around {hashrate}. {miners} active workers contributing shares.',
  },
  {
    name: 'tech_sync',
    tone: 'technical_network',
    topic: 'node_status',
    weight: 1,
    body: 'Node sync status: {sync_status}. Block reward per find: {reward} {ticker}. Node setup guide in docs.',
  },

  // milestone_celebration
  {
    name: 'milestone_height',
    tone: 'milestone_celebration',
    topic: 'block_milestone',
    weight: 5,
    body: 'Block {height} — that\'s a milestone worth noting. Thanks to everyone hashing on HOBC. Explorer: {explorer_url}',
  },
  {
    name: 'milestone_visits',
    tone: 'milestone_celebration',
    topic: 'traffic_milestone',
    weight: 4,
    body: 'hobbyhashcoin.com just crossed {visits} visits. Good to see more people discovering the project.',
  },
  {
    name: 'milestone_miners',
    tone: 'milestone_celebration',
    topic: 'miner_milestone',
    weight: 4,
    body: '{miners} miners on the pool now — community is growing. Welcome to everyone who joined recently.',
  },
  {
    name: 'docs_updated',
    tone: 'technical_network',
    topic: 'docs_update',
    weight: 3,
    body: 'Docs update: {doc_title} — full guides at {docs_url}. Good time to double-check mining setup or wallet options.',
  },
  {
    name: 'block_found_pool',
    tone: 'milestone_celebration',
    topic: 'block_found',
    weight: 5,
    body: 'Pool block at height {height}! {reward} {ticker} subsidy heading to a miner. Explorer: {explorer_url}',
  },

  // safety_reminder
  {
    name: 'safety_custody',
    tone: 'safety_reminder',
    topic: 'wallet_safety',
    weight: 2,
    body: 'Reminder: the web wallet is custodial — great for beginners, but run a standalone wallet or your own node if you want full key control. Docs explain both paths.',
  },
  {
    name: 'safety_phishing',
    tone: 'safety_reminder',
    topic: 'security',
    weight: 1,
    body: 'Official site is hobbyhashcoin.com only. We never DM asking for seeds or passwords. Report suspicious messages via support.',
  },
  {
    name: 'safety_backups',
    tone: 'safety_reminder',
    topic: 'wallet_safety',
    weight: 1,
    body: 'If you run the standalone wallet, back up your wallet.dat. Lost keys = lost coins. No recovery from our side for self-hosted wallets.',
  },

  // wallet_download
  {
    name: 'wallet_web',
    tone: 'wallet_download',
    topic: 'wallet',
    weight: 2,
    body: 'Need a payout address fast? Web wallet at {wallet_url} — no install required. Standalone builds for Windows/Linux on the downloads page.',
  },
  {
    name: 'wallet_download_new',
    tone: 'wallet_download',
    topic: 'download_release',
    weight: 4,
    body: 'New {platform} wallet release: v{version}. Checksums on the downloads page. Always verify before installing.',
  },
  {
    name: 'wallet_node',
    tone: 'wallet_download',
    topic: 'node_setup',
    weight: 2,
    body: 'Running your own HOBC node? Full setup guide in docs — sync, mine solo, or point your miner at our pool.',
  },

  // community_question
  {
    name: 'community_rig',
    tone: 'community_question',
    topic: 'community',
    weight: 2,
    body: 'What hardware are you mining HOBC with? ASIC, old GPU rig experiment, or something else? Curious what the community is running.',
  },
  {
    name: 'community_pool',
    tone: 'community_question',
    topic: 'community',
    weight: 1,
    body: 'Main pool or nano pool — which are you on and why? Both are live at {pool_url}.',
  },
  {
    name: 'community_docs',
    tone: 'community_question',
    topic: 'community',
    weight: 1,
    body: 'Which part of the HOBC docs helped you most when getting started? Always looking to improve them.',
  },

  // educational_fact
  {
    name: 'edu_subsidy',
    tone: 'educational_fact',
    topic: 'mining_facts',
    weight: 2,
    body: 'HOBC block subsidy: {reward} {ticker} per block. Total supply cap: 84M. Normal mining target: 75.6M coins.',
  },
  {
    name: 'edu_sha256',
    tone: 'educational_fact',
    topic: 'mining_facts',
    weight: 2,
    body: 'HobbyHash uses SHA-256 — same family as Bitcoin. That means many ASICs can hash HOBC with a pool connection and payout address.',
  },
  {
    name: 'edu_burn',
    tone: 'educational_fact',
    topic: 'tokenomics',
    weight: 1,
    body: 'HOBC has a documented burn address and launch reserve. Supply stats are public on the site — no hidden minting.',
  },
];

const TONE_EMOJI = {
  casual_miner: ['⛏️', '🔧', '👋'],
  technical_network: ['📊', '🔗'],
  milestone_celebration: ['🎉', '🏁', '✨'],
  safety_reminder: ['🔒', '⚠️'],
  wallet_download: ['💼', '📥'],
  community_question: ['💬', '🤔'],
  educational_fact: ['📖', '💡'],
};

const TOPICS = [
  'pool_status', 'mining_general', 'community', 'chain_status', 'hashrate',
  'node_status', 'block_milestone', 'traffic_milestone', 'miner_milestone',
  'block_found', 'wallet_safety', 'security', 'wallet', 'download_release',
  'node_setup', 'mining_facts', 'tokenomics', 'docs_update',
];

function pickRandom(arr) {
  return arr[Math.floor(Math.random() * arr.length)];
}

function maybeAddEmoji(text, tone) {
  const bucket = BRAND_VOICE.toneBuckets[tone];
  if (!bucket || Math.random() > bucket.emojiChance) return text;

  const emojis = TONE_EMOJI[tone] || [];
  if (emojis.length === 0) return text;

  const count = 1 + Math.floor(Math.random() * Math.min(bucket.maxEmoji, emojis.length));
  const chosen = [];
  for (let i = 0; i < count; i++) {
    chosen.push(pickRandom(emojis));
  }

  if (Math.random() < 0.5) {
    return `${chosen.join('')} ${text}`;
  }
  return `${text} ${chosen.join('')}`;
}

function applyPlaceholders(template, vars) {
  let result = template;
  for (const [key, value] of Object.entries(vars)) {
    result = result.replace(new RegExp(`\\{${key}\\}`, 'g'), value ?? '');
  }
  return result.replace(/\{[a-z_]+\}/g, '').replace(/\s+/g, ' ').trim();
}

function getTemplatesFromDb(db) {
  try {
    const rows = db.prepare('SELECT * FROM social_templates WHERE enabled = 1').all();
    if (rows.length > 0) return rows.map((r) => ({
      name: r.name,
      tone: r.tone,
      topic: r.topic,
      weight: r.weight,
      body: r.template_body,
    }));
  } catch {
    // table may not exist yet during seed
  }
  return DEFAULT_TEMPLATES;
}

function selectTemplate(templates, excludeTopic = null, excludeTopics = []) {
  const blocked = new Set(excludeTopics);
  if (excludeTopic) blocked.add(excludeTopic);

  let pool = templates;
  if (blocked.size > 0) {
    const filtered = templates.filter((t) => !blocked.has(t.topic));
    if (filtered.length > 0) pool = filtered;
  }

  const totalWeight = pool.reduce((sum, t) => sum + (t.weight || 1), 0);
  let roll = Math.random() * totalWeight;
  for (const t of pool) {
    roll -= t.weight || 1;
    if (roll <= 0) return t;
  }
  return pool[pool.length - 1];
}

module.exports = {
  DEFAULT_TEMPLATES,
  TOPICS,
  TONE_EMOJI,
  pickRandom,
  maybeAddEmoji,
  applyPlaceholders,
  getTemplatesFromDb,
  selectTemplate,
};
