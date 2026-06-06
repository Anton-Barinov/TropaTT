<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Согласования'; ?>
<body data-page="approvals" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-automation-page"><div class="crm-page-head"><div><h1 class="crm-page-title">Согласования</h1><p class="crm-subtitle">Запросы на утверждение задач и проектов без переписок, потерянных решений и ручного контроля.</p></div><div class="d-flex gap-2"><button id="approvalsRefreshBtn" class="btn crm-btn-secondary" type="button"><i class="fa-solid fa-rotate me-1" aria-hidden="true"></i>Обновить</button><button id="approvalsCreateBtn" class="btn crm-btn-primary" type="button"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Создать запрос</button></div></div>

<div class="crm-automation-brief" aria-label="Как работают согласования">
  <div class="crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-file-signature" aria-hidden="true"></i></span><strong>Запрос</strong><p>Сотрудник отправляет задачу или проект на утверждение ответственным людям.</p></div>
  <div class="crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span><strong>Решение</strong><p>Согласующий одобряет или отклоняет запрос, а CRM сохраняет результат в истории.</p></div>
  <div class="crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span><strong>Контроль</strong><p>Доступ к действиям ограничен ролями, поэтому решение принимает только уполномоченный участник.</p></div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Запросы на согласование</h2><div class="crm-section-note">Ожидающие, одобренные и отклонённые решения.</div></div></div>
      <div class="table-responsive"><table class="table table-sm crm-table crm-automation-table mb-0"><thead><tr><th>Запрос</th><th>Сущность</th><th>Запросил</th><th>Статус</th><th>Дата</th><th class="text-end">Действия</th></tr></thead><tbody id="approvalsListBody"><tr><td colspan="6" class="text-muted">Загрузка...</td></tr></tbody></table></div>
    </div>
  </div>
</div>

<div class="modal fade" id="approvalsCreateModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header"><div><h5 class="modal-title">Создать запрос на согласование</h5><div class="crm-modal-subtitle">Запрос будет виден согласующим и сохранит решение в CRM.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div><form id="approvalsCreateForm"><div class="modal-body"><div class="row g-3"><div class="col-md-4"><label class="form-label">Тип сущности</label><select class="form-select" name="entity_type" required><option value="task">Задача</option><option value="project">Проект</option></select></div><div class="col-md-8"><label class="form-label">Public ID задачи или проекта</label><input class="form-control crm-monospace-input" name="entity_public_id" maxlength="64" required placeholder="Например: task_... или project_..."><div class="form-text">ID можно взять из URL карточки или API-ответа.</div></div></div><div class="mt-3"><label class="form-label">Согласующие</label><input class="form-control crm-monospace-input" name="reviewer_public_ids" required placeholder="user_1, user_2"><div class="crm-inline-help mt-2"><i class="fa-solid fa-users" aria-hidden="true"></i><span>Укажите public ID пользователей через запятую. API сохранит список как участников согласования.</span></div></div><div class="mt-3"><label class="form-label">Комментарий к запросу</label><textarea class="form-control" name="comment" maxlength="1000" rows="4" placeholder="Например: проверьте бюджет, сроки и готовность к запуску"></textarea></div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit">Отправить на согласование</button></div></form></div></div></div>

</main></div></div>
