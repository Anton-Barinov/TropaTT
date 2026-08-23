<?php declare(strict_types=1); ?>
<?php $title = $t('admin_logs.title', 'TropaTT — Журналы и ошибки'); ?>
<body data-page="admin" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="admin_logs.link_admin"><?= htmlspecialchars($t('admin_logs.link_admin', 'Админка'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="admin_logs.breadcrumb"><?= htmlspecialchars($t('admin_logs.breadcrumb', 'Журналы'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" data-i18n="admin_logs.page_title"><?= htmlspecialchars($t('admin_logs.page_title', 'Журналы и ошибки'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_logs.subtitle"><?= htmlspecialchars($t('admin_logs.subtitle', 'Всё, что происходит в системе: действия сотрудников, работа API, ошибки и безопасность.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>

<div class="crm-segmented-filter crm-logs-tabs mb-3" id="adminLogsTabs" role="tablist">
  <button class="crm-segmented-filter-btn active" type="button" role="tab" data-log-tab="errors"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-bug"></i></span><span data-i18n="admin_logs.tab_errors"><?= htmlspecialchars($t('admin_logs.tab_errors', 'Ошибки'), ENT_QUOTES, 'UTF-8') ?></span></button>
  <button class="crm-segmented-filter-btn" type="button" role="tab" data-log-tab="users"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-user-clock"></i></span><span data-i18n="admin_logs.tab_users"><?= htmlspecialchars($t('admin_logs.tab_users', 'Действия'), ENT_QUOTES, 'UTF-8') ?></span></button>
  <button class="crm-segmented-filter-btn" type="button" role="tab" data-log-tab="api"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-plug"></i></span><span data-i18n="admin_logs.tab_api"><?= htmlspecialchars($t('admin_logs.tab_api', 'API-запросы'), ENT_QUOTES, 'UTF-8') ?></span></button>
  <button class="crm-segmented-filter-btn" type="button" role="tab" data-log-tab="security"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span><span data-i18n="admin_logs.tab_security"><?= htmlspecialchars($t('admin_logs.tab_security', 'Вход и безопасность'), ENT_QUOTES, 'UTF-8') ?></span></button>
  <button class="crm-segmented-filter-btn" type="button" role="tab" data-log-tab="mcp"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-robot"></i></span><span data-i18n="admin_logs.tab_mcp"><?= htmlspecialchars($t('admin_logs.tab_mcp', 'MCP / AI'), ENT_QUOTES, 'UTF-8') ?></span></button>
  <button class="crm-segmented-filter-btn" type="button" role="tab" data-log-tab="worklog"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-stopwatch"></i></span><span data-i18n="admin_logs.tab_worklog"><?= htmlspecialchars($t('admin_logs.tab_worklog', 'Учёт времени'), ENT_QUOTES, 'UTF-8') ?></span></button>
</div>

<div class="crm-toolbar crm-toolbar-surface crm-filters-card d-flex flex-wrap gap-2 align-items-end mb-3">
  <div>
    <label class="form-label mb-0 small text-muted" for="adminLogsPeriod" data-i18n="admin_logs.lbl_period"><?= htmlspecialchars($t('admin_logs.lbl_period', 'Период'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="adminLogsPeriod" class="form-select crm-field-w-160">
      <option value="today" data-i18n="admin_logs.period_today"><?= htmlspecialchars($t('admin_logs.period_today', 'Сегодня'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="yesterday" data-i18n="admin_logs.period_yesterday"><?= htmlspecialchars($t('admin_logs.period_yesterday', 'Вчера'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="7d" selected data-i18n="admin_logs.period_7d"><?= htmlspecialchars($t('admin_logs.period_7d', 'Последние 7 дней'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="30d" data-i18n="admin_logs.period_30d"><?= htmlspecialchars($t('admin_logs.period_30d', 'Последние 30 дней'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="all" data-i18n="admin_logs.period_all"><?= htmlspecialchars($t('admin_logs.period_all', 'За всё время'), ENT_QUOTES, 'UTF-8') ?></option>
    </select>
  </div>
  <div data-tab-for="errors" class="d-none">
    <label class="form-label mb-0 small text-muted" for="adminLogsLevel" data-i18n="admin_logs.lbl_level"><?= htmlspecialchars($t('admin_logs.lbl_level', 'Критичность'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="adminLogsLevel" class="form-select crm-field-w-180">
      <option value="" data-i18n="admin_logs.level_any"><?= htmlspecialchars($t('admin_logs.level_any', 'Любая'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="fatal" data-i18n="admin_logs.level_fatal"><?= htmlspecialchars($t('admin_logs.level_fatal', 'Критические сбои'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="exception" data-i18n="admin_logs.level_exception"><?= htmlspecialchars($t('admin_logs.level_exception', 'Сбои (исключения)'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="error" data-i18n="admin_logs.level_error"><?= htmlspecialchars($t('admin_logs.level_error', 'Ошибки'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="warning" data-i18n="admin_logs.level_warning"><?= htmlspecialchars($t('admin_logs.level_warning', 'Предупреждения'), ENT_QUOTES, 'UTF-8') ?></option>
    </select>
  </div>
  <div data-tab-for="errors" class="d-none">
    <label class="form-label mb-0 small text-muted" for="adminLogsSource" data-i18n="admin_logs.lbl_source"><?= htmlspecialchars($t('admin_logs.lbl_source', 'Источник'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="adminLogsSource" class="form-select crm-field-w-190">
      <option value="" data-i18n="admin_logs.source_any"><?= htmlspecialchars($t('admin_logs.source_any', 'Все источники'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="server_error" data-i18n="admin_logs.source_php"><?= htmlspecialchars($t('admin_logs.source_php', 'Сервер (PHP)'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="frontend_error" data-i18n="admin_logs.source_js"><?= htmlspecialchars($t('admin_logs.source_js', 'Браузер (JavaScript)'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="module_error" data-i18n="admin_logs.source_module"><?= htmlspecialchars($t('admin_logs.source_module', 'Модули расширений'), ENT_QUOTES, 'UTF-8') ?></option>
    </select>
  </div>
  <div data-tab-for="api mcp" class="d-none">
    <label class="form-label mb-0 small text-muted" for="adminLogsMethod" data-i18n="admin_logs.lbl_method"><?= htmlspecialchars($t('admin_logs.lbl_method', 'Метод'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="adminLogsMethod" class="form-select crm-field-w-120">
      <option value="" data-i18n="admin_logs.method_any"><?= htmlspecialchars($t('admin_logs.method_any', 'Любой'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="GET">GET</option>
      <option value="POST">POST</option>
      <option value="PATCH">PATCH</option>
      <option value="PUT">PUT</option>
      <option value="DELETE">DELETE</option>
    </select>
  </div>
  <div data-tab-for="api" class="d-none">
    <label class="form-label mb-0 small text-muted" for="adminLogsStatus" data-i18n="admin_logs.lbl_status"><?= htmlspecialchars($t('admin_logs.lbl_status', 'Результат'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="adminLogsStatus" class="form-select crm-field-w-190">
      <option value="" data-i18n="admin_logs.status_any"><?= htmlspecialchars($t('admin_logs.status_any', 'Все результаты'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="2xx" data-i18n="admin_logs.status_ok"><?= htmlspecialchars($t('admin_logs.status_ok', 'Успешные'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="4xx" data-i18n="admin_logs.status_client"><?= htmlspecialchars($t('admin_logs.status_client', 'Ошибки запросов'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="5xx" data-i18n="admin_logs.status_server"><?= htmlspecialchars($t('admin_logs.status_server', 'Ошибки сервера'), ENT_QUOTES, 'UTF-8') ?></option>
    </select>
  </div>
  <div data-tab-for="security" class="d-none">
    <label class="form-label mb-0 small text-muted" for="adminLogsEvent" data-i18n="admin_logs.lbl_event"><?= htmlspecialchars($t('admin_logs.lbl_event', 'Событие'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="adminLogsEvent" class="form-select crm-field-w-240">
      <option value="" data-i18n="admin_logs.event_any"><?= htmlspecialchars($t('admin_logs.event_any', 'Все события'), ENT_QUOTES, 'UTF-8') ?></option>
    </select>
  </div>
  <div data-tab-for="users" class="d-none">
    <label class="form-label mb-0 small text-muted" for="adminLogsEntity" data-i18n="admin_logs.lbl_entity"><?= htmlspecialchars($t('admin_logs.lbl_entity', 'Раздел'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="adminLogsEntity" class="form-select crm-field-w-190">
      <option value="" data-i18n="admin_logs.entity_any"><?= htmlspecialchars($t('admin_logs.entity_any', 'Все разделы'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="task" data-i18n="admin_logs.entity_task"><?= htmlspecialchars($t('admin_logs.entity_task', 'Задачи'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="project" data-i18n="admin_logs.entity_project"><?= htmlspecialchars($t('admin_logs.entity_project', 'Проекты'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="user" data-i18n="admin_logs.entity_user"><?= htmlspecialchars($t('admin_logs.entity_user', 'Пользователи'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="worklog" data-i18n="admin_logs.entity_worklog"><?= htmlspecialchars($t('admin_logs.entity_worklog', 'Учёт времени'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="notification" data-i18n="admin_logs.entity_notification"><?= htmlspecialchars($t('admin_logs.entity_notification', 'Уведомления'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="external_user" data-i18n="admin_logs.entity_external"><?= htmlspecialchars($t('admin_logs.entity_external', 'Внешний доступ'), ENT_QUOTES, 'UTF-8') ?></option>
    </select>
  </div>
  <div data-tab-for="errors" class="d-none flex-grow-1" style="min-width:200px;max-width:320px">
    <label class="form-label mb-0 small text-muted" for="adminLogsSearch" data-i18n="admin_logs.lbl_search"><?= htmlspecialchars($t('admin_logs.lbl_search', 'Поиск по тексту'), ENT_QUOTES, 'UTF-8') ?></label>
    <input id="adminLogsSearch" class="form-control" placeholder="<?= htmlspecialchars($t('admin_logs.search_placeholder', 'Например: вход, задача, ошибка…'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_logs.search_placeholder">
  </div>
  <div data-tab-for="users api security worklog" class="d-none">
    <label class="form-label mb-0 small text-muted" for="adminLogsUser"><?= htmlspecialchars($t('admin_logs.lbl_user', 'Пользователь (ID)'), ENT_QUOTES, 'UTF-8') ?></label>
    <input id="adminLogsUser" class="form-control crm-field-w-200" placeholder="usr_…">
  </div>
  <div class="ms-auto d-flex gap-2">
    <button id="adminLogsApplyBtn" class="btn crm-btn-secondary" type="button" data-i18n="page.filter"><?= htmlspecialchars($t('page.filter', 'Применить'), ENT_QUOTES, 'UTF-8') ?></button>
    <button id="adminLogsResetBtn" class="btn crm-btn-muted" type="button" data-i18n="page.reset"><?= htmlspecialchars($t('page.reset', 'Сбросить'), ENT_QUOTES, 'UTF-8') ?></button>
  </div>
</div>

<div id="adminLogsSummary" class="d-flex flex-wrap gap-2 mb-3"></div>

<div class="crm-card crm-section-card mb-3" id="adminLogsErrorChartCard">
  <div class="crm-section-head">
    <div>
      <h2 class="h6 mb-0" data-i18n="admin_logs.chart_title"><?= htmlspecialchars($t('admin_logs.chart_title', 'Ошибки браузера (API) по часам'), ENT_QUOTES, 'UTF-8') ?></h2>
      <div class="crm-section-note" id="adminLogsErrorChartNote" data-i18n="admin_logs.chart_note"><?= htmlspecialchars($t('admin_logs.chart_note', 'Сбои связи с сервером: сеть, таймауты, некорректные ответы.'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <select id="adminLogsErrorChartRange" class="form-select crm-field-w-140">
      <option value="24" data-i18n="admin_logs.chart_24h"><?= htmlspecialchars($t('admin_logs.chart_24h', '24 часа'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="48" selected data-i18n="admin_logs.chart_48h"><?= htmlspecialchars($t('admin_logs.chart_48h', '48 часов'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="168" data-i18n="admin_logs.chart_7d"><?= htmlspecialchars($t('admin_logs.chart_7d', '7 дней'), ENT_QUOTES, 'UTF-8') ?></option>
    </select>
  </div>
  <div class="crm-error-chart-wrap">
    <div id="adminLogsErrorChart" class="crm-error-chart"><div class="text-muted p-3" data-i18n="admin_logs.loading"><?= htmlspecialchars($t('admin_logs.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
    <div class="crm-error-chart-legend">
      <span class="crm-legend-item"><i class="crm-legend-dot crm-legend-transport"></i><span data-i18n="admin_logs.chart_transport"><?= htmlspecialchars($t('admin_logs.chart_transport', 'Сеть/связь'), ENT_QUOTES, 'UTF-8') ?></span></span>
      <span class="crm-legend-item"><i class="crm-legend-dot crm-legend-other"></i><span data-i18n="admin_logs.chart_other"><?= htmlspecialchars($t('admin_logs.chart_other', 'Прочие'), ENT_QUOTES, 'UTF-8') ?></span></span>
    </div>
  </div>
</div>

<div class="crm-card crm-section-card p-0 table-responsive mb-3"><table class="table crm-table mb-0"><thead id="adminLogsHead"></thead><tbody id="adminLogsTableBody">
<tr><td colspan="6" class="text-muted" data-i18n="admin_logs.loading_logs"><?= htmlspecialchars($t('admin_logs.loading_logs', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr>
</tbody></table></div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="text-muted small" id="adminLogsPageInfo"></div>
  <div class="btn-group">
    <button id="adminLogsPrevBtn" class="btn btn-sm crm-btn-secondary" type="button"><i class="fa-solid fa-chevron-left"></i></button>
    <button id="adminLogsNextBtn" class="btn btn-sm crm-btn-secondary" type="button"><i class="fa-solid fa-chevron-right"></i></button>
  </div>
</div>

<div class="offcanvas offcanvas-end crm-drawer-wide" tabindex="-1" id="logDetailDrawer"><div class="offcanvas-header"><h5 data-i18n="admin_logs.drawer_title"><?= htmlspecialchars($t('admin_logs.drawer_title', 'Детали события'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div><div class="offcanvas-body"><div class="text-muted" data-i18n="admin_logs.drawer_empty"><?= htmlspecialchars($t('admin_logs.drawer_empty', 'Выберите событие из таблицы, чтобы увидеть детали.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>

</main></div></div>
