#!/usr/bin/env node
require('dotenv').config();
const { migrate } = require('./migrate');
const { getDb } = require('../db');
const { resolveReply } = require('../replies/resolveReply');
const { handleIncomingMessage } = require('../replies/replyRouter');
const { hasLinks } = require('../replies/formatReply');
const xAdapter = require('../adapters/x');

async function main() {
  migrate();

  const live = process.argv.includes('--live');
  const tweetIdArg = process.argv.find((a, i) => process.argv[i - 1] === '--tweet-id');
  const textOverride = process.argv.includes('--text')
    ? process.argv[process.argv.indexOf('--text') + 1]
    : null;

  let tweetId = tweetIdArg;
  let incomingText = textOverride;
  let authorName = 'TestUser';

  if (tweetId) {
    const tweet = await xAdapter.getTweetById(tweetId);
    if (!tweet) throw new Error(`Could not load tweet ${tweetId}`);
    const botUserId = await xAdapter.getBotUserId();
    if (botUserId && tweet.authorId === botUserId) {
      throw new Error('Refusing to reply to our own tweet. Use a tweet from someone else who @mentioned @HobbyHashCoin.');
    }
    if (!incomingText) {
      incomingText = xAdapter.normalizeMentionText(tweet.text);
      authorName = tweet.authorName || authorName;
    }
  } else {
    const mentions = await xAdapter.getMentions();
    const botUserId = await xAdapter.getBotUserId();
    const candidate = (mentions || []).find((m) => m.author_id !== botUserId);
    if (candidate) {
      tweetId = candidate.id;
      incomingText = xAdapter.normalizeMentionText(candidate.text);
      console.log('Using recent mention tweet id:', tweetId);
    }
  }

  if (!incomingText) {
    incomingText = 'How do I start mining HOBC?';
  }

  console.log('\n=== X reply test ===');
  console.log('Mode:', live ? 'LIVE (will post reply on X)' : 'dry-run (generate only)');
  console.log('Tweet id:', tweetId || '(none)');
  console.log('Incoming (from tweet):', incomingText, '\n');

  const resolved = await resolveReply(incomingText, {
    platform: 'x',
    authorName,
  });

  console.log('Resolved reply:');
  console.log(resolved.reply);
  console.log('\nLength:', resolved.reply.length);
  console.log('Has links:', hasLinks(resolved.reply) ? 'YES (unexpected)' : 'no');
  console.log('Source:', resolved.source, '| Intent:', resolved.intent);

  if (hasLinks(resolved.reply)) {
    console.error('\nFAIL: X reply still contains a link.');
    process.exit(1);
  }

  if (!live) {
    console.log('\nDry-run OK. Mention @HobbyHashCoin on X, then: npm run test-x-reply -- --live');
    return;
  }

  if (!tweetId) {
    throw new Error('No tweet to reply to. Mention @HobbyHashCoin on X, then re-run with --live.');
  }

  getDb().prepare('DELETE FROM social_reply_log WHERE platform = ? AND message_id = ?').run('x', tweetId);

  await handleIncomingMessage({
    platform: 'x',
    channelId: null,
    messageId: tweetId,
    authorId: null,
    authorName: null,
    text: incomingText,
  });

  const row = getDb().prepare('SELECT status, incoming_text, reply_text, external_reply_id FROM social_reply_log WHERE platform = ? AND message_id = ? ORDER BY id DESC LIMIT 1').get('x', tweetId);
  console.log('\nReply log:', row);
  if (row?.status === 'sent' && row.external_reply_id) {
    console.log('Live reply URL: https://x.com/HobbyHashCoin/status/' + row.external_reply_id);
  } else if (row?.status === 'failed') {
    process.exit(1);
  }
}

main().catch((err) => {
  console.error(err.response?.data?.detail || err.message);
  process.exit(1);
});
