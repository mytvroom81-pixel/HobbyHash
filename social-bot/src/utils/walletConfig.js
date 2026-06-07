const fs = require('fs');
const { execFileSync } = require('child_process');

const CONFIG_CANDIDATES = [
  process.env.HOBC_WALLET_CONFIG,
  '/home/hobbyhashcoin/hobbyhash-wallet-private/config.php',
  '/home/hobbyhashcoin/hobbyhash-clean/wallet/config.php',
  '/home/hobbyhashcoin/public_html/config.php',
].filter(Boolean);

function loadWalletConfigSection(section) {
  for (const configPath of CONFIG_CANDIDATES) {
    if (!configPath || !fs.existsSync(configPath)) continue;
    try {
      fs.accessSync(configPath, fs.constants.R_OK);
    } catch {
      continue;
    }
    try {
      const php = `echo json_encode((require ${JSON.stringify(configPath)})[${JSON.stringify(section)}] ?? []);`;
      const stdout = execFileSync('php', ['-r', php], { encoding: 'utf8', timeout: 5000, stdio: ['pipe', 'pipe', 'ignore'] });
      const data = JSON.parse(stdout.trim());
      if (data && typeof data === 'object' && Object.keys(data).length > 0) {
        return data;
      }
    } catch {
      // try next candidate
    }
  }
  return null;
}

function loadWalletDbConfig() {
  const db = loadWalletConfigSection('db');
  if (!db?.username) return null;
  return {
    host: db.host || '127.0.0.1',
    port: parseInt(db.port, 10) || 3306,
    user: db.username,
    password: db.password || '',
    database: db.database || 'hobbyhash_wallet',
  };
}

module.exports = { loadWalletConfigSection, loadWalletDbConfig, CONFIG_CANDIDATES };
