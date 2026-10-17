<?php
declare(strict_types=1);
/** @var array $data */
$titleRaw = (string)($data['title'] ?? 'Яндекс Диск');
$title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
?>
<body data-page="module-yandex-disk" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-module-page"><?php crm_page_head([
    ['label' => $t('admin_module_detail.link_admin', 'Администрирование'), 'href' => 'index.php?route=admin'],
    ['label' => $t('admin_module_detail.link_modules', 'Модули'), 'href' => 'index.php?route=admin-modules'],
    ['label' => $titleRaw, 'active' => true],
], $titleRaw, 'Хранение и обмен файлами проектов через Яндекс Диск.', '<a class="btn crm-btn-secondary" href="index.php?route=admin-modules" data-i18n="admin_module_detail.back_to_modules">' . htmlspecialchars($t('admin_module_detail.back_to_modules', 'Вернуться к модулям'), ENT_QUOTES, 'UTF-8') . '</a>'); ?>
<section class="crm-card mt-3" aria-labelledby="moduleSetupState">
    <div class="crm-card-body">
        <div class="alert alert-warning mb-3" role="status">
            <strong id="moduleSetupState">Установлен · не подключён</strong>
            <div>Учётная запись Яндекс Диска не подключена. Передача файлов не выполняется.</div>
        </div>
        <p class="mb-0">На этой странице пока нет доступных настроек и проверки соединения. Кнопки настройки или синхронизации не показываются, пока эти действия не реализованы.</p>
    </div>
</section></main></div></div>
