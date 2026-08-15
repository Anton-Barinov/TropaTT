<?php declare(strict_types=1); ?>
<?php $title = $t('module_wip_limit.title', 'TropaTT — WIP Limits'); ?>
<body data-page="module-wip-limit" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="nav.admin"><?= htmlspecialchars($t('nav.admin', 'Administration'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="module_wip_limit.page_title"><?= htmlspecialchars($t('module_wip_limit.page_title', 'WIP Limits'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" data-i18n="module_wip_limit.page_title"><?= htmlspecialchars($t('module_wip_limit.page_title', 'WIP Limits'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="module_wip_limit.subtitle"><?= htmlspecialchars($t('module_wip_limit.subtitle', 'Limit the number of tasks in progress per user and track overloads.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>

<div class="row g-4 mb-4">
  <div class="col-lg-7">
    <div class="crm-card crm-section-card p-0 table-responsive mb-3">
      <table class="table crm-table mb-0"><thead><tr><th data-i18n="module_wip_limit.th_user"><?= htmlspecialchars($t('module_wip_limit.th_user', 'User'), ENT_QUOTES, 'UTF-8') ?></th><th>WIP</th><th data-i18n="module_wip_limit.th_limit"><?= htmlspecialchars($t('module_wip_limit.th_limit', 'Limit'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="module_wip_limit.th_status"><?= htmlspecialchars($t('module_wip_limit.th_status', 'Status'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="module_wip_limit.th_actions"><?= htmlspecialchars($t('module_wip_limit.th_actions', 'Actions'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="summaryBody"><tr><td colspan="5" class="text-muted" data-i18n="module_wip_limit.loading"><?= htmlspecialchars($t('module_wip_limit.loading', 'Loading...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><h2 class="h6 mb-0" data-i18n="module_wip_limit.set_title"><?= htmlspecialchars($t('module_wip_limit.set_title', 'Set limit'), ENT_QUOTES, 'UTF-8') ?></h2></div>
      <div class="mb-3">
        <label class="form-label" data-i18n="module_wip_limit.field_user"><?= htmlspecialchars($t('module_wip_limit.field_user', 'User'), ENT_QUOTES, 'UTF-8') ?></label>
        <select class="form-select" id="wipUserSelect"><option value="" data-i18n="module_wip_limit.select_user"><?= htmlspecialchars($t('module_wip_limit.select_user', 'Select user'), ENT_QUOTES, 'UTF-8') ?></option></select>
      </div>
      <div class="mb-3">
        <label class="form-label" data-i18n="module_wip_limit.field_max"><?= htmlspecialchars($t('module_wip_limit.field_max', 'Maximum tasks in progress'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="number" class="form-control" id="wipMaxInput" min="1" max="50" value="5">
        <div class="form-text" data-i18n="module_wip_limit.field_hint"><?= htmlspecialchars($t('module_wip_limit.field_hint', 'Default: 5. The current WIP load is computed live from the task list.'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <button class="btn crm-btn-primary" id="wipSetBtn"><i class="fa-solid fa-floppy-disk me-1"></i> <span data-i18n="module_wip_limit.btn_set"><?= htmlspecialchars($t('module_wip_limit.btn_set', 'Set limit'), ENT_QUOTES, 'UTF-8') ?></span></button>
      <span id="wipSetStatus" class="ms-2 small"></span>
    </div>

    <div class="crm-card crm-section-card mt-3">
      <div class="crm-section-head"><h2 class="h6 mb-0" data-i18n="module_wip_limit.how_title"><?= htmlspecialchars($t('module_wip_limit.how_title', 'How it works'), ENT_QUOTES, 'UTF-8') ?></h2></div>
      <ul class="mb-0 small">
        <li><?= htmlspecialchars($t('module_wip_limit.how_item_1', 'The limit is checked when a task moves to'), ENT_QUOTES, 'UTF-8') ?> <code>in_progress</code> <?= htmlspecialchars($t('module_wip_limit.how_item_1_or', 'or'), ENT_QUOTES, 'UTF-8') ?> <code>review</code></li>
        <li data-i18n="module_wip_limit.how_item_2"><?= htmlspecialchars($t('module_wip_limit.how_item_2', 'When the limit is exceeded, the module writes an event to the log.'), ENT_QUOTES, 'UTF-8') ?></li>
        <li data-i18n="module_wip_limit.how_item_3"><?= htmlspecialchars($t('module_wip_limit.how_item_3', 'The limit can be configured individually for each user.'), ENT_QUOTES, 'UTF-8') ?></li>
        <li data-i18n="module_wip_limit.how_item_4"><?= htmlspecialchars($t('module_wip_limit.how_item_4', 'If no limit is set, the default value is used: 5.'), ENT_QUOTES, 'UTF-8') ?></li>
        <li data-i18n="module_wip_limit.how_item_5"><?= htmlspecialchars($t('module_wip_limit.how_item_5', 'WIP counters are computed live from the task list, so they always stay up to date.'), ENT_QUOTES, 'UTF-8') ?></li>
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
        tbody.innerHTML = '<tr><td colspan="5" class="text-muted"><?= htmlspecialchars($t('module_wip_limit.loading', 'Loading...'), ENT_QUOTES, 'UTF-8') ?></td></tr>';
        sel.innerHTML = '<option value=""><?= htmlspecialchars($t('module_wip_limit.loading', 'Loading...'), ENT_QUOTES, 'UTF-8') ?></option>';

        window.CRM.api.request('_module/crm.wip-limit/summary', { method: 'GET', timeoutMs: 15000 })
            .then(function (env) {
                var items = (env.data || {}).items || [];
                if (items.length === 0) { tbody.innerHTML = '<tr><td colspan="5" class="text-muted"><?= htmlspecialchars($t('module_wip_limit.empty', 'No data. Set limits for users.'), ENT_QUOTES, 'UTF-8') ?></td></tr>'; sel.innerHTML = '<option value=""><?= htmlspecialchars($t('module_wip_limit.no_users', 'No users'), ENT_QUOTES, 'UTF-8') ?></option>'; return; }

                // Populate dropdown
                sel.innerHTML = '<option value=""><?= htmlspecialchars($t('module_wip_limit.select_user', 'Select user'), ENT_QUOTES, 'UTF-8') ?></option>' +
                    items.map(function (u) {
                        return '<option value="' + u.user_id + '">' + window.CRM.text.escapeHtml(u.full_name || u.login || 'User #' + u.user_id) + '</option>';
                    }).join('');

                // Populate table
                tbody.innerHTML = items.map(function (u) {
                    var pct = u.limit_value > 0 ? Math.round(u.current_count / u.limit_value * 100) : 0;
                    var barColor = u.at_limit ? 'bg-danger' : (pct > 80 ? 'bg-warning' : 'bg-success');
                    var rowClass = u.at_limit ? 'table-danger' : (pct > 80 ? 'table-warning' : '');
                    var statusBadge = u.at_limit ? '<span class="badge bg-danger"><?= htmlspecialchars($t('module_wip_limit.status_exceeded', 'Exceeded'), ENT_QUOTES, 'UTF-8') ?></span>' : (pct > 80 ? '<span class="badge bg-warning"><?= htmlspecialchars($t('module_wip_limit.status_close', 'Close'), ENT_QUOTES, 'UTF-8') ?></span>' : '<span class="badge bg-success">OK</span>');
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
                tbody.innerHTML = '<tr><td colspan="5" class="text-danger"><?= htmlspecialchars($t('module_wip_limit.load_error', 'Load error'), ENT_QUOTES, 'UTF-8') ?></td></tr>';
                sel.innerHTML = '<option value=""><?= htmlspecialchars($t('module_wip_limit.error', 'Error'), ENT_QUOTES, 'UTF-8') ?></option>';
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
        if (!uid) { setStatus('error', '<?= htmlspecialchars($t('module_wip_limit.error_select_user', 'Select user'), ENT_QUOTES, 'UTF-8') ?>'); return; }
        if (max < 1) { setStatus('error', '<?= htmlspecialchars($t('module_wip_limit.error_min_limit', 'Limit must be >= 1'), ENT_QUOTES, 'UTF-8') ?>'); return; }

        var btn = this;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> <?= htmlspecialchars($t('module_wip_limit.saving', 'Saving...'), ENT_QUOTES, 'UTF-8') ?>';

        window.CRM.api.request('_module/crm.wip-limit/limits', { method: 'POST', timeoutMs: 10000, body: { user_id: uid, max_tasks: max } })
            .then(function () {
                setStatus('success', '<?= htmlspecialchars($t('module_wip_limit.saved', 'Limit saved'), ENT_QUOTES, 'UTF-8') ?>');
                btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> <?= htmlspecialchars($t('module_wip_limit.btn_set', 'Set limit'), ENT_QUOTES, 'UTF-8') ?>';
                loadAll();
            })
            .catch(function (err) {
                setStatus('error', '<?= htmlspecialchars($t('module_wip_limit.error', 'Error'), ENT_QUOTES, 'UTF-8') ?>: ' + (err.envelope && err.envelope.message || ''));
                btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> <?= htmlspecialchars($t('module_wip_limit.btn_set', 'Set limit'), ENT_QUOTES, 'UTF-8') ?>';
            });
    });

    function setStatus(type, msg) {
        var s = document.getElementById('wipSetStatus');
        s.innerHTML = '<span class="text-' + (type === 'error' ? 'danger' : 'success') + '">' + window.CRM.text.escapeHtml(msg) + '</span>';
    }
})();
</script>
</body>
