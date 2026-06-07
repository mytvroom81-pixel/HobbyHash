const { app, BrowserWindow, Menu, clipboard, dialog, ipcMain, session, shell } = require('electron');
const fs = require('fs');
const http = require('http');
const https = require('https');
const crypto = require('crypto');
const net = require('net');
const os = require('os');
const path = require('path');
const { spawn } = require('child_process');

const APP_VERSION = '0.1.25';
const WALLET_NAME = 'HobbyHash';
const RPC_HOST = '127.0.0.1';
const DEFAULT_RPC_PORT = 18762;
const P2P_PORT = 18761;
const PUBLIC_P2P = 'hobbyhashcoin.com:18761';
const PUBLIC_P2P_IP = '47.145.65.88:18761';
const PUBLIC_P2P_BACKUP = '162.254.37.69:18761';
const LOCAL_P2P = '127.0.0.1:18761';
const EXPLORER_URL = 'https://hobbyhashcoin.com/explorer/';
const HOMEPAGE_URL = 'https://hobbyhashcoin.com/';
const REMOTE_CHAIN_STATUS_URL = 'https://hobbyhashcoin.com/api/chain/status/';
const UPDATE_MANIFEST_URL = 'https://hobbyhashcoin.com/downloads/windows/latest.json';
const TOTP_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

let mainWindow = null;
let nodeProcess = null;
let currentRpcPort = DEFAULT_RPC_PORT;
let currentListenEnabled = true;
let adoptedExistingNode = false;
let stoppingNode = false;
let updateCheckInProgress = false;
let remoteChainStatusCache = null;

app.disableHardwareAcceleration();
app.commandLine.appendSwitch('disable-gpu');
if (process.platform === 'win32') {
  app.setAppUserModelId('com.hobbyhashcoin.wallet');
}

const gotSingleInstanceLock = app.requestSingleInstanceLock();
if (!gotSingleInstanceLock) {
  app.quit();
}

function startupLogPath() {
  return path.join(app.getPath('userData'), 'startup.log');
}

function appendStartupLog(message) {
  try {
    fs.mkdirSync(app.getPath('userData'), { recursive: true });
    fs.appendFileSync(startupLogPath(), `${new Date().toISOString()} ${message}${os.EOL}`);
  } catch (_error) {
    // Startup logging is best-effort only.
  }
}

function userDataPath(...parts) {
  return path.join(app.getPath('userData'), ...parts);
}

function nodeDataDir() {
  return userDataPath('node-data');
}

function walletStatePath() {
  return userDataPath('wallet-state.json');
}

function rpcCookiePath() {
  return path.join(nodeDataDir(), '.cookie');
}

function updateBackupDir() {
  return path.join(app.getPath('appData'), 'HobbyHash Wallet Update Backup');
}

function nodeBinaryPath() {
  const resourceRoot = app.isPackaged ? process.resourcesPath : path.join(__dirname, '..', 'resources');
  const exeName = process.platform === 'win32' ? 'hobbyhashd.exe' : 'hobbyhashd';
  return path.join(resourceRoot, 'bin', 'win', exeName);
}

function appIconPath() {
  if (process.platform === 'win32') {
    return app.isPackaged ? path.join(process.resourcesPath, 'icon.ico') : path.join(__dirname, '..', 'build', 'icon.ico');
  }
  return path.join(__dirname, 'assets', 'logo-round.png');
}

function sendToRenderer(channel, payload) {
  if (mainWindow && !mainWindow.isDestroyed()) {
    mainWindow.webContents.send(channel, payload);
  }
}

function compareVersions(left, right) {
  const leftParts = String(left || '').replace(/[^\d.]/g, '').split('.').map((part) => Number(part || 0));
  const rightParts = String(right || '').replace(/[^\d.]/g, '').split('.').map((part) => Number(part || 0));
  const length = Math.max(leftParts.length, rightParts.length);
  for (let index = 0; index < length; index += 1) {
    const diff = (leftParts[index] || 0) - (rightParts[index] || 0);
    if (diff !== 0) {
      return diff;
    }
  }
  return 0;
}

function requestUrl(url, redirectCount = 0) {
  return new Promise((resolve, reject) => {
    const client = url.startsWith('https:') ? https : http;
    const request = client.get(url, {
      timeout: 15000,
      headers: {
        'User-Agent': `HobbyHash-Wallet/${APP_VERSION}`
      }
    }, (response) => {
      if ([301, 302, 303, 307, 308].includes(response.statusCode) && response.headers.location && redirectCount < 5) {
        response.resume();
        const nextUrl = new URL(response.headers.location, url).toString();
        requestUrl(nextUrl, redirectCount + 1).then(resolve).catch(reject);
        return;
      }
      if (response.statusCode < 200 || response.statusCode >= 300) {
        response.resume();
        reject(new Error(`Update server returned HTTP ${response.statusCode}.`));
        return;
      }
      resolve(response);
    });
    request.on('timeout', () => request.destroy(new Error('Update check timed out.')));
    request.on('error', reject);
  });
}

async function fetchJson(url) {
  const response = await requestUrl(url);
  return new Promise((resolve, reject) => {
    const chunks = [];
    let size = 0;
    response.on('data', (chunk) => {
      size += chunk.length;
      if (size > 1024 * 1024) {
        response.destroy(new Error('Update manifest is too large.'));
        return;
      }
      chunks.push(chunk);
    });
    response.on('end', () => {
      try {
        resolve(JSON.parse(Buffer.concat(chunks).toString('utf8')));
      } catch (error) {
        reject(new Error(`Update manifest is invalid JSON: ${error.message}`));
      }
    });
    response.on('error', reject);
  });
}

