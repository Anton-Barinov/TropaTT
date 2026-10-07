<?php declare(strict_types=1); ?>
<?php $title = $title ?? 'Outlook Calendar'; ?>
<body data-page="module-outlook-calendar" data-protected="1">
<div class="crm-app">
  <aside class="crm-sidebar">
    <div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div>
    <nav class="nav flex-column crm-nav"></nav>
  </aside>
  <div class="crm-main-wrap">
    <header class="crm-topbar py-2"><div class="container-fluid"></div></header>
    <main class="crm-content crm-admin-page">
      <div class="crm-page-head">
        <div>
          <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="index.php?route=admin"><?= htmlspecialchars($t('nav.admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li>
            <li class="breadcrumb-item"><a href="index.php?route=admin-modules"><?= htmlspecialchars($t('nav.modules', 'Модули'), ENT_QUOTES, 'UTF-8') ?></a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars('Microsoft Outlook Календарь', ENT_QUOTES, 'UTF-8') ?></li>
          </ol>
          <h1 class="crm-page-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="crm-subtitle"><?= htmlspecialchars('Двусторонняя синхронизация с календарем Microsoft 365 и Outlook через Microsoft Graph API.', ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="crm-page-actions d-flex gap-2">
          <button id="msoutRefreshBtn" class="btn crm-btn-secondary" type="button"><i class="fa-solid fa-arrows-rotate"></i> <?= htmlspecialchars($t('common.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
          <button id="msoutSaveBtn" class="btn crm-btn-primary" type="button"><i class="fa-solid fa-floppy-disk"></i> <?= htmlspecialchars($t('common.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </div>

      <div class="msout-grid">
        <!-- Hero Panel -->
        <section class="crm-card msout-panel msout-hero">
          <div class="msout-hero-icon"><i class="fa-brands fa-microsoft"></i></div>
          <div class="flex-grow-1">
            <h4 class="mb-1">Синхронизация с Microsoft Outlook</h4>
            <p class="mb-0 text-muted">Синхронизируйте события, встречи с клиентами и дедлайны задач между CRM и корпоративным календарем Microsoft 365.</p>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success-subtle text-success px-3 py-2 border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i> Активен</span>
          </div>
        </section>

        <!-- Credentials / Settings Panel -->
        <section class="crm-card msout-panel">
          <div class="crm-card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fa-solid fa-sliders me-2 text-primary"></i>Параметры интеграции</h5>
            <span class="badge bg-light text-muted border">Авторизация</span>
          </div>
          <div class="crm-card-body">
            <form id="msoutConfigForm" class="vstack gap-3" autocomplete="off">
            <div>
              <label class="form-label mb-1" for="msout_tenant_id">Azure Tenant ID</label>
              <input id="msout_tenant_id" class="form-control" type="text" placeholder="common или 365-tenant-id" autocomplete="off">
            </div>
            <div>
              <label class="form-label mb-1" for="msout_client_id">Application (Client) ID</label>
              <input id="msout_client_id" class="form-control" type="text" placeholder="00000000-0000-0000-0000-000000000000" autocomplete="off">
            </div>
            <div>
              <label class="form-label mb-1" for="msout_client_secret">Client Secret Value</label>
              <input id="msout_client_secret" class="form-control" type="password" placeholder="Value секрета из Azure Portal" autocomplete="off">
            </div>

              <div class="d-flex align-items-center gap-2 pt-2">
                <button class="btn crm-btn-primary" type="submit"><i class="fa-solid fa-link"></i> Подключить и сохранить</button>
                <button id="msoutTestBtn" class="btn crm-btn-secondary" type="button"><i class="fa-solid fa-plug-circle-check"></i> Проверить связь</button>
              </div>
            </form>
            <div id="msoutMessage" class="small mt-3" role="status"></div>
          </div>
        </section>

        <!-- Operational Status Panel -->
        <section class="crm-card msout-panel">
          <div class="crm-card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fa-solid fa-signal me-2 text-primary"></i>Состояние и синхронизация</h5>
            <span id="msoutLiveBadge" class="badge bg-success-subtle text-success border border-success-subtle">Online</span>
          </div>
          <div class="crm-card-body">
            <div id="msoutStatusBox" class="msout-status-box mb-3 p-3 rounded bg-light border">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <strong class="d-block text-dark">Статус шлюза: Подключено</strong>
                  <span class="small text-muted" id="msoutStatusMeta">Последняя проверка: только что &middot; Ошибок нет</span>
                </div>
                <button id="msoutQuickActionBtn" class="btn btn-sm crm-btn-secondary" type="button"><i class="fa-solid fa-bolt me-1"></i> Тестовый вызов</button>
              </div>
            </div>
            <h6 class="text-uppercase text-muted fw-semibold small mb-2">Возможности конфигурации:</h6>
            <ul class="list-unstyled mb-0 small text-secondary">
              <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i>Двусторонняя передача событий между TropaTT CRM и Microsoft Outlook</li>
              <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i>Поддержка встреч Microsoft Teams с автоматическим формированием ссылок</li>
              <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i>Учет статуса занятости (Busy/Free) при назначении задач коллегам</li>
              <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i>Защита от дубликатов на основе стабильных iCalUID Microsoft Graph</li>
            </ul>
          </div>
        </section>

        <!-- How it works Panel -->
        <section class="crm-card msout-panel">
          <div class="crm-card-header">
            <h5 class="mb-0"><i class="fa-solid fa-shield-halved me-2 text-primary"></i>Архитектура и безопасность</h5>
          </div>
          <div class="crm-card-body">
            <ul class="ps-3 mb-3 small text-secondary">
              <li class="mb-2">Интеграция работает через Microsoft Graph API v1.0.</li>
              <li class="mb-2">Cron-воркер каждые 15 минут опрашивает дельты изменений через delta-токены.</li>
              <li class="mb-2">Токены авторизации шифруются в локальной БД.</li>
            </ul>
            <div class="alert alert-light border small mb-0 text-muted">
              <i class="fa-solid fa-lock me-1 text-primary"></i> Все ключи доступа и учетные данные шифруются на сервере TropaTT CRM и никогда не передаются в открытом виде через клиентский API.
            </div>
          </div>
        </section>
      </div>
    </main>
  </div>
</div>
