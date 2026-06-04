window.CRM = window.CRM || {};
window.CRM.br1 = (function () {
  var currentTask = null;
  var currentUserPublicId = '';
  var currentTaskTags = [];
  var currentTaskFiles = [];
  var currentTaskSubtasks = [];
  var currentTaskChecklists = [];
  var checklistActiveEditId = '';
  var checklistDraftState = {};
  var checklistViewAddItemState = {};
  var currentTaskWorklogs = [];
  var worklogAddOpen = false;
  var worklogActiveEditId = '';
  var worklogCreateDraft = null;
  var worklogEditDrafts = {};
  var currentTaskAiSuggestion = null;
  var currentTaskFollowSubscription = null;
  var currentTaskFavorite = null;
  var currentTaskOwnReactionsByComment = {};
  var taskTimerTickIntervalId = null;
  var availableTags = [];
  var availableUsers = [];
  var availableProjects = [];
  var availableClients = [];
  var availableTeams = [];
  var availableTaskStatuses = [];
  var currentProject = null;
  var currentTaskPermissions = {
    canEditIdentity: false,
    canEditWorkflow: false,
    canEditAssignment: false,
    canEditProject: false,
    canEditTags: false,
    canWorkItems: false
  };
  var TASK_TIMER_COOKIE_NAME = 'crm_task_timer_state';
  var AI_INTENT_VISIBILITY_SELECTORS = {
    '#taskAiGenerateBtn': 'task_summary',
    '#taskAiNextActionBtn': 'task_next_action',
    '#taskAiDecomposeBtn': 'task_decomposition',
    '#taskAiChecklistBtn': 'task_checklist',
    '#taskAiImproveDescBtn': 'task_summary',
    '#taskAiCommentDraftBtn': 'task_comment_draft',
    '#taskAiQualityBtn': 'task_quality',
    '#tasksAiPriorityBtn': 'task_list_priority',
    '#dashboardAiDigestRefreshBtn': 'dashboard_daily_digest',
    '#projectAiSummaryBtn': 'project_summary',
    '#projectAiRisksBtn': 'project_risk_summary',
    '#projectAiClientReportBtn': 'project_client_report',
    '#projectAiNextActionsBtn': 'project_summary',
    '#clientAiSummaryBtn': 'client_summary',
    '#clientAiMeetingPrepBtn': 'client_meeting_prep',
    '#clientAiDataQualityBtn': 'client_data_quality',
    '#clientAiSafeReportBtn': 'client_safe_report',
    '#analyticsAiKpiBtn': 'analytics_kpi_explanation',
    '#analyticsAiRisksBtn': 'analytics_risks_explanation',
    '#analyticsAiWorkloadBtn': 'analytics_team_workload_summary',
    '#myDayAiGenerateBtn': 'my_day_plan',
    '#myWeekAiGenerateBtn': 'my_week_plan',
    '#adminAiLogReviewBtn': 'admin_log_review',
    '#adminAiWebhookHealthBtn': 'webhook_health_review',
    '#adminAiWorkflowAuditBtn': 'workflow_rule_audit',
    '#adminAiDailyPlanBtn': 'daily_work_plan',
    '#adminAiSecurityLogBtn': 'security_log_review',
    '[data-calendar-ai-plan-generate-btn]': 'my_day_plan',
    '[data-calendar-ai-generate-btn]': 'calendar_event_agenda'
  };

  function escapeHtml(value) {
    if (window.CRM.text && typeof window.CRM.text.escapeHtml === 'function') {
      return window.CRM.text.escapeHtml(value);
    }
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function sanitizeRichTextHtml(value) {
    var raw = String(value || '').trim();
    if (!raw) return '';
    var template = document.createElement('template');
    template.innerHTML = raw;

    var allowedTags = {
      A: true,
      B: true,
      BLOCKQUOTE: true,
      BR: true,
      CODE: true,
      DIV: true,
      EM: true,
      I: true,
      LI: true,
      OL: true,
      P: true,
      PRE: true,
      S: true,
      SPAN: true,
      STRONG: true,
      U: true,
      UL: true
    };

    function isSafeHref(href) {
      var valueHref = String(href || '').trim().toLowerCase();
      return valueHref === ''
        || valueHref.indexOf('#') === 0
        || valueHref.indexOf('/') === 0
        || valueHref.indexOf('https://') === 0
        || valueHref.indexOf('http://') === 0
        || valueHref.indexOf('mailto:') === 0;
    }

    function sanitizeNode(node) {
      if (!node) return;
      if (node.nodeType === 3) return;
      if (node.nodeType !== 1) {
        node.remove();
        return;
      }

      var tag = String(node.tagName || '').toUpperCase();
      if (!allowedTags[tag]) {
        var parent = node.parentNode;
        if (parent) {
          while (node.firstChild) {
            parent.insertBefore(node.firstChild, node);
          }
          parent.removeChild(node);
        }
        return;
      }

      Array.prototype.slice.call(node.attributes || []).forEach(function (attr) {
        var attrName = String(attr.name || '').toLowerCase();
        if (attrName.indexOf('on') === 0 || attrName === 'style' || attrName === 'id') {
          node.removeAttribute(attr.name);
          return;
        }
        if (tag === 'A' && attrName === 'href') {
          if (!isSafeHref(attr.value || '')) {
            node.removeAttribute(attr.name);
          }
          return;
        }
        if (tag === 'A' && (attrName === 'target' || attrName === 'rel')) {
          return;
        }
        if (attrName === 'class' || attrName.indexOf('data-') === 0) return;
        node.removeAttribute(attr.name);
      });

      if (tag === 'A') {
        var href = String(node.getAttribute('href') || '').trim();
        if (href.indexOf('http://') === 0 || href.indexOf('https://') === 0) {
          node.setAttribute('target', '_blank');
          node.setAttribute('rel', 'noopener noreferrer');
        } else {
          node.removeAttribute('target');
          node.removeAttribute('rel');
        }
      }

      Array.prototype.slice.call(node.childNodes || []).forEach(sanitizeNode);
    }

    Array.prototype.slice.call(template.content.childNodes || []).forEach(sanitizeNode);
    return template.innerHTML;
  }

  function notify(text, type) {
    var toastEl = document.getElementById('toastSuccess');
    if (!toastEl || !window.bootstrap) return;

    toastEl.classList.remove('text-bg-success', 'text-bg-danger', 'text-bg-warning');
    toastEl.classList.add(type === 'error' ? 'text-bg-danger' : type === 'warning' ? 'text-bg-warning' : 'text-bg-success');

    var body = toastEl.querySelector('.toast-body');
    if (body) body.textContent = text;

    window.bootstrap.Toast.getOrCreateInstance(toastEl).show();
  }

  function getCurrentRoute() {
    return new URLSearchParams(window.location.search).get('route') || 'dashboard';
  }

  function getTaskPublicIdFromUrl() {
    return new URLSearchParams(window.location.search).get('task_public_id') || '';
  }

  function getProjectPublicIdFromUrl() {
    return new URLSearchParams(window.location.search).get('project_public_id') || '';
  }

  function withQuery(route, key, value) {
    if (!value) return 'index.php?route=' + encodeURIComponent(route);
    return 'index.php?route=' + encodeURIComponent(route) + '&' + encodeURIComponent(key) + '=' + encodeURIComponent(value);
  }

  function statusBadgeClass(code) {
    if (code === 'done' || code === 'completed') return 'success';
    if (code === 'in_progress' || code === 'active') return 'active';
    if (code === 'blocked') return 'blocked';
    if (code === 'overdue') return 'overdue';
    return 'archived';
  }

  function statusLabel(code) {
    var codeKey = String(code || '');
    if (availableTaskStatuses.length) {
      var dynamicStatus = availableTaskStatuses.find(function (item) {
        return String(item.code || '') === codeKey;
      });
      if (dynamicStatus && dynamicStatus.title) {
        return String(dynamicStatus.title);
      }
    }

    var map = {
      new: 'К выполнению',
      todo: 'К выполнению',
      in_progress: 'В работе',
      active: 'Активный',
      on_hold: 'На паузе',
      blocked: 'Блокировано',
      done: 'Готово',
      completed: 'Готово',
      archived: 'Архив'
    };
    return map[code] || code || 'Без статуса';
  }

  function priorityLabel(code) {
    var map = {
      low: 'Низкий',
      normal: 'Нормальный',
      high: 'Высокий',
      urgent: 'Срочный'
    };
    return map[code] || code || 'Без приоритета';
  }

  function formatDate(dateValue) {
    if (!dateValue) return '—';
    var date = new Date(dateValue);
    if (Number.isNaN(date.getTime())) return String(dateValue);
    return date.toLocaleString('ru-RU', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  function formatForDatetimeLocal(dateValue) {
    if (!dateValue) return '';
    var date = new Date(dateValue);
    if (Number.isNaN(date.getTime())) return '';
    var pad = function (n) { return String(n).padStart(2, '0'); };
    return date.getFullYear()
      + '-' + pad(date.getMonth() + 1)
      + '-' + pad(date.getDate())
      + 'T' + pad(date.getHours())
      + ':' + pad(date.getMinutes());
  }

  function activityActorLabel(item) {
    if (!item || typeof item !== 'object') return 'Система';
    var explicitName = String(
      item.actor_name
      || item.user_full_name
      || item.user_name
      || item.user_login
      || item.author_name
      || item.login
      || ''
    ).trim();
    if (explicitName && !/^usr_/i.test(explicitName)) {
      return explicitName;
    }

    var actorPublicId = String(
      item.user_public_id
      || item.actor_public_id
      || item.author_public_id
      || explicitName
      || ''
    ).trim();
    if (actorPublicId) {
      var resolvedName = resolveUserNameByPublicId(actorPublicId);
      return resolvedName || actorPublicId;
    }

    return 'Система';
  }

  function activityDetailLabel(item) {
    if (!item || typeof item !== 'object') return '';
    if (item.request_route) {
      return (item.method ? item.method + ' ' : '') + item.request_route;
    }
    if (item.details && typeof item.details === 'object') {
      return item.details.route || item.details.entity_public_id || item.details.user_public_id || item.details.diff || '';
    }
    return item.entity_public_id || '';
  }

  function extractTaskPayload(envelope) {
    if (!envelope || typeof envelope !== 'object') return null;
    var data = envelope.data;
    if (data && typeof data === 'object' && data.task && typeof data.task === 'object') {
      return data.task;
    }
    if (data && typeof data === 'object' && (data.public_id || data.title || data.status_code || data.creator_user_public_id)) {
      return data;
    }
    return null;
  }

  function mergeTaskState(nextTask) {
    if (!nextTask || typeof nextTask !== 'object') return currentTask;
    if (!currentTask || typeof currentTask !== 'object') {
      return Object.assign({}, nextTask);
    }
    var merged = Object.assign({}, currentTask);
    Object.keys(nextTask).forEach(function (key) {
      merged[key] = nextTask[key];
    });
    return merged;
  }

  function getCurrentUserPublicId() {
    var user = window.CRM.api.getUser();
    return user && user.public_id ? String(user.public_id) : '';
  }

  function resolveUserNameByPublicId(publicId) {
    var id = String(publicId || '').trim();
    if (!id) return '';
    if (currentUserPublicId && id === currentUserPublicId && window.CRM.api && typeof window.CRM.api.getUser === 'function') {
      var me = window.CRM.api.getUser();
      if (me && (me.full_name || me.login)) {
        return String(me.full_name || me.login);
      }
    }
    if (Array.isArray(availableUsers)) {
      var found = availableUsers.find(function (u) { return String(u.public_id || '') === id; });
      if (found) {
        return String(found.full_name || found.login || found.public_id || '');
      }
    }
    return '';
  }

  function resolveUserDisplayName(nameCandidate, publicId, fallbackLabel) {
    var explicit = String(nameCandidate || '').trim();
    if (explicit && !/^usr_/i.test(explicit)) {
      return explicit;
    }
    var resolved = resolveUserNameByPublicId(publicId);
    if (resolved) {
      return resolved;
    }
    if (explicit) {
      return explicit;
    }
    return String(fallbackLabel || '—');
  }

  function hasPermission(code) {
    return !window.CRM.api
      || typeof window.CRM.api.hasPermission !== 'function'
      || window.CRM.api.hasPermission(code);
  }

  function hasAnyPermission(codes) {
    return !window.CRM.api
      || typeof window.CRM.api.hasAnyPermission !== 'function'
      || window.CRM.api.hasAnyPermission(codes);
  }

  function hasAiAdminAccess() {
    if (!window.CRM.api || typeof window.CRM.api.getUser !== 'function') return true;
    var user = window.CRM.api.getUser();
    if (!user || typeof user !== 'object') return false;
    if (Boolean(user.is_root)) return true;
    var roles = Array.isArray(user.roles) ? user.roles : [];
    var isAdminRole = roles.some(function (role) {
      return String(role || '').toLowerCase() === 'admin';
    });
    if (isAdminRole) return true;
    return hasPermission('ai.admin');
  }

  function fallbackRouteForCurrentUser() {
    var candidates = ['dashboard', 'tasks', 'projects', 'notifications', 'profile', 'docs'];
    for (var i = 0; i < candidates.length; i += 1) {
      if (!window.CRM.api || typeof window.CRM.api.canAccessRoute !== 'function' || window.CRM.api.canAccessRoute(candidates[i])) {
        return candidates[i];
      }
    }
    return 'profile';
  }

  function enforceRoutePermission() {
    var route = getCurrentRoute();
    if (route === 'login') return true;
    if (!window.CRM.api || typeof window.CRM.api.canAccessRoute !== 'function') return true;
    if (window.CRM.api.canAccessRoute(route)) return true;

    window.location.href = withQuery(fallbackRouteForCurrentUser());
    return false;
  }

  function removeImpersonationBanner() {
    var existing = document.getElementById('globalImpersonationBanner');
    if (existing) {
      existing.remove();
    }
  }

  async function restoreAfterImpersonationStop() {
    var originalToken = window.CRM.api && typeof window.CRM.api.restoreOriginalSessionAfterImpersonation === 'function'
      ? window.CRM.api.restoreOriginalSessionAfterImpersonation()
      : '';
    if (originalToken) {
      try {
        await window.CRM.api.me();
      } catch (error) {
        window.CRM.api.clearAuth();
      }
    } else if (window.CRM.api && typeof window.CRM.api.clearAuth === 'function') {
      window.CRM.api.clearAuth();
    }
  }

  async function renderImpersonationBanner() {
    if (!window.CRM.api || typeof window.CRM.api.getActiveImpersonation !== 'function') {
      removeImpersonationBanner();
      return;
    }
    var localState = window.CRM.api.getActiveImpersonation();
    if (!localState || !localState.active) {
      removeImpersonationBanner();
      return;
    }

    var status = null;
    try {
      status = await window.CRM.api.request('api/v1/security/impersonation/status', {
        method: 'GET',
        timeoutMs: 7000
      });
    } catch (error) {
      await restoreAfterImpersonationStop();
      removeImpersonationBanner();
      return;
    }

    var current = status && status.data && status.data.current ? status.data.current : {};
    if (!current.active) {
      await restoreAfterImpersonationStop();
      removeImpersonationBanner();
      return;
    }

    var audit = current.audit && typeof current.audit === 'object' ? current.audit : {};
    var targetLabel = localState.target_label || audit.target_login || audit.target_user_public_id || 'пользователь';
    var originalLabel = audit.admin_login || audit.admin_user_public_id || 'администратор';
    var existing = document.getElementById('globalImpersonationBanner');
    var banner = existing || document.createElement('div');
    banner.id = 'globalImpersonationBanner';
    banner.className = 'crm-impersonation-banner';
    banner.setAttribute('role', 'status');
    banner.innerHTML = ''
      + '<div><strong>Вход как пользователь:</strong> ' + escapeHtml(targetLabel)
      + '<span class="text-muted ms-2">Исходная сессия: ' + escapeHtml(originalLabel) + '</span></div>'
      + '<button class="btn btn-sm btn-danger" type="button" id="globalStopImpersonationBtn">Вернуться</button>';

    if (!existing) {
      var content = document.querySelector('.crm-content');
      if (content) {
        content.insertBefore(banner, content.firstChild);
      } else {
        document.body.insertBefore(banner, document.body.firstChild);
      }
    }

    var stopBtn = document.getElementById('globalStopImpersonationBtn');
    if (stopBtn && stopBtn.dataset.bound !== '1') {
      stopBtn.addEventListener('click', async function () {
        stopBtn.disabled = true;
        try {
          await window.CRM.api.request('api/v1/security/impersonation/stop', { method: 'POST' });
        } catch (error) {
          // Even if the temporary token is already revoked, try to restore the original session below.
        }
        await restoreAfterImpersonationStop();
        window.location.href = withQuery('admin-users');
      });
      stopBtn.dataset.bound = '1';
    }
  }

  function hideOrDisableElement(el) {
    if (!el) return;
    if (el.tagName === 'BUTTON' || el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') {
      el.disabled = true;
    }
    if (el.classList.contains('modal')) {
      el.classList.add('d-none');
    } else {
      el.style.display = 'none';
    }
    el.setAttribute('aria-hidden', 'true');
    el.setAttribute('data-permission-hidden', '1');
  }

  function restorePermissionHiddenElement(el) {
    if (!el || el.getAttribute('data-permission-hidden') !== '1') return;

    if (el.tagName === 'BUTTON' || el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') {
      el.disabled = false;
    }
    if (el.classList.contains('modal')) {
      el.classList.remove('d-none');
    } else {
      el.style.display = '';
    }
    el.removeAttribute('data-permission-hidden');
    el.removeAttribute('aria-hidden');
  }

  function aiIntentDisabledLabel(reason) {
    var code = String(reason || '').trim();
    if (code === 'ai_disabled') return 'AI выключен в feature flags.';
    if (code === 'provider_missing') return 'AI-провайдер или secret еще не настроен.';
    if (code === 'intent_disabled') return 'AI intent выключен в настройках.';
    if (code === 'feature_disabled') return 'Доменный AI feature flag выключен.';
    if (code === 'permission_required') return 'Недостаточно прав для этого AI-действия.';
    return 'AI-действие сейчас недоступно.';
  }

  function hideByIntentElement(el, reason) {
    if (!el) return;
    if (String(reason || '').trim() === 'permission_required') {
      if (el.classList.contains('modal')) {
        el.classList.add('d-none');
      } else {
        el.style.display = 'none';
      }
      el.setAttribute('aria-hidden', 'true');
      el.setAttribute('data-ai-intent-hidden', '1');
      el.removeAttribute('data-ai-intent-disabled');
      return;
    }
    if (el.tagName === 'BUTTON' || el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') {
      if (!el.hasAttribute('data-ai-original-title')) {
        el.setAttribute('data-ai-original-title', el.getAttribute('title') || '');
      }
      el.disabled = true;
      el.title = aiIntentDisabledLabel(reason);
      el.setAttribute('aria-disabled', 'true');
      el.setAttribute('data-ai-intent-disabled', '1');
      el.removeAttribute('data-ai-intent-hidden');
      el.removeAttribute('aria-hidden');
      return;
    }
    if (el.classList.contains('modal')) {
      el.classList.add('d-none');
    } else {
      el.style.display = 'none';
    }
    el.setAttribute('aria-hidden', 'true');
    el.setAttribute('data-ai-intent-hidden', '1');
  }

  function restoreIntentHiddenElement(el) {
    if (!el || (el.getAttribute('data-ai-intent-hidden') !== '1' && el.getAttribute('data-ai-intent-disabled') !== '1')) return;
    if (el.getAttribute('data-permission-hidden') === '1') return;

    if (el.tagName === 'BUTTON' || el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') {
      el.disabled = false;
      el.removeAttribute('aria-disabled');
      if (el.hasAttribute('data-ai-original-title')) {
        var originalTitle = el.getAttribute('data-ai-original-title') || '';
        if (originalTitle) {
          el.setAttribute('title', originalTitle);
        } else {
          el.removeAttribute('title');
        }
        el.removeAttribute('data-ai-original-title');
      }
    }
    if (el.classList.contains('modal')) {
      el.classList.remove('d-none');
    } else {
      el.style.display = '';
    }
    el.removeAttribute('data-ai-intent-hidden');
    el.removeAttribute('data-ai-intent-disabled');
    el.removeAttribute('aria-hidden');
  }

  function hideOrDisable(selector) {
    document.querySelectorAll(selector).forEach(hideOrDisableElement);
  }

  function restorePermissionHidden(selector) {
    document.querySelectorAll(selector).forEach(restorePermissionHiddenElement);
  }

  function applyPermissionVisibility() {
    if (!window.CRM.api || document.body.dataset.protected !== '1') return;

    var rules = [
      {
        permission: 'task.manage',
        selectors: [
          '[data-open-modal="createTaskModal"]',
          '[data-open-modal="assignUserModal"]',
          '[data-open-modal="calendarEventModal"]',
          '[data-confirm-delete]',
          '#taskEditBtn',
          '#projectCreateTaskBtn',
          '#bulkActionsBar',
          '#subtaskCreateForm',
          '#checklistCreateForm',
          '#worklogCreateForm',
          '#commentForm',
          '#taskFileUploadBtn',
          '#statusCreateOpenBtn',
          '#statusCreateForm',
          '#statusEditForm',
          '[data-status-edit]',
          '[data-status-delete]'
        ]
      },
      {
        permission: 'project.manage',
        selectors: [
          '[data-open-modal="createProjectModal"]',
          '[data-project-edit-toggle]',
          '[data-project-manage-form]'
        ]
      },
      {
        permission: 'team.manage',
        selectors: [
          '[data-bs-target="#teamCreateModal"]',
          '#teamCreateForm',
          '#teamEditForm',
          '[data-team-edit]',
          '[data-team-delete]'
        ]
      },
      {
        permission: 'user.manage',
        selectors: [
          '[data-bs-target="#userCreateModal"]',
          '#adminUserCreateForm',
          '#adminUserEditForm',
          '[data-user-edit]',
          '[data-user-delete]'
        ]
      },
      {
        permission: 'role.manage',
        selectors: [
          '#roleCreateOpenBtn',
          '#roleCreateForm',
          '#roleEditForm',
          '#roleDeleteConfirmBtn',
          '[data-role-edit]',
          '[data-role-delete]'
        ]
      },
      {
        permission: 'api_client.manage',
        selectors: [
          '#apcCreateForm',
          '#apcEditForm',
          '#apcIssueKeyForm',
          '#apcRotateKeyBtn',
          '#apcRevokeKeyBtn'
        ]
      },
      {
        permission: 'webhook.manage',
        selectors: [
          '#whCreateForm',
          '#whEditForm',
          '#whTestBtn',
          '#whDeleteBtn'
        ]
      },
      {
        permissionAny: ['api_client.view', 'api_client.manage'],
        selectors: [
          '#apcClientsTableBody',
          '#apcKeysTableBody'
        ]
      },
      {
        permission: 'webhook.manage',
        selectors: [
          '#whTableBody',
          '#whDeliveriesBody'
        ]
      },
      {
        allow: hasAiAdminAccess,
        selectors: [
          '[data-requires-ai-admin]'
        ]
      },
      {
        permission: 'ai.use',
        selectors: [
          '[data-requires-ai-use]'
        ]
      },
      {
        permission: 'feature_flag.manage',
        selectors: [
          '[data-requires-feature-flag-manage]'
        ]
      }
    ];

    rules.forEach(function (rule) {
      var allowed = true;
      if (rule.permission) {
        allowed = hasPermission(rule.permission);
      } else if (rule.permissionAny) {
        allowed = hasAnyPermission(rule.permissionAny);
      } else if (typeof rule.allow === 'function') {
        allowed = Boolean(rule.allow());
      }
      rule.selectors.forEach(allowed ? restorePermissionHidden : hideOrDisable);
    });

    var aiClient = window.CRM.ai || null;
    var canCheckIntent = Boolean(aiClient && typeof aiClient.isIntentEnabledForUi === 'function');
    if (!canCheckIntent) return;

    Object.keys(AI_INTENT_VISIBILITY_SELECTORS).forEach(function (selector) {
      var intentCode = AI_INTENT_VISIBILITY_SELECTORS[selector];
      document.querySelectorAll(selector).forEach(function (node) {
        var availability = aiClient && typeof aiClient.getIntentAvailability === 'function'
          ? aiClient.getIntentAvailability(intentCode)
          : { enabled: aiClient.isIntentEnabledForUi(intentCode), reason: '' };
        var enabled = Boolean(availability.enabled);
        if (enabled) {
          restoreIntentHiddenElement(node);
          return;
        }
        hideByIntentElement(node, availability.reason);
      });
    });
  }

  function setSessionUiUser(user) {
    var fullName = user && (user.full_name || user.login) ? (user.full_name || user.login) : 'Гость';
    var publicId = user && user.public_id ? user.public_id : '—';

    document.querySelectorAll('[data-session-user]').forEach(function (el) {
      el.textContent = fullName;
    });

    document.querySelectorAll('[data-session-public-id]').forEach(function (el) {
      el.textContent = publicId;
    });

    document.querySelectorAll('[data-session-user-btn], .crm-topbar [data-global-actions] .dropdown .dropdown-toggle, .crm-btn-ghost.dropdown-toggle').forEach(function (el) {
      if (el.textContent && el.textContent.trim()) {
        el.textContent = fullName;
      }
    });
  }

  // Persistent logger that sends logs to server file
  function plog(msg) {
    var entry = { t: Date.now(), m: msg };
    console.log('[BR1]', msg);
    // Send to server for file logging
    try {
      navigator.sendBeacon(
        '/api/index.php?route=api/v1/telemetry/login-debug',
        new Blob([JSON.stringify(entry)], { type: 'application/json' })
      );
    } catch(e) {}
    // Also store in localStorage as backup
    try {
      var logs = JSON.parse(localStorage.getItem('crm_login_debug') || '[]');
      logs.push(entry);
      if (logs.length > 50) logs = logs.slice(-50);
      localStorage.setItem('crm_login_debug', JSON.stringify(logs));
    } catch(e) {}
  }

  function showLoginError(message) {
    var errorNode = document.getElementById('loginError');
    if (!errorNode) return;
    errorNode.classList.remove('d-none');
    errorNode.textContent = String(message || 'Ошибка входа');
  }

  async function initLoginFlow() {
    plog('initLoginFlow called');
    var loginForm = document.getElementById('loginForm');
    if (!loginForm) {
      plog('loginForm not found');
      return;
    }
    if (loginForm.dataset.crmLoginBound === '1') {
      plog('loginForm already bound');
      return;
    }
    plog('loginForm found, binding handlers');
    var loginInput = loginForm.querySelector('[name="login"]') || loginForm.querySelector('[name="email"]');

    var localeSelect = loginForm.querySelector('[name="locale"]');
    if (localeSelect && window.CRM.api && typeof window.CRM.api.getPreferredLocale === 'function') {
      localeSelect.value = window.CRM.api.getPreferredLocale();
      localeSelect.addEventListener('change', function () {
        if (typeof window.CRM.api.setPreferredLocale === 'function') {
          window.CRM.api.setPreferredLocale(localeSelect.value);
        }
      });
    }

    var submitBtn = loginForm.querySelector('button[type="submit"]') || loginForm.querySelector('button[type="button"]');
    plog('submitBtn found: ' + (submitBtn ? 'yes id=' + (submitBtn.id || 'none') : 'NO'));

    async function handleLogin(e) {
      plog('handleLogin called, event type: ' + (e ? e.type : 'direct'));
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      plog('checking CRM.api');
      if (!window.CRM.api || typeof window.CRM.api.login !== 'function') {
        plog('CRM.api.login not available');
        showLoginError('Не удалось инициализировать модуль авторизации. Обновите страницу (Ctrl+F5).');
        return;
      }
      plog('CRM.api.login available');

      var passInput = loginForm.querySelector('[name="password"]');

      var login = loginInput ? loginInput.value.trim() : '';
      var password = passInput ? passInput.value.trim() : '';
      var locale = localeSelect ? String(localeSelect.value || '').trim().toLowerCase() : '';
      plog('login: ' + login + ' password length: ' + password.length);

      if (!login || !password) {
        plog('login or password empty');
        showLoginError('Введите логин и пароль.');
        return;
      }
      try {
        plog('calling CRM.api.login...');
        await window.CRM.api.login(login, password, locale);
        plog('login successful, calling CRM.api.me...');
        await window.CRM.api.me();
        plog('me() successful, redirecting to dashboard');
        notify('Вход выполнен');
        var query = new URLSearchParams(window.location.search);
        var returnRoute = query.get('return_route') || query.get('redirect');
        window.location.href = withQuery(returnRoute || 'dashboard');
      } catch (error) {
        plog('login error: ' + (error && error.message ? error.message : String(error)));
        var normalized = window.CRM.api && typeof window.CRM.api.normalizeError === 'function'
          ? window.CRM.api.normalizeError(error, 'Ошибка входа')
          : { message: 'Ошибка входа', fieldErrors: {} };
        var message = window.CRM.api && typeof window.CRM.api.formatErrorMessage === 'function'
          ? window.CRM.api.formatErrorMessage(normalized, { withRequestId: normalized.isServerError })
          : normalized.message;
        var authErrors = normalized.fieldErrors && Array.isArray(normalized.fieldErrors.auth) ? normalized.fieldErrors.auth : [];
        var details = authErrors.length ? ' (' + authErrors.join(', ') + ')' : '';
        showLoginError(message + details);
      }
    }

    // Bind click handler on submit button (most reliable)
    if (submitBtn) {
      submitBtn.addEventListener('click', handleLogin, true);
      submitBtn.addEventListener('mousedown', function(e) {
        plog('submit button mousedown');
      });
      plog('button click handler bound');
    }

    // Expose as global for inline onsubmit handler
    window.__handleLogin = handleLogin;

    // Also bind form submit as backup
    loginForm.addEventListener('submit', handleLogin, true);
    plog('form submit handler bound + window.__handleLogin exposed');
    loginForm.dataset.crmLoginBound = '1';
  }

  function showFormAlert(id, message, type) {
    var node = document.getElementById(id);
    if (!node) return;
    node.classList.remove('d-none', 'alert-danger', 'alert-success', 'alert-warning');
    node.classList.add(type === 'error' ? 'alert-danger' : (type === 'warning' ? 'alert-warning' : 'alert-success'));
    node.textContent = String(message || '');
  }

  function hideFormAlert(id) {
    var node = document.getElementById(id);
    if (!node) return;
    node.classList.add('d-none');
    node.textContent = '';
  }

  function readTokenFromQuery(name) {
    var params = new URLSearchParams(window.location.search || '');
    var value = String(params.get(name) || '').trim();
    if (!value) return '';
    params.delete(name);
    var route = params.get('route') || '';
    if (!route) params.set('route', getCurrentRoute());
    var nextQuery = params.toString();
    var nextUrl = window.location.pathname + (nextQuery ? ('?' + nextQuery) : '');
    if (window.history && typeof window.history.replaceState === 'function') {
      window.history.replaceState({}, document.title, nextUrl);
    }
    return value;
  }

  async function initLoginFlow() {
    console.log('[BR1] initLoginFlow called');
    var loginForm = document.getElementById('loginForm');
    if (!loginForm) {
      console.log('[BR1] loginForm not found');
      return;
    }
    if (loginForm.dataset.crmLoginBound === '1') {
      console.log('[BR1] loginForm already bound');
      return;
    }
    console.log('[BR1] loginForm found, binding handlers');
    var loginInput = loginForm.querySelector('[name="login"]') || loginForm.querySelector('[name="email"]');

    var localeSelect = loginForm.querySelector('[name="locale"]');
    if (localeSelect && window.CRM.api && typeof window.CRM.api.getPreferredLocale === 'function') {
      localeSelect.value = window.CRM.api.getPreferredLocale();
      localeSelect.addEventListener('change', function () {
        if (typeof window.CRM.api.setPreferredLocale === 'function') {
          window.CRM.api.setPreferredLocale(localeSelect.value);
        }
      });
    }

    var submitBtn = loginForm.querySelector('button[type="submit"]') || loginForm.querySelector('button[type="button"]') || loginForm.querySelector('button');

    async function handleLogin(e) {
      console.log('[BR1] handleLogin called, event type:', e ? e.type : 'direct');
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      console.log('[BR1] checking CRM.api');
      if (!window.CRM.api || typeof window.CRM.api.login !== 'function') {
        console.log('[BR1] CRM.api.login not available');
        showLoginError('Не удалось инициализировать модуль авторизации. Обновите страницу (Ctrl+F5).');
        return;
      }
      console.log('[BR1] CRM.api.login available');

      var passInput = loginForm.querySelector('[name="password"]');

      var login = loginInput ? loginInput.value.trim() : '';
      var password = passInput ? passInput.value.trim() : '';
      var locale = localeSelect ? String(localeSelect.value || '').trim().toLowerCase() : '';

      if (!login || !password) {
        console.log('[BR1] credentials missing');
        showLoginError('Введите логин и пароль.');
        return;
      }
      try {
        console.log('[BR1] calling CRM.api.login...');
        await window.CRM.api.login(login, password, locale);
        console.log('[BR1] login successful, calling CRM.api.me...');
        await window.CRM.api.me();
        console.log('[BR1] me() successful, redirecting');
        notify('Вход выполнен');

        var query = new URLSearchParams(window.location.search);
        var returnRoute = query.get('return_route') || query.get('redirect');
        window.location.href = withQuery(returnRoute || 'dashboard');
      } catch (error) {
        console.log('[BR1] login error:', error);
        var normalized = window.CRM.api && typeof window.CRM.api.normalizeError === 'function'
          ? window.CRM.api.normalizeError(error, 'Ошибка входа')
          : { message: 'Ошибка входа', fieldErrors: {} };
        var message = window.CRM.api && typeof window.CRM.api.formatErrorMessage === 'function'
          ? window.CRM.api.formatErrorMessage(normalized, { withRequestId: normalized.isServerError })
          : normalized.message;
        var authErrors = normalized.fieldErrors && Array.isArray(normalized.fieldErrors.auth) ? normalized.fieldErrors.auth : [];
        var details = authErrors.length ? ' (' + authErrors.join(', ') + ')' : '';
        showLoginError(message + details);
      }
    }

    // Bind click handler on submit button (most reliable)
    if (submitBtn) {
      submitBtn.addEventListener('click', handleLogin, true);
      submitBtn.addEventListener('mousedown', function(e) {
        console.log('[BR1] submit button mousedown');
      });
      console.log('[BR1] button click handler bound');
    }

    // Also bind form submit as backup
    loginForm.addEventListener('submit', handleLogin, true);
    console.log('[BR1] form submit handler bound');
    loginForm.dataset.crmLoginBound = '1';
  }

  function initPasswordResetRequestFlow() {
    var form = document.getElementById('passwordResetRequestForm');
    if (!form || !window.CRM.api) return;
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      hideFormAlert('passwordResetRequestError');
      hideFormAlert('passwordResetRequestSuccess');
      var identifierInput = form.querySelector('[name="identifier"]');
      var identifier = identifierInput ? String(identifierInput.value || '').trim() : '';
      if (!identifier) {
        showFormAlert('passwordResetRequestError', 'Укажите логин или email.', 'error');
        return;
      }
      try {
        await window.CRM.api.request('api/v1/security/password-reset', {
          method: 'POST',
          auth: false,
          body: { identifier: identifier }
        });
        showFormAlert('passwordResetRequestSuccess', 'Запрос принят. Если пользователь существует, сброс будет обработан.', 'success');
      } catch (error) {
        var normalized = window.CRM.api.normalizeError(error, 'Не удалось отправить запрос');
        showFormAlert('passwordResetRequestError', window.CRM.api.formatErrorMessage(normalized), 'error');
      }
    });
  }

  function initPasswordResetConfirmFlow() {
    var form = document.getElementById('passwordResetConfirmForm');
    if (!form || !window.CRM.api) return;
    var tokenInput = form.querySelector('[name="reset_token"]');
    if (tokenInput && !tokenInput.value) {
      tokenInput.value = readTokenFromQuery('token') || readTokenFromQuery('reset_token');
    }
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      hideFormAlert('passwordResetConfirmError');
      hideFormAlert('passwordResetConfirmSuccess');
      var resetToken = tokenInput ? String(tokenInput.value || '').trim() : '';
      var passwordInput = form.querySelector('[name="new_password"]');
      var password = passwordInput ? String(passwordInput.value || '') : '';
      if (!resetToken || !password) {
        showFormAlert('passwordResetConfirmError', 'Заполните токен и новый пароль.', 'error');
        return;
      }
      if (password.length < 8) {
        showFormAlert('passwordResetConfirmError', 'Новый пароль должен содержать минимум 8 символов.', 'error');
        return;
      }
      try {
        await window.CRM.api.request('api/v1/security/password-reset/confirm', {
          method: 'POST',
          auth: false,
          body: { reset_token: resetToken, new_password: password }
        });
        showFormAlert('passwordResetConfirmSuccess', 'Пароль обновлен. Теперь вы можете войти в систему.', 'success');
      } catch (error) {
        var normalized = window.CRM.api.normalizeError(error, 'Не удалось выполнить сброс пароля');
        showFormAlert('passwordResetConfirmError', window.CRM.api.formatErrorMessage(normalized), 'error');
      }
    });
  }

  function initInvitationAcceptFlow() {
    var form = document.getElementById('invitationAcceptForm');
    if (!form || !window.CRM.api) return;
    var tokenInput = form.querySelector('[name="invitation_token"]');
    if (tokenInput && !tokenInput.value) {
      tokenInput.value = readTokenFromQuery('token') || readTokenFromQuery('invitation_token');
    }
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      hideFormAlert('invitationAcceptError');
      hideFormAlert('invitationAcceptSuccess');
      var body = {
        invitation_token: tokenInput ? String(tokenInput.value || '').trim() : '',
        login: String(((form.querySelector('[name="login"]') || {}).value) || '').trim(),
        full_name: String(((form.querySelector('[name="full_name"]') || {}).value) || '').trim(),
        password: String(((form.querySelector('[name="password"]') || {}).value) || '')
      };
      if (!body.invitation_token || !body.login || !body.full_name || !body.password) {
        showFormAlert('invitationAcceptError', 'Заполните все обязательные поля.', 'error');
        return;
      }
      if (body.password.length < 8) {
        showFormAlert('invitationAcceptError', 'Пароль должен содержать минимум 8 символов.', 'error');
        return;
      }
      try {
        await window.CRM.api.request('api/v1/security/invitations/accept', {
          method: 'POST',
          auth: false,
          body: body
        });
        showFormAlert('invitationAcceptSuccess', 'Приглашение принято. Войдите в систему под новым логином.', 'success');
      } catch (error) {
        var normalized = window.CRM.api.normalizeError(error, 'Не удалось принять приглашение');
        showFormAlert('invitationAcceptError', window.CRM.api.formatErrorMessage(normalized), 'error');
      }
    });
  }

  function bindLogoutButtons() {
    // Use event delegation for robustness
    if (document.body.dataset.logoutBound !== '1') {
      document.body.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-action="logout"]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        if (window.CRM && window.CRM.api && typeof window.CRM.api.logout === 'function') {
          window.CRM.api.logout().then(function() {
            window.location.href = withQuery('login');
          }).catch(function(error) {
            window.location.href = withQuery('login');
          });
        } else {
          window.location.href = withQuery('login');
        }
      });
      document.body.dataset.logoutBound = '1';
    }
  }

  function ensureProtectedAccess() {
    var protectedPage = document.body.dataset.protected === '1';

    if (!protectedPage) return true;
    return true;
  }

  async function hydrateSessionUi() {
    console.log('BR1: Starting hydrateSessionUi...');
    var cachedUser = window.CRM.api.getUser();
    if (cachedUser) {
      setSessionUiUser(cachedUser);
      currentUserPublicId = cachedUser.public_id ? String(cachedUser.public_id) : '';
      console.log('BR1: Using cached user:', cachedUser);
    }

    if (document.body.dataset.protected !== '1') {
      console.log('BR1: Not protected page, skipping me() call');
      return;
    }

    try {
      var envelope = await window.CRM.api.me();
      var meUser = envelope.data && envelope.data.user ? envelope.data.user : null;
      setSessionUiUser(meUser);
      currentUserPublicId = meUser && meUser.public_id ? String(meUser.public_id) : '';
      await renderImpersonationBanner();
      await loadTaskStatusesDictionary();
    } catch (error) {
      var isAuthError = error && error.envelope && (error.envelope.code === 'UNAUTHORIZED' || error.envelope.status === 401);
      if (!isAuthError) return;
      var retryKey = 'crm_me_401_retry_at';
      var lastRetry = window.sessionStorage ? parseInt(window.sessionStorage.getItem(retryKey) || '0', 10) : 0;
      var now = Date.now();
      if (now - lastRetry < 15000) {
        window.CRM.api.clearAuth();
        window.location.href = withQuery('login') + '&return_route=' + encodeURIComponent(getCurrentRoute());
        return;
      }
      window.sessionStorage && window.sessionStorage.setItem(retryKey, String(now));
    }

    if (document.body.dataset.protected === '1') {
      var originalRequest = window.CRM.api.request.bind(window.CRM.api);
      window.CRM.api.request = function (route, opts) {
        return originalRequest(route, opts).catch(function (err) {
          if (err && err.isAuthError) {
            console.warn('BR1: request auth error, but keeping session alive');
          }
          throw err;
        });
      };
    }
  }

  async function loadTaskStatusesDictionary() {
    if (!hasPermission('task.manage')) {
      availableTaskStatuses = [];
      return;
    }

    try {
      var statusesEnvelope = await window.CRM.api.request('api/v1/statuses', {
        query: {
          scope: 'task',
          is_active: 1,
          limit: 200
        }
      });
      availableTaskStatuses = window.CRM.api.items(statusesEnvelope).map(function (item) {
        return {
          code: String(item.code || ''),
          title: String(item.title || item.code || ''),
          sort_order: Number(item.sort_order || 0)
        };
      }).filter(function (item) {
        return item.code !== '';
      }).sort(function (a, b) {
        return a.sort_order - b.sort_order;
      });
    } catch (e) {
      // Для ограниченных ролей endpoint может быть недоступен, оставляем fallback.
    }
  }

  async function initProjectCreateFlow() {
    var form = document.getElementById('createProjectForm');
    if (!form) return;
    form.setAttribute('novalidate', 'novalidate');

    if (!hasPermission('project.manage')) {
      hideOrDisable('[data-open-modal="createProjectModal"]');
      hideOrDisable('#createProjectForm');
      return;
    }

    function renderProjectCreateOptions() {
      var clientSelect = form.querySelector('[name="client_public_id"]');
      var teamSelect = form.querySelector('[name="team_public_id"]');
      var managerSelect = form.querySelector('[name="manager_user_public_id"]');

      if (clientSelect) {
        var currentClient = String(clientSelect.value || '').trim();
        clientSelect.innerHTML = ['<option value="">Без клиента</option>'].concat(availableClients.map(function (client) {
          return '<option value="' + escapeHtml(client.public_id || '') + '">' + escapeHtml(client.title || client.legal_name || client.public_id || 'Клиент') + '</option>';
        })).join('');
        clientSelect.value = currentClient;
      }

      if (teamSelect) {
        var currentTeam = String(teamSelect.value || '').trim();
        teamSelect.innerHTML = ['<option value="">Команда не назначена</option>'].concat(availableTeams.map(function (team) {
          return '<option value="' + escapeHtml(team.public_id || '') + '">' + escapeHtml(team.title || team.name || team.public_id || 'Команда') + '</option>';
        })).join('');
        teamSelect.value = currentTeam;
      }

      if (managerSelect) {
        var currentManager = String(managerSelect.value || '').trim();
        managerSelect.innerHTML = ['<option value="">Без менеджера</option>'].concat(availableUsers.map(function (user) {
          return '<option value="' + escapeHtml(user.public_id || '') + '">' + escapeHtml(user.full_name || user.login || user.public_id || 'Пользователь') + '</option>';
        })).join('');
        managerSelect.value = currentManager;
      }
    }

    async function ensureProjectCreateDictionaries() {
      if (availableClients.length === 0) {
        try {
          var clientsEnvelope = await window.CRM.api.request('api/v1/clients', { query: { limit: 200 } });
          availableClients = window.CRM.api.items(clientsEnvelope);
        } catch (e) {
          availableClients = [];
        }
      }
      if (availableTeams.length === 0) {
        try {
          var teamsEnvelope = await window.CRM.api.request('api/v1/teams', { query: { limit: 200 } });
          availableTeams = window.CRM.api.items(teamsEnvelope);
        } catch (e) {
          availableTeams = [];
        }
      }
      if (availableUsers.length === 0) {
        try {
          var usersEnvelope = await window.CRM.api.request('api/v1/users', { query: { limit: 200, is_active: 1 } });
          availableUsers = window.CRM.api.items(usersEnvelope);
        } catch (e) {
          availableUsers = [];
        }
      }
      renderProjectCreateOptions();
    }

    var modal = document.getElementById('createProjectModal');
    if (modal && modal.dataset.boundCreateProject !== '1') {
      modal.addEventListener('show.bs.modal', function () {
        ensureProjectCreateDictionaries();
      });
      modal.dataset.boundCreateProject = '1';
    }

    if (form.dataset.bound === '1') return;
    function clearProjectCreateErrors() {
      form.querySelectorAll('.is-invalid').forEach(function (node) {
        node.classList.remove('is-invalid');
      });
      form.querySelectorAll('[data-project-create-error]').forEach(function (node) {
        node.remove();
      });
    }

    function showProjectCreateError(field, message) {
      if (!field) return;
      field.classList.add('is-invalid');
      var errorNode = document.createElement('div');
      errorNode.className = 'invalid-feedback d-block';
      errorNode.setAttribute('data-project-create-error', '1');
      errorNode.textContent = message;
      field.insertAdjacentElement('afterend', errorNode);
    }

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      clearProjectCreateErrors();

      var titleEl = form.querySelector('[name="title"]');
      var clientEl = form.querySelector('[name="client_public_id"]');
      var teamEl = form.querySelector('[name="team_public_id"]');
      var managerEl = form.querySelector('[name="manager_user_public_id"]');
      var statusEl = form.querySelector('[name="status"]');
      var priorityEl = form.querySelector('[name="priority"]');
      var descEl = form.querySelector('[name="description"]');
      var submitBtn = form.querySelector('[type="submit"]');

      var title = titleEl ? titleEl.value.trim() : '';
      if (!title) {
        showProjectCreateError(titleEl, 'Введите название проекта');
        notify('Введите название проекта', 'warning');
        return;
      }

      if (submitBtn) submitBtn.disabled = true;
      try {
        await window.CRM.api.request('api/v1/projects', {
          method: 'POST',
          headers: {
            'X-Idempotency-Key': window.CRM.api.createIdempotencyKey('web-project')
          },
          body: {
            title: title,
            description: descEl ? descEl.value.trim() : '',
            client_public_id: clientEl ? String(clientEl.value || '').trim() : '',
            team_public_id: teamEl ? String(teamEl.value || '').trim() : '',
            manager_user_public_id: managerEl ? String(managerEl.value || '').trim() : '',
            status: statusEl ? String(statusEl.value || 'active') : 'active',
            priority: priorityEl ? String(priorityEl.value || 'normal') : 'normal'
          }
        });

        notify('Проект создан');
        if (window.bootstrap && modal) {
          window.bootstrap.Modal.getOrCreateInstance(modal).hide();
        }
        form.reset();
        if (statusEl) statusEl.value = 'active';
        if (priorityEl) priorityEl.value = 'normal';

        if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.refreshCurrentPage === 'function') {
          window.CRM.pageApiBindings.refreshCurrentPage();
        }
      } catch (error) {
        var envelope = error && error.envelope ? error.envelope : null;
        var errors = envelope && envelope.errors && typeof envelope.errors === 'object' ? envelope.errors : {};
        if (errors.title) {
          showProjectCreateError(titleEl, Array.isArray(errors.title) ? String(errors.title[0] || 'Проверьте название проекта') : String(errors.title));
        }
        notify((envelope && envelope.message) || 'Не удалось создать проект', 'error');
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
    form.dataset.bound = '1';
  }

  function renderCreateTaskProjectOptions() {
    var modal = document.getElementById('createTaskModal');
    if (!modal) return;

    var projectSelect = modal.querySelector('select[name="project_public_id"]');
    if (!projectSelect) return;

    var currentValue = projectSelect.value || '';
    var options = ['<option value="">Без проекта</option>'].concat(availableProjects.map(function (project) {
      return '<option value="' + escapeHtml(project.public_id || '') + '">' + escapeHtml(project.title || project.public_id || '') + '</option>';
    }));
    projectSelect.innerHTML = options.join('');
    projectSelect.value = currentValue;
  }

  function renderCreateTaskAssigneeOptions() {
    var modal = document.getElementById('createTaskModal');
    if (!modal) return;

    var assigneeSelect = modal.querySelector('select[name="assignee_user_public_id"]');
    if (!assigneeSelect) return;

    var currentValue = assigneeSelect.value || '';
    var options = ['<option value="">Не назначен</option>'].concat(availableUsers.map(function (user) {
      return '<option value="' + escapeHtml(user.public_id || '') + '">' + escapeHtml(user.full_name || user.login || user.public_id || '') + '</option>';
    }));
    assigneeSelect.innerHTML = options.join('');
    assigneeSelect.value = currentValue;
  }

  function renderCreateTaskStatusOptions() {
    var modal = document.getElementById('createTaskModal');
    if (!modal) return;

    var statusSelect = modal.querySelector('select[name="status"]');
    if (!statusSelect) return;

    var currentValue = statusSelect.value || 'new';
    var source = availableTaskStatuses.length ? availableTaskStatuses : [
      { code: 'new', title: 'Новая' },
      { code: 'in_progress', title: 'В работе' },
      { code: 'blocked', title: 'Заблокирована' },
      { code: 'done', title: 'Завершена' }
    ];

    statusSelect.innerHTML = source.map(function (status) {
      return '<option value="' + escapeHtml(status.code || '') + '">' + escapeHtml(status.title || status.code || '') + '</option>';
    }).join('');
    statusSelect.value = currentValue && source.some(function (status) { return String(status.code || '') === String(currentValue); }) ? currentValue : 'new';
  }

  function renderCreateTaskTagOptions() {
    var modal = document.getElementById('createTaskModal');
    if (!modal) return;

    var tagsSelect = modal.querySelector('select[name="tag_public_ids"]');
    if (!tagsSelect) return;

    var selectedValues = Array.prototype.slice.call(tagsSelect.selectedOptions || []).map(function (option) {
      return String(option.value || '');
    });

    tagsSelect.innerHTML = availableTags.map(function (tag) {
      var value = String(tag.public_id || '');
      var selected = selectedValues.indexOf(value) >= 0 ? ' selected' : '';
      return '<option value="' + escapeHtml(value) + '"' + selected + '>' + escapeHtml(tag.title || value) + '</option>';
    }).join('');
  }

  async function ensureCreateTaskDictionaries() {
    if (availableProjects.length === 0) {
      try {
        var projectsEnvelope = await window.CRM.api.request('api/v1/projects', { query: { limit: 100 } });
        availableProjects = window.CRM.api.items(projectsEnvelope);
      } catch (e) {
        availableProjects = [];
      }
    }

    if (availableUsers.length === 0) {
      try {
        var usersEnvelope = await window.CRM.api.request('api/v1/users', { query: { limit: 100, is_active: 1 } });
        availableUsers = window.CRM.api.items(usersEnvelope);
      } catch (e) {
        availableUsers = [];
      }
    }

    if (availableTags.length === 0) {
      try {
        var tagsEnvelope = await window.CRM.api.request('api/v1/tags', { query: { limit: 100 } });
        availableTags = window.CRM.api.items(tagsEnvelope);
      } catch (e) {
        availableTags = [];
      }
    }

    renderCreateTaskProjectOptions();
    renderCreateTaskAssigneeOptions();
    renderCreateTaskStatusOptions();
    renderCreateTaskTagOptions();
  }

  function primeCreateTaskDefaults() {
    var modal = document.getElementById('createTaskModal');
    if (!modal) return;

    var form = modal.querySelector('#createTaskForm');
    if (!form) return;

    form.reset();

    var projectSelect = form.querySelector('select[name="project_public_id"]');
    var statusSelect = form.querySelector('select[name="status"]');
    var prioritySelect = form.querySelector('select[name="priority"]');
    var routeProjectId = getProjectPublicIdFromUrl();

    if (projectSelect) {
      projectSelect.value = routeProjectId || '';
    }
    if (statusSelect) {
      statusSelect.value = 'new';
    }
    if (prioritySelect) {
      prioritySelect.value = 'normal';
    }
  }

  function initTaskCreateFlow() {
    var modal = document.getElementById('createTaskModal');
    var form = document.getElementById('createTaskForm');
    if (!hasPermission('task.manage')) {
      hideOrDisable('[data-open-modal="createTaskModal"]');
      hideOrDisable('#createTaskModal');
      return;
    }

    if (modal && form) {
      if (form.dataset.bound === '1') {
        return;
      }

      modal.addEventListener('show.bs.modal', async function () {
        await ensureCreateTaskDictionaries();
        primeCreateTaskDefaults();
      });

      modal.addEventListener('hidden.bs.modal', function () {
        primeCreateTaskDefaults();
      });

      form.addEventListener('submit', async function (e) {
        e.preventDefault();

        var titleInput = form.querySelector('[name="title"]');
        var startInput = form.querySelector('[name="start_at"]');
        var dueInput = form.querySelector('[name="due_at"]');
        var endInput = form.querySelector('[name="end_at"]');
        var descInput = form.querySelector('[name="description"]');
        var projectSelect = form.querySelector('[name="project_public_id"]');
        var statusSelect = form.querySelector('[name="status"]');
        var prioritySelect = form.querySelector('[name="priority"]');
        var assigneeSelect = form.querySelector('[name="assignee_user_public_id"]');
        var tagsSelect = form.querySelector('[name="tag_public_ids"]');
        var submitBtn = form.querySelector('.crm-btn-primary');

        var title = titleInput ? titleInput.value.trim() : '';
        if (!title) {
          notify('Введите название задачи', 'warning');
          return;
        }
        if (!hasPermission('task.manage')) {
          return;
        }

        var selectedAssigneePublicId = assigneeSelect ? String(assigneeSelect.value || '').trim() : '';
        var selectedTagIds = tagsSelect
          ? Array.prototype.slice.call(tagsSelect.selectedOptions).map(function (option) { return String(option.value || '').trim(); }).filter(Boolean)
          : [];

        function normalizeDateTime(value, fallbackTime) {
          return value ? value + ' ' + fallbackTime + ':00' : null;
        }

        if (submitBtn) submitBtn.disabled = true;

        try {
          var createEnvelope = await window.CRM.api.request('api/v1/tasks', {
            method: 'POST',
            headers: {
              'X-Idempotency-Key': window.CRM.api.createIdempotencyKey('web-task')
            },
            body: {
              title: title,
              description: descInput ? descInput.value.trim() : '',
              start_at: normalizeDateTime(startInput && startInput.value ? startInput.value : '', '09:00'),
              due_at: normalizeDateTime(dueInput && dueInput.value ? dueInput.value : '', '18:00'),
              end_at: normalizeDateTime(endInput && endInput.value ? endInput.value : '', '18:00'),
              project_public_id: projectSelect && projectSelect.value ? String(projectSelect.value) : '',
              status: statusSelect && statusSelect.value ? String(statusSelect.value) : 'new',
              priority: prioritySelect && prioritySelect.value ? String(prioritySelect.value) : 'normal'
            }
          });

          var taskPayload = extractTaskPayload(createEnvelope);
          var taskPublicId = taskPayload && taskPayload.public_id ? String(taskPayload.public_id) : '';

          if (taskPublicId && selectedAssigneePublicId) {
            await window.CRM.api.request('api/v1/tasks/bulk', {
              method: 'POST',
              body: {
                task_public_ids: [taskPublicId],
                changes: {
                  assignee_user_public_id: selectedAssigneePublicId
                }
              }
            });
          }

          if (taskPublicId && selectedTagIds.length) {
            for (var i = 0; i < selectedTagIds.length; i += 1) {
              await window.CRM.api.request('api/v1/tasks/' + taskPublicId + '/tags/' + selectedTagIds[i], {
                method: 'POST'
              });
            }
          }

          notify('Задача создана');
          if (window.bootstrap) {
            window.bootstrap.Modal.getOrCreateInstance(modal).hide();
          }
          primeCreateTaskDefaults();

          if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.refreshCurrentPage === 'function') {
            window.CRM.pageApiBindings.refreshCurrentPage();
          }
        } catch (error) {
          var envelope = error && error.envelope ? error.envelope : null;
          notify((envelope && envelope.message) || 'Не удалось создать задачу', 'error');
        } finally {
          if (submitBtn) submitBtn.disabled = false;
        }
      });
      form.dataset.bound = '1';
    }
  }

  function initCalendarEventCreateFlow() {
    if (!hasPermission('task.manage')) {
      hideOrDisable('[data-open-modal="calendarEventModal"]');
      hideOrDisable('#calendarEventModal');
      return;
    }

    var form = document.getElementById('calendarEventForm');
    var modal = document.getElementById('calendarEventModal');
    if (!form || !modal || form.dataset.bound === '1') return;
    form.setAttribute('novalidate', 'novalidate');

    function clearCalendarFormErrors() {
      form.querySelectorAll('.is-invalid').forEach(function (node) {
        node.classList.remove('is-invalid');
      });
      form.querySelectorAll('[data-inline-error]').forEach(function (node) {
        node.remove();
      });
    }

    function showCalendarFormErrors(errors) {
      clearCalendarFormErrors();
      Object.keys(errors || {}).forEach(function (field) {
        var input = form.querySelector('[name="' + field + '"]');
        var messages = Array.isArray(errors[field]) ? errors[field] : [String(errors[field] || '')];
        var message = String(messages[0] || '').trim();
        if (!input || !message) return;
        input.classList.add('is-invalid');
        var feedback = document.createElement('div');
        feedback.className = 'invalid-feedback d-block';
        feedback.setAttribute('data-inline-error', '1');
        feedback.textContent = message;
        input.insertAdjacentElement('afterend', feedback);
      });
    }

    function setCalendarFormMode(mode, eventId) {
      var isEdit = mode === 'edit' && eventId;
      if (isEdit) {
        form.dataset.calendarEditId = String(eventId || '');
      } else {
        delete form.dataset.calendarEditId;
      }
      var title = modal.querySelector('.modal-title');
      var submitBtn = form.querySelector('[type="submit"]');
      if (title) title.textContent = isEdit ? 'Редактировать событие' : 'Создать событие';
      if (submitBtn) submitBtn.textContent = isEdit ? 'Сохранить' : 'Создать';
    }

    function prepareCalendarCreate(trigger) {
      setCalendarFormMode('create', '');
      form.reset();
      clearCalendarFormErrors();
      var rawDate = trigger ? String(trigger.getAttribute('data-calendar-date') || '').trim() : '';
      if (rawDate) {
        var startsDate = form.querySelector('[name="starts_at_date"]');
        var endsDate = form.querySelector('[name="ends_at_date"]');
        if (startsDate) startsDate.value = rawDate;
        if (endsDate) endsDate.value = rawDate;
      }
      setDefaults();
    }

    function splitApiDateTime(raw) {
      var value = String(raw || '').trim().replace('T', ' ');
      var parts = value.split(' ');
      return {
        date: parts[0] || todayValue(),
        time: parts[1] ? parts[1].slice(0, 5) : ''
      };
    }

    window.CRM.calendarOpenEventEditor = function (eventItem) {
      if (!eventItem || !eventItem.public_id) return;
      setCalendarFormMode('edit', eventItem.public_id);
      clearCalendarFormErrors();
      var start = splitApiDateTime(eventItem.starts_at || eventItem.start_at || '');
      var end = splitApiDateTime(eventItem.ends_at || eventItem.end_at || eventItem.starts_at || eventItem.start_at || '');
      var titleInput = form.querySelector('[name="title"]');
      var startsDate = form.querySelector('[name="starts_at_date"]');
      var startsTime = form.querySelector('[name="starts_at_time"]');
      var endsDate = form.querySelector('[name="ends_at_date"]');
      var endsTime = form.querySelector('[name="ends_at_time"]');
      var description = form.querySelector('[name="description"]');
      var taskInput = form.querySelector('[name="task_public_id"]');
      if (titleInput) titleInput.value = String(eventItem.title || eventItem.name || '');
      if (startsDate) startsDate.value = start.date;
      if (startsTime) startsTime.value = start.time || '09:00';
      if (endsDate) endsDate.value = end.date || start.date;
      if (endsTime) endsTime.value = end.time || start.time || '10:00';
      if (description) description.value = String(eventItem.description || eventItem.note || eventItem.comment || eventItem.body || '');
      if (taskInput) taskInput.value = String(eventItem.task_public_id || '');
      applyDateLimits();
      if (window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
      }
    };

    function todayValue() {
      var date = new Date();
      var pad = function (value) { return String(value).padStart(2, '0'); };
      return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    function applyDateLimits() {
      var minDate = todayValue();
      var startsDate = form.querySelector('[name="starts_at_date"]');
      var endsDate = form.querySelector('[name="ends_at_date"]');
      if (startsDate) startsDate.min = minDate;
      if (endsDate) endsDate.min = startsDate && startsDate.value ? startsDate.value : minDate;
    }

    function currentTaskContext() {
      var taskId = getTaskPublicIdFromUrl();
      if (!taskId) {
        return { id: '', title: '' };
      }

      var title = currentTask && currentTask.title ? String(currentTask.title) : '';
      if (!title) {
        var titleEl = document.querySelector('.crm-page-title');
        title = titleEl ? String(titleEl.textContent || '').trim() : '';
      }
      if (title === 'Загрузка задачи...') title = '';

      return { id: taskId, title: title };
    }

    function applyTaskContext() {
      var context = currentTaskContext();
      var taskInput = form.querySelector('[name="task_public_id"]');
      var contextBox = form.querySelector('[data-calendar-task-context]');
      if (taskInput) taskInput.value = context.id;

      if (!contextBox) return;
      if (!context.id) {
        contextBox.classList.add('d-none');
        contextBox.innerHTML = '';
        return;
      }

      contextBox.classList.remove('d-none');
      contextBox.innerHTML = '<div class="crm-calendar-form-context">'
        + '<span>Контекст</span>'
        + '<strong>Задача: ' + escapeHtml(context.title || context.id) + '</strong>'
        + '</div>';
    }

    function setDefaults() {
      var dateValue = todayValue();
      var startsDate = form.querySelector('[name="starts_at_date"]');
      var endsDate = form.querySelector('[name="ends_at_date"]');
      var startsTime = form.querySelector('[name="starts_at_time"]');
      var endsTime = form.querySelector('[name="ends_at_time"]');
      if (startsDate && (!startsDate.value || startsDate.value < dateValue)) startsDate.value = dateValue;
      if (endsDate && (!endsDate.value || endsDate.value < (startsDate && startsDate.value ? startsDate.value : dateValue))) endsDate.value = startsDate && startsDate.value ? startsDate.value : dateValue;
      if (startsTime && !startsTime.value) startsTime.value = '09:00';
      if (endsTime && !endsTime.value) endsTime.value = '10:00';
      applyDateLimits();
      applyTaskContext();
    }

    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-open-modal="calendarEventModal"]');
      if (!trigger) return;
      prepareCalendarCreate(trigger);
    });

    modal.addEventListener('show.bs.modal', function () {
      if (!form.dataset.calendarEditId) setDefaults();
    });
    applyDateLimits();

    var startsDateLimitInput = form.querySelector('[name="starts_at_date"]');
    if (startsDateLimitInput) {
      startsDateLimitInput.addEventListener('change', function () {
        var endsDate = form.querySelector('[name="ends_at_date"]');
        applyDateLimits();
        if (endsDate && startsDateLimitInput.value && endsDate.value && endsDate.value < startsDateLimitInput.value) {
          endsDate.value = startsDateLimitInput.value;
        }
      });
    }

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      var titleInput = form.querySelector('[name="title"]');
      var startsDateInput = form.querySelector('[name="starts_at_date"]');
      var startsTimeInput = form.querySelector('[name="starts_at_time"]');
      var endsDateInput = form.querySelector('[name="ends_at_date"]');
      var endsTimeInput = form.querySelector('[name="ends_at_time"]');
      var descriptionInput = form.querySelector('[name="description"]');
      var taskInput = form.querySelector('[name="task_public_id"]');
      var submitBtn = form.querySelector('[type="submit"]');
      clearCalendarFormErrors();

      var title = titleInput ? titleInput.value.trim() : '';
      if (!title) {
        showCalendarFormErrors({ title: ['Введите название события'] });
        notify('Введите название события', 'warning');
        return;
      }

      var minDate = todayValue();
      if (!startsDateInput || !startsDateInput.value) {
        showCalendarFormErrors({ starts_at_date: ['Выберите дату начала события'] });
        notify('Выберите дату начала события', 'warning');
        return;
      }
      if (startsDateInput.value < minDate) {
        showCalendarFormErrors({ starts_at_date: ['Нельзя создать событие на дату из прошлого'] });
        notify('Нельзя создать событие на дату из прошлого', 'warning');
        startsDateInput.focus();
        return;
      }
      if (endsDateInput && endsDateInput.value && endsDateInput.value < startsDateInput.value) {
        showCalendarFormErrors({ ends_at_date: ['Дата окончания не может быть раньше даты начала'] });
        notify('Дата окончания не может быть раньше даты начала', 'warning');
        endsDateInput.focus();
        return;
      }

      var startsAt = startsDateInput && startsDateInput.value
        ? startsDateInput.value + ' ' + (startsTimeInput && startsTimeInput.value ? startsTimeInput.value : '09:00') + ':00'
        : null;
      var endsAt = endsDateInput && endsDateInput.value
        ? endsDateInput.value + ' ' + (endsTimeInput && endsTimeInput.value ? endsTimeInput.value : (startsTimeInput && startsTimeInput.value ? startsTimeInput.value : '10:00')) + ':00'
        : startsAt;

      if (startsAt && endsAt && new Date(endsAt.replace(' ', 'T')).getTime() <= new Date(startsAt.replace(' ', 'T')).getTime()) {
        showCalendarFormErrors({ ends_at_time: ['Окончание должно быть позже начала'] });
        notify('Окончание события должно быть позже начала', 'warning');
        return;
      }

      if (submitBtn) submitBtn.disabled = true;
      try {
        var body = {
          title: title,
          description: descriptionInput ? descriptionInput.value.trim() : '',
          starts_at: startsAt,
          ends_at: endsAt
        };
        if (taskInput && taskInput.value) {
          body.task_public_id = taskInput.value;
        }

        var editId = String(form.dataset.calendarEditId || '').trim();
        await window.CRM.api.request('api/v1/calendar/events' + (editId ? '/' + encodeURIComponent(editId) : ''), {
          method: editId ? 'PATCH' : 'POST',
          body: body
        });

        notify(editId ? 'Событие обновлено' : 'Событие создано');
        if (window.bootstrap) {
          window.bootstrap.Modal.getOrCreateInstance(modal).hide();
        }
        form.reset();
        setCalendarFormMode('create', '');
        if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.refreshCurrentPage === 'function') {
          window.CRM.pageApiBindings.refreshCurrentPage();
        }
      } catch (error) {
        var envelope = error && error.envelope ? error.envelope : null;
        if (envelope && envelope.errors && typeof envelope.errors === 'object') {
          showCalendarFormErrors(envelope.errors);
        }
        notify((envelope && envelope.message) || (form.dataset.calendarEditId ? 'Не удалось обновить событие' : 'Не удалось создать событие'), 'error');
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
    form.dataset.bound = '1';
  }

  async function resolveTaskForDetail() {
    var taskId = getTaskPublicIdFromUrl();
    if (taskId) return taskId;

    try {
      var listEnvelope = await window.CRM.api.request('api/v1/tasks', {
        method: 'GET',
        query: { limit: 1 }
      });
      var items = window.CRM.api.items(listEnvelope);
      return items.length ? items[0].public_id : '';
    } catch (error) {
      return '';
    }
  }

  function renderTaskStatus(code) {
    var statusBadge = document.getElementById('taskStatusBadge');
    if (!statusBadge) return;

    statusBadge.className = 'crm-badge ' + statusBadgeClass(code);
    statusBadge.textContent = statusLabel(code);
  }

  function orderedTaskStatuses(currentStatusCode) {
    var fallback = [
      { code: 'new', title: 'К выполнению', sort_order: 10 },
      { code: 'in_progress', title: 'В работе', sort_order: 20 },
      { code: 'blocked', title: 'Блокировано', sort_order: 30 },
      { code: 'done', title: 'Готово', sort_order: 40 }
    ];
    var options = (availableTaskStatuses && availableTaskStatuses.length) ? availableTaskStatuses.slice() : fallback.slice();
    var currentCode = String(currentStatusCode || '');
    if (currentCode) {
      var exists = options.some(function (item) { return String(item.code || '') === currentCode; });
      if (!exists) {
        options.push({
          code: currentCode,
          title: statusLabel(currentCode),
          sort_order: 999
        });
      }
    }
    options.sort(function (a, b) { return Number(a.sort_order || 0) - Number(b.sort_order || 0); });
    return options;
  }

  function renderTaskDescription(description) {
    var text = String(description || '').trim();

    var detail = document.getElementById('taskDescriptionContent');
    if (detail) {
      if (text) {
        var hasMarkup = /<\/?[a-z][\s\S]*>/i.test(text);
        if (hasMarkup) {
          var sanitized = sanitizeRichTextHtml(text);
          detail.innerHTML = sanitized || '<p class="text-muted mb-0">Описание задачи не заполнено.</p>';
        } else {
          detail.innerHTML = '<p class="mb-0">' + escapeHtml(text).replace(/\n/g, '<br>') + '</p>';
        }
      } else {
        detail.innerHTML = '<p class="text-muted mb-0">Описание задачи не заполнено.</p>';
      }
    }

    var inlineInput = document.getElementById('taskDescriptionInlineInput');
    if (inlineInput) {
      inlineInput.value = currentTask && currentTask.description ? String(currentTask.description) : '';
    }

  }

  function renderTaskProgressByStatus(statusCode) {
    var progressBar = document.getElementById('taskProgressBar');
    var progressHint = document.getElementById('taskProgressHint');
    if (!progressBar) return;

    var code = String(statusCode || '').toLowerCase();
    var statuses = orderedTaskStatuses(code);
    var percent = 0;
    if (code === 'done' || code === 'completed') {
      percent = 100;
    } else if (statuses.length > 1) {
      var index = statuses.findIndex(function (item) { return String(item.code || '').toLowerCase() === code; });
      if (index >= 0) {
        percent = Math.round((index / (statuses.length - 1)) * 100);
      }
    } else if (statuses.length === 1 && code) {
      percent = 50;
    }

    progressBar.style.width = percent + '%';
    progressBar.textContent = percent + '%';
    if (progressHint) {
      progressHint.textContent = 'Прогресс: ' + String(percent) + '% (позиция статуса «' + statusLabel(code) + '» в воронке статусов).';
    }
  }

  function renderTaskRiskBanner() {
    var alert = document.getElementById('taskRiskAlert');
    if (!alert || !currentTask) return;

    var statusCode = String(currentTask.status_code || '').toLowerCase();
    var dueMs = currentTask.due_at ? Date.parse(String(currentTask.due_at)) : NaN;
    var isOverdue = Number.isFinite(dueMs) && dueMs < Date.now() && statusCode !== 'done' && statusCode !== 'completed';

    if (isOverdue) {
      alert.className = 'alert alert-danger mb-2';
      alert.innerHTML = '<strong>Риск:</strong> задача просрочена, нужен приоритетный разбор блокеров.';
      return;
    }

    if (statusCode === 'blocked') {
      alert.className = 'alert alert-warning mb-2';
      alert.innerHTML = '<strong>Риск:</strong> задача в блоке. Требуется эскалация/согласование.';
      return;
    }

    if (statusCode === 'done' || statusCode === 'completed') {
      alert.className = 'alert alert-success mb-2';
      alert.innerHTML = '<strong>Статус:</strong> задача завершена.';
      return;
    }

    alert.className = 'alert alert-info mb-2';
    alert.innerHTML = '<strong>Статус:</strong> критичных рисков по задаче не выявлено.';
  }

  function setTaskEditAvailability(canEdit) {
    var editBtn = document.getElementById('taskEditBtn');
    if (editBtn) {
      editBtn.classList.toggle('d-none', !canEdit);
    }

    var form = document.getElementById('editTaskForm');
    if (!form) return;

    var titleInput = form.querySelector('[name="title"]');
    var descInput = form.querySelector('[name="description"]');
    var saveBtn = form.querySelector('button[type="submit"]');
    var lockInfo = form.querySelector('[data-edit-task-lock]');

    if (titleInput) titleInput.disabled = !canEdit;
    if (descInput) descInput.disabled = !canEdit;
    if (saveBtn) saveBtn.disabled = !canEdit;
    if (lockInfo) lockInfo.classList.toggle('d-none', canEdit);
  }

  function applyTaskInlinePermissions(permissions) {
    var datesToggle = document.querySelector('[data-task-inline-toggle="dates"]');
    if (datesToggle) {
      var datesPanel = datesToggle.closest('.crm-info-panel');
      if (datesPanel) datesPanel.classList.toggle('d-none', !permissions.canEditIdentity);
    }

    var assigneeToggle = document.querySelector('[data-task-inline-toggle="assignee"]');
    if (assigneeToggle) {
      var assigneePanel = assigneeToggle.closest('.crm-info-panel');
      if (assigneePanel) assigneePanel.classList.toggle('d-none', !permissions.canEditAssignment);
    }

    var managerToggle = document.querySelector('[data-task-inline-toggle="manager"]');
    if (managerToggle) {
      var managerPanel = managerToggle.closest('.crm-info-panel');
      if (managerPanel) managerPanel.classList.toggle('d-none', !permissions.canEditAssignment);
    }

    var projectToggle = document.querySelector('[data-task-inline-toggle="project"]');
    if (projectToggle) {
      var projectPanel = projectToggle.closest('.crm-info-panel');
      if (projectPanel) projectPanel.classList.toggle('d-none', !permissions.canEditProject);
    }

    var tagsToggle = document.querySelector('[data-task-inline-toggle="tags"]');
    if (tagsToggle) {
      var tagsPanel = tagsToggle.closest('.crm-info-panel');
      if (tagsPanel) tagsPanel.classList.toggle('d-none', !permissions.canEditTags);
    }

    var descToggle = document.querySelector('[data-task-inline-toggle="description"]');
    if (descToggle) {
      var descPanel = descToggle.closest('.d-flex');
      if (descPanel) descPanel.classList.toggle('d-none', !permissions.canEditIdentity);
    }
  }

  function renderTaskSidebarSummary() {
    if (!currentTask) return;

    var authorEl = document.getElementById('taskAuthorValue');
    if (authorEl) {
      authorEl.textContent = resolveUserDisplayName(
        currentTask.creator_user_name || '',
        currentTask.creator_user_public_id || '',
        'Не указан'
      );
    }

    var assigneeEl = document.getElementById('taskAssigneeValue');
    if (assigneeEl) {
      assigneeEl.textContent = resolveUserDisplayName(
        currentTask.assignee_name || currentTask.assignee_login || '',
        currentTask.assignee_user_public_id || '',
        'Не назначен'
      );
    }

    var managerEl = document.getElementById('taskManagerValue');
    if (managerEl) {
      managerEl.textContent = resolveUserDisplayName(
        currentTask.project_manager_name || '',
        currentTask.project_manager_user_public_id || '',
        'Не назначен'
      );
    }

    var tagsEl = document.getElementById('taskTagsValue');
    if (tagsEl) {
      if (currentTaskTags && currentTaskTags.length) {
        tagsEl.innerHTML = currentTaskTags.map(function (tag) {
          return '<span class="crm-chip me-1 mb-1">' + escapeHtml(tag.title || tag.code || tag.public_id || '—') + '</span>';
        }).join('');
      } else {
        tagsEl.textContent = 'Нет тегов';
      }
    }

    var projectLink = document.getElementById('taskProjectLink');
    if (projectLink) {
      if (currentTask.project_public_id) {
        projectLink.textContent = currentTask.project_title || currentTask.project_public_id;
        projectLink.href = withQuery('project-detail', 'project_public_id', currentTask.project_public_id);
      } else {
        projectLink.textContent = 'Без проекта';
        projectLink.href = '#';
      }
    }

    var datesEl = document.getElementById('taskDatesValue');
    if (datesEl) {
      var parts = [];
      if (currentTask.start_at) parts.push('Начало: ' + formatDate(currentTask.start_at));
      if (currentTask.due_at) parts.push('Дедлайн: ' + formatDate(currentTask.due_at));
      if (currentTask.end_at) parts.push('Завершение: ' + formatDate(currentTask.end_at));
      datesEl.textContent = parts.length ? parts.join(' · ') : 'Не заданы';
    }

    var datesStartInput = document.getElementById('taskDatesStartAt');
    if (datesStartInput) datesStartInput.value = toDateTimeLocalValue(currentTask.start_at);

    var datesDueInput = document.getElementById('taskDatesDueAt');
    if (datesDueInput) datesDueInput.value = toDateTimeLocalValue(currentTask.due_at);

    var datesEndInput = document.getElementById('taskDatesEndAt');
    if (datesEndInput) datesEndInput.value = toDateTimeLocalValue(currentTask.end_at);

    var projectSelect = document.getElementById('taskProjectInlineSelect');
    if (projectSelect) {
      var projectOptions = ['<option value="">Без проекта</option>'].concat(availableProjects.map(function (p) {
        var selected = currentTask && String(currentTask.project_public_id || '') === String(p.public_id || '') ? ' selected' : '';
        return '<option value="' + escapeHtml(p.public_id || '') + '"' + selected + '>' + escapeHtml(p.title || p.public_id || '') + '</option>';
      }));
      projectSelect.innerHTML = projectOptions.join('');
    }

    var assigneeSelect = document.getElementById('taskAssigneeInlineSelect');
    if (assigneeSelect) {
      var assigneeOptions = ['<option value="">Не назначен</option>'].concat(availableUsers.map(function (u) {
        var selected = currentTask && String(currentTask.assignee_user_public_id || '') === String(u.public_id || '') ? ' selected' : '';
        return '<option value="' + escapeHtml(u.public_id || '') + '"' + selected + '>' + escapeHtml(u.full_name || u.login || u.public_id || '') + '</option>';
      }));
      assigneeSelect.innerHTML = assigneeOptions.join('');
    }

    var managerSelect = document.getElementById('taskManagerInlineSelect');
    if (managerSelect) {
      var managerOptions = ['<option value="">Не назначен</option>'].concat(availableUsers.map(function (u) {
        var selected = currentTask && String(currentTask.project_manager_user_public_id || '') === String(u.public_id || '') ? ' selected' : '';
        return '<option value="' + escapeHtml(u.public_id || '') + '"' + selected + '>' + escapeHtml(u.full_name || u.login || u.public_id || '') + '</option>';
      }));
      managerSelect.innerHTML = managerOptions.join('');
    }

    var tagsSelect = document.getElementById('taskTagsInlineSelect');
    if (tagsSelect) {
      var selectedTagIds = currentTaskTags.map(function (tag) { return String(tag.public_id || ''); });
      tagsSelect.innerHTML = availableTags.map(function (tag) {
        var tagId = String(tag.public_id || '');
        var selected = selectedTagIds.indexOf(tagId) >= 0 ? ' selected' : '';
        return '<option value="' + escapeHtml(tagId) + '"' + selected + '>' + escapeHtml(tag.title || tag.code || tagId) + '</option>';
      }).join('');
    }

  }

  function bindTaskInlineEditors(taskId) {
    var descForm = document.getElementById('taskDescriptionInlineForm');
    var assigneeForm = document.getElementById('taskAssigneeInlineForm');
    var managerForm = document.getElementById('taskManagerInlineForm');
    var tagsForm = document.getElementById('taskTagsInlineForm');
    var projectForm = document.getElementById('taskProjectInlineForm');
    var datesForm = document.getElementById('taskDatesInlineForm');
    if (descForm && descForm.dataset.bound !== '1') {
      descForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!currentTaskPermissions.canEditIdentity) {
          notify('Изменение описания доступно только автору задачи', 'warning');
          return;
        }
        var input = document.getElementById('taskDescriptionInlineInput');
        var description = input ? String(input.value || '').trim() : '';
        try {
          var envelope = await window.CRM.api.request('api/v1/tasks/' + taskId, {
            method: 'PATCH',
            body: {
              description: description,
              row_version: currentTask.row_version
            }
          });
          currentTask = mergeTaskState(extractTaskPayload(envelope));
          renderTaskDescription(currentTask.description);
          descForm.classList.add('d-none');
          await loadTaskActivity(taskId);
          notify('Описание обновлено');
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || 'Не удалось обновить описание', 'error');
        }
      });
      descForm.dataset.bound = '1';
    }

    if (assigneeForm && assigneeForm.dataset.bound !== '1') {
      assigneeForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!currentTaskPermissions.canEditAssignment) {
          notify('Изменение исполнителя доступно только автору задачи', 'warning');
          return;
        }
        var assigneeSelect = document.getElementById('taskAssigneeInlineSelect');
        var assigneePublicId = assigneeSelect ? String(assigneeSelect.value || '').trim() : '';
        try {
          await window.CRM.api.request('api/v1/tasks/bulk', {
            method: 'POST',
            body: {
              task_public_ids: [taskId],
              changes: {
                assignee_user_public_id: assigneePublicId
              }
            }
          });
          var taskEnvelope = await window.CRM.api.request('api/v1/tasks/' + taskId);
          currentTask = mergeTaskState(extractTaskPayload(taskEnvelope));
          await loadProjectForTask();
          if (currentProject) {
            currentTask.project_public_id = currentProject.public_id || currentTask.project_public_id;
            currentTask.project_title = currentProject.title || currentTask.project_title;
            currentTask.project_manager_user_public_id = currentProject.manager_user_public_id || currentTask.project_manager_user_public_id;
            currentTask.project_manager_name = currentProject.manager_user_name || currentTask.project_manager_name;
          }
          renderTaskSidebarSummary();
          assigneeForm.classList.add('d-none');
          await loadTaskActivity(taskId);
          notify('Исполнитель обновлен');
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || 'Не удалось обновить исполнителя', 'error');
        }
      });
      assigneeForm.dataset.bound = '1';
    }

    if (managerForm && managerForm.dataset.bound !== '1') {
      managerForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!currentTaskPermissions.canEditAssignment) {
          notify('Изменение менеджера доступно только автору задачи', 'warning');
          return;
        }
        var managerSelect = document.getElementById('taskManagerInlineSelect');
        var managerPublicId = managerSelect ? String(managerSelect.value || '').trim() : '';
        if (!currentTask.project_public_id) {
          notify('Чтобы назначить менеджера, сначала привяжите задачу к проекту', 'warning');
          return;
        }
        try {
          await loadProjectForTask();
          if (currentProject) {
            await window.CRM.api.request('api/v1/projects/' + currentTask.project_public_id, {
              method: 'PATCH',
              body: {
                row_version: currentProject.row_version,
                manager_user_public_id: managerPublicId
              }
            });
          }
          await loadProjectForTask();
          var taskEnvelope = await window.CRM.api.request('api/v1/tasks/' + taskId);
          currentTask = mergeTaskState(extractTaskPayload(taskEnvelope));
          if (currentProject && currentProject.manager_user_public_id) {
            currentTask.project_manager_user_public_id = currentProject.manager_user_public_id;
            currentTask.project_manager_name = currentProject.manager_user_name || currentTask.project_manager_name;
          } else {
            currentTask.project_manager_user_public_id = '';
            currentTask.project_manager_name = '';
          }
          renderTaskSidebarSummary();
          managerForm.classList.add('d-none');
          await loadTaskActivity(taskId);
          notify('Менеджер обновлен');
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || 'Не удалось обновить менеджера', 'error');
        }
      });
      managerForm.dataset.bound = '1';
    }

    if (projectForm && projectForm.dataset.bound !== '1') {
      projectForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!currentTaskPermissions.canEditProject) {
          notify('Изменение проекта доступно только автору задачи', 'warning');
          return;
        }
        var projectSelect = document.getElementById('taskProjectInlineSelect');
        var projectPublicId = projectSelect ? String(projectSelect.value || '').trim() : '';
        try {
          var envelope = await window.CRM.api.request('api/v1/tasks/' + taskId, {
            method: 'PATCH',
            body: {
              project_public_id: projectPublicId,
              row_version: currentTask.row_version
            }
          });
          currentTask = mergeTaskState(extractTaskPayload(envelope));
          await loadProjectForTask();
          if (currentProject) {
            currentTask.project_title = currentProject.title || currentTask.project_title;
            currentTask.project_manager_user_public_id = currentProject.manager_user_public_id || currentTask.project_manager_user_public_id;
            currentTask.project_manager_name = currentProject.manager_user_name || currentTask.project_manager_name;
          } else {
            currentTask.project_manager_user_public_id = '';
            currentTask.project_manager_name = '';
          }
          var subtitle = document.querySelector('.crm-subtitle');
          if (subtitle) {
            subtitle.textContent = 'Проект: ' + (currentTask.project_title || '—')
              + ' · Дедлайн: ' + (currentTask.due_at ? formatDate(currentTask.due_at) : 'не задан');
          }
          renderTaskSidebarSummary();
          renderTaskMetaChips();
          projectForm.classList.add('d-none');
          await loadTaskActivity(taskId);
          notify('Проект задачи обновлен');
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || 'Не удалось обновить проект задачи', 'error');
        }
      });
      projectForm.dataset.bound = '1';
    }

    if (tagsForm && tagsForm.dataset.bound !== '1') {
      tagsForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!currentTaskPermissions.canEditTags) {
          notify('Изменение тегов доступно только автору задачи', 'warning');
          return;
        }
        var tagsSelect = document.getElementById('taskTagsInlineSelect');
        var selectedTagIds = tagsSelect
          ? Array.prototype.slice.call(tagsSelect.selectedOptions).map(function (opt) { return String(opt.value || '').trim(); }).filter(Boolean)
          : [];
        var currentTagIds = currentTaskTags.map(function (tag) { return String(tag.public_id || ''); });
        var addTagIds = selectedTagIds.filter(function (id) { return currentTagIds.indexOf(id) < 0; });
        var removeTagIds = currentTagIds.filter(function (id) { return selectedTagIds.indexOf(id) < 0; });

        try {
          if (!addTagIds.length && !removeTagIds.length) {
            notify('Изменений по тегам нет', 'warning');
            tagsForm.classList.add('d-none');
            return;
          }

          for (var i = 0; i < addTagIds.length; i += 1) {
            await window.CRM.api.request('api/v1/tasks/' + taskId + '/tags/' + addTagIds[i], {
              method: 'POST'
            });
          }
          for (var j = 0; j < removeTagIds.length; j += 1) {
            await window.CRM.api.request('api/v1/tasks/' + taskId + '/tags/' + removeTagIds[j], {
              method: 'DELETE'
            });
          }

          await loadTaskTags(taskId);
          renderTaskMetaChips();
          renderTaskSidebarSummary();
          tagsForm.classList.add('d-none');
          await loadTaskActivity(taskId);
          notify('Теги задачи обновлены');
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || 'Не удалось обновить теги задачи', 'error');
        }
      });
      tagsForm.dataset.bound = '1';
    }

    var datesForm = document.getElementById('taskDatesInlineForm');
    if (datesForm && datesForm.dataset.bound !== '1') {
      datesForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!currentTaskPermissions.canEditIdentity) {
          notify('Изменение сроков доступно только автору задачи', 'warning');
          return;
        }
        var startAt = String((document.getElementById('taskDatesStartAt') || {}).value || '').trim();
        var dueAt = String((document.getElementById('taskDatesDueAt') || {}).value || '').trim();
        var endAt = String((document.getElementById('taskDatesEndAt') || {}).value || '').trim();
        var body = { row_version: currentTask.row_version };
        if (startAt) body.start_at = startAt.replace('T', ' ');
        if (dueAt) body.due_at = dueAt.replace('T', ' ');
        if (endAt) body.end_at = endAt.replace('T', ' ');
        try {
          var envelope = await window.CRM.api.request('api/v1/tasks/' + taskId, {
            method: 'PATCH',
            body: body
          });
          currentTask = mergeTaskState(extractTaskPayload(envelope));
          renderTaskSidebarSummary();
          var subtitle = document.querySelector('.crm-subtitle');
          if (subtitle) {
            subtitle.textContent = 'Проект: ' + (currentTask.project_title || '—')
              + ' · Дедлайн: ' + (currentTask.due_at ? formatDate(currentTask.due_at) : 'не задан');
          }
          datesForm.classList.add('d-none');
          await loadTaskActivity(taskId);
          notify('Сроки задачи обновлены');
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || 'Не удалось обновить сроки задачи', 'error');
        }
      });
      datesForm.dataset.bound = '1';
    }

    var summaryCard = document.getElementById('taskSummaryCard');
    var descSection = document.getElementById('detailDesc');
    [summaryCard, descSection].forEach(function (container) {
      if (!container || container.dataset.inlineBound === '1') return;
      container.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-task-inline-toggle]');
        if (toggle) {
          var block = String(toggle.getAttribute('data-task-inline-toggle') || '');
          if (block === 'description') {
            if (!currentTaskPermissions.canEditIdentity) {
              notify('Изменение описания доступно только автору задачи', 'warning');
              return;
            }
            var descriptionForm = document.getElementById('taskDescriptionInlineForm');
            if (descriptionForm) descriptionForm.classList.remove('d-none');
          }
          if (block === 'assignee') {
            if (!currentTaskPermissions.canEditAssignment) {
              notify('Изменение исполнителя доступно только автору задачи', 'warning');
              return;
            }
            var assigneeInlineForm = document.getElementById('taskAssigneeInlineForm');
            if (assigneeInlineForm) assigneeInlineForm.classList.remove('d-none');
          }
          if (block === 'manager') {
            if (!currentTaskPermissions.canEditAssignment) {
              notify('Изменение менеджера доступно только автору задачи', 'warning');
              return;
            }
            var managerInlineForm = document.getElementById('taskManagerInlineForm');
            if (managerInlineForm) managerInlineForm.classList.remove('d-none');
          }
          if (block === 'project') {
            if (!currentTaskPermissions.canEditProject) {
              notify('Изменение проекта доступно только автору задачи', 'warning');
              return;
            }
            var projectInlineForm = document.getElementById('taskProjectInlineForm');
            if (projectInlineForm) projectInlineForm.classList.remove('d-none');
          }
          if (block === 'tags') {
            if (!currentTaskPermissions.canEditTags) {
              notify('Изменение тегов доступно только автору задачи', 'warning');
              return;
            }
            var tagsInlineForm = document.getElementById('taskTagsInlineForm');
            if (tagsInlineForm) tagsInlineForm.classList.remove('d-none');
          }
          if (block === 'dates') {
            if (!currentTaskPermissions.canEditIdentity) {
              notify('Изменение сроков доступно только автору задачи', 'warning');
              return;
            }
            var datesInlineForm = document.getElementById('taskDatesInlineForm');
            if (datesInlineForm) datesInlineForm.classList.remove('d-none');
          }
          return;
        }

        var cancel = e.target.closest('[data-task-inline-cancel]');
        if (!cancel) return;
        var cancelTarget = String(cancel.getAttribute('data-task-inline-cancel') || '');
        if (cancelTarget === 'description' && descForm) descForm.classList.add('d-none');
        if (cancelTarget === 'assignee' && assigneeForm) assigneeForm.classList.add('d-none');
        if (cancelTarget === 'manager' && managerForm) managerForm.classList.add('d-none');
        if (cancelTarget === 'tags' && tagsForm) tagsForm.classList.add('d-none');
        if (cancelTarget === 'project' && projectForm) projectForm.classList.add('d-none');
        if (cancelTarget === 'dates' && datesForm) datesForm.classList.add('d-none');
      });
      container.dataset.inlineBound = '1';
    });
  }

  function renderTaskMetaChips() {
    var chips = document.getElementById('taskMetaChips');
    if (!chips || !currentTask) return;

    var tagsHtml = currentTaskTags.map(function (tag) {
      return '<span class="crm-chip" data-tag-id="' + escapeHtml(tag.public_id || '') + '">' + escapeHtml(tag.title || tag.code || tag.public_id || '') + '</span>';
    }).join('');

    chips.innerHTML = ''
      + '<span id="taskStatusBadge" class="crm-badge ' + statusBadgeClass(currentTask.status_code) + '">' + escapeHtml(statusLabel(currentTask.status_code)) + '</span>'
      + '<span class="crm-chip" id="taskPriorityChip">' + escapeHtml(priorityLabel(currentTask.priority_code)) + '</span>'
      + tagsHtml;
  }

  async function loadTaskTags(taskId) {
    try {
      var envelope = await window.CRM.api.request('api/v1/tasks/' + taskId + '/tags');
      currentTaskTags = window.CRM.api.items(envelope);
    } catch (e) {
      currentTaskTags = [];
    }
  }

  async function loadProjectForTask() {
    if (!currentTask || !currentTask.project_public_id) {
      currentProject = null;
      return;
    }
    try {
      var envelope = await window.CRM.api.request('api/v1/projects/' + currentTask.project_public_id);
      currentProject = envelope && envelope.data ? envelope.data.project : null;
    } catch (e) {
      currentProject = null;
    }
  }

  async function loadTaskReferenceData() {
    try {
      var usersEnvelope = await window.CRM.api.request('api/v1/users', { query: { limit: 100, is_active: 1 } });
      availableUsers = window.CRM.api.items(usersEnvelope);
    } catch (e) {
      availableUsers = [];
    }

    try {
      var tagsEnvelope = await window.CRM.api.request('api/v1/tags', { query: { limit: 100 } });
      availableTags = window.CRM.api.items(tagsEnvelope);
    } catch (e) {
      availableTags = [];
    }

    try {
      var projectsEnvelope = await window.CRM.api.request('api/v1/projects', { query: { limit: 100 } });
      availableProjects = window.CRM.api.items(projectsEnvelope);
    } catch (e) {
      availableProjects = [];
    }

    await loadTaskStatusesDictionary();

    renderCreateTaskProjectOptions();
  }

  function renderTaskManagePanel(permissions) {
    var panel = document.getElementById('taskManagePanel');
    if (!panel) return;
    var canEditIdentity = Boolean(permissions && permissions.canEditIdentity);
    var canEditWorkflow = Boolean(permissions && permissions.canEditWorkflow);
    var canEditAssignment = Boolean(permissions && permissions.canEditAssignment);
    var canEditProject = Boolean(permissions && permissions.canEditProject);
    var canEditTags = Boolean(permissions && permissions.canEditTags);

    var assigneeOptions = ['<option value="">Не назначен</option>'].concat(availableUsers.map(function (u) {
      var selected = currentTask && currentTask.assignee_user_public_id && String(currentTask.assignee_user_public_id) === String(u.public_id) ? ' selected' : '';
      return '<option value="' + escapeHtml(u.public_id || '') + '"' + selected + '>' + escapeHtml(u.full_name || u.login || u.public_id || '') + '</option>';
    })).join('');

    var managerOptions = ['<option value="">Не назначен</option>'].concat(availableUsers.map(function (u) {
      var selected = currentTask && currentTask.project_manager_user_public_id && String(currentTask.project_manager_user_public_id) === String(u.public_id) ? ' selected' : '';
      return '<option value="' + escapeHtml(u.public_id || '') + '"' + selected + '>' + escapeHtml(u.full_name || u.login || u.public_id || '') + '</option>';
    })).join('');

    var projectOptions = ['<option value="">Без проекта</option>'].concat(availableProjects.map(function (project) {
      var selected = currentTask && currentTask.project_public_id && String(currentTask.project_public_id) === String(project.public_id) ? ' selected' : '';
      return '<option value="' + escapeHtml(project.public_id || '') + '"' + selected + '>' + escapeHtml(project.title || project.public_id || '') + '</option>';
    })).join('');

    var currentTagIds = currentTaskTags.map(function (t) { return String(t.public_id || ''); });
    var tagOptions = availableTags.map(function (t) {
      var selected = currentTagIds.indexOf(String(t.public_id || '')) >= 0 ? ' selected' : '';
      return '<option value="' + escapeHtml(t.public_id || '') + '"' + selected + '>' + escapeHtml(t.title || t.code || t.public_id || '') + '</option>';
    }).join('');

    var fallbackTaskStatuses = [
      { code: 'new', title: 'К выполнению', sort_order: 10 },
      { code: 'in_progress', title: 'В работе', sort_order: 20 },
      { code: 'blocked', title: 'Блокировано', sort_order: 30 },
      { code: 'done', title: 'Готово', sort_order: 40 }
    ];
    var taskStatuses = (availableTaskStatuses && availableTaskStatuses.length) ? availableTaskStatuses.slice() : fallbackTaskStatuses.slice();
    if (currentTask && currentTask.status_code) {
      var currentCode = String(currentTask.status_code);
      var exists = taskStatuses.some(function (item) { return String(item.code) === currentCode; });
      if (!exists) {
        taskStatuses.push({
          code: currentCode,
          title: statusLabel(currentCode),
          sort_order: 999
        });
      }
    }
    taskStatuses.sort(function (a, b) { return Number(a.sort_order || 0) - Number(b.sort_order || 0); });
    var statusOptions = taskStatuses.map(function (item) {
      var code = String(item.code || '');
      var selected = currentTask && String(currentTask.status_code || '') === code ? ' selected' : '';
      return '<option value="' + escapeHtml(code) + '"' + selected + '>' + escapeHtml(item.title || code) + '</option>';
    }).join('');

    var currentTagTitles = currentTaskTags.length
      ? currentTaskTags.map(function (tag) { return escapeHtml(tag.title || tag.code || tag.public_id || '—'); }).join(', ')
      : 'Нет тегов';
    var currentProjectTitle = 'Без проекта';
    if (currentTask && currentTask.project_public_id) {
      var selectedProject = availableProjects.find(function (project) {
        return String(project.public_id || '') === String(currentTask.project_public_id || '');
      });
      currentProjectTitle = selectedProject
        ? String(selectedProject.title || selectedProject.public_id || 'Без проекта')
        : String(currentTask.project_public_id || 'Без проекта');
    }

    panel.innerHTML = ''
      + '<div class="crm-card p-3 bg-light-subtle">'
      + '<div class="d-flex justify-content-between align-items-center mb-3"><h3 class="h6 mb-0">Параметры задачи</h3><small class="text-muted">'
      + (canEditIdentity ? 'Вы автор задачи: доступно редактирование всех параметров.' : (canEditWorkflow ? 'Вы исполнитель задачи: доступно рабочее изменение статуса и приоритета.' : 'Редактирование параметров недоступно.'))
      + '</small></div>'
      + '<div class="row g-3">'
      + '<div class="col-lg-12">'
      + '<article class="border rounded-3 p-3 h-100">'
      + '<div class="d-flex justify-content-between align-items-center mb-2"><h4 class="h6 mb-0">Название и описание</h4><button type="button" class="btn btn-sm btn-light" data-task-edit-toggle="identity"' + (canEditIdentity ? '' : ' disabled') + '>✏️</button></div>'
      + '<div class="small text-muted mb-1">Название</div><div class="mb-2">' + escapeHtml(currentTask && currentTask.title || '—') + '</div>'
      + '<div class="small text-muted mb-1">Описание</div><div class="mb-2">' + escapeHtml(currentTask && currentTask.description || 'Описание отсутствует') + '</div>'
      + '<form class="row g-2 d-none" data-task-manage-form="identity">'
      + '<div class="col-md-6"><label class="form-label">Название</label><input class="form-control" name="title" maxlength="255" value="' + escapeHtml(currentTask && currentTask.title || '') + '"></div>'
      + '<div class="col-md-6"><label class="form-label">Описание</label><textarea class="form-control" name="description" rows="3">' + escapeHtml(currentTask && currentTask.description || '') + '</textarea></div>'
      + '<div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-sm crm-btn-primary">Сохранить</button><button type="button" class="btn btn-sm btn-light" data-task-edit-cancel="identity">Отмена</button></div>'
      + '</form>'
      + '</article>'
      + '</div>'
      + '<div class="col-lg-6">'
      + '<article class="border rounded-3 p-3 h-100">'
      + '<div class="d-flex justify-content-between align-items-center mb-2"><h4 class="h6 mb-0">Статус и приоритет</h4><button type="button" class="btn btn-sm btn-light" data-task-edit-toggle="workflow"' + (canEditWorkflow ? '' : ' disabled') + '>✏️</button></div>'
      + '<div class="small text-muted mb-1">Статус</div><div class="mb-2"><span class="crm-badge ' + statusBadgeClass(currentTask && currentTask.status_code || 'new') + '">' + escapeHtml(statusLabel(currentTask && currentTask.status_code || 'new')) + '</span></div>'
      + '<div class="small text-muted mb-1">Приоритет</div><div class="mb-2">' + escapeHtml(priorityLabel(currentTask && currentTask.priority_code || 'normal')) + '</div>'
      + '<form class="row g-2 d-none" data-task-manage-form="workflow">'
      + '<div class="col-6"><label class="form-label">Статус</label><select class="form-select" name="status">' + statusOptions + '</select></div>'
      + '<div class="col-6"><label class="form-label">Приоритет</label><select class="form-select" name="priority">'
      + '<option value="low"' + (currentTask && currentTask.priority_code === 'low' ? ' selected' : '') + '>Низкий</option>'
      + '<option value="normal"' + (currentTask && currentTask.priority_code === 'normal' ? ' selected' : '') + '>Нормальный</option>'
      + '<option value="high"' + (currentTask && currentTask.priority_code === 'high' ? ' selected' : '') + '>Высокий</option>'
      + '<option value="urgent"' + (currentTask && currentTask.priority_code === 'urgent' ? ' selected' : '') + '>Срочный</option>'
      + '</select></div>'
      + '<div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-sm crm-btn-primary">Сохранить</button><button type="button" class="btn btn-sm btn-light" data-task-edit-cancel="workflow">Отмена</button></div>'
      + '</form>'
      + '</article>'
      + '</div>'
      + '<div class="col-lg-6">'
      + '<article class="border rounded-3 p-3 h-100">'
      + '<div class="d-flex justify-content-between align-items-center mb-2"><h4 class="h6 mb-0">Исполнители</h4><button type="button" class="btn btn-sm btn-light" data-task-edit-toggle="assignment"' + (canEditAssignment ? '' : ' disabled') + '>✏️</button></div>'
      + '<div class="small text-muted mb-1">Исполнитель</div><div class="mb-2">' + escapeHtml(currentTask && (currentTask.assignee_name || currentTask.assignee_login || currentTask.assignee_user_public_id) || 'Не назначен') + '</div>'
      + '<div class="small text-muted mb-1">Менеджер проекта</div><div class="mb-2">' + escapeHtml(currentTask && (currentTask.project_manager_name || currentTask.project_manager_user_public_id) || 'Не назначен') + '</div>'
      + '<form class="row g-2 d-none" data-task-manage-form="assignment">'
      + '<div class="col-6"><label class="form-label">Исполнитель</label><select class="form-select" name="assignee_user_public_id">' + assigneeOptions + '</select></div>'
      + '<div class="col-6"><label class="form-label">Менеджер проекта</label><select class="form-select" name="manager_user_public_id">' + managerOptions + '</select></div>'
      + '<div class="col-12"><small class="text-muted">Менеджер назначается на выбранный проект. Если у задачи нет проекта, сначала выберите проект в блоке «Проект».</small></div>'
      + '<div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-sm crm-btn-primary">Сохранить</button><button type="button" class="btn btn-sm btn-light" data-task-edit-cancel="assignment">Отмена</button></div>'
      + '</form>'
      + '</article>'
      + '</div>'
      + '<div class="col-lg-6">'
      + '<article class="border rounded-3 p-3 h-100">'
      + '<div class="d-flex justify-content-between align-items-center mb-2"><h4 class="h6 mb-0">Проект</h4><button type="button" class="btn btn-sm btn-light" data-task-edit-toggle="project"' + (canEditProject ? '' : ' disabled') + '>✏️</button></div>'
      + '<div class="small text-muted mb-1">Текущий проект</div><div class="mb-2">' + escapeHtml(currentProjectTitle) + '</div>'
      + '<form class="row g-2 d-none" data-task-manage-form="project">'
      + '<div class="col-12"><label class="form-label">Проект</label><select class="form-select" name="project_public_id">' + projectOptions + '</select></div>'
      + '<div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-sm crm-btn-primary">Сохранить</button><button type="button" class="btn btn-sm btn-light" data-task-edit-cancel="project">Отмена</button></div>'
      + '</form>'
      + '</article>'
      + '</div>'
      + '<div class="col-lg-6">'
      + '<article class="border rounded-3 p-3 h-100">'
      + '<div class="d-flex justify-content-between align-items-center mb-2"><h4 class="h6 mb-0">Теги</h4><button type="button" class="btn btn-sm btn-light" data-task-edit-toggle="tags"' + (canEditTags ? '' : ' disabled') + '>✏️</button></div>'
      + '<div class="small text-muted mb-1">Назначенные теги</div><div class="mb-2">' + currentTagTitles + '</div>'
      + '<form class="row g-2 d-none" data-task-manage-form="tags">'
      + '<div class="col-12"><label class="form-label">Теги</label><select class="form-select" name="tag_public_ids" multiple size="5">' + tagOptions + '</select></div>'
      + '<div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-sm crm-btn-primary">Сохранить</button><button type="button" class="btn btn-sm btn-light" data-task-edit-cancel="tags">Отмена</button></div>'
      + '</form>'
      + '</article>'
      + '</div>'
      + '</div>'
      + '</div>';
  }

  function renderTaskComments(items) {
    var list = document.getElementById('commentsList');
    if (!list) return;
    setTaskTabCounter('detailCommentsCounter', Array.isArray(items) ? items.length : 0);

    list.innerHTML = items.length ? items.map(function (item) {
      var canEditComment = currentUserPublicId !== ''
        && String(item.author_public_id || '') === currentUserPublicId;
      var editButton = canEditComment
        ? '<button type="button" class="btn btn-sm btn-light" data-comment-edit="' + escapeHtml(item.public_id || '') + '">Редактировать</button>'
        : '';
      var commentId = String(item.public_id || '');
      var ownReaction = currentTaskOwnReactionsByComment[commentId] || null;
      var reactionLabel = ownReaction && ownReaction.reaction
        ? ('Моя реакция: ' + String(ownReaction.reaction))
        : 'Без реакции';

      return '<div class="crm-comment mb-2" data-comment-id="' + escapeHtml(item.public_id || '') + '" data-comment-author="' + escapeHtml(item.author_public_id || '') + '">'
        + '<div class="d-flex justify-content-between align-items-start gap-2">'
        + '<div><strong>' + escapeHtml(item.author_name || item.author_login || 'Пользователь') + '</strong></div>'
        + editButton
        + '</div>'
        + '<p class="mb-1" data-comment-body="' + escapeHtml(item.public_id || '') + '">' + escapeHtml(item.body || '') + '</p>'
        + '<div class="d-flex gap-2 flex-wrap align-items-center mb-1">'
        + '<button type="button" class="btn btn-sm btn-light crm-btn-compact" data-comment-react="' + escapeHtml(commentId) + '" data-reaction="like">👍</button>'
        + '<button type="button" class="btn btn-sm btn-light crm-btn-compact" data-comment-react="' + escapeHtml(commentId) + '" data-reaction="love">❤️</button>'
        + '<button type="button" class="btn btn-sm btn-light crm-btn-compact" data-comment-react="' + escapeHtml(commentId) + '" data-reaction="up">⬆️</button>'
        + '<button type="button" class="btn btn-sm btn-light crm-btn-compact" data-comment-reaction-clear="' + escapeHtml(commentId) + '"' + (ownReaction ? '' : ' disabled') + '>Снять</button>'
        + '<small class="text-muted">' + escapeHtml(reactionLabel) + '</small>'
        + '</div>'
        + '<small class="text-muted">' + escapeHtml(formatDate(item.created_at)) + '</small>'
        + '</div>';
    }).join('') : '<div class="crm-empty"><h3 class="h6">Комментариев пока нет</h3><p class="text-muted mb-0">Добавьте первый комментарий к задаче.</p></div>';
  }

  function matchMentionedUsersFromText(text) {
    var content = String(text || '');
    if (!content) return [];
    var matches = content.match(/@([a-zA-Z0-9_.-]+)/g) || [];
    if (!matches.length) return [];
    var result = [];
    var seen = {};
    matches.forEach(function (token) {
      var key = String(token || '').replace(/^@/, '').trim().toLowerCase();
      if (!key || seen[key]) return;
      seen[key] = true;
      var user = availableUsers.find(function (item) {
        var login = String(item && item.login || '').trim().toLowerCase();
        var fullName = String(item && item.full_name || '').trim().toLowerCase();
        return key === login || key === fullName;
      });
      if (user && user.public_id) {
        result.push(String(user.public_id));
      }
    });
    return result;
  }

  async function loadTaskCollaborationState(taskId) {
    var followBtn = document.getElementById('taskFollowBtn');
    var favoriteBtn = document.getElementById('taskFavoriteBtn');
    if (!taskId) return;

    async function safeRequest(route, options) {
      try {
        return await window.CRM.api.request(route, options || {});
      } catch (e) {
        return { success: false, data: { items: [] } };
      }
    }

    var reactionsEnvelope = await safeRequest('api/v1/reactions', {
      query: {
        entity_type: 'comment',
        limit: 200
      }
    });
    var ownReactions = window.CRM.api.items(reactionsEnvelope);
    currentTaskOwnReactionsByComment = {};
    ownReactions.forEach(function (item) {
      var key = String(item.entity_public_id || '').trim();
      if (!key) return;
      currentTaskOwnReactionsByComment[key] = item;
    });

    var subscriptionsEnvelope = await safeRequest('api/v1/subscriptions', {
      query: {
        entity_type: 'task',
        entity_public_id: taskId,
        limit: 5
      }
    });
    var subs = window.CRM.api.items(subscriptionsEnvelope);
    currentTaskFollowSubscription = subs.length ? subs[0] : null;
    if (followBtn) {
      followBtn.textContent = currentTaskFollowSubscription ? 'Не отслеживать задачу' : 'Отслеживать задачу';
      followBtn.classList.toggle('crm-btn-primary', Boolean(currentTaskFollowSubscription));
      followBtn.classList.toggle('crm-btn-secondary', !currentTaskFollowSubscription);
    }

    var favoritesEnvelope = await safeRequest('api/v1/favorites', {
      query: {
        entity_type: 'task',
        entity_public_id: taskId,
        limit: 5
      }
    });
    var favorites = window.CRM.api.items(favoritesEnvelope);
    currentTaskFavorite = favorites.length ? favorites[0] : null;
    if (favoriteBtn) {
      favoriteBtn.textContent = currentTaskFavorite ? 'Убрать из избранного' : 'В избранное';
      favoriteBtn.classList.toggle('crm-btn-primary', Boolean(currentTaskFavorite));
      favoriteBtn.classList.toggle('crm-btn-secondary', !currentTaskFavorite);
    }
  }

  async function loadTaskComments(taskId) {
    var commentsEnvelope = await window.CRM.api.request('api/v1/tasks/' + taskId + '/comments');
    var items = window.CRM.api.items(commentsEnvelope);
    renderTaskComments(items);
    return items;
  }

  function renderTaskFiles(files) {
    var list = document.getElementById('taskFilesList');
    if (!list) return;

    list.innerHTML = files.length ? files.map(function (file) {
      var displayName = String(
        file.original_name
        || file.name
        || file.file_name
        || file.filename
        || file.public_id
        || 'Файл'
      );
      return '<div class="crm-file-item mb-2 d-flex justify-content-between align-items-center">'
        + '<div><strong>' + escapeHtml(displayName) + '</strong><div class="small text-muted">'
        + escapeHtml(formatDate(file.created_at || new Date().toISOString())) + '</div></div>'
        + '<button type="button" class="btn btn-sm btn-light" data-file-download="' + escapeHtml(String(file.public_id || '')) + '" data-file-name="' + escapeHtml(displayName) + '">Скачать</button>'
        + '</div>';
    }).join('') : '<div class="text-muted">Файлы к задаче пока не загружены.</div>';
  }

  async function loadTaskFiles(taskId) {
    try {
      var envelope = await window.CRM.api.request('api/v1/tasks/' + taskId + '/files');
      currentTaskFiles = window.CRM.api.items(envelope);
      renderTaskFiles(currentTaskFiles);
    } catch (e) {
      currentTaskFiles = [];
      renderTaskFiles([]);
    }
  }

  function subtaskStatusOptions(selectedStatus) {
    return orderedTaskStatuses(selectedStatus).map(function (item) {
      var selected = String(selectedStatus || '') === item.code ? ' selected' : '';
      return '<option value="' + escapeHtml(item.code) + '"' + selected + '>' + escapeHtml(item.title) + '</option>';
    }).join('');
  }

  function subtaskPriorityOptions(selectedPriority) {
    var priorities = [
      { code: 'low', title: 'Низкий' },
      { code: 'normal', title: 'Нормальный' },
      { code: 'high', title: 'Высокий' },
      { code: 'urgent', title: 'Срочный' }
    ];
    return priorities.map(function (item) {
      var selected = String(selectedPriority || 'normal') === item.code ? ' selected' : '';
      return '<option value="' + escapeHtml(item.code) + '"' + selected + '>' + escapeHtml(item.title) + '</option>';
    }).join('');
  }

  function toDateInputValue(value) {
    var raw = String(value || '').trim();
    if (!raw) return '';
    if (raw.indexOf(' ') > -1) return raw.slice(0, 10);
    if (raw.indexOf('T') > -1) return raw.slice(0, 10);
    return raw.slice(0, 10);
  }

  function toDateTimeLocalValue(value) {
    var raw = String(value || '').trim();
    if (!raw) return '';
    if (raw.indexOf('T') > -1) return raw.slice(0, 16);
    if (raw.indexOf(' ') > -1) return raw.slice(0, 10) + 'T' + raw.slice(11, 16);
    return raw.slice(0, 10) + 'T00:00';
  }

  function collectSelectedValues(selectNode) {
    if (!selectNode || !selectNode.selectedOptions) return [];
    return Array.prototype.slice.call(selectNode.selectedOptions)
      .map(function (option) { return String(option.value || '').trim(); })
      .filter(Boolean);
  }

  async function loadTaskTagIds(taskPublicId) {
    var targetTaskId = String(taskPublicId || '').trim();
    if (!targetTaskId) return [];
    try {
      var envelope = await window.CRM.api.request('api/v1/tasks/' + targetTaskId + '/tags');
      return window.CRM.api.items(envelope).map(function (tag) {
        return String(tag.public_id || '').trim();
      }).filter(Boolean);
    } catch (e) {
      return [];
    }
  }

  function renderSubtaskFormProjectOption(formNode) {
    if (!formNode) return;
    var projectSelect = formNode.querySelector('[name="project_public_id"]');
    if (!projectSelect) return;

    var currentProjectId = String((currentTask && currentTask.project_public_id) || '').trim();
    var currentProjectTitle = String((currentTask && currentTask.project_title) || '').trim();
    var optionTitle = currentProjectTitle || (currentProjectId ? currentProjectId : 'Без проекта');
    projectSelect.innerHTML = '<option value="' + escapeHtml(currentProjectId) + '" selected>' + escapeHtml(optionTitle) + '</option>';
  }

  function renderSubtaskFormStatusOptions(formNode, selectedStatus) {
    if (!formNode) return;
    var statusSelect = formNode.querySelector('[name="status"]');
    if (!statusSelect) return;
    var status = String(selectedStatus || 'new');
    statusSelect.innerHTML = subtaskStatusOptions(status);
    statusSelect.value = status;
  }

  function renderSubtaskFormPriorityOptions(formNode, selectedPriority) {
    if (!formNode) return;
    var prioritySelect = formNode.querySelector('[name="priority"]');
    if (!prioritySelect) return;
    var priority = String(selectedPriority || 'normal');
    prioritySelect.innerHTML = subtaskPriorityOptions(priority);
    prioritySelect.value = priority;
  }

  function renderSubtaskFormAssigneeOptions(formNode, selectedAssigneePublicId) {
    if (!formNode) return;
    var assigneeSelect = formNode.querySelector('[name="assignee_user_public_id"]');
    if (!assigneeSelect) return;
    var selectedAssignee = String(selectedAssigneePublicId || '').trim();
    assigneeSelect.innerHTML = '<option value="">Не назначен</option>' + availableUsers.map(function (user) {
      var label = String(user.full_name || user.login || user.public_id || '').trim();
      var value = String(user.public_id || '');
      var selected = selectedAssignee === value ? ' selected' : '';
      return '<option value="' + escapeHtml(value) + '"' + selected + '>' + escapeHtml(label || 'Пользователь') + '</option>';
    }).join('');
  }

  function renderSubtaskFormTagOptions(formNode, selectedTagIds) {
    if (!formNode) return;
    var tagsSelect = formNode.querySelector('[name="tag_public_ids"]');
    if (!tagsSelect) return;
    var selectedSet = {};
    (selectedTagIds || []).forEach(function (id) {
      selectedSet[String(id || '')] = true;
    });
    tagsSelect.innerHTML = availableTags.map(function (tag) {
      var value = String(tag.public_id || '');
      var title = String(tag.title || value || '').trim();
      var selected = selectedSet[value] ? ' selected' : '';
      return '<option value="' + escapeHtml(value) + '"' + selected + '>' + escapeHtml(title) + '</option>';
    }).join('');
  }

  function fillSubtaskForm(formNode, item, selectedTagIds) {
    if (!formNode) return;
    var subtask = item || {};
    var titleInput = formNode.querySelector('[name="title"]');
    var descInput = formNode.querySelector('[name="description"]');
    var startInput = formNode.querySelector('[name="start_at"]');
    var dueInput = formNode.querySelector('[name="due_at"]');
    var endInput = formNode.querySelector('[name="end_at"]');

    renderSubtaskFormProjectOption(formNode);
    renderSubtaskFormStatusOptions(formNode, String(subtask.status_code || 'new'));
    renderSubtaskFormPriorityOptions(formNode, String(subtask.priority_code || 'normal'));
    renderSubtaskFormAssigneeOptions(formNode, String(subtask.assignee_user_public_id || ''));
    renderSubtaskFormTagOptions(formNode, selectedTagIds || []);

    if (titleInput) titleInput.value = String(subtask.title || '');
    if (descInput) descInput.value = String(subtask.description || '');
    if (startInput) startInput.value = toDateInputValue(subtask.start_at);
    if (dueInput) dueInput.value = toDateInputValue(subtask.due_at);
    if (endInput) endInput.value = toDateInputValue(subtask.end_at);
  }

  function isSubtaskAuthor(item) {
    var actorId = getCurrentUserPublicId();
    if (!actorId) return false;
    return String(item && item.creator_user_public_id || '') === actorId;
  }

  function renderSubtasks(items, canWorkTask, canCreateTask) {
    var list = document.getElementById('subtasksList');
    if (!list) return;
    setTaskTabCounter('detailSubtasksCounter', Array.isArray(items) ? items.length : 0);

    if (!items.length) {
      list.innerHTML = '<div class="text-muted">Подзадач пока нет. Нажмите «Создать подзадачу», чтобы создать первую.</div>';
      return;
    }

    list.innerHTML = '<div class="table-responsive"><table class="table align-middle crm-subtasks-table mb-0">'
      + '<thead><tr>'
      + '<th>Подзадача</th>'
      + '<th>Дедлайн</th>'
      + '<th>Статус</th>'
      + '<th>Приоритет</th>'
      + '<th class="text-end">Действия</th>'
      + '</tr></thead><tbody>'
      + items.map(function (item) {
        var subtaskId = String(item.public_id || '');
        var dueLabel = item.due_at ? formatDate(item.due_at) : 'Без дедлайна';
        var canEditSubtask = canCreateTask && isSubtaskAuthor(item);
        var authorLabel = resolveUserDisplayName(item.creator_name || '', item.creator_user_public_id || '', 'Не указан');
        return '<tr data-subtask-id="' + escapeHtml(subtaskId) + '">'
          + '<td>'
          + '<a class="crm-subtask-link fw-semibold" href="index.php?route=task-detail&task_public_id=' + encodeURIComponent(subtaskId) + '">'
          + escapeHtml(item.title || 'Без названия')
          + '</a>'
          + '<div class="small text-muted mt-1">Автор: ' + escapeHtml(authorLabel) + '</div>'
          + '</td>'
          + '<td>' + escapeHtml(dueLabel) + '</td>'
          + '<td style="min-width:190px">'
          + '<select class="form-select form-select-sm" data-subtask-status="' + escapeHtml(subtaskId) + '"' + (canWorkTask ? '' : ' disabled') + '>'
          + subtaskStatusOptions(item.status_code || 'new')
          + '</select>'
          + '</td>'
          + '<td><span class="crm-chip">' + escapeHtml(priorityLabel(item.priority_code || 'normal')) + '</span></td>'
          + '<td class="text-end">'
          + '<div class="d-inline-flex gap-2">'
          + '<a class="btn btn-sm btn-light crm-btn-compact" href="index.php?route=task-detail&task_public_id=' + encodeURIComponent(subtaskId) + '">Открыть</a>'
          + '<button type="button" class="btn btn-sm crm-btn-primary crm-btn-compact" data-subtask-edit="' + escapeHtml(subtaskId) + '"' + (canEditSubtask ? '' : ' disabled') + '>Редактировать</button>'
          + '</div>'
          + '</td>'
          + '</tr>';
      }).join('')
      + '</tbody></table></div>';
  }

  async function loadSubtasks(taskId, canWorkTask, canCreateTask) {
    try {
      var envelope = await window.CRM.api.request('api/v1/tasks/' + taskId + '/subtasks');
      currentTaskSubtasks = window.CRM.api.items(envelope);
      renderSubtasks(currentTaskSubtasks, canWorkTask, canCreateTask);
    } catch (e) {
      currentTaskSubtasks = [];
      renderSubtasks([], canWorkTask, canCreateTask);
    }
  }

  function checklistProgressMeta(items) {
    var list = Array.isArray(items) ? items : [];
    var total = list.length;
    var done = list.filter(function (item) { return Number(item && item.is_done || 0) === 1; }).length;
    var percent = total > 0 ? Math.round((done / total) * 100) : 0;
    return { total: total, done: done, percent: percent };
  }

  function buildChecklistDraft(checklist) {
    var source = checklist && typeof checklist === 'object' ? checklist : {};
    var items = Array.isArray(source.items) ? source.items : [];
    return {
      title: String(source.title || ''),
      items: items.map(function (item) {
        return {
          public_id: String(item && item.public_id || ''),
          title: String(item && item.title || ''),
          is_done: Number(item && item.is_done || 0) === 1 ? 1 : 0,
          sort_order: Number(item && item.sort_order || 0),
          _is_new: false,
          _deleted: false
        };
      })
    };
  }

  function getChecklistById(checklistId) {
    var id = String(checklistId || '');
    for (var i = 0; i < currentTaskChecklists.length; i += 1) {
      var checklist = currentTaskChecklists[i];
      if (String(checklist && checklist.public_id || '') === id) return checklist;
    }
    return null;
  }

  function renderChecklistViewMode(checklist, canEditTask) {
    var checklistId = String(checklist && checklist.public_id || '');
    var checklistItems = Array.isArray(checklist && checklist.items) ? checklist.items : [];
    var progress = checklistProgressMeta(checklistItems);
    var canAdd = checklistViewAddItemState[checklistId] === true;
    return ''
      + '<article class="crm-checklist-card" data-checklist-id="' + escapeHtml(checklistId) + '">'
      + '<header class="crm-checklist-head">'
      + '<div class="crm-checklist-head-main">'
      + '<div class="crm-checklist-title">' + escapeHtml(checklist.title || 'Без названия') + '</div>'
      + '<div class="crm-checklist-progress-line">'
      + '<span class="crm-checklist-progress-copy">' + escapeHtml(String(progress.done)) + ' из ' + escapeHtml(String(progress.total)) + ' выполнено</span>'
      + '<div class="crm-checklist-progress-bar" role="progressbar" aria-label="Прогресс чеклиста"><span style="width:' + escapeHtml(String(progress.percent)) + '%"></span></div>'
      + '<span class="crm-checklist-progress-percent">' + escapeHtml(String(progress.percent)) + '%</span>'
      + '</div>'
      + '</div>'
      + '<div class="crm-checklist-head-actions">'
      + '<button class="btn btn-sm btn-light crm-btn-compact" type="button" data-checklist-edit="' + escapeHtml(checklistId) + '"' + (canEditTask ? '' : ' disabled') + '>Редактировать</button>'
      + '<button class="btn btn-sm btn-light crm-btn-compact" type="button" data-checklist-more="' + escapeHtml(checklistId) + '"' + (canEditTask ? '' : ' disabled') + '>...</button>'
      + '</div>'
      + '</header>'
      + '<ul class="crm-checklist-view-items">'
      + (checklistItems.length ? checklistItems.map(function (item) {
        var done = Number(item && item.is_done || 0) === 1;
        return '<li class="crm-checklist-view-item' + (done ? ' is-done' : '') + '">'
          + '<label class="crm-checklist-view-label">'
          + '<input class="form-check-input mt-0" type="checkbox" data-checklist-item-toggle="' + escapeHtml(String(item && item.public_id || '')) + '"' + (done ? ' checked' : '') + (canEditTask ? '' : ' disabled') + '>'
          + '<span class="crm-checklist-view-title">' + escapeHtml(item && item.title || 'Без названия') + '</span>'
          + '</label>'
          + '<span class="crm-checklist-view-status">' + (done ? 'Выполнено' : 'Не выполнено') + '</span>'
          + '</li>';
      }).join('') : '<li class="crm-checklist-empty">Пунктов пока нет.</li>')
      + '</ul>'
      + (canEditTask
        ? '<div class="crm-checklist-view-add">'
          + (canAdd
            ? '<form class="d-flex gap-2" data-checklist-item-create-view="' + escapeHtml(checklistId) + '">'
              + '<input class="form-control form-control-sm" name="title" maxlength="255" placeholder="Новый пункт чеклиста" required>'
              + '<button class="btn btn-sm crm-btn-primary crm-btn-compact" type="submit">Добавить</button>'
              + '<button class="btn btn-sm btn-light crm-btn-compact" type="button" data-checklist-item-create-cancel="' + escapeHtml(checklistId) + '">Отмена</button>'
              + '</form>'
            : '<button class="btn btn-sm btn-link p-0 text-decoration-none" type="button" data-checklist-item-create-toggle="' + escapeHtml(checklistId) + '">+ Добавить пункт</button>')
          + '</div>'
        : '')
      + '</article>';
  }

  function renderChecklistEditMode(checklist, draft, canEditTask) {
    var checklistId = String(checklist && checklist.public_id || '');
    var draftItems = Array.isArray(draft && draft.items) ? draft.items.filter(function (item) { return item && item._deleted !== true; }) : [];
    var progress = checklistProgressMeta(draftItems);
    return ''
      + '<article class="crm-checklist-card is-editing" data-checklist-id="' + escapeHtml(checklistId) + '">'
      + '<form data-checklist-save="' + escapeHtml(checklistId) + '">'
      + '<header class="crm-checklist-head">'
      + '<div class="crm-checklist-head-main">'
      + '<input class="form-control crm-checklist-title-input" name="title" maxlength="255" value="' + escapeHtml(draft && draft.title || '') + '"' + (canEditTask ? '' : ' disabled') + '>'
      + '<div class="crm-checklist-progress-line">'
      + '<span class="crm-checklist-progress-copy">' + escapeHtml(String(progress.done)) + ' из ' + escapeHtml(String(progress.total)) + ' выполнено</span>'
      + '<div class="crm-checklist-progress-bar" role="progressbar" aria-label="Прогресс чеклиста"><span style="width:' + escapeHtml(String(progress.percent)) + '%"></span></div>'
      + '<span class="crm-checklist-progress-percent">' + escapeHtml(String(progress.percent)) + '%</span>'
      + '</div>'
      + '</div>'
      + '<div class="crm-checklist-head-actions">'
      + '<button class="btn btn-sm btn-light crm-btn-compact" type="button" data-checklist-edit-cancel="' + escapeHtml(checklistId) + '"' + (canEditTask ? '' : ' disabled') + '>Отмена</button>'
      + '<button class="btn btn-sm crm-btn-primary crm-btn-compact" type="submit"' + (canEditTask ? '' : ' disabled') + '>Сохранить</button>'
      + '</div>'
      + '</header>'
      + '<div class="crm-checklist-edit-items">'
      + (draftItems.length ? draftItems.map(function (item, index) {
        return '<div class="crm-checklist-edit-item">'
          + '<span class="crm-checklist-drag-handle" aria-hidden="true">⋮⋮</span>'
          + '<input class="form-check-input mt-0" type="checkbox" data-checklist-draft-done="' + escapeHtml(String(item.public_id || '')) + '"' + (Number(item.is_done || 0) === 1 ? ' checked' : '') + (canEditTask ? '' : ' disabled') + '>'
          + '<input class="form-control form-control-sm" data-checklist-draft-title="' + escapeHtml(String(item.public_id || '')) + '" maxlength="255" value="' + escapeHtml(item.title || '') + '"' + (canEditTask ? '' : ' disabled') + '>'
          + '<span class="crm-checklist-order-meta small text-muted" aria-hidden="true">#' + escapeHtml(String(index + 1)) + '</span>'
          + '<button class="btn btn-sm crm-btn-danger-icon" type="button" aria-label="Удалить пункт чеклиста" data-checklist-draft-delete="' + escapeHtml(String(item.public_id || '')) + '"' + (canEditTask ? '' : ' disabled') + '><span class="crm-icon" aria-hidden="true"><i class="fa-regular fa-trash-can"></i></span></button>'
          + '</div>';
      }).join('') : '<div class="crm-checklist-empty">Пунктов пока нет.</div>')
      + '</div>'
      + '<div class="crm-checklist-edit-add">'
      + '<button class="btn btn-sm btn-link p-0 text-decoration-none" type="button" data-checklist-draft-add-item="' + escapeHtml(checklistId) + '"' + (canEditTask ? '' : ' disabled') + '>+ Добавить пункт</button>'
      + '</div>'
      + '<div class="crm-checklist-edit-danger">'
      + '<button class="btn btn-sm crm-btn-danger crm-btn-compact" type="button" data-checklist-delete="' + escapeHtml(checklistId) + '"' + (canEditTask ? '' : ' disabled') + '>Удалить чеклист</button>'
      + '</div>'
      + '</form>'
      + '</article>';
  }

  function renderChecklists(items, canEditTask) {
    var list = document.getElementById('checklistsList');
    if (!list) return;
    setTaskTabCounter('detailChecklistsCounter', Array.isArray(items) ? items.length : 0);

    if (!items.length) {
      list.innerHTML = '<div class="text-muted">Чеклистов пока нет. Добавьте первый чеклист выше.</div>';
      return;
    }

    list.innerHTML = '<div class="vstack gap-3">' + items.map(function (checklist) {
      var checklistId = String(checklist && checklist.public_id || '');
      var isChecklistEditing = checklistActiveEditId === checklistId && !!checklistDraftState[checklistId];
      if (isChecklistEditing) {
        return renderChecklistEditMode(checklist, checklistDraftState[checklistId], canEditTask);
      }
      return renderChecklistViewMode(checklist, canEditTask);
    }).join('') + '</div>';
  }

  async function loadChecklists(taskId, canEditTask) {
    try {
      var envelope = await window.CRM.api.request('api/v1/tasks/' + taskId + '/checklists');
      var checklists = window.CRM.api.items(envelope);
      var itemResults = await Promise.all(checklists.map(function (checklist) {
        return window.CRM.api.request('api/v1/checklists/' + checklist.public_id + '/items')
          .then(function (resp) {
            checklist.items = window.CRM.api.items(resp);
          })
          .catch(function () {
            checklist.items = [];
          });
      }));
      void itemResults;
      currentTaskChecklists = checklists;

      if (checklistActiveEditId) {
        var exists = currentTaskChecklists.some(function (item) {
          return String(item && item.public_id || '') === checklistActiveEditId;
        });
        if (!exists) {
          checklistActiveEditId = '';
        }
      }
      if (checklistActiveEditId) {
        var sourceChecklist = getChecklistById(checklistActiveEditId);
        if (sourceChecklist) {
          checklistDraftState[checklistActiveEditId] = buildChecklistDraft(sourceChecklist);
        }
      }
      renderChecklists(currentTaskChecklists, canEditTask);
    } catch (e) {
      currentTaskChecklists = [];
      checklistActiveEditId = '';
      checklistDraftState = {};
      renderChecklists([], canEditTask);
    }
  }

  function renderTaskActivity(items) {
    var list = document.getElementById('taskActivityList');
    if (!list) return;

    function activityHeadline(item) {
      var raw = String(
        item.event_type
        || item.result_code
        || item.action
        || item.channel
        || ''
      ).toLowerCase();
      var route = String(item.request_route || '').toLowerCase();
      var joined = raw + ' ' + route;

      if (joined.indexOf('comment') >= 0) {
        if (joined.indexOf('delete') >= 0) return 'Удален комментарий';
        if (joined.indexOf('update') >= 0 || joined.indexOf('edit') >= 0 || joined.indexOf('patch') >= 0) return 'Изменен комментарий';
        return 'Добавлен комментарий';
      }
      if (joined.indexOf('status') >= 0) return 'Изменен статус задачи';
      if (joined.indexOf('assignee') >= 0) return 'Изменен исполнитель';
      if (joined.indexOf('manager') >= 0) return 'Изменен менеджер проекта';
      if (joined.indexOf('project') >= 0) return 'Изменен связанный проект';
      if (joined.indexOf('tag') >= 0) return 'Обновлены теги задачи';
      if (joined.indexOf('worklog') >= 0 || joined.indexOf('time') >= 0) return 'Изменен учет времени';
      if (joined.indexOf('file') >= 0 || joined.indexOf('attachment') >= 0 || joined.indexOf('upload') >= 0) return 'Изменены файлы задачи';
      if (joined.indexOf('subtask') >= 0) return 'Изменены подзадачи';
      if (joined.indexOf('checklist') >= 0) return 'Изменен чеклист';
      if (joined.indexOf('create') >= 0 && joined.indexOf('task') >= 0) return 'Создана задача';
      if (joined.indexOf('update') >= 0 || joined.indexOf('patch') >= 0 || joined.indexOf('put') >= 0) return 'Обновлены параметры задачи';
      if (joined.indexOf('delete') >= 0) return 'Выполнено удаление по задаче';
      return 'Событие по задаче';
    }

    function activityReadableDetail(item) {
      if (!item || typeof item !== 'object') return '';
      if (item.body) return String(item.body);
      if (item.note) return String(item.note);
      if (item.message) return String(item.message);
      if (item.details && typeof item.details === 'object') {
        var d = item.details;
        if (d.comment_body) return String(d.comment_body);
        if (d.diff && typeof d.diff === 'string') return String(d.diff);
        if (d.from && d.to) return 'Из "' + String(d.from) + '" в "' + String(d.to) + '"';
        if (d.status_from || d.status_to) {
          return 'Из "' + statusLabel(d.status_from || '') + '" в "' + statusLabel(d.status_to || '') + '"';
        }
      }
      return '';
    }

    list.innerHTML = items.length ? items.map(function (item) {
      var actor = activityActorLabel(item);
      var headline = activityHeadline(item);
      var detail = activityReadableDetail(item);
      return '<div class="crm-timeline-item">'
        + '<div><strong>' + escapeHtml(headline) + '</strong></div>'
        + (detail ? '<div class="small mt-1">' + escapeHtml(detail) + '</div>' : '')
        + '<div class="small text-muted mt-1">' + escapeHtml(actor) + ' · ' + escapeHtml(formatDate(item.created_at)) + '</div>'
        + '</div>';
    }).join('') : '<div class="crm-timeline-item">История изменений пока пуста.</div>';
  }

  async function loadTaskActivity(taskId) {
    try {
      var envelope = await window.CRM.api.request('api/v1/activity/feed', {
        query: {
          entity_type: 'task',
          entity_public_id: taskId,
          limit: 20
        }
      });
      renderTaskActivity(window.CRM.api.items(envelope));
    } catch (e) {
      renderTaskActivity([]);
    }
  }

  async function loadTaskHistory(taskId) {
    var list = document.getElementById('taskHistoryList');
    if (!list) return;
    try {
      var envelope = await window.CRM.api.request('api/v1/history/entity/task/' + taskId, {
        query: { limit: 50 }
      });
      var items = window.CRM.api.items(envelope);
      renderTaskHistory(items);
    } catch (e) {
      list.innerHTML = '<tr><td colspan="5" class="text-muted">Нет истории изменений</td></tr>';
    }
  }

  function renderTaskHistory(items) {
    var list = document.getElementById('taskHistoryList');
    if (!list) return;
    if (!items || items.length === 0) {
      list.innerHTML = '<tr><td colspan="5" class="text-muted">Нет истории изменений</td></tr>';
      return;
    }
    var fieldLabels = {
      title: 'Название',
      description: 'Описание',
      status_code: 'Статус',
      assignee_user_public_id: 'Исполнитель',
      priority_code: 'Приоритет',
      due_at: 'Дедлайн',
      project_public_id: 'Проект',
      estimated_hours: 'Оценка (часы)',
      actual_hours: 'Факт (часы)'
    };
    list.innerHTML = items.map(function (item) {
      var fieldName = String(item.field_name || item.field || '—');
      var label = fieldLabels[fieldName] || fieldName;
      var oldValue = item.old_value !== null && item.old_value !== undefined ? String(item.old_value) : '—';
      var newValue = item.new_value !== null && item.new_value !== undefined ? String(item.new_value) : '—';
      var changedAt = formatDate(item.created_at || item.changed_at || '');
      var changedBy = String(item.changed_by_name || item.actor_name || item.user_name || '—');
      return '<tr><td>' + escapeHtml(changedAt) + '</td><td>' + escapeHtml(label) + '</td><td class="small">' + escapeHtml(oldValue) + '</td><td class="small">' + escapeHtml(newValue) + '</td><td>' + escapeHtml(changedBy) + '</td></tr>';
    }).join('');
  }

  function setTaskTabCounter(counterId, count) {
    var el = document.getElementById(counterId);
    if (!el) return;
    var safeCount = Number(count || 0);
    if (safeCount > 0) {
      el.textContent = String(safeCount);
      el.classList.remove('d-none');
      return;
    }
    el.textContent = '0';
    el.classList.add('d-none');
  }

  function bindTaskStatusButtons(taskId) {
    function renderStatusSelect() {
      var select = document.getElementById('taskStatusSelect');
      var applyBtn = document.getElementById('taskStatusApplyBtn');
      if (!select) return;
      var options = orderedTaskStatuses(currentTask && currentTask.status_code ? currentTask.status_code : '');
      select.innerHTML = options.map(function (item) {
        var code = String(item.code || '');
        var selected = currentTask && String(currentTask.status_code || '') === code ? ' selected' : '';
        return '<option value="' + escapeHtml(code) + '"' + selected + '>' + escapeHtml(item.title || code) + '</option>';
      }).join('');
      var canChangeStatus = Boolean(currentTaskPermissions.canWorkItems);
      select.disabled = !canChangeStatus;
      if (applyBtn) {
        applyBtn.disabled = true;
      }
    }

    async function updateTaskStatus(nextStatus, reasonText) {
      if (!currentTask) return;
      if (!currentTaskPermissions.canWorkItems) {
        notify('Изменение статуса доступно автору или исполнителю задачи', 'warning');
        return;
      }
      var targetStatus = String(nextStatus || '').trim();
      if (!targetStatus) {
        notify('Выберите статус', 'warning');
        return;
      }
      var statusReason = String(reasonText || '').trim();
      if (!statusReason) {
        notify('Укажите причину смены статуса', 'warning');
        return;
      }
      if (statusReason.length < 5) {
        notify('Комментарий к смене статуса должен быть подробнее', 'warning');
        return;
      }
      var oldStatusCode = String(currentTask.status_code || '');
      var oldStatusLabel = statusLabel(oldStatusCode);
      var newStatusLabel = statusLabel(targetStatus);
      try {
        var envelope = await window.CRM.api.request('api/v1/tasks/' + taskId, {
          method: 'PATCH',
          body: {
            status: targetStatus,
            row_version: currentTask.row_version
          }
        });

        currentTask = mergeTaskState(extractTaskPayload(envelope));
        renderTaskStatus(currentTask.status_code || targetStatus);
        renderTaskMetaChips();
        renderTaskProgressByStatus(currentTask.status_code || targetStatus);
        renderTaskRiskBanner();
        renderStatusSelect();
        try {
          await window.CRM.api.request('api/v1/tasks/' + taskId + '/comments', {
            method: 'POST',
            body: {
              body: 'Изменение статуса: "' + oldStatusLabel + '" → "' + newStatusLabel + '". Причина: ' + statusReason
            }
          });
        } catch (commentError) {
          notify('Статус изменен, но не удалось сохранить комментарий причины', 'warning');
        }
        await loadTaskActivity(taskId);
        notify('Статус задачи обновлен');
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || 'Не удалось обновить статус', 'error');
      }
    }

    renderStatusSelect();
    var select = document.getElementById('taskStatusSelect');
    var applyBtn = document.getElementById('taskStatusApplyBtn');
    var reasonForm = document.getElementById('taskStatusReasonForm');
    var reasonInput = document.getElementById('taskStatusReasonInput');
    var reasonTarget = document.getElementById('taskStatusReasonTarget');
    var reasonCancelBtn = document.getElementById('taskStatusReasonCancelBtn');
    var pendingStatus = '';

    function closeReasonForm(resetSelection) {
      if (reasonForm) reasonForm.classList.add('d-none');
      if (reasonInput) reasonInput.value = '';
      pendingStatus = '';
      if (resetSelection && select && currentTask) {
        select.value = String(currentTask.status_code || '');
      }
      if (applyBtn && currentTask) {
        applyBtn.disabled = true;
      }
    }

    if (select && select.dataset.bound !== '1') {
      select.addEventListener('change', function () {
        var value = String(select.value || '').trim();
        if (!value || (currentTask && String(currentTask.status_code || '') === value)) {
          closeReasonForm(false);
          return;
        }
        if (!currentTaskPermissions.canWorkItems) {
          notify('Изменение статуса доступно автору или исполнителю задачи', 'warning');
          select.value = currentTask ? String(currentTask.status_code || '') : '';
          return;
        }
        pendingStatus = value;
        if (reasonTarget) {
          reasonTarget.textContent = '"' + statusLabel(currentTask ? currentTask.status_code : '') + '" → "' + statusLabel(value) + '"';
        }
        if (applyBtn) {
          applyBtn.disabled = false;
        }
      });
      select.dataset.bound = '1';
    }

    if (applyBtn && applyBtn.dataset.bound !== '1') {
      applyBtn.addEventListener('click', function () {
        if (!select || !currentTask) return;
        var value = String(select.value || '').trim();
        if (!value || String(currentTask.status_code || '') === value) {
          notify('Выберите новый статус', 'warning');
          return;
        }
        pendingStatus = value;
        if (reasonTarget) {
          reasonTarget.textContent = '"' + statusLabel(currentTask.status_code || '') + '" → "' + statusLabel(value) + '"';
        }
        if (reasonForm) reasonForm.classList.remove('d-none');
        if (reasonInput) reasonInput.focus();
      });
      applyBtn.dataset.bound = '1';
    }

    if (reasonForm && reasonForm.dataset.bound !== '1') {
      reasonForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!pendingStatus) {
          notify('Сначала выберите новый статус', 'warning');
          return;
        }
        var reason = reasonInput ? String(reasonInput.value || '').trim() : '';
        await updateTaskStatus(pendingStatus, reason);
        if (currentTask && String(currentTask.status_code || '') === String(pendingStatus)) {
          closeReasonForm(false);
        }
      });
      reasonForm.dataset.bound = '1';
    }

    if (reasonCancelBtn && reasonCancelBtn.dataset.bound !== '1') {
      reasonCancelBtn.addEventListener('click', function () {
        closeReasonForm(true);
      });
      reasonCancelBtn.dataset.bound = '1';
    }
  }

  function bindTaskCommentFlow(taskId) {
    var form = document.getElementById('commentForm');
    if (!form) return;

    var textArea = form.querySelector('[name="comment_text"]');
    var mentionSelect = document.getElementById('commentMentionUserSelect');
    var followBtn = document.getElementById('taskFollowBtn');
    var favoriteBtn = document.getElementById('taskFavoriteBtn');

    function renderMentionOptions() {
      if (!mentionSelect) return;
      mentionSelect.textContent = '';
      var base = document.createElement('option');
      base.value = '';
      base.textContent = 'Без упоминания';
      mentionSelect.appendChild(base);
      availableUsers.forEach(function (user) {
        var userId = String(user && user.public_id || '').trim();
        if (!userId) return;
        var option = document.createElement('option');
        option.value = userId;
        option.textContent = String(user.full_name || user.login || userId);
        mentionSelect.appendChild(option);
      });
    }
    renderMentionOptions();

    if (followBtn && followBtn.dataset.bound !== '1') {
      followBtn.dataset.bound = '1';
      followBtn.addEventListener('click', async function () {
        try {
          if (currentTaskFollowSubscription && currentTaskFollowSubscription.public_id) {
            await window.CRM.api.request('api/v1/subscriptions/' + encodeURIComponent(String(currentTaskFollowSubscription.public_id)), {
              method: 'DELETE'
            });
            notify('Отслеживание задачи отключено');
          } else {
            await window.CRM.api.request('api/v1/subscriptions', {
              method: 'POST',
              body: {
                entity_type: 'task',
                entity_public_id: taskId
              }
            });
            notify('Отслеживание задачи включено');
          }
          await loadTaskCollaborationState(taskId);
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || 'Не удалось изменить подписку', 'error');
        }
      });
    }

    if (favoriteBtn && favoriteBtn.dataset.bound !== '1') {
      favoriteBtn.dataset.bound = '1';
      favoriteBtn.addEventListener('click', async function () {
        try {
          if (currentTaskFavorite && currentTaskFavorite.public_id) {
            await window.CRM.api.request('api/v1/favorites/' + encodeURIComponent(String(currentTaskFavorite.public_id)), {
              method: 'DELETE'
            });
            notify('Убрано из избранного');
          } else {
            await window.CRM.api.request('api/v1/favorites', {
              method: 'POST',
              body: {
                entity_type: 'task',
                entity_public_id: taskId
              }
            });
            notify('Добавлено в избранное');
          }
          await loadTaskCollaborationState(taskId);
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || 'Не удалось изменить избранное', 'error');
        }
      });
    }

    if (textArea) {
      textArea.addEventListener('blur', async function () {
        var value = textArea.value.trim();
        if (!value) return;

        try {
          await window.CRM.api.request('api/v1/tasks/' + taskId + '/comment-draft', {
            method: 'POST',
            body: { body: value }
          });
        } catch (e) {
          // ignore draft save errors
        }
      });
    }

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      var text = textArea ? textArea.value.trim() : '';
      if (!text) {
        notify('Введите текст комментария', 'warning');
        return;
      }

      try {
        var createdCommentEnvelope = await window.CRM.api.request('api/v1/tasks/' + taskId + '/comments', {
          method: 'POST',
          body: { body: text }
        });
        var createdComment = createdCommentEnvelope && createdCommentEnvelope.data
          ? createdCommentEnvelope.data.comment
          : null;
        var createdCommentPublicId = String(createdComment && createdComment.public_id || '').trim();

        var mentionedUserIds = matchMentionedUsersFromText(text);
        var selectedMentionUser = mentionSelect ? String(mentionSelect.value || '').trim() : '';
        if (selectedMentionUser) {
          mentionedUserIds.push(selectedMentionUser);
        }
        if (createdCommentPublicId && mentionedUserIds.length) {
          var dedup = {};
          for (var i = 0; i < mentionedUserIds.length; i += 1) {
            var mentionedUserPublicId = String(mentionedUserIds[i] || '').trim();
            if (!mentionedUserPublicId || dedup[mentionedUserPublicId]) continue;
            dedup[mentionedUserPublicId] = true;
            try {
              await window.CRM.api.request('api/v1/mentions', {
                method: 'POST',
                body: {
                  entity_type: 'comment',
                  entity_public_id: createdCommentPublicId,
                  mentioned_user_public_id: mentionedUserPublicId
                }
              });
            } catch (mentionError) {
              void mentionError;
            }
          }
        }

        if (textArea) textArea.value = '';
        if (mentionSelect) mentionSelect.value = '';

        await window.CRM.api.request('api/v1/tasks/' + taskId + '/comment-draft', {
          method: 'DELETE'
        });

        await loadTaskCollaborationState(taskId);
        await loadTaskComments(taskId);
        notify('Комментарий сохранен');
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || 'Не удалось сохранить комментарий', 'error');
      }
    });

    var commentsList = document.getElementById('commentsList');
    if (!commentsList || commentsList.dataset.editBound === '1') return;

    commentsList.addEventListener('click', function (e) {
      var editBtn = e.target.closest('[data-comment-edit]');
      if (!editBtn) return;

      var commentPublicId = String(editBtn.dataset.commentEdit || '');
      if (!commentPublicId) return;

      var commentCard = editBtn.closest('.crm-comment');
      if (!commentCard) return;

      var bodyEl = commentCard.querySelector('[data-comment-body="' + commentPublicId + '"]');
      if (!bodyEl) return;

      var oldEditor = commentCard.querySelector('[data-comment-edit-form]');
      if (oldEditor) return;

      var currentBody = bodyEl.textContent || '';
      bodyEl.classList.add('d-none');

      var editor = document.createElement('div');
      editor.setAttribute('data-comment-edit-form', '1');
      editor.className = 'mt-2';
      editor.innerHTML = ''
        + '<textarea class="form-control mb-2" rows="3" data-comment-edit-text="' + commentPublicId + '"></textarea>'
        + '<div class="d-flex gap-2">'
        + '<button type="button" class="btn btn-sm crm-btn-primary" data-comment-save="' + commentPublicId + '">Сохранить</button>'
        + '<button type="button" class="btn btn-sm btn-light" data-comment-cancel="' + commentPublicId + '">Отмена</button>'
        + '</div>';
      commentCard.appendChild(editor);

      var textArea = editor.querySelector('[data-comment-edit-text="' + commentPublicId + '"]');
      if (textArea) textArea.value = currentBody.trim();
    });

    commentsList.addEventListener('click', async function (e) {
      var cancelBtn = e.target.closest('[data-comment-cancel]');
      if (cancelBtn) {
        var cancelId = String(cancelBtn.dataset.commentCancel || '');
        var cancelCard = cancelBtn.closest('.crm-comment');
        if (cancelCard) {
          var cancelBody = cancelCard.querySelector('[data-comment-body="' + cancelId + '"]');
          if (cancelBody) cancelBody.classList.remove('d-none');
          var cancelEditor = cancelCard.querySelector('[data-comment-edit-form]');
          if (cancelEditor) cancelEditor.remove();
        }
        return;
      }

      var saveBtn = e.target.closest('[data-comment-save]');
      var reactBtn = e.target.closest('[data-comment-react]');
      if (reactBtn) {
        var reactionCommentId = String(reactBtn.getAttribute('data-comment-react') || '').trim();
        var reactionCode = String(reactBtn.getAttribute('data-reaction') || '').trim().toLowerCase();
        if (!reactionCommentId || !reactionCode) return;
        try {
          await window.CRM.api.request('api/v1/reactions', {
            method: 'POST',
            body: {
              entity_type: 'comment',
              entity_public_id: reactionCommentId,
              reaction: reactionCode
            }
          });
          await loadTaskCollaborationState(taskId);
          await loadTaskComments(taskId);
          notify('Реакция сохранена');
        } catch (reactionError) {
          var reactionEnvelopeError = reactionError && reactionError.envelope ? reactionError.envelope : null;
          notify((reactionEnvelopeError && reactionEnvelopeError.message) || 'Не удалось сохранить реакцию', 'error');
        }
        return;
      }

      var clearReactionBtn = e.target.closest('[data-comment-reaction-clear]');
      if (clearReactionBtn) {
        var clearCommentId = String(clearReactionBtn.getAttribute('data-comment-reaction-clear') || '').trim();
        var ownReaction = currentTaskOwnReactionsByComment[clearCommentId] || null;
        if (!ownReaction || !ownReaction.public_id) {
          notify('Для комментария нет вашей реакции', 'warning');
          return;
        }
        try {
          await window.CRM.api.request('api/v1/reactions/' + encodeURIComponent(String(ownReaction.public_id)), {
            method: 'DELETE'
          });
          await loadTaskCollaborationState(taskId);
          await loadTaskComments(taskId);
          notify('Реакция удалена');
        } catch (clearError) {
          var clearEnvelopeError = clearError && clearError.envelope ? clearError.envelope : null;
          notify((clearEnvelopeError && clearEnvelopeError.message) || 'Не удалось удалить реакцию', 'error');
        }
        return;
      }

      if (!saveBtn) return;

      var commentPublicId = String(saveBtn.dataset.commentSave || '');
      if (!commentPublicId) return;

      var editorCard = saveBtn.closest('.crm-comment');
      if (!editorCard) return;

      var commentAuthorPublicId = String(editorCard.getAttribute('data-comment-author') || '');
      if (!currentUserPublicId || !commentAuthorPublicId || commentAuthorPublicId !== currentUserPublicId) {
        notify('Редактировать можно только свои комментарии', 'warning');
        return;
      }

      var editText = editorCard.querySelector('[data-comment-edit-text="' + commentPublicId + '"]');
      var body = editText ? editText.value.trim() : '';
      if (!body) {
        notify('Текст комментария не может быть пустым', 'warning');
        return;
      }

      try {
        await window.CRM.api.request('api/v1/comments/' + commentPublicId, {
          method: 'PATCH',
          body: { body: body }
        });
        await loadTaskComments(taskId);
        notify('Комментарий обновлен');
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || 'Не удалось обновить комментарий', 'error');
      }
    });

    commentsList.dataset.editBound = '1';
  }

  function bindTaskFileUpload(taskId) {
    var input = document.getElementById('taskFileInput');
    var button = document.getElementById('taskFileUploadBtn');
    var list = document.getElementById('taskFilesList');
    if (!input || !button) return;

    button.addEventListener('click', async function () {
      if (!input.files || !input.files.length) {
        notify('Выберите файл для загрузки', 'warning');
        return;
      }

      var file = input.files[0];
      var reader = new FileReader();

      reader.onload = async function () {
        try {
          var dataUrl = String(reader.result || '');
          var base64 = dataUrl.indexOf(',') >= 0 ? dataUrl.split(',')[1] : dataUrl;

          var envelope = await window.CRM.api.request('api/v1/files', {
            method: 'POST',
            body: {
              entity_type: 'task',
              entity_public_id: taskId,
              name: file.name,
              mime_type: file.type || 'application/octet-stream',
              content_base64: base64
            }
          });

          var uploaded = envelope.data && envelope.data.file
            ? envelope.data.file
            : { public_id: 'uploaded', original_name: file.name, name: file.name };
          currentTaskFiles.unshift(uploaded);
          renderTaskFiles(currentTaskFiles);

          input.value = '';
          notify('Файл загружен');
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || 'Не удалось загрузить файл', 'error');
        }
      };

      reader.readAsDataURL(file);
    });

    if (list && list.dataset.downloadBound !== '1') {
      list.addEventListener('click', async function (e) {
        var downloadBtn = e.target.closest('[data-file-download]');
        if (!downloadBtn) return;

        var filePublicId = String(downloadBtn.getAttribute('data-file-download') || '').trim();
        var fileName = String(downloadBtn.getAttribute('data-file-name') || 'file.bin').trim();
        if (!filePublicId) return;

        try {
          var headers = {};
          var locale = window.CRM.api && typeof window.CRM.api.getPreferredLocale === 'function'
            ? String(window.CRM.api.getPreferredLocale() || '')
            : '';
          if (locale) {
            headers['X-Locale'] = locale;
          }

          var response = await fetch(window.CRM.api.buildUrl('api/v1/files/' + filePublicId + '/download'), {
            method: 'GET',
            credentials: 'same-origin',
            headers: headers
          });

          if (!response.ok) {
            var errorMessage = 'Не удалось скачать файл';
            try {
              var errorEnvelope = await response.json();
              errorMessage = (errorEnvelope && errorEnvelope.message) ? String(errorEnvelope.message) : errorMessage;
            } catch (jsonErr) {
              void jsonErr;
            }
            notify(errorMessage, 'error');
            return;
          }

          var blob = await response.blob();
          var blobUrl = window.URL.createObjectURL(blob);
          var a = document.createElement('a');
          a.href = blobUrl;
          a.download = fileName || 'file.bin';
          a.style.display = 'none';
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          window.URL.revokeObjectURL(blobUrl);
        } catch (error) {
          notify('Не удалось скачать файл', 'error');
        }
      });
      list.dataset.downloadBound = '1';
    }
  }

  function bindSubtaskFlow(taskId, canWorkTask, canCreateTask) {
    var createForm = document.getElementById('subtaskCreateForm');
    var editForm = document.getElementById('subtaskEditForm');
    var list = document.getElementById('subtasksList');
    var openCreateBtn = document.getElementById('openCreateSubtaskModalBtn');
    var createModalEl = document.getElementById('createSubtaskModal');
    var editModalEl = document.getElementById('editSubtaskModal');
    if (!createForm || !editForm || !list || !openCreateBtn || !createModalEl || !editModalEl) return;

    openCreateBtn.disabled = !canCreateTask;
    createForm.querySelectorAll('input,select,button,textarea').forEach(function (el) {
      el.disabled = !canCreateTask;
    });
    editForm.querySelectorAll('input,select,button,textarea').forEach(function (el) {
      if (String(el.name || '') !== 'public_id') {
        el.disabled = !canCreateTask;
      }
    });

    var bootSubtaskDictionaries = async function () {
      await ensureCreateTaskDictionaries();
      fillSubtaskForm(createForm, {
        status_code: 'new',
        priority_code: 'normal'
      }, []);
    };

    bootSubtaskDictionaries();

    if (openCreateBtn.dataset.bound !== '1') {
      openCreateBtn.addEventListener('click', async function () {
        if (!canCreateTask) return;
        await ensureCreateTaskDictionaries();
        fillSubtaskForm(createForm, {
          status_code: 'new',
          priority_code: 'normal'
        }, []);
      });
      openCreateBtn.dataset.bound = '1';
    }

    if (createForm.dataset.bound !== '1') {
      createForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!canCreateTask) return;

        var title = String((createForm.querySelector('[name="title"]') || {}).value || '').trim();
        if (!title) {
          notify('Введите название подзадачи', 'warning');
          return;
        }

        var selectedTagIds = collectSelectedValues(createForm.querySelector('[name="tag_public_ids"]'));
        try {
          var createEnvelope = await window.CRM.api.request('api/v1/tasks/' + taskId + '/subtasks', {
            method: 'POST',
            body: {
              title: title,
              status: String((createForm.querySelector('[name="status"]') || {}).value || 'new'),
              priority: String((createForm.querySelector('[name="priority"]') || {}).value || 'normal'),
              description: String((createForm.querySelector('[name="description"]') || {}).value || '').trim(),
              assignee_user_public_id: String((createForm.querySelector('[name="assignee_user_public_id"]') || {}).value || '').trim(),
              due_at: String((createForm.querySelector('[name="due_at"]') || {}).value || '').trim(),
              start_at: String((createForm.querySelector('[name="start_at"]') || {}).value || '').trim(),
              end_at: String((createForm.querySelector('[name="end_at"]') || {}).value || '').trim()
            }
          });

          var createdSubtask = (createEnvelope && createEnvelope.data && createEnvelope.data.subtask) ? createEnvelope.data.subtask : null;
          var createdSubtaskPublicId = String((createdSubtask && createdSubtask.public_id) || '').trim();
          if (createdSubtaskPublicId && selectedTagIds.length) {
            for (var i = 0; i < selectedTagIds.length; i += 1) {
              await window.CRM.api.request('api/v1/tasks/' + createdSubtaskPublicId + '/tags/' + selectedTagIds[i], { method: 'POST' });
            }
          }

          createForm.reset();
          fillSubtaskForm(createForm, {
            status_code: 'new',
            priority_code: 'normal'
          }, []);
          await loadSubtasks(taskId, canWorkTask, canCreateTask);
          notify('Подзадача создана');
          window.bootstrap.Modal.getOrCreateInstance(createModalEl).hide();
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || 'Не удалось создать подзадачу', 'error');
        }
      });
      createForm.dataset.bound = '1';
    }

    if (editForm.dataset.bound !== '1') {
      editForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!canCreateTask) return;

        var subtaskPublicId = String((editForm.querySelector('[name="public_id"]') || {}).value || '').trim();
        if (!subtaskPublicId) return;
        var editedItem = currentTaskSubtasks.find(function (item) {
          return String(item.public_id || '') === subtaskPublicId;
        });
        if (!editedItem || !isSubtaskAuthor(editedItem)) {
          notify('Редактировать подзадачу может только ее автор', 'warning');
          return;
        }

        var selectedTagIds = collectSelectedValues(editForm.querySelector('[name="tag_public_ids"]'));
        var initialTagIds = String(editForm.dataset.initialTags || '')
          .split(',')
          .map(function (item) { return String(item || '').trim(); })
          .filter(Boolean);

        try {
          await window.CRM.api.request('api/v1/subtasks/' + subtaskPublicId, {
            method: 'PATCH',
            body: {
              title: String((editForm.querySelector('[name="title"]') || {}).value || '').trim(),
              status: String((editForm.querySelector('[name="status"]') || {}).value || 'new'),
              priority: String((editForm.querySelector('[name="priority"]') || {}).value || 'normal'),
              description: String((editForm.querySelector('[name="description"]') || {}).value || '').trim(),
              assignee_user_public_id: String((editForm.querySelector('[name="assignee_user_public_id"]') || {}).value || '').trim(),
              due_at: String((editForm.querySelector('[name="due_at"]') || {}).value || '').trim(),
              start_at: String((editForm.querySelector('[name="start_at"]') || {}).value || '').trim(),
              end_at: String((editForm.querySelector('[name="end_at"]') || {}).value || '').trim()
            }
          });

          var tagsToDelete = initialTagIds.filter(function (tagId) { return selectedTagIds.indexOf(tagId) === -1; });
          var tagsToAdd = selectedTagIds.filter(function (tagId) { return initialTagIds.indexOf(tagId) === -1; });
          for (var d = 0; d < tagsToDelete.length; d += 1) {
            await window.CRM.api.request('api/v1/tasks/' + subtaskPublicId + '/tags/' + tagsToDelete[d], { method: 'DELETE' });
          }
          for (var a = 0; a < tagsToAdd.length; a += 1) {
            await window.CRM.api.request('api/v1/tasks/' + subtaskPublicId + '/tags/' + tagsToAdd[a], { method: 'POST' });
          }

          await loadSubtasks(taskId, canWorkTask, canCreateTask);
          notify('Подзадача обновлена');
          window.bootstrap.Modal.getOrCreateInstance(editModalEl).hide();
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || 'Не удалось обновить подзадачу', 'error');
        }
      });
      editForm.dataset.bound = '1';
    }

    if (list.dataset.bound === '1') return;
    list.addEventListener('change', async function (e) {
      var statusSelect = e.target.closest('[data-subtask-status]');
      if (!statusSelect) return;

      if (!canWorkTask) {
        return;
      }

      var subtaskPublicId = String(statusSelect.getAttribute('data-subtask-status') || '').trim();
      if (!subtaskPublicId) return;
      try {
        await window.CRM.api.request('api/v1/subtasks/' + subtaskPublicId, {
          method: 'PATCH',
          body: {
            status: String(statusSelect.value || 'new')
          }
        });
        await loadSubtasks(taskId, canWorkTask, canCreateTask);
        notify('Статус подзадачи обновлен');
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || 'Не удалось изменить статус подзадачи', 'error');
      }
    });

    list.addEventListener('click', async function (e) {
      var editBtn = e.target.closest('[data-subtask-edit]');
      if (!editBtn) return;
      var subtaskPublicId = String(editBtn.getAttribute('data-subtask-edit') || '').trim();
      if (!subtaskPublicId) return;
      var subtaskItem = currentTaskSubtasks.find(function (item) {
        return String(item.public_id || '') === subtaskPublicId;
      });
      if (!subtaskItem) return;
      if (!canCreateTask || !isSubtaskAuthor(subtaskItem)) {
        notify('Редактировать подзадачу может только ее автор', 'warning');
        return;
      }

      var tagIds = await loadTaskTagIds(subtaskPublicId);
      fillSubtaskForm(editForm, subtaskItem, tagIds);
      editForm.dataset.initialTags = tagIds.join(',');
      var publicIdInput = editForm.querySelector('[name="public_id"]');
      if (publicIdInput) publicIdInput.value = subtaskPublicId;
      window.bootstrap.Modal.getOrCreateInstance(editModalEl).show();
    });

    list.dataset.bound = '1';
  }

  function bindChecklistFlow(taskId, canEditTask) {
    var createForm = document.getElementById('checklistCreateForm');
    var list = document.getElementById('checklistsList');
    if (!createForm || !list) return;

    createForm.querySelectorAll('input,button').forEach(function (el) {
      el.disabled = !canEditTask;
    });

    if (createForm.dataset.bound !== '1') {
      createForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!canEditTask) return;
        var title = String((createForm.querySelector('[name="title"]') || {}).value || '').trim();
        if (!title) {
          notify('Введите название чеклиста', 'warning');
          return;
        }
        try {
          await window.CRM.api.request('api/v1/tasks/' + taskId + '/checklists', {
            method: 'POST',
            body: { title: title }
          });
          createForm.reset();
          await loadChecklists(taskId, canEditTask);
          notify('Чеклист создан');
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || 'Не удалось создать чеклист', 'error');
        }
      });
      createForm.dataset.bound = '1';
    }

    async function saveChecklistDraft(checklistId) {
      var targetId = String(checklistId || '');
      if (!targetId) return;
      var sourceChecklist = getChecklistById(targetId);
      var draft = checklistDraftState[targetId];
      if (!sourceChecklist || !draft) return;

      var nextTitle = String(draft.title || '').trim();
      if (!nextTitle) {
        notify('Название чеклиста не может быть пустым', 'warning');
        return;
      }

      if (nextTitle !== String(sourceChecklist.title || '').trim()) {
        await window.CRM.api.request('api/v1/checklists/' + targetId, {
          method: 'PATCH',
          body: { title: nextTitle }
        });
      }

      var sourceItems = Array.isArray(sourceChecklist.items) ? sourceChecklist.items : [];
      var sourceMap = {};
      sourceItems.forEach(function (item) {
        sourceMap[String(item && item.public_id || '')] = item;
      });

      var draftItems = Array.isArray(draft.items) ? draft.items : [];
      for (var i = 0; i < draftItems.length; i += 1) {
        var draftItem = draftItems[i];
        if (!draftItem || draftItem._deleted === true) {
          if (draftItem && !draftItem._is_new && draftItem.public_id) {
            await window.CRM.api.request('api/v1/checklist-items/' + draftItem.public_id, { method: 'DELETE' });
          }
          continue;
        }

        var draftTitle = String(draftItem.title || '').trim();
        if (!draftTitle) continue;
        if (draftItem._is_new) {
          await window.CRM.api.request('api/v1/checklists/' + targetId + '/items', {
            method: 'POST',
            body: {
              title: draftTitle,
              is_done: Number(draftItem.is_done || 0) === 1 ? 1 : 0,
              sort_order: Number(draftItem.sort_order || 0)
            }
          });
          continue;
        }

        var sourceItem = sourceMap[String(draftItem.public_id || '')];
        if (!sourceItem) continue;
        var sourceTitle = String(sourceItem.title || '').trim();
        var sourceDone = Number(sourceItem.is_done || 0) === 1 ? 1 : 0;
        var sourceSort = Number(sourceItem.sort_order || 0);
        var draftDone = Number(draftItem.is_done || 0) === 1 ? 1 : 0;
        var draftSort = Number(draftItem.sort_order || 0);
        if (sourceTitle !== draftTitle || sourceDone !== draftDone || sourceSort !== draftSort) {
          await window.CRM.api.request('api/v1/checklist-items/' + draftItem.public_id, {
            method: 'PATCH',
            body: {
              title: draftTitle,
              is_done: draftDone,
              sort_order: draftSort
            }
          });
        }
      }
    }

    if (list.dataset.bound === '1') return;
    list.addEventListener('submit', async function (e) {
      var checklistSaveForm = e.target.closest('[data-checklist-save]');
      if (checklistSaveForm) {
        e.preventDefault();
        if (!canEditTask) return;
        var checklistId = String(checklistSaveForm.getAttribute('data-checklist-save') || '');
        if (!checklistId) return;
        var draft = checklistDraftState[checklistId];
        if (draft) {
          draft.title = String((checklistSaveForm.querySelector('[name="title"]') || {}).value || '').trim();
          var draftItems = Array.isArray(draft.items) ? draft.items : [];
          draftItems.forEach(function (item) {
            if (!item || item._deleted === true) return;
            var itemId = String(item.public_id || '');
            var titleInput = checklistSaveForm.querySelector('[data-checklist-draft-title="' + itemId + '"]');
            var doneInput = checklistSaveForm.querySelector('[data-checklist-draft-done="' + itemId + '"]');
            if (titleInput) item.title = String(titleInput.value || '').trim();
            if (doneInput) item.is_done = doneInput.checked ? 1 : 0;
          });
          draftItems.forEach(function (item, index) {
            if (!item || item._deleted === true) return;
            item.sort_order = index;
          });
        }
        try {
          await saveChecklistDraft(checklistId);
          checklistActiveEditId = '';
          delete checklistDraftState[checklistId];
          await loadChecklists(taskId, canEditTask);
          notify('Чеклист сохранен');
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || 'Не удалось сохранить чеклист', 'error');
        }
        return;
      }

      var createItemForm = e.target.closest('[data-checklist-item-create-view]');
      if (createItemForm) {
        e.preventDefault();
        if (!canEditTask) return;
        var checklistPublicId = String(createItemForm.getAttribute('data-checklist-item-create-view') || '');
        var itemTitle = String((createItemForm.querySelector('[name="title"]') || {}).value || '').trim();
        if (!checklistPublicId || !itemTitle) {
          notify('Введите название пункта', 'warning');
          return;
        }
        try {
          await window.CRM.api.request('api/v1/checklists/' + checklistPublicId + '/items', {
            method: 'POST',
            body: { title: itemTitle }
          });
          createItemForm.reset();
          checklistViewAddItemState[checklistPublicId] = false;
          await loadChecklists(taskId, canEditTask);
          notify('Пункт чеклиста добавлен');
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || 'Не удалось добавить пункт', 'error');
        }
        return;
      }
    });

    list.addEventListener('change', async function (e) {
      var checkbox = e.target.closest('[data-checklist-item-toggle]');
      if (!checkbox || !canEditTask) return;
      var itemPublicId = String(checkbox.getAttribute('data-checklist-item-toggle') || '');
      if (!itemPublicId) return;
      var targetItem = null;
      for (var i = 0; i < currentTaskChecklists.length && !targetItem; i += 1) {
        var checklist = currentTaskChecklists[i];
        var checklistItems = Array.isArray(checklist && checklist.items) ? checklist.items : [];
        for (var j = 0; j < checklistItems.length; j += 1) {
          if (String(checklistItems[j] && checklistItems[j].public_id || '') === itemPublicId) {
            targetItem = checklistItems[j];
            break;
          }
        }
      }
      if (!targetItem) return;
      try {
        await window.CRM.api.request('api/v1/checklist-items/' + itemPublicId, {
          method: 'PATCH',
          body: {
            title: String(targetItem.title || ''),
            is_done: checkbox.checked ? 1 : 0,
            sort_order: Number(targetItem.sort_order || 0)
          }
        });
        await loadChecklists(taskId, canEditTask);
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || 'Не удалось изменить статус пункта', 'error');
      }
    });

    list.addEventListener('click', async function (e) {
      var checklistEditBtn = e.target.closest('[data-checklist-edit]');
      if (checklistEditBtn && canEditTask) {
        var checklistEditId = String(checklistEditBtn.getAttribute('data-checklist-edit') || '');
        if (!checklistEditId) return;
        checklistActiveEditId = checklistEditId;
        var sourceChecklist = getChecklistById(checklistEditId);
        checklistDraftState[checklistEditId] = buildChecklistDraft(sourceChecklist || {});
        renderChecklists(currentTaskChecklists, canEditTask);
        return;
      }

      var checklistEditCancelBtn = e.target.closest('[data-checklist-edit-cancel]');
      if (checklistEditCancelBtn && canEditTask) {
        var checklistCancelId = String(checklistEditCancelBtn.getAttribute('data-checklist-edit-cancel') || '');
        if (!checklistCancelId) return;
        checklistActiveEditId = '';
        delete checklistDraftState[checklistCancelId];
        renderChecklists(currentTaskChecklists, canEditTask);
        return;
      }

      var addItemToggleBtn = e.target.closest('[data-checklist-item-create-toggle]');
      if (addItemToggleBtn && canEditTask) {
        var checklistAddId = String(addItemToggleBtn.getAttribute('data-checklist-item-create-toggle') || '');
        if (!checklistAddId) return;
        checklistViewAddItemState[checklistAddId] = true;
        renderChecklists(currentTaskChecklists, canEditTask);
        return;
      }

      var addItemCancelBtn = e.target.closest('[data-checklist-item-create-cancel]');
      if (addItemCancelBtn && canEditTask) {
        var checklistAddCancelId = String(addItemCancelBtn.getAttribute('data-checklist-item-create-cancel') || '');
        if (!checklistAddCancelId) return;
        checklistViewAddItemState[checklistAddCancelId] = false;
        renderChecklists(currentTaskChecklists, canEditTask);
        return;
      }

      var addDraftItemBtn = e.target.closest('[data-checklist-draft-add-item]');
      if (addDraftItemBtn && canEditTask) {
        var checklistDraftId = String(addDraftItemBtn.getAttribute('data-checklist-draft-add-item') || '');
        if (!checklistDraftId) return;
        var draft = checklistDraftState[checklistDraftId];
        if (!draft) return;
        var nextIndex = Array.isArray(draft.items) ? draft.items.length : 0;
        draft.items = Array.isArray(draft.items) ? draft.items : [];
        draft.items.push({
          public_id: 'draft_' + String(Date.now()) + '_' + String(Math.floor(Math.random() * 100000)),
          title: '',
          is_done: 0,
          sort_order: nextIndex,
          _is_new: true,
          _deleted: false
        });
        renderChecklists(currentTaskChecklists, canEditTask);
        return;
      }

      var draftDeleteBtn = e.target.closest('[data-checklist-draft-delete]');
      if (draftDeleteBtn && canEditTask) {
        var itemDraftId = String(draftDeleteBtn.getAttribute('data-checklist-draft-delete') || '');
        if (!itemDraftId || !checklistActiveEditId) return;
        var activeDraft = checklistDraftState[checklistActiveEditId];
        if (!activeDraft || !Array.isArray(activeDraft.items)) return;
        for (var d = 0; d < activeDraft.items.length; d += 1) {
          var activeItem = activeDraft.items[d];
          if (String(activeItem && activeItem.public_id || '') !== itemDraftId) continue;
          if (activeItem._is_new) {
            activeDraft.items.splice(d, 1);
          } else {
            activeItem._deleted = true;
          }
          break;
        }
        renderChecklists(currentTaskChecklists, canEditTask);
        return;
      }

      var checklistDeleteBtn = e.target.closest('[data-checklist-delete]');
      if (checklistDeleteBtn && canEditTask) {
        var checklistPublicId = String(checklistDeleteBtn.getAttribute('data-checklist-delete') || '');
        if (!checklistPublicId) return;
        try {
          await window.CRM.api.request('api/v1/checklists/' + checklistPublicId, { method: 'DELETE' });
          if (checklistActiveEditId === checklistPublicId) checklistActiveEditId = '';
          delete checklistDraftState[checklistPublicId];
          delete checklistViewAddItemState[checklistPublicId];
          await loadChecklists(taskId, canEditTask);
          notify('Чеклист удален');
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || 'Не удалось удалить чеклист', 'error');
        }
        return;
      }

    });

    list.dataset.bound = '1';
  }

  function formatMinutes(minutes) {
    var total = Number(minutes || 0);
    var hours = Math.floor(total / 60);
    var mins = total % 60;
    if (hours <= 0) return String(mins) + ' мин';
    return String(hours) + ' ч ' + String(mins) + ' мин';
  }

  function formatWorklogEntriesLabel(count) {
    var normalized = Number(count || 0);
    var mod10 = normalized % 10;
    var mod100 = normalized % 100;
    if (mod10 === 1 && mod100 !== 11) return String(normalized) + ' запись';
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return String(normalized) + ' записи';
    return String(normalized) + ' записей';
  }

  function toApiDatetimeFromLocal(value) {
    var raw = String(value || '').trim();
    if (!raw) return '';
    if (raw.indexOf('T') > -1 && raw.length === 16) return raw.replace('T', ' ') + ':00';
    if (raw.indexOf('T') > -1) return raw.replace('T', ' ');
    return raw;
  }

  function toLocalDatetimeValue(value) {
    var raw = String(value || '').trim();
    if (!raw) return '';
    var normalized = raw.replace(' ', 'T');
    return normalized.length >= 16 ? normalized.slice(0, 16) : normalized;
  }

  function setCookie(name, value, days) {
    var expires = '';
    if (days && Number(days) > 0) {
      var date = new Date();
      date.setTime(date.getTime() + Number(days) * 24 * 60 * 60 * 1000);
      expires = '; expires=' + date.toUTCString();
    }
    document.cookie = name + '=' + encodeURIComponent(String(value || '')) + expires + '; path=/; SameSite=Lax';
  }

  function getCookie(name) {
    var escaped = String(name || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    var match = document.cookie.match(new RegExp('(?:^|; )' + escaped + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
  }

  function removeCookie(name) {
    document.cookie = String(name || '') + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax';
  }

  function readTaskTimerState() {
    var raw = getCookie(TASK_TIMER_COOKIE_NAME);
    if (!raw) return null;
    try {
      var parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object') return null;
      if (!parsed.task_public_id || !parsed.user_public_id || !parsed.started_at) return null;
      return parsed;
    } catch (e) {
      return null;
    }
  }

  function writeTaskTimerState(state) {
    if (!state) return;
    setCookie(TASK_TIMER_COOKIE_NAME, JSON.stringify(state), 7);
  }

  function clearTaskTimerState() {
    removeCookie(TASK_TIMER_COOKIE_NAME);
  }

  function formatElapsedSeconds(totalSeconds) {
    var sec = Math.max(0, Number(totalSeconds || 0));
    var hours = Math.floor(sec / 3600);
    var minutes = Math.floor((sec % 3600) / 60);
    var seconds = sec % 60;
    return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
  }

  function renderTaskTimerState(taskId, state) {
    var elapsedEl = document.getElementById('taskTimerElapsed');
    var startedAtEl = document.getElementById('taskTimerStartedAt');
    var startBtn = document.getElementById('taskTimerStartBtn');
    var stopBtn = document.getElementById('taskTimerStopBtn');
    var timerForm = document.getElementById('taskTimerLogForm');

    if (!elapsedEl || !startedAtEl || !startBtn || !stopBtn) return;

    var canUseTimer = Boolean(currentTaskPermissions.canWorkItems);
    var hasState = Boolean(state);
    var currentUserId = getCurrentUserPublicId();
    var isCurrentTask = hasState && String(state.task_public_id || '') === String(taskId || '');
    var isCurrentUser = hasState && String(state.user_public_id || '') === String(currentUserId || '');
    var isRunningHere = hasState && isCurrentTask && isCurrentUser;

    if (taskTimerTickIntervalId) {
      window.clearInterval(taskTimerTickIntervalId);
      taskTimerTickIntervalId = null;
    }

    if (!canUseTimer) {
      elapsedEl.textContent = '00:00:00';
      startedAtEl.textContent = 'Таймер недоступен: недостаточно прав';
      startBtn.disabled = true;
      stopBtn.disabled = true;
      if (timerForm) timerForm.classList.add('d-none');
      return;
    }

    if (!hasState) {
      elapsedEl.textContent = '00:00:00';
      startedAtEl.textContent = 'Таймер не запущен';
      startBtn.disabled = false;
      stopBtn.disabled = true;
      return;
    }

    if (isRunningHere) {
      startBtn.disabled = true;
      stopBtn.disabled = false;

      var updateElapsed = function () {
        var start = new Date(state.started_at);
        var startMs = start.getTime();
        if (Number.isNaN(startMs)) {
          elapsedEl.textContent = '00:00:00';
          return;
        }
        var seconds = Math.floor((Date.now() - startMs) / 1000);
        elapsedEl.textContent = formatElapsedSeconds(seconds);
      };

      startedAtEl.textContent = 'Старт: ' + formatDate(state.started_at);
      updateElapsed();
      taskTimerTickIntervalId = window.setInterval(updateElapsed, 1000);
      return;
    }

    elapsedEl.textContent = '00:00:00';
    if (isCurrentUser) {
      startedAtEl.textContent = 'У вас уже запущен таймер в другой задаче';
    } else {
      startedAtEl.textContent = 'Таймер занят другим пользователем';
    }
    startBtn.disabled = true;
    stopBtn.disabled = true;
    if (timerForm) timerForm.classList.add('d-none');
  }

  function bindTaskTimerFlow(taskId) {
    var startBtn = document.getElementById('taskTimerStartBtn');
    var stopBtn = document.getElementById('taskTimerStopBtn');
    var timerForm = document.getElementById('taskTimerLogForm');
    var timerCancelBtn = document.getElementById('taskTimerLogCancelBtn');
    if (!startBtn || !stopBtn || !timerForm) return;

    var minutesInput = timerForm.querySelector('[name="minutes_spent"]');
    var noteInput = timerForm.querySelector('[name="note"]');
    var pendingLogPayload = null;

    if (startBtn.dataset.bound === '1') {
      renderTaskTimerState(taskId, readTaskTimerState());
      return;
    }

    startBtn.addEventListener('click', function () {
      if (!currentTaskPermissions.canWorkItems) {
        return;
      }

      var activeState = readTaskTimerState();
      var currentUserId = getCurrentUserPublicId();
      if (activeState && String(activeState.user_public_id || '') === String(currentUserId || '')) {
        if (String(activeState.task_public_id || '') === String(taskId || '')) {
          notify('Таймер уже запущен для этой задачи', 'warning');
        } else {
          notify('Сначала остановите таймер в другой задаче', 'warning');
        }
        return;
      }

      var startedAt = new Date().toISOString();
      writeTaskTimerState({
        task_public_id: String(taskId || ''),
        user_public_id: String(currentUserId || ''),
        started_at: startedAt
      });
      pendingLogPayload = null;
      if (minutesInput) minutesInput.value = '';
      if (noteInput) noteInput.value = '';
      timerForm.classList.add('d-none');
      renderTaskTimerState(taskId, readTaskTimerState());
      notify('Таймер запущен');
    });

    stopBtn.addEventListener('click', function () {
      var state = readTaskTimerState();
      var currentUserId = getCurrentUserPublicId();
      if (!state || String(state.user_public_id || '') !== String(currentUserId || '') || String(state.task_public_id || '') !== String(taskId || '')) {
        notify('Активный таймер для этой задачи не найден', 'warning');
        renderTaskTimerState(taskId, state);
        return;
      }

      var startedAt = new Date(state.started_at);
      var finishedAt = new Date();
      var startMs = startedAt.getTime();
      if (Number.isNaN(startMs)) {
        clearTaskTimerState();
        renderTaskTimerState(taskId, null);
        notify('Таймер был повреждён и сброшен', 'warning');
        return;
      }

      var seconds = Math.max(1, Math.floor((finishedAt.getTime() - startMs) / 1000));
      var roundedMinutes = Math.max(1, Math.ceil(seconds / 60));
      pendingLogPayload = {
        seconds: seconds,
        started_at: startedAt.toISOString(),
        finished_at: finishedAt.toISOString()
      };
      clearTaskTimerState();
      renderTaskTimerState(taskId, null);

      if (minutesInput) minutesInput.value = String(roundedMinutes);
      if (noteInput) noteInput.value = '';
      timerForm.classList.remove('d-none');
      if (noteInput) noteInput.focus();
    });

    if (timerCancelBtn) {
      timerCancelBtn.addEventListener('click', function () {
        pendingLogPayload = null;
        timerForm.classList.add('d-none');
      });
    }

    timerForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      if (!pendingLogPayload) {
        notify('Сначала остановите таймер', 'warning');
        return;
      }

      var minutes = Number(minutesInput ? minutesInput.value : 0);
      var note = String(noteInput ? noteInput.value : '').trim();
      if (minutes <= 0) {
        notify('Укажите количество минут больше нуля', 'warning');
        return;
      }
      if (!note) {
        notify('Опишите, что было сделано', 'warning');
        return;
      }

      var timerNote = '[' + formatElapsedSeconds(pendingLogPayload.seconds) + '] ' + note;

      try {
        await window.CRM.api.request('api/v1/worklogs', {
          method: 'POST',
          body: {
            task_public_id: taskId,
            minutes_spent: minutes,
            note: timerNote
          }
        });

        pendingLogPayload = null;
        timerForm.classList.add('d-none');
        if (minutesInput) minutesInput.value = '';
        if (noteInput) noteInput.value = '';
        await loadTaskWorklogs(taskId);
        await loadTaskActivity(taskId);
        renderTaskTimerState(taskId, null);
        notify('Время по таймеру добавлено в учёт');
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || 'Не удалось сохранить время по таймеру', 'error');
      }
    });

    startBtn.dataset.bound = '1';
    renderTaskTimerState(taskId, readTaskTimerState());
  }

  function renderTaskAiSuggestionCard(suggestion, preview) {
    var card = document.getElementById('taskAiSummaryCard');
    var stateNode = document.getElementById('taskAiSummaryState');
    var resultNode = document.getElementById('taskAiSummaryResult');
    var summaryNode = document.getElementById('taskAiSummaryText');
    var metaNode = document.getElementById('taskAiSummaryMeta');
    var previewNode = document.getElementById('taskAiSummaryPreview');
    var previewWrap = document.getElementById('taskAiSummaryPreviewWrap');
    var dismissBtn = document.getElementById('taskAiDismissBtn');
    var previewBtn = document.getElementById('taskAiPreviewBtn');
    var applyBtn = document.getElementById('taskAiApplyBtn');

    function setTaskCardState(stateCode, message) {
      if (card && typeof card.setAttribute === 'function') {
        card.setAttribute('data-ai-state', String(stateCode || 'idle'));
      }
      if (stateNode) {
        stateNode.textContent = String(message || ('Состояние: ' + String(stateCode || 'idle')));
      }
    }

    if (!stateNode || !resultNode || !summaryNode || !metaNode || !previewNode || !previewWrap) return;
    if (!suggestion) {
      setTaskCardState('empty', 'AI-сводка не сформирована.');
      resultNode.classList.add('d-none');
      previewWrap.classList.add('d-none');
      if (dismissBtn) dismissBtn.disabled = true;
      if (previewBtn) previewBtn.disabled = true;
      if (applyBtn) applyBtn.disabled = true;
      return;
    }

    setTaskCardState('ready', 'Сформировано: ' + formatDate(suggestion.created_at || ''));
    summaryNode.textContent = String(suggestion.summary || '—');
    metaNode.textContent = 'Статус: ' + String(suggestion.status || 'draft');
    resultNode.classList.remove('d-none');
    var isFinal = String(suggestion.status || '') === 'applied' || String(suggestion.status || '') === 'dismissed';
    if (dismissBtn) dismissBtn.disabled = isFinal;
    if (previewBtn) previewBtn.disabled = isFinal;
    if (applyBtn) applyBtn.disabled = isFinal;

    if (preview && Array.isArray(preview.changes) && preview.changes.length) {
      previewNode.innerHTML = '<ul class="mb-0 ps-3">'
        + preview.changes.map(function (change) {
          var label = String(change.field || change.type || 'change');
          var value = String(change.value || '');
          return '<li><strong>' + escapeHtml(label) + '</strong>: ' + escapeHtml(value) + '</li>';
        }).join('')
        + '</ul>';
      previewWrap.classList.remove('d-none');
    } else {
      previewNode.innerHTML = '';
      previewWrap.classList.add('d-none');
    }
  }

  function bindTaskAiSummaryFlow(taskId) {
    var card = document.getElementById('taskAiSummaryCard');
    var generateBtn = document.getElementById('taskAiGenerateBtn');
    var nextActionBtn = document.getElementById('taskAiNextActionBtn');
    var decomposeBtn = document.getElementById('taskAiDecomposeBtn');
    var checklistBtn = document.getElementById('taskAiChecklistBtn');
    var improveDescriptionBtn = document.getElementById('taskAiImproveDescBtn');
    var commentDraftBtn = document.getElementById('taskAiCommentDraftBtn');
    var qualityBtn = document.getElementById('taskAiQualityBtn');
    var createMeetingBtn = document.getElementById('taskAiCreateMeetingBtn');
    var previewBtn = document.getElementById('taskAiPreviewBtn');
    var applyBtn = document.getElementById('taskAiApplyBtn');
    var dismissBtn = document.getElementById('taskAiDismissBtn');
    var stateNode = document.getElementById('taskAiSummaryState');
    var descriptionDiffModal = document.getElementById('taskAiDescriptionDiffModal');
    var descriptionDiffOldNode = document.getElementById('taskAiDescriptionDiffOld');
    var descriptionDiffNewNode = document.getElementById('taskAiDescriptionDiffNew');
    var descriptionDiffApplyBtn = document.getElementById('taskAiDescriptionDiffApplyBtn');
    var regenerateModal = document.getElementById('taskAiRegenerateModal');
    var regenerateModalTitle = document.getElementById('taskAiRegenerateModalTitle');
    var regenerateInfo = document.getElementById('taskAiRegenerateInfo');
    var regenerateLoading = document.getElementById('taskAiRegenerateLoading');
    var regenerateLoadingText = document.getElementById('taskAiRegenerateLoadingText');
    var regenerateFooter = document.getElementById('taskAiRegenerateFooter');
    var regenerateSummaryNode = document.getElementById('taskAiRegenerateSummary');
    var regenerateStatusNode = document.getElementById('taskAiRegenerateStatus');
    var regenerateUpdatedNode = document.getElementById('taskAiRegenerateUpdated');
    var regenerateBtn = document.getElementById('taskAiRegenerateBtn');
    var highRiskModal = document.getElementById('taskAiHighRiskModal');
    var highRiskActionsNode = document.getElementById('taskAiHighRiskActions');
    var highRiskConfirmBtn = document.getElementById('taskAiHighRiskConfirmBtn');
    var pendingSelectedActions = [];
    var latestPreview = null;
    var pendingRegenerateIntent = null;
    if (!card || !generateBtn) return;
    var aiClient = window.CRM.ai || null;
    var primaryButtons = [
      generateBtn,
      nextActionBtn,
      decomposeBtn,
      checklistBtn,
      improveDescriptionBtn,
      commentDraftBtn,
      qualityBtn,
      createMeetingBtn
    ];

    function openTaskLinkedCalendarModal() {
      var trigger = document.querySelector('.crm-task-calendar-action[data-open-modal="calendarEventModal"]')
        || document.querySelector('[data-open-modal="calendarEventModal"]');
      if (trigger && typeof trigger.click === 'function') {
        trigger.click();
        return true;
      }

      var modal = document.getElementById('calendarEventModal');
      if (modal && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
        return true;
      }

      return false;
    }

    function toAiUiError(error, fallbackMessage) {
      if (aiClient && typeof aiClient.toUiError === 'function') {
        return aiClient.toUiError(error, fallbackMessage);
      }
      var envelope = error && error.envelope ? error.envelope : null;
      return {
        code: String((envelope && envelope.code) || 'AI_REQUEST_FAILED'),
        message: String((envelope && envelope.message) || fallbackMessage || 'Не удалось выполнить AI-запрос')
      };
    }

    function toAiUiState(value, fallbackMessage) {
      if (aiClient && typeof aiClient.toUiState === 'function') {
        return aiClient.toUiState(value, fallbackMessage);
      }
      var code = '';
      if (typeof value === 'string') {
        code = String(value || '');
      } else if (value && typeof value === 'object' && typeof value.code === 'string') {
        code = String(value.code || '');
      } else if (value && value.envelope) {
        code = String(value.envelope.code || '');
      }
      if (code === 'AI_PROVIDER_NOT_CONFIGURED') return 'provider_missing';
      if (code === 'AI_RATE_LIMITED') return 'rate_limited';
      if (code === 'AI_DISABLED' || code === 'AI_INTENT_DISABLED' || code === 'AI_FEATURE_DISABLED') return 'disabled';
      if (code === 'AI_ROW_VERSION_CONFLICT') return 'conflict';
      return 'error';
    }

    function setTaskAiState(stateCode, message) {
      if (card && typeof card.setAttribute === 'function') {
        card.setAttribute('data-ai-state', String(stateCode || 'idle'));
      }
      if (stateNode) {
        stateNode.textContent = String(message || ('Состояние: ' + String(stateCode || 'idle')));
      }
    }

    function setTaskAiHardDisabled(isDisabled) {
      primaryButtons.forEach(function (btn) {
        if (!btn) return;
        btn.dataset.hardDisabled = isDisabled ? '1' : '0';
        btn.disabled = Boolean(isDisabled);
      });
    }

    function applyTaskAiSoftState(aiError) {
      if (aiClient && typeof aiClient.applyAiSoftState === 'function') {
        aiClient.applyAiSoftState({
          aiError: aiError,
          controls: primaryButtons.concat([previewBtn, applyBtn, dismissBtn]),
          setState: setTaskAiState,
          fallbackMessage: 'AI-действие временно недоступно.'
        });
        return;
      }
      setTaskAiHardDisabled(true);
      setTaskAiState(toAiUiState(aiError), String((aiError && aiError.message) || 'AI-действие временно недоступно.'));
    }

    if (generateBtn.dataset.bound === '1') {
      renderTaskAiSuggestionCard(currentTaskAiSuggestion, null);
      return;
    }

    renderTaskAiSuggestionCard(currentTaskAiSuggestion, null);

    function ensureDrawerHandlers() {
      return {
        onApply: function (selectedActions) {
          var selected = Array.isArray(selectedActions) ? selectedActions : [];
          if (selected.length === 0) {
            notify('Выберите хотя бы одно действие для применения', 'warning');
            return;
          }
          pendingSelectedActions = selected.slice();
          if (applyBtn) applyBtn.click();
        },
        onDismiss: function () {
          if (dismissBtn) dismissBtn.click();
        },
        onRefresh: function () {
          if (previewBtn) previewBtn.click();
        }
      };
    }

    function showHighRiskModal(actions) {
      if (!highRiskModal || !highRiskConfirmBtn || !window.bootstrap || typeof window.bootstrap.Modal !== 'function') {
        return Promise.resolve(window.confirm('Вы выбрали действие с повышенным риском. Продолжить применение?'));
      }
      var highRiskActions = actions.filter(function (a) { return a && a.high_risk; });
      if (highRiskActionsNode) {
        highRiskActionsNode.innerHTML = '<ul class="mb-0 ps-3">' + highRiskActions.map(function (a) {
          var label = a.label || a.field || a.type || 'действие';
          return '<li>' + escapeHtml(label) + '</li>';
        }).join('') + '</ul>';
      }
      var modalInstance = window.bootstrap.Modal.getOrCreateInstance(highRiskModal);
      return new Promise(function (resolve) {
        var settled = false;
        function done(value) {
          if (settled) return;
          settled = true;
          highRiskConfirmBtn.removeEventListener('click', onConfirm);
          highRiskModal.removeEventListener('hidden.bs.modal', onHidden);
          resolve(Boolean(value));
        }
        function onConfirm() { done(true); modalInstance.hide(); }
        function onHidden() { done(false); }
        highRiskModal.addEventListener('hidden.bs.modal', onHidden, { once: true });
        highRiskConfirmBtn.addEventListener('click', onConfirm);
        modalInstance.show();
      });
    }

    async function loadSuggestionPreview() {
      if (!currentTaskAiSuggestion || !currentTaskAiSuggestion.public_id) return null;
      if (aiClient && typeof aiClient.canPreviewSuggestion === 'function' && !aiClient.canPreviewSuggestion(currentTaskAiSuggestion)) {
        var blockedMessage = typeof aiClient.suggestionPreviewPolicyMessage === 'function'
          ? aiClient.suggestionPreviewPolicyMessage(currentTaskAiSuggestion)
          : 'Предпросмотр временно недоступен. Обновите AI-результат.';
        setTaskAiState('ready', blockedMessage);
        return null;
      }
      setTaskAiState('loading', 'Подготавливаем preview AI-предложения...');
      var envelope = aiClient && typeof aiClient.previewSuggestion === 'function'
        ? await aiClient.previewSuggestion(currentTaskAiSuggestion.public_id)
        : await window.CRM.api.request('api/v1/ai/suggestions/' + encodeURIComponent(currentTaskAiSuggestion.public_id) + '/preview-apply', {
          method: 'POST',
          headers: { 'X-Idempotency-Key': window.CRM.api.createIdempotencyKey('ai-suggestion-preview') }
        });
      latestPreview = envelope && envelope.data ? envelope.data.preview : null;
      renderTaskAiSuggestionCard(currentTaskAiSuggestion, latestPreview);
      return latestPreview;
    }

    async function requestTaskSuggestion(intentPath, payload, successMessage, options) {
      var opts = options && typeof options === 'object' ? options : {};
      if (!hasPermission('ai.use')) {
        return null;
      }
      var hasExisting = currentTaskAiSuggestion && ['applied', 'ready', 'draft'].indexOf(String(currentTaskAiSuggestion.status || '')) !== -1;
      if (hasExisting && regenerateModal && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
        pendingRegenerateIntent = { intentPath: intentPath, payload: payload || {}, successMessage: successMessage, options: opts };
        if (regenerateSummaryNode) regenerateSummaryNode.textContent = String(currentTaskAiSuggestion.summary || '—');
        if (regenerateStatusNode) {
          var statusLabels = { draft: 'Черновик', ready: 'Готово', applied: 'Применено', dismissed: 'Отклонено', error: 'Ошибка' };
          regenerateStatusNode.textContent = statusLabels[String(currentTaskAiSuggestion.status || '')] || String(currentTaskAiSuggestion.status || '—');
        }
        if (regenerateUpdatedNode) regenerateUpdatedNode.textContent = String(currentTaskAiSuggestion.updated_at || '—');
        window.bootstrap.Modal.getOrCreateInstance(regenerateModal).show();
        return currentTaskAiSuggestion;
      }
      if (hasExisting) {
        return doRegenerateTaskSuggestion(intentPath, payload, successMessage, opts);
      }
      return doRequestTaskSuggestion(intentPath, payload, successMessage, opts);
    }

    async function doRequestTaskSuggestion(intentPath, payload, successMessage, opts) {
      setTaskAiState('loading', 'Формируем AI-предложение по задаче...');

      var envelope = aiClient && typeof aiClient.requestAi === 'function'
        ? await aiClient.requestAi('api/v1/ai/tasks/' + encodeURIComponent(taskId) + '/' + intentPath, payload || {})
        : await window.CRM.api.request('api/v1/ai/tasks/' + encodeURIComponent(taskId) + '/' + intentPath, {
          method: 'POST',
          headers: { 'X-Idempotency-Key': window.CRM.api.createIdempotencyKey('ai-task-' + intentPath) },
          body: payload || {}
        });

      var suggestion = envelope && envelope.data ? envelope.data.suggestion : null;
      currentTaskAiSuggestion = suggestion || null;
      latestPreview = null;
      pendingSelectedActions = [];
      renderTaskAiSuggestionCard(currentTaskAiSuggestion, null);

      if (currentTaskAiSuggestion && ['applied', 'ready', 'draft'].indexOf(String(currentTaskAiSuggestion.status || '')) !== -1) {
        if (regenerateModal && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
          pendingRegenerateIntent = { intentPath: intentPath, payload: payload || {}, successMessage: successMessage, options: opts };
          if (regenerateSummaryNode) regenerateSummaryNode.textContent = String(currentTaskAiSuggestion.summary || '—');
          if (regenerateStatusNode) {
            var statusLabels = { draft: 'Черновик', ready: 'Готово', applied: 'Применено', dismissed: 'Отклонено', error: 'Ошибка' };
            regenerateStatusNode.textContent = statusLabels[String(currentTaskAiSuggestion.status || '')] || String(currentTaskAiSuggestion.status || '—');
          }
          if (regenerateUpdatedNode) regenerateUpdatedNode.textContent = String(currentTaskAiSuggestion.updated_at || '—');
          window.bootstrap.Modal.getOrCreateInstance(regenerateModal).show();
        } else {
          return doRegenerateTaskSuggestion(intentPath, payload, successMessage, opts);
        }
        return currentTaskAiSuggestion;
      }

      if (currentTaskAiSuggestion && opts.autoApply) {
        try {
          await autoApplyDescriptionSuggestion();
        } catch (_) {
          // already shows its own notifications
        }
      } else if (currentTaskAiSuggestion && opts.autoPreview) {
        try {
          await autoApplyAiSuggestion();
        } catch (_) {
          // autoApplyAiSuggestion already shows its own notifications
        }
      } else if (currentTaskAiSuggestion && opts.showDrawer && aiClient && typeof aiClient.openSuggestionDrawer === 'function') {
        var preview = await loadSuggestionPreview();
        aiClient.openSuggestionDrawer(currentTaskAiSuggestion, preview, ensureDrawerHandlers());
      }
      return currentTaskAiSuggestion;
    }

    async function doRegenerateTaskSuggestion(intentPath, payload, successMessage, opts) {
      setTaskAiState('loading', 'Перегенерируем AI-предложение...');

      var requestPayload = Object.assign({}, payload || {}, { force_refresh: true });
      var envelope = aiClient && typeof aiClient.requestAi === 'function'
        ? await aiClient.requestAi('api/v1/ai/tasks/' + encodeURIComponent(taskId) + '/' + intentPath, requestPayload)
        : await window.CRM.api.request('api/v1/ai/tasks/' + encodeURIComponent(taskId) + '/' + intentPath, {
          method: 'POST',
          headers: { 'X-Idempotency-Key': window.CRM.api.createIdempotencyKey('ai-task-' + intentPath) },
          body: requestPayload
        });

      var suggestion = envelope && envelope.data ? envelope.data.suggestion : null;
      currentTaskAiSuggestion = suggestion || null;
      latestPreview = null;
      pendingSelectedActions = [];
      renderTaskAiSuggestionCard(currentTaskAiSuggestion, null);

      if (regenerateModalInstance && regenerateModal) {
        regenerateModalInstance.hide();
        regenerateModalInstance = null;
      }

      if (currentTaskAiSuggestion && opts.autoApply) {
        try {
          await autoApplyDescriptionSuggestion();
        } catch (_) {
          // already shows its own notifications
        }
      } else if (currentTaskAiSuggestion && opts.autoPreview) {
        try {
          await autoApplyAiSuggestion();
        } catch (_) {
          // autoApplyAiSuggestion already shows its own notifications
        }
      } else if (currentTaskAiSuggestion && opts.showDrawer && aiClient && typeof aiClient.openSuggestionDrawer === 'function') {
        var preview = await loadSuggestionPreview();
        aiClient.openSuggestionDrawer(currentTaskAiSuggestion, preview, ensureDrawerHandlers());
      }
      return currentTaskAiSuggestion;
    }

    async function autoApplyDescriptionSuggestion() {
      if (!currentTaskAiSuggestion || !currentTaskAiSuggestion.public_id) return;
      try {
        var previewEnvelope = aiClient && typeof aiClient.previewSuggestion === 'function'
          ? await aiClient.previewSuggestion(currentTaskAiSuggestion.public_id)
          : await window.CRM.api.request('api/v1/ai/suggestions/' + encodeURIComponent(currentTaskAiSuggestion.public_id) + '/preview-apply', {
            method: 'POST',
            headers: { 'X-Idempotency-Key': window.CRM.api.createIdempotencyKey('ai-suggestion-preview') }
          });
        latestPreview = previewEnvelope && previewEnvelope.data ? previewEnvelope.data.preview : null;
        renderTaskAiSuggestionCard(currentTaskAiSuggestion, latestPreview);

        var descChange = (Array.isArray(latestPreview && latestPreview.changes) ? latestPreview.changes : []).find(function (c) {
          return String(c && c.type || '') === 'update_task_description' || String(c && c.field || '') === 'task.description';
        });
        if (!descChange || !descChange.value) {
          setTaskAiState('ready', 'AI не предложил изменений описания.');
          notify('AI не предложил изменений описания.', 'warning');
          return;
        }

        var confirmed = await confirmDescriptionDiff(descChange.value);
        if (!confirmed) {
          setTaskAiState('ready', 'Применение отменено.');
          notify('Применение отменено.', 'warning');
          return;
        }

        setTaskAiState('loading', 'Применяем улучшение описания...');
        var rowVersion = Number(currentTask && currentTask.row_version ? currentTask.row_version : 0);
        if (!rowVersion) {
          var reloadEnvelope = await window.CRM.api.request('api/v1/tasks/' + encodeURIComponent(taskId));
          currentTask = mergeTaskState(extractTaskPayload(reloadEnvelope));
          rowVersion = Number(currentTask && currentTask.row_version ? currentTask.row_version : 0);
        }
        var updateEnvelope = await window.CRM.api.request('api/v1/tasks/' + encodeURIComponent(taskId), {
          method: 'PATCH',
          body: { description: descChange.value, row_version: rowVersion }
        });
        currentTask = mergeTaskState(extractTaskPayload(updateEnvelope));

        var confirmEnvelope = aiClient && typeof aiClient.confirmSuggestion === 'function'
          ? await aiClient.confirmSuggestion(currentTaskAiSuggestion.public_id, {
            decision: 'applied',
            apply_target: 'update_task_description',
            apply_target_public_id: String(taskId || ''),
            row_version: Number(currentTask && currentTask.row_version ? currentTask.row_version : 0)
          })
          : await window.CRM.api.request('api/v1/ai/suggestions/' + encodeURIComponent(currentTaskAiSuggestion.public_id) + '/confirm', {
            method: 'POST',
            headers: { 'X-Idempotency-Key': window.CRM.api.createIdempotencyKey('ai-suggestion-confirm') },
            body: {
              decision: 'applied',
              apply_target: 'update_task_description',
              apply_target_public_id: String(taskId || ''),
              row_version: Number(currentTask && currentTask.row_version ? currentTask.row_version : 0)
            }
          });

        currentTaskAiSuggestion = confirmEnvelope && confirmEnvelope.data ? confirmEnvelope.data.suggestion : currentTaskAiSuggestion;
        latestPreview = null;
        pendingSelectedActions = [];
        renderTaskAiSuggestionCard(currentTaskAiSuggestion, null);
        setTaskAiState('applied', 'AI-предложение применено.');
        if (aiClient && typeof aiClient.setDrawerState === 'function') {
          aiClient.setDrawerState('applied');
        }
        await loadTaskActivity(taskId);
        notify('Описание задачи улучшено.');
      } catch (error) {
        var aiError = toAiUiError(error, 'Не удалось улучшить описание');
        setTaskAiState(toAiUiState(aiError), aiError.message || 'Не удалось улучшить описание');
        notify(aiError.message || 'Не удалось улучшить описание', 'error');
      }
    }

    async function autoApplyAiSuggestion() {
      if (!currentTaskAiSuggestion || !currentTaskAiSuggestion.public_id) return;
      try {
        var previewEnvelope;
        try {
          previewEnvelope = aiClient && typeof aiClient.previewSuggestion === 'function'
            ? await aiClient.previewSuggestion(currentTaskAiSuggestion.public_id)
            : await window.CRM.api.request('api/v1/ai/suggestions/' + encodeURIComponent(currentTaskAiSuggestion.public_id) + '/preview-apply', {
              method: 'POST',
              headers: { 'X-Idempotency-Key': window.CRM.api.createIdempotencyKey('ai-suggestion-preview') }
            });
        } catch (previewError) {
          var previewCode = previewError && previewError.code ? String(previewError.code) : '';
          var previewMsg = previewError && previewError.message ? String(previewError.message) : '';
          if (previewCode === 'AI_SUGGESTION_NOT_ACTIONABLE' || previewCode === 'AI_SUGGESTION_STALE' || previewMsg.indexOf('устарело') >= 0 || previewMsg.indexOf('уже применено') >= 0) {
            setTaskAiState('ready', 'AI-предложение уже применено.');
            notify('AI-предложение уже применено. Нажмите кнопку ещё раз для перегенерации.', 'warning');
            return;
          }
          throw previewError;
        }
        latestPreview = previewEnvelope && previewEnvelope.data ? previewEnvelope.data.preview : null;
        renderTaskAiSuggestionCard(currentTaskAiSuggestion, latestPreview);

        var selectedActions = Array.isArray(latestPreview && latestPreview.changes) ? latestPreview.changes.map(function (change) {
          return {
            type: String(change && change.type || ''),
            field: String(change && change.field || ''),
            label: String(change && change.label || ''),
            value: String(change && change.value || ''),
            high_risk: String(change && change.risk_level || '') === 'high',
            raw: change
          };
        }) : [];

        if (selectedActions.length === 0) {
          setTaskAiState('ready', 'AI-предложение сформировано. Нет действий для применения.');
          notify('AI-предложение сформировано. Нет действий для применения.', 'warning');
          return;
        }

        var hasHighRisk = selectedActions.some(function (action) {
          return Boolean(action && action.high_risk);
        });
        if (hasHighRisk) {
          var confirmed = await showHighRiskModal(selectedActions);
          if (!confirmed) {
            setTaskAiState('ready', 'AI-предложение сформировано. Применение отменено.');
            notify('Применение отменено.', 'warning');
            return;
          }
        }

        setTaskAiState('loading', 'Применяем AI-предложение...');
        var applyResult = await applySelectedActions(selectedActions);

        if (applyResult.appliedCount <= 0) {
          setTaskAiState('error', 'Не удалось применить AI-предложение.');
          notify('Не удалось применить AI-предложение.', 'error');
          return;
        }

        var isPartiallyApplied = applyResult.appliedCount < selectedActions.length || applyResult.invalidCount > 0;
        var confirmDecision = isPartiallyApplied ? 'partially_applied' : 'applied';

        var confirmEnvelope = aiClient && typeof aiClient.confirmSuggestion === 'function'
          ? await aiClient.confirmSuggestion(currentTaskAiSuggestion.public_id, {
            decision: confirmDecision,
            apply_target: applyResult.applyTarget || 'multiple',
            apply_target_public_id: String(taskId || ''),
            row_version: applyResult.rowVersionUsed || undefined,
            warnings: Array.isArray(applyResult.warnings) ? applyResult.warnings : [],
            applied_action_types: Array.isArray(applyResult.appliedActionTypes) ? applyResult.appliedActionTypes : [],
            skipped_action_types: Array.isArray(applyResult.skippedActionTypes) ? applyResult.skippedActionTypes : []
          })
          : await window.CRM.api.request('api/v1/ai/suggestions/' + encodeURIComponent(currentTaskAiSuggestion.public_id) + '/confirm', {
            method: 'POST',
            headers: { 'X-Idempotency-Key': window.CRM.api.createIdempotencyKey('ai-suggestion-confirm') },
            body: {
              decision: confirmDecision,
              apply_target: applyResult.applyTarget || 'multiple',
              apply_target_public_id: String(taskId || ''),
              row_version: applyResult.rowVersionUsed || undefined,
              warnings: Array.isArray(applyResult.warnings) ? applyResult.warnings : [],
              applied_action_types: Array.isArray(applyResult.appliedActionTypes) ? applyResult.appliedActionTypes : [],
              skipped_action_types: Array.isArray(applyResult.skippedActionTypes) ? applyResult.skippedActionTypes : []
            }
          });

        currentTaskAiSuggestion = confirmEnvelope && confirmEnvelope.data ? confirmEnvelope.data.suggestion : currentTaskAiSuggestion;
        latestPreview = null;
        pendingSelectedActions = [];
        renderTaskAiSuggestionCard(currentTaskAiSuggestion, latestPreview);
        setTaskAiState(
          isPartiallyApplied ? 'partially_applied' : 'applied',
          isPartiallyApplied ? 'AI-предложение применено частично.' : 'AI-предложение применено.'
        );
        if (aiClient && typeof aiClient.setDrawerState === 'function') {
          aiClient.setDrawerState(isPartiallyApplied ? 'partially_applied' : 'applied');
        }
        if (applyResult.touchedSubtasks) {
          await loadSubtasks(taskId, Boolean(currentTaskPermissions.canWorkItems), Boolean(currentTaskPermissions.canEditIdentity));
        }
        if (applyResult.touchedChecklists) {
          await loadChecklists(taskId, Boolean(currentTaskPermissions.canWorkItems));
        }
        if (applyResult.touchedComments) {
          await loadTaskComments(taskId);
        }
        if (applyResult.touchedActivity || applyResult.touchedDescription) {
          await loadTaskActivity(taskId);
        }
        notify('AI-предложение применено: ' + String(applyResult.appliedCount) + ' действ.');
      } catch (error) {
        var aiError = toAiUiError(error, 'Не удалось применить AI-предложение');
        setTaskAiState(toAiUiState(aiError), aiError.message || 'Не удалось применить AI-предложение');
        notify(aiError.message || 'Не удалось применить AI-предложение', 'error');
      }
    }

    function normalizeActionMeta(action) {
      if (!action || typeof action !== 'object') return {};
      var raw = action.raw && typeof action.raw === 'object' ? action.raw : {};
      var meta = raw.meta && typeof raw.meta === 'object' ? raw.meta : {};
      return meta;
    }

    async function confirmDescriptionDiff(newDescription) {
      if (!descriptionDiffModal || !descriptionDiffApplyBtn || !window.bootstrap || typeof window.bootstrap.Modal !== 'function') {
        return window.confirm('Применить улучшенное описание задачи?');
      }

      if (descriptionDiffOldNode) {
        descriptionDiffOldNode.textContent = String(currentTask && currentTask.description ? currentTask.description : 'Описание отсутствует');
      }
      if (descriptionDiffNewNode) {
        descriptionDiffNewNode.textContent = String(newDescription || '').trim();
      }

      var modalInstance = window.bootstrap.Modal.getOrCreateInstance(descriptionDiffModal);
      return await new Promise(function (resolve) {
        var settled = false;
        function done(value) {
          if (settled) return;
          settled = true;
          descriptionDiffApplyBtn.removeEventListener('click', onApply);
          descriptionDiffModal.removeEventListener('hidden.bs.modal', onHidden);
          resolve(Boolean(value));
        }
        function onApply() {
          done(true);
          modalInstance.hide();
        }
        function onHidden() {
          done(false);
        }
        descriptionDiffApplyBtn.addEventListener('click', onApply);
        descriptionDiffModal.addEventListener('hidden.bs.modal', onHidden, { once: true });
        modalInstance.show();
      });
    }

    async function applySelectedActions(actions) {
      var selected = Array.isArray(actions) ? actions : [];
      if (selected.length === 0) {
        return {
          appliedCount: 0,
          invalidCount: 0,
          warnings: [],
          appliedActionTypes: [],
          skippedActionTypes: [],
          applyTarget: '',
          rowVersionUsed: 0,
          touchedComments: false,
          touchedSubtasks: false,
          touchedChecklists: false,
          touchedActivity: false,
          touchedDescription: false,
        };
      }

      var createdChecklistMap = {};
      var applyTargets = {};
      var touchedComments = false;
      var touchedSubtasks = false;
      var touchedChecklists = false;
      var touchedActivity = false;
      var touchedDescription = false;
      var appliedCount = 0;
      var invalidCount = 0;
      var warnings = [];
      var appliedActionTypes = [];
      var skippedActionTypes = [];
      var rowVersionUsed = Number(currentTask && currentTask.row_version ? currentTask.row_version : 0);

      for (var index = 0; index < selected.length; index += 1) {
        var action = selected[index] || {};
        var actionType = String(action.type || '').trim();
        var actionField = String(action.field || '').trim();
        var actionValue = String(action.value || '').trim();
        var meta = normalizeActionMeta(action);

        if (!actionType && !actionField) continue;

        if (actionType === 'comment_draft' || actionField === 'task_comment_draft.body' || actionField === 'task_comment.body') {
          if (!actionValue) continue;
          await window.CRM.api.request('api/v1/tasks/' + encodeURIComponent(taskId) + '/comment-draft', {
            method: 'POST',
            body: { body: actionValue }
          });
          var textArea = document.querySelector('#commentForm [name="comment_text"]');
          if (textArea) textArea.value = actionValue;
          touchedComments = true;
          touchedActivity = true;
          applyTargets['/api/v1/tasks/{public_id}/comment-draft'] = true;
          appliedCount += 1;
          appliedActionTypes.push(actionType || actionField || 'create_comment_draft');
          continue;
        }

        if (actionType === 'create_subtask' || actionType === 'create_follow_up_task' || actionField === 'subtask.title') {
          if (!actionValue) continue;
          await window.CRM.api.request('api/v1/tasks/' + encodeURIComponent(taskId) + '/subtasks', {
            method: 'POST',
            body: {
              title: actionValue,
              description: String(meta.description || ''),
              status: 'new',
              priority: 'normal'
            }
          });
          touchedSubtasks = true;
          touchedActivity = true;
          applyTargets['/api/v1/tasks/{public_id}/subtasks'] = true;
          appliedCount += 1;
          appliedActionTypes.push(actionType || actionField || 'create_subtask');
          continue;
        }

        if (actionType === 'create_checklist' || actionField === 'checklist.title') {
          var checklistTitle = actionValue || String(meta.checklist_title || '').trim();
          if (!checklistTitle) {
            continue;
          }
          var createChecklistEnvelope = await window.CRM.api.request('api/v1/tasks/' + encodeURIComponent(taskId) + '/checklists', {
            method: 'POST',
            body: { title: checklistTitle }
          });
          var createdChecklist = createChecklistEnvelope && createChecklistEnvelope.data
            ? createChecklistEnvelope.data.checklist
            : null;
          if (createdChecklist && createdChecklist.public_id) {
            createdChecklistMap[checklistTitle] = String(createdChecklist.public_id);
          }
          touchedChecklists = true;
          touchedActivity = true;
          applyTargets['/api/v1/tasks/{public_id}/checklists'] = true;
          appliedCount += 1;
          appliedActionTypes.push(actionType || actionField || 'create_checklist');
          continue;
        }

        if (actionType === 'create_checklist_item' || actionField === 'checklist_item.title') {
          if (!actionValue) continue;
          var ownerChecklistTitle = String(meta.checklist_title || 'AI checklist').trim();
          if (!ownerChecklistTitle) {
            continue;
          }
          var ownerChecklistId = String(meta.checklist_public_id || createdChecklistMap[ownerChecklistTitle] || '');
          if (!ownerChecklistId) {
            var ownerChecklistEnvelope = await window.CRM.api.request('api/v1/tasks/' + encodeURIComponent(taskId) + '/checklists', {
              method: 'POST',
              body: { title: ownerChecklistTitle }
            });
            var ownerChecklist = ownerChecklistEnvelope && ownerChecklistEnvelope.data
              ? ownerChecklistEnvelope.data.checklist
              : null;
            ownerChecklistId = String(ownerChecklist && ownerChecklist.public_id ? ownerChecklist.public_id : '');
            if (ownerChecklistId) {
              createdChecklistMap[ownerChecklistTitle] = ownerChecklistId;
              applyTargets['/api/v1/tasks/{public_id}/checklists'] = true;
            }
          }
          if (!ownerChecklistId) continue;
          await window.CRM.api.request('api/v1/checklists/' + encodeURIComponent(ownerChecklistId) + '/items', {
            method: 'POST',
            body: { title: actionValue }
          });
          touchedChecklists = true;
          touchedActivity = true;
          applyTargets['/api/v1/checklists/{public_id}/items'] = true;
          appliedCount += 1;
          appliedActionTypes.push(actionType || actionField || 'create_checklist_item');
          continue;
        }

        if (actionType === 'update_task' || actionField === 'task.description') {
          if (!actionValue) continue;
          if (!rowVersionUsed) {
            var reloadEnvelope = await window.CRM.api.request('api/v1/tasks/' + encodeURIComponent(taskId));
            currentTask = mergeTaskState(extractTaskPayload(reloadEnvelope));
            rowVersionUsed = Number(currentTask && currentTask.row_version ? currentTask.row_version : 0);
          }
          if (!rowVersionUsed) {
            throw {
              envelope: {
                code: 'AI_ROW_VERSION_CONFLICT',
                message: 'Для изменения описания требуется актуальная версия задачи.'
              }
            };
          }
          var confirmed = await confirmDescriptionDiff(actionValue);
          if (!confirmed) {
            continue;
          }
          var updateEnvelope = await window.CRM.api.request('api/v1/tasks/' + encodeURIComponent(taskId), {
            method: 'PATCH',
            body: {
              description: actionValue,
              row_version: rowVersionUsed
            }
          });
          currentTask = mergeTaskState(extractTaskPayload(updateEnvelope));
          rowVersionUsed = Number(currentTask && currentTask.row_version ? currentTask.row_version : rowVersionUsed);
          renderTaskDescription(currentTask && currentTask.description ? currentTask.description : '');
          touchedDescription = true;
          touchedActivity = true;
          applyTargets['/api/v1/tasks/{public_id}'] = true;
          appliedCount += 1;
          appliedActionTypes.push(actionType || actionField || 'update_task');
          continue;
        }

        if (
          actionType === 'create_meeting'
          || actionType === 'create_calendar_event'
          || actionType === 'schedule_meeting'
          || actionField === 'calendar_event.title'
          || actionField === 'meeting.title'
        ) {
          if (!openTaskLinkedCalendarModal()) {
            throw {
              envelope: {
                code: 'AI_CALENDAR_MODAL_NOT_AVAILABLE',
                message: 'Не удалось открыть форму встречи. Обновите страницу и повторите.'
              }
            };
          }
          applyTargets['/api/v1/calendar/events'] = true;
          appliedCount += 1;
          appliedActionTypes.push(actionType || actionField || 'create_calendar_event');
          continue;
        }

        if (actionType || actionField || actionValue) {
          invalidCount += 1;
          skippedActionTypes.push(actionType || actionField || 'unknown_action');
          warnings.push('Невалидное или неподдерживаемое AI-действие пропущено: ' + (actionType || actionField || 'unknown_action'));
        }
      }

      var targetList = Object.keys(applyTargets);
      var applyTarget = '';
      if (targetList.length === 1) {
        applyTarget = targetList[0];
      } else if (targetList.length > 1) {
        applyTarget = 'multiple';
      }

      return {
        appliedCount: appliedCount,
        invalidCount: invalidCount,
        warnings: warnings,
        appliedActionTypes: Array.from(new Set(appliedActionTypes)),
        skippedActionTypes: Array.from(new Set(skippedActionTypes)),
        applyTarget: applyTarget,
        rowVersionUsed: rowVersionUsed,
        touchedComments: touchedComments,
        touchedSubtasks: touchedSubtasks,
        touchedChecklists: touchedChecklists,
        touchedActivity: touchedActivity,
        touchedDescription: touchedDescription,
      };
    }

    function bindIntentButton(button, intentPath, successMessage, fallbackMessage, payloadFactory, options) {
      if (aiClient && typeof aiClient.bindAiActionButton === 'function') {
        aiClient.bindAiActionButton(button, {
          canRun: function () { return hasPermission('ai.use'); },
          run: async function () {
            var payload = typeof payloadFactory === 'function' ? payloadFactory() : {};
            await requestTaskSuggestion(intentPath, payload || {}, successMessage, options);
          },
          successMessage: successMessage,
          fallbackMessage: fallbackMessage,
          onError: function (aiError, originalError) {
            applyTaskAiSoftState(aiError);
            if (aiClient && typeof aiClient.renderAiError === 'function') {
              aiClient.renderAiError(originalError, fallbackMessage);
            }
            notify(aiError.message || fallbackMessage, 'error');
          }
        });
        return;
      }
      if (!button || button.dataset.bound === '1') return;
      button.addEventListener('click', async function () {
        if (!hasPermission('ai.use')) return;
        button.disabled = true;
        try {
          var payload = typeof payloadFactory === 'function' ? payloadFactory() : {};
          await requestTaskSuggestion(intentPath, payload || {}, successMessage, options);
          if (successMessage) notify(successMessage);
        } catch (error) {
          var aiError = toAiUiError(error, fallbackMessage);
          applyTaskAiSoftState(aiError);
          notify(aiError.message || fallbackMessage, 'error');
        } finally {
          button.disabled = button.dataset.hardDisabled === '1';
        }
      });
      button.dataset.bound = '1';
    }

    bindIntentButton(generateBtn, 'summary', 'AI-сводка сформирована', 'Не удалось сформировать AI-сводку', function () {
      return { prompt: 'Сделай краткую read-only сводку по задаче.' };
    }, { showDrawer: true });
    bindIntentButton(nextActionBtn, 'next-action', 'AI-следующий шаг сформирован', 'Не удалось сформировать следующий шаг', function () {
      return {};
    }, { showDrawer: true });
    bindIntentButton(decomposeBtn, 'decompose', 'AI-предложение подзадач сформировано', 'Не удалось сформировать предложение подзадач', function () {
      return {};
    }, { showDrawer: true });
    bindIntentButton(checklistBtn, 'checklist', 'AI-предложение чеклиста сформировано', 'Не удалось сформировать AI-чеклист', function () {
      return {};
    }, { showDrawer: true });
    bindIntentButton(improveDescriptionBtn, 'summary', 'AI-предложение улучшенного описания сформировано', 'Не удалось улучшить описание', function () {
      return {
        prompt: 'Сфокусируйся на улучшении описания задачи. Верни результат строго в structured JSON по системной схеме task_summary с полями improved_description и suggested_actions(update_task_description).'
      };
    }, { autoApply: true });
    bindIntentButton(commentDraftBtn, 'comment-draft', 'AI-черновик комментария сформирован', 'Не удалось сформировать AI-черновик комментария', function () {
      return {};
    }, { showDrawer: true });
    bindIntentButton(qualityBtn, 'quality', 'AI-проверка задачи сформирована', 'Не удалось выполнить AI-проверку задачи', function () {
      return {};
    }, { showDrawer: true });

    if (createMeetingBtn && createMeetingBtn.dataset.bound !== '1') {
      createMeetingBtn.addEventListener('click', function () {
        if (!openTaskLinkedCalendarModal()) {
          notify('Не удалось открыть форму встречи. Обновите страницу и повторите.', 'error');
          return;
        }
        notify('Открыта форма встречи. Проверьте детали и сохраните событие вручную.');
      });
      createMeetingBtn.dataset.bound = '1';
    }

    if (previewBtn) {
      previewBtn.addEventListener('click', async function () {
        if (!currentTaskAiSuggestion || !currentTaskAiSuggestion.public_id) {
          notify('Сначала сформируйте AI-сводку', 'warning');
          return;
        }
        if (aiClient && typeof aiClient.canPreviewSuggestion === 'function' && !aiClient.canPreviewSuggestion(currentTaskAiSuggestion)) {
          var blockedMessage = typeof aiClient.suggestionPreviewPolicyMessage === 'function'
            ? aiClient.suggestionPreviewPolicyMessage(currentTaskAiSuggestion)
            : 'Предпросмотр временно недоступен. Обновите AI-результат.';
          setTaskAiState('ready', blockedMessage);
          if (aiClient && typeof aiClient.openSuggestionDrawer === 'function') {
            aiClient.openSuggestionDrawer(currentTaskAiSuggestion, null, ensureDrawerHandlers());
          }
          notify(blockedMessage, 'warning');
          return;
        }
        previewBtn.disabled = true;
        try {
          var preview = await loadSuggestionPreview();
          if (aiClient && typeof aiClient.openSuggestionDrawer === 'function') {
            aiClient.openSuggestionDrawer(currentTaskAiSuggestion, preview, ensureDrawerHandlers());
          }
          notify('Предпросмотр готов. Изменения применяются только вручную.');
        } catch (error) {
          var aiError = toAiUiError(error, 'Не удалось построить предпросмотр');
          setTaskAiState(toAiUiState(aiError), aiError.message || 'Не удалось построить предпросмотр');
          if (aiClient && typeof aiClient.renderAiError === 'function') {
            aiClient.renderAiError(error, 'Не удалось построить предпросмотр');
          }
          notify(aiError.message || 'Не удалось построить предпросмотр', 'error');
        } finally {
          previewBtn.disabled = false;
        }
      });
    }

    if (dismissBtn) {
      dismissBtn.addEventListener('click', async function () {
        if (!currentTaskAiSuggestion || !currentTaskAiSuggestion.public_id) {
          notify('Сводка не выбрана', 'warning');
          return;
        }
        dismissBtn.disabled = true;
        setTaskAiState('loading', 'Отклоняем AI-предложение...');
        try {
          var envelope = aiClient && typeof aiClient.dismissSuggestion === 'function'
            ? await aiClient.dismissSuggestion(currentTaskAiSuggestion.public_id)
            : await window.CRM.api.request('api/v1/ai/suggestions/' + encodeURIComponent(currentTaskAiSuggestion.public_id) + '/dismiss', {
              method: 'POST',
              headers: { 'X-Idempotency-Key': window.CRM.api.createIdempotencyKey('ai-suggestion-dismiss') }
            });
          currentTaskAiSuggestion = envelope && envelope.data ? envelope.data.suggestion : currentTaskAiSuggestion;
          renderTaskAiSuggestionCard(currentTaskAiSuggestion, null);
          setTaskAiState('dismissed', 'AI-предложение скрыто.');
          if (aiClient && typeof aiClient.setDrawerState === 'function') {
            aiClient.setDrawerState('dismissed');
          }
          if (aiClient && typeof aiClient.closeSuggestionDrawer === 'function') {
            aiClient.closeSuggestionDrawer();
          }
          notify('AI-предложение скрыто');
        } catch (error) {
          var aiError = toAiUiError(error, 'Не удалось скрыть AI-предложение');
          setTaskAiState(toAiUiState(aiError), aiError.message || 'Не удалось скрыть AI-предложение');
          if (aiClient && typeof aiClient.renderAiError === 'function') {
            aiClient.renderAiError(error, 'Не удалось скрыть AI-предложение');
          }
          if (aiClient && typeof aiClient.closeSuggestionDrawer === 'function') {
            aiClient.closeSuggestionDrawer();
          }
          notify(aiError.message || 'Не удалось скрыть AI-предложение', 'error');
        } finally {
          dismissBtn.disabled = false;
        }
      });
    }

    if (applyBtn) {
      applyBtn.addEventListener('click', async function () {
        if (!currentTaskAiSuggestion || !currentTaskAiSuggestion.public_id) {
          notify('Сначала сформируйте AI-сводку', 'warning');
          return;
        }
        if (String(currentTaskAiSuggestion.status || '') === 'applied') {
          notify('Предложение уже применено', 'warning');
          return;
        }

        applyBtn.disabled = true;
        setTaskAiState('loading', 'Применяем выбранные AI-действия...');
        try {
          if (!latestPreview) {
            await loadSuggestionPreview();
          }

          var selectedActions = Array.isArray(pendingSelectedActions) && pendingSelectedActions.length > 0
            ? pendingSelectedActions.slice()
            : (Array.isArray(latestPreview && latestPreview.changes) ? (latestPreview.changes.map(function (change) {
              return {
                type: String(change && change.type || ''),
                field: String(change && change.field || ''),
                label: String(change && change.label || ''),
                value: String(change && change.value || ''),
                high_risk: String(change && change.risk_level || '') === 'high',
                raw: change
              };
            })) : []);
          if (selectedActions.length === 0) {
            notify('Для этого предложения нет действий применения. Режим read-only.', 'warning');
            return;
          }

          var hasHighRisk = selectedActions.some(function (action) {
            return Boolean(action && action.high_risk);
          });
          if (hasHighRisk) {
            var confirmed = await showHighRiskModal(selectedActions);
            if (!confirmed) return;
          }

          var applyResult = await applySelectedActions(selectedActions);
          if (applyResult.appliedCount <= 0) {
            if (applyResult.invalidCount > 0) {
              var failedEnvelope = aiClient && typeof aiClient.confirmSuggestion === 'function'
                ? await aiClient.confirmSuggestion(currentTaskAiSuggestion.public_id, {
                  decision: 'failed',
                  apply_target: 'invalid_action',
                  apply_target_public_id: String(taskId || ''),
                  warnings: Array.isArray(applyResult.warnings) ? applyResult.warnings : []
                })
                : await window.CRM.api.request('api/v1/ai/suggestions/' + encodeURIComponent(currentTaskAiSuggestion.public_id) + '/confirm', {
                  method: 'POST',
                  headers: { 'X-Idempotency-Key': window.CRM.api.createIdempotencyKey('ai-suggestion-confirm-failed') },
                  body: {
                    decision: 'failed',
                    apply_target: 'invalid_action',
                    apply_target_public_id: String(taskId || ''),
                    warnings: Array.isArray(applyResult.warnings) ? applyResult.warnings : []
                  }
                });
              currentTaskAiSuggestion = failedEnvelope && failedEnvelope.data ? failedEnvelope.data.suggestion : currentTaskAiSuggestion;
              renderTaskAiSuggestionCard(currentTaskAiSuggestion, latestPreview);
              setTaskAiState('error', 'AI-действия не применены: обнаружены невалидные пункты.');
              notify('AI-действия не применены: обнаружены невалидные пункты.', 'warning');
              return;
            }
            notify('Не выбрано ни одного применимого действия', 'warning');
            return;
          }

          var totalPreviewActions = Array.isArray(latestPreview && latestPreview.changes) ? latestPreview.changes.length : selectedActions.length;
          var isPartiallyApplied = applyResult.appliedCount < totalPreviewActions || applyResult.invalidCount > 0;
          var confirmDecision = isPartiallyApplied ? 'partially_applied' : 'applied';

          var confirmEnvelope = aiClient && typeof aiClient.confirmSuggestion === 'function'
            ? await aiClient.confirmSuggestion(currentTaskAiSuggestion.public_id, {
              decision: confirmDecision,
              apply_target: applyResult.applyTarget || 'multiple',
              apply_target_public_id: String(taskId || ''),
              row_version: applyResult.rowVersionUsed || undefined,
              warnings: Array.isArray(applyResult.warnings) ? applyResult.warnings : [],
              applied_action_types: Array.isArray(applyResult.appliedActionTypes) ? applyResult.appliedActionTypes : [],
              skipped_action_types: Array.isArray(applyResult.skippedActionTypes) ? applyResult.skippedActionTypes : []
            })
            : await window.CRM.api.request('api/v1/ai/suggestions/' + encodeURIComponent(currentTaskAiSuggestion.public_id) + '/confirm', {
              method: 'POST',
              headers: { 'X-Idempotency-Key': window.CRM.api.createIdempotencyKey('ai-suggestion-confirm') },
              body: {
                decision: confirmDecision,
                apply_target: applyResult.applyTarget || 'multiple',
                apply_target_public_id: String(taskId || ''),
                row_version: applyResult.rowVersionUsed || undefined,
                warnings: Array.isArray(applyResult.warnings) ? applyResult.warnings : [],
                applied_action_types: Array.isArray(applyResult.appliedActionTypes) ? applyResult.appliedActionTypes : [],
                skipped_action_types: Array.isArray(applyResult.skippedActionTypes) ? applyResult.skippedActionTypes : []
              }
            });

          currentTaskAiSuggestion = confirmEnvelope && confirmEnvelope.data ? confirmEnvelope.data.suggestion : currentTaskAiSuggestion;
          latestPreview = null;
          pendingSelectedActions = [];
          renderTaskAiSuggestionCard(currentTaskAiSuggestion, latestPreview);
          setTaskAiState(
            isPartiallyApplied ? 'partially_applied' : 'applied',
            isPartiallyApplied
              ? 'AI-предложение применено частично.'
              : 'AI-предложение применено.'
          );
          if (aiClient && typeof aiClient.setDrawerState === 'function') {
            aiClient.setDrawerState(isPartiallyApplied ? 'partially_applied' : 'applied');
          }
          if (aiClient && typeof aiClient.closeSuggestionDrawer === 'function') {
            aiClient.closeSuggestionDrawer();
          }
          if (applyResult.touchedSubtasks) {
            await loadSubtasks(taskId, Boolean(currentTaskPermissions.canWorkItems), Boolean(currentTaskPermissions.canEditIdentity));
          }
          if (applyResult.touchedChecklists) {
            await loadChecklists(taskId, Boolean(currentTaskPermissions.canWorkItems));
          }
          if (applyResult.touchedComments) {
            await loadTaskComments(taskId);
          }
          if (applyResult.touchedActivity || applyResult.touchedDescription) {
    await loadTaskActivity(taskId);
    await loadTaskHistory(taskId);
          }
          notify('AI-предложение применено: ' + String(applyResult.appliedCount) + ' действ.');
        } catch (error) {
          var aiError = toAiUiError(error, 'Не удалось применить AI-сводку');
          setTaskAiState(toAiUiState(aiError), aiError.message || 'Не удалось применить AI-сводку');
          pendingSelectedActions = [];
          if (aiClient && typeof aiClient.renderAiError === 'function') {
            aiClient.renderAiError(error, 'Не удалось применить AI-сводку');
          }
          if (aiClient && typeof aiClient.closeSuggestionDrawer === 'function') {
            aiClient.closeSuggestionDrawer();
          }
          notify(aiError.message || 'Не удалось применить AI-сводку', 'error');
        } finally {
          applyBtn.disabled = false;
        }
      });
    }

    if (regenerateModal && regenerateBtn && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
      var regenerateModalInstance = null;
      regenerateModal.addEventListener('show.bs.modal', function () {
        regenerateModalInstance = window.bootstrap.Modal.getOrCreateInstance(regenerateModal);
        if (regenerateInfo) regenerateInfo.classList.remove('d-none');
        if (regenerateLoading) regenerateLoading.classList.add('d-none');
        if (regenerateFooter) regenerateFooter.classList.remove('d-none');
        if (regenerateModalTitle) regenerateModalTitle.textContent = 'AI-предложение уже существует';
        if (regenerateBtn) {
          regenerateBtn.disabled = false;
          regenerateBtn.innerHTML = 'Перегенерировать';
        }
      });
      regenerateBtn.addEventListener('click', function () {
        if (!pendingRegenerateIntent) return;
        var intent = pendingRegenerateIntent;
        pendingRegenerateIntent = null;
        if (regenerateInfo) regenerateInfo.classList.add('d-none');
        if (regenerateLoading) regenerateLoading.classList.remove('d-none');
        if (regenerateLoadingText) regenerateLoadingText.textContent = 'Перегенерируем AI-предложение...';
        if (regenerateFooter) regenerateFooter.classList.add('d-none');
        if (regenerateModalTitle) regenerateModalTitle.textContent = 'Перегенерация AI';
        if (regenerateBtn) {
          regenerateBtn.disabled = true;
          regenerateBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Перегенерация...';
        }
        doRegenerateTaskSuggestion(intent.intentPath, intent.payload, intent.successMessage, intent.options).then(function () {
          if (regenerateModalInstance) {
            regenerateModalInstance.hide();
          }
        }).catch(function () {
          if (regenerateInfo) regenerateInfo.classList.remove('d-none');
          if (regenerateLoading) regenerateLoading.classList.add('d-none');
          if (regenerateFooter) regenerateFooter.classList.remove('d-none');
          if (regenerateModalTitle) regenerateModalTitle.textContent = 'AI-предложение уже существует';
          if (regenerateBtn) {
            regenerateBtn.disabled = false;
            regenerateBtn.innerHTML = 'Перегенерировать';
          }
        });
      });
    }

    generateBtn.dataset.bound = '1';
  }

  function getDefaultWorklogDraft() {
    var now = new Date();
    return {
      minutes_spent: '60',
      logged_at: new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16),
      note: ''
    };
  }

  function getWorklogDraftFromItem(item) {
    return {
      minutes_spent: String(item && item.minutes_spent ? item.minutes_spent : ''),
      logged_at: toLocalDatetimeValue(item && item.logged_at ? item.logged_at : ''),
      note: String(item && item.note ? item.note : '')
    };
  }

  function renderWorklogs(items) {
    var list = document.getElementById('taskWorklogsList');
    var summary = document.getElementById('taskWorklogSummary');
    var createForm = document.getElementById('worklogCreateForm');
    var addToggleBtn = document.getElementById('worklogAddToggleBtn');
    if (!list) return;

    var totalMinutes = items.reduce(function (acc, item) {
      return acc + Number(item.minutes_spent || 0);
    }, 0);
    if (summary) {
      summary.innerHTML = '<article class="crm-worklog-summary-card">'
        + '<span class="crm-worklog-summary-icon" aria-hidden="true"><i class="fa-regular fa-clock"></i></span>'
        + '<div class="crm-worklog-summary-value">' + escapeHtml(formatMinutes(totalMinutes)) + '</div>'
        + '<div class="crm-worklog-summary-label">Всего времени</div>'
        + '</article>'
        + '<article class="crm-worklog-summary-card">'
        + '<span class="crm-worklog-summary-icon" aria-hidden="true"><i class="fa-regular fa-rectangle-list"></i></span>'
        + '<div class="crm-worklog-summary-value">' + escapeHtml(formatWorklogEntriesLabel(items.length)) + '</div>'
        + '<div class="crm-worklog-summary-label">В журнале</div>'
        + '</article>';
    }

    if (createForm) {
      if (!worklogCreateDraft) worklogCreateDraft = getDefaultWorklogDraft();
      createForm.classList.toggle('d-none', !worklogAddOpen);
      if (worklogAddOpen) {
        var createMinutesInput = createForm.querySelector('[name="minutes_spent"]');
        var createLoggedAtInput = createForm.querySelector('[name="logged_at"]');
        var createNoteInput = createForm.querySelector('[name="note"]');
        if (createMinutesInput) createMinutesInput.value = worklogCreateDraft.minutes_spent;
        if (createLoggedAtInput) createLoggedAtInput.value = worklogCreateDraft.logged_at;
        if (createNoteInput) createNoteInput.value = worklogCreateDraft.note;
      }
    }
    if (addToggleBtn) addToggleBtn.classList.toggle('d-none', worklogAddOpen);

    if (!items.length) {
      list.innerHTML = '<div class="text-muted">Записей времени пока нет.</div>';
      return;
    }

    list.innerHTML = '<div class="vstack gap-2">' + items.map(function (item) {
      var worklogId = String(item.public_id || '');
      var isEditing = worklogActiveEditId === worklogId;
      var draft = isEditing ? (worklogEditDrafts[worklogId] || getWorklogDraftFromItem(item)) : null;
      var author = resolveUserDisplayName(item.user_full_name || item.user_login || '', item.user_public_id || '', '—');
      var note = String(item.note || '').trim();
      var noteHtml = note
        ? '<div class="crm-worklog-note">' + escapeHtml(note) + '</div>'
        : '<div class="crm-worklog-note text-muted">Комментарий не указан</div>';
      if (!isEditing) {
        return '<article class="crm-worklog-card" data-worklog-id="' + escapeHtml(worklogId) + '">'
          + '<div class="crm-worklog-view-head">'
          + '<div class="crm-worklog-view-main"><span class="crm-worklog-entry-icon" aria-hidden="true"><i class="fa-regular fa-clock"></i></span><strong>' + escapeHtml(formatMinutes(item.minutes_spent || 0)) + '</strong>'
          + '<span class="crm-worklog-entry-date"><i class="fa-regular fa-calendar" aria-hidden="true"></i>' + escapeHtml(formatDate(item.logged_at)) + '</span></div>'
          + '<div class="crm-worklog-view-actions"><button class="btn btn-light crm-btn-compact" type="button" data-worklog-edit-open="' + escapeHtml(worklogId) + '">Редактировать</button>'
          + '<details class="crm-worklog-more"><summary class="btn btn-light crm-btn-compact" aria-label="Дополнительные действия"><span>...</span></summary><div class="crm-worklog-more-menu"><button class="btn btn-sm crm-btn-danger crm-btn-compact" type="button" data-worklog-delete-view="' + escapeHtml(worklogId) + '">Удалить</button></div></details></div>'
          + '</div>'
          + noteHtml
          + '<div class="crm-worklog-meta">Автор: ' + escapeHtml(author) + ' · Создано: ' + escapeHtml(formatDate(item.created_at)) + '</div>'
          + '</article>';
      }
      return '<article class="crm-worklog-card is-editing" data-worklog-id="' + escapeHtml(worklogId) + '">'
        + '<div class="crm-worklog-edit-badge">Режим редактирования</div>'
        + '<form class="row g-2" data-worklog-update-form="' + escapeHtml(worklogId) + '">'
        + '<div class="col-md-3"><label class="form-label">Минуты</label><input class="form-control" type="number" min="1" step="1" name="minutes_spent" value="' + escapeHtml(draft.minutes_spent) + '" required></div>'
        + '<div class="col-md-3"><label class="form-label">Дата/время</label><input class="form-control" type="datetime-local" name="logged_at" value="' + escapeHtml(draft.logged_at) + '" required></div>'
        + '<div class="col-md-6"><label class="form-label">Комментарий</label><input class="form-control" name="note" maxlength="8000" value="' + escapeHtml(draft.note) + '"></div>'
        + '<div class="col-12"><div class="crm-worklog-meta">Автор: ' + escapeHtml(author) + ' · Создано: ' + escapeHtml(formatDate(item.created_at)) + '</div></div>'
        + '<div class="col-12 crm-task-row-actions">'
        + '<button class="btn btn-sm crm-btn-primary crm-btn-compact" type="submit">Сохранить</button>'
        + '<button class="btn btn-sm btn-light crm-btn-compact" type="button" data-worklog-edit-cancel="' + escapeHtml(worklogId) + '">Отмена</button>'
        + '<button class="btn btn-sm crm-btn-danger crm-btn-compact" type="button" data-worklog-delete="' + escapeHtml(worklogId) + '">Удалить</button>'
        + '</div>'
        + '</form>'
        + '</article>';
    }).join('') + '</div>';
  }

  async function loadTaskWorklogs(taskId) {
    try {
      var envelope = await window.CRM.api.request('api/v1/worklogs', { query: { task_public_id: taskId, limit: 100 } });
      currentTaskWorklogs = window.CRM.api.items(envelope);
      renderWorklogs(currentTaskWorklogs);
    } catch (e) {
      currentTaskWorklogs = [];
      renderWorklogs([]);
    }
  }

  function bindTaskWorklogFlow(taskId) {
    var createForm = document.getElementById('worklogCreateForm');
    var list = document.getElementById('taskWorklogsList');
    var addToggleBtn = document.getElementById('worklogAddToggleBtn');
    var createCancelBtn = document.getElementById('worklogCreateCancelBtn');
    if (!createForm || !list || !addToggleBtn) return;

    if (!worklogCreateDraft) worklogCreateDraft = getDefaultWorklogDraft();
    renderWorklogs(currentTaskWorklogs);

    if (addToggleBtn.dataset.bound !== '1') {
      addToggleBtn.addEventListener('click', function () {
        if (!worklogCreateDraft) worklogCreateDraft = getDefaultWorklogDraft();
        worklogAddOpen = true;
        worklogActiveEditId = '';
        renderWorklogs(currentTaskWorklogs);
      });
      addToggleBtn.dataset.bound = '1';
    }

    if (createCancelBtn && createCancelBtn.dataset.bound !== '1') {
      createCancelBtn.addEventListener('click', function () {
        worklogAddOpen = false;
        worklogCreateDraft = getDefaultWorklogDraft();
        renderWorklogs(currentTaskWorklogs);
      });
      createCancelBtn.dataset.bound = '1';
    }

    if (createForm.dataset.bound !== '1') {
      createForm.addEventListener('input', function (e) {
        var target = e.target;
        if (!target || !target.name) return;
        if (!worklogCreateDraft) worklogCreateDraft = getDefaultWorklogDraft();
        worklogCreateDraft[target.name] = String(target.value || '');
      });

      createForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        var minutesRaw = String((createForm.querySelector('[name="minutes_spent"]') || {}).value || '').trim();
        var minutes = Number(minutesRaw || 0);
        var note = String((createForm.querySelector('[name="note"]') || {}).value || '').trim();
        var loggedAtRaw = String((createForm.querySelector('[name="logged_at"]') || {}).value || '').trim();
        var loggedAt = toApiDatetimeFromLocal(loggedAtRaw);

        if (minutes <= 0) {
          notify('Укажите количество минут больше нуля', 'warning');
          return;
        }
        if (!loggedAtRaw) {
          notify('Укажите дату и время', 'warning');
          return;
        }

        try {
          await window.CRM.api.request('api/v1/worklogs', {
            method: 'POST',
            body: {
              task_public_id: taskId,
              minutes_spent: minutes,
              note: note,
              logged_at: loggedAt || undefined
            }
          });
          worklogAddOpen = false;
          worklogCreateDraft = getDefaultWorklogDraft();
          await loadTaskWorklogs(taskId);
          await loadTaskActivity(taskId);
          notify('Запись времени добавлена');
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || 'Не удалось добавить запись времени', 'error');
        }
      });
      createForm.dataset.bound = '1';
    }

    if (list.dataset.bound === '1') return;
    list.addEventListener('input', function (e) {
      var form = e.target.closest('[data-worklog-update-form]');
      if (!form) return;
      var worklogId = String(form.getAttribute('data-worklog-update-form') || '');
      if (!worklogId) return;
      if (!worklogEditDrafts[worklogId]) {
        var source = currentTaskWorklogs.find(function (item) { return String(item.public_id || '') === worklogId; });
        worklogEditDrafts[worklogId] = getWorklogDraftFromItem(source || {});
      }
      var target = e.target;
      if (!target || !target.name) return;
      worklogEditDrafts[worklogId][target.name] = String(target.value || '');
    });

    list.addEventListener('submit', async function (e) {
      var form = e.target.closest('[data-worklog-update-form]');
      if (!form) return;
      e.preventDefault();
      var worklogId = String(form.getAttribute('data-worklog-update-form') || '');
      if (!worklogId) return;

      var minutesRaw = String((form.querySelector('[name="minutes_spent"]') || {}).value || '').trim();
      var minutes = Number(minutesRaw || 0);
      var note = String((form.querySelector('[name="note"]') || {}).value || '').trim();
      var loggedAtRaw = String((form.querySelector('[name="logged_at"]') || {}).value || '').trim();
      var loggedAt = toApiDatetimeFromLocal(loggedAtRaw);
      if (minutes <= 0) {
        notify('Укажите количество минут больше нуля', 'warning');
        return;
      }
      if (!loggedAtRaw) {
        notify('Укажите дату и время', 'warning');
        return;
      }

      try {
        await window.CRM.api.request('api/v1/worklogs/' + worklogId, {
          method: 'PATCH',
          body: {
            minutes_spent: minutes,
            note: note,
            logged_at: loggedAt || undefined
          }
        });
        worklogActiveEditId = '';
        delete worklogEditDrafts[worklogId];
        await loadTaskWorklogs(taskId);
        await loadTaskActivity(taskId);
        notify('Запись времени обновлена');
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || 'Не удалось обновить запись времени', 'error');
      }
    });

    list.addEventListener('click', async function (e) {
      var openEditBtn = e.target.closest('[data-worklog-edit-open]');
      if (openEditBtn) {
        var openEditId = String(openEditBtn.getAttribute('data-worklog-edit-open') || '');
        if (!openEditId) return;
        var sourceItem = currentTaskWorklogs.find(function (item) { return String(item.public_id || '') === openEditId; });
        if (sourceItem) worklogEditDrafts[openEditId] = getWorklogDraftFromItem(sourceItem);
        worklogAddOpen = false;
        worklogActiveEditId = openEditId;
        renderWorklogs(currentTaskWorklogs);
        return;
      }

      var cancelEditBtn = e.target.closest('[data-worklog-edit-cancel]');
      if (cancelEditBtn) {
        var cancelEditId = String(cancelEditBtn.getAttribute('data-worklog-edit-cancel') || '');
        if (cancelEditId) delete worklogEditDrafts[cancelEditId];
        worklogActiveEditId = '';
        renderWorklogs(currentTaskWorklogs);
        return;
      }

      var deleteBtn = e.target.closest('[data-worklog-delete]');
      var deleteViewBtn = e.target.closest('[data-worklog-delete-view]');
      if (!deleteBtn && !deleteViewBtn) return;
      deleteBtn = deleteBtn || deleteViewBtn;
      var worklogId = String(deleteBtn.getAttribute('data-worklog-delete') || '');
      if (!worklogId) worklogId = String(deleteBtn.getAttribute('data-worklog-delete-view') || '');
      if (!worklogId) return;
      if (!window.confirm('Удалить запись времени?')) return;

      try {
        await window.CRM.api.request('api/v1/worklogs/' + worklogId, { method: 'DELETE' });
        if (worklogActiveEditId === worklogId) worklogActiveEditId = '';
        delete worklogEditDrafts[worklogId];
        await loadTaskWorklogs(taskId);
        await loadTaskActivity(taskId);
        notify('Запись времени удалена');
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || 'Не удалось удалить запись времени', 'error');
      }
    });

    list.dataset.bound = '1';
  }

  function bindTaskManageFlow(taskId) {
    var panel = document.getElementById('taskManagePanel');
    if (!panel || panel.dataset.bound === '1') return;

    panel.addEventListener('click', function (e) {
      var toggle = e.target.closest('[data-task-edit-toggle]');
      if (toggle) {
        var section = String(toggle.dataset.taskEditToggle || '');
        if (!section) return;
        var form = panel.querySelector('[data-task-manage-form="' + section + '"]');
        if (!form) return;
        form.classList.remove('d-none');
        return;
      }

      var cancel = e.target.closest('[data-task-edit-cancel]');
      if (!cancel) return;
      var cancelSection = String(cancel.dataset.taskEditCancel || '');
      var cancelForm = panel.querySelector('[data-task-manage-form="' + cancelSection + '"]');
      if (cancelForm) cancelForm.classList.add('d-none');
    });

    panel.addEventListener('submit', async function (e) {
      var form = e.target.closest('[data-task-manage-form]');
      if (!form) return;
      e.preventDefault();
      if (!currentTask) return;

      var section = String(form.getAttribute('data-task-manage-form') || '');
      if (section === 'identity' && !currentTaskPermissions.canEditIdentity) {
        notify('Изменение названия и описания доступно только автору задачи', 'warning');
        return;
      }
      if (section === 'workflow' && !currentTaskPermissions.canEditWorkflow) {
        notify('Изменение статуса и приоритета доступно автору или исполнителю задачи', 'warning');
        return;
      }
      if ((section === 'assignment' || section === 'project' || section === 'tags') && !currentTaskPermissions.canEditIdentity) {
        notify('Изменение этого блока доступно только автору задачи', 'warning');
        return;
      }

      try {
        if (section === 'identity') {
          var titleValue = String((form.querySelector('[name="title"]') || {}).value || '').trim();
          var descriptionValue = String((form.querySelector('[name="description"]') || {}).value || '').trim();
          if (!titleValue) {
            notify('Введите название задачи', 'warning');
            return;
          }
          var identityEnvelope = await window.CRM.api.request('api/v1/tasks/' + taskId, {
            method: 'PATCH',
            body: {
              title: titleValue,
              description: descriptionValue,
              row_version: currentTask.row_version
            }
          });
          currentTask = mergeTaskState(extractTaskPayload(identityEnvelope));
          renderTaskDescription(currentTask.description);
          var titleEl = document.querySelector('.crm-page-title');
          if (titleEl) titleEl.textContent = currentTask.title || titleEl.textContent;
        } else if (section === 'project') {
          var projectPublicId = ((form.querySelector('[name="project_public_id"]') || {}).value || '').trim();
          if (projectPublicId !== String(currentTask.project_public_id || '')) {
            var projectTaskEnvelope = await window.CRM.api.request('api/v1/tasks/' + taskId, {
              method: 'PATCH',
              body: {
                project_public_id: projectPublicId,
                row_version: currentTask.row_version
              }
            });
            currentTask = mergeTaskState(extractTaskPayload(projectTaskEnvelope));
          }
        } else {
          var bulkChanges = {};
          if (section === 'workflow') {
            bulkChanges.status = (form.querySelector('[name="status"]') || {}).value || 'new';
            bulkChanges.priority = (form.querySelector('[name="priority"]') || {}).value || 'normal';
          }
          if (section === 'assignment') {
            bulkChanges.assignee_user_public_id = ((form.querySelector('[name="assignee_user_public_id"]') || {}).value || '').trim();
            var managerPublicId = ((form.querySelector('[name="manager_user_public_id"]') || {}).value || '').trim();
            if (managerPublicId && !currentTask.project_public_id) {
              notify('Чтобы назначить менеджера, сначала выберите проект для задачи', 'warning');
              return;
            }
            if (currentTask.project_public_id && String(managerPublicId) !== String(currentTask.project_manager_user_public_id || '')) {
              await loadProjectForTask();
              if (currentProject) {
                await window.CRM.api.request('api/v1/projects/' + currentTask.project_public_id, {
                  method: 'PATCH',
                  body: {
                    row_version: currentProject.row_version,
                    manager_user_public_id: managerPublicId
                  }
                });
              }
            }
          }
          if (section === 'tags') {
            var tagsSelect = form.querySelector('[name="tag_public_ids"]');
            var selectedTagIds = tagsSelect ? Array.prototype.slice.call(tagsSelect.selectedOptions).map(function (opt) { return String(opt.value || ''); }).filter(Boolean) : [];
            var existingTagIds = currentTaskTags.map(function (tag) { return String(tag.public_id || ''); });
            bulkChanges.add_tag_public_ids = selectedTagIds.filter(function (id) { return existingTagIds.indexOf(id) < 0; });
            bulkChanges.remove_tag_public_ids = existingTagIds.filter(function (id) { return selectedTagIds.indexOf(id) < 0; });
          }

          if (Object.keys(bulkChanges).length) {
            await window.CRM.api.request('api/v1/tasks/bulk', {
              method: 'POST',
              body: {
                task_public_ids: [taskId],
                changes: bulkChanges
              }
            });
          }
        }

        var taskEnvelope = await window.CRM.api.request('api/v1/tasks/' + taskId);
        currentTask = mergeTaskState(extractTaskPayload(taskEnvelope));
        await loadProjectForTask();
        if (currentProject && currentProject.manager_user_public_id) {
          currentTask.project_manager_user_public_id = currentProject.manager_user_public_id;
          currentTask.project_manager_name = currentProject.manager_user_name || currentTask.project_manager_name;
        }
        await loadTaskTags(taskId);
        renderTaskMetaChips();
        renderTaskProgressByStatus(currentTask.status_code);
        renderTaskRiskBanner();
        renderTaskSidebarSummary();
        renderTaskStatus(currentTask.status_code);
        await loadTaskActivity(taskId);
        notify('Параметры задачи обновлены');
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || 'Не удалось обновить параметры задачи', 'error');
      }
    });

    panel.dataset.bound = '1';
  }

  function bindTaskEditFlow(taskId) {
    var form = document.getElementById('editTaskForm');
    var modal = document.getElementById('editTaskModal');
    if (!form || !modal) return;
    if (form.dataset.bound === '1') return;

    function fillForm() {
      if (!currentTask) return;
      var titleInput = form.querySelector('[name="title"]');
      var projectSelect = form.querySelector('[name="project_public_id"]');
      var statusSelect = form.querySelector('[name="status"]');
      var prioritySelect = form.querySelector('[name="priority"]');
      var assigneeSelect = form.querySelector('[name="assignee_user_public_id"]');
      var startInput = form.querySelector('[name="start_at"]');
      var dueInput = form.querySelector('[name="due_at"]');
      var endInput = form.querySelector('[name="end_at"]');
      var tagsSelect = form.querySelector('[name="tag_public_ids"]');
      var descInput = form.querySelector('[name="description"]');

      if (titleInput) titleInput.value = currentTask.title || '';
      if (projectSelect) projectSelect.value = currentTask.project_public_id || '';
      if (statusSelect) statusSelect.value = currentTask.status_code || 'new';
      if (prioritySelect) prioritySelect.value = currentTask.priority_code || 'normal';
      if (assigneeSelect) assigneeSelect.value = currentTask.assignee_user_public_id || '';
      if (startInput) startInput.value = toDateInputValue(currentTask.start_at);
      if (dueInput) dueInput.value = toDateInputValue(currentTask.due_at);
      if (endInput) endInput.value = toDateInputValue(currentTask.end_at);
      if (descInput) descInput.value = currentTask.description || '';

      if (tagsSelect && currentTaskTags) {
        var selectedTagIds = currentTaskTags.map(function (tag) { return String(tag.public_id || ''); });
        for (var i = 0; i < tagsSelect.options.length; i++) {
          tagsSelect.options[i].selected = selectedTagIds.indexOf(String(tagsSelect.options[i].value)) >= 0;
        }
      }
    }

    modal.addEventListener('show.bs.modal', async function () {
      await populateEditTaskFormSelects();
      fillForm();
    });

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      if (!currentTask) return;

      var titleInput = form.querySelector('[name="title"]');
      var projectSelect = form.querySelector('[name="project_public_id"]');
      var statusSelect = form.querySelector('[name="status"]');
      var prioritySelect = form.querySelector('[name="priority"]');
      var assigneeSelect = form.querySelector('[name="assignee_user_public_id"]');
      var startInput = form.querySelector('[name="start_at"]');
      var dueInput = form.querySelector('[name="due_at"]');
      var endInput = form.querySelector('[name="end_at"]');
      var tagsSelect = form.querySelector('[name="tag_public_ids"]');
      var descInput = form.querySelector('[name="description"]');

      var title = titleInput ? titleInput.value.trim() : '';
      if (!title) {
        notify('Введите название задачи', 'warning');
        return;
      }

      var body = {
        title: title,
        row_version: currentTask.row_version
      };

      var projectPublicId = projectSelect ? String(projectSelect.value || '').trim() : '';
      body.project_public_id = projectPublicId || null;

      var statusCode = statusSelect ? String(statusSelect.value || '').trim() : '';
      if (statusCode) body.status_code = statusCode;

      var priorityCode = prioritySelect ? String(prioritySelect.value || '').trim() : '';
      if (priorityCode) body.priority_code = priorityCode;

      var assigneePublicId = assigneeSelect ? String(assigneeSelect.value || '').trim() : '';
      if (assigneePublicId || assigneePublicId === '') body.assignee_user_public_id = assigneePublicId;

      var startAt = startInput ? String(startInput.value || '').trim() : '';
      if (startAt) body.start_at = startAt + ' 00:00:00';

      var dueAt = dueInput ? String(dueInput.value || '').trim() : '';
      if (dueAt) body.due_at = dueAt + ' 18:00:00';

      var endAt = endInput ? String(endInput.value || '').trim() : '';
      if (endAt) body.end_at = endAt + ' 18:00:00';

      var description = descInput ? descInput.value.trim() : '';
      body.description = description;

      try {
        var envelope = await window.CRM.api.request('api/v1/tasks/' + taskId, {
          method: 'PATCH',
          body: body
        });

        currentTask = mergeTaskState(extractTaskPayload(envelope));

        if (tagsSelect) {
          var selectedTagIds = Array.prototype.slice.call(tagsSelect.selectedOptions).map(function (opt) { return String(opt.value || '').trim(); }).filter(Boolean);
          var currentTagIds = currentTaskTags.map(function (tag) { return String(tag.public_id || ''); });
          var addTagIds = selectedTagIds.filter(function (id) { return currentTagIds.indexOf(id) < 0; });
          var removeTagIds = currentTagIds.filter(function (id) { return selectedTagIds.indexOf(id) < 0; });

          for (var i = 0; i < addTagIds.length; i += 1) {
            await window.CRM.api.request('api/v1/tasks/' + taskId + '/tags/' + addTagIds[i], { method: 'POST' });
          }
          for (var j = 0; j < removeTagIds.length; j += 1) {
            await window.CRM.api.request('api/v1/tasks/' + taskId + '/tags/' + removeTagIds[j], { method: 'DELETE' });
          }
          await loadTaskTags(taskId);
        }

        var titleEl = document.querySelector('.crm-page-title');
        if (titleEl) titleEl.textContent = currentTask.title || titleEl.textContent;
        renderTaskDescription(currentTask.description);
        renderTaskSidebarSummary();
        renderTaskMetaChips();
        renderTaskProgressByStatus(currentTask.status_code);
        renderTaskRiskBanner();
        var subtitle = document.querySelector('.crm-subtitle');
        if (subtitle) {
          subtitle.textContent = 'Проект: ' + (currentTask.project_title || '—')
            + ' · Дедлайн: ' + (currentTask.due_at ? formatDate(currentTask.due_at) : 'не задан');
        }
        await loadTaskActivity(taskId);

        if (window.bootstrap) {
          window.bootstrap.Modal.getOrCreateInstance(modal).hide();
        }
        notify('Задача обновлена');
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || 'Не удалось обновить задачу', 'error');
      }
    });

    form.dataset.bound = '1';
  }

  async function populateEditTaskFormSelects() {
    if (!availableUsers || !availableUsers.length) {
      try {
        var usersEnvelope = await window.CRM.api.request('api/v1/users', { query: { limit: 200, is_active: 1 } });
        availableUsers = window.CRM.api.items(usersEnvelope);
      } catch (e) {
        availableUsers = [];
      }
    }

    if (!availableTags || !availableTags.length) {
      try {
        var tagsEnvelope = await window.CRM.api.request('api/v1/tags', { query: { limit: 200 } });
        availableTags = window.CRM.api.items(tagsEnvelope);
      } catch (e) {
        availableTags = [];
      }
    }

    if (!availableProjects || !availableProjects.length) {
      try {
        var projectsEnvelope = await window.CRM.api.request('api/v1/projects', { query: { limit: 200 } });
        availableProjects = window.CRM.api.items(projectsEnvelope);
      } catch (e) {
        availableProjects = [];
      }
    }

    if (!currentTaskTags || !currentTaskTags.length) {
      try {
        var tagsEnv = await window.CRM.api.request('api/v1/tasks/' + (currentTask && currentTask.public_id) + '/tags');
        currentTaskTags = window.CRM.api.items(tagsEnv);
      } catch (e) {
        currentTaskTags = [];
      }
    }

    var form = document.getElementById('editTaskForm');
    if (!form) return;

    var projectSelect = form.querySelector('[name="project_public_id"]');
    if (projectSelect) {
      var projects = availableProjects || [];
      var projectOptions = ['<option value="">Без проекта</option>'].concat(projects.map(function (p) {
        var selected = currentTask && String(currentTask.project_public_id || '') === String(p.public_id || '') ? ' selected' : '';
        return '<option value="' + escapeHtml(p.public_id || '') + '"' + selected + '>' + escapeHtml(p.title || p.public_id || '') + '</option>';
      }));
      projectSelect.innerHTML = projectOptions.join('');
    }

    var statusSelect = form.querySelector('[name="status"]');
    if (statusSelect) {
      var fallbackStatuses = [
        { code: 'new', title: 'К выполнению' },
        { code: 'in_progress', title: 'В работе' },
        { code: 'blocked', title: 'Блокировано' },
        { code: 'done', title: 'Готово' }
      ];
      var statuses = (availableTaskStatuses && availableTaskStatuses.length) ? availableTaskStatuses.slice() : fallbackStatuses;
      if (currentTask && currentTask.status_code) {
        var currentCode = String(currentTask.status_code);
        var exists = statuses.some(function (item) { return String(item.code) === currentCode; });
        if (!exists) {
          statuses.push({ code: currentCode, title: statusLabel(currentCode) });
        }
      }
      statusSelect.innerHTML = statuses.map(function (s) {
        var selected = currentTask && String(currentTask.status_code || 'new') === String(s.code || '') ? ' selected' : '';
        return '<option value="' + escapeHtml(s.code || '') + '"' + selected + '>' + escapeHtml(s.title || s.code || '') + '</option>';
      }).join('');
    }

    var prioritySelect = form.querySelector('[name="priority"]');
    if (prioritySelect) {
      var priorities = (typeof availablePriorities !== 'undefined' && availablePriorities && availablePriorities.length)
        ? availablePriorities
        : [
            { code: 'low', title: 'Низкий' },
            { code: 'normal', title: 'Нормальный' },
            { code: 'high', title: 'Высокий' },
            { code: 'urgent', title: 'Срочный' }
          ];
      prioritySelect.innerHTML = priorities.map(function (p) {
        var selected = currentTask && String(currentTask.priority_code || 'normal') === String(p.code || '') ? ' selected' : '';
        return '<option value="' + escapeHtml(p.code || '') + '"' + selected + '>' + escapeHtml(p.title || p.code || '') + '</option>';
      }).join('');
    }

    var assigneeSelect = form.querySelector('[name="assignee_user_public_id"]');
    if (assigneeSelect) {
      var users = availableUsers || [];
      var assigneeOptions = ['<option value="">Не назначен</option>'].concat(users.map(function (u) {
        var selected = currentTask && String(currentTask.assignee_user_public_id || '') === String(u.public_id || '') ? ' selected' : '';
        return '<option value="' + escapeHtml(u.public_id || '') + '"' + selected + '>' + escapeHtml(u.full_name || u.login || u.public_id || '') + '</option>';
      }));
      assigneeSelect.innerHTML = assigneeOptions.join('');
    }

    var tagsSelect = form.querySelector('[name="tag_public_ids"]');
    if (tagsSelect) {
      var tags = availableTags || [];
      var selectedTagIds = currentTaskTags ? currentTaskTags.map(function (tag) { return String(tag.public_id || ''); }) : [];
      tagsSelect.innerHTML = tags.map(function (tag) {
        var tagId = String(tag.public_id || '');
        var selected = selectedTagIds.indexOf(tagId) >= 0 ? ' selected' : '';
        return '<option value="' + escapeHtml(tagId) + '"' + selected + '>' + escapeHtml(tag.title || tag.code || tagId) + '</option>';
      }).join('');
    }
  }

  async function initTaskDetailFlow() {
    var statusBadge = document.getElementById('taskStatusBadge');
    if (!statusBadge) return;

    var taskId = await resolveTaskForDetail();
    if (!taskId) {
      notify('Не удалось определить задачу для карточки', 'warning');
      return;
    }

    try {
      var taskEnvelope = await window.CRM.api.request('api/v1/tasks/' + taskId);
      currentTask = mergeTaskState(extractTaskPayload(taskEnvelope));
    } catch (error) {
      var envelopeError = error && error.envelope ? error.envelope : null;
      notify((envelopeError && envelopeError.message) || 'Не удалось загрузить карточку задачи', 'error');
      return;
    }

    var canEditTask = false;
    var canWorkTask = false;

    await loadTaskReferenceData();
    await loadTaskTags(taskId);
    await loadProjectForTask();

    if (currentTask) {
      currentUserPublicId = getCurrentUserPublicId();
      if (!currentUserPublicId) {
        try {
          var meEnvelope = await window.CRM.api.me();
          var meUser = meEnvelope && meEnvelope.data ? meEnvelope.data.user : null;
          currentUserPublicId = meUser && meUser.public_id ? String(meUser.public_id) : '';
        } catch (e) {
          currentUserPublicId = '';
        }
      }

      var titleEl = document.querySelector('.crm-page-title');
      if (titleEl) titleEl.textContent = currentTask.title || titleEl.textContent;

      var subtitle = document.querySelector('.crm-subtitle');
      if (subtitle) {
        subtitle.textContent = 'Проект: ' + (currentTask.project_title || '—')
          + ' · Дедлайн: ' + (currentTask.due_at ? formatDate(currentTask.due_at) : 'не задан');
      }

      if (currentProject && currentProject.manager_user_public_id) {
        currentTask.project_manager_user_public_id = currentProject.manager_user_public_id;
        currentTask.project_manager_name = currentProject.manager_user_name || currentTask.project_manager_name;
      }

      renderTaskMetaChips();
      renderTaskDescription(currentTask.description);
      renderTaskProgressByStatus(currentTask.status_code);
      renderTaskRiskBanner();
      renderTaskSidebarSummary();
      var userObj = window.CRM.api && typeof window.CRM.api.getUser === 'function' ? window.CRM.api.getUser() : null;
      var isRoot = !!(userObj && userObj.is_root);
      var isAuthor = currentUserPublicId !== ''
        && currentTask.creator_user_public_id
        && currentUserPublicId === String(currentTask.creator_user_public_id);
      var isAssignee = currentUserPublicId !== ''
        && currentTask.assignee_user_public_id
        && currentUserPublicId === String(currentTask.assignee_user_public_id);
      canEditTask = isAuthor || isRoot;
      canWorkTask = isAuthor || isAssignee || isRoot;
      currentTaskPermissions = {
        canEditIdentity: canEditTask,
        canEditWorkflow: canWorkTask,
        canEditAssignment: canEditTask,
        canEditProject: canEditTask,
        canEditTags: canEditTask,
        canWorkItems: canWorkTask
      };
      setTaskEditAvailability(Boolean(canEditTask));
      applyTaskInlinePermissions(currentTaskPermissions);

    }

    try {
      var draftEnvelope = await window.CRM.api.request('api/v1/tasks/' + taskId + '/comment-draft');
      var draftBody = draftEnvelope && draftEnvelope.data && draftEnvelope.data.draft ? draftEnvelope.data.draft.body : '';
      var textArea = document.querySelector('#commentForm [name="comment_text"]');
      if (textArea && draftBody) textArea.value = draftBody;
    } catch (draftError) {
      // no draft is normal
    }

    await loadTaskFiles(taskId);
    try {
      await loadTaskComments(taskId);
    } catch (e) {
      renderTaskComments([]);
    }
    await loadTaskCollaborationState(taskId);
    await loadTaskActivity(taskId);
    await loadSubtasks(taskId, canWorkTask, canEditTask);
    await loadChecklists(taskId, canWorkTask);
    await loadTaskWorklogs(taskId);

    bindTaskStatusButtons(taskId);
    bindTaskCommentFlow(taskId);
    bindTaskFileUpload(taskId);
    bindTaskInlineEditors(taskId);
    bindTaskEditFlow(taskId);
    bindSubtaskFlow(taskId, canWorkTask, canEditTask);
    bindChecklistFlow(taskId, canWorkTask);
    bindTaskWorklogFlow(taskId);
    bindTaskTimerFlow(taskId);
    bindTaskAiSummaryFlow(taskId);

    // Bind dependencies UI
    if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.bindTaskDependencies === 'function') {
      window.CRM.pageApiBindings.bindTaskDependencies(taskId);
    }
  }

  function init() {
    console.log('[BR1] init starting, page:', document.body.dataset.page);
    initLoginFlow();
    initPasswordResetRequestFlow();
    initPasswordResetConfirmFlow();
    initInvitationAcceptFlow();

    if (!window.CRM.api) {
      console.log('CRM API not available');
      return;
    }
    console.log('CRM API available, proceeding with init');

    bindLogoutButtons();

    ensureProtectedAccess();
    if (!enforceRoutePermission()) return;

    hydrateSessionUi().then(function () {
      if (!enforceRoutePermission()) return;
      applyPermissionVisibility();
      if (document.body.dataset.protected === '1' && window.CRM.ai && typeof window.CRM.ai.hydrateAvailability === 'function') {
        var knownIntents = Array.from(new Set(Object.keys(AI_INTENT_VISIBILITY_SELECTORS).map(function (selector) {
          return AI_INTENT_VISIBILITY_SELECTORS[selector];
        })));
        window.CRM.ai.hydrateAvailability(knownIntents).finally(function () {
          applyPermissionVisibility();
        });
      }
      initProjectCreateFlow();
      initTaskCreateFlow();
      initCalendarEventCreateFlow();
      if (window.CRM.navigation && typeof window.CRM.navigation.init === 'function') {
        window.CRM.navigation.init();
      }
      var syncedUser = window.CRM.api && typeof window.CRM.api.getUser === 'function'
        ? window.CRM.api.getUser()
        : null;
      if (syncedUser) {
        setSessionUiUser(syncedUser);
      }
    });
    initTaskDetailFlow();

    if (document.body.dataset.protected === '1') {
      var permissionReapplyScheduled = false;
      var observer = new MutationObserver(function () {
        if (permissionReapplyScheduled) return;
        permissionReapplyScheduled = true;
        window.requestAnimationFrame(function () {
          permissionReapplyScheduled = false;
          applyPermissionVisibility();
        });
      });
      observer.observe(document.body, { childList: true, subtree: true });
    }
  }

  return {
    init: init,
    notify: notify,
    statusBadgeClass: statusBadgeClass,
    statusLabel: statusLabel,
    formatDate: formatDate,
    escapeHtml: escapeHtml,
    getProjectPublicIdFromUrl: getProjectPublicIdFromUrl,
    getTaskPublicIdFromUrl: getTaskPublicIdFromUrl,
    bindLogoutButtons: bindLogoutButtons
  };
})();
