<?php declare(strict_types=1); ?>
<?php $title = $t('login.title', 'TropaTT — Вход'); ?>
<?php $loginLocale = strtolower((string)($locale ?? 'ru-ru')); ?>
<body class="crm-login-page" data-page="login">
<main class="crm-login-wrap">
  <section class="crm-login-panel" aria-labelledby="loginPageTitle">
    <div class="crm-login-panel-inner">
      <div class="crm-login-brand">
        <img src="assets/icons/tableau.png" alt="<?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?>" class="crm-login-logo-wide">
        <span class="crm-login-brand-name"><span class="crm-brand-mark" aria-hidden="true"></span><span><?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></span></span>
      </div>
      <div class="crm-login-copy">
        <div class="crm-login-kicker" data-i18n="login.kicker"><?= htmlspecialchars($t('login.kicker', 'Локальная рабочая система'), ENT_QUOTES, 'UTF-8') ?></div>
        <h1 id="loginPageTitle" data-i18n="login.page_title"><?= htmlspecialchars($t('login.page_title', 'Вход в систему'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p data-i18n="login.subtitle"><?= htmlspecialchars($t('login.subtitle', 'Вход в рабочее пространство команды и быстрый переход к текущим задачам.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>

      <form id="loginForm" class="crm-login-card" action="javascript:void(0);" autocomplete="on">
        <div class="crm-login-field">
          <label class="form-label" for="loginInput" data-i18n="login.label_login"><?= htmlspecialchars($t('login.label_login', 'Логин'), ENT_QUOTES, 'UTF-8') ?></label>
          <input id="loginInput" class="form-control" type="text" name="login" placeholder="<?= htmlspecialchars($t('login.placeholder_login', 'Например: root'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="login.placeholder_login" autocomplete="username" value="">
        </div>
        <div class="crm-login-field">
          <label class="form-label" for="passwordInput" data-i18n="login.label_password"><?= htmlspecialchars($t('login.label_password', 'Пароль'), ENT_QUOTES, 'UTF-8') ?></label>
          <input id="passwordInput" class="form-control" type="password" name="password" placeholder="<?= htmlspecialchars($t('login.placeholder_password', '••••••••'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="login.placeholder_password" autocomplete="current-password" value="">
        </div>
        <div id="twoFactorLoginField" class="crm-login-field d-none">
          <label class="form-label" for="twoFactorCodeInput" data-i18n="login.two_factor_code_label"><?= htmlspecialchars($t('login.two_factor_code_label', 'Код подтверждения'), ENT_QUOTES, 'UTF-8') ?></label>
          <input id="twoFactorCodeInput" class="form-control" type="text" name="two_factor_code" inputmode="numeric" autocomplete="one-time-code" maxlength="10" placeholder="<?= htmlspecialchars($t('login.two_factor_code_placeholder', '6-значный код'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="login.two_factor_code_placeholder" value="">
          <div class="form-check mt-2"><input id="twoFactorBackupInput" class="form-check-input" type="checkbox" name="two_factor_backup"><label class="form-check-label" for="twoFactorBackupInput" data-i18n="login.two_factor_backup_label"><?= htmlspecialchars($t('login.two_factor_backup_label', 'Использовать резервный код'), ENT_QUOTES, 'UTF-8') ?></label></div>
          <button id="twoFactorBackBtn" class="btn btn-link p-0 mt-2" type="button" data-i18n="login.two_factor_back"><?= htmlspecialchars($t('login.two_factor_back', 'Вернуться к входу'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
        <div class="crm-login-field">
          <label class="form-label" for="loginLocaleSelect" data-i18n="login.label_locale"><?= htmlspecialchars($t('login.label_locale', 'Язык авторизации'), ENT_QUOTES, 'UTF-8') ?></label>
          <select class="form-select" name="locale" id="loginLocaleSelect">
            <option value="ru-ru"<?= $loginLocale === 'ru-ru' ? ' selected' : '' ?> data-i18n="login.option_ru"><?= htmlspecialchars($t('login.option_ru', 'Русский'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="en-gb"<?= $loginLocale === 'en-gb' ? ' selected' : '' ?> data-i18n="login.option_en"><?= htmlspecialchars($t('login.option_en', 'English'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="zh-cn"<?= $loginLocale === 'zh-cn' ? ' selected' : '' ?> data-i18n="login.option_zh"><?= htmlspecialchars($t('login.option_zh', '中文'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="es-es"<?= $loginLocale === 'es-es' ? ' selected' : '' ?> data-i18n="login.option_es"><?= htmlspecialchars($t('login.option_es', 'Español'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="fr-fr"<?= $loginLocale === 'fr-fr' ? ' selected' : '' ?> data-i18n="login.option_fr"><?= htmlspecialchars($t('login.option_fr', 'Français'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="pt-br"<?= $loginLocale === 'pt-br' ? ' selected' : '' ?> data-i18n="login.option_pt"><?= htmlspecialchars($t('login.option_pt', 'Português (Brasil)'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="de-de"<?= $loginLocale === 'de-de' ? ' selected' : '' ?> data-i18n="login.option_de"><?= htmlspecialchars($t('login.option_de', 'Deutsch'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
        </div>
        <div id="loginError" class="alert alert-danger d-none py-2"></div>
        <button type="submit" id="loginSubmitBtn" class="btn crm-btn-primary w-100" data-i18n="login.btn_login"><?= htmlspecialchars($t('login.btn_login', 'Войти'), ENT_QUOTES, 'UTF-8') ?></button>
        <div class="crm-login-hint" data-i18n="login.hint"><?= htmlspecialchars($t('login.hint', 'После входа откроется главный рабочий экран с задачами и проектами.'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="crm-login-links">
          <a href="index.php?route=password-reset-request" data-i18n="login.link_forgot_password"><?= htmlspecialchars($t('login.link_forgot_password', 'Забыли пароль?'), ENT_QUOTES, 'UTF-8') ?></a>
          <a href="index.php?route=invitation-accept" data-i18n="login.link_invitation"><?= htmlspecialchars($t('login.link_invitation', 'Принять приглашение'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
      </form>
    </div>
  </section>

  <section class="crm-login-cover" aria-labelledby="loginCoverTitle">
    <div class="crm-login-cover-inner">
      <div class="crm-login-cover-copy">
        <div class="crm-login-kicker" data-i18n="login.cover_kicker"><?= htmlspecialchars($t('login.cover_kicker', 'CRM · задачи · проекты · AI'), ENT_QUOTES, 'UTF-8') ?></div>
        <h2 id="loginCoverTitle" data-i18n="login.cover_title"><?= htmlspecialchars($t('login.cover_title', 'Рабочий центр задач и проектов'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p data-i18n="login.cover_text"><?= htmlspecialchars($t('login.cover_text', 'Командный контур с задачами, календарем, канбаном, гантом, комментариями, файлами и уведомлениями.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>

      <div class="crm-login-product-preview" aria-hidden="true">
        <div class="crm-login-preview-sidebar">
          <span></span><span></span><span></span><span></span>
        </div>
        <div class="crm-login-preview-main">
          <div class="crm-login-preview-top">
            <span></span>
            <div><i></i><i></i><i></i></div>
          </div>
          <div class="crm-login-preview-grid">
            <div class="crm-login-preview-metric"><strong>48</strong><span data-i18n="login.preview_tasks"><?= htmlspecialchars($t('login.preview_tasks', 'активных задач'), ENT_QUOTES, 'UTF-8') ?></span></div>
            <div class="crm-login-preview-metric"><strong>10</strong><span data-i18n="login.preview_projects"><?= htmlspecialchars($t('login.preview_projects', 'проектов'), ENT_QUOTES, 'UTF-8') ?></span></div>
            <div class="crm-login-preview-metric"><strong>AI</strong><span data-i18n="login.preview_ai"><?= htmlspecialchars($t('login.preview_ai', 'планирование'), ENT_QUOTES, 'UTF-8') ?></span></div>
          </div>
          <div class="crm-login-preview-table">
            <div><span></span><b></b><em></em></div>
            <div><span></span><b></b><em></em></div>
            <div><span></span><b></b><em></em></div>
            <div><span></span><b></b><em></em></div>
          </div>
        </div>
      </div>

      <div class="crm-login-proof-grid">
        <div>
          <strong data-i18n="login.proof_local_title"><?= htmlspecialchars($t('login.proof_local_title', 'Свои данные'), ENT_QUOTES, 'UTF-8') ?></strong>
          <span data-i18n="login.proof_local_text"><?= htmlspecialchars($t('login.proof_local_text', 'Хранение на вашем сервере и MySQL.'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div>
          <strong data-i18n="login.proof_shared_title"><?= htmlspecialchars($t('login.proof_shared_title', 'Shared hosting'), ENT_QUOTES, 'UTF-8') ?></strong>
          <span data-i18n="login.proof_shared_text"><?= htmlspecialchars($t('login.proof_shared_text', 'Работает без сложной инфраструктуры.'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div>
          <strong data-i18n="login.proof_ai_title"><?= htmlspecialchars($t('login.proof_ai_title', 'AI-инструменты'), ENT_QUOTES, 'UTF-8') ?></strong>
          <span data-i18n="login.proof_ai_text"><?= htmlspecialchars($t('login.proof_ai_text', 'Идеи, планы дня и проектные подсказки.'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>
    </div>
  </section>
</main>
