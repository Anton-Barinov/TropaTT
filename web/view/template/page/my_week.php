<?php declare(strict_types=1); ?>
<?php $title = htmlspecialchars($t('my_week.title', 'TropaTT — Моя неделя'), ENT_QUOTES, 'UTF-8'); ?>
<body data-page="week" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-my-week-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=dashboard"><?= htmlspecialchars($t('page.home', 'Главная'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active"><?= htmlspecialchars($t('my_week.page_title', 'Моя неделя'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title"><?= htmlspecialchars($t('my_week.page_title', 'Моя неделя'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle"><?= htmlspecialchars($t('my_week.subtitle', 'Задачи, события и рабочая нагрузка на неделю.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="d-flex gap-2"><a class="btn crm-btn-secondary" href="index.php?route=gantt"><?= htmlspecialchars($t('my_week.open_gantt', 'Открыть Гант'), ENT_QUOTES, 'UTF-8') ?></a></div></div>

<div class="crm-my-week-layout-v2">
  <section class="crm-my-week-primary">
    <div id="myWeekAiCard" class="crm-card crm-section-card crm-my-week-ai-card" data-requires-ai-use="1" data-ai-state="idle">
      <div class="crm-section-head">
        <div>
          <h2 class="h6 mb-0"><?= htmlspecialchars($t('my_week.ai_title', 'AI-план недели'), ENT_QUOTES, 'UTF-8') ?></h2>
          <div class="crm-section-note"><?= htmlspecialchars($t('my_week.ai_note', 'План по дням, риски и перегруз без авто-применения.'), ENT_QUOTES, 'UTF-8') ?></div>
          <div id="myWeekAiState" class="small text-muted mt-1"><?= htmlspecialchars($t('my_week.ai_state_idle', 'Состояние: idle'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <button id="myWeekAiGenerateBtn" class="btn btn-sm btn-light crm-btn-compact" type="button"><?= htmlspecialchars($t('my_week.ai_btn_generate', 'Сформировать план'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div id="myWeekAiPlanSummary" class="crm-empty-state">
        <strong><?= htmlspecialchars($t('my_week.ai_suggest_generate', 'План пока не сформирован'), ENT_QUOTES, 'UTF-8') ?></strong>
        <p class="mb-0"><?= htmlspecialchars($t('my_week.ai_suggest_note', 'Нажмите кнопку, чтобы получить AI-предложение на неделю.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div id="myWeekAiPlanDetails" class="mt-2"></div>
      <div class="d-flex gap-2 mt-2 flex-wrap crm-ai-actions">
        <button id="myWeekAiApplyEventsBtn" class="btn btn-sm btn-outline-secondary crm-btn-compact d-none" type="button" disabled><?= htmlspecialchars($t('my_week.ai_btn_apply', 'Создать выбранные события'), ENT_QUOTES, 'UTF-8') ?></button>
        <button id="myWeekAiPreviewBtn" class="btn btn-sm btn-light crm-btn-compact d-none" type="button" disabled><?= htmlspecialchars($t('my_week.ai_btn_preview', 'Открыть'), ENT_QUOTES, 'UTF-8') ?></button>
        <button id="myWeekAiDismissBtn" class="btn btn-sm crm-btn-muted crm-btn-compact d-none" type="button" disabled><?= htmlspecialchars($t('my_week.ai_btn_dismiss', 'Отклонить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </section>

  <aside class="crm-my-week-aside">
    <div class="crm-card crm-section-card"><div class="crm-section-head"><div><h2 class="h6 mb-0"><?= htmlspecialchars($t('my_week.metrics_title', 'Метрики недели'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note"><?= htmlspecialchars($t('my_week.metrics_note', 'Ключевые показатели текущей недели.'), ENT_QUOTES, 'UTF-8') ?></div></div></div><div id="myWeekMetrics"><div class="crm-empty-state"><strong><?= htmlspecialchars($t('my_week.metrics_loading', 'Загрузка недельных метрик...'), ENT_QUOTES, 'UTF-8') ?></strong></div></div></div>
    <div class="crm-card crm-section-card"><div class="crm-section-head"><div><h2 class="h6 mb-0"><?= htmlspecialchars($t('my_week.events_title', 'События недели'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note"><?= htmlspecialchars($t('my_week.events_note', 'Календарные события на текущую неделю.'), ENT_QUOTES, 'UTF-8') ?></div></div></div><div id="myWeekMeetings"><div class="crm-metric-tile"><?= htmlspecialchars($t('my_week.events_loading', 'Загрузка событий недели...'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
  </aside>
</div>

<div class="crm-my-week-stack">
  <div class="crm-card crm-section-card crm-my-week-tasks-section"><div class="crm-section-head"><div><h2 class="h6 mb-0"><?= htmlspecialchars($t('my_week.week_title', 'Задачи недели'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note"><?= htmlspecialchars($t('my_week.week_note', 'Все задачи на текущую неделю с разбивкой по дням.'), ENT_QUOTES, 'UTF-8') ?></div></div></div><div class="table-responsive crm-table-wrap"><table class="table table-hover align-middle mb-0 crm-table" data-my-week-table><thead><tr><th style="min-width:240px"><?= htmlspecialchars($t('my_week.th_task', 'Задача'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:150px"><?= htmlspecialchars($t('my_week.th_assignee', 'Ответственный'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:120px"><?= htmlspecialchars($t('my_week.th_priority', 'Приоритет'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:130px"><?= htmlspecialchars($t('my_week.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:120px"><?= htmlspecialchars($t('my_week.th_deadline', 'Дедлайн'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:200px"><?= htmlspecialchars($t('my_week.th_action', 'Действие'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="myWeekTasksTableBody"><tr><td colspan="6" class="text-muted"><?= htmlspecialchars($t('my_week.week_loading', 'Загрузка задач недели...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div></div>

  <div class="crm-card crm-section-card crm-overdue-section"><div class="crm-section-head"><div><h2 class="h6 mb-0"><?= htmlspecialchars($t('my_week.overdue_title', 'Просроченные задачи'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note"><?= htmlspecialchars($t('my_week.overdue_note', 'Задачи с истекшим сроком, требующие внимания.'), ENT_QUOTES, 'UTF-8') ?></div></div></div><div class="table-responsive crm-table-wrap crm-overdue-table-wrap"><table class="table table-hover align-middle mb-0 crm-table" data-my-week-table><thead><tr><th style="min-width:240px"><?= htmlspecialchars($t('my_week.th_task', 'Задача'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:150px"><?= htmlspecialchars($t('my_week.th_assignee', 'Ответственный'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:120px"><?= htmlspecialchars($t('my_week.th_priority', 'Приоритет'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:130px"><?= htmlspecialchars($t('my_week.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:120px"><?= htmlspecialchars($t('my_week.th_deadline', 'Дедлайн'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:200px"><?= htmlspecialchars($t('my_week.th_action', 'Действие'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="myWeekOverdueTableBody"><tr><td colspan="6" class="text-muted"><?= htmlspecialchars($t('my_week.overdue_checking', 'Проверка просроченных задач...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div></div>
</div>

</main></div></div>
