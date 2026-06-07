const walletApi = window.hobcWallet;

let sessionMnemonic = '';
let currentAddresses = [];
let selectedAddress = null;
let signedExport = null;

const elements = {
  alert: document.querySelector('#app-alert'),
  navTabs: document.querySelectorAll('.nav-tab'),
  views: document.querySelectorAll('.view'),
  panicLock: document.querySelector('#panic-lock'),
  generateWallet: document.querySelector('#generate-wallet'),
  mnemonicPanel: document.querySelector('#mnemonic-panel'),
  mnemonicWords: document.querySelector('#mnemonic-words'),
  savedPhraseConfirm: document.querySelector('#saved-phrase-confirm'),
  useCreatedWallet: document.querySelector('#use-created-wallet'),
  restorePhrase: document.querySelector('#restore-phrase'),
  restoreWallet: document.querySelector('#restore-wallet'),
  receiveEmpty: document.querySelector('#receive-empty'),
  receiveGrid: document.querySelector('#receive-grid'),
  addressList: document.querySelector('#address-list'),
  addressQr: document.querySelector('#address-qr'),
  selectedAddress: document.querySelector('#selected-address'),
  copySelectedAddress: document.querySelector('#copy-selected-address'),
  exportWatchOnly: document.querySelector('#export-watch-only'),
  unsignedFile: document.querySelector('#unsigned-file'),
  unsignedPayload: document.querySelector('#unsigned-payload'),
  signTransaction: document.querySelector('#sign-transaction'),
  signedOutput: document.querySelector('#signed-output'),
  exportSignedFile: document.querySelector('#export-signed-file'),
  savePassword: document.querySelector('#save-password'),
  saveEncryptedWallet: document.querySelector('#save-encrypted-wallet'),
  unlockPassword: document.querySelector('#unlock-password'),
  unlockWallet: document.querySelector('#unlock-wallet'),
  deleteEncryptedWallet: document.querySelector('#delete-encrypted-wallet'),
  storageLocation: document.querySelector('#storage-location')
};

function showAlert(message, type = 'success') {
  elements.alert.textContent = message;
  elements.alert.className = `alert ${type}`;
  elements.alert.hidden = false;
}

function clearAlert() {
  elements.alert.hidden = true;
  elements.alert.textContent = '';
  elements.alert.className = 'alert';
}

function showView(viewName) {
  elements.navTabs.forEach((tab) => {
    tab.classList.toggle('is-active', tab.dataset.view === viewName);
  });
  elements.views.forEach((view) => {
    view.classList.toggle('is-active', view.id === `view-${viewName}`);
  });
  clearAlert();
}

function setSessionWallet(mnemonic, message) {
  walletApi.validateMnemonic(mnemonic);
  sessionMnemonic = mnemonic.trim().toLowerCase().replace(/\s+/g, ' ');
  currentAddresses = walletApi.deriveAddresses(sessionMnemonic, 20, 0);
  renderAddresses();
  showView('receive');
  showAlert(message);
}

function renderMnemonic(mnemonic) {
  elements.mnemonicWords.textContent = '';
  mnemonic.split(' ').forEach((word) => {
    const item = document.createElement('li');
    item.textContent = word;
    elements.mnemonicWords.appendChild(item);
  });
  elements.mnemonicPanel.hidden = false;
}

async function selectAddress(address) {
  selectedAddress = address;
  elements.addressQr.src = await walletApi.qrForAddress(address.address);
  elements.addressQr.hidden = false;
  elements.selectedAddress.textContent = address.address;

  document.querySelectorAll('.address-row').forEach((row) => {
    row.classList.toggle('is-selected', row.dataset.address === address.address);
  });
}

function renderAddresses() {
  elements.addressList.textContent = '';

  if (!currentAddresses.length) {
    elements.receiveEmpty.hidden = false;
    elements.receiveGrid.hidden = true;
    elements.addressQr.hidden = true;
    elements.selectedAddress.textContent = '';
    return;
  }

  elements.receiveEmpty.hidden = true;
  elements.receiveGrid.hidden = false;

  currentAddresses.forEach((address) => {
    const row = document.createElement('div');
    row.className = 'address-row';
    row.dataset.address = address.address;

    const index = document.createElement('span');
    index.className = 'address-index';
    index.textContent = `#${address.index}`;

    const code = document.createElement('code');
    code.textContent = address.address;

    const selectButton = document.createElement('button');
    selectButton.type = 'button';
    selectButton.textContent = 'QR';
    selectButton.addEventListener('click', () => {
      selectAddress(address).catch((error) => showAlert(error.message, 'error'));
    });

    const copyButton = document.createElement('button');
    copyButton.type = 'button';
    copyButton.textContent = 'Copy';
    copyButton.addEventListener('click', () => {
      walletApi.copyText(address.address);
      showAlert(`Copied ${address.address}`);
    });

    row.append(index, code, selectButton, copyButton);
    elements.addressList.appendChild(row);
  });

  selectAddress(currentAddresses[0]).catch((error) => showAlert(error.message, 'error'));
}

function clearSensitiveScreenData() {
  sessionMnemonic = '';
  currentAddresses = [];
  selectedAddress = null;
  signedExport = null;

  elements.mnemonicWords.textContent = '';
  elements.mnemonicPanel.hidden = true;
  elements.savedPhraseConfirm.checked = false;
  elements.restorePhrase.value = '';
  elements.unsignedPayload.value = '';
  elements.signedOutput.textContent = 'No signed transaction yet.';
  elements.exportSignedFile.disabled = true;
  elements.savePassword.value = '';
  elements.unlockPassword.value = '';
  elements.addressQr.hidden = true;
  elements.selectedAddress.textContent = '';
  renderAddresses();
  showAlert('Panic lock cleared displayed sensitive data from this screen.');
}

