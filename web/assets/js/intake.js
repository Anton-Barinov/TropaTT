window.CRM = window.CRM || {};
window.CRM.intake = (function () {
  var state = {
    items: [],
    filters: {},
    page: 1,
    limit: 20,
    total: 0,
    pages: 0,
    currentPublicId: null,
  };

  var STATUS_MAP = {
    pending: { label: 'Новая', class: 'crm-badge-info' },
    accepted: { label: 'Принята', class: 'crm-badge-success' },
    rejected: { label: 'Отклонена', class: 'crm-badge-secondary' },
    snoozed: { label: 'Отложена', class: 'crm-badge-warning' },
    duplicate: { label: 'Дубликат', class: 'crm-badge-secondary' },
  };

  function t(key, fallback) {
    if (window.CRM.i18n && typeof window.CRM.i18n.t === 'function') {
      return window.CRM.i18n.t(key, fallback);
    }
    return fallback || key;
  }

  function safeText(value) {
    if (window.CRM.text && typeof window.CRM.text.safeText === 'function') {
      return window.CRM.text.safeText(value);
    }
    var el = document.createElement('span');
    el.textContent = String(value || '');
    return el.innerHTML;
  }

  function formatDate(value) {
    if (!value) return '—';
    try {
      var d = new Date(value.replace(' ', 'T'));
      return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch (e) {
      return String(value);
    }
  }

  function statusLabel(code) {
    return (STATUS_MAP[code] && STATUS_MAP[code].label) || code || '—';
  }

  function statusClass(code) {
    return (STATUS_MAP[code] && STATUS_MAP[code].class) || 'crm-badge-secondary';
  }

  function priorityLabel(code) {
    var map = {
      low: t('priority.low', 'Низкий'),
      normal: t('priority.normal', 'Нормальный'),
      high: t('priority.high', 'Высокий'),
      urgent: t('priority.urgent', 'Срочный'),
    };
    return map[String(code || '')] || code || '—';
  }

  function sourceLabel(code) {
    var map = {
      manual: t('intake.source_manual', 'Ручной ввод'),
      client: t('intake.source_client', 'Клиент'),
      api: t('intake.source_api', 'API'),
      webhook: t('intake.source_webhook', 'Webhook'),
      email: t('intake.source_email', 'Email'),
      ai: t('intake.source_ai', 'AI'),
      import: t('intake.source_import', 'Импорт'),
      system: t('intake.source_system', 'Система'),
    };
    return map[String(code || '')] || code || '—';
  }

  function hasPermission(code) {
    if (window.CRM.api && typeof window.CRM.api.hasPermission === 'function') {
      return window.CRM.api.hasPermission(code);
    }
    return true;
  }

  function apiRequest(route, options) {
    return window.CRM.api.request(route, options || {});
  }

  function setState(name) {
    document.querySelectorAll('#intakeStates > [data-state-item]').forEach(function (el) {
      el.classList.toggle('d-none', el.getAttribute('data-state-item') !== name);
    });
  }

  function showError(message) {
    if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.setErrorState === 'function') {
      window.CRM.pageApiBindings.setErrorState(message);
    }
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

  function loadItems() {
    setState('loading');

    var query = {
      page: state.page,
      limit: state.limit,
    };

    if (state.filters.status) query.status = state.filters.status;
    if (state.filters.source_type) query.source_type = state.filters.source_type;
    if (state.filters.project_public_id) query.project_public_id = state.filters.project_public_id;
    if (state.filters.priority_code) query.priority_code = state.filters.priority_code;
    if (state.filters.client_public_id) query.client_public_id = state.filters.client_public_id;
    if (state.filters.assignee_user_id) query.assignee_user_id = state.filters.assignee_user_id;
    if (state.filters.q) query.q = state.filters.q;

    apiRequest('api/v1/intake-items', { query: query })
      .then(function (envelope) {
        var items = (envelope.data && Array.isArray(envelope.data.items)) ? envelope.data.items : [];
        var meta = envelope.meta || {};
        var pagination = meta.pagination || {};
        state.total = pagination.total || items.length;
        state.pages = pagination.pages || 1;
        state.items = items;
        renderItems(items);
        updatePagination();
        updateSummary();
        if (!items.length && !state.filters.status && !state.filters.q && !state.filters.source_type) {
          setState('empty');
        } else if (!items.length) {
          setState('no-results');
        } else {
          setState('default');
        }
      })
      .catch(function (error) {
        setState('error');
        if (window.CRM.api && typeof window.CRM.api.normalizeError === 'function') {
          var normalized = window.CRM.api.normalizeError(error, t('intake.load_error', 'Ошибка загрузки'));
          showError(window.CRM.api.formatErrorMessage(normalized));
        }
      });
  }

  function renderItems(items) {
    var tbody = document.getElementById('intakeTableBody');
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-muted">' + t('intake.no_items', 'Нет заявок') + '</td></tr>';
      return;
    }

    tbody.innerHTML = items.map(function (item) {
      var publicId = item.public_id || '';
      var actionsHtml = buildActionsHtml(item);
      return '<tr>'
        + '<td><a href="#" class="intake-open-link" data-intake-public-id="' + safeText(publicId) + '">' + safeText(item.title || t('intake.untitled', 'Без названия')) + '</a></td>'
        + '<td><span class="crm-badge ' + statusClass(item.status) + '">' + safeText(statusLabel(item.status)) + '</span></td>'
        + '<td>' + safeText(item.project_title || '—') + '</td>'
        + '<td>' + safeText(sourceLabel(item.source_type)) + '</td>'
        + '<td>' + safeText(priorityLabel(item.priority_code)) + '</td>'
        + '<td>' + safeText(item.assignee_name || '—') + '</td>'
        + '<td>' + safeText(formatDate(item.due_at)) + '</td>'
        + '<td>' + safeText(formatDate(item.created_at)) + '</td>'
        + '<td>' + actionsHtml + '</td>'
        + '</tr>';
    }).join('');
  }

  function buildActionsHtml(item) {
    var status = item.status || 'pending';
    var publicId = item.public_id || '';
    var actions = [];

    actions.push('<a class="btn btn-sm crm-btn-subtle crm-btn-compact intake-open-link" data-intake-public-id="' + safeText(publicId) + '">' + t('intake.action_open', 'Открыть') + '</a>');

    if (status === 'pending' || status === 'snoozed') {
      if (hasPermission('intake.accept')) {
        actions.push('<button class="btn btn-sm crm-btn-success crm-btn-compact intake-accept-btn" data-intake-public-id="' + safeText(publicId) + '">' + t('intake.action_accept', 'Принять') + '</button>');
      }
      if (hasPermission('intake.manage')) {
        actions.push('<button class="btn btn-sm crm-btn-warning crm-btn-compact intake-snooze-btn" data-intake-public-id="' + safeText(publicId) + '">' + t('intake.action_snooze', 'Отложить') + '</button>');
        actions.push('<button class="btn btn-sm crm-btn-danger crm-btn-compact intake-reject-btn" data-intake-public-id="' + safeText(publicId) + '">' + t('intake.action_reject', 'Отклонить') + '</button>');
        actions.push('<button class="btn btn-sm crm-btn-secondary crm-btn-compact intake-duplicate-btn" data-intake-public-id="' + safeText(publicId) + '">' + t('intake.action_duplicate', 'Дубликат') + '</button>');
      }
    }

    if (status === 'rejected' || status === 'snoozed' || status === 'duplicate') {
      if (hasPermission('intake.manage')) {
        actions.push('<button class="btn btn-sm btn-light crm-btn-compact intake-reopen-btn" data-intake-public-id="' + safeText(publicId) + '">' + t('intake.action_reopen', 'Вернуть') + '</button>');
      }
    }

    if (hasPermission('intake.delete')) {
      actions.push('<button class="btn btn-sm crm-btn-danger crm-btn-compact intake-delete-btn" data-intake-public-id="' + safeText(publicId) + '">' + t('intake.action_delete', 'Удалить') + '</button>');
    }

    return actions.join(' ');
  }

  function updatePagination() {
    var pager = document.getElementById('intakePager');
    if (!pager) return;

    pager.classList.toggle('d-none', state.pages <= 1);
    var html = '<nav><ul class="pagination pagination-sm mb-0">';
    html += '<li class="page-item' + (state.page <= 1 ? ' disabled' : '') + '"><button class="page-link intake-page-btn" data-page="' + (state.page - 1) + '">&laquo;</button></li>';
    for (var p = 1; p <= state.pages; p++) {
      html += '<li class="page-item' + (p === state.page ? ' active' : '') + '"><button class="page-link intake-page-btn" data-page="' + p + '">' + p + '</button></li>';
    }
    html += '<li class="page-item' + (state.page >= state.pages ? ' disabled' : '') + '"><button class="page-link intake-page-btn" data-page="' + (state.page + 1) + '">&raquo;</button></li>';
    html += '</ul></nav>';
    pager.innerHTML = html;
  }

  function updateSummary() {
    var el = document.getElementById('intakeResultSummary');
    if (!el) return;
    el.textContent = t('intake.showing', 'Показано') + ' ' + state.items.length + ' ' + t('intake.of', 'из') + ' ' + state.total + ' ' + t('intake.items', 'заявок');
  }

  function openCreateModal() {
    var modal = document.getElementById('intakeCreateModal');
    if (!modal) return;

    // Load projects, clients, users for selects
    Promise.all([
      apiRequest('api/v1/projects', { query: { limit: 500 } }).catch(function () { return null; }),
      apiRequest('api/v1/clients', { query: { limit: 500 } }).catch(function () { return null; }),
      apiRequest('api/v1/users', { query: { limit: 500, is_active: 1 } }).catch(function () { return null; }),
    ]).then(function (results) {
      var projectsEnvelope = results[0];
      var clientsEnvelope = results[1];
      var usersEnvelope = results[2];

      var projectSelect = modal.querySelector('[name="project_public_id"]');
      if (projectSelect && projectsEnvelope) {
        var projects = (projectsEnvelope.data && Array.isArray(projectsEnvelope.data.items)) ? projectsEnvelope.data.items : [];
        projectSelect.innerHTML = '<option value="">' + t('intake.field_no_project', 'Без проекта') + '</option>'
          + projects.map(function (p) { return '<option value="' + safeText(p.public_id) + '">' + safeText(p.title) + '</option>'; }).join('');
      }

      var clientSelect = modal.querySelector('[name="client_public_id"]');
      if (clientSelect && clientsEnvelope) {
        var clients = (clientsEnvelope.data && Array.isArray(clientsEnvelope.data.items)) ? clientsEnvelope.data.items : [];
        clientSelect.innerHTML = '<option value="">' + t('intake.field_no_client', 'Без клиента') + '</option>'
          + clients.map(function (c) { return '<option value="' + safeText(c.public_id) + '">' + safeText(c.title || c.name || c.company_name) + '</option>'; }).join('');
      }

      var assigneeSelect = modal.querySelector('[name="assignee_user_id"]');
      if (assigneeSelect && usersEnvelope) {
        var users = (usersEnvelope.data && Array.isArray(usersEnvelope.data.items)) ? usersEnvelope.data.items : [];
        assigneeSelect.innerHTML = '<option value="">' + t('intake.field_no_assignee', 'Не назначен') + '</option>'
          + users.map(function (u) { return '<option value="' + safeText(String(u.id)) + '">' + safeText(u.full_name || u.login) + '</option>'; }).join('');
      }

      // Reset form
      var form = document.getElementById('intakeCreateForm');
      if (form) form.reset();
    });

    if (window.bootstrap && window.bootstrap.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }
  }

  function saveItem() {
    var modal = document.getElementById('intakeCreateModal');
    var form = document.getElementById('intakeCreateForm');
    if (!form) return;

    var data = {};
    var inputs = form.querySelectorAll('[name]');
    inputs.forEach(function (input) {
      var val = input.value;
      if (val !== '') {
        if (input.name === 'assignee_user_id') {
          data[input.name] = parseInt(val, 10) || null;
        } else {
          data[input.name] = val;
        }
      }
    });

    if (!data.title) {
      notify(t('intake.error_title_required', 'Укажите название заявки'), 'error');
      return;
    }

    var btn = document.getElementById('intakeCreateSaveBtn');
    if (btn) btn.disabled = true;

    apiRequest('api/v1/intake-items', { method: 'POST', body: data })
      .then(function () {
        notify(t('intake.created', 'Заявка создана'), 'success');
        if (modal && window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(modal).hide();
        }
        state.page = 1;
        loadItems();
      })
      .catch(function (error) {
        var normalized = window.CRM.api ? window.CRM.api.normalizeError(error, t('intake.create_error', 'Ошибка создания')) : null;
        notify(normalized ? window.CRM.api.formatErrorMessage(normalized) : t('intake.create_error', 'Ошибка создания'), 'error');
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

  function openEditModal(publicId) {
    loadItemDetail(publicId);
  }

  function loadItemDetail(publicId) {
    var modal = document.getElementById('intakeDetailModal');
    var body = document.getElementById('intakeDetailBody');
    var footer = document.getElementById('intakeDetailFooter');
    var titleEl = document.getElementById('intakeDetailTitle');

    if (!modal || !body) return;

    body.innerHTML = '<div class="text-center py-4 text-muted">' + t('intake.loading_detail', 'Загрузка...') + '</div>';
    state.currentPublicId = publicId;

    Promise.all([
      apiRequest('api/v1/intake-items/' + encodeURIComponent(publicId)),
      apiRequest('api/v1/intake-items/' + encodeURIComponent(publicId) + '/activities'),
      apiRequest('api/v1/projects', { query: { limit: 500 } }).catch(function () { return null; }),
      apiRequest('api/v1/clients', { query: { limit: 500 } }).catch(function () { return null; }),
      apiRequest('api/v1/users', { query: { limit: 500, is_active: 1 } }).catch(function () { return null; }),
    ]).then(function (results) {
      var itemEnvelope = results[0];
      var activitiesEnvelope = results[1];
      var projectsEnvelope = results[2];
      var clientsEnvelope = results[3];
      var usersEnvelope = results[4];

      var item = itemEnvelope && itemEnvelope.data ? itemEnvelope.data.item || itemEnvelope.data : null;
      var activities = (activitiesEnvelope && activitiesEnvelope.data && Array.isArray(activitiesEnvelope.data.items))
        ? activitiesEnvelope.data.items : [];

      if (!item) {
        body.innerHTML = '<div class="text-center text-danger py-4">' + t('intake.not_found', 'Заявка не найдена') + '</div>';
        return;
      }

      if (titleEl) titleEl.textContent = item.title || t('intake.untitled', 'Без названия');

      var projects = (projectsEnvelope && projectsEnvelope.data && Array.isArray(projectsEnvelope.data.items))
        ? projectsEnvelope.data.items : [];
      var clients = (clientsEnvelope && clientsEnvelope.data && Array.isArray(clientsEnvelope.data.items))
        ? clientsEnvelope.data.items : [];
      var users = (usersEnvelope && usersEnvelope.data && Array.isArray(usersEnvelope.data.items))
        ? usersEnvelope.data.items : [];

      var status = item.status || 'pending';
      var isAccepted = status === 'accepted';

      body.innerHTML = '<form id="intakeEditForm" novalidate>'
        + '<input type="hidden" name="public_id" value="' + safeText(item.public_id || '') + '">'
        + '<input type="hidden" name="row_version" value="' + safeText(String(item.row_version || 1)) + '">'
        + '<div class="row">'
        + '<div class="col-md-8">'
        + '<div class="mb-3"><label class="form-label">' + t('intake.field_title', 'Название') + '</label><input class="form-control" name="title" value="' + safeText(item.title || '') + '" maxlength="255"></div>'
        + '<div class="mb-3"><label class="form-label">' + t('intake.field_description', 'Описание') + '</label><textarea class="form-control" name="description" rows="4" maxlength="65535">' + safeText(item.description || '') + '</textarea></div>'
        + '<div class="row mb-3">'
        + '<div class="col-md-6"><label class="form-label">' + t('intake.field_project', 'Проект') + '</label><select class="form-select" name="project_public_id"><option value="">' + t('intake.field_no_project', 'Без проекта') + '</option>'
        + projects.map(function (p) { return '<option value="' + safeText(p.public_id) + '"' + (p.public_id === item.project_public_id ? ' selected' : '') + '>' + safeText(p.title) + '</option>'; }).join('')
        + '</select></div>'
        + '<div class="col-md-6"><label class="form-label">' + t('intake.field_client', 'Клиент') + '</label><select class="form-select" name="client_public_id"><option value="">' + t('intake.field_no_client', 'Без клиента') + '</option>'
        + clients.map(function (c) { return '<option value="' + safeText(c.public_id) + '"' + (c.public_id === item.client_public_id ? ' selected' : '') + '>' + safeText(c.title || c.name || c.company_name) + '</option>'; }).join('')
        + '</select></div>'
        + '</div>'
        + '<div class="row mb-3">'
        + '<div class="col-md-4"><label class="form-label">' + t('intake.field_priority', 'Приоритет') + '</label><select class="form-select" name="priority_code"><option value="">' + t('intake.field_no_priority', 'Без приоритета') + '</option><option value="low"' + (item.priority_code === 'low' ? ' selected' : '') + '>' + t('priority.low', 'Низкий') + '</option><option value="normal"' + (item.priority_code === 'normal' ? ' selected' : '') + '>' + t('priority.normal', 'Нормальный') + '</option><option value="high"' + (item.priority_code === 'high' ? ' selected' : '') + '>' + t('priority.high', 'Высокий') + '</option><option value="urgent"' + (item.priority_code === 'urgent' ? ' selected' : '') + '>' + t('priority.urgent', 'Срочный') + '</option></select></div>'
        + '<div class="col-md-4"><label class="form-label">' + t('intake.field_source', 'Источник') + '</label><select class="form-select" name="source_type"><option value="manual"' + (item.source_type === 'manual' ? ' selected' : '') + '>' + t('intake.source_manual', 'Ручной ввод') + '</option><option value="client"' + (item.source_type === 'client' ? ' selected' : '') + '>' + t('intake.source_client', 'Клиент') + '</option><option value="api"' + (item.source_type === 'api' ? ' selected' : '') + '>' + t('intake.source_api', 'API') + '</option><option value="webhook"' + (item.source_type === 'webhook' ? ' selected' : '') + '>' + t('intake.source_webhook', 'Webhook') + '</option><option value="email"' + (item.source_type === 'email' ? ' selected' : '') + '>' + t('intake.source_email', 'Email') + '</option><option value="ai"' + (item.source_type === 'ai' ? ' selected' : '') + '>' + t('intake.source_ai', 'AI') + '</option></select></div>'
        + '<div class="col-md-4"><label class="form-label">' + t('intake.field_assignee', 'Ответственный') + '</label><select class="form-select" name="assignee_user_id"><option value="">' + t('intake.field_no_assignee', 'Не назначен') + '</option>'
        + users.map(function (u) { return '<option value="' + safeText(String(u.id)) + '"' + (String(u.id) === String(item.assignee_user_id) ? ' selected' : '') + '>' + safeText(u.full_name || u.login) + '</option>'; }).join('')
        + '</select></div>'
        + '</div>'
        + '<div class="row mb-3">'
        + '<div class="col-md-6"><label class="form-label">' + t('intake.field_due', 'Срок') + '</label><input class="form-control" name="due_at" type="datetime-local" value="' + safeText(item.due_at || '') + '"></div>'
        + '<div class="col-md-6"><label class="form-label">' + t('intake.field_contact', 'Контакт') + '</label><input class="form-control" name="contact_public_id" value="' + safeText(item.contact_public_id || '') + '" placeholder="' + t('intake.field_contact_placeholder', 'public_id контакта') + '"></div>'
        + '</div>'
        + '</div>'
        + '<div class="col-md-4">'
        + '<div class="crm-card p-3 mb-3"><h6 class="mb-3">' + t('intake.detail_info', 'Информация') + '</h6>'
        + '<div class="crm-metric-tile mb-2"><small class="text-muted">' + t('intake.detail_status', 'Статус') + '</small><div><span class="crm-badge ' + statusClass(item.status) + '">' + safeText(statusLabel(item.status)) + '</span></div></div>'
        + '<div class="crm-metric-tile mb-2"><small class="text-muted">' + t('intake.detail_created_by', 'Создал') + '</small><div>' + safeText(item.creator_name || '—') + '</div></div>'
        + '<div class="crm-metric-tile mb-2"><small class="text-muted">' + t('intake.detail_created_at', 'Создано') + '</small><div>' + safeText(formatDate(item.created_at)) + '</div></div>'
        + '<div class="crm-metric-tile mb-2"><small class="text-muted">' + t('intake.detail_updated_at', 'Обновлено') + '</small><div>' + safeText(formatDate(item.updated_at)) + '</div></div>'
        + '<div class="crm-metric-tile mb-2"><small class="text-muted">' + t('intake.detail_version', 'Версия') + '</small><div>' + safeText(String(item.row_version || 1)) + '</div></div>'
        + (item.accepted_task_public_id ? '<div class="crm-metric-tile"><small class="text-muted">' + t('intake.detail_task', 'Созданная задача') + '</small><div><a href="index.php?route=task-detail&task_public_id=' + safeText(item.accepted_task_public_id) + '" target="_blank">' + safeText(item.accepted_task_public_id) + '</a></div></div>' : '')
        + (item.duplicate_intake_item_public_id ? '<div class="crm-metric-tile"><small class="text-muted">' + t('intake.detail_duplicate_intake', 'Дубликат заявки') + '</small><div><a href="#" class="intake-open-link" data-intake-public-id="' + safeText(item.duplicate_intake_item_public_id) + '">' + safeText(item.duplicate_intake_item_public_id) + '</a></div></div>' : '')
        + (item.duplicate_task_public_id ? '<div class="crm-metric-tile"><small class="text-muted">' + t('intake.detail_duplicate_task', 'Дубликат задачи') + '</small><div><a href="index.php?route=task-detail&task_public_id=' + safeText(item.duplicate_task_public_id) + '" target="_blank">' + safeText(item.duplicate_task_public_id) + '</a></div></div>' : '')
        + (item.resolution_note ? '<div class="crm-metric-tile"><small class="text-muted">' + t('intake.detail_resolution', 'Резолюция') + '</small><div>' + safeText(item.resolution_note) + '</div></div>' : '')
        + '</div>'
        + '</div>'
        + '</div>'
        + '</form>';

      // Activity feed
      if (activities.length) {
        var activityHtml = '<div class="mt-4"><h6>' + t('intake.activity_title', 'Активность') + '</h6><div class="crm-activity-feed">'
          + activities.map(function (a) {
            var actor = a.actor_name || t('intake.system', 'Система');
            var eventLabels = {
              created: t('intake.event_created', 'Создана'),
              updated: t('intake.event_updated', 'Обновлено поле') + ': ' + (a.field_name || ''),
              accepted: t('intake.event_accepted', 'Принята в работу'),
              rejected: t('intake.event_rejected', 'Отклонена'),
              snoozed: t('intake.event_snoozed', 'Отложена'),
              marked_duplicate: t('intake.event_duplicate', 'Помечена дубликатом'),
              reopened: t('intake.event_reopened', 'Возвращена'),
              deleted: t('intake.event_deleted', 'Удалена'),
              linked_task_created: t('intake.event_task_created', 'Создана задача'),
            };
            var eventLabel = eventLabels[a.event_type] || a.event_type;
            if (a.comment) {
              eventLabel += ' — ' + safeText(a.comment);
            }
            return '<div class="crm-activity-item small mb-2"><span class="text-muted">' + safeText(formatDate(a.created_at)) + '</span> <strong>' + safeText(actor) + '</strong> — ' + eventLabel + '</div>';
          }).join('')
          + '</div></div>';
        body.innerHTML += activityHtml;
      }

      // Footer buttons
      var footerActions = buildDetailActions(item);
      if (footer) footer.innerHTML = footerActions;
      if (!isAccepted) {
        footer.innerHTML += '<button type="button" class="btn crm-btn-primary ms-2" id="intakeSaveEditBtn">' + t('intake.btn_save', 'Сохранить') + '</button>';
      }
      footer.innerHTML += '<button type="button" class="btn btn-light ms-2" data-bs-dismiss="modal">' + t('intake.btn_close', 'Закрыть') + '</button>';

      // Bind save button inside detail modal
      var saveBtn = document.getElementById('intakeSaveEditBtn');
      if (saveBtn) {
        saveBtn.addEventListener('click', function () {
          saveEditItem(item.public_id, item.row_version);
        });
      }

      bindDetailActionButtons();

      if (window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
      }
    }).catch(function (error) {
      body.innerHTML = '<div class="text-center text-danger py-4">' + t('intake.load_error', 'Ошибка загрузки') + '</div>';
    });
  }

  function buildDetailActions(item) {
    var status = item.status || 'pending';
    var publicId = item.public_id || '';
    var actions = [];

    if (status === 'pending' || status === 'snoozed') {
      if (hasPermission('intake.accept')) {
        actions.push('<button class="btn crm-btn-success intake-accept-btn" data-intake-public-id="' + safeText(publicId) + '">' + t('intake.action_accept', 'Принять в работу') + '</button>');
      }
      if (hasPermission('intake.manage')) {
        actions.push('<button class="btn crm-btn-warning intake-snooze-btn" data-intake-public-id="' + safeText(publicId) + '">' + t('intake.action_snooze', 'Отложить') + '</button>');
        actions.push('<button class="btn crm-btn-danger intake-reject-btn" data-intake-public-id="' + safeText(publicId) + '">' + t('intake.action_reject', 'Отклонить') + '</button>');
        actions.push('<button class="btn crm-btn-secondary intake-duplicate-btn" data-intake-public-id="' + safeText(publicId) + '">' + t('intake.action_duplicate', 'Дубликат') + '</button>');
      }
    }

    if (status === 'rejected' || status === 'snoozed' || status === 'duplicate') {
      if (hasPermission('intake.manage')) {
        actions.push('<button class="btn btn-light intake-reopen-btn" data-intake-public-id="' + safeText(publicId) + '">' + t('intake.action_reopen', 'Вернуть в работу') + '</button>');
      }
    }

    if (hasPermission('intake.delete')) {
      actions.push('<button class="btn crm-btn-danger intake-delete-btn" data-intake-public-id="' + safeText(publicId) + '">' + t('intake.action_delete', 'Удалить') + '</button>');
    }

    return actions.join(' ');
  }

  function bindDetailActionButtons() {
    document.querySelectorAll('#intakeDetailModal .intake-accept-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        acceptItem(btn.getAttribute('data-intake-public-id'));
      });
    });
    document.querySelectorAll('#intakeDetailModal .intake-reject-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var publicId = btn.getAttribute('data-intake-public-id');
        document.querySelector('#intakeRejectForm [name="intake_public_id"]').value = publicId;
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeRejectModal')).show();
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeDetailModal')).hide();
        }
      });
    });
    document.querySelectorAll('#intakeDetailModal .intake-snooze-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var publicId = btn.getAttribute('data-intake-public-id');
        document.querySelector('#intakeSnoozeForm [name="intake_public_id"]').value = publicId;
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeSnoozeModal')).show();
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeDetailModal')).hide();
        }
      });
    });
    document.querySelectorAll('#intakeDetailModal .intake-duplicate-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var publicId = btn.getAttribute('data-intake-public-id');
        document.querySelector('#intakeDuplicateForm [name="intake_public_id"]').value = publicId;
        loadDuplicateTargets();
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeDuplicateModal')).show();
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeDetailModal')).hide();
        }
      });
    });
    document.querySelectorAll('#intakeDetailModal .intake-reopen-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        reopenItem(btn.getAttribute('data-intake-public-id'));
      });
    });
    document.querySelectorAll('#intakeDetailModal .intake-delete-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (confirm(t('intake.confirm_delete', 'Удалить заявку?'))) {
          deleteItem(btn.getAttribute('data-intake-public-id'));
        }
      });
    });
  }

  function saveEditItem(publicId, currentRowVersion) {
    var form = document.getElementById('intakeEditForm');
    if (!form) return;

    var data = {};
    var inputs = form.querySelectorAll('[name]');
    inputs.forEach(function (input) {
      if (input.name === 'public_id' || input.name === 'row_version') return;
      var val = input.value;
      if (val !== '') {
        if (input.name === 'assignee_user_id') {
          data[input.name] = parseInt(val, 10) || null;
        } else {
          data[input.name] = val;
        }
      } else {
        if (input.name === 'title') {
          notify(t('intake.error_title_required', 'Укажите название заявки'), 'error');
          return;
        }
        data[input.name] = null;
      }
    });

    data.row_version = currentRowVersion;

    var saveBtn = document.getElementById('intakeSaveEditBtn');
    if (saveBtn) saveBtn.disabled = true;

    apiRequest('api/v1/intake-items/' + encodeURIComponent(publicId), { method: 'PATCH', body: data })
      .then(function () {
        notify(t('intake.updated', 'Заявка обновлена'), 'success');
        loadItemDetail(publicId);
        loadItems();
      })
      .catch(function (error) {
        var normalized = window.CRM.api ? window.CRM.api.normalizeError(error, t('intake.update_error', 'Ошибка обновления')) : null;
        if (normalized && normalized.code === 'ROW_VERSION_CONFLICT') {
          notify(t('intake.error_row_version', 'Заявка была изменена другим пользователем. Обновите страницу.'), 'error');
        } else {
          notify(normalized ? window.CRM.api.formatErrorMessage(normalized) : t('intake.update_error', 'Ошибка обновления'), 'error');
        }
      })
      .finally(function () {
        if (saveBtn) saveBtn.disabled = false;
      });
  }

  function findItemOrPublic(publicId) {
    var item = state.items.find(function (i) { return i.public_id === publicId; });
    if (!item && state.currentPublicId === publicId) {
      // Try to get row_version from detail modal hidden input
      var rv = document.querySelector('#intakeEditForm [name="row_version"]');
      if (rv) {
        return { row_version: parseInt(rv.value, 10) || 1 };
      }
    }
    return item || { row_version: 1 };
  }

  function acceptItem(publicId) {
    if (!confirm(t('intake.confirm_accept', 'Принять заявку и создать задачу?'))) return;
    var item = findItemOrPublic(publicId);
    var body = { row_version: item.row_version || 1 };
    apiRequest('api/v1/intake-items/' + encodeURIComponent(publicId) + '/accept', { method: 'POST', body: body })
      .then(function (envelope) {
        var task = envelope.data && envelope.data.task ? envelope.data.task : null;
        var msg = t('intake.accepted', 'Заявка принята');
        if (task && task.public_id) {
          msg += '. <a href="index.php?route=task-detail&task_public_id=' + safeText(task.public_id) + '">' + t('intake.view_task', 'Перейти к задаче') + '</a>';
        }
        notify(msg, 'success');
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeDetailModal')).hide();
        }
        loadItems();
      })
      .catch(function (error) {
        var normalized = window.CRM.api ? window.CRM.api.normalizeError(error, t('intake.accept_error', 'Ошибка принятия')) : null;
        notify(normalized ? window.CRM.api.formatErrorMessage(normalized) : t('intake.accept_error', 'Ошибка принятия'), 'error');
      });
  }

  function rejectItem(publicId) {
    var form = document.getElementById('intakeRejectForm');
    var reason = form ? form.querySelector('[name="reason"]').value : '';
    if (!reason) {
      notify(t('intake.error_reason_required', 'Укажите причину отклонения'), 'error');
      return;
    }
    var item = findItemOrPublic(publicId);
    var body = { reason: reason, row_version: item.row_version || 1 };
    apiRequest('api/v1/intake-items/' + encodeURIComponent(publicId) + '/reject', { method: 'POST', body: body })
      .then(function () {
        notify(t('intake.rejected', 'Заявка отклонена'), 'success');
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeRejectModal')).hide();
        }
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeDetailModal')).hide();
        }
        loadItems();
      })
      .catch(function (error) {
        var normalized = window.CRM.api ? window.CRM.api.normalizeError(error, t('intake.reject_error', 'Ошибка отклонения')) : null;
        notify(normalized ? window.CRM.api.formatErrorMessage(normalized) : t('intake.reject_error', 'Ошибка отклонения'), 'error');
      });
  }

  function snoozeItem(publicId) {
    var form = document.getElementById('intakeSnoozeForm');
    var snoozedUntil = form ? form.querySelector('[name="snoozed_until"]').value : '';
    if (!snoozedUntil) {
      notify(t('intake.error_snooze_date_required', 'Укажите дату'), 'error');
      return;
    }
    var reason = form ? form.querySelector('[name="reason"]').value : '';
    var item = findItemOrPublic(publicId);
    var body = { snoozed_until: snoozedUntil, reason: reason || null, row_version: item.row_version || 1 };
    apiRequest('api/v1/intake-items/' + encodeURIComponent(publicId) + '/snooze', { method: 'POST', body: body })
      .then(function () {
        notify(t('intake.snoozed', 'Заявка отложена'), 'success');
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeSnoozeModal')).hide();
        }
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeDetailModal')).hide();
        }
        loadItems();
      })
      .catch(function (error) {
        var normalized = window.CRM.api ? window.CRM.api.normalizeError(error, t('intake.snooze_error', 'Ошибка')) : null;
        notify(normalized ? window.CRM.api.formatErrorMessage(normalized) : t('intake.snooze_error', 'Ошибка'), 'error');
      });
  }

  function markDuplicate(publicId) {
    var form = document.getElementById('intakeDuplicateForm');
    var duplicateIntakeItemPublicId = form ? form.querySelector('[name="duplicate_intake_item_public_id"]').value : '';
    var duplicateTaskPublicId = form ? form.querySelector('[name="duplicate_task_public_id"]').value : '';
    var reason = form ? form.querySelector('[name="reason"]').value : '';

    if (!duplicateIntakeItemPublicId && !duplicateTaskPublicId) {
      notify(t('intake.error_duplicate_target', 'Выберите заявку или укажите ID задачи'), 'error');
      return;
    }
    var item = findItemOrPublic(publicId);
    var body = { reason: reason || null, row_version: item.row_version || 1 };
    if (duplicateIntakeItemPublicId) body.duplicate_intake_item_public_id = duplicateIntakeItemPublicId;
    if (duplicateTaskPublicId) body.duplicate_task_public_id = duplicateTaskPublicId;

    apiRequest('api/v1/intake-items/' + encodeURIComponent(publicId) + '/duplicate', { method: 'POST', body: body })
      .then(function () {
        notify(t('intake.marked_duplicate', 'Заявка помечена дубликатом'), 'success');
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeDuplicateModal')).hide();
        }
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeDetailModal')).hide();
        }
        loadItems();
      })
      .catch(function (error) {
        var normalized = window.CRM.api ? window.CRM.api.normalizeError(error, t('intake.duplicate_error', 'Ошибка')) : null;
        notify(normalized ? window.CRM.api.formatErrorMessage(normalized) : t('intake.duplicate_error', 'Ошибка'), 'error');
      });
  }

  function reopenItem(publicId) {
    if (!confirm(t('intake.confirm_reopen', 'Вернуть заявку в работу?'))) return;
    apiRequest('api/v1/intake-items/' + encodeURIComponent(publicId) + '/reopen', { method: 'POST', body: {} })
      .then(function () {
        notify(t('intake.reopened', 'Заявка возвращена в работу'), 'success');
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeDetailModal')).hide();
        }
        loadItems();
      })
      .catch(function (error) {
        var normalized = window.CRM.api ? window.CRM.api.normalizeError(error, t('intake.reopen_error', 'Ошибка')) : null;
        notify(normalized ? window.CRM.api.formatErrorMessage(normalized) : t('intake.reopen_error', 'Ошибка'), 'error');
      });
  }

  function deleteItem(publicId) {
    apiRequest('api/v1/intake-items/' + encodeURIComponent(publicId), { method: 'DELETE' })
      .then(function () {
        notify(t('intake.deleted', 'Заявка удалена'), 'success');
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeDetailModal')).hide();
        }
        loadItems();
      })
      .catch(function (error) {
        var normalized = window.CRM.api ? window.CRM.api.normalizeError(error, t('intake.delete_error', 'Ошибка удаления')) : null;
        notify(normalized ? window.CRM.api.formatErrorMessage(normalized) : t('intake.delete_error', 'Ошибка удаления'), 'error');
      });
  }

  function loadDuplicateTargets() {
    apiRequest('api/v1/intake-items', { query: { limit: 100, status: 'pending' } })
      .then(function (envelope) {
        var items = (envelope.data && Array.isArray(envelope.data.items)) ? envelope.data.items : [];
        var select = document.querySelector('#intakeDuplicateForm [name="duplicate_intake_item_public_id"]');
        if (!select) return;
        var currentPublicId = state.currentPublicId;
        select.innerHTML = '<option value="">' + t('intake.duplicate_select_intake', 'Выберите заявку') + '</option>'
          + items.filter(function (i) { return i.public_id !== currentPublicId; }).map(function (i) {
            return '<option value="' + safeText(i.public_id) + '">' + safeText(i.title || i.public_id) + '</option>';
          }).join('');
      })
      .catch(function () {});
  }

  function loadActivities(publicId) {
    // Used in detail modal already
  }

  function bindEvents() {
    // Create button
    document.querySelectorAll('[data-intake-create]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        openCreateModal();
      });
    });

    // Create save button
    document.getElementById('intakeCreateSaveBtn').addEventListener('click', saveItem);

    // Pagination
    document.addEventListener('click', function (e) {
      var pageBtn = e.target.closest('.intake-page-btn');
      if (pageBtn) {
        e.preventDefault();
        var page = parseInt(pageBtn.getAttribute('data-page'), 10);
        if (page > 0 && page <= state.pages) {
          state.page = page;
          loadItems();
        }
      }
    });

    // Open detail links
    document.addEventListener('click', function (e) {
      var link = e.target.closest('.intake-open-link');
      if (link) {
        e.preventDefault();
        var publicId = link.getAttribute('data-intake-public-id');
        if (publicId) openEditModal(publicId);
      }
    });

    // Accept buttons in list
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.intake-accept-btn');
      if (btn) {
        e.preventDefault();
        acceptItem(btn.getAttribute('data-intake-public-id'));
      }
    });

    // Reject button - show reject modal
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.intake-reject-btn');
      if (btn) {
        e.preventDefault();
        var publicId = btn.getAttribute('data-intake-public-id');
        document.querySelector('#intakeRejectForm [name="intake_public_id"]').value = publicId;
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeRejectModal')).show();
        }
      }
    });

    // Reject confirm
    document.getElementById('intakeRejectConfirmBtn').addEventListener('click', function () {
      var publicId = document.querySelector('#intakeRejectForm [name="intake_public_id"]').value;
      rejectItem(publicId);
    });

    // Snooze button
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.intake-snooze-btn');
      if (btn) {
        e.preventDefault();
        var publicId = btn.getAttribute('data-intake-public-id');
        document.querySelector('#intakeSnoozeForm [name="intake_public_id"]').value = publicId;
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeSnoozeModal')).show();
        }
      }
    });

    // Snooze confirm
    document.getElementById('intakeSnoozeConfirmBtn').addEventListener('click', function () {
      var publicId = document.querySelector('#intakeSnoozeForm [name="intake_public_id"]').value;
      snoozeItem(publicId);
    });

    // Duplicate button
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.intake-duplicate-btn');
      if (btn) {
        e.preventDefault();
        var publicId = btn.getAttribute('data-intake-public-id');
        document.querySelector('#intakeDuplicateForm [name="intake_public_id"]').value = publicId;
        loadDuplicateTargets();
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(document.getElementById('intakeDuplicateModal')).show();
        }
      }
    });

    // Duplicate confirm
    document.getElementById('intakeDuplicateConfirmBtn').addEventListener('click', function () {
      var publicId = document.querySelector('#intakeDuplicateForm [name="intake_public_id"]').value;
      markDuplicate(publicId);
    });

    // Reopen buttons
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.intake-reopen-btn');
      if (btn) {
        e.preventDefault();
        reopenItem(btn.getAttribute('data-intake-public-id'));
      }
    });

    // Delete buttons
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.intake-delete-btn');
      if (btn) {
        e.preventDefault();
        if (confirm(t('intake.confirm_delete', 'Удалить заявку?'))) {
          deleteItem(btn.getAttribute('data-intake-public-id'));
        }
      }
    });

    // Filters
    var searchInput = document.getElementById('intakeSearchInput');
    if (searchInput) {
      var timer = null;
      searchInput.addEventListener('input', function () {
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () {
          state.filters.q = searchInput.value || '';
          state.page = 1;
          loadItems();
        }, 300);
      });
    }

    var filterSelects = ['intakeStatusFilter', 'intakeSourceFilter', 'intakeProjectFilter', 'intakePriorityFilter', 'intakeClientFilter', 'intakeAssigneeFilter'];
    filterSelects.forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.addEventListener('change', function () {
        readFilters();
        state.page = 1;
        loadItems();
        updateResetBtn();
      });
    });

    var resetBtn = document.getElementById('intakeFiltersResetBtn');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        state.filters = {};
        state.page = 1;
        if (searchInput) searchInput.value = '';
        filterSelects.forEach(function (id) {
          var el = document.getElementById(id);
          if (el) el.value = '';
        });
        readFilters();
        updateResetBtn();
        loadItems();
      });
    }

    // Load reference data for filters
    Promise.all([
      apiRequest('api/v1/projects', { query: { limit: 500 } }).catch(function () { return null; }),
      apiRequest('api/v1/clients', { query: { limit: 500 } }).catch(function () { return null; }),
      apiRequest('api/v1/users', { query: { limit: 500, is_active: 1 } }).catch(function () { return null; }),
    ]).then(function (results) {
      var projectsEnvelope = results[0];
      var clientsEnvelope = results[1];
      var usersEnvelope = results[2];

      var projectFilter = document.getElementById('intakeProjectFilter');
      if (projectFilter && projectsEnvelope) {
        var projects = (projectsEnvelope.data && Array.isArray(projectsEnvelope.data.items)) ? projectsEnvelope.data.items : [];
        projects.forEach(function (p) {
          var opt = document.createElement('option');
          opt.value = p.public_id || '';
          opt.textContent = p.title || p.public_id || '';
          projectFilter.appendChild(opt);
        });
      }

      var clientFilter = document.getElementById('intakeClientFilter');
      if (clientFilter && clientsEnvelope) {
        var clients = (clientsEnvelope.data && Array.isArray(clientsEnvelope.data.items)) ? clientsEnvelope.data.items : [];
        clients.forEach(function (c) {
          var opt = document.createElement('option');
          opt.value = c.public_id || '';
          opt.textContent = c.title || c.name || c.company_name || c.public_id || '';
          clientFilter.appendChild(opt);
        });
      }

      var assigneeFilter = document.getElementById('intakeAssigneeFilter');
      if (assigneeFilter && usersEnvelope) {
        var users = (usersEnvelope.data && Array.isArray(usersEnvelope.data.items)) ? usersEnvelope.data.items : [];
        users.forEach(function (u) {
          var opt = document.createElement('option');
          opt.value = String(u.id || '');
          opt.textContent = u.full_name || u.login || '';
          assigneeFilter.appendChild(opt);
        });
      }
    }).catch(function () {});
  }

  function init() {
    if (document.body.getAttribute('data-page') !== 'intake') return;
    bindEvents();
    loadItems();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return {
    loadItems: loadItems,
    renderItems: renderItems,
    openCreateModal: openCreateModal,
    openEditModal: openEditModal,
    saveItem: saveItem,
    acceptItem: acceptItem,
    rejectItem: rejectItem,
    snoozeItem: snoozeItem,
    markDuplicate: markDuplicate,
    reopenItem: reopenItem,
    deleteItem: deleteItem,
    loadActivities: loadActivities,
  };
})();
