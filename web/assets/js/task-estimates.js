/**
 * Task Estimates - display, assign, and remove estimates on task detail page
 */
window.CRM = window.CRM || {};
window.CRM.taskEstimates = (function () {
  'use strict';

  var currentTaskId = null;

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
    if ((n || 0) > 100) return;
    setTimeout(function () { waitForApi(cb, (n || 0) + 1); }, 100);
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
    console.log('[task-estimates]', type, text);
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

  function loadEstimates(taskId) {
    var container = document.getElementById('taskEstimatesList');
    if (!container) return;
    container.innerHTML = '<div class="text-muted small">' + esc(t('page.loading', 'Loading...')) + '</div>';

    req('api/v1/tasks/' + encodeURIComponent(taskId) + '/estimates')
      .then(function (envelope) {
        var estimates = (envelope.data && envelope.data.items) || [];
        renderEstimates(estimates);
      })
      .catch(function () {
        container.innerHTML = '<div class="text-muted small">' + esc(t('task_estimates.load_error', 'Error loading estimates')) + '</div>';
      });
  }

  function renderEstimates(estimates) {
    var container = document.getElementById('taskEstimatesList');
    if (!container) return;

    if (!estimates.length) {
      container.innerHTML = '<div class="text-muted small">' + esc(t('task_detail.estimates_empty', 'Оценок пока нет')) + '</div>';
      return;
    }

    container.innerHTML = estimates.map(function (e) {
      return '<div class="crm-estimate-item d-flex justify-content-between align-items-center mb-1 p-1" style="border-bottom:1px solid rgba(17,26,25,0.07)">'
        + '<div>'
        + '<strong>' + esc(e.option_label || e.text_value || '—') + '</strong>'
        + ' <span class="text-muted small">(' + esc(e.estimate_set_name || '') + ')</span>'
        + '</div>'
        + '<button class="btn btn-sm crm-btn-ghost crm-estimate-remove-btn" data-set-id="' + esc(e.estimate_set_public_id) + '" title="' + esc(t('task_estimates.remove', 'Remove')) + '" style="font-size:11px;padding:2px 6px;min-height:24px;border:0;color:var(--crm-danger)">'
        + '<i class="fa-solid fa-xmark"></i>'
        + '</button>'
        + '</div>';
    }).join('');

    // Bind remove buttons
    container.querySelectorAll('.crm-estimate-remove-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var setId = btn.getAttribute('data-set-id');
        if (confirm(t('task_estimates.remove_confirm', 'Remove this estimate?'))) {
          removeEstimate(currentTaskId, setId);
        }
      });
    });
  }

  function removeEstimate(taskId, setPublicId) {
    req('api/v1/tasks/' + encodeURIComponent(taskId) + '/estimates/' + encodeURIComponent(setPublicId), { method: 'DELETE' })
      .then(function () {
        notify(t('task_estimates.removed', 'Estimate removed'), 'success');
        loadEstimates(taskId);
      })
      .catch(function () {
        notify(t('task_estimates.remove_error', 'Error removing estimate'), 'error');
      });
  }

  function openAssignModal(taskId) {
    currentTaskId = taskId;
    var select = document.getElementById('taskEstimateSetSelect');
    var optionSelect = document.getElementById('taskEstimateOptionSelect');
    if (!select) return;

    // Reset
    select.innerHTML = '<option value="">' + esc(t('task_estimates.select_set', 'Select set...')) + '</option>';
    optionSelect.innerHTML = '<option value="">' + esc(t('task_estimates.select_option', 'Select option...')) + '</option>';
    optionSelect.disabled = true;

    // Load sets
    req('api/v1/estimate-sets', { query: { limit: 50 } })
      .then(function (envelope) {
        var sets = (envelope.data && envelope.data.items) || [];
        sets.forEach(function (set) {
          var opt = document.createElement('option');
          opt.value = set.public_id;
          opt.textContent = set.name + ' (' + typeLabel(set.estimate_type) + ')';
          select.appendChild(opt);
        });
      })
      .catch(function () {
        notify(t('task_estimates.load_sets_error', 'Error loading sets'), 'error');
      });

    var modal = new bootstrap.Modal(document.getElementById('taskEstimateAssignModal'));
    modal.show();
  }

  function onSetChange() {
    var select = document.getElementById('taskEstimateSetSelect');
    var optionSelect = document.getElementById('taskEstimateOptionSelect');
    var setId = select.value;
    if (!setId) {
      optionSelect.innerHTML = '<option value="">' + esc(t('task_estimates.select_option', 'Select option...')) + '</option>';
      optionSelect.disabled = true;
      return;
    }

    optionSelect.innerHTML = '<option value="">' + esc(t('page.loading', 'Loading...')) + '</option>';
    optionSelect.disabled = true;

    req('api/v1/estimate-sets/' + encodeURIComponent(setId) + '/options')
      .then(function (envelope) {
        var options = (envelope.data && envelope.data.items) || [];
        optionSelect.innerHTML = '<option value="">' + esc(t('task_estimates.select_option', 'Select option...')) + '</option>';
        options.forEach(function (opt) {
          var o = document.createElement('option');
          o.value = opt.public_id;
          o.textContent = opt.label + ' (' + opt.numeric_value + ')';
          optionSelect.appendChild(o);
        });
        optionSelect.disabled = false;
      })
      .catch(function () {
        optionSelect.innerHTML = '<option value="">' + esc(t('task_estimates.load_options_error', 'Error loading options')) + '</option>';
      });
  }

  function assignEstimate() {
    var setId = document.getElementById('taskEstimateSetSelect').value;
    var optionId = document.getElementById('taskEstimateOptionSelect').value;
    if (!setId || !optionId) {
      notify(t('task_estimates.select_required', 'Please select a set and an option'), 'error');
      return;
    }

    req('api/v1/tasks/' + encodeURIComponent(currentTaskId) + '/estimates', {
      method: 'POST',
      body: { estimate_set_public_id: setId, estimate_option_public_id: optionId }
    }).then(function () {
      notify(t('task_estimates.assigned', 'Estimate assigned'), 'success');
      var modalEl = document.getElementById('taskEstimateAssignModal');
      var modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
      loadEstimates(currentTaskId);
    }).catch(function () {
      notify(t('task_estimates.assign_error', 'Error assigning estimate'), 'error');
    });
  }

  function init() {
    var page = document.body.getAttribute('data-page');
    if (page !== 'tasks' && page !== 'task-detail') return;

    // Extract task ID from URL
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('route') !== 'task-detail') return;
    currentTaskId = urlParams.get('task_public_id') || urlParams.get('id');
    if (!currentTaskId) return;

    waitForApi(function () {
      // Initial load
      loadEstimates(currentTaskId);

      // Bind add estimate button
      var addBtn = document.getElementById('taskEstimateAddBtn');
      if (addBtn) {
        addBtn.addEventListener('click', function () { openAssignModal(currentTaskId); });
      }

      // Bind set change handler
      var setSelect = document.getElementById('taskEstimateSetSelect');
      if (setSelect) {
        setSelect.addEventListener('change', onSetChange);
      }

      // Bind assign button in modal
      var assignBtn = document.getElementById('taskEstimateAssignBtn');
      if (assignBtn) {
        assignBtn.addEventListener('click', assignEstimate);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return {
    loadEstimates: loadEstimates,
    openAssignModal: openAssignModal,
    assignEstimate: assignEstimate
  };
})();
