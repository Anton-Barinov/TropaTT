<?php declare(strict_types=1); ?>
<?php $title = $t('admin_modules.title', 'TropaTT — Модули'); ?>
<body data-page="admin-modules" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-admin-modules-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="admin_modules.link_admin"><?= htmlspecialchars($t('admin_modules.link_admin', 'Админка'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="admin_modules.breadcrumb"><?= htmlspecialchars($t('admin_modules.breadcrumb', 'Модули'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" data-i18n="admin_modules.page_title"><?= htmlspecialchars($t('admin_modules.page_title', 'Модули'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_modules.subtitle"><?= htmlspecialchars($t('admin_modules.subtitle', 'Управление модулями расширения: установка, активация, деактивация и удаление.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="crm-page-actions"><a class="btn crm-btn-primary" href="index.php?route=admin-modules-install" data-i18n="admin_modules.link_install_module"><i class="fa-solid fa-plus" aria-hidden="true"></i> <?= htmlspecialchars($t('admin_modules.link_install_module', 'Установить модуль'), ENT_QUOTES, 'UTF-8') ?></a></div></div>

<!--
  Segmented view switcher, the same control the rest of the admin area uses
  (#moduleCategoryFilters on this page, the filter bars on the logs and team
  pages): one bordered track whose segments are separated by hairlines, the
  current one tinted. It carries an icon and a live count per view, because the
  count is how a visitor decides which tab to open. Bootstrap's tab plugin drives
  it through data-bs-toggle/data-bs-target, so the panes below are unchanged.
-->
<div class="crm-module-tabs mb-3">
  <ul class="nav" id="moduleTabs" role="tablist" aria-label="<?= htmlspecialchars($t('admin_modules.tabs_aria', 'Разделы страницы модулей'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="admin_modules.tabs_aria">
    <li class="nav-item" role="presentation"><button class="nav-link active" id="moduleInstalledTab" data-bs-toggle="tab" data-bs-target="#installedPane" type="button" role="tab" aria-controls="installedPane" aria-selected="true"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-boxes-stacked"></i></span><span data-i18n="admin_modules.tab_installed"><?= htmlspecialchars($t('admin_modules.tab_installed', 'Установленные'), ENT_QUOTES, 'UTF-8') ?></span><span class="crm-tab-count" id="moduleInstalledCount" hidden>0</span></button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="moduleMarketplaceTab" data-bs-toggle="tab" data-bs-target="#marketplacePane" type="button" role="tab" aria-controls="marketplacePane" aria-selected="false"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-store"></i></span><span data-i18n="admin_modules.tab_marketplace"><?= htmlspecialchars($t('admin_modules.tab_marketplace', 'Маркетплейс'), ENT_QUOTES, 'UTF-8') ?></span><span class="crm-tab-count" id="moduleMarketplaceCount" hidden>0</span></button></li>
  </ul>
</div>

<div class="tab-content" id="moduleTabContent">
<div class="tab-pane fade show active" id="installedPane" role="tabpanel" aria-labelledby="moduleInstalledTab" tabindex="0">

<div class="crm-module-filters d-flex flex-wrap align-items-center gap-2 mb-3" id="moduleFilterBar">
    <span class="small text-muted" data-i18n="admin_modules.filter_label"><?= htmlspecialchars($t('admin_modules.filter_label', 'Фильтр:'), ENT_QUOTES, 'UTF-8') ?></span>
    <div class="btn-group btn-group-sm flex-wrap" id="moduleCategoryFilters" role="group" aria-label="<?= htmlspecialchars($t('admin_modules.filter_aria', 'Фильтр по категориям'), ENT_QUOTES, 'UTF-8') ?>"></div>
</div>

<div class="crm-card crm-section-card p-0 mb-3 crm-admin-modules-table-card">
<div class="crm-bulk-actions d-none align-items-center flex-wrap gap-2 p-2 border-bottom" id="moduleBulkToolbar">
    <span class="me-1 small text-muted" id="moduleBulkLabel"><strong id="bulkCount">0</strong> <span data-i18n="admin_modules.bulk_selected"><?= htmlspecialchars($t('admin_modules.bulk_selected', 'выбрано'), ENT_QUOTES, 'UTF-8') ?></span></span>
    <button type="button" class="btn btn-sm crm-btn-primary" id="bulkInstallBtn" data-i18n="admin_modules.bulk_install"><i class="fa-solid fa-download" aria-hidden="true"></i> <?= htmlspecialchars($t('admin_modules.bulk_install', 'Установить'), ENT_QUOTES, 'UTF-8') ?></button>
    <button type="button" class="btn btn-sm crm-btn-success" id="bulkActivateBtn" data-i18n="admin_modules.bulk_activate"><i class="fa-solid fa-play" aria-hidden="true"></i> <?= htmlspecialchars($t('admin_modules.bulk_activate', 'Активировать'), ENT_QUOTES, 'UTF-8') ?></button>
    <button type="button" class="btn btn-sm crm-btn-warning" id="bulkDeactivateBtn" data-i18n="admin_modules.bulk_deactivate"><i class="fa-solid fa-pause" aria-hidden="true"></i> <?= htmlspecialchars($t('admin_modules.bulk_deactivate', 'Деактивировать'), ENT_QUOTES, 'UTF-8') ?></button>
    <button type="button" class="btn btn-sm crm-btn-danger" id="bulkUninstallBtn" data-i18n="admin_modules.bulk_uninstall"><i class="fa-solid fa-trash-can" aria-hidden="true"></i> <?= htmlspecialchars($t('admin_modules.bulk_uninstall', 'Удалить'), ENT_QUOTES, 'UTF-8') ?></button>
    <button type="button" class="btn btn-sm crm-btn-danger" id="bulkPurgeBtn" data-i18n="admin_modules.bulk_purge"><i class="fa-solid fa-eraser" aria-hidden="true"></i> <?= htmlspecialchars($t('admin_modules.bulk_purge', 'Удалить полностью'), ENT_QUOTES, 'UTF-8') ?></button>
    <button type="button" class="btn btn-sm crm-btn-secondary" id="bulkClearBtn" data-i18n="admin_modules.bulk_clear"><i class="fa-solid fa-xmark" aria-hidden="true"></i> <?= htmlspecialchars($t('admin_modules.bulk_clear', 'Снять выбор'), ENT_QUOTES, 'UTF-8') ?></button>
</div>
<div class="table-responsive"><table class="table crm-table mb-0"><thead><tr><th style="width:40px" class="text-center"><input type="checkbox" id="selectAllModules" aria-label="<?= htmlspecialchars($t('admin_modules.select_all', 'Выбрать все модули'), ENT_QUOTES, 'UTF-8') ?>"></th><th data-i18n="admin_modules.th_module"><?= htmlspecialchars($t('admin_modules.th_module', 'Модуль'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_modules.th_version"><?= htmlspecialchars($t('admin_modules.th_version', 'Версия'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_modules.th_vendor"><?= htmlspecialchars($t('admin_modules.th_vendor', 'Вендор'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_modules.th_status"><?= htmlspecialchars($t('admin_modules.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:220px" data-i18n="admin_modules.th_actions"><?= htmlspecialchars($t('admin_modules.th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="moduleTableBody">
<tr><td colspan="6" class="text-muted" data-i18n="admin_modules.loading"><?= htmlspecialchars($t('admin_modules.loading', 'Загрузка списка модулей...'), ENT_QUOTES, 'UTF-8') ?></td></tr>
</tbody></table></div>
</div>
</div>

<div class="tab-pane fade" id="marketplacePane" role="tabpanel" aria-labelledby="moduleMarketplaceTab" tabindex="0">
<div class="crm-card crm-section-card p-3 mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="flex-grow-1" style="min-width:220px">
            <input type="search" class="form-control form-control-sm" id="mpSearch" autocomplete="off" placeholder="<?= htmlspecialchars($t('admin_modules.mp_search_placeholder', 'Поиск по каталогу модулей…'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_modules.mp_search_placeholder" aria-label="<?= htmlspecialchars($t('admin_modules.mp_search_placeholder', 'Поиск по каталогу модулей…'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="admin_modules.mp_search_placeholder">
        </div>
        <button type="button" class="btn btn-sm crm-btn-primary" id="mpSearchBtn" data-i18n="admin_modules.mp_search"><?= htmlspecialchars($t('admin_modules.mp_search', 'Найти'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm crm-btn-secondary" id="mpRefresh" title="<?= htmlspecialchars($t('admin_modules.mp_refresh', 'Обновить каталог'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="admin_modules.mp_refresh" aria-label="<?= htmlspecialchars($t('admin_modules.mp_refresh', 'Обновить каталог'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="admin_modules.mp_refresh"><i class="fa-solid fa-rotate" aria-hidden="true"></i></button>
        <a class="btn btn-sm crm-btn-secondary" id="mpOpenCatalog" href="https://marketplace.tropatt.com/" target="_blank" rel="noopener noreferrer" title="<?= htmlspecialchars($t('admin_modules.mp_open_catalog', 'Открыть каталог'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="admin_modules.mp_open_catalog" aria-label="<?= htmlspecialchars($t('admin_modules.mp_open_catalog', 'Открыть каталог'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="admin_modules.mp_open_catalog"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
    </div>
    <div class="btn-group btn-group-sm flex-wrap mt-2" id="mpCategoryFilters" role="group" aria-label="<?= htmlspecialchars($t('admin_modules.filter_aria', 'Фильтр по категориям'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="admin_modules.filter_aria"></div>
</div>

<div id="mpAlert" role="status" aria-live="polite"></div>

<div class="row g-3" id="mpGrid"></div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 d-none" id="mpPager">
    <span class="small text-muted" id="mpPagerInfo"></span>
    <div class="btn-group btn-group-sm">
        <button type="button" class="btn crm-btn-secondary" id="mpPrev" data-i18n="admin_modules.mp_prev"><?= htmlspecialchars($t('admin_modules.mp_prev', 'Назад'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn crm-btn-secondary" id="mpNext" data-i18n="admin_modules.mp_next"><?= htmlspecialchars($t('admin_modules.mp_next', 'Вперёд'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
</div>
</div>
</div>

</main></div></div>

<script nonce="<?= $csp_nonce ?>">
(function () {
    var tableBody = document.getElementById('moduleTableBody');
    if (!tableBody) return;

    // `modulesLoaded`/`modulesError` describe the *installed-modules* list, which
    // the marketplace tab needs to classify its cards. Without them the grid
    // treated "list not here yet" as "module not installed" and offered an
    // install button for a module that is registered or already on disk — the
    // click could then only answer 409 ALREADY_INSTALLED / MODULE_DISCOVERED_LOCALLY.
    var state = { selected: {}, modules: [], filter: 'all', modulesLoaded: false, modulesError: false };
    var COLSPAN = 6;
    var CATEGORY_ORDER = ['migration', 'calendar', 'integration', 'productivity', 'diagram'];

    function esc(value) {
        if (window.CRM && window.CRM.text && typeof window.CRM.text.escapeHtml === 'function') {
            return window.CRM.text.escapeHtml(value);
        }
        return String(value || '').replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c] || c; });
    }

    function notify(message, type) {
        if (window.CRM.br1 && typeof window.CRM.br1.notify === 'function') {
            window.CRM.br1.notify(message, type || 'success');
        }
    }

    function getSelectedNames() {
        return Object.keys(state.selected).filter(function (name) { return state.selected[name]; });
    }

    function categoryLabel(key) {
        var map = {
            migration: window.CRM.i18n.t('admin_modules.cat_migration', 'Миграции'),
            calendar: window.CRM.i18n.t('admin_modules.cat_calendar', 'Календари'),
            integration: window.CRM.i18n.t('admin_modules.cat_integration', 'Интеграции'),
            productivity: window.CRM.i18n.t('admin_modules.cat_productivity', 'Продуктивность'),
            diagram: window.CRM.i18n.t('admin_modules.cat_diagram', 'Диаграммы')
        };
        return Object.prototype.hasOwnProperty.call(map, key) ? map[key] : key;
    }

    function visibleModules() {
        if (state.filter === 'all') return state.modules;
        return state.modules.filter(function (m) { return (m.category || '') === state.filter; });
    }

    function renderFilters() {
        var container = document.getElementById('moduleCategoryFilters');
        if (!container) return;
        var seen = {};
        var cats = [];
        state.modules.forEach(function (m) {
            var c = m.category || '';
            if (c && !seen[c]) { seen[c] = true; cats.push(c); }
        });
        cats.sort(function (a, b) {
            var ia = CATEGORY_ORDER.indexOf(a);
            var ib = CATEGORY_ORDER.indexOf(b);
            if (ia === -1) ia = 999;
            if (ib === -1) ib = 999;
            return (ia - ib) || (a < b ? -1 : 1);
        });
        var html = '<button type="button" class="btn btn-sm crm-btn-secondary module-filter' + (state.filter === 'all' ? ' active' : '') + '" data-cat="all">' + window.CRM.i18n.t('admin_modules.cat_all', 'Все') + '</button>';
        cats.forEach(function (c) {
            html += '<button type="button" class="btn btn-sm crm-btn-secondary module-filter' + (state.filter === c ? ' active' : '') + '" data-cat="' + esc(c) + '">' + esc(categoryLabel(c)) + '</button>';
        });
        container.innerHTML = html;
        container.querySelectorAll('.module-filter').forEach(function (btn) {
            btn.addEventListener('click', function () {
                state.filter = this.getAttribute('data-cat') || 'all';
                renderFilters();
                renderRows();
            });
        });
    }

    function updateBulkToolbar() {
        var count = getSelectedNames().length;
        var toolbar = document.getElementById('moduleBulkToolbar');
        var countEl = document.getElementById('bulkCount');
        if (toolbar) toolbar.classList.toggle('d-none', count === 0);
        if (countEl) countEl.textContent = String(count);
        updateSelectAllState();
    }

    function updateSelectAllState() {
        var selectAll = document.getElementById('selectAllModules');
        if (!selectAll) return;
        var visible = visibleModules();
        var total = visible.length;
        var selected = visible.filter(function (m) { return !!state.selected[m.name]; }).length;
        if (total === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
            return;
        }
        selectAll.checked = selected === total;
        selectAll.indeterminate = selected > 0 && selected < total;
    }

    function resetSelection() {
        state.selected = {};
        var selectAll = document.getElementById('selectAllModules');
        if (selectAll) { selectAll.checked = false; selectAll.indeterminate = false; }
        updateBulkToolbar();
    }

    function loadModules() {
        tableBody.innerHTML = '<tr><td colspan="' + COLSPAN + '" class="text-muted">' + window.CRM.i18n.t('admin_modules.loading', 'Загрузка...') + '</td></tr>';
        resetSelection();

        if (!window.CRM || !window.CRM.api || typeof window.CRM.api.request !== 'function') {
            tableBody.innerHTML = '<tr><td colspan="' + COLSPAN + '" class="text-muted">' + window.CRM.i18n.t('admin_modules.waiting_api', 'Ожидание инициализации API...') + '</td></tr>';
            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(loadModules);
            } else {
                setTimeout(loadModules, 200);
            }
            return;
        }

        if (!window.CRM.text || typeof window.CRM.text.escapeHtml !== 'function') {
            tableBody.innerHTML = '<tr><td colspan="' + COLSPAN + '" class="text-muted">' + window.CRM.i18n.t('admin_modules.waiting_text', 'Ожидание текстовых утилит...') + '</td></tr>';
            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(loadModules);
            } else {
                setTimeout(loadModules, 200);
            }
            return;
        }

        // Returns the request promise so callers can refresh dependent views
        // (the marketplace tab marks already-installed modules) after the list
        // is actually up to date.
        return window.CRM.api.request('api/v1/modules', { method: 'GET', timeoutMs: 30000 })
            .then(function (env) {
                var modules = env.data || [];
                state.modules = modules;
                state.modulesLoaded = true;
                state.modulesError = false;
                setTabCount('moduleInstalledCount', modules.length);
                if (modules.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="' + COLSPAN + '" class="text-muted">' + window.CRM.i18n.t('admin_modules.empty', 'Модули не найдены.') + ' <a href="index.php?route=admin-modules-install">' + window.CRM.i18n.t('admin_modules.link_install_first', 'Установить первый модуль') + '</a></td></tr>';
                    renderFilters();
                    updateBulkToolbar();
                    mpRefreshCardStates();
                    return;
                }

                renderFilters();
                renderRows();
                mpRefreshCardStates();
            })
            .catch(function (err) {
                state.modulesError = true;
                state.modulesLoaded = false;
                // No count is honest here: the list could not be read.
                setTabCount('moduleInstalledCount', null);
                tableBody.innerHTML = '<tr><td colspan="' + COLSPAN + '" class="text-danger">' + window.CRM.i18n.t('admin_modules.error_load', 'Ошибка загрузки') + ': ' + esc((err.envelope && err.envelope.message) || (err.message) || window.CRM.i18n.t('admin_modules.unknown_error', 'Неизвестная ошибка')) + '</td></tr>';
                // The catalogue may already be on screen; its cards must stop
                // claiming to know the module state they could not read.
                mpRefreshCardStates();
            });
    }

    /**
     * Re-classify the marketplace cards after the installed-modules list
     * arrived (or failed). Without this the grid kept the classification it
     * derived while the list was still in flight, so a registered module showed
     * an install button for the rest of the session.
     */
    /**
     * Write a count onto a view tab. Pass `null` to hide it: a count that could
     * not be read (or has not arrived) is worse than no count at all, because
     * the tab is the only place the visitor sees how much is behind it.
     */
    function setTabCount(id, value) {
        var node = document.getElementById(id);
        if (!node) return;
        if (value === null || value === undefined || isNaN(value)) {
            node.hidden = true;
            node.textContent = '';
            return;
        }
        node.textContent = String(value);
        node.hidden = false;
    }

    /**
     * Keep `aria-selected` in step with the class the tab plugin toggles.
     * Bootstrap moves `.active` between the triggers; the attribute is what a
     * screen reader announces, and it is authored in the markup, so it has to be
     * updated by hand or the announced tab sticks to the first one forever.
     */
    function syncTabAria() {
        var list = document.getElementById('moduleTabs');
        if (!list) return;
        list.querySelectorAll('[role="tab"]').forEach(function (tab) {
            tab.setAttribute('aria-selected', tab.classList.contains('active') ? 'true' : 'false');
        });
    }

    function mpRefreshCardStates() {
        if (mpState.loaded && !mpState.loading) {
            mpRenderGrid();
        }
    }

    /**
     * Whether the installed-modules list is in hand: 'ready' once it arrived,
     * 'error' when the request failed, 'pending' while it is still in flight.
     */
    function mpListReadiness() {
        if (state.modulesLoaded) return 'ready';
        return state.modulesError ? 'error' : 'pending';
    }

    function renderRows() {
        var modules = visibleModules();
        if (modules.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="' + COLSPAN + '" class="text-muted">' + window.CRM.i18n.t('admin_modules.empty_filter', 'Нет модулей в выбранной категории.') + '</td></tr>';
            updateBulkToolbar();
            return;
        }

        var rows = '';
        modules.forEach(function (m) {
            var statusClass = m.is_active ? 'badge bg-success' : (m.status === 'installed' ? 'badge bg-warning' : 'badge bg-secondary');
            var statusText = m.is_active ? window.CRM.i18n.t('admin_modules.state_active', 'Активен') : (m.status === 'installed' ? window.CRM.i18n.t('admin_modules.state_installed', 'Установлен') : window.CRM.i18n.t('admin_modules.state_discovered', 'Обнаружен'));
            var actions = '';
            if (m.status === 'not_installed') {
                actions = '<button class="btn btn-sm crm-btn-primary module-install me-1" data-name="' + esc(m.name) + '" title="' + window.CRM.i18n.t('admin_modules.title_install', 'Установить модуль') + '" data-i18n-title="admin_modules.title_install" aria-label="' + window.CRM.i18n.t('admin_modules.aria_install', 'Установить модуль') + ' ' + esc(m.name) + '" data-i18n-aria-label="admin_modules.aria_install"><i class="fa-solid fa-download" aria-hidden="true"></i> ' + window.CRM.i18n.t('admin_modules.btn_install', 'Установить') + '</button>';
            } else if (m.is_active) {
                actions = '<button class="btn btn-sm crm-btn-warning module-deact me-1" data-name="' + esc(m.name) + '" title="' + window.CRM.i18n.t('admin_modules.title_deactivate', 'Деактивировать модуль') + '" data-i18n-title="admin_modules.title_deactivate" aria-label="' + window.CRM.i18n.t('admin_modules.aria_deactivate', 'Деактивировать модуль') + ' ' + esc(m.name) + '" data-i18n-aria-label="admin_modules.aria_deactivate"><i class="fa-solid fa-pause" aria-hidden="true"></i></button>';
                actions += '<button class="btn btn-sm crm-btn-danger-icon module-remove" data-name="' + esc(m.name) + '" title="' + window.CRM.i18n.t('admin_modules.title_remove', 'Удалить модуль') + '" data-i18n-title="admin_modules.title_remove" aria-label="' + window.CRM.i18n.t('admin_modules.aria_remove', 'Удалить модуль') + ' ' + esc(m.name) + '" data-i18n-aria-label="admin_modules.aria_remove"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>';
            } else {
                actions = '<button class="btn btn-sm crm-btn-success module-act me-1" data-name="' + esc(m.name) + '" title="' + window.CRM.i18n.t('admin_modules.title_activate', 'Активировать модуль') + '" data-i18n-title="admin_modules.title_activate" aria-label="' + window.CRM.i18n.t('admin_modules.aria_activate', 'Активировать модуль') + ' ' + esc(m.name) + '" data-i18n-aria-label="admin_modules.aria_activate"><i class="fa-solid fa-play" aria-hidden="true"></i></button>';
                actions += '<button class="btn btn-sm crm-btn-danger-icon module-remove" data-name="' + esc(m.name) + '" title="' + window.CRM.i18n.t('admin_modules.title_remove', 'Удалить модуль') + '" data-i18n-title="admin_modules.title_remove" aria-label="' + window.CRM.i18n.t('admin_modules.aria_remove', 'Удалить модуль') + ' ' + esc(m.name) + '" data-i18n-aria-label="admin_modules.aria_remove"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>';
            }

            rows += '<tr>';
            if (m.status === 'not_installed') { rows += '<td class="text-center"></td>'; } else { rows += '<td class="text-center"><input type="checkbox" class="module-select" data-name="' + esc(m.name) + '" aria-label="' + window.CRM.i18n.t('admin_modules.select_module', 'Выбрать модуль') + ' ' + esc(m.name) + '"></td>'; }
            rows += '<td><a href="index.php?route=admin-module-detail&module=' + encodeURIComponent(m.name) + '" class="text-decoration-none"><strong>' + esc(m.title || m.name) + '</strong></a>';
            if (m.category) rows += ' <span class="badge bg-light text-muted border crm-module-cat">' + esc(categoryLabel(m.category)) + '</span>';
            rows += '<br><small class="text-muted">' + esc(m.name) + '</small>';
            if (m.description) rows += '<br><small class="text-muted">' + esc(m.description) + '</small>';
            rows += '</td>';
            rows += '<td>' + esc(m.version) + '</td>';
            var vendorLabel = m.author || m.vendor || '';
            rows += '<td>' + (vendorLabel ? (m.author_url ? '<a href="' + esc(m.author_url) + '" target="_blank" rel="noopener noreferrer">' + esc(vendorLabel) + '</a>' : esc(vendorLabel)) : '—') + '</td>';
            rows += '<td><span class="' + statusClass + '">' + statusText + '</span></td>';
            rows += '<td>' + actions + '</td>';
            rows += '</tr>';
        });

        tableBody.innerHTML = rows;
        bindSelection();
        bindActions();
        updateBulkToolbar();
    }

    function bindSelection() {
        tableBody.querySelectorAll('.module-select').forEach(function (checkbox) {
            checkbox.checked = !!state.selected[checkbox.getAttribute('data-name')];
            checkbox.addEventListener('change', function () {
                var name = this.getAttribute('data-name');
                if (this.checked) {
                    state.selected[name] = true;
                } else {
                    delete state.selected[name];
                }
                updateBulkToolbar();
            });
        });
    }

    function moduleNamesPreview(names) {
        var list = names.slice(0, 5).join(', ');
        if (names.length > 5) {
            list += window.CRM.i18n.t('admin_modules.bulk_more', ' и ещё {n}').replace('{n}', String(names.length - 5));
        }
        return list;
    }

    function bulkAction(action, options) {
        var names = getSelectedNames();
        if (names.length === 0) {
            notify(window.CRM.i18n.t('admin_modules.bulk_none', 'Выберите хотя бы один модуль'), 'warning');
            return;
        }

        var preview = moduleNamesPreview(names);
        var message = options.message.replace('{name}', preview).replace('{count}', String(names.length));
        confirmModuleAction({
            title: options.title,
            message: message,
            actionText: options.actionText,
            actionClass: options.actionClass || 'crm-btn-primary'
        }).then(function (ok) {
            if (!ok) return;
            setBulkBusy(true);

            window.CRM.api.request('api/v1/modules/bulk', {
                method: 'POST',
                timeoutMs: 180000,
                body: { action: action, modules: names }
            })
                .then(function (env) {
                    var data = env.data || {};
                    var succeeded = Number(data.succeeded || 0);
                    var failed = Number(data.failed || 0);
                    var msg = window.CRM.i18n.t('admin_modules.bulk_result', 'Выполнено: {ok} успешно, {fail} с ошибками')
                        .replace('{ok}', String(succeeded))
                        .replace('{fail}', String(failed));
                    if (failed > 0 && data.results) {
                        var failures = data.results.filter(function (r) { return !r.success; }).map(function (r) { return r.name; });
                        msg += ' (' + failures.slice(0, 5).join(', ') + (failures.length > 5 ? ', …' : '') + ')';
                    }
                    notify(msg, failed > 0 ? 'warning' : 'success');
                    try { localStorage.removeItem('crm_menu_items'); } catch (e) {}
                    if (window.CRM.navigation && typeof window.CRM.navigation.refreshMenu === 'function') {
                        window.CRM.navigation.refreshMenu();
                    }
                    loadModules();
                })
                .catch(function (err) {
                    setBulkBusy(false);
                    notify(window.CRM.i18n.t('admin_modules.error_action', 'Ошибка') + ': ' + esc((err.envelope && err.envelope.message) || (err.message) || ''), 'error');
                });
        });
    }

    function setBulkBusy(busy) {
        ['bulkInstallBtn', 'bulkActivateBtn', 'bulkDeactivateBtn', 'bulkUninstallBtn', 'bulkPurgeBtn', 'bulkClearBtn'].forEach(function (id) {
            var btn = document.getElementById(id);
            if (btn) btn.disabled = busy;
        });
    }

    function bindBulkToolbar() {
        var selectAll = document.getElementById('selectAllModules');
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                var checked = this.checked;
                visibleModules().forEach(function (m) {
                    if (checked) state.selected[m.name] = true;
                    else delete state.selected[m.name];
                });
                tableBody.querySelectorAll('.module-select').forEach(function (checkbox) {
                    checkbox.checked = checked;
                });
                updateBulkToolbar();
            });
        }

        function onBulk(id, action, options) {
            var btn = document.getElementById(id);
            if (btn) btn.addEventListener('click', function () { bulkAction(action, options); });
        }

        onBulk('bulkInstallBtn', 'install', {
            title: window.CRM.i18n.t('admin_modules.bulk_install_title', 'Установить выбранные модули?'),
            message: window.CRM.i18n.t('admin_modules.bulk_install_msg', 'Будут установлены модули: {name}.'),
            actionText: window.CRM.i18n.t('admin_modules.bulk_install', 'Установить'),
            actionClass: 'crm-btn-primary'
        });
        onBulk('bulkActivateBtn', 'activate', {
            title: window.CRM.i18n.t('admin_modules.bulk_activate_title', 'Активировать выбранные модули?'),
            message: window.CRM.i18n.t('admin_modules.bulk_activate_msg', 'Будут активированы модули: {name}.'),
            actionText: window.CRM.i18n.t('admin_modules.bulk_activate', 'Активировать'),
            actionClass: 'crm-btn-primary'
        });
        onBulk('bulkDeactivateBtn', 'deactivate', {
            title: window.CRM.i18n.t('admin_modules.bulk_deactivate_title', 'Деактивировать выбранные модули?'),
            message: window.CRM.i18n.t('admin_modules.bulk_deactivate_msg', 'Будут деактивированы модули: {name}.'),
            actionText: window.CRM.i18n.t('admin_modules.bulk_deactivate', 'Деактивировать'),
            actionClass: 'crm-btn-danger-soft'
        });
        onBulk('bulkUninstallBtn', 'uninstall', {
            title: window.CRM.i18n.t('admin_modules.bulk_uninstall_title', 'Удалить выбранные модули?'),
            message: window.CRM.i18n.t('admin_modules.bulk_uninstall_msg', 'Модули будут удалены, а их миграции откачены. Файлы модулей останутся на диске. Модули: {name}.'),
            actionText: window.CRM.i18n.t('admin_modules.bulk_uninstall', 'Удалить'),
            actionClass: 'crm-btn-danger-soft'
        });
        onBulk('bulkPurgeBtn', 'purge', {
            title: window.CRM.i18n.t('admin_modules.bulk_purge_title', 'Полностью удалить модули с диска?'),
            message: window.CRM.i18n.t('admin_modules.bulk_purge_msg', 'Модули будут удалены физически вместе с их файлами. Это действие необратимо. Модули: {name}.'),
            actionText: window.CRM.i18n.t('admin_modules.bulk_purge', 'Удалить полностью'),
            actionClass: 'crm-btn-danger-soft'
        });

        var clearBtn = document.getElementById('bulkClearBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                resetSelection();
                tableBody.querySelectorAll('.module-select').forEach(function (checkbox) { checkbox.checked = false; });
            });
        }
    }

    function bindActions() {
        tableBody.querySelectorAll('.module-install').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var name = this.getAttribute('data-name');
                if (!name) return;
                var btnEl = this;
                confirmModuleAction({
                    title: window.CRM.i18n.t('admin_modules.confirm_install_title', 'Установить модуль?'),
                    message: window.CRM.i18n.t('admin_modules.confirm_install_msg', 'Модуль {name} будет установлен и сможет добавить новые возможности в CRM.').replace('{name}', name),
                    actionText: window.CRM.i18n.t('admin_modules.btn_install', 'Установить'),
                    actionClass: 'crm-btn-primary'
                }).then(function (ok) {
                    if (!ok) return;
                    btnEl.disabled = true;
                    btnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
                    moduleAction(name, 'install', btnEl);
                });
            });
        });
        tableBody.querySelectorAll('.module-act').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var name = this.getAttribute('data-name');
                if (!name) return;
                var btnEl = this;
                confirmModuleAction({
                    title: window.CRM.i18n.t('admin_modules.confirm_activate_title', 'Активировать модуль?'),
                    message: window.CRM.i18n.t('admin_modules.confirm_activate_msg', 'Модуль {name} начнет работать в CRM сразу после активации.').replace('{name}', name),
                    actionText: window.CRM.i18n.t('admin_modules.btn_activate', 'Активировать'),
                    actionClass: 'crm-btn-primary'
                }).then(function (ok) {
                    if (!ok) return;
                    btnEl.disabled = true;
                    btnEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                    moduleAction(name, 'activate', btnEl);
                });
            });
        });
        tableBody.querySelectorAll('.module-deact').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var name = this.getAttribute('data-name');
                if (!name) return;
                var btnEl = this;
                confirmModuleAction({
                    title: window.CRM.i18n.t('admin_modules.confirm_deactivate_title', 'Деактивировать модуль?'),
                    message: window.CRM.i18n.t('admin_modules.confirm_deactivate_msg', 'Модуль {name} перестанет работать, но останется установленным.').replace('{name}', name),
                    actionText: window.CRM.i18n.t('admin_modules.btn_deactivate', 'Деактивировать'),
                    actionClass: 'crm-btn-danger-soft'
                }).then(function (ok) {
                    if (!ok) return;
                    btnEl.disabled = true;
                    btnEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                    moduleAction(name, 'deactivate', btnEl);
                });
            });
        });
        tableBody.querySelectorAll('.module-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var name = this.getAttribute('data-name');
                if (!name) return;
                var btnEl = this;
                confirmModuleAction({
                    title: window.CRM.i18n.t('admin_modules.confirm_remove_title', 'Удалить модуль?'),
                    message: window.CRM.i18n.t('admin_modules.confirm_remove_msg', 'Модуль {name} будет удален, а его миграции будут откачены. Это действие нельзя выполнить случайно.').replace('{name}', name),
                    actionText: window.CRM.i18n.t('admin_modules.btn_remove', 'Удалить'),
                    actionClass: 'crm-btn-danger-soft'
                }).then(function (ok) {
                    if (!ok) return;
                    btnEl.disabled = true;
                    btnEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                    moduleAction(name, 'remove', btnEl);
                });
            });
        });
    }

    function confirmModuleAction(options) {
        var modal = document.getElementById('crmConfirmModal');
        var title = document.getElementById('crmConfirmTitle');
        var body = document.getElementById('crmConfirmBody');
        var action = document.getElementById('crmConfirmActionBtn');

        if (!modal || !title || !body || !action) {
            notify(window.CRM.i18n.t('admin_modules.error_confirm_open', 'Не удалось открыть окно подтверждения'), 'error');
            return Promise.resolve(false);
        }

        return new Promise(function (resolve) {
            var backdrop = document.createElement('div');
            var originalClass = action.className;
            var settled = false;

            function cleanup(result) {
                if (settled) return;
                settled = true;
                modal.classList.remove('show');
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('modal-open');
                action.className = originalClass;
                action.removeEventListener('click', onConfirm);
                modal.removeEventListener('click', onModalClick);
                document.removeEventListener('keydown', onKeydown);
                modal.querySelectorAll('[data-bs-dismiss="modal"], .btn-close').forEach(function (btn) {
                    btn.removeEventListener('click', onCancel);
                });
                if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
                resolve(result);
            }

            function onConfirm() { cleanup(true); }
            function onCancel() { cleanup(false); }
            function onModalClick(event) {
                if (event.target === modal) cleanup(false);
            }
            function onKeydown(event) {
                if (event.key === 'Escape') cleanup(false);
            }

            title.textContent = options.title || window.CRM.i18n.t('admin_modules.confirm_default_title', 'Подтвердите действие');
            body.innerHTML = '<p>' + esc(options.message || window.CRM.i18n.t('admin_modules.confirm_default_msg', 'Продолжить?')) + '</p>';
            action.textContent = options.actionText || window.CRM.i18n.t('admin_modules.btn_confirm', 'Подтвердить');
            action.className = 'btn ' + (options.actionClass || 'crm-btn-danger-soft');
            action.addEventListener('click', onConfirm);
            modal.addEventListener('click', onModalClick);
            document.addEventListener('keydown', onKeydown);
            modal.querySelectorAll('[data-bs-dismiss="modal"], .btn-close').forEach(function (btn) {
                btn.addEventListener('click', onCancel);
            });

            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
            document.body.classList.add('modal-open');
            modal.removeAttribute('aria-hidden');
            modal.style.display = 'block';
            modal.classList.add('show');
            action.focus();
        });
    }

    function moduleAction(name, action, btnEl, afterDone) {
        var endpoints = {
            install: 'api/v1/modules/' + encodeURIComponent(name) + '/install',
            activate: 'api/v1/modules/' + encodeURIComponent(name) + '/activate',
            deactivate: 'api/v1/modules/' + encodeURIComponent(name) + '/deactivate',
            remove: 'api/v1/modules/' + encodeURIComponent(name) + '/uninstall',
        };

        window.CRM.api.request(endpoints[action], { method: 'POST', timeoutMs: 60000 })
            .then(function () {
                try { localStorage.removeItem('crm_menu_items'); } catch (e) {}
                if (window.CRM.navigation && typeof window.CRM.navigation.refreshMenu === 'function') {
                    window.CRM.navigation.refreshMenu();
                }
                notify(window.CRM.i18n.t('admin_modules.action_success', 'Действие выполнено успешно'), 'success');
                Promise.resolve(loadModules()).then(function () {
                    if (typeof afterDone === 'function') afterDone();
                });
            })
            .catch(function (err) {
                if (btnEl) {
                    btnEl.disabled = false;
                    if (action === 'install') btnEl.innerHTML = '<i class="fa-solid fa-download" aria-hidden="true"></i> ' + window.CRM.i18n.t('admin_modules.btn_install', 'Установить');
                    else if (action === 'activate') btnEl.innerHTML = '<i class="fa-solid fa-play" aria-hidden="true"></i>';
                    else if (action === 'deactivate') btnEl.innerHTML = '<i class="fa-solid fa-pause" aria-hidden="true"></i>';
                    else btnEl.innerHTML = '<i class="fa-solid fa-trash-can" aria-hidden="true"></i>';
                }
                notify(window.CRM.i18n.t('admin_modules.error_action', 'Ошибка') + ': ' + esc((err.envelope && err.envelope.message) || (err.message) || ''), 'error');
            });
    }

    /* ------------------------------------------------------------------
     * Official module marketplace (marketplace.tropatt.com)
     * ------------------------------------------------------------------ */
    var mpState = {
        loaded: false,
        loading: false,
        items: [],
        meta: { page: 1, pages: 1, total: 0, limit: 12 },
        q: '',
        category: '',
        categories: [],
        canInstall: false,
        marketplaceUrl: 'https://marketplace.tropatt.com',
        status: null
    };

    function mpT(key, fallback) {
        return window.CRM.i18n.t(key, fallback);
    }

    /**
     * What a marketplace card must offer for a catalog entry.
     *
     * `modules` is the /api/v1/modules payload, and not every entry in it is an
     * installed module: the core build ships module directories, and a directory
     * without a registry entry is reported as status "not_installed"
     * ("Обнаружен"). Treating those as installed (as this page did) hid the
     * install action for modules that still had to be installed, and the
     * marketplace install would have been refused anyway — ModuleRemoteInstaller
     * rejects a target directory that already exists. Such an entry is therefore
     * its own state: install from the local copy instead of downloading.
     *
     * `listState` says whether that payload is actually in hand: 'ready', 'pending'
     * (still loading) or 'error' (the request failed). An unknown list must never
     * answer "available" — the catalogue request and the modules request race each
     * other, and when the catalogue won, a registered or on-disk module showed an
     * install button whose only possible answer was 409 ALREADY_INSTALLED /
     * MODULE_DISCOVERED_LOCALLY (reported from admin-modules on 2026-09-14).
     *
     * Pure on purpose, so the classification can be unit-tested: active —
     * registered and enabled; installed — registered but not enabled;
     * discovered — present on disk, not registered; available — not on disk;
     * unknown / unavailable — the installed-modules list is not (yet) known.
     */
    function mpCardState(fullCode, modules, listState) {
        var code = String(fullCode == null ? '' : fullCode);
        if (code === '') return 'available';
        var readiness = listState || 'ready';
        if (readiness !== 'ready') {
            return readiness === 'error' ? 'unavailable' : 'unknown';
        }
        var list = modules || [];
        for (var i = 0; i < list.length; i++) {
            var entry = list[i];
            if (!entry || entry.name !== code) continue;
            if (entry.is_active) return 'active';
            return entry.status === 'not_installed' ? 'discovered' : 'installed';
        }
        return 'available';
    }

    function mpAlert(kind, message) {
        var host = document.getElementById('mpAlert');
        if (!host) return;
        if (!kind) { host.innerHTML = ''; return; }
        host.innerHTML = '<div class="alert alert-' + esc(kind) + ' py-2 mb-3 small">' + message + '</div>';
    }

    function mpBusy(busy) {
        mpState.loading = busy;
        ['mpSearchBtn', 'mpRefresh', 'mpPrev', 'mpNext'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.disabled = busy;
        });
    }

    /**
     * Catalog categories may not all be part of the core category vocabulary
     * (the marketplace has an "ecommerce" slug, for example). Fall back to the
     * title the marketplace itself translates instead of showing a raw slug.
     */
    function mpCategoryLabel(slug) {
        if (!slug) return '';
        var known = categoryLabel(slug);
        if (known !== slug) return known;

        var list = mpState.categories || [];
        for (var i = 0; i < list.length; i++) {
            if (list[i].slug !== slug) continue;
            var lang = (document.documentElement.getAttribute('lang') || '').toLowerCase();
            if (lang.indexOf('zh') === 0) return list[i].title_zh || list[i].title_ru || slug;
            if (lang.indexOf('ru') === 0) return list[i].title_ru || slug;
            return list[i].title_en || list[i].title_ru || slug;
        }
        return slug;
    }

    function mpIconFallback() {
        return '<span class="crm-mp-icon-fallback" aria-hidden="true"><i class="fa-solid fa-cubes"></i></span>';
    }

    function mpModuleUrl(code) {
        return mpState.marketplaceUrl.replace(/\/+$/, '') + '/module/' + encodeURIComponent(code);
    }

    function mpRetryButton() {
        return '<button type="button" class="btn btn-sm crm-btn-secondary ms-2" data-mp-retry="1">'
            + esc(mpT('admin_modules.mp_retry', 'Повторить')) + '</button>';
    }

    function mpWireRetry() {
        var host = document.getElementById('mpAlert');
        if (!host) return;
        host.querySelectorAll('[data-mp-retry]').forEach(function (btn) {
            btn.addEventListener('click', function () { mpLoad(1, true); });
        });
    }

    function mpRenderStatus() {
        var status = mpState.status || {};
        var catalogLink = document.getElementById('mpOpenCatalog');
        if (catalogLink && mpState.marketplaceUrl) {
            catalogLink.setAttribute('href', mpState.marketplaceUrl.replace(/\/+$/, '') + '/');
        }

        var title = esc(mpT('admin_modules.mp_unavailable_title', 'Маркетплейс недоступен'));
        var text = esc(mpT('admin_modules.mp_unavailable_text', 'Сервер каталога не отвечает или интеграция отключена.'));

        if (!status.enabled || !status.configured) {
            mpAlert('warning', '<strong>' + title + '</strong><br>' + text);
            return;
        }
        if (!status.reachable) {
            mpAlert('danger', '<strong>' + title + '</strong><br>' + text + mpRetryButton());
            mpWireRetry();
            return;
        }
        if (!mpState.canInstall) {
            mpAlert('info', esc(mpT('admin_modules.mp_root_required', 'Установка доступна только главному администратору.')));
            return;
        }
        mpAlert(null);
    }

    function mpRenderCategories() {
        var host = document.getElementById('mpCategoryFilters');
        if (!host) return;
        var items = mpState.categories || [];
        if (items.length === 0) { host.innerHTML = ''; return; }

        var html = '<button type="button" class="btn btn-sm crm-btn-secondary module-filter'
            + (mpState.category === '' ? ' active' : '') + '" data-cat="">'
            + esc(mpT('admin_modules.cat_all', 'Все')) + '</button>';
        items.forEach(function (c) {
            var label = mpCategoryLabel(c.slug) || c.title_ru || c.slug;
            html += '<button type="button" class="btn btn-sm crm-btn-secondary module-filter'
                + (mpState.category === c.slug ? ' active' : '') + '" data-cat="' + esc(c.slug) + '">'
                + esc(label) + '</button>';
        });
        host.innerHTML = html;
        host.querySelectorAll('.module-filter').forEach(function (btn) {
            btn.addEventListener('click', function () {
                mpState.category = this.getAttribute('data-cat') || '';
                mpLoad(1);
            });
        });
    }

    function mpRenderGrid() {
        var grid = document.getElementById('mpGrid');
        if (!grid) return;
        var pager = document.getElementById('mpPager');
        var items = mpState.items || [];

        if (items.length === 0) {
            var message = (mpState.q || mpState.category)
                ? mpT('admin_modules.mp_empty_filter', 'Ничего не найдено. Измените запрос или категорию.')
                : mpT('admin_modules.mp_empty', 'В каталоге пока нет опубликованных модулей.');
            grid.innerHTML = '<div class="col-12"><div class="crm-empty-state text-center small">' + esc(message) + '</div></div>';
            if (pager) pager.classList.add('d-none');
            return;
        }

        var html = '';
        items.forEach(function (m) {
            var code = m.full_code || '';
            var cardState = mpCardState(code, state.modules, mpListReadiness());

            html += '<div class="col-12 col-md-6 col-xl-4">';
            html += '<div class="crm-card crm-section-card h-100 p-3 d-flex flex-column">';
            html += '<div class="d-flex align-items-start gap-2">';
            // Most catalog entries ship no icon, and an icon URL can 404; the
            // fallback keeps every card aligned instead of showing a broken image.
            if (m.icon_url) {
                html += '<img class="crm-mp-icon" src="' + esc(m.icon_url) + '" alt="" width="28" height="28" loading="lazy">';
            } else {
                html += mpIconFallback();
            }
            html += '<div class="flex-grow-1"><div class="fw-semibold">' + esc(m.title || code) + '</div><div class="small text-muted">' + esc(code) + '</div></div>';
            if (m.category_slug) {
                html += '<span class="badge bg-light text-muted border crm-module-cat">' + esc(mpCategoryLabel(m.category_slug)) + '</span>';
            }
            html += '</div>';

            if (m.summary) {
                html += '<p class="small text-muted mt-2 mb-2 flex-grow-1">' + esc(m.summary) + '</p>';
            } else {
                html += '<div class="flex-grow-1"></div>';
            }

            var metaParts = [];
            if (m.latest_version) metaParts.push(esc(m.latest_version));
            if (m.price_model === 'free') {
                metaParts.push('<span class="badge bg-success-subtle text-success border border-success-subtle">' + esc(mpT('admin_modules.mp_free', 'Бесплатно')) + '</span>');
            } else if (Number(m.price_minor) > 0) {
                metaParts.push('<span class="badge bg-light text-muted border">' + esc((Number(m.price_minor) / 100).toFixed(2) + ' ' + (m.currency || '')) + '</span>');
            }
            if (Number(m.total_downloads) > 0) {
                metaParts.push(esc(mpT('admin_modules.mp_downloads', 'Загрузок: {n}').replace('{n}', String(m.total_downloads))));
            }
            if (Number(m.rating_count) > 0) {
                metaParts.push(esc(mpT('admin_modules.mp_rating', 'Рейтинг: {n}').replace('{n}', String(m.rating_avg))));
            }
            if (metaParts.length > 0) {
                html += '<div class="d-flex flex-wrap align-items-center gap-2 small text-muted mb-2">' + metaParts.join('<span aria-hidden="true">·</span>') + '</div>';
            }

            html += '<div class="d-flex flex-wrap align-items-center gap-2 mt-auto">';
            if (cardState === 'unknown' || cardState === 'unavailable') {
                // The installed-modules list has not arrived (or failed): the page
                // cannot know whether this module is installable, so it must not
                // offer an install that can only come back as a conflict.
                html += cardState === 'unavailable'
                    ? '<span class="badge bg-danger">' + esc(mpT('admin_modules.state_unavailable', 'Состояние модулей неизвестно')) + '</span>'
                    : '<span class="badge bg-secondary">' + esc(mpT('admin_modules.state_unknown', 'Определяем состояние…')) + '</span>';
                if (cardState === 'unavailable') {
                    html += '<button type="button" class="btn btn-sm crm-btn-secondary mp-reload-modules" data-code="' + esc(code)
                        + '">' + esc(mpT('admin_modules.mp_retry_modules', 'Повторить загрузку')) + '</button>';
                }
            } else if (cardState === 'active' || cardState === 'installed') {
                // Mirrors the installed-modules tab: registered + enabled is
                // "Активен", registered but disabled is "Установлен". A registered
                // but disabled module gets an activate action — the marketplace
                // install with activate=true is exactly what the visitor meant.
                var stateBadge = cardState === 'active'
                    ? '<span class="badge bg-success">' + esc(mpT('admin_modules.state_active', 'Активен')) + '</span>'
                    : '<span class="badge bg-warning">' + esc(mpT('admin_modules.mp_installed', 'Установлен')) + '</span>';
                html += stateBadge;
                if (cardState === 'installed') {
                    html += '<button type="button" class="btn btn-sm crm-btn-primary mp-activate" data-code="' + esc(code)
                        + '" title="' + esc(mpT('admin_modules.mp_activate', 'Активировать')) + '"' + (mpState.canInstall ? '' : ' disabled')
                        + '>' + esc(mpT('admin_modules.mp_activate', 'Активировать')) + '</button>';
                }
            } else if (cardState === 'discovered') {
                // The package is already on disk (shipped by the build), so there
                // is nothing to download: registering the local copy is exactly
                // what the installed-modules tab offers for this status.
                html += '<span class="badge bg-secondary">' + esc(mpT('admin_modules.state_discovered', 'Обнаружен')) + '</span>';
                html += '<button type="button" class="btn btn-sm crm-btn-primary mp-install-local" data-code="' + esc(code)
                    + '" title="' + esc(mpT('admin_modules.title_install', 'Установить модуль')) + '" data-i18n-title="admin_modules.title_install"'
                    + '>' + esc(mpT('admin_modules.btn_install', 'Установить')) + '</button>';
            } else {
                html += '<button type="button" class="btn btn-sm crm-btn-primary mp-install" data-code="' + esc(code)
                    + '" data-title="' + esc(m.title || code) + '"' + (mpState.canInstall ? '' : ' disabled')
                    + '>' + esc(mpT('admin_modules.mp_install', 'Установить')) + '</button>';
            }
            if (code) {
                html += '<a class="btn btn-sm crm-btn-secondary" href="' + esc(mpModuleUrl(code))
                    + '" target="_blank" rel="noopener noreferrer">' + esc(mpT('admin_modules.mp_open', 'Открыть')) + '</a>';
            }
            html += '</div></div></div>';
        });

        grid.innerHTML = html;

        if (pager) {
            var pages = Math.max(1, parseInt(mpState.meta.pages, 10) || 1);
            var page = Math.max(1, parseInt(mpState.meta.page, 10) || 1);
            pager.classList.toggle('d-none', pages <= 1);
            var info = document.getElementById('mpPagerInfo');
            if (info) {
                info.textContent = mpT('admin_modules.mp_total', 'Найдено модулей: {n}').replace('{n}', String(mpState.meta.total || items.length))
                    + ' · ' + mpT('admin_modules.mp_page_of', 'Страница {page} из {pages}')
                        .replace('{page}', String(page)).replace('{pages}', String(pages));
            }
            var prev = document.getElementById('mpPrev');
            var next = document.getElementById('mpNext');
            if (prev) prev.disabled = page <= 1;
            if (next) next.disabled = page >= pages;
        }

        grid.querySelectorAll('.mp-install').forEach(function (btn) {
            btn.addEventListener('click', function () {
                mpInstall(this.getAttribute('data-code') || '', this.getAttribute('data-title') || '', this);
            });
        });

        // Discovered modules are registered from the copy already on disk; the
        // shared action keeps the installed-modules list in sync afterwards.
        grid.querySelectorAll('.mp-install-local').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var self = this;
                moduleAction(this.getAttribute('data-code') || '', 'install', self, function () {
                    mpLoad(parseInt(mpState.meta.page, 10) || 1, true);
                });
            });
        });

        // A registered-but-disabled module: the visitor asked to install it, so
        // offer the step that is actually left — activation.
        grid.querySelectorAll('.mp-activate').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var code = this.getAttribute('data-code') || '';
                var self = this;
                confirmModuleAction({
                    title: mpT('admin_modules.mp_activate_confirm_title', 'Активировать модуль?'),
                    message: mpT('admin_modules.mp_activate_confirm_msg', 'Модуль {name} уже установлен в CRM — он будет активирован без загрузки с маркетплейса.').replace('{name}', code),
                    actionText: mpT('admin_modules.mp_activate', 'Активировать'),
                    actionClass: 'crm-btn-primary'
                }).then(function (ok) {
                    if (!ok) return;
                    self.disabled = true;
                    self.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                    moduleAction(code, 'activate', self, function () {
                        mpLoad(parseInt(mpState.meta.page, 10) || 1, true);
                    });
                });
            });
        });

        // The modules list failed: give the visitor a way to retry the read the
        // card states depend on.
        grid.querySelectorAll('.mp-reload-modules').forEach(function (btn) {
            btn.addEventListener('click', function () {
                loadModules();
            });
        });

        // A remote icon that fails to load must not leave a broken-image marker.
        // The handler is attached in JS because the page CSP (nonce-based) drops
        // inline onerror attributes.
        grid.querySelectorAll('img.crm-mp-icon').forEach(function (img) {
            img.addEventListener('error', function () {
                var fallback = document.createElement('span');
                fallback.innerHTML = mpIconFallback();
                if (this.parentNode && fallback.firstChild) {
                    this.parentNode.replaceChild(fallback.firstChild, this);
                }
            });
        });
    }

    function mpLoad(page, refresh) {
        var targetPage = Math.max(1, parseInt(page, 10) || 1);

        if (!window.CRM || !window.CRM.api || typeof window.CRM.api.request !== 'function') {
            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(function () { mpLoad(targetPage, refresh); });
            } else {
                setTimeout(function () { mpLoad(targetPage, refresh); }, 200);
            }
            return;
        }

        mpBusy(true);
        var grid = document.getElementById('mpGrid');
        if (grid) {
            grid.innerHTML = '<div class="col-12"><div class="text-muted small py-4 text-center">'
                + esc(mpT('admin_modules.mp_loading', 'Загрузка каталога…')) + '</div></div>';
        }

        // Query parameters go through the api client's `query` option: it builds
        // ?route=<route> and appends them as real query parameters. Appending
        // them to the route string instead made the client URL-encode the whole
        // thing and the API answered ROUTE_NOT_FOUND.
        var options = {
            method: 'GET',
            timeoutMs: 30000,
            query: {
                page: targetPage,
                limit: mpState.meta.limit,
                category: mpState.category,
                q: mpState.q
            }
        };
        if (refresh) {
            options.query.refresh = 1;
            // Bypass the client-side reference cache as well, so the refresh
            // button always reflects the marketplace right now.
            options.noCache = true;
        }

        window.CRM.api.request('api/v1/marketplace/catalog', options)
            .then(function (env) {
                var data = env.data || {};
                mpState.items = data.items || [];
                mpState.meta = data.meta || mpState.meta;
                mpState.status = data.status || null;
                mpState.categories = data.categories || mpState.categories;
                mpState.canInstall = !!data.can_install;
                if (data.marketplace_url) mpState.marketplaceUrl = data.marketplace_url;
                mpState.loaded = true;
                mpBusy(false);
                // How much is behind the tab, exactly like the installed tab
                // counts every installed module. The API reports the total of
                // the *current* query (a search for one module answers 1), so the
                // catalogue size is the largest total seen — the tab is opened
                // unfiltered, which is where that number comes from, and it must
                // not shrink to "1" just because the visitor searched.
                var shownTotal = (data.status && data.status.total) || (data.meta && data.meta.total) || 0;
                mpState.catalogTotal = Math.max(mpState.catalogTotal || 0, shownTotal);
                setTabCount('moduleMarketplaceCount', mpState.catalogTotal > 0 ? mpState.catalogTotal : null);

                if (mpState.categories.length === 0) {
                    mpLoadCategories();
                }
                mpRenderStatus();
                mpRenderCategories();
                mpRenderGrid();
            })
            .catch(function (err) {
                mpBusy(false);
                mpState.loaded = true;
                mpState.items = [];
                mpState.status = { enabled: true, configured: true, reachable: false };
                mpRenderStatus();
                mpAlert('danger', '<strong>' + esc(mpT('admin_modules.mp_error', 'Не удалось загрузить каталог маркетплейса.'))
                    + '</strong><br>' + esc((err.envelope && err.envelope.message) || err.message || '')
                    + mpRetryButton());
                mpWireRetry();
                mpRenderGrid();
            });
    }

    function mpLoadCategories() {
        window.CRM.api.request('api/v1/marketplace/categories', { method: 'GET', timeoutMs: 20000 })
            .then(function (env) {
                mpState.categories = ((env.data || {}).categories) || [];
                mpRenderCategories();
            })
            .catch(function () {
                // Categories are decorative: the catalog itself is already usable.
            });
    }

    function mpInstall(code, title, btn) {
        if (!code) return;
        confirmModuleAction({
            title: mpT('admin_modules.mp_confirm_title', 'Установить модуль из маркетплейса?'),
            message: mpT('admin_modules.mp_confirm_msg', 'Модуль {name} будет скачан с маркетплейса и установлен в CRM.').replace('{name}', title || code),
            actionText: mpT('admin_modules.mp_install', 'Установить'),
            actionClass: 'crm-btn-primary'
        }).then(function (ok) {
            if (!ok) return;
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            }

            window.CRM.api.request('api/v1/marketplace/install', {
                method: 'POST',
                timeoutMs: 300000,
                body: { full_code: code, activate: true }
            })
                .then(function (env) {
                    var data = env.data || {};
                    var name = data.name || code;
                    if (data.activated) {
                        notify(mpT('admin_modules.mp_install_success', 'Модуль {name} установлен и активирован.').replace('{name}', name), 'success');
                    } else {
                        notify(mpT('admin_modules.mp_install_success_inactive', 'Модуль {name} установлен. Активируйте его вручную.').replace('{name}', name), 'warning');
                    }
                    try { localStorage.removeItem('crm_menu_items'); } catch (e) {}
                    if (window.CRM.navigation && typeof window.CRM.navigation.refreshMenu === 'function') {
                        window.CRM.navigation.refreshMenu();
                    }
                    Promise.resolve(loadModules()).then(function () { mpLoad(1, true); });
                })
                .catch(function (err) {
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = mpT('admin_modules.mp_install', 'Установить');
                    }
                    var code = (err.envelope && err.envelope.code) || '';
                    var detail = (err.envelope && err.envelope.message) || err.message || '';
                    notify(mpT('admin_modules.mp_install_failed', 'Не удалось установить модуль: {error}').replace('{error}', detail), 'error');

                    // The module turned out to be on disk already (someone else
                    // installed it, or a build shipped it) — the catalog card is
                    // stale, so re-read both lists instead of leaving a button
                    // that can only fail again.
                    if (code === 'MODULE_DISCOVERED_LOCALLY' || code === 'ALREADY_INSTALLED') {
                        Promise.resolve(loadModules()).then(function () { mpLoad(parseInt(mpState.meta.page, 10) || 1, true); });
                    }
                });
        });
    }

    function bindMarketplaceToolbar() {
        var searchInput = document.getElementById('mpSearch');
        var searchBtn = document.getElementById('mpSearchBtn');
        var refreshBtn = document.getElementById('mpRefresh');
        var prevBtn = document.getElementById('mpPrev');
        var nextBtn = document.getElementById('mpNext');

        // The search box is the source of truth for the query: without this, a
        // refresh (or a category click) kept serving the previously applied
        // search text instead of what the admin sees in the field.
        function syncSearch() {
            if (searchInput) {
                mpState.q = String(searchInput.value || '').trim();
            }
        }

        if (searchBtn) {
            searchBtn.addEventListener('click', function () {
                syncSearch();
                mpLoad(1);
            });
        }
        if (searchInput) {
            searchInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    syncSearch();
                    mpLoad(1);
                }
            });
        }
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                syncSearch();
                mpLoad(parseInt(mpState.meta.page, 10) || 1, true);
            });
        }
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                mpLoad(Math.max(1, (parseInt(mpState.meta.page, 10) || 1) - 1));
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                mpLoad((parseInt(mpState.meta.page, 10) || 1) + 1);
            });
        }

        var tabs = document.getElementById('moduleTabs');
        if (tabs) {
            tabs.querySelectorAll('[role="tab"]').forEach(function (node) {
                node.addEventListener('shown.bs.tab', syncTabAria);
            });
            syncTabAria();
        }

        var tab = document.getElementById('moduleMarketplaceTab');
        if (tab) {
            tab.addEventListener('shown.bs.tab', function () {
                if (!mpState.loaded) mpLoad(1, true);
            });
        }
    }

    /**
     * One-click install handed over by marketplace.tropatt.com: the catalog's
     * "Установить в один клик" button redirects to
     * web/index.php?route=admin-modules&install_marketplace=<code>&package_url=…&sha256=…
     * and the CRM used to ignore that redirect completely — the admin landed on
     * the modules page and nothing happened.
     *
     * The package_url/sha256 parameters are deliberately discarded: the release
     * is re-requested server-side through the configured marketplace channel
     * (install-request), so a crafted link can never aim the installer at an
     * arbitrary archive. The tab opens the standard confirm + install flow.
     */
    function mpHandleOneClickHandoff() {
        var params;
        try {
            params = new URLSearchParams(window.location.search);
        } catch (e) {
            return;
        }
        var code = params.get('install_marketplace');
        if (!code) {
            return;
        }
        params.delete('install_marketplace');
        params.delete('package_url');
        params.delete('sha256');
        var qs = params.toString();
        try {
            window.history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : '') + window.location.hash);
        } catch (e) {
            // History may be unavailable — the parameter is inert after this run anyway.
        }

        var tab = document.getElementById('moduleMarketplaceTab');
        if (tab) {
            tab.click(); // activates the tab, its shown handler starts mpLoad()
        }

        var tries = 0;
        var searchedForHandoff = false;
        var timer = setInterval(function () {
            tries++;
            var item = null;
            if (mpState.loaded) {
                var items = mpState.items || [];
                for (var i = 0; i < items.length; i++) {
                    var candidate = items[i];
                    if (String(candidate.full_code || candidate.code || candidate.name || '') === code) {
                        item = candidate;
                        break;
                    }
                }
                if (!item && !searchedForHandoff && tries >= 8) {
                    // Not on the first page of the catalog — search for it by code once.
                    searchedForHandoff = true;
                    mpState.q = code;
                    mpLoad(1);
                    return;
                }
            }
            if (item) {
                clearInterval(timer);
                mpInstall(code, String(item.title || item.name || code), null);
                return;
            }
            if (tries >= 120) {
                // ~30s without the card: give up quietly — mpLoad surfaces its
                // own error when the catalog itself failed to load.
                clearInterval(timer);
            }
        }, 250);
    }

    bindBulkToolbar();
    bindMarketplaceToolbar();
    mpHandleOneClickHandoff();
    loadModules();
})();
</script>
</body>
