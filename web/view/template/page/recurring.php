<?php declare(strict_types=1); ?>
<?php $title = $t('recurring.title', 'TropaTT — Периодические задачи'); ?>
<body data-page="recurring" data-protected="1">
<div class="crm-app">
  <aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
  <div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
  <main class="crm-content crm-automation-page">
    <div class="crm-page-head">
      <div>
        <ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="recurring.breadcrumb_admin"><?= htmlspecialchars($t('recurring.breadcrumb_admin', 'Админка'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="recurring.page_title"><?= htmlspecialchars($t('recurring.page_title', 'Периодические задачи'), ENT_QUOTES, 'UTF-8') ?></li></ol>
        <h1 class="crm-page-title" data-i18n="recurring.page_title"><?= htmlspecialchars($t('recurring.page_title', 'Периодические задачи'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="crm-subtitle" data-i18n="recurring.subtitle"><?= htmlspecialchars($t('recurring.subtitle', 'Шаблоны, по которым CRM сама создаёт повторяющиеся задачи, напоминания и события по расписанию.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div class="d-flex gap-2">
        <button id="recurringRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="recurring.btn_refresh"><i class="fa-solid fa-rotate me-1" aria-hidden="true"></i><?= htmlspecialchars($t('recurring.btn_refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
        <button id="recurringCreateBtn" class="btn crm-btn-primary" type="button" data-i18n="recurring.btn_create"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i><?= htmlspecialchars($t('recurring.btn_create', 'Создать шаблон'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-repeat" aria-hidden="true"></i></span><strong data-i18n="recurring.brief_template_title"><?= htmlspecialchars($t('recurring.brief_template_title', 'Шаблон'), ENT_QUOTES, 'UTF-8') ?></strong><p class="mb-0" data-i18n="recurring.brief_template_text"><?= htmlspecialchars($t('recurring.brief_template_text', 'Выберите задачу, проект, напоминание или событие как образец. Новые экземпляры будут создаваться на его основе с теми же параметрами.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
      <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span><strong data-i18n="recurring.brief_schedule_title"><?= htmlspecialchars($t('recurring.brief_schedule_title', 'Расписание'), ENT_QUOTES, 'UTF-8') ?></strong><p class="mb-0" data-i18n="recurring.brief_schedule_text"><?= htmlspecialchars($t('recurring.brief_schedule_text', 'Каждый день, по будням, раз в неделю, 1-го или 15-го числа месяца — через стандартный RRULE. Или задайте своё.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
      <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-pause" aria-hidden="true"></i></span><strong data-i18n="recurring.brief_manage_title"><?= htmlspecialchars($t('recurring.brief_manage_title', 'Управление'), ENT_QUOTES, 'UTF-8') ?></strong><p class="mb-0" data-i18n="recurring.brief_manage_text"><?= htmlspecialchars($t('recurring.brief_manage_text', 'Любой шаблон можно приостановить, возобновить, изменить или удалить. Исходная задача останется нетронутой.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
    </div>

    <div class="row g-3">
      <div class="col-12">
        <div class="crm-card crm-section-card crm-automation-list-toolbar">
          <div class="crm-section-head">
            <div><h2 class="h6 mb-0" data-i18n="recurring.heading_templates"><?= htmlspecialchars($t('recurring.heading_templates', 'Шаблоны'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" id="recurringCountBadge"></div></div>
            <div class="d-flex gap-2 align-items-center">
              <div class="btn-group btn-group-sm" role="group" id="recurringStatusFilter">
                <button type="button" class="btn crm-btn-secondary active" data-recurring-filter="all" data-i18n="recurring.filter_all"><?= htmlspecialchars($t('recurring.filter_all', 'Все'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="btn crm-btn-secondary" data-recurring-filter="active" data-i18n="recurring.filter_active"><?= htmlspecialchars($t('recurring.filter_active', 'Активные'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="btn crm-btn-secondary" data-recurring-filter="paused" data-i18n="recurring.filter_paused"><?= htmlspecialchars($t('recurring.filter_paused', 'Приостановлены'), ENT_QUOTES, 'UTF-8') ?></button>
              </div>
              <input type="search" id="recurringSearchInput" class="form-control form-control-sm" placeholder="<?= htmlspecialchars($t('recurring.placeholder_search', 'Поиск...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="recurring.placeholder_search" style="max-width:200px">
            </div>
          </div>
        </div>
        <div class="crm-card crm-section-card p-0 table-responsive crm-automation-table-card"><table class="table table-hover align-middle crm-table crm-automation-table mb-0"><thead><tr><th data-i18n="recurring.th_template"><?= htmlspecialchars($t('recurring.th_template', 'Шаблон'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="recurring.th_entity"><?= htmlspecialchars($t('recurring.th_entity', 'Сущность'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="recurring.th_schedule"><?= htmlspecialchars($t('recurring.th_schedule', 'Расписание'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="recurring.th_next_run"><?= htmlspecialchars($t('recurring.th_next_run', 'След. запуск'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="recurring.th_status"><?= htmlspecialchars($t('recurring.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th class="text-end" data-i18n="recurring.th_actions"><?= htmlspecialchars($t('recurring.th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
        <tbody id="recurringBody"><tr><td colspan="6" class="text-muted" data-i18n="recurring.loading"><?= htmlspecialchars($t('recurring.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div class="modal fade" id="recurringCreateModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header"><div><h5 class="modal-title" id="recurringModalTitle" data-i18n="recurring.modal_create_title"><?= htmlspecialchars($t('recurring.modal_create_title', 'Создать шаблон'), ENT_QUOTES, 'UTF-8') ?></h5><div class="crm-modal-subtitle" data-i18n="recurring.modal_create_subtitle"><?= htmlspecialchars($t('recurring.modal_create_subtitle', 'Выберите исходную сущность и задайте расписание повторения.'), ENT_QUOTES, 'UTF-8') ?></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('recurring.modal_close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="recurring.modal_close_aria"></button></div>
          <form id="recurringCreateForm">
            <input type="hidden" name="public_id">
            <div class="modal-body">
              <div class="mb-3"><label class="form-label" data-i18n="recurring.modal_field_title"><?= htmlspecialchars($t('recurring.modal_field_title', 'Название шаблона'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" maxlength="255" required placeholder="<?= htmlspecialchars($t('recurring.modal_placeholder_title', 'Например: Еженедельный отчёт по проектам'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="recurring.modal_placeholder_title"><div class="form-text" data-i18n="recurring.modal_title_hint"><?= htmlspecialchars($t('recurring.modal_title_hint', 'По названию вы сможете быстро найти шаблон в списке.'), ENT_QUOTES, 'UTF-8') ?></div></div>
              <div class="row g-3">
                <div class="col-md-4"><label class="form-label" data-i18n="recurring.modal_field_entity_type"><?= htmlspecialchars($t('recurring.modal_field_entity_type', 'Что повторять'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="entity_type" id="recurringEntityType" required><option value="task" data-i18n="recurring.opt_task"><?= htmlspecialchars($t('recurring.opt_task', 'Задачу'), ENT_QUOTES, 'UTF-8') ?></option><option value="project" data-i18n="recurring.opt_project"><?= htmlspecialchars($t('recurring.opt_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></option><option value="reminder" data-i18n="recurring.opt_reminder"><?= htmlspecialchars($t('recurring.opt_reminder', 'Напоминание'), ENT_QUOTES, 'UTF-8') ?></option><option value="calendar_event" data-i18n="recurring.opt_calendar_event"><?= htmlspecialchars($t('recurring.opt_calendar_event', 'Событие календаря'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
                <div class="col-md-8 position-relative"><label class="form-label" for="recurringEntitySearch" id="recurringEntitySearchLabel" data-i18n="recurring.modal_field_entity_search"><?= htmlspecialchars($t('recurring.modal_field_entity_search', 'Поиск задачи'), ENT_QUOTES, 'UTF-8') ?></label><input id="recurringEntitySearch" class="form-control" placeholder="<?= htmlspecialchars($t('recurring.modal_placeholder_entity_search', 'Введите название задачи или проекта...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="recurring.modal_placeholder_entity_search" autocomplete="off"><div id="recurringEntityResults" class="crm-autocomplete-list d-none"></div><input type="hidden" name="entity_public_id"></div>
              </div>
              <div class="mt-3">
                <label class="form-label" data-i18n="recurring.modal_field_schedule"><?= htmlspecialchars($t('recurring.modal_field_schedule', 'Расписание'), ENT_QUOTES, 'UTF-8') ?></label>
                <div class="crm-schedule-grid" role="group" aria-label="<?= htmlspecialchars($t('recurring.modal_schedule_aria', 'Быстрое расписание'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="recurring.modal_schedule_aria">
                  <button class="crm-schedule-option is-active" type="button" data-rrule-preset="FREQ=DAILY"><strong data-i18n="recurring.schedule_daily"><?= htmlspecialchars($t('recurring.schedule_daily', 'Каждый день'), ENT_QUOTES, 'UTF-8') ?></strong><span data-i18n="recurring.schedule_daily_desc"><?= htmlspecialchars($t('recurring.schedule_daily_desc', 'Для ежедневных проверок и рутины'), ENT_QUOTES, 'UTF-8') ?></span></button>
                  <button class="crm-schedule-option" type="button" data-rrule-preset="FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR"><strong data-i18n="recurring.schedule_weekdays"><?= htmlspecialchars($t('recurring.schedule_weekdays', 'По будням'), ENT_QUOTES, 'UTF-8') ?></strong><span data-i18n="recurring.schedule_weekdays_desc"><?= htmlspecialchars($t('recurring.schedule_weekdays_desc', 'С понедельника по пятницу'), ENT_QUOTES, 'UTF-8') ?></span></button>
                  <button class="crm-schedule-option" type="button" data-rrule-preset="FREQ=WEEKLY;BYDAY=MO,WE,FR"><strong data-i18n="recurring.schedule_mon_wed_fri"><?= htmlspecialchars($t('recurring.schedule_mon_wed_fri', 'Пн, ср, пт'), ENT_QUOTES, 'UTF-8') ?></strong><span data-i18n="recurring.schedule_mon_wed_fri_desc"><?= htmlspecialchars($t('recurring.schedule_mon_wed_fri_desc', 'Три раза в неделю'), ENT_QUOTES, 'UTF-8') ?></span></button>
                  <button class="crm-schedule-option" type="button" data-rrule-preset="FREQ=WEEKLY;INTERVAL=2"><strong data-i18n="recurring.schedule_biweekly"><?= htmlspecialchars($t('recurring.schedule_biweekly', 'Раз в 2 недели'), ENT_QUOTES, 'UTF-8') ?></strong><span data-i18n="recurring.schedule_biweekly_desc"><?= htmlspecialchars($t('recurring.schedule_biweekly_desc', 'Для регулярных ревью'), ENT_QUOTES, 'UTF-8') ?></span></button>
                  <button class="crm-schedule-option" type="button" data-rrule-preset="FREQ=MONTHLY;BYMONTHDAY=1"><strong data-i18n="recurring.schedule_monthly_1st"><?= htmlspecialchars($t('recurring.schedule_monthly_1st', '1-го числа'), ENT_QUOTES, 'UTF-8') ?></strong><span data-i18n="recurring.schedule_monthly_1st_desc"><?= htmlspecialchars($t('recurring.schedule_monthly_1st_desc', 'Начало месяца'), ENT_QUOTES, 'UTF-8') ?></span></button>
                  <button class="crm-schedule-option" type="button" data-rrule-preset="FREQ=MONTHLY;BYMONTHDAY=15"><strong data-i18n="recurring.schedule_monthly_15th"><?= htmlspecialchars($t('recurring.schedule_monthly_15th', '15-го числа'), ENT_QUOTES, 'UTF-8') ?></strong><span data-i18n="recurring.schedule_monthly_15th_desc"><?= htmlspecialchars($t('recurring.schedule_monthly_15th_desc', 'Середина месяца'), ENT_QUOTES, 'UTF-8') ?></span></button>
                </div>
              </div>
              <details class="crm-advanced-details mt-3">
                <summary data-i18n="recurring.modal_advanced_schedule"><?= htmlspecialchars($t('recurring.modal_advanced_schedule', 'Расширенное расписание'), ENT_QUOTES, 'UTF-8') ?></summary>
                <label class="form-label mt-2" data-i18n="recurring.modal_field_rrule"><?= htmlspecialchars($t('recurring.modal_field_rrule', 'RRULE'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control crm-monospace-input" name="rrule" maxlength="255" required value="FREQ=DAILY" placeholder="FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR">
                <div class="form-text" data-i18n="recurring.modal_rrule_hint"><?= htmlspecialchars($t('recurring.modal_rrule_hint', 'Для редких нестандартных сценариев. Обычно достаточно выбрать один из вариантов выше.'), ENT_QUOTES, 'UTF-8') ?></div>
              </details>
            </div>
            <div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" type="submit" id="recurringSubmitBtn" data-i18n="recurring.modal_btn_create"><?= htmlspecialchars($t('recurring.modal_btn_create', 'Создать шаблон'), ENT_QUOTES, 'UTF-8') ?></button></div>
          </form>
        </div>
      </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div class="modal fade" id="recurringDeleteModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><div><h5 class="modal-title" data-i18n="recurring.modal_delete_title"><?= htmlspecialchars($t('recurring.modal_delete_title', 'Удалить шаблон?'), ENT_QUOTES, 'UTF-8') ?></h5><div class="crm-modal-subtitle" data-i18n="recurring.modal_delete_subtitle"><?= htmlspecialchars($t('recurring.modal_delete_subtitle', 'Это действие нельзя отменить. Созданные по шаблону задачи останутся.'), ENT_QUOTES, 'UTF-8') ?></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('recurring.modal_close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="recurring.modal_close_aria"></button></div><div class="modal-body"><p id="recurringDeleteTemplateTitle" class="fw-bold"></p></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-danger" type="button" id="recurringDeleteConfirmBtn" data-i18n="page.delete"><?= htmlspecialchars($t('page.delete', 'Удалить'), ENT_QUOTES, 'UTF-8') ?></button></div></div></div></div>

  </main></div></div>
</body>
