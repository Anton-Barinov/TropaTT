/**
 * Admin Estimate Sets Management
 * Auto-initializes on pages with data-page="admin-estimates"
 */
window.CRM = window.CRM || {};
window.CRM.adminEstimates = (function () {
  'use strict';

  var state = {
    sets: [],
    projects: [],
    editingId: null,
    optionCounter: 0
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
    console.log('[admin-estimates]', type, text);
  }

  function req(route, opts) {
    var api = getApi();
    if (!api) return Promise.reject(new Error('API not ready'));
    return api.request(route, opts || {});
  }

  function typeLabel(type) {
    var map = {
      tshirt: t('admin_estimates.type_tshirt', 'T-shirt Size'),
      tshirt_size: t('admin_estimates.type_tshirt', 'T-shirt Size'),
      complexity: t('admin_estimates.type_complexity', 'Complexity'),
      risk: t('admin_estimates.type_risk', 'Risk'),
      story_points: t('admin_estimates.type_sp', 'Story Points'),
      hours: t('admin_estimates.type_hours', 'Hours'),
      cost: t('admin_estimates.type_cost', 'Cost'),
      custom: t('admin_estimates.type_custom', 'Custom')
    };
    return map[type] || type || '—';
  }

  function optionLabel(option) {
    var label = option.label || option.code || '';
    var value = option.numeric_value;
    if (value === null || value === undefined || value === '') return label;
    return label + ' · ' + value;
  }

  function loadSets() {
    var body = document.getElementById('adminEstimatesBody');
    if (!body) return;
    body.innerHTML = '<tr><td colspan="6" class="text-muted">' + esc(t('page.loading', 'Loading...')) + '</td></tr>';

    req('api/v1/estimate-sets', { query: { limit: 100 } })
      .then(function (envelope) {
        state.sets = (envelope.data && envelope.data.items) || [];
        return loadSetOptions(state.sets);
      })
      .then(function () {
        renderSets();
      })
      .catch(function () {
        body.innerHTML = '<tr><td colspan="6" class="text-muted">' + esc(t('admin_estimates.load_error', 'Failed to load estimate sets')) + '</td></tr>';
      });
  }

  function loadSetOptions(sets) {
    var jobs = (sets || []).map(function (set) {
      return req('api/v1/estimate-sets/' + encodeURIComponent(set.public_id) + '/options', { query: { active: 1 } })
        .then(function (envelope) {
          set.options = (envelope.data && envelope.data.items) || [];
        })
        .catch(function () {
          set.options = [];
        });
    });
    return Promise.all(jobs);
  }

  function renderSets() {
    var body = document.getElementById('adminEstimatesBody');
    if (!body) return;

    var items = state.sets;
    if (!items.length) {
      body.innerHTML = '<tr><td colspan="6" class="text-muted">' + esc(t('admin_estimates.no_sets', 'No estimate sets found')) + '</td></tr>';
      return;
    }

    body.innerHTML = items.map(function (set) {
      var optionsList = (set.options || []).map(function (o) {
        return '<span class="crm-admin-estimate-option">' + esc(optionLabel(o)) + '</span>';
      }).join('');

      var scopeLabel = set.scope_type === 'project' && set.project_title
        ? set.project_title
        : (set.scope_type === 'project' ? t('admin_estimates.scope_project', 'Project') : t('admin_estimates.scope_global', 'Global'));

      return '<tr>'
        + '<td><strong>' + esc(set.name || set.code) + '</strong>' + (set.description ? '<br><small class="text-muted">' + esc(set.description) + '</small>' : '') + '</td>'
        + '<td><code>' + esc(set.code) + '</code></td>'
        + '<td><span class="crm-badge crm-badge-info">' + esc(typeLabel(set.estimate_type)) + '</span></td>'
        + '<td>' + esc(scopeLabel) + '</td>'
        + '<td><div class="crm-admin-estimate-options">' + (optionsList || '<span class="text-muted small">' + esc(t('admin_estimates.no_options_short', 'Нет опций')) + '</span>') + '</div></td>'
        + '<td class="crm-admin-estimate-actions">'
        + '<button class="btn btn-sm crm-btn-secondary admin-estimates-edit-btn" data-set-id="' + esc(set.public_id) + '">' + esc(t('page.edit', 'Edit')) + '</button>'
        + '<div class="dropdown d-inline-flex">'
        + '<button class="btn btn-sm crm-btn-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="' + esc(t('admin_estimates.more_actions', 'More actions')) + '"><i class="fa-solid fa-ellipsis"></i></button>'
        + '<ul class="dropdown-menu dropdown-menu-end">'
        + '<li><button class="dropdown-item admin-estimates-archive-btn" type="button" data-set-id="' + esc(set.public_id) + '">' + esc(t('admin_estimates.archive_btn', 'Archive')) + '</button></li>'
        + '<li><button class="dropdown-item text-danger admin-estimates-delete-btn" type="button" data-set-id="' + esc(set.public_id) + '">' + esc(t('page.delete', 'Delete')) + '</button></li>'
        + '</ul></div>'
        + '</td></tr>';
    }).join('');

    bindTableEvents();
  }

  function bindTableEvents() {
    document.querySelectorAll('.admin-estimates-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-set-id');
        openEditModal(id);
      });
    });

    document.querySelectorAll('.admin-estimates-archive-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-set-id');
        openArchiveModal(id);
      });
    });

    document.querySelectorAll('.admin-estimates-delete-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-set-id');
        if (confirm(t('admin_estimates.delete_confirm', 'Delete this estimate set permanently?'))) {
          deleteSet(id);
        }
      });
    });
  }

  function openCreateModal() {
    state.editingId = null;
    state.optionCounter = 0;
    document.getElementById('estimateSetModalTitle').textContent = t('admin_estimates.modal_create_title', 'Create Estimate Set');
    document.getElementById('estimateSetPublicId').value = '';
    document.getElementById('estimateSetForm').reset();
    document.getElementById('estimateSetOptionsList').innerHTML = '<div class="text-muted small">' + esc(t('admin_estimates.no_options', 'No options. Add at least one option.')) + '</div>';
    document.getElementById('estimateSetProjectField').style.display = 'none';
    var modal = new bootstrap.Modal(document.getElementById('estimateSetModal'));
    modal.show();
    loadProjects();
  }

  function openEditModal(id) {
    var set = state.sets.find(function (s) { return s.public_id === id; });
    if (!set) return;
    state.editingId = id;
    state.optionCounter = (set.options || []).length;

    document.getElementById('estimateSetModalTitle').textContent = t('admin_estimates.modal_edit_title', 'Edit Estimate Set');
    document.getElementById('estimateSetPublicId').value = set.public_id;
    document.getElementById('estimateSetName').value = set.name || '';
    document.getElementById('estimateSetCode').value = set.code || '';
    document.getElementById('estimateSetType').value = set.estimate_type === 'tshirt_size' ? 'tshirt' : (set.estimate_type || 'custom');
    document.getElementById('estimateSetScope').value = set.scope_type || 'global';
    document.getElementById('estimateSetDescription').value = set.description || '';

    document.getElementById('estimateSetProjectField').style.display = set.scope_type === 'project' ? '' : 'none';

    renderOptions(set.options || []);
    var modal = new bootstrap.Modal(document.getElementById('estimateSetModal'));
    modal.show();
    loadProjects().then(function () {
      document.getElementById('estimateSetProject').value = set.project_public_id || '';
    });
  }

  function renderOptions(options) {
    var list = document.getElementById('estimateSetOptionsList');
    if (!options.length) {
      list.innerHTML = '<div class="text-muted small">' + esc(t('admin_estimates.no_options', 'No options. Add at least one option.')) + '</div>';
      return;
    }

    list.innerHTML = options.map(function (opt, index) {
      var optId = opt.public_id || ('__new_' + index);
      return '<div class="crm-estimate-option-row" data-opt-id="' + esc(optId) + '">'
        + '<input class="form-control form-control-sm crm-estimate-option-label" placeholder="' + esc(t('admin_estimates.option_label_placeholder', 'Label')) + '" value="' + esc(opt.label || '') + '">'
        + '<input class="form-control form-control-sm crm-estimate-option-value" type="number" step="0.1" placeholder="' + esc(t('admin_estimates.option_value_placeholder', 'Value')) + '" value="' + esc(String(opt.numeric_value != null ? opt.numeric_value : '')) + '">'
        + '<input class="form-control form-control-sm crm-estimate-option-order" type="number" step="1" placeholder="' + esc(t('admin_estimates.option_order_placeholder', 'Order')) + '" value="' + esc(String(opt.sort_order != null ? opt.sort_order : index + 1)) + '">'
        + '<input type="hidden" class="crm-estimate-option-public-id" value="' + esc(opt.public_id || '') + '">'
        + '<button type="button" class="btn btn-sm crm-btn-danger-soft crm-estimate-option-remove">' + esc(t('page.delete', 'Delete')) + '</button>'
        + '</div>';
    }).join('');

    bindOptionEvents();
  }

  function bindOptionEvents() {
    document.querySelectorAll('.crm-estimate-option-remove').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var row = btn.closest('.crm-estimate-option-row');
        if (row) row.remove();
        checkOptionsEmpty();
      });
    });
  }

  function checkOptionsEmpty() {
    var list = document.getElementById('estimateSetOptionsList');
    var rows = list.querySelectorAll('.crm-estimate-option-row');
    if (!rows.length) {
      list.innerHTML = '<div class="text-muted small">' + esc(t('admin_estimates.no_options', 'No options. Add at least one option.')) + '</div>';
    }
  }

  function addOptionRow() {
    var list = document.getElementById('estimateSetOptionsList');
    var emptyMsg = list.querySelector('.text-muted.small');
    if (emptyMsg) emptyMsg.remove();

    var index = state.optionCounter++;
    var html = '<div class="crm-estimate-option-row" data-opt-id="__new_' + index + '">'
      + '<input class="form-control form-control-sm crm-estimate-option-label" placeholder="' + esc(t('admin_estimates.option_label_placeholder', 'Label')) + '">'
      + '<input class="form-control form-control-sm crm-estimate-option-value" type="number" step="0.1" placeholder="' + esc(t('admin_estimates.option_value_placeholder', 'Value')) + '">'
      + '<input class="form-control form-control-sm crm-estimate-option-order" type="number" step="1" placeholder="' + esc(t('admin_estimates.option_order_placeholder', 'Order')) + '" value="' + (index + 1) + '">'
      + '<input type="hidden" class="crm-estimate-option-public-id" value="">'
      + '<button type="button" class="btn btn-sm crm-btn-danger-soft crm-estimate-option-remove">' + esc(t('page.delete', 'Delete')) + '</button>'
      + '</div>';

    list.insertAdjacentHTML('beforeend', html);
    bindOptionEvents();
  }

  function collectOptions() {
    var rows = document.querySelectorAll('#estimateSetOptionsList .crm-estimate-option-row');
    var options = [];
    rows.forEach(function (row) {
      var label = row.querySelector('.crm-estimate-option-label').value.trim();
      var numericValue = parseFloat(row.querySelector('.crm-estimate-option-value').value);
      var sortOrder = parseInt(row.querySelector('.crm-estimate-option-order').value, 10);
      var publicId = row.querySelector('.crm-estimate-option-public-id').value;

      if (!label) return;
      if (isNaN(numericValue)) numericValue = 0;

      var opt = { label: label, numeric_value: numericValue, sort_order: isNaN(sortOrder) ? 0 : sortOrder };
      if (publicId) opt.public_id = publicId;
      options.push(opt);
    });
    return options;
  }

  function saveSet(e) {
    e.preventDefault();

    var name = document.getElementById('estimateSetName').value.trim();
    var code = document.getElementById('estimateSetCode').value.trim();
    var estimateType = document.getElementById('estimateSetType').value;
    var scopeType = document.getElementById('estimateSetScope').value;
    var projectPublicId = document.getElementById('estimateSetProject').value;
    var description = document.getElementById('estimateSetDescription').value.trim();
    var options = collectOptions();

    if (!name) {
      notify(t('admin_estimates.error_name_required', 'Name is required'), 'error');
      return;
    }
    if (!options.length) {
      notify(t('admin_estimates.error_options_required', 'At least one option is required'), 'error');
      return;
    }

    var body = {
      name: name,
      code: code || undefined,
      estimate_type: estimateType,
      scope_type: scopeType,
      description: description || undefined,
      options: options
    };

    if (scopeType === 'project' && projectPublicId) {
      body.project_public_id = projectPublicId;
    }

    var isEdit = !!state.editingId;
    var method = isEdit ? 'PATCH' : 'POST';
    var url = isEdit ? 'api/v1/estimate-sets/' + encodeURIComponent(state.editingId) : 'api/v1/estimate-sets';

    // For edit, options are managed via separate endpoints, so don't send them
    if (isEdit) {
      delete body.options;
    }

    req(url, { method: method, body: body })
      .then(function () {
        if (isEdit) {
          return syncOptions(state.editingId, options);
        }
      })
      .then(function () {
        var msg = isEdit
          ? t('admin_estimates.updated', 'Set updated')
          : t('admin_estimates.created', 'Set created');
        notify(msg, 'success');
        var modalEl = document.getElementById('estimateSetModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        loadSets();
      })
      .catch(function () {
        notify(t('admin_estimates.save_error', 'Error saving estimate set'), 'error');
      });
  }

  function syncOptions(setPublicId, options) {
    // Get existing options from state
    var set = state.sets.find(function (s) { return s.public_id === setPublicId; });
    var existingOptions = (set && set.options) || [];

    var existingIds = existingOptions.map(function (o) { return o.public_id; }).filter(Boolean);
    var newOptions = options.filter(function (o) { return !o.public_id; });
    var updatedOptions = options.filter(function (o) { return o.public_id; });
    var updatedIds = updatedOptions.map(function (o) { return o.public_id; });
    var removedIds = existingIds.filter(function (id) { return updatedIds.indexOf(id) === -1; });

    var promises = [];

    // Delete removed options
    removedIds.forEach(function (id) {
      promises.push(
        req('api/v1/estimate-options/' + encodeURIComponent(id), { method: 'DELETE' })
          .catch(function () {})
      );
    });

    // Update existing options
    updatedOptions.forEach(function (opt) {
      promises.push(
        req('api/v1/estimate-options/' + encodeURIComponent(opt.public_id), {
          method: 'PATCH',
          body: { label: opt.label, numeric_value: opt.numeric_value, sort_order: opt.sort_order }
        }).catch(function () {})
      );
    });

    // Create new options
    newOptions.forEach(function (opt) {
      promises.push(
        req('api/v1/estimate-sets/' + encodeURIComponent(setPublicId) + '/options', {
          method: 'POST',
          body: { label: opt.label, numeric_value: opt.numeric_value, sort_order: opt.sort_order }
        }).catch(function () {})
      );
    });

    return Promise.all(promises);
  }

  function openArchiveModal(id) {
    var btn = document.getElementById('estimateArchiveConfirmBtn');
    btn.setAttribute('data-set-id', id);
    var modal = new bootstrap.Modal(document.getElementById('estimateArchiveModal'));
    modal.show();
  }

  function confirmArchive() {
    var btn = document.getElementById('estimateArchiveConfirmBtn');
    var id = btn.getAttribute('data-set-id');
    if (!id) return;

    req('api/v1/estimate-sets/' + encodeURIComponent(id) + '/archive', { method: 'POST', body: {} })
      .then(function () {
        notify(t('admin_estimates.archived', 'Set archived'), 'success');
        var modalEl = document.getElementById('estimateArchiveModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        loadSets();
      })
      .catch(function () {
        notify(t('admin_estimates.archive_error', 'Error archiving set'), 'error');
      });
  }

  function deleteSet(id) {
    req('api/v1/estimate-sets/' + encodeURIComponent(id), { method: 'DELETE' })
      .then(function () {
        notify(t('admin_estimates.deleted', 'Set deleted'), 'success');
        loadSets();
      })
      .catch(function () {
        notify(t('admin_estimates.delete_error', 'Error deleting set'), 'error');
      });
  }

  function loadProjects() {
    var select = document.getElementById('estimateSetProject');
    if (!select) return Promise.resolve();
    if (select.options.length > 1) return Promise.resolve(); // already loaded

    return req('api/v1/projects', { query: { limit: 200, status: 'active' } })
      .then(function (envelope) {
        var projects = (envelope.data && envelope.data.items) || [];
        state.projects = projects;
        projects.forEach(function (p) {
          var opt = document.createElement('option');
          opt.value = p.public_id;
          opt.textContent = p.title || p.public_id;
          select.appendChild(opt);
        });
      })
      .catch(function () {});
  }

  function init() {
    if (document.body.getAttribute('data-page') !== 'admin-estimates') return;

    waitForApi(function () {
      loadSets();

      document.getElementById('adminEstimatesRefreshBtn').addEventListener('click', loadSets);
      document.getElementById('adminEstimatesCreateBtn').addEventListener('click', openCreateModal);
      document.getElementById('estimateSetForm').addEventListener('submit', saveSet);
      document.getElementById('estimateSetAddOptionBtn').addEventListener('click', addOptionRow);
      document.getElementById('estimateArchiveConfirmBtn').addEventListener('click', confirmArchive);

      document.getElementById('estimateSetScope').addEventListener('change', function () {
        var projectField = document.getElementById('estimateSetProjectField');
        projectField.style.display = this.value === 'project' ? '' : 'none';
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return {
    loadSets: loadSets,
    openCreateModal: openCreateModal,
    openEditModal: openEditModal
  };
})();
