<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Карточка контрагента'; ?>
<body data-page="counterparties" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-counterparty-detail-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=dashboard">Главная</a></li><li class="breadcrumb-item"><a href="index.php?route=counterparties">Контрагенты</a></li><li class="breadcrumb-item active">Карточка контрагента</li></ol><h1 class="crm-page-title" id="counterpartyDetailTitle">Загрузка контрагента...</h1><p class="crm-subtitle" id="counterpartyDetailSubtitle">Загрузка параметров контрагента...</p></div><div class="d-flex gap-2 flex-wrap"><button class="btn crm-btn-secondary" id="counterpartyDetailEditBtn" type="button">Редактировать контрагента</button><a class="btn crm-btn-secondary" href="index.php?route=counterparties">Назад к списку</a></div></div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Профиль контрагента</h2><div class="crm-section-note">Основные реквизиты, контакты и юридическая информация.</div></div><button class="btn btn-sm crm-inline-icon-btn" id="counterpartyProfileEditBtn" type="button" aria-label="Редактировать профиль"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span></button></div>
      <div id="counterpartyDetailProfile"><div class="text-muted">Загрузка карточки...</div></div>
    </div>
    <div class="crm-card crm-section-card mt-3">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Контакты</h2><div class="crm-section-note">Связанные контакты контрагента.</div></div><button class="btn btn-sm crm-btn-primary" id="counterpartyContactAddBtn" type="button"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span> Добавить контакт</button></div>
      <div class="table-responsive"><table class="table crm-table mb-0"><thead><tr><th>ФИО</th><th>Роль</th><th>Email</th><th>Телефон</th><th style="width:100px"></th></tr></thead><tbody id="counterpartyDetailContactsBody"><tr><td colspan="5" class="text-muted">Загрузка контактов...</td></tr></tbody></table></div>
    </div>
    <div class="crm-card crm-section-card mt-3">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Связанные задачи</h2><div class="crm-section-note">Последние задачи по этому контрагенту.</div></div></div>
      <div class="table-responsive"><table class="table crm-table mb-0"><thead><tr><th>Задача</th><th>Статус</th><th>Проект</th><th>Обновлена</th></tr></thead><tbody id="counterpartyDetailTasksBody"><tr><td colspan="4" class="text-muted">Загрузка задач...</td></tr></tbody></table></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="crm-card crm-section-card mb-3">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Сводка</h2><div class="crm-section-note">Быстрые метрики по карточке контрагента.</div></div></div>
      <div id="counterpartyDetailSummary"><div class="text-muted">Загрузка...</div></div>
    </div>
    <div class="crm-card crm-section-card mt-3">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Реквизиты</h2><div class="crm-section-note">Юридические и банковские данные.</div></div><button class="btn btn-sm crm-inline-icon-btn" id="counterpartyRequisitesEditBtn" type="button" aria-label="Редактировать реквизиты"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span></button></div>
      <div id="counterpartyDetailRequisites"><div class="text-muted">Загрузка...</div></div>
    </div>
    <div class="crm-card crm-section-card mt-3">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Дополнительные поля</h2><div class="crm-section-note">Кастомные поля контрагента.</div></div><button class="btn btn-sm crm-inline-icon-btn" id="counterpartyExtraEditBtn" type="button" aria-label="Редактировать дополнительные поля"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span></button></div>
      <div id="counterpartyDetailExtra"><div class="text-muted">Нет дополнительных атрибутов</div></div>
    </div>
  </div>
</div>
</main></div></div>

