const { getSetting } = require('../db');
const { getSiteUrls } = require('../config/runtimeConfig');

const PLATFORM_UTM_SOURCE = {
  discord: 'discord',
  x: 'twitter',
  facebook: 'facebook',
};

const SITE_URL_PATTERN = /https?:\/\/(?:www\.)?hobbyhashcoin\.com[^\s<>"']*/gi;

function stripTrailingUrlPunctuation(url) {
  if (!url) return url;
  let result = String(url);
  let prev;
  do {
    prev = result;
    result = result.replace(/[.,;:!?'"`\]]+$/g, '');
    result = result.replace(/\)+$/g, '');
  } while (result !== prev);
  return result;
}

function sanitizeUtmParam(value) {
  if (!value) return '';
  return String(value).trim().replace(/^[\(\[]+|[\)\].,;:!?"'`\]]+$/g, '');
}

function sanitizeUrlsInContent(content) {
  if (!content) return content;
  return String(content).replace(SITE_URL_PATTERN, (match) => stripTrailingUrlPunctuation(match));
}

function getUtmSettings() {
  const stored = getSetting('utm_settings', {});
  return {
    enabled: stored.enabled !== false,
    medium: sanitizeUtmParam(stored.medium || 'social') || 'social',
    campaign: sanitizeUtmParam(stored.campaign || 'hobc_update_bot') || 'hobc_update_bot',
    sources: {
      discord: sanitizeUtmParam(stored.sources?.discord || PLATFORM_UTM_SOURCE.discord) || PLATFORM_UTM_SOURCE.discord,
      x: sanitizeUtmParam(stored.sources?.x || PLATFORM_UTM_SOURCE.x) || PLATFORM_UTM_SOURCE.x,
      facebook: sanitizeUtmParam(stored.sources?.facebook || PLATFORM_UTM_SOURCE.facebook) || PLATFORM_UTM_SOURCE.facebook,
    },
  };
}

function getUtmParams(platform) {
  const settings = getUtmSettings();
  const source = settings.sources[platform] || PLATFORM_UTM_SOURCE[platform] || platform;
  return {
    utm_source: source,
    utm_medium: settings.medium,
    utm_campaign: settings.campaign,
  };
}

function withUtmTracking(url, platform) {
  if (!url || !platform) return url;
  url = stripTrailingUrlPunctuation(url);

  const settings = getUtmSettings();
  if (!settings.enabled) return url;

  try {
    const parsed = new URL(url);
    if (!/hobbyhashcoin\.com$/i.test(parsed.hostname.replace(/^www\./i, ''))) {
      return url;
    }
    const params = getUtmParams(platform);
    for (const [key, value] of Object.entries(params)) {
      if (value) parsed.searchParams.set(key, value);
    }
    return parsed.toString();
  } catch {
    return url;
  }
}

function getTrackedSiteUrl(platform) {
  const urls = getSiteUrls();
  const base = (urls.siteUrl || 'https://hobbyhashcoin.com').replace(/\/$/, '');
  return withUtmTracking(base, platform);
}

function contentHasSiteLink(content) {
  if (!content) return false;
  if (/hobbyhashcoin\.com/i.test(content)) return true;
  const urls = getSiteUrls();
  const base = (urls.siteUrl || '').replace(/\/$/, '');
  return base !== '' && content.includes(base);
}

function applyUrlTracking(content, platform) {
  if (!content || !platform) return content;

  const settings = getUtmSettings();
  if (!settings.enabled) return sanitizeUrlsInContent(content);

  const tracked = getTrackedSiteUrl(platform);
  const bareBase = (getSiteUrls().siteUrl || 'https://hobbyhashcoin.com').replace(/\/$/, '');

  let updated = sanitizeUrlsInContent(content);

  updated = updated.replace(SITE_URL_PATTERN, (rawMatch) => {
    const match = stripTrailingUrlPunctuation(rawMatch);
    if (/[?&]utm_/i.test(match)) {
      return match;
    }
    if (match.startsWith(bareBase) || /^https?:\/\/(?:www\.)?hobbyhashcoin\.com/i.test(match)) {
      try {
        const parsed = new URL(match);
        const baseOnly = `${parsed.origin}${parsed.pathname}`.replace(/\/$/, '');
        if (baseOnly === bareBase || baseOnly === 'https://hobbyhashcoin.com') {
          return tracked;
        }
        return withUtmTracking(match, platform);
      } catch {
        return withUtmTracking(match, platform);
      }
    }
    return match;
  });

  if (!updated.includes(tracked) && !/[?&]utm_/i.test(updated) && contentHasSiteLink(updated)) {
    updated = updated.replace(SITE_URL_PATTERN, (rawMatch) => {
      const match = stripTrailingUrlPunctuation(rawMatch);
      return withUtmTracking(match, platform);
    });
  }

  return sanitizeUrlsInContent(updated);
}

module.exports = {
  PLATFORM_UTM_SOURCE,
  getUtmSettings,
  getUtmParams,
  withUtmTracking,
  getTrackedSiteUrl,
  contentHasSiteLink,
  applyUrlTracking,
  stripTrailingUrlPunctuation,
  sanitizeUrlsInContent,
  sanitizeUtmParam,
};
