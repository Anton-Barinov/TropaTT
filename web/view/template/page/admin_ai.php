<?php declare(strict_types=1); ?>
<?php $title = $t('admin_ai.title', 'TropaTT — AI-помощник'); ?>
<body data-page="admin-ai" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-admin-ai-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="admin_ai.link_admin"><?= htmlspecialchars($t('admin_ai.link_admin', 'Админка'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="admin_ai.breadcrumb"><?= htmlspecialchars($t('admin_ai.breadcrumb', 'AI-помощник'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" data-i18n="admin_ai.page_title"><?= htmlspecialchars($t('admin_ai.page_title', 'AI-помощник'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_ai.subtitle"><?= htmlspecialchars($t('admin_ai.subtitle', 'Подключите AI к CRM, выберите модель и настройте доступ к данным.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>

<section class="crm-card crm-section-card crm-admin-ai-hero mb-3" id="adminAiHeroCard">
  <div class="crm-admin-ai-hero-icon" aria-hidden="true">
    <i class="fa-solid fa-circle-check"></i>
  </div>
  <div class="crm-admin-ai-hero-body">
    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
      <h2 class="h4 mb-0" id="adminAiHeroTitle" data-i18n="admin_ai.hero_title"><?= htmlspecialchars($t('admin_ai.hero_title', 'Проверяем состояние AI'), ENT_QUOTES, 'UTF-8') ?></h2>
      <span class="crm-badge archived" id="adminAiHeroStatus" data-i18n="admin_ai.hero_status"><?= htmlspecialchars($t('admin_ai.hero_status', 'Загрузка'), ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <div class="crm-admin-ai-hero-meta">
      <div><span data-i18n="admin_ai.hero_label_service"><?= htmlspecialchars($t('admin_ai.hero_label_service', 'Основной сервис'), ENT_QUOTES, 'UTF-8') ?></span><strong id="adminAiHeroProvider">—</strong></div>
      <div><span data-i18n="admin_ai.hero_label_model"><?= htmlspecialchars($t('admin_ai.hero_label_model', 'Модель'), ENT_QUOTES, 'UTF-8') ?></span><strong id="adminAiHeroModel">—</strong></div>
      <div><span data-i18n="admin_ai.hero_label_last_check"><?= htmlspecialchars($t('admin_ai.hero_label_last_check', 'Последняя проверка'), ENT_QUOTES, 'UTF-8') ?></span><strong id="adminAiHeroLastCheck">—</strong></div>
      <div><span data-i18n="admin_ai.hero_label_secret"><?= htmlspecialchars($t('admin_ai.hero_label_secret', 'Ключ доступа'), ENT_QUOTES, 'UTF-8') ?></span><strong id="adminAiHeroSecret">—</strong></div>
      <div><span data-i18n="admin_ai.hero_label_scenarios"><?= htmlspecialchars($t('admin_ai.hero_label_scenarios', 'Используется в'), ENT_QUOTES, 'UTF-8') ?></span><strong id="adminAiHeroScenarios">—</strong></div>
    </div>
  </div>
  <div class="crm-admin-ai-hero-actions">
    <button class="btn crm-btn-primary" id="adminAiHeroTestBtn" type="button">
      <span class="crm-admin-ai-btn-icon" aria-hidden="true"><i class="fa-solid fa-heart-pulse"></i></span>
      <span data-i18n="admin_ai.hero_btn_test"><?= htmlspecialchars($t('admin_ai.hero_btn_test', 'Проверить подключение'), ENT_QUOTES, 'UTF-8') ?></span>
    </button>
    <button class="btn crm-btn-secondary" id="adminAiHeroEditBtn" type="button">
      <span class="crm-admin-ai-btn-icon" aria-hidden="true"><i class="fa-solid fa-sliders"></i></span>
      <span data-i18n="admin_ai.hero_btn_edit"><?= htmlspecialchars($t('admin_ai.hero_btn_edit', 'Изменить настройки'), ENT_QUOTES, 'UTF-8') ?></span>
    </button>
    <button class="btn btn-link crm-admin-ai-advanced-link" id="adminAiAdvancedSettingsBtn" type="button" data-bs-toggle="modal" data-bs-target="#adminAiAdvancedModal">
      <span class="crm-admin-ai-btn-icon" aria-hidden="true"><i class="fa-solid fa-bars-staggered"></i></span>
      <span data-i18n="admin_ai.hero_btn_advanced"><?= htmlspecialchars($t('admin_ai.hero_btn_advanced', 'Расширенные настройки'), ENT_QUOTES, 'UTF-8') ?></span>
    </button>
  </div>
</section>

<div class="row g-3 mb-3 crm-kpi-row">
  <div class="col-sm-6 col-xl-3"><div class="crm-card crm-kpi-card crm-admin-ai-metric"><span class="crm-admin-ai-metric-icon" aria-hidden="true"><i class="fa-solid fa-link"></i></span><div><small class="text-muted" data-i18n="admin_ai.kpi_providers"><?= htmlspecialchars($t('admin_ai.kpi_providers', 'Подключения'), ENT_QUOTES, 'UTF-8') ?></small><h2 id="adminAiKpiProviders" class="h4 mb-0">0</h2></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="crm-card crm-kpi-card crm-admin-ai-metric"><span class="crm-admin-ai-metric-icon is-success" aria-hidden="true"><i class="fa-solid fa-rocket"></i></span><div><small class="text-muted" data-i18n="admin_ai.kpi_active_intents"><?= htmlspecialchars($t('admin_ai.kpi_active_intents', 'Активные сценарии'), ENT_QUOTES, 'UTF-8') ?></small><h2 id="adminAiKpiEnabledIntents" class="h4 mb-0">0</h2></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="crm-card crm-kpi-card crm-admin-ai-metric"><span class="crm-admin-ai-metric-icon is-info" aria-hidden="true"><i class="fa-solid fa-chart-column"></i></span><div><small class="text-muted" data-i18n="admin_ai.kpi_today_jobs"><?= htmlspecialchars($t('admin_ai.kpi_today_jobs', 'Выполнено сегодня'), ENT_QUOTES, 'UTF-8') ?></small><h2 id="adminAiKpiJobsToday" class="h4 mb-0">0</h2></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="crm-card crm-kpi-card crm-admin-ai-metric is-warning"><span class="crm-admin-ai-metric-icon is-warning" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span><div><small class="text-muted" data-i18n="admin_ai.kpi_needs_attention"><?= htmlspecialchars($t('admin_ai.kpi_needs_attention', 'Требует внимания'), ENT_QUOTES, 'UTF-8') ?></small><h2 id="adminAiKpiErrorsToday" class="h4 mb-0">0</h2></div></div></div>
</div>

<div class="crm-card crm-admin-ai-tabs mb-3">
  <ul class="nav nav-pills gap-2" id="adminAiTabs" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link active" id="adminAiProvidersTab" data-bs-toggle="tab" data-bs-target="#adminAiProvidersPane" type="button" role="tab" aria-controls="adminAiProvidersPane" aria-selected="true" data-i18n="admin_ai.tab_providers"><?= htmlspecialchars($t('admin_ai.tab_providers', 'Подключение'), ENT_QUOTES, 'UTF-8') ?></button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="adminAiIntentsTab" data-bs-toggle="tab" data-bs-target="#adminAiIntentsPane" type="button" role="tab" aria-controls="adminAiIntentsPane" aria-selected="false" data-i18n="admin_ai.tab_intents"><?= htmlspecialchars($t('admin_ai.tab_intents', 'Сценарии'), ENT_QUOTES, 'UTF-8') ?></button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="adminAiPromptsTab" data-bs-toggle="tab" data-bs-target="#adminAiPromptsPane" type="button" role="tab" aria-controls="adminAiPromptsPane" aria-selected="false" data-i18n="admin_ai.tab_prompts"><?= htmlspecialchars($t('admin_ai.tab_prompts', 'Инструкции AI'), ENT_QUOTES, 'UTF-8') ?></button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="adminAiOpsTab" data-bs-toggle="tab" data-bs-target="#adminAiOpsPane" type="button" role="tab" aria-controls="adminAiOpsPane" aria-selected="false" data-i18n="admin_ai.tab_ops"><?= htmlspecialchars($t('admin_ai.tab_ops', 'История работы'), ENT_QUOTES, 'UTF-8') ?></button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="adminAiPoliciesTab" data-bs-toggle="tab" data-bs-target="#adminAiPoliciesPane" type="button" role="tab" aria-controls="adminAiPoliciesPane" aria-selected="false" data-i18n="admin_ai.tab_policies"><?= htmlspecialchars($t('admin_ai.tab_policies', 'Безопасность'), ENT_QUOTES, 'UTF-8') ?></button></li>
  </ul>
</div>

<div class="tab-content crm-admin-ai-tab-content" id="adminAiTabContent">
<section class="tab-pane fade show active" id="adminAiProvidersPane" role="tabpanel" aria-labelledby="adminAiProvidersTab" tabindex="0">

<div class="row g-3 align-items-start mb-3">
  <div class="col-lg-8">
    <div class="crm-card crm-section-card crm-admin-ai-connection-card" id="adminAiProviderOverviewCard">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
          <h2 class="h5 mb-1" data-i18n="admin_ai.section_primary_connection"><?= htmlspecialchars($t('admin_ai.section_primary_connection', 'Основное подключение'), ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-sm btn-light" id="adminAiPrimaryEditBtn" type="button">
            <span class="crm-admin-ai-btn-icon" aria-hidden="true"><i class="fa-solid fa-sliders"></i></span>
            <?= htmlspecialchars($t('admin_ai.btn_configure', 'Настроить'), ENT_QUOTES, 'UTF-8') ?>
          </button>
          <button class="btn btn-sm btn-light" id="adminAiPrimaryTestBtn" type="button">
            <span class="crm-admin-ai-btn-icon" aria-hidden="true"><i class="fa-solid fa-heart-pulse"></i></span>
            <?= htmlspecialchars($t('admin_ai.btn_check', 'Проверить'), ENT_QUOTES, 'UTF-8') ?>
          </button>
          <div class="dropdown">
            <button class="btn btn-sm btn-light crm-admin-ai-icon-btn" type="button" id="adminAiPrimaryMenuBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= htmlspecialchars($t('admin_ai.aria_actions', 'Дополнительные действия'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="admin_ai.aria_actions">
              <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminAiPrimaryMenuBtn">
              <li><button class="dropdown-item" type="button" id="adminAiPrimaryModelsBtn" data-i18n="admin_ai.menu_manage_models"><?= htmlspecialchars($t('admin_ai.menu_manage_models', 'Управлять моделями'), ENT_QUOTES, 'UTF-8') ?></button></li>
              <li><button class="dropdown-item" type="button" id="adminAiPrimaryTechBtn" data-bs-toggle="modal" data-bs-target="#adminAiTechnicalModal" data-i18n="admin_ai.menu_technical_data"><?= htmlspecialchars($t('admin_ai.menu_technical_data', 'Показать технические данные'), ENT_QUOTES, 'UTF-8') ?></button></li>
              <li><hr class="dropdown-divider"></li>
              <li><button class="dropdown-item text-danger" type="button" id="adminAiPrimaryDeleteBtn" data-i18n="admin_ai.menu_delete"><?= htmlspecialchars($t('admin_ai.menu_delete', 'Удалить'), ENT_QUOTES, 'UTF-8') ?></button></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="crm-admin-ai-primary-summary">
        <div class="crm-admin-ai-service-mark" aria-hidden="true"><i class="fa-solid fa-circle-plus"></i></div>
        <div>
          <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <h3 class="h5 mb-0" id="adminAiPrimaryTitle">—</h3>
            <span class="crm-badge archived" id="adminAiPrimaryStatusBadge" data-i18n="admin_ai.status_loading"><?= htmlspecialchars($t('admin_ai.status_loading', 'Загрузка'), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="crm-chip" id="adminAiPrimarySecret"><?= htmlspecialchars($t('admin_ai.chip_secret_key', 'Ключ доступа: —'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="text-muted small" id="adminAiPrimaryHost">—</div>
          <div class="d-flex gap-2 flex-wrap mt-2">
            <span class="crm-chip d-none" id="adminAiPrimaryDefaultBadge" data-i18n="admin_ai.chip_default"><?= htmlspecialchars($t('admin_ai.chip_default', 'Используется по умолчанию'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>
      </div>
      <div class="crm-admin-ai-connection-grid mt-3">
        <div><span data-i18n="admin_ai.label_answer_model"><?= htmlspecialchars($t('admin_ai.label_answer_model', 'Модель для ответов'), ENT_QUOTES, 'UTF-8') ?></span><strong id="adminAiPrimaryAnswerModel">—</strong></div>
        <div><span data-i18n="admin_ai.label_search_model"><?= htmlspecialchars($t('admin_ai.label_search_model', 'Модель для поиска по данным'), ENT_QUOTES, 'UTF-8') ?></span><strong id="adminAiPrimarySearchModel">—</strong></div>
      </div>
      <div class="d-flex gap-2 flex-wrap mt-3">
        <span class="crm-chip" data-i18n="admin_ai.chip_answers"><?= htmlspecialchars($t('admin_ai.chip_answers', 'Ответы'), ENT_QUOTES, 'UTF-8') ?></span>
        <span class="crm-chip" data-i18n="admin_ai.chip_search"><?= htmlspecialchars($t('admin_ai.chip_search', 'Поиск по базе'), ENT_QUOTES, 'UTF-8') ?></span>
        <span class="crm-chip" data-i18n="admin_ai.chip_speech"><?= htmlspecialchars($t('admin_ai.chip_speech', 'Распознавание речи'), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </div>

    <div class="crm-card crm-section-card crm-admin-ai-other-card mt-3">
      <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
        <div>
          <h2 class="h5 mb-1" data-i18n="admin_ai.section_other_connections"><?= htmlspecialchars($t('admin_ai.section_other_connections', 'Другие подключения'), ENT_QUOTES, 'UTF-8') ?></h2>
          <p class="text-muted small mb-0" data-i18n="admin_ai.other_connections_desc"><?= htmlspecialchars($t('admin_ai.other_connections_desc', 'Дополнительные сервисы можно проверить или настроить отдельно.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <button class="btn crm-btn-secondary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#adminAiCreateModal" data-i18n="admin_ai.btn_add_connection"><?= htmlspecialchars($t('admin_ai.btn_add_connection', 'Добавить подключение'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div id="adminAiProvidersBody" class="crm-admin-ai-provider-list"><div class="text-muted" data-i18n="admin_ai.loading_connections"><?= htmlspecialchars($t('admin_ai.loading_connections', 'Загрузка подключений...'), ENT_QUOTES, 'UTF-8') ?></div></div>
    </div>

    <div class="crm-card crm-section-card crm-admin-ai-help-card mt-3">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <span class="crm-admin-ai-help-icon" aria-hidden="true"><i class="fa-solid fa-circle-question"></i></span>
        <div class="me-auto">
          <h2 class="h6 mb-1" data-i18n="admin_ai.help_title"><?= htmlspecialchars($t('admin_ai.help_title', 'Нужна помощь с настройкой?'), ENT_QUOTES, 'UTF-8') ?></h2>
          <p class="text-muted small mb-0" data-i18n="admin_ai.help_desc"><?= htmlspecialchars($t('admin_ai.help_desc', 'Мы подготовили понятные инструкции и ответы на частые вопросы.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <a class="btn crm-btn-secondary btn-sm" href="index.php?route=docs" data-i18n="admin_ai.help_btn"><?= htmlspecialchars($t('admin_ai.help_btn', 'Открыть справку'), ENT_QUOTES, 'UTF-8') ?></a>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="crm-card crm-section-card crm-admin-ai-attention-card">
      <h2 class="h5 mb-3" data-i18n="admin_ai.attention_title"><?= htmlspecialchars($t('admin_ai.attention_title', 'Что требует внимания'), ENT_QUOTES, 'UTF-8') ?></h2>
      <div id="adminAiAttentionState">
        <div class="crm-admin-ai-attention-panel">
          <strong data-i18n="admin_ai.attention_loading_title"><?= htmlspecialchars($t('admin_ai.attention_loading_title', 'Загружаем проверки'), ENT_QUOTES, 'UTF-8') ?></strong>
          <p class="text-muted mb-0" data-i18n="admin_ai.attention_loading_desc"><?= htmlspecialchars($t('admin_ai.attention_loading_desc', 'Состояние подключений появится через несколько секунд.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap mt-3">
        <button class="btn crm-btn-primary btn-sm" id="adminAiAttentionCheckBtn" type="button" data-i18n="admin_ai.btn_check_now"><?= htmlspecialchars($t('admin_ai.btn_check_now', 'Проверить сейчас'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn crm-btn-secondary btn-sm" id="adminAiAttentionDetailsBtn" type="button" data-bs-toggle="modal" data-bs-target="#adminAiAdvancedModal" data-i18n="admin_ai.btn_details"><?= htmlspecialchars($t('admin_ai.btn_details', 'Подробнее'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="adminAiAdvancedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="h6 mb-0" data-i18n="admin_ai.modal_advanced_title"><?= htmlspecialchars($t('admin_ai.modal_advanced_title', 'Расширенные настройки'), ENT_QUOTES, 'UTF-8') ?></h2>
        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <div class="modal-body">
        <div id="adminAiReviewCard" class="crm-card crm-section-card crm-admin-ai-diagnostics mb-0" data-ai-state="empty">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div>
              <h3 class="h6 mb-1" data-i18n="admin_ai.diagnostics_title"><?= htmlspecialchars($t('admin_ai.diagnostics_title', 'Диагностика AI-помощника'), ENT_QUOTES, 'UTF-8') ?></h3>
              <p class="small text-muted mb-0" data-i18n="admin_ai.diagnostics_desc"><?= htmlspecialchars($t('admin_ai.diagnostics_desc', 'Запускайте проверки журналов, обмена данными и сценариев. Чувствительные данные не показываются на основном экране.'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <span class="crm-chip" data-i18n="admin_ai.chip_only_admin"><?= htmlspecialchars($t('admin_ai.chip_only_admin', 'только админ'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="d-flex gap-2 flex-wrap mb-3">
            <button id="adminAiLogReviewBtn" class="btn btn-sm btn-light" type="button" data-i18n="admin_ai.btn_check_logs"><?= htmlspecialchars($t('admin_ai.btn_check_logs', 'Проверить журналы'), ENT_QUOTES, 'UTF-8') ?></button>
            <button id="adminAiWebhookHealthBtn" class="btn btn-sm btn-light" type="button" data-i18n="admin_ai.btn_check_webhooks"><?= htmlspecialchars($t('admin_ai.btn_check_webhooks', 'Проверить обмен данными'), ENT_QUOTES, 'UTF-8') ?></button>
            <button id="adminAiWorkflowAuditBtn" class="btn btn-sm btn-light" type="button" data-i18n="admin_ai.btn_check_workflows"><?= htmlspecialchars($t('admin_ai.btn_check_workflows', 'Проверить сценарии'), ENT_QUOTES, 'UTF-8') ?></button>
            <button id="adminAiDailyPlanBtn" class="btn btn-sm btn-light" type="button" data-requires-ai-use="1" data-i18n="admin_ai.btn_daily_plan"><?= htmlspecialchars($t('admin_ai.btn_daily_plan', 'План задач на день'), ENT_QUOTES, 'UTF-8') ?></button>
            <button id="adminAiSecurityLogBtn" class="btn btn-sm btn-light" type="button" data-requires-ai-use="1" data-i18n="admin_ai.btn_security_check"><?= htmlspecialchars($t('admin_ai.btn_security_check', 'Проверка безопасности'), ENT_QUOTES, 'UTF-8') ?></button>
            <button id="adminAiReviewPreviewBtn" class="btn btn-sm btn-light" type="button" disabled data-i18n="admin_ai.btn_preview"><?= htmlspecialchars($t('admin_ai.btn_preview', 'Предпросмотр'), ENT_QUOTES, 'UTF-8') ?></button>
            <button id="adminAiReviewDismissBtn" class="btn btn-sm btn-outline-secondary" type="button" disabled data-i18n="admin_ai.btn_dismiss"><?= htmlspecialchars($t('admin_ai.btn_dismiss', 'Отклонить результат'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
          <div class="crm-admin-ai-diagnostics-result">
            <div>
              <div id="adminAiReviewState" class="small text-muted mb-1" data-i18n="admin_ai.diagnostics_not_run"><?= htmlspecialchars($t('admin_ai.diagnostics_not_run', 'Диагностика ещё не запускалась.'), ENT_QUOTES, 'UTF-8') ?></div>
              <div id="adminAiReviewSummary" class="small fw-semibold" data-i18n="admin_ai.diagnostics_select_check"><?= htmlspecialchars($t('admin_ai.diagnostics_select_check', 'Выберите проверку выше.'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="crm-soft-panel">
              <div class="small text-muted mb-1" data-i18n="admin_ai.diagnostics_facts"><?= htmlspecialchars($t('admin_ai.diagnostics_facts', 'Факты'), ENT_QUOTES, 'UTF-8') ?></div>
              <ul id="adminAiReviewFacts" class="small mb-0 ps-3"><li class="text-muted" data-i18n="admin_ai.diagnostics_no_data"><?= htmlspecialchars($t('admin_ai.diagnostics_no_data', 'Нет данных.'), ENT_QUOTES, 'UTF-8') ?></li></ul>
            </div>
            <div class="crm-soft-panel">
              <div class="small text-muted mb-1" data-i18n="admin_ai.diagnostics_risks"><?= htmlspecialchars($t('admin_ai.diagnostics_risks', 'Риски / вопросы'), ENT_QUOTES, 'UTF-8') ?></div>
              <ul id="adminAiReviewRisks" class="small mb-0 ps-3"><li class="text-muted" data-i18n="admin_ai.diagnostics_no_data"><?= htmlspecialchars($t('admin_ai.diagnostics_no_data', 'Нет данных.'), ENT_QUOTES, 'UTF-8') ?></li></ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="adminAiTechnicalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="h6 mb-0" data-i18n="admin_ai.modal_technical_title"><?= htmlspecialchars($t('admin_ai.modal_technical_title', 'Технические данные'), ENT_QUOTES, 'UTF-8') ?></h2>
        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <div class="modal-body">
        <div id="adminAiTechnicalDetails" class="crm-soft-panel small" data-i18n="admin_ai.technical_select"><?= htmlspecialchars($t('admin_ai.technical_select', 'Выберите подключение.'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="adminAiCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="h6 mb-0" data-i18n="admin_ai.modal_create_title"><?= htmlspecialchars($t('admin_ai.modal_create_title', 'Настроить подключение'), ENT_QUOTES, 'UTF-8') ?></h2>
        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
          <div class="small text-muted" data-i18n="admin_ai.create_required_fields"><?= htmlspecialchars($t('admin_ai.create_required_fields', 'Обязательные поля: название, ключ доступа, адрес API и модель для ответов.'), ENT_QUOTES, 'UTF-8') ?></div>
          <button id="adminAiCreateAdvancedToggle" class="btn btn-sm btn-light" type="button" data-i18n="admin_ai.btn_mode_basic"><?= htmlspecialchars($t('admin_ai.btn_mode_basic', 'Режим: базовый'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
        <form id="adminAiCreateForm" class="row g-2">
          <div class="col-md-4">
            <label class="form-label" data-i18n="admin_ai.label_type"><?= htmlspecialchars($t('admin_ai.label_type', 'Тип AI-сервиса'), ENT_QUOTES, 'UTF-8') ?></label>
            <select id="adminAiCreateProviderType" class="form-select">
              <option value="openai" data-i18n="admin_ai.opt_openai"><?= htmlspecialchars($t('admin_ai.opt_openai', 'OpenAI'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="compatible" data-i18n="admin_ai.opt_compatible"><?= htmlspecialchars($t('admin_ai.opt_compatible', 'OpenAI-compatible'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="deepseek" data-i18n="admin_ai.opt_deepseek"><?= htmlspecialchars($t('admin_ai.opt_deepseek', 'DeepSeek'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="cloud" data-i18n="admin_ai.opt_cloud"><?= htmlspecialchars($t('admin_ai.opt_cloud', 'Облачный сервис'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="local" data-i18n="admin_ai.opt_local"><?= htmlspecialchars($t('admin_ai.opt_local', 'Локальный сервис'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="custom" data-i18n="admin_ai.opt_custom"><?= htmlspecialchars($t('admin_ai.opt_custom', 'Другое'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>
          </div>
          <div class="col-md-8">
            <label for="adminAiCreatePresetSelect" class="form-label" data-i18n="admin_ai.label_preset"><?= htmlspecialchars($t('admin_ai.label_preset', 'Шаблон настроек'), ENT_QUOTES, 'UTF-8') ?></label>
            <select id="adminAiCreatePresetSelect" class="form-select">
              <option value="openai" data-i18n="admin_ai.opt_openai_rec"><?= htmlspecialchars($t('admin_ai.opt_openai_rec', 'OpenAI (рекомендуется)'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="anthropic" data-i18n="admin_ai.opt_anthropic"><?= htmlspecialchars($t('admin_ai.opt_anthropic', 'Anthropic (Cloud)'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="gemini" data-i18n="admin_ai.opt_gemini"><?= htmlspecialchars($t('admin_ai.opt_gemini', 'Gemini'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="openrouter" data-i18n="admin_ai.opt_openrouter"><?= htmlspecialchars($t('admin_ai.opt_openrouter', 'OpenRouter'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="deepseek" data-i18n="admin_ai.opt_deepseek"><?= htmlspecialchars($t('admin_ai.opt_deepseek', 'DeepSeek'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="mistral" data-i18n="admin_ai.opt_mistral"><?= htmlspecialchars($t('admin_ai.opt_mistral', 'Mistral'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="zai" data-i18n="admin_ai.opt_zai"><?= htmlspecialchars($t('admin_ai.opt_zai', 'Z.AI (GLM)'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="kimi" data-i18n="admin_ai.opt_kimi"><?= htmlspecialchars($t('admin_ai.opt_kimi', 'Kimi (Moonshot)'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="ollama" data-i18n="admin_ai.opt_ollama"><?= htmlspecialchars($t('admin_ai.opt_ollama', 'Ollama (локально)'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="lmstudio" data-i18n="admin_ai.opt_lmstudio"><?= htmlspecialchars($t('admin_ai.opt_lmstudio', 'LM Studio (локально)'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="generic" data-i18n="admin_ai.opt_generic"><?= htmlspecialchars($t('admin_ai.opt_generic', 'OpenAI-compatible (свой сервис)'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>
          </div>
          <div class="col-md-6"><label class="form-label" data-i18n="admin_ai.label_code"><?= htmlspecialchars($t('admin_ai.label_code', 'Внутренний код'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="provider_code" maxlength="64" placeholder="<?= htmlspecialchars($t('admin_ai.placeholder_code', 'openai'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_ai.placeholder_code" required></div>
          <div class="col-md-6"><label class="form-label" data-i18n="admin_ai.label_title"><?= htmlspecialchars($t('admin_ai.label_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" maxlength="255" placeholder="<?= htmlspecialchars($t('admin_ai.placeholder_title', 'OpenAI'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_ai.placeholder_title" required></div>
          <div class="col-md-8"><label class="form-label" data-i18n="admin_ai.label_secret"><?= htmlspecialchars($t('admin_ai.label_secret', 'Ключ доступа'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="create_secret" type="password" autocomplete="new-password" placeholder="<?= htmlspecialchars($t('admin_ai.placeholder_secret', 'sk-...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_ai.placeholder_secret" required><div class="form-text" data-i18n="admin_ai.help_secret"><?= htmlspecialchars($t('admin_ai.help_secret', 'Ключ хранится только на сервере в зашифрованном виде.'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="col-md-4"><label class="form-label" data-i18n="admin_ai.label_default_model"><?= htmlspecialchars($t('admin_ai.label_default_model', 'Модель для ответов'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="default_model" maxlength="255" placeholder="<?= htmlspecialchars($t('admin_ai.placeholder_default_model', 'gpt-4.1-mini'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_ai.placeholder_default_model" required></div>
          <div class="col-12"><label class="form-label" data-i18n="admin_ai.label_base_url"><?= htmlspecialchars($t('admin_ai.label_base_url', 'Адрес API'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="base_url" maxlength="2048" placeholder="<?= htmlspecialchars($t('admin_ai.placeholder_base_url', 'https://api.openai.com'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_ai.placeholder_base_url" required><div class="form-text" data-i18n="admin_ai.help_base_url"><?= htmlspecialchars($t('admin_ai.help_base_url', 'Без пробелов и без завершающего `/v1/chat/completions`.'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="col-md-6"><label class="form-label" data-i18n="admin_ai.label_api_path"><?= htmlspecialchars($t('admin_ai.label_api_path', 'Путь API'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="api_path" maxlength="255" value="/v1/chat/completions"><div class="form-text" data-i18n="admin_ai.help_api_path"><?= htmlspecialchars($t('admin_ai.help_api_path', 'Путь должен начинаться с `/`.'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="col-md-6"><label class="form-label" data-i18n="admin_ai.label_embedding_model"><?= htmlspecialchars($t('admin_ai.label_embedding_model', 'Модель для поиска по данным'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="embedding_model" maxlength="255" placeholder="<?= htmlspecialchars($t('admin_ai.placeholder_embedding_model', 'text-embedding-3-small'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_ai.placeholder_embedding_model"></div>
          <div class="col-12"><div class="crm-soft-panel small"><div data-i18n="admin_ai.chat_endpoint_label"><?= htmlspecialchars($t('admin_ai.chat_endpoint_label', 'Итоговый адрес для ответов:'), ENT_QUOTES, 'UTF-8') ?> <code id="adminAiCreateFinalChatEndpoint">—</code></div><div data-i18n="admin_ai.embeddings_endpoint_label"><?= htmlspecialchars($t('admin_ai.embeddings_endpoint_label', 'Итоговый адрес для поиска:'), ENT_QUOTES, 'UTF-8') ?> <code id="adminAiCreateFinalEmbeddingsEndpoint">—</code></div></div></div>
          <div class="col-md-6 admin-ai-advanced-create d-none"><label class="form-label" data-i18n="admin_ai.label_embeddings_endpoint"><?= htmlspecialchars($t('admin_ai.label_embeddings_endpoint', 'Адрес поиска по данным'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="embeddings_endpoint" maxlength="255" placeholder="/v1/embeddings"><div class="form-text" data-i18n="admin_ai.help_embeddings_path"><?= htmlspecialchars($t('admin_ai.help_embeddings_path', 'Должен начинаться с `/`.'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="col-md-6 admin-ai-advanced-create d-none"><label class="form-label" data-i18n="admin_ai.label_auth_header"><?= htmlspecialchars($t('admin_ai.label_auth_header', 'Заголовок авторизации'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="auth_header" maxlength="128" placeholder="Authorization"></div>
          <div class="col-md-6 admin-ai-advanced-create d-none"><label class="form-label" data-i18n="admin_ai.label_auth_scheme"><?= htmlspecialchars($t('admin_ai.label_auth_scheme', 'Схема авторизации'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="auth_scheme" maxlength="64" placeholder="Bearer"></div>
          <div class="col-md-6 admin-ai-advanced-create d-none"><label class="form-label" data-i18n="admin_ai.label_tls"><?= htmlspecialchars($t('admin_ai.label_tls', 'Проверка TLS'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="tls_verify"><option value="1" selected data-i18n="admin_ai.opt_tls_enabled"><?= htmlspecialchars($t('admin_ai.opt_tls_enabled', 'Включена'), ENT_QUOTES, 'UTF-8') ?></option><option value="0" data-i18n="admin_ai.opt_tls_disabled"><?= htmlspecialchars($t('admin_ai.opt_tls_disabled', 'Выключена только для локальной разработки'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
          <div class="col-md-6 admin-ai-advanced-create d-none"><label class="form-label" data-i18n="admin_ai.label_fast_model"><?= htmlspecialchars($t('admin_ai.label_fast_model', 'Быстрая модель'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="fast_model" maxlength="255" placeholder="<?= htmlspecialchars($t('admin_ai.placeholder_fast_model', 'gpt-4.1-mini'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_ai.placeholder_fast_model"></div>
          <div class="col-md-4 admin-ai-advanced-create d-none"><label class="form-label" data-i18n="admin_ai.label_reasoning_model"><?= htmlspecialchars($t('admin_ai.label_reasoning_model', 'Модель для сложных задач'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="reasoning_model" maxlength="255" placeholder="<?= htmlspecialchars($t('admin_ai.placeholder_reasoning_model', 'gpt-5.4-mini'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_ai.placeholder_reasoning_model"></div>
          <div class="col-md-4 admin-ai-advanced-create d-none"><label class="form-label" data-i18n="admin_ai.label_fallback_model"><?= htmlspecialchars($t('admin_ai.label_fallback_model', 'Резервная модель'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="fallback_model" maxlength="255" placeholder="<?= htmlspecialchars($t('admin_ai.placeholder_fallback_model', 'gpt-4.1'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_ai.placeholder_fallback_model"></div>
          <div class="col-md-4 admin-ai-advanced-create d-none"><label class="form-label" data-i18n="admin_ai.label_timeout"><?= htmlspecialchars($t('admin_ai.label_timeout', 'Таймаут, мс'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="number" min="1000" step="1000" name="timeout_ms" value="30000"></div>
          <div class="col-md-3 admin-ai-advanced-create d-none"><label class="form-label" data-i18n="admin_ai.label_max_tokens"><?= htmlspecialchars($t('admin_ai.label_max_tokens', 'Лимит токенов'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="number" min="1" step="1" name="max_tokens" value="2000"></div>
          <div class="col-md-3 admin-ai-advanced-create d-none"><label class="form-label" data-i18n="admin_ai.label_temperature"><?= htmlspecialchars($t('admin_ai.label_temperature', 'Температура'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="text" inputmode="decimal" name="temperature" value="0.2" placeholder="0.2"></div>
          <div class="col-md-3 admin-ai-advanced-create d-none"><label class="form-label" data-i18n="admin_ai.label_retry_count"><?= htmlspecialchars($t('admin_ai.label_retry_count', 'Повторы'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="number" min="0" step="1" name="retry_count" value="1"></div>
          <div class="col-md-3 admin-ai-advanced-create d-none"><label class="form-label" data-i18n="admin_ai.label_rate_limit"><?= htmlspecialchars($t('admin_ai.label_rate_limit', 'Лимит запросов в минуту'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="number" min="0" step="1" name="rate_limit_per_min" value="0"><div class="form-text" data-i18n="admin_ai.help_zero_unlimited"><?= htmlspecialchars($t('admin_ai.help_zero_unlimited', '0 = без лимита'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="col-md-3 admin-ai-advanced-create d-none"><label class="form-label" data-i18n="admin_ai.label_token_budget"><?= htmlspecialchars($t('admin_ai.label_token_budget', 'Лимит токенов в день'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="number" min="0" step="1" name="token_budget_daily" value="0"><div class="form-text" data-i18n="admin_ai.help_zero_unlimited"><?= htmlspecialchars($t('admin_ai.help_zero_unlimited', '0 = без лимита'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="col-md-3 admin-ai-advanced-create d-none"><label class="form-label" data-i18n="admin_ai.label_cost_budget"><?= htmlspecialchars($t('admin_ai.label_cost_budget', 'Бюджет в день'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="number" min="0" step="0.01" name="cost_budget_daily" value="0"><div class="form-text" data-i18n="admin_ai.help_zero_unlimited"><?= htmlspecialchars($t('admin_ai.help_zero_unlimited', '0 = без лимита'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="col-md-6"><label class="form-label" data-i18n="admin_ai.label_active"><?= htmlspecialchars($t('admin_ai.label_active', 'Активен'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="is_active"><option value="1" data-i18n="page.yes"><?= htmlspecialchars($t('page.yes', 'Да'), ENT_QUOTES, 'UTF-8') ?></option><option value="0" data-i18n="page.no"><?= htmlspecialchars($t('page.no', 'Нет'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
          <div class="col-md-6"><label class="form-label" data-i18n="admin_ai.label_default"><?= htmlspecialchars($t('admin_ai.label_default', 'По умолчанию'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="is_default"><option value="0" data-i18n="page.no"><?= htmlspecialchars($t('page.no', 'Нет'), ENT_QUOTES, 'UTF-8') ?></option><option value="1" data-i18n="page.yes"><?= htmlspecialchars($t('page.yes', 'Да'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
          <div class="col-12 admin-ai-advanced-create d-none">
            <label class="form-label" data-i18n="admin_ai.label_extra_headers"><?= htmlspecialchars($t('admin_ai.label_extra_headers', 'Дополнительные заголовки'), ENT_QUOTES, 'UTF-8') ?></label>
            <div class="table-responsive">
              <table class="table table-sm mb-2">
                <thead><tr><th data-i18n="admin_ai.th_header"><?= htmlspecialchars($t('admin_ai.th_header', 'Заголовок'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_value"><?= htmlspecialchars($t('admin_ai.th_value', 'Значение'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:80px"></th></tr></thead>
                <tbody data-ai-headers-body="create"></tbody>
              </table>
            </div>
            <button class="btn btn-sm btn-light" type="button" data-ai-headers-add="create" data-i18n="admin_ai.btn_add_header"><?= htmlspecialchars($t('admin_ai.btn_add_header', 'Добавить заголовок'), ENT_QUOTES, 'UTF-8') ?></button>
            <div class="small text-danger mt-1" data-i18n="admin_ai.alert_extra_headers_key"><?= htmlspecialchars($t('admin_ai.alert_extra_headers_key', 'Не добавляйте ключи доступа в дополнительные заголовки. Используйте поле «Ключ доступа».'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="small text-muted" data-i18n="admin_ai.alert_extra_headers_forbidden"><?= htmlspecialchars($t('admin_ai.alert_extra_headers_forbidden', 'Запрещены: Authorization, X-API-Key, Cookie, Set-Cookie.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="col-12 admin-ai-advanced-create d-none">
            <label class="form-label" data-i18n="admin_ai.label_capabilities"><?= htmlspecialchars($t('admin_ai.label_capabilities', 'Возможности'), ENT_QUOTES, 'UTF-8') ?></label>
            <div class="d-flex gap-3 flex-wrap">
              <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="cap_json_mode">json_mode</label>
              <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="cap_tools">tools</label>
              <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="cap_streaming">streaming</label>
              <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="cap_vision">vision</label>
              <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="cap_files">files</label>
            </div>
            <div class="form-text" data-i18n="admin_ai.help_capabilities"><?= htmlspecialchars($t('admin_ai.help_capabilities', 'Отметки должны соответствовать реальной модели, иначе проверка покажет предупреждение.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn crm-btn-primary" type="submit" form="adminAiCreateForm" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="adminAiEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="h6 mb-0" data-i18n="admin_ai.modal_edit_title"><?= htmlspecialchars($t('admin_ai.modal_edit_title', 'Настроить подключение'), ENT_QUOTES, 'UTF-8') ?></h2>
        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
          <div class="small text-muted" data-i18n="admin_ai.create_required_fields"><?= htmlspecialchars($t('admin_ai.create_required_fields', 'Обязательные поля: название, ключ доступа, адрес API и модель для ответов.'), ENT_QUOTES, 'UTF-8') ?></div>
          <button id="adminAiEditAdvancedToggle" class="btn btn-sm crm-btn-secondary" type="button" data-i18n="admin_ai.btn_mode_basic"><?= htmlspecialchars($t('admin_ai.btn_mode_basic', 'Режим: базовый'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
          <label for="adminAiEditProviderType" class="small text-muted mb-0" data-i18n="admin_ai.label_type_short"><?= htmlspecialchars($t('admin_ai.label_type_short', 'Тип:'), ENT_QUOTES, 'UTF-8') ?></label>
          <select id="adminAiEditProviderType" class="form-select form-select-sm crm-field-min-w-210">
            <option value="openai" data-i18n="admin_ai.opt_openai"><?= htmlspecialchars($t('admin_ai.opt_openai', 'OpenAI'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="compatible" data-i18n="admin_ai.opt_compatible"><?= htmlspecialchars($t('admin_ai.opt_compatible', 'OpenAI-compatible'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="deepseek" data-i18n="admin_ai.opt_deepseek"><?= htmlspecialchars($t('admin_ai.opt_deepseek', 'DeepSeek'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="cloud" data-i18n="admin_ai.opt_cloud"><?= htmlspecialchars($t('admin_ai.opt_cloud', 'Облачный сервис'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="local" data-i18n="admin_ai.opt_local"><?= htmlspecialchars($t('admin_ai.opt_local', 'Локальный сервис'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="custom" data-i18n="admin_ai.opt_custom"><?= htmlspecialchars($t('admin_ai.opt_custom', 'Другое'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
          <label for="adminAiEditPresetSelect" class="small text-muted mb-0" data-i18n="admin_ai.label_preset_short"><?= htmlspecialchars($t('admin_ai.label_preset_short', 'Шаблон:'), ENT_QUOTES, 'UTF-8') ?></label>
          <select id="adminAiEditPresetSelect" class="form-select form-select-sm crm-field-min-w-240">
            <option value="custom" data-i18n="admin_ai.opt_as_is"><?= htmlspecialchars($t('admin_ai.opt_as_is', 'Как есть'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="openai" data-i18n="admin_ai.opt_openai_rec"><?= htmlspecialchars($t('admin_ai.opt_openai_rec', 'OpenAI (рекомендуется)'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="anthropic" data-i18n="admin_ai.opt_anthropic"><?= htmlspecialchars($t('admin_ai.opt_anthropic', 'Anthropic (Cloud)'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="gemini" data-i18n="admin_ai.opt_gemini"><?= htmlspecialchars($t('admin_ai.opt_gemini', 'Gemini'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="openrouter" data-i18n="admin_ai.opt_openrouter"><?= htmlspecialchars($t('admin_ai.opt_openrouter', 'OpenRouter'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="deepseek" data-i18n="admin_ai.opt_deepseek"><?= htmlspecialchars($t('admin_ai.opt_deepseek', 'DeepSeek'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="mistral" data-i18n="admin_ai.opt_mistral"><?= htmlspecialchars($t('admin_ai.opt_mistral', 'Mistral'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="zai" data-i18n="admin_ai.opt_zai"><?= htmlspecialchars($t('admin_ai.opt_zai', 'Z.AI (GLM)'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="kimi" data-i18n="admin_ai.opt_kimi"><?= htmlspecialchars($t('admin_ai.opt_kimi', 'Kimi (Moonshot)'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="ollama" data-i18n="admin_ai.opt_ollama"><?= htmlspecialchars($t('admin_ai.opt_ollama', 'Ollama (локально)'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="lmstudio" data-i18n="admin_ai.opt_lmstudio"><?= htmlspecialchars($t('admin_ai.opt_lmstudio', 'LM Studio (локально)'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="generic" data-i18n="admin_ai.opt_generic"><?= htmlspecialchars($t('admin_ai.opt_generic', 'OpenAI-compatible (свой сервис)'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
        </div>
        <form id="adminAiEditForm" class="row g-2">
          <input type="hidden" name="public_id">
          <div class="col-md-6"><label class="form-label" data-i18n="admin_ai.label_code"><?= htmlspecialchars($t('admin_ai.label_code', 'Внутренний код'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="provider_code" maxlength="64"></div>
          <div class="col-md-6"><label class="form-label" data-i18n="admin_ai.label_title"><?= htmlspecialchars($t('admin_ai.label_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" maxlength="255"></div>
          <div class="col-12"><label class="form-label" data-i18n="admin_ai.label_base_url"><?= htmlspecialchars($t('admin_ai.label_base_url', 'Адрес API'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="base_url" maxlength="2048"><div class="form-text" data-i18n="admin_ai.help_base_url"><?= htmlspecialchars($t('admin_ai.help_base_url', 'Без пробелов и без завершающего `/v1/chat/completions`.'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="col-md-6"><label class="form-label" data-i18n="admin_ai.label_api_path"><?= htmlspecialchars($t('admin_ai.label_api_path', 'Путь API'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="api_path" maxlength="255"><div class="form-text" data-i18n="admin_ai.help_api_path"><?= htmlspecialchars($t('admin_ai.help_api_path', 'Путь должен начинаться с `/`.'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="col-md-6"><label class="form-label" data-i18n="admin_ai.label_default_model"><?= htmlspecialchars($t('admin_ai.label_default_model', 'Модель для ответов'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="default_model" maxlength="255"></div>
          <div class="col-md-6"><label class="form-label" data-i18n="admin_ai.label_embedding_model"><?= htmlspecialchars($t('admin_ai.label_embedding_model', 'Модель для поиска по данным'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="embedding_model" maxlength="255"></div>
          <div class="col-md-6"><label class="form-label" data-i18n="admin_ai.label_embeddings_endpoint"><?= htmlspecialchars($t('admin_ai.label_embeddings_endpoint', 'Адрес поиска по данным'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="embeddings_endpoint" maxlength="255" placeholder="/v1/embeddings"></div>
          <div class="col-12"><div class="crm-soft-panel small"><div data-i18n="admin_ai.chat_endpoint_label"><?= htmlspecialchars($t('admin_ai.chat_endpoint_label', 'Итоговый адрес для ответов:'), ENT_QUOTES, 'UTF-8') ?> <code id="adminAiEditFinalChatEndpoint">—</code></div><div data-i18n="admin_ai.embeddings_endpoint_label"><?= htmlspecialchars($t('admin_ai.embeddings_endpoint_label', 'Итоговый адрес для поиска:'), ENT_QUOTES, 'UTF-8') ?> <code id="adminAiEditFinalEmbeddingsEndpoint">—</code></div></div></div>
          <div class="col-md-4 admin-ai-advanced-edit d-none"><label class="form-label" data-i18n="admin_ai.label_timeout"><?= htmlspecialchars($t('admin_ai.label_timeout', 'Таймаут, мс'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="number" min="1000" step="1000" name="timeout_ms"></div>
          <div class="col-md-4 admin-ai-advanced-edit d-none"><label class="form-label" data-i18n="admin_ai.label_max_tokens"><?= htmlspecialchars($t('admin_ai.label_max_tokens', 'Лимит токенов'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="number" min="1" step="1" name="max_tokens"></div>
          <div class="col-md-4 admin-ai-advanced-edit d-none"><label class="form-label" data-i18n="admin_ai.label_temperature"><?= htmlspecialchars($t('admin_ai.label_temperature', 'Температура'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="text" inputmode="decimal" name="temperature" placeholder="0.2"></div>
          <div class="col-md-6 admin-ai-advanced-edit d-none"><label class="form-label" data-i18n="admin_ai.label_auth_header"><?= htmlspecialchars($t('admin_ai.label_auth_header', 'Заголовок авторизации'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="auth_header" maxlength="128" placeholder="Authorization"></div>
          <div class="col-md-6 admin-ai-advanced-edit d-none"><label class="form-label" data-i18n="admin_ai.label_auth_scheme"><?= htmlspecialchars($t('admin_ai.label_auth_scheme', 'Схема авторизации'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="auth_scheme" maxlength="64" placeholder="Bearer"></div>
          <div class="col-md-6 admin-ai-advanced-edit d-none"><label class="form-label" data-i18n="admin_ai.label_tls"><?= htmlspecialchars($t('admin_ai.label_tls', 'Проверка TLS'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="tls_verify"><option value="1" data-i18n="admin_ai.opt_tls_enabled"><?= htmlspecialchars($t('admin_ai.opt_tls_enabled', 'Включена'), ENT_QUOTES, 'UTF-8') ?></option><option value="0" data-i18n="admin_ai.opt_tls_disabled"><?= htmlspecialchars($t('admin_ai.opt_tls_disabled', 'Выключена только для локальной разработки'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
          <div class="col-md-6 admin-ai-advanced-edit d-none"><label class="form-label" data-i18n="admin_ai.label_fast_model"><?= htmlspecialchars($t('admin_ai.label_fast_model', 'Быстрая модель'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="fast_model" maxlength="255"></div>
          <div class="col-md-4 admin-ai-advanced-edit d-none"><label class="form-label" data-i18n="admin_ai.label_reasoning_model"><?= htmlspecialchars($t('admin_ai.label_reasoning_model', 'Модель для сложных задач'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="reasoning_model" maxlength="255"></div>
          <div class="col-md-4 admin-ai-advanced-edit d-none"><label class="form-label" data-i18n="admin_ai.label_fallback_model"><?= htmlspecialchars($t('admin_ai.label_fallback_model', 'Резервная модель'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="fallback_model" maxlength="255"></div>
          <div class="col-md-4 admin-ai-advanced-edit d-none"><label class="form-label" data-i18n="admin_ai.label_retry_count"><?= htmlspecialchars($t('admin_ai.label_retry_count', 'Повторы'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="number" min="0" step="1" name="retry_count"></div>
          <div class="col-md-3 admin-ai-advanced-edit d-none"><label class="form-label" data-i18n="admin_ai.label_rate_limit"><?= htmlspecialchars($t('admin_ai.label_rate_limit', 'Лимит запросов в минуту'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="number" min="0" step="1" name="rate_limit_per_min"><div class="form-text" data-i18n="admin_ai.help_zero_unlimited"><?= htmlspecialchars($t('admin_ai.help_zero_unlimited', '0 = без лимита'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="col-md-3 admin-ai-advanced-edit d-none"><label class="form-label" data-i18n="admin_ai.label_token_budget"><?= htmlspecialchars($t('admin_ai.label_token_budget', 'Лимит токенов в день'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="number" min="0" step="1" name="token_budget_daily"><div class="form-text" data-i18n="admin_ai.help_zero_unlimited"><?= htmlspecialchars($t('admin_ai.help_zero_unlimited', '0 = без лимита'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="col-md-3 admin-ai-advanced-edit d-none"><label class="form-label" data-i18n="admin_ai.label_cost_budget"><?= htmlspecialchars($t('admin_ai.label_cost_budget', 'Бюджет в день'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="number" min="0" step="0.01" name="cost_budget_daily"><div class="form-text" data-i18n="admin_ai.help_zero_unlimited"><?= htmlspecialchars($t('admin_ai.help_zero_unlimited', '0 = без лимита'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="col-md-3"><label class="form-label" data-i18n="admin_ai.label_active"><?= htmlspecialchars($t('admin_ai.label_active', 'Активен'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="is_active"><option value="1" data-i18n="page.yes"><?= htmlspecialchars($t('page.yes', 'Да'), ENT_QUOTES, 'UTF-8') ?></option><option value="0" data-i18n="page.no"><?= htmlspecialchars($t('page.no', 'Нет'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
          <div class="col-md-3"><label class="form-label" data-i18n="admin_ai.label_default"><?= htmlspecialchars($t('admin_ai.label_default', 'По умолчанию'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="is_default"><option value="0" data-i18n="page.no"><?= htmlspecialchars($t('page.no', 'Нет'), ENT_QUOTES, 'UTF-8') ?></option><option value="1" data-i18n="page.yes"><?= htmlspecialchars($t('page.yes', 'Да'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
          <div class="col-12 admin-ai-advanced-edit d-none">
            <label class="form-label" data-i18n="admin_ai.label_extra_headers"><?= htmlspecialchars($t('admin_ai.label_extra_headers', 'Дополнительные заголовки'), ENT_QUOTES, 'UTF-8') ?></label>
            <div class="table-responsive">
              <table class="table table-sm mb-2">
                <thead><tr><th data-i18n="admin_ai.th_header"><?= htmlspecialchars($t('admin_ai.th_header', 'Заголовок'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_value"><?= htmlspecialchars($t('admin_ai.th_value', 'Значение'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:80px"></th></tr></thead>
                <tbody data-ai-headers-body="edit"></tbody>
              </table>
            </div>
            <button class="btn btn-sm btn-light" type="button" data-ai-headers-add="edit" data-i18n="admin_ai.btn_add_header"><?= htmlspecialchars($t('admin_ai.btn_add_header', 'Добавить заголовок'), ENT_QUOTES, 'UTF-8') ?></button>
            <div class="small text-danger mt-1" data-i18n="admin_ai.alert_extra_headers_key"><?= htmlspecialchars($t('admin_ai.alert_extra_headers_key', 'Не добавляйте ключи доступа в дополнительные заголовки. Используйте поле «Ключ доступа».'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="small text-muted" data-i18n="admin_ai.alert_extra_headers_forbidden"><?= htmlspecialchars($t('admin_ai.alert_extra_headers_forbidden', 'Запрещены: Authorization, X-API-Key, Cookie, Set-Cookie.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="col-12 admin-ai-advanced-edit d-none">
            <label class="form-label" data-i18n="admin_ai.label_capabilities"><?= htmlspecialchars($t('admin_ai.label_capabilities', 'Возможности'), ENT_QUOTES, 'UTF-8') ?></label>
            <div class="d-flex gap-3 flex-wrap">
              <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="cap_json_mode">json_mode</label>
              <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="cap_tools">tools</label>
              <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="cap_streaming">streaming</label>
              <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="cap_vision">vision</label>
              <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="cap_files">files</label>
            </div>
          </div>
        </form>
        <hr>
        <h3 class="h6" data-i18n="admin_ai.secret_section_title"><?= htmlspecialchars($t('admin_ai.secret_section_title', 'Ключ доступа и проверка подключения'), ENT_QUOTES, 'UTF-8') ?></h3>
        <form id="adminAiSecretForm" class="row g-2">
          <div class="col-md-8"><label class="form-label" data-i18n="admin_ai.label_secret_update"><?= htmlspecialchars($t('admin_ai.label_secret_update', 'Ключ доступа'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="secret" type="password" autocomplete="new-password" placeholder="<?= htmlspecialchars($t('admin_ai.placeholder_secret_update', 'sk-... (вводится только для обновления)'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_ai.placeholder_secret_update"><div class="form-text" data-i18n="admin_ai.help_secret_update"><?= htmlspecialchars($t('admin_ai.help_secret_update', 'Ключ не логируется и не хранится в web.'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="col-md-4 d-flex align-items-end"><button class="btn crm-btn-secondary w-100" type="submit" data-i18n="admin_ai.btn_update_key"><?= htmlspecialchars($t('admin_ai.btn_update_key', 'Обновить ключ'), ENT_QUOTES, 'UTF-8') ?></button></div>
        </form>
        <div class="d-flex justify-content-between align-items-center mt-2">
          <div id="adminAiSecretState" class="small text-muted" data-i18n="admin_ai.secret_not_set"><?= htmlspecialchars($t('admin_ai.secret_not_set', 'Ключ доступа не добавлен.'), ENT_QUOTES, 'UTF-8') ?></div>
          <button id="adminAiSecretDeleteBtn" class="btn btn-sm crm-btn-danger-soft" type="button" data-i18n="admin_ai.btn_delete_key"><?= htmlspecialchars($t('admin_ai.btn_delete_key', 'Удалить ключ'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
        <div class="d-flex gap-2 mt-3 flex-wrap">
          <button id="adminAiTestProviderBtn" class="btn btn-sm btn-light" type="button" data-i18n="admin_ai.btn_test_connection"><?= htmlspecialchars($t('admin_ai.btn_test_connection', 'Проверить подключение'), ENT_QUOTES, 'UTF-8') ?></button>
          <button id="adminAiSyncModelsBtn" class="btn btn-sm btn-light" type="button" data-i18n="admin_ai.btn_sync_models"><?= htmlspecialchars($t('admin_ai.btn_sync_models', 'Синхронизировать модели'), ENT_QUOTES, 'UTF-8') ?></button>
          <button id="adminAiDeleteProviderBtn" class="btn btn-sm crm-btn-danger-soft" type="button" data-i18n="admin_ai.btn_delete_connection"><?= htmlspecialchars($t('admin_ai.btn_delete_connection', 'Удалить подключение'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
        <div id="adminAiTestState" class="small text-muted mt-2" data-i18n="admin_ai.test_not_run"><?= htmlspecialchars($t('admin_ai.test_not_run', 'Проверка подключения еще не запускалась.'), ENT_QUOTES, 'UTF-8') ?></div>
        <div id="adminAiTestChecklist" class="small mt-2"></div>
      </div>
      <div class="modal-footer">
        <button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.close"><?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn crm-btn-primary" type="submit" form="adminAiEditForm" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

</section>

<section class="tab-pane fade" id="adminAiIntentsPane" role="tabpanel" aria-labelledby="adminAiIntentsTab" tabindex="0">

<div class="row g-3 mt-1">
  <div class="col-12" data-requires-feature-flag-manage="1">
    <div class="crm-card crm-section-card">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0" data-i18n="admin_ai.section_feature_flags"><?= htmlspecialchars($t('admin_ai.section_feature_flags', 'Доступность функций'), ENT_QUOTES, 'UTF-8') ?></h2>
        <button id="adminAiFlagsReloadBtn" class="btn btn-sm btn-light" type="button" data-i18n="page.refresh"><?= htmlspecialchars($t('page.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div class="table-responsive crm-admin-ai-scroll-table">
        <table class="table crm-table mb-0">
          <thead><tr><th data-i18n="admin_ai.th_flag"><?= htmlspecialchars($t('admin_ai.th_flag', 'Флаг'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_status"><?= htmlspecialchars($t('admin_ai.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:160px" data-i18n="admin_ai.th_action"><?= htmlspecialchars($t('admin_ai.th_action', 'Действие'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
          <tbody id="adminAiFlagsBody"><tr><td colspan="3" class="text-muted" data-i18n="admin_ai.loading_flags"><?= htmlspecialchars($t('admin_ai.loading_flags', 'Загрузка флагов функций...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
        </table>
      </div>
      <div class="small text-muted mt-2" data-i18n="admin_ai.hint_feature_flags"><?= htmlspecialchars($t('admin_ai.hint_feature_flags', 'Здесь можно включить или выключить доступные функции AI.'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0" data-i18n="admin_ai.section_background_intents"><?= htmlspecialchars($t('admin_ai.section_background_intents', 'Фоновые сценарии'), ENT_QUOTES, 'UTF-8') ?></h2>
        <button id="adminAiCronReloadBtn" class="btn btn-sm btn-light" type="button" data-i18n="page.refresh"><?= htmlspecialchars($t('page.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div class="table-responsive crm-admin-ai-scroll-table">
        <table class="table crm-table mb-0">
          <thead><tr><th data-i18n="admin_ai.th_intent"><?= htmlspecialchars($t('admin_ai.th_intent', 'Сценарий'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_function"><?= htmlspecialchars($t('admin_ai.th_function', 'Функция'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_model"><?= htmlspecialchars($t('admin_ai.th_model', 'Модель'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_status"><?= htmlspecialchars($t('admin_ai.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:140px" data-i18n="admin_ai.th_action"><?= htmlspecialchars($t('admin_ai.th_action', 'Действие'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
          <tbody id="adminAiCronBody"><tr><td colspan="5" class="text-muted" data-i18n="admin_ai.loading_cron"><?= htmlspecialchars($t('admin_ai.loading_cron', 'Загрузка фоновых сценариев...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
        </table>
      </div>
      <div class="small text-muted mt-2" data-i18n="admin_ai.hint_background_intents"><?= htmlspecialchars($t('admin_ai.hint_background_intents', 'Управление автоматическими сценариями AI.'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0" data-i18n="admin_ai.section_intents"><?= htmlspecialchars($t('admin_ai.section_intents', 'Сценарии AI'), ENT_QUOTES, 'UTF-8') ?></h2>
        <button id="adminAiIntentsReloadBtn" class="btn btn-sm btn-light" type="button" data-i18n="page.refresh"><?= htmlspecialchars($t('page.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div class="table-responsive crm-admin-ai-scroll-table crm-admin-ai-intents-table">
        <table class="table crm-table mb-0">
          <thead><tr><th data-i18n="admin_ai.th_intent"><?= htmlspecialchars($t('admin_ai.th_intent', 'Сценарий'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_connection_model"><?= htmlspecialchars($t('admin_ai.th_connection_model', 'Подключение / модель'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_instruction_format"><?= htmlspecialchars($t('admin_ai.th_instruction_format', 'Инструкция / формат'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_function"><?= htmlspecialchars($t('admin_ai.th_function', 'Функция'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_rules_limits"><?= htmlspecialchars($t('admin_ai.th_rules_limits', 'Правила / лимиты'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:180px" data-i18n="admin_ai.th_action"><?= htmlspecialchars($t('admin_ai.th_action', 'Действие'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
          <tbody id="adminAiIntentsBody"><tr><td colspan="6" class="text-muted" data-i18n="admin_ai.loading_intents"><?= htmlspecialchars($t('admin_ai.loading_intents', 'Загрузка сценариев...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

</section>

<section class="tab-pane fade" id="adminAiPoliciesPane" role="tabpanel" aria-labelledby="adminAiPoliciesTab" tabindex="0">

<div class="row g-3 mt-1">
  <div class="col-xl-6">
    <div class="crm-card crm-section-card">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0" data-i18n="admin_ai.section_retention"><?= htmlspecialchars($t('admin_ai.section_retention', 'Политика хранения'), ENT_QUOTES, 'UTF-8') ?></h2>
        <button id="adminAiRetentionReloadBtn" class="btn btn-sm btn-light" type="button" data-i18n="page.refresh"><?= htmlspecialchars($t('page.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div class="table-responsive crm-admin-ai-scroll-table">
        <table class="table crm-table mb-0">
          <thead><tr><th data-i18n="admin_ai.th_policy"><?= htmlspecialchars($t('admin_ai.th_policy', 'Политика'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_days"><?= htmlspecialchars($t('admin_ai.th_days', 'Дней'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:160px" data-i18n="admin_ai.th_action"><?= htmlspecialchars($t('admin_ai.th_action', 'Действие'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
          <tbody id="adminAiRetentionBody"><tr><td colspan="3" class="text-muted" data-i18n="admin_ai.loading_retention"><?= htmlspecialchars($t('admin_ai.loading_retention', 'Загрузка политики хранения...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="crm-card crm-section-card">
      <h2 class="h6 mb-2" data-i18n="admin_ai.section_allowed_actions"><?= htmlspecialchars($t('admin_ai.section_allowed_actions', 'Разрешенные AI-действия'), ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="text-muted small mb-2" data-i18n="admin_ai.allowed_actions_desc"><?= htmlspecialchars($t('admin_ai.allowed_actions_desc', 'Список разрешенных действий доступен для чтения и контроля.'), ENT_QUOTES, 'UTF-8') ?></p>
      <div id="adminAiActionTypesList" class="d-flex flex-wrap gap-2"><span class="text-muted" data-i18n="admin_ai.loading_actions"><?= htmlspecialchars($t('admin_ai.loading_actions', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></span></div>
      <hr>
      <h3 class="h6 mb-2" data-i18n="admin_ai.section_available_models"><?= htmlspecialchars($t('admin_ai.section_available_models', 'Доступные модели'), ENT_QUOTES, 'UTF-8') ?></h3>
      <div id="adminAiModelsList" class="d-flex flex-wrap gap-2"><span class="text-muted" data-i18n="admin_ai.models_not_loaded"><?= htmlspecialchars($t('admin_ai.models_not_loaded', 'Список моделей пока не загружен.'), ENT_QUOTES, 'UTF-8') ?></span></div>
    </div>
  </div>
</div>

</section>

<section class="tab-pane fade" id="adminAiOpsPane" role="tabpanel" aria-labelledby="adminAiOpsTab" tabindex="0">

<div class="row g-3 mt-1">
  <div class="col-xl-6">
    <div class="crm-card crm-section-card">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0" data-i18n="admin_ai.section_usage_summary"><?= htmlspecialchars($t('admin_ai.section_usage_summary', 'Сводка использования'), ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="d-flex gap-2 align-items-center">
          <select id="adminAiUsageRange" class="form-select form-select-sm crm-field-w-160">
            <option value="1" data-i18n="admin_ai.opt_today"><?= htmlspecialchars($t('admin_ai.opt_today', 'Сегодня'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="7" selected data-i18n="admin_ai.opt_7days"><?= htmlspecialchars($t('admin_ai.opt_7days', '7 дней'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="30" data-i18n="admin_ai.opt_30days"><?= htmlspecialchars($t('admin_ai.opt_30days', '30 дней'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
          <button id="adminAiUsageReloadBtn" class="btn btn-sm crm-btn-secondary" type="button" data-i18n="page.refresh"><?= htmlspecialchars($t('page.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </div>
      <div id="adminAiUsageRangeSummary" class="small text-muted mb-2" data-i18n="admin_ai.loading_usage"><?= htmlspecialchars($t('admin_ai.loading_usage', 'Загрузка использования...'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="table-responsive crm-admin-ai-scroll-table">
        <table class="table crm-table mb-0">
          <thead><tr><th data-i18n="admin_ai.th_intent"><?= htmlspecialchars($t('admin_ai.th_intent', 'Сценарий'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_requests"><?= htmlspecialchars($t('admin_ai.th_requests', 'Запросы'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_errors"><?= htmlspecialchars($t('admin_ai.th_errors', 'Ошибки'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_avg_latency"><?= htmlspecialchars($t('admin_ai.th_avg_latency', 'Средняя задержка'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
          <tbody id="adminAiUsageBody"><tr><td colspan="4" class="text-muted" data-i18n="admin_ai.loading_summary"><?= htmlspecialchars($t('admin_ai.loading_summary', 'Загрузка сводки...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
        </table>
      </div>
      <hr>
      <h3 class="h6 mb-2" data-i18n="admin_ai.section_connection_health"><?= htmlspecialchars($t('admin_ai.section_connection_health', 'Задержка и ошибки подключений'), ENT_QUOTES, 'UTF-8') ?></h3>
      <div class="table-responsive crm-admin-ai-scroll-table">
        <table class="table crm-table mb-0">
          <thead><tr><th data-i18n="admin_ai.th_connection"><?= htmlspecialchars($t('admin_ai.th_connection', 'Подключение'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_requests"><?= htmlspecialchars($t('admin_ai.th_requests', 'Запросы'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_errors"><?= htmlspecialchars($t('admin_ai.th_errors', 'Ошибки'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_error_rate"><?= htmlspecialchars($t('admin_ai.th_error_rate', 'Доля ошибок'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_avg_latency"><?= htmlspecialchars($t('admin_ai.th_avg_latency', 'Средняя задержка'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
          <tbody id="adminAiProviderHealthBody"><tr><td colspan="5" class="text-muted" data-i18n="admin_ai.loading_metrics"><?= htmlspecialchars($t('admin_ai.loading_metrics', 'Загрузка метрик подключений...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-6">
    <div class="crm-card crm-section-card">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0" data-i18n="admin_ai.section_audit_log"><?= htmlspecialchars($t('admin_ai.section_audit_log', 'История действий AI'), ENT_QUOTES, 'UTF-8') ?></h2>
        <button id="adminAiAuditReloadBtn" class="btn btn-sm crm-btn-secondary" type="button" data-i18n="page.refresh"><?= htmlspecialchars($t('page.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div class="table-responsive crm-admin-ai-scroll-table">
        <table class="table crm-table mb-0">
          <thead><tr><th data-i18n="admin_ai.th_action"><?= htmlspecialchars($t('admin_ai.th_action', 'Действие'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_object"><?= htmlspecialchars($t('admin_ai.th_object', 'Объект'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_date"><?= htmlspecialchars($t('admin_ai.th_date', 'Дата'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:120px" data-i18n="admin_ai.th_details"><?= htmlspecialchars($t('admin_ai.th_details', 'Детали'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
          <tbody id="adminAiAuditBody"><tr><td colspan="4" class="text-muted" data-i18n="admin_ai.loading_history"><?= htmlspecialchars($t('admin_ai.loading_history', 'Загрузка истории...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
        </table>
      </div>
      <div class="crm-soft-panel mt-2">
        <div class="small text-muted mb-1" id="adminAiAuditDetailTitle" data-i18n="admin_ai.audit_detail_title"><?= htmlspecialchars($t('admin_ai.audit_detail_title', 'Очищенные детали записи'), ENT_QUOTES, 'UTF-8') ?></div>
        <pre id="adminAiAuditDetailPre" class="small mb-0 crm-pre-wrap crm-max-h-180 crm-overflow-auto" data-i18n="admin_ai.audit_detail_select"><?= htmlspecialchars($t('admin_ai.audit_detail_select', 'Выберите запись в таблице выше.'), ENT_QUOTES, 'UTF-8') ?></pre>
      </div>
      <hr>
      <h3 class="h6 mb-2" data-i18n="admin_ai.section_recent_errors"><?= htmlspecialchars($t('admin_ai.section_recent_errors', 'Последние ошибки AI-задач'), ENT_QUOTES, 'UTF-8') ?></h3>
      <div class="small text-muted mb-2" data-i18n="admin_ai.errors_safe_only"><?= htmlspecialchars($t('admin_ai.errors_safe_only', 'Показываются только безопасные для просмотра данные.'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="table-responsive">
        <table class="table crm-table mb-0">
          <thead><tr><th data-i18n="admin_ai.th_intent"><?= htmlspecialchars($t('admin_ai.th_intent', 'Сценарий'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_connection"><?= htmlspecialchars($t('admin_ai.th_connection', 'Подключение'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_error"><?= htmlspecialchars($t('admin_ai.th_error', 'Ошибка'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_date"><?= htmlspecialchars($t('admin_ai.th_date', 'Дата'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
          <tbody id="adminAiFailedJobsBody"><tr><td colspan="4" class="text-muted" data-i18n="admin_ai.loading_errors"><?= htmlspecialchars($t('admin_ai.loading_errors', 'Загрузка ошибок...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

</section>

<section class="tab-pane fade" id="adminAiPromptsPane" role="tabpanel" aria-labelledby="adminAiPromptsTab" tabindex="0">

<div class="row g-3 mt-1">
  <div class="col-xl-6">
    <div class="crm-card crm-section-card mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0" data-i18n="admin_ai.section_prompts"><?= htmlspecialchars($t('admin_ai.section_prompts', 'Инструкции AI'), ENT_QUOTES, 'UTF-8') ?></h2>
        <button id="adminAiPromptsReloadBtn" class="btn btn-sm btn-light" type="button" data-i18n="page.refresh"><?= htmlspecialchars($t('page.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div class="table-responsive mb-3 crm-admin-ai-scroll-table">
        <table class="table crm-table mb-0">
          <thead><tr><th data-i18n="admin_ai.th_intent_locale"><?= htmlspecialchars($t('admin_ai.th_intent_locale', 'Сценарий / язык'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_version"><?= htmlspecialchars($t('admin_ai.th_version', 'Версия'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_status"><?= htmlspecialchars($t('admin_ai.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:140px" data-i18n="admin_ai.th_action"><?= htmlspecialchars($t('admin_ai.th_action', 'Действие'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
          <tbody id="adminAiPromptsBody"><tr><td colspan="4" class="text-muted" data-i18n="admin_ai.loading_prompts"><?= htmlspecialchars($t('admin_ai.loading_prompts', 'Загрузка инструкций...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
        </table>
      </div>
      <h3 class="h6 mb-2" data-i18n="admin_ai.section_create_prompt"><?= htmlspecialchars($t('admin_ai.section_create_prompt', 'Создать инструкцию'), ENT_QUOTES, 'UTF-8') ?></h3>
      <form id="adminAiPromptCreateForm" class="row g-2">
        <div class="col-md-6"><label class="form-label" data-i18n="admin_ai.label_intent_code"><?= htmlspecialchars($t('admin_ai.label_intent_code', 'Код сценария'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="intent_code" maxlength="128" placeholder="<?= htmlspecialchars($t('admin_ai.placeholder_intent_code', 'task_summary'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_ai.placeholder_intent_code" required></div>
        <div class="col-md-3"><label class="form-label" data-i18n="admin_ai.label_locale"><?= htmlspecialchars($t('admin_ai.label_locale', 'Язык'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="locale" maxlength="16" value="ru-ru" required></div>
        <div class="col-md-3"><label class="form-label" data-i18n="admin_ai.label_version"><?= htmlspecialchars($t('admin_ai.label_version', 'Версия'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="number" min="1" step="1" name="version" value="1"></div>
        <div class="col-12"><label class="form-label" data-i18n="admin_ai.label_template_text"><?= htmlspecialchars($t('admin_ai.label_template_text', 'Текст инструкции'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="template_text" rows="4" maxlength="64000" placeholder="<?= htmlspecialchars($t('admin_ai.placeholder_template_text', 'Инструкция для сценария...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_ai.placeholder_template_text" required></textarea></div>
        <div class="col-md-4"><label class="form-label" data-i18n="admin_ai.label_active"><?= htmlspecialchars($t('admin_ai.label_active', 'Активный'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="is_active"><option value="1" data-i18n="page.yes"><?= htmlspecialchars($t('page.yes', 'Да'), ENT_QUOTES, 'UTF-8') ?></option><option value="0" data-i18n="page.no"><?= htmlspecialchars($t('page.no', 'Нет'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
        <div class="col-12"><button class="btn btn-sm btn-light" type="submit" data-i18n="admin_ai.btn_create_instruction"><?= htmlspecialchars($t('admin_ai.btn_create_instruction', 'Создать инструкцию'), ENT_QUOTES, 'UTF-8') ?></button></div>
      </form>
    </div>
  </div>

  <div class="col-xl-6">
    <div class="crm-card crm-section-card mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0" data-i18n="admin_ai.section_response_formats"><?= htmlspecialchars($t('admin_ai.section_response_formats', 'Форматы ответа'), ENT_QUOTES, 'UTF-8') ?></h2>
        <button id="adminAiSchemasReloadBtn" class="btn btn-sm btn-light" type="button" data-i18n="page.refresh"><?= htmlspecialchars($t('page.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div class="table-responsive mb-3 crm-admin-ai-scroll-table">
        <table class="table crm-table mb-0">
          <thead><tr><th data-i18n="admin_ai.th_intent"><?= htmlspecialchars($t('admin_ai.th_intent', 'Сценарий'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_schema_version"><?= htmlspecialchars($t('admin_ai.th_schema_version', 'Версия формата'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_ai.th_status"><?= htmlspecialchars($t('admin_ai.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:140px" data-i18n="admin_ai.th_action"><?= htmlspecialchars($t('admin_ai.th_action', 'Действие'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
          <tbody id="adminAiSchemasBody"><tr><td colspan="4" class="text-muted" data-i18n="admin_ai.loading_schemas"><?= htmlspecialchars($t('admin_ai.loading_schemas', 'Загрузка форматов ответа...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
        </table>
      </div>
      <h3 class="h6 mb-2" data-i18n="admin_ai.section_create_schema"><?= htmlspecialchars($t('admin_ai.section_create_schema', 'Создать формат ответа'), ENT_QUOTES, 'UTF-8') ?></h3>
      <form id="adminAiSchemaCreateForm" class="row g-2">
        <div class="col-md-6"><label class="form-label" data-i18n="admin_ai.label_intent_code"><?= htmlspecialchars($t('admin_ai.label_intent_code', 'Код сценария'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="intent_code" maxlength="128" placeholder="<?= htmlspecialchars($t('admin_ai.placeholder_intent_code', 'task_summary'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_ai.placeholder_intent_code" required></div>
        <div class="col-md-6"><label class="form-label" data-i18n="admin_ai.label_schema_version"><?= htmlspecialchars($t('admin_ai.label_schema_version', 'Версия формата'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="schema_version" maxlength="32" value="v1" required></div>
        <div class="col-12"><label class="form-label" data-i18n="admin_ai.label_schema_json"><?= htmlspecialchars($t('admin_ai.label_schema_json', 'JSON формата'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="schema_json" rows="6" maxlength="64000" placeholder='{"type":"object","required":[],"properties":{}}' required></textarea></div>
        <div class="col-md-4"><label class="form-label" data-i18n="admin_ai.label_active_f"><?= htmlspecialchars($t('admin_ai.label_active_f', 'Активная'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="is_active"><option value="1" data-i18n="page.yes"><?= htmlspecialchars($t('page.yes', 'Да'), ENT_QUOTES, 'UTF-8') ?></option><option value="0" data-i18n="page.no"><?= htmlspecialchars($t('page.no', 'Нет'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
        <div class="col-12"><button class="btn btn-sm btn-light" type="submit" data-i18n="admin_ai.btn_create_schema"><?= htmlspecialchars($t('admin_ai.btn_create_schema', 'Создать схему'), ENT_QUOTES, 'UTF-8') ?></button></div>
      </form>
    </div>
  </div>
</div>

</section>
</div>

</main></div></div>
