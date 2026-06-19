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
  var selectedTasks = {};

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

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, function (ch) {
      var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
      return map[ch] || ch;
    });
  }

  function formatDate(d) {
    if (!d) return '—';
    try {
      var dt = new Date(d.replace(' ', 'T') + 'Z');
      if (isNaN(dt.getTime())) return d;
      return dt.toLocaleDateString(t('cycles.locale', 'ru-RU'), { day: 'numeric', month: 'short', year: 'numeric' });
    } catch (e) { return d; }
  }

  function statusBadge(status) {
    var cls = 'crm-cycle-badge crm-cycle-badge-' + status;
    var labels = { planned: t('cycles.status_planned', 'Запланирован'), active: t('cycles.status_active', 'Активен'), completed: t('cycles.status_completed', 'Завершён'), archived: t('cycles.status_archived', 'Архив') };
    return '<span class="' + cls + '">' + (labels[status] || status) + '</span>';
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

  function renderCycles(items, container) {
    items.forEach(function (cycle) {
      var card = renderCycleCard(cycle);
      if (card) container.appendChild(card);
    });
  }

  function renderCycleCard(cycle) {
    if (!cycle) return null;

    var card = document.createElement('div');
    card.className = 'crm-cycle-card';

    var progress = cycle.progress_percent || 0;
    var tasksCount = (cycle.tasks_count || 0) + t('cycles.tasks_suffix', ' задач');
    var completedCount = cycle.completed_tasks_count || 0;
    var timeState = cycle.time_state || '';
    var timeLabel = timeState === 'running' ? t('cycles.time_running', 'Идёт') : timeState === 'not_started' ? t('cycles.time_not_started', 'Не начат') : timeState === 'ended' ? t('cycles.time_ended', 'Завершён по дате') : '';

    card.innerHTML =
      '<div class="d-flex justify-content-between align-items-start">' +
        '<div class="flex-grow-1">' +
          '<div class="d-flex align-items-center gap-2 mb-1">' +
            '<h6 class="mb-0" style="cursor:pointer;" onclick="window.openCycleDetail(\'' + escapeHtml(cycle.public_id) + '\')">' + escapeHtml(cycle.title) + '</h6>' +
            statusBadge(cycle.status) +
            (timeLabel ? '<small class="text-muted">' + escapeHtml(timeLabel) + '</small>' : '') +
          '</div>' +
          '<div class="small text-muted">' +
            escapeHtml(cycle.project_title || '') +
            (cycle.owner_name ? ' &middot; ' + escapeHtml(cycle.owner_name) : '') +
            (cycle.start_at ? ' &middot; ' + formatDate(cycle.start_at) : '') +
            (cycle.end_at ? ' — ' + formatDate(cycle.end_at) : '') +
          '</div>' +
          (cycle.goal ? '<div class="small text-muted mt-1">' + escapeHtml(cycle.goal.substring(0, 120)) + '</div>' : '') +
        '</div>' +
        '<div class="text-end ms-3" style="min-width:120px;">' +
          '<div class="d-flex justify-content-between small mb-1">' +
            '<span>' + completedCount + '/' + (cycle.tasks_count || 0) + '</span>' +
            '<span>' + progress + '%</span>' +
          '</div>' +
          '<div class="crm-cycle-progress"><div class="crm-cycle-progress-bar" style="width:' + progress + '%;"></div></div>' +
        '</div>' +
      '</div>' +
      '<div class="d-flex gap-1 mt-2">' +
        (cycle.status === 'planned' ? '<button class="btn btn-sm btn-outline-success" onclick="window.startCycle(\'' + escapeHtml(cycle.public_id) + '\')"><i class="fa-solid fa-play"></i> Старт</button>' : '') +
        (cycle.status !== 'completed' && cycle.status !== 'archived' ? '<button class="btn btn-sm btn-outline-warning" onclick="window.openCompleteCycle(\'' + escapeHtml(cycle.public_id) + '\')"><i class="fa-solid fa-check"></i> Завершить</button>' : '') +
        '<button class="btn btn-sm btn-outline-primary" onclick="window.openCycleModal(\'' + escapeHtml(cycle.public_id) + '\')"><i class="fa-solid fa-pen"></i></button>' +
        (cycle.status === 'completed' || cycle.status === 'archived' ? '<button class="btn btn-sm btn-outline-secondary" onclick="window.reopenCycle(\'' + escapeHtml(cycle.public_id) + '\')"><i class="fa-solid fa-rotate-left"></i> Открыть</button>' : '') +
        '<button class="btn btn-sm btn-outline-secondary" onclick="window.archiveCycle(\'' + escapeHtml(cycle.public_id) + '\')"><i class="fa-solid fa-archive"></i></button>' +
      '</div>';

    return card;
  }

  function renderPagination(page, totalPages, container) {
    if (totalPages <= 1) return;
    var ul = document.createElement('ul');
    ul.className = 'pagination pagination-sm';
    for (var i = 1; i <= totalPages; i++) {
      var li = document.createElement('li');
      li.className = 'page-item' + (i === page ? ' active' : '');
      var a = document.createElement('a');
      a.className = 'page-link';
      a.href = '#';
      a.textContent = i;
      a.onclick = (function (p) { return function (e) { e.preventDefault(); window.loadWorkCycles(p); }; })(i);
      li.appendChild(a);
      ul.appendChild(li);
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
    document.getElementById('cycleModalAlert').classList.add('d-none');

    loadProjectSelect();
    loadUserSelect();

    if (publicId) {
      apiRequest('api/v1/cycles/' + encodeURIComponent(publicId), { method: 'GET' })
        .then(function (env) {
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
          document.getElementById('cycleModalAlert').textContent = 'Ошибка загрузки данных цикла.';
          document.getElementById('cycleModalAlert').classList.remove('d-none');
        });
    }

    modal.show();
  };

  window.saveWorkCycle = function () {
    var publicId = document.getElementById('cycleFormPublicId').value;
    var title = document.getElementById('cycleFormTitle').value.trim();
    if (!title) {
      document.getElementById('cycleModalAlert').textContent = 'Название обязательно.';
      document.getElementById('cycleModalAlert').classList.remove('d-none');
      return;
    }

    var data = {
      title: title,
      goal: document.getElementById('cycleFormGoal').value.trim(),
      description: document.getElementById('cycleFormDescription').value.trim(),
      start_at: document.getElementById('cycleFormStartAt').value || null,
      end_at: document.getElementById('cycleFormEndAt').value || null,
      status: document.getElementById('cycleFormStatus').value,
      project_public_id: document.getElementById('cycleFormProject').value,
      owner_user_public_id: document.getElementById('cycleFormOwner').value || null,
    };

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
      })
      .catch(function (err) {
        document.getElementById('cycleFormSubmit').disabled = false;
        document.getElementById('cycleModalAlert').textContent = err && err.message || t('cycles.error_save', 'Ошибка сохранения.');
        document.getElementById('cycleModalAlert').classList.remove('d-none');
      });
  };

  // ====== Actions ======
  window.startCycle = function (publicId) {
    if (!confirm(t('cycles.confirm_start', 'Запустить этот цикл?'))) return;
    apiRequest('api/v1/cycles/' + encodeURIComponent(publicId) + '/start', { method: 'POST' })
      .then(function () { window.loadWorkCycles(currentPage); })
      .catch(function () { alert(t('cycles.error_start', 'Ошибка запуска цикла.')); });
  };

  window.reopenCycle = function (publicId) {
    if (!confirm(t('cycles.confirm_reopen', 'Открыть этот цикл заново?'))) return;
    apiRequest('api/v1/cycles/' + encodeURIComponent(publicId) + '/reopen', { method: 'POST' })
      .then(function () { window.loadWorkCycles(currentPage); })
      .catch(function () { alert(t('cycles.error_reopen', 'Ошибка открытия цикла.')); });
  };

  window.archiveCycle = function (publicId) {
    if (!confirm(t('cycles.confirm_archive', 'Архивировать этот цикл? Задачи не будут удалены.'))) return;
    apiRequest('api/v1/cycles/' + encodeURIComponent(publicId) + '/archive', { method: 'POST' })
      .then(function () { window.loadWorkCycles(currentPage); })
      .catch(function () { alert(t('cycles.error_archive', 'Ошибка архивирования.')); });
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
          '<tr><td>Всего задач:</td><td><strong>' + (s.total_tasks || 0) + '</strong></td></tr>' +
          '<tr><td>Завершено:</td><td><strong>' + (s.completed_tasks || 0) + '</strong></td></tr>' +
          '<tr><td>Открыто:</td><td><strong>' + (s.open_tasks || 0) + '</strong></td></tr>' +
          '<tr><td>Просрочено:</td><td><strong>' + (s.overdue_tasks || 0) + '</strong></td></tr>' +
          '<tr><td>Без исполнителя:</td><td><strong>' + (s.unassigned_tasks || 0) + '</strong></td></tr>' +
          '</table>';
        document.getElementById('completeCycleSummary').innerHTML = html;
      })
      .catch(function () {
        document.getElementById('completeCycleSummary').innerHTML = '<p class="text-muted">Не удалось загрузить сводку.</p>';
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
        alert(t('cycles.error_complete', 'Ошибка завершения цикла.'));
      });
  };

  // ====== Cycle Detail ======
  window.openCycleDetail = function (publicId) {
    currentCyclePublicId = publicId;
    var modal = new bootstrap.Modal(document.getElementById('cycleDetailModal'));
    document.getElementById('cycleDetailTitle').textContent = 'Загрузка...';

    apiRequest('api/v1/cycles/' + encodeURIComponent(publicId), { method: 'GET' })
      .then(function (env) {
        var c = env.data || {};
        document.getElementById('cycleDetailTitle').textContent = c.title || t('cycles.modal_detail_title', 'Цикл');
        renderCycleOverview(c);
        loadCycleTasks(publicId);
        loadCycleSummary(publicId);
      })
      .catch(function () {
        document.getElementById('cycleOverviewContent').innerHTML = '<div class="text-danger">Ошибка загрузки.</div>';
      });

    modal.show();
  };

  function renderCycleOverview(cycle) {
    var html =
      '<div class="row g-3">' +
        '<div class="col-md-6">' +
          '<div class="mb-2"><small class="text-muted">' + t('cycles.overview_status', 'Статус') + '</small><br>' + statusBadge(cycle.status) + '</div>' +
          '<div class="mb-2"><small class="text-muted">' + t('cycles.overview_project', 'Проект') + '</small><br>' + escapeHtml(cycle.project_title || '') + '</div>' +
          '<div class="mb-2"><small class="text-muted">' + t('cycles.overview_owner', 'Владелец') + '</small><br>' + escapeHtml(cycle.owner_name || '—') + '</div>' +
          (cycle.goal ? '<div class="mb-2"><small class="text-muted">' + t('cycles.overview_goal', 'Цель') + '</small><br>' + escapeHtml(cycle.goal) + '</div>' : '') +
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
      (cycle.description ? '<div class="mt-2"><small class="text-muted">' + t('cycles.overview_description', 'Описание') + '</small><p class="mb-0">' + escapeHtml(cycle.description) + '</p></div>' : '');

    document.getElementById('cycleOverviewContent').innerHTML = html;
  }

  function loadCycleTasks(publicId) {
    var container = document.getElementById('cycleTasksContent');
    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-muted" style="width:1.5rem;height:1.5rem;"><span class="visually-hidden">Загрузка...</span></div></div>';

    apiRequest('api/v1/cycles/' + encodeURIComponent(publicId) + '/tasks', { method: 'GET' })
      .then(function (env) {
        var items = env.data && env.data.items || [];
        if (!items.length) {
          container.innerHTML = '<div class="text-center py-3 text-muted"><p>Нет задач в этом цикле.</p>' +
            '<button class="btn btn-sm crm-btn-primary" onclick="window.openAddTasksModal(\'' + publicId + '\')">' + t('cycles.btn_add_tasks', 'Добавить задачи') + '</button></div>';
          return;
        }
        var html = '<div class="d-flex justify-content-between mb-2"><span><strong>Задачи (' + items.length + ')</strong></span>' +
          '<button class="btn btn-sm crm-btn-primary" onclick="window.openAddTasksModal(\'' + publicId + '\')"><i class="fa-solid fa-plus"></i>' + t('cycles.btn_add', 'Добавить') + '</button></div>' +
          '<div class="list-group list-group-flush" style="max-height:400px;overflow-y:auto;">';
        items.forEach(function (task) {
          var statusClass = '';
          if (task.task_status === 'done' || task.task_status === 'closed') statusClass = 'text-decoration-line-through text-muted';
          html += '<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2">' +
            '<div><a href="/?route=task-detail&task_public_id=' + encodeURIComponent(task.task_public_id) + '" class="' + statusClass + '">' + escapeHtml(task.task_title) + '</a>' +
            '<br><small class="text-muted">' + escapeHtml(task.task_status || '') + (task.assignee_name ? ' &middot; ' + escapeHtml(task.assignee_name) : '') + '</small></div>' +
            '<button class="btn btn-sm btn-outline-danger" onclick="window.removeTaskFromCycle(\'' + publicId + '\',\'' + encodeURIComponent(task.task_public_id) + '\')"><i class="fa-solid fa-xmark"></i></button>' +
            '</div>';
        });
        html += '</div>';
        container.innerHTML = html;
      })
      .catch(function () {
        container.innerHTML = '<div class="text-danger">Ошибка загрузки задач.</div>';
      });
  }

  function loadCycleSummary(publicId) {
    var container = document.getElementById('cycleSummaryContent');
    apiRequest('api/v1/cycles/' + encodeURIComponent(publicId) + '/summary', { method: 'GET' })
      .then(function (env) {
        var s = env.data && env.data.summary || {};
        var html =
          '<div class="row g-3">' +
            '<div class="col-md-6">' +
              '<div class="card"><div class="card-body">' +
                '<h6 class="card-title">По статусам</h6>';
        var byStatus = s.by_status || {};
        for (var code in byStatus) {
          html += '<div class="d-flex justify-content-between"><span>' + escapeHtml(code) + '</span><strong>' + byStatus[code] + '</strong></div>';
        }
        html += '</div></div></div>' +
          '<div class="col-md-6">' +
            '<div class="card"><div class="card-body">' +
              '<h6 class="card-title">По приоритетам</h6>';
        var byPriority = s.by_priority || {};
        for (var p in byPriority) {
          html += '<div class="d-flex justify-content-between"><span>' + escapeHtml(p) + '</span><strong>' + byPriority[p] + '</strong></div>';
        }
        html += '</div></div></div>' +
          '</div>' +
          '<div class="mt-3"><h6>По исполнителям</h6>';
        var byAssignee = s.by_assignee || [];
        if (byAssignee.length) {
          html += '<table class="table table-sm"><thead><tr><th>Исполнитель</th><th>Всего</th><th>Завершено</th></tr></thead><tbody>';
          byAssignee.forEach(function (a) {
            html += '<tr><td>' + escapeHtml(a.name || '—') + '</td><td>' + (a.total || 0) + '</td><td>' + (a.completed || 0) + '</td></tr>';
          });
          html += '</tbody></table>';
        } else {
          html += '<p class="text-muted">Нет данных</p>';
        }
        html += '</div>';
        container.innerHTML = html;
      })
      .catch(function () {
        container.innerHTML = '<div class="text-danger">Ошибка загрузки статистики.</div>';
      });
  }

  // ====== Add Tasks ======
  var addTaskDebounceTimer = null;

  window.openAddTasksModal = function (cyclePublicId) {
    currentCyclePublicId = cyclePublicId;
    selectedTasks = {};
    document.getElementById('addTaskResults').innerHTML = '<div class="text-center text-muted py-3">Введите поисковый запрос</div>';
    document.getElementById('addTaskSearchInput').value = '';
    var modal = new bootstrap.Modal(document.getElementById('addTasksModal'));
    modal.show();
  };

  document.addEventListener('DOMContentLoaded', function () {
    var searchInput = document.getElementById('addTaskSearchInput');
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        clearTimeout(addTaskDebounceTimer);
        var query = this.value.trim();
        if (query.length < 2) {
          document.getElementById('addTaskResults').innerHTML = '<div class="text-center text-muted py-3">Введите минимум 2 символа</div>';
          return;
        }
        addTaskDebounceTimer = setTimeout(function () {
          searchTasksForCycle(query);
        }, 300);
      });
    }
  });

  function searchTasksForCycle(query) {
    apiRequest('api/v1/tasks', { method: 'GET', query: { search: query, limit: 20 } })
      .then(function (env) {
        var items = env.data && env.data.items || [];
        var container = document.getElementById('addTaskResults');
        if (!items.length) {
          container.innerHTML = '<div class="text-center text-muted py-3">Задачи не найдены</div>';
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
        document.getElementById('addTaskResults').innerHTML = '<div class="text-danger">Ошибка поиска.</div>';
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
    if (!ids.length) { alert(t('cycles.select_tasks', 'Выберите задачи.')); return; }

    document.getElementById('addTasksConfirmBtn').disabled = true;
    apiRequest('api/v1/cycles/' + encodeURIComponent(currentCyclePublicId) + '/tasks', {
      method: 'POST',
      body: { task_public_ids: ids }
    })
      .then(function () {
        document.getElementById('addTasksConfirmBtn').disabled = false;
        bootstrap.Modal.getInstance(document.getElementById('addTasksModal')).hide();
        loadCycleTasks(currentCyclePublicId);
      })
      .catch(function (err) {
        document.getElementById('addTasksConfirmBtn').disabled = false;
        alert('Ошибка добавления: ' + (err && err.message || t('cycles.unknown_error', 'неизвестная ошибка')));
      });
  };

  window.removeTaskFromCycle = function (cyclePublicId, taskPublicId) {
    if (!confirm(t('cycles.confirm_remove_task', 'Удалить задачу из цикла?'))) return;
    apiRequest('api/v1/cycles/' + encodeURIComponent(cyclePublicId) + '/tasks/' + encodeURIComponent(taskPublicId), { method: 'DELETE' })
      .then(function () { loadCycleTasks(cyclePublicId); })
      .catch(function () { alert(t('cycles.error_remove_task', 'Ошибка удаления задачи из цикла.')); });
  };

  // ====== Select Loaders ======
  function loadProjectSelect() {
    var sel = document.getElementById('cycleFormProject');
    sel.innerHTML = '<option value="">Загрузка...</option>';
    apiRequest('api/v1/projects', { method: 'GET', query: { limit: 100 } })
      .then(function (env) {
        var items = env.data && env.data.items || [];
        sel.innerHTML = '<option value="">Выберите проект</option>';
        items.forEach(function (p) {
          var opt = document.createElement('option');
          opt.value = p.public_id;
          opt.textContent = p.title;
          sel.appendChild(opt);
        });
      })
      .catch(function () { sel.innerHTML = '<option value="">Ошибка загрузки</option>'; });
  }

  function loadUserSelect() {
    var sel = document.getElementById('cycleFormOwner');
    sel.innerHTML = '<option value="">Не назначен</option>';
    apiRequest('api/v1/users', { method: 'GET', query: { limit: 100 } })
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
    sel.innerHTML = '<option value="">Загрузка...</option>';
    apiRequest('api/v1/cycles', { method: 'GET', query: { limit: 50, status: 'planned,active' } })
      .then(function (env) {
        var items = env.data && env.data.items || [];
        sel.innerHTML = '<option value="">Выберите цикл</option>';
        items.forEach(function (c) {
          if (c.public_id === excludePublicId) return;
          var opt = document.createElement('option');
          opt.value = c.public_id;
          opt.textContent = c.title + ' (' + (c.status || '') + ')';
          sel.appendChild(opt);
        });
      })
      .catch(function () { sel.innerHTML = '<option value="">Ошибка загрузки</option>'; });
  }

  // ====== Init on page load ======
  document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('cycleList')) {
      loadProjectSelectForFilter();
      window.loadWorkCycles(1);
    }
  });

  function loadProjectSelectForFilter() {
    var sel = document.getElementById('cycleProjectFilter');
    if (!sel) return;
    apiRequest('api/v1/projects', { method: 'GET', query: { limit: 200 } })
      .then(function (env) {
        var items = env.data && env.data.items || [];
        sel.innerHTML = '<option value="">Все проекты</option>';
        items.forEach(function (p) {
          var opt = document.createElement('option');
          opt.value = p.public_id;
          opt.textContent = p.title;
          sel.appendChild(opt);
        });
        sel.onchange = function () { window.loadWorkCycles(1); };
      });
  }

})();
