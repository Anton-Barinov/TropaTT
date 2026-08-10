/**
 * Project Modules management page.
 * Auto-initializes on pages with data-page="project-modules"
 */
window.CRM = window.CRM || {};
window.CRM.projectModules = (function () {
  'use strict';

  var state = {
    modules: [],
    projects: [],
    users: [],
    editingId: null
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

  function plainText(value) {
    var text = String(value == null ? '' : value);
    if (/<[a-z][\s\S]*>/i.test(text)) {
      var tmp = document.createElement('div');
      tmp.innerHTML = text;
      text = tmp.textContent || tmp.innerText || '';
    }
    return text.replace(/\s+/g, ' ').trim();
  }

  function getVisualEditorValue(textarea) {
    if (!textarea || !window.CRM || !window.CRM.VisualEditor || typeof window.CRM.VisualEditor.getInstances !== 'function') {
      return textarea ? textarea.value : '';
    }
    var value = textarea.value;
    window.CRM.VisualEditor.getInstances().forEach(function (editor) {
      if (editor && editor._textarea === textarea && typeof editor.getValue === 'function') {
        value = editor.getValue();
      }
    });
    return value;
  }

  function refreshVisualEditors(scope) {
    if (window.CRM && window.CRM.VisualEditor && typeof window.CRM.VisualEditor.refreshEditors === 'function') {
      window.CRM.VisualEditor.refreshEditors(scope || document, true);
    }
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
  }

  function req(route, opts) {
    var api = getApi();
    if (!api) return Promise.reject(new Error('API not ready'));
    return api.request(route, opts || {});
  }

  function statusLabel(status) {
    var map = {
      backlog: t('project_modules.status_backlog', 'Backlog'),
      planned: t('project_modules.status_planned', 'Запланирован'),
      in_progress: t('project_modules.status_in_progress', 'В работе'),
      paused: t('project_modules.status_paused', 'Приостановлен'),
      completed: t('project_modules.status_completed', 'Завершён'),
      cancelled: t('project_modules.status_cancelled', 'Отменён')
    };
    return map[status] || status;
  }

  function statusBadgeHtml(status) {
    var colorMap = {
      backlog: '#6c757d',
      planned: '#0d6efd',
      in_progress: '#0f8f72',
      paused: '#ffc107',
      completed: '#198754',
      cancelled: '#dc3545'
    };
    var color = colorMap[status] || '#6c757d';
    return '<span class="badge" style="background:' + color + '">' + esc(statusLabel(status)) + '</span>';
  }

  function loadProjects() {
    req('api/v1/projects', { query: { limit: 200, archived: 0 } })
      .then(function (envelope) {
        state.projects = (envelope.data && envelope.data.items) || [];
        populateProjectSelects();
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

  function populateProjectSelects() {
    var selects = ['#projectModulesProjectFilter', '#projectModuleProject'];
    selects.forEach(function (sel) {
      var el = document.querySelector(sel);
      if (!el) return;
      var currentVal = el.value;
      el.innerHTML = '<option value="">' + (el.id === 'projectModuleProject' ? esc(t('project_modules.option_select_project', 'Выберите проект...')) : esc(t('project_modules.filter_all_projects', 'Все проекты'))) + '</option>';
      state.projects.forEach(function (p) {
        var opt = document.createElement('option');
        opt.value = p.public_id;
        opt.textContent = p.title || p.public_id;
        el.appendChild(opt);
      });
      el.value = currentVal;
    });
  }

  function populateUserSelects() {
    var el = document.getElementById('projectModuleLead');
    if (!el) return;
    var currentVal = el.value;
    el.innerHTML = '<option value="">' + esc(t('project_modules.option_no_lead', 'Не назначен')) + '</option>';
    state.users.forEach(function (u) {
      var opt = document.createElement('option');
      opt.value = u.public_id;
      opt.textContent = u.full_name || u.login || u.public_id;
      el.appendChild(opt);
    });
    el.value = currentVal;
  }

  function loadModules(filters) {
    var body = document.getElementById('projectModulesBody');
    if (!body) return;
    body.innerHTML = '<tr><td colspan="8" class="text-muted">' + esc(t('page.loading', 'Загрузка...')) + '</td></tr>';

    var query = { limit: 50 };
    if (filters && filters.project_public_id) {
      query.project_public_id = filters.project_public_id;
    }

    req('api/v1/project-modules', { query: query })
      .then(function (envelope) {
        state.modules = (envelope.data && envelope.data.items) || [];
        renderModules();
      })
      .catch(function () {
        body.innerHTML = '<tr><td colspan="8" class="text-muted">' + esc(t('project_modules.load_error', 'Ошибка загрузки модулей')) + '</td></tr>';
      });
  }

  function renderModules() {
    var body = document.getElementById('projectModulesBody');
    if (!body) return;

    var items = state.modules;
    if (!items.length) {
      body.innerHTML = '<tr><td colspan="8" class="text-muted">' + esc(t('project_modules.no_modules', 'Нет модулей. Создайте первый модуль.')) + '</td></tr>';
      return;
    }

    body.innerHTML = items.map(function (m) {
      var progress = m.progress_percent != null ? m.progress_percent : 0;
      var leadName = m.lead_name || '';
      var projectTitle = m.project_title || '';
      var targetStr = m.target_at ? m.target_at.slice(0, 10) : '';
      var tasksCount = (m.open_tasks_count != null ? m.open_tasks_count : 0) + '/' + (m.tasks_count != null ? m.tasks_count : 0);

      var descriptionPreview = plainText(m.description || '');

      return '<tr>'
        + '<td><strong>' + esc(m.title) + '</strong>' + (descriptionPreview ? '<br><small class="text-muted">' + esc(descriptionPreview.slice(0, 80)) + '</small>' : '') + '</td>'
        + '<td>' + esc(projectTitle) + '</td>'
        + '<td>' + statusBadgeHtml(m.status || 'planned') + '</td>'
        + '<td>' + esc(leadName || t('project_modules.no_lead', '—')) + '</td>'
        + '<td style="min-width:100px"><div class="progress" style="height:6px"><div class="progress-bar" style="width:' + progress + '%;background:var(--crm-primary)"></div></div><small class="text-muted">' + progress + '%</small></td>'
        + '<td>' + esc(tasksCount) + '</td>'
        + '<td><small>' + esc(targetStr || t('project_modules.no_target', '—')) + '</small></td>'
        + '<td class="text-end" style="white-space:nowrap">'
        + '<button class="btn btn-sm crm-btn-secondary pm-edit-btn" data-pm-id="' + esc(m.public_id) + '" style="font-size:11px;padding:2px 8px">' + esc(t('page.edit', 'Edit')) + '</button> '
        + '<button class="btn btn-sm crm-btn-secondary pm-archive-btn" data-pm-id="' + esc(m.public_id) + '" style="font-size:11px;padding:2px 8px">' + esc(t('project_modules.archive_btn', 'Archive')) + '</button>'
        + '</td></tr>';
    }).join('');

    bindTableEvents();
  }

  function bindTableEvents() {
    document.querySelectorAll('.pm-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-pm-id');
        openEditModal(id);
      });
    });

    document.querySelectorAll('.pm-archive-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-pm-id');
        openArchiveModal(id);
      });
    });
  }

  function openCreateModal() {
    state.editingId = null;
    document.getElementById('projectModuleModalTitle').textContent = t('project_modules.modal_create_title', 'Создать модуль');
    document.getElementById('projectModulePublicId').value = '';
    document.getElementById('projectModuleTitle').value = '';
    document.getElementById('projectModuleDescription').value = '';
    document.getElementById('projectModuleStatus').value = 'planned';
    document.getElementById('projectModuleColor').value = '#2563eb';
    document.getElementById('projectModuleStartAt').value = '';
    document.getElementById('projectModuleTargetAt').value = '';
    document.getElementById('projectModuleProject').value = '';
    document.getElementById('projectModuleLead').value = '';

    var modalEl = document.getElementById('projectModuleModal');
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
    window.setTimeout(function () { refreshVisualEditors(modalEl); }, 80);
  }

  function openEditModal(id) {
    var mod = state.modules.find(function (m) { return m.public_id === id; });
    if (!mod) return;
    state.editingId = id;

    document.getElementById('projectModuleModalTitle').textContent = t('project_modules.modal_edit_title', 'Редактировать модуль');
    document.getElementById('projectModulePublicId').value = mod.public_id;
    document.getElementById('projectModuleTitle').value = mod.title || '';
    document.getElementById('projectModuleDescription').value = mod.description || '';
    document.getElementById('projectModuleStatus').value = mod.status || 'planned';
    document.getElementById('projectModuleColor').value = mod.color || '#2563eb';
    document.getElementById('projectModuleStartAt').value = mod.start_at ? mod.start_at.slice(0, 10) : '';
    document.getElementById('projectModuleTargetAt').value = mod.target_at ? mod.target_at.slice(0, 10) : '';
    document.getElementById('projectModuleProject').value = mod.project_public_id || '';
    document.getElementById('projectModuleLead').value = mod.lead_user_public_id || '';

    var modalEl = document.getElementById('projectModuleModal');
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
    window.setTimeout(function () { refreshVisualEditors(modalEl); }, 80);
  }

  function handleSave(e) {
    e.preventDefault();

    var title = document.getElementById('projectModuleTitle').value.trim();
    var projectPublicId = document.getElementById('projectModuleProject').value;
    var descriptionTextarea = document.getElementById('projectModuleDescription');
    var description = getVisualEditorValue(descriptionTextarea).trim();
    var status = document.getElementById('projectModuleStatus').value;
    var leadUserPublicId = document.getElementById('projectModuleLead').value;
    var color = document.getElementById('projectModuleColor').value;
    var startAt = document.getElementById('projectModuleStartAt').value;
    var targetAt = document.getElementById('projectModuleTargetAt').value;
    var publicId = document.getElementById('projectModulePublicId').value;

    if (!title) {
      notify(t('project_modules.error_title_required', 'Введите название модуля'), 'error');
      return;
    }
    if (!projectPublicId) {
      notify(t('project_modules.error_project_required', 'Выберите проект'), 'error');
      return;
    }

    var payload = {
      project_public_id: projectPublicId,
      title: title,
      description: description || undefined,
      status: status,
      color: color !== '#2563eb' ? color : undefined,
      lead_user_public_id: leadUserPublicId || undefined,
      start_at: startAt || undefined,
      target_at: targetAt || undefined
    };

    var isEdit = !!publicId;

    waitForApi(function () {
      var method = isEdit ? 'PATCH' : 'POST';
      var url = isEdit ? 'api/v1/project-modules/' + encodeURIComponent(publicId) : 'api/v1/project-modules';

      req(url, { method: method, body: payload })
        .then(function (resp) {
          if (resp && resp.success) {
            notify(isEdit
              ? t('project_modules.updated', 'Модуль обновлён')
              : t('project_modules.created', 'Модуль создан'), 'success');
            var modalEl = document.getElementById('projectModuleModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            loadModules(getFilterState());
          } else {
            notify(t('project_modules.save_error', 'Ошибка сохранения модуля'), 'error');
          }
        })
        .catch(function () {
          notify(t('project_modules.save_error', 'Ошибка сохранения модуля'), 'error');
        });
    });
  }

  function openArchiveModal(id) {
    var btn = document.getElementById('projectModuleArchiveConfirmBtn');
    btn.setAttribute('data-pm-id', id);
    var modal = new bootstrap.Modal(document.getElementById('projectModuleArchiveModal'));
    modal.show();
  }

  function confirmArchive() {
    var btn = document.getElementById('projectModuleArchiveConfirmBtn');
    var id = btn.getAttribute('data-pm-id');
    if (!id) return;

    waitForApi(function () {
      req('api/v1/project-modules/' + encodeURIComponent(id) + '/archive', { method: 'POST', body: {} })
        .then(function (resp) {
          if (resp && resp.success) {
            notify(t('project_modules.archived', 'Модуль архивирован'), 'success');
            var modalEl = document.getElementById('projectModuleArchiveModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            loadModules(getFilterState());
          } else {
            notify(t('project_modules.archive_error', 'Ошибка архивирования'), 'error');
          }
        })
        .catch(function () {
          notify(t('project_modules.archive_error', 'Ошибка архивирования'), 'error');
        });
    });
  }

  function getFilterState() {
    var projectFilter = document.getElementById('projectModulesProjectFilter');
    return {
      project_public_id: projectFilter ? projectFilter.value : null
    };
  }

  function init() {
    if (document.body.getAttribute('data-page') !== 'project-modules') return;

    waitForApi(function () {
      loadProjects();
      loadUsers();
      loadModules(getFilterState());

      document.getElementById('projectModulesRefreshBtn').addEventListener('click', function () {
        loadModules(getFilterState());
      });
      document.getElementById('projectModulesCreateBtn').addEventListener('click', openCreateModal);
      document.getElementById('projectModuleForm').addEventListener('submit', handleSave);
      document.getElementById('projectModuleArchiveConfirmBtn').addEventListener('click', confirmArchive);

      document.getElementById('projectModulesProjectFilter').addEventListener('change', function () {
        loadModules(getFilterState());
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return {
    loadModules: loadModules,
    openCreateModal: openCreateModal,
    openEditModal: openEditModal
  };
})();
