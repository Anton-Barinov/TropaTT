<?php declare(strict_types=1); ?>
<?php $title = $title ?? 'Хранилища S3 & WebDAV'; ?>
<body data-page="module-storage-connectors" data-protected="1">
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
            <li class="breadcrumb-item active"><?= htmlspecialchars('Коннекторы Хранилищ', ENT_QUOTES, 'UTF-8') ?></li>
          </ol>
          <h1 class="crm-page-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="crm-subtitle"><?= htmlspecialchars('Универсальное подключение внешних хранилищ: Amazon S3, MinIO, Nextcloud, OwnCloud, Яндекс Object Storage, Selectel.', ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="crm-page-actions d-flex gap-2">
          <button id="stconRefreshBtn" class="btn crm-btn-secondary" type="button"><i class="fa-solid fa-arrows-rotate"></i> <?= htmlspecialchars($t('common.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
          <button id="stconSaveBtn" class="btn crm-btn-primary" type="button"><i class="fa-solid fa-floppy-disk"></i> <?= htmlspecialchars($t('common.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </div>

      <div class="stcon-grid">
        <!-- Hero Panel -->
        <section class="crm-card stcon-panel stcon-hero">
          <div class="stcon-hero-icon"><i class="fa-solid fa-server"></i></div>
          <div class="flex-grow-1">
            <h4 class="mb-1">Подключите S3 / WebDAV хранилище</h4>
            <p class="mb-0 text-muted">Храните гигабайты файлов задач и резервных копий во внешних масштабируемых объектных хранилищах с экономией диска сервера.</p>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success-subtle text-success px-3 py-2 border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i> Активен</span>
          </div>
        </section>

        <!-- Credentials / Settings Panel -->
        <section class="crm-card stcon-panel">
          <div class="crm-card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fa-solid fa-sliders me-2 text-primary"></i>Параметры интеграции</h5>
            <span class="badge bg-light text-muted border">Авторизация</span>
          </div>
          <div class="crm-card-body">
            <form id="stconConfigForm" class="vstack gap-3" autocomplete="off">
            <div>
              <label class="form-label mb-1" for="stcon_driver">Тип хранилища</label>
              <input id="stcon_driver" class="form-control" type="text" placeholder="s3 / webdav / nextcloud" autocomplete="off">
            </div>
            <div>
              <label class="form-label mb-1" for="stcon_endpoint">Endpoint URL</label>
              <input id="stcon_endpoint" class="form-control" type="text" placeholder="https://s3.storage.selcloud.ru" autocomplete="off">
            </div>
            <div>
              <label class="form-label mb-1" for="stcon_bucket">Bucket / Имя корзины</label>
              <input id="stcon_bucket" class="form-control" type="text" placeholder="crm-company-files" autocomplete="off">
            </div>
            <div>
              <label class="form-label mb-1" for="stcon_access_key">Access Key ID</label>
              <input id="stcon_access_key" class="form-control" type="text" placeholder="Key ID" autocomplete="off">
            </div>
            <div>
              <label class="form-label mb-1" for="stcon_secret_key">Secret Access Key</label>
              <input id="stcon_secret_key" class="form-control" type="password" placeholder="Secret Key" autocomplete="off">
            </div>

              <div class="d-flex align-items-center gap-2 pt-2">
                <button class="btn crm-btn-primary" type="submit"><i class="fa-solid fa-link"></i> Подключить и сохранить</button>
                <button id="stconTestBtn" class="btn crm-btn-secondary" type="button"><i class="fa-solid fa-plug-circle-check"></i> Проверить связь</button>
              </div>
            </form>
            <div id="stconMessage" class="small mt-3" role="status"></div>
          </div>
        </section>

        <!-- Operational Status Panel -->
        <section class="crm-card stcon-panel">
          <div class="crm-card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fa-solid fa-signal me-2 text-primary"></i>Состояние и синхронизация</h5>
            <span id="stconLiveBadge" class="badge bg-success-subtle text-success border border-success-subtle">Online</span>
          </div>
          <div class="crm-card-body">
            <div id="stconStatusBox" class="stcon-status-box mb-3 p-3 rounded bg-light border">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <strong class="d-block text-dark">Статус шлюза: Подключено</strong>
                  <span class="small text-muted" id="stconStatusMeta">Последняя проверка: только что &middot; Ошибок нет</span>
                </div>
                <button id="stconQuickActionBtn" class="btn btn-sm crm-btn-secondary" type="button"><i class="fa-solid fa-bolt me-1"></i> Тестовый вызов</button>
              </div>
            </div>
            <h6 class="text-uppercase text-muted fw-semibold small mb-2">Возможности конфигурации:</h6>
            <ul class="list-unstyled mb-0 small text-secondary">
              <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i>Совместимость со стандартным протоколом Amazon S3 API и WebDAV</li>
              <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i>Проверка соединения и тестовая запись в один клик</li>
              <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i>Фоновая пакетная миграция уже существующих локальных файлов CRM</li>
              <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i>Шифрование ключей доступа в БД CRM</li>
            </ul>
          </div>
        </section>

        <!-- How it works Panel -->
        <section class="crm-card stcon-panel">
          <div class="crm-card-header">
            <h5 class="mb-0"><i class="fa-solid fa-shield-halved me-2 text-primary"></i>Архитектура и безопасность</h5>
          </div>
          <div class="crm-card-body">
            <ul class="ps-3 mb-3 small text-secondary">
              <li class="mb-2">CRM направляет запросы на чтение и запись через нативный S3 V4 Signature драйвер.</li>
              <li class="mb-2">Поддерживается преподписанная генерация прямых ссылок (Pre-signed URLs).</li>
              <li class="mb-2">Локальный диск освобождается по мере переноса архивов.</li>
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
