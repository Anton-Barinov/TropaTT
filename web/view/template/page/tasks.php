<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Задачи'; ?>
<body data-page="tasks" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav">
</nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid d-flex align-items-center gap-2"><button class="btn crm-btn-secondary d-xl-none" id="sidebarToggle" aria-label="Открыть меню"></button><div class="input-group crm-field-w-420" data-global-search><span class="input-group-text"></span><input id="tasksGlobalSearchInput" class="form-control" placeholder="Поиск по задачам" aria-label="Глобальный поиск по задачам"></div><div class="ms-auto d-flex gap-2" data-global-actions="1"><button class="btn crm-btn-ghost crm-btn-icon" data-global-notifications data-bs-toggle="popover" data-bs-html="true" data-bs-content="<div class='text-muted small'>Загрузка уведомлений...</div>" aria-label="Уведомления"></button><div class="dropdown"><button class="btn crm-btn-ghost dropdown-toggle" data-bs-toggle="dropdown">Пользователь</button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="index.php?route=profile">Мой профиль</a></li><li><button class="dropdown-item" type="button" data-action="logout">Выйти</button></li></ul></div></div></div></header>
<main class="crm-content crm-tasks-page"><?php crm_page_head([
  ['label' => 'Главная', 'href' => 'index.php?route=dashboard'],
  ['label' => 'Задачи', 'active' => true],
], 'Задачи', 'Планирование, контроль сроков и работа с родительскими и дочерними задачами.', '<button class="btn crm-btn-primary" type="button" data-open-modal="createTaskModal">Создать задачу</button>'); ?>

<div id="tasksAiPriorityCard" data-ai-state="idle"><div class="crm-tasks-local-actions"><div class="btn-group crm-tasks-view-toggle" role="group" aria-label="Вид списка задач" data-tasks-view-toggle><button class="btn crm-btn-secondary" type="button" data-tasks-view="list">Список</button><button class="btn crm-btn-secondary" type="button" data-tasks-view="tree">Иерархия</button><button class="btn crm-btn-secondary" type="button" data-tasks-view="cards">Карточки</button></div><a class="btn crm-btn-secondary" href="index.php?route=kanban">Канбан</a><button id="tasksAiPriorityBtn" class="btn crm-btn-secondary" type="button" data-requires-ai-use="1" title="Сформировать AI-ранжирование задач по риску и срочности">AI-приоритет</button><button id="tasksAiPriorityResetBtn" class="btn crm-btn-muted d-none" type="button" data-requires-ai-use="1" title="Вернуть обычный порядок задач">Сброс AI-порядка</button></div>
<div id="tasksAiPriorityState" class="small text-muted mb-2"></div></div>

<div class="crm-toolbar crm-filters-card d-flex flex-wrap gap-2 align-items-center"><label class="visually-hidden" for="tasksSearchInput">Поиск задач</label><input id="tasksSearchInput" class="form-control crm-field-w-280" placeholder="Поиск: SLA, клиент, исполнитель"><label class="visually-hidden" for="tasksAssigneeFilter">Исполнитель</label><select id="tasksAssigneeFilter" class="form-select crm-field-w-170"><option value="">Исполнитель</option></select><label class="visually-hidden" for="tasksManagerFilter">Менеджер</label><select id="tasksManagerFilter" class="form-select crm-field-w-170"><option value="">Менеджер</option></select><label class="visually-hidden" for="tasksProjectFilter">Проект</label><select id="tasksProjectFilter" class="form-select crm-field-w-200"><option value="">Все проекты</option></select><label class="visually-hidden" for="tasksStatusFilter">Статус задачи</label><select id="tasksStatusFilter" class="form-select crm-field-w-170"><option value="">Все статусы</option><option value="new">К выполнению</option><option value="in_progress">В работе</option><option value="blocked">Блокировано</option><option value="done">Готово</option></select><label class="visually-hidden" for="tasksPriorityFilter">Приоритет задачи</label><select id="tasksPriorityFilter" class="form-select crm-field-w-170"><option value="">Все приоритеты</option><option value="low">Низкий</option><option value="normal">Нормальный</option><option value="high">Высокий</option><option value="urgent">Срочный</option></select><label class="visually-hidden" for="tasksTagFilter">Тег</label><select id="tasksTagFilter" class="form-select crm-field-w-170"><option value="">Все теги</option></select><label class="visually-hidden" for="tasksSavedViewSelect">Сохраненные виды задач</label><select id="tasksSavedViewSelect" class="form-select crm-field-w-220"><option value="">Сохраненные виды</option></select><button id="tasksSaveViewBtn" class="btn crm-btn-secondary" type="button">Сохранить вид</button><button id="tasksDeleteViewBtn" class="btn crm-btn-muted" type="button">Удалить вид</button><button id="tasksFiltersResetBtn" class="btn crm-btn-muted" type="button">Сбросить</button></div>

