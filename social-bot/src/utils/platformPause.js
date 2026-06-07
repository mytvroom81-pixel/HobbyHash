const { getSetting, setSetting, auditLog } = require('../db');
const logger = require('../logger');

function isFacebookAuthError(message = '') {
  const m = String(message).toLowerCase();
  return m.includes('code 190')
    || m.includes('error validating access token')
    || m.includes('session has expired')
    || m.includes('must be granted before impersonating');
}

function getFacebookPostingPause() {
  return getSetting('facebook_posting_paused', null);
}

function isFacebookPostingPaused() {
  const pause = getFacebookPostingPause();
  return !!(pause && pause.paused);
}

function pauseFacebookPosting(reason) {
  const existing = getFacebookPostingPause();
  if (existing?.paused && existing.reason === reason) {
    return;
  }
  setSetting('facebook_posting_paused', {
    paused: true,
    reason: String(reason).slice(0, 500),
    since: new Date().toISOString(),
  });
  auditLog('facebook_posting_paused', { reason: String(reason).slice(0, 200) }, 'system');
  logger.warn('Facebook posting paused — reconnect Page token in admin', { reason: String(reason).slice(0, 200) });
}

function clearFacebookPostingPause() {
  if (getFacebookPostingPause()) {
    setSetting('facebook_posting_paused', null);
    auditLog('facebook_posting_resumed', {}, 'system');
  }
}

module.exports = {
  isFacebookAuthError,
  getFacebookPostingPause,
  isFacebookPostingPaused,
  pauseFacebookPosting,
  clearFacebookPostingPause,
};
