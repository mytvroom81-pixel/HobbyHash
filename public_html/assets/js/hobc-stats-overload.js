(function () {
  'use strict';

  function hobcI18n(key, fallback, vars) {
    const bag = (window.HOBC_I18N && window.HOBC_I18N.strings) || {};
    let text = Object.prototype.hasOwnProperty.call(bag, key) ? bag[key] : (fallback || key);
    if (vars && typeof vars === 'object') {
      Object.keys(vars).forEach((name) => {
        text = String(text).split('{' + name + '}').join(String(vars[name]));
      });
    }
    return text;
  }

  const root = document.querySelector('[data-hobc-stats-module]');
  if (!root) return;

  const TZ = 'America/Los_Angeles';
  const DIFF1_HASHES = 4294967296;
  const API_URL = root.getAttribute('data-api-url') || '/api/pool/main/overload/';
  const LS_PREFIX = `hobc_stats_overload_${(location.pathname + '_' + location.search).replace(/[^a-z0-9]/gi, '_')}`;
  const LS_LAST_SHARE_KEYS = `${LS_PREFIX}_last_share_keys`;
  const LS_LAST_BLOCK_COUNT = `${LS_PREFIX}_last_block_count`;
  const LS_CHART_HISTORY = `${LS_PREFIX}_chart_history`;
  let MODEL = null;
  let reloadInFlight = false;
  let chartHistory = loadChartHistory();
  let lastChartPointKey = '';
  let ageTickTimer = null;

  function qs(id) { return document.getElementById(id); }
  function esc(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c]));
  }
  function num(value, fallback = 0) {
    const n = Number(value);
    return Number.isFinite(n) ? n : fallback;
  }
  function fmtNumber(value, decimals = 2) {
    return num(value, 0).toLocaleString(undefined, { maximumFractionDigits: decimals, minimumFractionDigits: decimals });
  }
  function fmtInt(value) {
    return Math.round(num(value, 0)).toLocaleString();
  }
  function fmtPct(value) {
    const n = num(value, 0);
    return `${n.toFixed(n >= 10 ? 1 : 2)}%`;
  }
  function secondsHuman(seconds) {
    let s = Math.max(0, Math.floor(num(seconds, 0)));
    if (s < 60) return `${s}s`;
    const m = Math.floor(s / 60);
    if (m < 60) return `${m}m`;
    const h = Math.floor(m / 60);
    const rem = m % 60;
    if (h < 48) return `${h}h ${rem}m`;
    const d = Math.floor(h / 24);
    return `${d}d ${h % 24}h`;
  }
  function humanBytes(bytes) {
    let value = num(bytes, 0);
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    while (value >= 1024 && i < units.length - 1) {
      value /= 1024;
      i += 1;
    }
    return `${value.toFixed(i ? 2 : 0)} ${units[i]}`;
  }
  function humanHashrate(hs) {
    const value = num(hs, 0);
    if (value <= 0) return '0.00 TH/s';
    const units = [
      { k: 1e18, s: 'EH/s' },
      { k: 1e15, s: 'PH/s' },
      { k: 1e12, s: 'TH/s' },
      { k: 1e9, s: 'GH/s' },
      { k: 1e6, s: 'MH/s' },
      { k: 1e3, s: 'KH/s' }
    ];
    const unit = units.find((row) => value >= row.k);
    return unit ? `${(value / unit.k).toFixed(2)} ${unit.s}` : `${value.toFixed(2)} H/s`;
  }
  function humanDiff(value) {
    const v = num(value, 0);
    if (v <= 0) return '-';
    const units = [{ k: 1e12, s: 'T' }, { k: 1e9, s: 'G' }, { k: 1e6, s: 'M' }, { k: 1e3, s: 'K' }];
    const unit = units.find((row) => v >= row.k);
    return unit ? `${(v / unit.k).toFixed(2)} ${unit.s}` : v.toFixed(2);
  }
  function renderDiffSpan(value) {
    const v = num(value, 0);
    if (v <= 0) return '-';
    let cls = 'hobc-stats-diff-tiny';
    if (v >= 1e12) cls = 'hobc-stats-diff-t';
    else if (v >= 1e9) cls = 'hobc-stats-diff-g';
    else if (v >= 1e6) cls = 'hobc-stats-diff-m';
    else if (v >= 1e3) cls = 'hobc-stats-diff-k';
    return `<span class="${cls}">${humanDiff(v)}</span>`;
  }
  function renderShareDiff(value) {
    const v = num(value, 0);
    if (v <= 0) return '-';
    if (v < 1) return Number(v.toFixed(6)).toString();
    return renderDiffSpan(v);
  }
  function fmtDateLocal(iso) {
    if (!iso) return '-';
    try {
      return new Intl.DateTimeFormat('en-US', {
        timeZone: TZ,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      }).format(new Date(iso)).replace(',', '');
    } catch (error) {
      return String(iso);
    }
  }
  function compactDate(iso) {
    if (!iso) return '-';
    try {
      return new Intl.DateTimeFormat('en-US', { timeZone: TZ, month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(iso)).replace(',', '');
    } catch (error) {
      return String(iso);
    }
  }
  function timeMs(value) {
    if (!value) return 0;
    const parsed = Date.parse(String(value));
    return Number.isFinite(parsed) ? parsed : 0;
  }
  function ageTextFromMs(ms) {
    const t = num(ms, 0);
    return t > 0 ? secondsHuman((Date.now() - t) / 1000) : '-';
  }
  function ageSpan(ms, fallbackSeconds = null) {
    const t = num(ms, 0);
    if (t > 0) return `<span data-hobc-age-ts="${Math.floor(t)}">${ageTextFromMs(t)}</span>`;
    if (fallbackSeconds !== null) {
      const fallbackTs = Date.now() - (num(fallbackSeconds, 0) * 1000);
      return `<span data-hobc-age-ts="${Math.floor(fallbackTs)}">${secondsHuman(fallbackSeconds)}</span>`;
    }
    return '<span>-</span>';
  }
  function updateRelativeAges() {
    const now = Date.now();
    document.querySelectorAll('[data-hobc-age-ts]').forEach((el) => {
      const ts = num(el.getAttribute('data-hobc-age-ts'), 0);
      if (ts > 0) el.textContent = secondsHuman((now - ts) / 1000);
    });
  }
  function shortHashMiddle(hash) {
    const h = String(hash || '');
    if (!h) return '-';
    return h.length <= 30 ? h : `${h.slice(0, 18)}...${h.slice(-10)}`;
  }
  function shortHashTight(hash) {
    const h = String(hash || '');
    if (!h) return '-';
    return h.length <= 20 ? h : `${h.slice(0, 13)}...${h.slice(-6)}`;
  }
  function displayWorker(worker) {
    const text = String(worker || '').trim();
    return text || '-';
  }
  function displayMinerNameOnly(worker) {
    const text = displayWorker(worker);
    const idx = text.lastIndexOf('.');
    const minerName = idx >= 0 ? text.slice(idx + 1).trim() : '';
    return minerName || hobcI18n('pool_stats.miner.none', 'NONE');
  }
  function shareIsAccepted(share) {
    return !!(share && (share.accepted === true || String(share.result || '').toLowerCase().includes('accept')));
  }
  function shareTimeMs(share) {
    if (!share) return 0;
    const raw = share.time || share.timestamp || share.created_at || share.ts || '';
    let t = 0;
    if (raw) {
      const parsed = Date.parse(String(raw));
      if (Number.isFinite(parsed)) t = parsed;
    }
    if (!t && Number.isFinite(Number(share._display_time_ts || share._time_ts || 0))) {
      const v = Number(share._display_time_ts || share._time_ts);
      t = v > 1e12 ? v : v * 1000;
    }
    return t;
  }
  function shareStableKey(share) {
    const hash = String((share && share.hash) || '').trim().toLowerCase();
    if (hash) return `hash:${hash}`;
    return ['nohash', share && share.worker, share && share.time, share && share.diff, share && share.sdiff, share && share.accepted].join('|');
  }
  function getWindow(obj, minutes) {
    if (!obj) return {};
    const key = String(minutes);
    if (obj.windows && obj.windows[key]) return obj.windows[key];
    return {};
  }
  function getRate(obj, minutes) {
    const win = getWindow(obj, minutes);
    return num(win.hashrate_accepted_hs, obj && obj[`hashrate_accepted_${minutes}m_hs`] || 0);
  }
  function getShareRate(obj, minutes) {
    const win = getWindow(obj, minutes);
    const fallback = Number(minutes) === 60 ? num(obj && obj.session_share_rate_per_min, obj && obj.share_rate_per_min || 0) : 0;
    return num(win.share_rate_per_min, fallback);
  }
  function getCoin() {
    return (MODEL && MODEL.coin && MODEL.coin.symbol) || 'HOBC';
  }
  function blockRewardValue(coin) {
    return coin && coin.block_reward != null && coin.block_reward !== '' ? Math.max(0, num(coin.block_reward, 0)) : 0;
  }
  function poolHashrateForOdds() {
    const pool = MODEL.pool || {};
    return getRate(pool, 60) || getRate(pool, 5) || getRate(pool, 720) || num(pool.hashrate_accepted_hs, 0);
  }
  function networkDiff() {
    return num((MODEL.network || {}).difficulty, 0);
  }
  function expectedBlockSeconds(diff, hashrate) {
    const d = num(diff, 0);
    const h = num(hashrate, 0);
    if (d <= 0 || h <= 0) return 0;
    return (d * DIFF1_HASHES) / h;
  }
  function etaHuman(seconds) {
    if (!seconds || seconds <= 0) return '-';
    if (seconds < 3600) return secondsHuman(seconds);
    const days = seconds / 86400;
    if (days < 2) return `${(seconds / 3600).toFixed(1)}h`;
    if (days < 365) return `${days.toFixed(1)}d`;
    return `${(days / 365).toFixed(2)}y`;
  }
  function bestProgressPct(best, diff = networkDiff()) {
    const d = num(diff, 0);
    const b = num(best, 0);
    if (d <= 0 || b <= 0) return 0;
    return Math.min(999999, (b / d) * 100);
  }
  function fmtProgressPct(value) {
    const n = num(value, 0);
    if (n <= 0) return '-';
    if (n < 0.0001) return `${n.toFixed(6).replace(/\.?0+$/, '')}%`;
    if (n < 1) return `${n.toFixed(4)}%`;
    if (n < 100) return `${n.toFixed(2)}%`;
    return `${n.toFixed(1)}%`;
  }
  function renderNetDiffRefPct(value) {
    const text = fmtProgressPct(value);
    if (text === '-' || num(value, 0) < 100) return text;
    return `<span class="hobc-stats-net-diff-hit">${text}</span>`;
  }
  function oddsForPeriod(expectedSec, periodSec) {
    const e = num(expectedSec, 0);
    if (e <= 0) return 0;
    return (1 - Math.exp(-periodSec / e)) * 100;
  }
  function fmtOdds(value) {
    const n = num(value, 0);
    if (n <= 0) return '-';
    if (n < 0.0001) return `${n.toFixed(6).replace(/\.?0+$/, '')}%`;
    if (n < 0.01) return `${n.toFixed(4)}%`;
    if (n < 1) return `${n.toFixed(3)}%`;
    return `${n.toFixed(2)}%`;
  }
  function expectedBlocks(expectedSec, periodSec) {
    const e = num(expectedSec, 0);
    return e > 0 ? periodSec / e : 0;
  }
  function hitQuantileSeconds(expectedSec, quantile) {
    const e = num(expectedSec, 0);
    const q = num(quantile, 0);
    if (e <= 0 || q <= 0 || q >= 1) return 0;
    return -Math.log(1 - q) * e;
  }
  function timesNeeded(best, diff = networkDiff()) {
    const b = num(best, 0);
    const d = num(diff, 0);
    return b > 0 && d > 0 ? d / b : 0;
  }
  function fmtMultiplier(value) {
    const n = num(value, 0);
    if (!n || n <= 0) return '-';
    if (n < 1) return hobcI18n('pool_stats.multiplier.hit_worthy', 'hit-worthy');
    if (n < 1000) return `${n.toFixed(2)}x`;
    if (n < 1e6) return `${(n / 1e3).toFixed(2)}Kx`;
    if (n < 1e9) return `${(n / 1e6).toFixed(2)}Mx`;
    return `${(n / 1e9).toFixed(2)}Bx`;
  }
  function recentShareWindowStats(minutes) {
    const end = Date.now();
    const start = end - minutes * 60 * 1000;
    let best = 0; let accepted = 0; let rejected = 0; let assignedBest = 0;
    for (const share of MODEL.recent_shares || []) {
      const t = shareTimeMs(share);
      if (!t || t < start || t > end + 1000) continue;
      if (shareIsAccepted(share)) accepted += 1;
      else rejected += 1;
      best = Math.max(best, num(share.sdiff || share.diff, 0));
      assignedBest = Math.max(assignedBest, num(share.diff, 0));
    }
    return { best, assignedBest, accepted, rejected, total: accepted + rejected };
  }
  function newestAcceptedShare() {
    return (MODEL.recent_shares || []).find((share) => shareIsAccepted(share)) || null;
  }
  function topMinerPoolSharePct(poolHs) {
    const p = num(poolHs, 0);
    if (p <= 0) return 0;
    let top = 0;
    for (const miner of MODEL.miners || []) {
      const hs = num(miner.session_hashrate_accepted_hs || miner.hashrate_accepted_60m_hs || miner.hashrate_accepted_hs, 0);
      if (hs > top) top = hs;
    }
    return top > 0 ? Math.min(100, (top / p) * 100) : 0;
  }
  function cardHtml(label, value, sub, cls = '', meterPct = null) {
    const meter = meterPct == null ? '' : `<div class="hobc-stats-progress-wrap"><div class="hobc-stats-progress-bar" style="width:${Math.max(0, Math.min(100, num(meterPct, 0)))}%"></div></div>`;
    return `<div class="hobc-stats-odds-card ${cls}"><div class="label">${esc(label)}</div><div class="value">${value}</div><div class="sub">${sub || ''}</div>${meter}</div>`;
  }
  function loadChartHistory() {
    try {
      const raw = localStorage.getItem(LS_CHART_HISTORY);
      const arr = raw ? JSON.parse(raw) : [];
      return Array.isArray(arr) ? arr.filter((p) => p && p.t) : [];
    } catch (error) {
      return [];
    }
  }
  function saveChartHistory(history) {
    try {
      localStorage.setItem(LS_CHART_HISTORY, JSON.stringify(history.slice(-900)));
    } catch (error) {
      // Ignore storage failures; charts can still render current points.
    }
  }
  function addChartPoint(point) {
    if (!point || !point.t) return;
    const key = String(Math.floor(point.t / 5000));
    if (key === lastChartPointKey) return;
    chartHistory.push(point);
    const cut = Date.now() - 13 * 3600 * 1000;
    chartHistory = chartHistory.filter((p) => p.t >= cut).slice(-900);
    lastChartPointKey = key;
    saveChartHistory(chartHistory);
  }
  function buildLivePoint(ts = Date.now()) {
    const pool = MODEL.pool || {};
    const d5 = recentShareWindowStats(5);
    const d30 = recentShareWindowStats(30);
    const d60 = recentShareWindowStats(60);
    const submitted5 = num(getWindow(pool, 5).submitted_rate_per_min, pool.submitted_rate_per_min || 0);
    const submitted60 = num(getWindow(pool, 60).submitted_rate_per_min, pool.submitted_rate_per_min || 0);
    const submittedPressurePct = submitted60 > 0 ? (submitted5 / submitted60) * 100 : (submitted5 > 0 ? 100 : 0);
    const rejectRecentWindowPct = d30.total > 0 ? (d30.rejected / d30.total) * 100 : 0;
    const rejectRecent = Math.max(num(pool.reject_rate_recent_pct, 0), rejectRecentWindowPct);
    const latest = newestAcceptedShare();
    const latestPct = latest ? bestProgressPct(num(latest.sdiff || latest.diff, 0), networkDiff()) : 0;
    const recentBestPct = Math.max(bestProgressPct(d5.best, networkDiff()), latestPct, bestProgressPct(pool.last_share, networkDiff()));
    return {
      t: ts,
      hash5: getRate(pool, 5),
      hash60: getRate(pool, 60),
      hash720: getRate(pool, 720),
      flow5: getShareRate(pool, 5),
      flow60: getShareRate(pool, 60),
      flow720: getShareRate(pool, 720),
      shareDiff5: d5.best,
      shareDiff30: d30.best,
      shareDiff60: d60.best,
      bestPct: bestProgressPct(pool.best_share, networkDiff()),
      recentBestPct,
      activeMiners: num(pool.active_miners, 0),
      activeSessions: num(pool.active_sessions, 0),
      rejectTotal: num(pool.reject_rate_total_pct, 0),
      rejectRecent,
      submittedPressurePct
    };
  }
  function ensureChartHistoryWarm(point) {
    if (!point) return;
    const cutoff = Date.now() - 30 * 60 * 1000;
    if (chartHistory.some((p) => num(p && p.t, 0) >= cutoff)) return;
    for (let i = 30; i >= 1; i -= 1) {
      const at = cutoff + ((30 - i) * 60 * 1000);
      chartHistory.push({ ...point, t: at });
    }
    saveChartHistory(chartHistory);
  }
  function chartTimeLabel(ms) {
    try {
      return new Intl.DateTimeFormat('en-US', { timeZone: TZ, hour: '2-digit', minute: '2-digit' }).format(new Date(ms));
    } catch (error) {
      return '';
    }
  }
  function drawChart(canvasId, seriesDefs, formatter, historyMinutes = 720) {
    const canvas = qs(canvasId);
    if (!canvas) return;
    const rect = canvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    const w = Math.max(320, Math.floor(rect.width || 600));
    const h = Math.max(180, Math.floor(rect.height || 195));
    canvas.width = Math.floor(w * dpr);
    canvas.height = Math.floor(h * dpr);
    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, w, h);
    const padL = 58; const padR = 14; const padT = 20; const padB = 30;
    const plotW = Math.max(10, w - padL - padR);
    const plotH = Math.max(10, h - padT - padB);
    const start = Date.now() - historyMinutes * 60 * 1000;
    const points = chartHistory.filter((p) => p.t >= start);
    ctx.font = '11px system-ui, Segoe UI, Arial';
    ctx.lineWidth = 1;
    ctx.strokeStyle = 'rgba(246,185,40,.20)';
    ctx.fillStyle = 'rgba(247,245,237,.76)';
    if (points.length < 2) {
      ctx.fillText(hobcI18n('pool_stats.chart.waiting', 'Waiting for live points...'), padL, padT + 22);
      return;
    }
    let vals = [];
    for (const p of points) {
      for (const def of seriesDefs) vals.push(num(p[def.key], 0));
    }
    let min = Math.min(...vals);
    let max = Math.max(...vals);
    if (!Number.isFinite(min) || !Number.isFinite(max)) { min = 0; max = 1; }
    if (max === min) { max += 1; min = Math.max(0, min - 1); }
    if (min > 0) min = 0;
    for (let i = 0; i <= 4; i += 1) {
      const y = padT + plotH * (i / 4);
      ctx.beginPath();
      ctx.moveTo(padL, y);
      ctx.lineTo(padL + plotW, y);
      ctx.stroke();
      const val = max - ((max - min) * (i / 4));
      ctx.fillText(formatter ? formatter(val) : fmtNumber(val), 6, y + 4);
    }
    const firstT = num(points[0].t, 0);
    const lastT = num(points[points.length - 1].t, firstT + 1);
    const span = Math.max(1, lastT - firstT);
    const xFor = (t) => padL + ((num(t, firstT) - firstT) / span) * plotW;
    const yFor = (v) => padT + plotH - ((num(v, 0) - min) / (max - min)) * plotH;
    const palette = ['#f7f5ed', '#20d1a0', '#ffb04a', '#ffd764', '#ff6f6f'];
    seriesDefs.forEach((def, idx) => {
      ctx.beginPath();
      ctx.lineWidth = 2;
      ctx.strokeStyle = def.color || palette[idx % palette.length];
      points.forEach((p, i) => {
        const x = xFor(p.t);
        const y = yFor(p[def.key]);
        if (i === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      });
      ctx.stroke();
      ctx.fillStyle = def.color || palette[idx % palette.length];
      ctx.fillText(def.label, padL + idx * 95, 13);
    });
    ctx.fillStyle = 'rgba(247,245,237,.62)';
    ctx.fillText(chartTimeLabel(firstT), padL, h - 8);
    ctx.textAlign = 'right';
    ctx.fillText(chartTimeLabel(lastT), w - padR, h - 8);
    ctx.textAlign = 'left';
  }
  function setBrowserRefresh(label, good = true) {
    const el = qs('hobcStatsBrowserRefresh');
    if (!el) return;
    el.textContent = label;
    el.className = good ? 'hobc-stats-good' : 'hobc-stats-warn';
  }
  function modelSignature(data) {
    try {
      return JSON.stringify([data.generated_at, data.pool && data.pool.accepted_total, data.pool && data.pool.rejected_total, data.pool && data.pool.best_share, (data.recent_shares || [])[0] && ((data.recent_shares || [])[0].time + '|' + ((data.recent_shares || [])[0].hash || '')), data.miners && data.miners.length]);
    } catch (error) {
      return String(Date.now());
    }
  }
  function modelHasUsefulStats(data) {
    if (!data || typeof data !== 'object') return false;
    if (Array.isArray(data.miners) && data.miners.length > 0) return true;
    if (Array.isArray(data.recent_shares) && data.recent_shares.length > 0) return true;
    const pool = data.pool || {};
    return !!(pool.accepted_total || pool.rejected_total || pool.best_share || pool.hashrate_accepted_hs || pool.hashrate_accepted_60m_hs);
  }
  function renderKpis() {
    const pool = MODEL.pool || {};
    const net = MODEL.network || {};
    const coin = MODEL.coin || {};
    const hs = poolHashrateForOdds();
    const eta = expectedBlockSeconds(networkDiff(), hs);
    const sync = net.initialblockdownload === true ? hobcI18n('pool_stats.node.syncing', 'Syncing') : (net.ok === false ? hobcI18n('pool_stats.node.issue', 'Node Issue') : hobcI18n('pool_stats.node.ready', 'Ready'));
    qs('hobcStatsCollector').textContent = MODEL.stats_version || 'unknown';
    qs('hobcStatsSessionGapText').textContent = secondsHuman(num(MODEL.session_gap_seconds, 10800));
    const cards = [
      [hobcI18n('pool_stats.kpi.pool_hashrate', 'Pool Hashrate'), humanHashrate(getRate(pool, 60) || pool.hashrate_accepted_hs), hobcI18n('pool_stats.kpi.pool_hashrate.sub', '60m accepted estimate'), 'goodish'],
      [hobcI18n('pool_stats.kpi.hashrate_5m', '5m Hashrate'), humanHashrate(getRate(pool, 5)), hobcI18n('pool_stats.kpi.hashrate_5m.sub', 'short window'), ''],
      [hobcI18n('pool_stats.kpi.hashrate_12h', '12h Hashrate'), humanHashrate(getRate(pool, 720)), hobcI18n('pool_stats.kpi.hashrate_12h.sub', 'long window'), ''],
      [hobcI18n('pool_stats.kpi.time_to_hit', 'Time To Hit'), etaHuman(eta), hobcI18n('pool_stats.kpi.time_to_hit.sub', 'average at current hashrate'), 'warnish'],
      [hobcI18n('pool_stats.kpi.odds_24h', 'Odds 24h'), fmtOdds(oddsForPeriod(eta, 86400)), hobcI18n('pool_stats.kpi.odds_7d', '7d {odds}', { odds: fmtOdds(oddsForPeriod(eta, 604800)) }), 'goldish'],
      [hobcI18n('pool_stats.kpi.active_miners', 'Active Miners'), `${fmtInt(pool.active_miners || 0)} / ${fmtInt(pool.miner_count || 0)}`, hobcI18n('pool_stats.kpi.active_miners.sub', 'active / seen'), ''],
      [hobcI18n('pool_stats.kpi.active_sessions', 'Active Sessions'), fmtInt(pool.active_sessions || 0), hobcI18n('pool_stats.kpi.active_sessions.sub', 'miners in fresh session'), ''],
      [hobcI18n('pool_stats.kpi.best_share', 'Best Share'), renderDiffSpan(pool.best_share), hobcI18n('pool_stats.kpi.best_share.sub', 'all miners · {progress}', { progress: fmtProgressPct(bestProgressPct(pool.best_share)) }), 'warnish'],
      [hobcI18n('pool_stats.kpi.last_share', 'Last Share'), renderDiffSpan(pool.last_share), hobcI18n('pool_stats.kpi.last_share.sub', 'latest submitted hit'), ''],
      [hobcI18n('pool_stats.kpi.shares', 'Shares'), `${fmtInt(pool.accepted_total || 0)} A / ${fmtInt(pool.rejected_total || 0)} R`, hobcI18n('pool_stats.kpi.shares.sub', 'all-time {reject} reject', { reject: fmtPct(pool.reject_rate_total_pct || 0) }), ''],
      [hobcI18n('pool_stats.kpi.recent_reject', 'Recent Reject'), fmtPct(pool.reject_rate_recent_pct || 0), hobcI18n('pool_stats.kpi.recent_reject.sub', 'current rolling window'), ''],
      [hobcI18n('pool_stats.kpi.node', 'Node'), sync, net.height ? hobcI18n('pool_stats.kpi.node.height', 'height {height}', { height: fmtInt(net.height) }) : hobcI18n('pool_stats.kpi.node.sub_waiting', 'waiting'), ''],
      [hobcI18n('pool_stats.kpi.block_reward', 'Block Reward'), blockRewardValue(coin) > 0 ? `${blockRewardValue(coin)} ${getCoin()}` : '-', hobcI18n('pool_stats.kpi.block_reward.sub', 'before pool fee'), 'goldish'],
      [hobcI18n('pool_stats.kpi.block_value', 'Block Value'), hobcI18n('status.not_available', 'not_available'), hobcI18n('pool_stats.kpi.block_value.sub', 'real market price unavailable'), 'warnish'],
      [hobcI18n('pool_stats.kpi.expected_blocks_day', 'Expected Blocks / Day'), fmtNumber(expectedBlocks(eta, 86400), 8), hobcI18n('pool_stats.kpi.expected_blocks_month', 'month {blocks}', { blocks: fmtNumber(expectedBlocks(eta, 2592000), 6) }), '']
    ];
    qs('hobcStatsKpiBar').innerHTML = cards.map((c) => `<div class="hobc-stats-kpi ${c[3] || ''}"><div class="hobc-stats-label">${esc(c[0])}</div><div class="hobc-stats-value">${c[1]}</div><div class="hobc-stats-sub">${esc(c[2])}</div></div>`).join('');
  }
  function renderOdds() {
    const pool = MODEL.pool || {};
    const hs = poolHashrateForOdds();
    const diff = networkDiff();
    const eta = expectedBlockSeconds(diff, hs);
    const best = num(pool.best_share, 0);
    const progress = bestProgressPct(best, diff);
    const latest = newestAcceptedShare();
    const latestPct = latest ? bestProgressPct(num(latest.sdiff || latest.diff, 0), diff) : 0;
    const d5 = recentShareWindowStats(5);
    const d30 = recentShareWindowStats(30);
    const d60 = recentShareWindowStats(60);
    const p50 = hitQuantileSeconds(eta, 0.5);
    const p90 = hitQuantileSeconds(eta, 0.9);
    const topShare = topMinerPoolSharePct(hs);
    const active = num(pool.active_miners, pool.miner_count || 0);
    const cards = [
      cardHtml(hobcI18n('pool_stats.odds.expected_time', 'Expected Time To Hit'), etaHuman(eta), hs ? hobcI18n('pool_stats.format.using_hashrate', 'using {hashrate} vs network diff {diff}', { hashrate: humanHashrate(hs), diff: humanDiff(diff) }) : hobcI18n('pool_stats.format.waiting_hashrate', 'waiting for hashrate'), 'goldish', progress),
      cardHtml(hobcI18n('pool_stats.odds.odds_24h', 'Odds Next 24h'), fmtOdds(oddsForPeriod(eta, 86400)), hobcI18n('pool_stats.format.odds_7d_30d', '7d {odds7d} · 30d {odds30d}', { odds7d: fmtOdds(oddsForPeriod(eta, 604800)), odds30d: fmtOdds(oddsForPeriod(eta, 2592000)) }), 'greenish'),
      cardHtml(hobcI18n('pool_stats.odds.odds_1h', 'Odds Next 1h'), fmtOdds(oddsForPeriod(eta, 3600)), hobcI18n('pool_stats.format.odds_6h_12h', '6h {odds6h} · 12h {odds12h}', { odds6h: fmtOdds(oddsForPeriod(eta, 21600)), odds12h: fmtOdds(oddsForPeriod(eta, 43200)) })),
      cardHtml(hobcI18n('pool_stats.odds.median_p50', 'Median Hit Window (P50)'), etaHuman(p50), hobcI18n('pool_stats.format.p90_window', 'P90 {p90} · probability window', { p90: etaHuman(p90) })),
      cardHtml(hobcI18n('pool_stats.odds.top_miner_share', 'Top Miner Share'), topShare ? fmtPct(topShare) : '-', active ? hobcI18n('pool_stats.format.top_miner_active', 'largest miner share of pool hashrate · {active} active', { active: fmtInt(active) }) : hobcI18n('pool_stats.format.waiting_rates', 'waiting for miner rates')),
      cardHtml(hobcI18n('pool_stats.odds.expected_blocks_year', 'Expected Blocks / Year'), fmtNumber(expectedBlocks(eta, 31536000), 6), hobcI18n('pool_stats.format.blocks_per_month', 'per month {blocks}', { blocks: fmtNumber(expectedBlocks(eta, 2592000), 6) })),
      cardHtml(hobcI18n('pool_stats.odds.best_progress', 'Best Share Progress'), fmtProgressPct(progress), hobcI18n('pool_stats.format.best_need_bigger', 'best {best} · need {multiplier} bigger', { best: humanDiff(best), multiplier: fmtMultiplier(timesNeeded(best)) }), 'goldish', progress),
      cardHtml(hobcI18n('pool_stats.odds.latest_vs_network', 'Latest Share vs Network'), fmtProgressPct(latestPct), latest ? hobcI18n('pool_stats.format.latest_share', '{date} · {diff}', { date: compactDate(latest.time), diff: humanDiff(latest.sdiff || latest.diff) }) : hobcI18n('pool_stats.odds.no_accepted_yet', 'no accepted shares yet')),
      cardHtml(hobcI18n('pool_stats.odds.window_best_hits', '5m / 30m / 60m Best Hits'), `${humanDiff(d5.best)} / ${humanDiff(d30.best)} / ${humanDiff(d60.best)}`, hobcI18n('pool_stats.format.shares_count', '{n5} / {n30} / {n60} shares', { n5: fmtInt(d5.total), n30: fmtInt(d30.total), n60: fmtInt(d60.total) })),
      cardHtml(hobcI18n('pool_stats.odds.share_pressure', 'Pool Share Pressure'), `${fmtNumber(getShareRate(pool, 5), 4)} /m`, hobcI18n('pool_stats.format.share_pressure_60m', '{rate} per min over 60m', { rate: fmtNumber(getShareRate(pool, 60), 4) }))
    ];
    qs('hobcStatsOddsBar').innerHTML = cards.join('');
    let note = '';
    if ((MODEL.network || {}).initialblockdownload === true) note = hobcI18n('pool_stats.odds.note.syncing', 'Node is still syncing, so mining jobs and odds may be incomplete until initialblockdownload=false.');
    else if (!hs || !diff) note = hobcI18n('pool_stats.odds.note.waiting', 'Waiting for pool hashrate and network difficulty before odds wake up.');
    else note = hobcI18n('pool_stats.odds.note.active', 'At this hashrate, one statistical block is {eta} on average. Probability is lumpy: the pool can hit early, late, or not for a long stretch. Best share has reached {progress} of the current network difficulty.', { eta: etaHuman(eta), progress: fmtProgressPct(progress) });
    qs('hobcStatsLuckNote').textContent = note;
  }
  function renderHighlights() {
    const miners = (MODEL.miners || []).slice();
    const active = miners.filter((m) => num(m.is_active, 0) || num(m.mining_idle_seconds, 999999) <= num(MODEL.session_gap_seconds, 10800));
    const fastest = active.slice().sort((a, b) => num(b.session_hashrate_accepted_hs, 0) - num(a.session_hashrate_accepted_hs, 0))[0];
    const bestActive = active.slice().sort((a, b) => num(b.best_share, 0) - num(a.best_share, 0))[0];
    const mostActive = active.slice().sort((a, b) => num(b.session_accepted_total, 0) - num(a.session_accepted_total, 0))[0];
    const allBest = miners.slice().sort((a, b) => num(b.best_share, 0) - num(a.best_share, 0))[0];
    const allGrinder = miners.slice().sort((a, b) => num(b.accepted_total, 0) - num(a.accepted_total, 0))[0];
    const cards = [
      [hobcI18n('pool_stats.highlight.fastest', 'Fastest active'), fastest, fastest ? humanHashrate(fastest.session_hashrate_accepted_hs) : '-', hobcI18n('pool_stats.highlight.sub.session', 'current session')],
      [hobcI18n('pool_stats.highlight.best_hit', 'Best active hit'), bestActive, bestActive ? renderDiffSpan(bestActive.best_share) : '-', bestActive ? hobcI18n('pool_stats.highlight.sub.net_diff', '{pct} of current net diff', { pct: fmtProgressPct(bestProgressPct(bestActive.best_share)) }) : hobcI18n('pool_stats.highlight.sub.share_hit', 'share hit')],
      [hobcI18n('pool_stats.highlight.most_active', 'Most active shares'), mostActive, mostActive ? `${fmtInt(mostActive.session_accepted_total)} A` : '-', hobcI18n('pool_stats.highlight.sub.session', 'current session')],
      [hobcI18n('pool_stats.highlight.highest_ever', 'Highest hit ever'), allBest, allBest ? renderDiffSpan(allBest.best_share) : '-', allBest ? hobcI18n('pool_stats.highlight.sub.net_diff', '{pct} of current net diff', { pct: fmtProgressPct(bestProgressPct(allBest.best_share)) }) : hobcI18n('pool_stats.highlight.sub.alltime', 'all-time best')],
      [hobcI18n('pool_stats.highlight.alltime_grinder', 'All-time grinder'), allGrinder, allGrinder ? `${fmtInt(allGrinder.accepted_total)} A` : '-', hobcI18n('pool_stats.highlight.sub.history', 'saved worker history')],
      [hobcI18n('pool_stats.highlight.clean_active', 'Clean active'), active[0], active[0] ? `${fmtInt(active[0].session_accepted_total)} / ${fmtInt(active[0].session_rejected_total)}` : '-', hobcI18n('pool_stats.highlight.sub.accepted_rejected', 'accepted / rejected')]
    ];
    qs('hobcStatsMinerHighlights').innerHTML = cards.map(([label, miner, metric, sub]) => `<div class="hobc-stats-leader-card"><div class="label">${esc(label)}</div><div class="name">${esc(miner ? displayMinerNameOnly(miner.worker) : '-')}</div><div class="metric">${metric}</div><div class="submetric">${esc(sub)}</div></div>`).join('');
  }
  function renderNetwork() {
    const n = MODEL.network || {};
    const coin = MODEL.coin || {};
    const pool = MODEL.pool || {};
    const progress = n.verificationprogress != null ? `${(num(n.verificationprogress, 0) * 100).toFixed(4)}%` : '-';
    const sync = n.initialblockdownload === true ? `<span class="hobc-stats-warn">${esc(hobcI18n('pool_stats.node.syncing', 'Syncing'))}</span>` : (n.ok === false ? `<span class="hobc-stats-bad">${esc(hobcI18n('pool_stats.node.problem', 'Problem'))}</span>` : `<span class="hobc-stats-good">${esc(hobcI18n('pool_stats.node.ready', 'Ready'))}</span>`);
    const diff = networkDiff();
    const eta = expectedBlockSeconds(diff, poolHashrateForOdds());
    const rows = [
      [hobcI18n('pool_stats.network.coin', 'Coin'), `${coin.name || 'HobbyHash Coin'} ${coin.symbol || 'HOBC'}`, hobcI18n('pool_stats.network.reward', 'reward {reward}', { reward: blockRewardValue(coin) || '-' })],
      [hobcI18n('pool_stats.network.sync', 'Sync'), sync, progress],
      [hobcI18n('pool_stats.network.height', 'Height'), n.height ? fmtInt(n.height) : '-', n.headers ? hobcI18n('pool_stats.network.headers', 'headers {headers}', { headers: fmtInt(n.headers) }) : ''],
      [hobcI18n('pool_stats.network.diff', 'Network Diff'), renderDiffSpan(diff), hobcI18n('pool_stats.network.diff_sub', 'authoritative node diff; only node-accepted blocks count')],
      [hobcI18n('pool_stats.network.hashrate', 'Network Hashrate'), humanHashrate(n.networkhashps), hobcI18n('pool_stats.network.hashrate_sub', 'reported by node')],
      [hobcI18n('pool_stats.network.pool_vs_net', 'Pool vs Network'), n.networkhashps ? fmtProgressPct((poolHashrateForOdds() / num(n.networkhashps, 1)) * 100) : '-', hobcI18n('pool_stats.network.pool_sub', '{hashrate} pool', { hashrate: humanHashrate(poolHashrateForOdds()) })],
      [hobcI18n('pool_stats.network.avg_time', 'Avg Time To Hit'), etaHuman(eta), hobcI18n('pool_stats.network.avg_time_odds', 'odds 24h {odds}', { odds: fmtOdds(oddsForPeriod(eta, 86400)) })],
      [hobcI18n('pool_stats.network.best_progress', 'Best Progress'), fmtProgressPct(bestProgressPct(pool.best_share, diff)), hobcI18n('pool_stats.network.best_need', 'need {multiplier} bigger', { multiplier: fmtMultiplier(timesNeeded(pool.best_share, diff)) })],
      [hobcI18n('pool_stats.network.mempool', 'Mempool'), n.mempool_size != null ? hobcI18n('pool_stats.network.mempool_tx', '{count} tx', { count: fmtInt(n.mempool_size) }) : '-', n.mempool_bytes ? humanBytes(n.mempool_bytes) : ''],
      [hobcI18n('pool_stats.network.chain', 'Chain'), n.chain || '-', n.pruned ? hobcI18n('pool_stats.network.pruned', 'pruned node') : ''],
      [hobcI18n('pool_stats.network.disk', 'Disk'), n.size_on_disk ? humanBytes(n.size_on_disk) : '-', hobcI18n('pool_stats.network.disk_sub', 'node chain size')],
      [hobcI18n('pool_stats.network.best_block', 'Best Block'), n.bestblockhash ? shortHashMiddle(String(n.bestblockhash).replace(/^0+/, '') || '0') : '-', '']
    ];
    qs('hobcStatsNetworkInfoBar').innerHTML = rows.map((r) => `<div class="hobc-stats-info-card"><div class="label">${esc(r[0])}</div><div class="value">${r[1]}</div><div class="sub">${esc(r[2] || '')}</div></div>`).join('');
  }
  function renderMiners() {
    let miners = (MODEL.miners || []).slice();
    const q = (qs('hobcStatsFilter').value || '').toLowerCase();
    if (q) miners = miners.filter((m) => String(m.worker || '').toLowerCase().includes(q));
    const [key, dir] = qs('hobcStatsSort').value.split('|');
    miners.sort((a, b) => {
      const av = num(a[key], 0); const bv = num(b[key], 0);
      return dir === 'asc' ? av - bv : bv - av;
    });
    qs('hobcStatsMinerCount').textContent = miners.length;
    const gap = num(MODEL.session_gap_seconds, 10800);
    const diff = networkDiff();
    const tbody = document.querySelector('#hobcStatsMinerTable tbody');
    tbody.innerHTML = miners.map((m) => {
      const worker = displayWorker(m.worker);
      const idle = num(m.mining_idle_seconds, m.idle_seconds || 0);
      const lastShareMs = timeMs(m.last_share_time || m.last_seen);
      const startedMs = timeMs(m.session_started);
      let status = 'off'; let label = hobcI18n('pool_stats.miner.offline', 'Offline');
      if (num(m.is_active, 0) || idle <= 900) { status = 'on'; label = hobcI18n('pool_stats.miner.mining', 'Mining'); }
      else if (idle < gap) { status = 'idle'; label = hobcI18n('pool_stats.miner.idle', 'Idle {duration}', { duration: secondsHuman(idle) }); }
      else { status = 'off'; label = hobcI18n('pool_stats.miner.session_ended', 'Session ended'); }
      const sessionAR = `<span class="hobc-stats-good">${fmtInt(m.session_accepted_total || 0)}</span> / <span class="hobc-stats-bad">${fmtInt(m.session_rejected_total || 0)}</span>`;
      const allAR = `<span class="hobc-stats-good">${fmtInt(m.accepted_total || 0)}</span> / <span class="hobc-stats-bad">${fmtInt(m.rejected_total || 0)}</span>`;
      const minerHs = num(m.session_hashrate_accepted_hs || m.hashrate_accepted_60m_hs || m.hashrate_accepted_hs, 0);
      const eta = expectedBlockSeconds(diff, minerHs);
      const progress = bestProgressPct(m.best_share, diff);
      const flow60 = getShareRate(m, 60);
      const flowSession = num(m.session_share_rate_per_min, m.share_rate_per_min || 0);
      const flow5Raw = getShareRate(m, 5);
      const flow5 = flow5Raw > 0 ? flow5Raw : (flowSession > 0 ? flowSession : flow60);
      const startedAge = startedMs ? `<div class="hobc-stats-small">${esc(hobcI18n('pool_stats.miner.age_prefix', 'age'))} ${ageSpan(startedMs)}</div>` : '';
      return `<tr>
        <td><span class="hobc-stats-status-dot ${status}"></span>${esc(label)}</td>
        <td><div class="hobc-stats-worker-name">${esc(worker)}</div><div class="hobc-stats-badge-row"><span class="hobc-stats-badge">${esc(hobcI18n('pool_stats.miner.last_prefix', 'last'))} ${ageSpan(lastShareMs, idle)} ${esc(hobcI18n('pool_stats.miner.ago_word', 'ago'))}</span>${m.is_active ? `<span class="hobc-stats-badge hobc-stats-good">${esc(hobcI18n('pool_stats.miner.live', 'live'))}</span>` : `<span class="hobc-stats-badge hobc-stats-warn">${esc(hobcI18n('pool_stats.miner.quiet', 'quiet'))}</span>`}</div></td>
        <td class="hobc-stats-right">${sessionAR}<div class="hobc-stats-small">${esc(hobcI18n('pool_stats.miner.best_prefix', 'best'))} ${renderDiffSpan(m.session_best_share)}</div><span class="hobc-stats-small">${esc(hobcI18n('pool_stats.miner.reject_pct', 'reject {pct}', { pct: fmtPct(m.session_reject_rate_pct || 0) }))}</span></td>
        <td class="hobc-stats-right">${humanHashrate(minerHs)}<div class="hobc-stats-small">${esc(hobcI18n('pool_stats.miner.sh_per_min', '{rate} sh/min', { rate: fmtNumber(m.session_share_rate_per_min || 0, 4) }))}</div></td>
        <td class="hobc-stats-right">${humanHashrate(m.session_hashrate_accepted_5m_hs || m.hashrate_accepted_5m_hs)}</td>
        <td class="hobc-stats-right">${humanHashrate(m.session_hashrate_accepted_60m_hs || m.hashrate_accepted_60m_hs)}</td>
        <td class="hobc-stats-right">${renderDiffSpan(m.last_share)}</td>
        <td class="hobc-stats-right">${renderDiffSpan(m.best_share)}</td>
        <td class="hobc-stats-right">${etaHuman(eta)}<div class="hobc-stats-small">${esc(hobcI18n('pool_stats.miner.avg_solo', 'avg solo'))}</div></td>
        <td class="hobc-stats-right">${fmtOdds(oddsForPeriod(eta, 86400))}<div class="hobc-stats-small">${esc(hobcI18n('pool_stats.miner.odds_7d', '7d {odds}', { odds: fmtOdds(oddsForPeriod(eta, 604800)) }))}</div></td>
        <td class="hobc-stats-right">${fmtProgressPct(progress)}<div class="hobc-stats-progress-wrap"><div class="hobc-stats-progress-bar" style="width:${Math.max(0, Math.min(100, progress))}%"></div></div><div class="hobc-stats-small">${esc(hobcI18n('pool_stats.miner.need', 'need {mult}', { mult: fmtMultiplier(timesNeeded(m.best_share, diff)) }))}</div></td>
        <td class="hobc-stats-right">${allAR}<div class="hobc-stats-small">${esc(hobcI18n('pool_stats.miner.reject_pct', 'reject {pct}', { pct: fmtPct(m.reject_rate_total_pct || 0) }))}</div></td>
        <td class="hobc-stats-right">${fmtNumber(flow5, 4)} /m<div class="hobc-stats-small">${esc(hobcI18n('pool_stats.miner.pace', 'pace {rate} /h', { rate: fmtNumber(flow5 * 60, 2) }))}</div></td>
        <td>${compactDate(m.session_started)}${startedAge}</td>
        <td>${compactDate(m.last_share_time || m.last_seen)}<div class="hobc-stats-small">${ageSpan(lastShareMs, idle)} ${esc(hobcI18n('pool_stats.miner.ago_word', 'ago'))}</div></td>
      </tr>`;
    }).join('');
    updateRelativeAges();
  }
  function renderShares() {
    let shares = (MODEL.recent_shares || []).slice();
    const q = (qs('hobcStatsShareFilter').value || '').toLowerCase();
    if (q) shares = shares.filter((s) => String(s.worker || '').toLowerCase().includes(q));
    const [key, dir] = qs('hobcStatsShareSort').value.split('|');
    shares.sort((a, b) => {
      let av; let bv;
      if (key === 'time') { av = shareTimeMs(a); bv = shareTimeMs(b); }
      else if (key === 'accepted') { av = shareIsAccepted(a) ? 1 : 0; bv = shareIsAccepted(b) ? 1 : 0; }
      else { av = num(a[key], 0); bv = num(b[key], 0); }
      return dir === 'asc' ? av - bv : bv - av;
    });
    const limit = num(qs('hobcStatsShareLimit').value, 20);
    qs('hobcStatsShareCountHdr').textContent = String(Math.min(limit, shares.length));
    qs('hobcStatsCapNote').textContent = (MODEL.recent_shares || []).length >= limit ? '' : hobcI18n('pool_stats.share.collector_count', 'collector has {count} recent shares', { count: (MODEL.recent_shares || []).length });
    const oldKeys = new Set(JSON.parse(localStorage.getItem(LS_LAST_SHARE_KEYS) || '[]'));
    const newKeys = [];
    const diff = networkDiff();
    const tbody = document.querySelector('#hobcStatsShares tbody');
    tbody.innerHTML = shares.slice(0, limit).map((s, i) => {
      const keyS = shareStableKey(s);
      newKeys.push(keyS);
      const isNew = !oldKeys.has(keyS) && oldKeys.size > 0;
      const ok = shareIsAccepted(s);
      const hit = num(s.sdiff || s.diff, 0);
      const assigned = num(s.diff, 0);
      const pctNet = bestProgressPct(hit, diff);
      const luck = assigned > 0 ? (hit / assigned) * 100 : 0;
      const shareMs = shareTimeMs(s);
      const age = shareMs ? ageSpan(shareMs) : '-';
      const rowClasses = [isNew ? 'hobc-stats-flash-new' : '', ok ? 'hobc-stats-share-accepted' : 'hobc-stats-share-rejected'];
      const result = ok ? `<span class="hobc-stats-share-pill accept">${esc(hobcI18n('pool_stats.share.accepted', 'Accepted'))}</span>` : `<span class="hobc-stats-share-pill reject">${esc(hobcI18n('pool_stats.share.rejected', 'Rejected'))}</span>`;
      return `<tr class="${rowClasses.filter(Boolean).join(' ')}" style="--flash-delay:${Math.min(i, 12) * 0.06}s"><td>${age}</td><td>${esc(displayWorker(s.worker || '-'))}</td><td class="hobc-stats-right">${renderShareDiff(hit)}</td><td class="hobc-stats-right">${renderShareDiff(assigned)}</td><td class="hobc-stats-right">${renderNetDiffRefPct(pctNet)}</td><td class="hobc-stats-right">${luck ? `${fmtNumber(luck, 2)}%` : '-'}</td><td>${result}</td><td class="hobc-stats-mono"><span class="hobc-stats-hash" title="${esc(String(s.hash || ''))}">${esc(shortHashTight(s.hash))}</span></td></tr>`;
    }).join('');
    localStorage.setItem(LS_LAST_SHARE_KEYS, JSON.stringify(newKeys.slice(0, 250)));
    updateRelativeAges();
  }
  function renderBlocks() {
    const blocks = (((MODEL.coin || {}).blocks) || []).slice();
    const tbody = document.querySelector('#hobcStatsBlocks tbody');
    tbody.innerHTML = blocks.length ? blocks.slice(0, 20).map((b) => `<tr><td>${fmtDateLocal(b.time)}<div class="hobc-stats-small">${esc(b.status || hobcI18n('status.not_available', 'not_available'))}</div></td><td class="hobc-stats-mono">${b.height ? fmtInt(b.height) : '-'}</td><td class="hobc-stats-mono"><a class="hobc-stats-hash" href="/explorer/?q=${encodeURIComponent(b.hash || '')}">${esc(shortHashMiddle(b.hash))}</a></td><td>${esc(displayWorker(b.worker || '-'))}</td></tr>`).join('') : `<tr><td colspan="4" class="hobc-stats-muted">${esc(hobcI18n('pool_stats.blocks.empty', 'No blocks recorded yet.'))}</td></tr>`;
    const count = blocks.length;
    localStorage.setItem(LS_LAST_BLOCK_COUNT, String(count));
  }
  function addLivePoint() {
    const point = buildLivePoint(Date.now());
    ensureChartHistoryWarm(point);
    addChartPoint(point);
  }
  function renderCharts() {
    drawChart('hobcStatsHashChart', [{ key: 'hash5', label: '5m' }, { key: 'hash60', label: '60m' }, { key: 'hash720', label: '12h' }], humanHashrate, 720);
    drawChart('hobcStatsDiffChart', [{ key: 'shareDiff5', label: '5m' }, { key: 'shareDiff30', label: '30m' }, { key: 'shareDiff60', label: '60m' }], humanDiff, 720);
    drawChart('hobcStatsFlowChart', [{ key: 'flow5', label: '5m' }, { key: 'flow60', label: '60m' }, { key: 'flow720', label: '12h' }], (v) => `${fmtNumber(v, 3)}/m`, 720);
    drawChart('hobcStatsLuckChart', [{ key: 'bestPct', label: hobcI18n('pool_stats.chart.label.best_pct', 'Best %') }, { key: 'recentBestPct', label: hobcI18n('pool_stats.chart.label.recent_pct', 'Recent % (5m)') }], fmtProgressPct, 720);
    drawChart('hobcStatsMinersChart', [{ key: 'activeMiners', label: hobcI18n('pool_stats.chart.label.active_miners', 'Active miners') }, { key: 'activeSessions', label: hobcI18n('pool_stats.chart.label.sessions', 'Sessions') }], (v) => fmtNumber(v, 0), 720);
    drawChart('hobcStatsRejectChart', [{ key: 'rejectRecent', label: hobcI18n('pool_stats.chart.label.reject_recent', 'Recent reject %') }, { key: 'rejectTotal', label: hobcI18n('pool_stats.chart.label.reject_total', 'All reject %') }, { key: 'submittedPressurePct', label: hobcI18n('pool_stats.chart.label.submitted_pressure', 'Submitted pressure %') }], fmtPct, 720);
  }
  function renderStatus() {
    const n = MODEL.network || {};
    const st = qs('hobcStatsStatusText');
    if (n.initialblockdownload === true) { st.textContent = hobcI18n('pool_stats.node.syncing', 'Syncing'); st.parentElement.className = 'hobc-stats-pill warn'; }
    else if (n.ok === false) { st.textContent = hobcI18n('pool_stats.node.issue', 'Node Issue'); st.parentElement.className = 'hobc-stats-pill bad'; }
    else { st.textContent = hobcI18n('pool_stats.status.ok', 'OK'); st.parentElement.className = 'hobc-stats-pill good'; }
  }
  function renderAll() {
    qs('hobcStatsUpdatedLocal').textContent = fmtDateLocal(MODEL.generated_at);
    qs('hobcStatsCoinName').textContent = (MODEL.coin && MODEL.coin.name) || 'HobbyHash Coin';
    qs('hobcStatsCoinSymbol').textContent = (MODEL.coin && MODEL.coin.symbol) || 'HOBC';
    renderStatus();
    renderKpis();
    renderOdds();
    renderHighlights();
    renderNetwork();
    renderMiners();
    addLivePoint();
    renderCharts();
    renderShares();
    renderBlocks();
    updateRelativeAges();
    document.body.setAttribute('data-hobc-stats-dashboard', 'ready');
    window.dispatchEvent(new CustomEvent('hobc:stats-ready'));
    window.dispatchEvent(new CustomEvent('hobc:stats-updated'));
  }
  async function reloadStats() {
    if (reloadInFlight) return;
    reloadInFlight = true;
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 8000);
    try {
      const sep = API_URL.includes('?') ? '&' : '?';
      const res = await fetch(`${API_URL}${sep}ts=${Date.now()}`, { cache: 'no-store', headers: { 'Cache-Control': 'no-cache', Pragma: 'no-cache' }, signal: controller.signal });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const text = await res.text();
      let data;
      try { data = JSON.parse(text); } catch (error) { throw new Error(`Bad JSON from ${API_URL}: ${text.slice(0, 160)}`); }
      if (!Array.isArray(data.miners)) data.miners = [];
      if (!Array.isArray(data.recent_shares)) data.recent_shares = [];
      if (MODEL && !modelHasUsefulStats(data) && modelHasUsefulStats(MODEL)) throw new Error('API returned empty shell, keeping last visible dashboard');
      modelSignature(data);
      MODEL = data;
      renderAll();
      setBrowserRefresh(new Date().toLocaleTimeString(), true);
    } catch (error) {
      console.error('HOBC stats refresh failed', error);
      setBrowserRefresh(hobcI18n('pool_stats.refresh.api_warning_label', 'API warning, still showing last good stats'), false);
      const st = qs('hobcStatsStatusText');
      if (st) { st.textContent = hobcI18n('pool_stats.status.api_warning', 'API warning: keeping last snapshot'); st.parentElement.className = 'hobc-stats-pill warn'; }
      try { if (MODEL) renderCharts(); } catch (_) { /* keep page visible */ }
    } finally {
      clearTimeout(timer);
      reloadInFlight = false;
    }
  }
  function dashboardLooksMissing() {
    if (document.body.getAttribute('data-hobc-stats-dashboard') === 'ready') return false;
    const bodyText = document.body.innerText || '';
    const markers = ['Pool Hashrate', 'Miner Sessions', 'Last 50 Shares', 'Network / Block Info', 'Live Graphs', 'Luck Engine'];
    const hits = markers.filter((m) => bodyText.includes(m)).length;
    const hasUsefulNodes = !!document.querySelector('#hobcStatsKpiBar > *, #hobcStatsMinerTable tbody tr, canvas');
    const height = Math.max(document.body.scrollHeight || 0, document.documentElement.scrollHeight || 0);
    return (hits < 2 && !hasUsefulNodes) || height < 700;
  }
  function recoverIfMissing(reason, delayMs) {
    if (new URLSearchParams(location.search).get('fresh') === 'hobcstats') return;
    setTimeout(() => {
      if (!dashboardLooksMissing()) return;
      try {
        const key = `hobc_stats_recovered_${location.pathname}_${location.search}`;
        if (sessionStorage.getItem(key)) return;
        sessionStorage.setItem(key, reason || 'missing-dashboard');
      } catch (error) {
        // Continue with one recovery attempt even if sessionStorage fails.
      }
      const u = new URL(location.href);
      u.searchParams.set('fresh', 'hobcstats');
      location.replace(u.toString());
    }, delayMs);
  }
  function init() {
    qs('hobcStatsRefresh').addEventListener('click', reloadStats);
    ['hobcStatsSort', 'hobcStatsShareLimit', 'hobcStatsShareSort'].forEach((id) => qs(id).addEventListener('change', () => { if (!MODEL) return; renderMiners(); renderShares(); }));
    ['hobcStatsFilter', 'hobcStatsShareFilter'].forEach((id) => qs(id).addEventListener('input', () => { if (!MODEL) return; renderMiners(); renderShares(); }));
    window.addEventListener('resize', () => { if (MODEL) renderCharts(); });
    window.addEventListener('pageshow', (event) => { if (event && event.persisted) recoverIfMissing('bfcache', 120); });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) recoverIfMissing('visible', 3200); });
    reloadStats();
    setBrowserRefresh(hobcI18n('pool_stats.refresh.loaded', 'loaded'), true);
    setTimeout(reloadStats, 900);
    setInterval(reloadStats, 2000);
    if (!ageTickTimer) ageTickTimer = setInterval(updateRelativeAges, 1000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
