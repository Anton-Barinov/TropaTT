(function () {
  'use strict';

  var TASK_ACTIVITY_ICONS = {
    created: 'fa-plus-circle',
    status: 'fa-exchange-alt',
    assignee: 'fa-user',
    date: 'fa-calendar',
    comment: 'fa-comment',
    file: 'fa-paperclip',
    checklist: 'fa-check-square',
    subtask: 'fa-tasks',
    relation: 'fa-link',
    dependency: 'fa-project-diagram',
    ai: 'fa-wand-magic-sparkles',
    system: 'fa-cog',
    default: 'fa-pen',
  };

  function getIconForEvent(eventType) {
    if (!eventType) return TASK_ACTIVITY_ICONS.default;
    if (eventType.indexOf('created') !== -1) return TASK_ACTIVITY_ICONS.created;
    if (eventType.indexOf('status') !== -1) return TASK_ACTIVITY_ICONS.status;
    if (eventType.indexOf('assignee') !== -1) return TASK_ACTIVITY_ICONS.assignee;
    if (eventType.indexOf('_at_changed') !== -1) return TASK_ACTIVITY_ICONS.date;
    if (eventType.indexOf('comment') !== -1) return TASK_ACTIVITY_ICONS.comment;
    if (eventType.indexOf('file') !== -1) return TASK_ACTIVITY_ICONS.file;
    if (eventType.indexOf('checklist') !== -1) return TASK_ACTIVITY_ICONS.checklist;
    if (eventType.indexOf('subtask') !== -1) return TASK_ACTIVITY_ICONS.subtask;
    if (eventType.indexOf('relation') !== -1) return TASK_ACTIVITY_ICONS.relation;
    if (eventType.indexOf('dependency') !== -1) return TASK_ACTIVITY_ICONS.dependency;
    if (eventType.indexOf('ai') !== -1 || eventType.indexOf('suggestion') !== -1) return TASK_ACTIVITY_ICONS.ai;
    if (eventType.indexOf('system') !== -1 || eventType.indexOf('workflow') !== -1 || eventType.indexOf('webhook') !== -1) return TASK_ACTIVITY_ICONS.system;
    return TASK_ACTIVITY_ICONS.default;
  }

  window.loadTaskActivity = function (taskPublicId, page, filters) {
    page = page || 1;
    filters = filters || {};
    var listEl = document.getElementById('taskActivityList');
    if (!listEl) return;

    var params = [];
    params.push('page=' + encodeURIComponent(page));
    params.push('limit=30');
    if (filters.event_type) params.push('event_type=' + encodeURIComponent(filters.event_type));
    if (filters.actor_type) params.push('actor_type=' + encodeURIComponent(filters.actor_type));

    listEl.innerHTML = '<div class="crm-timeline-item text-muted"><small>Загрузка...</small></div>';

    var api = window.CRM && window.CRM.api;
    if (!api || typeof api.request !== 'function') {
      listEl.innerHTML = '<div class="crm-timeline-item text-muted"><small>API not available</small></div>';
      return;
    }

    api.request('api/v1/tasks/' + encodeURIComponent(taskPublicId) + '/activity?' + params.join('&'), { method: 'GET' })
      .then(function (envelope) {
        var items = envelope.data && envelope.data.items || [];
        if (!items.length) {
          listEl.innerHTML = '<div class="crm-timeline-item text-muted"><small>История пока пуста. Новые изменения по задаче появятся здесь.</small></div>';
          return;
        }
        renderTaskActivity(items, false, listEl);

        var meta = envelope.meta || {};
        var pagination = meta.pagination || {};
        var totalPages = pagination.pages || 1;
        if (page < totalPages) {
          var loadMoreBtn = document.createElement('button');
          loadMoreBtn.className = 'btn btn-sm crm-btn-secondary mt-2';
          loadMoreBtn.textContent = 'Показать еще';
          loadMoreBtn.addEventListener('click', function () {
            loadMoreBtn.disabled = true;
            loadMoreBtn.textContent = 'Загрузка...';
            api.request('api/v1/tasks/' + encodeURIComponent(taskPublicId) + '/activity?' + params.join('&') + '&page=' + (page + 1), { method: 'GET' })
              .then(function (env2) {
                var moreItems = env2.data && env2.data.items || [];
                if (moreItems.length > 0) {
                  renderTaskActivity(moreItems, true, listEl);
                }
                var meta2 = env2.meta || {};
                var pag2 = meta2.pagination || {};
                if ((page + 1) < (pag2.pages || 1)) {
                  loadMoreBtn.disabled = false;
                  loadMoreBtn.textContent = 'Показать еще';
                } else {
                  loadMoreBtn.remove();
                }
              })
              .catch(function () {
                loadMoreBtn.disabled = false;
                loadMoreBtn.textContent = 'Показать еще';
              });
          });
          listEl.appendChild(loadMoreBtn);
        }
      })
      .catch(function () {
        listEl.innerHTML = '<div class="crm-timeline-item text-muted"><small>Ошибка загрузки истории</small></div>';
      });
  };

  function renderTaskActivity(items, append, listEl) {
    if (!append) {
      listEl.innerHTML = '';
    }

    items.forEach(function (item) {
      var el = renderTaskActivityItem(item);
      if (el) listEl.appendChild(el);
    });
  }

  function renderTaskActivityItem(item) {
    if (!item) return null;

    var el = document.createElement('div');
    el.className = 'crm-timeline-item d-flex gap-2 mb-2';

    var icon = getIconForEvent(item.event_type || '');

    var actorName = escapeActivityText(item.actor_display_name || 'System');
    var msgText = item.message_text || item.event_type || '';
    var createdAt = item.created_at || '';
    var formattedDate = formatActivityDate(createdAt);

    el.innerHTML =
      '<div class="crm-timeline-icon flex-shrink-0 pt-1" style="width:24px;text-align:center;">' +
        '<i class="fa-solid ' + icon + ' text-muted"></i>' +
      '</div>' +
      '<div class="crm-timeline-content flex-grow-1">' +
        '<div class="small">' + escapeActivityText(msgText) + '</div>' +
        '<div class="small text-muted">' + formattedDate + '</div>' +
      '</div>';

    return el;
  }

  function formatActivityDate(dateStr) {
    if (!dateStr) return '';
    try {
      var d = new Date(dateStr.replace(' ', 'T') + 'Z');
      if (isNaN(d.getTime())) return dateStr;
      var day = String(d.getDate()).padStart(2, '0');
      var month = String(d.getMonth() + 1).padStart(2, '0');
      var year = d.getFullYear();
      var hours = String(d.getHours()).padStart(2, '0');
      var mins = String(d.getMinutes()).padStart(2, '0');
      return day + '.' + month + '.' + year + ' ' + hours + ':' + mins;
    } catch (e) {
      return dateStr;
    }
  }

  function escapeActivityText(s) {
    return String(s || '').replace(/[&<>"']/g, function (ch) {
      var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
      return map[ch] || ch;
    });
  }
})();
