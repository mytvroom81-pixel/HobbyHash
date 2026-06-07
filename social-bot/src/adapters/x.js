const {
  recordXReads, recordXMentionPoll, mentionSearchFallbackEnabled, xBudgetBlocksMentionPoll,
} = require('../utils/xBudget');
const crypto = require('crypto');
const axios = require('axios');
const logger = require('../logger');
const { getRuntimeConfig } = require('../config/runtimeConfig');
const { refreshAccessToken, saveOAuth2Tokens } = require('./xOAuth2');

const X_API_BASE = 'https://api.x.com';
const X_API = `${X_API_BASE}/2`;

let cachedUserId = null;
let cachedAppBearer = null;

function normalizeToken(value) {
  if (!value) return '';
  return String(value).trim();
}

function normalizeBearerToken(value) {
  // X returns app-only bearer tokens URL-encoded; do not decode before use.
  return normalizeToken(value);
}

function getXCfg() {
  const { x } = getRuntimeConfig();
  return {
    ...x,
    apiKey: normalizeToken(x.apiKey),
    apiSecret: normalizeToken(x.apiSecret),
    accessToken: normalizeToken(x.accessToken),
    accessSecret: normalizeToken(x.accessSecret),
    bearerToken: normalizeBearerToken(x.bearerToken),
    oauth2ClientId: normalizeToken(x.oauth2ClientId || ''),
    oauth2ClientSecret: normalizeToken(x.oauth2ClientSecret || ''),
    oauth2RedirectUri: normalizeToken(x.oauth2RedirectUri || ''),
    oauth2AccessToken: normalizeToken(x.oauth2AccessToken || ''),
    oauth2RefreshToken: normalizeToken(x.oauth2RefreshToken || ''),
    oauth2ExpiresAt: Number(x.oauth2ExpiresAt || 0),
  };
}

function hasOAuth1UserContext(xCfg) {
  return !!(xCfg.apiKey && xCfg.apiSecret && xCfg.accessToken && xCfg.accessSecret);
}

function hasOAuth2UserContext(xCfg) {
  return !!xCfg.oauth2AccessToken;
}

async function ensureOAuth2AccessToken(xCfg) {
  if (!xCfg.oauth2AccessToken) return xCfg;
  const expiresAt = Number(xCfg.oauth2ExpiresAt || 0);
  if (expiresAt && Date.now() < expiresAt) return xCfg;
  if (!xCfg.oauth2RefreshToken) return xCfg;

  try {
    const tokens = await refreshAccessToken(xCfg);
    saveOAuth2Tokens(tokens, 'system');
    return getXCfg();
  } catch (err) {
    logger.warn('X OAuth 2.0 token refresh failed', {
      error: err.response?.data?.error_description || err.response?.data?.detail || err.message,
    });
    return xCfg;
  }
}

function hasUserContext(xCfg) {
  return hasOAuth1UserContext(xCfg) || hasOAuth2UserContext(xCfg);
}

function oauthHeader(method, url, xCfg, extraParams = {}) {
  const oauth = {
    oauth_consumer_key: xCfg.apiKey,
    oauth_nonce: crypto.randomBytes(16).toString('hex'),
    oauth_signature_method: 'HMAC-SHA1',
    oauth_timestamp: Math.floor(Date.now() / 1000).toString(),
    oauth_token: xCfg.accessToken,
    oauth_version: '1.0',
  };

  const params = { ...oauth, ...extraParams };
  const paramString = Object.keys(params)
    .sort()
    .map((k) => `${encodeURIComponent(k)}=${encodeURIComponent(params[k])}`)
    .join('&');

  const baseString = [
    method.toUpperCase(),
    encodeURIComponent(url),
    encodeURIComponent(paramString),
  ].join('&');

  const signingKey = `${encodeURIComponent(xCfg.apiSecret)}&${encodeURIComponent(xCfg.accessSecret)}`;
  oauth.oauth_signature = crypto
    .createHmac('sha1', signingKey)
    .update(baseString)
    .digest('base64');

  const headerParams = Object.keys(oauth)
    .sort()
    .map((k) => `${encodeURIComponent(k)}="${encodeURIComponent(oauth[k])}"`)
    .join(', ');

  return `OAuth ${headerParams}`;
}

