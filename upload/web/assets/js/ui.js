window.CRM = window.CRM || {};
window.CRM.ui = (function () {
  function initBootstrapUi() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      new bootstrap.Tooltip(el);
    });

    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
      new bootstrap.Popover(el);
    });
  }

  function showNotice(message, type, options) {
    var opts = options || {};
    var toastEl = document.getElementById('toastSuccess');
    if (!toastEl || !window.bootstrap || !window.bootstrap.Toast) return false;
    var kind = type === 'error' ? 'danger' : type === 'warning' ? 'warning' : type === 'info' ? 'info' : 'success';
    toastEl.classList.remove('text-bg-success', 'text-bg-danger', 'text-bg-warning', 'text-bg-info');
    toastEl.classList.add('text-bg-' + kind);
    toastEl.setAttribute('role', kind === 'danger' ? 'alert' : 'status');
    toastEl.setAttribute('aria-live', kind === 'danger' ? 'assertive' : 'polite');
    var body = toastEl.querySelector('.toast-body');
    if (body) body.textContent = String(message || '');
    var instance = window.bootstrap.Toast.getOrCreateInstance(toastEl, {
      autohide: opts.autohide !== false,
      delay: Number(opts.delay || (kind === 'danger' ? 8000 : 5000))
    });
    instance.show();
    return true;
  }

  function setPending(button, pending, pendingLabel) {
    if (!button) return;
    if (pending) {
      if (!button.dataset.crmOriginalLabel) button.dataset.crmOriginalLabel = button.innerHTML;
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.classList.add('is-pending');
      if (pendingLabel) button.textContent = String(pendingLabel);
    } else {
      button.disabled = false;
      button.removeAttribute('aria-busy');
      button.classList.remove('is-pending');
      if (button.dataset.crmOriginalLabel) {
        button.innerHTML = button.dataset.crmOriginalLabel;
        delete button.dataset.crmOriginalLabel;
      }
    }
  }

  function initDirtyForms() {
    document.querySelectorAll('[data-dirty-guard]').forEach(function (root) {
      if (root.dataset.crmDirtyBound === '1') return;
      root.dataset.crmDirtyBound = '1';
      var dirty = false;
      function setDirty(value) {
        dirty = Boolean(value);
        root.dataset.crmDirty = dirty ? '1' : '0';
        root.dispatchEvent(new CustomEvent('crm:dirty-change', { detail: { dirty: dirty } }));
      }
      root.addEventListener('input', function (event) {
        if (event.target && event.target.matches('input, select, textarea, [contenteditable="true"]')) setDirty(true);
      });
      root.addEventListener('change', function (event) {
        if (event.target && event.target.matches('input, select, textarea')) setDirty(true);
      });
      if (root.matches('form')) {
        root.addEventListener('submit', function () { setDirty(false); });
      }
      root.addEventListener('crm:mark-clean', function () { setDirty(false); });
      root.dataset.crmDirty = '0';
      root._crmIsDirty = function () { return dirty; };
    });
    if (window.CRM._dirtyUnloadBound) return;
    window.CRM._dirtyUnloadBound = true;
    window.addEventListener('beforeunload', function (event) {
      var dirtyRoot = Array.from(document.querySelectorAll('[data-dirty-guard]')).some(function (root) {
        return root._crmIsDirty && root._crmIsDirty();
      });
      if (!dirtyRoot) return;
      event.preventDefault();
      event.returnValue = '';
    });
  }

  function initStateSwitchers() {
    document.querySelectorAll('[data-toggle-state]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var target = document.getElementById(btn.dataset.target);
        if (!target) return;
        target.querySelectorAll('[data-state-item]').forEach(function (node) {
          node.classList.add('d-none');
        });
        var active = target.querySelector('[data-state-item="' + btn.dataset.toggleState + '"]');
        if (active) active.classList.remove('d-none');
      });
    });
  }

  function normalizeHex(value) {
    if (!value) return null;
    var hex = value.trim().replace(/^#/, '');
    if (/^[0-9a-fA-F]{3}$/.test(hex)) {
      hex = hex.split('').map(function (ch) { return ch + ch; }).join('');
    }
    if (!/^[0-9a-fA-F]{6}$/.test(hex)) return null;
    return '#' + hex.toUpperCase();
  }

  function getContrastText(hex) {
    var raw = hex.replace('#', '');
    var r = parseInt(raw.slice(0, 2), 16);
    var g = parseInt(raw.slice(2, 4), 16);
    var b = parseInt(raw.slice(4, 6), 16);
    var luma = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
    return luma > 0.62 ? '#1A2740' : '#FFFFFF';
  }

  function updateColorPreview(container, hex) {
    var preview = container.querySelector('[data-status-color-preview]');
    if (!preview) return;
    preview.style.background = hex;
    preview.style.color = getContrastText(hex);
    preview.textContent = hex;
  }

  function initStatusColorPickers() {
    document.querySelectorAll('[data-status-color-picker]').forEach(function (container) {
      var colorInput = container.querySelector('[data-status-color-input]');
      var hexInput = container.querySelector('[data-status-color-hex]');
      if (!colorInput || !hexInput) return;

      function applyHex(raw, syncFromHex) {
        var normalized = normalizeHex(raw);
        if (!normalized) {
          hexInput.classList.add('is-invalid');
          return;
        }
        hexInput.classList.remove('is-invalid');
        hexInput.value = normalized;
        if (syncFromHex) {
          colorInput.value = normalized;
        }
        updateColorPreview(container, normalized);
      }

      colorInput.addEventListener('input', function () {
        applyHex(colorInput.value, false);
      });

      hexInput.addEventListener('input', function () {
        applyHex(hexInput.value, true);
      });

      hexInput.addEventListener('blur', function () {
        applyHex(hexInput.value, true);
      });

      applyHex(colorInput.value || hexInput.value || '#2A67D9', true);
    });
  }

  // CSP M-7: inline event handlers are removed. These document-level
  // delegated listeners replace onclick="this.select()" (select-on-click)
  // and onsubmit="return false" (no-submit forms).
  document.addEventListener('click', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('[data-select-on-click]') : null;
    if (el && typeof el.select === 'function') { el.select(); }
  });

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form && form.hasAttribute && form.hasAttribute('data-no-submit')) {
      e.preventDefault();
    }
  });

  return {
    initBootstrapUi: initBootstrapUi,
    initToasts: function () {
      document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-toast]');
        if (!trigger) return;
        var toastEl = document.getElementById(trigger.dataset.toast);
        if (!toastEl) return;
        bootstrap.Toast.getOrCreateInstance(toastEl).show();
      });
    },
    showNotice: showNotice,
    setPending: setPending,
    initDirtyForms: initDirtyForms,
    initStateSwitchers: initStateSwitchers,
    initStatusColorPickers: initStatusColorPickers
  };
})();