async function refreshStorageInfo() {
  const info = await walletApi.storageInfo();
  elements.storageLocation.textContent = info.exists
    ? `Encrypted wallet file exists at: ${info.filePath}`
    : `No encrypted wallet file exists yet. Storage path: ${info.filePath}`;
}

function exportSignedFile() {
  if (!signedExport) {
    return;
  }

  const blob = new Blob([JSON.stringify(signedExport, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = signedExport.fileName || 'hobc-signed-transaction.json';
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

function exportJsonFile(payload, fileName) {
  const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = fileName;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

function exportWatchOnlyFile() {
  if (!currentAddresses.length) {
    showAlert('Create, restore, or unlock a wallet before exporting watch-only data.', 'error');
    return;
  }

  exportJsonFile({
    format: 'hobc-watch-only-v1',
    coinName: walletApi.networkConfig.coinName,
    ticker: walletApi.networkConfig.ticker,
    network: 'mainnet',
    publicP2P: walletApi.networkConfig.publicP2P,
    p2pPort: walletApi.networkConfig.mainnet.p2pPort,
    rpcPort: walletApi.networkConfig.mainnet.rpcPort,
    rpcWarning: 'RPC is private/local only. Do not expose RPC ports to the public internet.',
    exportedAt: new Date().toISOString(),
    derivation: walletApi.networkConfig.derivation,
    addresses: currentAddresses
  }, 'HobbyHash-Watch-Only-Mainnet.json');
  showAlert('Watch-only file exported. It contains public receive addresses only.');
}

elements.navTabs.forEach((tab) => {
  tab.addEventListener('click', () => showView(tab.dataset.view));
});

elements.generateWallet.addEventListener('click', () => {
  try {
    const mnemonic = walletApi.generateMnemonic();
    sessionMnemonic = mnemonic;
    renderMnemonic(mnemonic);
    showAlert('New 24-word recovery phrase generated locally. Write it down before continuing.');
  } catch (error) {
    showAlert(error.message, 'error');
  }
});

elements.useCreatedWallet.addEventListener('click', () => {
  try {
    if (!elements.savedPhraseConfirm.checked) {
      throw new Error('Confirm that you saved the recovery phrase before continuing.');
    }
    setSessionWallet(sessionMnemonic, 'Wallet is active in memory only. Receive addresses are ready.');
  } catch (error) {
    showAlert(error.message, 'error');
  }
});

elements.restoreWallet.addEventListener('click', () => {
  try {
    setSessionWallet(elements.restorePhrase.value, 'Wallet restored in memory only. Receive addresses are ready.');
    elements.restorePhrase.value = '';
  } catch (error) {
    showAlert(error.message, 'error');
  }
});

elements.copySelectedAddress.addEventListener('click', () => {
  if (!selectedAddress) {
    showAlert('Select an address first.', 'error');
    return;
  }
  walletApi.copyText(selectedAddress.address);
  showAlert(`Copied ${selectedAddress.address}`);
});

elements.exportWatchOnly.addEventListener('click', exportWatchOnlyFile);

elements.unsignedFile.addEventListener('change', () => {
  const file = elements.unsignedFile.files && elements.unsignedFile.files[0];
  if (!file) {
    return;
  }

  const reader = new FileReader();
  reader.onload = () => {
    elements.unsignedPayload.value = String(reader.result || '');
    showAlert(`Loaded ${file.name} into the offline signing box.`);
  };
  reader.onerror = () => showAlert('Could not read the selected file.', 'error');
  reader.readAsText(file);
});

elements.signTransaction.addEventListener('click', async () => {
  try {
    if (!sessionMnemonic) {
      throw new Error('Create, restore, or unlock a wallet before signing.');
    }
    signedExport = await walletApi.signOfflineTransaction(sessionMnemonic, elements.unsignedPayload.value);
    elements.signedOutput.textContent = JSON.stringify(signedExport, null, 2);
    elements.exportSignedFile.disabled = false;
    showAlert(signedExport.summary);
  } catch (error) {
    showAlert(error.message, 'error');
  }
});

elements.exportSignedFile.addEventListener('click', exportSignedFile);

elements.saveEncryptedWallet.addEventListener('click', async () => {
  try {
    if (!sessionMnemonic) {
      throw new Error('Create, restore, or unlock a wallet before saving.');
    }
    const encrypted = walletApi.encryptMnemonic(sessionMnemonic, elements.savePassword.value);
    await walletApi.saveEncryptedWallet(encrypted);
    elements.savePassword.value = '';
    await refreshStorageInfo();
    showAlert('Encrypted wallet saved locally. Plaintext recovery phrase was not written to disk.');
  } catch (error) {
    showAlert(error.message, 'error');
  }
});

elements.unlockWallet.addEventListener('click', async () => {
  try {
    const encrypted = await walletApi.readEncryptedWallet();
    if (!encrypted) {
      throw new Error('No encrypted wallet file exists.');
    }
    const mnemonic = walletApi.decryptMnemonic(encrypted, elements.unlockPassword.value);
    elements.unlockPassword.value = '';
    setSessionWallet(mnemonic, 'Encrypted wallet unlocked locally and loaded into memory.');
  } catch (error) {
    showAlert('Unlock failed. Check the password and encrypted wallet file.', 'error');
  }
});

elements.deleteEncryptedWallet.addEventListener('click', async () => {
  try {
    await walletApi.deleteEncryptedWallet();
    await refreshStorageInfo();
    showAlert('Encrypted wallet file deleted.');
  } catch (error) {
    showAlert(error.message, 'error');
  }
});

elements.panicLock.addEventListener('click', clearSensitiveScreenData);

refreshStorageInfo().catch((error) => showAlert(error.message, 'error'));
renderAddresses();
