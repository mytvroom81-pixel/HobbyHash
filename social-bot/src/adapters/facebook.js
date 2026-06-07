const axios = require('axios');
const logger = require('../logger');
const { getRuntimeConfig } = require('../config/runtimeConfig');
const { getDb } = require('../db');

const FB_API = 'https://graph.facebook.com/v21.0';

function facebookApiError(err) {
  const fb = err.response?.data?.error;
  if (fb?.message) {
    const code = fb.code ? ` (code ${fb.code})` : '';
    return `${fb.message}${code}`;
  }
  return err.message;
}

function commentAlreadyHandled(commentId) {
  if (!commentId) return false;
  const row = getDb().prepare(
    'SELECT id FROM social_reply_log WHERE platform = ? AND message_id = ? LIMIT 1'
  ).get('facebook', commentId);
  return !!row;
}

async function post(content) {
  const { facebook } = getRuntimeConfig();
  const pageId = facebook.pageId;
  const token = facebook.pageAccessToken;
  if (!pageId || !token) {
    throw new Error('Facebook Page ID or access token not configured');
  }

  try {
    const res = await axios.post(
      `${FB_API}/${pageId}/feed`,
      { message: content, access_token: token },
      { timeout: 15000 }
    );
    return { id: res.data?.id, platform: 'facebook' };
  } catch (err) {
    throw new Error(facebookApiError(err));
  }
}

async function getPageComments(since = null) {
  const { facebook } = getRuntimeConfig();
  const pageId = facebook.pageId;
  const token = facebook.pageAccessToken;
  if (!pageId || !token) return [];

  try {
    const res = await axios.get(`${FB_API}/${pageId}/posts`, {
      params: {
        fields: 'id,message,comments.limit(25){id,message,from,created_time}',
        access_token: token,
        limit: 5,
      },
      timeout: 15000,
    });

    const comments = [];
    for (const post of res.data?.data || []) {
      for (const c of post.comments?.data || []) {
        if (since && new Date(c.created_time) <= new Date(since)) continue;
        comments.push({
          postId: post.id,
          commentId: c.id,
          text: c.message,
          authorId: c.from?.id,
          authorName: c.from?.name,
        });
      }
    }
    return comments;
  } catch (err) {
    logger.warn('Facebook comments fetch failed', { error: facebookApiError(err) });
    return [];
  }
}

async function reply(commentId, content) {
  const { facebook } = getRuntimeConfig();
  const token = facebook.pageAccessToken;
  if (!token) throw new Error('Facebook access token not configured');

  const res = await axios.post(
    `${FB_API}/${commentId}/comments`,
    { message: content, access_token: token },
    { timeout: 15000 }
  );

  return { id: res.data?.id, platform: 'facebook' };
}

function startFacebookCommentListener(onComment) {
  const { facebook } = getRuntimeConfig();
  if (!facebook.replyEnabled) {
    logger.info('Facebook reply listener disabled');
    return null;
  }

  if (!facebook.pageId || !facebook.pageAccessToken) {
    logger.warn('Facebook reply listener skipped — missing page ID or token');
    return null;
  }

  const pageId = String(facebook.pageId);

  const poll = async () => {
    const comments = await getPageComments();
    for (const c of comments) {
      if (!c.text || !c.commentId) continue;
      if (String(c.authorId) === pageId) continue;
      if (commentAlreadyHandled(c.commentId)) continue;
      await onComment({
        platform: 'facebook',
        channelId: c.postId,
        messageId: c.commentId,
        authorId: c.authorId,
        authorName: c.authorName,
        text: c.text,
      });
    }
  };

  const interval = setInterval(poll, 60000);
  poll();
  logger.info('Facebook comment listener started', { pageId, intervalSec: 60 });
  return () => clearInterval(interval);
}

module.exports = {
  post,
  reply,
  getPageComments,
  startFacebookCommentListener,
};
