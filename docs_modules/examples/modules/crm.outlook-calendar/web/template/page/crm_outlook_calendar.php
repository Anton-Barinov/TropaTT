<?php
declare(strict_types=1);
/** @var array $data */
$titleRaw = (string)($data['title'] ?? 'Outlook Calendar');
?>
<body data-page="module-outlook-calendar" data-protected="1">
<div class="crm-app">
    <aside class="crm-sidebar">
        <div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div>
        <nav class="nav flex-column crm-nav"></nav>
    </aside>
    <div class="crm-main-wrap">
        <header class="crm-topbar py-2"><div class="container-fluid"></div></header>
        <main class="crm-content crm-admin-page crm-module-page">
            <?php crm_page_head([
                ['label' => $t('admin_module_detail.link_admin', 'Администрирование'), 'href' => 'index.php?route=admin'],
                ['label' => $t('admin_module_detail.link_modules', 'Модули'), 'href' => 'index.php?route=admin-modules'],
                ['label' => $titleRaw, 'active' => true],
            ], $titleRaw, 'Импорт событий из основного календаря Microsoft 365 в календарь CRM.', '<button class="btn crm-btn-secondary" id="outlookRefreshBtn" type="button">Обновить состояние</button>'); ?>

            <div id="outlookMessage" class="small mt-3" role="status" aria-live="polite"></div>
            <section class="crm-card mt-3" aria-labelledby="outlookConnectionHeading">
                <div class="crm-card-body">
                    <div class="outlook-status-box alert alert-warning" id="outlookStatusBox" role="status">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                            <div>
                                <h2 class="h5 mb-1" id="outlookConnectionHeading">Проверяем подключение…</h2>
                                <p class="mb-0" id="outlookStatusMeta">Состояние рабочего пространства загружается.</p>
                            </div>
                            <span class="badge text-bg-light" id="outlookSyncTime">Синхронизация ещё не запускалась</span>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-12 col-xl-6">
                            <section class="outlook-panel h-100" aria-labelledby="outlookSetupHeading">
                                <h3 class="h6" id="outlookSetupHeading">1. Настройте приложение Microsoft Entra</h3>
                                <p class="small text-muted">Создайте Web-приложение в Microsoft Entra ID и добавьте Redirect URI, указанный ниже. Разрешения делегированные: <code>User.Read</code>, <code>Calendars.Read</code>, <code>offline_access</code>, <code>openid</code>, <code>profile</code>.</p>
                                <form id="outlookConfigForm" autocomplete="off">
                                    <div class="mb-3">
                                        <label class="form-label" for="outlookTenantId">Tenant</label>
                                        <input class="form-control" id="outlookTenantId" name="tenant_id" value="organizations" maxlength="128" required>
                                        <div class="form-text">Например <code>organizations</code>, <code>common</code> или UUID арендатора.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="outlookClientId">Application (client) ID</label>
                                        <input class="form-control" id="outlookClientId" name="client_id" autocomplete="off" maxlength="36" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="outlookClientSecret">Client secret</label>
                                        <input class="form-control" id="outlookClientSecret" name="client_secret" type="password" autocomplete="new-password" maxlength="2048">
                                        <div class="form-text">Секрет шифруется через APP_KEY и не читается обратно. Оставьте поле пустым, чтобы сохранить текущий секрет.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="outlookRedirectUri">Redirect URI</label>
                                        <div class="input-group">
                                            <input class="form-control" id="outlookRedirectUri" name="redirect_uri" type="url" readonly required>
                                            <button class="btn crm-btn-secondary" id="outlookCopyRedirectBtn" type="button">Копировать</button>
                                        </div>
                                        <div class="form-text">Добавьте эту точную ссылку в разделе Authentication → Web в Entra.</div>
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <button class="btn crm-btn-primary" id="outlookSaveBtn" type="submit">Сохранить настройки</button>
                                        <span class="small text-muted" id="outlookConfigState"></span>
                                    </div>
                                </form>
                            </section>
                        </div>

                        <div class="col-12 col-xl-6">
                            <section class="outlook-panel h-100" aria-labelledby="outlookSyncHeading">
                                <h3 class="h6" id="outlookSyncHeading">2. Подключите и синхронизируйте календарь</h3>
                                <p class="small text-muted">Подключается основной календарь текущего пользователя. Синхронизация только из Outlook в CRM: события CRM не отправляются в Microsoft и не изменяют источник.</p>
                                <div class="outlook-account-card mb-3" id="outlookAccountCard">
                                    <div class="small text-muted">Аккаунт Microsoft</div>
                                    <strong id="outlookAccountEmail">Не подключён</strong>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn crm-btn-primary" id="outlookConnectBtn" type="button" disabled>Подключить Microsoft</button>
                                    <button class="btn crm-btn-secondary" id="outlookSyncBtn" type="button" disabled>Синхронизировать сейчас</button>
                                    <button class="btn crm-btn-danger-soft" id="outlookDisconnectBtn" type="button" hidden>Отключить</button>
                                </div>
                                <div class="outlook-sync-result mt-3" id="outlookSyncResult" aria-live="polite"></div>
                                <div class="outlook-window-note small text-muted mt-3" id="outlookWindowNote"></div>
                                <div class="alert alert-light small mt-3 mb-0">Отключение удаляет локально сохранённые токены, но оставляет уже импортированные события в CRM. Чтобы полностью отозвать согласие, удалите разрешение TropaTT в настройках приложений Microsoft.</div>
                            </section>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>
