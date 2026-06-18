<?php declare(strict_types=1); ?>
<?php $title = $t('admin_updates.title', 'TropaTT — Обновления'); ?>
<body data-page="admin-updates" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-updates-page">
  <div class="crm-page-head">
    <div>
      <h1 class="crm-page-title">Обновления ядра TropaTT</h1>
      <p class="crm-subtitle">Проверка update.crm.ru, diff/changelog, dry-run preflight и подготовка безопасной установки.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <button class="btn crm-btn-secondary" type="button" data-update-action="status">Проверить сервер обновлений</button>
      <button class="btn crm-btn-secondary" type="button" data-update-action="check">Проверить обновления</button>
      <button class="btn crm-btn-secondary" type="button" data-update-action="changes">Показать изменения</button>
      <button class="btn crm-btn-primary" type="button" data-update-action="preflight">Выполнить preflight</button>
      <button class="btn crm-btn-primary" type="button" data-update-action="download">Скачать архив dry-run</button>
      <a class="btn crm-btn-secondary" href="/updater/rescue.php" target="_blank" rel="noopener">Recovery mode</a>
    </div>
  </div>

  <div class="alert alert-warning">
    <strong>Обновляется только ядро TropaTT:</strong> api/**, web/**, корневые файлы ядра, docs/changelog при наличии.<br>
    <strong>Не обновляется:</strong> modules/**, storage/**, storage_api/**, uploads/**, .env, local config, updater/**.
  </div>

  <div class="row g-3">
    <div class="col-lg-4"><div class="crm-card crm-section-card"><h2 class="h6">Текущая версия</h2><pre id="updatesVersion" class="small text-muted">Загрузка...</pre></div></div>
    <div class="col-lg-4"><div class="crm-card crm-section-card"><h2 class="h6">Update center audit</h2><pre id="updatesStatus" class="small text-muted">Загрузка...</pre></div></div>
    <div class="col-lg-4"><div class="crm-card crm-section-card"><h2 class="h6">Последний job</h2><pre id="updatesJob" class="small text-muted">—</pre></div></div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-lg-6"><div class="crm-card crm-section-card"><h2 class="h6">Доступное обновление</h2><pre id="updatesPlan" class="small">Нажмите «Проверить обновления».</pre></div></div>
    <div class="col-lg-6"><div class="crm-card crm-section-card"><h2 class="h6">Preflight report</h2><pre id="updatesPreflight" class="small">Dry-run еще не выполнялся.</pre></div></div>
  </div>

  <div class="crm-card crm-section-card mt-3"><h2 class="h6">Скачивание и staging архива</h2><pre id="updatesDownload" class="small">После успешного preflight нажмите «Скачать архив dry-run».</pre></div>
  <div class="crm-card crm-section-card mt-3"><h2 class="h6">Изменения и файлы</h2><pre id="updatesChanges" class="small">Нажмите «Показать изменения».</pre></div>
</main></div></div>
<script>
(function () {
  let lastPreflightJobId = null;
  const api = async (url, options = {}) => {
    const csrfToken = (window.CRM && window.CRM.api && typeof window.CRM.api.getCsrfToken === 'function')
      ? window.CRM.api.getCsrfToken()
      : (document.cookie.match(/crm_csrf_token=([^;]+)/) || [])[1] || '';
    const headers = Object.assign({'Content-Type': 'application/json'}, options.headers || {});
    if (csrfToken && !headers['X-CSRF-Token']) headers['X-CSRF-Token'] = csrfToken;
    const res = await fetch(url, Object.assign({credentials: 'same-origin', headers: headers}, options));
    return res.json();
  };
  const pretty = (value) => JSON.stringify(value, null, 2);
  const set = (id, value) => { document.getElementById(id).textContent = pretty(value); };
  async function loadStatus() {
    const version = await api('/api/index.php?route=api/v1/core/version');
    const status = await api('/api/index.php?route=api/v1/core/updates/status');
    set('updatesVersion', version.data || version);
    set('updatesStatus', status.data || status);
    set('updatesJob', (status.data && status.data.latest_job) || null);
  }
  async function check() {
    const result = await api('/api/index.php?route=api/v1/core/updates/check', {method: 'POST', body: '{}'});
    set('updatesPlan', result.data || result);
  }
  async function changes() {
    const result = await api('/api/index.php?route=api/v1/core/updates/changes');
    set('updatesChanges', result.data || result);
  }
  async function preflight() {
    const result = await api('/api/index.php?route=api/v1/core/updates/preflight', {method: 'POST', body: JSON.stringify({dry_run: true})});
    lastPreflightJobId = result.data && result.data.job_id ? result.data.job_id : null;
    set('updatesPreflight', result.data || result);
  }
  async function download() {
    if (!lastPreflightJobId) {
      set('updatesDownload', {error: 'Сначала выполните preflight, чтобы получить job_id.'});
      return;
    }
    const result = await api('/updater/index.php?action=download', {method: 'POST', body: JSON.stringify({dry_run: true, job_id: lastPreflightJobId})});
    set('updatesDownload', result);
    await loadStatus();
  }
  document.addEventListener('click', (event) => {
    const btn = event.target.closest && event.target.closest('[data-update-action]');
    if (!btn) return;
    const action = btn.getAttribute('data-update-action');
    if (!action) return;
    if (action === 'status') loadStatus();
    if (action === 'check') check();
    if (action === 'changes') changes();
    if (action === 'preflight') preflight();
    if (action === 'download') download();
  });
  loadStatus().catch((err) => set('updatesStatus', {error: String(err)}));
})();
</script>
