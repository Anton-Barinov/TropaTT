<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Логи пользователей'; ?>
<body data-page="admin" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin">Админка</a></li><li class="breadcrumb-item active">Логи пользователей</li></ol><h1 class="crm-page-title">Логи пользователей</h1><p class="crm-subtitle">История входов, аудита, учета времени и действий сотрудников.</p></div></div>

<div class="crm-toolbar crm-toolbar-surface crm-filters-card d-flex flex-wrap gap-2">
  <select id="adminLogsSourceFilter" class="form-select crm-field-w-220">
    <option value="audit">Журнал аудита</option>
    <option value="security">Журнал безопасности</option>
    <option value="request">Журнал запросов</option>
    <option value="worklog">Записи учета времени</option>
  </select>
  <input id="adminLogsUserFilter" class="form-control crm-field-w-240" placeholder="ID пользователя / автора">
  <input id="adminLogsEntityFilter" class="form-control crm-field-w-220" placeholder="Тип объекта (task/project/...)">
  <input id="adminLogsRouteFilter" class="form-control crm-field-w-260" placeholder="Маршрут запроса">
  <input id="adminLogsFromFilter" class="form-control crm-field-w-180" type="date">
  <input id="adminLogsToFilter" class="form-control crm-field-w-180" type="date">
  <button id="adminLogsApplyBtn" class="btn crm-btn-secondary" type="button">Применить</button>
  <button id="adminLogsResetBtn" class="btn crm-btn-muted" type="button">Сбросить</button>
</div>

<div class="crm-card crm-section-card mb-3">
  <div class="d-flex flex-wrap gap-2 align-items-end">
    <div>
      <label class="form-label mb-1">Активность пользователя (агрегированно)</label>
      <input id="adminLogsUserActivityInput" class="form-control crm-field-min-w-260" placeholder="ID пользователя">
    </div>
    <button id="adminLogsUserActivityBtn" class="btn crm-btn-secondary" type="button">Показать активность</button>
  </div>
  <div id="adminUserActivitySummary" class="text-muted mt-2">Введите ID пользователя, чтобы увидеть активность в журналах запросов, безопасности и аудита.</div>
</div>

<div class="crm-card crm-section-card p-0 table-responsive mb-3"><table class="table crm-table mb-0"><thead><tr><th>Время</th><th>Пользователь</th><th>Источник/событие</th><th>Объект/маршрут</th><th>IP/Статус</th><th style="width:130px">Детали</th></tr></thead><tbody id="adminLogsTableBody">
<tr><td colspan="6" class="text-muted">Загрузка логов...</td></tr>
</tbody></table></div>

<div class="row g-3 crm-kpi-row"><div class="col-md-4"><div class="crm-card crm-kpi-card"><small class="text-muted">Событий за сутки</small><h2 class="h4 mb-0">Загрузка...</h2></div></div><div class="col-md-4"><div class="crm-card crm-kpi-card"><small class="text-muted">Активных пользователей</small><h2 class="h4 mb-0">Загрузка...</h2></div></div><div class="col-md-4"><div class="crm-card crm-kpi-card"><small class="text-muted">Событий изменений</small><h2 class="h4 mb-0">Загрузка...</h2></div></div></div>

<div class="offcanvas offcanvas-end crm-drawer-wide" tabindex="-1" id="logDetailDrawer"><div class="offcanvas-header"><h5>Детали события</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div><div class="offcanvas-body"><div class="text-muted">Выберите событие из таблицы, чтобы увидеть детали.</div></div></div>

</main></div></div>
