/**
 * Intake Items management page.
 * Auto-initializes on pages with data-page="intake"
 */
window.CRM = window.CRM || {};
window.CRM.intake = (function () {
  'use strict';

  var state = {
    items: [],
    projects: [],
    clients: [],
    users: [],
    editingId: null,
    filters: {}
  };

  function t(key, fallback) {
    if (window.CRM.i18n && typeof window.CRM.i18n.t === 'function') {
      return window.CRM.i18n.t(key, fallback);
    }
    return fallback || key;
  }

  function esc(value) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(String(value == null ? '' : value)));
    return d.innerHTML;
  }

  function getApi() {
    return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
  }

  function waitForApi(cb, n) {
    if (getApi()) { cb(); return; }
    if ((n || 0) > 80) return;
    setTimeout(function () { waitForApi(cb, (n || 0) + 1); }, 50);
  }

  function notify(text, type) {
    if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.notify === 'function') {
      window.CRM.pageApiBindings.notify(text, type);
      return;
    }
    if (typeof window.notify === 'function') {
      window.notify(text, type);
      return;
    }
    console.log('[intake]', type, text);
  }

  function req(route, opts) {
    var api = getApi();
    if (!api) return Promise.reject(new Error('API not ready'));
    return api.request(route, opts || {});
  }

  // ---- Status helpers ----

  function statusLabel(status) {
    var map = {
      pending: t('intake.status_pending', 'Ожидает'),
      snoozed: t('intake.status_snoozed', 'Отложено'),
      accepted: t('intake.status_accepted', 'Принято'),
      rejected: t('intake.status_rejected', 'Отклонено'),
      duplicate: t('intake.status_duplicate', 'Дубликат')
    };
    return map[status] || status;
  }

  function statusBadgeHtml(status) {
    return '<span class="crm-intake-status-badge" data-status="' + esc(status) + '">' + esc(statusLabel(status)) + '</span>';
  }

  function priorityLabel(code) {
    var map = {
      low: t('intake.priority_low', 'Низкий'),
      normal: t('intake.priority_normal', 'Средний'),
      high: t('intake.priority_high', 'Высокий'),
      urgent: t('intake.priority_urgent', 'Срочный')
    };
    return map[code] || code;
  }

  function priorityBadgeHtml(code) {
    return '<span class="crm-intake-priority-badge" data-priority="' + esc(code) + '">' + esc(priorityLabel(code)) + '</span>';
  }

  function sourceLabel(source) {
    var map = {
      manual: t('intake.source_manual', 'Вручную'),
      email: t('intake.source_email', 'Email'),
      api: t('intake.source_api', 'API'),
      webhook: t('intake.source_webhook', 'Webhook'),
      ai: t('intake.source_ai', 'AI'),
      import: t('intake.source_import', 'Импорт'),
      system: t('intake.source_system', 'Система')
    };
    return map[source] || source;
  }

  // ---- Data loading ----

  function loadProjects() {
    req('api/v1/projects', { query: { limit: 200, archived: 0 } })
      .then(function (envelope) {
        state.projects = (envelope.data && envelope.data.items) || [];
        populateProjectSelects();
        populateAcceptProjectSelect();
      })
      .catch(function () {});
  }

  function loadUsers() {
    req('api/v1/users', { query: { limit: 200 } })
      .then(function (envelope) {
        state.users = (envelope.data && envelope.data.items) || [];
        populateUserSelects();
      })
      .catch(function () {});
  }

  function loadClients() {
    req('api/v1/counterparties', { query: { limit: 200 } })
      .then(function (envelope) {
        state.clients = (envelope.data && envelope.data.items) || [];
        populateClientSelects();
      })
      .catch(function () {});
  }

  function populateProjectSelects() {
    var selects = ['#intakeProject'];
    selects.forEach(function (sel) {
      var el = document.querySelector(sel);
      if (!el) return;
      var currentVal = el.value;
      el.innerHTML = '<option value="">' + esc(t('intake.option_no_project', 'Не выбран')) + '</option>';
      state.projects.forEach(function (p) {
        var opt = document.createElement('option');
        opt.value = p.public_id;
        opt.textContent = p.title || p.public_id;
        el.appendChild(opt);
      });
      el.value = currentVal;
    });
  }

  function populateAcceptProjectSelect() {
    var el = document.getElementById('intakeAcceptProject');
    if (!el) return;
    el.innerHTML = '<option value="">' + esc(t('intake.option_no_project', 'Не выбран')) + '</option>';
    state.projects.forEach(function (p) {
      var opt = document.createElement('option');
      opt.value = p.public_id;
      opt.textContent = p.title || p.public_id;
      el.appendChild(opt);
    });
  }

  function populateUserSelects() {
    var el = document.getElementById('intakeAssign');
    if (!el) return;
    var currentVal = el.value;
    el.innerHTML = '<option value="">' + esc(t('intake.option_no_assignee', 'Не назначен')) + '</option>';
    state.users.forEach(function (u) {
      var opt = document.createElement('option');
      opt.value = u.public_id;
      opt.textContent = u.full_name || u.login || u.public_id;
      el.appendChild(opt);
    });
    el.value = currentVal;
  }

  function populateClientSelects() {
    var el = document.getElementById('intakeClient');
    if (!el) return;
    var currentVal = el.value;
    el.innerHTML = '<option value="">' + esc(t('intake.option_no_client', 'Не выбран')) + '</option>';
    state.clients.forEach(function (c) {
      var opt = document.createElement('option');
      opt.value = c.public_id;
      opt.textContent = c.full_name || c.title || c.public_id;
      el.appendChild(opt);
    });
    el.value = currentVal;
  }

  // ---- Load & render items ----

  function getFilterState() {
    var statusEl = document.getElementById('intakeStatusFilter');
    var sourceEl = document.getElementById('intakeSourceFilter');
    var filters = {};
    if (statusEl && statusEl.value) filters.status = statusEl.value;
    if (sourceEl && sourceEl.value) filters.source_type = sourceEl.value;
    return filters;
  }

  function loadItems() {
    var body = document.getElementById('intakeBody');
    if (!body) return;
    body.innerHTML = '<tr><td colspan="11" class="text-muted">' + esc(t('page.loading', 'Загрузка...')) + '</td></tr>';

    var filters = getFilterState();
    var query = { limit: 50 };
    if (filters.status) query.status = filters.status;
    if (filters.source_type) query.source_type = filters.source_type;

    req('api/v1/intake-items', { query: query })
      .then(function (envelope) {
        state.items = (envelope.data && envelope.data.items) || [];
        var total = envelope.data && envelope.data.total != null ? envelope.data.total : state.items.length;
        var countEl = document.getElementById('intakeTotalCount');
        if (countEl) countEl.textContent = total;
        renderItems();
      })
      .catch(function () {
        body.innerHTML = '<tr><td colspan="11" class="text-muted">' + esc(t('intake.load_error', 'Ошибка загрузки заявок')) + '</td></tr>';
      });
  }

  function renderItems() {
    var body = document.getElementById('intakeBody');
    if (!body) return;

    var items = state.items;
    if (!items.length) {
      body.innerHTML = '<tr><td colspan="11"><div class="crm-intake-empty">'
        + '<i class="fa-solid fa-inbox"></i>'
        + '<h5>' + esc(t('intake.no_items_title', 'Заявок пока нет')) + '</h5>'
        + '<p>' + esc(t('intake.no_items', 'Создайте первую заявку или настройте автоматический сбор входящих.')) + '</p>'
        + '<button class="btn btn-sm crm-btn-primary intake-create-empty-btn" type="button">'
        + '<i class="fa-solid fa-plus"></i> ' + esc(t('intake.create_btn', 'Создать заявку'))
        + '</button>'
        + '</div></td></tr>';
      var emptyBtn = body.querySelector('.intake-create-empty-btn');
      if (emptyBtn) {
        emptyBtn.addEventListener('click', function () {
          var createBtn = document.getElementById('intakeCreateBtn');
          if (createBtn) createBtn.click();
        });
      }
      return;
    }

    body.innerHTML = items.map(function (item) {
      var assigneeName = item.assignee_name || '';
      var projectTitle = item.project_title || '';
      var clientName = item.client_name || '';
      var dueStr = item.due_at ? item.due_at.slice(0, 10) : '';
      var createdStr = item.created_at ? item.created_at.slice(0, 10) : '';
      var publicId = item.public_id || '';
      var isPending = item.status === 'pending';
      var isSnoozed = item.status === 'snoozed';
      var isAccepted = item.status === 'accepted';
      var isRejected = item.status === 'rejected';
      var isDuplicate = item.status === 'duplicate';
      var isActive = isPending || isSnoozed;

      return '<tr>'
        + '<td class="crm-intake-id-cell"><code>' + esc(publicId.slice(0, 8)) + '</code></td>'
        + '<td class="crm-intake-title-cell">'
          + '<strong>' + esc(item.title) + '</strong>'
          + (item.description ? '<small>' + esc(item.description.slice(0, 80)) + '</small>' : '')
        + '</td>'
        + '<td>' + statusBadgeHtml(item.status) + '</td>'
        + '<td>' + priorityBadgeHtml(item.priority_code || 'normal') + '</td>'
        + '<td>' + '<span class="crm-intake-source-label">' + esc(sourceLabel(item.source_type)) + '</span>' + '</td>'
        + '<td>' + esc(projectTitle || '—') + '</td>'
        + '<td>' + esc(clientName || '—') + '</td>'
        + '<td>' + esc(assigneeName || '—') + '</td>'
        + '<td class="crm-intake-date-cell">' + esc(dueStr || '—') + '</td>'
        + '<td class="crm-intake-date-cell">' + esc(createdStr || '—') + '</td>'
        + '<td class="crm-intake-actions">'
        + actionButtons(item)
        + '</td></tr>';
    }).join('');

    bindTableEvents();
  }

  function actionButtons(item) {
    var id = esc(item.public_id);
    var editBtn = '<button class="btn btn-sm crm-btn-secondary intake-edit-btn" data-intake-id="' + id + '" title="' + esc(t('page.edit', 'Edit')) + '"><i class="fa-solid fa-pen"></i></button>';
    var actBtn = '<button class="btn btn-sm crm-btn-secondary intake-activities-btn" data-intake-id="' + id + '" title="' + esc(t('intake.activities_btn', 'History')) + '"><i class="fa-solid fa-clock-rotate-left"></i></button>';
    var delBtn = '<button class="btn btn-sm crm-btn-secondary intake-delete-btn" data-intake-id="' + id + '" title="' + esc(t('page.delete', 'Delete')) + '"><i class="fa-solid fa-trash-can"></i></button>';

    var statusBtns = '';
    if (item.status === 'pending' || item.status === 'snoozed') {
      statusBtns += '<button class="btn btn-sm intake-accept-btn" data-intake-id="' + id + '" title="' + esc(t('intake.accept_btn', 'Accept')) + '"><i class="fa-solid fa-check"></i></button> ';
      statusBtns += '<button class="btn btn-sm crm-btn-secondary intake-reject-btn" data-intake-id="' + id + '" title="' + esc(t('intake.reject_btn', 'Reject')) + '"><i class="fa-solid fa-xmark"></i></button> ';
      statusBtns += '<button class="btn btn-sm crm-btn-secondary intake-snooze-btn" data-intake-id="' + id + '" title="' + esc(t('intake.snooze_btn', 'Snooze')) + '"><i class="fa-solid fa-clock"></i></button> ';
      statusBtns += '<button class="btn btn-sm crm-btn-secondary intake-duplicate-btn" data-intake-id="' + id + '" title="' + esc(t('intake.duplicate_btn', 'Duplicate')) + '"><i class="fa-solid fa-copy"></i></button> ';
    }
    if (item.status === 'rejected' || item.status === 'duplicate') {
      statusBtns += '<button class="btn btn-sm crm-btn-secondary intake-reopen-btn" data-intake-id="' + id + '" title="' + esc(t('intake.reopen_btn', 'Reopen')) + '"><i class="fa-solid fa-rotate-left"></i></button> ';
    }
    if (item.accepted_task_id) {
      statusBtns += '<a class="btn btn-sm crm-btn-secondary" href="index.php?route=task-detail&task_public_id=' + esc(item.accepted_task_id) + '" title="' + esc(t('intake.view_task_btn', 'View task')) + '"><i class="fa-solid fa-arrow-up-right-from-square"></i></a> ';
    }

    return editBtn + actBtn + delBtn + statusBtns;
  }

  function bindTableEvents() {
    document.querySelectorAll('.intake-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openEditModal(btn.getAttribute('data-intake-id'));
      });
    });
    document.querySelectorAll('.intake-delete-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openDeleteModal(btn.getAttribute('data-intake-id'));
      });
    });
    document.querySelectorAll('.intake-activities-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openActivitiesModal(btn.getAttribute('data-intake-id'));
      });
    });
    document.querySelectorAll('.intake-accept-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openAcceptModal(btn.getAttribute('data-intake-id'));
      });
    });
    document.querySelectorAll('.intake-reject-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openRejectModal(btn.getAttribute('data-intake-id'));
      });
    });
    document.querySelectorAll('.intake-snooze-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openSnoozeModal(btn.getAttribute('data-intake-id'));
      });
    });
    document.querySelectorAll('.intake-duplicate-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openDuplicateModal(btn.getAttribute('data-intake-id'));
      });
    });
    document.querySelectorAll('.intake-reopen-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openReopenModal(btn.getAttribute('data-intake-id'));
      });
    });
  }

  // ---- Create / Edit Modal ----

  function openCreateModal() {
    state.editingId = null;
    document.getElementById('intakeModalTitle').textContent = t('intake.modal_create_title', 'Создать заявку');
    document.getElementById('intakePublicId').value = '';
    document.getElementById('intakeRowVersion').value = '';
    document.getElementById('intakeTitle').value = '';
    document.getElementById('intakeDescription').value = '';
    document.getElementById('intakePriority').value = 'normal';
    document.getElementById('intakeSource').value = 'manual';
    document.getElementById('intakeProject').value = '';
    document.getElementById('intakeClient').value = '';
    document.getElementById('intakeAssign').value = '';
    document.getElementById('intakeDueAt').value = '';
    document.getElementById('intakeSourceRef').value = '';

    var modal = new bootstrap.Modal(document.getElementById('intakeModal'));
    modal.show();
  }

  function openEditModal(id) {
    var item = state.items.find(function (m) { return m.public_id === id; });
    if (!item) return;
    state.editingId = id;

    document.getElementById('intakeModalTitle').textContent = t('intake.modal_edit_title', 'Редактировать заявку');
    document.getElementById('intakePublicId').value = item.public_id || '';
    document.getElementById('intakeRowVersion').value = item.row_version || '';
    document.getElementById('intakeTitle').value = item.title || '';
    document.getElementById('intakeDescription').value = item.description || '';
    document.getElementById('intakePriority').value = item.priority_code || 'normal';
    document.getElementById('intakeSource').value = item.source_type || 'manual';
    document.getElementById('intakeProject').value = item.project_public_id || '';
    document.getElementById('intakeClient').value = item.client_public_id || '';
    document.getElementById('intakeAssign').value = item.assignee_user_public_id || '';
    document.getElementById('intakeDueAt').value = item.due_at ? item.due_at.slice(0, 10) : '';
    document.getElementById('intakeSourceRef').value = item.source_ref || '';

    var modal = new bootstrap.Modal(document.getElementById('intakeModal'));
    modal.show();
  }

  function handleSave(e) {
    e.preventDefault();

    var title = document.getElementById('intakeTitle').value.trim();
    if (!title) {
      notify(t('intake.error_title_required', 'Введите название заявки'), 'error');
      return;
    }

    var payload = {
      title: title,
      description: document.getElementById('intakeDescription').value.trim() || undefined,
      priority_code: document.getElementById('intakePriority').value,
      source_type: document.getElementById('intakeSource').value,
      project_public_id: document.getElementById('intakeProject').value || undefined,
      client_public_id: document.getElementById('intakeClient').value || undefined,
      assignee_user_public_id: document.getElementById('intakeAssign').value || undefined,
      due_at: document.getElementById('intakeDueAt').value || undefined,
      source_ref: document.getElementById('intakeSourceRef').value.trim() || undefined
    };

    var publicId = document.getElementById('intakePublicId').value;
    var isEdit = !!publicId;

    if (isEdit) {
      payload.row_version = document.getElementById('intakeRowVersion').value || undefined;
    }

    waitForApi(function () {
      var method = isEdit ? 'PATCH' : 'POST';
      var url = isEdit ? 'api/v1/intake-items/' + encodeURIComponent(publicId) : 'api/v1/intake-items';

      req(url, { method: method, body: payload })
        .then(function (resp) {
          if (resp && resp.success) {
            notify(isEdit
              ? t('intake.updated', 'Заявка обновлена')
              : t('intake.created', 'Заявка создана'), 'success');
            var modalEl = document.getElementById('intakeModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            loadItems();
          } else {
            var msg = (resp && resp.message) || t('intake.save_error', 'Ошибка сохранения заявки');
            notify(msg, 'error');
          }
        })
        .catch(function (err) {
          var msg = t('intake.save_error', 'Ошибка сохранения заявки');
          if (err && err.message) msg = err.message;
          notify(msg, 'error');
        });
    });
  }

  // ---- Delete ----

  function openDeleteModal(id) {
    var item = state.items.find(function (m) { return m.public_id === id; });
    if (!item) return;
    document.getElementById('intakeDeleteItemTitle').textContent = item.title || id;
    var btn = document.getElementById('intakeDeleteConfirmBtn');
    btn.setAttribute('data-intake-id', id);
    btn.setAttribute('data-intake-rv', item.row_version || '');
    var modal = new bootstrap.Modal(document.getElementById('intakeDeleteModal'));
    modal.show();
  }

  function confirmDelete() {
    var btn = document.getElementById('intakeDeleteConfirmBtn');
    var id = btn.getAttribute('data-intake-id');
    if (!id) return;

    waitForApi(function () {
      req('api/v1/intake-items/' + encodeURIComponent(id), { method: 'DELETE' })
        .then(function (resp) {
          if (resp && resp.success) {
            notify(t('intake.deleted', 'Заявка удалена'), 'success');
            var modalEl = document.getElementById('intakeDeleteModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            loadItems();
          } else {
            notify(t('intake.delete_error', 'Ошибка удаления'), 'error');
          }
        })
        .catch(function () {
          notify(t('intake.delete_error', 'Ошибка удаления'), 'error');
        });
    });
  }

  // ---- Accept ----

  function openAcceptModal(id) {
    var item = state.items.find(function (m) { return m.public_id === id; });
    if (!item) return;
    document.getElementById('intakeAcceptItemTitle').textContent = item.title || id;
    document.getElementById('intakeAcceptPublicId').value = item.public_id || '';
    document.getElementById('intakeAcceptRowVersion').value = item.row_version || '';
    document.getElementById('intakeAcceptProject').value = item.project_public_id || '';
    var modal = new bootstrap.Modal(document.getElementById('intakeAcceptModal'));
    modal.show();
  }

  function confirmAccept(e) {
    e.preventDefault();
    var id = document.getElementById('intakeAcceptPublicId').value;
    var rv = document.getElementById('intakeAcceptRowVersion').value;
    var projectId = document.getElementById('intakeAcceptProject').value;
    if (!projectId) {
      notify(t('intake.error_accept_project_required', 'Выберите проект'), 'error');
      return;
    }

    waitForApi(function () {
      req('api/v1/intake-items/' + encodeURIComponent(id) + '/accept', {
        method: 'POST',
        body: { project_public_id: projectId, row_version: rv || undefined }
      })
        .then(function (resp) {
          if (resp && resp.success) {
            notify(t('intake.accepted', 'Заявка принята, задача создана'), 'success');
            var modalEl = document.getElementById('intakeAcceptModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            loadItems();
          } else {
            notify(resp && resp.message || t('intake.accept_error', 'Ошибка при принятии'), 'error');
          }
        })
        .catch(function () {
          notify(t('intake.accept_error', 'Ошибка при принятии'), 'error');
        });
    });
  }

  // ---- Reject ----

  function openRejectModal(id) {
    var item = state.items.find(function (m) { return m.public_id === id; });
    if (!item) return;
    document.getElementById('intakeRejectItemTitle').textContent = item.title || id;
    document.getElementById('intakeRejectPublicId').value = item.public_id || '';
    document.getElementById('intakeRejectRowVersion').value = item.row_version || '';
    document.getElementById('intakeRejectReason').value = '';
    var modal = new bootstrap.Modal(document.getElementById('intakeRejectModal'));
    modal.show();
  }

  function confirmReject(e) {
    e.preventDefault();
    var id = document.getElementById('intakeRejectPublicId').value;
    var rv = document.getElementById('intakeRejectRowVersion').value;
    var reason = document.getElementById('intakeRejectReason').value.trim();
    if (!reason) {
      notify(t('intake.error_reject_reason_required', 'Укажите причину отклонения'), 'error');
      return;
    }

    waitForApi(function () {
      req('api/v1/intake-items/' + encodeURIComponent(id) + '/reject', {
        method: 'POST',
        body: { resolution_note: reason, row_version: rv || undefined }
      })
        .then(function (resp) {
          if (resp && resp.success) {
            notify(t('intake.rejected', 'Заявка отклонена'), 'success');
            var modalEl = document.getElementById('intakeRejectModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            loadItems();
          } else {
            notify(resp && resp.message || t('intake.reject_error', 'Ошибка при отклонении'), 'error');
          }
        })
        .catch(function () {
          notify(t('intake.reject_error', 'Ошибка при отклонении'), 'error');
        });
    });
  }

  // ---- Snooze ----

  function openSnoozeModal(id) {
    var item = state.items.find(function (m) { return m.public_id === id; });
    if (!item) return;
    document.getElementById('intakeSnoozeItemTitle').textContent = item.title || id;
    document.getElementById('intakeSnoozePublicId').value = item.public_id || '';
    document.getElementById('intakeSnoozeRowVersion').value = item.row_version || '';
    // Default snooze to tomorrow
    var tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('intakeSnoozeUntil').value = tomorrow.toISOString().slice(0, 10);
    var modal = new bootstrap.Modal(document.getElementById('intakeSnoozeModal'));
    modal.show();
  }

  function confirmSnooze(e) {
    e.preventDefault();
    var id = document.getElementById('intakeSnoozePublicId').value;
    var rv = document.getElementById('intakeSnoozeRowVersion').value;
    var until = document.getElementById('intakeSnoozeUntil').value;
    if (!until) {
      notify(t('intake.error_snooze_date_required', 'Укажите дату'), 'error');
      return;
    }

    waitForApi(function () {
      req('api/v1/intake-items/' + encodeURIComponent(id) + '/snooze', {
        method: 'POST',
        body: { snoozed_until: until, row_version: rv || undefined }
      })
        .then(function (resp) {
          if (resp && resp.success) {
            notify(t('intake.snoozed', 'Заявка отложена'), 'success');
            var modalEl = document.getElementById('intakeSnoozeModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            loadItems();
          } else {
            notify(resp && resp.message || t('intake.snooze_error', 'Ошибка'), 'error');
          }
        })
        .catch(function () {
          notify(t('intake.snooze_error', 'Ошибка'), 'error');
        });
    });
  }

  // ---- Duplicate ----

  function openDuplicateModal(id) {
    var item = state.items.find(function (m) { return m.public_id === id; });
    if (!item) return;
    document.getElementById('intakeDuplicateItemTitle').textContent = item.title || id;
    document.getElementById('intakeDuplicatePublicId').value = item.public_id || '';
    document.getElementById('intakeDuplicateRowVersion').value = item.row_version || '';
    document.getElementById('intakeDuplicateTarget').value = '';
    var modal = new bootstrap.Modal(document.getElementById('intakeDuplicateModal'));
    modal.show();
  }

  function confirmDuplicate(e) {
    e.preventDefault();
    var id = document.getElementById('intakeDuplicatePublicId').value;
    var rv = document.getElementById('intakeDuplicateRowVersion').value;
    var target = document.getElementById('intakeDuplicateTarget').value.trim();
    if (!target) {
      notify(t('intake.error_duplicate_target_required', 'Укажите ID заявки-дубликата'), 'error');
      return;
    }

    waitForApi(function () {
      req('api/v1/intake-items/' + encodeURIComponent(id) + '/duplicate', {
        method: 'POST',
        body: { duplicate_intake_item_public_id: target, row_version: rv || undefined }
      })
        .then(function (resp) {
          if (resp && resp.success) {
            notify(t('intake.marked_duplicate', 'Заявка отмечена как дубликат'), 'success');
            var modalEl = document.getElementById('intakeDuplicateModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            loadItems();
          } else {
            notify(resp && resp.message || t('intake.duplicate_error', 'Ошибка'), 'error');
          }
        })
        .catch(function () {
          notify(t('intake.duplicate_error', 'Ошибка'), 'error');
        });
    });
  }

  // ---- Reopen ----

  function openReopenModal(id) {
    var item = state.items.find(function (m) { return m.public_id === id; });
    if (!item) return;
    document.getElementById('intakeReopenItemTitle').textContent = item.title || id;
    document.getElementById('intakeReopenPublicId').value = item.public_id || '';
    document.getElementById('intakeReopenRowVersion').value = item.row_version || '';
    var modal = new bootstrap.Modal(document.getElementById('intakeReopenModal'));
    modal.show();
  }

  function confirmReopen(e) {
    e.preventDefault();
    var id = document.getElementById('intakeReopenPublicId').value;
    var rv = document.getElementById('intakeReopenRowVersion').value;

    waitForApi(function () {
      req('api/v1/intake-items/' + encodeURIComponent(id) + '/reopen', {
        method: 'POST',
        body: { row_version: rv || undefined }
      })
        .then(function (resp) {
          if (resp && resp.success) {
            notify(t('intake.reopened', 'Заявка восстановлена'), 'success');
            var modalEl = document.getElementById('intakeReopenModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            loadItems();
          } else {
            notify(resp && resp.message || t('intake.reopen_error', 'Ошибка'), 'error');
          }
        })
        .catch(function () {
          notify(t('intake.reopen_error', 'Ошибка'), 'error');
        });
    });
  }

  // ---- Activities ----

  function openActivitiesModal(id) {
    var item = state.items.find(function (m) { return m.public_id === id; });
    if (!item) return;
    var bodyEl = document.getElementById('intakeActivitiesBody');
    if (!bodyEl) return;
    bodyEl.innerHTML = '<p class="text-muted">' + esc(t('page.loading', 'Загрузка...')) + '</p>';

    var modal = new bootstrap.Modal(document.getElementById('intakeActivitiesModal'));
    modal.show();

    waitForApi(function () {
      req('api/v1/intake-items/' + encodeURIComponent(id) + '/activities', { method: 'GET' })
        .then(function (envelope) {
          var activities = (envelope.data && envelope.data.items) || [];
          if (!activities.length) {
            bodyEl.innerHTML = '<p class="text-muted">' + esc(t('intake.activities_empty', 'История пуста')) + '</p>';
            return;
          }
          bodyEl.innerHTML = '<div class="crm-timeline">' + activities.map(function (a) {
            var actor = a.actor_name || t('intake.unknown_user', 'Неизвестно');
            var eventLabel = eventTypeLabel(a.event_type);
            var fieldInfo = a.field_name ? (': ' + esc(a.field_name) + ' ' + esc(a.old_value || '') + ' → ' + esc(a.new_value || '')) : '';
            var comment = a.comment ? '<br><em>' + esc(a.comment) + '</em>' : '';
            var time = a.created_at ? a.created_at.slice(0, 16).replace('T', ' ') : '';
            return '<div class="crm-timeline-item mb-2 pb-2 border-bottom">'
              + '<small class="text-muted">' + esc(time) + '</small> '
              + '<strong>' + esc(actor) + '</strong> '
              + esc(eventLabel) + fieldInfo + comment
              + '</div>';
          }).join('') + '</div>';
        })
        .catch(function () {
          bodyEl.innerHTML = '<p class="text-danger">' + esc(t('intake.activities_error', 'Ошибка загрузки истории')) + '</p>';
        });
    });
  }

  function eventTypeLabel(eventType) {
    var map = {
      created: t('intake.event_created', 'создал(а) заявку'),
      updated: t('intake.event_updated', 'изменил(а)'),
      accepted: t('intake.event_accepted', 'принял(а) заявку'),
      rejected: t('intake.event_rejected', 'отклонил(а)'),
      snoozed: t('intake.event_snoozed', 'отложил(а)'),
      reopened: t('intake.event_reopened', 'восстановил(а)'),
      marked_duplicate: t('intake.event_marked_duplicate', 'отметил(а) дубликатом')
    };
    return map[eventType] || eventType;
  }

  // ---- Init ----

  function init() {
    if (document.body.getAttribute('data-page') !== 'intake') return;

    waitForApi(function () {
      loadProjects();
      loadUsers();
      loadClients();
      loadItems();

      document.getElementById('intakeRefreshBtn').addEventListener('click', function () {
        loadItems();
      });
      document.getElementById('intakeCreateBtn').addEventListener('click', openCreateModal);
      document.getElementById('intakeForm').addEventListener('submit', handleSave);
      document.getElementById('intakeAcceptForm').addEventListener('submit', confirmAccept);
      document.getElementById('intakeRejectForm').addEventListener('submit', confirmReject);
      document.getElementById('intakeSnoozeForm').addEventListener('submit', confirmSnooze);
      document.getElementById('intakeDuplicateForm').addEventListener('submit', confirmDuplicate);
      document.getElementById('intakeReopenForm').addEventListener('submit', confirmReopen);
      document.getElementById('intakeDeleteConfirmBtn').addEventListener('click', confirmDelete);

      document.getElementById('intakeStatusFilter').addEventListener('change', function () {
        loadItems();
      });
      document.getElementById('intakeSourceFilter').addEventListener('change', function () {
        loadItems();
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return {
    loadItems: loadItems,
    openCreateModal: openCreateModal,
    openEditModal: openEditModal
  };
})();
