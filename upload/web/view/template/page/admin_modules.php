<?php declare(strict_types=1); ?>
<?php $title = $t('admin_modules.title', 'TropaTT — Модули'); ?>
<body data-page="admin-modules" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-admin-modules-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="admin_modules.link_admin"><?= htmlspecialchars($t('admin_modules.link_admin', 'Админка'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="admin_modules.breadcrumb"><?= htmlspecialchars($t('admin_modules.breadcrumb', 'Модули'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" data-i18n="admin_modules.page_title"><?= htmlspecialchars($t('admin_modules.page_title', 'Модули'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_modules.subtitle"><?= htmlspecialchars($t('admin_modules.subtitle', 'Управление модулями расширения: установка, активация, деактивация и удаление.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="crm-page-actions"><a class="btn crm-btn-primary" href="index.php?route=admin-modules-install" data-i18n="admin_modules.link_install_module"><i class="fa-solid fa-plus"></i> <?= htmlspecialchars($t('admin_modules.link_install_module', 'Установить модуль'), ENT_QUOTES, 'UTF-8') ?></a></div></div>

<div class="crm-module-filters d-flex flex-wrap align-items-center gap-2 mb-3" id="moduleFilterBar">
    <span class="small text-muted" data-i18n="admin_modules.filter_label"><?= htmlspecialchars($t('admin_modules.filter_label', 'Фильтр:'), ENT_QUOTES, 'UTF-8') ?></span>
    <div class="btn-group btn-group-sm flex-wrap" id="moduleCategoryFilters" role="group" aria-label="<?= htmlspecialchars($t('admin_modules.filter_aria', 'Фильтр по категориям'), ENT_QUOTES, 'UTF-8') ?>"></div>
</div>

<div class="crm-card crm-section-card p-0 mb-3 crm-admin-modules-table-card">
<div class="crm-bulk-actions d-none align-items-center flex-wrap gap-2 p-2 border-bottom" id="moduleBulkToolbar">
    <span class="me-1 small text-muted" id="moduleBulkLabel"><strong id="bulkCount">0</strong> <span data-i18n="admin_modules.bulk_selected"><?= htmlspecialchars($t('admin_modules.bulk_selected', 'выбрано'), ENT_QUOTES, 'UTF-8') ?></span></span>
    <button type="button" class="btn btn-sm btn-primary" id="bulkInstallBtn" data-i18n="admin_modules.bulk_install"><i class="fa-solid fa-download"></i> <?= htmlspecialchars($t('admin_modules.bulk_install', 'Установить'), ENT_QUOTES, 'UTF-8') ?></button>
    <button type="button" class="btn btn-sm btn-outline-success" id="bulkActivateBtn" data-i18n="admin_modules.bulk_activate"><i class="fa-solid fa-play"></i> <?= htmlspecialchars($t('admin_modules.bulk_activate', 'Активировать'), ENT_QUOTES, 'UTF-8') ?></button>
    <button type="button" class="btn btn-sm btn-outline-warning" id="bulkDeactivateBtn" data-i18n="admin_modules.bulk_deactivate"><i class="fa-solid fa-pause"></i> <?= htmlspecialchars($t('admin_modules.bulk_deactivate', 'Деактивировать'), ENT_QUOTES, 'UTF-8') ?></button>
    <button type="button" class="btn btn-sm btn-outline-danger" id="bulkUninstallBtn" data-i18n="admin_modules.bulk_uninstall"><i class="fa-solid fa-trash"></i> <?= htmlspecialchars($t('admin_modules.bulk_uninstall', 'Удалить'), ENT_QUOTES, 'UTF-8') ?></button>
    <button type="button" class="btn btn-sm btn-danger" id="bulkPurgeBtn" data-i18n="admin_modules.bulk_purge"><i class="fa-solid fa-eraser"></i> <?= htmlspecialchars($t('admin_modules.bulk_purge', 'Удалить полностью'), ENT_QUOTES, 'UTF-8') ?></button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="bulkClearBtn" data-i18n="admin_modules.bulk_clear"><i class="fa-solid fa-xmark"></i> <?= htmlspecialchars($t('admin_modules.bulk_clear', 'Снять выбор'), ENT_QUOTES, 'UTF-8') ?></button>
</div>
<div class="table-responsive"><table class="table crm-table mb-0"><thead><tr><th style="width:40px" class="text-center"><input type="checkbox" id="selectAllModules" aria-label="<?= htmlspecialchars($t('admin_modules.select_all', 'Выбрать все модули'), ENT_QUOTES, 'UTF-8') ?>"></th><th data-i18n="admin_modules.th_module"><?= htmlspecialchars($t('admin_modules.th_module', 'Модуль'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_modules.th_version"><?= htmlspecialchars($t('admin_modules.th_version', 'Версия'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_modules.th_vendor"><?= htmlspecialchars($t('admin_modules.th_vendor', 'Вендор'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_modules.th_status"><?= htmlspecialchars($t('admin_modules.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:220px" data-i18n="admin_modules.th_actions"><?= htmlspecialchars($t('admin_modules.th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="moduleTableBody">
<tr><td colspan="6" class="text-muted" data-i18n="admin_modules.loading"><?= htmlspecialchars($t('admin_modules.loading', 'Загрузка списка модулей...'), ENT_QUOTES, 'UTF-8') ?></td></tr>
</tbody></table></div>
</div>

</main></div></div>

<script>
(function () {
    var tableBody = document.getElementById('moduleTableBody');
    if (!tableBody) return;

    var state = { selected: {}, modules: [], filter: 'all' };
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
        var html = '<button type="button" class="btn btn-sm btn-outline-secondary module-filter' + (state.filter === 'all' ? ' active' : '') + '" data-cat="all">' + window.CRM.i18n.t('admin_modules.cat_all', 'Все') + '</button>';
        cats.forEach(function (c) {
            html += '<button type="button" class="btn btn-sm btn-outline-secondary module-filter' + (state.filter === c ? ' active' : '') + '" data-cat="' + esc(c) + '">' + esc(categoryLabel(c)) + '</button>';
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

        window.CRM.api.request('api/v1/modules', { method: 'GET', timeoutMs: 30000 })
            .then(function (env) {
                var modules = env.data || [];
                state.modules = modules;
                if (modules.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="' + COLSPAN + '" class="text-muted">' + window.CRM.i18n.t('admin_modules.empty', 'Модули не найдены.') + ' <a href="index.php?route=admin-modules-install">' + window.CRM.i18n.t('admin_modules.link_install_first', 'Установить первый модуль') + '</a></td></tr>';
                    renderFilters();
                    updateBulkToolbar();
                    return;
                }

                renderFilters();
                renderRows();
            })
            .catch(function (err) {
                tableBody.innerHTML = '<tr><td colspan="' + COLSPAN + '" class="text-danger">' + window.CRM.i18n.t('admin_modules.error_load', 'Ошибка загрузки') + ': ' + esc((err.envelope && err.envelope.message) || (err.message) || window.CRM.i18n.t('admin_modules.unknown_error', 'Неизвестная ошибка')) + '</td></tr>';
            });
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
                actions = '<button class="btn btn-sm btn-primary module-install me-1" data-name="' + esc(m.name) + '" title="' + window.CRM.i18n.t('admin_modules.title_install', 'Установить модуль') + '" data-i18n-title="admin_modules.title_install" aria-label="' + window.CRM.i18n.t('admin_modules.aria_install', 'Установить модуль') + ' ' + esc(m.name) + '" data-i18n-aria-label="admin_modules.aria_install"><i class="fa-solid fa-download"></i> ' + window.CRM.i18n.t('admin_modules.btn_install', 'Установить') + '</button>';
            } else if (m.is_active) {
                actions = '<button class="btn btn-sm btn-outline-warning module-deact me-1" data-name="' + esc(m.name) + '" title="' + window.CRM.i18n.t('admin_modules.title_deactivate', 'Деактивировать модуль') + '" data-i18n-title="admin_modules.title_deactivate" aria-label="' + window.CRM.i18n.t('admin_modules.aria_deactivate', 'Деактивировать модуль') + ' ' + esc(m.name) + '" data-i18n-aria-label="admin_modules.aria_deactivate"><i class="fa-solid fa-pause"></i></button>';
                actions += '<button class="btn btn-sm btn-outline-danger module-remove" data-name="' + esc(m.name) + '" title="' + window.CRM.i18n.t('admin_modules.title_remove', 'Удалить модуль') + '" data-i18n-title="admin_modules.title_remove" aria-label="' + window.CRM.i18n.t('admin_modules.aria_remove', 'Удалить модуль') + ' ' + esc(m.name) + '" data-i18n-aria-label="admin_modules.aria_remove"><i class="fa-solid fa-trash"></i></button>';
            } else {
                actions = '<button class="btn btn-sm btn-outline-success module-act me-1" data-name="' + esc(m.name) + '" title="' + window.CRM.i18n.t('admin_modules.title_activate', 'Активировать модуль') + '" data-i18n-title="admin_modules.title_activate" aria-label="' + window.CRM.i18n.t('admin_modules.aria_activate', 'Активировать модуль') + ' ' + esc(m.name) + '" data-i18n-aria-label="admin_modules.aria_activate"><i class="fa-solid fa-play"></i></button>';
                actions += '<button class="btn btn-sm btn-outline-danger module-remove" data-name="' + esc(m.name) + '" title="' + window.CRM.i18n.t('admin_modules.title_remove', 'Удалить модуль') + '" data-i18n-title="admin_modules.title_remove" aria-label="' + window.CRM.i18n.t('admin_modules.aria_remove', 'Удалить модуль') + ' ' + esc(m.name) + '" data-i18n-aria-label="admin_modules.aria_remove"><i class="fa-solid fa-trash"></i></button>';
            }

            rows += '<tr>';
            rows += '<td class="text-center"><input type="checkbox" class="module-select" data-name="' + esc(m.name) + '" aria-label="' + window.CRM.i18n.t('admin_modules.select_module', 'Выбрать модуль') + ' ' + esc(m.name) + '"></td>';
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

    function moduleAction(name, action, btnEl) {
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
                loadModules();
            })
            .catch(function (err) {
                if (btnEl) {
                    btnEl.disabled = false;
                    if (action === 'install') btnEl.innerHTML = '<i class="fa-solid fa-download"></i> ' + window.CRM.i18n.t('admin_modules.btn_install', 'Установить');
                    else if (action === 'activate') btnEl.innerHTML = '<i class="fa-solid fa-play"></i>';
                    else if (action === 'deactivate') btnEl.innerHTML = '<i class="fa-solid fa-pause"></i>';
                    else btnEl.innerHTML = '<i class="fa-solid fa-trash"></i>';
                }
                notify(window.CRM.i18n.t('admin_modules.error_action', 'Ошибка') + ': ' + esc((err.envelope && err.envelope.message) || (err.message) || ''), 'error');
            });
    }

    bindBulkToolbar();
    loadModules();
})();
</script>
</body>
