<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Кастомные поля'; ?>
<body data-page="admin-custom-fields" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title">Кастомные поля</h1><p class="crm-subtitle">Управление пользовательскими полями для задач, проектов и других сущностей.</p></div><div class="d-flex gap-2"><button id="adminCustomFieldsRefreshBtn" class="btn crm-btn-secondary" type="button">Обновить</button><button id="adminCustomFieldsCreateBtn" class="btn crm-btn-primary" type="button">Создать поле</button></div></div>

<div class="alert alert-info mb-3" role="alert">
  Кастомные поля позволяют расширять стандартные сущности дополнительными атрибутами. Поля могут быть привязаны к задачам, проектам, клиентам и другим типам сущностей.
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Пользовательские поля</h2><div class="crm-section-note">Список всех кастомных полей.</div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th>Название</th><th>Тип</th><th>Сущность</th><th>Обязательное</th><th></th></tr></thead><tbody id="adminCustomFieldsBody"><tr><td colspan="5" class="text-muted">Загрузка...</td></tr></tbody></table>
    </div>
  </div>
</div>

</main></div></div>
