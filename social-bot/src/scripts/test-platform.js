require('dotenv').config();
const { migrate } = require('./migrate');
const { gatherDataContext } = require('../events/eventProcessor');
const { generatePost } = require('../generator/postGenerator');
const { queuePost } = require('../scheduler');
const { isDryRun } = require('../utils/dryRun');
const { getRuntimeConfig } = require('../config/runtimeConfig');

const ADAPTER_CHECK = {
  discord: () => {
    const d = getRuntimeConfig().discord;
    return d.enabled && (d.webhookUrl || d.botToken);
  },
  x: () => {
    const x = getRuntimeConfig().x;
    const hasUser = x.oauth2AccessToken || ((x.accessToken && x.accessSecret));
    return x.enabled && (x.oauth2ClientId || x.apiKey) && hasUser;
  },
  facebook: () => {
    const f = getRuntimeConfig().facebook;
    return f.enabled && f.pageId && f.pageAccessToken;
  },
};

async function runPlatformTest(platform) {
  migrate();

  if (!ADAPTER_CHECK[platform]?.()) {
    console.error(`Platform "${platform}" is not configured. Set credentials in /social-bot/.env`);
    process.exit(1);
  }

  const live = process.argv.includes('--live');
  const dryRun = isDryRun(!live);

  console.log(`\n=== Test post: ${platform} ===`);
  console.log(`Mode: ${dryRun ? 'DRY-RUN (log only)' : 'LIVE publish'}`);
  if (live) console.log('⚠️  --live flag set — this WILL post publicly.\n');

  const ctx = await gatherDataContext();
  const generated = await generatePost({
    platform,
    dataContext: ctx,
    forceTopic: 'mining_general',
  });

  console.log('Generated content:\n');
  console.log(generated.content);
  console.log('');

  const postId = await queuePost({
    platform,
    content: `[TEST] ${generated.content}`,
    topic: generated.topic,
    tone: generated.tone,
    source: 'test',
    requiresApproval: false,
    forceLive: live,
  });

  console.log(`Post id: ${postId}`);
  if (dryRun) {
    console.log('Dry-run active — check logs/dry-run.log for what would have been posted.');
    console.log('To publish for real: npm run test-' + platform + ' -- --live\n');
  } else {
    console.log('Published (or failed — check admin history).\n');
  }
}

module.exports = { runPlatformTest };
