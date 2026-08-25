<?php declare(strict_types=1); ?>
<?php $title = $t('admin_jobs.title', 'TropaTT — Задания'); ?>
<body data-page="admin-jobs" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-admin-jobs-page">
  <div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="admin_jobs.page_title"><?= htmlspecialchars($t('admin_jobs.page_title', 'Задания: импорт, экспорт и AI'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_jobs.subtitle"><?= htmlspecialchars($t('admin_jobs.subtitle', 'Запуск заданий импорта и экспорта, мониторинг их статусов без перезагрузки страницы.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>

  <div class="row g-3 mb-3">
    <div class="col-lg-6">
      <div class="crm-card crm-section-card">
        <h2 class="h6 mb-2" data-i18n="admin_jobs.card_import_title"><?= htmlspecialchars($t('admin_jobs.card_import_title', 'Создать задание импорта'), ENT_QUOTES, 'UTF-8') ?></h2>
        <form id="adminJobsImportForm" class="d-grid gap-2">
          <select name="type" class="form-select"><option value="tasks" data-i18n="admin_jobs.opt_tasks"><?= htmlspecialchars($t('admin_jobs.opt_tasks', 'Задачи'), ENT_QUOTES, 'UTF-8') ?></option><option value="projects" data-i18n="admin_jobs.opt_projects"><?= htmlspecialchars($t('admin_jobs.opt_projects', 'Проекты'), ENT_QUOTES, 'UTF-8') ?></option></select>
          <select name="format" class="form-select"><option value="json_rows" data-i18n="admin_jobs.opt_json_rows"><?= htmlspecialchars($t('admin_jobs.opt_json_rows', 'JSON-строки'), ENT_QUOTES, 'UTF-8') ?></option><option value="csv" data-i18n="admin_jobs.opt_csv"><?= htmlspecialchars($t('admin_jobs.opt_csv', 'CSV'), ENT_QUOTES, 'UTF-8') ?></option></select>
          <textarea name="payload" class="form-control" rows="5" placeholder="<?= htmlspecialchars($t('admin_jobs.placeholder_payload', '[{"title":"Новая задача","status":"new","priority":"normal"}]'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_jobs.placeholder_payload"></textarea>
          <button type="submit" class="btn crm-btn-primary" data-i18n="admin_jobs.btn_create_import"><?= htmlspecialchars($t('admin_jobs.btn_create_import', 'Создать задание импорта'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="crm-card crm-section-card">
        <h2 class="h6 mb-2" data-i18n="admin_jobs.card_export_title"><?= htmlspecialchars($t('admin_jobs.card_export_title', 'Создать задание экспорта'), ENT_QUOTES, 'UTF-8') ?></h2>
        <form id="adminJobsExportForm" class="d-grid gap-2">
          <select name="type" class="form-select"><option value="tasks" data-i18n="admin_jobs.opt_tasks"><?= htmlspecialchars($t('admin_jobs.opt_tasks', 'Задачи'), ENT_QUOTES, 'UTF-8') ?></option><option value="projects" data-i18n="admin_jobs.opt_projects"><?= htmlspecialchars($t('admin_jobs.opt_projects', 'Проекты'), ENT_QUOTES, 'UTF-8') ?></option></select>
          <input name="search" class="form-control" placeholder="<?= htmlspecialchars($t('admin_jobs.placeholder_search', 'Фильтр поиска (необязательно)'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_jobs.placeholder_search">
          <button type="submit" class="btn crm-btn-primary" data-i18n="admin_jobs.btn_create_export"><?= htmlspecialchars($t('admin_jobs.btn_create_export', 'Создать задание экспорта'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
      </div>
    </div>
  </div>

  <div class="crm-card crm-section-card mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0" data-i18n="admin_jobs.card_feed_title"><?= htmlspecialchars($t('admin_jobs.card_feed_title', 'Лента заданий'), ENT_QUOTES, 'UTF-8') ?></h2><button id="adminJobsRefreshBtn" type="button" class="btn crm-btn-secondary" data-i18n="page.refresh"><?= htmlspecialchars($t('page.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button></div>
    <div id="adminJobsState" class="crm-info-panel mb-2" data-i18n="admin_jobs.loading"><?= htmlspecialchars($t('admin_jobs.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
    <div id="adminJobDetailsPanel" class="crm-job-details-panel d-none mb-2" aria-live="polite"></div>
    <div class="table-responsive"><table class="table crm-table mb-0"><thead><tr><th data-i18n="admin_jobs.th_type"><?= htmlspecialchars($t('admin_jobs.th_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_jobs.th_id"><?= htmlspecialchars($t('admin_jobs.th_id', 'ID'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_jobs.th_status"><?= htmlspecialchars($t('admin_jobs.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_jobs.th_updated"><?= htmlspecialchars($t('admin_jobs.th_updated', 'Обновлено'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_jobs.th_actions"><?= htmlspecialchars($t('admin_jobs.th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="adminJobsTableBody"><tr><td colspan="5" class="text-muted" data-i18n="admin_jobs.loading"><?= htmlspecialchars($t('admin_jobs.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
  </div>

  <div class="crm-card crm-section-card mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0"><i class="fa-solid fa-gears me-1" aria-hidden="true"></i><span data-i18n="admin_jobs.card_cron_title"><?= htmlspecialchars($t('admin_jobs.card_cron_title', 'Плановые задачи (cron)'), ENT_QUOTES, 'UTF-8') ?></span></h2><div class="d-flex gap-2"><button id="adminCronRunBtn" type="button" class="btn crm-btn-primary"><i class="fa-solid fa-play me-1" aria-hidden="true"></i><span data-i18n="admin_jobs.btn_cron_run"><?= htmlspecialchars($t('admin_jobs.btn_cron_run', 'Запустить сейчас'), ENT_QUOTES, 'UTF-8') ?></span></button><button id="adminCronRefreshBtn" type="button" class="btn crm-btn-secondary"><i class="fa-solid fa-rotate me-1" aria-hidden="true"></i><span data-i18n="page.refresh"><?= htmlspecialchars($t('page.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></span></button></div></div>
    <div id="adminCronStaleWarning" class="crm-info-panel mb-2 d-none" style="background:#fff3cd;border:1px solid #ffc107"></div>
    <div id="adminCronState" class="crm-info-panel mb-2" data-i18n="admin_jobs.loading"><?= htmlspecialchars($t('admin_jobs.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
    <div class="table-responsive"><table class="table crm-table mb-0"><thead><tr><th data-i18n="admin_jobs.th_module"><?= htmlspecialchars($t('admin_jobs.th_module', 'Модуль'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_jobs.th_task"><?= htmlspecialchars($t('admin_jobs.th_task', 'Задача'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_jobs.th_schedule"><?= htmlspecialchars($t('admin_jobs.th_schedule', 'Расписание'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_jobs.th_enabled"><?= htmlspecialchars($t('admin_jobs.th_enabled', 'Вкл.'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_jobs.th_last_run"><?= htmlspecialchars($t('admin_jobs.th_last_run', 'Последний запуск'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_jobs.th_next_run"><?= htmlspecialchars($t('admin_jobs.th_next_run', 'След. запуск'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_jobs.th_last_status"><?= htmlspecialchars($t('admin_jobs.th_last_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_jobs.th_last_error"><?= htmlspecialchars($t('admin_jobs.th_last_error', 'Ошибка'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="adminCronTasksTableBody"><tr><td colspan="8" class="text-muted" data-i18n="admin_jobs.loading"><?= htmlspecialchars($t('admin_jobs.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
  </div>

  <div class="crm-card crm-section-card">
    <h2 class="h6 mb-2" data-i18n="admin_jobs.card_ai_title"><?= htmlspecialchars($t('admin_jobs.card_ai_title', 'AI-задания (только просмотр)'), ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="table-responsive crm-admin-jobs-ai-table"><table class="table crm-table mb-0"><thead><tr><th data-i18n="admin_jobs.th_code"><?= htmlspecialchars($t('admin_jobs.th_code', 'Код'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_jobs.th_id"><?= htmlspecialchars($t('admin_jobs.th_id', 'ID'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_jobs.th_status"><?= htmlspecialchars($t('admin_jobs.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_jobs.th_attempts"><?= htmlspecialchars($t('admin_jobs.th_attempts', 'Попытки'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_jobs.th_updated"><?= htmlspecialchars($t('admin_jobs.th_updated', 'Обновлено'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="adminAiJobsTableBody"><tr><td colspan="5" class="text-muted" data-i18n="admin_jobs.loading"><?= htmlspecialchars($t('admin_jobs.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
  </div>
</main></div></div>
