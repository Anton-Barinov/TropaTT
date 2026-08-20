<?php declare(strict_types=1); ?>
<?php $title = $t('counterparty_detail.title', 'TropaTT — Карточка контрагента'); ?>
<body data-page="counterparties" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-counterparty-detail-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=dashboard" data-i18n="page.dashboard"><?= htmlspecialchars($t('page.dashboard', 'Главная'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item"><a href="index.php?route=counterparties" data-i18n="counterparties.page_title"><?= htmlspecialchars($t('counterparties.page_title', 'Контрагенты'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="counterparty_detail.breadcrumb"><?= htmlspecialchars($t('counterparty_detail.breadcrumb', 'Карточка контрагента'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" id="counterpartyDetailTitle" data-i18n="counterparty_detail.loading_title"><?= htmlspecialchars($t('counterparty_detail.loading_title', 'Загрузка контрагента...'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" id="counterpartyDetailSubtitle" data-i18n="counterparty_detail.loading_subtitle"><?= htmlspecialchars($t('counterparty_detail.loading_subtitle', 'Загрузка параметров контрагента...'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="d-flex gap-2 flex-wrap"><button class="btn crm-btn-primary" id="counterpartyCreateTaskBtn" type="button" data-i18n="counterparty_detail.btn_create_task"><?= htmlspecialchars($t('counterparty_detail.btn_create_task', 'Создать задачу'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" id="counterpartyCreateProjectBtn" type="button" data-i18n="counterparty_detail.btn_create_project"><?= htmlspecialchars($t('counterparty_detail.btn_create_project', 'Создать проект'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-secondary" id="counterpartyDetailEditBtn" type="button" data-i18n="counterparty_detail.btn_edit"><?= htmlspecialchars($t('counterparty_detail.btn_edit', 'Редактировать контрагента'), ENT_QUOTES, 'UTF-8') ?></button><a class="btn crm-btn-secondary" href="index.php?route=counterparties" data-i18n="counterparty_detail.link_back"><?= htmlspecialchars($t('counterparty_detail.link_back', 'Назад к списку'), ENT_QUOTES, 'UTF-8') ?></a></div></div>

<div class="crm-pr-tabs" role="tablist" aria-label="<?= htmlspecialchars($t('counterparty_detail.tabs_label', 'Разделы контрагента'), ENT_QUOTES, 'UTF-8') ?>">
  <button class="crm-pr-tab active" type="button" role="tab" aria-selected="true" data-cp-tab="overview" id="counterpartyTabOverview"><?= htmlspecialchars($t('counterparty_detail.tab_overview', 'Обзор'), ENT_QUOTES, 'UTF-8') ?></button>
  <button class="crm-pr-tab" type="button" role="tab" aria-selected="false" data-cp-tab="contacts" id="counterpartyTabContacts"><?= htmlspecialchars($t('counterparty_detail.section_contacts', 'Контакты'), ENT_QUOTES, 'UTF-8') ?><span class="crm-pr-tab-count" id="counterpartyContactsTabCount">0</span></button>
  <button class="crm-pr-tab" type="button" role="tab" aria-selected="false" data-cp-tab="tasks" id="counterpartyTabTasks"><?= htmlspecialchars($t('counterparty_detail.section_tasks', 'Связанные задачи'), ENT_QUOTES, 'UTF-8') ?><span class="crm-pr-tab-count" id="counterpartyTasksTabCount">0</span></button>
  <button class="crm-pr-tab" type="button" role="tab" aria-selected="false" data-cp-tab="knowledge" id="counterpartyTabKnowledge"><?= htmlspecialchars($t('counterparty_detail.section_knowledge', 'База знаний'), ENT_QUOTES, 'UTF-8') ?></button>
</div>

<div class="crm-pr-panel active" data-cp-panel="overview" role="tabpanel">
  <section class="crm-card crm-section-card mb-3">
    <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="counterparty_detail.section_summary"><?= htmlspecialchars($t('counterparty_detail.section_summary', 'Сводка'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="counterparty_detail.section_summary_note"><?= htmlspecialchars($t('counterparty_detail.section_summary_note', 'Быстрые метрики по карточке контрагента.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
    <div id="counterpartyDetailSummary"><div class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
  </section>

  <section class="crm-card crm-section-card mb-3">
    <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="counterparty_detail.section_profile"><?= htmlspecialchars($t('counterparty_detail.section_profile', 'Профиль контрагента'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="counterparty_detail.section_profile_note"><?= htmlspecialchars($t('counterparty_detail.section_profile_note', 'Основные реквизиты, контакты и юридическая информация.'), ENT_QUOTES, 'UTF-8') ?></div></div><button class="btn btn-sm crm-inline-icon-btn" id="counterpartyProfileEditBtn" type="button" aria-label="<?= htmlspecialchars($t('counterparty_detail.btn_edit_profile_aria', 'Редактировать профиль'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="counterparty_detail.btn_edit_profile_aria"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen" aria-hidden="true"></i></span></button></div>
    <div id="counterpartyDetailProfile"><div class="text-muted" data-i18n="counterparty_detail.loading_profile"><?= htmlspecialchars($t('counterparty_detail.loading_profile', 'Загрузка карточки...'), ENT_QUOTES, 'UTF-8') ?></div></div>
  </section>

  <section class="crm-pr-acc" id="counterpartyRequisitesAcc">
    <button class="crm-pr-acc-head" type="button" aria-expanded="false">
      <span class="crm-pr-acc-title"><span class="name" data-i18n="counterparty_detail.section_requisites"><?= htmlspecialchars($t('counterparty_detail.section_requisites', 'Реквизиты'), ENT_QUOTES, 'UTF-8') ?></span><span class="meta" data-i18n="counterparty_detail.section_requisites_note"><?= htmlspecialchars($t('counterparty_detail.section_requisites_note', 'Юридические и банковские данные.'), ENT_QUOTES, 'UTF-8') ?></span></span>
      <span class="crm-pr-acc-caret" aria-hidden="true">▾</span>
    </button>
    <div class="crm-pr-acc-panel"><div class="crm-pr-acc-inner">
      <div class="d-flex justify-content-end mb-2"><button class="btn btn-sm crm-inline-icon-btn" id="counterpartyRequisitesEditBtn" type="button" aria-label="<?= htmlspecialchars($t('counterparty_detail.btn_edit_requisites_aria', 'Редактировать реквизиты'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="counterparty_detail.btn_edit_requisites_aria"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen" aria-hidden="true"></i></span></button></div>
      <div id="counterpartyDetailRequisites"><div class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
    </div></div>
  </section>

  <section class="crm-pr-acc" id="counterpartyExtraAcc">
    <button class="crm-pr-acc-head" type="button" aria-expanded="false">
      <span class="crm-pr-acc-title"><span class="name" data-i18n="counterparty_detail.section_extra"><?= htmlspecialchars($t('counterparty_detail.section_extra', 'Дополнительные поля'), ENT_QUOTES, 'UTF-8') ?></span><span class="meta" data-i18n="counterparty_detail.section_extra_note"><?= htmlspecialchars($t('counterparty_detail.section_extra_note', 'Кастомные поля контрагента.'), ENT_QUOTES, 'UTF-8') ?></span></span>
      <span class="crm-pr-acc-caret" aria-hidden="true">▾</span>
    </button>
    <div class="crm-pr-acc-panel"><div class="crm-pr-acc-inner">
      <div class="d-flex justify-content-end mb-2"><button class="btn btn-sm crm-inline-icon-btn" id="counterpartyExtraEditBtn" type="button" aria-label="<?= htmlspecialchars($t('counterparty_detail.btn_edit_extra_aria', 'Редактировать дополнительные поля'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="counterparty_detail.btn_edit_extra_aria"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-pen" aria-hidden="true"></i></span></button></div>
      <div id="counterpartyDetailExtra"><div class="text-muted" data-i18n="counterparty_detail.extra_empty"><?= htmlspecialchars($t('counterparty_detail.extra_empty', 'Нет дополнительных атрибутов'), ENT_QUOTES, 'UTF-8') ?></div></div>
    </div></div>
  </section>
</div>

<div class="crm-pr-panel" data-cp-panel="contacts" role="tabpanel">
  <section class="crm-card crm-section-card">
    <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="counterparty_detail.section_contacts"><?= htmlspecialchars($t('counterparty_detail.section_contacts', 'Контакты'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="counterparty_detail.section_contacts_note"><?= htmlspecialchars($t('counterparty_detail.section_contacts_note', 'Связанные контакты контрагента.'), ENT_QUOTES, 'UTF-8') ?></div></div><button class="btn btn-sm crm-btn-primary" id="counterpartyContactAddBtn" type="button" data-i18n="counterparty_detail.btn_add_contact"><?= htmlspecialchars($t('counterparty_detail.btn_add_contact', 'Добавить контакт'), ENT_QUOTES, 'UTF-8') ?></button></div>
    <div class="table-responsive"><table class="table crm-table mb-0"><thead><tr><th data-i18n="counterparty_detail.th_full_name"><?= htmlspecialchars($t('counterparty_detail.th_full_name', 'ФИО'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="counterparty_detail.th_role"><?= htmlspecialchars($t('counterparty_detail.th_role', 'Роль'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="counterparty_detail.th_email"><?= htmlspecialchars($t('counterparty_detail.th_email', 'Email'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="counterparty_detail.th_phone"><?= htmlspecialchars($t('counterparty_detail.th_phone', 'Телефон'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:140px" data-i18n="counterparty_detail.th_actions"><?= htmlspecialchars($t('counterparty_detail.th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="counterpartyDetailContactsBody"><tr><td colspan="5" class="text-muted" data-i18n="counterparty_detail.loading_contacts"><?= htmlspecialchars($t('counterparty_detail.loading_contacts', 'Загрузка контактов...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
  </section>
</div>

<div class="crm-pr-panel" data-cp-panel="tasks" role="tabpanel">
  <section class="crm-card crm-section-card">
    <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="counterparty_detail.section_tasks"><?= htmlspecialchars($t('counterparty_detail.section_tasks', 'Связанные задачи'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="counterparty_detail.section_tasks_note"><?= htmlspecialchars($t('counterparty_detail.section_tasks_note', 'Последние задачи по этому контрагенту.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
    <div class="table-responsive"><table class="table crm-table mb-0"><thead><tr><th data-i18n="counterparty_detail.th_task"><?= htmlspecialchars($t('counterparty_detail.th_task', 'Задача'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="counterparty_detail.th_status"><?= htmlspecialchars($t('counterparty_detail.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="counterparty_detail.th_project"><?= htmlspecialchars($t('counterparty_detail.th_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="counterparty_detail.th_updated"><?= htmlspecialchars($t('counterparty_detail.th_updated', 'Обновлена'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="counterpartyDetailTasksBody"><tr><td colspan="4" class="text-muted" data-i18n="counterparty_detail.loading_tasks"><?= htmlspecialchars($t('counterparty_detail.loading_tasks', 'Загрузка задач...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
  </section>
</div>

<div class="crm-pr-panel" data-cp-panel="knowledge" role="tabpanel">
  <section class="crm-card crm-section-card">
    <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="counterparty_detail.section_knowledge"><?= htmlspecialchars($t('counterparty_detail.section_knowledge', 'База знаний'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="counterparty_detail.section_knowledge_note"><?= htmlspecialchars($t('counterparty_detail.section_knowledge_note', 'Связанные страницы и документация.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
    <div id="counterpartyKnowledgeList"><div class="text-muted small" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
    <div class="mt-3 pt-3 border-top"><h3 class="h6 d-flex align-items-center gap-2 mb-2" data-i18n="counterparty_detail.team_knowledge_title"><span class="crm-icon text-muted" aria-hidden="true"><i class="fa-solid fa-users" aria-hidden="true"></i></span><?= htmlspecialchars($t('counterparty_detail.team_knowledge_title', 'Материалы команды'), ENT_QUOTES, 'UTF-8') ?></h3><div id="counterpartyTeamKnowledgeList"><div class="text-muted small" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
    <div class="mt-2 d-flex gap-2 flex-wrap"><a class="btn btn-sm crm-btn-primary" href="index.php?route=knowledge" data-i18n="counterparty_detail.btn_knowledge"><?= htmlspecialchars($t('counterparty_detail.btn_knowledge', 'Перейти в базу знаний'), ENT_QUOTES, 'UTF-8') ?></a><button id="counterpartyAttachKnowledgeBtn" class="btn btn-sm crm-btn-secondary" type="button" data-i18n="counterparty_detail.btn_attach_knowledge"><?= htmlspecialchars($t('counterparty_detail.btn_attach_knowledge', 'Прикрепить статью'), ENT_QUOTES, 'UTF-8') ?></button><a id="counterpartyCreateKnowledgeBtn" class="btn btn-sm crm-btn-secondary" href="index.php?route=knowledge" data-i18n="counterparty_detail.btn_create_knowledge"><?= htmlspecialchars($t('counterparty_detail.btn_create_knowledge', 'Создать связанную страницу'), ENT_QUOTES, 'UTF-8') ?></a></div>
  </section>
</div>
</main></div></div>

<div class="modal fade" id="counterpartyDetailEditModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" data-i18n="counterparty_detail.edit_modal_title"><?= htmlspecialchars($t('counterparty_detail.edit_modal_title', 'Редактировать контрагента'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div><form id="counterpartyDetailEditForm" novalidate><div class="modal-body"><input type="hidden" name="public_id"><div class="row g-3">
  <div class="col-12 d-none" data-form-error-summary></div>
  <div class="col-md-4"><label class="form-label" data-i18n="counterparties.field_type"><?= htmlspecialchars($t('counterparties.field_type', 'Тип контрагента'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="counterparty_type" data-counterparty-type-input><option value="organization" data-i18n="counterparties.type_organization"><?= htmlspecialchars($t('counterparties.type_organization', 'Организация'), ENT_QUOTES, 'UTF-8') ?></option><option value="individual" data-i18n="counterparties.type_individual"><?= htmlspecialchars($t('counterparties.type_individual', 'Физлицо'), ENT_QUOTES, 'UTF-8') ?></option><option value="sole_proprietor" data-i18n="counterparties.type_sole_proprietor"><?= htmlspecialchars($t('counterparties.type_sole_proprietor', 'ИП'), ENT_QUOTES, 'UTF-8') ?></option><option value="legal_entity" data-i18n="counterparties.type_legal_entity"><?= htmlspecialchars($t('counterparties.type_legal_entity', 'Юрлицо'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
  <div class="col-md-8"><label class="form-label" data-i18n="counterparties.field_title"><?= htmlspecialchars($t('counterparties.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" maxlength="255" required></div>
  <div class="col-md-6" data-counterparty-type-group="sole_proprietor,legal_entity"><label class="form-label" data-i18n="counterparties.field_legal_name"><?= htmlspecialchars($t('counterparties.field_legal_name', 'Юридическое наименование / ФИО ИП'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="legal_name" maxlength="255"></div>
  <div class="col-md-4" data-counterparty-type-group="sole_proprietor,legal_entity"><label class="form-label" data-i18n="counterparties.field_inn"><?= htmlspecialchars($t('counterparties.field_inn', 'ИНН'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="tax_inn" maxlength="12"></div>
  <div class="col-md-4" data-counterparty-type-group="legal_entity"><label class="form-label" data-i18n="counterparties.field_kpp"><?= htmlspecialchars($t('counterparties.field_kpp', 'КПП'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="tax_kpp" maxlength="9"></div>
  <div class="col-md-4" data-counterparty-type-group="legal_entity"><label class="form-label" data-i18n="counterparties.field_ogrn"><?= htmlspecialchars($t('counterparties.field_ogrn', 'ОГРН'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="tax_ogrn" maxlength="13"></div>
  <div class="col-md-4" data-counterparty-type-group="sole_proprietor"><label class="form-label" data-i18n="counterparties.field_ogrnip"><?= htmlspecialchars($t('counterparties.field_ogrnip', 'ОГРНИП'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="tax_ogrnip" maxlength="15"></div>
  <div class="col-md-4"><label class="form-label" data-i18n="counterparties.field_email"><?= htmlspecialchars($t('counterparties.field_email', 'Email'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="email" name="email" maxlength="190"></div>
  <div class="col-md-4"><label class="form-label" data-i18n="counterparties.field_phone"><?= htmlspecialchars($t('counterparties.field_phone', 'Телефон'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="phone" maxlength="64"></div>
  <div class="col-md-4"><label class="form-label" data-i18n="counterparties.field_website"><?= htmlspecialchars($t('counterparties.field_website', 'Сайт'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="website" maxlength="2048"></div>
  <div class="col-md-4"><label class="form-label" data-i18n="counterparties.field_messenger"><?= htmlspecialchars($t('counterparties.field_messenger', 'Мессенджер'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="messenger" maxlength="190"></div>
  <div class="col-md-4" data-counterparty-type-group="sole_proprietor,legal_entity"><label class="form-label" data-i18n="counterparties.field_bank_account"><?= htmlspecialchars($t('counterparties.field_bank_account', 'Расчетный счет'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="bank_account" maxlength="34"></div>
  <div class="col-md-4" data-counterparty-type-group="sole_proprietor,legal_entity"><label class="form-label" data-i18n="counterparties.field_bik"><?= htmlspecialchars($t('counterparties.field_bik', 'БИК'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="bank_bik" maxlength="9"></div>
  <div class="col-md-4" data-counterparty-type-group="sole_proprietor,legal_entity"><label class="form-label" data-i18n="counterparties.field_corr_account"><?= htmlspecialchars($t('counterparties.field_corr_account', 'Корр. счет'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="bank_corr_account" maxlength="34"></div>
  <div class="col-md-8" data-counterparty-type-group="sole_proprietor,legal_entity"><label class="form-label" data-i18n="counterparties.field_bank_name"><?= htmlspecialchars($t('counterparties.field_bank_name', 'Банк'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="bank_name" maxlength="255"></div>
  <div class="col-md-4"><label class="form-label" data-i18n="counterparties.field_status"><?= htmlspecialchars($t('counterparties.field_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="status" maxlength="64"></div>
  <div class="col-md-6"><label class="form-label" data-i18n="counterparties.field_address_legal"><?= htmlspecialchars($t('counterparties.field_address_legal', 'Юридический адрес'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="address_legal" rows="2"></textarea></div>
  <div class="col-md-6"><label class="form-label" data-i18n="counterparties.field_address_postal"><?= htmlspecialchars($t('counterparties.field_address_postal', 'Почтовый адрес'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="address_postal" rows="2"></textarea></div>
  <div class="col-md-6"><label class="form-label" data-i18n="counterparties.field_address_actual"><?= htmlspecialchars($t('counterparties.field_address_actual', 'Фактический адрес'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="address_actual" rows="2"></textarea></div>
  <div class="col-12"><label class="form-label" data-i18n="counterparties.field_notes"><?= htmlspecialchars($t('counterparties.field_notes', 'Комментарий'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="notes" rows="2"></textarea></div>
</div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" type="submit" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button></div></form></div></div></div>

<div class="modal fade" id="counterpartyProfileEditModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" data-i18n="counterparty_detail.profile_edit_modal_title"><?= htmlspecialchars($t('counterparty_detail.profile_edit_modal_title', 'Редактировать профиль'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div><form id="counterpartyProfileEditForm" novalidate><div class="modal-body"><input type="hidden" name="public_id"><div class="row g-3">
  <div class="col-12 d-none" data-form-error-summary></div>
  <div class="col-md-4"><label class="form-label" data-i18n="counterparties.field_type"><?= htmlspecialchars($t('counterparties.field_type', 'Тип контрагента'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="counterparty_type" data-profile-type-input><option value="organization" data-i18n="counterparties.type_organization"><?= htmlspecialchars($t('counterparties.type_organization', 'Организация'), ENT_QUOTES, 'UTF-8') ?></option><option value="individual" data-i18n="counterparties.type_individual"><?= htmlspecialchars($t('counterparties.type_individual', 'Физлицо'), ENT_QUOTES, 'UTF-8') ?></option><option value="sole_proprietor" data-i18n="counterparties.type_sole_proprietor"><?= htmlspecialchars($t('counterparties.type_sole_proprietor', 'ИП'), ENT_QUOTES, 'UTF-8') ?></option><option value="legal_entity" data-i18n="counterparties.type_legal_entity"><?= htmlspecialchars($t('counterparties.type_legal_entity', 'Юрлицо'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
  <div class="col-md-8"><label class="form-label" data-i18n="counterparties.field_title"><?= htmlspecialchars($t('counterparties.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" maxlength="255" required></div>
  <div class="col-md-4"><label class="form-label" data-i18n="counterparties.field_email"><?= htmlspecialchars($t('counterparties.field_email', 'Email'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="email" name="email" maxlength="190"></div>
  <div class="col-md-4"><label class="form-label" data-i18n="counterparties.field_phone"><?= htmlspecialchars($t('counterparties.field_phone', 'Телефон'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="phone" maxlength="64"></div>
  <div class="col-md-4"><label class="form-label" data-i18n="counterparties.field_website"><?= htmlspecialchars($t('counterparties.field_website', 'Сайт'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="website" maxlength="2048"></div>
  <div class="col-md-4"><label class="form-label" data-i18n="counterparties.field_messenger"><?= htmlspecialchars($t('counterparties.field_messenger', 'Мессенджер'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="messenger" maxlength="190"></div>
  <div class="col-md-4"><label class="form-label" data-i18n="counterparties.field_status"><?= htmlspecialchars($t('counterparties.field_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="status"><option value="active" data-i18n="counterparties.status_active"><?= htmlspecialchars($t('counterparties.status_active', 'Активен'), ENT_QUOTES, 'UTF-8') ?></option><option value="inactive" data-i18n="counterparties.status_inactive"><?= htmlspecialchars($t('counterparties.status_inactive', 'Неактивен'), ENT_QUOTES, 'UTF-8') ?></option><option value="blocked" data-i18n="counterparties.status_blocked"><?= htmlspecialchars($t('counterparties.status_blocked', 'Заблокирован'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
  <div class="col-12"><label class="form-label" data-i18n="counterparties.field_notes"><?= htmlspecialchars($t('counterparties.field_notes', 'Комментарий'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="notes" rows="2"></textarea></div>
</div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" type="submit" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button></div></form></div></div></div>

<div class="modal fade" id="counterpartyContactModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="counterpartyContactModalTitle" data-i18n="counterparty_detail.contact_modal_title"><?= htmlspecialchars($t('counterparty_detail.contact_modal_title', 'Добавить контакт'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div><form id="counterpartyContactForm" novalidate><div class="modal-body"><input type="hidden" name="public_id"><input type="hidden" name="counterparty_public_id"><div class="row g-3">
  <div class="col-12 d-none" data-form-error-summary></div>
  <div class="col-12"><label class="form-label" data-i18n="counterparty_detail.contact_field_full_name"><?= htmlspecialchars($t('counterparty_detail.contact_field_full_name', 'ФИО'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="full_name" maxlength="255" required></div>
  <div class="col-md-6"><label class="form-label" data-i18n="counterparty_detail.contact_field_role"><?= htmlspecialchars($t('counterparty_detail.contact_field_role', 'Роль'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="role" id="contactRoleSelect"><option value="decision_maker" data-i18n="contacts.role_decision_maker"><?= htmlspecialchars($t('contacts.role_decision_maker', 'ЛПР'), ENT_QUOTES, 'UTF-8') ?></option><option value="influencer" data-i18n="contacts.role_influencer"><?= htmlspecialchars($t('contacts.role_influencer', 'Влияющий'), ENT_QUOTES, 'UTF-8') ?></option><option value="user" data-i18n="contacts.role_user"><?= htmlspecialchars($t('contacts.role_user', 'Пользователь'), ENT_QUOTES, 'UTF-8') ?></option><option value="technical" data-i18n="contacts.role_technical"><?= htmlspecialchars($t('contacts.role_technical', 'Технический'), ENT_QUOTES, 'UTF-8') ?></option><option value="contact" data-i18n="contacts.role_contact"><?= htmlspecialchars($t('contacts.role_contact', 'Контакт'), ENT_QUOTES, 'UTF-8') ?></option><option value="__custom" data-i18n="counterparty_detail.contact_role_custom"><?= htmlspecialchars($t('counterparty_detail.contact_role_custom', 'Своя роль...'), ENT_QUOTES, 'UTF-8') ?></option></select><input class="form-control mt-2 d-none" type="text" id="contactRoleCustomInput" name="role_custom" maxlength="64" placeholder="<?= htmlspecialchars($t('counterparty_detail.contact_role_custom_placeholder', 'Введите свою роль'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="counterparty_detail.contact_role_custom_placeholder"></div>
  <div class="col-md-6"><label class="form-label" data-i18n="counterparty_detail.contact_field_email"><?= htmlspecialchars($t('counterparty_detail.contact_field_email', 'Email'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="email" name="email" maxlength="190"></div>
  <div class="col-md-6"><label class="form-label" data-i18n="counterparty_detail.contact_field_phone"><?= htmlspecialchars($t('counterparty_detail.contact_field_phone', 'Телефон'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="phone" maxlength="64"></div>
</div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" type="submit" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button></div></form></div></div></div>

<div class="modal fade" id="counterpartyRequisitesEditModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" data-i18n="counterparty_detail.requisites_edit_modal_title"><?= htmlspecialchars($t('counterparty_detail.requisites_edit_modal_title', 'Редактировать реквизиты'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div><form id="counterpartyRequisitesEditForm" novalidate><div class="modal-body"><input type="hidden" name="public_id"><div class="row g-3">
  <div class="col-12 d-none" data-form-error-summary></div>
  <div class="col-md-4"><label class="form-label" data-i18n="counterparty_detail.requisites_field_legal_name"><?= htmlspecialchars($t('counterparty_detail.requisites_field_legal_name', 'Юридическое наименование'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="legal_name" maxlength="255"></div>
  <div class="col-md-4 position-relative" data-ru-field><label class="form-label" data-i18n="counterparty_detail.requisites_field_inn"><?= htmlspecialchars($t('counterparty_detail.requisites_field_inn', 'ИНН'), ENT_QUOTES, 'UTF-8') ?> <small class="text-muted" data-i18n="counterparty_detail.requisites_inn_hint"><?= htmlspecialchars($t('counterparty_detail.requisites_inn_hint', '(введите для поиска)'), ENT_QUOTES, 'UTF-8') ?></small></label><input class="form-control" name="tax_inn" maxlength="12" id="requisitesInnInput" autocomplete="off"><div id="dadataSuggestions" class="list-group position-absolute w-100" style="z-index:1050;display:none;"></div></div>
  <div class="col-md-4" data-ru-field><label class="form-label" data-i18n="counterparty_detail.requisites_field_kpp"><?= htmlspecialchars($t('counterparty_detail.requisites_field_kpp', 'КПП'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="tax_kpp" maxlength="9"></div>
  <div class="col-md-4" data-intl-field style="display:none"><label class="form-label" data-i18n="counterparty_detail.requisites_field_tax_id"><?= htmlspecialchars($t('counterparty_detail.requisites_field_tax_id', 'Tax ID / VAT Number'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="tax_inn" maxlength="32"></div>
  <div class="col-md-4" data-intl-field style="display:none"><label class="form-label" data-i18n="counterparty_detail.requisites_field_company_reg"><?= htmlspecialchars($t('counterparty_detail.requisites_field_company_reg', 'Company Registration No.'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="tax_kpp" maxlength="32"></div>
  <div class="col-md-4" data-ru-field data-req-type-group="organization,legal_entity"><label class="form-label" data-i18n="counterparty_detail.requisites_field_ogrn"><?= htmlspecialchars($t('counterparty_detail.requisites_field_ogrn', 'ОГРН'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="tax_ogrn" maxlength="13"></div>
  <div class="col-md-4" data-ru-field data-req-type-group="sole_proprietor"><label class="form-label" data-i18n="counterparty_detail.requisites_field_ogrnip"><?= htmlspecialchars($t('counterparty_detail.requisites_field_ogrnip', 'ОГРНИП'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="tax_ogrnip" maxlength="15"></div>
  <div class="col-md-4" data-intl-field style="display:none"><label class="form-label" data-i18n="counterparty_detail.requisites_field_reg_number"><?= htmlspecialchars($t('counterparty_detail.requisites_field_reg_number', 'Registration Number'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="tax_ogrn" maxlength="32"></div>
  <div class="col-md-4" data-intl-field style="display:none"><label class="form-label" data-i18n="counterparty_detail.requisites_field_tax_reg"><?= htmlspecialchars($t('counterparty_detail.requisites_field_tax_reg', 'Tax Registration No.'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="tax_ogrnip" maxlength="32"></div>
  <div class="col-md-4"><label class="form-label" data-i18n="counterparty_detail.requisites_field_bank_account"><?= htmlspecialchars($t('counterparty_detail.requisites_field_bank_account', 'Расчетный счет / Account No.'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="bank_account" maxlength="34"></div>
  <div class="col-md-4"><label class="form-label" data-i18n="counterparty_detail.requisites_field_bik_swift"><?= htmlspecialchars($t('counterparty_detail.requisites_field_bik_swift', 'БИК / SWIFT / BIC'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="bank_bik" maxlength="16"></div>
  <div class="col-md-4"><label class="form-label" data-i18n="counterparty_detail.requisites_field_corr_iban"><?= htmlspecialchars($t('counterparty_detail.requisites_field_corr_iban', 'Корр. счет / IBAN'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="bank_corr_account" maxlength="34"></div>
  <div class="col-md-8"><label class="form-label" data-i18n="counterparty_detail.requisites_field_bank_name"><?= htmlspecialchars($t('counterparty_detail.requisites_field_bank_name', 'Банк'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="bank_name" maxlength="255"></div>
  <div class="col-md-6"><label class="form-label" data-i18n="counterparty_detail.requisites_field_address_legal"><?= htmlspecialchars($t('counterparty_detail.requisites_field_address_legal', 'Юридический адрес'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="address_legal" rows="2"></textarea></div>
  <div class="col-md-6"><label class="form-label" data-i18n="counterparty_detail.requisites_field_address_postal"><?= htmlspecialchars($t('counterparty_detail.requisites_field_address_postal', 'Почтовый адрес'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="address_postal" rows="2"></textarea></div>
  <div class="col-md-6"><label class="form-label" data-i18n="counterparty_detail.requisites_field_address_actual"><?= htmlspecialchars($t('counterparty_detail.requisites_field_address_actual', 'Фактический адрес'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="address_actual" rows="2"></textarea></div>
</div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" type="submit" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button></div></form></div></div></div>

<div class="modal fade" id="counterpartyExtraEditModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" data-i18n="counterparty_detail.extra_edit_modal_title"><?= htmlspecialchars($t('counterparty_detail.extra_edit_modal_title', 'Редактировать дополнительные поля'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div><form id="counterpartyExtraEditForm" novalidate><div class="modal-body"><input type="hidden" name="public_id"><div class="row g-3">
  <div class="col-12 d-none" data-form-error-summary></div>
  <div id="counterpartyExtraFieldsContainer"><div class="text-muted" data-i18n="counterparty_detail.extra_fields_empty"><?= htmlspecialchars($t('counterparty_detail.extra_fields_empty', 'Нет дополнительных полей.'), ENT_QUOTES, 'UTF-8') ?></div></div>
  <div class="col-12 mt-3"><button type="button" class="btn btn-sm crm-btn-secondary" id="addExtraFieldBtn" data-i18n="counterparty_detail.btn_add_field"><?= htmlspecialchars($t('counterparty_detail.btn_add_field', 'Добавить поле'), ENT_QUOTES, 'UTF-8') ?></button></div>
</div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" type="submit" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button></div></form></div></div></div>
<div class="modal fade" id="contactPortalInviteModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" data-i18n="contacts.portal_modal_title"><?= htmlspecialchars($t('contacts.portal_modal_title', 'Доступ в клиентский портал'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div>
  <div class="modal-body">
    <div id="contactPortalInvitePending" data-portal-pending class="text-muted small"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
    <div id="contactPortalInviteError" data-portal-error class="alert alert-danger d-none py-2"></div>
    <div id="contactPortalInviteResult" data-portal-result class="d-none">
      <p class="small text-muted mb-2" data-i18n="contacts.portal_invite_hint"><?= htmlspecialchars($t('contacts.portal_invite_hint', 'Отправьте эту ссылку контакту. По ней он задаст пароль и войдёт в свой ограниченный кабинет.'), ENT_QUOTES, 'UTF-8') ?></p>
      <label class="form-label" data-i18n="contacts.portal_login_label"><?= htmlspecialchars($t('contacts.portal_login_label', 'Логин'), ENT_QUOTES, 'UTF-8') ?></label>
      <div class="input-group mb-3"><input type="text" class="form-control" id="contactPortalLoginInput" data-portal-login readonly><button class="btn crm-btn-secondary" type="button" data-copy-target="contactPortalLoginInput" data-i18n="admin_users.invite_copy"><?= htmlspecialchars($t('admin_users.invite_copy', 'Скопировать'), ENT_QUOTES, 'UTF-8') ?></button></div>
      <label class="form-label" data-i18n="contacts.portal_link_label"><?= htmlspecialchars($t('contacts.portal_link_label', 'Ссылка приглашения'), ENT_QUOTES, 'UTF-8') ?></label>
      <div class="input-group"><input type="text" class="form-control" id="contactPortalLinkInput" data-portal-link readonly><button class="btn crm-btn-secondary" type="button" data-copy-target="contactPortalLinkInput" data-i18n="admin_users.invite_copy"><?= htmlspecialchars($t('admin_users.invite_copy', 'Скопировать'), ENT_QUOTES, 'UTF-8') ?></button></div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.close"><?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?></button></div>
</div></div></div>

<div class="modal fade" id="counterpartyKnowledgeAttachModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" data-i18n="counterparty_detail.attach_knowledge_title"><?= htmlspecialchars($t('counterparty_detail.attach_knowledge_title', 'Прикрепить статью базы знаний'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label" for="counterpartyKnowledgeSearch" data-i18n="counterparty_detail.attach_knowledge_search_label"><?= htmlspecialchars($t('counterparty_detail.attach_knowledge_search_label', 'Поиск статьи'), ENT_QUOTES, 'UTF-8') ?></label><input id="counterpartyKnowledgeSearch" class="form-control" type="search" autocomplete="off" placeholder="<?= htmlspecialchars($t('counterparty_detail.attach_knowledge_search_placeholder', 'Введите название статьи...'), ENT_QUOTES, 'UTF-8') ?>"></div><div id="counterpartyKnowledgeAttachResults"><div class="text-muted small" data-i18n="counterparty_detail.attach_knowledge_loading"><?= htmlspecialchars($t('counterparty_detail.attach_knowledge_loading', 'Загрузка статей...'), ENT_QUOTES, 'UTF-8') ?></div></div></div><div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button></div></div></div></div>

<script>
(function () {
  var urlParams = new URLSearchParams(window.location.search);
  var counterpartyId = urlParams.get('counterparty_public_id') || urlParams.get('id');
  if (!counterpartyId) return;
  var createKnowledgeBtn = document.getElementById('counterpartyCreateKnowledgeBtn');
  if (createKnowledgeBtn) createKnowledgeBtn.href = 'index.php?route=knowledge&entity_type=counterparty&entity_public_id=' + encodeURIComponent(counterpartyId);
  function getApi() {
    return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
  }
  function waitForApi(cb, n) {
    if (getApi()) { cb(); return; }
    if ((n || 0) > 80) return;
    window.setTimeout(function () { waitForApi(cb, (n || 0) + 1); }, 50);
  }
  waitForApi(async function () {
    var api = getApi();
    var listEl = document.getElementById('counterpartyKnowledgeList');
    if (listEl) {
      try {
        var envelope = await api.request('api/v1/knowledge/entities/counterparty/' + encodeURIComponent(counterpartyId) + '/pages', { method: 'GET' });
        var items = envelope.data && envelope.data.items || [];
        if (!items.length) {
          listEl.innerHTML = '<div class="text-muted small"><?= htmlspecialchars($t('counterparty_detail.knowledge_empty', 'Нет связанных страниц'), ENT_QUOTES, 'UTF-8') ?></div>';
        } else {
          listEl.innerHTML = '<ul class="list-unstyled mb-0">' + items.map(function (p) {
            return '<li class="mb-1"><a href="index.php?route=knowledge-page&amp;id=' + encodeURIComponent(p.public_id) + '">' + (function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (ch) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch]; }); })(p.title || '') + '</a> <span class="text-muted small">(' + (function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (ch) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch]; }); })(p.relation_type || 'related') + ')</span></li>';
          }).join('') + '</ul>';
        }
      } catch (e) {
        listEl.innerHTML = '<div class="text-muted small">—</div>';
      }
    }

    var teamEl = document.getElementById('counterpartyTeamKnowledgeList');
    if (teamEl && window.CRM && window.CRM.teamMaterials) {
      window.CRM.teamMaterials.render({
        container: teamEl,
        entityType: 'counterparty',
        entityPublicId: counterpartyId,
        api: api,
        texts: {
          searchPlaceholder: <?= json_encode($t('page.team_materials_search', 'Поиск по материалам...'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
          empty: <?= json_encode($t('counterparty_detail.team_knowledge_empty', 'Нет материалов команды'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
          noMatch: <?= json_encode($t('page.team_materials_no_match', 'Ничего не найдено'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
          loading: <?= json_encode($t('page.loading', 'Загрузка...'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>
        }
      });
    }
  });
})();
</script>
<script>
(function () {
  var attachBtn = document.getElementById('counterpartyAttachKnowledgeBtn');
  var modalEl = document.getElementById('counterpartyKnowledgeAttachModal');
  var searchInput = document.getElementById('counterpartyKnowledgeSearch');
  var resultsEl = document.getElementById('counterpartyKnowledgeAttachResults');
  if (!attachBtn || !modalEl || !searchInput || !resultsEl) return;

  var urlParams = new URLSearchParams(window.location.search);
  var counterpartyId = urlParams.get('counterparty_public_id') || urlParams.get('id');
  if (!counterpartyId) {
    attachBtn.disabled = true;
    return;
  }

  var api = null;
  var searchTimer = null;
  var loading = false;
  var linkedIds = {};

  function canAttachKnowledge() {
    return !api || typeof api.hasPermission !== 'function' || api.hasPermission('knowledge.edit');
  }

  function getApi() {
    return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
    });
  }

  function text(key, fallback) {
    return window.CRM && window.CRM.i18n && typeof window.CRM.i18n.t === 'function'
      ? window.CRM.i18n.t(key, fallback)
      : fallback;
  }

  function setResultsMessage(message, className) {
    resultsEl.innerHTML = '<div class="text-muted small ' + (className || '') + '">' + escapeHtml(message) + '</div>';
  }

  async function loadLinkedIds() {
    linkedIds = {};
    var envelope = await api.request('api/v1/knowledge/entities/counterparty/' + encodeURIComponent(counterpartyId) + '/pages', { method: 'GET' });
    var items = envelope && envelope.data && Array.isArray(envelope.data.items) ? envelope.data.items : [];
    items.forEach(function (item) {
      var id = String(item && item.public_id || '').trim();
      if (id) linkedIds[id] = true;
    });
  }

  async function loadArticles() {
    if (loading) return;
    loading = true;
    setResultsMessage(text('counterparty_detail.attach_knowledge_loading', 'Загрузка статей...'));
    try {
      var query = { limit: 50, q: String(searchInput.value || '').trim() };
      var envelope = await api.request('api/v1/knowledge/pages', { method: 'GET', query: query });
      var items = envelope && envelope.data && Array.isArray(envelope.data.items) ? envelope.data.items : [];
      var available = items.filter(function (item) {
        return item && item.public_id && !linkedIds[String(item.public_id)];
      });
      if (!available.length) {
        setResultsMessage(text('counterparty_detail.attach_knowledge_empty', 'Статьи не найдены'));
        return;
      }
      resultsEl.innerHTML = available.map(function (item) {
        var id = encodeURIComponent(String(item.public_id));
        var title = escapeHtml(item.title || text('knowledge.untitled', 'Без названия'));
        var meta = [item.space_title, item.status].filter(Boolean).map(escapeHtml).join(' · ');
        return '<button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-3" data-knowledge-page-id="' + id + '"><span class="text-start"><strong class="d-block">' + title + '</strong>' + (meta ? '<span class="small text-muted">' + meta + '</span>' : '') + '</span><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-link" aria-hidden="true"></i></span></button>';
      }).join('');
    } catch (e) {
      setResultsMessage(text('counterparty_detail.attach_knowledge_error', 'Не удалось загрузить статьи'), 'text-danger');
    } finally {
      loading = false;
    }
  }

  attachBtn.addEventListener('click', async function () {
    api = getApi();
    if (!api || !canAttachKnowledge()) return;
    try {
      await loadLinkedIds();
    } catch (e) {
      setResultsMessage(text('counterparty_detail.attach_knowledge_error', 'Не удалось загрузить связанные статьи'), 'text-danger');
      return;
    }
    searchInput.value = '';
    resultsEl.innerHTML = '';
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    loadArticles();
  });

  function refreshAttachPermission() {
    api = getApi();
    if (!api) return false;
    if (typeof api.hasPermission === 'function' && !api.hasPermission('knowledge.edit')) {
      attachBtn.classList.add('d-none');
    }
    return true;
  }

  function waitForAttachPermission(attempt) {
    if (refreshAttachPermission() || (attempt || 0) >= 80) return;
    window.setTimeout(function () { waitForAttachPermission((attempt || 0) + 1); }, 50);
  }

  waitForAttachPermission(0);

  searchInput.addEventListener('input', function () {
    if (!api) api = getApi();
    if (!api) return;
    if (searchTimer) window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(loadArticles, 250);
  });

  resultsEl.addEventListener('click', async function (event) {
    var button = event.target.closest('[data-knowledge-page-id]');
    if (!button || !api) return;
    button.disabled = true;
    try {
      var pageId = decodeURIComponent(button.getAttribute('data-knowledge-page-id') || '');
      await api.request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/links', {
        method: 'POST',
        body: { entity_type: 'counterparty', entity_public_id: counterpartyId, relation_type: 'related' },
        idempotent: true
      });
      bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      window.location.reload();
    } catch (e) {
      button.disabled = false;
      setResultsMessage(text('counterparty_detail.attach_knowledge_error', 'Не удалось прикрепить статью'), 'text-danger');
    }
  });
})();
</script>
</body>
