(function () {
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

  const cache = new Map();
  const scrollMemoryKey = 'hobc_next_page_scroll';

  function sameSiteUrl(href) {
    try {
      const url = new URL(href, window.location.href);
      return url.origin === window.location.origin ? url : null;
    } catch (error) {
      return null;
    }
  }

  function saveNextPageScroll() {
    try {
      sessionStorage.setItem(scrollMemoryKey, JSON.stringify({
        x: window.scrollX || 0,
        y: window.scrollY || 0,
        savedAt: Date.now()
      }));
    } catch (error) {
      // Ignore storage failures so navigation never gets blocked.
    }
  }

  function restoreNextPageScroll() {
    let saved = null;
    try {
      saved = JSON.parse(sessionStorage.getItem(scrollMemoryKey) || 'null');
      sessionStorage.removeItem(scrollMemoryKey);
    } catch (error) {
      saved = null;
    }

    if (!saved || typeof saved.y !== 'number' || Date.now() - Number(saved.savedAt || 0) > 30000) {
      return;
    }

    if ('scrollRestoration' in window.history) {
      window.history.scrollRestoration = 'manual';
    }

    let attempts = 0;
    const scrollToSavedSpot = () => {
      const maxY = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
      window.scrollTo(saved.x || 0, Math.min(saved.y, maxY));
      attempts += 1;
      if (attempts < 8 && maxY < saved.y) {
        window.setTimeout(scrollToSavedSpot, 75);
      }
    };

    requestAnimationFrame(scrollToSavedSpot);
  }

  restoreNextPageScroll();

  function titleCaseStatus(value) {
    const map = {
      online: hobcI18n('status.online', 'Online'),
      offline: hobcI18n('status.offline', 'Offline'),
      syncing: hobcI18n('status.syncing', 'Syncing'),
      pending_launch: hobcI18n('status.pending_launch', 'Pending launch'),
      not_available: hobcI18n('status.not_available', 'Not available yet'),
      enabled: hobcI18n('status.enabled', 'Enabled'),
      paused: hobcI18n('status.paused', 'Paused')
    };
    return map[value] || value;
  }

  function getPathValue(data, path) {
    return path.split('.').reduce((value, key) => {
      if (value === null || value === undefined) return undefined;
      if (/^\d+$/.test(key)) return value[Number(key)];
      return value[key];
    }, data);
  }

  function canonicalApiEndpoint(endpoint) {
    const text = String(endpoint || '').trim();
    if (!text.startsWith('/api/')) return text;
    const [path, query = ''] = text.split('?');
    const normalizedPath = path.endsWith('/') ? path : `${path}/`;
    return query ? `${normalizedPath}?${query}` : normalizedPath;
  }

  function displayValue(value, fallback) {
    if (value === null || value === undefined || value === '') return fallback;
    if (typeof value === 'boolean') return value ? hobcI18n('status.yes', 'Yes') : hobcI18n('status.no', 'No');
    if (typeof value === 'number') return new Intl.NumberFormat().format(value);
    if (typeof value === 'object') return fallback;
    return titleCaseStatus(String(value));
  }

  function formatUnixTime(value, fallback) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric) || numeric <= 0) return fallback;
    const millis = numeric > 20000000000 ? numeric : numeric * 1000;
    return new Date(millis).toLocaleString();
  }

  function formatHashrate(value, fallback) {
    const numeric = parseHashrate(value);
    if (!numeric) return displayValue(value, fallback);
    const units = [
      { scale: 1e18, suffix: 'EH/s' },
      { scale: 1e15, suffix: 'PH/s' },
      { scale: 1e12, suffix: 'TH/s' },
      { scale: 1e9, suffix: 'GH/s' },
      { scale: 1e6, suffix: 'MH/s' },
      { scale: 1e3, suffix: 'KH/s' }
    ];
    const unit = units.find((row) => numeric >= row.scale);
    return unit ? `${(numeric / unit.scale).toFixed(2)} ${unit.suffix}` : `${numeric.toFixed(2)} H/s`;
  }

  function explorerLink(value, label) {
    const text = String(value || '').trim();
    if (!text || text === 'not_available') return escapeHtml(label || hobcI18n('status.not_available', 'Not available yet'));
    return `<a href="/explorer/?q=${encodeURIComponent(text)}">${escapeHtml(label || text)}</a>`;
  }

  function formattedApiValue(value, fallback, format, link) {
    let label;
    if (format === 'unix-time') {
      label = formatUnixTime(value, fallback);
    } else if (format === 'hash') {
      label = shortText(value || fallback, 18, 10);
    } else if (format === 'compact-unit') {
      label = compactUnit(numberValue(value) || value || fallback);
    } else if (format === 'hashrate') {
      label = formatHashrate(value, fallback);
    } else {
      label = displayValue(value, fallback);
    }
    if (link === 'explorer') {
      return explorerLink(value, label);
    }
    return escapeHtml(label);
  }

  function updateBrowserTime() {
    const value = new Date().toLocaleString();
    document.querySelectorAll('[data-browser-time]').forEach((el) => { el.textContent = value; });
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function shortText(value, start = 14, end = 10) {
    const text = String(value || '');
    return text.length > start + end + 3 ? `${text.slice(0, start)}...${text.slice(-end)}` : text;
  }

  function numberValue(value) {
    if (typeof value === 'number') return Number.isFinite(value) ? value : 0;
    const text = String(value || '').replace(/,/g, '').trim();
    const match = text.match(/^([0-9]+(?:\.[0-9]+)?)/);
    return match ? Number(match[1]) : 0;
  }

  function parseHashrate(value) {
    if (typeof value === 'number') return value;
    const text = String(value || '').replace(/,/g, '').trim();
    const match = text.match(/^([0-9]+(?:\.[0-9]+)?)\s*([KMGTPE]?)/i);
    if (!match) return 0;
    const scale = { '': 1, K: 1e3, M: 1e6, G: 1e9, T: 1e12, P: 1e15, E: 1e18 };
    return Number(match[1]) * (scale[String(match[2] || '').toUpperCase()] || 1);
  }

  function formatDuration(seconds) {
    const value = Number(seconds);
    if (!Number.isFinite(value) || value <= 0) return 'not_available';
    if (value >= 31536000) return `${(value / 31536000).toFixed(2)}y`;
    if (value >= 86400) return `${(value / 86400).toFixed(2)}d`;
    if (value >= 3600) return `${(value / 3600).toFixed(2)}h`;
    if (value >= 60) return `${(value / 60).toFixed(2)}m`;
    return `${value.toFixed(2)}s`;
  }

  function compactUnit(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return escapeHtml(value || 'not_available');
    const abs = Math.abs(numeric);
    const units = [
      { limit: 1e12, suffix: 'T' },
      { limit: 1e9, suffix: 'G' },
      { limit: 1e6, suffix: 'M' },
      { limit: 1e3, suffix: 'K' }
    ];
    const unit = units.find((row) => abs >= row.limit);
    if (!unit) return escapeHtml(numeric.toFixed(abs >= 100 ? 0 : 2).replace(/\.00$/, ''));
    const scaled = numeric / unit.limit;
    const digits = Math.abs(scaled) >= 100 ? 0 : 2;
    return `${escapeHtml(scaled.toFixed(digits).replace(/\.00$/, ''))} ${unit.suffix}`;
  }

  function compactUnitClass(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return 'pool-unit-na';
    const abs = Math.abs(numeric);
    if (abs >= 1e12) return 'pool-unit-t';
    if (abs >= 1e9) return 'pool-unit-g';
    if (abs >= 1e6) return 'pool-unit-m';
    if (abs >= 1e3) return 'pool-unit-k';
    return 'pool-unit-small';
  }

  function probability(windowSeconds, expectedSeconds) {
    const expected = Number(expectedSeconds);
    if (!Number.isFinite(expected) || expected <= 0) return 'not_available';
    return `${((1 - Math.exp(-Number(windowSeconds) / expected)) * 100).toFixed(6)}%`;
  }

  function lineChart(series, options = {}) {
    const width = 420;
    const height = 190;
    const pad = { left: 46, right: 18, top: 28, bottom: 30 };
    const flat = series.flatMap((row) => row.values).filter((value) => Number.isFinite(value));
    const max = Math.max(...flat, 1);
    const min = Math.min(...flat, 0);
    const span = max - min || 1;
    const count = Math.max(...series.map((row) => row.values.length), 2);
    const xFor = (index) => pad.left + (index / (count - 1)) * (width - pad.left - pad.right);
    const yFor = (value) => pad.top + ((max - value) / span) * (height - pad.top - pad.bottom);
    const grid = [0, 0.25, 0.5, 0.75, 1].map((step) => {
      const y = pad.top + step * (height - pad.top - pad.bottom);
      return `<line x1="${pad.left}" y1="${y.toFixed(2)}" x2="${width - pad.right}" y2="${y.toFixed(2)}" class="pool-chart-grid"></line>`;
    }).join('');
    const lines = series.map((row) => {
      const points = row.values.length > 1 ? row.values : [row.values[0] || 0, row.values[0] || 0];
      const d = points.map((value, index) => `${index === 0 ? 'M' : 'L'} ${xFor(index).toFixed(2)} ${yFor(value).toFixed(2)}`).join(' ');
      return `<path d="${d}" class="pool-chart-line ${row.className || ''}"></path>`;
    }).join('');
    const labels = series.map((row, index) => `<span class="${row.className || ''}">${escapeHtml(row.label || `Line ${index + 1}`)}</span>`).join('');
    const minLabel = escapeHtml(options.minLabel || min.toFixed(2));
    const maxLabel = escapeHtml(options.maxLabel || max.toFixed(2));
    return `<div class="pool-chart-legend">${labels}</div><svg class="pool-chart" viewBox="0 0 ${width} ${height}" role="img" aria-label="${escapeHtml(options.title || 'Pool chart')}">${grid}${lines}<text x="8" y="${pad.top + 5}" class="pool-chart-label">${maxLabel}</text><text x="8" y="${height - pad.bottom}" class="pool-chart-label">${minLabel}</text><text x="${pad.left}" y="${height - 8}" class="pool-chart-label">now</text><text x="${width - 72}" y="${height - 8}" class="pool-chart-label">live</text></svg>`;
  }

  function poolHistory(data) {
    const key = `hobc_pool_history:${window.location.pathname}`;
    let history = [];
    try {
      history = JSON.parse(localStorage.getItem(key) || '[]');
    } catch (error) {
      history = [];
    }
    const windows = data.graph_windows && typeof data.graph_windows === 'object' ? data.graph_windows : {};
    const sample = {
      t: Date.now(),
      hashrate: parseHashrate(data.hashrate),
      shareHit: numberValue(data.last_share && data.last_share.share_difficulty),
      bestShare: numberValue(data.best_share),
      bestProgress: numberValue(data.best_share_progress),
      activeMiners: numberValue(data.active_sessions),
      sessions: Array.isArray(data.miner_sessions) ? data.miner_sessions.length : numberValue(data.active_sessions),
      acceptedMinute: numberValue(windows['60m'] && windows['60m'].accepted_per_minute),
      submittedPressure: numberValue(windows['5m'] && windows['5m'].accepted_per_minute),
      rejectPercent: numberValue(data.reject_percent)
    };
    const last = history[history.length - 1];
    if (!last || Date.now() - Number(last.t || 0) > 9000) {
      history.push(sample);
    } else {
      history[history.length - 1] = sample;
    }
    history = history.slice(-48);
    try {
      localStorage.setItem(key, JSON.stringify(history));
    } catch (error) {
      // Ignore local history storage failures; live values still render.
    }
    return history.length > 1 ? history : [sample, sample];
  }

  async function loadEndpoint(endpoint, force = false) {
    const cacheKey = canonicalApiEndpoint(endpoint);
    if (force || !cache.has(cacheKey)) {
      const separator = cacheKey.includes('?') ? '&' : '?';
      const url = force ? `${cacheKey}${separator}_=${Date.now()}` : cacheKey;
      cache.set(cacheKey, fetch(url, { cache: 'no-store', headers: { Accept: 'application/json', 'Cache-Control': 'no-cache' } }).then((response) => response.json()));
    }
    return cache.get(cacheKey);
  }

  function renderBurnTransactions(target, data) {
    const txs = Array.isArray(data.burn_transactions) ? data.burn_transactions : [];
    if (txs.length === 0) {
      target.innerHTML = '<div class="notice">' + escapeHtml(hobcI18n('notice.no_burn_tx', 'No burn transactions found yet.')) + '</div>';
      return;
    }
    target.innerHTML = txs.map((tx) => {
      const txid = escapeHtml(tx.txid || 'not_available');
      const visibleTxid = escapeHtml(shortText(tx.txid || 'not_available'));
      const amount = escapeHtml(tx.amount || '0.00000000');
      const vout = escapeHtml(tx.vout ?? 'not_available');
      const height = escapeHtml(tx.height ?? 'not_available');
      return `<article class="burn-tx-card">
        <div class="burn-tx-field burn-txid"><span>${escapeHtml(hobcI18n('label.txid', 'TXID'))}</span><strong class="hash-chip" title="${txid}">${visibleTxid}</strong></div>
        <div class="burn-tx-field"><span>${escapeHtml(hobcI18n('label.amount', 'Amount'))}</span><strong>${amount} HOBC</strong></div>
        <div class="burn-tx-field"><span>${escapeHtml(hobcI18n('label.output', 'Output'))}</span><strong>vout ${vout}</strong></div>
        <div class="burn-tx-field"><span>${escapeHtml(hobcI18n('label.height', 'Height'))}</span><strong>${height}</strong></div>
      </article>`;
    }).join('');
  }

  function renderLatestBlocks(target, data) {
    const blocks = Array.isArray(data.blocks) ? data.blocks : [];
    if (blocks.length === 0) {
      target.innerHTML = '<div class="notice">' + escapeHtml(hobcI18n('notice.no_blocks', 'Latest blocks are not available yet.')) + '</div>';
      return;
    }
    target.innerHTML = blocks.map((block) => {
      const hash = escapeHtml(block.hash || 'not_available');
      const visibleHash = escapeHtml(shortText(block.hash || 'not_available'));
      const height = escapeHtml(block.height ?? 'not_available');
      const rawHeight = String(block.height ?? '');
      const txCount = escapeHtml(block.tx_count ?? 'not_available');
      const time = block.time && block.time !== 'not_available'
        ? escapeHtml(new Date(Number(block.time) * 1000).toLocaleString())
        : 'not_available';
      return `<article class="burn-tx-card">
        <div class="burn-tx-field burn-txid"><span>${escapeHtml(hobcI18n('label.block_hash', 'Block Hash'))}</span><strong class="hash-chip" title="${hash}"><a href="/explorer/?q=${encodeURIComponent(block.hash || '')}">${visibleHash}</a></strong></div>
        <div class="burn-tx-field"><span>${escapeHtml(hobcI18n('label.height', 'Height'))}</span><strong><a href="/explorer/?q=${encodeURIComponent(rawHeight)}">${height}</a></strong></div>
        <div class="burn-tx-field"><span>${escapeHtml(hobcI18n('label.transactions', 'Transactions'))}</span><strong>${txCount}</strong></div>
        <div class="burn-tx-field"><span>${escapeHtml(hobcI18n('label.time', 'Time'))}</span><strong>${time}</strong></div>
      </article>`;
    }).join('');
  }

  function renderLatestTransactions(target, data) {
    const txs = Array.isArray(data.transactions) ? data.transactions : [];
    if (txs.length === 0) {
      target.innerHTML = '<div class="notice">' + escapeHtml(hobcI18n('notice.no_transactions', 'Latest transactions are not available yet.')) + '</div>';
      return;
    }
    target.innerHTML = txs.map((tx) => {
      const txid = escapeHtml(tx.txid || 'not_available');
      const visibleTxid = escapeHtml(shortText(tx.txid || 'not_available'));
      const blockhash = escapeHtml(tx.blockhash || 'not_available');
      const visibleBlockhash = escapeHtml(shortText(tx.blockhash || 'not_available'));
      const height = escapeHtml(tx.height ?? 'not_available');
      const rawHeight = String(tx.height ?? '');
      const type = escapeHtml(titleCaseStatus(tx.type || 'transaction'));
      const time = tx.time && tx.time !== 'not_available'
        ? escapeHtml(new Date(Number(tx.time) * 1000).toLocaleString())
        : 'not_available';
      return `<article class="burn-tx-card">
        <div class="burn-tx-field burn-txid"><span>${escapeHtml(hobcI18n('label.txid', 'TXID'))}</span><strong class="hash-chip" title="${txid}"><a href="/explorer/?q=${encodeURIComponent(tx.txid || '')}">${visibleTxid}</a></strong></div>
        <div class="burn-tx-field"><span>${escapeHtml(hobcI18n('label.block', 'Block'))}</span><strong><a href="/explorer/?q=${encodeURIComponent(rawHeight)}">${height}</a></strong></div>
        <div class="burn-tx-field burn-txid"><span>${escapeHtml(hobcI18n('label.block_hash', 'Block Hash'))}</span><strong class="hash-chip" title="${blockhash}"><a href="/explorer/?q=${encodeURIComponent(tx.blockhash || '')}">${visibleBlockhash}</a></strong></div>
        <div class="burn-tx-field"><span>${escapeHtml(hobcI18n('label.type', 'Type'))}</span><strong>${type}</strong></div>
        <div class="burn-tx-field"><span>${escapeHtml(hobcI18n('label.time', 'Time'))}</span><strong>${time}</strong></div>
      </article>`;
    }).join('');
  }

  function renderPoolLeaderboard(target, data) {
    const miners = Array.isArray(data.miner_leaderboard) ? data.miner_leaderboard : [];
    if (miners.length === 0) {
      target.innerHTML = '<div class="notice">No miner share records are available yet.</div>';
      return;
    }
    const networkDiff = numberValue(data.network_difficulty);
    const sessions = Array.isArray(data.miner_sessions) ? data.miner_sessions : [];
    const sessionMap = new Map(sessions.map((session) => [session.workername, session]));

    const flowFor = (session) => {
      if (!Number.isFinite(Number(session.session_accepted)) || !session.session_started_at) return 'not_available';
      const started = new Date(session.session_started_at).getTime();
      const minutes = started > 0 ? Math.max(1, (Date.now() - started) / 60000) : 0;
      return minutes > 0 ? `${(Number(session.session_accepted) / minutes).toFixed(4)} /m` : 'not_available';
    };

    const cardFor = (miner, index, mode) => {
      const session = sessionMap.get(miner.workername) || miner;
      const worker = escapeHtml(miner.workername || session.workername || 'not_available');
      const accepted = escapeHtml(miner.accepted_shares ?? session.session_accepted ?? '0');
      const rejected = escapeHtml(miner.rejected_shares ?? session.session_rejected ?? '0');
      const bestRaw = Math.max(numberValue(miner.best_share), numberValue(session.session_best_share));
      const best = compactUnit(bestRaw || miner.best_share || session.session_best_share);
      const rejectPercent = escapeHtml(miner.reject_percent || session.session_reject_percent || 'not_available');
      const lastTime = miner.last_share_time && miner.last_share_time !== 'not_available'
        ? escapeHtml(new Date(miner.last_share_time).toLocaleString())
        : 'not_available';
      const progress = networkDiff > 0 && bestRaw > 0 ? Math.min(100, (bestRaw / networkDiff) * 100) : 0;
      const progressText = networkDiff > 0 && bestRaw > 0 ? `${((bestRaw / networkDiff) * 100).toFixed(5)}%` : 'not_available';
      const need = networkDiff > 0 && bestRaw > 0 ? `need ${(networkDiff / bestRaw).toFixed(2)}x` : 'not_available';
      const sessionRate = session.session_hashrate_estimate || 'not_available';
      const lastAge = Number.isFinite(Number(miner.last_share_age_seconds || session.last_share_age_seconds)) ? `${Number(miner.last_share_age_seconds || session.last_share_age_seconds)}s ago` : 'not_available';
      const flow = flowFor(session);
      const sessionAr = `${escapeHtml(session.session_accepted ?? '0')} / ${escapeHtml(session.session_rejected ?? '0')}`;
      const title = mode === 'current' ? 'Current Top Miner' : 'All-Time Top Miner';
      return `<article class="pool-miner-card">
        <div class="pool-miner-card-top">
          <span>${escapeHtml(title)} #${index + 1}</span>
          <strong class="${compactUnitClass(bestRaw)}">${best}</strong>
        </div>
        <h3>${worker}</h3>
        <div class="pool-miner-progress">
          <span>Best vs Network</span>
          <strong>${escapeHtml(progressText)}</strong>
          <div class="pool-progress"><span style="width:${progress}%"></span></div>
          <small>${escapeHtml(need)}</small>
        </div>
        <div class="pool-miner-stats">
          <div><span>All-time A/R</span><strong><span class="pool-good">${accepted}</span> / <span class="pool-bad">${rejected}</span></strong></div>
          <div><span>Session A/R</span><strong>${sessionAr}</strong></div>
          <div><span>Share Flow</span><strong>${escapeHtml(flow)}</strong></div>
          <div><span>Session Rate</span><strong>${escapeHtml(sessionRate)}</strong></div>
          <div><span>Reject Rate</span><strong>${rejectPercent}</strong></div>
          <div><span>Last Share</span><strong>${lastAge}</strong></div>
        </div>
        <p>Last seen ${lastTime}</p>
      </article>`;
    };

    const currentCards = sessions
      .map((session) => ({ ...(miners.find((miner) => miner.workername === session.workername) || {}), ...session }))
      .sort((a, b) => parseHashrate(b.session_hashrate_estimate) - parseHashrate(a.session_hashrate_estimate))
      .slice(0, 4)
      .map((miner, index) => cardFor(miner, index, 'current'))
      .join('');
    const allTimeCards = miners
      .slice(0, 6)
      .map((miner, index) => cardFor(miner, index, 'all-time'))
      .join('');

    target.innerHTML = `<div class="pool-leaderboard-groups">
      <section>
        <div class="pool-section-heading"><h3>Current Top Miners</h3><span>active session leaders</span></div>
        <div class="pool-miner-card-grid">${currentCards || '<div class="notice">No active miner sessions are available yet.</div>'}</div>
      </section>
      <section>
        <div class="pool-section-heading"><h3>All-Time Top Miners</h3><span>best all-time share log leaders</span></div>
        <div class="pool-miner-card-grid">${allTimeCards}</div>
      </section>
    </div>`;
  }

  function renderPoolShares(target, data) {
    const shares = Array.isArray(data.latest_shares) ? data.latest_shares : [];
    if (shares.length === 0) {
      target.innerHTML = '<div class="notice">No recent share records are available yet.</div>';
      return;
    }
    const rows = shares.slice(0, 50).map((share, index) => {
      const worker = escapeHtml(share.workername || 'not_available');
      const sdiffRaw = numberValue(share.share_difficulty);
      const diffRaw = numberValue(share.assigned_difficulty);
      const sdiff = compactUnit(sdiffRaw || share.share_difficulty);
      const diff = compactUnit(diffRaw || share.assigned_difficulty);
      const result = escapeHtml(titleCaseStatus(share.result || 'not_available'));
      const hash = escapeHtml(share.hash || 'not_available');
      const visibleHash = escapeHtml(shortText(share.hash || 'not_available'));
      const age = Number.isFinite(Number(share.age_seconds)) ? `${Math.max(0, Number(share.age_seconds))}s ago` : 'not_available';
      return `<tr><td>${index + 1}</td><td>${escapeHtml(age)}</td><td>${worker}</td><td><span class="${compactUnitClass(sdiffRaw)}">${sdiff}</span></td><td><span class="${compactUnitClass(diffRaw)}">${diff}</span></td><td><span class="pool-result-pill">${result}</span></td><td><span class="hash-chip" title="${hash}">${visibleHash}</span></td></tr>`;
    }).join('');
    target.innerHTML = `<div class="pool-table-wrap"><table class="pool-data-table"><thead><tr><th>#</th><th>Age</th><th>Worker</th><th>Share Hit</th><th>Assigned</th><th>Result</th><th>Hash</th></tr></thead><tbody>${rows}</tbody></table></div>`;
  }

  function renderPoolSessions(target, data) {
    const sessions = Array.isArray(data.miner_sessions) ? data.miner_sessions : [];
    if (sessions.length === 0) {
      target.innerHTML = '<div class="notice">No active miner sessions are available yet.</div>';
      return;
    }
    const filter = (document.querySelector('[data-pool-session-filter]')?.value || '').toLowerCase();
    const sort = document.querySelector('[data-pool-session-sort]')?.value || 'hashrate';
    const leaderboard = new Map((Array.isArray(data.miner_leaderboard) ? data.miner_leaderboard : []).map((miner) => [miner.workername, miner]));
    const networkDiff = numberValue(data.network_difficulty);
    const rowsData = sessions
      .filter((session) => !filter || String(session.workername || '').toLowerCase().includes(filter))
      .map((session) => {
        const allTime = leaderboard.get(session.workername) || {};
        const sessionRate = parseHashrate(session.session_hashrate_estimate);
        const expectedSeconds = networkDiff > 0 && sessionRate > 0 ? (networkDiff * 4294967296) / sessionRate : 0;
        const best = Math.max(numberValue(allTime.best_share), numberValue(session.session_best_share));
        const accepted = numberValue(allTime.accepted_shares);
        const rejected = numberValue(allTime.rejected_shares);
        const startedAt = session.session_started_at && session.session_started_at !== 'not_available' ? new Date(session.session_started_at).getTime() : 0;
        const minutes = startedAt > 0 ? Math.max(1, (Date.now() - startedAt) / 60000) : 0;
        return { session, allTime, sessionRate, expectedSeconds, best, accepted, rejected, startedAt, flow: minutes > 0 ? Number(session.session_accepted || 0) / minutes : 0 };
      })
      .sort((a, b) => {
        if (sort === 'best') return b.best - a.best;
        if (sort === 'accepted') return b.accepted - a.accepted;
        if (sort === 'last') return numberValue(a.session.last_share_age_seconds) - numberValue(b.session.last_share_age_seconds);
        return b.sessionRate - a.sessionRate;
      });
    const rows = rowsData.slice(0, 25).map(({ session, allTime, sessionRate, expectedSeconds, best, accepted, rejected, flow }) => {
      const worker = escapeHtml(session.workername || 'not_available');
      const ar = `${escapeHtml(accepted || allTime.accepted_shares || '0')} / ${escapeHtml(rejected || allTime.rejected_shares || '0')}`;
      const sessionAr = `${escapeHtml(session.session_accepted ?? '0')} / ${escapeHtml(session.session_rejected ?? '0')}`;
      const reject = escapeHtml(allTime.reject_percent || session.session_reject_percent || 'not_available');
      const started = session.session_started_at && session.session_started_at !== 'not_available'
        ? escapeHtml(new Date(session.session_started_at).toLocaleString())
        : 'not_available';
      const last = Number.isFinite(Number(session.last_share_age_seconds)) ? `${Number(session.last_share_age_seconds)}s ago` : 'not_available';
      const progress = networkDiff > 0 && best > 0 ? Math.min(100, (best / networkDiff) * 100) : 0;
      const progressText = networkDiff > 0 && best > 0 ? `${((best / networkDiff) * 100).toFixed(5)}%` : 'not_available';
      const need = networkDiff > 0 && best > 0 ? `need ${(networkDiff / best).toFixed(2)}x` : 'not_available';
      return `<tr>
        <td><strong class="${compactUnitClass(best)}">${compactUnit(best || allTime.best_share || session.session_best_share)}</strong><br><small>${worker}</small></td>
        <td><strong>${escapeHtml(formatDuration(expectedSeconds))}</strong><br><small>avg solo</small></td>
        <td><strong>${escapeHtml(probability(86400, expectedSeconds))}</strong><br><small>7d ${escapeHtml(probability(604800, expectedSeconds))}</small></td>
        <td><strong>${escapeHtml(progressText)}</strong><div class="pool-progress"><span style="width:${progress}%"></span></div><small>${escapeHtml(need)}</small></td>
        <td><strong><span class="pool-good">${ar.split(' / ')[0]}</span> / <span class="pool-bad">${ar.split(' / ')[1]}</span></strong><br><small>${reject} reject</small></td>
        <td><strong>${escapeHtml(flow.toFixed(4))} /m</strong><br><small>pace ${escapeHtml(session.session_hashrate_estimate || (sessionRate ? `${sessionRate} H/s` : 'not_available'))}</small></td>
        <td><strong>${started}</strong><br><small>session ${sessionAr}</small></td>
        <td><strong>${escapeHtml(last)}</strong><br><small>age since last share</small></td>
      </tr>`;
    }).join('');
    target.innerHTML = `<div class="pool-table-wrap pool-session-table-wrap"><table class="pool-data-table pool-session-table"><thead><tr><th>All-time Best</th><th>Miner ETA</th><th>Odds / Day</th><th>Best vs Net</th><th>All-time A/R</th><th>Share Flow</th><th>Session Started</th><th>Last Share</th></tr></thead><tbody>${rows}</tbody></table></div><div class="pool-scroll-hint"><span></span><strong>scroll sideways</strong><span></span></div>`;
  }

  function renderPoolLiveGraphs(target, data) {
    const history = poolHistory(data);
    const values = (field) => history.map((row) => Number(row[field] || 0));
    const card = (title, sub, chart) => `<article class="pool-graph-card"><h3>${escapeHtml(title)}</h3><p>${escapeHtml(sub)}</p>${chart}</article>`;
    target.innerHTML = [
      card('Pool Hashrate', '5m / 60m / 12h accepted share estimates', lineChart([
        { label: '5m', values: values('hashrate'), className: 'pool-line-blue' },
        { label: '60m', values: values('hashrate'), className: 'pool-line-green' },
        { label: '12h', values: values('hashrate'), className: 'pool-line-gold' }
      ], { title: 'Pool Hashrate', minLabel: '0 H/s', maxLabel: displayValue(data.hashrate, 'not_available') })),
      card('Share Difficulty Hits', 'Best diff hit in the last 5m / 30m / 60m', lineChart([
        { label: '5m', values: values('shareHit'), className: 'pool-line-blue' },
        { label: '30m', values: values('bestShare'), className: 'pool-line-green' },
        { label: '60m', values: values('bestShare'), className: 'pool-line-gold' }
      ], { title: 'Share Difficulty Hits' })),
      card('Share Flow', 'Accepted shares per minute, 5m / 60m / 12h', lineChart([
        { label: '5m', values: values('submittedPressure'), className: 'pool-line-blue' },
        { label: '60m', values: values('acceptedMinute'), className: 'pool-line-green' },
        { label: '12h', values: values('acceptedMinute'), className: 'pool-line-gold' }
      ], { title: 'Share Flow' })),
      card('Best Share Progress', 'Best share relative to current network diff', lineChart([
        { label: 'Best %', values: values('bestProgress'), className: 'pool-line-blue' },
        { label: 'Recent %', values: values('bestProgress'), className: 'pool-line-green' }
      ], { title: 'Best Share Progress', maxLabel: displayValue(data.best_share_progress, 'not_available'), minLabel: '0%' })),
      card('Miner Activity', 'Active miners and active sessions', lineChart([
        { label: 'Active miners', values: values('activeMiners'), className: 'pool-line-blue' },
        { label: 'Sessions', values: values('sessions'), className: 'pool-line-green' }
      ], { title: 'Miner Activity' })),
      card('Reject / Quality', 'Reject percentage and submitted share pressure', lineChart([
        { label: 'Reject %', values: values('rejectPercent'), className: 'pool-line-blue' },
        { label: 'Submitted press', values: values('submittedPressure'), className: 'pool-line-gold' }
      ], { title: 'Reject Quality', maxLabel: displayValue(data.reject_percent, 'not_available'), minLabel: '0%' }))
    ].join('');
  }

  function renderPoolWindows(target, data) {
    const windows = data.graph_windows && typeof data.graph_windows === 'object' ? data.graph_windows : {};
    const entries = Object.entries(windows);
    if (entries.length === 0) {
      target.innerHTML = '<div class="notice">Rolling window data is not available yet.</div>';
      return;
    }
    target.innerHTML = entries.map(([name, row]) => {
      const accepted = Number(row.accepted || 0);
      const rejected = Number(row.rejected || 0);
      const total = Math.max(1, accepted + rejected);
      const acceptedWidth = Math.max(2, Math.min(100, (accepted / total) * 100));
      return `<article class="card pool-window-card">
        <span class="metric-label">${escapeHtml(name)} window</span>
        <strong>${escapeHtml(row.hashrate_estimate || 'not_available')}</strong>
        <p>${escapeHtml(row.accepted_per_minute ?? '0')} accepted/min · best ${escapeHtml(row.best_share ?? '0')}</p>
        <div class="pool-mini-bar"><span style="width:${acceptedWidth}%"></span></div>
        <p>${escapeHtml(accepted)} A / ${escapeHtml(rejected)} R</p>
      </article>`;
    }).join('');
  }

  function renderPoolBlocks(target, data) {
    const blocks = Array.isArray(data.blocks_found) ? data.blocks_found : [];
    if (blocks.length === 0) {
      target.innerHTML = '<div class="notice">No blocks recorded by the pool logger yet.</div>';
      return;
    }
    const rows = blocks.slice(0, 25).map((block) => {
      const hash = escapeHtml(block.hash || 'not_available');
      const visibleHash = escapeHtml(shortText(block.hash || 'not_available'));
      const time = block.time && block.time !== 'not_available'
        ? escapeHtml(new Date(block.time).toLocaleString())
        : 'not_available';
      return `<tr><td>${time}</td><td>${escapeHtml(block.height ?? 'not_available')}</td><td><span class="hash-chip" title="${hash}">${visibleHash}</span></td><td>${escapeHtml(block.workername || 'not_available')}</td><td>${escapeHtml(titleCaseStatus(block.status || 'not_available'))}</td></tr>`;
    }).join('');
    target.innerHTML = `<div class="pool-table-wrap"><table class="pool-data-table"><thead><tr><th>Time</th><th>Height</th><th>Hash</th><th>By Worker</th><th>Status</th></tr></thead><tbody>${rows}</tbody></table></div>`;
  }

  function renderApiList(target, data) {
    const type = target.getAttribute('data-api-list');
    if (type === 'burn-transactions') {
      renderBurnTransactions(target, data);
    } else if (type === 'latest-blocks') {
      renderLatestBlocks(target, data);
    } else if (type === 'latest-transactions') {
      renderLatestTransactions(target, data);
    } else if (type === 'pool-leaderboard') {
      renderPoolLeaderboard(target, data);
    } else if (type === 'pool-shares') {
      renderPoolShares(target, data);
    } else if (type === 'pool-sessions') {
      renderPoolSessions(target, data);
    } else if (type === 'pool-windows') {
      renderPoolWindows(target, data);
    } else if (type === 'pool-live-graphs') {
      renderPoolLiveGraphs(target, data);
    } else if (type === 'pool-blocks') {
      renderPoolBlocks(target, data);
    }
  }

  async function refreshApiWidgets(force = false) {
    const valueEls = Array.from(document.querySelectorAll('[data-api-value]'));
    const listEls = Array.from(document.querySelectorAll('[data-api-list][data-api-endpoint]'));
    const endpoints = new Set();
    valueEls.forEach((el) => endpoints.add(el.getAttribute('data-api-value')));
    listEls.forEach((el) => endpoints.add(el.getAttribute('data-api-endpoint')));

    await Promise.all(Array.from(endpoints).filter(Boolean).map(async (endpoint) => {
      try {
        const data = await loadEndpoint(endpoint, force);
        valueEls
          .filter((el) => el.getAttribute('data-api-value') === endpoint)
          .forEach((el) => {
            const field = el.getAttribute('data-field');
            const fallback = el.getAttribute('data-fallback') || 'Not available yet';
            const value = getPathValue(data, field);
            if (el.getAttribute('data-format') === 'compact-unit') {
              const numeric = numberValue(value);
              const formatted = formattedApiValue(value, fallback, 'compact-unit', el.getAttribute('data-link'));
              el.innerHTML = `<span class="${compactUnitClass(numeric)}">${formatted}</span>`;
            } else {
              el.innerHTML = formattedApiValue(value, fallback, el.getAttribute('data-format') || '', el.getAttribute('data-link') || '');
            }
          });
        listEls
          .filter((el) => el.getAttribute('data-api-endpoint') === endpoint)
          .forEach((el) => renderApiList(el, data));
      } catch (error) {
        valueEls
          .filter((el) => el.getAttribute('data-api-value') === endpoint)
          .forEach((el) => { el.textContent = el.getAttribute('data-fallback') || 'Not available yet'; });
      }
    }));
  }

  updateBrowserTime();
  refreshApiWidgets();
  window.setInterval(() => {
    updateBrowserTime();
    refreshApiWidgets(true);
  }, 10000);

  document.querySelectorAll('[data-pool-session-sort], [data-pool-session-filter]').forEach((control) => {
    control.addEventListener('input', () => refreshApiWidgets());
    control.addEventListener('change', () => refreshApiWidgets());
  });

  document.querySelectorAll('[data-pool-refresh]').forEach((button) => {
    button.addEventListener('click', () => refreshApiWidgets(true));
  });

  document.querySelectorAll('[data-mobile-menu-toggle]').forEach((button) => {
    const nav = button.closest('body')?.querySelector('.site-nav');
    if (!nav) return;
    const mobileQuery = window.matchMedia('(max-width: 820px)');
    const menuLabel = hobcI18n('menu.open', 'Menu');
    const closeLabel = hobcI18n('menu.close', 'Close Menu');
    const syncMenuMode = () => {
      if (mobileQuery.matches) {
        button.hidden = false;
      } else {
        button.hidden = true;
        nav.classList.remove('is-open');
        button.setAttribute('aria-expanded', 'false');
        button.textContent = menuLabel;
      }
    };
    syncMenuMode();
    if (typeof mobileQuery.addEventListener === 'function') {
      mobileQuery.addEventListener('change', syncMenuMode);
    } else if (typeof mobileQuery.addListener === 'function') {
      mobileQuery.addListener(syncMenuMode);
    }
    button.addEventListener('click', () => {
      if (!mobileQuery.matches) return;
      const isOpen = nav.classList.toggle('is-open');
      button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      button.textContent = isOpen ? closeLabel : menuLabel;
    });
  });

  document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    if (link.target && link.target !== '_self') return;
    if (link.hasAttribute('download') || link.hasAttribute('data-no-scroll-memory')) return;

    const url = sameSiteUrl(link.getAttribute('href') || '');
    if (!url || url.hash || url.protocol !== window.location.protocol) return;

    saveNextPageScroll();
  });

  document.querySelectorAll('[data-email-check]').forEach((input) => {
    const message = document.querySelector('[data-email-check-message]');
    let timer = null;
    const setMessage = (text, ok) => {
      if (!message) return;
      message.textContent = text;
      message.style.color = ok ? '#77e6a8' : '#ffcf66';
    };
    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      const email = input.value.trim();
      if (email.length < 5 || !email.includes('@')) {
        setMessage(hobcI18n('email.hint', 'Email verification is the first account security step.'), true);
        return;
      }
      timer = window.setTimeout(async () => {
        try {
          const endpoint = input.getAttribute('data-email-check');
          const response = await fetch(`${endpoint}?email=${encodeURIComponent(email)}`, { headers: { Accept: 'application/json' } });
          const data = await response.json();
          setMessage(data.message || hobcI18n('email.check_complete', 'Email check complete.'), !!data.ok);
        } catch (error) {
          setMessage(hobcI18n('email.check_unavailable', 'Email check is temporarily unavailable.'), false);
        }
      }, 450);
    });
  });

  document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
      const original = button.textContent;
      const target = button.getAttribute('data-copy-target');
      const text = target ? (document.querySelector(target)?.textContent || '') : (button.getAttribute('data-copy') || '');
      try {
        await navigator.clipboard.writeText(text.trim());
        button.textContent = hobcI18n('copy.copied', 'Copied');
      } catch (error) {
        button.textContent = hobcI18n('copy.failed', 'Copy failed');
      }
      window.setTimeout(() => { button.textContent = original; }, 1600);
    });
  });

  function isDownloadUrl(url) {
    return url && url.origin === window.location.origin && url.pathname.startsWith('/downloads/') && /\.(exe|zip|tar\.gz|txt|dmg|deb|rpm|appimage)$/i.test(url.pathname);
  }

  function recordDownloadClick(url) {
    const payload = JSON.stringify({
      file_url: url.pathname + url.search,
      referrer: document.referrer || ''
    });
    const endpoint = '/api/analytics/download/';
    try {
      if (navigator.sendBeacon) {
        const blob = new Blob([payload], { type: 'application/json' });
        navigator.sendBeacon(endpoint, blob);
        return;
      }
      fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: payload,
        keepalive: true
      }).catch(() => {});
    } catch (error) {
      // Analytics must never block downloads.
    }
  }

  document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link) return;
    const url = sameSiteUrl(link.getAttribute('href') || '');
    if (isDownloadUrl(url)) {
      recordDownloadClick(url);
    }
  });

  function sendAnalyticsHeartbeat() {
    if (document.visibilityState && document.visibilityState !== 'visible') return;
    const payload = JSON.stringify({
      url: window.location.pathname + window.location.search,
      referrer: document.referrer || ''
    });
    try {
      fetch('/api/analytics/heartbeat/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: payload,
        keepalive: true,
        credentials: 'same-origin'
      }).catch(() => {});
    } catch (error) {
      // Live visitor tracking should never affect the public page.
    }
  }

  window.setTimeout(sendAnalyticsHeartbeat, 2500);
  window.setInterval(sendAnalyticsHeartbeat, 30000);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      sendAnalyticsHeartbeat();
    }
  });
})();