<div id="bulkActionsBar" class="alert alert-primary d-none d-flex justify-content-between align-items-center" role="region" aria-label="Bulk actions">
  <div>Выбрано задач: <strong data-selected-count>0</strong> <span class="small ms-2" id="tasksBulkResult" aria-live="polite"></span></div>
  <div class="d-flex gap-2">
    <select id="tasksBulkStatusSelect" class="form-select form-select-sm crm-field-w-170" aria-label="Изменить статус">
      <option value="">Статус...</option>
      <option value="new">К выполнению</option>
      <option value="in_progress">В работе</option>
      <option value="blocked">Блокировано</option>
      <option value="done">Готово</option>
    </select>
    <select id="tasksBulkPrioritySelect" class="form-select form-select-sm crm-field-w-170" aria-label="Изменить приоритет">
      <option value="">Приоритет...</option>
      <option value="low">Низкий</option>
      <option value="normal">Нормальный</option>
      <option value="high">Высокий</option>
      <option value="urgent">Срочный</option>
    </select>
    <button class="btn btn-sm btn-light" type="button" data-bulk-archive="1">В архив</button>
    <button class="btn btn-sm btn-light" type="button" data-bulk-unarchive="1">Из архива</button>
    <button class="btn btn-sm btn-light" data-open-modal="assignUserModal">Назначить</button>
    <button class="btn btn-sm crm-btn-danger-soft" data-confirm-delete>Удалить</button>
    <button class="btn btn-sm crm-btn-secondary" type="button" id="tasksBulkHelpBtn" title="Шорткаты: Shift+A архив, Shift+U из архива, Shift+D удалить">?</button>
  </div>
</div>

<section id="tasksStates">
  <div data-state-item="default">
    <div class="table-responsive crm-card p-0" data-tasks-list-view>
      <table class="table table-hover align-middle mb-0 crm-table" data-select-table data-bulk-target="bulkActionsBar"><thead><tr><th style="width:40px"><input class="form-check-input" type="checkbox" data-select-all aria-label="Выбрать все задачи"></th><th><button type="button" class="btn btn-link p-0 crm-th-sort" data-tasks-sort="title">Задача</button></th><th>Проект</th><th><button type="button" class="btn btn-link p-0 crm-th-sort" data-tasks-sort="due_at">Срок</button></th><th><button type="button" class="btn btn-link p-0 crm-th-sort" data-tasks-sort="status_code">Статус</button></th><th><button type="button" class="btn btn-link p-0 crm-th-sort" data-tasks-sort="priority_code">Приоритет</button></th><th style="width:230px">Действия</th></tr></thead><tbody>
        <tr><td colspan="7" class="text-muted">Загрузка задач...</td></tr>
      </tbody></table>
    </div>
    <div class="crm-tasks-view-surface d-none" data-tasks-tree-view></div>
    <div class="crm-tasks-view-surface d-none" data-tasks-cards-view></div>
  </div>

  <div data-state-item="empty" class="d-none"><div class="crm-empty-state"><strong>Задач пока нет</strong><p class="mb-3">Создайте первую задачу, чтобы запустить рабочий поток команды.</p><button class="btn crm-btn-primary" data-open-modal="createTaskModal">Создать задачу</button></div></div>
  <div data-state-item="no-results" class="d-none"><div class="crm-empty-state"><strong>Ничего не найдено</strong><p class="mb-3">Попробуйте очистить фильтры или изменить поисковый запрос.</p></div></div>
  <div data-state-item="loading" class="d-none"><div class="crm-card p-4"><div class="crm-skeleton mb-2" style="height:16px"></div><div class="crm-skeleton mb-2" style="height:16px"></div><div class="crm-skeleton" style="height:16px"></div></div></div>
  <div data-state-item="error" class="d-none"><div class="crm-empty-state"><strong>Ошибка загрузки задач</strong><p class="mb-0">Не удалось обновить список. Обновите страницу или повторите позже.</p></div></div>
  <div data-state-item="no-access" class="d-none"><div class="crm-empty-state"><strong>Нет доступа к разделу</strong><p class="mb-3">Запросите права у администратора проекта.</p><button class="btn crm-btn-subtle" data-bs-toggle="tooltip" title="Нужна роль Менеджер или Администратор">Почему недоступно</button></div></div>
</section>
<div id="tasksPager" class="crm-table-pager d-none" aria-live="polite"></div>

</main></div></div>