<div class="modal fade" id="counterpartyDetailEditModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Редактировать контрагента</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="counterpartyDetailEditForm" novalidate><div class="modal-body"><input type="hidden" name="public_id"><div class="row g-3">
  <div class="col-12 d-none" data-form-error-summary></div>
  <div class="col-md-4"><label class="form-label">Тип контрагента</label><select class="form-select" name="counterparty_type" data-counterparty-type-input><option value="organization">Организация</option><option value="individual">Физлицо</option><option value="sole_proprietor">ИП</option><option value="legal_entity">Юрлицо</option></select></div>
  <div class="col-md-8"><label class="form-label">Название</label><input class="form-control" name="title" maxlength="255" required></div>
  <div class="col-md-6" data-counterparty-type-group="sole_proprietor,legal_entity"><label class="form-label">Юридическое наименование / ФИО ИП</label><input class="form-control" name="legal_name" maxlength="255"></div>
  <div class="col-md-4" data-counterparty-type-group="sole_proprietor,legal_entity"><label class="form-label">ИНН</label><input class="form-control" name="tax_inn" maxlength="12"></div>
  <div class="col-md-4" data-counterparty-type-group="legal_entity"><label class="form-label">КПП</label><input class="form-control" name="tax_kpp" maxlength="9"></div>
  <div class="col-md-4" data-counterparty-type-group="legal_entity"><label class="form-label">ОГРН</label><input class="form-control" name="tax_ogrn" maxlength="13"></div>
  <div class="col-md-4" data-counterparty-type-group="sole_proprietor"><label class="form-label">ОГРНИП</label><input class="form-control" name="tax_ogrnip" maxlength="15"></div>
  <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" maxlength="190"></div>
  <div class="col-md-4"><label class="form-label">Телефон</label><input class="form-control" name="phone" maxlength="64"></div>
  <div class="col-md-4"><label class="form-label">Сайт</label><input class="form-control" name="website" maxlength="2048"></div>
  <div class="col-md-4"><label class="form-label">Мессенджер</label><input class="form-control" name="messenger" maxlength="190"></div>
  <div class="col-md-4" data-counterparty-type-group="sole_proprietor,legal_entity"><label class="form-label">Расчетный счет</label><input class="form-control" name="bank_account" maxlength="34"></div>
  <div class="col-md-4" data-counterparty-type-group="sole_proprietor,legal_entity"><label class="form-label">БИК</label><input class="form-control" name="bank_bik" maxlength="9"></div>
  <div class="col-md-4" data-counterparty-type-group="sole_proprietor,legal_entity"><label class="form-label">Корр. счет</label><input class="form-control" name="bank_corr_account" maxlength="34"></div>
  <div class="col-md-8" data-counterparty-type-group="sole_proprietor,legal_entity"><label class="form-label">Банк</label><input class="form-control" name="bank_name" maxlength="255"></div>
  <div class="col-md-4"><label class="form-label">Статус</label><input class="form-control" name="status" maxlength="64"></div>
  <div class="col-md-6"><label class="form-label">Юридический адрес</label><textarea class="form-control" name="address_legal" rows="2"></textarea></div>
  <div class="col-md-6"><label class="form-label">Почтовый адрес</label><textarea class="form-control" name="address_postal" rows="2"></textarea></div>
  <div class="col-12"><label class="form-label">Комментарий</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
</div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit">Сохранить</button></div></form></div></div></div>

<div class="modal fade" id="counterpartyProfileEditModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Редактировать профиль</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="counterpartyProfileEditForm" novalidate><div class="modal-body"><input type="hidden" name="public_id"><div class="row g-3">
  <div class="col-12 d-none" data-form-error-summary></div>
  <div class="col-md-4"><label class="form-label">Тип контрагента</label><select class="form-select" name="counterparty_type" data-profile-type-input><option value="organization">Организация</option><option value="individual">Физлицо</option><option value="sole_proprietor">ИП</option><option value="legal_entity">Юрлицо</option></select></div>
  <div class="col-md-8"><label class="form-label">Название</label><input class="form-control" name="title" maxlength="255" required></div>
  <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" maxlength="190"></div>
  <div class="col-md-4"><label class="form-label">Телефон</label><input class="form-control" name="phone" maxlength="64"></div>
  <div class="col-md-4"><label class="form-label">Сайт</label><input class="form-control" name="website" maxlength="2048"></div>
  <div class="col-md-4"><label class="form-label">Мессенджер</label><input class="form-control" name="messenger" maxlength="190"></div>
  <div class="col-md-4"><label class="form-label">Статус</label><select class="form-select" name="status"><option value="active">Активен</option><option value="inactive">Неактивен</option><option value="blocked">Заблокирован</option></select></div>
  <div class="col-12"><label class="form-label">Комментарий</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
</div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit">Сохранить</button></div></form></div></div></div>

