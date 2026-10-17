<?php
declare(strict_types=1);
/** @var array $data */
$titleRaw = (string)($data['title'] ?? 'Стартовые наборы');
$title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
?>
<body data-page="module-starter-kits" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-module-page"><?php crm_page_head([
    ['label' => $t('admin_module_detail.link_admin', 'Администрирование'), 'href' => 'index.php?route=admin'],
    ['label' => $t('admin_module_detail.link_modules', 'Модули'), 'href' => 'index.php?route=admin-modules'],
    ['label' => $titleRaw, 'active' => true],
], $titleRaw, 'Выберите стартовый набор, проверьте состав и конфликты, затем примените только нужные компоненты к текущему рабочему пространству.', '<a class="btn crm-btn-secondary" href="index.php?route=admin-modules" data-i18n="admin_module_detail.back_to_modules">' . htmlspecialchars($t('admin_module_detail.back_to_modules', 'Вернуться к модулям'), ENT_QUOTES, 'UTF-8') . '</a>'); ?>
<div id="skitsMessage" class="alert d-none mt-3" role="status" aria-live="polite"></div>
<section class="crm-card mt-3" aria-labelledby="skitsHelpTitle"><div class="crm-card-body"><h2 class="h5" id="skitsHelpTitle">Как это работает</h2><p class="mb-0">Предпросмотр ничего не меняет. Применение создаёт только явно выбранные компоненты; существующие объекты с совпадающими кодами или названиями пропускаются. Роли общие для CRM, поэтому создать их может только главный администратор. Роли из наборов не назначаются новым пользователям автоматически.</p></div></section>
<section class="mt-3" aria-labelledby="skitsCatalogTitle"><div class="d-flex align-items-center justify-content-between gap-2 mb-2"><h2 class="h4 mb-0" id="skitsCatalogTitle">Доступные наборы</h2><button type="button" class="btn crm-btn-secondary" id="skitsRefreshBtn">Обновить список</button></div><div class="crm-starter-kits-grid" id="kitsGrid" aria-live="polite"><div class="crm-card"><div class="crm-card-body">Загрузка наборов…</div></div></div></section>
<section class="crm-card mt-3 d-none" id="skitsPreviewPanel" aria-labelledby="skitsPreviewTitle"><div class="crm-card-body"><div class="d-flex align-items-start justify-content-between gap-2"><div><h2 class="h4" id="skitsPreviewTitle">Предпросмотр</h2><p id="skitsPreviewDescription" class="text-muted mb-0"></p></div><button type="button" class="btn crm-btn-secondary" id="skitsClosePreview">Закрыть</button></div><div id="skitsPreviewContent" class="mt-3"></div><fieldset class="mt-3"><legend class="h6">Что добавить</legend><div id="skitsComponentChoices" class="d-flex flex-wrap gap-3"></div></fieldset><div class="d-flex justify-content-end mt-3"><button type="button" class="btn crm-btn-primary" id="skitsApplyBtn" disabled>Применить выбранное</button></div></div></section>
</main></div></div>
