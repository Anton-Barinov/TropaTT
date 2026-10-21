(() => {
  'use strict';

  // API helper using window.CRM.api.request
  const api = (path, options = {}) => {
    let cleanPath = path.startsWith('/') ? path.slice(1) : path;
    const query = Object.assign({}, options.query || {});
    if (cleanPath.includes('?')) {
      const parts = cleanPath.split('?');
      cleanPath = parts[0];
      const params = new URLSearchParams(parts[1]);
      params.forEach((v, k) => {
        if (query[k] === undefined) {
          query[k] = v;
        }
      });
    }
    const finalOptions = Object.assign({}, options, { query });

    if (window.CRM?.api?.request) {
      return window.CRM.api.request(cleanPath, finalOptions).then(envelope => {
        if (envelope && typeof envelope === 'object') {
          if (envelope.success === false) {
            throw new Error(envelope.message || envelope.code || 'Ошибка запроса к API');
          }
          return envelope.data !== undefined ? envelope.data : envelope;
        }
        return envelope;
      });
    }

    const method = String(options.method || 'GET').toUpperCase();
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    if (['POST', 'PATCH', 'PUT', 'DELETE'].includes(method) && window.CRM?.api?.getCsrfToken) {
      headers['X-CSRF-Token'] = window.CRM.api.getCsrfToken();
    }
    const hasBody = options.body !== undefined && options.body !== null;
    if (hasBody && typeof options.body !== 'string' && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }

    const searchParams = new URLSearchParams();
    searchParams.set('route', cleanPath);
    Object.keys(query).forEach(k => {
      if (query[k] !== undefined && query[k] !== null) {
        searchParams.set(k, String(query[k]));
      }
    });
    const url = '/api/index.php?' + searchParams.toString();

    return fetch(url, {
      ...options,
      method,
      credentials: 'same-origin',
      headers,
      body: hasBody ? (typeof options.body === 'string' ? options.body : JSON.stringify(options.body)) : undefined,
    }).then(async response => {
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.success === false) {
        throw new Error(data.message || data.code || 'Ошибка запроса к API');
      }
      return data.data !== undefined ? data.data : data;
    });
  };

  const $ = id => document.getElementById(id);
  const esc = str => window.CRM?.text?.escapeHtml ? window.CRM.text.escapeHtml(String(str ?? '')) : String(str ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  let currentBotUsername = '';
  let currentOrgId = 1;

  const showAlert = (message, type = 'success') => {
    const el = $('tgAlertContainer');
    if (!el) return;
    el.style.display = 'block';
    el.innerHTML = `
      <div class="alert alert-${type} alert-dismissible fade show d-flex align-items-center mb-0" role="alert">
        <i class="fa-solid fa-${type === 'success' ? 'circle-check' : (type === 'danger' ? 'circle-exclamation' : 'circle-info')} me-2" aria-hidden="true"></i>
        <div class="flex-grow-1">${message}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
      </div>
    `;
    setTimeout(() => {
      if (el && el.firstElementChild) {
        el.style.display = 'none';
        el.innerHTML = '';
      }
    }, 6000);
  };

  // Compute standard webhook URL for current host
  const getComputedWebhookUrl = (orgId = 1) => {
    const origin = window.location.origin;
    return `${origin}/api/index.php?route=_module/crm.telegram-bot/webhook/${orgId}`;
  };

  // Load Status and config
  const loadStatus = async () => {
    try {
      const data = await api('_module/crm.telegram-bot/status');

      // 1. Status Banner
      const banner = $('tgStatusBanner');
      if (banner) {
        const isActive = !!data.is_active;
        const username = data.bot_username ? (data.bot_username.startsWith('@') ? data.bot_username : '@' + data.bot_username) : '';
        currentBotUsername = username;
        currentOrgId = data.organization_id || 1;

        let badgeHtml = isActive
          ? `<span class="badge bg-success fs-6"><i class="fa-solid fa-circle-check me-1"></i> Подключён · Активен</span>`
          : `<span class="badge bg-warning text-dark fs-6"><i class="fa-solid fa-triangle-exclamation me-1"></i> Установлен · не подключён</span>`;

        let detailsHtml = isActive
          ? (username ? `Бот <a href="https://t.me/${encodeURIComponent(username.replace('@', ''))}" target="_blank" rel="noopener noreferrer" class="fw-bold text-decoration-none">${esc(username)}</a> подключён и готов принимать сообщения.` : 'Бот успешно настроен и принимает обращения.')
          : 'Бот не подключён к Telegram. Укажите Bot Token и сохраните настройки для активации.';

        banner.innerHTML = `
          <div class="alert ${isActive ? 'alert-success' : 'alert-warning'} d-flex align-items-center justify-content-between p-3 flex-wrap gap-2" role="status">
            <div class="d-flex align-items-center">
              <div class="fs-2 me-3 text-${isActive ? 'success' : 'warning'}"><i class="fa-brands fa-telegram"></i></div>
              <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <strong class="fs-6">${isActive ? 'Telegram бот активен' : 'Требуется настройка бота'}</strong>
                  ${badgeHtml}
                </div>
                <div class="small mt-1">${detailsHtml}</div>
              </div>
            </div>
          </div>
        `;
      }

      // 2. KPI Cards
      if ($('kpiBotName')) $('kpiBotName').textContent = data.bot_username || (data.configured ? 'Подключён' : 'Не настроен');
      if ($('kpiMode')) $('kpiMode').textContent = data.webhook_url ? 'Webhook' : 'Polling';
      if ($('kpiBindings')) $('kpiBindings').textContent = String(data.verified_bindings ?? 0);
      if ($('kpiUpdates')) $('kpiUpdates').textContent = String(data.total_updates ?? 0);

      // 3. Form fields
      if ($('tgBotToken') && !$('tgBotToken').value) {
        if (data.bot_token_masked) {
          $('tgBotToken').value = data.bot_token_masked;
        }
      }
      if ($('tgBotUsername') && data.bot_username) {
        $('tgBotUsername').value = data.bot_username;
      }
      if ($('tgSecretToken') && data.webhook_secret_set) {
        // keep existing if populated
      }
      if ($('tgWebhookUrl')) {
        $('tgWebhookUrl').value = data.webhook_url || getComputedWebhookUrl(currentOrgId);
      }
      if ($('tgAutoIntake')) {
        $('tgAutoIntake').checked = data.auto_create_intake !== false;
      }
      if ($('tgWelcomeMessage') && data.welcome_message) {
        $('tgWelcomeMessage').value = data.welcome_message;
      }

      // 4. Env Import Button
      const envBtn = $('tgImportEnvBtn');
      if (envBtn) {
        envBtn.style.display = data.has_env_token ? 'inline-block' : 'none';
      }

      // 5. Update direct bot link if requested
      const openBotLink = $('tgOpenBotLink');
      if (openBotLink && currentBotUsername) {
        openBotLink.href = `https://t.me/${encodeURIComponent(currentBotUsername.replace('@', ''))}`;
      }
    } catch (err) {
      console.error('[TelegramBot] Failed to load status:', err);
      const banner = $('tgStatusBanner');
      if (banner) {
        banner.innerHTML = `
          <div class="alert alert-danger p-3" role="alert">
            <strong>Ошибка загрузки статуса:</strong> ${esc(err.message || 'Сбой API')}
          </div>
        `;
      }
    }
  };

  // Load staff bindings
  const loadBindings = async () => {
    const tbody = $('tgBindingsBody');
    if (!tbody) return;

    try {
      const data = await api('_module/crm.telegram-bot/bindings');
      const items = (data && data.items) || [];

      if (items.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="6" class="text-muted text-center py-4">
              <i class="fa-solid fa-users-slash me-1"></i> Пока нет привязанных сотрудников.
              <div class="small mt-1">Нажмите «Получить мой код привязки» чтобы привязать свой аккаунт.</div>
            </td>
          </tr>
        `;
        return;
      }

      let html = '';
      items.forEach(item => {
        const isVerified = !!item.is_verified;
        const statusBadge = isVerified
          ? '<span class="badge bg-success">Подтверждён</span>'
          : '<span class="badge bg-secondary">Ожидает код</span>';

        html += `
          <tr>
            <td><strong>${esc(item.user_public_id)}</strong></td>
            <td><code>${esc(item.telegram_chat_id || '—')}</code></td>
            <td>${item.telegram_username ? `<a href="https://t.me/${esc(item.telegram_username.replace('@',''))}" target="_blank" rel="noopener noreferrer">${esc(item.telegram_username)}</a>` : '<span class="text-muted">—</span>'}</td>
            <td>${statusBadge}</td>
            <td class="small text-muted">${esc(item.created_at || '—')}</td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-danger tg-del-bind-btn" data-id="${esc(item.public_id)}" title="Отвязать аккаунт">
                <i class="fa-solid fa-trash" aria-hidden="true"></i>
              </button>
            </td>
          </tr>
        `;
      });
      tbody.innerHTML = html;

      // Attach delete handlers
      tbody.querySelectorAll('.tg-del-bind-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
          const id = btn.getAttribute('data-id');
          if (!id) return;
          if (!confirm('Вы уверены, что хотите отвязать этот Telegram-аккаунт сотрудника?')) return;
          try {
            await api(`_module/crm.telegram-bot/bindings/${encodeURIComponent(id)}`, { method: 'DELETE' });
            showAlert('Привязка сотрудника успешно удалена', 'success');
            loadBindings();
            loadStatus();
          } catch (err) {
            showAlert(err.message || 'Ошибка удаления привязки', 'danger');
          }
        });
      });
    } catch (err) {
      console.error('[TelegramBot] Failed to load bindings:', err);
      tbody.innerHTML = `<tr><td colspan="6" class="text-danger text-center py-3">Ошибка загрузки списка: ${esc(err.message)}</td></tr>`;
    }
  };

  // Load recent updates log
  const loadLogs = async () => {
    const tbody = $('tgLogsBody');
    if (!tbody) return;

    try {
      const data = await api('_module/crm.telegram-bot/recent-updates?limit=15');
      const items = (data && data.items) || [];

      if (items.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="5" class="text-muted text-center py-4">
              Журнал событий пока пуст. Входящие сообщения от клиентов или команды появятся здесь автоматически.
            </td>
          </tr>
        `;
        return;
      }

      let html = '';
      items.forEach(log => {
        let actionBadge = `<span class="badge bg-secondary">${esc(log.action_type)}</span>`;
        if (log.action_type === 'intake_created') {
          actionBadge = '<span class="badge bg-primary"><i class="fa-solid fa-inbox me-1"></i> Заявка в Intake</span>';
        } else if (log.action_type === 'bind_success') {
          actionBadge = '<span class="badge bg-success"><i class="fa-solid fa-user-check me-1"></i> Привязка сотрудника</span>';
        } else if (log.action_type === 'start_greeted') {
          actionBadge = '<span class="badge bg-info text-dark"><i class="fa-solid fa-hand me-1"></i> /start</span>';
        }

        html += `
          <tr>
            <td><code>#${esc(log.update_id)}</code></td>
            <td><code>${esc(log.chat_id || '—')}</code></td>
            <td>${actionBadge}</td>
            <td class="text-truncate" style="max-width: 320px;">${esc(log.message_preview || '—')}</td>
            <td class="small text-muted">${esc(log.created_at || '—')}</td>
          </tr>
        `;
      });
      tbody.innerHTML = html;
    } catch (err) {
      console.error('[TelegramBot] Failed to load logs:', err);
      tbody.innerHTML = `<tr><td colspan="5" class="text-danger text-center py-3">Ошибка загрузки журнала: ${esc(err.message)}</td></tr>`;
    }
  };

  // Initialize event listeners
  document.addEventListener('DOMContentLoaded', () => {
    if (!$('tgConfigForm') && !$('tgStatusBanner')) return;

    // Toggle Bot Token visibility
    $('tgToggleTokenBtn')?.addEventListener('click', () => {
      const input = $('tgBotToken');
      const icon = $('tgToggleTokenIcon');
      if (!input) return;
      if (input.type === 'password') {
        input.type = 'text';
        icon?.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        input.type = 'password';
        icon?.classList.replace('fa-eye-slash', 'fa-eye');
      }
    });

    // Generate random secret token
    $('tgGenSecretBtn')?.addEventListener('click', () => {
      const arr = new Uint8Array(16);
      window.crypto.getRandomValues(arr);
      const hex = Array.from(arr).map(b => b.toString(16).padStart(2, '0')).join('');
      if ($('tgSecretToken')) {
        $('tgSecretToken').value = hex;
        showAlert('Сгенерирован новый секретный токен вебхука. Не забудьте сохранить настройки.', 'info');
      }
    });

    // Copy Webhook URL
    $('tgCopyWebhookBtn')?.addEventListener('click', () => {
      const input = $('tgWebhookUrl');
      if (input && input.value) {
        navigator.clipboard?.writeText(input.value).then(() => {
          showAlert('Webhook URL скопирован в буфер обмена', 'success');
        }).catch(() => {
          input.select();
          document.execCommand('copy');
          showAlert('Webhook URL скопирован', 'success');
        });
      }
    });

    // Save Configuration Form
    $('tgConfigForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const saveBtn = $('tgSaveBtn');
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Сохранение...';
      }

      const rawToken = $('tgBotToken')?.value || '';
      const body = {
        bot_token: (rawToken.includes('••••') || rawToken.startsWith('gcm:') || rawToken.startsWith('b64:')) ? '' : rawToken,
        bot_username: $('tgBotUsername')?.value || '',
        webhook_secret_token: $('tgSecretToken')?.value || '',
        webhook_url: $('tgWebhookUrl')?.value || '',
        auto_create_intake: !!$('tgAutoIntake')?.checked,
        welcome_message: $('tgWelcomeMessage')?.value || '',
      };

      try {
        await api('_module/crm.telegram-bot/config', {
          method: 'POST',
          body,
        });
        showAlert('Конфигурация Telegram бота успешно сохранена!', 'success');
        await loadStatus();
      } catch (err) {
        showAlert(err.message || 'Ошибка сохранения настроек', 'danger');
      } finally {
        if (saveBtn) {
          saveBtn.disabled = false;
          saveBtn.innerHTML = '<i class="fa-solid fa-check me-1" aria-hidden="true"></i> Сохранить настройки';
        }
      }
    });

    // Test Connection (getMe)
    $('tgTestConnBtn')?.addEventListener('click', async () => {
      const btn = $('tgTestConnBtn');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Проверка...';
      }

      const rawVal = $('tgBotToken')?.value || '';
      const botToken = (rawVal.includes('••••') || rawVal.startsWith('gcm:') || rawVal.startsWith('b64:')) ? '' : rawVal;

      try {
        const res = await api('_module/crm.telegram-bot/test-connection', {
          method: 'POST',
          body: { bot_token: botToken },
        });

        const bot = res.bot || {};
        const username = bot.username ? '@' + bot.username : (bot.first_name || 'Бот');
        showAlert(`Связь с Telegram API подтверждена! Бот: <strong>${esc(username)}</strong> (ID: ${esc(bot.id)})`, 'success');
        if (bot.username && $('tgBotUsername')) {
          $('tgBotUsername').value = '@' + bot.username;
        }
        await loadStatus();
      } catch (err) {
        showAlert(err.message || 'Не удалось связаться с Telegram API', 'danger');
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa-solid fa-bolt me-1" aria-hidden="true"></i> Проверить соединение (getMe)';
        }
      }
    });

    // Set Webhook
    $('tgSetWebhookBtn')?.addEventListener('click', async () => {
      const btn = $('tgSetWebhookBtn');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Установка...';
      }

      const webhookUrl = $('tgWebhookUrl')?.value || getComputedWebhookUrl(currentOrgId);
      const secret = $('tgSecretToken')?.value || '';

      try {
        const res = await api('_module/crm.telegram-bot/set-webhook', {
          method: 'POST',
          body: { webhook_url: webhookUrl, webhook_secret_token: secret },
        });
        showAlert(`Вебхук успешно установлен в Telegram! Ответ: ${esc(res.description || 'OK')}`, 'success');
        await loadStatus();
      } catch (err) {
        showAlert(err.message || 'Ошибка установки вебхука', 'danger');
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa-solid fa-link me-1" aria-hidden="true"></i> Установить Webhook в Telegram';
        }
      }
    });

    // Delete Webhook
    $('tgDeleteWebhookBtn')?.addEventListener('click', async () => {
      const btn = $('tgDeleteWebhookBtn');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Удаление...';
      }

      try {
        const res = await api('_module/crm.telegram-bot/delete-webhook', { method: 'POST' });
        showAlert(`Вебхук удалён. Бот переведён в режим ручного опроса (Polling). Ответ: ${esc(res.description || 'OK')}`, 'info');
        await loadStatus();
      } catch (err) {
        showAlert(err.message || 'Ошибка удаления вебхука', 'danger');
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa-solid fa-link-slash me-1" aria-hidden="true"></i> Удалить Webhook (Polling)';
        }
      }
    });

    // Import from .env
    $('tgImportEnvBtn')?.addEventListener('click', async () => {
      const btn = $('tgImportEnvBtn');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Импорт...';
      }

      try {
        await api('_module/crm.telegram-bot/import-env', { method: 'POST' });
        showAlert('Настройки бота успешно импортированы из файла .env!', 'success');
        await loadStatus();
      } catch (err) {
        showAlert(err.message || 'Ошибка импорта из .env', 'danger');
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa-solid fa-file-import me-1" aria-hidden="true"></i> Импорт из .env';
        }
      }
    });

    // Request Binding Code for current staff member
    $('tgRequestBindBtn')?.addEventListener('click', async () => {
      const btn = $('tgRequestBindBtn');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Генерация...';
      }

      try {
        const res = await api('_module/crm.telegram-bot/bind-request', { method: 'POST' });
        const code = res.verification_code || '------';
        const box = $('tgBindCodeBox');
        if (box) {
          box.style.display = 'block';
          if ($('tgBindCodeValue')) $('tgBindCodeValue').textContent = code;
          if ($('tgBindCommand')) $('tgBindCommand').textContent = `/bind ${code}`;
          if ($('tgOpenBotLink') && currentBotUsername) {
            $('tgOpenBotLink').href = `https://t.me/${encodeURIComponent(currentBotUsername.replace('@', ''))}`;
          }
        }
        showAlert(`Код привязки сгенерирован: <strong>${esc(code)}</strong>. Отправьте боту команду <code>/bind ${esc(code)}</code>`, 'info');
        loadBindings();
      } catch (err) {
        showAlert(err.message || 'Ошибка генерации кода привязки', 'danger');
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa-solid fa-key me-1" aria-hidden="true"></i> Получить мой код привязки';
        }
      }
    });

    // Poll updates manually
    $('tgPollUpdatesBtn')?.addEventListener('click', async () => {
      const btn = $('tgPollUpdatesBtn');
      const statusEl = $('tgPollStatus');
      const resultEl = $('tgPollResult');

      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Опрос...';
      }
      if (statusEl) statusEl.textContent = 'Обращение к очереди Telegram API…';

      try {
        const res = await api('_module/crm.telegram-bot/poll', { method: 'POST', body: { limit: 20 } });
        const count = res.processed_count ?? 0;
        if (statusEl) statusEl.textContent = `Успешно: ${count} обновлений обработано.`;
        if (resultEl) {
          resultEl.style.display = 'block';
          resultEl.textContent = JSON.stringify(res, null, 2);
        }
        showAlert(`Опрос завершён: обработано новых обновлений: ${count}`, 'success');
        loadStatus();
        loadLogs();
      } catch (err) {
        if (statusEl) statusEl.textContent = 'Ошибка опроса.';
        showAlert(err.message || 'Ошибка при опросе getUpdates', 'danger');
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa-solid fa-arrows-rotate me-1" aria-hidden="true"></i> Запустить опрос (getUpdates)';
        }
      }
    });

    // Send Test Message
    $('tgSendMsgForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = $('tgSendMsgBtn');
      const statusEl = $('tgSendMsgStatus');
      const chatId = $('tgTestChatId')?.value || '';
      const text = $('tgTestMessageText')?.value || '';

      if (!chatId || !text) {
        showAlert('Укажите Chat ID и текст сообщения', 'warning');
        return;
      }

      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Отправка...';
      }
      if (statusEl) statusEl.textContent = '';

      try {
        await api('_module/crm.telegram-bot/send-message', {
          method: 'POST',
          body: { chat_id: chatId, text },
        });
        showAlert(`Сообщение успешно доставлено в чат <code>${esc(chatId)}</code>!`, 'success');
        if (statusEl) statusEl.innerHTML = '<span class="text-success">✓ Отправлено</span>';
      } catch (err) {
        showAlert(err.message || 'Ошибка отправки сообщения', 'danger');
        if (statusEl) statusEl.innerHTML = `<span class="text-danger">✗ Ошибка: ${esc(err.message)}</span>`;
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa-regular fa-paper-plane me-1" aria-hidden="true"></i> Отправить сообщение';
        }
      }
    });

    // Refresh buttons
    $('tgRefreshBtn')?.addEventListener('click', () => {
      loadStatus();
      loadBindings();
      loadLogs();
      showAlert('Данные обновлены', 'info');
    });

    $('tgRefreshLogsBtn')?.addEventListener('click', () => {
      loadLogs();
    });

    // Initial load
    loadStatus();
    loadBindings();
    loadLogs();
  });
})();
