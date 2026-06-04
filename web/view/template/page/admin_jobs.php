<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Задания'; ?>
<body data-page="admin-jobs" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-admin-jobs-page">
  <div class="crm-page-head"><div><h1 class="crm-page-title">Задания: импорт, экспорт и AI</h1><p class="crm-subtitle">Запуск заданий импорта и экспорта, мониторинг их статусов без перезагрузки страницы.</p></div></div>

  <div class="row g-3 mb-3">
    <div class="col-lg-6">
      <div class="crm-card crm-section-card">
        <h2 class="h6 mb-2">Создать задание импорта</h2>
        <form id="adminJobsImportForm" class="d-grid gap-2">
          <select name="type" class="form-select"><option value="tasks">Задачи</option><option value="projects">Проекты</option></select>
          <select name="format" class="form-select"><option value="json_rows">JSON-строки</option><option value="csv">CSV</option></select>
          <textarea name="payload" class="form-control" rows="5" placeholder='[{"title":"Новая задача","status":"new","priority":"normal"}]'></textarea>
          <button type="submit" class="btn crm-btn-primary">Создать задание импорта</button>
        </form>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="crm-card crm-section-card">
        <h2 class="h6 mb-2">Создать задание экспорта</h2>
        <form id="adminJobsExportForm" class="d-grid gap-2">
          <select name="type" class="form-select"><option value="tasks">Задачи</option><option value="projects">Проекты</option></select>
          <input name="search" class="form-control" placeholder="Фильтр поиска (необязательно)">
          <button type="submit" class="btn crm-btn-primary">Создать задание экспорта</button>
        </form>
      </div>
    </div>
  </div>

  <div class="crm-card crm-section-card mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0">Лента заданий</h2><button id="adminJobsRefreshBtn" type="button" class="btn crm-btn-secondary">Обновить</button></div>
    <div id="adminJobsState" class="crm-info-panel mb-2">Загрузка...</div>
    <div id="adminJobDetailsPanel" class="crm-job-details-panel d-none mb-2" aria-live="polite"></div>
    <div class="table-responsive"><table class="table crm-table mb-0"><thead><tr><th>Тип</th><th>ID</th><th>Статус</th><th>Обновлено</th><th>Действия</th></tr></thead><tbody id="adminJobsTableBody"><tr><td colspan="5" class="text-muted">Загрузка...</td></tr></tbody></table></div>
  </div>

  <div class="crm-card crm-section-card">
    <h2 class="h6 mb-2">AI-задания (только просмотр)</h2>
    <div class="table-responsive crm-admin-jobs-ai-table"><table class="table crm-table mb-0"><thead><tr><th>Код</th><th>ID</th><th>Статус</th><th>Попытки</th><th>Обновлено</th></tr></thead><tbody id="adminAiJobsTableBody"><tr><td colspan="5" class="text-muted">Загрузка...</td></tr></tbody></table></div>
  </div>
</main></div></div>