function sendUpdateDownloadProgress(progress) {
  if (mainWindow && !mainWindow.isDestroyed()) {
    mainWindow.webContents.send('update:download-progress', progress);
    if (typeof progress.percent === 'number') {
      mainWindow.setProgressBar(Math.max(0, Math.min(1, progress.percent / 100)));
    }
  }
}

async function downloadFile(url, destinationPath, onProgress = () => {}) {
  const response = await requestUrl(url);
  await fs.promises.mkdir(path.dirname(destinationPath), { recursive: true });
  return new Promise((resolve, reject) => {
    const file = fs.createWriteStream(destinationPath);
    const totalBytes = Number(response.headers['content-length'] || 0);
    let downloadedBytes = 0;
    response.on('data', (chunk) => {
      downloadedBytes += chunk.length;
      onProgress({
        status: 'downloading',
        downloadedBytes,
        totalBytes,
        percent: totalBytes > 0 ? Math.round((downloadedBytes / totalBytes) * 100) : 0
      });
    });
    response.pipe(file);
    file.on('finish', () => file.close(() => {
      onProgress({
        status: 'downloaded',
        downloadedBytes,
        totalBytes,
        percent: 100
      });
      resolve(destinationPath);
    }));
    file.on('error', (error) => {
      fs.unlink(destinationPath, () => reject(error));
    });
    response.on('error', (error) => {
      fs.unlink(destinationPath, () => reject(error));
    });
  });
}

function fileSha256(filePath) {
  return new Promise((resolve, reject) => {
    const hash = crypto.createHash('sha256');
    const stream = fs.createReadStream(filePath);
    stream.on('data', (chunk) => hash.update(chunk));
    stream.on('end', () => resolve(hash.digest('hex')));
    stream.on('error', reject);
  });
}

async function remoteChainStatus() {
  const now = Date.now();
  if (remoteChainStatusCache && now - remoteChainStatusCache.fetchedAt < 15000) {
    return remoteChainStatusCache.value;
  }

  const status = await fetchJson(REMOTE_CHAIN_STATUS_URL);
  const value = {
    ok: Boolean(status.ok),
    status: String(status.status || ''),
    height: Number(status.blocks || 0),
    headers: Number(status.headers || 0),
    bestblockhash: status.bestblockhash || null,
    initialblockdownload: Boolean(status.initialblockdownload),
    updatedAt: status.updated_at || null,
    source: 'hobbyhashcoin.com'
  };
  if (!value.ok || !Number.isFinite(value.height) || value.height <= 0) {
    throw new Error('hobbyhashcoin.com did not return a valid chain height.');
  }
  remoteChainStatusCache = { fetchedAt: now, value };
  return value;
}

async function checkForUpdates(options = {}) {
  const manual = Boolean(options.manual);
  if (updateCheckInProgress || process.platform !== 'win32') {
    return { checked: false };
  }
  if (!app.isPackaged && !manual) {
    return { checked: false, skippedDev: true };
  }

  updateCheckInProgress = true;
  let downloadStarted = false;
  try {
    const manifest = await fetchJson(UPDATE_MANIFEST_URL);
    const latestVersion = String(manifest.version || '').trim();
    const installerUrl = String(manifest.url || '').trim();
    if (!latestVersion || !installerUrl) {
      throw new Error('Update manifest is missing version or installer URL.');
    }
    if (compareVersions(latestVersion, APP_VERSION) <= 0) {
      if (manual) {
        await dialog.showMessageBox(mainWindow, {
          type: 'info',
          title: 'No Update Available',
          message: 'HobbyHash Wallet is up to date.',
          detail: `Installed version: ${APP_VERSION}`
        });
      }
      return { checked: true, updateAvailable: false };
    }

    const answer = await dialog.showMessageBox(mainWindow, {
      type: 'info',
      title: 'HobbyHash Wallet Update Available',
      message: `HobbyHash Wallet ${latestVersion} is available.`,
      detail: `Installed version: ${APP_VERSION}\n\n${manifest.notes || 'Download and run the new installer to update the wallet.'}`,
      buttons: ['Update Now', 'Later'],
      defaultId: 0,
      cancelId: 1
    });
    if (answer.response !== 0) {
      return { checked: true, updateAvailable: true, declined: true };
    }

    const installerPath = path.join(app.getPath('temp'), `HobbyHash-Wallet-Setup-${latestVersion}.exe`);
    downloadStarted = true;
    sendUpdateDownloadProgress({
      status: 'starting',
      version: latestVersion,
      downloadedBytes: 0,
      totalBytes: 0,
      percent: 0
    });
    await downloadFile(installerUrl, installerPath, (progress) => sendUpdateDownloadProgress({
      ...progress,
      version: latestVersion
    }));
    sendUpdateDownloadProgress({
      status: 'verifying',
      version: latestVersion,
      downloadedBytes: 0,
      totalBytes: 0,
      percent: 100
    });
    if (manifest.sha256) {
      const actualHash = await fileSha256(installerPath);
      if (actualHash.toLowerCase() !== String(manifest.sha256).toLowerCase()) {
        throw new Error('Downloaded installer checksum did not match the update manifest.');
      }
    }

    sendUpdateDownloadProgress({
      status: 'launching',
      version: latestVersion,
      downloadedBytes: 0,
      totalBytes: 0,
      percent: 100
    });
    backupWalletDataForUpdate();
    spawn(installerPath, [], {
      detached: true,
      stdio: 'ignore',
      windowsHide: false
    }).unref();
    app.quit();
    return { checked: true, updateAvailable: true, launched: true };
  } catch (error) {
    appendStartupLog(`update check failed: ${error.message}`);
    if (downloadStarted) {
      sendUpdateDownloadProgress({
        status: 'failed',
        percent: 100,
        error: error.message
      });
    }
    if (manual) {
      await dialog.showMessageBox(mainWindow, {
        type: 'error',
        title: 'Update Check Failed',
        message: 'HobbyHash Wallet could not check for updates.',
        detail: error.message
      });
    }
    return { checked: false, error: error.message };
  } finally {
    if (mainWindow && !mainWindow.isDestroyed()) {
      mainWindow.setProgressBar(-1);
    }
    updateCheckInProgress = false;
  }
}

