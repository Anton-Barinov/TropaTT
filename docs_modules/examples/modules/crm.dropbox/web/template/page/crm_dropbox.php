<?php
declare(strict_types=1);
/** @var array $data */
$titleRaw = (string)($data['title'] ?? 'Dropbox Хранилище');
$title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
?>
<body data-page="module-dropbox" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-module-page"><?php crm_page_head([
    ['label' => $t('admin_module_detail.link_admin', 'Администрирование'), 'href' => 'index.php?route=admin'],
    ['label' => $t('admin_module_detail.link_modules', 'Модули'), 'href' => 'index.php?route=admin-modules'],
    ['label' => $titleRaw, 'active' => true],
], $titleRaw, 'Подключите Dropbox и передавайте выбранные файлы CRM в приватное хранилище.', '<a class="btn crm-btn-secondary" href="index.php?route=admin-modules" data-i18n="admin_module_detail.back_to_modules">' . htmlspecialchars($t('admin_module_detail.back_to_modules', 'Вернуться к модулям'), ENT_QUOTES, 'UTF-8') . '</a>'); ?>
<div class="dpbx-grid mt-3">
    <section class="crm-card dpbx-panel" aria-labelledby="dpbxStatusTitle"><div class="crm-card-header"><h2 id="dpbxStatusTitle" class="h5 mb-0">Состояние подключения</h2></div><div class="crm-card-body"><p id="dpbxStatusMeta" class="mb-1" role="status" aria-live="polite">Проверяем настройки Dropbox…</p><div id="dpbxAccount" class="small text-muted"></div><div id="dpbxMessage" class="small mt-3" role="status" aria-live="polite"></div></div></section>
    <section class="crm-card dpbx-panel" aria-labelledby="dpbxConfigTitle"><div class="crm-card-header"><h2 id="dpbxConfigTitle" class="h5 mb-0">Подключение Dropbox</h2></div><div class="crm-card-body"><p class="small text-muted">Используйте Dropbox App Key, App Secret и refresh token из OAuth-настройки приложения. Секреты шифруются через AES-256-GCM и никогда не показываются повторно. На сервере должен быть установлен APP_KEY длиной не менее 32 символов.</p>
        <form id="dpbxConfigForm" autocomplete="off" novalidate>
            <div class="crm-form-group"><label class="crm-form-label" for="dpbxAppKey">App Key</label><input class="crm-form-input" id="dpbxAppKey" name="app_key" type="text" maxlength="128" autocomplete="off" required></div>
            <div class="crm-form-group"><label class="crm-form-label" for="dpbxAppSecret">App Secret</label><input class="crm-form-input" id="dpbxAppSecret" name="app_secret" type="password" maxlength="4096" autocomplete="new-password" required></div>
            <div class="crm-form-group"><label class="crm-form-label" for="dpbxRefreshToken">Refresh token</label><input class="crm-form-input" id="dpbxRefreshToken" name="refresh_token" type="password" maxlength="4096" autocomplete="new-password" required></div>
            <div class="crm-form-group"><label class="crm-form-label" for="dpbxBasePath">Корневая папка Dropbox</label><input class="crm-form-input" id="dpbxBasePath" name="base_folder_path" type="text" maxlength="180" value="/TropaTT_CRM" autocomplete="off" required></div>
            <div class="d-flex flex-wrap gap-2"><button class="btn crm-btn-primary" id="dpbxSaveBtn" type="submit">Сохранить реквизиты</button><button class="btn crm-btn-secondary" id="dpbxTestBtn" type="button">Проверить подключение</button></div>
        </form></div></section>
    <section class="crm-card dpbx-panel" aria-labelledby="dpbxSyncTitle"><div class="crm-card-header"><h2 id="dpbxSyncTitle" class="h5 mb-0">Передать файл в Dropbox</h2></div><div class="crm-card-body"><p class="small text-muted">Передача доступна участникам рабочего пространства, имеющим права просмотра файла. Файлы карантина и файлы больше 20 МБ блокируются. Dropbox-ссылка общего доступа не создаётся.</p>
        <form id="dpbxSyncForm" autocomplete="off" novalidate><div class="crm-form-group"><label class="crm-form-label" for="dpbxFileId">Публичный ID файла CRM</label><input class="crm-form-input" id="dpbxFileId" name="file_public_id" type="text" maxlength="64" placeholder="fil_…" pattern="[A-Za-z0-9_-]{1,64}" required></div><button class="btn crm-btn-primary" id="dpbxSyncBtn" type="submit">Передать файл</button></form><div id="dpbxSyncResult" class="small mt-3" role="status" aria-live="polite"></div>
    </div></section>
</div></main></div></div>
