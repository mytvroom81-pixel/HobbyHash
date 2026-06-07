const wallet = window.hobbyhashWallet;

let lastAddress = '';
let refreshTimer = null;
let walletReady = false;
let nodeStarted = false;
let latestTransactions = [];
let latestReceiveAddresses = [];
let authenticatorEnabled = false;
let pendingAuthSecret = '';
let appUnlocked = false;
let walletConfigured = false;

const elements = {
  startupLoadingScreen: document.querySelector('#startup-loading-screen'),
  startupLoadingTitle: document.querySelector('#startup-loading-title'),
  startupLoadingMessage: document.querySelector('#startup-loading-message'),
  startupLoadingDetail: document.querySelector('#startup-loading-detail'),
  startupLoadingProgressBar: document.querySelector('#startup-loading-progress-bar'),
  startupLoadingProgressText: document.querySelector('#startup-loading-progress-text'),
  authLockScreen: document.querySelector('#auth-lock-screen'),
  startupAuthCode: document.querySelector('#startup-auth-code'),
  startupAuthUnlock: document.querySelector('#startup-auth-unlock'),
  startupAuthError: document.querySelector('#startup-auth-error'),
  alert: document.querySelector('#app-alert'),
  startWallet: document.querySelector('#start-wallet'),
  walletNav: document.querySelector('#wallet-nav'),
  homepageLink: document.querySelector('#homepage-link'),
  sidebarSetupNote: document.querySelector('#sidebar-setup-note'),
  navTabs: document.querySelectorAll('.nav-tab'),
  views: document.querySelectorAll('.view'),
  networkStatus: document.querySelector('#network-status'),
  syncStatus: document.querySelector('#sync-status'),
  balanceStatus: document.querySelector('#balance-status'),
  createPassword: document.querySelector('#create-password'),
  createWallet: document.querySelector('#create-wallet'),
  setupRestoreBackup: document.querySelector('#setup-restore-backup'),
  firstTimeSetupCard: document.querySelector('#first-time-setup-card'),
  transactions: document.querySelector('#transactions'),
  dashboardTotalBalance: document.querySelector('#dashboard-total-balance'),
  walletDashboard: document.querySelector('#wallet-dashboard'),
  dashboardPendingDeposits: document.querySelector('#dashboard-pending-deposits'),
  dashboardPendingWithdrawals: document.querySelector('#dashboard-pending-withdrawals'),
  dashboardNodeHeight: document.querySelector('#dashboard-node-height'),
  dashboardNodePeers: document.querySelector('#dashboard-node-peers'),
  primaryReceiveLabel: document.querySelector('#primary-receive-label'),
  primaryReceiveCard: document.querySelector('#primary-receive-card'),
  primaryReceiveAddress: document.querySelector('#primary-receive-address'),
  recentTransactionsCard: document.querySelector('#recent-transactions-card'),
  homeNextStepsCard: document.querySelector('#home-next-steps-card'),
  homeNewAddress: document.querySelector('#home-new-address'),
  homeCopyAddress: document.querySelector('#home-copy-address'),
  homeOpenAddress: document.querySelector('#home-open-address'),
  receiveTotalBalance: document.querySelector('#receive-total-balance'),
  receiveLabel: document.querySelector('#receive-label'),
  receiveAddressList: document.querySelector('#receive-address-list'),
  transactionSearch: document.querySelector('#transaction-search'),
  transactionTableBody: document.querySelector('#transaction-table-body'),
  receiveTransactionsModal: document.querySelector('#receive-transactions-modal'),
  receiveTransactionsDetail: document.querySelector('#receive-transactions-detail'),
  receiveTransactionsBody: document.querySelector('#receive-transactions-body'),
  transactionModal: document.querySelector('#transaction-modal'),
  transactionDetailGrid: document.querySelector('#transaction-detail-grid'),
  newAddress: document.querySelector('#new-address'),
  receiveAddress: document.querySelector('#receive-address'),
  copyAddress: document.querySelector('#copy-address'),
  sendAddress: document.querySelector('#send-address'),
  sendAmount: document.querySelector('#send-amount'),
  sendPassword: document.querySelector('#send-password'),
  sendAuthGroup: document.querySelector('#send-auth-group'),
  sendAuthCode: document.querySelector('#send-auth-code'),
  sendHobc: document.querySelector('#send-hobc'),
  sendResult: document.querySelector('#send-result'),
  backupAuthGroup: document.querySelector('#backup-auth-group'),
  backupAuthCode: document.querySelector('#backup-auth-code'),
  authStatus: document.querySelector('#auth-status'),
  authStartSetup: document.querySelector('#auth-start-setup'),
  authSetupPanel: document.querySelector('#auth-setup-panel'),
  authSecret: document.querySelector('#auth-secret'),
  authCopySecret: document.querySelector('#auth-copy-secret'),
  authVerifyCode: document.querySelector('#auth-verify-code'),
  authEnable: document.querySelector('#auth-enable'),
  authDisablePanel: document.querySelector('#auth-disable-panel'),
  authDisableCode: document.querySelector('#auth-disable-code'),
  authDisable: document.querySelector('#auth-disable'),
  openBackup: document.querySelector('#open-backup'),
  saveBackup: document.querySelector('#save-backup'),
  restoreBackup: document.querySelector('#restore-backup'),
  refreshStatus: document.querySelector('#refresh-status'),
  stopNode: document.querySelector('#stop-node'),
  nodeLog: document.querySelector('#node-log'),
  paths: document.querySelector('#paths')
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

function showView(name) {
  elements.navTabs.forEach((tab) => tab.classList.toggle('is-active', tab.dataset.view === name));
  elements.views.forEach((view) => view.classList.toggle('is-active', view.id === `view-${name}`));
  clearAlert();
}

function formatBalance(value) {
  return `${Number(value || 0).toFixed(8)} HOBC`;
}

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function shortHash(value, start = 14, end = 10) {
  const text = String(value || '');
  if (text.length <= start + end + 3) {
    return text || 'Not available';
  }
  return `${text.slice(0, start)}...${text.slice(-end)}`;
}

function cleanErrorMessage(error) {
  return String(error && error.message ? error.message : error)
    .replace(/^Error invoking remote method '[^']+': Error: /, '');
}

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function remoteChainHeight(summary) {
  return summary.remoteChain && Number(summary.remoteChain.height || 0) > 0
    ? Number(summary.remoteChain.height)
    : null;
}

function isChainCaughtUp(summary, info) {
  const localHeight = Number(info.blocks || 0);
  const remoteHeight = remoteChainHeight(summary);
  if (remoteHeight) {
    return localHeight >= remoteHeight;
  }
  return !info.initialblockdownload || Number(info.verificationprogress || 0) >= 0.999;
}

function formatSyncStatus(summary, info) {
  const connections = Number(summary.network.connections || 0);
  const localHeight = Number(info.blocks || 0);
  const remoteHeight = remoteChainHeight(summary);

  if (connections === 0) {
    return 'Waiting for peers';
  }
  if (remoteHeight && localHeight < remoteHeight) {
    return `${Math.round((localHeight / remoteHeight) * 100)}% syncing`;
  }
  if (isChainCaughtUp(summary, info)) {
    return 'Ready';
  }
  if (info.initialblockdownload) {
    const verifyPercent = Math.round((info.verificationprogress || 0) * 100);
    if (verifyPercent >= 99) {
      return 'Ready';
    }
    return `Verifying (${verifyPercent}%)`;
  }
  return 'Ready';
}

function showLoadingOverlay(title, percent, message, detail = '') {
  if (elements.startupLoadingTitle) {
    elements.startupLoadingTitle.textContent = title;
  }
  elements.startupLoadingScreen.hidden = false;
  updateStartupLoading(percent, message, detail);
}

function updateStartupLoading(percent, message, detail = '') {
  const safePercent = Math.max(0, Math.min(100, Number(percent) || 0));
  if (elements.startupLoadingProgressBar) {
    const nextWidth = `${safePercent}%`;
    if (elements.startupLoadingProgressBar.style.width !== nextWidth) {
      elements.startupLoadingProgressBar.style.width = nextWidth;
    }
  }
  if (elements.startupLoadingProgressText) {
    const nextProgressText = `${Math.round(safePercent)}%`;
    if (elements.startupLoadingProgressText.textContent !== nextProgressText) {
      elements.startupLoadingProgressText.textContent = nextProgressText;
    }
  }
  if (message && elements.startupLoadingMessage) {
    if (elements.startupLoadingMessage.textContent !== message) {
      elements.startupLoadingMessage.textContent = message;
    }
  }
  if (elements.startupLoadingDetail) {
    if (elements.startupLoadingDetail.textContent !== detail) {
      elements.startupLoadingDetail.textContent = detail;
    }
    elements.startupLoadingDetail.hidden = !detail;
  }
}

function hideStartupLoading() {
  elements.startupLoadingScreen.hidden = true;
}

function formatBytes(bytes) {
  const value = Number(bytes || 0);
  if (value >= 1024 * 1024) {
    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
  }
  if (value >= 1024) {
    return `${(value / 1024).toFixed(1)} KB`;
  }
  return `${value} B`;
}

function handleUpdateDownloadProgress(progress) {
  const versionText = progress.version ? ` ${progress.version}` : '';
  if (progress.status === 'failed') {
    hideStartupLoading();
    return;
  }
  if (progress.status === 'verifying') {
    showLoadingOverlay('Verifying Update', 100, `Verifying HobbyHash Wallet${versionText} download.`);
    return;
  }
  if (progress.status === 'launching') {
    showLoadingOverlay('Launching Update', 100, 'Opening the installer now. Your wallet data will be preserved.');
    return;
  }
  const totalText = Number(progress.totalBytes || 0) > 0
    ? `${formatBytes(progress.downloadedBytes)} of ${formatBytes(progress.totalBytes)}`
    : formatBytes(progress.downloadedBytes);
  showLoadingOverlay(
    'Downloading Update',
    progress.percent,
    `Downloading HobbyHash Wallet${versionText}: ${totalText}`
  );
}

function updateSidebarState() {
  elements.walletNav.hidden = !(walletConfigured && nodeStarted);
  elements.sidebarSetupNote.hidden = walletConfigured;
  elements.homepageLink.hidden = false;
}

function setWalletActionsEnabled(enabled) {
  walletReady = enabled;
  updateSidebarState();
  [elements.newAddress, elements.homeNewAddress, elements.homeCopyAddress, elements.homeOpenAddress, elements.sendHobc, elements.saveBackup, elements.openBackup].forEach((button) => {
    if (button) {
      button.disabled = !enabled;
      button.title = enabled ? '' : 'Create or restore an encrypted wallet first.';
    }
  });
  if (!enabled) {
    elements.receiveAddress.textContent = 'Create an encrypted wallet on the Home tab first.';
    elements.sendResult.textContent = 'Create an encrypted wallet before sending.';
  }
}

function renderConfiguredStoppedHome() {
  elements.firstTimeSetupCard.hidden = true;
  elements.walletDashboard.hidden = true;
  elements.primaryReceiveCard.hidden = true;
  elements.recentTransactionsCard.hidden = true;
  elements.networkStatus.textContent = 'Wallet/node stopped';
  elements.syncStatus.textContent = 'Stopped';
  elements.balanceStatus.textContent = 'Wallet ready';
  elements.transactions.textContent = 'Start the wallet/node to load recent transactions.';
}

function handleNodeStoppedUi(message = 'Local HobbyHash node stopped.') {
  hideStartupLoading();
  if (refreshTimer) {
    clearInterval(refreshTimer);
    refreshTimer = null;
  }
  setNodeStarted(false);
  setWalletActionsEnabled(false);
  showView('home');
  if (walletConfigured) {
    renderConfiguredStoppedHome();
  } else {
    elements.networkStatus.textContent = 'Wallet/node stopped';
    elements.syncStatus.textContent = 'Stopped';
  }
  showAlert(message);
}

function renderSecurityStatus(enabled) {
  authenticatorEnabled = Boolean(enabled);
  elements.authStatus.textContent = authenticatorEnabled ? 'Enabled' : 'Not enabled';
  elements.authStartSetup.hidden = authenticatorEnabled;
  elements.authSetupPanel.hidden = authenticatorEnabled || !pendingAuthSecret;
  elements.authDisablePanel.hidden = !authenticatorEnabled;
  elements.sendAuthGroup.hidden = !authenticatorEnabled;
  elements.backupAuthGroup.hidden = !authenticatorEnabled;
  if (!authenticatorEnabled) {
    elements.sendAuthCode.value = '';
    elements.backupAuthCode.value = '';
  }
}

function showLockedApp() {
  appUnlocked = false;
  document.body.classList.add('auth-locked');
  document.body.classList.remove('auth-pending');
  elements.authLockScreen.hidden = false;
  elements.startupAuthCode.value = '';
  elements.startupAuthError.hidden = true;
  setTimeout(() => elements.startupAuthCode.focus(), 0);
}

function showUnlockedApp() {
  appUnlocked = true;
  document.body.classList.remove('auth-pending');
  document.body.classList.remove('auth-locked');
  elements.authLockScreen.hidden = true;
}

function setNodeStarted(started) {
  nodeStarted = started;
  elements.startWallet.textContent = started ? 'Stop Wallet/Node' : 'Start Wallet/Node';
  updateSidebarState();
}

function closeModals() {
  document.querySelectorAll('.modal-backdrop').forEach((modal) => {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
  });
}

function openModal(modal) {
  if (!modal) {
    return;
  }
  modal.classList.add('is-open');
  modal.setAttribute('aria-hidden', 'false');
}

function explorerButton(value, label = 'View') {
  const safe = escapeHtml(value || '');
  return `<button class="small-button explorer-button" type="button" data-explorer-value="${safe}">${escapeHtml(label)}</button>`;
}

function copyButton(value, label = 'Copy') {
  const safe = escapeHtml(value || '');
  return `<button class="small-button copy-value-button" type="button" data-copy-value="${safe}">${escapeHtml(label)}</button>`;
}

function txStatus(tx) {
  if (Number(tx.confirmations || 0) <= 0) {
    return 'confirming';
  }
  return Number(tx.amount || 0) < 0 ? 'confirmed' : 'credited';
}

function txType(tx) {
  return Number(tx.amount || 0) < 0 ? 'Withdrawal' : 'Deposit';
}

function txWhen(tx) {
  const time = Number(tx.time || tx.timereceived || 0);
  return time > 0 ? new Date(time * 1000).toLocaleString() : 'Not available';
}

function detailRow(label, value) {
  return `<div class="detail-row"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value || 'Not available')}</strong></div>`;
}

function renderDashboard(summary) {
  const walletData = summary.wallet;
  elements.dashboardNodeHeight.textContent = String(summary.info.blocks || 0);
  elements.dashboardNodePeers.textContent = `${summary.network.connections} peer${summary.network.connections === 1 ? '' : 's'} connected`;

  if (!walletData) {
    elements.firstTimeSetupCard.hidden = walletConfigured;
    if (walletConfigured) {
      elements.walletDashboard.hidden = true;
      elements.primaryReceiveCard.hidden = false;
      elements.recentTransactionsCard.hidden = false;
      if (!lastAddress) {
        elements.primaryReceiveLabel.textContent = 'Wallet loading';
        elements.primaryReceiveAddress.textContent = 'Start is complete. Waiting for the local node to reload saved receive wallets.';
        elements.primaryReceiveAddress.className = 'address-full empty';
      }
      if (!latestReceiveAddresses.length) {
        elements.receiveAddressList.innerHTML = '<tr><td colspan="6">Saved receive wallets will appear after the wallet reloads.</td></tr>';
      }
      if (!latestTransactions.length) {
        elements.transactionTableBody.innerHTML = '<tr><td colspan="8">Transactions will appear after the wallet reloads.</td></tr>';
      }
      return;
    }
    elements.walletDashboard.hidden = true;
    elements.primaryReceiveCard.hidden = true;
    elements.recentTransactionsCard.hidden = true;
    elements.dashboardTotalBalance.textContent = '0.00000000';
    elements.dashboardPendingDeposits.textContent = '0';
    elements.dashboardPendingWithdrawals.textContent = '0';
    elements.receiveTotalBalance.textContent = '0.00000000';
    elements.primaryReceiveLabel.textContent = 'No receive wallet has been created yet.';
    elements.primaryReceiveAddress.textContent = 'Create an encrypted wallet, then create a receive address.';
    elements.primaryReceiveAddress.className = 'address-full empty';
    renderReceiveAddresses([]);
    renderTransactionTable([]);
    lastAddress = '';
    return;
  }

  elements.firstTimeSetupCard.hidden = true;
  elements.walletDashboard.hidden = false;
  elements.primaryReceiveCard.hidden = false;
  elements.recentTransactionsCard.hidden = false;
  elements.dashboardTotalBalance.textContent = Number(walletData.balance || 0).toFixed(8);
  elements.dashboardPendingDeposits.textContent = String(walletData.pendingDeposits || 0);
  elements.dashboardPendingWithdrawals.textContent = String(walletData.pendingWithdrawals || 0);
  elements.receiveTotalBalance.textContent = Number(walletData.balance || 0).toFixed(8);
  latestTransactions = walletData.transactions || [];
  latestReceiveAddresses = walletData.receiveAddresses || [];
  renderReceiveAddresses(latestReceiveAddresses);
  renderTransactionTable(latestTransactions);

  if (walletData.primaryReceive && walletData.primaryReceive.address) {
    lastAddress = walletData.primaryReceive.address;
    elements.primaryReceiveLabel.textContent = walletData.primaryReceive.label || 'Primary receive wallet';
    elements.primaryReceiveAddress.textContent = walletData.primaryReceive.address;
    elements.primaryReceiveAddress.className = 'address-full';
  } else {
    lastAddress = '';
    elements.primaryReceiveLabel.textContent = 'No receive wallet has been created yet.';
    elements.primaryReceiveAddress.textContent = 'Create a receive wallet to show your first HOBC address.';
    elements.primaryReceiveAddress.className = 'address-full empty';
  }
}

function renderReceiveAddresses(addresses) {
  if (!addresses.length) {
    elements.receiveAddressList.innerHTML = '<tr><td colspan="6">No receive wallets yet.</td></tr>';
    return;
  }
  elements.receiveAddressList.innerHTML = addresses.map((row) => {
    const address = row.address || '';
    const label = row.label || 'Receive Wallet';
    const txCount = latestTransactions.filter((tx) => tx.address === address && Number(tx.amount || 0) > 0).length;
    const pending = latestTransactions
      .filter((tx) => tx.address === address && Number(tx.amount || 0) > 0 && Number(tx.confirmations || 0) <= 0)
      .reduce((sum, tx) => sum + Number(tx.amount || 0), 0);
    return `
      <tr>
        <td><input class="inline-label-input" data-address="${escapeHtml(address)}" value="${escapeHtml(label)}"></td>
        <td><span class="address-full">${escapeHtml(address)}</span> ${copyButton(address, 'Copy')}</td>
        <td>${Number(row.amount || 0).toFixed(8)}</td>
        <td>${pending.toFixed(8)}</td>
        <td>${txCount}</td>
        <td>
          <button class="small-button rename-address-button" type="button" data-address="${escapeHtml(address)}">Save Name</button>
          <button class="small-button receive-tx-button" type="button" data-address="${escapeHtml(address)}">View Transactions</button>
          ${explorerButton(address, 'Explorer')}
        </td>
      </tr>
    `;
  }).join('');
}

function renderTransactions(transactions) {
  if (!transactions || !transactions.length) {
    elements.transactions.textContent = 'No transactions yet.';
    return;
  }

  elements.transactions.textContent = '';
  transactions.slice(0, 10).forEach((tx) => {
    const row = document.createElement('div');
    row.className = 'transaction-row';
    const txid = String(tx.txid || '');
    row.innerHTML = `
      <strong>${escapeHtml(txType(tx))} ${escapeHtml(formatBalance(tx.amount))}<small>${escapeHtml(txStatus(tx))} · ${escapeHtml(tx.confirmations ?? 0)} confs</small></strong>
      <span>
        <button class="small-button tx-detail-button" type="button" data-txid="${escapeHtml(txid)}">View</button>
        <code title="${escapeHtml(txid)}">${escapeHtml(shortHash(txid))}</code>
      </span>
    `;
    elements.transactions.appendChild(row);
  });
}

function renderTransactionTable(transactions) {
  const query = String(elements.transactionSearch.value || '').trim().toLowerCase();
  const filtered = transactions.filter((tx) => {
    const haystack = [txType(tx), txStatus(tx), tx.amount, tx.address, tx.txid, tx.label, tx.category].join(' ').toLowerCase();
    return query === '' || haystack.includes(query);
  });
  if (!filtered.length) {
    elements.transactionTableBody.innerHTML = '<tr><td colspan="8">No transactions found yet.</td></tr>';
    return;
  }
  elements.transactionTableBody.innerHTML = filtered.map((tx) => {
    const txid = tx.txid || '';
    const address = tx.address || 'Not available';
    const label = tx.label || (Number(tx.amount || 0) < 0 ? 'External address' : 'Receive Wallet');
    return `
      <tr>
        <td>${escapeHtml(txType(tx))}</td>
        <td>${Number(tx.amount || 0).toFixed(8)}</td>
        <td>${escapeHtml(txStatus(tx))}</td>
        <td>${escapeHtml(label)}<br><span class="address-full">${escapeHtml(address)}</span></td>
        <td><code title="${escapeHtml(txid)}">${escapeHtml(shortHash(txid))}</code></td>
        <td>${escapeHtml(tx.confirmations ?? 0)}</td>
        <td>${escapeHtml(txWhen(tx))}</td>
        <td><button type="button" class="small-button tx-detail-button" data-txid="${escapeHtml(txid)}">View</button></td>
      </tr>
    `;
  }).join('');
}

async function refreshStatus() {
  try {
    const summary = await wallet.summary();
    const info = summary.info;
    const listenText = summary.ports.listenEnabled ? 'inbound/outbound' : 'outbound only';
    const peerText = `${summary.network.connections} peer${summary.network.connections === 1 ? '' : 's'}`;
    const adoptedText = summary.ports.adoptedExistingNode ? ', using existing local node' : '';
    const remoteHeight = remoteChainHeight(summary);
    const heightText = remoteHeight ? `${info.blocks}/${remoteHeight}` : String(info.blocks);
    elements.networkStatus.textContent = `Mainnet block ${heightText} (${peerText}, ${listenText}, RPC ${summary.ports.rpc}${adoptedText})`;
    elements.syncStatus.textContent = formatSyncStatus(summary, info);
    renderPeerSummary(summary.peers || []);
    renderSecurityStatus(summary.security && summary.security.totpEnabled);
    walletConfigured = Boolean(walletConfigured || summary.wallet || (summary.security && summary.security.walletConfigured));
    updateSidebarState();
    renderDashboard(summary);
    if (summary.wallet) {
      setWalletActionsEnabled(true);
      elements.balanceStatus.textContent = formatBalance(summary.wallet.balance);
      renderTransactions(summary.wallet.transactions);
    } else {
      setWalletActionsEnabled(Boolean(walletConfigured && nodeStarted));
      elements.balanceStatus.textContent = walletConfigured ? 'Wallet loading' : 'No wallet yet';
      elements.transactions.textContent = walletConfigured
        ? 'Wallet setup is complete. Waiting for the local node to load wallet details.'
        : 'Create an encrypted wallet to begin.';
    }
    return summary;
  } catch (error) {
    setWalletActionsEnabled(Boolean(walletConfigured && nodeStarted));
    renderSecurityStatus(false);
    if (walletConfigured && !nodeStarted) {
      renderConfiguredStoppedHome();
      return null;
    }
    elements.networkStatus.textContent = 'Starting local node...';
    elements.syncStatus.textContent = 'Waiting for RPC';
    return null;
  }
}

function renderPeerSummary(peers) {
  const existing = document.querySelector('#peer-summary');
  if (!existing) {
    return;
  }
  if (!peers.length) {
    existing.textContent = 'No peers connected yet. The wallet is trying hobbyhashcoin.com, 47.145.65.88, 162.254.37.69, and local fallback 127.0.0.1.';
    return;
  }
  existing.textContent = '';
  peers.forEach((peer) => {
    const row = document.createElement('div');
    row.className = 'transaction-row';
    row.innerHTML = `<strong>${peer.address} (${peer.connectionType})</strong><code>synced blocks: ${peer.syncedBlocks}</code>`;
    existing.appendChild(row);
  });
}

async function renderPaths() {
  const paths = await wallet.paths();
  elements.paths.innerHTML = [
    ['Version', paths.version],
    ['Data folder', paths.dataDir],
    ['Config file', paths.configFile],
    ['Bundled node', paths.nodeBinary],
    ['Local RPC', `${paths.rpcHost}:${paths.rpcPort}${paths.rpcPort !== paths.preferredRpcPort ? ` (fallback from ${paths.preferredRpcPort})` : ''}`],
    ['RPC auth', 'Local cookie file, no rpcuser/rpcpassword'],
    ['P2P mode', paths.listenEnabled ? `Listening on ${paths.p2pPort}` : 'Outbound only because the P2P port is already in use'],
    ['Peer seeds', 'hobbyhashcoin.com:18761, 47.145.65.88:18761, 162.254.37.69:18761, local fallback 127.0.0.1:18761']
  ].map(([label, value]) => `<div class="table-row"><span>${label}</span><strong>${value}</strong></div>`).join('');
}

elements.navTabs.forEach((tab) => tab.addEventListener('click', () => showView(tab.dataset.view)));

elements.startWallet.addEventListener('click', async () => {
  elements.startWallet.disabled = true;
  try {
    if (nodeStarted) {
      const stopResult = await wallet.stopNode();
      if (stopResult.stopped) {
        handleNodeStoppedUi(stopResult.message || 'Wallet/node stopped.');
      } else {
        setNodeStarted(true);
        showAlert(stopResult.message || 'The local node is still running.', 'error');
      }
      return;
    }
    await finishNodeStartupOverlay({
      title: 'Starting Wallet/Node',
      initialMessage: 'Restarting the local HobbyHash node...'
    });
  } catch (error) {
    setNodeStarted(false);
    showAlert(cleanErrorMessage(error), 'error');
  } finally {
    elements.startWallet.disabled = false;
  }
});

async function initializeApp() {
  updateStartupLoading(5, 'Opening HobbyHash Wallet...');
  try {
    updateStartupLoading(18, 'Checking wallet security settings...');
    const security = await wallet.securityStatus();
    walletConfigured = Boolean(security.walletConfigured);
    updateSidebarState();
    renderSecurityStatus(security.totpEnabled);
    if (security.totpEnabled) {
      updateStartupLoading(100, 'Authenticator protection is ready.');
      showLockedApp();
      return;
    }
    updateStartupLoading(38, 'Loading wallet interface...');
    showUnlockedApp();
    await finishNodeStartupOverlay({ reuseVisibleOverlay: true });
  } catch (error) {
    updateStartupLoading(100, 'Startup finished with a message.');
    showUnlockedApp();
    showAlert(cleanErrorMessage(error), 'error');
    await delay(300);
    hideStartupLoading();
  }
}

async function autoStartNode(onProgress = () => {}) {
  if (!appUnlocked) {
    return false;
  }
  try {
    onProgress(52, 'Starting the local HobbyHash node...');
    const result = await wallet.startNode();
    setNodeStarted(true);
    updateSidebarState();
    onProgress(70, 'Loading local node paths...');
    await renderPaths();
    onProgress(76, 'Waiting for local node RPC and blockchain sync...');
    await waitForInitialNodeStatus(onProgress);
    if (!refreshTimer) {
      refreshTimer = setInterval(refreshStatus, 10000);
    }
    onProgress(96, 'Finishing wallet startup...');
    if (result.adoptedExistingNode) {
      showAlert('Connected to an already-running local HobbyHash node.');
    }
    return true;
  } catch (error) {
    onProgress(100, 'Wallet opened, but the local node needs attention.');
    showAlert(cleanErrorMessage(error), 'error');
    return false;
  }
}

async function finishNodeStartupOverlay(options = {}) {
  const {
    title = 'Loading Wallet',
    reuseVisibleOverlay = false,
    initialMessage = 'Starting the local HobbyHash node...'
  } = options;

  if (!appUnlocked) {
    return false;
  }

  if (!reuseVisibleOverlay) {
    showLoadingOverlay(title, 5, initialMessage);
  } else {
    if (elements.startupLoadingTitle) {
      elements.startupLoadingTitle.textContent = title;
    }
    elements.startupLoadingScreen.hidden = false;
    updateStartupLoading(5, initialMessage);
  }

  try {
    const ready = await autoStartNode(updateStartupLoading);
    updateStartupLoading(100, ready ? 'Wallet loaded.' : 'Startup finished with a message.');
    return ready;
  } catch (error) {
    updateStartupLoading(100, 'Startup finished with a message.');
    showAlert(cleanErrorMessage(error), 'error');
    return false;
  } finally {
    await delay(300);
    hideStartupLoading();
  }
}

async function waitForInitialNodeStatus(onProgress) {
  const timeoutMs = 30 * 60 * 1000;
  const startedAt = Date.now();
  let attempts = 0;

  while (Date.now() - startedAt < timeoutMs) {
    attempts += 1;
    const elapsed = Date.now() - startedAt;
    const fallbackProgress = 76 + Math.min(10, Math.floor((elapsed / timeoutMs) * 10));

    const summary = await refreshStatus();
    if (!summary) {
      onProgress(fallbackProgress, 'Waiting for the local node to answer RPC...');
      await delay(attempts < 6 ? 1000 : 2500);
      continue;
    }

    const remoteHeight = remoteChainHeight(summary);
    const localHeight = Number(summary.info.blocks || 0);
    const syncPercent = remoteHeight
      ? Math.max(0, Math.min(100, Math.round((localHeight / remoteHeight) * 100)))
      : Math.max(0, Math.min(100, Math.round((summary.info.verificationprogress || 0) * 100)));
    const synced = isChainCaughtUp(summary, summary.info);
    const hasPeers = Number(summary.network.connections || 0) > 0;
    const walletLoaded = !walletConfigured || Boolean(summary.wallet);
    const blockText = remoteHeight ? `block ${localHeight} of ${remoteHeight}` : `block ${localHeight}`;
    const peerText = `${summary.network.connections || 0} peer${summary.network.connections === 1 ? '' : 's'}`;
    const detailText = `${blockText}, ${peerText}`;
    const progress = synced ? 96 : Math.max(78, Math.min(95, 78 + Math.floor(syncPercent * 0.17)));

    if (!remoteHeight) {
      onProgress(progress, 'Checking hobbyhashcoin.com for the current chain height...', detailText);
    } else if (!hasPeers) {
      onProgress(progress, 'Waiting for peers before final sync check...', detailText);
    } else if (!synced) {
      onProgress(progress, `Syncing to hobbyhashcoin.com height: ${syncPercent}%`, detailText);
    } else if (!walletLoaded) {
      onProgress(96, 'Blockchain is synced. Loading your configured wallet...');
    }

    if (hasPeers && synced && walletLoaded) {
      return summary;
    }

    await delay(attempts < 6 ? 1000 : 2500);
  }

  throw new Error(walletConfigured
    ? 'The local node did not finish syncing and loading the wallet in time. Leave the app open and use Refresh Status after the node catches up.'
    : 'The local node did not finish syncing in time. Leave the app open and use Refresh Status after it catches up.');
}

async function unlockStartupAuth() {
  try {
    await wallet.unlockTotp(elements.startupAuthCode.value);
    showUnlockedApp();
    await finishNodeStartupOverlay({ reuseVisibleOverlay: true });
  } catch (error) {
    elements.startupAuthError.textContent = cleanErrorMessage(error);
    elements.startupAuthError.hidden = false;
  }
}

elements.startupAuthUnlock.addEventListener('click', async () => {
  await unlockStartupAuth();
});

elements.startupAuthCode.addEventListener('keydown', async (event) => {
  if (event.key === 'Enter') {
    await unlockStartupAuth();
  }
});

elements.createWallet.addEventListener('click', async () => {
  try {
    const result = await wallet.createWallet(elements.createPassword.value);
    walletConfigured = true;
    updateSidebarState();
    elements.createPassword.value = '';
    elements.firstTimeSetupCard.hidden = true;
    elements.sidebarSetupNote.hidden = true;
    if (result.primaryAddress) {
      lastAddress = result.primaryAddress;
      elements.primaryReceiveLabel.textContent = 'Main Wallet';
      elements.primaryReceiveAddress.textContent = result.primaryAddress;
      elements.primaryReceiveAddress.className = 'address-full';
      elements.receiveAddress.textContent = result.primaryAddress;
    }
    showAlert('Encrypted HobbyHash wallet created with a Main Wallet receive address. Back up the wallet data folder before receiving large balances.');
    await refreshStatus();
  } catch (error) {
    showAlert(cleanErrorMessage(error), 'error');
  }
});

elements.setupRestoreBackup.addEventListener('click', async () => {
  try {
    const result = await wallet.restoreBackup(elements.backupAuthCode.value);
    if (!result.canceled) {
      walletConfigured = true;
      updateSidebarState();
      elements.firstTimeSetupCard.hidden = true;
      elements.sidebarSetupNote.hidden = true;
      showAlert('Wallet backup restored. The wallet menu will appear after the local node loads it.');
      await refreshStatus();
    }
  } catch (error) {
    showAlert(cleanErrorMessage(error), 'error');
  }
});

async function createReceiveAddress() {
  if (!walletReady) {
    showView('home');
    showAlert('Create an encrypted wallet first, then you can create a receive wallet.', 'error');
    return;
  }
  try {
    lastAddress = await wallet.newReceiveAddress(elements.receiveLabel.value);
    elements.receiveLabel.value = '';
    elements.receiveAddress.textContent = lastAddress;
    elements.primaryReceiveLabel.textContent = 'Primary receive wallet';
    elements.primaryReceiveAddress.textContent = lastAddress;
    elements.primaryReceiveAddress.className = 'address-full';
    showAlert('New HOBC receive address created.');
    await refreshStatus();
  } catch (error) {
    showAlert(cleanErrorMessage(error), 'error');
  }
}

elements.newAddress.addEventListener('click', async () => {
  await createReceiveAddress();
});

elements.homeNewAddress.addEventListener('click', async () => {
  await createReceiveAddress();
});

elements.homepageLink.addEventListener('click', async () => {
  await wallet.openHomepage();
});

elements.copyAddress.addEventListener('click', async () => {
  if (!lastAddress) {
    showAlert('Create a receive address first.', 'error');
    return;
  }
  await wallet.copy(lastAddress);
  showAlert('Address copied.');
});

elements.homeCopyAddress.addEventListener('click', async () => {
  if (!lastAddress) {
    showAlert('Create a receive address first.', 'error');
    return;
  }
  await wallet.copy(lastAddress);
  showAlert('Primary receive address copied.');
});

elements.homeOpenAddress.addEventListener('click', async () => {
  if (!lastAddress) {
    showAlert('Create a receive address first.', 'error');
    return;
  }
  await wallet.openExplorer(lastAddress);
});

function findTransaction(txid) {
  return latestTransactions.find((tx) => tx.txid === txid) || null;
}

function openTransactionDetail(tx) {
  if (!tx) {
    return;
  }
  elements.transactionDetailGrid.innerHTML = [
    detailRow('Type', txType(tx)),
    detailRow('Amount', Number(tx.amount || 0).toFixed(8)),
    detailRow('Status', txStatus(tx)),
    detailRow('Wallet', tx.label || (Number(tx.amount || 0) < 0 ? 'External address' : 'Receive Wallet')),
    detailRow('Address', tx.address || 'Not available'),
    detailRow('TXID', tx.txid || 'Not available'),
    detailRow('Confirmations', String(tx.confirmations ?? 0)),
    detailRow('When', txWhen(tx)),
    `<div class="detail-row"><span>Explorer</span><strong>${explorerButton(tx.txid || '', 'View in Explorer')}</strong></div>`
  ].join('');
  openModal(elements.transactionModal);
}

function openReceiveTransactions(address) {
  const row = latestReceiveAddresses.find((entry) => entry.address === address);
  const txs = latestTransactions.filter((tx) => tx.address === address && Number(tx.amount || 0) > 0);
  elements.receiveTransactionsDetail.innerHTML = [
    detailRow('Wallet', row ? (row.label || 'Receive Wallet') : 'Receive Wallet'),
    detailRow('Address', address),
    detailRow('Transactions', String(txs.length))
  ].join('');
  if (!txs.length) {
    elements.receiveTransactionsBody.innerHTML = '<tr><td colspan="6">No transactions.</td></tr>';
  } else {
    elements.receiveTransactionsBody.innerHTML = txs.map((tx) => `
      <tr>
        <td><code title="${escapeHtml(tx.txid || '')}">${escapeHtml(shortHash(tx.txid || ''))}</code></td>
        <td>${Number(tx.amount || 0).toFixed(8)}</td>
        <td>${escapeHtml(tx.confirmations ?? 0)}</td>
        <td>${escapeHtml(txStatus(tx))}</td>
        <td>${escapeHtml(txWhen(tx))}</td>
        <td>${explorerButton(tx.txid || '', 'Explorer')}</td>
      </tr>
    `).join('');
  }
  openModal(elements.receiveTransactionsModal);
}

document.addEventListener('click', async (event) => {
  const copy = event.target.closest('.copy-value-button');
  if (copy) {
    await wallet.copy(copy.dataset.copyValue || '');
    showAlert('Copied.');
    return;
  }
  const explorer = event.target.closest('.explorer-button');
  if (explorer) {
    await wallet.openExplorer(explorer.dataset.explorerValue || '');
    return;
  }
  const txDetail = event.target.closest('.tx-detail-button');
  if (txDetail) {
    openTransactionDetail(findTransaction(txDetail.dataset.txid || ''));
    return;
  }
  const receiveTx = event.target.closest('.receive-tx-button');
  if (receiveTx) {
    openReceiveTransactions(receiveTx.dataset.address || '');
    return;
  }
  const rename = event.target.closest('.rename-address-button');
  if (rename) {
    const address = rename.dataset.address || '';
    const input = Array.from(document.querySelectorAll('.inline-label-input')).find((field) => field.dataset.address === address);
    try {
      await wallet.renameAddress(address, input ? input.value : '');
      showAlert('Receive wallet name updated.');
      await refreshStatus();
    } catch (error) {
      showAlert(cleanErrorMessage(error), 'error');
    }
    return;
  }
  if (event.target.closest('[data-close-modal]')) {
    closeModals();
  }
});

elements.transactionSearch.addEventListener('input', () => {
  renderTransactionTable(latestTransactions);
});

elements.authStartSetup.addEventListener('click', async () => {
  try {
    const setup = await wallet.beginTotpSetup();
    pendingAuthSecret = setup.secret;
    elements.authSecret.textContent = setup.secret;
    elements.authVerifyCode.value = '';
    renderSecurityStatus(false);
  } catch (error) {
    showAlert(cleanErrorMessage(error), 'error');
  }
});

elements.authCopySecret.addEventListener('click', async () => {
  if (!pendingAuthSecret) {
    showAlert('Start authenticator setup first.', 'error');
    return;
  }
  await wallet.copy(pendingAuthSecret);
  showAlert('Authenticator secret copied.');
});

elements.authEnable.addEventListener('click', async () => {
  try {
    await wallet.enableTotp(elements.authVerifyCode.value);
    pendingAuthSecret = '';
    elements.authSecret.textContent = 'No secret generated.';
    elements.authVerifyCode.value = '';
    showAlert('Authenticator protection enabled.');
    renderSecurityStatus(true);
  } catch (error) {
    showAlert(cleanErrorMessage(error), 'error');
  }
});

elements.authDisable.addEventListener('click', async () => {
  try {
    await wallet.disableTotp(elements.authDisableCode.value);
    elements.authDisableCode.value = '';
    showAlert('Authenticator protection disabled.');
    renderSecurityStatus(false);
  } catch (error) {
    showAlert(cleanErrorMessage(error), 'error');
  }
});

elements.sendHobc.addEventListener('click', async () => {
  if (!walletReady) {
    showView('home');
    showAlert('Create an encrypted wallet first, then you can send HOBC.', 'error');
    return;
  }
  try {
    const txid = await wallet.send(elements.sendAddress.value, elements.sendAmount.value, elements.sendPassword.value, elements.sendAuthCode.value);
    elements.sendPassword.value = '';
    elements.sendAuthCode.value = '';
    elements.sendResult.textContent = JSON.stringify({ txid }, null, 2);
    showAlert('Transaction sent through your local HobbyHash node.');
    await refreshStatus();
  } catch (error) {
    const message = cleanErrorMessage(error);
    elements.sendResult.textContent = message;
    showAlert(message, 'error');
  }
});

elements.openBackup.addEventListener('click', async () => {
  await wallet.openBackupFolder();
});

elements.saveBackup.addEventListener('click', async () => {
  if (!walletReady) {
    showView('home');
    showAlert('Create an encrypted wallet before saving a wallet backup.', 'error');
    return;
  }
  try {
    const result = await wallet.saveBackup(elements.backupAuthCode.value);
    elements.backupAuthCode.value = '';
    if (!result.canceled) {
      showAlert(`Wallet backup saved: ${result.filePath}`);
    }
  } catch (error) {
    showAlert(cleanErrorMessage(error), 'error');
  }
});

elements.restoreBackup.addEventListener('click', async () => {
  try {
    const result = await wallet.restoreBackup(elements.backupAuthCode.value);
    elements.backupAuthCode.value = '';
    if (!result.canceled) {
      walletConfigured = true;
      updateSidebarState();
      elements.firstTimeSetupCard.hidden = true;
      elements.sidebarSetupNote.hidden = true;
      showAlert('Wallet backup restored. Refresh status after the local node loads it.');
      await refreshStatus();
    }
  } catch (error) {
    showAlert(cleanErrorMessage(error), 'error');
  }
});

elements.refreshStatus.addEventListener('click', refreshStatus);

elements.stopNode.addEventListener('click', async () => {
  elements.stopNode.disabled = true;
  try {
    const result = await wallet.stopNode();
    if (result.stopped) {
      handleNodeStoppedUi(result.message || 'Local HobbyHash node stopped.');
    } else {
      setNodeStarted(true);
      showAlert(result.message || 'The local node is still running.', 'error');
    }
  } catch (error) {
    showAlert(cleanErrorMessage(error), 'error');
  } finally {
    elements.stopNode.disabled = false;
  }
});

wallet.onNodeLog((message) => {
  elements.nodeLog.textContent = `${elements.nodeLog.textContent}\n${message}`.slice(-12000);
  elements.nodeLog.scrollTop = elements.nodeLog.scrollHeight;
});

wallet.onNavigate((view) => {
  showView(view);
});

wallet.onStartNodeRequest(async () => {
  if (nodeStarted || !appUnlocked) {
    return;
  }
  elements.startWallet.disabled = true;
  try {
    await finishNodeStartupOverlay({
      title: 'Starting Wallet/Node',
      initialMessage: 'Starting the local HobbyHash node...'
    });
  } catch (error) {
    setNodeStarted(false);
    showAlert(cleanErrorMessage(error), 'error');
  } finally {
    elements.startWallet.disabled = false;
  }
});

wallet.onNodeStopped((result) => {
  handleNodeStoppedUi(result.message || 'Local HobbyHash node stopped.');
});

wallet.onUpdateDownloadProgress(handleUpdateDownloadProgress);

document.querySelectorAll('.modal-backdrop').forEach((modal) => {
  modal.addEventListener('click', (event) => {
    if (event.target === modal) {
      closeModals();
    }
  });
});

renderPaths().catch(() => {});
setWalletActionsEnabled(false);
setNodeStarted(false);
renderSecurityStatus(false);
updateSidebarState();
elements.walletDashboard.hidden = true;
elements.primaryReceiveCard.hidden = true;
elements.recentTransactionsCard.hidden = true;
initializeApp();