function buildApplicationMenu() {
  const template = [
    {
      label: 'File',
      submenu: [
        { label: 'Start Wallet/Node', click: () => sendToRenderer('app:start-node-request') },
        { label: 'Stop Wallet/Node', click: () => stopNode().then((result) => sendToRenderer('app:node-stopped', result)).catch((error) => dialog.showErrorBox('Stop failed', error.message)) },
        { type: 'separator' },
        { label: 'Exit', accelerator: 'Alt+F4', click: () => app.quit() }
      ]
    },
    {
      label: 'Wallet',
      submenu: [
        { label: 'Home', click: () => sendToRenderer('app:navigate', 'home') },
        { label: 'Receive', click: () => sendToRenderer('app:navigate', 'receive') },
        { label: 'Send', click: () => sendToRenderer('app:navigate', 'send') },
        { label: 'Transactions', click: () => sendToRenderer('app:navigate', 'transactions') },
        { label: 'Security', click: () => sendToRenderer('app:navigate', 'security') },
        { label: 'Backup', click: () => sendToRenderer('app:navigate', 'backup') }
      ]
    },
    {
      label: 'Tools',
      submenu: [
        { label: 'Advanced Node Details', click: () => sendToRenderer('app:navigate', 'advanced') },
        { label: 'Open Wallet Data Folder', click: () => shell.openPath(path.join(nodeDataDir(), 'wallets')) },
        { label: 'Open Node Data Folder', click: () => shell.openPath(nodeDataDir()) },
        { label: 'Open hobbyhashcoin.com', click: () => shell.openExternal(HOMEPAGE_URL) },
        { label: 'Open Startup Log', click: () => shell.openPath(startupLogPath()) }
      ]
    },
    {
      label: 'Help',
      submenu: [
        {
          label: 'About HobbyHash Wallet',
          click: () => dialog.showMessageBox(mainWindow, {
            type: 'info',
            title: 'About HobbyHash Wallet',
            message: `HobbyHash Wallet ${APP_VERSION}`,
            detail: 'Turnkey full-node desktop wallet for HobbyHash Coin (HOBC).\nP2P: hobbyhashcoin.com:18761\nRPC: local/private only'
          })
        },
        {
          label: 'Check for Updates',
          click: () => checkForUpdates({ manual: true })
        }
      ]
    }
  ];
  Menu.setApplicationMenu(Menu.buildFromTemplate(template));
}

function readWalletState() {
  try {
    return JSON.parse(fs.readFileSync(walletStatePath(), 'utf8'));
  } catch (_error) {
    return {};
  }
}

function writeWalletState(state) {
  fs.mkdirSync(path.dirname(walletStatePath()), { recursive: true });
  fs.writeFileSync(walletStatePath(), JSON.stringify(state, null, 2), { mode: 0o600 });
}

function copyPathIfExists(source, destination) {
  if (!fs.existsSync(source)) {
    return false;
  }
  fs.mkdirSync(path.dirname(destination), { recursive: true });
  fs.cpSync(source, destination, { recursive: true, force: true });
  return true;
}

function backupWalletDataForUpdate() {
  const backupDir = updateBackupDir();
  fs.mkdirSync(backupDir, { recursive: true });
  const copiedState = copyPathIfExists(walletStatePath(), path.join(backupDir, 'wallet-state.json'));
  const copiedWallet = copyPathIfExists(path.join(nodeDataDir(), 'wallets', WALLET_NAME), path.join(backupDir, 'wallets', WALLET_NAME));
  const copiedConfig = copyPathIfExists(path.join(nodeDataDir(), 'hobbyhash.conf'), path.join(backupDir, 'hobbyhash.conf'));
  fs.writeFileSync(path.join(backupDir, 'backup-info.json'), JSON.stringify({
    version: APP_VERSION,
    createdAt: new Date().toISOString(),
    copiedState,
    copiedWallet,
    copiedConfig
  }, null, 2));
  appendStartupLog(`update backup prepared at ${backupDir}, state=${copiedState}, wallet=${copiedWallet}, config=${copiedConfig}`);
  return { backupDir, copiedState, copiedWallet, copiedConfig };
}

