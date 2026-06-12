<?php declare(strict_types=1); ?>
<?php $title = $t('admin_api_clients.title', 'TropaTT — API-клиенты'); ?>
<body data-page="admin-api-clients" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-admin-api-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="nav.admin"><?= htmlspecialchars($t('nav.admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="admin_api_clients.page_title"><?= htmlspecialchars($t('admin_api_clients.page_title', 'API-клиенты'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" data-i18n="admin_api_clients.page_title"><?= htmlspecialchars($t('admin_api_clients.page_title', 'API-клиенты'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_api_clients.subtitle"><?= htmlspecialchars($t('admin_api_clients.subtitle', 'Интеграционные клиенты, ключи доступа, webhooks и журналы использования.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="crm-page-actions"><a class="btn crm-btn-secondary" href="index.php?route=admin" data-i18n="admin_api_clients.back_to_admin"><?= htmlspecialchars($t('admin_api_clients.back_to_admin', 'Админка'), ENT_QUOTES, 'UTF-8') ?></a></div></div>

<div class="row g-3 mb-3 crm-kpi-row">
  <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted" data-i18n="admin_api_clients.kpi_total_clients"><?= htmlspecialchars($t('admin_api_clients.kpi_total_clients', 'Всего клиентов'), ENT_QUOTES, 'UTF-8') ?></small><h2 id="apcTotalClients" class="h4 mb-0">0</h2></div></div>
  <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted" data-i18n="admin_api_clients.kpi_active_clients"><?= htmlspecialchars($t('admin_api_clients.kpi_active_clients', 'Активные клиенты'), ENT_QUOTES, 'UTF-8') ?></small><h2 id="apcActiveClients" class="h4 mb-0">0</h2></div></div>
  <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted" data-i18n="admin_api_clients.kpi_keys_per_client"><?= htmlspecialchars($t('admin_api_clients.kpi_keys_per_client', 'Ключей у клиента'), ENT_QUOTES, 'UTF-8') ?></small><h2 id="apcSelectedKeys" class="h4 mb-0">0</h2></div></div>
  <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted" data-i18n="admin_api_clients.kpi_active_keys"><?= htmlspecialchars($t('admin_api_clients.kpi_active_keys', 'Активных ключей'), ENT_QUOTES, 'UTF-8') ?></small><h2 id="apcSelectedActiveKeys" class="h4 mb-0">0</h2></div></div>
</div>

<div class="row g-3 align-items-start">
  <div class="col-xl-5">
    <div class="crm-card crm-toolbar-surface crm-filters-card mb-3">
      <div class="d-flex gap-2 flex-wrap">
        <input id="apcSearchInput" class="form-control crm-field-w-260" placeholder="<?= htmlspecialchars($t('admin_api_clients.search_placeholder', 'Поиск: название/ID'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_api_clients.search_placeholder">
        <select id="apcActiveFilter" class="form-select crm-field-w-220">
          <option value="" data-i18n="admin_api_clients.filter_all"><?= htmlspecialchars($t('admin_api_clients.filter_all', 'Все клиенты'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="1" data-i18n="admin_api_clients.filter_active"><?= htmlspecialchars($t('admin_api_clients.filter_active', 'Только активные'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="0" data-i18n="admin_api_clients.filter_inactive"><?= htmlspecialchars($t('admin_api_clients.filter_inactive', 'Только неактивные'), ENT_QUOTES, 'UTF-8') ?></option>
        </select>
        <button id="apcFiltersResetBtn" class="btn crm-btn-muted" type="button" data-i18n="page.reset"><?= htmlspecialchars($t('page.reset', 'Сбросить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>

    <div class="crm-card crm-section-card p-0 table-responsive crm-admin-api-table-card">
      <table class="table crm-table mb-0">
        <thead><tr><th data-i18n="admin_api_clients.th_client"><?= htmlspecialchars($t('admin_api_clients.th_client', 'Клиент'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_api_clients.th_status"><?= htmlspecialchars($t('admin_api_clients.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:110px" data-i18n="admin_api_clients.th_actions"><?= htmlspecialchars($t('admin_api_clients.th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
        <tbody id="apcClientsTableBody">
          <tr><td colspan="3" class="text-muted" data-i18n="admin_api_clients.loading"><?= htmlspecialchars($t('admin_api_clients.loading', 'Загрузка API-клиентов...'), ENT_QUOTES, 'UTF-8') ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-xl-7">
    <div class="crm-card crm-section-card mb-3">
      <h2 class="h6 mb-3" data-i18n="admin_api_clients.section_create_title"><?= htmlspecialchars($t('admin_api_clients.section_create_title', 'Создать API-клиент'), ENT_QUOTES, 'UTF-8') ?></h2>
      <form id="apcCreateForm" class="row g-2">
        <div class="col-12"><label class="form-label" data-i18n="admin_api_clients.field_title"><?= htmlspecialchars($t('admin_api_clients.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" maxlength="255" required></div>
        <div class="col-12"><label class="form-label" data-i18n="admin_api_clients.field_scopes"><?= htmlspecialchars($t('admin_api_clients.field_scopes', 'Права API (через запятую)'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="scopes" placeholder="<?= htmlspecialchars($t('admin_api_clients.placeholder_scopes', 'tasks.read,projects.read'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_api_clients.placeholder_scopes"></div>
        <div class="col-6"><label class="form-label" data-i18n="admin_api_clients.field_is_active"><?= htmlspecialchars($t('admin_api_clients.field_is_active', 'Активен'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="is_active"><option value="1" data-i18n="admin_api_clients.opt_yes"><?= htmlspecialchars($t('admin_api_clients.opt_yes', 'Да'), ENT_QUOTES, 'UTF-8') ?></option><option value="0" data-i18n="admin_api_clients.opt_no"><?= htmlspecialchars($t('admin_api_clients.opt_no', 'Нет'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
        <div class="col-12"><button class="btn crm-btn-primary" type="submit" data-i18n="admin_api_clients.create_client_btn"><?= htmlspecialchars($t('admin_api_clients.create_client_btn', 'Создать API-клиент'), ENT_QUOTES, 'UTF-8') ?></button></div>
      </form>
    </div>

    <div class="crm-card crm-section-card">
      <h2 class="h6 mb-3" data-i18n="admin_api_clients.section_edit_title"><?= htmlspecialchars($t('admin_api_clients.section_edit_title', 'Редактировать API-клиент'), ENT_QUOTES, 'UTF-8') ?></h2>
      <form id="apcEditForm" class="row g-2">
        <div class="col-12"><input type="hidden" name="public_id"><label class="form-label" data-i18n="admin_api_clients.field_title"><?= htmlspecialchars($t('admin_api_clients.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" maxlength="255" required></div>
        <div class="col-12"><label class="form-label" data-i18n="admin_api_clients.field_scopes"><?= htmlspecialchars($t('admin_api_clients.field_scopes', 'Права API (через запятую)'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="scopes"></div>
        <div class="col-6"><label class="form-label" data-i18n="admin_api_clients.field_is_active"><?= htmlspecialchars($t('admin_api_clients.field_is_active', 'Активен'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="is_active"><option value="1" data-i18n="admin_api_clients.opt_yes"><?= htmlspecialchars($t('admin_api_clients.opt_yes', 'Да'), ENT_QUOTES, 'UTF-8') ?></option><option value="0" data-i18n="admin_api_clients.opt_no"><?= htmlspecialchars($t('admin_api_clients.opt_no', 'Нет'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
        <div class="col-12 d-flex gap-2"><button class="btn crm-btn-primary" type="submit" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button><button id="apcDeleteClientBtn" class="btn crm-btn-danger-soft" type="button" data-i18n="admin_api_clients.delete_client_btn"><?= htmlspecialchars($t('admin_api_clients.delete_client_btn', 'Удалить API-клиент'), ENT_QUOTES, 'UTF-8') ?></button></div>
      </form>
      <div id="apcSelectedClientHint" class="small text-muted mt-2" data-i18n="admin_api_clients.select_client_hint"><?= htmlspecialchars($t('admin_api_clients.select_client_hint', 'Выберите клиента в таблице слева.'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>
</div>

<div class="crm-card crm-section-card mt-3 crm-admin-api-keys-card">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 id="apcKeysTitle" class="h6 mb-0" data-i18n="admin_api_clients.section_keys_title"><?= htmlspecialchars($t('admin_api_clients.section_keys_title', 'Ключи API-клиента'), ENT_QUOTES, 'UTF-8') ?></h2>
  </div>
  <div class="row g-3 mb-3">
    <div class="col-lg-8">
      <form id="apcIssueKeyForm" class="row g-2">
        <div class="col-md-6"><label class="form-label" data-i18n="admin_api_clients.field_key_scopes"><?= htmlspecialchars($t('admin_api_clients.field_key_scopes', 'Права ключа (опционально)'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="scopes" placeholder="<?= htmlspecialchars($t('admin_api_clients.placeholder_key_scopes', 'пусто = права клиента'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_api_clients.placeholder_key_scopes"></div>
        <div class="col-md-4"><label class="form-label" data-i18n="admin_api_clients.field_expires_at"><?= htmlspecialchars($t('admin_api_clients.field_expires_at', 'Истекает (YYYY-MM-DD HH:MM:SS)'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="expires_at" placeholder="<?= htmlspecialchars($t('admin_api_clients.placeholder_expires_at', '2026-12-31 23:59:59'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_api_clients.placeholder_expires_at"></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn crm-btn-secondary w-100" type="submit" data-i18n="admin_api_clients.issue_btn"><?= htmlspecialchars($t('admin_api_clients.issue_btn', 'Выпустить'), ENT_QUOTES, 'UTF-8') ?></button></div>
      </form>
      <div id="apcPlainKeyWrap" class="alert alert-warning py-2 mt-2 d-none"></div>
    </div>
    <div class="col-lg-4">
      <div class="small text-muted" data-i18n="admin_api_clients.keys_help"><?= htmlspecialchars($t('admin_api_clients.keys_help', 'Выберите ключ в таблице, чтобы получить журналы использования, ротировать или отозвать ключ.'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="d-flex gap-2 mt-2">
        <button id="apcRotateKeyBtn" class="btn btn-sm crm-btn-secondary" type="button" data-i18n="admin_api_clients.rotate_btn"><?= htmlspecialchars($t('admin_api_clients.rotate_btn', 'Ротировать ключ'), ENT_QUOTES, 'UTF-8') ?></button>
        <button id="apcRevokeKeyBtn" class="btn btn-sm crm-btn-danger-soft" type="button" data-i18n="admin_api_clients.revoke_btn"><?= htmlspecialchars($t('admin_api_clients.revoke_btn', 'Отозвать ключ'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>

  <div class="table-responsive mb-3">
    <table class="table crm-table mb-0">
      <thead><tr><th data-i18n="admin_api_clients.th_key_id"><?= htmlspecialchars($t('admin_api_clients.th_key_id', 'ID ключа'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_api_clients.th_key_scopes"><?= htmlspecialchars($t('admin_api_clients.th_key_scopes', 'Права API'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_api_clients.th_expires"><?= htmlspecialchars($t('admin_api_clients.th_expires', 'Истекает'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_api_clients.th_revoked"><?= htmlspecialchars($t('admin_api_clients.th_revoked', 'Отозван'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_api_clients.th_created"><?= htmlspecialchars($t('admin_api_clients.th_created', 'Создан'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:140px" data-i18n="admin_api_clients.th_usage"><?= htmlspecialchars($t('admin_api_clients.th_usage', 'Использование'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
      <tbody id="apcKeysTableBody"><tr><td colspan="6" class="text-muted" data-i18n="admin_api_clients.select_client_for_keys"><?= htmlspecialchars($t('admin_api_clients.select_client_for_keys', 'Сначала выберите API-клиент.'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
    </table>
  </div>

  <div class="row g-3">
    <div class="col-lg-6"><div class="crm-card crm-soft-panel"><h3 class="h6" data-i18n="admin_api_clients.section_audit_log"><?= htmlspecialchars($t('admin_api_clients.section_audit_log', 'Журнал аудита'), ENT_QUOTES, 'UTF-8') ?></h3><div id="apcUsageAudit" class="small text-muted" data-i18n="admin_api_clients.select_key_for_usage"><?= htmlspecialchars($t('admin_api_clients.select_key_for_usage', 'Выберите ключ и нажмите «Использование».'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
    <div class="col-lg-6"><div class="crm-card crm-soft-panel"><h3 class="h6" data-i18n="admin_api_clients.section_security_log"><?= htmlspecialchars($t('admin_api_clients.section_security_log', 'Журнал безопасности'), ENT_QUOTES, 'UTF-8') ?></h3><div id="apcUsageSecurity" class="small text-muted" data-i18n="admin_api_clients.select_key_for_usage"><?= htmlspecialchars($t('admin_api_clients.select_key_for_usage', 'Выберите ключ и нажмите «Использование».'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
  </div>
</div>

<div class="crm-card crm-section-card mt-3 crm-admin-api-webhooks-card">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h6 mb-0" data-i18n="admin_api_clients.section_webhooks_title"><?= htmlspecialchars($t('admin_api_clients.section_webhooks_title', 'Вебхуки'), ENT_QUOTES, 'UTF-8') ?></h2>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-12">
      <div class="d-flex gap-2 flex-wrap mb-2">
        <input id="whSearchInput" class="form-control crm-field-w-260" placeholder="<?= htmlspecialchars($t('admin_api_clients.wh_search_placeholder', 'Поиск вебхука: название/endpoint'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_api_clients.wh_search_placeholder">
        <select id="whActiveFilter" class="form-select crm-field-w-220">
          <option value="" data-i18n="admin_api_clients.wh_filter_all"><?= htmlspecialchars($t('admin_api_clients.wh_filter_all', 'Все вебхуки'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="1" data-i18n="admin_api_clients.wh_filter_active"><?= htmlspecialchars($t('admin_api_clients.wh_filter_active', 'Только активные'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="0" data-i18n="admin_api_clients.wh_filter_inactive"><?= htmlspecialchars($t('admin_api_clients.wh_filter_inactive', 'Только неактивные'), ENT_QUOTES, 'UTF-8') ?></option>
        </select>
        <button id="whResetBtn" class="btn crm-btn-muted" type="button" data-i18n="page.reset"><?= htmlspecialchars($t('page.reset', 'Сбросить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div class="table-responsive">
        <table class="table crm-table mb-0">
          <thead><tr><th data-i18n="admin_api_clients.wh_th_webhook"><?= htmlspecialchars($t('admin_api_clients.wh_th_webhook', 'Webhook'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_api_clients.wh_th_status"><?= htmlspecialchars($t('admin_api_clients.wh_th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:110px" data-i18n="admin_api_clients.wh_th_actions"><?= htmlspecialchars($t('admin_api_clients.wh_th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
          <tbody id="whTableBody"><tr><td colspan="3" class="text-muted" data-i18n="admin_api_clients.wh_loading"><?= htmlspecialchars($t('admin_api_clients.wh_loading', 'Загрузка webhooks...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
        </table>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="crm-form-panel h-100">
        <h3 class="h6 mb-2" data-i18n="admin_api_clients.wh_create_title"><?= htmlspecialchars($t('admin_api_clients.wh_create_title', 'Создать webhook'), ENT_QUOTES, 'UTF-8') ?></h3>
        <form id="whCreateForm" class="row g-2">
          <div class="col-12"><label class="form-label" data-i18n="admin_api_clients.wh_field_title"><?= htmlspecialchars($t('admin_api_clients.wh_field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" maxlength="255" required></div>
          <div class="col-12"><label class="form-label" data-i18n="admin_api_clients.wh_field_endpoint"><?= htmlspecialchars($t('admin_api_clients.wh_field_endpoint', 'URL обработчика'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="endpoint" maxlength="2048" placeholder="<?= htmlspecialchars($t('admin_api_clients.wh_placeholder_endpoint', 'https://example.com/webhook'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_api_clients.wh_placeholder_endpoint" required></div>
          <div class="col-12"><label class="form-label" data-i18n="admin_api_clients.wh_field_secret"><?= htmlspecialchars($t('admin_api_clients.wh_field_secret', 'Секрет (опционально)'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="secret" maxlength="255"></div>
          <div class="col-12"><label class="form-label" data-i18n="admin_api_clients.wh_field_events"><?= htmlspecialchars($t('admin_api_clients.wh_field_events', 'События (через запятую)'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="events" placeholder="<?= htmlspecialchars($t('admin_api_clients.wh_placeholder_events', 'task.created,project.updated'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_api_clients.wh_placeholder_events"></div>
          <div class="col-6"><label class="form-label" data-i18n="admin_api_clients.wh_field_is_active"><?= htmlspecialchars($t('admin_api_clients.wh_field_is_active', 'Активен'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="is_active"><option value="1" data-i18n="admin_api_clients.opt_yes"><?= htmlspecialchars($t('admin_api_clients.opt_yes', 'Да'), ENT_QUOTES, 'UTF-8') ?></option><option value="0" data-i18n="admin_api_clients.opt_no"><?= htmlspecialchars($t('admin_api_clients.opt_no', 'Нет'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
          <div class="col-12"><button class="btn crm-btn-primary" type="submit" data-i18n="admin_api_clients.wh_create_btn"><?= htmlspecialchars($t('admin_api_clients.wh_create_btn', 'Создать webhook'), ENT_QUOTES, 'UTF-8') ?></button></div>
        </form>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="crm-form-panel h-100">
        <h3 class="h6 mb-2" data-i18n="admin_api_clients.wh_edit_title"><?= htmlspecialchars($t('admin_api_clients.wh_edit_title', 'Редактировать выбранный webhook'), ENT_QUOTES, 'UTF-8') ?></h3>
        <form id="whEditForm" class="row g-2">
          <input type="hidden" name="public_id">
          <div class="col-12"><label class="form-label" data-i18n="admin_api_clients.wh_field_title"><?= htmlspecialchars($t('admin_api_clients.wh_field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" maxlength="255"></div>
          <div class="col-12"><label class="form-label" data-i18n="admin_api_clients.wh_field_endpoint"><?= htmlspecialchars($t('admin_api_clients.wh_field_endpoint', 'URL обработчика'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="endpoint" maxlength="2048"></div>
          <div class="col-12"><label class="form-label" data-i18n="admin_api_clients.wh_field_secret_edit"><?= htmlspecialchars($t('admin_api_clients.wh_field_secret_edit', 'Секрет (если заполнить — будет обновлён)'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="secret" maxlength="255"></div>
          <div class="col-12"><label class="form-label" data-i18n="admin_api_clients.wh_field_events"><?= htmlspecialchars($t('admin_api_clients.wh_field_events', 'События (через запятую)'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="events"></div>
          <div class="col-6"><label class="form-label" data-i18n="admin_api_clients.wh_field_is_active"><?= htmlspecialchars($t('admin_api_clients.wh_field_is_active', 'Активен'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="is_active"><option value="1" data-i18n="admin_api_clients.opt_yes"><?= htmlspecialchars($t('admin_api_clients.opt_yes', 'Да'), ENT_QUOTES, 'UTF-8') ?></option><option value="0" data-i18n="admin_api_clients.opt_no"><?= htmlspecialchars($t('admin_api_clients.opt_no', 'Нет'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
          <div class="col-12 d-flex gap-2 flex-wrap">
            <button class="btn crm-btn-primary" type="submit" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
            <button id="whTestBtn" class="btn crm-btn-secondary" type="button" data-i18n="admin_api_clients.wh_test_btn"><?= htmlspecialchars($t('admin_api_clients.wh_test_btn', 'Отправить тест'), ENT_QUOTES, 'UTF-8') ?></button>
            <button id="whDeleteBtn" class="btn crm-btn-danger-soft" type="button" data-i18n="admin_api_clients.wh_delete_btn"><?= htmlspecialchars($t('admin_api_clients.wh_delete_btn', 'Удалить webhook'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
        </form>
        <div id="whSelectedHint" class="small text-muted mt-2" data-i18n="admin_api_clients.wh_select_hint"><?= htmlspecialchars($t('admin_api_clients.wh_select_hint', 'Выберите webhook в таблице слева.'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>
  </div>

  <div class="crm-soft-panel">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h3 class="h6 mb-0" data-i18n="admin_api_clients.wh_deliveries_title"><?= htmlspecialchars($t('admin_api_clients.wh_deliveries_title', 'Журнал доставок выбранного webhook'), ENT_QUOTES, 'UTF-8') ?></h3>
      <button id="whDeliveriesRefreshBtn" class="btn btn-sm crm-btn-secondary" type="button" data-i18n="admin_api_clients.wh_deliveries_refresh"><?= htmlspecialchars($t('admin_api_clients.wh_deliveries_refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div class="table-responsive">
      <table class="table crm-table mb-0">
        <thead><tr><th data-i18n="admin_api_clients.wh_th_time"><?= htmlspecialchars($t('admin_api_clients.wh_th_time', 'Время'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_api_clients.wh_th_event"><?= htmlspecialchars($t('admin_api_clients.wh_th_event', 'Событие'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_api_clients.wh_th_status"><?= htmlspecialchars($t('admin_api_clients.wh_th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_api_clients.wh_th_http"><?= htmlspecialchars($t('admin_api_clients.wh_th_http', 'HTTP'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_api_clients.wh_th_endpoint"><?= htmlspecialchars($t('admin_api_clients.wh_th_endpoint', 'URL обработчика'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
        <tbody id="whDeliveriesBody"><tr><td colspan="5" class="text-muted" data-i18n="admin_api_clients.wh_select_for_deliveries"><?= htmlspecialchars($t('admin_api_clients.wh_select_for_deliveries', 'Выберите webhook, чтобы увидеть доставки.'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
      </table>
    </div>
  </div>
</div>

</main></div></div>
