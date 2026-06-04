<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Сброс пароля'; ?>
<body data-page="password-reset-confirm">
<div class="crm-login-wrap">
  <section class="crm-login-panel">
    <div class="crm-brand mb-4"><span class="crm-brand-mark"></span> TropaTT</div>
    <h1 class="h3 mb-2">Новый пароль</h1>
    <p class="text-muted mb-4">Введите новый пароль. Токен сброса используется только в этом запросе.</p>

    <form id="passwordResetConfirmForm" class="crm-card">
      <div class="mb-3">
        <label class="form-label">Токен сброса</label>
        <input class="form-control" type="text" name="reset_token" placeholder="reset token" autocomplete="off" value="">
      </div>
      <div class="mb-3">
        <label class="form-label">Новый пароль</label>
        <input class="form-control" type="password" name="new_password" placeholder="Минимум 8 символов" value="">
      </div>
      <div id="passwordResetConfirmError" class="alert alert-danger d-none py-2"></div>
      <div id="passwordResetConfirmSuccess" class="alert alert-success d-none py-2"></div>
      <button type="submit" class="btn crm-btn-primary w-100">Сбросить пароль</button>
      <div class="small text-muted mt-3"><a href="index.php?route=login">Вернуться ко входу</a></div>
    </form>
  </section>
  <section class="crm-login-cover">
    <div class="crm-card">
      <h2 class="h4">Проверка токена</h2>
      <p class="mb-0">Для просроченного или недействительного токена показывается безопасная ошибка без раскрытия лишних деталей.</p>
    </div>
  </section>
</div>