function restoreWalletDataAfterUpdateIfNeeded() {
  const backupDir = updateBackupDir();
  if (!fs.existsSync(backupDir)) {
    return { restored: false, reason: 'no update backup' };
  }

  const backupState = path.join(backupDir, 'wallet-state.json');
  const backupWallet = path.join(backupDir, 'wallets', WALLET_NAME);
  const backupConfig = path.join(backupDir, 'hobbyhash.conf');
  let restoredState = false;
  let restoredWallet = false;
  let restoredConfig = false;

  if (!fs.existsSync(walletStatePath()) && fs.existsSync(backupState)) {
    copyPathIfExists(backupState, walletStatePath());
    restoredState = true;
  }

  const walletDir = path.join(nodeDataDir(), 'wallets', WALLET_NAME);
  if (!fs.existsSync(walletDir) && fs.existsSync(backupWallet)) {
    copyPathIfExists(backupWallet, walletDir);
    restoredWallet = true;
  }

  const configFile = path.join(nodeDataDir(), 'hobbyhash.conf');
  if (!fs.existsSync(configFile) && fs.existsSync(backupConfig)) {
    copyPathIfExists(backupConfig, configFile);
    restoredConfig = true;
  }

  if (restoredState || restoredWallet || restoredConfig) {
    appendStartupLog(`restored wallet data after update, state=${restoredState}, wallet=${restoredWallet}, config=${restoredConfig}`);
  }
  return { restored: restoredState || restoredWallet || restoredConfig, restoredState, restoredWallet, restoredConfig };
}

function base32Encode(buffer) {
  let bits = '';
  let output = '';
  for (const byte of buffer) {
    bits += byte.toString(2).padStart(8, '0');
  }
  for (let index = 0; index < bits.length; index += 5) {
    const chunk = bits.slice(index, index + 5).padEnd(5, '0');
    output += TOTP_ALPHABET[parseInt(chunk, 2)];
  }
  return output;
}

function base32Decode(value) {
  const clean = String(value || '').toUpperCase().replace(/[^A-Z2-7]/g, '');
  let bits = '';
  const bytes = [];
  for (const char of clean) {
    const index = TOTP_ALPHABET.indexOf(char);
    if (index >= 0) {
      bits += index.toString(2).padStart(5, '0');
    }
  }
  for (let offset = 0; offset + 8 <= bits.length; offset += 8) {
    bytes.push(parseInt(bits.slice(offset, offset + 8), 2));
  }
  return Buffer.from(bytes);
}

function generateTotpSecret() {
  return base32Encode(crypto.randomBytes(20));
}

function totpCode(secret, timeStep = Math.floor(Date.now() / 30000)) {
  const key = base32Decode(secret);
  const counter = Buffer.alloc(8);
  counter.writeBigUInt64BE(BigInt(timeStep));
  const hmac = crypto.createHmac('sha1', key).update(counter).digest();
  const offset = hmac[hmac.length - 1] & 0x0f;
  const binary = ((hmac[offset] & 0x7f) << 24)
    | ((hmac[offset + 1] & 0xff) << 16)
    | ((hmac[offset + 2] & 0xff) << 8)
    | (hmac[offset + 3] & 0xff);
  return String(binary % 1000000).padStart(6, '0');
}

function verifyTotp(secret, code) {
  const cleanCode = String(code || '').trim().replace(/\s+/g, '');
  if (!/^\d{6}$/.test(cleanCode)) {
    return false;
  }
  const currentStep = Math.floor(Date.now() / 30000);
  return [-1, 0, 1].some((drift) => totpCode(secret, currentStep + drift) === cleanCode);
}

function securityState() {
  const state = readWalletState();
  return {
    totpEnabled: Boolean(state.security && state.security.totpEnabled),
    totpSecret: state.security && state.security.totpSecret ? state.security.totpSecret : ''
  };
}

function existingWalletDataPresent() {
  const walletDir = path.join(nodeDataDir(), 'wallets', WALLET_NAME);
  try {
    return fs.existsSync(walletDir) && fs.readdirSync(walletDir).length > 0;
  } catch (_error) {
    return false;
  }
}

function walletConfigured() {
  const state = readWalletState();
  if (state.walletCreated) {
    return true;
  }
  if (existingWalletDataPresent()) {
    writeWalletState({ ...state, walletCreated: true });
    appendStartupLog('wallet setup marker restored from existing wallet data.');
    return true;
  }
  return false;
}

function waitForNodeExit(timeoutMs = 12000) {
  const processToWaitFor = nodeProcess;
  if (!processToWaitFor) {
    return Promise.resolve(true);
  }
  return new Promise((resolve) => {
    const timeout = setTimeout(() => {
      processToWaitFor.removeListener('exit', onExit);
      resolve(false);
    }, timeoutMs);
    function onExit() {
      clearTimeout(timeout);
      resolve(true);
    }
    processToWaitFor.once('exit', onExit);
  });
}

function requireTotpVerified(code) {
  const security = securityState();
  if (!security.totpEnabled) {
    return;
  }
  if (!verifyTotp(security.totpSecret, code)) {
    throw new Error('Enter the current 6-digit authenticator code to continue.');
  }
}

function isPortAvailable(port) {
  return new Promise((resolve) => {
    const server = net.createServer();
    server.once('error', () => resolve(false));
    server.once('listening', () => {
      server.close(() => resolve(true));
    });
    server.listen(port, RPC_HOST);
  });
}

async function findAvailablePort(preferredPort) {
  for (let port = preferredPort; port <= preferredPort + 100; port += 1) {
    if (await isPortAvailable(port)) {
      return port;
    }
  }
  throw new Error(`No private local RPC port available from ${preferredPort} to ${preferredPort + 100}.`);
}

