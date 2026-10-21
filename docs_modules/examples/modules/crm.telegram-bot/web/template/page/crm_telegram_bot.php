<?php declare(strict_types=1); ?>
<?php
/** @var array $data */
$titleRaw = (string)($data['title'] ?? 'Telegram Бот');
$title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
?>
<body data-page="module-telegram-bot" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-module-page"><?php crm_page_head([
    ['label' => $t('admin_module_detail.link_admin', 'Администрирование'), 'href' => 'index.php?route=admin'],
    ['label' => $t('admin_module_detail.link_modules', 'Модули'), 'href' => 'index.php?route=admin-modules'],
    ['label' => $titleRaw, 'active' => true],
], $titleRaw, 'Интеграция с Telegram для приёма входящих заявок в Intake, уведомлений сотрудников и связи с клиентами.', '<a class="btn crm-btn-secondary" href="index.php?route=admin-modules" data-i18n="admin_module_detail.back_to_modules">' . htmlspecialchars($t('admin_module_detail.back_to_modules', 'Вернуться к модулям'), ENT_QUOTES, 'UTF-8') . '</a>'); ?>

<!-- Workspace / Organization Selection Bar -->
<div class="crm-card p-3 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-3" id="tgWorkspaceBar">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <label for="tgWorkspaceSelect" class="form-label mb-0 fw-semibold text-nowrap">
            <i class="fa-solid fa-building me-1 text-primary"></i> Рабочее пространство:
        </label>
        <select id="tgWorkspaceSelect" class="form-select form-select-sm" style="min-width: 220px; max-width: 340px;">
            <option value="1">Загрузка пространств...</option>
        </select>
        <span id="tgWorkspaceBadge" class="badge bg-secondary">Орг #1</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm crm-btn-secondary" id="tgRefreshBtn" title="Обновить текущие данные">
            <i class="fa-solid fa-rotate me-1" aria-hidden="true"></i> Обновить
        </button>
    </div>
</div>

<!-- Dynamic Status Banner -->
<div id="tgStatusBanner" class="mb-3">
    <div class="alert alert-info d-flex align-items-center justify-content-between p-3" role="status">
        <div class="d-flex align-items-center">
            <span class="spinner-border spinner-border-sm text-primary me-3" role="status" aria-hidden="true"></span>
            <div>
                <strong>Проверка статуса Telegram-бота...</strong>
                <div class="small text-muted">Загрузка параметров подключения и информации о боте</div>
            </div>
        </div>
    </div>
</div>

<!-- Notification alert container -->
<div id="tgAlertContainer" class="mb-3" style="display:none;"></div>

<!-- Main Navigation Tabs -->
<ul class="nav nav-pills mb-3 gap-1" id="tgTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-dialogs" data-bs-toggle="pill" data-bs-target="#pane-dialogs" type="button" role="tab" aria-controls="pane-dialogs" aria-selected="true">
            <i class="fa-solid fa-comments me-1"></i> Диалоги с клиентами
            <span id="tgDialogsCountBadge" class="badge bg-primary ms-1" style="display:none;">0</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-config" data-bs-toggle="pill" data-bs-target="#pane-config" type="button" role="tab" aria-controls="pane-config" aria-selected="false">
            <i class="fa-solid fa-gear me-1"></i> Настройки бота
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-bindings" data-bs-toggle="pill" data-bs-target="#pane-bindings" type="button" role="tab" aria-controls="pane-bindings" aria-selected="false">
            <i class="fa-solid fa-users me-1"></i> Привязка сотрудников
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-diag" data-bs-toggle="pill" data-bs-target="#pane-diag" type="button" role="tab" aria-controls="pane-diag" aria-selected="false">
            <i class="fa-solid fa-stethoscope me-1"></i> Диагностика и журнал
        </button>
    </li>
</ul>

