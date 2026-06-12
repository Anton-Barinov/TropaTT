<?php declare(strict_types=1); ?>
<?php $title = $t('admin_priorities.title', 'TropaTT — Приоритеты'); ?>
<body data-page="admin-priorities" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="admin_priorities.page_title"><?= htmlspecialchars($t('admin_priorities.page_title', 'Приоритеты'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_priorities.subtitle"><?= htmlspecialchars($t('admin_priorities.subtitle', 'Управление приоритетами задач и других сущностей.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="d-flex gap-2"><button id="adminPrioritiesRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="admin_priorities.refresh_btn"><?= htmlspecialchars($t('admin_priorities.refresh_btn', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button><button id="adminPrioritiesCreateBtn" class="btn crm-btn-primary" type="button" data-i18n="admin_priorities.create_btn"><?= htmlspecialchars($t('admin_priorities.create_btn', 'Создать приоритет'), ENT_QUOTES, 'UTF-8') ?></button></div></div>

<div class="alert alert-info mb-3" role="alert" data-i18n="admin_priorities.alert_info">
  <?= htmlspecialchars($t('admin_priorities.alert_info', 'Приоритеты используются для классификации задач по важности. Можно создавать собственные приоритеты с цветами и порядком сортировки.'), ENT_QUOTES, 'UTF-8') ?>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_priorities.section_list_title"><?= htmlspecialchars($t('admin_priorities.section_list_title', 'Приоритеты'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_priorities.section_list_note"><?= htmlspecialchars($t('admin_priorities.section_list_note', 'Список всех приоритетов.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th data-i18n="admin_priorities.th_title"><?= htmlspecialchars($t('admin_priorities.th_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_priorities.th_code"><?= htmlspecialchars($t('admin_priorities.th_code', 'Код'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_priorities.th_color"><?= htmlspecialchars($t('admin_priorities.th_color', 'Цвет'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_priorities.th_order"><?= htmlspecialchars($t('admin_priorities.th_order', 'Порядок'), ENT_QUOTES, 'UTF-8') ?></th><th></th></tr></thead><tbody id="adminPrioritiesBody"><tr><td colspan="5" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table>
    </div>
  </div>
</div>

</main></div></div>
