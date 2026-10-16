<?php
declare(strict_types=1);
/** @var array $data */
$titleRaw = (string)($data['title'] ?? 'Публичные формы');
$title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
?>
<body data-page="module-public-forms" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-module-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin-modules">Модули</a></li><li class="breadcrumb-item active"><?= $title ?></li></ol><h1 class="crm-page-title"><?= $title ?></h1><p class="crm-subtitle">Формы для сбора обращений и заявок клиентов.</p>
</div><div class="crm-page-actions"><a class="btn crm-btn-secondary" href="index.php?route=admin-modules">Вернуться к модулям</a></div></div>
<section class="crm-card mt-3" aria-labelledby="moduleSetupState">
    <div class="crm-card-body">
        <div class="alert alert-warning mb-3" role="status">
            <strong id="moduleSetupState">Установлен · не подключён</strong>
            <div>Публичные формы ещё не созданы и не опубликованы.</div>
        </div>
        <p class="mb-0">На этой странице пока нет доступных настроек и проверки соединения. Кнопки настройки или синхронизации не показываются, пока эти действия не реализованы.</p>
    </div>
</section></main></div></div>
