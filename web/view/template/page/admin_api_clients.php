<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — API-клиенты'; ?>
<body data-page="admin-api-clients" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-admin-api-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin">Админка</a></li><li class="breadcrumb-item active">API-клиенты</li></ol><h1 class="crm-page-title">API-клиенты</h1><p class="crm-subtitle">Интеграционные клиенты, ключи доступа, webhooks и журналы использования.</p></div><div class="crm-page-actions"><a class="btn crm-btn-secondary" href="index.php?route=admin">Админка</a></div></div>

<div class="row g-3 mb-3 crm-kpi-row">
  <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted">Всего клиентов</small><h2 id="apcTotalClients" class="h4 mb-0">0</h2></div></div>
  <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted">Активные клиенты</small><h2 id="apcActiveClients" class="h4 mb-0">0</h2></div></div>
  <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted">Ключей у клиента</small><h2 id="apcSelectedKeys" class="h4 mb-0">0</h2></div></div>
  <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted">Активных ключей</small><h2 id="apcSelectedActiveKeys" class="h4 mb-0">0</h2></div></div>
</div>

<div class="row g-3 align-items-start">
  <div class="col-xl-5">
    <div class="crm-card crm-toolbar-surface crm-filters-card mb-3">
      <div class="d-flex gap-2 flex-wrap">
        <input id="apcSearchInput" class="form-control crm-field-w-260" placeholder="Поиск: название/ID">
        <select id="apcActiveFilter" class="form-select crm-field-w-220">
          <option value="">Все клиенты</option>
          <option value="1">Только активные</option>
          <option value="0">Только неактивные</option>
        </select>
        <button id="apcFiltersResetBtn" class="btn crm-btn-muted" type="button">Сбросить</button>
      </div>
    </div>

    <div class="crm-card crm-section-card p-0 table-responsive crm-admin-api-table-card">
      <table class="table crm-table mb-0">
        <thead><tr><th>Клиент</th><th>Статус</th><th style="width:110px">Действия</th></tr></thead>
        <tbody id="apcClientsTableBody">
          <tr><td colspan="3" class="text-muted">Загрузка API-клиентов...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-xl-7">
    <div class="crm-card crm-section-card mb-3">
      <h2 class="h6 mb-3">Создать API-клиент</h2>
      <form id="apcCreateForm" class="row g-2">
        <div class="col-12"><label class="form-label">Название</label><input class="form-control" name="title" maxlength="255" required></div>
        <div class="col-12"><label class="form-label">Права API (через запятую)</label><input class="form-control" name="scopes" placeholder="tasks.read,projects.read"></div>
        <div class="col-6"><label class="form-label">Активен</label><select class="form-select" name="is_active"><option value="1">Да</option><option value="0">Нет</option></select></div>
        <div class="col-12"><button class="btn crm-btn-primary" type="submit">Создать API-клиент</button></div>
      </form>
    </div>

    <div class="crm-card crm-section-card">
      <h2 class="h6 mb-3">Редактировать API-клиент</h2>
      <form id="apcEditForm" class="row g-2">
        <div class="col-12"><input type="hidden" name="public_id"><label class="form-label">Название</label><input class="form-control" name="title" maxlength="255" required></div>
        <div class="col-12"><label class="form-label">Права API (через запятую)</label><input class="form-control" name="scopes"></div>
        <div class="col-6"><label class="form-label">Активен</label><select class="form-select" name="is_active"><option value="1">Да</option><option value="0">Нет</option></select></div>
        <div class="col-12 d-flex gap-2"><button class="btn crm-btn-primary" type="submit">Сохранить</button><button id="apcDeleteClientBtn" class="btn crm-btn-danger-soft" type="button">Удалить API-клиент</button></div>
      </form>
      <div id="apcSelectedClientHint" class="small text-muted mt-2">Выберите клиента в таблице слева.</div>
    </div>
  </div>
</div>

<div class="crm-card crm-section-card mt-3 crm-admin-api-keys-card">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 id="apcKeysTitle" class="h6 mb-0">Ключи API-клиента</h2>
  </div>
  <div class="row g-3 mb-3">
    <div class="col-lg-8">
      <form id="apcIssueKeyForm" class="row g-2">
        <div class="col-md-6"><label class="form-label">Права ключа (опционально)</label><input class="form-control" name="scopes" placeholder="пусто = права клиента"></div>
        <div class="col-md-4"><label class="form-label">Истекает (YYYY-MM-DD HH:MM:SS)</label><input class="form-control" name="expires_at" placeholder="2026-12-31 23:59:59"></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn crm-btn-secondary w-100" type="submit">Выпустить</button></div>
      </form>
      <div id="apcPlainKeyWrap" class="alert alert-warning py-2 mt-2 d-none"></div>
    </div>
    <div class="col-lg-4">
      <div class="small text-muted">Выберите ключ в таблице, чтобы получить журналы использования, ротировать или отозвать ключ.</div>
      <div class="d-flex gap-2 mt-2">
        <button id="apcRotateKeyBtn" class="btn btn-sm crm-btn-secondary" type="button">Ротировать ключ</button>
        <button id="apcRevokeKeyBtn" class="btn btn-sm crm-btn-danger-soft" type="button">Отозвать ключ</button>
      </div>
    </div>
  </div>

  <div class="table-responsive mb-3">
    <table class="table crm-table mb-0">
      <thead><tr><th>ID ключа</th><th>Права API</th><th>Истекает</th><th>Отозван</th><th>Создан</th><th style="width:140px">Использование</th></tr></thead>
      <tbody id="apcKeysTableBody"><tr><td colspan="6" class="text-muted">Сначала выберите API-клиент.</td></tr></tbody>
    </table>
  </div>

  <div class="row g-3">
    <div class="col-lg-6"><div class="crm-card crm-soft-panel"><h3 class="h6">Журнал аудита</h3><div id="apcUsageAudit" class="small text-muted">Выберите ключ и нажмите «Использование».</div></div></div>
    <div class="col-lg-6"><div class="crm-card crm-soft-panel"><h3 class="h6">Журнал безопасности</h3><div id="apcUsageSecurity" class="small text-muted">Выберите ключ и нажмите «Использование».</div></div></div>
  </div>
