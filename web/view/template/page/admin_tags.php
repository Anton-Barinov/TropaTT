<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Теги'; ?>
<body data-page="admin-tags" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title">Теги</h1><p class="crm-subtitle">Управление глобальными тегами для задач и проектов.</p></div><div class="d-flex gap-2"><button id="adminTagsRefreshBtn" class="btn crm-btn-secondary" type="button">Обновить</button></div></div>

<div class="row g-3">
  <div class="col-lg-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Список тегов</h2><div class="crm-section-note">Глобальные теги, доступные для всех задач и проектов.</div></div><div class="d-flex gap-2"><button id="createTagBtn" class="btn btn-sm crm-btn-primary" type="button">Создать</button></div></div>
      <div class="table-responsive"><table class="table table-sm crm-table mb-0"><thead><tr><th>Название</th><th>Цвет</th><th>Описание</th><th>Использований</th><th></th></tr></thead><tbody id="adminTagsBody"><tr><td colspan="5" class="text-muted">Загрузка...</td></tr></tbody></table></div>
    </div>
  </div>
</div>

</main></div></div>

<div class="modal fade" id="createTagModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Создать тег</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>
    <form id="createTagForm"><div class="modal-body">
      <div class="mb-3"><label class="form-label" for="tagName">Название</label><input class="form-control" id="tagName" name="name" required maxlength="64"></div>
      <div class="mb-3"><label class="form-label" for="tagColor">Цвет</label><input class="form-control" id="tagColor" name="color" type="color" value="#0f8f72"></div>
      <div class="mb-3"><label class="form-label" for="tagDescription">Описание</label><textarea class="form-control" id="tagDescription" name="description" rows="2"></textarea></div>
    </div><div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button><button type="submit" class="btn crm-btn-primary">Создать</button></div></form>
  </div></div>
</div>

<div class="modal fade" id="editTagModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Редактировать тег</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>
    <form id="editTagForm"><div class="modal-body">
      <input type="hidden" id="editTagPublicId" name="public_id">
      <div class="mb-3"><label class="form-label" for="editTagName">Название</label><input class="form-control" id="editTagName" name="name" required maxlength="64"></div>
      <div class="mb-3"><label class="form-label" for="editTagColor">Цвет</label><input class="form-control" id="editTagColor" name="color" type="color"></div>
      <div class="mb-3"><label class="form-label" for="editTagDescription">Описание</label><textarea class="form-control" id="editTagDescription" name="description" rows="2"></textarea></div>
    </div><div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button><button type="submit" class="btn crm-btn-primary">Сохранить</button></div></form>
  </div></div>
</div>
