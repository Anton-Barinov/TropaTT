<?php declare(strict_types=1); ?>
<?php $title = $t('companies.title', 'TropaTT — Компании'); ?>
<body data-page="companies" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=dashboard" data-i18n="page.dashboard"><?= htmlspecialchars($t('page.dashboard', 'Главная'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="companies.breadcrumb"><?= htmlspecialchars($t('companies.breadcrumb', 'Компании'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" data-i18n="companies.page_title"><?= htmlspecialchars($t('companies.page_title', 'Компании'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="companies.page_subtitle"><?= htmlspecialchars($t('companies.page_subtitle', 'Справочник компаний и реквизитов.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="crm-page-actions"><a class="btn crm-btn-secondary" href="index.php?route=clients" data-i18n="companies.link_clients"><?= htmlspecialchars($t('companies.link_clients', 'Клиенты'), ENT_QUOTES, 'UTF-8') ?></a><a class="btn crm-btn-secondary" href="index.php?route=contacts" data-i18n="companies.link_contacts"><?= htmlspecialchars($t('companies.link_contacts', 'Контакты'), ENT_QUOTES, 'UTF-8') ?></a></div></div>

<section class="crm-card crm-section-card mb-3">
  <form id="companiesCreateForm" class="row g-2">
    <div class="col-md-3"><label class="form-label" data-i18n="companies.field_title"><?= htmlspecialchars($t('companies.field_title', 'Название *'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" required maxlength="255"></div>
    <div class="col-md-2"><span class="form-label d-block" data-i18n="companies.field_status"><?= htmlspecialchars($t('companies.field_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></span><span class="crm-badge success" data-i18n="clients.status_active"><?= htmlspecialchars($t('clients.status_active', 'Активен'), ENT_QUOTES, 'UTF-8') ?></span><input type="hidden" name="status" value="active"></div>
    <div class="col-md-2"><label class="form-label" data-i18n="companies.field_tax_number"><?= htmlspecialchars($t('companies.field_tax_number', 'ИНН'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="tax_number" maxlength="32"></div>
    <div class="col-md-3"><label class="form-label" data-i18n="companies.field_email"><?= htmlspecialchars($t('companies.field_email', 'Email'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="email" maxlength="190"></div>
    <div class="col-md-2 d-flex align-items-end"><button class="btn crm-btn-primary w-100" type="submit" data-i18n="page.create"><?= htmlspecialchars($t('page.create', 'Создать'), ENT_QUOTES, 'UTF-8') ?></button></div>
  </form>
</section>

<section class="crm-card crm-section-card">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h6 mb-0" data-i18n="companies.section_list"><?= htmlspecialchars($t('companies.section_list', 'Список компаний'), ENT_QUOTES, 'UTF-8') ?></h2>
    <button id="companiesRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="companies.btn_refresh"><?= htmlspecialchars($t('companies.btn_refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
  </div>
  <div id="companiesList"><div class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
</section>
</main>

<div class="modal fade" id="companyEditModal" tabindex="-1" aria-labelledby="companyEditModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="companyEditModalTitle" data-i18n="companies.edit_modal_title"><?= htmlspecialchars($t('companies.edit_modal_title', 'Редактировать компанию'), ENT_QUOTES, 'UTF-8') ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <form id="companyEditForm">
        <div class="modal-body">
          <input type="hidden" name="public_id">
          <div class="row g-3">
            <div class="col-md-8"><label class="form-label" for="companyEditTitle" data-i18n="companies.field_title"><?= htmlspecialchars($t('companies.field_title', 'Название *'), ENT_QUOTES, 'UTF-8') ?></label><input id="companyEditTitle" class="form-control" name="title" required maxlength="255"></div>
            <div class="col-md-4"><label class="form-label" for="companyEditStatus" data-i18n="companies.field_status"><?= htmlspecialchars($t('companies.field_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label><select id="companyEditStatus" class="form-select" name="status"><option value="active" data-i18n="clients.status_active"><?= htmlspecialchars($t('clients.status_active', 'Активен'), ENT_QUOTES, 'UTF-8') ?></option><option value="inactive" data-i18n="clients.status_inactive"><?= htmlspecialchars($t('clients.status_inactive', 'Неактивен'), ENT_QUOTES, 'UTF-8') ?></option><option value="archived" data-i18n="clients.status_archived"><?= htmlspecialchars($t('clients.status_archived', 'Архив'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
            <div class="col-md-6"><label class="form-label" for="companyEditTaxNumber" data-i18n="companies.field_tax_number"><?= htmlspecialchars($t('companies.field_tax_number', 'ИНН'), ENT_QUOTES, 'UTF-8') ?></label><input id="companyEditTaxNumber" class="form-control" name="tax_number" maxlength="32"></div>
            <div class="col-md-6"><label class="form-label" for="companyEditEmail" data-i18n="companies.field_email"><?= htmlspecialchars($t('companies.field_email', 'Email'), ENT_QUOTES, 'UTF-8') ?></label><input id="companyEditEmail" class="form-control" name="email" type="email" maxlength="190"></div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" type="submit" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button></div>
      </form>
    </div>
  </div>
</div>
</div></div>
</body>
