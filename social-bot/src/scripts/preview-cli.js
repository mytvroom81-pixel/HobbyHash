#!/usr/bin/env node
require('dotenv').config();
const { migrate } = require('./migrate');
const { gatherDataContext } = require('../events/eventProcessor');
const { generatePost } = require('../generator/postGenerator');

function withTimeout(promise, ms, label) {
  return Promise.race([
    promise,
    new Promise((_, reject) => {
      setTimeout(() => reject(new Error(`${label} timed out after ${ms}ms`)), ms);
    }),
  ]);
}

async function main() {
  migrate();
  const platform = process.argv[2] || 'discord';
  const topic = process.argv[3] || null;
  const useAi = process.argv[4] === '1';
  const ctx = await withTimeout(gatherDataContext(), 15000, 'Data collection');
  const preview = await withTimeout(
    generatePost({
      platform,
      forceTopic: topic || null,
      dataContext: ctx,
      forceAi: useAi,
      skipSiteLink: true,
    }),
    10000,
    'Post generation'
  );
  process.stdout.write(JSON.stringify({ ok: true, preview }));
}

main().catch((err) => {
  process.stderr.write(JSON.stringify({ ok: false, error: err.message }));
  process.exit(1);
});
