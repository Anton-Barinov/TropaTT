<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Проекты'; ?>
<body data-page="projects" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-projects-page"><div class="crm-page-head"><div class="crm-page-intro"><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=dashboard">Главная</a></li><li class="breadcrumb-item active">Проекты</li></ol><h1 class="crm-page-title">Проекты</h1><p class="crm-subtitle">Список проектов со статусами, клиентами, командами и сроками.</p></div><div class="crm-page-actions"><button class="btn crm-btn-primary" type="button" data-open-modal="createProjectModal">Создать проект</button></div></div>

<section class="crm-kanban-filters crm-filters-card">
  <div class="crm-kanban-search">
    <label class="crm-filter-label" for="projectsSearchInput">Поиск</label>
    <input id="projectsSearchInput" class="form-control" type="search" placeholder="Название, описание или код проекта">
  </div>
  <div>
    <label class="crm-filter-label" for="projectsStatusFilter">Статус</label>
    <select id="projectsStatusFilter" class="form-select"><option value="">Все статусы</option><option value="active">Активный</option><option value="in_progress">В работе</option><option value="blocked">Блокирован</option><option value="done">Завершен</option></select>
  </div>
  <div>
    <label class="crm-filter-label" for="projectsClientFilter">Клиент</label>
    <select id="projectsClientFilter" class="form-select"><option value="">Все клиенты</option></select>
  </div>
  <div>
    <label class="crm-filter-label" for="projectsTeamFilter">Команда</label>
    <select id="projectsTeamFilter" class="form-select"><option value="">Все команды</option></select>
  </div>
  <div>
    <label class="crm-filter-label" for="projectsManagerFilter">Менеджер</label>
    <select id="projectsManagerFilter" class="form-select"><option value="">Менеджер</option></select>
  </div>
  <div>
    <label class="crm-filter-label" for="projectsPriorityFilter">Приоритет</label>
    <select id="projectsPriorityFilter" class="form-select"><option value="">Все приоритеты</option><option value="low">Низкий</option><option value="normal">Нормальный</option><option value="high">Высокий</option><option value="urgent">Срочный</option></select>
  </div>
  <div class="crm-filters-view-actions">
    <span id="projectsResultSummary" class="text-muted" style="font-size:11px"></span>
    <button class="btn crm-btn-secondary" type="button" id="projectsFiltersResetBtn" disabled>Сбросить</button>
  </div>
</section>

<div class="row g-3 mb-3" id="projectsDynamicList">
  <div class="col-12"><div class="crm-card"><div class="text-muted">Загрузка проектов...</div></div></div>
</div>

<div id="projectsBulkActionsBar" class="alert alert-primary d-none d-flex justify-content-between align-items-center" role="region" aria-label="Bulk actions projects">
  <div>Выбрано проектов: <strong data-projects-selected-count>0</strong> <span class="small ms-2" id="projectsBulkResult" aria-live="polite"></span></div>
  <div class="d-flex gap-2">
    <select id="projectsBulkStatusSelect" class="form-select form-select-sm crm-field-w-170" aria-label="Изменить статус проектов">
      <option value="">Статус...</option>
      <option value="active">Активные</option>
      <option value="new">К выполнению</option>
      <option value="in_progress">В работе</option>
      <option value="blocked">Блокировано</option>
      <option value="done">Готово</option>
    </select>
    <button class="btn btn-sm crm-btn-danger-soft" type="button" id="projectsBulkDeleteBtn" data-confirm-delete>Удалить</button>
  </div>
</div>

<div class="crm-card crm-section-card p-0 table-responsive" id="projectsTableWrap"><table class="table crm-table mb-0"><thead><tr><th style="width:40px"><input class="form-check-input" type="checkbox" id="projectsBulkSelectAll" aria-label="Выбрать все проекты"></th><th>Проект</th><th>Статус</th><th>Прогресс</th><th>Клиент</th><th>Команда</th><th>Дедлайн</th></tr></thead><tbody>
<tr><td colspan="7" class="text-muted">Загрузка таблицы проектов...</td></tr>
</tbody></table></div>

<div class="modal fade" id="projectsSaveViewModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Сохранить вид проектов</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div><form id="projectsSaveViewForm" novalidate><div class="modal-body"><div class="mb-3"><label class="form-label" for="projectsSaveViewTitle">Название вида</label><input class="form-control" id="projectsSaveViewTitle" name="title" maxlength="120" required placeholder="Например: Активные проекты команды"></div><div class="small text-muted">Будут сохранены текущие фильтры, команда и режим просмотра.</div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit">Сохранить</button></div></form></div></div></div>

</main></div></div>

<div class="modal fade" id="createProjectModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Создать проект</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div><form id="createProjectForm" novalidate><div class="modal-body"><div class="row g-3"><div class="col-md-8"><label class="form-label">Название проекта</label><input class="form-control" name="title" maxlength="255" placeholder="Запуск клиентского портала"></div><div class="col-md-4"><label class="form-label">Статус</label><select class="form-select" name="status"><option value="active">Активный</option><option value="new">К выполнению</option><option value="in_progress">В работе</option><option value="blocked">Блокирован</option><option value="done">Завершен</option></select></div><div class="col-md-6"><label class="form-label">Клиент</label><select class="form-select" name="client_public_id"><option value="">Без клиента</option></select></div><div class="col-md-6"><label class="form-label">Команда</label><select class="form-select" name="team_public_id"><option value="">Команда не назначена</option></select></div><div class="col-md-6"><label class="form-label">Менеджер проекта</label><select class="form-select" name="manager_user_public_id"><option value="">Без менеджера</option></select></div><div class="col-md-6"><label class="form-label">Приоритет</label><select class="form-select" name="priority"><option value="normal">Нормальный</option><option value="low">Низкий</option><option value="high">Высокий</option><option value="urgent">Срочный</option></select></div><div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" name="description" rows="4" placeholder="Цели, контекст и основные ожидания по проекту"></textarea></div><div class="col-12"><div class="form-text" data-project-create-hint>Проект будет создан сразу в рабочей модели API, включая клиента, команду и менеджера.</div></div></div></div><div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button><button type="submit" class="btn crm-btn-primary">Создать</button></div></form></div></div></div>
