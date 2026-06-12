<?php declare(strict_types=1); ?>
<?php $title = $t('gantt.title', 'TropaTT — Гант'); ?>
<body data-page="gantt" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-gantt-page"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="gantt.page_title"><?= htmlspecialchars($t('gantt.page_title', 'Гант'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="gantt.subtitle"><?= htmlspecialchars($t('gantt.subtitle', 'План-график задач и проектов по датам.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="crm-page-actions"><button class="btn crm-btn-primary" type="button" data-open-modal="createTaskModal" data-i18n="gantt.btn_create_task"><?= htmlspecialchars($t('gantt.btn_create_task', 'Создать задачу'), ENT_QUOTES, 'UTF-8') ?></button></div></div>
<section class="crm-gantt-shell crm-card crm-section-card">
  <div class="crm-gantt-shell-head">
    <div>
      <div class="crm-eyebrow" data-i18n="gantt.eyebrow"><?= htmlspecialchars($t('gantt.eyebrow', 'План-график'), ENT_QUOTES, 'UTF-8') ?></div>
      <h2 class="h5 mb-1" data-i18n="gantt.heading_title"><?= htmlspecialchars($t('gantt.heading_title', 'Задачи и проекты'), ENT_QUOTES, 'UTF-8') ?></h2>
      <div class="crm-section-note" data-i18n="gantt.heading_note"><?= htmlspecialchars($t('gantt.heading_note', 'Шкала строится по фактическим датам начала и завершения задач, сгруппированным по проектам.'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <div class="crm-gantt-head-tools">
      <div class="crm-gantt-controls">
        <div class="btn-group" role="group" aria-label="<?= htmlspecialchars($t('gantt.scale_aria', 'gantt scale'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="gantt.scale_aria">
          <button class="btn btn-sm btn-outline-secondary" data-gantt-zoom="days" aria-pressed="false" data-i18n="gantt.zoom_days"><?= htmlspecialchars($t('gantt.zoom_days', 'Дни'), ENT_QUOTES, 'UTF-8') ?></button>
          <button class="btn btn-sm btn-outline-secondary active" data-gantt-zoom="weeks" aria-pressed="true" data-i18n="gantt.zoom_weeks"><?= htmlspecialchars($t('gantt.zoom_weeks', 'Недели'), ENT_QUOTES, 'UTF-8') ?></button>
          <button class="btn btn-sm btn-outline-secondary" data-gantt-zoom="months" aria-pressed="false" data-i18n="gantt.zoom_months"><?= htmlspecialchars($t('gantt.zoom_months', 'Месяцы'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </div>
      <div class="crm-gantt-quick-tools" id="ganttQuickTools">
        <div class="crm-gantt-search">
          <span class="crm-gantt-search-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
          <label class="visually-hidden" for="ganttSearchInput" data-i18n="gantt.search_label"><?= htmlspecialchars($t('gantt.search_label', 'Поиск задач на диаграмме Ганта'), ENT_QUOTES, 'UTF-8') ?></label>
          <input type="text" id="ganttSearchInput" placeholder="<?= htmlspecialchars($t('gantt.placeholder_search', 'Поиск задач...'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" data-i18n-placeholder="gantt.placeholder_search">
          <button class="crm-gantt-search-clear d-none" type="button" id="ganttSearchClearBtn" aria-label="<?= htmlspecialchars($t('gantt.btn_clear_search_aria', 'Очистить поиск по диаграмме Ганта'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="gantt.btn_clear_search_aria"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="crm-gantt-project-filter">
          <label class="visually-hidden" for="ganttProjectFilter" data-i18n="gantt.filter_project_label"><?= htmlspecialchars($t('gantt.filter_project_label', 'Фильтр проектов на диаграмме Ганта'), ENT_QUOTES, 'UTF-8') ?></label>
          <select id="ganttProjectFilter">
            <option value="all" data-i18n="gantt.opt_all_projects"><?= htmlspecialchars($t('gantt.opt_all_projects', 'Все проекты'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
        </div>
      </div>
    </div>
  </div>
  <div class="crm-gantt-legend" id="ganttLegend"><span class="crm-chip" data-i18n="gantt.loading_statuses"><?= htmlspecialchars($t('gantt.loading_statuses', 'Загрузка статусов...'), ENT_QUOTES, 'UTF-8') ?></span></div>
  <div class="crm-gantt-kpis" id="ganttSummaryTiles"><div class="crm-gantt-kpi-card"><small data-i18n="gantt.kpi_active_tasks"><?= htmlspecialchars($t('gantt.kpi_active_tasks', 'Активные задачи'), ENT_QUOTES, 'UTF-8') ?></small><strong>...</strong><span data-i18n="gantt.kpi_loading_data"><?= htmlspecialchars($t('gantt.kpi_loading_data', 'Подготовка данных'), ENT_QUOTES, 'UTF-8') ?></span></div></div>
  <div class="crm-mobile-work-mode-hint" data-i18n="gantt.mobile_hint"><?= htmlspecialchars($t('gantt.mobile_hint', 'Таймлайн можно прокручивать по горизонтали.'), ENT_QUOTES, 'UTF-8') ?></div>
  <div class="crm-gantt-board">
    <div class="crm-gantt-scale"><div class="crm-gantt-scale-label" data-i18n="gantt.scale_label"><?= htmlspecialchars($t('gantt.scale_label', 'Проект / задача'), ENT_QUOTES, 'UTF-8') ?></div><div class="crm-gantt-scale-track" id="ganttScaleTrack"><span data-i18n="gantt.loading_scale"><?= htmlspecialchars($t('gantt.loading_scale', 'Загрузка шкалы...'), ENT_QUOTES, 'UTF-8') ?></span></div>
      <div class="crm-gantt"><div class="crm-gantt-rows"></div><div class="crm-gantt-lanes">
        <svg class="crm-gantt-dependencies" id="ganttDependenciesSvg"></svg>
      </div></div>
    </div>
  </div>
</section>
<div class="row g-3 mt-1"><div class="col-lg-8"><div class="crm-card crm-section-card crm-gantt-focus-card"><div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="gantt.section_upcoming"><?= htmlspecialchars($t('gantt.section_upcoming', 'Ближайшие сроки'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="gantt.note_upcoming"><?= htmlspecialchars($t('gantt.note_upcoming', 'Задачи с ближайшими дедлайнами и критические участки текущего окна.'), ENT_QUOTES, 'UTF-8') ?></div></div></div><div class="crm-gantt-focus-list" id="ganttMilestonesList"><span class="crm-chip" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></span></div></div></div><div class="col-lg-4"><div class="crm-card crm-section-card crm-gantt-status-card"><div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="gantt.section_summary"><?= htmlspecialchars($t('gantt.section_summary', 'Сводка графика'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="gantt.note_summary"><?= htmlspecialchars($t('gantt.note_summary', 'Индикаторы по срокам, блокировкам и загруженности диапазона.'), ENT_QUOTES, 'UTF-8') ?></div></div></div><div id="ganttStatusSummary"><div class="crm-metric-tile" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div></div></div></div>
</main></div></div>
