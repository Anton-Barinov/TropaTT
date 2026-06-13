<?php declare(strict_types=1); ?>
<?php $title = $t('admin_knowledge.title', 'TropaTT — Настройки базы знаний'); ?>
<body data-page="admin-knowledge" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-knowledge-page"><?php crm_page_head([
  ['label' => $t('page.home', 'Главная'), 'href' => 'index.php?route=dashboard'],
  ['label' => $t('admin.title', 'Админка'), 'href' => 'index.php?route=admin'],
  ['label' => $t('admin_knowledge.page_title', 'База знаний'), 'active' => true],
], $t('admin_knowledge.page_title', 'Настройки базы знаний'), $t('admin_knowledge.subtitle', 'Разделы, шаблоны и контроль качества корпоративной wiki.'), '<a class="btn crm-btn-secondary" href="index.php?route=knowledge">' . htmlspecialchars($t('knowledge.page_title', 'База знаний'), ENT_QUOTES, 'UTF-8') . '</a>'); ?>

<section class="crm-knowledge-stats" id="adminKnowledgeStats">
  <div class="crm-card"><strong>0</strong><span><?= htmlspecialchars($t('knowledge.stat_spaces', 'разделов'), ENT_QUOTES, 'UTF-8') ?></span></div>
  <div class="crm-card"><strong>0</strong><span><?= htmlspecialchars($t('knowledge.stat_pages', 'страниц'), ENT_QUOTES, 'UTF-8') ?></span></div>
  <div class="crm-card"><strong>0</strong><span><?= htmlspecialchars($t('knowledge.stat_published', 'опубликовано'), ENT_QUOTES, 'UTF-8') ?></span></div>
  <div class="crm-card"><strong>0</strong><span><?= htmlspecialchars($t('knowledge.stat_drafts', 'черновиков'), ENT_QUOTES, 'UTF-8') ?></span></div>
</section>

