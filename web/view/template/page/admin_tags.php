<?php declare(strict_types=1); ?>
<?php $title = $t('admin_tags.title', 'TropaTT — Теги'); ?>
<body data-page="admin-tags" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="admin_tags.page_title"><?= htmlspecialchars($t('admin_tags.page_title', 'Теги'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_tags.subtitle"><?= htmlspecialchars($t('admin_tags.subtitle', 'Управление глобальными тегами для задач и проектов.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="d-flex gap-2"><button id="adminTagsRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="admin_tags.refresh_btn"><?= htmlspecialchars($t('admin_tags.refresh_btn', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button></div></div>

<div class="row g-3">
  <div class="col-lg-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_tags.section_list_title"><?= htmlspecialchars($t('admin_tags.section_list_title', 'Список тегов'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_tags.section_list_note"><?= htmlspecialchars($t('admin_tags.section_list_note', 'Глобальные теги, доступные для всех задач и проектов.'), ENT_QUOTES, 'UTF-8') ?></div></div><div class="d-flex gap-2"><button id="createTagBtn" class="btn btn-sm crm-btn-primary" type="button" data-i18n="admin_tags.create_btn"><?= htmlspecialchars($t('admin_tags.create_btn', 'Создать'), ENT_QUOTES, 'UTF-8') ?></button></div></div>
      <div class="table-responsive"><table class="table table-sm crm-table mb-0"><thead><tr><th data-i18n="admin_tags.th_title"><?= htmlspecialchars($t('admin_tags.th_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_tags.th_color"><?= htmlspecialchars($t('admin_tags.th_color', 'Цвет'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_tags.th_description"><?= htmlspecialchars($t('admin_tags.th_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_tags.th_usage"><?= htmlspecialchars($t('admin_tags.th_usage', 'Использований'), ENT_QUOTES, 'UTF-8') ?></th><th></th></tr></thead><tbody id="adminTagsBody"><tr><td colspan="5" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
    </div>
  </div>
</div>

</main></div></div>

<div class="modal fade" id="createTagModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" data-i18n="admin_tags.modal_create_title"><?= htmlspecialchars($t('admin_tags.modal_create_title', 'Создать тег'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div>
    <form id="createTagForm"><div class="modal-body">
      <div class="mb-3"><label class="form-label" for="tagName" data-i18n="admin_tags.field_name"><?= htmlspecialchars($t('admin_tags.field_name', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" id="tagName" name="name" required maxlength="64"></div>
      <div class="mb-3"><label class="form-label" for="tagColor" data-i18n="admin_tags.field_color"><?= htmlspecialchars($t('admin_tags.field_color', 'Цвет'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" id="tagColor" name="color" type="color" value="#0f8f72"></div>
      <div class="mb-3"><label class="form-label" for="tagDescription" data-i18n="admin_tags.field_description"><?= htmlspecialchars($t('admin_tags.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" id="tagDescription" name="description" rows="2"></textarea></div>
    </div><div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button type="submit" class="btn crm-btn-primary" data-i18n="page.create"><?= htmlspecialchars($t('page.create', 'Создать'), ENT_QUOTES, 'UTF-8') ?></button></div></form>
  </div></div>
</div>

<div class="modal fade" id="editTagModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" data-i18n="admin_tags.modal_edit_title"><?= htmlspecialchars($t('admin_tags.modal_edit_title', 'Редактировать тег'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div>
    <form id="editTagForm"><div class="modal-body">
      <input type="hidden" id="editTagPublicId" name="public_id">
      <div class="mb-3"><label class="form-label" for="editTagName" data-i18n="admin_tags.field_name"><?= htmlspecialchars($t('admin_tags.field_name', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" id="editTagName" name="name" required maxlength="64"></div>
      <div class="mb-3"><label class="form-label" for="editTagColor" data-i18n="admin_tags.field_color"><?= htmlspecialchars($t('admin_tags.field_color', 'Цвет'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" id="editTagColor" name="color" type="color"></div>
      <div class="mb-3"><label class="form-label" for="editTagDescription" data-i18n="admin_tags.field_description"><?= htmlspecialchars($t('admin_tags.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" id="editTagDescription" name="description" rows="2"></textarea></div>
    </div><div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button type="submit" class="btn crm-btn-primary" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button></div></form>
  </div></div>
</div>
