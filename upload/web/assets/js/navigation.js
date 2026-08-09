window.CRM = window.CRM || {};
window.CRM.navigation = (function () {
  function t(key, fallback) {
    if (window.CRM.i18n && typeof window.CRM.i18n.t === 'function') {
      return window.CRM.i18n.t(key, fallback);
    }
    return fallback || key;
  }

  function icon(name) {
    var icons = {
      menu: '<span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-bars"></i></span>',
      search: '<span class="crm-icon crm-input-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>',
      bell: '<span class="crm-icon" aria-hidden="true"><i class="fa-regular fa-bell"></i></span>',
      chat: '<span class="crm-icon" aria-hidden="true"><i class="fa-regular fa-comments"></i></span>',
      chevronLeft: '<span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-chevron-left"></i></span>',
      chevronRight: '<span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>',
      palette: '<span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-palette"></i></span>'
    };
    return icons[name] || '';
  }

  function navIcon(routeKey) {
    var icons = {
      dashboard: '<i class="fa-solid fa-house"></i>',
      day: '<i class="fa-solid fa-calendar-day"></i>',
      week: '<i class="fa-solid fa-calendar-week"></i>',
      tasks: '<i class="fa-solid fa-list-check"></i>',
      projects: '<i class="fa-solid fa-folder-tree"></i>',
      calendar: '<i class="fa-solid fa-calendar-days"></i>',
      gantt: '<i class="fa-solid fa-chart-gantt"></i>',
      kanban: '<i class="fa-solid fa-table-columns"></i>',
      analytics: '<i class="fa-solid fa-chart-column"></i>',
      notifications: '<i class="fa-regular fa-bell"></i>',
      chat: '<i class="fa-regular fa-comments"></i>',
      counterparties: '<i class="fa-solid fa-address-book"></i>',
      teams: '<i class="fa-solid fa-people-group"></i>',
      knowledge: '<i class="fa-solid fa-book-open-reader"></i>',
      profile: '<i class="fa-solid fa-user"></i>',
      admin: '<i class="fa-solid fa-shield-halved"></i>',
      'admin-settings': '<i class="fa-solid fa-sliders"></i>',
      'admin-jobs': '<i class="fa-solid fa-gears"></i>',
      'admin-ai': '<i class="fa-solid fa-robot"></i>',
      'admin-workflow': '<i class="fa-solid fa-diagram-project"></i>',
      'admin-sla': '<i class="fa-solid fa-clock"></i>',
      'approvals': '<i class="fa-solid fa-clipboard-check"></i>',
      'recycle-bin': '<i class="fa-solid fa-trash-can"></i>',
      'admin-custom-fields': '<i class="fa-solid fa-pen-to-square"></i>',
      'recurring': '<i class="fa-solid fa-arrows-rotate"></i>',
      'organizations': '<i class="fa-solid fa-building-columns"></i>',
      'admin-priorities': '<i class="fa-solid fa-arrow-up-wide-short"></i>',
      'admin-calendar': '<i class="fa-solid fa-calendar-check"></i>',
      'mentions': '<i class="fa-solid fa-at"></i>',
      'admin-templates': '<i class="fa-solid fa-copy"></i>',
      'admin-tags': '<i class="fa-solid fa-tags"></i>',
      'admin-webhooks': '<i class="fa-solid fa-webhook"></i>',
      'admin-modules': '<i class="fa-solid fa-cubes"></i>',
      'admin-estimates': '<i class="fa-solid fa-ruler-combined"></i>',
      'ideas': '<i class="fa-regular fa-lightbulb"></i>',
      intake: '<i class="fa-solid fa-inbox"></i>',
      cycles: '<i class="fa-solid fa-arrows-spin"></i>',
      docs: '<i class="fa-solid fa-book-open"></i>',
      api: '<i class="fa-solid fa-code"></i>'
    };
    return icons[routeKey] || '<i class="fa-solid fa-circle-dot"></i>';
  }

  var SIDEBAR_COLLAPSE_KEY = 'crm_sidebar_collapsed';
  var sidebarCollapsed = false;
  var sidebarDocumentClickBound = false;

  var MENU_CACHE_KEY = 'crm_menu_items';
  var MENU_CACHE_TTL_MS = 5 * 60 * 1000;
  var navItems = [];
  var allAvailableItems = [];
  var menuLoaded = false;

  function readCachedMenu() {
    try {
      var raw = localStorage.getItem(MENU_CACHE_KEY);
      if (!raw) return null;
      var parsed = JSON.parse(raw);
      if (!parsed || !parsed.items || !parsed.timestamp) return null;
      var age = Date.now() - parsed.timestamp;
      if (age > MENU_CACHE_TTL_MS) return null;
      return parsed.items;
    } catch (e) {
      return null;
    }
  }

  function cacheMenu(items) {
    try {
      localStorage.setItem(MENU_CACHE_KEY, JSON.stringify({
        items: items,
        timestamp: Date.now()
      }));
    } catch (e) {}
  }

  function clearMenuCache() {
    try {
      localStorage.removeItem(MENU_CACHE_KEY);
    } catch (e) {}
  }

  async function fetchMenuFromApi() {
    if (!window.CRM.api || typeof window.CRM.api.request !== 'function') {
      return null;
    }
    try {
      var envelope = await window.CRM.api.request('api/v1/auth/menu');
      var data = envelope && envelope.data ? envelope.data : {};
      var items = Array.isArray(data.items) ? data.items : [];
      allAvailableItems = Array.isArray(data.all_available_items) ? data.all_available_items : getDefaultNavItems();
      return items;
    } catch (e) {
      return null;
    }
  }

  async function loadNavItems() {
    var fetched = await fetchMenuFromApi();
    if (fetched && fetched.length > 0) {
      navItems = fetched;
      cacheMenu(fetched);
      menuLoaded = true;
      return;
    }

    navItems = getDefaultNavItems();
    allAvailableItems = getDefaultNavItems();
    menuLoaded = true;
  }

  function getDefaultNavItems() {
    return [
      { key: 'dashboard', i18n: 'nav.dashboard', label: t('nav.dashboard', 'Dashboard'), href: 'index.php?route=dashboard' },
      { key: 'ideas', i18n: 'nav.ideas', label: t('nav.ideas', 'Ideas'), href: 'index.php?route=ideas' },
      { key: 'tasks', i18n: 'nav.tasks', label: t('nav.tasks', 'Tasks'), href: 'index.php?route=tasks' },
      { key: 'day', i18n: 'nav.day', label: t('nav.day', 'My day'), href: 'index.php?route=my-day' },
      { key: 'week', i18n: 'nav.week', label: t('nav.week', 'My week'), href: 'index.php?route=my-week' },
      { key: 'kanban', i18n: 'nav.kanban', label: t('nav.kanban', 'Kanban'), href: 'index.php?route=kanban' },
      { key: 'gantt', i18n: 'nav.gantt', label: t('nav.gantt', 'Gantt'), href: 'index.php?route=gantt' },
      { key: 'projects', i18n: 'nav.projects', label: t('nav.projects', 'Projects'), href: 'index.php?route=projects' },
      { key: 'calendar', i18n: 'nav.calendar', label: t('nav.calendar', 'Calendar'), href: 'index.php?route=calendar' },
      { key: 'counterparties', i18n: 'nav.counterparties', label: t('nav.counterparties', 'Counterparties'), href: 'index.php?route=counterparties' },
      { key: 'teams', i18n: 'nav.teams', label: t('nav.teams', 'Teams and departments'), href: 'index.php?route=teams' },
      { key: 'knowledge', i18n: 'nav.knowledge', label: t('nav.knowledge', 'Knowledge base'), href: 'index.php?route=knowledge' },
      { key: 'analytics', i18n: 'nav.analytics', label: t('nav.analytics', 'Analytics'), href: 'index.php?route=analytics' },
      { key: 'intake', i18n: 'nav.intake', label: t('nav.intake', 'Intake'), href: 'index.php?route=intake' },
      { key: 'cycles', i18n: 'nav.cycles', label: t('nav.cycles', 'Cycles'), href: 'index.php?route=cycles' },
      { key: 'notifications', i18n: 'nav.notifications', label: t('nav.notifications', 'Notifications'), href: 'index.php?route=notifications' },
      { key: 'chat', i18n: 'nav.chat', label: t('nav.chat', 'Chats'), href: 'index.php?route=chat' },
      { key: 'admin', i18n: 'nav.admin', label: t('nav.admin', 'Administration'), href: 'index.php?route=admin' },
      { key: 'admin-modules', i18n: 'nav.admin_modules', label: t('nav.admin_modules', 'Modules'), href: 'index.php?route=admin-modules' },
      { key: 'project-modules', i18n: 'nav.project_modules', label: t('nav.project_modules', 'Project modules'), href: 'index.php?route=project-modules' },
      { key: 'admin-estimates', i18n: 'nav.admin_estimates', label: t('nav.admin_estimates', 'Estimates'), href: 'index.php?route=admin-estimates' },
      { key: 'docs', i18n: 'nav.docs', label: t('nav.docs', 'Documentation'), href: 'index.php?route=docs' }
    ];
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
      if (char === '&') return '&amp;';
      if (char === '<') return '&lt;';
      if (char === '>') return '&gt;';
      if (char === '"') return '&quot;';
      return '&#39;';
    });
  }

  function safeNavHref(value) {
    var href = String(value || '').trim();
    if (!href || /[\u0000-\u001F\u007F]/.test(href)) return '#';

    var decoded = href;
    for (var i = 0; i < 2; i += 1) {
      try {
        var next = decodeURIComponent(decoded);
        if (next === decoded) break;
        decoded = next;
      } catch (e) {
        break;
      }
    }

    if (/[\u0000-\u001F\u007F]/.test(decoded)) return '#';
    if (/^\/\//.test(decoded) || /^\\\\/.test(decoded)) return '#';
    var schemeMatch = decoded.match(/^([a-z][a-z0-9+.-]*):/i);
    if (schemeMatch && ['http', 'https', 'mailto', 'tel'].indexOf(schemeMatch[1].toLowerCase()) === -1) {
      return '#';
    }
    return href;
  }

  function resultUrl(item) {
    var type = String(item && item.entity_type || '').trim();
    var publicId = String(item && item.public_id || '').trim();
    var label = String(item && item.label || '').trim();
    if (type === 'task' && publicId) return 'index.php?route=task-detail&task_public_id=' + encodeURIComponent(publicId);
    if (type === 'project' && publicId) return 'index.php?route=project-detail&project_public_id=' + encodeURIComponent(publicId);
    if (type === 'knowledge' && publicId) return 'index.php?route=knowledge-page&id=' + encodeURIComponent(publicId);
    if (type === 'client' && publicId) return 'index.php?route=client-detail&client_public_id=' + encodeURIComponent(publicId);
    if ((type === 'company' || type === 'contact') && label) {
      return 'index.php?route=clients&search=' + encodeURIComponent(label);
    }
    return 'index.php?route=tasks&search=' + encodeURIComponent(label || publicId);
  }

  function resultTypeLabel(type) {
    var map = {
      task: t('nav.result_task', 'Task'),
      project: t('nav.result_project', 'Project'),
      client: t('nav.result_client', 'Client'),
      company: t('nav.result_company', 'Company'),
      contact: t('nav.result_contact', 'Contact'),
      knowledge: t('nav.result_knowledge', 'Knowledge'),
      comment: t('nav.result_comment', 'Comment'),
      file: t('nav.result_file', 'File')
    };
    return map[String(type || '').trim()] || t('nav.result_default', 'Result');
  }

  function renderSearchDropdown(container, payload, activeIndex) {
    if (!container) return [];
    var results = [];
    var groups = payload && payload.results && typeof payload.results === 'object' ? payload.results : {};
    var typeMap = { tasks: 'task', projects: 'project', clients: 'client', companies: 'company', contacts: 'contact', knowledge: 'knowledge' };
    ['tasks', 'projects', 'clients', 'companies', 'contacts', 'knowledge'].forEach(function (groupKey) {
      var list = Array.isArray(groups[groupKey]) ? groups[groupKey] : [];
      list.forEach(function (item) {
        results.push({
          entity_type: typeMap[groupKey] || groupKey.slice(0, -1),
          public_id: String(item.public_id || ''),
          label: String(item.title || item.full_name || item.public_id || ''),
          meta: item
        });
      });
    });

    if (!results.length) {
      container.innerHTML = '<div class="dropdown-item-text text-muted small">' + t('nav.search_no_results', 'Nothing found') + '</div>';
      return [];
    }

    container.innerHTML = results.slice(0, 12).map(function (item, index) {
      var meta = item.meta || {};
      var subtitle = '';
      if (item.entity_type === 'task') {
        subtitle = String(meta.project_title || meta.status_code || '');
      } else if (item.entity_type === 'project') {
        subtitle = String(meta.status_code || '');
      } else if (item.entity_type === 'client') {
        subtitle = String(meta.email || meta.phone || '');
      } else       if (item.entity_type === 'contact') {
        subtitle = String(meta.email || meta.phone || '');
      } else if (item.entity_type === 'knowledge') {
        subtitle = String(meta.space_title || meta.page_type || '');
      }
      var activeClass = index === activeIndex ? ' active' : '';
      return '<a class="dropdown-item crm-search-result' + activeClass + '" href="' + resultUrl(item) + '" data-search-result-index="' + index + '">'
        + '<div class="d-flex justify-content-between gap-2"><strong>' + escapeHtml(item.label || t('nav.untitled', 'Untitled')) + '</strong><span class="text-muted small">' + escapeHtml(resultTypeLabel(item.entity_type)) + '</span></div>'
        + (subtitle ? '<div class="small text-muted">' + escapeHtml(subtitle) + '</div>' : '')
        + '</a>';
    }).join('');

    return results.slice(0, 12);
  }

  function renderSemanticSearchDropdown(container, payload, activeIndex) {
    if (!container) return [];
    var items = payload && Array.isArray(payload.items) ? payload.items : [];
    var results = items.map(function (item) {
      var type = String(item.entity_type || '').trim();
      var publicId = String(item.entity_public_id || '').trim();
      var snippet = String(item.snippet || '').trim();
      return {
        entity_type: type,
        public_id: publicId,
        label: snippet ? snippet.slice(0, 110) : publicId,
        meta: {
          score: Number(item.score || 0),
          snippet: snippet
        }
      };
    }).filter(function (item) {
      return item.entity_type && item.public_id;
    });

    if (!results.length) {
      container.innerHTML = '<div class="dropdown-item-text text-muted small">' + t('nav.ai_no_results', 'AI found nothing') + '</div>';
      return [];
    }

    container.innerHTML = results.slice(0, 12).map(function (item, index) {
      var score = Number(item.meta && item.meta.score || 0);
      var activeClass = index === activeIndex ? ' active' : '';
      var subtitle = resultTypeLabel(item.entity_type) + (score > 0 ? (' · ' + t('nav.score', 'score') + ' ' + score.toFixed(2)) : '');
      return '<a class="dropdown-item crm-search-result' + activeClass + '" href="' + resultUrl(item) + '" data-search-result-index="' + index + '">'
        + '<div class="d-flex justify-content-between gap-2"><strong>' + escapeHtml(item.label || t('nav.untitled', 'Untitled')) + '</strong><span class="text-muted small">' + escapeHtml(t('nav.ai_search_short', 'AI')) + '</span></div>'
        + '<div class="small text-muted">' + escapeHtml(subtitle) + '</div>'
        + '</a>';
    }).join('');

    return results.slice(0, 12);
  }

  function ensureSidebar() {
    var nav = document.querySelector('.crm-nav');
    if (!nav) return;
    var items = navItems.slice();
    var parented = {};
    var topLevel = [];

    items.forEach(function (item) {
      if (item.parent) {
        if (!parented[item.parent]) parented[item.parent] = [];
        parented[item.parent].push(item);
      } else {
        topLevel.push(item);
      }
    });

    nav.innerHTML = topLevel.map(function (item) {
      var label = t(item.i18n, item.label || item.key);
      var safeLabel = escapeHtml(label);
      var iconHtml = item.icon || navIcon(item.key);
      var badge = item.key === 'chat'
        ? '<span class="crm-nav-badge d-none" data-chat-unread-badge aria-label=""></span>'
        : (item.key === 'notifications'
          ? '<span class="crm-nav-badge d-none" data-nav-notification-badge aria-label=""></span>'
          : '');
      var html = '<a class="nav-link" data-nav="' + escapeHtml(item.key) + '" href="' + escapeHtml(safeNavHref(item.href)) + '" title="' + safeLabel + '">'
        + '<span class="crm-nav-icon" aria-hidden="true">' + iconHtml + '</span>'
        + '<span class="crm-nav-label">' + safeLabel + '</span>'
        + badge
        + '</a>';

      if (parented[item.key]) {
        parented[item.key].forEach(function (sub) {
          var subLabel = t(sub.i18n, sub.label || sub.key);
          html += '<a class="nav-link crm-nav-sub" data-nav="' + escapeHtml(sub.key) + '" href="' + escapeHtml(safeNavHref(sub.href)) + '" title="' + escapeHtml(subLabel) + '">'
            + '<span class="crm-nav-label ps-4">' + escapeHtml(subLabel) + '</span>'
            + '</a>';
        });
      }

      return html;
    }).join('');
  }

  function renderSidebarSync() {
    var nav = document.querySelector('.crm-nav');
    if (!nav) return;
    var items = navItems.slice();
    var parented = {};
    var topLevel = [];

    items.forEach(function (item) {
      if (item.parent) {
        if (!parented[item.parent]) parented[item.parent] = [];
        parented[item.parent].push(item);
      } else {
        topLevel.push(item);
      }
    });

    nav.innerHTML = topLevel.map(function (item) {
      var label = t(item.i18n, item.label || item.key);
      var safeLabel = escapeHtml(label);
      var iconHtml;
      if (item.is_custom && item.icon) {
        iconHtml = '<span class="crm-icon" aria-hidden="true"><i class="' + escapeHtml(item.icon) + '"></i></span>';
      } else {
        iconHtml = item.icon || navIcon(item.key);
      }
      var badge = item.key === 'chat'
        ? '<span class="crm-nav-badge d-none" data-chat-unread-badge aria-label=""></span>'
        : (item.key === 'notifications'
          ? '<span class="crm-nav-badge d-none" data-nav-notification-badge aria-label=""></span>'
          : '');
      var href = safeNavHref(item.href);
      var html = '<a class="nav-link" data-nav="' + escapeHtml(item.key) + '" href="' + escapeHtml(href) + '" title="' + safeLabel + '">'
        + '<span class="crm-nav-icon" aria-hidden="true">' + iconHtml + '</span>'
        + '<span class="crm-nav-label">' + safeLabel + '</span>'
        + badge
        + '</a>';

      if (parented[item.key]) {
        parented[item.key].forEach(function (sub) {
          var subLabel = t(sub.i18n, sub.label || sub.key);
          var subIcon = sub.is_custom && sub.icon
            ? '<span class="crm-icon" aria-hidden="true"><i class="' + escapeHtml(sub.icon) + '"></i></span>'
            : (sub.icon || navIcon(sub.key));
          html += '<a class="nav-link crm-nav-sub" data-nav="' + escapeHtml(sub.key) + '" href="' + escapeHtml(safeNavHref(sub.href)) + '" title="' + escapeHtml(subLabel) + '">'
            + '<span class="crm-nav-label ps-4">' + escapeHtml(subLabel) + '</span>'
            + '</a>';
        });
      }

      return html;
    }).join('');
  }

  function sidebarToggleMarkup() {
    return '<button class="crm-sidebar-toggle" type="button" data-sidebar-collapse-toggle aria-label="' + t('nav.collapse_menu', 'Collapse menu') + '" title="' + t('nav.collapse_menu', 'Collapse menu') + '">' + icon('chevronLeft') + '</button>';
  }

  function ensureSidebarCollapseControl() {
    var sidebar = document.querySelector('.crm-sidebar');
    if (!sidebar) return;
    var brand = sidebar.querySelector('.crm-brand');
    if (!brand) return;
    if (!brand.querySelector('[data-sidebar-collapse-toggle]')) {
      brand.insertAdjacentHTML('beforeend', sidebarToggleMarkup());
    }
  }

  function applySidebarCollapsedState(collapsed, persist) {
    sidebarCollapsed = !!collapsed;
    document.documentElement.classList.toggle('crm-sidebar-collapsed', sidebarCollapsed);
    if (document.body) {
      document.body.classList.toggle('crm-sidebar-collapsed', sidebarCollapsed);
    }

    var toggle = document.querySelector('[data-sidebar-collapse-toggle]');
    if (toggle) {
      var label = sidebarCollapsed
        ? t('nav.expand_menu', 'Expand menu')
        : t('nav.collapse_menu', 'Collapse menu');
      toggle.setAttribute('aria-label', label);
      toggle.setAttribute('title', label);
      toggle.innerHTML = sidebarCollapsed ? icon('chevronRight') : icon('chevronLeft');
    }

    if (persist) {
      document.cookie = SIDEBAR_COLLAPSE_KEY + '=' + (sidebarCollapsed ? '1' : '0') + '; path=/; max-age=31536000; samesite=lax';
    }
  }

  function readCookie(name) {
    var key = String(name || '').trim();
    if (!key) return '';
    var parts = String(document.cookie || '').split(';');
    for (var i = 0; i < parts.length; i += 1) {
      var piece = String(parts[i] || '').trim();
      if (piece.indexOf(key + '=') === 0) return decodeURIComponent(piece.slice(key.length + 1));
    }
    return '';
  }

  function restoreSidebarState() {
    var shouldCollapse = readCookie(SIDEBAR_COLLAPSE_KEY) === '1';
    applySidebarCollapsedState(shouldCollapse, false);
  }

  function normalizeSearchGroup(searchGroup) {
    if (!searchGroup) return;
    var iconSlot = searchGroup.querySelector('.input-group-text');
    if (iconSlot) iconSlot.innerHTML = icon('search');
    var searchInput = searchGroup.querySelector('input');
    if (searchInput) searchInput.setAttribute('placeholder', t('topbar.search_placeholder', 'Search'));
  }

  function ensureTopbar() {
    var bar = document.querySelector('.crm-topbar .container-fluid');
    if (!bar) return;

    if (!bar.querySelector('#sidebarToggle')) {
      var toggleBtn = document.createElement('button');
      toggleBtn.id = 'sidebarToggle';
      toggleBtn.className = 'crm-sidebar-toggle';
      toggleBtn.setAttribute('aria-label', t('topbar.open_menu', 'Open menu'));
      toggleBtn.innerHTML = icon('menu');
      toggleBtn.style.display = 'inline-flex';
      bar.insertAdjacentElement('afterbegin', toggleBtn);
      // Let CSS handle visibility via media queries (desktop: none, mobile: inline-flex)
    } else {
      var sidebarToggle = bar.querySelector('#sidebarToggle');
      if (sidebarToggle && !sidebarToggle.querySelector('i')) sidebarToggle.innerHTML = icon('menu');
    }

    // Quick create task button (like Jira) — opens the global createTaskModal.
    // RBAC hiding is handled by br1.js applyPermissionVisibility() via the
    // [data-open-modal="createTaskModal"] selector + MutationObserver.
    if (!bar.querySelector('[data-quick-create]')) {
      var quickCreate = document.createElement('button');
      quickCreate.type = 'button';
      quickCreate.className = 'btn crm-btn-primary crm-btn-compact crm-topbar-quick-create';
      quickCreate.setAttribute('data-quick-create', '1');
      quickCreate.setAttribute('data-open-modal', 'createTaskModal');
      quickCreate.setAttribute('aria-label', t('topbar.create_task', 'Create task'));
      quickCreate.setAttribute('title', t('topbar.create_task', 'Create task'));
      quickCreate.innerHTML = '<span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span><span class="crm-topbar-quick-create-label">' + t('topbar.create_task', 'Create task') + '</span>';
      // #sidebarToggle is guaranteed to exist here (created in the block above).
      bar.querySelector('#sidebarToggle').insertAdjacentElement('afterend', quickCreate);
    }

    var searchGroup = bar.querySelector('[data-global-search], .input-group');
    if (!searchGroup) {
      bar.insertAdjacentHTML('beforeend', '<div class="input-group" data-global-search><span class="input-group-text">' + icon('search') + '</span><input class="form-control" placeholder="' + t('topbar.search_placeholder', 'Search') + '"></div>');
      searchGroup = bar.querySelector('[data-global-search]');
    } else {
      searchGroup.setAttribute('data-global-search', '1');
      normalizeSearchGroup(searchGroup);
    }

    if (searchGroup && !searchGroup.id) {
      searchGroup.id = 'crmGlobalSearch';
    }
    if (searchGroup) {
      searchGroup.classList.add('crm-search-group');
      searchGroup.classList.add('is-collapsed');
      normalizeSearchGroup(searchGroup);
    }
    bar.classList.add('crm-search-collapsed');

    var right = bar.querySelector('[data-global-actions]') || bar.querySelector('.ms-auto');
    if (!right) {
      right = document.createElement('div');
      right.className = 'ms-auto d-flex align-items-center gap-2';
      right.setAttribute('data-global-actions', '1');
      bar.appendChild(right);
    } else if (!right.hasAttribute('data-global-actions')) {
      right.setAttribute('data-global-actions', '1');
    }
    right.classList.add('ms-auto', 'd-flex', 'align-items-center', 'gap-2');

    if (!bar.querySelector('[data-search-toggle]')) {
      right.insertAdjacentHTML('afterbegin', '<button class="btn crm-btn-ghost crm-btn-icon" data-search-toggle aria-label="' + t('topbar.open_search', 'Open search') + '" title="' + t('topbar.search_title', 'Search') + '">' + icon('search') + '</button>');
    }
    var existingSearchToggle = bar.querySelector('[data-search-toggle]');
    if (existingSearchToggle && !existingSearchToggle.querySelector('i')) {
      existingSearchToggle.innerHTML = icon('search');
    }

    // Global running-timer indicator slot; br1.js renderTopbarTaskTimer()
    // fills it with the elapsed time and task link while a timer is running.
    // Placed BEFORE the search toggle so the running timer is the first item
    // in the global-actions block when it is visible.
    if (!bar.querySelector('#topbarTaskTimer')) {
      var timerSlot = document.createElement('div');
      timerSlot.id = 'topbarTaskTimer';
      timerSlot.className = 'd-none';
      timerSlot.setAttribute('data-topbar-timer', '1');
      var searchToggleRef = bar.querySelector('[data-search-toggle]');
      if (searchToggleRef) {
        searchToggleRef.insertAdjacentElement('beforebegin', timerSlot);
      } else {
        right.insertAdjacentElement('afterbegin', timerSlot);
      }
    }

    if (!bar.querySelector('[data-global-chat]')) {
      right.insertAdjacentHTML('beforeend', '<a class="btn crm-btn-ghost crm-btn-icon position-relative" data-global-chat href="index.php?route=chat" aria-label="' + t('nav.chats', 'Chats') + '" title="' + t('nav.chats', 'Chats') + '">' + icon('chat') + '<span class="crm-topbar-badge d-none" data-chat-unread-badge aria-label=""></span></a>');
    }

    if (!bar.querySelector('[data-global-notifications]') && !bar.querySelector('[data-bs-toggle="popover"]')) {
      right.insertAdjacentHTML('beforeend', '<button class="btn crm-btn-ghost crm-btn-icon" data-global-notifications data-bs-toggle="popover" data-bs-html="true" data-bs-content="<div class=\'text-muted small\'>...</div>" aria-label="' + t('topbar.notifications', 'Notifications') + '">' + icon('bell') + '</button>');
    }
    var existingNotify = bar.querySelector('[data-global-notifications], [data-bs-toggle="popover"]');
    if (existingNotify && !existingNotify.querySelector('i')) {
      existingNotify.innerHTML = icon('bell');
    }

    // Per-user color theme switcher (light / dark / contrast). The theme is
    // applied instantly via window.CRM.theme (data-theme + localStorage) and
    // persisted to the profile preferences so the choice follows the user
    // across devices.
    if (!bar.querySelector('[data-theme-switcher]')) {
      right.insertAdjacentHTML('beforeend', '<div class="dropdown" data-theme-switcher>'
        + '<button class="btn crm-btn-ghost crm-btn-icon" type="button" data-bs-toggle="dropdown" data-theme-switch-btn aria-label="' + t('topbar.theme', 'Theme') + '" title="' + t('topbar.theme', 'Theme') + '" aria-haspopup="true">' + icon('palette') + '</button>'
        + '<ul class="dropdown-menu dropdown-menu-end" data-theme-menu>'
        + '<li><h6 class="dropdown-header">' + t('topbar.theme', 'Theme') + '</h6></li>'
        + '<li><button class="dropdown-item crm-theme-option" type="button" data-theme-option="light"><i class="fa-solid fa-check crm-theme-check" aria-hidden="true"></i><span>' + t('topbar.theme_light', 'Light') + '</span></button></li>'
        + '<li><button class="dropdown-item crm-theme-option" type="button" data-theme-option="dark"><i class="fa-solid fa-check crm-theme-check" aria-hidden="true"></i><span>' + t('topbar.theme_dark', 'Dark') + '</span></button></li>'
        + '<li><button class="dropdown-item crm-theme-option" type="button" data-theme-option="contrast"><i class="fa-solid fa-check crm-theme-check" aria-hidden="true"></i><span>' + t('topbar.theme_contrast', 'High contrast') + '</span></button></li>'
        + '</ul></div>');
    }
    syncThemeSwitcher();
    bindThemeSwitcher();

    if (!bar.querySelector('[data-profile-dropdown]') && !bar.querySelector('.dropdown [data-bs-toggle="dropdown"]')) {
      right.insertAdjacentHTML('beforeend', '<div class="dropdown" data-profile-dropdown><button class="btn crm-btn-ghost dropdown-toggle" data-bs-toggle="dropdown">' + t('topbar.user_fallback', 'User') + '</button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="index.php?route=profile">' + t('topbar.profile', 'Profile') + '</a></li><li><a class="dropdown-item" href="index.php?route=notifications">' + t('topbar.notifications', 'Notifications') + '</a></li><li><hr class="dropdown-divider"></li><li><button class="dropdown-item" type="button" data-action="logout">' + t('topbar.logout', 'Logout') + '</button></li></ul></div>');
    }

    var profileButton = bar.querySelector('[data-profile-dropdown] .dropdown-toggle')
      || bar.querySelector('[data-global-actions] .dropdown .dropdown-toggle')
      || bar.querySelector('.ms-auto .dropdown .dropdown-toggle');
    if (profileButton) {
      profileButton.setAttribute('data-session-user-btn', '1');
    }

    // The topbar is built asynchronously (after the menu fetch), so it can be
    // created after br1.js already hydrated the session user name. Apply the
    // cached user name right after the button exists, so it never stays on the
    // generic default label ("Пользователь"/"User").
    if (profileButton && window.CRM.br1 && typeof window.CRM.br1.setSessionUiUser === 'function') {
      var cachedSessionUser = window.CRM.api && typeof window.CRM.api.getUser === 'function'
        ? window.CRM.api.getUser()
        : null;
      if (cachedSessionUser) {
        window.CRM.br1.setSessionUiUser(cachedSessionUser);
      }
    }

    // Some pages bake the user menu / notifications button into their own
    // header markup, which puts them before the JS-added buttons and makes the
    // topbar order differ from page to page. Normalize the order so every page
    // shows: running-timer indicator, search toggle, chats, notifications and
    // the user menu last (same as the dashboard).
    if (right) {
      var canonicalOrder = [
        bar.querySelector('#topbarTaskTimer'),
        right.querySelector('[data-search-toggle]'),
        right.querySelector('[data-global-chat]'),
        right.querySelector('[data-global-notifications]'),
        right.querySelector('[data-theme-switcher]'),
        right.querySelector('[data-profile-dropdown]') || right.querySelector('.dropdown [data-bs-toggle="dropdown"]')
      ];
      canonicalOrder.forEach(function (el) {
        if (el && el.parentNode === right) {
          right.appendChild(el);
        }
      });
    }
  }

  var themeSwitcherBound = false;

  function syncThemeSwitcher() {
    var current = window.CRM.theme && typeof window.CRM.theme.get === 'function'
      ? window.CRM.theme.get()
      : 'light';
    document.querySelectorAll('[data-theme-option]').forEach(function (btn) {
      var active = btn.getAttribute('data-theme-option') === current;
      btn.classList.toggle('is-active', active);
    });
  }

  function bindThemeSwitcher() {
    if (themeSwitcherBound) return;
    themeSwitcherBound = true;
    // Event delegation: works no matter when/whether the dropdown markup is
    // present (topbar is built asynchronously after the menu fetch).
    document.addEventListener('click', function (event) {
      var btn = event.target && event.target.closest ? event.target.closest('[data-theme-option]') : null;
      if (!btn) return;
      var name = btn.getAttribute('data-theme-option');
      if (!name) return;
      if (window.CRM.theme && typeof window.CRM.theme.apply === 'function') {
        window.CRM.theme.apply(name);
      }
      // Persist the choice server-side (same endpoint as the profile page) so
      // it follows the user across devices. CSRF is attached by api.js.
      if (window.CRM.api && typeof window.CRM.api.request === 'function') {
        window.CRM.api.request('api/v1/profile/preferences', {
          method: 'PATCH',
          body: { preferences: { theme: name } }
        }).catch(function () {});
      }
      // Keep the profile page select in sync when it is open.
      var profileSelect = document.getElementById('profileThemeSelect');
      if (profileSelect && profileSelect.value !== name) {
        profileSelect.value = name;
      }
    });
    document.addEventListener('crm:theme-changed', function () {
      syncThemeSwitcher();
    });
  }

  function markActive() {
    var page = (document.body.dataset.page || '').trim();
    var route = window.CRM.api && typeof window.CRM.api.currentRoute === 'function'
      ? window.CRM.api.currentRoute(page || 'dashboard')
      : '';
    document.querySelectorAll('[data-nav]').forEach(function (link) {
      if (link.dataset.nav === page || (route && link.dataset.nav === route)) link.classList.add('active');
    });
  }

  function bindSidebarToggle() {
    var toggle = document.getElementById('sidebarToggle');
    var backdrop = null;

    function ensureBackdrop() {
      if (!backdrop || !backdrop.parentNode) {
        backdrop = document.querySelector('.crm-mobile-backdrop');
        if (!backdrop) {
          backdrop = document.createElement('div');
          backdrop.className = 'crm-mobile-backdrop';
          document.body.appendChild(backdrop);
        }
        backdrop.addEventListener('click', closeMobileMenu);
      }
      return backdrop;
    }

    function openMobileMenu() {
      document.body.classList.add('sidebar-open');
      var bd = ensureBackdrop();
      setTimeout(function () { bd.classList.add('is-visible'); }, 10);
    }

    function closeMobileMenu() {
      var bd = document.querySelector('.crm-mobile-backdrop');
      if (bd) bd.classList.remove('is-visible');
      document.body.classList.remove('sidebar-open');
    }

    function toggleMobileMenu() {
      if (document.body.classList.contains('sidebar-open')) {
        closeMobileMenu();
      } else {
        openMobileMenu();
      }
    }

    if (toggle && toggle.dataset.sidebarToggleBound !== '1') {
      toggle.dataset.sidebarToggleBound = '1';
      toggle.addEventListener('click', toggleMobileMenu);
    }

    // Close mobile menu on orientation change to desktop
    var mobileResizeHandler = function () {
      if (window.innerWidth > 991.98) {
        closeMobileMenu();
      }
    };
    window.addEventListener('resize', mobileResizeHandler);

    // Close on Escape and on nav link click
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
        closeMobileMenu();
      }
    });

    // Close on nav link click on mobile
    document.addEventListener('click', function (e) {
      var navLink = e.target.closest('.crm-nav .nav-link');
      if (navLink && window.innerWidth <= 991.98 && document.body.classList.contains('sidebar-open')) {
        closeMobileMenu();
      }
    });

    if (!sidebarDocumentClickBound) {
      sidebarDocumentClickBound = true;
      document.addEventListener('click', function (e) {
        if (window.innerWidth > 991.98) return;
        if (!document.body.classList.contains('sidebar-open')) return;
        var insideSidebar = e.target.closest('.crm-sidebar');
        var isToggle = e.target.closest('#sidebarToggle');
        if (!insideSidebar && !isToggle) closeMobileMenu();
      });
    }

    var collapseToggle = document.querySelector('[data-sidebar-collapse-toggle]');
    if (collapseToggle && collapseToggle.dataset.sidebarCollapseBound !== '1') {
      collapseToggle.dataset.sidebarCollapseBound = '1';
      collapseToggle.addEventListener('click', function () {
        if (window.innerWidth <= 991.98) {
          openMobileMenu();
          return;
        }
        applySidebarCollapsedState(!sidebarCollapsed, true);
      });
    }
  }

  function bindSearchToggle() {
    var bar = document.querySelector('.crm-topbar .container-fluid');
    if (!bar) return;
    var toggle = bar.querySelector('[data-search-toggle]');
    var searchGroup = bar.querySelector('[data-global-search]');
    var input = searchGroup ? searchGroup.querySelector('input') : null;
    if (!toggle) return;
    if (toggle.dataset.searchToggleBound === '1') return;
    toggle.dataset.searchToggleBound = '1';

    // Hide inline search group (always collapsed now)
    if (searchGroup) {
      searchGroup.classList.add('is-collapsed');
      bar.classList.add('crm-search-collapsed');
    }

    var modal = null;

    toggle.addEventListener('click', function () {
      if (modal && modal.parentNode) {
        // Already in DOM, show it
        var bsModal = bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal);
        bsModal.show();
        var mi = modal.querySelector('input');
        if (mi) setTimeout(function () { mi.focus(); }, 200);
        return;
      }

      // Create modal
      modal = document.createElement('div');
      modal.className = 'modal fade';
      modal.setAttribute('tabindex', '-1');
      modal.setAttribute('aria-hidden', 'true');
      modal.innerHTML = '<div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-body p-4"><div class="input-group input-group-lg"><span class="input-group-text"><span class="crm-icon crm-input-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span></span><input class="form-control" id="globalSearchModalInput" placeholder="' + t('topbar.search_placeholder', 'Search') + '" autocomplete="off" autofocus></div></div></div></div>';
      document.body.appendChild(modal);

      var modalInput = modal.querySelector('input');
      if (modalInput) {
        // Bind Enter key to search
        modalInput.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') {
            var query = this.value.trim();
            if (query) {
              var bsModal = bootstrap.Modal.getInstance(modal);
              if (bsModal) bsModal.hide();
              window.location.href = 'index.php?route=global-search&q=' + encodeURIComponent(query);
            }
          }
        });
        // Bind Escape to close
        modalInput.addEventListener('keydown', function (e) {
          if (e.key === 'Escape') {
            var bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
          }
        });
      }

      var bsModal = new bootstrap.Modal(modal);
      bsModal.show();
      if (modalInput) setTimeout(function () { modalInput.focus(); }, 300);
    });
  }

  function bindGlobalSearch() {
    var searchGroup = document.querySelector('[data-global-search]');
    if (!searchGroup) return;
    var input = searchGroup.querySelector('input');
    if (!input || input.dataset.searchBound === '1') return;
    if (
      window.CRM.api
      && typeof window.CRM.api.getUser === 'function'
      && document.body
      && document.body.dataset.protected === '1'
      && !window.CRM.api.getUser()
    ) {
      return;
    }

    var canSearch = window.CRM.api
      && typeof window.CRM.api.hasPermission === 'function'
      && window.CRM.api.hasPermission('task.manage');
    var canSemanticSearch = window.CRM.api
      && typeof window.CRM.api.hasPermission === 'function'
      && window.CRM.api.hasPermission('ai.use');

    if (!canSearch && !canSemanticSearch) {
      input.value = '';
      input.disabled = true;
      input.placeholder = t('nav.search_role_restricted', 'Search is not available for your role');
      input.dataset.searchBound = '1';
      return;
    }

    var mode = canSearch ? 'global' : 'semantic';
    var modeToggle = null;
    if (canSemanticSearch) {
      modeToggle = searchGroup.querySelector('[data-search-mode-toggle]');
      if (!modeToggle) {
        modeToggle = document.createElement('button');
        modeToggle.className = 'btn btn-light';
        modeToggle.type = 'button';
        modeToggle.setAttribute('data-search-mode-toggle', '1');
        modeToggle.setAttribute('title', t('nav.ai_search', 'AI Search'));
        modeToggle.setAttribute('aria-label', t('nav.ai_search', 'AI Search'));
        modeToggle.textContent = t('nav.ai_search_short', 'AI');
        input.insertAdjacentElement('afterend', modeToggle);
      }
    }

    searchGroup.style.position = 'relative';
    var dropdown = document.createElement('div');
    dropdown.className = 'dropdown-menu w-100 shadow-sm';
    dropdown.style.display = 'none';
    dropdown.style.maxHeight = '420px';
    dropdown.style.overflowY = 'auto';
    dropdown.setAttribute('data-global-search-results', '1');
    searchGroup.appendChild(dropdown);

    var activeIndex = -1;
    var currentResults = [];
    var timer = null;

    function updateSearchModeUi() {
      if (mode === 'semantic') {
        input.placeholder = t('nav.ai_search', 'AI Search');
      } else {
        input.placeholder = t('topbar.search_placeholder', 'Search');
      }
      if (modeToggle) {
        modeToggle.classList.toggle('crm-btn-primary', mode === 'semantic');
        modeToggle.classList.toggle('btn-light', mode !== 'semantic');
        modeToggle.setAttribute('aria-pressed', mode === 'semantic' ? 'true' : 'false');
      }
    }

    function openDropdown() {
      dropdown.style.display = 'block';
      dropdown.classList.add('show');
    }

    function closeDropdown() {
      dropdown.style.display = 'none';
      dropdown.classList.remove('show');
      activeIndex = -1;
    }

    function applyActiveIndex(nextIndex) {
      activeIndex = nextIndex;
      dropdown.querySelectorAll('[data-search-result-index]').forEach(function (node) {
        var index = Number(node.getAttribute('data-search-result-index'));
        node.classList.toggle('active', index === activeIndex);
      });
    }

    async function performSearch() {
      var query = String(input.value || '').trim();
      if (query.length < 2) {
        closeDropdown();
        dropdown.innerHTML = '';
        currentResults = [];
        return;
      }

      dropdown.innerHTML = '<div class="dropdown-item-text text-muted small">' + t('nav.search_loading', 'Searching...') + '</div>';
      openDropdown();

      try {
        var useSemanticSearch = mode === 'semantic' && window.CRM.ai && typeof window.CRM.ai.semanticSearch === 'function';
        var envelope = useSemanticSearch
          ? await window.CRM.ai.semanticSearch({ query: query, limit: 12 })
          : await window.CRM.api.request('api/v1/search/global', {
            query: { q: query, limit: 12 }
          });
        var data = envelope && envelope.data ? envelope.data : {};
        currentResults = useSemanticSearch
          ? renderSemanticSearchDropdown(dropdown, data, activeIndex)
          : renderSearchDropdown(dropdown, data, activeIndex);
        if (currentResults.length) {
          openDropdown();
          applyActiveIndex(activeIndex >= 0 ? Math.min(activeIndex, currentResults.length - 1) : 0);
        } else {
          openDropdown();
        }
      } catch (error) {
        var message = t('nav.search_unavailable', 'Search is unavailable. Try again later.');
        if (window.CRM.ai && typeof window.CRM.ai.toUiError === 'function') {
          message = window.CRM.ai.toUiError(error, message).message;
        }
        dropdown.innerHTML = '<div class="dropdown-item-text text-danger small">' + escapeHtml(message) + '</div>';
        openDropdown();
        currentResults = [];
      }
    }

    if (modeToggle) {
      modeToggle.addEventListener('click', function () {
        mode = mode === 'semantic' && canSearch ? 'global' : 'semantic';
        currentResults = [];
        activeIndex = -1;
        updateSearchModeUi();
        performSearch();
      });
    }

    input.addEventListener('input', function () {
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(performSearch, 220);
    });

    input.addEventListener('focus', function () {
      if (currentResults.length || String(input.value || '').trim().length >= 2) {
        performSearch();
      }
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (!currentResults.length) return;
        applyActiveIndex(activeIndex >= currentResults.length - 1 ? 0 : activeIndex + 1);
        openDropdown();
        return;
      }
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (!currentResults.length) return;
        applyActiveIndex(activeIndex <= 0 ? currentResults.length - 1 : activeIndex - 1);
        openDropdown();
        return;
      }
      if (e.key === 'Enter') {
        var query = String(input.value || '').trim();
        if (!query) return;
        e.preventDefault();
        var target = currentResults[activeIndex >= 0 ? activeIndex : 0];
        window.location.href = target ? resultUrl(target) : ('index.php?route=tasks&search=' + encodeURIComponent(query));
        return;
      }
      if (e.key === 'Escape') {
        closeDropdown();
      }
    });

    document.addEventListener('click', function (e) {
      if (searchGroup.contains(e.target)) return;
      closeDropdown();
    });

    dropdown.addEventListener('mousemove', function (e) {
      var item = e.target.closest('[data-search-result-index]');
      if (!item) return;
      applyActiveIndex(Number(item.getAttribute('data-search-result-index')));
    });

    updateSearchModeUi();
    input.dataset.searchBound = '1';
  }

  var _navInitDone = false;
  var _chatUnreadTimer = null;
  var _notifTimer = null;

  function startNavPolling() {
    if (_chatUnreadTimer && _notifTimer) return;
    stopNavPolling();
    _chatUnreadTimer = window.setInterval(updateChatUnreadBadges, 120000);
    _notifTimer = window.setInterval(updateNotificationBadges, 120000);
  }

  function stopNavPolling() {
    if (_chatUnreadTimer) {
      window.clearInterval(_chatUnreadTimer);
      _chatUnreadTimer = null;
    }
    if (_notifTimer) {
      window.clearInterval(_notifTimer);
      _notifTimer = null;
    }
  }

  async function init() {
    if (_navInitDone) return;
    _navInitDone = true;
    await loadNavItems();
    renderSidebarSync();
    ensureSidebarCollapseControl();
    restoreSidebarState();
    ensureTopbar();
    markActive();
    bindSidebarToggle();
    bindSearchToggle();
    bindGlobalSearch();
    bindLogoutButtons();
    if (window.CRM && window.CRM.tabLeader) {
      window.CRM.tabLeader.onBecomeLeader(startNavPolling);
      window.CRM.tabLeader.onLoseLeader(stopNavPolling);
      window.CRM.tabLeader.onMessage('nav-chat-unread', function (payload) {
        var count = Number(payload && payload.count || 0) || 0;
        document.querySelectorAll('[data-chat-unread-badge]').forEach(function (badge) {
          badge.classList.toggle('d-none', count <= 0);
          badge.textContent = count > 99 ? '99+' : String(count);
          badge.setAttribute('aria-label', count > 0 ? (t('nav.unread_chats', 'Unread chats: ') + count) : '');
        });
      });
      window.CRM.tabLeader.onMessage('nav-notif-unread', function (payload) {
        var count = Number(payload && payload.count || 0) || 0;
        document.querySelectorAll('[data-nav-notification-badge]').forEach(function (badge) {
          badge.classList.toggle('d-none', count <= 0);
          badge.textContent = count > 99 ? '99+' : String(count);
          badge.setAttribute('aria-label', count > 0 ? (t('nav.unread_notifications', 'Unread notifications: ') + count) : '');
        });
      });
      if (window.CRM.tabLeader.isLeader()) {
        updateChatUnreadBadges();
        updateNotificationBadges();
        startNavPolling();
      }
    } else {
      updateChatUnreadBadges();
      updateNotificationBadges();
      startNavPolling();
    }
  }

  async function updateChatUnreadBadges() {
    if (!window.CRM || !window.CRM.api || typeof window.CRM.api.request !== 'function') return;
    if (!document.body || document.body.dataset.protected !== '1') return;
    try {
      var envelope = await window.CRM.api.request('api/v1/chats/unread-count', { method: 'GET' });
      var count = Number(envelope && envelope.data && envelope.data.count || 0) || 0;
      document.querySelectorAll('[data-chat-unread-badge]').forEach(function (badge) {
        badge.classList.toggle('d-none', count <= 0);
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.setAttribute('aria-label', count > 0 ? (t('nav.unread_chats', 'Unread chats: ') + count) : '');
      });
      if (window.CRM && window.CRM.tabLeader && window.CRM.tabLeader.isLeader()) {
        window.CRM.tabLeader.broadcast('nav-chat-unread', { count: count });
      }
    } catch (e) {}
  }

  async function updateNotificationBadges() {
    if (!window.CRM || !window.CRM.api || typeof window.CRM.api.request !== 'function') return;
    if (!document.body || document.body.dataset.protected !== '1') return;
    try {
      var envelope = await window.CRM.api.request('api/v1/notifications/counters', { method: 'GET' });
      var counters = envelope && envelope.data && envelope.data.counters;
      var count = Number(counters && counters.unread || 0) || 0;
      document.querySelectorAll('[data-nav-notification-badge]').forEach(function (badge) {
        badge.classList.toggle('d-none', count <= 0);
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.setAttribute('aria-label', count > 0 ? (t('nav.unread_notifications', 'Unread notifications: ') + count) : '');
      });
      if (window.CRM && window.CRM.tabLeader && window.CRM.tabLeader.isLeader()) {
        window.CRM.tabLeader.broadcast('nav-notif-unread', { count: count });
      }
    } catch (e) {}
  }

  function bindLogoutButtons() {
    if (!window.CRM || !window.CRM.br1 || typeof window.CRM.br1.bindLogoutButtons !== 'function') return;
    window.CRM.br1.bindLogoutButtons();
  }

  function refreshMenu() {
    clearMenuCache();
    loadNavItems().then(function () {
      renderSidebarSync();
      markActive();
    });
  }

  var CUSTOMIZE_MODAL_ID = 'crmMenuCustomizeModal';

  function navIconByKey(key) {
    var icons = {
      dashboard: '<i class="fa-solid fa-house"></i>',
      ideas: '<i class="fa-regular fa-lightbulb"></i>',
      tasks: '<i class="fa-solid fa-list-check"></i>',
      day: '<i class="fa-solid fa-calendar-day"></i>',
      week: '<i class="fa-solid fa-calendar-week"></i>',
      kanban: '<i class="fa-solid fa-table-columns"></i>',
      gantt: '<i class="fa-solid fa-chart-gantt"></i>',
      projects: '<i class="fa-solid fa-folder-tree"></i>',
      calendar: '<i class="fa-solid fa-calendar-days"></i>',
      counterparties: '<i class="fa-solid fa-address-book"></i>',
      teams: '<i class="fa-solid fa-people-group"></i>',
      intake: '<i class="fa-solid fa-inbox"></i>',
      cycles: '<i class="fa-solid fa-arrows-spin"></i>',
      knowledge: '<i class="fa-solid fa-book-open-reader"></i>',
      analytics: '<i class="fa-solid fa-chart-column"></i>',
      notifications: '<i class="fa-regular fa-bell"></i>',
      admin: '<i class="fa-solid fa-shield-halved"></i>',
      'admin-estimates': '<i class="fa-solid fa-ruler-combined"></i>',
      'admin-modules': '<i class="fa-solid fa-cubes"></i>',
      chat: '<i class="fa-regular fa-comments"></i>',
      docs: '<i class="fa-solid fa-code"></i>',
      'project-modules': '<i class="fa-solid fa-cube"></i>',
      'admin-custom-fields': '<i class="fa-solid fa-pen-to-square"></i>',
      'admin-tags': '<i class="fa-solid fa-tags"></i>',
      'admin-webhooks': '<i class="fa-solid fa-webhook"></i>',
      'admin-templates': '<i class="fa-solid fa-copy"></i>',
      'admin-calendar': '<i class="fa-solid fa-calendar-check"></i>',
      'admin-priorities': '<i class="fa-solid fa-arrow-up-wide-short"></i>',
      mentions: '<i class="fa-solid fa-at"></i>',
      'recycle-bin': '<i class="fa-solid fa-trash-can"></i>',
      'admin-settings': '<i class="fa-solid fa-sliders"></i>',
      'admin-jobs': '<i class="fa-solid fa-gears"></i>',
      'admin-ai': '<i class="fa-solid fa-robot"></i>',
      'admin-workflow': '<i class="fa-solid fa-diagram-project"></i>',
      'admin-sla': '<i class="fa-solid fa-clock"></i>',
      approvals: '<i class="fa-solid fa-clipboard-check"></i>',
      recurring: '<i class="fa-solid fa-arrows-rotate"></i>',
      organizations: '<i class="fa-solid fa-building-columns"></i>',
      profile: '<i class="fa-solid fa-user"></i>'
    };
    return icons[key] || '<i class="fa-solid fa-circle-dot"></i>';
  }

  function ensureCustomizeButton() {
    var nav = document.querySelector('.crm-nav');
    if (!nav || nav.querySelector('[data-menu-customize-btn]')) return;
    if (!document.body || document.body.dataset.protected !== '1') return;

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'nav-link crm-nav-customize-btn';
    btn.setAttribute('data-menu-customize-btn', '1');
    btn.innerHTML = '<span class="crm-nav-icon" aria-hidden="true"><i class="fa-solid fa-sliders"></i></span>'
      + '<span class="crm-nav-label">' + escapeHtml(t('nav.customize_menu', 'Customize menu')) + '</span>';
    btn.title = t('nav.customize_menu', 'Customize menu');
    nav.appendChild(btn);
  }

  function openCustomizeModal() {
    var existing = document.getElementById(CUSTOMIZE_MODAL_ID);
    if (existing) {
      if (existing.parentNode) existing.parentNode.removeChild(existing);
    }

    var currentOrder = navItems.map(function (item) { return item.key; });
    var visibleSet = {};
    navItems.forEach(function (item) { visibleSet[item.key] = true; });

    var customItems = navItems.filter(function (item) { return item.is_custom; });

    var sourceItems = allAvailableItems.length > 0 ? allAvailableItems : getDefaultNavItems();
    var standardItems = sourceItems.slice();

    var sortedItems = standardItems.slice().sort(function (a, b) {
      var ai = currentOrder.indexOf(a.key);
      var bi = currentOrder.indexOf(b.key);
      if (ai === -1 && bi === -1) return 0;
      if (ai === -1) return 1;
      if (bi === -1) return -1;
      return ai - bi;
    });

    var listHtml = sortedItems.map(function (item) {
      var isVisible = visibleSet[item.key] !== false;
      var iconHtml = navIconByKey(item.key);
      var label = t(item.i18n, item.label || item.key);
      return buildCustomizeRow(item.key, iconHtml, label, isVisible, false);
    }).join('');

    var customHtml = customItems.map(function (item) {
      var iconHtml = item.icon ? '<i class="' + escapeHtml(item.icon) + '"></i>' : '<i class="fa-solid fa-link"></i>';
      return buildCustomizeRow(item.key, iconHtml, item.label || item.title || item.key, visibleSet[item.key] !== false, true, item.href);
    }).join('');

    var modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = CUSTOMIZE_MODAL_ID;
    modal.setAttribute('tabindex', '-1');
    modal.setAttribute('aria-hidden', 'true');

    modal.innerHTML = '<div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">'
      + '<div class="modal-header">'
      + '<h5 class="modal-title"><i class="fa-solid fa-sliders me-2"></i>' + escapeHtml(t('nav.customize_menu', 'Customize menu')) + '</h5>'
      + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' + escapeHtml(t('page.close', 'Close')) + '"></button>'
      + '</div>'
      + '<div class="modal-body">'
      + '<p class="text-muted small mb-3">' + escapeHtml(t('nav.customize_menu_hint', 'Drag to reorder items, toggle switches to show or hide menu items.')) + '</p>'
      + '<div class="crm-menu-customize-list" data-menu-customize-list>' + listHtml + '</div>'
      + (customHtml ? '<div class="crm-menu-customize-separator"><span>' + escapeHtml(t('nav.custom_items', 'Custom links')) + '</span></div><div class="crm-menu-customize-list" data-menu-customize-custom-list>' + customHtml + '</div>' : '<div class="crm-menu-customize-list" data-menu-customize-custom-list></div>')
      + '<div class="crm-menu-customize-add-section">'
      + '<button type="button" class="btn crm-btn-secondary btn-sm" data-menu-customize-add-link><i class="fa-solid fa-plus me-1"></i>' + escapeHtml(t('nav.add_custom_link', 'Add custom link')) + '</button>'
      + '</div>'
      + '<div class="crm-menu-customize-add-form d-none" data-menu-customize-add-form>'
      + '<div class="row g-2 align-items-end">'
      + '<div class="col-md-4"><label class="form-label small">' + escapeHtml(t('nav.custom_link_title', 'Title')) + '</label><input type="text" class="form-control form-control-sm" data-custom-title placeholder="' + escapeHtml(t('nav.custom_link_title_placeholder', 'My link')) + '"></div>'
      + '<div class="col-md-4"><label class="form-label small">' + escapeHtml(t('nav.custom_link_url', 'URL')) + '</label><input type="url" class="form-control form-control-sm" data-custom-href placeholder="https://example.com"></div>'
      + '<div class="col-md-3"><label class="form-label small">' + escapeHtml(t('nav.custom_link_icon', 'Icon (FA class)')) + '</label><input type="text" class="form-control form-control-sm" data-custom-icon placeholder="fa-solid fa-link"></div>'
      + '<div class="col-md-1"><button type="button" class="btn crm-btn-primary btn-sm w-100" data-menu-customize-add-confirm title="' + escapeHtml(t('common.add', 'Add')) + '"><i class="fa-solid fa-check"></i></button></div>'
      + '</div>'
      + '</div>'
      + '</div>'
      + '<div class="modal-footer">'
      + '<button type="button" class="btn crm-btn-secondary" data-menu-customize-reset>' + escapeHtml(t('nav.reset_defaults', 'Reset to default')) + '</button>'
      + '<button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">' + escapeHtml(t('common.cancel_btn', 'Cancel')) + '</button>'
      + '<button type="button" class="btn crm-btn-primary" data-menu-customize-save>' + escapeHtml(t('common.save', 'Save')) + '</button>'
      + '</div>'
      + '</div></div>';

    document.body.appendChild(modal);

    var bsModal = new bootstrap.Modal(modal);
    bsModal.show();

    initSortable(modal);

    modal.querySelector('[data-menu-customize-save]').addEventListener('click', function () {
      saveCustomizeModal(modal, bsModal);
    });

    modal.querySelector('[data-menu-customize-reset]').addEventListener('click', function () {
      resetCustomizeModal(modal);
    });

    modal.querySelector('[data-menu-customize-add-link]').addEventListener('click', function () {
      var form = modal.querySelector('[data-menu-customize-add-form]');
      if (form) form.classList.toggle('d-none');
    });

    modal.querySelector('[data-menu-customize-add-confirm]').addEventListener('click', function () {
      addCustomItem(modal);
    });

    modal.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && e.target.matches('[data-custom-href]')) {
        e.preventDefault();
        addCustomItem(modal);
      }
    });

    modal.addEventListener('click', function (e) {
      var deleteBtn = e.target.closest('[data-menu-customize-delete]');
      if (deleteBtn) {
        var row = deleteBtn.closest('.crm-menu-customize-item');
        if (row) {
          row.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
          row.style.opacity = '0';
          row.style.transform = 'translateX(20px)';
          setTimeout(function () {
            if (row.parentNode) row.parentNode.removeChild(row);
            updateCustomSectionVisibility(modal);
          }, 200);
        }
      }
    });

    modal.addEventListener('hidden.bs.modal', function () {
      if (modal.parentNode) {
        modal.parentNode.removeChild(modal);
      }
    });
  }

  function buildCustomizeRow(key, iconHtml, label, isVisible, isCustom, customHref) {
    var deleteBtn = isCustom
      ? '<button type="button" class="btn btn-sm crm-btn-ghost crm-btn-icon text-danger" data-menu-customize-delete title="' + escapeHtml(t('common.delete', 'Delete')) + '"><i class="fa-solid fa-xmark"></i></button>'
      : '';
    var hrefAttribute = isCustom ? ' data-custom-href="' + escapeHtml(customHref || '') + '"' : '';
    return '<div class="crm-menu-customize-item' + (isCustom ? ' crm-menu-customize-item--custom' : '') + '" data-key="' + escapeHtml(key) + '" data-is-custom="' + (isCustom ? '1' : '0') + '"' + hrefAttribute + '>'
      + '<span class="crm-menu-customize-drag" title="' + escapeHtml(t('nav.drag_reorder', 'Drag to reorder')) + '"><i class="fa-solid fa-grip-vertical"></i></span>'
      + '<span class="crm-menu-customize-icon">' + iconHtml + '</span>'
      + '<span class="crm-menu-customize-label">' + escapeHtml(label) + '</span>'
      + '<label class="crm-menu-customize-toggle">'
      + '<input type="checkbox" data-toggle-visibility data-key="' + escapeHtml(key) + '"' + (isVisible ? ' checked' : '') + '>'
      + '<span class="crm-toggle-slider"></span>'
      + '</label>'
      + deleteBtn
      + '</div>';
  }

  function addCustomItem(modal) {
    var titleInput = modal.querySelector('[data-custom-title]');
    var hrefInput = modal.querySelector('[data-custom-href]');
    var iconInput = modal.querySelector('[data-custom-icon]');

    var title = (titleInput ? titleInput.value : '').trim();
    var href = (hrefInput ? hrefInput.value : '').trim();
    var icon = (iconInput ? iconInput.value : '').trim();

    if (!title || !href) return;

    var key = 'custom_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
    var iconHtml = icon ? '<i class="' + escapeHtml(icon) + '"></i>' : '<i class="fa-solid fa-link"></i>';
    var rowHtml = buildCustomizeRow(key, iconHtml, title, true, true, href);

    var customList = modal.querySelector('[data-menu-customize-custom-list]');
    if (customList) {
      customList.insertAdjacentHTML('beforeend', rowHtml);
    } else {
      var separator = '<div class="crm-menu-customize-separator"><span>' + escapeHtml(t('nav.custom_items', 'Custom links')) + '</span></div>';
      var listContainer = modal.querySelector('[data-menu-customize-list]');
      if (listContainer) {
        listContainer.insertAdjacentHTML('afterend', separator + '<div class="crm-menu-customize-list" data-menu-customize-custom-list>' + rowHtml + '</div>');
      }
      customList = modal.querySelector('[data-menu-customize-custom-list]');
      if (customList) initSortable(modal);
    }

    updateCustomSectionVisibility(modal);

    if (titleInput) titleInput.value = '';
    if (hrefInput) hrefInput.value = '';
    if (iconInput) iconInput.value = '';

    var form = modal.querySelector('[data-menu-customize-add-form]');
    if (form) form.classList.add('d-none');

    var newRows = modal.querySelectorAll('.crm-menu-customize-item');
    var lastRow = newRows[newRows.length - 1];
    if (lastRow) {
      lastRow.style.animation = 'none';
      lastRow.offsetHeight;
      lastRow.style.animation = 'menuFadeIn 0.25s ease both';
    }
  }

  function updateCustomSectionVisibility(modal) {
    var customList = modal.querySelector('[data-menu-customize-custom-list]');
    var hasCustomItems = customList && customList.querySelectorAll('.crm-menu-customize-item').length > 0;
    var separator = modal.querySelector('.crm-menu-customize-separator');
    if (separator) {
      separator.style.display = hasCustomItems ? '' : 'none';
    }
  }

  function initSortable(modal) {
    var lists = modal.querySelectorAll('.crm-menu-customize-list');
    if (typeof Sortable === 'undefined') return;
    lists.forEach(function (listEl) {
      if (listEl.dataset.sortableInit) return;
      listEl.dataset.sortableInit = '1';
      Sortable.create(listEl, {
        handle: '.crm-menu-customize-drag',
        group: 'menu-customize',
        animation: 180,
        ghostClass: 'crm-menu-customize-ghost',
        chosenClass: 'crm-menu-customize-chosen',
        dragClass: 'crm-menu-customize-dragging',
        easing: 'cubic-bezier(0.22, 1, 0.36, 1)'
      });
    });
  }

  function readCustomizeItems(modal) {
    var items = [];
    var rows = modal.querySelectorAll('.crm-menu-customize-item');
    rows.forEach(function (row) {
      var key = row.getAttribute('data-key') || '';
      var checkbox = row.querySelector('[data-toggle-visibility]');
      var visible = checkbox ? checkbox.checked : true;
      var entry = { key: key, visible: visible };

      if (row.getAttribute('data-is-custom') === '1') {
        var existingCustom = navItems.find(function (ni) { return ni.key === key; });
        if (existingCustom) {
          entry.title = existingCustom.label || existingCustom.title || key;
          entry.href = row.getAttribute('data-custom-href') || existingCustom.href || '#';
          entry.icon = existingCustom.icon || 'fa-solid fa-link';
        } else {
          entry.title = row.querySelector('.crm-menu-customize-label') ? row.querySelector('.crm-menu-customize-label').textContent : key;
          entry.href = row.getAttribute('data-custom-href') || '#';
          entry.icon = 'fa-solid fa-link';
        }
      }

      items.push(entry);
    });
    return items;
  }

  function resetCustomizeModal(modal) {
    var defaultItems = getDefaultNavItems();
    var sortedItems = defaultItems.slice();

    var listHtml = sortedItems.map(function (item) {
      var iconHtml = navIconByKey(item.key);
      var label = t(item.i18n, item.label || item.key);
      return buildCustomizeRow(item.key, iconHtml, label, true, false);
    }).join('');

    var listEl = modal.querySelector('[data-menu-customize-list]');
    if (listEl) {
      listEl.innerHTML = listHtml;
    }

    var customList = modal.querySelector('[data-menu-customize-custom-list]');
    if (customList) {
      customList.innerHTML = '';
    }

    var separator = modal.querySelector('.crm-menu-customize-separator');
    if (separator) separator.style.display = 'none';

    initSortable(modal);
  }

  async function saveCustomizeModal(modal, bsModal) {
    var saveBtn = modal.querySelector('[data-menu-customize-save]');
    if (saveBtn) {
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + escapeHtml(t('common.saving', 'Saving...'));
    }
    var items = readCustomizeItems(modal);
    try {
      await window.CRM.api.request('api/v1/auth/menu/preferences', {
        method: 'PUT',
        body: { items: items }
      });
      clearMenuCache();
      await loadNavItems();
      renderSidebarSync();
      markActive();
      ensureCustomizeButton();
      if (bsModal) bsModal.hide();
    } catch (e) {
      if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.innerHTML = escapeHtml(t('common.save', 'Save'));
      }
    }
  }

  function bindCustomizeButton() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-menu-customize-btn]');
      if (btn) {
        e.preventDefault();
        openCustomizeModal();
      }
    });
  }

  async function init() {
    if (_navInitDone) return;
    _navInitDone = true;
    await loadNavItems();
    renderSidebarSync();
    ensureSidebarCollapseControl();
    restoreSidebarState();
    ensureTopbar();
    markActive();
    bindSidebarToggle();
    bindSearchToggle();
    bindGlobalSearch();
    bindLogoutButtons();
    ensureCustomizeButton();
    bindCustomizeButton();
    if (window.CRM && window.CRM.tabLeader) {
      window.CRM.tabLeader.onBecomeLeader(startNavPolling);
      window.CRM.tabLeader.onLoseLeader(stopNavPolling);
      window.CRM.tabLeader.onMessage('nav-chat-unread', function (payload) {
        var count = Number(payload && payload.count || 0) || 0;
        document.querySelectorAll('[data-chat-unread-badge]').forEach(function (badge) {
          badge.classList.toggle('d-none', count <= 0);
          badge.textContent = count > 99 ? '99+' : String(count);
          badge.setAttribute('aria-label', count > 0 ? (t('nav.unread_chats', 'Unread chats: ') + count) : '');
        });
      });
      window.CRM.tabLeader.onMessage('nav-notif-unread', function (payload) {
        var count = Number(payload && payload.count || 0) || 0;
        document.querySelectorAll('[data-nav-notification-badge]').forEach(function (badge) {
          badge.classList.toggle('d-none', count <= 0);
          badge.textContent = count > 99 ? '99+' : String(count);
          badge.setAttribute('aria-label', count > 0 ? (t('nav.unread_notifications', 'Unread notifications: ') + count) : '');
        });
      });
      if (window.CRM.tabLeader.isLeader()) {
        updateChatUnreadBadges();
        updateNotificationBadges();
        startNavPolling();
      }
    } else {
      updateChatUnreadBadges();
      updateNotificationBadges();
      startNavPolling();
    }
  }

  async function updateChatUnreadBadges() {
    if (!window.CRM || !window.CRM.api || typeof window.CRM.api.request !== 'function') return;
    if (!document.body || document.body.dataset.protected !== '1') return;
    try {
      var envelope = await window.CRM.api.request('api/v1/chats/unread-count', { method: 'GET' });
      var count = Number(envelope && envelope.data && envelope.data.count || 0) || 0;
      document.querySelectorAll('[data-chat-unread-badge]').forEach(function (badge) {
        badge.classList.toggle('d-none', count <= 0);
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.setAttribute('aria-label', count > 0 ? (t('nav.unread_chats', 'Unread chats: ') + count) : '');
      });
      if (window.CRM && window.CRM.tabLeader && window.CRM.tabLeader.isLeader()) {
        window.CRM.tabLeader.broadcast('nav-chat-unread', { count: count });
      }
    } catch (e) {}
  }

  async function updateNotificationBadges() {
    if (!window.CRM || !window.CRM.api || typeof window.CRM.api.request !== 'function') return;
    if (!document.body || document.body.dataset.protected !== '1') return;
    try {
      var envelope = await window.CRM.api.request('api/v1/notifications/counters', { method: 'GET' });
      var counters = envelope && envelope.data && envelope.data.counters;
      var count = Number(counters && counters.unread || 0) || 0;
      document.querySelectorAll('[data-nav-notification-badge]').forEach(function (badge) {
        badge.classList.toggle('d-none', count <= 0);
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.setAttribute('aria-label', count > 0 ? (t('nav.unread_notifications', 'Unread notifications: ') + count) : '');
      });
      if (window.CRM && window.CRM.tabLeader && window.CRM.tabLeader.isLeader()) {
        window.CRM.tabLeader.broadcast('nav-notif-unread', { count: count });
      }
    } catch (e) {}
  }

  function bindLogoutButtons() {
    if (!window.CRM || !window.CRM.br1 || typeof window.CRM.br1.bindLogoutButtons !== 'function') return;
    window.CRM.br1.bindLogoutButtons();
  }

  function refreshMenu() {
    clearMenuCache();
    loadNavItems().then(function () {
      renderSidebarSync();
      markActive();
    });
  }

  return { init: init, refreshMenu: refreshMenu, clearMenuCache: clearMenuCache };
})();
