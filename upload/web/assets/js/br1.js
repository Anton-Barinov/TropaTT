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
  var expandedCommentIds = {};
  var COMMENT_COLLAPSE_MAX_HEIGHT = 200;
  var taskTimerTickIntervalId = null;
  var topbarTimerEl = null;
  var topbarTimerTickId = null;
  var topbarTimerTitles = {};
  var topbarTimerTitleFetching = {};
  var topbarTimerMenuKey = null;
  var topbarTimerMenuDocBound = false;
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
      FIGCAPTION: true,
      FIGURE: true,
      I: true,
      IMG: true,
      DETAILS: true,
      SUMMARY: true,
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
        || (valueHref.indexOf('/') === 0 && valueHref.indexOf('//') !== 0)
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
        if (tag === 'IMG' && (attrName === 'src' || attrName === 'alt')) {
          if (attrName === 'src' && !isSafeHref(attr.value || '')) {
            node.removeAttribute(attr.name);
          }
          return;
        }
        if (tag === 'FIGURE' && (attrName === 'data-align' || attrName === 'data-width')) {
          if (attrName === 'data-align' && ['left', 'center', 'right'].indexOf(String(attr.value || '')) === -1) {
            node.setAttribute('data-align', 'center');
          }
          if (attrName === 'data-width') {
            var widthNum = parseFloat(String(attr.value || ''));
            if (!Number.isFinite(widthNum)) {
              node.removeAttribute(attr.name);
            } else {
              node.setAttribute('data-width', String(Math.min(Math.max(widthNum, 10), 100)));
            }
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
      if (tag === 'IMG') {
        var src = String(node.getAttribute('src') || '').trim();
        if (!src || src.toLowerCase().indexOf('data:') === 0) {
          node.remove();
          return;
        }
      }

      Array.prototype.slice.call(node.childNodes || []).forEach(sanitizeNode);
    }

    Array.prototype.slice.call(template.content.childNodes || []).forEach(sanitizeNode);
    return template.innerHTML;
  }

  function fileInputPlaceholder(input) {
    return input && input.multiple
      ? window.CRM.i18n.t('js.br1.vyberite_fayly', 'Выберите файлы')
      : window.CRM.i18n.t('js.br1.vyberite_fayl', 'Выберите файл');
  }

  function fileInputEmptyLabel() {
    return window.CRM.i18n.t('js.br1.fayl_ne_vybran', 'Файл не выбран');
  }

  function fileInputSelectionLabel(input) {
    var files = input && input.files ? input.files : null;
    var list = [];
    var i;
    if (!files || !files.length) return fileInputEmptyLabel();
    if (files.length === 1) return files[0].name || fileInputEmptyLabel();
    for (i = 0; i < files.length && i < 2; i += 1) {
      if (files[i] && files[i].name) list.push(files[i].name);
    }
    if (files.length > 2) {
      return window.CRM.i18n.t('js.br1.faylov_vybrano_count', '{count} файлов выбрано')
        .replace('{count}', String(files.length));
    }
    return list.join(', ');
  }

  function updateEnhancedFileInput(input) {
    var shell = input && input.closest ? input.closest('.crm-file-input-shell') : null;
    var display = shell ? shell.querySelector('.crm-file-input-display') : null;
    if (!shell || !display) return;
    var label = fileInputSelectionLabel(input);
    display.textContent = label;
    display.title = label;
    if (label !== fileInputEmptyLabel()) {
      shell.classList.add('is-filled');
    } else {
      shell.classList.remove('is-filled');
    }
  }

  function enhanceFileInput(input) {
    if (!input || input.getAttribute('data-crm-file-enhanced') === '1') return;
    if (input.type !== 'file' || input.getAttribute('data-crm-file-native') === '1') return;
    if (input.closest && input.closest('.crm-file-input-shell')) {
      input.setAttribute('data-crm-file-enhanced', '1');
      return;
    }

    var shell = document.createElement('div');
    var trigger = document.createElement('button');
    var display = document.createElement('div');
    var parent = input.parentNode;
    var beforeNode = input.nextSibling;

    shell.className = 'crm-file-input-shell';
    if (input.id) {
      shell.setAttribute('data-crm-file-shell-for', input.id);
    }
    trigger.type = 'button';
    trigger.className = 'btn crm-file-input-trigger';
    trigger.textContent = fileInputPlaceholder(input);
    display.className = 'crm-file-input-display';
    display.textContent = fileInputEmptyLabel();

    if (parent) {
      parent.insertBefore(shell, beforeNode);
      shell.appendChild(trigger);
      shell.appendChild(display);
      shell.appendChild(input);
    }

    input.classList.add('crm-file-input-native');
    input.setAttribute('data-crm-file-enhanced', '1');
    input.setAttribute('tabindex', '-1');

    trigger.addEventListener('click', function () {
      if (input.disabled) return;
      input.click();
    });

    shell.addEventListener('click', function (event) {
      if (event.target === trigger) return;
      if (input.disabled) return;
      input.click();
    });

    input.addEventListener('change', function () {
      updateEnhancedFileInput(input);
    });

    updateEnhancedFileInput(input);
  }

  function enhanceFileInputs(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var inputs = scope.querySelectorAll ? scope.querySelectorAll('input[type="file"].crm-file-input') : [];
    var i;
    for (i = 0; i < inputs.length; i += 1) {
      enhanceFileInput(inputs[i]);
    }
  }

  function observeFileInputs() {
    if (!window.MutationObserver) return;
    var scheduled = false;
    var observer = new MutationObserver(function (mutations) {
      var needsEnhance = false;
      var i;
      var j;
      for (i = 0; i < mutations.length; i += 1) {
        if (mutations[i].type !== 'childList') continue;
        for (j = 0; j < mutations[i].addedNodes.length; j += 1) {
          var node = mutations[i].addedNodes[j];
          if (!node || node.nodeType !== 1) continue;
          if ((node.matches && node.matches('input[type="file"].crm-file-input')) || (node.querySelector && node.querySelector('input[type="file"].crm-file-input'))) {
            needsEnhance = true;
            break;
          }
        }
        if (needsEnhance) break;
      }
      if (needsEnhance && !scheduled) {
        scheduled = true;
        window.requestAnimationFrame(function () {
          scheduled = false;
          enhanceFileInputs(document);
        });
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  function renderRichTextOrPlain(value) {
    var text = String(value || '').trim();
    if (!text) return '';
    if (/<[a-z][\s\S]*>/i.test(text)) {
      return sanitizeRichTextHtml(text);
    }
    return escapeHtml(text).replace(/\n/g, '<br>');
  }

  function refreshVisualEditors(scope, force) {
    var root = scope || document;
    if (window.CRM.VisualEditor && typeof window.CRM.VisualEditor.initScope === 'function') {
      window.CRM.VisualEditor.initScope(root);
    }
    if (window.CRM.VisualEditor && typeof window.CRM.VisualEditor.refreshEditors === 'function') {
      window.CRM.VisualEditor.refreshEditors(root, !!force);
    }
  }

  function getVisualEditorTextareaValue(textarea) {
    if (!textarea) return '';
    if (window.CRM.VisualEditor && typeof window.CRM.VisualEditor.getInstances === 'function') {
      var instances = window.CRM.VisualEditor.getInstances();
      for (var i = 0; i < instances.length; i += 1) {
        if (instances[i] && instances[i]._textarea === textarea && typeof instances[i].getValue === 'function') {
          return String(instances[i].getValue() || '');
        }
      }
    }
    return String(textarea.value || '');
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
    if (code === 'in_progress' || code === 'active' || code === 'planning' || code === 'on_hold') return 'active';
    if (code === 'blocked') return 'blocked';
    if (code === 'overdue') return 'overdue';
    return 'archived';
  }

  function normalizeStatusColor(value) {
    var match = String(value || '').trim().match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
    if (!match) return '';
    var hex = match[1];
    if (hex.length === 3) {
      hex = hex.split('').map(function (part) { return part + part; }).join('');
    }
    return '#' + hex.toLowerCase();
  }

  function statusTextColor(backgroundColor) {
    var hex = normalizeStatusColor(backgroundColor);
    if (!hex) return '';

    var red = parseInt(hex.slice(1, 3), 16) / 255;
    var green = parseInt(hex.slice(3, 5), 16) / 255;
    var blue = parseInt(hex.slice(5, 7), 16) / 255;
    var linearize = function (channel) {
      return channel <= 0.03928 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4);
    };
    var luminance = 0.2126 * linearize(red) + 0.7152 * linearize(green) + 0.0722 * linearize(blue);
    return luminance > 0.179 ? '#0f172a' : '#ffffff';
  }

  function statusColor(code) {
    var normalizedCode = String(code || '');
    var status = availableTaskStatuses.find(function (item) {
      return String(item.code || '') === normalizedCode;
    });
    return normalizeStatusColor(status && status.color);
  }

  function applyTaskStatusBadgeColor(statusBadge, code) {
    if (!statusBadge) return;
    var color = statusColor(code);
    statusBadge.style.removeProperty('background-color');
    statusBadge.style.removeProperty('border-color');
    statusBadge.style.removeProperty('color');
    if (!color) return;

    statusBadge.style.backgroundColor = color;
    statusBadge.style.borderColor = color;
    statusBadge.style.color = statusTextColor(color);
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
      new: window.CRM.i18n.t('js.br1.k_vypolneniyu', 'К выполнению'),
      todo: window.CRM.i18n.t('js.br1.k_vypolneniyu_2', 'К выполнению'),
      in_progress: window.CRM.i18n.t('js.br1.v_rabote', 'В работе'),
      active: window.CRM.i18n.t('js.br1.aktivnyy', 'Активный'),
      planning: window.CRM.i18n.t('js.br1.planning', 'Планирование'),
      on_hold: window.CRM.i18n.t('js.br1.na_pauze', 'На паузе'),
      blocked: window.CRM.i18n.t('js.br1.blokirovano', 'Блокировано'),
      done: window.CRM.i18n.t('js.br1.gotovo', 'Готово'),
      completed: window.CRM.i18n.t('js.br1.gotovo_2', 'Готово'),
      archived: window.CRM.i18n.t('js.br1.arkhiv', 'Архив'),
      review: window.CRM.i18n.t('js.br1.review', 'Ревью'),
      qa_testing: window.CRM.i18n.t('js.br1.qa_testing', 'QA тестирование'),
      ready_release: window.CRM.i18n.t('js.br1.ready_release', 'Готова к релизу')
    };
    return map[code] || code || window.CRM.i18n.t('js.br1.bez_statusa', 'Без статуса');
  }

  function priorityLabel(code) {
    var map = {
      low: window.CRM.i18n.t('js.br1.nizkiy', 'Низкий'),
      normal: window.CRM.i18n.t('js.br1.normalnyy', 'Нормальный'),
      high: window.CRM.i18n.t('js.br1.vysokiy', 'Высокий'),
      urgent: window.CRM.i18n.t('js.br1.srochnyy', 'Срочный')
    };
    return map[code] || code || window.CRM.i18n.t('js.br1.bez_prioriteta', 'Без приоритета');
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
    if (!item || typeof item !== 'object') return window.CRM.i18n.t('js.br1.sistema', 'Система');
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

    return window.CRM.i18n.t('js.br1.sistema_2', 'Система');
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

  function extractProjectPayload(envelope) {
    if (!envelope || typeof envelope !== 'object') return null;
    var data = envelope.data;
    if (data && typeof data === 'object' && data.project && typeof data.project === 'object') {
      return data.project;
    }
    if (data && typeof data === 'object' && (data.public_id || data.title || data.status_code || data.task_key_prefix)) {
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
    var targetLabel = localState.target_label || audit.target_login || audit.target_user_public_id || window.CRM.i18n.t('js.br1.polzovatel', 'пользователь');
    var originalLabel = audit.admin_login || audit.admin_user_public_id || window.CRM.i18n.t('js.br1.administrator', 'администратор');
    var existing = document.getElementById('globalImpersonationBanner');
    var banner = existing || document.createElement('div');
    banner.id = 'globalImpersonationBanner';
    banner.className = 'crm-impersonation-banner';
    banner.setAttribute('role', 'status');
    banner.innerHTML = ''
      + window.CRM.i18n.t('js.br1.div_strong_vkhod_kak_polzovatel_strong', '<div><strong>Вход как пользователь:</strong> ') + escapeHtml(targetLabel)
      + window.CRM.i18n.t('js.br1.span_class_text_muted_ms_2_iskhodnaya_sessiya', '<span class="text-muted ms-2">Исходная сессия: ') + escapeHtml(originalLabel) + '</span></div>'
      + window.CRM.i18n.t('js.br1.button_class_btn_btn_sm_btn_danger_type_button_id_globa', '<button class="btn btn-sm crm-btn-danger" type="button" id="globalStopImpersonationBtn">Вернуться</button>');

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
    if (code === 'ai_disabled') return window.CRM.i18n.t('js.br1.ai_vyklyuchen_v_feature_flags', 'AI выключен в feature flags.');
    if (code === 'provider_missing') return window.CRM.i18n.t('js.br1.ai_provayder_ili_secret_eshche_ne_nastroen', 'AI-провайдер или secret еще не настроен.');
    if (code === 'intent_disabled') return window.CRM.i18n.t('js.br1.ai_intent_vyklyuchen_v_nastroykakh', 'AI intent выключен в настройках.');
    if (code === 'feature_disabled') return window.CRM.i18n.t('js.br1.domennyy_ai_feature_flag_vyklyuchen', 'Доменный AI feature flag выключен.');
    if (code === 'permission_required') return window.CRM.i18n.t('js.br1.nedostatochno_prav_dlya_etogo_ai_deystviya', 'Недостаточно прав для этого AI-действия.');
    return window.CRM.i18n.t('js.br1.ai_deystvie_seychas_nedostupno', 'AI-действие сейчас недоступно.');
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
          '[data-task-actions-menu]',
          '#taskEditBtn',
          '#projectCreateTaskBtn',
          '#bulkActionsBar',
          '#subtaskCreateForm',
          '#checklistCreateForm',
          '#worklogCreateForm',
          '#commentForm',
          '#taskFileUploadBtn',
          '[data-crm-file-shell-for="taskFileInput"]',
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
    var fullName = user && (user.full_name || user.login) ? (user.full_name || user.login) : window.CRM.i18n.t('js.br1.gost', 'Гость');
    var publicId = user && user.public_id ? user.public_id : '—';

    document.querySelectorAll('[data-session-user]').forEach(function (el) {
      el.textContent = fullName;
    });

    document.querySelectorAll('[data-session-public-id]').forEach(function (el) {
      el.textContent = publicId;
    });

    // The profile button is the only element that should carry the session
    // user's display name. The old selector also matched ANY `.crm-btn-ghost
    // .dropdown-toggle` on the page, which could overwrite the label of other
    // dropdown buttons with the user name.
    document.querySelectorAll('[data-session-user-btn]').forEach(function (el) {
      el.textContent = fullName;
    });

    // Fallback for server-rendered topbars that mark the user dropdown toggle.
    document.querySelectorAll('.crm-topbar [data-profile-dropdown] .dropdown-toggle, .crm-topbar [data-global-actions] .dropdown .dropdown-toggle').forEach(function (el) {
      if (el.textContent && el.textContent.trim()) {
        el.textContent = fullName;
      }
    });
  }

  // Login diagnostics must never persist credentials or authentication metadata.
  function plog() {}

  function showLoginError(message) {
    var errorNode = document.getElementById('loginError');
    if (!errorNode) return;
    errorNode.classList.remove('d-none');
    errorNode.textContent = String(message || window.CRM.i18n.t('js.br1.oshibka_vkhoda', 'Ошибка входа'));
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
    var loginForm = document.getElementById('loginForm');
    if (!loginForm || loginForm.dataset.crmLoginBound === '1') return;
    var loginInput = loginForm.querySelector('[name="login"]') || loginForm.querySelector('[name="email"]');

    var localeSelect = loginForm.querySelector('[name="locale"]');
    if (localeSelect && window.CRM.api && typeof window.CRM.api.getPreferredLocale === 'function') {
      var preferredLocale = normalizeLocaleCode(window.CRM.api.getPreferredLocale());
      var activeLocale = normalizeLocaleCode((window.CRM && window.CRM.locale) || '');
      var queryLocale = normalizeLocaleCode(new URLSearchParams(window.location.search || '').get('lang') || '');
      if (queryLocale && typeof window.CRM.api.setPreferredLocale === 'function') {
        window.CRM.api.setPreferredLocale(activeLocale || queryLocale);
        preferredLocale = activeLocale || queryLocale;
      }
      localeSelect.value = preferredLocale;
      if (!queryLocale && preferredLocale && activeLocale && preferredLocale !== activeLocale) {
        var localeUrl = new URL(window.location.href);
        localeUrl.searchParams.set('route', 'login');
        localeUrl.searchParams.set('lang', preferredLocale);
        window.location.replace(localeUrl.toString());
        return;
      }
      localeSelect.addEventListener('change', function () {
        var nextLocale = String(localeSelect.value || '').trim().toLowerCase();
        if (typeof window.CRM.api.setPreferredLocale === 'function') {
          window.CRM.api.setPreferredLocale(nextLocale);
        }
        var localeUrl = new URL(window.location.href);
        localeUrl.searchParams.set('route', 'login');
        localeUrl.searchParams.set('lang', nextLocale);
        window.location.href = localeUrl.toString();
      });
    }

    var submitBtn = loginForm.querySelector('button[type="submit"]') || loginForm.querySelector('button[type="button"]') || loginForm.querySelector('button');
    var twoFactorField = document.getElementById('twoFactorLoginField');
    var twoFactorBackBtn = document.getElementById('twoFactorBackBtn');
    var loginButtonLabel = submitBtn ? submitBtn.textContent : '';

    function resetTwoFactorLogin() {
      delete loginForm.dataset.twoFactorToken;
      if (twoFactorField) twoFactorField.classList.add('d-none');
      if (loginInput) loginInput.disabled = false;
      var passwordField = loginForm.querySelector('[name="password"]');
      if (passwordField) passwordField.disabled = false;
      var codeField = loginForm.querySelector('[name="two_factor_code"]');
      if (codeField) codeField.value = '';
      var backupField = loginForm.querySelector('[name="two_factor_backup"]');
      if (backupField) backupField.checked = false;
      if (submitBtn) submitBtn.textContent = loginButtonLabel;
      hideFormAlert('loginError');
      if (loginInput) loginInput.focus();
    }

    if (twoFactorBackBtn) {
      twoFactorBackBtn.addEventListener('click', resetTwoFactorLogin);
    }

    async function handleLogin(e) {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      if (!window.CRM.api || typeof window.CRM.api.login !== 'function') {
        showLoginError(window.CRM.i18n.t('js.br1.ne_udalos_initsializirovat_modul_avtorizatsii_obnovite_2', 'Не удалось инициализировать модуль авторизации. Обновите страницу (Ctrl+F5).'));
        return;
      }

      var passInput = loginForm.querySelector('[name="password"]');

      var login = loginInput ? loginInput.value.trim() : '';
      var password = passInput ? passInput.value.trim() : '';
      var locale = localeSelect ? String(localeSelect.value || '').trim().toLowerCase() : '';
      var pendingTwoFactorToken = String(loginForm.dataset.twoFactorToken || '');
      var twoFactorInput = loginForm.querySelector('[name="two_factor_code"]');
      var backupInput = loginForm.querySelector('[name="two_factor_backup"]');

      if (!pendingTwoFactorToken && (!login || !password)) {
        showLoginError(window.CRM.i18n.t('js.br1.vvedite_login_i_parol_2', 'Введите логин и пароль.'));
        return;
      }
      try {
        var loginEnvelope;
        if (pendingTwoFactorToken) {
          var twoFactorCode = twoFactorInput ? String(twoFactorInput.value || '').trim() : '';
          if (!twoFactorCode) {
            showLoginError(window.CRM.i18n.t('login.two_factor_code_required', 'Введите код подтверждения.'));
            return;
          }
          loginEnvelope = await window.CRM.api.verifyTwoFactor(pendingTwoFactorToken, twoFactorCode, !!(backupInput && backupInput.checked), locale);
        } else {
          loginEnvelope = await window.CRM.api.login(login, password, locale);
          if (loginEnvelope.data && loginEnvelope.data.requires_two_factor) {
            loginForm.dataset.twoFactorToken = String(loginEnvelope.data.login_token || '');
            if (twoFactorField) twoFactorField.classList.remove('d-none');
            if (loginInput) loginInput.disabled = true;
            if (passInput) passInput.disabled = true;
            if (submitBtn) submitBtn.textContent = window.CRM.i18n.t('login.btn_verify_two_factor', 'Подтвердить вход');
            if (twoFactorInput) twoFactorInput.focus();
            return;
          }
        }
        var meEnvelope = await window.CRM.api.me();
        notify(window.CRM.i18n.t('js.br1.vkhod_vypolnen_2', 'Вход выполнен'));

        // External guest (client portal) users have no dashboard/admin access —
        // land them on their projects list instead (see MenuController's
        // external nav allowlist and web/index.php's $externalAllowedRoutes).
        var loggedInUser = meEnvelope && meEnvelope.data ? meEnvelope.data.user : null;
        var defaultRoute = (loggedInUser && loggedInUser.is_external) ? 'projects' : 'dashboard';

        var query = new URLSearchParams(window.location.search);
        var returnRoute = query.get('return_route') || query.get('redirect');
        window.location.href = withQuery(returnRoute || defaultRoute);
      } catch (error) {
        var normalized = window.CRM.api && typeof window.CRM.api.normalizeError === 'function'
          ? window.CRM.api.normalizeError(error, window.CRM.i18n.t('js.br1.oshibka_vkhoda_4', 'Ошибка входа'))
          : { message: window.CRM.i18n.t('js.br1.oshibka_vkhoda_5', 'Ошибка входа'), fieldErrors: {} };
        var message = window.CRM.api && typeof window.CRM.api.formatErrorMessage === 'function'
          ? window.CRM.api.formatErrorMessage(normalized, { withRequestId: normalized.isServerError })
          : normalized.message;
        var authErrors = normalized.fieldErrors && Array.isArray(normalized.fieldErrors.auth) ? normalized.fieldErrors.auth : [];
        var uniqueAuthErrors = authErrors.filter(function (item) {
          return String(item || '').trim() !== String(message || '').trim();
        });
        var details = uniqueAuthErrors.length ? ' (' + uniqueAuthErrors.join(', ') + ')' : '';
        showLoginError(message + details);
      }
    }

    // Bind click handler on submit button (most reliable)
    if (submitBtn) {
      submitBtn.addEventListener('click', handleLogin, true);
    }

    // Also bind form submit as backup
    loginForm.addEventListener('submit', handleLogin, true);
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
        showFormAlert('passwordResetRequestError', window.CRM.i18n.t('js.br1.ukazhite_login_ili_email', 'Укажите логин или email.'), 'error');
        return;
      }
      try {
        await window.CRM.api.request('api/v1/security/password-reset', {
          method: 'POST',
          auth: false,
          body: { identifier: identifier }
        });
        showFormAlert('passwordResetRequestSuccess', window.CRM.i18n.t('js.br1.zapros_prinyat_esli_polzovatel_sushchestvuet_sbros_bude', 'Запрос принят. Если пользователь существует, сброс будет обработан.'), 'success');
      } catch (error) {
        var normalized = window.CRM.api.normalizeError(error, window.CRM.i18n.t('js.br1.ne_udalos_otpravit_zapros', 'Не удалось отправить запрос'));
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
        showFormAlert('passwordResetConfirmError', window.CRM.i18n.t('js.br1.zapolnite_token_i_novyy_parol', 'Заполните токен и новый пароль.'), 'error');
        return;
      }
      if (password.length < 8) {
        showFormAlert('passwordResetConfirmError', window.CRM.i18n.t('js.br1.novyy_parol_dolzhen_soderzhat_minimum_8_simvolov', 'Новый пароль должен содержать минимум 8 символов.'), 'error');
        return;
      }
      try {
        await window.CRM.api.request('api/v1/security/password-reset/confirm', {
          method: 'POST',
          auth: false,
          body: { reset_token: resetToken, new_password: password }
        });
        showFormAlert('passwordResetConfirmSuccess', window.CRM.i18n.t('js.br1.parol_obnovlen_teper_vy_mozhete_voyti_v_sistemu', 'Пароль обновлен. Теперь вы можете войти в систему.'), 'success');
      } catch (error) {
        var normalized = window.CRM.api.normalizeError(error, window.CRM.i18n.t('js.br1.ne_udalos_vypolnit_sbros_parolya', 'Не удалось выполнить сброс пароля'));
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
        showFormAlert('invitationAcceptError', window.CRM.i18n.t('js.br1.zapolnite_vse_obyazatelnye_polya', 'Заполните все обязательные поля.'), 'error');
        return;
      }
      if (body.password.length < 12 || !/[A-Z]/.test(body.password) || !/[a-z]/.test(body.password) || !/[0-9]/.test(body.password)) {
        showFormAlert('invitationAcceptError', window.CRM.i18n.t('js.br1.parol_dolzhen_soderzhat_minimum_12_simvolov', 'Пароль должен содержать минимум 12 символов, включая заглавные и строчные буквы и цифры.'), 'error');
        return;
      }
      try {
        await window.CRM.api.request('api/v1/security/invitations/accept', {
          method: 'POST',
          auth: false,
          body: body
        });
        showFormAlert('invitationAcceptSuccess', window.CRM.i18n.t('js.br1.priglashenie_prinyato_voydite_v_sistemu_pod_novym_login', 'Приглашение принято. Войдите в систему под новым логином.'), 'success');
      } catch (error) {
        var normalized = window.CRM.api.normalizeError(error, window.CRM.i18n.t('js.br1.ne_udalos_prinyat_priglashenie', 'Не удалось принять приглашение'));
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
    var cachedUser = window.CRM.api.getUser();
    if (cachedUser) {
      setSessionUiUser(cachedUser);
      currentUserPublicId = cachedUser.public_id ? String(cachedUser.public_id) : '';
    }

    if (document.body.dataset.protected !== '1') {
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
        /* auth error — keep session alive */
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
          sort_order: Number(item.sort_order || 0),
          color: normalizeStatusColor(item.color)
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
        var projectPrefill = window._projectClientPrefill || '';
        window._projectClientPrefill = null;
        var currentClient = projectPrefill || String(clientSelect.value || '').trim();
        clientSelect.innerHTML = [window.CRM.i18n.t('js.br1.option_value_bez_klienta_option', '<option value="">Без клиента</option>')].concat(availableClients.map(function (client) {
          return '<option value="' + escapeHtml(client.public_id || '') + '">' + escapeHtml(client.title || client.legal_name || client.public_id || window.CRM.i18n.t('js.br1.klient', 'Клиент')) + '</option>';
        })).join('');
        clientSelect.value = currentClient;
      }

      if (teamSelect) {
        var currentTeam = String(teamSelect.value || '').trim();
        teamSelect.innerHTML = [window.CRM.i18n.t('js.br1.option_value_komanda_ne_naznachena_option', '<option value="">Команда не назначена</option>')].concat(availableTeams.map(function (team) {
          return '<option value="' + escapeHtml(team.public_id || '') + '">' + escapeHtml(team.title || team.name || team.public_id || window.CRM.i18n.t('js.br1.komanda', 'Команда')) + '</option>';
        })).join('');
        teamSelect.value = currentTeam;
      }

      if (managerSelect) {
        var currentManager = String(managerSelect.value || '').trim();
        managerSelect.innerHTML = [window.CRM.i18n.t('js.br1.option_value_bez_menedzhera_option', '<option value="">Без менеджера</option>')].concat(availableUsers.map(function (user) {
          return '<option value="' + escapeHtml(user.public_id || '') + '">' + escapeHtml(user.full_name || user.login || user.public_id || window.CRM.i18n.t('js.br1.polzovatel_2', 'Пользователь')) + '</option>';
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
      modal.addEventListener('hidden.bs.modal', function () {
        window._projectClientPrefill = null;
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
        showProjectCreateError(titleEl, window.CRM.i18n.t('js.br1.vvedite_nazvanie_proekta', 'Введите название проекта'));
        notify(window.CRM.i18n.t('js.br1.vvedite_nazvanie_proekta_2', 'Введите название проекта'), 'warning');
        return;
      }

      if (submitBtn) submitBtn.disabled = true;
      try {
        var createEnvelope = await window.CRM.api.request('api/v1/projects', {
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
            priority: priorityEl ? String(priorityEl.value || 'normal') : 'normal',
            task_key_prefix: (function () {
              var prefixEl = form.querySelector('[name="task_key_prefix"]');
              return prefixEl ? String(prefixEl.value || '').trim() : '';
            }())
          }
        });
        var createdProject = extractProjectPayload(createEnvelope);
        if (createdProject && createdProject.public_id) {
          availableProjects = availableProjects.filter(function (item) {
            return String(item.public_id || '') !== String(createdProject.public_id);
          });
          availableProjects.push(createdProject);

          var quickProjectTarget = window._quickProjectTargetSelect;
          if (quickProjectTarget) {
            var projectOptionExists = Array.prototype.some.call(quickProjectTarget.options, function (option) {
              return String(option.value || '') === String(createdProject.public_id);
            });
            if (!projectOptionExists) {
              var projectOption = document.createElement('option');
              projectOption.value = String(createdProject.public_id);
              projectOption.textContent = String(createdProject.title || createdProject.public_id);
              quickProjectTarget.appendChild(projectOption);
            }
            quickProjectTarget.value = String(createdProject.public_id);
            quickProjectTarget.dispatchEvent(new Event('change', { bubbles: true }));
            window._quickProjectTargetSelect = null;
          }
        }

        notify(window.CRM.i18n.t('js.br1.proekt_sozdan', 'Проект создан'));
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
          showProjectCreateError(titleEl, Array.isArray(errors.title) ? String(errors.title[0] || window.CRM.i18n.t('js.br1.proverte_nazvanie_proekta', 'Проверьте название проекта')) : String(errors.title));
        }
        notify((envelope && envelope.message) || window.CRM.i18n.t('js.br1.ne_udalos_sozdat_proekt', 'Не удалось создать проект'), 'error');
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
    var options = [window.CRM.i18n.t('js.br1.option_value_bez_proekta_option', '<option value="">Без проекта</option>')].concat(availableProjects.map(function (project) {
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
    var options = [window.CRM.i18n.t('js.br1.option_value_ne_naznachen_option', '<option value="">Не назначен</option>')].concat(availableUsers.map(function (user) {
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
      { code: 'new', title: window.CRM.i18n.t('js.br1.novaya', 'Новая') },
      { code: 'in_progress', title: window.CRM.i18n.t('js.br1.v_rabote_2', 'В работе') },
      { code: 'blocked', title: window.CRM.i18n.t('js.br1.zablokirovana', 'Заблокирована') },
      { code: 'done', title: window.CRM.i18n.t('js.br1.zavershena', 'Завершена') }
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

    if (availableClients.length === 0) {
      try {
        var clientsEnvelope = await window.CRM.api.request('api/v1/clients', { query: { limit: 200 } });
        availableClients = window.CRM.api.items(clientsEnvelope);
      } catch (e) {
        availableClients = [];
      }
    }

    renderCreateTaskProjectOptions();
    renderCreateTaskClientOptions();
    renderCreateTaskAssigneeOptions();
    renderCreateTaskStatusOptions();
    renderCreateTaskTagOptions();
  }

  function renderCreateTaskClientOptions() {
    var modal = document.getElementById('createTaskModal');
    if (!modal) return;

    var clientSelect = modal.querySelector('select[name="client_public_id"]');
    if (!clientSelect) return;

    var currentValue = clientSelect.value || '';
    var options = [window.CRM.i18n.t('js.br1.option_value_bez_klienta_2', '<option value="">Без клиента</option>')].concat(availableClients.map(function (client) {
      return '<option value="' + escapeHtml(client.public_id || '') + '">' + escapeHtml(client.title || client.legal_name || client.public_id || window.CRM.i18n.t('js.br1.klient', 'Клиент')) + '</option>';
    }));
    clientSelect.innerHTML = options.join('');
    clientSelect.value = currentValue;
  }

  function applyCreateTaskPrefill() {
    var prefill = window._taskClientPrefill || '';
    if (!prefill) return;
    window._taskClientPrefill = null;
    var modal = document.getElementById('createTaskModal');
    if (!modal) return;
    var clientSelect = modal.querySelector('select[name="client_public_id"]');
    if (clientSelect) clientSelect.value = prefill;
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

  function enhanceClientSelects() {
    // ТЗ 3.1: кнопка «+ Создать» рядом с селектом клиента (формы проекта и задачи).
    if (!hasPermission('client.manage')) return;
    document.querySelectorAll('select[name="client_public_id"]').forEach(function (select) {
      if (!select || select.dataset.quickEnhanced === '1') return;
      if (!select.closest('form')) return;
      select.dataset.quickEnhanced = '1';
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-sm crm-btn-secondary crm-quick-client-btn mt-1';
      btn.setAttribute('data-quick-client-create', '1');
      btn.title = window.CRM.i18n.t('js.br1.quick_client_btn_aria', 'Создать клиента');
      btn.textContent = window.CRM.i18n.t('js.br1.quick_client_btn', '+ Создать');
      select.insertAdjacentElement('afterend', btn);
    });
  }

  function enhanceProjectSelects() {
    // Быстрое создание проекта закрывает сценарий «новая задача → новый проект».
    if (!hasPermission('project.manage')) return;
    document.querySelectorAll('#createTaskForm select[name="project_public_id"]').forEach(function (select) {
      if (!select || select.dataset.quickProjectEnhanced === '1') return;
      select.dataset.quickProjectEnhanced = '1';
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-sm crm-btn-secondary crm-quick-project-btn mt-1';
      btn.setAttribute('data-quick-project-create', '1');
      btn.title = window.CRM.i18n.t('js.br1.quick_project_btn_aria', 'Создать проект');
      btn.textContent = window.CRM.i18n.t('js.br1.quick_project_btn', '+ Создать');
      select.insertAdjacentElement('afterend', btn);
    });
  }

  function initQuickProjectCreate() {
    if (window._quickProjectBound === '1') return;
    window._quickProjectBound = '1';

    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('[data-quick-project-create]');
      if (!trigger) return;
      e.preventDefault();
      if (!hasPermission('project.manage')) {
        notify(window.CRM.i18n.t('js.br1.quick_project_no_perm', 'Недостаточно прав для создания проекта'), 'warning');
        return;
      }
      var taskForm = trigger.closest('form');
      var targetSelect = taskForm ? taskForm.querySelector('select[name="project_public_id"]') : null;
      if (!targetSelect) return;
      window._quickProjectTargetSelect = targetSelect;
      // If the task already has a client, carry it into the project form.
      window._projectClientPrefill = String((taskForm.querySelector('select[name="client_public_id"]') || {}).value || '').trim();

      var modalEl = document.getElementById('createProjectModal');
      if (modalEl && window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
      }
    });

    var modalEl = document.getElementById('createProjectModal');
    if (modalEl && modalEl.dataset.quickProjectBound !== '1') {
      modalEl.addEventListener('hidden.bs.modal', function () {
        window._quickProjectTargetSelect = null;
        window._projectClientPrefill = null;
      });
      modalEl.dataset.quickProjectBound = '1';
    }
  }

  function initQuickClientCreate() {
    if (window._quickClientBound === '1') return;
    window._quickClientBound = '1';

    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('[data-quick-client-create]');
      if (!trigger) return;
      e.preventDefault();
      if (!hasPermission('client.manage')) {
        notify(window.CRM.i18n.t('js.br1.quick_client_no_perm', 'Недостаточно прав для создания клиента'), 'warning');
        return;
      }
      var form = trigger.closest('form');
      var targetSelect = form ? form.querySelector('select[name="client_public_id"]') : null;
      if (!targetSelect) return;
      window._quickClientTargetSelect = targetSelect;

      var quickForm = document.getElementById('quickClientCreateForm');
      if (quickForm) quickForm.reset();
      var modalEl = document.getElementById('quickClientCreateModal');
      if (modalEl) {
        var clearTarget = function () { window._quickClientTargetSelect = null; };
        modalEl.removeEventListener('hidden.bs.modal', clearTarget);
        modalEl.addEventListener('hidden.bs.modal', clearTarget);
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
      }
    });

    var quickForm = document.getElementById('quickClientCreateForm');
    if (quickForm && quickForm.dataset.bound !== '1') {
      quickForm.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        var titleInput = quickForm.querySelector('[name="title"]');
        var title = titleInput ? String(titleInput.value || '').trim() : '';
        if (!title) {
          notify(window.CRM.i18n.t('js.br1.vvedite_nazvanie_klienta', 'Введите название клиента'), 'warning');
          return;
        }
        var typeInput = quickForm.querySelector('[name="client_type"]');
        var emailInput = quickForm.querySelector('[name="email"]');
        var phoneInput = quickForm.querySelector('[name="phone"]');
        var submitBtn = quickForm.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        try {
          var envelope = await window.CRM.api.request('api/v1/clients', {
            method: 'POST',
            headers: {
              'X-Idempotency-Key': window.CRM.api.createIdempotencyKey('web-client')
            },
            body: {
              title: title,
              client_type: typeInput ? String(typeInput.value || 'individual') : 'individual',
              email: emailInput ? String(emailInput.value || '').trim() : '',
              phone: phoneInput ? String(phoneInput.value || '').trim() : ''
            }
          });
          var client = (envelope && envelope.data && envelope.data.client) || {};
          var clientPublicId = String(client.public_id || '');
          var targetSelect = window._quickClientTargetSelect;
          window._quickClientTargetSelect = null;
          if (clientPublicId && targetSelect) {
            var exists = Array.prototype.some.call(targetSelect.options, function (opt) { return String(opt.value || '') === clientPublicId; });
            if (!exists) {
              var opt = document.createElement('option');
              opt.value = clientPublicId;
              opt.textContent = client.title || client.legal_name || clientPublicId;
              targetSelect.appendChild(opt);
            }
            targetSelect.value = clientPublicId;
            if (targetSelect.dataset && targetSelect.dataset.searchable === '1') {
              targetSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
          }
          if (typeof availableClients !== 'undefined' && Array.isArray(availableClients)) {
            availableClients = availableClients.filter(function (item) {
              return String(item.public_id || '') !== clientPublicId;
            });
            availableClients.push(client);
          }
          var modalEl = document.getElementById('quickClientCreateModal');
          if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
          }
          notify(window.CRM.i18n.t('js.br1.quick_client_created', 'Клиент создан'));
        } catch (error) {
          var envelope = error && error.envelope ? error.envelope : null;
          notify((envelope && envelope.message) || window.CRM.i18n.t('js.br1.quick_client_error', 'Не удалось создать клиента'), 'error');
        } finally {
          if (submitBtn) submitBtn.disabled = false;
        }
      });
      quickForm.dataset.bound = '1';
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
        applyCreateTaskPrefill();
      });

      modal.addEventListener('hidden.bs.modal', function () {
        window._taskClientPrefill = null;
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
        var clientSelect = form.querySelector('[name="client_public_id"]');
        var statusSelect = form.querySelector('[name="status"]');
        var prioritySelect = form.querySelector('[name="priority"]');
        var assigneeSelect = form.querySelector('[name="assignee_user_public_id"]');
        var tagsSelect = form.querySelector('[name="tag_public_ids"]');
        var submitBtn = form.querySelector('.crm-btn-primary');

        var title = titleInput ? titleInput.value.trim() : '';
        if (!title) {
          notify(window.CRM.i18n.t('js.br1.vvedite_nazvanie_zadachi', 'Введите название задачи'), 'warning');
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
              description: descInput ? getVisualEditorTextareaValue(descInput).trim() : '',
              start_at: normalizeDateTime(startInput && startInput.value ? startInput.value : '', '09:00'),
              due_at: normalizeDateTime(dueInput && dueInput.value ? dueInput.value : '', '18:00'),
              end_at: normalizeDateTime(endInput && endInput.value ? endInput.value : '', '18:00'),
              project_public_id: projectSelect && projectSelect.value ? String(projectSelect.value) : '',
              client_public_id: clientSelect && clientSelect.value ? String(clientSelect.value) : '',
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

          notify(window.CRM.i18n.t('js.br1.zadacha_sozdana', 'Задача создана'));
          if (window.bootstrap) {
            window.bootstrap.Modal.getOrCreateInstance(modal).hide();
          }
          primeCreateTaskDefaults();

          if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.refreshCurrentPage === 'function') {
            window.CRM.pageApiBindings.refreshCurrentPage();
          }
        } catch (error) {
          var envelope = error && error.envelope ? error.envelope : null;
          notify((envelope && envelope.message) || window.CRM.i18n.t('js.br1.ne_udalos_sozdat_zadachu', 'Не удалось создать задачу'), 'error');
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
      if (title) title.textContent = isEdit ? window.CRM.i18n.t('js.br1.redaktirovat_sobytie', 'Редактировать событие') : window.CRM.i18n.t('js.br1.sozdat_sobytie', 'Создать событие');
      if (submitBtn) submitBtn.textContent = isEdit ? window.CRM.i18n.t('js.br1.sokhranit', 'Сохранить') : window.CRM.i18n.t('js.br1.sozdat', 'Создать');
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
      if (title === window.CRM.i18n.t('js.br1.zagruzka_zadachi', 'Загрузка задачи...')) title = '';

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
        + window.CRM.i18n.t('js.br1.span_kontekst_span', '<span>Контекст</span>')
        + window.CRM.i18n.t('js.br1.strong_zadacha', '<strong>Задача: ') + escapeHtml(context.title || context.id) + '</strong>'
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
        showCalendarFormErrors({ title: [window.CRM.i18n.t('js.br1.vvedite_nazvanie_sobytiya', 'Введите название события')] });
        notify(window.CRM.i18n.t('js.br1.vvedite_nazvanie_sobytiya_2', 'Введите название события'), 'warning');
        return;
      }

      var minDate = todayValue();
      if (!startsDateInput || !startsDateInput.value) {
        showCalendarFormErrors({ starts_at_date: [window.CRM.i18n.t('js.br1.vyberite_datu_nachala_sobytiya', 'Выберите дату начала события')] });
        notify(window.CRM.i18n.t('js.br1.vyberite_datu_nachala_sobytiya_2', 'Выберите дату начала события'), 'warning');
        return;
      }
      if (startsDateInput.value < minDate) {
        showCalendarFormErrors({ starts_at_date: [window.CRM.i18n.t('js.br1.nelzya_sozdat_sobytie_na_datu_iz_proshlogo', 'Нельзя создать событие на дату из прошлого')] });
        notify(window.CRM.i18n.t('js.br1.nelzya_sozdat_sobytie_na_datu_iz_proshlogo_2', 'Нельзя создать событие на дату из прошлого'), 'warning');
        startsDateInput.focus();
        return;
      }
      if (endsDateInput && endsDateInput.value && endsDateInput.value < startsDateInput.value) {
        showCalendarFormErrors({ ends_at_date: [window.CRM.i18n.t('js.br1.data_okonchaniya_ne_mozhet_byt_ranshe_daty_nachala', 'Дата окончания не может быть раньше даты начала')] });
        notify(window.CRM.i18n.t('js.br1.data_okonchaniya_ne_mozhet_byt_ranshe_daty_nachala_2', 'Дата окончания не может быть раньше даты начала'), 'warning');
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
        showCalendarFormErrors({ ends_at_time: [window.CRM.i18n.t('js.br1.okonchanie_dolzhno_byt_pozzhe_nachala', 'Окончание должно быть позже начала')] });
        notify(window.CRM.i18n.t('js.br1.okonchanie_sobytiya_dolzhno_byt_pozzhe_nachala', 'Окончание события должно быть позже начала'), 'warning');
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

        notify(editId ? window.CRM.i18n.t('js.br1.sobytie_obnovleno', 'Событие обновлено') : window.CRM.i18n.t('js.br1.sobytie_sozdano', 'Событие создано'));
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
        notify((envelope && envelope.message) || (form.dataset.calendarEditId ? window.CRM.i18n.t('js.br1.ne_udalos_obnovit_sobytie', 'Не удалось обновить событие') : window.CRM.i18n.t('js.br1.ne_udalos_sozdat_sobytie', 'Не удалось создать событие')), 'error');
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

    statusBadge.className = 'crm-badge' + (statusBadge.tagName === 'BUTTON' ? ' dropdown-toggle' : '') + ' ' + statusBadgeClass(code);
    statusBadge.textContent = statusLabel(code);
    applyTaskStatusBadgeColor(statusBadge, code);
  }

  function orderedTaskStatuses(currentStatusCode) {
    var fallback = [
      { code: 'new', title: window.CRM.i18n.t('js.br1.k_vypolneniyu_3', 'К выполнению'), sort_order: 10 },
      { code: 'in_progress', title: window.CRM.i18n.t('js.br1.v_rabote_3', 'В работе'), sort_order: 20 },
      { code: 'blocked', title: window.CRM.i18n.t('js.br1.blokirovano_2', 'Блокировано'), sort_order: 30 },
      { code: 'done', title: window.CRM.i18n.t('js.br1.gotovo_3', 'Готово'), sort_order: 40 }
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
          detail.innerHTML = sanitized || window.CRM.i18n.t('js.br1.p_class_text_muted_mb_0_opisanie_zadachi_ne_zapolneno_p', '<p class="text-muted mb-0">Описание задачи не заполнено.</p>');
          if (window.CRM.VisualEditor && typeof window.CRM.VisualEditor.renderReadonly === 'function') {
            window.CRM.VisualEditor.renderReadonly(detail);
          }
        } else {
          detail.innerHTML = '<p class="mb-0">' + escapeHtml(text).replace(/\n/g, '<br>') + '</p>';
        }
      } else {
        detail.innerHTML = window.CRM.i18n.t('js.br1.p_class_text_muted_mb_0_opisanie_zadachi_ne_zapolneno_p_2', '<p class="text-muted mb-0">Описание задачи не заполнено.</p>');
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
      progressHint.textContent = window.CRM.i18n.t('js.br1.progress', 'Прогресс: ') + String(percent) + window.CRM.i18n.t('js.br1.pozitsiya_statusa', '% (позиция статуса «') + statusLabel(code) + window.CRM.i18n.t('js.br1.v_voronke_statusov', '» в воронке статусов).');
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
      alert.innerHTML = window.CRM.i18n.t('js.br1.strong_risk_strong_zadacha_prosrochena_nuzhen_prioritet', '<strong>Риск:</strong> задача просрочена, нужен приоритетный разбор блокеров.');
      return;
    }

    if (statusCode === 'blocked') {
      alert.className = 'alert alert-warning mb-2';
      alert.innerHTML = window.CRM.i18n.t('js.br1.strong_risk_strong_zadacha_v_bloke_trebuetsya_eskalatsi', '<strong>Риск:</strong> задача в блоке. Требуется эскалация/согласование.');
      return;
    }

    if (statusCode === 'done' || statusCode === 'completed') {
      alert.className = 'alert alert-success mb-2 d-none';
      alert.textContent = '';
      return;
    }

    alert.className = 'alert alert-info mb-2 d-none';
    alert.textContent = '';
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
      descToggle.classList.toggle('d-none', !permissions.canEditIdentity);
    }
  }

  function renderTaskSidebarSummary() {
    if (!currentTask) return;

    var authorEl = document.getElementById('taskAuthorValue');
    if (authorEl) {
      authorEl.textContent = resolveUserDisplayName(
        currentTask.creator_user_name || '',
        currentTask.creator_user_public_id || '',
        window.CRM.i18n.t('js.br1.ne_ukazan', 'Не указан')
      );
    }

    var assigneeEl = document.getElementById('taskAssigneeValue');
    if (assigneeEl) {
      assigneeEl.textContent = resolveUserDisplayName(
        currentTask.assignee_name || currentTask.assignee_login || '',
        currentTask.assignee_user_public_id || '',
        window.CRM.i18n.t('js.br1.ne_naznachen', 'Не назначен')
      );
    }

    var managerEl = document.getElementById('taskManagerValue');
    if (managerEl) {
      managerEl.textContent = resolveUserDisplayName(
        currentTask.project_manager_name || '',
        currentTask.project_manager_user_public_id || '',
        window.CRM.i18n.t('js.br1.ne_naznachen_2', 'Не назначен')
      );
    }

    var tagsEl = document.getElementById('taskTagsValue');
    if (tagsEl) {
      if (currentTaskTags && currentTaskTags.length) {
        tagsEl.innerHTML = currentTaskTags.map(function (tag) {
          return '<span class="crm-chip me-1 mb-1">' + escapeHtml(tag.title || tag.code || tag.public_id || '—') + '</span>';
        }).join('');
      } else {
        tagsEl.textContent = window.CRM.i18n.t('js.br1.net_tegov', 'Нет тегов');
      }
    }

    var projectLink = document.getElementById('taskProjectLink');
    if (projectLink) {
      if (currentTask.project_public_id) {
        projectLink.textContent = currentTask.project_title || currentTask.project_public_id;
        projectLink.href = withQuery('project-detail', 'project_public_id', currentTask.project_public_id);
      } else {
        projectLink.textContent = window.CRM.i18n.t('js.br1.bez_proekta', 'Без проекта');
        projectLink.href = '#';
      }
    }

    var taskKeyEl = document.getElementById('taskKeyValue');
    if (taskKeyEl) {
      if (currentTask.task_key) {
        taskKeyEl.textContent = currentTask.task_key;
      } else {
        taskKeyEl.textContent = window.CRM.i18n.t('js.br1.bez_klyucha', 'Без ключа');
      }
    }

    var copyBtn = document.getElementById('taskKeyCopyBtn');
    if (copyBtn) {
      var keyText = currentTask.task_key || '';
      if (keyText) {
        copyBtn.style.display = '';
        var iconSpan = document.createElement('span');
        iconSpan.className = 'crm-icon';
        iconSpan.setAttribute('aria-hidden', 'true');
        var iconI = document.createElement('i');
        iconI.className = 'fa-regular fa-copy';
        iconSpan.appendChild(iconI);
        copyBtn.innerHTML = '';
        copyBtn.appendChild(iconSpan);
        copyBtn.addEventListener('click', function (e) {
          e.preventDefault();
          navigator.clipboard.writeText(keyText).then(function () {
            copyBtn.innerHTML = '<span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-check" aria-hidden="true"></i></span>';
            copyBtn.classList.add('crm-btn-success');
            setTimeout(function () {
              copyBtn.innerHTML = '<span class="crm-icon" aria-hidden="true"><i class="fa-regular fa-copy" aria-hidden="true"></i></span>';
              copyBtn.classList.remove('crm-btn-success');
            }, 1500);
          })['catch'](function () {
            copyBtn.innerHTML = '<span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-xmark" aria-hidden="true"></i></span>';
            setTimeout(function () {
              copyBtn.innerHTML = '<span class="crm-icon" aria-hidden="true"><i class="fa-regular fa-copy" aria-hidden="true"></i></span>';
            }, 1500);
          });
        });
      } else {
        copyBtn.style.display = 'none';
      }
    }

    var datesEl = document.getElementById('taskDatesValue');
    if (datesEl) {
      var parts = [];
      if (currentTask.start_at) parts.push(window.CRM.i18n.t('js.br1.nachalo', 'Начало: ') + formatDate(currentTask.start_at));
      if (currentTask.due_at) parts.push(window.CRM.i18n.t('js.br1.dedlayn', 'Дедлайн: ') + formatDate(currentTask.due_at));
      if (currentTask.end_at) parts.push(window.CRM.i18n.t('js.br1.zavershenie', 'Завершение: ') + formatDate(currentTask.end_at));
      datesEl.textContent = parts.length ? parts.join(' · ') : window.CRM.i18n.t('js.br1.ne_zadany', 'Не заданы');
    }

    var datesStartInput = document.getElementById('taskDatesStartAt');
    if (datesStartInput) datesStartInput.value = toDateTimeLocalValue(currentTask.start_at);

    var datesDueInput = document.getElementById('taskDatesDueAt');
    if (datesDueInput) datesDueInput.value = toDateTimeLocalValue(currentTask.due_at);

    var datesEndInput = document.getElementById('taskDatesEndAt');
    if (datesEndInput) datesEndInput.value = toDateTimeLocalValue(currentTask.end_at);

    var projectSelect = document.getElementById('taskProjectInlineSelect');
    if (projectSelect) {
      var projectOptions = [window.CRM.i18n.t('js.br1.option_value_bez_proekta_option_2', '<option value="">Без проекта</option>')].concat(availableProjects.map(function (p) {
        var selected = currentTask && String(currentTask.project_public_id || '') === String(p.public_id || '') ? ' selected' : '';
        return '<option value="' + escapeHtml(p.public_id || '') + '"' + selected + '>' + escapeHtml(p.title || p.public_id || '') + '</option>';
      }));
      projectSelect.innerHTML = projectOptions.join('');
    }

    var assigneeSelect = document.getElementById('taskAssigneeInlineSelect');
    if (assigneeSelect) {
      var assigneeOptions = [window.CRM.i18n.t('js.br1.option_value_ne_naznachen_option_2', '<option value="">Не назначен</option>')].concat(availableUsers.map(function (u) {
        var selected = currentTask && String(currentTask.assignee_user_public_id || '') === String(u.public_id || '') ? ' selected' : '';
        return '<option value="' + escapeHtml(u.public_id || '') + '"' + selected + '>' + escapeHtml(u.full_name || u.login || u.public_id || '') + '</option>';
      }));
      assigneeSelect.innerHTML = assigneeOptions.join('');
    }

    var managerSelect = document.getElementById('taskManagerInlineSelect');
    if (managerSelect) {
      var managerOptions = [window.CRM.i18n.t('js.br1.option_value_ne_naznachen_option_3', '<option value="">Не назначен</option>')].concat(availableUsers.map(function (u) {
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
    var descContent = document.getElementById('taskDescriptionContent');

    function openDescriptionEditor() {
      if (!descForm) return;
      var input = document.getElementById('taskDescriptionInlineInput');
      if (input) {
        input.value = currentTask && currentTask.description ? String(currentTask.description) : '';
      }
      if (descContent) descContent.classList.add('d-none');
      descForm.classList.remove('d-none');
      refreshVisualEditors(descForm, true);
      window.setTimeout(function () {
        refreshVisualEditors(descForm, true);
        var content = descForm.querySelector('.crm-ve-content');
        if (content) content.focus();
      }, 80);
    }

    function closeDescriptionEditor() {
      if (descForm) descForm.classList.add('d-none');
      if (descContent) descContent.classList.remove('d-none');
    }

    if (descForm && descForm.dataset.bound !== '1') {
      descForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!currentTaskPermissions.canEditIdentity) {
          notify(window.CRM.i18n.t('js.br1.izmenenie_opisaniya_dostupno_tolko_avtoru_zadachi', 'Изменение описания доступно только автору задачи'), 'warning');
          return;
        }
        var input = document.getElementById('taskDescriptionInlineInput');
        var description = input ? getVisualEditorTextareaValue(input).trim() : '';
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
          closeDescriptionEditor();
          await loadTaskActivity(taskId);
          notify(window.CRM.i18n.t('js.br1.opisanie_obnovleno', 'Описание обновлено'));
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_obnovit_opisanie', 'Не удалось обновить описание'), 'error');
        }
      });
      descForm.dataset.bound = '1';
    }

    if (assigneeForm && assigneeForm.dataset.bound !== '1') {
      assigneeForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!currentTaskPermissions.canEditAssignment) {
          notify(window.CRM.i18n.t('js.br1.izmenenie_ispolnitelya_dostupno_tolko_avtoru_zadachi', 'Изменение исполнителя доступно только автору задачи'), 'warning');
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
          notify(window.CRM.i18n.t('js.br1.ispolnitel_obnovlen', 'Исполнитель обновлен'));
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_obnovit_ispolnitelya', 'Не удалось обновить исполнителя'), 'error');
        }
      });
      assigneeForm.dataset.bound = '1';
    }

    if (managerForm && managerForm.dataset.bound !== '1') {
      managerForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!currentTaskPermissions.canEditAssignment) {
          notify(window.CRM.i18n.t('js.br1.izmenenie_menedzhera_dostupno_tolko_avtoru_zadachi', 'Изменение менеджера доступно только автору задачи'), 'warning');
          return;
        }
        var managerSelect = document.getElementById('taskManagerInlineSelect');
        var managerPublicId = managerSelect ? String(managerSelect.value || '').trim() : '';
        if (!currentTask.project_public_id) {
          notify(window.CRM.i18n.t('js.br1.chtoby_naznachit_menedzhera_snachala_privyazhite_zadach', 'Чтобы назначить менеджера, сначала привяжите задачу к проекту'), 'warning');
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
          notify(window.CRM.i18n.t('js.br1.menedzher_obnovlen', 'Менеджер обновлен'));
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_obnovit_menedzhera', 'Не удалось обновить менеджера'), 'error');
        }
      });
      managerForm.dataset.bound = '1';
    }

    if (projectForm && projectForm.dataset.bound !== '1') {
      projectForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!currentTaskPermissions.canEditProject) {
          notify(window.CRM.i18n.t('js.br1.izmenenie_proekta_dostupno_tolko_avtoru_zadachi', 'Изменение проекта доступно только автору задачи'), 'warning');
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
            subtitle.textContent = window.CRM.i18n.t('js.br1.proekt', 'Проект: ') + (currentTask.project_title || '—')
              + window.CRM.i18n.t('js.br1.dedlayn_2', ' · Дедлайн: ') + (currentTask.due_at ? formatDate(currentTask.due_at) : window.CRM.i18n.t('js.br1.ne_zadan', 'не задан'));
          }
          renderTaskSidebarSummary();
          renderTaskMetaChips();
          projectForm.classList.add('d-none');
          await loadTaskActivity(taskId);
          notify(window.CRM.i18n.t('js.br1.proekt_zadachi_obnovlen', 'Проект задачи обновлен'));
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_obnovit_proekt_zadachi', 'Не удалось обновить проект задачи'), 'error');
        }
      });
      projectForm.dataset.bound = '1';
    }

    if (tagsForm && tagsForm.dataset.bound !== '1') {
      tagsForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!currentTaskPermissions.canEditTags) {
          notify(window.CRM.i18n.t('js.br1.izmenenie_tegov_dostupno_tolko_avtoru_zadachi', 'Изменение тегов доступно только автору задачи'), 'warning');
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
            notify(window.CRM.i18n.t('js.br1.izmeneniy_po_tegam_net', 'Изменений по тегам нет'), 'warning');
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
          notify(window.CRM.i18n.t('js.br1.tegi_zadachi_obnovleny', 'Теги задачи обновлены'));
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_obnovit_tegi_zadachi', 'Не удалось обновить теги задачи'), 'error');
        }
      });
      tagsForm.dataset.bound = '1';
    }

    var datesForm = document.getElementById('taskDatesInlineForm');
    if (datesForm && datesForm.dataset.bound !== '1') {
      datesForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!currentTaskPermissions.canEditIdentity) {
          notify(window.CRM.i18n.t('js.br1.izmenenie_srokov_dostupno_tolko_avtoru_zadachi', 'Изменение сроков доступно только автору задачи'), 'warning');
          return;
        }
        var startAt = String((document.getElementById('taskDatesStartAt') || {}).value || '').trim();
        var dueAt = String((document.getElementById('taskDatesDueAt') || {}).value || '').trim();
        var endAt = String((document.getElementById('taskDatesEndAt') || {}).value || '').trim();
        // Always send all three date fields so clearing a date (empty value) also
        // persists as NULL on the server instead of being silently dropped.
        var body = {
          row_version: currentTask.row_version,
          start_at: startAt ? startAt.replace('T', ' ') : '',
          due_at: dueAt ? dueAt.replace('T', ' ') : '',
          end_at: endAt ? endAt.replace('T', ' ') : ''
        };
        try {
          var envelope = await window.CRM.api.request('api/v1/tasks/' + taskId, {
            method: 'PATCH',
            body: body
          });
          currentTask = mergeTaskState(extractTaskPayload(envelope));
          renderTaskSidebarSummary();
          var subtitle = document.querySelector('.crm-subtitle');
          if (subtitle) {
            subtitle.textContent = window.CRM.i18n.t('js.br1.proekt_2', 'Проект: ') + (currentTask.project_title || '—')
              + window.CRM.i18n.t('js.br1.dedlayn_3', ' · Дедлайн: ') + (currentTask.due_at ? formatDate(currentTask.due_at) : window.CRM.i18n.t('js.br1.ne_zadan_2', 'не задан'));
          }
          datesForm.classList.add('d-none');
          await loadTaskActivity(taskId);
          notify(window.CRM.i18n.t('js.br1.sroki_zadachi_obnovleny', 'Сроки задачи обновлены'));
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_obnovit_sroki_zadachi', 'Не удалось обновить сроки задачи'), 'error');
        }
      });
      datesForm.dataset.bound = '1';
    }

    var summaryCard = document.getElementById('taskSummaryCard');
    var descSection = document.querySelector('.crm-task-description-summary');
    [summaryCard, descSection].forEach(function (container) {
      if (!container || container.dataset.inlineBound === '1') return;
      container.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-task-inline-toggle]');
        if (toggle) {
          var block = String(toggle.getAttribute('data-task-inline-toggle') || '');
          if (block === 'description') {
            if (!currentTaskPermissions.canEditIdentity) {
              notify(window.CRM.i18n.t('js.br1.izmenenie_opisaniya_dostupno_tolko_avtoru_zadachi_2', 'Изменение описания доступно только автору задачи'), 'warning');
              return;
            }
            openDescriptionEditor();
          }
          if (block === 'assignee') {
            if (!currentTaskPermissions.canEditAssignment) {
              notify(window.CRM.i18n.t('js.br1.izmenenie_ispolnitelya_dostupno_tolko_avtoru_zadachi_2', 'Изменение исполнителя доступно только автору задачи'), 'warning');
              return;
            }
            var assigneeInlineForm = document.getElementById('taskAssigneeInlineForm');
            if (assigneeInlineForm) assigneeInlineForm.classList.remove('d-none');
          }
          if (block === 'manager') {
            if (!currentTaskPermissions.canEditAssignment) {
              notify(window.CRM.i18n.t('js.br1.izmenenie_menedzhera_dostupno_tolko_avtoru_zadachi_2', 'Изменение менеджера доступно только автору задачи'), 'warning');
              return;
            }
            var managerInlineForm = document.getElementById('taskManagerInlineForm');
            if (managerInlineForm) managerInlineForm.classList.remove('d-none');
          }
          if (block === 'project') {
            if (!currentTaskPermissions.canEditProject) {
              notify(window.CRM.i18n.t('js.br1.izmenenie_proekta_dostupno_tolko_avtoru_zadachi_2', 'Изменение проекта доступно только автору задачи'), 'warning');
              return;
            }
            var projectInlineForm = document.getElementById('taskProjectInlineForm');
            if (projectInlineForm) projectInlineForm.classList.remove('d-none');
          }
          if (block === 'tags') {
            if (!currentTaskPermissions.canEditTags) {
              notify(window.CRM.i18n.t('js.br1.izmenenie_tegov_dostupno_tolko_avtoru_zadachi_2', 'Изменение тегов доступно только автору задачи'), 'warning');
              return;
            }
            var tagsInlineForm = document.getElementById('taskTagsInlineForm');
            if (tagsInlineForm) tagsInlineForm.classList.remove('d-none');
          }
          if (block === 'dates') {
            if (!currentTaskPermissions.canEditIdentity) {
              notify(window.CRM.i18n.t('js.br1.izmenenie_srokov_dostupno_tolko_avtoru_zadachi_2', 'Изменение сроков доступно только автору задачи'), 'warning');
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
        if (cancelTarget === 'description') closeDescriptionEditor();
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
      return '<span class="crm-chip crm-tag-chip-link" data-tag-id="' + escapeHtml(tag.public_id || '') + '" style="background:' + escapeHtml(tag.color || '#6b7280') + ';color:#fff;cursor:pointer">'
        + escapeHtml(tag.title || tag.code || tag.public_id || '') + '</span>';
    }).join('');

    chips.innerHTML = ''
      + '<div class="dropdown crm-task-status-dropdown">'
      + '<button id="taskStatusBadge" class="crm-badge dropdown-toggle ' + statusBadgeClass(currentTask.status_code) + '" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="' + escapeHtml(window.CRM.i18n.t('task_detail.status_select_label', 'Статус задачи')) + '">' + escapeHtml(statusLabel(currentTask.status_code)) + '</button>'
      + '<ul class="dropdown-menu crm-task-status-menu" id="taskStatusMenu" aria-labelledby="taskStatusBadge"></ul>'
      + '</div>'
      + '<span class="crm-chip" id="taskPriorityChip">' + escapeHtml(priorityLabel(currentTask.priority_code)) + '</span>'
      + tagsHtml;

    applyTaskStatusBadgeColor(document.getElementById('taskStatusBadge'), currentTask.status_code);
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

    var assigneeOptions = [window.CRM.i18n.t('js.br1.option_value_ne_naznachen_option_4', '<option value="">Не назначен</option>')].concat(availableUsers.map(function (u) {
      var selected = currentTask && currentTask.assignee_user_public_id && String(currentTask.assignee_user_public_id) === String(u.public_id) ? ' selected' : '';
      return '<option value="' + escapeHtml(u.public_id || '') + '"' + selected + '>' + escapeHtml(u.full_name || u.login || u.public_id || '') + '</option>';
    })).join('');

    var managerOptions = [window.CRM.i18n.t('js.br1.option_value_ne_naznachen_option_5', '<option value="">Не назначен</option>')].concat(availableUsers.map(function (u) {
      var selected = currentTask && currentTask.project_manager_user_public_id && String(currentTask.project_manager_user_public_id) === String(u.public_id) ? ' selected' : '';
      return '<option value="' + escapeHtml(u.public_id || '') + '"' + selected + '>' + escapeHtml(u.full_name || u.login || u.public_id || '') + '</option>';
    })).join('');

    var projectOptions = [window.CRM.i18n.t('js.br1.option_value_bez_proekta_option_3', '<option value="">Без проекта</option>')].concat(availableProjects.map(function (project) {
      var selected = currentTask && currentTask.project_public_id && String(currentTask.project_public_id) === String(project.public_id) ? ' selected' : '';
      return '<option value="' + escapeHtml(project.public_id || '') + '"' + selected + '>' + escapeHtml(project.title || project.public_id || '') + '</option>';
    })).join('');

    var currentTagIds = currentTaskTags.map(function (t) { return String(t.public_id || ''); });
    var tagOptions = availableTags.map(function (t) {
      var selected = currentTagIds.indexOf(String(t.public_id || '')) >= 0 ? ' selected' : '';
      return '<option value="' + escapeHtml(t.public_id || '') + '"' + selected + '>' + escapeHtml(t.title || t.code || t.public_id || '') + '</option>';
    }).join('');

    var fallbackTaskStatuses = [
      { code: 'new', title: window.CRM.i18n.t('js.br1.k_vypolneniyu_4', 'К выполнению'), sort_order: 10 },
      { code: 'in_progress', title: window.CRM.i18n.t('js.br1.v_rabote_4', 'В работе'), sort_order: 20 },
      { code: 'blocked', title: window.CRM.i18n.t('js.br1.blokirovano_3', 'Блокировано'), sort_order: 30 },
      { code: 'done', title: window.CRM.i18n.t('js.br1.gotovo_4', 'Готово'), sort_order: 40 }
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
      : window.CRM.i18n.t('js.br1.net_tegov_2', 'Нет тегов');
    var currentProjectTitle = window.CRM.i18n.t('js.br1.bez_proekta_2', 'Без проекта');
    if (currentTask && currentTask.project_public_id) {
      var selectedProject = availableProjects.find(function (project) {
        return String(project.public_id || '') === String(currentTask.project_public_id || '');
      });
      currentProjectTitle = selectedProject
        ? String(selectedProject.title || selectedProject.public_id || window.CRM.i18n.t('js.br1.bez_proekta_3', 'Без проекта'))
        : String(currentTask.project_public_id || window.CRM.i18n.t('js.br1.bez_proekta_4', 'Без проекта'));
    }

    panel.innerHTML = ''
      + '<div class="crm-card p-3 bg-light-subtle">'
      + window.CRM.i18n.t('js.br1.div_class_d_flex_justify_content_between_align_items_ce', '<div class="d-flex justify-content-between align-items-center mb-3"><h3 class="h6 mb-0">Параметры задачи</h3><small class="text-muted">')
      + (canEditIdentity ? window.CRM.i18n.t('js.br1.vy_avtor_zadachi_dostupno_redaktirovanie_vsekh_parametr', 'Вы автор задачи: доступно редактирование всех параметров.') : (canEditWorkflow ? window.CRM.i18n.t('js.br1.vy_ispolnitel_zadachi_dostupno_rabochee_izmenenie_statu', 'Вы исполнитель задачи: доступно рабочее изменение статуса и приоритета.') : window.CRM.i18n.t('js.br1.redaktirovanie_parametrov_nedostupno', 'Редактирование параметров недоступно.')))
      + '</small></div>'
      + '<div class="row g-3">'
      + '<div class="col-lg-12">'
      + '<article class="border rounded-3 p-3 h-100">'
      + window.CRM.i18n.t('js.br1.div_class_d_flex_justify_content_between_align_items_ce_2', '<div class="d-flex justify-content-between align-items-center mb-2"><h4 class="h6 mb-0">Название и описание</h4><button type="button" class="btn btn-sm crm-btn-secondary" data-task-edit-toggle="identity"') + (canEditIdentity ? '' : ' disabled') + ' aria-label="Edit"><i class="fa-solid fa-pen" aria-hidden="true"></i></button></div>'
      + window.CRM.i18n.t('js.br1.div_class_small_text_muted_mb_1_nazvanie_div_div_class', '<div class="small text-muted mb-1">Название</div><div class="mb-2">') + escapeHtml(currentTask && currentTask.title || '—') + '</div>'
      + window.CRM.i18n.t('js.br1.div_class_small_text_muted_mb_1_opisanie_div_div_class', '<div class="small text-muted mb-1">Описание</div><div class="mb-2">') + escapeHtml(currentTask && currentTask.description || window.CRM.i18n.t('js.br1.opisanie_otsutstvuet', 'Описание отсутствует')) + '</div>'
      + '<form class="row g-2 d-none" data-task-manage-form="identity">'
      + window.CRM.i18n.t('js.br1.div_class_col_md_6_label_class_form_label_nazvanie_labe', '<div class="col-md-6"><label class="form-label">Название</label><input class="form-control" name="title" maxlength="255" value="') + escapeHtml(currentTask && currentTask.title || '') + '"></div>'
      + window.CRM.i18n.t('js.br1.div_class_col_md_6_label_class_form_label_opisanie_labe', '<div class="col-md-6"><label class="form-label">Описание</label><textarea class="form-control" name="description" rows="3" data-crm-visual-editor="1">') + escapeHtml(currentTask && currentTask.description || '') + '</textarea></div>'
      + window.CRM.i18n.t('js.br1.div_class_col_12_d_flex_gap_2_button_type_submit_class', '<div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-sm crm-btn-primary">Сохранить</button><button type="button" class="btn btn-sm crm-btn-secondary" data-task-edit-cancel="identity">Отмена</button></div>')
      + '</form>'
      + '</article>'
      + '</div>'
      + '<div class="col-lg-6">'
      + '<article class="border rounded-3 p-3 h-100">'
      + window.CRM.i18n.t('js.br1.div_class_d_flex_justify_content_between_align_items_ce_3', '<div class="d-flex justify-content-between align-items-center mb-2"><h4 class="h6 mb-0">Статус и приоритет</h4><button type="button" class="btn btn-sm crm-btn-secondary" data-task-edit-toggle="workflow"') + (canEditWorkflow ? '' : ' disabled') + ' aria-label="Edit"><i class="fa-solid fa-pen" aria-hidden="true"></i></button></div>'
      + window.CRM.i18n.t('js.br1.div_class_small_text_muted_mb_1_status_div_div_class_mb', '<div class="small text-muted mb-1">Статус</div><div class="mb-2"><span class="crm-badge ') + statusBadgeClass(currentTask && currentTask.status_code || 'new') + '">' + escapeHtml(statusLabel(currentTask && currentTask.status_code || 'new')) + '</span></div>'
      + window.CRM.i18n.t('js.br1.div_class_small_text_muted_mb_1_prioritet_div_div_class', '<div class="small text-muted mb-1">Приоритет</div><div class="mb-2">') + escapeHtml(priorityLabel(currentTask && currentTask.priority_code || 'normal')) + '</div>'
      + '<form class="row g-2 d-none" data-task-manage-form="workflow">'
      + window.CRM.i18n.t('js.br1.div_class_col_6_label_class_form_label_status_label_sel', '<div class="col-6"><label class="form-label">Статус</label><select class="form-select" name="status">') + statusOptions + '</select></div>'
      + window.CRM.i18n.t('js.br1.div_class_col_6_label_class_form_label_prioritet_label', '<div class="col-6"><label class="form-label">Приоритет</label><select class="form-select" name="priority">')
      + '<option value="low"' + (currentTask && currentTask.priority_code === 'low' ? ' selected' : '') + window.CRM.i18n.t('js.br1.nizkiy_option', '>Низкий</option>')
      + '<option value="normal"' + (currentTask && currentTask.priority_code === 'normal' ? ' selected' : '') + window.CRM.i18n.t('js.br1.normalnyy_option', '>Нормальный</option>')
      + '<option value="high"' + (currentTask && currentTask.priority_code === 'high' ? ' selected' : '') + window.CRM.i18n.t('js.br1.vysokiy_option', '>Высокий</option>')
      + '<option value="urgent"' + (currentTask && currentTask.priority_code === 'urgent' ? ' selected' : '') + window.CRM.i18n.t('js.br1.srochnyy_option', '>Срочный</option>')
      + '</select></div>'
      + window.CRM.i18n.t('js.br1.div_class_col_12_d_flex_gap_2_button_type_submit_class_2', '<div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-sm crm-btn-primary">Сохранить</button><button type="button" class="btn btn-sm crm-btn-secondary" data-task-edit-cancel="workflow">Отмена</button></div>')
      + '</form>'
      + '</article>'
      + '</div>'
      + '<div class="col-lg-6">'
      + '<article class="border rounded-3 p-3 h-100">'
      + window.CRM.i18n.t('js.br1.div_class_d_flex_justify_content_between_align_items_ce_4', '<div class="d-flex justify-content-between align-items-center mb-2"><h4 class="h6 mb-0">Исполнители</h4><button type="button" class="btn btn-sm crm-btn-secondary" data-task-edit-toggle="assignment"') + (canEditAssignment ? '' : ' disabled') + ' aria-label="Edit"><i class="fa-solid fa-pen" aria-hidden="true"></i></button></div>'
      + window.CRM.i18n.t('js.br1.div_class_small_text_muted_mb_1_ispolnitel_div_div_clas', '<div class="small text-muted mb-1">Исполнитель</div><div class="mb-2">') + escapeHtml(currentTask && (currentTask.assignee_name || currentTask.assignee_login || currentTask.assignee_user_public_id) || window.CRM.i18n.t('js.br1.ne_naznachen_3', 'Не назначен')) + '</div>'
      + window.CRM.i18n.t('js.br1.div_class_small_text_muted_mb_1_menedzher_proekta_div_d', '<div class="small text-muted mb-1">Менеджер проекта</div><div class="mb-2">') + escapeHtml(currentTask && (currentTask.project_manager_name || currentTask.project_manager_user_public_id) || window.CRM.i18n.t('js.br1.ne_naznachen_4', 'Не назначен')) + '</div>'
      + '<form class="row g-2 d-none" data-task-manage-form="assignment">'
      + window.CRM.i18n.t('js.br1.div_class_col_6_label_class_form_label_ispolnitel_label', '<div class="col-6"><label class="form-label">Исполнитель</label><select class="form-select" name="assignee_user_public_id">') + assigneeOptions + '</select></div>'
      + window.CRM.i18n.t('js.br1.div_class_col_6_label_class_form_label_menedzher_proekt', '<div class="col-6"><label class="form-label">Менеджер проекта</label><select class="form-select" name="manager_user_public_id">') + managerOptions + '</select></div>'
      + window.CRM.i18n.t('js.br1.div_class_col_12_small_class_text_muted_menedzher_nazna', '<div class="col-12"><small class="text-muted">Менеджер назначается на выбранный проект. Если у задачи нет проекта, сначала выберите проект в блоке «Проект».</small></div>')
      + window.CRM.i18n.t('js.br1.div_class_col_12_d_flex_gap_2_button_type_submit_class_3', '<div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-sm crm-btn-primary">Сохранить</button><button type="button" class="btn btn-sm crm-btn-secondary" data-task-edit-cancel="assignment">Отмена</button></div>')
      + '</form>'
      + '</article>'
      + '</div>'
      + '<div class="col-lg-6">'
      + '<article class="border rounded-3 p-3 h-100">'
      + window.CRM.i18n.t('js.br1.div_class_d_flex_justify_content_between_align_items_ce_5', '<div class="d-flex justify-content-between align-items-center mb-2"><h4 class="h6 mb-0">Проект</h4><button type="button" class="btn btn-sm crm-btn-secondary" data-task-edit-toggle="project"') + (canEditProject ? '' : ' disabled') + ' aria-label="Edit"><i class="fa-solid fa-pen" aria-hidden="true"></i></button></div>'
      + window.CRM.i18n.t('js.br1.div_class_small_text_muted_mb_1_tekushchiy_proekt_div_d', '<div class="small text-muted mb-1">Текущий проект</div><div class="mb-2">') + escapeHtml(currentProjectTitle) + '</div>'
      + '<form class="row g-2 d-none" data-task-manage-form="project">'
      + window.CRM.i18n.t('js.br1.div_class_col_12_label_class_form_label_proekt_label_se', '<div class="col-12"><label class="form-label">Проект</label><select class="form-select" name="project_public_id">') + projectOptions + '</select></div>'
      + window.CRM.i18n.t('js.br1.div_class_col_12_d_flex_gap_2_button_type_submit_class_4', '<div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-sm crm-btn-primary">Сохранить</button><button type="button" class="btn btn-sm crm-btn-secondary" data-task-edit-cancel="project">Отмена</button></div>')
      + '</form>'
      + '</article>'
      + '</div>'
      + '<div class="col-lg-6">'
      + '<article class="border rounded-3 p-3 h-100">'
      + window.CRM.i18n.t('js.br1.div_class_d_flex_justify_content_between_align_items_ce_6', '<div class="d-flex justify-content-between align-items-center mb-2"><h4 class="h6 mb-0">Теги</h4><button type="button" class="btn btn-sm crm-btn-secondary" data-task-edit-toggle="tags"') + (canEditTags ? '' : ' disabled') + ' aria-label="Edit"><i class="fa-solid fa-pen" aria-hidden="true"></i></button></div>'
      + window.CRM.i18n.t('js.br1.div_class_small_text_muted_mb_1_naznachennye_tegi_div_d', '<div class="small text-muted mb-1">Назначенные теги</div><div class="mb-2">') + currentTagTitles + '</div>'
      + '<form class="row g-2 d-none" data-task-manage-form="tags">'
      + window.CRM.i18n.t('js.br1.div_class_col_12_label_class_form_label_tegi_label_sele', '<div class="col-12"><label class="form-label">Теги</label><select class="form-select" name="tag_public_ids" multiple size="5">') + tagOptions + '</select></div>'
      + window.CRM.i18n.t('js.br1.div_class_col_12_d_flex_gap_2_button_type_submit_class_5', '<div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-sm crm-btn-primary">Сохранить</button><button type="button" class="btn btn-sm crm-btn-secondary" data-task-edit-cancel="tags">Отмена</button></div>')
      + '</form>'
      + '</article>'
      + '</div>'
      + '</div>'
      + '</div>';

    refreshVisualEditors(panel, true);
  }

  function renderTaskComments(items) {
    var list = document.getElementById('commentsList');
    if (!list) return;
    setTaskTabCounter('detailCommentsCounter', Array.isArray(items) ? items.length : 0);

    // The compose-form checkbox only exists in an internal viewer's DOM
    // (task_detail.php gates it behind is_external_user, same pattern used
    // everywhere else on this page) — reuse its presence as the "am I an
    // internal viewer" signal instead of threading a separate flag through.
    var isInternalViewer = Boolean(document.getElementById('commentVisibilityClient'));

    list.innerHTML = items.length ? items.map(function (item) {
      var canEditComment = currentUserPublicId !== ''
        && String(item.author_public_id || '') === currentUserPublicId;
      var editButton = canEditComment
        ? '<button type="button" class="btn btn-sm crm-btn-secondary" data-comment-edit="' + escapeHtml(item.public_id || '') + window.CRM.i18n.t('js.br1.redaktirovat_button', '">Редактировать</button>')
        : '';
      // Deleting a comment goes through DELETE /api/v1/comments/{public_id}.
      // The route is open to any authenticated user; the server only allows
      // the author (or root / task participants) via CommentService. The
      // author therefore gets the button even without task.manage.
      var deleteButton = (canEditComment || hasPermission('task.manage'))
        ? '<button type="button" class="btn btn-sm crm-btn-secondary" data-comment-delete="' + escapeHtml(item.public_id || '') + window.CRM.i18n.t('js.br1.udalit_kommentariy_button', '">Удалить</button>')
        : '';
      var itemVisibility = String(item.visibility || 'internal');
      // Only an internal viewer sees/toggles this — a guest only ever receives
      // visibility=client comments in the first place (CommentService filters
      // server-side), so the badge/toggle would be redundant noise for them.
      var visibilityBadge = '';
      var toggleVisibilityButton = '';
      if (isInternalViewer) {
        visibilityBadge = itemVisibility === 'client'
          ? '<span class="crm-badge crm-badge-info" title="' + escapeHtml(window.CRM.i18n.t('js.br1.comment_visibility_client_hint', 'Виден приглашённому пользователю (клиенту/фрилансеру), если такой привязан к задаче')) + '">' + escapeHtml(window.CRM.i18n.t('js.br1.comment_visibility_client_badge', 'Видно приглашённому')) + '</span>'
          : '<span class="crm-badge crm-badge-muted" title="' + escapeHtml(window.CRM.i18n.t('js.br1.comment_visibility_internal_hint', 'Виден только сотрудникам компании')) + '">' + escapeHtml(window.CRM.i18n.t('js.br1.comment_visibility_internal_badge', 'Внутренний')) + '</span>';
        if (canEditComment || hasPermission('task.manage')) {
          toggleVisibilityButton = '<button type="button" class="btn btn-sm crm-btn-secondary" data-comment-toggle-visibility="' + escapeHtml(item.public_id || '') + '" data-current-visibility="' + escapeHtml(itemVisibility) + '">'
            + escapeHtml(itemVisibility === 'client'
              ? window.CRM.i18n.t('js.br1.comment_make_internal_btn', 'Скрыть от приглашённого')
              : window.CRM.i18n.t('js.br1.comment_make_client_btn', 'Показать приглашённому'))
            + '</button>';
        }
      }
      var commentActionsHtml = (editButton || deleteButton || toggleVisibilityButton)
        ? '<div class="d-flex gap-2 flex-wrap">' + editButton + deleteButton + toggleVisibilityButton + '</div>'
        : '';
      var commentId = String(item.public_id || '');
      var ownReaction = currentTaskOwnReactionsByComment[commentId] || null;
      var reactionLabel = ownReaction && ownReaction.reaction
        ? (window.CRM.i18n.t('js.br1.moya_reaktsiya', 'Моя реакция: ') + String(ownReaction.reaction))
        : window.CRM.i18n.t('js.br1.bez_reaktsii', 'Без реакции');

      return '<div class="crm-comment mb-2" data-comment-id="' + escapeHtml(item.public_id || '') + '" data-comment-author="' + escapeHtml(item.author_public_id || '') + '" data-comment-raw="' + escapeHtml(item.body || '') + '">'
        + '<div class="d-flex justify-content-between align-items-start gap-2">'
        + '<div><strong>' + escapeHtml(item.author_name || item.author_login || window.CRM.i18n.t('js.br1.polzovatel_3', 'Пользователь')) + '</strong>' + (visibilityBadge ? ' ' + visibilityBadge : '') + '</div>'
        + commentActionsHtml
        + '</div>'
        + '<div class="mb-1" data-comment-body="' + escapeHtml(item.public_id || '') + '">' + renderRichTextOrPlain(item.body || '') + '</div>'
        + '<div class="d-flex gap-2 flex-wrap align-items-center mb-1">'
        + '<button type="button" class="btn btn-sm crm-btn-secondary crm-btn-compact" data-comment-react="' + escapeHtml(commentId) + '" data-reaction="like" aria-label="Like" title="Like"><i class="fa-solid fa-thumbs-up" aria-hidden="true"></i></button>'
        + '<button type="button" class="btn btn-sm crm-btn-secondary crm-btn-compact" data-comment-react="' + escapeHtml(commentId) + '" data-reaction="love" aria-label="Love" title="Love"><i class="fa-regular fa-heart" aria-hidden="true"></i></button>'
        + '<button type="button" class="btn btn-sm crm-btn-secondary crm-btn-compact" data-comment-react="' + escapeHtml(commentId) + '" data-reaction="up" aria-label="Upvote" title="Upvote"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>'
        + '<button type="button" class="btn btn-sm crm-btn-secondary crm-btn-compact" data-comment-reaction-clear="' + escapeHtml(commentId) + '"' + (ownReaction ? '' : ' disabled') + window.CRM.i18n.t('js.br1.snyat_button', '>Снять</button>')
        + '<small class="text-muted">' + escapeHtml(reactionLabel) + '</small>'
        + '</div>'
        + '<small class="text-muted">' + escapeHtml(formatDate(item.created_at)) + '</small>'
        + '</div>';
    }).join('') : window.CRM.i18n.t('js.br1.div_class_crm_empty_h3_class_h6_kommentariev_poka_net_h', '<div class="crm-empty"><h3 class="h6">Комментариев пока нет</h3><p class="text-muted mb-0">Добавьте первый комментарий к задаче.</p></div>');
    if (window.CRM.VisualEditor && typeof window.CRM.VisualEditor.renderReadonly === 'function') {
      window.CRM.VisualEditor.renderReadonly(list);
    }
    applyCommentCollapse(list);
  }

  // Long comments are collapsed to COMMENT_COLLAPSE_MAX_HEIGHT with a toggle
  // button (Jira-style expand block) so the feed does not grow endlessly.
  function applyCommentCollapse(list) {
    if (!list) return;
    var expandLabel = window.CRM.i18n.t('js.br1.razvernut_kommentariy', 'Развернуть');
    var collapseLabel = window.CRM.i18n.t('js.br1.svernut_kommentariy', 'Свернуть');
    var comments = list.querySelectorAll('.crm-comment');
    for (var ci = 0; ci < comments.length; ci += 1) {
      var card = comments[ci];
      var body = card.querySelector('[data-comment-body]');
      if (!body) continue;
      if (body.scrollHeight <= COMMENT_COLLAPSE_MAX_HEIGHT) continue;

      var publicId = String(card.getAttribute('data-comment-id') || '');
      var wrap = document.createElement('div');
      wrap.className = 'crm-comment-body-wrap';
      body.parentNode.insertBefore(wrap, body);
      wrap.appendChild(body);

      // Fade sits inside the clipped body so it is hidden while editing too.
      var fade = document.createElement('div');
      fade.className = 'crm-comment-collapse-fade';
      body.appendChild(fade);

      var toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'crm-comment-collapse-toggle';
      wrap.appendChild(toggle);

      function setExpanded(expanded) {
        wrap.classList.toggle('crm-comment-expanded', expanded);
        toggle.textContent = expanded ? collapseLabel : expandLabel;
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        if (expanded) {
          expandedCommentIds[publicId] = true;
        } else {
          delete expandedCommentIds[publicId];
        }
      }
      setExpanded(Boolean(expandedCommentIds[publicId]));
      toggle.addEventListener('click', function () {
        setExpanded(!wrap.classList.contains('crm-comment-expanded'));
      });
    }
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
      followBtn.textContent = currentTaskFollowSubscription ? window.CRM.i18n.t('js.br1.ne_otslezhivat_zadachu', 'Не отслеживать задачу') : window.CRM.i18n.t('js.br1.otslezhivat_zadachu', 'Отслеживать задачу');
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
      favoriteBtn.textContent = currentTaskFavorite ? window.CRM.i18n.t('js.br1.ubrat_iz_izbrannogo', 'Убрать из избранного') : window.CRM.i18n.t('js.br1.v_izbrannoe', 'В избранное');
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
        || window.CRM.i18n.t('js.br1.fayl', 'Файл')
      );
      return '<div class="crm-file-item mb-2 d-flex justify-content-between align-items-center">'
        + '<div><strong>' + escapeHtml(displayName) + '</strong><div class="small text-muted">'
        + escapeHtml(formatDate(file.created_at || new Date().toISOString())) + '</div></div>'
        + '<button type="button" class="btn btn-sm crm-btn-secondary" data-file-download="' + escapeHtml(String(file.public_id || '')) + '" data-file-name="' + escapeHtml(displayName) + window.CRM.i18n.t('js.br1.skachat_button', '">Скачать</button>')
        + '</div>';
    }).join('') : window.CRM.i18n.t('js.br1.div_class_text_muted_fayly_k_zadache_poka_ne_zagruzheny', '<div class="text-muted">Файлы к задаче пока не загружены.</div>');
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

  function subtaskStatusControl(item, canChangeStatus) {
    var currentCode = String((item && item.status_code) || 'new');
    var color = statusColor(currentCode);
    var colorStyle = color
      ? ' style="background-color:' + escapeHtml(color) + ';border-color:' + escapeHtml(color) + ';color:' + escapeHtml(statusTextColor(color)) + '"'
      : '';
    var options = orderedTaskStatuses(currentCode).map(function (status) {
      var code = String(status.code || '');
      var selected = code === currentCode;
      return '<li><button class="dropdown-item' + (selected ? ' active' : '') + '" type="button" data-subtask-status-option="' + escapeHtml(code) + '"' + (selected ? ' disabled aria-current="true"' : '') + '>' + escapeHtml(status.title || code) + '</button></li>';
    }).join('');

    return '<div class="dropdown crm-subtask-status-dropdown">'
      + '<button class="crm-badge dropdown-toggle ' + statusBadgeClass(currentCode) + '" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Статус подзадачи"' + colorStyle + (canChangeStatus ? '' : ' disabled aria-disabled="true"') + '>' + escapeHtml(statusLabel(currentCode)) + '</button>'
      + '<ul class="dropdown-menu crm-task-status-menu">' + options + '</ul>'
      + '</div>';
  }

  function subtaskPriorityOptions(selectedPriority) {
    var priorities = [
      { code: 'low', title: window.CRM.i18n.t('js.br1.nizkiy_2', 'Низкий') },
      { code: 'normal', title: window.CRM.i18n.t('js.br1.normalnyy_2', 'Нормальный') },
      { code: 'high', title: window.CRM.i18n.t('js.br1.vysokiy_2', 'Высокий') },
      { code: 'urgent', title: window.CRM.i18n.t('js.br1.srochnyy_2', 'Срочный') }
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
    var optionTitle = currentProjectTitle || (currentProjectId ? currentProjectId : window.CRM.i18n.t('js.br1.bez_proekta_5', 'Без проекта'));
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
    assigneeSelect.innerHTML = window.CRM.i18n.t('js.br1.option_value_ne_naznachen_option_6', '<option value="">Не назначен</option>') + availableUsers.map(function (user) {
      var label = String(user.full_name || user.login || user.public_id || '').trim();
      var value = String(user.public_id || '');
      var selected = selectedAssignee === value ? ' selected' : '';
      return '<option value="' + escapeHtml(value) + '"' + selected + '>' + escapeHtml(label || window.CRM.i18n.t('js.br1.polzovatel_4', 'Пользователь')) + '</option>';
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
    if (descInput) {
      descInput.value = String(subtask.description || '');
      refreshVisualEditors(formNode, true);
    }
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
      list.innerHTML = window.CRM.i18n.t('js.br1.div_class_text_muted_podzadach_poka_net_nazhmite_sozdat', '<div class="text-muted">Подзадач пока нет. Нажмите «Создать подзадачу», чтобы создать первую.</div>');
      return;
    }

    list.innerHTML = '<div class="table-responsive"><table class="table align-middle crm-subtasks-table mb-0">'
      + '<thead><tr>'
      + window.CRM.i18n.t('js.br1.th_podzadacha_th', '<th>Подзадача</th>')
      + window.CRM.i18n.t('js.br1.th_dedlayn_th', '<th>Дедлайн</th>')
      + window.CRM.i18n.t('js.br1.th_status_th', '<th>Статус</th>')
      + window.CRM.i18n.t('js.br1.th_prioritet_th', '<th>Приоритет</th>')
      + window.CRM.i18n.t('js.br1.th_class_text_end_deystviya_th', '<th class="text-end">Действия</th>')
      + '</tr></thead><tbody>'
      + items.map(function (item) {
        var subtaskId = String(item.public_id || '');
        var dueLabel = item.due_at ? formatDate(item.due_at) : window.CRM.i18n.t('js.br1.bez_dedlayna', 'Без дедлайна');
        var canEditSubtask = canCreateTask && isSubtaskAuthor(item);
        var authorLabel = resolveUserDisplayName(item.creator_name || '', item.creator_user_public_id || '', window.CRM.i18n.t('js.br1.ne_ukazan_2', 'Не указан'));
        return '<tr data-subtask-id="' + escapeHtml(subtaskId) + '">'
          + '<td>'
          + '<a class="crm-subtask-link fw-semibold" href="index.php?route=task-detail&task_public_id=' + encodeURIComponent(subtaskId) + '">'
          + escapeHtml(item.title || window.CRM.i18n.t('js.br1.bez_nazvaniya', 'Без названия'))
          + '</a>'
          + window.CRM.i18n.t('js.br1.div_class_small_text_muted_mt_1_avtor', '<div class="small text-muted mt-1">Автор: ') + escapeHtml(authorLabel) + '</div>'
          + '</td>'
          + '<td>' + escapeHtml(dueLabel) + '</td>'
          + '<td class="crm-subtask-status-cell">'
          + subtaskStatusControl(item, canWorkTask)
          + '</td>'
          + '<td><span class="crm-chip">' + escapeHtml(priorityLabel(item.priority_code || 'normal')) + '</span></td>'
          + '<td class="text-end">'
          + '<div class="d-inline-flex gap-2">'
          + '<a class="btn btn-sm crm-btn-subtle crm-btn-compact" href="index.php?route=task-detail&task_public_id=' + encodeURIComponent(subtaskId) + window.CRM.i18n.t('js.br1.otkryt_a', '">Открыть</a>')
          + '<button type="button" class="btn btn-sm crm-btn-secondary crm-btn-compact" data-subtask-edit="' + escapeHtml(subtaskId) + '"' + (canEditSubtask ? '' : ' disabled') + window.CRM.i18n.t('js.br1.redaktirovat_button_2', '>Редактировать</button>')
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
      + '<div class="crm-checklist-title">' + escapeHtml(checklist.title || window.CRM.i18n.t('js.br1.bez_nazvaniya_2', 'Без названия')) + '</div>'
      + '<div class="crm-checklist-progress-line">'
      + '<span class="crm-checklist-progress-copy">' + escapeHtml(String(progress.done)) + window.CRM.i18n.t('js.br1.iz', ' из ') + escapeHtml(String(progress.total)) + window.CRM.i18n.t('js.br1.vypolneno_span', ' выполнено</span>')
      + window.CRM.i18n.t('js.br1.div_class_crm_checklist_progress_bar_role_progressbar_a', '<div class="crm-checklist-progress-bar" role="progressbar" aria-label="Прогресс чеклиста"><span style="width:') + escapeHtml(String(progress.percent)) + '%"></span></div>'
      + '<span class="crm-checklist-progress-percent">' + escapeHtml(String(progress.percent)) + '%</span>'
      + '</div>'
      + '</div>'
      + '<div class="crm-checklist-head-actions">'
      + '<button class="btn btn-sm crm-btn-secondary crm-btn-compact" type="button" data-checklist-edit="' + escapeHtml(checklistId) + '"' + (canEditTask ? '' : ' disabled') + window.CRM.i18n.t('js.br1.redaktirovat_button_3', '>Редактировать</button>')
      + '<button class="btn btn-sm crm-btn-secondary crm-btn-compact" type="button" data-checklist-more="' + escapeHtml(checklistId) + '"' + (canEditTask ? '' : ' disabled') + '>...</button>'
      + '</div>'
      + '</header>'
      + '<ul class="crm-checklist-view-items">'
      + (checklistItems.length ? checklistItems.map(function (item) {
        var done = Number(item && item.is_done || 0) === 1;
        return '<li class="crm-checklist-view-item' + (done ? ' is-done' : '') + '">'
          + '<label class="crm-checklist-view-label">'
          + '<input class="form-check-input mt-0" type="checkbox" data-checklist-item-toggle="' + escapeHtml(String(item && item.public_id || '')) + '"' + (done ? ' checked' : '') + (canEditTask ? '' : ' disabled') + '>'
          + '<span class="crm-checklist-view-title">' + escapeHtml(item && item.title || window.CRM.i18n.t('js.br1.bez_nazvaniya_3', 'Без названия')) + '</span>'
          + '</label>'
          + '<span class="crm-checklist-view-status">' + (done ? window.CRM.i18n.t('js.br1.vypolneno', 'Выполнено') : window.CRM.i18n.t('js.br1.ne_vypolneno', 'Не выполнено')) + '</span>'
          + '</li>';
      }).join('') : window.CRM.i18n.t('js.br1.li_class_crm_checklist_empty_punktov_poka_net_li', '<li class="crm-checklist-empty">Пунктов пока нет.</li>'))
      + '</ul>'
      + (canEditTask
        ? '<div class="crm-checklist-view-add">'
          + (canAdd
            ? '<form class="d-flex gap-2" data-checklist-item-create-view="' + escapeHtml(checklistId) + '">'
              + window.CRM.i18n.t('js.br1.input_class_form_control_form_control_sm_name_title_max', '<input class="form-control form-control-sm" name="title" maxlength="255" placeholder="Новый пункт чеклиста" required>')
              + window.CRM.i18n.t('js.br1.button_class_btn_btn_sm_crm_btn_primary_crm_btn_compact', '<button class="btn btn-sm crm-btn-primary crm-btn-compact" type="submit">Добавить</button>')
              + '<button class="btn btn-sm crm-btn-secondary crm-btn-compact" type="button" data-checklist-item-create-cancel="' + escapeHtml(checklistId) + window.CRM.i18n.t('js.br1.otmena_button', '">Отмена</button>')
              + '</form>'
            : '<button class="btn btn-sm btn-link p-0 text-decoration-none" type="button" data-checklist-item-create-toggle="' + escapeHtml(checklistId) + window.CRM.i18n.t('js.br1.dobavit_punkt_button', '">+ Добавить пункт</button>'))
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
      + '<span class="crm-checklist-progress-copy">' + escapeHtml(String(progress.done)) + window.CRM.i18n.t('js.br1.iz_2', ' из ') + escapeHtml(String(progress.total)) + window.CRM.i18n.t('js.br1.vypolneno_span_2', ' выполнено</span>')
      + window.CRM.i18n.t('js.br1.div_class_crm_checklist_progress_bar_role_progressbar_a_2', '<div class="crm-checklist-progress-bar" role="progressbar" aria-label="Прогресс чеклиста"><span style="width:') + escapeHtml(String(progress.percent)) + '%"></span></div>'
      + '<span class="crm-checklist-progress-percent">' + escapeHtml(String(progress.percent)) + '%</span>'
      + '</div>'
      + '</div>'
      + '<div class="crm-checklist-head-actions">'
      + '<button class="btn btn-sm crm-btn-secondary crm-btn-compact" type="button" data-checklist-edit-cancel="' + escapeHtml(checklistId) + '"' + (canEditTask ? '' : ' disabled') + window.CRM.i18n.t('js.br1.otmena_button_2', '>Отмена</button>')
      + '<button class="btn btn-sm crm-btn-primary crm-btn-compact" type="submit"' + (canEditTask ? '' : ' disabled') + window.CRM.i18n.t('js.br1.sokhranit_button', '>Сохранить</button>')
      + '</div>'
      + '</header>'
      + '<div class="crm-checklist-edit-items">'
      + (draftItems.length ? draftItems.map(function (item, index) {
        return '<div class="crm-checklist-edit-item">'
          + '<span class="crm-checklist-drag-handle" aria-hidden="true">⋮⋮</span>'
          + '<input class="form-check-input mt-0" type="checkbox" data-checklist-draft-done="' + escapeHtml(String(item.public_id || '')) + '"' + (Number(item.is_done || 0) === 1 ? ' checked' : '') + (canEditTask ? '' : ' disabled') + '>'
          + '<input class="form-control form-control-sm" data-checklist-draft-title="' + escapeHtml(String(item.public_id || '')) + '" maxlength="255" value="' + escapeHtml(item.title || '') + '"' + (canEditTask ? '' : ' disabled') + '>'
          + '<span class="crm-checklist-order-meta small text-muted" aria-hidden="true">#' + escapeHtml(String(index + 1)) + '</span>'
          + window.CRM.i18n.t('js.br1.button_class_btn_btn_sm_crm_btn_danger_icon_type_button', '<button class="btn btn-sm crm-btn-danger-icon" type="button" aria-label="Удалить пункт чеклиста" data-checklist-draft-delete="') + escapeHtml(String(item.public_id || '')) + '"' + (canEditTask ? '' : ' disabled') + '><span class="crm-icon" aria-hidden="true"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></span></button>'
          + '</div>';
      }).join('') : window.CRM.i18n.t('js.br1.div_class_crm_checklist_empty_punktov_poka_net_div', '<div class="crm-checklist-empty">Пунктов пока нет.</div>'))
      + '</div>'
      + '<div class="crm-checklist-edit-add">'
      + '<button class="btn btn-sm btn-link p-0 text-decoration-none" type="button" data-checklist-draft-add-item="' + escapeHtml(checklistId) + '"' + (canEditTask ? '' : ' disabled') + window.CRM.i18n.t('js.br1.dobavit_punkt_button_2', '>+ Добавить пункт</button>')
      + '</div>'
      + '<div class="crm-checklist-edit-danger">'
      + '<button class="btn btn-sm crm-btn-danger crm-btn-compact" type="button" data-checklist-delete="' + escapeHtml(checklistId) + '"' + (canEditTask ? '' : ' disabled') + window.CRM.i18n.t('js.br1.udalit_cheklist_button', '>Удалить чеклист</button>')
      + '</div>'
      + '</form>'
      + '</article>';
  }

  function renderChecklists(items, canEditTask) {
    var list = document.getElementById('checklistsList');
    if (!list) return;
    setTaskTabCounter('detailChecklistsCounter', Array.isArray(items) ? items.length : 0);

    if (!items.length) {
      list.innerHTML = window.CRM.i18n.t('js.br1.div_class_text_muted_cheklistov_poka_net_dobavte_pervyy', '<div class="text-muted">Чеклистов пока нет. Добавьте первый чеклист выше.</div>');
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
        if (joined.indexOf('delete') >= 0) return window.CRM.i18n.t('js.br1.udalen_kommentariy', 'Удален комментарий');
        if (joined.indexOf('update') >= 0 || joined.indexOf('edit') >= 0 || joined.indexOf('patch') >= 0) return window.CRM.i18n.t('js.br1.izmenen_kommentariy', 'Изменен комментарий');
        return window.CRM.i18n.t('js.br1.dobavlen_kommentariy', 'Добавлен комментарий');
      }
      if (joined.indexOf('status') >= 0) return window.CRM.i18n.t('js.br1.izmenen_status_zadachi', 'Изменен статус задачи');
      if (joined.indexOf('assignee') >= 0) return window.CRM.i18n.t('js.br1.izmenen_ispolnitel', 'Изменен исполнитель');
      if (joined.indexOf('manager') >= 0) return window.CRM.i18n.t('js.br1.izmenen_menedzher_proekta', 'Изменен менеджер проекта');
      if (joined.indexOf('project') >= 0) return window.CRM.i18n.t('js.br1.izmenen_svyazannyy_proekt', 'Изменен связанный проект');
      if (joined.indexOf('tag') >= 0) return window.CRM.i18n.t('js.br1.obnovleny_tegi_zadachi', 'Обновлены теги задачи');
      if (joined.indexOf('worklog') >= 0 || joined.indexOf('time') >= 0) return window.CRM.i18n.t('js.br1.izmenen_uchet_vremeni', 'Изменен учет времени');
      if (joined.indexOf('file') >= 0 || joined.indexOf('attachment') >= 0 || joined.indexOf('upload') >= 0) return window.CRM.i18n.t('js.br1.izmeneny_fayly_zadachi', 'Изменены файлы задачи');
      if (joined.indexOf('subtask') >= 0) return window.CRM.i18n.t('js.br1.izmeneny_podzadachi', 'Изменены подзадачи');
      if (joined.indexOf('checklist') >= 0) return window.CRM.i18n.t('js.br1.izmenen_cheklist', 'Изменен чеклист');
      if (joined.indexOf('create') >= 0 && joined.indexOf('task') >= 0) return window.CRM.i18n.t('js.br1.sozdana_zadacha', 'Создана задача');
      if (joined.indexOf('update') >= 0 || joined.indexOf('patch') >= 0 || joined.indexOf('put') >= 0) return window.CRM.i18n.t('js.br1.obnovleny_parametry_zadachi', 'Обновлены параметры задачи');
      if (joined.indexOf('delete') >= 0) return window.CRM.i18n.t('js.br1.vypolneno_udalenie_po_zadache', 'Выполнено удаление по задаче');
      return window.CRM.i18n.t('js.br1.sobytie_po_zadache', 'Событие по задаче');
    }

    function activityReadableDetail(item) {
      if (!item || typeof item !== 'object') return '';
      var payload = item.payload && typeof item.payload === 'object' ? item.payload : null;
      if (payload && payload.reason) {
        return window.CRM.i18n.t('js.br1.prichina_smeny_statusa', 'Причина: ') + String(payload.reason);
      }
      if (item.field_name && (item.old_label || item.new_label)) {
        return window.CRM.i18n.t('js.br1.iz_4', 'Из "') + String(item.old_label || '—') + window.CRM.i18n.t('js.br1.v_2', '" в "') + String(item.new_label || '—') + '"';
      }
      if (item.body) return String(item.body);
      if (item.note) return String(item.note);
      if (item.message) return String(item.message);
      if (item.details && typeof item.details === 'object') {
        var d = item.details;
        if (d.comment_body) return String(d.comment_body);
        if (d.diff && typeof d.diff === 'string') return String(d.diff);
        if (d.from && d.to) return window.CRM.i18n.t('js.br1.iz_3', 'Из "') + String(d.from) + window.CRM.i18n.t('js.br1.v', '" в "') + String(d.to) + '"';
        if (d.status_from || d.status_to) {
          return window.CRM.i18n.t('js.br1.iz_4', 'Из "') + statusLabel(d.status_from || '') + window.CRM.i18n.t('js.br1.v_2', '" в "') + statusLabel(d.status_to || '') + '"';
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
    }).join('') : window.CRM.i18n.t('js.br1.div_class_crm_timeline_item_istoriya_izmeneniy_poka_pus', '<div class="crm-timeline-item">История изменений пока пуста.</div>');
  }

  async function loadTaskActivity(taskId) {
    try {
      var envelope = await window.CRM.api.request('api/v1/tasks/' + encodeURIComponent(taskId) + '/activity', {
        query: { limit: 50 }
      });
      renderTaskActivity(window.CRM.api.items(envelope));
    } catch (e) {
      renderTaskActivity([]);
    }
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
    function renderStatusMenu() {
      var menu = document.getElementById('taskStatusMenu');
      var statusBadge = document.getElementById('taskStatusBadge');
      if (!menu || !statusBadge) return;
      var options = orderedTaskStatuses(currentTask && currentTask.status_code ? currentTask.status_code : '');
      var currentCode = String(currentTask && currentTask.status_code || '');
      menu.innerHTML = options.map(function (item) {
        var code = String(item.code || '');
        var selected = currentCode === code;
        return '<li><button class="dropdown-item' + (selected ? ' active' : '') + '" type="button" data-task-status-option="' + escapeHtml(code) + '"' + (selected ? ' disabled aria-current="true"' : '') + '>' + escapeHtml(item.title || code) + '</button></li>';
      }).join('');
      var canChangeStatus = Boolean(currentTaskPermissions.canWorkItems);
      statusBadge.disabled = !canChangeStatus;
      statusBadge.setAttribute('aria-disabled', canChangeStatus ? 'false' : 'true');
    }

    async function updateTaskStatus(nextStatus, reasonText) {
      if (!currentTask) return;
      if (!currentTaskPermissions.canWorkItems) {
        notify(window.CRM.i18n.t('js.br1.izmenenie_statusa_dostupno_avtoru_ili_ispolnitelyu_zada', 'Изменение статуса доступно автору или исполнителю задачи'), 'warning');
        return;
      }
      var targetStatus = String(nextStatus || '').trim();
      if (!targetStatus) {
        notify(window.CRM.i18n.t('js.br1.vyberite_status', 'Выберите статус'), 'warning');
        return;
      }
      // Причина смены статуса опциональна (см. ТЗ 3.6): заполнение не блокирует перевод.
      var statusReason = String(reasonText || '').trim();
      var oldStatusCode = String(currentTask.status_code || '');
      var patchBody = {
        status: targetStatus,
        row_version: currentTask.row_version
      };
      if (statusReason) {
        patchBody.status_reason = statusReason;
      }
      try {
        var envelope = await window.CRM.api.request('api/v1/tasks/' + taskId, {
          method: 'PATCH',
          body: patchBody
        });

        currentTask = mergeTaskState(extractTaskPayload(envelope));
        renderTaskStatus(currentTask.status_code || targetStatus);
        renderTaskMetaChips();
        renderTaskProgressByStatus(currentTask.status_code || targetStatus);
        renderTaskRiskBanner();
        renderStatusMenu();
        await loadTaskActivity(taskId);
        notify(window.CRM.i18n.t('js.br1.status_zadachi_obnovlen', 'Статус задачи обновлен'));
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_obnovit_status', 'Не удалось обновить статус'), 'error');
      }
    }

    renderStatusMenu();
    var statusChips = document.getElementById('taskMetaChips');
    var reasonForm = document.getElementById('taskStatusReasonForm');
    var reasonInput = document.getElementById('taskStatusReasonInput');
    var reasonTarget = document.getElementById('taskStatusReasonTarget');
    var reasonCancelBtn = document.getElementById('taskStatusReasonCancelBtn');
    var pendingStatus = '';

    function closeReasonForm(resetSelection) {
      if (reasonForm) reasonForm.classList.add('d-none');
      if (reasonInput) reasonInput.value = '';
      pendingStatus = '';
    }

    if (statusChips && statusChips.dataset.statusMenuBound !== '1') {
      statusChips.addEventListener('click', function (event) {
        var option = event.target.closest('[data-task-status-option]');
        if (!option || option.disabled) return;
        if (!currentTaskPermissions.canWorkItems) {
          notify(window.CRM.i18n.t('js.br1.izmenenie_statusa_dostupno_avtoru_ili_ispolnitelyu_zada_2', 'Изменение статуса доступно автору или исполнителю задачи'), 'warning');
          return;
        }
        pendingStatus = String(option.getAttribute('data-task-status-option') || '').trim();
        if (!pendingStatus || (currentTask && String(currentTask.status_code || '') === pendingStatus)) return;
        if (reasonTarget) {
          reasonTarget.textContent = '"' + statusLabel(currentTask ? currentTask.status_code : '') + '" → "' + statusLabel(pendingStatus) + '"';
        }
        if (reasonForm) reasonForm.classList.remove('d-none');
        if (reasonInput) reasonInput.focus();
      });
      statusChips.dataset.statusMenuBound = '1';
    }

    if (reasonForm && reasonForm.dataset.bound !== '1') {
      reasonForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!pendingStatus) {
          notify(window.CRM.i18n.t('js.br1.snachala_vyberite_novyy_status', 'Сначала выберите новый статус'), 'warning');
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
    // Absent entirely from a guest's DOM (task_detail.php gates it on
    // is_external_user) — a guest's own comments are always forced
    // visibility=client server-side (CommentService::createByTask), so the
    // checkbox would be meaningless for them anyway.
    var visibilityCheckbox = document.getElementById('commentVisibilityClient');

    function renderMentionOptions() {
      if (!mentionSelect) return;
      mentionSelect.textContent = '';
      var base = document.createElement('option');
      base.value = '';
      base.textContent = window.CRM.i18n.t('js.br1.bez_upominaniya', 'Без упоминания');
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
            notify(window.CRM.i18n.t('js.br1.otslezhivanie_zadachi_otklyucheno', 'Отслеживание задачи отключено'));
          } else {
            await window.CRM.api.request('api/v1/subscriptions', {
              method: 'POST',
              body: {
                entity_type: 'task',
                entity_public_id: taskId
              }
            });
            notify(window.CRM.i18n.t('js.br1.otslezhivanie_zadachi_vklyucheno', 'Отслеживание задачи включено'));
          }
          await loadTaskCollaborationState(taskId);
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_izmenit_podpisku', 'Не удалось изменить подписку'), 'error');
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
            notify(window.CRM.i18n.t('js.br1.ubrano_iz_izbrannogo', 'Убрано из избранного'));
          } else {
            await window.CRM.api.request('api/v1/favorites', {
              method: 'POST',
              body: {
                entity_type: 'task',
                entity_public_id: taskId
              }
            });
            notify(window.CRM.i18n.t('js.br1.dobavleno_v_izbrannoe', 'Добавлено в избранное'));
          }
          await loadTaskCollaborationState(taskId);
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_izmenit_izbrannoe', 'Не удалось изменить избранное'), 'error');
        }
      });
    }

    if (textArea) {
      textArea.addEventListener('blur', async function () {
        var value = getVisualEditorTextareaValue(textArea).trim();
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

    var cancelBtn = form.querySelector('[data-comment-create-cancel]');

    function resetCommentMention() {
      if (!mentionSelect) return;
      mentionSelect.value = '';
      // #commentMentionUserSelect is converted to a searchable select by
      // applySearchableSelects(): the widget mirrors the value into a visible
      // input only on 'change', so dispatch it to keep the UI in sync.
      if (mentionSelect.dataset && mentionSelect.dataset.searchable === '1') {
        mentionSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }

    if (cancelBtn && cancelBtn.dataset.bound !== '1') {
      cancelBtn.dataset.bound = '1';
      cancelBtn.addEventListener('click', function () {
        if (textArea) {
          textArea.value = '';
          refreshVisualEditors(form, true);
        }
        resetCommentMention();
        if (visibilityCheckbox) visibilityCheckbox.checked = false;
        // Best-effort: drop the auto-saved draft so it does not resurrect on
        // the next page load after the user cancelled the comment. The blur
        // handler saves the draft (fire-and-forget) right before this click,
        // so delay the DELETE a little to let that POST settle first.
        window.setTimeout(function () {
          window.CRM.api.request('api/v1/tasks/' + taskId + '/comment-draft', {
            method: 'DELETE'
          }).catch(function () {});
        }, 300);
      });
    }

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      var text = textArea ? getVisualEditorTextareaValue(textArea).trim() : '';
      if (!text) {
        notify(window.CRM.i18n.t('js.br1.vvedite_tekst_kommentariya', 'Введите текст комментария'), 'warning');
        return;
      }

      try {
        var commentBody = { body: text };
        if (visibilityCheckbox && visibilityCheckbox.checked) {
          commentBody.visibility = 'client';
        }
        var createdCommentEnvelope = await window.CRM.api.request('api/v1/tasks/' + taskId + '/comments', {
          method: 'POST',
          body: commentBody
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

        if (textArea) {
          textArea.value = '';
          refreshVisualEditors(form, true);
        }
        resetCommentMention();
        if (visibilityCheckbox) visibilityCheckbox.checked = false;

        await window.CRM.api.request('api/v1/tasks/' + taskId + '/comment-draft', {
          method: 'DELETE'
        });

        await loadTaskCollaborationState(taskId);
        await loadTaskComments(taskId);
        notify(window.CRM.i18n.t('js.br1.kommentariy_sokhranen', 'Комментарий сохранен'));
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_sokhranit_kommentariy', 'Не удалось сохранить комментарий'), 'error');
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

      var currentBody = commentCard.getAttribute('data-comment-raw') || bodyEl.innerHTML || bodyEl.textContent || '';
      bodyEl.classList.add('d-none');
      commentCard.classList.add('crm-comment-editing');

      var editor = document.createElement('div');
      editor.setAttribute('data-comment-edit-form', '1');
      editor.className = 'crm-comment-edit-shell';
      editor.innerHTML = ''
        + '<textarea class="form-control" rows="3" data-comment-edit-text="' + commentPublicId + '" data-crm-visual-editor="1"></textarea>'
        + '<div class="crm-comment-edit-actions">'
        + '<button type="button" class="btn btn-sm crm-btn-primary crm-btn-compact" data-comment-save="' + commentPublicId + window.CRM.i18n.t('js.br1.sokhranit_button_2', '">Сохранить</button>')
        + '<button type="button" class="btn btn-sm crm-btn-secondary crm-btn-compact" data-comment-cancel="' + commentPublicId + window.CRM.i18n.t('js.br1.otmena_button_3', '">Отмена</button>')
        + '</div>';
      commentCard.appendChild(editor);

      var textArea = editor.querySelector('[data-comment-edit-text="' + commentPublicId + '"]');
      if (textArea) {
        textArea.value = currentBody.trim();
        window.setTimeout(function () { refreshVisualEditors(editor, true); }, 0);
        window.setTimeout(function () { refreshVisualEditors(editor, true); }, 120);
      }
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
          cancelCard.classList.remove('crm-comment-editing');
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
          notify(window.CRM.i18n.t('js.br1.reaktsiya_sokhranena', 'Реакция сохранена'));
        } catch (reactionError) {
          var reactionEnvelopeError = reactionError && reactionError.envelope ? reactionError.envelope : null;
          notify((reactionEnvelopeError && reactionEnvelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_sokhranit_reaktsiyu', 'Не удалось сохранить реакцию'), 'error');
        }
        return;
      }

      var clearReactionBtn = e.target.closest('[data-comment-reaction-clear]');
      if (clearReactionBtn) {
        var clearCommentId = String(clearReactionBtn.getAttribute('data-comment-reaction-clear') || '').trim();
        var ownReaction = currentTaskOwnReactionsByComment[clearCommentId] || null;
        if (!ownReaction || !ownReaction.public_id) {
          notify(window.CRM.i18n.t('js.br1.dlya_kommentariya_net_vashey_reaktsii', 'Для комментария нет вашей реакции'), 'warning');
          return;
        }
        try {
          await window.CRM.api.request('api/v1/reactions/' + encodeURIComponent(String(ownReaction.public_id)), {
            method: 'DELETE'
          });
          await loadTaskCollaborationState(taskId);
          await loadTaskComments(taskId);
          notify(window.CRM.i18n.t('js.br1.reaktsiya_udalena', 'Реакция удалена'));
        } catch (clearError) {
          var clearEnvelopeError = clearError && clearError.envelope ? clearError.envelope : null;
          notify((clearEnvelopeError && clearEnvelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_udalit_reaktsiyu', 'Не удалось удалить реакцию'), 'error');
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
        notify(window.CRM.i18n.t('js.br1.redaktirovat_mozhno_tolko_svoi_kommentarii', 'Редактировать можно только свои комментарии'), 'warning');
        return;
      }

      var editText = editorCard.querySelector('[data-comment-edit-text="' + commentPublicId + '"]');
      var body = editText ? getVisualEditorTextareaValue(editText).trim() : '';
      if (!body) {
        notify(window.CRM.i18n.t('js.br1.tekst_kommentariya_ne_mozhet_byt_pustym', 'Текст комментария не может быть пустым'), 'warning');
        return;
      }

      try {
        await window.CRM.api.request('api/v1/comments/' + commentPublicId, {
          method: 'PATCH',
          body: { body: body }
        });
        await loadTaskComments(taskId);
        notify(window.CRM.i18n.t('js.br1.kommentariy_obnovlen', 'Комментарий обновлен'));
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_obnovit_kommentariy', 'Не удалось обновить комментарий'), 'error');
      }
    });

    commentsList.addEventListener('click', async function (e) {
      var deleteBtn = e.target.closest('[data-comment-delete]');
      if (!deleteBtn) return;
      var deleteCommentId = String(deleteBtn.getAttribute('data-comment-delete') || '');
      if (!deleteCommentId) return;
      if (!window.confirm(window.CRM.i18n.t('js.br1.udalit_kommentariy_confirm', 'Удалить комментарий?'))) return;

      try {
        await window.CRM.api.request('api/v1/comments/' + encodeURIComponent(deleteCommentId), {
          method: 'DELETE'
        });
        await loadTaskComments(taskId);
        notify(window.CRM.i18n.t('js.br1.kommentariy_udalen', 'Комментарий удалён'));
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_udalit_kommentariy', 'Не удалось удалить комментарий'), 'error');
      }
    });

    // Lets internal staff flip an already-posted comment (typically a reply
    // to a guest) between internal-only and client-visible without reopening
    // the body editor. This is the direct fix for "invited users never see
    // replies from other users": a reply defaults to visibility=internal
    // (CommentService::createByTaskInternal) and, before this control
    // existed, there was no UI at all to change that after the fact.
    commentsList.addEventListener('click', async function (e) {
      var toggleBtn = e.target.closest('[data-comment-toggle-visibility]');
      if (!toggleBtn) return;
      var toggleCommentId = String(toggleBtn.getAttribute('data-comment-toggle-visibility') || '');
      if (!toggleCommentId) return;
      var nextVisibility = String(toggleBtn.getAttribute('data-current-visibility') || '') === 'client' ? 'internal' : 'client';

      try {
        await window.CRM.api.request('api/v1/comments/' + encodeURIComponent(toggleCommentId), {
          method: 'PATCH',
          body: { visibility: nextVisibility }
        });
        await loadTaskComments(taskId);
        notify(nextVisibility === 'client'
          ? window.CRM.i18n.t('js.br1.comment_now_client_visible', 'Комментарий теперь виден приглашённому пользователю')
          : window.CRM.i18n.t('js.br1.comment_now_internal', 'Комментарий теперь виден только сотрудникам'));
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_izmenit_vidimost_kommentariya', 'Не удалось изменить видимость комментария'), 'error');
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
        notify(window.CRM.i18n.t('js.br1.vyberite_fayl_dlya_zagruzki', 'Выберите файл для загрузки'), 'warning');
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
          notify(window.CRM.i18n.t('js.br1.fayl_zagruzhen', 'Файл загружен'));
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_zagruzit_fayl', 'Не удалось загрузить файл'), 'error');
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
            var errorMessage = window.CRM.i18n.t('js.br1.ne_udalos_skachat_fayl', 'Не удалось скачать файл');
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
          notify(window.CRM.i18n.t('js.br1.ne_udalos_skachat_fayl_2', 'Не удалось скачать файл'), 'error');
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
          notify(window.CRM.i18n.t('js.br1.vvedite_nazvanie_podzadachi', 'Введите название подзадачи'), 'warning');
          return;
        }

        var selectedTagIds = collectSelectedValues(createForm.querySelector('[name="tag_public_ids"]'));
        var submitBtn = createForm.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        try {
          var createEnvelope = await window.CRM.api.request('api/v1/tasks/' + taskId + '/subtasks', {
            method: 'POST',
            headers: {
              'X-Idempotency-Key': window.CRM.api.createIdempotencyKey('web-subtask')
            },
            body: {
              title: title,
              status: String((createForm.querySelector('[name="status"]') || {}).value || 'new'),
              priority: String((createForm.querySelector('[name="priority"]') || {}).value || 'normal'),
              description: getVisualEditorTextareaValue(createForm.querySelector('[name="description"]')).trim(),
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
          notify(window.CRM.i18n.t('js.br1.podzadacha_sozdana', 'Подзадача создана'));
          window.bootstrap.Modal.getOrCreateInstance(createModalEl).hide();
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_sozdat_podzadachu', 'Не удалось создать подзадачу'), 'error');
        } finally {
          if (submitBtn) submitBtn.disabled = false;
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
          notify(window.CRM.i18n.t('js.br1.redaktirovat_podzadachu_mozhet_tolko_ee_avtor', 'Редактировать подзадачу может только ее автор'), 'warning');
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
              description: getVisualEditorTextareaValue(editForm.querySelector('[name="description"]')).trim(),
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
          notify(window.CRM.i18n.t('js.br1.podzadacha_obnovlena', 'Подзадача обновлена'));
          window.bootstrap.Modal.getOrCreateInstance(editModalEl).hide();
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_obnovit_podzadachu', 'Не удалось обновить подзадачу'), 'error');
        }
      });
      editForm.dataset.bound = '1';
    }

    if (list.dataset.bound === '1') return;
    list.addEventListener('click', async function (e) {
      var statusOption = e.target.closest('[data-subtask-status-option]');
      if (!statusOption || statusOption.disabled) return;

      if (!canWorkTask) {
        return;
      }

      var subtaskRow = statusOption.closest('[data-subtask-id]');
      var subtaskPublicId = String((subtaskRow && subtaskRow.getAttribute('data-subtask-id')) || '').trim();
      if (!subtaskPublicId) return;
      try {
        await window.CRM.api.request('api/v1/subtasks/' + subtaskPublicId, {
          method: 'PATCH',
          body: {
            status: String(statusOption.getAttribute('data-subtask-status-option') || 'new')
          }
        });
        await loadSubtasks(taskId, canWorkTask, canCreateTask);
        notify(window.CRM.i18n.t('js.br1.status_podzadachi_obnovlen', 'Статус подзадачи обновлен'));
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_izmenit_status_podzadachi', 'Не удалось изменить статус подзадачи'), 'error');
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
        notify(window.CRM.i18n.t('js.br1.redaktirovat_podzadachu_mozhet_tolko_ee_avtor_2', 'Редактировать подзадачу может только ее автор'), 'warning');
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
          notify(window.CRM.i18n.t('js.br1.vvedite_nazvanie_cheklista', 'Введите название чеклиста'), 'warning');
          return;
        }
        try {
          await window.CRM.api.request('api/v1/tasks/' + taskId + '/checklists', {
            method: 'POST',
            body: { title: title }
          });
          createForm.reset();
          await loadChecklists(taskId, canEditTask);
          notify(window.CRM.i18n.t('js.br1.cheklist_sozdan', 'Чеклист создан'));
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_sozdat_cheklist', 'Не удалось создать чеклист'), 'error');
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
        notify(window.CRM.i18n.t('js.br1.nazvanie_cheklista_ne_mozhet_byt_pustym', 'Название чеклиста не может быть пустым'), 'warning');
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
          notify(window.CRM.i18n.t('js.br1.cheklist_sokhranen', 'Чеклист сохранен'));
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_sokhranit_cheklist', 'Не удалось сохранить чеклист'), 'error');
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
          notify(window.CRM.i18n.t('js.br1.vvedite_nazvanie_punkta', 'Введите название пункта'), 'warning');
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
          notify(window.CRM.i18n.t('js.br1.punkt_cheklista_dobavlen', 'Пункт чеклиста добавлен'));
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_dobavit_punkt', 'Не удалось добавить пункт'), 'error');
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
        notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_izmenit_status_punkta', 'Не удалось изменить статус пункта'), 'error');
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
          notify(window.CRM.i18n.t('js.br1.cheklist_udalen', 'Чеклист удален'));
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_udalit_cheklist', 'Не удалось удалить чеклист'), 'error');
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
    if (hours <= 0) return String(mins) + window.CRM.i18n.t('js.br1.min', ' мин');
    return String(hours) + window.CRM.i18n.t('js.br1.ch', ' ч ') + String(mins) + window.CRM.i18n.t('js.br1.min_2', ' мин');
  }

  function timerSecondsFromNote(note) {
    var match = String(note || '').match(/\[timer_seconds:(\d+)\]/i);
    if (!match) return 0;
    var seconds = Number(match[1]);
    return Number.isFinite(seconds) && seconds > 0 ? Math.floor(seconds) : 0;
  }

  function worklogDurationLabel(item) {
    var exactSeconds = timerSecondsFromNote(item && item.note);
    return exactSeconds > 0
      ? formatElapsedSeconds(exactSeconds)
      : formatMinutes(item && item.minutes_spent ? item.minutes_spent : 0);
  }

  function worklogVisibleNote(note) {
    return String(note || '').replace(/\[timer_seconds:\d+\]\s*/i, '').trim();
  }

  function formatWorklogEntriesLabel(count) {
    var normalized = Number(count || 0);
    var mod10 = normalized % 10;
    var mod100 = normalized % 100;
    if (mod10 === 1 && mod100 !== 11) return String(normalized) + window.CRM.i18n.t('js.br1.zapis', ' запись');
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return String(normalized) + window.CRM.i18n.t('js.br1.zapisi', ' записи');
    return String(normalized) + window.CRM.i18n.t('js.br1.zapisey', ' записей');
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
      // Migrate the legacy single-timer cookie {task_public_id, user_public_id,
      // started_at} to the multi-timer shape {user_public_id, timers: {task: startedAt}}.
      if (parsed.timers === undefined && parsed.task_public_id && parsed.started_at) {
        var migrated = { user_public_id: String(parsed.user_public_id || ''), timers: {} };
        migrated.timers[String(parsed.task_public_id)] = String(parsed.started_at);
        parsed = migrated;
      }
      if (!parsed.user_public_id || !parsed.timers || typeof parsed.timers !== 'object') return null;
      var timers = {};
      Object.keys(parsed.timers).forEach(function (taskId) {
        var ts = parsed.timers[taskId];
        if (typeof ts !== 'string' || ts === '') return;
        if (Number.isNaN(new Date(ts).getTime())) return;
        timers[String(taskId)] = ts;
      });
      if (Object.keys(timers).length === 0) return null;
      return { user_public_id: String(parsed.user_public_id), timers: timers };
    } catch (e) {
      return null;
    }
  }

  // Timers that belong to the current user. A cookie left by a previously
  // logged-in user (different user_public_id) is deliberately ignored.
  function getMyTimerState() {
    var state = readTaskTimerState();
    var currentUserId = getCurrentUserPublicId();
    if (!state || String(state.user_public_id || '') !== String(currentUserId || '')) return null;
    return state;
  }

  function getTimerEntries(state) {
    var entries = [];
    if (!state || !state.timers || typeof state.timers !== 'object') return entries;
    Object.keys(state.timers).forEach(function (taskId) {
      entries.push({ task_public_id: String(taskId), started_at: String(state.timers[taskId]) });
    });
    entries.sort(function (a, b) { return String(a.started_at).localeCompare(String(b.started_at)); });
    return entries;
  }

  function getTaskTimerStartedAt(state, taskId) {
    if (!state || !state.timers || typeof state.timers !== 'object') return null;
    var ts = state.timers[String(taskId || '')];
    return (typeof ts === 'string' && ts !== '') ? ts : null;
  }

  function writeTaskTimerState(state) {
    if (!state) return;
    setCookie(TASK_TIMER_COOKIE_NAME, JSON.stringify(state), 7);
  }

  function clearTaskTimerState() {
    removeCookie(TASK_TIMER_COOKIE_NAME);
  }

  function persistTimerState(timers) {
    var entries = {};
    Object.keys(timers || {}).forEach(function (taskId) {
      var ts = timers[taskId];
      if (typeof ts === 'string' && ts !== '' && !Number.isNaN(new Date(ts).getTime())) {
        entries[String(taskId)] = ts;
      }
    });
    if (Object.keys(entries).length === 0) {
      clearTaskTimerState();
      return null;
    }
    var state = { user_public_id: String(getCurrentUserPublicId() || ''), timers: entries };
    writeTaskTimerState(state);
    return state;
  }

  // Starts the timer for a task without touching timers of other tasks: several
  // timers may run in parallel, each tracked separately by task.
  function startTaskTimer(taskId) {
    var state = getMyTimerState() || { user_public_id: String(getCurrentUserPublicId() || ''), timers: {} };
    var timers = state.timers || {};
    var key = String(taskId || '');
    if (!timers[key]) timers[key] = new Date().toISOString();
    return persistTimerState(timers);
  }

  // Stops the timer of one task and returns the exact measured span
  // {started_at, finished_at, seconds}. {error:'not_found'} when the task has
  // no running timer; {error:'damaged'} when the stored timestamp is unusable
  // (in that case the entry is dropped so it cannot block the flow).
  function stopTaskTimer(taskId) {
    var state = getMyTimerState();
    if (!state) return { error: 'not_found' };
    var timers = state.timers || {};
    var key = String(taskId || '');
    if (!Object.prototype.hasOwnProperty.call(timers, key)) return { error: 'not_found' };
    var startedAt = String(timers[key]);
    var started = new Date(startedAt);
    if (Number.isNaN(started.getTime())) {
      delete timers[key];
      persistTimerState(timers);
      return { error: 'damaged' };
    }
    var finishedAt = new Date();
    var seconds = Math.max(1, Math.floor((finishedAt.getTime() - started.getTime()) / 1000));
    delete timers[key];
    persistTimerState(timers);
    return { started_at: startedAt, finished_at: finishedAt.toISOString(), seconds: seconds };
  }

  // Sum of all running timers of the current user - the topbar widget shows
  // this total while several timers run in parallel, while each task keeps its
  // own start timestamp so per-task accounting stays exact.
  function getTotalElapsedSeconds(state) {
    var total = 0;
    var now = Date.now();
    getTimerEntries(state).forEach(function (entry) {
      var ms = new Date(entry.started_at).getTime();
      if (!Number.isNaN(ms)) total += Math.max(0, Math.floor((now - ms) / 1000));
    });
    return total;
  }

  function formatElapsedSeconds(totalSeconds) {
    var sec = Math.max(0, Number(totalSeconds || 0));
    var hours = Math.floor(sec / 3600);
    var minutes = Math.floor((sec % 3600) / 60);
    var seconds = sec % 60;
    return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
  }

  function initTopbarTaskTimer() {
    var slot = document.getElementById('topbarTaskTimer');
    if (!slot || slot.dataset.bound === '1') return;
    slot.dataset.bound = '1';
    topbarTimerEl = slot;
    renderTopbarTaskTimer();
  }

  // Global running-timer indicator in the topbar: shows the total elapsed time
  // of all running timers and the task while a single timer runs. With several
  // parallel timers it shows the total + a count badge; clicking opens a small
  // menu listing every running task with its own elapsed time.
  function renderTopbarTaskTimer() {
    if (!topbarTimerEl) return;
    var state = getMyTimerState();
    var entries = getTimerEntries(state);

    if (entries.length === 0) {
      if (topbarTimerTickId) {
        window.clearInterval(topbarTimerTickId);
        topbarTimerTickId = null;
      }
      closeTopbarTimerMenu();
      topbarTimerEl.classList.add('d-none');
      return;
    }

    topbarTimerEl.classList.remove('d-none');
    if (!topbarTimerEl.dataset.built) {
      topbarTimerEl.dataset.built = '1';
      topbarTimerEl.innerHTML = ''
        + '<div class="crm-topbar-timer-wrap">'
        + '<a class="crm-topbar-timer-link" href="javascript:void(0)" title="' + window.CRM.i18n.t('topbar.timer_running', 'Таймер запущен') + '">'
        + '<span class="crm-topbar-timer-dot" aria-hidden="true"></span>'
        + '<span class="crm-topbar-timer-time">00:00:00</span>'
        + '<span class="crm-topbar-timer-title"></span>'
        + '<span class="crm-topbar-timer-count d-none" aria-hidden="true"></span>'
        + '</a>'
        + '<div class="crm-topbar-timer-menu d-none" role="menu"></div>'
        + '</div>';
      var linkEl = topbarTimerEl.querySelector('.crm-topbar-timer-link');
      if (linkEl) {
        linkEl.addEventListener('click', function (e) {
          if (getTimerEntries(getMyTimerState()).length > 1) {
            e.preventDefault();
            toggleTopbarTimerMenu();
          }
        });
      }
    }

    var timeEl = topbarTimerEl.querySelector('.crm-topbar-timer-time');
    var titleEl = topbarTimerEl.querySelector('.crm-topbar-timer-title');
    var countEl = topbarTimerEl.querySelector('.crm-topbar-timer-count');
    var linkEl = topbarTimerEl.querySelector('.crm-topbar-timer-link');

    var updateFn = function () {
      var s = getMyTimerState();
      var list = getTimerEntries(s);
      if (list.length === 0) {
        renderTopbarTaskTimer();
        return;
      }
      timeEl.textContent = formatElapsedSeconds(getTotalElapsedSeconds(s));
      prefetchTopbarTimerTitles(list);
      if (list.length === 1) {
        var one = list[0];
        countEl.classList.add('d-none');
        titleEl.classList.remove('d-none');
        titleEl.textContent = topbarTimerTitles[one.task_public_id] || one.task_public_id;
        linkEl.setAttribute('href', 'index.php?route=task-detail&task_public_id=' + encodeURIComponent(one.task_public_id));
        linkEl.setAttribute('title', window.CRM.i18n.t('topbar.timer_running', 'Таймер запущен') + (titleEl.textContent ? ' · ' + titleEl.textContent : ''));
      } else {
        titleEl.classList.add('d-none');
        titleEl.textContent = '';
        countEl.classList.remove('d-none');
        countEl.textContent = '×' + list.length;
        linkEl.removeAttribute('href');
        linkEl.setAttribute('title', window.CRM.i18n.t('topbar.timers_running', 'Таймеры запущены') + ' (' + list.length + ')');
        renderTopbarTimerMenu(s);
      }
    };
    updateFn();
    if (topbarTimerTickId) {
      window.clearInterval(topbarTimerTickId);
    }
    topbarTimerTickId = window.setInterval(updateFn, 1000);
  }

  // Fetches task titles for the topbar widget once per task (cached), so the
  // running-timer indicator and its menu show human-readable names without
  // hammering the API on every tick.
  function prefetchTopbarTimerTitles(entries) {
    entries.forEach(function (entry) {
      var id = String(entry.task_public_id || '');
      if (!id || topbarTimerTitles[id] !== undefined || topbarTimerTitleFetching[id]) return;
      topbarTimerTitleFetching[id] = true;
      window.CRM.api.request('api/v1/tasks/' + encodeURIComponent(id), { silent: true })
        .then(function (env) {
          var task = env && env.data ? (env.data.task || env.data) : null;
          var title = task && task.title ? String(task.title) : '';
          topbarTimerTitles[id] = title || id;
          delete topbarTimerTitleFetching[id];
          renderTopbarTaskTimer();
        })
        .catch(function () {
          topbarTimerTitles[id] = id;
          delete topbarTimerTitleFetching[id];
        });
    });
  }

  function renderTopbarTimerMenu(state) {
    var menu = topbarTimerEl ? topbarTimerEl.querySelector('.crm-topbar-timer-menu') : null;
    if (!menu) return;
    var list = getTimerEntries(state);
    var key = list.map(function (e) { return e.task_public_id; }).sort().join('|');
    if (topbarTimerMenuKey !== key) {
      topbarTimerMenuKey = key;
      var header = '<div class="crm-topbar-timer-menu-head">' + window.CRM.i18n.t('topbar.timers_running', 'Таймеры запущены') + ' (' + list.length + ')</div>';
      var rows = list.map(function (entry) {
        return '<a class="crm-topbar-timer-menu-item" role="menuitem" href="index.php?route=task-detail&task_public_id=' + encodeURIComponent(entry.task_public_id) + '">'
          + '<span class="crm-topbar-timer-menu-id" data-menu-title="' + entry.task_public_id + '"></span>'
          + '<span class="crm-topbar-timer-menu-time" data-menu-time="' + entry.task_public_id + '"></span>'
          + '</a>';
      }).join('');
      menu.innerHTML = header + rows;
    }
    var now = Date.now();
    list.forEach(function (entry) {
      var id = String(entry.task_public_id);
      var titleEl = menu.querySelector('[data-menu-title="' + id + '"]');
      if (titleEl) titleEl.textContent = topbarTimerTitles[id] || id;
      var timeEl = menu.querySelector('[data-menu-time="' + id + '"]');
      if (timeEl) {
        var ms = new Date(entry.started_at).getTime();
        timeEl.textContent = Number.isNaN(ms) ? '00:00:00' : formatElapsedSeconds(Math.max(0, Math.floor((now - ms) / 1000)));
      }
    });
  }

  function toggleTopbarTimerMenu() {
    var menu = topbarTimerEl ? topbarTimerEl.querySelector('.crm-topbar-timer-menu') : null;
    if (!menu) return;
    if (!menu.classList.contains('d-none')) {
      menu.classList.add('d-none');
      return;
    }
    renderTopbarTimerMenu(getMyTimerState());
    menu.classList.remove('d-none');
    if (!topbarTimerMenuDocBound) {
      topbarTimerMenuDocBound = true;
      document.addEventListener('click', function (e) {
        if (topbarTimerEl && !topbarTimerEl.contains(e.target)) closeTopbarTimerMenu();
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.key === 'Esc') closeTopbarTimerMenu();
      });
    }
  }

  function closeTopbarTimerMenu() {
    var menu = topbarTimerEl ? topbarTimerEl.querySelector('.crm-topbar-timer-menu') : null;
    if (menu) menu.classList.add('d-none');
  }

  function renderTaskTimerState(taskId, state) {
    var elapsedEl = document.getElementById('taskTimerElapsed');
    var startedAtEl = document.getElementById('taskTimerStartedAt');
    var startBtn = document.getElementById('taskTimerStartBtn');
    var stopBtn = document.getElementById('taskTimerStopBtn');
    var timerForm = document.getElementById('taskTimerLogForm');

    if (!elapsedEl || !startedAtEl || !startBtn || !stopBtn) return;

    var canUseTimer = Boolean(currentTaskPermissions.canWorkItems);
    var myState = getMyTimerState();
    var startedAtHere = getTaskTimerStartedAt(myState, taskId);
    var isRunningHere = Boolean(startedAtHere);
    var otherTimers = myState ? getTimerEntries(myState).filter(function (e) {
      return String(e.task_public_id) !== String(taskId || '');
    }) : [];

    if (taskTimerTickIntervalId) {
      window.clearInterval(taskTimerTickIntervalId);
      taskTimerTickIntervalId = null;
    }

    if (!canUseTimer) {
      elapsedEl.textContent = '00:00:00';
      startedAtEl.textContent = window.CRM.i18n.t('js.br1.taymer_nedostupen_nedostatochno_prav', 'Таймер недоступен: недостаточно прав');
      startBtn.disabled = true;
      stopBtn.disabled = true;
      if (timerForm) timerForm.classList.add('d-none');
      return;
    }

    if (!isRunningHere) {
      elapsedEl.textContent = '00:00:00';
      if (otherTimers.length > 0) {
        var hint = window.CRM.i18n.t('js.br1.taymer_takzhe_zapushchen_v_neskolkikh_zadachakh', 'Таймер также запущен ещё в {n} задачах');
        startedAtEl.textContent = String(hint).replace('{n}', String(otherTimers.length));
      } else {
        startedAtEl.textContent = window.CRM.i18n.t('js.br1.taymer_ne_zapushchen', 'Таймер не запущен');
      }
      startBtn.disabled = false;
      stopBtn.disabled = true;
      if (timerForm) timerForm.classList.add('d-none');
      return;
    }

    startBtn.disabled = true;
    stopBtn.disabled = false;

    var updateElapsed = function () {
      var s = getMyTimerState();
      var ts = getTaskTimerStartedAt(s, taskId);
      var ms = ts ? new Date(ts).getTime() : NaN;
      if (Number.isNaN(ms)) {
        elapsedEl.textContent = '00:00:00';
        return;
      }
      elapsedEl.textContent = formatElapsedSeconds(Math.max(0, Math.floor((Date.now() - ms) / 1000)));
    };

    startedAtEl.textContent = window.CRM.i18n.t('js.br1.start', 'Старт: ') + formatDate(startedAtHere);
    updateElapsed();
    taskTimerTickIntervalId = window.setInterval(updateElapsed, 1000);
  }

  function bindTaskTimerFlow(taskId) {
    var startBtn = document.getElementById('taskTimerStartBtn');
    var stopBtn = document.getElementById('taskTimerStopBtn');
    var timerForm = document.getElementById('taskTimerLogForm');
    var timerCancelBtn = document.getElementById('taskTimerLogCancelBtn');
    if (!startBtn || !stopBtn || !timerForm) return;

    var minutesInput = timerForm.querySelector('[name="minutes_spent"]');
    var noteInput = timerForm.querySelector('[name="note"]');
    var activityInput = timerForm.querySelector('[name="activity_code"]');
    var pendingLogPayload = null;

    if (startBtn.dataset.bound === '1') {
      renderTaskTimerState(taskId, readTaskTimerState());
      return;
    }

    startBtn.addEventListener('click', function () {
      if (!currentTaskPermissions.canWorkItems) {
        return;
      }

      if (getTaskTimerStartedAt(getMyTimerState(), taskId)) {
        notify(window.CRM.i18n.t('js.br1.taymer_uzhe_zapushchen_dlya_etoy_zadachi', 'Таймер уже запущен для этой задачи'), 'warning');
        return;
      }

      startTaskTimer(taskId);
      pendingLogPayload = null;
      if (minutesInput) minutesInput.value = '';
      if (noteInput) noteInput.value = '';
      timerForm.classList.add('d-none');
      renderTaskTimerState(taskId, getMyTimerState());
      renderTopbarTaskTimer();
      notify(window.CRM.i18n.t('js.br1.taymer_zapushchen', 'Таймер запущен'));
    });

    stopBtn.addEventListener('click', function () {
      var result = stopTaskTimer(taskId);
      if (!result || result.error === 'not_found') {
        notify(window.CRM.i18n.t('js.br1.aktivnyy_taymer_dlya_etoy_zadachi_ne_nayden', 'Активный таймер для этой задачи не найден'), 'warning');
        renderTaskTimerState(taskId, getMyTimerState());
        return;
      }
      if (result.error === 'damaged') {
        renderTaskTimerState(taskId, getMyTimerState());
        renderTopbarTaskTimer();
        notify(window.CRM.i18n.t('js.br1.taymer_byl_povrezhdyon_i_sbroshen', 'Таймер был повреждён и сброшен'), 'warning');
        return;
      }

      var seconds = result.seconds;
      // Round to the nearest whole minute (minimum 1). The exact elapsed time
      // is always preserved in the worklog note (e.g. [00:02:12]) and shown in
      // the form hint below, so the logged value never silently contradicts
      // what the timer displayed while it was running.
      var roundedMinutes = Math.max(1, Math.round(seconds / 60));
      loadTimeRoundingSetting().then(function (rounding) {
        if (rounding > 0) roundedMinutes = applyTimeRounding(roundedMinutes);
        if (minutesInput) minutesInput.value = String(roundedMinutes);
      });
      pendingLogPayload = {
        seconds: seconds,
        started_at: result.started_at,
        finished_at: result.finished_at
      };
      renderTaskTimerState(taskId, getMyTimerState());
      renderTopbarTaskTimer();
      if (noteInput) noteInput.value = '';
      timerForm.classList.remove('d-none');
      var elapsedHint = document.getElementById('taskTimerLogElapsedHint');
      if (elapsedHint) {
        elapsedHint.textContent = window.CRM.i18n.t('js.br1.taymer_tochnoe_vremya', 'Точное время: ') + formatElapsedSeconds(seconds);
        elapsedHint.classList.remove('d-none');
      }
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
        notify(window.CRM.i18n.t('js.br1.snachala_ostanovite_taymer', 'Сначала остановите таймер'), 'warning');
        return;
      }

      var minutes = Number(minutesInput ? minutesInput.value : 0);
      var note = String(noteInput ? noteInput.value : '').trim();
      var activityCode = String(activityInput ? activityInput.value : '').trim();

      await loadTimeRoundingSetting();
      minutes = applyTimeRounding(minutes);
      if (minutesInput) minutesInput.value = String(minutes);

      if (minutes <= 0) {
        notify(window.CRM.i18n.t('js.br1.ukazhite_kolichestvo_minut_bolshe_nulya', 'Укажите количество минут больше нуля'), 'warning');
        return;
      }

      var timerNote = '[timer_seconds:' + String(Math.max(1, Math.floor(pendingLogPayload.seconds))) + '] '
        + window.CRM.i18n.t('js.br1.taymer_tochnoe_vremya', 'Точное время: ')
        + formatElapsedSeconds(pendingLogPayload.seconds)
        + (note ? ' — ' + note : '');

      try {
        await window.CRM.api.request('api/v1/worklogs', {
          method: 'POST',
          body: {
            task_public_id: taskId,
            minutes_spent: minutes,
            note: timerNote,
            started_at: pendingLogPayload.started_at,
            ended_at: pendingLogPayload.finished_at,
            activity_code: activityCode || null
          }
        });

        pendingLogPayload = null;
        timerForm.classList.add('d-none');
        if (minutesInput) minutesInput.value = '';
        if (noteInput) noteInput.value = '';
        await loadTaskWorklogs(taskId);
        await loadTaskActivity(taskId);
        renderTaskTimerState(taskId, getMyTimerState());
        renderTopbarTaskTimer();
        notify(window.CRM.i18n.t('js.br1.vremya_po_taymeru_dobavleno_v_uchyot', 'Время по таймеру добавлено в учёт'));
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_sokhranit_vremya_po_taymeru', 'Не удалось сохранить время по таймеру'), 'error');
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
        stateNode.textContent = String(message || (window.CRM.i18n.t('js.br1.sostoyanie', 'Состояние: ') + String(stateCode || 'idle')));
      }
    }

    if (!stateNode || !resultNode || !summaryNode || !metaNode || !previewNode || !previewWrap) return;
    if (!suggestion) {
      setTaskCardState('empty', window.CRM.i18n.t('js.br1.ai_svodka_ne_sformirovana', 'AI-сводка не сформирована.'));
      resultNode.classList.add('d-none');
      previewWrap.classList.add('d-none');
      if (dismissBtn) dismissBtn.disabled = true;
      if (previewBtn) previewBtn.disabled = true;
      if (applyBtn) applyBtn.disabled = true;
      return;
    }

    setTaskCardState('ready', window.CRM.i18n.t('js.br1.sformirovano', 'Сформировано: ') + formatDate(suggestion.created_at || ''));
    summaryNode.textContent = String(suggestion.summary || '—');
    metaNode.textContent = window.CRM.i18n.t('js.br1.status_2', 'Статус: ') + String(suggestion.status || 'draft');
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
        message: String((envelope && envelope.message) || fallbackMessage || window.CRM.i18n.t('js.br1.ne_udalos_vypolnit_ai_zapros', 'Не удалось выполнить AI-запрос'))
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
        stateNode.textContent = String(message || (window.CRM.i18n.t('js.br1.sostoyanie_2', 'Состояние: ') + String(stateCode || 'idle')));
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
          fallbackMessage: window.CRM.i18n.t('js.br1.ai_deystvie_vremenno_nedostupno', 'AI-действие временно недоступно.')
        });
        return;
      }
      setTaskAiHardDisabled(true);
      setTaskAiState(toAiUiState(aiError), String((aiError && aiError.message) || window.CRM.i18n.t('js.br1.ai_deystvie_vremenno_nedostupno_2', 'AI-действие временно недоступно.')));
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
            notify(window.CRM.i18n.t('js.br1.vyberite_khotya_by_odno_deystvie_dlya_primeneniya', 'Выберите хотя бы одно действие для применения'), 'warning');
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
        return Promise.resolve(window.confirm(window.CRM.i18n.t('js.br1.vy_vybrali_deystvie_s_povyshennym_riskom_prodolzhit_pri', 'Вы выбрали действие с повышенным риском. Продолжить применение?')));
      }
      var highRiskActions = actions.filter(function (a) { return a && a.high_risk; });
      if (highRiskActionsNode) {
        highRiskActionsNode.innerHTML = '<ul class="mb-0 ps-3">' + highRiskActions.map(function (a) {
          var label = a.label || a.field || a.type || window.CRM.i18n.t('js.br1.deystvie', 'действие');
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
          : window.CRM.i18n.t('js.br1.predprosmotr_vremenno_nedostupen_obnovite_ai_rezultat', 'Предпросмотр временно недоступен. Обновите AI-результат.');
        setTaskAiState('ready', blockedMessage);
        return null;
      }
      setTaskAiState('loading', window.CRM.i18n.t('js.br1.podgotavlivaem_preview_ai_predlozheniya', 'Подготавливаем preview AI-предложения...'));
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
          var statusLabels = { draft: window.CRM.i18n.t('js.br1.chernovik', 'Черновик'), ready: window.CRM.i18n.t('js.br1.gotovo_5', 'Готово'), applied: window.CRM.i18n.t('js.br1.primeneno', 'Применено'), dismissed: window.CRM.i18n.t('js.br1.otkloneno', 'Отклонено'), error: window.CRM.i18n.t('js.br1.oshibka', 'Ошибка') };
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
      setTaskAiState('loading', window.CRM.i18n.t('js.br1.formiruem_ai_predlozhenie_po_zadache', 'Формируем AI-предложение по задаче...'));

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
            var statusLabels = { draft: window.CRM.i18n.t('js.br1.chernovik_2', 'Черновик'), ready: window.CRM.i18n.t('js.br1.gotovo_6', 'Готово'), applied: window.CRM.i18n.t('js.br1.primeneno_2', 'Применено'), dismissed: window.CRM.i18n.t('js.br1.otkloneno_2', 'Отклонено'), error: window.CRM.i18n.t('js.br1.oshibka_2', 'Ошибка') };
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
      setTaskAiState('loading', window.CRM.i18n.t('js.br1.peregeneriruem_ai_predlozhenie', 'Перегенерируем AI-предложение...'));

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
          setTaskAiState('ready', window.CRM.i18n.t('js.br1.ai_ne_predlozhil_izmeneniy_opisaniya', 'AI не предложил изменений описания.'));
          notify(window.CRM.i18n.t('js.br1.ai_ne_predlozhil_izmeneniy_opisaniya_2', 'AI не предложил изменений описания.'), 'warning');
          return;
        }

        var confirmed = await confirmDescriptionDiff(descChange.value);
        if (!confirmed) {
          setTaskAiState('ready', window.CRM.i18n.t('js.br1.primenenie_otmeneno', 'Применение отменено.'));
          notify(window.CRM.i18n.t('js.br1.primenenie_otmeneno_2', 'Применение отменено.'), 'warning');
          return;
        }

        setTaskAiState('loading', window.CRM.i18n.t('js.br1.primenyaem_uluchshenie_opisaniya', 'Применяем улучшение описания...'));
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
        setTaskAiState('applied', window.CRM.i18n.t('js.br1.ai_predlozhenie_primeneno', 'AI-предложение применено.'));
        if (aiClient && typeof aiClient.setDrawerState === 'function') {
          aiClient.setDrawerState('applied');
        }
        await loadTaskActivity(taskId);
        notify(window.CRM.i18n.t('js.br1.opisanie_zadachi_uluchsheno', 'Описание задачи улучшено.'));
      } catch (error) {
        var aiError = toAiUiError(error, window.CRM.i18n.t('js.br1.ne_udalos_uluchshit_opisanie', 'Не удалось улучшить описание'));
        setTaskAiState(toAiUiState(aiError), aiError.message || window.CRM.i18n.t('js.br1.ne_udalos_uluchshit_opisanie_2', 'Не удалось улучшить описание'));
        notify(aiError.message || window.CRM.i18n.t('js.br1.ne_udalos_uluchshit_opisanie_3', 'Не удалось улучшить описание'), 'error');
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
          if (previewCode === 'AI_SUGGESTION_NOT_ACTIONABLE' || previewCode === 'AI_SUGGESTION_STALE' || previewMsg.indexOf(window.CRM.i18n.t('js.br1.ustarelo', 'устарело')) >= 0 || previewMsg.indexOf(window.CRM.i18n.t('js.br1.uzhe_primeneno', 'уже применено')) >= 0) {
            setTaskAiState('ready', window.CRM.i18n.t('js.br1.ai_predlozhenie_uzhe_primeneno', 'AI-предложение уже применено.'));
            notify(window.CRM.i18n.t('js.br1.ai_predlozhenie_uzhe_primeneno_nazhmite_knopku_eshchyo', 'AI-предложение уже применено. Нажмите кнопку ещё раз для перегенерации.'), 'warning');
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
          setTaskAiState('ready', window.CRM.i18n.t('js.br1.ai_predlozhenie_sformirovano_net_deystviy_dlya_primenen', 'AI-предложение сформировано. Нет действий для применения.'));
          notify(window.CRM.i18n.t('js.br1.ai_predlozhenie_sformirovano_net_deystviy_dlya_primenen_2', 'AI-предложение сформировано. Нет действий для применения.'), 'warning');
          return;
        }

        var hasHighRisk = selectedActions.some(function (action) {
          return Boolean(action && action.high_risk);
        });
        if (hasHighRisk) {
          var confirmed = await showHighRiskModal(selectedActions);
          if (!confirmed) {
            setTaskAiState('ready', window.CRM.i18n.t('js.br1.ai_predlozhenie_sformirovano_primenenie_otmeneno', 'AI-предложение сформировано. Применение отменено.'));
            notify(window.CRM.i18n.t('js.br1.primenenie_otmeneno_3', 'Применение отменено.'), 'warning');
            return;
          }
        }

        setTaskAiState('loading', window.CRM.i18n.t('js.br1.primenyaem_ai_predlozhenie', 'Применяем AI-предложение...'));
        var applyResult = await applySelectedActions(selectedActions);

        if (applyResult.appliedCount <= 0) {
          setTaskAiState('error', window.CRM.i18n.t('js.br1.ne_udalos_primenit_ai_predlozhenie', 'Не удалось применить AI-предложение.'));
          notify(window.CRM.i18n.t('js.br1.ne_udalos_primenit_ai_predlozhenie_2', 'Не удалось применить AI-предложение.'), 'error');
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
          isPartiallyApplied ? window.CRM.i18n.t('js.br1.ai_predlozhenie_primeneno_chastichno', 'AI-предложение применено частично.') : window.CRM.i18n.t('js.br1.ai_predlozhenie_primeneno_2', 'AI-предложение применено.')
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
        notify(window.CRM.i18n.t('js.br1.ai_predlozhenie_primeneno_3', 'AI-предложение применено: ') + String(applyResult.appliedCount) + window.CRM.i18n.t('js.br1.deystv', ' действ.'));
      } catch (error) {
        var aiError = toAiUiError(error, window.CRM.i18n.t('js.br1.ne_udalos_primenit_ai_predlozhenie_3', 'Не удалось применить AI-предложение'));
        setTaskAiState(toAiUiState(aiError), aiError.message || window.CRM.i18n.t('js.br1.ne_udalos_primenit_ai_predlozhenie_4', 'Не удалось применить AI-предложение'));
        notify(aiError.message || window.CRM.i18n.t('js.br1.ne_udalos_primenit_ai_predlozhenie_5', 'Не удалось применить AI-предложение'), 'error');
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
        return window.confirm(window.CRM.i18n.t('js.br1.primenit_uluchshennoe_opisanie_zadachi', 'Применить улучшенное описание задачи?'));
      }

      if (descriptionDiffOldNode) {
        descriptionDiffOldNode.textContent = String(currentTask && currentTask.description ? currentTask.description : window.CRM.i18n.t('js.br1.opisanie_otsutstvuet_2', 'Описание отсутствует'));
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
            headers: {
              'X-Idempotency-Key': window.CRM.api.createIdempotencyKey('web-subtask-intent')
            },
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
                message: window.CRM.i18n.t('js.br1.dlya_izmeneniya_opisaniya_trebuetsya_aktualnaya_versiya', 'Для изменения описания требуется актуальная версия задачи.')
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
                message: window.CRM.i18n.t('js.br1.ne_udalos_otkryt_formu_vstrechi_obnovite_stranitsu_i_po', 'Не удалось открыть форму встречи. Обновите страницу и повторите.')
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
          warnings.push(window.CRM.i18n.t('js.br1.nevalidnoe_ili_nepodderzhivaemoe_ai_deystvie_propushche', 'Невалидное или неподдерживаемое AI-действие пропущено: ') + (actionType || actionField || 'unknown_action'));
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

    bindIntentButton(generateBtn, 'summary', window.CRM.i18n.t('js.br1.ai_svodka_sformirovana', 'AI-сводка сформирована'), window.CRM.i18n.t('js.br1.ne_udalos_sformirovat_ai_svodku', 'Не удалось сформировать AI-сводку'), function () {
      return { prompt: window.CRM.i18n.t('js.br1.sdelay_kratkuyu_read_only_svodku_po_zadache', 'Сделай краткую read-only сводку по задаче.') };
    }, { showDrawer: true });
    bindIntentButton(nextActionBtn, 'next-action', window.CRM.i18n.t('js.br1.ai_sleduyushchiy_shag_sformirovan', 'AI-следующий шаг сформирован'), window.CRM.i18n.t('js.br1.ne_udalos_sformirovat_sleduyushchiy_shag', 'Не удалось сформировать следующий шаг'), function () {
      return {};
    }, { showDrawer: true });
    bindIntentButton(decomposeBtn, 'decompose', window.CRM.i18n.t('js.br1.ai_predlozhenie_podzadach_sformirovano', 'AI-предложение подзадач сформировано'), window.CRM.i18n.t('js.br1.ne_udalos_sformirovat_predlozhenie_podzadach', 'Не удалось сформировать предложение подзадач'), function () {
      return {};
    }, { showDrawer: true });
    bindIntentButton(checklistBtn, 'checklist', window.CRM.i18n.t('js.br1.ai_predlozhenie_cheklista_sformirovano', 'AI-предложение чеклиста сформировано'), window.CRM.i18n.t('js.br1.ne_udalos_sformirovat_ai_cheklist', 'Не удалось сформировать AI-чеклист'), function () {
      return {};
    }, { showDrawer: true });
    bindIntentButton(improveDescriptionBtn, 'summary', window.CRM.i18n.t('js.br1.ai_predlozhenie_uluchshennogo_opisaniya_sformirovano', 'AI-предложение улучшенного описания сформировано'), window.CRM.i18n.t('js.br1.ne_udalos_uluchshit_opisanie_4', 'Не удалось улучшить описание'), function () {
      return {
        prompt: window.CRM.i18n.t('js.br1.sfokusiruysya_na_uluchshenii_opisaniya_zadachi_verni_re', 'Сфокусируйся на улучшении описания задачи. Верни результат строго в structured JSON по системной схеме task_summary с полями improved_description и suggested_actions(update_task_description).')
      };
    }, { autoApply: true });
    bindIntentButton(commentDraftBtn, 'comment-draft', window.CRM.i18n.t('js.br1.ai_chernovik_kommentariya_sformirovan', 'AI-черновик комментария сформирован'), window.CRM.i18n.t('js.br1.ne_udalos_sformirovat_ai_chernovik_kommentariya', 'Не удалось сформировать AI-черновик комментария'), function () {
      return {};
    }, { showDrawer: true });
    bindIntentButton(qualityBtn, 'quality', window.CRM.i18n.t('js.br1.ai_proverka_zadachi_sformirovana', 'AI-проверка задачи сформирована'), window.CRM.i18n.t('js.br1.ne_udalos_vypolnit_ai_proverku_zadachi', 'Не удалось выполнить AI-проверку задачи'), function () {
      return {};
    }, { showDrawer: true });

    if (createMeetingBtn && createMeetingBtn.dataset.bound !== '1') {
      createMeetingBtn.addEventListener('click', function () {
        if (!openTaskLinkedCalendarModal()) {
          notify(window.CRM.i18n.t('js.br1.ne_udalos_otkryt_formu_vstrechi_obnovite_stranitsu_i_po_2', 'Не удалось открыть форму встречи. Обновите страницу и повторите.'), 'error');
          return;
        }
        notify(window.CRM.i18n.t('js.br1.otkryta_forma_vstrechi_proverte_detali_i_sokhranite_sob', 'Открыта форма встречи. Проверьте детали и сохраните событие вручную.'));
      });
      createMeetingBtn.dataset.bound = '1';
    }

    if (previewBtn) {
      previewBtn.addEventListener('click', async function () {
        if (!currentTaskAiSuggestion || !currentTaskAiSuggestion.public_id) {
          notify(window.CRM.i18n.t('js.br1.snachala_sformiruyte_ai_svodku', 'Сначала сформируйте AI-сводку'), 'warning');
          return;
        }
        if (aiClient && typeof aiClient.canPreviewSuggestion === 'function' && !aiClient.canPreviewSuggestion(currentTaskAiSuggestion)) {
          var blockedMessage = typeof aiClient.suggestionPreviewPolicyMessage === 'function'
            ? aiClient.suggestionPreviewPolicyMessage(currentTaskAiSuggestion)
            : window.CRM.i18n.t('js.br1.predprosmotr_vremenno_nedostupen_obnovite_ai_rezultat_2', 'Предпросмотр временно недоступен. Обновите AI-результат.');
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
          notify(window.CRM.i18n.t('js.br1.predprosmotr_gotov_izmeneniya_primenyayutsya_tolko_vruc', 'Предпросмотр готов. Изменения применяются только вручную.'));
        } catch (error) {
          var aiError = toAiUiError(error, window.CRM.i18n.t('js.br1.ne_udalos_postroit_predprosmotr', 'Не удалось построить предпросмотр'));
          setTaskAiState(toAiUiState(aiError), aiError.message || window.CRM.i18n.t('js.br1.ne_udalos_postroit_predprosmotr_2', 'Не удалось построить предпросмотр'));
          if (aiClient && typeof aiClient.renderAiError === 'function') {
            aiClient.renderAiError(error, window.CRM.i18n.t('js.br1.ne_udalos_postroit_predprosmotr_3', 'Не удалось построить предпросмотр'));
          }
          notify(aiError.message || window.CRM.i18n.t('js.br1.ne_udalos_postroit_predprosmotr_4', 'Не удалось построить предпросмотр'), 'error');
        } finally {
          previewBtn.disabled = false;
        }
      });
    }

    if (dismissBtn) {
      dismissBtn.addEventListener('click', async function () {
        if (!currentTaskAiSuggestion || !currentTaskAiSuggestion.public_id) {
          notify(window.CRM.i18n.t('js.br1.svodka_ne_vybrana', 'Сводка не выбрана'), 'warning');
          return;
        }
        dismissBtn.disabled = true;
        setTaskAiState('loading', window.CRM.i18n.t('js.br1.otklonyaem_ai_predlozhenie', 'Отклоняем AI-предложение...'));
        try {
          var envelope = aiClient && typeof aiClient.dismissSuggestion === 'function'
            ? await aiClient.dismissSuggestion(currentTaskAiSuggestion.public_id)
            : await window.CRM.api.request('api/v1/ai/suggestions/' + encodeURIComponent(currentTaskAiSuggestion.public_id) + '/dismiss', {
              method: 'POST',
              headers: { 'X-Idempotency-Key': window.CRM.api.createIdempotencyKey('ai-suggestion-dismiss') }
            });
          currentTaskAiSuggestion = envelope && envelope.data ? envelope.data.suggestion : currentTaskAiSuggestion;
          renderTaskAiSuggestionCard(currentTaskAiSuggestion, null);
          setTaskAiState('dismissed', window.CRM.i18n.t('js.br1.ai_predlozhenie_skryto', 'AI-предложение скрыто.'));
          if (aiClient && typeof aiClient.setDrawerState === 'function') {
            aiClient.setDrawerState('dismissed');
          }
          if (aiClient && typeof aiClient.closeSuggestionDrawer === 'function') {
            aiClient.closeSuggestionDrawer();
          }
          notify(window.CRM.i18n.t('js.br1.ai_predlozhenie_skryto_2', 'AI-предложение скрыто'));
        } catch (error) {
          var aiError = toAiUiError(error, window.CRM.i18n.t('js.br1.ne_udalos_skryt_ai_predlozhenie', 'Не удалось скрыть AI-предложение'));
          setTaskAiState(toAiUiState(aiError), aiError.message || window.CRM.i18n.t('js.br1.ne_udalos_skryt_ai_predlozhenie_2', 'Не удалось скрыть AI-предложение'));
          if (aiClient && typeof aiClient.renderAiError === 'function') {
            aiClient.renderAiError(error, window.CRM.i18n.t('js.br1.ne_udalos_skryt_ai_predlozhenie_3', 'Не удалось скрыть AI-предложение'));
          }
          if (aiClient && typeof aiClient.closeSuggestionDrawer === 'function') {
            aiClient.closeSuggestionDrawer();
          }
          notify(aiError.message || window.CRM.i18n.t('js.br1.ne_udalos_skryt_ai_predlozhenie_4', 'Не удалось скрыть AI-предложение'), 'error');
        } finally {
          dismissBtn.disabled = false;
        }
      });
    }

    if (applyBtn) {
      applyBtn.addEventListener('click', async function () {
        if (!currentTaskAiSuggestion || !currentTaskAiSuggestion.public_id) {
          notify(window.CRM.i18n.t('js.br1.snachala_sformiruyte_ai_svodku_2', 'Сначала сформируйте AI-сводку'), 'warning');
          return;
        }
        if (String(currentTaskAiSuggestion.status || '') === 'applied') {
          notify(window.CRM.i18n.t('js.br1.predlozhenie_uzhe_primeneno', 'Предложение уже применено'), 'warning');
          return;
        }

        applyBtn.disabled = true;
        setTaskAiState('loading', window.CRM.i18n.t('js.br1.primenyaem_vybrannye_ai_deystviya', 'Применяем выбранные AI-действия...'));
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
            notify(window.CRM.i18n.t('js.br1.dlya_etogo_predlozheniya_net_deystviy_primeneniya_rezhi', 'Для этого предложения нет действий применения. Режим read-only.'), 'warning');
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
              setTaskAiState('error', window.CRM.i18n.t('js.br1.ai_deystviya_ne_primeneny_obnaruzheny_nevalidnye_punkty', 'AI-действия не применены: обнаружены невалидные пункты.'));
              notify(window.CRM.i18n.t('js.br1.ai_deystviya_ne_primeneny_obnaruzheny_nevalidnye_punkty_2', 'AI-действия не применены: обнаружены невалидные пункты.'), 'warning');
              return;
            }
            notify(window.CRM.i18n.t('js.br1.ne_vybrano_ni_odnogo_primenimogo_deystviya', 'Не выбрано ни одного применимого действия'), 'warning');
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
              ? window.CRM.i18n.t('js.br1.ai_predlozhenie_primeneno_chastichno_2', 'AI-предложение применено частично.')
              : window.CRM.i18n.t('js.br1.ai_predlozhenie_primeneno_4', 'AI-предложение применено.')
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
          }
          notify(window.CRM.i18n.t('js.br1.ai_predlozhenie_primeneno_5', 'AI-предложение применено: ') + String(applyResult.appliedCount) + window.CRM.i18n.t('js.br1.deystv_2', ' действ.'));
        } catch (error) {
          var aiError = toAiUiError(error, window.CRM.i18n.t('js.br1.ne_udalos_primenit_ai_svodku', 'Не удалось применить AI-сводку'));
          setTaskAiState(toAiUiState(aiError), aiError.message || window.CRM.i18n.t('js.br1.ne_udalos_primenit_ai_svodku_2', 'Не удалось применить AI-сводку'));
          pendingSelectedActions = [];
          if (aiClient && typeof aiClient.renderAiError === 'function') {
            aiClient.renderAiError(error, window.CRM.i18n.t('js.br1.ne_udalos_primenit_ai_svodku_3', 'Не удалось применить AI-сводку'));
          }
          if (aiClient && typeof aiClient.closeSuggestionDrawer === 'function') {
            aiClient.closeSuggestionDrawer();
          }
          notify(aiError.message || window.CRM.i18n.t('js.br1.ne_udalos_primenit_ai_svodku_4', 'Не удалось применить AI-сводку'), 'error');
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
        if (regenerateModalTitle) regenerateModalTitle.textContent = window.CRM.i18n.t('js.br1.ai_predlozhenie_uzhe_sushchestvuet', 'AI-предложение уже существует');
        if (regenerateBtn) {
          regenerateBtn.disabled = false;
          regenerateBtn.innerHTML = window.CRM.i18n.t('js.br1.peregenerirovat', 'Перегенерировать');
        }
      });
      regenerateBtn.addEventListener('click', function () {
        if (!pendingRegenerateIntent) return;
        var intent = pendingRegenerateIntent;
        pendingRegenerateIntent = null;
        if (regenerateInfo) regenerateInfo.classList.add('d-none');
        if (regenerateLoading) regenerateLoading.classList.remove('d-none');
        if (regenerateLoadingText) regenerateLoadingText.textContent = window.CRM.i18n.t('js.br1.peregeneriruem_ai_predlozhenie_2', 'Перегенерируем AI-предложение...');
        if (regenerateFooter) regenerateFooter.classList.add('d-none');
        if (regenerateModalTitle) regenerateModalTitle.textContent = window.CRM.i18n.t('js.br1.peregeneratsiya_ai', 'Перегенерация AI');
        if (regenerateBtn) {
          regenerateBtn.disabled = true;
          regenerateBtn.innerHTML = window.CRM.i18n.t('js.br1.span_class_spinner_border_spinner_border_sm_me_1_span_p', '<span class="spinner-border spinner-border-sm me-1"></span>Перегенерация...');
        }
        doRegenerateTaskSuggestion(intent.intentPath, intent.payload, intent.successMessage, intent.options).then(function () {
          if (regenerateModalInstance) {
            regenerateModalInstance.hide();
          }
        }).catch(function () {
          if (regenerateInfo) regenerateInfo.classList.remove('d-none');
          if (regenerateLoading) regenerateLoading.classList.add('d-none');
          if (regenerateFooter) regenerateFooter.classList.remove('d-none');
          if (regenerateModalTitle) regenerateModalTitle.textContent = window.CRM.i18n.t('js.br1.ai_predlozhenie_uzhe_sushchestvuet_2', 'AI-предложение уже существует');
          if (regenerateBtn) {
            regenerateBtn.disabled = false;
            regenerateBtn.innerHTML = window.CRM.i18n.t('js.br1.peregenerirovat_2', 'Перегенерировать');
          }
        });
      });
    }

    generateBtn.dataset.bound = '1';
  }

  // --- Time rounding ---
  var _timeRoundingMinutes = null;
  var _timeRoundingLoaded = false;

  async function loadTimeRoundingSetting() {
    if (_timeRoundingLoaded) return _timeRoundingMinutes || 0;
    try {
      var env = await window.CRM.api.request('api/v1/settings', { query: { scope: 'system', name: 'time_rounding_minutes', limit: 1 } });
      var items = window.CRM.api.items(env);
      var found = items.find(function (s) { return String(s.name) === 'time_rounding_minutes'; });
      _timeRoundingMinutes = Number((found && found.value) || 0);
    } catch (e) {
      _timeRoundingMinutes = 0;
    }
    _timeRoundingLoaded = true;
    return _timeRoundingMinutes || 0;
  }

  /** Apply time rounding: round up to the nearest N-minute boundary.
   *  If rounding is 0 or not set, returns the original minutes unchanged. */
  function applyTimeRounding(minutes) {
    if (!_timeRoundingLoaded || !_timeRoundingMinutes || _timeRoundingMinutes <= 0) return minutes;
    var n = Math.floor(_timeRoundingMinutes);
    if (minutes <= 0) return n;
    return Math.ceil(minutes / n) * n;
  }

  function getDefaultWorklogDraft() {
    var now = new Date();
    return {
      minutes_spent: '60',
      logged_at: new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16),
      note: '',
      activity_code: ''
    };
  }

  function getWorklogDraftFromItem(item) {
    return {
      minutes_spent: String(item && item.minutes_spent ? item.minutes_spent : ''),
      logged_at: toLocalDatetimeValue(item && item.logged_at ? item.logged_at : ''),
      note: String(item && item.note ? item.note : ''),
      activity_code: String(item && item.activity_code ? item.activity_code : '')
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
      // Per-user breakdown
      var userTotals = {};
      items.forEach(function (item) {
        var uid = item.user_public_id || 'unknown';
        if (!userTotals[uid]) userTotals[uid] = { name: item.user_full_name || item.user_login || '—', minutes: 0 };
        userTotals[uid].minutes += Number(item.minutes_spent || 0);
      });
      var userBreakdownHtml = '';
      var sortedUsers = Object.keys(userTotals).sort(function (a, b) { return userTotals[b].minutes - userTotals[a].minutes; });
      sortedUsers.forEach(function (uid) {
        var u = userTotals[uid];
        var pct = totalMinutes > 0 ? (u.minutes / totalMinutes * 100).toFixed(0) : 0;
        userBreakdownHtml += '<div class="crm-worklog-user-row"><span class="crm-worklog-user-name">' + escapeHtml(u.name) + '</span>'
          + '<span class="crm-worklog-user-minutes">' + escapeHtml(formatMinutes(u.minutes)) + '</span>'
          + '<span class="crm-worklog-user-pct">(' + pct + '%)</span></div>';
      });
      var userBreakdownBlock = sortedUsers.length > 1
        ? window.CRM.i18n.t('js.br1.div_class_crm_worklog_summary_users_div_class_crm_worklog_summary_label_po_polzovatelyam_div', '<div class="crm-worklog-summary-users"><div class="crm-worklog-summary-label">По пользователям</div>') + userBreakdownHtml + '</div>'
        : '';

      summary.innerHTML = '<div class="crm-worklog-summary-stat">'
        + '<span class="crm-worklog-summary-icon" aria-hidden="true"><i class="fa-regular fa-clock" aria-hidden="true"></i></span>'
        + '<strong class="crm-worklog-summary-value">' + escapeHtml(formatMinutes(totalMinutes)) + '</strong>'
        + window.CRM.i18n.t('js.br1.span_class_crm_worklog_summary_label_vsego_vremeni_span', '<span class="crm-worklog-summary-label">Всего времени</span>')
        + '</div>'
        + '<div class="crm-worklog-summary-stat">'
        + '<span class="crm-worklog-summary-icon" aria-hidden="true"><i class="fa-regular fa-rectangle-list" aria-hidden="true"></i></span>'
        + '<strong class="crm-worklog-summary-value">' + escapeHtml(formatWorklogEntriesLabel(items.length)) + '</strong>'
        + window.CRM.i18n.t('js.br1.span_class_crm_worklog_summary_label_v_zhurnale_span', '<span class="crm-worklog-summary-label">В журнале</span>')
        + '</div>'
        + userBreakdownBlock;
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
      list.innerHTML = window.CRM.i18n.t('js.br1.div_class_crm_worklog_empty_zapisey_vremeni_poka_net_div', '<div class="crm-worklog-empty"><span class="crm-worklog-empty-icon" aria-hidden="true"><i class="fa-regular fa-clock" aria-hidden="true"></i></span><div class="text-muted">Записей времени пока нет.</div></div>');
      return;
    }

    list.innerHTML = '<div class="vstack gap-2">' + items.map(function (item) {
      var worklogId = String(item.public_id || '');
      var isEditing = worklogActiveEditId === worklogId;
      var draft = isEditing ? (worklogEditDrafts[worklogId] || getWorklogDraftFromItem(item)) : null;
      var author = resolveUserDisplayName(item.user_full_name || item.user_login || '', item.user_public_id || '', '—');
      var note = worklogVisibleNote(item.note);
      var noteHtml = note
        ? '<div class="crm-worklog-note">' + escapeHtml(note) + '</div>'
        : window.CRM.i18n.t('js.br1.div_class_crm_worklog_note_text_muted_kommentariy_ne_uk', '<div class="crm-worklog-note text-muted">Комментарий не указан</div>');
      if (!isEditing) {
        var intervalNote = (item.started_at && item.ended_at)
          ? '<span class="crm-worklog-entry-interval"><i class="fa-regular fa-hourglass" aria-hidden="true"></i>' + escapeHtml(window.CRM.i18n.t('js.br1.interval_label', 'Интервал: ')) + escapeHtml(formatDate(item.started_at) + ' — ' + formatDate(item.ended_at)) + '</span>'
          : '';
        var activityNote = item.activity_code
          ? '<span class="crm-worklog-entry-activity" title="' + escapeHtml(window.CRM.i18n.t('js.br1.worklog_activity_label', 'Вид работ')) + '"><i class="fa-solid fa-tag" aria-hidden="true"></i>' + escapeHtml(worklogActivityLabel(item.activity_code)) + '</span>'
          : '';
        return '<article class="crm-worklog-card" data-worklog-id="' + escapeHtml(worklogId) + '">'
          + '<div class="crm-worklog-view-head">'
          + '<div class="crm-worklog-view-main"><span class="crm-worklog-entry-icon" aria-hidden="true"><i class="fa-regular fa-clock" aria-hidden="true"></i></span><strong>' + escapeHtml(worklogDurationLabel(item)) + '</strong>'
          + '<span class="crm-worklog-entry-date"><i class="fa-regular fa-calendar" aria-hidden="true"></i>' + escapeHtml(formatDate(item.logged_at)) + '</span>' + intervalNote + activityNote + '</div>'
          + '<div class="crm-worklog-view-actions"><button class="btn crm-btn-secondary crm-btn-compact" type="button" data-worklog-edit-open="' + escapeHtml(worklogId) + window.CRM.i18n.t('js.br1.redaktirovat_button_4', '" aria-label="Редактировать запись" title="Редактировать"><i class="fa-solid fa-pen" aria-hidden="true"></i><span class="visually-hidden">Редактировать</span></button>')
          + window.CRM.i18n.t('js.br1.details_class_crm_worklog_more_summary_class_btn_btn_li', '<details class="crm-worklog-more"><summary class="btn crm-btn-secondary crm-btn-compact" aria-label="Дополнительные действия"><span>...</span></summary><div class="crm-worklog-more-menu"><button class="btn btn-sm crm-btn-danger crm-btn-compact" type="button" data-worklog-delete-view="') + escapeHtml(worklogId) + window.CRM.i18n.t('js.br1.udalit_button_div_details_div', '">Удалить</button></div></details></div>')
          + '</div>'
          + noteHtml
          + window.CRM.i18n.t('js.br1.div_class_crm_worklog_meta_avtor', '<div class="crm-worklog-meta">Автор: ') + escapeHtml(author) + window.CRM.i18n.t('js.br1.sozdano', ' · Создано: ') + escapeHtml(formatDate(item.created_at)) + '</div>'
          + '</article>';
      }
      return '<article class="crm-worklog-card is-editing" data-worklog-id="' + escapeHtml(worklogId) + '">'
        + window.CRM.i18n.t('js.br1.div_class_crm_worklog_edit_badge_rezhim_redaktirovaniya', '<div class="crm-worklog-edit-badge">Режим редактирования</div>')
        + '<form class="row g-2" data-worklog-update-form="' + escapeHtml(worklogId) + '">'
        + window.CRM.i18n.t('js.br1.div_class_col_md_3_label_class_form_label_minuty_label', '<div class="col-md-3"><label class="form-label">Минуты</label><input class="form-control" type="number" min="1" step="1" name="minutes_spent" value="') + escapeHtml(draft.minutes_spent) + '" required></div>'
        + window.CRM.i18n.t('js.br1.div_class_col_md_3_label_class_form_label_data_vremya_l', '<div class="col-md-3"><label class="form-label">Дата/время</label><input class="form-control" type="datetime-local" name="logged_at" value="') + escapeHtml(draft.logged_at) + '" required></div>'
        + '<div class="col-md-6"><label class="form-label">' + window.CRM.i18n.t('js.br1.worklog_activity_label', 'Вид работ') + '</label><select class="form-select" name="activity_code">' + worklogActivityOptionsHtml(draft.activity_code) + '</select></div>'
        + window.CRM.i18n.t('js.br1.div_class_col_md_6_label_class_form_label_kommentariy_l', '<div class="col-md-6"><label class="form-label">Комментарий</label><input class="form-control" name="note" maxlength="8000" value="') + escapeHtml(draft.note) + '"></div>'
        + window.CRM.i18n.t('js.br1.div_class_col_12_div_class_crm_worklog_meta_avtor', '<div class="col-12"><div class="crm-worklog-meta">Автор: ') + escapeHtml(author) + window.CRM.i18n.t('js.br1.sozdano_2', ' · Создано: ') + escapeHtml(formatDate(item.created_at)) + '</div></div>'
        + '<div class="col-12 crm-task-row-actions">'
        + window.CRM.i18n.t('js.br1.button_class_btn_btn_sm_crm_btn_primary_crm_btn_compact_2', '<button class="btn btn-sm crm-btn-primary crm-btn-compact" type="submit">Сохранить</button>')
        + '<button class="btn btn-sm crm-btn-secondary crm-btn-compact" type="button" data-worklog-edit-cancel="' + escapeHtml(worklogId) + window.CRM.i18n.t('js.br1.otmena_button_4', '">Отмена</button>')
        + '<button class="btn btn-sm crm-btn-danger crm-btn-compact" type="button" data-worklog-delete="' + escapeHtml(worklogId) + window.CRM.i18n.t('js.br1.udalit_button', '">Удалить</button>')
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

  // --- Work-type (activity_code) dictionary for the time-entry forms (TZ 8.6) ---
  var _worklogActivitiesResolved = [];
  var _worklogActivitiesLoading = false;

  async function loadWorklogActivities() {
    if (_worklogActivitiesResolved.length) return _worklogActivitiesResolved;
    if (_worklogActivitiesLoading) return _worklogActivitiesResolved;
    _worklogActivitiesLoading = true;
    try {
      var env = await window.CRM.api.request('api/v1/statuses', { query: { scope: 'worklog_activity', limit: 200 } });
      _worklogActivitiesResolved = window.CRM.api.items(env) || [];
    } catch (e) {
      _worklogActivitiesResolved = [];
    }
    _worklogActivitiesLoading = false;
    return _worklogActivitiesResolved;
  }

  function worklogActivityOptionsHtml(selectedCode) {
    var html = '<option value="">' + window.CRM.i18n.t('js.br1.worklog_activity_none', '— не указан —') + '</option>';
    _worklogActivitiesResolved.forEach(function (s) {
      var code = String(s && s.code ? s.code : '');
      var title = String(s && s.title ? s.title : code);
      var sel = code === String(selectedCode || '') ? ' selected' : '';
      html += '<option value="' + escapeHtml(code) + '"' + sel + '>' + escapeHtml(title) + '</option>';
    });
    if (selectedCode && !_worklogActivitiesResolved.some(function (s) { return String(s && s.code) === String(selectedCode); })) {
      html += '<option value="' + escapeHtml(selectedCode) + '" selected>' + escapeHtml(selectedCode) + '</option>';
    }
    return html;
  }

  function worklogActivityLabel(code) {
    if (!code) return '';
    var found = null;
    for (var i = 0; i < _worklogActivitiesResolved.length; i++) {
      if (String(_worklogActivitiesResolved[i] && _worklogActivitiesResolved[i].code) === String(code)) {
        found = _worklogActivitiesResolved[i];
        break;
      }
    }
    return found ? String(found.title || found.code) : String(code);
  }

  function populateWorklogActivitySelects(defaultCode) {
    var selects = document.querySelectorAll('select[data-worklog-activity-select]');
    if (!selects.length) return;
    loadWorklogActivities().then(function () {
      selects.forEach(function (sel) {
        if (sel.dataset.activityPopulated === '1') return;
        sel.insertAdjacentHTML('beforeend', worklogActivityOptionsHtml(defaultCode));
        sel.dataset.activityPopulated = '1';
      });
    });
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
        var activityCode = String((createForm.querySelector('[name="activity_code"]') || {}).value || '').trim();
        var loggedAtRaw = String((createForm.querySelector('[name="logged_at"]') || {}).value || '').trim();
        var loggedAt = toApiDatetimeFromLocal(loggedAtRaw);

        await loadTimeRoundingSetting();
        minutes = applyTimeRounding(minutes);

        if (minutes <= 0) {
          notify(window.CRM.i18n.t('js.br1.ukazhite_kolichestvo_minut_bolshe_nulya_2', 'Укажите количество минут больше нуля'), 'warning');
          return;
        }
        if (!loggedAtRaw) {
          notify(window.CRM.i18n.t('js.br1.ukazhite_datu_i_vremya', 'Укажите дату и время'), 'warning');
          return;
        }

        try {
          await window.CRM.api.request('api/v1/worklogs', {
            method: 'POST',
            body: {
              task_public_id: taskId,
              minutes_spent: minutes,
              note: note,
              logged_at: loggedAt || undefined,
              activity_code: activityCode || null
            }
          });
          worklogAddOpen = false;
          worklogCreateDraft = getDefaultWorklogDraft();
          await loadTaskWorklogs(taskId);
          await loadTaskActivity(taskId);
          notify(window.CRM.i18n.t('js.br1.zapis_vremeni_dobavlena', 'Запись времени добавлена'));
        } catch (error) {
          var envelopeError = error && error.envelope ? error.envelope : null;
          notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_dobavit_zapis_vremeni', 'Не удалось добавить запись времени'), 'error');
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
      var activityCode = String((form.querySelector('[name="activity_code"]') || {}).value || '').trim();
      var loggedAtRaw = String((form.querySelector('[name="logged_at"]') || {}).value || '').trim();
      var loggedAt = toApiDatetimeFromLocal(loggedAtRaw);

      await loadTimeRoundingSetting();
      minutes = applyTimeRounding(minutes);

      if (minutes <= 0) {
        notify(window.CRM.i18n.t('js.br1.ukazhite_kolichestvo_minut_bolshe_nulya_3', 'Укажите количество минут больше нуля'), 'warning');
        return;
      }
      if (!loggedAtRaw) {
        notify(window.CRM.i18n.t('js.br1.ukazhite_datu_i_vremya_2', 'Укажите дату и время'), 'warning');
        return;
      }

      try {
        await window.CRM.api.request('api/v1/worklogs/' + worklogId, {
          method: 'PATCH',
          body: {
            minutes_spent: minutes,
            note: note,
            logged_at: loggedAt || undefined,
            activity_code: activityCode || null
          }
        });
        worklogActiveEditId = '';
        delete worklogEditDrafts[worklogId];
        await loadTaskWorklogs(taskId);
        await loadTaskActivity(taskId);
        notify(window.CRM.i18n.t('js.br1.zapis_vremeni_obnovlena', 'Запись времени обновлена'));
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_obnovit_zapis_vremeni', 'Не удалось обновить запись времени'), 'error');
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
      if (!window.confirm(window.CRM.i18n.t('js.br1.udalit_zapis_vremeni', 'Удалить запись времени?'))) return;

      try {
        await window.CRM.api.request('api/v1/worklogs/' + worklogId, { method: 'DELETE' });
        if (worklogActiveEditId === worklogId) worklogActiveEditId = '';
        delete worklogEditDrafts[worklogId];
        await loadTaskWorklogs(taskId);
        await loadTaskActivity(taskId);
        notify(window.CRM.i18n.t('js.br1.zapis_vremeni_udalena', 'Запись времени удалена'));
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_udalit_zapis_vremeni', 'Не удалось удалить запись времени'), 'error');
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
        refreshVisualEditors(form, true);
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
        notify(window.CRM.i18n.t('js.br1.izmenenie_nazvaniya_i_opisaniya_dostupno_tolko_avtoru_z', 'Изменение названия и описания доступно только автору задачи'), 'warning');
        return;
      }
      if (section === 'workflow' && !currentTaskPermissions.canEditWorkflow) {
        notify(window.CRM.i18n.t('js.br1.izmenenie_statusa_i_prioriteta_dostupno_avtoru_ili_ispo', 'Изменение статуса и приоритета доступно автору или исполнителю задачи'), 'warning');
        return;
      }
      if ((section === 'assignment' || section === 'project' || section === 'tags') && !currentTaskPermissions.canEditIdentity) {
        notify(window.CRM.i18n.t('js.br1.izmenenie_etogo_bloka_dostupno_tolko_avtoru_zadachi', 'Изменение этого блока доступно только автору задачи'), 'warning');
        return;
      }

      try {
        if (section === 'identity') {
          var titleValue = String((form.querySelector('[name="title"]') || {}).value || '').trim();
          var descriptionValue = getVisualEditorTextareaValue(form.querySelector('[name="description"]')).trim();
          if (!titleValue) {
            notify(window.CRM.i18n.t('js.br1.vvedite_nazvanie_zadachi_2', 'Введите название задачи'), 'warning');
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
              notify(window.CRM.i18n.t('js.br1.chtoby_naznachit_menedzhera_snachala_vyberite_proekt_dl', 'Чтобы назначить менеджера, сначала выберите проект для задачи'), 'warning');
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
        notify(window.CRM.i18n.t('js.br1.parametry_zadachi_obnovleny', 'Параметры задачи обновлены'));
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_obnovit_parametry_zadachi', 'Не удалось обновить параметры задачи'), 'error');
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
      var clientSelect = form.querySelector('[name="client_public_id"]');
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
      if (clientSelect) clientSelect.value = currentTask.task_client_public_id || currentTask.client_public_id || '';
      if (statusSelect) statusSelect.value = currentTask.status_code || 'new';
      if (prioritySelect) prioritySelect.value = currentTask.priority_code || 'normal';
      if (assigneeSelect) assigneeSelect.value = currentTask.assignee_user_public_id || '';
      if (startInput) startInput.value = toDateInputValue(currentTask.start_at);
      if (dueInput) dueInput.value = toDateInputValue(currentTask.due_at);
      if (endInput) endInput.value = toDateInputValue(currentTask.end_at);       if (descInput) {
         descInput.value = currentTask.description || '';
         refreshVisualEditors(form, true);
       }

      // Sync searchable-select widgets (page-api-bindings.js makeSelectSearchable):
      // they hide the native <select> and mirror the value into a visible input
      // on 'change'. Setting .value directly does not fire 'change', so the
      // visible input stays empty (e.g. Project field). Dispatch 'change' so
      // the widget re-renders from the selected option.
      [projectSelect, assigneeSelect, clientSelect].forEach(function (sel) {
        if (sel && sel.dataset && sel.dataset.searchable === '1') {
          sel.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });

      if (tagsSelect && currentTaskTags) {
        var selectedTagIds = currentTaskTags.map(function (tag) { return String(tag.public_id || ''); });
        for (var i = 0; i < tagsSelect.options.length; i++) {
          tagsSelect.options[i].selected = selectedTagIds.indexOf(String(tagsSelect.options[i].value)) >= 0;
        }
      }

      var activitySelect = form.querySelector('[name="activity_code"]');
      if (activitySelect) activitySelect.value = currentTask.activity_code || '';

      var overrideWrap = form.querySelector('[data-task-rate-overrides]');
      var canManageRates = window.CRM.api && typeof window.CRM.api.hasPermission === 'function'
        ? window.CRM.api.hasPermission('finance.rate.manage') : false;
      if (overrideWrap) overrideWrap.classList.toggle('d-none', !canManageRates);
      var costOverride = form.querySelector('[name="override_cost_rate"]');
      var billOverride = form.querySelector('[name="override_bill_rate"]');
      var payoutOverride = form.querySelector('[name="override_payout_rate"]');
      if (costOverride) costOverride.value = currentTask.override_cost_rate != null ? currentTask.override_cost_rate : '';
      if (billOverride) billOverride.value = currentTask.override_bill_rate != null ? currentTask.override_bill_rate : '';
      if (payoutOverride) payoutOverride.value = currentTask.override_payout_rate != null ? currentTask.override_payout_rate : '';
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
      var clientSelect = form.querySelector('[name="client_public_id"]');
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
        notify(window.CRM.i18n.t('js.br1.vvedite_nazvanie_zadachi_3', 'Введите название задачи'), 'warning');
        return;
      }

      var body = {
        title: title,
        row_version: currentTask.row_version
      };

      var projectPublicId = projectSelect ? String(projectSelect.value || '').trim() : '';
      body.project_public_id = projectPublicId || null;

      var clientPublicId = clientSelect ? String(clientSelect.value || '').trim() : '';
      body.client_public_id = clientPublicId || null;

      var statusCode = statusSelect ? String(statusSelect.value || '').trim() : '';
      if (statusCode) body.status_code = statusCode;

      var priorityCode = prioritySelect ? String(prioritySelect.value || '').trim() : '';
      if (priorityCode) body.priority_code = priorityCode;

      var assigneePublicId = assigneeSelect ? String(assigneeSelect.value || '').trim() : '';
      if (assigneePublicId || assigneePublicId === '') body.assignee_user_public_id = assigneePublicId;

      var startAt = startInput ? String(startInput.value || '').trim() : '';
      body.start_at = startAt ? startAt + ' 00:00:00' : '';

      var dueAt = dueInput ? String(dueInput.value || '').trim() : '';
      body.due_at = dueAt ? dueAt + ' 18:00:00' : '';

      var endAt = endInput ? String(endInput.value || '').trim() : '';
      body.end_at = endAt ? endAt + ' 18:00:00' : '';
      var description = descInput ? getVisualEditorTextareaValue(descInput).trim() : '';
      body.description = description;

      var activitySelect = form.querySelector('[name="activity_code"]');
      if (activitySelect) body.activity_code = String(activitySelect.value || '').trim() || null;

      var canManageRates = window.CRM.api && typeof window.CRM.api.hasPermission === 'function'
        ? window.CRM.api.hasPermission('finance.rate.manage') : false;
      if (canManageRates) {
        var costOverride = form.querySelector('[name="override_cost_rate"]');
        var billOverride = form.querySelector('[name="override_bill_rate"]');
        var payoutOverride = form.querySelector('[name="override_payout_rate"]');
        body.override_cost_rate = costOverride && costOverride.value !== '' ? Number(costOverride.value) : null;
        body.override_bill_rate = billOverride && billOverride.value !== '' ? Number(billOverride.value) : null;
        body.override_payout_rate = payoutOverride && payoutOverride.value !== '' ? Number(payoutOverride.value) : null;
      }

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
          subtitle.textContent = window.CRM.i18n.t('js.br1.proekt_4', 'Проект: ') + (currentTask.project_title || '—')
            + window.CRM.i18n.t('js.br1.dedlayn_5', ' · Дедлайн: ') + (currentTask.due_at ? formatDate(currentTask.due_at) : window.CRM.i18n.t('js.br1.ne_zadan_3', 'не задан'));
        }
        await loadTaskActivity(taskId);

        if (window.bootstrap) {
          window.bootstrap.Modal.getOrCreateInstance(modal).hide();
        }
        notify(window.CRM.i18n.t('js.br1.zadacha_obnovlena', 'Задача обновлена'));
      } catch (error) {
        var envelopeError = error && error.envelope ? error.envelope : null;
        notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_obnovit_zadachu', 'Не удалось обновить задачу'), 'error');
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

    if (!availableClients || !availableClients.length) {
      try {
        var clientsEnv = await window.CRM.api.request('api/v1/clients', { query: { limit: 200 } });
        availableClients = window.CRM.api.items(clientsEnv);
      } catch (e) {
        availableClients = [];
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
      var projectOptions = [window.CRM.i18n.t('js.br1.option_value_bez_proekta_option_4', '<option value="">Без проекта</option>')].concat(projects.map(function (p) {
        var selected = currentTask && String(currentTask.project_public_id || '') === String(p.public_id || '') ? ' selected' : '';
        return '<option value="' + escapeHtml(p.public_id || '') + '"' + selected + '>' + escapeHtml(p.title || p.public_id || '') + '</option>';
      }));
      projectSelect.innerHTML = projectOptions.join('');
    }

    var clientSelect = form.querySelector('[name="client_public_id"]');
    if (clientSelect) {
      var clients = availableClients || [];
      var currentClientId = currentTask ? (String(currentTask.task_client_public_id || '') || String(currentTask.client_public_id || '')) : '';
      var clientOptions = [window.CRM.i18n.t('js.br1.option_value_bez_klienta_2', '<option value="">Без клиента</option>')].concat(clients.map(function (client) {
        var sel = currentClientId && String(currentClientId) === String(client.public_id || '') ? ' selected' : '';
        return '<option value="' + escapeHtml(client.public_id || '') + '"' + sel + '>' + escapeHtml(client.title || client.legal_name || client.public_id || '') + '</option>';
      }));
      clientSelect.innerHTML = clientOptions.join('');
    }

    var statusSelect = form.querySelector('[name="status"]');
    if (statusSelect) {
      var fallbackStatuses = [
        { code: 'new', title: window.CRM.i18n.t('js.br1.k_vypolneniyu_5', 'К выполнению') },
        { code: 'in_progress', title: window.CRM.i18n.t('js.br1.v_rabote_5', 'В работе') },
        { code: 'blocked', title: window.CRM.i18n.t('js.br1.blokirovano_4', 'Блокировано') },
        { code: 'done', title: window.CRM.i18n.t('js.br1.gotovo_7', 'Готово') }
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
            { code: 'low', title: window.CRM.i18n.t('js.br1.nizkiy_3', 'Низкий') },
            { code: 'normal', title: window.CRM.i18n.t('js.br1.normalnyy_3', 'Нормальный') },
            { code: 'high', title: window.CRM.i18n.t('js.br1.vysokiy_3', 'Высокий') },
            { code: 'urgent', title: window.CRM.i18n.t('js.br1.srochnyy_3', 'Срочный') }
          ];
      prioritySelect.innerHTML = priorities.map(function (p) {
        var selected = currentTask && String(currentTask.priority_code || 'normal') === String(p.code || '') ? ' selected' : '';
        return '<option value="' + escapeHtml(p.code || '') + '"' + selected + '>' + escapeHtml(p.title || p.code || '') + '</option>';
      }).join('');
    }

    var assigneeSelect = form.querySelector('[name="assignee_user_public_id"]');
    if (assigneeSelect) {
      var users = availableUsers || [];
      var assigneeOptions = [window.CRM.i18n.t('js.br1.option_value_ne_naznachen_option_7', '<option value="">Не назначен</option>')].concat(users.map(function (u) {
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

    var activitySelect = form.querySelector('[name="activity_code"]');
    if (activitySelect) {
      var acts = [];
      try {
        var actEnv = await window.CRM.api.request('api/v1/statuses', { query: { scope: 'worklog_activity', limit: 200 } });
        acts = window.CRM.api.items(actEnv);
      } catch (e) {
        acts = [];
      }
      activitySelect.innerHTML = [window.CRM.i18n.t('js.br1.worklog_activity_none', '<option value="">Не задан</option>')]
        .concat(acts.map(function (s) {
          var selected = currentTask && String(currentTask.activity_code || '') === String(s.code || '') ? ' selected' : '';
          return '<option value="' + escapeHtml(s.code || '') + '"' + selected + '>' + escapeHtml(s.title || s.code || '') + '</option>';
        })).join('');
    }
  }

  function renderTaskDetailOverview() {
    if (!currentTask) return;

    var titleEl = document.querySelector('.crm-page-title');
    if (titleEl) titleEl.textContent = currentTask.title || titleEl.textContent;

    var subtitle = document.querySelector('.crm-subtitle');
    if (subtitle) {
      subtitle.textContent = window.CRM.i18n.t('js.br1.proekt_5', 'Проект: ') + (currentTask.project_title || '—')
        + window.CRM.i18n.t('js.br1.dedlayn_6', ' · Дедлайн: ') + (currentTask.due_at ? formatDate(currentTask.due_at) : window.CRM.i18n.t('js.br1.ne_zadan_4', 'не задан'));
    }

    renderTaskMetaChips();
    renderTaskDescription(currentTask.description);
    renderTaskProgressByStatus(currentTask.status_code);
    renderTaskRiskBanner();
    window.CRM.currentTaskProjectId = currentTask.project_public_id || '';
    renderTaskSourceChat();
    renderTaskSidebarSummary();
  }

  // Task created from a chat message: show a link back to the dialogue.
  function renderTaskSourceChat() {
    var section = document.getElementById('taskSourceChatSection');
    if (!section || !currentTask) return;
    if (String(currentTask.source_type || '') !== 'chat') {
      section.classList.add('d-none');
      return;
    }

    var payload = null;
    var raw = currentTask.source_payload_json;
    if (raw) {
      try { payload = JSON.parse(raw); } catch (e) { payload = null; }
    }
    payload = payload || {};

    var chatPublicId = String(payload.chat_public_id || currentTask.source_id || '');
    var messagePublicId = String(payload.message_public_id || '');
    var chatTitle = String(payload.chat_title || '');
    var textEl = document.getElementById('taskSourceChatText');
    if (textEl && chatTitle) {
      textEl.textContent = window.CRM.i18n.t('task_detail.chat_source_text', 'Задача создана из сообщения в чате. Перейдите к диалогу, чтобы увидеть контекст.')
        + ' ' + window.CRM.i18n.t('task_detail.chat_source_chat', 'Чат: ') + chatTitle;
    }

    var link = document.getElementById('taskSourceChatLink');
    if (link) {
      var href = 'index.php?route=chat&id=' + encodeURIComponent(chatPublicId);
      if (messagePublicId) href += '&message=' + encodeURIComponent(messagePublicId);
      link.href = href;
    }

    section.classList.remove('d-none');
  }

  async function loadTaskCommentDraft(taskId) {
    try {
      var draftEnvelope = await window.CRM.api.request('api/v1/tasks/' + taskId + '/comment-draft');
      var draftBody = draftEnvelope && draftEnvelope.data && draftEnvelope.data.draft ? draftEnvelope.data.draft.body : '';
      var textArea = document.querySelector('#commentForm [name="comment_text"]');
      if (textArea && draftBody) {
        textArea.value = draftBody;
        refreshVisualEditors(document.getElementById('commentForm'), true);
      }
    } catch (draftError) {
      // Отсутствие черновика не является ошибкой.
    }
  }

  function bindTaskDetailDeferredLoads(taskId, canWorkTask, canEditTask) {
    var tabsNav = document.querySelector('.crm-task-tabs-nav');
    if (!tabsNav || tabsNav.dataset.deferredLoadsBound === '1') return;

    var loads = {};
    function loadForTab(target) {
      if (!target || loads[target]) return loads[target];

      var loader = null;
      if (target === '#detailSubtasks') {
        loader = function () { return loadSubtasks(taskId, canWorkTask, canEditTask); };
      } else if (target === '#detailChecklists') {
        loader = function () { return loadChecklists(taskId, canWorkTask); };
      } else if (target === '#detailComments') {
        loader = function () {
          return Promise.all([
            loadTaskCommentDraft(taskId),
            loadTaskComments(taskId).catch(function () { renderTaskComments([]); })
          ]);
        };
      } else if (target === '#detailWorklogs') {
        loader = function () { return loadTaskWorklogs(taskId); };
      } else if (target === '#detailFiles') {
        loader = function () { return loadTaskFiles(taskId); };
      } else if (target === '#detailActivity') {
        loader = function () { return loadTaskActivity(taskId); };
      } else if (target === '#detailDependencies') {
        loader = function () {
          if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.bindTaskDependencies === 'function') {
            window.CRM.pageApiBindings.bindTaskDependencies(taskId);
          }
          return Promise.resolve();
        };
      }

      if (!loader) return null;
      loads[target] = Promise.resolve().then(loader);
      return loads[target];
    }

    tabsNav.addEventListener('shown.bs.tab', function (event) {
      loadForTab(event.target && event.target.getAttribute('data-bs-target'));
    });
    tabsNav.dataset.deferredLoadsBound = '1';
    var initialTab = tabsNav.querySelector('.nav-link.active[data-bs-target]');
    loadForTab(initialTab && initialTab.getAttribute('data-bs-target'));
  }

  async function initTaskDetailFlow() {
    var statusBadge = document.getElementById('taskStatusBadge');
    if (!statusBadge) return;

    bindTaskTabOverflowNavigation();

    var taskId = await resolveTaskForDetail();
    if (!taskId) {
      notify(window.CRM.i18n.t('js.br1.ne_udalos_opredelit_zadachu_dlya_kartochki', 'Не удалось определить задачу для карточки'), 'warning');
      return;
    }

    try {
      var taskEnvelope = await window.CRM.api.request('api/v1/tasks/' + taskId);
      currentTask = mergeTaskState(extractTaskPayload(taskEnvelope));
      renderTaskDetailOverview();
    } catch (error) {
      var envelopeError = error && error.envelope ? error.envelope : null;
      notify((envelopeError && envelopeError.message) || window.CRM.i18n.t('js.br1.ne_udalos_zagruzit_kartochku_zadachi', 'Не удалось загрузить карточку задачи'), 'error');
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

      if (currentProject && currentProject.manager_user_public_id) {
        currentTask.project_manager_user_public_id = currentProject.manager_user_public_id;
        currentTask.project_manager_name = currentProject.manager_user_name || currentTask.project_manager_name;
      }

      renderTaskDetailOverview();
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
      bindTaskInlineEditors(taskId);

    }

    await loadTaskCollaborationState(taskId);

    bindTaskStatusButtons(taskId);
    bindTaskCommentFlow(taskId);
    bindTaskFileUpload(taskId);
    bindTaskEditFlow(taskId);
    bindSubtaskFlow(taskId, canWorkTask, canEditTask);
    bindChecklistFlow(taskId, canWorkTask);
    populateWorklogActivitySelects(currentTask && currentTask.activity_code);
    bindTaskWorklogFlow(taskId);
    bindTaskTimerFlow(taskId);
    bindTaskAiSummaryFlow(taskId);
    bindTaskDetailDeferredLoads(taskId, canWorkTask, canEditTask);
  }

  function bindTaskTabOverflowNavigation() {
    var tabsNav = document.querySelector('.crm-task-tabs-nav');
    var moreButton = document.getElementById('taskTabsMore');
    if (!tabsNav || !moreButton || tabsNav.dataset.overflowBound === '1') return;

    tabsNav.addEventListener('shown.bs.tab', function (event) {
      var selected = event.target;
      var isOverflowTab = selected && selected.matches('[data-task-overflow-tab]');
      moreButton.classList.toggle('active', Boolean(isOverflowTab));
      if (isOverflowTab) {
        moreButton.setAttribute('aria-current', 'page');
      } else {
        moreButton.removeAttribute('aria-current');
      }
    });

    tabsNav.dataset.overflowBound = '1';
  }

  function init() {
    initLoginFlow();
    initPasswordResetRequestFlow();
    initPasswordResetConfirmFlow();
    initInvitationAcceptFlow();

    if (!window.CRM.api) {
      return;
    }

    bindLogoutButtons();
    enhanceFileInputs(document);
    observeFileInputs();

    ensureProtectedAccess();
    if (!enforceRoutePermission()) return;

    hydrateSessionUi().then(function () {
      if (!enforceRoutePermission()) return;
      applyPermissionVisibility();
      if (document.body.dataset.protected === '1' && window.CRM.ai && typeof window.CRM.ai.hydrateAvailability === 'function') {
        var knownIntents = Array.from(new Set(Object.keys(AI_INTENT_VISIBILITY_SELECTORS).map(function (selector) {
          return AI_INTENT_VISIBILITY_SELECTORS[selector];
        })));
        var aiAvailabilityHydrated = false;
        var hydrateAiAvailability = function () {
          if (aiAvailabilityHydrated) return;
          aiAvailabilityHydrated = true;
          window.CRM.ai.hydrateAvailability(knownIntents).finally(function () {
            applyPermissionVisibility();
          });
        };
        document.addEventListener('crm:page-data-ready', function () {
          window.setTimeout(hydrateAiAvailability, 1500);
        }, { once: true });
        window.setTimeout(hydrateAiAvailability, 12000);
      } else {
        window.setTimeout(function () {
          applyPermissionVisibility();
        }, 0);
      }
      initProjectCreateFlow();
      initTaskCreateFlow();
      initCalendarEventCreateFlow();
      enhanceClientSelects();
      enhanceProjectSelects();
      initQuickClientCreate();
      initQuickProjectCreate();
      if (window.CRM.navigation && typeof window.CRM.navigation.init === 'function') {
        var navInitPromise = window.CRM.navigation.init();
        // navigation.init() is async: it fetches the menu and only then builds
        // the topbar with the profile button. Re-apply the user name after it
        // resolves, otherwise the button is left with the default label.
        if (navInitPromise && typeof navInitPromise.finally === 'function') {
          navInitPromise
            .finally(function () {
              var syncedAfterNav = window.CRM.api && typeof window.CRM.api.getUser === 'function'
                ? window.CRM.api.getUser()
                : null;
              if (syncedAfterNav) {
                setSessionUiUser(syncedAfterNav);
              }
            })
            .catch(function () {
              // Menu fetch may fail on slow networks; the topbar fallback
              // labels are acceptable. Never surface an unhandled rejection.
            });
        }
      }
      initTopbarTaskTimer();
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
    bindLogoutButtons: bindLogoutButtons,
    setSessionUiUser: setSessionUiUser
  };
})();
