<?php declare(strict_types=1); ?>
<?php $title = '404 Not Found'; ?>
<body data-page="not-found">
<div class="container py-5">
  <section class="card border-danger">
    <div class="card-body">
      <h1 class="h3 text-danger">404 — Страница не найдена</h1>
      <p class="mb-0">Не найден маршрут: <code><?= htmlspecialchars((string)$route, ENT_QUOTES, 'UTF-8') ?></code></p>
    </div>
  </section>
</div>
