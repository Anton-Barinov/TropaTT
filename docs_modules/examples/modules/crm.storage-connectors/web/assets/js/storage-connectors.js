(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const api = (path, options = {}) => {
    const requestOptions = { timeoutMs: 12000, maxRetries: 0, ...options };
    if (window.CRM?.api?.request) {
      const method = String(requestOptions.method || 'GET').toUpperCase();
      const request = window.CRM.api.request(path, requestOptions).then(envelope => {
        if (envelope?.success === false) throw new Error(envelope.message || envelope.code || 'Запрос не выполнен.');
        return envelope?.data ?? envelope;
      }).catch(error => {
        throw new Error(error?.envelope?.message || error.message || 'Запрос не выполнен.');
      });
      return method === 'GET' || method === 'HEAD'
        ? settleReadRequest(request, requestOptions.timeoutMs)
        : request;
    }
    const method = String(requestOptions.method || 'GET').toUpperCase();
    const headers = { Accept: 'application/json', ...(requestOptions.headers || {}) };
    if (['POST', 'PATCH', 'PUT', 'DELETE'].includes(method) && window.CRM?.api?.getCsrfToken) headers['X-CSRF-Token'] = window.CRM.api.getCsrfToken();
    const hasBody = requestOptions.body !== undefined && requestOptions.body !== null;
    if (hasBody && typeof requestOptions.body !== 'string' && !headers['Content-Type']) headers['Content-Type'] = 'application/json';
    const controller = typeof AbortController === 'function' ? new AbortController() : null;
    const timeout = controller ? window.setTimeout(() => controller.abort(), requestOptions.timeoutMs) : null;
    const fetchOptions = { ...requestOptions };
    delete fetchOptions.timeoutMs;
    delete fetchOptions.maxRetries;
    return fetch(path, { ...fetchOptions, method, credentials: 'same-origin', headers, signal: controller?.signal, body: hasBody ? (typeof requestOptions.body === 'string' ? requestOptions.body : JSON.stringify(requestOptions.body)) : undefined }).then(async response => {
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.success === false) throw new Error(data.message || data.code || 'Запрос не выполнен.');
      return data.data ?? data;
    }).catch(error => {
      if (error?.name === 'AbortError') throw new Error('Сервер не ответил за 12 секунд. Проверьте соединение и попробуйте обновить состояние.');
      throw error;
    }).finally(() => { if (timeout !== null) window.clearTimeout(timeout); });
  };
  const settleReadRequest = (promise, timeoutMs) => {
    let timer = null;
    const timeout = new Promise((_, reject) => {
      timer = window.setTimeout(() => {
        reject(new Error(`Сервер не ответил за ${Math.round(timeoutMs / 1000)} секунд. Повторите запрос.`));
      }, timeoutMs);
    });
    return Promise.race([promise, timeout]).finally(() => {
      if (timer !== null) window.clearTimeout(timer);
    });
  };
  const apiBase = '_module/crm.storage-connectors';
  let configured = false;
  let busy = false;
  let initialized = false;
  let canMigrate = false;
  let migrationScope = '';
  let migrationCursor = 0;
  const cursorStorageKey = scope => `crm.storage-connectors.cursor:${String(scope || '')}`;

  const message = (text, isError = false) => {
    const target = $('stconMessage');
    if (!target) return;
    target.textContent = text;
    target.className = `small mt-3 ${isError ? 'text-danger' : 'text-success'}`;
  };
  const setBusy = value => {
    busy = value;
    ['stconSaveBtn', 'stconTestBtn', 'stconMigrateBtn'].forEach(id => {
      if (!$(id)) return;
      $(id).disabled = value || (id !== 'stconSaveBtn' && !configured) || (id === 'stconMigrateBtn' && !canMigrate);
    });
    document.querySelectorAll('[data-migration-retry]').forEach(button => { button.disabled = value; });
  };
  const errorLabels = {
    FILE_NOT_AVAILABLE: 'Файл удалён, помещён в карантин или недоступен.',
    FILE_DOWNLOAD_DENIED: 'CRM запретила чтение этого файла.',
    LOCAL_FILE_UNAVAILABLE: 'Не удалось безопасно прочитать исходный файл.',
    REMOTE_STORAGE_FAILED: 'Внешнее хранилище отклонило запись.',
  };
  const loadMigrationFailures = async () => {
    const target = $('stconMigrationFailures');
    if (!target) return;
    try {
      const result = await api(`${apiBase}/migration-failures`);
      const items = Array.isArray(result.items) ? result.items : [];
      target.replaceChildren();
      const heading = document.createElement('h3');
      heading.className = 'h6';
      const countSuffix = result.visible_failure_count_complete === false ? ' (показана нижняя граница)' : '';
      heading.textContent = `Доступные файлы с ошибками: ${Number(result.failed_remaining_count || 0)}${countSuffix}`;
      target.append(heading);
      if (items.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'small text-muted mb-0';
        empty.textContent = 'Ошибок переноса нет.';
        target.append(empty);
        return;
      }
      const list = document.createElement('ul');
      list.className = 'list-unstyled mb-0';
      items.forEach(item => {
        const row = document.createElement('li');
        row.className = 'd-flex flex-wrap align-items-center justify-content-between gap-2 border-top py-2';
        const details = document.createElement('span');
        details.className = 'small';
        const filename = document.createElement('strong');
        filename.textContent = String(item.filename || item.file_public_id || 'Файл');
        const reason = document.createElement('span');
        reason.className = 'd-block text-muted';
        reason.textContent = `${errorLabels[item.error_code] || 'Не удалось перенести файл.'} Попыток: ${Number(item.attempts || 0)}.`;
        details.append(filename, reason);
        const retry = document.createElement('button');
        retry.type = 'button';
        retry.className = 'btn crm-btn-secondary btn-sm';
        retry.textContent = 'Повторить';
        retry.dataset.migrationRetry = String(item.file_public_id || '');
        retry.disabled = busy;
        row.append(details, retry);
        list.append(row);
      });
      target.append(list);
    } catch (error) {
      target.textContent = error.message || 'Не удалось загрузить список ошибок переноса.';
      target.className = 'small mt-3 text-danger';
    }
  };
  const updateProviderFields = () => {
    const isS3 = $('stconProvider')?.value === 's3';
    $('stconS3Fields').hidden = !isS3;
    $('stconWebdavFields').hidden = isS3;
    $('stconBucketLabel').textContent = isS3 ? 'Имя S3-бакета' : 'Путь в хранилище';
    ['stconAccessKey', 'stconSecretKey'].forEach(id => { $(id).required = isS3; $(id).disabled = !isS3; });
    ['stconUsername', 'stconPassword'].forEach(id => { $(id).required = !isS3; $(id).disabled = isS3; });
  };
  const loadState = async () => {
    $('stconStatus').textContent = 'Загружаем состояние…';
    try {
      const [preflight, configResult] = await Promise.all([api(`${apiBase}/preflight`), api(`${apiBase}/config`)]);
      const checks = preflight.checks || {};
      $('stconPreflight').textContent = Object.entries(checks).map(([name, item]) => `${name}: ${item.status === 'pass' ? 'доступно' : item.status === 'warn' ? 'предупреждение' : 'недоступно'}`).join(' · ') || (preflight.ok ? 'Окружение готово.' : 'Не удалось подтвердить готовность окружения.');
      const config = configResult.config || null;
      configured = Boolean(config);
      canMigrate = configResult.can_migrate === true;
      migrationScope = String(configResult.cursor_scope || '');
      migrationCursor = 0;
      if (migrationScope) {
        try {
          const savedCursor = Number(sessionStorage.getItem(cursorStorageKey(migrationScope)) || 0);
          if (Number.isSafeInteger(savedCursor) && savedCursor > 0) migrationCursor = savedCursor;
        } catch (_) { /* Browsers may disable sessionStorage; the current page can still migrate. */ }
      }
      $('stconStatus').textContent = configured ? `Настроено: ${config.provider_type === 's3' ? 'S3-совместимое хранилище' : 'Nextcloud / WebDAV'} · ${config.endpoint_url}. ${canMigrate ? 'Нажмите «Проверить подключение», чтобы подтвердить доступ.' : 'Для переноса нужны права settings.edit и files.view.'}` : 'Внешнее хранилище не настроено. Файлы остаются в CRM.';
      if (config) {
        $('stconProvider').value = config.provider_type;
        $('stconEndpoint').value = config.endpoint_url || '';
        $('stconBucket').value = config.bucket_or_path || '';
        $('stconRegion').value = config.region || 'us-east-1';
      }
      updateProviderFields();
      $('stconTestBtn').disabled = busy || !configured;
      $('stconMigrateBtn').disabled = busy || !configured || !canMigrate;
      await loadMigrationFailures();
    } catch (error) {
      configured = false;
      $('stconStatus').textContent = 'Не удалось загрузить состояние хранилища.';
      $('stconPreflight').textContent = 'Проверьте доступность API и права settings.view.';
      $('stconTestBtn').disabled = true;
      $('stconMigrateBtn').disabled = true;
      message(error.message || 'Ошибка загрузки.', true);
    }
  };

  const init = () => {
    if (initialized) return;
    const form = $('stconConfigForm');
    if (!form) return;
    initialized = true;
    $('stconProvider').addEventListener('change', updateProviderFields);
    updateProviderFields();
    loadState();

    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (busy || !form.reportValidity()) return;
      const isS3 = $('stconProvider').value === 's3';
      const body = {
        provider_type: $('stconProvider').value,
        endpoint_url: $('stconEndpoint').value.trim(),
        bucket_or_path: $('stconBucket').value.trim(),
        region: $('stconRegion').value.trim() || 'us-east-1',
        credentials: isS3
          ? { access_key: $('stconAccessKey').value, secret_key: $('stconSecretKey').value }
          : { username: $('stconUsername').value, password: $('stconPassword').value },
      };
      setBusy(true);
      message('Сохраняем настройки…');
      try {
        await api(`${apiBase}/config`, { method: 'POST', body });
        form.querySelectorAll('input[type="password"]').forEach(input => { input.value = ''; });
        message('Настройки зашифрованы и сохранены. Проверьте соединение перед переносом файлов.');
        await loadState();
      } catch (error) {
        message(error.message || 'Не удалось сохранить настройки.', true);
      } finally {
        setBusy(false);
      }
    });

    $('stconTestBtn').addEventListener('click', async () => {
      if (busy || !configured) return;
      setBusy(true);
      message('Проверяем соединение с сохранённым хранилищем…');
      try {
        const result = await api(`${apiBase}/test-connection`, { method: 'POST', body: {} });
        message(result.message || 'Соединение успешно проверено.');
      } catch (error) {
        message(error.message || 'Проверка соединения завершилась ошибкой.', true);
      } finally {
        setBusy(false);
      }
    });

    $('stconMigrateBtn').addEventListener('click', async () => {
      if (busy || !configured || !window.confirm('Перенести следующую партию файлов в удалённое хранилище? Исходники останутся в CRM.')) return;
      setBusy(true);
      $('stconMigrationResult').textContent = 'Переносим не более 15 файлов…';
      try {
        const result = await api(`${apiBase}/migrate-batch`, { method: 'POST', body: { after_file_id: migrationCursor } });
        const returnedScope = String(result.cursor_scope || migrationScope);
        if (returnedScope !== migrationScope) { migrationScope = returnedScope; migrationCursor = 0; }
        if (result.has_more && Number.isSafeInteger(Number(result.next_cursor)) && Number(result.next_cursor) > 0) {
          migrationCursor = Number(result.next_cursor);
          try { if (migrationScope) sessionStorage.setItem(cursorStorageKey(migrationScope), String(migrationCursor)); } catch (_) { /* API still returns the cursor for manual continuation. */ }
        } else {
          migrationCursor = 0;
          try { if (migrationScope) sessionStorage.removeItem(cursorStorageKey(migrationScope)); } catch (_) { /* Ignore unavailable browser storage. */ }
        }
        const failureCountSuffix = result.visible_failure_count_complete === false ? ' (нижняя граница)' : '';
        $('stconMigrationResult').textContent = `Обработано: ${result.processed_count}; пропущено без доступа: ${result.skipped_count || 0}; перенесено: ${result.synced_count}; ошибок в этой партии: ${result.failed_count}; доступных ошибок переноса: ${result.failed_remaining_count}${failureCountSuffix}; ${result.has_more ? 'есть следующая партия; нажмите кнопку ещё раз.' : 'обычная очередь обработана.'}`;
        await loadMigrationFailures();
      } catch (error) {
        $('stconMigrationResult').textContent = error.message || 'Не удалось перенести файлы.';
      } finally {
        setBusy(false);
      }
    });

    $('stconMigrationFailures').addEventListener('click', async event => {
      const button = event.target.closest('[data-migration-retry]');
      if (!button || busy || !button.dataset.migrationRetry) return;
      setBusy(true);
      $('stconMigrationResult').textContent = 'Повторяем перенос выбранного файла…';
      try {
        const result = await api(`${apiBase}/migration-retry`, { method: 'POST', body: { file_public_id: button.dataset.migrationRetry } });
        $('stconMigrationResult').textContent = result.synced_count > 0 ? 'Файл перенесён.' : 'Повтор завершён; проверьте состояние и причину ошибки.';
        await loadMigrationFailures();
      } catch (error) {
        $('stconMigrationResult').textContent = error.message || 'Не удалось повторить перенос файла.';
      } finally {
        setBusy(false);
      }
    });
  };

  // Module assets may be loaded by different page shells. A deferred script
  // normally runs before DOMContentLoaded, but the readyState guard also covers
  // shells that inject the module script after that event has already fired.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }

  // Some CRM shells inject module page markup after the asset has loaded.
  // Keep initialization retryable until the form exists, then disconnect so
  // this observer cannot become a permanent page-wide listener.
  if (!initialized && document.documentElement) {
    const observer = new MutationObserver(() => {
      init();
      if (initialized) observer.disconnect();
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
  }
})();
