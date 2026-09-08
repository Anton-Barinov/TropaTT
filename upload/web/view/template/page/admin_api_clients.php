<?php declare(strict_types=1); ?>
<?php $title = $t('admin_api_clients.title', 'TropaTT — API-клиенты'); ?>
<body data-page="admin-api-clients" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-admin-api-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="nav.admin"><?= htmlspecialchars($t('nav.admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="admin_api_clients.page_title"><?= htmlspecialchars($t('admin_api_clients.page_title', 'API-клиенты'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" data-i18n="admin_api_clients.page_title"><?= htmlspecialchars($t('admin_api_clients.page_title', 'API-клиенты'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_api_clients.subtitle"><?= htmlspecialchars($t('admin_api_clients.subtitle', 'Интеграционные приложения и их ключи доступа.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="crm-page-actions"><button class="btn crm-btn-primary" id="apcNewClientBtn" type="button" data-i18n="admin_api_clients.new_client_btn"><?= htmlspecialchars($t('admin_api_clients.new_client_btn', 'Новый клиент'), ENT_QUOTES, 'UTF-8') ?></button><a class="btn crm-btn-secondary" href="index.php?route=admin-webhooks" data-i18n="admin_api_clients.webhooks_link"><?= htmlspecialchars($t('admin_api_clients.webhooks_link', 'Вебхуки'), ENT_QUOTES, 'UTF-8') ?></a><a class="btn crm-btn-secondary" href="index.php?route=admin" data-i18n="admin_api_clients.back_to_admin"><?= htmlspecialchars($t('admin_api_clients.back_to_admin', 'Админка'), ENT_QUOTES, 'UTF-8') ?></a></div></div>

<div class="row g-3 align-items-start">
  <div class="col-xl-4">
    <div class="crm-card crm-toolbar-surface crm-filters-card mb-3">
      <div class="d-flex gap-2 flex-wrap">
        <input id="apcSearchInput" class="form-control crm-field-w-220" placeholder="<?= htmlspecialchars($t('admin_api_clients.search_placeholder', 'Поиск клиента'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_api_clients.search_placeholder">
        <select id="apcActiveFilter" class="form-select crm-field-w-160">
          <option value="" data-i18n="admin_api_clients.filter_all"><?= htmlspecialchars($t('admin_api_clients.filter_all', 'Все'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="1" data-i18n="admin_api_clients.filter_active"><?= htmlspecialchars($t('admin_api_clients.filter_active', 'Активные'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="0" data-i18n="admin_api_clients.filter_inactive"><?= htmlspecialchars($t('admin_api_clients.filter_inactive', 'Неактивные'), ENT_QUOTES, 'UTF-8') ?></option>
        </select>
      </div>
    </div>

    <div class="crm-card crm-section-card p-0 crm-admin-api-clients-list">
      <div id="apcClientsList" class="crm-admin-api-clients-list-inner">
        <div class="p-4 text-muted" data-i18n="admin_api_clients.loading"><?= htmlspecialchars($t('admin_api_clients.loading', 'Загрузка API-клиентов...'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>
  </div>

  <div class="col-xl-8">
    <div id="apcClientDetailWrap">
      <div class="crm-card crm-section-card p-4 text-muted" data-i18n="admin_api_clients.select_client_hint"><?= htmlspecialchars($t('admin_api_clients.select_client_hint', 'Выберите клиента слева, чтобы управлять его ключами.'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>
</div>

<div class="modal fade" id="apcClientModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="apcClientModalTitle"><?= htmlspecialchars($t('admin_api_clients.modal_client_title', 'Клиент'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div><form id="apcClientForm"><div class="modal-body">
  <input type="hidden" name="public_id">
  <div class="mb-3"><label class="form-label" data-i18n="admin_api_clients.field_title"><?= htmlspecialchars($t('admin_api_clients.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" maxlength="255" required></div>
  <div class="mb-3"><label class="form-label" data-i18n="admin_api_clients.field_is_active"><?= htmlspecialchars($t('admin_api_clients.field_is_active', 'Активен'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="is_active"><option value="1" data-i18n="admin_api_clients.opt_yes"><?= htmlspecialchars($t('admin_api_clients.opt_yes', 'Да'), ENT_QUOTES, 'UTF-8') ?></option><option value="0" data-i18n="admin_api_clients.opt_no"><?= htmlspecialchars($t('admin_api_clients.opt_no', 'Нет'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
  <div class="mb-1 d-flex align-items-center gap-2">
    <input type="checkbox" class="form-check-input m-0" id="apcClientFullAccess" checked>
    <label class="form-check-label" for="apcClientFullAccess" data-i18n="admin_api_clients.full_access_like_me"><?= htmlspecialchars($t('admin_api_clients.full_access_like_me', 'Полный доступ — как у меня'), ENT_QUOTES, 'UTF-8') ?></label>
  </div>
  <div id="apcClientScopesHint" class="small text-muted mb-2" data-i18n="admin_api_clients.scopes_hint"><?= htmlspecialchars($t('admin_api_clients.scopes_hint', 'Снимите галочку, чтобы выбрать разделы вручную. Снять права ниже своих нельзя.'), ENT_QUOTES, 'UTF-8') ?></div>
  <div id="apcClientScopesBlock" class="border rounded p-2 crm-max-h-320 crm-overflow-auto d-none"></div>
</div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" type="submit" data-i18n="admin_api_clients.save_btn"><?= htmlspecialchars($t('admin_api_clients.save_btn', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button></div></form></div></div></div>

<div class="modal fade" id="apcKeyModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" data-i18n="admin_api_clients.modal_key_title"><?= htmlspecialchars($t('admin_api_clients.modal_key_title', 'Новый ключ'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div><form id="apcKeyForm"><div class="modal-body">
  <div class="mb-3"><label class="form-label" data-i18n="admin_api_clients.field_key_name"><?= htmlspecialchars($t('admin_api_clients.field_key_name', 'Название ключа'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="name" maxlength="255" placeholder="<?= htmlspecialchars($t('admin_api_clients.placeholder_key_name', 'например: CI, скрипт синхронизации'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_api_clients.placeholder_key_name"></div>
  <div class="mb-3"><label class="form-label" data-i18n="admin_api_clients.field_ttl"><?= htmlspecialchars($t('admin_api_clients.field_ttl', 'Срок жизни'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="ttl"><option value="" data-i18n="admin_api_clients.ttl_never"><?= htmlspecialchars($t('admin_api_clients.ttl_never', 'Без срока'), ENT_QUOTES, 'UTF-8') ?></option><option value="30" data-i18n="admin_api_clients.ttl_30"><?= htmlspecialchars($t('admin_api_clients.ttl_30', '30 дней'), ENT_QUOTES, 'UTF-8') ?></option><option value="90" data-i18n="admin_api_clients.ttl_90"><?= htmlspecialchars($t('admin_api_clients.ttl_90', '90 дней'), ENT_QUOTES, 'UTF-8') ?></option><option value="365" data-i18n="admin_api_clients.ttl_365"><?= htmlspecialchars($t('admin_api_clients.ttl_365', '1 год'), ENT_QUOTES, 'UTF-8') ?></option><option value="custom" data-i18n="admin_api_clients.ttl_custom"><?= htmlspecialchars($t('admin_api_clients.ttl_custom', 'Свой срок'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
  <div class="mb-3 d-none" id="apcKeyExpiryWrap"><label class="form-label" data-i18n="admin_api_clients.field_expires_at"><?= htmlspecialchars($t('admin_api_clients.field_expires_at', 'Действует до'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="datetime-local" name="expires_at_local"></div>
  <div class="mb-1 d-flex align-items-center gap-2">
    <input type="checkbox" class="form-check-input m-0" id="apcKeyFullAccess" checked>
    <label class="form-check-label" for="apcKeyFullAccess" data-i18n="admin_api_clients.full_access_client"><?= htmlspecialchars($t('admin_api_clients.full_access_client', 'Права как у клиента (по умолчанию)'), ENT_QUOTES, 'UTF-8') ?></label>
  </div>
  <div class="small text-muted mb-2" data-i18n="admin_api_clients.scopes_hint"><?= htmlspecialchars($t('admin_api_clients.scopes_hint', 'Снимите галочку, чтобы выбрать разделы вручную. Снять права ниже своих нельзя.'), ENT_QUOTES, 'UTF-8') ?></div>
  <div id="apcKeyScopesBlock" class="border rounded p-2 crm-max-h-320 crm-overflow-auto d-none"></div>
</div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" type="submit" data-i18n="admin_api_clients.issue_btn"><?= htmlspecialchars($t('admin_api_clients.issue_btn', 'Выпустить ключ'), ENT_QUOTES, 'UTF-8') ?></button></div></form></div></div></div>

<div class="modal fade" id="apcRevealModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" data-i18n="admin_api_clients.reveal_title"><?= htmlspecialchars($t('admin_api_clients.reveal_title', 'Ключ создан'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div><div class="modal-body">
  <div class="alert alert-warning py-2" data-i18n="admin_api_clients.reveal_once_warning"><?= htmlspecialchars($t('admin_api_clients.reveal_once_warning', 'Ключ показывается только один раз. Скопируйте и сохраните его сейчас.'), ENT_QUOTES, 'UTF-8') ?></div>
  <div class="input-group"><input class="form-control font-monospace" id="apcRevealKeyInput" type="text" readonly><button class="btn crm-btn-secondary" id="apcRevealCopyBtn" type="button" data-i18n="page.copy"><?= htmlspecialchars($t('page.copy', 'Копировать'), ENT_QUOTES, 'UTF-8') ?></button></div>
  <div class="small text-muted mt-2" id="apcRevealKeyLabel"></div>
</div><div class="modal-footer"><button class="btn crm-btn-primary" type="button" data-bs-dismiss="modal" data-i18n="page.done"><?= htmlspecialchars($t('page.done', 'Готово'), ENT_QUOTES, 'UTF-8') ?></button></div></div></div></div>

</main></div></div>