async function writeNodeConfig() {
  fs.mkdirSync(nodeDataDir(), { recursive: true });
  currentRpcPort = await findAvailablePort(DEFAULT_RPC_PORT);
  currentListenEnabled = await isPortAvailable(P2P_PORT);
  const seedNodes = currentListenEnabled ? [PUBLIC_P2P, PUBLIC_P2P_IP, PUBLIC_P2P_BACKUP] : [PUBLIC_P2P, PUBLIC_P2P_IP, PUBLIC_P2P_BACKUP, LOCAL_P2P];
  const conf = [
    'server=1',
    `listen=${currentListenEnabled ? 1 : 0}`,
    ...(currentListenEnabled ? [`port=${P2P_PORT}`] : []),
    `rpcport=${currentRpcPort}`,
    `rpcbind=${RPC_HOST}`,
    'rpcallowip=127.0.0.1',
    ...seedNodes.map((seedNode) => `addnode=${seedNode}`),
    'txindex=1',
    'fallbackfee=0.0001',
    ''
  ].join(os.EOL);
  fs.writeFileSync(path.join(nodeDataDir(), 'hobbyhash.conf'), conf, { mode: 0o600 });
}

function appendNodeLog(message) {
  appendStartupLog(`node: ${message.toString().trim()}`);
  if (mainWindow && !mainWindow.isDestroyed()) {
    mainWindow.webContents.send('node:log', message.toString());
  }
}

function readRpcCookie() {
  try {
    const cookie = fs.readFileSync(rpcCookiePath(), 'utf8').trim();
    if (!cookie || !cookie.includes(':')) {
      throw new Error('Local RPC cookie is not ready yet.');
    }
    return cookie;
  } catch (_error) {
    throw new Error('Local node RPC cookie is not ready yet. The node may still be starting.');
  }
}

async function tryAdoptExistingNode() {
  if (await isPortAvailable(DEFAULT_RPC_PORT)) {
    return false;
  }

  const previousRpcPort = currentRpcPort;
  currentRpcPort = DEFAULT_RPC_PORT;
  try {
    const info = await rpcCall('getblockchaininfo');
    const network = await rpcCall('getnetworkinfo').catch(() => null);
    adoptedExistingNode = true;
    currentListenEnabled = true;
    appendNodeLog(`Found an already-running local HobbyHash node on ${RPC_HOST}:${DEFAULT_RPC_PORT}; using it instead of starting a second node. Current height: ${info.blocks}, peers: ${network ? network.connections : 'unknown'}.`);
    await ensureSeedConnections();
    return true;
  } catch (error) {
    appendStartupLog(`Could not adopt node on ${DEFAULT_RPC_PORT}: ${error.message}`);
    currentRpcPort = previousRpcPort;
    return false;
  }
}

async function ensureSeedConnections() {
  const nodes = [PUBLIC_P2P, PUBLIC_P2P_IP, PUBLIC_P2P_BACKUP];
  if (!currentListenEnabled) {
    nodes.push(LOCAL_P2P);
  }

  for (const node of nodes) {
    try {
      await rpcCall('addnode', [node, 'onetry']);
      appendNodeLog(`Requested peer connection to ${node}.`);
    } catch (error) {
      appendStartupLog(`addnode ${node} failed: ${error.message}`);
    }
  }
}

async function startNode() {
  if (stoppingNode) {
    throw new Error('The local node is still stopping. Try Start again in a few seconds.');
  }
  if (nodeProcess) {
    return { started: true, alreadyRunning: true };
  }

  if (await tryAdoptExistingNode()) {
    return { started: true, alreadyRunning: true, adoptedExistingNode: true, rpcPort: currentRpcPort, listenEnabled: currentListenEnabled };
  }

  await writeNodeConfig();
  adoptedExistingNode = false;
  const binary = nodeBinaryPath();
  if (!fs.existsSync(binary)) {
    throw new Error(`Bundled HOBC node not found: ${binary}`);
  }

  if (currentRpcPort !== DEFAULT_RPC_PORT) {
    appendNodeLog(`RPC port ${DEFAULT_RPC_PORT} is already in use. Using private local RPC port ${currentRpcPort} for this wallet session.`);
  }
  if (!currentListenEnabled) {
    appendNodeLog(`P2P port ${P2P_PORT} is already in use. Starting this wallet node in outbound-only P2P mode.`);
  }

  nodeProcess = spawn(binary, [
    `-datadir=${nodeDataDir()}`,
    '-printtoconsole',
    '-server=1'
  ], {
    windowsHide: true,
    stdio: ['ignore', 'pipe', 'pipe']
  });

  nodeProcess.stdout.on('data', appendNodeLog);
  nodeProcess.stderr.on('data', appendNodeLog);
  nodeProcess.on('exit', (code) => {
    appendNodeLog(`HobbyHash node stopped with code ${code}.`);
    nodeProcess = null;
  });

  setTimeout(() => {
    ensureSeedConnections().catch((error) => appendStartupLog(`ensureSeedConnections failed: ${error.message}`));
  }, 3000);

  return { started: true, alreadyRunning: false, adoptedExistingNode: false, rpcPort: currentRpcPort, listenEnabled: currentListenEnabled };
}

async function stopNode() {
  if (stoppingNode) {
    const stopped = await waitForNodeExit(12000);
    return { stopped, alreadyStopping: true, message: stopped ? 'Wallet/node stopped.' : 'The local node is still stopping.' };
  }
  if (!nodeProcess) {
    if (adoptedExistingNode) {
      stoppingNode = true;
      try {
        await rpcCall('stop');
        adoptedExistingNode = false;
        return { stopped: true, adoptedExistingNode: true, graceful: true };
      } catch (error) {
        appendStartupLog(`Could not stop adopted local node: ${error.message}`);
        return { stopped: false, adoptedExistingNode: true, message: 'Could not stop the already-running local node. Close that node separately if it stays running.' };
      } finally {
        stoppingNode = false;
      }
    }
    return { stopped: true, alreadyStopped: true };
  }
  stoppingNode = true;
  try {
    await rpcCall('stop');
    const exited = await waitForNodeExit(12000);
    if (!exited && nodeProcess) {
      appendStartupLog('RPC stop did not exit in time; killing node process.');
      nodeProcess.kill();
      await waitForNodeExit(5000);
    }
    nodeProcess = null;
    return { stopped: true, graceful: true };
  } catch (error) {
    appendStartupLog(`RPC stop failed, killing node process: ${error.message}`);
    nodeProcess.kill();
    await waitForNodeExit(5000);
    nodeProcess = null;
    return { stopped: true, graceful: false };
  } finally {
    stoppingNode = false;
  }
}

