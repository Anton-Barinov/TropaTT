<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Карточка задачи'; ?>
<body data-page="tasks" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav">
</nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid d-flex align-items-center gap-2"><button class="btn crm-btn-secondary d-xl-none" id="sidebarToggle" aria-label="Открыть меню"></button><div class="input-group crm-field-w-420" data-global-search><span class="input-group-text"></span><input id="taskDetailGlobalSearchInput" class="form-control" placeholder="Поиск" aria-label="Глобальный поиск"></div><div class="ms-auto d-flex gap-2" data-global-actions="1"><div class="dropdown"><button class="btn crm-btn-ghost dropdown-toggle" data-bs-toggle="dropdown">Пользователь</button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="index.php?route=profile">Профиль</a></li><li><hr class="dropdown-divider"></li><li><button class="dropdown-item" type="button" data-action="logout">Выйти</button></li></ul></div></div></div></header>
<main class="crm-content crm-task-detail-page">
<div class="crm-page-head crm-task-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=tasks">Задачи</a></li><li class="breadcrumb-item active">Карточка задачи</li></ol><h1 class="crm-page-title">Загрузка задачи...</h1><p class="crm-subtitle">Загрузка параметров задачи...</p></div><div class="crm-page-actions crm-task-page-actions"><a class="btn crm-btn-secondary" href="index.php?route=companies">Компании</a><a class="btn crm-btn-secondary" href="index.php?route=contacts">Контакты</a><button class="btn crm-btn-primary d-none" id="taskEditBtn" type="button" data-open-modal="editTaskModal">Редактировать</button><button class="btn crm-btn-danger-soft crm-task-delete-head" type="button" data-confirm-delete>Удалить</button></div></div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="crm-card mb-3 crm-task-hero">
      <div class="crm-task-hero-top">
        <div class="d-flex flex-wrap gap-2 mb-3" id="taskMetaChips"><span id="taskStatusBadge" class="crm-badge overdue">Просрочено</span><span class="crm-chip" id="taskPriorityChip">normal</span></div>
      </div>
      <div class="crm-task-hero-progress-wrap">
        <div class="crm-task-hero-progress-head">
          <div>
            <div class="crm-task-eyebrow">Прогресс выполнения</div>
            <div class="crm-task-progress-copy" id="taskProgressHint">Прогресс рассчитывается по текущему статусу.</div>
          </div>
        </div>
        <div class="progress mb-0 crm-task-progress" role="progressbar" aria-label="Прогресс"><div class="progress-bar" id="taskProgressBar" style="width: 0%">0%</div></div>
      </div>
      <div class="crm-task-control-panel">
        <div class="crm-task-control-panel-head">
          <div>
            <div class="crm-task-eyebrow">Управление статусом</div>
            <div class="crm-task-control-panel-title">Смена текущего статуса задачи</div>
          </div>
        </div>
        <div class="crm-task-quick-statuses">
          <label class="form-label mb-0 small text-muted" for="taskStatusSelect">Статус задачи</label>
          <select class="form-select form-select-sm" id="taskStatusSelect"></select>
          <button type="button" class="btn btn-sm crm-btn-primary crm-btn-compact" id="taskStatusApplyBtn" disabled>Применить</button>
        </div>
      </div>
      <form id="taskStatusReasonForm" class="mb-3 d-none">
        <div class="small text-muted mb-1">Причина смены статуса: <span id="taskStatusReasonTarget">—</span></div>
        <div class="d-flex gap-2">
          <textarea class="form-control form-control-sm" id="taskStatusReasonInput" rows="2" placeholder="Опишите, почему меняете статус и что изменилось"></textarea>
          <div class="d-flex flex-column gap-2">
            <button class="btn btn-sm crm-btn-primary" type="submit">Сохранить</button>
            <button class="btn btn-sm btn-light" type="button" id="taskStatusReasonCancelBtn">Отмена</button>
          </div>
        </div>
      </form>
      <div class="alert alert-info mb-0 crm-task-risk-alert" id="taskRiskAlert"><strong>Риск:</strong> оценка риска загружается...</div>
    </div>

    <ul class="nav nav-tabs mb-3 crm-task-tabs-nav" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#detailDesc" type="button">Описание</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#detailSubtasks" type="button">Подзадачи <span id="detailSubtasksCounter" class="badge text-bg-secondary crm-tab-counter d-none">0</span></button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#detailDependencies" type="button">Зависимости <span id="detailDependenciesCounter" class="badge text-bg-secondary crm-tab-counter d-none">0</span></button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#detailChecklists" type="button">Чеклисты <span id="detailChecklistsCounter" class="badge text-bg-secondary crm-tab-counter d-none">0</span></button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#detailWorklogs" type="button">Учет времени</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#detailFiles" type="button">Файлы</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#detailComments" type="button">Комментарии <span id="detailCommentsCounter" class="badge text-bg-secondary crm-tab-counter d-none">0</span></button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#detailActivity" type="button">Активность</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#detailHistory" type="button">История</button></li>
    </ul>

    <div class="tab-content">
      <section id="detailDesc" class="tab-pane fade show active crm-card crm-task-section">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 mb-0">Контекст и критерии готовности</h2>
          <button type="button" class="btn btn-sm crm-inline-icon-btn" data-task-inline-toggle="description" aria-label="Редактировать описание">
            <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span>
          </button>
        </div>
        <div id="taskDescriptionContent"><div class="text-muted">Детали задачи загружаются...</div></div>
        <form id="taskDescriptionInlineForm" class="mt-3 d-none">
          <label class="form-label">Описание</label>
          <textarea id="taskDescriptionInlineInput" class="form-control" rows="5"></textarea>
          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-sm crm-btn-primary">Сохранить</button>
            <button type="button" class="btn btn-sm btn-light" data-task-inline-cancel="description">Отмена</button>
          </div>
        </form>
      </section>

      <section id="detailSubtasks" class="tab-pane fade crm-card crm-task-section">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 mb-0">Подзадачи</h2>
          <div class="d-flex align-items-center gap-2">
            <small class="text-muted">Декомпозиция задачи на шаги</small>
            <button type="button" class="btn btn-sm crm-btn-primary crm-btn-compact" id="openCreateSubtaskModalBtn" data-open-modal="createSubtaskModal">Создать подзадачу</button>
          </div>
        </div>
        <div id="subtasksList"><div class="text-muted">Подзадачи загружаются...</div></div>
      </section>

      <section id="detailDependencies" class="tab-pane fade crm-card crm-task-section">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 mb-0">Зависимости</h2>
          <div class="d-flex align-items-center gap-2">
            <small class="text-muted">Связи с другими задачами</small>
            <button type="button" class="btn btn-sm crm-btn-primary crm-btn-compact" id="openCreateDependencyBtn">Добавить зависимость</button>
          </div>
        </div>
        <div id="dependenciesList"><div class="text-muted">Зависимости загружаются...</div></div>
      </section>

      <section id="detailChecklists" class="tab-pane fade crm-card crm-task-section">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 mb-0">Чеклисты</h2>
          <small class="text-muted">Контроль выполнения деталей</small>
        </div>
        <form id="checklistCreateForm" class="row g-2 mb-3 crm-task-create-form">
          <div class="col-md-9">
            <label class="form-label">Новый чеклист</label>
            <input class="form-control" name="title" maxlength="255" placeholder="Например: Проверка перед релизом" required>
          </div>
          <div class="col-md-3 d-flex align-items-end crm-task-create-action">
            <button class="btn crm-btn-primary crm-btn-compact w-100" type="submit">+ Новый чеклист</button>
          </div>
        </form>
        <div id="checklistsList"><div class="text-muted">Чеклисты загружаются...</div></div>
      </section>

      <section id="detailWorklogs" class="tab-pane fade crm-card crm-task-section">
        <div class="crm-worklog-head mb-3">
          <div>
            <h2 class="h6 mb-1">Учет времени</h2>
            <small class="text-muted">Фиксация фактически затраченного времени</small>
          </div>
          <button class="btn crm-btn-primary crm-btn-compact" type="button" id="worklogAddToggleBtn">+ Добавить запись</button>
        </div>
        <div id="taskWorklogSummary" class="crm-worklog-summary mb-3">
          <article class="crm-worklog-summary-card">
            <div class="crm-worklog-summary-value">0 мин</div>
            <div class="crm-worklog-summary-label">Всего времени</div>
          </article>
          <article class="crm-worklog-summary-card">
            <div class="crm-worklog-summary-value">0 записей</div>
            <div class="crm-worklog-summary-label">В журнале</div>
          </article>
        </div>
        <form id="worklogCreateForm" class="row g-2 mb-3 crm-task-create-form d-none">
          <div class="col-md-3">
            <label class="form-label">Минуты</label>
            <input class="form-control" name="minutes_spent" type="number" min="1" step="1" placeholder="60" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Дата/время</label>
            <input class="form-control" name="logged_at" type="datetime-local" min="<?= date('Y-m-d\TH:i') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Комментарий</label>
            <input class="form-control" name="note" maxlength="8000" placeholder="Что было сделано">
          </div>
          <div class="col-md-2 d-flex align-items-end crm-task-create-action">
            <button class="btn crm-btn-primary crm-btn-compact w-100" type="submit">Сохранить</button>
          </div>
          <div class="col-md-2 d-flex align-items-end crm-task-create-action">
            <button class="btn crm-btn-secondary crm-btn-compact w-100" type="button" id="worklogCreateCancelBtn">Отмена</button>
          </div>
        </form>
        <div class="crm-worklog-list-head mb-2"><h3 class="h6 mb-0">Журнал времени</h3></div>
        <div id="taskWorklogsList"><div class="text-muted">Логи времени загружаются...</div></div>
      </section>

      <section id="detailFiles" class="tab-pane fade crm-card crm-task-section">
        <h2 class="h6">Файлы</h2>
        <div class="mb-3">
          <label class="form-label">Добавить файл</label>
          <div class="d-flex gap-2">
            <input class="form-control" type="file" id="taskFileInput">
            <button class="btn crm-btn-primary" type="button" id="taskFileUploadBtn">Загрузить</button>
          </div>
          
        </div>
        <div id="taskFilesList"><div class="text-muted">Файлы к задаче пока не загружены.</div></div>
      </section>

      <section id="detailComments" class="tab-pane fade crm-card crm-task-section">
        <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0">Комментарии</h2></div>
        <div class="d-flex gap-2 flex-wrap mb-3">
          <button id="taskFollowBtn" class="btn btn-sm crm-btn-secondary" type="button">Отслеживать задачу</button>
          <button id="taskFavoriteBtn" class="btn btn-sm crm-btn-secondary" type="button">В избранное</button>
        </div>
        <form id="commentForm" class="mb-3">
          <label class="form-label">Новый комментарий</label>
          <div class="d-flex gap-2">
            <textarea class="form-control" name="comment_text" rows="2" placeholder="Добавьте комментарий и сохраните его в карточке задачи"></textarea>
            <button class="btn crm-btn-primary" type="submit">Сохранить</button>
          </div>
          <div class="d-flex gap-2 align-items-center mt-2">
            <label class="small text-muted mb-0" for="commentMentionUserSelect">Упомянуть:</label>
            <select id="commentMentionUserSelect" class="form-select form-select-sm crm-field-w-220"><option value="">Без упоминания</option></select>
          </div>
        </form>
        <div id="commentsList"><div class="text-muted">Комментарии загружаются...</div></div>
      </section>

      <section id="detailActivity" class="tab-pane fade crm-card crm-task-section">
        <h2 class="h6">История изменений</h2>
        <div class="crm-timeline" id="taskActivityList"><div class="crm-timeline-item">История изменений загружается...</div></div>
      </section>

      <section id="detailHistory" class="tab-pane fade crm-card crm-task-section">
        <h2 class="h6">История изменений полей</h2>
        <p class="text-muted small mb-3">Хронология изменений атрибутов задачи.</p>
        <table class="table table-sm crm-table mb-0"><thead><tr><th>Дата</th><th>Поле</th><th>Было</th><th>Стало</th><th>Кем изменено</th></tr></thead><tbody id="taskHistoryList"><tr><td colspan="5" class="text-muted">Загрузка...</td></tr></tbody></table>
      </section>
    </div>
  </div>

  <aside class="col-lg-4 crm-task-side-column">
    <div class="crm-task-side-rail">
    <div class="crm-card mb-3" id="taskTimerPanel">
      <div class="crm-side-card-head">
        <div>
          <div class="crm-task-eyebrow">Таймер работы</div>
          <h2 class="h6 mb-0">Таймер задачи</h2>
        </div>
      </div>
	      <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
	        <div>
	          <div class="small text-muted">Таймер работы по задаче</div>
	          <div class="fw-semibold" id="taskTimerElapsed">00:00:00</div>
	          <div class="small text-muted" id="taskTimerStartedAt">Таймер не запущен</div>
	        </div>
	        <div class="d-flex gap-2">
	          <button type="button" class="btn btn-sm crm-btn-primary crm-btn-compact" id="taskTimerStartBtn">Начать работу</button>
	          <button type="button" class="btn btn-sm crm-btn-danger-soft crm-btn-compact" id="taskTimerStopBtn" disabled>Остановить</button>
	        </div>
	      </div>
	      <div class="crm-task-timer-actions">
	        <div>
	          <div class="small text-muted">Планирование</div>
	          <div class="crm-task-timer-action-copy">Событие будет связано с этой задачей.</div>
	        </div>
	        <button class="btn btn-sm btn-light crm-task-calendar-action" type="button" data-open-modal="calendarEventModal">Создать событие</button>
	      </div>
	      <form id="taskTimerLogForm" class="row g-2 mt-3 d-none">
        <div class="col-12">
          <label class="form-label">Затрачено (мин)</label>
          <input class="form-control" type="number" min="1" step="1" name="minutes_spent" required>
        </div>
        <div class="col-12">
          <label class="form-label">Что было сделано</label>
          <input class="form-control" name="note" maxlength="8000" placeholder="Кратко опишите выполненную работу" required>
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn btn-sm crm-btn-primary crm-btn-compact">Добавить запись</button>
          <button type="button" class="btn btn-sm btn-light" id="taskTimerLogCancelBtn">Отмена</button>
        </div>
      </form>
    </div>
    <div class="crm-card mb-3" id="taskAiSummaryCard" data-requires-ai-use="1">
      <div class="crm-side-card-head">
        <div>
          <div class="crm-task-eyebrow">AI-помощник</div>
          <h2 class="h6 mb-0">AI-действия по задаче</h2>
        </div>
      </div>
      <div class="small text-muted mb-2" id="taskAiSummaryState">AI-сводка не сформирована.</div>
      <div class="d-flex gap-2 mb-2 flex-wrap" id="taskAiPrimaryActions">
        <button class="btn btn-sm crm-btn-primary crm-btn-compact" type="button" id="taskAiGenerateBtn">AI-сводка</button>
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiNextActionBtn">Следующий шаг</button>
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiDecomposeBtn">Предложить подзадачи</button>
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiChecklistBtn">Предложить чеклист</button>
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiImproveDescBtn">Улучшить описание</button>
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiCommentDraftBtn">Черновик комментария</button>
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiQualityBtn">Проверить задачу</button>
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiCreateMeetingBtn">Создать встречу</button>
      </div>
      <div class="d-flex gap-2 mb-2 flex-wrap" id="taskAiSecondaryActions">
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiPreviewBtn" disabled>Предпросмотр</button>
        <button class="btn btn-sm btn-light crm-btn-compact" type="button" id="taskAiApplyBtn" disabled>Применить</button>
        <button class="btn btn-sm crm-btn-muted crm-btn-compact" type="button" id="taskAiDismissBtn" disabled>Отклонить</button>
      </div>
      <div id="taskAiSummaryResult" class="crm-empty-state d-none">
        <div class="small mb-2" id="taskAiSummaryText">—</div>
        <div class="small text-muted" id="taskAiSummaryMeta">—</div>
      </div>
      <div id="taskAiSummaryPreviewWrap" class="mt-2 d-none">
        <div class="small text-muted mb-1">Предпросмотр применения:</div>
        <div class="crm-info-panel small" id="taskAiSummaryPreview"></div>
      </div>
    </div>
    <div class="crm-card mb-3 crm-task-summary-card" id="taskSummaryCard">
      <div class="crm-side-card-head">
        <div>
          <div class="crm-task-eyebrow">Быстрая навигация</div>
          <h2 class="h6 mb-0">Сводка</h2>
        </div>
      </div>
      <div class="crm-info-panel mb-2">
        <small class="text-muted">Автор задачи</small>
        <div class="crm-summary-value" id="taskAuthorValue">—</div>
      </div>
      <div class="crm-info-panel mb-2">
        <div class="d-flex justify-content-between align-items-center">
          <small class="text-muted">Исполнитель</small>
          <button type="button" class="btn btn-sm crm-inline-icon-btn" data-task-inline-toggle="assignee" aria-label="Редактировать исполнителя">
            <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span>
          </button>
        </div>
        <div class="crm-summary-value" id="taskAssigneeValue">—</div>
        <form id="taskAssigneeInlineForm" class="mt-2 d-none">
          <select id="taskAssigneeInlineSelect" class="form-select form-select-sm"></select>
          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-sm crm-btn-primary">Сохранить</button>
            <button type="button" class="btn btn-sm btn-light" data-task-inline-cancel="assignee">Отмена</button>
          </div>
        </form>
      </div>
      <div class="crm-info-panel mb-2">
        <div class="d-flex justify-content-between align-items-center">
          <small class="text-muted">Менеджер</small>
          <button type="button" class="btn btn-sm crm-inline-icon-btn" data-task-inline-toggle="manager" aria-label="Редактировать менеджера">
            <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span>
          </button>
        </div>
        <div class="crm-summary-value" id="taskManagerValue">—</div>
        <form id="taskManagerInlineForm" class="mt-2 d-none">
          <select id="taskManagerInlineSelect" class="form-select form-select-sm"></select>
          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-sm crm-btn-primary">Сохранить</button>
            <button type="button" class="btn btn-sm btn-light" data-task-inline-cancel="manager">Отмена</button>
          </div>
        </form>
      </div>
      <div class="crm-info-panel mb-2">
        <div class="d-flex justify-content-between align-items-center">
          <small class="text-muted">Теги</small>
          <button type="button" class="btn btn-sm crm-inline-icon-btn" data-task-inline-toggle="tags" aria-label="Редактировать теги">
            <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span>
          </button>
        </div>
        <div class="crm-summary-value" id="taskTagsValue">—</div>
        <form id="taskTagsInlineForm" class="mt-2 d-none">
          <select id="taskTagsInlineSelect" class="form-select form-select-sm" multiple size="6"></select>
          <div class="form-text">Можно выбрать несколько тегов (Ctrl/Cmd + клик).</div>
          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-sm crm-btn-primary">Сохранить</button>
            <button type="button" class="btn btn-sm btn-light" data-task-inline-cancel="tags">Отмена</button>
          </div>
        </form>
      </div>
      <div class="crm-info-panel mb-2">
        <div class="d-flex justify-content-between align-items-center">
          <small class="text-muted">Сроки задачи</small>
          <button type="button" class="btn btn-sm crm-inline-icon-btn" data-task-inline-toggle="dates" aria-label="Редактировать сроки задачи">
            <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span>
          </button>
        </div>
        <div class="crm-summary-value" id="taskDatesValue">—</div>
        <form id="taskDatesInlineForm" class="mt-2 d-none">
          <div class="mb-2">
            <label class="form-label mb-1 small text-muted" for="taskDatesStartAt">Начало</label>
            <input class="form-control form-control-sm" id="taskDatesStartAt" name="start_at" type="datetime-local" min="<?= date('Y-m-d\TH:i') ?>">
          </div>
          <div class="mb-2">
            <label class="form-label mb-1 small text-muted" for="taskDatesDueAt">Дедлайн</label>
            <input class="form-control form-control-sm" id="taskDatesDueAt" name="due_at" type="datetime-local" min="<?= date('Y-m-d\TH:i') ?>">
          </div>
          <div class="mb-2">
            <label class="form-label mb-1 small text-muted" for="taskDatesEndAt">Плановое завершение</label>
            <input class="form-control form-control-sm" id="taskDatesEndAt" name="end_at" type="datetime-local" min="<?= date('Y-m-d\TH:i') ?>">
          </div>
          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-sm crm-btn-primary">Сохранить</button>
            <button type="button" class="btn btn-sm btn-light" data-task-inline-cancel="dates">Отмена</button>
          </div>
        </form>
      </div>
      <div class="crm-info-panel">
        <div class="d-flex justify-content-between align-items-center">
          <small class="text-muted">Связанный проект</small>
          <button type="button" class="btn btn-sm crm-inline-icon-btn" data-task-inline-toggle="project" aria-label="Редактировать проект">
            <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span>
          </button>
        </div>
        <div class="crm-summary-value"><a href="index.php?route=projects" id="taskProjectLink">—</a></div>
        <form id="taskProjectInlineForm" class="mt-2 d-none">
          <select id="taskProjectInlineSelect" class="form-select form-select-sm"></select>
          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-sm crm-btn-primary">Сохранить</button>
            <button type="button" class="btn btn-sm btn-light" data-task-inline-cancel="project">Отмена</button>
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
        <h5 class="modal-title">Создать подзадачу</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <form id="subtaskCreateForm">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Название подзадачи</label>
              <input class="form-control" name="title" maxlength="255" placeholder="Например: Подготовить макет" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Проект</label>
              <select class="form-select" name="project_public_id" disabled>
                <option value="">Без проекта</option>
              </select>
              <div class="form-text">Подзадача создается в проекте родительской задачи.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Статус</label>
              <select class="form-select" name="status"></select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Приоритет</label>
              <select class="form-select" name="priority"></select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Исполнитель</label>
              <select class="form-select" name="assignee_user_public_id"></select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Начало</label>
              <input class="form-control" name="start_at" type="date" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Дедлайн</label>
              <input class="form-control" name="due_at" type="date" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Плановое завершение</label>
              <input class="form-control" name="end_at" type="date" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Теги</label>
              <select class="form-select" name="tag_public_ids" multiple size="6"></select>
              <div class="form-text">Можно выбрать несколько тегов.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Описание</label>
              <textarea class="form-control" name="description" rows="5" placeholder="Контекст, шаги, критерии готовности"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button>
          <button class="btn crm-btn-primary" type="submit">Создать</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editSubtaskModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Редактирование подзадачи</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <form id="subtaskEditForm">
        <input type="hidden" name="public_id" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Название подзадачи</label>
              <input class="form-control" name="title" maxlength="255" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Проект</label>
              <select class="form-select" name="project_public_id" disabled>
                <option value="">Без проекта</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Статус</label>
              <select class="form-select" name="status"></select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Приоритет</label>
              <select class="form-select" name="priority"></select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Исполнитель</label>
              <select class="form-select" name="assignee_user_public_id"></select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Начало</label>
              <input class="form-control" name="start_at" type="date" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Дедлайн</label>
              <input class="form-control" name="due_at" type="date" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Плановое завершение</label>
              <input class="form-control" name="end_at" type="date" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Теги</label>
              <select class="form-select" name="tag_public_ids" multiple size="6"></select>
              <div class="form-text">Можно выбрать несколько тегов.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Описание</label>
              <textarea class="form-control" name="description" rows="5"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button>
          <button class="btn crm-btn-primary" type="submit">Сохранить изменения</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="taskAiDescriptionDiffModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>AI: улучшение описания</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-3">Сравните текущее и предложенное описание. Применение обновит описание задачи.</p>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="d-flex align-items-center mb-2">
              <span class="badge bg-secondary me-2">Текущее</span>
            </div>
            <div class="crm-diff-panel p-3" id="taskAiDescriptionDiffOld">—</div>
          </div>
          <div class="col-md-6">
            <div class="d-flex align-items-center mb-2">
              <span class="badge bg-success me-2">Предложенное</span>
            </div>
            <div class="crm-diff-panel crm-diff-panel-new p-3" id="taskAiDescriptionDiffNew">—</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="button" class="btn crm-btn-primary" id="taskAiDescriptionDiffApplyBtn"><i class="fa-solid fa-check me-1"></i>Применить</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="taskAiRegenerateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="taskAiRegenerateModalTitle">AI-предложение уже существует</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <div id="taskAiRegenerateInfo">
          <p class="mb-2">Для этой задачи уже есть AI-предложение:</p>
          <div class="crm-info-panel p-3 mb-3">
            <div class="small text-muted mb-1">Сводка</div>
            <div id="taskAiRegenerateSummary" class="mb-2">—</div>
            <div class="small text-muted mb-1">Статус</div>
            <div id="taskAiRegenerateStatus" class="mb-2">—</div>
            <div class="small text-muted mb-1">Обновлено</div>
            <div id="taskAiRegenerateUpdated">—</div>
          </div>
          <p class="mb-0">Перегенерировать предложение?</p>
        </div>
        <div id="taskAiRegenerateLoading" class="d-none text-center py-4">
          <div class="spinner-border text-primary mb-3" style="width: 2.5rem; height: 2.5rem;" role="status"></div>
          <div id="taskAiRegenerateLoadingText" class="text-muted fs-6">Перегенерируем AI-предложение...</div>
        </div>
      </div>
      <div class="modal-footer" id="taskAiRegenerateFooter">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="button" class="btn crm-btn-primary" id="taskAiRegenerateBtn">Перегенерировать</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="taskAiHighRiskModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>Действие с повышенным риском</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">AI предлагает действие, которое может существенно изменить задачу:</p>
        <div id="taskAiHighRiskActions" class="crm-info-panel p-3 mb-3 small">—</div>
        <p class="mb-0">Продолжить применение?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="button" class="btn crm-btn-warning" id="taskAiHighRiskConfirmBtn">Продолжить</button>
      </div>
    </div>
  </div>
</div>

</main></div></div>
