<body data-page="module-linear-migration" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page">

    <div class="crm-page-head">
        <div>
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="nav.admin"><?= htmlspecialchars($t('nav.admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li>
                <li class="breadcrumb-item active">Миграция из Linear</li>
            </ol>
            <h1 class="crm-page-title">Миграция из Linear</h1>
            <p class="crm-subtitle">Перенос команд, проектов, задач, меток и комментариев из Linear в TropaTT</p>
        </div>
    </div>

    <div id="linearApp">
        <!-- Step 1: connections -->
        <div id="step-connections">
            <div class="crm-card mb-3">
                <div class="crm-card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Подключения к Linear</h5>
                    <button class="btn crm-btn-primary" id="addConnectionBtn"><i class="fa-solid fa-plus"></i> Добавить</button>
                </div>
                <div class="crm-card-body"><div id="connectionsList"><div class="text-muted py-3">Загрузка...</div></div></div>
            </div>
            <div class="text-end"><button class="btn crm-btn-primary" id="toSourceBtn" style="display:none">Далее <i class="fa-solid fa-arrow-right"></i></button></div>
        </div>

        <!-- Step 2: source -->
        <div id="step-source" class="d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header"><h5 class="mb-0">Выберите команды Linear</h5></div>
                <div class="crm-card-body">
                    <div id="teamsList"><div class="text-muted py-3">Загрузка...</div></div>
                </div>
            </div>
            <div class="text-end">
                <button class="btn crm-btn-secondary me-2" id="backToConnectionsBtn"><i class="fa-solid fa-arrow-left"></i> Назад</button>
                <button class="btn crm-btn-primary" id="toRunBtn" disabled>Далее <i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </div>

        <!-- Step 3: run -->
        <div id="step-run" class="d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Выполнение миграции</h5>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="optDryRun">
                        <label class="form-check-label" for="optDryRun">Пробный прогон (без записи)</label>
                    </div>
                </div>
                <div class="crm-card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span id="currentStepLabel">Ожидание...</span>
                            <span id="progressPercent">0%</span>
                        </div>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="mb-3 text-end">
                        <button class="btn crm-btn-success" id="startImportBtn"><i class="fa-solid fa-play"></i> Запустить</button>
                    </div>
                    <div>
                        <h6>Журнал</h6>
                        <div id="jobLog" class="bg-dark text-light p-3 rounded" style="max-height: 200px; overflow-y: auto; font-size: 12px; font-family: monospace;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</main></div></div>

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
                    <input type="text" class="form-control" id="connName" placeholder="Моя команда">
                </div>
                <div class="mb-3">
                    <label class="form-label">API-ключ (lin_api_...)</label>
                    <input type="password" class="form-control" id="connApiKey" placeholder="lin_api_...">
                    <div class="form-text">Linear → Settings → Security & access → Personal API keys.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button class="btn crm-btn-primary" id="saveConnectionBtn">Сохранить</button>
            </div>
        </div>
    </div>
</div>
</body>
