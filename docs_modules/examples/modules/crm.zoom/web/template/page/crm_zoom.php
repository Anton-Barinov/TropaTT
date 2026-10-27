<?php
declare(strict_types=1);
/** @var array $data */
$titleRaw = (string)($data['title'] ?? 'Zoom Meetings');
$title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
?>
<body data-page="module-zoom" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-module-page"><?php crm_page_head([
    ['label' => $t('admin_module_detail.link_admin', 'Администрирование'), 'href' => 'index.php?route=admin'],
    ['label' => $t('admin_module_detail.link_modules', 'Модули'), 'href' => 'index.php?route=admin-modules'],
    ['label' => $titleRaw, 'active' => true],
], $titleRaw, 'Создавайте видеовстречи Zoom из CRM и добавляйте их в календарь рабочего пространства.', '<a class="btn crm-btn-secondary" href="index.php?route=admin-modules" data-i18n="admin_module_detail.back_to_modules">' . htmlspecialchars($t('admin_module_detail.back_to_modules', 'Вернуться к модулям'), ENT_QUOTES, 'UTF-8') . '</a>'); ?>
<div class="zoom-grid mt-3">
    <section class="crm-card zoom-panel" aria-labelledby="zoomStatusTitle"><div class="crm-card-header"><h2 id="zoomStatusTitle" class="h5 mb-0">Состояние подключения</h2></div><div class="crm-card-body"><p id="zoomStatus" class="mb-1" role="status" aria-live="polite">Проверяем настройки…</p><p id="zoomAccount" class="small text-muted mb-0"></p><p id="zoomStatusMessage" class="small text-muted mt-2 mb-0"></p></div></section>
    <section class="crm-card zoom-panel" aria-labelledby="zoomSetupTitle"><div class="crm-card-header"><h2 id="zoomSetupTitle" class="h5 mb-0">Подключение Zoom</h2></div><div class="crm-card-body">
        <p class="small text-muted">Создайте в Zoom Marketplace внутреннее приложение типа Server-to-Server OAuth. Добавьте granular scopes <code>meeting:write:meeting:admin</code> и <code>user:read:user:admin</code>. Используйте Account ID, Client ID и Client Secret этого приложения; укажите Zoom User ID или email владельца встреч. Секрет шифруется AES-256-GCM ключом APP_KEY и не показывается после сохранения.</p>
        <form id="zoomConfigForm" autocomplete="off" novalidate>
            <div class="crm-form-group"><label class="crm-form-label" for="zoomAccountId">Account ID</label><input class="crm-form-input" id="zoomAccountId" name="account_id" maxlength="128" autocomplete="off" required></div>
            <div class="crm-form-group"><label class="crm-form-label" for="zoomClientId">Client ID</label><input class="crm-form-input" id="zoomClientId" name="client_id" maxlength="128" autocomplete="off" required></div>
            <div class="crm-form-group"><label class="crm-form-label" for="zoomClientSecret">Client Secret</label><input class="crm-form-input" id="zoomClientSecret" name="client_secret" type="password" maxlength="512" autocomplete="new-password" placeholder="Оставьте пустым, чтобы сохранить прежний секрет"></div>
            <div class="crm-form-group"><label class="crm-form-label" for="zoomHostUserId">Zoom User ID или email</label><input class="crm-form-input" id="zoomHostUserId" name="host_user_id" maxlength="128" autocomplete="off" required></div>
            <div class="d-flex flex-wrap gap-2"><button class="btn crm-btn-primary" id="zoomSaveBtn" type="submit">Сохранить настройки</button><button class="btn crm-btn-secondary" id="zoomTestBtn" type="button">Проверить подключение</button></div>
            <p id="zoomConfigMessage" class="small mt-3 mb-0" role="status" aria-live="polite"></p>
        </form></div></section>
    <section class="crm-card zoom-panel" aria-labelledby="zoomCreateTitle"><div class="crm-card-header"><h2 id="zoomCreateTitle" class="h5 mb-0">Создать встречу</h2></div><div class="crm-card-body">
        <form id="zoomMeetingForm" autocomplete="off" novalidate>
            <div class="crm-form-group"><label class="crm-form-label" for="zoomTopic">Тема</label><input class="crm-form-input" id="zoomTopic" name="topic" maxlength="200" required></div>
            <div class="crm-form-group"><label class="crm-form-label" for="zoomStart">Начало</label><input class="crm-form-input" id="zoomStart" name="start_time" type="datetime-local" required></div>
            <div class="crm-form-group"><label class="crm-form-label" for="zoomTimezone">Часовой пояс IANA</label><input class="crm-form-input" id="zoomTimezone" name="timezone" maxlength="64" value="UTC" required></div>
            <div class="crm-form-group"><label class="crm-form-label" for="zoomDuration">Длительность, минут</label><input class="crm-form-input" id="zoomDuration" name="duration" type="number" min="15" max="480" step="5" value="40" required></div>
            <div class="crm-form-group"><label class="crm-form-label" for="zoomAgenda">Повестка</label><textarea class="crm-form-input" id="zoomAgenda" name="agenda" rows="3" maxlength="2000"></textarea></div>
            <button class="btn crm-btn-primary" id="zoomCreateBtn" type="submit">Создать Zoom-встречу</button>
            <p id="zoomMeetingMessage" class="small mt-3 mb-0" role="status" aria-live="polite"></p>
        </form></div></section>
    <section class="crm-card zoom-panel" aria-labelledby="zoomMeetingsTitle"><div class="crm-card-header d-flex justify-content-between align-items-center"><h2 id="zoomMeetingsTitle" class="h5 mb-0">Встречи рабочего пространства</h2><button class="btn crm-btn-secondary btn-sm" id="zoomRefreshBtn" type="button">Обновить</button></div><div class="crm-card-body"><div id="zoomMeetings" class="zoom-meetings" role="list" aria-live="polite"><p class="small text-muted mb-0">Загрузка…</p></div></div></section>
    <p class="zoom-limit small text-muted mb-0">Сейчас поддерживаются создание встречи и запись события в календарь CRM. Записи, транскрипты, вебхуки и синхронизация завершённых встреч не реализованы.</p>
</div></main></div></div>
