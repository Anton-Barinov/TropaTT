<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — AI-помощник'; ?>
<body data-page="admin-ai" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-admin-ai-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin">Админка</a></li><li class="breadcrumb-item active">AI-помощник</li></ol><h1 class="crm-page-title">AI-помощник</h1><p class="crm-subtitle">Подключите AI к CRM, выберите модель и настройте доступ к данным.</p></div></div>

<section class="crm-card crm-section-card crm-admin-ai-hero mb-3" id="adminAiHeroCard">
  <div class="crm-admin-ai-hero-icon" aria-hidden="true">
    <i class="fa-solid fa-circle-check"></i>
  </div>
  <div class="crm-admin-ai-hero-body">
    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
      <h2 class="h4 mb-0" id="adminAiHeroTitle">Проверяем состояние AI</h2>
      <span class="crm-badge archived" id="adminAiHeroStatus">Загрузка</span>
    </div>
    <div class="crm-admin-ai-hero-meta">
      <div><span>Основной сервис</span><strong id="adminAiHeroProvider">—</strong></div>
      <div><span>Модель</span><strong id="adminAiHeroModel">—</strong></div>
      <div><span>Последняя проверка</span><strong id="adminAiHeroLastCheck">—</strong></div>
      <div><span>Ключ доступа</span><strong id="adminAiHeroSecret">—</strong></div>
      <div><span>Используется в</span><strong id="adminAiHeroScenarios">—</strong></div>
    </div>
  </div>
  <div class="crm-admin-ai-hero-actions">
    <button class="btn crm-btn-primary" id="adminAiHeroTestBtn" type="button">
      <span class="crm-admin-ai-btn-icon" aria-hidden="true"><i class="fa-solid fa-heart-pulse"></i></span>
      <span>Проверить подключение</span>
    </button>
    <button class="btn crm-btn-secondary" id="adminAiHeroEditBtn" type="button">
      <span class="crm-admin-ai-btn-icon" aria-hidden="true"><i class="fa-solid fa-sliders"></i></span>
      <span>Изменить настройки</span>
    </button>
    <button class="btn btn-link crm-admin-ai-advanced-link" id="adminAiAdvancedSettingsBtn" type="button" data-bs-toggle="modal" data-bs-target="#adminAiAdvancedModal">
      <span class="crm-admin-ai-btn-icon" aria-hidden="true"><i class="fa-solid fa-bars-staggered"></i></span>
      <span>Расширенные настройки</span>
    </button>
  </div>
</section>

<div class="row g-3 mb-3 crm-kpi-row">
  <div class="col-sm-6 col-xl-3"><div class="crm-card crm-kpi-card crm-admin-ai-metric"><span class="crm-admin-ai-metric-icon" aria-hidden="true"><i class="fa-solid fa-link"></i></span><div><small class="text-muted">Подключения</small><h2 id="adminAiKpiProviders" class="h4 mb-0">0</h2></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="crm-card crm-kpi-card crm-admin-ai-metric"><span class="crm-admin-ai-metric-icon is-success" aria-hidden="true"><i class="fa-solid fa-rocket"></i></span><div><small class="text-muted">Активные сценарии</small><h2 id="adminAiKpiEnabledIntents" class="h4 mb-0">0</h2></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="crm-card crm-kpi-card crm-admin-ai-metric"><span class="crm-admin-ai-metric-icon is-info" aria-hidden="true"><i class="fa-solid fa-chart-column"></i></span><div><small class="text-muted">Выполнено сегодня</small><h2 id="adminAiKpiJobsToday" class="h4 mb-0">0</h2></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="crm-card crm-kpi-card crm-admin-ai-metric is-warning"><span class="crm-admin-ai-metric-icon is-warning" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span><div><small class="text-muted">Требует внимания</small><h2 id="adminAiKpiErrorsToday" class="h4 mb-0">0</h2></div></div></div>
</div>

<div class="crm-card crm-admin-ai-tabs mb-3">
  <ul class="nav nav-pills gap-2" id="adminAiTabs" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link active" id="adminAiProvidersTab" data-bs-toggle="tab" data-bs-target="#adminAiProvidersPane" type="button" role="tab" aria-controls="adminAiProvidersPane" aria-selected="true">Подключение</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="adminAiIntentsTab" data-bs-toggle="tab" data-bs-target="#adminAiIntentsPane" type="button" role="tab" aria-controls="adminAiIntentsPane" aria-selected="false">Сценарии</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="adminAiPromptsTab" data-bs-toggle="tab" data-bs-target="#adminAiPromptsPane" type="button" role="tab" aria-controls="adminAiPromptsPane" aria-selected="false">Инструкции AI</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="adminAiOpsTab" data-bs-toggle="tab" data-bs-target="#adminAiOpsPane" type="button" role="tab" aria-controls="adminAiOpsPane" aria-selected="false">История работы</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="adminAiPoliciesTab" data-bs-toggle="tab" data-bs-target="#adminAiPoliciesPane" type="button" role="tab" aria-controls="adminAiPoliciesPane" aria-selected="false">Безопасность</button></li>
  </ul>
