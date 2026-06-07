const fs = require('fs');
const path = require('path');
const logger = require('../logger');
const { query } = require('../db/mysql');
const { getSetting, setSetting } = require('../db');

const PDF_ASSETS = [
  { key: 'whitepaper', label: 'Whitepaper', paths: [
    '/home/hobbyhashcoin/public_html/assets/docs/hobc-whitepaper.pdf',
    '/home/hobbyhashcoin/public_html/assets/docs/hobc-whitepaper-placeholder.pdf',
  ]},
  { key: 'factsheet', label: 'Listing factsheet', paths: [
    '/home/hobbyhashcoin/public_html/assets/docs/hobc-listing-factsheet.pdf',
    '/home/hobbyhashcoin/public_html/assets/docs/hobc-listing-factsheet-placeholder.pdf',
  ]},
];

function classifyDownload(row) {
  const title = String(row.title || '').toLowerCase();
  const platform = String(row.platform || '').toLowerCase();
  if (platform.includes('node') || title.includes('node') || title.includes('hobbyhashd')) return 'node';
  if (platform.includes('wallet') || title.includes('wallet') || title.includes('hobbyhash-cli')) return 'wallet';
  return 'download';
}

async function collectDownloadData() {
  const result = {
    latest: [],
    walletReleases: [],
    nodeReleases: [],
    totalDownloads: 0,
    sources: [],
  };

  const rows = await query(`
    SELECT id, title, description, platform, version, file_url, checksum_sha256,
           download_count, status, updated_at, created_at
    FROM downloads
    WHERE status = 'published'
    ORDER BY updated_at DESC, created_at DESC
    LIMIT 20
  `);

  if (rows) {
    result.latest = rows;
    result.sources.push('mysql:downloads');
    for (const row of rows) {
      const kind = classifyDownload(row);
      if (kind === 'wallet') result.walletReleases.push(row);
      if (kind === 'node') result.nodeReleases.push(row);
    }
    const countRows = await query(`SELECT COALESCE(SUM(download_count), 0) AS total FROM downloads WHERE status = 'published'`);
    result.totalDownloads = Number(countRows?.[0]?.total || 0);
  } else {
    logger.warn('Downloads collector: MySQL unavailable');
  }

  return result;
}

async function collectContentUpdates() {
  const result = {
    docs: [],
    announcements: [],
    pdfUpdates: [],
    sources: [],
  };

  const docs = await query(`
    SELECT id, title, slug, category, updated_at, published_at
    FROM docs_pages
    WHERE status = 'published'
    ORDER BY updated_at DESC
    LIMIT 15
  `);
  if (docs) {
    result.docs = docs;
    result.sources.push('mysql:docs_pages');
  }

  const announcements = await query(`
    SELECT id, title, slug, published_at, updated_at
    FROM announcements
    WHERE status = 'published'
    ORDER BY COALESCE(published_at, updated_at) DESC
    LIMIT 10
  `);
  if (announcements) {
    result.announcements = announcements;
    result.sources.push('mysql:announcements');
  }

  for (const asset of PDF_ASSETS) {
    for (const p of asset.paths) {
      if (!fs.existsSync(p)) continue;
      const stat = fs.statSync(p);
      result.pdfUpdates.push({
        key: asset.key,
        label: asset.label,
        path: p,
        mtime: stat.mtime.toISOString(),
        size: stat.size,
      });
      break;
    }
  }
  if (result.pdfUpdates.length) result.sources.push('file:pdf_assets');

  return result;
}

function detectNewReleases(currentDownloads, lastSeenVersions = {}) {
  const newReleases = [];
  for (const dl of currentDownloads) {
    const key = `${dl.platform}-${dl.version}`;
    if (!lastSeenVersions[key] && dl.version) {
      newReleases.push({
        kind: classifyDownload(dl),
        platform: dl.platform,
        version: dl.version,
        title: dl.title,
        url: dl.file_url,
        updated_at: dl.updated_at,
      });
    }
  }
  return newReleases;
}

function detectContentChanges(content, state = {}) {
  const events = [];
  const lastDocs = state.lastDocs || {};
  const lastAnnouncements = state.lastAnnouncements || {};
  const lastPdfs = state.lastPdfs || {};
  const initialized = !!state.initialized;

  for (const doc of content.docs || []) {
    const key = String(doc.id);
    const ts = doc.updated_at || doc.published_at;
    if (initialized && lastDocs[key] && lastDocs[key] !== ts) {
      events.push({ type: 'docs_update', payload: { title: doc.title, slug: doc.slug, category: doc.category } });
    }
    lastDocs[key] = ts;
  }

  for (const ann of content.announcements || []) {
    const key = String(ann.id);
    const ts = ann.published_at || ann.updated_at;
    if (initialized && !lastAnnouncements[key] && ts) {
      events.push({ type: 'announcement', payload: { title: ann.title, slug: ann.slug } });
    }
    lastAnnouncements[key] = ts;
  }

  for (const pdf of content.pdfUpdates || []) {
    const prev = lastPdfs[pdf.key];
    if (initialized && prev && prev !== pdf.mtime) {
      events.push({ type: 'pdf_update', payload: { label: pdf.label, key: pdf.key } });
    }
    lastPdfs[pdf.key] = pdf.mtime;
  }

  return {
    events,
    state: { lastDocs, lastAnnouncements, lastPdfs, initialized: true },
  };
}

function loadContentState() {
  return getSetting('content_tracking_state', {
    lastDocs: {},
    lastAnnouncements: {},
    lastPdfs: {},
    lastDownloadVersions: {},
  });
}

function saveContentState(state) {
  setSetting('content_tracking_state', state);
}

module.exports = {
  collectDownloadData,
  collectContentUpdates,
  detectNewReleases,
  detectContentChanges,
  loadContentState,
  saveContentState,
  classifyDownload,
};
