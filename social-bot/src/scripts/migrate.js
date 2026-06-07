const { getDb } = require('../db');
const logger = require('../logger');

const SCHEMA = `
CREATE TABLE IF NOT EXISTS social_posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  platform TEXT NOT NULL,
  content TEXT NOT NULL,
  topic TEXT NOT NULL,
  tone TEXT,
  status TEXT NOT NULL DEFAULT 'pending',
  source TEXT DEFAULT 'scheduler',
  event_id INTEGER,
  external_id TEXT,
  error_message TEXT,
  requires_approval INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  approved_at TEXT,
  approved_by TEXT,
  published_at TEXT,
  metadata TEXT
);

CREATE INDEX IF NOT EXISTS idx_social_posts_status ON social_posts(status);
CREATE INDEX IF NOT EXISTS idx_social_posts_platform ON social_posts(platform);
CREATE INDEX IF NOT EXISTS idx_social_posts_published ON social_posts(published_at);

CREATE TABLE IF NOT EXISTS social_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  event_type TEXT NOT NULL,
  payload TEXT NOT NULL,
  processed INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  processed_at TEXT
);

CREATE INDEX IF NOT EXISTS idx_social_events_processed ON social_events(processed);

CREATE TABLE IF NOT EXISTS social_platform_accounts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  platform TEXT NOT NULL UNIQUE,
  enabled INTEGER NOT NULL DEFAULT 0,
  display_name TEXT,
  account_id TEXT,
  config_json TEXT,
  last_post_at TEXT,
  daily_post_count INTEGER DEFAULT 0,
  daily_count_reset_at TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS social_reply_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  platform TEXT NOT NULL,
  channel_id TEXT,
  message_id TEXT,
  author_id TEXT,
  author_name TEXT,
  incoming_text TEXT NOT NULL,
  reply_text TEXT,
  intent TEXT,
  confidence REAL,
  status TEXT NOT NULL DEFAULT 'pending',
  requires_approval INTEGER NOT NULL DEFAULT 0,
  external_reply_id TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  replied_at TEXT,
  metadata TEXT
);

CREATE INDEX IF NOT EXISTS idx_social_reply_log_platform ON social_reply_log(platform);

CREATE TABLE IF NOT EXISTS social_x_usage_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  usage_date TEXT NOT NULL,
  action_type TEXT NOT NULL,
  has_url INTEGER NOT NULL DEFAULT 0,
  cost_usd REAL NOT NULL,
  reference_id TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_social_x_usage_log_date ON social_x_usage_log(usage_date);

CREATE TABLE IF NOT EXISTS social_templates (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  tone TEXT NOT NULL,
  topic TEXT NOT NULL,
  template_body TEXT NOT NULL,
  enabled INTEGER NOT NULL DEFAULT 1,
  weight INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS social_settings (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL,
  updated_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_by TEXT DEFAULT 'system'
);

CREATE TABLE IF NOT EXISTS social_milestones (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  milestone_type TEXT NOT NULL,
  milestone_value TEXT NOT NULL,
  reached_at TEXT NOT NULL DEFAULT (datetime('now')),
  posted INTEGER NOT NULL DEFAULT 0,
  UNIQUE(milestone_type, milestone_value)
);

CREATE TABLE IF NOT EXISTS social_content_cooldowns (
  content_key TEXT PRIMARY KEY,
  expires_at TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS social_audit_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  action TEXT NOT NULL,
  details TEXT,
  actor TEXT DEFAULT 'system',
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_social_audit_log_created ON social_audit_log(created_at);
`;

function migrate() {
  const db = getDb();
  db.exec(SCHEMA);
  logger.info('Database migration completed');
}

module.exports = { migrate, SCHEMA };

if (require.main === module) {
  migrate();
  console.log('Migration complete.');
}
