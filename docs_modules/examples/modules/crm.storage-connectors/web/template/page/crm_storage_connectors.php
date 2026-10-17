<?php
declare(strict_types=1);
/** @var array<string, mixed> $data */
$titleRaw = (string)($data['title'] ?? 'Внешние хранилища');
$title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
?>
<body data-page="module-storage-connectors" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-module-page"><?php crm_page_head([
    ['label' => $t('admin_module_detail.link_admin', 'Администрирование'), 'href' => 'index.php?route=admin'],
    ['label' => $t('admin_module_detail.link_modules', 'Модули'), 'href' => 'index.php?route=admin-modules'],
    ['label' => $titleRaw, 'active' => true],
], $titleRaw, 'Подключение Nextcloud, WebDAV и S3-совместимых хранилищ для текущего рабочего пространства.', '<a class="btn crm-btn-secondary" href="index.php?route=admin-modules" data-i18n="admin_module_detail.back_to_modules">' . htmlspecialchars($t('admin_module_detail.back_to_modules', 'Вернуться к модулям'), ENT_QUOTES, 'UTF-8') . '</a>'); ?>
<div class="crm-storage-connectors" id="stconPage" aria-live="polite">
    <section class="crm-card mt-3" aria-labelledby="stconStatusTitle">
        <div class="crm-card-header"><h2 id="stconStatusTitle" class="h5 mb-0">Состояние подключения</h2></div>
        <div class="crm-card-body">
            <p class="mb-2" id="stconStatus" role="status">Загружаем состояние…</p>
            <div class="small text-muted" id="stconPreflight">Проверка возможностей хостинга…</div>
            <div id="stconMessage" class="small mt-3" role="status" aria-live="polite"></div>
        </div>
    </section>
    <section class="crm-card mt-3" aria-labelledby="stconFormTitle">
        <div class="crm-card-header"><h2 id="stconFormTitle" class="h5 mb-0">Настройки хранилища</h2></div>
        <div class="crm-card-body">
            <p class="small text-muted">Настройка действует только в выбранном рабочем пространстве. При повторном сохранении введите ключи доступа заново: CRM не показывает сохранённые секреты.</p>
            <form id="stconConfigForm" autocomplete="off" novalidate>
                <div class="crm-form-group"><label class="crm-form-label" for="stconProvider">Провайдер</label><select class="crm-form-select" id="stconProvider" name="provider_type"><option value="s3">S3-совместимое хранилище</option><option value="nextcloud_webdav">Nextcloud / WebDAV</option></select></div>
                <div class="crm-form-group"><label class="crm-form-label" for="stconEndpoint">HTTPS-адрес сервера</label><input class="crm-form-input" id="stconEndpoint" name="endpoint_url" type="url" inputmode="url" placeholder="https://storage.example.com" required autocomplete="url"><div class="form-text">Только HTTPS на стандартном порту. Адреса локальной и частной сети запрещены.</div></div>
                <div class="crm-form-group"><label class="crm-form-label" for="stconBucket" id="stconBucketLabel">Имя S3-бакета</label><input class="crm-form-input" id="stconBucket" name="bucket_or_path" type="text" required maxlength="180" autocomplete="off"></div>
                <div id="stconS3Fields">
                    <div class="crm-form-group"><label class="crm-form-label" for="stconRegion">Регион</label><input class="crm-form-input" id="stconRegion" name="region" type="text" value="us-east-1" maxlength="80" autocomplete="off"></div>
                    <div class="crm-form-group"><label class="crm-form-label" for="stconAccessKey">Access Key ID</label><input class="crm-form-input" id="stconAccessKey" name="access_key" type="text" maxlength="255" autocomplete="off"></div>
                    <div class="crm-form-group"><label class="crm-form-label" for="stconSecretKey">Secret Access Key</label><input class="crm-form-input" id="stconSecretKey" name="secret_key" type="password" maxlength="512" autocomplete="new-password"></div>
                </div>
                <div id="stconWebdavFields" hidden>
                    <div class="crm-form-group"><label class="crm-form-label" for="stconUsername">Имя пользователя WebDAV</label><input class="crm-form-input" id="stconUsername" name="username" type="text" maxlength="255" autocomplete="username"></div>
                    <div class="crm-form-group"><label class="crm-form-label" for="stconPassword">Пароль приложения</label><input class="crm-form-input" id="stconPassword" name="password" type="password" maxlength="512" autocomplete="new-password"></div>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button class="btn crm-btn-primary" id="stconSaveBtn" type="submit">Сохранить подключение</button>
                    <button class="btn crm-btn-secondary" id="stconTestBtn" type="button" disabled>Проверить подключение</button>
                </div>
            </form>
        </div>
    </section>
    <section class="crm-card mt-3" aria-labelledby="stconMigrationTitle">
        <div class="crm-card-header"><h2 id="stconMigrationTitle" class="h5 mb-0">Перенос существующих файлов</h2></div>
        <div class="crm-card-body"><p class="small text-muted">Файлы переносятся небольшими партиями. Исходные файлы остаются в CRM; перед началом убедитесь, что удалённое хранилище доступно и имеет достаточно места.</p><button class="btn crm-btn-secondary" id="stconMigrateBtn" type="button" disabled>Перенести следующую партию</button><div id="stconMigrationResult" class="small mt-3" role="status" aria-live="polite"></div></div>
    </section>
</div></main></div></div>
