<?php declare(strict_types=1); ?>
<?php $title = $t('admin_logs.title', 'TropaTT — Логи пользователей'); ?>
<body data-page="admin" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="admin_logs.link_admin"><?= htmlspecialchars($t('admin_logs.link_admin', 'Админка'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="admin_logs.breadcrumb"><?= htmlspecialchars($t('admin_logs.breadcrumb', 'Логи пользователей'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" data-i18n="admin_logs.page_title"><?= htmlspecialchars($t('admin_logs.page_title', 'Логи пользователей'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_logs.subtitle"><?= htmlspecialchars($t('admin_logs.subtitle', 'История входов, аудита, учета времени и действий сотрудников.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>

<div class="crm-toolbar crm-toolbar-surface crm-filters-card d-flex flex-wrap gap-2">
  <select id="adminLogsSourceFilter" class="form-select crm-field-w-220">
    <option value="audit" data-i18n="admin_logs.opt_audit"><?= htmlspecialchars($t('admin_logs.opt_audit', 'Журнал аудита'), ENT_QUOTES, 'UTF-8') ?></option>
    <option value="security" data-i18n="admin_logs.opt_security"><?= htmlspecialchars($t('admin_logs.opt_security', 'Журнал безопасности'), ENT_QUOTES, 'UTF-8') ?></option>
    <option value="request" data-i18n="admin_logs.opt_request"><?= htmlspecialchars($t('admin_logs.opt_request', 'Журнал запросов'), ENT_QUOTES, 'UTF-8') ?></option>
    <option value="worklog" data-i18n="admin_logs.opt_worklog"><?= htmlspecialchars($t('admin_logs.opt_worklog', 'Записи учета времени'), ENT_QUOTES, 'UTF-8') ?></option>
  </select>
  <input id="adminLogsUserFilter" class="form-control crm-field-w-240" placeholder="<?= htmlspecialchars($t('admin_logs.placeholder_user', 'ID пользователя / автора'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_logs.placeholder_user">
  <input id="adminLogsEntityFilter" class="form-control crm-field-w-220" placeholder="<?= htmlspecialchars($t('admin_logs.placeholder_entity', 'Тип объекта (task/project/...)'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_logs.placeholder_entity">
  <input id="adminLogsRouteFilter" class="form-control crm-field-w-260" placeholder="<?= htmlspecialchars($t('admin_logs.placeholder_route', 'Маршрут запроса'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_logs.placeholder_route">
  <input id="adminLogsFromFilter" class="form-control crm-field-w-180" type="date">
  <input id="adminLogsToFilter" class="form-control crm-field-w-180" type="date">
  <button id="adminLogsApplyBtn" class="btn crm-btn-secondary" type="button" data-i18n="page.filter"><?= htmlspecialchars($t('page.filter', 'Применить'), ENT_QUOTES, 'UTF-8') ?></button>
  <button id="adminLogsResetBtn" class="btn crm-btn-muted" type="button" data-i18n="page.reset"><?= htmlspecialchars($t('page.reset', 'Сбросить'), ENT_QUOTES, 'UTF-8') ?></button>
</div>

<div class="crm-card crm-section-card mb-3">
  <div class="d-flex flex-wrap gap-2 align-items-end">
    <div>
      <label class="form-label mb-1" data-i18n="admin_logs.label_activity"><?= htmlspecialchars($t('admin_logs.label_activity', 'Активность пользователя (агрегированно)'), ENT_QUOTES, 'UTF-8') ?></label>
      <input id="adminLogsUserActivityInput" class="form-control crm-field-min-w-260" placeholder="<?= htmlspecialchars($t('admin_logs.placeholder_user_id', 'ID пользователя'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_logs.placeholder_user_id">
    </div>
    <button id="adminLogsUserActivityBtn" class="btn crm-btn-secondary" type="button" data-i18n="admin_logs.btn_show_activity"><?= htmlspecialchars($t('admin_logs.btn_show_activity', 'Показать активность'), ENT_QUOTES, 'UTF-8') ?></button>
  </div>
  <div id="adminUserActivitySummary" class="text-muted mt-2" data-i18n="admin_logs.activity_hint"><?= htmlspecialchars($t('admin_logs.activity_hint', 'Введите ID пользователя, чтобы увидеть активность в журналах запросов, безопасности и аудита.'), ENT_QUOTES, 'UTF-8') ?></div>
</div>

<div class="crm-card crm-section-card p-0 table-responsive mb-3"><table class="table crm-table mb-0"><thead><tr><th data-i18n="admin_logs.th_time"><?= htmlspecialchars($t('admin_logs.th_time', 'Время'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_logs.th_user"><?= htmlspecialchars($t('admin_logs.th_user', 'Пользователь'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_logs.th_source_event"><?= htmlspecialchars($t('admin_logs.th_source_event', 'Источник/событие'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_logs.th_object_route"><?= htmlspecialchars($t('admin_logs.th_object_route', 'Объект/маршрут'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_logs.th_ip_status"><?= htmlspecialchars($t('admin_logs.th_ip_status', 'IP/Статус'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:130px" data-i18n="admin_logs.th_details"><?= htmlspecialchars($t('admin_logs.th_details', 'Детали'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="adminLogsTableBody">
<tr><td colspan="6" class="text-muted" data-i18n="admin_logs.loading_logs"><?= htmlspecialchars($t('admin_logs.loading_logs', 'Загрузка логов...'), ENT_QUOTES, 'UTF-8') ?></td></tr>
</tbody></table></div>

<div class="row g-3 crm-kpi-row"><div class="col-md-4"><div class="crm-card crm-kpi-card"><small class="text-muted" data-i18n="admin_logs.kpi_events_today"><?= htmlspecialchars($t('admin_logs.kpi_events_today', 'Событий за сутки'), ENT_QUOTES, 'UTF-8') ?></small><h2 class="h4 mb-0" data-i18n="admin_logs.loading"><?= htmlspecialchars($t('admin_logs.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></h2></div></div><div class="col-md-4"><div class="crm-card crm-kpi-card"><small class="text-muted" data-i18n="admin_logs.kpi_active_users"><?= htmlspecialchars($t('admin_logs.kpi_active_users', 'Активных пользователей'), ENT_QUOTES, 'UTF-8') ?></small><h2 class="h4 mb-0" data-i18n="admin_logs.loading"><?= htmlspecialchars($t('admin_logs.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></h2></div></div><div class="col-md-4"><div class="crm-card crm-kpi-card"><small class="text-muted" data-i18n="admin_logs.kpi_change_events"><?= htmlspecialchars($t('admin_logs.kpi_change_events', 'Событий изменений'), ENT_QUOTES, 'UTF-8') ?></small><h2 class="h4 mb-0" data-i18n="admin_logs.loading"><?= htmlspecialchars($t('admin_logs.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></h2></div></div></div>

<div class="offcanvas offcanvas-end crm-drawer-wide" tabindex="-1" id="logDetailDrawer"><div class="offcanvas-header"><h5 data-i18n="admin_logs.drawer_title"><?= htmlspecialchars($t('admin_logs.drawer_title', 'Детали события'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div><div class="offcanvas-body"><div class="text-muted" data-i18n="admin_logs.drawer_empty"><?= htmlspecialchars($t('admin_logs.drawer_empty', 'Выберите событие из таблицы, чтобы увидеть детали.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>

</main></div></div>
