<?php declare(strict_types=1); ?>
<?php $title = $t('admin_module_detail.title', 'TropaTT — Детали модуля'); $moduleName = htmlspecialchars((string)($_GET['module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
<body data-page="admin-module-detail" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="admin_module_detail.link_admin"><?= htmlspecialchars($t('admin_module_detail.link_admin', 'Админка'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item"><a href="index.php?route=admin-modules" data-i18n="admin_module_detail.link_modules"><?= htmlspecialchars($t('admin_module_detail.link_modules', 'Модули'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" id="moduleBreadcrumb"><?= htmlspecialchars($t('admin_module_detail.breadcrumb_loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" id="moduleTitle" data-i18n="admin_module_detail.page_title"><?= htmlspecialchars($t('admin_module_detail.page_title', 'Детали модуля'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" id="moduleDesc"></p><div id="modulePageLink" class="mt-2"></div></div></div>

<div id="moduleHeroBanner" class="mb-4" style="display:none"></div>
<div id="moduleUpdateBanner" class="mb-4" style="display:none"></div>

<div class="row g-4 mb-4" id="moduleStats" style="display:none">
  <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted" data-i18n="admin_module_detail.kpi_version"><?= htmlspecialchars($t('admin_module_detail.kpi_version', 'Версия'), ENT_QUOTES, 'UTF-8') ?></small><h2 class="h4 mb-0" id="statVersion">—</h2></div></div>
  <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted" data-i18n="admin_module_detail.kpi_vendor"><?= htmlspecialchars($t('admin_module_detail.kpi_vendor', 'Вендор'), ENT_QUOTES, 'UTF-8') ?></small><h2 class="h4 mb-0" id="statVendor">—</h2></div></div>
  <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted" data-i18n="admin_module_detail.kpi_status"><?= htmlspecialchars($t('admin_module_detail.kpi_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></small><h2 class="h4 mb-0" id="statStatus">—</h2></div></div>
  <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted" data-i18n="admin_module_detail.kpi_core"><?= htmlspecialchars($t('admin_module_detail.kpi_core', 'Core'), ENT_QUOTES, 'UTF-8') ?></small><h2 class="h4 mb-0" id="statCore">—</h2></div></div>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="crm-card crm-section-card mb-4">
      <div class="crm-section-head"><h2 class="h6 mb-0" data-i18n="admin_module_detail.section_config"><?= htmlspecialchars($t('admin_module_detail.section_config', 'Конфигурация'), ENT_QUOTES, 'UTF-8') ?></h2></div>
      <div id="configContainer"><p class="text-muted" data-i18n="admin_module_detail.loading"><?= htmlspecialchars($t('admin_module_detail.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></p></div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="crm-card crm-section-card mb-4">
      <div class="crm-section-head"><h2 class="h6 mb-0" data-i18n="admin_module_detail.section_migrations"><?= htmlspecialchars($t('admin_module_detail.section_migrations', 'Миграции'), ENT_QUOTES, 'UTF-8') ?></h2></div>
      <table class="table crm-table mb-0"><thead><tr><th data-i18n="admin_module_detail.th_migration"><?= htmlspecialchars($t('admin_module_detail.th_migration', 'Миграция'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_module_detail.th_status"><?= htmlspecialchars($t('admin_module_detail.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_module_detail.th_applied"><?= htmlspecialchars($t('admin_module_detail.th_applied', 'Применена'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="migrationsBody"><tr><td colspan="3" class="text-muted" data-i18n="admin_module_detail.loading"><?= htmlspecialchars($t('admin_module_detail.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table>
    </div>
  </div>
</div>

<div class="crm-card crm-section-card mb-4">
  <div class="crm-section-head d-flex justify-content-between align-items-center">
    <h2 class="h6 mb-0" data-i18n="admin_module_detail.section_errors"><?= htmlspecialchars($t('admin_module_detail.section_errors', 'Ошибки модуля'), ENT_QUOTES, 'UTF-8') ?></h2>
    <button class="btn btn-sm crm-btn-muted" id="clearErrorsBtn" style="display:none" data-i18n="admin_module_detail.btn_clear"><?= htmlspecialchars($t('admin_module_detail.btn_clear', 'Очистить'), ENT_QUOTES, 'UTF-8') ?></button>
  </div>
  <table class="table crm-table mb-0"><thead><tr><th data-i18n="admin_module_detail.th_context"><?= htmlspecialchars($t('admin_module_detail.th_context', 'Контекст'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_module_detail.th_code"><?= htmlspecialchars($t('admin_module_detail.th_code', 'Код'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_module_detail.th_message"><?= htmlspecialchars($t('admin_module_detail.th_message', 'Сообщение'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_module_detail.th_date"><?= htmlspecialchars($t('admin_module_detail.th_date', 'Дата'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="errorsBody"><tr><td colspan="4" class="text-muted" data-i18n="admin_module_detail.no_errors"><?= htmlspecialchars($t('admin_module_detail.no_errors', 'Нет ошибок'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table>
</div>

</main></div></div>

<script nonce="<?= $csp_nonce ?>" src="assets/js/module-update-queue.js?v=20261021-1"></script>
<script nonce="<?= $csp_nonce ?>">
(function () {
    var name = '<?= e($moduleName) ?>';
    if (!name) { document.getElementById('moduleTitle').textContent = window.CRM.i18n.t('admin_module_detail.module_not_specified', 'Модуль не указан'); return; }

    function load() {
        if (!window.CRM || !window.CRM.api) { setTimeout(load, 200); return; }

        window.CRM.api.request('api/v1/modules/' + encodeURIComponent(name), { method: 'GET', timeoutMs: 15000 })
            .then(function (env) {
                var m = env.data || {};
                document.getElementById('moduleBreadcrumb').textContent = m.name || name;
                document.getElementById('moduleTitle').textContent = m.title || m.name || name;
                document.getElementById('moduleDesc').textContent = m.description || '';

                // Link straight to the module's own page (its real settings UI):
                // the raw "Конфигурация" section below only shows the module config.
                var pageRoutes = m.page_routes || [];
                var pageLink = document.getElementById('modulePageLink');
                var heroBanner = document.getElementById('moduleHeroBanner');
                if (pageRoutes.length > 0) {
                    var route = String(pageRoutes[0]);
                    var moduleTitle = m.title || m.name || name;
                    pageLink.innerHTML = '<a class="btn crm-btn-primary" href="index.php?route=' + encodeURIComponent(route) + '"><i class="fa-solid fa-arrow-up-right-from-square me-2" aria-hidden="true"></i>' + window.CRM.text.escapeHtml(window.CRM.i18n.t('admin_module_detail.open_module_page', 'Открыть интерфейс модуля')) + '</a>';
                    
                    if (heroBanner) {
                        heroBanner.style.display = '';
                        heroBanner.innerHTML = '<div class="alert alert-primary p-3 d-flex align-items-center justify-content-between flex-wrap gap-3 shadow-sm border-primary">' +
                            '<div class="d-flex align-items-center">' +
                                '<div class="me-3 fs-1 text-primary"><i class="fa-solid fa-layer-group"></i></div>' +
                                '<div>' +
                                    '<h5 class="alert-heading mb-1 fw-bold">' + window.CRM.text.escapeHtml(moduleTitle) + '</h5>' +
                                    '<div class="small text-muted">' + (m.description ? window.CRM.text.escapeHtml(m.description) : 'Этот модуль предоставляет интерактивный интерфейс управления.') + '</div>' +
                                '</div>' +
                            '</div>' +
                            '<a class="btn crm-btn-primary fw-semibold px-4 py-2" href="index.php?route=' + encodeURIComponent(route) + '">' +
                                '<i class="fa-solid fa-arrow-up-right-from-square me-2" aria-hidden="true"></i>' + window.CRM.text.escapeHtml(window.CRM.i18n.t('admin_module_detail.open_module_page', 'Открыть панель модуля')) +
                            '</a>' +
                        '</div>';
                    }
                } else {
                    pageLink.innerHTML = '';
                    if (heroBanner) heroBanner.style.display = 'none';
                }

                document.getElementById('moduleStats').style.display = '';

                document.getElementById('statVersion').textContent = m.version || '—';
                var vendorLabel = m.author || m.vendor || '—';
                if (m.author_url) {
                    document.getElementById('statVendor').innerHTML = '<a href="' + window.CRM.text.escapeHtml(m.author_url) + '" target="_blank" rel="noopener noreferrer" class="text-decoration-none">' + window.CRM.text.escapeHtml(vendorLabel) + '</a>';
                } else {
                    document.getElementById('statVendor').textContent = vendorLabel;
                }
                document.getElementById('statStatus').textContent = m.is_active ? window.CRM.i18n.t('admin_module_detail.state_active', 'Активен') : (m.status === 'installed' ? window.CRM.i18n.t('admin_module_detail.state_installed', 'Установлен') : window.CRM.i18n.t('admin_module_detail.state_discovered', 'Обнаружен'));
                document.getElementById('statCore').textContent = m.core_version || '—';

                // Check if this module has a published update in the Marketplace
                window.CRM.api.request('api/v1/marketplace/updates', { method: 'GET', timeoutMs: 15000 })
                    .then(function (res) {
                        var items = (res.data && res.data.items) || [];
                        var update = items.find(function (it) { return it.full_code === name; });
                        var updateBanner = document.getElementById('moduleUpdateBanner');
                        if (update && update.update_supported && res.data.can_update === true && updateBanner) {
                            updateBanner.style.display = '';
                            updateBanner.innerHTML = '<div class="alert alert-warning p-3 d-flex align-items-center justify-content-between flex-wrap gap-3 shadow-sm border-warning">' +
                                '<div class="d-flex align-items-center">' +
                                    '<div class="me-3 fs-2 text-warning"><i class="fa-solid fa-cloud-arrow-up"></i></div>' +
                                    '<div>' +
                                        '<h6 class="alert-heading mb-1 fw-bold">' + window.CRM.text.escapeHtml(window.CRM.i18n.t('admin_module_detail.update_available_title', 'Доступно обновление модуля')) + '</h6>' +
                                        '<div class="small">' + window.CRM.text.escapeHtml(window.CRM.i18n.t('admin_module_detail.update_available_desc', 'Доступна новая версия: {latest} (установлена: {current})').replace('{latest}', update.latest_version).replace('{current}', update.installed_version)) + '</div>' +
                                    '</div>' +
                                '</div>' +
                                '<button type="button" class="btn btn-warning fw-semibold px-4 py-2" id="btnUpdateCurrentModule">' +
                                    '<i class="fa-solid fa-arrow-up me-2" aria-hidden="true"></i>' + window.CRM.text.escapeHtml(window.CRM.i18n.t('admin_module_detail.btn_update_now', 'Обновить модуль') + ' v' + update.latest_version) +
                                '</button>' +
                            '</div>';
                            var btn = document.getElementById('btnUpdateCurrentModule');
                            if (btn) {
                                btn.addEventListener('click', function () {
                                    btn.disabled = true;
                                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + window.CRM.text.escapeHtml(window.CRM.i18n.t('admin_module_detail.updating', 'Обновление…'));
                                    window.CRMModuleUpdateQueue.run([{ code: name, version: update.latest_version }], function () {
                                        return window.CRM.api.request('api/v1/marketplace/update', { method: 'POST', body: { full_code: name }, timeoutMs: 330000, retry: false });
                                    }, function (item) {
                                        return window.CRM.api.request('api/v1/modules/' + encodeURIComponent(name), { method: 'GET', noCache: true, timeoutMs: 15000, retry: false }).then(function (env) {
                                            var data = env.data || {};
                                            return data.version === item.version && data.registry_version === item.version && data.update_in_progress === false && data.status !== 'not_installed';
                                        });
                                    }, function () {}).then(function (result) {
                                        if (result.updated.length) {
                                            if (window.CRM.toast) window.CRM.toast.success(window.CRM.i18n.t('admin_module_detail.update_success', 'Модуль успешно обновлён'));
                                            setTimeout(function () { window.location.reload(); }, 1200);
                                        } else {
                                            btn.disabled = result.uncertain.length > 0;
                                            btn.textContent = window.CRM.i18n.t(result.uncertain.length ? 'admin_modules.queue_uncertain' : 'admin_modules.queue_failed', 'Ошибка обновления');
                                        }
                                    });
                                });
                            }
                        }
                    })
                    .catch(function () {});

                loadConfig(name, pageRoutes);
                loadMigrations(name);
                loadErrors(name);
            })
            .catch(function (err) {
                document.getElementById('moduleTitle').textContent = window.CRM.i18n.t('admin_module_detail.error_load', 'Ошибка загрузки');
                document.getElementById('moduleDesc').textContent = (err.envelope && err.envelope.message) || err.message || '';
            });
    }

    function loadConfig(n, pageRoutes) {
        var c = document.getElementById('configContainer');
        window.CRM.api.request('api/v1/modules/' + encodeURIComponent(n) + '/config', { method: 'GET', timeoutMs: 10000 })
            .then(function (env) {
                var cfg = ((env.data || {}).config || {});
                var keys = Object.keys(cfg);
                var html = '';
                if (pageRoutes && pageRoutes.length > 0) {
                    var route = String(pageRoutes[0]);
                    html += '<div class="alert alert-info py-2 px-3 small mb-3"><i class="fa-solid fa-circle-info me-1"></i> ' +
                        window.CRM.i18n.t('admin_module_detail.custom_ui_hint', 'Основная настройка и проверка работы выполняется в панели модуля:') +
                        ' <a href="index.php?route=' + encodeURIComponent(route) + '" class="alert-link fw-semibold">' +
                        window.CRM.text.escapeHtml(window.CRM.i18n.t('admin_module_detail.open_module_page', 'Открыть панель модуля')) + '</a></div>';
                }
                if (keys.length === 0) { c.innerHTML = html + '<p class="text-muted">' + window.CRM.i18n.t('admin_module_detail.no_config', 'Нет настраиваемых параметров') + '</p>'; return; }
                keys.forEach(function (k) {
                    var v = cfg[k];
                    if (v === null || typeof v === 'object') {
                        html += '<div class="mb-2 row"><label class="col-sm-4 col-form-label">' + window.CRM.text.escapeHtml(k) + '</label>';
                        html += '<div class="col-sm-8"><textarea rows="4" class="form-control font-monospace config-field" data-key="' + window.CRM.text.escapeHtml(k) + '" data-type="json">' + window.CRM.text.escapeHtml(JSON.stringify(v, null, 2)) + '</textarea></div></div>';
                        return;
                    }
                    var inputType = typeof v === 'boolean' ? 'checkbox' : (typeof v === 'number' ? 'number' : 'text');
                    var valStr = typeof v === 'boolean' ? '' : ' value="' + window.CRM.text.escapeHtml(String(v)) + '"';
                    var checked = typeof v === 'boolean' && v ? ' checked' : '';

                    html += '<div class="mb-2 row"><label class="col-sm-4 col-form-label">' + window.CRM.text.escapeHtml(k) + '</label>';
                    html += '<div class="col-sm-8">';
                    if (inputType === 'checkbox') {
                        html += '<input type="checkbox" class="form-check-input mt-2 config-field" data-key="' + window.CRM.text.escapeHtml(k) + '" data-type="bool"' + checked + '>';
                    } else {
                        html += '<input type="' + inputType + '" class="form-control config-field" data-key="' + window.CRM.text.escapeHtml(k) + '" data-type="' + inputType + '"' + valStr + '>';
                    }
                    html += '</div></div>';
                });

                html += '<button class="btn crm-btn-primary mt-2" id="saveConfigBtn" data-i18n="admin_module_detail.btn_save">' + window.CRM.i18n.t('admin_module_detail.btn_save', 'Сохранить') + '</button>';
                html += '<span id="configStatus" class="ms-2 small"></span>';
                c.innerHTML = html;

                document.getElementById('saveConfigBtn').addEventListener('click', function () {
                    var fields = c.querySelectorAll('.config-field');
                    var data = {};
                    var invalidJsonKey = '';
                    var invalidNumberKey = '';
                    fields.forEach(function (f) {
                        var key = f.getAttribute('data-key');
                        var type = f.getAttribute('data-type');
                        if (type === 'bool') data[key] = f.checked;
                        else if (type === 'number') {
                            var numberValue = f.valueAsNumber;
                            if (!Number.isFinite(numberValue)) invalidNumberKey = invalidNumberKey || key;
                            else data[key] = numberValue;
                        }
                        else if (type === 'json') {
                            try { data[key] = JSON.parse(f.value); }
                            catch (e) { invalidJsonKey = invalidJsonKey || key; }
                        }
                        else data[key] = f.value;
                    });

                    if (invalidJsonKey) {
                        var status = document.getElementById('configStatus');
                        status.textContent = window.CRM.i18n.t('admin_module_detail.invalid_json', 'Введите корректный JSON для поля') + ' ' + invalidJsonKey;
                        status.className = 'ms-2 small text-danger';
                        Array.prototype.find.call(c.querySelectorAll('.config-field'), function (field) {
                            return field.getAttribute('data-key') === invalidJsonKey;
                        })?.focus();
                        return;
                    }

                    if (invalidNumberKey) {
                        var numberStatus = document.getElementById('configStatus');
                        numberStatus.textContent = window.CRM.i18n.t('modal_edit_number_invalid', 'Укажите корректное числовое значение') + ': ' + invalidNumberKey;
                        numberStatus.className = 'ms-2 small text-danger';
                        Array.prototype.find.call(c.querySelectorAll('.config-field'), function (field) {
                            return field.getAttribute('data-key') === invalidNumberKey;
                        })?.focus();
                        return;
                    }

                    var btn = document.getElementById('saveConfigBtn');
                    btn.disabled = true; btn.textContent = window.CRM.i18n.t('admin_module_detail.saving', 'Сохранение...');

                    window.CRM.api.request('api/v1/modules/' + encodeURIComponent(n) + '/config', {
                        method: 'PUT', timeoutMs: 10000, body: { config: data }
                    })
                    .then(function () {
                        document.getElementById('configStatus').innerHTML = '<span class="text-success">' + window.CRM.i18n.t('admin_module_detail.saved', 'Сохранено') + '</span>';
                        btn.disabled = false; btn.textContent = window.CRM.i18n.t('admin_module_detail.btn_save', 'Сохранить');
                    })
                    .catch(function (err) {
                        document.getElementById('configStatus').innerHTML = '<span class="text-danger">' + window.CRM.i18n.t('admin_module_detail.error', 'Ошибка') + ': ' + window.CRM.text.escapeHtml((err.envelope && err.envelope.message) || err.message || '') + '</span>';
                        btn.disabled = false; btn.textContent = window.CRM.i18n.t('admin_module_detail.btn_save', 'Сохранить');
                    });
                });
            })
            .catch(function () { c.innerHTML = '<p class="text-muted">' + window.CRM.i18n.t('admin_module_detail.error_load_config', 'Не удалось загрузить конфигурацию') + '</p>'; });
    }

    function loadMigrations(n) {
        var t = document.getElementById('migrationsBody');
        window.CRM.api.request('api/v1/modules/' + encodeURIComponent(n) + '/migrations', { method: 'GET', timeoutMs: 10000 })
            .then(function (env) {
                var migrations = (env.data || {}).migrations || [];
                if (migrations.length === 0) { t.innerHTML = '<tr><td colspan="3" class="text-muted">' + window.CRM.i18n.t('admin_module_detail.no_migrations', 'Нет миграций') + '</td></tr>'; return; }

                t.innerHTML = migrations.map(function (m) {
                    return '<tr><td data-label="' + window.CRM.i18n.t('admin_module_detail.th_migration', 'Миграция') + '">' + window.CRM.text.escapeHtml(m.name) + '</td>'
                        + '<td data-label="' + window.CRM.i18n.t('admin_module_detail.th_status', 'Статус') + '"><span class="badge ' + (m.applied ? 'bg-success' : 'bg-secondary') + '">' + (m.applied ? window.CRM.i18n.t('admin_module_detail.state_applied', 'Применена') : window.CRM.i18n.t('admin_module_detail.state_not_applied', 'Не применена')) + '</span></td>'
                        + '<td data-label="' + window.CRM.i18n.t('admin_module_detail.th_applied', 'Применена') + '"><small class="text-muted">' + (m.applied_at ? window.CRM.text.escapeHtml(m.applied_at) : '—') + '</small></td></tr>';
                }).join('');
            })
            .catch(function () { t.innerHTML = '<tr><td colspan="3" class="text-muted">' + window.CRM.i18n.t('admin_module_detail.error_load_migrations', 'Не удалось загрузить') + '</td></tr>'; });
    }

    function loadErrors(n) {
        var t = document.getElementById('errorsBody');
        window.CRM.api.request('api/v1/modules/' + encodeURIComponent(n) + '/errors', { method: 'GET', timeoutMs: 10000 })
            .then(function (env) {
                var errors = (env.data || {}).errors || [];
                if (errors.length === 0) { t.innerHTML = '<tr><td colspan="4" class="text-muted">' + window.CRM.i18n.t('admin_module_detail.no_errors', 'Нет ошибок') + '</td></tr>'; return; }

                document.getElementById('clearErrorsBtn').style.display = '';
                t.innerHTML = errors.map(function (e) {
                    return '<tr><td data-label="' + window.CRM.i18n.t('admin_module_detail.th_context', 'Контекст') + '">' + window.CRM.text.escapeHtml(e.context) + '</td>'
                        + '<td data-label="' + window.CRM.i18n.t('admin_module_detail.th_code', 'Код') + '"><code>' + window.CRM.text.escapeHtml(e.error_code || '') + '</code></td>'
                        + '<td data-label="' + window.CRM.i18n.t('admin_module_detail.th_message', 'Сообщение') + '">' + window.CRM.text.escapeHtml(e.error_message || '') + '</td>'
                        + '<td data-label="' + window.CRM.i18n.t('admin_module_detail.th_date', 'Дата') + '"><small class="text-muted">' + window.CRM.text.escapeHtml(e.created_at || '') + '</small></td></tr>';
                }).join('');
            })
            .catch(function () { t.innerHTML = '<tr><td colspan="4" class="text-muted">' + window.CRM.i18n.t('admin_module_detail.error_load_errors', 'Не удалось загрузить') + '</td></tr>'; });
    }

    document.getElementById('clearErrorsBtn').addEventListener('click', function () {
        if (!confirm(window.CRM.i18n.t('admin_module_detail.confirm_clear_errors', 'Очистить все ошибки модуля?'))) return;
        window.CRM.api.request('api/v1/modules/' + encodeURIComponent(name) + '/errors', { method: 'DELETE', timeoutMs: 5000 })
            .then(function () { loadErrors(name); if (window.CRM.br1) window.CRM.br1.notify('success', window.CRM.i18n.t('admin_module_detail.errors_cleared', 'Ошибки очищены')); })
            .catch(function (err) { if (window.CRM.br1) window.CRM.br1.notify('error', window.CRM.i18n.t('admin_module_detail.error', 'Ошибка') + ': ' + (err.envelope && err.envelope.message || '')); });
    });

    load();
})();
</script>
</body>
