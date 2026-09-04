(function () {
  var LOGIN_FORM_STATE_KEY = 'crm_login_form_state_v1';

  function saveLoginFormState(form) {
    if (!form) return;
    try {
      if (!window.sessionStorage) return;
      var loginInput = form.querySelector('[name="login"]') || form.querySelector('[name="email"]');
      var passwordInput = form.querySelector('[name="password"]');
      window.sessionStorage.setItem(LOGIN_FORM_STATE_KEY, JSON.stringify({
        login: loginInput ? String(loginInput.value || '') : '',
        password: passwordInput ? String(passwordInput.value || '') : ''
      }));
    } catch (e) {
      // Browser storage may be disabled; the language switch still works.
    }
  }

  function restoreLoginFormState(form) {
    if (!form) return;
    try {
      if (!window.sessionStorage) return;
      var raw = window.sessionStorage.getItem(LOGIN_FORM_STATE_KEY);
      if (!raw) return;
      window.sessionStorage.removeItem(LOGIN_FORM_STATE_KEY);
      var state = JSON.parse(raw);
      var loginInput = form.querySelector('[name="login"]') || form.querySelector('[name="email"]');
      var passwordInput = form.querySelector('[name="password"]');
      if (loginInput && typeof state.login === 'string') loginInput.value = state.login;
      if (passwordInput && typeof state.password === 'string') passwordInput.value = state.password;
    } catch (e) {
      try { window.sessionStorage.removeItem(LOGIN_FORM_STATE_KEY); } catch (ignore) { void ignore; }
    }
  }

  function bindLoginFallback() {
    var form = document.getElementById('loginForm');
    if (!form || form.dataset.crmLoginBound === '1') {
      return;
    }

    var errorNode = document.getElementById('loginError');
    var showError = function (message) {
      if (!errorNode) return;
      errorNode.classList.remove('d-none');
      errorNode.textContent = String(message || window.CRM.i18n.t('js.app.login_error', 'Login error'));
    };
    var hideError = function () {
      if (!errorNode) return;
      errorNode.classList.add('d-none');
      errorNode.textContent = '';
    };
    var withQuery = function (route) {
      return 'index.php?route=' + encodeURIComponent(route || 'dashboard');
    };
    var localeInput = form.querySelector('[name="locale"]');
    restoreLoginFormState(form);
    if (localeInput) {
      var queryLocale = String(new URLSearchParams(window.location.search || '').get('lang') || '').trim().toLowerCase().replace('_', '-');
      var preferredLocale = queryLocale;
      if (!preferredLocale && window.CRM && window.CRM.api && typeof window.CRM.api.getPreferredLocale === 'function') {
        preferredLocale = String(window.CRM.api.getPreferredLocale() || '').trim().toLowerCase().replace('_', '-');
      }
      if (preferredLocale && Array.from(localeInput.options).some(function (option) {
        return String(option.value || '').toLowerCase() === preferredLocale;
      })) {
        localeInput.value = preferredLocale;
      }
    }

    if (localeInput && localeInput.dataset.crmLocaleSwitchBound !== '1') {
      localeInput.addEventListener('change', function () {
        var nextLocale = String(localeInput.value || '').trim().toLowerCase();
        var currentLocale = String((window.CRM && window.CRM.locale) || document.documentElement.lang || '').trim().toLowerCase();
        if (!nextLocale || nextLocale === currentLocale) {
          return;
        }
        saveLoginFormState(form);
        var url = new URL(window.location.href);
        url.searchParams.set('route', 'login');
        url.searchParams.set('lang', nextLocale);
        window.location.href = url.toString();
      });
      localeInput.dataset.crmLocaleSwitchBound = '1';
    }

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      if (!window.CRM || !window.CRM.api || typeof window.CRM.api.login !== 'function') {
        showError(window.CRM.i18n.t('js.app.auth_unavailable', 'Auth module unavailable. Refresh the page (Ctrl+F5).'));
        return;
      }
      hideError();
      var loginInput = form.querySelector('[name="login"]') || form.querySelector('[name="email"]');
      var passInput = form.querySelector('[name="password"]');
      var login = loginInput ? String(loginInput.value || '').trim() : '';
      var password = passInput ? String(passInput.value || '').trim() : '';
      var locale = localeInput ? String(localeInput.value || '').trim().toLowerCase() : '';
      if (!login || !password) {
        showError(window.CRM.i18n.t('js.app.enter_credentials', 'Enter login and password.'));
        return;
      }
      try {
        await window.CRM.api.login(login, password, locale);
        await window.CRM.api.me();
        if (typeof window.CRM.api.setPreferredLocale === 'function') {
          window.CRM.api.setPreferredLocale(locale);
        }
        // Pull the per-user color theme into localStorage so the next page
        // load renders with it immediately (no flash). Wait for the sync
        // before redirecting: the navigation would otherwise abort this fetch
        // on a fresh browser (no cached theme yet), leaving the user on the
        // wrong theme until they open the profile page. Capped at 1500ms so a
        // slow network can never delay login noticeably.
        if (window.CRM.theme) {
          var themeSync = window.CRM.api.request('api/v1/profile/preferences', { silent: true })
            .then(function (env) {
              var prefs = env && env.data && env.data.preferences ? env.data.preferences : {};
              window.CRM.theme.syncFromPreferences(prefs);
            })
            .catch(function () {});
          await Promise.race([
            themeSync,
            new Promise(function (resolve) { window.setTimeout(resolve, 1500); })
          ]);
        }
        var meUser = window.CRM.api.getUser ? window.CRM.api.getUser() : null;
        var isExternalUser = Boolean(meUser && meUser.is_external);
        var query = new URLSearchParams(window.location.search || '');
        var returnRoute = query.get('return_route') || query.get('redirect');
        // External guest (client portal) users have no dashboard/admin access:
        // web/index.php's $externalAllowedRoutes is the only page shell list
        // they may land on. The ?redirect= param (added when an anonymous
        // visitor hit a protected page) may point at internal-only routes, so
        // validate it and fall back to the projects list otherwise.
        if (isExternalUser) {
          var externalAllowedRoutes = ['projects', 'project-detail', 'tasks', 'task-detail', 'notifications', 'profile', 'my-earnings'];
          if (!returnRoute || externalAllowedRoutes.indexOf(returnRoute) === -1) {
            returnRoute = '';
          }
        }
        window.location.href = withQuery(returnRoute || (isExternalUser ? 'projects' : 'dashboard'));
      } catch (error) {
        try { window.sessionStorage.removeItem(LOGIN_FORM_STATE_KEY); } catch (ignore) { void ignore; }
        var normalized = window.CRM.api && typeof window.CRM.api.normalizeError === 'function'
          ? window.CRM.api.normalizeError(error, window.CRM.i18n.t('js.app.login_error', 'Login error'))
          : { message: window.CRM.i18n.t('js.app.login_error', 'Login error'), fieldErrors: {} };
        var message = window.CRM.api && typeof window.CRM.api.formatErrorMessage === 'function'
          ? window.CRM.api.formatErrorMessage(normalized, { withRequestId: true })
          : String(normalized.message || window.CRM.i18n.t('js.app.login_error', 'Login error'));
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
        // Script load failed — realtime notifications unavailable
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
    var hasPageApiBindings = false;
    try {
      if (window.CRM.tabLeader) window.CRM.tabLeader.init();
    } catch (error) {
      /* tabLeader init failed */
    }
    try {
      if (window.CRM.i18n) window.CRM.i18n.init();
    } catch (error) {
      /* i18n init failed */
    }
    try {
      if (window.CRM.ui) {
        window.CRM.ui.initBootstrapUi();
        window.CRM.ui.initToasts();
        window.CRM.ui.initDirtyForms();
        window.CRM.ui.initStateSwitchers();
        window.CRM.ui.initStatusColorPickers();
      }
    } catch (error) {
      /* ui init failed */
    }
    try {
      if (window.CRM.modals) {
        window.CRM.modals.bindActions();
        window.CRM.modals.initEscapeForCustom();
      }
    } catch (error) {
      /* modals init failed */
    }
    /* Re-run modal-bound init functions after injectGlobalOverlays() inserted
       the modal HTML. These are safe to call multiple times (idempotent). */
    try {
      if (typeof window.CRM._initTaskCreateFlow === 'function') window.CRM._initTaskCreateFlow();
      if (typeof window.CRM._initProjectCreateFlow === 'function') window.CRM._initProjectCreateFlow();
      if (typeof window.CRM._enhanceClientSelects === 'function') window.CRM._enhanceClientSelects();
      if (typeof window.CRM._initQuickClientCreate === 'function') window.CRM._initQuickClientCreate();
      if (typeof window.CRM._initQuickProjectCreate === 'function') window.CRM._initQuickProjectCreate();
    } catch (error) {
      /* late-bound modal init failed */
    }
    try { if (window.CRM.drawers) window.CRM.drawers.init(); } catch (error) { /* drawer init failed */ }
    try { if (window.CRM.tabs) window.CRM.tabs.init(); } catch (error) { /* tab init failed */ }
    try { if (window.CRM.filters) window.CRM.filters.init(); } catch (error) { /* filter init failed */ }
    try { if (window.CRM.tables) window.CRM.tables.init(); } catch (error) { /* table init failed */ }
    /* TASK-10: Fix aria-hidden accessibility warning. Move focus away from
       elements inside modals before Bootstrap sets aria-hidden='true'. */
    document.addEventListener('hide.bs.modal', function(e) {
      var modal = e.target;
      if (modal) {
        var focused = modal.querySelector(':focus');
        if (focused && modal.contains(focused)) {
          focused.blur();
        }
      }
    });
    try { if (window.CRM.notifications) window.CRM.notifications.init(); } catch (error) { /* notification init failed */ }
    try { if (window.CRM.richtext) window.CRM.richtext.init(); } catch (error) { /* richtext init failed */ }

    try { if (window.CRM.br1) window.CRM.br1.init(); } catch (error) { /* br1 init failed */ }
    try { if (window.CRM.navigation) await window.CRM.navigation.init(); } catch (error) { /* navigation init failed */ }
    try {
      if (window.CRM.pageApiBindings) {
        hasPageApiBindings = true;
        window.CRM.pageApiBindings.init();
      }
    } catch (error) { /* pageApiBindings init failed */ }
    try { startPushAfterPageData(); } catch (error) { /* notificationsPush bootstrap failed */ }
    try { startRealtimeAfterPageData(); } catch (error) { /* realtime bootstrap failed */ }
    if (!hasPageApiBindings) {
      window.setTimeout(function () {
        try {
          document.dispatchEvent(new CustomEvent('crm:page-data-ready', {
            detail: { route: new URLSearchParams(window.location.search || '').get('route') || '' }
          }));
        } catch (e) {
          void e;
        }
      }, 0);
    }
    try { enhanceAccessibility(document); } catch (error) { /* a11y enhance failed */ }
    document.addEventListener('crm:page-data-ready', function () {
      try { enhanceAccessibility(document); } catch (error) { /* a11y enhance failed */ }
    });

    bindLoginFallback();
  }

  document.addEventListener('DOMContentLoaded', init);
})();
