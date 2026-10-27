(() => {
  'use strict';
  const base = '_module/crm.yandex-disk';
  const api = (path, options = {}) => window.CRM?.api?.request
    ? window.CRM.api.request(`${base}/${path}`, options).then(envelope => envelope.data || envelope)
    : Promise.reject(new Error('CRM API client is unavailable.'));
  const byId = id => document.getElementById(id);
  const message = (id, text, error = false) => {
    const node = byId(id);
    if (!node) return;
    node.textContent = text;
    node.className = `small mt-3 ${error ? 'text-danger' : 'text-muted'}`;
  };
  const lock = (button, active, label, busy) => { button.disabled = active; button.textContent = active ? busy : label; };

  document.addEventListener('DOMContentLoaded', async () => {
    const status = byId('yadiskStatusMeta');
    if (!status) return;
    const refresh = async () => {
      const data = await api('status');
      status.textContent = data.connected ? 'Яндекс Диск подключён.' : (data.configured ? 'Токен сохранён; требуется проверить доступ.' : 'Подключение не настроено.');
      const used = Number.isFinite(Number(data.used_space)) ? ` · Занято: ${Math.round(Number(data.used_space) / 1048576)} МБ` : '';
      byId('yadiskAccount').textContent = data.account_uid ? `ID аккаунта: ${data.account_uid}${used}` : 'Аккаунт не подтверждён.';
      if (data.root_folder) byId('yadiskRoot').value = data.root_folder;
    };
    try { await refresh(); } catch (_) { status.textContent = 'Не удалось загрузить состояние подключения. Проверьте права и обновите страницу.'; }

    byId('yadiskConfigForm')?.addEventListener('submit', async event => {
      event.preventDefault(); const button = byId('yadiskSaveBtn'); lock(button, true, 'Сохранить настройки', 'Сохранение…');
      try {
        await api('settings', { method: 'POST', body: Object.fromEntries(new FormData(event.currentTarget)) });
        byId('yadiskToken').value = '';
        message('yadiskMessage', 'Токен сохранён в зашифрованном виде. Теперь проверьте подключение.'); await refresh();
      } catch (_) { message('yadiskMessage', 'Не удалось сохранить настройки. Проверьте токен, путь и APP_KEY.', true); }
      finally { lock(button, false, 'Сохранить настройки', 'Сохранение…'); }
    });

    byId('yadiskTestBtn')?.addEventListener('click', async event => {
      const button = event.currentTarget; lock(button, true, 'Проверить подключение', 'Проверка…');
      try { await api('test-connection', { method: 'POST', body: {} }); message('yadiskMessage', 'Подключение Яндекс Диска работает.'); await refresh(); }
      catch (_) { message('yadiskMessage', 'Проверка не пройдена. Проверьте OAuth-токен и доступ сервера к HTTPS.', true); }
      finally { lock(button, false, 'Проверить подключение', 'Проверка…'); }
    });

    byId('yadiskUploadForm')?.addEventListener('submit', async event => {
      event.preventDefault(); const button = byId('yadiskUploadBtn'); lock(button, true, 'Передать в Яндекс Диск', 'Передача…');
      try { const data = await api('upload-file', { method: 'POST', body: { file_public_id: byId('yadiskFileId').value.trim() } }); message('yadiskUploadResult', `Файл передан: ${data.path} (${data.size_bytes} байт).`); }
      catch (_) { message('yadiskUploadResult', 'Передача не выполнена. Проверьте доступ к файлу и подключение.', true); }
      finally { lock(button, false, 'Передать в Яндекс Диск', 'Передача…'); }
    });
  });
})();
