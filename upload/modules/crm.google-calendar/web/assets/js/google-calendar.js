(() => {
  'use strict';
  const api = (path, options = {}) => {
    const route = `/_module/crm.google-calendar${path}`;
    const normalized = { ...options };
    if (typeof normalized.body === 'string') {
      try { normalized.body = JSON.parse(normalized.body); } catch (_) { /* keep non-JSON bodies unchanged */ }
    }
    if (window.CRM?.api?.request) {
      return window.CRM.api.request(route, normalized).then(envelope => envelope.data || envelope);
    }

    // Fallback for a page rendered without the shared API bundle. Keep the
    // same module route and explicitly forward the session CSRF token.
    const headers = { Accept: 'application/json', ...(normalized.headers || {}) };
    const method = String(normalized.method || 'GET').toUpperCase();
    if (['POST', 'PATCH', 'PUT', 'DELETE'].includes(method) && window.CRM?.api?.getCsrfToken) {
      const csrf = window.CRM.api.getCsrfToken();
      if (csrf) headers['X-CSRF-Token'] = csrf;
    }
    const hasBody = normalized.body !== undefined && normalized.body !== null;
    if (hasBody && typeof normalized.body !== 'string' && !headers['Content-Type'] && !headers['content-type']) {
      headers['Content-Type'] = 'application/json';
    }
    const body = hasBody
      ? (typeof normalized.body === 'string' ? normalized.body : JSON.stringify(normalized.body))
      : undefined;
    return fetch(`../api/index.php?route=${encodeURIComponent(route)}`, {
      ...normalized,
      method,
      credentials: 'same-origin',
      headers,
      body,
    }).then(async response => {
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.success === false) throw new Error(data.message || data.error?.message || 'Ошибка API');
      return data.data || data;
    });
  };
  const $ = id => document.getElementById(id);
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#039;', '"':'&quot;' }[c]));
  let connection = null;
  const message = (text, bad = false) => { const el = $('gcalMessage'); if (!el) return; el.textContent = text; el.className = `small mt-3 ${bad ? 'text-danger' : 'text-success'}`; };
  const render = data => {
    const items = data.connections || [];
    const status = $('gcalStatus'); const actions = $('gcalConnectionActions'); const root = $('gcalCalendars'); root.innerHTML = ''; if (actions) actions.innerHTML = '';
    if (!items.length) { connection = null; status.textContent = 'Google Calendar ещё не подключён.'; return; }
    connection = items[0]; status.innerHTML = `<strong>Подключено</strong><div class="gcal-calendar-meta">${escapeHtml(connection.google_account_email || 'Google account')} · ${escapeHtml(connection.status || '')}</div>`;
    if (actions) {
      actions.innerHTML = '<button type="button" class="btn crm-btn-secondary btn-sm" id="gcalTest"><i class="fa-solid fa-plug-circle-check"></i> Проверить</button><button type="button" class="btn btn-outline-danger btn-sm" id="gcalDisconnect"><i class="fa-solid fa-link-slash"></i> Отключить</button>';
      $('gcalTest')?.addEventListener('click', () => { message('Проверка подключения…'); api(`/connections/${encodeURIComponent(connection.public_id)}/test`, { method: 'POST' }).then(() => message('Подключение работает.')).catch(e => message(e.message, true)); });
      $('gcalDisconnect')?.addEventListener('click', () => { if (!window.confirm('Отключить Google Calendar и удалить синхронизированные события из CRM?')) return; api(`/connections/${encodeURIComponent(connection.public_id)}`, { method: 'DELETE' }).then(() => { message('Google Calendar отключён.'); load(); }).catch(e => message(e.message, true)); });
    }
    (connection.calendars || []).forEach(calendar => {
      const row = document.createElement('div'); row.className = 'gcal-calendar';
      row.innerHTML = `<div class="gcal-calendar-title">${escapeHtml(calendar.summary || calendar.calendar_id)}<div class="gcal-calendar-meta">${escapeHtml(calendar.timezone || '')}</div></div><div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" ${calendar.is_enabled ? 'checked' : ''} aria-label="Включить календарь"></div><select class="form-select form-select-sm" style="max-width:180px" ${calendar.is_enabled ? '' : 'disabled'}><option value="google_to_crm">Google → CRM</option><option value="crm_to_google">CRM → Google</option><option value="bidirectional">Двусторонняя</option></select><button type="button" class="btn btn-sm crm-btn-secondary" ${calendar.is_enabled ? '' : 'disabled'}>Синхронизировать</button>`;
      const select = row.querySelector('select'); const toggle = row.querySelector('input[type="checkbox"]'); const button = row.querySelector('button'); select.value = calendar.direction || 'google_to_crm'; const save = () => api(`/calendars/${encodeURIComponent(calendar.public_id)}`, { method: 'PATCH', body: { direction: select.value, is_enabled: toggle.checked } }).then(() => message('Настройки календаря сохранены.')).catch(e => message(e.message, true)); select.addEventListener('change', save); toggle.addEventListener('change', () => { select.disabled = !toggle.checked; button.disabled = !toggle.checked; save(); }); button.addEventListener('click', () => sync()); root.appendChild(row);
    });
  };
  const load = () => api('/connections').then(render).catch(e => { $('gcalStatus').textContent = e.message; });
  const sync = () => { if (!connection) return; message('Синхронизация выполняется…'); api(`/connections/${encodeURIComponent(connection.public_id)}/sync`, { method: 'POST' }).then(data => { const r = data.result || {}; message(`Готово: загружено ${r.pulled || 0}, отправлено ${r.pushed || 0}, удалено ${r.deleted || 0}.`); load(); }).catch(e => message(e.message, true)); };
  const connect = () => {
    // Open synchronously from the click handler so popup blockers do not
    // discard the OAuth window after the asynchronous API response.
    const popup = window.open('about:blank', '_blank', 'noopener');
    api('/oauth/start', { method: 'POST', body: {} }).then(data => {
      if (!data.authorization_url) throw new Error('Google не вернул authorization URL');
      if (popup && !popup.closed) popup.location.href = data.authorization_url;
      else window.location.href = data.authorization_url;
      message('Окно Google открыто. После подтверждения обновите страницу.');
    }).catch(e => {
      if (popup && !popup.closed) popup.close();
      message(e.message, true);
    });
  };
  document.addEventListener('DOMContentLoaded', () => {
    // This module's page may not be mounted (e.g. stale cached HTML that
    // still references this script). Never touch DOM that does not exist.
    if (!$('gcalConnect') && !$('gcalStatus') && !$('gcalCalendars')) return;
    $('gcalConnect')?.addEventListener('click', connect);
    $('gcalRefresh')?.addEventListener('click', load);
    load();
  });
})();
