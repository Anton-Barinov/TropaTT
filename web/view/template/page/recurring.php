<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Периодические задачи'; ?>
<body data-page="recurring" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title">Периодические задачи</h1><p class="crm-subtitle">Управление задачами, которые создаются автоматически по расписанию.</p></div><div class="d-flex gap-2"><button id="recurringRefreshBtn" class="btn crm-btn-secondary" type="button">Обновить</button><button id="recurringCreateBtn" class="btn crm-btn-primary" type="button">Создать шаблон</button></div></div>

<div class="alert alert-info mb-3" role="alert">
  Периодические задачи создаются автоматически по заданному расписанию. Шаблоны можно приостанавливать и возобновлять.
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Шаблоны периодических задач</h2><div class="crm-section-note">Список всех шаблонов.</div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th>Название</th><th>Расписание</th><th>Следующий запуск</th><th>Статус</th><th></th></tr></thead><tbody id="recurringBody"><tr><td colspan="5" class="text-muted">Загрузка...</td></tr></tbody></table>
    </div>
  </div>
</div>

<div class="modal fade" id="recurringCreateModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Создать шаблон периодической задачи</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div><form id="recurringCreateForm"><div class="modal-body"><div class="mb-3"><label class="form-label">Тип сущности</label><select class="form-select" name="entity_type" required><option value="task">Задача</option><option value="project">Проект</option><option value="reminder">Напоминание</option><option value="calendar_event">Событие календаря</option></select></div><div class="mb-3"><label class="form-label">ID сущности</label><input class="form-control" name="entity_public_id" maxlength="64" required placeholder="01ABCD..."></div><div class="mb-3"><label class="form-label">RRULE (расписание)</label><input class="form-control" name="rrule" maxlength="1000" required placeholder="FREQ=WEEKLY;BYDAY=MO,WE,FR"><div class="form-text">Формат RFC 5545. Примеры: <code>FREQ=DAILY</code>, <code>FREQ=WEEKLY;BYDAY=MO,WE,FR</code>, <code>FREQ=MONTHLY;BYMONTHDAY=1</code></div></div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit">Создать</button></div></form></div></div></div>

</main></div></div>
