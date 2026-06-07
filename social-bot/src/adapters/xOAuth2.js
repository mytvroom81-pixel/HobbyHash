const crypto = require('crypto');
const axios = require('axios');
const { getSetting, setSetting } = require('../db');

const X_API_BASE = 'https://api.x.com';
const X_AUTHORIZE = 'https://x.com/i/oauth2/authorize';
const X_SCOPES = 'tweet.read tweet.write users.read offline.access';

function base64Url(buf) {
  return buf.toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function generatePkce() {
  const codeVerifier = base64Url(crypto.randomBytes(32));
  const codeChallenge = base64Url(crypto.createHash('sha256').update(codeVerifier).digest());
  const state = base64Url(crypto.randomBytes(16));
  return { codeVerifier, codeChallenge, state };
}

function getOAuth2Cfg(xCfg) {
  return {
    clientId: String(xCfg.oauth2ClientId || '').trim(),
    clientSecret: String(xCfg.oauth2ClientSecret || '').trim(),
    redirectUri: String(xCfg.oauth2RedirectUri || '').trim(),
  };
}

function buildAuthorizeUrl(xCfg, pkce) {
  const { clientId, redirectUri } = getOAuth2Cfg(xCfg);
  if (!clientId || !redirectUri) {
    throw new Error('X OAuth 2.0 client ID and redirect URI are required');
  }

  const params = new URLSearchParams({
    response_type: 'code',
    client_id: clientId,
    redirect_uri: redirectUri,
    scope: X_SCOPES,
    state: pkce.state,
    code_challenge: pkce.codeChallenge,
    code_challenge_method: 'S256',
  });

  return `${X_AUTHORIZE}?${params.toString()}`;
}

async function exchangeCodeForTokens(xCfg, code, codeVerifier) {
  const { clientId, clientSecret, redirectUri } = getOAuth2Cfg(xCfg);
  if (!clientId || !clientSecret || !redirectUri) {
    throw new Error('X OAuth 2.0 client credentials and redirect URI are required');
  }

  const body = new URLSearchParams({
    grant_type: 'authorization_code',
    code: String(code).trim(),
    redirect_uri: redirectUri,
    code_verifier: codeVerifier,
  });

  const basic = Buffer.from(`${clientId}:${clientSecret}`).toString('base64');
  const res = await axios.post(`${X_API_BASE}/2/oauth2/token`, body.toString(), {
    headers: {
      Authorization: `Basic ${basic}`,
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    timeout: 20000,
  });

  return normalizeTokenResponse(res.data);
}

async function refreshAccessToken(xCfg) {
  const refreshToken = String(xCfg.oauth2RefreshToken || '').trim();
  const { clientId, clientSecret } = getOAuth2Cfg(xCfg);
  if (!refreshToken || !clientId || !clientSecret) {
    throw new Error('X OAuth 2.0 refresh token not configured');
  }

  const body = new URLSearchParams({
    grant_type: 'refresh_token',
    refresh_token: refreshToken,
  });

  const basic = Buffer.from(`${clientId}:${clientSecret}`).toString('base64');
  const res = await axios.post(`${X_API_BASE}/2/oauth2/token`, body.toString(), {
    headers: {
      Authorization: `Basic ${basic}`,
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    timeout: 20000,
  });

  return normalizeTokenResponse(res.data);
}

function normalizeTokenResponse(data) {
  const expiresIn = Number(data?.expires_in || 7200);
  return {
    accessToken: data?.access_token || '',
    refreshToken: data?.refresh_token || '',
    expiresAt: Date.now() + (expiresIn * 1000) - 60000,
    tokenType: data?.token_type || 'bearer',
    scope: data?.scope || '',
  };
}

function saveOAuth2Tokens(tokens, actor = 'system') {
  const creds = getSetting('platform_credentials', {});
  const x = creds.x || {};
  x.oauth2AccessToken = tokens.accessToken;
  if (tokens.refreshToken) x.oauth2RefreshToken = tokens.refreshToken;
  x.oauth2ExpiresAt = tokens.expiresAt;
  x.oauth2Scope = tokens.scope;
  creds.x = x;
  setSetting('platform_credentials', creds, actor);
  return x;
}

function storePkceSession(pkce, actor = 'system') {
  setSetting('x_oauth_pkce', {
    state: pkce.state,
    codeVerifier: pkce.codeVerifier,
    expiresAt: Date.now() + (10 * 60 * 1000),
  }, actor);
}

function loadPkceSession(state) {
  const row = getSetting('x_oauth_pkce', null);
  if (!row || row.state !== state) return null;
  if (row.expiresAt && Date.now() > row.expiresAt) return null;
  return row;
}

function clearPkceSession() {
  setSetting('x_oauth_pkce', null, 'system');
}

module.exports = {
  X_SCOPES,
  generatePkce,
  buildAuthorizeUrl,
  exchangeCodeForTokens,
  refreshAccessToken,
  saveOAuth2Tokens,
  storePkceSession,
  loadPkceSession,
  clearPkceSession,
};
