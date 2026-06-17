<?php declare(strict_types=1); ?>
<?php $title = $t('password_reset_confirm.title', 'TropaTT — Сброс пароля'); ?>
<body data-page="password-reset-confirm">
<div class="crm-login-wrap">
  <section class="crm-login-panel">
    <div class="crm-brand mb-4"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div>
    <h1 class="h3 mb-2" data-i18n="password_reset_confirm.page_title"><?= htmlspecialchars($t('password_reset_confirm.page_title', 'Новый пароль'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="text-muted mb-4" data-i18n="password_reset_confirm.subtitle"><?= htmlspecialchars($t('password_reset_confirm.subtitle', 'Введите новый пароль. Токен сброса используется только в этом запросе.'), ENT_QUOTES, 'UTF-8') ?></p>

    <form id="passwordResetConfirmForm" class="crm-card">
      <div class="mb-3">
        <label class="form-label" data-i18n="password_reset_confirm.label_token"><?= htmlspecialchars($t('password_reset_confirm.label_token', 'Токен сброса'), ENT_QUOTES, 'UTF-8') ?></label>
        <input class="form-control" type="text" name="reset_token" placeholder="<?= htmlspecialchars($t('password_reset_confirm.placeholder_token', 'reset token'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="password_reset_confirm.placeholder_token" autocomplete="off" value="">
      </div>
      <div class="mb-3">
        <label class="form-label" data-i18n="password_reset_confirm.label_password"><?= htmlspecialchars($t('password_reset_confirm.label_password', 'Новый пароль'), ENT_QUOTES, 'UTF-8') ?></label>
        <input class="form-control" type="password" name="new_password" placeholder="<?= htmlspecialchars($t('password_reset_confirm.placeholder_password', 'Минимум 8 символов'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="password_reset_confirm.placeholder_password" value="">
      </div>
      <div id="passwordResetConfirmError" class="alert alert-danger d-none py-2"></div>
      <div id="passwordResetConfirmSuccess" class="alert alert-success d-none py-2"></div>
      <button type="submit" class="btn crm-btn-primary w-100" data-i18n="password_reset_confirm.btn_submit"><?= htmlspecialchars($t('password_reset_confirm.btn_submit', 'Сбросить пароль'), ENT_QUOTES, 'UTF-8') ?></button>
      <div class="small text-muted mt-3"><a href="index.php?route=login" data-i18n="password_reset_confirm.link_back_to_login"><?= htmlspecialchars($t('password_reset_confirm.link_back_to_login', 'Вернуться ко входу'), ENT_QUOTES, 'UTF-8') ?></a></div>
    </form>
  </section>
  <section class="crm-login-cover">
    <div class="crm-card">
      <h2 class="h4" data-i18n="password_reset_confirm.cover_title"><?= htmlspecialchars($t('password_reset_confirm.cover_title', 'Проверка токена'), ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="mb-0" data-i18n="password_reset_confirm.cover_text"><?= htmlspecialchars($t('password_reset_confirm.cover_text', 'Для просроченного или недействительного токена показывается безопасная ошибка без раскрытия лишних деталей.'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </section>
</div>
