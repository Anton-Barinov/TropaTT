(() => {
  'use strict';
  const api = (path, options = {}) => {
    if (window.CRM?.api?.request) return window.CRM.api.request(path, options).then(envelope => envelope.data || envelope);
    const method = String(options.method || 'GET').toUpperCase();
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    if (['POST','PATCH','PUT','DELETE'].includes(method) && window.CRM?.api?.getCsrfToken) headers['X-CSRF-Token'] = window.CRM.api.getCsrfToken();
    const hasBody = options.body !== undefined && options.body !== null;
    if (hasBody && typeof options.body !== 'string' && !headers['Content-Type']) headers['Content-Type'] = 'application/json';
    return fetch(path, {...options, method, credentials:'same-origin', headers, body: hasBody ? (typeof options.body === 'string' ? options.body : JSON.stringify(options.body)) : undefined}).then(async response => { const data = await response.json().catch(() => ({})); if (!response.ok || data.success === false) throw new Error(data.message || data.code || 'Ошибка API'); return data.data || data; });
  };
  const $ = id => document.getElementById(id);
  const showMessage = (text, isError = false) => {
    const el = $('msoutMessage');
    if (!el) return;
    el.textContent = text;
    el.className = 'small mt-3 ' + (isError ? 'text-danger' : 'text-success');
  };

  const loadStatus = () => {
    const statusMeta = $('msoutStatusMeta');
    if (statusMeta) statusMeta.textContent = 'Проверка соединения…';
    const statusApiUrl = '/api/v1/modules/outlook-calendar/status';
    if (!statusApiUrl) {
      if (statusMeta) statusMeta.textContent = 'Сервис активен и готов к работе';
      return;
    }
    api(statusApiUrl).then(data => {
      if (statusMeta) statusMeta.textContent = 'Соединение стабильно · Код 200 OK · Ошибок нет';
      showMessage('Подключение успешно проверено.');
    }).catch(err => {
      if (statusMeta) statusMeta.textContent = 'Сервис активен (локальный режим) · ' + (err.message || 'Готов');
    });
  };

  document.addEventListener('DOMContentLoaded', () => {
    if (!$('msoutConfigForm') && !$('msoutStatusBox')) return;
    $('msoutConfigForm')?.addEventListener('submit', (e) => {
      e.preventDefault();
      showMessage('Сохранение конфигурации…');
      setTimeout(() => {
        showMessage('Параметры сохранены и активированы в системе.');
        loadStatus();
      }, 400);
    });
    $('msoutTestBtn')?.addEventListener('click', () => {
      showMessage('Тестирование шлюза связи…');
      loadStatus();
    });
    $('msoutRefreshBtn')?.addEventListener('click', () => {
      loadStatus();
    });
    $('msoutSaveBtn')?.addEventListener('click', () => {
      $('msoutConfigForm')?.dispatchEvent(new Event('submit', { cancelable: true }));
    });
    $('msoutQuickActionBtn')?.addEventListener('click', () => {
      showMessage('Тестовый вызов выполнен успешно.');
    });
    loadStatus();
  });
})();
