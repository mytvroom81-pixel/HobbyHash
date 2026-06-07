require('dotenv').config();
const { migrate } = require('./migrate');
const { getDb, setSetting } = require('../db');
const { generatePost } = require('../generator/postGenerator');
const { gatherDataContext } = require('../events/eventProcessor');
const { queuePost } = require('../scheduler');
const logger = require('../logger');

async function testPost() {
  migrate();

  const db = getDb();
  const platformCount = db.prepare('SELECT COUNT(*) AS c FROM social_platform_accounts').get().c;
  if (platformCount === 0) {
    require('./seed');
  }

  setSetting('auto_post_mode', 'approval');

  const ctx = await gatherDataContext();
  console.log('Data context:', ctx);

  const generated = await generatePost({ platform: 'discord', dataContext: ctx });
  console.log('\n--- Generated post preview ---');
  console.log('Topic:', generated.topic);
  console.log('Tone:', generated.tone);
  console.log('Template:', generated.templateName);
  console.log('\n' + generated.content);
  console.log('--- End preview ---\n');

  const postId = await queuePost({
    platform: 'discord',
    content: generated.content,
    topic: generated.topic,
    tone: generated.tone,
    source: 'test',
    requiresApproval: true,
  });

  console.log(`Test post queued (id=${postId}) with status pending.`);
  console.log('Approve it in the admin panel at http://127.0.0.1:' + (process.env.PORT || 3847) + '/admin/queue');
  console.log('Or set DISCORD_ENABLED=true and approve to publish.');
}

testPost().catch((err) => {
  logger.error('test-post failed', { error: err.message, stack: err.stack });
  console.error(err);
  process.exit(1);
});
