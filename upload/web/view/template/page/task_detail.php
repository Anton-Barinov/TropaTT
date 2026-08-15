<?php declare(strict_types=1); ?>
<?php $title = $t('task_detail.title', 'TropaTT — Карточка задачи'); ?>
<body data-page="tasks" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav">
</nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid d-flex align-items-center gap-2"><button class="btn crm-btn-secondary d-xl-none" id="sidebarToggle" aria-label="<?= htmlspecialchars($t('task_detail.sidebar_toggle_aria', 'Открыть меню'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="task_detail.sidebar_toggle_aria"></button><div class="input-group crm-field-w-420" data-global-search><span class="input-group-text"></span><input id="taskDetailGlobalSearchInput" class="form-control" placeholder="<?= htmlspecialchars($t('task_detail.global_search_placeholder', 'Поиск'), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($t('task_detail.global_search_aria', 'Глобальный поиск'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="task_detail.global_search_placeholder" data-i18n-aria-label="task_detail.global_search_aria"></div><div class="ms-auto d-flex gap-2" data-global-actions="1"></div></div></header>
<main class="crm-content crm-task-detail-page">
<div class="crm-page-head crm-task-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=tasks" data-i18n="task_detail.breadcrumb_tasks"><?= htmlspecialchars($t('task_detail.breadcrumb_tasks', 'Задачи'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="task_detail.breadcrumb_current"><?= htmlspecialchars($t('task_detail.breadcrumb_current', 'Карточка задачи'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" data-i18n="task_detail.loading_title"><?= htmlspecialchars($t('task_detail.loading_title', 'Загрузка задачи...'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="task_detail.loading_subtitle"><?= htmlspecialchars($t('task_detail.loading_subtitle', 'Загрузка параметров задачи...'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="crm-page-actions crm-task-page-actions"><button class="btn crm-btn-primary d-none" id="taskEditBtn" type="button" data-open-modal="editTaskModal" data-i18n="task_detail.btn_edit"><?= htmlspecialchars($t('task_detail.btn_edit', 'Редактировать'), ENT_QUOTES, 'UTF-8') ?></button><div class="dropdown" data-task-actions-menu><button class="btn crm-btn-secondary crm-task-actions-menu" type="button" id="taskActionsMenuBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= htmlspecialchars($t('common.more', 'Ещё'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="common.more"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button><ul class="dropdown-menu dropdown-menu-end" aria-labelledby="taskActionsMenuBtn"><li><button class="dropdown-item text-danger" type="button" data-confirm-delete data-i18n="task_detail.btn_delete"><?= htmlspecialchars($t('task_detail.btn_delete', 'Удалить'), ENT_QUOTES, 'UTF-8') ?></button></li></ul></div></div></div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="crm-card mb-3 crm-task-hero">
      <div class="crm-task-hero-top">
        <div class="d-flex flex-wrap gap-2 mb-3" id="taskMetaChips"><span id="taskStatusBadge" class="crm-badge overdue" data-i18n="task_detail.status_overdue"><?= htmlspecialchars($t('task_detail.status_overdue', 'Просрочено'), ENT_QUOTES, 'UTF-8') ?></span><span class="crm-chip" id="taskPriorityChip" data-i18n="task_detail.priority_normal"><?= htmlspecialchars($t('task_detail.priority_normal', 'Обычный'), ENT_QUOTES, 'UTF-8') ?></span></div>
      </div>
      <div class="crm-task-hero-progress-wrap">
        <div class="crm-task-hero-progress-head">
          <div>
            <div class="crm-task-eyebrow" data-i18n="task_detail.progress_label"><?= htmlspecialchars($t('task_detail.progress_label', 'Прогресс выполнения'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="crm-task-progress-copy" id="taskProgressHint" data-i18n="task_detail.progress_hint"><?= htmlspecialchars($t('task_detail.progress_hint', 'Прогресс рассчитывается по текущему статусу.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
        <div class="progress mb-0 crm-task-progress" role="progressbar" aria-label="<?= htmlspecialchars($t('task_detail.progress_aria', 'Прогресс'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="task_detail.progress_aria"><div class="progress-bar" id="taskProgressBar" style="width: 0%">0%</div></div>
      </div>
      <section class="crm-task-description-summary" aria-labelledby="taskDescriptionSummaryTitle">
        <div class="crm-task-description-summary-head">
          <h2 class="h6 mb-0" id="taskDescriptionSummaryTitle" data-i18n="task_detail.desc_title"><?= htmlspecialchars($t('task_detail.desc_title', 'Контекст и критерии готовности'), ENT_QUOTES, 'UTF-8') ?></h2>
          <button type="button" class="btn btn-sm crm-inline-icon-btn" data-task-inline-toggle="description" aria-label="<?= htmlspecialchars($t('task_detail.desc_edit_aria', 'Редактировать описание'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="task_detail.desc_edit_aria"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span></button>
        </div>
        <form id="taskDescriptionInlineForm" class="d-none crm-task-description-edit-form">
          <label class="form-label" data-i18n="task_detail.desc_field_label"><?= htmlspecialchars($t('task_detail.desc_field_label', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label>
          <textarea id="taskDescriptionInlineInput" class="form-control" rows="5" data-crm-visual-editor="1"></textarea>
          <div class="d-flex gap-2 mt-2"><button type="submit" class="btn btn-sm crm-btn-primary" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button><button type="button" class="btn btn-sm btn-light" data-task-inline-cancel="description" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button></div>
        </form>
        <div id="taskDescriptionContent"><div class="text-muted" data-i18n="task_detail.desc_loading"><?= htmlspecialchars($t('task_detail.desc_loading', 'Детали задачи загружаются...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </section>
      <section class="crm-task-source-chat d-none" id="taskSourceChatSection" aria-labelledby="taskSourceChatTitle">
        <h2 class="h6 mb-1" id="taskSourceChatTitle" data-i18n="task_detail.chat_source_title"><?= htmlspecialchars($t('task_detail.chat_source_title', 'Создано из обсуждения'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="small text-muted mb-2" id="taskSourceChatText" data-i18n="task_detail.chat_source_text"><?= htmlspecialchars($t('task_detail.chat_source_text', 'Задача создана из сообщения в чате. Перейдите к диалогу, чтобы увидеть контекст.'), ENT_QUOTES, 'UTF-8') ?></p>
        <a class="btn btn-sm crm-btn-secondary" id="taskSourceChatLink" href="#" target="_blank" rel="noopener" data-i18n="task_detail.chat_source_open"><?= htmlspecialchars($t('task_detail.chat_source_open', 'Открыть обсуждение'), ENT_QUOTES, 'UTF-8') ?></a>
      </section>
      <form id="taskStatusReasonForm" class="mb-3 d-none">
        <div class="small text-muted mb-1"><span data-i18n="task_detail.status_reason_label"><?= htmlspecialchars($t('task_detail.status_reason_label', 'Причина смены статуса: '), ENT_QUOTES, 'UTF-8') ?></span><span id="taskStatusReasonTarget">—</span></div>
        <div class="d-flex gap-2">
          <textarea class="form-control form-control-sm" id="taskStatusReasonInput" rows="2" placeholder="<?= htmlspecialchars($t('task_detail.status_reason_placeholder', 'Опишите, почему меняете статус и что изменилось'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="task_detail.status_reason_placeholder"></textarea>
          <div class="d-flex flex-column gap-2">
            <button class="btn btn-sm crm-btn-primary" type="submit" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
            <button class="btn btn-sm btn-light" type="button" id="taskStatusReasonCancelBtn" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
        </div>
      </form>
      <div class="alert alert-info mb-0 crm-task-risk-alert" id="taskRiskAlert"><strong data-i18n="task_detail.risk_label"><?= htmlspecialchars($t('task_detail.risk_label', 'Риск:'), ENT_QUOTES, 'UTF-8') ?></strong> <span data-i18n="task_detail.risk_loading"><?= htmlspecialchars($t('task_detail.risk_loading', 'оценка риска загружается...'), ENT_QUOTES, 'UTF-8') ?></span></div>
    </div>

    <ul class="nav nav-tabs mb-3 crm-task-tabs-nav" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#detailSubtasks" type="button" data-i18n="task_detail.tab_subtasks"><?= htmlspecialchars($t('task_detail.tab_subtasks', 'Подзадачи'), ENT_QUOTES, 'UTF-8') ?> <span id="detailSubtasksCounter" class="badge text-bg-secondary crm-tab-counter d-none">0</span></button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#detailChecklists" type="button" data-i18n="task_detail.tab_checklists"><?= htmlspecialchars($t('task_detail.tab_checklists', 'Чеклисты'), ENT_QUOTES, 'UTF-8') ?> <span id="detailChecklistsCounter" class="badge text-bg-secondary crm-tab-counter d-none">0</span></button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#detailComments" type="button" data-i18n="task_detail.tab_comments"><?= htmlspecialchars($t('task_detail.tab_comments', 'Комментарии'), ENT_QUOTES, 'UTF-8') ?> <span id="detailCommentsCounter" class="badge text-bg-secondary crm-tab-counter d-none">0</span></button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#detailWorklogs" type="button" data-i18n="task_detail.tab_worklogs"><?= htmlspecialchars($t('task_detail.tab_worklogs', 'Учет времени'), ENT_QUOTES, 'UTF-8') ?></button></li>
      <li class="nav-item dropdown crm-task-tabs-overflow">
        <button class="nav-link dropdown-toggle" id="taskTabsMore" data-bs-toggle="dropdown" type="button" aria-expanded="false" data-i18n="task_detail.tab_more"><?= htmlspecialchars($t('task_detail.tab_more', 'Ещё'), ENT_QUOTES, 'UTF-8') ?></button>
        <ul class="dropdown-menu" aria-labelledby="taskTabsMore">
          <li><button class="dropdown-item" data-bs-toggle="tab" data-bs-target="#detailDependencies" type="button" data-task-overflow-tab="1" data-i18n="task_detail.tab_dependencies"><?= htmlspecialchars($t('task_detail.tab_dependencies', 'Зависимости'), ENT_QUOTES, 'UTF-8') ?> <span id="detailDependenciesCounter" class="badge text-bg-secondary crm-tab-counter d-none">0</span></button></li>
          <li><button class="dropdown-item" data-bs-toggle="tab" data-bs-target="#detailFiles" type="button" data-task-overflow-tab="1" data-i18n="task_detail.tab_files"><?= htmlspecialchars($t('task_detail.tab_files', 'Файлы'), ENT_QUOTES, 'UTF-8') ?></button></li>
          <li><button class="dropdown-item" data-bs-toggle="tab" data-bs-target="#detailActivity" type="button" data-task-overflow-tab="1" data-i18n="task_detail.tab_history"><?= htmlspecialchars($t('task_detail.tab_history', 'История'), ENT_QUOTES, 'UTF-8') ?></button></li>
          <li><button class="dropdown-item" data-bs-toggle="tab" data-bs-target="#detailKnowledge" type="button" data-task-overflow-tab="1" data-i18n="task_detail.tab_knowledge"><?= htmlspecialchars($t('task_detail.tab_knowledge', 'База знаний'), ENT_QUOTES, 'UTF-8') ?></button></li>
        </ul>
      </li>
    </ul>

    <div class="tab-content">
      <section id="detailSubtasks" class="tab-pane fade show active crm-card crm-task-section">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 mb-0" data-i18n="task_detail.subtasks_title"><?= htmlspecialchars($t('task_detail.subtasks_title', 'Подзадачи'), ENT_QUOTES, 'UTF-8') ?></h2>
          <div class="d-flex align-items-center gap-2">
            <small class="text-muted" data-i18n="task_detail.subtasks_hint"><?= htmlspecialchars($t('task_detail.subtasks_hint', 'Декомпозиция задачи на шаги'), ENT_QUOTES, 'UTF-8') ?></small>
            <button type="button" class="btn btn-sm crm-btn-primary crm-btn-compact" id="openCreateSubtaskModalBtn" data-open-modal="createSubtaskModal" data-i18n="task_detail.subtasks_create_btn"><?= htmlspecialchars($t('task_detail.subtasks_create_btn', 'Создать подзадачу'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
        </div>
        <div id="subtasksList"><div class="text-muted" data-i18n="task_detail.subtasks_loading"><?= htmlspecialchars($t('task_detail.subtasks_loading', 'Подзадачи загружаются...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </section>

      <section id="detailDependencies" class="tab-pane fade crm-card crm-task-section">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 mb-0" data-i18n="task_detail.dependencies_title"><?= htmlspecialchars($t('task_detail.dependencies_title', 'Зависимости'), ENT_QUOTES, 'UTF-8') ?></h2>
          <div class="d-flex align-items-center gap-2">
            <small class="text-muted" data-i18n="task_detail.dependencies_hint"><?= htmlspecialchars($t('task_detail.dependencies_hint', 'Связи с другими задачами'), ENT_QUOTES, 'UTF-8') ?></small>
            <button type="button" class="btn btn-sm crm-btn-primary crm-btn-compact" id="openCreateDependencyBtn" data-open-modal="createDependencyModal" data-i18n="task_detail.dependencies_add_btn"><?= htmlspecialchars($t('task_detail.dependencies_add_btn', 'Добавить зависимость'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
        </div>

        <div class="mb-3">
          <h3 class="h6 text-muted mb-2" data-i18n="dependency.section_depends_on"><?= htmlspecialchars($t('dependency.section_depends_on', 'Зависит от'), ENT_QUOTES, 'UTF-8') ?></h3>
          <div id="dependenciesOutgoing"><div class="text-muted small" data-i18n="task_detail.dependencies_loading"><?= htmlspecialchars($t('task_detail.dependencies_loading', 'Зависимости загружаются...'), ENT_QUOTES, 'UTF-8') ?></div></div>
        </div>

        <div>
          <h3 class="h6 text-muted mb-2" data-i18n="dependency.section_blocked_by"><?= htmlspecialchars($t('dependency.section_blocked_by', 'От этой задачи зависят'), ENT_QUOTES, 'UTF-8') ?></h3>
          <div id="dependenciesIncoming"><div class="text-muted small" data-i18n="task_detail.dependencies_loading"><?= htmlspecialchars($t('task_detail.dependencies_loading', 'Зависимости загружаются...'), ENT_QUOTES, 'UTF-8') ?></div></div>
        </div>
      </section>

      <section id="detailChecklists" class="tab-pane fade crm-card crm-task-section">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 mb-0" data-i18n="task_detail.checklists_title"><?= htmlspecialchars($t('task_detail.checklists_title', 'Чеклисты'), ENT_QUOTES, 'UTF-8') ?></h2>
          <small class="text-muted" data-i18n="task_detail.checklists_hint"><?= htmlspecialchars($t('task_detail.checklists_hint', 'Контроль выполнения деталей'), ENT_QUOTES, 'UTF-8') ?></small>
        </div>
        <form id="checklistCreateForm" class="row g-2 mb-3 crm-task-create-form">
          <div class="col-md-9">
            <label class="form-label" data-i18n="task_detail.checklist_new_label"><?= htmlspecialchars($t('task_detail.checklist_new_label', 'Новый чеклист'), ENT_QUOTES, 'UTF-8') ?></label>
            <input class="form-control" name="title" maxlength="255" placeholder="<?= htmlspecialchars($t('task_detail.checklist_placeholder', 'Например: Проверка перед релизом'), ENT_QUOTES, 'UTF-8') ?>" required data-i18n-placeholder="task_detail.checklist_placeholder">
          </div>
          <div class="col-md-3 d-flex align-items-end crm-task-create-action">
            <button class="btn crm-btn-primary crm-btn-compact w-100" type="submit" data-i18n="task_detail.checklist_add_btn"><?= htmlspecialchars($t('task_detail.checklist_add_btn', '+ Новый чеклист'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
        </form>
        <div id="checklistsList"><div class="text-muted" data-i18n="task_detail.checklists_loading"><?= htmlspecialchars($t('task_detail.checklists_loading', 'Чеклисты загружаются...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </section>

      <section id="detailWorklogs" class="tab-pane fade crm-card crm-task-section">
        <div class="crm-worklog-head mb-2">
          <div>
            <h2 class="h6 mb-1" data-i18n="task_detail.worklogs_title"><?= htmlspecialchars($t('task_detail.worklogs_title', 'Учет времени'), ENT_QUOTES, 'UTF-8') ?></h2>
            <small class="text-muted" data-i18n="task_detail.worklogs_hint"><?= htmlspecialchars($t('task_detail.worklogs_hint', 'Фиксация фактически затраченного времени'), ENT_QUOTES, 'UTF-8') ?></small>
          </div>
          <button class="btn crm-btn-primary crm-btn-compact" type="button" id="worklogAddToggleBtn" data-i18n="task_detail.worklogs_add_btn"><?= htmlspecialchars($t('task_detail.worklogs_add_btn', '+ Добавить запись'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
        <div id="taskWorklogSummary" class="crm-worklog-summary mb-2">
          <div class="crm-worklog-summary-stat">
            <span class="crm-worklog-summary-icon" aria-hidden="true"><i class="fa-regular fa-clock"></i></span>
            <strong class="crm-worklog-summary-value">0 <?= htmlspecialchars($t('task_detail.worklogs_minutes', 'мин'), ENT_QUOTES, 'UTF-8') ?></strong>
            <span class="crm-worklog-summary-label" data-i18n="task_detail.worklogs_total_label"><?= htmlspecialchars($t('task_detail.worklogs_total_label', 'Всего времени'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="crm-worklog-summary-stat">
            <span class="crm-worklog-summary-icon" aria-hidden="true"><i class="fa-regular fa-rectangle-list"></i></span>
            <strong class="crm-worklog-summary-value">0 <?= htmlspecialchars($t('task_detail.worklogs_entries', 'записей'), ENT_QUOTES, 'UTF-8') ?></strong>
            <span class="crm-worklog-summary-label" data-i18n="task_detail.worklogs_entries_label"><?= htmlspecialchars($t('task_detail.worklogs_entries_label', 'В журнале'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>
        <form id="worklogCreateForm" class="row g-3 mb-3 crm-task-create-form crm-worklog-create-form d-none">
          <div class="col-lg-3">
            <label class="form-label" data-i18n="task_detail.worklog_minutes_label"><?= htmlspecialchars($t('task_detail.worklog_minutes_label', 'Минуты'), ENT_QUOTES, 'UTF-8') ?></label>
            <input class="form-control" name="minutes_spent" type="number" min="1" step="1" placeholder="60" required>
          </div>
          <div class="col-lg-3">
            <label class="form-label" data-i18n="task_detail.worklog_date_label"><?= htmlspecialchars($t('task_detail.worklog_date_label', 'Дата/время'), ENT_QUOTES, 'UTF-8') ?></label>
            <input class="form-control" name="logged_at" type="datetime-local" min="<?= date('Y-m-d\TH:i') ?>">
          </div>
          <div class="col-lg-6">
            <label class="form-label" data-i18n="task_detail.worklog_comment_label"><?= htmlspecialchars($t('task_detail.worklog_comment_label', 'Комментарий'), ENT_QUOTES, 'UTF-8') ?></label>
            <input class="form-control" name="note" maxlength="8000" placeholder="<?= htmlspecialchars($t('task_detail.worklog_comment_placeholder', 'Что было сделано'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="task_detail.worklog_comment_placeholder">
          </div>
          <div class="col-12 crm-worklog-create-actions">
            <button class="btn crm-btn-secondary crm-btn-compact" type="button" id="worklogCreateCancelBtn" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
            <button class="btn crm-btn-primary crm-btn-compact" type="submit" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
        </form>
        <div class="crm-worklog-list-head mb-2"><h3 class="h6 mb-0" data-i18n="task_detail.worklog_list_title"><?= htmlspecialchars($t('task_detail.worklog_list_title', 'Журнал времени'), ENT_QUOTES, 'UTF-8') ?></h3></div>
        <div id="taskWorklogsList"><div class="text-muted" data-i18n="task_detail.worklogs_loading"><?= htmlspecialchars($t('task_detail.worklogs_loading', 'Логи времени загружаются...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </section>

      <section id="detailFiles" class="tab-pane fade crm-card crm-task-section">
        <h2 class="h6" data-i18n="task_detail.files_title"><?= htmlspecialchars($t('task_detail.files_title', 'Файлы'), ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="mb-3">
          <label class="form-label" data-i18n="task_detail.files_add_label"><?= htmlspecialchars($t('task_detail.files_add_label', 'Добавить файл'), ENT_QUOTES, 'UTF-8') ?></label>
          <div class="crm-file-upload-row">
            <input class="form-control crm-file-input" type="file" id="taskFileInput">
            <button class="btn crm-btn-primary" type="button" id="taskFileUploadBtn" data-i18n="task_detail.files_upload_btn"><?= htmlspecialchars($t('task_detail.files_upload_btn', 'Загрузить'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
          
        </div>
        <div id="taskFilesList"><div class="text-muted" data-i18n="task_detail.files_empty"><?= htmlspecialchars($t('task_detail.files_empty', 'Файлы к задаче пока не загружены.'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </section>

      <section id="detailComments" class="tab-pane fade crm-card crm-task-section">
        <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0" data-i18n="task_detail.comments_title"><?= htmlspecialchars($t('task_detail.comments_title', 'Комментарии'), ENT_QUOTES, 'UTF-8') ?></h2></div>
        <div class="d-flex gap-2 flex-wrap mb-3">
          <button id="taskFollowBtn" class="btn btn-sm crm-btn-secondary" type="button" data-i18n="task_detail.comments_follow_btn"><?= htmlspecialchars($t('task_detail.comments_follow_btn', 'Отслеживать задачу'), ENT_QUOTES, 'UTF-8') ?></button>
          <button id="taskFavoriteBtn" class="btn btn-sm crm-btn-secondary" type="button" data-i18n="task_detail.comments_favorite_btn"><?= htmlspecialchars($t('task_detail.comments_favorite_btn', 'В избранное'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
        <form id="commentForm" class="mb-3 crm-comment-create-shell">
          <label class="form-label" data-i18n="task_detail.comment_new_label"><?= htmlspecialchars($t('task_detail.comment_new_label', 'Новый комментарий'), ENT_QUOTES, 'UTF-8') ?></label>
          <textarea class="form-control" name="comment_text" rows="2" placeholder="<?= htmlspecialchars($t('task_detail.comment_placeholder', 'Добавьте комментарий и сохраните его в карточке задачи'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="task_detail.comment_placeholder" data-crm-visual-editor="1"></textarea>
          <div class="crm-comment-create-actions">
            <div class="crm-comment-mention-field">
              <label class="small text-muted mb-0" for="commentMentionUserSelect" data-i18n="task_detail.comment_mention_label"><?= htmlspecialchars($t('task_detail.comment_mention_label', 'Упомянуть:'), ENT_QUOTES, 'UTF-8') ?></label>
              <select id="commentMentionUserSelect" class="form-select form-select-sm crm-field-w-220"><option value="" data-i18n="task_detail.comment_no_mention"><?= htmlspecialchars($t('task_detail.comment_no_mention', 'Без упоминания'), ENT_QUOTES, 'UTF-8') ?></option></select>
            </div>
            <div class="d-flex gap-2 justify-content-end">
              <button class="btn crm-btn-secondary" type="button" data-comment-create-cancel data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
              <button class="btn crm-btn-primary" type="submit" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
          </div>
        </form>
        <div id="commentsList"><div class="text-muted" data-i18n="task_detail.comments_loading"><?= htmlspecialchars($t('task_detail.comments_loading', 'Комментарии загружаются...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </section>

      <section id="detailActivity" class="tab-pane fade crm-card crm-task-section">
        <h2 class="h6 mb-1" data-i18n="task_detail.history_title"><?= htmlspecialchars($t('task_detail.history_title', 'История изменений'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="text-muted small mb-3" data-i18n="task_detail.history_subtitle"><?= htmlspecialchars($t('task_detail.history_subtitle', 'Все события и изменения по задаче в одной хронологии.'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="crm-timeline" id="taskActivityList"><div class="crm-timeline-item" data-i18n="task_detail.activity_loading"><?= htmlspecialchars($t('task_detail.activity_loading', 'История изменений загружается...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </section>
      <section id="detailKnowledge" class="tab-pane fade crm-card crm-task-section">
        <h2 class="h6" data-i18n="task_detail.knowledge_title"><?= htmlspecialchars($t('task_detail.knowledge_title', 'Связанные страницы'), ENT_QUOTES, 'UTF-8') ?></h2>
        <div id="taskKnowledgeList"><div class="text-muted small" data-i18n="task_detail.knowledge_loading"><?= htmlspecialchars($t('task_detail.knowledge_loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
        <div class="mt-2 d-flex gap-2 flex-wrap"><button id="taskAttachKnowledgeBtn" class="btn btn-sm crm-btn-secondary" type="button" data-i18n="task_detail.btn_attach_knowledge"><?= htmlspecialchars($t('task_detail.btn_attach_knowledge', 'Прикрепить статью'), ENT_QUOTES, 'UTF-8') ?></button><a id="taskCreateKnowledgeBtn" class="btn btn-sm crm-btn-secondary" href="index.php?route=knowledge" data-i18n="task_detail.btn_create_knowledge"><?= htmlspecialchars($t('task_detail.btn_create_knowledge', 'Создать связанную страницу'), ENT_QUOTES, 'UTF-8') ?></a></div>
      </section>
    </div>
  </div>

  <aside class="col-lg-4 crm-task-side-column">
    <div class="crm-task-side-rail">
    <?= module_position('task.detail.sidebar', ['route' => $route ?? 'task-detail', 'task_public_id' => (string)($_GET['task_public_id'] ?? '')]) ?>
    <div class="crm-card mb-3" id="taskEstimatesPanel">
      <div class="crm-side-card-head">
        <div>
          <div class="crm-task-eyebrow" data-i18n="task_detail.estimates_eyebrow"><?= htmlspecialchars($t('task_detail.estimates_eyebrow', 'Task Estimates'), ENT_QUOTES, 'UTF-8') ?></div>
          <h2 class="h6 mb-0" data-i18n="task_detail.estimates_title"><?= htmlspecialchars($t('task_detail.estimates_title', 'Оценки'), ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
        <button type="button" class="btn btn-sm crm-btn-primary crm-btn-compact" id="taskEstimateAddBtn" data-i18n="task_detail.estimates_add_btn"><?= htmlspecialchars($t('task_detail.estimates_add_btn', '+ Оценить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div id="taskEstimatesList">
        <div class="text-muted small" data-i18n="task_detail.estimates_loading"><?= htmlspecialchars($t('task_detail.estimates_loading', 'Загрузка оценок...'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>
    <div class="crm-card mb-3" id="taskTimerPanel">
      <div class="crm-side-card-head">
        <div>
          <div class="crm-task-eyebrow" data-i18n="task_detail.timer_eyebrow"><?= htmlspecialchars($t('task_detail.timer_eyebrow', 'Таймер работы'), ENT_QUOTES, 'UTF-8') ?></div>
          <h2 class="h6 mb-0" data-i18n="task_detail.timer_title"><?= htmlspecialchars($t('task_detail.timer_title', 'Таймер задачи'), ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div>
          <div class="small text-muted" data-i18n="task_detail.timer_running_label"><?= htmlspecialchars($t('task_detail.timer_running_label', 'Таймер работы по задаче'), ENT_QUOTES, 'UTF-8') ?></div>
          <div class="fw-semibold" id="taskTimerElapsed">00:00:00</div>
          <div class="small text-muted" id="taskTimerStartedAt" data-i18n="task_detail.timer_not_started"><?= htmlspecialchars($t('task_detail.timer_not_started', 'Таймер не запущен'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm crm-btn-primary crm-btn-compact" id="taskTimerStartBtn" data-i18n="task_detail.timer_start_btn"><?= htmlspecialchars($t('task_detail.timer_start_btn', 'Начать работу'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="button" class="btn btn-sm crm-btn-danger-soft crm-btn-compact" id="taskTimerStopBtn" disabled data-i18n="task_detail.timer_stop_btn"><?= htmlspecialchars($t('task_detail.timer_stop_btn', 'Остановить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </div>
      <div class="crm-task-timer-actions">
        <div>
          <div class="small text-muted" data-i18n="task_detail.timer_planning_label"><?= htmlspecialchars($t('task_detail.timer_planning_label', 'Планирование'), ENT_QUOTES, 'UTF-8') ?></div>
          <div class="crm-task-timer-action-copy" data-i18n="task_detail.timer_event_hint"><?= htmlspecialchars($t('task_detail.timer_event_hint', 'Событие будет связано с этой задачей.'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <button class="btn btn-sm btn-light crm-task-calendar-action" type="button" data-open-modal="calendarEventModal" data-i18n="task_detail.timer_create_event_btn"><?= htmlspecialchars($t('task_detail.timer_create_event_btn', 'Создать событие'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <form id="taskTimerLogForm" class="row g-2 mt-3 d-none">
        <div class="col-12">
          <label class="form-label" data-i18n="task_detail.timer_log_minutes_label"><?= htmlspecialchars($t('task_detail.timer_log_minutes_label', 'Затрачено (мин)'), ENT_QUOTES, 'UTF-8') ?></label>
          <input class="form-control" type="number" min="1" step="1" name="minutes_spent" required>
          <div id="taskTimerLogElapsedHint" class="small text-muted mt-1 d-none"></div>
        </div>
        <div class="col-12">
          <label class="form-label" data-i18n="task_detail.timer_log_note_label"><?= htmlspecialchars($t('task_detail.timer_log_note_label', 'Что было сделано'), ENT_QUOTES, 'UTF-8') ?></label>
          <input class="form-control" name="note" maxlength="8000" placeholder="<?= htmlspecialchars($t('task_detail.timer_log_note_placeholder', 'Кратко опишите выполненную работу'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="task_detail.timer_log_note_placeholder">
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn btn-sm crm-btn-primary crm-btn-compact" data-i18n="task_detail.timer_log_add_btn"><?= htmlspecialchars($t('task_detail.timer_log_add_btn', 'Добавить запись'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="button" class="btn btn-sm btn-light" id="taskTimerLogCancelBtn" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
    </div>
    <div class="crm-card mb-3" id="taskAiSummaryCard" data-requires-ai-use="1">
      <div class="crm-side-card-head">
        <div>
          <div class="crm-task-eyebrow" data-i18n="task_detail.ai_eyebrow"><?= htmlspecialchars($t('task_detail.ai_eyebrow', 'AI-помощник'), ENT_QUOTES, 'UTF-8') ?></div>
          <h2 class="h6 mb-0" data-i18n="task_detail.ai_title"><?= htmlspecialchars($t('task_detail.ai_title', 'AI-действия по задаче'), ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
      </div>
      <div class="small text-muted mb-2" id="taskAiSummaryState" data-i18n="task_detail.ai_state_idle"><?= htmlspecialchars($t('task_detail.ai_state_idle', 'AI-сводка не сформирована.'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="d-flex gap-2 mb-2 flex-wrap" id="taskAiPrimaryActions">
        <button class="btn btn-sm crm-btn-primary crm-btn-compact" type="button" id="taskAiGenerateBtn" data-i18n="task_detail.ai_generate_btn"><?= htmlspecialchars($t('task_detail.ai_generate_btn', 'AI-сводка'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiNextActionBtn" data-i18n="task_detail.ai_next_action_btn"><?= htmlspecialchars($t('task_detail.ai_next_action_btn', 'Следующий шаг'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiDecomposeBtn" data-i18n="task_detail.ai_decompose_btn"><?= htmlspecialchars($t('task_detail.ai_decompose_btn', 'Предложить подзадачи'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiChecklistBtn" data-i18n="task_detail.ai_checklist_btn"><?= htmlspecialchars($t('task_detail.ai_checklist_btn', 'Предложить чеклист'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiImproveDescBtn" data-i18n="task_detail.ai_improve_desc_btn"><?= htmlspecialchars($t('task_detail.ai_improve_desc_btn', 'Улучшить описание'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiCommentDraftBtn" data-i18n="task_detail.ai_comment_draft_btn"><?= htmlspecialchars($t('task_detail.ai_comment_draft_btn', 'Черновик комментария'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiQualityBtn" data-i18n="task_detail.ai_quality_btn"><?= htmlspecialchars($t('task_detail.ai_quality_btn', 'Проверить задачу'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiCreateMeetingBtn" data-i18n="task_detail.ai_create_meeting_btn"><?= htmlspecialchars($t('task_detail.ai_create_meeting_btn', 'Создать встречу'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div class="d-flex gap-2 mb-2 flex-wrap" id="taskAiSecondaryActions">
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiPreviewBtn" disabled data-i18n="task_detail.ai_preview_btn"><?= htmlspecialchars($t('task_detail.ai_preview_btn', 'Предпросмотр'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiApplyBtn" disabled data-i18n="task_detail.ai_apply_btn"><?= htmlspecialchars($t('task_detail.ai_apply_btn', 'Применить'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm crm-btn-muted crm-btn-compact" type="button" id="taskAiDismissBtn" disabled data-i18n="task_detail.ai_dismiss_btn"><?= htmlspecialchars($t('task_detail.ai_dismiss_btn', 'Отклонить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div id="taskAiSummaryResult" class="crm-empty-state d-none">
        <div class="small mb-2" id="taskAiSummaryText">—</div>
        <div class="small text-muted" id="taskAiSummaryMeta">—</div>
      </div>
      <div id="taskAiSummaryPreviewWrap" class="mt-2 d-none">
        <div class="small text-muted mb-1" data-i18n="task_detail.ai_preview_label"><?= htmlspecialchars($t('task_detail.ai_preview_label', 'Предпросмотр применения:'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="crm-info-panel small" id="taskAiSummaryPreview"></div>
      </div>
    </div>
    <div class="crm-card mb-3 crm-task-summary-card" id="taskSummaryCard">
      <div class="crm-side-card-head">
        <div>
          <div class="crm-task-eyebrow" data-i18n="task_detail.summary_eyebrow"><?= htmlspecialchars($t('task_detail.summary_eyebrow', 'Быстрая навигация'), ENT_QUOTES, 'UTF-8') ?></div>
          <h2 class="h6 mb-0" data-i18n="task_detail.summary_title"><?= htmlspecialchars($t('task_detail.summary_title', 'Сводка'), ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
      </div>
      <div class="crm-info-panel mb-2">
        <small class="text-muted" data-i18n="task_detail.summary_task_key_label"><?= htmlspecialchars($t('task_detail.summary_task_key_label', 'Ключ задачи'), ENT_QUOTES, 'UTF-8') ?></small>
        <div class="crm-summary-value d-flex align-items-center gap-2">
          <span id="taskKeyValue">—</span>
          <button class="btn btn-sm crm-btn-ghost crm-btn-icon" id="taskKeyCopyBtn" title="<?= htmlspecialchars($t('task_detail.copy_key_title', 'Копировать ключ'), ENT_QUOTES, 'UTF-8') ?>" style="display:none" data-i18n-title="task_detail.copy_key_title"></button>
        </div>
      </div>
      <div class="crm-info-panel mb-2">
        <small class="text-muted" data-i18n="task_detail.summary_author_label"><?= htmlspecialchars($t('task_detail.summary_author_label', 'Автор задачи'), ENT_QUOTES, 'UTF-8') ?></small>
        <div class="crm-summary-value" id="taskAuthorValue">—</div>
      </div>
      <div class="crm-info-panel mb-2">
        <div class="d-flex justify-content-between align-items-center">
          <small class="text-muted" data-i18n="task_detail.summary_assignee_label"><?= htmlspecialchars($t('task_detail.summary_assignee_label', 'Исполнитель'), ENT_QUOTES, 'UTF-8') ?></small>
          <button type="button" class="btn btn-sm crm-inline-icon-btn" data-task-inline-toggle="assignee" aria-label="<?= htmlspecialchars($t('task_detail.summary_assignee_edit_aria', 'Редактировать исполнителя'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="task_detail.summary_assignee_edit_aria">
            <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span>
          </button>
        </div>
        <div class="crm-summary-value" id="taskAssigneeValue">—</div>
        <form id="taskAssigneeInlineForm" class="mt-2 d-none">
          <select id="taskAssigneeInlineSelect" class="form-select form-select-sm"></select>
          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-sm crm-btn-primary" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" class="btn btn-sm btn-light" data-task-inline-cancel="assignee" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
        </form>
      </div>
      <div class="crm-info-panel mb-2">
        <div class="d-flex justify-content-between align-items-center">
          <small class="text-muted" data-i18n="task_detail.summary_manager_label"><?= htmlspecialchars($t('task_detail.summary_manager_label', 'Менеджер'), ENT_QUOTES, 'UTF-8') ?></small>
          <button type="button" class="btn btn-sm crm-inline-icon-btn" data-task-inline-toggle="manager" aria-label="<?= htmlspecialchars($t('task_detail.summary_manager_edit_aria', 'Редактировать менеджера'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="task_detail.summary_manager_edit_aria">
            <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span>
          </button>
        </div>
        <div class="crm-summary-value" id="taskManagerValue">—</div>
        <form id="taskManagerInlineForm" class="mt-2 d-none">
          <select id="taskManagerInlineSelect" class="form-select form-select-sm"></select>
          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-sm crm-btn-primary" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" class="btn btn-sm btn-light" data-task-inline-cancel="manager" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
        </form>
      </div>
      <div class="crm-info-panel mb-2">
        <div class="d-flex justify-content-between align-items-center">
          <small class="text-muted" data-i18n="task_detail.summary_tags_label"><?= htmlspecialchars($t('task_detail.summary_tags_label', 'Теги'), ENT_QUOTES, 'UTF-8') ?></small>
          <button type="button" class="btn btn-sm crm-inline-icon-btn" data-task-inline-toggle="tags" aria-label="<?= htmlspecialchars($t('task_detail.summary_tags_edit_aria', 'Редактировать теги'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="task_detail.summary_tags_edit_aria">
            <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span>
          </button>
        </div>
        <div class="crm-summary-value" id="taskTagsValue">—</div>
        <form id="taskTagsInlineForm" class="mt-2 d-none">
          <select id="taskTagsInlineSelect" class="form-select form-select-sm" multiple size="6"></select>
          <div class="form-text" data-i18n="task_detail.summary_tags_hint"><?= htmlspecialchars($t('task_detail.summary_tags_hint', 'Можно выбрать несколько тегов (Ctrl/Cmd + клик).'), ENT_QUOTES, 'UTF-8') ?></div>
          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-sm crm-btn-primary" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" class="btn btn-sm btn-light" data-task-inline-cancel="tags" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
        </form>
      </div>
      <div class="crm-info-panel mb-2">
        <div class="d-flex justify-content-between align-items-center">
          <small class="text-muted" data-i18n="task_detail.summary_dates_label"><?= htmlspecialchars($t('task_detail.summary_dates_label', 'Сроки задачи'), ENT_QUOTES, 'UTF-8') ?></small>
          <button type="button" class="btn btn-sm crm-inline-icon-btn" data-task-inline-toggle="dates" aria-label="<?= htmlspecialchars($t('task_detail.summary_dates_edit_aria', 'Редактировать сроки задачи'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="task_detail.summary_dates_edit_aria">
            <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span>
          </button>
        </div>
        <div class="crm-summary-value" id="taskDatesValue">—</div>
        <form id="taskDatesInlineForm" class="mt-2 d-none">
          <div class="mb-2">
            <label class="form-label mb-1 small text-muted" for="taskDatesStartAt" data-i18n="task_detail.summary_dates_start_label"><?= htmlspecialchars($t('task_detail.summary_dates_start_label', 'Начало'), ENT_QUOTES, 'UTF-8') ?></label>
            <input class="form-control form-control-sm" id="taskDatesStartAt" name="start_at" type="datetime-local" min="<?= date('Y-m-d\TH:i') ?>">
          </div>
          <div class="mb-2">
            <label class="form-label mb-1 small text-muted" for="taskDatesDueAt" data-i18n="task_detail.summary_dates_due_label"><?= htmlspecialchars($t('task_detail.summary_dates_due_label', 'Дедлайн'), ENT_QUOTES, 'UTF-8') ?></label>
            <input class="form-control form-control-sm" id="taskDatesDueAt" name="due_at" type="datetime-local" min="<?= date('Y-m-d\TH:i') ?>">
          </div>
          <div class="mb-2">
            <label class="form-label mb-1 small text-muted" for="taskDatesEndAt" data-i18n="task_detail.summary_dates_end_label"><?= htmlspecialchars($t('task_detail.summary_dates_end_label', 'Плановое завершение'), ENT_QUOTES, 'UTF-8') ?></label>
            <input class="form-control form-control-sm" id="taskDatesEndAt" name="end_at" type="datetime-local" min="<?= date('Y-m-d\TH:i') ?>">
          </div>
          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-sm crm-btn-primary" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" class="btn btn-sm btn-light" data-task-inline-cancel="dates" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
        </form>
      </div>
      <div class="crm-info-panel">
        <div class="d-flex justify-content-between align-items-center">
          <small class="text-muted" data-i18n="task_detail.summary_project_label"><?= htmlspecialchars($t('task_detail.summary_project_label', 'Связанный проект'), ENT_QUOTES, 'UTF-8') ?></small>
          <button type="button" class="btn btn-sm crm-inline-icon-btn" data-task-inline-toggle="project" aria-label="<?= htmlspecialchars($t('task_detail.summary_project_edit_aria', 'Редактировать проект'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="task_detail.summary_project_edit_aria">
            <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span>
          </button>
        </div>
        <div class="crm-summary-value"><a href="index.php?route=projects" id="taskProjectLink">—</a></div>
        <form id="taskProjectInlineForm" class="mt-2 d-none">
          <select id="taskProjectInlineSelect" class="form-select form-select-sm"></select>
          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-sm crm-btn-primary" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" class="btn btn-sm btn-light" data-task-inline-cancel="project" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
        </form>
      </div>
    </div>
    </div>
  </aside>
</div>

<div class="modal fade" id="createSubtaskModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="task_detail.modal_create_subtask_title"><?= htmlspecialchars($t('task_detail.modal_create_subtask_title', 'Создать подзадачу'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <form id="subtaskCreateForm">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label" data-i18n="task_detail.modal_create_subtask_label_title"><?= htmlspecialchars($t('task_detail.modal_create_subtask_label_title', 'Название подзадачи'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" name="title" maxlength="255" placeholder="<?= htmlspecialchars($t('task_detail.modal_create_subtask_placeholder_title', 'Например: Подготовить макет'), ENT_QUOTES, 'UTF-8') ?>" required data-i18n-placeholder="task_detail.modal_create_subtask_placeholder_title">
            </div>
            <div class="col-md-4">
              <label class="form-label" data-i18n="task_detail.modal_create_subtask_label_project"><?= htmlspecialchars($t('task_detail.modal_create_subtask_label_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" name="project_public_id" disabled>
                <option value="" data-i18n="task_detail.modal_create_subtask_option_no_project"><?= htmlspecialchars($t('task_detail.modal_create_subtask_option_no_project', 'Без проекта'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
              <div class="form-text" data-i18n="task_detail.modal_create_subtask_project_hint"><?= htmlspecialchars($t('task_detail.modal_create_subtask_project_hint', 'Подзадача создается в проекте родительской задачи.'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="col-md-4">
              <label class="form-label" data-i18n="task_detail.modal_create_subtask_label_status"><?= htmlspecialchars($t('task_detail.modal_create_subtask_label_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" name="status"></select>
            </div>
            <div class="col-md-4">
              <label class="form-label" data-i18n="task_detail.modal_create_subtask_label_priority"><?= htmlspecialchars($t('task_detail.modal_create_subtask_label_priority', 'Приоритет'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" name="priority"></select>
            </div>
            <div class="col-md-4">
              <label class="form-label" data-i18n="task_detail.modal_create_subtask_label_assignee"><?= htmlspecialchars($t('task_detail.modal_create_subtask_label_assignee', 'Исполнитель'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" name="assignee_user_public_id"></select>
            </div>
            <div class="col-md-4">
              <label class="form-label" data-i18n="task_detail.modal_create_subtask_label_start"><?= htmlspecialchars($t('task_detail.modal_create_subtask_label_start', 'Начало'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" name="start_at" type="date" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" data-i18n="task_detail.modal_create_subtask_label_due"><?= htmlspecialchars($t('task_detail.modal_create_subtask_label_due', 'Дедлайн'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" name="due_at" type="date" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" data-i18n="task_detail.modal_create_subtask_label_end"><?= htmlspecialchars($t('task_detail.modal_create_subtask_label_end', 'Плановое завершение'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" name="end_at" type="date" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-12">
              <label class="form-label" data-i18n="task_detail.modal_create_subtask_label_tags"><?= htmlspecialchars($t('task_detail.modal_create_subtask_label_tags', 'Теги'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" name="tag_public_ids" multiple size="6"></select>
              <div class="form-text" data-i18n="task_detail.modal_create_subtask_tags_hint"><?= htmlspecialchars($t('task_detail.modal_create_subtask_tags_hint', 'Можно выбрать несколько тегов.'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="col-12">
              <label class="form-label" data-i18n="task_detail.modal_create_subtask_label_description"><?= htmlspecialchars($t('task_detail.modal_create_subtask_label_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label>
              <textarea class="form-control" name="description" rows="5" placeholder="<?= htmlspecialchars($t('task_detail.modal_create_subtask_placeholder_description', 'Контекст, шаги, критерии готовности'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="task_detail.modal_create_subtask_placeholder_description" data-crm-visual-editor="1"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          <button class="btn crm-btn-primary" type="submit" data-i18n="page.create"><?= htmlspecialchars($t('page.create', 'Создать'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editSubtaskModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="task_detail.modal_edit_subtask_title"><?= htmlspecialchars($t('task_detail.modal_edit_subtask_title', 'Редактирование подзадачи'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <form id="subtaskEditForm">
        <input type="hidden" name="public_id" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label" data-i18n="task_detail.modal_edit_subtask_label_title"><?= htmlspecialchars($t('task_detail.modal_edit_subtask_label_title', 'Название подзадачи'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" name="title" maxlength="255" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" data-i18n="task_detail.modal_edit_subtask_label_project"><?= htmlspecialchars($t('task_detail.modal_edit_subtask_label_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" name="project_public_id" disabled>
                <option value="" data-i18n="task_detail.modal_edit_subtask_option_no_project"><?= htmlspecialchars($t('task_detail.modal_edit_subtask_option_no_project', 'Без проекта'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" data-i18n="task_detail.modal_edit_subtask_label_status"><?= htmlspecialchars($t('task_detail.modal_edit_subtask_label_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" name="status"></select>
            </div>
            <div class="col-md-4">
              <label class="form-label" data-i18n="task_detail.modal_edit_subtask_label_priority"><?= htmlspecialchars($t('task_detail.modal_edit_subtask_label_priority', 'Приоритет'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" name="priority"></select>
            </div>
            <div class="col-md-4">
              <label class="form-label" data-i18n="task_detail.modal_edit_subtask_label_assignee"><?= htmlspecialchars($t('task_detail.modal_edit_subtask_label_assignee', 'Исполнитель'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" name="assignee_user_public_id"></select>
            </div>
            <div class="col-md-4">
              <label class="form-label" data-i18n="task_detail.modal_edit_subtask_label_start"><?= htmlspecialchars($t('task_detail.modal_edit_subtask_label_start', 'Начало'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" name="start_at" type="date" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" data-i18n="task_detail.modal_edit_subtask_label_due"><?= htmlspecialchars($t('task_detail.modal_edit_subtask_label_due', 'Дедлайн'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" name="due_at" type="date" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" data-i18n="task_detail.modal_edit_subtask_label_end"><?= htmlspecialchars($t('task_detail.modal_edit_subtask_label_end', 'Плановое завершение'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" name="end_at" type="date" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-12">
              <label class="form-label" data-i18n="task_detail.modal_edit_subtask_label_tags"><?= htmlspecialchars($t('task_detail.modal_edit_subtask_label_tags', 'Теги'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" name="tag_public_ids" multiple size="6"></select>
              <div class="form-text" data-i18n="task_detail.modal_edit_subtask_tags_hint"><?= htmlspecialchars($t('task_detail.modal_edit_subtask_tags_hint', 'Можно выбрать несколько тегов.'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="col-12">
              <label class="form-label" data-i18n="task_detail.modal_edit_subtask_label_description"><?= htmlspecialchars($t('task_detail.modal_edit_subtask_label_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label>
              <textarea class="form-control" name="description" rows="5" data-crm-visual-editor="1"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          <button class="btn crm-btn-primary" type="submit" data-i18n="task_detail.modal_edit_subtask_save_btn"><?= htmlspecialchars($t('task_detail.modal_edit_subtask_save_btn', 'Сохранить изменения'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="taskAiDescriptionDiffModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="task_detail.modal_ai_diff_title"><i class="fa-solid fa-wand-magic-sparkles me-2"></i><?= htmlspecialchars($t('task_detail.modal_ai_diff_title', 'AI: улучшение описания'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-3" data-i18n="task_detail.modal_ai_diff_desc"><?= htmlspecialchars($t('task_detail.modal_ai_diff_desc', 'Сравните текущее и предложенное описание. Применение обновит описание задачи.'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="d-flex align-items-center mb-2">
              <span class="badge bg-secondary me-2" data-i18n="task_detail.modal_ai_diff_current"><?= htmlspecialchars($t('task_detail.modal_ai_diff_current', 'Текущее'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="crm-diff-panel p-3" id="taskAiDescriptionDiffOld">—</div>
          </div>
          <div class="col-md-6">
            <div class="d-flex align-items-center mb-2">
              <span class="badge bg-success me-2" data-i18n="task_detail.modal_ai_diff_proposed"><?= htmlspecialchars($t('task_detail.modal_ai_diff_proposed', 'Предложенное'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="crm-diff-panel crm-diff-panel-new p-3" id="taskAiDescriptionDiffNew">—</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn crm-btn-primary" id="taskAiDescriptionDiffApplyBtn" data-i18n="task_detail.modal_ai_diff_apply_btn"><i class="fa-solid fa-check me-1"></i><?= htmlspecialchars($t('task_detail.modal_ai_diff_apply_btn', 'Применить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="taskAiRegenerateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="taskAiRegenerateModalTitle" data-i18n="task_detail.modal_ai_regenerate_title"><?= htmlspecialchars($t('task_detail.modal_ai_regenerate_title', 'AI-предложение уже существует'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <div class="modal-body">
        <div id="taskAiRegenerateInfo">
          <p class="mb-2" data-i18n="task_detail.modal_ai_regenerate_info"><?= htmlspecialchars($t('task_detail.modal_ai_regenerate_info', 'Для этой задачи уже есть AI-предложение:'), ENT_QUOTES, 'UTF-8') ?></p>
          <div class="crm-info-panel p-3 mb-3">
            <div class="small text-muted mb-1" data-i18n="task_detail.modal_ai_regenerate_summary_label"><?= htmlspecialchars($t('task_detail.modal_ai_regenerate_summary_label', 'Сводка'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="taskAiRegenerateSummary" class="mb-2">—</div>
            <div class="small text-muted mb-1" data-i18n="task_detail.modal_ai_regenerate_status_label"><?= htmlspecialchars($t('task_detail.modal_ai_regenerate_status_label', 'Статус'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="taskAiRegenerateStatus" class="mb-2">—</div>
            <div class="small text-muted mb-1" data-i18n="task_detail.modal_ai_regenerate_updated_label"><?= htmlspecialchars($t('task_detail.modal_ai_regenerate_updated_label', 'Обновлено'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="taskAiRegenerateUpdated">—</div>
          </div>
          <p class="mb-0" data-i18n="task_detail.modal_ai_regenerate_confirm"><?= htmlspecialchars($t('task_detail.modal_ai_regenerate_confirm', 'Перегенерировать предложение?'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div id="taskAiRegenerateLoading" class="d-none text-center py-4">
          <div class="spinner-border text-primary mb-3" style="width: 2.5rem; height: 2.5rem;" role="status"></div>
          <div id="taskAiRegenerateLoadingText" class="text-muted fs-6" data-i18n="task_detail.modal_ai_regenerate_loading"><?= htmlspecialchars($t('task_detail.modal_ai_regenerate_loading', 'Перегенерируем AI-предложение...'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </div>
      <div class="modal-footer" id="taskAiRegenerateFooter">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn crm-btn-primary" id="taskAiRegenerateBtn" data-i18n="task_detail.modal_ai_regenerate_btn"><?= htmlspecialchars($t('task_detail.modal_ai_regenerate_btn', 'Перегенерировать'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="taskAiHighRiskModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="task_detail.modal_ai_high_risk_title"><i class="fa-solid fa-triangle-exclamation text-warning me-2"></i><?= htmlspecialchars($t('task_detail.modal_ai_high_risk_title', 'Действие с повышенным риском'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2" data-i18n="task_detail.modal_ai_high_risk_desc"><?= htmlspecialchars($t('task_detail.modal_ai_high_risk_desc', 'AI предлагает действие, которое может существенно изменить задачу:'), ENT_QUOTES, 'UTF-8') ?></p>
        <div id="taskAiHighRiskActions" class="crm-info-panel p-3 mb-3 small">—</div>
        <p class="mb-0" data-i18n="task_detail.modal_ai_high_risk_confirm"><?= htmlspecialchars($t('task_detail.modal_ai_high_risk_confirm', 'Продолжить применение?'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn crm-btn-warning" id="taskAiHighRiskConfirmBtn" data-i18n="task_detail.modal_ai_high_risk_confirm_btn"><?= htmlspecialchars($t('task_detail.modal_ai_high_risk_confirm_btn', 'Продолжить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="createDependencyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="dependency.modal_title"><?= htmlspecialchars($t('dependency.modal_title', 'Добавить зависимость'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <form id="dependencyCreateForm">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" data-i18n="dependency.modal_task_label"><?= htmlspecialchars($t('dependency.modal_task_label', 'Задача'), ENT_QUOTES, 'UTF-8') ?></label>
            <select id="depTargetTaskSelect" class="form-select" name="target_task_public_id">
              <option value=""><?= htmlspecialchars($t('dependency.modal_task_placeholder', 'Поиск задачи по названию...'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>
            <small class="text-muted" data-i18n="dependency.modal_same_project_hint"><?= htmlspecialchars($t('dependency.modal_same_project_hint', 'Показаны только задачи из того же проекта'), ENT_QUOTES, 'UTF-8') ?></small>
          </div>
          <div class="mb-3">
            <label class="form-label" data-i18n="dependency.modal_type_label"><?= htmlspecialchars($t('dependency.modal_type_label', 'Тип зависимости'), ENT_QUOTES, 'UTF-8') ?></label>
            <select class="form-select" name="dependency_type" required>
              <option value="FS"><?= htmlspecialchars($t('dependency.type_fs', 'Финиш-Старт'), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($t('dependency.modal_type_fs_desc', 'Начинается после завершения'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="SS"><?= htmlspecialchars($t('dependency.type_ss', 'Старт-Старт'), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($t('dependency.modal_type_ss_desc', 'Начинается одновременно'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="FF"><?= htmlspecialchars($t('dependency.type_ff', 'Финиш-Финиш'), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($t('dependency.modal_type_ff_desc', 'Заканчивается одновременно'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="SF"><?= htmlspecialchars($t('dependency.type_sf', 'Старт-Финиш'), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($t('dependency.modal_type_sf_desc', 'Заканчивается после начала'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="BLOCKS"><?= htmlspecialchars($t('dependency.type_blocks', 'Блокирует'), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($t('dependency.modal_type_blocks_desc', 'Блокирует выполнение'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="dependency.modal_cancel_btn"><?= htmlspecialchars($t('dependency.modal_cancel_btn', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          <button class="btn crm-btn-primary" type="submit" data-i18n="dependency.modal_add_btn"><?= htmlspecialchars($t('dependency.modal_add_btn', 'Добавить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Task Estimate Assign Modal -->
<div class="modal fade" id="taskEstimateAssignModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="task_detail.estimates_modal_title"><?= htmlspecialchars($t('task_detail.estimates_modal_title', 'Назначить оценку'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="taskEstimateSetSelect" data-i18n="task_detail.estimates_modal_set_label"><?= htmlspecialchars($t('task_detail.estimates_modal_set_label', 'Набор оценок'), ENT_QUOTES, 'UTF-8') ?></label>
          <select class="form-select" id="taskEstimateSetSelect">
            <option value="" data-i18n="task_detail.estimates_modal_select_set"><?= htmlspecialchars($t('task_detail.estimates_modal_select_set', 'Выберите набор...'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label" for="taskEstimateOptionSelect" data-i18n="task_detail.estimates_modal_option_label"><?= htmlspecialchars($t('task_detail.estimates_modal_option_label', 'Значение'), ENT_QUOTES, 'UTF-8') ?></label>
          <select class="form-select" id="taskEstimateOptionSelect" disabled>
            <option value="" data-i18n="task_detail.estimates_modal_select_option"><?= htmlspecialchars($t('task_detail.estimates_modal_select_option', 'Выберите значение...'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn crm-btn-primary" id="taskEstimateAssignBtn" data-i18n="task_detail.estimates_modal_assign_btn"><?= htmlspecialchars($t('task_detail.estimates_modal_assign_btn', 'Назначить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

</main></div></div>
<div class="modal fade" id="taskKnowledgeAttachModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="task_detail.attach_knowledge_title"><?= htmlspecialchars($t('task_detail.attach_knowledge_title', 'Прикрепить статью базы знаний'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="taskKnowledgeSearch" data-i18n="task_detail.attach_knowledge_search_label"><?= htmlspecialchars($t('task_detail.attach_knowledge_search_label', 'Поиск статьи'), ENT_QUOTES, 'UTF-8') ?></label>
          <input id="taskKnowledgeSearch" class="form-control" type="search" autocomplete="off" placeholder="<?= htmlspecialchars($t('task_detail.attach_knowledge_search_placeholder', 'Введите название статьи...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="task_detail.attach_knowledge_search_placeholder">
        </div>
        <div id="taskKnowledgeAttachResults"><div class="text-muted small" data-i18n="task_detail.attach_knowledge_loading"><?= htmlspecialchars($t('task_detail.attach_knowledge_loading', 'Загрузка статей...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button></div>
    </div>
  </div>
</div>

<script>
(function () {
  var taskId = null;
  var urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('route') === 'task-detail') taskId = urlParams.get('task_public_id');
  if (!taskId) return;
  var createKnowledgeBtn = document.getElementById('taskCreateKnowledgeBtn');
  if (createKnowledgeBtn) createKnowledgeBtn.href = 'index.php?route=knowledge&entity_type=task&entity_public_id=' + encodeURIComponent(taskId);

  function getApi() { return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null; }

  function waitForApi(cb, n) {
    if (getApi()) { cb(); return; }
    if ((n || 0) > 100) return;
    setTimeout(function () { waitForApi(cb, (n || 0) + 1); }, 100);
  }

  waitForApi(async function () {
    var api = getApi();
    var listEl = document.getElementById('taskKnowledgeList');
    if (!listEl) return;
    try {
      var envelope = await api.request('api/v1/knowledge/entities/task/' + encodeURIComponent(taskId) + '/pages', { method: 'GET' });
      var items = envelope.data && envelope.data.items || [];
      if (!items.length) {
        listEl.innerHTML = '<div class="text-muted small"><?= htmlspecialchars($t('task_detail.knowledge_empty', 'Нет связанных страниц'), ENT_QUOTES, 'UTF-8') ?></div>';
      } else {
        listEl.innerHTML = '<ul class="list-unstyled mb-0">' + items.map(function (p) {
          return '<li class="mb-1"><a href="index.php?route=knowledge-page&amp;id=' + encodeURIComponent(p.public_id) + '">' + escapeHtml(p.title || '') + '</a> <span class="text-muted small">(' + escapeHtml(p.relation_type || 'related') + ')</span></li>';
        }).join('') + '</ul>';
      }
    } catch (e) {
      listEl.innerHTML = '<div class="text-muted small">—</div>';
    }
  });

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"]/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[ch] || ch;
    });
  }

  (function initKnowledgeAttach() {
    var attachBtn = document.getElementById('taskAttachKnowledgeBtn');
    var modalEl = document.getElementById('taskKnowledgeAttachModal');
    var searchInput = document.getElementById('taskKnowledgeSearch');
    var resultsEl = document.getElementById('taskKnowledgeAttachResults');
    if (!attachBtn || !modalEl || !searchInput || !resultsEl) return;

    var api = null;
    var searchTimer = null;
    var loading = false;
    var loadRequestId = 0;
    var linkedIds = {};
    var permissionPromise = null;

    function ensurePermissions() {
      if (permissionPromise) return permissionPromise;
      permissionPromise = (async function () {
        var attempts = 0;
        while (!getApi() && attempts < 100) {
          await new Promise(function (resolve) { window.setTimeout(resolve, 50); });
          attempts += 1;
        }
        api = getApi();
        if (!api) return false;
        if (typeof api.me === 'function') {
          try {
            await api.me();
          } catch (e) {
            return false;
          }
        }
        return true;
      })();
      return permissionPromise;
    }

    async function refreshPermission() {
      var ready = await ensurePermissions();
      if (!ready || !api || typeof api.hasPermission !== 'function') {
        attachBtn.classList.add('d-none');
        return;
      }
      attachBtn.classList.toggle('d-none', !canAttach());
    }

    function text(key, fallback) {
      return window.CRM && window.CRM.i18n && typeof window.CRM.i18n.t === 'function'
        ? window.CRM.i18n.t(key, fallback)
        : fallback;
    }

    function canAttach() {
      return !api || typeof api.hasPermission !== 'function' || (api.hasPermission('knowledge.view') && api.hasPermission('task.manage'));
    }

    function setMessage(message, className) {
      resultsEl.innerHTML = '<div class="text-muted small ' + (className || '') + '">' + escapeHtml(message) + '</div>';
    }

    async function loadLinkedIds() {
      linkedIds = {};
      var envelope = await api.request('api/v1/knowledge/entities/task/' + encodeURIComponent(taskId) + '/pages', { method: 'GET' });
      var items = envelope && envelope.data && Array.isArray(envelope.data.items) ? envelope.data.items : [];
      items.forEach(function (item) {
        var id = String(item && item.public_id || '').trim();
        if (id) linkedIds[id] = true;
      });
    }

    async function loadArticles() {
      var requestId = ++loadRequestId;
      var query = String(searchInput.value || '').trim();
      if (loading) return;
      loading = true;
      setMessage(text('task_detail.attach_knowledge_loading', 'Загрузка статей...'));
      try {
        var envelope = await api.request('api/v1/knowledge/pages', {
          method: 'GET',
          query: { limit: 50, q: query, min_access: 'view' }
        });
        if (requestId !== loadRequestId) return;
        var items = envelope && envelope.data && Array.isArray(envelope.data.items) ? envelope.data.items : [];
        var available = items.filter(function (item) {
          return item && item.public_id && !linkedIds[String(item.public_id)];
        });
        if (!available.length) {
          setMessage(text('task_detail.attach_knowledge_empty', 'Статьи не найдены'));
          return;
        }
        resultsEl.innerHTML = available.map(function (item) {
          var id = encodeURIComponent(String(item.public_id));
          var title = escapeHtml(item.title || text('knowledge.untitled', 'Без названия'));
          var meta = [item.space_title, item.status].filter(Boolean).map(escapeHtml).join(' · ');
          return '<button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-3" data-task-knowledge-page-id="' + id + '"><span class="text-start"><strong class="d-block">' + title + '</strong>' + (meta ? '<span class="small text-muted">' + meta + '</span>' : '') + '</span><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-link"></i></span></button>';
        }).join('');
      } catch (e) {
        setMessage(text('task_detail.attach_knowledge_error', 'Не удалось выполнить операцию'), 'text-danger');
      } finally {
        loading = false;
        if (requestId !== loadRequestId) loadArticles();
      }
    }

    attachBtn.addEventListener('click', async function () {
      var ready = await ensurePermissions();
      if (!ready || !api || !canAttach()) return;
      try {
        await loadLinkedIds();
      } catch (e) {
        setMessage(text('task_detail.attach_knowledge_error', 'Не удалось выполнить операцию'), 'text-danger');
        return;
      }
      searchInput.value = '';
      resultsEl.innerHTML = '';
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
      loadArticles();
    });

    refreshPermission();

    searchInput.addEventListener('input', function () {
      if (!api) api = getApi();
      if (!api) return;
      if (searchTimer) window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(loadArticles, 250);
    });

    resultsEl.addEventListener('click', async function (event) {
      var button = event.target.closest('[data-task-knowledge-page-id]');
      if (!button || !api) return;
      button.disabled = true;
      try {
        var pageId = decodeURIComponent(button.getAttribute('data-task-knowledge-page-id') || '');
        await api.request('api/v1/tasks/' + encodeURIComponent(taskId) + '/knowledge-pages', {
          method: 'POST',
          body: { page_public_id: pageId, relation_type: 'related' },
          idempotent: true
        });
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        window.location.reload();
      } catch (e) {
        button.disabled = false;
        setMessage(text('task_detail.attach_knowledge_error', 'Не удалось выполнить операцию'), 'text-danger');
      }
    });
  })();

  // Load activity feed when the Activity tab is first shown
  var activityTab = document.querySelector('[data-bs-target="#detailActivity"]');
  if (activityTab && typeof window.loadTaskActivity === 'function') {
    var loaded = false;
    activityTab.addEventListener('shown.bs.tab', function () {
      if (!loaded) {
        loaded = true;
        window.loadTaskActivity(taskId, 1, {});
      }
    });
  }
})();
</script>
