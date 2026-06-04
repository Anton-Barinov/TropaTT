<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Шаблоны'; ?>
<body data-page="admin-templates" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title">Шаблоны</h1><p class="crm-subtitle">Управление шаблонами задач и проектов.</p></div><div class="d-flex gap-2"><button id="adminTemplatesRefreshBtn" class="btn crm-btn-secondary" type="button">Обновить</button></div></div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Шаблоны задач</h2><div class="crm-section-note">Переиспользуемые шаблоны задач.</div></div><div class="d-flex gap-2"><button id="createTaskTemplateBtn" class="btn btn-sm crm-btn-primary" type="button">Создать</button></div></div>
      <div class="table-responsive"><table class="table table-sm crm-table mb-0"><thead><tr><th>Название</th><th>Описание</th><th></th></tr></thead><tbody id="taskTemplatesBody"><tr><td colspan="3" class="text-muted">Загрузка...</td></tr></tbody></table></div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Шаблоны проектов</h2><div class="crm-section-note">Переиспользуемые шаблоны проектов.</div></div><div class="d-flex gap-2"><button id="createProjectTemplateBtn" class="btn btn-sm crm-btn-primary" type="button">Создать</button></div></div>
      <div class="table-responsive"><table class="table table-sm crm-table mb-0"><thead><tr><th>Название</th><th>Описание</th><th></th></tr></thead><tbody id="projectTemplatesBody"><tr><td colspan="3" class="text-muted">Загрузка...</td></tr></tbody></table></div>
    </div>
  </div>
</div>

</main></div></div>

<div class="modal fade" id="createTaskTemplateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Создать шаблон задачи</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>
    <form id="createTaskTemplateForm"><div class="modal-body">
      <div class="mb-3"><label class="form-label" for="taskTemplateTitle">Название</label><input class="form-control" id="taskTemplateTitle" name="title" required maxlength="255"></div>
      <div class="mb-3"><label class="form-label" for="taskTemplateDescription">Описание</label><textarea class="form-control" id="taskTemplateDescription" name="description" rows="3"></textarea></div>
      <div class="mb-3"><label class="form-label" for="taskTemplateStatusCode">Статус по умолчанию</label><input class="form-control" id="taskTemplateStatusCode" name="status_code" value="new"></div>
      <div class="mb-3"><label class="form-label" for="taskTemplatePriorityCode">Приоритет по умолчанию</label><input class="form-control" id="taskTemplatePriorityCode" name="priority_code" value="normal"></div>
    </div><div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button><button type="submit" class="btn crm-btn-primary">Создать</button></div></form>
  </div></div>
</div>

<div class="modal fade" id="createProjectTemplateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Создать шаблон проекта</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>
    <form id="createProjectTemplateForm"><div class="modal-body">
      <div class="mb-3"><label class="form-label" for="projectTemplateTitle">Название</label><input class="form-control" id="projectTemplateTitle" name="title" required maxlength="255"></div>
      <div class="mb-3"><label class="form-label" for="projectTemplateDescription">Описание</label><textarea class="form-control" id="projectTemplateDescription" name="description" rows="3"></textarea></div>
    </div><div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button><button type="submit" class="btn crm-btn-primary">Создать</button></div></form>
  </div></div>
</div>
