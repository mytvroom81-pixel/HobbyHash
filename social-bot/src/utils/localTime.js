const config = require('../config');

function getAdminTimezone() {
  return config.adminTimezone
    || process.env.HOBC_ADMIN_TIMEZONE
    || process.env.HOBC_ANALYTICS_TIMEZONE
    || 'America/Los_Angeles';
}

function formatInTimezone(date, timeZone, options) {
  return new Intl.DateTimeFormat('en-US', { timeZone, ...options }).format(date);
}

function localDateKey(d = new Date()) {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: getAdminTimezone(),
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(d);
}

function localHour(d = new Date()) {
  return parseInt(formatInTimezone(d, getAdminTimezone(), {
    hour: 'numeric',
    hour12: false,
  }), 10);
}

function localMinute(d = new Date()) {
  return parseInt(formatInTimezone(d, getAdminTimezone(), {
    minute: 'numeric',
  }), 10);
}

function formatUtcSql(date) {
  return date.toISOString().slice(0, 19).replace('T', ' ');
}

function localDayUtcBounds(dateKey = localDateKey()) {
  const tz = getAdminTimezone();
  const [year, month, day] = dateKey.split('-').map(Number);
  const center = Date.UTC(year, month - 1, day, 12, 0, 0);
  let startMs = null;

  for (let ms = center - 36 * 3600000; ms <= center + 36 * 3600000; ms += 60000) {
    const probe = new Date(ms);
    if (localDateKey(probe) !== dateKey) continue;
    if (localHour(probe) === 0 && localMinute(probe) === 0) {
      startMs = ms;
      break;
    }
  }

  if (startMs === null) {
    startMs = center - 12 * 3600000;
  }

  return {
    date: dateKey,
    timezone: tz,
    startUtc: formatUtcSql(new Date(startMs)),
    endUtc: formatUtcSql(new Date(startMs + 86400000)),
  };
}

function utcStampToLocalDateKey(value) {
  if (!value) return null;
  const raw = String(value).trim();
  const normalized = raw.includes('T') ? raw : `${raw.replace(' ', 'T')}Z`;
  const d = new Date(normalized);
  if (Number.isNaN(d.getTime())) return null;
  return localDateKey(d);
}

function localDayLabel(dateKey = localDateKey()) {
  const [year, month, day] = dateKey.split('-').map(Number);
  const probe = new Date(Date.UTC(year, month - 1, day, 12, 0, 0));
  return formatInTimezone(probe, getAdminTimezone(), {
    month: 'short',
    day: 'numeric',
  });
}

module.exports = {
  getAdminTimezone,
  localDateKey,
  localHour,
  localDayUtcBounds,
  utcStampToLocalDateKey,
  localDayLabel,
};
