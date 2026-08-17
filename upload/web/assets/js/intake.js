/**
 * Intake page controller.
 * Works with web/view/template/intake/index.php.
 */
window.CRM = window.CRM || {};
window.CRM.intake = (function () {
  'use strict';

  var state = {
    items: [],
    projects: [],
    clients: [],
    users: [],
    total: 0,
    page: 1,
    totalPages: 1,
    sort: 'created_at',
    order: 'desc',
    statusCounts: {},
    selected: {},
    loading: false
  };

  var selectors = {
    tableBody: '#intakeTableBody',
    states: '#intakeStates',
    search: '#intakeSearchInput',
    searchClear: '#intakeSearchClearBtn',
    status: '#intakeStatusFilter',
    source: '#intakeSourceFilter',
    project: '#intakeProjectFilter',
    priority: '#intakePriorityFilter',
    client: '#intakeClientFilter',
    assignee: '#intakeAssigneeFilter',
    reset: '#intakeFiltersResetBtn',
    refresh: '#intakeRefreshBtn',
    summary: '#intakeResultSummary',
    createForm: '#intakeCreateForm',
    createSave: '#intakeCreateSaveBtn',
    createModal: '#intakeCreateModal',
    detailModal: '#intakeDetailModal',
    detailTitle: '#intakeDetailTitle',
    detailBody: '#intakeDetailBody',
    detailFooter: '#intakeDetailFooter',
    acceptModal: '#intakeAcceptModal',
    acceptForm: '#intakeAcceptForm',
    acceptConfirm: '#intakeAcceptConfirmBtn',
    rejectModal: '#intakeRejectModal',
    rejectForm: '#intakeRejectForm',
    rejectConfirm: '#intakeRejectConfirmBtn',
    snoozeModal: '#intakeSnoozeModal',
    snoozeForm: '#intakeSnoozeForm',
    snoozeConfirm: '#intakeSnoozeConfirmBtn',
    duplicateModal: '#intakeDuplicateModal',
    duplicateForm: '#intakeDuplicateForm',
    duplicateConfirm: '#intakeDuplicateConfirmBtn',
    statusTabs: '#intakeStatusTabs',
    pager: '#intakePager',
    bulkBar: '#intakeBulkBar',
    bulkModal: '#intakeBulkModal',
    bulkForm: '#intakeBulkForm',
    bulkConfirm: '#intakeBulkConfirmBtn',
    bulkSummary: '#intakeBulkSummary'
  };

  function qs(selector, root) {
    return (root || document).querySelector(selector);
  }

  function qsa(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function t(key, fallback) {
    if (window.CRM.i18n && typeof window.CRM.i18n.t === 'function') {
      return window.CRM.i18n.t(key, fallback);
    }
    return fallback || key;
  }

  function esc(value) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(value == null ? '' : value)));
    return div.innerHTML;
  }

  // Intake descriptions can be visual-editor HTML (e.g. "<p>text</p>").
  // For compact previews strip tags to plain text; caller escapes for display.
  function stripHtml(value) {
    if (window.CRM.text && typeof window.CRM.text.stripHtml === 'function') {
      return window.CRM.text.stripHtml(value);
    }
    return String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
  }

  function api() {
    return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function'
      ? window.CRM.api
      : null;
  }

  function request(route, options) {
    var client = api();
    if (!client) {
      return Promise.reject(new Error('CRM API is not ready'));
    }
    return client.request(route, options || {});
  }

  function notify(message, type) {
    if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.notify === 'function') {
      window.CRM.pageApiBindings.notify(message, type || 'info');
      return;
    }
    if (typeof window.notify === 'function') {
      window.notify(message, type || 'info');
      return;
    }
    console[type === 'error' ? 'error' : 'log']('[intake] ' + message);
  }

  function extractItems(envelope) {
    if (!envelope || !envelope.data) return [];
    if (Array.isArray(envelope.data.items)) return envelope.data.items;
    if (Array.isArray(envelope.data.projects)) return envelope.data.projects;
    if (Array.isArray(envelope.data.users)) return envelope.data.users;
    if (Array.isArray(envelope.data.counterparties)) return envelope.data.counterparties;
    return [];
  }

  function extractTotal(envelope, fallback) {
    var pagination = envelope && envelope.meta && envelope.meta.pagination ? envelope.meta.pagination : null;
    if (pagination && pagination.total != null) return Number(pagination.total) || 0;
    if (envelope && envelope.data && envelope.data.total != null) return Number(envelope.data.total) || 0;
    return fallback || 0;
  }

  function debounce(fn, delay) {
    var timer = 0;
    return function () {
      var args = arguments;
      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        fn.apply(null, args);
      }, delay || 250);
    };
  }

  function modal(selector) {
    var el = qs(selector);
    if (!el || !window.bootstrap || !window.bootstrap.Modal) return null;
    return window.bootstrap.Modal.getOrCreateInstance(el);
  }

  function hideModal(selector) {
    var instance = modal(selector);
    if (instance) instance.hide();
  }

  function textOrDash(value) {
    var str = String(value == null ? '' : value).trim();
    return str === '' ? '—' : str;
  }

  function formatDate(value, withTime) {
    if (!value) return '—';
    var str = String(value).replace('T', ' ');
    return withTime ? str.slice(0, 16) : str.slice(0, 10);
  }

  function setState(name) {
    qsa('[data-state-item]', qs(selectors.states)).forEach(function (item) {
      item.classList.toggle('d-none', item.getAttribute('data-state-item') !== name);
    });
  }

  function statusLabel(status) {
    return {
      pending: t('intake.status_pending', 'Новая'),
      accepted: t('intake.status_accepted', 'Принята'),
      rejected: t('intake.status_rejected', 'Отклонена'),
      snoozed: t('intake.status_snoozed', 'Отложена'),
      duplicate: t('intake.status_duplicate', 'Дубликат')
    }[status] || status || '—';
  }

  function priorityLabel(priority) {
    return {
      low: t('priority.low', 'Низкий'),
      normal: t('priority.normal', 'Нормальный'),
      high: t('priority.high', 'Высокий'),
      urgent: t('priority.urgent', 'Срочный')
    }[priority] || '—';
  }

  function sourceLabel(source) {
    return {
      manual: t('intake.source_manual', 'Ручной ввод'),
      client: t('intake.source_client', 'Клиент'),
      api: t('intake.source_api', 'API'),
      webhook: t('intake.source_webhook', 'Webhook'),
      email: t('intake.source_email', 'Email'),
      ai: t('intake.source_ai', 'AI'),
      import: t('intake.source_import', 'Импорт'),
      system: t('intake.source_system', 'Система')
    }[source] || source || '—';
  }

  function statusBadge(status) {
    return '<span class="crm-intake-status-badge" data-status="' + esc(status || '') + '">' + esc(statusLabel(status)) + '</span>';
  }

  function priorityBadge(priority) {
    if (!priority) return '—';
    return '<span class="crm-intake-priority-badge" data-priority="' + esc(priority) + '">' + esc(priorityLabel(priority)) + '</span>';
  }

  function sourceBadge(source) {
    return '<span class="crm-intake-source-label">' + esc(sourceLabel(source)) + '</span>';
  }

  function itemById(publicId) {
    return state.items.find(function (item) {
      return item.public_id === publicId;
    }) || null;
  }

  function selectedValue(selector) {
    var el = qs(selector);
    return el ? String(el.value || '').trim() : '';
  }

  function getFilters() {
    var filters = {
      q: selectedValue(selectors.search),
      status: selectedValue(selectors.status),
      source_type: selectedValue(selectors.source),
      project_public_id: selectedValue(selectors.project),
      priority_code: selectedValue(selectors.priority),
      client_public_id: selectedValue(selectors.client),
      assignee_user_id: selectedValue(selectors.assignee)
    };
    Object.keys(filters).forEach(function (key) {
      if (!filters[key]) delete filters[key];
    });
    return filters;
  }

  function hasActiveFilters(filters) {
    return Object.keys(filters || getFilters()).length > 0;
  }

  function updateFilterControls() {
    var filters = getFilters();
    var reset = qs(selectors.reset);
    var clear = qs(selectors.searchClear);
    if (reset) reset.disabled = !hasActiveFilters(filters);
    if (clear) clear.classList.toggle('d-none', !selectedValue(selectors.search));
  }

  function appendOptions(select, items, options) {
    if (!select) return;
    var current = select.value;
    var firstLabel = options.firstLabel;
    var valueKey = options.valueKey || 'public_id';
    var label = options.label;
    select.innerHTML = '<option value="">' + esc(firstLabel) + '</option>';
    items.forEach(function (item) {
      var value = item[valueKey];
      if (value == null || value === '') return;
      var option = document.createElement('option');
      option.value = String(value);
      option.textContent = label(item);
      select.appendChild(option);
    });
    select.value = current;
  }

  function populateReferenceSelects() {
    appendOptions(qs(selectors.project), state.projects, {
      firstLabel: t('intake.filter_all_projects', 'Все проекты'),
      label: function (project) { return project.title || project.name || project.public_id; }
    });
    appendOptions(qs(selectors.createForm + ' [name="project_public_id"]'), state.projects, {
      firstLabel: t('intake.field_no_project', 'Без проекта'),
      label: function (project) { return project.title || project.name || project.public_id; }
    });
    appendOptions(qs(selectors.acceptForm + ' [name="project_public_id"]'), state.projects, {
      firstLabel: t('intake.accept_project_placeholder', 'Выберите проект'),
      label: function (project) { return project.title || project.name || project.public_id; }
    });

    appendOptions(qs(selectors.client), state.clients, {
      firstLabel: t('intake.filter_all_clients', 'Все клиенты'),
      label: function (client) { return client.title || client.name || client.full_name || client.public_id; }
    });
    appendOptions(qs(selectors.createForm + ' [name="client_public_id"]'), state.clients, {
      firstLabel: t('intake.field_no_client', 'Без клиента'),
      label: function (client) { return client.title || client.name || client.full_name || client.public_id; }
    });

    appendOptions(qs(selectors.assignee), state.users, {
      firstLabel: t('intake.filter_all_assignees', 'Все'),
      valueKey: 'id',
      label: function (user) { return user.full_name || user.name || user.login || user.public_id || String(user.id); }
    });
    appendOptions(qs(selectors.createForm + ' [name="assignee_user_id"]'), state.users, {
      firstLabel: t('intake.field_no_assignee', 'Не назначен'),
      valueKey: 'id',
      label: function (user) { return user.full_name || user.name || user.login || user.public_id || String(user.id); }
    });
  }

  function loadReferences() {
    return Promise.all([
      request('api/v1/projects', { query: { limit: 200 } }).then(function (envelope) {
        state.projects = extractItems(envelope);
      }).catch(function () { state.projects = []; }),
      request('api/v1/counterparties', { query: { limit: 200 } }).then(function (envelope) {
        state.clients = extractItems(envelope);
      }).catch(function () { state.clients = []; }),
      request('api/v1/users', { query: { limit: 200, is_active: 1 } }).then(function (envelope) {
        state.users = extractItems(envelope);
      }).catch(function () { state.users = []; })
    ]).then(populateReferenceSelects);
  }

  function updateSummary(count, total) {
    var el = qs(selectors.summary);
    if (!el) return;
    var template = t('intake.result_summary_dynamic', 'Показано {shown} из {total} заявок');
    if (template.indexOf('{shown}') < 0 || template.indexOf('{total}') < 0) {
      template = 'Показано {shown} из {total} заявок';
    }
    el.textContent = template.replace('{shown}', String(count)).replace('{total}', String(total));
  }

  function renderRows(items) {
    var body = qs(selectors.tableBody);
    if (!body) return;
    body.innerHTML = items.map(function (item) {
      var publicId = item.public_id || '';
      var description = String(item.description || '').trim();
      var preview = stripHtml(description);
      var title = item.title || publicId;
      var checked = state.selected[publicId] ? ' checked' : '';
      return '<tr data-intake-row="' + esc(publicId) + '">'
        + '<td><input class="form-check-input" type="checkbox" data-intake-bulk-id="' + esc(publicId) + '"' + checked + ' aria-label="' + esc(t('intake.select_item_aria', 'Выбрать заявку')) + '"></td>'
        + '<td class="crm-intake-title-cell">'
          + '<button type="button" class="crm-intake-title-button" data-intake-action="detail" data-intake-id="' + esc(publicId) + '">' + esc(title) + '</button>'
          + (preview ? '<small>' + esc(preview.slice(0, 120)) + '</small>' : '<small>' + esc(publicId) + '</small>')
        + '</td>'
        + '<td>' + statusBadge(item.status) + '</td>'
        + '<td>' + esc(textOrDash(item.project_title)) + '</td>'
        + '<td>' + sourceBadge(item.source_type) + '</td>'
        + '<td>' + priorityBadge(item.priority_code || 'normal') + '</td>'
        + '<td>' + esc(textOrDash(item.assignee_name)) + '</td>'
        + '<td class="crm-intake-date-cell">' + esc(formatDate(item.due_at, false)) + '</td>'
        + '<td class="crm-intake-date-cell">' + esc(formatDate(item.created_at, false)) + '</td>'
        + '<td class="crm-intake-actions">' + actionButtons(item) + '</td>'
      + '</tr>';
    }).join('');
  }

  function actionButtons(item) {
    var id = esc(item.public_id || '');
    var status = item.status || 'pending';
    var html = '<button class="btn crm-btn-secondary" type="button" data-intake-action="detail" data-intake-id="' + id + '" title="' + esc(t('intake.action_detail', 'Открыть')) + '"><i class="fa-solid fa-eye"></i></button>';

    if (status === 'pending' || status === 'snoozed') {
      html += '<button class="btn crm-btn-primary intake-accept-btn" type="button" data-intake-action="accept" data-intake-id="' + id + '" title="' + esc(t('intake.btn_accept', 'Принять')) + '"><i class="fa-solid fa-check"></i></button>';
      html += secondaryMenu(id, [
        ['snooze', 'fa-clock', t('intake.btn_snooze', 'Отложить')],
        ['reject', 'fa-xmark', t('intake.btn_reject', 'Отклонить')],
        ['duplicate', 'fa-copy', t('intake.btn_duplicate', 'Дубликат')]
      ]);
    }
    if (status === 'rejected' || status === 'duplicate' || status === 'snoozed') {
      html += '<button class="btn crm-btn-secondary" type="button" data-intake-action="reopen" data-intake-id="' + id + '" title="' + esc(t('intake.btn_reopen', 'Вернуть в работу')) + '"><i class="fa-solid fa-rotate-left"></i></button>';
    }
    if (item.accepted_task_public_id) {
      html += '<a class="btn crm-btn-secondary" href="index.php?route=task-detail&id=' + esc(encodeURIComponent(item.accepted_task_public_id)) + '" title="' + esc(t('intake.view_task_btn', 'Открыть задачу')) + '"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>';
    }
    return html;
  }

  function secondaryMenu(id, actions) {
    return '<div class="dropdown crm-intake-row-menu">'
      + '<button class="btn crm-btn-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="' + esc(t('intake.more_actions', 'Ещё действия')) + '"><i class="fa-solid fa-ellipsis"></i></button>'
      + '<ul class="dropdown-menu dropdown-menu-end">'
      + actions.map(function (action) {
        return '<li><button class="dropdown-item" type="button" data-intake-action="' + esc(action[0]) + '" data-intake-id="' + id + '"><i class="fa-solid ' + esc(action[1]) + ' me-2"></i>' + esc(action[2]) + '</button></li>';
      }).join('')
      + '</ul>'
      + '</div>';
  }

  function loadItems(resetPage) {
    var body = qs(selectors.tableBody);
    if (!body || state.loading) return;
    if (resetPage) state.page = 1;
    state.loading = true;
    setState('loading');
    updateFilterControls();

    var filters = getFilters();
    var query = Object.assign({
      limit: 25,
      page: state.page,
      sort: state.sort,
      order: state.order,
      with_status_counts: '1'
    }, filters);
    request('api/v1/intake-items', { query: query })
      .then(function (envelope) {
        state.items = extractItems(envelope);
        state.total = extractTotal(envelope, state.items.length);
        var pagination = envelope && envelope.meta && envelope.meta.pagination ? envelope.meta.pagination : null;
        state.totalPages = pagination && pagination.pages != null ? Number(pagination.pages) || 1 : 1;
        state.statusCounts = (envelope && envelope.meta && envelope.meta.status_counts && typeof envelope.meta.status_counts === 'object')
          ? envelope.meta.status_counts
          : {};
        updateSummary(state.items.length, state.total);
        renderRows(state.items);
        renderStatusTabs();
        renderPager();
        if (state.items.length > 0) {
          setState('default');
        } else {
          setState(hasActiveFilters(filters) ? 'no-results' : 'empty');
        }
      })
      .catch(function (error) {
        updateSummary(0, 0);
        setState('error');
      })
      .finally(function () {
        state.loading = false;
      });
  }

  function statusCount(code) {
    return Number(state.statusCounts[code]) || 0;
  }

  function renderStatusTabs() {
    var holder = qs(selectors.statusTabs);
    if (!holder) return;
    var current = selectedValue(selectors.status);
    var tabs = [
      ['', t('intake.filter_all_statuses', 'Все статусы'), state.total],
      ['pending', t('intake.status_pending', 'Ожидает'), statusCount('pending')],
      ['snoozed', t('intake.status_snoozed', 'Отложено'), statusCount('snoozed')],
      ['accepted', t('intake.status_accepted', 'Принято'), statusCount('accepted')],
      ['rejected', t('intake.status_rejected', 'Отклонено'), statusCount('rejected')],
      ['duplicate', t('intake.status_duplicate', 'Дубликат'), statusCount('duplicate')]
    ];

    holder.innerHTML = tabs.map(function (tab) {
      var active = tab[0] === current ? ' active' : '';
      return '<button type="button" class="crm-segmented-filter-btn' + active + '" data-intake-status-tab="' + esc(tab[0]) + '">'
        + esc(tab[1]) + '<span class="crm-intake-tab-count">' + esc(String(tab[2])) + '</span></button>';
    }).join('');
  }

  function renderPager() {
    var pager = qs(selectors.pager);
    if (!pager) return;
    if (state.totalPages <= 1) {
      pager.classList.add('d-none');
      pager.innerHTML = '';
      return;
    }
    pager.classList.remove('d-none');
    pager.innerHTML = '<div class="small text-muted">' + esc(t('intake.pager_showing', 'Показано')) + ' '
      + esc(String((state.page - 1) * 25 + 1)) + '–' + esc(String(Math.min(state.page * 25, state.total)))
      + ' ' + esc(t('intake.pager_of', 'из')) + ' ' + esc(String(state.total)) + '</div>'
      + '<div class="crm-table-pager-controls">'
      + '<button class="btn crm-btn-secondary" type="button" data-intake-page-prev' + (state.page <= 1 ? ' disabled' : '') + '>' + esc(t('intake.pager_prev', 'Назад')) + '</button>'
      + '<span class="small">' + esc(t('intake.pager_page', 'Страница')) + ' ' + esc(String(state.page)) + ' ' + esc(t('intake.pager_of', 'из')) + ' ' + esc(String(state.totalPages)) + '</span>'
      + '<button class="btn crm-btn-secondary" type="button" data-intake-page-next' + (state.page >= state.totalPages ? ' disabled' : '') + '>' + esc(t('intake.pager_next', 'Вперёд')) + '</button>'
      + '</div>';
  }

  function selectedIds() {
    return Object.keys(state.selected).filter(function (id) {
      return state.selected[id] === true;
    });
  }

  function updateBulkBar() {
    var bar = qs(selectors.bulkBar);
    if (!bar) return;
    var count = selectedIds().length;
    bar.classList.toggle('d-none', count === 0);
    var counter = bar.querySelector('[data-selected-count]');
    if (counter) counter.textContent = String(count);
  }

  function clearSelection() {
    state.selected = {};
    updateBulkBar();
    var selectAll = document.querySelector('#intakeStates [data-select-all]');
    if (selectAll) {
      selectAll.checked = false;
      selectAll.indeterminate = false;
    }
  }

  function onBulkCheckboxChange(event) {
    var target = event.target;
    if (!(target instanceof HTMLInputElement)) return;
    if (target.matches('[data-select-all]')) {
      var checked = !!target.checked;
      qsa('[data-intake-bulk-id]', qs(selectors.tableBody)).forEach(function (cb) {
        cb.checked = checked;
        var id = cb.getAttribute('data-intake-bulk-id');
        if (id) state.selected[id] = checked;
      });
    } else if (target.matches('[data-intake-bulk-id]')) {
      var id = target.getAttribute('data-intake-bulk-id');
      if (id) state.selected[id] = !!target.checked;
    }
    updateBulkBar();
    var selectAll = document.querySelector('#intakeStates [data-select-all]');
    var all = qsa('[data-intake-bulk-id]', qs(selectors.tableBody));
    var checkedCount = all.filter(function (cb) { return cb.checked; }).length;
    if (selectAll) {
      selectAll.checked = all.length > 0 && checkedCount === all.length;
      selectAll.indeterminate = checkedCount > 0 && checkedCount < all.length;
    }
  }

  function openBulkAction(action) {
    var ids = selectedIds();
    if (!ids.length) {
      notify(t('intake.bulk_no_selection', 'Выберите хотя бы одну заявку'), 'error');
      return;
    }

    // reopen/delete are parameterless: run directly, then refresh.
    if (action === 'reopen' || action === 'delete') {
      var confirmText = action === 'delete'
        ? t('intake.bulk_delete_confirm', 'Удалить выбранные заявки? Это действие необратимо.')
        : t('intake.bulk_reopen_confirm', 'Вернуть выбранные заявки в работу?');
      if (!window.confirm(confirmText)) return;
      submitBulk({ action: action, public_ids: ids });
      return;
    }

    var form = qs(selectors.bulkForm);
    if (!form) return;
    form.reset();
    form.elements.action.value = action;
    var title = qs('#intakeBulkModalTitle');
    if (title) {
      title.textContent = {
        accept: t('intake.bulk_accept_title', 'Принять выбранные'),
        assign: t('intake.bulk_assign_title', 'Назначить выбранные'),
        snooze: t('intake.bulk_snooze_title', 'Отложить выбранные'),
        reject: t('intake.bulk_reject_title', 'Отклонить выбранные')
      }[action] || t('intake.bulk_modal_title', 'Массовое действие');
    }
    var summary = qs(selectors.bulkSummary);
    if (summary) summary.textContent = t('intake.bulk_selected_count', 'Выбрано заявок') + ': ' + ids.length;

    // Show only the field relevant to this action.
    qsa('[data-intake-bulk-field]', form).forEach(function (field) {
      field.classList.add('d-none');
    });
    var fieldMap = { accept: 'project', assign: 'assignee', reject: 'reason', snooze: 'snooze' };
    var fieldName = fieldMap[action];
    var field = form.querySelector('[data-intake-bulk-field="' + fieldName + '"]');
    if (field) field.classList.remove('d-none');

    if (action === 'accept') {
      appendOptions(form.elements.project_public_id, state.projects, {
        firstLabel: t('intake.accept_project_placeholder', 'Выберите проект'),
        label: function (project) { return project.title || project.name || project.public_id; }
      });
    }
    if (action === 'assign') {
      appendOptions(form.elements.assignee_user_id, state.users, {
        firstLabel: t('intake.field_no_assignee', 'Не назначен'),
        valueKey: 'id',
        label: function (user) { return user.full_name || user.name || user.login || user.public_id || String(user.id); }
      });
    }

    var instance = modal(selectors.bulkModal);
    if (instance) instance.show();
  }

  function confirmBulk() {
    var form = qs(selectors.bulkForm);
    var button = qs(selectors.bulkConfirm);
    if (!form) return;
    var action = String(form.elements.action.value || '').trim();
    var ids = selectedIds();
    if (!ids.length) {
      notify(t('intake.bulk_no_selection', 'Выберите хотя бы одну заявку'), 'error');
      return;
    }
    var payload = { action: action, public_ids: ids };

    if (action === 'accept') {
      payload.project_public_id = String(form.elements.project_public_id.value || '').trim();
      if (!payload.project_public_id) {
        notify(t('intake.error_accept_project_required', 'Выберите проект'), 'error');
        return;
      }
    }
    if (action === 'assign') {
      payload.assignee_user_id = String(form.elements.assignee_user_id.value || '').trim();
    }
    if (action === 'reject') {
      payload.reason = String(form.elements.reason.value || '').trim();
      if (!payload.reason) {
        notify(t('intake.error_reject_reason_required', 'Укажите причину отклонения'), 'error');
        return;
      }
    }
    if (action === 'snooze') {
      payload.snoozed_until = String(form.elements.snoozed_until.value || '').trim();
      if (!payload.snoozed_until) {
        notify(t('intake.error_snooze_date_required', 'Укажите дату'), 'error');
        return;
      }
    }

    setButtonLoading(button, true);
    submitBulk(payload, function () {
      setButtonLoading(button, false);
    });
  }

  function submitBulk(payload, onFinish) {
    request('api/v1/intake-items/bulk', { method: 'POST', body: payload })
      .then(function (envelope) {
        var summary = (envelope && envelope.data && envelope.data.summary) || {};
        var updated = Number(summary.updated) || 0;
        var skipped = Number(summary.skipped) || 0;
        var failed = Number(summary.failed) || 0;
        var message = t('intake.bulk_result', 'Готово: {updated} обновлено, {skipped} пропущено, {failed} ошибок')
          .replace('{updated}', String(updated))
          .replace('{skipped}', String(skipped))
          .replace('{failed}', String(failed));
        notify(message, failed > 0 ? 'warning' : 'success');
        hideModal(selectors.bulkModal);
        clearSelection();
        if (onFinish) onFinish();
        loadItems(true);
      })
      .catch(function (error) {
        notify(formatError(error, t('intake.bulk_error', 'Не удалось выполнить массовое действие')), 'error');
        if (onFinish) onFinish();
      });
  }

  function payloadFromForm(form) {
    var payload = {};
    qsa('input, select, textarea', form).forEach(function (field) {
      if (!field.name) return;
      var value = String(field.value || '').trim();
      if (value !== '') payload[field.name] = value;
    });
    return payload;
  }

  function openCreateModal() {
    var form = qs(selectors.createForm);
    if (form) {
      form.reset();
      var source = form.elements.source_type;
      var priority = form.elements.priority_code;
      if (source) source.value = 'manual';
      if (priority) priority.value = 'normal';
    }
    var instance = modal(selectors.createModal);
    if (instance) instance.show();
  }

  function saveCreate() {
    var form = qs(selectors.createForm);
    var button = qs(selectors.createSave);
    if (!form) return;
    var payload = payloadFromForm(form);
    if (!payload.title) {
      notify(t('intake.error_title_required', 'Введите название заявки'), 'error');
      return;
    }
    setButtonLoading(button, true);
    request('api/v1/intake-items', { method: 'POST', body: payload })
      .then(function () {
        notify(t('intake.created', 'Заявка создана'), 'success');
        hideModal(selectors.createModal);
        loadItems();
      })
      .catch(function (error) {
        notify(formatError(error, t('intake.save_error', 'Не удалось сохранить заявку')), 'error');
      })
      .finally(function () {
        setButtonLoading(button, false);
      });
  }

  function setButtonLoading(button, isLoading) {
    if (!button) return;
    button.disabled = !!isLoading;
    button.classList.toggle('is-loading', !!isLoading);
  }

  function formatError(error, fallback) {
    if (window.CRM.api && typeof window.CRM.api.normalizeError === 'function' && typeof window.CRM.api.formatErrorMessage === 'function') {
      return window.CRM.api.formatErrorMessage(window.CRM.api.normalizeError(error, fallback), { withRequestId: true });
    }
    return (error && error.message) || fallback;
  }

  function openDetail(publicId) {
    var item = itemById(publicId);
    if (!item) return;
    var title = qs(selectors.detailTitle);
    var body = qs(selectors.detailBody);
    var footer = qs(selectors.detailFooter);
    if (title) title.textContent = item.title || publicId;
    if (body) {
      body.innerHTML = detailHtml(item) + '<div class="crm-intake-activity mt-3" id="intakeDetailActivities"><p class="text-muted small mb-0">' + esc(t('intake.loading_activities', 'Загрузка истории...')) + '</p></div>';
    }
    if (footer) {
      footer.innerHTML = '<button type="button" class="btn btn-light" data-bs-dismiss="modal">' + esc(t('intake.btn_close', 'Закрыть')) + '</button>' + detailActions(item);
    }
    var instance = modal(selectors.detailModal);
    if (instance) instance.show();
    loadActivities(publicId);
  }

  function detailHtml(item) {
    var desc = String(item.description || '').trim();
    return '<div class="crm-intake-detail-grid">'
      + '<div><span>' + esc(t('intake.th_status', 'Статус')) + '</span>' + statusBadge(item.status) + '</div>'
      + '<div><span>' + esc(t('intake.th_project', 'Проект')) + '</span><strong>' + esc(textOrDash(item.project_title)) + '</strong></div>'
      + '<div><span>' + esc(t('intake.th_source', 'Источник')) + '</span>' + sourceBadge(item.source_type) + '</div>'
      + '<div><span>' + esc(t('intake.th_priority', 'Приоритет')) + '</span>' + priorityBadge(item.priority_code || 'normal') + '</div>'
      + '<div><span>' + esc(t('intake.th_assignee', 'Ответственный')) + '</span><strong>' + esc(textOrDash(item.assignee_name)) + '</strong></div>'
      + '<div><span>' + esc(t('intake.th_due', 'Срок')) + '</span><strong>' + esc(formatDate(item.due_at, true)) + '</strong></div>'
      + '</div>'
      + '<div class="crm-intake-detail-description mt-3">'
        + '<h6>' + esc(t('intake.field_description', 'Описание')) + '</h6>'
        + '<p>' + esc(stripHtml(desc) || t('intake.no_description', 'Описание не заполнено.')) + '</p>'
      + '</div>';
  }

  function detailActions(item) {
    return '<span class="crm-intake-actions crm-intake-detail-actions">' + actionButtons(item) + '</span>';
  }

  function loadActivities(publicId) {
    var holder = qs('#intakeDetailActivities');
    if (!holder) return;
    request('api/v1/intake-items/' + encodeURIComponent(publicId) + '/activities', { method: 'GET' })
      .then(function (envelope) {
        var activities = extractItems(envelope);
        if (!activities.length) {
          holder.innerHTML = '<p class="text-muted small mb-0">' + esc(t('intake.activities_empty', 'История пока пустая')) + '</p>';
          return;
        }
        holder.innerHTML = '<h6>' + esc(t('intake.activities_title', 'История')) + '</h6>'
          + '<div class="crm-intake-activity-list">'
          + activities.map(function (activity) {
            return '<div><strong>' + esc(eventLabel(activity.event_type)) + '</strong><small>' + esc(formatDate(activity.created_at, true)) + '</small>'
              + (activity.comment ? '<p>' + esc(activity.comment) + '</p>' : '') + '</div>';
          }).join('')
          + '</div>';
      })
      .catch(function () {
        holder.innerHTML = '<p class="text-muted small mb-0">' + esc(t('intake.activities_error', 'Историю загрузить не удалось.')) + '</p>';
      });
  }

  function eventLabel(type) {
    return {
      created: t('intake.event_created', 'Создана'),
      updated: t('intake.event_updated', 'Обновлена'),
      accepted: t('intake.event_accepted', 'Принята'),
      rejected: t('intake.event_rejected', 'Отклонена'),
      snoozed: t('intake.event_snoozed', 'Отложена'),
      reopened: t('intake.event_reopened', 'Переоткрыта'),
      marked_duplicate: t('intake.event_marked_duplicate', 'Помечена дубликатом'),
      linked_task_created: t('intake.event_linked_task_created', 'Создана задача')
    }[type] || type || '—';
  }

  function openAccept(publicId) {
    var item = itemById(publicId);
    var form = qs(selectors.acceptForm);
    if (!item || !form) return;
    form.reset();
    form.elements.intake_public_id.value = publicId;
    form.elements.project_public_id.value = item.project_public_id || '';
    form.elements.title.value = item.title || '';
    var label = qs('#intakeAcceptItemTitle');
    if (label) label.textContent = item.title || publicId;
    var instance = modal(selectors.acceptModal);
    if (instance) instance.show();
  }

  function confirmAccept() {
    var form = qs(selectors.acceptForm);
    var button = qs(selectors.acceptConfirm);
    if (!form) return;
    var publicId = form.elements.intake_public_id.value;
    var item = itemById(publicId);
    var payload = payloadFromForm(form);
    delete payload.intake_public_id;
    if (!payload.project_public_id) {
      notify(t('intake.error_accept_project_required', 'Выберите проект'), 'error');
      return;
    }
    if (item && item.row_version) payload.row_version = item.row_version;
    setButtonLoading(button, true);
    request('api/v1/intake-items/' + encodeURIComponent(publicId) + '/accept', { method: 'POST', body: payload })
      .then(function () {
        notify(t('intake.accepted', 'Заявка принята, задача создана'), 'success');
        hideModal(selectors.acceptModal);
        hideModal(selectors.detailModal);
        loadItems();
      })
      .catch(function (error) {
        notify(formatError(error, t('intake.accept_error', 'Не удалось принять заявку')), 'error');
      })
      .finally(function () { setButtonLoading(button, false); });
  }

  function openReasonModal(publicId, selector, formSelector) {
    var item = itemById(publicId);
    var form = qs(formSelector);
    if (!item || !form) return;
    form.reset();
    form.elements.intake_public_id.value = publicId;
    var instance = modal(selector);
    if (instance) instance.show();
  }

  function confirmReject() {
    var form = qs(selectors.rejectForm);
    var button = qs(selectors.rejectConfirm);
    if (!form) return;
    var publicId = form.elements.intake_public_id.value;
    var item = itemById(publicId);
    var reason = String(form.elements.reason.value || '').trim();
    if (!reason) {
      notify(t('intake.error_reject_reason_required', 'Укажите причину отклонения'), 'error');
      return;
    }
    var payload = { reason: reason };
    if (item && item.row_version) payload.row_version = item.row_version;
    setButtonLoading(button, true);
    request('api/v1/intake-items/' + encodeURIComponent(publicId) + '/reject', { method: 'POST', body: payload })
      .then(function () {
        notify(t('intake.rejected', 'Заявка отклонена'), 'success');
        hideModal(selectors.rejectModal);
        hideModal(selectors.detailModal);
        loadItems();
      })
      .catch(function (error) {
        notify(formatError(error, t('intake.reject_error', 'Не удалось отклонить заявку')), 'error');
      })
      .finally(function () { setButtonLoading(button, false); });
  }

  function confirmSnooze() {
    var form = qs(selectors.snoozeForm);
    var button = qs(selectors.snoozeConfirm);
    if (!form) return;
    var publicId = form.elements.intake_public_id.value;
    var item = itemById(publicId);
    var payload = payloadFromForm(form);
    delete payload.intake_public_id;
    if (!payload.snoozed_until) {
      notify(t('intake.error_snooze_date_required', 'Укажите дату'), 'error');
      return;
    }
    if (item && item.row_version) payload.row_version = item.row_version;
    setButtonLoading(button, true);
    request('api/v1/intake-items/' + encodeURIComponent(publicId) + '/snooze', { method: 'POST', body: payload })
      .then(function () {
        notify(t('intake.snoozed', 'Заявка отложена'), 'success');
        hideModal(selectors.snoozeModal);
        hideModal(selectors.detailModal);
        loadItems();
      })
      .catch(function (error) {
        notify(formatError(error, t('intake.snooze_error', 'Не удалось отложить заявку')), 'error');
      })
      .finally(function () { setButtonLoading(button, false); });
  }

  function openDuplicate(publicId) {
    var item = itemById(publicId);
    var form = qs(selectors.duplicateForm);
    if (!item || !form) return;
    form.reset();
    form.elements.intake_public_id.value = publicId;
    appendOptions(form.elements.duplicate_intake_item_public_id, state.items.filter(function (candidate) {
      return candidate.public_id !== publicId;
    }), {
      firstLabel: t('intake.duplicate_select_intake', 'Выберите заявку'),
      label: function (candidate) { return (candidate.title || candidate.public_id) + ' · ' + statusLabel(candidate.status); }
    });
    var instance = modal(selectors.duplicateModal);
    if (instance) instance.show();
  }

  function confirmDuplicate() {
    var form = qs(selectors.duplicateForm);
    var button = qs(selectors.duplicateConfirm);
    if (!form) return;
    var publicId = form.elements.intake_public_id.value;
    var item = itemById(publicId);
    var payload = payloadFromForm(form);
    delete payload.intake_public_id;
    if (!payload.duplicate_intake_item_public_id && !payload.duplicate_task_public_id) {
      notify(t('intake.error_duplicate_target_required', 'Выберите заявку или укажите задачу'), 'error');
      return;
    }
    if (item && item.row_version) payload.row_version = item.row_version;
    setButtonLoading(button, true);
    request('api/v1/intake-items/' + encodeURIComponent(publicId) + '/duplicate', { method: 'POST', body: payload })
      .then(function () {
        notify(t('intake.marked_duplicate', 'Заявка помечена как дубликат'), 'success');
        hideModal(selectors.duplicateModal);
        hideModal(selectors.detailModal);
        loadItems();
      })
      .catch(function (error) {
        notify(formatError(error, t('intake.duplicate_error', 'Не удалось пометить дубликатом')), 'error');
      })
      .finally(function () { setButtonLoading(button, false); });
  }

  function reopen(publicId) {
    request('api/v1/intake-items/' + encodeURIComponent(publicId) + '/reopen', { method: 'POST', body: {} })
      .then(function () {
        notify(t('intake.reopened', 'Заявка возвращена в работу'), 'success');
        hideModal(selectors.detailModal);
        loadItems();
      })
      .catch(function (error) {
        notify(formatError(error, t('intake.reopen_error', 'Не удалось вернуть заявку')), 'error');
      });
  }

  function onAction(event) {
    var button = event.target.closest('[data-intake-action]');
    if (!button) return;
    var action = button.getAttribute('data-intake-action');
    var publicId = button.getAttribute('data-intake-id');
    if (!publicId) return;
    event.preventDefault();
    if (action === 'detail') openDetail(publicId);
    if (action === 'accept') openAccept(publicId);
    if (action === 'reject') openReasonModal(publicId, selectors.rejectModal, selectors.rejectForm);
    if (action === 'snooze') {
      openReasonModal(publicId, selectors.snoozeModal, selectors.snoozeForm);
      var until = qs(selectors.snoozeForm + ' [name="snoozed_until"]');
      if (until && !until.value) {
        var tomorrow = new Date(Date.now() + 24 * 60 * 60 * 1000);
        until.value = tomorrow.toISOString().slice(0, 16);
      }
    }
    if (action === 'duplicate') openDuplicate(publicId);
    if (action === 'reopen') reopen(publicId);
  }

  function resetFilters() {
    [selectors.search, selectors.status, selectors.source, selectors.project, selectors.priority, selectors.client, selectors.assignee].forEach(function (selector) {
      var el = qs(selector);
      if (el) el.value = '';
    });
    updateFilterControls();
    loadItems(true);
  }

  function reloadFirstPage() {
    loadItems(true);
  }

  function onSortClick(event) {
    var button = event.target.closest('[data-intake-sort]');
    if (!button) return;
    var column = button.getAttribute('data-intake-sort');
    if (state.sort === column) {
      state.order = state.order === 'asc' ? 'desc' : 'asc';
    } else {
      state.sort = column;
      state.order = 'asc';
    }
    updateSortIndicators();
    loadItems(true);
  }

  function updateSortIndicators() {
    qsa('[data-intake-sort]').forEach(function (button) {
      var column = button.getAttribute('data-intake-sort');
      var active = state.sort === column;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-sort', active ? (state.order === 'asc' ? 'ascending' : 'descending') : 'none');
      var arrow = button.querySelector('.crm-intake-sort-arrow');
      if (!arrow) {
        arrow = document.createElement('span');
        arrow.className = 'crm-intake-sort-arrow';
        button.appendChild(arrow);
      }
      arrow.textContent = active ? (state.order === 'asc' ? ' ▲' : ' ▼') : '';
    });
  }

  function bindEvents() {
    qsa('[data-intake-create]').forEach(function (button) {
      button.addEventListener('click', openCreateModal);
    });

    var body = qs(selectors.tableBody);
    if (body) body.addEventListener('click', onAction);
    var footer = qs(selectors.detailFooter);
    if (footer) footer.addEventListener('click', onAction);

    var table = qs('#intakeStates table');
    if (table) table.addEventListener('change', onBulkCheckboxChange);

    var save = qs(selectors.createSave);
    if (save) save.addEventListener('click', saveCreate);
    var accept = qs(selectors.acceptConfirm);
    if (accept) accept.addEventListener('click', confirmAccept);
    var reject = qs(selectors.rejectConfirm);
    if (reject) reject.addEventListener('click', confirmReject);
    var snooze = qs(selectors.snoozeConfirm);
    if (snooze) snooze.addEventListener('click', confirmSnooze);
    var duplicate = qs(selectors.duplicateConfirm);
    if (duplicate) duplicate.addEventListener('click', confirmDuplicate);

    var bulkConfirm = qs(selectors.bulkConfirm);
    if (bulkConfirm) bulkConfirm.addEventListener('click', confirmBulk);
    var bulkBar = qs(selectors.bulkBar);
    if (bulkBar) bulkBar.addEventListener('click', function (event) {
      var button = event.target.closest('[data-intake-bulk-action]');
      if (!button) return;
      openBulkAction(button.getAttribute('data-intake-bulk-action'));
    });

    var statusTabs = qs(selectors.statusTabs);
    if (statusTabs) statusTabs.addEventListener('click', function (event) {
      var tab = event.target.closest('[data-intake-status-tab]');
      if (!tab) return;
      var status = tab.getAttribute('data-intake-status-tab');
      var select = qs(selectors.status);
      if (select) select.value = status;
      updateFilterControls();
      renderStatusTabs();
      loadItems(true);
    });

    var pager = qs(selectors.pager);
    if (pager) pager.addEventListener('click', function (event) {
      var prev = event.target.closest('[data-intake-page-prev]');
      var next = event.target.closest('[data-intake-page-next]');
      if (prev && state.page > 1) {
        state.page -= 1;
        loadItems();
      } else if (next && state.page < state.totalPages) {
        state.page += 1;
        loadItems();
      }
    });

    var thead = qs('#intakeStates thead');
    if (thead) thead.addEventListener('click', onSortClick);

    var debouncedReload = debounce(reloadFirstPage, 300);
    var search = qs(selectors.search);
    if (search) search.addEventListener('input', function () {
      updateFilterControls();
      debouncedReload();
    });
    var clear = qs(selectors.searchClear);
    if (clear) clear.addEventListener('click', function () {
      if (search) search.value = '';
      updateFilterControls();
      loadItems(true);
      if (search) search.focus();
    });
    [selectors.status, selectors.source, selectors.project, selectors.priority, selectors.client, selectors.assignee].forEach(function (selector) {
      var el = qs(selector);
      if (el) el.addEventListener('change', function () {
        updateFilterControls();
        renderStatusTabs();
        loadItems(true);
      });
    });
    var reset = qs(selectors.reset);
    if (reset) reset.addEventListener('click', resetFilters);
    var refresh = qs(selectors.refresh);
    if (refresh) refresh.addEventListener('click', reloadFirstPage);
  }

  function init() {
    if (!document.body || document.body.getAttribute('data-page') !== 'intake') return;
    if (document.body.getAttribute('data-intake-ready') === '1') return;
    document.body.setAttribute('data-intake-ready', '1');
    bindEvents();
    updateFilterControls();
    updateSortIndicators();

    var started = false;
    function startWhenApiReady() {
      if (started) return;
      if (!api()) {
        window.setTimeout(startWhenApiReady, 80);
        return;
      }
      started = true;
      loadReferences().then(loadItems);
    }
    startWhenApiReady();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return {
    init: init,
    reload: loadItems
  };
})();
