<?php declare(strict_types=1); ?>
<?php $title = $t('dashboard.title', 'TropaTT — Главная'); ?>
<body data-page="dashboard" data-protected="1">
<div class="crm-app">
  <aside class="crm-sidebar">
    <div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div>
    <nav class="nav flex-column crm-nav"></nav>
  </aside>

  <div class="crm-main-wrap">
    <header class="crm-topbar py-2">
      <div class="container-fluid"></div>
    </header>

    <main class="crm-content crm-dashboard-page">
      <div class="crm-page-head">
        <div>
          <h1 class="crm-page-title" data-i18n="dashboard.page_title"><?= htmlspecialchars($t('dashboard.page_title', 'Главная'), ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="crm-subtitle mb-0" data-dashboard-subtitle data-i18n="dashboard.subtitle"><?= htmlspecialchars($t('dashboard.subtitle', 'Срез по задачам, рискам и загрузке команд на 01 мая 2026 г.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="crm-page-actions">
          <button class="btn crm-btn-secondary d-inline-flex align-items-center gap-2" type="button" data-dashboard-builder-toggle>
            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
            <span data-i18n="dashboard.builder_edit"><?= htmlspecialchars($t('dashboard.builder_edit', 'Редактировать'), ENT_QUOTES, 'UTF-8') ?></span>
          </button>
          <button class="btn crm-btn-primary d-inline-flex align-items-center gap-2" data-open-modal="createTaskModal" type="button" data-i18n="dashboard.btn_create_task">
            <span><?= htmlspecialchars($t('dashboard.btn_create_task', 'Создать задачу'), ENT_QUOTES, 'UTF-8') ?></span>
          </button>
        </div>
      </div>

      <div class="crm-dashboard-builder-bar d-none" data-dashboard-builder-bar>
        <span class="crm-dashboard-builder-hint text-muted small" data-i18n="dashboard.builder_hint"><?= htmlspecialchars($t('dashboard.builder_hint', 'Перетащите виджеты, чтобы изменить порядок. Изменения вступят в силу после сохранения.'), ENT_QUOTES, 'UTF-8') ?></span>
        <div class="d-inline-flex gap-2 ms-auto">
          <button type="button" class="btn btn-sm crm-btn-secondary d-inline-flex align-items-center gap-1" data-dashboard-builder-add>
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            <span data-i18n="dashboard.builder_add_widget"><?= htmlspecialchars($t('dashboard.builder_add_widget', 'Добавить виджет'), ENT_QUOTES, 'UTF-8') ?></span>
          </button>
          <button type="button" class="btn btn-sm crm-btn-secondary" data-dashboard-builder-reset data-i18n="dashboard.widgets_cancel"><?= htmlspecialchars($t('dashboard.widgets_cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="button" class="btn btn-sm crm-btn-primary" data-dashboard-builder-save data-i18n="dashboard.widgets_save"><?= htmlspecialchars($t('dashboard.widgets_save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </div>

      <div class="crm-dashboard-grid" data-dashboard-grid>
        <section class="crm-dashboard-section crm-col-12" data-dashboard-widget="kpi">
        <div class="row g-3">
          <div class="col-12 col-sm-6 col-xl-3">
            <a class="text-decoration-none text-reset d-block" href="index.php?route=tasks&kpi=active" aria-label="<?= htmlspecialchars($t('dashboard.kpi_active_aria', 'Открыть выборку активных задач'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="dashboard.kpi_active_aria">
              <article class="crm-dashboard-kpi is-active" data-kpi-link="active_tasks">
                <div class="crm-dashboard-kpi-main">
                  <div class="crm-dashboard-kpi-icon" aria-hidden="true">
                    <i class="fa-regular fa-rectangle-list"></i>
                  </div>
                  <div>
                    <div class="crm-dashboard-kpi-meta">
                      <span class="crm-dashboard-kpi-label" data-i18n="dashboard.kpi_active_tasks"><?= htmlspecialchars($t('dashboard.kpi_active_tasks', 'Активные задачи'), ENT_QUOTES, 'UTF-8') ?></span>
                      <span class="crm-badge archived" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <strong>—</strong>
                    <p class="crm-dashboard-kpi-note" data-i18n="dashboard.kpi_active_note"><?= htmlspecialchars($t('dashboard.kpi_active_note', 'Задач к выполнению сегодня: 0.'), ENT_QUOTES, 'UTF-8') ?></p>
                  </div>
                </div>
              </article>
            </a>
          </div>
          <div class="col-12 col-sm-6 col-xl-3">
            <a class="text-decoration-none text-reset d-block" href="index.php?route=tasks&kpi=overdue" aria-label="<?= htmlspecialchars($t('dashboard.kpi_overdue_aria', 'Открыть выборку просроченных задач'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="dashboard.kpi_overdue_aria">
              <article class="crm-dashboard-kpi is-danger" data-kpi-link="overdue_tasks">
                <div class="crm-dashboard-kpi-main">
                  <div class="crm-dashboard-kpi-icon" aria-hidden="true">
                    <i class="fa-regular fa-clock"></i>
                  </div>
                  <div>
                    <div class="crm-dashboard-kpi-meta">
                      <span class="crm-dashboard-kpi-label" data-i18n="dashboard.kpi_overdue_tasks"><?= htmlspecialchars($t('dashboard.kpi_overdue_tasks', 'Просрочено'), ENT_QUOTES, 'UTF-8') ?></span>
                      <span class="crm-badge archived" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <strong>—</strong>
                    <p class="crm-dashboard-kpi-note" data-i18n="dashboard.kpi_overdue_note"><?= htmlspecialchars($t('dashboard.kpi_overdue_note', 'Просроченных задач в системе: 0.'), ENT_QUOTES, 'UTF-8') ?></p>
                  </div>
                </div>
              </article>
            </a>
          </div>
          <div class="col-12 col-sm-6 col-xl-3">
            <a class="text-decoration-none text-reset d-block" href="index.php?route=projects&status=active&kpi=active_projects" aria-label="<?= htmlspecialchars($t('dashboard.kpi_projects_aria', 'Открыть выборку активных проектов'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="dashboard.kpi_projects_aria">
              <article class="crm-dashboard-kpi is-warning" data-kpi-link="active_projects">
                <div class="crm-dashboard-kpi-main">
                  <div class="crm-dashboard-kpi-icon" aria-hidden="true">
                    <i class="fa-regular fa-folder-open"></i>
                  </div>
                  <div>
                    <div class="crm-dashboard-kpi-meta">
                      <span class="crm-dashboard-kpi-label" data-i18n="dashboard.kpi_active_projects"><?= htmlspecialchars($t('dashboard.kpi_active_projects', 'Активные проекты'), ENT_QUOTES, 'UTF-8') ?></span>
                      <span class="crm-badge archived" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <strong>—</strong>
                    <p class="crm-dashboard-kpi-note" data-i18n="dashboard.kpi_projects_note"><?= htmlspecialchars($t('dashboard.kpi_projects_note', 'Активных проектов в работе: 0.'), ENT_QUOTES, 'UTF-8') ?></p>
                  </div>
                </div>
              </article>
            </a>
          </div>
          <div class="col-12 col-sm-6 col-xl-3">
            <a class="text-decoration-none text-reset d-block" href="index.php?route=tasks&kpi=sla_week" aria-label="<?= htmlspecialchars($t('dashboard.kpi_sla_aria', 'Открыть выборку задач по SLA недели'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="dashboard.kpi_sla_aria">
              <article class="crm-dashboard-kpi is-success" data-kpi-link="sla_week">
                <div class="crm-dashboard-kpi-main">
                  <div class="crm-dashboard-kpi-icon" aria-hidden="true">
                    <i class="fa-solid fa-stopwatch"></i>
                  </div>
                  <div>
                    <div class="crm-dashboard-kpi-meta">
                      <span class="crm-dashboard-kpi-label" data-i18n="dashboard.kpi_sla_week"><?= htmlspecialchars($t('dashboard.kpi_sla_week', 'SLA недели'), ENT_QUOTES, 'UTF-8') ?></span>
                      <span class="crm-badge archived" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <strong>—</strong>
                    <p class="crm-dashboard-kpi-note" data-i18n="dashboard.kpi_sla_note"><?= htmlspecialchars($t('dashboard.kpi_sla_note', 'Суммарный worklog за неделю: 0 мин.'), ENT_QUOTES, 'UTF-8') ?></p>
                  </div>
                </div>
              </article>
            </a>
          </div>
        </div>
      </section>

      <section class="crm-card crm-dashboard-section crm-dashboard-actions crm-col-12" data-dashboard-widget="quick_actions">
        <h2 class="crm-dashboard-actions-title mb-0" data-i18n="dashboard.quick_actions"><?= htmlspecialchars($t('dashboard.quick_actions', 'Быстрые действия'), ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="crm-dashboard-actions-list">
          <button class="btn crm-btn-primary crm-dashboard-action-chip crm-dashboard-action-chip-primary" type="button" data-open-drawer="quickTaskDrawer" data-i18n="dashboard.action_last_task"><span class="crm-dashboard-chip-icon" aria-hidden="true"><i class="fa-regular fa-folder"></i></span><?= htmlspecialchars($t('dashboard.action_last_task', 'Открыть последнюю задачу'), ENT_QUOTES, 'UTF-8') ?></button>
          <button class="btn crm-btn-secondary crm-dashboard-action-chip" type="button" data-open-modal="assignUserModal" data-i18n="dashboard.action_assign"><span class="crm-dashboard-chip-icon" aria-hidden="true"><i class="fa-solid fa-user-plus"></i></span><?= htmlspecialchars($t('dashboard.action_assign', 'Назначить исполнителя'), ENT_QUOTES, 'UTF-8') ?></button>
          <button class="btn crm-btn-secondary crm-dashboard-action-chip" type="button" data-open-modal="createProjectModal" data-i18n="dashboard.action_create_project"><span class="crm-dashboard-chip-icon" aria-hidden="true"><i class="fa-solid fa-circle-plus"></i></span><?= htmlspecialchars($t('dashboard.action_create_project', 'Создать проект'), ENT_QUOTES, 'UTF-8') ?></button>
          <a class="btn crm-btn-secondary crm-dashboard-action-chip" href="index.php?route=calendar" data-i18n="dashboard.action_calendar"><span class="crm-dashboard-chip-icon" aria-hidden="true"><i class="fa-regular fa-calendar"></i></span><?= htmlspecialchars($t('dashboard.action_calendar', 'Открыть календарь'), ENT_QUOTES, 'UTF-8') ?></a>
          <a class="btn crm-btn-secondary crm-dashboard-action-chip" href="index.php?route=kanban" data-i18n="dashboard.action_kanban"><span class="crm-dashboard-chip-icon" aria-hidden="true"><i class="fa-solid fa-table-columns"></i></span><?= htmlspecialchars($t('dashboard.action_kanban', 'Открыть канбан'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
      </section>

      <section class="crm-card crm-dashboard-section crm-col-12" id="dashboardAiDigestCard" data-dashboard-widget="ai_digest" data-requires-ai-use="1" data-ai-state="idle">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
          <div>
            <h2 class="h5 mb-1 d-inline-flex align-items-center gap-2" data-i18n="dashboard.ai_digest_title">
              <span class="crm-dashboard-inline-icon" aria-hidden="true">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
              </span>
              <?= htmlspecialchars($t('dashboard.ai_digest_title', 'AI-сводка дня'), ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <p class="text-muted mb-0" id="dashboardAiDigestMeta" data-i18n="dashboard.ai_digest_meta"><?= htmlspecialchars($t('dashboard.ai_digest_meta', 'Сводка по рискам и фокусу. Источник: AI suggestion `dashboard_daily_digest`.'), ENT_QUOTES, 'UTF-8') ?></p>
          </div>
          <button type="button" class="btn btn-sm crm-btn-secondary" id="dashboardAiDigestRefreshBtn" data-i18n="dashboard.ai_digest_refresh"><?= htmlspecialchars($t('dashboard.ai_digest_refresh', 'Обновить AI-сводку'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
        <div id="dashboardAiDigestSummary" class="crm-dashboard-ai-summary-panel mb-3">
          <strong data-i18n="dashboard.ai_digest_empty"><?= htmlspecialchars($t('dashboard.ai_digest_empty', 'AI-сводка не сформирована'), ENT_QUOTES, 'UTF-8') ?></strong>
          <p class="mb-0 crm-dashboard-ai-text" data-i18n="dashboard.ai_digest_hint"><?= htmlspecialchars($t('dashboard.ai_digest_hint', 'Нажмите кнопку «Обновить AI-сводку», чтобы получить рекомендацию.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="row g-2">
          <div class="col-12 col-md-6 col-xl-3">
            <div class="crm-info-panel h-100 crm-dashboard-ai-subcard">
              <h3 class="h6 mb-2 d-inline-flex align-items-center gap-2" data-i18n="dashboard.ai_risks"><span class="crm-dashboard-inline-icon is-danger" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span><?= htmlspecialchars($t('dashboard.ai_risks', 'Риски'), ENT_QUOTES, 'UTF-8') ?></h3>
              <div id="dashboardAiDigestRisks"></div>
            </div>
          </div>
          <div class="col-12 col-md-6 col-xl-3">
            <div class="crm-info-panel h-100 crm-dashboard-ai-subcard">
              <h3 class="h6 mb-2 d-inline-flex align-items-center gap-2" data-i18n="dashboard.ai_highlights"><span class="crm-dashboard-inline-icon is-warning" aria-hidden="true"><i class="fa-solid fa-star"></i></span><?= htmlspecialchars($t('dashboard.ai_highlights', 'Highlights'), ENT_QUOTES, 'UTF-8') ?></h3>
              <div id="dashboardAiDigestHighlights"></div>
            </div>
          </div>
          <div class="col-12 col-xl-6">
            <div class="crm-info-panel h-100 crm-dashboard-ai-subcard">
              <h3 class="h6 mb-2 d-inline-flex align-items-center gap-2" data-i18n="dashboard.ai_actions"><span class="crm-dashboard-inline-icon is-success" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span><?= htmlspecialchars($t('dashboard.ai_actions', 'Рекомендуемые действия'), ENT_QUOTES, 'UTF-8') ?></h3>
              <div id="dashboardAiDigestActions"></div>
            </div>
          </div>
        </div>
      </section>

      <section class="crm-card crm-dashboard-section crm-col-12" data-dashboard-widget="today_tasks">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.today_tasks"><?= htmlspecialchars($t('dashboard.today_tasks', 'Задачи на сегодня'), ENT_QUOTES, 'UTF-8') ?></h2>
          <a href="index.php?route=tasks" class="fw-semibold" data-i18n="dashboard.all_tasks"><?= htmlspecialchars($t('dashboard.all_tasks', 'Все задачи'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>

        <div class="table-responsive crm-dashboard-table-wrap d-none d-md-block">
          <table class="table crm-table mb-0 crm-dashboard-task-table">
            <thead>
              <tr>
                <th data-i18n="dashboard.th_task"><?= htmlspecialchars($t('dashboard.th_task', 'Задача'), ENT_QUOTES, 'UTF-8') ?></th>
                <th data-i18n="dashboard.th_project"><?= htmlspecialchars($t('dashboard.th_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></th>
                <th data-i18n="dashboard.th_assignee"><?= htmlspecialchars($t('dashboard.th_assignee', 'Исполнитель'), ENT_QUOTES, 'UTF-8') ?></th>
                <th data-i18n="dashboard.th_due"><?= htmlspecialchars($t('dashboard.th_due', 'Срок'), ENT_QUOTES, 'UTF-8') ?></th>
                <th data-i18n="dashboard.th_status"><?= htmlspecialchars($t('dashboard.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th>
              </tr>
            </thead>
             <tbody data-dashboard-tasks-body>
              <tr><td colspan="5"><div class="crm-skeleton crm-skeleton--row" style="width:100%"></div><div class="crm-skeleton crm-skeleton--row" style="width:100%"></div><div class="crm-skeleton crm-skeleton--row" style="width:100%"></div></td></tr>
            </tbody>
          </table>
        </div>

        <div class="d-grid gap-2 d-md-none" data-dashboard-task-list>
          <article class="crm-card crm-dashboard-task-card"><div class="crm-skeleton crm-skeleton--text"></div><div class="crm-skeleton crm-skeleton--text-sm"></div></article>
        </div>
      </section>

      <section class="crm-card h-100 crm-dashboard-widget crm-col-4" data-dashboard-widget="risks">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.risks_title"><?= htmlspecialchars($t('dashboard.risks_title', 'Риски'), ENT_QUOTES, 'UTF-8') ?></h2>
          <a href="index.php?route=tasks&kpi=overdue" data-i18n="dashboard.risks_more"><?= htmlspecialchars($t('dashboard.risks_more', 'Подробнее'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div data-dashboard-risks-list>
          <div class="crm-skeleton crm-skeleton--text"></div><div class="crm-skeleton crm-skeleton--text-sm"></div>
        </div>
        <div data-dashboard-risks-metrics class="mt-2"></div>
      </section>
      <section class="crm-card h-100 crm-dashboard-widget crm-col-4" data-dashboard-widget="activity">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.activity_title"><?= htmlspecialchars($t('dashboard.activity_title', 'Активность'), ENT_QUOTES, 'UTF-8') ?></h2>
          <a href="index.php?route=notifications" data-i18n="dashboard.activity_more"><?= htmlspecialchars($t('dashboard.activity_more', 'Подробнее'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div class="crm-timeline" data-dashboard-activity-list>
          <div class="crm-timeline-item"><div class="crm-skeleton crm-skeleton--text"></div><div class="crm-skeleton crm-skeleton--text-sm"></div></div>
          <div class="crm-timeline-item"><div class="crm-skeleton crm-skeleton--text"></div><div class="crm-skeleton crm-skeleton--text-sm"></div></div>
        </div>
      </section>
      <section class="crm-card h-100 crm-dashboard-widget crm-col-4" data-dashboard-widget="projects_overview">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.projects_overview"><?= htmlspecialchars($t('dashboard.projects_overview', 'Обзор проектов'), ENT_QUOTES, 'UTF-8') ?></h2>
          <a href="index.php?route=projects" data-i18n="dashboard.projects_more"><?= htmlspecialchars($t('dashboard.projects_more', 'Подробнее'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div data-dashboard-overview-list>
          <div class="crm-skeleton crm-skeleton--text"></div><div class="crm-skeleton crm-skeleton--text-sm mb-2"></div>
        </div>
      </section>
      <section class="crm-card h-100 crm-dashboard-widget crm-col-4" data-dashboard-widget="knowledge">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.knowledge_title"><?= htmlspecialchars($t('dashboard.knowledge_title', 'База знаний'), ENT_QUOTES, 'UTF-8') ?></h2>
          <a href="index.php?route=knowledge" data-i18n="dashboard.knowledge_more"><?= htmlspecialchars($t('dashboard.knowledge_more', 'Перейти'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div data-dashboard-knowledge-list>
          <div class="crm-skeleton crm-skeleton--text"></div><div class="crm-skeleton crm-skeleton--text-sm mb-2"></div>
        </div>
      </section>
      <section class="crm-card h-100 crm-dashboard-widget crm-col-4" data-dashboard-widget="cycles">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.cycles_title"><?= htmlspecialchars($t('dashboard.cycles_title', 'Активные циклы'), ENT_QUOTES, 'UTF-8') ?></h2>
          <a href="index.php?route=cycles" data-i18n="dashboard.cycles_more"><?= htmlspecialchars($t('dashboard.cycles_more', 'Все циклы'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div data-dashboard-cycles-list>
          <div class="crm-skeleton crm-skeleton--text"></div><div class="crm-skeleton crm-skeleton--text-sm mb-2"></div>
        </div>
      </section>
      <section class="crm-card h-100 crm-dashboard-widget crm-col-4" data-dashboard-widget="reminders">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.widget_reminders"><?= htmlspecialchars($t('dashboard.widget_reminders', 'Мои напоминания'), ENT_QUOTES, 'UTF-8') ?></h2>
          <a href="index.php?route=notifications" data-i18n="dashboard.activity_more"><?= htmlspecialchars($t('dashboard.activity_more', 'Подробнее'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div data-dashboard-reminders-list>
          <div class="crm-skeleton crm-skeleton--text"></div><div class="crm-skeleton crm-skeleton--text-sm"></div>
        </div>
      </section>
      <section class="crm-card h-100 crm-dashboard-widget crm-col-6" data-dashboard-widget="my_day">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.widget_my_day"><?= htmlspecialchars($t('dashboard.widget_my_day', 'Мой день'), ENT_QUOTES, 'UTF-8') ?></h2>
          <a href="index.php?route=calendar" data-i18n="dashboard.activity_more"><?= htmlspecialchars($t('dashboard.activity_more', 'Подробнее'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div data-dashboard-myday-list>
          <div class="crm-skeleton crm-skeleton--text"></div><div class="crm-skeleton crm-skeleton--text-sm"></div>
        </div>
      </section>
      <section class="crm-card h-100 crm-dashboard-widget crm-col-4" data-dashboard-widget="sticky_notes">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.widget_sticky_notes"><?= htmlspecialchars($t('dashboard.widget_sticky_notes', 'Заметки'), ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
        <div class="row g-2" id="stickyNotesList" data-dashboard-sticky-notes-list>
          <div class="col-12 text-muted small" data-i18n="dashboard.sticky_notes_loading"><?= htmlspecialchars($t('dashboard.sticky_notes_loading', 'Загрузка заметок...'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </section>
      <section class="crm-card h-100 crm-dashboard-widget crm-col-6" data-dashboard-widget="worklog">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.widget_worklog"><?= htmlspecialchars($t('dashboard.widget_worklog', 'Моё время'), ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
        <div id="dashboardWorklogWidget" data-dashboard-worklog-widget>
          <div class="col-12 text-muted small" data-i18n="dashboard.loading_widget"><?= htmlspecialchars($t('dashboard.loading_widget', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </section>
      <section class="crm-card h-100 crm-dashboard-widget crm-col-12" data-dashboard-widget="my_tasks">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.widget_my_tasks"><?= htmlspecialchars($t('dashboard.widget_my_tasks', 'Мои задачи'), ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
        <div id="dashboardMyTasksWidget" data-dashboard-my-tasks-widget>
          <div class="col-12 text-muted small" data-i18n="dashboard.loading_widget"><?= htmlspecialchars($t('dashboard.loading_widget', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </section>
      <section class="crm-card h-100 crm-dashboard-widget crm-col-4" data-dashboard-widget="approvals">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.widget_approvals"><?= htmlspecialchars($t('dashboard.widget_approvals', 'Согласования'), ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
        <div id="dashboardApprovalsWidget" data-dashboard-approvals-widget>
          <div class="col-12 text-muted small" data-i18n="dashboard.loading_widget"><?= htmlspecialchars($t('dashboard.loading_widget', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </section>
      <section class="crm-card h-100 crm-dashboard-widget crm-col-4" data-dashboard-widget="milestones">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.widget_milestones"><?= htmlspecialchars($t('dashboard.widget_milestones', 'Ближайшие вехи'), ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
        <div id="dashboardMilestonesWidget" data-dashboard-milestones-widget>
          <div class="col-12 text-muted small" data-i18n="dashboard.loading_widget"><?= htmlspecialchars($t('dashboard.loading_widget', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </section>
      <section class="crm-card h-100 crm-dashboard-widget crm-col-4" data-dashboard-widget="favorites">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.widget_favorites"><?= htmlspecialchars($t('dashboard.widget_favorites', 'Избранное'), ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
        <div id="dashboardFavoritesWidget" data-dashboard-favorites-widget>
          <div class="col-12 text-muted small" data-i18n="dashboard.loading_widget"><?= htmlspecialchars($t('dashboard.loading_widget', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </section>
      <section class="crm-card h-100 crm-dashboard-widget crm-col-4" data-dashboard-widget="intake">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.widget_intake"><?= htmlspecialchars($t('dashboard.widget_intake', 'Входящие заявки'), ENT_QUOTES, 'UTF-8') ?></h2>
          <a href="index.php?route=intake" data-i18n="dashboard.intake_more"><?= htmlspecialchars($t('dashboard.intake_more', 'Все заявки'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div id="dashboardIntakeWidget" data-dashboard-intake-widget>
          <div class="col-12 text-muted small" data-i18n="dashboard.loading_widget"><?= htmlspecialchars($t('dashboard.loading_widget', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </section>
      <section class="crm-card h-100 crm-dashboard-widget crm-col-4" data-dashboard-widget="recurring">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.widget_recurring"><?= htmlspecialchars($t('dashboard.widget_recurring', 'Повторяющиеся задачи'), ENT_QUOTES, 'UTF-8') ?></h2>
          <a href="index.php?route=recurring" data-i18n="dashboard.recurring_more"><?= htmlspecialchars($t('dashboard.recurring_more', 'Все правила'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div id="dashboardRecurringWidget" data-dashboard-recurring-widget>
          <div class="col-12 text-muted small" data-i18n="dashboard.loading_widget"><?= htmlspecialchars($t('dashboard.loading_widget', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </section>
      <section class="crm-card h-100 crm-dashboard-widget crm-col-4" data-dashboard-widget="mentions">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.widget_mentions"><?= htmlspecialchars($t('dashboard.widget_mentions', 'Мои упоминания'), ENT_QUOTES, 'UTF-8') ?></h2>
          <a href="index.php?route=mentions" data-i18n="dashboard.mentions_more"><?= htmlspecialchars($t('dashboard.mentions_more', 'Все упоминания'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div id="dashboardMentionsWidget" data-dashboard-mentions-widget>
          <div class="col-12 text-muted small" data-i18n="dashboard.loading_widget"><?= htmlspecialchars($t('dashboard.loading_widget', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </section>
      <section class="crm-card h-100 crm-dashboard-widget crm-col-12" data-dashboard-widget="my_week">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.widget_my_week"><?= htmlspecialchars($t('dashboard.widget_my_week', 'Моя неделя'), ENT_QUOTES, 'UTF-8') ?></h2>
          <a href="index.php?route=my-week" data-i18n="dashboard.my_week_more"><?= htmlspecialchars($t('dashboard.my_week_more', 'Открыть неделю'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div id="dashboardMyWeekWidget" data-dashboard-my-week-widget>
          <div class="col-12 text-muted small" data-i18n="dashboard.loading_widget"><?= htmlspecialchars($t('dashboard.loading_widget', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </section>
      <section class="crm-card h-100 crm-dashboard-widget crm-col-6" data-dashboard-widget="unassigned">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="dashboard.widget_unassigned"><?= htmlspecialchars($t('dashboard.widget_unassigned', 'Задачи без исполнителя'), ENT_QUOTES, 'UTF-8') ?></h2>
          <a href="index.php?route=tasks&assignee=none" data-i18n="dashboard.unassigned_more"><?= htmlspecialchars($t('dashboard.unassigned_more', 'Все такие задачи'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div id="dashboardUnassignedWidget" data-dashboard-unassigned-widget>
          <div class="col-12 text-muted small" data-i18n="dashboard.loading_widget"><?= htmlspecialchars($t('dashboard.loading_widget', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </section>

      <?php
      $extraDashboardWidgets = [
          ['key' => 'analytics_summary', 'size' => 'crm-col-4', 'title' => 'Аналитика', 'href' => 'index.php?route=analytics'],
          ['key' => 'project_health', 'size' => 'crm-col-6', 'title' => 'Здоровье проектов', 'href' => 'index.php?route=analytics'],
          ['key' => 'team_workload', 'size' => 'crm-col-6', 'title' => 'Загрузка команды', 'href' => 'index.php?route=analytics'],
          ['key' => 'time_team', 'size' => 'crm-col-6', 'title' => 'Командное время', 'href' => 'index.php?route=time-analytics'],
          ['key' => 'notification_inbox', 'size' => 'crm-col-4', 'title' => 'Центр уведомлений', 'href' => 'index.php?route=notifications'],
          ['key' => 'chat_unread', 'size' => 'crm-col-4', 'title' => 'Непрочитанные чаты', 'href' => 'index.php?route=chat'],
          ['key' => 'client_pipeline', 'size' => 'crm-col-4', 'title' => 'Клиенты', 'href' => 'index.php?route=counterparties'],
          ['key' => 'company_directory', 'size' => 'crm-col-4', 'title' => 'Компании', 'href' => 'index.php?route=counterparties'],
          ['key' => 'contact_followups', 'size' => 'crm-col-4', 'title' => 'Контакты', 'href' => 'index.php?route=counterparties'],
          ['key' => 'tag_usage', 'size' => 'crm-col-4', 'title' => 'Популярные теги', 'href' => 'index.php?route=admin-tags'],
          ['key' => 'saved_views', 'size' => 'crm-col-4', 'title' => 'Сохранённые представления', 'href' => 'index.php?route=tasks'],
          ['key' => 'subscriptions', 'size' => 'crm-col-4', 'title' => 'Мои подписки', 'href' => 'index.php?route=tasks'],
          ['key' => 'dependency_watch', 'size' => 'crm-col-6', 'title' => 'Зависимости', 'href' => 'index.php?route=tasks'],
          ['key' => 'milestone_watch', 'size' => 'crm-col-6', 'title' => 'Ближайшие вехи', 'href' => 'index.php?route=projects'],
          ['key' => 'recurring_health', 'size' => 'crm-col-4', 'title' => 'Автоматизация задач', 'href' => 'index.php?route=recurring'],
          ['key' => 'approval_queue', 'size' => 'crm-col-4', 'title' => 'Очередь согласований', 'href' => 'index.php?route=approvals'],
          ['key' => 'intake_sla', 'size' => 'crm-col-4', 'title' => 'SLA входящих', 'href' => 'index.php?route=intake'],
          ['key' => 'webhook_health', 'size' => 'crm-col-6', 'title' => 'Здоровье вебхуков', 'href' => 'index.php?route=admin-webhooks'],
          ['key' => 'workflow_automation', 'size' => 'crm-col-6', 'title' => 'Workflow', 'href' => 'index.php?route=admin-workflow'],
          ['key' => 'system_health', 'size' => 'crm-col-4', 'title' => 'Состояние системы', 'href' => 'index.php?route=admin'],
          ['key' => 'active_sessions', 'size' => 'crm-col-4', 'title' => 'Активные сессии', 'href' => 'index.php?route=profile'],
      ];
      foreach ($extraDashboardWidgets as $extraWidget):
          $extraKey = (string)$extraWidget['key'];
          $extraTitleKey = 'dashboard.extra_' . $extraKey;
      ?>
      <section class="crm-card h-100 crm-dashboard-widget <?= htmlspecialchars((string)$extraWidget['size'], ENT_QUOTES, 'UTF-8') ?>" data-dashboard-widget="<?= htmlspecialchars($extraKey, ENT_QUOTES, 'UTF-8') ?>">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0" data-i18n="<?= htmlspecialchars($extraTitleKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t($extraTitleKey, (string)$extraWidget['title']), ENT_QUOTES, 'UTF-8') ?></h2>
          <a href="<?= htmlspecialchars((string)$extraWidget['href'], ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($t('dashboard.extra_open', 'Открыть'), ENT_QUOTES, 'UTF-8') ?>">↗</a>
        </div>
        <div data-extra-widget-body="<?= htmlspecialchars($extraKey, ENT_QUOTES, 'UTF-8') ?>"></div>
      </section>
      <?php endforeach; ?>
      </div>

      <?= module_position('dashboard.content.after', ['route' => $route ?? 'dashboard']) ?>

      <div id="dashboardWidgetPool" hidden></div>

    </main>
  </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="dashboardCatalogOffcanvas" aria-labelledby="dashboardCatalogOffcanvasLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="dashboardCatalogOffcanvasLabel" data-i18n="dashboard.catalog_title"><?= htmlspecialchars($t('dashboard.catalog_title', 'Каталог виджетов'), ENT_QUOTES, 'UTF-8') ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
  </div>
  <div class="offcanvas-body">
    <p class="text-muted small mb-3" data-i18n="dashboard.catalog_hint"><?= htmlspecialchars($t('dashboard.catalog_hint', 'Выберите виджеты, чтобы добавить их на дашборд. Готово — закройте каталог и нажмите «Сохранить».'), ENT_QUOTES, 'UTF-8') ?></p>
    <div class="crm-dashboard-catalog-list" id="dashboardCatalogList" data-dashboard-catalog-list>
      <div class="d-flex align-items-center gap-2 py-1"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> <span data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка'), ENT_QUOTES, 'UTF-8') ?></span></div>
    </div>
  </div>
</div>

<div class="modal fade" id="createProjectModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" data-i18n="dashboard.modal_create_project.title"><?= htmlspecialchars($t('dashboard.modal_create_project.title', 'Создать проект'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div><form id="createProjectForm" novalidate><div class="modal-body"><div class="row g-3"><div class="col-md-8"><label class="form-label" data-i18n="dashboard.modal_create_project.label_title"><?= htmlspecialchars($t('dashboard.modal_create_project.label_title', 'Название проекта'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" maxlength="255" placeholder="<?= htmlspecialchars($t('dashboard.modal_create_project.placeholder_title', 'Запуск клиентского портала'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="dashboard.modal_create_project.placeholder_title"></div><div class="col-md-4"><label class="form-label" data-i18n="dashboard.modal_create_project.label_status"><?= htmlspecialchars($t('dashboard.modal_create_project.label_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="status"><option value="active" data-i18n="tasks.status_active"><?= htmlspecialchars($t('tasks.status_active', 'Активный'), ENT_QUOTES, 'UTF-8') ?></option><option value="new" data-i18n="tasks.status_new"><?= htmlspecialchars($t('tasks.status_new', 'К выполнению'), ENT_QUOTES, 'UTF-8') ?></option><option value="in_progress" data-i18n="tasks.status_in_progress"><?= htmlspecialchars($t('tasks.status_in_progress', 'В работе'), ENT_QUOTES, 'UTF-8') ?></option><option value="blocked" data-i18n="tasks.status_blocked"><?= htmlspecialchars($t('tasks.status_blocked', 'Блокирован'), ENT_QUOTES, 'UTF-8') ?></option><option value="done" data-i18n="tasks.status_done"><?= htmlspecialchars($t('tasks.status_done', 'Завершен'), ENT_QUOTES, 'UTF-8') ?></option></select></div><div class="col-md-6"><label class="form-label" data-i18n="dashboard.modal_create_project.label_client"><?= htmlspecialchars($t('dashboard.modal_create_project.label_client', 'Клиент'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="client_public_id"><option value="" data-i18n="dashboard.modal_create_project.option_no_client"><?= htmlspecialchars($t('dashboard.modal_create_project.option_no_client', 'Без клиента'), ENT_QUOTES, 'UTF-8') ?></option></select></div><div class="col-md-6"><label class="form-label" data-i18n="dashboard.modal_create_project.label_team"><?= htmlspecialchars($t('dashboard.modal_create_project.label_team', 'Команда'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="team_public_id"><option value="" data-i18n="dashboard.modal_create_project.option_no_team"><?= htmlspecialchars($t('dashboard.modal_create_project.option_no_team', 'Команда не назначена'), ENT_QUOTES, 'UTF-8') ?></option></select></div><div class="col-md-6"><label class="form-label" data-i18n="dashboard.modal_create_project.label_manager"><?= htmlspecialchars($t('dashboard.modal_create_project.label_manager', 'Менеджер проекта'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="manager_user_public_id"><option value="" data-i18n="dashboard.modal_create_project.option_no_manager"><?= htmlspecialchars($t('dashboard.modal_create_project.option_no_manager', 'Без менеджера'), ENT_QUOTES, 'UTF-8') ?></option></select></div><div class="col-md-6"><label class="form-label" data-i18n="dashboard.modal_create_project.label_priority"><?= htmlspecialchars($t('dashboard.modal_create_project.label_priority', 'Приоритет'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="priority"><option value="normal" data-i18n="priority.normal"><?= htmlspecialchars($t('priority.normal', 'Нормальный'), ENT_QUOTES, 'UTF-8') ?></option><option value="low" data-i18n="priority.low"><?= htmlspecialchars($t('priority.low', 'Низкий'), ENT_QUOTES, 'UTF-8') ?></option><option value="high" data-i18n="priority.high"><?= htmlspecialchars($t('priority.high', 'Высокий'), ENT_QUOTES, 'UTF-8') ?></option><option value="urgent" data-i18n="priority.urgent"><?= htmlspecialchars($t('priority.urgent', 'Срочный'), ENT_QUOTES, 'UTF-8') ?></option></select></div><div class="col-12"><label class="form-label" data-i18n="dashboard.modal_create_project.label_description"><?= htmlspecialchars($t('dashboard.modal_create_project.label_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="description" rows="4" placeholder="<?= htmlspecialchars($t('dashboard.modal_create_project.placeholder_description', 'Цели, контекст и основные ожидания по проекту'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="dashboard.modal_create_project.placeholder_description" data-crm-visual-editor="1" data-richtext-off="1"></textarea></div><div class="col-12"><div class="form-text" data-project-create-hint data-i18n="dashboard.modal_create_project.hint"><?= htmlspecialchars($t('dashboard.modal_create_project.hint', 'Проект будет создан сразу в рабочей модели API, включая клиента, команду и менеджера.'), ENT_QUOTES, 'UTF-8') ?></div></div></div></div><div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button type="submit" class="btn crm-btn-primary" data-i18n="page.create"><?= htmlspecialchars($t('page.create', 'Создать'), ENT_QUOTES, 'UTF-8') ?></button></div></form></div></div></div>
