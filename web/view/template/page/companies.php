<?php declare(strict_types=1); ?>
<?php $title = $t('companies.title', 'TropaTT — Компании'); ?>
<body data-page="companies" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=dashboard" data-i18n="page.dashboard"><?= htmlspecialchars($t('page.dashboard', 'Главная'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="companies.breadcrumb"><?= htmlspecialchars($t('companies.breadcrumb', 'Компании'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" data-i18n="companies.page_title"><?= htmlspecialchars($t('companies.page_title', 'Компании'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="companies.page_subtitle"><?= htmlspecialchars($t('companies.page_subtitle', 'Справочник компаний и реквизитов.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="crm-page-actions"><a class="btn crm-btn-secondary" href="index.php?route=clients" data-i18n="companies.link_clients"><?= htmlspecialchars($t('companies.link_clients', 'Клиенты'), ENT_QUOTES, 'UTF-8') ?></a><a class="btn crm-btn-secondary" href="index.php?route=contacts" data-i18n="companies.link_contacts"><?= htmlspecialchars($t('companies.link_contacts', 'Контакты'), ENT_QUOTES, 'UTF-8') ?></a></div></div>

<section class="crm-card crm-section-card mb-3">
  <form id="companiesCreateForm" class="row g-2">
    <div class="col-md-3"><label class="form-label" data-i18n="companies.field_title"><?= htmlspecialchars($t('companies.field_title', 'Название *'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" required maxlength="255"></div>
    <div class="col-md-2"><label class="form-label" data-i18n="companies.field_status"><?= htmlspecialchars($t('companies.field_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="status" maxlength="64" placeholder="active"></div>
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
</main></div></div>
</body>