<div class="crm-knowledge-grid">
  <section class="crm-card crm-section-card">
    <div class="crm-section-head"><div><h2 class="h5 mb-0"><?= htmlspecialchars($t('admin_knowledge.spaces_title', 'Разделы'), ENT_QUOTES, 'UTF-8') ?></h2><p class="text-muted mb-0 small"><?= htmlspecialchars($t('admin_knowledge.spaces_hint', 'Управляйте областями знаний и их видимостью.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
    <div class="table-responsive">
      <table class="table crm-table align-middle mb-0"><thead><tr><th><?= htmlspecialchars($t('knowledge.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars($t('knowledge.visibility', 'Видимость'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars($t('knowledge.stat_pages', 'Страниц'), ENT_QUOTES, 'UTF-8') ?></th><th></th></tr></thead><tbody id="adminKnowledgeSpaces"><tr><td colspan="4" class="text-muted"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table>
    </div>
  </section>
  <section class="crm-card crm-section-card">
    <div class="crm-section-head"><div><h2 class="h5 mb-0"><?= htmlspecialchars($t('admin_knowledge.templates_title', 'Шаблоны'), ENT_QUOTES, 'UTF-8') ?></h2><p class="text-muted mb-0 small"><?= htmlspecialchars($t('admin_knowledge.templates_hint', 'Типовые структуры для инструкций, FAQ и регламентов.'), ENT_QUOTES, 'UTF-8') ?></p></div><button class="btn crm-btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#knowledgeTemplateModal"><?= htmlspecialchars($t('admin_knowledge.btn_create_template', 'Создать шаблон'), ENT_QUOTES, 'UTF-8') ?></button></div>
    <div class="crm-knowledge-list" id="adminKnowledgeTemplates"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
  </section>
  <section class="crm-card crm-section-card">
    <div class="crm-section-head"><h2 class="h5 mb-0"><?= htmlspecialchars($t('admin_knowledge.review_title', 'Очередь проверки'), ENT_QUOTES, 'UTF-8') ?></h2></div>
    <div class="crm-knowledge-list" id="adminKnowledgeReview"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
  </section>
  <section class="crm-card crm-section-card">
    <div class="crm-section-head"><h2 class="h5 mb-0"><?= htmlspecialchars($t('admin_knowledge.outdated_title', 'Требуют ревью'), ENT_QUOTES, 'UTF-8') ?></h2></div>
    <div class="crm-knowledge-list" id="adminKnowledgeOutdated"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
  </section>
</div>

<div class="modal fade" id="knowledgeTemplateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"><form class="modal-content" id="knowledgeTemplateForm">
    <div class="modal-header"><h5 class="modal-title"><?= htmlspecialchars($t('admin_knowledge.create_template_title', 'Новый шаблон'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button></div>
    <div class="modal-body">
      <div class="row g-3">
        <div class="col-md-8"><label class="crm-filter-label" for="knowledgeTemplateTitle"><?= htmlspecialchars($t('knowledge.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input id="knowledgeTemplateTitle" class="form-control" name="title" required></div>
        <div class="col-md-4"><label class="crm-filter-label" for="knowledgeTemplateType"><?= htmlspecialchars($t('knowledge.field_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></label><select id="knowledgeTemplateType" class="form-select" name="page_type"><option value="article"><?= htmlspecialchars($t('knowledge.type_article', 'Статья'), ENT_QUOTES, 'UTF-8') ?></option><option value="instruction"><?= htmlspecialchars($t('knowledge.type_instruction', 'Инструкция'), ENT_QUOTES, 'UTF-8') ?></option><option value="regulation"><?= htmlspecialchars($t('knowledge.type_regulation', 'Регламент'), ENT_QUOTES, 'UTF-8') ?></option><option value="faq"><?= htmlspecialchars($t('knowledge.type_faq', 'FAQ'), ENT_QUOTES, 'UTF-8') ?></option><option value="checklist"><?= htmlspecialchars($t('knowledge.type_checklist', 'Чеклист'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
        <div class="col-12"><label class="crm-filter-label" for="knowledgeTemplateDescription"><?= htmlspecialchars($t('knowledge.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label><input id="knowledgeTemplateDescription" class="form-control" name="description"></div>
        <div class="col-12"><label class="crm-filter-label" for="knowledgeTemplateContent"><?= htmlspecialchars($t('knowledge.field_content', 'Содержание'), ENT_QUOTES, 'UTF-8') ?></label><textarea id="knowledgeTemplateContent" class="form-control" name="content_html" rows="8"></textarea></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($t('common.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button type="submit" class="btn crm-btn-primary"><?= htmlspecialchars($t('common.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button></div>
  </form></div>
</div>

</main></div></div>
<script>
(function () {
  var i18n = window.CRM && window.CRM.i18n;
  var t = function (key, fallback) { return i18n && i18n.t ? i18n.t(key, fallback) : fallback; };
  var stats = document.getElementById('adminKnowledgeStats');
  function getApi() {
    return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
  }
  function waitForApi(callback, attempts) {
    if (getApi()) {
      callback();
      return;
    }
    if ((attempts || 0) > 80) {
      document.getElementById('adminKnowledgeSpaces').innerHTML = '<tr><td colspan="3" class="text-muted">' + esc(t('knowledge.load_error', 'Не удалось загрузить базу знаний.')) + '</td></tr>';
      return;
    }
    window.setTimeout(function () { waitForApi(callback, (attempts || 0) + 1); }, 50);
  }
  async function request(route, options) {
    var api = getApi();
    if (!api) throw new Error('CRM_API_NOT_READY');
    return api.request(route, options);
  }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
    });
  }
  function pageLink(item) { return 'index.php?route=knowledge-page&id=' + encodeURIComponent(item.public_id || ''); }
  function list(target, items, empty) {
    if (!items || !items.length) {
      target.innerHTML = '<div class="text-muted p-3">' + esc(empty) + '</div>';
      return;
    }
    target.innerHTML = items.map(function (item) {
      var statusBadge = '';
      if (item.status) {
        var sm = { draft: 'crm-badge-secondary', review: 'crm-badge-warning', published: 'crm-badge-success', archived: 'crm-badge-light', needs_update: 'crm-badge-danger' };
        statusBadge = '<span class="crm-badge ' + (sm[item.status] || 'crm-badge-secondary') + '" style="font-size:0.7rem;padding:0.15rem 0.4rem;margin-left:0.5rem">' + esc(item.status) + '</span>';
      }
      return '<a class="crm-knowledge-list-item" href="' + esc(pageLink(item)) + '"><span><strong>' + esc(item.title) + statusBadge + '</strong><small>' + esc(item.space_title || item.page_type || '') + '</small></span><i class="fa-solid fa-chevron-right"></i></a>';
    }).join('');
  }
  async function load() {
    var overview = await request('api/v1/knowledge/overview', { method: 'GET' });
    var data = overview.data || {};
    var totals = data.totals || {};
    [totals.spaces || 0, totals.pages || 0, totals.published || 0, totals.drafts || 0].forEach(function (value, index) {
      stats.querySelectorAll('strong')[index].textContent = String(value);
    });
    document.getElementById('adminKnowledgeSpaces').innerHTML = (data.spaces || []).map(function (space) {
      var arch = space.is_archived ? 1 : 0;
      var actionBtn = arch
        ? '<button class="btn btn-sm crm-btn-secondary" data-space-restore="' + esc(space.public_id) + '">' + esc(t('admin_knowledge.btn_restore_space', 'Восстановить')) + '</button>'
        : '<button class="btn btn-sm crm-btn-danger-soft" data-space-archive="' + esc(space.public_id) + '">' + esc(t('admin_knowledge.btn_archive_space', 'Архивировать')) + '</button>';
      var archLabel = arch ? '<span class="crm-badge crm-badge-light">' + esc(t('admin_knowledge.archived', 'Архив')) + '</span>' : '';
      return '<tr><td><strong>' + esc(space.title) + ' ' + archLabel + '</strong><div class="text-muted small">' + esc(space.description || '') + '</div></td><td>' + esc(space.visibility || '') + '</td><td>' + esc(space.pages_count || 0) + '</td><td class="crm-table-actions">' + actionBtn + '</td></tr>';
    }).join('') || '<tr><td colspan="4" class="text-muted">' + esc(t('knowledge.empty_spaces', 'Разделов пока нет.')) + '</td></tr>';
    list(document.getElementById('adminKnowledgeReview'), data.review_queue || [], t('knowledge.empty_review', 'Нет страниц на проверке.'));
    list(document.getElementById('adminKnowledgeOutdated'), data.outdated || [], t('admin_knowledge.empty_outdated', 'Нет просроченных ревью.'));
    var templates = await request('api/v1/knowledge/templates', { method: 'GET' });
    document.getElementById('adminKnowledgeTemplates').innerHTML = (templates.data && templates.data.items || []).map(function (tpl) {
      return '<div class="crm-knowledge-list-item"><span><strong>' + esc(tpl.title) + '</strong><small>' + esc(tpl.description || tpl.page_type || '') + '</small></span><span class="crm-badge">' + esc(tpl.page_type || '') + '</span></div>';
    }).join('') || '<div class="text-muted p-3">' + esc(t('admin_knowledge.empty_templates', 'Шаблонов пока нет.')) + '</div>';
  }
  document.getElementById('knowledgeTemplateForm').addEventListener('submit', async function (event) {
    event.preventDefault();
    var body = {};
    new FormData(event.currentTarget).forEach(function (value, key) { body[key] = value; });
    await request('api/v1/knowledge/templates', { method: 'POST', body: body, idempotent: true });
    window.bootstrap && bootstrap.Modal.getOrCreateInstance(document.getElementById('knowledgeTemplateModal')).hide();
    event.currentTarget.reset();
    load();
  });
  document.addEventListener('click', function (e) {
    var archiveBtn = e.target.closest('[data-space-archive]');
    if (archiveBtn) {
      request('api/v1/knowledge/spaces/' + encodeURIComponent(archiveBtn.getAttribute('data-space-archive')) + '/archive', { method: 'POST', idempotent: true }).then(load);
      return;
    }
    var restoreBtn = e.target.closest('[data-space-restore]');
    if (restoreBtn) {
      request('api/v1/knowledge/spaces/' + encodeURIComponent(restoreBtn.getAttribute('data-space-restore')) + '/restore', { method: 'POST', idempotent: true }).then(load);
    }
  });
  waitForApi(function () { load().catch(function () {
    document.getElementById('adminKnowledgeSpaces').innerHTML = '<tr><td colspan="4" class="text-muted">' + esc(t('knowledge.load_error', 'Не удалось загрузить базу знаний.')) + '</td></tr>';
  }); });
})();
</script>
</body>
