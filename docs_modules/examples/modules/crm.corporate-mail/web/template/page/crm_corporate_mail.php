<?php declare(strict_types=1); ?>
<?php
/** @var array $data */
$titleRaw = (string)($data['title'] ?? 'Корпоративная почта');
$title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
?>
<body data-page="module-corporate-mail" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-module-page"><?php crm_page_head([
    ['label' => $t('admin_module_detail.link_admin', 'Администрирование'), 'href' => 'index.php?route=admin'],
    ['label' => $t('admin_module_detail.link_modules', 'Модули'), 'href' => 'index.php?route=admin-modules'],
    ['label' => $titleRaw, 'active' => true],
], $titleRaw, 'Общие корпоративные ящики команды, получение и отправка писем через IMAP/SMTP.', '<a class="btn crm-btn-secondary" href="index.php?route=admin-modules" data-i18n="admin_module_detail.back_to_modules">' . htmlspecialchars($t('admin_module_detail.back_to_modules', 'Вернуться к модулям'), ENT_QUOTES, 'UTF-8') . '</a>'); ?>

<!-- Dynamic Status Banner -->
<div id="cmailStatusBanner" class="mb-4">
    <div class="alert alert-info d-flex align-items-center justify-content-between p-3" role="status">
        <div class="d-flex align-items-center">
            <span class="spinner-border spinner-border-sm text-primary me-3" role="status" aria-hidden="true"></span>
            <div>
                <strong>Проверка статуса подключения...</strong>
                <div class="small text-muted">Загрузка информации о корпоративных почтовых ящиках и серверах связи</div>
            </div>
        </div>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4" id="cmailKpis">
    <div class="col-6 col-md-3">
        <div class="crm-card crm-kpi-card p-3">
            <small class="text-muted">Всего ящиков</small>
            <h2 class="h4 mb-0 mt-1" id="kpiTotal">—</h2>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="crm-card crm-kpi-card p-3">
            <small class="text-muted">Активных ящиков</small>
            <h2 class="h4 mb-0 mt-1" id="kpiActive">—</h2>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="crm-card crm-kpi-card p-3">
            <small class="text-muted">Статус связи</small>
            <h2 class="h4 mb-0 mt-1" id="kpiStatus">—</h2>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="crm-card crm-kpi-card p-3">
            <small class="text-muted">Последняя синхронизация</small>
            <h2 class="h4 mb-0 mt-1 text-truncate" id="kpiLastSync" style="font-size:1.05rem">—</h2>
        </div>
    </div>
</div>

<!-- Toolbar and Main Table -->
<div class="crm-card crm-section-card mb-4">
    <div class="crm-section-head d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="h6 mb-0">Подключённые почтовые ящики</h2>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button class="btn btn-sm crm-btn-primary" id="cmailAddMailboxBtn">
                <i class="fa-solid fa-plus me-1" aria-hidden="true"></i> Добавить ящик
            </button>
            <button class="btn btn-sm crm-btn-secondary" id="cmailImportEnvBtn" style="display:none;">
                <i class="fa-solid fa-file-import me-1" aria-hidden="true"></i> Импорт из .env
            </button>
            <button class="btn btn-sm crm-btn-secondary" id="cmailRefreshBtn">
                <i class="fa-solid fa-rotate me-1" aria-hidden="true"></i> Обновить
            </button>
        </div>
    </div>

    <!-- Alert / notification container -->
    <div id="cmailAlertContainer" class="px-3 pt-3" style="display:none;"></div>

    <div class="table-responsive">
        <table class="table crm-table align-middle mb-0" id="cmailTable">
            <thead>
                <tr>
                    <th>Email / Название</th>
                    <th>Протокол</th>
                    <th>Статус</th>
                    <th>Синхронизация</th>
                    <th>Последняя проверка</th>
                    <th class="text-end">Действия</th>
                </tr>
            </thead>
            <tbody id="cmailTableBody">
                <tr>
                    <td colspan="6" class="text-muted text-center py-4">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Загрузка списка почтовых ящиков...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add / Edit Mailbox -->