function rpcCall(method, params = [], wallet = null) {
  const cookie = readRpcCookie();
  const body = JSON.stringify({
    jsonrpc: '1.0',
    id: 'hobbyhash-wallet',
    method,
    params
  });
  const rpcPath = wallet ? `/wallet/${encodeURIComponent(wallet)}` : '/';

  return new Promise((resolve, reject) => {
    const request = http.request({
      host: RPC_HOST,
      port: currentRpcPort,
      path: rpcPath,
      method: 'POST',
      timeout: 10000,
      headers: {
        Authorization: `Basic ${Buffer.from(cookie).toString('base64')}`,
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(body)
      }
    }, (response) => {
      const chunks = [];
      response.on('data', (chunk) => chunks.push(chunk));
      response.on('end', () => {
        const raw = Buffer.concat(chunks).toString('utf8');
        try {
          const parsed = JSON.parse(raw);
          if (parsed.error) {
            reject(new Error(parsed.error.message || 'RPC returned an error.'));
            return;
          }
          resolve(parsed.result);
        } catch (_error) {
          reject(new Error(`Local node returned HTTP ${response.statusCode}: ${raw.slice(0, 200)}`));
        }
      });
    });

    request.on('timeout', () => request.destroy(new Error('Local node RPC timed out. It may still be starting.')));
    request.on('error', reject);
    request.write(body);
    request.end();
  });
}

async function ensureWalletLoaded() {
  const wallets = await rpcCall('listwallets');
  if (wallets.includes(WALLET_NAME)) {
    return true;
  }

  const walletDir = await rpcCall('listwalletdir').catch(() => null);
  const walletNames = walletDir && Array.isArray(walletDir.wallets)
    ? walletDir.wallets.map((walletEntry) => walletEntry.name)
    : [];
  if (walletNames.includes(WALLET_NAME)) {
    try {
      await rpcCall('loadwallet', [WALLET_NAME, true]);
    } catch (error) {
      if (!/already loaded/i.test(error.message)) {
        await rpcCall('loadwallet', [WALLET_NAME]);
      }
    }
    return true;
  }

  const dir = path.join(nodeDataDir(), 'wallets', WALLET_NAME);
  if (fs.existsSync(dir)) {
    try {
      await rpcCall('loadwallet', [WALLET_NAME, true]);
    } catch (error) {
      if (!/already loaded/i.test(error.message)) {
        await rpcCall('loadwallet', [WALLET_NAME]);
      }
    }
    return true;
  }

  return false;
}

async function requireWalletLoaded() {
  const loaded = await ensureWalletLoaded();
  if (!loaded) {
    throw new Error('Create or restore an encrypted HobbyHash wallet before using Receive, Send, or Backup.');
  }
}

async function walletSummary() {
  const info = await rpcCall('getblockchaininfo');
  const network = await rpcCall('getnetworkinfo');
  const peers = await rpcCall('getpeerinfo').catch(() => []);
  const remoteChain = await remoteChainStatus().catch((error) => {
    appendStartupLog(`remote chain status unavailable: ${error.message}`);
    return null;
  });
  const loaded = await ensureWalletLoaded();
  if (network.connections === 0) {
    ensureSeedConnections().catch((error) => appendStartupLog(`peer retry failed: ${error.message}`));
  }
  let wallet = null;
  if (loaded) {
    const transactions = await rpcCall('listtransactions', ['*', 1000, 0, true], WALLET_NAME);
    const receiveAddresses = await rpcCall('listreceivedbyaddress', [0, true, true, '', false], WALLET_NAME).catch(() => []);
    const walletInfo = await rpcCall('getwalletinfo', [], WALLET_NAME).catch(() => null);
    const balances = await rpcCall('getbalances', [], WALLET_NAME).catch(() => null);
    const unspent = await rpcCall('listunspent', [0, 9999999, []], WALLET_NAME).catch(() => []);
    wallet = {
      balance: await rpcCall('getbalance', [], WALLET_NAME),
      walletInfo,
      balances,
      unconfirmedBalance: walletInfo ? Number(walletInfo.unconfirmed_balance || 0) : 0,
      immatureBalance: walletInfo ? Number(walletInfo.immature_balance || 0) : 0,
      spendableBalance: balances && balances.mine ? Number(balances.mine.trusted || 0) : 0,
      pendingDeposits: transactions.filter((tx) => Number(tx.amount || 0) > 0 && Number(tx.confirmations || 0) <= 0).length,
      pendingWithdrawals: transactions.filter((tx) => Number(tx.amount || 0) < 0 && Number(tx.confirmations || 0) <= 0).length,
      utxoCount: Array.isArray(unspent) ? unspent.length : 0,
      primaryReceive: receiveAddresses.length ? receiveAddresses[0] : null,
      receiveAddresses,
      transactions: transactions.map((tx) => ({
        ...tx,
        explorerUrl: `${EXPLORER_URL}?q=${encodeURIComponent(tx.txid || '')}`
      }))
    };
  }
  return {
    info,
    network,
    peers: peers.map((peer) => ({
      address: peer.addr,
      inbound: Boolean(peer.inbound),
      syncedBlocks: peer.synced_blocks,
      connectionType: peer.connection_type || 'unknown'
    })),
    wallet,
    ports: {
      p2p: P2P_PORT,
      rpc: currentRpcPort,
      rpcHost: RPC_HOST,
      publicP2P: PUBLIC_P2P,
      listenEnabled: currentListenEnabled,
      adoptedExistingNode,
      explorerUrl: EXPLORER_URL
    },
    security: {
      totpEnabled: securityState().totpEnabled,
      walletConfigured: walletConfigured()
    },
    remoteChain
  };
}

