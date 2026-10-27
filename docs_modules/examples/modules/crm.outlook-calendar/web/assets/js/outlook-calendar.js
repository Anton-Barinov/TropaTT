(() => {
  'use strict';

  const api = (path, options = {}) => window.CRM.api.request(path, options).then(envelope => envelope.data || envelope);
  const $ = id => document.getElementById(id);
  let connected = false;
  let configured = false;
  let syncing = false;

  const showMessage = (text, isError = false) => {
    $('outlookMessage').textContent = text;
    $('outlookMessage').className = 'small mt-3 ' + (isError ? 'text-danger' : 'text-success');
  };

  const setBusy = (button, busy, label, busyLabel) => {
    button.disabled = busy;
    button.textContent = busy ? busyLabel : label;
  };

  const callbackRedirectUri = () => {
    const configuredUrl = String(window.CRM?.config?.apiBaseUrl || '/api/index.php');
    const apiBase = new URL(configuredUrl, window.location.href);
    const directory = apiBase.pathname.replace(/\/index\.php\/?$/, '').replace(/\/$/, '');
    return `${apiBase.origin}${directory}/v1/modules/outlook-calendar/oauth/callback`;
  };

  const updateControls = () => {
    $('outlookConnectBtn').disabled = !configured || connected;
    $('outlookSyncBtn').disabled = !connected || syncing;
    $('outlookDisconnectBtn').hidden = !connected;
    $('outlookConfigState').textContent = configured ? 'Настройки приложения сохранены.' : 'Сначала сохраните параметры приложения.';
  };

  const loadStatus = async (silent = false) => {
    try {
      const data = await api('/api/v1/modules/outlook-calendar/status');
      configured = data.configured === true;
      connected = data.connected === true;
      if (data.tenant_id) $('outlookTenantId').value = data.tenant_id;
      if (data.client_id) $('outlookClientId').value = data.client_id;
      $('outlookRedirectUri').value = data.redirect_uri || callbackRedirectUri();
      $('outlookAccountEmail').textContent = data.account_email || 'Не подключён';
      $('outlookConnectionHeading').textContent = connected ? 'Microsoft подключён' : (configured ? 'Приложение готово к подключению' : 'Microsoft не подключён');
      $('outlookStatusMeta').textContent = connected
        ? 'Подключён основной календарь Microsoft. События импортируются в активное рабочее пространство.'
        : (configured ? 'Подключите личный аккаунт Microsoft с разрешением Calendars.Read.' : 'Сначала сохраните настройки приложения Microsoft Entra.');
      $('outlookStatusBox').className = 'outlook-status-box alert ' + (connected ? 'alert-success' : 'alert-warning');
      $('outlookSyncTime').textContent = data.last_synced_at ? `Синхронизировано: ${data.last_synced_at}` : 'Синхронизация ещё не запускалась';
      $('outlookWindowNote').textContent = data.sync_window
        ? `Delta-окно календаря: ${data.sync_window.from} — ${data.sync_window.to} UTC. Окно фиксируется при первом импорте, чтобы курсор Microsoft оставался валидным.`
        : 'Первый запуск импортирует события за последние 90 дней и на 365 дней вперёд.';
      if (data.sync_in_progress) $('outlookStatusMeta').textContent += ' Синхронизация уже выполняется.';
      updateControls();
      if (!silent) showMessage('Состояние подключения обновлено.');
    } catch (_) {
      $('outlookConnectionHeading').textContent = 'Не удалось загрузить состояние';
      $('outlookStatusMeta').textContent = 'Проверьте права calendar.view, активное рабочее пространство и применённые миграции.';
      $('outlookStatusBox').className = 'outlook-status-box alert alert-danger';
      configured = false;
      connected = false;
      updateControls();
      if (!silent) showMessage('Не удалось получить состояние Outlook Calendar.', true);
    }
  };

  document.addEventListener('DOMContentLoaded', () => {
    if (!$('outlookConfigForm')) return;
    $('outlookRedirectUri').value = callbackRedirectUri();
    const callback = new URLSearchParams(window.location.search).get('outlook');
    if (callback === 'connected') showMessage('Microsoft аккаунт подключён. Теперь можно синхронизировать календарь.');
    if (callback === 'error') showMessage('Microsoft не завершил подключение. Проверьте настройки приложения и запустите авторизацию ещё раз.', true);
    if (callback) {
      const cleanUrl = new URL(window.location.href);
      cleanUrl.searchParams.delete('outlook');
      window.history.replaceState({}, '', cleanUrl.toString());
    }

    $('outlookConfigForm').addEventListener('submit', async event => {
      event.preventDefault();
      const button = $('outlookSaveBtn');
      setBusy(button, true, 'Сохранить настройки', 'Сохранение…');
      try {
        await api('/api/v1/modules/outlook-calendar/settings', {method:'POST', body:{
          tenant_id: $('outlookTenantId').value.trim(),
          client_id: $('outlookClientId').value.trim(),
          client_secret: $('outlookClientSecret').value,
          redirect_uri: $('outlookRedirectUri').value.trim()
        }});
        $('outlookClientSecret').value = '';
        configured = true;
        showMessage('Настройки приложения сохранены. Клиентский секрет зашифрован на сервере.');
        await loadStatus(true);
      } catch (_) {
        showMessage('Не удалось сохранить настройки. Проверьте значения, APP_KEY, OpenSSL и активное рабочее пространство.', true);
      } finally {
        setBusy(button, false, 'Сохранить настройки', 'Сохранение…');
      }
    });

    $('outlookConnectBtn').addEventListener('click', async () => {
      const button = $('outlookConnectBtn');
      setBusy(button, true, 'Подключить Microsoft', 'Подготовка…');
      try {
        const data = await api('/api/v1/modules/outlook-calendar/oauth/start', {method:'POST', body:{}});
        const target = new URL(String(data.authorization_url || ''));
        if (target.protocol !== 'https:' || target.hostname !== 'login.microsoftonline.com') throw new Error('Unsafe provider URL');
        window.location.assign(target.toString());
      } catch (_) {
        showMessage('Не удалось начать авторизацию. Проверьте настройки Entra и право calendar.edit.', true);
        setBusy(button, false, 'Подключить Microsoft', 'Подготовка…');
      }
    });

    $('outlookSyncBtn').addEventListener('click', async () => {
      if (syncing) return;
      syncing = true;
      const button = $('outlookSyncBtn');
      setBusy(button, true, 'Синхронизировать сейчас', 'Синхронизация…');
      const totals = {imported:0,updated:0,deleted:0,skipped:0,pages:0};
      try {
        let data;
        for (let round = 0; round < 4; round++) {
          data = await api('/api/v1/modules/outlook-calendar/sync', {method:'POST', body:{}});
          Object.keys(totals).forEach(key => { totals[key] += Number(data[key] || 0); });
          if (data.cursor_reset) {
            showMessage('Microsoft сбросил курсор синхронизации. Запустите синхронизацию ещё раз для полной загрузки.', true);
            break;
          }
          if (data.completed !== false || data.continuation_available !== true) break;
        }
        const suffix = data?.continuation_available ? ' Ещё страницы загружены не все; нажмите синхронизацию повторно.' : '';
        $('outlookSyncResult').textContent = `Страниц: ${totals.pages}; создано: ${totals.imported}; обновлено: ${totals.updated}; удалено из импорта: ${totals.deleted}; пропущено: ${totals.skipped}.${suffix}`;
        if (!data?.cursor_reset) showMessage(data?.continuation_available ? 'Импорт продолжится при следующем запуске.' : 'Синхронизация завершена.');
        await loadStatus(true);
      } catch (_) {
        showMessage('Синхронизация не завершена. Текущая страница delta будет повторена при следующем запуске.', true);
      } finally {
        syncing = false;
        setBusy(button, false, 'Синхронизировать сейчас', 'Синхронизация…');
        updateControls();
      }
    });

    $('outlookDisconnectBtn').addEventListener('click', async () => {
      if (!window.confirm('Отключить Microsoft? Уже импортированные события останутся в CRM.')) return;
      const button = $('outlookDisconnectBtn');
      button.disabled = true;
      try {
        await api('/api/v1/modules/outlook-calendar/connection', {method:'DELETE'});
        showMessage('Локальные токены очищены; импортированные события сохранены.');
        await loadStatus(true);
      } catch (_) {
        showMessage('Не удалось отключить Microsoft аккаунт.', true);
      } finally {
        button.disabled = false;
      }
    });

    $('outlookRefreshBtn').addEventListener('click', () => loadStatus());
    $('outlookCopyRedirectBtn').addEventListener('click', async () => {
      const input = $('outlookRedirectUri');
      try {
        await navigator.clipboard.writeText(input.value);
      } catch (_) {
        input.focus();
        input.select();
        document.execCommand('copy');
      }
      showMessage('Redirect URI скопирован. Вставьте его в регистрацию приложения Entra.');
    });
    loadStatus(true);
  });
})();
