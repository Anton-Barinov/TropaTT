<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Workflow Rules'; ?>
<body data-page="admin-workflow" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title">Workflow Rules</h1><p class="crm-subtitle">Правила автоматизации: создание, редактирование, тестирование и просмотр логов выполнения.</p></div><div class="d-flex gap-2"><button id="adminWorkflowRefreshBtn" class="btn crm-btn-secondary" type="button">Обновить</button><button id="adminWorkflowCreateBtn" class="btn crm-btn-primary" type="button">Создать правило</button></div></div>

<div class="alert alert-info mb-3" role="alert">
  Workflow rules автоматизируют действия при изменении задач, проектов и других сущностей. Правила выполняются асинхронно.
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Правила</h2><div class="crm-section-note">Список всех правил автоматизации.</div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th>Название</th><th>Сущность</th><th>Событие</th><th>Действие</th><th>Статус</th><th></th></tr></thead><tbody id="adminWorkflowRulesBody"><tr><td colspan="6" class="text-muted">Загрузка...</td></tr></tbody></table>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Логи выполнения</h2><div class="crm-section-note">Последние выполнения правил.</div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th>Правило</th><th>Сущность</th><th>Результат</th><th>Дата</th></tr></thead><tbody id="adminWorkflowLogsBody"><tr><td colspan="4" class="text-muted">Загрузка...</td></tr></tbody></table>
    </div>
  </div>
</div>

</main></div></div>
