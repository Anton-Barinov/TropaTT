/**
 * Saved Views v2 — Web UI for managing and applying task filter views.
 * Relies on window.CRM.api.request() and window.CRM.i18n.t().
 */
window.CRM = window.CRM || {};
window.CRM.savedViews = (function () {
  'use strict';

  var t = window.CRM && window.CRM.i18n
    ? window.CRM.i18n.t
    : function (key, fallback) { return fallback || key; };

  var state = {
    views: [],
    activePublicId: null,
  };

  /**
   * Wait for the API client to be ready.
   */
  function waitForApi(cb, n) {
    if (window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function') {
      cb();
      return;
    }
    if ((n || 0) > 80) return;
    setTimeout(function () { waitForApi(cb, (n || 0) + 1); }, 50);
  }

  /**
   * Safe HTML escape.
   */
  function esc(str) {
    if (str == null) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
  }

  /**
   * Show a toast notification.
   */
  function notify(msg, type) {
    type = type || 'info';
    if (window.CRM && window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.notify === 'function') {
      window.CRM.pageApiBindings.notify(msg, type);
    } else if (window.notify) {
      window.notify(msg, type);
    } else {
      console.log('[' + type + '] ' + msg);
    }
  }

  /**
   * Collect current task filter state from the DOM.
   * Returns an object suitable for saving as a view's filters.
   */
  function collectCurrentFilters() {
    var filters = {};

    var searchEl = document.getElementById('tasksSearchInput');
    if (searchEl && searchEl.value.trim() !== '') {
      filters.search = searchEl.value.trim();
    }

    var assigneeEl = document.getElementById('tasksAssigneeFilter');
    if (assigneeEl && assigneeEl.value !== '') {
      filters.assignee_user_public_id = assigneeEl.value;
    }

    var managerEl = document.getElementById('tasksManagerFilter');
    if (managerEl && managerEl.value !== '') {
      filters.manager_user_public_id = managerEl.value;
    }

    var projectEl = document.getElementById('tasksProjectFilter');
    if (projectEl && projectEl.value !== '') {
      filters.project_public_id = projectEl.value;
    }

    // Due date filter (via active kanban-due buttons)
    var dueBtn = document.querySelector('[data-kanban-due].active');
    if (dueBtn) {
      var dueVal = dueBtn.getAttribute('data-kanban-due');
      if (dueVal === 'overdue') filters.due_at = 'overdue';
      else if (dueVal === 'today') filters.due_at = 'today';
      else if (dueVal === 'week') filters.due_at = 'week';
    }

    // Tag chip filter
    var tagEl = document.getElementById('tasksTagChipFilter');
    if (tagEl && tagEl.getAttribute('data-tag-public-id')) {
      filters.tag_public_id = tagEl.getAttribute('data-tag-public-id');
    }

    return filters;
  }

  /**
   * Apply filter values from a saved view to the DOM and trigger search.
   */
  function applyFilters(filters) {
    if (!filters) return;

    var searchEl = document.getElementById('tasksSearchInput');
    if (searchEl && filters.search != null) {
      searchEl.value = filters.search;
    }

    var assigneeEl = document.getElementById('tasksAssigneeFilter');
    if (assigneeEl && filters.assignee_user_public_id != null) {
      assigneeEl.value = filters.assignee_user_public_id;
      assigneeEl.dispatchEvent(new Event('change', { bubbles: true }));
    }

    var managerEl = document.getElementById('tasksManagerFilter');
    if (managerEl && filters.manager_user_public_id != null) {
      managerEl.value = filters.manager_user_public_id;
      managerEl.dispatchEvent(new Event('change', { bubbles: true }));
    }

    var projectEl = document.getElementById('tasksProjectFilter');
    if (projectEl && filters.project_public_id != null) {
      projectEl.value = filters.project_public_id;
      projectEl.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // Due date buttons
    var dueFilter = filters.due_at ? String(filters.due_at) : null;
    document.querySelectorAll('[data-kanban-due]').forEach(function (btn) {
      var isActive = dueFilter === btn.getAttribute('data-kanban-due');
      btn.classList.toggle('active', isActive);
    });

    // Tag chip
    var tagEl = document.getElementById('tasksTagChipFilter');
    if (tagEl) {
      if (filters.tag_public_id) {
        tagEl.style.display = '';
        tagEl.setAttribute('data-tag-public-id', filters.tag_public_id);
      } else {
        tagEl.style.display = 'none';
        tagEl.removeAttribute('data-tag-public-id');
      }
    }

    // Trigger search
    var searchInput = document.getElementById('tasksSearchInput');
    if (searchInput) {
      searchInput.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // Enable reset button if any filter is active
    var resetBtn = document.getElementById('tasksFiltersResetBtn');
    if (resetBtn) {
      var hasFilters = Object.keys(filters).length > 0;
      resetBtn.disabled = !hasFilters;
    }
  }

  /**
   * Load saved views from API.
   */
  function loadViews() {
    var container = document.getElementById('savedViewsDropdown');
    if (!container) return;

    var listEl = container.querySelector('.crm-saved-views-list');
    if (listEl) {
      listEl.innerHTML = '<div class="dropdown-item text-muted small" style="cursor:default">' + esc(t('saved_views.loading', 'Загрузка...')) + '</div>';
    }

    waitForApi(function () {
      window.CRM.api.request('GET', '/api/v1/views', { entity_type: 'task' })
        .then(function (resp) {
          if (resp && resp.success && resp.data && resp.data.items) {
            state.views = resp.data.items;
            renderViews();
          }
        })
        .catch(function (err) {
          console.error('Failed to load saved views', err);
          if (listEl) {
            listEl.innerHTML = '<div class="dropdown-item text-muted small" style="cursor:default">' + esc(t('saved_views.load_error', 'Ошибка загрузки')) + '</div>';
          }
        });
    });
  }

  /**
   * Render saved views into the dropdown list.
   */
  function renderViews() {
    var container = document.getElementById('savedViewsDropdown');
    if (!container) return;

    var listEl = container.querySelector('.crm-saved-views-list');
    if (!listEl) return;

    if (state.views.length === 0) {
      listEl.innerHTML = '<div class="dropdown-item text-muted small" style="cursor:default">' + esc(t('saved_views.empty', 'Нет сохранённых представлений')) + '</div>';
      return;
    }

    var html = '';

    // Pinned views first
    var pinned = state.views.filter(function (v) { return v.is_pinned; });
    var unpinned = state.views.filter(function (v) { return !v.is_pinned; });

    if (pinned.length > 0) {
      html += '<div class="dropdown-header small text-muted px-3 py-1">' + esc(t('saved_views.pinned', 'Закреплённые')) + '</div>';
      pinned.forEach(function (v) { html += renderViewItem(v); });
      if (unpinned.length > 0) {
        html += '<div class="dropdown-divider my-1"></div>';
        html += '<div class="dropdown-header small text-muted px-3 py-1">' + esc(t('saved_views.other', 'Другие')) + '</div>';
      }
    }

    unpinned.forEach(function (v) { html += renderViewItem(v); });

    listEl.innerHTML = html;

    // Bind click events
    listEl.querySelectorAll('[data-saved-view-apply]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        var publicId = el.getAttribute('data-saved-view-apply');
        applyView(publicId);
      });
    });

    listEl.querySelectorAll('[data-saved-view-pin]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.stopPropagation();
        var publicId = el.getAttribute('data-saved-view-pin');
        var isPinned = el.getAttribute('data-pinned') === '1';
        togglePin(publicId, !isPinned);
      });
    });

    listEl.querySelectorAll('[data-saved-view-dup]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.stopPropagation();
        var publicId = el.getAttribute('data-saved-view-dup');
        duplicateView(publicId);
      });
    });

    listEl.querySelectorAll('[data-saved-view-archive]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.stopPropagation();
        var publicId = el.getAttribute('data-saved-view-archive');
        archiveView(publicId);
      });
    });

    listEl.querySelectorAll('[data-saved-view-edit]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.stopPropagation();
        var publicId = el.getAttribute('data-saved-view-edit');
        openEditModal(publicId);
      });
    });

    // Update active state
    listEl.querySelectorAll('[data-saved-view-apply]').forEach(function (el) {
      var pid = el.getAttribute('data-saved-view-apply');
      el.closest('.dropdown-item').classList.toggle('active', pid === state.activePublicId);
    });
  }

  /**
   * Render a single view item in the dropdown.
   */
  function renderViewItem(v) {
    var accessIcon = '';
    if (v.access_level === 'system') accessIcon = '<span class="crm-sv-badge crm-sv-badge-system" title="' + esc(t('saved_views.system', 'Системное')) + '">S</span> ';
    else if (v.access_level === 'public') accessIcon = '<span class="crm-sv-badge crm-sv-badge-public" title="' + esc(t('saved_views.public', 'Публичное')) + '">P</span> ';
    else accessIcon = '<span class="crm-sv-badge crm-sv-badge-private" title="' + esc(t('saved_views.private', 'Приватное')) + '"></span> ';

    var actionsHtml = '';
    if (!v.is_locked && v.access_level !== 'system') {
      actionsHtml += '<button class="crm-sv-action crm-sv-action-pin" data-saved-view-pin="' + esc(v.public_id) + '" data-pinned="' + (v.is_pinned ? '1' : '0') + '" title="' + esc(v.is_pinned ? t('saved_views.unpin', 'Открепить') : t('saved_views.pin', 'Закрепить')) + '">' + (v.is_pinned ? '★' : '☆') + '</button>';
      actionsHtml += '<button class="crm-sv-action crm-sv-action-dup" data-saved-view-dup="' + esc(v.public_id) + '" title="' + esc(t('saved_views.duplicate', 'Дублировать')) + '">⧉</button>';
      actionsHtml += '<button class="crm-sv-action crm-sv-action-edit" data-saved-view-edit="' + esc(v.public_id) + '" title="' + esc(t('saved_views.edit', 'Редактировать')) + '">✎</button>';
      actionsHtml += '<button class="crm-sv-action crm-sv-action-archive" data-saved-view-archive="' + esc(v.public_id) + '" title="' + esc(t('saved_views.archive', 'Архивировать')) + '">🗑</button>';
    }

    return '<div class="dropdown-item d-flex align-items-center gap-1 py-1 px-3">'
      + '<span class="flex-grow-1" data-saved-view-apply="' + esc(v.public_id) + '" style="cursor:pointer">'
      + accessIcon + esc(v.title)
      + '</span>'
      + '<span class="crm-sv-actions d-flex gap-1">' + actionsHtml + '</span>'
      + '</div>';
  }

  /**
   * Apply a saved view: load its filters and trigger task search.
   */
  function applyView(publicId) {
    var view = state.views.filter(function (v) { return v.public_id === publicId; })[0];
    if (view && view.filters) {
      applyFilters(view.filters);
      state.activePublicId = publicId;

      // Touch last-used
      waitForApi(function () {
        window.CRM.api.request('POST', '/api/v1/views/' + publicId + '/touch-last-used', {});
      });

      notify(esc(t('saved_views.applied', 'Представление применено: ')) + (view.title || ''), 'success');
    } else {
      // Load from API
      waitForApi(function () {
        window.CRM.api.request('GET', '/api/v1/views/' + publicId)
          .then(function (resp) {
            if (resp && resp.success && resp.data && resp.data.saved_view) {
              var sv = resp.data.saved_view;
              if (sv.filters) applyFilters(sv.filters);
              state.activePublicId = publicId;
              window.CRM.api.request('POST', '/api/v1/views/' + publicId + '/touch-last-used', {});
              notify(esc(t('saved_views.applied', 'Представление применено: ')) + (sv.title || ''), 'success');
            }
          })
          .catch(function () {
            notify(esc(t('saved_views.apply_error', 'Ошибка применения представления')), 'error');
          });
      });
    }
  }

  /**
   * Open the save modal (for creating or updating a view).
   */
  function openSaveModal(mode) {
    var modalEl = document.getElementById('savedViewModal');
    if (!modalEl) return;

    var titleEl = modalEl.querySelector('#savedViewModalTitle');
    var nameInput = document.getElementById('savedViewNameInput');
    var descInput = document.getElementById('savedViewDescInput');
    var accessSelect = document.getElementById('savedViewAccessSelect');
    var publicIdInput = document.getElementById('savedViewPublicIdInput');

    if (mode === 'save-as') {
      if (titleEl) titleEl.textContent = esc(t('saved_views.save_as', 'Сохранить как новое представление'));
      if (publicIdInput) publicIdInput.value = '';
      if (nameInput) nameInput.value = '';
      if (descInput) descInput.value = '';
      if (accessSelect) accessSelect.value = 'private';
    }

    var modal = new bootstrap.Modal(modalEl);
    modal.show();
  }

  /**
   * Open the edit modal for an existing view.
   */
  function openEditModal(publicId) {
    var view = state.views.filter(function (v) { return v.public_id === publicId; })[0];
    if (!view) return;

    var modalEl = document.getElementById('savedViewModal');
    if (!modalEl) return;

    var titleEl = modalEl.querySelector('#savedViewModalTitle');
    var nameInput = document.getElementById('savedViewNameInput');
    var descInput = document.getElementById('savedViewDescInput');
    var accessSelect = document.getElementById('savedViewAccessSelect');
    var publicIdInput = document.getElementById('savedViewPublicIdInput');

    if (titleEl) titleEl.textContent = esc(t('saved_views.edit_title', 'Редактировать представление'));
    if (publicIdInput) publicIdInput.value = publicId;
    if (nameInput) nameInput.value = view.title || '';
    if (descInput) descInput.value = view.description || '';
    if (accessSelect) accessSelect.value = view.access_level || 'private';

    var modal = new bootstrap.Modal(modalEl);
    modal.show();
  }

  /**
   * Save or update a view from the modal form.
   */
  function handleSaveView() {
    var nameInput = document.getElementById('savedViewNameInput');
    var descInput = document.getElementById('savedViewDescInput');
    var accessSelect = document.getElementById('savedViewAccessSelect');
    var publicIdInput = document.getElementById('savedViewPublicIdInput');

    var title = nameInput ? nameInput.value.trim() : '';
    if (!title) {
      notify(esc(t('saved_views.title_required', 'Введите название представления')), 'error');
      return;
    }

    var description = descInput ? descInput.value.trim() : '';
    var accessLevel = accessSelect ? accessSelect.value : 'private';
    var publicId = publicIdInput ? publicIdInput.value.trim() : '';
    var filters = collectCurrentFilters();
    var isEdit = publicId !== '';

    var payload = {
      entity_type: 'task',
      title: title,
      description: description || undefined,
      access_level: accessLevel,
      filters: filters,
    };

    waitForApi(function () {
      if (isEdit) {
        window.CRM.api.request('PATCH', '/api/v1/views/' + publicId, payload)
          .then(function (resp) {
            if (resp && resp.success) {
              notify(esc(t('saved_views.updated', 'Представление обновлено')), 'success');
              var modalEl = document.getElementById('savedViewModal');
              if (modalEl) {
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
              }
              loadViews();
            } else {
              notify(esc(t('saved_views.update_error', 'Ошибка обновления представления')), 'error');
            }
          })
          .catch(function (err) {
            notify(esc(t('saved_views.update_error', 'Ошибка обновления представления')), 'error');
            console.error(err);
          });
      } else {
        window.CRM.api.request('POST', '/api/v1/views', payload)
          .then(function (resp) {
            if (resp && resp.success) {
              notify(esc(t('saved_views.created', 'Представление создано')), 'success');
              var modalEl = document.getElementById('savedViewModal');
              if (modalEl) {
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
              }
              loadViews();
            } else {
              notify(esc(t('saved_views.create_error', 'Ошибка создания представления')), 'error');
            }
          })
          .catch(function (err) {
            notify(esc(t('saved_views.create_error', 'Ошибка создания представления')), 'error');
            console.error(err);
          });
      }
    });
  }

  /**
   * Toggle pin/unpin for a view.
   */
  function togglePin(publicId, isPinned) {
    waitForApi(function () {
      window.CRM.api.request('POST', '/api/v1/views/' + publicId + '/pin', { is_pinned: isPinned })
        .then(function (resp) {
          if (resp && resp.success) {
            var action = isPinned ? t('saved_views.pinned', 'Закреплено') : t('saved_views.unpinned', 'Откреплено');
            notify(action, 'success');
            loadViews();
          }
        })
        .catch(function () {
          notify(esc(t('saved_views.pin_error', 'Ошибка')), 'error');
        });
    });
  }

  /**
   * Duplicate a view.
   */
  function duplicateView(publicId) {
    waitForApi(function () {
      window.CRM.api.request('POST', '/api/v1/views/' + publicId + '/duplicate', {})
        .then(function (resp) {
          if (resp && resp.success) {
            notify(esc(t('saved_views.duplicated', 'Представление дублировано')), 'success');
            loadViews();
          }
        })
        .catch(function () {
          notify(esc(t('saved_views.duplicate_error', 'Ошибка дублирования')), 'error');
        });
    });
  }

  /**
   * Archive (soft-delete) a view.
   */
  function archiveView(publicId) {
    if (!confirm(esc(t('saved_views.confirm_archive', 'Архивировать это представление?')))) return;

    waitForApi(function () {
      window.CRM.api.request('POST', '/api/v1/views/' + publicId + '/archive', {})
        .then(function (resp) {
          if (resp && resp.success) {
            notify(esc(t('saved_views.archived', 'Представление архивировано')), 'success');
            if (state.activePublicId === publicId) {
              state.activePublicId = null;
            }
            loadViews();
          }
        })
        .catch(function () {
          notify(esc(t('saved_views.archive_error', 'Ошибка архивирования')), 'error');
        });
    });
  }

  /**
   * Refresh the saved views dropdown (reload from API).
   */
  function refresh() {
    loadViews();
  }

  /**
   * Initialize saved views: set up event listeners and load views.
   */
  function init() {
    // Bind save button
    var saveBtn = document.getElementById('savedViewsSaveBtn');
    if (saveBtn) {
      saveBtn.addEventListener('click', function () {
        openSaveModal('save-as');
      });
    }

    // Bind modal save handler
    var modalSaveBtn = document.getElementById('savedViewModalSaveBtn');
    if (modalSaveBtn) {
      modalSaveBtn.addEventListener('click', handleSaveView);
    }

    // Load views after API is ready
    loadViews();
  }

  // Auto-initialize on tasks page
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return {
    init: init,
    refresh: refresh,
    applyView: applyView,
  };
})();
