/**
 * Platform-specific reply formatting.
 * X replies are plain text by default; URLs added only via xBudget when allowed.
 */

function stripLinks(text) {
  if (!text) return '';
  let s = String(text);
  s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/gi, '$1');
  s = s.replace(/https?:\/\/[^\s<>"')\]]+/gi, '');
  s = s.replace(/\bwww\.[^\s<>"')\]]+/gi, '');
  s = s.replace(/\s{2,}/g, ' ').replace(/\s+([.,!?;:])/g, '$1').trim();
  return s;
}

function formatReplyForPlatform(text, platform, options = {}) {
  if (platform !== 'x') {
    return String(text || '').trim();
  }

  const allowLinks = !!options.allowLinks;
  let s = allowLinks ? String(text || '').trim() : stripLinks(text);
  if (s.length > 280) {
    s = s.slice(0, 277).replace(/\s+\S*$/, '') + '…';
  }
  return s;
}

function hasLinks(text) {
  return /https?:\/\/|\bwww\./i.test(String(text || ''));
}

module.exports = {
  stripLinks,
  formatReplyForPlatform,
  hasLinks,
};
