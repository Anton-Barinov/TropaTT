<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Правила автоматизации'; ?>
<body data-page="admin-workflow" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-automation-page"><div class="crm-page-head"><div><h1 class="crm-page-title">Правила автоматизации</h1><p class="crm-subtitle">Условия и действия, которые помогают CRM менять статусы, уведомлять команду и запускать процессы без ручной памяти.</p></div><div class="d-flex gap-2"><button id="adminWorkflowRefreshBtn" class="btn crm-btn-secondary" type="button"><i class="fa-solid fa-rotate me-1" aria-hidden="true"></i>Обновить</button><button id="adminWorkflowCreateBtn" class="btn crm-btn-primary" type="button"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Создать правило</button></div></div>

<div class="crm-automation-brief" aria-label="Как работают правила автоматизации">
  <div class="crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-bolt" aria-hidden="true"></i></span><strong>Триггер</strong><p>Правило реагирует на создание задачи, смену статуса, комментарий, файл или дедлайн.</p></div>
  <div class="crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></span><strong>Действие</strong><p>CRM может назначить исполнителя, поменять статус, создать напоминание или отправить уведомление.</p></div>
  <div class="crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i></span><strong>Аудит</strong><p>Каждый запуск фиксируется в логах, чтобы было понятно, что сработало и когда.</p></div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Правила</h2><div class="crm-section-note">Активные и отключённые автоматизации.</div></div></div>
      <div class="table-responsive"><table class="table table-sm crm-table crm-automation-table mb-0"><thead><tr><th>Название</th><th>Сущность</th><th>Событие</th><th>Действие</th><th>Статус</th><th class="text-end">Действия</th></tr></thead><tbody id="adminWorkflowRulesBody"><tr><td colspan="6" class="text-muted">Загрузка...</td></tr></tbody></table></div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Логи выполнения</h2><div class="crm-section-note">Последние выполнения правил.</div></div></div>
      <div class="table-responsive"><table class="table table-sm crm-table crm-automation-table mb-0"><thead><tr><th>Правило</th><th>Сущность</th><th>Результат</th><th>Дата</th></tr></thead><tbody id="adminWorkflowLogsBody"><tr><td colspan="4" class="text-muted">Загрузка...</td></tr></tbody></table></div>
    </div>
  </div>
</div>

<div class="modal fade" id="adminWorkflowCreateModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header"><div><h5 class="modal-title">Создать правило автоматизации</h5><div class="crm-modal-subtitle">Соберите правило из события и действия. Сложные параметры можно передать JSON-ом.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div><form id="adminWorkflowCreateForm"><div class="modal-body"><div class="mb-3"><label class="form-label">Название правила</label><input class="form-control" name="title" maxlength="255" required placeholder="Например: назначить ответственного при создании задачи"></div><div class="row g-3"><div class="col-md-6"><label class="form-label">Когда сработает</label><select class="form-select" name="trigger_code" required><option value="task_created">Задача создана</option><option value="task_updated">Задача изменена</option><option value="task_status_changed">Статус задачи изменён</option><option value="comment_added">Добавлен комментарий</option><option value="file_uploaded">Загружен файл</option><option value="deadline_reached">Наступил дедлайн</option><option value="project_archived">Проект архивирован</option><option value="user_created">Создан пользователь</option></select></div><div class="col-md-6"><label class="form-label">Что сделать</label><select class="form-select" name="action_code" required><option value="change_status">Изменить статус</option><option value="assign_user">Назначить исполнителя</option><option value="create_comment">Добавить комментарий</option><option value="send_notification">Отправить уведомление</option><option value="create_follow_up_task">Создать подзадачу</option><option value="call_webhook">Вызвать вебхук</option><option value="escalate_sla">Эскалировать SLA</option><option value="create_reminder">Создать напоминание</option></select></div></div><div class="mt-3"><label class="form-label">Шаблоны параметров</label><div class="crm-preset-row" role="group" aria-label="Шаблоны payload"><button class="btn crm-btn-subtle crm-preset-btn" type="button" data-workflow-payload='{"status":"in_progress"}'>Сменить статус</button><button class="btn crm-btn-subtle crm-preset-btn" type="button" data-workflow-payload='{"assignee_user_id":"user_..."}'>Назначить</button><button class="btn crm-btn-subtle crm-preset-btn" type="button" data-workflow-payload='{"message":"Проверьте задачу"}'>Уведомить</button><button class="btn crm-btn-subtle crm-preset-btn" type="button" data-workflow-payload='{"url":"https://example.com/webhook","method":"POST"}'>Webhook</button></div></div><div class="mt-3"><label class="form-label">Параметры действия</label><textarea class="form-control crm-monospace-input" name="payload" rows="5" placeholder='{"assignee_user_id": "user_..."}'></textarea><div class="crm-inline-help mt-2"><i class="fa-solid fa-code" aria-hidden="true"></i><span>Поле необязательное. Если действие не требует настроек, оставьте его пустым.</span></div></div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit">Создать правило</button></div></form></div></div></div>

</main></div></div>
