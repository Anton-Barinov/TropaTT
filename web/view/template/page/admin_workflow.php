<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Правила автоматизации'; ?>
<body data-page="admin-workflow" data-protected="1">
<div class="crm-app">
  <aside class="crm-sidebar">
    <div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div>
    <nav class="nav flex-column crm-nav"></nav>
  </aside>
  <div class="crm-main-wrap">
    <header class="crm-topbar py-2"><div class="container-fluid"></div></header>
    <main class="crm-content crm-automation-page">
      <div class="crm-page-head">
        <div>
          <ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin">Админка</a></li><li class="breadcrumb-item active">Правила автоматизации</li></ol>
          <h1 class="crm-page-title">Правила автоматизации</h1>
          <p class="crm-subtitle">Когда в задачах происходит событие — CRM сама выполняет действие. Без кода, без скриптов.</p>
        </div>
        <div class="d-flex gap-2">
          <button id="adminWorkflowRefreshBtn" class="btn crm-btn-secondary" type="button"><i class="fa-solid fa-rotate me-1" aria-hidden="true"></i>Обновить</button>
          <button id="adminWorkflowCreateBtn" class="btn crm-btn-primary" type="button"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Создать правило</button>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-bolt" aria-hidden="true"></i></span><strong>Событие</strong><p class="mb-0">Создание задачи, изменение полей или смена статуса — три простых триггера для запуска правила.</p></div></div>
        <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></span><strong>Действие</strong><p class="mb-0">Сменить статус, назначить исполнителя, добавить комментарий, уведомить, создать напоминание, follow-up задачу, вызвать вебхук или эскалировать SLA.</p></div></div>
        <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i></span><strong>Проверка</strong><p class="mb-0">Протестируйте правило на любой задаче — результат запишется в журнал выполнения. Ничего не сломается.</p></div></div>
      </div>

      <div class="row g-3">
        <div class="col-12">
          <div class="crm-card crm-section-card crm-automation-list-toolbar">
            <div class="crm-section-head">
              <div><h2 class="h6 mb-0">Правила</h2><div class="crm-section-note" id="adminWorkflowRulesCount"></div></div>
              <div class="d-flex gap-2"><input type="search" id="adminWorkflowSearchInput" class="form-control form-control-sm" placeholder="Поиск правил..." style="max-width:240px"><button id="adminWorkflowSearchClear" class="btn btn-sm btn-light d-none" type="button">Сбросить</button></div>
            </div>
          </div>
          <div class="crm-card crm-section-card p-0 table-responsive crm-automation-table-card">
            <table class="table table-hover align-middle crm-table crm-automation-table mb-0">
              <thead><tr><th>Название</th><th>Когда</th><th>Что сделать</th><th>Параметры</th><th>Статус</th><th class="text-end">Действия</th></tr></thead>
              <tbody id="adminWorkflowRulesBody"><tr><td colspan="6" class="text-muted">Загрузка...</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-12">
          <div class="crm-card crm-section-card crm-automation-list-toolbar">
            <div class="crm-section-head">
              <div><h2 class="h6 mb-0">Журнал выполнения</h2><div class="crm-section-note">Последние тестовые и реальные запуски правил.</div></div>
            </div>
          </div>
          <div class="crm-card crm-section-card p-0 table-responsive crm-automation-table-card">
            <table class="table table-hover align-middle crm-table crm-automation-table mb-0">
              <thead><tr><th>Правило</th><th>Событие</th><th>Действие</th><th>Результат</th><th>Подробности</th><th>Дата</th></tr></thead>
              <tbody id="adminWorkflowLogsBody"><tr><td colspan="6" class="text-muted">Загрузка...</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Create/Edit Modal -->
      <div class="modal fade" id="adminWorkflowCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <div><h5 class="modal-title" id="adminWorkflowModalTitle">Создать правило автоматизации</h5><div class="crm-modal-subtitle">Выберите событие и действие — система подготовит нужные параметры.</div></div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <form id="adminWorkflowCreateForm">
              <input type="hidden" name="public_id">
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label" for="workflowRuleTitle">Название правила</label>
                  <input id="workflowRuleTitle" class="form-control" name="title" maxlength="255" required placeholder="Например: поставить задачу в работу после создания">
                  <div class="form-text">Дайте понятное название. По нему вы сможете быстро найти правило в списке.</div>
                </div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label" for="workflowTriggerCode">Когда сработает</label>
                    <select id="workflowTriggerCode" class="form-select" name="trigger_code" required>
                      <option value="task_created">Задача создана</option>
                      <option value="task_updated">Задача изменена</option>
                      <option value="task_status_changed">Статус задачи изменен</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="workflowActionCode">Что сделать</label>
                    <select id="workflowActionCode" class="form-select" name="action_code" required>
                      <option value="change_status">Изменить статус задачи</option>
                      <option value="assign_user">Назначить исполнителя</option>
                      <option value="create_comment">Добавить комментарий</option>
                      <option value="send_notification">Отправить уведомление</option>
                      <option value="create_follow_up_task">Создать follow-up подзадачу</option>
                      <option value="create_reminder">Создать напоминание</option>
                      <option value="escalate_sla">Отметить SLA как нарушенное</option>
                      <option value="call_webhook">Вызвать вебхук</option>
                    </select>
                  </div>
                </div>

                <!-- Filter conditions for status trigger -->
                <div class="crm-workflow-action-panel mt-3 d-none" data-filter-panel="task_status_changed">
                  <label class="form-label fw-bold">Условия срабатывания</label>
                  <div class="crm-inline-help mb-2"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Правило сработает только при переходе задачи из одного статуса в другой. Можно не указывать «из» или «в» — тогда правило сработает при любом изменении.</span></div>
                  <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="workflowFromStatus">Из статуса</label><select id="workflowFromStatus" class="form-select" name="from_status_code"><option value="">Любой статус</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="workflowToStatus">В статус</label><select id="workflowToStatus" class="form-select" name="to_status_code"><option value="">Любой статус</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="workflowConditionTag">Тег задачи</label><select id="workflowConditionTag" class="form-select" name="condition_tag_public_id"><option value="">Любой тег</option></select></div>
                  </div>
                </div>

                <!-- Action panels -->
                <div class="crm-workflow-action-panel mt-3 d-none" data-action-panel="change_status">
                  <label class="form-label" for="workflowStatusCode">Новый статус задачи</label>
                  <select id="workflowStatusCode" class="form-select" name="status_code"></select>
                </div>
                <div class="crm-workflow-action-panel mt-3 d-none" data-action-panel="assign_user">
                  <label class="form-label" for="workflowAssigneeUser">Назначить исполнителем</label>
                  <select id="workflowAssigneeUser" class="form-select" name="assignee_user_public_id"></select>
                </div>
                <div class="crm-workflow-action-panel mt-3 d-none" data-action-panel="create_comment">
                  <label class="form-label" for="workflowCommentText">Текст комментария</label>
                  <textarea id="workflowCommentText" class="form-control" name="comment_text" rows="3" placeholder="Например: Проверьте задачу и обновите срок."></textarea>
                </div>
                <div class="crm-workflow-action-panel mt-3 d-none" data-action-panel="send_notification">
                  <label class="form-label" for="workflowNotificationUsers">Кому отправить</label>
                  <select id="workflowNotificationUsers" class="form-select" name="recipient_user_public_ids" multiple></select>
                  <div class="row g-3 mt-1"><div class="col-md-5"><label class="form-label" for="workflowNotificationTitle">Заголовок</label><input id="workflowNotificationTitle" class="form-control" name="notification_title" placeholder="Нужно внимание"></div><div class="col-md-7"><label class="form-label" for="workflowNotificationBody">Сообщение</label><input id="workflowNotificationBody" class="form-control" name="notification_body" placeholder="Проверьте задачу"></div></div>
                </div>
                <div class="crm-workflow-action-panel mt-3 d-none" data-action-panel="create_follow_up_task">
                  <div class="row g-3"><div class="col-md-7"><label class="form-label" for="workflowFollowupTitle">Название подзадачи</label><input id="workflowFollowupTitle" class="form-control" name="task_title" placeholder="Связаться с ответственным"></div><div class="col-md-5"><label class="form-label" for="workflowFollowupAssignee">Исполнитель</label><select id="workflowFollowupAssignee" class="form-select" name="followup_assignee_user_public_id"></select></div></div>
                </div>
                <div class="crm-workflow-action-panel mt-3 d-none" data-action-panel="create_reminder">
                  <div class="row g-3"><div class="col-md-6"><label class="form-label" for="workflowReminderUser">Кому напомнить</label><select id="workflowReminderUser" class="form-select" name="reminder_user_public_id"></select></div><div class="col-md-6"><label class="form-label" for="workflowReminderAt">Когда</label><input id="workflowReminderAt" class="form-control" name="remind_at" placeholder="+1 hour"></div></div>
                  <div class="crm-inline-help mt-2"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Дата-время или понятная запись: +1 hour, +30 minutes, tomorrow 10:00.</span></div>
                </div>
                <div class="crm-workflow-action-panel mt-3 d-none" data-action-panel="call_webhook">
                  <label class="form-label" for="workflowWebhookUrl">URL вебхука</label>
                  <input id="workflowWebhookUrl" class="form-control" name="webhook_url" placeholder="https://example.com/webhook">
                </div>
                <div class="crm-workflow-action-panel mt-3 d-none" data-action-panel="escalate_sla">
                  <div class="crm-inline-help"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>При срабатывании правило отметит задачу как нарушившую SLA.</span></div>
                </div>
                <div class="form-check mt-3"><input class="form-check-input" type="checkbox" value="1" id="workflowRuleEnabled" name="is_enabled" checked><label class="form-check-label" for="workflowRuleEnabled">Правило включено</label></div>
              </div>
              <div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit" id="adminWorkflowSubmitBtn">Создать правило</button></div>
            </form>
          </div>
        </div>
      </div>

      <!-- Test Modal -->
      <div class="modal fade" id="adminWorkflowTestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header"><div><h5 class="modal-title">Проверить правило</h5><div class="crm-modal-subtitle">Выберите задачу — система применит правило к ней и запишет результат в журнал.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>
            <form id="adminWorkflowTestForm">
              <input type="hidden" name="rule_public_id">
              <div class="modal-body"><label class="form-label" for="workflowTestTask">Задача для проверки</label><select id="workflowTestTask" class="form-select" name="task_public_id" required></select><div class="crm-inline-help mt-2"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Правило выполнит реальное действие. Результат появится в журнале.</span></div></div>
              <div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit">Запустить тест</button></div>
            </form>
          </div>
        </div>
      </div>

      <!-- Delete Confirm Modal -->
      <div class="modal fade" id="adminWorkflowDeleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header"><div><h5 class="modal-title">Удалить правило?</h5><div class="crm-modal-subtitle">Это действие нельзя отменить. Все связанные записи в журнале сохранятся.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>
            <div class="modal-body"><p id="adminWorkflowDeleteRuleTitle" class="fw-bold"></p></div>
            <div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-danger" type="button" id="adminWorkflowDeleteConfirmBtn">Удалить</button></div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