</div>

<div class="crm-card crm-section-card mt-3 crm-admin-api-webhooks-card">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h6 mb-0">Вебхуки</h2>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-12">
      <div class="d-flex gap-2 flex-wrap mb-2">
        <input id="whSearchInput" class="form-control crm-field-w-260" placeholder="Поиск вебхука: название/endpoint">
        <select id="whActiveFilter" class="form-select crm-field-w-220">
          <option value="">Все вебхуки</option>
          <option value="1">Только активные</option>
          <option value="0">Только неактивные</option>
        </select>
        <button id="whResetBtn" class="btn crm-btn-muted" type="button">Сбросить</button>
      </div>
      <div class="table-responsive">
        <table class="table crm-table mb-0">
          <thead><tr><th>Webhook</th><th>Статус</th><th style="width:110px">Действия</th></tr></thead>
          <tbody id="whTableBody"><tr><td colspan="3" class="text-muted">Загрузка webhooks...</td></tr></tbody>
        </table>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="crm-form-panel h-100">
        <h3 class="h6 mb-2">Создать webhook</h3>
        <form id="whCreateForm" class="row g-2">
          <div class="col-12"><label class="form-label">Название</label><input class="form-control" name="title" maxlength="255" required></div>
          <div class="col-12"><label class="form-label">URL обработчика</label><input class="form-control" name="endpoint" maxlength="2048" placeholder="https://example.com/webhook" required></div>
          <div class="col-12"><label class="form-label">Секрет (опционально)</label><input class="form-control" name="secret" maxlength="255"></div>
          <div class="col-12"><label class="form-label">События (через запятую)</label><input class="form-control" name="events" placeholder="task.created,project.updated"></div>
          <div class="col-6"><label class="form-label">Активен</label><select class="form-select" name="is_active"><option value="1">Да</option><option value="0">Нет</option></select></div>
          <div class="col-12"><button class="btn crm-btn-primary" type="submit">Создать webhook</button></div>
        </form>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="crm-form-panel h-100">
        <h3 class="h6 mb-2">Редактировать выбранный webhook</h3>
        <form id="whEditForm" class="row g-2">
          <input type="hidden" name="public_id">
          <div class="col-12"><label class="form-label">Название</label><input class="form-control" name="title" maxlength="255"></div>
          <div class="col-12"><label class="form-label">URL обработчика</label><input class="form-control" name="endpoint" maxlength="2048"></div>
          <div class="col-12"><label class="form-label">Секрет (если заполнить — будет обновлён)</label><input class="form-control" name="secret" maxlength="255"></div>
          <div class="col-12"><label class="form-label">События (через запятую)</label><input class="form-control" name="events"></div>
          <div class="col-6"><label class="form-label">Активен</label><select class="form-select" name="is_active"><option value="1">Да</option><option value="0">Нет</option></select></div>
          <div class="col-12 d-flex gap-2 flex-wrap">
            <button class="btn crm-btn-primary" type="submit">Сохранить</button>
            <button id="whTestBtn" class="btn crm-btn-secondary" type="button">Отправить тест</button>
            <button id="whDeleteBtn" class="btn crm-btn-danger-soft" type="button">Удалить webhook</button>
          </div>
        </form>
        <div id="whSelectedHint" class="small text-muted mt-2">Выберите webhook в таблице слева.</div>
      </div>
    </div>
  </div>

  <div class="crm-soft-panel">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h3 class="h6 mb-0">Журнал доставок выбранного webhook</h3>
      <button id="whDeliveriesRefreshBtn" class="btn btn-sm crm-btn-secondary" type="button">Обновить</button>
    </div>
    <div class="table-responsive">
      <table class="table crm-table mb-0">
        <thead><tr><th>Время</th><th>Событие</th><th>Статус</th><th>HTTP</th><th>URL обработчика</th></tr></thead>
        <tbody id="whDeliveriesBody"><tr><td colspan="5" class="text-muted">Выберите webhook, чтобы увидеть доставки.</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

</main></div></div>
