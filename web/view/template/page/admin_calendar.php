<?php declare(strict_types=1); ?>
<?php $title = $t('admin_calendar.title', 'TropaTT — Производственный календарь'); ?>
<body data-page="admin-calendar" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="admin_calendar.page_title"><?= htmlspecialchars($t('admin_calendar.page_title', 'Производственный календарь'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_calendar.subtitle"><?= htmlspecialchars($t('admin_calendar.subtitle', 'Управление бизнес-календарями, праздниками и рабочими часами.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="d-flex gap-2"><button id="adminCalendarRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="admin_calendar.btn_refresh"><?= htmlspecialchars($t('admin_calendar.btn_refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button></div></div>

<div class="alert alert-info mb-3" role="alert" data-i18n="admin_calendar.alert_info">
  <?= htmlspecialchars($t('admin_calendar.alert_info', 'Бизнес-календари определяют рабочие дни, праздники и рабочие часы. Используются для расчета SLA и сроков задач.'), ENT_QUOTES, 'UTF-8') ?>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_calendar.card_calendars_title"><?= htmlspecialchars($t('admin_calendar.card_calendars_title', 'Бизнес-календари'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_calendar.card_calendars_note"><?= htmlspecialchars($t('admin_calendar.card_calendars_note', 'Список календарей.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th data-i18n="admin_calendar.th_name"><?= htmlspecialchars($t('admin_calendar.th_name', 'Название'), ENT_QUOTES, 'UTF-8') ?></th><th></th></tr></thead><tbody id="adminCalendarsBody"><tr><td colspan="2" class="text-muted" data-i18n="admin_calendar.loading"><?= htmlspecialchars($t('admin_calendar.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_calendar.card_holidays_title"><?= htmlspecialchars($t('admin_calendar.card_holidays_title', 'Праздники'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_calendar.card_holidays_note"><?= htmlspecialchars($t('admin_calendar.card_holidays_note', 'Нерабочие дни.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th data-i18n="admin_calendar.th_date"><?= htmlspecialchars($t('admin_calendar.th_date', 'Дата'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_calendar.th_holiday_name"><?= htmlspecialchars($t('admin_calendar.th_holiday_name', 'Название'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="adminHolidaysBody"><tr><td colspan="2" class="text-muted" data-i18n="admin_calendar.loading"><?= htmlspecialchars($t('admin_calendar.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_calendar.card_hours_title"><?= htmlspecialchars($t('admin_calendar.card_hours_title', 'Рабочие часы'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_calendar.card_hours_note"><?= htmlspecialchars($t('admin_calendar.card_hours_note', 'Расписание рабочих часов.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th data-i18n="admin_calendar.th_day"><?= htmlspecialchars($t('admin_calendar.th_day', 'День'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_calendar.th_hours"><?= htmlspecialchars($t('admin_calendar.th_hours', 'Часы'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="adminWorkingHoursBody"><tr><td colspan="2" class="text-muted" data-i18n="admin_calendar.loading"><?= htmlspecialchars($t('admin_calendar.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table>
    </div>
  </div>
</div>

</main></div></div>
