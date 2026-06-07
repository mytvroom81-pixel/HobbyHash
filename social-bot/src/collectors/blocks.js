const fs = require('fs');
const { execFile } = require('child_process');
const { promisify } = require('util');
const axios = require('axios');
const config = require('../config');
const logger = require('../logger');
const { query } = require('../db/mysql');
const { redactWorkerName } = require('../utils/privacy');
const { getSetting, setSetting } = require('../db');

const execFileAsync = promisify(execFile);

async function fetchApi(apiPath) {
  try {
    const url = `${config.apiBase.replace(/\/$/, '')}${apiPath}`;
    const res = await axios.get(url, { timeout: 10000, validateStatus: () => true });
    if (res.status >= 200 && res.status < 300) return res.data;
    logger.warn('API fetch non-OK status', { path: apiPath, status: res.status });
    return null;
  } catch (err) {
    logger.warn('API fetch failed', { path: apiPath, error: err.message });
    return null;
  }
}

function readJsonFile(filePath) {
  try {
    if (!filePath || !fs.existsSync(filePath)) {
      logger.warn('JSON file not found', { filePath });
      return null;
    }
    return JSON.parse(fs.readFileSync(filePath, 'utf8'));
  } catch (err) {
    logger.warn('JSON file read failed', { filePath, error: err.message });
    return null;
  }
}

async function runCli(args) {
  try {
    const cmdArgs = [];
    if (config.hobbyhashCliConf) cmdArgs.push(`-conf=${config.hobbyhashCliConf}`);
    cmdArgs.push(...args);
    const { stdout } = await execFileAsync(config.hobbyhashCli, cmdArgs, {
      timeout: 12000,
      maxBuffer: 1024 * 1024,
    });
    return JSON.parse(stdout);
  } catch (err) {
    logger.warn('hobbyhash-cli failed', { args, error: err.message });
    return null;
  }
}

function mergePoolBlocks(poolJson, payoutState) {
  const fromJson = poolJson?.blocks_found || [];
  const fromPayout = [];
  for (const row of [...(payoutState?.candidates || []), ...(payoutState?.paid || [])]) {
    fromPayout.push({
      height: row.height,
      hash: row.blockhash || row.hash || 'pending',
      status: row.status,
      time: row.seen_at || row.paid_at,
      workername: row.workername,
      reward: row.reward,
      winner_address: row.winner_address,
    });
  }
  const byHeight = new Map();
  for (const b of [...fromJson, ...fromPayout]) {
    if (!b?.height) continue;
    const h = Number(b.height);
    if (!byHeight.has(h) || (b.hash && b.hash !== 'not_available')) {
      byHeight.set(h, b);
    }
  }
  return [...byHeight.values()].sort((a, b) => Number(b.height) - Number(a.height));
}

