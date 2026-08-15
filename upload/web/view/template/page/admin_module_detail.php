<?php declare(strict_types=1); ?>
<?php $title = $t('admin_module_detail.title', 'TropaTT — Детали модуля'); $moduleName = htmlspecialchars((string)($_GET['module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
<body data-page="admin-module-detail" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="admin_module_detail.link_admin"><?= htmlspecialchars($t('admin_module_detail.link_admin', 'Админка'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item"><a href="index.php?route=admin-modules" data-i18n="admin_module_detail.link_modules"><?= htmlspecialchars($t('admin_module_detail.link_modules', 'Модули'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" id="moduleBreadcrumb"><?= htmlspecialchars($t('admin_module_detail.breadcrumb_loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" id="moduleTitle" data-i18n="admin_module_detail.page_title"><?= htmlspecialchars($t('admin_module_detail.page_title', 'Детали модуля'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" id="moduleDesc"></p></div></div>

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

<script>
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

                loadConfig(name);
                loadMigrations(name);
                loadErrors(name);
            })
            .catch(function (err) {
                document.getElementById('moduleTitle').textContent = window.CRM.i18n.t('admin_module_detail.error_load', 'Ошибка загрузки');
                document.getElementById('moduleDesc').textContent = (err.envelope && err.envelope.message) || err.message || '';
            });
    }

    function loadConfig(n) {
        var c = document.getElementById('configContainer');
        window.CRM.api.request('api/v1/modules/' + encodeURIComponent(n) + '/config', { method: 'GET', timeoutMs: 10000 })
            .then(function (env) {
                var cfg = ((env.data || {}).config || {});
                var keys = Object.keys(cfg);
                if (keys.length === 0) { c.innerHTML = '<p class="text-muted">' + window.CRM.i18n.t('admin_module_detail.no_config', 'Нет настраиваемых параметров') + '</p>'; return; }

                var html = '';
                keys.forEach(function (k) {
                    var v = cfg[k];
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
                    fields.forEach(function (f) {
                        var key = f.getAttribute('data-key');
                        var type = f.getAttribute('data-type');
                        if (type === 'bool') data[key] = f.checked;
                        else if (type === 'number') data[key] = Number(f.value) || 0;
                        else data[key] = f.value;
                    });

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
                    return '<tr><td>' + window.CRM.text.escapeHtml(m.name) + '</td>'
                        + '<td><span class="badge ' + (m.applied ? 'bg-success' : 'bg-secondary') + '">' + (m.applied ? window.CRM.i18n.t('admin_module_detail.state_applied', 'Применена') : window.CRM.i18n.t('admin_module_detail.state_not_applied', 'Не применена')) + '</span></td>'
                        + '<td><small class="text-muted">' + (m.applied_at ? window.CRM.text.escapeHtml(m.applied_at) : '—') + '</small></td></tr>';
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
                    return '<tr><td>' + window.CRM.text.escapeHtml(e.context) + '</td>'
                        + '<td><code>' + window.CRM.text.escapeHtml(e.error_code || '') + '</code></td>'
                        + '<td>' + window.CRM.text.escapeHtml(e.error_message || '') + '</td>'
                        + '<td><small class="text-muted">' + window.CRM.text.escapeHtml(e.created_at || '') + '</small></td></tr>';
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