<!-- Tab Contents -->
<div class="tab-content" id="tgTabsContent">

    <!-- TAB 1: CLIENT DIALOGS & MESSENGER -->
    <div class="tab-pane fade show active" id="pane-dialogs" role="tabpanel" aria-labelledby="tab-dialogs">
        <div class="row g-3">
            <!-- Left Pane: Dialogs List -->
            <div class="col-lg-4 col-md-5">
                <div class="crm-card crm-section-card h-100 d-flex flex-column" style="min-height: 580px;">
                    <div class="crm-section-head p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h2 class="h6 mb-0 d-flex align-items-center gap-2">
                                <span>Чаты с клиентами</span>
                            </h2>
                            <button class="btn btn-sm btn-link p-0 text-decoration-none" id="tgRefreshDialogsBtn" title="Обновить список диалогов">
                                <i class="fa-solid fa-arrows-rotate"></i>
                            </button>
                        </div>
                        <div class="d-flex gap-2 tg-dialog-search-bar">
                            <input type="text" class="form-control form-control-sm" id="tgDialogSearch" placeholder="Поиск по имени или @username...">
                            <select class="form-select form-select-sm" id="tgDialogStatusFilter" style="width: 105px; flex-shrink: 0;">
                                <option value="all">Все</option>
                                <option value="open">Открыт</option>
                                <option value="in_progress">В работе</option>
                                <option value="resolved">Решён</option>
                                <option value="archived">Архив</option>
                            </select>
                        </div>
                    </div>
                    <div class="tg-dialogs-list flex-grow-1 overflow-auto p-2" id="tgDialogsList">
                        <div class="text-center text-muted py-5">
                            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                            <div>Загрузка диалогов...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Pane: Active Dialog View & Chat Box -->
            <div class="col-lg-8 col-md-7">
                <div class="crm-card crm-section-card h-100 d-flex flex-column" style="min-height: 580px;">
                    <!-- Placeholder when no dialog is selected -->
                    <div id="tgNoDialogSelected" class="d-flex flex-column align-items-center justify-content-center flex-grow-1 p-5 text-center text-muted">
                        <div class="fs-1 text-primary opacity-50 mb-3">
                            <i class="fa-brands fa-telegram"></i>
                        </div>
                        <h5>Выберите диалог из списка</h5>
                        <p class="small text-muted mb-0" style="max-width: 360px;">
                            Нажмите на чат с клиентом слева, чтобы просмотреть переписку, ответить прямо в Telegram или создать связанную задачу в CRM.
                        </p>
                    </div>

                    <!-- Active Dialog View -->
                    <div id="tgActiveDialogView" class="d-flex flex-column flex-grow-1" style="display:none !important;">
                        <!-- Dialog Header -->
                        <div class="crm-section-head p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="tg-avatar-circle" id="tgActiveAvatar">?</div>
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <strong class="fs-6" id="tgActiveClientName">—</strong>
                                        <span class="badge bg-secondary font-monospace" id="tgActiveChatId">#—</span>
                                    </div>
                                    <div class="small text-muted" id="tgActiveClientHandle">—</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap tg-dialog-actions">
                                <!-- Status Selector -->
                                <select class="form-select form-select-sm" id="tgActiveStatusSelect" style="width: 125px;">
                                    <option value="open">Открыт</option>
                                    <option value="in_progress">В работе</option>
                                    <option value="resolved">Решён</option>
                                    <option value="archived">Архивирован</option>
                                </select>

                                <!-- Links and actions -->
                                <a href="#" target="_blank" class="btn btn-sm crm-btn-secondary" id="tgActiveIntakeLink" style="display:none;" title="Перейти к обращению в Intake">
                                    <i class="fa-solid fa-inbox me-1"></i> Intake
                                </a>
                                <a href="#" target="_blank" class="btn btn-sm crm-btn-secondary" id="tgActiveTaskLink" style="display:none;" title="Перейти к задаче CRM">
                                    <i class="fa-solid fa-list-check me-1"></i> Задача
                                </a>
                                <button type="button" class="btn btn-sm crm-btn-primary" id="tgOpenCreateTaskModalBtn" title="Создать CRM-задачу на основе этого диалога">
                                    <i class="fa-solid fa-plus me-1"></i> Создать задачу
                                </button>
                            </div>
                        </div>

                        <!-- Messages Thread -->
                        <div class="tg-messages-thread flex-grow-1 overflow-auto p-3" id="tgMessagesThread">
                            <div class="text-center text-muted py-5">
                                <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                                <div>Загрузка сообщений...</div>
                            </div>
                        </div>

                        <!-- Reply Box -->
                        <div class="tg-reply-box p-3 border-top">
                            <form id="tgReplyForm" class="d-flex gap-2">
                                <textarea class="form-control" id="tgReplyText" rows="2" placeholder="Написать ответ клиенту в Telegram... (Enter для отправки)" required></textarea>
                                <button type="submit" class="btn crm-btn-primary px-3 d-flex flex-column align-items-center justify-content-center" id="tgSendReplyBtn" title="Отправить в Telegram">
                                    <i class="fa-regular fa-paper-plane fs-5 mb-1"></i>
                                    <span class="small" style="font-size: 0.75rem;">Ответить</span>
                                </button>
                            </form>
                            <div class="small text-muted mt-1 d-flex justify-content-between">
                                <span>Сообщение будет мгновенно доставлено клиенту от имени бота.</span>
                                <span id="tgReplyStatus"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: BOT SETTINGS & CONFIGURATION -->
    <div class="tab-pane fade" id="pane-config" role="tabpanel" aria-labelledby="tab-config">
        <div class="crm-card crm-section-card mb-4">
            <div class="crm-section-head d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h2 class="h6 mb-0">Конфигурация Telegram Бота</h2>
                    <small class="text-muted">Настройки токена и интеграции для выбранного рабочего пространства</small>
                </div>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <button class="btn btn-sm crm-btn-secondary" id="tgImportEnvBtn" style="display:none;">
                        <i class="fa-solid fa-file-import me-1" aria-hidden="true"></i> Импорт из .env
                    </button>
                </div>
            </div>
            <div class="crm-card-body p-3">
                <form id="tgConfigForm" novalidate>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="tgBotToken">Bot Token <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control font-monospace" id="tgBotToken" name="bot_token" placeholder="123456789:ABCdefGHIjklMNOpqrSTUvwxYZ" required autocomplete="off">
                                <button class="btn btn-outline-secondary" type="button" id="tgToggleTokenBtn" title="Показать/скрыть токен">
                                    <i class="fa-regular fa-eye" id="tgToggleTokenIcon" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="form-text">Токен, полученный у официального бота @BotFather в Telegram.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="tgBotUsername">Имя пользователя бота (@username)</label>
                            <input type="text" class="form-control" id="tgBotUsername" name="bot_username" placeholder="@MyCompanyBot">
                            <div class="form-text">Определяется автоматически при проверке соединения через Telegram API.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="tgSecretToken">Секретный токен Webhook (Secret Token)</label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" id="tgSecretToken" name="webhook_secret_token" placeholder="Случайный токен валидации вебхука">
                                <button class="btn btn-outline-secondary" type="button" id="tgGenSecretBtn" title="Сгенерировать случайный токен">
                                    <i class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></i> Сгенерировать
                                </button>
                            </div>
                            <div class="form-text">Используется Telegram для проверки подлинности запросов к вашему CRM серверу.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="tgWebhookUrl">Webhook URL</label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" id="tgWebhookUrl" name="webhook_url" readonly>
                                <button class="btn btn-outline-secondary" type="button" id="tgCopyWebhookBtn" title="Скопировать URL">
                                    <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="form-text">Публичный адрес для регистрации в Telegram API через setWebhook.</div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="tgAutoIntake" name="auto_create_intake" checked>
                                <label class="form-check-label" for="tgAutoIntake">
                                    <strong>Автоматически создавать заявки в Intake из сообщений клиентов</strong>
                                </label>
                            </div>
                            <div class="form-text ms-4">Каждое новое входящее обращение от клиента будет регистрироваться в очереди Intake со статусом «Ожидает». Последующие сообщения клиента в том же диалоге дополняют историю переписки.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="tgWelcomeMessage">Приветственное сообщение бота (/start)</label>
                            <textarea class="form-control" id="tgWelcomeMessage" name="welcome_message" rows="2" placeholder="Здравствуйте! Я официальный бот TropaTT CRM. Напишите ваш вопрос или заявку, и наша команда свяжется с вами."></textarea>
                            <div class="form-text">Текст, который бот отправляет новым пользователям при первом обращении.</div>
                        </div>

                        <div class="col-12 d-flex flex-wrap gap-2 pt-2 border-top">
                            <button type="submit" class="btn crm-btn-primary" id="tgSaveBtn">
                                <i class="fa-solid fa-check me-1" aria-hidden="true"></i> Сохранить настройки
                            </button>
                            <button type="button" class="btn crm-btn-secondary" id="tgTestConnBtn">
                                <i class="fa-solid fa-bolt me-1" aria-hidden="true"></i> Проверить соединение (getMe)
                            </button>
                            <button type="button" class="btn crm-btn-secondary" id="tgSetWebhookBtn">
                                <i class="fa-solid fa-link me-1" aria-hidden="true"></i> Установить Webhook в Telegram
                            </button>
                            <button type="button" class="btn crm-btn-secondary" id="tgDeleteWebhookBtn">
                                <i class="fa-solid fa-link-slash me-1" aria-hidden="true"></i> Удалить Webhook (Polling)
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB 3: STAFF BINDINGS -->
    <div class="tab-pane fade" id="pane-bindings" role="tabpanel" aria-labelledby="tab-bindings">
        <div class="crm-card crm-section-card mb-4">
            <div class="crm-section-head d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h2 class="h6 mb-0">Привязка сотрудников к Telegram</h2>
                    <small class="text-muted">Персональные уведомления о задачах и обращениях прямо в Telegram</small>
                </div>
                <button class="btn btn-sm crm-btn-primary" id="tgRequestBindBtn">
                    <i class="fa-solid fa-key me-1" aria-hidden="true"></i> Получить мой код привязки
                </button>
            </div>
            <div class="crm-card-body p-3">
                <!-- Bind Code Result Box -->
                <div id="tgBindCodeBox" class="alert alert-secondary p-3 mb-3" style="display:none;">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <strong>Ваш одноразовый код привязки:</strong>
                            <span class="badge bg-primary fs-6 font-monospace ms-2" id="tgBindCodeValue">------</span>
                            <div class="small mt-1 text-muted">
                                Откройте бота в Telegram и отправьте команду:
                                <code class="user-select-all" id="tgBindCommand">/bind ------</code>
                            </div>
                        </div>
                        <a href="#" target="_blank" rel="noopener noreferrer" class="btn btn-sm crm-btn-secondary" id="tgOpenBotLink">
                            <i class="fa-brands fa-telegram me-1" aria-hidden="true"></i> Открыть бота в Telegram
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table crm-table align-middle mb-0" id="tgBindingsTable">
                        <thead>
                            <tr>
                                <th>Сотрудник</th>
                                <th>Telegram Chat ID</th>
                                <th>Telegram Username</th>
                                <th>Статус</th>
                                <th>Дата привязки</th>
                                <th class="text-end">Действия</th>
                            </tr>
                        </thead>
                        <tbody id="tgBindingsBody">
                            <tr>
                                <td colspan="6" class="text-muted text-center py-3">Загрузка привязок...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 4: DIAGNOSTICS & LOGS -->
    <div class="tab-pane fade" id="pane-diag" role="tabpanel" aria-labelledby="tab-diag">
        <!-- KPI Cards -->
        <div class="row g-3 mb-4" id="tgKpis">
            <div class="col-6 col-md-3">
                <div class="crm-card crm-kpi-card p-3">
                    <small class="text-muted">Статус бота</small>
                    <h2 class="h5 mb-0 mt-1 text-truncate" id="kpiBotName">—</h2>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="crm-card crm-kpi-card p-3">
                    <small class="text-muted">Режим связи</small>
                    <h2 class="h5 mb-0 mt-1" id="kpiMode">—</h2>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="crm-card crm-kpi-card p-3">
                    <small class="text-muted">Привязано сотрудников</small>
                    <h2 class="h4 mb-0 mt-1" id="kpiBindings">—</h2>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="crm-card crm-kpi-card p-3">
                    <small class="text-muted">Обработано сообщений</small>
                    <h2 class="h4 mb-0 mt-1" id="kpiUpdates">—</h2>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="crm-card crm-section-card h-100">
                    <div class="crm-section-head">
                        <h2 class="h6 mb-0">Ручной опрос (Polling getUpdates)</h2>
                    </div>
                    <div class="crm-card-body p-3">
                        <p class="small text-muted mb-3">
                            Полезно при локальной разработке, отсутствии публичного SSL-сертификата или блокировке вебхуков. Забирает скопившиеся сообщения из очереди Telegram.
                        </p>
                        <div class="d-flex gap-2 align-items-center mb-3">
                            <button class="btn btn-sm crm-btn-secondary" id="tgPollUpdatesBtn">
                                <i class="fa-solid fa-arrows-rotate me-1" aria-hidden="true"></i> Запустить опрос (getUpdates)
                            </button>
                            <span id="tgPollStatus" class="small text-muted"></span>
                        </div>
                        <div id="tgPollResult" class="tg-code-box small p-2 rounded font-monospace" style="display:none; max-height:120px; overflow-y:auto;"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="crm-card crm-section-card h-100">
                    <div class="crm-section-head">
                        <h2 class="h6 mb-0">Отправка тестового сообщения</h2>
                    </div>
                    <div class="crm-card-body p-3">
                        <form id="tgSendMsgForm">
                            <div class="mb-2">
                                <label class="form-label small" for="tgTestChatId">Chat ID получателя</label>
                                <input type="text" class="form-control form-control-sm font-monospace" id="tgTestChatId" placeholder="Например: 123456789" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small" for="tgTestMessageText">Текст сообщения</label>
                                <input type="text" class="form-control form-control-sm" id="tgTestMessageText" value="🔔 Тестовое уведомление из TropaTT CRM" required>
                            </div>
                            <button type="submit" class="btn btn-sm crm-btn-secondary" id="tgSendMsgBtn">
                                <i class="fa-regular fa-paper-plane me-1" aria-hidden="true"></i> Отправить сообщение
                            </button>
                            <span id="tgSendMsgStatus" class="small ms-2"></span>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Updates Log -->
        <div class="crm-card crm-section-card mb-4">
            <div class="crm-section-head d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0">Журнал недавних событий (Updates Log)</h2>
                <button class="btn btn-sm crm-btn-secondary" id="tgRefreshLogsBtn">
                    <i class="fa-solid fa-rotate me-1" aria-hidden="true"></i> Обновить журнал
                </button>
            </div>
            <div class="table-responsive">
                <table class="table crm-table align-middle mb-0" id="tgLogsTable">
                    <thead>
                        <tr>
                            <th>Update ID</th>
                            <th>Chat ID</th>
                            <th>Действие</th>
                            <th>Превью сообщения</th>
                            <th>Дата / Время</th>
                        </tr>
                    </thead>
                    <tbody id="tgLogsBody">
                        <tr>
                            <td colspan="5" class="text-muted text-center py-3">Загрузка журнала...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Create Task from Dialog -->
