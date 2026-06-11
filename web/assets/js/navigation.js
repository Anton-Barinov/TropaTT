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
      chevronRight: '<span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>'
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
      'ideas': '<i class="fa-regular fa-lightbulb"></i>',
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
      return items;
    } catch (e) {
      return null;
    }
  }

  async function loadNavItems() {
    // Skip cache for now to use updated defaults
    var fetched = await fetchMenuFromApi();
    if (fetched && fetched.length > 0) {
      navItems = fetched;
      cacheMenu(fetched);
      menuLoaded = true;
      return;
    }

    navItems = getDefaultNavItems();
    menuLoaded = true;
  }

  function getDefaultNavItems() {
    return [
      { key: 'dashboard', i18n: 'nav.dashboard', label: 'Главная', href: 'index.php?route=dashboard' },
      { key: 'ideas', i18n: 'nav.ideas', label: 'Идеи', href: 'index.php?route=ideas' },
      { key: 'tasks', i18n: 'nav.tasks', label: 'Задачи', href: 'index.php?route=tasks' },
      { key: 'day', i18n: 'nav.day', label: 'Мой день', href: 'index.php?route=my-day' },
      { key: 'week', i18n: 'nav.week', label: 'Моя неделя', href: 'index.php?route=my-week' },
      { key: 'kanban', i18n: 'nav.kanban', label: 'Канбан', href: 'index.php?route=kanban' },
      { key: 'gantt', i18n: 'nav.gantt', label: 'Гант', href: 'index.php?route=gantt' },
      { key: 'projects', i18n: 'nav.projects', label: 'Проекты', href: 'index.php?route=projects' },
      { key: 'calendar', i18n: 'nav.calendar', label: 'Календарь', href: 'index.php?route=calendar' },
      { key: 'counterparties', i18n: 'nav.counterparties', label: 'Контрагенты', href: 'index.php?route=counterparties' },
      { key: 'teams', i18n: 'nav.teams', label: 'Команды и отделы', href: 'index.php?route=teams' },
      { key: 'analytics', i18n: 'nav.analytics', label: 'Аналитика', href: 'index.php?route=analytics' },
      { key: 'notifications', i18n: 'nav.notifications', label: 'Уведомления', href: 'index.php?route=notifications' },
      { key: 'chat', i18n: 'nav.chat', label: 'Чаты', href: 'index.php?route=chat' },
      { key: 'admin', i18n: 'nav.admin', label: 'Администрирование', href: 'index.php?route=admin' },
      { key: 'admin-modules', i18n: 'nav.admin_modules', label: 'Модули', href: 'index.php?route=admin-modules' },
      { key: 'docs', i18n: 'nav.docs', label: 'Документация', href: 'index.php?route=docs' }
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

  function resultUrl(item) {
    var type = String(item && item.entity_type || '').trim();
    var publicId = String(item && item.public_id || '').trim();
    var label = String(item && item.label || '').trim();
    if (type === 'task' && publicId) return 'index.php?route=task-detail&task_public_id=' + encodeURIComponent(publicId);
    if (type === 'project' && publicId) return 'index.php?route=project-detail&project_public_id=' + encodeURIComponent(publicId);
    if (type === 'client' && publicId) return 'index.php?route=client-detail&client_public_id=' + encodeURIComponent(publicId);
    if ((type === 'company' || type === 'contact') && label) {
      return 'index.php?route=clients&search=' + encodeURIComponent(label);
    }
    return 'index.php?route=tasks&search=' + encodeURIComponent(label || publicId);
  }

  function resultTypeLabel(type) {
    var map = {
      task: 'Задача',
      project: 'Проект',
      client: 'Клиент',
      company: 'Компания',
      contact: 'Контакт',
      comment: 'Комментарий',
      file: 'Файл'
    };
    return map[String(type || '').trim()] || 'Результат';
  }

  function renderSearchDropdown(container, payload, activeIndex) {
    if (!container) return [];
    var results = [];
    var groups = payload && payload.results && typeof payload.results === 'object' ? payload.results : {};
    ['tasks', 'projects', 'clients', 'companies', 'contacts'].forEach(function (groupKey) {
      var list = Array.isArray(groups[groupKey]) ? groups[groupKey] : [];
      list.forEach(function (item) {
        results.push({
          entity_type: groupKey.slice(0, -1),
          public_id: String(item.public_id || ''),
          label: String(item.title || item.full_name || item.public_id || ''),
          meta: item
        });
      });
    });

    if (!results.length) {
      container.innerHTML = '<div class="dropdown-item-text text-muted small">Ничего не найдено</div>';
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
      } else if (item.entity_type === 'contact') {
        subtitle = String(meta.email || meta.phone || '');
      }
      var activeClass = index === activeIndex ? ' active' : '';
      return '<a class="dropdown-item crm-search-result' + activeClass + '" href="' + resultUrl(item) + '" data-search-result-index="' + index + '">'
        + '<div class="d-flex justify-content-between gap-2"><strong>' + escapeHtml(item.label || 'Без названия') + '</strong><span class="text-muted small">' + escapeHtml(resultTypeLabel(item.entity_type)) + '</span></div>'
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
      container.innerHTML = '<div class="dropdown-item-text text-muted small">AI ничего не нашел</div>';
      return [];
    }

    container.innerHTML = results.slice(0, 12).map(function (item, index) {
      var score = Number(item.meta && item.meta.score || 0);
      var activeClass = index === activeIndex ? ' active' : '';
      var subtitle = resultTypeLabel(item.entity_type) + (score > 0 ? (' · score ' + score.toFixed(2)) : '');
      return '<a class="dropdown-item crm-search-result' + activeClass + '" href="' + resultUrl(item) + '" data-search-result-index="' + index + '">'
        + '<div class="d-flex justify-content-between gap-2"><strong>' + escapeHtml(item.label || 'Без названия') + '</strong><span class="text-muted small">AI</span></div>'
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
      var html = '<a class="nav-link" data-nav="' + item.key + '" href="' + item.href + '" title="' + safeLabel + '">'
        + '<span class="crm-nav-icon" aria-hidden="true">' + iconHtml + '</span>'
        + '<span class="crm-nav-label">' + safeLabel + '</span>'
        + badge
        + '</a>';

      if (parented[item.key]) {
        parented[item.key].forEach(function (sub) {
          var subLabel = t(sub.i18n, sub.label || sub.key);
          html += '<a class="nav-link crm-nav-sub" data-nav="' + sub.key + '" href="' + sub.href + '" title="' + escapeHtml(subLabel) + '">'
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
      var iconHtml = item.icon || navIcon(item.key);
      var badge = item.key === 'chat'
        ? '<span class="crm-nav-badge d-none" data-chat-unread-badge aria-label=""></span>'
        : (item.key === 'notifications'
          ? '<span class="crm-nav-badge d-none" data-nav-notification-badge aria-label=""></span>'
          : '');
      var html = '<a class="nav-link" data-nav="' + item.key + '" href="' + item.href + '" title="' + safeLabel + '">'
        + '<span class="crm-nav-icon" aria-hidden="true">' + iconHtml + '</span>'
        + '<span class="crm-nav-label">' + safeLabel + '</span>'
        + badge
        + '</a>';

      if (parented[item.key]) {
        parented[item.key].forEach(function (sub) {
          var subLabel = t(sub.i18n, sub.label || sub.key);
          html += '<a class="nav-link crm-nav-sub" data-nav="' + sub.key + '" href="' + sub.href + '" title="' + escapeHtml(subLabel) + '">'
            + '<span class="crm-nav-label ps-4">' + escapeHtml(subLabel) + '</span>'
            + '</a>';
        });
      }

      return html;
    }).join('');
  }

  function sidebarToggleMarkup() {
    return '<button class="crm-sidebar-toggle" type="button" data-sidebar-collapse-toggle aria-label="' + t('sidebar.collapse', 'Свернуть меню') + '" title="' + t('sidebar.collapse', 'Свернуть меню') + '">' + icon('chevronLeft') + '</button>';
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
        ? t('sidebar.expand', 'Развернуть меню')
        : t('sidebar.collapse', 'Свернуть меню');
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

    if (!bar.querySelector('[data-global-chat]')) {
      right.insertAdjacentHTML('beforeend', '<a class="btn crm-btn-ghost crm-btn-icon position-relative" data-global-chat href="index.php?route=chat" aria-label="Чаты" title="Чаты">' + icon('chat') + '<span class="crm-topbar-badge d-none" data-chat-unread-badge aria-label=""></span></a>');
    }

    if (!bar.querySelector('[data-global-notifications]') && !bar.querySelector('[data-bs-toggle="popover"]')) {
      right.insertAdjacentHTML('beforeend', '<button class="btn crm-btn-ghost crm-btn-icon" data-global-notifications data-bs-toggle="popover" data-bs-html="true" data-bs-content="<div class=\'text-muted small\'>...</div>" aria-label="' + t('topbar.notifications', 'Notifications') + '">' + icon('bell') + '</button>');
    }
    var existingNotify = bar.querySelector('[data-global-notifications], [data-bs-toggle="popover"]');
    if (existingNotify && !existingNotify.querySelector('i')) {
      existingNotify.innerHTML = icon('bell');
    }

    if (!bar.querySelector('[data-profile-dropdown]') && !bar.querySelector('.dropdown [data-bs-toggle="dropdown"]')) {
      right.insertAdjacentHTML('beforeend', '<div class="dropdown" data-profile-dropdown><button class="btn crm-btn-ghost dropdown-toggle" data-bs-toggle="dropdown">' + t('topbar.user_fallback', 'User') + '</button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="index.php?route=profile">' + t('topbar.profile', 'Profile') + '</a></li><li><a class="dropdown-item" href="index.php?route=notifications">' + t('topbar.notifications', 'Notifications') + '</a></li><li><hr class="dropdown-divider"></li><li><button class="dropdown-item" type="button" data-action="logout">' + t('topbar.logout', 'Logout') + '</button></li></ul></div>');
    }

    var profileButton = bar.querySelector('[data-profile-dropdown] .dropdown-toggle')
      || bar.querySelector('[data-global-actions] .dropdown .dropdown-toggle')
      || bar.querySelector('.ms-auto .dropdown .dropdown-toggle');
    if (profileButton) {
      profileButton.setAttribute('data-session-user-btn', '1');
    }
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
      modal.innerHTML = '<div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-body p-4"><div class="input-group input-group-lg"><span class="input-group-text"><span class="crm-icon crm-input-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span></span><input class="form-control" id="globalSearchModalInput" placeholder="' + t('topbar.search_placeholder', 'Поиск по TropaTT') + '" autocomplete="off" autofocus></div></div></div></div>';
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
      input.placeholder = 'Поиск недоступен для вашей роли';
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
        modeToggle.setAttribute('title', 'AI-поиск');
        modeToggle.setAttribute('aria-label', 'AI-поиск');
        modeToggle.textContent = 'AI';
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
        input.placeholder = 'AI-поиск';
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

      dropdown.innerHTML = '<div class="dropdown-item-text text-muted small">Ищем совпадения...</div>';
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
        var message = 'Поиск недоступен. Попробуйте позже.';
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
    updateChatUnreadBadges();
    updateNotificationBadges();
    if (window.CRM && window.CRM.tabLeader) {
      window.CRM.tabLeader.onBecomeLeader(startNavPolling);
      window.CRM.tabLeader.onLoseLeader(stopNavPolling);
      window.CRM.tabLeader.onMessage('nav-chat-unread', function (payload) {
        var count = Number(payload && payload.count || 0) || 0;
        document.querySelectorAll('[data-chat-unread-badge]').forEach(function (badge) {
          badge.classList.toggle('d-none', count <= 0);
          badge.textContent = count > 99 ? '99+' : String(count);
          badge.setAttribute('aria-label', count > 0 ? ('Непрочитанных чатов: ' + count) : '');
        });
      });
      window.CRM.tabLeader.onMessage('nav-notif-unread', function (payload) {
        var count = Number(payload && payload.count || 0) || 0;
        document.querySelectorAll('[data-nav-notification-badge]').forEach(function (badge) {
          badge.classList.toggle('d-none', count <= 0);
          badge.textContent = count > 99 ? '99+' : String(count);
          badge.setAttribute('aria-label', count > 0 ? ('Непрочитанных уведомлений: ' + count) : '');
        });
      });
      if (window.CRM.tabLeader.isLeader()) {
        startNavPolling();
      }
    } else {
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
        badge.setAttribute('aria-label', count > 0 ? ('Непрочитанных чатов: ' + count) : '');
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
        badge.setAttribute('aria-label', count > 0 ? ('Непрочитанных уведомлений: ' + count) : '');
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
