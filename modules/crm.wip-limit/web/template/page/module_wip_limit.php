<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — WIP-лимиты'; ?>
<body data-page="module-wip-limit" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin">Админка</a></li><li class="breadcrumb-item active">WIP-лимиты</li></ol><h1 class="crm-page-title">WIP-лимиты</h1><p class="crm-subtitle">Ограничение количества задач в работе. Лимиты на пользователя, контроль превышений.</p></div></div>

<div class="row g-4 mb-4">
  <div class="col-lg-7">
    <div class="crm-card crm-section-card p-0 table-responsive mb-3">
      <table class="table crm-table mb-0"><thead><tr><th>Пользователь</th><th>WIP</th><th>Лимит</th><th>Статус</th><th>Действия</th></tr></thead><tbody id="summaryBody"><tr><td colspan="5" class="text-muted">Загрузка...</td></tr></tbody></table>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><h2 class="h6 mb-0">Установить лимит</h2></div>
      <div class="mb-3">
        <label class="form-label">Пользователь</label>
        <select class="form-select" id="wipUserSelect"><option value="">Выберите пользователя</option></select>
      </div>
      <div class="mb-3">
        <label class="form-label">Максимум задач в работе</label>
        <input type="number" class="form-control" id="wipMaxInput" min="1" max="50" value="5">
        <div class="form-text">По умолчанию: 5. При превышении — уведомление в лог.</div>
      </div>
      <button class="btn crm-btn-primary" id="wipSetBtn"><i class="fa-solid fa-floppy-disk me-1"></i> Установить лимит</button>
      <span id="wipSetStatus" class="ms-2 small"></span>
    </div>

    <div class="crm-card crm-section-card mt-3">
      <div class="crm-section-head"><h2 class="h6 mb-0">Как это работает</h2></div>
      <ul class="mb-0 small">
        <li>Лимит проверяется при переводе задачи в статус <code>in_progress</code> или <code>review</code></li>
        <li>При превышении лимита запись добавляется в лог модуля</li>
        <li>Лимит можно настроить индивидуально для каждого пользователя</li>
        <li>Если лимит не задан — используется значение по умолчанию (5)</li>
        <li>Счётчик WIP обновляется автоматически через хук <code>task.status_changed</code></li>
      </ul>
    </div>
  </div>
</div>

</main></div></div>

<script>
(function () {
    function apiReady() { return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function'; }
    function waitForApi(cb) { if (apiReady()) cb(); else setTimeout(function () { waitForApi(cb); }, 200); }

    waitForApi(function () {
        loadAll();
    });

    function loadAll() {
        var tbody = document.getElementById('summaryBody');
        var sel = document.getElementById('wipUserSelect');
        if (!tbody || !sel) return;
        tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Загрузка...</td></tr>';
        sel.innerHTML = '<option value="">Загрузка...</option>';

        window.CRM.api.request('_module/crm.wip-limit/summary', { method: 'GET', timeoutMs: 15000 })
            .then(function (env) {
                var items = (env.data || {}).items || [];
                if (items.length === 0) { tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Нет данных. Установите лимиты для пользователей.</td></tr>'; sel.innerHTML = '<option value="">Нет пользователей</option>'; return; }

                // Populate dropdown
                sel.innerHTML = '<option value="">Выберите пользователя</option>' +
                    items.map(function (u) {
                        return '<option value="' + u.user_id + '">' + window.CRM.text.escapeHtml(u.full_name || u.login || 'User #' + u.user_id) + '</option>';
                    }).join('');

                // Populate table
                tbody.innerHTML = items.map(function (u) {
                    var pct = u.limit_value > 0 ? Math.round(u.current_count / u.limit_value * 100) : 0;
                    var barColor = u.at_limit ? 'bg-danger' : (pct > 80 ? 'bg-warning' : 'bg-success');
                    var rowClass = u.at_limit ? 'table-danger' : (pct > 80 ? 'table-warning' : '');
                    var statusBadge = u.at_limit ? '<span class="badge bg-danger">Превышен</span>' : (pct > 80 ? '<span class="badge bg-warning">Близко</span>' : '<span class="badge bg-success">OK</span>');
                    return '<tr class="' + rowClass + '">'
                        + '<td><strong>' + window.CRM.text.escapeHtml(u.full_name || u.login) + '</strong></td>'
                        + '<td><div class="d-flex align-items-center"><div class="progress flex-grow-1 me-2" style="height:6px"><div class="progress-bar ' + barColor + '" style="width:' + pct + '%"></div></div><small>' + u.current_count + '/' + u.limit_value + '</small></div></td>'
                        + '<td>' + u.limit_value + '</td>'
                        + '<td>' + statusBadge + '</td>'
                        + '<td><button class="btn btn-sm crm-btn-muted edit-limit" data-uid="' + u.user_id + '" data-limit="' + u.limit_value + '"><i class="fa-solid fa-pen"></i></button></td>'
                        + '</tr>';
                }).join('');

                bindEditButtons();
            })
            .catch(function (err) {
                console.error('[WIP] fetch error:', err);
                tbody.innerHTML = '<tr><td colspan="5" class="text-danger">Ошибка загрузки</td></tr>';
                sel.innerHTML = '<option value="">Ошибка</option>';
            });
    }

    function bindEditButtons() {
        document.querySelectorAll('.edit-limit').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var uid = parseInt(this.getAttribute('data-uid'), 10);
                var limit = parseInt(this.getAttribute('data-limit'), 10);
                window.__wipSelectedUserId = uid;
                document.getElementById('wipUserSelect').value = uid;
                document.getElementById('wipMaxInput').value = limit;
            });
        });
    }

    document.getElementById('wipSetBtn').addEventListener('click', function () {
        var uid = window.__wipSelectedUserId || parseInt(document.getElementById('wipUserSelect').value, 10) || 0;
        var max = parseInt(document.getElementById('wipMaxInput').value, 10);
        if (!uid) { setStatus('error', 'Выберите пользователя'); return; }
        if (max < 1) { setStatus('error', 'Лимит должен быть >= 1'); return; }

        var btn = this;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Сохранение...';

        window.CRM.api.request('_module/crm.wip-limit/limits', { method: 'POST', timeoutMs: 10000, body: { user_id: uid, max_tasks: max } })
            .then(function () {
                setStatus('success', 'Лимит установлен');
                btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Установить лимит';
                loadAll();
            })
            .catch(function (err) {
                setStatus('error', 'Ошибка: ' + (err.envelope && err.envelope.message || ''));
                btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Установить лимит';
            });
    });

    function setStatus(type, msg) {
        var s = document.getElementById('wipSetStatus');
        s.innerHTML = '<span class="text-' + (type === 'error' ? 'danger' : 'success') + '">' + window.CRM.text.escapeHtml(msg) + '</span>';
    }
})();
</script>
</body>
