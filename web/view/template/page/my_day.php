<?php declare(strict_types=1); ?>
<?php $title = htmlspecialchars($t('my_day.title', 'TropaTT — Мой день'), ENT_QUOTES, 'UTF-8'); ?>
<body data-page="day" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-my-day-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=dashboard"><?= htmlspecialchars($t('page.home', 'Главная'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active"><?= htmlspecialchars($t('my_day.page_title', 'Мой день'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title"><?= htmlspecialchars($t('my_day.page_title', 'Мой день'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle"><?= htmlspecialchars($t('my_day.subtitle', 'Задачи, события и напоминания на сегодня.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="d-flex gap-2 flex-wrap"><button class="btn crm-btn-secondary" type="button" data-open-modal="calendarEventModal"><?= htmlspecialchars($t('my_day.create_event', 'Создать событие'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" type="button" data-open-modal="createTaskModal"><?= htmlspecialchars($t('my_day.create_task', 'Создать задачу'), ENT_QUOTES, 'UTF-8') ?></button></div></div>

<div class="crm-my-day-layout-v2">
  <section class="crm-my-day-primary">
    <div id="myDayAiCard" class="crm-card crm-section-card crm-my-day-ai-card" data-requires-ai-use="1" data-ai-state="idle">
      <div class="crm-section-head">
        <div>
          <h2 class="h6 mb-0"><?= htmlspecialchars($t('my_day.ai_title', 'AI-план дня'), ENT_QUOTES, 'UTF-8') ?></h2>
          <div class="crm-section-note"><?= htmlspecialchars($t('my_day.ai_note', 'Предложение порядка задач и временных слотов без авто-применения.'), ENT_QUOTES, 'UTF-8') ?></div>
          <div id="myDayAiState" class="small text-muted mt-1"><?= htmlspecialchars($t('my_day.ai_state_idle', 'Состояние: idle'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <button id="myDayAiGenerateBtn" class="btn btn-sm btn-light crm-btn-compact" type="button"><?= htmlspecialchars($t('my_day.ai_btn_generate', 'AI-план'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div id="myDayAiPlanSummary" class="crm-empty-state">
        <strong><?= htmlspecialchars($t('my_day.ai_suggest_generate', 'План пока не сформирован'), ENT_QUOTES, 'UTF-8') ?></strong>
        <p class="mb-0"><?= htmlspecialchars($t('my_day.ai_suggest_note', 'Нажмите кнопку, чтобы получить AI-предложение на день.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div id="myDayAiPlanTasks" class="mt-2"></div>
      <div class="d-flex gap-2 mt-2 flex-wrap crm-ai-actions">
        <button id="myDayAiApplySlotsBtn" class="btn btn-sm btn-outline-secondary crm-btn-compact d-none" type="button" disabled><?= htmlspecialchars($t('my_day.ai_btn_apply', 'Применить выбранные слоты'), ENT_QUOTES, 'UTF-8') ?></button>
        <button id="myDayAiPreviewBtn" class="btn btn-sm btn-light crm-btn-compact d-none" type="button" disabled><?= htmlspecialchars($t('my_day.ai_btn_preview', 'Предпросмотр'), ENT_QUOTES, 'UTF-8') ?></button>
        <button id="myDayAiDismissBtn" class="btn btn-sm crm-btn-muted crm-btn-compact d-none" type="button" disabled><?= htmlspecialchars($t('my_day.ai_btn_dismiss', 'Отклонить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </section>

  <aside class="crm-my-day-aside">
    <div class="crm-card crm-section-card"><div class="crm-section-head"><div><h2 class="h6 mb-0"><?= htmlspecialchars($t('my_day.summary_title', 'Итоги дня'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note"><?= htmlspecialchars($t('my_day.summary_note', 'Прогресс по задачам и событиям текущего дня.'), ENT_QUOTES, 'UTF-8') ?></div></div></div><div id="myDayFocusSummary"><div class="crm-empty-state"><strong><?= htmlspecialchars($t('my_day.summary_loading', 'Собираем оперативные показатели рабочего дня.'), ENT_QUOTES, 'UTF-8') ?></strong></div></div></div>
    <div class="crm-card crm-section-card"><div class="crm-section-head"><div><h2 class="h6 mb-0"><?= htmlspecialchars($t('my_day.events_title', 'События дня'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note"><?= htmlspecialchars($t('my_day.events_note', 'Ближайшие события из календаря.'), ENT_QUOTES, 'UTF-8') ?></div></div></div><div id="myDayMeetingsList"><div class="crm-metric-tile"><?= htmlspecialchars($t('my_day.events_loading', 'Загрузка событий...'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
    <div class="crm-card crm-section-card"><div class="crm-section-head"><div><h2 class="h6 mb-0"><?= htmlspecialchars($t('my_day.reminders_title', 'Личные напоминания'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note"><?= htmlspecialchars($t('my_day.reminders_note', 'Ваши личные сигналы и договоренности на сегодня.'), ENT_QUOTES, 'UTF-8') ?></div></div></div><div class="crm-timeline" id="myDayRemindersList"><div class="crm-timeline-item"><?= htmlspecialchars($t('my_day.reminders_loading', 'Загрузка напоминаний...'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
  </aside>
</div>

<div class="crm-my-day-stack">
  <div class="crm-card crm-section-card crm-my-day-today-section"><div class="crm-section-head"><div><h2 class="h6 mb-0"><?= htmlspecialchars($t('my_day.today_title', 'Задачи на сегодня'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note"><?= htmlspecialchars($t('my_day.today_note', 'Ключевые задачи, требующие внимания в течение дня.'), ENT_QUOTES, 'UTF-8') ?></div></div></div><div class="table-responsive crm-table-wrap"><table class="table table-hover align-middle mb-0 crm-table" data-my-day-table><thead><tr><th style="min-width:240px"><?= htmlspecialchars($t('my_day.th_task', 'Задача'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:120px"><?= htmlspecialchars($t('my_day.th_date', 'Срок'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:120px"><?= htmlspecialchars($t('my_day.th_priority', 'Приоритет'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:130px"><?= htmlspecialchars($t('my_day.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:200px"><?= htmlspecialchars($t('my_day.th_action', 'Действие'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="myDayTasksTableBody"><tr><td colspan="5" class="text-muted"><?= htmlspecialchars($t('my_day.today_loading', 'Загрузка задач дня...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div></div>

  <div class="crm-card crm-section-card crm-overdue-section"><div class="crm-section-head"><div><h2 class="h6 mb-0"><?= htmlspecialchars($t('my_day.overdue_title', 'Просроченные задачи'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note"><?= htmlspecialchars($t('my_day.overdue_note', 'Задачи с истекшим сроком, требующие внимания.'), ENT_QUOTES, 'UTF-8') ?></div></div></div><div class="table-responsive crm-table-wrap crm-overdue-table-wrap"><table class="table table-hover align-middle mb-0 crm-table" data-my-day-table><thead><tr><th style="min-width:240px"><?= htmlspecialchars($t('my_day.th_task', 'Задача'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:120px"><?= htmlspecialchars($t('my_day.th_date', 'Срок'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:120px"><?= htmlspecialchars($t('my_day.th_priority', 'Приоритет'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:130px"><?= htmlspecialchars($t('my_day.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:200px"><?= htmlspecialchars($t('my_day.th_action', 'Действие'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="myDayOverdueTableBody"><tr><td colspan="5" class="text-muted"><?= htmlspecialchars($t('my_day.overdue_checking', 'Проверка просроченных задач...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div></div>
</div>

</main></div></div>
