(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const apiBase = '/api/v1/_module/crm.google-drive';
  let configured = false;
  let busy = false;
  let initialized = false;

  const request = (path, options = {}) => {
    if (window.CRM?.api?.request) return window.CRM.api.request(path, options).then(envelope => {
      if (envelope?.success === false) throw new Error(envelope.message || envelope.code || 'Запрос не выполнен.');
      return envelope?.data ?? envelope;
    }).catch(error => { throw new Error(error?.envelope?.message || error.message || 'Запрос не выполнен.'); });
    const method = String(options.method || 'GET').toUpperCase();
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    if (['POST', 'PATCH', 'PUT', 'DELETE'].includes(method) && window.CRM?.api?.getCsrfToken) headers['X-CSRF-Token'] = window.CRM.api.getCsrfToken();
    const body = options.body == null ? undefined : (typeof options.body === 'string' ? options.body : JSON.stringify(options.body));
    if (body !== undefined && !headers['Content-Type']) headers['Content-Type'] = 'application/json';
    return fetch(path, { ...options, method, headers, body, credentials: 'same-origin' }).then(async response => {
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.success === false) throw new Error(data.message || data.code || 'Запрос не выполнен.');
      return data.data ?? data;
    });
  };
  const message = (target, text, error = false) => {
    if (!target) return;
    target.textContent = text;
    target.className = `small mt-3 ${error ? 'text-danger' : 'text-success'}`;
  };
  const setBusy = value => {
    busy = value;
    ['gdrvSaveBtn', 'gdrvTestBtn', 'gdrvUploadBtn'].forEach(id => { if ($(id)) $(id).disabled = value || (id !== 'gdrvSaveBtn' && !configured); });
  };
  const loadStatus = async () => {
    try {
      const status = await request(`${apiBase}/status`);
      configured = Boolean(status.configured);
      $('gdrvStatusMeta').textContent = status.connected ? `Подключено: ${status.account_email}` : configured ? 'Настройки сохранены. Подключение ещё не проверено.' : 'Google Drive не настроен для этого рабочего пространства.';
      if (status.root_folder_id && !$('gdrvFolderId').value) $('gdrvFolderId').value = status.root_folder_id;
      $('gdrvTestBtn').disabled = busy || !configured;
      $('gdrvUploadBtn').disabled = busy || !configured;
    } catch (error) {
      configured = false;
      $('gdrvStatusMeta').textContent = 'Не удалось загрузить состояние Google Drive.';
      $('gdrvTestBtn').disabled = true;
      $('gdrvUploadBtn').disabled = true;
      message($('gdrvMessage'), error.message, true);
    }
  };
  const init = () => {
    if (initialized || !$('gdrvConfigForm')) return;
    initialized = true;
    loadStatus();
    $('gdrvConfigForm').addEventListener('submit', async event => {
      event.preventDefault();
      const form = event.currentTarget;
      if (busy || !form.reportValidity()) return;
      setBusy(true);
      message($('gdrvMessage'), 'Сохраняем OAuth-настройки…');
      try {
        await request(`${apiBase}/settings`, { method: 'POST', body: { client_id: $('gdrvClientId').value.trim(), client_secret: $('gdrvClientSecret').value, refresh_token: $('gdrvRefreshToken').value, root_folder_id: $('gdrvFolderId').value.trim() } });
        $('gdrvClientSecret').value = '';
        $('gdrvRefreshToken').value = '';
        message($('gdrvMessage'), 'Настройки сохранены зашифрованно. Проверьте подключение перед передачей файла.');
        await loadStatus();
      } catch (error) { message($('gdrvMessage'), error.message, true); }
      finally { setBusy(false); }
    });
    $('gdrvTestBtn').addEventListener('click', async () => {
      if (busy || !configured) return;
      setBusy(true);
      message($('gdrvMessage'), 'Проверяем Google OAuth и доступ к Drive API…');
      try {
        const result = await request(`${apiBase}/test-connection`, { method: 'POST', body: {} });
        message($('gdrvMessage'), `Подключено: ${result.account_email}${result.display_name ? ` · ${result.display_name}` : ''}`);
        await loadStatus();
      } catch (error) { message($('gdrvMessage'), error.message, true); }
      finally { setBusy(false); }
    });
    $('gdrvUploadForm').addEventListener('submit', async event => {
      event.preventDefault();
      if (busy || !configured || !event.currentTarget.reportValidity()) return;
      setBusy(true);
      message($('gdrvUploadResult'), 'Передаём файл в Google Drive…');
      try {
        const result = await request(`${apiBase}/upload-file`, { method: 'POST', body: { file_public_id: $('gdrvFileId').value.trim() } });
        const url = result.web_view_link;
        if (url) {
          const link = document.createElement('a'); link.href = url; link.target = '_blank'; link.rel = 'noopener noreferrer'; link.textContent = 'Открыть файл в Google Drive';
          const target = $('gdrvUploadResult'); target.replaceChildren(document.createTextNode('Файл передан. '), link);
        } else message($('gdrvUploadResult'), `Файл передан. ID Google Drive: ${result.drive_file_id}`);
      } catch (error) { message($('gdrvUploadResult'), error.message, true); }
      finally { setBusy(false); }
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true }); else init();
  if (!initialized && document.documentElement) {
    const observer = new MutationObserver(() => { init(); if (initialized) observer.disconnect(); });
    observer.observe(document.documentElement, { childList: true, subtree: true });
  }
})();
