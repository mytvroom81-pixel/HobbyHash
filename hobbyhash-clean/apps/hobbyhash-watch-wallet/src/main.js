const { app, BrowserWindow, ipcMain, session } = require('electron');
const http = require('http');
const path = require('path');

let mainWindow = null;

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1180,
    height: 820,
    minWidth: 980,
    minHeight: 700,
    title: 'HobbyHash Watch Wallet',
    backgroundColor: '#050708',
    show: false,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      webSecurity: true,
      allowRunningInsecureContent: false,
      devTools: false
    }
  });

  mainWindow.setMenuBarVisibility(false);
  mainWindow.loadFile(path.join(__dirname, 'index.html'));
  mainWindow.once('ready-to-show', () => mainWindow.show());
  mainWindow.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));
  mainWindow.webContents.on('will-navigate', (event) => event.preventDefault());
}

function isPrivateRpcHost(host) {
  const value = String(host || '').trim().toLowerCase();
  return value === '127.0.0.1'
    || value === 'localhost'
    || value === '::1'
    || value.startsWith('192.168.')
    || value.startsWith('10.')
    || /^172\.(1[6-9]|2\d|3[0-1])\./.test(value);
}

function rpcCall(settings, method, params = []) {
  const host = String(settings.host || '127.0.0.1').trim();
  const port = Number(settings.port || 18762);
  const username = String(settings.username || '');
  const password = String(settings.password || '');

  if (!isPrivateRpcHost(host)) {
    throw new Error('RPC host must be localhost or a private LAN address. Do not connect to public RPC.');
  }
  if (host === 'hobbyhashcoin.com' || port !== 18762) {
    throw new Error('Default mainnet RPC is 127.0.0.1:18762. Do not use public RPC endpoints.');
  }
  if (!username || !password) {
    throw new Error('Enter RPC credentials for your own local/private HOBC node.');
  }

  const body = JSON.stringify({
    jsonrpc: '1.0',
    id: 'hobc-watch-wallet',
    method,
    params
  });

  return new Promise((resolve, reject) => {
    const request = http.request({
      host,
      port,
      method: 'POST',
      path: '/',
      timeout: 8000,
      headers: {
        Authorization: `Basic ${Buffer.from(`${username}:${password}`).toString('base64')}`,
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
          reject(new Error(`RPC returned non-JSON response with HTTP ${response.statusCode}.`));
        }
      });
    });

    request.on('timeout', () => {
      request.destroy(new Error('RPC request timed out.'));
    });
    request.on('error', reject);
    request.write(body);
    request.end();
  });
}

app.whenReady().then(() => {
  session.defaultSession.setPermissionRequestHandler((_webContents, _permission, callback) => {
    callback(false);
  });
  createWindow();
  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    }
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

ipcMain.handle('rpc:test', async (_event, settings) => {
  return rpcCall(settings, 'getblockchaininfo', []);
});

ipcMain.handle('rpc:broadcast', async (_event, settings, signedHex) => {
  const hex = String(signedHex || '').trim();
  if (!/^[0-9a-fA-F]+$/.test(hex)) {
    throw new Error('Signed transaction hex is invalid.');
  }
  return rpcCall(settings, 'sendrawtransaction', [hex]);
});
