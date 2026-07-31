<?php declare(strict_types=1); ?>
<?php $title = $t('password_reset_request.title', 'TropaTT — Восстановление пароля'); ?>
<body data-page="password-reset-request">
<div class="crm-login-wrap">
  <section class="crm-login-panel">
    <div class="crm-brand mb-4"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div>
    <h1 class="h3 mb-2" data-i18n="password_reset_request.page_title"><?= htmlspecialchars($t('password_reset_request.page_title', 'Восстановление пароля'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="text-muted mb-4" data-i18n="password_reset_request.subtitle"><?= htmlspecialchars($t('password_reset_request.subtitle', 'Укажите логин или email. Если учетная запись существует, мы примем запрос на сброс.'), ENT_QUOTES, 'UTF-8') ?></p>

    <form id="passwordResetRequestForm" class="crm-card">
      <div class="mb-3">
        <label class="form-label" data-i18n="password_reset_request.label_identifier"><?= htmlspecialchars($t('password_reset_request.label_identifier', 'Логин или email'), ENT_QUOTES, 'UTF-8') ?></label>
        <input class="form-control" type="text" name="identifier" placeholder="<?= htmlspecialchars($t('password_reset_request.placeholder_identifier', 'Например: root или user@example.com'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="password_reset_request.placeholder_identifier" value="">
      </div>
      <div id="passwordResetRequestError" class="alert alert-danger d-none py-2"></div>
      <div id="passwordResetRequestSuccess" class="alert alert-success d-none py-2"></div>
      <button type="submit" class="btn crm-btn-primary w-100" data-i18n="password_reset_request.btn_submit"><?= htmlspecialchars($t('password_reset_request.btn_submit', 'Отправить запрос'), ENT_QUOTES, 'UTF-8') ?></button>
      <div class="small text-muted mt-3"><a href="index.php?route=login" data-i18n="password_reset_request.link_back_to_login"><?= htmlspecialchars($t('password_reset_request.link_back_to_login', 'Вернуться ко входу'), ENT_QUOTES, 'UTF-8') ?></a></div>
    </form>
  </section>
  <section class="crm-login-cover">
    <div class="crm-card">
      <h2 class="h4" data-i18n="password_reset_request.cover_title"><?= htmlspecialchars($t('password_reset_request.cover_title', 'Безопасность аккаунта'), ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="mb-0" data-i18n="password_reset_request.cover_text"><?= htmlspecialchars($t('password_reset_request.cover_text', 'Токен сброса не отображается в интерфейсе и не сохраняется в браузерном хранилище.'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </section>
</div>
