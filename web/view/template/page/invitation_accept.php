<?php declare(strict_types=1); ?>
<?php $title = $t('invitation_accept.title', 'TropaTT — Принятие приглашения'); ?>
<body data-page="invitation-accept">
<div class="crm-login-wrap">
  <section class="crm-login-panel">
    <div class="crm-brand mb-4"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div>
    <h1 class="h3 mb-2" data-i18n="invitation_accept.page_title"><?= htmlspecialchars($t('invitation_accept.page_title', 'Принятие приглашения'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="text-muted mb-4" data-i18n="invitation_accept.subtitle"><?= htmlspecialchars($t('invitation_accept.subtitle', 'Завершите создание учетной записи по приглашению администратора.'), ENT_QUOTES, 'UTF-8') ?></p>

    <form id="invitationAcceptForm" class="crm-card">
      <div class="mb-3">
        <label class="form-label" data-i18n="invitation_accept.label_token"><?= htmlspecialchars($t('invitation_accept.label_token', 'Токен приглашения'), ENT_QUOTES, 'UTF-8') ?></label>
        <input class="form-control" type="text" name="invitation_token" placeholder="<?= htmlspecialchars($t('invitation_accept.placeholder_token', 'invitation token'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="invitation_accept.placeholder_token" autocomplete="off" value="">
      </div>
      <div class="mb-3">
        <label class="form-label" data-i18n="invitation_accept.label_login"><?= htmlspecialchars($t('invitation_accept.label_login', 'Логин'), ENT_QUOTES, 'UTF-8') ?></label>
        <input class="form-control" type="text" name="login" placeholder="<?= htmlspecialchars($t('invitation_accept.placeholder_login', 'Логин'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="invitation_accept.placeholder_login" value="">
      </div>
      <div class="mb-3">
        <label class="form-label" data-i18n="invitation_accept.label_full_name"><?= htmlspecialchars($t('invitation_accept.label_full_name', 'ФИО'), ENT_QUOTES, 'UTF-8') ?></label>
        <input class="form-control" type="text" name="full_name" placeholder="<?= htmlspecialchars($t('invitation_accept.placeholder_full_name', 'Полное имя'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="invitation_accept.placeholder_full_name" value="">
      </div>
      <div class="mb-3">
        <label class="form-label" data-i18n="invitation_accept.label_password"><?= htmlspecialchars($t('invitation_accept.label_password', 'Пароль'), ENT_QUOTES, 'UTF-8') ?></label>
        <input class="form-control" type="password" name="password" placeholder="<?= htmlspecialchars($t('invitation_accept.placeholder_password', 'Минимум 12 символов: A-Z, a-z, 0-9'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="invitation_accept.placeholder_password" value="">
        <div class="form-text" data-i18n="invitation_accept.password_hint"><?= htmlspecialchars($t('invitation_accept.password_hint', 'Пароль должен содержать не менее 12 символов, включая заглавные и строчные буквы и цифры.'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div id="invitationAcceptError" class="alert alert-danger d-none py-2"></div>
      <div id="invitationAcceptSuccess" class="alert alert-success d-none py-2"></div>
      <button type="submit" class="btn crm-btn-primary w-100" data-i18n="invitation_accept.btn_submit"><?= htmlspecialchars($t('invitation_accept.btn_submit', 'Принять приглашение'), ENT_QUOTES, 'UTF-8') ?></button>
      <div class="small text-muted mt-3"><a href="index.php?route=login" data-i18n="invitation_accept.link_back_to_login"><?= htmlspecialchars($t('invitation_accept.link_back_to_login', 'Вернуться ко входу'), ENT_QUOTES, 'UTF-8') ?></a></div>
    </form>
  </section>
  <section class="crm-login-cover">
    <div class="crm-card">
      <h2 class="h4" data-i18n="invitation_accept.cover_title"><?= htmlspecialchars($t('invitation_accept.cover_title', 'Доступ в рабочее пространство'), ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="mb-0" data-i18n="invitation_accept.cover_text"><?= htmlspecialchars($t('invitation_accept.cover_text', 'После принятия приглашения можно сразу авторизоваться под новым логином.'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </section>
</div>