async function collectBlockData() {
  const result = {
    height: null,
    difficulty: null,
    bestBlockHash: null,
    syncStatus: null,
    blockReward: null,
    latestBlocks: [],
    poolBlocksFound: [],
    newPoolBlock: null,
    sources: [],
  };

  const chainStatus = await fetchApi('/chain/status/');
  if (chainStatus) {
    result.sources.push('api:chain/status');
    result.height = chainStatus.blocks ?? chainStatus.height ?? chainStatus.chain_height ?? null;
    result.difficulty = chainStatus.difficulty ?? chainStatus.network_difficulty ?? null;
    result.bestBlockHash = chainStatus.bestblockhash ?? chainStatus.best_block_hash ?? null;
    result.syncStatus = chainStatus.status ?? chainStatus.sync_status ?? null;
    result.blockReward = chainStatus.block_reward ?? null;
  }

  const supply = await fetchApi('/chain/supply/');
  if (supply?.current_height && !result.height) {
    result.height = supply.current_height;
    result.sources.push('api:chain/supply');
  }

  const latestBlocks = await fetchApi('/chain/latest-blocks/');
  if (latestBlocks?.blocks?.length) {
    result.latestBlocks = latestBlocks.blocks;
    result.sources.push('api:chain/latest-blocks');
    if (!result.height) result.height = latestBlocks.blocks[0]?.height;
    if (!result.bestBlockHash) result.bestBlockHash = latestBlocks.blocks[0]?.hash;
  }

  const poolMain = readJsonFile(config.poolStatsMain);
  const payoutMain = readJsonFile(config.payoutStateMain);
  if (poolMain || payoutMain) {
    result.sources.push('file:pool-stats-main');
    result.poolBlocksFound = mergePoolBlocks(poolMain, payoutMain);
    if (!result.height && poolMain?.chain_height) result.height = poolMain.chain_height;
    if (!result.blockReward && poolMain?.block_reward) result.blockReward = poolMain.block_reward;
  }

  const poolStatus = await fetchApi('/pool/main/status/');
  if (poolStatus) {
    result.sources.push('api:pool/main/status');
    if (!result.height && poolStatus.chain_height) result.height = poolStatus.chain_height;
    if (!result.difficulty && poolStatus.network_difficulty) result.difficulty = poolStatus.network_difficulty;
    if (!result.blockReward && poolStatus.block_reward) result.blockReward = poolStatus.block_reward;
    if (poolStatus.blocks_found?.length && !result.poolBlocksFound.length) {
      result.poolBlocksFound = poolStatus.blocks_found;
    }
    if (poolStatus.last_block) {
      const exists = result.poolBlocksFound.some((b) => Number(b.height) === Number(poolStatus.last_block.height));
      if (!exists) result.poolBlocksFound.unshift(poolStatus.last_block);
    }
  }

  if (!result.height) {
    const cliInfo = await runCli(['getblockchaininfo']);
    if (cliInfo?.blocks) {
      result.sources.push('cli:getblockchaininfo');
      result.height = cliInfo.blocks;
      result.difficulty = cliInfo.difficulty;
      result.bestBlockHash = cliInfo.bestblockhash;
    }
  }

  // Explorer DB when indexed (optional — wallet DB user may lack explorer grants)
  try {
    const explorerRows = await query(`
      SELECT height, hash, block_time, tx_count, difficulty
      FROM hobbyhash_explorer.blocks ORDER BY height DESC LIMIT 10
    `);
    if (explorerRows?.length) {
      result.sources.push('mysql:explorer.blocks');
      if (!result.latestBlocks.length) {
        result.latestBlocks = explorerRows.map((r) => ({
          height: r.height,
          hash: r.hash,
          time: r.block_time,
          tx_count: r.tx_count,
        }));
      }
      if (!result.height) result.height = explorerRows[0].height;
    }
  } catch {
    // explorer not indexed or no DB grant — RPC/API already used
  }

  // Detect newly seen pool block
  if (result.poolBlocksFound.length > 0) {
    const latest = result.poolBlocksFound[0];
    const lastSeen = getSetting('last_seen_pool_block_height', 0);
    const h = Number(latest.height);
    if (h && h > Number(lastSeen)) {
      if (Number(lastSeen) > 0) {
        result.newPoolBlock = {
          height: h,
          hash: latest.hash && latest.hash !== 'not_available' ? latest.hash : null,
          workerLabel: redactWorkerName(latest.workername),
          status: latest.status,
          time: latest.time,
          reward: latest.reward ?? result.blockReward,
          pool: 'main',
        };
      }
      setSetting('last_seen_pool_block_height', h);
    }
  }

  if (!result.blockReward) result.blockReward = 45;

  return result;
}

function checkBlockMilestones(height, recordMilestone) {
  if (!height) return [];
  const milestones = [];
  const h = Number(height);
  for (let t = 500; t <= 100000; t += 500) {
    if (h >= t) {
      const isNew = recordMilestone('block_height', t);
      if (isNew) milestones.push({ type: 'block_height', value: t });
    }
  }
  return milestones;
}

module.exports = {
  collectBlockData,
  checkBlockMilestones,
  fetchApi,
  readJsonFile,
  runCli,
  mergePoolBlocks,
};
