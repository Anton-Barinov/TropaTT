<?php declare(strict_types=1); ?>
<?php $title = $t('project_detail.title', 'TropaTT — Карточка проекта'); ?>
<body data-page="projects" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=projects" data-i18n="projects.breadcrumb"><?= htmlspecialchars($t('projects.breadcrumb', 'Проекты'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" id="projectBreadcrumbTitle" data-i18n="project_detail.breadcrumb"><?= htmlspecialchars($t('project_detail.breadcrumb', 'Карточка проекта'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" data-i18n="project_detail.page_title"><?= htmlspecialchars($t('project_detail.page_title', 'Карточка проекта'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="project_detail.loading_data"><?= htmlspecialchars($t('project_detail.loading_data', 'Загрузка данных проекта...'), ENT_QUOTES, 'UTF-8') ?></p><div class="d-flex gap-2 flex-wrap mt-2"><span class="crm-chip" id="projectPublicIdChip" data-i18n="project_detail.label_id"><?= htmlspecialchars($t('project_detail.label_id', 'ID: —'), ENT_QUOTES, 'UTF-8') ?></span><span class="crm-chip" id="projectRowVersionChip" data-i18n="project_detail.label_version"><?= htmlspecialchars($t('project_detail.label_version', 'Версия: —'), ENT_QUOTES, 'UTF-8') ?></span><span class="crm-badge archived" id="projectStatusBadge">—</span></div></div><div class="crm-page-actions"><a class="btn crm-btn-secondary" href="index.php?route=projects" data-i18n="project_detail.link_back_to_projects"><?= htmlspecialchars($t('project_detail.link_back_to_projects', 'К списку проектов'), ENT_QUOTES, 'UTF-8') ?></a><a class="btn crm-btn-secondary" href="index.php?route=companies" data-i18n="project_detail.link_companies"><?= htmlspecialchars($t('project_detail.link_companies', 'Компании'), ENT_QUOTES, 'UTF-8') ?></a><a class="btn crm-btn-secondary" href="index.php?route=contacts" data-i18n="project_detail.link_contacts"><?= htmlspecialchars($t('project_detail.link_contacts', 'Контакты'), ENT_QUOTES, 'UTF-8') ?></a><button class="btn crm-btn-secondary" type="button" data-open-drawer="projectQuickPreviewDrawer" data-i18n="project_detail.btn_preview"><?= htmlspecialchars($t('project_detail.btn_preview', 'Предпросмотр'), ENT_QUOTES, 'UTF-8') ?></button></div></div>
<div class="row g-3">
  <div class="col-lg-8">
    <section class="crm-card mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0" data-i18n="project_detail.section_parameters"><?= htmlspecialchars($t('project_detail.section_parameters', 'Параметры проекта'), ENT_QUOTES, 'UTF-8') ?></h2>
        <small class="text-muted" data-i18n="project_detail.section_parameters_note"><?= htmlspecialchars($t('project_detail.section_parameters_note', 'Редактирование по блокам'), ENT_QUOTES, 'UTF-8') ?></small>
      </div>
      <div id="projectEditAccessNote" class="alert alert-info py-2" data-i18n="project_detail.edit_access_note"><?= htmlspecialchars($t('project_detail.edit_access_note', 'Права редактирования проверяются...'), ENT_QUOTES, 'UTF-8') ?></div>
      <div id="projectEditBlocks"><div class="text-muted" data-i18n="project_detail.loading_parameters"><?= htmlspecialchars($t('project_detail.loading_parameters', 'Загрузка параметров проекта...'), ENT_QUOTES, 'UTF-8') ?></div></div>
    </section>
    <section class="crm-card mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0" data-i18n="project_detail.section_create_task"><?= htmlspecialchars($t('project_detail.section_create_task', 'Создать задачу в проекте'), ENT_QUOTES, 'UTF-8') ?></h2>
        <small class="text-muted" data-i18n="project_detail.section_create_task_note"><?= htmlspecialchars($t('project_detail.section_create_task_note', 'Задача будет привязана к текущему проекту'), ENT_QUOTES, 'UTF-8') ?></small>
      </div>
      <form id="projectCreateTaskForm" novalidate>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label" for="projectTaskTitleInput" data-i18n="project_detail.field_task_title"><?= htmlspecialchars($t('project_detail.field_task_title', 'Название задачи'), ENT_QUOTES, 'UTF-8') ?></label>
            <input class="form-control" id="projectTaskTitleInput" name="title" maxlength="255" required placeholder="<?= htmlspecialchars($t('project_detail.placeholder_task_title', 'Например: Подготовить релиз'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="project_detail.placeholder_task_title">
          </div>
          <div class="col-md-3">
            <label class="form-label" for="projectTaskStatusInput" data-i18n="project_detail.field_status"><?= htmlspecialchars($t('project_detail.field_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label>
            <select class="form-select" id="projectTaskStatusInput" name="status">
              <option value="new" data-i18n="projects.status_new"><?= htmlspecialchars($t('projects.status_new', 'К выполнению'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="in_progress" data-i18n="projects.status_in_progress"><?= htmlspecialchars($t('projects.status_in_progress', 'В работе'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="blocked" data-i18n="projects.status_blocked"><?= htmlspecialchars($t('projects.status_blocked', 'Блокирован'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="done" data-i18n="projects.status_done"><?= htmlspecialchars($t('projects.status_done', 'Завершен'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="projectTaskPriorityInput" data-i18n="project_detail.field_priority"><?= htmlspecialchars($t('project_detail.field_priority', 'Приоритет'), ENT_QUOTES, 'UTF-8') ?></label>
            <select class="form-select" id="projectTaskPriorityInput" name="priority">
              <option value="normal" data-i18n="priority.normal"><?= htmlspecialchars($t('priority.normal', 'Нормальный'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="low" data-i18n="priority.low"><?= htmlspecialchars($t('priority.low', 'Низкий'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="high" data-i18n="priority.high"><?= htmlspecialchars($t('priority.high', 'Высокий'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="urgent" data-i18n="priority.urgent"><?= htmlspecialchars($t('priority.urgent', 'Срочный'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label" for="projectTaskDescriptionInput" data-i18n="project_detail.field_description"><?= htmlspecialchars($t('project_detail.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label>
            <textarea class="form-control" id="projectTaskDescriptionInput" name="description" rows="2" placeholder="<?= htmlspecialchars($t('project_detail.placeholder_task_description', 'Что нужно сделать'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="project_detail.placeholder_task_description"></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="projectTaskDueAtInput" data-i18n="project_detail.field_due_at"><?= htmlspecialchars($t('project_detail.field_due_at', 'Срок'), ENT_QUOTES, 'UTF-8') ?></label>
            <input class="form-control" id="projectTaskDueAtInput" name="due_at" type="datetime-local" min="<?= date('Y-m-d\TH:i') ?>">
          </div>
        </div>
        <div class="d-flex gap-2 mt-3">
          <button type="submit" class="btn crm-btn-primary" id="projectCreateTaskBtn" data-i18n="project_detail.btn_create_task"><?= htmlspecialchars($t('project_detail.btn_create_task', 'Создать задачу'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="button" class="btn crm-btn-muted" id="projectCreateTaskResetBtn" data-i18n="project_detail.btn_clear"><?= htmlspecialchars($t('project_detail.btn_clear', 'Очистить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
    </section>
    <section class="crm-card mb-3"><h2 class="h6" data-i18n="project_detail.section_milestones"><?= htmlspecialchars($t('project_detail.section_milestones', 'Этапы проекта'), ENT_QUOTES, 'UTF-8') ?></h2><div id="projectMilestonesSummary" class="d-flex gap-2 flex-wrap mb-2"></div><div class="crm-timeline" id="projectMilestonesList"><div class="crm-timeline-item" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div></section>
    <section class="crm-card mb-3"><h2 class="h6" data-i18n="project_detail.section_tasks"><?= htmlspecialchars($t('project_detail.section_tasks', 'Связанные задачи'), ENT_QUOTES, 'UTF-8') ?></h2><div class="table-responsive"><table class="table crm-table"><thead><tr><th data-i18n="project_detail.th_task"><?= htmlspecialchars($t('project_detail.th_task', 'Задача'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="project_detail.th_assignee"><?= htmlspecialchars($t('project_detail.th_assignee', 'Исполнитель'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="project_detail.th_status"><?= htmlspecialchars($t('project_detail.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="project_detail.th_due"><?= htmlspecialchars($t('project_detail.th_due', 'Срок'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="projectTasksTableBody"><tr><td colspan="4" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div></section>
    <section class="crm-card"><h2 class="h6" data-i18n="project_detail.section_activity"><?= htmlspecialchars($t('project_detail.section_activity', 'Недавняя активность'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-timeline" id="projectActivityList"><div class="crm-timeline-item" data-i18n="project_detail.loading_activity"><?= htmlspecialchars($t('project_detail.loading_activity', 'Данные загружаются...'), ENT_QUOTES, 'UTF-8') ?></div></div></section>
    <section class="crm-card"><h2 class="h6" data-i18n="project_detail.section_history"><?= htmlspecialchars($t('project_detail.section_history', 'История изменений полей'), ENT_QUOTES, 'UTF-8') ?></h2><p class="text-muted small mb-3" data-i18n="project_detail.section_history_note"><?= htmlspecialchars($t('project_detail.section_history_note', 'Хронология изменений атрибутов проекта.'), ENT_QUOTES, 'UTF-8') ?></p><div class="table-responsive"><table class="table table-sm crm-table mb-0"><thead><tr><th data-i18n="project_detail.th_date"><?= htmlspecialchars($t('project_detail.th_date', 'Дата'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="project_detail.th_field"><?= htmlspecialchars($t('project_detail.th_field', 'Поле'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="project_detail.th_was"><?= htmlspecialchars($t('project_detail.th_was', 'Было'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="project_detail.th_became"><?= htmlspecialchars($t('project_detail.th_became', 'Стало'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="project_detail.th_changed_by"><?= htmlspecialchars($t('project_detail.th_changed_by', 'Кем изменено'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="projectHistoryList"><tr><td colspan="5" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div></section>
  </div>
  <aside class="col-lg-4">
    <section class="crm-card mb-3" id="projectAiCard" data-requires-ai-use="1" data-ai-state="idle">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div>
          <h2 class="h6 mb-1" data-i18n="project_detail.section_ai"><?= htmlspecialchars($t('project_detail.section_ai', 'AI по проекту'), ENT_QUOTES, 'UTF-8') ?></h2>
          <div class="small text-muted" id="projectAiState" data-i18n="project_detail.ai_state_idle"><?= htmlspecialchars($t('project_detail.ai_state_idle', 'AI-сводка проекта не сформирована.'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </div>
      <div class="d-flex gap-2 mb-2 flex-wrap" id="projectAiPrimaryActions">
        <button type="button" class="btn btn-sm crm-btn-primary crm-btn-compact" id="projectAiSummaryBtn" data-i18n="project_detail.ai_summary_btn"><?= htmlspecialchars($t('project_detail.ai_summary_btn', 'AI-сводка'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm crm-btn-secondary crm-btn-compact" id="projectAiRisksBtn" data-i18n="project_detail.ai_risks_btn"><?= htmlspecialchars($t('project_detail.ai_risks_btn', 'AI-риски'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm crm-btn-secondary crm-btn-compact" id="projectAiClientReportBtn" data-i18n="project_detail.ai_client_report_btn"><?= htmlspecialchars($t('project_detail.ai_client_report_btn', 'Client report draft'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm crm-btn-secondary crm-btn-compact" id="projectAiNextActionsBtn" data-i18n="project_detail.ai_next_actions_btn"><?= htmlspecialchars($t('project_detail.ai_next_actions_btn', 'Следующие действия'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div class="d-flex gap-2 mb-2 flex-wrap" id="projectAiSecondaryActions">
        <button type="button" class="btn btn-sm crm-btn-secondary crm-btn-compact" id="projectAiPreviewBtn" disabled data-i18n="project_detail.ai_preview_btn"><?= htmlspecialchars($t('project_detail.ai_preview_btn', 'Предпросмотр'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm crm-btn-muted crm-btn-compact" id="projectAiDismissBtn" disabled data-i18n="project_detail.ai_dismiss_btn"><?= htmlspecialchars($t('project_detail.ai_dismiss_btn', 'Отклонить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div class="crm-empty-state mb-2" id="projectAiSummaryText" data-i18n="project_detail.ai_summary_empty"><?= htmlspecialchars($t('project_detail.ai_summary_empty', 'Нажмите «AI-сводка», чтобы получить AI-рекомендацию по проекту.'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="crm-info-panel mb-2 d-none" id="projectAiReportDraftWrap">
        <small class="text-muted" data-i18n="project_detail.ai_report_draft_label"><?= htmlspecialchars($t('project_detail.ai_report_draft_label', 'Client report draft (read-only)'), ENT_QUOTES, 'UTF-8') ?></small>
        <div class="mt-1" id="projectAiReportDraftText">—</div>
      </div>
      <div class="row g-2">
        <div class="col-12">
          <div class="crm-info-panel">
            <small class="text-muted" data-i18n="project_detail.ai_risks_label"><?= htmlspecialchars($t('project_detail.ai_risks_label', 'Риски и вопросы'), ENT_QUOTES, 'UTF-8') ?></small>
            <div class="mt-1" id="projectAiRisksQuestions">—</div>
          </div>
        </div>
        <div class="col-12">
          <div class="crm-info-panel">
            <small class="text-muted" data-i18n="project_detail.ai_next_actions_label"><?= htmlspecialchars($t('project_detail.ai_next_actions_label', 'Следующие действия'), ENT_QUOTES, 'UTF-8') ?></small>
            <div class="mt-1" id="projectAiNextActions">—</div>
          </div>
        </div>
      </div>
    </section>
    <section class="crm-card mb-3"><h2 class="h6" data-i18n="project_detail.section_metrics"><?= htmlspecialchars($t('project_detail.section_metrics', 'Метрики проекта'), ENT_QUOTES, 'UTF-8') ?></h2><div id="projectMetricsList"><div class="crm-metric-tile mb-2"><small class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка'), ENT_QUOTES, 'UTF-8') ?></small><div>...</div></div></div></section>
    <section class="crm-card mb-3"><h2 class="h6" data-i18n="project_detail.section_team"><?= htmlspecialchars($t('project_detail.section_team', 'Команда'), ENT_QUOTES, 'UTF-8') ?></h2><div id="projectTeamList"><div class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div></section>
    <section class="crm-card mb-3" id="projectKnowledgeSection"><h2 class="h6" data-i18n="project_detail.section_knowledge"><?= htmlspecialchars($t('project_detail.section_knowledge', 'База знаний'), ENT_QUOTES, 'UTF-8') ?></h2><div id="projectKnowledgeList"><div class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div><div class="mt-2 d-flex gap-2 flex-wrap"><a class="btn btn-sm crm-btn-primary" href="index.php?route=knowledge" data-i18n="project_detail.btn_knowledge"><?= htmlspecialchars($t('project_detail.btn_knowledge', 'Перейти в базу знаний'), ENT_QUOTES, 'UTF-8') ?></a><a id="projectCreateKnowledgeBtn" class="btn btn-sm crm-btn-secondary" href="index.php?route=knowledge" data-i18n="project_detail.btn_create_knowledge"><?= htmlspecialchars($t('project_detail.btn_create_knowledge', 'Создать связанную страницу'), ENT_QUOTES, 'UTF-8') ?></a></div></section>
    <section class="crm-card"><h2 class="h6" data-i18n="project_detail.section_summary"><?= htmlspecialchars($t('project_detail.section_summary', 'Сводка проекта'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-info-panel mb-2"><small class="text-muted" data-i18n="project_detail.summary_author"><?= htmlspecialchars($t('project_detail.summary_author', 'Автор'), ENT_QUOTES, 'UTF-8') ?></small><div id="projectSummaryCreator">—</div></div><div class="crm-info-panel mb-2"><small class="text-muted" data-i18n="project_detail.summary_manager"><?= htmlspecialchars($t('project_detail.summary_manager', 'Менеджер'), ENT_QUOTES, 'UTF-8') ?></small><div id="projectSummaryManager">—</div></div><div class="crm-info-panel mb-2"><small class="text-muted" data-i18n="project_detail.summary_team"><?= htmlspecialchars($t('project_detail.summary_team', 'Команда'), ENT_QUOTES, 'UTF-8') ?></small><div id="projectSummaryTeam">—</div></div><div class="crm-info-panel mb-2"><small class="text-muted" data-i18n="project_detail.summary_client"><?= htmlspecialchars($t('project_detail.summary_client', 'Клиент'), ENT_QUOTES, 'UTF-8') ?></small><div id="projectSummaryClient">—</div></div><div class="crm-info-panel mb-2"><small class="text-muted" data-i18n="project_detail.summary_created_at"><?= htmlspecialchars($t('project_detail.summary_created_at', 'Создан'), ENT_QUOTES, 'UTF-8') ?></small><div id="projectSummaryCreatedAt">—</div></div><div class="crm-info-panel mb-2"><small class="text-muted" data-i18n="project_detail.summary_updated_at"><?= htmlspecialchars($t('project_detail.summary_updated_at', 'Обновлён'), ENT_QUOTES, 'UTF-8') ?></small><div id="projectSummaryUpdatedAt">—</div></div><div class="crm-info-panel mb-2"><small class="text-muted" data-i18n="project_detail.summary_status"><?= htmlspecialchars($t('project_detail.summary_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></small><div id="projectSummaryStatus">—</div></div><div class="crm-info-panel mb-2"><small class="text-muted" data-i18n="project_detail.summary_priority"><?= htmlspecialchars($t('project_detail.summary_priority', 'Приоритет'), ENT_QUOTES, 'UTF-8') ?></small><div id="projectSummaryPriority">—</div></div><div class="crm-info-panel"><small class="text-muted" data-i18n="project_detail.summary_description"><?= htmlspecialchars($t('project_detail.summary_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></small><div id="projectSummaryDescription">—</div></div></section>
  </aside>
</div>
</main></div></div>
<script>
(function () {
  var projectId = null;
  var urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('route') === 'project-detail') projectId = urlParams.get('project_public_id');
  if (!projectId) return;
  var createKnowledgeBtn = document.getElementById('projectCreateKnowledgeBtn');
  if (createKnowledgeBtn) createKnowledgeBtn.href = 'index.php?route=knowledge&entity_type=project&entity_public_id=' + encodeURIComponent(projectId);

  function getApi() { return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null; }

  function waitForApi(cb, n) {
    if (getApi()) { cb(); return; }
    if ((n || 0) > 100) return;
    setTimeout(function () { waitForApi(cb, (n || 0) + 1); }, 100);
  }

  waitForApi(async function () {
    var api = getApi();
    var listEl = document.getElementById('projectKnowledgeList');
    if (!listEl) return;
    try {
      var envelope = await api.request('api/v1/knowledge/entities/project/' + encodeURIComponent(projectId) + '/pages', { method: 'GET' });
      var items = envelope.data && envelope.data.items || [];
      if (!items.length) {
        listEl.innerHTML = '<div class="text-muted small"><?= htmlspecialchars($t('project_detail.knowledge_empty', 'Связанных страниц нет'), ENT_QUOTES, 'UTF-8') ?></div>';
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
})();
</script>
</body>
