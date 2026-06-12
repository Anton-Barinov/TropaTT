<?php declare(strict_types=1); ?>
<?php $title = $t('login.title', 'TropaTT — Вход'); ?>
<body data-page="login">
<div class="crm-login-wrap">
  <section class="crm-login-panel">
    <div class="crm-brand mb-4"><span class="crm-brand-mark"></span> TropaTT</div>
    <h1 class="h3 mb-2" data-i18n="login.page_title"><?= htmlspecialchars($t('login.page_title', 'Вход в систему'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="text-muted mb-4" data-i18n="login.subtitle"><?= htmlspecialchars($t('login.subtitle', 'Вход в рабочее пространство команды и быстрый переход к текущим задачам.'), ENT_QUOTES, 'UTF-8') ?></p>

    <form id="loginForm" class="crm-card" action="javascript:void(0);">
      <div class="mb-3">
        <label class="form-label" data-i18n="login.label_login"><?= htmlspecialchars($t('login.label_login', 'Логин'), ENT_QUOTES, 'UTF-8') ?></label>
        <input class="form-control" type="text" name="login" placeholder="<?= htmlspecialchars($t('login.placeholder_login', 'Например: root'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="login.placeholder_login" value="">
      </div>
      <div class="mb-3">
        <label class="form-label" data-i18n="login.label_password"><?= htmlspecialchars($t('login.label_password', 'Пароль'), ENT_QUOTES, 'UTF-8') ?></label>
        <input class="form-control" type="password" name="password" placeholder="<?= htmlspecialchars($t('login.placeholder_password', '••••••••'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="login.placeholder_password" value="">
      </div>
      <div class="mb-3">
        <label class="form-label" data-i18n="login.label_locale"><?= htmlspecialchars($t('login.label_locale', 'Язык авторизации'), ENT_QUOTES, 'UTF-8') ?></label>
        <select class="form-select" name="locale" id="loginLocaleSelect">
          <option value="ru-ru" selected data-i18n="login.option_ru"><?= htmlspecialchars($t('login.option_ru', 'Русский'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="en-gb" data-i18n="login.option_en"><?= htmlspecialchars($t('login.option_en', 'English'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="zh-cn" data-i18n="login.option_zh"><?= htmlspecialchars($t('login.option_zh', '中文'), ENT_QUOTES, 'UTF-8') ?></option>
        </select>
      </div>
      <div id="loginError" class="alert alert-danger d-none py-2"></div>
      <button type="submit" id="loginSubmitBtn" class="btn crm-btn-primary w-100" data-i18n="login.btn_login"><?= htmlspecialchars($t('login.btn_login', 'Войти'), ENT_QUOTES, 'UTF-8') ?></button>
      <div class="small text-muted mt-3" data-i18n="login.hint"><?= htmlspecialchars($t('login.hint', 'После входа откроется главный рабочий экран с задачами и проектами.'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="small mt-2 d-flex flex-column gap-1">
        <a href="index.php?route=password-reset-request" data-i18n="login.link_forgot_password"><?= htmlspecialchars($t('login.link_forgot_password', 'Забыли пароль?'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="index.php?route=invitation-accept" data-i18n="login.link_invitation"><?= htmlspecialchars($t('login.link_invitation', 'Принять приглашение'), ENT_QUOTES, 'UTF-8') ?></a>
      </div>
    </form>

    
  </section>
  <section class="crm-login-cover">
    <div class="crm-card">
      <h2 class="h3" data-i18n="login.cover_title"><?= htmlspecialchars($t('login.cover_title', 'Рабочий центр задач и проектов'), ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="mb-4" data-i18n="login.cover_text"><?= htmlspecialchars($t('login.cover_text', 'Командный контур с задачами, календарем, канбаном, гантом, комментариями, файлами и уведомлениями.'), ENT_QUOTES, 'UTF-8') ?></p>
      <div class="row g-3">
        <div class="col-6"><div class="bg-white bg-opacity-10 rounded-3 p-3"><small data-i18n="login.cover_scenario_u2"><?= htmlspecialchars($t('login.cover_scenario_u2', 'Сценарий U2'), ENT_QUOTES, 'UTF-8') ?></small><div class="h5 mb-0" data-i18n="login.cover_scenario_u2_title"><?= htmlspecialchars($t('login.cover_scenario_u2_title', 'Создание проекта'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
        <div class="col-6"><div class="bg-white bg-opacity-10 rounded-3 p-3"><small data-i18n="login.cover_scenario_u3_u4"><?= htmlspecialchars($t('login.cover_scenario_u3_u4', 'Сценарий U3-U4'), ENT_QUOTES, 'UTF-8') ?></small><div class="h5 mb-0" data-i18n="login.cover_scenario_u3_u4_title"><?= htmlspecialchars($t('login.cover_scenario_u3_u4_title', 'Задача и статус'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
        <div class="col-6"><div class="bg-white bg-opacity-10 rounded-3 p-3"><small data-i18n="login.cover_scenario_u5"><?= htmlspecialchars($t('login.cover_scenario_u5', 'Сценарий U5'), ENT_QUOTES, 'UTF-8') ?></small><div class="h5 mb-0" data-i18n="login.cover_scenario_u5_title"><?= htmlspecialchars($t('login.cover_scenario_u5_title', 'Комментарии'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
        <div class="col-6"><div class="bg-white bg-opacity-10 rounded-3 p-3"><small data-i18n="login.cover_scenario_u6"><?= htmlspecialchars($t('login.cover_scenario_u6', 'Сценарий U6'), ENT_QUOTES, 'UTF-8') ?></small><div class="h5 mb-0" data-i18n="login.cover_scenario_u6_title"><?= htmlspecialchars($t('login.cover_scenario_u6_title', 'Файлы'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      </div>
    </div>
  </section>
</div>
