<?php declare(strict_types=1); ?>
<?php $title = $t('calendar.title', 'TropaTT — Календарь'); ?>
<body data-page="calendar" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-calendar-page"><?php crm_page_head([
  ['label' => htmlspecialchars($t('page.home', 'Главная'), ENT_QUOTES, 'UTF-8'), 'href' => 'index.php?route=dashboard'],
  ['label' => htmlspecialchars($t('calendar.page_title', 'Календарь'), ENT_QUOTES, 'UTF-8'), 'active' => true],
], htmlspecialchars($t('calendar.page_title', 'Календарь'), ENT_QUOTES, 'UTF-8'), htmlspecialchars($t('calendar.subtitle', 'События, встречи и сроки команды в календарном виде.'), ENT_QUOTES, 'UTF-8'), '<div class="btn-group crm-calendar-view-switch" role="group" aria-label="' . htmlspecialchars($t('calendar.view_switch_aria', 'Режим календаря'), ENT_QUOTES, 'UTF-8') . '" data-i18n-aria-label="calendar.view_switch_aria" data-calendar-view-toggle><button class="btn crm-btn-secondary" type="button" data-calendar-view="day" data-i18n="calendar.btn_day">' . htmlspecialchars($t('calendar.btn_day', 'День'), ENT_QUOTES, 'UTF-8') . '</button><button class="btn crm-btn-secondary" type="button" data-calendar-view="week" data-i18n="calendar.btn_week">' . htmlspecialchars($t('calendar.btn_week', 'Неделя'), ENT_QUOTES, 'UTF-8') . '</button><button class="btn crm-btn-secondary" type="button" data-calendar-view="month" data-i18n="calendar.btn_month">' . htmlspecialchars($t('calendar.btn_month', 'Месяц'), ENT_QUOTES, 'UTF-8') . '</button></div><button class="btn crm-btn-primary" type="button" data-open-modal="calendarEventModal" data-i18n="calendar.btn_create_event">' . htmlspecialchars($t('calendar.btn_create_event', 'Создать событие'), ENT_QUOTES, 'UTF-8') . '</button>', '', 'data-calendar-subtitle'); ?>
<section class="crm-calendar-shell">
  <div class="crm-calendar-main">
    <div class="crm-calendar-panel">
      <div class="crm-calendar-panel-head">
        <div class="crm-calendar-period-head">
          <div class="crm-calendar-period-title">
            <div class="btn-group crm-calendar-nav-switch crm-calendar-local-nav" role="group" aria-label="<?= htmlspecialchars($t('calendar.nav_aria', 'Навигация календаря'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="calendar.nav_aria">
              <button class="btn crm-btn-secondary crm-calendar-nav-icon" type="button" data-calendar-nav="prev" aria-label="<?= htmlspecialchars($t('calendar.btn_prev_aria', 'Предыдущий период'), ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($t('calendar.btn_prev_title', 'Предыдущий период'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="calendar.btn_prev_aria" data-i18n-title="calendar.btn_prev_title"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></span></button>
              <button class="btn crm-btn-secondary crm-calendar-current-period" type="button" data-calendar-nav="today" data-calendar-title aria-label="<?= htmlspecialchars($t('calendar.btn_today_aria', 'Перейти к текущему периоду'), ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($t('calendar.btn_today_title', 'Перейти к текущему периоду'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="calendar.btn_today_aria" data-i18n-title="calendar.btn_today_title" data-i18n="calendar.btn_today"><?= htmlspecialchars($t('calendar.btn_today', 'Календарь'), ENT_QUOTES, 'UTF-8') ?></button>
              <button class="btn crm-btn-secondary crm-calendar-nav-icon" type="button" data-calendar-nav="next" aria-label="<?= htmlspecialchars($t('calendar.btn_next_aria', 'Следующий период'), ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($t('calendar.btn_next_title', 'Следующий период'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="calendar.btn_next_aria" data-i18n-title="calendar.btn_next_title"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></span></button>
            </div>
          </div>
        </div>
        <div class="crm-calendar-summary" data-calendar-summary><span class="crm-chip" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></span></div>
      </div>
      <div data-calendar-surface><div class="crm-calendar-loading" data-i18n="calendar.loading_calendar"><?= htmlspecialchars($t('calendar.loading_calendar', 'Загрузка календаря...'), ENT_QUOTES, 'UTF-8') ?></div></div>
    </div>
  </div>
  <aside class="crm-calendar-side">
    <div class="crm-calendar-side-card">
      <div class="crm-section-head">
        <div><h2 class="h6 mb-0" data-i18n="calendar.section_events"><?= htmlspecialchars($t('calendar.section_events', 'События периода'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="calendar.note_events"><?= htmlspecialchars($t('calendar.note_events', 'Ближайшие события выбранного окна.'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </div>
      <div class="crm-calendar-agenda" data-calendar-feed><div class="crm-calendar-agenda-empty" data-i18n="calendar.loading_events"><?= htmlspecialchars($t('calendar.loading_events', 'Загрузка событий...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      <button class="btn crm-btn-secondary w-100" type="button" data-open-modal="calendarEventModal" data-i18n="calendar.btn_create_event"><?= htmlspecialchars($t('calendar.btn_create_event', 'Создать событие'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div class="crm-calendar-side-card mt-3" id="calendarAiDayPlanCard" data-calendar-ai-day-plan data-ai-state="idle">
      <div class="crm-section-head">
        <div><h2 class="h6 mb-0" data-i18n="calendar.section_ai_plan"><?= htmlspecialchars($t('calendar.section_ai_plan', 'AI-блоки дня'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="calendar.note_ai_plan"><?= htmlspecialchars($t('calendar.note_ai_plan', 'Предпросмотр дневного плана с ручным подтверждением перед применением.'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </div>
      <div class="crm-empty-state mb-2" data-calendar-ai-plan-summary>
        <strong data-i18n="calendar.ai_plan_empty_title"><?= htmlspecialchars($t('calendar.ai_plan_empty_title', 'План не сформирован'), ENT_QUOTES, 'UTF-8') ?></strong>
        <p class="mb-0" data-i18n="calendar.ai_plan_empty_text"><?= htmlspecialchars($t('calendar.ai_plan_empty_text', 'Нажмите «Сформировать предпросмотр», чтобы получить AI-предложение.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div class="crm-calendar-agenda" data-calendar-ai-plan-list>
        <div class="crm-calendar-agenda-empty" data-i18n="calendar.ai_plan_slots_hint"><?= htmlspecialchars($t('calendar.ai_plan_slots_hint', 'Слоты будут показаны после генерации.'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="d-grid gap-2 mt-2">
        <button class="btn crm-btn-secondary" type="button" data-calendar-ai-plan-generate-btn data-i18n="calendar.btn_ai_generate"><?= htmlspecialchars($t('calendar.btn_ai_generate', 'Сформировать предпросмотр'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn crm-btn-primary" type="button" data-calendar-ai-plan-apply-btn disabled data-i18n="calendar.btn_ai_apply"><?= htmlspecialchars($t('calendar.btn_ai_apply', 'Применить выбранные слоты'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <a class="btn crm-btn-muted w-100 mt-2" href="index.php?route=my-day" data-i18n="calendar.link_my_day"><?= htmlspecialchars($t('calendar.link_my_day', 'Открыть «Мой день»'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
  </aside>
</section>
<?= module_position('calendar.content.after', ['route' => $route ?? 'calendar']) ?>
</main></div></div>
