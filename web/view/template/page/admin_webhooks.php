<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Вебхуки'; ?>
<body data-page="admin-webhooks" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title">Вебхуки</h1><p class="crm-subtitle">Управление webhook endpoints для внешних интеграций.</p></div><div class="d-flex gap-2"><button id="adminWebhooksRefreshBtn" class="btn crm-btn-secondary" type="button">Обновить</button></div></div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Список вебхуков</h2><div class="crm-section-note">Настроенные webhook endpoints.</div></div><div class="d-flex gap-2"><button id="createWebhookBtn" class="btn btn-sm crm-btn-primary" type="button">Создать</button></div></div>
      <div class="table-responsive"><table class="table table-sm crm-table mb-0"><thead><tr><th>URL</th><th>События</th><th>Статус</th><th>Последняя доставка</th><th></th></tr></thead><tbody id="adminWebhooksBody"><tr><td colspan="5" class="text-muted">Загрузка...</td></tr></tbody></table></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Статистика</h2><div class="crm-section-note">Общая информация о вебхуках.</div></div></div>
      <div id="adminWebhooksStats"><div class="text-muted">Загрузка...</div></div>
    </div>
  </div>
</div>

</main></div></div>

<div class="modal fade" id="createWebhookModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Создать вебхук</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>
    <form id="createWebhookForm"><div class="modal-body">
      <div class="mb-3"><label class="form-label" for="webhookUrl">URL</label><input class="form-control" id="webhookUrl" name="url" required type="url" placeholder="https://example.com/webhook"></div>
      <div class="mb-3"><label class="form-label" for="webhookSecret">Secret</label><input class="form-control" id="webhookSecret" name="secret" type="password" placeholder="Оставьте пустым для автогенерации"></div>
      <div class="mb-3"><label class="form-label">События</label>
        <div class="row g-2">
          <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="task.created" id="evt_task_created"><label class="form-check-label" for="evt_task_created">task.created</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="task.updated" id="evt_task_updated"><label class="form-check-label" for="evt_task_updated">task.updated</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="task.deleted" id="evt_task_deleted"><label class="form-check-label" for="evt_task_deleted">task.deleted</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="comment.created" id="evt_comment_created"><label class="form-check-label" for="evt_comment_created">comment.created</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="project.created" id="evt_project_created"><label class="form-check-label" for="evt_project_created">project.created</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="project.updated" id="evt_project_updated"><label class="form-check-label" for="evt_project_updated">project.updated</label></div></div>
        </div>
      </div>
      <div class="mb-3"><label class="form-label" for="webhookDescription">Описание</label><textarea class="form-control" id="webhookDescription" name="description" rows="2"></textarea></div>
      <div class="mb-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="webhookIsActive" checked><label class="form-check-label" for="webhookIsActive">Активен</label></div></div>
    </div><div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button><button type="submit" class="btn crm-btn-primary">Создать</button></div></form>
  </div></div>
</div>
