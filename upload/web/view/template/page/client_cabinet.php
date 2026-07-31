<?php declare(strict_types=1); ?>
<?php $title = $t('client_cabinet.title', 'TropaTT — Кабинет клиента'); ?>
<body data-page="clients" data-protected="1">
<div class="crm-app">
  <aside class="crm-sidebar">
    <div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div>
    <nav class="nav flex-column crm-nav"></nav>
  </aside>
  <div class="crm-main-wrap">
    <header class="crm-topbar py-2">
      <div class="container-fluid"></div>
    </header>
    <main class="crm-content crm-client-cabinet-page">
      <div class="crm-page-head">
        <div>
          <h1 class="crm-page-title" data-i18n="client_cabinet.page_title"><?= htmlspecialchars($t('client_cabinet.page_title', 'Клиенты'), ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="crm-subtitle" data-i18n="client_cabinet.page_subtitle"><?= htmlspecialchars($t('client_cabinet.page_subtitle', 'Клиентский список, активные задачи и лента выбранного клиента.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="crm-page-actions"><button class="btn crm-btn-secondary" type="button" data-open-modal="calendarEventModal" data-i18n="client_cabinet.btn_create_event"><?= htmlspecialchars($t('client_cabinet.btn_create_event', 'Создать событие'), ENT_QUOTES, 'UTF-8') ?></button></div>
      </div>

      <div class="row g-3 mb-3 crm-kpi-row">
        <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted" data-i18n="client_cabinet.kpi_total"><?= htmlspecialchars($t('client_cabinet.kpi_total', 'Всего клиентов'), ENT_QUOTES, 'UTF-8') ?></small><h2 id="clientCabinetKpiTotal" class="h4">—</h2><span class="crm-badge archived" data-i18n="client_cabinet.kpi_list"><?= htmlspecialchars($t('client_cabinet.kpi_list', 'Список'), ENT_QUOTES, 'UTF-8') ?></span></div></div>
        <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted" data-i18n="client_cabinet.kpi_email"><?= htmlspecialchars($t('client_cabinet.kpi_email', 'С email'), ENT_QUOTES, 'UTF-8') ?></small><h2 id="clientCabinetKpiEmail" class="h4">—</h2><span class="crm-badge active" data-i18n="client_cabinet.kpi_contacts"><?= htmlspecialchars($t('client_cabinet.kpi_contacts', 'Контакты'), ENT_QUOTES, 'UTF-8') ?></span></div></div>
        <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted" data-i18n="client_cabinet.kpi_phone"><?= htmlspecialchars($t('client_cabinet.kpi_phone', 'С телефоном'), ENT_QUOTES, 'UTF-8') ?></small><h2 id="clientCabinetKpiPhone" class="h4">—</h2><span class="crm-badge success" data-i18n="client_cabinet.kpi_connection"><?= htmlspecialchars($t('client_cabinet.kpi_connection', 'Связь'), ENT_QUOTES, 'UTF-8') ?></span></div></div>
        <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted" data-i18n="client_cabinet.kpi_tasks"><?= htmlspecialchars($t('client_cabinet.kpi_tasks', 'Задач по выбранному'), ENT_QUOTES, 'UTF-8') ?></small><h2 id="clientCabinetKpiTasks" class="h4">—</h2><span class="crm-badge blocked" data-i18n="client_cabinet.kpi_focus"><?= htmlspecialchars($t('client_cabinet.kpi_focus', 'Фокус'), ENT_QUOTES, 'UTF-8') ?></span></div></div>
      </div>

      <div class="crm-client-shell">
        <div class="crm-client-main">
          <div class="crm-card crm-section-card">
            <div class="crm-section-head">
              <div>
                <h2 class="h6 mb-0" data-i18n="client_cabinet.section_client_list"><?= htmlspecialchars($t('client_cabinet.section_client_list', 'Клиентский список'), ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="crm-section-note" data-i18n="client_cabinet.section_client_list_note"><?= htmlspecialchars($t('client_cabinet.section_client_list_note', 'Добавляйте клиентов, выбирайте нужного и переходите в его карточку.'), ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>
            <div class="row g-2 mb-3 crm-client-filters">
              <div class="col-md-5">
                <label class="form-label" for="clientCabinetFilterSearch" data-i18n="client_cabinet.filter_search_label"><?= htmlspecialchars($t('client_cabinet.filter_search_label', 'Поиск'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" id="clientCabinetFilterSearch" placeholder="<?= htmlspecialchars($t('client_cabinet.filter_search_placeholder', 'Название, email, телефон, ИНН'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="client_cabinet.filter_search_placeholder">
              </div>
              <div class="col-md-3">
                <label class="form-label" for="clientCabinetFilterType" data-i18n="client_cabinet.filter_type_label"><?= htmlspecialchars($t('client_cabinet.filter_type_label', 'Тип'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select" id="clientCabinetFilterType">
                  <option value="" data-i18n="page.all_types"><?= htmlspecialchars($t('page.all_types', 'Все типы'), ENT_QUOTES, 'UTF-8') ?></option>
                  <option value="individual" data-i18n="clients.type_individual"><?= htmlspecialchars($t('clients.type_individual', 'Физлицо'), ENT_QUOTES, 'UTF-8') ?></option>
                  <option value="sole_proprietor" data-i18n="clients.type_sole_proprietor"><?= htmlspecialchars($t('clients.type_sole_proprietor', 'ИП'), ENT_QUOTES, 'UTF-8') ?></option>
                  <option value="legal_entity" data-i18n="clients.type_legal_entity"><?= htmlspecialchars($t('clients.type_legal_entity', 'Юрлицо'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label" for="clientCabinetFilterStatus" data-i18n="client_cabinet.filter_status_label"><?= htmlspecialchars($t('client_cabinet.filter_status_label', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select" id="clientCabinetFilterStatus">
                  <option value="" data-i18n="page.all_statuses"><?= htmlspecialchars($t('page.all_statuses', 'Все статусы'), ENT_QUOTES, 'UTF-8') ?></option>
                  <option value="active" data-i18n="clients.status_active"><?= htmlspecialchars($t('clients.status_active', 'Активен'), ENT_QUOTES, 'UTF-8') ?></option>
                  <option value="inactive" data-i18n="clients.status_inactive"><?= htmlspecialchars($t('clients.status_inactive', 'Неактивен'), ENT_QUOTES, 'UTF-8') ?></option>
                  <option value="archived" data-i18n="clients.status_archived"><?= htmlspecialchars($t('clients.status_archived', 'Архив'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
              </div>
              <div class="col-md-2 d-flex align-items-end">
                <button class="btn crm-btn-muted w-100" type="button" id="clientCabinetFilterReset" data-i18n="page.reset"><?= htmlspecialchars($t('page.reset', 'Сбросить'), ENT_QUOTES, 'UTF-8') ?></button>
              </div>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="clientCabinetCompactMode">
                  <label class="form-check-label" for="clientCabinetCompactMode" data-i18n="client_cabinet.label_compact_mode"><?= htmlspecialchars($t('client_cabinet.label_compact_mode', 'Компактный режим таблицы'), ENT_QUOTES, 'UTF-8') ?></label>
                </div>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table crm-table mb-0">
                <thead>
                  <tr>
                    <th data-i18n="client_cabinet.th_client"><?= htmlspecialchars($t('client_cabinet.th_client', 'Клиент'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th data-i18n="client_cabinet.th_type"><?= htmlspecialchars($t('client_cabinet.th_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th data-i18n="client_cabinet.th_email"><?= htmlspecialchars($t('client_cabinet.th_email', 'Email'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th data-i18n="client_cabinet.th_phone"><?= htmlspecialchars($t('client_cabinet.th_phone', 'Телефон'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th data-i18n="client_cabinet.th_requisites"><?= htmlspecialchars($t('client_cabinet.th_requisites', 'Реквизиты'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th data-i18n="client_cabinet.th_tasks"><?= htmlspecialchars($t('client_cabinet.th_tasks', 'Задачи'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th style="width:320px" data-i18n="client_cabinet.th_actions"><?= htmlspecialchars($t('client_cabinet.th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?></th>
                  </tr>
                </thead>
                <tbody id="clientCabinetClientsBody"><tr><td colspan="7" class="text-muted" data-i18n="client_cabinet.loading"><?= htmlspecialchars($t('client_cabinet.loading', 'Загрузка клиентов...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
              </table>
            </div>
          </div>

          <div class="crm-card crm-section-card">
            <div class="crm-section-head">
              <div>
                <h2 class="h6 mb-0" data-i18n="client_cabinet.section_active_tasks"><?= htmlspecialchars($t('client_cabinet.section_active_tasks', 'Активные задачи клиента'), ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="crm-section-note" data-i18n="client_cabinet.section_active_tasks_note"><?= htmlspecialchars($t('client_cabinet.section_active_tasks_note', 'Текущий перечень задач выбранного клиента с акцентом на статус и ближайшие изменения.'), ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>
            <ul class="mb-0" id="clientCabinetTasksList"><li class="text-muted" data-i18n="client_cabinet.select_client_hint"><?= htmlspecialchars($t('client_cabinet.select_client_hint', 'Выберите клиента.'), ENT_QUOTES, 'UTF-8') ?></li></ul>
          </div>

          <div class="crm-card crm-section-card">
            <div class="crm-section-head">
              <div>
                <h2 class="h6 mb-0" data-i18n="client_cabinet.section_activity"><?= htmlspecialchars($t('client_cabinet.section_activity', 'Активность клиента'), ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="crm-section-note" data-i18n="client_cabinet.section_activity_note"><?= htmlspecialchars($t('client_cabinet.section_activity_note', 'Ключевые события и обновления по задачам и этапам проекта выбранного клиента.'), ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>
            <div class="crm-timeline" id="clientCabinetTimeline"><div class="crm-timeline-item" data-i18n="client_cabinet.select_client_hint"><?= htmlspecialchars($t('client_cabinet.select_client_hint', 'Выберите клиента.'), ENT_QUOTES, 'UTF-8') ?></div></div>
          </div>
        </div>

        <aside class="crm-client-side">
          <div class="crm-card crm-section-card">
            <div class="crm-section-head">
              <div>
                <h2 class="h6 mb-0" data-i18n="client_cabinet.section_create_client"><?= htmlspecialchars($t('client_cabinet.section_create_client', 'Создать клиента'), ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="crm-section-note" data-i18n="client_cabinet.section_create_client_note"><?= htmlspecialchars($t('client_cabinet.section_create_client_note', 'Создайте нового клиента прямо из этого экрана.'), ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>
            <form id="clientCabinetCreateForm" class="row g-2">
              <div class="col-12 d-none" data-form-error-summary></div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_type"><?= htmlspecialchars($t('clients.field_type', 'Тип клиента'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select" name="client_type" data-client-type-input>
                  <option value="individual" data-i18n="clients.type_individual"><?= htmlspecialchars($t('clients.type_individual', 'Физлицо'), ENT_QUOTES, 'UTF-8') ?></option>
                  <option value="sole_proprietor" data-i18n="clients.type_sole_proprietor"><?= htmlspecialchars($t('clients.type_sole_proprietor', 'ИП'), ENT_QUOTES, 'UTF-8') ?></option>
                  <option value="legal_entity" data-i18n="clients.type_legal_entity"><?= htmlspecialchars($t('clients.type_legal_entity', 'Юрлицо'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_title"><?= htmlspecialchars($t('clients.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="title" maxlength="255" placeholder="<?= htmlspecialchars($t('client_cabinet.placeholder_title', 'Например: Верона Трэвел'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="client_cabinet.placeholder_title">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label" data-i18n="clients.field_legal_name"><?= htmlspecialchars($t('clients.field_legal_name', 'Юридическое наименование / ФИО ИП'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="legal_name" maxlength="255">
              </div>
              <div class="col-12" data-client-type-group="individual">
                <label class="form-label" data-i18n="client_cabinet.field_last_name"><?= htmlspecialchars($t('client_cabinet.field_last_name', 'Фамилия'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="person_last_name" maxlength="120">
              </div>
              <div class="col-12" data-client-type-group="individual">
                <label class="form-label" data-i18n="client_cabinet.field_first_name"><?= htmlspecialchars($t('client_cabinet.field_first_name', 'Имя'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="person_first_name" maxlength="120">
              </div>
              <div class="col-12" data-client-type-group="individual">
                <label class="form-label" data-i18n="client_cabinet.field_middle_name"><?= htmlspecialchars($t('client_cabinet.field_middle_name', 'Отчество'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="person_middle_name" maxlength="120">
              </div>
              <div class="col-12" data-client-type-group="individual">
                <label class="form-label" data-i18n="client_cabinet.field_birth_date"><?= htmlspecialchars($t('client_cabinet.field_birth_date', 'Дата рождения'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" type="date" name="person_birth_date">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label" data-i18n="clients.field_inn"><?= htmlspecialchars($t('clients.field_inn', 'ИНН'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="tax_inn" maxlength="12">
              </div>
              <div class="col-12" data-client-type-group="legal_entity">
                <label class="form-label" data-i18n="clients.field_kpp"><?= htmlspecialchars($t('clients.field_kpp', 'КПП'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="tax_kpp" maxlength="9">
              </div>
              <div class="col-12" data-client-type-group="legal_entity">
                <label class="form-label" data-i18n="clients.field_ogrn"><?= htmlspecialchars($t('clients.field_ogrn', 'ОГРН'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="tax_ogrn" maxlength="13">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor">
                <label class="form-label" data-i18n="clients.field_ogrnip"><?= htmlspecialchars($t('clients.field_ogrnip', 'ОГРНИП'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="tax_ogrnip" maxlength="15">
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_email"><?= htmlspecialchars($t('clients.field_email', 'Email'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" type="email" name="email" maxlength="190" placeholder="<?= htmlspecialchars($t('client_cabinet.placeholder_email', 'contact@client.com'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="client_cabinet.placeholder_email">
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_phone"><?= htmlspecialchars($t('clients.field_phone', 'Телефон'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="phone" maxlength="64" placeholder="<?= htmlspecialchars($t('client_cabinet.placeholder_phone', '+7 ...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="client_cabinet.placeholder_phone">
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_website"><?= htmlspecialchars($t('clients.field_website', 'Сайт'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="website" maxlength="2048" placeholder="<?= htmlspecialchars($t('client_cabinet.placeholder_website', 'https://client.com'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="client_cabinet.placeholder_website">
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_messenger"><?= htmlspecialchars($t('clients.field_messenger', 'Мессенджер'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="messenger" maxlength="190" placeholder="<?= htmlspecialchars($t('client_cabinet.placeholder_messenger', '@username'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="client_cabinet.placeholder_messenger">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label" data-i18n="clients.field_bank_account"><?= htmlspecialchars($t('clients.field_bank_account', 'Расчетный счет'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="bank_account" maxlength="34">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label" data-i18n="clients.field_bik"><?= htmlspecialchars($t('clients.field_bik', 'БИК'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="bank_bik" maxlength="9">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label" data-i18n="clients.field_corr_account"><?= htmlspecialchars($t('clients.field_corr_account', 'Корр. счет'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="bank_corr_account" maxlength="34">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label" data-i18n="clients.field_bank_name"><?= htmlspecialchars($t('clients.field_bank_name', 'Банк'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="bank_name" maxlength="255">
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_address_legal"><?= htmlspecialchars($t('clients.field_address_legal', 'Юридический адрес'), ENT_QUOTES, 'UTF-8') ?></label>
                <textarea class="form-control" name="address_legal" rows="2"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_address_postal"><?= htmlspecialchars($t('clients.field_address_postal', 'Почтовый адрес'), ENT_QUOTES, 'UTF-8') ?></label>
                <textarea class="form-control" name="address_postal" rows="2"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_status"><?= htmlspecialchars($t('clients.field_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="status" maxlength="64" value="active">
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_notes"><?= htmlspecialchars($t('clients.field_notes', 'Комментарий'), ENT_QUOTES, 'UTF-8') ?></label>
                <textarea class="form-control" name="notes" rows="2"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_extra_attributes"><?= htmlspecialchars($t('clients.field_extra_attributes', 'Дополнительные поля (JSON)'), ENT_QUOTES, 'UTF-8') ?></label>
                <textarea class="form-control" name="extra_attributes_text" rows="3" placeholder="{&quot;source&quot;:&quot;manual&quot;}"></textarea>
              </div>
              <div class="col-12">
                <button class="btn crm-btn-primary w-100" type="submit" data-i18n="client_cabinet.btn_create_client"><?= htmlspecialchars($t('client_cabinet.btn_create_client', 'Создать клиента'), ENT_QUOTES, 'UTF-8') ?></button>
              </div>
            </form>
          </div>

          <div class="crm-card crm-section-card">
            <div class="crm-section-head">
              <div>
                <h2 class="h6 mb-0" data-i18n="client_cabinet.section_edit_client"><?= htmlspecialchars($t('client_cabinet.section_edit_client', 'Редактировать клиента'), ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="crm-section-note" id="clientCabinetEditHint" data-i18n="client_cabinet.section_edit_client_note"><?= htmlspecialchars($t('client_cabinet.section_edit_client_note', 'Выберите клиента в таблице слева.'), ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>
            <form id="clientCabinetEditForm" class="row g-2">
              <input type="hidden" name="public_id">
              <div class="col-12 d-none" data-form-error-summary></div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_type"><?= htmlspecialchars($t('clients.field_type', 'Тип клиента'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select" name="client_type" data-client-type-input>
                  <option value="individual" data-i18n="clients.type_individual"><?= htmlspecialchars($t('clients.type_individual', 'Физлицо'), ENT_QUOTES, 'UTF-8') ?></option>
                  <option value="sole_proprietor" data-i18n="clients.type_sole_proprietor"><?= htmlspecialchars($t('clients.type_sole_proprietor', 'ИП'), ENT_QUOTES, 'UTF-8') ?></option>
                  <option value="legal_entity" data-i18n="clients.type_legal_entity"><?= htmlspecialchars($t('clients.type_legal_entity', 'Юрлицо'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_title"><?= htmlspecialchars($t('clients.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="title" maxlength="255">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label" data-i18n="clients.field_legal_name"><?= htmlspecialchars($t('clients.field_legal_name', 'Юридическое наименование / ФИО ИП'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="legal_name" maxlength="255">
              </div>
              <div class="col-12" data-client-type-group="individual">
                <label class="form-label" data-i18n="client_cabinet.field_last_name"><?= htmlspecialchars($t('client_cabinet.field_last_name', 'Фамилия'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="person_last_name" maxlength="120">
              </div>
              <div class="col-12" data-client-type-group="individual">
                <label class="form-label" data-i18n="client_cabinet.field_first_name"><?= htmlspecialchars($t('client_cabinet.field_first_name', 'Имя'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="person_first_name" maxlength="120">
              </div>
              <div class="col-12" data-client-type-group="individual">
                <label class="form-label" data-i18n="client_cabinet.field_middle_name"><?= htmlspecialchars($t('client_cabinet.field_middle_name', 'Отчество'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="person_middle_name" maxlength="120">
              </div>
              <div class="col-12" data-client-type-group="individual">
                <label class="form-label" data-i18n="client_cabinet.field_birth_date"><?= htmlspecialchars($t('client_cabinet.field_birth_date', 'Дата рождения'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" type="date" name="person_birth_date">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label" data-i18n="clients.field_inn"><?= htmlspecialchars($t('clients.field_inn', 'ИНН'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="tax_inn" maxlength="12">
              </div>
              <div class="col-12" data-client-type-group="legal_entity">
                <label class="form-label" data-i18n="clients.field_kpp"><?= htmlspecialchars($t('clients.field_kpp', 'КПП'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="tax_kpp" maxlength="9">
              </div>
              <div class="col-12" data-client-type-group="legal_entity">
                <label class="form-label" data-i18n="clients.field_ogrn"><?= htmlspecialchars($t('clients.field_ogrn', 'ОГРН'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="tax_ogrn" maxlength="13">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor">
                <label class="form-label" data-i18n="clients.field_ogrnip"><?= htmlspecialchars($t('clients.field_ogrnip', 'ОГРНИП'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="tax_ogrnip" maxlength="15">
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_email"><?= htmlspecialchars($t('clients.field_email', 'Email'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" type="email" name="email" maxlength="190">
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_phone"><?= htmlspecialchars($t('clients.field_phone', 'Телефон'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="phone" maxlength="64">
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_website"><?= htmlspecialchars($t('clients.field_website', 'Сайт'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="website" maxlength="2048">
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_messenger"><?= htmlspecialchars($t('clients.field_messenger', 'Мессенджер'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="messenger" maxlength="190">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label" data-i18n="clients.field_bank_account"><?= htmlspecialchars($t('clients.field_bank_account', 'Расчетный счет'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="bank_account" maxlength="34">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label" data-i18n="clients.field_bik"><?= htmlspecialchars($t('clients.field_bik', 'БИК'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="bank_bik" maxlength="9">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label" data-i18n="clients.field_corr_account"><?= htmlspecialchars($t('clients.field_corr_account', 'Корр. счет'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="bank_corr_account" maxlength="34">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label" data-i18n="clients.field_bank_name"><?= htmlspecialchars($t('clients.field_bank_name', 'Банк'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="bank_name" maxlength="255">
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_address_legal"><?= htmlspecialchars($t('clients.field_address_legal', 'Юридический адрес'), ENT_QUOTES, 'UTF-8') ?></label>
                <textarea class="form-control" name="address_legal" rows="2"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_address_postal"><?= htmlspecialchars($t('clients.field_address_postal', 'Почтовый адрес'), ENT_QUOTES, 'UTF-8') ?></label>
                <textarea class="form-control" name="address_postal" rows="2"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_status"><?= htmlspecialchars($t('clients.field_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" name="status" maxlength="64">
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_notes"><?= htmlspecialchars($t('clients.field_notes', 'Комментарий'), ENT_QUOTES, 'UTF-8') ?></label>
                <textarea class="form-control" name="notes" rows="2"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label" data-i18n="clients.field_extra_attributes"><?= htmlspecialchars($t('clients.field_extra_attributes', 'Дополнительные поля (JSON)'), ENT_QUOTES, 'UTF-8') ?></label>
                <textarea class="form-control" name="extra_attributes_text" rows="3"></textarea>
              </div>
              <div class="col-12 d-flex gap-2">
                <button class="btn crm-btn-primary flex-grow-1" type="submit" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
                <button class="btn crm-btn-danger-soft" id="clientCabinetDeleteBtn" type="button" data-i18n="page.delete"><?= htmlspecialchars($t('page.delete', 'Удалить'), ENT_QUOTES, 'UTF-8') ?></button>
              </div>
            </form>
          </div>

          <div class="crm-card crm-section-card">
            <div class="crm-section-head">
              <div>
                <h2 class="h6 mb-0" data-i18n="client_cabinet.section_selected_client"><?= htmlspecialchars($t('client_cabinet.section_selected_client', 'Выбранный клиент'), ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="crm-section-note" data-i18n="client_cabinet.section_selected_client_note"><?= htmlspecialchars($t('client_cabinet.section_selected_client_note', 'Краткая карточка выбранного клиента.'), ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>
            <div id="clientCabinetSelectedClient"><div class="text-muted" data-i18n="client_cabinet.no_client_selected"><?= htmlspecialchars($t('client_cabinet.no_client_selected', 'Клиент не выбран.'), ENT_QUOTES, 'UTF-8') ?></div></div>
          </div>

          <div class="crm-card crm-section-card crm-progress-card">
            <div class="crm-section-head">
              <div>
                <h2 class="h6 mb-0" data-i18n="client_cabinet.section_progress"><?= htmlspecialchars($t('client_cabinet.section_progress', 'Сводка прогресса'), ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="crm-section-note" data-i18n="client_cabinet.section_progress_note"><?= htmlspecialchars($t('client_cabinet.section_progress_note', 'Быстрый визуальный обзор общего состояния работ.'), ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>
            <div class="progress mb-2"><div class="progress-bar" style="width:0%">0%</div></div>
            <div class="text-muted small" id="clientCabinetProgressText" data-i18n="client_cabinet.progress_hint"><?= htmlspecialchars($t('client_cabinet.progress_hint', 'Выберите клиента для расчета прогресса.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>

          <div class="crm-card crm-section-card crm-comments-card">
            <div class="crm-section-head">
              <div>
                <h2 class="h6 mb-0" data-i18n="client_cabinet.section_comments"><?= htmlspecialchars($t('client_cabinet.section_comments', 'Комментарии'), ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="crm-section-note" data-i18n="client_cabinet.section_comments_note"><?= htmlspecialchars($t('client_cabinet.section_comments_note', 'Последние сообщения и важные договоренности по проекту.'), ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>
            <div id="clientCabinetComments"><div class="crm-comment text-muted" data-i18n="client_cabinet.select_client_hint"><?= htmlspecialchars($t('client_cabinet.select_client_hint', 'Выберите клиента.'), ENT_QUOTES, 'UTF-8') ?></div></div>
          </div>
        </aside>
      </div>
    </main>
  </div>
</div>
</body>