</div>

<div class="tab-content crm-admin-ai-tab-content" id="adminAiTabContent">
<section class="tab-pane fade show active" id="adminAiProvidersPane" role="tabpanel" aria-labelledby="adminAiProvidersTab" tabindex="0">

<div class="row g-3 align-items-start mb-3">
  <div class="col-lg-8">
    <div class="crm-card crm-section-card crm-admin-ai-connection-card" id="adminAiProviderOverviewCard">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
          <h2 class="h5 mb-1">Основное подключение</h2>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-sm btn-light" id="adminAiPrimaryEditBtn" type="button">
            <span class="crm-admin-ai-btn-icon" aria-hidden="true"><i class="fa-solid fa-sliders"></i></span>
            Настроить
          </button>
          <button class="btn btn-sm btn-light" id="adminAiPrimaryTestBtn" type="button">
            <span class="crm-admin-ai-btn-icon" aria-hidden="true"><i class="fa-solid fa-heart-pulse"></i></span>
            Проверить
          </button>
          <div class="dropdown">
            <button class="btn btn-sm btn-light crm-admin-ai-icon-btn" type="button" id="adminAiPrimaryMenuBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Дополнительные действия">
              <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminAiPrimaryMenuBtn">
              <li><button class="dropdown-item" type="button" id="adminAiPrimaryModelsBtn">Управлять моделями</button></li>
              <li><button class="dropdown-item" type="button" id="adminAiPrimaryTechBtn" data-bs-toggle="modal" data-bs-target="#adminAiTechnicalModal">Показать технические данные</button></li>
              <li><hr class="dropdown-divider"></li>
              <li><button class="dropdown-item text-danger" type="button" id="adminAiPrimaryDeleteBtn">Удалить</button></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="crm-admin-ai-primary-summary">
        <div class="crm-admin-ai-service-mark" aria-hidden="true"><i class="fa-solid fa-circle-plus"></i></div>
        <div>
          <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <h3 class="h5 mb-0" id="adminAiPrimaryTitle">—</h3>
            <span class="crm-badge archived" id="adminAiPrimaryStatusBadge">Загрузка</span>
            <span class="crm-chip" id="adminAiPrimarySecret">Ключ доступа: —</span>
          </div>
          <div class="text-muted small" id="adminAiPrimaryHost">—</div>
          <div class="d-flex gap-2 flex-wrap mt-2">
            <span class="crm-chip d-none" id="adminAiPrimaryDefaultBadge">Используется по умолчанию</span>
          </div>
        </div>
      </div>
      <div class="crm-admin-ai-connection-grid mt-3">
        <div><span>Модель для ответов</span><strong id="adminAiPrimaryAnswerModel">—</strong></div>
        <div><span>Модель для поиска по данным</span><strong id="adminAiPrimarySearchModel">—</strong></div>
      </div>
      <div class="d-flex gap-2 flex-wrap mt-3">
        <span class="crm-chip">Ответы</span>
        <span class="crm-chip">Поиск по базе</span>
        <span class="crm-chip">Распознавание речи</span>
      </div>
    </div>

    <div class="crm-card crm-section-card crm-admin-ai-other-card mt-3">
      <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
        <div>
          <h2 class="h5 mb-1">Другие подключения</h2>
          <p class="text-muted small mb-0">Дополнительные сервисы можно проверить или настроить отдельно.</p>
        </div>
        <button class="btn crm-btn-secondary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#adminAiCreateModal">Добавить подключение</button>
      </div>
      <div id="adminAiProvidersBody" class="crm-admin-ai-provider-list"><div class="text-muted">Загрузка подключений...</div></div>
    </div>

    <div class="crm-card crm-section-card crm-admin-ai-help-card mt-3">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <span class="crm-admin-ai-help-icon" aria-hidden="true"><i class="fa-solid fa-circle-question"></i></span>
        <div class="me-auto">
          <h2 class="h6 mb-1">Нужна помощь с настройкой?</h2>
          <p class="text-muted small mb-0">Мы подготовили понятные инструкции и ответы на частые вопросы.</p>
        </div>
        <a class="btn crm-btn-secondary btn-sm" href="index.php?route=docs">Открыть справку</a>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="crm-card crm-section-card crm-admin-ai-attention-card">
      <h2 class="h5 mb-3">Что требует внимания</h2>
      <div id="adminAiAttentionState">
        <div class="crm-admin-ai-attention-panel">
          <strong>Загружаем проверки</strong>
          <p class="text-muted mb-0">Состояние подключений появится через несколько секунд.</p>
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap mt-3">
        <button class="btn crm-btn-primary btn-sm" id="adminAiAttentionCheckBtn" type="button">Проверить сейчас</button>
        <button class="btn crm-btn-secondary btn-sm" id="adminAiAttentionDetailsBtn" type="button" data-bs-toggle="modal" data-bs-target="#adminAiAdvancedModal">Подробнее</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="adminAiAdvancedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="h6 mb-0">Расширенные настройки</h2>
        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <div id="adminAiReviewCard" class="crm-card crm-section-card crm-admin-ai-diagnostics mb-0" data-ai-state="empty">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div>
              <h3 class="h6 mb-1">Диагностика AI-помощника</h3>
              <p class="small text-muted mb-0">Запускайте проверки журналов, обмена данными и сценариев. Чувствительные данные не показываются на основном экране.</p>
            </div>
            <span class="crm-chip">только админ</span>
          </div>
          <div class="d-flex gap-2 flex-wrap mb-3">
            <button id="adminAiLogReviewBtn" class="btn btn-sm btn-light" type="button">Проверить журналы</button>
            <button id="adminAiWebhookHealthBtn" class="btn btn-sm btn-light" type="button">Проверить обмен данными</button>
            <button id="adminAiWorkflowAuditBtn" class="btn btn-sm btn-light" type="button">Проверить сценарии</button>
            <button id="adminAiDailyPlanBtn" class="btn btn-sm btn-light" type="button" data-requires-ai-use="1">План задач на день</button>
            <button id="adminAiSecurityLogBtn" class="btn btn-sm btn-light" type="button" data-requires-ai-use="1">Проверка безопасности</button>
            <button id="adminAiReviewPreviewBtn" class="btn btn-sm btn-light" type="button" disabled>Предпросмотр</button>
            <button id="adminAiReviewDismissBtn" class="btn btn-sm btn-outline-secondary" type="button" disabled>Отклонить результат</button>
          </div>
          <div class="crm-admin-ai-diagnostics-result">
            <div>
              <div id="adminAiReviewState" class="small text-muted mb-1">Диагностика ещё не запускалась.</div>
              <div id="adminAiReviewSummary" class="small fw-semibold">Выберите проверку выше.</div>
            </div>
            <div class="crm-soft-panel">
              <div class="small text-muted mb-1">Факты</div>
              <ul id="adminAiReviewFacts" class="small mb-0 ps-3"><li class="text-muted">Нет данных.</li></ul>
            </div>
            <div class="crm-soft-panel">
              <div class="small text-muted mb-1">Риски / вопросы</div>
              <ul id="adminAiReviewRisks" class="small mb-0 ps-3"><li class="text-muted">Нет данных.</li></ul>
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
        <h2 class="h6 mb-0">Технические данные</h2>
        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <div id="adminAiTechnicalDetails" class="crm-soft-panel small">Выберите подключение.</div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="adminAiCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="h6 mb-0">Настроить подключение</h2>
        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
          <div class="small text-muted">Обязательные поля: название, ключ доступа, адрес API и модель для ответов.</div>
          <button id="adminAiCreateAdvancedToggle" class="btn btn-sm btn-light" type="button">Режим: базовый</button>
        </div>
        <form id="adminAiCreateForm" class="row g-2">
          <div class="col-md-4">
            <label class="form-label">Тип AI-сервиса</label>
            <select id="adminAiCreateProviderType" class="form-select">
              <option value="openai">OpenAI</option>
              <option value="compatible">OpenAI-compatible</option>
              <option value="deepseek">DeepSeek</option>
              <option value="cloud">Облачный сервис</option>
              <option value="local">Локальный сервис</option>
              <option value="custom">Другое</option>
            </select>
          </div>
          <div class="col-md-8">
            <label for="adminAiCreatePresetSelect" class="form-label">Шаблон настроек</label>
            <select id="adminAiCreatePresetSelect" class="form-select">
              <option value="openai">OpenAI (рекомендуется)</option>
              <option value="anthropic">Anthropic (Cloud)</option>
              <option value="gemini">Gemini</option>
              <option value="openrouter">OpenRouter</option>
              <option value="deepseek">DeepSeek</option>
              <option value="mistral">Mistral</option>
              <option value="zai">Z.AI (GLM)</option>
              <option value="kimi">Kimi (Moonshot)</option>
              <option value="ollama">Ollama (локально)</option>
              <option value="lmstudio">LM Studio (локально)</option>
              <option value="generic">OpenAI-compatible (свой сервис)</option>
            </select>
          </div>
          <div class="col-md-6"><label class="form-label">Внутренний код</label><input class="form-control" name="provider_code" maxlength="64" placeholder="openai" required></div>
          <div class="col-md-6"><label class="form-label">Название</label><input class="form-control" name="title" maxlength="255" placeholder="OpenAI" required></div>
          <div class="col-md-8"><label class="form-label">Ключ доступа</label><input class="form-control" name="create_secret" type="password" autocomplete="new-password" placeholder="sk-..." required><div class="form-text">Ключ хранится только на сервере в зашифрованном виде.</div></div>
          <div class="col-md-4"><label class="form-label">Модель для ответов</label><input class="form-control" name="default_model" maxlength="255" placeholder="gpt-4.1-mini" required></div>
          <div class="col-12"><label class="form-label">Адрес API</label><input class="form-control" name="base_url" maxlength="2048" placeholder="https://api.openai.com" required><div class="form-text">Без пробелов и без завершающего `/v1/chat/completions`.</div></div>
          <div class="col-md-6"><label class="form-label">Путь API</label><input class="form-control" name="api_path" maxlength="255" value="/v1/chat/completions"><div class="form-text">Путь должен начинаться с `/`.</div></div>
          <div class="col-md-6"><label class="form-label">Модель для поиска по данным</label><input class="form-control" name="embedding_model" maxlength="255" placeholder="text-embedding-3-small"></div>
          <div class="col-12"><div class="crm-soft-panel small"><div>Итоговый адрес для ответов: <code id="adminAiCreateFinalChatEndpoint">—</code></div><div>Итоговый адрес для поиска: <code id="adminAiCreateFinalEmbeddingsEndpoint">—</code></div></div></div>
          <div class="col-md-6 admin-ai-advanced-create d-none"><label class="form-label">Адрес поиска по данным</label><input class="form-control" name="embeddings_endpoint" maxlength="255" placeholder="/v1/embeddings"><div class="form-text">Должен начинаться с `/`.</div></div>
          <div class="col-md-6 admin-ai-advanced-create d-none"><label class="form-label">Заголовок авторизации</label><input class="form-control" name="auth_header" maxlength="128" placeholder="Authorization"></div>
          <div class="col-md-6 admin-ai-advanced-create d-none"><label class="form-label">Схема авторизации</label><input class="form-control" name="auth_scheme" maxlength="64" placeholder="Bearer"></div>
          <div class="col-md-6 admin-ai-advanced-create d-none"><label class="form-label">Проверка TLS</label><select class="form-select" name="tls_verify"><option value="1" selected>Включена</option><option value="0">Выключена только для локальной разработки</option></select></div>
          <div class="col-md-6 admin-ai-advanced-create d-none"><label class="form-label">Быстрая модель</label><input class="form-control" name="fast_model" maxlength="255" placeholder="gpt-4.1-mini"></div>
          <div class="col-md-4 admin-ai-advanced-create d-none"><label class="form-label">Модель для сложных задач</label><input class="form-control" name="reasoning_model" maxlength="255" placeholder="gpt-5.4-mini"></div>
          <div class="col-md-4 admin-ai-advanced-create d-none"><label class="form-label">Резервная модель</label><input class="form-control" name="fallback_model" maxlength="255" placeholder="gpt-4.1"></div>
          <div class="col-md-4 admin-ai-advanced-create d-none"><label class="form-label">Таймаут, мс</label><input class="form-control" type="number" min="1000" step="1000" name="timeout_ms" value="30000"></div>
          <div class="col-md-3 admin-ai-advanced-create d-none"><label class="form-label">Лимит токенов</label><input class="form-control" type="number" min="1" step="1" name="max_tokens" value="2000"></div>
          <div class="col-md-3 admin-ai-advanced-create d-none"><label class="form-label">Температура</label><input class="form-control" type="text" inputmode="decimal" name="temperature" value="0.2" placeholder="0.2"></div>
          <div class="col-md-3 admin-ai-advanced-create d-none"><label class="form-label">Повторы</label><input class="form-control" type="number" min="0" step="1" name="retry_count" value="1"></div>
          <div class="col-md-3 admin-ai-advanced-create d-none"><label class="form-label">Лимит запросов в минуту</label><input class="form-control" type="number" min="0" step="1" name="rate_limit_per_min" value="0"><div class="form-text">0 = без лимита</div></div>
          <div class="col-md-3 admin-ai-advanced-create d-none"><label class="form-label">Лимит токенов в день</label><input class="form-control" type="number" min="0" step="1" name="token_budget_daily" value="0"><div class="form-text">0 = без лимита</div></div>
          <div class="col-md-3 admin-ai-advanced-create d-none"><label class="form-label">Бюджет в день</label><input class="form-control" type="number" min="0" step="0.01" name="cost_budget_daily" value="0"><div class="form-text">0 = без лимита</div></div>
          <div class="col-md-6"><label class="form-label">Активен</label><select class="form-select" name="is_active"><option value="1">Да</option><option value="0">Нет</option></select></div>
          <div class="col-md-6"><label class="form-label">По умолчанию</label><select class="form-select" name="is_default"><option value="0">Нет</option><option value="1">Да</option></select></div>
          <div class="col-12 admin-ai-advanced-create d-none">
            <label class="form-label">Дополнительные заголовки</label>
            <div class="table-responsive">
              <table class="table table-sm mb-2">
                <thead><tr><th>Заголовок</th><th>Значение</th><th style="width:80px"></th></tr></thead>
                <tbody data-ai-headers-body="create"></tbody>
              </table>
            </div>
            <button class="btn btn-sm btn-light" type="button" data-ai-headers-add="create">Добавить заголовок</button>
            <div class="small text-danger mt-1">Не добавляйте ключи доступа в дополнительные заголовки. Используйте поле «Ключ доступа».</div>
            <div class="small text-muted">Запрещены: Authorization, X-API-Key, Cookie, Set-Cookie.</div>
          </div>
          <div class="col-12 admin-ai-advanced-create d-none">
            <label class="form-label">Возможности</label>
            <div class="d-flex gap-3 flex-wrap">
              <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="cap_json_mode">json_mode</label>
              <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="cap_tools">tools</label>
              <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="cap_streaming">streaming</label>
              <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="cap_vision">vision</label>
              <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="cap_files">files</label>
            </div>
            <div class="form-text">Отметки должны соответствовать реальной модели, иначе проверка покажет предупреждение.</div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button>
        <button class="btn crm-btn-primary" type="submit" form="adminAiCreateForm">Сохранить</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="adminAiEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="h6 mb-0">Настроить подключение</h2>
        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
          <div class="small text-muted">Обязательные поля: название, ключ доступа, адрес API и модель для ответов.</div>
          <button id="adminAiEditAdvancedToggle" class="btn btn-sm crm-btn-secondary" type="button">Режим: базовый</button>
        </div>
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
          <label for="adminAiEditProviderType" class="small text-muted mb-0">Тип:</label>
          <select id="adminAiEditProviderType" class="form-select form-select-sm crm-field-min-w-210">
            <option value="openai">OpenAI</option>
            <option value="compatible">OpenAI-compatible</option>
            <option value="deepseek">DeepSeek</option>
            <option value="cloud">Облачный сервис</option>
            <option value="local">Локальный сервис</option>
            <option value="custom">Другое</option>
          </select>
          <label for="adminAiEditPresetSelect" class="small text-muted mb-0">Шаблон:</label>
          <select id="adminAiEditPresetSelect" class="form-select form-select-sm crm-field-min-w-240">
            <option value="custom">Как есть</option>
            <option value="openai">OpenAI (рекомендуется)</option>
            <option value="anthropic">Anthropic (Cloud)</option>
            <option value="gemini">Gemini</option>
            <option value="openrouter">OpenRouter</option>
            <option value="deepseek">DeepSeek</option>
            <option value="mistral">Mistral</option>
            <option value="zai">Z.AI (GLM)</option>
            <option value="kimi">Kimi (Moonshot)</option>
            <option value="ollama">Ollama (локально)</option>
            <option value="lmstudio">LM Studio (локально)</option>
            <option value="generic">OpenAI-compatible (свой сервис)</option>
          </select>
        </div>
        <form id="adminAiEditForm" class="row g-2">
          <input type="hidden" name="public_id">
          <div class="col-md-6"><label class="form-label">Внутренний код</label><input class="form-control" name="provider_code" maxlength="64"></div>
          <div class="col-md-6"><label class="form-label">Название</label><input class="form-control" name="title" maxlength="255"></div>
          <div class="col-12"><label class="form-label">Адрес API</label><input class="form-control" name="base_url" maxlength="2048"><div class="form-text">Без пробелов и без завершающего `/v1/chat/completions`.</div></div>
          <div class="col-md-6"><label class="form-label">Путь API</label><input class="form-control" name="api_path" maxlength="255"><div class="form-text">Путь должен начинаться с `/`.</div></div>
          <div class="col-md-6"><label class="form-label">Модель для ответов</label><input class="form-control" name="default_model" maxlength="255"></div>
          <div class="col-md-6"><label class="form-label">Модель для поиска по данным</label><input class="form-control" name="embedding_model" maxlength="255"></div>
          <div class="col-md-6"><label class="form-label">Адрес поиска по данным</label><input class="form-control" name="embeddings_endpoint" maxlength="255" placeholder="/v1/embeddings"></div>
          <div class="col-12"><div class="crm-soft-panel small"><div>Итоговый адрес для ответов: <code id="adminAiEditFinalChatEndpoint">—</code></div><div>Итоговый адрес для поиска: <code id="adminAiEditFinalEmbeddingsEndpoint">—</code></div></div></div>
          <div class="col-md-4 admin-ai-advanced-edit d-none"><label class="form-label">Таймаут, мс</label><input class="form-control" type="number" min="1000" step="1000" name="timeout_ms"></div>
          <div class="col-md-4 admin-ai-advanced-edit d-none"><label class="form-label">Лимит токенов</label><input class="form-control" type="number" min="1" step="1" name="max_tokens"></div>
          <div class="col-md-4 admin-ai-advanced-edit d-none"><label class="form-label">Температура</label><input class="form-control" type="text" inputmode="decimal" name="temperature" placeholder="0.2"></div>
          <div class="col-md-6 admin-ai-advanced-edit d-none"><label class="form-label">Заголовок авторизации</label><input class="form-control" name="auth_header" maxlength="128" placeholder="Authorization"></div>
          <div class="col-md-6 admin-ai-advanced-edit d-none"><label class="form-label">Схема авторизации</label><input class="form-control" name="auth_scheme" maxlength="64" placeholder="Bearer"></div>
          <div class="col-md-6 admin-ai-advanced-edit d-none"><label class="form-label">Проверка TLS</label><select class="form-select" name="tls_verify"><option value="1">Включена</option><option value="0">Выключена только для локальной разработки</option></select></div>
          <div class="col-md-6 admin-ai-advanced-edit d-none"><label class="form-label">Быстрая модель</label><input class="form-control" name="fast_model" maxlength="255"></div>
          <div class="col-md-4 admin-ai-advanced-edit d-none"><label class="form-label">Модель для сложных задач</label><input class="form-control" name="reasoning_model" maxlength="255"></div>
          <div class="col-md-4 admin-ai-advanced-edit d-none"><label class="form-label">Резервная модель</label><input class="form-control" name="fallback_model" maxlength="255"></div>
          <div class="col-md-4 admin-ai-advanced-edit d-none"><label class="form-label">Повторы</label><input class="form-control" type="number" min="0" step="1" name="retry_count"></div>
          <div class="col-md-3 admin-ai-advanced-edit d-none"><label class="form-label">Лимит запросов в минуту</label><input class="form-control" type="number" min="0" step="1" name="rate_limit_per_min"><div class="form-text">0 = без лимита</div></div>
          <div class="col-md-3 admin-ai-advanced-edit d-none"><label class="form-label">Лимит токенов в день</label><input class="form-control" type="number" min="0" step="1" name="token_budget_daily"><div class="form-text">0 = без лимита</div></div>
          <div class="col-md-3 admin-ai-advanced-edit d-none"><label class="form-label">Бюджет в день</label><input class="form-control" type="number" min="0" step="0.01" name="cost_budget_daily"><div class="form-text">0 = без лимита</div></div>
          <div class="col-md-3"><label class="form-label">Активен</label><select class="form-select" name="is_active"><option value="1">Да</option><option value="0">Нет</option></select></div>
          <div class="col-md-3"><label class="form-label">По умолчанию</label><select class="form-select" name="is_default"><option value="0">Нет</option><option value="1">Да</option></select></div>
          <div class="col-12 admin-ai-advanced-edit d-none">
            <label class="form-label">Дополнительные заголовки</label>
            <div class="table-responsive">
              <table class="table table-sm mb-2">
                <thead><tr><th>Заголовок</th><th>Значение</th><th style="width:80px"></th></tr></thead>
                <tbody data-ai-headers-body="edit"></tbody>
              </table>
            </div>
            <button class="btn btn-sm btn-light" type="button" data-ai-headers-add="edit">Добавить заголовок</button>
            <div class="small text-danger mt-1">Не добавляйте ключи доступа в дополнительные заголовки. Используйте поле «Ключ доступа».</div>
            <div class="small text-muted">Запрещены: Authorization, X-API-Key, Cookie, Set-Cookie.</div>
          </div>
          <div class="col-12 admin-ai-advanced-edit d-none">
            <label class="form-label">Возможности</label>
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
        <h3 class="h6">Ключ доступа и проверка подключения</h3>
        <form id="adminAiSecretForm" class="row g-2">
          <div class="col-md-8"><label class="form-label">Ключ доступа</label><input class="form-control" name="secret" type="password" autocomplete="new-password" placeholder="sk-... (вводится только для обновления)"><div class="form-text">Ключ не логируется и не хранится в web.</div></div>
          <div class="col-md-4 d-flex align-items-end"><button class="btn crm-btn-secondary w-100" type="submit">Обновить ключ</button></div>
        </form>
        <div class="d-flex justify-content-between align-items-center mt-2">
          <div id="adminAiSecretState" class="small text-muted">Ключ доступа не добавлен.</div>
          <button id="adminAiSecretDeleteBtn" class="btn btn-sm crm-btn-danger-soft" type="button">Удалить ключ</button>
        </div>
        <div class="d-flex gap-2 mt-3 flex-wrap">
          <button id="adminAiTestProviderBtn" class="btn btn-sm btn-light" type="button">Проверить подключение</button>
          <button id="adminAiSyncModelsBtn" class="btn btn-sm btn-light" type="button">Синхронизировать модели</button>
          <button id="adminAiDeleteProviderBtn" class="btn btn-sm crm-btn-danger-soft" type="button">Удалить подключение</button>
        </div>
        <div id="adminAiTestState" class="small text-muted mt-2">Проверка подключения еще не запускалась.</div>
        <div id="adminAiTestChecklist" class="small mt-2"></div>
      </div>
      <div class="modal-footer">
        <button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Закрыть</button>
        <button class="btn crm-btn-primary" type="submit" form="adminAiEditForm">Сохранить</button>
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
        <h2 class="h6 mb-0">Доступность функций</h2>
        <button id="adminAiFlagsReloadBtn" class="btn btn-sm btn-light" type="button">Обновить</button>
      </div>
      <div class="table-responsive crm-admin-ai-scroll-table">
        <table class="table crm-table mb-0">
          <thead><tr><th>Флаг</th><th>Статус</th><th style="width:160px">Действие</th></tr></thead>
          <tbody id="adminAiFlagsBody"><tr><td colspan="3" class="text-muted">Загрузка флагов функций...</td></tr></tbody>
        </table>
      </div>
      <div class="small text-muted mt-2">Здесь можно включить или выключить доступные функции AI.</div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">Фоновые сценарии</h2>
        <button id="adminAiCronReloadBtn" class="btn btn-sm btn-light" type="button">Обновить</button>
      </div>
      <div class="table-responsive crm-admin-ai-scroll-table">
        <table class="table crm-table mb-0">
          <thead><tr><th>Сценарий</th><th>Функция</th><th>Модель</th><th>Статус</th><th style="width:140px">Действие</th></tr></thead>
          <tbody id="adminAiCronBody"><tr><td colspan="5" class="text-muted">Загрузка фоновых сценариев...</td></tr></tbody>
        </table>
      </div>
      <div class="small text-muted mt-2">Управление автоматическими сценариями AI.</div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">Сценарии AI</h2>
        <button id="adminAiIntentsReloadBtn" class="btn btn-sm btn-light" type="button">Обновить</button>
      </div>
      <div class="table-responsive crm-admin-ai-scroll-table crm-admin-ai-intents-table">
        <table class="table crm-table mb-0">
          <thead><tr><th>Сценарий</th><th>Подключение / модель</th><th>Инструкция / формат</th><th>Функция</th><th>Правила / лимиты</th><th style="width:180px">Действие</th></tr></thead>
          <tbody id="adminAiIntentsBody"><tr><td colspan="6" class="text-muted">Загрузка сценариев...</td></tr></tbody>
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
        <h2 class="h6 mb-0">Политика хранения</h2>
        <button id="adminAiRetentionReloadBtn" class="btn btn-sm btn-light" type="button">Обновить</button>
      </div>
      <div class="table-responsive crm-admin-ai-scroll-table">
        <table class="table crm-table mb-0">
          <thead><tr><th>Политика</th><th>Дней</th><th style="width:160px">Действие</th></tr></thead>
          <tbody id="adminAiRetentionBody"><tr><td colspan="3" class="text-muted">Загрузка политики хранения...</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="crm-card crm-section-card">
      <h2 class="h6 mb-2">Разрешенные AI-действия</h2>
      <p class="text-muted small mb-2">Список разрешенных действий доступен для чтения и контроля.</p>
      <div id="adminAiActionTypesList" class="d-flex flex-wrap gap-2"><span class="text-muted">Загрузка...</span></div>
      <hr>
      <h3 class="h6 mb-2">Доступные модели</h3>
      <div id="adminAiModelsList" class="d-flex flex-wrap gap-2"><span class="text-muted">Список моделей пока не загружен.</span></div>
    </div>
  </div>
