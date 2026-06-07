const { getAiConfig, getSiteUrls } = require('../config/runtimeConfig');
const { BRAND_VOICE, sanitizeBrandText } = require('./brandVoice');
const config = require('../config');

const CONTACT_MARKER = '__CONTACT_ONLY__';

async function generateReplyWithAI(incomingText, meta = {}) {
  const ai = getAiConfig();
  if (!ai.enabled || ai.provider === 'none' || ai.repliesUseAi === false) {
    return null;
  }

  const urls = getSiteUrls();
  const platform = meta.platform || 'social';
  const userMessage = String(incomingText || '').trim();
  const isX = platform === 'x';

  const linkRule = isX
    ? `- Do NOT include any URLs, links, or web addresses (plain text only on X).
- If they need docs, wallet, pool, or support, name the page (e.g. "our docs" or "Contact page") without typing a URL.`
    : `- Link only when helpful: docs ${urls.docsUrl}, pool ${urls.poolUrl}, wallet ${urls.walletUrl}, downloads ${urls.downloadsUrl}, contact ${urls.contactUrl || urls.siteUrl.replace(/\/$/, '') + '/contact/'}.`;

  const systemPrompt = `You are the official ${config.brand.botName} for ${config.brand.name} (${config.brand.ticker}) on ${platform}.

Your job: read the user's exact message and write ONE direct reply to what THEY said — not a generic announcement.

Rules:
- Reply ONLY to the exact tweet text below. Do not invent a question they did not ask.
- If they commented on something (e.g. block height, pool update), respond to THAT comment — not an unrelated FAQ.
- If they asked a specific question, answer that question directly in the first sentence.
- Answer in plain language (2–4 sentences, under ${isX ? '260' : '450'} characters).
- For greetings, thanks, or encouragement about HOBC/mining, reply briefly and warmly — stay on topic.
- Do NOT talk about block height, miners, or pool stats unless they asked about those.
- Only use HOBC official facts you know: SHA-256 mining, 45 HOBC block reward, 84M max supply, web wallet is custodial, standalone wallet is self-custody.
${linkRule}
- Never ask for passwords, seeds, or private keys. No price or investment talk.
- Use ${CONTACT_MARKER} ONLY when: they need account/support help you cannot give, the topic is unrelated to HOBC, you would have to guess, or the message is abusive/spam.
- Output ONLY the reply text (or ${CONTACT_MARKER}). No labels, no quotes around the reply.`;

  const facts = meta.facts
    ? `\nOptional live network stats (use ONLY if their question is about network/pool): ${meta.facts}`
    : '';

  const prompt = `This is the exact tweet you must reply to${meta.authorName ? ` (from @${meta.authorName})` : ''}:\n"""${userMessage}"""\n${facts}\n\nWrite a reply that directly addresses what they said in this tweet. Do not answer a different question.`;

  const raw = await generateWithAI(prompt, {
    systemPrompt,
    force: true,
    temperature: 0.65,
    maxTokens: 280,
  });

  if (!raw) return null;

  const cleaned = raw.trim();
  if (!cleaned || cleaned.includes(CONTACT_MARKER)) {
    return { unsure: true };
  }

  return { reply: sanitizeBrandText(cleaned) };
}

async function generateWithOpenAI(prompt, systemPrompt, options = {}) {
  const ai = getAiConfig();
  if (!ai.openai.apiKey) return null;

  const axios = require('axios');
  const response = await axios.post(
    'https://api.openai.com/v1/chat/completions',
    {
      model: ai.openai.model,
      messages: [
        { role: 'system', content: systemPrompt },
        { role: 'user', content: prompt },
      ],
      max_tokens: options.maxTokens || 220,
      temperature: options.temperature ?? 0.85,
    },
    {
      headers: {
        Authorization: `Bearer ${ai.openai.apiKey}`,
        'Content-Type': 'application/json',
      },
      timeout: 20000,
    }
  );

  return response.data?.choices?.[0]?.message?.content?.trim() || null;
}

async function generateWithAnthropic(prompt, systemPrompt, options = {}) {
  const ai = getAiConfig();
  if (!ai.anthropic.apiKey) return null;

  const axios = require('axios');
  const response = await axios.post(
    'https://api.anthropic.com/v1/messages',
    {
      model: ai.anthropic.model,
      max_tokens: options.maxTokens || 220,
      system: systemPrompt,
      messages: [{ role: 'user', content: prompt }],
    },
    {
      headers: {
        'x-api-key': ai.anthropic.apiKey,
        'anthropic-version': '2023-06-01',
        'Content-Type': 'application/json',
      },
      timeout: 20000,
    }
  );

  const block = response.data?.content?.find((b) => b.type === 'text');
  return block?.text?.trim() || null;
}

function aiIsActive(ai, options = {}) {
  if (options.force) return ai.provider !== 'none';
  return ai.enabled && ai.provider !== 'none';
}

async function generateWithAI(prompt, options = {}) {
  const ai = getAiConfig();
  if (!aiIsActive(ai, options)) return null;

  const systemPrompt = options.systemPrompt || `You are the official ${config.brand.botName} for ${config.brand.name} (${config.brand.ticker}).
Write ONE social media post. Sound human and conversational — not corporate or spammy.
No hype, price talk, or investment language. Max 280 chars when possible.
Use only the facts provided. Do not invent statistics.
Blocked phrases: ${BRAND_VOICE.blockedPhrases.join(', ')}.`;

  try {
    let text = null;
    const genOpts = { temperature: options.temperature, maxTokens: options.maxTokens };
    if (ai.provider === 'openai') {
      text = await generateWithOpenAI(prompt, systemPrompt, genOpts);
    } else if (ai.provider === 'anthropic') {
      text = await generateWithAnthropic(prompt, systemPrompt, genOpts);
    }
    if (!text) return null;
    return sanitizeBrandText(text);
  } catch (err) {
    const logger = require('../logger');
    logger.warn('AI generation failed', { provider: ai.provider, error: err.message });
    return null;
  }
}

module.exports = {
  generateWithAI,
  generateReplyWithAI,
  generateWithOpenAI,
  generateWithAnthropic,
  CONTACT_MARKER,
};
