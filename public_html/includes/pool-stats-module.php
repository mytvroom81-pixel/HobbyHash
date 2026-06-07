<?php
declare(strict_types=1);
$poolStatsTitleKey = $poolStatsTitleKey ?? 'pool_stats.title';
$poolStatsSubtitleKey = $poolStatsSubtitleKey ?? 'pool_stats.subtitle';
?>
    <section class="hobc-stats-card hobc-stats-module" data-hobc-stats-module data-api-url="<?= hobc_e($poolStatsApiUrl ?? '/api/pool/main/overload/') ?>">
      <div class="hobc-stats-hero-row">
        <div>
          <h1 class="hobc-stats-title"><?= hobc_te($poolStatsTitleKey) ?></h1>
          <p class="hobc-stats-subtitle"><?= hobc_te($poolStatsSubtitleKey) ?></p>
          <p class="hobc-stats-foot"><?= hobc_te('pool_stats.foot.updated') ?> <span id="hobcStatsUpdatedLocal">-</span> · <?= hobc_te('pool_stats.foot.browser_refresh') ?> <span id="hobcStatsBrowserRefresh"><?= hobc_te('pool_stats.foot.starting') ?></span> · <span class="hobc-stats-pill hobc-stats-warn"><?= hobc_te('pool_stats.foot.masked') ?></span> · <?= hobc_te('pool_stats.foot.source') ?> <span class="hobc-stats-mono"><?= hobc_te('pool_stats.foot.source_value') ?></span> · <?= hobc_te('pool_stats.foot.collector') ?> <span id="hobcStatsCollector" class="hobc-stats-mono"><?= hobc_te('pool_stats.foot.collector_unknown') ?></span></p>
        </div>
        <div class="hobc-stats-coin-badge"><span class="hobc-stats-coin-dot"></span><span id="hobcStatsCoinName">HobbyHash Coin</span> <span id="hobcStatsCoinSymbol">HOBC</span></div>
      </div>

      <div class="hobc-stats-kpis" id="hobcStatsKpiBar"></div>

      <div class="hobc-stats-sep"></div>
      <h2 class="hobc-stats-section-title"><?= hobc_te('pool_stats.section.luck.title') ?></h2>
      <p class="hobc-stats-section-sub"><?= hobc_te('pool_stats.section.luck.sub') ?></p>
      <div class="hobc-stats-overload-grid" id="hobcStatsOddsBar"></div>
      <div class="hobc-stats-big-note" id="hobcStatsLuckNote"><?= hobc_te('pool_stats.luck_note.default') ?></div>

      <div class="hobc-stats-sep"></div>
      <h2 class="hobc-stats-section-title"><?= hobc_te('pool_stats.section.leaderboard.title') ?></h2>
      <p class="hobc-stats-section-sub"><?= hobc_te('pool_stats.section.leaderboard.sub') ?></p>
      <div class="hobc-stats-overload-grid" id="hobcStatsMinerHighlights"></div>

      <h2 class="hobc-stats-section-title"><?= hobc_te('pool_stats.section.network.title') ?></h2>
      <div class="hobc-stats-network-grid" id="hobcStatsNetworkInfoBar"></div>

      <div class="hobc-stats-sep"></div>
      <h2 class="hobc-stats-section-title"><?= hobc_te('pool_stats.section.sessions.title') ?></h2>
      <p class="hobc-stats-section-sub"><?= hobc_te('pool_stats.section.sessions.sub') ?></p>
      <div class="hobc-stats-controls">
        <label><?= hobc_te('pool_stats.controls.sort') ?>
          <select id="hobcStatsSort">
            <option value="session_hashrate_accepted_hs|desc"><?= hobc_te('pool_stats.sort.session_hashrate_desc') ?></option>
            <option value="hashrate_accepted_5m_hs|desc"><?= hobc_te('pool_stats.sort.hashrate_5m_desc') ?></option>
            <option value="hashrate_accepted_60m_hs|desc"><?= hobc_te('pool_stats.sort.hashrate_60m_desc') ?></option>
            <option value="hashrate_accepted_720m_hs|desc"><?= hobc_te('pool_stats.sort.hashrate_12h_desc') ?></option>
            <option value="session_accepted_total|desc"><?= hobc_te('pool_stats.sort.session_accepted_desc') ?></option>
            <option value="accepted_total|desc"><?= hobc_te('pool_stats.sort.alltime_accepted_desc') ?></option>
            <option value="best_share|desc"><?= hobc_te('pool_stats.sort.best_share_desc') ?></option>
          </select>
        </label>
        <input id="hobcStatsFilter" placeholder="<?= hobc_te('pool_stats.filter.worker_placeholder') ?>" autocomplete="off">
        <button id="hobcStatsRefresh" type="button"><?= hobc_te('pool_stats.controls.refresh') ?></button>
        <span class="hobc-stats-pill"><?= hobc_te('pool_stats.pill.status') ?> <span id="hobcStatsStatusText"><?= hobc_te('pool_stats.pill.status_ok') ?></span></span>
        <span class="hobc-stats-pill"><?= hobc_te('pool_stats.pill.miners') ?> <span id="hobcStatsMinerCount">0</span></span>
        <span class="hobc-stats-pill"><?= hobc_te('pool_stats.pill.session_gap') ?> <span id="hobcStatsSessionGapText">-</span></span>
      </div>

      <div class="hobc-stats-table-wrap">
        <table id="hobcStatsMinerTable" class="hobc-stats-table">
          <thead>
            <tr>
              <th><?= hobc_te('label.status') ?></th><th><?= hobc_te('label.worker') ?></th>
              <th class="hobc-stats-right"><?= hobc_te('pool_stats.table.session_ar') ?></th>
              <th class="hobc-stats-right"><?= hobc_te('pool_stats.table.session_rate') ?></th>
              <th class="hobc-stats-right"><?= hobc_te('pool_stats.table.hashrate_5m') ?></th><th class="hobc-stats-right"><?= hobc_te('pool_stats.table.hashrate_60m') ?></th><th class="hobc-stats-right"><?= hobc_te('pool_stats.table.last_hit') ?></th>
              <th class="hobc-stats-right"><?= hobc_te('pool_stats.table.alltime_best') ?></th>
              <th class="hobc-stats-right"><?= hobc_te('pool_stats.table.miner_eta') ?></th><th class="hobc-stats-right"><?= hobc_te('pool_stats.table.odds_day') ?></th>
              <th class="hobc-stats-right"><?= hobc_te('pool_stats.table.best_vs_net') ?></th>
              <th class="hobc-stats-right"><?= hobc_te('pool_stats.table.alltime_ar') ?></th>
              <th class="hobc-stats-right"><?= hobc_te('pool_stats.table.share_flow') ?></th>
              <th><?= hobc_te('pool_stats.table.session_started') ?></th><th><?= hobc_te('pool_stats.table.last_share') ?></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <div class="hobc-stats-sep"></div>
      <h2 class="hobc-stats-section-title"><?= hobc_te('pool_stats.section.graphs.title') ?></h2>
      <p class="hobc-stats-section-sub"><?= hobc_te('pool_stats.section.graphs.sub') ?></p>
      <div class="hobc-stats-chart-grid">
        <div class="hobc-stats-chart-card"><div class="hobc-stats-chart-title"><?= hobc_te('pool.chart.pool_hashrate') ?></div><div class="hobc-stats-chart-sub"><?= hobc_te('pool_stats.chart.hash_sub') ?></div><canvas id="hobcStatsHashChart" height="195"></canvas></div>
        <div class="hobc-stats-chart-card"><div class="hobc-stats-chart-title"><?= hobc_te('pool.chart.share_diff_hits') ?></div><div class="hobc-stats-chart-sub"><?= hobc_te('pool_stats.chart.diff_sub') ?></div><canvas id="hobcStatsDiffChart" height="195"></canvas></div>
        <div class="hobc-stats-chart-card"><div class="hobc-stats-chart-title"><?= hobc_te('pool.chart.share_flow') ?></div><div class="hobc-stats-chart-sub"><?= hobc_te('pool_stats.chart.flow_sub') ?></div><canvas id="hobcStatsFlowChart" height="195"></canvas></div>
        <div class="hobc-stats-chart-card"><div class="hobc-stats-chart-title"><?= hobc_te('pool.chart.best_progress') ?></div><div class="hobc-stats-chart-sub"><?= hobc_te('pool_stats.chart.progress_sub') ?></div><canvas id="hobcStatsLuckChart" height="195"></canvas></div>
        <div class="hobc-stats-chart-card"><div class="hobc-stats-chart-title"><?= hobc_te('pool.chart.miner_activity') ?></div><div class="hobc-stats-chart-sub"><?= hobc_te('pool_stats.chart.miners_sub') ?></div><canvas id="hobcStatsMinersChart" height="195"></canvas></div>
        <div class="hobc-stats-chart-card"><div class="hobc-stats-chart-title"><?= hobc_te('pool.chart.reject_quality') ?></div><div class="hobc-stats-chart-sub"><?= hobc_te('pool_stats.chart.reject_sub') ?></div><canvas id="hobcStatsRejectChart" height="195"></canvas></div>
      </div>

      <div class="hobc-stats-sep"></div>
      <h2 class="hobc-stats-section-title"><?= hobc_te('pool_stats.section.shares.title_prefix') ?> <span id="hobcStatsShareCountHdr">20</span> <?= hobc_te('pool_stats.section.shares.title_suffix') ?> <span id="hobcStatsCapNote" class="hobc-stats-note"></span></h2>
      <p class="hobc-stats-section-sub"><?= hobc_te('pool_stats.section.shares.sub') ?></p>
      <div class="hobc-stats-controls">
        <label><?= hobc_te('pool_stats.controls.show') ?>
          <select id="hobcStatsShareLimit">
            <option value="20" selected><?= hobc_te('pool_stats.share_limit.last_20') ?></option>
            <option value="50"><?= hobc_te('pool_stats.share_limit.last_50') ?></option>
            <option value="100"><?= hobc_te('pool_stats.share_limit.last_100') ?></option>
            <option value="250"><?= hobc_te('pool_stats.share_limit.last_250') ?></option>
          </select>
        </label>
        <label><?= hobc_te('pool_stats.controls.sort') ?>
          <select id="hobcStatsShareSort">
            <option value="time|desc"><?= hobc_te('pool_stats.share_sort.newest') ?></option>
            <option value="time|asc"><?= hobc_te('pool_stats.share_sort.oldest') ?></option>
            <option value="sdiff|desc"><?= hobc_te('pool_stats.share_sort.biggest_hit') ?></option>
            <option value="diff|desc"><?= hobc_te('pool_stats.share_sort.biggest_diff') ?></option>
            <option value="accepted|desc"><?= hobc_te('pool_stats.share_sort.accepted_first') ?></option>
          </select>
        </label>
        <input id="hobcStatsShareFilter" placeholder="<?= hobc_te('pool_stats.filter.worker_placeholder') ?>" autocomplete="off">
      </div>
      <div class="hobc-stats-table-wrap">
        <table id="hobcStatsShares" class="hobc-stats-table">
          <thead>
            <tr><th><?= hobc_te('label.age') ?></th><th><?= hobc_te('label.worker') ?></th><th class="hobc-stats-right"><?= hobc_te('pool_stats.table.share_hit_sdiff') ?></th><th class="hobc-stats-right"><?= hobc_te('pool_stats.table.assigned_diff') ?></th><th class="hobc-stats-right"><?= hobc_te('pool_stats.table.pct_net_diff') ?></th><th class="hobc-stats-right"><?= hobc_te('pool_stats.table.luck_vs_assigned') ?></th><th><?= hobc_te('label.result') ?></th><th><?= hobc_te('label.hash') ?></th></tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <div class="hobc-stats-sep"></div>
      <h2 class="hobc-stats-section-title"><?= hobc_te('pool_stats.section.blocks.title') ?></h2>
      <p class="hobc-stats-section-sub"><?= hobc_te('pool_stats.section.blocks.sub') ?></p>
      <div class="hobc-stats-table-wrap">
        <table id="hobcStatsBlocks" class="hobc-stats-table">
          <thead><tr><th><?= hobc_te('label.time') ?></th><th><?= hobc_te('label.height') ?></th><th><?= hobc_te('label.hash') ?></th><th><?= hobc_te('label.by_worker') ?></th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </section>
