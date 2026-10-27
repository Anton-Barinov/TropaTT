(() => {
  'use strict';

  if (!document.querySelector('[data-page="module-corporate-mail"]')) {
    return;
  }

  const MODULE_ROUTE = '_module/crm.corporate-mail';
  let activeMailbox = null;
  let activeMailboxEmail = '';
  let activeThread = '';
  let activeMessages = [];
  let activeSendKey = '';
  const pendingStorageKey = 'tropatt.corporate-mail.pending-send.v1';
  let pendingStorageHealthy = true;
  const loadPendingSends = () => {
    try {
      const raw = window.sessionStorage.getItem(pendingStorageKey);
      const stored = JSON.parse(raw || '{}');
      if (!stored || typeof stored !== 'object' || Array.isArray(stored)) { pendingStorageHealthy = false; return new Map(); }
      const entries = Object.entries(stored);
      return new Map(entries.filter(([slot, payload]) =>
        typeof slot === 'string' && slot.length <= 260 && payload &&
        typeof payload.idempotency_key === 'string' && /^[a-f0-9]{48}$/.test(payload.idempotency_key)
      ).map(([slot, payload]) => [slot, { idempotency_key: payload.idempotency_key, persisted: true }]).slice(-20));
    } catch (_) { pendingStorageHealthy = false; return new Map(); }
  };
  const pendingSends = loadPendingSends();
  const persistPendingSends = () => {
    try {
      const safeEntries = Array.from(pendingSends, ([slot, payload]) => [slot, { idempotency_key: payload.idempotency_key }]);
      window.sessionStorage.setItem(pendingStorageKey, JSON.stringify(Object.fromEntries(safeEntries)));
      return true;
    }
    catch (_) { pendingStorageHealthy = false; return false; }
  };
  const clearPendingSend = (slot) => { pendingSends.delete(slot); persistPendingSends(); };

  const apiRequest = (path, options = {}) => {
    if (window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function') {
      return window.CRM.api.request(path, options).then(envelope => envelope.data || envelope);
    }
    const method = String(options.method || 'GET').toUpperCase();
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    if (['POST', 'PATCH', 'PUT', 'DELETE'].includes(method) && window.CRM && window.CRM.api && window.CRM.api.getCsrfToken) {
      headers['X-CSRF-Token'] = window.CRM.api.getCsrfToken();
    }
    const hasBody = options.body !== undefined && options.body !== null;
    if (hasBody && typeof options.body !== 'string' && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }
    const url = (path.startsWith('/') ? path : '/' + path);
    return fetch(url, {
      ...options,
      method,
      credentials: 'same-origin',
      headers,
      body: hasBody ? (typeof options.body === 'string' ? options.body : JSON.stringify(options.body)) : undefined
    }).then(async res => {
      const data = await res.json().catch(() => ({}));
      if (!res.ok || data.success === false) {
        const error = new Error(data.message || data.code || 'Ошибка запроса к серверу');
        error.code = data.code || '';
        throw error;
      }
      return data.data || data;
    });
  };

  const esc = (s) => {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[c]);
  };

  const showAlert = (message, type = 'success') => {
    const container = document.getElementById('cmailAlertContainer');
    if (!container) return;
    container.style.display = 'block';
    container.innerHTML = `
      <div class="alert alert-${type} alert-dismissible fade show mb-0" role="alert">
        <div>${esc(message)}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
      </div>
    `;
    setTimeout(() => {
      if (container.firstElementChild) {
        container.firstElementChild.classList.remove('show');
        setTimeout(() => { container.style.display = 'none'; container.innerHTML = ''; }, 300);
      }
    }, 6000);
  };

  // State
  let modalInstance = null;

  const getModal = () => {
    const el = document.getElementById('cmailMailboxModal');
    if (!el) return null;
    if (!modalInstance && window.bootstrap && window.bootstrap.Modal) {
      modalInstance = window.bootstrap.Modal.getOrCreateInstance(el);
    }
    return modalInstance;
  };

  const updateStatusBanner = (status) => {
    const banner = document.getElementById('cmailStatusBanner');
    const importBtn = document.getElementById('cmailImportEnvBtn');
    if (!banner) return;

    if (status.status === 'configured') {
      banner.innerHTML = `
        <div class="alert alert-warning d-flex align-items-center justify-content-between p-3" role="status">
          <div class="d-flex align-items-center">
            <i class="fa-solid fa-triangle-exclamation fs-4 text-warning me-3" aria-hidden="true"></i>
            <div>
              <strong class="fs-6">Почтовые ящики настроены</strong>
              <div class="small">Настроено ящиков: <strong>${status.active_mailboxes_count}</strong>. Доступны проверка TLS, bounded-синхронизация IMAP и отправка через SMTP; ошибки авторизации показываются после проверки.</div>
            </div>
          </div>
          ${status.last_synced_at ? `<span class="badge bg-secondary-subtle text-secondary border">Последний статус: ${esc(status.last_synced_at)}</span>` : ''}
        </div>
      `;
    } else if (status.status === 'env_ready') {
      banner.innerHTML = `
        <div class="alert alert-info d-flex align-items-center justify-content-between p-3" role="status">
          <div class="d-flex align-items-center">
            <i class="fa-solid fa-circle-info fs-4 text-info me-3" aria-hidden="true"></i>
            <div>
              <strong class="fs-6">Обнаружены настройки в .env</strong>
              <div class="small">${esc(status.env_hint || 'Найдены параметры почтового шлюза.')} Вы можете импортировать этот ящик в один клик.</div>
            </div>
          </div>
          <button class="btn btn-sm crm-btn-primary" id="cmailBannerImportBtn">
            <i class="fa-solid fa-file-import me-1" aria-hidden="true"></i> Импортировать из .env
          </button>
        </div>
      `;
      document.getElementById('cmailBannerImportBtn')?.addEventListener('click', importEnv);
    } else {
      banner.innerHTML = `
        <div class="alert alert-warning d-flex align-items-center justify-content-between p-3" role="status">
          <div class="d-flex align-items-center">
            <i class="fa-solid fa-triangle-exclamation fs-4 text-warning me-3" aria-hidden="true"></i>
            <div>
              <strong class="fs-6">Установлен · требуется настройка</strong>
              <div class="small">Почтовые ящики пока не добавлены. Можно сохранить настройки; приём и отправка писем станут доступны после завершения интеграции.</div>
            </div>
          </div>
          <button class="btn btn-sm crm-btn-primary" id="cmailBannerAddBtn">
            <i class="fa-solid fa-plus me-1" aria-hidden="true"></i> Добавить ящик
          </button>
        </div>
      `;
      document.getElementById('cmailBannerAddBtn')?.addEventListener('click', openAddModal);
    }

    if (importBtn) {
      importBtn.style.display = (status.env_available && status.mailboxes_count === 0) ? 'inline-block' : 'none';
    }

    // Update KPIs
    const elTotal = document.getElementById('kpiTotal');
    const elActive = document.getElementById('kpiActive');
    const elStatus = document.getElementById('kpiStatus');
    const elLastSync = document.getElementById('kpiLastSync');

    if (elTotal) elTotal.textContent = status.mailboxes_count ?? 0;
    if (elActive) elActive.textContent = status.active_mailboxes_count ?? 0;
    if (elStatus) {
      elStatus.textContent = status.is_connected ? 'В сети' : (status.env_available ? '.env найден' : 'Ожидает');
      elStatus.className = 'h4 mb-0 mt-1 ' + (status.is_connected ? 'text-success' : 'text-muted');
    }
    if (elLastSync) elLastSync.textContent = status.last_synced_at || 'Никогда';
  };

  const loadStatus = () => {
    return apiRequest(`${MODULE_ROUTE}/status`)
      .then(data => {
        updateStatusBanner(data);
        return data;
      })
      .catch(err => {
        const banner = document.getElementById('cmailStatusBanner');
        if (banner) {
          banner.innerHTML = `
            <div class="alert alert-danger p-3 mb-0" role="status">
              <strong>Ошибка получения статуса:</strong> ${esc(err.message)}
            </div>
          `;
        }
      });
  };

  const loadMailboxes = () => {
    const tbody = document.getElementById('cmailTableBody');
    if (!tbody) return;

    apiRequest(`${MODULE_ROUTE}/mailboxes`)
      .then(res => {
        const items = res.items || [];
        if (items.length === 0) {
          tbody.innerHTML = `
            <tr>
              <td colspan="6" class="text-center py-5 text-muted">
                <i class="fa-solid fa-inbox fs-2 mb-2 d-block opacity-50" aria-hidden="true"></i>
                <div class="fw-semibold">Почтовые ящики пока не подключены</div>
                <div class="small mb-3">Добавьте IMAP/SMTP параметры или выполните быстрый импорт</div>
                <button class="btn btn-sm crm-btn-primary" id="cmailEmptyAddBtn">
                  <i class="fa-solid fa-plus me-1" aria-hidden="true"></i> Добавить почтовый ящик
                </button>
              </td>
            </tr>
          `;
          document.getElementById('cmailEmptyAddBtn')?.addEventListener('click', openAddModal);
          return;
        }

        tbody.innerHTML = items.map(m => {
          const isActive = !!m.is_active;
          const syncStatus = m.sync_status || 'idle';
          let syncBadge = '<span class="badge bg-secondary">Ожидание</span>';
          if (syncStatus === 'synced' || syncStatus === 'ok') {
            syncBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle">Синхронизирован</span>';
          } else if (syncStatus === 'error') {
            syncBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Ошибка связи</span>';
          } else if (syncStatus === 'syncing') {
            syncBadge = '<span class="badge bg-info-subtle text-info border border-info-subtle">Синхронизация...</span>';
          }

          return `
            <tr data-id="${esc(m.public_id)}">
              <td>
                <div class="fw-bold">${esc(m.display_name || m.email_address)}</div>
                <div class="small text-muted">${esc(m.email_address)}</div>
              </td>
              <td>
                <span class="badge bg-light text-dark border">${esc(m.protocol_adapter || 'imap_smtp')}</span>
              </td>
              <td>
                <span class="badge ${isActive ? 'bg-success' : 'bg-secondary'}">${isActive ? 'Активен' : 'Отключён'}</span>
              </td>
              <td>${syncBadge}</td>
              <td class="small text-muted">${esc(m.last_synced_at || 'Никогда')}</td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn crm-btn-secondary cmail-btn-test" data-id="${esc(m.public_id)}" title="Проверить соединение">
                    <i class="fa-solid fa-plug" aria-hidden="true"></i>
                  </button>
                  <button type="button" class="btn crm-btn-secondary cmail-btn-inbox" data-id="${esc(m.public_id)}" data-email="${esc(m.email_address)}" data-name="${esc(m.display_name || m.email_address)}" title="Открыть переписки" aria-label="Открыть переписки ящика ${esc(m.display_name || m.email_address)}">Входящие</button>
                  <button type="button" class="btn crm-btn-secondary cmail-btn-sync" data-id="${esc(m.public_id)}" title="Синхронизировать следующую ограниченную партию" aria-label="Синхронизировать следующую партию">
                    <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                  </button>
                  <button type="button" class="btn crm-btn-secondary cmail-btn-failures" data-id="${esc(m.public_id)}" title="Показать проблемные письма (${esc(m.sync_failure_count || 0)})" aria-label="Показать проблемные письма">UID ${esc(m.sync_failure_count || 0)}</button>
                  <button type="button" class="btn crm-btn-secondary cmail-btn-edit" data-id="${esc(m.public_id)}" title="Редактировать">
                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                  </button>
                  <button type="button" class="btn crm-btn-danger-soft cmail-btn-delete" data-id="${esc(m.public_id)}" title="Удалить ящик">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                  </button>
                </div>
              </td>
            </tr>
          `;
        }).join('');

        // Attach action handlers
        tbody.querySelectorAll('.cmail-btn-test').forEach(btn => {
          btn.addEventListener('click', () => testMailbox(btn.getAttribute('data-id'), btn));
        });
        tbody.querySelectorAll('.cmail-btn-inbox').forEach(btn => {
          btn.addEventListener('click', () => openInbox(btn.getAttribute('data-id'), btn.getAttribute('data-email'), btn.getAttribute('data-name')));
        });
        tbody.querySelectorAll('.cmail-btn-sync').forEach(btn => {
          btn.addEventListener('click', () => syncMailbox(btn.getAttribute('data-id'), btn));
        });
        tbody.querySelectorAll('.cmail-btn-failures').forEach(btn => {
          btn.addEventListener('click', () => loadSyncFailures(btn.getAttribute('data-id')));
        });
        tbody.querySelectorAll('.cmail-btn-edit').forEach(btn => {
          btn.addEventListener('click', () => openEditModal(btn.getAttribute('data-id')));
        });
        tbody.querySelectorAll('.cmail-btn-delete').forEach(btn => {
          btn.addEventListener('click', () => deleteMailbox(btn.getAttribute('data-id'), btn));
        });
      })
      .catch(err => {
        tbody.innerHTML = `
          <tr>
            <td colspan="6" class="text-danger text-center py-4">
              Ошибка загрузки почтовых ящиков: ${esc(err.message)}
            </td>
          </tr>
        `;
      });
  };

  const openInbox = (mailboxId, mailboxEmail, mailboxName) => {
    activeMailbox = mailboxId;
    activeMailboxEmail = mailboxEmail || '';
    activeThread = '';
    activeMessages = [];
    activeSendKey = '';
    const panel = document.getElementById('cmailInboxPanel');
    if (panel) panel.hidden = false;
    const label = document.getElementById('cmailInboxMailboxLabel');
    if (label) label.textContent = `${mailboxName || mailboxEmail || mailboxId} · ${mailboxEmail || ''}`;
    document.getElementById('cmailReplyForm')?.setAttribute('hidden', '');
    document.getElementById('cmailMessageList').innerHTML = '<p class="text-muted p-3 mb-0">Выберите переписку, чтобы открыть письма.</p>';
    loadThreads();
    panel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  const loadThreads = () => {
    const list = document.getElementById('cmailThreadList');
    if (!list || !activeMailbox) return;
    list.innerHTML = '<p class="text-muted p-3 mb-0">Загружаем переписки…</p>';
    apiRequest(`${MODULE_ROUTE}/mailboxes/${encodeURIComponent(activeMailbox)}/threads?limit=100`)
      .then(data => {
        const threads = Array.isArray(data.items) ? data.items : [];
        if (!threads.length) {
          list.innerHTML = '<p class="text-muted p-3 mb-0">В этом ящике пока нет писем. Выполните синхронизацию.</p>';
          return;
        }
        list.innerHTML = threads.map(thread => `
          <button type="button" class="cmail-thread-item${thread.thread_id === activeThread ? ' active' : ''}" data-thread="${esc(thread.thread_id)}">
            <span class="cmail-thread-subject">${esc(thread.subject || '(без темы)')}</span>
            <span class="cmail-thread-meta">${esc(thread.sender_name || thread.sender_email)} · ${esc(thread.sent_at)} · ${esc(thread.message_count)} пис.</span>
          </button>`).join('');
        list.querySelectorAll('.cmail-thread-item').forEach(button => button.addEventListener('click', () => openThread(button.getAttribute('data-thread'))));
      })
      .catch(error => { list.innerHTML = `<p class="text-danger p-3 mb-0">Не удалось загрузить переписки: ${esc(error.message)}</p>`; });
  };

  const openThread = (threadId) => {
    if (!threadId || !activeMailbox) return;
    activeThread = threadId;
    const pendingSend = pendingSends.get(`${activeMailbox}:${activeThread}`) || null;
    activeSendKey = pendingSend?.idempotency_key || '';
    const list = document.getElementById('cmailMessageList');
    const replyForm = document.getElementById('cmailReplyForm');
    if (replyForm) replyForm.hidden = true;
    document.getElementById('cmailReplySubject').disabled = !!activeSendKey;
    document.getElementById('cmailReplyBody').disabled = !!activeSendKey;
    document.getElementById('cmailReplySend').disabled = !!activeSendKey;
    document.getElementById('cmailReplyCheckStatus').hidden = !activeSendKey;
    if (list) list.innerHTML = '<p class="text-muted p-3 mb-0">Загружаем письма…</p>';
    apiRequest(`${MODULE_ROUTE}/mailboxes/${encodeURIComponent(activeMailbox)}/messages?thread_id=${encodeURIComponent(threadId)}&limit=100`)
      .then(data => {
        activeMessages = Array.isArray(data.items) ? data.items : [];
        if (!activeMessages.length) {
          list.innerHTML = '<p class="text-muted p-3 mb-0">Переписка не найдена или больше недоступна.</p>';
          return;
        }
        list.innerHTML = activeMessages.map(message => `
          <article class="cmail-message ${message.direction === 'outbound' ? 'outbound' : 'inbound'}">
            <header><strong>${esc(message.sender_name || message.sender_email)}</strong><span>${esc(message.sent_at)}</span></header>
            <div class="small text-muted">Кому: ${esc(message.recipient_email)}</div>
            <h3>${esc(message.subject || '(без темы)')}</h3>
            <pre>${esc(message.body_text || '')}</pre>
          </article>`).join('');
        const latest = activeMessages[activeMessages.length - 1];
        const candidates = String(latest.direction === 'inbound' ? latest.sender_email : latest.recipient_email || '').split(/[,;\s]+/).filter(Boolean);
        const recipient = candidates.find(email => email.toLowerCase() !== activeMailboxEmail.toLowerCase()) || '';
        const subject = String(latest.subject || '').trim();
        document.getElementById('cmailReplyRecipient').textContent = pendingSend ? 'Для этой переписки сохранён незавершённый запрос. Проверьте статус перед повтором.' : (recipient ? `Ответ будет отправлен: ${recipient}` : 'Не удалось определить адрес для ответа.');
        document.getElementById('cmailReplySubject').value = pendingSend?.subject || (/^re\s*:/i.test(subject) ? subject : `Re: ${subject}`.trim());
        document.getElementById('cmailReplyBody').value = pendingSend?.body || '';
        if (pendingSend?.persisted) document.getElementById('cmailReplyBody').placeholder = 'После подтверждённого статуса «не отправлено» введите исходный текст без изменений.';
        else document.getElementById('cmailReplyBody').placeholder = 'Введите текст ответа…';
        document.getElementById('cmailReplySubject').disabled = !!pendingSend;
        document.getElementById('cmailReplyBody').disabled = !!pendingSend;
        document.getElementById('cmailReplyForm').dataset.recipient = recipient;
        document.getElementById('cmailReplyForm').dataset.replyTo = latest.message_id_header || '';
        if (pendingSend?.persisted) document.getElementById('cmailReplySubject').disabled = true;
        document.getElementById('cmailReplyForm').hidden = !recipient || !latest.message_id_header;
        document.getElementById('cmailReplySend').disabled = !!pendingSend || !recipient || !latest.message_id_header;
        document.getElementById('cmailReplyStatus').textContent = pendingSend ? 'Для этой переписки есть незавершённая отправка. Сначала проверьте её статус.' : '';
        document.getElementById('cmailReplyCheckStatus').hidden = !pendingSend;
        list?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      })
      .catch(error => { list.innerHTML = `<p class="text-danger p-3 mb-0">Не удалось загрузить переписку: ${esc(error.message)}</p>`; });
  };

  const openAddModal = () => {
    document.getElementById('cmailModalLabel').textContent = 'Добавить корпоративный ящик';
    document.getElementById('cmailFieldPublicId').value = '';
    document.getElementById('cmailFieldEmail').value = '';
    document.getElementById('cmailFieldName').value = '';
    document.getElementById('cmailFieldImapHost').value = '';
    document.getElementById('cmailFieldImapPort').value = 993;
    document.getElementById('cmailFieldImapEnc').value = 'ssl';
    document.getElementById('cmailFieldImapUser').value = '';
    document.getElementById('cmailFieldImapPass').value = '';
    document.getElementById('cmailFieldSmtpHost').value = '';
    document.getElementById('cmailFieldSmtpPort').value = 587;
    document.getElementById('cmailFieldSmtpEnc').value = 'tls';
    document.getElementById('cmailFieldSmtpUser').value = '';
    document.getElementById('cmailFieldSmtpPass').value = '';

    const feedback = document.getElementById('cmailModalFeedback');
    if (feedback) { feedback.style.display = 'none'; feedback.innerHTML = ''; }

    getModal()?.show();
  };

  const openEditModal = (pubId) => {
    document.getElementById('cmailModalLabel').textContent = 'Редактировать почтовый ящик';
    const feedback = document.getElementById('cmailModalFeedback');
    if (feedback) { feedback.style.display = 'none'; feedback.innerHTML = ''; }

    apiRequest(`${MODULE_ROUTE}/mailboxes/${encodeURIComponent(pubId)}`)
      .then(m => {
        document.getElementById('cmailFieldPublicId').value = m.public_id || '';
        document.getElementById('cmailFieldEmail').value = m.email_address || '';
        document.getElementById('cmailFieldName').value = m.display_name || '';

        const auth = m.auth_config || {};
        const imap = auth.imap || {};
        const smtp = auth.smtp || {};

        document.getElementById('cmailFieldImapHost').value = imap.host || '';
        document.getElementById('cmailFieldImapPort').value = imap.port || 993;
        document.getElementById('cmailFieldImapEnc').value = imap.encryption || 'ssl';
        document.getElementById('cmailFieldImapUser').value = imap.username || '';
        document.getElementById('cmailFieldImapPass').value = m.has_imap_password ? '••••••••' : '';

        document.getElementById('cmailFieldSmtpHost').value = smtp.host || '';
        document.getElementById('cmailFieldSmtpPort').value = smtp.port || 587;
        document.getElementById('cmailFieldSmtpEnc').value = smtp.encryption || 'tls';
        document.getElementById('cmailFieldSmtpUser').value = smtp.username || '';
        document.getElementById('cmailFieldSmtpPass').value = m.has_smtp_password ? '••••••••' : '';

        getModal()?.show();
      })
      .catch(err => {
        showAlert('Ошибка получения данных ящика: ' + err.message, 'danger');
      });
  };

  const testMailbox = (pubId, btn) => {
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

    apiRequest(`${MODULE_ROUTE}/mailboxes/${encodeURIComponent(pubId)}/test`, { method: 'POST' })
      .then(res => {
        if (res.ok) {
          showAlert(`Соединение проверено: ${res.message}`, 'success');
        } else {
          showAlert(`Ошибка соединения: ${res.message}`, 'danger');
        }
      })
      .catch(err => {
        showAlert('Ошибка проверки: ' + err.message, 'danger');
      })
      .finally(() => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
      });
  };

  const syncMailbox = (pubId, btn) => {
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

    apiRequest(`${MODULE_ROUTE}/mailboxes/${encodeURIComponent(pubId)}/sync`, { method: 'POST' })
      .then(res => {
        showAlert(res.message || 'Синхронизация завершена успешно', Number(res.failed_count || 0) > 0 ? 'warning' : 'success');
        loadMailboxes();
        loadStatus();
      })
      .catch(err => {
        showAlert('Ошибка синхронизации: ' + err.message, 'danger');
      })
      .finally(() => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
      });
  };

  const loadSyncFailures = (pubId) => {
    const panel = document.getElementById('cmailFailuresPanel');
    const tbody = document.getElementById('cmailFailuresBody');
    if (!panel || !tbody) return;
    panel.hidden = false;
    panel.dataset.mailbox = pubId;
    tbody.innerHTML = '<tr><td colspan="6" class="text-muted text-center">Загрузка проблемных UID…</td></tr>';
    apiRequest(`${MODULE_ROUTE}/mailboxes/${encodeURIComponent(pubId)}/sync-failures`)
      .then(res => {
        const rows = res.items || [];
        if (!rows.length) { tbody.innerHTML = '<tr><td colspan="6" class="text-muted text-center">Проблемных писем нет.</td></tr>'; return; }
        tbody.innerHTML = rows.map(item => `<tr><td>${esc(item.uid)}</td><td>${esc(item.uidvalidity)}</td><td>${esc(item.error_code)}</td><td>${esc(item.attempts)}</td><td>${esc(item.updated_at)}</td><td><button class="btn btn-sm crm-btn-secondary cmail-btn-retry-uid" type="button" data-uid="${esc(item.uid)}" data-validity="${esc(item.uidvalidity)}">Повторить</button></td></tr>`).join('');
        tbody.querySelectorAll('.cmail-btn-retry-uid').forEach(btn => btn.addEventListener('click', () => {
          btn.disabled = true;
          apiRequest(`${MODULE_ROUTE}/mailboxes/${encodeURIComponent(pubId)}/sync-failures/retry`, { method: 'POST', body: { uid: Number(btn.dataset.uid), uidvalidity: Number(btn.dataset.validity) } })
            .then(result => { showAlert(result.message || 'UID обработан.', 'success'); loadSyncFailures(pubId); loadMailboxes(); })
            .catch(err => showAlert(err.message || 'Повтор UID не выполнен.', 'danger'))
            .finally(() => { btn.disabled = false; });
        }));
      })
      .catch(err => { tbody.innerHTML = `<tr><td colspan="6" class="text-danger">Не удалось загрузить список: ${esc(err.message)}</td></tr>`; });
  };
  document.getElementById('cmailFailuresClose')?.addEventListener('click', () => { document.getElementById('cmailFailuresPanel').hidden = true; });

  const deleteMailbox = (pubId, btn) => {
    if (!confirm('Вы действительно хотите удалить этот почтовый ящик?')) {
      return;
    }
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

    apiRequest(`${MODULE_ROUTE}/mailboxes/${encodeURIComponent(pubId)}`, { method: 'DELETE' })
      .then(() => {
        showAlert('Почтовый ящик успешно удалён', 'success');
        loadMailboxes();
        loadStatus();
      })
      .catch(err => {
        showAlert('Ошибка удаления: ' + err.message, 'danger');
        btn.disabled = false;
        btn.innerHTML = origHtml;
      });
  };

  const importEnv = () => {
    const btn = document.getElementById('cmailImportEnvBtn');
    if (btn) btn.disabled = true;

    apiRequest(`${MODULE_ROUTE}/import-env`, { method: 'POST' })
      .then(res => {
        showAlert('Почтовый ящик успешно импортирован из .env!', 'success');
        loadMailboxes();
        loadStatus();
      })
      .catch(err => {
        showAlert('Ошибка импорта из .env: ' + err.message, 'danger');
      })
      .finally(() => {
        if (btn) btn.disabled = false;
      });
  };

  // Form submit handler
  document.getElementById('cmailModalForm')?.addEventListener('submit', (e) => {
    e.preventDefault();
    const submitBtn = document.getElementById('cmailModalSubmitBtn');
    if (submitBtn) submitBtn.disabled = true;

    const pubId = document.getElementById('cmailFieldPublicId').value.trim();
    const payload = {
      public_id: pubId || undefined,
      email_address: document.getElementById('cmailFieldEmail').value.trim(),
      display_name: document.getElementById('cmailFieldName').value.trim(),
      protocol_adapter: document.getElementById('cmailFieldAdapter').value,
      auth_config: {
        imap: {
          host: document.getElementById('cmailFieldImapHost').value.trim(),
          port: parseInt(document.getElementById('cmailFieldImapPort').value, 10) || 993,
          encryption: document.getElementById('cmailFieldImapEnc').value,
          username: document.getElementById('cmailFieldImapUser').value.trim(),
          password: document.getElementById('cmailFieldImapPass').value,
        },
        smtp: {
          host: document.getElementById('cmailFieldSmtpHost').value.trim(),
          port: parseInt(document.getElementById('cmailFieldSmtpPort').value, 10) || 587,
          encryption: document.getElementById('cmailFieldSmtpEnc').value,
          username: document.getElementById('cmailFieldSmtpUser').value.trim(),
          password: document.getElementById('cmailFieldSmtpPass').value,
          from_address: document.getElementById('cmailFieldEmail').value.trim(),
        }
      }
    };

    apiRequest(`${MODULE_ROUTE}/mailboxes`, { method: 'POST', body: payload })
      .then(() => {
        getModal()?.hide();
        showAlert('Почтовый ящик успешно сохранён', 'success');
        loadMailboxes();
        loadStatus();
      })
      .catch(err => {
        const feedback = document.getElementById('cmailModalFeedback');
        if (feedback) {
          feedback.style.display = 'block';
          feedback.innerHTML = `<div class="alert alert-danger mb-0">${esc(err.message)}</div>`;
        }
      })
      .finally(() => {
        if (submitBtn) submitBtn.disabled = false;
      });
  });

  // Modal connection test button
  document.getElementById('cmailModalTestBtn')?.addEventListener('click', () => {
    const testBtn = document.getElementById('cmailModalTestBtn');
    const feedback = document.getElementById('cmailModalFeedback');
    if (testBtn) testBtn.disabled = true;
    if (feedback) {
      feedback.style.display = 'block';
      feedback.innerHTML = '<div class="alert alert-info mb-0"><span class="spinner-border spinner-border-sm me-2"></span>Проверка соединения с серверами...</div>';
    }

    const payload = {
      imap: {
        host: document.getElementById('cmailFieldImapHost').value.trim(),
        port: parseInt(document.getElementById('cmailFieldImapPort').value, 10) || 993,
        encryption: document.getElementById('cmailFieldImapEnc').value,
      },
      smtp: {
        host: document.getElementById('cmailFieldSmtpHost').value.trim(),
        port: parseInt(document.getElementById('cmailFieldSmtpPort').value, 10) || 587,
        encryption: document.getElementById('cmailFieldSmtpEnc').value,
      }
    };

    apiRequest(`${MODULE_ROUTE}/test-connection`, { method: 'POST', body: payload })
      .then(res => {
        if (feedback) {
          const type = res.ok ? 'success' : 'warning';
          feedback.innerHTML = `<div class="alert alert-${type} mb-0">${esc(res.message || 'Проверка выполнена')}</div>`;
        }
      })
      .catch(err => {
        if (feedback) {
          feedback.innerHTML = `<div class="alert alert-danger mb-0">Ошибка проверки: ${esc(err.message)}</div>`;
        }
      })
      .finally(() => {
        if (testBtn) testBtn.disabled = false;
      });
  });

  // Top toolbar buttons
  document.getElementById('cmailInboxRefresh')?.addEventListener('click', loadThreads);
  document.getElementById('cmailInboxClose')?.addEventListener('click', () => {
    const panel = document.getElementById('cmailInboxPanel');
    if (panel) panel.hidden = true;
  });
  document.getElementById('cmailReplyForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const sendButton = document.getElementById('cmailReplySend');
    const status = document.getElementById('cmailReplyStatus');
    const recipient = form.dataset.recipient || '';
    const replyTo = form.dataset.replyTo || '';
    const body = document.getElementById('cmailReplyBody').value;
    if (!activeMailbox || !recipient || !replyTo || !body.trim()) return;
    const slot = `${activeMailbox}:${activeThread}`;
    let requestPayload = pendingSends.get(slot);
    if (!requestPayload || requestPayload.persisted) {
      const bytes = new Uint8Array(24);
      window.crypto.getRandomValues(bytes);
      activeSendKey = requestPayload?.idempotency_key || Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
      requestPayload = {
        to: [recipient],
        subject: document.getElementById('cmailReplySubject').value,
        body,
        in_reply_to: replyTo,
        idempotency_key: activeSendKey
      };
      pendingSends.set(slot, requestPayload);
      if (!pendingStorageHealthy || !persistPendingSends()) {
        status.textContent = 'Браузер не смог безопасно сохранить ключ повторной отправки. Письмо не отправлено; проверьте хранилище браузера и повторите попытку.';
        sendButton.disabled = false;
        return;
      }
    } else {
      activeSendKey = requestPayload.idempotency_key;
    }
    sendButton.disabled = true;
    document.getElementById('cmailReplyCheckStatus').hidden = true;
    status.textContent = 'Отправляем письмо…';
    try {
      const result = await apiRequest(`${MODULE_ROUTE}/mailboxes/${encodeURIComponent(activeMailbox)}/send`, {
        method: 'POST',
        body: requestPayload
      });
      status.textContent = result.message || 'Письмо отправлено.';
      clearPendingSend(slot);
      activeSendKey = '';
      await loadThreads();
      openThread(activeThread);
    } catch (error) {
      status.textContent = `${error.message} Проверьте статус перед повторной отправкой.`;
      document.getElementById('cmailReplySubject').disabled = true;
      document.getElementById('cmailReplyBody').disabled = true;
      document.getElementById('cmailReplyCheckStatus').hidden = false;
    } finally {
      if (!activeSendKey) sendButton.disabled = false;
    }
  });
  document.getElementById('cmailReplyCheckStatus')?.addEventListener('click', async () => {
    if (!activeMailbox || !activeSendKey) return;
    const status = document.getElementById('cmailReplyStatus');
    const check = document.getElementById('cmailReplyCheckStatus');
    check.disabled = true;
    try {
      const result = await apiRequest(`${MODULE_ROUTE}/mailboxes/${encodeURIComponent(activeMailbox)}/send-status?idempotency_key=${encodeURIComponent(activeSendKey)}`);
      status.textContent = `Статус доставки: ${result.status || 'неизвестен'}.`;
      if (result.status === 'sent') {
        clearPendingSend(`${activeMailbox}:${activeThread}`);
        activeSendKey = '';
        document.getElementById('cmailReplySend').disabled = true;
        check.hidden = true;
        await loadThreads();
        openThread(activeThread);
      } else if (result.status === 'failed') {
        clearPendingSend(`${activeMailbox}:${activeThread}`);
        activeSendKey = '';
        document.getElementById('cmailReplySubject').disabled = false;
        document.getElementById('cmailReplyBody').disabled = false;
        document.getElementById('cmailReplySend').disabled = false;
        status.textContent = 'Сервер подтвердил, что письмо не отправлено. Исправьте или заново введите ответ и отправьте его как новую попытку.';
        check.hidden = true;
      }
    } catch (error) {
      if (error.code === 'NOT_FOUND') {
        clearPendingSend(`${activeMailbox}:${activeThread}`);
        activeSendKey = '';
        document.getElementById('cmailReplySubject').disabled = false;
        document.getElementById('cmailReplyBody').disabled = false;
        document.getElementById('cmailReplySend').disabled = false;
        check.hidden = true;
        status.textContent = 'Попытка отправки не найдена: можно проверить адрес и создать новый ответ.';
      } else {
        status.textContent = `Статус пока не получен: ${error.message}. Не отправляйте это письмо с новым ключом.`;
      }
    } finally {
      check.disabled = false;
    }
  });
  document.getElementById('cmailAddMailboxBtn')?.addEventListener('click', openAddModal);
  document.getElementById('cmailImportEnvBtn')?.addEventListener('click', importEnv);
  document.getElementById('cmailRefreshBtn')?.addEventListener('click', () => {
    loadStatus();
    loadMailboxes();
    if (activeMailbox) loadThreads();
  });

  // Initial load
  loadStatus();
  loadMailboxes();
})();
