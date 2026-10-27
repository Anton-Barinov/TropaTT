<?php
declare(strict_types=1);
/** @var array $data */
$titleRaw = (string)($data['title'] ?? 'Paystack Africa');
$title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
?>
<body data-page="module-paystack-africa" data-protected="1">
<div class="crm-app">
    <aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
    <div class="crm-main-wrap">
        <header class="crm-topbar py-2"><div class="container-fluid"></div></header>
        <main class="crm-content crm-admin-page crm-module-page">
            <?php crm_page_head([
                ['label' => $t('admin_module_detail.link_admin', 'Администрирование'), 'href' => 'index.php?route=admin'],
                ['label' => $t('admin_module_detail.link_modules', 'Модули'), 'href' => 'index.php?route=admin-modules'],
                ['label' => $titleRaw, 'active' => true],
            ], $titleRaw, 'Настройки платёжного подключения Paystack для выбранного рабочего пространства.', '<a class="btn crm-btn-secondary" href="index.php?route=admin-modules" data-i18n="admin_module_detail.back_to_modules">' . htmlspecialchars($t('admin_module_detail.back_to_modules', 'Вернуться к модулям'), ENT_QUOTES, 'UTF-8') . '</a>'); ?>

            <div class="pystk-grid mt-3">
                <section class="crm-card pystk-panel" aria-labelledby="pystkSettingsHeading">
                    <div class="crm-card-body">
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                            <div>
                                <h2 class="h5 mb-1" id="pystkSettingsHeading">Подключение Paystack</h2>
                                <p class="text-muted mb-0">Ключи хранятся отдельно для каждого рабочего пространства.</p>
                            </div>
                            <span class="badge text-bg-secondary" id="pystkStatusBadge">Проверка настроек…</span>
                        </div>

                        <form id="pystkConfigForm" autocomplete="off" novalidate>
                            <div class="mb-3">
                                <label class="form-label" for="pystkMode">Среда</label>
                                <select class="form-select" id="pystkMode" name="mode" required>
                                    <option value="test">Тестовая среда</option>
                                    <option value="live">Рабочая среда</option>
                                </select>
                                <div class="form-text" id="pystkModeHint">Тестовые ключи не проводят реальные списания.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="pystkPublicKey">Публичный ключ</label>
                                <input class="form-control" id="pystkPublicKey" name="public_key" type="text" inputmode="text" maxlength="255" autocomplete="off" spellcheck="false" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="pystkSecretKey">Секретный ключ</label>
                                <input class="form-control" id="pystkSecretKey" name="secret_key" type="password" maxlength="255" autocomplete="new-password" spellcheck="false" required>
                                <div class="form-text">Ключ не возвращается в браузер. При каждом сохранении введите его заново.</div>
                            </div>
                            <div class="alert alert-warning py-2" id="pystkLiveWarning" hidden>
                                Рабочая среда обрабатывает реальные платежи. Проверьте live-ключи перед сохранением.
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <button class="btn crm-btn-primary" id="pystkSaveBtn" type="submit">Сохранить настройки</button>
                                <button class="btn crm-btn-secondary" id="pystkRefreshBtn" type="button">Обновить статус</button>
                                <button class="btn crm-btn-secondary" id="pystkTestConnectionBtn" type="button">Проверить подключение</button>
                            </div>
                            <p class="small mt-3 mb-0" id="pystkMessage" role="status" aria-live="polite"></p>
                        </form>
                    </div>
                </section>

                <section class="crm-card pystk-panel" aria-labelledby="pystkStatusHeading">
                    <div class="crm-card-body">
                        <h2 class="h5 mb-1" id="pystkStatusHeading">Состояние подключения</h2>
                        <p class="text-muted mb-3">Отображаются только безопасные сведения о конфигурации.</p>
                        <dl class="pystk-status-list mb-0">
                            <div><dt>Статус ключей</dt><dd id="pystkStatusMeta">Загрузка…</dd></div>
                            <div><dt>Среда</dt><dd id="pystkModeStatus">—</dd></div>
                            <div><dt>Публичный ключ</dt><dd id="pystkPublicKeyStatus">—</dd></div>
                            <div><dt>Секретный ключ</dt><dd id="pystkSecretStatus">—</dd></div>
                            <div><dt>Webhook Paystack</dt><dd><code id="pystkWebhookPath">Сначала сохраните ключи</code></dd></div>
                        </dl>
                    </div>
                </section>
            </div>

            <section class="crm-card pystk-panel mt-4" aria-labelledby="pystkPaymentsHeading">
                <div class="crm-card-body">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                        <div>
                            <h2 class="h5 mb-1" id="pystkPaymentsHeading">Платёжные ссылки</h2>
                            <p class="text-muted mb-0">Ссылка создаётся через Paystack. Оплата считается успешной только после сверки статуса, суммы и валюты.</p>
                        </div>
                    </div>
                    <form id="pystkPaymentForm" class="row g-3 align-items-end">
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="pystkCustomerEmail">Email клиента</label>
                            <input class="form-control" id="pystkCustomerEmail" type="email" maxlength="254" autocomplete="email" required>
                        </div>
                        <div class="col-8 col-md-3">
                            <label class="form-label" for="pystkAmount">Сумма</label>
                            <input class="form-control" id="pystkAmount" type="number" min="0.01" max="9999999999999.99" step="0.01" inputmode="decimal" required>
                        </div>
                        <div class="col-4 col-md-2">
                            <label class="form-label" for="pystkCurrency">Валюта</label>
                            <select class="form-select" id="pystkCurrency">
                                <option value="NGN">NGN</option><option value="GHS">GHS</option><option value="ZAR">ZAR</option><option value="KES">KES</option><option value="USD">USD</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <button class="btn crm-btn-primary w-100" id="pystkCreatePaymentBtn" type="submit" disabled>Создать платёжную ссылку</button>
                        </div>
                        <div class="col-12"><p class="small text-muted mb-0">В тестовом режиме создаётся только тестовая сессия Paystack; реальные списания не выполняются.</p></div>
                    </form>
                    <p class="small mt-3 mb-0" id="pystkPaymentMessage" role="status" aria-live="polite"></p>
                    <div id="pystkRecoveryActions" class="mt-2 d-flex flex-wrap align-items-center gap-2" hidden>
                        <code id="pystkRecoveryReference" class="small"></code>
                        <button class="btn crm-btn-secondary btn-sm" id="pystkVerifyAttemptBtn" type="button">Проверить существующую попытку</button>
                    </div>
                    <div id="pystkCheckoutLink" class="mt-2" hidden></div>

                    <hr class="my-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <h3 class="h6 mb-0">Транзакции рабочего пространства</h3>
                        <button class="btn crm-btn-secondary btn-sm" type="button" id="pystkReloadTransactionsBtn">Обновить</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table crm-table align-middle mb-0">
                            <thead><tr><th>Ссылка</th><th>Клиент</th><th>Сумма</th><th>Статус</th><th>Создано</th><th>Действие</th></tr></thead>
                            <tbody id="pystkTransactionsBody"><tr><td colspan="6" class="text-muted">Настройте Paystack, чтобы загрузить транзакции.</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>
