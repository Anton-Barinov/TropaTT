<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Workflow Rules'; ?>
<body data-page="admin-workflow" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title">Workflow Rules</h1><p class="crm-subtitle">Правила автоматизации: создание, редактирование, тестирование и просмотр логов выполнения.</p></div><div class="d-flex gap-2"><button id="adminWorkflowRefreshBtn" class="btn crm-btn-secondary" type="button">Обновить</button><button id="adminWorkflowCreateBtn" class="btn crm-btn-primary" type="button">Создать правило</button></div></div>

<div class="alert alert-info mb-3" role="alert">
  Workflow rules автоматизируют действия при изменении задач, проектов и других сущностей. Правила выполняются асинхронно.
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Правила</h2><div class="crm-section-note">Список всех правил автоматизации.</div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th>Название</th><th>Сущность</th><th>Событие</th><th>Действие</th><th>Статус</th><th></th></tr></thead><tbody id="adminWorkflowRulesBody"><tr><td colspan="6" class="text-muted">Загрузка...</td></tr></tbody></table>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Логи выполнения</h2><div class="crm-section-note">Последние выполнения правил.</div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th>Правило</th><th>Сущность</th><th>Результат</th><th>Дата</th></tr></thead><tbody id="adminWorkflowLogsBody"><tr><td colspan="4" class="text-muted">Загрузка...</td></tr></tbody></table>
    </div>
  </div>
</div>

<div class="modal fade" id="adminWorkflowCreateModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Создать правило автоматизации</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div><form id="adminWorkflowCreateForm"><div class="modal-body"><div class="mb-3"><label class="form-label">Название правила</label><input class="form-control" name="title" maxlength="255" required placeholder="Назначить ответственного при создании"></div><div class="mb-3"><label class="form-label">Триггер</label><select class="form-select" name="trigger_code" required><option value="task_created">Задача создана</option><option value="task_updated">Задача изменена</option><option value="task_status_changed">Статус задачи изменён</option><option value="comment_added">Добавлен комментарий</option><option value="file_uploaded">Загружен файл</option><option value="deadline_reached">Наступил дедлайн</option><option value="project_archived">Проект архивирован</option><option value="user_created">Создан пользователь</option></select></div><div class="mb-3"><label class="form-label">Действие</label><select class="form-select" name="action_code" required><option value="change_status">Изменить статус</option><option value="assign_user">Назначить исполнителя</option><option value="create_comment">Добавить комментарий</option><option value="send_notification">Отправить уведомление</option><option value="create_follow_up_task">Создать подзадачу</option><option value="call_webhook">Вызвать вебхук</option><option value="escalate_sla">Эскалировать SLA</option><option value="create_reminder">Создать напоминание</option></select></div><div class="mb-3"><label class="form-label">Параметры (JSON)</label><textarea class="form-control" name="payload" rows="4" placeholder='{"assignee_user_id": "01USER1"}'></textarea><div class="form-text">Опциональный JSON с параметрами действия</div></div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit">Создать</button></div></form></div></div></div>

</main></div></div>
