<body data-page="module-ecommerce-gateway" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page">

    <div class="crm-page-head">
        <div>
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="nav.admin"><?= htmlspecialchars($t('nav.admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($t('ecommerce_gateway.title', 'Шлюз интернет-магазинов'), ENT_QUOTES, 'UTF-8') ?></li>
            </ol>
            <h1 class="crm-page-title"><?= htmlspecialchars($t('ecommerce_gateway.page_title', 'Шлюз интернет-магазинов'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="crm-subtitle"><?= htmlspecialchars($t('ecommerce_gateway.subtitle', 'Реестр витрин, приём заказов из CMS, маппинг воронок и журнал синхронизации'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>

    <!-- Store Selector & Toolbar -->
    <div class="crm-card mb-3 p-3">
        <div class="row g-3 align-items-center">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1" for="storeSelector"><?= htmlspecialchars($t('ecommerce_gateway.select_store', 'Активная витрина (магазин)'), ENT_QUOTES, 'UTF-8') ?></label>
                <select id="storeSelector" class="form-select">
                    <option value=""><?= htmlspecialchars($t('ecommerce_gateway.loading_stores', 'Загрузка витрин...'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>
            <div class="col-md-7 d-flex justify-content-md-end align-items-end gap-2 mt-md-auto">
                <button type="button" class="btn crm-btn-secondary" id="pingStoreBtn" title="Проверить доступность Webhook/URL витрины">
                    <i class="fa-solid fa-satellite-dish me-1"></i> <?= htmlspecialchars($t('ecommerce_gateway.btn_ping', 'Ping Тест'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="button" class="btn crm-btn-secondary" id="rotateSecretBtn" title="Сгенерировать новый секретный ключ HMAC">
                    <i class="fa-solid fa-key me-1"></i> <?= htmlspecialchars($t('ecommerce_gateway.btn_rotate', 'Ротация ключа'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="button" class="btn crm-btn-primary" id="addStoreBtn">
                    <i class="fa-solid fa-plus me-1"></i> <?= htmlspecialchars($t('ecommerce_gateway.btn_add_store', 'Добавить витрину'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="crm-segmented-filter mb-3" id="ecomTabs" role="tablist">
        <button class="crm-segmented-filter-btn active" type="button" role="tab" data-tab="stores">
            <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-store"></i></span>
            <span><?= htmlspecialchars($t('ecommerce_gateway.tab_stores', 'Витрины и Настройки'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <button class="crm-segmented-filter-btn" type="button" role="tab" data-tab="routing">
            <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-route"></i></span>
            <span><?= htmlspecialchars($t('ecommerce_gateway.tab_routing', 'Маршрутизация'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <button class="crm-segmented-filter-btn" type="button" role="tab" data-tab="status_mapping">
            <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-arrows-split-up-and-left"></i></span>
            <span><?= htmlspecialchars($t('ecommerce_gateway.tab_status_mapping', 'Маппинг статусов'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <button class="crm-segmented-filter-btn" type="button" role="tab" data-tab="security">
            <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
            <span><?= htmlspecialchars($t('ecommerce_gateway.tab_security', 'Безопасность и Спам'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <button class="crm-segmented-filter-btn" type="button" role="tab" data-tab="sync_log">
            <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left"></i></span>
            <span><?= htmlspecialchars($t('ecommerce_gateway.tab_sync_log', 'Журнал синхронизации'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
    </div>

    <!-- TAB 1: Stores Registry -->
    <div class="crm-ecom-tab-pane active" id="tab-stores">
        <div class="crm-card crm-section-card p-0 table-responsive">
            <table class="table crm-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th><?= htmlspecialchars($t('ecommerce_gateway.th_name', 'Витрина'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars($t('ecommerce_gateway.th_cms', 'CMS / Платформа'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars($t('ecommerce_gateway.th_api_key', 'API Ключ'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars($t('ecommerce_gateway.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars($t('ecommerce_gateway.th_last_ingest', 'Посл. активность'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="text-end"><?= htmlspecialchars($t('ecommerce_gateway.th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody id="storesTableBody">
                    <tr><td colspan="6" class="text-muted p-3"><?= htmlspecialchars($t('ecommerce_gateway.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 2: Routing Rules -->
    <div class="crm-ecom-tab-pane" id="tab-routing">
        <div class="crm-card crm-section-card mb-3">
            <div class="crm-section-head">
                <div>
                    <h2 class="h6 mb-0"><?= htmlspecialchars($t('ecommerce_gateway.routing_title', 'Маршрутизация входящих сущностей'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="crm-section-note"><?= htmlspecialchars($t('ecommerce_gateway.routing_desc', 'Определите параметры создания задач/лидов и поведение дедупликации для выбранной витрины.'), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
            <form id="routingForm" class="p-3">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="settingDefaultProject"><?= htmlspecialchars($t('ecommerce_gateway.lbl_project', 'Проект TropaTT по умолчанию'), ENT_QUOTES, 'UTF-8') ?></label>
                        <select class="form-select" id="settingDefaultProject">
                            <option value=""><?= htmlspecialchars($t('ecommerce_gateway.select_project', '-- Выберите проект --'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                        <div class="form-text"><?= htmlspecialchars($t('ecommerce_gateway.hint_project', 'В этот проект будут помещаться создаваемые задачи/заказы.'), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="settingDefaultAssignee"><?= htmlspecialchars($t('ecommerce_gateway.lbl_assignee', 'Ответственный сотрудник'), ENT_QUOTES, 'UTF-8') ?></label>
                        <select class="form-select" id="settingDefaultAssignee">
                            <option value=""><?= htmlspecialchars($t('ecommerce_gateway.select_assignee', '-- Без ответственного --'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="settingOnDuplicate"><?= htmlspecialchars($t('ecommerce_gateway.lbl_on_duplicate', 'Обработка дубликатов (дедупликация)'), ENT_QUOTES, 'UTF-8') ?></label>
                        <select class="form-select" id="settingOnDuplicate">
                            <option value="merge"><?= htmlspecialchars($t('ecommerce_gateway.duplicate_merge', 'Merge — обновлять существующий лид/заказ'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="reject"><?= htmlspecialchars($t('ecommerce_gateway.duplicate_reject', 'Reject — отклонять повторный запрос (409 Conflict)'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="create"><?= htmlspecialchars($t('ecommerce_gateway.duplicate_create', 'Create — всегда создавать новую карточку'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="settingDefaultPriority"><?= htmlspecialchars($t('ecommerce_gateway.lbl_priority', 'Приоритет задач'), ENT_QUOTES, 'UTF-8') ?></label>
                        <select class="form-select" id="settingDefaultPriority">
                            <option value="normal">Normal (Обычный)</option>
                            <option value="high">High (Высокий)</option>
                            <option value="urgent">Urgent (Срочный)</option>
                            <option value="low">Low (Низкий)</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label d-block mb-2"><?= htmlspecialchars($t('ecommerce_gateway.lbl_create_tasks_for', 'Автоматически создавать задачи CRM для типов:'), ENT_QUOTES, 'UTF-8') ?></label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chkTaskOrder" value="order">
                                <label class="form-check-label" for="chkTaskOrder">Заказы (Orders)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chkTaskQuickOrder" value="quick_order">
                                <label class="form-check-label" for="chkTaskQuickOrder">1-клик покупки (Quick Orders)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chkTaskCallback" value="callback">
                                <label class="form-check-label" for="chkTaskCallback">Обратные звонки (Callbacks)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chkTaskFeedback" value="feedback">
                                <label class="form-check-label" for="chkTaskFeedback">Формы обратной связи (Feedback)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn crm-btn-primary" id="saveRoutingBtn">
                        <i class="fa-solid fa-floppy-disk me-1"></i> <?= htmlspecialchars($t('ecommerce_gateway.btn_save_routing', 'Сохранить маршрутизацию'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TAB 3: Status Mapping -->
    <div class="crm-ecom-tab-pane" id="tab-status_mapping">
        <div class="crm-card crm-section-card mb-3">
            <div class="crm-section-head d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h6 mb-0"><?= htmlspecialchars($t('ecommerce_gateway.mapping_title', 'Двусторонний маппинг статусов CMS ↔ CRM'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="crm-section-note"><?= htmlspecialchars($t('ecommerce_gateway.mapping_desc', 'Синхронизация смены статусов заказа между интернет-магазином и воронкой TropaTT.'), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <button type="button" class="btn btn-sm crm-btn-secondary" id="addMappingRowBtn">
                    <i class="fa-solid fa-plus me-1"></i> <?= htmlspecialchars($t('ecommerce_gateway.btn_add_mapping_row', 'Добавить соответствие'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
            <div class="p-3">
                <div class="table-responsive">
                    <table class="table crm-table align-middle" id="statusMappingTable">
                        <thead>
                            <tr>
                                <th style="width: 20%;"><?= htmlspecialchars($t('ecommerce_gateway.th_scope', 'Область (Scope)'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th style="width: 35%;"><?= htmlspecialchars($t('ecommerce_gateway.th_external_status', 'Статус CMS (Storefront)'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th style="width: 5%;" class="text-center"><i class="fa-solid fa-arrows-left-right text-muted"></i></th>
                                <th style="width: 35%;"><?= htmlspecialchars($t('ecommerce_gateway.th_crm_status', 'Статус CRM'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th style="width: 5%;" class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody id="statusMappingBody">
                            <tr><td colspan="5" class="text-muted text-center p-3"><?= htmlspecialchars($t('ecommerce_gateway.no_mappings', 'Нет настроенных соответствий. Добавьте строки или выберите витрину.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn crm-btn-primary" id="saveStatusMappingsBtn">
                        <i class="fa-solid fa-floppy-disk me-1"></i> <?= htmlspecialchars($t('ecommerce_gateway.btn_save_mappings', 'Сохранить маппинг статусов'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 4: Security & Anti-Spam -->
    <div class="crm-ecom-tab-pane" id="tab-security">
        <div class="crm-card crm-section-card mb-3">
            <div class="crm-section-head">
                <div>
                    <h2 class="h6 mb-0"><?= htmlspecialchars($t('ecommerce_gateway.security_title', 'Безопасность, лимиты и защита от спама'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="crm-section-note"><?= htmlspecialchars($t('ecommerce_gateway.security_desc', 'HMAC аутентификация, белые списки IP-адресов серверов CMS и защита от накруток.'), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
            <form id="securityForm" class="p-3">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="securityRateLimit"><?= htmlspecialchars($t('ecommerce_gateway.lbl_rate_limit', 'Лимит запросов (в минуту)'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="number" class="form-control" id="securityRateLimit" min="10" max="3600" value="120">
                        <div class="form-text"><?= htmlspecialchars($t('ecommerce_gateway.hint_rate_limit', 'Защита от перегрузки шлюза. При превышении возвращается HTTP 429.'), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="securityRetentionDays"><?= htmlspecialchars($t('ecommerce_gateway.lbl_retention', 'Хранение сырых логов (дней)'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="number" class="form-control" id="securityRetentionDays" min="7" max="365" value="90">
                        <div class="form-text"><?= htmlspecialchars($t('ecommerce_gateway.hint_retention', 'Срок автоочистки необработанных payloads и audit записей.'), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="securityIpWhitelist"><?= htmlspecialchars($t('ecommerce_gateway.lbl_ip_whitelist', 'Белый список IP-адресов (IP Whitelist)'), ENT_QUOTES, 'UTF-8') ?></label>
                        <textarea class="form-control font-monospace" id="securityIpWhitelist" rows="3" placeholder="192.168.1.1, 10.0.0.0/24"></textarea>
                        <div class="form-text"><?= htmlspecialchars($t('ecommerce_gateway.hint_ip_whitelist', 'Разделяйте адреса запятыми или новыми строками. Если пусто — разрешены любые IP.'), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="securityAntispamEnabled" checked>
                            <label class="form-check-label" for="securityAntispamEnabled"><?= htmlspecialchars($t('ecommerce_gateway.lbl_antispam', 'Включить эвристический антиспам (honeypot + проверка таймингов формы)'), ENT_QUOTES, 'UTF-8') ?></label>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn crm-btn-primary" id="saveSecurityBtn">
                        <i class="fa-solid fa-floppy-disk me-1"></i> <?= htmlspecialchars($t('ecommerce_gateway.btn_save_security', 'Сохранить настройки безопасности'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TAB 5: Sync Log & Outbox -->
    <div class="crm-ecom-tab-pane" id="tab-sync_log">
        <div class="crm-card crm-section-card mb-3">
            <div class="crm-section-head d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h6 mb-0"><?= htmlspecialchars($t('ecommerce_gateway.log_title', 'Журнал синхронизации и Outbox'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="crm-section-note"><?= htmlspecialchars($t('ecommerce_gateway.log_desc', 'Аудит входящих заказов из CMS и исходящих вебхуков обновления статусов.'), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <select id="logDirectionFilter" class="form-select form-select-sm" style="width: 150px;">
                        <option value="all">Все направления</option>
                        <option value="inbound">Входящие (Inbound)</option>
                        <option value="outbound">Исходящие (Outbox)</option>
                    </select>
                    <button type="button" class="btn btn-sm crm-btn-secondary" id="refreshLogBtn">
                        <i class="fa-solid fa-rotate me-1"></i> Обновить
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table crm-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Направление</th>
                            <th>Тип / ID заказа</th>
                            <th>Статус / HTTP</th>
                            <th>CRM Задача</th>
                            <th>Дата и время</th>
                            <th class="text-end">Действия</th>
                        </tr>
                    </thead>
                    <tbody id="syncLogTableBody">
                        <tr><td colspan="6" class="text-muted p-3">Загрузка журнала синхронизации...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main></div></div>

<!-- Add / Edit Store Modal -->
<div class="modal fade" id="storeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="storeModalTitle">Настройка витрины интернет-магазина</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="storeForm">
                    <input type="hidden" id="modalStorePublicId" value="">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="modalStoreName">Название витрины *</label>
                            <input type="text" class="form-control" id="modalStoreName" required placeholder="Например: Главный магазин OpenCart">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="modalStoreCms">CMS Платформа</label>
                            <select class="form-select" id="modalStoreCms">
                                <option value="opencart">OpenCart / ocStore</option>
                                <option value="woocommerce">WooCommerce</option>
                                <option value="bitrix">1C-Битрикс</option>
                                <option value="insales">InSales</option>
                                <option value="custom">Custom / Другая CMS</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="modalStoreUrl">URL интернет-магазина</label>
                            <input type="url" class="form-control" id="modalStoreUrl" placeholder="https://shop.example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="modalWebhookUrl">Webhook URL витрины (для синхронизации статусов)</label>
                            <input type="url" class="form-control" id="modalWebhookUrl" placeholder="https://shop.example.com/index.php?route=extension/tropatt/webhook">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="modalStoreStatus">Статус активности</label>
                            <select class="form-select" id="modalStoreStatus">
                                <option value="active">Active (Активен)</option>
                                <option value="paused">Paused (Приостановлен)</option>
                                <option value="disabled">Disabled (Отключен)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="modalStoreLocale">Локаль по умолчанию</label>
                            <select class="form-select" id="modalStoreLocale">
                                <option value="ru-ru">Русский (ru-ru)</option>
                                <option value="en-gb">English (en-gb)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="modalStoreDesc">Описание / Примечания</label>
                            <textarea class="form-control" id="modalStoreDesc" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn crm-btn-primary" id="saveStoreModalBtn">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<!-- Fresh Secret Display Modal -->
<div class="modal fade" id="secretModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fa-solid fa-key me-2"></i> Секретный ключ витрины</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    <strong>Внимание!</strong> Секретный ключ отображается только один раз. Скопируйте и сохраните его в настройках модуля интеграции CMS.
                </div>
                <div class="mb-3">
                    <label class="form-label small text-muted">API Ключ (Key):</label>
                    <div class="crm-ecom-secret-box" id="displayApiKey">stk_...</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-muted">API Secret (HMAC SHA-256):</label>
                    <div class="crm-ecom-secret-box" id="displayApiSecret">...</div>
                </div>
                <div class="mb-2 text-muted small" id="displaySecretExpiry"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn crm-btn-primary" data-bs-dismiss="modal">Я сохранил ключ</button>
            </div>
        </div>
    </div>
</div>

<!-- Payload Inspector Modal -->
<div class="modal fade" id="payloadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="payloadModalTitle">Просмотр пакета синхронизации</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Request Payload (JSON):</label>
                    <pre class="crm-ecom-code-block" id="modalRequestPayload">{}</pre>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Response Payload / Ошибка:</label>
                    <pre class="crm-ecom-code-block" id="modalResponsePayload">{}</pre>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>
