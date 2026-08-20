<?php declare(strict_types=1); ?>
<?php $title = $t('external_accept.title', 'TropaTT — Доступ в портал'); ?>
<body data-page="external-accept">
<div class="crm-login-wrap">
  <section class="crm-login-panel">
    <div class="crm-brand mb-4"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div>
    <h1 class="h3 mb-2" data-i18n="external_accept.page_title"><?= htmlspecialchars($t('external_accept.page_title', 'Доступ в клиентский портал'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="text-muted mb-4" data-i18n="external_accept.subtitle"><?= htmlspecialchars($t('external_accept.subtitle', 'Задайте пароль, чтобы получить доступ к своим проектам и задачам.'), ENT_QUOTES, 'UTF-8') ?></p>

    <form id="externalAcceptForm" class="crm-card">
      <input type="hidden" name="token" value="">
      <div class="mb-3">
        <label class="form-label" data-i18n="external_accept.label_password"><?= htmlspecialchars($t('external_accept.label_password', 'Пароль'), ENT_QUOTES, 'UTF-8') ?></label>
        <input class="form-control" type="password" name="password" placeholder="<?= htmlspecialchars($t('external_accept.placeholder_password', 'Минимум 8 символов'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="external_accept.placeholder_password" autocomplete="new-password" value="">
        <div class="form-text" data-i18n="external_accept.password_hint"><?= htmlspecialchars($t('external_accept.password_hint', 'Пароль должен содержать не менее 8 символов.'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div id="externalAcceptError" class="alert alert-danger d-none py-2"></div>
      <button type="submit" class="btn crm-btn-primary w-100" data-i18n="external_accept.btn_submit"><?= htmlspecialchars($t('external_accept.btn_submit', 'Активировать доступ'), ENT_QUOTES, 'UTF-8') ?></button>
      <div class="small text-muted mt-3"><a href="index.php?route=login" data-i18n="external_accept.link_back_to_login"><?= htmlspecialchars($t('external_accept.link_back_to_login', 'Вернуться ко входу'), ENT_QUOTES, 'UTF-8') ?></a></div>
    </form>
    <div id="externalAcceptSuccess" class="alert alert-success d-none mt-3" role="status" aria-live="polite">
      <div id="externalAcceptSuccessMessage"></div>
      <a id="externalAcceptLoginLink" class="btn crm-btn-primary mt-3 d-none" href="index.php?route=login" data-i18n="external_accept.link_back_to_login"><?= htmlspecialchars($t('external_accept.link_back_to_login', 'Войти в систему'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
  </section>
  <section class="crm-login-cover">
    <div class="crm-card">
      <h2 class="h4" data-i18n="external_accept.cover_title"><?= htmlspecialchars($t('external_accept.cover_title', 'Клиентский портал'), ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="mb-0" data-i18n="external_accept.cover_text"><?= htmlspecialchars($t('external_accept.cover_text', 'Здесь видны только проекты и задачи вашей компании — остальные разделы CRM скрыты.'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </section>
</div>
<script>
(function () {
  var form = document.getElementById('externalAcceptForm');
  if (!form) return;
  var tokenInput = form.querySelector('[name="token"]');
  if (tokenInput) {
    var params = new URLSearchParams(window.location.search);
    tokenInput.value = params.get('token') || '';
  }
  var errorBox = document.getElementById('externalAcceptError');
  var successBox = document.getElementById('externalAcceptSuccess');
  var successMessage = document.getElementById('externalAcceptSuccessMessage');
  var loginLink = document.getElementById('externalAcceptLoginLink');

  function t(key, fallback) {
    return (window.CRM && window.CRM.i18n && typeof window.CRM.i18n.t === 'function') ? window.CRM.i18n.t(key, fallback) : fallback;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (errorBox) errorBox.classList.add('d-none');
    if (successBox) successBox.classList.add('d-none');

    var token = String((tokenInput && tokenInput.value) || '').trim();
    var password = String((form.querySelector('[name="password"]') || {}).value || '');
    if (!token) {
      if (errorBox) { errorBox.textContent = t('external_accept.token_missing', 'Ссылка неполная — не найден токен приглашения.'); errorBox.classList.remove('d-none'); }
      return;
    }
    if (password.length < 8) {
      if (errorBox) { errorBox.textContent = t('external_accept.weak_password', 'Пароль должен содержать не менее 8 символов.'); errorBox.classList.remove('d-none'); }
      return;
    }

    var submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;

    (window.CRM && window.CRM.api ? window.CRM.api.request('api/v1/external-users/accept', {
      method: 'POST',
      auth: false,
      body: { token: token, password: password }
    }) : Promise.reject(new Error('no api'))).then(function (envelope) {
      var user = (envelope && envelope.data && envelope.data.user) || {};
      form.classList.add('d-none');
      if (successBox) {
        var login = String(user.login || '');
        var message = login
          ? t('external_accept.success_with_login', 'Доступ активирован. Ваш логин: ') + login + '. ' + t('external_accept.success_login_hint', 'Войдите в систему под этим логином и заданным паролем.')
          : t('external_accept.success', 'Доступ активирован. Теперь вы можете войти в систему.');
        if (successMessage) {
          successMessage.textContent = message;
        }
        successBox.classList.remove('d-none');
        if (loginLink) {
          loginLink.classList.remove('d-none');
        }
      }
    }).catch(function (error) {
      if (submitBtn) submitBtn.disabled = false;
      var envelope = error && error.envelope ? error.envelope : null;
      var message = (envelope && envelope.message) || t('external_accept.accept_failed', 'Не удалось активировать доступ. Проверьте ссылку и попробуйте снова.');
      if (errorBox) { errorBox.textContent = message; errorBox.classList.remove('d-none'); }
    });
  });
})();
</script>
</body>
