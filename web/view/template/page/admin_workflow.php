<?php declare(strict_types=1); ?>
<?php $title = $t('admin_workflow.title', 'TropaTT — Правила автоматизации'); ?>
<body data-page="admin-workflow" data-protected="1">
<div class="crm-app">
  <aside class="crm-sidebar">
    <div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div>
    <nav class="nav flex-column crm-nav"></nav>
  </aside>
  <div class="crm-main-wrap">
    <header class="crm-topbar py-2"><div class="container-fluid"></div></header>
    <main class="crm-content crm-automation-page">
      <div class="crm-page-head">
        <div>
          <ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="nav.admin"><?= htmlspecialchars($t('nav.admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="admin_workflow.page_title"><?= htmlspecialchars($t('admin_workflow.page_title', 'Правила автоматизации'), ENT_QUOTES, 'UTF-8') ?></li></ol>
          <h1 class="crm-page-title" data-i18n="admin_workflow.page_title"><?= htmlspecialchars($t('admin_workflow.page_title', 'Правила автоматизации'), ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="crm-subtitle" data-i18n="admin_workflow.subtitle"><?= htmlspecialchars($t('admin_workflow.subtitle', 'Когда в задачах происходит событие — CRM сама выполняет действие. Без кода, без скриптов.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="d-flex gap-2">
          <button id="adminWorkflowRefreshBtn" class="btn crm-btn-secondary" type="button"><i class="fa-solid fa-rotate me-1" aria-hidden="true"></i><span data-i18n="admin_workflow.refresh_btn"><?= htmlspecialchars($t('admin_workflow.refresh_btn', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></span></button>
          <button id="adminWorkflowCreateBtn" class="btn crm-btn-primary" type="button"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i><span data-i18n="admin_workflow.create_btn"><?= htmlspecialchars($t('admin_workflow.create_btn', 'Создать правило'), ENT_QUOTES, 'UTF-8') ?></span></button>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-bolt" aria-hidden="true"></i></span><strong data-i18n="admin_workflow.brief_event_title"><?= htmlspecialchars($t('admin_workflow.brief_event_title', 'Событие'), ENT_QUOTES, 'UTF-8') ?></strong><p class="mb-0" data-i18n="admin_workflow.brief_event_desc"><?= htmlspecialchars($t('admin_workflow.brief_event_desc', 'Создание задачи, изменение полей или смена статуса — три простых триггера для запуска правила.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
        <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></span><strong data-i18n="admin_workflow.brief_action_title"><?= htmlspecialchars($t('admin_workflow.brief_action_title', 'Действие'), ENT_QUOTES, 'UTF-8') ?></strong><p class="mb-0" data-i18n="admin_workflow.brief_action_desc"><?= htmlspecialchars($t('admin_workflow.brief_action_desc', 'Сменить статус, назначить исполнителя, добавить комментарий, уведомить, создать напоминание, follow-up задачу, вызвать вебхук или эскалировать SLA.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
        <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i></span><strong data-i18n="admin_workflow.brief_test_title"><?= htmlspecialchars($t('admin_workflow.brief_test_title', 'Проверка'), ENT_QUOTES, 'UTF-8') ?></strong><p class="mb-0" data-i18n="admin_workflow.brief_test_desc"><?= htmlspecialchars($t('admin_workflow.brief_test_desc', 'Протестируйте правило на любой задаче — результат запишется в журнал выполнения. Ничего не сломается.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
      </div>

      <div class="row g-3">
        <div class="col-12">
          <div class="crm-card crm-section-card crm-automation-list-toolbar">
            <div class="crm-section-head">
              <div><h2 class="h6 mb-0" data-i18n="admin_workflow.section_rules_title"><?= htmlspecialchars($t('admin_workflow.section_rules_title', 'Правила'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" id="adminWorkflowRulesCount"></div></div>
              <div class="d-flex gap-2"><input type="search" id="adminWorkflowSearchInput" class="form-control form-control-sm" placeholder="<?= htmlspecialchars($t('admin_workflow.search_placeholder', 'Поиск правил...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_workflow.search_placeholder" style="max-width:240px"><button id="adminWorkflowSearchClear" class="btn btn-sm btn-light d-none" type="button" data-i18n="admin_workflow.search_clear"><?= htmlspecialchars($t('admin_workflow.search_clear', 'Сбросить'), ENT_QUOTES, 'UTF-8') ?></button></div>
            </div>
          </div>
          <div class="crm-card crm-section-card p-0 table-responsive crm-automation-table-card">
            <table class="table table-hover align-middle crm-table crm-automation-table mb-0">
              <thead><tr><th data-i18n="admin_workflow.th_title"><?= htmlspecialchars($t('admin_workflow.th_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_workflow.th_trigger"><?= htmlspecialchars($t('admin_workflow.th_trigger', 'Когда'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_workflow.th_action"><?= htmlspecialchars($t('admin_workflow.th_action', 'Что сделать'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_workflow.th_params"><?= htmlspecialchars($t('admin_workflow.th_params', 'Параметры'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_workflow.th_status"><?= htmlspecialchars($t('admin_workflow.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th class="text-end" data-i18n="admin_workflow.th_actions"><?= htmlspecialchars($t('admin_workflow.th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
              <tbody id="adminWorkflowRulesBody"><tr><td colspan="6" class="text-muted" data-i18n="admin_workflow.loading_rules"><?= htmlspecialchars($t('admin_workflow.loading_rules', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-12">
          <div class="crm-card crm-section-card crm-automation-list-toolbar">
            <div class="crm-section-head">
              <div><h2 class="h6 mb-0" data-i18n="admin_workflow.section_logs_title"><?= htmlspecialchars($t('admin_workflow.section_logs_title', 'Журнал выполнения'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_workflow.section_logs_note"><?= htmlspecialchars($t('admin_workflow.section_logs_note', 'Последние тестовые и реальные запуски правил.'), ENT_QUOTES, 'UTF-8') ?></div></div>
            </div>
          </div>
          <div class="crm-card crm-section-card p-0 table-responsive crm-automation-table-card">
            <table class="table table-hover align-middle crm-table crm-automation-table mb-0">
              <thead><tr><th data-i18n="admin_workflow.th_log_rule"><?= htmlspecialchars($t('admin_workflow.th_log_rule', 'Правило'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_workflow.th_log_trigger"><?= htmlspecialchars($t('admin_workflow.th_log_trigger', 'Событие'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_workflow.th_log_action"><?= htmlspecialchars($t('admin_workflow.th_log_action', 'Действие'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_workflow.th_log_result"><?= htmlspecialchars($t('admin_workflow.th_log_result', 'Результат'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_workflow.th_log_details"><?= htmlspecialchars($t('admin_workflow.th_log_details', 'Подробности'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_workflow.th_log_date"><?= htmlspecialchars($t('admin_workflow.th_log_date', 'Дата'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
              <tbody id="adminWorkflowLogsBody"><tr><td colspan="6" class="text-muted" data-i18n="admin_workflow.loading_logs"><?= htmlspecialchars($t('admin_workflow.loading_logs', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Create/Edit Modal -->
      <div class="modal fade" id="adminWorkflowCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <div><h5 class="modal-title" id="adminWorkflowModalTitle" data-i18n="admin_workflow.modal_create_title"><?= htmlspecialchars($t('admin_workflow.modal_create_title', 'Создать правило автоматизации'), ENT_QUOTES, 'UTF-8') ?></h5><div class="crm-modal-subtitle" data-i18n="admin_workflow.modal_create_subtitle"><?= htmlspecialchars($t('admin_workflow.modal_create_subtitle', 'Выберите событие и действие — система подготовит нужные параметры.'), ENT_QUOTES, 'UTF-8') ?></div></div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
            </div>
            <form id="adminWorkflowCreateForm">
              <input type="hidden" name="public_id">
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label" for="workflowRuleTitle" data-i18n="admin_workflow.field_rule_title"><?= htmlspecialchars($t('admin_workflow.field_rule_title', 'Название правила'), ENT_QUOTES, 'UTF-8') ?></label>
                  <input id="workflowRuleTitle" class="form-control" name="title" maxlength="255" required placeholder="<?= htmlspecialchars($t('admin_workflow.placeholder_rule_title', 'Например: поставить задачу в работу после создания'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_workflow.placeholder_rule_title">
                  <div class="form-text" data-i18n="admin_workflow.help_rule_title"><?= htmlspecialchars($t('admin_workflow.help_rule_title', 'Дайте понятное название. По нему вы сможете быстро найти правило в списке.'), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label" for="workflowTriggerCode" data-i18n="admin_workflow.field_trigger"><?= htmlspecialchars($t('admin_workflow.field_trigger', 'Когда сработает'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select id="workflowTriggerCode" class="form-select" name="trigger_code" required>
                      <option value="task_created" data-i18n="admin_workflow.trigger_task_created"><?= htmlspecialchars($t('admin_workflow.trigger_task_created', 'Задача создана'), ENT_QUOTES, 'UTF-8') ?></option>
                      <option value="task_updated" data-i18n="admin_workflow.trigger_task_updated"><?= htmlspecialchars($t('admin_workflow.trigger_task_updated', 'Задача изменена'), ENT_QUOTES, 'UTF-8') ?></option>
                      <option value="task_status_changed" data-i18n="admin_workflow.trigger_task_status_changed"><?= htmlspecialchars($t('admin_workflow.trigger_task_status_changed', 'Статус задачи изменен'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="workflowActionCode" data-i18n="admin_workflow.field_action"><?= htmlspecialchars($t('admin_workflow.field_action', 'Что сделать'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select id="workflowActionCode" class="form-select" name="action_code" required>
                      <option value="change_status" data-i18n="admin_workflow.action_change_status"><?= htmlspecialchars($t('admin_workflow.action_change_status', 'Изменить статус задачи'), ENT_QUOTES, 'UTF-8') ?></option>
                      <option value="assign_user" data-i18n="admin_workflow.action_assign_user"><?= htmlspecialchars($t('admin_workflow.action_assign_user', 'Назначить исполнителя'), ENT_QUOTES, 'UTF-8') ?></option>
                      <option value="create_comment" data-i18n="admin_workflow.action_create_comment"><?= htmlspecialchars($t('admin_workflow.action_create_comment', 'Добавить комментарий'), ENT_QUOTES, 'UTF-8') ?></option>
                      <option value="send_notification" data-i18n="admin_workflow.action_send_notification"><?= htmlspecialchars($t('admin_workflow.action_send_notification', 'Отправить уведомление'), ENT_QUOTES, 'UTF-8') ?></option>
                      <option value="create_follow_up_task" data-i18n="admin_workflow.action_create_follow_up"><?= htmlspecialchars($t('admin_workflow.action_create_follow_up', 'Создать follow-up подзадачу'), ENT_QUOTES, 'UTF-8') ?></option>
                      <option value="create_reminder" data-i18n="admin_workflow.action_create_reminder"><?= htmlspecialchars($t('admin_workflow.action_create_reminder', 'Создать напоминание'), ENT_QUOTES, 'UTF-8') ?></option>
                      <option value="escalate_sla" data-i18n="admin_workflow.action_escalate_sla"><?= htmlspecialchars($t('admin_workflow.action_escalate_sla', 'Отметить SLA как нарушенное'), ENT_QUOTES, 'UTF-8') ?></option>
                      <option value="call_webhook" data-i18n="admin_workflow.action_call_webhook"><?= htmlspecialchars($t('admin_workflow.action_call_webhook', 'Вызвать вебхук'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                  </div>
                </div>

                <!-- Filter conditions for status trigger -->
                <div class="crm-workflow-action-panel mt-3 d-none" data-filter-panel="task_status_changed">
                  <label class="form-label fw-bold" data-i18n="admin_workflow.section_conditions"><?= htmlspecialchars($t('admin_workflow.section_conditions', 'Условия срабатывания'), ENT_QUOTES, 'UTF-8') ?></label>
                  <div class="crm-inline-help mb-2"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span data-i18n="admin_workflow.help_conditions"><?= htmlspecialchars($t('admin_workflow.help_conditions', 'Правило сработает только при переходе задачи из одного статуса в другой. Можно не указывать «из» или «в» — тогда правило сработает при любом изменении.'), ENT_QUOTES, 'UTF-8') ?></span></div>
                  <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="workflowFromStatus" data-i18n="admin_workflow.field_from_status"><?= htmlspecialchars($t('admin_workflow.field_from_status', 'Из статуса'), ENT_QUOTES, 'UTF-8') ?></label><select id="workflowFromStatus" class="form-select" name="from_status_code"><option value="" data-i18n="admin_workflow.opt_any_status"><?= htmlspecialchars($t('admin_workflow.opt_any_status', 'Любой статус'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
                    <div class="col-md-6"><label class="form-label" for="workflowToStatus" data-i18n="admin_workflow.field_to_status"><?= htmlspecialchars($t('admin_workflow.field_to_status', 'В статус'), ENT_QUOTES, 'UTF-8') ?></label><select id="workflowToStatus" class="form-select" name="to_status_code"><option value="" data-i18n="admin_workflow.opt_any_status"><?= htmlspecialchars($t('admin_workflow.opt_any_status', 'Любой статус'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
                    <div class="col-md-6"><label class="form-label" for="workflowConditionTag" data-i18n="admin_workflow.field_tag"><?= htmlspecialchars($t('admin_workflow.field_tag', 'Тег задачи'), ENT_QUOTES, 'UTF-8') ?></label><select id="workflowConditionTag" class="form-select" name="condition_tag_public_id"><option value="" data-i18n="admin_workflow.opt_any_tag"><?= htmlspecialchars($t('admin_workflow.opt_any_tag', 'Любой тег'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
                  </div>
                </div>

                <!-- Action panels -->
                <div class="crm-workflow-action-panel mt-3 d-none" data-action-panel="change_status">
                  <label class="form-label" for="workflowStatusCode" data-i18n="admin_workflow.field_new_status"><?= htmlspecialchars($t('admin_workflow.field_new_status', 'Новый статус задачи'), ENT_QUOTES, 'UTF-8') ?></label>
                  <select id="workflowStatusCode" class="form-select" name="status_code"></select>
                </div>
                <div class="crm-workflow-action-panel mt-3 d-none" data-action-panel="assign_user">
                  <label class="form-label" for="workflowAssigneeUser" data-i18n="admin_workflow.field_assignee"><?= htmlspecialchars($t('admin_workflow.field_assignee', 'Назначить исполнителем'), ENT_QUOTES, 'UTF-8') ?></label>
                  <select id="workflowAssigneeUser" class="form-select" name="assignee_user_public_id"></select>
                </div>
                <div class="crm-workflow-action-panel mt-3 d-none" data-action-panel="create_comment">
                  <label class="form-label" for="workflowCommentText" data-i18n="admin_workflow.field_comment_text"><?= htmlspecialchars($t('admin_workflow.field_comment_text', 'Текст комментария'), ENT_QUOTES, 'UTF-8') ?></label>
                  <textarea id="workflowCommentText" class="form-control" name="comment_text" rows="3" placeholder="<?= htmlspecialchars($t('admin_workflow.placeholder_comment', 'Например: Проверьте задачу и обновите срок.'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_workflow.placeholder_comment"></textarea>
                </div>
                <div class="crm-workflow-action-panel mt-3 d-none" data-action-panel="send_notification">
                  <label class="form-label" for="workflowNotificationUsers" data-i18n="admin_workflow.field_recipients"><?= htmlspecialchars($t('admin_workflow.field_recipients', 'Кому отправить'), ENT_QUOTES, 'UTF-8') ?></label>
                  <select id="workflowNotificationUsers" class="form-select" name="recipient_user_public_ids" multiple></select>
                  <div class="row g-3 mt-1"><div class="col-md-5"><label class="form-label" for="workflowNotificationTitle" data-i18n="admin_workflow.field_notification_title"><?= htmlspecialchars($t('admin_workflow.field_notification_title', 'Заголовок'), ENT_QUOTES, 'UTF-8') ?></label><input id="workflowNotificationTitle" class="form-control" name="notification_title" placeholder="<?= htmlspecialchars($t('admin_workflow.placeholder_notification_title', 'Нужно внимание'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_workflow.placeholder_notification_title"></div><div class="col-md-7"><label class="form-label" for="workflowNotificationBody" data-i18n="admin_workflow.field_notification_body"><?= htmlspecialchars($t('admin_workflow.field_notification_body', 'Сообщение'), ENT_QUOTES, 'UTF-8') ?></label><input id="workflowNotificationBody" class="form-control" name="notification_body" placeholder="<?= htmlspecialchars($t('admin_workflow.placeholder_notification_body', 'Проверьте задачу'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_workflow.placeholder_notification_body"></div></div>
                </div>
                <div class="crm-workflow-action-panel mt-3 d-none" data-action-panel="create_follow_up_task">
                  <div class="row g-3"><div class="col-md-7"><label class="form-label" for="workflowFollowupTitle" data-i18n="admin_workflow.field_followup_title"><?= htmlspecialchars($t('admin_workflow.field_followup_title', 'Название подзадачи'), ENT_QUOTES, 'UTF-8') ?></label><input id="workflowFollowupTitle" class="form-control" name="task_title" placeholder="<?= htmlspecialchars($t('admin_workflow.placeholder_followup_title', 'Связаться с ответственным'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_workflow.placeholder_followup_title"></div><div class="col-md-5"><label class="form-label" for="workflowFollowupAssignee" data-i18n="admin_workflow.field_followup_assignee"><?= htmlspecialchars($t('admin_workflow.field_followup_assignee', 'Исполнитель'), ENT_QUOTES, 'UTF-8') ?></label><select id="workflowFollowupAssignee" class="form-select" name="followup_assignee_user_public_id"></select></div></div>
                </div>
                <div class="crm-workflow-action-panel mt-3 d-none" data-action-panel="create_reminder">
                  <div class="row g-3"><div class="col-md-6"><label class="form-label" for="workflowReminderUser" data-i18n="admin_workflow.field_reminder_user"><?= htmlspecialchars($t('admin_workflow.field_reminder_user', 'Кому напомнить'), ENT_QUOTES, 'UTF-8') ?></label><select id="workflowReminderUser" class="form-select" name="reminder_user_public_id"></select></div><div class="col-md-6"><label class="form-label" for="workflowReminderAt" data-i18n="admin_workflow.field_reminder_at"><?= htmlspecialchars($t('admin_workflow.field_reminder_at', 'Когда'), ENT_QUOTES, 'UTF-8') ?></label><input id="workflowReminderAt" class="form-control" name="remind_at" placeholder="<?= htmlspecialchars($t('admin_workflow.placeholder_reminder_at', '+1 hour'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_workflow.placeholder_reminder_at"></div></div>
                  <div class="crm-inline-help mt-2"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span data-i18n="admin_workflow.help_reminder_at"><?= htmlspecialchars($t('admin_workflow.help_reminder_at', 'Дата-время или понятная запись: +1 hour, +30 minutes, tomorrow 10:00.'), ENT_QUOTES, 'UTF-8') ?></span></div>
                </div>
                <div class="crm-workflow-action-panel mt-3 d-none" data-action-panel="call_webhook">
                  <label class="form-label" for="workflowWebhookUrl" data-i18n="admin_workflow.field_webhook_url"><?= htmlspecialchars($t('admin_workflow.field_webhook_url', 'URL вебхука'), ENT_QUOTES, 'UTF-8') ?></label>
                  <input id="workflowWebhookUrl" class="form-control" name="webhook_url" placeholder="<?= htmlspecialchars($t('admin_workflow.placeholder_webhook_url', 'https://example.com/webhook'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_workflow.placeholder_webhook_url">
                </div>
                <div class="crm-workflow-action-panel mt-3 d-none" data-action-panel="escalate_sla">
                  <div class="crm-inline-help"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span data-i18n="admin_workflow.help_escalate_sla"><?= htmlspecialchars($t('admin_workflow.help_escalate_sla', 'При срабатывании правило отметит задачу как нарушившую SLA.'), ENT_QUOTES, 'UTF-8') ?></span></div>
                </div>
                <div class="form-check mt-3"><input class="form-check-input" type="checkbox" value="1" id="workflowRuleEnabled" name="is_enabled" checked><label class="form-check-label" for="workflowRuleEnabled" data-i18n="admin_workflow.field_enabled"><?= htmlspecialchars($t('admin_workflow.field_enabled', 'Правило включено'), ENT_QUOTES, 'UTF-8') ?></label></div>
              </div>
              <div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" type="submit" id="adminWorkflowSubmitBtn" data-i18n="admin_workflow.submit_btn"><?= htmlspecialchars($t('admin_workflow.submit_btn', 'Создать правило'), ENT_QUOTES, 'UTF-8') ?></button></div>
            </form>
          </div>
        </div>
      </div>

      <!-- Test Modal -->
      <div class="modal fade" id="adminWorkflowTestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header"><div><h5 class="modal-title" data-i18n="admin_workflow.modal_test_title"><?= htmlspecialchars($t('admin_workflow.modal_test_title', 'Проверить правило'), ENT_QUOTES, 'UTF-8') ?></h5><div class="crm-modal-subtitle" data-i18n="admin_workflow.modal_test_subtitle"><?= htmlspecialchars($t('admin_workflow.modal_test_subtitle', 'Выберите задачу — система применит правило к ней и запишет результат в журнал.'), ENT_QUOTES, 'UTF-8') ?></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div>
            <form id="adminWorkflowTestForm">
              <input type="hidden" name="rule_public_id">
              <div class="modal-body"><label class="form-label" for="workflowTestTask" data-i18n="admin_workflow.field_test_task"><?= htmlspecialchars($t('admin_workflow.field_test_task', 'Задача для проверки'), ENT_QUOTES, 'UTF-8') ?></label><select id="workflowTestTask" class="form-select" name="task_public_id" required></select><div class="crm-inline-help mt-2"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span data-i18n="admin_workflow.help_test"><?= htmlspecialchars($t('admin_workflow.help_test', 'Правило выполнит реальное действие. Результат появится в журнале.'), ENT_QUOTES, 'UTF-8') ?></span></div></div>
              <div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" type="submit" data-i18n="admin_workflow.test_btn"><?= htmlspecialchars($t('admin_workflow.test_btn', 'Запустить тест'), ENT_QUOTES, 'UTF-8') ?></button></div>
            </form>
          </div>
        </div>
      </div>

      <!-- Delete Confirm Modal -->
      <div class="modal fade" id="adminWorkflowDeleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header"><div><h5 class="modal-title" data-i18n="admin_workflow.modal_delete_title"><?= htmlspecialchars($t('admin_workflow.modal_delete_title', 'Удалить правило?'), ENT_QUOTES, 'UTF-8') ?></h5><div class="crm-modal-subtitle" data-i18n="admin_workflow.modal_delete_subtitle"><?= htmlspecialchars($t('admin_workflow.modal_delete_subtitle', 'Это действие нельзя отменить. Все связанные записи в журнале сохранятся.'), ENT_QUOTES, 'UTF-8') ?></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div>
            <div class="modal-body"><p id="adminWorkflowDeleteRuleTitle" class="fw-bold"></p></div>
            <div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-danger" type="button" id="adminWorkflowDeleteConfirmBtn" data-i18n="page.delete"><?= htmlspecialchars($t('page.delete', 'Удалить'), ENT_QUOTES, 'UTF-8') ?></button></div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
