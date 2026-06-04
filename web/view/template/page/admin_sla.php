<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — SLA Policies'; ?>
<body data-page="admin-sla" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title">SLA Policies</h1><p class="crm-subtitle">Политики уровня обслуживания: создание, редактирование и отчёт соответствия.</p></div><div class="d-flex gap-2"><button id="adminSlaRefreshBtn" class="btn crm-btn-secondary" type="button">Обновить</button><button id="adminSlaCreateBtn" class="btn crm-btn-primary" type="button">Создать политику</button><button id="adminSlaReportBtn" class="btn crm-btn-secondary" type="button">Отчёт</button></div></div>

<div class="alert alert-info mb-3" role="alert">
  SLA policies определяют целевые показатели времени реакции и разрешения для задач. Отчёт соответствия показывает, сколько задач уложилось в SLA.
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Политики SLA</h2><div class="crm-section-note">Список всех политик уровня обслуживания.</div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th>Название</th><th>Приоритет</th><th>Реакция (ч)</th><th>Разрешение (ч)</th><th>Статус</th><th></th></tr></thead><tbody id="adminSlaPoliciesBody"><tr><td colspan="6" class="text-muted">Загрузка...</td></tr></tbody></table>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Отчёт соответствия</h2><div class="crm-section-note">Статистика соблюдения SLA за последние 30 дней.</div></div></div>
      <div id="adminSlaReportState" class="text-muted">Нажмите "Отчёт" для загрузки данных.</div>
      <div class="row g-3 mt-2" id="adminSlaReportMetrics" style="display:none;">
        <div class="col-md-3"><div class="crm-metric-tile"><small class="text-muted">Всего задач</small><div id="slaReportTotal">—</div></div></div>
        <div class="col-md-3"><div class="crm-metric-tile"><small class="text-muted">В SLA</small><div id="slaReportCompliant">—</div></div></div>
        <div class="col-md-3"><div class="crm-metric-tile"><small class="text-muted">Нарушено</small><div id="slaReportBreached">—</div></div></div>
        <div class="col-md-3"><div class="crm-metric-tile"><small class="text-muted">% соответствия</small><div id="slaReportRate">—</div></div></div>
      </div>
    </div>
  </div>
</div>

</main></div></div>
