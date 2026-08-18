window.CRM = window.CRM || {};
window.CRM.ai = (function () {
  function t(key, fallback) {
    if (window.CRM.i18n && typeof window.CRM.i18n.t === 'function') {
      return window.CRM.i18n.t(key, fallback);
    }
    return typeof fallback === 'undefined' ? key : fallback;
  }

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
      empty: t('js.ai.task_summary_empty', 'Task AI summary not generated yet.'),
      error: t('js.ai.task_summary_error', 'Failed to generate task AI summary.')
    },
    'task_decomposition': {
      empty: t('js.ai.task_decomposition_empty', 'Task AI decomposition not generated yet.'),
      error: t('js.ai.task_decomposition_error', 'Failed to generate task AI decomposition.')
    },
    'task_checklist': {
      empty: t('js.ai.task_checklist_empty', 'Task AI checklist not generated yet.'),
      error: t('js.ai.task_checklist_error', 'Failed to generate task AI checklist.')
    },
    'task_quality': {
      empty: t('js.ai.task_quality_empty', 'Task AI quality assessment not generated yet.'),
      error: t('js.ai.task_quality_error', 'Failed to generate task AI quality assessment.')
    },
    'task_next_action': {
      empty: t('js.ai.task_next_action_empty', 'Next step AI recommendation not generated yet.'),
      error: t('js.ai.task_next_action_error', 'Failed to generate next step AI recommendation.')
    },
    'task_comment_draft': {
      empty: t('js.ai.task_comment_draft_empty', 'Comment AI draft not generated yet.'),
      error: t('js.ai.task_comment_draft_error', 'Failed to generate comment AI draft.')
    },
    'project_summary': {
      empty: t('js.ai.project_summary_empty', 'Project AI summary not generated yet.'),
      error: t('js.ai.project_summary_error', 'Failed to generate project AI summary.')
    },
    'project_risk_summary': {
      empty: t('js.ai.project_risk_summary_empty', 'Project AI risk analysis not generated yet.'),
      error: t('js.ai.project_risk_summary_error', 'Failed to generate project AI risk analysis.')
    },
    'project_client_report': {
      empty: t('js.ai.project_client_report_empty', 'Project AI client report not generated yet.'),
      error: t('js.ai.project_client_report_error', 'Failed to generate project AI client report.')
    },
    'client_summary': {
      empty: t('js.ai.client_summary_empty', 'Client AI summary not generated yet.'),
      error: t('js.ai.client_summary_error', 'Failed to generate client AI summary.')
    },
    'client_meeting_prep': {
      empty: t('js.ai.client_meeting_prep_empty', 'Meeting AI preparation not generated yet.'),
      error: t('js.ai.client_meeting_prep_error', 'Failed to generate meeting AI preparation.')
    },
    'client_data_quality': {
      empty: t('js.ai.client_data_quality_empty', 'Client data quality AI check not generated yet.'),
      error: t('js.ai.client_data_quality_error', 'Failed to generate client data quality AI check.')
    },
    'client_safe_report': {
      empty: t('js.ai.client_safe_report_empty', 'AI client-safe report not generated yet.'),
      error: t('js.ai.client_safe_report_error', 'Failed to generate AI client-safe report.')
    },
    'calendar_event_agenda': {
      empty: t('js.ai.calendar_event_agenda_empty', 'AI event agenda not generated yet.'),
      error: t('js.ai.calendar_event_agenda_error', 'Failed to generate AI event agenda.')
    },
    'dashboard_daily_digest': {
      empty: t('js.ai.dashboard_daily_digest_empty', 'Daily AI digest not generated yet.'),
      error: t('js.ai.dashboard_daily_digest_error', 'Failed to generate daily AI digest.')
    },
    'analytics_kpi_explanation': {
      empty: t('js.ai.analytics_kpi_explanation_empty', 'KPI AI explanation not generated yet.'),
      error: t('js.ai.analytics_kpi_explanation_error', 'Failed to generate KPI AI explanation.')
    },
    'analytics_risks_explanation': {
      empty: t('js.ai.analytics_risks_explanation_empty', 'Risk AI explanation not generated yet.'),
      error: t('js.ai.analytics_risks_explanation_error', 'Failed to generate risk AI explanation.')
    },
    'analytics_team_workload_summary': {
      empty: t('js.ai.analytics_team_workload_summary_empty', 'Team workload AI summary not generated yet.'),
      error: t('js.ai.analytics_team_workload_summary_error', 'Failed to generate team workload AI summary.')
    },
    'admin_log_review': {
      empty: t('js.ai.admin_log_review_empty', 'Log AI review not generated yet.'),
      error: t('js.ai.admin_log_review_error', 'Failed to generate log AI review.')
    },
    'webhook_health_review': {
      empty: t('js.ai.webhook_health_review_empty', 'Webhook health AI review not generated yet.'),
      error: t('js.ai.webhook_health_review_error', 'Failed to generate webhook health AI review.')
    },
    'workflow_rule_audit': {
      empty: t('js.ai.workflow_rule_audit_empty', 'Workflow rules AI audit not generated yet.'),
      error: t('js.ai.workflow_rule_audit_error', 'Failed to generate workflow rules AI audit.')
    },
    'my_day_plan': {
      empty: t('js.ai.my_day_plan_empty', 'Day AI plan not generated yet.'),
      error: t('js.ai.my_day_plan_error', 'Failed to generate day AI plan.')
    },
    'my_week_plan': {
      empty: t('js.ai.my_week_plan_empty', 'Week AI plan not generated yet.'),
      error: t('js.ai.my_week_plan_error', 'Failed to generate week AI plan.')
    },
    'task_list_priority': {
      empty: t('js.ai.task_list_priority_empty', 'Task priority AI not generated yet.'),
      error: t('js.ai.task_list_priority_error', 'Failed to generate task priority AI.')
    },
    'daily_work_plan': {
      empty: t('js.ai.daily_work_plan_empty', 'Daily work plan AI not generated yet.'),
      error: t('js.ai.daily_work_plan_error', 'Failed to generate daily work plan AI.')
    },
    'security_log_review': {
      empty: t('js.ai.security_log_review_empty', 'Security log AI review not generated yet.'),
      error: t('js.ai.security_log_review_error', 'Failed to generate security log AI review.')
    },
    'semantic_search': {
      empty: t('js.ai.semantic_search_empty', 'AI semantic search result not generated yet.'),
      error: t('js.ai.semantic_search_error', 'Failed to generate AI semantic search result.')
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
    var error = new Error(String(message || t('js.ai.error_request', 'Failed to execute AI request')));
    error.status = 403;
    error.envelope = {
      success: false,
      code: String(code || 'FORBIDDEN'),
      message: String(message || t('js.ai.error_permission', 'Insufficient permissions')),
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
      window.notify(t('js.ai.notice_context', 'AI uses available CRM context without transmitting passwords, tokens or secrets.'), 'info');
    }
  }

  function requestAi(route, body, options) {
    var opts = options || {};
    var method = String(opts.method || 'POST').toUpperCase();
    var headers = Object.assign({}, opts.headers || {});
    var inferredIntent = inferIntentByRoute(route);

    if (!hasAiPermission(inferredIntent)) {
      return Promise.reject(createApiLikeError('FORBIDDEN', t('js.ai.error_permission_action', 'Insufficient permissions to perform AI action'), {
        reason: 'permission_required',
        intent_code: inferredIntent
      }));
    }

    var ensureAvailability = inferredIntent
      ? hydrateAvailability([inferredIntent]).then(function () {
          var availability = getIntentAvailability(inferredIntent);
          if (!availability.enabled) {
            throw createApiLikeError('AI_INTENT_DISABLED', t('js.ai.error_intent_disabled', 'AI action temporarily unavailable for this role.'), {
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

    function executeWithAvailableSlot(attempt) {
      showAiActionNotice(inferredIntent);
      return request(route, {
        method: method,
        query: opts.query || {},
        headers: headers,
        body: body === undefined ? (opts.body || {}) : body
      }).catch(function (error) {
        var envelope = error && error.envelope ? error.envelope : {};
        var code = String(envelope.code || '');
        var retryAfter = Math.max(1, Math.min(15, Number((envelope.meta || {}).retry_after || 5)));
        if (code !== 'AI_BUSY' || attempt >= 3) {
          throw error;
        }

        // AI work already in progress keeps its PHP-FPM slot. Wait in the
        // browser instead of holding another worker, then make a new safe
        // idempotent request for the free slot.
        headers['X-Idempotency-Key'] = window.CRM.api.createIdempotencyKey('ai-retry');
        return new Promise(function (resolve) {
          setTimeout(resolve, retryAfter * 1000);
        }).then(function () {
          return executeWithAvailableSlot(attempt + 1);
        });
      });
    }

    return ensureAvailability.then(function () {
      return executeWithAvailableSlot(0);
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

    var message = String((envelope && envelope.message) || fallbackMessage || t('js.ai.error_request', 'Failed to execute AI request'));
    if (code === 'AI_DISABLED') {
      message = t('js.ai.error_disabled', 'AI temporarily disabled by administrator.');
    } else if (code === 'AI_PROVIDER_NOT_CONFIGURED') {
      message = t('js.ai.error_not_configured', 'AI not configured by administrator yet.');
      if (window.CRM && window.CRM.api && typeof window.CRM.api.hasPermission === 'function' && window.CRM.api.hasPermission('ai.admin')) {
        message += t('js.ai.error_not_configured_admin_hint', ' Check provider override in intent settings and default provider.');
      }
    } else if (code === 'AI_PROVIDER_TIMEOUT') {
      message = t('js.ai.error_timeout', 'AI provider did not respond in time. Try again.');
    } else if (code === 'AI_PROVIDER_AUTH_FAILED') {
      message = t('js.ai.error_auth_failed', 'AI provider access error. Contact administrator.');
    } else if (code === 'AI_PROVIDER_UNAVAILABLE') {
      message = t('js.ai.error_provider_unavailable', 'AI provider temporarily unavailable. Try again later.');
    } else if (code === 'AI_BUSY') {
      message = retryAfter > 0
        ? (t('js.ai.error_busy', 'AI is processing other requests. Retrying in ') + String(retryAfter) + t('js.ai.error_rate_limit_sec', ' sec.'))
        : t('js.ai.error_busy_generic', 'AI is processing other requests. Please try again shortly.');
    } else if (code === 'AI_RATE_LIMITED') {
      message = retryAfter > 0
        ? (t('js.ai.error_rate_limit', 'AI request limit temporarily exhausted. Retry in ') + String(retryAfter) + t('js.ai.error_rate_limit_sec', ' sec.'))
        : t('js.ai.error_rate_limit_generic', 'AI request limit temporarily exhausted. Try again later.');
    } else if (code === 'AI_COST_LIMIT_EXCEEDED') {
      message = t('js.ai.error_cost_limit', 'AI limit reached for current period. Update AI limits or try again later.');
    } else if (code === 'AI_SCHEMA_VALIDATION_FAILED') {
      message = t('js.ai.error_schema_validation', 'Failed to parse AI response. You can retry the request.');
    } else if (code === 'AI_ROW_VERSION_CONFLICT') {
      message = t('js.ai.error_version_conflict', 'Data changed after suggestion preparation. Refresh the suggestion.');
    } else if (code === 'AI_SUGGESTION_NOT_FOUND') {
      message = t('js.ai.error_suggestion_not_found', 'AI suggestion not found. Refresh the AI result.');
    } else if (code === 'AI_SUGGESTION_NOT_ACTIONABLE' || code === 'AI_SUGGESTION_STALE') {
      message = t('js.ai.error_suggestion_stale', 'Suggestion is outdated or already applied. Refresh the AI result.');
    } else if (code === 'VALIDATION_ERROR') {
      message = t('js.ai.error_validation', 'Some AI action fields are invalid. Refresh the AI result.');
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
      empty: t('js.ai.result_empty', 'AI result not generated yet.'),
      error: t('js.ai.result_error', 'Failed to execute AI action.')
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
      <h5 id="aiSuggestionDrawerTitle" class="mb-1">' + t('js.ai.drawer_title', 'AI Suggestion') + '</h5>\
      <div class="small text-muted" id="aiSuggestionDrawerState">' + t('js.ai.state_idle', 'Status: idle') + '</div>\
    </div>\
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="' + t('js.ai.drawer_close', 'Close') + '"></button>\
  </div>\
  <div class="offcanvas-body">\
    <div class="alert alert-danger d-none" id="aiSuggestionDrawerError"></div>\
    <section class="mb-3">\
      <div class="small text-muted mb-1">' + t('js.ai.summary_heading', 'Summary') + '</div>\
      <div class="crm-metric-tile" id="aiSuggestionDrawerSummary">' + t('js.ai.summary_placeholder', 'Select an AI suggestion.') + '</div>\
    </section>\
    <section class="mb-3">\
      <div class="small text-muted mb-1">' + t('js.ai.warnings_heading', 'Warnings') + '</div>\
      <div class="crm-metric-tile" id="aiSuggestionDrawerWarnings">' + t('js.ai.warnings_placeholder', 'No warnings.') + '</div>\
    </section>\
    <section class="mb-3">\
      <div class="small text-muted mb-1">' + t('js.ai.actions_heading', 'Suggested actions') + '</div>\
      <div class="crm-metric-tile" id="aiSuggestionDrawerActions">' + t('js.ai.actions_placeholder', 'No actions.') + '</div>\
    </section>\
    <section class="mb-3">\
      <div class="small text-muted mb-1">' + t('js.ai.diff_heading', 'Changes preview') + '</div>\
      <div id="aiSuggestionDrawerDiff">' + t('js.ai.diff_placeholder', 'No preview.') + '</div>\
    </section>\
    <section class="mb-3">\
      <div class="small text-muted mb-1">' + t('js.ai.source_heading', 'Source') + '</div>\
      <div class="crm-metric-tile" id="aiSuggestionDrawerSource">—</div>\
    </section>\
    <div class="d-flex gap-2">\
      <button type="button" class="btn btn-sm crm-btn-primary" id="aiSuggestionDrawerApplyBtn" disabled>' + t('js.ai.apply', 'Apply selected') + '</button>\
      <button type="button" class="btn btn-sm crm-btn-danger" id="aiSuggestionDrawerDismissBtn">' + t('js.ai.dismiss', 'Dismiss') + '</button>\
      <button type="button" class="btn btn-sm crm-btn-secondary" id="aiSuggestionDrawerRefreshBtn">' + t('js.ai.refresh', 'Refresh') + '</button>\
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
      hidden: t('js.ai.state_hidden', 'Status: hidden'),
      idle: t('js.ai.state_idle', 'Status: idle'),
      loading: t('js.ai.state_loading', 'Status: loading'),
      ready: t('js.ai.state_ready', 'Status: ready'),
      empty: t('js.ai.state_empty', 'Status: empty'),
      disabled: t('js.ai.state_disabled', 'Status: disabled'),
      provider_missing: t('js.ai.state_provider_missing', 'Status: provider missing'),
      rate_limited: t('js.ai.state_rate_limited', 'Status: rate limited'),
      error: t('js.ai.state_error', 'Status: error'),
      conflict: t('js.ai.state_conflict', 'Status: conflict'),
      applied: t('js.ai.state_applied', 'Status: applied'),
      partially_applied: t('js.ai.state_partially_applied', 'Status: partially applied'),
      dismissed: t('js.ai.state_dismissed', 'Status: dismissed')
    };

    return map[state] || (t('js.ai.state_prefix', 'Status: ') + state);
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
        var message = String((details && details.message) || t('js.ai.error_generic', 'AI action failed with an error.'));
        if ((state === 'provider_missing' || state === 'disabled') && canOpenAdminAi()) {
          errorNode.innerHTML = escapeHtml(message) + '<div class="mt-1"><a href="index.php?route=admin-ai">' + t('js.ai.open_ai_settings', 'Open AI settings') + '</a></div>';
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
      summaryNode.innerHTML = '<div class="d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>' + t('js.ai.loading_message', 'Request to AI in progress...') + '</span></div>';
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
      renderAiError(error, fallbackErrorMessage || t('js.ai.drawer_action_failed', 'Failed to perform action in AI drawer'));
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
          t('js.ai.drawer_apply_failed', 'Failed to apply selected AI actions')
        );
      });
    }

    if (dismissBtn) {
      dismissBtn.addEventListener('click', function () {
        runDrawerAction(drawerHandlers.onDismiss, defaultDismissHandler, t('js.ai.drawer_dismiss_failed', 'Failed to dismiss AI suggestion'));
      });
    }

    if (refreshBtn) {
      refreshBtn.addEventListener('click', function () {
        runDrawerAction(drawerHandlers.onRefresh, defaultRefreshHandler, t('js.ai.drawer_refresh_failed', 'Failed to refresh AI suggestion'));
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
      node.innerHTML = '<div class="crm-empty-state"><p class="mb-0">' + t('js.ai.actions_placeholder', 'No actions.') + '</p></div>';
      return;
    }

    node.innerHTML = actions.map(function (action, index) {
      var label = String(action.label || action.field || action.type || ('action_' + String(index + 1)));
      var value = String(action.value || '');
      var requiresExplicitSelection = Boolean(action && action.raw && action.raw.requires_explicit_selection);
      var warning = action.high_risk ? '<div class="small text-warning">' + t('js.ai.confirm_required', 'Separate confirmation required') + '</div>' : '';
      var explicitSelectionHint = requiresExplicitSelection ? '<div class="small text-warning">' + t('js.ai.manual_selection_needed', 'Must be selected manually: a new business entity will be created') + '</div>' : '';
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
      node.innerHTML = '<div class="crm-empty-state"><p class="mb-0">' + t('js.ai.diff_placeholder', 'No preview.') + '</p></div>';
      return;
    }

    var riskIcons = {
      low: '<span class="badge bg-success-subtle text-success">' + t('js.ai.risk_low', 'Low') + '</span>',
      medium: '<span class="badge bg-warning-subtle text-warning">' + t('js.ai.risk_medium', 'Medium') + '</span>',
      high: '<span class="badge bg-danger-subtle text-danger">' + t('js.ai.risk_high', 'High') + '</span>'
    };

    node.innerHTML = '<div class="vstack gap-2">' + actions.map(function (action, index) {
      var label = String(action.label || action.field || action.type || (t('js.ai.action_label', 'Action ') + String(index + 1)));
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
      if (summaryNode) summaryNode.textContent = t('js.ai.suggestion_not_selected', 'No AI suggestion selected.');
      if (warningsNode) warningsNode.textContent = t('js.ai.warnings_placeholder', 'No warnings.');
      if (actionsNode) actionsNode.textContent = t('js.ai.actions_placeholder', 'No actions.');
      if (diffNode) diffNode.textContent = t('js.ai.preview_not_loaded', 'Preview not loaded yet.');
      if (sourceNode) sourceNode.textContent = '—';
      if (applyBtn) applyBtn.disabled = true;
      setDrawerState('empty');
      return;
    }

    var payload = suggestion.payload && typeof suggestion.payload === 'object' ? suggestion.payload : {};
    if (summaryNode) {
      summaryNode.innerHTML = '<strong>' + escapeHtml(String(payload.summary || suggestion.summary || t('js.ai.drawer_title', 'AI Suggestion'))) + '</strong>'
        + '<div class="small text-muted mt-1">' + t('js.ai.status_label', 'Status: ') + escapeHtml(String(suggestion.status || 'draft')) + '</div>';
    }

    var warningItems = [];
    if (Array.isArray(payload.warnings)) {
      warningItems = warningItems.concat(payload.warnings);
    }
    if (Array.isArray(payload.risks)) {
      warningItems = warningItems.concat(payload.risks);
    }
    renderList(warningsNode, warningItems, t('js.ai.warnings_placeholder', 'No warnings.'), function (risk) {
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
      return t('js.ai.suggestion_stale_preview', 'Suggestion is outdated. Refresh the AI result before preview.');
    }
    return t('js.ai.suggestion_preview_unavailable', 'Preview unavailable for current suggestion. Refresh the AI result.');
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
    var fallbackMessage = String(opts.fallbackMessage || t('js.ai.temporarily_unavailable', 'AI action temporarily unavailable.'));
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
        var fallbackMessage = String(opts.fallbackMessage || t('js.ai.result_error', 'Failed to execute AI action.'));
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
