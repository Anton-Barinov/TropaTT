<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Контакты'; ?>
<body data-page="contacts" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-contacts-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=dashboard">Главная</a></li><li class="breadcrumb-item active">Контакты</li></ol><h1 class="crm-page-title">Контакты</h1><p class="crm-subtitle">Контактные лица контрагентов.</p></div><div class="crm-page-actions"><button class="btn crm-btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#contactCreateModal">Создать контакт</button></div></div>

<div class="crm-card crm-section-card crm-filters-card mb-3">
  <div class="row g-2 crm-contacts-filters">
    <div class="col-md-5"><label class="form-label" for="contactsFilterSearch">Поиск</label><input id="contactsFilterSearch" class="form-control" placeholder="ФИО, email, телефон"></div>
    <div class="col-md-3"><label class="form-label">Контрагент</label><select id="contactsFilterCounterparty" class="form-select"><option value="">Все контрагенты</option></select></div>
    <div class="col-md-2"><label class="form-label">Роль</label><select id="contactsFilterRole" class="form-select"><option value="">Все роли</option><option value="decision_maker">ЛПР</option><option value="influencer">Влияющий</option><option value="user">Пользователь</option><option value="technical">Технический</option></select></div>
    <div class="col-md-2"><label class="form-label">Основной</label><select id="contactsFilterPrimary" class="form-select"><option value="">Все</option><option value="1">Только основные</option></select></div>
    <div class="col-12 d-flex justify-content-end"><button class="btn crm-btn-muted" type="button" id="contactsFilterReset">Сбросить</button></div>
  </div>
</div>

<div class="crm-card crm-section-card p-0 table-responsive"><table id="contactsTable" class="table crm-table mb-0"><thead><tr><th>Контакт</th><th>Контрагент</th><th>Роль</th><th>Email</th><th>Телефон</th><th>Основной</th><th style="width:96px">Действия</th></tr></thead><tbody id="contactsTableBody"><tr><td colspan="7" class="text-muted">Загрузка контактов...</td></tr></tbody></table></div>
</main></div></div>

<div class="modal fade" id="contactCreateModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Создать контакт</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div><form id="contactsCreateForm"><div class="modal-body"><div class="row g-3">
  <div class="col-12 d-none" data-form-error-summary></div>
  <div class="col-md-6"><label class="form-label">ФИО *</label><input class="form-control" name="full_name" maxlength="255" required></div>
  <div class="col-md-6"><label class="form-label">Контрагент</label><select class="form-select" name="counterparty_public_id" id="contactCreateCounterpartySelect"><option value="">Выберите контрагента</option></select></div>
  <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" maxlength="190"></div>
  <div class="col-md-4"><label class="form-label">Телефон</label><input class="form-control" name="phone" maxlength="64"></div>
  <div class="col-md-2"><label class="form-label">Роль</label><select class="form-select" name="role"><option value="">Без роли</option><option value="decision_maker">ЛПР</option><option value="influencer">Влияющий</option><option value="user">Пользователь</option><option value="technical">Технический</option></select></div>
  <div class="col-md-2 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_primary" id="contactCreateIsPrimary"><label class="form-check-label" for="contactCreateIsPrimary">Основной</label></div></div>
</div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit">Создать</button></div></form></div></div></div>

<div class="modal fade" id="contactEditModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Редактировать контакт</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div><form id="contactsEditForm"><div class="modal-body"><input type="hidden" name="public_id"><div class="row g-3">
  <div class="col-12 d-none" data-form-error-summary></div>
  <div class="col-md-6"><label class="form-label">ФИО *</label><input class="form-control" name="full_name" maxlength="255" required></div>
  <div class="col-md-6"><label class="form-label">Контрагент</label><select class="form-select" name="counterparty_public_id" id="contactEditCounterpartySelect"><option value="">Выберите контрагента</option></select></div>
  <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" maxlength="190"></div>
  <div class="col-md-4"><label class="form-label">Телефон</label><input class="form-control" name="phone" maxlength="64"></div>
  <div class="col-md-2"><label class="form-label">Роль</label><select class="form-select" name="role"><option value="">Без роли</option><option value="decision_maker">ЛПР</option><option value="influencer">Влияющий</option><option value="user">Пользователь</option><option value="technical">Технический</option></select></div>
  <div class="col-md-2 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_primary" id="contactEditIsPrimary"><label class="form-check-label" for="contactEditIsPrimary">Основной</label></div></div>
</div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-danger-soft me-auto" type="button" id="contactsDeleteInModalBtn">Удалить контакт</button><button class="btn crm-btn-primary" type="submit">Сохранить</button></div></form></div></div></div>
