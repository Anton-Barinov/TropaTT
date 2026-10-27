<body data-page="module-notion-migration" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page">

    <div class="crm-page-head">
        <div>
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="nav.admin"><?= htmlspecialchars($t('nav.admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li>
                <li class="breadcrumb-item active" data-i18n="notion_migration.title"><?= htmlspecialchars($t('notion_migration.title', 'Миграция из Notion'), ENT_QUOTES, 'UTF-8') ?></li>
            </ol>
            <h1 class="crm-page-title" data-i18n="notion_migration.title"><?= htmlspecialchars($t('notion_migration.title', 'Миграция из Notion'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="crm-subtitle" data-i18n="notion_migration.description"><?= htmlspecialchars($t('notion_migration.description', 'Перенос страниц и баз данных Notion в базу знаний TropaTT'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>

    <div id="notionMigrationApp">
        <!-- Step indicator -->
        <div class="crm-card mb-4">
            <div class="crm-card-body">
                <ul class="nav nav-pills nav-fill" id="migrationSteps">
                    <li class="nav-item">
                        <a class="nav-link active" data-step="connections" href="#">
                            <span class="step-number">1</span>
                            <?= $t('notion_migration.step_connections', 'Подключение') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" data-step="objects" href="#">
                            <span class="step-number">2</span>
                            <?= $t('notion_migration.step_objects', 'Объекты') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" data-step="settings" href="#">
                            <span class="step-number">3</span>
                            <?= $t('notion_migration.step_settings', 'Настройки') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" data-step="run" href="#">
                            <span class="step-number">4</span>
                            <?= $t('notion_migration.step_run', 'Выполнение') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" data-step="report" href="#">
                            <span class="step-number">5</span>
                            <?= $t('notion_migration.step_report', 'Отчет') ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Step 1: Connections -->
        <div id="step-connections" class="migration-step">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="notion_migration.connections_title"><?= htmlspecialchars($t('notion_migration.connections_title', 'Подключения к Notion'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body">
                    <div id="connectionsList" class="mb-3">
                        <div class="text-muted py-3"><?= $t('notion_migration.loading', 'Загрузка...') ?></div>
                    </div>
                    <button class="btn crm-btn-primary" id="addConnectionBtn">
                        <i class="fa-solid fa-plus"></i>
                        <?= $t('notion_migration.add_connection', 'Добавить подключение') ?>
                    </button>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="btn crm-btn-primary" id="toObjectsBtn" style="display:none">
                    <?= $t('notion_migration.next', 'Далее') ?> <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- Step 2: Object selection -->
        <div id="step-objects" class="migration-step d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="notion_migration.select_objects"><?= htmlspecialchars($t('notion_migration.select_objects', 'Выберите страницы и базы данных'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="objectSearch" placeholder="<?= htmlspecialchars($t('notion_migration.search_objects', 'Поиск объектов...'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div id="objectsList" class="list-group">
                        <div class="text-muted py-3"><?= $t('notion_migration.loading', 'Загрузка...') ?></div>
                    </div>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="btn crm-btn-secondary me-2" id="backToConnectionsBtn">
                    <i class="fa-solid fa-arrow-left"></i> <?= $t('notion_migration.back', 'Назад') ?>
                </button>
                <button class="btn crm-btn-primary" id="toSettingsBtn" disabled>
                    <?= $t('notion_migration.next', 'Далее') ?> <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- Step 3: Settings -->
        <div id="step-settings" class="migration-step d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="notion_migration.import_settings"><?= htmlspecialchars($t('notion_migration.import_settings', 'Настройки переноса'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body">
                    <div class="mb-3">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" id="optIncludeComments" checked>
                            <?= $t('notion_migration.opt_comments', 'Переносить комментарии') ?>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" id="optPublishPages">
                            <?= $t('notion_migration.opt_publish', 'Публиковать страницы сразу') ?>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" id="optDryRun">
                            <?= $t('notion_migration.opt_dry_run', 'Тестовый прогон без импорта (dry run)') ?>
                        </label>
                    </div>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="btn crm-btn-secondary me-2" id="backToObjectsBtn">
                    <i class="fa-solid fa-arrow-left"></i> <?= $t('notion_migration.back', 'Назад') ?>
                </button>
                <button class="btn crm-btn-primary" id="startImportBtn">
                    <i class="fa-solid fa-play"></i> <?= $t('notion_migration.start_import', 'Запустить') ?>
                </button>
            </div>
        </div>

        <!-- Step 4: Execution -->
        <div id="step-run" class="migration-step d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="notion_migration.execution_title"><?= htmlspecialchars($t('notion_migration.execution_title', 'Выполнение миграции'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span id="currentStepLabel"><?= $t('notion_migration.waiting', 'Ожидание...') ?></span>
                            <span id="progressPercent">0%</span>
                        </div>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <div class="text-muted small" data-i18n="notion_migration.imported"><?= htmlspecialchars($t('notion_migration.imported', 'Импортировано'), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="h4" id="statImported">0</div>
                        </div>
                        <div class="col">
                            <div class="text-muted small" data-i18n="notion_migration.failed"><?= htmlspecialchars($t('notion_migration.failed', 'Ошибок'), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="h4 text-danger" id="statFailed">0</div>
                        </div>
                        <div class="col">
                            <div class="text-muted small" data-i18n="notion_migration.skipped"><?= htmlspecialchars($t('notion_migration.skipped', 'Пропущено'), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="h4 text-warning" id="statSkipped">0</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <button class="btn crm-btn-danger-soft" id="cancelJobBtn">
                            <i class="fa-solid fa-stop"></i> <?= $t('notion_migration.cancel', 'Отмена') ?>
                        </button>
                    </div>
                    <div>
                        <h6 data-i18n="notion_migration.log"><?= htmlspecialchars($t('notion_migration.log', 'Журнал'), ENT_QUOTES, 'UTF-8') ?></h6>
                        <div id="jobLog" class="bg-dark text-light p-3 rounded" style="max-height: 200px; overflow-y: auto; font-size: 12px; font-family: monospace;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 5: Report -->
        <div id="step-report" class="migration-step d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="notion_migration.report_title"><?= htmlspecialchars($t('notion_migration.report_title', 'Отчет о миграции'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body" id="reportContent">
                    <div class="text-muted py-3"><?= $t('notion_migration.loading', 'Загрузка...') ?></div>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="btn crm-btn-secondary me-2" id="viewKnowledgeBtn">
                    <i class="fa-solid fa-book"></i> <?= $t('notion_migration.open_knowledge', 'Открыть базу знаний') ?>
                </button>
                <button class="btn crm-btn-secondary me-2" id="retryFailedBtn">
                    <i class="fa-solid fa-rotate"></i> <?= $t('notion_migration.retry_failed', 'Повторить ошибки') ?>
                </button>
                <button class="btn crm-btn-primary" id="newMigrationBtn">
                    <i class="fa-solid fa-plus"></i> <?= $t('notion_migration.new_migration', 'Новая миграция') ?>
                </button>
            </div>
        </div>
    </div>

</main></div></div>

<!-- Modal -->
<div class="modal fade" id="connectionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="connectionModalTitle"><?= $t('notion_migration.new_connection', 'Новое подключение') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label"><?= $t('notion_migration.connection_name', 'Название подключения') ?></label>
                    <input type="text" class="form-control" id="connName" placeholder="Мой Notion workspace">
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= $t('notion_migration.token', 'Integration token') ?></label>
                    <input type="password" class="form-control" id="connToken" placeholder="secret_...">
                    <small class="text-muted">
                        <?= $t('notion_migration.token_hint', 'Создайте internal integration на notion.so/my-integrations и подключите к нему нужные страницы') ?>
                    </small>
                </div>
                <div id="connectionTestResult" class="d-none"></div>
            </div>
            <div class="modal-footer">
                <button class="btn crm-btn-secondary" id="testConnectionBtn">
                    <?= $t('notion_migration.test_connection', 'Проверить подключение') ?>
                </button>
                <button class="btn crm-btn-primary" id="saveConnectionBtn">
                    <?= $t('notion_migration.save_connection', 'Сохранить') ?>
                </button>
            </div>
        </div>
    </div>
</div>
