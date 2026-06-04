<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Компании'; ?>
<body data-page="companies" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=dashboard">Главная</a></li><li class="breadcrumb-item active">Компании</li></ol><h1 class="crm-page-title">Компании</h1><p class="crm-subtitle">Справочник компаний и реквизитов.</p></div><div class="crm-page-actions"><a class="btn crm-btn-secondary" href="index.php?route=clients">Клиенты</a><a class="btn crm-btn-secondary" href="index.php?route=contacts">Контакты</a></div></div>

<section class="crm-card crm-section-card mb-3">
  <form id="companiesCreateForm" class="row g-2">
    <div class="col-md-3"><label class="form-label">Название *</label><input class="form-control" name="title" required maxlength="255"></div>
    <div class="col-md-2"><label class="form-label">Статус</label><input class="form-control" name="status" maxlength="64" placeholder="active"></div>
    <div class="col-md-2"><label class="form-label">ИНН</label><input class="form-control" name="tax_number" maxlength="32"></div>
    <div class="col-md-3"><label class="form-label">Email</label><input class="form-control" name="email" maxlength="190"></div>
    <div class="col-md-2 d-flex align-items-end"><button class="btn crm-btn-primary w-100" type="submit">Создать</button></div>
  </form>
</section>

<section class="crm-card crm-section-card">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h6 mb-0">Список компаний</h2>
    <button id="companiesRefreshBtn" class="btn crm-btn-secondary" type="button">Обновить</button>
  </div>
  <div id="companiesList"><div class="text-muted">Загрузка...</div></div>
</section>
</main></div></div>

