<body data-page="module-github-integration" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page">

    <div class="crm-page-head">
        <div>
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="nav.admin"><?= htmlspecialchars($t('nav.admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li>
                <li class="breadcrumb-item active">Интеграция с GitHub</li>
            </ol>
            <h1 class="crm-page-title">Интеграция с GitHub</h1>
            <p class="crm-subtitle">Синхронизация issues и pull requests GitHub с задачами TropaTT (включая GitHub Enterprise Server)</p>
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
            <h5 class="mb-0">Связанные репозитории</h5>
            <button class="btn crm-btn-secondary" id="addLinkBtn"><i class="fa-solid fa-plus"></i> Связать репозиторий</button>
        </div>
        <div class="crm-card-body">
            <p class="text-muted small">Связь «репозиторий → проект TropaTT». Issue и pull request репозитория становятся задачами выбранного проекта. Обновления приходят через webhook или по cron-опросу.</p>
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
                    <input type="text" class="form-control" id="connName" placeholder="Компания / организация">
                </div>
                <div class="mb-3">
                    <label class="form-label">API Base URL</label>
                    <input type="url" class="form-control" id="connBaseUrl" value="https://api.github.com">
                    <div class="form-text">GitHub.com — <code>https://api.github.com</code>. Для GitHub Enterprise Server укажите адрес API (например <code>https://ghes.example.com/api/v3</code>).</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Personal Access Token</label>
                    <input type="password" class="form-control" id="connToken" placeholder="ghp_...">
                    <div class="form-text">Токен с правами <code>repo</code> (для чтения issues/PR). Хранится в зашифрованном виде.</div>
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
                <h5 class="modal-title">Связать репозиторий</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Подключение</label>
                    <select class="form-select" id="linkConnection"></select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Владелец (owner)</label>
                    <input type="text" class="form-control" id="linkOwner" placeholder="octocat">
                </div>
                <div class="mb-3">
                    <label class="form-label">Репозиторий (repo)</label>
                    <input type="text" class="form-control" id="linkRepo" placeholder="hello-world">
                </div>
                <div class="mb-3">
                    <label class="form-label">Проект TropaTT</label>
                    <select class="form-select" id="linkProject"></select>
                    <div class="form-text">Задачи из этого репозитория будут создаваться в выбранном проекте.</div>
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
                <p class="text-muted small">Скопируйте эти значения в настройки репозитория GitHub → Settings → Webhooks → Add webhook.</p>
                <div class="mb-3">
                    <label class="form-label">Payload URL</label>
                    <input type="text" class="form-control" id="webhookUrl" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Content type</label>
                    <input type="text" class="form-control" value="application/json" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Secret</label>
                    <input type="text" class="form-control" id="webhookSecret" readonly>
                    <div class="form-text">Секрет показывается только один раз. Сохраните его до закрытия окна.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">События (Events)</label>
                    <input type="text" class="form-control" value="issues, issue_comment, pull_request, pull_request_review" readonly>
                </div>
                <div class="alert alert-info small mb-0">Если публичный webhook недоступен на вашем хостинге, синхронизация всё равно будет работать через cron-опрос (раз в несколько минут).</div>
            </div>
            <div class="modal-footer">
                <button class="btn crm-btn-primary" data-bs-dismiss="modal">Готово</button>
            </div>
        </div>
    </div>
</div>
</body>