function cleanAddressLabel(label) {
  const value = String(label || '').trim();
  return value === '' ? 'Receive Wallet' : value.slice(0, 80);
}

async function createWallet(password) {
  const passphrase = String(password || '');
  if (passphrase.length < 10) {
    throw new Error('Choose a wallet password with at least 10 characters.');
  }

  if (await ensureWalletLoaded()) {
    throw new Error('A HobbyHash wallet already exists and is loaded.');
  }

  try {
    await rpcCall('createwallet', [WALLET_NAME, false, false, '', false, true, true]);
  } catch (error) {
    if (!/too many parameters|invalid parameter|method not found/i.test(error.message)) {
      throw error;
    }
    await rpcCall('createwallet', [WALLET_NAME, false, false, '', false, true]);
  }
  const primaryAddress = await rpcCall('getnewaddress', ['Main Wallet', 'bech32'], WALLET_NAME);
  await rpcCall('encryptwallet', [passphrase], WALLET_NAME);
  writeWalletState({ ...readWalletState(), walletCreated: true, backupWarningAccepted: false, primaryReceiveAddress: primaryAddress });
  return {
    created: true,
    primaryAddress,
    note: 'Wallet created, encrypted, and assigned a Main Wallet receive address. The node may restart the wallet internally after encryption.'
  };
}

async function createReceiveAddress(label) {
  await requireWalletLoaded();
  return rpcCall('getnewaddress', [cleanAddressLabel(label), 'bech32'], WALLET_NAME);
}

async function renameReceiveAddress(address, label) {
  await requireWalletLoaded();
  const cleanAddress = String(address || '').trim();
  if (!cleanAddress) {
    throw new Error('Select a receive address to rename.');
  }
  await rpcCall('setlabel', [cleanAddress, cleanAddressLabel(label)], WALLET_NAME);
  return { renamed: true };
}

async function unlockWallet(password, seconds = 120) {
  await rpcCall('walletpassphrase', [String(password || ''), Number(seconds)], WALLET_NAME);
  return { unlocked: true };
}

function securityStatus() {
  return { totpEnabled: securityState().totpEnabled, walletConfigured: walletConfigured() };
}

function beginTotpSetup() {
  const secret = generateTotpSecret();
  writeWalletState({ ...readWalletState(), pendingTotpSecret: secret });
  return {
    secret,
    account: 'HobbyHash Wallet',
    issuer: 'HobbyHash Coin'
  };
}

function enableTotp(code) {
  const state = readWalletState();
  const secret = state.pendingTotpSecret;
  if (!secret) {
    throw new Error('Start authenticator setup first.');
  }
  if (!verifyTotp(secret, code)) {
    throw new Error('That authenticator code did not match. Check the manual secret and try again.');
  }
  const nextState = {
    ...state,
    pendingTotpSecret: undefined,
    security: {
      ...(state.security || {}),
      totpEnabled: true,
      totpSecret: secret
    }
  };
  delete nextState.pendingTotpSecret;
  writeWalletState(nextState);
  return { enabled: true };
}

function disableTotp(code) {
  requireTotpVerified(code);
  const state = readWalletState();
  writeWalletState({
    ...state,
    security: {
      ...(state.security || {}),
      totpEnabled: false,
      totpSecret: ''
    }
  });
  return { disabled: true };
}

function unlockWithTotp(code) {
  requireTotpVerified(code);
  if (mainWindow && !mainWindow.isDestroyed()) {
    mainWindow.setMenuBarVisibility(true);
  }
  return { unlocked: true };
}

