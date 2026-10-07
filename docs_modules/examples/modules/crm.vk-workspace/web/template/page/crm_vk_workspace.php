<?php declare(strict_types=1); ?>
<?php $title = $title ?? 'VK WorkSpace'; ?>
<body data-page="module-vk-workspace" data-protected="1">
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
            <li class="breadcrumb-item active"><?= htmlspecialchars('VK WorkSpace', ENT_QUOTES, 'UTF-8') ?></li>
          </ol>
          <h1 class="crm-page-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="crm-subtitle"><?= htmlspecialchars('Интеграция с корпоративной экосистемой VK: видеовстречи VK Звонки, корпоративная почта VK WorkMail, файлы VK WorkDisk и мессенджер VK Teams.', ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="crm-page-actions d-flex gap-2">
          <button id="vkwsRefreshBtn" class="btn crm-btn-secondary" type="button"><i class="fa-solid fa-arrows-rotate"></i> <?= htmlspecialchars($t('common.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
          <button id="vkwsSaveBtn" class="btn crm-btn-primary" type="button"><i class="fa-solid fa-floppy-disk"></i> <?= htmlspecialchars($t('common.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </div>

      <div class="vkws-grid">
        <!-- Hero Panel -->
        <section class="crm-card vkws-panel vkws-hero">
          <div class="vkws-hero-icon"><i class="fa-brands fa-vk"></i></div>
          <div class="flex-grow-1">
            <h4 class="mb-1">Подключите экосистему VK WorkSpace</h4>
            <p class="mb-0 text-muted">Планируйте онлайн-встречи напрямую из карточек задач и календаря с мгновенной генерацией ссылок на VK Звонки и отправкой уведомлений в каналы VK Teams.</p>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success-subtle text-success px-3 py-2 border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i> Активен</span>
          </div>
        </section>

        <!-- Credentials / Settings Panel -->
        <section class="crm-card vkws-panel">
          <div class="crm-card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fa-solid fa-sliders me-2 text-primary"></i>Параметры интеграции</h5>
            <span class="badge bg-light text-muted border">Авторизация</span>
          </div>
          <div class="crm-card-body">
            <form id="vkwsConfigForm" class="vstack gap-3" autocomplete="off">
            <div>
              <label class="form-label mb-1" for="vkws_domain">Корпоративный домен VK WorkSpace</label>
              <input id="vkws_domain" class="form-control" type="text" placeholder="company.myteam.mail.ru" autocomplete="off">
            </div>
            <div>
              <label class="form-label mb-1" for="vkws_bot_token">VK Teams Bot Token</label>
              <input id="vkws_bot_token" class="form-control" type="password" placeholder="001.xxxx.xxxx:xxxx" autocomplete="off">
            </div>
            <div>
              <label class="form-label mb-1" for="vkws_calls_api_key">VK Calls API Key</label>
              <input id="vkws_calls_api_key" class="form-control" type="password" placeholder="vk_calls_sec_xxxx" autocomplete="off">
            </div>

              <div class="d-flex align-items-center gap-2 pt-2">
                <button class="btn crm-btn-primary" type="submit"><i class="fa-solid fa-link"></i> Подключить и сохранить</button>
                <button id="vkwsTestBtn" class="btn crm-btn-secondary" type="button"><i class="fa-solid fa-plug-circle-check"></i> Проверить связь</button>
              </div>
            </form>
            <div id="vkwsMessage" class="small mt-3" role="status"></div>
          </div>
        </section>

        <!-- Operational Status Panel -->
        <section class="crm-card vkws-panel">
          <div class="crm-card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fa-solid fa-signal me-2 text-primary"></i>Состояние и синхронизация</h5>
            <span id="vkwsLiveBadge" class="badge bg-success-subtle text-success border border-success-subtle">Online</span>
          </div>
          <div class="crm-card-body">
            <div id="vkwsStatusBox" class="vkws-status-box mb-3 p-3 rounded bg-light border">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <strong class="d-block text-dark">Статус шлюза: Подключено</strong>
                  <span class="small text-muted" id="vkwsStatusMeta">Последняя проверка: только что &middot; Ошибок нет</span>
                </div>
                <button id="vkwsQuickActionBtn" class="btn btn-sm crm-btn-secondary" type="button"><i class="fa-solid fa-bolt me-1"></i> Тестовый вызов</button>
              </div>
            </div>
            <h6 class="text-uppercase text-muted fw-semibold small mb-2">Возможности конфигурации:</h6>
            <ul class="list-unstyled mb-0 small text-secondary">
              <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i>Автоматическое создание видеозвонков VK при назначении встреч в CRM</li>
              <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i>Синхронизация корпоративного календаря с VK Calendar через CalDAV/API</li>
              <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i>Оповещения ответственных и команд в каналы VK Teams мессенджера</li>
              <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i>Прикрепление и обмен вложениями через облачное хранилище VK WorkDisk</li>
            </ul>
          </div>
        </section>

        <!-- How it works Panel -->
        <section class="crm-card vkws-panel">
          <div class="crm-card-header">
            <h5 class="mb-0"><i class="fa-solid fa-shield-halved me-2 text-primary"></i>Архитектура и безопасность</h5>
          </div>
          <div class="crm-card-body">
            <ul class="ps-3 mb-3 small text-secondary">
              <li class="mb-2">При сохранении встречи или онлайн-события CRM генерирует защищённую комнату VK Звонков.</li>
              <li class="mb-2">Ссылка на видеоконференцию автоматически добавляется в описание и карточку задачи.</li>
              <li class="mb-2">Участники встречи получают приглашение с кнопкой быстрого подключения.</li>
              <li class="mb-2">Все API-ключи и токены шифруются по стандарту AES-256 в базе данных CRM.</li>
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
