const watchApi = window.hobcWatchWallet;

let watchOnly = null;

const elements = {
  alert: document.querySelector('#app-alert'),
  navTabs: document.querySelectorAll('.nav-tab'),
  views: document.querySelectorAll('.view'),
  rpcHost: document.querySelector('#rpc-host'),
  rpcPort: document.querySelector('#rpc-port'),
  rpcUser: document.querySelector('#rpc-user'),
  rpcPass: document.querySelector('#rpc-pass'),
  testRpc: document.querySelector('#test-rpc'),
  watchFile: document.querySelector('#watch-file'),
  watchJson: document.querySelector('#watch-json'),
  importWatch: document.querySelector('#import-watch'),
  addressList: document.querySelector('#address-list'),
  signedHex: document.querySelector('#signed-hex'),
  broadcastTx: document.querySelector('#broadcast-tx'),
  broadcastResult: document.querySelector('#broadcast-result')
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

function rpcSettings() {
  return {
    host: elements.rpcHost.value.trim() || '127.0.0.1',
    port: Number(elements.rpcPort.value || watchApi.chainConfig.mainnet.rpcPort),
    username: elements.rpcUser.value,
    password: elements.rpcPass.value
  };
}

function renderAddresses() {
  elements.addressList.textContent = '';
  if (!watchOnly) {
    elements.addressList.textContent = 'No watch-only file imported yet.';
    return;
  }

  watchOnly.addresses.forEach((address) => {
    const row = document.createElement('div');
    row.className = 'address-row';

    const index = document.createElement('span');
    index.className = 'address-index';
    index.textContent = `#${address.index}`;

    const code = document.createElement('code');
    code.textContent = address.address;

    const copy = document.createElement('button');
    copy.type = 'button';
    copy.textContent = 'Copy';
    copy.addEventListener('click', () => {
      watchApi.copyText(address.address);
      showAlert(`Copied ${address.address}`);
    });

    row.append(index, code, copy);
    elements.addressList.appendChild(row);
  });
}

elements.navTabs.forEach((tab) => {
  tab.addEventListener('click', () => showView(tab.dataset.view));
});

elements.testRpc.addEventListener('click', async () => {
  try {
    const info = await watchApi.testRpc(rpcSettings());
    showAlert(`Connected to local/private HOBC node. Chain: ${info.chain || 'main'}, blocks: ${info.blocks ?? '-'}`);
  } catch (error) {
    showAlert(error.message, 'error');
  }
});

elements.watchFile.addEventListener('change', () => {
  const file = elements.watchFile.files && elements.watchFile.files[0];
  if (!file) {
    return;
  }

  const reader = new FileReader();
  reader.onload = () => {
    elements.watchJson.value = String(reader.result || '');
    showAlert(`Loaded ${file.name}.`);
  };
  reader.onerror = () => showAlert('Could not read the selected file.', 'error');
  reader.readAsText(file);
});

elements.importWatch.addEventListener('click', () => {
  try {
    watchOnly = watchApi.parseWatchOnlyFile(elements.watchJson.value);
    renderAddresses();
    showAlert(`Imported ${watchOnly.addresses.length} watch-only HOBC addresses.`);
  } catch (error) {
    showAlert(error.message, 'error');
  }
});

elements.broadcastTx.addEventListener('click', async () => {
  try {
    const txid = await watchApi.broadcast(rpcSettings(), elements.signedHex.value);
    elements.broadcastResult.textContent = JSON.stringify({ txid }, null, 2);
    showAlert('Signed transaction broadcast through your local/private HOBC node.');
  } catch (error) {
    elements.broadcastResult.textContent = error.message;
    showAlert(error.message, 'error');
  }
});
