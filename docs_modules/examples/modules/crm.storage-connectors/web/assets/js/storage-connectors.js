(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const api = (path, options = {}) => {
    if (window.CRM?.api?.request) return window.CRM.api.request(path, options).then(envelope => {
      if (envelope?.success === false) throw new Error(envelope.message || envelope.code || 'Запрос не выполнен.');
      return envelope?.data ?? envelope;
    }).catch(error => {
      throw new Error(error?.envelope?.message || error.message || 'Запрос не выполнен.');
    });
    const method = String(options.method || 'GET').toUpperCase();
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    if (['POST', 'PATCH', 'PUT', 'DELETE'].includes(method) && window.CRM?.api?.getCsrfToken) headers['X-CSRF-Token'] = window.CRM.api.getCsrfToken();
    const hasBody = options.body !== undefined && options.body !== null;
    if (hasBody && typeof options.body !== 'string' && !headers['Content-Type']) headers['Content-Type'] = 'application/json';
    return fetch(path, { ...options, method, credentials: 'same-origin', headers, body: hasBody ? (typeof options.body === 'string' ? options.body : JSON.stringify(options.body)) : undefined }).then(async response => {
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.success === false) throw new Error(data.message || data.code || 'Запрос не выполнен.');
      return data.data ?? data;
    });
  };
  const apiBase = '_module/crm.storage-connectors';
  let configured = false;
  let busy = false;

  const message = (text, isError = false) => {
    const target = $('stconMessage');
    if (!target) return;
    target.textContent = text;
    target.className = `small mt-3 ${isError ? 'text-danger' : 'text-success'}`;
  };
  const setBusy = value => {
    busy = value;
    ['stconSaveBtn', 'stconTestBtn', 'stconMigrateBtn'].forEach(id => { if ($(id)) $(id).disabled = value || (id !== 'stconSaveBtn' && !configured); });
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
      $('stconStatus').textContent = configured ? `Подключено: ${config.provider_type === 's3' ? 'S3-совместимое хранилище' : 'Nextcloud / WebDAV'} · ${config.endpoint_url}` : 'Внешнее хранилище не настроено. Файлы остаются в CRM.';
      if (config) {
        $('stconProvider').value = config.provider_type;
        $('stconEndpoint').value = config.endpoint_url || '';
        $('stconBucket').value = config.bucket_or_path || '';
        $('stconRegion').value = config.region || 'us-east-1';
      }
      updateProviderFields();
      $('stconTestBtn').disabled = busy || !configured;
      $('stconMigrateBtn').disabled = busy || !configured;
    } catch (error) {
      configured = false;
      $('stconStatus').textContent = 'Не удалось загрузить состояние хранилища.';
      $('stconPreflight').textContent = 'Проверьте доступность API и права settings.view.';
      $('stconTestBtn').disabled = true;
      $('stconMigrateBtn').disabled = true;
      message(error.message || 'Ошибка загрузки.', true);
    }
  };

  document.addEventListener('DOMContentLoaded', () => {
    const form = $('stconConfigForm');
    if (!form) return;
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
        const result = await api(`${apiBase}/migrate-batch`, { method: 'POST', body: {} });
        $('stconMigrationResult').textContent = `Обработано: ${result.processed_count}; перенесено: ${result.synced_count}; ошибок: ${result.failed_count}; ${result.has_more ? 'есть следующая партия.' : 'неперенесённых файлов нет.'}`;
      } catch (error) {
        $('stconMigrationResult').textContent = error.message || 'Не удалось перенести файлы.';
      } finally {
        setBusy(false);
      }
    });
  });
})();
