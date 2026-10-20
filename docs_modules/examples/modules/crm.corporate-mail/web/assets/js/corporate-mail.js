(() => {
  'use strict';

  if (!document.querySelector('[data-page="module-corporate-mail"]')) {
    return;
  }

  const MODULE_ROUTE = '_module/crm.corporate-mail';

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
        throw new Error(data.message || data.code || 'Ошибка запроса к серверу');
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

    if (status.status === 'connected') {
      banner.innerHTML = `
        <div class="alert alert-success d-flex align-items-center justify-content-between p-3" role="status">
          <div class="d-flex align-items-center">
            <i class="fa-solid fa-circle-check fs-4 text-success me-3" aria-hidden="true"></i>
            <div>
              <strong class="fs-6">Подключён · Активен</strong>
              <div class="small">Активных ящиков: <strong>${status.active_mailboxes_count}</strong>. Почтовый сервис принимает и отправляет письма в штатном режиме.</div>
            </div>
          </div>
          ${status.last_synced_at ? `<span class="badge bg-success-subtle text-success border border-success-subtle">Посл. проверка: ${esc(status.last_synced_at)}</span>` : ''}
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
              <div class="small">Почтовые ящики пока не добавлены. Добавьте первый корпоративный ящик для приёма и отправки писем команды.</div>
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
                  <button type="button" class="btn crm-btn-secondary cmail-btn-sync" data-id="${esc(m.public_id)}" title="Синхронизировать сейчас">
                    <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                  </button>
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
        tbody.querySelectorAll('.cmail-btn-sync').forEach(btn => {
          btn.addEventListener('click', () => syncMailbox(btn.getAttribute('data-id'), btn));
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
        showAlert(res.message || 'Синхронизация завершена успешно', 'success');
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
  document.getElementById('cmailAddMailboxBtn')?.addEventListener('click', openAddModal);
  document.getElementById('cmailImportEnvBtn')?.addEventListener('click', importEnv);
  document.getElementById('cmailRefreshBtn')?.addEventListener('click', () => {
    loadStatus();
    loadMailboxes();
  });

  // Initial load
  loadStatus();
  loadMailboxes();
})();
