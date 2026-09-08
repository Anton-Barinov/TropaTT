/**
 * Admin API Clients — master-detail manager for API clients and their keys.
 * Auto-initializes on pages with data-page="admin-api-clients".
 *
 * Model: a client (integration application) holds several API keys. Keys carry
 * a name, an optional expiry and a scope allow-list. When scopes are empty the
 * key inherits the rights of the account it is bound to; explicit scopes can
 * only narrow the creator's own permissions (enforced server-side).
 */
window.CRM = window.CRM || {};
window.CRM.adminApiClients = (function () {
  'use strict';

  var state = {
    clients: [],
    selectedClientId: '',
    selectedClient: null,
    keys: [],
    catalog: [],
    grantableCodes: [],
    loaded: false,
    initialized: false
  };

  function t(key, fallback) {
    if (window.CRM.i18n && typeof window.CRM.i18n.t === 'function') {
      return window.CRM.i18n.t(key, fallback);
    }
    return fallback || key;
  }

  function esc(value) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(String(value == null ? '' : value)));
    return d.innerHTML;
  }

  function getApi() {
    return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
  }

  function req(route, opts) {
    var api = getApi();
    if (!api) return Promise.reject(new Error('API not ready'));
    return api.request(route, opts || {});
  }

  function notify(text, type) {
    if (window.CRM.br1 && typeof window.CRM.br1.notify === 'function') {
      window.CRM.br1.notify(text, type);
      return;
    }
    if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.notify === 'function') {
      window.CRM.pageApiBindings.notify(text, type);
      return;
    }
    if (typeof window.notify === 'function') {
      window.notify(text, type);
      return;
    }
    if (type === 'error') {
      // eslint-disable-next-line no-console
      console.error('[CRM]', text);
    }
  }

  function waitForApi(cb, n) {
    if (getApi()) { cb(); return; }
    if ((n || 0) > 100) return;
    setTimeout(function () { waitForApi(cb, (n || 0) + 1); }, 50);
  }

  function waitForBootstrap(cb) {
    var n = 0;
    (function poll() {
      if (typeof window.bootstrap !== 'undefined' && getApi()) { cb(); return; }
      n += 1;
      if (n > 120) return;
      setTimeout(poll, 60);
    })();
  }

  function formatDate(value) {
    if (!value) return '—';
    var date = new Date(String(value).replace(' ', 'T') + (String(value).indexOf('Z') === -1 && !/\+[0-9]{2}:[0-9]{2}$/.test(value) ? 'Z' : ''));
    if (isNaN(date.getTime())) return String(value);
    var opts = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString(undefined, opts);
  }

  function formatDateTimeLocal(value) {
    if (!value) return '';
    var date = new Date(String(value).replace(' ', 'T') + 'Z');
    if (isNaN(date.getTime())) return '';
    function p(n) { return String(n).padStart(2, '0'); }
    return date.getFullYear() + '-' + p(date.getMonth() + 1) + '-' + p(date.getDate())
      + 'T' + p(date.getHours()) + ':' + p(date.getMinutes());
  }

  function isKeyExpired(key) {
    var exp = String((key && key.expires_at) || '').trim();
    if (!exp) return false;
    var ts = Date.parse(String(exp).replace(' ', 'T') + 'Z');
    if (isNaN(ts)) return false;
    return ts < Date.now();
  }

  function isKeyActive(key) {
    return key && !key.revoked_at && !isKeyExpired(key);
  }

  function keyStatus(key) {
    if (key && key.revoked_at) return 'revoked';
    if (isKeyExpired(key)) return 'expired';
    return 'active';
  }

  function scopeTitle(code) {
    for (var i = 0; i < state.catalog.length; i++) {
      if (String(state.catalog[i].code || '') === String(code)) {
        return state.catalog[i].title || code;
      }
    }
    return code;
  }

  function scopeChipsHtml(codes, emptyLabel) {
    var list = Array.isArray(codes) ? codes : [];
    if (!list.length) {
      return '<span class="crm-scope-empty">' + esc(emptyLabel || '—') + '</span>';
    }
    var items = list.map(function (code) {
      return '<span class="crm-scope-token" title="' + esc(scopeTitle(code)) + '">' + esc(code) + '</span>';
    });
    return '<div class="crm-scope-list">' + items.join('') + '</div>';
  }

  // ── Data loading ────────────────────────────────────────────────────────

  function maskKey(key) {
    var s = String(key || '');
    if (s.length <= 8) return s;
    var prefix = s.substring(0, 7);
    var suffix = s.substring(s.length - 4);
    return prefix + '*'.repeat(Math.min(s.length - 11, 12)) + suffix;
  }

  function loadOptions() {
    return req('api/v1/api-clients/options', { noCache: true })
      .then(function (envelope) {
        var data = envelope && envelope.data ? envelope.data : {};
        state.catalog = Array.isArray(data.catalog) ? data.catalog : [];
        state.grantableCodes = Array.isArray(data.grantable_codes) ? data.grantable_codes : [];
      })
      .catch(function () {
        state.catalog = [];
        state.grantableCodes = [];
      });
  }

  function loadClients() {
    var searchInput = document.getElementById('apcSearchInput');
    var activeFilter = document.getElementById('apcActiveFilter');
    var query = { limit: 500 };
    if (searchInput && String(searchInput.value || '').trim()) {
      query.search = String(searchInput.value || '').trim();
    }
    if (activeFilter && (activeFilter.value === '0' || activeFilter.value === '1')) {
      query.is_active = activeFilter.value;
    }
    return req('api/v1/api-clients', { query: query, noCache: true })
      .then(function (envelope) {
        var data = envelope && envelope.data ? envelope.data : {};
        state.clients = (data.items && Array.isArray(data.items)) ? data.items : [];
        if (state.clients.length) {
          var stillThere = state.selectedClientId && state.clients.some(function (c) {
            return String(c.public_id || '') === String(state.selectedClientId);
          });
          if (!stillThere) {
            state.selectedClientId = String(state.clients[0].public_id || '');
          }
        } else {
          state.selectedClientId = '';
        }
        renderClientList();
        if (state.selectedClientId) {
          return loadClientDetail(state.selectedClientId);
        }
        renderDetailEmpty();
        return null;
      })
      .catch(function () {
        renderClientListError();
      });
  }

  function loadClientDetail(clientId) {
    return req('api/v1/api-clients/' + encodeURIComponent(clientId), { noCache: true })
      .then(function (env) {
        var data = env && env.data ? env.data : {};
        state.selectedClient = data.api_client || null;
        return req('api/v1/api-clients/' + encodeURIComponent(clientId) + '/keys', { noCache: true })
          .then(function (keysEnv) {
            var kd = keysEnv && keysEnv.data ? keysEnv.data : {};
            state.keys = (kd.items && Array.isArray(kd.items)) ? kd.items : [];
            renderDetail();
          });
      })
      .catch(function () {
        renderDetailError();
      });
  }

  // ── Rendering: master list ──────────────────────────────────────────────

  function clientRowHtml(client) {
    var id = String(client.public_id || '');
    var selected = String(id) === String(state.selectedClientId || '');
    var active = Number(client.is_active || 0) === 1;
    var activeKeys = Number(client.active_keys_count || 0);
    var scopeLabel = Array.isArray(client.scopes) && client.scopes.length
      ? client.scopes.length + ' ' + t('admin_api_clients.scopes_count', 'rights')
      : t('admin_api_clients.full_access_short', 'full access');
    return '<div class="crm-api-client-item' + (selected ? ' is-selected' : '') + '" data-client-id="' + esc(id) + '" tabindex="0" role="button">'
      + '<div class="d-flex justify-content-between align-items-start gap-2">'
      + '<div class="min-w-0">'
      + '<div class="crm-api-client-title">' + esc(client.title || id) + '</div>'
      + '<div class="crm-api-client-meta">'
      + '<span class="crm-badge ' + (active ? 'success' : 'archived') + '">' + esc(active ? t('common.active', 'Active') : t('common.inactive', 'Inactive')) + '</span>'
      + ' <span class="crm-api-client-sub">' + esc(activeKeys + ' ' + t('admin_api_clients.active_keys_short', 'keys')) + ' · ' + esc(scopeLabel) + '</span>'
      + '</div>'
      + '</div>'
      + '<span class="crm-chevron"></span>'
      + '</div>'
      + '</div>';
  }

  function renderClientList() {
    var list = document.getElementById('apcClientsList');
    if (!list) return;
    if (!state.clients.length) {
      list.innerHTML = '<div class="p-4 text-muted">' + esc(t('admin_api_clients.clients_empty', 'API clients not found. Create the first one.')) + '</div>';
      return;
    }
    list.innerHTML = state.clients.map(clientRowHtml).join('');
    bindClientRowClicks();
  }

  function renderClientListError() {
    var list = document.getElementById('apcClientsList');
    if (!list) return;
    list.innerHTML = '<div class="p-4 text-muted">' + esc(t('admin_api_clients.load_fail', 'Failed to load API clients.')) + '</div>';
  }

  function bindClientRowClicks() {
    var items = document.querySelectorAll('#apcClientsList .crm-api-client-item');
    items.forEach(function (item) {
      if (item.dataset.bound === '1') return;
      item.addEventListener('click', function () {
        state.selectedClientId = String(item.getAttribute('data-client-id') || '');
        renderClientList();
        loadClientDetail(state.selectedClientId);
      });
      item.dataset.bound = '1';
    });
  }

  // ── Rendering: detail pane ──────────────────────────────────────────────

  function activeKeyCount() {
    return state.keys.filter(function (k) { return isKeyActive(k); }).length;
  }

  function keyRowHtml(key, index) {
    var id = String(key.public_id || '');
    var name = String(key.name || '').trim() || (t('admin_api_clients.key_unnamed', 'Key') + ' #' + (index + 1));
    var status = keyStatus(key);
    var statusLabel = status === 'revoked'
      ? t('admin_api_clients.key_revoked', 'Revoked')
      : (status === 'expired' ? t('admin_api_clients.key_expired', 'Expired') : t('common.active', 'Active'));
    var statusClass = status === 'active' ? 'success' : (status === 'expired' ? 'warning' : 'archived');
    var scopesLabel = (Array.isArray(key.scopes) && key.scopes.length)
      ? key.scopes.map(scopeTitle).join(', ')
      : t('admin_api_clients.full_access_short', 'full access');
    var action = isKeyActive(key)
      ? '<button class="btn btn-sm crm-btn-danger-soft" data-key-revoke="' + esc(id) + '" type="button">' + esc(t('admin_api_clients.revoke_btn', 'Отозвать')) + '</button>'
      : '<span class="text-muted small">' + esc(statusLabel) + '</span>';
    var keyDisplay = key.key_preview ? String(key.key_preview) : maskKey(id);
    return '<tr>'
      + '<td><div class="crm-key-name">' + esc(name) + '</div><div class="crm-entity-id font-monospace small">' + esc(keyDisplay) + '</div></td>'
      + '<td><span class="crm-badge ' + statusClass + '">' + esc(statusLabel) + '</span></td>'
      + '<td>' + scopeChipsHtml(key.scopes, t('admin_api_clients.full_access_short', 'full access')) + '<div class="crm-key-scopes-title small text-muted">' + esc(scopesLabel) + '</div></td>'
      + '<td>' + esc(formatDate(key.created_at)) + '</td>'
      + '<td>' + esc(key.expires_at ? formatDate(key.expires_at) : (t('admin_api_clients.ttl_never', 'Без срока'))) + '</td>'
      + '<td class="text-end">' + action + '</td>'
      + '</tr>';
  }

  function renderDetail() {
    var wrap = document.getElementById('apcClientDetailWrap');
    if (!wrap) return;
    var client = state.selectedClient;
    if (!client) {
      renderDetailEmpty();
      return;
    }
    var active = Number(client.is_active || 0) === 1;
    var keysHtml = '<tr><td colspan="6" class="text-muted">' + esc(t('admin_api_clients.keys_empty', 'Keys not found. Issue the first key.')) + '</td></tr>';
    if (state.keys.length) {
      keysHtml = state.keys.map(keyRowHtml).join('');
    }
    var clientScopesLabel = (Array.isArray(client.scopes) && client.scopes.length)
      ? client.scopes.map(scopeTitle).join(', ')
      : t('admin_api_clients.full_access_like_me', 'Полный доступ — как у меня');

    wrap.innerHTML =
      '<div class="crm-card crm-section-card mb-3">'
      + '<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">'
      + '<div>'
      + '<h2 class="h5 mb-1">' + esc(client.title || client.public_id || '—') + '</h2>'
      + '<div class="crm-entity-id mb-1">' + esc(String(client.public_id || '')) + '</div>'
      + '<div class="d-flex align-items-center gap-2 flex-wrap">'
      + '<span class="crm-badge ' + (active ? 'success' : 'archived') + '">' + esc(active ? t('common.active', 'Active') : t('common.inactive', 'Inactive')) + '</span>'
      + '<span class="text-muted small">' + esc(t('admin_api_clients.created_prefix', 'Created: ') + formatDate(client.created_at)) + '</span>'
      + '</div>'
      + '</div>'
      + '<div class="d-flex gap-2 flex-wrap">'
      + '<button class="btn crm-btn-primary" id="apcIssueKeyBtn" type="button">' + esc(t('admin_api_clients.new_key_btn', 'Новый ключ')) + '</button>'
      + '<button class="btn crm-btn-secondary" id="apcEditClientBtn" type="button">' + esc(t('common.edit', 'Изменить')) + '</button>'
      + '<button class="btn crm-btn-danger-soft" id="apcDeleteClientBtn" type="button">' + esc(t('common.delete', 'Удалить')) + '</button>'
      + '</div>'
      + '</div>'
      + '<hr class="my-3">'
      + '<div class="row g-3">'
      + '<div class="col-md-6">'
      + '<div class="small text-muted mb-1">' + esc(t('admin_api_clients.client_rights_label', 'Права клиента')) + '</div>'
      + '<div class="crm-scope-list">' + scopeChipsHtml(client.scopes, t('admin_api_clients.full_access_like_me', 'Полный доступ — как у меня')) + '</div>'
      + '<div class="small text-muted mt-1">' + esc(clientScopesLabel) + '</div>'
      + '</div>'
      + '<div class="col-md-6">'
      + '<div class="small text-muted mb-1">' + esc(t('admin_api_clients.client_keys_label', 'Ключи')) + '</div>'
      + '<div class="h6 mb-0">' + esc(String(activeKeyCount()) + ' ' + t('admin_api_clients.active_keys_of', 'active of ') + String(state.keys.length)) + '</div>'
      + '</div>'
      + '</div>'
      + '</div>'
      + '<div class="crm-card crm-section-card p-0 table-responsive">'
      + '<table class="table crm-table mb-0"><thead><tr>'
      + '<th>' + esc(t('admin_api_clients.th_key_name', 'Ключ')) + '</th>'
      + '<th>' + esc(t('admin_api_clients.th_key_status', 'Статус')) + '</th>'
      + '<th>' + esc(t('admin_api_clients.th_key_scopes', 'Права')) + '</th>'
      + '<th>' + esc(t('admin_api_clients.th_created', 'Создан')) + '</th>'
      + '<th>' + esc(t('admin_api_clients.th_expires', 'Истекает')) + '</th>'
      + '<th class="text-end">' + esc(t('admin_api_clients.th_actions', 'Действия')) + '</th>'
      + '</tr></thead><tbody>' + keysHtml + '</tbody></table>'
      + '</div>';

    document.getElementById('apcIssueKeyBtn').addEventListener('click', openKeyModal);
    document.getElementById('apcEditClientBtn').addEventListener('click', openEditClientModal);
    document.getElementById('apcDeleteClientBtn').addEventListener('click', confirmDeleteClient);
    bindKeyActions();
  }

  function renderDetailEmpty() {
    var wrap = document.getElementById('apcClientDetailWrap');
    if (!wrap) return;
    wrap.innerHTML = '<div class="crm-card crm-section-card p-4 text-muted">'
      + esc(t('admin_api_clients.select_client_hint', 'Выберите клиента слева, чтобы управлять его ключами.'))
      + '</div>';
  }

  function renderDetailError() {
    var wrap = document.getElementById('apcClientDetailWrap');
    if (!wrap) return;
    wrap.innerHTML = '<div class="crm-card crm-section-card p-4 text-muted">' + esc(t('admin_api_clients.detail_fail', 'Failed to load client.')) + '</div>';
  }

  function bindKeyActions() {
    document.querySelectorAll('[data-key-revoke]').forEach(function (btn) {
      if (btn.dataset.bound === '1') return;
      btn.addEventListener('click', function () {
        var keyId = String(btn.getAttribute('data-key-revoke') || '').trim();
        revokeKeyById(keyId);
      });
      btn.dataset.bound = '1';
    });
  }

  // ── Permissions checkbox list (scope picker) ────────────────────────────

  function codeGroups(codes) {
    var groups = {};
    (codes || []).forEach(function (code) {
      var prefix = String(code || '').split('.')[0] || 'other';
      if (!groups[prefix]) groups[prefix] = [];
      groups[prefix].push(code);
    });
    var names = Object.keys(groups).sort();
    var result = [];
    names.forEach(function (name) {
      result.push({ group: name, codes: groups[name].sort() });
    });
    return result;
  }

  function allowedScopeCodes(scopeCeiling) {
    var grantable = state.grantableCodes;
    if (!scopeCeiling || !scopeCeiling.length) {
      return grantable;
    }
    var ceilingSet = {};
    scopeCeiling.forEach(function (c) { ceilingSet[String(c)] = true; });
    var grantableSet = {};
    grantable.forEach(function (c) { grantableSet[String(c)] = true; });
    return grantable.filter(function (c) {
      return ceilingSet[String(c)] === true || grantableSet[String(c)] === true;
    });
  }

  function renderScopePicker(containerId, checkedCodes, options) {
    var container = document.getElementById(containerId);
    if (!container) return;
    var opts = options || {};
    var ceiling = opts.ceiling || [];
    var scopeOptions = allowedScopeCodes(ceiling);
    // Preselect: explicitly checked codes first; otherwise grantable codes when
    // full access is on; otherwise (manual mode) everything that can be granted.
    var checkedSet = {};
    (checkedCodes || []).forEach(function (c) { checkedSet[String(c)] = true; });

    var html = '';
    var groups = codeGroups(scopeOptions);
    groups.forEach(function (group) {
      html += '<div class="crm-perm-group mb-1"><div class="crm-perm-group-title small text-muted">' + esc(group.group) + '</div>';
      html += group.codes.map(function (code) {
        var isChecked = checkedSet[String(code)] === true;
        return '<div class="form-check form-check-inline crm-perm-check">'
          + '<input class="form-check-input" type="checkbox" id="' + esc(containerId + '_' + code) + '" value="' + esc(code) + '"' + (isChecked ? ' checked' : '') + '>'
          + '<label class="form-check-label" for="' + esc(containerId + '_' + code) + '">' + esc(scopeTitle(code)) + '</label>'
          + '</div>';
      }).join('');
      html += '</div>';
    });
    if (!scopeOptions.length) {
      html = '<div class="text-muted small">' + esc(t('admin_api_clients.no_grantable_scopes', 'Нет разделов, доступных для выдачи')) + '</div>';
    }
    container.innerHTML = html;
  }

  function readScopePicker(containerId) {
    var container = document.getElementById(containerId);
    if (!container) return [];
    return Array.prototype.map.call(
      container.querySelectorAll('input[type="checkbox"]:checked'),
      function (cb) { return String(cb.value || ''); }
    ).filter(Boolean);
  }

  // ── Client modal (create/edit) ──────────────────────────────────────────

  function openNewClientModal() {
    var form = document.getElementById('apcClientForm');
    var modalTitle = document.getElementById('apcClientModalTitle');
    if (form) form.reset();
    if (form) form.querySelector('[name="public_id"]').value = '';
    if (form) form.querySelector('[name="is_active"]').value = '1';
    if (modalTitle) modalTitle.textContent = t('admin_api_clients.modal_client_create', 'Новый клиент');
    var fullBox = document.getElementById('apcClientFullAccess');
    if (fullBox) fullBox.checked = true;
    toggleClientScopeBlock();
    renderScopePicker('apcClientScopesBlock', state.grantableCodes, {});
    var modalEl = document.getElementById('apcClientModal');
    if (modalEl) window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }

  function openEditClientModal() {
    var client = state.selectedClient;
    if (!client) {
      notify(t('admin_api_clients.select_client_first', 'Сначала выберите клиента'), 'warning');
      return;
    }
    var form = document.getElementById('apcClientForm');
    var modalTitle = document.getElementById('apcClientModalTitle');
    if (modalTitle) modalTitle.textContent = t('admin_api_clients.modal_client_edit', 'Изменить клиента');
    if (form) {
      form.querySelector('[name="public_id"]').value = String(client.public_id || '');
      form.querySelector('[name="title"]').value = String(client.title || '');
      form.querySelector('[name="is_active"]').value = Number(client.is_active || 0) === 1 ? '1' : '0';
    }
    var hasRestriction = Array.isArray(client.scopes) && client.scopes.length > 0;
    var fullBox = document.getElementById('apcClientFullAccess');
    if (fullBox) fullBox.checked = !hasRestriction;
    toggleClientScopeBlock();
    renderScopePicker('apcClientScopesBlock', hasRestriction ? client.scopes : state.grantableCodes, {});
    var modalEl = document.getElementById('apcClientModal');
    if (modalEl) window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }

  function toggleClientScopeBlock() {
    var fullBox = document.getElementById('apcClientFullAccess');
    var block = document.getElementById('apcClientScopesBlock');
    if (!fullBox || !block) return;
    block.classList.toggle('d-none', fullBox.checked === true);
    if (fullBox.checked !== true) {
      renderScopePicker('apcClientScopesBlock', state.grantableCodes, {});
    }
  }

  function clientPayloadFromForm() {
    var form = document.getElementById('apcClientForm');
    if (!form) return null;
    var title = String((form.querySelector('[name="title"]') || {}).value || '').trim();
    if (!title) {
      notify(t('admin_api_clients.title_required', 'Введите название клиента'), 'warning');
      return null;
    }
    var publicId = String((form.querySelector('[name="public_id"]') || {}).value || '').trim();
    var isActive = String((form.querySelector('[name="is_active"]') || {}).value || '1');
    var fullBox = document.getElementById('apcClientFullAccess');
    var scopes = (fullBox && fullBox.checked === true) ? [] : readScopePicker('apcClientScopesBlock');
    return {
      public_id: publicId,
      title: title,
      is_active: isActive === '1' ? 1 : 0,
      scopes: scopes
    };
  }

  function submitClientForm() {
    var form = document.getElementById('apcClientForm');
    if (!form) return;
    var payload = clientPayloadFromForm();
    if (!payload) return;
    var isEdit = Boolean(payload.public_id);
    var route = isEdit
      ? 'api/v1/api-clients/' + encodeURIComponent(payload.public_id)
      : 'api/v1/api-clients';
    var method = isEdit ? 'PATCH' : 'POST';
    req(route, { method: method, body: payload })
      .then(function (envelope) {
        var modalEl = document.getElementById('apcClientModal');
        if (modalEl) window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        notify(t('admin_api_clients.client_saved', 'Клиент сохранён'));
        return loadClients().then(function () {
          if (!isEdit) {
            var plain = envelope && envelope.data ? envelope.data.plain_key : '';
            var keyMeta = envelope && envelope.data ? envelope.data.api_key : null;
            if (plain) {
              openRevealModal(plain, keyMeta);
            }
          }
        });
      })
      .catch(function (error) {
        var env = error && error.envelope ? error.envelope : null;
        notify((env && env.message) || t('admin_api_clients.client_save_fail', 'Не удалось сохранить клиента'), 'error');
      });
  }

  // ── Key modal ───────────────────────────────────────────────────────────

  function openKeyModal() {
    var client = state.selectedClient;
    if (!client) {
      notify(t('admin_api_clients.select_client_first', 'Сначала выберите клиента'), 'warning');
      return;
    }
    if (Number(client.is_active || 0) !== 1) {
      notify(t('admin_api_clients.client_inactive_no_keys', 'Клиент неактивен: сначала включите его'), 'warning');
      return;
    }
    var form = document.getElementById('apcKeyForm');
    if (form) form.reset();
    var count = state.keys.length + 1;
    var nameInput = form && form.querySelector('[name="name"]');
    if (nameInput && !nameInput.value) {
      nameInput.value = t('admin_api_clients.key_default_name', 'Ключ') + ' ' + count;
    }
    var ttlSelect = form && form.querySelector('[name="ttl"]');
    if (ttlSelect) ttlSelect.value = '90';
    toggleKeyExpiry();
    var fullBox = document.getElementById('apcKeyFullAccess');
    if (fullBox) fullBox.checked = true;
    toggleKeyScopeBlock();
    var ceiling = Array.isArray(client.scopes) && client.scopes.length ? client.scopes : [];
    renderScopePicker('apcKeyScopesBlock', ceiling.length ? ceiling : state.grantableCodes, { ceiling: ceiling });
    var modalEl = document.getElementById('apcKeyModal');
    if (modalEl) window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }

  function toggleKeyExpiry() {
    var ttlSelect = document.querySelector('#apcKeyForm [name="ttl"]');
    var expiryWrap = document.getElementById('apcKeyExpiryWrap');
    if (!ttlSelect || !expiryWrap) return;
    expiryWrap.classList.toggle('d-none', String(ttlSelect.value || '') !== 'custom');
  }

  function toggleKeyScopeBlock() {
    var fullBox = document.getElementById('apcKeyFullAccess');
    var block = document.getElementById('apcKeyScopesBlock');
    if (!fullBox || !block) return;
    block.classList.toggle('d-none', fullBox.checked === true);
    if (fullBox.checked !== true) {
      var client = state.selectedClient || {};
      var ceiling = Array.isArray(client.scopes) && client.scopes.length ? client.scopes : [];
      renderScopePicker('apcKeyScopesBlock', ceiling.length ? ceiling : state.grantableCodes, { ceiling: ceiling });
    }
  }

  function computeExpiresAt() {
    var ttlSelect = document.querySelector('#apcKeyForm [name="ttl"]');
    var ttl = String((ttlSelect && ttlSelect.value) || '');
    if (!ttl) return '';
    if (ttl === 'custom') {
      var localInput = document.querySelector('#apcKeyForm [name="expires_at_local"]');
      var localValue = String((localInput && localInput.value) || '');
      if (!localValue) {
        notify(t('admin_api_clients.expiry_required', 'Укажите дату окончания'), 'warning');
        return null;
      }
      var d = new Date(localValue);
      if (isNaN(d.getTime())) {
        notify(t('admin_api_clients.expiry_invalid', 'Некорректная дата окончания'), 'warning');
        return null;
      }
      function p(n) { return String(n).padStart(2, '0'); }
      return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate())
        + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':00';
    }
    var days = parseInt(ttl, 10);
    if (!days || days <= 0) return '';
    var ts = new Date();
    ts.setUTCDate(ts.getUTCDate() + days);
    function pu(n) { return String(n).padStart(2, '0'); }
    return ts.getUTCFullYear() + '-' + pu(ts.getUTCMonth() + 1) + '-' + pu(ts.getUTCDate())
      + ' ' + pu(ts.getUTCHours()) + ':' + pu(ts.getUTCMinutes()) + ':00';
  }

  function submitKeyForm() {
    var client = state.selectedClient;
    var form = document.getElementById('apcKeyForm');
    if (!client || !form) return;
    var clientId = String(client.public_id || '').trim();
    var name = String((form.querySelector('[name="name"]') || {}).value || '').trim();
    var fullBox = document.getElementById('apcKeyFullAccess');
    var ceiling = Array.isArray(client.scopes) && client.scopes.length ? client.scopes : [];
    var scopes = (fullBox && fullBox.checked === true) ? [] : readScopePicker('apcKeyScopesBlock');
    var expiresAt = computeExpiresAt();
    if (expiresAt === null) return;
    var body = { name: name, scopes: scopes };
    if (expiresAt) body.expires_at = expiresAt;
    var submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;
    req('api/v1/api-clients/' + encodeURIComponent(clientId) + '/keys', { method: 'POST', body: body })
      .then(function (envelope) {
        var modalEl = document.getElementById('apcKeyModal');
        if (modalEl) window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        notify(t('admin_api_clients.key_issued', 'Ключ выпущен'));
        var plain = envelope && envelope.data ? envelope.data.plain_key : '';
        var keyMeta = envelope && envelope.data ? envelope.data.api_key : null;
        if (submitBtn) submitBtn.disabled = false;
        if (plain) {
          openRevealModal(plain, keyMeta);
        }
        return loadClientDetail(clientId);
      })
      .catch(function (error) {
        var env = error && error.envelope ? error.envelope : null;
        notify((env && env.message) || t('admin_api_clients.key_issue_fail', 'Не удалось выпустить ключ'), 'error');
        if (submitBtn) submitBtn.disabled = false;
      });
  }

  // ── Reveal (one-time) modal ─────────────────────────────────────────────

  function openRevealModal(plainKey, keyMeta) {
    var input = document.getElementById('apcRevealKeyInput');
    var label = document.getElementById('apcRevealKeyLabel');
    var idEl = document.getElementById('apcRevealKeyId');
    if (input) input.value = String(plainKey || '');
    if (label) {
      var name = keyMeta && keyMeta.name ? String(keyMeta.name).trim() : '';
      label.textContent = name
        ? (t('admin_api_clients.reveal_key_name', 'Ключ') + ': ' + name)
        : '';
    }
    if (idEl) {
      var preview = keyMeta && keyMeta.key_preview ? String(keyMeta.key_preview) : '';
      var kid = keyMeta && keyMeta.public_id ? String(keyMeta.public_id) : '';
      idEl.textContent = preview
        ? (t('admin_api_clients.reveal_key_masked', 'В списке ключей:') + ' ' + preview)
        : (kid ? (t('admin_api_clients.reveal_key_masked', 'В списке ключей:') + ' ' + maskKey(kid)) : '');
    }
    var modalEl = document.getElementById('apcRevealModal');
    if (!modalEl) return;
    var modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
    var copyBtn = document.getElementById('apcRevealCopyBtn');
    if (copyBtn) {
      var newBtn = copyBtn.cloneNode(true);
      copyBtn.parentNode.replaceChild(newBtn, copyBtn);
      newBtn.addEventListener('click', function () {
        var target = document.getElementById('apcRevealKeyInput');
        if (!target) return;
        var text = target.value;
        var btn = newBtn;
        var originalText = btn.textContent;
        function showCopied() {
          btn.textContent = t('page.copied', 'Скопировано');
          setTimeout(function () { btn.textContent = originalText; }, 1500);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(function () {
            showCopied();
            notify(t('page.copied', 'Скопировано'));
          }, function () {
            fallbackCopy(target);
            showCopied();
          });
        } else {
          fallbackCopy(target);
          showCopied();
        }
      });
    }
  }

  function fallbackCopy(input) {
    try {
      input.focus();
      input.select();
      var ok = document.execCommand('copy');
      if (ok) {
        notify(t('page.copied', 'Скопировано'));
      } else {
        notify(t('admin_api_clients.copy_fail', 'Не удалось скопировать'), 'error');
      }
    } catch (e) {
      notify(t('admin_api_clients.copy_fail', 'Не удалось скопировать'), 'error');
    }
  }

  // ── Destructive actions ─────────────────────────────────────────────────

  function revokeKeyById(keyId) {
    if (!keyId) return;
    if (!window.confirm(t('admin_api_clients.revoke_confirm', 'Отозвать ключ? После отзыва он перестанет работать.'))) return;
    req('api/v1/api-keys/' + encodeURIComponent(keyId) + '/revoke', { method: 'POST' })
      .then(function () {
        notify(t('admin_api_clients.key_revoked_done', 'Ключ отозван'));
        var clientId = state.selectedClientId;
        return loadClientDetail(clientId);
      })
      .catch(function (error) {
        var env = error && error.envelope ? error.envelope : null;
        notify((env && env.message) || t('admin_api_clients.key_revoke_fail', 'Не удалось отозвать ключ'), 'error');
      });
  }

  function confirmDeleteClient() {
    var client = state.selectedClient;
    if (!client) {
      notify(t('admin_api_clients.select_client_first', 'Сначала выберите клиента'), 'warning');
      return;
    }
    var issuedCount = state.keys.length;
    var msg = t('admin_api_clients.delete_client_confirm', 'Удалить клиента?');
    if (issuedCount > 0) {
      msg += ' ' + t('admin_api_clients.delete_client_keys_warning', 'Все выданные ключи будут отозваны.');
    }
    if (!window.confirm(msg)) return;
    var clientId = String(client.public_id || '').trim();
    // Always cascade: deleting the client must revoke every issued key in one step.
    var body = { revoke_keys: true };
    req('api/v1/api-clients/' + encodeURIComponent(clientId), { method: 'DELETE', body: body })
      .then(function () {
        notify(t('admin_api_clients.client_deleted', 'Клиент удалён'));
        state.selectedClientId = '';
        state.selectedClient = null;
        state.keys = [];
        return loadClients();
      })
      .catch(function (error) {
        var env = error && error.envelope ? error.envelope : null;
        notify((env && env.message) || t('admin_api_clients.client_delete_fail', 'Не удалось удалить клиента'), 'error');
      });
  }

  // ── Init ────────────────────────────────────────────────────────────────

  function bindStaticEvents() {
    var newClientBtn = document.getElementById('apcNewClientBtn');
    if (newClientBtn && newClientBtn.dataset.bound !== '1') {
      newClientBtn.addEventListener('click', openNewClientModal);
      newClientBtn.dataset.bound = '1';
    }
    var searchInput = document.getElementById('apcSearchInput');
    if (searchInput && searchInput.dataset.bound !== '1') {
      var timer = null;
      searchInput.addEventListener('input', function () {
        if (timer) window.clearTimeout(timer);
        timer = window.setTimeout(function () { loadClients(); }, 300);
      });
      searchInput.dataset.bound = '1';
    }
    var activeFilter = document.getElementById('apcActiveFilter');
    if (activeFilter && activeFilter.dataset.bound !== '1') {
      activeFilter.addEventListener('change', function () { loadClients(); });
      activeFilter.dataset.bound = '1';
    }
    var clientForm = document.getElementById('apcClientForm');
    if (clientForm && clientForm.dataset.bound !== '1') {
      clientForm.addEventListener('submit', function (e) {
        e.preventDefault();
        submitClientForm();
      });
      clientForm.dataset.bound = '1';
    }
    var clientFullBox = document.getElementById('apcClientFullAccess');
    if (clientFullBox && clientFullBox.dataset.bound !== '1') {
      clientFullBox.addEventListener('change', toggleClientScopeBlock);
      clientFullBox.dataset.bound = '1';
    }
    var keyForm = document.getElementById('apcKeyForm');
    if (keyForm && keyForm.dataset.bound !== '1') {
      keyForm.addEventListener('submit', function (e) {
        e.preventDefault();
        submitKeyForm();
      });
      keyForm.dataset.bound = '1';
    }
    var ttlSelect = keyForm && keyForm.querySelector('[name="ttl"]');
    if (ttlSelect && ttlSelect.dataset.bound !== '1') {
      ttlSelect.addEventListener('change', toggleKeyExpiry);
      ttlSelect.dataset.bound = '1';
    }
    var keyFullBox = document.getElementById('apcKeyFullAccess');
    if (keyFullBox && keyFullBox.dataset.bound !== '1') {
      keyFullBox.addEventListener('change', toggleKeyScopeBlock);
      keyFullBox.dataset.bound = '1';
    }
  }

  function init() {
    if (state.initialized) return;
    if (document.body.getAttribute('data-page') !== 'admin-api-clients') return;
    state.initialized = true;
    bindStaticEvents();
    waitForBootstrap(function () {
      loadOptions().then(loadClients);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return {
    init: init,
    loadClients: loadClients,
    openRevealModal: openRevealModal
  };
})();
