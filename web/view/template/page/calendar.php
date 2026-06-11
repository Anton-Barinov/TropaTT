<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Календарь'; ?>
<body data-page="calendar" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-calendar-page"><?php crm_page_head([
  ['label' => 'Главная', 'href' => 'index.php?route=dashboard'],
  ['label' => 'Календарь', 'active' => true],
], 'Календарь', 'События, встречи и сроки команды в календарном виде.', '<div class="btn-group crm-calendar-view-switch" role="group" aria-label="Режим календаря" data-calendar-view-toggle><button class="btn crm-btn-secondary" type="button" data-calendar-view="day">День</button><button class="btn crm-btn-secondary" type="button" data-calendar-view="week">Неделя</button><button class="btn crm-btn-secondary" type="button" data-calendar-view="month">Месяц</button></div><button class="btn crm-btn-primary" type="button" data-open-modal="calendarEventModal">Создать событие</button>', '', 'data-calendar-subtitle'); ?>
<section class="crm-calendar-shell">
  <div class="crm-calendar-main">
    <div class="crm-calendar-panel">
      <div class="crm-calendar-panel-head">
        <div class="crm-calendar-period-head">
          <div class="crm-calendar-period-title">
            <div class="btn-group crm-calendar-nav-switch crm-calendar-local-nav" role="group" aria-label="Навигация календаря">
              <button class="btn crm-btn-secondary crm-calendar-nav-icon" type="button" data-calendar-nav="prev" aria-label="Предыдущий период" title="Предыдущий период"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-chevron-left"></i></span></button>
              <button class="btn crm-btn-secondary crm-calendar-current-period" type="button" data-calendar-nav="today" data-calendar-title aria-label="Перейти к текущему периоду" title="Перейти к текущему периоду">Календарь</button>
              <button class="btn crm-btn-secondary crm-calendar-nav-icon" type="button" data-calendar-nav="next" aria-label="Следующий период" title="Следующий период"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span></button>
            </div>
          </div>
        </div>
        <div class="crm-calendar-summary" data-calendar-summary><span class="crm-chip">Загрузка...</span></div>
      </div>
      <div data-calendar-surface><div class="crm-calendar-loading">Загрузка календаря...</div></div>
    </div>
  </div>
  <aside class="crm-calendar-side">
    <div class="crm-calendar-side-card">
      <div class="crm-section-head">
        <div><h2 class="h6 mb-0">События периода</h2><div class="crm-section-note">Ближайшие события выбранного окна.</div></div>
      </div>
      <div class="crm-calendar-agenda" data-calendar-feed><div class="crm-calendar-agenda-empty">Загрузка событий...</div></div>
      <button class="btn crm-btn-secondary w-100" type="button" data-open-modal="calendarEventModal">Создать событие</button>
    </div>
    <div class="crm-calendar-side-card mt-3" id="calendarAiDayPlanCard" data-calendar-ai-day-plan data-ai-state="idle">
      <div class="crm-section-head">
        <div><h2 class="h6 mb-0">AI-блоки дня</h2><div class="crm-section-note">Предпросмотр дневного плана с ручным подтверждением перед применением.</div></div>
      </div>
      <div class="crm-empty-state mb-2" data-calendar-ai-plan-summary>
        <strong>План не сформирован</strong>
        <p class="mb-0">Нажмите «Сформировать предпросмотр», чтобы получить AI-предложение.</p>
      </div>
      <div class="crm-calendar-agenda" data-calendar-ai-plan-list>
        <div class="crm-calendar-agenda-empty">Слоты будут показаны после генерации.</div>
      </div>
      <div class="d-grid gap-2 mt-2">
        <button class="btn crm-btn-secondary" type="button" data-calendar-ai-plan-generate-btn>Сформировать предпросмотр</button>
        <button class="btn crm-btn-primary" type="button" data-calendar-ai-plan-apply-btn disabled>Применить выбранные слоты</button>
      </div>
      <a class="btn crm-btn-muted w-100 mt-2" href="index.php?route=my-day">Открыть «Мой день»</a>
    </div>
  </aside>
</section>
</main></div></div>