<div class="modal fade" id="counterpartyContactModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="counterpartyContactModalTitle">Добавить контакт</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="counterpartyContactForm" novalidate><div class="modal-body"><input type="hidden" name="public_id"><input type="hidden" name="counterparty_public_id"><div class="row g-3">
  <div class="col-12 d-none" data-form-error-summary></div>
  <div class="col-12"><label class="form-label">ФИО</label><input class="form-control" name="full_name" maxlength="255" required></div>
  <div class="col-md-6"><label class="form-label">Роль</label><select class="form-select" name="role"><option value="decision_maker">ЛПР</option><option value="influencer">Влияющий</option><option value="user">Пользователь</option><option value="technical">Технический</option><option value="contact">Контакт</option></select></div>
  <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" maxlength="190"></div>
  <div class="col-md-6"><label class="form-label">Телефон</label><input class="form-control" name="phone" maxlength="64"></div>
</div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit">Сохранить</button></div></form></div></div></div>

<div class="modal fade" id="counterpartyRequisitesEditModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Редактировать реквизиты</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="counterpartyRequisitesEditForm" novalidate><div class="modal-body"><input type="hidden" name="public_id"><div class="row g-3">
  <div class="col-12 d-none" data-form-error-summary></div>
  <div class="col-md-4"><label class="form-label">Юридическое наименование</label><input class="form-control" name="legal_name" maxlength="255"></div>
  <div class="col-md-4 position-relative" data-ru-field><label class="form-label">ИНН <small class="text-muted">(введите для поиска)</small></label><input class="form-control" name="tax_inn" maxlength="12" id="requisitesInnInput" autocomplete="off"><div id="dadataSuggestions" class="list-group position-absolute w-100" style="z-index:1050;display:none;"></div></div>
  <div class="col-md-4" data-ru-field><label class="form-label">КПП</label><input class="form-control" name="tax_kpp" maxlength="9"></div>
  <div class="col-md-4" data-intl-field style="display:none"><label class="form-label">Tax ID / VAT Number</label><input class="form-control" name="tax_inn" maxlength="32"></div>
  <div class="col-md-4" data-intl-field style="display:none"><label class="form-label">Company Registration No.</label><input class="form-control" name="tax_kpp" maxlength="32"></div>
  <div class="col-md-4" data-ru-field data-req-type-group="organization,legal_entity"><label class="form-label">ОГРН</label><input class="form-control" name="tax_ogrn" maxlength="13"></div>
  <div class="col-md-4" data-ru-field data-req-type-group="sole_proprietor"><label class="form-label">ОГРНИП</label><input class="form-control" name="tax_ogrnip" maxlength="15"></div>
  <div class="col-md-4" data-intl-field style="display:none"><label class="form-label">Registration Number</label><input class="form-control" name="tax_ogrn" maxlength="32"></div>
  <div class="col-md-4" data-intl-field style="display:none"><label class="form-label">Tax Registration No.</label><input class="form-control" name="tax_ogrnip" maxlength="32"></div>
  <div class="col-md-4"><label class="form-label">Расчетный счет / Account No.</label><input class="form-control" name="bank_account" maxlength="34"></div>
  <div class="col-md-4"><label class="form-label">БИК / SWIFT / BIC</label><input class="form-control" name="bank_bik" maxlength="16"></div>
  <div class="col-md-4"><label class="form-label">Корр. счет / IBAN</label><input class="form-control" name="bank_corr_account" maxlength="34"></div>
  <div class="col-md-8"><label class="form-label">Банк</label><input class="form-control" name="bank_name" maxlength="255"></div>
  <div class="col-md-6"><label class="form-label">Юридический адрес</label><textarea class="form-control" name="address_legal" rows="2"></textarea></div>
  <div class="col-md-6"><label class="form-label">Почтовый адрес</label><textarea class="form-control" name="address_postal" rows="2"></textarea></div>
</div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit">Сохранить</button></div></form></div></div></div>

<div class="modal fade" id="counterpartyExtraEditModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Редактировать дополнительные поля</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="counterpartyExtraEditForm" novalidate><div class="modal-body"><input type="hidden" name="public_id"><div class="row g-3">
  <div class="col-12 d-none" data-form-error-summary></div>
  <div id="counterpartyExtraFieldsContainer"><div class="text-muted">Нет дополнительных полей.</div></div>
  <div class="col-12 mt-3"><button type="button" class="btn btn-sm crm-btn-secondary" id="addExtraFieldBtn"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span> Добавить поле</button></div>
</div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit">Сохранить</button></div></form></div></div></div>
