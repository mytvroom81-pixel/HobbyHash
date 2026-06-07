const fs = require('fs');
const path = require('path');
const config = require('../config');
const logger = require('../logger');
const { getSetting } = require('../db');

function isDryRun(forceLive = false) {
  if (forceLive || process.env.FORCE_PUBLISH === '1') return false;
  if (process.env.DRY_RUN === '0' || process.env.DRY_RUN === 'false') return false;
  if (process.env.DRY_RUN === '1' || process.env.DRY_RUN === 'true') return true;
  return getSetting('dry_run_mode', true) !== false;
}

function logDryRunPost(platform, content, meta = {}) {
  const logDir = path.join(__dirname, '..', '..', 'logs');
  if (!fs.existsSync(logDir)) fs.mkdirSync(logDir, { recursive: true });
  const entry = {
    time: new Date().toISOString(),
    platform,
    content,
    dry_run: true,
    ...meta,
  };
  const line = JSON.stringify(entry);
  fs.appendFileSync(path.join(logDir, 'dry-run.log'), line + '\n');
  logger.info('[DRY-RUN] Would post', { platform, preview: content.slice(0, 120), ...meta });
}

module.exports = { isDryRun, logDryRunPost };