async function getAppBearerToken(xCfg) {
  if (cachedAppBearer) return cachedAppBearer;
  if (!xCfg.apiKey || !xCfg.apiSecret) {
    return normalizeBearerToken(xCfg.bearerToken);
  }

  const basic = Buffer.from(`${xCfg.apiKey}:${xCfg.apiSecret}`).toString('base64');
  const res = await axios.post(
    `${X_API_BASE}/oauth2/token`,
    'grant_type=client_credentials',
    {
      headers: {
        Authorization: `Basic ${basic}`,
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      timeout: 15000,
    }
  );

  cachedAppBearer = normalizeBearerToken(res.data?.access_token || xCfg.bearerToken);
  return cachedAppBearer;
}

async function oauth1Get(path, params = {}) {
  const xCfg = getXCfg();
  if (!hasOAuth1UserContext(xCfg)) {
    throw new Error('X OAuth 1.0a user credentials not configured');
  }

  const url = `${X_API}${path}`;
  const res = await axios.get(url, {
    headers: { Authorization: oauthHeader('GET', url, xCfg, params) },
    params,
    timeout: 15000,
  });
  return res.data;
}

async function oauth1Post(path, body) {
  const xCfg = getXCfg();
  if (!hasOAuth1UserContext(xCfg)) {
    throw new Error('X OAuth 1.0a user credentials not configured');
  }

  const url = `${X_API}${path}`;
  const res = await axios.post(url, body, {
    headers: {
      Authorization: oauthHeader('POST', url, xCfg),
      'Content-Type': 'application/json',
    },
    timeout: 15000,
  });
  return res.data;
}

async function oauth2Post(path, body) {
  let xCfg = await ensureOAuth2AccessToken(getXCfg());
  if (!hasOAuth2UserContext(xCfg)) {
    throw new Error('X OAuth 2.0 user access token not configured');
  }

  const res = await axios.post(`${X_API}${path}`, body, {
    headers: {
      Authorization: `Bearer ${xCfg.oauth2AccessToken}`,
      'Content-Type': 'application/json',
    },
    timeout: 15000,
  });
  return res.data;
}

async function createTweet(body) {
  let xCfg = getXCfg();
  if (hasOAuth2UserContext(xCfg)) {
    xCfg = await ensureOAuth2AccessToken(xCfg);
    const data = await oauth2Post('/tweets', body);
    return { id: data?.data?.id, platform: 'x' };
  }
  if (hasOAuth1UserContext(xCfg)) {
    const data = await oauth1Post('/tweets', body);
    return { id: data?.data?.id, platform: 'x' };
  }
  throw new Error('X user credentials not configured (connect OAuth 2.0 or add OAuth 1.0a access tokens)');
}

async function getAuthenticatedUserId() {
  if (cachedUserId) return cachedUserId;

  let xCfg = getXCfg();
  try {
    if (hasOAuth2UserContext(xCfg)) {
      xCfg = await ensureOAuth2AccessToken(xCfg);
      const res = await axios.get(`${X_API}/users/me`, {
        headers: { Authorization: `Bearer ${xCfg.oauth2AccessToken}` },
        params: { 'user.fields': 'id' },
        timeout: 15000,
      });
      cachedUserId = res.data?.data?.id || null;
      if (cachedUserId) {
        recordXReads('owned_user', ['me'], { source: 'users/me' });
      }
      return cachedUserId;
    }

    if (hasOAuth1UserContext(xCfg)) {
      const data = await oauth1Get('/users/me', { 'user.fields': 'id' });
      cachedUserId = data?.data?.id || null;
      if (cachedUserId) {
        recordXReads('owned_user', ['me'], { source: 'users/me' });
      }
      return cachedUserId;
    }
  } catch (err) {
    const detail = err.response?.data?.detail || err.response?.data?.errors?.[0]?.message || err.message;
    logger.warn('X user authentication failed', { error: detail });
    cachedUserId = null;
  }

  return null;
}

async function post(content) {
  const body = { text: content.slice(0, 280) };
  return createTweet(body);
}

async function getTweetById(tweetId) {
  let xCfg = getXCfg();
  if (!hasOAuth2UserContext(xCfg) && !hasOAuth1UserContext(xCfg)) {
    return null;
  }

  try {
    if (hasOAuth2UserContext(xCfg)) {
      xCfg = await ensureOAuth2AccessToken(xCfg);
    }
    const headers = hasOAuth2UserContext(xCfg)
      ? { Authorization: `Bearer ${xCfg.oauth2AccessToken}` }
      : { Authorization: oauthHeader('GET', `${X_API}/tweets/${tweetId}`, xCfg) };

    const res = await axios.get(`${X_API}/tweets/${tweetId}`, {
      headers,
      params: {
        'tweet.fields': 'author_id,conversation_id,created_at,text',
        expansions: 'author_id',
        'user.fields': 'username,name',
      },
      timeout: 15000,
    });

    const tweet = res.data?.data;
    if (!tweet) return null;

    const users = res.data?.includes?.users || [];
    const author = users.find((u) => u.id === tweet.author_id);

    recordXReads('owned_post', [tweet.id], { source: 'tweets/show' });

    return {
      id: tweet.id,
      text: tweet.text || '',
      authorId: tweet.author_id,
      authorName: author?.username || null,
      conversationId: tweet.conversation_id,
    };
  } catch (err) {
    logger.warn('X tweet fetch failed', { tweetId, error: err.response?.data?.detail || err.message });
    return null;
  }
}

async function getBotUserId() {
  return getAuthenticatedUserId();
}

function normalizeMentionText(text) {
  return String(text || '')
    .replace(/@\w+/g, ' ')
    .replace(/\s{2,}/g, ' ')
    .trim();
}

async function searchIncomingTweets(sinceId, botUserId) {
  let xCfg = getXCfg();
  if (!hasOAuth2UserContext(xCfg) && !hasOAuth1UserContext(xCfg)) {
    return [];
  }

  const query = `(to:HobbyHashCoin OR @HobbyHashCoin) -from:HobbyHashCoin -is:retweet`;
  const params = {
    query,
    'tweet.fields': 'author_id,conversation_id,created_at,text',
    max_results: 10,
  };
  if (sinceId) params.since_id = sinceId;

  try {
    if (hasOAuth2UserContext(xCfg)) {
      xCfg = await ensureOAuth2AccessToken(xCfg);
      const res = await axios.get(`${X_API}/tweets/search/recent`, {
        headers: { Authorization: `Bearer ${xCfg.oauth2AccessToken}` },
        params,
        timeout: 15000,
      });
      return (res.data?.data || []).filter((t) => t.author_id !== botUserId);
    }
  } catch (err) {
    const detail = err.response?.data?.detail || err.response?.data?.title || err.message;
    logger.warn('X search incoming fetch failed', { error: detail });
  }

  return [];
}

async function getMentionsTimeline(sinceId, userId) {
  let xCfg = getXCfg();
  const params = {
    'tweet.fields': 'author_id,conversation_id,created_at,text',
    max_results: 10,
  };
  if (sinceId) params.since_id = sinceId;

  if (hasOAuth2UserContext(xCfg)) {
    xCfg = await ensureOAuth2AccessToken(xCfg);
    const res = await axios.get(`${X_API}/users/${userId}/mentions`, {
      headers: { Authorization: `Bearer ${xCfg.oauth2AccessToken}` },
      params,
      timeout: 15000,
    });
    return res.data?.data || [];
  }

  if (hasOAuth1UserContext(xCfg)) {
    const data = await oauth1Get(`/users/${userId}/mentions`, params);
    return data?.data || [];
  }

  return [];
}

async function getMentions(sinceId = null) {
  const empty = [];
  empty.timelineCount = 0;
  empty.searchCount = 0;

  let xCfg = getXCfg();
  if (!hasUserContext(xCfg)) {
    logger.warn('X user-context credentials required for mention polling (access token + secret)');
    return empty;
  }

  const botUserId = await getAuthenticatedUserId();
  if (!botUserId) {
    logger.warn('X could not resolve authenticated user id for mentions');
    return empty;
  }

  try {
    const seen = new Set();
    const merged = [];

    const add = (tweet) => {
      if (!tweet?.id || seen.has(tweet.id)) return;
      if (botUserId && tweet.author_id === botUserId) return;
      seen.add(tweet.id);
      merged.push(tweet);
    };

    const timeline = await getMentionsTimeline(sinceId, botUserId);
    recordXReads('owned_post', timeline.map((t) => t.id), { source: 'users/mentions' });
    timeline.forEach(add);

    let searchCount = 0;
    if (mentionSearchFallbackEnabled()) {
      const searched = await searchIncomingTweets(sinceId, botUserId);
      searchCount = searched.length;
      recordXReads('public_post', searched.map((t) => t.id), { source: 'tweets/search/recent' });
      searched.forEach(add);
    }

    merged.sort((a, b) => BigInt(a.id) - BigInt(b.id));
    merged.timelineCount = timeline.length;
    merged.searchCount = searchCount;
    return merged;
  } catch (err) {
    const detail = err.response?.data?.detail || err.response?.data?.title || err.message;
    logger.warn('X mentions fetch failed', { error: detail });
    return empty;
  }
}

async function reply(tweetId, content) {
  const body = {
    text: content.slice(0, 280),
    reply: { in_reply_to_tweet_id: tweetId },
  };
  return createTweet(body);
}

async function verifyConnection() {
  const xCfg = getXCfg();
  const result = {
    appOnly: false,
    userContext: false,
    userId: null,
    username: null,
    errors: [],
  };

  try {
    const bearer = await getAppBearerToken(xCfg);
    if (bearer) {
      await axios.get(`${X_API}/tweets/counts/recent`, {
        headers: { Authorization: `Bearer ${bearer}` },
        params: { query: 'hobbyhash', granularity: 'day' },
        timeout: 15000,
      });
      result.appOnly = true;
    }
  } catch (err) {
    result.errors.push(`App-only: ${err.response?.data?.detail || err.message}`);
  }

  try {
    if (hasUserContext(xCfg)) {
      const userId = await getAuthenticatedUserId();
      result.userId = userId;
      result.userContext = !!userId;

      if (hasOAuth1UserContext(xCfg)) {
        const data = await oauth1Get('/users/me', { 'user.fields': 'username' });
        result.username = data?.data?.username || null;
      } else if (hasOAuth2UserContext(xCfg)) {
        const res = await axios.get(`${X_API}/users/me`, {
          headers: { Authorization: `Bearer ${xCfg.oauth2AccessToken}` },
          params: { 'user.fields': 'username' },
          timeout: 15000,
        });
        result.username = res.data?.data?.username || null;
      }
    }
  } catch (err) {
    result.errors.push(`User context: ${err.response?.data?.detail || err.message}`);
  }

  return result;
}

const X_MENTION_POLL_MS = 5 * 60 * 1000;

function startXMentionListener(onMention) {
  const xCfg = getXCfg();
  if (!xCfg.replyEnabled || !hasUserContext(xCfg)) {
    logger.info('X mention listener disabled (needs reply enabled + user access tokens)');
    return null;
  }

  let sinceId = null;
  let baselineSet = false;

  const poll = async () => {
    try {
      if (xBudgetBlocksMentionPoll()) {
        logger.info('X mention poll skipped — daily API budget exhausted');
        recordXMentionPoll({ blocked: true, source: 'users/mentions' });
        return;
      }

      const mentions = await getMentions(sinceId);
      recordXMentionPoll({
        timelineCount: mentions.timelineCount ?? mentions.length,
        searchCount: mentions.searchCount ?? 0,
      });

      if (!baselineSet) {
        if (mentions.length > 0) {
          sinceId = mentions[mentions.length - 1].id;
          logger.info('X listener baseline set', { sinceId, skipped: mentions.length });
        } else {
          logger.info('X listener baseline set (no pending mentions)');
        }
        baselineSet = true;
        return;
      }

      if (mentions.length > 0) {
        logger.info('X incoming tweets detected', { count: mentions.length });
      }

      for (const tweet of mentions) {
        sinceId = tweet.id;
        const botUserId = await getBotUserId();
        if (botUserId && tweet.author_id === botUserId) {
          logger.debug('X mention skipped — own tweet', { tweetId: tweet.id });
          continue;
        }
        await onMention({
          platform: 'x',
          messageId: tweet.id,
          authorId: tweet.author_id,
          text: normalizeMentionText(tweet.text),
        });
      }
    } catch (err) {
      logger.warn('X mention poll failed', { error: err.message });
    }
  };

  const interval = setInterval(poll, X_MENTION_POLL_MS);
  poll();
  logger.info('X mention listener started', { intervalSec: X_MENTION_POLL_MS / 1000 });
  return () => clearInterval(interval);
}

module.exports = {
  post,
  reply,
  getMentions,
  getTweetById,
  getBotUserId,
  normalizeMentionText,
  verifyConnection,
  startXMentionListener,
  normalizeToken,
};
