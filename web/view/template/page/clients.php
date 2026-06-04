<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Клиенты'; ?>
<body data-page="clients" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-clients-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=dashboard">Главная</a></li><li class="breadcrumb-item active">Клиенты</li></ol><h1 class="crm-page-title">Клиенты</h1><p class="crm-subtitle">Список клиентов, контактов и реквизитов.</p></div><div class="crm-page-actions"><select id="clientsSavedViewSelect" class="form-select form-select-sm crm-field-w-220" aria-label="Сохраненные виды клиентов"><option value="">Вид по умолчанию</option></select><button id="clientsSaveViewBtn" class="btn crm-btn-secondary" type="button">Сохранить вид</button><button id="clientsDeleteViewBtn" class="btn crm-btn-secondary" type="button">Удалить вид</button><a class="btn crm-btn-secondary" href="index.php?route=companies">Компании</a><a class="btn crm-btn-secondary" href="index.php?route=contacts">Контакты</a><button class="btn crm-btn-muted" type="button" id="clientsCompactToggle">Компактный вид</button><button class="btn crm-btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#clientCreateModal">Создать клиента</button></div></div>

<div class="crm-card crm-section-card crm-filters-card mb-3">
  <div class="row g-2 crm-clients-filters">
    <div class="col-md-5"><label class="form-label" for="clientsFilterSearch">Поиск</label><input id="clientsFilterSearch" class="form-control" placeholder="Название, ИНН, email, телефон, сайт"></div>
    <div class="col-md-3"><label class="form-label">Тип клиента</label><select id="clientsFilterType" class="form-select"><option value="">Все типы</option><option value="individual">Физлицо</option><option value="sole_proprietor">ИП</option><option value="legal_entity">Юрлицо</option></select></div>
    <div class="col-md-2"><label class="form-label">Статус</label><input id="clientsFilterStatus" class="form-control" placeholder="active"></div>
    <div class="col-md-2"><label class="form-label">Сайт</label><select id="clientsFilterHasWebsite" class="form-select"><option value="">Любой</option><option value="1">Только с сайтом</option><option value="0">Без сайта</option></select></div>
    <div class="col-md-3"><label class="form-label">Создан с</label><input id="clientsFilterCreatedFrom" type="date" class="form-control"></div>
    <div class="col-md-3"><label class="form-label">Создан по</label><input id="clientsFilterCreatedTo" type="date" class="form-control"></div>
    <div class="col-md-3"><label class="form-label">Сортировка</label><select id="clientsSortBy" class="form-select"><option value="created_at">Дата создания</option><option value="updated_at">Дата обновления</option><option value="title">Название</option></select></div>
    <div class="col-md-3"><label class="form-label">Направление</label><select id="clientsSortDir" class="form-select"><option value="DESC">По убыванию</option><option value="ASC">По возрастанию</option></select></div>
    <div class="col-12 d-flex justify-content-end"><button class="btn crm-btn-muted" type="button" id="clientsFilterReset">Сбросить</button></div>
  </div>
</div>

<div id="clientsBulkActionsBar" class="alert alert-primary d-none d-flex justify-content-between align-items-center" role="region" aria-label="Bulk actions clients">
  <div>Выбрано клиентов: <strong data-clients-selected-count>0</strong> <span class="small ms-2" id="clientsBulkResult" aria-live="polite"></span></div>
  <div class="d-flex gap-2">
    <select id="clientsBulkStatusSelect" class="form-select form-select-sm crm-field-w-170" aria-label="Изменить статус клиентов">
      <option value="">Статус...</option>
      <option value="active">Активен</option>
      <option value="inactive">Неактивен</option>
      <option value="archived">Архив</option>
    </select>
    <button class="btn btn-sm crm-btn-danger-soft" type="button" id="clientsBulkDeleteBtn">Удалить</button>
  </div>
</div>

<div class="crm-card crm-section-card p-0 table-responsive"><table id="clientsTable" class="table crm-table mb-0"><thead><tr><th style="width:40px"><input class="form-check-input" type="checkbox" id="clientsBulkSelectAll"></th><th>Клиент</th><th>Тип</th><th>ИНН</th><th>Email</th><th>Телефон</th><th>Статус</th><th>Обновлен</th><th style="width:96px">Действия</th></tr></thead><tbody id="clientsTableBody"><tr><td colspan="9" class="text-muted">Загрузка клиентов...</td></tr></tbody></table></div>
</main></div></div>