<div class="modal fade" id="tgCreateTaskModal" tabindex="-1" aria-labelledby="tgCreateTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tgCreateTaskModalLabel">
                    <i class="fa-solid fa-list-check me-2 text-primary"></i> Создать задачу в CRM из диалога
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <form id="tgCreateTaskForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="tgTaskTitle" class="form-label">Название задачи <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="tgTaskTitle" required placeholder="Например: Запрос от клиента...">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="tgTaskProjectSelect" class="form-label">Проект</label>
                            <select class="form-select" id="tgTaskProjectSelect">
                                <option value="0">Без проекта (общие задачи)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="tgTaskPriority" class="form-label">Приоритет</label>
                            <select class="form-select" id="tgTaskPriority">
                                <option value="low">Низкий</option>
                                <option value="medium" selected>Средний</option>
                                <option value="high">Высокий</option>
                                <option value="urgent">Срочный</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="tgTaskDescription" class="form-label">Описание задачи (история сообщений)</label>
                        <textarea class="form-control font-monospace" id="tgTaskDescription" rows="8"></textarea>
                        <div class="form-text">История диалога предзаполнена и будет сохранена в описание созданной задачи.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn crm-btn-primary" id="tgSubmitCreateTaskBtn">
                        <i class="fa-solid fa-check me-1"></i> Создать задачу
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

</main></div></div>
