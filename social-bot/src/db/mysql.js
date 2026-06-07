const mysql = require('mysql2/promise');
const config = require('../config');
const logger = require('../logger');
const { loadWalletDbConfig } = require('../utils/walletConfig');

let pool = null;

function resolveMysqlConfig() {
  if (config.mysql.user) {
    return config.mysql;
  }
  const fromWallet = loadWalletDbConfig();
  if (fromWallet) {
    logger.info('MySQL credentials loaded from HOBC wallet config.php');
    return fromWallet;
  }
  return null;
}

async function getMysqlPool() {
  if (pool) return pool;

  const cfg = resolveMysqlConfig();
  if (!cfg?.user) {
    logger.warn('MySQL not configured — set MYSQL_USER or ensure wallet config.php is readable');
    return null;
  }

  try {
    pool = mysql.createPool({
      host: cfg.host,
      port: cfg.port,
      user: cfg.user,
      password: cfg.password,
      database: cfg.database,
      waitForConnections: true,
      connectionLimit: 5,
      connectTimeout: 8000,
    });
    await pool.query('SELECT 1');
    logger.info('MySQL pool connected', { database: cfg.database });
    return pool;
  } catch (err) {
    logger.warn('MySQL pool creation failed', { error: err.message });
    pool = null;
    return null;
  }
}

async function query(sql, params = []) {
  const db = await getMysqlPool();
  if (!db) return null;
  try {
    const [rows] = await db.query(sql, params);
    return rows;
  } catch (err) {
    logger.warn('MySQL query failed', { error: err.message, sql: sql.slice(0, 80) });
    return null;
  }
}

module.exports = { getMysqlPool, query, resolveMysqlConfig };
