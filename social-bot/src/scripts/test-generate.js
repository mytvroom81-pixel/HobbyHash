require('dotenv').config();
const { migrate } = require('./migrate');
const { gatherDataContext } = require('../events/eventProcessor');
const { collectBlockData } = require('../collectors/blocks');
const { collectMinerData } = require('../collectors/miners');
const { collectSiteStats } = require('../collectors/siteStats');
const { collectDownloadData, collectContentUpdates } = require('../collectors/downloads');
const { generatePost } = require('../generator/postGenerator');
const { resolveMysqlConfig } = require('../db/mysql');
const logger = require('../logger');

async function main() {
  migrate();
  console.log('\n=== HOBC Social Bot — Data Source Report ===\n');

  const mysqlCfg = resolveMysqlConfig();
  console.log('MySQL:', mysqlCfg ? `connected config for ${mysqlCfg.database}@${mysqlCfg.host}` : 'NOT configured');

  const [blocks, miners, site, downloads, content] = await Promise.all([
    collectBlockData(),
    collectMinerData(),
    collectSiteStats(),
    collectDownloadData(),
    collectContentUpdates(),
  ]);

  console.log('\n--- Blocks ---');
  console.log(JSON.stringify({
    height: blocks.height,
    difficulty: blocks.difficulty,
    blockReward: blocks.blockReward,
    bestBlockHash: blocks.bestBlockHash ? `${String(blocks.bestBlockHash).slice(0, 16)}…` : null,
    poolBlocks: blocks.poolBlocksFound?.length || 0,
    newPoolBlock: blocks.newPoolBlock,
    sources: blocks.sources,
  }, null, 2));

  console.log('\n--- Miners ---');
  console.log(JSON.stringify({
    totalMiners: miners.totalMiners,
    totalWorkers: miners.totalWorkers,
    hashrate: miners.main.hashrate,
    bestShare: miners.main.bestShare,
    topMiner: miners.main.topMiner,
    sources: miners.sources,
  }, null, 2));

  console.log('\n--- Site stats (aggregates only, no PII) ---');
  console.log(JSON.stringify({
    humanPageviews: site.humanPageviews,
    uniqueVisitors: site.uniqueVisitors,
    dailyPageviews: site.dailyPageviews,
    topRoutes: site.topRoutes,
    sources: site.sources,
  }, null, 2));

  console.log('\n--- Downloads / content ---');
  console.log(JSON.stringify({
    publishedDownloads: downloads.latest?.length || 0,
    walletReleases: downloads.walletReleases?.length || 0,
    nodeReleases: downloads.nodeReleases?.length || 0,
    docs: content.docs?.slice(0, 3).map((d) => d.title),
    announcements: content.announcements?.slice(0, 3).map((a) => a.title),
    pdfAssets: content.pdfUpdates?.map((p) => p.label),
    sources: [...new Set([...(downloads.sources || []), ...(content.sources || [])])],
  }, null, 2));

  const ctx = await gatherDataContext();
  console.log('\n--- Generated post samples ---');
  for (const platform of ['discord', 'x', 'facebook']) {
    const post = await generatePost({ platform, dataContext: ctx });
    console.log(`\n[${platform}] topic=${post.topic} tone=${post.tone}`);
    console.log(post.content);
  }

  console.log('\nDone. Dry-run mode is ON by default — nothing was published.\n');
}

main().catch((err) => {
  logger.error('test-generate failed', { error: err.message });
  console.error(err);
  process.exit(1);
});
