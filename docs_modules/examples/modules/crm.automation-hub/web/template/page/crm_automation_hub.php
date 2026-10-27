<?php
declare(strict_types=1);
/** @var array $data */
$titleRaw = (string)($data['title'] ?? 'Автоматизация и интеграции');
$title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
?>
<body data-page="module-automation-hub" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-module-page"><?php crm_page_head([
    ['label' => $t('admin_module_detail.link_admin', 'Администрирование'), 'href' => 'index.php?route=admin'],
    ['label' => $t('admin_module_detail.link_modules', 'Модули'), 'href' => 'index.php?route=admin-modules'],
    ['label' => $titleRaw, 'active' => true],
], $titleRaw, 'Выберите события CRM и доставляйте их в n8n или Make через подписанную очередь вебхуков.', '<a class="btn crm-btn-secondary" href="index.php?route=admin-modules" data-i18n="admin_module_detail.back_to_modules">' . htmlspecialchars($t('admin_module_detail.back_to_modules', 'Вернуться к модулям'), ENT_QUOTES, 'UTF-8') . '</a>'); ?>
<section class="crm-card mt-3" aria-labelledby="athubFormTitle">
  <div class="crm-card-header"><h2 class="h5 mb-0" id="athubFormTitle">Новое подключение</h2></div>
  <div class="crm-card-body">
    <p class="text-muted">Выбранные события поступают в очередь webhooks CRM, подписываются секретом и доставляются фоновым обработчиком с повторными попытками. Входящие сценарии и произвольные двусторонние действия модуль не выполняет.</p>
    <form id="athubConfigForm" class="row g-3" autocomplete="off">
      <div class="col-12 col-md-4"><label class="form-label" for="athubPlatform">Платформа</label><select class="form-select" id="athubPlatform" name="platform" required><option value="n8n">n8n</option><option value="make">Make</option></select></div>
      <div class="col-12 col-md-4"><label class="form-label" for="athubTitle">Название</label><input class="form-control" id="athubTitle" name="title" maxlength="255" required></div>
      <div class="col-12 col-md-4"><label class="form-label" for="athubRecipeKey">Ключ сценария</label><input class="form-control" id="athubRecipeKey" name="recipe_key" maxlength="64" required></div>
      <div class="col-12"><label class="form-label" for="athubEndpoint">HTTPS endpoint</label><input class="form-control" id="athubEndpoint" name="webhook_endpoint_url" type="url" inputmode="url" placeholder="https://…" maxlength="1024" required><div class="form-text">Разрешён только публичный HTTPS-адрес на порту 443 без логина, пароля, query-параметров и fragment.</div></div>
      <div class="col-12"><fieldset class="border rounded p-3"><legend class="float-none w-auto px-2 fs-6 mb-1">События для отправки</legend><div class="row g-2">
        <div class="col-12 col-sm-6 col-lg-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="task.created" checked><span class="form-check-label">Новая задача</span></label></div>
        <div class="col-12 col-sm-6 col-lg-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="task.updated"><span class="form-check-label">Изменение задачи</span></label></div>
        <div class="col-12 col-sm-6 col-lg-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="task.status_changed"><span class="form-check-label">Изменение статуса задачи</span></label></div>
        <div class="col-12 col-sm-6 col-lg-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="project.created"><span class="form-check-label">Новый проект</span></label></div>
        <div class="col-12 col-sm-6 col-lg-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="project.updated"><span class="form-check-label">Изменение проекта</span></label></div>
        <div class="col-12 col-sm-6 col-lg-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="comment.added"><span class="form-check-label">Новый комментарий</span></label></div>
        <div class="col-12 col-sm-6 col-lg-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="intake.created"><span class="form-check-label">Новое обращение</span></label></div>
      </div><div class="form-text mt-2">Тело события очищается от секретов и финансовых полей. Доставка работает через штатную очередь webhooks CRM.</div></fieldset></div>
      <div class="col-12 col-md-8"><label class="form-label" for="athubSecret">Секрет подписи</label><input class="form-control" id="athubSecret" name="secret_token" type="password" minlength="1" maxlength="4096" autocomplete="new-password" required><div class="form-text">Сохраняется шифрованно; подпись событий использует тот же секрет. После сохранения он повторно не отображается.</div></div>
      <div class="col-12 col-md-4 d-flex align-items-end"><button class="btn crm-btn-primary w-100" id="athubSaveBtn" type="submit">Сохранить подписку</button></div>
    </form>
    <div id="athubMessage" class="small mt-3" role="status" aria-live="polite"></div>
  </div>
</section>
<section class="crm-card mt-3" aria-labelledby="athubRecipesTitle">
  <div class="crm-card-header d-flex flex-wrap align-items-center justify-content-between gap-2"><h2 class="h5 mb-0" id="athubRecipesTitle">Подключения рабочего пространства</h2><button class="btn crm-btn-secondary" id="athubRefreshBtn" type="button">Обновить</button></div>
  <div class="crm-card-body"><div id="athubStatusMeta" class="text-muted mb-2" role="status" aria-live="polite">Список ещё не загружен.</div><div id="athubRecipesList" class="d-grid gap-2"><div class="text-muted">Загрузка подключений…</div></div></div>
</section>
</main></div></div>
