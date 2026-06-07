const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('hobbyhashWallet', {
  startNode() {
    return ipcRenderer.invoke('node:start');
  },
  stopNode() {
    return ipcRenderer.invoke('node:stop');
  },
  summary() {
    return ipcRenderer.invoke('wallet:summary');
  },
  createWallet(password) {
    return ipcRenderer.invoke('wallet:create', password);
  },
  unlockWallet(password, seconds) {
    return ipcRenderer.invoke('wallet:unlock', password, seconds);
  },
  newReceiveAddress(label) {
    return ipcRenderer.invoke('wallet:receive', label);
  },
  renameAddress(address, label) {
    return ipcRenderer.invoke('wallet:rename-address', { address, label });
  },
  send(address, amount, password, totpCode) {
    return ipcRenderer.invoke('wallet:send', { address, amount, password, totpCode });
  },
  openBackupFolder() {
    return ipcRenderer.invoke('wallet:backup-folder');
  },
  saveBackup(totpCode) {
    return ipcRenderer.invoke('wallet:backup-save', totpCode);
  },
  restoreBackup(totpCode) {
    return ipcRenderer.invoke('wallet:restore-backup', totpCode);
  },
  paths() {
    return ipcRenderer.invoke('app:paths');
  },
  launchInfo() {
    return ipcRenderer.invoke('app:launch-info');
  },
  openExplorer(value) {
    return ipcRenderer.invoke('app:open-explorer', value);
  },
  openHomepage() {
    return ipcRenderer.invoke('app:open-homepage');
  },
  copy(text) {
    return ipcRenderer.invoke('app:copy', text);
  },
  securityStatus() {
    return ipcRenderer.invoke('security:status');
  },
  unlockTotp(code) {
    return ipcRenderer.invoke('security:totp-unlock', code);
  },
  beginTotpSetup() {
    return ipcRenderer.invoke('security:totp-begin');
  },
  enableTotp(code) {
    return ipcRenderer.invoke('security:totp-enable', code);
  },
  disableTotp(code) {
    return ipcRenderer.invoke('security:totp-disable', code);
  },
  onNodeLog(callback) {
    ipcRenderer.on('node:log', (_event, message) => callback(String(message || '')));
  },
  onNavigate(callback) {
    ipcRenderer.on('app:navigate', (_event, view) => callback(String(view || 'home')));
  },
  onStartNodeRequest(callback) {
    ipcRenderer.on('app:start-node-request', () => callback());
  },
  onNodeStopped(callback) {
    ipcRenderer.on('app:node-stopped', (_event, result) => callback(result || {}));
  },
  onUpdateDownloadProgress(callback) {
    ipcRenderer.on('update:download-progress', (_event, progress) => callback(progress || {}));
  }
});
