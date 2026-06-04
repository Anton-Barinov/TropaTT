<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Принятие приглашения'; ?>
<body data-page="invitation-accept">
<div class="crm-login-wrap">
  <section class="crm-login-panel">
    <div class="crm-brand mb-4"><span class="crm-brand-mark"></span> TropaTT</div>
    <h1 class="h3 mb-2">Принятие приглашения</h1>
    <p class="text-muted mb-4">Завершите создание учетной записи по приглашению администратора.</p>

    <form id="invitationAcceptForm" class="crm-card">
      <div class="mb-3">
        <label class="form-label">Токен приглашения</label>
        <input class="form-control" type="text" name="invitation_token" placeholder="invitation token" autocomplete="off" value="">
      </div>
      <div class="mb-3">
        <label class="form-label">Логин</label>
        <input class="form-control" type="text" name="login" placeholder="Логин" value="">
      </div>
      <div class="mb-3">
        <label class="form-label">ФИО</label>
        <input class="form-control" type="text" name="full_name" placeholder="Полное имя" value="">
      </div>
      <div class="mb-3">
        <label class="form-label">Пароль</label>
        <input class="form-control" type="password" name="password" placeholder="Минимум 8 символов" value="">
      </div>
      <div id="invitationAcceptError" class="alert alert-danger d-none py-2"></div>
      <div id="invitationAcceptSuccess" class="alert alert-success d-none py-2"></div>
      <button type="submit" class="btn crm-btn-primary w-100">Принять приглашение</button>
      <div class="small text-muted mt-3"><a href="index.php?route=login">Вернуться ко входу</a></div>
    </form>
  </section>
  <section class="crm-login-cover">
    <div class="crm-card">
      <h2 class="h4">Доступ в рабочее пространство</h2>
      <p class="mb-0">После принятия приглашения можно сразу авторизоваться под новым логином.</p>
    </div>
  </section>
</div>
