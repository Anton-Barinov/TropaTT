<body data-page="module-trello-migration" data-protected="1">
<div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page">
    <div class="crm-page-head"><div>
        <ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin"><?= htmlspecialchars($t('nav.admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active">Миграция из Trello</li></ol>
        <h1 class="crm-page-title">Миграция из Trello</h1>
        <p class="crm-subtitle">Перенос досок, списков, карточек, меток, чек-листов, комментариев и вложений в TropaTT CRM.</p>
    </div></div>

    <div id="trelloMigrationApp" class="trello-migration-grid">
        <section class="crm-card trello-panel">
            <div class="crm-card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">Подключение Trello</h5><button class="btn crm-btn-primary btn-sm" id="trelloAddConnection"><i class="fa-solid fa-plus"></i> Добавить</button></div>
            <div class="crm-card-body"><div id="trelloConnections" class="trello-list-state">Загрузка подключений…</div></div>
        </section>

        <section class="crm-card trello-panel trello-source-panel">
            <div class="crm-card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">Источник</h5><button class="btn crm-btn-secondary btn-sm" id="trelloDiscover" disabled><i class="fa-solid fa-rotate"></i> Обновить доски</button></div>
            <div class="crm-card-body"><div id="trelloBoards" class="trello-list-state">Выберите подключение.</div><div id="trelloBoardOptions" class="mt-3 d-none"></div></div>
        </section>

        <section class="crm-card trello-panel trello-settings-panel">
            <div class="crm-card-header"><h5 class="mb-0">Настройки миграции</h5></div>
            <div class="crm-card-body">
                <label class="form-label">Режим</label><select id="trelloMode" class="form-select mb-3"><option value="import">Однократный импорт</option><option value="sync">Импорт с последующей синхронизацией</option><option value="dry_run">Только предпросмотр</option></select>
                <label class="form-label">Списки Trello</label><select id="trelloListMode" class="form-select mb-3"><option value="status">Списки как статусы задач</option><option value="module">Списки как модули проекта</option><option value="ignore">Не переносить списки</option></select>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="trelloAttachments"><label class="form-check-label" for="trelloAttachments">Скачивать вложения</label></div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="trelloArchived" checked><label class="form-check-label" for="trelloArchived">Включать архивные карточки и списки</label></div>
                <button class="btn crm-btn-primary w-100" id="trelloStart" disabled><i class="fa-solid fa-play"></i> Создать и запустить job</button>
                <div id="trelloActionMessage" class="small mt-3" role="status"></div>
            </div>
        </section>

        <section class="crm-card trello-panel trello-job-panel">
            <div class="crm-card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">Выполнение</h5><button class="btn crm-btn-secondary btn-sm" id="trelloRefreshJobs"><i class="fa-solid fa-rotate"></i></button></div>
            <div class="crm-card-body"><div id="trelloJobs" class="trello-list-state">Загрузка job…</div><div id="trelloProgress" class="d-none mt-3"></div></div>
        </section>
    </div>
</main></div></div>

<div class="modal fade" id="trelloConnectionModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="trelloConnectionForm">
    <div class="modal-header"><h5 class="modal-title">Новое подключение Trello</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>
    <div class="modal-body"><p class="text-muted small">Используйте API key приложения и User token с доступом к нужным доскам. Секрет приложения нужен только для HMAC-проверки webhook.</p>
        <label class="form-label">Название</label><input class="form-control mb-3" name="name" maxlength="255" required placeholder="Рабочий Trello">
        <label class="form-label">API key</label><input class="form-control mb-3" name="api_key" required autocomplete="off">
        <label class="form-label">User token</label><input class="form-control mb-3" name="token" required autocomplete="off">
        <label class="form-label">Секрет приложения <span class="text-muted">(необязательно)</span></label><input class="form-control" name="api_secret" type="password" autocomplete="new-password">
        <div id="trelloConnectionError" class="alert alert-danger d-none mt-3"></div>
    </div><div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit">Сохранить и проверить</button></div>
</form></div></div></div>
