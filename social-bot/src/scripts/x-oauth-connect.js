require('dotenv').config();
const readline = require('readline');
const { migrate } = require('./migrate');
const { getRuntimeConfig } = require('../config/runtimeConfig');
const {
  generatePkce,
  buildAuthorizeUrl,
  exchangeCodeForTokens,
  saveOAuth2Tokens,
  storePkceSession,
  clearPkceSession,
} = require('../adapters/xOAuth2');

function extractCode(input) {
  const raw = String(input || '').trim();
  if (!raw) return '';
  try {
    const url = new URL(raw);
    return url.searchParams.get('code') || '';
  } catch {
    return raw;
  }
}

async function main() {
  migrate();
  const { x } = getRuntimeConfig();
  if (!x.oauth2ClientId || !x.oauth2ClientSecret) {
    console.error('OAuth 2.0 Client ID and Client Secret must be saved in admin first.');
    process.exit(1);
  }
  if (!x.oauth2RedirectUri) {
    console.error('OAuth 2.0 redirect URI is not configured.');
    process.exit(1);
  }

  const pkce = generatePkce();
  storePkceSession(pkce, 'cli');
  const url = buildAuthorizeUrl(x, pkce);

  console.log('\n1) Open this URL while logged into @HobbyHashCoin:\n');
  console.log(url);
  console.log('\n2) After approving, paste the full redirect URL (or just the code):\n');

  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  const answer = await new Promise((resolve) => rl.question('> ', resolve));
  rl.close();

  const code = extractCode(answer);
  if (!code) {
    console.error('No authorization code found.');
    process.exit(1);
  }

  const tokens = await exchangeCodeForTokens(x, code, pkce.codeVerifier);
  saveOAuth2Tokens(tokens, 'cli');
  clearPkceSession();

  console.log('\nOAuth 2.0 connected.');
  console.log('Scopes:', tokens.scope || '(not returned)');
  console.log('Expires:', new Date(tokens.expiresAt).toISOString());
  console.log('\nYou can now publish to X.');
}

main().catch((err) => {
  console.error(err.response?.data?.error_description || err.response?.data?.detail || err.message);
  process.exit(1);
});
