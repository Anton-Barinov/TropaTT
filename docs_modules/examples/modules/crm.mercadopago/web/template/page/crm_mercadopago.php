<?php
declare(strict_types=1);
/** @var array $data */
$titleRaw = (string)($data['title'] ?? 'Mercado Pago');
$title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
?>
<body data-page="module-mercadopago" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-module-page"><?php crm_page_head([
    ['label' => $t('admin_module_detail.link_admin', 'Администрирование'), 'href' => 'index.php?route=admin'],
    ['label' => $t('admin_module_detail.link_modules', 'Модули'), 'href' => 'index.php?route=admin-modules'],
    ['label' => $titleRaw, 'active' => true],
], $titleRaw, 'Интеграция платежей для Mercado Pago.', '<a class="btn crm-btn-secondary" href="index.php?route=admin-modules" data-i18n="admin_module_detail.back_to_modules">' . htmlspecialchars($t('admin_module_detail.back_to_modules', 'Вернуться к модулям'), ENT_QUOTES, 'UTF-8') . '</a>'); ?>
<section class="crm-card mt-3" aria-labelledby="moduleSetupState">
    <div class="crm-card-body">
        <div class="alert alert-warning mb-3" id="mpagoStatusBox" role="status">
            <strong id="moduleSetupState">Проверка подключения…</strong>
            <div id="mpagoStatusMeta">Состояние рабочего пространства загружается.</div>
        </div>
        <form id="mpagoConfigForm" class="crm-form-grid" autocomplete="off">
            <div class="mb-3">
                <label class="form-label" for="mpagoAccessToken">Access token Mercado Pago</label>
                <input class="form-control" id="mpagoAccessToken" name="access_token" type="password" required maxlength="512" autocomplete="new-password">
                <div class="form-text">Токен шифруется перед сохранением и не показывается повторно.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="mpagoPublicKey">Public key (необязательно)</label>
                <input class="form-control" id="mpagoPublicKey" name="public_key" type="text" maxlength="255" autocomplete="off">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" id="mpagoSandboxMode" type="checkbox" checked>
                <label class="form-check-label" for="mpagoSandboxMode">Тестовый режим</label>
            </div>
            <div>
                <button class="btn crm-btn-primary" id="mpagoSaveBtn" type="submit">Сохранить настройки</button>
                <span class="small ms-2" id="mpagoMessage" role="status" aria-live="polite"></span>
            </div>
        </form>
    </div>
</section>

<section class="crm-card mt-4" aria-labelledby="mpagoPaymentsHeading">
    <div class="crm-card-body">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h5 mb-1" id="mpagoPaymentsHeading">Платёжные ссылки</h2>
                <p class="text-muted mb-0">Checkout Pro создаёт ссылку на оплату в BRL. Статус считается подтверждённым только после запроса к Mercado Pago.</p>
            </div>
            <button class="btn crm-btn-secondary" id="mpagoReloadPaymentsBtn" type="button">Обновить список</button>
        </div>
        <form id="mpagoPaymentForm" class="row g-3 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label" for="mpagoPaymentTitle">Наименование услуги или товара</label>
                <input class="form-control" id="mpagoPaymentTitle" type="text" maxlength="255" required>
            </div>
            <div class="col-8 col-md-3">
                <label class="form-label" for="mpagoPaymentAmount">Сумма, BRL</label>
                <input class="form-control" id="mpagoPaymentAmount" type="number" min="0.01" max="999999999999.99" step="0.01" inputmode="decimal" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="mpagoExternalReference">Внутренний номер (необязательно)</label>
                <input class="form-control" id="mpagoExternalReference" type="text" maxlength="128" autocomplete="off">
            </div>
            <div class="col-12 d-flex flex-wrap align-items-center gap-2">
                <button class="btn crm-btn-primary" id="mpagoCreatePaymentBtn" type="submit" disabled>Создать ссылку</button>
                <span class="small" id="mpagoPaymentMessage" role="status" aria-live="polite"></span>
            </div>
        </form>
        <div id="mpagoCheckoutResult" class="alert alert-success mt-3" hidden>
            <strong>Ссылка создана.</strong>
            <a id="mpagoCheckoutLink" href="#" target="_blank" rel="noopener noreferrer">Открыть Checkout Mercado Pago</a>
            <span class="small text-muted ms-2" id="mpagoPaymentReference"></span>
        </div>
        <hr class="my-4">
        <div class="table-responsive">
            <table class="table crm-table align-middle mb-0">
                <thead><tr><th>Платёж</th><th>Сумма</th><th>Статус</th><th>Создан</th><th>Действия</th></tr></thead>
                <tbody id="mpagoPaymentsBody"><tr><td colspan="5" class="text-muted">Загрузка платежей…</td></tr></tbody>
            </table>
        </div>
    </div>
</section>
</main></div></div>
