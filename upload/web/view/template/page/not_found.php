<?php declare(strict_types=1); ?>
<?php $title = $t('not_found.title', '404 Not Found'); ?>
<body data-page="not-found">
<div class="container py-5">
  <section class="card border-danger">
    <div class="card-body">
      <h1 class="h3 text-danger" data-i18n="not_found.page_title"><?= htmlspecialchars($t('not_found.page_title', '404 — Страница не найдена'), ENT_QUOTES, 'UTF-8') ?></h1>
<?php if (!empty($moduleHint)): ?>
      <p class="mb-2"><?= htmlspecialchars($t('not_found.module_inactive', 'Этот модуль не активирован. Перейдите в Администрирование → Модули и нажмите «Активировать».'), ENT_QUOTES, 'UTF-8') ?></p>
      <p class="mb-0"><a href="index.php?route=admin-modules" class="btn btn-sm crm-btn-primary"><?= htmlspecialchars($t('not_found.go_modules', 'Перейти к модулям'), ENT_QUOTES, 'UTF-8') ?></a></p>
<?php else: ?>
      <p class="mb-0"><?= htmlspecialchars($t('not_found.message', 'Не найден маршрут:'), ENT_QUOTES, 'UTF-8') ?> <code><?= htmlspecialchars((string)$route, ENT_QUOTES, 'UTF-8') ?></code></p>
<?php endif; ?>
    </div>
  </section>
</div>