async function createWindow() {
  appendStartupLog(`creating main window, packaged=${app.isPackaged}, resources=${process.resourcesPath}`);
  mainWindow = new BrowserWindow({
    width: 1380,
    height: 820,
    minWidth: 1180,
    minHeight: 700,
    title: 'HobbyHash Wallet',
    icon: appIconPath(),
    backgroundColor: '#050708',
    show: true,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      webSecurity: true,
      allowRunningInsecureContent: false,
      devTools: false
    }
  });

  buildApplicationMenu();
  mainWindow.setMenuBarVisibility(!securityState().totpEnabled);
  mainWindow.center();
  mainWindow.show();
  mainWindow.focus();
  mainWindow.webContents.on('did-fail-load', (_event, errorCode, errorDescription) => {
    appendStartupLog(`window load failed ${errorCode}: ${errorDescription}`);
  });
  mainWindow.webContents.on('render-process-gone', (_event, details) => {
    appendStartupLog(`renderer gone: ${details.reason}`);
  });
  try {
    await mainWindow.loadFile(path.join(__dirname, 'index.html'));
    appendStartupLog('main window loaded index.html');
    mainWindow.show();
    mainWindow.focus();
    setTimeout(() => {
      checkForUpdates({ manual: false }).catch((error) => appendStartupLog(`scheduled update check failed: ${error.message}`));
    }, 5000);
  } catch (error) {
    appendStartupLog(`loadFile failed: ${error.message}`);
    await mainWindow.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(`
      <body style="background:#050708;color:#f7f5ed;font-family:Segoe UI,sans-serif;padding:32px">
        <h1>HobbyHash Wallet could not load</h1>
        <p>${error.message}</p>
        <p>Startup log: ${startupLogPath()}</p>
      </body>
    `)}`);
    mainWindow.show();
  }
  mainWindow.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));
  mainWindow.webContents.on('will-navigate', (event) => event.preventDefault());
}

app.whenReady().then(async () => {
  appendStartupLog('app ready');
  session.defaultSession.setPermissionRequestHandler((_webContents, _permission, callback) => callback(false));
  restoreWalletDataAfterUpdateIfNeeded();
  await createWindow();
}).catch((error) => {
  appendStartupLog(`fatal startup error: ${error.stack || error.message}`);
  dialog.showErrorBox('HobbyHash Wallet startup error', error.stack || error.message);
});

app.on('second-instance', () => {
  if (mainWindow) {
    if (mainWindow.isMinimized()) {
      mainWindow.restore();
    }
    mainWindow.show();
    mainWindow.focus();
  }
});

process.on('uncaughtException', (error) => {
  appendStartupLog(`uncaught exception: ${error.stack || error.message}`);
  dialog.showErrorBox('HobbyHash Wallet error', error.stack || error.message);
});

process.on('unhandledRejection', (error) => {
  const message = error && (error.stack || error.message) ? (error.stack || error.message) : String(error);
  appendStartupLog(`unhandled rejection: ${message}`);
});

app.on('before-quit', () => {
  stopNode();
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

ipcMain.handle('node:start', async () => startNode());
ipcMain.handle('node:stop', async () => stopNode());
ipcMain.handle('wallet:summary', async () => walletSummary());
ipcMain.handle('wallet:create', async (_event, password) => createWallet(password));
ipcMain.handle('wallet:unlock', async (_event, password, seconds) => unlockWallet(password, seconds));
ipcMain.handle('wallet:receive', async (_event, label) => createReceiveAddress(label));
ipcMain.handle('wallet:rename-address', async (_event, { address, label }) => renameReceiveAddress(address, label));
ipcMain.handle('wallet:send', async (_event, { address, amount, password, totpCode: code }) => {
  await requireWalletLoaded();
  requireTotpVerified(code);
  await unlockWallet(password, 120);
  return rpcCall('sendtoaddress', [String(address || '').trim(), Number(amount)], WALLET_NAME);
});
ipcMain.handle('wallet:backup-folder', async () => {
  await shell.openPath(path.join(nodeDataDir(), 'wallets'));
  return true;
});
ipcMain.handle('wallet:backup-save', async (_event, code) => {
  await requireWalletLoaded();
  requireTotpVerified(code);
  const result = await dialog.showSaveDialog(mainWindow, {
    title: 'Save HobbyHash wallet backup',
    defaultPath: 'HobbyHash-wallet-backup.dat',
    filters: [{ name: 'Wallet backup', extensions: ['dat'] }]
  });
  if (result.canceled || !result.filePath) {
    return { canceled: true };
  }
  await rpcCall('backupwallet', [result.filePath], WALLET_NAME);
  return { saved: true, filePath: result.filePath };
});
ipcMain.handle('wallet:restore-backup', async (_event, code) => {
  requireTotpVerified(code);
  if (await ensureWalletLoaded()) {
    throw new Error('A HobbyHash wallet already exists. Save a backup from the Backup page before replacing wallet data manually.');
  }
  const result = await dialog.showOpenDialog(mainWindow, {
    title: 'Restore HobbyHash wallet backup',
    properties: ['openFile'],
    filters: [{ name: 'Wallet backup', extensions: ['dat'] }]
  });
  if (result.canceled || !result.filePaths.length) {
    return { canceled: true };
  }
  await rpcCall('restorewallet', [WALLET_NAME, result.filePaths[0]]);
  writeWalletState({ ...readWalletState(), walletCreated: true });
  return { restored: true, filePath: result.filePaths[0] };
});
ipcMain.handle('app:open-explorer', async (_event, value) => {
  const query = String(value || '').trim();
  if (!query) {
    return false;
  }
  await shell.openExternal(`${EXPLORER_URL}?q=${encodeURIComponent(query)}`);
  return true;
});
ipcMain.handle('app:open-homepage', async () => {
  await shell.openExternal(HOMEPAGE_URL);
  return true;
});
ipcMain.handle('app:copy', async (_event, value) => {
  clipboard.writeText(String(value || ''));
  return true;
});
ipcMain.handle('security:status', async () => securityStatus());
ipcMain.handle('security:totp-unlock', async (_event, code) => unlockWithTotp(code));
ipcMain.handle('security:totp-begin', async () => beginTotpSetup());
ipcMain.handle('security:totp-enable', async (_event, code) => enableTotp(code));
ipcMain.handle('security:totp-disable', async (_event, code) => disableTotp(code));
ipcMain.handle('app:paths', async () => ({
  dataDir: nodeDataDir(),
  configFile: path.join(nodeDataDir(), 'hobbyhash.conf'),
  nodeBinary: nodeBinaryPath(),
  rpcHost: RPC_HOST,
  rpcPort: currentRpcPort,
  preferredRpcPort: DEFAULT_RPC_PORT,
  p2pPort: P2P_PORT,
  listenEnabled: currentListenEnabled,
  version: APP_VERSION
}));
ipcMain.handle('app:launch-info', async () => ({
  updated: process.argv.includes('--updated')
}));
