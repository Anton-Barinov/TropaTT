/**
 * Task detail sidebar blocks.
 *
 * Lets the user hide, re-add and reorder the blocks of the right column on the
 * task card (core blocks + module-injected blocks). Layout is persisted per
 * user through GET/PUT api/v1/tasks/sidebar.
 */
window.CRM = window.CRM || {};
window.CRM.taskSidebarBlocks = (function () {
  'use strict';

  var EDIT_MODE = '1';

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

  function notify(text, type) {
    if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.notify === 'function') {
      window.CRM.pageApiBindings.notify(text, type);
      return;
    }
    if (typeof window.notify === 'function') {
      window.notify(text, type);
    }
  }

  function req(route, opts) {
    var api = getApi();
    if (!api) return Promise.reject(new Error('API not ready'));
    return api.request(route, opts || {});
  }

  var config = { catalog: [], active: [] };
  var sortable = null;
  var started = false;

  function rail() {
    return document.querySelector('[data-task-sidebar-rail]');
  }

  function pool() {
    return document.getElementById('taskSidebarBlockPool');
  }

  function blockNodeByKey(key) {
    var found = null;
    document.querySelectorAll('[data-task-sidebar-block]').forEach(function (node) {
      if (!found && node.getAttribute('data-task-sidebar-block') === key) found = node;
    });
    return found;
  }

  function catalogEntry(key) {
    for (var i = 0; i < config.catalog.length; i += 1) {
      if (config.catalog[i] && config.catalog[i].key === key) return config.catalog[i];
    }
    return null;
  }

  function blockTitle(key) {
    var node = blockNodeByKey(key);
    if (node) {
      var heading = node.querySelector('h2');
      var text = heading ? String(heading.textContent || '').trim() : '';
      if (text) return text;
    }
    var entry = catalogEntry(key);
    if (entry) {
      var label = t(entry.label_key || '', entry.label || key);
      if (label && label !== key) return label;
    }
    return key;
  }

  function blockRenderable(key) {
    var node = blockNodeByKey(key);
    if (node && node.getAttribute('data-requires-ai-use') === '1') {
      var api = getApi();
      if (api && typeof api.hasPermission === 'function' && !api.hasPermission('ai.use')) return false;
    }
    return true;
  }

  function applyVisibility(active) {
    var r = rail();
    var p = pool();
    var nodes = {};
    document.querySelectorAll('[data-task-sidebar-block]').forEach(function (node) {
      nodes[node.getAttribute('data-task-sidebar-block')] = node;
    });

    var activeList = Array.isArray(active) ? active : [];
    if (activeList.length) {
      activeList.forEach(function (key) {
        var node = nodes[key];
        if (!node || !blockRenderable(key)) return;
        node.classList.remove('d-none');
        if (r) r.appendChild(node);
      });
      Object.keys(nodes).forEach(function (key) {
        if (activeList.indexOf(key) !== -1) return;
        var node = nodes[key];
        node.classList.add('d-none');
        if (p && node.parentNode !== p) p.appendChild(node);
      });
    } else {
      Object.keys(nodes).forEach(function (key) {
        var node = nodes[key];
        if (blockRenderable(key)) node.classList.remove('d-none');
      });
    }
  }

  function injectControls(node) {
    if (!node || node.querySelector('.crm-widget-builder-controls')) return;
    var key = node.getAttribute('data-task-sidebar-block');
    var controls = document.createElement('div');
    controls.className = 'crm-widget-builder-controls';
    var drag = document.createElement('button');
    drag.type = 'button';
    drag.className = 'crm-widget-builder-drag crm-widget-builder-drag-handle';
    drag.title = t('js.pab.builder_drag', 'Drag to reorder');
    drag.setAttribute('aria-label', drag.title);
    drag.innerHTML = '<i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>';
    var remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'crm-widget-builder-remove';
    remove.title = t('js.pab.builder_remove', 'Remove block');
    remove.setAttribute('aria-label', remove.title);
    remove.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
    remove.addEventListener('click', function () { removeBlock(key); });
    controls.appendChild(drag);
    controls.appendChild(remove);
    node.appendChild(controls);
  }

  function enterEditMode() {
    window.CRM.__taskSidebarEditMode = EDIT_MODE;
    var r = rail();
    if (r) {
      r.classList.add('crm-task-sidebar-edit-mode');
      r.querySelectorAll('[data-task-sidebar-block]').forEach(injectControls);
    }
    var bar = document.querySelector('[data-task-sidebar-builder-bar]');
    if (bar) bar.classList.remove('d-none');
    var toggle = document.querySelector('[data-task-sidebar-edit-toggle]');
    if (toggle) toggle.classList.add('active');
    initSortable();
  }

  function exitEditMode() {
    window.CRM.__taskSidebarEditMode = '0';
    var r = rail();
    if (r) r.classList.remove('crm-task-sidebar-edit-mode');
    var bar = document.querySelector('[data-task-sidebar-builder-bar]');
    if (bar) bar.classList.add('d-none');
    var toggle = document.querySelector('[data-task-sidebar-edit-toggle]');
    if (toggle) toggle.classList.remove('active');
    document.querySelectorAll('.crm-widget-builder-controls').forEach(function (c) { c.remove(); });
    if (sortable) { sortable.destroy(); sortable = null; }
  }

  function removeBlock(key) {
    var p = pool();
    var node = blockNodeByKey(key);
    if (!node) return;
    node.classList.add('d-none');
    node.classList.remove('crm-widget-placeholder');
    if (p) p.appendChild(node);
    refreshCatalog();
  }

  function addBlock(key) {
    var p = pool();
    var r = rail();
    var node = (p && p.querySelector('[data-task-sidebar-block="' + key + '"]')) || blockNodeByKey(key);
    if (!node || !r) return;
    node.classList.remove('d-none');
    if (node.parentNode !== r) r.appendChild(node);
    if (window.CRM.__taskSidebarEditMode === EDIT_MODE) injectControls(node);
    refreshCatalog();
  }

  function initSortable() {
    if (typeof window.Sortable !== 'function') return;
    var r = rail();
    if (!r) return;
    if (sortable) { sortable.destroy(); sortable = null; }
    sortable = window.Sortable.create(r, {
      handle: '.crm-widget-builder-drag',
      animation: 180,
      ghostClass: 'sortable-ghost',
      chosenClass: 'sortable-chosen',
      dragClass: 'sortable-drag',
      easing: 'cubic-bezier(1, 0, 0, 1)',
      onEnd: function () { refreshCatalog(); }
    });
  }

  function refreshCatalog() {
    var list = document.querySelector('[data-task-sidebar-catalog-list]');
    if (!list) return;
    if (!Array.isArray(config.catalog)) {
      list.innerHTML = '<div class="text-muted">' + esc(t('js.pab.catalog_load_error', 'Catalog unavailable.')) + '</div>';
      return;
    }
    var r = rail();
    var activeKeys = {};
    if (r) {
      r.querySelectorAll('[data-task-sidebar-block]').forEach(function (node) {
        activeKeys[node.getAttribute('data-task-sidebar-block')] = true;
      });
    }
    list.innerHTML = config.catalog.map(function (entry) {
      var key = String(entry && entry.key ? entry.key : '');
      var title = blockTitle(key);
      var desc = t(entry && entry.description_key ? entry.description_key : '', entry && entry.description ? entry.description : '');
      var added = !!activeKeys[key];
      return '<div class="crm-dashboard-catalog-item">'
        + '<span class="crm-dashboard-catalog-icon" aria-hidden="true"><i class="fa-solid fa-puzzle-piece"></i></span>'
        + '<div class="crm-dashboard-catalog-body">'
        + '<div class="crm-dashboard-catalog-title">' + esc(title) + '</div>'
        + (desc ? '<p class="crm-dashboard-catalog-desc">' + esc(desc) + '</p>' : '')
        + '</div>'
        + '<button type="button" class="btn btn-sm ' + (added ? 'crm-btn-secondary' : 'crm-btn-primary') + ' crm-dashboard-catalog-add" data-sidebar-catalog-key="' + esc(key) + '"' + (added ? ' disabled' : '') + '>'
        + (added ? esc(t('js.pab.builder_added', 'Added')) : '<i class="fa-solid fa-plus" aria-hidden="true"></i> ' + esc(t('js.pab.builder_add', 'Add')))
        + '</button>'
        + '</div>';
    }).join('');

    list.querySelectorAll('[data-sidebar-catalog-key]').forEach(function (btn) {
      if (btn.dataset.bound === '1') return;
      btn.addEventListener('click', function () {
        addBlock(btn.getAttribute('data-sidebar-catalog-key'));
      });
      btn.dataset.bound = '1';
    });
  }

  async function save() {
    var saveBtn = document.querySelector('[data-task-sidebar-builder-save]');
    if (saveBtn && saveBtn.dataset.loading === '1') return;
    var r = rail();
    var active = [];
    if (r) {
      r.querySelectorAll('[data-task-sidebar-block]').forEach(function (node) {
        active.push(node.getAttribute('data-task-sidebar-block'));
      });
    }
    if (saveBtn) { saveBtn.dataset.loading = '1'; saveBtn.disabled = true; }
    try {
      var envelope = await req('api/v1/tasks/sidebar', { method: 'PUT', body: { active: active } });
      var data = envelope && envelope.data ? envelope.data : {};
      config = {
        catalog: Array.isArray(data.catalog) ? data.catalog : [],
        active: Array.isArray(data.active) ? data.active : []
      };
      notify(t('task_detail.sidebar_saved', 'Column updated'), 'success');
      exitEditMode();
      applyVisibility(config.active);
    } catch (error) {
      var api = getApi();
      var message;
      if (api && typeof api.formatErrorMessage === 'function' && typeof api.normalizeError === 'function') {
        message = api.formatErrorMessage(api.normalizeError(error, t('task_detail.sidebar_save_error', 'Failed to save column')), {});
      } else {
        message = t('task_detail.sidebar_save_error', 'Failed to save column');
      }
      notify(message, 'error');
    } finally {
      if (saveBtn) { saveBtn.dataset.loading = '0'; saveBtn.disabled = false; }
    }
  }

  function bind() {
    var toggle = document.querySelector('[data-task-sidebar-edit-toggle]');
    if (toggle && toggle.dataset.bound !== '1') {
      toggle.addEventListener('click', function () {
        if (window.CRM.__taskSidebarEditMode === EDIT_MODE) { exitEditMode(); return; }
        enterEditMode();
        refreshCatalog();
      });
      toggle.dataset.bound = '1';
    }

    var addBtn = document.querySelector('[data-task-sidebar-builder-add]');
    if (addBtn && addBtn.dataset.bound !== '1') {
      addBtn.addEventListener('click', function () {
        refreshCatalog();
        var offcanvasEl = document.getElementById('taskSidebarCatalogOffcanvas');
        if (offcanvasEl && window.bootstrap && typeof window.bootstrap.Offcanvas === 'function') {
          window.bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl).show();
        }
      });
      addBtn.dataset.bound = '1';
    }

    var resetBtn = document.querySelector('[data-task-sidebar-builder-reset]');
    if (resetBtn && resetBtn.dataset.bound !== '1') {
      resetBtn.addEventListener('click', function () {
        exitEditMode();
        applyVisibility(config.active);
        notify(t('js.pab.widgets_cancel', 'Changes discarded'), 'info');
      });
      resetBtn.dataset.bound = '1';
    }

    var saveBtn = document.querySelector('[data-task-sidebar-builder-save]');
    if (saveBtn && saveBtn.dataset.bound !== '1') {
      saveBtn.addEventListener('click', save);
      saveBtn.dataset.bound = '1';
    }

    var offcanvasEl = document.getElementById('taskSidebarCatalogOffcanvas');
    if (offcanvasEl && offcanvasEl.dataset.bound !== '1') {
      offcanvasEl.addEventListener('show.bs.offcanvas', refreshCatalog);
      offcanvasEl.dataset.bound = '1';
    }
  }

  async function init() {
    if (started) return;
    started = true;
    try {
      var envelope = await req('api/v1/tasks/sidebar', { method: 'GET' });
      var data = envelope && envelope.data ? envelope.data : {};
      config = {
        catalog: Array.isArray(data.catalog) ? data.catalog : [],
        active: Array.isArray(data.active) ? data.active : []
      };
      applyVisibility(config.active);
    } catch (e) {
      applyVisibility([]);
    }
    bind();
  }

  function waitForApi(cb, n) {
    if (getApi()) { cb(); return; }
    if ((n || 0) > 100) return;
    setTimeout(function () { waitForApi(cb, (n || 0) + 1); }, 100);
  }

  function start() {
    if (!getApi()) { waitForApi(start, 0); return; }
    init();
  }

  return { init: start, applyVisibility: applyVisibility };
})();

(function boot() {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      if (document.body && document.body.getAttribute('data-page') === 'tasks') {
        window.CRM.taskSidebarBlocks.init();
      }
    });
  } else {
    if (document.body && document.body.getAttribute('data-page') === 'tasks') {
      window.CRM.taskSidebarBlocks.init();
    }
  }
})();
