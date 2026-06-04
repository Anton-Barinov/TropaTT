<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Детали модуля'; $moduleName = htmlspecialchars((string)($_GET['module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
<body data-page="admin-module-detail" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin">Админка</a></li><li class="breadcrumb-item"><a href="index.php?route=admin-modules">Модули</a></li><li class="breadcrumb-item active" id="moduleBreadcrumb">Загрузка...</li></ol><h1 class="crm-page-title" id="moduleTitle">Детали модуля</h1><p class="crm-subtitle" id="moduleDesc"></p></div></div>

<div class="row g-4 mb-4" id="moduleStats" style="display:none">
  <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted">Версия</small><h2 class="h4 mb-0" id="statVersion">—</h2></div></div>
  <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted">Вендор</small><h2 class="h4 mb-0" id="statVendor">—</h2></div></div>
  <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted">Статус</small><h2 class="h4 mb-0" id="statStatus">—</h2></div></div>
  <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted">Core</small><h2 class="h4 mb-0" id="statCore">—</h2></div></div>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="crm-card crm-section-card mb-4">
      <div class="crm-section-head"><h2 class="h6 mb-0">Конфигурация</h2></div>
      <div id="configContainer"><p class="text-muted">Загрузка...</p></div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="crm-card crm-section-card mb-4">
      <div class="crm-section-head"><h2 class="h6 mb-0">Миграции</h2></div>
      <table class="table crm-table mb-0"><thead><tr><th>Миграция</th><th>Статус</th><th>Применена</th></tr></thead><tbody id="migrationsBody"><tr><td colspan="3" class="text-muted">Загрузка...</td></tr></tbody></table>
    </div>
  </div>
</div>

<div class="crm-card crm-section-card mb-4">
  <div class="crm-section-head d-flex justify-content-between align-items-center">
    <h2 class="h6 mb-0">Ошибки модуля</h2>
    <button class="btn btn-sm crm-btn-muted" id="clearErrorsBtn" style="display:none">Очистить</button>
  </div>
  <table class="table crm-table mb-0"><thead><tr><th>Контекст</th><th>Код</th><th>Сообщение</th><th>Дата</th></tr></thead><tbody id="errorsBody"><tr><td colspan="4" class="text-muted">Нет ошибок</td></tr></tbody></table>
</div>

</main></div></div>

<script>
(function () {
    var name = '<?= $moduleName ?>';
    if (!name) { document.getElementById('moduleTitle').textContent = 'Модуль не указан'; return; }

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
                document.getElementById('statVendor').textContent = m.vendor || '—';
                document.getElementById('statStatus').textContent = m.is_active ? 'Активен' : (m.status === 'installed' ? 'Установлен' : 'Обнаружен');
                document.getElementById('statCore').textContent = m.core_version || '—';

                loadConfig(name);
                loadMigrations(name);
                loadErrors(name);
            })
            .catch(function (err) {
                document.getElementById('moduleTitle').textContent = 'Ошибка загрузки';
                document.getElementById('moduleDesc').textContent = (err.envelope && err.envelope.message) || err.message || '';
            });
    }

    function loadConfig(n) {
        var c = document.getElementById('configContainer');
        window.CRM.api.request('api/v1/modules/' + encodeURIComponent(n) + '/config', { method: 'GET', timeoutMs: 10000 })
            .then(function (env) {
                var cfg = ((env.data || {}).config || {});
                var keys = Object.keys(cfg);
                if (keys.length === 0) { c.innerHTML = '<p class="text-muted">Нет настраиваемых параметров</p>'; return; }

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

                html += '<button class="btn crm-btn-primary mt-2" id="saveConfigBtn">Сохранить</button>';
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
                    btn.disabled = true; btn.textContent = 'Сохранение...';

                    window.CRM.api.request('api/v1/modules/' + encodeURIComponent(n) + '/config', {
                        method: 'PUT', timeoutMs: 10000, body: { config: data }
                    })
                    .then(function () {
                        document.getElementById('configStatus').innerHTML = '<span class="text-success">Сохранено</span>';
                        btn.disabled = false; btn.textContent = 'Сохранить';
                    })
                    .catch(function (err) {
                        document.getElementById('configStatus').innerHTML = '<span class="text-danger">Ошибка: ' + window.CRM.text.escapeHtml((err.envelope && err.envelope.message) || err.message || '') + '</span>';
                        btn.disabled = false; btn.textContent = 'Сохранить';
                    });
                });
            })
            .catch(function () { c.innerHTML = '<p class="text-muted">Не удалось загрузить конфигурацию</p>'; });
    }

    function loadMigrations(n) {
        var t = document.getElementById('migrationsBody');
        window.CRM.api.request('api/v1/modules/' + encodeURIComponent(n) + '/migrations', { method: 'GET', timeoutMs: 10000 })
            .then(function (env) {
                var migrations = (env.data || {}).migrations || [];
                if (migrations.length === 0) { t.innerHTML = '<tr><td colspan="3" class="text-muted">Нет миграций</td></tr>'; return; }

                t.innerHTML = migrations.map(function (m) {
                    return '<tr><td>' + window.CRM.text.escapeHtml(m.name) + '</td>'
                        + '<td><span class="badge ' + (m.applied ? 'bg-success' : 'bg-secondary') + '">' + (m.applied ? 'Применена' : 'Не применена') + '</span></td>'
                        + '<td><small class="text-muted">' + (m.applied_at ? window.CRM.text.escapeHtml(m.applied_at) : '—') + '</small></td></tr>';
                }).join('');
            })
            .catch(function () { t.innerHTML = '<tr><td colspan="3" class="text-muted">Не удалось загрузить</td></tr>'; });
    }

    function loadErrors(n) {
        var t = document.getElementById('errorsBody');
        window.CRM.api.request('api/v1/modules/' + encodeURIComponent(n) + '/errors', { method: 'GET', timeoutMs: 10000 })
            .then(function (env) {
                var errors = (env.data || {}).errors || [];
                if (errors.length === 0) { t.innerHTML = '<tr><td colspan="4" class="text-muted">Нет ошибок</td></tr>'; return; }

                document.getElementById('clearErrorsBtn').style.display = '';
                t.innerHTML = errors.map(function (e) {
                    return '<tr><td>' + window.CRM.text.escapeHtml(e.context) + '</td>'
                        + '<td><code>' + window.CRM.text.escapeHtml(e.error_code || '') + '</code></td>'
                        + '<td>' + window.CRM.text.escapeHtml(e.error_message || '') + '</td>'
                        + '<td><small class="text-muted">' + window.CRM.text.escapeHtml(e.created_at || '') + '</small></td></tr>';
                }).join('');
            })
            .catch(function () { t.innerHTML = '<tr><td colspan="4" class="text-muted">Не удалось загрузить</td></tr>'; });
    }

    document.getElementById('clearErrorsBtn').addEventListener('click', function () {
        if (!confirm('Очистить все ошибки модуля?')) return;
        window.CRM.api.request('api/v1/modules/' + encodeURIComponent(name) + '/errors', { method: 'DELETE', timeoutMs: 5000 })
            .then(function () { loadErrors(name); if (window.CRM.br1) window.CRM.br1.notify('success', 'Ошибки очищены'); })
            .catch(function (err) { if (window.CRM.br1) window.CRM.br1.notify('error', 'Ошибка: ' + (err.envelope && err.envelope.message || '')); });
    });

    load();
})();
</script>
</body>
