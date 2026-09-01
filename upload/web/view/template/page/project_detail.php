<?php declare(strict_types=1); ?>
<?php $title = $t('project_detail.title', 'TropaTT — Карточка проекта'); ?>
<body data-page="projects" data-protected="1">
<div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content">

<div class="crm-page-head">
  <div>
    <ol class="breadcrumb mb-1">
      <li class="breadcrumb-item"><a href="index.php?route=projects" data-i18n="projects.breadcrumb"><?= htmlspecialchars($t('projects.breadcrumb', 'Проекты'), ENT_QUOTES, 'UTF-8') ?></a></li>
      <li class="breadcrumb-item active" id="projectBreadcrumbTitle" data-i18n="project_detail.breadcrumb"><?= htmlspecialchars($t('project_detail.breadcrumb', 'Карточка проекта'), ENT_QUOTES, 'UTF-8') ?></li>
    </ol>
    <h1 class="crm-page-title" data-i18n="project_detail.page_title"><?= htmlspecialchars($t('project_detail.page_title', 'Карточка проекта'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="crm-subtitle" data-i18n="project_detail.loading_data"><?= htmlspecialchars($t('project_detail.loading_data', 'Загрузка данных проекта...'), ENT_QUOTES, 'UTF-8') ?></p>
    <div class="d-flex gap-2 flex-wrap mt-2">
      <span class="crm-chip" id="projectPublicIdChip" data-i18n="project_detail.label_id"><?= htmlspecialchars($t('project_detail.label_id', 'ID: —'), ENT_QUOTES, 'UTF-8') ?></span>
      <span class="crm-chip" id="projectRowVersionChip" data-i18n="project_detail.label_version"><?= htmlspecialchars($t('project_detail.label_version', 'Версия: —'), ENT_QUOTES, 'UTF-8') ?></span>
      <span class="crm-badge archived" id="projectStatusBadge">—</span>
    </div>
  </div>
  <div class="crm-page-actions">
    <button class="btn crm-btn-secondary" type="button" data-open-drawer="projectQuickPreviewDrawer" data-i18n="project_detail.btn_preview"><?= htmlspecialchars($t('project_detail.btn_preview', 'Предпросмотр'), ENT_QUOTES, 'UTF-8') ?></button>
    <?php if (empty($is_external_user)): // client portal: internal-only control ?><button class="btn crm-btn-primary" type="button" data-open-modal="projectEditModal" id="projectHeaderEditBtn" data-i18n="project_detail.btn_edit"><?= htmlspecialchars($t('project_detail.btn_edit', 'Редактировать'), ENT_QUOTES, 'UTF-8') ?></button>
    <div class="crm-pr-menu-wrap">
      <button class="btn crm-btn-secondary crm-pr-menu-btn" type="button" id="projectMoreBtn" aria-haspopup="menu" aria-expanded="false" aria-label="<?= htmlspecialchars($t('project_detail.btn_more', 'Ещё действия'), ENT_QUOTES, 'UTF-8') ?>"><span aria-hidden="true">⋯</span></button>
      <div class="crm-pr-menu" id="projectMoreMenu" role="menu">
        <a href="index.php?route=projects" role="menuitem" data-i18n="project_detail.link_back_to_projects"><?= htmlspecialchars($t('project_detail.link_back_to_projects', 'К списку проектов'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="index.php?route=companies" role="menuitem" data-i18n="project_detail.link_companies"><?= htmlspecialchars($t('project_detail.link_companies', 'Компании'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="index.php?route=contacts" role="menuitem" data-i18n="project_detail.link_contacts"><?= htmlspecialchars($t('project_detail.link_contacts', 'Контакты'), ENT_QUOTES, 'UTF-8') ?></a>
        <div class="crm-pr-menu-divider" role="separator"></div>
        <button type="button" id="projectArchiveMenuBtn" role="menuitem" class="crm-pr-menu-danger" data-i18n="project_detail.menu_archive_project"><?= htmlspecialchars($t('project_detail.menu_archive_project', 'Архивировать проект'), ENT_QUOTES, 'UTF-8') ?></button>
      </div><?php endif; ?>
    </div>
  </div>
</div>

<div class="crm-pr-rail-wrap" id="projectProgressRail">
  <div class="crm-pr-rail-label"><span data-i18n="project_detail.progress_rail_label"><?= htmlspecialchars($t('project_detail.progress_rail_label', 'Прогресс проекта'), ENT_QUOTES, 'UTF-8') ?></span><b id="projectProgressRailValue">0%</b></div>
  <div class="crm-pr-rail"><span id="projectProgressRailFill" style="width:0%"></span></div>
</div>

<div class="crm-pr-tabs" role="tablist" aria-label="<?= htmlspecialchars($t('project_detail.tabs_label', 'Разделы проекта'), ENT_QUOTES, 'UTF-8') ?>">
  <button class="crm-pr-tab active" type="button" role="tab" aria-selected="true" data-project-tab="overview" id="projectTabOverview"><?= htmlspecialchars($t('project_detail.tab_overview', 'Обзор'), ENT_QUOTES, 'UTF-8') ?></button>
  <button class="crm-pr-tab" type="button" role="tab" aria-selected="false" data-project-tab="tasks" id="projectTabTasks"><?= htmlspecialchars($t('project_detail.tab_tasks', 'Задачи'), ENT_QUOTES, 'UTF-8') ?><span class="crm-pr-tab-count" id="projectTaskTabCount">0</span></button>
  <button class="crm-pr-tab" type="button" role="tab" aria-selected="false" data-project-tab="chat" id="projectTabChat"><?= htmlspecialchars($t('project_detail.tab_client_chat', 'Чат'), ENT_QUOTES, 'UTF-8') ?></button>
<?php if (empty($is_external_user)): // client portal: internal-only block ?>
  <button class="crm-pr-tab" type="button" role="tab" aria-selected="false" data-project-tab="ai" id="projectTabAi"><?= htmlspecialchars($t('project_detail.tab_ai', 'AI-инсайты'), ENT_QUOTES, 'UTF-8') ?></button>
<?php endif; ?>
<?php if (empty($is_external_user)): // client portal: internal-only block ?>
  <button class="crm-pr-tab" type="button" role="tab" aria-selected="false" data-project-tab="activity" id="projectTabActivity"><?= htmlspecialchars($t('project_detail.tab_activity', 'Активность'), ENT_QUOTES, 'UTF-8') ?></button>
<?php endif; ?>
</div>

<!-- ============ OVERVIEW ============ -->
<div class="crm-pr-panel active" data-project-panel="overview" role="tabpanel">
  <section class="crm-card crm-pr-hero mb-3">
    <div class="crm-pr-hero-ring" id="projectProgressRing"></div>
    <div class="crm-pr-v-divider" aria-hidden="true"></div>
    <div class="crm-pr-hero-facts" id="projectMetricsList"><div class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка'), ENT_QUOTES, 'UTF-8') ?></div></div>
  </section>

  <div class="crm-pr-cols">
    <div class="crm-pr-col-main">
      <section class="crm-card mb-3">
        <div class="crm-pr-card-head">
          <h2 class="h6 mb-0" data-i18n="project_detail.section_about"><?= htmlspecialchars($t('project_detail.section_about', 'О проекте'), ENT_QUOTES, 'UTF-8') ?></h2>
          <button class="crm-inline-icon-btn" type="button" data-open-modal="projectEditModal" aria-label="<?= htmlspecialchars($t('project_detail.btn_edit', 'Редактировать'), ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($t('project_detail.btn_edit', 'Редактировать'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
        </div>
        <div id="projectEditAccessNote" class="alert alert-secondary py-2" data-i18n="project_detail.edit_access_note"><?= htmlspecialchars($t('project_detail.edit_access_note', 'Права редактирования проверяются...'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="crm-pr-id-grid" id="projectAboutGrid"><div class="text-muted" data-i18n="project_detail.loading_parameters"><?= htmlspecialchars($t('project_detail.loading_parameters', 'Загрузка параметров проекта...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </section>

      <section class="crm-card mb-3" id="projectAiCompactCard">
        <div class="crm-pr-card-head">
          <div>
            <h2 class="h6 mb-0" data-i18n="project_detail.section_ai"><?= htmlspecialchars($t('project_detail.section_ai', 'AI по проекту'), ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="small text-muted" id="projectAiCompactState" data-i18n="project_detail.ai_state_idle"><?= htmlspecialchars($t('project_detail.ai_state_idle', 'AI-сводка проекта не сформирована.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="crm-pr-segmented" role="tablist" aria-label="<?= htmlspecialchars($t('project_detail.ai_segment_label', 'Раздел AI'), ENT_QUOTES, 'UTF-8') ?>">
            <button type="button" class="active" data-ai-segment="summary" role="tab" aria-selected="true" data-i18n="project_detail.ai_segment_summary"><?= htmlspecialchars($t('project_detail.ai_segment_summary', 'Сводка'), ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" data-ai-segment="risks" role="tab" aria-selected="false" data-i18n="project_detail.ai_segment_risks"><?= htmlspecialchars($t('project_detail.ai_segment_risks', 'Риски'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
        </div>
        <div class="crm-pr-ai-compact-body">
          <div class="crm-pr-ai-compact-panel active" data-ai-panel="summary">
            <div class="crm-pr-ai-compact-text" id="projectAiCompactSummary" data-i18n="project_detail.ai_summary_empty"><?= htmlspecialchars($t('project_detail.ai_summary_empty', 'Нажмите «AI-сводка», чтобы получить AI-рекомендацию по проекту.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="crm-pr-ai-compact-panel" data-ai-panel="risks">
            <div class="crm-pr-ai-compact-list" id="projectAiCompactRisks">—</div>
          </div>
        </div>
        <div class="mt-2">
          <button class="btn btn-sm crm-btn-secondary" type="button" id="projectAiCompactOpenBtn" data-i18n="project_detail.ai_open_full"><?= htmlspecialchars($t('project_detail.ai_open_full', 'Открыть AI-инсайты'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </section>
    </div>

    <div class="crm-pr-col-side">
      <?= module_position('project.detail.sidebar', ['route' => $route ?? 'project-detail', 'project_public_id' => (string)($_GET['project_public_id'] ?? '')]) ?>
<?php if (empty($is_external_user)): // client portal: internal-only block ?>
      <section class="crm-card mb-3"><h2 class="h6" data-i18n="project_detail.section_team"><?= htmlspecialchars($t('project_detail.section_team', 'Команда'), ENT_QUOTES, 'UTF-8') ?></h2><div id="projectTeamList"><div class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div></section>
<?php endif; ?>
<?php if (empty($is_external_user)): // client portal: internal-only block ?>
      <section class="crm-card mb-3 d-none" id="projectRatesSection"><h2 class="h6" data-i18n="project_detail.section_rates"><?= htmlspecialchars($t('project_detail.section_rates', 'Прайс проекта'), ENT_QUOTES, 'UTF-8') ?></h2><div id="projectRatesBlock"><div class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div></section>
<?php endif; ?>
<?php if (empty($is_external_user)): // client portal: internal-only block ?>
      <section class="crm-card mb-3" id="projectKnowledgeSection"><h2 class="h6" data-i18n="project_detail.section_knowledge"><?= htmlspecialchars($t('project_detail.section_knowledge', 'База знаний'), ENT_QUOTES, 'UTF-8') ?></h2><div id="projectKnowledgeList"><div class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div><div class="mt-3 pt-3 border-top"><h3 class="h6 d-flex align-items-center gap-2 mb-2" data-i18n="project_detail.team_knowledge_title"><span class="crm-icon text-muted" aria-hidden="true"><i class="fa-solid fa-users" aria-hidden="true"></i></span><?= htmlspecialchars($t('project_detail.team_knowledge_title', 'Материалы команды'), ENT_QUOTES, 'UTF-8') ?></h3><div id="projectTeamKnowledgeList"><div class="text-muted small" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div></div><div class="mt-2 d-flex gap-2 flex-wrap"><a class="btn btn-sm crm-btn-primary" href="index.php?route=knowledge" data-i18n="project_detail.btn_knowledge"><?= htmlspecialchars($t('project_detail.btn_knowledge', 'Перейти в базу знаний'), ENT_QUOTES, 'UTF-8') ?></a><a id="projectCreateKnowledgeBtn" class="btn btn-sm crm-btn-secondary" href="index.php?route=knowledge" data-i18n="project_detail.btn_create_knowledge"><?= htmlspecialchars($t('project_detail.btn_create_knowledge', 'Создать связанную страницу'), ENT_QUOTES, 'UTF-8') ?></a></div></section>
<?php else: // client portal: external user sees client-visible knowledge pages ?>
      <section class="crm-card mb-3" id="projectClientKnowledgeSection"><h2 class="h6" data-i18n="project_detail.section_client_knowledge"><?= htmlspecialchars($t('project_detail.section_client_knowledge', 'Документы для клиента'), ENT_QUOTES, 'UTF-8') ?></h2><div id="projectClientKnowledgeList"><div class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div></section>
<?php endif; ?>
    </div>
  </div>
</div>

<!-- ============ TASKS ============ -->
<div class="crm-pr-panel" data-project-panel="tasks" role="tabpanel">
  <div class="crm-pr-toolbar">
    <div>
      <h2 class="h6 mb-1" data-i18n="project_detail.section_tasks_toolbar_title"><?= htmlspecialchars($t('project_detail.section_tasks_toolbar_title', 'Задачи проекта'), ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="crm-subtitle mb-0" id="projectTasksSubtitle">0</p>
    </div>
    <button class="btn crm-btn-primary" type="button" data-open-modal="projectCreateTaskModal" id="projectCreateTaskOpenBtn" data-i18n="project_detail.btn_new_task"><?= htmlspecialchars($t('project_detail.btn_new_task', '+ Новая задача'), ENT_QUOTES, 'UTF-8') ?></button>
  </div>

  <section class="crm-card mb-3">
    <div class="table-responsive"><table class="table crm-table"><thead><tr><th data-i18n="project_detail.th_task"><?= htmlspecialchars($t('project_detail.th_task', 'Задача'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="project_detail.th_assignee"><?= htmlspecialchars($t('project_detail.th_assignee', 'Исполнитель'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="project_detail.th_status"><?= htmlspecialchars($t('project_detail.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="project_detail.th_due"><?= htmlspecialchars($t('project_detail.th_due', 'Срок'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="projectTasksTableBody"><tr><td colspan="4" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
  </section>

  <section class="crm-pr-acc" id="projectMilestonesAcc">
    <button class="crm-pr-acc-head" type="button" aria-expanded="false">
      <span class="crm-pr-acc-title">
        <span class="name" data-i18n="project_detail.section_milestones"><?= htmlspecialchars($t('project_detail.section_milestones', 'Этапы проекта'), ENT_QUOTES, 'UTF-8') ?></span>
        <span class="meta" id="projectMilestonesSummary"></span>
      </span>
      <span class="crm-pr-acc-caret" aria-hidden="true">▾</span>
    </button>
    <div class="crm-pr-acc-panel"><div class="crm-pr-acc-inner"><div class="crm-timeline" id="projectMilestonesList"><div class="crm-timeline-item" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div></div></div>
  </section>

  <?php if (empty($is_external_user)): // client portal: internal-only control ?><section class="crm-pr-acc" id="projectModulesAcc">
    <button class="crm-pr-acc-head" type="button" aria-expanded="false">
      <span class="crm-pr-acc-title">
        <span class="name" data-i18n="project_detail.section_modules"><?= htmlspecialchars($t('project_detail.section_modules', 'Модули проекта'), ENT_QUOTES, 'UTF-8') ?></span>
        <span class="meta" id="projectModulesSummary"></span>
      </span>
      <span class="crm-pr-acc-caret" aria-hidden="true">▾</span>
    </button>
    <div class="crm-pr-acc-panel"><div class="crm-pr-acc-inner">
      <div class="mb-2"><button class="btn btn-sm crm-btn-primary crm-btn-compact" type="button" id="projectModuleAddBtn" data-i18n="project_detail.btn_add_module"><?= htmlspecialchars($t('project_detail.btn_add_module', '+ Модуль'), ENT_QUOTES, 'UTF-8') ?></button></div>
      <div id="projectModulesList"><div class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
    </div></div>
  </section><?php endif; ?>
</div>

<!-- ============ CLIENT CHAT ============ -->
<!-- Rendered for everyone with access: external users chat with the team here,
     staff (project manager/admin) see the same conversation via project access. -->
<div class="crm-pr-panel" data-project-panel="chat" role="tabpanel">
  <section class="crm-card mb-3">
    <div class="crm-pr-card-head">
      <h2 class="h6 mb-0" data-i18n="project_detail.section_client_chat"><?= htmlspecialchars($t('project_detail.section_client_chat', 'Чат с командой'), ENT_QUOTES, 'UTF-8') ?></h2>
    </div>
    <div id="projectClientChatMessages" class="crm-chat-messages" style="max-height:400px;"><div class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
    <div id="projectClientChatEmpty" class="text-muted small mb-3" style="display:none;" data-i18n="project_detail.client_chat_empty"><?= htmlspecialchars($t('project_detail.client_chat_empty', 'Чат ещё не создан. Администратор создаст его при необходимости.'), ENT_QUOTES, 'UTF-8') ?></div>
    <div id="projectClientChatReplyPreview" class="crm-chat-reply-preview d-none"></div>
    <form id="projectClientChatForm" class="crm-chat-composer" style="display:none;">
      <input type="hidden" id="projectClientChatReplyId" value="">
      <input class="form-control form-control-sm" id="projectClientChatInput" type="text" maxlength="2000" placeholder="<?= htmlspecialchars($t('project_detail.client_chat_placeholder', 'Напишите сообщение...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="project_detail.client_chat_placeholder" required>
      <button class="btn btn-sm crm-btn-primary crm-chat-send-btn" type="submit" data-i18n="project_detail.client_chat_send"><?= htmlspecialchars($t('project_detail.client_chat_send', 'Отправить'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
  </section>
</div>

<!-- ============ AI INSIGHTS ============ -->
<?php if (empty($is_external_user)): // client portal: internal-only block ?>
<div class="crm-pr-panel" data-project-panel="ai" role="tabpanel">
  <section class="crm-card" id="projectAiCard" data-requires-ai-use="1" data-ai-state="idle">
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
    <div class="crm-empty-state mb-3" id="projectAiSummaryText" data-i18n="project_detail.ai_summary_empty"><?= htmlspecialchars($t('project_detail.ai_summary_empty', 'Нажмите «AI-сводка», чтобы получить AI-рекомендацию по проекту.'), ENT_QUOTES, 'UTF-8') ?></div>

    <div class="crm-pr-ai-accs">
      <section class="crm-pr-acc d-none" id="projectAiReportDraftWrap">
        <button class="crm-pr-acc-head" type="button" aria-expanded="false">
          <span class="crm-pr-acc-title">
            <span class="name" data-i18n="project_detail.ai_report_draft_label"><?= htmlspecialchars($t('project_detail.ai_report_draft_label', 'Client report draft (read-only)'), ENT_QUOTES, 'UTF-8') ?></span>
          </span>
          <span class="crm-pr-acc-caret" aria-hidden="true">▾</span>
        </button>
        <div class="crm-pr-acc-panel"><div class="crm-pr-acc-inner">
          <div id="projectAiReportDraftText">—</div>
        </div></div>
      </section>

      <section class="crm-pr-acc" id="projectAiRisksAcc">
        <button class="crm-pr-acc-head" type="button" aria-expanded="false">
          <span class="crm-pr-acc-title">
            <span class="name" data-i18n="project_detail.ai_risks_label"><?= htmlspecialchars($t('project_detail.ai_risks_label', 'Риски и вопросы'), ENT_QUOTES, 'UTF-8') ?></span>
          </span>
          <span class="crm-pr-acc-caret" aria-hidden="true">▾</span>
        </button>
        <div class="crm-pr-acc-panel"><div class="crm-pr-acc-inner">
          <div id="projectAiRisksQuestions">—</div>
        </div></div>
      </section>

      <section class="crm-pr-acc" id="projectAiNextActionsAcc">
        <button class="crm-pr-acc-head" type="button" aria-expanded="false">
          <span class="crm-pr-acc-title">
            <span class="name" data-i18n="project_detail.ai_next_actions_label"><?= htmlspecialchars($t('project_detail.ai_next_actions_label', 'Следующие действия'), ENT_QUOTES, 'UTF-8') ?></span>
          </span>
          <span class="crm-pr-acc-caret" aria-hidden="true">▾</span>
        </button>
        <div class="crm-pr-acc-panel"><div class="crm-pr-acc-inner">
          <div id="projectAiNextActions">—</div>
        </div></div>
      </section>
    </div>
  </section>
</div>
<?php endif; ?>

<!-- ============ ACTIVITY ============ -->
<?php if (empty($is_external_user)): // client portal: internal-only block ?>
<div class="crm-pr-panel" data-project-panel="activity" role="tabpanel">
  <section class="crm-card mb-3"><h2 class="h6" data-i18n="project_detail.section_activity"><?= htmlspecialchars($t('project_detail.section_activity', 'Недавняя активность'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-timeline" id="projectActivityList"><div class="crm-timeline-item" data-i18n="project_detail.loading_activity"><?= htmlspecialchars($t('project_detail.loading_activity', 'Данные загружаются...'), ENT_QUOTES, 'UTF-8') ?></div></div></section>

  <section class="crm-pr-acc" id="projectHistoryAcc">
    <button class="crm-pr-acc-head" type="button" aria-expanded="false">
      <span class="crm-pr-acc-title">
        <span class="name" data-i18n="project_detail.section_history"><?= htmlspecialchars($t('project_detail.section_history', 'История изменений полей'), ENT_QUOTES, 'UTF-8') ?></span>
        <span class="meta" data-i18n="project_detail.section_history_note"><?= htmlspecialchars($t('project_detail.section_history_note', 'Хронология изменений атрибутов проекта.'), ENT_QUOTES, 'UTF-8') ?></span>
      </span>
      <span class="crm-pr-acc-caret" aria-hidden="true">▾</span>
    </button>
    <div class="crm-pr-acc-panel"><div class="crm-pr-acc-inner">
      <div class="table-responsive"><table class="table table-sm crm-table mb-0"><thead><tr><th data-i18n="project_detail.th_date"><?= htmlspecialchars($t('project_detail.th_date', 'Дата'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="project_detail.th_field"><?= htmlspecialchars($t('project_detail.th_field', 'Поле'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="project_detail.th_was"><?= htmlspecialchars($t('project_detail.th_was', 'Было'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="project_detail.th_became"><?= htmlspecialchars($t('project_detail.th_became', 'Стало'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="project_detail.th_changed_by"><?= htmlspecialchars($t('project_detail.th_changed_by', 'Кем изменено'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="projectHistoryList"><tr><td colspan="5" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
    </div></div>
  </section>
</div>
<?php endif; ?>

</main></div></div>

<!-- ===== Create task modal ===== -->
<div class="modal fade" id="projectCreateTaskModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="project_detail.modal_new_task_title"><?= htmlspecialchars($t('project_detail.modal_new_task_title', 'Новая задача'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <form id="projectCreateTaskForm" novalidate>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-8">
              <label class="form-label" for="projectTaskTitleInput" data-i18n="project_detail.field_task_title"><?= htmlspecialchars($t('project_detail.field_task_title', 'Название задачи'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" id="projectTaskTitleInput" name="title" maxlength="255" required placeholder="<?= htmlspecialchars($t('project_detail.placeholder_task_title', 'Например: Подготовить релиз'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="project_detail.placeholder_task_title">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="projectTaskStatusInput" data-i18n="project_detail.field_status"><?= htmlspecialchars($t('project_detail.field_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="projectTaskStatusInput" name="status">
                <option value="new" data-i18n="projects.status_new"><?= htmlspecialchars($t('projects.status_new', 'К выполнению'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="in_progress" data-i18n="projects.status_in_progress"><?= htmlspecialchars($t('projects.status_in_progress', 'В работе'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="blocked" data-i18n="projects.status_blocked"><?= htmlspecialchars($t('projects.status_blocked', 'Блокирован'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="done" data-i18n="projects.status_done"><?= htmlspecialchars($t('projects.status_done', 'Завершен'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-md-4">
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
              <textarea class="form-control" id="projectTaskDescriptionInput" name="description" rows="3" placeholder="<?= htmlspecialchars($t('project_detail.placeholder_task_description', 'Что нужно сделать'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="project_detail.placeholder_task_description" data-crm-visual-editor="1" data-richtext-off="1"></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="projectTaskDueAtInput" data-i18n="project_detail.field_due_at"><?= htmlspecialchars($t('project_detail.field_due_at', 'Срок'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" id="projectTaskDueAtInput" name="due_at" type="datetime-local" min="<?= date('Y-m-d\\TH:i') ?>">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn crm-btn-muted" id="projectCreateTaskResetBtn" data-i18n="project_detail.btn_clear"><?= htmlspecialchars($t('project_detail.btn_clear', 'Очистить'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="submit" class="btn crm-btn-primary" id="projectCreateTaskBtn" data-i18n="project_detail.btn_create_task"><?= htmlspecialchars($t('project_detail.btn_create_task', 'Создать задачу'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===== Edit project modal ===== -->
<div class="modal fade" id="projectEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="project_detail.modal_edit_title"><?= htmlspecialchars($t('project_detail.modal_edit_title', 'Редактировать проект'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <form id="projectEditForm">
        <div class="modal-body">
          <div class="crm-pr-mtabs" role="tablist">
            <button type="button" class="active" role="tab" aria-selected="true" data-project-edit-tab="identity" data-i18n="project_detail.edit_tab_identity"><?= htmlspecialchars($t('project_detail.edit_tab_identity', 'Название и описание'), ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" role="tab" aria-selected="false" data-project-edit-tab="workflow" data-i18n="project_detail.edit_tab_workflow"><?= htmlspecialchars($t('project_detail.edit_tab_workflow', 'Статус и приоритет'), ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" role="tab" aria-selected="false" data-project-edit-tab="links" data-i18n="project_detail.edit_tab_links"><?= htmlspecialchars($t('project_detail.edit_tab_links', 'Связи проекта'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
          <div class="crm-pr-mpanel active" data-project-edit-panel="identity">
            <div class="mb-3">
              <label class="form-label" for="projectEditTitle" data-i18n="project_detail.field_name"><?= htmlspecialchars($t('project_detail.field_name', 'Название'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" id="projectEditTitle" name="title" maxlength="255">
            </div>
            <div class="mb-0">
              <label class="form-label" for="projectEditDescription" data-i18n="project_detail.field_description"><?= htmlspecialchars($t('project_detail.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label>
              <textarea class="form-control" id="projectEditDescription" name="description" rows="5" data-crm-visual-editor="1" data-richtext-off="1"></textarea>
            </div>
          </div>
          <div class="crm-pr-mpanel" data-project-edit-panel="workflow">
            <div class="row g-3">
              <div class="col-6">
                <label class="form-label" for="projectEditStatus" data-i18n="project_detail.field_status"><?= htmlspecialchars($t('project_detail.field_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select" id="projectEditStatus" name="status"></select>
              </div>
              <div class="col-6">
                <label class="form-label" for="projectEditPriority" data-i18n="project_detail.field_priority"><?= htmlspecialchars($t('project_detail.field_priority', 'Приоритет'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select" id="projectEditPriority" name="priority"></select>
              </div>
            </div>
          </div>
          <div class="crm-pr-mpanel" data-project-edit-panel="links">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label" for="projectEditClient" data-i18n="project_detail.summary_client"><?= htmlspecialchars($t('project_detail.summary_client', 'Клиент'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select" id="projectEditClient" name="client_public_id"></select>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="projectEditManager" data-i18n="project_detail.summary_manager"><?= htmlspecialchars($t('project_detail.summary_manager', 'Менеджер'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select" id="projectEditManager" name="manager_user_public_id"></select>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="projectEditTeam" data-i18n="project_detail.summary_team"><?= htmlspecialchars($t('project_detail.summary_team', 'Команда'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select" id="projectEditTeam" name="team_public_id"></select>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="submit" class="btn crm-btn-primary" id="projectEditSaveBtn" data-i18n="project_detail.btn_save_changes"><?= htmlspecialchars($t('project_detail.btn_save_changes', 'Сохранить изменения'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===== Create/Edit Module Modal ===== -->
<div class="modal fade" id="projectModuleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="projectModuleModalTitle" data-i18n="project_modules.modal_create_title"><?= htmlspecialchars($t('project_modules.modal_create_title', 'Создать модуль'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <form id="projectModuleForm">
        <input type="hidden" id="projectModulePublicId" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="projectModuleTitle" data-i18n="project_modules.field_title"><?= htmlspecialchars($t('project_modules.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" id="projectModuleTitle" required maxlength="255" data-i18n-placeholder="project_modules.field_title_placeholder" placeholder="<?= htmlspecialchars($t('project_modules.field_title_placeholder', 'Например: Оплата'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="projectModuleProject" data-i18n="project_modules.field_project"><?= htmlspecialchars($t('project_modules.field_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="projectModuleProject" required>
                <option value="" data-i18n="project_modules.option_select_project"><?= htmlspecialchars($t('project_modules.option_select_project', 'Выберите проект...'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="projectModuleStatus" data-i18n="project_modules.field_status"><?= htmlspecialchars($t('project_modules.field_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="projectModuleStatus">
                <option value="planned" data-i18n="project_modules.status_planned"><?= htmlspecialchars($t('project_modules.status_planned', 'Запланирован'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="in_progress" data-i18n="project_modules.status_in_progress"><?= htmlspecialchars($t('project_modules.status_in_progress', 'В работе'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="paused" data-i18n="project_modules.status_paused"><?= htmlspecialchars($t('project_modules.status_paused', 'Приостановлен'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="completed" data-i18n="project_modules.status_completed"><?= htmlspecialchars($t('project_modules.status_completed', 'Завершён'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="cancelled" data-i18n="project_modules.status_cancelled"><?= htmlspecialchars($t('project_modules.status_cancelled', 'Отменён'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="projectModuleLead" data-i18n="project_modules.field_lead"><?= htmlspecialchars($t('project_modules.field_lead', 'Ответственный'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="projectModuleLead">
                <option value="" data-i18n="project_modules.option_no_lead"><?= htmlspecialchars($t('project_modules.option_no_lead', 'Не назначен'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="projectModuleColor" data-i18n="project_modules.field_color"><?= htmlspecialchars($t('project_modules.field_color', 'Цвет'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" id="projectModuleColor" type="color" value="#0f8f72" style="height:38px;padding:3px">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="projectModuleStartAt" data-i18n="project_modules.field_start_at"><?= htmlspecialchars($t('project_modules.field_start_at', 'Дата начала'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" id="projectModuleStartAt" type="date">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="projectModuleTargetAt" data-i18n="project_modules.field_target_at"><?= htmlspecialchars($t('project_modules.field_target_at', 'Целевая дата'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" id="projectModuleTargetAt" type="date">
            </div>
            <div class="col-12">
              <label class="form-label" for="projectModuleDescription" data-i18n="project_modules.field_description"><?= htmlspecialchars($t('project_modules.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label>
              <textarea class="form-control" id="projectModuleDescription" rows="3" maxlength="65535" data-crm-visual-editor="1" data-richtext-off="1"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="submit" class="btn crm-btn-primary" id="projectModuleSaveBtn" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===== Archive Confirm Modal ===== -->
<div class="modal fade" id="projectModuleArchiveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="project_modules.archive_title"><?= htmlspecialchars($t('project_modules.archive_title', 'Архивировать модуль'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <div class="modal-body">
        <p data-i18n="project_modules.archive_confirm"><?= htmlspecialchars($t('project_modules.archive_confirm', 'Архивировать этот модуль? Задачи модуля не будут удалены.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn crm-btn-primary" id="projectModuleArchiveConfirmBtn" data-i18n="project_modules.archive_btn"><?= htmlspecialchars($t('project_modules.archive_btn', 'Архивировать'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Project Archive Confirm Modal ===== -->
<div class="modal fade" id="projectArchiveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="project_detail.archive_modal_title"><?= htmlspecialchars($t('project_detail.archive_modal_title', 'Архивировать проект'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <div class="modal-body">
        <p data-i18n="project_detail.archive_confirm_text"><?= htmlspecialchars($t('project_detail.archive_confirm_text', 'Архивировать этот проект? Проект будет скрыт из списка, но все его данные сохранятся.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn crm-btn-danger" id="projectArchiveConfirmBtn" data-i18n="project_detail.archive_btn"><?= htmlspecialchars($t('project_detail.archive_btn', 'Архивировать'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<script nonce="<?= $csp_nonce ?>">
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
    if (listEl) {
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
    }

    var teamEl = document.getElementById('projectTeamKnowledgeList');
    if (teamEl && window.CRM && window.CRM.teamMaterials) {
      window.CRM.teamMaterials.render({
        container: teamEl,
        entityType: 'project',
        entityPublicId: projectId,
        api: api,
        texts: {
          searchPlaceholder: <?= json_encode($t('page.team_materials_search', 'Поиск по материалам...'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
          empty: <?= json_encode($t('project_detail.team_knowledge_empty', 'Нет материалов команды'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
          noMatch: <?= json_encode($t('page.team_materials_no_match', 'Ничего не найдено'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
          loading: <?= json_encode($t('page.loading', 'Загрузка...'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>
        }
      });
    }

    /* ---- Client portal: knowledge base for external users ---- */
    var clientKnowledgeEl = document.getElementById('projectClientKnowledgeList');
    if (clientKnowledgeEl) {
      try {
        var ckb = await api.request('api/v1/knowledge/project/' + encodeURIComponent(projectId) + '/client-pages', { method: 'GET' });
        var ckItems = ckb.data && ckb.data.items || [];
        if (!ckItems.length) {
          clientKnowledgeEl.innerHTML = '<div class="text-muted small"><?= htmlspecialchars($t('project_detail.client_knowledge_empty', 'Документов для клиента пока нет.'), ENT_QUOTES, 'UTF-8') ?></div>';
        } else {
          clientKnowledgeEl.innerHTML = '<ul class="list-unstyled mb-0">' + ckItems.map(function (p) {
            return '<li class="mb-2"><a href="index.php?route=knowledge-page&amp;id=' + encodeURIComponent(p.public_id) + '">' + escapeHtml(p.title || '') + '</a>' + (p.summary ? '<div class="text-muted small mt-1">' + escapeHtml(p.summary).substring(0, 120) + '</div>' : '') + '</li>';
          }).join('') + '</ul>';
        }
      } catch (e) {
        clientKnowledgeEl.innerHTML = '<div class="text-muted small">—</div>';
      }
    }

    /* ---- Client portal: chat (uses shared chat-widget.js) ---- */
    var chatMsgsEl = document.getElementById('projectClientChatMessages');
    var chatFormEl = document.getElementById('projectClientChatForm');
    var chatInputEl = document.getElementById('projectClientChatInput');
    var chatEmptyEl = document.getElementById('projectClientChatEmpty');
    var clientChatPublicId = null;
    var clientChatMessages = [];
    var chatW = window.CRM && window.CRM.chat;
    var clientReplyTo = null;

    function findClientMessage(id) {
      return clientChatMessages.find(function (m) { return String(m.public_id) === String(id); }) || null;
    }

    function renderClientReplyPreview() {
      var node = document.getElementById('projectClientChatReplyPreview');
      var replyId = document.getElementById('projectClientChatReplyId');
      if (!node) return;
      node.classList.toggle('d-none', !clientReplyTo);
      if (!clientReplyTo) { node.innerHTML = ''; if (replyId) replyId.value = ''; return; }
      if (replyId) replyId.value = clientReplyTo.public_id || '';
      node.innerHTML = '<div><strong><?= htmlspecialchars($t('project_detail.client_chat_reply', 'Ответ'), ENT_QUOTES, 'UTF-8') ?></strong><span>' + (clientReplyTo.text || '').substring(0, 120) + '</span></div><button type="button" aria-label="<?= htmlspecialchars($t('project_detail.client_chat_cancel_reply', 'Отменить'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>';
      node.querySelector('button').addEventListener('click', function () {
        clientReplyTo = null;
        renderClientReplyPreview();
      });
    }

    function renderClientChatMessages(msgs) {
      clientChatMessages = msgs;
      if (!chatMsgsEl || !chatW) return;
      if (!msgs.length) {
        chatMsgsEl.innerHTML = '<div class="crm-chat-empty crm-chat-message-text--small"><strong><?= htmlspecialchars($t('project_detail.client_chat_no_messages', 'Сообщений пока нет.'), ENT_QUOTES, 'UTF-8') ?></strong></div>';
        return;
      }
      chatMsgsEl.innerHTML = msgs.map(function (m) {
        return chatW.renderMessage(m, { findMessage: findClientMessage });
      }).join('');
      chatW.bindMessageActions(chatMsgsEl, {
        findMessage: findClientMessage,
        chatId: clientChatPublicId,
        copyText: function (text) { return navigator.clipboard.writeText(text); },
        onReply: function (message) {
          clientReplyTo = message;
          renderClientReplyPreview();
          if (chatInputEl) chatInputEl.focus();
        },
        onEdit: function (message) {
          /* inline edit: put text in input and focus */
          if (chatInputEl) {
            chatInputEl.value = message.text || '';
            chatInputEl.focus();
          }
        },
        showConfirmModal: function (opts) {
          return new Promise(function (resolve) {
            resolve(window.confirm(opts.title + '\n' + (opts.body || '')));
          });
        },
        request: function (route, opts) { return api.request(route, opts); },
        onAfterDelete: function () { loadClientChatMessages(api, clientChatPublicId, chatMsgsEl); },
        onCreateTask: function (messageId) {
          var msg = findClientMessage(messageId);
          if (!msg) return;
          var text = (msg.text || '').trim();
          var defaultTitle = text.substring(0, 120);
          if (text.length > 120) defaultTitle += '...';
          if (!defaultTitle) defaultTitle = '<?= htmlspecialchars($t('project_detail.client_chat_task_default', 'Задача из сообщения'), ENT_QUOTES, 'UTF-8') ?>';
          window._taskCreatePrefill = {
            title: defaultTitle,
            description: text ? '<p>' + text.replace(/\n/g, '<br>') + '</p>' : '',
            project_public_id: projectId || '',
            source_type: 'chat',
            source_id: clientChatPublicId || ''
          };
          var modalEl = document.getElementById('createTaskModal');
          if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            modalEl.classList.remove('d-none');
            modalEl.removeAttribute('data-permission-hidden');
            modalEl.removeAttribute('aria-hidden');
            modalEl.style.display = '';
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
          }
        },
        onCreateKnowledge: function () { /* not implemented in project chat */ },
        showMessageHistory: function () { /* not implemented in project chat */ },
        showImageModal: function () { /* not implemented in project chat */ }
      });
      chatMsgsEl.scrollTop = chatMsgsEl.scrollHeight;
    }

    if (chatMsgsEl) {
      try {
        var chatResp = await api.request('api/v1/chats');
        var chatItems = chatResp.data && chatResp.data.items || [];
        var matchingChats = chatItems.filter(function (c) { return c.project_public_id === projectId; });
        if (matchingChats.length) {
          clientChatPublicId = matchingChats[0].public_id;
          await loadClientChatMessages(api, clientChatPublicId, chatMsgsEl);
          if (chatFormEl) chatFormEl.style.display = '';
        } else {
          chatMsgsEl.style.display = 'none';
          if (chatEmptyEl) chatEmptyEl.style.display = '';
        }
      } catch (e) {
        chatMsgsEl.innerHTML = '<div class="text-muted small">—</div>';
      }
    }

    if (chatFormEl && chatInputEl) {
      chatFormEl.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        var text = (chatInputEl.value || '').trim();
        if (!text || !clientChatPublicId) return;
        var body = { text: text };
        if (clientReplyTo && clientReplyTo.public_id) body.reply_to_message_public_id = clientReplyTo.public_id;
        chatInputEl.disabled = true;
        try {
          await api.request('api/v1/chats/' + encodeURIComponent(clientChatPublicId) + '/messages', { method: 'POST', body: body });
          chatInputEl.value = '';
          clientReplyTo = null;
          renderClientReplyPreview();
          await loadClientChatMessages(api, clientChatPublicId, chatMsgsEl);
        } catch (e) { /* ignore */ }
        chatInputEl.disabled = false;
        chatInputEl.focus();
      });
    }

  async function loadClientChatMessages(api, chatId, container) {
    try {
      var resp = await api.request('api/v1/chats/' + encodeURIComponent(chatId) + '/messages');
      var msgs = resp.data && (resp.data.items || resp.data.messages) || [];
      renderClientChatMessages(msgs);
    } catch (e) {
      container.innerHTML = '<div class="text-muted small">—</div>';
    }
  }
  });
})();
</script>
</body>
