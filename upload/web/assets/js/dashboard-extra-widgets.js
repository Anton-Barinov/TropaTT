(function () {
  'use strict';

  var definitions = {
    analytics_summary: { route: 'api/v1/analytics/summary', permission: 'task.manage', kind: 'summary', link: 'index.php?route=analytics' },
    project_health: { route: 'api/v1/analytics/projects', permission: 'task.manage', kind: 'projects', link: 'index.php?route=analytics' },
    team_workload: { route: 'api/v1/analytics/users', permission: 'task.manage', kind: 'workload', query: { limit: 6 }, link: 'index.php?route=analytics' },
    time_team: { route: 'api/v1/worklogs/matrix', permission: 'task.manage', kind: 'time_team', link: 'index.php?route=time-analytics' },
    notification_inbox: { route: 'api/v1/notifications', permission: 'task.manage', kind: 'notifications', query: { limit: 5 }, link: 'index.php?route=notifications' },
    chat_unread: { route: 'api/v1/chats/unread-count', permission: 'chat.use', kind: 'count', link: 'index.php?route=chat' },
    client_pipeline: { route: 'api/v1/clients', permission: 'client.manage', kind: 'entities', query: { limit: 5 }, link: 'index.php?route=counterparties' },
    company_directory: { route: 'api/v1/companies', permission: 'company.manage', kind: 'entities', query: { limit: 5 }, link: 'index.php?route=counterparties' },
    contact_followups: { route: 'api/v1/contacts', permission: 'contact.manage', kind: 'entities', query: { limit: 5 }, link: 'index.php?route=counterparties' },
    tag_usage: { route: 'api/v1/tags', permission: 'task.manage', kind: 'tags', query: { limit: 6 }, link: 'index.php?route=admin-tags' },
    saved_views: { route: 'api/v1/views', permission: 'task.manage', kind: 'views', query: { entity_type: 'task', limit: 6 }, link: 'index.php?route=tasks' },
    subscriptions: { route: 'api/v1/subscriptions', permission: 'task.manage', kind: 'subscriptions', query: { limit: 6 }, titleFields: ['entity_title'], link: 'index.php?route=tasks', entityLink: true },
    dependency_watch: { route: 'api/v1/dependencies', permission: 'task.manage', kind: 'dependencies', query: { limit: 6 }, link: 'index.php?route=tasks' },
    milestone_watch: { route: 'api/v1/milestones', permission: 'project.manage', kind: 'milestones', link: 'index.php?route=projects' },
    recurring_health: { route: 'api/v1/recurring', permission: 'task.manage', kind: 'recurring', query: { limit: 6 }, link: 'index.php?route=recurring' },
    approval_queue: { route: 'api/v1/approvals', permission: 'approval.manage', kind: 'approvals', query: { limit: 6 }, link: 'index.php?route=approvals' },
    intake_sla: { route: 'api/v1/intake-items', permission: 'intake.view', kind: 'intake', query: { limit: 6 }, link: 'index.php?route=intake' },
    webhook_health: { route: 'api/v1/webhooks/deliveries', permission: 'webhook.manage', kind: 'webhooks', query: { limit: 6 }, link: 'index.php?route=admin-webhooks' },
    workflow_automation: { route: 'api/v1/workflow/rules', permission: 'settings.manage', kind: 'workflows', query: { limit: 6 }, link: 'index.php?route=admin-workflow' },
    system_health: { route: 'api/v1/health/status', kind: 'health', link: 'index.php?route=admin' },
    active_sessions: { route: 'api/v1/security/sessions', permission: 'logs.view', kind: 'sessions', query: { limit: 6 }, link: 'index.php?route=profile' }
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

  function hasPermission(permission) {
    if (!permission) return true;
    return !!api() && typeof api().hasPermission === 'function' && api().hasPermission(permission);
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
    return renderList(container, envelope, definition);
  }

  function activeKeys() {
    var config = window.CRM && window.CRM.dashboardWidgetsConfig;
    var active = config && Array.isArray(config.active) ? config.active : [];
    return active.filter(function (key) { return definitions[key] && hasPermission(definitions[key].permission); });
  }

  var loaded = false;

  function load() {
    if (loaded || !document.querySelector('[data-page="dashboard"]')) return;
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
      return request(definition, queryOverride).then(function (envelope) { render(definition, envelope); });
    })).catch(function () { loaded = false; });
  }

  document.addEventListener('crm:page-data-ready', load);
  document.addEventListener('DOMContentLoaded', function () {
    window.setTimeout(load, 1000);
  });
}());
