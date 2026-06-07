/**
 * Strip private/sensitive data before posts or logs.
 * Never expose IPs, emails, full wallet addresses, session IDs, or user agents.
 */

function redactWorkerName(name) {
  if (!name || typeof name !== 'string') return 'a pool miner';
  let s = name.trim();
  // Already truncated in pool JSON (hobc1qtv...v4ksey.WORKER)
  if (s.includes('...')) {
    const parts = s.split('.');
    return parts.length > 1 ? `worker ${parts[parts.length - 1]}` : 'a pool miner';
  }
  // Full address — keep last 4 of worker suffix only
  if (s.includes('.')) {
    const worker = s.split('.').pop();
    return worker ? `worker ${worker.slice(0, 12)}` : 'a pool miner';
  }
  if (s.length > 20) {
    return `worker ${s.slice(0, 8)}…`;
  }
  return `worker ${s}`;
}

function redactAddress(addr) {
  if (!addr || typeof addr !== 'string') return null;
  const s = addr.trim();
  if (s.length <= 12) return s;
  return `${s.slice(0, 8)}…${s.slice(-4)}`;
}

function safeLeaderboardEntry(entry) {
  if (!entry) return null;
  return {
    workerLabel: redactWorkerName(entry.workername || entry.worker_name),
    acceptedShares: entry.accepted_shares ?? entry.accepted ?? null,
    bestShare: entry.best_share ?? null,
    rejectPercent: entry.reject_percent ?? null,
  };
}

function stripPrivateFields(obj) {
  const blocked = new Set([
    'ip', 'ip_address', 'ip_hash', 'email', 'user_agent', 'session_id',
    'visitor_id', 'password', 'balance', 'wallet_balance',
  ]);
  const out = {};
  for (const [k, v] of Object.entries(obj || {})) {
    if (blocked.has(k.toLowerCase())) continue;
    out[k] = v;
  }
  return out;
}

module.exports = {
  redactWorkerName,
  redactAddress,
  safeLeaderboardEntry,
  stripPrivateFields,
};
