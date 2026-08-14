(() => {
  'use strict';
  const api = (path, options = {}) => fetch(`../api/index.php?route=api/v1/modules/crm.google-calendar${path}`, { credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, ...options }).then(async response => { const data = await response.json().catch(() => ({})); if (!response.ok || data.success === false) throw new Error(data.message || data.error?.message || 'Ошибка API'); return data.data || data; });
  const $ = id => document.getElementById(id);
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#039;', '"':'&quot;' }[c]));
  let connection = null;
  const message = (text, bad = false) => { const el = $('gcalMessage'); if (!el) return; el.textContent = text; el.className = `small mt-3 ${bad ? 'text-danger' : 'text-success'}`; };
  const render = data => {
    const items = data.connections || [];
    const status = $('gcalStatus'); const root = $('gcalCalendars'); root.innerHTML = '';
    if (!items.length) { connection = null; status.textContent = 'Google Calendar ещё не подключён.'; return; }
    connection = items[0]; status.innerHTML = `<strong>Подключено</strong><div class="gcal-calendar-meta">${escapeHtml(connection.google_account_email || 'Google account')} · ${escapeHtml(connection.status || '')}</div>`;
    (connection.calendars || []).forEach(calendar => {
      const row = document.createElement('div'); row.className = 'gcal-calendar';
      row.innerHTML = `<div class="gcal-calendar-title">${escapeHtml(calendar.summary || calendar.calendar_id)}</div><div class="gcal-calendar-meta">${escapeHtml(calendar.timezone || '')}</div><select class="form-select form-select-sm" style="max-width:180px"><option value="google_to_crm">Google → CRM</option><option value="crm_to_google">CRM → Google</option><option value="bidirectional">Двусторонняя</option></select><button type="button" class="btn btn-sm crm-btn-secondary">Синхронизировать</button>`;
      const select = row.querySelector('select'); select.value = calendar.direction || 'google_to_crm'; select.addEventListener('change', () => api(`/calendars/${encodeURIComponent(calendar.public_id)}`, { method: 'PATCH', body: JSON.stringify({ direction: select.value, is_enabled: true }) }).then(() => message('Настройки календаря сохранены.')).catch(e => { message(e.message, true); select.value = calendar.direction || 'google_to_crm'; }));
      row.querySelector('button').addEventListener('click', () => sync()); root.appendChild(row);
    });
  };
  const load = () => api('/connections').then(render).catch(e => { $('gcalStatus').textContent = e.message; });
  const sync = () => { if (!connection) return; message('Синхронизация выполняется…'); api(`/connections/${encodeURIComponent(connection.public_id)}/sync`, { method: 'POST' }).then(data => { const r = data.result || {}; message(`Готово: загружено ${r.pulled || 0}, отправлено ${r.pushed || 0}, удалено ${r.deleted || 0}.`); load(); }).catch(e => message(e.message, true)); };
  const connect = () => api('/oauth/start', { method: 'POST' }).then(data => { if (!data.authorization_url) throw new Error('Google не вернул authorization URL'); window.open(data.authorization_url, '_blank', 'noopener'); message('Окно Google открыто. После подтверждения обновите страницу.'); }).catch(e => message(e.message, true));
  document.addEventListener('DOMContentLoaded', () => { $('gcalConnect')?.addEventListener('click', connect); $('gcalRefresh')?.addEventListener('click', load); load(); });
})();
