<?php
declare(strict_types=1);
/** @var array $data */
$titleRaw = (string)($data['title'] ?? 'WhatsApp Business');
$title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
?>
<body data-page="module-whatsapp-business" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-module-page"><?php crm_page_head([
    ['label' => $t('admin_module_detail.link_admin', 'Администрирование'), 'href' => 'index.php?route=admin'],
    ['label' => $t('admin_module_detail.link_modules', 'Модули'), 'href' => 'index.php?route=admin-modules'],
    ['label' => $titleRaw, 'active' => true],
], $titleRaw, 'Очередь обращений WhatsApp Business Platform.', '<a class="btn crm-btn-secondary" href="index.php?route=admin-modules" data-i18n="admin_module_detail.back_to_modules">' . htmlspecialchars($t('admin_module_detail.back_to_modules', 'Вернуться к модулям'), ENT_QUOTES, 'UTF-8') . '</a>'); ?>
<section class="crm-card mt-3" aria-labelledby="whatsappConfigTitle">
  <div class="crm-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <h2 class="h5 mb-0" id="whatsappConfigTitle">Подключение Meta WhatsApp Business</h2>
    <span class="badge text-bg-secondary" id="wappStatusBadge" role="status">Проверка…</span>
  </div>
  <div class="crm-card-body">
    <p class="text-muted">Настройки сохраняются отдельно в активном рабочем пространстве. Access Token, Verify Token и App Secret шифруются на сервере и не возвращаются в браузер.</p>
    <div class="alert alert-info" id="wappCapabilityNotice" role="status">Проверяю состояние подключения…</div>
    <form id="wappConfigForm" class="row g-3" autocomplete="off">
      <div class="col-12 col-md-6"><label class="form-label" for="wappPhoneNumberId">Phone Number ID</label><input class="form-control" id="wappPhoneNumberId" name="phone_number_id" maxlength="64" required></div>
      <div class="col-12 col-md-6"><label class="form-label" for="wappWabaId">WhatsApp Business Account ID</label><input class="form-control" id="wappWabaId" name="waba_id" maxlength="64" required></div>
      <div class="col-12"><label class="form-label" for="wappApiToken">Permanent Access Token</label><input class="form-control" id="wappApiToken" name="api_token" type="password" maxlength="4096" autocomplete="new-password" required><div class="form-text">Токен показывается только при вводе и никогда не возвращается из API.</div></div>
      <div class="col-12 col-md-6"><label class="form-label" for="wappVerifyToken">Webhook Verify Token</label><input class="form-control" id="wappVerifyToken" name="verify_token" type="password" maxlength="48" autocomplete="new-password" required><div class="form-text">До 48 символов; хранится зашифрованным.</div></div>
      <div class="col-12 col-md-6"><label class="form-label" for="wappAppSecret">Meta App Secret</label><input class="form-control" id="wappAppSecret" name="app_secret" type="password" maxlength="4096" autocomplete="new-password" required></div>
      <div class="col-12"><label class="form-label" for="wappWebhookUrl">Webhook URL</label><div class="input-group"><input class="form-control" id="wappWebhookUrl" readonly><button class="btn crm-btn-secondary" id="wappCopyWebhook" type="button">Копировать</button></div><div class="form-text">В Meta укажите этот URL и Verify Token. Webhook принимает только запросы с корректной подписью App Secret. Исходящие ответы доступны в пределах 24-часового окна сервиса WhatsApp.</div></div>
      <div class="col-12 d-flex flex-wrap gap-2"><button class="btn crm-btn-primary" id="wappSaveBtn" type="submit">Зашифровать и сохранить</button><button class="btn crm-btn-secondary" id="wappRefreshBtn" type="button">Обновить состояние</button></div>
    </form>
    <div id="wappMessage" class="small mt-3" role="status" aria-live="polite"></div>
  </div>
</section>
<section class="crm-card mt-3" aria-labelledby="wappInboxTitle">
  <div class="crm-card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><h2 class="h5 mb-0" id="wappInboxTitle">Входящие диалоги</h2><button class="btn crm-btn-secondary" id="wappInboxRefresh" type="button">Обновить список</button></div>
  <div class="crm-card-body"><div id="wappInboxState" class="text-muted" role="status" aria-live="polite">Загрузка диалогов…</div><div class="wapp-inbox-layout"><div id="wappInboxList" class="d-grid gap-2 mt-2"></div><section id="wappConversation" class="wapp-conversation" aria-label="Диалог WhatsApp"><div class="text-muted">Выберите диалог, чтобы просмотреть переписку.</div></section></div></div>
</section>
</main></div></div>
