<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Производственный календарь'; ?>
<body data-page="admin-calendar" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title">Производственный календарь</h1><p class="crm-subtitle">Управление бизнес-календарями, праздниками и рабочими часами.</p></div><div class="d-flex gap-2"><button id="adminCalendarRefreshBtn" class="btn crm-btn-secondary" type="button">Обновить</button></div></div>

<div class="alert alert-info mb-3" role="alert">
  Бизнес-календари определяют рабочие дни, праздники и рабочие часы. Используются для расчета SLA и сроков задач.
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Бизнес-календари</h2><div class="crm-section-note">Список календарей.</div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th>Название</th><th></th></tr></thead><tbody id="adminCalendarsBody"><tr><td colspan="2" class="text-muted">Загрузка...</td></tr></tbody></table>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Праздники</h2><div class="crm-section-note">Нерабочие дни.</div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th>Дата</th><th>Название</th></tr></thead><tbody id="adminHolidaysBody"><tr><td colspan="2" class="text-muted">Загрузка...</td></tr></tbody></table>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Рабочие часы</h2><div class="crm-section-note">Расписание рабочих часов.</div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th>День</th><th>Часы</th></tr></thead><tbody id="adminWorkingHoursBody"><tr><td colspan="2" class="text-muted">Загрузка...</td></tr></tbody></table>
    </div>
  </div>
</div>

</main></div></div>
