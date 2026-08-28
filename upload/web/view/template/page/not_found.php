<?php declare(strict_types=1); ?>
<?php $title = $t('not_found.title', '404 Not Found'); ?>
<body data-page="not-found">
<div class="container py-5">
  <section class="card border-danger">
    <div class="card-body">
      <h1 class="h3 text-danger" data-i18n="not_found.page_title"><?= htmlspecialchars($t('not_found.page_title', '404 — Страница не найдена'), ENT_QUOTES, 'UTF-8') ?></h1>
<?php if (!empty($moduleHint)): ?>
      <p class="mb-2"><?= htmlspecialchars($t('not_found.module_inactive', 'Этот модуль не активирован. Перейдите в Администрирование → Модули и нажмите «Активировать».'), ENT_QUOTES, 'UTF-8') ?></p>
      <p class="mb-0">
        <button id="activateModuleBtn" class="btn btn-sm crm-btn-primary" data-module="<?= htmlspecialchars($moduleHint, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t('not_found.activate_module', 'Активировать модуль'), ENT_QUOTES, 'UTF-8') ?></button>
        <a href="index.php?route=admin-modules" class="btn btn-sm crm-btn-secondary ms-2"><?= htmlspecialchars($t('not_found.go_modules', 'Перейти к модулям'), ENT_QUOTES, 'UTF-8') ?></a>
      </p>
      <div id="activateResult" class="mt-2" style="display:none"></div>
      <script>
      document.getElementById('activateModuleBtn').addEventListener('click', async function() {
        var btn = this;
        var module = btn.dataset.module;
        var result = document.getElementById('activateResult');
        btn.disabled = true;
        btn.textContent = '…';
        result.style.display = 'block';
        result.innerHTML = '<span class="text-muted">Активация ' + module + '…</span>';
        try {
          var resp = await fetch('/api/index.php?route=api/v1/modules/' + module + '/activate', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'same-origin'
          });
          var data = await resp.json();
          if (data.success) {
            result.innerHTML = '<span class="text-success">✓ Модуль активирован. <a href="/web/index.php?route=' + encodeURIComponent('module-' + module) + '">Открыть модуль →</a></span>';
          } else {
            result.innerHTML = '<span class="text-danger">✗ ' + (data.message || 'Ошибка активации') + '</span>';
            btn.disabled = false;
            btn.textContent = 'Активировать модуль';
          }
        } catch (e) {
          result.innerHTML = '<span class="text-danger">✗ Ошибка сети</span>';
          btn.disabled = false;
          btn.textContent = 'Активировать модуль';
        }
      });
      </script>
<?php else: ?>
      <p class="mb-0"><?= htmlspecialchars($t('not_found.message', 'Не найден маршрут:'), ENT_QUOTES, 'UTF-8') ?> <code><?= htmlspecialchars((string)$route, ENT_QUOTES, 'UTF-8') ?></code></p>
<?php endif; ?>
    </div>
  </section>
</div>
