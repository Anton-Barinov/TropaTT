<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Согласования'; ?>
<body data-page="approvals" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title">Согласования</h1><p class="crm-subtitle">Запросы на согласование: просмотр, одобрение и отклонение.</p></div><div class="d-flex gap-2"><button id="approvalsRefreshBtn" class="btn crm-btn-secondary" type="button">Обновить</button><button id="approvalsCreateBtn" class="btn crm-btn-primary" type="button">Создать запрос</button></div></div>

<div class="alert alert-info mb-3" role="alert">
  Согласования позволяют управлять процессом утверждения изменений в задачах, проектах и других сущностях. Запросы ожидают одобрения или отклонения ответственным лицом.
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Запросы на согласование</h2><div class="crm-section-note">Список всех запросов.</div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th>Название</th><th>Сущность</th><th>Запросил</th><th>Статус</th><th>Дата</th><th></th></tr></thead><tbody id="approvalsListBody"><tr><td colspan="6" class="text-muted">Загрузка...</td></tr></tbody></table>
    </div>
  </div>
</div>

<div class="modal fade" id="approvalsCreateModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Создать запрос на согласование</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div><form id="approvalsCreateForm"><div class="modal-body"><div class="mb-3"><label class="form-label">Тип сущности</label><select class="form-select" name="entity_type" required><option value="task">Задача</option><option value="project">Проект</option></select></div><div class="mb-3"><label class="form-label">ID сущности</label><input class="form-control" name="entity_public_id" maxlength="64" required placeholder="01ABCD..."></div><div class="mb-3"><label class="form-label">Согласующие (ID через запятую)</label><input class="form-control" name="reviewer_public_ids" required placeholder="01USER1, 01USER2"><div class="form-text">Public ID пользователей через запятую</div></div><div class="mb-3"><label class="form-label">Комментарий</label><textarea class="form-control" name="comment" maxlength="1000" rows="3" placeholder="Проверьте задачу перед сдачей"></textarea></div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit">Создать</button></div></form></div></div></div>

</main></div></div>
