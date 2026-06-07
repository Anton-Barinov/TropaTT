<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Периодические задачи'; ?>
<body data-page="recurring" data-protected="1">
<div class="crm-app">
  <aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
  <div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
  <main class="crm-content crm-automation-page">
    <div class="crm-page-head">
      <div>
        <ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin">Админка</a></li><li class="breadcrumb-item active">Периодические задачи</li></ol>
        <h1 class="crm-page-title">Периодические задачи</h1>
        <p class="crm-subtitle">Шаблоны, по которым CRM сама создаёт повторяющиеся задачи, напоминания и события по расписанию.</p>
      </div>
      <div class="d-flex gap-2">
        <button id="recurringRefreshBtn" class="btn crm-btn-secondary" type="button"><i class="fa-solid fa-rotate me-1" aria-hidden="true"></i>Обновить</button>
        <button id="recurringCreateBtn" class="btn crm-btn-primary" type="button"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Создать шаблон</button>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-repeat" aria-hidden="true"></i></span><strong>Шаблон</strong><p class="mb-0">Выберите задачу, проект, напоминание или событие как образец. Новые экземпляры будут создаваться на его основе с теми же параметрами.</p></div></div>
      <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span><strong>Расписание</strong><p class="mb-0">Каждый день, по будням, раз в неделю, 1-го или 15-го числа месяца — через стандартный RRULE. Или задайте своё.</p></div></div>
      <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-pause" aria-hidden="true"></i></span><strong>Управление</strong><p class="mb-0">Любой шаблон можно приостановить, возобновить, изменить или удалить. Исходная задача останется нетронутой.</p></div></div>
    </div>

    <div class="row g-3">
      <div class="col-12">
        <div class="crm-card crm-section-card">
          <div class="crm-section-head">
            <div><h2 class="h6 mb-0">Шаблоны</h2><div class="crm-section-note" id="recurringCountBadge"></div></div>
            <div class="d-flex gap-2 align-items-center">
              <div class="btn-group btn-group-sm" role="group" id="recurringStatusFilter">
                <button type="button" class="btn crm-btn-secondary active" data-recurring-filter="all">Все</button>
                <button type="button" class="btn crm-btn-secondary" data-recurring-filter="active">Активные</button>
                <button type="button" class="btn crm-btn-secondary" data-recurring-filter="paused">Приостановлены</button>
              </div>
              <input type="search" id="recurringSearchInput" class="form-control form-control-sm" placeholder="Поиск..." style="max-width:200px">
            </div>
          </div>
          <div class="table-responsive"><table class="table table-sm crm-table crm-automation-table mb-0"><thead><tr><th>Шаблон</th><th>Сущность</th><th>Расписание</th><th>След. запуск</th><th>Статус</th><th class="text-end">Действия</th></tr></thead>
          <tbody id="recurringBody"><tr><td colspan="6" class="text-muted">Загрузка...</td></tr></tbody></table></div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div class="modal fade" id="recurringCreateModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header"><div><h5 class="modal-title" id="recurringModalTitle">Создать шаблон</h5><div class="crm-modal-subtitle">Выберите исходную сущность и задайте расписание повторения.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div><form id="recurringCreateForm"><input type="hidden" name="public_id"><div class="modal-body"><div class="mb-3"><label class="form-label">Название шаблона</label><input class="form-control" name="title" maxlength="255" required placeholder="Например: Еженедельный отчёт по проектам"></div><div class="row g-3"><div class="col-md-4"><label class="form-label">Тип сущности</label><select class="form-select" name="entity_type" id="recurringEntityType" required><option value="task">Задача</option><option value="project">Проект</option><option value="reminder">Напоминание</option><option value="calendar_event">Событие календаря</option></select></div><div class="col-md-8 position-relative"><label class="form-label" for="recurringEntitySearch">Поиск задачи или проекта</label><input id="recurringEntitySearch" class="form-control" placeholder="Введите название или public_id..." autocomplete="off"><div id="recurringEntityResults" class="crm-autocomplete-list d-none"></div><input type="hidden" name="entity_public_id"></div></div><div class="mt-3"><label class="form-label">Быстрое расписание</label><div class="d-flex flex-wrap gap-1" role="group"><button class="btn btn-sm crm-btn-secondary crm-preset-btn" type="button" data-rrule-preset="FREQ=DAILY">Каждый день</button><button class="btn btn-sm crm-btn-secondary crm-preset-btn" type="button" data-rrule-preset="FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR">По будням</button><button class="btn btn-sm crm-btn-secondary crm-preset-btn" type="button" data-rrule-preset="FREQ=WEEKLY;BYDAY=MO,WE,FR">Пн/ср/пт</button><button class="btn btn-sm crm-btn-secondary crm-preset-btn" type="button" data-rrule-preset="FREQ=WEEKLY;INTERVAL=2">Раз в 2 недели</button><button class="btn btn-sm crm-btn-secondary crm-preset-btn" type="button" data-rrule-preset="FREQ=MONTHLY;BYMONTHDAY=1">1-го числа</button><button class="btn btn-sm crm-btn-secondary crm-preset-btn" type="button" data-rrule-preset="FREQ=MONTHLY;BYMONTHDAY=15">15-го числа</button></div></div><div class="mt-2"><label class="form-label">RRULE (или своё значение)</label><input class="form-control crm-monospace-input" name="rrule" maxlength="255" required placeholder="FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR"><div class="form-text">Стандартный формат iCalendar RRULE. Можно указать свою строку.</div></div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit" id="recurringSubmitBtn">Создать шаблон</button></div></form></div></div></div>

    <!-- Delete Confirm Modal -->
    <div class="modal fade" id="recurringDeleteModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><div><h5 class="modal-title">Удалить шаблон?</h5><div class="crm-modal-subtitle">Это действие нельзя отменить. Созданные по шаблону задачи останутся.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div><div class="modal-body"><p id="recurringDeleteTemplateTitle" class="fw-bold"></p></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-danger" type="button" id="recurringDeleteConfirmBtn">Удалить</button></div></div></div></div>

  </main></div></div>
</body>
