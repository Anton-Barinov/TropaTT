<?php declare(strict_types=1); ?>
<?php $title = $t('admin_templates.title', 'TropaTT — Шаблоны'); ?>
<body data-page="admin-templates" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="admin_templates.page_title"><?= htmlspecialchars($t('admin_templates.page_title', 'Шаблоны'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_templates.subtitle"><?= htmlspecialchars($t('admin_templates.subtitle', 'Управление шаблонами задач и проектов.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="d-flex gap-2"><button id="adminTemplatesRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="page.refresh"><?= htmlspecialchars($t('page.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button></div></div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_templates.card_task_templates_title"><?= htmlspecialchars($t('admin_templates.card_task_templates_title', 'Шаблоны задач'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_templates.card_task_templates_note"><?= htmlspecialchars($t('admin_templates.card_task_templates_note', 'Переиспользуемые шаблоны задач.'), ENT_QUOTES, 'UTF-8') ?></div></div><div class="d-flex gap-2"><button id="createTaskTemplateBtn" class="btn btn-sm crm-btn-primary" type="button" data-i18n="page.create"><?= htmlspecialchars($t('page.create', 'Создать'), ENT_QUOTES, 'UTF-8') ?></button></div></div>
      <div class="table-responsive"><table class="table table-sm crm-table mb-0"><thead><tr><th data-i18n="admin_templates.th_name"><?= htmlspecialchars($t('admin_templates.th_name', 'Название'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_templates.th_description"><?= htmlspecialchars($t('admin_templates.th_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></th><th></th></tr></thead><tbody id="taskTemplatesBody"><tr><td colspan="3" class="text-muted" data-i18n="admin_templates.loading"><?= htmlspecialchars($t('admin_templates.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_templates.card_project_templates_title"><?= htmlspecialchars($t('admin_templates.card_project_templates_title', 'Шаблоны проектов'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_templates.card_project_templates_note"><?= htmlspecialchars($t('admin_templates.card_project_templates_note', 'Переиспользуемые шаблоны проектов.'), ENT_QUOTES, 'UTF-8') ?></div></div><div class="d-flex gap-2"><button id="createProjectTemplateBtn" class="btn btn-sm crm-btn-primary" type="button" data-i18n="page.create"><?= htmlspecialchars($t('page.create', 'Создать'), ENT_QUOTES, 'UTF-8') ?></button></div></div>
      <div class="table-responsive"><table class="table table-sm crm-table mb-0"><thead><tr><th data-i18n="admin_templates.th_name"><?= htmlspecialchars($t('admin_templates.th_name', 'Название'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_templates.th_description"><?= htmlspecialchars($t('admin_templates.th_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></th><th></th></tr></thead><tbody id="projectTemplatesBody"><tr><td colspan="3" class="text-muted" data-i18n="admin_templates.loading"><?= htmlspecialchars($t('admin_templates.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
    </div>
  </div>
</div>

</main></div></div>

<div class="modal fade" id="createTaskTemplateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" data-i18n="admin_templates.modal_title_task"><?= htmlspecialchars($t('admin_templates.modal_title_task', 'Создать шаблон задачи'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div>
    <form id="createTaskTemplateForm"><div class="modal-body">
      <div class="mb-3"><label class="form-label" for="taskTemplateTitle" data-i18n="admin_templates.field_title"><?= htmlspecialchars($t('admin_templates.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" id="taskTemplateTitle" name="title" required maxlength="255"></div>
      <div class="mb-3"><label class="form-label" for="taskTemplateDescription" data-i18n="admin_templates.field_description"><?= htmlspecialchars($t('admin_templates.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" id="taskTemplateDescription" name="description" rows="3"></textarea></div>
      <div class="mb-3"><label class="form-label" for="taskTemplateStatusCode" data-i18n="admin_templates.field_default_status"><?= htmlspecialchars($t('admin_templates.field_default_status', 'Статус по умолчанию'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" id="taskTemplateStatusCode" name="status_code" value="new"></div>
      <div class="mb-3"><label class="form-label" for="taskTemplatePriorityCode" data-i18n="admin_templates.field_default_priority"><?= htmlspecialchars($t('admin_templates.field_default_priority', 'Приоритет по умолчанию'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" id="taskTemplatePriorityCode" name="priority_code" value="normal"></div>
    </div><div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button type="submit" class="btn crm-btn-primary" data-i18n="page.create"><?= htmlspecialchars($t('page.create', 'Создать'), ENT_QUOTES, 'UTF-8') ?></button></div></form>
  </div></div>
</div>

<div class="modal fade" id="createProjectTemplateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" data-i18n="admin_templates.modal_title_project"><?= htmlspecialchars($t('admin_templates.modal_title_project', 'Создать шаблон проекта'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div>
    <form id="createProjectTemplateForm"><div class="modal-body">
      <div class="mb-3"><label class="form-label" for="projectTemplateTitle" data-i18n="admin_templates.field_title"><?= htmlspecialchars($t('admin_templates.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" id="projectTemplateTitle" name="title" required maxlength="255"></div>
      <div class="mb-3"><label class="form-label" for="projectTemplateDescription" data-i18n="admin_templates.field_description"><?= htmlspecialchars($t('admin_templates.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" id="projectTemplateDescription" name="description" rows="3"></textarea></div>
    </div><div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button type="submit" class="btn crm-btn-primary" data-i18n="page.create"><?= htmlspecialchars($t('page.create', 'Создать'), ENT_QUOTES, 'UTF-8') ?></button></div></form>
  </div></div>
</div>
