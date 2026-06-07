(function () {
  function qs(selector, root) {
    return (root || document).querySelector(selector);
  }

  function qsa(selector, root) {
    return Array.from((root || document).querySelectorAll(selector));
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
  }

  function openModal(modal) {
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function initMobileMenu() {
    const shell = qs('[data-admin-shell]');
    const toggle = qs('[data-admin-menu-toggle]');
    if (!shell || !toggle) return;

    const close = () => {
      shell.classList.remove('is-menu-open');
      toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', () => {
      const open = shell.classList.toggle('is-menu-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    qsa('[data-admin-menu-close]').forEach((button) => button.addEventListener('click', close));
    qsa('.admin-nav-group-links a, .admin-nav a').forEach((link) => link.addEventListener('click', close));
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') close();
    });
  }

  function initConfirmations() {
    const modal = qs('[data-admin-confirm-modal]');
    const message = qs('[data-admin-confirm-message]', modal);
    const accept = qs('[data-admin-confirm-accept]', modal);
    const cancel = qs('[data-admin-confirm-cancel]', modal);
    let pendingForm = null;
    let confirmedSubmitter = null;

    if (!modal || !message || !accept || !cancel) return;

    document.addEventListener('submit', (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement)) return;
      const submitter = event.submitter;
      const trigger = submitter && submitter.getAttribute('data-confirm')
        ? submitter
        : form.querySelector('[data-confirm]');
      if (!trigger || form.dataset.confirmed === '1') return;

      event.preventDefault();
      pendingForm = form;
      confirmedSubmitter = submitter || trigger;
      message.textContent = trigger.getAttribute('data-confirm') || 'Confirm this admin action.';
      openModal(modal);
    });

    accept.addEventListener('click', () => {
      if (!pendingForm) {
        closeModal(modal);
        return;
      }
      pendingForm.dataset.confirmed = '1';
      closeModal(modal);
      if (confirmedSubmitter && confirmedSubmitter.name) {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = confirmedSubmitter.name;
        hidden.value = confirmedSubmitter.value || '';
        pendingForm.appendChild(hidden);
      }
      pendingForm.submit();
    });

    cancel.addEventListener('click', () => {
      pendingForm = null;
      confirmedSubmitter = null;
      closeModal(modal);
    });

    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        pendingForm = null;
        confirmedSubmitter = null;
        closeModal(modal);
      }
    });
  }

  function initModalButtons() {
    qsa('[data-open-modal]').forEach((button) => {
      button.addEventListener('click', () => {
        openModal(document.getElementById(button.dataset.openModal || ''));
      });
    });

    qsa('[data-close-modal]').forEach((button) => {
      button.addEventListener('click', () => closeModal(button.closest('.modal-backdrop')));
    });

    qsa('.modal-backdrop').forEach((modal) => {
      modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal(modal);
      });
    });
  }

  function initTableFilters() {
    qsa('[data-admin-table-filter]').forEach((input) => {
      const table = input.closest('.admin-card, .card, .admin-content')?.querySelector('[data-admin-filter-table]');
      if (!table) return;

      input.addEventListener('input', () => {
        const needle = input.value.trim().toLowerCase();
        qsa('tbody tr', table).forEach((row) => {
          row.hidden = needle !== '' && !row.textContent.toLowerCase().includes(needle);
        });
      });
    });
  }

  function initTabs() {
    qsa('[data-admin-tab]').forEach((tab) => {
      tab.addEventListener('click', () => {
        const group = tab.closest('[data-admin-tabs]');
        if (!group) return;
        const target = tab.getAttribute('data-admin-tab');
        qsa('[data-admin-tab]', group).forEach((item) => item.classList.toggle('is-active', item === tab));
        qsa('[data-admin-tab-panel]', group).forEach((panel) => {
          panel.hidden = panel.getAttribute('data-admin-tab-panel') !== target;
        });
      });
    });
  }

  function initLiveRefresh() {
    qsa('[data-admin-live-refresh]').forEach((root) => {
      const endpoint = root.getAttribute('data-admin-live-refresh');
      const interval = Math.max(5000, Number(root.getAttribute('data-refresh-ms') || 30000));
      const target = qs('[data-admin-live-target]', root) || root;
      if (!endpoint) return;

      const refresh = async () => {
        try {
          const separator = endpoint.includes('?') ? '&' : '?';
          const response = await fetch(`${endpoint}${separator}_=${Date.now()}`, {
            cache: 'no-store',
            headers: { Accept: 'application/json' }
          });
          const data = await response.json();
          target.textContent = data.status || data.ok || data.updated_at || 'Updated';
          target.classList.toggle('ok', data.ok === true || data.status === 'online');
          target.classList.toggle('warn', data.ok !== true && data.status !== 'online');
        } catch (error) {
          target.textContent = 'Refresh failed';
          target.classList.add('warn');
        }
      };

      qsa('[data-admin-refresh-now]', root).forEach((button) => button.addEventListener('click', refresh));
      window.setInterval(refresh, interval);
    });
  }

  function applyDashboardTone(card, tone) {
    card.classList.remove('admin-stat-ok', 'admin-stat-warn', 'admin-stat-error', 'admin-stat-info');
    if (tone) {
      card.classList.add(`admin-stat-${tone}`);
    }
  }

  function initDashboardLive() {
    const root = qs('[data-admin-dashboard]');
    if (!root) return;

    const endpoint = root.getAttribute('data-admin-dashboard-url');
    const interval = Math.max(5000, Number(root.getAttribute('data-refresh-ms') || 15000));
    const updatedTarget = qs('[data-admin-dashboard-updated]', root);
    const alertsTarget = qs('[data-admin-dashboard-alerts]', root);
    const eventsTarget = qs('[data-admin-dashboard-events]', root);
    const blocksTarget = qs('[data-admin-dashboard-blocks]', root);
    if (!endpoint) return;

    const escapeHtml = (value) => String(value ?? '').replace(/[<>&"]/g, (char) => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' }[char]));

    const renderAlerts = (alerts) => {
      if (!alertsTarget || !Array.isArray(alerts)) return;
      if (alerts.length === 0) {
        alertsTarget.innerHTML = '';
        return;
      }
      alertsTarget.innerHTML = alerts.map((alert) => {
        const type = alert && alert.type ? alert.type : 'warning';
        const message = escapeHtml(alert && alert.message ? alert.message : '');
        const toneClass = type === 'error' ? 'admin-alert-error' : (type === 'warning' ? 'admin-alert-warning' : 'admin-alert-success');
        return `<div class="admin-alert ${toneClass}" role="status">${message}</div>`;
      }).join('');
    };

    const renderEvents = (events) => {
      if (!eventsTarget || !Array.isArray(events)) return;
      if (events.length === 0) {
        eventsTarget.innerHTML = '<div class="admin-empty"><h3>No admin events yet</h3><p>No admin audit events have been recorded yet.</p></div>';
        return;
      }
      const rows = events.map((event) => `<tr><td>${escapeHtml(event.action)}</td><td>${escapeHtml(event.created_at)}</td></tr>`).join('');
      eventsTarget.innerHTML = `<div class="admin-table-wrap"><table class="admin-table" data-admin-filter-table><thead><tr><th>Action</th><th>When</th></tr></thead><tbody>${rows}</tbody></table></div>`;
    };

    const renderBlocks = (blocks) => {
      if (!blocksTarget || !Array.isArray(blocks)) return;
      if (blocks.length === 0) {
        blocksTarget.innerHTML = '<div class="admin-empty"><h3>No blocks yet</h3><p>Node RPC has not returned recent blocks yet.</p></div>';
        return;
      }
      const rows = blocks.map((block) => `<tr><td>${escapeHtml(block.height)}</td><td><code class="admin-mono-cell">${escapeHtml(block.hash)}</code></td><td>${escapeHtml(block.tx_count)}</td><td>${escapeHtml(block.time)}</td></tr>`).join('');
      blocksTarget.innerHTML = `<div class="admin-table-wrap"><table class="admin-table" data-admin-filter-table><thead><tr><th>Height</th><th>Hash</th><th>TX</th><th>When</th></tr></thead><tbody>${rows}</tbody></table></div>`;
    };

    const refresh = async () => {
      try {
        const separator = endpoint.includes('?') ? '&' : '?';
        const response = await fetch(`${endpoint}${separator}_=${Date.now()}`, {
          cache: 'no-store',
          credentials: 'same-origin',
          headers: { Accept: 'application/json' }
        });
        if (!response.ok) {
          throw new Error('Dashboard refresh failed');
        }
        const data = await response.json();
        if (updatedTarget && data.updated_at) {
          updatedTarget.textContent = data.updated_at;
        }
        if (data.stats && typeof data.stats === 'object') {
          Object.entries(data.stats).forEach(([id, stat]) => {
            const card = qs(`[data-admin-stat="${id}"]`, root);
            if (!card || !stat) return;
            const valueEl = qs('[data-admin-stat-value]', card);
            const subtextEl = qs('[data-admin-stat-subtext]', card);
            if (valueEl && stat.value !== undefined) {
              valueEl.textContent = stat.value;
            }
            if (subtextEl && stat.subtext !== undefined) {
              subtextEl.textContent = stat.subtext;
            }
            applyDashboardTone(card, stat.tone || '');
          });
        }
        renderAlerts(data.alerts);
        renderEvents(data.recent_events);
        renderBlocks(data.recent_blocks);
      } catch (error) {
        if (updatedTarget) {
          updatedTarget.textContent = 'Refresh failed';
        }
      }
    };

    qsa('[data-admin-dashboard-refresh]', root).forEach((button) => button.addEventListener('click', refresh));
    window.setInterval(refresh, interval);
  }

  document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initConfirmations();
    initModalButtons();
    initTableFilters();
    initTabs();
    initLiveRefresh();
    initDashboardLive();
  });
})();
