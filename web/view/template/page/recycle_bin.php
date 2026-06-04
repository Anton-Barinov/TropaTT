<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Корзина'; ?>
<body data-page="recycle-bin" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title">Корзина</h1><p class="crm-subtitle">Удалённые элементы: восстановление или окончательное удаление.</p></div><div class="d-flex gap-2"><button id="recycleBinRefreshBtn" class="btn crm-btn-secondary" type="button">Обновить</button></div></div>

<div class="alert alert-warning mb-3" role="alert">
  Элементы в корзине можно восстановить или удалить навсегда. Окончательное удаление нельзя отменить.
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Удалённые элементы</h2><div class="crm-section-note">Список элементов, ожидающих удаления.</div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th>Название</th><th>Тип</th><th>Удалён</th><th>Удалил</th><th></th></tr></thead><tbody id="recycleBinBody"><tr><td colspan="5" class="text-muted">Загрузка...</td></tr></tbody></table>
    </div>
  </div>
</div>

</main></div></div>
