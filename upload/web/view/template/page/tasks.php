<?php declare(strict_types=1); ?>
<?php $title = $t('tasks.title', 'TropaTT — Задачи'); ?>
<body data-page="tasks" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav">
</nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid d-flex align-items-center gap-2"><button class="btn crm-btn-secondary d-xl-none" id="sidebarToggle" aria-label="<?= htmlspecialchars($t('tasks.sidebar_toggle_aria', 'Открыть меню'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="tasks.sidebar_toggle_aria"></button><div class="input-group crm-field-w-420" data-global-search><span class="input-group-text"></span><input id="tasksGlobalSearchInput" class="form-control" placeholder="<?= htmlspecialchars($t('tasks.global_search_placeholder', 'Поиск по задачам'), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($t('tasks.global_search_aria', 'Глобальный поиск по задачам'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="tasks.global_search_placeholder" data-i18n-aria-label="tasks.global_search_aria"></div><div class="ms-auto d-flex gap-2" data-global-actions="1"><button class="btn crm-btn-ghost crm-btn-icon" data-global-notifications data-bs-toggle="popover" data-bs-html="true" data-bs-content="<div class='text-muted small'><?= htmlspecialchars($t('tasks.notifications_loading', 'Загрузка уведомлений...'), ENT_QUOTES, 'UTF-8') ?></div>" aria-label="<?= htmlspecialchars($t('tasks.notifications_aria', 'Уведомления'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="tasks.notifications_aria"></button></div></div></header>
<main class="crm-content crm-tasks-page"><?php crm_page_head([
  ['label' => $t('page.home', 'Главная'), 'href' => 'index.php?route=dashboard'],
  ['label' => $t('tasks.page_title', 'Задачи'), 'active' => true],
], $t('tasks.page_title', 'Задачи'), $t('tasks.subtitle', 'Планирование, контроль сроков и работа с родительскими и дочерними задачами.'), '<button class="btn crm-btn-primary" type="button" data-open-modal="createTaskModal">' . htmlspecialchars($t('tasks.btn_create_task', 'Создать задачу'), ENT_QUOTES, 'UTF-8') . '</button>'); ?>

<div id="tasksAiPriorityCard" data-ai-state="idle"><div class="crm-tasks-local-actions"><div class="btn-group crm-tasks-view-toggle" role="group" aria-label="<?= htmlspecialchars($t('tasks.view_toggle_aria', 'Вид списка задач'), ENT_QUOTES, 'UTF-8') ?>" data-tasks-view-toggle data-i18n-aria-label="tasks.view_toggle_aria"><button class="btn crm-btn-secondary" type="button" data-tasks-view="list" data-i18n="tasks.view_list"><?= htmlspecialchars($t('tasks.view_list', 'Список'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-secondary" type="button" data-tasks-view="tree" data-i18n="tasks.view_tree"><?= htmlspecialchars($t('tasks.view_tree', 'Иерархия'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-secondary" type="button" data-tasks-view="cards" data-i18n="tasks.view_cards"><?= htmlspecialchars($t('tasks.view_cards', 'Карточки'), ENT_QUOTES, 'UTF-8') ?></button></div><a class="btn crm-btn-secondary" href="index.php?route=kanban" data-i18n="tasks.kanban_link"><?= htmlspecialchars($t('tasks.kanban_link', 'Канбан'), ENT_QUOTES, 'UTF-8') ?></a><button id="tasksAiPriorityBtn" class="btn crm-btn-secondary" type="button" data-requires-ai-use="1" title="<?= htmlspecialchars($t('tasks.ai_priority_title', 'Сформировать AI-ранжирование задач по риску и срочности'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="tasks.ai_priority_title" data-i18n="tasks.ai_priority"><?= htmlspecialchars($t('tasks.ai_priority', 'AI-приоритет'), ENT_QUOTES, 'UTF-8') ?></button><button id="tasksAiPriorityResetBtn" class="btn crm-btn-muted d-none" type="button" data-requires-ai-use="1" title="<?= htmlspecialchars($t('tasks.ai_priority_reset_title', 'Вернуть обычный порядок задач'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="tasks.ai_priority_reset_title" data-i18n="tasks.ai_priority_reset"><?= htmlspecialchars($t('tasks.ai_priority_reset', 'Сброс AI-порядка'), ENT_QUOTES, 'UTF-8') ?></button></div>
<div id="tasksAiPriorityState" class="small text-muted mb-2"></div></div>

<div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
  <div class="dropdown" id="savedViewsDropdown">
    <button class="btn crm-btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-i18n="tasks.views_btn">
      <?= htmlspecialchars($t('tasks.views_btn', 'Представления'), ENT_QUOTES, 'UTF-8') ?>
    </button>
    <ul class="dropdown-menu crm-saved-views-menu" aria-label="<?= htmlspecialchars($t('tasks.views_aria', 'Сохранённые представления'), ENT_QUOTES, 'UTF-8') ?>">
      <li><button class="dropdown-item crm-sv-save-btn" type="button" id="savedViewsSaveBtn" data-i18n="tasks.views_save_current"><?= htmlspecialchars($t('tasks.views_save_current', '💾 Сохранить текущие фильтры'), ENT_QUOTES, 'UTF-8') ?></button></li>
      <li><hr class="dropdown-divider my-1"></li>
      <li class="crm-saved-views-list"></li>
    </ul>
  </div>
</div>

<section class="crm-kanban-filters crm-filters-card">
  <div class="crm-kanban-search">
    <label class="crm-filter-label" for="tasksSearchInput" data-i18n="tasks.filter_search"><?= htmlspecialchars($t('tasks.filter_search', 'Поиск'), ENT_QUOTES, 'UTF-8') ?></label>
    <input id="tasksSearchInput" class="form-control" type="search" placeholder="<?= htmlspecialchars($t('tasks.filter_search_placeholder', 'Название, описание или код задачи'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="tasks.filter_search_placeholder">
  </div>
  <div>
    <label class="crm-filter-label" for="tasksAssigneeFilter" data-i18n="tasks.filter_assignee"><?= htmlspecialchars($t('tasks.filter_assignee', 'Исполнитель'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="tasksAssigneeFilter" class="form-select"><option value="" data-i18n="tasks.filter_assignee_placeholder"><?= htmlspecialchars($t('tasks.filter_assignee_placeholder', 'Исполнитель'), ENT_QUOTES, 'UTF-8') ?></option></select>
  </div>
  <div>
    <label class="crm-filter-label" for="tasksManagerFilter" data-i18n="tasks.filter_manager"><?= htmlspecialchars($t('tasks.filter_manager', 'Менеджер'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="tasksManagerFilter" class="form-select"><option value="" data-i18n="tasks.filter_manager_placeholder"><?= htmlspecialchars($t('tasks.filter_manager_placeholder', 'Менеджер'), ENT_QUOTES, 'UTF-8') ?></option></select>
  </div>
  <div>
    <label class="crm-filter-label" for="tasksProjectFilter" data-i18n="tasks.filter_project"><?= htmlspecialchars($t('tasks.filter_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="tasksProjectFilter" class="form-select"><option value="" data-i18n="tasks.filter_all_projects"><?= htmlspecialchars($t('tasks.filter_all_projects', 'Все проекты'), ENT_QUOTES, 'UTF-8') ?></option></select>
  </div>
  <div>
    <label class="crm-filter-label" for="tasksClientFilter" data-i18n="tasks.filter_client"><?= htmlspecialchars($t('tasks.filter_client', 'Клиент'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="tasksClientFilter" class="form-select"><option value="" data-i18n="tasks.filter_all_clients"><?= htmlspecialchars($t('tasks.filter_all_clients', 'Все клиенты'), ENT_QUOTES, 'UTF-8') ?></option></select>
  </div>
  <div>
    <label class="crm-filter-label" for="tasksCycleFilter" data-i18n="tasks.filter_cycle"><?= htmlspecialchars($t('tasks.filter_cycle', 'Цикл'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="tasksCycleFilter" class="form-select"><option value="" data-i18n="tasks.filter_all_cycles"><?= htmlspecialchars($t('tasks.filter_all_cycles', 'Все циклы'), ENT_QUOTES, 'UTF-8') ?></option></select>
  </div>
  <div>
    <label class="crm-filter-label" for="tasksTagFilter" data-i18n="tasks.filter_tag"><?= htmlspecialchars($t('tasks.filter_tag', 'Тег'), ENT_QUOTES, 'UTF-8') ?></label>
    <select id="tasksTagFilter" class="form-select"><option value="" data-i18n="tasks.filter_all_tags"><?= htmlspecialchars($t('tasks.filter_all_tags', 'Все теги'), ENT_QUOTES, 'UTF-8') ?></option></select>
  </div>
  <div class="crm-kanban-due-filters" role="group">
    <button class="btn crm-btn-secondary" type="button" data-kanban-due="overdue" data-i18n="tasks.filter_overdue"><?= htmlspecialchars($t('tasks.filter_overdue', 'Просроченные'), ENT_QUOTES, 'UTF-8') ?></button>
    <button class="btn crm-btn-secondary" type="button" data-kanban-due="today" data-i18n="tasks.filter_today"><?= htmlspecialchars($t('tasks.filter_today', 'Сегодня'), ENT_QUOTES, 'UTF-8') ?></button>
    <button class="btn crm-btn-secondary" type="button" data-kanban-due="week" data-i18n="tasks.filter_week"><?= htmlspecialchars($t('tasks.filter_week', 'На неделе'), ENT_QUOTES, 'UTF-8') ?></button>
  </div>
  <div class="crm-kanban-filter-summary">
    <span id="tasksResultSummary" data-i18n="tasks.result_summary"><?= htmlspecialchars($t('tasks.result_summary', 'Показано 0 из 0 задач'), ENT_QUOTES, 'UTF-8') ?></span>
    <button class="btn crm-btn-secondary" type="button" id="tasksFiltersResetBtn" disabled data-i18n="tasks.filter_reset"><?= htmlspecialchars($t('tasks.filter_reset', 'Сбросить'), ENT_QUOTES, 'UTF-8') ?></button>
  </div>
</section>

<div id="bulkActionsBar" class="alert alert-primary d-none d-flex justify-content-between align-items-center" role="region" aria-label="<?= htmlspecialchars($t('tasks.bulk_aria', 'Bulk actions'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="tasks.bulk_aria">
  <div data-i18n="tasks.bulk_selected"><?= htmlspecialchars($t('tasks.bulk_selected', 'Выбрано задач: '), ENT_QUOTES, 'UTF-8') ?><strong data-selected-count>0</strong> <span class="small ms-2" id="tasksBulkResult" aria-live="polite"></span></div>
  <div class="d-flex gap-2">
    <select id="tasksBulkStatusSelect" class="form-select form-select-sm crm-field-w-170" aria-label="<?= htmlspecialchars($t('tasks.bulk_status_aria', 'Изменить статус'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="tasks.bulk_status_aria">
      <option value="" data-i18n="tasks.bulk_status_placeholder"><?= htmlspecialchars($t('tasks.bulk_status_placeholder', 'Статус...'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="new" data-i18n="tasks.status_new"><?= htmlspecialchars($t('tasks.status_new', 'К выполнению'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="in_progress" data-i18n="tasks.status_in_progress"><?= htmlspecialchars($t('tasks.status_in_progress', 'В работе'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="blocked" data-i18n="tasks.status_blocked"><?= htmlspecialchars($t('tasks.status_blocked', 'Блокировано'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="done" data-i18n="tasks.status_done"><?= htmlspecialchars($t('tasks.status_done', 'Готово'), ENT_QUOTES, 'UTF-8') ?></option>
    </select>
    <select id="tasksBulkPrioritySelect" class="form-select form-select-sm crm-field-w-170" aria-label="<?= htmlspecialchars($t('tasks.bulk_priority_aria', 'Изменить приоритет'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="tasks.bulk_priority_aria">
      <option value="" data-i18n="tasks.bulk_priority_placeholder"><?= htmlspecialchars($t('tasks.bulk_priority_placeholder', 'Приоритет...'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="low" data-i18n="priority.low"><?= htmlspecialchars($t('priority.low', 'Низкий'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="normal" data-i18n="priority.normal"><?= htmlspecialchars($t('priority.normal', 'Нормальный'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="high" data-i18n="priority.high"><?= htmlspecialchars($t('priority.high', 'Высокий'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="urgent" data-i18n="priority.urgent"><?= htmlspecialchars($t('priority.urgent', 'Срочный'), ENT_QUOTES, 'UTF-8') ?></option>
    </select>
    <button class="btn btn-sm btn-light" type="button" data-bulk-archive="1" data-i18n="tasks.bulk_archive"><?= htmlspecialchars($t('tasks.bulk_archive', 'В архив'), ENT_QUOTES, 'UTF-8') ?></button>
    <button class="btn btn-sm btn-light" type="button" data-bulk-unarchive="1" data-i18n="tasks.bulk_unarchive"><?= htmlspecialchars($t('tasks.bulk_unarchive', 'Из архива'), ENT_QUOTES, 'UTF-8') ?></button>
    <button class="btn btn-sm btn-light" data-open-modal="assignUserModal" data-i18n="tasks.bulk_assign"><?= htmlspecialchars($t('tasks.bulk_assign', 'Назначить'), ENT_QUOTES, 'UTF-8') ?></button>
    <button class="btn btn-sm crm-btn-danger-soft" data-confirm-delete data-i18n="tasks.bulk_delete"><?= htmlspecialchars($t('tasks.bulk_delete', 'Удалить'), ENT_QUOTES, 'UTF-8') ?></button>
    <button class="btn btn-sm crm-btn-secondary" type="button" id="tasksBulkHelpBtn" title="<?= htmlspecialchars($t('tasks.bulk_help_title', 'Шорткаты: Shift+A архив, Shift+U из архива, Shift+D удалить'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="tasks.bulk_help_title" data-i18n="tasks.bulk_help"><?= htmlspecialchars($t('tasks.bulk_help', '?'), ENT_QUOTES, 'UTF-8') ?></button>
  </div>
</div>

<section id="tasksStates">
  <div data-state-item="default">
    <div class="table-responsive crm-card p-0" data-tasks-list-view>
      <table class="table table-hover align-middle mb-0 crm-table" data-select-table data-bulk-target="bulkActionsBar"><thead><tr>                        <th style="width:40px"><input class="form-check-input" type="checkbox" data-select-all aria-label="<?= htmlspecialchars($t('tasks.select_all_aria', 'Выбрать все задачи'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="tasks.select_all_aria"></th><th style="width:90px" data-i18n="tasks.th_key"><?= htmlspecialchars($t('tasks.th_key', 'Ключ'), ENT_QUOTES, 'UTF-8') ?></th><th><button type="button" class="btn btn-link p-0 crm-th-sort" data-tasks-sort="title" data-i18n="tasks.th_task"><?= htmlspecialchars($t('tasks.th_task', 'Задача'), ENT_QUOTES, 'UTF-8') ?></button></th><th data-i18n="tasks.th_project"><?= htmlspecialchars($t('tasks.th_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="tasks.th_client"><?= htmlspecialchars($t('tasks.th_client', 'Клиент'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="tasks.th_manager"><?= htmlspecialchars($t('tasks.th_manager', 'Менеджер'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="tasks.th_assignee"><?= htmlspecialchars($t('tasks.th_assignee', 'Исполнитель'), ENT_QUOTES, 'UTF-8') ?></th><th><button type="button" class="btn btn-link p-0 crm-th-sort" data-tasks-sort="due_at" data-i18n="tasks.th_due"><?= htmlspecialchars($t('tasks.th_due', 'Срок'), ENT_QUOTES, 'UTF-8') ?></button></th><th><button type="button" class="btn btn-link p-0 crm-th-sort" data-tasks-sort="status_code" data-i18n="tasks.th_status"><?= htmlspecialchars($t('tasks.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></button></th><th><button type="button" class="btn btn-link p-0 crm-th-sort" data-tasks-sort="priority_code" data-i18n="tasks.th_priority"><?= htmlspecialchars($t('tasks.th_priority', 'Приоритет'), ENT_QUOTES, 'UTF-8') ?></button></th><th style="width:230px" data-i18n="tasks.th_actions"><?= htmlspecialchars($t('tasks.th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody>
        <tr><td colspan="11" class="text-muted" data-i18n="tasks.loading"><?= htmlspecialchars($t('tasks.loading', 'Загрузка задач...'), ENT_QUOTES, 'UTF-8') ?></td></tr>
      </tbody></table>
    </div>
    <div class="crm-tasks-view-surface d-none" data-tasks-tree-view></div>
    <div class="crm-tasks-view-surface d-none" data-tasks-cards-view></div>
  </div>

  <div data-state-item="empty" class="d-none"><div class="crm-empty-state"><strong data-i18n="tasks.empty_title"><?= htmlspecialchars($t('tasks.empty_title', 'Задач пока нет'), ENT_QUOTES, 'UTF-8') ?></strong><p class="mb-3" data-i18n="tasks.empty_text"><?= htmlspecialchars($t('tasks.empty_text', 'Создайте первую задачу, чтобы запустить рабочий поток команды.'), ENT_QUOTES, 'UTF-8') ?></p><button class="btn crm-btn-primary" data-open-modal="createTaskModal" data-i18n="tasks.btn_create_task"><?= htmlspecialchars($t('tasks.btn_create_task', 'Создать задачу'), ENT_QUOTES, 'UTF-8') ?></button></div></div>
  <div data-state-item="no-results" class="d-none"><div class="crm-empty-state"><strong data-i18n="tasks.no_results_title"><?= htmlspecialchars($t('tasks.no_results_title', 'Ничего не найдено'), ENT_QUOTES, 'UTF-8') ?></strong><p class="mb-3" data-i18n="tasks.no_results_text"><?= htmlspecialchars($t('tasks.no_results_text', 'Попробуйте очистить фильтры или изменить поисковый запрос.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
  <div data-state-item="loading" class="d-none"><div class="crm-card p-4"><div class="crm-skeleton mb-2" style="height:16px"></div><div class="crm-skeleton mb-2" style="height:16px"></div><div class="crm-skeleton" style="height:16px"></div></div></div>
  <div data-state-item="error" class="d-none"><div class="crm-empty-state"><strong data-i18n="tasks.error_title"><?= htmlspecialchars($t('tasks.error_title', 'Ошибка загрузки задач'), ENT_QUOTES, 'UTF-8') ?></strong><p class="mb-0" data-i18n="tasks.error_text"><?= htmlspecialchars($t('tasks.error_text', 'Не удалось обновить список. Обновите страницу или повторите позже.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
  <div data-state-item="no-access" class="d-none"><div class="crm-empty-state"><strong data-i18n="tasks.no_access_title"><?= htmlspecialchars($t('tasks.no_access_title', 'Нет доступа к разделу'), ENT_QUOTES, 'UTF-8') ?></strong><p class="mb-3" data-i18n="tasks.no_access_text"><?= htmlspecialchars($t('tasks.no_access_text', 'Запросите права у администратора проекта.'), ENT_QUOTES, 'UTF-8') ?></p><button class="btn crm-btn-subtle" data-bs-toggle="tooltip" title="<?= htmlspecialchars($t('tasks.no_access_tooltip', 'Нужна роль Менеджер или Администратор'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="tasks.no_access_tooltip" data-i18n="tasks.no_access_why"><?= htmlspecialchars($t('tasks.no_access_why', 'Почему недоступно'), ENT_QUOTES, 'UTF-8') ?></button></div></div>
</section>
<div id="tasksPager" class="crm-table-pager d-none" aria-live="polite"></div>

</main></div></div>

<!-- Saved View Create/Edit Modal -->
<div class="modal fade" id="savedViewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" id="savedViewModalTitle" data-i18n="tasks.views_modal_title"><?= htmlspecialchars($t('tasks.views_modal_title', 'Сохранить представление'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('common.close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="common.close_aria"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="savedViewPublicIdInput" value="">
        <div class="mb-3">
          <label for="savedViewNameInput" class="form-label" data-i18n="tasks.views_name_label"><?= htmlspecialchars($t('tasks.views_name_label', 'Название'), ENT_QUOTES, 'UTF-8') ?></label>
          <input id="savedViewNameInput" class="form-control" type="text" maxlength="255" placeholder="<?= htmlspecialchars($t('tasks.views_name_placeholder', 'Мои задачи'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="tasks.views_name_placeholder">
        </div>
        <div class="mb-3">
          <label for="savedViewDescInput" class="form-label" data-i18n="tasks.views_desc_label"><?= htmlspecialchars($t('tasks.views_desc_label', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label>
          <textarea id="savedViewDescInput" class="form-control" rows="2" maxlength="2000" placeholder="<?= htmlspecialchars($t('tasks.views_desc_placeholder', 'Необязательное описание'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="tasks.views_desc_placeholder" data-crm-visual-editor="1" data-richtext-off="1"></textarea>
        </div>
        <div class="mb-3">
          <label for="savedViewAccessSelect" class="form-label" data-i18n="tasks.views_access_label"><?= htmlspecialchars($t('tasks.views_access_label', 'Доступ'), ENT_QUOTES, 'UTF-8') ?></label>
          <select id="savedViewAccessSelect" class="form-select">
            <option value="private" data-i18n="tasks.views_access_private"><?= htmlspecialchars($t('tasks.views_access_private', 'Приватное'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="public" data-i18n="tasks.views_access_public"><?= htmlspecialchars($t('tasks.views_access_public', 'Публичное'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="common.cancel_btn"><?= htmlspecialchars($t('common.cancel_btn', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn crm-btn-primary" id="savedViewModalSaveBtn" data-i18n="tasks.views_save_btn"><?= htmlspecialchars($t('tasks.views_save_btn', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>