<div class="modal fade" id="cmailMailboxModal" tabindex="-1" aria-labelledby="cmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cmailModalLabel">Добавить почтовый ящик</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <form id="cmailModalForm">
                <div class="modal-body">
                    <input type="hidden" id="cmailFieldPublicId" value="">

                    <!-- Basic Settings -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="cmailFieldEmail">Email адрес <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="cmailFieldEmail" required placeholder="support@company.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="cmailFieldName">Отображаемое имя <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cmailFieldName" required placeholder="Служба поддержки">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="cmailFieldAdapter">Протокол</label>
                        <select class="form-select" id="cmailFieldAdapter">
                            <option value="imap_smtp" selected>Стандартный IMAP / SMTP (любой почтовый сервер)</option>
                        </select>
                    </div>

                    <hr class="my-3">

                    <!-- IMAP Configuration -->
                    <h6 class="mb-3 text-primary"><i class="fa-solid fa-inbox me-1" aria-hidden="true"></i> Входящая почта (IMAP)</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="cmailFieldImapHost">Сервер IMAP</label>
                            <input type="text" class="form-control" id="cmailFieldImapHost" placeholder="imap.yandex.ru / imap.mail.ru">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="cmailFieldImapPort">Порт IMAP</label>
                            <input type="number" class="form-control" id="cmailFieldImapPort" value="993">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="cmailFieldImapEnc">Шифрование</label>
                            <select class="form-select" id="cmailFieldImapEnc">
                                <option value="ssl" selected>SSL / TLS (993)</option>
                                <option value="tls">STARTTLS (143)</option>
                                <option value="none">Без шифрования (143)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="cmailFieldImapUser">Логин IMAP</label>
                            <input type="text" class="form-control" id="cmailFieldImapUser" placeholder="Обычно совпадает с Email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="cmailFieldImapPass">Пароль IMAP (или пароль приложения)</label>
                            <input type="password" class="form-control" id="cmailFieldImapPass" placeholder="••••••••" autocomplete="new-password">
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- SMTP Configuration -->
                    <h6 class="mb-3 text-primary"><i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i> Исходящая почта (SMTP)</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="cmailFieldSmtpHost">Сервер SMTP</label>
                            <input type="text" class="form-control" id="cmailFieldSmtpHost" placeholder="smtp.yandex.ru / smtp.mail.ru">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="cmailFieldSmtpPort">Порт SMTP</label>
                            <input type="number" class="form-control" id="cmailFieldSmtpPort" value="587">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="cmailFieldSmtpEnc">Шифрование</label>
                            <select class="form-select" id="cmailFieldSmtpEnc">
                                <option value="tls" selected>STARTTLS (587)</option>
                                <option value="ssl">SSL / TLS (465)</option>
                                <option value="none">Без шифрования (25)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="cmailFieldSmtpUser">Логин SMTP</label>
                            <input type="text" class="form-control" id="cmailFieldSmtpUser" placeholder="Обычно совпадает с Email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="cmailFieldSmtpPass">Пароль SMTP (или пароль приложения)</label>
                            <input type="password" class="form-control" id="cmailFieldSmtpPass" placeholder="••••••••" autocomplete="new-password">
                        </div>
                    </div>

                    <!-- Inline Test Connection Feedback -->
                    <div id="cmailModalFeedback" class="small mt-2" style="display:none;"></div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn crm-btn-secondary" id="cmailModalTestBtn">
                        <i class="fa-solid fa-plug me-1" aria-hidden="true"></i> Проверить соединение
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn crm-btn-primary" id="cmailModalSubmitBtn">
                            <i class="fa-solid fa-check me-1" aria-hidden="true"></i> Сохранить
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

</main></div></div>
