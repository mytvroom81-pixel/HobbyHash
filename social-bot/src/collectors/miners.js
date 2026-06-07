const fs = require('fs');
const config = require('../config');
const logger = require('../logger');
const { fetchApi, readJsonFile, runCli } = require('./blocks');
const { safeLeaderboardEntry } = require('../utils/privacy');
const { getSetting, setSetting } = require('../db');

function parsePoolStatusFile(filePath) {
  try {
    if (!filePath || !fs.existsSync(filePath)) return null;
    const lines = fs.readFileSync(filePath, 'utf8').trim().split('\n');
    const summary = lines[0] ? JSON.parse(lines[0]) : {};
    const hashrates = lines[1] ? JSON.parse(lines[1]) : {};
    return { summary, hashrates };
  } catch (err) {
    logger.warn('pool.status read failed', { filePath, error: err.message });
    return null;
  }
}

function pickHashrate(api, json, statusFile, window = 'hashrate5m') {
  if (api?.hashrate) return api.hashrate;
  if (api?.hashrate_5m) return api.hashrate_5m;
  const gw = json?.graph_windows?.['5m']?.hashrate_estimate;
  if (gw) return gw;
  if (statusFile?.hashrates?.[window]) return statusFile.hashrates[window];
  if (json?.graph_windows?.['60m']?.hashrate_estimate) return json.graph_windows['60m'].hashrate_estimate;
  return null;
}

function formatHashrate(raw) {
  if (raw === null || raw === undefined) return null;
  const n = Number(raw);
  if (!Number.isFinite(n)) {
    const s = String(raw).trim();
    return s.includes('/') || s.includes('H') ? s : s;
  }
  if (n >= 1e12) return `${(n / 1e12).toFixed(2)} TH/s`;
  if (n >= 1e9) return `${(n / 1e9).toFixed(2)} GH/s`;
  if (n >= 1e6) return `${(n / 1e6).toFixed(2)} MH/s`;
  if (n >= 1e3) return `${(n / 1e3).toFixed(2)} KH/s`;
  return `${n.toFixed(2)} H/s`;
}

async function collectPoolSide(poolName, statsFile, statusFile, apiPath) {
  const side = {
    pool: poolName,
    miners: 0,
    workers: 0,
    activeSessions: 0,
    seenWorkers: 0,
    hashrate: null,
    bestShare: null,
    acceptedShares: 0,
    rejectedShares: 0,
    topMiner: null,
    sources: [],
  };

  const api = await fetchApi(apiPath);
  if (api) {
    side.sources.push(`api:${apiPath}`);
    side.miners = api.users ?? api.miners ?? 0;
    side.workers = api.workers ?? api.active_workers ?? 0;
    side.activeSessions = api.active_sessions ?? 0;
    side.seenWorkers = api.seen_workers ?? 0;
    side.bestShare = api.best_share ?? null;
    side.acceptedShares = api.accepted_shares ?? 0;
    side.rejectedShares = api.rejected_shares ?? 0;
    side.hashrate = formatHashrate(pickHashrate(api, null, null));
    if (api.miner_leaderboard?.[0]) {
      side.topMiner = safeLeaderboardEntry(api.miner_leaderboard[0]);
    }
  }

  const json = readJsonFile(statsFile);
  const status = parsePoolStatusFile(statusFile);
  if (json) {
    side.sources.push(`file:${statsFile}`);
    side.miners = side.miners || json.users || json.active_users || 0;
    side.workers = side.workers || json.workers || json.active_sessions || 0;
    side.activeSessions = side.activeSessions || json.active_sessions || 0;
    side.seenWorkers = side.seenWorkers || json.seen_workers || 0;
    side.bestShare = side.bestShare || json.best_share || null;
    side.acceptedShares = side.acceptedShares || json.accepted_shares || 0;
    side.rejectedShares = side.rejectedShares || json.rejected_shares || 0;
    side.hashrate = side.hashrate || formatHashrate(pickHashrate(null, json, status));
    if (!side.topMiner && json.miner_leaderboard?.[0]) {
      side.topMiner = safeLeaderboardEntry(json.miner_leaderboard[0]);
    }
  }
  if (status?.summary) {
    side.sources.push(`file:${statusFile}`);
    side.miners = side.miners || status.summary.Users || 0;
    side.workers = side.workers || status.summary.Workers || 0;
  }

  return side;
}

