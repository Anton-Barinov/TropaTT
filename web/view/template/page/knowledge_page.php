<?php declare(strict_types=1); ?>
<?php $title = $t('knowledge_page.title', 'TropaTT — Материал базы знаний'); ?>
<?php $pageId = (string)($_GET['id'] ?? ''); ?>
<body data-page="knowledge-page" data-protected="1" data-knowledge-page-id="<?= htmlspecialchars($pageId, ENT_QUOTES, 'UTF-8') ?>"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-knowledge-page-detail"><?php crm_page_head([
  ['label' => $t('page.home', 'Главная'), 'href' => 'index.php?route=dashboard'],
  ['label' => $t('knowledge.page_title', 'База знаний'), 'href' => 'index.php?route=knowledge'],
  ['label' => $t('knowledge_page.page_title', 'Материал'), 'active' => true],
], $t('knowledge_page.page_title', 'Материал'), $t('knowledge_page.subtitle', 'Просмотр, редактирование и публикация знаний команды.'), '<div class="d-flex gap-2 flex-wrap"><a class="btn crm-btn-secondary" href="index.php?route=knowledge">' . htmlspecialchars($t('knowledge.back_to_list', 'К базе знаний'), ENT_QUOTES, 'UTF-8') . '</a><button class="btn crm-btn-secondary" type="button" id="knowledgeEditBtn">' . htmlspecialchars($t('knowledge_page.btn_edit', 'Редактировать'), ENT_QUOTES, 'UTF-8') . '</button><button class="btn crm-btn-primary" type="button" id="knowledgePublishBtn">' . htmlspecialchars($t('knowledge_page.btn_publish', 'Опубликовать'), ENT_QUOTES, 'UTF-8') . '</button></div>'); ?>

