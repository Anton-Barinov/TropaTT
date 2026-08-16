(function () {
  'use strict';

  function t(key, fallback) {
    if (window.CRM && window.CRM.i18n && typeof window.CRM.i18n.t === 'function') {
      return window.CRM.i18n.t(key, fallback || key);
    }
    return fallback || key;
  }

  var currentPage = 1;
  var currentCyclePublicId = null;
  var currentCycleDetail = null;
  var selectedTasks = {};
  var cycleFocusLoaded = false;
  var cycleSearchTimer = null;

  function api() {
    return window.CRM && window.CRM.api;
  }

  function apiRequest(path, opts) {
    var a = api();
    if (!a || typeof a.request !== 'function') {
      return Promise.reject(new Error('API not available'));
    }
    return a.request(path, opts || {});
  }

  function showCycleFeedback(message, type) {
    var toast = document.getElementById('toastSuccess');
    if (!toast || !window.bootstrap) {
      return;
    }

    toast.classList.remove('text-bg-success', 'text-bg-danger', 'text-bg-warning');
    toast.classList.add(type === 'error' ? 'text-bg-danger' : type === 'warning' ? 'text-bg-warning' : 'text-bg-success');
    var body = toast.querySelector('.toast-body');
    if (body) {
      body.textContent = String(message || '');
    }
    window.bootstrap.Toast.getOrCreateInstance(toast).show();
  }

  function confirmCycleAction(message, actionText, actionClass) {
    if (window.CRM && typeof window.CRM.confirm === 'function') {
      return window.CRM.confirm({
        title: t('cycles.confirm_title', 'Подтвердите действие'),
        body: message,
        actionText: actionText || t('cycles.confirm_action', 'Подтвердить'),
        actionClass: actionClass || 'crm-btn-danger-soft'
      });
    }
    return Promise.resolve(window.confirm(message));
  }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, function (ch) {
      var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
      return map[ch] || ch;
    });
  }

  // Cycle goal is stored as visual-editor HTML (e.g. "<p>text</p>").
  // For compact previews strip tags to plain text; for the full detail view
  // render sanitized HTML via the shared VisualEditor sanitizer.
  function stripHtml(value) {
    if (window.CRM.text && typeof window.CRM.text.stripHtml === 'function') {
      return window.CRM.text.stripHtml(value);
    }
    return String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
  }

  function visualEditorHtml(value) {
    var text = String(value || '').trim();
    if (!text) return '';
    if (/<[a-z][\s\S]*>/i.test(text) && window.CRM.VisualEditor && typeof window.CRM.VisualEditor.sanitizeHtml === 'function') {
      return window.CRM.VisualEditor.sanitizeHtml(text);
    }
    // VisualEditor may not be loaded yet (deferred script race): show plain
    // text instead of literal HTML tags.
    return escapeHtml(stripHtml(text)).replace(/\n/g, '<br>');
  }

  function formatDate(d) {
    if (!d) return '—';
    try {
      var dt = new Date(d.replace(' ', 'T') + 'Z');
      if (isNaN(dt.getTime())) return d;
      return dt.toLocaleDateString(t('cycles.locale', 'ru-RU'), { day: 'numeric', month: 'short', year: 'numeric' });
    } catch (e) { return d; }
  }

  function daysDiffFromNow(d) {
    if (!d) return null;
    var dt = new Date(String(d).replace(' ', 'T') + 'Z');
    if (isNaN(dt.getTime())) return null;
    return Math.ceil((dt.getTime() - Date.now()) / 86400000);
  }

  function statusBadge(status) {
    var cls = 'crm-cycle-badge crm-cycle-badge-' + status;
    var labels = { planned: t('cycles.status_planned', 'Запланирован'), active: t('cycles.status_active', 'Активен'), completed: t('cycles.status_completed', 'Завершён'), archived: t('cycles.status_archived', 'Архив') };
    return '<span class="' + cls + '">' + (labels[status] || status) + '</span>';
  }

  function statusLabel(status) {
    var labels = { planned: t('cycles.status_planned', 'Запланирован'), active: t('cycles.status_active', 'Активен'), completed: t('cycles.status_completed', 'Завершён'), archived: t('cycles.status_archived', 'Архив') };
    return labels[status] || status || '';
  }

  // ====== Load Cycles ======
  window.loadWorkCycles = function (page) {
    page = page || 1;
    currentPage = page;

    var listEl = document.getElementById('cycleList');
    var loadingEl = document.getElementById('cycleLoadingState');
    var emptyEl = document.getElementById('cycleEmptyState');
    var errorEl = document.getElementById('cycleErrorState');
    var paginationEl = document.getElementById('cyclePagination');

    if (!listEl) return;

    loadingEl.classList.remove('d-none');
    emptyEl.classList.add('d-none');
    errorEl.classList.add('d-none');
    listEl.innerHTML = '';
    paginationEl.innerHTML = '';

    var query = {};
    query.page = page;
    query.limit = 20;

    var projectFilter = document.getElementById('cycleProjectFilter');
    if (projectFilter && projectFilter.value) query.project_public_id = projectFilter.value;

    var statusFilter = document.getElementById('cycleStatusFilter');
    if (statusFilter && statusFilter.value) query.status = statusFilter.value;

    var searchInput = document.getElementById('cycleSearchInput');
    if (searchInput && searchInput.value.trim()) query.q = searchInput.value.trim();

    apiRequest('api/v1/cycles', { method: 'GET', query: query })
      .then(function (envelope) {
        loadingEl.classList.add('d-none');

        var items = envelope.data && envelope.data.items || [];
        if (!items.length) {
          emptyEl.classList.remove('d-none');
          return;
        }

        renderCycles(items, listEl);

        var meta = envelope.meta || {};
        var pagination = meta.pagination || {};
        var totalPages = pagination.pages || 1;
        renderPagination(page, totalPages, paginationEl);
      })
      .catch(function (err) {
        loadingEl.classList.add('d-none');
        errorEl.classList.remove('d-none');
                var errMsg = "";
        if (err && typeof err === "object") {
          if (err.code) errMsg = err.code + ": ";
          if (err.message) errMsg += err.message;
          if (err.isPermissionError) errMsg = t("cycles.error_permission", "Недостаточно прав для просмотра циклов.");
          if (err.isNotFound) errMsg = t("cycles.error_not_found", "Раздел циклов временно недоступен.");
        }
        document.getElementById("cycleErrorText").textContent = errMsg || err && err.message || t("cycles.error_load", "Не удалось загрузить циклы.");
      });
  };

  function loadCycleFocusSummary() {
    var container = document.getElementById('cycleCommandCenter');
    if (!container) return;
    if (!cycleFocusLoaded) {
      container.classList.remove('d-none');
      // Build the empty state with DOM nodes so concatenated data is never parsed as markup.
      container.textContent = '';
      var wrapper = document.createElement('div');
      wrapper.className = 'crm-cycle-command-text';
      var strong = document.createElement('strong');
      strong.textContent = t('cycles.command_title', 'Фокус по циклам');
      wrapper.appendChild(strong);
      var span = document.createElement('span');
      span.textContent = t('cycles.loading', 'Загрузка...');
      wrapper.appendChild(span);
      container.appendChild(wrapper);
    }

    var query = { page: 1, limit: 100 };
    var projectFilter = document.getElementById('cycleProjectFilter');
    if (projectFilter && projectFilter.value) query.project_public_id = projectFilter.value;

    apiRequest('api/v1/cycles', { method: 'GET', query: query })
      .then(function (envelope) {
        cycleFocusLoaded = true;
        renderCycleCommandCenter(envelope.data && envelope.data.items || []);
      })
      .catch(function () {
        if (!cycleFocusLoaded) {
          container.classList.add('d-none');
          container.innerHTML = '';
        }
      });
  }

  function renderCycles(items, container) {
    items.forEach(function (cycle) {
      var card = renderCycleCard(cycle);
      if (card) container.appendChild(card);
    });
  }

  function renderCycleCommandCenter(items) {
    var container = document.getElementById('cycleCommandCenter');
    if (!container) return;
    if (!items || !items.length) {
      container.classList.add('d-none');
      container.innerHTML = '';
      return;
    }

    var active = 0;
    var planned = 0;
    var overdue = 0;
    var emptyPlans = 0;
    var openTasks = 0;

    items.forEach(function (cycle) {
      var total = cycle.tasks_count || 0;
      var completed = cycle.completed_tasks_count || 0;
      if (cycle.status === 'active') active++;
      if (cycle.status === 'planned') planned++;
      if (cycle.status === 'active' && cycle.time_state === 'ended') overdue++;
      if ((cycle.status === 'active' || cycle.status === 'planned') && total === 0) emptyPlans++;
      openTasks += Math.max(0, total - completed);
    });

    var advice = '';
    if (overdue > 0) {
      advice = t('cycles.command_advice_overdue', 'Сначала разберите просроченные активные циклы: завершите их или перенесите незавершённые задачи.');
    } else if (emptyPlans > 0) {
      advice = t('cycles.command_advice_empty', 'Есть циклы без задач: добавьте задачи или архивируйте лишние циклы, чтобы список не шумел.');
    } else if (active > 0) {
      advice = t('cycles.command_advice_active', 'Рабочая зона готова: держите фокус на открытых задачах активных циклов.');
    } else if (planned > 0) {
      advice = t('cycles.command_advice_planned', 'Есть запланированные циклы: проверьте план и запустите ближайший.');
    } else {
      advice = t('cycles.command_advice_create', 'Создайте новый цикл, чтобы собрать задачи в понятную итерацию.');
    }

    container.classList.remove('d-none');
    container.innerHTML =
      '<div class="crm-cycle-command-text">' +
        '<strong>' + t('cycles.command_title', 'Фокус по циклам') + '</strong>' +
        '<span>' + escapeHtml(advice) + '</span>' +
      '</div>' +
      '<div class="crm-cycle-command-metrics">' +
        '<button type="button" class="crm-cycle-command-metric" onclick="document.getElementById(\'cycleStatusFilter\').value=\'active\'; window.loadWorkCycles(1);"><strong>' + active + '</strong><span>' + t('cycles.metric_active', 'активных') + '</span></button>' +
        '<button type="button" class="crm-cycle-command-metric" onclick="document.getElementById(\'cycleStatusFilter\').value=\'planned\'; window.loadWorkCycles(1);"><strong>' + planned + '</strong><span>' + t('cycles.metric_planned', 'запланировано') + '</span></button>' +
        '<span class="crm-cycle-command-metric"><strong>' + overdue + '</strong><span>' + t('cycles.metric_overdue', 'просрочено') + '</span></span>' +
        '<span class="crm-cycle-command-metric"><strong>' + openTasks + '</strong><span>' + t('cycles.metric_open_tasks', 'открытых задач') + '</span></span>' +
      '</div>';
  }

  function renderCycleCard(cycle) {
    if (!cycle) return null;

    var card = document.createElement('div');
    card.className = 'crm-cycle-card';

    var progress = cycle.progress_percent || 0;
    var totalTasks = cycle.tasks_count || 0;
    var completedCount = cycle.completed_tasks_count || 0;
    var openCount = Math.max(0, totalTasks - completedCount);
    var timeState = cycle.time_state || '';
    var timeLabel = timeState === 'running' ? t('cycles.time_running', 'Идёт') : timeState === 'not_started' ? t('cycles.time_not_started', 'Не начат') : timeState === 'ended' ? t('cycles.time_ended', 'Завершён по дате') : '';
    var isOverdue = timeState === 'ended' && cycle.status === 'active';
    var dueDelta = daysDiffFromNow(cycle.end_at);
    var planningHint = totalTasks === 0
      ? t('cycles.card_hint_empty', 'План пустой: добавьте задачи, чтобы цикл стал рабочим.')
      : openCount > 0
        ? t('cycles.card_hint_open', 'Открытых задач:') + ' ' + openCount
        : t('cycles.card_hint_done', 'Все задачи закрыты. Цикл можно завершить.');
    var dateHint = '';
    if (isOverdue && dueDelta !== null) {
      dateHint = t('cycles.card_overdue_days', 'Просрочено дней:') + ' ' + Math.abs(dueDelta);
    } else if (dueDelta !== null && cycle.status === 'active') {
      dateHint = dueDelta >= 0 ? t('cycles.card_days_left', 'Осталось дней:') + ' ' + dueDelta : '';
    }

    card.innerHTML =
      '<div class="crm-cycle-card-grid">' +
        '<div class="flex-grow-1 min-w-0">' +
          '<div class="d-flex align-items-center gap-2 mb-1 flex-wrap">' +
            '<button type="button" class="crm-cycle-title" onclick="window.openCycleDetail(\'' + escapeHtml(cycle.public_id) + '\')">' + escapeHtml(cycle.title) + '</button>' +
            statusBadge(cycle.status) +
            (isOverdue ? '<span class="crm-badge crm-badge-danger" style="font-size:11px;">' + t('cycles.overdue', 'Просрочен') + '</span>' : '') +
            (timeLabel && !isOverdue ? '<small class="text-muted">' + escapeHtml(timeLabel) + '</small>' : '') +
          '</div>' +
          '<div class="small text-muted d-flex flex-wrap gap-1">' +
            (cycle.project_title ? '<span><i class="fa-regular fa-folder-open"></i> ' + escapeHtml(cycle.project_title) + '</span>' : '') +
            (cycle.owner_name ? '<span><i class="fa-regular fa-user"></i> ' + escapeHtml(cycle.owner_name) + '</span>' : '') +
            (cycle.start_at ? '<span><i class="fa-regular fa-calendar"></i> ' + formatDate(cycle.start_at) + '</span>' : '') +
            (cycle.end_at ? '<span>— ' + formatDate(cycle.end_at) + '</span>' : '') +
          '</div>' +
          (cycle.goal ? '<div class="small text-muted mt-1"><i class="fa-regular fa-bullseye"></i> ' + escapeHtml(stripHtml(cycle.goal).substring(0, 150)) + '</div>' : '') +
          '<div class="crm-cycle-next-step">' + escapeHtml(planningHint) + (dateHint ? '<span>' + escapeHtml(dateHint) + '</span>' : '') + '</div>' +
        '</div>' +
        '<div class="crm-cycle-progress-panel">' +
          '<div class="d-flex justify-content-between small mb-1 gap-2">' +
            '<span>' + completedCount + '/' + totalTasks + ' ' + t('cycles.tasks_done', 'задач') + '</span>' +
            '<span class="fw-semibold">' + progress + '%</span>' +
          '</div>' +
          '<div class="crm-cycle-progress"><div class="crm-cycle-progress-bar" style="width:' + progress + '%;"></div></div>' +
        '</div>' +
      '</div>' +
      '<div class="d-flex gap-1 mt-2 flex-wrap">' +
        (cycle.status === 'planned' ? '<button class="btn btn-sm btn-outline-success" onclick="window.startCycle(\'' + escapeHtml(cycle.public_id) + '\')"><i class="fa-solid fa-play"></i> ' + t('cycles.btn_start_small', 'Старт') + '</button>' : '') +
        (totalTasks === 0 && cycle.status !== 'completed' && cycle.status !== 'archived' ? '<button class="btn btn-sm crm-btn-primary" onclick="window.openCycleDetail(\'' + escapeHtml(cycle.public_id) + '\')"><i class="fa-solid fa-list-check"></i> ' + t('cycles.btn_plan_tasks', 'Запланировать задачи') + '</button>' : '') +
        (totalTasks > 0 ? '<button class="btn btn-sm crm-btn-secondary" onclick="window.openCycleDetail(\'' + escapeHtml(cycle.public_id) + '\')"><i class="fa-solid fa-chart-simple"></i> ' + t('cycles.btn_open_detail', 'Открыть') + '</button>' : '') +
        (cycle.status !== 'completed' && cycle.status !== 'archived' ? '<button class="btn btn-sm btn-outline-warning" onclick="window.openCompleteCycle(\'' + escapeHtml(cycle.public_id) + '\')"><i class="fa-solid fa-check"></i> ' + t('cycles.btn_complete_small', 'Завершить') + '</button>' : '') +
        '<button class="btn btn-sm btn-outline-secondary" onclick="window.openCycleModal(\'' + escapeHtml(cycle.public_id) + '\')"><i class="fa-solid fa-pen"></i></button>' +
        (cycle.archived_at || cycle.status === 'completed' || cycle.status === 'archived' ? '<button class="btn btn-sm btn-outline-secondary" onclick="window.reopenCycle(\'' + escapeHtml(cycle.public_id) + '\')"><i class="fa-solid fa-rotate-left"></i> ' + t('cycles.btn_reopen_small', 'Открыть') + '</button>' : '') +
        '<button class="btn btn-sm btn-outline-secondary" onclick="window.archiveCycle(\'' + escapeHtml(cycle.public_id) + '\')"><i class="fa-solid fa-archive"></i></button>' +
      '</div>';

    return card;
  }

  function renderPagination(page, totalPages, container) {
    if (totalPages <= 1) return;
    var ul = document.createElement('div');
    ul.className = 'crm-cycle-pagination';
    for (var i = 1; i <= totalPages; i++) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'crm-cycle-page-btn' + (i === page ? ' is-active' : '');
      btn.textContent = i;
      btn.setAttribute('aria-current', i === page ? 'page' : 'false');
      btn.onclick = (function (p) { return function () { window.loadWorkCycles(p); }; })(i);
      ul.appendChild(btn);
    }
    container.appendChild(ul);
  }

  // ====== Create / Edit ======
  window.openCycleModal = function (publicId) {
    var modal = new bootstrap.Modal(document.getElementById('cycleModal'));
    document.getElementById('cycleModalTitle').textContent =publicId ? t('cycles.modal_edit_title', 'Редактировать цикл') : t('cycles.modal_create_title', 'Создать цикл');
    document.getElementById('cycleFormSubmit').textContent =publicId ? t('cycles.btn_save', 'Сохранить') : t('cycles.btn_submit_create', 'Создать');
    document.getElementById('cycleFormPublicId').value = publicId || '';
    document.getElementById('cycleFormRowVersion').value = '';
    document.getElementById('cycleFormTitle').value = '';
    document.getElementById('cycleFormGoal').value = '';
    document.getElementById('cycleFormDescription').value = '';
    document.getElementById('cycleFormStartAt').value = '';
    document.getElementById('cycleFormEndAt').value = '';
    document.getElementById('cycleFormStatus').value = 'planned';
    document.getElementById('cycleFormStatusField').classList.toggle('d-none', !!publicId);
    document.getElementById('cycleModalAlert').classList.add('d-none');

    var selectPromises = Promise.all([loadProjectSelect(), loadUserSelect()]);

    if (publicId) {
      Promise.all([
        selectPromises,
        apiRequest('api/v1/cycles/' + encodeURIComponent(publicId), { method: 'GET' })
      ])
        .then(function (results) {
          var env = results[1];
          var c = env.data || {};
          document.getElementById('cycleFormTitle').value = c.title || '';
          document.getElementById('cycleFormGoal').value = c.goal || '';
          document.getElementById('cycleFormDescription').value = c.description || '';
          document.getElementById('cycleFormRowVersion').value = c.row_version || '';

          if (c.project_public_id) {
            var sel = document.getElementById('cycleFormProject');
            for (var i = 0; i < sel.options.length; i++) {
              if (sel.options[i].value === c.project_public_id) { sel.value = c.project_public_id; break; }
            }
          }

          if (c.start_at) document.getElementById('cycleFormStartAt').value = c.start_at.substring(0, 16);
          if (c.end_at) document.getElementById('cycleFormEndAt').value = c.end_at.substring(0, 16);
          if (c.status) document.getElementById('cycleFormStatus').value = c.status === 'completed' || c.status === 'archived' ? 'planned' : c.status;

          if (c.owner_user_public_id) {
            var sel2 = document.getElementById('cycleFormOwner');
            for (var j = 0; j < sel2.options.length; j++) {
              if (sel2.options[j].value === c.owner_user_public_id) { sel2.value = c.owner_user_public_id; break; }
            }
          }
        })
        .catch(function () {
          document.getElementById('cycleModalAlert').textContent = window.CRM.i18n.t('js.cycles.error_load_data', 'Ошибка загрузки данных цикла.');
          document.getElementById('cycleModalAlert').classList.remove('d-none');
        });
    }

    modal.show();
  };

  window.saveWorkCycle = function () {
    var publicId = document.getElementById('cycleFormPublicId').value;
    var title = document.getElementById('cycleFormTitle').value.trim();
    if (!title) {
      document.getElementById('cycleModalAlert').textContent = t('cycles.error_title_required', 'Название обязательно.');
      document.getElementById('cycleModalAlert').classList.remove('d-none');
      return;
    }

    var data = {
      title: title,
      goal: document.getElementById('cycleFormGoal').value.trim(),
      description: document.getElementById('cycleFormDescription').value.trim(),
      start_at: document.getElementById('cycleFormStartAt').value || '',
      end_at: document.getElementById('cycleFormEndAt').value || '',
      project_public_id: document.getElementById('cycleFormProject').value,
      owner_user_public_id: document.getElementById('cycleFormOwner').value || null,
    };

    if (!publicId) {
      data.status = document.getElementById('cycleFormStatus').value;
    }

    document.getElementById('cycleFormSubmit').disabled = true;
    document.getElementById('cycleModalAlert').classList.add('d-none');

    var method = publicId ? 'PATCH' : 'POST';
    var url = publicId ? 'api/v1/cycles/' + encodeURIComponent(publicId) : 'api/v1/cycles';

    if (publicId) {
      data.row_version = parseInt(document.getElementById('cycleFormRowVersion').value) || 0;
    }

    apiRequest(url, { method: method, body: data })
      .then(function () {
        document.getElementById('cycleFormSubmit').disabled = false;
        bootstrap.Modal.getInstance(document.getElementById('cycleModal')).hide();
        window.loadWorkCycles(currentPage);
        if (currentCyclePublicId === publicId) {
          window.openCycleDetail(publicId);
        }
      })
      .catch(function (err) {
        document.getElementById('cycleFormSubmit').disabled = false;
        document.getElementById('cycleModalAlert').textContent = err && err.message || t('cycles.error_save', 'Ошибка сохранения.');
        document.getElementById('cycleModalAlert').classList.remove('d-none');
      });
  };

  // ====== Actions ======
  window.startCycle = async function (publicId) {
    if (!await confirmCycleAction(t('cycles.confirm_start', 'Запустить этот цикл?'), t('cycles.start', 'Запустить'), 'crm-btn-primary')) return;
    apiRequest('api/v1/cycles/' + encodeURIComponent(publicId) + '/start', { method: 'POST' })
      .then(function () { window.loadWorkCycles(currentPage); })
      .catch(function () { showCycleFeedback(t('cycles.error_start', 'Ошибка запуска цикла.'), 'error'); });
  };

  window.reopenCycle = async function (publicId) {
    if (!await confirmCycleAction(t('cycles.confirm_reopen', 'Открыть этот цикл заново?'), t('cycles.reopen', 'Открыть'), 'crm-btn-primary')) return;
    apiRequest('api/v1/cycles/' + encodeURIComponent(publicId) + '/reopen', { method: 'POST' })
      .then(function () { window.loadWorkCycles(currentPage); })
      .catch(function () { showCycleFeedback(t('cycles.error_reopen', 'Ошибка открытия цикла.'), 'error'); });
  };

  window.archiveCycle = async function (publicId) {
    if (!await confirmCycleAction(t('cycles.confirm_archive', 'Архивировать этот цикл? Задачи не будут удалены.'), t('cycles.archive', 'Архивировать'))) return;
    apiRequest('api/v1/cycles/' + encodeURIComponent(publicId) + '/archive', { method: 'POST' })
      .then(function () { window.loadWorkCycles(currentPage); })
      .catch(function () { showCycleFeedback(t('cycles.error_archive', 'Ошибка архивирования.'), 'error'); });
  };

  // ====== Complete Cycle ======
  window.openCompleteCycle = function (publicId) {
    currentCyclePublicId = publicId;
    var modal = new bootstrap.Modal(document.getElementById('completeCycleModal'));

    apiRequest('api/v1/cycles/' + encodeURIComponent(publicId) + '/summary', { method: 'GET' })
      .then(function (env) {
        var s = env.data && env.data.summary || {};
        var html = '<h6>' + t('cycles.summary_title', 'Итого по циклу:') + '</h6>' +
          '<table class="table table-sm table-borderless">' +
          '<tr><td>' + t('cycles.total_tasks', 'Всего задач:') + '</td><td><strong>' + (s.total_tasks || 0) + '</strong></td></tr>' +
          '<tr><td>' + t('cycles.completed', 'Завершено:') + '</td><td><strong>' + (s.completed_tasks || 0) + '</strong></td></tr>' +
          '<tr><td>' + t('cycles.open', 'Открыто:') + '</td><td><strong>' + (s.open_tasks || 0) + '</strong></td></tr>' +
          '<tr><td>' + t('cycles.overdue', 'Просрочено:') + '</td><td><strong>' + (s.overdue_tasks || 0) + '</strong></td></tr>' +
          '<tr><td>' + t('cycles.no_assignee', 'Без исполнителя:') + '</td><td><strong>' + (s.unassigned_tasks || 0) + '</strong></td></tr>' +
          '</table>';
        document.getElementById('completeCycleSummary').innerHTML = html;
      })
      .catch(function () {
        document.getElementById('completeCycleSummary').innerHTML = '<p class="text-muted">' + t('cycles.error_load_summary', 'Не удалось загрузить сводку.') + '</p>';
      });

    loadCyclesForSelect('completeTargetCycle', publicId);
    document.getElementById('completeTargetCycleContainer').classList.add('d-none');
    document.getElementById('completeUnfinishedAction').value = 'leave';

    document.getElementById('completeUnfinishedAction').onchange = function () {
      document.getElementById('completeTargetCycleContainer').classList.toggle('d-none', this.value !== 'move');
    };

    modal.show();
  };

  window.confirmCompleteCycle = function () {
    var action = document.getElementById('completeUnfinishedAction').value;
    var data = { unfinished_action: action };
    if (action === 'move') {
      data.target_cycle_public_id = document.getElementById('completeTargetCycle').value;
      if (!data.target_cycle_public_id) {
        showCycleFeedback(t('cycles.select_target_cycle', 'Выберите целевой цикл.'), 'warning');
        return;
      }
    }

    document.getElementById('completeCycleConfirmBtn').disabled = true;
    apiRequest('api/v1/cycles/' + encodeURIComponent(currentCyclePublicId) + '/complete', { method: 'POST', body: data })
      .then(function () {
        document.getElementById('completeCycleConfirmBtn').disabled = false;
        bootstrap.Modal.getInstance(document.getElementById('completeCycleModal')).hide();
        window.loadWorkCycles(currentPage);
      })
      .catch(function () {
        document.getElementById('completeCycleConfirmBtn').disabled = false;
        showCycleFeedback(t('cycles.error_complete', 'Ошибка завершения цикла.'), 'error');
      });
  };

  // ====== Cycle Detail ======
  window.openCycleDetail = function (publicId) {
    currentCyclePublicId = publicId;
    var modal = new bootstrap.Modal(document.getElementById('cycleDetailModal'));
    document.getElementById('cycleDetailTitle').textContent = t('cycles.loading', 'Загрузка...');

    apiRequest('api/v1/cycles/' + encodeURIComponent(publicId), { method: 'GET' })
      .then(function (env) {
        var c = env.data || {};
        currentCycleDetail = c;
        document.getElementById('cycleDetailTitle').textContent = c.title || t('cycles.modal_detail_title', 'Цикл');
        renderCycleOverview(c);
        renderCycleBoard(c);
        loadCycleTasks(publicId);
        loadCycleSummary(publicId);
      })
      .catch(function () {
        document.getElementById('cycleOverviewContent').innerHTML = '<div class="text-danger">' + t('cycles.error_load_detail', 'Ошибка загрузки данных цикла.') + '</div>';
      });

    modal.show();
  };

  function renderCycleOverview(cycle) {
    var totalTasks = cycle.tasks_count || 0;
    var completedCount = cycle.completed_tasks_count || 0;
    var openCount = Math.max(0, totalTasks - completedCount);
    var isOverdue = cycle.time_state === 'ended' && cycle.status === 'active';
    var recommendation = totalTasks === 0
      ? t('cycles.recommendation_plan_tasks', 'Сначала добавьте задачи в цикл: без плана это просто календарная метка.')
      : openCount === 0
        ? t('cycles.recommendation_complete_cycle', 'Все задачи закрыты: можно завершить цикл и зафиксировать результат.')
        : isOverdue
          ? t('cycles.recommendation_overdue', 'Цикл просрочен: проверьте незавершённые задачи и перенесите их в следующий цикл.')
          : t('cycles.recommendation_work_focus', 'Рабочий цикл: держите фокус на открытых задачах и проверяйте прогресс каждый день.');
    var html =
      '<div class="crm-cycle-detail-callout mb-3">' +
        '<div><strong>' + t('cycles.next_step_title', 'Что делать дальше') + '</strong><div class="text-muted small mt-1">' + escapeHtml(recommendation) + '</div></div>' +
        '<div class="d-flex gap-2 flex-wrap">' +
          (cycle.status !== 'completed' && cycle.status !== 'archived' ? '<button class="btn btn-sm crm-btn-primary" onclick="window.openAddTasksModal(\'' + escapeHtml(cycle.public_id) + '\')"><i class="fa-solid fa-plus"></i> ' + t('cycles.btn_add_tasks', 'Добавить задачи') + '</button>' : '') +
          (cycle.status !== 'completed' && cycle.status !== 'archived' ? '<button class="btn btn-sm crm-btn-secondary" onclick="window.openCompleteCycle(\'' + escapeHtml(cycle.public_id) + '\')"><i class="fa-solid fa-flag-checkered"></i> ' + t('cycles.btn_complete_small', 'Завершить') + '</button>' : '') +
        '</div>' +
      '</div>' +
      '<div class="row g-3">' +
        '<div class="col-md-6">' +
          '<div class="mb-2"><small class="text-muted">' + t('cycles.overview_status', 'Статус') + '</small><br>' + statusBadge(cycle.status) + '</div>' +
          '<div class="mb-2"><small class="text-muted">' + t('cycles.overview_project', 'Проект') + '</small><br>' + escapeHtml(cycle.project_title || '') + '</div>' +
          '<div class="mb-2"><small class="text-muted">' + t('cycles.overview_owner', 'Владелец') + '</small><br>' + escapeHtml(cycle.owner_name || '—') + '</div>' +
          (cycle.goal ? '<div class="mb-2"><small class="text-muted">' + t('cycles.overview_goal', 'Цель') + '</small><br>' + visualEditorHtml(cycle.goal) + '</div>' : '') +
        '</div>' +
        '<div class="col-md-6">' +
          '<div class="mb-2"><small class="text-muted">' + t('cycles.overview_start', 'Дата начала') + '</small><br>' + formatDate(cycle.start_at) + '</div>' +
          '<div class="mb-2"><small class="text-muted">' + t('cycles.overview_end', 'Дата окончания') + '</small><br>' + formatDate(cycle.end_at) + '</div>' +
          '<div class="mb-2"><small class="text-muted">' + t('cycles.overview_progress', 'Прогресс') + '</small><br>' +
            '<div class="d-flex align-items-center gap-2">' +
              '<div class="crm-cycle-progress flex-grow-1"><div class="crm-cycle-progress-bar" style="width:' + (cycle.progress_percent || 0) + '%;"></div></div>' +
              '<small>' + (cycle.progress_percent || 0) + '%</small>' +
            '</div>' +
          '</div>' +
          '<div class="mb-2"><small class="text-muted">' + t('cycles.overview_tasks', 'Задачи') + '</small><br>' + (cycle.completed_tasks_count || 0) + '/' + (cycle.tasks_count || 0) + ' ' + t('cycles.completed_short', 'завершено') + '</div>' +
        '</div>' +
      '</div>' +
      (cycle.description ? '<div class="mt-2"><small class="text-muted">' + t('cycles.overview_description', 'Описание') + '</small><p class="mb-0">' + visualEditorHtml(cycle.description) + '</p></div>' : '');

    document.getElementById('cycleOverviewContent').innerHTML = html;
  }

  function renderCycleBoard(cycle) {
    var container = document.getElementById('cycleBoardContent');
    if (!container) return;
    var url = 'index.php?route=kanban&cycle_public_id=' + encodeURIComponent(cycle.public_id || '');
    container.innerHTML =
      '<div class="crm-cycle-board-link">' +
        '<div>' +
          '<strong>' + t('cycles.board_title', 'Канбан этого цикла') + '</strong>' +
          '<p class="text-muted small mb-0">' + t('cycles.board_text', 'Откройте доску с фильтром по этому циклу, чтобы двигать задачи по статусам без лишнего шума.') + '</p>' +
        '</div>' +
        '<a class="btn btn-sm crm-btn-primary" href="' + url + '"><i class="fa-solid fa-table-columns"></i> ' + t('cycles.btn_open_board', 'Открыть доску') + '</a>' +
      '</div>';
  }

  function loadCycleTasks(publicId) {
    var container = document.getElementById('cycleTasksContent');
    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-muted" style="width:1.5rem;height:1.5rem;"><span class="visually-hidden">' + t('cycles.loading', 'Загрузка...') + '</span></div></div>';

    apiRequest('api/v1/cycles/' + encodeURIComponent(publicId) + '/tasks', { method: 'GET' })
      .then(function (env) {
        var items = env.data && env.data.items || [];
        if (!items.length) {
          container.innerHTML = '<div class="text-center py-3 text-muted"><p>' + t('cycles.no_tasks', 'Нет задач в этом цикле.') + '</p>' +
            '<button class="btn btn-sm crm-btn-primary" onclick="window.openAddTasksModal(\'' + publicId + '\')">' + t('cycles.btn_add_tasks', 'Добавить задачи') + '</button></div>';
          return;
        }
        var html = '<div class="d-flex justify-content-between mb-2"><span><strong>' + t('cycles.tasks_heading', 'Задачи') + ' (' + items.length + ')</strong></span>' +
          '<button class="btn btn-sm crm-btn-primary" onclick="window.openAddTasksModal(\'' + publicId + '\')"><i class="fa-solid fa-plus"></i>' + t('cycles.btn_add', 'Добавить') + '</button></div>' +
          '<div class="list-group list-group-flush" style="max-height:400px;overflow-y:auto;">';
        items.forEach(function (task) {
          var statusClass = '';
          if (task.task_status === 'done' || task.task_status === 'closed') statusClass = 'text-decoration-line-through text-muted';
          html += '<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2">' +
            '<div><a href="index.php?route=task-detail&id=' + encodeURIComponent(task.task_public_id) + '" class="' + statusClass + '">' + escapeHtml(task.task_title) + '</a>' +
            '<br><small class="text-muted">' + escapeHtml(task.task_status || '') + (task.assignee_name ? ' &middot; ' + escapeHtml(task.assignee_name) : '') + '</small></div>' +
            '<button class="btn btn-sm btn-outline-danger" onclick="window.removeTaskFromCycle(\'' + publicId + '\',\'' + encodeURIComponent(task.task_public_id) + '\')"><i class="fa-solid fa-xmark"></i></button>' +
            '</div>';
        });
        html += '</div>';
        container.innerHTML = html;
      })
      .catch(function () {
        container.innerHTML = '<div class="text-danger">' + t('cycles.error_load_tasks', 'Ошибка загрузки задач.') + '</div>';
      });
  }

  function loadCycleSummary(publicId) {
    var container = document.getElementById('cycleSummaryContent');
    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-muted" style="width:1.5rem;height:1.5rem;"><span class="visually-hidden">' + t('cycles.loading', 'Загрузка...') + '</span></div></div>';

    var projectPublicId = currentCycleDetail && currentCycleDetail.project_public_id ? currentCycleDetail.project_public_id : '';

    Promise.all([
      apiRequest('api/v1/cycles/' + encodeURIComponent(publicId) + '/summary', { method: 'GET' }),
      apiRequest('api/v1/cycles/' + encodeURIComponent(publicId) + '/burndown', { method: 'GET' }).catch(function () { return null; }),
      apiRequest('api/v1/cycles/' + encodeURIComponent(publicId) + '/scope', { method: 'GET' }).catch(function () { return null; }),
      projectPublicId
        ? apiRequest('api/v1/cycles/velocity?project_public_id=' + encodeURIComponent(projectPublicId), { method: 'GET' }).catch(function () { return null; })
        : Promise.resolve(null),
      apiRequest('api/v1/cycles/' + encodeURIComponent(publicId) + '/capacity', { method: 'GET' }).catch(function () { return null; })
    ]).then(function (results) {
      var summaryEnv = results[0];
      var s = (summaryEnv && summaryEnv.data && summaryEnv.data.summary) || {};
      var burndown = results[1] && results[1].data ? results[1].data : null;
      var scope = (results[2] && results[2].data) ? results[2].data : (s.scope || null);
      var velocity = results[3] && results[3].data ? results[3].data : null;
      var capacity = results[4] && results[4].data ? results[4].data : null;
      container.innerHTML = renderCycleStatistics(s, burndown, scope, velocity, capacity);
    }).catch(function () {
      container.innerHTML = '<div class="text-danger">' + t('cycles.error_load_stats', 'Ошибка загрузки статистики.') + '</div>';
    });
  }

  function renderCycleStatistics(s, burndown, scope, velocity, capacity) {
    var html = '';

    // Burndown chart
    html += '<div class="card mb-3"><div class="card-body">' +
      '<h6 class="card-title">' + t('cycles.burndown_title', 'Диаграмма сгорания') + '</h6>' +
      renderBurndownChart(burndown) +
      '</div></div>';

    // Scope change + velocity
    html += '<div class="row g-3 mb-3">' +
      '<div class="col-md-6"><div class="card"><div class="card-body">' +
        '<h6 class="card-title">' + t('cycles.scope_title', 'Изменение состава') + '</h6>' + renderScopeChange(scope) +
      '</div></div></div>' +
      '<div class="col-md-6"><div class="card"><div class="card-body">' +
        '<h6 class="card-title">' + t('cycles.velocity_title', 'Скорость команды') + '</h6>' + renderVelocity(velocity) +
      '</div></div></div>' +
      '</div>';

    // Capacity planning (per assignee)
    html += '<div class="card mb-3"><div class="card-body">' +
      '<h6 class="card-title">' + t('cycles.capacity_title', 'Мощность (по исполнителям)') + '</h6>' +
      renderCapacity(capacity) +
      '</div></div>';

    // By status / priority
    html += '<div class="row g-3">' +
      '<div class="col-md-6"><div class="card"><div class="card-body">' +
        '<h6 class="card-title">' + t('cycles.by_status', 'По статусам') + '</h6>';
    var byStatus = s.by_status || {};
    var hasStatus = false;
    for (var code in byStatus) { hasStatus = true; html += '<div class="d-flex justify-content-between"><span>' + escapeHtml(code) + '</span><strong>' + byStatus[code] + '</strong></div>'; }
    if (!hasStatus) html += '<p class="text-muted small mb-0">' + t('cycles.no_data', 'Нет данных') + '</p>';
    html += '</div></div></div>' +
      '<div class="col-md-6"><div class="card"><div class="card-body">' +
        '<h6 class="card-title">' + t('cycles.by_priority', 'По приоритетам') + '</h6>';
    var byPriority = s.by_priority || {};
    var hasPriority = false;
    for (var p in byPriority) { hasPriority = true; html += '<div class="d-flex justify-content-between"><span>' + escapeHtml(p) + '</span><strong>' + byPriority[p] + '</strong></div>'; }
    if (!hasPriority) html += '<p class="text-muted small mb-0">' + t('cycles.no_data', 'Нет данных') + '</p>';
    html += '</div></div></div>' +
      '</div>';

    // By assignee
    html += '<div class="mt-3"><h6>' + t('cycles.by_assignee', 'По исполнителям') + '</h6>';
    var byAssignee = s.by_assignee || [];
    if (byAssignee.length) {
      html += '<table class="table table-sm"><thead><tr><th>' + t('cycles.col_assignee', 'Исполнитель') + '</th><th>' + t('cycles.col_total', 'Всего') + '</th><th>' + t('cycles.completed', 'Завершено:') + '</th></tr></thead><tbody>';
      byAssignee.forEach(function (a) {
        html += '<tr><td>' + escapeHtml(a.name || '—') + '</td><td>' + (a.total || 0) + '</td><td>' + (a.completed || 0) + '</td></tr>';
      });
      html += '</tbody></table>';
    } else {
      html += '<p class="text-muted">' + t('cycles.no_data', 'Нет данных') + '</p>';
    }
    html += '</div>';

    return html;
  }

  function renderBurndownChart(burndown) {
    if (!burndown || !burndown.series || !burndown.series.length) {
      return '<div class="text-muted small">' + t('cycles.burndown_empty', 'Недостаточно данных: график появится после старта цикла и накопления снимков.') + '</div>';
    }

    var series = burndown.series || [];
    var ideal = burndown.ideal || [];
    var maxVal = 0;
    var dates = [];
    series.forEach(function (p) { maxVal = Math.max(maxVal, p.total || 0, p.remaining || 0); if (p.date) dates.push(p.date); });
    ideal.forEach(function (p) { maxVal = Math.max(maxVal, p.remaining || 0); if (p.date) dates.push(p.date); });
    if (burndown.scope_committed) maxVal = Math.max(maxVal, burndown.scope_committed);
    maxVal = maxVal || 1;

    if (!dates.length) {
      return '<div class="text-muted small">' + t('cycles.burndown_empty', 'Недостаточно данных для графика.') + '</div>';
    }
    dates.sort();
    var minT = Date.parse(dates[0]);
    var maxT = Date.parse(dates[dates.length - 1]);
    if (isNaN(minT) || isNaN(maxT) || maxT <= minT) { maxT = minT + 86400000; }

    var W = 600, H = 200, P = 26;
    function x(date) { var t = Date.parse(date); if (isNaN(t)) return P; return P + ((t - minT) / (maxT - minT)) * (W - 2 * P); }
    function y(v) { return H - P - (Math.max(0, v) / maxVal) * (H - 2 * P); }
    function polyline(points, color, dash) {
      if (!points.length) return '';
      var pts = points.map(function (p) { return x(p.date).toFixed(1) + ',' + y(p.remaining).toFixed(1); }).join(' ');
      return '<polyline points="' + pts + '" fill="none" stroke="' + color + '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"' + (dash ? ' stroke-dasharray="5 4"' : '') + '/>';
    }

    var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" style="width:100%;height:auto;" role="img" aria-label="' + t('cycles.burndown_title', 'Диаграмма сгорания') + '">';
    svg += '<line x1="' + P + '" y1="' + (H - P) + '" x2="' + (W - P) + '" y2="' + (H - P) + '" stroke="var(--crm-border,#e5e7eb)" stroke-width="1"/>';
    svg += '<line x1="' + P + '" y1="' + P + '" x2="' + P + '" y2="' + (H - P) + '" stroke="var(--crm-border,#e5e7eb)" stroke-width="1"/>';
    svg += polyline(ideal, 'var(--crm-text-muted,#9ca3af)', true);
    svg += polyline(series, 'var(--crm-primary,#2563eb)', false);
    svg += '</svg>';

    return '<div>' + svg +
      '<div class="d-flex gap-3 small text-muted mt-2 flex-wrap">' +
        '<span><span style="display:inline-block;width:14px;height:2px;background:var(--crm-primary,#2563eb);vertical-align:middle;"></span> ' + t('cycles.burndown_actual', 'Осталось (факт)') + '</span>' +
        '<span><span style="display:inline-block;width:14px;height:0;border-top:2px dashed var(--crm-text-muted,#9ca3af);vertical-align:middle;"></span> ' + t('cycles.burndown_ideal', 'Идеальная линия') + '</span>' +
      '</div></div>';
  }

  function renderScopeChange(scope) {
    if (!scope) {
      return '<p class="text-muted small mb-0">' + t('cycles.scope_empty', 'Нет данных об изменении состава.') + '</p>';
    }
    return '<div class="d-flex gap-3 flex-wrap">' +
      '<div><strong>' + (scope.committed_count || 0) + '</strong><br><small class="text-muted">' + t('cycles.scope_committed', 'Запланировано') + '</small></div>' +
      '<div><strong>' + (scope.current_count || 0) + '</strong><br><small class="text-muted">' + t('cycles.scope_current', 'Текущий состав') + '</small></div>' +
      '<div><strong class="text-success">+' + (scope.added_count || 0) + '</strong><br><small class="text-muted">' + t('cycles.scope_added', 'Добавлено') + '</small></div>' +
      '<div><strong class="text-danger">−' + (scope.removed_count || 0) + '</strong><br><small class="text-muted">' + t('cycles.scope_removed', 'Убрано') + '</small></div>' +
      '</div>';
  }

  function renderVelocity(velocity) {
    if (!velocity || !velocity.total_cycles) {
      return '<p class="text-muted small mb-0">' + t('cycles.velocity_empty', 'Нет завершённых циклов — скорость появится после первого завершения.') + '</p>';
    }
    var hasPoints = typeof velocity.average_points_velocity === 'number' && velocity.average_points_velocity > 0;
    var html = '<div class="d-flex gap-3 flex-wrap mb-2">' +
      '<div><strong>' + (velocity.average_velocity || 0) + '</strong><br><small class="text-muted">' + t('cycles.velocity_avg', 'Ср. скорость (задач/цикл)') + '</small></div>';
    if (hasPoints) {
      html += '<div><strong>' + velocity.average_points_velocity + '</strong><br><small class="text-muted">' + t('cycles.velocity_points_avg', 'Ср. очки/цикл') + (velocity.points_unit_label ? ' (' + escapeHtml(velocity.points_unit_label) + ')' : '') + '</small></div>';
    }
    html += '<div><strong>' + (velocity.total_cycles || 0) + '</strong><br><small class="text-muted">' + t('cycles.velocity_cycles', 'Завершённых циклов') + '</small></div>' +
      '</div>';
    var list = (velocity.cycles || []).slice().reverse().slice(0, 5);
    if (list.length) {
      html += '<ul class="list-unstyled small mb-0">';
      list.forEach(function (c) {
        var right = (c.completed_tasks || 0) + '/' + (c.total_tasks || 0);
        if (typeof c.points_completed === 'number') right += ' · ' + c.points_completed + ' ' + escapeHtml(velocity.points_unit_label || 'SP');
        html += '<li class="d-flex justify-content-between"><span>' + escapeHtml(c.title || '—') + '</span><strong>' + right + '</strong></li>';
      });
      html += '</ul>';
    }
    return html;
  }

  function renderCapacity(capacity) {
    if (!capacity || !capacity.assignees || !capacity.assignees.length) {
      return '<p class="text-muted small mb-0">' + t('cycles.capacity_empty', 'Нет исполнителей с задачами в этом цикле.') + '</p>';
    }
    var hasPoints = capacity.has_points;
    var unit = capacity.unit_label || '';
    var html = '<div class="table-responsive"><table class="table table-sm align-middle mb-0">' +
      '<thead><tr>' +
        '<th>' + t('cycles.col_assignee', 'Исполнитель') + '</th>' +
        '<th class="text-end">' + t('cycles.capacity_tasks', 'Задачи (заверш./всего)') + '</th>' +
        (hasPoints ? '<th class="text-end">' + t('cycles.capacity_points', 'Очки') + (unit ? ' (' + escapeHtml(unit) + ')' : '') + '</th>' : '') +
      '</tr></thead><tbody>';
    capacity.assignees.forEach(function (a) {
      var name = a.name || t('cycles.unassigned', 'Без исполнителя');
      html += '<tr><td>' + escapeHtml(name) + '</td>' +
        '<td class="text-end">' + (a.tasks_completed || 0) + '/' + (a.tasks_total || 0) + '</td>' +
        (hasPoints ? '<td class="text-end">' + (a.points_completed || 0) + '/' + (a.points_total || 0) + '</td>' : '') +
        '</tr>';
    });
    var team = capacity.team || {};
    html += '<tr class="fw-bold"><td>' + t('cycles.capacity_team', 'Команда') + '</td>' +
      '<td class="text-end">' + (team.tasks_completed || 0) + '/' + (team.tasks_total || 0) + '</td>' +
      (hasPoints ? '<td class="text-end">' + (team.points_completed || 0) + '/' + (team.points_total || 0) + '</td>' : '') +
      '</tr>';
    html += '</tbody></table></div>';
    return html;
  }

  // ====== Add Tasks ======
  var addTaskDebounceTimer = null;

  window.openAddTasksModal = function (cyclePublicId) {
    currentCyclePublicId = cyclePublicId;
    selectedTasks = {};
    document.getElementById('addTaskResults').innerHTML = '<div class="text-center text-muted py-3">' + t('cycles.search_placeholder_min', 'Введите поисковый запрос') + '</div>';
    document.getElementById('addTaskSearchInput').value = '';
    document.getElementById('addTasksConfirmBtn').textContent = t('cycles.btn_add_selected', 'Добавить выбранные');
    document.getElementById('addTasksConfirmBtn').disabled = false;
    var modal = new bootstrap.Modal(document.getElementById('addTasksModal'));
    modal.show();
  };

  function showCycleTasksTab() {
    var tabButton = document.getElementById('cycleTasksTab');
    if (!tabButton) return;
    if (window.bootstrap && bootstrap.Tab) {
      bootstrap.Tab.getOrCreateInstance(tabButton).show();
      return;
    }
    tabButton.click();
  }

  document.addEventListener('DOMContentLoaded', function () {
    var searchInput = document.getElementById('addTaskSearchInput');
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        clearTimeout(addTaskDebounceTimer);
        var query = this.value.trim();
        if (query.length < 2) {
          document.getElementById('addTaskResults').innerHTML = '<div class="text-center text-muted py-3">' + t('cycles.search_min_chars', 'Введите минимум 2 символа') + '</div>';
          return;
        }
        addTaskDebounceTimer = setTimeout(function () {
          searchTasksForCycle(query);
        }, 300);
      });
    }
  });

  function searchTasksForCycle(query) {
    var taskQuery = { search: query, limit: 20 };
    if (currentCycleDetail && currentCycleDetail.project_public_id) {
      taskQuery.project_public_id = currentCycleDetail.project_public_id;
    }
    apiRequest('api/v1/tasks', { method: 'GET', query: taskQuery })
      .then(function (env) {
        var items = env.data && env.data.items || [];
        var container = document.getElementById('addTaskResults');
        if (!items.length) {
          container.innerHTML = '<div class="text-center text-muted py-3">' + t('cycles.tasks_not_found', 'Задачи не найдены') + '</div>';
          return;
        }
        var html = '';
        items.forEach(function (t) {
          var checked = selectedTasks[t.public_id] ? 'checked' : '';
          html += '<div class="crm-cycle-card py-2">' +
            '<label class="d-flex align-items-center gap-2" style="cursor:pointer;">' +
              '<input type="checkbox" class="form-check-input" value="' + encodeURIComponent(t.public_id) + '" ' + checked +
                ' onchange="window.toggleTaskSelection(\'' + encodeURIComponent(t.public_id) + '\', this.checked)">' +
              '<div><strong>' + escapeHtml(t.title) + '</strong>' +
              '<br><small class="text-muted">' + escapeHtml(t.status_code || '') + (t.assignee_name ? ' &middot; ' + escapeHtml(t.assignee_name) : '') + '</small></div>' +
            '</label>' +
            '</div>';
        });
        container.innerHTML = html;
      })
      .catch(function () {
        document.getElementById('addTaskResults').innerHTML = '<div class="text-danger">' + t('cycles.error_search', 'Ошибка поиска.') + '</div>';
      });
  }

  window.toggleTaskSelection = function (publicId, checked) {
    publicId = decodeURIComponent(publicId);
    if (checked) {
      selectedTasks[publicId] = true;
    } else {
      delete selectedTasks[publicId];
    }
    var count = Object.keys(selectedTasks).length;
    document.getElementById('addTasksConfirmBtn').textContent = t('cycles.btn_add_selected', 'Добавить выбранные') + ' (' + count + ')';
  };

  window.confirmAddTasks = function () {
    var ids = Object.keys(selectedTasks);
    if (!ids.length) { showCycleFeedback(t('cycles.select_tasks', 'Выберите задачи.'), 'warning'); return; }

    document.getElementById('addTasksConfirmBtn').disabled = true;
    document.getElementById('addTasksConfirmBtn').textContent = t('cycles.adding_tasks', 'Добавляем...');
    apiRequest('api/v1/cycles/' + encodeURIComponent(currentCyclePublicId) + '/tasks', {
      method: 'POST',
      body: { task_public_ids: ids }
    })
      .then(function () {
        document.getElementById('addTasksConfirmBtn').disabled = false;
        document.getElementById('addTasksConfirmBtn').textContent = t('cycles.btn_add_selected', 'Добавить выбранные');
        bootstrap.Modal.getInstance(document.getElementById('addTasksModal')).hide();
        loadCycleTasks(currentCyclePublicId);
        showCycleTasksTab();
      })
      .catch(function (err) {
        document.getElementById('addTasksConfirmBtn').disabled = false;
        document.getElementById('addTasksConfirmBtn').textContent = t('cycles.btn_add_selected', 'Добавить выбранные') + ' (' + ids.length + ')';
        showCycleFeedback(t('cycles.error_add_tasks', 'Ошибка добавления:') + ' ' + (err && err.message || t('cycles.unknown_error', 'неизвестная ошибка')), 'error');
      });
  };

  window.removeTaskFromCycle = async function (cyclePublicId, taskPublicId) {
    if (!await confirmCycleAction(t('cycles.confirm_remove_task', 'Удалить задачу из цикла?'), t('cycles.remove_task', 'Удалить задачу'))) return;
    apiRequest('api/v1/cycles/' + encodeURIComponent(cyclePublicId) + '/tasks/' + encodeURIComponent(taskPublicId), { method: 'DELETE' })
      .then(function () { loadCycleTasks(cyclePublicId); })
      .catch(function () { showCycleFeedback(t('cycles.error_remove_task', 'Ошибка удаления задачи из цикла.'), 'error'); });
  };

  // ====== Select Loaders ======
  function loadProjectSelect() {
    var sel = document.getElementById('cycleFormProject');
    sel.innerHTML = '<option value="">' + t('cycles.loading', 'Загрузка...') + '</option>';
    return apiRequest('api/v1/projects', { method: 'GET', query: { limit: 100 } })
      .then(function (env) {
        var items = env.data && env.data.items || [];
        sel.innerHTML = '<option value="">' + t('cycles.select_project', 'Выберите проект') + '</option>';
        items.forEach(function (p) {
          var opt = document.createElement('option');
          opt.value = p.public_id;
          opt.textContent = p.title;
          sel.appendChild(opt);
        });
      })
      .catch(function () { sel.innerHTML = '<option value="">' + t('cycles.error_title', 'Ошибка загрузки') + '</option>'; });
  }

  function loadUserSelect() {
    var sel = document.getElementById('cycleFormOwner');
    sel.innerHTML = '<option value="">' + t('cycles.no_lead', 'Не назначен') + '</option>';
    return apiRequest('api/v1/users', { method: 'GET', query: { limit: 100 } })
      .then(function (env) {
        var items = env.data && env.data.items || [];
        items.forEach(function (u) {
          var opt = document.createElement('option');
          opt.value = u.public_id;
          opt.textContent = u.full_name || u.login || u.public_id;
          sel.appendChild(opt);
        });
      })
      .catch(function () { /* ignore */ });
  }

  function loadCyclesForSelect(selectId, excludePublicId) {
    var sel = document.getElementById(selectId);
    if (!sel) return;
    sel.innerHTML = '<option value="">' + t('cycles.loading', 'Загрузка...') + '</option>';
    apiRequest('api/v1/cycles', { method: 'GET', query: { limit: 100 } })
      .then(function (env) {
        var items = env.data && env.data.items || [];
        sel.innerHTML = '<option value="">' + t('cycles.select_cycle', 'Выберите цикл') + '</option>';
        items.forEach(function (c) {
          if (c.public_id === excludePublicId) return;
          if (['planned', 'active'].indexOf(c.status) === -1) return;
          var opt = document.createElement('option');
          opt.value = c.public_id;
          opt.textContent = c.title + ' (' + statusLabel(c.status) + ')';
          sel.appendChild(opt);
        });
      })
      .catch(function () { sel.innerHTML = '<option value="">' + t('cycles.error_title', 'Ошибка загрузки') + '</option>'; });
  }

  // ====== Init on page load ======
  document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('cycleList')) {
      loadProjectSelectForFilter();
      loadCycleFocusSummary();
      window.loadWorkCycles(1);
    }
  });

  function loadProjectSelectForFilter() {
    var sel = document.getElementById('cycleProjectFilter');
    if (!sel) return;
    apiRequest('api/v1/projects', { method: 'GET', query: { limit: 200 } })
      .then(function (env) {
        var items = env.data && env.data.items || [];
        sel.innerHTML = '<option value="">' + t('cycles.filter_all_projects', 'Все проекты') + '</option>';
        items.forEach(function (p) {
          var opt = document.createElement('option');
          opt.value = p.public_id;
          opt.textContent = p.title;
          sel.appendChild(opt);
        });
        sel.onchange = function () {
          loadCycleFocusSummary();
          window.loadWorkCycles(1);
        };
      });
    var statusSel = document.getElementById('cycleStatusFilter');
    if (statusSel) statusSel.onchange = function () { window.loadWorkCycles(1); };
    var searchInput = document.getElementById('cycleSearchInput');
    if (searchInput) {
      var searchSubmit = document.getElementById('cycleSearchSubmitBtn');
      var searchClear = document.getElementById('cycleSearchClearBtn');
      var syncSearchClear = function () {
        if (!searchClear) return;
        searchClear.classList.toggle('d-none', !searchInput.value.trim());
      };
      var scheduleSearch = function () {
        clearTimeout(cycleSearchTimer);
        cycleSearchTimer = setTimeout(function () {
          window.loadWorkCycles(1);
        }, 320);
      };

      syncSearchClear();
      searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          clearTimeout(cycleSearchTimer);
          window.loadWorkCycles(1);
        }
      });
      searchInput.addEventListener('input', function () {
        syncSearchClear();
        scheduleSearch();
      });
      if (searchSubmit) {
        searchSubmit.addEventListener('click', function () {
          clearTimeout(cycleSearchTimer);
          window.loadWorkCycles(1);
        });
      }
      if (searchClear) {
        searchClear.addEventListener('click', function () {
          if (!searchInput.value) return;
          searchInput.value = '';
          syncSearchClear();
          clearTimeout(cycleSearchTimer);
          window.loadWorkCycles(1);
          searchInput.focus();
        });
      }
    }
  }

})();
