<?php declare(strict_types=1); ?>
<?php $title = $t('recycle_bin.title', 'TropaTT — Корзина'); ?>
<body data-page="recycle-bin" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="recycle_bin.page_title"><?= htmlspecialchars($t('recycle_bin.page_title', 'Корзина'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="recycle_bin.subtitle"><?= htmlspecialchars($t('recycle_bin.subtitle', 'Удалённые элементы: восстановление или окончательное удаление.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="d-flex gap-2"><button id="recycleBinRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="recycle_bin.btn_refresh"><?= htmlspecialchars($t('recycle_bin.btn_refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button></div></div>

<div class="alert alert-warning mb-3" role="alert" data-i18n="recycle_bin.alert_text">
  <?= htmlspecialchars($t('recycle_bin.alert_text', 'Элементы в корзине можно восстановить или удалить навсегда. Окончательное удаление нельзя отменить.'), ENT_QUOTES, 'UTF-8') ?>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="recycle_bin.heading_deleted"><?= htmlspecialchars($t('recycle_bin.heading_deleted', 'Удалённые элементы'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="recycle_bin.list_note"><?= htmlspecialchars($t('recycle_bin.list_note', 'Список элементов, ожидающих удаления.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th data-i18n="recycle_bin.th_name"><?= htmlspecialchars($t('recycle_bin.th_name', 'Название'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="recycle_bin.th_type"><?= htmlspecialchars($t('recycle_bin.th_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="recycle_bin.th_deleted_at"><?= htmlspecialchars($t('recycle_bin.th_deleted_at', 'Удалён'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="recycle_bin.th_deleted_by"><?= htmlspecialchars($t('recycle_bin.th_deleted_by', 'Удалил'), ENT_QUOTES, 'UTF-8') ?></th><th></th></tr></thead><tbody id="recycleBinBody"><tr><td colspan="5" class="text-muted" data-i18n="recycle_bin.loading"><?= htmlspecialchars($t('recycle_bin.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table>
    </div>
  </div>
</div>

</main></div></div>
