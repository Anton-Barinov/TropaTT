<?php
declare(strict_types=1);
/** @var array<string, mixed> $data */
$titleRaw = (string)($data['title'] ?? 'Google Drive');
?>
<body data-page="module-google-drive" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-module-page"><?php crm_page_head([
    ['label' => $t('admin_module_detail.link_admin', 'Администрирование'), 'href' => 'index.php?route=admin'],
    ['label' => $t('admin_module_detail.link_modules', 'Модули'), 'href' => 'index.php?route=admin-modules'],
    ['label' => $titleRaw, 'active' => true],
], $titleRaw, 'Приватная передача файлов CRM в Google Drive выбранного рабочего пространства.', '<a class="btn crm-btn-secondary" href="index.php?route=admin-modules" data-i18n="admin_module_detail.back_to_modules">' . htmlspecialchars($t('admin_module_detail.back_to_modules', 'Вернуться к модулям'), ENT_QUOTES, 'UTF-8') . '</a>'); ?>
<div class="crm-google-drive" id="gdrvPage" aria-live="polite">
    <section class="crm-card mt-3" aria-labelledby="gdrvStatusTitle"><div class="crm-card-header"><h2 id="gdrvStatusTitle" class="h5 mb-0">Состояние подключения</h2></div><div class="crm-card-body"><p id="gdrvStatusMeta" class="mb-2" role="status">Загружаем состояние…</p><p class="small text-muted mb-0">Файлы остаются приватными. Модуль не выдаёт публичный доступ и не заменяет штатное хранилище CRM.</p><div id="gdrvMessage" class="small mt-3" role="status" aria-live="polite"></div></div></section>
    <section class="crm-card mt-3" aria-labelledby="gdrvSettingsTitle"><div class="crm-card-header"><h2 id="gdrvSettingsTitle" class="h5 mb-0">Настройки Google OAuth</h2></div><div class="crm-card-body">
        <p class="small text-muted">Создайте OAuth-клиент Google Drive API с offline-доступом и вставьте client ID, client secret и refresh token. Секреты хранятся зашифрованно и повторно не показываются.</p>
        <form id="gdrvConfigForm" autocomplete="off" novalidate>
            <div class="crm-form-group"><label class="crm-form-label" for="gdrvClientId">OAuth Client ID</label><input class="crm-form-input" id="gdrvClientId" name="client_id" type="text" maxlength="8192" autocomplete="off" required></div>
            <div class="crm-form-group"><label class="crm-form-label" for="gdrvClientSecret">OAuth Client Secret</label><input class="crm-form-input" id="gdrvClientSecret" name="client_secret" type="password" maxlength="8192" autocomplete="new-password" required></div>
            <div class="crm-form-group"><label class="crm-form-label" for="gdrvRefreshToken">OAuth Refresh Token</label><input class="crm-form-input" id="gdrvRefreshToken" name="refresh_token" type="password" maxlength="8192" autocomplete="new-password" required></div>
            <div class="crm-form-group"><label class="crm-form-label" for="gdrvFolderId">ID папки Google Drive (необязательно)</label><input class="crm-form-input" id="gdrvFolderId" name="root_folder_id" type="text" maxlength="128" autocomplete="off"><div class="form-text">Если оставить пустым, файл загрузится в корень Google Drive подключённой учётной записи.</div></div>
            <div class="d-flex flex-wrap gap-2 mt-3"><button class="btn crm-btn-primary" id="gdrvSaveBtn" type="submit">Сохранить настройки</button><button class="btn crm-btn-secondary" id="gdrvTestBtn" type="button" disabled>Проверить подключение</button></div>
        </form>
    </div></section>
    <section class="crm-card mt-3" aria-labelledby="gdrvUploadTitle"><div class="crm-card-header"><h2 id="gdrvUploadTitle" class="h5 mb-0">Передача файла CRM</h2></div><div class="crm-card-body"><p class="small text-muted">Передаётся только файл, доступный текущему пользователю в активном рабочем пространстве. Размер — до 20 МБ. Передача выполняется вручную.</p><form id="gdrvUploadForm" class="d-flex flex-wrap gap-2 align-items-end" novalidate><div class="crm-form-group flex-grow-1 mb-0"><label class="crm-form-label" for="gdrvFileId">ID файла CRM</label><input class="crm-form-input" id="gdrvFileId" name="file_public_id" type="text" maxlength="64" placeholder="fil_…" required></div><button class="btn crm-btn-secondary" id="gdrvUploadBtn" type="submit" disabled>Передать в Google Drive</button></form><div id="gdrvUploadResult" class="small mt-3" role="status" aria-live="polite"></div></div></section>
</div></main></div></div>
