<?php
declare(strict_types=1);
/** @var array $data */
$titleRaw = (string)($data['title'] ?? 'VK WorkSpace');
$title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
?>
<body data-page="module-vk-workspace" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-module-page"><?php crm_page_head([
    ['label' => $t('admin_module_detail.link_admin', 'Администрирование'), 'href' => 'index.php?route=admin'],
    ['label' => $t('admin_module_detail.link_modules', 'Модули'), 'href' => 'index.php?route=admin-modules'],
    ['label' => $titleRaw, 'active' => true],
], $titleRaw, 'Интеграция недоступна: официальный контракт SaaS API для этих операций не подтверждён.', '<a class="btn crm-btn-secondary" href="index.php?route=admin-modules" data-i18n="admin_module_detail.back_to_modules">' . htmlspecialchars($t('admin_module_detail.back_to_modules', 'Вернуться к модулям'), ENT_QUOTES, 'UTF-8') . '</a>'); ?>
<section class="crm-card mt-3" aria-labelledby="moduleSetupState">
    <div class="crm-card-body">
        <div class="alert alert-warning mb-3" role="status">
            <strong id="moduleSetupState">API провайдера недоступен · функции отключены</strong>
            <div>Пакет не подключается к VK WorkSpace. Создание встреч, ссылки на звонки и синхронизация календаря недоступны.</div>
        </div>
        <p class="mb-0">Официальный публичный контракт SaaS API для создания видеовстреч и управления календарными событиями не подтверждён. Настроек подключения нет; внешние запросы и фиктивные встречи не создаются. См. <a href="https://biz.mail.ru/docs/saas/ru/tariffs/info" target="_blank" rel="noopener noreferrer">официальную документацию VK WorkSpace</a>.</p>
    </div>
</section></main></div></div>
