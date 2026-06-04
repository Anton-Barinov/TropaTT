<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Восстановление пароля'; ?>
<body data-page="password-reset-request">
<div class="crm-login-wrap">
  <section class="crm-login-panel">
    <div class="crm-brand mb-4"><span class="crm-brand-mark"></span> TropaTT</div>
    <h1 class="h3 mb-2">Восстановление пароля</h1>
    <p class="text-muted mb-4">Укажите логин или email. Если учетная запись существует, мы примем запрос на сброс.</p>

    <form id="passwordResetRequestForm" class="crm-card">
      <div class="mb-3">
        <label class="form-label">Логин или email</label>
        <input class="form-control" type="text" name="identifier" placeholder="Например: root или user@example.com" value="">
      </div>
      <div id="passwordResetRequestError" class="alert alert-danger d-none py-2"></div>
      <div id="passwordResetRequestSuccess" class="alert alert-success d-none py-2"></div>
      <button type="submit" class="btn crm-btn-primary w-100">Отправить запрос</button>
      <div class="small text-muted mt-3"><a href="index.php?route=login">Вернуться ко входу</a></div>
    </form>
  </section>
  <section class="crm-login-cover">
    <div class="crm-card">
      <h2 class="h4">Безопасность аккаунта</h2>
      <p class="mb-0">Токен сброса не отображается в интерфейсе и не сохраняется в браузерном хранилище.</p>
    </div>
  </section>
</div>
