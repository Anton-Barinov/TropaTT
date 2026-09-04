window.CRM = window.CRM || {};
window.CRM.filters = (function () {
  var mobileQuery = '(max-width: 767.98px)';
  var roots = [];
  var resizeBound = false;

  function text(value) {
    return String(value || '').trim();
  }

  function isMobile() {
    return window.matchMedia ? window.matchMedia(mobileQuery).matches : window.innerWidth < 768;
  }

  function cssEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(value);
    return String(value || '').replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  }

  function getLabel(root) {
    return root.getAttribute('aria-label') || window.CRM.i18n.t('common.filters', 'Filters');
  }
  function createMobileChrome(root, index) {
    if (root.dataset.crmMobileFilterReady === '1') return;
    var id = root.id || ('crmFilterPanel' + index);
    root.id = id;
    root.dataset.crmMobileFilterReady = '1';
    var placeholder = document.createComment('crm-filter-placeholder');
    root.parentNode.insertBefore(placeholder, root);
    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'btn crm-btn-secondary crm-mobile-filter-toggle d-md-none';
    trigger.setAttribute('data-open-drawer', id + 'Offcanvas');
    trigger.setAttribute('aria-controls', id + 'Offcanvas');
    trigger.innerHTML = '<span class="crm-mobile-filter-toggle-label">' + window.CRM.i18n.t('common.filters', 'Filters') + '</span><span class="crm-mobile-filter-count d-none" aria-hidden="true"></span>';
    trigger.addEventListener('click', function () {
      if (!window.bootstrap || !window.bootstrap.Offcanvas) return;
      window.bootstrap.Offcanvas.getOrCreateInstance(drawer).show();
    });
    placeholder.parentNode.insertBefore(trigger, placeholder.nextSibling);

    var chips = document.createElement('div');
    chips.className = 'crm-active-filter-chips d-md-none';
    chips.setAttribute('aria-live', 'polite');
    placeholder.parentNode.insertBefore(chips, trigger.nextSibling);

    var drawer = document.createElement('div');
    drawer.className = 'offcanvas offcanvas-end crm-filter-offcanvas';
    drawer.id = id + 'Offcanvas';
    drawer.tabIndex = -1;
    drawer.setAttribute('aria-labelledby', id + 'OffcanvasTitle');
    drawer.innerHTML = '<div class="offcanvas-header"><h5 class="offcanvas-title" id="' + id + 'OffcanvasTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="' + window.CRM.i18n.t('common.close_aria', 'Close') + '"></button></div><div class="offcanvas-body"></div>';
    drawer.querySelector('h5').textContent = getLabel(root);
    document.body.appendChild(drawer);

    var entry = { root: root, placeholder: placeholder, drawer: drawer, trigger: trigger, chips: chips };
    roots.push(entry);
    root.querySelectorAll('input, select, textarea, [data-kanban-due]').forEach(function (field) {
      field.addEventListener('input', function () { update(entry); });
      field.addEventListener('change', function () { update(entry); });
      field.addEventListener('click', function () { window.setTimeout(function () { update(entry); }, 0); });
    });
    update(entry);
  }

  function fieldIsActive(field) {
    if (field.matches('[data-kanban-due]')) return field.classList.contains('is-active');
    if (field.type === 'checkbox' || field.type === 'radio') return field.checked;
    return text(field.value) !== '';
  }

  function update(entry) {
    var active = [];
    entry.root.querySelectorAll('input, select, textarea, [data-kanban-due]').forEach(function (field) {
      if (!fieldIsActive(field)) return;
      var label = '';
      if (field.matches('[data-kanban-due]')) label = text(field.textContent);
      else if (field.id) {
        var labelEl = document.querySelector('label[for="' + cssEscape(field.id) + '"]');
        label = labelEl ? text(labelEl.textContent) : '';
      }
      if (!label && field.tagName === 'SELECT' && field.selectedOptions[0]) label = text(field.selectedOptions[0].textContent);
      if (!label) label = text(field.value);
      if (label) active.push(label);
    });
    var count = entry.trigger.querySelector('.crm-mobile-filter-count');
    if (count) {
      count.textContent = String(active.length);
      count.classList.toggle('d-none', active.length === 0);
      count.setAttribute('aria-hidden', active.length ? 'false' : 'true');
    }
    entry.chips.innerHTML = active.map(function (label) { return '<span class="crm-filter-chip">' + escapeHtml(label) + '</span>'; }).join('');
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
    });
  }

  function syncPlacement() {
    roots.forEach(function (entry) {
      var body = entry.drawer.querySelector('.offcanvas-body');
      if (isMobile()) {
        if (entry.root.parentNode !== body) body.appendChild(entry.root);
      } else if (entry.root.parentNode !== entry.placeholder.parentNode) {
        entry.placeholder.parentNode.insertBefore(entry.root, entry.placeholder.nextSibling);
        var instance = window.bootstrap && window.bootstrap.Offcanvas ? window.bootstrap.Offcanvas.getInstance(entry.drawer) : null;
        if (instance) instance.hide();
      }
      update(entry);
    });
  }

  function init() {
    document.querySelectorAll('[data-filter-reset]').forEach(function (btn) {
      if (btn.dataset.crmFilterResetBound === '1') return;
      btn.dataset.crmFilterResetBound = '1';
      btn.addEventListener('click', function () {
        var scope = btn.closest('.crm-kanban-filters, .crm-filters-card, form') || document;
        scope.querySelectorAll('input, select, textarea').forEach(function (field) {
          if (field.type === 'checkbox' || field.type === 'radio') field.checked = false;
          else field.value = '';
        });
        scope.querySelectorAll('[data-kanban-due]').forEach(function (due) { due.classList.remove('is-active'); });
        scope.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });

    document.querySelectorAll('.crm-tasks-page .crm-kanban-filters, .crm-kanban-page .crm-kanban-filters, .crm-projects-page .crm-kanban-filters').forEach(function (root, index) {
      createMobileChrome(root, index + 1);
    });
    syncPlacement();
    if (!resizeBound) {
      resizeBound = true;
      window.addEventListener('resize', syncPlacement);
    }
  }

  return { init: init };
})();
