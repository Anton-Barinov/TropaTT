(() => {
  'use strict';
  const base = '_module/crm.dropbox';
  const api = (path, options = {}) => window.CRM?.api?.request
    ? window.CRM.api.request(`${base}/${path}`, options).then(envelope => envelope.data || envelope)
    : Promise.reject(new Error('CRM API client is unavailable.'));
  const byId = id => document.getElementById(id);
  const setMessage = (id, text, error = false) => {
    const node = byId(id);
    if (!node) return;
    node.textContent = text;
    node.className = `small mt-3 ${error ? 'text-danger' : 'text-muted'}`;
  };
  const lock = (button, active, idle, busy) => {
    button.disabled = active;
    button.textContent = active ? busy : idle;
  };

  document.addEventListener('DOMContentLoaded', async () => {
    const status = byId('dpbxStatusMeta');
    if (!status) return;
    const refreshStatus = async () => {
      const state = await api('status');
      status.textContent = state.configured ? 'Реквизиты сохранены; выполните проверку подключения.' : 'Dropbox не подключён.';
      byId('dpbxAccount').textContent = state.account_email ? `Аккаунт: ${state.account_email}` : 'Аккаунт ещё не подтверждён.';
      if (state.base_folder_path) byId('dpbxBasePath').value = state.base_folder_path;
    };
    try { await refreshStatus(); } catch (_) { status.textContent = 'Не удалось загрузить состояние. Обновите страницу или проверьте права.'; }

    byId('dpbxConfigForm')?.addEventListener('submit', async event => {
      event.preventDefault();
      const button = byId('dpbxSaveBtn');
      lock(button, true, 'Сохранить реквизиты', 'Сохранение…');
      try {
        await api('settings', { method: 'POST', body: Object.fromEntries(new FormData(event.currentTarget)) });
        event.currentTarget.querySelectorAll('input[type="password"]').forEach(input => { input.value = ''; });
        setMessage('dpbxMessage', 'Реквизиты зашифрованы и сохранены. Теперь нажмите «Проверить подключение».');
        await refreshStatus();
      } catch (_) { setMessage('dpbxMessage', 'Не удалось сохранить настройки. Проверьте обязательные поля и APP_KEY.', true); }
      finally { lock(button, false, 'Сохранить реквизиты', 'Сохранение…'); }
    });

    byId('dpbxTestBtn')?.addEventListener('click', async event => {
      const button = event.currentTarget;
      lock(button, true, 'Проверить подключение', 'Проверка…');
      try {
        const result = await api('test-connection', { method: 'POST', body: {} });
        setMessage('dpbxMessage', `Подключение работает${result.account_email ? `: ${result.account_email}` : ''}.`);
        await refreshStatus();
        status.textContent = 'Dropbox подключён';
      } catch (_) { setMessage('dpbxMessage', 'Проверка не пройдена. Проверьте ключ, секрет, refresh token и доступ к HTTPS.', true); }
      finally { lock(button, false, 'Проверить подключение', 'Проверка…'); }
    });

    byId('dpbxSyncForm')?.addEventListener('submit', async event => {
      event.preventDefault();
      const button = byId('dpbxSyncBtn');
      lock(button, true, 'Передать файл', 'Передача…');
      try {
        const result = await api('sync-file', { method: 'POST', body: { file_public_id: byId('dpbxFileId').value.trim() } });
        setMessage('dpbxSyncResult', `Файл передан: ${result.path || result.remote_id} (${result.size_bytes} байт).`);
      } catch (_) { setMessage('dpbxSyncResult', 'Передача не выполнена. Проверьте доступ к файлу, рабочее пространство и подключение Dropbox.', true); }
      finally { lock(button, false, 'Передать файл', 'Передача…'); }
    });
  });
})();
