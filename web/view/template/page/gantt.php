<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Гант'; ?>
<body data-page="gantt" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-gantt-page"><div class="crm-page-head"><div><h1 class="crm-page-title">Гант</h1><p class="crm-subtitle">План-график задач и проектов по датам.</p></div><div class="crm-page-actions"><button class="btn crm-btn-secondary" type="button" data-open-drawer="filterOffcanvas">Фильтры</button><button class="btn crm-btn-primary" type="button" data-open-modal="createTaskModal">Создать задачу</button></div></div>
<section class="crm-gantt-shell crm-card crm-section-card">
  <div class="crm-gantt-shell-head">
    <div>
      <div class="crm-eyebrow">План-график</div>
      <h2 class="h5 mb-1">Задачи и проекты</h2>
      <div class="crm-section-note">Шкала строится по фактическим датам начала и завершения задач, сгруппированным по проектам.</div>
    </div>
    <div class="crm-gantt-head-tools">
      <div class="crm-gantt-controls">
        <div class="btn-group" role="group" aria-label="gantt scale">
          <button class="btn btn-sm btn-outline-secondary" data-gantt-zoom="days" aria-pressed="false">Дни</button>
          <button class="btn btn-sm btn-outline-secondary active" data-gantt-zoom="weeks" aria-pressed="true">Недели</button>
          <button class="btn btn-sm btn-outline-secondary" data-gantt-zoom="months" aria-pressed="false">Месяцы</button>
        </div>
      </div>
      <div class="crm-gantt-quick-tools" id="ganttQuickTools">
        <div class="crm-gantt-search">
          <span class="crm-gantt-search-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
          <label class="visually-hidden" for="ganttSearchInput">Поиск задач на диаграмме Ганта</label>
          <input type="text" id="ganttSearchInput" placeholder="Поиск задач..." autocomplete="off">
          <button class="crm-gantt-search-clear d-none" type="button" id="ganttSearchClearBtn" aria-label="Очистить поиск по диаграмме Ганта"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="crm-gantt-project-filter">
          <label class="visually-hidden" for="ganttProjectFilter">Фильтр проектов на диаграмме Ганта</label>
          <select id="ganttProjectFilter">
            <option value="all">Все проекты</option>
          </select>
        </div>
      </div>
    </div>
  </div>
  <div class="crm-gantt-legend" id="ganttLegend"><span class="crm-chip">Загрузка статусов...</span></div>
  <div class="crm-gantt-kpis" id="ganttSummaryTiles"><div class="crm-gantt-kpi-card"><small>Активные задачи</small><strong>...</strong><span>Подготовка данных</span></div></div>
  <div class="crm-mobile-work-mode-hint d-md-none">На телефоне сначала используйте «Ближайшие сроки» ниже, а таймлайн можно прокручивать по горизонтали.</div>
  <div class="crm-gantt-mobile-mode d-md-none">
    <button type="button" class="btn crm-btn-secondary is-active" data-gantt-mobile-mode="list">Список</button>
    <button type="button" class="btn crm-btn-secondary" data-gantt-mobile-mode="timeline">Таймлайн</button>
  </div>
  <div id="ganttMobileListMode" class="crm-gantt-mobile-list d-md-none"></div>
  <div class="crm-gantt-board">
    <div class="crm-gantt-scale"><div class="crm-gantt-scale-label">Проект / задача</div><div class="crm-gantt-scale-track" id="ganttScaleTrack"><span>Загрузка шкалы...</span></div>
      <div class="crm-gantt"><div class="crm-gantt-rows"></div><div class="crm-gantt-lanes">
        <svg class="crm-gantt-dependencies" id="ganttDependenciesSvg"></svg>
      </div></div>
    </div>
  </div>
</section>
<div class="row g-3 mt-1"><div class="col-lg-8"><div class="crm-card crm-section-card crm-gantt-focus-card"><div class="crm-section-head"><div><h2 class="h6 mb-0">Ближайшие сроки</h2><div class="crm-section-note">Задачи с ближайшими дедлайнами и критические участки текущего окна.</div></div></div><div class="crm-gantt-focus-list" id="ganttMilestonesList"><span class="crm-chip">Загрузка...</span></div></div></div><div class="col-lg-4"><div class="crm-card crm-section-card crm-gantt-status-card"><div class="crm-section-head"><div><h2 class="h6 mb-0">Сводка графика</h2><div class="crm-section-note">Индикаторы по срокам, блокировкам и загруженности диапазона.</div></div></div><div id="ganttStatusSummary"><div class="crm-metric-tile">Загрузка...</div></div></div></div></div>
</main></div></div>