</div>

</section>

<section class="tab-pane fade" id="adminAiOpsPane" role="tabpanel" aria-labelledby="adminAiOpsTab" tabindex="0">

<div class="row g-3 mt-1">
  <div class="col-xl-6">
    <div class="crm-card crm-section-card">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">Сводка использования</h2>
        <div class="d-flex gap-2 align-items-center">
          <select id="adminAiUsageRange" class="form-select form-select-sm crm-field-w-160">
            <option value="1">Сегодня</option>
            <option value="7" selected>7 дней</option>
            <option value="30">30 дней</option>
          </select>
          <button id="adminAiUsageReloadBtn" class="btn btn-sm crm-btn-secondary" type="button">Обновить</button>
        </div>
      </div>
      <div id="adminAiUsageRangeSummary" class="small text-muted mb-2">Загрузка использования...</div>
      <div class="table-responsive crm-admin-ai-scroll-table">
        <table class="table crm-table mb-0">
          <thead><tr><th>Сценарий</th><th>Запросы</th><th>Ошибки</th><th>Средняя задержка</th></tr></thead>
          <tbody id="adminAiUsageBody"><tr><td colspan="4" class="text-muted">Загрузка сводки...</td></tr></tbody>
        </table>
      </div>
      <hr>
      <h3 class="h6 mb-2">Задержка и ошибки подключений</h3>
      <div class="table-responsive crm-admin-ai-scroll-table">
        <table class="table crm-table mb-0">
          <thead><tr><th>Подключение</th><th>Запросы</th><th>Ошибки</th><th>Доля ошибок</th><th>Средняя задержка</th></tr></thead>
          <tbody id="adminAiProviderHealthBody"><tr><td colspan="5" class="text-muted">Загрузка метрик подключений...</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-6">
    <div class="crm-card crm-section-card">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">История действий AI</h2>
        <button id="adminAiAuditReloadBtn" class="btn btn-sm crm-btn-secondary" type="button">Обновить</button>
      </div>
      <div class="table-responsive crm-admin-ai-scroll-table">
        <table class="table crm-table mb-0">
          <thead><tr><th>Действие</th><th>Объект</th><th>Дата</th><th style="width:120px">Детали</th></tr></thead>
          <tbody id="adminAiAuditBody"><tr><td colspan="4" class="text-muted">Загрузка истории...</td></tr></tbody>
        </table>
      </div>
      <div class="crm-soft-panel mt-2">
        <div class="small text-muted mb-1" id="adminAiAuditDetailTitle">Очищенные детали записи</div>
        <pre id="adminAiAuditDetailPre" class="small mb-0 crm-pre-wrap crm-max-h-180 crm-overflow-auto">Выберите запись в таблице выше.</pre>
      </div>
      <hr>
      <h3 class="h6 mb-2">Последние ошибки AI-задач</h3>
      <div class="small text-muted mb-2">Показываются только безопасные для просмотра данные.</div>
      <div class="table-responsive">
        <table class="table crm-table mb-0">
          <thead><tr><th>Сценарий</th><th>Подключение</th><th>Ошибка</th><th>Дата</th></tr></thead>
          <tbody id="adminAiFailedJobsBody"><tr><td colspan="4" class="text-muted">Загрузка ошибок...</td></tr></tbody>
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
        <h2 class="h6 mb-0">Инструкции AI</h2>
        <button id="adminAiPromptsReloadBtn" class="btn btn-sm btn-light" type="button">Обновить</button>
      </div>
      <div class="table-responsive mb-3 crm-admin-ai-scroll-table">
        <table class="table crm-table mb-0">
          <thead><tr><th>Сценарий / язык</th><th>Версия</th><th>Статус</th><th style="width:140px">Действие</th></tr></thead>
          <tbody id="adminAiPromptsBody"><tr><td colspan="4" class="text-muted">Загрузка инструкций...</td></tr></tbody>
        </table>
      </div>
      <h3 class="h6 mb-2">Создать инструкцию</h3>
      <form id="adminAiPromptCreateForm" class="row g-2">
        <div class="col-md-6"><label class="form-label">Код сценария</label><input class="form-control" name="intent_code" maxlength="128" placeholder="task_summary" required></div>
        <div class="col-md-3"><label class="form-label">Язык</label><input class="form-control" name="locale" maxlength="16" value="ru-ru" required></div>
        <div class="col-md-3"><label class="form-label">Версия</label><input class="form-control" type="number" min="1" step="1" name="version" value="1"></div>
        <div class="col-12"><label class="form-label">Текст инструкции</label><textarea class="form-control" name="template_text" rows="4" maxlength="64000" placeholder="Инструкция для сценария..." required></textarea></div>
        <div class="col-md-4"><label class="form-label">Активный</label><select class="form-select" name="is_active"><option value="1">Да</option><option value="0">Нет</option></select></div>
        <div class="col-12"><button class="btn btn-sm btn-light" type="submit">Создать инструкцию</button></div>
      </form>
    </div>
  </div>

  <div class="col-xl-6">
    <div class="crm-card crm-section-card mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">Форматы ответа</h2>
        <button id="adminAiSchemasReloadBtn" class="btn btn-sm btn-light" type="button">Обновить</button>
      </div>
      <div class="table-responsive mb-3 crm-admin-ai-scroll-table">
        <table class="table crm-table mb-0">
          <thead><tr><th>Сценарий</th><th>Версия формата</th><th>Статус</th><th style="width:140px">Действие</th></tr></thead>
          <tbody id="adminAiSchemasBody"><tr><td colspan="4" class="text-muted">Загрузка форматов ответа...</td></tr></tbody>
        </table>
      </div>
      <h3 class="h6 mb-2">Создать формат ответа</h3>
      <form id="adminAiSchemaCreateForm" class="row g-2">
        <div class="col-md-6"><label class="form-label">Код сценария</label><input class="form-control" name="intent_code" maxlength="128" placeholder="task_summary" required></div>
        <div class="col-md-6"><label class="form-label">Версия формата</label><input class="form-control" name="schema_version" maxlength="32" value="v1" required></div>
        <div class="col-12"><label class="form-label">JSON формата</label><textarea class="form-control" name="schema_json" rows="6" maxlength="64000" placeholder='{"type":"object","required":[],"properties":{}}' required></textarea></div>
        <div class="col-md-4"><label class="form-label">Активная</label><select class="form-select" name="is_active"><option value="1">Да</option><option value="0">Нет</option></select></div>
        <div class="col-12"><button class="btn btn-sm btn-light" type="submit">Создать схему</button></div>
      </form>
    </div>
  </div>
</div>

</section>
</div>

</main></div></div>
