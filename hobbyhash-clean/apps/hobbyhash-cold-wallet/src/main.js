const { app, BrowserWindow, ipcMain, session } = require('electron');
const fs = require('fs');
const path = require('path');

const WALLET_FILE = 'hobc-cold-wallet.encrypted.json';

let mainWindow = null;

function walletPath() {
  return path.join(app.getPath('userData'), WALLET_FILE);
}

function installNetworkLockdown() {
  const deny = { urls: ['http://*/*', 'https://*/*', 'ftp://*/*', 'ws://*/*', 'wss://*/*'] };

  session.defaultSession.webRequest.onBeforeRequest(deny, (_details, callback) => {
    callback({ cancel: true });
  });

  session.defaultSession.setPermissionRequestHandler((_webContents, _permission, callback) => {
    callback(false);
  });
}

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1180,
    height: 820,
    minWidth: 980,
    minHeight: 700,
    title: 'HobbyHash Cold Wallet',
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

  mainWindow.once('ready-to-show', () => {
    mainWindow.show();
  });

  mainWindow.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));
  mainWindow.webContents.on('will-navigate', (event) => {
    event.preventDefault();
  });
}

app.whenReady().then(() => {
  installNetworkLockdown();
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

ipcMain.handle('storage:get-info', async () => {
  const filePath = walletPath();
  return {
    exists: fs.existsSync(filePath),
    filePath
  };
});

ipcMain.handle('storage:read-wallet', async () => {
  const filePath = walletPath();
  if (!fs.existsSync(filePath)) {
    return null;
  }
  return fs.readFileSync(filePath, 'utf8');
});

ipcMain.handle('storage:save-wallet', async (_event, encryptedPayload) => {
  if (typeof encryptedPayload !== 'string' || encryptedPayload.length < 40) {
    throw new Error('Encrypted wallet payload is invalid.');
  }

  const filePath = walletPath();
  fs.mkdirSync(path.dirname(filePath), { recursive: true, mode: 0o700 });
  fs.writeFileSync(filePath, encryptedPayload, { encoding: 'utf8', mode: 0o600 });
  return { saved: true, filePath };
});

ipcMain.handle('storage:delete-wallet', async () => {
  const filePath = walletPath();
  if (fs.existsSync(filePath)) {
    fs.rmSync(filePath, { force: true });
  }
  return { deleted: true };
});
