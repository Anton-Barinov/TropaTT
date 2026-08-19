<?php declare(strict_types=1); ?>
<?php $title = $t('intake.title', 'TropaTT — Входящие'); ?>
<body data-page="intake" data-protected="1" data-ajax="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav">
</nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid d-flex align-items-center gap-2"><button class="btn crm-btn-secondary d-xl-none" id="sidebarToggle" aria-label="<?= htmlspecialchars($t('intake.sidebar_toggle_aria', 'Открыть меню'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="intake.sidebar_toggle_aria"></button><div class="ms-auto d-flex gap-2" data-global-actions="1"><button class="btn crm-btn-ghost crm-btn-icon" data-global-notifications data-bs-toggle="popover" data-bs-html="true" data-bs-content="<div class='text-muted small'><?= htmlspecialchars($t('intake.notifications_loading', 'Загрузка уведомлений...'), ENT_QUOTES, 'UTF-8') ?></div>"></button></div></div></header>
<main class="crm-content crm-intake-page"><?php crm_page_head([
  ['label' => $t('page.home', 'Главная'), 'href' => 'index.php?route=dashboard'],
  ['label' => $t('intake.page_title', 'Входящие заявки'), 'active' => true],
], $t('intake.page_title', 'Входящие заявки'), $t('intake.subtitle', 'Фиксация, разбор и приём в работу входящих обращений.'), '<button class="btn crm-btn-primary" type="button" data-intake-create data-bs-toggle="modal" data-bs-target="#intakeCreateModal" data-i18n="intake.btn_create">' . htmlspecialchars($t('intake.btn_create', 'Новая заявка'), ENT_QUOTES, 'UTF-8') . '</button>'); ?>

<section class="crm-intake-filters crm-filters-card">
  <div class="crm-intake-search">
    <label class="crm-filter-label" for="intakeSearchInput" data-i18n="intake.filter_search"><?= htmlspecialchars($t('intake.filter_search', 'Поиск'), ENT_QUOTES, 'UTF-8') ?></label>
    <div class="crm-intake-search-control">
      <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
      <input id="intakeSearchInput" class="form-control" type="search" placeholder="<?= htmlspecialchars($t('intake.filter_search_placeholder', 'Название, описание или код заявки'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="intake.filter_search_placeholder">
      <button class="btn crm-btn-ghost crm-btn-icon d-none" type="button" id="intakeSearchClearBtn" aria-label="<?= htmlspecialchars($t('intake.filter_search_clear', 'Очистить поиск'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="intake.filter_search_clear"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>
  </div>
  <div>
    <label class="crm-filter-label" for="intakeStatusFilter" data-i18n="intake.filter_status"><?= htmlspecialchars($t('intake.filter_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="intakeStatusFilter" class="form-select"><option value="" data-i18n="intake.filter_all_statuses"><?= htmlspecialchars($t('intake.filter_all_statuses', 'Все статусы'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="pending" data-i18n="intake.status_pending"><?= htmlspecialchars($t('intake.status_pending', 'Новая'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="accepted" data-i18n="intake.status_accepted"><?= htmlspecialchars($t('intake.status_accepted', 'Принята'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="rejected" data-i18n="intake.status_rejected"><?= htmlspecialchars($t('intake.status_rejected', 'Отклонена'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="snoozed" data-i18n="intake.status_snoozed"><?= htmlspecialchars($t('intake.status_snoozed', 'Отложена'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="duplicate" data-i18n="intake.status_duplicate"><?= htmlspecialchars($t('intake.status_duplicate', 'Дубликат'), ENT_QUOTES, 'UTF-8') ?></option>
    </select>
  </div>
  <div>
    <label class="crm-filter-label" for="intakeSourceFilter" data-i18n="intake.filter_source"><?= htmlspecialchars($t('intake.filter_source', 'Источник'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="intakeSourceFilter" class="form-select"><option value="" data-i18n="intake.filter_all_sources"><?= htmlspecialchars($t('intake.filter_all_sources', 'Все источники'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="manual" data-i18n="intake.source_manual"><?= htmlspecialchars($t('intake.source_manual', 'Ручной ввод'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="client" data-i18n="intake.source_client"><?= htmlspecialchars($t('intake.source_client', 'Клиент'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="api" data-i18n="intake.source_api"><?= htmlspecialchars($t('intake.source_api', 'API'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="webhook" data-i18n="intake.source_webhook"><?= htmlspecialchars($t('intake.source_webhook', 'Webhook'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="email" data-i18n="intake.source_email"><?= htmlspecialchars($t('intake.source_email', 'Email'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="ai" data-i18n="intake.source_ai"><?= htmlspecialchars($t('intake.source_ai', 'AI'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="import" data-i18n="intake.source_import"><?= htmlspecialchars($t('intake.source_import', 'Импорт'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="system" data-i18n="intake.source_system"><?= htmlspecialchars($t('intake.source_system', 'Система'), ENT_QUOTES, 'UTF-8') ?></option>
    </select>
  </div>
  <div>
    <label class="crm-filter-label" for="intakeProjectFilter" data-i18n="intake.filter_project"><?= htmlspecialchars($t('intake.filter_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="intakeProjectFilter" class="form-select"><option value="" data-i18n="intake.filter_all_projects"><?= htmlspecialchars($t('intake.filter_all_projects', 'Все проекты'), ENT_QUOTES, 'UTF-8') ?></option></select>
  </div>
  <div>
    <label class="crm-filter-label" for="intakePriorityFilter" data-i18n="intake.filter_priority"><?= htmlspecialchars($t('intake.filter_priority', 'Приоритет'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="intakePriorityFilter" class="form-select"><option value="" data-i18n="intake.filter_all_priorities"><?= htmlspecialchars($t('intake.filter_all_priorities', 'Все'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="low" data-i18n="priority.low"><?= htmlspecialchars($t('priority.low', 'Низкий'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="normal" data-i18n="priority.normal"><?= htmlspecialchars($t('priority.normal', 'Нормальный'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="high" data-i18n="priority.high"><?= htmlspecialchars($t('priority.high', 'Высокий'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="urgent" data-i18n="priority.urgent"><?= htmlspecialchars($t('priority.urgent', 'Срочный'), ENT_QUOTES, 'UTF-8') ?></option>
    </select>
  </div>
  <div>
    <label class="crm-filter-label" for="intakeClientFilter" data-i18n="intake.filter_client"><?= htmlspecialchars($t('intake.filter_client', 'Клиент'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="intakeClientFilter" class="form-select"><option value="" data-i18n="intake.filter_all_clients"><?= htmlspecialchars($t('intake.filter_all_clients', 'Все клиенты'), ENT_QUOTES, 'UTF-8') ?></option></select>
  </div>
  <div>
    <label class="crm-filter-label" for="intakeAssigneeFilter" data-i18n="intake.filter_assignee"><?= htmlspecialchars($t('intake.filter_assignee', 'Ответственный'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="intakeAssigneeFilter" class="form-select"><option value="" data-i18n="intake.filter_all_assignees"><?= htmlspecialchars($t('intake.filter_all_assignees', 'Все'), ENT_QUOTES, 'UTF-8') ?></option></select>
  </div>
  <div class="crm-intake-filter-summary">
    <span id="intakeResultSummary" data-i18n="intake.result_summary"><?= htmlspecialchars($t('intake.result_summary', 'Показано 0 из 0 заявок'), ENT_QUOTES, 'UTF-8') ?></span>
    <div class="crm-intake-filter-actions">
      <button class="btn crm-btn-secondary" type="button" id="intakeRefreshBtn" data-i18n="intake.refresh"><i class="fa-solid fa-arrows-rotate me-1" aria-hidden="true"></i><?= htmlspecialchars($t('intake.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn crm-btn-secondary" type="button" id="intakeFiltersResetBtn" disabled data-i18n="intake.filter_reset"><?= htmlspecialchars($t('intake.filter_reset', 'Сбросить'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </div>
</section>

<div class="crm-intake-status-tabs crm-segmented-filter mb-3" id="intakeStatusTabs" role="group" aria-label="<?= htmlspecialchars($t('intake.status_tabs_aria', 'Фильтр по статусу'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="intake.status_tabs_aria"></div>

<div id="intakeBulkBar" class="crm-intake-bulk-bar d-none">
  <span class="crm-intake-bulk-count"><span data-selected-count>0</span> <span data-i18n="intake.bulk_selected"><?= htmlspecialchars($t('intake.bulk_selected', 'выбрано'), ENT_QUOTES, 'UTF-8') ?></span></span>
  <div class="crm-intake-bulk-actions">
    <button class="btn crm-btn-primary" type="button" data-intake-bulk-action="accept" title="<?= htmlspecialchars($t('intake.bulk_accept_title', 'Принять выбранные'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="intake.bulk_accept_title"><i class="fa-solid fa-check me-1" aria-hidden="true"></i><span data-i18n="intake.bulk_accept"><?= htmlspecialchars($t('intake.bulk_accept', 'Принять'), ENT_QUOTES, 'UTF-8') ?></span></button>
    <button class="btn crm-btn-secondary" type="button" data-intake-bulk-action="assign" title="<?= htmlspecialchars($t('intake.bulk_assign_title', 'Назначить выбранные'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="intake.bulk_assign_title"><i class="fa-solid fa-user-pen me-1" aria-hidden="true"></i><span data-i18n="intake.bulk_assign"><?= htmlspecialchars($t('intake.bulk_assign', 'Назначить'), ENT_QUOTES, 'UTF-8') ?></span></button>
    <button class="btn crm-btn-secondary" type="button" data-intake-bulk-action="snooze" title="<?= htmlspecialchars($t('intake.bulk_snooze_title', 'Отложить выбранные'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="intake.bulk_snooze_title"><i class="fa-regular fa-clock me-1" aria-hidden="true"></i><span data-i18n="intake.bulk_snooze"><?= htmlspecialchars($t('intake.bulk_snooze', 'Отложить'), ENT_QUOTES, 'UTF-8') ?></span></button>
    <button class="btn crm-btn-secondary" type="button" data-intake-bulk-action="reject" title="<?= htmlspecialchars($t('intake.bulk_reject_title', 'Отклонить выбранные'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="intake.bulk_reject_title"><i class="fa-solid fa-xmark me-1" aria-hidden="true"></i><span data-i18n="intake.bulk_reject"><?= htmlspecialchars($t('intake.bulk_reject', 'Отклонить'), ENT_QUOTES, 'UTF-8') ?></span></button>
    <button class="btn crm-btn-secondary" type="button" data-intake-bulk-action="reopen" title="<?= htmlspecialchars($t('intake.bulk_reopen_title', 'Вернуть выбранные в работу'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="intake.bulk_reopen_title"><i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i><span data-i18n="intake.bulk_reopen"><?= htmlspecialchars($t('intake.bulk_reopen', 'Восстановить'), ENT_QUOTES, 'UTF-8') ?></span></button>
    <button class="btn crm-btn-danger" type="button" data-intake-bulk-action="delete" title="<?= htmlspecialchars($t('intake.bulk_delete_title', 'Удалить выбранные'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="intake.bulk_delete_title"><i class="fa-solid fa-trash-can me-1" aria-hidden="true"></i><span data-i18n="intake.bulk_delete"><?= htmlspecialchars($t('intake.bulk_delete', 'Удалить'), ENT_QUOTES, 'UTF-8') ?></span></button>
  </div>
</div>

<section id="intakeStates">
  <div data-state-item="default">
    <div class="table-responsive crm-card p-0">
      <table class="table table-hover align-middle mb-0 crm-table"><thead><tr>
        <th style="width:40px"><input class="form-check-input" type="checkbox" data-select-all aria-label="<?= htmlspecialchars($t('intake.select_all_aria', 'Выбрать все заявки'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="intake.select_all_aria"></th>
        <th><button type="button" class="crm-intake-th-sort" data-intake-sort="title" data-i18n="intake.th_title"><?= htmlspecialchars($t('intake.th_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></button></th>
        <th><button type="button" class="crm-intake-th-sort" data-intake-sort="status" data-i18n="intake.th_status"><?= htmlspecialchars($t('intake.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></button></th>
        <th data-i18n="intake.th_project"><?= htmlspecialchars($t('intake.th_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></th>
        <th data-i18n="intake.th_source"><?= htmlspecialchars($t('intake.th_source', 'Источник'), ENT_QUOTES, 'UTF-8') ?></th>
        <th><button type="button" class="crm-intake-th-sort" data-intake-sort="priority_code" data-i18n="intake.th_priority"><?= htmlspecialchars($t('intake.th_priority', 'Приоритет'), ENT_QUOTES, 'UTF-8') ?></button></th>
        <th data-i18n="intake.th_assignee"><?= htmlspecialchars($t('intake.th_assignee', 'Ответственный'), ENT_QUOTES, 'UTF-8') ?></th>
        <th><button type="button" class="crm-intake-th-sort" data-intake-sort="due_at" data-i18n="intake.th_due"><?= htmlspecialchars($t('intake.th_due', 'Срок'), ENT_QUOTES, 'UTF-8') ?></button></th>
        <th><button type="button" class="crm-intake-th-sort" data-intake-sort="created_at" data-i18n="intake.th_created"><?= htmlspecialchars($t('intake.th_created', 'Создано'), ENT_QUOTES, 'UTF-8') ?></button></th>
        <th data-i18n="intake.th_actions"><?= htmlspecialchars($t('intake.th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?></th>
      </tr></thead><tbody id="intakeTableBody">
        <tr><td colspan="10" class="text-muted" data-i18n="intake.loading"><?= htmlspecialchars($t('intake.loading', 'Загрузка заявок...'), ENT_QUOTES, 'UTF-8') ?></td></tr>
      </tbody></table>
    </div>
  </div>
  <div data-state-item="empty" class="d-none"><div class="crm-empty-state"><strong data-i18n="intake.empty_title"><?= htmlspecialchars($t('intake.empty_title', 'Пока нет входящих заявок'), ENT_QUOTES, 'UTF-8') ?></strong><p class="mb-3" data-i18n="intake.empty_text"><?= htmlspecialchars($t('intake.empty_text', 'Создайте первую заявку или подключите источник через API/webhook.'), ENT_QUOTES, 'UTF-8') ?></p><button class="btn crm-btn-primary" data-intake-create data-bs-toggle="modal" data-bs-target="#intakeCreateModal" data-i18n="intake.btn_create"><?= htmlspecialchars($t('intake.btn_create', 'Новая заявка'), ENT_QUOTES, 'UTF-8') ?></button></div></div>
  <div data-state-item="no-results" class="d-none"><div class="crm-empty-state"><strong data-i18n="intake.no_results_title"><?= htmlspecialchars($t('intake.no_results_title', 'Ничего не найдено'), ENT_QUOTES, 'UTF-8') ?></strong><p class="mb-3" data-i18n="intake.no_results_text"><?= htmlspecialchars($t('intake.no_results_text', 'По выбранным фильтрам заявок нет.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
  <div data-state-item="loading" class="d-none"><div class="crm-card p-4"><div class="crm-skeleton mb-2" style="height:16px"></div><div class="crm-skeleton mb-2" style="height:16px"></div><div class="crm-skeleton" style="height:16px"></div></div></div>
  <div data-state-item="error" class="d-none"><div class="crm-empty-state"><strong data-i18n="intake.error_title"><?= htmlspecialchars($t('intake.error_title', 'Ошибка загрузки заявок'), ENT_QUOTES, 'UTF-8') ?></strong><p class="mb-0" data-i18n="intake.error_text"><?= htmlspecialchars($t('intake.error_text', 'Не удалось обновить список. Обновите страницу или повторите позже.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
</section>
<div id="intakePager" class="crm-table-pager d-none"></div>

<!-- Create Modal -->
<div class="modal fade" id="intakeCreateModal" tabindex="-1" data-intake-modal="create"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" data-i18n="intake.modal_create_title"><?= htmlspecialchars($t('intake.modal_create_title', 'Новая заявка'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
  <form id="intakeCreateForm" novalidate>
    <div class="mb-3"><label class="form-label" data-i18n="intake.field_title"><?= htmlspecialchars($t('intake.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?> <span class="text-danger">*</span></label><input class="form-control" name="title" required maxlength="255" placeholder="<?= htmlspecialchars($t('intake.field_title_placeholder', 'Введите название заявки'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="intake.field_title_placeholder"></div>
    <div class="mb-3"><label class="form-label" data-i18n="intake.field_description"><?= htmlspecialchars($t('intake.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="description" rows="4" maxlength="65535" placeholder="<?= htmlspecialchars($t('intake.field_description_placeholder', 'Подробное описание обращения'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="intake.field_description_placeholder" data-crm-visual-editor="1" data-richtext-off="1"></textarea></div>
    <div class="row mb-3">
      <div class="col-md-6"><label class="form-label" data-i18n="intake.field_project"><?= htmlspecialchars($t('intake.field_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="project_public_id"><option value="" data-i18n="intake.field_no_project"><?= htmlspecialchars($t('intake.field_no_project', 'Без проекта'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
      <div class="col-md-6"><label class="form-label" data-i18n="intake.field_client"><?= htmlspecialchars($t('intake.field_client', 'Клиент'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="client_public_id"><option value="" data-i18n="intake.field_no_client"><?= htmlspecialchars($t('intake.field_no_client', 'Без клиента'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
    </div>
    <div class="row mb-3">
      <div class="col-md-4"><label class="form-label" data-i18n="intake.field_priority"><?= htmlspecialchars($t('intake.field_priority', 'Приоритет'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="priority_code"><option value="" data-i18n="intake.field_no_priority"><?= htmlspecialchars($t('intake.field_no_priority', 'Без приоритета'), ENT_QUOTES, 'UTF-8') ?></option><option value="low" data-i18n="priority.low"><?= htmlspecialchars($t('priority.low', 'Низкий'), ENT_QUOTES, 'UTF-8') ?></option><option value="normal" selected data-i18n="priority.normal"><?= htmlspecialchars($t('priority.normal', 'Нормальный'), ENT_QUOTES, 'UTF-8') ?></option><option value="high" data-i18n="priority.high"><?= htmlspecialchars($t('priority.high', 'Высокий'), ENT_QUOTES, 'UTF-8') ?></option><option value="urgent" data-i18n="priority.urgent"><?= htmlspecialchars($t('priority.urgent', 'Срочный'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
      <div class="col-md-4"><label class="form-label" data-i18n="intake.field_source"><?= htmlspecialchars($t('intake.field_source', 'Источник'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="source_type"><option value="manual" selected data-i18n="intake.source_manual"><?= htmlspecialchars($t('intake.source_manual', 'Ручной ввод'), ENT_QUOTES, 'UTF-8') ?></option><option value="client" data-i18n="intake.source_client"><?= htmlspecialchars($t('intake.source_client', 'Клиент'), ENT_QUOTES, 'UTF-8') ?></option><option value="api" data-i18n="intake.source_api"><?= htmlspecialchars($t('intake.source_api', 'API'), ENT_QUOTES, 'UTF-8') ?></option><option value="webhook" data-i18n="intake.source_webhook"><?= htmlspecialchars($t('intake.source_webhook', 'Webhook'), ENT_QUOTES, 'UTF-8') ?></option><option value="email" data-i18n="intake.source_email"><?= htmlspecialchars($t('intake.source_email', 'Email'), ENT_QUOTES, 'UTF-8') ?></option><option value="ai" data-i18n="intake.source_ai"><?= htmlspecialchars($t('intake.source_ai', 'AI'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
      <div class="col-md-4"><label class="form-label" data-i18n="intake.field_assignee"><?= htmlspecialchars($t('intake.field_assignee', 'Ответственный'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="assignee_user_id"><option value="" data-i18n="intake.field_no_assignee"><?= htmlspecialchars($t('intake.field_no_assignee', 'Не назначен'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
    </div>
    <div class="mb-3"><label class="form-label" data-i18n="intake.field_due"><?= htmlspecialchars($t('intake.field_due', 'Желаемый срок'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="due_at" type="datetime-local"></div>
  </form>
</div><div class="modal-footer">
  <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="intake.btn_cancel"><?= htmlspecialchars($t('intake.btn_cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
  <button type="button" class="btn crm-btn-primary" id="intakeCreateSaveBtn" data-i18n="intake.btn_save"><?= htmlspecialchars($t('intake.btn_save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
</div></div></div></div>

<!-- Edit/Detail Modal -->
<div class="modal fade" id="intakeDetailModal" tabindex="-1" data-intake-modal="detail"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="intakeDetailTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="intakeDetailBody">
  <div class="text-center py-4 text-muted" data-i18n="intake.loading_detail"><?= htmlspecialchars($t('intake.loading_detail', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
</div><div class="modal-footer" id="intakeDetailFooter"></div></div></div></div>

<!-- Accept Modal -->
<div class="modal fade" id="intakeAcceptModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" data-i18n="intake.accept_title"><?= htmlspecialchars($t('intake.accept_title', 'Принять заявку в работу'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
  <form id="intakeAcceptForm">
    <p class="text-muted small mb-3" id="intakeAcceptItemTitle"></p>
    <div class="mb-3"><label class="form-label" data-i18n="intake.accept_project"><?= htmlspecialchars($t('intake.accept_project', 'Проект для задачи'), ENT_QUOTES, 'UTF-8') ?> <span class="text-danger">*</span></label><select class="form-select" name="project_public_id" required><option value="" data-i18n="intake.field_no_project"><?= htmlspecialchars($t('intake.field_no_project', 'Без проекта'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
    <div class="mb-3"><label class="form-label" data-i18n="intake.accept_task_title"><?= htmlspecialchars($t('intake.accept_task_title', 'Название задачи'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" maxlength="255"></div>
    <input type="hidden" name="intake_public_id" value="">
  </form>
</div><div class="modal-footer">
  <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="intake.btn_cancel"><?= htmlspecialchars($t('intake.btn_cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
  <button type="button" class="btn crm-btn-primary" id="intakeAcceptConfirmBtn" data-i18n="intake.btn_accept"><?= htmlspecialchars($t('intake.btn_accept', 'Принять'), ENT_QUOTES, 'UTF-8') ?></button>
</div></div></div></div>

<!-- Reject Modal -->
<div class="modal fade" id="intakeRejectModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" data-i18n="intake.reject_title"><?= htmlspecialchars($t('intake.reject_title', 'Отклонить заявку'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
  <form id="intakeRejectForm">
    <div class="mb-3"><label class="form-label" data-i18n="intake.reject_reason"><?= htmlspecialchars($t('intake.reject_reason', 'Причина отклонения'), ENT_QUOTES, 'UTF-8') ?> <span class="text-danger">*</span></label><textarea class="form-control" name="reason" rows="3" required placeholder="<?= htmlspecialchars($t('intake.reject_reason_placeholder', 'Укажите причину'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="intake.reject_reason_placeholder"></textarea></div>
    <input type="hidden" name="intake_public_id" value="">
  </form>
</div><div class="modal-footer">
  <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="intake.btn_cancel"><?= htmlspecialchars($t('intake.btn_cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
  <button type="button" class="btn crm-btn-danger" id="intakeRejectConfirmBtn" data-i18n="intake.btn_reject"><?= htmlspecialchars($t('intake.btn_reject', 'Отклонить'), ENT_QUOTES, 'UTF-8') ?></button>
</div></div></div></div>

<!-- Snooze Modal -->
<div class="modal fade" id="intakeSnoozeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" data-i18n="intake.snooze_title"><?= htmlspecialchars($t('intake.snooze_title', 'Отложить заявку'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
  <form id="intakeSnoozeForm">
    <div class="mb-3"><label class="form-label" data-i18n="intake.snooze_until"><?= htmlspecialchars($t('intake.snooze_until', 'Отложить до'), ENT_QUOTES, 'UTF-8') ?> <span class="text-danger">*</span></label><input class="form-control" name="snoozed_until" type="datetime-local" required></div>
    <div class="mb-3"><label class="form-label" data-i18n="intake.snooze_reason"><?= htmlspecialchars($t('intake.snooze_reason', 'Причина'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="reason" rows="2" placeholder="<?= htmlspecialchars($t('intake.snooze_reason_placeholder', 'Например: ждём ответа от клиента'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="intake.snooze_reason_placeholder"></textarea></div>
    <input type="hidden" name="intake_public_id" value="">
  </form>
</div><div class="modal-footer">
  <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="intake.btn_cancel"><?= htmlspecialchars($t('intake.btn_cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
  <button type="button" class="btn crm-btn-warning" id="intakeSnoozeConfirmBtn" data-i18n="intake.btn_snooze"><?= htmlspecialchars($t('intake.btn_snooze', 'Отложить'), ENT_QUOTES, 'UTF-8') ?></button>
</div></div></div></div>

<!-- Duplicate Modal -->
<div class="modal fade" id="intakeDuplicateModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" data-i18n="intake.duplicate_title"><?= htmlspecialchars($t('intake.duplicate_title', 'Пометить как дубликат'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
  <form id="intakeDuplicateForm">
    <div class="mb-3"><label class="form-label" data-i18n="intake.duplicate_target"><?= htmlspecialchars($t('intake.duplicate_target', 'Дубликат заявки'), ENT_QUOTES, 'UTF-8') ?></label>
      <select class="form-select" name="duplicate_intake_item_public_id"><option value="" data-i18n="intake.duplicate_select_intake"><?= htmlspecialchars($t('intake.duplicate_select_intake', 'Выберите заявку'), ENT_QUOTES, 'UTF-8') ?></option></select>
    </div>
    <div class="mb-3"><label class="form-label" data-i18n="intake.duplicate_task_target"><?= htmlspecialchars($t('intake.duplicate_task_target', 'Дубликат задачи'), ENT_QUOTES, 'UTF-8') ?></label>
      <input class="form-control" name="duplicate_task_public_id" placeholder="<?= htmlspecialchars($t('intake.duplicate_task_placeholder', 'public_id задачи (tsk_...)'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="intake.duplicate_task_placeholder">
    </div>
    <div class="mb-3"><label class="form-label" data-i18n="intake.duplicate_reason"><?= htmlspecialchars($t('intake.duplicate_reason', 'Комментарий'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="reason" rows="2"></textarea></div>
    <input type="hidden" name="intake_public_id" value="">
    <small class="text-muted" data-i18n="intake.duplicate_hint"><?= htmlspecialchars($t('intake.duplicate_hint', 'Выберите заявку или укажите ID задачи.'), ENT_QUOTES, 'UTF-8') ?></small>
  </form>
</div><div class="modal-footer">
  <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="intake.btn_cancel"><?= htmlspecialchars($t('intake.btn_cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
  <button type="button" class="btn crm-btn-secondary" id="intakeDuplicateConfirmBtn" data-i18n="intake.btn_duplicate"><?= htmlspecialchars($t('intake.btn_duplicate', 'Пометить дубликатом'), ENT_QUOTES, 'UTF-8') ?></button>
</div></div></div></div>

<!-- Bulk Action Modal -->
<div class="modal fade" id="intakeBulkModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="intakeBulkModalTitle" data-i18n="intake.bulk_modal_title"><?= htmlspecialchars($t('intake.bulk_modal_title', 'Массовое действие'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
  <form id="intakeBulkForm">
    <p class="text-muted small mb-3" id="intakeBulkSummary"></p>
    <div class="mb-3" data-intake-bulk-field="project"><label class="form-label" data-i18n="intake.accept_project"><?= htmlspecialchars($t('intake.accept_project', 'Проект для задачи'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="project_public_id"><option value="" data-i18n="intake.field_no_project"><?= htmlspecialchars($t('intake.field_no_project', 'Без проекта'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
    <div class="mb-3 d-none" data-intake-bulk-field="assignee"><label class="form-label" data-i18n="intake.field_assignee"><?= htmlspecialchars($t('intake.field_assignee', 'Ответственный'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="assignee_user_id"><option value="" data-i18n="intake.field_no_assignee"><?= htmlspecialchars($t('intake.field_no_assignee', 'Не назначен'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
    <div class="mb-3 d-none" data-intake-bulk-field="reason"><label class="form-label" data-i18n="intake.reject_reason"><?= htmlspecialchars($t('intake.reject_reason', 'Причина отклонения'), ENT_QUOTES, 'UTF-8') ?> <span class="text-danger">*</span></label><textarea class="form-control" name="reason" rows="3" placeholder="<?= htmlspecialchars($t('intake.reject_reason_placeholder', 'Укажите причину'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="intake.reject_reason_placeholder"></textarea></div>
    <div class="mb-3 d-none" data-intake-bulk-field="snooze"><label class="form-label" data-i18n="intake.snooze_until"><?= htmlspecialchars($t('intake.snooze_until', 'Отложить до'), ENT_QUOTES, 'UTF-8') ?> <span class="text-danger">*</span></label><input class="form-control" name="snoozed_until" type="datetime-local"></div>
    <input type="hidden" name="action" value="">
  </form>
</div><div class="modal-footer">
  <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="intake.btn_cancel"><?= htmlspecialchars($t('intake.btn_cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
  <button type="button" class="btn crm-btn-primary" id="intakeBulkConfirmBtn" data-i18n="intake.bulk_apply"><?= htmlspecialchars($t('intake.bulk_apply', 'Применить'), ENT_QUOTES, 'UTF-8') ?></button>
</div></div></div></div>

</main></div></div>
