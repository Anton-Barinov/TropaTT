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
  <div class="mt-3 pt-3 border-top" id="departmentKnowledgeSection">
    <h6 class="mb-2"><?= htmlspecialchars($t('departments.section_knowledge', 'Регламенты отдела'), ENT_QUOTES, 'UTF-8') ?></h6>
    <div id="departmentKnowledgeList"><div class="text-muted small">—</div></div>
    <div class="mt-2"><a class="btn btn-sm crm-btn-primary" href="index.php?route=knowledge" id="departmentKnowledgeLink" data-i18n="departments.btn_knowledge"><?= htmlspecialchars($t('departments.btn_knowledge', 'Перейти в базу знаний'), ENT_QUOTES, 'UTF-8') ?></a></div>
  </div>
</section>
</main></div></div>

<script>
(function () {
  var deptKnowledgeSection = document.getElementById('departmentKnowledgeSection');
  var deptKnowledgeList = document.getElementById('departmentKnowledgeList');
  if (!deptKnowledgeSection || !deptKnowledgeList) return;
  var deptLink = document.getElementById('departmentKnowledgeLink');
  var api = window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
  if (!api) return;
  function loadDepartmentKnowledge(deptId) {
    if (!deptId) { deptKnowledgeList.innerHTML = '<?= htmlspecialchars($t('departments.knowledge_select_department', 'Выберите департамент'), ENT_QUOTES, 'UTF-8') ?>'; return; }
    if (deptLink) deptLink.href = 'index.php?route=knowledge&amp;entity_type=department&amp;entity_public_id=' + encodeURIComponent(deptId);
    deptKnowledgeList.innerHTML = '<?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?>';
    api.request('api/v1/knowledge/entities/department/' + encodeURIComponent(deptId) + '/pages', { method: 'GET' }).then(function (envelope) {
      var items = envelope.data && envelope.data.items || [];
      if (!items.length) {
        deptKnowledgeList.innerHTML = '<?= htmlspecialchars($t('departments.knowledge_empty', 'Нет регламентов отдела'), ENT_QUOTES, 'UTF-8') ?>';
      } else {
        deptKnowledgeList.innerHTML = '<ul class="list-unstyled mb-0 small">' + items.map(function (p) {
          return '<li class="mb-1"><a href="index.php?route=knowledge-page&amp;id=' + encodeURIComponent(p.public_id) + '">' + (function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function(ch) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[ch]; }); })(p.title || '') + '</a></li>';
        }).join('') + '</ul>';
      }
    }).catch(function () {
      deptKnowledgeList.innerHTML = '<div class="text-muted small">—</div>';
    });
  }
  function findFirstDepartmentId() {
    var node = document.querySelector('#departmentsList .dept-item, #departmentsList tr, #departmentsList .crm-dept-item');
    if (!node) return '';
    var id = node.getAttribute('data-dept-id') || node.getAttribute('data-public-id') || '';
    if (!id) {
      var link = node.querySelector('a[href*="department"]');
      if (link) {
        var m = link.getAttribute('href').match(/department[&?]?.*?=([a-zA-Z0-9_]+)/);
        if (m) id = m[1];
      }
    }
    return id;
  }
  function waitForDeptList(cb, n) {
    if (document.querySelector('#departmentsList .text-muted') && document.querySelector('#departmentsList .text-muted').textContent.indexOf('Загрузка') >= 0) {
      if ((n || 0) > 40) { cb(''); return; }
      setTimeout(function () { waitForDeptList(cb, (n || 0) + 1); }, 200);
      return;
    }
    var firstId = findFirstDepartmentId();
    cb(firstId);
  }
  waitForDeptList(function (deptId) {
    loadDepartmentKnowledge(deptId);
  });

  document.getElementById('departmentsRefreshBtn') && document.getElementById('departmentsRefreshBtn').addEventListener('click', function () {
    setTimeout(function () {
      var firstId = findFirstDepartmentId();
      if (firstId) loadDepartmentKnowledge(firstId);
    }, 500);
  });
})();
</script>
</body>
</html>