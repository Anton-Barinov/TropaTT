<body data-page="module-jira-migration" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page">

    <div class="crm-page-head">
        <div>
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="nav.admin"><?= htmlspecialchars($t('nav.admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li>
                <li class="breadcrumb-item active" data-i18n="jira_migration.title"><?= htmlspecialchars($t('jira_migration.title', 'Миграция из Jira'), ENT_QUOTES, 'UTF-8') ?></li>
            </ol>
            <h1 class="crm-page-title" data-i18n="jira_migration.title"><?= htmlspecialchars($t('jira_migration.title', 'Миграция из Jira'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="crm-subtitle" data-i18n="jira_migration.description"><?= htmlspecialchars($t('jira_migration.description', 'Перенос проектов, задач и рабочих данных из Jira Cloud в TropaTT CRM'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>

    <div id="jiraMigrationApp">
        <!-- Step indicator -->
        <div class="crm-card mb-4">
            <div class="crm-card-body">
                <ul class="nav nav-pills nav-fill" id="migrationSteps">
                    <li class="nav-item">
                        <a class="nav-link active" data-step="connections" href="#">
                            <span class="step-number">1</span>
                            <?= $t('jira_migration.step_connections', 'Подключение') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" data-step="source" href="#">
                            <span class="step-number">2</span>
                            <?= $t('jira_migration.step_source', 'Источник') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" data-step="settings" href="#">
                            <span class="step-number">3</span>
                            <?= $t('jira_migration.step_settings', 'Настройки') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" data-step="mappings" href="#">
                            <span class="step-number">4</span>
                            <?= $t('jira_migration.step_mappings', 'Сопоставление') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" data-step="preview" href="#">
                            <span class="step-number">5</span>
                            <?= $t('jira_migration.step_preview', 'Предпросмотр') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" data-step="run" href="#">
                            <span class="step-number">6</span>
                            <?= $t('jira_migration.step_run', 'Выполнение') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" data-step="report" href="#">
                            <span class="step-number">7</span>
                            <?= $t('jira_migration.step_report', 'Отчет') ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Step 1: Connections -->
        <div id="step-connections" class="migration-step">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="jira_migration.connections_title"><?= htmlspecialchars($t('jira_migration.connections_title', 'Подключения к Jira'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body">
                    <div id="connectionsList" class="mb-3">
                        <div class="text-muted py-3"><?= $t('jira_migration.loading', 'Загрузка...') ?></div>
                    </div>
                    <button class="btn crm-btn-primary" id="addConnectionBtn">
                        <i class="fa-solid fa-plus"></i>
                        <?= $t('jira_migration.add_connection', 'Добавить подключение') ?>
                    </button>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="btn crm-btn-primary" id="toSourceBtn" style="display:none">
                    <?= $t('jira_migration.next', 'Далее') ?> <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- Step 2: Source selection -->
        <div id="step-source" class="migration-step d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="jira_migration.select_projects"><?= htmlspecialchars($t('jira_migration.select_projects', 'Выберите проекты Jira для переноса'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="projectSearch" placeholder="<?= htmlspecialchars($t('jira_migration.search_projects', 'Поиск проектов...'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div id="projectsList" class="list-group">
                        <div class="text-muted py-3"><?= $t('jira_migration.loading', 'Загрузка...') ?></div>
                    </div>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="btn crm-btn-secondary me-2" id="backToConnectionsBtn">
                    <i class="fa-solid fa-arrow-left"></i> <?= $t('jira_migration.back', 'Назад') ?>
                </button>
                <button class="btn crm-btn-primary" id="toSettingsBtn" disabled>
                    <?= $t('jira_migration.next', 'Далее') ?> <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- Step 3: Settings -->
        <div id="step-settings" class="migration-step d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="jira_migration.import_settings"><?= htmlspecialchars($t('jira_migration.import_settings', 'Настройки переноса'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body">
                    <div class="mb-3">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" id="optImportAttachments" checked>
                            <?= $t('jira_migration.opt_attachments', 'Переносить вложения') ?>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" id="optImportComments" checked>
                            <?= $t('jira_migration.opt_comments', 'Переносить комментарии') ?>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" id="optImportWorklogs" checked>
                            <?= $t('jira_migration.opt_worklogs', 'Переносить учет времени') ?>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" id="optImportSprints" checked>
                            <?= $t('jira_migration.opt_sprints', 'Переносить спринты как циклы') ?>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" id="optImportLinks" checked>
                            <?= $t('jira_migration.opt_links', 'Переносить связи задач') ?>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" id="optImportLabels" checked>
                            <?= $t('jira_migration.opt_labels', 'Переносить метки как теги') ?>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" data-i18n="jira_migration.duplicate_mode"><?= htmlspecialchars($t('jira_migration.duplicate_mode', 'Режим обработки дублей'), ENT_QUOTES, 'UTF-8') ?></label>
                        <select class="form-select" id="optDuplicateMode">
                            <option value="skip_existing"><?= $t('jira_migration.dup_skip', 'Пропустить существующие') ?></option>
                            <option value="update_existing"><?= $t('jira_migration.dup_update', 'Обновить по source_id') ?></option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="btn crm-btn-secondary me-2" id="backToSourceBtn">
                    <i class="fa-solid fa-arrow-left"></i> <?= $t('jira_migration.back', 'Назад') ?>
                </button>
                <button class="btn crm-btn-primary" id="toMappingsBtn">
                    <?= $t('jira_migration.next', 'Далее') ?> <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- Step 4: Mappings -->
        <div id="step-mappings" class="migration-step d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="jira_migration.mappings_title"><?= htmlspecialchars($t('jira_migration.mappings_title', 'Сопоставление сущностей'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body">
                    <p class="text-muted" data-i18n="jira_migration.mappings_desc"><?= htmlspecialchars($t('jira_migration.mappings_desc', 'Сопоставьте пользователей, статусы и приоритеты Jira с сущностями TropaTT. Несопоставленные пользователи будут отображаться как текст.'), ENT_QUOTES, 'UTF-8') ?></p>
                    <ul class="nav nav-tabs mb-3" id="mappingTabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-mapping-tab="users" href="#"><?= $t('jira_migration.mappings_users', 'Пользователи') ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-mapping-tab="statuses" href="#"><?= $t('jira_migration.mappings_statuses', 'Статусы') ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-mapping-tab="priorities" href="#"><?= $t('jira_migration.mappings_priorities', 'Приоритеты') ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-mapping-tab="issuetypes" href="#"><?= $t('jira_migration.mappings_issue_types', 'Типы задач') ?></a>
                        </li>
                    </ul>
                    <div id="mappingsContainer">
                        <div class="text-muted py-3"><?= $t('jira_migration.loading', 'Загрузка...') ?></div>
                    </div>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="btn crm-btn-secondary me-2" id="backToSettingsFromMappingsBtn">
                    <i class="fa-solid fa-arrow-left"></i> <?= $t('jira_migration.back', 'Назад') ?>
                </button>
                <button class="btn crm-btn-primary" id="toPreviewFromMappingsBtn">
                    <?= $t('jira_migration.next', 'Далее') ?> <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- Step 5: Preview -->
        <div id="step-preview" class="migration-step d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="jira_migration.preview_title"><?= htmlspecialchars($t('jira_migration.preview_title', 'Предпросмотр плана миграции'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body" id="previewContent">
                    <div class="text-muted py-3"><?= $t('jira_migration.loading', 'Загрузка...') ?></div>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="btn crm-btn-secondary me-2" id="backToSettingsBtn">
                    <i class="fa-solid fa-arrow-left"></i> <?= $t('jira_migration.back', 'Назад') ?>
                </button>
                <button class="btn crm-btn-primary" id="startImportBtn">
                    <i class="fa-solid fa-play"></i> <?= $t('jira_migration.start_import', 'Запустить миграцию') ?>
                </button>
            </div>
        </div>

        <!-- Step 5: Execution -->
        <div id="step-run" class="migration-step d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="jira_migration.execution_title"><?= htmlspecialchars($t('jira_migration.execution_title', 'Выполнение миграции'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span id="currentStepLabel"><?= $t('jira_migration.waiting', 'Ожидание...') ?></span>
                            <span id="progressPercent">0%</span>
                        </div>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <div class="text-muted small" data-i18n="jira_migration.imported"><?= htmlspecialchars($t('jira_migration.imported', 'Импортировано'), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="h4" id="statImported">0</div>
                        </div>
                        <div class="col">
                            <div class="text-muted small" data-i18n="jira_migration.failed"><?= htmlspecialchars($t('jira_migration.failed', 'Ошибок'), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="h4 text-danger" id="statFailed">0</div>
                        </div>
                        <div class="col">
                            <div class="text-muted small" data-i18n="jira_migration.skipped"><?= htmlspecialchars($t('jira_migration.skipped', 'Пропущено'), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="h4 text-warning" id="statSkipped">0</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <button class="btn crm-btn-secondary" id="pauseJobBtn">
                            <i class="fa-solid fa-pause"></i> <?= $t('jira_migration.pause', 'Пауза') ?>
                        </button>
                        <button class="btn crm-btn-success" id="resumeJobBtn" style="display:none">
                            <i class="fa-solid fa-play"></i> <?= $t('jira_migration.resume', 'Продолжить') ?>
                        </button>
                        <button class="btn crm-btn-danger-soft" id="cancelJobBtn">
                            <i class="fa-solid fa-stop"></i> <?= $t('jira_migration.cancel', 'Отмена') ?>
                        </button>
                    </div>
                    <div>
                        <h6 data-i18n="jira_migration.log"><?= htmlspecialchars($t('jira_migration.log', 'Журнал'), ENT_QUOTES, 'UTF-8') ?></h6>
                        <div id="jobLog" class="bg-dark text-light p-3 rounded" style="max-height: 200px; overflow-y: auto; font-size: 12px; font-family: monospace;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 6: Report -->
        <div id="step-report" class="migration-step d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="jira_migration.report_title"><?= htmlspecialchars($t('jira_migration.report_title', 'Отчет о миграции'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body" id="reportContent">
                    <div class="text-muted py-3"><?= $t('jira_migration.loading', 'Загрузка...') ?></div>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="btn crm-btn-secondary me-2" id="openProjectsBtn">
                    <i class="fa-solid fa-folder-open"></i> <?= $t('jira_migration.open_projects', 'Открыть проекты') ?>
                </button>
                <button class="btn crm-btn-secondary me-2" id="retryFailedBtn">
                    <i class="fa-solid fa-rotate"></i> <?= $t('jira_migration.retry_failed', 'Повторить ошибки') ?>
                </button>
                <button class="btn crm-btn-primary" id="newMigrationBtn">
                    <i class="fa-solid fa-plus"></i> <?= $t('jira_migration.new_migration', 'Новая миграция') ?>
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
                <h5 class="modal-title" id="connectionModalTitle"><?= $t('jira_migration.new_connection', 'Новое подключение') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label"><?= $t('jira_migration.connection_name', 'Название подключения') ?></label>
                    <input type="text" class="form-control" id="connName" placeholder="My Jira">
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= $t('jira_migration.site_url', 'Jira Site URL') ?></label>
                    <input type="url" class="form-control" id="connSiteUrl" placeholder="https://your-domain.atlassian.net">
                    <small class="text-muted"><?= $t('jira_migration.site_url_hint', 'URL вида https://your-domain.atlassian.net') ?></small>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= $t('jira_migration.email', 'Email') ?></label>
                    <input type="email" class="form-control" id="connEmail" placeholder="user@example.com">
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= $t('jira_migration.api_token', 'API Token') ?></label>
                    <input type="password" class="form-control" id="connApiToken" placeholder="">
                    <small class="text-muted">
                        <?= $t('jira_migration.token_hint', 'Создайте API токен в https://id.atlassian.com/manage/api-tokens') ?>
                    </small>
                </div>
                <div id="connectionTestResult" class="d-none"></div>
            </div>
            <div class="modal-footer">
                <button class="btn crm-btn-secondary" id="testConnectionBtn">
                    <?= $t('jira_migration.test_connection', 'Проверить подключение') ?>
                </button>
                <button class="btn crm-btn-primary" id="saveConnectionBtn">
                    <?= $t('jira_migration.save_connection', 'Сохранить') ?>
                </button>
            </div>
        </div>
    </div>
</div>
