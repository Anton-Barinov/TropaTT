<?php declare(strict_types=1); ?>
<?php $title = $t('my_earnings.title', 'TropaTT — Моё вознаграждение'); ?>
<body data-page="my-earnings" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-my-earnings-page">
  <div class="crm-page-head">
    <div>
      <ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=dashboard" data-i18n="page.home"><?= htmlspecialchars($t('page.home', 'Главная'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="my_earnings.page_title"><?= htmlspecialchars($t('my_earnings.page_title', 'Моё вознаграждение'), ENT_QUOTES, 'UTF-8') ?></li></ol>
      <h1 class="crm-page-title" data-i18n="my_earnings.page_title"><?= htmlspecialchars($t('my_earnings.page_title', 'Моё вознаграждение'), ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="crm-subtitle" data-i18n="my_earnings.subtitle"><?= htmlspecialchars($t('my_earnings.subtitle', 'Начисленная сумма по вашему договору за выбранный период.'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </div>

  <div class="crm-toolbar-surface d-flex flex-wrap gap-2 align-items-center mb-3">
    <input id="myEarningsFrom" class="form-control crm-field-w-180" type="date" data-i18n-aria-label="my_earnings.from_aria" aria-label="<?= htmlspecialchars($t('my_earnings.from_aria', 'С даты'), ENT_QUOTES, 'UTF-8') ?>">
    <input id="myEarningsTo" class="form-control crm-field-w-180" type="date" data-i18n-aria-label="my_earnings.to_aria" aria-label="<?= htmlspecialchars($t('my_earnings.to_aria', 'По дату'), ENT_QUOTES, 'UTF-8') ?>">
    <button class="btn crm-btn-secondary crm-btn-compact" type="button" id="myEarningsPrevMonthBtn" data-i18n="my_earnings.btn_prev_month"><?= htmlspecialchars($t('my_earnings.btn_prev_month', 'Прошлый месяц'), ENT_QUOTES, 'UTF-8') ?></button>
    <button class="btn crm-btn-secondary crm-btn-compact" type="button" id="myEarningsQuarterBtn" data-i18n="my_earnings.btn_quarter"><?= htmlspecialchars($t('my_earnings.btn_quarter', 'За квартал'), ENT_QUOTES, 'UTF-8') ?></button>
    <button class="btn crm-btn-primary crm-btn-compact" type="button" id="myEarningsApplyBtn" data-i18n="page.apply"><?= htmlspecialchars($t('page.apply', 'Применить'), ENT_QUOTES, 'UTF-8') ?></button>
    <span id="myEarningsPeriodBadge" class="crm-badge crm-badge-muted" hidden data-i18n="my_earnings.period_closed"><?= htmlspecialchars($t('my_earnings.period_closed', 'Период закрыт'), ENT_QUOTES, 'UTF-8') ?></span>
  </div>

  <div class="row g-3 mb-3" id="myEarningsKpis">
    <div class="col-md-4"><div class="crm-card crm-kpi-card crm-kpi-primary"><small class="text-muted" data-i18n="my_earnings.kpi_accrued"><?= htmlspecialchars($t('my_earnings.kpi_accrued', 'Начислено за период'), ENT_QUOTES, 'UTF-8') ?></small><h2 class="h3 mb-0" id="myEarningsAccrued">—</h2></div></div>
    <div class="col-md-4"><div class="crm-card crm-kpi-card"><small class="text-muted" data-i18n="my_earnings.kpi_hours"><?= htmlspecialchars($t('my_earnings.kpi_hours', 'Часы (записано)'), ENT_QUOTES, 'UTF-8') ?></small><h2 class="h3 mb-0" id="myEarningsHours">0</h2></div></div>
    <div class="col-md-4"><div class="crm-card crm-kpi-card"><small class="text-muted" data-i18n="my_earnings.kpi_rate"><?= htmlspecialchars($t('my_earnings.kpi_rate', 'Ставка по договору'), ENT_QUOTES, 'UTF-8') ?></small><h2 class="h3 mb-0" id="myEarningsRate">—</h2></div></div>
  </div>

  <div class="crm-card crm-section-card p-0 table-responsive">
    <div class="crm-section-head px-3 pt-3"><div><h2 class="h6 mb-0" data-i18n="my_earnings.breakdown_title"><?= htmlspecialchars($t('my_earnings.breakdown_title', 'Разбивка по проектам и задачам'), ENT_QUOTES, 'UTF-8') ?></h2></div></div>
    <table class="table table-hover align-middle mb-0 crm-table">
      <thead><tr><th data-i18n="my_earnings.th_day"><?= htmlspecialchars($t('my_earnings.th_day', 'Дата'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="my_earnings.th_project"><?= htmlspecialchars($t('my_earnings.th_project', 'Проект / задача'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="my_earnings.th_hours"><?= htmlspecialchars($t('my_earnings.th_hours', 'Часы'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="my_earnings.th_amount"><?= htmlspecialchars($t('my_earnings.th_amount', 'Начислено'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
      <tbody id="myEarningsBody"><tr><td colspan="4" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка данных...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
    </table>
  </div>

  <div class="crm-empty-state" id="myEarningsEmpty" hidden>
    <strong data-i18n="my_earnings.rate_not_set_title"><?= htmlspecialchars($t('my_earnings.rate_not_set_title', 'Ставка вознаграждения не задана'), ENT_QUOTES, 'UTF-8') ?></strong>
    <p class="mb-0" data-i18n="my_earnings.rate_not_set_hint"><?= htmlspecialchars($t('my_earnings.rate_not_set_hint', 'Обратитесь к менеджеру, чтобы задать ставку по вашему договору.'), ENT_QUOTES, 'UTF-8') ?></p>
  </div>
</main></div></div>
