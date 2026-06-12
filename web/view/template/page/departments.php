<?php declare(strict_types=1); ?>
<?php $title = $t('departments.title', 'TropaTT — Департаменты'); ?>
<body data-page="departments" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=dashboard" data-i18n="page.home"><?= htmlspecialchars($t('page.home', 'Главная'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="departments.page_title"><?= htmlspecialchars($t('departments.page_title', 'Департаменты'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" data-i18n="departments.page_title"><?= htmlspecialchars($t('departments.page_title', 'Департаменты'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="departments.subtitle"><?= htmlspecialchars($t('departments.subtitle', 'Оргструктура команд и подразделений.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="crm-page-actions"><a class="btn crm-btn-secondary" href="index.php?route=teams" data-i18n="departments.btn_teams"><?= htmlspecialchars($t('departments.btn_teams', 'Команды'), ENT_QUOTES, 'UTF-8') ?></a><a class="btn crm-btn-secondary" href="index.php?route=admin-users" data-i18n="departments.btn_users"><?= htmlspecialchars($t('departments.btn_users', 'Пользователи'), ENT_QUOTES, 'UTF-8') ?></a></div></div>

<section class="crm-card crm-section-card mb-3">
  <form id="departmentsCreateForm" class="row g-2">
    <div class="col-md-4"><label class="form-label" data-i18n="departments.field_title"><?= htmlspecialchars($t('departments.field_title', 'Название *'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" required maxlength="255"></div>
    <div class="col-md-4"><label class="form-label" data-i18n="departments.field_code"><?= htmlspecialchars($t('departments.field_code', 'Код'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="code" maxlength="64"></div>
    <div class="col-md-2"><label class="form-label" data-i18n="departments.field_status"><?= htmlspecialchars($t('departments.field_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="status" maxlength="64" placeholder="<?= htmlspecialchars($t('departments.placeholder_status', 'active'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="departments.placeholder_status"></div>
    <div class="col-md-2 d-flex align-items-end"><button class="btn crm-btn-primary w-100" type="submit" data-i18n="page.create"><?= htmlspecialchars($t('page.create', 'Создать'), ENT_QUOTES, 'UTF-8') ?></button></div>
  </form>
</section>

<section class="crm-card crm-section-card">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h6 mb-0" data-i18n="departments.heading_list"><?= htmlspecialchars($t('departments.heading_list', 'Список департаментов'), ENT_QUOTES, 'UTF-8') ?></h2>
    <button id="departmentsRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="page.refresh"><?= htmlspecialchars($t('page.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
  </div>
  <div id="departmentsList"><div class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
</section>
</main></div></div>
