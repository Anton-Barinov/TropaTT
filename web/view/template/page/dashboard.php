<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Главная'; ?>
<body data-page="dashboard" data-protected="1">
<div class="crm-app">
  <aside class="crm-sidebar">
    <div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div>
    <nav class="nav flex-column crm-nav"></nav>
  </aside>

  <div class="crm-main-wrap">
    <header class="crm-topbar py-2">
      <div class="container-fluid"></div>
    </header>

    <main class="crm-content crm-dashboard-page">
      <div class="crm-page-head">
        <div>
          <h1 class="crm-page-title">Главная</h1>
          <p class="crm-subtitle mb-0" data-dashboard-subtitle>Срез по задачам, рискам и загрузке команд на 01 мая 2026 г.</p>
        </div>
        <div class="crm-page-actions">
          <button class="btn crm-btn-primary d-inline-flex align-items-center gap-2" data-open-modal="createTaskModal" type="button">
            <span>Создать задачу</span>
          </button>
        </div>
      </div>

      <section class="crm-dashboard-section">
        <div class="row g-3">
          <div class="col-12 col-sm-6 col-xl-3">
            <a class="text-decoration-none text-reset d-block" href="index.php?route=tasks&kpi=active" aria-label="Открыть выборку активных задач">
              <article class="crm-dashboard-kpi is-active" data-kpi-link="active_tasks">
                <div class="crm-dashboard-kpi-main">
                  <div class="crm-dashboard-kpi-icon" aria-hidden="true">
                    <i class="fa-regular fa-rectangle-list"></i>
                  </div>
                  <div>
                    <div class="crm-dashboard-kpi-meta">
                      <span class="crm-dashboard-kpi-label">Активные задачи</span>
                      <span class="crm-badge archived">Загрузка</span>
                    </div>
                    <strong>—</strong>
                    <p class="crm-dashboard-kpi-note">Задач к выполнению сегодня: 0.</p>
                  </div>
                </div>
              </article>
            </a>
          </div>
          <div class="col-12 col-sm-6 col-xl-3">
            <a class="text-decoration-none text-reset d-block" href="index.php?route=tasks&kpi=overdue" aria-label="Открыть выборку просроченных задач">
              <article class="crm-dashboard-kpi is-danger" data-kpi-link="overdue_tasks">
                <div class="crm-dashboard-kpi-main">
                  <div class="crm-dashboard-kpi-icon" aria-hidden="true">
                    <i class="fa-regular fa-clock"></i>
                  </div>
                  <div>
                    <div class="crm-dashboard-kpi-meta">
                      <span class="crm-dashboard-kpi-label">Просрочено</span>
                      <span class="crm-badge archived">Загрузка</span>
                    </div>
                    <strong>—</strong>
                    <p class="crm-dashboard-kpi-note">Просроченных задач в системе: 0.</p>
                  </div>
                </div>
              </article>
            </a>
          </div>
          <div class="col-12 col-sm-6 col-xl-3">
            <a class="text-decoration-none text-reset d-block" href="index.php?route=projects&status=active&kpi=active_projects" aria-label="Открыть выборку активных проектов">
              <article class="crm-dashboard-kpi is-warning" data-kpi-link="active_projects">
                <div class="crm-dashboard-kpi-main">
                  <div class="crm-dashboard-kpi-icon" aria-hidden="true">
                    <i class="fa-regular fa-folder-open"></i>
                  </div>
                  <div>
                    <div class="crm-dashboard-kpi-meta">
                      <span class="crm-dashboard-kpi-label">Активные проекты</span>
                      <span class="crm-badge archived">Загрузка</span>
                    </div>
                    <strong>—</strong>
                    <p class="crm-dashboard-kpi-note">Активных проектов в работе: 0.</p>
                  </div>
                </div>
              </article>
            </a>
          </div>
          <div class="col-12 col-sm-6 col-xl-3">
            <a class="text-decoration-none text-reset d-block" href="index.php?route=tasks&kpi=sla_week" aria-label="Открыть выборку задач по SLA недели">
              <article class="crm-dashboard-kpi is-success" data-kpi-link="sla_week">
                <div class="crm-dashboard-kpi-main">
                  <div class="crm-dashboard-kpi-icon" aria-hidden="true">
                    <i class="fa-solid fa-stopwatch"></i>
                  </div>
                  <div>
                    <div class="crm-dashboard-kpi-meta">
                      <span class="crm-dashboard-kpi-label">SLA недели</span>
                      <span class="crm-badge archived">Загрузка</span>
                    </div>
                    <strong>—</strong>
                    <p class="crm-dashboard-kpi-note">Суммарный worklog за неделю: 0 мин.</p>
                  </div>
                </div>
              </article>
            </a>
          </div>
        </div>
      </section>

      <section class="crm-card crm-dashboard-section crm-dashboard-actions">
        <h2 class="crm-dashboard-actions-title mb-0">Быстрые действия</h2>
        <div class="crm-dashboard-actions-list">
          <button class="btn crm-btn-secondary crm-dashboard-action-chip" type="button" data-open-drawer="quickTaskDrawer"><span class="crm-dashboard-chip-icon" aria-hidden="true"><i class="fa-regular fa-folder"></i></span>Открыть последнюю задачу</button>
          <button class="btn crm-btn-secondary crm-dashboard-action-chip" type="button" data-open-modal="assignUserModal"><span class="crm-dashboard-chip-icon" aria-hidden="true"><i class="fa-solid fa-user-plus"></i></span>Назначить исполнителя</button>
          <button class="btn crm-btn-secondary crm-dashboard-action-chip" type="button" data-open-modal="createProjectModal"><span class="crm-dashboard-chip-icon" aria-hidden="true"><i class="fa-solid fa-circle-plus"></i></span>Создать проект</button>
          <a class="btn crm-btn-secondary crm-dashboard-action-chip" href="index.php?route=calendar"><span class="crm-dashboard-chip-icon" aria-hidden="true"><i class="fa-regular fa-calendar"></i></span>Открыть календарь</a>
          <a class="btn crm-btn-secondary crm-dashboard-action-chip" href="index.php?route=kanban"><span class="crm-dashboard-chip-icon" aria-hidden="true"><i class="fa-solid fa-table-columns"></i></span>Открыть канбан</a>
        </div>
      </section>

      <section class="crm-card crm-dashboard-section" id="dashboardAiDigestCard" data-requires-ai-use="1" data-ai-state="idle">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
          <div>
            <h2 class="h5 mb-1 d-inline-flex align-items-center gap-2">
              <span class="crm-dashboard-inline-icon" aria-hidden="true">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
              </span>
              AI-сводка дня
            </h2>
            <p class="text-muted mb-0" id="dashboardAiDigestMeta">Сводка по рискам и фокусу. Источник: AI suggestion `dashboard_daily_digest`.</p>
          </div>
          <button type="button" class="btn btn-sm crm-btn-secondary" id="dashboardAiDigestRefreshBtn">Обновить AI-сводку</button>
        </div>
        <div id="dashboardAiDigestSummary" class="crm-dashboard-ai-summary-panel mb-3">
          <strong>AI-сводка не сформирована</strong>
          <p class="mb-0 crm-dashboard-ai-text">Нажмите кнопку «Обновить AI-сводку», чтобы получить рекомендацию.</p>
        </div>
        <div class="row g-2">
          <div class="col-12 col-md-6 col-xl-3">
            <div class="crm-info-panel h-100 crm-dashboard-ai-subcard">
              <h3 class="h6 mb-2 d-inline-flex align-items-center gap-2"><span class="crm-dashboard-inline-icon is-danger" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>Риски</h3>
              <div id="dashboardAiDigestRisks"></div>
            </div>
          </div>
          <div class="col-12 col-md-6 col-xl-3">
            <div class="crm-info-panel h-100 crm-dashboard-ai-subcard">
              <h3 class="h6 mb-2 d-inline-flex align-items-center gap-2"><span class="crm-dashboard-inline-icon is-warning" aria-hidden="true"><i class="fa-solid fa-star"></i></span>Highlights</h3>
              <div id="dashboardAiDigestHighlights"></div>
            </div>
          </div>
          <div class="col-12 col-xl-6">
            <div class="crm-info-panel h-100 crm-dashboard-ai-subcard">
              <h3 class="h6 mb-2 d-inline-flex align-items-center gap-2"><span class="crm-dashboard-inline-icon is-success" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>Рекомендуемые действия</h3>
              <div id="dashboardAiDigestActions"></div>
            </div>
          </div>
        </div>
      </section>

      <section class="crm-card crm-dashboard-section">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 class="h5 mb-0">Задачи на сегодня</h2>
          <a href="index.php?route=tasks" class="fw-semibold">Все задачи</a>
        </div>

        <div class="table-responsive crm-dashboard-table-wrap d-none d-md-block">
          <table class="table crm-table mb-0 crm-dashboard-task-table">
            <thead>
              <tr>
                <th>Задача</th>
                <th>Проект</th>
                <th>Исполнитель</th>
                <th>Срок</th>
                <th>Статус</th>
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

      <section class="crm-dashboard-section">
        <div class="row g-3">
          <aside class="col-12 col-lg-4">
            <div class="crm-card h-100 crm-dashboard-widget">
              <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <h2 class="h5 mb-0">Риски</h2>
                <a href="index.php?route=tasks&kpi=overdue">Подробнее</a>
              </div>
               <div data-dashboard-risks-list>
                <div class="crm-skeleton crm-skeleton--text"></div><div class="crm-skeleton crm-skeleton--text-sm"></div>
              </div>
              <div data-dashboard-risks-metrics class="mt-2"></div>
            </div>
          </aside>
          <section class="col-12 col-lg-4">
            <div class="crm-card h-100 crm-dashboard-widget">
              <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <h2 class="h5 mb-0">Активность</h2>
                <a href="index.php?route=notifications">Подробнее</a>
              </div>
               <div class="crm-timeline" data-dashboard-activity-list>
                <div class="crm-timeline-item"><div class="crm-skeleton crm-skeleton--text"></div><div class="crm-skeleton crm-skeleton--text-sm"></div></div>
                <div class="crm-timeline-item"><div class="crm-skeleton crm-skeleton--text"></div><div class="crm-skeleton crm-skeleton--text-sm"></div></div>
              </div>
            </div>
          </section>
          <section class="col-12 col-lg-4">
            <div class="crm-card h-100 crm-dashboard-widget">
              <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <h2 class="h5 mb-0">Обзор проектов</h2>
                <a href="index.php?route=projects">Подробнее</a>
              </div>
              <div data-dashboard-overview-list>
                <div class="crm-skeleton crm-skeleton--text"></div><div class="crm-skeleton crm-skeleton--text-sm mb-2"></div>
              </div>
            </div>
          </section>
        </div>
      </section>

      <section class="crm-card crm-dashboard-section">
        <h2 class="h5">Рабочие заметки</h2>
        <div class="accordion crm-dashboard-notes" id="opsAccordion">
          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#opsOne" data-dashboard-note-1-title>Контроль просроченных задач</button>
            </h3>
            <div id="opsOne" class="accordion-collapse collapse show" data-bs-parent="#opsAccordion">
              <div class="accordion-body" data-dashboard-note-1-body>Просрочено задач: 9. Приоритетно обработайте задачи с ближайшими дедлайнами и статусом blocked.</div>
            </div>
          </div>
          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#opsTwo" data-dashboard-note-2-title>План на сегодня</button>
            </h3>
            <div id="opsTwo" class="accordion-collapse collapse" data-bs-parent="#opsAccordion">
              <div class="accordion-body" data-dashboard-note-2-body>Сегодня нет запланированных событий и напоминаний. Можно сфокусироваться на закрытии текущего бэклога.</div>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>
