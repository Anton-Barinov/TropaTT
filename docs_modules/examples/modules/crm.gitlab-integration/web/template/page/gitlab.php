<body data-page="module-gitlab-integration" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page">

    <div class="crm-page-head">
        <div>
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="nav.admin"><?= htmlspecialchars($t('nav.admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li>
                <li class="breadcrumb-item active">Интеграция с GitLab</li>
            </ol>
            <h1 class="crm-page-title">Интеграция с GitLab</h1>
            <p class="crm-subtitle">Синхронизация Merge Requests GitLab с задачами TropaTT (включая self-managed GitLab)</p>
        </div>
    </div>

    <div class="crm-card mb-3">
        <div class="crm-card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Подключения</h5>
            <button class="btn crm-btn-primary" id="addConnectionBtn"><i class="fa-solid fa-plus"></i> Добавить подключение</button>
        </div>
        <div class="crm-card-body">
            <div id="connectionsList"><div class="text-muted py-3">Загрузка...</div></div>
        </div>
    </div>

    <div class="crm-card">
        <div class="crm-card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Связанные проекты</h5>
            <button class="btn crm-btn-secondary" id="addLinkBtn"><i class="fa-solid fa-plus"></i> Связать проект</button>
        </div>
        <div class="crm-card-body">
            <p class="text-muted small">Связь «проект GitLab → проект TropaTT». Merge Request проекта становятся задачами выбранного проекта TropaTT.</p>
            <div id="linksList"><div class="text-muted py-3">Загрузка...</div></div>
        </div>
    </div>

    <div class="crm-card mt-3">
        <div class="crm-card-header"><h5 class="mb-0">Журнал синхронизации</h5></div>
        <div class="crm-card-body">
            <div id="logsList"><div class="text-muted py-3">Выберите связь, чтобы увидеть журнал.</div></div>
        </div>
    </div>

</main></div></div>

<!-- Connection modal -->
<div class="modal fade" id="connectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Новое подключение</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Название</label>
                    <input type="text" class="form-control" id="connName" placeholder="Компания / группа">
                </div>
                <div class="mb-3">
                    <label class="form-label">API Base URL</label>
                    <input type="url" class="form-control" id="connBaseUrl" value="https://gitlab.com/api/v4">
                    <div class="form-text">GitLab.com — <code>https://gitlab.com/api/v4</code>. Для self-managed укажите адрес API (например <code>https://gitlab.example.com/api/v4</code>).</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Personal Access Token</label>
                    <input type="password" class="form-control" id="connToken" placeholder="glpat-...">
                    <div class="form-text">Токен с правами <code>read_api</code> (или <code>api</code>). Хранится в зашифрованном виде.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button class="btn crm-btn-primary" id="saveConnectionBtn">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<!-- Link modal -->
<div class="modal fade" id="linkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Связать проект</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Подключение</label>
                    <select class="form-select" id="linkConnection"></select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Проект GitLab</label>
                    <select class="form-select" id="linkProjectPath"></select>
                    <div class="form-text">Загружается из GitLab. Если список пуст — проверьте права токена.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Проект TropaTT</label>
                    <select class="form-select" id="linkCrmProject"></select>
                    <div class="form-text">MR будут создаваться в выбранном проекте TropaTT.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button class="btn crm-btn-primary" id="saveLinkBtn">Связать</button>
            </div>
        </div>
    </div>
</div>

<!-- Webhook result modal -->
<div class="modal fade" id="webhookModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Webhook создан</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Скопируйте значения в настройки проекта GitLab → Settings → Webhooks.</p>
                <div class="mb-3">
                    <label class="form-label">URL</label>
                    <input type="text" class="form-control" id="webhookUrl" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Secret Token</label>
                    <input type="text" class="form-control" id="webhookSecret" readonly>
                    <div class="form-text">Секрет показывается только один раз. Сохраните его до закрытия окна.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Триггеры</label>
                    <input type="text" class="form-control" value="Merge Request events, Note events" readonly>
                </div>
                <div class="alert alert-info small mb-0">Если публичный webhook недоступен на вашем хостинге, синхронизация будет работать через cron-опрос.</div>
            </div>
            <div class="modal-footer">
                <button class="btn crm-btn-primary" data-bs-dismiss="modal">Готово</button>
            </div>
        </div>
    </div>
</div>
</body>
