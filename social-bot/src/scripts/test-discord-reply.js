#!/usr/bin/env node
require('dotenv').config();
const axios = require('axios');
const { migrate } = require('./migrate');
const { getSetting, getDb } = require('../db');
const { handleIncomingMessage } = require('../replies/replyRouter');
const discordAdapter = require('../adapters/discord');

async function main() {
  migrate();

  const creds = getSetting('platform_credentials', {}).discord || {};
  const token = creds.botToken;
  const channelId = (creds.replyChannelIds || creds.channelId || '').toString().split(',')[0].trim();
  const useArgText = process.argv.includes('--text');
  const testText = useArgText ? process.argv[process.argv.indexOf('--text') + 1] : null;

  if (!token || !channelId) {
    throw new Error('Discord bot token and channel ID are required.');
  }

  const headers = { Authorization: `Bot ${token}` };
  const res = await axios.get(
    `https://discord.com/api/v10/channels/${channelId}/messages?limit=15`,
    { headers, validateStatus: () => true, timeout: 15000 }
  );

  if (res.status !== 200) {
    throw new Error(`Could not read channel messages: ${JSON.stringify(res.data)}`);
  }

  const target = (res.data || []).find((m) => !m.author?.bot);
  if (!target) {
    await discordAdapter.post(
      'Ask a HOBC question in this channel and the bot will reply to your message.'
    );
    console.log('No user message found. Post a question in Discord, then re-run this script.');
    return;
  }

  const incomingText = testText || target.content;
  console.log('User message:', incomingText);

  getDb().prepare('DELETE FROM social_reply_log WHERE platform = ? AND message_id = ?').run('discord', target.id);

  await handleIncomingMessage({
    platform: 'discord',
    channelId,
    messageId: target.id,
    authorId: target.author?.id,
    authorName: target.author?.username,
    text: incomingText,
  });

  console.log('Done — check Discord and admin → Social Bot → Replies.');
}

main().catch((err) => {
  console.error(err.message);
  process.exit(1);
});
