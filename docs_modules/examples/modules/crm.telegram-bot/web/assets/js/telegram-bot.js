(() => {
  'use strict';

  // API helper using window.CRM.api.request with robust fallback
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
  let currentActiveDialogId = null;
  let activeDialogData = null;
  let projectsList = [];

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

  // Load Workspaces / Organizations
  const loadWorkspaces = async () => {
    const sel = $('tgWorkspaceSelect');
    if (!sel) return;

    try {
      const data = await api('_module/crm.telegram-bot/organizations');
      const items = (data && data.items) || [];
      const activeOrg = data && data.active_organization_id ? parseInt(data.active_organization_id, 10) : currentOrgId;

      if (items.length > 0) {
        sel.innerHTML = items.map(org => {
          const isSel = org.id === activeOrg ? 'selected' : '';
          return `<option value="${esc(org.id)}" ${isSel}>${esc(org.name)} (#${esc(org.id)})</option>`;
        }).join('');
        currentOrgId = activeOrg;
        if ($('tgWorkspaceBadge')) {
          $('tgWorkspaceBadge').textContent = `Орг #${currentOrgId}`;
        }
      }
    } catch (err) {
      console.warn('[TelegramBot] Failed to load organizations list:', err);
    }
  };

  // Load Status and config
  const loadStatus = async () => {
    try {
      const data = await api('_module/crm.telegram-bot/status', {
        query: { organization_id: currentOrgId },
      });

      // 1. Status Banner
      const banner = $('tgStatusBanner');
      if (banner) {
        const isActive = !!data.is_active;
        const username = data.bot_username ? (data.bot_username.startsWith('@') ? data.bot_username : '@' + data.bot_username) : '';
        currentBotUsername = username;
        if (data.organization_id) {
          currentOrgId = parseInt(data.organization_id, 10);
        }

        let badgeHtml = isActive
          ? `<span class="badge bg-success fs-6"><i class="fa-solid fa-circle-check me-1"></i> Подключён · Активен</span>`
          : `<span class="badge bg-warning text-dark fs-6"><i class="fa-solid fa-triangle-exclamation me-1"></i> Установлен · не подключён</span>`;

        let detailsHtml = isActive
          ? (username ? `Бот <a href="https://t.me/${encodeURIComponent(username.replace('@', ''))}" target="_blank" rel="noopener noreferrer" class="fw-bold text-decoration-none">${esc(username)}</a> подключён и принимает сообщения.` : 'Бот успешно настроен и принимает обращения.')
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

  // Load Projects for task creation modal
  const loadProjects = async () => {
    try {
      const data = await api('_module/crm.telegram-bot/projects', {
        query: { organization_id: currentOrgId },
      });
      projectsList = (data && data.items) || [];
      const sel = $('tgTaskProjectSelect');
      if (sel) {
        let opts = '<option value="0">Без проекта (общие задачи)</option>';
        projectsList.forEach(p => {
          opts += `<option value="${esc(p.id)}">${esc(p.name)}</option>`;
        });
        sel.innerHTML = opts;
      }
    } catch (err) {
      console.warn('[TelegramBot] Failed to load projects:', err);
    }
  };

  // Load Dialogs List
  const loadDialogs = async () => {
    const listEl = $('tgDialogsList');
    if (!listEl) return;

    const statusFilter = $('tgDialogStatusFilter')?.value || 'all';
    const searchVal = $('tgDialogSearch')?.value || '';

    try {
      const data = await api('_module/crm.telegram-bot/dialogs', {
        query: {
          organization_id: currentOrgId,
          status: statusFilter,
          search: searchVal,
          limit: 50,
        },
      });

      const items = (data && data.items) || [];
      const countBadge = $('tgDialogsCountBadge');
      if (countBadge) {
        if (items.length > 0) {
          countBadge.style.display = 'inline-block';
          countBadge.textContent = String(items.length);
        } else {
          countBadge.style.display = 'none';
        }
      }

      if (items.length === 0) {
        listEl.innerHTML = `
          <div class="text-center text-muted py-5 px-3">
            <i class="fa-regular fa-message fs-3 mb-2 opacity-50"></i>
            <div>Нет активных диалогов с клиентами.</div>
            <div class="small mt-1">Когда клиент напишет боту, диалог появится здесь автоматически.</div>
          </div>
        `;
        return;
      }

      let html = '';
      items.forEach(d => {
        const isActive = d.public_id === currentActiveDialogId ? 'active' : '';
        const name = (d.first_name || d.last_name) ? `${d.first_name || ''} ${d.last_name || ''}`.trim() : (d.username ? '@' + d.username : `Клиент #${d.chat_id}`);
        const initials = (d.first_name ? d.first_name[0] : (d.username ? d.username[0] : 'К')).toUpperCase();
        const snippet = d.last_message_text || 'Входящее обращение';
        const time = d.last_message_at ? d.last_message_at.slice(11, 16) : '';
        const unread = d.unread_count > 0 ? `<span class="badge bg-danger rounded-pill">${esc(d.unread_count)}</span>` : '';

        let statusDot = 'bg-secondary';
        if (d.status === 'open') statusDot = 'bg-primary';
        if (d.status === 'in_progress') statusDot = 'bg-warning';
        if (d.status === 'resolved') statusDot = 'bg-success';

        html += `
          <div class="tg-dialog-item ${isActive}" data-id="${esc(d.public_id)}">
            <div class="tg-avatar-circle">${esc(initials)}</div>
            <div class="tg-dialog-body">
              <div class="tg-dialog-top">
                <div class="tg-dialog-name d-flex align-items-center gap-1">
                  <span class="badge ${statusDot} p-1 rounded-circle" style="width:8px;height:8px;" title="Статус: ${esc(d.status)}"></span>
                  ${esc(name)}
                </div>
                <div class="tg-dialog-time">${esc(time)}</div>
              </div>
              <div class="d-flex justify-content-between align-items-center gap-2">
                <div class="tg-dialog-snippet">${esc(snippet)}</div>
                ${unread}
              </div>
            </div>
          </div>
        `;
      });

      listEl.innerHTML = html;

      // Click listener for dialog items
      listEl.querySelectorAll('.tg-dialog-item').forEach(item => {
        item.addEventListener('click', () => {
          const id = item.getAttribute('data-id');
          if (id) {
            selectDialog(id);
          }
        });
      });

      // If active dialog exists, maintain selection
      if (currentActiveDialogId) {
        const activeItem = listEl.querySelector(`[data-id="${currentActiveDialogId}"]`);
        if (activeItem) {
          activeItem.classList.add('active');
        }
      }
    } catch (err) {
      console.error('[TelegramBot] Failed to load dialogs:', err);
      listEl.innerHTML = `<div class="text-danger text-center py-3">Ошибка загрузки: ${esc(err.message)}</div>`;
    }
  };

  // Select Dialog and render chat messages
  const selectDialog = async (dialogId) => {
    currentActiveDialogId = dialogId;

    // Update active highlight in left list
    document.querySelectorAll('.tg-dialog-item').forEach(el => {
      el.classList.toggle('active', el.getAttribute('data-id') === dialogId);
    });

    const noDialogView = $('tgNoDialogSelected');
    const activeView = $('tgActiveDialogView');
    const thread = $('tgMessagesThread');

    if (noDialogView) noDialogView.style.setProperty('display', 'none', 'important');
    if (activeView) activeView.style.setProperty('display', 'flex', 'important');
    if (thread) {
      thread.innerHTML = `
        <div class="text-center text-muted py-5">
          <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
          <div>Загрузка сообщений...</div>
        </div>
      `;
    }

    try {
      const data = await api(`_module/crm.telegram-bot/dialogs/${encodeURIComponent(dialogId)}`, {
        query: { organization_id: currentOrgId },
      });

      activeDialogData = data;
      const d = data.dialog || {};
      const messages = data.messages || [];

      // Update header
      const clientName = (d.first_name || d.last_name) ? `${d.first_name || ''} ${d.last_name || ''}`.trim() : (d.username ? '@' + d.username : `Клиент #${d.chat_id}`);
      const initials = (d.first_name ? d.first_name[0] : (d.username ? d.username[0] : 'К')).toUpperCase();

      if ($('tgActiveAvatar')) $('tgActiveAvatar').textContent = initials;
      if ($('tgActiveClientName')) $('tgActiveClientName').textContent = clientName;
      if ($('tgActiveChatId')) $('tgActiveChatId').textContent = `Chat ID: ${d.chat_id}`;
      if ($('tgActiveClientHandle')) {
        $('tgActiveClientHandle').textContent = d.username ? `@${d.username}` : (d.first_name ? `Telegram user #${d.chat_id}` : '');
      }

      if ($('tgActiveStatusSelect')) {
        $('tgActiveStatusSelect').value = d.status || 'open';
      }

      // Intake & Task links
      const intakeLink = $('tgActiveIntakeLink');
      if (intakeLink) {
        if (d.linked_intake_id) {
          intakeLink.style.display = 'inline-block';
          intakeLink.href = 'index.php?route=intake';
        } else {
          intakeLink.style.display = 'none';
        }
      }

      const taskLink = $('tgActiveTaskLink');
      if (taskLink) {
        if (d.linked_task_id) {
          taskLink.style.display = 'inline-block';
          taskLink.href = `index.php?route=task-detail&id=${encodeURIComponent(d.linked_task_id)}`;
          taskLink.textContent = `Задача #${d.linked_task_id}`;
        } else {
          taskLink.style.display = 'none';
        }
      }

      // Render Messages Thread
      if (messages.length === 0) {
        thread.innerHTML = `
          <div class="text-center text-muted py-5">
            <div>История сообщений пока пуста.</div>
          </div>
        `;
      } else {
        let msgHtml = '';
        messages.forEach(m => {
          const isOutbound = m.direction === 'outbound';
          const isSystem = m.sender_type === 'system';
          const sender = m.sender_name || (isOutbound ? 'Менеджер' : clientName);
          const time = m.created_at ? m.created_at.slice(11, 16) : '';

          if (isSystem) {
            msgHtml += `
              <div class="tg-msg tg-msg-system">
                <div class="tg-msg-bubble">${esc(m.message_text)}</div>
                <div class="tg-msg-meta">${esc(time)}</div>
              </div>
            `;
          } else if (isOutbound) {
            msgHtml += `
              <div class="tg-msg tg-msg-outbound">
                <div class="tg-msg-bubble">${esc(m.message_text)}</div>
                <div class="tg-msg-meta">
                  <span>${esc(sender)}</span> · <span>${esc(time)}</span>
                  ${m.delivery_status === 'sent' ? '<i class="fa-solid fa-check ms-1" title="Доставлено"></i>' : ''}
                </div>
              </div>
            `;
          } else {
            msgHtml += `
              <div class="tg-msg tg-msg-inbound">
                <div class="tg-msg-bubble">${esc(m.message_text)}</div>
                <div class="tg-msg-meta">
                  <span>${esc(sender)}</span> · <span>${esc(time)}</span>
                </div>
              </div>
            `;
          }
        });

        thread.innerHTML = msgHtml;
      }

      // Auto-scroll to bottom of thread
      thread.scrollTop = thread.scrollHeight;
    } catch (err) {
      console.error('[TelegramBot] Failed to load dialog messages:', err);
      thread.innerHTML = `<div class="text-danger text-center py-4">Ошибка загрузки переписки: ${esc(err.message)}</div>`;
    }
  };

  // Reply in Dialog
  const sendReply = async () => {
    if (!currentActiveDialogId) return;
    const input = $('tgReplyText');
    const text = input ? input.value.trim() : '';
    if (!text) return;

    const btn = $('tgSendReplyBtn');
    const statusEl = $('tgReplyStatus');

    if (btn) btn.disabled = true;
    if (statusEl) statusEl.textContent = 'Отправка...';

    try {
      await api(`_module/crm.telegram-bot/dialogs/${encodeURIComponent(currentActiveDialogId)}/reply`, {
        method: 'POST',
        body: {
          text,
          organization_id: currentOrgId,
        },
      });

      if (input) input.value = '';
      if (statusEl) statusEl.innerHTML = '<span class="text-success">✓ Доставлено</span>';
      setTimeout(() => { if (statusEl) statusEl.textContent = ''; }, 3000);

      // Reload active dialog messages and refresh list snippet
      await selectDialog(currentActiveDialogId);
      loadDialogs();
    } catch (err) {
      console.error('[TelegramBot] Reply failed:', err);
      if (statusEl) statusEl.innerHTML = `<span class="text-danger">✗ Ошибка: ${esc(err.message)}</span>`;
      showAlert(err.message || 'Ошибка отправки ответа', 'danger');
    } finally {
      if (btn) btn.disabled = false;
    }
  };

  // Update dialog status from select
  const updateDialogStatus = async (newStatus) => {
    if (!currentActiveDialogId) return;
    try {
      await api(`_module/crm.telegram-bot/dialogs/${encodeURIComponent(currentActiveDialogId)}/status`, {
        method: 'POST',
        body: {
          status: newStatus,
          organization_id: currentOrgId,
        },
      });
      showAlert(`Статус диалога изменен на «${esc(newStatus)}»`, 'success');
      loadDialogs();
    } catch (err) {
      showAlert(err.message || 'Ошибка обновления статуса', 'danger');
    }
  };

  // Create Task from Dialog
  const submitCreateTask = async (e) => {
    e.preventDefault();
    if (!currentActiveDialogId) return;

    const btn = $('tgSubmitCreateTaskBtn');
    const title = $('tgTaskTitle')?.value.trim() || '';
    const projectId = parseInt($('tgTaskProjectSelect')?.value || '0', 10);
    const priority = $('tgTaskPriority')?.value || 'medium';
    const description = $('tgTaskDescription')?.value || '';

    if (!title) {
      showAlert('Укажите название задачи', 'warning');
      return;
    }

    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Создание...';
    }

    try {
      const res = await api(`_module/crm.telegram-bot/dialogs/${encodeURIComponent(currentActiveDialogId)}/create-task`, {
        method: 'POST',
        body: {
          title,
          project_id: projectId,
          priority,
          description,
          organization_id: currentOrgId,
        },
      });

      // Close modal
      const modalEl = $('tgCreateTaskModal');
      if (modalEl) {
        if (window.bootstrap?.Modal) {
          const modalInstance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
          modalInstance.hide();
        } else {
          modalEl.classList.remove('show');
          modalEl.style.display = 'none';
          document.body.classList.remove('modal-open');
          const backdrop = document.querySelector('.modal-backdrop');
          if (backdrop) backdrop.remove();
        }
      }

      showAlert(`Задача успешно создана в CRM: <strong>#${esc(res.task_id || '')}</strong>!`, 'success');
      await selectDialog(currentActiveDialogId);
      loadDialogs();
      loadLogs();
    } catch (err) {
      showAlert(err.message || 'Ошибка создания задачи', 'danger');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Создать задачу';
      }
    }
  };

  // Load staff bindings
  const loadBindings = async () => {
    const tbody = $('tgBindingsBody');
    if (!tbody) return;

    try {
      const data = await api('_module/crm.telegram-bot/bindings', {
        query: { organization_id: currentOrgId },
      });
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
            await api(`_module/crm.telegram-bot/bindings/${encodeURIComponent(id)}`, {
              method: 'DELETE',
              query: { organization_id: currentOrgId },
            });
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
      const data = await api('_module/crm.telegram-bot/recent-updates', {
        query: { limit: 15, organization_id: currentOrgId },
      });
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

  // Refresh all sections
  const refreshAll = () => {
    loadStatus();
    loadDialogs();
    loadBindings();
    loadLogs();
    loadProjects();
  };

  // Initialize event listeners
  document.addEventListener('DOMContentLoaded', async () => {
    if (!$('tgConfigForm') && !$('tgStatusBanner') && !$('tgTabs')) return;

    // Workspace change handler
    $('tgWorkspaceSelect')?.addEventListener('change', (e) => {
      currentOrgId = parseInt(e.target.value, 10) || 1;
      if ($('tgWorkspaceBadge')) {
        $('tgWorkspaceBadge').textContent = `Орг #${currentOrgId}`;
      }
      if ($('tgWebhookUrl')) {
        $('tgWebhookUrl').value = getComputedWebhookUrl(currentOrgId);
      }
      currentActiveDialogId = null;
      if ($('tgNoDialogSelected')) $('tgNoDialogSelected').style.removeProperty('display');
      if ($('tgActiveDialogView')) $('tgActiveDialogView').style.setProperty('display', 'none', 'important');
      refreshAll();
    });

    // Tab switching fallback (in case bootstrap tab component is delayed)
    document.querySelectorAll('#tgTabs button[data-bs-toggle="pill"]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelectorAll('#tgTabs .nav-link').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const target = btn.getAttribute('data-bs-target');
        if (target) {
          document.querySelectorAll('#tgTabsContent .tab-pane').forEach(pane => {
            pane.classList.remove('show', 'active');
          });
          const targetPane = document.querySelector(target);
          if (targetPane) {
            targetPane.classList.add('show', 'active');
          }
        }
      });
    });

    // Dialogs Search and Status Filter
    $('tgDialogSearch')?.addEventListener('input', () => {
      loadDialogs();
    });
    $('tgDialogStatusFilter')?.addEventListener('change', () => {
      loadDialogs();
    });
    $('tgRefreshDialogsBtn')?.addEventListener('click', () => {
      loadDialogs();
    });

    // Active Dialog status change
    $('tgActiveStatusSelect')?.addEventListener('change', (e) => {
      updateDialogStatus(e.target.value);
    });

    // Reply Form
    $('tgReplyForm')?.addEventListener('submit', (e) => {
      e.preventDefault();
      sendReply();
    });

    // Ctrl+Enter or Cmd+Enter to send reply
    $('tgReplyText')?.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        sendReply();
      }
    });

    // Open "Create Task" modal
    $('tgOpenCreateTaskModalBtn')?.addEventListener('click', () => {
      if (!activeDialogData || !activeDialogData.dialog) {
        showAlert('Сначала выберите диалог', 'warning');
        return;
      }
      const d = activeDialogData.dialog;
      const clientName = (d.first_name || d.last_name) ? `${d.first_name || ''} ${d.last_name || ''}`.trim() : (d.username ? '@' + d.username : `Клиент #${d.chat_id}`);

      if ($('tgTaskTitle')) {
        $('tgTaskTitle').value = `Обращение из Telegram: ${clientName}`;
      }

      // Format description with messages excerpt
      const messages = activeDialogData.messages || [];
      let excerpt = `Запрос от клиента Telegram: ${clientName} (Chat ID: ${d.chat_id}${d.username ? ', @' + d.username : ''})\n\n`;
      excerpt += `История переписки:\n`;
      messages.forEach(m => {
        const time = m.created_at || '';
        const sender = m.sender_name || (m.direction === 'outbound' ? 'Менеджер' : clientName);
        excerpt += `[${time}] ${sender}: ${m.message_text}\n`;
      });

      if ($('tgTaskDescription')) {
        $('tgTaskDescription').value = excerpt;
      }

      // Show modal
      const modalEl = $('tgCreateTaskModal');
      if (modalEl) {
        if (window.bootstrap?.Modal) {
          const modalInstance = window.bootstrap.Modal.getOrCreateInstance(modalEl);
          modalInstance.show();
        } else {
          modalEl.classList.add('show');
          modalEl.style.display = 'block';
          document.body.classList.add('modal-open');
        }
      }
    });

    // Submit Create Task Form
    $('tgCreateTaskForm')?.addEventListener('submit', submitCreateTask);

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
        organization_id: currentOrgId,
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
          body: { bot_token: botToken, organization_id: currentOrgId },
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
          body: { webhook_url: webhookUrl, webhook_secret_token: secret, organization_id: currentOrgId },
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
        const res = await api('_module/crm.telegram-bot/delete-webhook', {
          method: 'POST',
          body: { organization_id: currentOrgId },
        });
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
        await api('_module/crm.telegram-bot/import-env', {
          method: 'POST',
          body: { organization_id: currentOrgId },
        });
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
        const res = await api('_module/crm.telegram-bot/bind-request', {
          method: 'POST',
          body: { organization_id: currentOrgId },
        });
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
        const res = await api('_module/crm.telegram-bot/poll', {
          method: 'POST',
          body: { limit: 20, organization_id: currentOrgId },
        });
        const count = res.processed_count ?? 0;
        if (statusEl) statusEl.textContent = `Успешно: ${count} обновлений обработано.`;
        if (resultEl) {
          resultEl.style.display = 'block';
          resultEl.textContent = JSON.stringify(res, null, 2);
        }
        showAlert(`Опрос завершён: обработано новых обновлений: ${count}`, 'success');
        loadStatus();
        loadDialogs();
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
          body: { chat_id: chatId, text, organization_id: currentOrgId },
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
      refreshAll();
      showAlert('Данные обновлены', 'info');
    });

    $('tgRefreshLogsBtn')?.addEventListener('click', () => {
      loadLogs();
    });

    // Initial load sequence
    await loadWorkspaces();
    refreshAll();
  });
})();
