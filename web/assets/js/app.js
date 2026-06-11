(function () {
  function bindLoginFallback() {
    var form = document.getElementById('loginForm');
    if (!form || form.dataset.crmLoginBound === '1') {
      return;
    }

    var errorNode = document.getElementById('loginError');
    var showError = function (message) {
      if (!errorNode) return;
      errorNode.classList.remove('d-none');
      errorNode.textContent = String(message || 'Ошибка входа');
    };
    var hideError = function () {
      if (!errorNode) return;
      errorNode.classList.add('d-none');
      errorNode.textContent = '';
    };
    var withQuery = function (route) {
      return 'index.php?route=' + encodeURIComponent(route || 'dashboard');
    };

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      if (!window.CRM || !window.CRM.api || typeof window.CRM.api.login !== 'function') {
        showError('Модуль авторизации недоступен. Обновите страницу (Ctrl+F5).');
        return;
      }
      hideError();
      var loginInput = form.querySelector('[name="login"]') || form.querySelector('[name="email"]');
      var passInput = form.querySelector('[name="password"]');
      var localeInput = form.querySelector('[name="locale"]');
      var login = loginInput ? String(loginInput.value || '').trim() : '';
      var password = passInput ? String(passInput.value || '').trim() : '';
      var locale = localeInput ? String(localeInput.value || '').trim().toLowerCase() : '';
      if (!login || !password) {
        showError('Введите логин и пароль.');
        return;
      }
      try {
        await window.CRM.api.login(login, password, locale);
        await window.CRM.api.me();
        var query = new URLSearchParams(window.location.search || '');
        var returnRoute = query.get('return_route') || query.get('redirect') || 'dashboard';
        window.location.href = withQuery(returnRoute);
      } catch (error) {
        var normalized = window.CRM.api && typeof window.CRM.api.normalizeError === 'function'
          ? window.CRM.api.normalizeError(error, 'Ошибка входа')
          : { message: 'Ошибка входа', fieldErrors: {} };
        var message = window.CRM.api && typeof window.CRM.api.formatErrorMessage === 'function'
          ? window.CRM.api.formatErrorMessage(normalized, { withRequestId: true })
          : String(normalized.message || 'Ошибка входа');
        showError(message);
      }
    });
    form.dataset.crmLoginBound = '1';
  }

  function startRealtimeAfterPageData() {
    var started = false;
    var startTimer = null;
    var loadingPromise = null;

    function loadRealtimeScript() {
      if (window.CRM.notificationsRealtime && typeof window.CRM.notificationsRealtime.start === 'function') {
        return Promise.resolve();
      }
      if (loadingPromise) {
        return loadingPromise;
      }

      loadingPromise = new Promise(function (resolve, reject) {
        var existing = document.querySelector('script[data-crm-script="notifications-realtime"]');
        if (existing) {
          existing.addEventListener('load', function () { resolve(); }, { once: true });
          existing.addEventListener('error', reject, { once: true });
          return;
        }

        var script = document.createElement('script');
        var version = window.CRM && window.CRM.config ? String(window.CRM.config.assetsVersion || '') : '';
        script.src = 'assets/js/notifications-realtime.js' + (version ? '?v=' + encodeURIComponent(version) : '');
        script.defer = true;
        script.dataset.crmScript = 'notifications-realtime';
        script.onload = function () { resolve(); };
        script.onerror = reject;
        document.head.appendChild(script);
      }).catch(function (error) {
        console.error('notificationsRealtime load failed', error);
      });

      return loadingPromise;
    }

    function startOnce() {
      if (started) return;
      if (startTimer) {
        window.clearTimeout(startTimer);
        startTimer = null;
      }
      started = true;
      loadRealtimeScript().then(function () {
        if (window.CRM.notificationsRealtime && typeof window.CRM.notificationsRealtime.start === 'function') {
          window.CRM.notificationsRealtime.start();
        }
      });
    }
    function scheduleStart(delayMs) {
      if (started || startTimer) return;
      startTimer = window.setTimeout(startOnce, Math.max(0, Number(delayMs) || 0));
    }

    document.addEventListener('crm:page-data-ready', function () {
      scheduleStart(5000);
    }, { once: true });
    window.setTimeout(function () {
      scheduleStart(0);
    }, 15000);
    window.addEventListener('pagehide', function () {
      if (startTimer) {
        window.clearTimeout(startTimer);
        startTimer = null;
      }
      if (window.CRM.notificationsRealtime && typeof window.CRM.notificationsRealtime.stop === 'function') {
        window.CRM.notificationsRealtime.stop();
      }
    });
  }

  function startPushAfterPageData() {
    if (!window.CRM.notificationsPush || typeof window.CRM.notificationsPush.start !== 'function') {
      return;
    }

    var started = false;
    var startTimer = null;
    function startOnce() {
      if (started) return;
      if (startTimer) {
        window.clearTimeout(startTimer);
        startTimer = null;
      }
      started = true;
      window.CRM.notificationsPush.start();
    }
    function scheduleStart(delayMs) {
      if (started || startTimer) return;
      startTimer = window.setTimeout(startOnce, Math.max(0, Number(delayMs) || 0));
    }

    document.addEventListener('crm:page-data-ready', function () {
      scheduleStart(2500);
    }, { once: true });
    window.setTimeout(function () {
      scheduleStart(0);
    }, 12000);
  }

  function enhanceAccessibility(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var autoId = 0;

    scope.querySelectorAll('input, select, textarea').forEach(function (control) {
      if (control.type === 'hidden') return;
      var escapedId = control.id && window.CSS && typeof window.CSS.escape === 'function'
        ? window.CSS.escape(control.id)
        : String(control.id || '').replace(/"/g, '\\"');
      var hasName = control.id && document.querySelector('label[for="' + escapedId + '"]');
      if (!hasName && !control.getAttribute('aria-label')) {
        var wrapper = control.closest('.mb-1, .mb-2, .mb-3, .col, [class*="col-"], .form-check, .input-group, td, th');
        var label = wrapper ? wrapper.querySelector('label.form-label, label.form-check-label, .form-label') : null;
        if (label) {
          if (!control.id) {
            autoId += 1;
            control.id = 'crmAutoField' + Date.now().toString(36) + '_' + autoId;
          }
          if (label.tagName === 'LABEL') {
            label.setAttribute('for', control.id);
          } else if (!control.getAttribute('aria-label')) {
            control.setAttribute('aria-label', label.textContent.trim());
          }
          hasName = true;
        }
      }

      if (!hasName && !control.getAttribute('aria-label')) {
        var placeholder = String(control.getAttribute('placeholder') || '').trim();
        var name = String(control.getAttribute('name') || '').trim();
        if (placeholder || name) {
          control.setAttribute('aria-label', placeholder || name);
        }
      }
    });

    scope.querySelectorAll('table').forEach(function (table) {
      if (!table.querySelector('th[scope]')) {
        table.querySelectorAll('thead th').forEach(function (th) {
          th.setAttribute('scope', 'col');
        });
      }
    });
  }

  async function init() {
    try {
      if (window.CRM.tabLeader) window.CRM.tabLeader.init();
    } catch (error) {
      console.error('tabLeader init failed', error);
    }
    try {
      if (window.CRM.i18n) window.CRM.i18n.init();
    } catch (error) {
      console.error('i18n init failed', error);
    }
    try {
      if (window.CRM.ui) {
        window.CRM.ui.initBootstrapUi();
        window.CRM.ui.initToasts();
        window.CRM.ui.initStateSwitchers();
        window.CRM.ui.initStatusColorPickers();
      }
    } catch (error) {
      console.error('ui init failed', error);
    }
    try {
      if (window.CRM.modals) {
        window.CRM.modals.bindActions();
        window.CRM.modals.initEscapeForCustom();
      }
    } catch (error) {
      console.error('modals init failed', error);
    }
    try { if (window.CRM.drawers) window.CRM.drawers.init(); } catch (error) { console.error('drawers init failed', error); }
    try { if (window.CRM.tabs) window.CRM.tabs.init(); } catch (error) { console.error('tabs init failed', error); }
    try { if (window.CRM.filters) window.CRM.filters.init(); } catch (error) { console.error('filters init failed', error); }
    try { if (window.CRM.tables) window.CRM.tables.init(); } catch (error) { console.error('tables init failed', error); }
    try { if (window.CRM.notifications) window.CRM.notifications.init(); } catch (error) { console.error('notifications init failed', error); }
    try { if (window.CRM.richtext) window.CRM.richtext.init(); } catch (error) { console.error('richtext init failed', error); }

    try { if (window.CRM.br1) window.CRM.br1.init(); } catch (error) { console.error('br1 init failed', error); }
    try { if (window.CRM.navigation) await window.CRM.navigation.init(); } catch (error) { console.error('navigation init failed', error); }
    try { if (window.CRM.pageApiBindings) window.CRM.pageApiBindings.init(); } catch (error) { console.error('pageApiBindings init failed', error); }
    try { startPushAfterPageData(); } catch (error) { console.error('notificationsPush bootstrap failed', error); }
    try { startRealtimeAfterPageData(); } catch (error) { console.error('realtime bootstrap failed', error); }
    try { enhanceAccessibility(document); } catch (error) { console.error('a11y enhance failed', error); }
    document.addEventListener('crm:page-data-ready', function () {
      try { enhanceAccessibility(document); } catch (error) { console.error('a11y enhance failed', error); }
    });

    bindLoginFallback();
  }

  document.addEventListener('DOMContentLoaded', init);
})();
