<?php declare(strict_types=1); ?>
<?php $title = $t('admin_custom_fields.title', 'TropaTT — Кастомные поля'); ?>
<body data-page="admin-custom-fields" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="admin_custom_fields.page_title"><?= htmlspecialchars($t('admin_custom_fields.page_title', 'Кастомные поля'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_custom_fields.subtitle"><?= htmlspecialchars($t('admin_custom_fields.subtitle', 'Управление пользовательскими полями для задач, проектов и других сущностей.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="d-flex gap-2"><button id="adminCustomFieldsRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="page.refresh"><?= htmlspecialchars($t('page.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button><button id="adminCustomFieldsCreateBtn" class="btn crm-btn-primary" type="button" data-i18n="admin_custom_fields.btn_create_field"><?= htmlspecialchars($t('admin_custom_fields.btn_create_field', 'Создать поле'), ENT_QUOTES, 'UTF-8') ?></button></div></div>

<div class="alert alert-info mb-3" role="alert" data-i18n="admin_custom_fields.alert_info">
  <?= htmlspecialchars($t('admin_custom_fields.alert_info', 'Кастомные поля позволяют расширять стандартные сущности дополнительными атрибутами. Поля могут быть привязаны к задачам, проектам, клиентам и другим типам сущностей.'), ENT_QUOTES, 'UTF-8') ?>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_custom_fields.card_title"><?= htmlspecialchars($t('admin_custom_fields.card_title', 'Пользовательские поля'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_custom_fields.card_note"><?= htmlspecialchars($t('admin_custom_fields.card_note', 'Список всех кастомных полей.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th data-i18n="admin_custom_fields.th_name"><?= htmlspecialchars($t('admin_custom_fields.th_name', 'Название'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_custom_fields.th_type"><?= htmlspecialchars($t('admin_custom_fields.th_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_custom_fields.th_entity"><?= htmlspecialchars($t('admin_custom_fields.th_entity', 'Сущность'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_custom_fields.th_required"><?= htmlspecialchars($t('admin_custom_fields.th_required', 'Обязательное'), ENT_QUOTES, 'UTF-8') ?></th><th></th></tr></thead><tbody id="adminCustomFieldsBody"><tr><td colspan="5" class="text-muted" data-i18n="admin_custom_fields.loading"><?= htmlspecialchars($t('admin_custom_fields.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table>
    </div>
  </div>
</div>

</main></div></div>