</div>

<div class="modal fade" id="createProjectModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Создать проект</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div><form id="createProjectForm" novalidate><div class="modal-body"><div class="row g-3"><div class="col-md-8"><label class="form-label">Название проекта</label><input class="form-control" name="title" maxlength="255" placeholder="Запуск клиентского портала"></div><div class="col-md-4"><label class="form-label">Статус</label><select class="form-select" name="status"><option value="active">Активный</option><option value="new">К выполнению</option><option value="in_progress">В работе</option><option value="blocked">Блокирован</option><option value="done">Завершен</option></select></div><div class="col-md-6"><label class="form-label">Клиент</label><select class="form-select" name="client_public_id"><option value="">Без клиента</option></select></div><div class="col-md-6"><label class="form-label">Команда</label><select class="form-select" name="team_public_id"><option value="">Команда не назначена</option></select></div><div class="col-md-6"><label class="form-label">Менеджер проекта</label><select class="form-select" name="manager_user_public_id"><option value="">Без менеджера</option></select></div><div class="col-md-6"><label class="form-label">Приоритет</label><select class="form-select" name="priority"><option value="normal">Нормальный</option><option value="low">Низкий</option><option value="high">Высокий</option><option value="urgent">Срочный</option></select></div><div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" name="description" rows="4" placeholder="Цели, контекст и основные ожидания по проекту"></textarea></div><div class="col-12"><div class="form-text" data-project-create-hint>Проект будет создан сразу в рабочей модели API, включая клиента, команду и менеджера.</div></div></div></div><div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button><button type="submit" class="btn crm-btn-primary">Создать</button></div></form></div></div></div>
