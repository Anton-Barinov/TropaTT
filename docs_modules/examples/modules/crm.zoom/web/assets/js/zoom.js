(() => {
  'use strict';
  const API = '_module/crm.zoom';
  const api = (path, options = {}) => window.CRM?.api?.request
    ? window.CRM.api.request(`${API}/${path}`, options).then(envelope => envelope.data || envelope)
    : Promise.reject(new Error('CRM API client is unavailable'));
  const byId = id => document.getElementById(id);
  const message = (id, text, error = false) => {
    const node = byId(id);
    if (node) { node.textContent = text; node.className = `small mt-3 ${error ? 'text-danger' : 'text-muted'}`; }
  };
  const lock = (button, active, idle, busy) => { button.disabled = active; button.textContent = active ? busy : idle; };
  const newIdempotencyKey = () => window.crypto?.randomUUID
    ? window.crypto.randomUUID()
    : `zoom-${Date.now()}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
  const pendingCreateStorageKey = 'crm.zoom.pending-create.v1';
  const readPendingCreate = () => {
    try { return JSON.parse(window.sessionStorage.getItem(pendingCreateStorageKey) || 'null'); }
    catch (_) { return null; }
  };

  document.addEventListener('DOMContentLoaded', async () => {
    if (!byId('zoomConfigForm')) return;
    if (byId('zoomTimezone') && Intl.DateTimeFormat().resolvedOptions().timeZone) byId('zoomTimezone').value = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const loadState = async () => {
      const state = await api('status');
      byId('zoomStatus').textContent = state.connected ? 'Подключено' : (state.configured ? 'Настройки сохранены; проверьте подключение.' : 'Zoom не подключён.');
      byId('zoomAccount').textContent = state.host_email ? `Аккаунт Zoom: ${state.host_email}` : '';
      byId('zoomStatusMessage').textContent = state.message || '';
      if (state.account_id) byId('zoomAccountId').value = state.account_id;
      if (state.client_id) byId('zoomClientId').value = state.client_id;
      if (state.host_user_id) byId('zoomHostUserId').value = state.host_user_id;
    };
    const loadMeetings = async () => {
      const data = await api('meetings');
      const host = byId('zoomMeetings');
      host.replaceChildren();
      for (const item of data.items || []) {
        const row = document.createElement('article'); row.className = 'zoom-meeting'; row.setAttribute('role', 'listitem');
        const title = document.createElement('strong'); title.textContent = item.topic || 'Zoom-встреча';
        const date = document.createElement('span'); date.textContent = `${item.start_time || ''} · ${item.duration_minutes || 0} мин.`;
        row.append(title, date);
        try { const url = new URL(item.join_url); if (url.protocol === 'https:' && ['zoom.us', 'www.zoom.us'].includes(url.hostname) && /^\/j\/[0-9]+$/.test(url.pathname)) { const link = document.createElement('a'); link.href = url.href; link.target = '_blank'; link.rel = 'noopener noreferrer'; link.textContent = 'Открыть ссылку участника'; row.append(link); } } catch (_) { /* Ignore malformed persisted URLs. */ } host.append(row);
      }
      if (!host.childElementCount) { const empty = document.createElement('p'); empty.className = 'small text-muted mb-0'; empty.textContent = 'Встреч пока нет.'; host.append(empty); }
    };
    try { await Promise.all([loadState(), loadMeetings()]); }
    catch (_) { byId('zoomStatus').textContent = 'Не удалось загрузить состояние. Проверьте доступ и обновите страницу.'; }

    byId('zoomConfigForm').addEventListener('submit', async event => {
      event.preventDefault(); const button = byId('zoomSaveBtn'); lock(button, true, 'Сохранить настройки', 'Сохранение…');
      try {
        await api('settings', { method: 'POST', body: Object.fromEntries(new FormData(event.currentTarget)) });
        byId('zoomClientSecret').value = ''; message('zoomConfigMessage', 'Реквизиты сохранены в зашифрованном виде. Проверьте подключение.'); await loadState();
      } catch (_) { message('zoomConfigMessage', 'Настройки не сохранены. Проверьте поля и APP_KEY.', true); }
      finally { lock(button, false, 'Сохранить настройки', 'Сохранение…'); }
    });
    byId('zoomTestBtn').addEventListener('click', async event => {
      const button = event.currentTarget; lock(button, true, 'Проверить подключение', 'Проверка…');
      try { const result = await api('test-connection', { method: 'POST', body: {} }); message('zoomConfigMessage', `Подключение подтверждено: ${result.host_email}.`); await loadState(); }
      catch (_) { message('zoomConfigMessage', 'Проверка не пройдена. Проверьте Account ID, реквизиты, Zoom User ID и scopes.', true); }
      finally { lock(button, false, 'Проверить подключение', 'Проверка…'); }
    });
    byId('zoomMeetingForm').addEventListener('submit', async event => {
      event.preventDefault(); const button = byId('zoomCreateBtn'); lock(button, true, 'Создать Zoom-встречу', 'Создание…');
      let pending = readPendingCreate();
      try {
        const body = Object.fromEntries(new FormData(event.currentTarget)); body.duration = Number(body.duration);
        body.start_time = `${body.start_time}:00`;
        const payload = JSON.stringify(body);
        if (pending && pending.payload !== payload) {
          message('zoomMeetingMessage', 'Предыдущий запрос мог создать встречу. Сначала проверьте Zoom, затем подтвердите сброс ключа ниже.', true);
          showResolvePending();
          return;
        }
        if (!pending) {
          pending = { key: newIdempotencyKey(), payload };
          window.sessionStorage.setItem(pendingCreateStorageKey, JSON.stringify(pending));
        }
        const result = await api('meetings', { method: 'POST', headers: { 'X-Idempotency-Key': pending.key }, body });
        window.sessionStorage.removeItem(pendingCreateStorageKey);
        const link = document.createElement('a'); link.href = result.join_url; link.target = '_blank'; link.rel = 'noopener noreferrer'; link.textContent = 'Открыть ссылку Zoom';
        const output = byId('zoomMeetingMessage'); output.replaceChildren(document.createTextNode('Встреча создана и добавлена в календарь. '), link);
        event.currentTarget.reset(); byId('zoomTimezone').value = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'; await loadMeetings();
      } catch (error) {
        const code = String(error?.message || error?.code || '');
        const unknown = /RESULT_UNKNOWN|NETWORK_ERROR|NETWORK_TIMEOUT|REQUEST_ABORTED/.test(code);
        if (!unknown) window.sessionStorage.removeItem(pendingCreateStorageKey);
        message('zoomMeetingMessage', unknown
          ? 'Результат запроса неизвестен. Ключ сохранён для безопасного повтора; если параметры нужно изменить, сначала проверьте Zoom.'
          : 'Встреча не подтверждена. Проверьте поля и подключение, затем повторите.', true);
        if (unknown) showResolvePending();
      }
      finally { lock(button, false, 'Создать Zoom-встречу', 'Создание…'); }
    });
    function showResolvePending() {
      const form = byId('zoomMeetingForm');
      if (byId('zoomResolvePendingBtn')) return;
      const resolve = document.createElement('button'); resolve.type = 'button'; resolve.id = 'zoomResolvePendingBtn';
      resolve.className = 'btn crm-btn-secondary mt-2'; resolve.textContent = 'Я проверил Zoom — разрешить новый запрос';
      resolve.addEventListener('click', () => {
        if (!window.confirm('Убедитесь, что предыдущая попытка не создала встречу в Zoom. Сбросить ключ безопасного повтора?')) return;
        window.sessionStorage.removeItem(pendingCreateStorageKey); resolve.remove();
        message('zoomMeetingMessage', 'Ключ сброшен. Теперь можно отправить новую встречу.');
      });
      form.append(resolve);
    }
    byId('zoomRefreshBtn').addEventListener('click', async () => { try { await loadMeetings(); } catch (_) { message('zoomStatusMessage', 'Не удалось обновить список встреч.', true); } });
  });
})();