async function collectMinerData() {
  const result = {
    main: await collectPoolSide('main', config.poolStatsMain, config.poolStatusMain, '/pool/main/status/'),
    nano: await collectPoolSide('nano', config.poolStatsNano, config.poolStatusNano, '/pool/nano/status/'),
    totalMiners: 0,
    totalWorkers: 0,
    totalActiveSessions: 0,
    bestShareEver: null,
    topMinerUpdate: null,
    sources: [],
  };

  result.totalMiners = (result.main.miners || 0) + (result.nano.miners || 0);
  result.totalWorkers = (result.main.workers || 0) + (result.nano.workers || 0);
  result.totalActiveSessions = (result.main.activeSessions || 0) + (result.nano.activeSessions || 0);
  result.sources = [...new Set([...result.main.sources, ...result.nano.sources])];

  const shares = [result.main.bestShare, result.nano.bestShare].filter(Boolean);
  if (shares.length) result.bestShareEver = Math.max(...shares.map(Number));

  if (!result.main.hashrate) {
    const netInfo = await runCli(['getnetworkinfo']);
    if (netInfo?.networkhashps) {
      result.main.hashrate = formatHashrate(netInfo.networkhashps);
      result.sources.push('cli:getnetworkinfo');
    }
  }

  // Top miner change (privacy-safe label only)
  const leader = result.main.topMiner || result.nano.topMiner;
  if (leader?.workerLabel) {
    const prev = getSetting('last_top_miner_label', '');
    if (prev && prev !== leader.workerLabel) {
      result.topMinerUpdate = leader;
    }
    setSetting('last_top_miner_label', leader.workerLabel);
  }

  return result;
}

function checkMinerMilestones(totalMiners, recordMilestone) {
  const milestones = [];
  for (const t of [1, 5, 10, 25, 50, 100, 250, 500, 1000]) {
    if (totalMiners >= t) {
      const isNew = recordMilestone('miner_count', t);
      if (isNew) milestones.push({ type: 'miner_count', value: t });
    }
  }
  return milestones;
}

function checkHashrateMilestone(hashrate, recordMilestone) {
  if (!hashrate) return [];
  const milestones = [];
  const match = String(hashrate).match(/([\d.]+)\s*(TH|GH|MH)/i);
  if (!match) return milestones;
  let gh = parseFloat(match[1]);
  if (match[2].toUpperCase() === 'TH') gh *= 1000;
  if (match[2].toUpperCase() === 'MH') gh /= 1000;
  for (const t of [0.1, 0.5, 1, 5, 10, 50, 100]) {
    if (gh >= t) {
      const isNew = recordMilestone('hashrate_gh', t);
      if (isNew) milestones.push({ type: 'hashrate_gh', value: t });
    }
  }
  return milestones;
}

function checkDifficultyMilestone(difficulty, recordMilestone) {
  if (!difficulty) return [];
  const milestones = [];
  const d = Number(difficulty);
  if (!Number.isFinite(d)) return milestones;
  for (const t of [1000, 5000, 10000, 50000, 100000]) {
    if (d >= t) {
      const isNew = recordMilestone('difficulty', t);
      if (isNew) milestones.push({ type: 'difficulty', value: t });
    }
  }
  return milestones;
}

module.exports = {
  collectMinerData,
  checkMinerMilestones,
  checkHashrateMilestone,
  checkDifficultyMilestone,
  formatHashrate,
};
