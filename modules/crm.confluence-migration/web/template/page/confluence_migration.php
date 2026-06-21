<body data-page="module-confluence-migration" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page">

    <div class="crm-page-head">
        <div>
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="nav.admin"><?= htmlspecialchars($t('nav.admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li>
                <li class="breadcrumb-item active" data-i18n="confluence_migration.title"><?= htmlspecialchars($t('confluence_migration.title', 'Миграция из Confluence'), ENT_QUOTES, 'UTF-8') ?></li>
            </ol>
            <h1 class="crm-page-title" data-i18n="confluence_migration.title"><?= htmlspecialchars($t('confluence_migration.title', 'Миграция из Confluence'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="crm-subtitle" data-i18n="confluence_migration.description"><?= htmlspecialchars($t('confluence_migration.description', 'Перенос пространств, страниц и вложений из Confluence Cloud в базу знаний TropaTT'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>

    <div id="confluenceMigrationApp">
        <!-- Step indicator -->
        <div class="crm-card mb-4">
            <div class="crm-card-body">
                <ul class="nav nav-pills nav-fill" id="migrationSteps">
                    <li class="nav-item">
                        <a class="nav-link active" data-step="connections" href="#">
                            <span class="step-number">1</span>
                            <?= $t('confluence_migration.step_connections', 'Подключение') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" data-step="source" href="#">
                            <span class="step-number">2</span>
                            <?= $t('confluence_migration.step_source', 'Источник') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" data-step="settings" href="#">
                            <span class="step-number">3</span>
                            <?= $t('confluence_migration.step_settings', 'Настройки') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" data-step="mappings" href="#">
                            <span class="step-number">4</span>
                            <?= $t('confluence_migration.step_mappings', 'Пользователи') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" data-step="preview" href="#">
                            <span class="step-number">5</span>
                            <?= $t('confluence_migration.step_preview', 'Предпросмотр') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" data-step="run" href="#">
                            <span class="step-number">6</span>
                            <?= $t('confluence_migration.step_run', 'Выполнение') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" data-step="report" href="#">
                            <span class="step-number">7</span>
                            <?= $t('confluence_migration.step_report', 'Отчет') ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Step 1: Connections -->
        <div id="step-connections" class="migration-step">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="confluence_migration.connections_title"><?= htmlspecialchars($t('confluence_migration.connections_title', 'Подключения к Confluence'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body">
                    <div id="connectionsList" class="mb-3">
                        <div class="text-muted py-3"><?= $t('confluence_migration.loading', 'Загрузка...') ?></div>
                    </div>
                    <button class="crm-btn-primary" id="addConnectionBtn">
                        <i class="fa-solid fa-plus"></i>
                        <?= $t('confluence_migration.add_connection', 'Добавить подключение') ?>
                    </button>
                </div>
            </div>

            <!-- Connection form modal -->
            <div class="modal fade" id="connectionModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="connectionModalTitle"><?= $t('confluence_migration.new_connection', 'Новое подключение') ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label"><?= $t('confluence_migration.connection_name', 'Название подключения') ?></label>
                                <input type="text" class="form-control" id="connName" placeholder="Ringme Confluence">
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= $t('confluence_migration.base_url', 'Confluence Base URL') ?></label>
                                <input type="url" class="form-control" id="connBaseUrl" placeholder="https://your-domain.atlassian.net/wiki">
                                <small class="text-muted"><?= $t('confluence_migration.base_url_hint', 'URL вида https://your-domain.atlassian.net/wiki') ?></small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= $t('confluence_migration.email', 'Email') ?></label>
                                <input type="email" class="form-control" id="connEmail" placeholder="user@example.com">
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= $t('confluence_migration.api_token', 'API Token') ?></label>
                                <input type="password" class="form-control" id="connApiToken" placeholder="">
                                <small class="text-muted">
                                    <?= $t('confluence_migration.token_hint', 'Создайте API токен в https://id.atlassian.com/manage/api-tokens') ?>
                                </small>
                            </div>
                            <div id="connectionTestResult" class="d-none"></div>
                        </div>
                        <div class="modal-footer">
                            <button class="crm-btn-secondary" id="testConnectionBtn">
                                <?= $t('confluence_migration.test_connection', 'Проверить подключение') ?>
                            </button>
                            <button class="crm-btn-primary" id="saveConnectionBtn">
                                <?= $t('confluence_migration.save_connection', 'Сохранить') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end mt-3">
                <button class="crm-btn-primary" id="toSourceBtn" style="display:none">
                    <?= $t('confluence_migration.next', 'Далее') ?> <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- Step 2: Source selection -->
        <div id="step-source" class="migration-step d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="confluence_migration.select_spaces"><?= htmlspecialchars($t('confluence_migration.select_spaces', 'Выберите пространства для переноса'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="spaceSearch" placeholder="<?= htmlspecialchars($t('confluence_migration.search_spaces', 'Поиск пространств...'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div id="spacesList" class="list-group">
                        <div class="text-muted py-3"><?= $t('confluence_migration.loading', 'Загрузка...') ?></div>
                    </div>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="crm-btn-secondary me-2" id="backToConnectionsBtn">
                    <i class="fa-solid fa-arrow-left"></i> <?= $t('confluence_migration.back', 'Назад') ?>
                </button>
                <button class="crm-btn-primary" id="toSettingsBtn" disabled>
                    <?= $t('confluence_migration.next', 'Далее') ?> <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- Step 3: Settings -->
        <div id="step-settings" class="migration-step d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="confluence_migration.import_settings"><?= htmlspecialchars($t('confluence_migration.import_settings', 'Настройки переноса'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body">
                    <div class="mb-3">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" id="optImportAttachments" checked>
                            <?= $t('confluence_migration.opt_attachments', 'Переносить вложения') ?>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" id="optImportComments" checked>
                            <?= $t('confluence_migration.opt_comments', 'Переносить комментарии') ?>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" id="optImportLabels" checked>
                            <?= $t('confluence_migration.opt_labels', 'Переносить labels как теги') ?>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" id="optPublishPages">
                            <?= $t('confluence_migration.opt_publish', 'Публиковать страницы сразу') ?>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" data-i18n="confluence_migration.duplicate_mode"><?= htmlspecialchars($t('confluence_migration.duplicate_mode', 'Режим обработки дублей'), ENT_QUOTES, 'UTF-8') ?></label>
                        <select class="form-select" id="optDuplicateMode">
                            <option value="skip_existing"><?= $t('confluence_migration.dup_skip', 'Пропустить существующие') ?></option>
                            <option value="update_existing"><?= $t('confluence_migration.dup_update', 'Обновить по source_id') ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" data-i18n="confluence_migration.unsupported_macros"><?= htmlspecialchars($t('confluence_migration.unsupported_macros', 'Неподдерживаемые макросы'), ENT_QUOTES, 'UTF-8') ?></label>
                        <select class="form-select" id="optMacroHandling">
                            <option value="placeholder"><?= $t('confluence_migration.macro_placeholder', 'Оставить placeholder') ?></option>
                            <option value="remove"><?= $t('confluence_migration.macro_remove', 'Удалить') ?></option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="crm-btn-secondary me-2" id="backToSourceBtn">
                    <i class="fa-solid fa-arrow-left"></i> <?= $t('confluence_migration.back', 'Назад') ?>
                </button>
                <button class="crm-btn-primary" id="toMappingsBtn">
                    <?= $t('confluence_migration.next', 'Далее') ?> <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- Step 4: Mappings -->
        <div id="step-mappings" class="migration-step d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="confluence_migration.mappings_title"><?= htmlspecialchars($t('confluence_migration.mappings_title', 'Сопоставление пользователей'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body">
                    <p class="text-muted" data-i18n="confluence_migration.mappings_desc"><?= htmlspecialchars($t('confluence_migration.mappings_desc', 'Сопоставьте пользователей Confluence с пользователями TropaTT. Несопоставленные пользователи будут отображаться как текст.'), ENT_QUOTES, 'UTF-8') ?></p>
                    <ul class="nav nav-tabs mb-3" id="mappingTabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-mapping-tab="users" href="#"><?= $t('confluence_migration.mappings_users', 'Пользователи') ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-mapping-tab="groups" href="#"><?= $t('confluence_migration.mappings_groups', 'Группы') ?></a>
                        </li>
                    </ul>
                    <div id="mappingsList">
                        <div class="text-muted py-3"><?= $t('confluence_migration.loading', 'Загрузка...') ?></div>
                    </div>
                    <div id="mappingsGroupsList" class="d-none">
                        <div class="text-muted py-3"><?= $t('confluence_migration.loading', 'Загрузка...') ?></div>
                    </div>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="crm-btn-secondary me-2" id="backToMappingsBtn">
                    <i class="fa-solid fa-arrow-left"></i> <?= $t('confluence_migration.back', 'Назад') ?>
                </button>
                <button class="crm-btn-primary" id="toPreviewFromMappingsBtn">
                    <?= $t('confluence_migration.next', 'Далее') ?> <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- Step 5: Preview -->
        <div id="step-preview" class="migration-step d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="confluence_migration.preview_title"><?= htmlspecialchars($t('confluence_migration.preview_title', 'Предпросмотр плана миграции'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body" id="previewContent">
                    <div class="text-muted py-3"><?= $t('confluence_migration.loading', 'Загрузка...') ?></div>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="crm-btn-secondary me-2" id="backToMappingsFromPreviewBtn">
                    <i class="fa-solid fa-arrow-left"></i> <?= $t('confluence_migration.back', 'Назад') ?>
                </button>
                <button class="crm-btn-primary" id="startImportBtn">
                    <i class="fa-solid fa-play"></i> <?= $t('confluence_migration.start_import', 'Запустить миграцию') ?>
                </button>
            </div>
        </div>

        <!-- Step 6: Execution -->
        <div id="step-run" class="migration-step d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="confluence_migration.execution_title"><?= htmlspecialchars($t('confluence_migration.execution_title', 'Выполнение миграции'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span id="currentStepLabel"><?= $t('confluence_migration.waiting', 'Ожидание...') ?></span>
                            <span id="progressPercent">0%</span>
                        </div>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <div class="text-muted small" data-i18n="confluence_migration.imported"><?= htmlspecialchars($t('confluence_migration.imported', 'Импортировано'), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="h4" id="statImported">0</div>
                        </div>
                        <div class="col">
                            <div class="text-muted small" data-i18n="confluence_migration.failed"><?= htmlspecialchars($t('confluence_migration.failed', 'Ошибок'), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="h4 text-danger" id="statFailed">0</div>
                        </div>
                        <div class="col">
                            <div class="text-muted small" data-i18n="confluence_migration.skipped"><?= htmlspecialchars($t('confluence_migration.skipped', 'Пропущено'), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="h4 text-warning" id="statSkipped">0</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <button class="crm-btn-secondary" id="pauseJobBtn">
                            <i class="fa-solid fa-pause"></i> <?= $t('confluence_migration.pause', 'Пауза') ?>
                        </button>
                        <button class="crm-btn-success" id="resumeJobBtn" style="display:none">
                            <i class="fa-solid fa-play"></i> <?= $t('confluence_migration.resume', 'Продолжить') ?>
                        </button>
                        <button class="crm-btn-danger-soft" id="cancelJobBtn">
                            <i class="fa-solid fa-stop"></i> <?= $t('confluence_migration.cancel', 'Отмена') ?>
                        </button>
                    </div>
                    <div>
                        <h6 data-i18n="confluence_migration.log"><?= htmlspecialchars($t('confluence_migration.log', 'Журнал'), ENT_QUOTES, 'UTF-8') ?></h6>
                        <div id="jobLog" class="bg-dark text-light p-3 rounded" style="max-height: 200px; overflow-y: auto; font-size: 12px; font-family: monospace;">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 7: Report -->
        <div id="step-report" class="migration-step d-none">
            <div class="crm-card mb-3">
                <div class="crm-card-header">
                    <h5 class="mb-0" data-i18n="confluence_migration.report_title"><?= htmlspecialchars($t('confluence_migration.report_title', 'Отчет о миграции'), ENT_QUOTES, 'UTF-8') ?></h5>
                </div>
                <div class="crm-card-body" id="reportContent">
                    <div class="text-muted py-3"><?= $t('confluence_migration.loading', 'Загрузка...') ?></div>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="crm-btn-secondary me-2" id="viewKnowledgeBtn">
                    <i class="fa-solid fa-book"></i> <?= $t('confluence_migration.open_knowledge', 'Открыть базу знаний') ?>
                </button>
                <button class="crm-btn-secondary me-2" id="retryFailedBtn">
                    <i class="fa-solid fa-rotate"></i> <?= $t('confluence_migration.retry_failed', 'Повторить ошибки') ?>
                </button>
                <button class="crm-btn-primary" id="newMigrationBtn">
                    <i class="fa-solid fa-plus"></i> <?= $t('confluence_migration.new_migration', 'Новая миграция') ?>
                </button>
            </div>
        </div>
    </div>

</main></div></div>