<div class="crm-knowledge-detail-layout">
  <article class="crm-card crm-section-card crm-knowledge-article">
    <div id="knowledgePageState" class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
    <div id="knowledgePageView" class="d-none">
      <div class="crm-knowledge-article-head">
        <div><div class="crm-eyebrow" id="knowledgePageSpace"></div><h2 id="knowledgePageTitle"></h2></div>
        <span class="crm-badge" id="knowledgePageStatus"></span>
      </div>
      <div class="crm-knowledge-content" id="knowledgePageContent"></div>
    </div>
    <form id="knowledgePageEditor" class="d-none">
      <div class="row g-3">
        <div class="col-12"><label class="crm-filter-label" for="knowledgeEditTitle"><?= htmlspecialchars($t('knowledge.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input id="knowledgeEditTitle" class="form-control" name="title" required></div>
        <div class="col-12"><label class="crm-filter-label" for="knowledgeEditContent"><?= htmlspecialchars($t('knowledge.field_content', 'Содержание'), ENT_QUOTES, 'UTF-8') ?></label><textarea id="knowledgeEditContent" class="form-control crm-knowledge-editor" name="content_html" rows="18"></textarea></div>
      </div>
      <div class="crm-knowledge-editor-actions">
        <button class="btn crm-btn-secondary" type="button" id="knowledgeCancelEditBtn"><?= htmlspecialchars($t('common.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn crm-btn-secondary" type="button" id="knowledgeSaveDraftBtn"><?= htmlspecialchars($t('knowledge_page.btn_save_draft', 'Сохранить черновик'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn crm-btn-primary" type="submit"><?= htmlspecialchars($t('knowledge_page.btn_save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </form>
  </article>
  <aside class="crm-card crm-section-card crm-knowledge-side">
    <h2 class="h5"><?= htmlspecialchars($t('knowledge_page.meta_title', 'Состояние'), ENT_QUOTES, 'UTF-8') ?></h2>
    <dl class="crm-knowledge-meta">
      <dt><?= htmlspecialchars($t('knowledge.field_space', 'Раздел'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="knowledgeMetaSpace">—</dd>
      <dt><?= htmlspecialchars($t('knowledge.field_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="knowledgeMetaType">—</dd>
      <dt><?= htmlspecialchars($t('knowledge_page.updated_at', 'Обновлено'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="knowledgeMetaUpdated">—</dd>
      <dt><?= htmlspecialchars($t('knowledge_page.views', 'Просмотры'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="knowledgeMetaViews">0</dd>
    </dl>
    <div class="d-grid gap-2">
      <button class="btn crm-btn-secondary" type="button" id="knowledgeReviewBtn"><?= htmlspecialchars($t('knowledge_page.btn_request_review', 'Отправить на проверку'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn crm-btn-danger-soft" type="button" id="knowledgeArchiveBtn"><?= htmlspecialchars($t('knowledge_page.btn_archive', 'В архив'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <hr>
    <h3 class="h6"><?= htmlspecialchars($t('knowledge_page.versions_title', 'Версии'), ENT_QUOTES, 'UTF-8') ?></h3>
    <div id="knowledgeVersions" class="crm-knowledge-version-list"><div class="text-muted small"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
  </aside>
</div>
</main></div></div>
<script>
(function () {
  var pageId = document.body.getAttribute('data-knowledge-page-id') || '';
  var api = window.CRM && window.CRM.api;
  var i18n = window.CRM && window.CRM.i18n;
  var t = function (key, fallback) { return i18n && i18n.t ? i18n.t(key, fallback) : fallback; };
  var current = null;
  var els = {
    state: document.getElementById('knowledgePageState'),
    view: document.getElementById('knowledgePageView'),
    editor: document.getElementById('knowledgePageEditor'),
    title: document.getElementById('knowledgePageTitle'),
    space: document.getElementById('knowledgePageSpace'),
    status: document.getElementById('knowledgePageStatus'),
    content: document.getElementById('knowledgePageContent'),
    editTitle: document.getElementById('knowledgeEditTitle'),
    editContent: document.getElementById('knowledgeEditContent'),
    metaSpace: document.getElementById('knowledgeMetaSpace'),
    metaType: document.getElementById('knowledgeMetaType'),
    metaUpdated: document.getElementById('knowledgeMetaUpdated'),
    metaViews: document.getElementById('knowledgeMetaViews'),
    versions: document.getElementById('knowledgeVersions')
  };
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
    });
  }
  function showEditor(show) {
    els.editor.classList.toggle('d-none', !show);
    els.view.classList.toggle('d-none', show);
    if (show && current) {
      els.editTitle.value = current.title || '';
      els.editContent.value = current.content_html || '';
    }
  }
  function render(page) {
    current = page;
    els.state.classList.add('d-none');
    els.view.classList.remove('d-none');
    els.title.textContent = page.title || '';
    els.space.textContent = page.space_title || '';
    els.status.textContent = page.status || '';
    els.content.innerHTML = page.content_html || '<p class="text-muted">' + esc(t('knowledge_page.empty_content', 'Содержание пока не заполнено.')) + '</p>';
    els.metaSpace.textContent = page.space_title || '—';
    els.metaType.textContent = page.page_type || '—';
    els.metaUpdated.textContent = page.updated_at || '—';
    els.metaViews.textContent = String(page.views_count || 0);
  }
  function renderVersions(items) {
    if (!items || !items.length) {
      els.versions.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.no_versions', 'Версий пока нет.')) + '</div>';
      return;
    }
    els.versions.innerHTML = items.map(function (item) {
      return '<div class="crm-knowledge-version"><strong>v' + esc(item.version_number) + '</strong><span>' + esc(item.created_at || '') + '</span></div>';
    }).join('');
  }
  async function load() {
    if (!pageId) {
      els.state.textContent = t('knowledge_page.no_id', 'Не указан материал базы знаний.');
      return;
    }
    try {
      var envelope = await api.request('api/v1/knowledge/pages/' + encodeURIComponent(pageId), { method: 'GET' });
      render(envelope.data && envelope.data.page || {});
      var versions = await api.request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/versions', { method: 'GET' });
      renderVersions(versions.data && versions.data.items || []);
    } catch (e) {
      els.state.textContent = t('knowledge_page.load_error', 'Не удалось загрузить материал.');
    }
  }
  async function patch(body) {
    var envelope = await api.request('api/v1/knowledge/pages/' + encodeURIComponent(pageId), { method: 'PATCH', body: Object.assign({ row_version: current && current.row_version }, body) });
    render(envelope.data && envelope.data.page || {});
  }
  document.getElementById('knowledgeEditBtn').addEventListener('click', function () { showEditor(true); });
  document.getElementById('knowledgeCancelEditBtn').addEventListener('click', function () { showEditor(false); });
  document.getElementById('knowledgeSaveDraftBtn').addEventListener('click', async function () {
    await api.request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/draft', { method: 'POST', body: { title: els.editTitle.value, content_html: els.editContent.value }, idempotent: true });
  });
  els.editor.addEventListener('submit', async function (event) {
    event.preventDefault();
    await patch({ title: els.editTitle.value, content_html: els.editContent.value });
    showEditor(false);
  });
  document.getElementById('knowledgePublishBtn').addEventListener('click', async function () {
    var envelope = await api.request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/publish', { method: 'POST', body: {}, idempotent: true });
    render(envelope.data && envelope.data.page || {});
    load();
  });
  document.getElementById('knowledgeReviewBtn').addEventListener('click', async function () {
    var envelope = await api.request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/request-review', { method: 'POST', body: {}, idempotent: true });
    render(envelope.data && envelope.data.page || {});
  });
  document.getElementById('knowledgeArchiveBtn').addEventListener('click', async function () {
    var envelope = await api.request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/archive', { method: 'POST', body: {}, idempotent: true });
    render(envelope.data && envelope.data.page || {});
  });
  load();
})();
</script>
</body>
