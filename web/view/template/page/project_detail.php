<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Карточка проекта'; ?>
<body data-page="projects" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=projects">Проекты</a></li><li class="breadcrumb-item active" id="projectBreadcrumbTitle">Карточка проекта</li></ol><h1 class="crm-page-title">Карточка проекта</h1><p class="crm-subtitle">Загрузка данных проекта...</p><div class="d-flex gap-2 flex-wrap mt-2"><span class="crm-chip" id="projectPublicIdChip">ID: —</span><span class="crm-chip" id="projectRowVersionChip">Версия: —</span><span class="crm-badge archived" id="projectStatusBadge">—</span></div></div><div class="crm-page-actions"><a class="btn crm-btn-secondary" href="index.php?route=projects">К списку проектов</a><a class="btn crm-btn-secondary" href="index.php?route=companies">Компании</a><a class="btn crm-btn-secondary" href="index.php?route=contacts">Контакты</a><button class="btn crm-btn-secondary" type="button" data-open-drawer="projectQuickPreviewDrawer">Предпросмотр</button></div></div>
<div class="row g-3">
  <div class="col-lg-8">
    <section class="crm-card mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">Параметры проекта</h2>
        <small class="text-muted">Редактирование по блокам</small>
      </div>
      <div id="projectEditAccessNote" class="alert alert-info py-2">Права редактирования проверяются...</div>
      <div id="projectEditBlocks"><div class="text-muted">Загрузка параметров проекта...</div></div>
    </section>
    <section class="crm-card mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">Создать задачу в проекте</h2>
        <small class="text-muted">Задача будет привязана к текущему проекту</small>
      </div>
      <form id="projectCreateTaskForm" novalidate>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label" for="projectTaskTitleInput">Название задачи</label>
            <input class="form-control" id="projectTaskTitleInput" name="title" maxlength="255" required placeholder="Например: Подготовить релиз">
          </div>
          <div class="col-md-3">
            <label class="form-label" for="projectTaskStatusInput">Статус</label>
            <select class="form-select" id="projectTaskStatusInput" name="status">
              <option value="new">К выполнению</option>
              <option value="in_progress">В работе</option>
              <option value="blocked">Блокирован</option>
              <option value="done">Завершен</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="projectTaskPriorityInput">Приоритет</label>
            <select class="form-select" id="projectTaskPriorityInput" name="priority">
              <option value="normal">Нормальный</option>
              <option value="low">Низкий</option>
              <option value="high">Высокий</option>
              <option value="urgent">Срочный</option>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label" for="projectTaskDescriptionInput">Описание</label>
            <textarea class="form-control" id="projectTaskDescriptionInput" name="description" rows="2" placeholder="Что нужно сделать"></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="projectTaskDueAtInput">Срок</label>
            <input class="form-control" id="projectTaskDueAtInput" name="due_at" type="datetime-local" min="<?= date('Y-m-d\TH:i') ?>">
          </div>
        </div>
        <div class="d-flex gap-2 mt-3">
          <button type="submit" class="btn crm-btn-primary" id="projectCreateTaskBtn">Создать задачу</button>
          <button type="button" class="btn crm-btn-muted" id="projectCreateTaskResetBtn">Очистить</button>
        </div>
      </form>
    </section>
    <section class="crm-card mb-3"><h2 class="h6">Этапы проекта</h2><div id="projectMilestonesSummary" class="d-flex gap-2 flex-wrap mb-2"></div><div class="crm-timeline" id="projectMilestonesList"><div class="crm-timeline-item">Загрузка...</div></div></section>
    <section class="crm-card mb-3"><h2 class="h6">Связанные задачи</h2><div class="table-responsive"><table class="table crm-table"><thead><tr><th>Задача</th><th>Исполнитель</th><th>Статус</th><th>Срок</th></tr></thead><tbody id="projectTasksTableBody"><tr><td colspan="4" class="text-muted">Загрузка...</td></tr></tbody></table></div></section>
    <section class="crm-card"><h2 class="h6">Недавняя активность</h2><div class="crm-timeline" id="projectActivityList"><div class="crm-timeline-item">Данные загружаются...</div></div></section>
    <section class="crm-card"><h2 class="h6">История изменений полей</h2><p class="text-muted small mb-3">Хронология изменений атрибутов проекта.</p><div class="table-responsive"><table class="table table-sm crm-table mb-0"><thead><tr><th>Дата</th><th>Поле</th><th>Было</th><th>Стало</th><th>Кем изменено</th></tr></thead><tbody id="projectHistoryList"><tr><td colspan="5" class="text-muted">Загрузка...</td></tr></tbody></table></div></section>
  </div>
  <aside class="col-lg-4">
    <section class="crm-card mb-3" id="projectAiCard" data-requires-ai-use="1" data-ai-state="idle">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div>
          <h2 class="h6 mb-1">AI по проекту</h2>
          <div class="small text-muted" id="projectAiState">AI-сводка проекта не сформирована.</div>
        </div>
      </div>
      <div class="d-flex gap-2 mb-2 flex-wrap" id="projectAiPrimaryActions">
        <button type="button" class="btn btn-sm crm-btn-primary crm-btn-compact" id="projectAiSummaryBtn">AI-сводка</button>
        <button type="button" class="btn btn-sm crm-btn-secondary crm-btn-compact" id="projectAiRisksBtn">AI-риски</button>
        <button type="button" class="btn btn-sm crm-btn-secondary crm-btn-compact" id="projectAiClientReportBtn">Client report draft</button>
        <button type="button" class="btn btn-sm crm-btn-secondary crm-btn-compact" id="projectAiNextActionsBtn">Следующие действия</button>
      </div>
      <div class="d-flex gap-2 mb-2 flex-wrap" id="projectAiSecondaryActions">
        <button type="button" class="btn btn-sm crm-btn-secondary crm-btn-compact" id="projectAiPreviewBtn" disabled>Открыть preview</button>
        <button type="button" class="btn btn-sm crm-btn-muted crm-btn-compact" id="projectAiDismissBtn" disabled>Отклонить</button>
      </div>
      <div class="crm-empty-state mb-2" id="projectAiSummaryText">Нажмите «AI-сводка», чтобы получить AI-рекомендацию по проекту.</div>
      <div class="crm-info-panel mb-2 d-none" id="projectAiReportDraftWrap">
        <small class="text-muted">Client report draft (read-only)</small>
        <div class="mt-1" id="projectAiReportDraftText">—</div>
      </div>
      <div class="row g-2">
        <div class="col-12">
          <div class="crm-info-panel">
            <small class="text-muted">Риски и вопросы</small>
            <div class="mt-1" id="projectAiRisksQuestions">—</div>
          </div>
        </div>
        <div class="col-12">
          <div class="crm-info-panel">
            <small class="text-muted">Следующие действия</small>
            <div class="mt-1" id="projectAiNextActions">—</div>
          </div>
        </div>
      </div>
    </section>
    <section class="crm-card mb-3"><h2 class="h6">Метрики проекта</h2><div id="projectMetricsList"><div class="crm-metric-tile mb-2"><small class="text-muted">Загрузка</small><div>...</div></div></div></section>
    <section class="crm-card mb-3"><h2 class="h6">Команда</h2><div id="projectTeamList"><div class="text-muted">Загрузка...</div></div></section>
    <section class="crm-card"><h2 class="h6">Сводка проекта</h2><div class="crm-info-panel mb-2"><small class="text-muted">Автор</small><div id="projectSummaryCreator">—</div></div><div class="crm-info-panel mb-2"><small class="text-muted">Менеджер</small><div id="projectSummaryManager">—</div></div><div class="crm-info-panel mb-2"><small class="text-muted">Команда</small><div id="projectSummaryTeam">—</div></div><div class="crm-info-panel mb-2"><small class="text-muted">Клиент</small><div id="projectSummaryClient">—</div></div><div class="crm-info-panel mb-2"><small class="text-muted">Создан</small><div id="projectSummaryCreatedAt">—</div></div><div class="crm-info-panel mb-2"><small class="text-muted">Обновлён</small><div id="projectSummaryUpdatedAt">—</div></div><div class="crm-info-panel mb-2"><small class="text-muted">Статус</small><div id="projectSummaryStatus">—</div></div><div class="crm-info-panel mb-2"><small class="text-muted">Приоритет</small><div id="projectSummaryPriority">—</div></div><div class="crm-info-panel"><small class="text-muted">Описание</small><div id="projectSummaryDescription">—</div></div></section>
  </aside>
</div>
</main></div></div>
