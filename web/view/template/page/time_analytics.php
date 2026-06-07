<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Учет времени'; ?>
<body data-page="time-analytics" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-time-analytics-page"><div class="crm-page-head"><div><h1 class="crm-page-title">Учет времени</h1><p class="crm-subtitle">Статистика трудозатрат и заработка по пользователям.</p></div></div>

<ul class="nav nav-tabs mb-3" id="timeAnalyticsTabs" role="tablist">
  <li class="nav-item"><button class="nav-link active" id="timeTabTime" data-bs-toggle="tab" data-bs-target="#timeAnalyticsTime" type="button">Затраченное время</button></li>
  <li class="nav-item"><button class="nav-link" id="timeTabEarnings" data-bs-toggle="tab" data-bs-target="#timeAnalyticsEarnings" type="button">Заработок</button></li>
  <li class="nav-item"><button class="nav-link" id="timeTabMatrix" data-bs-toggle="tab" data-bs-target="#timeAnalyticsMatrix" type="button">Общая сводка</button></li>
</ul>

<div class="tab-content">
  <div id="timeAnalyticsTime" class="tab-pane fade show active">
    <div class="crm-toolbar-surface d-flex flex-wrap gap-2 align-items-center mb-3">
      <input id="timeAnalyticsFrom" class="form-control crm-field-w-200" type="date" placeholder="От">
      <input id="timeAnalyticsTo" class="form-control crm-field-w-200" type="date" placeholder="До">
      <select id="timeAnalyticsUserFilter" class="form-select crm-field-w-220" aria-label="Пользователь"><option value="">Все пользователи</option></select>
      <button class="btn crm-btn-primary crm-btn-compact" type="button" id="timeAnalyticsApplyBtn">Применить</button>
    </div>
    <div class="crm-card crm-section-card p-0 table-responsive"><table class="table table-hover align-middle mb-0 crm-table"><thead><tr><th>Дата</th><th>Пользователь</th><th>Часов</th></tr></thead><tbody id="timeAnalyticsTimeBody"><tr><td colspan="3" class="text-muted">Загрузка данных...</td></tr></tbody></table></div>
  </div>

  <div id="timeAnalyticsEarnings" class="tab-pane fade">
    <div class="crm-toolbar-surface d-flex flex-wrap gap-2 align-items-center mb-3">
      <input id="timeAnalyticsEarningsFrom" class="form-control crm-field-w-200" type="date" placeholder="От">
      <input id="timeAnalyticsEarningsTo" class="form-control crm-field-w-200" type="date" placeholder="До">
      <select id="timeAnalyticsEarningsUserFilter" class="form-select crm-field-w-220" aria-label="Пользователь"><option value="">Все пользователи</option></select>
      <button class="btn crm-btn-primary crm-btn-compact" type="button" id="timeAnalyticsEarningsApplyBtn">Применить</button>
    </div>
    <div class="crm-card crm-section-card p-0 table-responsive"><table class="table table-hover align-middle mb-0 crm-table"><thead><tr><th>Дата</th><th>Пользователь</th><th>Часов</th><th>Ставка (себестоимость)</th><th>Ставка (продажа)</th><th>Себестоимость</th><th>Продажа</th></tr></thead><tbody id="timeAnalyticsEarningsBody"><tr><td colspan="7" class="text-muted">Загрузка данных...</td></tr></tbody></table></div>
  </div>

  <div id="timeAnalyticsMatrix" class="tab-pane fade">
    <div class="crm-toolbar-surface d-flex flex-wrap gap-2 align-items-center mb-3">
      <input id="timeAnalyticsMatrixFrom" class="form-control crm-field-w-200" type="date" placeholder="От">
      <input id="timeAnalyticsMatrixTo" class="form-control crm-field-w-200" type="date" placeholder="До">
      <select id="timeAnalyticsMatrixUserFilter" class="form-select crm-field-w-220" aria-label="Пользователь"><option value="">Все пользователи</option></select>
      <button class="btn crm-btn-primary crm-btn-compact" type="button" id="timeAnalyticsMatrixApplyBtn">Применить</button>
    </div>
    <div class="crm-card crm-section-card p-0 table-responsive"><div id="timeAnalyticsMatrixWrap"><p class="text-muted p-3 mb-0">Выберите период и нажмите «Применить».</p></div></div>
  </div>
</div>

</main></div></div>

<div class="modal fade" id="timeDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">Детализация времени</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body" id="timeDetailModalBody">
        <p class="text-muted">Загрузка...</p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Закрыть</button>
      </div>
    </div>
  </div>
</div>
