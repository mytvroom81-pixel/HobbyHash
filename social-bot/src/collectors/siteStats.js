const logger = require('../logger');
const { query } = require('../db/mysql');

async function collectSiteStats() {
  const result = {
    totalPageviews: 0,
    uniqueVisitors: 0,
    humanPageviews: 0,
    dailyPageviews: 0,
    dailyUniqueVisitors: 0,
    topRoutes: [],
    sources: [],
  };

  const pvRows = await query(`
    SELECT COUNT(*) AS cnt FROM site_pageviews WHERE is_bot = 0
  `);
  if (pvRows) {
    result.humanPageviews = Number(pvRows[0]?.cnt || 0);
    result.totalPageviews = result.humanPageviews;
    result.sources.push('mysql:site_pageviews');
  }

  const visitorRows = await query(`
    SELECT COUNT(DISTINCT visitor_id) AS cnt FROM site_visitors WHERE is_bot = 0
  `);
  if (visitorRows) {
    result.uniqueVisitors = Number(visitorRows[0]?.cnt || 0);
    result.sources.push('mysql:site_visitors');
  }

  const dailyPv = await query(`
    SELECT COUNT(*) AS cnt FROM site_pageviews
    WHERE is_bot = 0 AND created_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY
  `);
  if (dailyPv) {
    result.dailyPageviews = Number(dailyPv[0]?.cnt || 0);
  }

  const dailyUv = await query(`
    SELECT COUNT(DISTINCT visitor_id) AS cnt FROM site_pageviews
    WHERE is_bot = 0 AND created_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY
  `);
  if (dailyUv) {
    result.dailyUniqueVisitors = Number(dailyUv[0]?.cnt || 0);
  }

  const routes = await query(`
    SELECT route_name, COUNT(*) AS cnt FROM site_pageviews
    WHERE is_bot = 0 AND route_name IS NOT NULL AND route_name != ''
    GROUP BY route_name ORDER BY cnt DESC LIMIT 5
  `);
  if (routes) {
    result.topRoutes = routes.map((r) => ({ route: r.route_name, count: Number(r.cnt) }));
  }

  if (!result.sources.length) {
    logger.warn('Site stats: MySQL unavailable — traffic milestones skipped');
  }

  return result;
}

function checkVisitMilestones(pageviews, recordMilestone) {
  const milestones = [];
  const pv = Number(pageviews) || 0;
  for (let t = 1000; t <= 1000000; t += 1000) {
    if (pv >= t) {
      const isNew = recordMilestone('site_visits', t);
      if (isNew) milestones.push({ type: 'site_visits', value: t });
    }
  }
  return milestones;
}

module.exports = {
  collectSiteStats,
  checkVisitMilestones,
};
