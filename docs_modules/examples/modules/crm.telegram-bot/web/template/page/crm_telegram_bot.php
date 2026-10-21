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

<!-- Dynamic Status Banner -->
<div id="tgStatusBanner" class="mb-4">
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

<!-- Notification alert container -->
<div id="tgAlertContainer" class="mb-4" style="display:none;"></div>

<!-- Main Configuration Card -->
<div class="crm-card crm-section-card mb-4">
    <div class="crm-section-head d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="h6 mb-0">Конфигурация Telegram Бота</h2>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button class="btn btn-sm crm-btn-secondary" id="tgImportEnvBtn" style="display:none;">
                <i class="fa-solid fa-file-import me-1" aria-hidden="true"></i> Импорт из .env
            </button>
            <button class="btn btn-sm crm-btn-secondary" id="tgRefreshBtn">
                <i class="fa-solid fa-rotate me-1" aria-hidden="true"></i> Обновить статус
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
                    <div class="form-text">Токен полученный у официального бота @BotFather в Telegram.</div>
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
                    <div class="form-text ms-4">Каждое входящее сообщение от клиента будет автоматически регистрироваться в очереди Intake со статусом «Ожидает».</div>
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

<!-- Staff Bindings Section -->
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

<!-- Diagnostics, Polling & Test Message Section -->
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
                <div id="tgPollResult" class="small p-2 bg-light rounded font-monospace" style="display:none; max-height:120px; overflow-y:auto;"></div>
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

</main></div></div>
