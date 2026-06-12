window.CRM = window.CRM || {};
window.CRM.api = (function () {
  // Legacy keys are preserved only for backward compatibility with external scripts.
  // Current auth model relies on same-origin cookie session + in-memory user/csrf state.
  var LEGACY_TOKEN_KEY = 'crm_api_access_token_v1';
  var LEGACY_USER_KEY = 'crm_api_user_v1';
  var LOCALE_KEY = 'crm_api_locale_v1';
  var IMPERSONATION_TOKEN_KEY = 'crm_impersonation_access_token_v1';
  var IMPERSONATION_ORIGINAL_TOKEN_KEY = 'crm_impersonation_original_token_v1';
  var IMPERSONATION_AUDIT_KEY = 'crm_impersonation_audit_public_id_v1';
  var IMPERSONATION_TARGET_KEY = 'crm_impersonation_target_label_v1';
  var inFlightGetRequests = {};
  var referenceGetCache = {};
  var REFERENCE_GET_CACHE_PREFIX = 'crm_ref_get_cache_v1:';
  var ROUTE_PERMISSIONS = {
    dashboard: [],
    index: [],
    'my-day': ['task.manage'],
    'my-week': ['task.manage'],
    tasks: ['task.manage'],
    'task-detail': ['task.manage'],
    kanban: ['task.manage'],
    calendar: ['task.manage'],
    gantt: ['project.manage'],
    analytics: ['task.manage'],
    projects: ['project.manage'],
    'project-detail': ['project.manage'],
    teams: [],
    clients: ['client.manage'],
    companies: ['company.manage'],
    contacts: ['contact.manage'],
    departments: ['department.manage'],
    'client-detail': ['client.manage'],
    'client-cabinet': ['client.manage'],
    admin: ['logs.view', 'user.view', 'user.manage', 'role.view', 'role.manage', 'api_client.view', 'api_client.manage', 'webhook.manage', 'settings.manage', 'ai.admin'],
    'admin-users': ['user.view', 'user.manage'],
    'admin-roles': ['role.view', 'role.manage'],
    'admin-statuses': ['task.manage'],
    'admin-logs': ['logs.view'],
    'admin-api-clients': ['api_client.view', 'api_client.manage', 'webhook.manage'],
    'admin-settings': ['settings.manage'],
    'admin-jobs': ['import.manage', 'export.manage', 'ai.admin', 'ai.view_cron_results', 'ai.manage_cron_jobs'],
    'admin-ai': ['ai.admin']
  };

  function normalizeLocaleCode(locale) {
    var value = String(locale || '').trim().toLowerCase().replace('_', '-');
    if (value === 'ru') return 'ru-ru';
    if (value === 'en') return 'en-gb';
    if (value === 'zh' || value === 'cn' || value === 'zh-hans') return 'zh-cn';
    if (value === 'es') return 'es-es';
    if (value === 'pt') return 'pt-br';
    if (value === 'de') return 'de-de';
    if (value === 'fr') return 'fr-fr';
    return value;
  }

  function getBaseUrl() {
    var preset = window.CRM && window.CRM.config && window.CRM.config.apiBaseUrl;
    if (preset) return preset;
    return window.location.protocol + '//' + window.location.host + '/api/index.php';
  }

  function buildUrl(route, query) {
    var url = new URL(getBaseUrl(), window.location.origin);
    url.searchParams.set('route', route);

    if (query && typeof query === 'object') {
      Object.keys(query).forEach(function (key) {
        var value = query[key];
        if (value === undefined || value === null || value === '') return;
        url.searchParams.set(key, String(value));
      });
    }

    return url.toString();
  }

  function buildWebUrl(route, query) {
    var params = new URLSearchParams();
    params.set('route', String(route || 'dashboard'));

    if (query && typeof query === 'object') {
      Object.keys(query).forEach(function (key) {
        var value = query[key];
        if (value === undefined || value === null || value === '') return;
        params.set(String(key), String(value));
      });
    }

    return 'index.php?' + params.toString();
  }

  function currentRoute(fallback) {
    var fallbackRoute = String(fallback || 'dashboard').trim() || 'dashboard';
    var queryRoute = new URLSearchParams(window.location.search).get('route');
    if (queryRoute) {
      return String(queryRoute || '').trim() || fallbackRoute;
    }

    var path = String(window.location.pathname || '').replace(/\/+$/, '');
    var segment = path.split('/').filter(Boolean).pop() || '';
    if (!segment || segment === 'web' || segment === 'index.php') {
      return fallbackRoute;
    }

    return decodeURIComponent(segment);
  }

  function setToken(token) {
    window.CRM.__memoryAccessToken = token ? String(token) : '';
  }

  function getToken() {
    return String(window.CRM.__memoryAccessToken || '');
  }

  function setCsrfToken(token) {
    window.CRM.__memoryCsrfToken = token ? String(token) : '';
  }

  function getCsrfToken() {
    return String(window.CRM.__memoryCsrfToken || '');
  }

  function setUser(user) {
    window.CRM.__memoryUser = user || null;
  }

  function getUser() {
    return window.CRM.__memoryUser || null;
  }

  function getPermissionCodes() {
    var user = getUser();
    if (!user || !Array.isArray(user.permission_codes)) return [];
    return user.permission_codes.map(function (code) {
      return String(code || '').trim();
    }).filter(Boolean);
  }

  function isRootUser() {
    var user = getUser();
    return Boolean(user && user.is_root);
  }

  function hasPermission(code) {
    var permission = String(code || '').trim();
    if (!permission) return true;
    if (isRootUser()) return true;
    var permissions = getPermissionCodes();
    return permissions.indexOf('*') >= 0 || permissions.indexOf(permission) >= 0;
  }

  function hasAnyPermission(codes) {
    if (!Array.isArray(codes) || codes.length === 0) return true;
    return codes.some(function (code) {
      return hasPermission(code);
    });
  }

  function hasRole(roleCode) {
    var target = String(roleCode || '').trim().toLowerCase();
    if (!target) return false;
    var user = getUser();
    var roles = user && Array.isArray(user.roles) ? user.roles : [];
    return roles.some(function (role) {
      return String(role || '').trim().toLowerCase() === target;
    });
  }

  function canAccessRoute(route) {
    var routeKey = String(route || '').trim();
    if (!routeKey) return true;
    if (!getUser()) return true;
    if (routeKey === 'admin-ai') {
      return isRootUser() || hasRole('admin') || hasPermission('ai.admin');
    }
    return hasAnyPermission(ROUTE_PERMISSIONS[routeKey] || []);
  }

  function clearAuth() {
    setToken('');
    setCsrfToken('');
    setUser(null);
  }

  function storageGet(key, fallback) {
    try {
      if (!window.localStorage) return fallback;
      var value = window.localStorage.getItem(key);
      return value === null ? fallback : value;
    } catch (e) {
      return fallback;
    }
  }

  function storageSet(key, value) {
    try {
      if (window.localStorage) {
        window.localStorage.setItem(key, value);
      }
    } catch (e) {
      void e;
    }
  }

  function storageRemove(key) {
    try {
      if (window.localStorage) {
        window.localStorage.removeItem(key);
      }
    } catch (e) {
      void e;
    }
  }

  function sessionGet(key, fallback) {
    try {
      if (!window.sessionStorage) return fallback;
      var value = window.sessionStorage.getItem(key);
      return value === null ? fallback : value;
    } catch (e) {
      return fallback;
    }
  }

  function sessionSet(key, value) {
    try {
      if (window.sessionStorage) {
        window.sessionStorage.setItem(key, value);
      }
    } catch (e) {
      void e;
    }
  }

  function sessionRemove(key) {
    try {
      if (window.sessionStorage) {
        window.sessionStorage.removeItem(key);
      }
    } catch (e) {
      void e;
    }
  }

  function readCookie(name) {
    var target = String(name || '').trim();
    if (!target || typeof document === 'undefined') return '';
    var cookies = String(document.cookie || '').split(';');
    for (var i = 0; i < cookies.length; i += 1) {
      var part = cookies[i].trim();
      if (part.indexOf(target + '=') === 0) {
        return decodeURIComponent(part.slice(target.length + 1));
      }
    }
    return '';
  }

  function getCookieAuthToken() {
    var configured = window.CRM && window.CRM.config && window.CRM.config.authCookieName
      ? String(window.CRM.config.authCookieName)
      : 'crm_api_session';
    return readCookie(configured);
  }

  function getActiveImpersonation() {
    var token = sessionGet(IMPERSONATION_TOKEN_KEY, '');
    return {
      active: Boolean(token),
      audit_public_id: sessionGet(IMPERSONATION_AUDIT_KEY, ''),
      target_label: sessionGet(IMPERSONATION_TARGET_KEY, '')
    };
  }

  function activateImpersonationSession(data) {
    var payload = data && typeof data === 'object' ? data : {};
    var token = String(payload.impersonation_access_token || '').trim();
    if (!token) return false;

    var originalToken = getToken() || getCookieAuthToken();
    if (originalToken) {
      sessionSet(IMPERSONATION_ORIGINAL_TOKEN_KEY, originalToken);
    }
    sessionSet(IMPERSONATION_TOKEN_KEY, token);

    var audit = payload.audit && typeof payload.audit === 'object' ? payload.audit : {};
    var auditPublicId = String(audit.public_id || '').trim();
    if (auditPublicId) {
      sessionSet(IMPERSONATION_AUDIT_KEY, auditPublicId);
    } else {
      sessionRemove(IMPERSONATION_AUDIT_KEY);
    }

    var target = payload.target_user && typeof payload.target_user === 'object' ? payload.target_user : {};
    var targetLabel = String(target.full_name || target.login || target.public_id || '').trim();
    if (targetLabel) {
      sessionSet(IMPERSONATION_TARGET_KEY, targetLabel);
    } else {
      sessionRemove(IMPERSONATION_TARGET_KEY);
    }

    setToken(token);
    setCsrfToken('');
    if (target.public_id) {
      setUser(target);
    }
    return true;
  }

  function restoreOriginalSessionAfterImpersonation() {
    var originalToken = sessionGet(IMPERSONATION_ORIGINAL_TOKEN_KEY, '');
    sessionRemove(IMPERSONATION_TOKEN_KEY);
    sessionRemove(IMPERSONATION_ORIGINAL_TOKEN_KEY);
    sessionRemove(IMPERSONATION_AUDIT_KEY);
    sessionRemove(IMPERSONATION_TARGET_KEY);
    setToken(originalToken || '');
    setCsrfToken('');
    setUser(null);
    return originalToken || '';
  }

  function setPreferredLocale(locale) {
    var value = normalizeLocaleCode(locale);
    if (!value) {
      storageRemove(LOCALE_KEY);
      document.cookie = 'crm_locale=; path=/; max-age=0; samesite=lax';
      return;
    }
    storageSet(LOCALE_KEY, value);
    document.cookie = 'crm_locale=' + encodeURIComponent(value) + '; path=/; max-age=31536000; samesite=lax';
    if (document && document.documentElement) {
      document.documentElement.lang = value.split('-')[0];
    }
  }

  function getPreferredLocale() {
    return normalizeLocaleCode(storageGet(LOCALE_KEY, 'ru-ru') || 'ru-ru');
  }

  function createIdempotencyKey(prefix) {
    var p = prefix || 'web';
    var random = Math.random().toString(36).slice(2, 10);
    return p + '-' + Date.now() + '-' + random;
  }

  function normalizeEnvelope(status, payload) {
    if (payload && typeof payload === 'object' && Object.prototype.hasOwnProperty.call(payload, 'success')) {
      return payload;
    }

    if (status >= 200 && status < 300) {
      return {
        success: true,
        code: 'HTTP_' + status,
        message: 'OK',
        data: payload,
        errors: [],
        meta: {}
      };
    }

    return {
      success: false,
      code: 'HTTP_' + status,
      message: 'HTTP error ' + status,
      data: null,
      errors: [],
      meta: {}
    };
  }

  function sleep(ms) {
    return new Promise(function (resolve) {
      setTimeout(resolve, Math.max(0, Number(ms) || 0));
    });
  }

  function toNumber(value, fallback) {
    var parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
  }

  function retryableStatus(status) {
    return status === 429 || status === 502 || status === 503 || status === 504;
  }

  function applyResponseMeta(envelope, response) {
    if (!envelope || typeof envelope !== 'object' || !response) return envelope;
    envelope.meta = envelope.meta && typeof envelope.meta === 'object' ? envelope.meta : {};

    var retryAfter = response.headers && response.headers.get ? response.headers.get('Retry-After') : null;
    if (retryAfter !== null && retryAfter !== '') {
      envelope.meta.retry_after = retryAfter;
    }

    var requestId = response.headers && response.headers.get ? response.headers.get('X-Request-Id') : null;
    if (requestId) {
      envelope.meta.request_id = requestId;
    }

    var correlationId = response.headers && response.headers.get ? response.headers.get('X-Correlation-Id') : null;
    if (correlationId) {
      envelope.meta.correlation_id = correlationId;
    }

    return envelope;
  }

  function parseHttpStatus(code, meta) {
    if (meta && Number.isFinite(Number(meta.status))) return Number(meta.status);
    if (meta && Number.isFinite(Number(meta.status_code))) return Number(meta.status_code);
    var match = String(code || '').match(/^HTTP_(\d{3})$/);
    if (match) return Number(match[1]);
    return 0;
  }

  function toFieldErrors(rawErrors) {
    if (!rawErrors || typeof rawErrors !== 'object') return {};
    if (Array.isArray(rawErrors)) return {};
    return rawErrors;
  }

  function normalizeError(error, fallbackMessage) {
    var envelope = error && error.envelope ? error.envelope : null;
    var code = String((envelope && envelope.code) || (error && error.message) || 'REQUEST_FAILED');
    var meta = envelope && typeof envelope.meta === 'object' && envelope.meta ? envelope.meta : {};
    var status = parseHttpStatus(code, meta);
    var fieldErrors = toFieldErrors(envelope && envelope.errors ? envelope.errors : {});
    var retryAfter = meta.retry_after ? String(meta.retry_after) : '';
    var requestId = meta.request_id ? String(meta.request_id) : '';
    var correlationId = meta.correlation_id ? String(meta.correlation_id) : '';

    var isAuthError = status === 401 || code === 'UNAUTHORIZED';
    var isPermissionError = status === 403 || code === 'FORBIDDEN' || code === 'HTTP_403';
    var isNotFound = status === 404 || code === 'NOT_FOUND' || code === 'HTTP_404' || code === 'ROUTE_NOT_FOUND';
    var isValidationError = status === 422 || code === 'VALIDATION_ERROR';
    var isRateLimited = status === 429 || code === 'RATE_LIMITED' || code === 'AUTH_RATE_LIMITED' || code === 'AI_RATE_LIMITED';
    var isNetworkError = code === 'NETWORK_ERROR';
    var isTimeout = code === 'NETWORK_TIMEOUT';
    var isAborted = code === 'REQUEST_ABORTED';
    var isServerError = status >= 500;

    var message = (envelope && envelope.message) ? String(envelope.message) : '';
    if (!message) {
      if (isAborted) message = window.CRM.i18n.t('js.api.aborted', 'Request cancelled');
      else if (isTimeout) message = window.CRM.i18n.t('js.api.timeout', 'Response timeout expired');
      else if (isNetworkError) message = window.CRM.i18n.t('js.api.network_error', 'Network error');
      else if (isAuthError) message = window.CRM.i18n.t('js.api.auth_required', 'Re-authentication required');
      else if (isPermissionError) message = window.CRM.i18n.t('js.api.permission_denied', 'Insufficient permissions');
      else if (isRateLimited) message = window.CRM.i18n.t('js.api.rate_limited', 'Too many requests, try again later');
      else message = String(fallbackMessage || window.CRM.i18n.t('js.api.error', 'API Error'));
    }

    return {
      code: code,
      status: status,
      message: message,
      fieldErrors: fieldErrors,
      retryAfter: retryAfter,
      requestId: requestId,
      correlationId: correlationId,
      envelope: envelope,
      isAuthError: isAuthError,
      isPermissionError: isPermissionError,
      isNotFound: isNotFound,
      isValidationError: isValidationError,
      isRateLimited: isRateLimited,
      isNetworkError: isNetworkError,
      isTimeout: isTimeout,
      isAborted: isAborted,
      isServerError: isServerError
    };
  }

  function formatErrorMessage(normalizedError, options) {
    var err = normalizedError && typeof normalizedError === 'object' ? normalizedError : normalizeError(normalizedError);
    var opts = options && typeof options === 'object' ? options : {};
    var withRequestId = opts.withRequestId === true;
    var baseMessage = String(err.message || window.CRM.i18n.t('js.api.error', 'API Error'));
    if (withRequestId && err.requestId) {
      return baseMessage + ' [request_id: ' + err.requestId + ']';
    }
    return baseMessage;
  }

  function redactTelemetryPayload(payload) {
    if (!payload || typeof payload !== 'object') return {};
    var output = {};
    Object.keys(payload).forEach(function (key) {
      var normalized = String(key || '').toLowerCase();
      var value = payload[key];
      var sensitive = normalized.indexOf('password') >= 0
        || normalized.indexOf('secret') >= 0
        || normalized.indexOf('token') >= 0
        || normalized.indexOf('authorization') >= 0
        || normalized.indexOf('cookie') >= 0
        || normalized.indexOf('api_key') >= 0
        || normalized.indexOf('prompt') >= 0;
      if (sensitive) {
        output[key] = '[REDACTED]';
        return;
      }
      if (value && typeof value === 'object') {
        output[key] = redactTelemetryPayload(value);
        return;
      }
      var serialized = value === null || value === undefined ? '' : String(value);
      output[key] = serialized.length > 1000 ? serialized.slice(0, 1000) : serialized;
    });
    return output;
  }

  function sendFrontendTelemetry(eventType, payload) {
    var event = String(eventType || '').trim().toLowerCase();
    if (event !== 'api_error' && event !== 'js_error') return;
    if (window.CRM.__frontendTelemetryInFlight) return;
    var body = {
      event_type: event,
      route: currentRoute('dashboard'),
      page_url: String(window.location.pathname || '') + String(window.location.search || ''),
      payload: redactTelemetryPayload(payload || {})
    };
    window.CRM.__frontendTelemetryInFlight = true;
    var _headers = {
      'Content-Type': 'application/json',
      'X-Locale': getPreferredLocale()
    };
    var _csrf = getCsrfToken();
    if (_csrf) {
      _headers['X-CSRF-Token'] = _csrf;
    }

    fetch(buildUrl('api/v1/telemetry/frontend-event'), {
      method: 'POST',
      headers: _headers,
      credentials: 'same-origin',
      body: JSON.stringify(body)
    }).catch(function () {
      return null;
    }).finally(function () {
      window.CRM.__frontendTelemetryInFlight = false;
    });
  }

  function bindGlobalTelemetry() {
    if (window.CRM.__frontendTelemetryBound) return;
    window.CRM.__frontendTelemetryBound = true;

    window.addEventListener('error', function (event) {
      sendFrontendTelemetry('js_error', {
        message: event && event.message ? String(event.message) : 'window.error',
        file: event && event.filename ? String(event.filename) : '',
        line: event && event.lineno ? String(event.lineno) : '',
        column: event && event.colno ? String(event.colno) : ''
      });
    });

    window.addEventListener('unhandledrejection', function (event) {
      var reason = event && event.reason;
      var reasonMessage = '';
      if (reason && typeof reason === 'object' && reason.message) {
        reasonMessage = String(reason.message);
      } else {
        reasonMessage = String(reason || 'unhandledrejection');
      }
      sendFrontendTelemetry('js_error', {
        message: 'unhandledrejection',
        reason: reasonMessage
      });
    });
  }

  function referenceCacheTtlMs(route) {
    var key = String(route || '').replace(/^\/+/, '').split('?')[0];
    if (key === 'api/v1/auth/me') return 10000;
    if (key === 'api/v1/auth/menu') return 30000;
    if (key === 'api/v1/statuses' || key === 'api/v1/priorities') return 60000;
    if (key === 'api/v1/users') return 20000;
    return 0;
  }

  function referenceCacheKey(route, query) {
    return buildUrl(route, query) + '|locale=' + getPreferredLocale();
  }

  function referenceCacheStorageKey(cacheKey) {
    return REFERENCE_GET_CACHE_PREFIX + cacheKey;
  }

  function readReferenceCache(cacheKey) {
    var memoryCached = referenceGetCache[cacheKey];
    if (memoryCached && memoryCached.expiresAt > Date.now()) {
      return memoryCached.envelope;
    }
    if (memoryCached) {
      delete referenceGetCache[cacheKey];
    }

    var raw = sessionGet(referenceCacheStorageKey(cacheKey), '');
    if (!raw) return null;
    try {
      var decoded = JSON.parse(raw);
      if (!decoded || Number(decoded.expiresAt || 0) <= Date.now() || !decoded.envelope) {
        sessionRemove(referenceCacheStorageKey(cacheKey));
        return null;
      }
      referenceGetCache[cacheKey] = {
        expiresAt: Number(decoded.expiresAt || 0),
        envelope: decoded.envelope
      };
      return decoded.envelope;
    } catch (e) {
      sessionRemove(referenceCacheStorageKey(cacheKey));
      return null;
    }
  }

  function writeReferenceCache(cacheKey, envelope, ttlMs) {
    var payload = {
      expiresAt: Date.now() + ttlMs,
      envelope: envelope
    };
    referenceGetCache[cacheKey] = payload;
    try {
      sessionSet(referenceCacheStorageKey(cacheKey), JSON.stringify(payload));
    } catch (e) {
      void e;
    }
  }

  function clearReferenceGetCache() {
    referenceGetCache = {};
    try {
      if (!window.sessionStorage) return;
      var keys = [];
      for (var i = 0; i < window.sessionStorage.length; i += 1) {
        keys.push(window.sessionStorage.key(i));
      }
      keys.forEach(function (key) {
        if (String(key || '').indexOf(REFERENCE_GET_CACHE_PREFIX) === 0) {
          window.sessionStorage.removeItem(key);
        }
      });
    } catch (e) {
      void e;
    }
  }

  async function request(route, options) {
    var opts = options && typeof options === 'object' ? Object.assign({}, options) : {};
    var method = (opts.method || 'GET').toUpperCase();
    var headers = Object.assign({}, opts.headers || {});
    var useAuth = opts.auth !== false;
    var body = opts.body;
    var isFormData = typeof FormData !== 'undefined' && body instanceof FormData;
    var isUrlParams = typeof URLSearchParams !== 'undefined' && body instanceof URLSearchParams;
    var isBlob = typeof Blob !== 'undefined' && body instanceof Blob;

    void useAuth;

    var cacheTtlMs = method === 'GET' && body === undefined && !opts.signal && opts.noCache !== true
      ? referenceCacheTtlMs(route)
      : 0;
    var cacheKey = cacheTtlMs > 0 ? referenceCacheKey(route, opts.query) : '';
    if (cacheKey) {
      var cached = readReferenceCache(cacheKey);
      if (cached) return cached;
    }

    var hasCustomHeaders = opts.headers && typeof opts.headers === 'object' && Object.keys(opts.headers).length > 0;
    var canDedupeGet = method === 'GET'
      && opts.noDedupe !== true
      && body === undefined
      && !opts.signal
      && !hasCustomHeaders;
    if (canDedupeGet) {
      var dedupeKey = buildUrl(route, opts.query) + '|locale=' + getPreferredLocale();
      if (inFlightGetRequests[dedupeKey]) {
        return inFlightGetRequests[dedupeKey];
      }
      var dedupeOptions = Object.assign({}, opts, { noDedupe: true });
      inFlightGetRequests[dedupeKey] = request(route, dedupeOptions).finally(function () {
        delete inFlightGetRequests[dedupeKey];
      });
      return inFlightGetRequests[dedupeKey];
    }

    if (!headers['X-Locale'] && !headers['x-locale']) {
      headers['X-Locale'] = getPreferredLocale();
    }

    if (useAuth && !headers.Authorization && !headers.authorization) {
      var bearerToken = getToken();
      if (bearerToken) {
        headers.Authorization = 'Bearer ' + bearerToken;
      }
    }

    if (body !== undefined && body !== null && !isFormData && !isUrlParams && !isBlob) {
      headers['Content-Type'] = 'application/json';
    }
    if (['POST', 'PATCH', 'PUT', 'DELETE'].indexOf(method) >= 0 && !headers['X-CSRF-Token'] && !headers['x-csrf-token']) {
      var csrfToken = getCsrfToken();
      if (csrfToken) {
        headers['X-CSRF-Token'] = csrfToken;
      }
    }

    var allowRetry = opts.retry === true;
    var isIdempotent = method === 'GET' || method === 'HEAD' || opts.idempotent === true;
    var maxRetries = Math.max(0, Math.floor(toNumber(opts.maxRetries, 1)));
    var retryDelayMs = Math.max(0, Math.floor(toNumber(opts.retryDelayMs, 300)));
    var timeoutMs = Math.max(0, Math.floor(toNumber(opts.timeoutMs, route.indexOf('api/v1/ai/') === 0 ? 120000 : 15000)));
    var attempts = 0;

    while (true) {
      attempts += 1;

      var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
      var timeoutHandle = null;
      var timedOut = false;
      var abortedByCaller = false;
      var abortListener = null;

      if (controller && opts.signal && typeof opts.signal.addEventListener === 'function') {
        if (opts.signal.aborted) {
          controller.abort();
          abortedByCaller = true;
        } else {
          abortListener = function () {
            abortedByCaller = true;
            controller.abort();
          };
          opts.signal.addEventListener('abort', abortListener, { once: true });
        }
      }

      if (controller && timeoutMs > 0) {
        timeoutHandle = setTimeout(function () {
          timedOut = true;
          controller.abort();
        }, timeoutMs);
      }

      var response = null;
      try {
        response = await fetch(buildUrl(route, opts.query), {
          method: method,
          headers: headers,
          credentials: 'same-origin',
          signal: controller ? controller.signal : undefined,
          body: body !== undefined && body !== null
            ? (isFormData || isUrlParams || isBlob ? body : JSON.stringify(body))
            : undefined
        });
      } catch (networkError) {
        if (timeoutHandle) clearTimeout(timeoutHandle);
        if (abortListener && opts.signal && typeof opts.signal.removeEventListener === 'function') {
          opts.signal.removeEventListener('abort', abortListener);
        }

        if (timedOut) {
          var tError = new Error('NETWORK_TIMEOUT');
          tError.envelope = {
            success: false,
            code: 'NETWORK_TIMEOUT',
            message: 'Request timeout',
            data: null,
            errors: [String(networkError)],
            meta: { timeout_ms: timeoutMs, attempts: attempts }
          };
          if (allowRetry && isIdempotent && attempts <= maxRetries) {
            await sleep(retryDelayMs * attempts);
            continue;
          }
          throw tError;
        }

        if (abortedByCaller || (opts.signal && opts.signal.aborted)) {
          var aError = new Error('REQUEST_ABORTED');
          aError.envelope = {
            success: false,
            code: 'REQUEST_ABORTED',
            message: 'Request aborted',
            data: null,
            errors: [String(networkError)],
            meta: { attempts: attempts }
          };
          throw aError;
        }

        if (allowRetry && isIdempotent && attempts <= maxRetries) {
          await sleep(retryDelayMs * attempts);
          continue;
        }

        var nError = new Error('NETWORK_ERROR');
        nError.envelope = {
          success: false,
          code: 'NETWORK_ERROR',
          message: 'Network error',
          data: null,
          errors: [String(networkError)],
          meta: { attempts: attempts }
        };
        throw nError;
      } finally {
        if (timeoutHandle) clearTimeout(timeoutHandle);
        if (abortListener && opts.signal && typeof opts.signal.removeEventListener === 'function') {
          opts.signal.removeEventListener('abort', abortListener);
        }
      }

      var payload = null;
      try {
        payload = await response.json();
      } catch (parseError) {
        payload = null;
      }

      var routeKey = String(route || '').trim().toLowerCase();
      var contentType = response.headers && response.headers.get
        ? String(response.headers.get('Content-Type') || '').toLowerCase()
        : '';
      var looksLikeApiRoute = routeKey.indexOf('api/') === 0 || routeKey.indexOf('/api/') === 0;
      var isHtmlResponse = contentType.indexOf('text/html') >= 0;
      if (looksLikeApiRoute && isHtmlResponse) {
        var invalidApiResponseError = new Error('INVALID_API_RESPONSE');
        invalidApiResponseError.envelope = {
          success: false,
          code: 'INVALID_API_RESPONSE',
          message: 'API returned unexpected HTML response',
          data: null,
          errors: [],
          meta: {
            status: Number(response.status || 0) || 0,
            content_type: contentType
          }
        };
        throw invalidApiResponseError;
      }

      var envelope = applyResponseMeta(normalizeEnvelope(response.status, payload), response);
      if (response.ok && envelope.success) {
        if (cacheKey) {
          writeReferenceCache(cacheKey, envelope, cacheTtlMs);
        } else if (method !== 'GET') {
          clearReferenceGetCache();
        }
        return envelope;
      }

      if (allowRetry && isIdempotent && retryableStatus(response.status) && attempts <= maxRetries) {
        await sleep(retryDelayMs * attempts);
        continue;
      }

      var error = new Error(envelope.code || 'API_ERROR');
      error.envelope = envelope;
      if (String(route || '').indexOf('api/v1/telemetry/frontend-event') === -1) {
        sendFrontendTelemetry('api_error', {
          route: String(route || ''),
          method: method,
          code: String(envelope.code || ''),
          status: String(response.status || ''),
          request_id: envelope && envelope.meta ? String(envelope.meta.request_id || '') : '',
          correlation_id: envelope && envelope.meta ? String(envelope.meta.correlation_id || '') : ''
        });
      }
      throw error;
    }
  }

  async function login(loginValue, passwordValue, localeValue) {
    var locale = String(localeValue || '').trim().toLowerCase();
    if (locale) {
      setPreferredLocale(locale);
    }
    var payload = {
      login: loginValue,
      password: passwordValue
    };
    var envelope = await request('api/v1/auth/login', {
      method: 'POST',
      auth: false,
      headers: locale ? { 'X-Locale': locale } : {},
      body: payload
    });

    var user = envelope.data && envelope.data.user ? envelope.data.user : null;
    var csrfToken = envelope.data && envelope.data.csrf_token ? envelope.data.csrf_token : '';
    var accessToken = envelope.data && envelope.data.access_token ? envelope.data.access_token : '';

    setToken(accessToken);
    setCsrfToken(csrfToken);
    setUser(user);

    return envelope;
  }

  async function logout() {
    try {
      await request('api/v1/auth/logout', {
        method: 'POST',
        body: {}
      });
    } catch (e) {
      // ignore server-side logout error, local auth will still be cleared
    }

    clearAuth();
  }

  async function me() {
    var envelope = await request('api/v1/auth/me', { method: 'GET' });
    var user = envelope.data && envelope.data.user ? envelope.data.user : null;
    var csrfToken = envelope.data && envelope.data.csrf_token ? envelope.data.csrf_token : '';
    setCsrfToken(csrfToken);
    setUser(user);
    return envelope;
  }

  function items(envelope) {
    return envelope && envelope.data && Array.isArray(envelope.data.items) ? envelope.data.items : [];
  }

  bindGlobalTelemetry();
  setToken(sessionGet(IMPERSONATION_TOKEN_KEY, ''));

  return {
    TOKEN_KEY: LEGACY_TOKEN_KEY,
    USER_KEY: LEGACY_USER_KEY,
    LOCALE_KEY: LOCALE_KEY,
    buildUrl: buildUrl,
    buildWebUrl: buildWebUrl,
    currentRoute: currentRoute,
    request: request,
    items: items,
    createIdempotencyKey: createIdempotencyKey,
    setToken: setToken,
    getToken: getToken,
    setCsrfToken: setCsrfToken,
    getCsrfToken: getCsrfToken,
    setUser: setUser,
    getUser: getUser,
    getPermissionCodes: getPermissionCodes,
    hasPermission: hasPermission,
    hasAnyPermission: hasAnyPermission,
    hasRole: hasRole,
    canAccessRoute: canAccessRoute,
    sendFrontendTelemetry: sendFrontendTelemetry,
    normalizeError: normalizeError,
    formatErrorMessage: formatErrorMessage,
    clearAuth: clearAuth,
    setPreferredLocale: setPreferredLocale,
    getPreferredLocale: getPreferredLocale,
    getActiveImpersonation: getActiveImpersonation,
    activateImpersonationSession: activateImpersonationSession,
    restoreOriginalSessionAfterImpersonation: restoreOriginalSessionAfterImpersonation,
    login: login,
    logout: logout,
    me: me
  };
})();
