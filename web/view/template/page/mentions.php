<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Упоминания'; ?>
<body data-page="mentions" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title">Упоминания</h1><p class="crm-subtitle">Задачи и комментарии, где вы были упомянуты.</p></div><div class="d-flex gap-2"><button id="mentionsRefreshBtn" class="btn crm-btn-secondary" type="button">Обновить</button></div></div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Мои упоминания</h2><div class="crm-section-note">Список упоминаний.</div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th>Сущность</th><th>Контекст</th><th>Упомянул</th><th>Дата</th></tr></thead><tbody id="mentionsBody"><tr><td colspan="4" class="text-muted">Загрузка...</td></tr></tbody></table>
    </div>
  </div>
</div>

</main></div></div>
