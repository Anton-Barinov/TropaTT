(() => {
  'use strict';
  const api = (path, options = {}) => {
    const route = `/_module/crm.yandex-calendar${path}`;
    if (window.CRM?.api?.request) return window.CRM.api.request(route, options).then(envelope => envelope.data || envelope);
    const method = String(options.method || 'GET').toUpperCase();
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    if (['POST','PATCH','PUT','DELETE'].includes(method) && window.CRM?.api?.getCsrfToken) headers['X-CSRF-Token'] = window.CRM.api.getCsrfToken();
    const hasBody = options.body !== undefined && options.body !== null;
    if (hasBody && typeof options.body !== 'string' && !headers['Content-Type']) headers['Content-Type'] = 'application/json';
    return fetch(`../api/index.php?route=${encodeURIComponent(route)}`, {...options, method, credentials:'same-origin', headers, body: hasBody ? (typeof options.body === 'string' ? options.body : JSON.stringify(options.body)) : undefined}).then(async response => { const data = await response.json().catch(() => ({})); if (!response.ok || data.success === false) throw new Error(data.message || data.code || 'Ошибка API'); return data.data || data; });
  };
  const $ = id => document.getElementById(id);
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
  let connection = null;
  const message = (text, bad = false) => { const el = $('ycalMessage'); if (!el) return; el.textContent = text; el.className = `small mt-3 ${bad ? 'text-danger' : 'text-success'}`; };
  const render = data => {
    const items = data.connections || []; const status = $('ycalStatus'); const root = $('ycalCalendars'); const actions = $('ycalConnectionActions'); root.innerHTML = ''; actions.innerHTML = '';
    if (!items.length) { connection = null; status.textContent = 'Яндекс.Календарь ещё не подключён.'; return; }
    connection = items[0]; status.innerHTML = `<strong>Подключено</strong><div class="ycal-calendar-meta">${escapeHtml(connection.account_email || '')} · ${escapeHtml(connection.status || '')}</div>`;
    actions.innerHTML = '<button type="button" class="btn crm-btn-secondary btn-sm" id="ycalTest"><i class="fa-solid fa-plug-circle-check"></i> Проверить</button><button type="button" class="btn btn-outline-danger btn-sm" id="ycalDisconnect"><i class="fa-solid fa-link-slash"></i> Отключить</button>';
    $('ycalTest').addEventListener('click', () => { message('Проверка подключения…'); api(`/connections/${encodeURIComponent(connection.public_id)}/test`, {method:'POST'}).then(() => message('Подключение работает.')).catch(e => message(e.message, true)); });
    $('ycalDisconnect').addEventListener('click', () => { if (!window.confirm('Отключить Яндекс.Календарь и удалить его события из CRM?')) return; api(`/connections/${encodeURIComponent(connection.public_id)}`, {method:'DELETE'}).then(() => { message('Яндекс.Календарь отключён.'); load(); }).catch(e => message(e.message, true)); });
    (connection.calendars || []).forEach(calendar => {
      const row = document.createElement('div'); row.className = 'ycal-calendar'; row.innerHTML = `<div class="ycal-calendar-title">${escapeHtml(calendar.display_name || 'Календарь')}<div class="ycal-calendar-meta">${escapeHtml(calendar.timezone || '')}</div></div><div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" ${calendar.is_enabled ? 'checked' : ''} aria-label="Включить календарь"></div><select class="form-select form-select-sm" style="max-width:180px"><option value="yandex_to_crm">Яндекс → CRM</option><option value="crm_to_yandex">CRM → Яндекс</option><option value="bidirectional">Двусторонняя</option></select><button type="button" class="btn btn-sm crm-btn-secondary">Синхронизировать</button>`;
      const select = row.querySelector('select'); const toggle = row.querySelector('input'); const button = row.querySelector('button'); select.value = calendar.direction || 'yandex_to_crm'; select.disabled = !toggle.checked; button.disabled = !toggle.checked;
      const save = () => api(`/calendars/${encodeURIComponent(calendar.public_id)}`, {method:'PATCH', body:{direction:select.value, is_enabled:toggle.checked}}).then(() => message('Настройки календаря сохранены.')).catch(e => message(e.message, true));
      select.addEventListener('change', save); toggle.addEventListener('change', () => { select.disabled = !toggle.checked; button.disabled = !toggle.checked; save(); }); button.addEventListener('click', sync); root.appendChild(row);
    });
  };
  const load = () => api('/connections').then(render).catch(e => { $('ycalStatus').textContent = e.message; });
  const sync = () => { if (!connection) return; message('Синхронизация выполняется…'); api(`/connections/${encodeURIComponent(connection.public_id)}/sync`, {method:'POST'}).then(data => { const r = data.result || {}; message(`Готово: загружено ${r.pulled || 0}, отправлено ${r.pushed || 0}, удалено ${r.deleted || 0}.`); load(); }).catch(e => message(e.message, true)); };
  document.addEventListener('DOMContentLoaded', () => { $('ycalConnectForm')?.addEventListener('submit', event => { event.preventDefault(); const email = $('ycalEmail').value.trim(); const appPassword = $('ycalPassword').value; message('Проверяем подключение…'); api('/connections', {method:'POST', body:{email, app_password:appPassword}}).then(() => { $('ycalPassword').value = ''; message('Яндекс.Календарь подключён.'); load(); }).catch(e => message(e.message, true)); }); $('ycalRefresh')?.addEventListener('click', load); load(); });
})();
