const { contextBridge, ipcRenderer, clipboard } = require('electron');
const chainConfig = require('./hobc-chain.json');

function parseWatchOnlyFile(rawText) {
  const payload = JSON.parse(String(rawText || ''));
  if (payload.format !== 'hobc-watch-only-v1') {
    throw new Error('Watch-only file format is not supported.');
  }
  if (payload.coinName !== 'HobbyHash Coin' || payload.ticker !== 'HOBC' || payload.network !== 'mainnet') {
    throw new Error('Watch-only file is not for HobbyHash Coin mainnet.');
  }
  if (!Array.isArray(payload.addresses) || payload.addresses.length === 0) {
    throw new Error('Watch-only file does not contain addresses.');
  }
  return payload;
}

contextBridge.exposeInMainWorld('hobcWatchWallet', {
  chainConfig,
  parseWatchOnlyFile,
  testRpc(settings) {
    return ipcRenderer.invoke('rpc:test', settings);
  },
  broadcast(settings, signedHex) {
    return ipcRenderer.invoke('rpc:broadcast', settings, signedHex);
  },
  copyText(text) {
    clipboard.writeText(String(text || ''));
    return true;
  }
});
