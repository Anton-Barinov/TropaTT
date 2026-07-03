<?php declare(strict_types=1); ?>
<?php $title = $t('admin_webhooks.title', 'TropaTT — Вебхуки'); ?>
<body data-page="admin-webhooks" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="admin_webhooks.page_title"><?= htmlspecialchars($t('admin_webhooks.page_title', 'Вебхуки'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_webhooks.subtitle"><?= htmlspecialchars($t('admin_webhooks.subtitle', 'Управление webhook endpoints для внешних интеграций.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="d-flex gap-2"><button id="adminWebhooksRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="admin_webhooks.refresh_btn"><?= htmlspecialchars($t('admin_webhooks.refresh_btn', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button></div></div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_webhooks.section_list_title"><?= htmlspecialchars($t('admin_webhooks.section_list_title', 'Список вебхуков'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_webhooks.section_list_note"><?= htmlspecialchars($t('admin_webhooks.section_list_note', 'Настроенные webhook endpoints.'), ENT_QUOTES, 'UTF-8') ?></div></div><div class="d-flex gap-2"><button id="createWebhookBtn" class="btn btn-sm crm-btn-primary" type="button" data-i18n="admin_webhooks.create_btn"><?= htmlspecialchars($t('admin_webhooks.create_btn', 'Создать'), ENT_QUOTES, 'UTF-8') ?></button></div></div>
      <div class="table-responsive"><table class="table table-sm crm-table mb-0"><thead><tr><th data-i18n="admin_webhooks.th_url"><?= htmlspecialchars($t('admin_webhooks.th_url', 'URL'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_webhooks.th_events"><?= htmlspecialchars($t('admin_webhooks.th_events', 'События'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_webhooks.th_status"><?= htmlspecialchars($t('admin_webhooks.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_webhooks.th_last_delivery"><?= htmlspecialchars($t('admin_webhooks.th_last_delivery', 'Последняя доставка'), ENT_QUOTES, 'UTF-8') ?></th><th></th></tr></thead><tbody id="adminWebhooksBody"><tr><td colspan="5" class="text-muted" data-i18n="admin_webhooks.loading"><?= htmlspecialchars($t('admin_webhooks.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_webhooks.section_stats_title"><?= htmlspecialchars($t('admin_webhooks.section_stats_title', 'Статистика'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_webhooks.section_stats_note"><?= htmlspecialchars($t('admin_webhooks.section_stats_note', 'Общая информация о вебхуках.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div id="adminWebhooksStats"><div class="text-muted" data-i18n="admin_webhooks.loading"><?= htmlspecialchars($t('admin_webhooks.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
    </div>
  </div>
</div>

</main></div></div>

<div class="modal fade" id="createWebhookModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" data-i18n="admin_webhooks.modal_create_title"><?= htmlspecialchars($t('admin_webhooks.modal_create_title', 'Создать вебхук'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div>
    <form id="createWebhookForm"><div class="modal-body">
      <div class="mb-3"><label class="form-label" for="webhookUrl" data-i18n="admin_webhooks.field_url"><?= htmlspecialchars($t('admin_webhooks.field_url', 'URL'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" id="webhookUrl" name="url" required type="url" placeholder="<?= htmlspecialchars($t('admin_webhooks.placeholder_url', 'https://example.com/webhook'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_webhooks.placeholder_url"></div>
      <div class="mb-3"><label class="form-label" for="webhookSecret" data-i18n="admin_webhooks.field_secret"><?= htmlspecialchars($t('admin_webhooks.field_secret', 'Secret'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" id="webhookSecret" name="secret" type="password" placeholder="<?= htmlspecialchars($t('admin_webhooks.placeholder_secret', 'Оставьте пустым для автогенерации'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_webhooks.placeholder_secret"></div>
      <div class="mb-3"><label class="form-label" data-i18n="admin_webhooks.field_events"><?= htmlspecialchars($t('admin_webhooks.field_events', 'События'), ENT_QUOTES, 'UTF-8') ?></label>
        <div class="row g-2">
          <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="task.created" id="evt_task_created"><label class="form-check-label" for="evt_task_created">task.created</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="task.updated" id="evt_task_updated"><label class="form-check-label" for="evt_task_updated">task.updated</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="task.deleted" id="evt_task_deleted"><label class="form-check-label" for="evt_task_deleted">task.deleted</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="comment.created" id="evt_comment_created"><label class="form-check-label" for="evt_comment_created">comment.created</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="project.created" id="evt_project_created"><label class="form-check-label" for="evt_project_created">project.created</label></div></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="project.updated" id="evt_project_updated"><label class="form-check-label" for="evt_project_updated">project.updated</label></div></div>
        </div>
      </div>
      <div class="mb-3"><label class="form-label" for="webhookDescription" data-i18n="admin_webhooks.field_description"><?= htmlspecialchars($t('admin_webhooks.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" id="webhookDescription" name="description" rows="2" data-crm-visual-editor="1" data-richtext-off="1"></textarea></div>
      <div class="mb-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="webhookIsActive" checked><label class="form-check-label" for="webhookIsActive" data-i18n="admin_webhooks.field_is_active"><?= htmlspecialchars($t('admin_webhooks.field_is_active', 'Активен'), ENT_QUOTES, 'UTF-8') ?></label></div></div>
    </div><div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button type="submit" class="btn crm-btn-primary" data-i18n="page.create"><?= htmlspecialchars($t('page.create', 'Создать'), ENT_QUOTES, 'UTF-8') ?></button></div></form>
  </div></div>
</div>
