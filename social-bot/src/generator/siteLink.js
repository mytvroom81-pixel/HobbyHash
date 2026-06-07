const { getSetting, setSetting } = require('../db');
const { getLinkSettings } = require('../config/runtimeConfig');
const { getTrackedSiteUrl, contentHasSiteLink, sanitizeUrlsInContent } = require('./utmLinks');

function shouldIncludeSiteLink() {
  const settings = getLinkSettings();
  if (settings.enabled === false) return false;

  const min = Math.max(1, parseInt(settings.min_posts, 10) || 5);
  const max = Math.max(min, parseInt(settings.max_posts, 10) || 10);
  const target = getSetting('link_target_interval', null);

  let counter = getSetting('link_post_counter', 0);
  counter += 1;

  if (target === null || counter >= target) {
    const nextTarget = min + Math.floor(Math.random() * (max - min + 1));
    setSetting('link_post_counter', 0);
    setSetting('link_target_interval', nextTarget);
    return true;
  }

  setSetting('link_post_counter', counter);
  return false;
}

function appendSiteLink(content, platform) {
  if (contentHasSiteLink(content)) {
    return content;
  }

  const tracked = getTrackedSiteUrl(platform);
  const linkPhrases = [
    `More at ${tracked}`,
    `${tracked}`,
    `Details: ${tracked}`,
  ];
  const phrase = linkPhrases[Math.floor(Math.random() * linkPhrases.length)];

  if (platform === 'x' && (content.length + phrase.length + 2) > 280) {
    return sanitizeUrlsInContent(`${content}\n${tracked}`);
  }

  return sanitizeUrlsInContent(`${content}\n${phrase}`);
}

function applySiteLinkPolicy(content, platform, force = false) {
  if (force || shouldIncludeSiteLink()) {
    return appendSiteLink(content, platform);
  }
  return content;
}

module.exports = {
  shouldIncludeSiteLink,
  appendSiteLink,
  applySiteLinkPolicy,
};
