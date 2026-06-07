<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Аналитика'; ?>
<body data-page="analytics" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-analytics-page"><div class="crm-page-head"><div><h1 class="crm-page-title">Аналитика</h1><p class="crm-subtitle" data-analytics-subtitle>SLA, выполнение задач, загрузка команд и проектные риски.</p></div><div class="crm-page-actions"><a class="btn crm-btn-secondary" href="index.php?route=time-analytics"><i class="fa-regular fa-clock"></i> Учет времени</a><button class="btn crm-btn-secondary" type="button" id="analyticsExportBtn" disabled>Экспорт CSV</button></div></div>
<div class="crm-toolbar-surface crm-analytics-toolbar mb-3" aria-label="Фильтры аналитики"><select class="form-select crm-field-w-220" id="analyticsProjectFilter" aria-label="Проект"><option value="">Все проекты</option></select><select class="form-select crm-field-w-220" id="analyticsTeamFilter" aria-label="Исполнитель"><option value="">Все исполнители</option></select><button class="btn crm-btn-secondary" type="button" id="analyticsApplyBtn">Применить</button><span class="crm-toolbar-note" data-analytics-filter-note>Показан общий срез по доступным данным.</span></div>
<div class="row g-3 mb-3 crm-kpi-row"><div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted">Всего задач</small><h2 class="h4 mb-0">Загрузка...</h2><span class="crm-badge archived">Ожидание данных</span></div></div><div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted">Доля завершения</small><h2 class="h4 mb-0">Загрузка...</h2><span class="crm-badge archived">Ожидание данных</span></div></div><div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted">Просрочки</small><h2 class="h4 mb-0">Загрузка...</h2><span class="crm-badge archived">Ожидание данных</span></div></div><div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted">Лог времени за неделю</small><h2 class="h4 mb-0">Загрузка...</h2><span class="crm-badge archived">Ожидание данных</span></div></div></div>
<div class="row g-3 mb-3">
  <div class="col-12">
    <div class="crm-card crm-section-card" id="analyticsAiCard" data-ai-state="idle">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">AI-объяснение аналитики</h2><div class="crm-section-note">AI объясняет KPI и риски, но не заменяет числовые метрики.</div></div></div>
      <div class="crm-analytics-ai-pills mb-2">
        <button class="btn btn-sm crm-btn-primary" type="button" id="analyticsAiKpiBtn" title="Сформировать AI-объяснение текущих KPI">Пояснить KPI</button>
        <button class="btn btn-sm btn-light" type="button" id="analyticsAiRisksBtn" title="Сформировать AI-пояснение ключевых рисков">Пояснить риски</button>
        <button class="btn btn-sm btn-light" type="button" id="analyticsAiWorkloadBtn" title="Сформировать AI-сводку по загрузке команд">Сводка загрузки</button>
        <button class="btn btn-sm btn-light" type="button" id="analyticsAiPreviewBtn" disabled title="Открыть предпросмотр AI-предложения">Открыть preview</button>
        <button class="btn btn-sm crm-btn-muted" type="button" id="analyticsAiDismissBtn" disabled title="Отклонить текущее AI-предложение">Отклонить</button>
      </div>
      <div class="small text-muted mb-2" id="analyticsAiState">AI-объяснение пока не сформировано.</div>
      <div class="crm-analytics-ai-result">
        <div class="crm-metric-tile mb-2"><small class="text-muted d-block">Резюме</small><div id="analyticsAiSummaryText">Нажмите «Пояснить KPI», чтобы получить объяснение по текущим метрикам.</div></div>
        <div class="crm-metric-tile mb-2"><small class="text-muted d-block">Факты / KPI</small><div id="analyticsAiFacts">—</div></div>
        <div class="crm-metric-tile"><small class="text-muted d-block">Риски и вопросы</small><div id="analyticsAiInferences">—</div></div>
      </div>
    </div>
  </div>
</div>
<div class="row g-3"><div class="col-lg-8"><div class="crm-card crm-section-card"><div class="crm-section-head"><div><h2 class="h6 mb-0">Выполнение задач</h2><div class="crm-section-note">Динамика выполнения по текущим данным API.</div></div></div><div data-analytics-trend><div class="text-muted">Загрузка тренда...</div></div></div></div><div class="col-lg-4"><div class="crm-card crm-section-card"><div class="crm-section-head"><div><h2 class="h6 mb-0">Команды</h2><div class="crm-section-note">Команды считаются по привязке проектов и суммарной загрузке задач.</div></div></div><div data-analytics-teams><div class="crm-metric-tile mb-2 text-muted">Загрузка командных метрик...</div></div></div></div></div>
<div class="row g-3 mt-1"><div class="col-lg-6"><div class="crm-card crm-section-card"><div class="crm-section-head"><div><h2 class="h6 mb-0">Проекты под риском</h2><div class="crm-section-note">Приоритет по просрочкам, блокировкам и текущему статусу проекта.</div></div></div><div data-analytics-projects><div class="text-muted">Загрузка проектных метрик...</div></div></div></div><div class="col-lg-6"><div class="crm-card crm-section-card"><div class="crm-section-head"><div><h2 class="h6 mb-0">Исполнители</h2><div class="crm-section-note">Фактическая активность и доля завершения по пользователям.</div></div></div><div data-analytics-users><div class="text-muted">Загрузка пользовательских метрик...</div></div></div></div></div>
</main></div></div>
