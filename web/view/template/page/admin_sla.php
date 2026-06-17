<?php declare(strict_types=1); ?>
<?php $title = $t('admin_sla.title', 'TropaTT — SLA Policies'); ?>
<body data-page="admin-sla" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="admin_sla.page_title"><?= htmlspecialchars($t('admin_sla.page_title', 'SLA Policies'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_sla.subtitle"><?= htmlspecialchars($t('admin_sla.subtitle', 'Политики уровня обслуживания: создание, редактирование и отчёт соответствия.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="d-flex gap-2"><button id="adminSlaRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="page.refresh"><?= htmlspecialchars($t('page.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button><button id="adminSlaCreateBtn" class="btn crm-btn-primary" type="button" data-i18n="admin_sla.btn_create_policy"><?= htmlspecialchars($t('admin_sla.btn_create_policy', 'Создать политику'), ENT_QUOTES, 'UTF-8') ?></button><button id="adminSlaReportBtn" class="btn crm-btn-secondary" type="button" data-i18n="admin_sla.btn_report"><?= htmlspecialchars($t('admin_sla.btn_report', 'Отчёт'), ENT_QUOTES, 'UTF-8') ?></button></div></div>

<div class="alert alert-info mb-3" role="alert" data-i18n="admin_sla.alert_info">
  <?= htmlspecialchars($t('admin_sla.alert_info', 'SLA policies определяют целевые показатели времени реакции и разрешения для задач. Отчёт соответствия показывает, сколько задач уложилось в SLA.'), ENT_QUOTES, 'UTF-8') ?>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_sla.card_policies_title"><?= htmlspecialchars($t('admin_sla.card_policies_title', 'Политики SLA'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_sla.card_policies_note"><?= htmlspecialchars($t('admin_sla.card_policies_note', 'Список всех политик уровня обслуживания.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th data-i18n="admin_sla.th_name"><?= htmlspecialchars($t('admin_sla.th_name', 'Название'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_sla.th_priority"><?= htmlspecialchars($t('admin_sla.th_priority', 'Приоритет'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_sla.th_response_hours"><?= htmlspecialchars($t('admin_sla.th_response_hours', 'Реакция (ч)'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_sla.th_resolution_hours"><?= htmlspecialchars($t('admin_sla.th_resolution_hours', 'Разрешение (ч)'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_sla.th_status"><?= htmlspecialchars($t('admin_sla.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th></th></tr></thead><tbody id="adminSlaPoliciesBody"><tr><td colspan="6" class="text-muted" data-i18n="admin_sla.loading"><?= htmlspecialchars($t('admin_sla.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_sla.card_report_title"><?= htmlspecialchars($t('admin_sla.card_report_title', 'Отчёт соответствия'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_sla.card_report_note"><?= htmlspecialchars($t('admin_sla.card_report_note', 'Статистика соблюдения SLA за последние 30 дней.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div id="adminSlaReportState" class="text-muted" data-i18n="admin_sla.report_hint"><?= htmlspecialchars($t('admin_sla.report_hint', 'Нажмите "Отчёт" для загрузки данных.'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="row g-3 mt-2" id="adminSlaReportMetrics" style="display:none;">
        <div class="col-md-3"><div class="crm-metric-tile"><small class="text-muted" data-i18n="admin_sla.metric_total_tasks"><?= htmlspecialchars($t('admin_sla.metric_total_tasks', 'Всего задач'), ENT_QUOTES, 'UTF-8') ?></small><div id="slaReportTotal">—</div></div></div>
        <div class="col-md-3"><div class="crm-metric-tile"><small class="text-muted" data-i18n="admin_sla.metric_compliant"><?= htmlspecialchars($t('admin_sla.metric_compliant', 'В SLA'), ENT_QUOTES, 'UTF-8') ?></small><div id="slaReportCompliant">—</div></div></div>
        <div class="col-md-3"><div class="crm-metric-tile"><small class="text-muted" data-i18n="admin_sla.metric_breached"><?= htmlspecialchars($t('admin_sla.metric_breached', 'Нарушено'), ENT_QUOTES, 'UTF-8') ?></small><div id="slaReportBreached">—</div></div></div>
        <div class="col-md-3"><div class="crm-metric-tile"><small class="text-muted" data-i18n="admin_sla.metric_rate"><?= htmlspecialchars($t('admin_sla.metric_rate', '% соответствия'), ENT_QUOTES, 'UTF-8') ?></small><div id="slaReportRate">—</div></div></div>
      </div>
    </div>
  </div>
</div>

</main></div></div>
