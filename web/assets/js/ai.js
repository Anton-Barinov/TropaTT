window.CRM = window.CRM || {};
window.CRM.ai = (function () {
  var drawerHandlers = {
    onApply: null,
    onDismiss: null,
    onRefresh: null
  };

  var drawerContext = {
    suggestion: null,
    preview: null,
    actions: []
  };
  var drawerBusy = false;
  var availabilityCache = {
    loaded: false,
    loading: null,
    requested: {},
    intents: {},
    ai: {
      enabled: null,
      provider_configured: null
    },
    actor: {
      can_use_ai: null,
      can_manage_ai: null
    }
  };
  var intentUiStateCopy = {
    'task_summary': {
      empty: 'AI-сводка задачи пока не сформирована.',
      error: 'Не удалось сформировать AI-сводку задачи.'
    },
    'task_decomposition': {
      empty: 'AI-декомпозиция задачи пока не сформирована.',
      error: 'Не удалось сформировать AI-декомпозицию задачи.'
    },
    'task_checklist': {
      empty: 'AI-чеклист задачи пока не сформирован.',
      error: 'Не удалось сформировать AI-чеклист задачи.'
    },
    'task_quality': {
      empty: 'AI-оценка качества задачи пока не сформирована.',
      error: 'Не удалось сформировать AI-оценку качества задачи.'
    },
    'task_next_action': {
      empty: 'AI-рекомендация следующего шага пока не сформирована.',
      error: 'Не удалось сформировать AI-рекомендацию следующего шага.'
    },
    'task_comment_draft': {
      empty: 'AI-черновик комментария пока не сформирован.',
      error: 'Не удалось сформировать AI-черновик комментария.'
    },
    'project_summary': {
      empty: 'AI-сводка проекта пока не сформирована.',
      error: 'Не удалось сформировать AI-сводку проекта.'
    },
    'project_risk_summary': {
      empty: 'AI-анализ рисков проекта пока не сформирован.',
      error: 'Не удалось сформировать AI-анализ рисков проекта.'
    },
    'project_client_report': {
      empty: 'AI-клиентский отчет по проекту пока не сформирован.',
      error: 'Не удалось сформировать AI-клиентский отчет по проекту.'
    },
    'client_summary': {
      empty: 'AI-сводка клиента пока не сформирована.',
      error: 'Не удалось сформировать AI-сводку клиента.'
    },
    'client_meeting_prep': {
      empty: 'AI-подготовка к встрече пока не сформирована.',
      error: 'Не удалось сформировать AI-подготовку к встрече.'
    },
    'client_data_quality': {
      empty: 'AI-проверка качества данных клиента пока не сформирована.',
      error: 'Не удалось сформировать AI-проверку качества данных клиента.'
    },
    'client_safe_report': {
      empty: 'AI client-safe отчет пока не сформирован.',
      error: 'Не удалось сформировать AI client-safe отчет.'
    },
    'calendar_event_agenda': {
      empty: 'AI agenda события пока не сформирована.',
      error: 'Не удалось сформировать AI agenda события.'
    },
    'dashboard_daily_digest': {
      empty: 'AI-сводка дня пока не сформирована.',
      error: 'Не удалось сформировать AI-сводку дня.'
    },
    'analytics_kpi_explanation': {
      empty: 'AI-пояснение KPI пока не сформировано.',
      error: 'Не удалось сформировать AI-пояснение KPI.'
    },
    'analytics_risks_explanation': {
      empty: 'AI-пояснение рисков пока не сформировано.',
      error: 'Не удалось сформировать AI-пояснение рисков.'
    },
    'analytics_team_workload_summary': {
      empty: 'AI-сводка нагрузки команды пока не сформирована.',
      error: 'Не удалось сформировать AI-сводку нагрузки команды.'
    },
    'admin_log_review': {
      empty: 'AI-обзор логов пока не сформирован.',
      error: 'Не удалось сформировать AI-обзор логов.'
    },
    'webhook_health_review': {
      empty: 'AI-обзор состояния webhook пока не сформирован.',
      error: 'Не удалось сформировать AI-обзор состояния webhook.'
    },
    'workflow_rule_audit': {
      empty: 'AI-аудит workflow правил пока не сформирован.',
      error: 'Не удалось сформировать AI-аудит workflow правил.'
    },
    'my_day_plan': {
      empty: 'AI-план дня пока не сформирован.',
      error: 'Не удалось сформировать AI-план дня.'
    },
    'my_week_plan': {
      empty: 'AI-план недели пока не сформирован.',
      error: 'Не удалось сформировать AI-план недели.'
    },
    'task_list_priority': {
      empty: 'AI-приоритет задач пока не сформирован.',
      error: 'Не удалось сформировать AI-приоритет задач.'
    },
    'daily_work_plan': {
      empty: 'AI daily work plan пока не сформирован.',
      error: 'Не удалось сформировать AI daily work plan.'
    },
    'security_log_review': {
      empty: 'AI security log review пока не сформирован.',
      error: 'Не удалось сформировать AI security log review.'
    },
    'semantic_search': {
      empty: 'AI semantic search результат пока не сформирован.',
      error: 'Не удалось сформировать AI semantic search результат.'
    }
  };
  var aiRouteIntentPatterns = [
    { pattern: /^api\/v1\/ai\/tasks\/[^/]+\/summary$/i, intent: 'task_summary' },
    { pattern: /^api\/v1\/ai\/tasks\/[^/]+\/next-action$/i, intent: 'task_next_action' },
    { pattern: /^api\/v1\/ai\/tasks\/[^/]+\/decompose$/i, intent: 'task_decomposition' },
    { pattern: /^api\/v1\/ai\/tasks\/[^/]+\/checklist$/i, intent: 'task_checklist' },
    { pattern: /^api\/v1\/ai\/tasks\/[^/]+\/improve-description$/i, intent: 'task_summary' },
    { pattern: /^api\/v1\/ai\/tasks\/[^/]+\/comment-draft$/i, intent: 'task_comment_draft' },
    { pattern: /^api\/v1\/ai\/tasks\/[^/]+\/quality$/i, intent: 'task_quality' },
    { pattern: /^api\/v1\/ai\/tasks\/[^/]+\/quality-check$/i, intent: 'task_quality' },
    { pattern: /^api\/v1\/ai\/tasks\/[^/]+\/meeting-create$/i, intent: 'task_summary' },
    { pattern: /^api\/v1\/ai\/projects\/[^/]+\/summary$/i, intent: 'project_summary' },
    { pattern: /^api\/v1\/ai\/projects\/[^/]+\/risks$/i, intent: 'project_risk_summary' },
    { pattern: /^api\/v1\/ai\/projects\/[^/]+\/client-report$/i, intent: 'project_client_report' },
    { pattern: /^api\/v1\/ai\/clients\/[^/]+\/summary$/i, intent: 'client_summary' },
    { pattern: /^api\/v1\/ai\/clients\/[^/]+\/meeting-prep$/i, intent: 'client_meeting_prep' },
    { pattern: /^api\/v1\/ai\/clients\/[^/]+\/data-quality$/i, intent: 'client_data_quality' },
    { pattern: /^api\/v1\/ai\/clients\/[^/]+\/client-safe-report$/i, intent: 'client_safe_report' },
    { pattern: /^api\/v1\/ai\/clients\/[^/]+\/safe-report$/i, intent: 'client_safe_report' },
    { pattern: /^api\/v1\/ai\/calendar\/events\/[^/]+\/agenda$/i, intent: 'calendar_event_agenda' },
    { pattern: /^api\/v1\/ai\/dashboard\/digest$/i, intent: 'dashboard_daily_digest' },
    { pattern: /^api\/v1\/ai\/my-day\/plan$/i, intent: 'my_day_plan' },
    { pattern: /^api\/v1\/ai\/my-week\/plan$/i, intent: 'my_week_plan' },
    { pattern: /^api\/v1\/ai\/tasks\/priority$/i, intent: 'task_list_priority' },
    { pattern: /^api\/v1\/ai\/search\/semantic$/i, intent: 'semantic_search' },
    { pattern: /^api\/v1\/ai\/analytics\/kpi-explanation$/i, intent: 'analytics_kpi_explanation' },
    { pattern: /^api\/v1\/ai\/analytics\/kpi-explain$/i, intent: 'analytics_kpi_explanation' },
    { pattern: /^api\/v1\/ai\/analytics\/risks-explanation$/i, intent: 'analytics_risks_explanation' },
    { pattern: /^api\/v1\/ai\/analytics\/risk-explain$/i, intent: 'analytics_risks_explanation' },
    { pattern: /^api\/v1\/ai\/analytics\/team-workload-summary$/i, intent: 'analytics_team_workload_summary' },
    { pattern: /^api\/v1\/ai\/analytics\/team-workload$/i, intent: 'analytics_team_workload_summary' },
    { pattern: /^api\/v1\/ai\/admin\/log-review$/i, intent: 'admin_log_review' },
    { pattern: /^api\/v1\/ai\/admin\/logs-review$/i, intent: 'admin_log_review' },
    { pattern: /^api\/v1\/ai\/admin\/webhook-health$/i, intent: 'webhook_health_review' },
    { pattern: /^api\/v1\/ai\/admin\/workflow-audit$/i, intent: 'workflow_rule_audit' }
  ];

  function request(route, options) {
    return window.CRM.api.request(route, options || {});
  }

  function normalizeIntentList(intents) {
    var list = Array.isArray(intents) ? intents : [];
    return list.map(function (item) {
      return String(item || '').trim();
    }).filter(Boolean).filter(function (value, index, source) {
      return source.indexOf(value) === index;
    });
  }

  function mergeAvailability(payload) {
    var data = payload && typeof payload === 'object' ? payload : {};
    var ai = data.ai && typeof data.ai === 'object' ? data.ai : {};
    var actor = data.actor && typeof data.actor === 'object' ? data.actor : {};
    var intents = data.intents && typeof data.intents === 'object' ? data.intents : {};

    availabilityCache.loaded = true;
    availabilityCache.ai.enabled = typeof ai.enabled === 'boolean' ? ai.enabled : availabilityCache.ai.enabled;
    availabilityCache.ai.provider_configured = typeof ai.provider_configured === 'boolean' ? ai.provider_configured : availabilityCache.ai.provider_configured;
    availabilityCache.actor.can_use_ai = typeof actor.can_use_ai === 'boolean' ? actor.can_use_ai : availabilityCache.actor.can_use_ai;
    availabilityCache.actor.can_manage_ai = typeof actor.can_manage_ai === 'boolean' ? actor.can_manage_ai : availabilityCache.actor.can_manage_ai;

    Object.keys(intents).forEach(function (intentCode) {
      var item = intents[intentCode];
      if (!item || typeof item !== 'object') return;
      var code = String(item.intent_code || intentCode || '').trim();
      if (!code) return;
      availabilityCache.intents[code] = {
        intent_code: code,
        enabled: Boolean(item.enabled),
        reason: String(item.reason || '')
      };
      availabilityCache.requested[code] = true;
    });
  }

  function hydrateAvailability(intents) {
    var requestedIntents = normalizeIntentList(intents);
    var missingIntents = requestedIntents.filter(function (intentCode) {
      return availabilityCache.requested[intentCode] !== true;
    });
    if (availabilityCache.loaded && missingIntents.length === 0) {
      return Promise.resolve({
        ai: availabilityCache.ai,
        actor: availabilityCache.actor,
        intents: availabilityCache.intents
      });
    }
    if (availabilityCache.loading) {
      return availabilityCache.loading;
    }

    var query = {};
    if (missingIntents.length > 0) {
      query.intents = missingIntents.join(',');
    }

    availabilityCache.loading = request('api/v1/ai/availability', { method: 'GET', query: query }).then(function (envelope) {
      mergeAvailability(envelope && envelope.data ? envelope.data : {});
      availabilityCache.loading = null;
      return {
        ai: availabilityCache.ai,
        actor: availabilityCache.actor,
        intents: availabilityCache.intents
      };
    }).catch(function () {
      availabilityCache.loading = null;
      return {
        ai: availabilityCache.ai,
        actor: availabilityCache.actor,
        intents: availabilityCache.intents
      };
    });

    return availabilityCache.loading;
  }

  function isIntentEnabledForUi(intentCode) {
    var code = String(intentCode || '').trim();
    if (!code) return true;
    var item = availabilityCache.intents[code];
    if (!item || typeof item !== 'object') return true;
    return Boolean(item.enabled);
  }

  function getIntentAvailability(intentCode) {
    var code = String(intentCode || '').trim();
    if (!code) {
      return {
        intent_code: '',
        enabled: true,
        reason: ''
      };
    }
    var item = availabilityCache.intents[code];
    if (!item || typeof item !== 'object') {
      return {
        intent_code: code,
        enabled: true,
        reason: ''
      };
    }
    return {
      intent_code: code,
      enabled: Boolean(item.enabled),
      reason: String(item.reason || '')
    };
  }

  function inferIntentByRoute(route) {
    var normalizedRoute = String(route || '').trim();
    if (!normalizedRoute) return '';
    for (var i = 0; i < aiRouteIntentPatterns.length; i += 1) {
      var item = aiRouteIntentPatterns[i];
      if (item && item.pattern && item.pattern.test(normalizedRoute)) {
        return String(item.intent || '');
      }
    }
    return '';
  }

  function createApiLikeError(code, message, meta) {
    var error = new Error(String(message || 'Не удалось выполнить AI-запрос'));
    error.status = 403;
    error.envelope = {
      success: false,
      code: String(code || 'FORBIDDEN'),
      message: String(message || 'Недостаточно прав'),
      data: null,
      errors: [],
      meta: meta && typeof meta === 'object' ? meta : {}
    };
    return error;
  }

  function showAiActionNotice(intentCode) {
    var intent = String(intentCode || 'general').trim() || 'general';
    var key = 'ai_notice_' + intent;
    window.CRM.__aiActionNotices = window.CRM.__aiActionNotices || {};
    if (window.CRM.__aiActionNotices[key]) return;
    window.CRM.__aiActionNotices[key] = true;
    if (typeof window.notify === 'function') {
      window.notify('AI использует доступный CRM-контекст без передачи паролей, токенов и секретов.', 'info');
    }
  }

  function requestAi(route, body, options) {
    var opts = options || {};
    var method = String(opts.method || 'POST').toUpperCase();
    var headers = Object.assign({}, opts.headers || {});
    var inferredIntent = inferIntentByRoute(route);

    if (!hasAiPermission(inferredIntent)) {
      return Promise.reject(createApiLikeError('FORBIDDEN', 'Недостаточно прав для выполнения AI-действия', {
        reason: 'permission_required',
        intent_code: inferredIntent
      }));
    }

    var ensureAvailability = inferredIntent
      ? hydrateAvailability([inferredIntent]).then(function () {
          var availability = getIntentAvailability(inferredIntent);
          if (!availability.enabled) {
            throw createApiLikeError('AI_INTENT_DISABLED', 'AI-действие временно недоступно для этой роли.', {
              reason: String(availability.reason || 'intent_disabled'),
              intent_code: inferredIntent
            });
          }
          return true;
        })
      : Promise.resolve(true);

    if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) >= 0 && !headers['X-Idempotency-Key']) {
      headers['X-Idempotency-Key'] = window.CRM.api.createIdempotencyKey('ai-request');
    }

    return ensureAvailability.then(function () {
      showAiActionNotice(inferredIntent);
      return request(route, {
        method: method,
        query: opts.query || {},
        headers: headers,
        body: body === undefined ? (opts.body || {}) : body
      });
    });
  }

  function normalizeError(error, fallbackMessage) {
    var envelope = error && error.envelope ? error.envelope : null;
    var code = String((envelope && envelope.code) || 'AI_REQUEST_FAILED');
    var meta = envelope && envelope.meta && typeof envelope.meta === 'object' ? envelope.meta : {};
    var providerError = envelope && envelope.provider_error && typeof envelope.provider_error === 'object'
      ? envelope.provider_error
      : (meta && meta.provider_error && typeof meta.provider_error === 'object' ? meta.provider_error : null);

    var retryAfter = Number(meta.retry_after || 0);
    var retryable = providerError ? Boolean(providerError.retryable) : (code !== 'AI_PROVIDER_AUTH_FAILED');

    var message = String((envelope && envelope.message) || fallbackMessage || 'Не удалось выполнить AI-запрос');
    if (code === 'AI_DISABLED') {
      message = 'AI временно отключен администратором.';
    } else if (code === 'AI_PROVIDER_NOT_CONFIGURED') {
      message = 'AI пока не настроен администратором.';
      if (window.CRM && window.CRM.api && typeof window.CRM.api.hasPermission === 'function' && window.CRM.api.hasPermission('ai.admin')) {
        message += ' Проверьте provider override в intent settings и default provider.';
      }
    } else if (code === 'AI_PROVIDER_TIMEOUT') {
      message = 'Провайдер AI не ответил вовремя. Попробуйте еще раз.';
    } else if (code === 'AI_PROVIDER_AUTH_FAILED') {
      message = 'Ошибка доступа к AI-провайдеру. Обратитесь к администратору.';
    } else if (code === 'AI_PROVIDER_UNAVAILABLE') {
      message = 'AI-провайдер временно недоступен. Попробуйте позже.';
    } else if (code === 'AI_RATE_LIMITED') {
      message = retryAfter > 0
        ? ('Лимит AI-запросов временно исчерпан. Повторите через ' + String(retryAfter) + ' сек.')
        : 'Лимит AI-запросов временно исчерпан. Попробуйте позже.';
    } else if (code === 'AI_COST_LIMIT_EXCEEDED') {
      message = 'Достигнут лимит AI на текущий период. Обновите лимиты AI или повторите позже.';
    } else if (code === 'AI_SCHEMA_VALIDATION_FAILED') {
      message = 'Не удалось разобрать ответ AI. Можно повторить запрос.';
    } else if (code === 'AI_ROW_VERSION_CONFLICT') {
      message = 'Данные изменились после подготовки предложения. Обновите предложение.';
    } else if (code === 'AI_SUGGESTION_NOT_FOUND') {
      message = 'AI-предложение не найдено. Обновите AI-результат.';
    } else if (code === 'AI_SUGGESTION_NOT_ACTIONABLE' || code === 'AI_SUGGESTION_STALE') {
      message = 'Предложение устарело или уже применено. Обновите AI-результат.';
    } else if (code === 'VALIDATION_ERROR') {
      message = 'Некоторые поля AI-действия невалидны. Обновите AI-результат.';
    }

    var requestId = String((meta && meta.request_id) || '');
    if (requestId) {
      message += ' (request_id: ' + requestId + ')';
    }

    return {
      code: code,
      message: message,
      retry_after: retryAfter > 0 ? retryAfter : 0,
      retryable: retryable,
      meta: meta,
      provider_error: providerError
    };
  }

  function hasAiPermission(intent) {
    void intent;
    if (!window.CRM.api || typeof window.CRM.api.hasPermission !== 'function') return true;
    return window.CRM.api.hasPermission('ai.use');
  }

  function getIntentUiStateCopy(intentCode) {
    var code = String(intentCode || '').trim();
    if (code && intentUiStateCopy[code]) {
      return {
        empty: String(intentUiStateCopy[code].empty || ''),
        error: String(intentUiStateCopy[code].error || '')
      };
    }
    return {
      empty: 'AI-результат пока не сформирован.',
      error: 'Не удалось выполнить AI-действие.'
    };
  }

  function createTaskSummary(taskPublicId, payload) {
    return requestAi('api/v1/ai/tasks/' + encodeURIComponent(String(taskPublicId || '')) + '/summary', payload || {});
  }

  function previewSuggestion(suggestionPublicId) {
    return requestAi('api/v1/ai/suggestions/' + encodeURIComponent(String(suggestionPublicId || '')) + '/preview-apply', {}, { body: undefined });
  }

  function dismissSuggestion(suggestionPublicId) {
    return requestAi('api/v1/ai/suggestions/' + encodeURIComponent(String(suggestionPublicId || '')) + '/dismiss', {}, { body: undefined });
  }

  function confirmSuggestion(suggestionPublicId, payload) {
    return requestAi('api/v1/ai/suggestions/' + encodeURIComponent(String(suggestionPublicId || '')) + '/confirm', payload || {});
  }

  function createMyDayPlan(payload) {
    return requestAi('api/v1/ai/my-day/plan', payload || {});
  }

  function createMyWeekPlan() {
    return requestAi('api/v1/ai/my-week/plan', {});
  }

  function createTaskListPriority(payload) {
    return requestAi('api/v1/ai/tasks/priority', payload || {});
  }

  function semanticSearch(payload) {
    return requestAi('api/v1/ai/search/semantic', payload || {});
  }

  function createDashboardDigest() {
    return requestAi('api/v1/ai/dashboard/digest', {});
  }

  function applySuggestionAction(action) {
    if (!action || typeof action !== 'object') {
      return Promise.reject(new Error('INVALID_ACTION'));
    }

    var route = String(action.route || '').trim();
    if (!route) {
      return Promise.reject(new Error('ACTION_ROUTE_REQUIRED'));
    }

    var method = String(action.method || 'POST').toUpperCase();
    return request(route, {
      method: method,
      body: action.body || {},
      headers: {
        'X-Idempotency-Key': window.CRM.api.createIdempotencyKey('ai-apply')
      }
    });
  }

  function ensureSuggestionDrawer() {
    var drawer = document.getElementById('aiSuggestionDrawer');
    if (drawer) return drawer;

    document.body.insertAdjacentHTML('beforeend', '\
<div class="offcanvas offcanvas-end crm-drawer-wide" tabindex="-1" id="aiSuggestionDrawer" aria-labelledby="aiSuggestionDrawerTitle">\
  <div class="offcanvas-header">\
    <div>\
      <h5 id="aiSuggestionDrawerTitle" class="mb-1">AI-предложение</h5>\
      <div class="small text-muted" id="aiSuggestionDrawerState">Состояние: idle</div>\
    </div>\
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Закрыть"></button>\
  </div>\
  <div class="offcanvas-body">\
    <div class="alert alert-danger d-none" id="aiSuggestionDrawerError"></div>\
    <section class="mb-3">\
      <div class="small text-muted mb-1">Сводка</div>\
      <div class="crm-metric-tile" id="aiSuggestionDrawerSummary">Выберите AI-предложение.</div>\
    </section>\
    <section class="mb-3">\
      <div class="small text-muted mb-1">Предупреждения</div>\
      <div class="crm-metric-tile" id="aiSuggestionDrawerWarnings">Нет предупреждений.</div>\
    </section>\
    <section class="mb-3">\
      <div class="small text-muted mb-1">Предложенные действия</div>\
      <div class="crm-metric-tile" id="aiSuggestionDrawerActions">Действия отсутствуют.</div>\
    </section>\
    <section class="mb-3">\
      <div class="small text-muted mb-1">Предпросмотр изменений</div>\
      <div id="aiSuggestionDrawerDiff">Нет предпросмотра.</div>\
    </section>\
    <section class="mb-3">\
      <div class="small text-muted mb-1">Источник</div>\
      <div class="crm-metric-tile" id="aiSuggestionDrawerSource">—</div>\
    </section>\
    <div class="d-flex gap-2">\
      <button type="button" class="btn btn-sm crm-btn-primary" id="aiSuggestionDrawerApplyBtn" disabled>Применить выбранное</button>\
      <button type="button" class="btn btn-sm crm-btn-danger" id="aiSuggestionDrawerDismissBtn">Отклонить</button>\
      <button type="button" class="btn btn-sm btn-light" id="aiSuggestionDrawerRefreshBtn">Обновить</button>\
    </div>\
  </div>\
</div>');

    drawer = document.getElementById('aiSuggestionDrawer');
    bindDrawerButtons(drawer);
    return drawer;
  }

  function canOpenAdminAi() {
    return Boolean(window.CRM.api && typeof window.CRM.api.canAccessRoute === 'function' && window.CRM.api.canAccessRoute('admin-ai'));
  }

  function stateLabel(stateCode) {
    var state = String(stateCode || 'idle');
    var map = {
      hidden: 'Состояние: hidden',
      idle: 'Состояние: idle',
      loading: 'Состояние: loading',
      ready: 'Состояние: ready',
      empty: 'Состояние: empty',
      disabled: 'Состояние: disabled',
      provider_missing: 'Состояние: provider_missing',
      rate_limited: 'Состояние: rate_limited',
      error: 'Состояние: error',
      conflict: 'Состояние: conflict',
      applied: 'Состояние: applied',
      partially_applied: 'Состояние: partially_applied',
      dismissed: 'Состояние: dismissed'
    };

    return map[state] || ('Состояние: ' + state);
  }

  function setDrawerState(stateCode, details) {
    var drawer = ensureSuggestionDrawer();
    var stateNode = drawer.querySelector('#aiSuggestionDrawerState');
    var summaryNode = drawer.querySelector('#aiSuggestionDrawerSummary');
    var errorNode = drawer.querySelector('#aiSuggestionDrawerError');
    var applyBtn = drawer.querySelector('#aiSuggestionDrawerApplyBtn');
    var dismissBtn = drawer.querySelector('#aiSuggestionDrawerDismissBtn');
    var refreshBtn = drawer.querySelector('#aiSuggestionDrawerRefreshBtn');

    var state = String(stateCode || 'idle');
    drawer.dataset.aiState = state;
    if (stateNode) {
      stateNode.textContent = stateLabel(state);
    }

    if (errorNode) {
      if (state === 'error' || state === 'provider_missing' || state === 'rate_limited' || state === 'conflict' || state === 'disabled') {
        var message = String((details && details.message) || 'AI-действие завершилось с ошибкой.');
        if ((state === 'provider_missing' || state === 'disabled') && canOpenAdminAi()) {
          errorNode.innerHTML = escapeHtml(message) + '<div class="mt-1"><a href="index.php?route=admin-ai">Открыть AI-настройки</a></div>';
        } else {
          errorNode.textContent = message;
        }
        errorNode.classList.remove('d-none');
      } else {
        errorNode.classList.add('d-none');
        errorNode.textContent = '';
      }
    }

    if (summaryNode && state === 'loading') {
      summaryNode.innerHTML = '<div class="d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Запрос к AI выполняется...</span></div>';
    }

    if (applyBtn) {
      if (state !== 'ready') {
        applyBtn.disabled = true;
      } else if (!drawerBusy && drawerContext.actions.length > 0) {
        applyBtn.disabled = false;
      }
    }

    if (dismissBtn) {
      dismissBtn.disabled = drawerBusy || state === 'loading' || !drawerContext.suggestion || !drawerContext.suggestion.public_id;
    }

    if (refreshBtn) {
      refreshBtn.disabled = drawerBusy || state === 'loading' || !drawerContext.suggestion || !drawerContext.suggestion.public_id;
    }
  }

  function setDrawerBusy(value) {
    drawerBusy = Boolean(value);
    var drawer = ensureSuggestionDrawer();
    var applyBtn = drawer.querySelector('#aiSuggestionDrawerApplyBtn');
    var dismissBtn = drawer.querySelector('#aiSuggestionDrawerDismissBtn');
    var refreshBtn = drawer.querySelector('#aiSuggestionDrawerRefreshBtn');
    var canApply = drawerContext.actions.length > 0;

    if (applyBtn) {
      applyBtn.disabled = drawerBusy || !canApply;
    }
    if (dismissBtn) {
      dismissBtn.disabled = drawerBusy || !drawerContext.suggestion || !drawerContext.suggestion.public_id;
    }
    if (refreshBtn) {
      refreshBtn.disabled = drawerBusy || !drawerContext.suggestion || !drawerContext.suggestion.public_id;
    }
  }

  function suggestionPublicId() {
    return String((drawerContext.suggestion && drawerContext.suggestion.public_id) || '').trim();
  }

  function defaultRefreshHandler() {
    var publicId = suggestionPublicId();
    if (!publicId) {
      return Promise.resolve(null);
    }
    return previewSuggestion(publicId).then(function (envelope) {
      var suggestion = envelope && envelope.data ? envelope.data.suggestion : null;
      var preview = envelope && envelope.data ? envelope.data.preview : null;
      if (suggestion) {
        drawerContext.suggestion = suggestion;
      }
      renderSuggestion(drawerContext.suggestion, preview || null);
      return envelope;
    });
  }

  function defaultDismissHandler() {
    var publicId = suggestionPublicId();
    if (!publicId) {
      return Promise.resolve(null);
    }
    return dismissSuggestion(publicId).then(function (envelope) {
      var suggestion = envelope && envelope.data ? envelope.data.suggestion : null;
      if (suggestion) {
        drawerContext.suggestion = suggestion;
      }
      drawerContext.preview = null;
      renderSuggestion(drawerContext.suggestion, null);
      setDrawerState('dismissed');
      return envelope;
    });
  }

  function runDrawerAction(handler, fallback, fallbackErrorMessage) {
    if (drawerBusy) return;
    setDrawerBusy(true);
    setDrawerState('loading');
    Promise.resolve().then(function () {
      if (typeof handler === 'function') {
        return handler();
      }
      if (typeof fallback === 'function') {
        return fallback();
      }
      return null;
    }).catch(function (error) {
      renderAiError(error, fallbackErrorMessage || 'Не удалось выполнить действие в AI drawer');
    }).finally(function () {
      setDrawerBusy(false);
      var drawer = ensureSuggestionDrawer();
      var currentState = String(drawer && drawer.dataset ? drawer.dataset.aiState : '');
      if (drawerContext.suggestion && currentState === 'loading') {
        setDrawerState('ready');
      }
    });
  }

  function bindDrawerButtons(drawer) {
    if (!drawer || drawer.dataset.bound === '1') return;

    var applyBtn = drawer.querySelector('#aiSuggestionDrawerApplyBtn');
    var dismissBtn = drawer.querySelector('#aiSuggestionDrawerDismissBtn');
    var refreshBtn = drawer.querySelector('#aiSuggestionDrawerRefreshBtn');
    var actionsNode = drawer.querySelector('#aiSuggestionDrawerActions');

    if (actionsNode) {
      actionsNode.addEventListener('change', function (event) {
        var target = event.target;
        if (!target || !target.matches('[data-ai-action-checkbox]')) return;
        if (!applyBtn) return;
        applyBtn.disabled = selectedDrawerActions().length === 0;
      });
    }

    if (applyBtn) {
      applyBtn.addEventListener('click', function () {
        var selected = selectedDrawerActions();
        runDrawerAction(
          function () {
            if (typeof drawerHandlers.onApply === 'function') {
              return drawerHandlers.onApply(selected);
            }
            return null;
          },
          null,
          'Не удалось применить выбранные AI-действия'
        );
      });
    }

    if (dismissBtn) {
      dismissBtn.addEventListener('click', function () {
        runDrawerAction(drawerHandlers.onDismiss, defaultDismissHandler, 'Не удалось отклонить AI-предложение');
      });
    }

    if (refreshBtn) {
      refreshBtn.addEventListener('click', function () {
        runDrawerAction(drawerHandlers.onRefresh, defaultRefreshHandler, 'Не удалось обновить AI-предложение');
      });
    }

    drawer.dataset.bound = '1';
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderList(node, items, fallbackText, formatter) {
    if (!node) return;
    if (!Array.isArray(items) || !items.length) {
      node.innerHTML = '<div class="crm-empty-state"><p class="mb-0">' + escapeHtml(fallbackText || '—') + '</p></div>';
      return;
    }

    node.innerHTML = '<ul class="mb-0 ps-3">' + items.map(function (item) {
      return '<li>' + formatter(item) + '</li>';
    }).join('') + '</ul>';
  }

  function renderActionCheckboxes(node, actions) {
    if (!node) return;
    if (!Array.isArray(actions) || actions.length === 0) {
      node.innerHTML = '<div class="crm-empty-state"><p class="mb-0">Действия отсутствуют.</p></div>';
      return;
    }

    node.innerHTML = actions.map(function (action, index) {
      var label = String(action.label || action.field || action.type || ('action_' + String(index + 1)));
      var value = String(action.value || '');
      var requiresExplicitSelection = Boolean(action && action.raw && action.raw.requires_explicit_selection);
      var warning = action.high_risk ? '<div class="small text-warning">Требуется отдельное подтверждение</div>' : '';
      var explicitSelectionHint = requiresExplicitSelection ? '<div class="small text-warning">Нужно выбрать вручную: будет создана новая business entity</div>' : '';
      return ''
        + '<label class="form-check mb-2">'
        + '<input class="form-check-input" type="checkbox" data-ai-action-checkbox data-ai-action-index="' + String(index) + '"' + (requiresExplicitSelection ? '' : ' checked') + '>'
        + '<span class="form-check-label">'
        + '<strong>' + escapeHtml(label) + '</strong>'
        + (value ? ('<div class="small text-muted">' + escapeHtml(value) + '</div>') : '')
        + warning
        + explicitSelectionHint
        + '</span>'
        + '</label>';
    }).join('');
  }

  function renderDiffPreview(node, actions) {
    if (!node) return;
    if (!Array.isArray(actions) || actions.length === 0) {
      node.innerHTML = '<div class="crm-empty-state"><p class="mb-0">Нет предпросмотра.</p></div>';
      return;
    }

    var riskIcons = {
      low: '<span class="badge bg-success-subtle text-success">Низкий</span>',
      medium: '<span class="badge bg-warning-subtle text-warning">Средний</span>',
      high: '<span class="badge bg-danger-subtle text-danger">Высокий</span>'
    };

    node.innerHTML = '<div class="vstack gap-2">' + actions.map(function (action, index) {
      var label = String(action.label || action.field || action.type || ('Действие ' + String(index + 1)));
      var value = String(action.value || '');
      var riskLevel = action.high_risk ? 'high' : (action.risk_level || 'low');
      var riskBadge = riskIcons[riskLevel] || riskIcons.low;
      var valuePreview = value.length > 200 ? value.slice(0, 200) + '…' : value;

      return '<div class="crm-diff-card">'
        + '<div class="crm-diff-card-header">'
        + '<span class="crm-diff-card-label">' + escapeHtml(label) + '</span>'
        + riskBadge
        + '</div>'
        + (value ? '<div class="crm-diff-card-value">' + escapeHtml(valuePreview) + '</div>' : '')
        + '</div>';
    }).join('') + '</div>';
  }

  function selectedDrawerActions() {
    var drawer = ensureSuggestionDrawer();
    var selectedIndexes = Array.prototype.slice.call(drawer.querySelectorAll('[data-ai-action-checkbox]:checked')).map(function (node) {
      return Number(node.getAttribute('data-ai-action-index'));
    }).filter(function (value) {
      return Number.isFinite(value) && value >= 0;
    });

    return selectedIndexes.map(function (index) {
      return drawerContext.actions[index];
    }).filter(function (item) {
      return !!item;
    });
  }

  function normalizePreviewActions(preview) {
    var changes = preview && Array.isArray(preview.changes) ? preview.changes : [];
    return changes.map(function (change) {
      var type = String(change && change.type || 'change');
      var field = String(change && change.field || type);
      var value = String(change && change.value || '');
      var label = String(change && change.label || field);
      var highRisk = Boolean(change && change.risk_level === 'high');
      return {
        type: type,
        field: field,
        label: label,
        value: value,
        high_risk: highRisk,
        raw: change
      };
    });
  }

  function renderSuggestion(suggestion, preview) {
    var drawer = ensureSuggestionDrawer();
    var summaryNode = drawer.querySelector('#aiSuggestionDrawerSummary');
    var warningsNode = drawer.querySelector('#aiSuggestionDrawerWarnings');
    var actionsNode = drawer.querySelector('#aiSuggestionDrawerActions');
    var diffNode = drawer.querySelector('#aiSuggestionDrawerDiff');
    var sourceNode = drawer.querySelector('#aiSuggestionDrawerSource');
    var applyBtn = drawer.querySelector('#aiSuggestionDrawerApplyBtn');

    drawerContext.suggestion = suggestion || null;
    drawerContext.preview = preview || null;
    drawerContext.actions = normalizePreviewActions(preview);
    setDrawerBusy(false);

    if (!suggestion || typeof suggestion !== 'object') {
      if (summaryNode) summaryNode.textContent = 'AI-предложение не выбрано.';
      if (warningsNode) warningsNode.textContent = 'Нет предупреждений.';
      if (actionsNode) actionsNode.textContent = 'Действия отсутствуют.';
      if (diffNode) diffNode.textContent = 'Предпросмотр пока не загружен.';
      if (sourceNode) sourceNode.textContent = '—';
      if (applyBtn) applyBtn.disabled = true;
      setDrawerState('empty');
      return;
    }

    var payload = suggestion.payload && typeof suggestion.payload === 'object' ? suggestion.payload : {};
    if (summaryNode) {
      summaryNode.innerHTML = '<strong>' + escapeHtml(String(payload.summary || suggestion.summary || 'AI-предложение')) + '</strong>'
        + '<div class="small text-muted mt-1">Статус: ' + escapeHtml(String(suggestion.status || 'draft')) + '</div>';
    }

    var warningItems = [];
    if (Array.isArray(payload.warnings)) {
      warningItems = warningItems.concat(payload.warnings);
    }
    if (Array.isArray(payload.risks)) {
      warningItems = warningItems.concat(payload.risks);
    }
    renderList(warningsNode, warningItems, 'Нет предупреждений.', function (risk) {
      return escapeHtml(String(risk || ''));
    });

    renderActionCheckboxes(actionsNode, drawerContext.actions);
    var cacheMeta = getSuggestionCacheMeta(suggestion);
    var canApply = cacheMeta ? cacheMeta.can_apply !== false : true;
    if (applyBtn) {
      applyBtn.disabled = drawerContext.actions.length === 0 || !canApply;
    }

    renderDiffPreview(diffNode, drawerContext.actions);

    if (sourceNode) {
      sourceNode.innerHTML = ''
        + '<div>intent: ' + escapeHtml(String(suggestion.intent_code || '')) + '</div>'
        + '<div>entity: ' + escapeHtml(String(suggestion.entity_type || '')) + ' / ' + escapeHtml(String(suggestion.entity_public_id || '')) + '</div>'
        + '<div>updated: ' + escapeHtml(String(suggestion.updated_at || suggestion.created_at || '')) + '</div>';
    }

    setDrawerState('ready');
  }

  function mapErrorToState(normalized) {
    var code = String(normalized && normalized.code || 'AI_REQUEST_FAILED');
    if (code === 'AI_PROVIDER_NOT_CONFIGURED') return 'provider_missing';
    if (code === 'AI_RATE_LIMITED') return 'rate_limited';
    if (code === 'AI_DISABLED' || code === 'AI_INTENT_DISABLED' || code === 'AI_FEATURE_DISABLED') return 'disabled';
    if (code === 'AI_ROW_VERSION_CONFLICT') return 'conflict';
    return 'error';
  }

  function getSuggestionCacheMeta(suggestion) {
    if (!suggestion || typeof suggestion !== 'object') return null;
    var payload = suggestion.payload && typeof suggestion.payload === 'object' ? suggestion.payload : null;
    var meta = payload && payload.meta && typeof payload.meta === 'object' ? payload.meta : null;
    var cache = meta && meta.cache && typeof meta.cache === 'object' ? meta.cache : null;
    return cache;
  }

  function canPreviewSuggestion(suggestion) {
    var cache = getSuggestionCacheMeta(suggestion);
    if (!cache) return true;
    return cache.can_apply !== false;
  }

  function suggestionPreviewPolicyMessage(suggestion) {
    if (canPreviewSuggestion(suggestion)) return '';
    var cache = getSuggestionCacheMeta(suggestion) || {};
    if (cache.stale === true || String(cache.status || '') === 'stale' || String(cache.status || '') === 'stale_due_to_ai_error') {
      return 'Предложение устарело. Обновите AI-результат перед предпросмотром.';
    }
    return 'Предпросмотр недоступен для текущего предложения. Обновите AI-результат.';
  }

  function toUiState(errorOrCode, fallbackMessage) {
    if (typeof errorOrCode === 'string') {
      return mapErrorToState({ code: String(errorOrCode || '') });
    }
    if (errorOrCode && typeof errorOrCode === 'object' && typeof errorOrCode.code === 'string') {
      return mapErrorToState(errorOrCode);
    }
    return mapErrorToState(normalizeError(errorOrCode, fallbackMessage));
  }

  function shouldLockAiControls(errorOrCode) {
    var code = '';
    if (typeof errorOrCode === 'string') {
      code = String(errorOrCode || '');
    } else if (errorOrCode && typeof errorOrCode === 'object' && typeof errorOrCode.code === 'string') {
      code = String(errorOrCode.code || '');
    }
    return code === 'AI_PROVIDER_NOT_CONFIGURED'
      || code === 'AI_DISABLED'
      || code === 'AI_FEATURE_DISABLED'
      || code === 'AI_INTENT_DISABLED';
  }

  function applyAiSoftState(options) {
    var opts = options && typeof options === 'object' ? options : {};
    var aiError = opts.aiError || null;
    var controls = Array.isArray(opts.controls) ? opts.controls : [];
    var stateSetter = typeof opts.setState === 'function' ? opts.setState : null;
    var fallbackMessage = String(opts.fallbackMessage || 'AI-действие временно недоступно.');
    if (!shouldLockAiControls(aiError)) return false;
    controls.forEach(function (btn) {
      if (!btn) return;
      btn.disabled = true;
      if (btn.dataset) {
        btn.dataset.hardDisabled = '1';
      }
    });
    if (stateSetter) {
      stateSetter(toUiState(aiError), String((aiError && aiError.message) || fallbackMessage));
    }
    return true;
  }

  function bindAiActionButton(button, options) {
    if (!button) return;
    var opts = options && typeof options === 'object' ? options : {};
    if (button.dataset && button.dataset.bound === '1') return;
    button.addEventListener('click', async function () {
      var isLocked = button.dataset && button.dataset.hardDisabled === '1';
      if (isLocked) return;
      if (button.dataset && button.dataset.loading === '1') return;
      var canRun = typeof opts.canRun === 'function' ? Boolean(opts.canRun()) : true;
      if (!canRun) return;
      if (button.dataset) {
        button.dataset.loading = '1';
      }
      button.disabled = true;
      if (typeof opts.onStart === 'function') {
        opts.onStart();
      }
      try {
        if (typeof opts.run === 'function') {
          await opts.run();
        }
        if (typeof opts.successMessage === 'string' && opts.successMessage) {
          if (typeof window.notify === 'function') {
            window.notify(opts.successMessage);
          }
        }
        if (typeof opts.onSuccess === 'function') {
          opts.onSuccess();
        }
      } catch (error) {
        var fallbackMessage = String(opts.fallbackMessage || 'Не удалось выполнить AI-действие');
        var aiError = normalizeError(error, fallbackMessage);
        if (typeof opts.onError === 'function') {
          opts.onError(aiError, error);
        } else {
          if (typeof opts.setState === 'function') {
            opts.setState(toUiState(aiError), aiError.message || fallbackMessage);
          }
          if (typeof opts.softState === 'function') {
            opts.softState(aiError);
          }
          if (typeof window.notify === 'function') {
            window.notify(aiError.message || fallbackMessage, 'error');
          }
          if (opts.renderDrawerError !== false) {
            renderAiError(error, fallbackMessage);
          }
        }
      } finally {
        if (button.dataset) {
          button.dataset.loading = '0';
        }
        var keepLocked = button.dataset && button.dataset.hardDisabled === '1';
        if (!keepLocked) {
          button.disabled = false;
        }
        if (typeof opts.onFinally === 'function') {
          opts.onFinally();
        }
      }
    });
    if (button.dataset) {
      button.dataset.bound = '1';
    }
  }

  function renderAiError(error, fallbackMessage) {
    var normalized = normalizeError(error, fallbackMessage);
    setDrawerState(mapErrorToState(normalized), { message: normalized.message });
    return normalized;
  }

  function openSuggestionDrawer(suggestion, preview, handlers) {
    drawerHandlers.onApply = handlers && typeof handlers.onApply === 'function' ? handlers.onApply : null;
    drawerHandlers.onDismiss = handlers && typeof handlers.onDismiss === 'function' ? handlers.onDismiss : null;
    drawerHandlers.onRefresh = handlers && typeof handlers.onRefresh === 'function' ? handlers.onRefresh : null;

    var drawer = ensureSuggestionDrawer();
    renderSuggestion(suggestion, preview || null);
    if (window.bootstrap && typeof window.bootstrap.Offcanvas === 'function') {
      window.bootstrap.Offcanvas.getOrCreateInstance(drawer).show();
    }
    return drawer;
  }

  function closeSuggestionDrawer() {
    var drawer = document.getElementById('aiSuggestionDrawer');
    if (!drawer) return;
    if (window.bootstrap && typeof window.bootstrap.Offcanvas === 'function') {
      var instance = window.bootstrap.Offcanvas.getInstance(drawer);
      if (instance) {
        instance.hide();
      }
    }
  }

  return {
    request: request,
    requestAi: requestAi,
    createTaskSummary: createTaskSummary,
    previewSuggestion: previewSuggestion,
    dismissSuggestion: dismissSuggestion,
    confirmSuggestion: confirmSuggestion,
    createMyDayPlan: createMyDayPlan,
    createMyWeekPlan: createMyWeekPlan,
    createTaskListPriority: createTaskListPriority,
    semanticSearch: semanticSearch,
    createDashboardDigest: createDashboardDigest,
    hydrateAvailability: hydrateAvailability,
    isIntentEnabledForUi: isIntentEnabledForUi,
    getIntentAvailability: getIntentAvailability,
    applySuggestionAction: applySuggestionAction,
    openSuggestionDrawer: openSuggestionDrawer,
    closeSuggestionDrawer: closeSuggestionDrawer,
    renderSuggestion: renderSuggestion,
    renderAiError: renderAiError,
    shouldLockAiControls: shouldLockAiControls,
    applyAiSoftState: applyAiSoftState,
    bindAiActionButton: bindAiActionButton,
    hasAiPermission: hasAiPermission,
    toUiError: normalizeError,
    toUiState: toUiState,
    getIntentUiStateCopy: getIntentUiStateCopy,
    setDrawerState: setDrawerState,
    selectedDrawerActions: selectedDrawerActions,
    canPreviewSuggestion: canPreviewSuggestion,
    suggestionPreviewPolicyMessage: suggestionPreviewPolicyMessage
  };
})();
