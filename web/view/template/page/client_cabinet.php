<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Кабинет клиента'; ?>
<body data-page="clients" data-protected="1">
<div class="crm-app">
  <aside class="crm-sidebar">
    <div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div>
    <nav class="nav flex-column crm-nav"></nav>
  </aside>
  <div class="crm-main-wrap">
    <header class="crm-topbar py-2">
      <div class="container-fluid"></div>
    </header>
    <main class="crm-content crm-client-cabinet-page">
      <div class="crm-page-head">
        <div>
          <h1 class="crm-page-title">Клиенты</h1>
          <p class="crm-subtitle">Клиентский список, активные задачи и лента выбранного клиента.</p>
        </div>
        <div class="crm-page-actions"><button class="btn crm-btn-secondary" type="button" data-open-modal="calendarEventModal">Создать событие</button></div>
      </div>

      <div class="row g-3 mb-3 crm-kpi-row">
        <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted">Всего клиентов</small><h2 id="clientCabinetKpiTotal" class="h4">—</h2><span class="crm-badge archived">Список</span></div></div>
        <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted">С email</small><h2 id="clientCabinetKpiEmail" class="h4">—</h2><span class="crm-badge active">Контакты</span></div></div>
        <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted">С телефоном</small><h2 id="clientCabinetKpiPhone" class="h4">—</h2><span class="crm-badge success">Связь</span></div></div>
        <div class="col-md-3"><div class="crm-card crm-kpi-card"><small class="text-muted">Задач по выбранному</small><h2 id="clientCabinetKpiTasks" class="h4">—</h2><span class="crm-badge blocked">Фокус</span></div></div>
      </div>

      <div class="crm-client-shell">
        <div class="crm-client-main">
          <div class="crm-card crm-section-card">
            <div class="crm-section-head">
              <div>
                <h2 class="h6 mb-0">Клиентский список</h2>
                <div class="crm-section-note">Добавляйте клиентов, выбирайте нужного и переходите в его карточку.</div>
              </div>
            </div>
            <div class="row g-2 mb-3 crm-client-filters">
              <div class="col-md-5">
                <label class="form-label">Поиск</label>
                <input class="form-control" id="clientCabinetFilterSearch" placeholder="Название, email, телефон, ИНН">
              </div>
              <div class="col-md-3">
                <label class="form-label">Тип</label>
                <select class="form-select" id="clientCabinetFilterType">
                  <option value="">Все типы</option>
                  <option value="individual">Физлицо</option>
                  <option value="sole_proprietor">ИП</option>
                  <option value="legal_entity">Юрлицо</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label">Статус</label>
                <input class="form-control" id="clientCabinetFilterStatus" placeholder="active">
              </div>
              <div class="col-md-2 d-flex align-items-end">
                <button class="btn crm-btn-muted w-100" type="button" id="clientCabinetFilterReset">Сбросить</button>
              </div>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="clientCabinetCompactMode">
                  <label class="form-check-label" for="clientCabinetCompactMode">Компактный режим таблицы</label>
                </div>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table crm-table mb-0">
                <thead>
                  <tr>
                    <th>Клиент</th>
                    <th>Тип</th>
                    <th>Email</th>
                    <th>Телефон</th>
                    <th>Реквизиты</th>
                    <th>Задачи</th>
                    <th style="width:320px">Действия</th>
                  </tr>
                </thead>
                <tbody id="clientCabinetClientsBody"><tr><td colspan="7" class="text-muted">Загрузка клиентов...</td></tr></tbody>
              </table>
            </div>
          </div>

          <div class="crm-card crm-section-card">
            <div class="crm-section-head">
              <div>
                <h2 class="h6 mb-0">Активные задачи клиента</h2>
                <div class="crm-section-note">Текущий перечень задач выбранного клиента с акцентом на статус и ближайшие изменения.</div>
              </div>
            </div>
            <ul class="mb-0" id="clientCabinetTasksList"><li class="text-muted">Выберите клиента.</li></ul>
          </div>

          <div class="crm-card crm-section-card">
            <div class="crm-section-head">
              <div>
                <h2 class="h6 mb-0">Активность клиента</h2>
                <div class="crm-section-note">Ключевые события и обновления по задачам и этапам проекта выбранного клиента.</div>
              </div>
            </div>
            <div class="crm-timeline" id="clientCabinetTimeline"><div class="crm-timeline-item">Выберите клиента.</div></div>
          </div>
        </div>

        <aside class="crm-client-side">
          <div class="crm-card crm-section-card">
            <div class="crm-section-head">
              <div>
                <h2 class="h6 mb-0">Создать клиента</h2>
                <div class="crm-section-note">Создайте нового клиента прямо из этого экрана.</div>
              </div>
            </div>
            <form id="clientCabinetCreateForm" class="row g-2">
              <div class="col-12 d-none" data-form-error-summary></div>
              <div class="col-12">
                <label class="form-label">Тип клиента</label>
                <select class="form-select" name="client_type" data-client-type-input>
                  <option value="individual">Физлицо</option>
                  <option value="sole_proprietor">ИП</option>
                  <option value="legal_entity">Юрлицо</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Название</label>
                <input class="form-control" name="title" maxlength="255" placeholder="Например: Верона Трэвел">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label">Юридическое наименование / ФИО ИП</label>
                <input class="form-control" name="legal_name" maxlength="255">
              </div>
              <div class="col-12" data-client-type-group="individual">
                <label class="form-label">Фамилия</label>
                <input class="form-control" name="person_last_name" maxlength="120">
              </div>
              <div class="col-12" data-client-type-group="individual">
                <label class="form-label">Имя</label>
                <input class="form-control" name="person_first_name" maxlength="120">
              </div>
              <div class="col-12" data-client-type-group="individual">
                <label class="form-label">Отчество</label>
                <input class="form-control" name="person_middle_name" maxlength="120">
              </div>
              <div class="col-12" data-client-type-group="individual">
                <label class="form-label">Дата рождения</label>
                <input class="form-control" type="date" name="person_birth_date">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label">ИНН</label>
                <input class="form-control" name="tax_inn" maxlength="12">
              </div>
              <div class="col-12" data-client-type-group="legal_entity">
                <label class="form-label">КПП</label>
                <input class="form-control" name="tax_kpp" maxlength="9">
              </div>
              <div class="col-12" data-client-type-group="legal_entity">
                <label class="form-label">ОГРН</label>
                <input class="form-control" name="tax_ogrn" maxlength="13">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor">
                <label class="form-label">ОГРНИП</label>
                <input class="form-control" name="tax_ogrnip" maxlength="15">
              </div>
              <div class="col-12">
                <label class="form-label">Email</label>
                <input class="form-control" type="email" name="email" maxlength="190" placeholder="contact@client.com">
              </div>
              <div class="col-12">
                <label class="form-label">Телефон</label>
                <input class="form-control" name="phone" maxlength="64" placeholder="+7 ...">
              </div>
              <div class="col-12">
                <label class="form-label">Сайт</label>
                <input class="form-control" name="website" maxlength="2048" placeholder="https://client.com">
              </div>
              <div class="col-12">
                <label class="form-label">Мессенджер</label>
                <input class="form-control" name="messenger" maxlength="190" placeholder="@username">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label">Расчетный счет</label>
                <input class="form-control" name="bank_account" maxlength="34">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label">БИК</label>
                <input class="form-control" name="bank_bik" maxlength="9">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label">Корр. счет</label>
                <input class="form-control" name="bank_corr_account" maxlength="34">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label">Банк</label>
                <input class="form-control" name="bank_name" maxlength="255">
              </div>
              <div class="col-12">
                <label class="form-label">Юридический адрес</label>
                <textarea class="form-control" name="address_legal" rows="2"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Почтовый адрес</label>
                <textarea class="form-control" name="address_postal" rows="2"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Статус</label>
                <input class="form-control" name="status" maxlength="64" value="active">
              </div>
              <div class="col-12">
                <label class="form-label">Комментарий</label>
                <textarea class="form-control" name="notes" rows="2"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Дополнительные поля (JSON)</label>
                <textarea class="form-control" name="extra_attributes_text" rows="3" placeholder="{&quot;source&quot;:&quot;manual&quot;}"></textarea>
              </div>
              <div class="col-12">
                <button class="btn crm-btn-primary w-100" type="submit">Создать клиента</button>
              </div>
            </form>
          </div>

          <div class="crm-card crm-section-card">
            <div class="crm-section-head">
              <div>
                <h2 class="h6 mb-0">Редактировать клиента</h2>
                <div class="crm-section-note" id="clientCabinetEditHint">Выберите клиента в таблице слева.</div>
              </div>
            </div>
            <form id="clientCabinetEditForm" class="row g-2">
              <input type="hidden" name="public_id">
              <div class="col-12 d-none" data-form-error-summary></div>
              <div class="col-12">
                <label class="form-label">Тип клиента</label>
                <select class="form-select" name="client_type" data-client-type-input>
                  <option value="individual">Физлицо</option>
                  <option value="sole_proprietor">ИП</option>
                  <option value="legal_entity">Юрлицо</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Название</label>
                <input class="form-control" name="title" maxlength="255">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label">Юридическое наименование / ФИО ИП</label>
                <input class="form-control" name="legal_name" maxlength="255">
              </div>
              <div class="col-12" data-client-type-group="individual">
                <label class="form-label">Фамилия</label>
                <input class="form-control" name="person_last_name" maxlength="120">
              </div>
              <div class="col-12" data-client-type-group="individual">
                <label class="form-label">Имя</label>
                <input class="form-control" name="person_first_name" maxlength="120">
              </div>
              <div class="col-12" data-client-type-group="individual">
                <label class="form-label">Отчество</label>
                <input class="form-control" name="person_middle_name" maxlength="120">
              </div>
              <div class="col-12" data-client-type-group="individual">
                <label class="form-label">Дата рождения</label>
                <input class="form-control" type="date" name="person_birth_date">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label">ИНН</label>
                <input class="form-control" name="tax_inn" maxlength="12">
              </div>
              <div class="col-12" data-client-type-group="legal_entity">
                <label class="form-label">КПП</label>
                <input class="form-control" name="tax_kpp" maxlength="9">
              </div>
              <div class="col-12" data-client-type-group="legal_entity">
                <label class="form-label">ОГРН</label>
                <input class="form-control" name="tax_ogrn" maxlength="13">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor">
                <label class="form-label">ОГРНИП</label>
                <input class="form-control" name="tax_ogrnip" maxlength="15">
              </div>
              <div class="col-12">
                <label class="form-label">Email</label>
                <input class="form-control" type="email" name="email" maxlength="190">
              </div>
              <div class="col-12">
                <label class="form-label">Телефон</label>
                <input class="form-control" name="phone" maxlength="64">
              </div>
              <div class="col-12">
                <label class="form-label">Сайт</label>
                <input class="form-control" name="website" maxlength="2048">
              </div>
              <div class="col-12">
                <label class="form-label">Мессенджер</label>
                <input class="form-control" name="messenger" maxlength="190">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label">Расчетный счет</label>
                <input class="form-control" name="bank_account" maxlength="34">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label">БИК</label>
                <input class="form-control" name="bank_bik" maxlength="9">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label">Корр. счет</label>
                <input class="form-control" name="bank_corr_account" maxlength="34">
              </div>
              <div class="col-12" data-client-type-group="sole_proprietor,legal_entity">
                <label class="form-label">Банк</label>
                <input class="form-control" name="bank_name" maxlength="255">
              </div>
              <div class="col-12">
                <label class="form-label">Юридический адрес</label>
                <textarea class="form-control" name="address_legal" rows="2"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Почтовый адрес</label>
                <textarea class="form-control" name="address_postal" rows="2"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Статус</label>
                <input class="form-control" name="status" maxlength="64">
              </div>
              <div class="col-12">
                <label class="form-label">Комментарий</label>
                <textarea class="form-control" name="notes" rows="2"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Дополнительные поля (JSON)</label>
                <textarea class="form-control" name="extra_attributes_text" rows="3"></textarea>
              </div>
              <div class="col-12 d-flex gap-2">
                <button class="btn crm-btn-primary flex-grow-1" type="submit">Сохранить</button>
                <button class="btn crm-btn-danger-soft" id="clientCabinetDeleteBtn" type="button">Удалить</button>
              </div>
            </form>
          </div>

          <div class="crm-card crm-section-card">
            <div class="crm-section-head">
              <div>
                <h2 class="h6 mb-0">Выбранный клиент</h2>
                <div class="crm-section-note">Краткая карточка выбранного клиента.</div>
              </div>
            </div>
            <div id="clientCabinetSelectedClient"><div class="text-muted">Клиент не выбран.</div></div>
          </div>

          <div class="crm-card crm-section-card crm-progress-card">
            <div class="crm-section-head">
              <div>
                <h2 class="h6 mb-0">Сводка прогресса</h2>
                <div class="crm-section-note">Быстрый визуальный обзор общего состояния работ.</div>
              </div>
            </div>
            <div class="progress mb-2"><div class="progress-bar" style="width:0%">0%</div></div>
            <div class="text-muted small" id="clientCabinetProgressText">Выберите клиента для расчета прогресса.</div>
          </div>

          <div class="crm-card crm-section-card crm-comments-card">
            <div class="crm-section-head">
              <div>
                <h2 class="h6 mb-0">Комментарии</h2>
                <div class="crm-section-note">Последние сообщения и важные договоренности по проекту.</div>
              </div>
            </div>
            <div id="clientCabinetComments"><div class="crm-comment text-muted">Выберите клиента.</div></div>
          </div>
        </aside>
      </div>
    </main>
  </div>
</div>
