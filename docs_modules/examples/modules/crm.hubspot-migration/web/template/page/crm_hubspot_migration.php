<?php
declare(strict_types=1);
/** @var array $data */
$titleRaw = (string)($data['title'] ?? 'Миграция из HubSpot');
$title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
?>
<body data-page="module-hubspot-migration" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-module-page"><?php crm_page_head([
    ['label' => $t('admin_module_detail.link_admin', 'Администрирование'), 'href' => 'index.php?route=admin'],
    ['label' => $t('admin_module_detail.link_modules', 'Модули'), 'href' => 'index.php?route=admin-modules'],
    ['label' => $titleRaw, 'active' => true],
], $titleRaw, 'Перенос контактов из JSON-экспорта HubSpot в выбранное рабочее пространство TropaTT.', '<a class="btn crm-btn-secondary" href="index.php?route=admin-modules" data-i18n="admin_module_detail.back_to_modules">' . htmlspecialchars($t('admin_module_detail.back_to_modules', 'Вернуться к модулям'), ENT_QUOTES, 'UTF-8') . '</a>'); ?>
<div class="hubm-callout" role="note"><strong>Важно: модуль не подключается к HubSpot.</strong> Он принимает вставленный JSON-экспорт контактов. Компании, сделки, связи между записями и HubSpot API сейчас не поддерживаются. Сначала создайте сессию и проверьте предпросмотр; только кнопка импорта создаёт контакты в CRM.</div>
<section class="hubm-grid" aria-label="Импорт контактов">
  <article class="crm-card hubm-panel"><div class="crm-card-body">
    <h2 class="h5">1. Создайте сессию</h2><p>Сессия изолирована текущим рабочим пространством и пользователем, который её создал.</p>
    <button class="btn crm-btn-primary" type="button" id="hubmCreateSession">Создать сессию импорта</button><div id="hubmSessionLabel" class="small mt-2" aria-live="polite">Сессия не выбрана</div>
  </div></article>
  <article class="crm-card hubm-panel"><div class="crm-card-body">
    <h2 class="h5">2. Вставьте JSON контактов</h2><p>Поддерживается массив объектов HubSpot с `id` и `properties`. До 100 записей и 512 КБ на пакет.</p>
    <label for="hubmJson" class="form-label">JSON-массив контактов</label><textarea class="form-control hubm-json" id="hubmJson" rows="10" spellcheck="false" placeholder='[{"id":"123","properties":{"firstname":"Ada","lastname":"Lovelace","email":"ada@example.com","phone":"+1 555 0100"}}]'></textarea>
    <details class="mt-3"><summary>Сопоставление полей</summary><div class="hubm-mapping"><label>Имя<input id="mapFirstname" class="form-control" value="firstname"></label><label>Фамилия<input id="mapLastname" class="form-control" value="lastname"></label><label>Email<input id="mapEmail" class="form-control" value="email"></label><label>Телефон<input id="mapPhone" class="form-control" value="phone"></label><label>Примечания<input id="mapNotes" class="form-control" value=""></label></div><small class="text-muted">Укажите имя свойства из HubSpot; для пустого поля оставьте значение пустым. Допустимые поля: firstname, lastname, email, phone, mobilephone, notes.</small></details>
    <button class="btn crm-btn-secondary mt-3" id="hubmPreview" type="button" disabled>Проверить и подготовить предпросмотр</button>
  </div></article>
  <article class="crm-card hubm-panel hubm-report"><div class="crm-card-body">
    <h2 class="h5">3. Предпросмотр и результат</h2><div id="hubmMessage" class="small" role="status" aria-live="polite">Данные не отправляются в CRM до запуска импорта.</div><div id="hubmCounts" class="hubm-counts" aria-live="polite"></div>
    <div class="hubm-actions"><button class="btn crm-btn-primary" id="hubmImport" type="button" disabled>Импортировать следующий пакет (до 25)</button><button class="btn crm-btn-secondary" id="hubmRetry" type="button" disabled>Повторить ошибки (до 25)</button><button class="btn crm-btn-danger-soft" id="hubmRollback" type="button" disabled>Откатить сессию</button><button class="btn crm-btn-secondary" id="hubmDiscard" type="button" disabled>Очистить неперенесённые данные</button></div>
    <div class="table-responsive mt-3"><table class="table table-sm align-middle"><thead><tr><th>HubSpot ID</th><th>Контакт в CRM</th><th>Состояние</th></tr></thead><tbody id="hubmRows"><tr><td colspan="3">Предпросмотр ещё не создан.</td></tr></tbody></table></div>
  </div></article>
</section></main></div></div>
