<?php declare(strict_types=1); ?>
<?php $title = $t('kanban.title', 'TropaTT — Канбан'); ?>
<body data-page="kanban" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-kanban-page"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="kanban.page_title"><?= htmlspecialchars($t('kanban.page_title', 'Канбан'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="kanban.subtitle"><?= htmlspecialchars($t('kanban.subtitle', 'Задачи по статусам в рабочей доске.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="crm-page-actions"><button class="btn crm-btn-primary" type="button" data-open-modal="createTaskModal" data-i18n="kanban.create_task"><?= htmlspecialchars($t('kanban.create_task', 'Создать задачу'), ENT_QUOTES, 'UTF-8') ?></button></div></div>
<div class="crm-kanban-local-actions"><a class="btn crm-btn-secondary" href="index.php?route=tasks" data-i18n="kanban.tasks_link"><?= htmlspecialchars($t('kanban.tasks_link', 'Задачи'), ENT_QUOTES, 'UTF-8') ?></a></div>
<section class="crm-kanban-filters crm-filters-card" aria-label="<?= htmlspecialchars($t('kanban.filters_aria', 'Фильтры канбан-доски'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="kanban.filters_aria">
  <div class="crm-kanban-search">
    <label class="crm-filter-label" for="kanbanSearchInput" data-i18n="kanban.filters.search"><?= htmlspecialchars($t('kanban.filters.search', 'Поиск'), ENT_QUOTES, 'UTF-8') ?></label>
    <input class="form-control" id="kanbanSearchInput" type="search" placeholder="<?= htmlspecialchars($t('kanban.filters.search_placeholder', 'Название, описание или код задачи'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="kanban.filters.search_placeholder">
  </div>
  <div>
    <label class="crm-filter-label" for="kanbanAssigneeFilter" data-i18n="kanban.filters.assignee"><?= htmlspecialchars($t('kanban.filters.assignee', 'Исполнитель'), ENT_QUOTES, 'UTF-8') ?></label>
    <select class="form-select" id="kanbanAssigneeFilter" aria-label="<?= htmlspecialchars($t('kanban.filters.assignee_aria', 'Фильтр по исполнителям'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="kanban.filters.assignee_aria"></select>
  </div>
  <div>
    <label class="crm-filter-label" for="kanbanManagerFilter" data-i18n="kanban.filters.manager"><?= htmlspecialchars($t('kanban.filters.manager', 'Менеджер'), ENT_QUOTES, 'UTF-8') ?></label>
    <select class="form-select" id="kanbanManagerFilter" aria-label="<?= htmlspecialchars($t('kanban.filters.manager_aria', 'Фильтр по менеджерам'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="kanban.filters.manager_aria"></select>
  </div>
  <div>
    <label class="crm-filter-label" for="kanbanProjectFilter" data-i18n="kanban.filters.project"><?= htmlspecialchars($t('kanban.filters.project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></label>
    <select class="form-select" id="kanbanProjectFilter" aria-label="<?= htmlspecialchars($t('kanban.filters.project_aria', 'Фильтр по проектам'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="kanban.filters.project_aria"></select>
  </div>
  <div>
    <label class="crm-filter-label" for="kanbanCycleFilter" data-i18n="kanban.filters.cycle"><?= htmlspecialchars($t('kanban.filters.cycle', 'Цикл'), ENT_QUOTES, 'UTF-8') ?></label>
    <select class="form-select" id="kanbanCycleFilter" aria-label="<?= htmlspecialchars($t('kanban.filters.cycle_aria', 'Фильтр по циклам'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="kanban.filters.cycle_aria"></select>
  </div>
  <div class="crm-kanban-due-filters" role="group" aria-label="<?= htmlspecialchars($t('kanban.filters.due_aria', 'Фильтры по срокам'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="kanban.filters.due_aria">
    <button class="btn crm-btn-secondary" type="button" data-kanban-due="overdue" data-i18n="kanban.filters.overdue"><?= htmlspecialchars($t('kanban.filters.overdue', 'Просроченные'), ENT_QUOTES, 'UTF-8') ?></button>
    <button class="btn crm-btn-secondary" type="button" data-kanban-due="today" data-i18n="kanban.filters.today"><?= htmlspecialchars($t('kanban.filters.today', 'Сегодня'), ENT_QUOTES, 'UTF-8') ?></button>
    <button class="btn crm-btn-secondary" type="button" data-kanban-due="week" data-i18n="kanban.filters.week"><?= htmlspecialchars($t('kanban.filters.week', 'На неделе'), ENT_QUOTES, 'UTF-8') ?></button>
  </div>
  <div class="crm-kanban-tag-filter-area" id="kanbanTagChipFilter"></div>
  <div class="crm-kanban-filter-summary">
    <span id="kanbanResultSummary"><?= htmlspecialchars($t('kanban.summary.empty', 'Показано 0 из 0 задач'), ENT_QUOTES, 'UTF-8') ?></span>
    <button class="btn crm-btn-secondary" type="button" id="kanbanFiltersResetBtn" disabled data-i18n="kanban.filters.reset"><?= htmlspecialchars($t('kanban.filters.reset', 'Сбросить'), ENT_QUOTES, 'UTF-8') ?></button>
  </div>
</section>
<div class="crm-mobile-work-mode-hint d-md-none" data-i18n="kanban.mobile_hint"><?= htmlspecialchars($t('kanban.mobile_hint', 'Колонки доски пролистываются горизонтально. Для полного списка задач используйте раздел «Задачи».'), ENT_QUOTES, 'UTF-8') ?></div>
<div id="kanbanMobileStatusTabs" class="crm-kanban-mobile-tabs d-md-none" role="tablist" aria-label="<?= htmlspecialchars($t('kanban.mobile_status_aria', 'Статусы канбана'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="kanban.mobile_status_aria"></div>
    <div class="crm-kanban"></div>
</main></div></div>
