(function () {
  'use strict';

  // NOTE: widget availability is decided SERVER-SIDE. GET /api/v1/dashboard/widgets
  // returns an `active` list already filtered by the actor's permissions
  // (DashboardController::resolveActive -> widgetAllowed), and activeKeys() below
  // trusts that list. These definitions only map a widget key to its data route
  // and renderer. Do NOT duplicate permission checks here - they would drift from
  // the authoritative server catalog.
  var definitions = {
    analytics_summary: { route: 'api/v1/analytics/summary', kind: 'summary', link: 'index.php?route=analytics' },
    project_health: { route: 'api/v1/analytics/projects', kind: 'projects', link: 'index.php?route=analytics' },
    team_workload: { route: 'api/v1/analytics/users', kind: 'workload', query: { limit: 6 }, link: 'index.php?route=analytics' },
    time_team: { route: 'api/v1/worklogs/matrix', kind: 'time_team', link: 'index.php?route=time-analytics' },
    notification_inbox: { route: 'api/v1/notifications', kind: 'notifications', query: { limit: 5 }, link: 'index.php?route=notifications' },
    chat_unread: { route: 'api/v1/chats/unread-count', kind: 'count', link: 'index.php?route=chat' },
    client_pipeline: { route: 'api/v1/clients', kind: 'entities', query: { limit: 5 }, link: 'index.php?route=counterparties' },
    company_directory: { route: 'api/v1/companies', kind: 'entities', query: { limit: 5 }, link: 'index.php?route=counterparties' },
    contact_followups: { route: 'api/v1/contacts', kind: 'entities', query: { limit: 5 }, link: 'index.php?route=counterparties' },
    tag_usage: { route: 'api/v1/tags', kind: 'tags', query: { limit: 6 }, link: 'index.php?route=admin-tags' },
    saved_views: { route: 'api/v1/views', kind: 'views', query: { entity_type: 'task', limit: 6 }, link: 'index.php?route=tasks' },
    subscriptions: { route: 'api/v1/subscriptions', kind: 'subscriptions', query: { limit: 6 }, titleFields: ['entity_title'], link: 'index.php?route=tasks', entityLink: true },
    dependency_watch: { route: 'api/v1/dependencies', kind: 'dependencies', query: { limit: 6 }, link: 'index.php?route=tasks' },
    milestone_watch: { route: 'api/v1/milestones', kind: 'milestones', link: 'index.php?route=projects' },
    recurring_health: { route: 'api/v1/recurring', kind: 'recurring', query: { limit: 6 }, link: 'index.php?route=recurring' },
    approval_queue: { route: 'api/v1/approvals', kind: 'approvals', query: { limit: 6 }, link: 'index.php?route=approvals' },
    intake_sla: { route: 'api/v1/intake-items', kind: 'intake', query: { limit: 6 }, link: 'index.php?route=intake' },
    webhook_health: { route: 'api/v1/webhooks/deliveries', kind: 'webhooks', query: { limit: 6 }, link: 'index.php?route=admin-webhooks' },
    workflow_automation: { route: 'api/v1/workflow/rules', kind: 'workflows', query: { limit: 6 }, link: 'index.php?route=admin-workflow' },
    system_health: { route: 'api/v1/health/status', kind: 'health', link: 'index.php?route=admin' },
    active_sessions: { route: 'api/v1/security/sessions', kind: 'sessions', query: { limit: 6 }, link: 'index.php?route=profile' },
    my_workload_efficiency: { route: 'api/v1/dashboard/insights', kind: 'insights_my_load', query: { widget: 'my_workload_efficiency', period: 30 }, link: 'index.php?route=analytics' },
    tasks_actual_time: { route: 'api/v1/dashboard/insights', kind: 'insights_actual_time', query: { widget: 'tasks_actual_time', period: 30 }, link: 'index.php?route=time-analytics' },
    my_kpi_scorecard: { route: 'api/v1/dashboard/insights', kind: 'insights_kpi', query: { widget: 'my_kpi_scorecard', period: 30 }, link: 'index.php?route=analytics' },
    assignee_department_load: { route: 'api/v1/dashboard/insights', kind: 'insights_assignee_load', query: { widget: 'assignee_department_load', period: 30 }, link: 'index.php?route=analytics' },
    tasks_completion_velocity: { route: 'api/v1/dashboard/insights', kind: 'insights_velocity', query: { widget: 'tasks_completion_velocity', period: 30 }, link: 'index.php?route=analytics' },
    workload_efficiency_management: { route: 'api/v1/dashboard/insights', kind: 'insights_workload_mgmt', query: { widget: 'workload_efficiency_management', period: 30 }, link: 'index.php?route=analytics' },
    streams_load_efficiency: { route: 'api/v1/dashboard/insights', kind: 'insights_streams', query: { widget: 'streams_load_efficiency', period: 30 }, link: 'index.php?route=projects' },
    stream_detail_load_efficiency: { route: 'api/v1/dashboard/insights', kind: 'insights_stream_detail', query: { widget: 'stream_detail_load_efficiency', period: 30 }, link: 'index.php?route=projects' }
  };

  function api() {
    return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
  }

  function safe(value) {
    if (window.CRM && window.CRM.text && typeof window.CRM.text.safeText === 'function') return window.CRM.text.safeText(value);
    var text = String(value === null || value === undefined ? '' : value);
    return text.replace(/[&<>"']/g, function (ch) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]; });
  }

  function translate(key, fallback) {
    return window.CRM && window.CRM.i18n && typeof window.CRM.i18n.t === 'function'
      ? window.CRM.i18n.t(key, fallback)
      : fallback;
  }

  function items(envelope) {
    return envelope && envelope.data && Array.isArray(envelope.data.items) ? envelope.data.items : [];
  }

  function data(envelope) {
    return envelope && envelope.data && typeof envelope.data === 'object' ? envelope.data : {};
  }

  function request(definition, queryOverride) {
    if (!api()) return Promise.resolve({ success: false, data: null });
    return api().request(definition.route, { method: 'GET', query: queryOverride || definition.query || {} }).catch(function () {
      return { success: false, data: null };
    });
  }

  function value(item, fields, fallback) {
    for (var i = 0; i < fields.length; i += 1) {
      var candidate = item && item[fields[i]];
      if (candidate !== undefined && candidate !== null && String(candidate).trim() !== '') return candidate;
    }
    return fallback || '—';
  }

  function link(definition, id) {
    if (!id) return definition.link;
    if (String(definition.link).indexOf('route=task') >= 0) return definition.link + '&task_public_id=' + encodeURIComponent(id);
    return definition.link;
  }

  // Build a deep link straight to the subscribed entity (task/project/knowledge
  // page/client/counterparty). Raw & is intentional: safe() escapes it once to
  // &amp;, which the browser decodes back to & when following the link.
  function entityUrl(item, definition) {
    var type = String(item && item.entity_type || '').trim().toLowerCase();
    var entityId = String(item && item.entity_public_id || '').trim();
    if (!entityId) return definition.link;
    if (type === 'task') return 'index.php?route=task-detail&task_public_id=' + encodeURIComponent(entityId);
    if (type === 'project') return 'index.php?route=project-detail&project_public_id=' + encodeURIComponent(entityId);
    if (type === 'knowledge' || type === 'knowledge_page') return 'index.php?route=knowledge-page&id=' + encodeURIComponent(entityId);
    if (type === 'client') return 'index.php?route=client-detail&client_public_id=' + encodeURIComponent(entityId);
    if (type === 'counterparty') return 'index.php?route=counterparty-detail&counterparty_public_id=' + encodeURIComponent(entityId);
    if (type === 'company' || type === 'contact') {
      var label = String(item && (item.title || item.entity_title) || '').trim();
      return 'index.php?route=clients&search=' + encodeURIComponent(label || entityId);
    }
    // Comments and unknown types have no detail page — fall back to the list.
    return definition.link;
  }

  function row(label, valueHtml, href) {
    var content = href ? '<a href="' + safe(href) + '">' + valueHtml + '</a>' : valueHtml;
    return '<div class="crm-dashboard-overview-row"><span>' + safe(label) + '</span><strong>' + content + '</strong></div>';
  }

  function renderSummary(container, envelope, definition) {
    if (!envelope || envelope.success === false) {
      return renderList(container, envelope, definition);
    }
    var summary = data(envelope).summary || {};
    var entries = [
      [translate('dashboard.extra_active_tasks', 'Активные задачи'), Number(summary.active_tasks !== undefined ? summary.active_tasks : Math.max(0, Number(summary.total_tasks || 0) - Number(summary.completed_tasks || 0)))],
      [translate('dashboard.extra_overdue_tasks', 'Просроченные задачи'), Number(summary.overdue_tasks || 0)],
      [translate('dashboard.extra_active_projects', 'Активные проекты'), Number(summary.active_projects || summary.total_projects || 0)],
      [translate('dashboard.extra_week_minutes', 'Минут за неделю'), Number(summary.worklog_minutes_week || 0)]
    ];
    container.innerHTML = entries.map(function (entry) { return row(entry[0], safe(String(entry[1])), definition.link); }).join('');
  }

  function renderCount(container, envelope, definition) {
    if (!envelope || envelope.success === false) {
      return renderList(container, envelope, definition);
    }
    var count = Number(data(envelope).count || 0);
    container.innerHTML = '<div class="crm-dashboard-extra-count"><strong>' + safe(String(count)) + '</strong><span>' + safe(translate('dashboard.extra_unread_count', 'непрочитанных диалогов')) + '</span></div><a class="btn btn-sm crm-btn-secondary mt-2" href="' + safe(definition.link) + '">' + safe(translate('dashboard.extra_open', 'Открыть')) + '</a>';
  }

  function renderList(container, envelope, definition) {
    if (!envelope || envelope.success === false) {
      container.innerHTML = '<div class="text-muted small">' + safe(translate('dashboard.extra_unavailable', 'Данные недоступны')) + '</div>';
      return;
    }
    var list = items(envelope).slice(0, 6);
    if (!list.length) {
      container.innerHTML = '<div class="text-muted small">' + safe(translate('dashboard.extra_empty', 'Пока нет данных')) + '</div>';
      return;
    }
    container.innerHTML = list.map(function (item) {
      var id = value(item, ['public_id'], '');
      var titleFields = definition.titleFields || ['title', 'name', 'full_name', 'user_full_name', 'user_name', 'login', 'label', 'task_title', 'project_title', 'project_name', 'entity_title', 'device_name'];
      var title = value(item, titleFields, '');
      if (!title || title === '—') {
        // Subscriptions, sessions and similar entities carry no display title;
        // fall back to a humanized entity_type instead of a bare placeholder.
        var etype = String(item.entity_type || '').trim();
        title = etype
          ? etype.charAt(0).toUpperCase() + etype.slice(1).replace(/_/g, ' ')
          : translate('dashboard.extra_untitled', 'Без названия');
      }
      var meta = value(item, ['status_title', 'status_code', 'priority_title', 'priority_code', 'due_at', 'next_run_at', 'created_at', 'updated_at'], '');
      var href = definition.entityLink ? entityUrl(item, definition) : link(definition, id);
      return '<div class="crm-dashboard-extra-row"><div class="text-truncate"><a href="' + safe(href) + '">' + safe(title) + '</a>' + (meta ? '<small>' + safe(meta) + '</small>' : '') + '</div></div>';
    }).join('');
  }

  // Upcoming milestones across active projects.
  // The milestones endpoint requires project_public_ids and returns a
  // by_project map, so this loader resolves active projects first and
  // flattens the result into a list of upcoming milestones.
  function loadMilestones(definition) {
    if (!api()) return Promise.resolve({ success: false, data: null });
    return api().request('api/v1/projects', { method: 'GET', query: { status: 'active', limit: 50 }, silent: true })
      .then(function (projectsEnv) {
        var projects = (projectsEnv && projectsEnv.data && Array.isArray(projectsEnv.data.items)) ? projectsEnv.data.items : [];
        var ids = [];
        var titles = {};
        projects.forEach(function (p) {
          var pid = p && p.public_id ? String(p.public_id) : '';
          if (pid) {
            ids.push(pid);
            titles[pid] = String(p.title || '');
          }
        });
        if (!ids.length) return { success: true, data: { by_project: {} } };
        return api().request('api/v1/milestones', { method: 'GET', query: { project_public_ids: ids.join(',') }, silent: true })
          .then(function (msEnv) {
            if (!msEnv || !msEnv.data || !msEnv.data.by_project) return { success: false, data: null };
            Object.keys(msEnv.data.by_project).forEach(function (pid) {
              (msEnv.data.by_project[pid] || []).forEach(function (m) {
                if (!m.project_title && titles[pid]) m.project_title = titles[pid];
              });
            });
            return msEnv;
          })
          .catch(function () { return { success: false, data: null }; });
      })
      .catch(function () { return { success: false, data: null }; });
  }

  function renderMilestones(container, envelope, definition) {
    if (!envelope || envelope.success === false) {
      container.innerHTML = '<div class="text-muted small">' + safe(translate('dashboard.extra_unavailable', 'Данные недоступны')) + '</div>';
      return;
    }
    var byProject = (envelope.data && envelope.data.by_project) ? envelope.data.by_project : {};
    var all = [];
    Object.keys(byProject).forEach(function (pid) {
      (byProject[pid] || []).forEach(function (m) { all.push(m); });
    });
    var nowMs = Date.now();
    var upcoming = all.filter(function (m) {
      var st = String(m.status || '').toLowerCase();
      if (st === 'done' || st === 'completed' || st === 'cancelled') return false;
      if (!m.due_at) return false;
      var ms = Date.parse(String(m.due_at).replace(' ', 'T'));
      return Number.isFinite(ms) && ms >= nowMs;
    }).sort(function (a, b) {
      return String(a.due_at || '').localeCompare(String(b.due_at || ''));
    }).slice(0, 6);
    if (!upcoming.length) {
      container.innerHTML = '<div class="text-muted small">' + safe(translate('dashboard.extra_empty', 'Пока нет данных')) + '</div>';
      return;
    }
    container.innerHTML = upcoming.map(function (m) {
      // Raw & is intentional: safe() escapes it once to &amp;, which the
      // browser decodes back to & when following the link.
      var href = m.project_public_id
        ? 'index.php?route=project-detail&project_public_id=' + encodeURIComponent(String(m.project_public_id))
        : definition.link;
      return '<div class="crm-dashboard-extra-row"><div class="text-truncate">'
        + '<a href="' + safe(href) + '">' + safe(String(m.title || m.public_id || '')) + '</a>'
        + (m.due_at ? '<small>' + safe(String(m.due_at).slice(0, 10)) + '</small>' : '')
        + (m.project_title ? '<small>' + safe(String(m.project_title)) + '</small>' : '')
        + '</div></div>';
    }).join('');
  }

  // Team workload: per-user load (active/overdue tasks + week minutes).
  function renderWorkload(container, envelope, definition) {
    if (!envelope || envelope.success === false) {
      container.innerHTML = '<div class="text-muted small">' + safe(translate('dashboard.extra_unavailable', 'Данные недоступны')) + '</div>';
      return;
    }
    var list = items(envelope).slice(0, 6);
    if (!list.length) {
      container.innerHTML = '<div class="text-muted small">' + safe(translate('dashboard.extra_empty', 'Пока нет данных')) + '</div>';
      return;
    }
    var maxMinutes = 1;
    list.forEach(function (u) {
      var mins = Number(u.worklog_minutes_week || 0);
      if (mins > maxMinutes) maxMinutes = mins;
    });
    container.innerHTML = list.map(function (u) {
      var name = String(u.full_name || u.login || u.public_id || '');
      var active = Number(u.assigned_active_tasks || 0);
      var overdue = Number(u.assigned_overdue_tasks || 0);
      var mins = Number(u.worklog_minutes_week || 0);
      var width = Math.max(2, Math.min(100, Math.round(mins / maxMinutes * 100)));
      var meta = safe(String(active)) + ' ' + safe(translate('dashboard.extra_team_tasks', 'задач'));
      if (overdue > 0) meta += ' · <span class="is-overdue">' + safe(String(overdue)) + ' ' + safe(translate('dashboard.extra_team_overdue', 'просрочено')) + '</span>';
      return '<div class="crm-dashboard-wl-row">'
        + '<div class="crm-dashboard-wl-head"><span class="text-truncate" title="' + safe(name) + '">' + safe(name) + '</span>'
        + '<strong>' + safe(formatMinutesCompact(mins)) + '</strong></div>'
        + '<div class="crm-dashboard-time-bar" aria-hidden="true"><i style="width:' + width + '%"></i></div>'
        + '<div class="crm-dashboard-wl-meta"><span>' + meta + '</span>'
        + '<span class="text-muted">' + safe(translate('dashboard.extra_team_week', 'за неделю')) + '</span></div>'
        + '</div>';
    }).join('');
  }

  function renderTags(container, envelope, definition) {
    var list = items(envelope).slice(0, 6);
    if (!list.length) { renderList(container, envelope, definition); return; }
    container.innerHTML = list.map(function (tag) {
      var title = value(tag, ['title', 'code'], '—');
      var count = Number(tag.usage_count || tag.tasks_count || 0);
      return '<div class="crm-dashboard-extra-row"><span class="crm-chip">' + safe(title) + '</span><strong>' + safe(String(count)) + '</strong></div>';
    }).join('');
  }

  function isoDateForPeriod(date) {
    var m = String(date.getMonth() + 1);
    var d = String(date.getDate());
    if (m.length === 1) m = '0' + m;
    if (d.length === 1) d = '0' + d;
    return date.getFullYear() + '-' + m + '-' + d;
  }

  function formatMinutesCompact(mins) {
    mins = Number(mins || 0);
    if (mins <= 0) return '0';
    var h = Math.floor(mins / 60);
    var m = mins % 60;
    if (h > 0 && m > 0) return h + translate('dashboard.extra_time_hour', 'ч') + ' ' + m + translate('dashboard.extra_time_min', 'м');
    if (h > 0) return h + translate('dashboard.extra_time_hour', 'ч');
    return m + translate('dashboard.extra_time_min', 'м');
  }

  // Team time: ranked hours per visible user for the last 7 days.
  // Visibility is enforced server-side by the worklog matrix endpoint
  // (root sees all, managers see subordinates + team members, employees see only themselves).
  // The matrix payload carries no financial fields, so no rate stripping is needed here.
  var TEAM_TIME_OVER_MINUTES = 2400;  // 40h in 7 days => overload signal
  var TEAM_TIME_UNDER_MINUTES = 900;  // < 15h in 7 days => underload signal

  function renderTeamTime(container, envelope, definition) {
    if (!envelope || envelope.success === false) {
      container.innerHTML = '<div class="text-muted small">' + safe(translate('dashboard.extra_unavailable', 'Данные недоступны')) + '</div>';
      return;
    }
    var data = envelope.data || {};
    var users = data.users || [];
    var totals = data.user_totals || {};
    var dayTotals = data.day_totals || {};
    var dates = data.dates || [];
    if (!users.length) {
      container.innerHTML = '<div class="text-muted small">' + safe(translate('dashboard.extra_empty', 'Пока нет данных')) + '</div>';
      return;
    }

    var rows = users.map(function (u) {
      return {
        publicId: String(u.public_id || ''),
        name: String(u.full_name || u.login || u.public_id || ''),
        minutes: Number(totals[u.public_id] || 0)
      };
    }).sort(function (a, b) { return b.minutes - a.minutes; });

    var grandTotal = 0;
    Object.keys(dayTotals).forEach(function (day) { grandTotal += Number(dayTotals[day] || 0); });
    var days = dates.length || 7;
    var avgPerDay = days > 0 ? Math.round(grandTotal / days) : 0;
    var maxMinutes = rows.length ? rows[0].minutes : 0;
    var visible = rows.slice(0, 8);
    var hiddenCount = rows.length - visible.length;
    var leaderId = rows.length && rows[0].minutes > 0 ? rows[0].publicId : '';
    var laggardId = rows.length > 1 && rows[rows.length - 1].minutes > 0 ? rows[rows.length - 1].publicId : '';

    var html = '<div class="crm-dashboard-time-summary">'
      + '<span>' + safe(translate('dashboard.extra_time_team_period', 'за 7 дней')) + '</span>'
      + '<strong>' + safe(formatMinutesCompact(grandTotal)) + ' ' + safe(translate('dashboard.extra_time_team_total', 'всего')) + '</strong>'
      + '<span>' + safe(translate('dashboard.extra_time_team_avg', 'в среднем в день')) + ': ' + safe(formatMinutesCompact(avgPerDay)) + '</span>'
      + '</div>';

    html += '<div class="crm-dashboard-time-list">';
    visible.forEach(function (row, index) {
      var status = '';
      if (row.minutes >= TEAM_TIME_OVER_MINUTES) {
        status = ' is-over';
      } else if (row.minutes > 0 && row.minutes <= TEAM_TIME_UNDER_MINUTES) {
        status = ' is-under';
      } else if (row.minutes === 0) {
        status = ' is-none';
      }
      var width = maxMinutes > 0 ? Math.max(2, Math.round((row.minutes / maxMinutes) * 100)) : 0;
      var label = status === ' is-over' ? translate('dashboard.extra_time_team_over', 'Переработка')
        : status === ' is-under' ? translate('dashboard.extra_time_team_under', 'Мало времени')
          : status === ' is-none' ? translate('dashboard.extra_time_team_none', 'Нет учёта времени') : '';
      var badge = '';
      if (row.publicId === leaderId) {
        badge = '<span class="crm-dashboard-time-badge is-leader">' + safe(translate('dashboard.extra_time_team_leader', 'Лидер')) + '</span>';
      } else if (row.publicId === laggardId) {
        badge = '<span class="crm-dashboard-time-badge is-laggard">' + safe(translate('dashboard.extra_time_team_laggard', 'Меньше всех')) + '</span>';
      }
      html += '<div class="crm-dashboard-time-row' + status + '" title="' + safe(label ? row.name + ' — ' + label : row.name) + '">'
        + '<span class="crm-dashboard-time-rank">' + (index + 1) + '</span>'
        + '<span class="crm-dashboard-time-name">' + safe(row.name) + '</span>'
        + '<span class="crm-dashboard-time-bar" aria-hidden="true"><i style="width:' + width + '%"></i></span>'
        + '<strong class="crm-dashboard-time-value">' + safe(formatMinutesCompact(row.minutes)) + '</strong>'
        + badge
        + '</div>';
    });
    html += '</div>';
    if (hiddenCount > 0) {
      html += '<div class="crm-dashboard-time-more">' + safe(translate('dashboard.extra_time_team_more', 'и ещё %s').replace('%s', String(hiddenCount))) + '</div>';
    }
    container.innerHTML = html;
  }

  function renderHealth(container, envelope) {
    if (!envelope || envelope.success === false) {
      return renderList(container, envelope, { link: 'index.php?route=admin' });
    }
    var health = data(envelope);
    var ok = health.status === 'ok';
    container.innerHTML = '<div class="crm-dashboard-extra-health ' + (ok ? 'is-ok' : 'is-error') + '"><span></span><strong>' + safe(ok ? translate('dashboard.extra_healthy', 'Работает') : translate('dashboard.extra_unhealthy', 'Требует проверки')) + '</strong></div>'
      + (health.version ? '<small class="text-muted">' + safe(health.version) + '</small>' : '');
  }

  // ---------------------------------------------------------------------------
  // Insights widgets (GET /api/v1/dashboard/insights?widget=...&period=...)
  // The server decides visibility: personal metrics are self-scoped, task time
  // is limited to the actor's visible users, and money fields never travel here.
  // ---------------------------------------------------------------------------

  // Each card remembers its own view options (period, sort, expanded rows,
  // selected project). They live in localStorage instead of user settings so a
  // widget can be re-configured without a server round trip on every click.
  var INSIGHT_PERIODS = [7, 30, 90];

  function insightOption(key, name, fallback, allowed) {
    try {
      var stored = window.localStorage.getItem('crm.dashboard.insight.' + name + '.' + key);
      if (stored !== null && stored !== '' && (!allowed || allowed.indexOf(stored) >= 0)) return stored;
    } catch (e) {
      // Storage can be unavailable (private mode) - fall back to the default.
    }
    return fallback;
  }

  function saveInsightOption(key, name, value) {
    try {
      window.localStorage.setItem('crm.dashboard.insight.' + name + '.' + key, String(value));
    } catch (e) {
      // Ignore: the option is a convenience, not required for the widget to work.
    }
  }

  function widgetPeriod(definition) {
    var fallback = String(Number((definition.query || {}).period) || 30);
    return Number(insightOption(definition.key, 'period', fallback, ['7', '30', '90']));
  }

  function widgetSort(definition, allowed, fallback) {
    return insightOption(definition.key, 'sort', fallback, allowed);
  }

  function widgetExpanded(definition) {
    return insightOption(definition.key, 'expanded', '0', ['0', '1']) === '1';
  }

  function periodOptions() {
    return INSIGHT_PERIODS.map(function (days) {
      return { value: days, label: translate('dashboard.extra_period_' + days, days + ' дн.') };
    });
  }

  // One segmented control markup for period/sort/expand so every insights card
  // looks and behaves the same.
  function insightToolbar(definition, groups) {
    return '<div class="crm-dashboard-insight-toolbar" data-insight-toolbar="' + safe(definition.key) + '">'
      + groups.map(function (group) {
        return '<span class="crm-dashboard-insight-group"><span class="crm-dashboard-insight-toolbar-label">' + safe(group.label) + '</span>'
          + group.options.map(function (option) {
            var active = String(option.value) === String(group.value) ? ' is-active' : '';
            return '<button type="button" class="crm-dashboard-insight-option' + active + '" data-insight-option="'
              + safe(group.name) + '" data-insight-option-value="' + safe(option.value) + '">' + safe(option.label) + '</button>';
          }).join('') + '</span>';
      }).join('') + '</div>';
  }

  function bindInsightToolbar(container, definition) {
    var toolbar = container.querySelector('[data-insight-toolbar]');
    if (!toolbar) return;
    toolbar.addEventListener('click', function (event) {
      var button = event.target && event.target.closest ? event.target.closest('[data-insight-option]') : null;
      if (!button) return;
      event.preventDefault();
      saveInsightOption(definition.key, button.getAttribute('data-insight-option'), button.getAttribute('data-insight-option-value'));
      reloadWidget(definition);
    });
  }

  // Only the options the endpoint understands travel back: sorting is a client-side
  // reorder of the same payload, so it stays in localStorage.
  function insightQuery(definition) {
    var query = Object.assign({}, definition.query || {}, { period: widgetPeriod(definition) });
    // Widgets with a project picker send the stored choice; the rest never carry the
    // parameter, so a stale value in localStorage cannot leak into them.
    if (PROJECT_FILTER_WIDGETS.indexOf(definition.key) >= 0) {
      var project = insightOption(definition.key, 'project', '', null);
      if (project) query.project_public_id = project;
    }
    return query;
  }

  var PROJECT_FILTER_WIDGETS = ['stream_detail_load_efficiency', 'tasks_actual_time'];

  function reloadWidget(definition) {
    var container = document.querySelector('[data-extra-widget-body="' + definition.key + '"]');
    if (!container) return;
    container.innerHTML = loadingHtml();
    if (definition.key === 'milestone_watch') {
      loadMilestones(definition).then(function (envelope) { render(definition, envelope); });
      return;
    }
    request(definition, insightQuery(definition)).then(function (envelope) { render(definition, envelope); });
  }

  function taskDetailUrl(id) {
    return 'index.php?route=task-detail&task_public_id=' + encodeURIComponent(String(id || ''));
  }

  function projectDetailUrl(id) {
    return 'index.php?route=project-detail&project_public_id=' + encodeURIComponent(String(id || ''));
  }

  // Raw & is intentional: safe() escapes it once to &amp;, and the browser decodes
  // it back to & when the link is followed (same convention as entityUrl()).
  function tasksListUrl(params) {
    var parts = ['index.php?route=tasks'];
    Object.keys(params || {}).forEach(function (key) {
      var value = params[key];
      if (value === undefined || value === null || String(value) === '') return;
      parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(String(value)));
    });
    return parts.join('&');
  }

  function loadingHtml() {
    return '<div class="text-muted small">' + safe(translate('dashboard.loading_widget', 'Загрузка...')) + '</div>';
  }

  function unavailableHtml() {
    return '<div class="text-muted small">' + safe(translate('dashboard.extra_unavailable', 'Данные недоступны')) + '</div>';
  }

  function emptyHtml() {
    return '<div class="text-muted small">' + safe(translate('dashboard.extra_empty', 'Пока нет данных')) + '</div>';
  }

  // Fill positional %s placeholders in order. split/join would replace *every*
  // placeholder with the first value, which printed "первые 10 из 10" for a
  // twelve-row list.
  function formatPlaceholders(template, values) {
    var index = 0;
    return String(template).replace(/%s/g, function () {
      var value = index < values.length ? values[index] : '';
      index += 1;
      return String(value);
    });
  }

  function deltaBadge(value) {
    if (value === null || value === undefined || value === '') return '';
    var num = Number(value || 0);
    var cls = num > 0 ? 'is-up' : (num < 0 ? 'is-down' : '');
    return ' <span class="crm-dashboard-insight-delta ' + cls + '">' + (num > 0 ? '+' : '') + num + '%</span>';
  }

  function dateText(value) {
    var text = String(value === null || value === undefined ? '' : value).trim();
    if (text === '') return '';
    var parts = text.slice(0, 10).split('-');
    if (parts.length !== 3) return text;
    return parts[2] + '.' + parts[1] + '.' + parts[0];
  }

  function progressBar(percent) {
    var width = Math.max(0, Math.min(100, Math.round(Number(percent || 0))));
    return '<span class="crm-dashboard-insight-progress" aria-hidden="true"><i style="width:' + width + '%"></i></span>';
  }

  function formatPercentOr(value, emptyLabel) {
    if (value === null || value === undefined || value === '') return emptyLabel;
    return Number(value) + '%';
  }

  // Activity codes are stored in English; the label is translated per locale and an
  // unknown code is shown as-is instead of being hidden.
  var ACTIVITY_LABEL_KEYS = {
    development: 'dashboard.extra_activity_dev',
    dev: 'dashboard.extra_activity_dev',
    qa: 'dashboard.extra_activity_qa',
    testing: 'dashboard.extra_activity_qa',
    design: 'dashboard.extra_activity_design',
    meeting: 'dashboard.extra_activity_meeting',
    support: 'dashboard.extra_activity_support',
    admin: 'dashboard.extra_activity_admin',
    research: 'dashboard.extra_activity_research',
    management: 'dashboard.extra_activity_management',
    unassigned: 'dashboard.extra_activity_unassigned'
  };

  function activityLabel(code) {
    var key = String(code || '').trim().toLowerCase();
    var labelKey = ACTIVITY_LABEL_KEYS[key];
    if (!labelKey) return String(code || '');
    return translate(labelKey, String(code || ''));
  }

  function insightsPayload(envelope) {
    var payload = data(envelope);
    return payload && typeof payload.data === 'object' && payload.data !== null ? payload.data : {};
  }

  // With `href` the tile becomes a link to the filtered list behind the number, so a
  // snapshot counter is a place to go rather than a dead end.
  function insightTile(label, value, signal, valueIsHtml, href) {
    var cls = signal ? ' crm-dashboard-insight-tile--' + signal : '';
    var body = '<span class="crm-dashboard-insight-label">'
      + safe(label) + '</span><strong>' + (valueIsHtml ? String(value) : safe(value)) + '</strong>';
    if (href) {
      return '<a class="crm-dashboard-insight-tile is-link' + cls + '" href="' + safe(href) + '">' + body + '</a>';
    }

    return '<div class="crm-dashboard-insight-tile' + cls + '">' + body + '</div>';
  }

  // Where the weekly capacity came from: a number presented as "the target" must
  // say whether it is the org's calendar, an explicit setting or the 40h default.
  function capacitySourceLabel(source) {
    var map = {
      calendar: translate('dashboard.extra_insights_capacity_calendar', 'по календарю'),
      setting: translate('dashboard.extra_insights_capacity_setting', 'по настройке')
    };

    return map[String(source || '')] || translate('dashboard.extra_insights_capacity_default', 'по умолчанию');
  }

  // "Медиана команды: 42% — вы выше на 8 п.п." - the comparison the load number
  // needs to mean anything. Null median means the scope had nothing to compare.
  function scopeMedianHtml(payload) {
    var median = payload.scope_median_load_percent;
    var sample = Number(payload.scope_sample || 0);
    if (median === null || median === undefined || median === '' || sample <= 1) {
      return sample <= 1
        ? '<div class="crm-dashboard-insight-legend">' + safe(translate('dashboard.extra_insights_scope_none', 'Нет коллег в доступном скоупе для сравнения')) + '</div>'
        : '';
    }

    var delta = payload.load_vs_scope_median_percent;
    var deltaText = '';
    if (delta !== null && delta !== undefined && delta !== '') {
      var value = Math.abs(Number(delta));
      deltaText = Number(delta) >= 0
        ? ' · ' + formatPlaceholders(translate('dashboard.extra_insights_scope_above', 'выше медианы на %s'), [value])
        : ' · ' + formatPlaceholders(translate('dashboard.extra_insights_scope_below', 'ниже медианы на %s'), [value]);
    }

    return '<div class="crm-dashboard-insight-legend">'
      + safe(formatPlaceholders(translate('dashboard.extra_insights_scope_median', 'Медиана команды: %s'), [Math.round(Number(median)) + '%']))
      + safe(deltaText) + '</div>';
  }

  // The single sentence that says whether the queue is winning or losing.
  function backlogVerdictHtml(payload) {
    var signal = String(payload.backlog_signal || '');
    if (signal === '') return '';

    var created = Number(payload.created_period || 0);
    var completed = Number(payload.completed_period || 0);
    var days = payload.backlog_days_to_clear;
    var cls = signal === 'growing' ? ' is-warn' : (signal === 'clearing' ? ' is-good' : '');
    var text = signal === 'growing'
      ? translate('dashboard.extra_insights_backlog_growing', 'Бэклог растёт: +%s за период').replace('%s', String(Math.max(0, created - completed)))
      : signal === 'clearing'
        ? translate('dashboard.extra_insights_backlog_clearing', 'Бэклог снижается: %s за период').replace('%s', String(Math.max(0, completed - created)))
        : translate('dashboard.extra_insights_backlog_stable', 'Бэклог стабилен');
    // throughput_per_day is the rate the day estimate is derived from; showing it
    // turns "~30 дн." into a number the reader can sanity-check.
    var pace = Number(payload.throughput_per_day || 0);
    var paceText = pace > 0
      ? ' · ' + formatPlaceholders(translate('dashboard.extra_insights_pace', 'темп %s задач/день'), [pace])
      : '';
    var tail = days === null || days === undefined
      ? translate('dashboard.extra_insights_backlog_no_speed', 'нет завершений для оценки скорости')
      : translate('dashboard.extra_insights_backlog_days', 'разбор текущего объёма ~%s дн.').replace('%s', String(days)) + paceText;

    return '<div class="crm-dashboard-insight-verdict' + cls + '" title="'
      + safe(translate('dashboard.extra_insights_backlog_hint', 'Сравнение созданных и завершённых задач за период')) + '">'
      + '<span>' + safe(text) + '</span><small>' + safe(tail) + '</small></div>';
  }

  function renderMyLoad(container, envelope, definition) {
    var toolbar = insightToolbar(definition, [{
      name: 'period',
      label: translate('dashboard.extra_period', 'Период'),
      value: widgetPeriod(definition),
      options: periodOptions()
    }]);

    if (!envelope || envelope.success === false) {
      container.innerHTML = toolbar + unavailableHtml();
      bindInsightToolbar(container, definition);
      return;
    }

    var payload = insightsPayload(envelope);
    var hasData = Number(payload.active_tasks || 0) || Number(payload.minutes_week || 0)
      || Number(payload.completed_period || 0) || Number(payload.minutes_period || 0);
    if (!hasData) {
      container.innerHTML = toolbar + emptyHtml();
      bindInsightToolbar(container, definition);
      return;
    }

    var daily = Array.isArray(payload.period_daily_minutes) && payload.period_daily_minutes.length
      ? payload.period_daily_minutes
      : (Array.isArray(payload.daily_minutes) ? payload.daily_minutes : []);
    var maxMinutes = 1;
    daily.forEach(function (day) { maxMinutes = Math.max(maxMinutes, Number(day.minutes || 0)); });
    // Long periods come back as multi-day buckets; the tooltip has to name the range
    // the bar stands for, otherwise every bar claims a single day it does not cover.
    var barSpan = function (day) {
      var from = dateText(day.date);
      var to = dateText(day.end_date);
      return to && to !== from ? from + '\u2013' + to : from;
    };
    var bars = daily.map(function (day) {
      var width = Math.max(2, Math.min(100, Math.round(Number(day.minutes || 0) / maxMinutes * 100)));
      return '<div class="crm-dashboard-insight-bar" title="' + safe(barSpan(day) + ' · ' + formatMinutesCompact(day.minutes)) + '">'
        + '<i style="width:' + width + '%"></i></div>';
    }).join('');
    var bucketDays = Number(payload.period_daily_bucket_days || 1);
    var barsLegend = bucketDays > 1
      ? '<div class="crm-dashboard-insight-legend">'
        + safe(formatPlaceholders(translate('dashboard.extra_insights_bucket_days', '1 столбец = %s дн.'), [bucketDays]))
        + '</div>'
      : '';

    var delta = payload.delta_percent || {};
    var hasOnTime = payload.on_time_percent !== null && payload.on_time_percent !== undefined && payload.on_time_percent !== '';
    var onTimeHint = Number(payload.on_time_sample || 0) > 0
      ? ' · ' + translate('dashboard.extra_insights_of_deadlines', 'из %s с дедлайном').replace('%s', String(Number(payload.on_time_sample)))
      : '';
    var onTime = formatPercentOr(payload.on_time_percent, translate('dashboard.extra_insights_no_deadlines', 'нет задач с дедлайном'));
    var capacity = Number(payload.capacity_minutes_week || 2400);
    // "цель", not "норма": the signal label already reads "норма", so a second
    // "норма" on the same tile would just repeat itself.
    var loadValue = Math.round(Number(payload.load_percent || 0)) + '% · ' + signalLabel(payload.load_signal)
      + ' · ' + translate('dashboard.extra_insights_capacity_target', 'цель %s').replace('%s', formatMinutesCompact(capacity))
      + ' (' + capacitySourceLabel(payload.capacity_source) + ')';
    // The two live snapshot tiles lead to the tasks behind them. "Завершено за
    // период" deliberately stays plain: the task list has no "completed in this
    // period" filter, so a link would promise a list it cannot show.
    var me = String(payload.user_public_id || '');
    var activeHref = me ? tasksListUrl({ assignee: me, kpi: 'active' }) : '';
    var overdueHref = me ? tasksListUrl({ assignee: me, kpi: 'overdue' }) : '';

    container.innerHTML = toolbar
      + '<div class="crm-dashboard-insight-grid">'
      + insightTile(translate('dashboard.extra_insights_active_now', 'Активные (сейчас)'), String(Number(payload.active_tasks || 0)), null, false, activeHref)
      + insightTile(translate('dashboard.extra_insights_overdue_now', 'Просрочено (сейчас)'), String(Number(payload.overdue_tasks || 0)), Number(payload.overdue_tasks || 0) > 0 ? 'risk' : null, false, overdueHref)
      + insightTile(translate('dashboard.extra_insights_hours_week', 'Часы за 7 дней'), formatMinutesCompact(payload.minutes_week))
      + insightTile(translate('dashboard.extra_insights_completed_period', 'Завершено за период'), String(Number(payload.completed_period || 0)) + deltaBadge(delta.completed), null, true)
      + insightTile(translate('dashboard.extra_insights_cycle', 'Время выполнения (медиана)'), formatMinutesCompact(payload.cycle_time_median_minutes))
      // No deadline sample is *unknown*, not a risk: a risk marker here would make
      // the card look alarming on a scope that simply has no dated tasks yet.
      + insightTile(translate('dashboard.extra_insights_on_time', 'Вовремя'), onTime + onTimeHint, null)
      + insightTile(translate('dashboard.extra_insights_load', 'Загрузка'), loadValue, payload.load_signal)
      + '</div>'
      + scopeMedianHtml(payload)
      + backlogVerdictHtml(payload)
      + '<div class="crm-dashboard-insight-bars" aria-hidden="true">' + bars + '</div>'
      + barsLegend;

    bindInsightToolbar(container, definition);
  }

  function renderActualTime(container, envelope, definition) {
    var toolbar = insightToolbar(definition, [
      {
        name: 'period',
        label: translate('dashboard.extra_period', 'Период'),
        value: widgetPeriod(definition),
        options: periodOptions()
      },
      {
        name: 'sort',
        label: translate('dashboard.extra_insights_sort', 'Сортировка'),
        value: widgetSort(definition, ['minutes', 'recent'], 'minutes'),
        options: [
          { value: 'minutes', label: translate('dashboard.extra_insights_sort_minutes', 'По времени') },
          { value: 'recent', label: translate('dashboard.extra_insights_sort_recent', 'По свежести') }
        ]
      }
    ]);

    if (!envelope || envelope.success === false) {
      container.innerHTML = toolbar + unavailableHtml();
      bindInsightToolbar(container, definition);
      return;
    }

    var payload = insightsPayload(envelope);
    // The picker is built from the payload, so it can only ever offer projects the
    // server decided this actor may reach - the client never widens the scope.
    var projectOptions = Array.isArray(payload.projects) ? payload.projects : [];
    var selector = '';
    if (projectOptions.length) {
      var currentProject = String(payload.project_filter || '');
      selector = '<select class="form-select form-select-sm crm-dashboard-insight-select" id="dashboardInsightActualProjectSelect" data-insights-project>'
        + '<option value="">' + safe(translate('dashboard.extra_insights_all_projects', 'Все проекты')) + '</option>'
        + projectOptions.map(function (option) {
          var selected = String(option.project_public_id) === currentProject ? ' selected' : '';
          return '<option value="' + safe(option.project_public_id) + '"' + selected + '>' + safe(option.title) + '</option>';
        }).join('')
        + '</select>';
    }
    // A refused narrowing must say so: silently showing the whole scope under a filter
    // the user did set reads as "your project really is everywhere".
    var deniedNotice = payload.project_filter_denied
      ? '<div class="crm-dashboard-insight-legend is-warn">'
        + safe(translate('dashboard.extra_insights_project_denied', 'Проект недоступен: показан весь ваш скоуп')) + '</div>'
      : '';

    var tasks = Array.isArray(payload.top_tasks) ? payload.top_tasks.slice() : [];
    var unloggedCount = Number(payload.active_without_logs || 0);
    var unlogged = Array.isArray(payload.active_without_logs_list) ? payload.active_without_logs_list : [];
    var estimateSets = Array.isArray(payload.estimate_sets) ? payload.estimate_sets : [];
    var withoutEstimate = Array.isArray(payload.tasks_without_estimate) ? payload.tasks_without_estimate : [];

    if (!tasks.length && unloggedCount === 0) {
      container.innerHTML = toolbar + selector
        + '<div class="text-muted small mt-2">' + safe(translate('dashboard.extra_insights_no_logs', 'За выбранный период учёта времени нет')) + '</div>'
        + deniedNotice;
      bindInsightToolbar(container, definition);
      bindStreamProjectSelect(container, definition);
      return;
    }

    if (widgetSort(definition, ['minutes', 'recent'], 'minutes') === 'recent') {
      tasks.sort(function (a, b) {
        return String(b.last_logged_at || '').localeCompare(String(a.last_logged_at || ''))
          || Number(b.minutes || 0) - Number(a.minutes || 0);
      });
    }

    var maxMinutes = 1;
    tasks.forEach(function (task) { maxMinutes = Math.max(maxMinutes, Number(task.minutes || 0)); });
    var rows = tasks.map(function (task) {
      var width = Math.max(2, Math.min(100, Math.round(Number(task.minutes || 0) / maxMinutes * 100)));
      var project = String(task.project_title || '');
      var share = Number(task.share_percent || 0) > 0 ? ' · ' + Number(task.share_percent) + '%' : '';
      return '<div class="crm-dashboard-wl-row">'
        + '<div class="crm-dashboard-wl-head"><span class="text-truncate"><a href="' + safe(taskDetailUrl(task.task_public_id))
        + '" title="' + safe(task.title) + '">' + safe(task.title) + '</a></span>'
        + '<strong>' + safe(formatMinutesCompact(task.minutes)) + '</strong></div>'
        + '<div class="crm-dashboard-time-bar" aria-hidden="true"><i style="width:' + width + '%"></i></div>'
        + '<div class="crm-dashboard-wl-meta"><span>' + safe(project || translate('dashboard.extra_insights_no_project', 'Без проекта')) + '</span>'
        + '<span class="text-muted">' + safe(String(Number(task.sessions || 0))) + ' ' + safe(translate('dashboard.extra_insights_sessions', 'сессий')) + safe(share) + '</span></div>'
        + '</div>';
    }).join('');

    // The unlogged list is the actionable half of `covered_percent`: it names the
    // tasks whose time was never recorded instead of only showing a percentage.
    var unloggedBlock = '';
    if (unloggedCount > 0) {
      var unloggedRows = unlogged.map(function (task) {
        return '<div class="crm-dashboard-extra-row"><div class="text-truncate"><a href="' + safe(taskDetailUrl(task.task_public_id)) + '">'
          + safe(String(task.title || task.task_public_id || '')) + '</a>'
          + (task.due_at ? '<small>' + safe(dateText(task.due_at)) + '</small>' : '') + '</div></div>';
      }).join('');
      // The endpoint caps the list while the count covers every task, so the head has
      // to say how many of them are actually shown - otherwise it claims 30 and
      // lists 5.
      var unloggedHead = unlogged.length > 0 && unlogged.length < unloggedCount
        ? translate('dashboard.extra_insights_unlogged_title', 'Активные задачи без учёта времени') + ': '
          + formatPlaceholders(translate('dashboard.extra_insights_first_of', 'первые %s из %s'), [unlogged.length, unloggedCount])
        : translate('dashboard.extra_insights_unlogged_title', 'Активные задачи без учёта времени') + ': ' + String(unloggedCount);
      unloggedBlock = '<div class="crm-dashboard-insight-alert">'
        + '<div class="crm-dashboard-insight-alert-head">'
        + safe(unloggedHead)
        + '</div>' + unloggedRows
        + '<a class="btn btn-sm crm-btn-secondary mt-2" href="' + safe(tasksListUrl({})) + '">'
        + safe(translate('dashboard.extra_insights_open_tasks', 'Открыть задачи')) + '</a></div>';
    }

    var activities = Array.isArray(payload.by_activity) ? payload.by_activity : [];
    var activityChips = activities.slice(0, 5).map(function (row) {
      return '<span class="crm-chip">' + safe(activityLabel(row.activity_code)) + ' · ' + safe(formatMinutesCompact(row.minutes)) + '</span>';
    }).join(' ');

    // Below the average:
    // - p90 is the "bad case" the average hides (one 1 000 000-minute task on the
    //   demo pulls the mean to tens of thousands while the median stays at 135);
    // - the estimate block answers "does anyone estimate at all, and how far off is
    //   it" - the endpoint reports minutes per point per estimate set, never a
    //   points-to-minutes conversion, because the schema has no such mapping.
    var maxSetTasks = 1;
    estimateSets.forEach(function (set) { maxSetTasks = Math.max(maxSetTasks, Number(set.tasks || 0)); });
    var calibrationRows = estimateSets.map(function (set) {
      var unit = String(set.unit_label || '');
      // Two shapes come out of the endpoint and both are honest: a set measured in
      // hours yields the overrun against the estimate, any other scale yields the
      // cooling rate (logged minutes per one unit of the scale).
      var value;
      if (set.is_time_unit) {
        value = formatMinutesCompact(set.minutes) + ' / ' + formatMinutesCompact(set.estimated_minutes);
      } else if (Number(set.minutes_per_point || 0) > 0) {
        value = formatMinutesCompact(set.minutes_per_point) + (unit ? ' / ' + unit : '');
      } else {
        value = String(translate('dashboard.extra_insights_estimate_no_unit', 'нет данных по часам'));
      }
      var meta = Number(set.tasks || 0) + ' ' + translate('dashboard.extra_insights_estimate_tasks', 'задач с оценкой');
      if (set.is_time_unit) {
        var overrun = Number(set.overrun_percent);
        meta += ' · ' + (overrun > 0 ? '+' : '') + overrun + '% '
          + translate('dashboard.extra_insights_estimate_overrun', 'к оценке');
      } else if (Number(set.points || 0) > 0) {
        meta += ' · ' + String(set.points) + ' ' + safe(unit || translate('dashboard.extra_insights_points', 'ед.'))
          + ' · ' + translate('dashboard.extra_insights_estimate_rate_hint', 'факт на единицу оценки');
      }
      return barRow(String(set.set_name || ''), value, Math.round(Number(set.tasks || 0) / maxSetTasks * 100), meta);
    }).join('');
    var calibrationBlock = estimateSets.length
      ? '<div class="crm-dashboard-insight-section-title">'
        + safe(translate('dashboard.extra_insights_estimate_calibration', 'Оценка против факта (часы на единицу оценки)')) + '</div>'
        + calibrationRows
      : '';

    var withoutEstimateTotal = Number(payload.tasks_without_estimate_total || withoutEstimate.length);
    var withoutEstimateHead = withoutEstimate.length > 0 && withoutEstimate.length < withoutEstimateTotal
      ? formatPlaceholders(translate('dashboard.extra_insights_first_of', 'первые %s из %s'), [withoutEstimate.length, withoutEstimateTotal])
      : String(withoutEstimateTotal);
    var withoutEstimateBlock = withoutEstimate.length
      ? '<div class="crm-dashboard-insight-alert">'
        + '<div class="crm-dashboard-insight-alert-head">'
        + safe(translate('dashboard.extra_insights_without_estimate', 'Задачи с учётом времени, но без оценки') + ': ' + withoutEstimateHead)
        + '</div>'
        + withoutEstimate.map(function (task) {
          return '<div class="crm-dashboard-extra-row"><div class="text-truncate"><a href="' + safe(taskDetailUrl(task.task_public_id))
            + '" title="' + safe(task.title) + '">' + safe(String(task.title || task.task_public_id || '')) + '</a>'
            + '<small>' + safe(formatMinutesCompact(task.minutes)) + '</small></div></div>';
        }).join('')
        + '</div>'
      : '';

    var coverageValue = payload.estimate_coverage_percent === null || payload.estimate_coverage_percent === undefined
      ? translate('dashboard.extra_insights_no_data', 'нет данных')
      : Number(payload.estimate_coverage_percent) + '%';

    container.innerHTML = toolbar
      + (selector ? '<div class="crm-dashboard-insight-stream-head">' + selector + '</div>' : '')
      + deniedNotice
      + '<div class="crm-dashboard-insight-grid">'
      + insightTile(translate('dashboard.extra_insights_total', 'Всего часов'), formatMinutesCompact(payload.total_minutes))
      + insightTile(translate('dashboard.extra_insights_median', 'Медиана на задачу'), formatMinutesCompact(payload.median_minutes))
      + insightTile(translate('dashboard.extra_insights_p90', 'p90 на задачу'), formatMinutesCompact(payload.p90_minutes))
      + insightTile(translate('dashboard.extra_insights_max', 'Максимум на задачу'), formatMinutesCompact(payload.max_minutes))
      + insightTile(translate('dashboard.extra_insights_average', 'Среднее на задачу'), formatMinutesCompact(payload.average_minutes))
      + insightTile(translate('dashboard.extra_insights_covered', 'Задач с ворклогами'), Number(payload.covered_percent || 0) + '%', unloggedCount > 0 ? 'risk' : null)
      + insightTile(translate('dashboard.extra_insights_estimate_coverage', 'Задач с оценкой'), coverageValue,
        Number(payload.estimate_coverage_percent) > 0 && Number(payload.estimate_coverage_percent) < 50 ? 'risk' : null)
      + '</div>'
      // "Only live tasks" is stated, not assumed: without it a manager who deleted a
      // task would see hours disappear from the card and read it as a bug.
      + '<div class="crm-dashboard-insight-legend">'
      + safe(translate('dashboard.extra_insights_p90_hint', 'p90 = 90% задач быстрее этого значения; среднее искажают выбросы'))
      + (Number(payload.tasks_with_logs || 0) > 0
        ? ' · ' + safe(formatPlaceholders(translate('dashboard.extra_insights_percentile_sample', 'выборка: %s задач с учётом'), [Number(payload.tasks_with_logs || 0)]))
        : '')
      + ' · ' + safe(translate('dashboard.extra_insights_live_tasks_note', 'учитываются только неудалённые и неархивные задачи'))
      + '</div>'
      + rows
      + calibrationBlock
      + (activityChips ? '<div class="crm-dashboard-insight-chips">' + activityChips + '</div>' : '')
      + unloggedBlock
      + withoutEstimateBlock;

    bindInsightToolbar(container, definition);
    bindStreamProjectSelect(container, definition);
  }

  function signalClass(signal) {
    if (signal === 'overload' || signal === 'critical') return ' is-over';
    if (signal === 'risk' || signal === 'underload') return ' is-warn';
    return '';
  }

  function signalLabel(signal) {
    var map = {
      overload: translate('dashboard.extra_insights_overload', 'перегруз'),
      underload: translate('dashboard.extra_insights_underload', 'недогруз'),
      risk: translate('dashboard.extra_insights_risk', 'риск'),
      critical: translate('dashboard.extra_insights_critical', 'критично'),
      normal: translate('dashboard.extra_insights_normal', 'норма'),
      ok: translate('dashboard.extra_insights_ok', 'норма'),
      no_data: translate('dashboard.extra_insights_no_data', 'нет данных')
    };
    return map[signal] || map.normal;
  }

  function barRow(label, value, width, meta) {
    return '<div class="crm-dashboard-wl-row">'
      + '<div class="crm-dashboard-wl-head"><span class="text-truncate" title="' + safe(label) + '">' + safe(label) + '</span><strong>' + safe(value) + '</strong></div>'
      + '<div class="crm-dashboard-time-bar" aria-hidden="true"><i style="width:' + Math.max(2, Math.min(100, Math.round(width))) + '%"></i></div>'
      + (meta ? '<div class="crm-dashboard-wl-meta"><span>' + safe(meta) + '</span></div>' : '')
      + '</div>';
  }

  function insightsTable(headers, rows) {
    if (!rows.length) return '';
    var head = headers.map(function (h) { return '<th>' + safe(h) + '</th>'; }).join('');
    var body = rows.map(function (cells) {
      return '<tr>' + cells.map(function (cell) { return '<td>' + cell + '</td>'; }).join('') + '</tr>';
    }).join('');
    return '<div class="table-responsive"><table class="table table-sm crm-dashboard-insight-table"><thead><tr>'
      + head + '</tr></thead><tbody>' + body + '</tbody></table></div>';
  }

  function renderKpi(container, envelope, definition) {
    var toolbar = insightToolbar(definition, [{
      name: 'period',
      label: translate('dashboard.extra_period', 'Период'),
      value: widgetPeriod(definition),
      options: periodOptions()
    }]);

    if (!envelope || envelope.success === false) {
      container.innerHTML = toolbar + unavailableHtml();
      bindInsightToolbar(container, definition);
      return;
    }

    var payload = insightsPayload(envelope);
    var hasData = Number(payload.completed || 0) || Number(payload.minutes || 0) || Number(payload.overdue || 0)
      || Number(payload.streak_days || 0);
    if (!hasData) {
      container.innerHTML = toolbar + emptyHtml();
      bindInsightToolbar(container, definition);
      return;
    }

    var delta = payload.delta_percent || {};
    var deltaCell = function (value) {
      if (value === null || value === undefined) return '—';
      var num = Number(value || 0);
      var cls = num > 0 ? 'is-up' : (num < 0 ? 'is-down' : '');
      return '<span class="crm-dashboard-insight-delta ' + cls + '">' + (num > 0 ? '+' : '') + num + '%</span>';
    };
    var link = function (label, href) {
      return '<a href="' + safe(href) + '">' + safe(label) + '</a>';
    };
    var hasOnTime = payload.on_time_percent !== null && payload.on_time_percent !== undefined && payload.on_time_percent !== '';
    var onTimeValue = hasOnTime
      ? Number(payload.on_time_percent) + '%' + (Number(payload.on_time_sample || 0) > 0
        ? ' <small class="text-muted">' + safe(translate('dashboard.extra_insights_of_deadlines', 'из %s с дедлайном').replace('%s', String(Number(payload.on_time_sample)))) + '</small>'
        : '')
      : safe(translate('dashboard.extra_insights_no_deadlines_short', 'нет дедлайнов'));

    var rows = [
      [safe(translate('dashboard.extra_insights_completed', 'Завершено')), '<strong>' + safe(String(Number(payload.completed || 0))) + '</strong>', deltaCell(delta.completed)],
      [safe(translate('dashboard.extra_insights_hours', 'Часы')), '<strong>' + safe(formatMinutesCompact(payload.minutes)) + '</strong>', deltaCell(delta.minutes)],
      [safe(translate('dashboard.extra_insights_on_time', 'Вовремя')), '<strong>' + onTimeValue + '</strong>', deltaCell(payload.trend_percent)],
      [safe(translate('dashboard.extra_insights_average', 'Среднее на задачу')), '<strong>' + safe(formatMinutesCompact(payload.average_minutes_per_task)) + '</strong>', '—'],
      [safe(translate('dashboard.extra_insights_overdue_now', 'Просрочено (сейчас)')), '<strong>' + link(String(Number(payload.overdue || 0)), tasksListUrl({ due: 'overdue' })) + '</strong>', '—'],
      [safe(translate('dashboard.extra_insights_stale', 'Без движения > 7 дней')), '<strong>' + safe(Number(payload.stale_share_percent || 0) + '%') + '</strong>', '—'],
      [safe(translate('dashboard.extra_insights_streak', 'Дней подряд с ворклогами')), '<strong>' + safe(String(Number(payload.streak_days || 0))) + '</strong>', '—']
    ];

    var weekly = Array.isArray(payload.weekly) ? payload.weekly : [];
    var maxCompleted = 1;
    weekly.forEach(function (week) { maxCompleted = Math.max(maxCompleted, Number(week.completed || 0)); });
    var bars = weekly.map(function (week) {
      var width = Math.max(2, Math.min(100, Math.round(Number(week.completed || 0) / maxCompleted * 100)));
      return '<div class="crm-dashboard-insight-bar" title="' + safe(dateText(week.week_start) + ' · ' + formatMinutesCompact(week.minutes)) + '">'
        + '<i style="width:' + width + '%"></i></div>';
    }).join('');

    container.innerHTML = toolbar
      + (bars ? '<div class="crm-dashboard-insight-bars" aria-hidden="true">' + bars + '</div>' : '')
      + insightsTable(
        [
          safe(translate('dashboard.extra_insights_metric', 'Метрика')),
          safe(translate('dashboard.extra_insights_value', 'Значение')),
          safe(translate('dashboard.extra_insights_delta_short', 'Δ 4 нед.'))
        ],
        rows
      )
      + '<div class="crm-dashboard-insight-legend">'
      + safe(translate('dashboard.extra_insights_legend_snapshot', '«сейчас» — снимок на текущий момент, остальное — за выбранный период'))
      + '</div>';

    bindInsightToolbar(container, definition);
  }

  // `no_data` sorts last on purpose: somebody with nothing assigned yet is not a
  // workload problem and must not jump above a real overload.
  var SIGNAL_ORDER = { overload: 0, critical: 0, risk: 1, normal: 2, ok: 2, underload: 3, no_data: 4 };

  function signalRank(signal) {
    var key = String(signal || '').trim().toLowerCase();
    return SIGNAL_ORDER[key] === undefined ? 2 : SIGNAL_ORDER[key];
  }

  // "Who needs help first": the default order is by severity, not alphabetically.
  function sortedAssigneeRows(list, sort) {
    var rows = list.slice();
    rows.sort(function (a, b) {
      if (sort === 'load') return Number(b.load_percent || 0) - Number(a.load_percent || 0);
      if (sort === 'overdue') return Number(b.overdue_tasks || 0) - Number(a.overdue_tasks || 0);
      if (sort === 'name') return String(a.full_name || a.login || '').localeCompare(String(b.full_name || b.login || ''));
      return signalRank(a.signal) - signalRank(b.signal)
        || Number(b.overdue_tasks || 0) - Number(a.overdue_tasks || 0)
        || Number(b.load_percent || 0) - Number(a.load_percent || 0);
    });
    return rows;
  }

  function assigneeRows(list, options) {
    options = options || {};
    return list.map(function (row) {
      var name = String(row.full_name || row.login || row.user_public_id || '');
      var label = options.plain
        ? safe(name)
        : '<a href="' + safe(tasksListUrl({ assignee: row.user_public_id })) + '" title="'
          + safe(translate('dashboard.extra_insights_person_tasks', 'Задачи сотрудника')) + '">' + safe(name) + '</a>';
      return [
        label,
        safe(String(Number(row.active_tasks || 0))),
        safe(String(Number(row.overdue_tasks || 0))),
        safe(formatMinutesCompact(row.minutes_week)),
        safe(Number(row.load_percent || 0) + '%'),
        '<span class="crm-dashboard-wl-signal' + signalClass(row.signal) + '">' + safe(signalLabel(row.signal)) + '</span>'
      ];
    });
  }

  var ASSIGNEE_HEADERS = function () {
    return [
      safe(translate('dashboard.extra_insights_person', 'Сотрудник')),
      safe(translate('dashboard.extra_insights_active', 'Активные')),
      safe(translate('dashboard.extra_insights_overdue', 'Просрочено')),
      safe(translate('dashboard.extra_insights_hours_week', 'Часы за 7 дней')),
      safe(translate('dashboard.extra_insights_load', 'Загрузка')),
      safe(translate('dashboard.extra_insights_signal', 'Сигнал'))
    ];
  };

  var DEPARTMENT_HEADERS = function () {
    return [
      safe(translate('dashboard.extra_insights_department', 'Отдел')),
      safe(translate('dashboard.extra_insights_members', 'Участники')),
      safe(translate('dashboard.extra_insights_active', 'Активные')),
      safe(translate('dashboard.extra_insights_overdue', 'Просрочено')),
      safe(translate('dashboard.extra_insights_hours_week', 'Часы за 7 дней')),
      safe(translate('dashboard.extra_insights_load', 'Загрузка')),
      safe(translate('dashboard.extra_insights_signal', 'Сигнал'))
    ];
  };

  // A department's members are rendered as links instead of a "drill-down" filter:
  // the task list has no department filter (departments have no membership column),
  // so the honest destination for "who is in this department" is each person.
  function departmentTitleCell(department) {
    var members = Array.isArray(department.members) ? department.members.slice(0, 5) : [];
    var links = members.map(function (member) {
      var name = String(member.full_name || member.login || member.user_public_id || '');
      return '<a href="' + safe(tasksListUrl({ assignee: member.user_public_id })) + '">' + safe(name) + '</a>';
    }).join(', ');
    var more = Number(department.members_count || 0) - members.length;
    var tail = more > 0 ? ' + ' + safe(String(more)) : '';
    var noManager = department.no_manager
      ? '<div class="crm-dashboard-wl-signal is-warn">' + safe(translate('dashboard.extra_insights_department_no_manager', 'нет руководителя — состав не определён')) + '</div>'
      : '';
    var membersLine = links !== ''
      ? '<div class="text-muted small text-truncate">' + links + tail + '</div>'
      : '';

    return safe(String(department.title || '')) + noManager + membersLine;
  }

  function departmentRows(departments) {
    return sortedAssigneeRows(departments, 'signal').map(function (department) {
      return [
        departmentTitleCell(department),
        safe(String(Number(department.members_count || 0))),
        safe(String(Number(department.active_tasks || 0))),
        safe(String(Number(department.overdue_tasks || 0))),
        safe(formatMinutesCompact(department.minutes_week)),
        safe(Number(department.load_percent || 0) + '%'),
        '<span class="crm-dashboard-wl-signal' + signalClass(department.signal) + '">' + safe(signalLabel(department.signal)) + '</span>'
      ];
    });
  }

  // The legend has to name the capacity that is actually in force, otherwise it
  // keeps claiming "40 h/week" after an admin set the org's own week.
  function signalLegendHtml(payload) {
    payload = payload || {};
    var capacity = Number(payload.capacity_minutes_week || 2400);
    var source = capacitySourceLabel(payload.capacity_source);
    var base = translate('dashboard.extra_insights_legend_signals', 'Перегруз > 110% загрузки, недогруз < 50% без просрочек, риск — есть просрочка. {capacity}.');
    var capacityText = formatPlaceholders(
      translate('dashboard.extra_insights_legend_capacity', 'Норма — %s/нед, источник: %s'),
      [formatMinutesCompact(capacity), source]
    );

    return '<div class="crm-dashboard-insight-legend">'
      + safe(base.replace('{capacity}', capacityText))
      + '</div>';
  }

  function limitStateHtml(definition, total, visible) {
    if (widgetExpanded(definition) || total <= visible) return '';
    return '<button type="button" class="btn btn-sm crm-btn-secondary mt-2" data-insight-option="expanded" data-insight-option-value="1">'
      + safe(translate('dashboard.extra_insights_show_all', 'Показать всех') + ' (' + String(total) + ')') + '</button>';
  }

  function renderAssigneeLoad(container, envelope, definition) {
    var toolbar = insightToolbar(definition, [
      {
        name: 'period',
        label: translate('dashboard.extra_period', 'Период'),
        value: widgetPeriod(definition),
        options: periodOptions()
      },
      {
        name: 'sort',
        label: translate('dashboard.extra_insights_sort', 'Сортировка'),
        value: widgetSort(definition, ['signal', 'load', 'overdue', 'name'], 'signal'),
        options: [
          { value: 'signal', label: translate('dashboard.extra_insights_sort_risk', 'По риску') },
          { value: 'load', label: translate('dashboard.extra_insights_sort_load', 'По загрузке') },
          { value: 'overdue', label: translate('dashboard.extra_insights_sort_overdue', 'По просрочке') },
          { value: 'name', label: translate('dashboard.extra_insights_sort_name', 'По имени') }
        ]
      }
    ]);

    if (!envelope || envelope.success === false) {
      container.innerHTML = toolbar + unavailableHtml();
      bindInsightToolbar(container, definition);
      return;
    }

    var payload = insightsPayload(envelope);
    var assignees = Array.isArray(payload.assignees) ? payload.assignees : [];
    var departments = Array.isArray(payload.departments) ? payload.departments : [];
    if (!assignees.length && !departments.length) {
      container.innerHTML = toolbar + emptyHtml();
      bindInsightToolbar(container, definition);
      return;
    }

    var sort = widgetSort(definition, ['signal', 'load', 'overdue', 'name'], 'signal');
    var ordered = sortedAssigneeRows(assignees, sort);
    var visible = widgetExpanded(definition) ? ordered : ordered.slice(0, 10);
    var hidden = ordered.length - visible.length;

    var departmentsBlock = '';
    if (departments.length) {
      departmentsBlock = '<div class="crm-dashboard-insight-section-title">'
        + safe(translate('dashboard.extra_insights_departments', 'Отделы')) + '</div>'
        + insightsTable(DEPARTMENT_HEADERS(), departmentRows(departments));
    }

    var peopleBlock = '<div class="crm-dashboard-insight-section-title">'
      + safe(translate('dashboard.extra_insights_people_title', 'Сотрудники'))
      + (hidden > 0 ? ' <span class="text-muted">' + safe(formatPlaceholders(
        translate('dashboard.extra_insights_first_of', 'первые %s из %s'), [visible.length, ordered.length]
      )) + '</span>' : '')
      + '</div>'
      + insightsTable(ASSIGNEE_HEADERS(), assigneeRows(visible))
      + limitStateHtml(definition, ordered.length, 10);

    container.innerHTML = toolbar + departmentsBlock + peopleBlock + signalLegendHtml(payload);
    bindInsightToolbar(container, definition);
  }

  function renderVelocity(container, envelope, definition) {
    var toolbar = insightToolbar(definition, [{
      name: 'period',
      label: translate('dashboard.extra_period', 'Период'),
      value: widgetPeriod(definition),
      options: periodOptions()
    }]);

    if (!envelope || envelope.success === false) {
      container.innerHTML = toolbar + unavailableHtml();
      bindInsightToolbar(container, definition);
      return;
    }

    var payload = insightsPayload(envelope);
    var weeks = Array.isArray(payload.weeks) ? payload.weeks : [];
    if (!weeks.length) {
      container.innerHTML = toolbar + emptyHtml();
      bindInsightToolbar(container, definition);
      return;
    }

    var max = 1;
    weeks.forEach(function (week) { max = Math.max(max, Number(week.completed || 0)); });
    var bars = weeks.map(function (week) {
      var width = Math.round(Number(week.completed || 0) / max * 100);
      var meta = translate('dashboard.extra_insights_wip', 'В работе') + ': ' + Number(week.wip || 0)
        + ' · ' + translate('dashboard.extra_insights_opened', 'создано') + ': ' + Number(week.opened || 0);
      return barRow(dateText(week.week_start), String(Number(week.completed || 0)), width, meta);
    }).join('');

    // Forecast is deliberately worded as an estimate: it is a linear projection of
    // the last four weeks, not a commitment.
    var forecast = payload.forecast || {};
    var forecastBlock = '';
    if (forecast.weeks_to_finish !== null && forecast.weeks_to_finish !== undefined) {
      forecastBlock = '<div class="crm-dashboard-insight-forecast">'
        + '<div class="crm-dashboard-insight-forecast-head">'
        + safe(translate('dashboard.extra_insights_forecast_title', 'Прогноз по среднему темпу за 4 недели')) + '</div>'
        + '<div class="crm-dashboard-insight-forecast-body">'
        + safe(translate('dashboard.extra_insights_forecast_speed', 'Средний темп')) + ': <strong>' + safe(String(Number(forecast.average_per_week || 0)))
        + '</strong> ' + safe(translate('dashboard.extra_insights_per_week', 'задач/нед'))
        + ' · ' + safe(translate('dashboard.extra_insights_forecast_open', 'в работе')) + ': <strong>' + safe(String(Number(forecast.open_tasks || 0))) + '</strong>'
        + ' · ≈ ' + safe(String(Number(forecast.weeks_to_finish))) + ' ' + safe(translate('dashboard.extra_insights_weeks', 'нед.'))
        + (forecast.finish_date ? ' · ' + safe(translate('dashboard.extra_insights_forecast_date', 'финиш ~%s').replace('%s', dateText(forecast.finish_date))) : '')
        + '</div></div>';
    }

    var wipTrend = String(payload.wip_trend || 'stable');
    var wipHint = wipTrend === 'growing'
      ? translate('dashboard.extra_insights_wip_growing', 'растёт')
      : (wipTrend === 'shrinking' ? translate('dashboard.extra_insights_wip_shrinking', 'снижается') : translate('dashboard.extra_insights_wip_stable', 'стабильно'));

    container.innerHTML = toolbar
      + '<div class="crm-dashboard-insight-grid">'
      // Older cached payloads predate cycle_time_median_period_minutes; fall back to
      // the all-history median rather than showing a false 0.
      + insightTile(translate('dashboard.extra_insights_cycle_median_period', 'Cycle time (медиана, период)'), formatMinutesCompact(
        payload.cycle_time_median_period_minutes !== undefined && payload.cycle_time_median_period_minutes !== null
          ? payload.cycle_time_median_period_minutes
          : payload.cycle_time_median_minutes
      ))
      + insightTile(translate('dashboard.extra_insights_cycle_p90', 'Cycle time (p90)'), formatMinutesCompact(payload.cycle_time_p90_minutes))
      + insightTile(translate('dashboard.extra_insights_wip', 'В работе'), String(Number(payload.wip || 0)) + ' · ' + wipHint, wipTrend === 'growing' ? 'risk' : null)
      + insightTile('Δ ' + translate('dashboard.extra_insights_throughput', 'Throughput') + ' ' + translate('dashboard.extra_insights_four_weeks', '4 нед.'), Number(payload.trend_percent || 0) + '%')
      + '</div>'
      + forecastBlock
      + bars;

    bindInsightToolbar(container, definition);
  }

  function renderWorkloadMgmt(container, envelope, definition) {
    var toolbar = insightToolbar(definition, [
      {
        name: 'period',
        label: translate('dashboard.extra_period', 'Период'),
        value: widgetPeriod(definition),
        options: periodOptions()
      },
      {
        name: 'sort',
        label: translate('dashboard.extra_insights_sort', 'Сортировка'),
        value: widgetSort(definition, ['signal', 'load', 'overdue', 'name'], 'signal'),
        options: [
          { value: 'signal', label: translate('dashboard.extra_insights_sort_risk', 'По риску') },
          { value: 'load', label: translate('dashboard.extra_insights_sort_load', 'По загрузке') },
          { value: 'overdue', label: translate('dashboard.extra_insights_sort_overdue', 'По просрочке') }
        ]
      }
    ]);

    if (!envelope || envelope.success === false) {
      container.innerHTML = toolbar + unavailableHtml();
      bindInsightToolbar(container, definition);
      return;
    }

    var payload = insightsPayload(envelope);
    var assignees = Array.isArray(payload.assignees) ? payload.assignees : [];
    var departments = Array.isArray(payload.departments) ? payload.departments : [];
    var summary = payload.summary || {};
    if (!assignees.length && !departments.length) {
      container.innerHTML = toolbar + emptyHtml();
      bindInsightToolbar(container, definition);
      return;
    }

    // The recommendation is the only actionable part of this widget: without it the
    // card only reports numbers and leaves the manager to do the math.
    var recommendationBlock = '';
    var recommendation = payload.recommendation;
    if (recommendation && recommendation.from && recommendation.to) {
      recommendationBlock = '<div class="crm-dashboard-insight-recommendation">'
        + '<div class="crm-dashboard-insight-alert-head">' + safe(translate('dashboard.extra_insights_rebalance_title', 'Рекомендация по ребалансировке')) + '</div>'
        + '<div class="crm-dashboard-insight-recommendation-body">'
        + safe(translate('dashboard.extra_insights_rebalance_body', 'Передать %tasks задач: от %from к %to').replace('%tasks', String(Number(recommendation.tasks || 0)))
          .replace('%from', String(recommendation.from.name || ''))
          .replace('%to', String(recommendation.to.name || '')))
        + '</div>'
        + '<div class="crm-dashboard-insight-recommendation-links">'
        + '<a href="' + safe(tasksListUrl({ assignee: recommendation.from.user_public_id })) + '">' + safe(translate('dashboard.extra_insights_person_tasks', 'Задачи сотрудника')) + ': ' + safe(String(recommendation.from.name || '')) + '</a>'
        + ' · <a href="' + safe(tasksListUrl({ assignee: recommendation.to.user_public_id })) + '">' + safe(String(recommendation.to.name || '')) + '</a>'
        + '</div></div>';
    }

    var sort = widgetSort(definition, ['signal', 'load', 'overdue', 'name'], 'signal');
    var ordered = sortedAssigneeRows(assignees, sort);
    var visible = widgetExpanded(definition) ? ordered : ordered.slice(0, 10);
    var hidden = ordered.length - visible.length;

    var departmentsBlock = departments.length
      ? '<div class="crm-dashboard-insight-section-title">' + safe(translate('dashboard.extra_insights_departments', 'Отделы')) + '</div>'
        + insightsTable(DEPARTMENT_HEADERS(), departmentRows(departments))
      : '';

    var peopleRows = visible.map(function (row) {
      return [
        '<a href="' + safe(tasksListUrl({ assignee: row.user_public_id })) + '">' + safe(String(row.full_name || row.login || row.user_public_id || '')) + '</a>',
        safe(Number(row.load_percent || 0) + '%'),
        safe(Number(row.efficiency_percent || 0) + '%'),
        safe(String(Number(row.overdue_tasks || 0))),
        safe(String(Number(row.active_tasks || 0))),
        '<span class="crm-dashboard-wl-signal' + signalClass(row.signal) + '">' + safe(signalLabel(row.signal)) + '</span>'
      ];
    });

    var peopleBlock = '<div class="crm-dashboard-insight-section-title">'
      + safe(translate('dashboard.extra_insights_people_title', 'Сотрудники'))
      + (hidden > 0 ? ' <span class="text-muted">' + safe(formatPlaceholders(
        translate('dashboard.extra_insights_first_of', 'первые %s из %s'), [visible.length, ordered.length]
      )) + '</span>' : '')
      + '</div>'
      + insightsTable([
        safe(translate('dashboard.extra_insights_person', 'Сотрудник')),
        safe(translate('dashboard.extra_insights_load', 'Загрузка')),
        safe(translate('dashboard.extra_insights_efficiency', 'Эффективность')),
        safe(translate('dashboard.extra_insights_overdue', 'Просрочено')),
        safe(translate('dashboard.extra_insights_active', 'Активные')),
        safe(translate('dashboard.extra_insights_signal', 'Сигнал'))
      ], peopleRows)
      + limitStateHtml(definition, ordered.length, 10);

    container.innerHTML = toolbar
      + '<div class="crm-dashboard-insight-grid">'
      + insightTile(translate('dashboard.extra_insights_people', 'Сотрудников'), String(Number(summary.people || 0)))
      + insightTile(translate('dashboard.extra_insights_overload', 'перегруз'), String(Number(summary.overload || 0)), 'overload')
      + insightTile(translate('dashboard.extra_insights_underload', 'недогруз'), String(Number(summary.underload || 0)))
      + insightTile(translate('dashboard.extra_insights_risk', 'риск'), String(Number(summary.risk || 0)), 'risk')
      + insightTile(translate('dashboard.extra_insights_avg_load', 'Средняя загрузка'), Number(summary.average_load_percent || 0) + '%')
      + insightTile(translate('dashboard.extra_insights_avg_efficiency', 'Средняя эффективность'), Number(summary.average_efficiency_percent || 0) + '%')
      + '</div>'
      + recommendationBlock
      + departmentsBlock
      + peopleBlock
      + signalLegendHtml(payload);

    bindInsightToolbar(container, definition);
  }

  // Projects are ordered client-side so the same payload can answer "what is at
  // risk" and "where did the hours go" without another request.
  function sortedProjectRows(list, sort) {
    var rows = list.slice();
    rows.sort(function (a, b) {
      if (sort === 'hours') return Number(b.minutes_period || 0) - Number(a.minutes_period || 0);
      if (sort === 'progress') return Number(b.progress_percent || 0) - Number(a.progress_percent || 0);
      if (sort === 'overdue') return Number(b.overdue_tasks || 0) - Number(a.overdue_tasks || 0);
      return signalRank(a.health) - signalRank(b.health)
        || Number(b.overdue_tasks || 0) - Number(a.overdue_tasks || 0);
    });
    return rows;
  }

  function milestoneCellHtml(project, nowMs) {
    var due = String(project.next_milestone_at || '').trim();
    if (due === '') return '<span class="text-muted">' + safe(translate('dashboard.extra_insights_no_milestone', 'нет вехи')) + '</span>';
    var ts = Date.parse(due.replace(' ', 'T'));
    var overdue = Number.isFinite(ts) && ts < nowMs && Number(project.active_tasks || 0) > 0;
    return '<span class="' + (overdue ? 'is-overdue' : '') + '" title="'
      + safe(translate('dashboard.extra_insights_next_milestone', 'Ближайшая веха проекта')) + '">' + safe(dateText(due)) + '</span>';
  }

  function renderStreams(container, envelope, definition) {
    var toolbar = insightToolbar(definition, [
      {
        name: 'period',
        label: translate('dashboard.extra_period', 'Период'),
        value: widgetPeriod(definition),
        options: periodOptions()
      },
      {
        name: 'sort',
        label: translate('dashboard.extra_insights_sort', 'Сортировка'),
        value: widgetSort(definition, ['risk', 'overdue', 'hours', 'progress'], 'risk'),
        options: [
          { value: 'risk', label: translate('dashboard.extra_insights_sort_risk', 'По риску') },
          { value: 'overdue', label: translate('dashboard.extra_insights_sort_overdue', 'По просрочке') },
          { value: 'hours', label: translate('dashboard.extra_insights_sort_hours', 'По часам') },
          { value: 'progress', label: translate('dashboard.extra_insights_sort_progress', 'По прогрессу') }
        ]
      }
    ]);

    if (!envelope || envelope.success === false) {
      container.innerHTML = toolbar + unavailableHtml();
      bindInsightToolbar(container, definition);
      return;
    }

    var payload = insightsPayload(envelope);
    var projects = Array.isArray(payload.projects) ? payload.projects : [];
    if (!projects.length) {
      container.innerHTML = toolbar + emptyHtml();
      bindInsightToolbar(container, definition);
      return;
    }

    var sort = widgetSort(definition, ['risk', 'overdue', 'hours', 'progress'], 'risk');
    var ordered = sortedProjectRows(projects, sort);
    var visible = widgetExpanded(definition) ? ordered : ordered.slice(0, 10);
    var hidden = ordered.length - visible.length;
    var nowMs = Date.now();

    var rows = visible.map(function (project) {
      var title = String(project.title || project.project_public_id || '');
      var overdue = Number(project.overdue_tasks || 0);
      return [
        '<a href="' + safe(projectDetailUrl(project.project_public_id)) + '">' + safe(title) + '</a>',
        progressBar(project.progress_percent) + ' <small>' + safe(Number(project.progress_percent || 0) + '%') + '</small>',
        safe(String(Number(project.active_tasks || 0))),
        overdue > 0
          ? '<a class="is-overdue" href="' + safe(tasksListUrl({ project: project.project_public_id, due: 'overdue' })) + '">' + safe(String(overdue)) + '</a>'
          : safe('0'),
        safe(formatMinutesCompact(project.minutes_period)),
        safe(String(Number(project.members || 0))),
        milestoneCellHtml(project, nowMs),
        '<span class="crm-dashboard-wl-signal' + signalClass(project.health) + '">' + safe(signalLabel(project.health)) + '</span>'
      ];
    });

    container.innerHTML = toolbar
      + (hidden > 0 ? '<div class="text-muted small mb-1">' + safe(formatPlaceholders(
        translate('dashboard.extra_insights_first_of', 'первые %s из %s'), [visible.length, ordered.length]
      )) + '</div>' : '')
      + insightsTable([
        safe(translate('dashboard.extra_insights_project', 'Поток (проект)')),
        safe(translate('dashboard.extra_insights_progress', 'Прогресс')),
        safe(translate('dashboard.extra_insights_active', 'Активные')),
        safe(translate('dashboard.extra_insights_overdue', 'Просрочено')),
        safe(translate('dashboard.extra_insights_hours', 'Часы')),
        safe(translate('dashboard.extra_insights_members', 'Участники')),
        safe(translate('dashboard.extra_insights_milestone', 'Ближайшая веха')),
        safe(translate('dashboard.extra_insights_signal', 'Сигнал'))
      ], rows)
      + limitStateHtml(definition, ordered.length, 10)
      + '<div class="crm-dashboard-insight-legend">'
      + safe(translate('dashboard.extra_insights_legend_streams', 'Критично — просрочено более 20% активных задач или веха в прошлом; риск — есть просрочка или веха на этой неделе.'))
      + '</div>';

    bindInsightToolbar(container, definition);
  }

  function renderStreamDetail(container, envelope, definition) {
    var toolbar = insightToolbar(definition, [{
      name: 'period',
      label: translate('dashboard.extra_period', 'Период'),
      value: widgetPeriod(definition),
      options: periodOptions()
    }]);

    if (!envelope || envelope.success === false) {
      container.innerHTML = toolbar + unavailableHtml();
      bindInsightToolbar(container, definition);
      return;
    }
    var payload = insightsPayload(envelope);
    var project = payload.project || null;
    var options = Array.isArray(payload.projects) ? payload.projects : [];
    var selector = '';
    if (options.length) {
      // The id is registered in page-api-bindings' searchable-select list, so the
      // existing makeSelectSearchable() handles it - no second implementation here.
      selector = '<select class="form-select form-select-sm crm-dashboard-insight-select" id="dashboardInsightStreamSelect" data-insights-project>'
        + options.map(function (option) {
          var selected = project && option.project_public_id === project.project_public_id ? ' selected' : '';
          return '<option value="' + safe(option.project_public_id) + '"' + selected + '>' + safe(option.title) + '</option>';
        }).join('')
        + '</select>';
    }
    if (!project) {
      container.innerHTML = toolbar + selector + '<div class="text-muted small mt-2">'
        + safe(translate('dashboard.extra_insights_no_streams', 'Нет доступных потоков')) + '</div>';
      bindInsightToolbar(container, definition);
      bindStreamProjectSelect(container, definition);
      return;
    }
    var weeks = Array.isArray(project.throughput_weeks) ? project.throughput_weeks : [];
    var max = 1;
    weeks.forEach(function (week) { max = Math.max(max, Number(week.completed || 0)); });
    var bars = weeks.map(function (week) {
      return barRow(dateText(week.week_start), String(Number(week.completed || 0)), Math.round(Number(week.completed || 0) / max * 100), '');
    }).join('');
    var statuses = (project.by_status || []).map(function (row) {
      return '<span class="crm-chip">' + safe(row.status_code) + ' · ' + safe(String(Number(row.tasks || 0))) + '</span>';
    }).join(' ');
    var members = (project.top_members || []).map(function (row) {
      var name = String(row.full_name || row.login || '');
      var label = row.user_public_id
        ? '<a href="' + safe(tasksListUrl({ assignee: row.user_public_id })) + '">' + safe(name) + '</a>'
        : safe(name);
      var width = 100;
      return '<div class="crm-dashboard-wl-row"><div class="crm-dashboard-wl-head"><span class="text-truncate">' + label
        + '</span><strong>' + safe(formatMinutesCompact(row.minutes)) + '</strong></div>'
        + '<div class="crm-dashboard-time-bar" aria-hidden="true"><i style="width:' + width + '%"></i></div></div>';
    }).join('');
    var overdue = (project.overdue_list || []).map(function (row) {
      return '<div class="crm-dashboard-extra-row"><div class="text-truncate"><a href="' + safe(taskDetailUrl(row.task_public_id)) + '" title="'
        + safe(row.title) + '">' + safe(row.title) + '</a><small class="is-overdue">' + safe(dateText(row.due_at)) + '</small></div></div>';
    }).join('');

    // Milestones arrive in the payload but are usually the only future-facing data
    // point of a project, so they get their own block with an overdue highlight.
    var nowMs = Date.now();
    var milestones = (project.milestones || []).map(function (milestone) {
      var due = String(milestone.due_at || '');
      var ts = Date.parse(due.replace(' ', 'T'));
      var status = String(milestone.status || '').toLowerCase();
      var closed = status === 'done' || status === 'completed' || status === 'cancelled';
      var isOverdue = !closed && Number.isFinite(ts) && ts < nowMs;
      return '<div class="crm-dashboard-extra-row"><div class="text-truncate"><a href="'
        + safe(projectDetailUrl(project.project_public_id)) + '" title="' + safe(milestone.title) + '">' + safe(milestone.title) + '</a>'
        + '<small class="' + (isOverdue ? 'is-overdue' : '') + '">' + safe(dateText(due) + (isOverdue ? ' · ' + translate('dashboard.extra_insights_overdue_short', 'просрочена') : '')) + '</small>'
        + '</div></div>';
    }).join('');

    var health = String(project.health || '');
    var healthHint = health === 'critical'
      ? translate('dashboard.extra_insights_health_critical', 'просрочено > 20% активных задач или веха в прошлом')
      : (health === 'risk' ? translate('dashboard.extra_insights_health_risk', 'есть просрочка или веха на этой неделе')
        : translate('dashboard.extra_insights_health_ok', 'риска нет'));

    var averagePerWeek = weeks.length
      ? Math.round(weeks.reduce(function (sum, week) { return sum + Number(week.completed || 0); }, 0) / weeks.length * 10) / 10
      : 0;
    var remaining = Number(project.active_tasks || 0);
    var finishHint = averagePerWeek > 0 && remaining > 0
      ? translate('dashboard.extra_insights_stream_eta', 'остаток ~%s нед. при текущем темпе').replace('%s', String(Math.round(remaining / averagePerWeek * 10) / 10))
      : '';

    container.innerHTML = toolbar
      + '<div class="crm-dashboard-insight-stream-head">' + selector
      + '<span class="crm-dashboard-wl-signal' + signalClass(project.health) + '">' + safe(signalLabel(project.health)) + '</span></div>'
      + '<div class="crm-dashboard-insight-grid mt-2">'
      + insightTile(translate('dashboard.extra_insights_progress', 'Прогресс'), progressBar(project.progress_percent) + ' ' + Number(project.progress_percent || 0) + '%', null, true)
      + insightTile(translate('dashboard.extra_insights_active', 'Активные'), String(remaining))
      + insightTile(translate('dashboard.extra_insights_overdue', 'Просрочено'), String(Number(project.overdue_tasks || 0)), Number(project.overdue_tasks || 0) > 0 ? 'risk' : null)
      + insightTile(translate('dashboard.extra_insights_cycle_median', 'Cycle time (медиана)'), formatMinutesCompact(project.cycle_time_median_minutes))
      + '</div>'
      + '<div class="crm-dashboard-insight-legend">' + safe(healthHint) + (finishHint ? ' · ' + safe(finishHint) : '') + '</div>'
      + (bars ? '<div class="crm-dashboard-insight-chips mt-2">' + safe(translate('dashboard.extra_insights_throughput', 'Throughput')) + '</div>' + bars : '')
      + (milestones ? '<div class="crm-dashboard-insight-section-title">' + safe(translate('dashboard.extra_insights_milestones', 'Вехи')) + '</div>' + milestones : '')
      + (statuses ? '<div class="crm-dashboard-insight-chips mt-2">' + statuses + '</div>' : '')
      + (members ? '<div class="crm-dashboard-insight-section-title">' + safe(translate('dashboard.extra_insights_top_members', 'Топ исполнителей по часам')) + '</div>' + members : '')
      + (overdue ? '<div class="crm-dashboard-insight-section-title">' + safe(translate('dashboard.extra_insights_overdue_list', 'Просроченные задачи')) + '</div>' + overdue : '');

    bindInsightToolbar(container, definition);
    bindStreamProjectSelect(container, definition);
  }

  // The selected project is remembered per widget so reopening the dashboard keeps
  // the manager on the stream they were inspecting.
  function bindStreamProjectSelect(container, definition) {
    var select = container.querySelector('[data-insights-project]');
    if (!select) return;
    select.addEventListener('change', function () {
      saveInsightOption(definition.key, 'project', select.value);
      reloadWidget(definition);
    });
  }

  function render(definition, envelope) {
    var key = definition.key;
    var container = document.querySelector('[data-extra-widget-body="' + key + '"]');
    if (!container) return;
    if (definition.kind === 'summary') return renderSummary(container, envelope, definition);
    if (definition.kind === 'count') return renderCount(container, envelope, definition);
    if (definition.kind === 'health') return renderHealth(container, envelope);
    if (definition.kind === 'tags') return renderTags(container, envelope, definition);
    if (definition.kind === 'time_team') return renderTeamTime(container, envelope, definition);
    if (definition.kind === 'workload') return renderWorkload(container, envelope, definition);
    if (definition.kind === 'milestones') return renderMilestones(container, envelope, definition);
    if (definition.kind === 'insights_my_load') return renderMyLoad(container, envelope, definition);
    if (definition.kind === 'insights_actual_time') return renderActualTime(container, envelope, definition);
    if (definition.kind === 'insights_kpi') return renderKpi(container, envelope, definition);
    if (definition.kind === 'insights_assignee_load') return renderAssigneeLoad(container, envelope, definition);
    if (definition.kind === 'insights_velocity') return renderVelocity(container, envelope, definition);
    if (definition.kind === 'insights_workload_mgmt') return renderWorkloadMgmt(container, envelope, definition);
    if (definition.kind === 'insights_streams') return renderStreams(container, envelope, definition);
    if (definition.kind === 'insights_stream_detail') return renderStreamDetail(container, envelope, definition);
    return renderList(container, envelope, definition);
  }

  function activeKeys() {
    var config = window.CRM && window.CRM.dashboardWidgetsConfig;
    var active = config && Array.isArray(config.active) ? config.active : [];
    // The server already filtered `active` by the actor's permissions
    // (DashboardController::resolveActive). Only keep keys this file renders.
    return active.filter(function (key) { return !!definitions[key]; });
  }

  var loaded = false;
  var loadAttempts = 0;
  var MAX_LOAD_ATTEMPTS = 20; // ~10s of 500ms retries while the widget config loads

  function load() {
    if (loaded || !document.querySelector('[data-page="dashboard"]')) return;
    var config = window.CRM && window.CRM.dashboardWidgetsConfig;
    if (!config || !Array.isArray(config.active)) {
      // The server catalog (GET /api/v1/dashboard/widgets) populates
      // window.CRM.dashboardWidgetsConfig. Retry briefly until it arrives so
      // widgets still render when the response is slow.
      loadAttempts += 1;
      if (loadAttempts < MAX_LOAD_ATTEMPTS) {
        window.setTimeout(load, 500);
      }
      return;
    }
    var keys = activeKeys();
    if (!keys.length) return;
    loaded = true;
    Promise.all(keys.map(function (key) {
      var definition = Object.assign({}, definitions[key], { key: key });
      var container = document.querySelector('[data-extra-widget-body="' + key + '"]');
      if (container) container.innerHTML = '<div class="text-muted small">' + safe(translate('dashboard.loading_widget', 'Загрузка...')) + '</div>';
      var queryOverride = null;
      if (key === 'time_team') {
        var from = new Date();
        from.setDate(from.getDate() - 6);
        queryOverride = { from: isoDateForPeriod(from), to: isoDateForPeriod(new Date()) };
      }
      if (key === 'milestone_watch') {
        return loadMilestones(definition).then(function (envelope) { render(definition, envelope); });
      }
      // Insights widgets honour the period/sort/project the user last chose for that
      // card, so a refresh does not silently reset the view.
      if (definition.kind && definition.kind.indexOf('insights_') === 0) {
        queryOverride = insightQuery(definition);
      }
      return request(definition, queryOverride).then(function (envelope) { render(definition, envelope); });
    })).catch(function () { loaded = false; });
  }

  document.addEventListener('crm:page-data-ready', load);
  document.addEventListener('DOMContentLoaded', function () {
    window.setTimeout(load, 1000);
  });
}());
