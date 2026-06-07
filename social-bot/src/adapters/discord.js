const axios = require('axios');
const config = require('../config');
const logger = require('../logger');
const { getRuntimeConfig } = require('../config/runtimeConfig');

const DISCORD_API = 'https://discord.com/api/v10';

async function postViaWebhook(content, discord) {
  const url = discord.webhookUrl;
  if (!url) throw new Error('Discord webhook URL not configured');

  const res = await axios.post(url, {
    content,
    username: config.brand.botName,
    allowed_mentions: { parse: [] },
  }, { timeout: 15000 });

  return { id: res.data?.id || 'webhook', platform: 'discord' };
}

async function postViaBot(content, discord) {
  const token = discord.botToken;
  const channel = discord.channelId;
  if (!token || !channel) throw new Error('Discord bot token or channel ID not configured');

  const res = await axios.post(
    `${DISCORD_API}/channels/${channel}/messages`,
    { content, allowed_mentions: { parse: [] } },
    {
      headers: {
        Authorization: `Bot ${token}`,
        'Content-Type': 'application/json',
      },
      timeout: 15000,
    }
  );

  return { id: res.data.id, platform: 'discord' };
}

async function post(content) {
  const { discord } = getRuntimeConfig();
  if (discord.webhookUrl) return postViaWebhook(content, discord);
  return postViaBot(content, discord);
}

async function reply(channelId, messageId, content) {
  const { discord } = getRuntimeConfig();
  const token = discord.botToken;
  if (!token) throw new Error('Discord bot token required for replies');

  const res = await axios.post(
    `${DISCORD_API}/channels/${channelId}/messages`,
    {
      content,
      message_reference: { message_id: messageId, fail_if_not_exists: false },
      allowed_mentions: { parse: [] },
    },
    {
      headers: {
        Authorization: `Bot ${token}`,
        'Content-Type': 'application/json',
      },
      timeout: 15000,
    }
  );

  return { id: res.data.id, platform: 'discord' };
}

function startDiscordListener(onMessage) {
  const { discord } = getRuntimeConfig();
  if (!discord.botToken) {
    logger.info('Discord listener skipped — no bot token');
    return null;
  }

  const lastSeenByChannel = new Map();
  const bootstrapped = new Set();
  const allowedChannels = new Set(
    (discord.replyChannelIds || []).filter(Boolean)
  );

  if (allowedChannels.size === 0) {
    logger.warn('Discord reply listener skipped — no reply channel IDs configured');
    return null;
  }

  const poll = async () => {
    for (const channelId of allowedChannels) {
      try {
        const res = await axios.get(
          `${DISCORD_API}/channels/${channelId}/messages?limit=10`,
          {
            headers: { Authorization: `Bot ${discord.botToken}` },
            timeout: 10000,
          }
        );

        const messages = res.data || [];
        if (!bootstrapped.has(channelId)) {
          const newestUser = messages.find((m) => !m.author?.bot);
          if (newestUser) {
            lastSeenByChannel.set(channelId, newestUser.id);
          }
          bootstrapped.add(channelId);
          logger.info('Discord listener baseline set', { channelId, lastId: newestUser?.id || null });
          continue;
        }

        const lastSeen = lastSeenByChannel.get(channelId);
        const pending = messages
          .filter((m) => !m.author?.bot)
          .filter((m) => !lastSeen || BigInt(m.id) > BigInt(lastSeen))
          .sort((a, b) => (BigInt(a.id) < BigInt(b.id) ? -1 : 1));

        for (const msg of pending) {
          lastSeenByChannel.set(channelId, msg.id);
          await onMessage({
            platform: 'discord',
            channelId,
            messageId: msg.id,
            authorId: msg.author?.id,
            authorName: msg.author?.username,
            text: msg.content,
          });
        }
      } catch (err) {
        logger.warn('Discord poll error', { channelId, error: err.message });
      }
    }
  };

  const interval = setInterval(poll, 30000);
  poll();
  logger.info('Discord reply listener started', { channels: [...allowedChannels] });
  return () => clearInterval(interval);
}

module.exports = {
  post,
  reply,
  startDiscordListener,
};
