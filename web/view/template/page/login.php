<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Вход'; ?>
<body data-page="login">
<div class="crm-login-wrap">
  <section class="crm-login-panel">
    <div class="crm-brand mb-4"><span class="crm-brand-mark"></span> TropaTT</div>
    <h1 class="h3 mb-2">Вход в систему</h1>
    <p class="text-muted mb-4">Вход в рабочее пространство команды и быстрый переход к текущим задачам.</p>

    <form id="loginForm" class="crm-card" action="javascript:void(0);">
      <div class="mb-3">
        <label class="form-label">Логин</label>
        <input class="form-control" type="text" name="login" placeholder="Например: root" value="">
      </div>
      <div class="mb-3">
        <label class="form-label">Пароль</label>
        <input class="form-control" type="password" name="password" placeholder="••••••••" value="">
      </div>
      <div class="mb-3">
        <label class="form-label">Язык авторизации</label>
        <select class="form-select" name="locale" id="loginLocaleSelect">
          <option value="ru-ru" selected>Русский</option>
          <option value="en-gb">English</option>
        </select>
      </div>
      <div id="loginError" class="alert alert-danger d-none py-2"></div>
      <button type="submit" id="loginSubmitBtn" class="btn crm-btn-primary w-100">Войти</button>
      <div class="small text-muted mt-3">После входа откроется главный рабочий экран с задачами и проектами.</div>
      <div class="small mt-2 d-flex flex-column gap-1">
        <a href="index.php?route=password-reset-request">Забыли пароль?</a>
        <a href="index.php?route=invitation-accept">Принять приглашение</a>
      </div>
    </form>

    
  </section>
  <section class="crm-login-cover">
    <div class="crm-card">
      <h2 class="h3">Рабочий центр задач и проектов</h2>
      <p class="mb-4">Командный контур с задачами, календарем, канбаном, гантом, комментариями, файлами и уведомлениями.</p>
      <div class="row g-3">
        <div class="col-6"><div class="bg-white bg-opacity-10 rounded-3 p-3"><small>Сценарий U2</small><div class="h5 mb-0">Создание проекта</div></div></div>
        <div class="col-6"><div class="bg-white bg-opacity-10 rounded-3 p-3"><small>Сценарий U3-U4</small><div class="h5 mb-0">Задача и статус</div></div></div>
        <div class="col-6"><div class="bg-white bg-opacity-10 rounded-3 p-3"><small>Сценарий U5</small><div class="h5 mb-0">Комментарии</div></div></div>
        <div class="col-6"><div class="bg-white bg-opacity-10 rounded-3 p-3"><small>Сценарий U6</small><div class="h5 mb-0">Файлы</div></div></div>
      </div>
    </div>
  </section>
</div>