<div class="modal fade" id="clientCreateModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Создать клиента</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div><form id="clientsCreateForm"><div class="modal-body"><div class="row g-3">
  <div class="col-12 d-none" data-form-error-summary></div>
  <div class="col-md-4"><label class="form-label">Тип клиента</label><select class="form-select" name="client_type" data-client-type-input><option value="individual">Физлицо</option><option value="sole_proprietor">ИП</option><option value="legal_entity">Юрлицо</option></select></div>
  <div class="col-md-8"><label class="form-label">Название</label><input class="form-control" name="title" maxlength="255" required></div>
  <div class="col-md-6" data-client-type-group="sole_proprietor,legal_entity"><label class="form-label">Юридическое наименование / ФИО ИП</label><input class="form-control" name="legal_name" maxlength="255"></div>
  <div class="col-md-4" data-client-type-group="sole_proprietor,legal_entity"><label class="form-label">ИНН</label><input class="form-control" name="tax_inn" maxlength="12"></div>
  <div class="col-md-4" data-client-type-group="legal_entity"><label class="form-label">КПП</label><input class="form-control" name="tax_kpp" maxlength="9"></div>
  <div class="col-md-4" data-client-type-group="legal_entity"><label class="form-label">ОГРН</label><input class="form-control" name="tax_ogrn" maxlength="13"></div>
  <div class="col-md-4" data-client-type-group="sole_proprietor"><label class="form-label">ОГРНИП</label><input class="form-control" name="tax_ogrnip" maxlength="15"></div>
  <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" maxlength="190"></div>
  <div class="col-md-4"><label class="form-label">Телефон</label><input class="form-control" name="phone" maxlength="64"></div>
  <div class="col-md-4"><label class="form-label">Сайт</label><input class="form-control" name="website" maxlength="2048" placeholder="https://example.com"></div>
  <div class="col-md-4"><label class="form-label">Мессенджер</label><input class="form-control" name="messenger" maxlength="190"></div>
  <div class="col-md-4" data-client-type-group="sole_proprietor,legal_entity"><label class="form-label">Расчетный счет</label><input class="form-control" name="bank_account" maxlength="34"></div>
  <div class="col-md-4" data-client-type-group="sole_proprietor,legal_entity"><label class="form-label">БИК</label><input class="form-control" name="bank_bik" maxlength="9"></div>
  <div class="col-md-4" data-client-type-group="sole_proprietor,legal_entity"><label class="form-label">Корр. счет</label><input class="form-control" name="bank_corr_account" maxlength="34"></div>
  <div class="col-md-8" data-client-type-group="sole_proprietor,legal_entity"><label class="form-label">Банк</label><input class="form-control" name="bank_name" maxlength="255"></div>
  <div class="col-md-4"><label class="form-label">Статус</label><input class="form-control" name="status" maxlength="64" value="active"></div>
  <div class="col-md-6"><label class="form-label">Юридический адрес</label><textarea class="form-control" name="address_legal" rows="2"></textarea></div>
  <div class="col-md-6"><label class="form-label">Почтовый адрес</label><textarea class="form-control" name="address_postal" rows="2"></textarea></div>
  <div class="col-12"><label class="form-label">Комментарий</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
  <div class="col-12"><label class="form-label">Дополнительные поля (JSON)</label><textarea class="form-control" name="extra_attributes_text" rows="3"></textarea></div>
</div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit">Создать</button></div></form></div></div></div>

<div class="modal fade" id="clientEditModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Редактировать клиента</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div><form id="clientsEditForm"><div class="modal-body"><input type="hidden" name="public_id"><div class="row g-3">
  <div class="col-12 d-none" data-form-error-summary></div>
  <div class="col-md-4"><label class="form-label">Тип клиента</label><select class="form-select" name="client_type" data-client-type-input><option value="individual">Физлицо</option><option value="sole_proprietor">ИП</option><option value="legal_entity">Юрлицо</option></select></div>
  <div class="col-md-8"><label class="form-label">Название</label><input class="form-control" name="title" maxlength="255" required></div>
  <div class="col-md-6" data-client-type-group="sole_proprietor,legal_entity"><label class="form-label">Юридическое наименование / ФИО ИП</label><input class="form-control" name="legal_name" maxlength="255"></div>
  <div class="col-md-4" data-client-type-group="sole_proprietor,legal_entity"><label class="form-label">ИНН</label><input class="form-control" name="tax_inn" maxlength="12"></div>
  <div class="col-md-4" data-client-type-group="legal_entity"><label class="form-label">КПП</label><input class="form-control" name="tax_kpp" maxlength="9"></div>
  <div class="col-md-4" data-client-type-group="legal_entity"><label class="form-label">ОГРН</label><input class="form-control" name="tax_ogrn" maxlength="13"></div>
  <div class="col-md-4" data-client-type-group="sole_proprietor"><label class="form-label">ОГРНИП</label><input class="form-control" name="tax_ogrnip" maxlength="15"></div>
  <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" maxlength="190"></div>
  <div class="col-md-4"><label class="form-label">Телефон</label><input class="form-control" name="phone" maxlength="64"></div>
  <div class="col-md-4"><label class="form-label">Сайт</label><input class="form-control" name="website" maxlength="2048"></div>
  <div class="col-md-4"><label class="form-label">Мессенджер</label><input class="form-control" name="messenger" maxlength="190"></div>
  <div class="col-md-4" data-client-type-group="sole_proprietor,legal_entity"><label class="form-label">Расчетный счет</label><input class="form-control" name="bank_account" maxlength="34"></div>
  <div class="col-md-4" data-client-type-group="sole_proprietor,legal_entity"><label class="form-label">БИК</label><input class="form-control" name="bank_bik" maxlength="9"></div>
  <div class="col-md-4" data-client-type-group="sole_proprietor,legal_entity"><label class="form-label">Корр. счет</label><input class="form-control" name="bank_corr_account" maxlength="34"></div>
  <div class="col-md-8" data-client-type-group="sole_proprietor,legal_entity"><label class="form-label">Банк</label><input class="form-control" name="bank_name" maxlength="255"></div>
  <div class="col-md-4"><label class="form-label">Статус</label><input class="form-control" name="status" maxlength="64"></div>
  <div class="col-md-6"><label class="form-label">Юридический адрес</label><textarea class="form-control" name="address_legal" rows="2"></textarea></div>
  <div class="col-md-6"><label class="form-label">Почтовый адрес</label><textarea class="form-control" name="address_postal" rows="2"></textarea></div>
  <div class="col-12"><label class="form-label">Комментарий</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
  <div class="col-12"><label class="form-label">Дополнительные поля (JSON)</label><textarea class="form-control" name="extra_attributes_text" rows="3"></textarea></div>
</div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-danger-soft me-auto" type="button" id="clientsDeleteInModalBtn">Удалить клиента</button><button class="btn crm-btn-primary" type="submit">Сохранить</button></div></form></div></div></div>
