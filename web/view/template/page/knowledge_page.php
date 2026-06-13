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
  <aside class="crm-card crm-section-card crm-knowledge-tree crm-knowledge-tree-sidebar">
    <h3 class="h6"><?= htmlspecialchars($t('knowledge_page.tree_title', 'Страницы раздела'), ENT_QUOTES, 'UTF-8') ?></h3>
    <div id="knowledgeTree" class="crm-knowledge-tree-list"><div class="text-muted small"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
  </aside>
  <div class="crm-knowledge-center">
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
          <div class="col-12">
            <label class="crm-filter-label"><?= htmlspecialchars($t('knowledge.field_content', 'Содержание'), ENT_QUOTES, 'UTF-8') ?></label>
            <div class="crm-knowledge-toolbar">
              <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="bold" title="<?= htmlspecialchars($t('knowledge_page.bold', 'Жирный'), ENT_QUOTES, 'UTF-8') ?>"><b>B</b></button>
              <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="italic" title="<?= htmlspecialchars($t('knowledge_page.italic', 'Курсив'), ENT_QUOTES, 'UTF-8') ?>"><i>I</i></button>
              <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="h2" title="<?= htmlspecialchars($t('knowledge_page.h2', 'Заголовок H2'), ENT_QUOTES, 'UTF-8') ?>">H2</button>
              <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="h3" title="<?= htmlspecialchars($t('knowledge_page.h3', 'Заголовок H3'), ENT_QUOTES, 'UTF-8') ?>">H3</button>
              <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="ul" title="<?= htmlspecialchars($t('knowledge_page.ul', 'Список'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-list-ul"></i></button>
              <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="ol" title="<?= htmlspecialchars($t('knowledge_page.ol', 'Нумерованный список'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-list-ol"></i></button>
              <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="blockquote" title="<?= htmlspecialchars($t('knowledge_page.blockquote', 'Цитата'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-quote-right"></i></button>
              <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="code" title="<?= htmlspecialchars($t('knowledge_page.code', 'Код'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-code"></i></button>
              <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="link" title="<?= htmlspecialchars($t('knowledge_page.link', 'Ссылка'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-link"></i></button>
              <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="checklist" title="<?= htmlspecialchars($t('knowledge_page.checklist', 'Чеклист'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-check-square"></i></button>
            </div>
            <textarea id="knowledgeEditContent" class="form-control crm-knowledge-editor" name="content_html" rows="18"></textarea>
            <div class="small text-muted mt-1" id="knowledgeAutosaveStatus"></div>
          </div>
        </div>
        <div class="crm-knowledge-editor-actions">
          <button class="btn crm-btn-secondary" type="button" id="knowledgeCancelEditBtn"><?= htmlspecialchars($t('common.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          <button class="btn crm-btn-secondary" type="button" id="knowledgeSaveDraftBtn"><?= htmlspecialchars($t('knowledge_page.btn_save_draft', 'Сохранить черновик'), ENT_QUOTES, 'UTF-8') ?></button>
          <button class="btn crm-btn-primary" type="submit"><?= htmlspecialchars($t('knowledge_page.btn_save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
      <div id="knowledgeCommentsSection" class="crm-knowledge-comments mt-4">
        <h3 class="h5"><?= htmlspecialchars($t('knowledge_page.comments_title', 'Комментарии'), ENT_QUOTES, 'UTF-8') ?></h3>
        <div id="knowledgeCommentsList"><div class="text-muted small"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
        <div class="mt-2 d-flex gap-2">
          <textarea id="knowledgeCommentInput" class="form-control" rows="2" placeholder="<?= htmlspecialchars($t('knowledge_page.comments_placeholder', 'Напишите комментарий...'), ENT_QUOTES, 'UTF-8') ?>" style="flex:1"></textarea>
          <button class="btn crm-btn-primary" type="button" id="knowledgeCommentSendBtn" style="align-self:flex-end"><?= htmlspecialchars($t('knowledge_page.comments_send', 'Отправить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </div>
    </article>
  </div>
  <aside class="crm-card crm-section-card crm-knowledge-side">
    <h2 class="h5"><?= htmlspecialchars($t('knowledge_page.meta_title', 'Состояние'), ENT_QUOTES, 'UTF-8') ?></h2>
    <dl class="crm-knowledge-meta">
      <dt><?= htmlspecialchars($t('knowledge.field_space', 'Раздел'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="knowledgeMetaSpace">—</dd>
      <dt><?= htmlspecialchars($t('knowledge.field_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="knowledgeMetaType">—</dd>
      <dt><?= htmlspecialchars($t('knowledge_page.updated_at', 'Обновлено'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="knowledgeMetaUpdated">—</dd>
      <dt><?= htmlspecialchars($t('knowledge_page.views', 'Просмотры'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="knowledgeMetaViews">0</dd>
    </dl>
    <div class="d-flex gap-2 mb-2">
      <button class="btn btn-sm crm-btn-secondary" type="button" id="knowledgeFavBtn"><?= htmlspecialchars($t('knowledge_page.favorite_add', 'В избранное'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn btn-sm crm-btn-secondary" type="button" id="knowledgeSubBtn"><?= htmlspecialchars($t('knowledge_page.subscribe', 'Подписаться'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div class="d-grid gap-2">
      <button class="btn crm-btn-secondary" type="button" id="knowledgeReviewBtn"><?= htmlspecialchars($t('knowledge_page.btn_request_review', 'Отправить на проверку'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn crm-btn-danger-soft" type="button" id="knowledgeArchiveBtn"><?= htmlspecialchars($t('knowledge_page.btn_archive', 'В архив'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <hr>
    <h3 class="h6"><?= htmlspecialchars($t('knowledge_page.versions_title', 'Версии'), ENT_QUOTES, 'UTF-8') ?></h3>
    <div id="knowledgeVersions" class="crm-knowledge-version-list"><div class="text-muted small"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
    <div id="knowledgeDiffContainer" class="d-none mt-2"><h4 class="h6"><?= htmlspecialchars($t('knowledge_page.diff_title', 'Сравнение'), ENT_QUOTES, 'UTF-8') ?></h4><div id="knowledgeDiffContent" class="crm-knowledge-diff small"></div></div>
  </aside>
</div>
</main></div></div>
<script>
(function () {
  var pageId = document.body.getAttribute('data-knowledge-page-id') || '';
  var i18n = window.CRM && window.CRM.i18n;
  var t = function (key, fallback) { return i18n && i18n.t ? i18n.t(key, fallback) : fallback; };
  var current = null;
  var isFav = false, isSub = false, autoTimer = null;
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
    versions: document.getElementById('knowledgeVersions'),
    tree: document.getElementById('knowledgeTree'),
    favBtn: document.getElementById('knowledgeFavBtn'),
    subBtn: document.getElementById('knowledgeSubBtn'),
    commentsList: document.getElementById('knowledgeCommentsList'),
    commentInput: document.getElementById('knowledgeCommentInput'),
    commentSend: document.getElementById('knowledgeCommentSendBtn'),
    diffContainer: document.getElementById('knowledgeDiffContainer'),
    diffContent: document.getElementById('knowledgeDiffContent'),
    autosaveStatus: document.getElementById('knowledgeAutosaveStatus')
  };
  function editorWrapTag(ta, before, after) {
    var start = ta.selectionStart, end = ta.selectionEnd;
    var text = ta.value;
    var selected = text.substring(start, end) || '';
    ta.value = text.substring(0, start) + before + selected + after + text.substring(end);
    ta.selectionStart = ta.selectionEnd = start + before.length + selected.length;
    ta.focus();
    ta.dispatchEvent(new Event('input', {bubbles: true}));
  }
  function editorInsertLineBefore(ta, prefix) {
    var start = ta.selectionStart;
    var text = ta.value;
    var lineStart = text.lastIndexOf('\n', start - 1) + 1;
    var lineEnd = text.indexOf('\n', start);
    if (lineEnd === -1) lineEnd = text.length;
    var line = text.substring(lineStart, lineEnd);
    ta.value = text.substring(0, lineStart) + prefix + line + '\n' + text.substring(lineEnd);
    ta.selectionStart = ta.selectionEnd = lineStart + prefix.length + line.length + 1;
    ta.focus();
    ta.dispatchEvent(new Event('input', {bubbles: true}));
  }
  document.querySelectorAll('[data-editor-cmd]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!els.editContent) return;
      var cmd = btn.getAttribute('data-editor-cmd');
      var ta = els.editContent;
      switch (cmd) {
        case 'bold': editorWrapTag(ta, '<strong>', '</strong>'); break;
        case 'italic': editorWrapTag(ta, '<em>', '</em>'); break;
        case 'h2': editorInsertLineBefore(ta, '<h2>'); editorWrapTag(ta, '', '</h2>'); break;
        case 'h3': editorInsertLineBefore(ta, '<h3>'); editorWrapTag(ta, '', '</h3>'); break;
        case 'ul': editorInsertLineBefore(ta, '<ul><li>'); editorWrapTag(ta, '', '</li></ul>'); break;
        case 'ol': editorInsertLineBefore(ta, '<ol><li>'); editorWrapTag(ta, '', '</li></ol>'); break;
        case 'blockquote': editorInsertLineBefore(ta, '<blockquote><p>'); editorWrapTag(ta, '', '</p></blockquote>'); break;
        case 'code': editorWrapTag(ta, '<code>', '</code>'); break;
        case 'link': {
          var url = prompt(t('knowledge_page.link_prompt', 'Введите URL:'), 'https://');
          if (url) editorWrapTag(ta, '<a href="' + esc(url) + '">', '</a>');
          break;
        }
        case 'checklist': editorInsertLineBefore(ta, '<p><input type="checkbox"> '); editorWrapTag(ta, '', '</p>'); break;
      }
    });
  });
  function getApi() {
    return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
  }
  function waitForApi(callback, attempts) {
    if (getApi()) {
      callback();
      return;
    }
    if ((attempts || 0) > 80) {
      els.state.textContent = t('knowledge_page.load_error', 'Не удалось загрузить материал.');
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
    var statusMap = { draft: 'crm-badge-secondary', review: 'crm-badge-warning', published: 'crm-badge-success', archived: 'crm-badge-light', needs_update: 'crm-badge-danger' };
    els.status.className = 'crm-badge ' + (statusMap[page.status] || 'crm-badge-secondary');
    els.status.textContent = page.status || '';
    els.content.innerHTML = page.content_html || '<p class="text-muted">' + esc(t('knowledge_page.empty_content', 'Содержание пока не заполнено.')) + '</p>';
    els.metaSpace.textContent = page.space_title || '—';
    els.metaType.textContent = page.page_type || '—';
    els.metaUpdated.textContent = page.updated_at || '—';
    els.metaViews.textContent = String(page.views_count || 0);
    updateFavSubButtons();
    loadTree(page);
    loadComments();
  }
  function renderVersions(items) {
    if (!items || !items.length) {
      els.versions.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.no_versions', 'Версий пока нет.')) + '</div>';
      return;
    }
    els.versions.innerHTML = items.map(function (item) {
      return '<div class="crm-knowledge-version"><div><strong>v' + esc(item.version_number) + '</strong><span>' + esc(item.created_at || '') + '</span>' + (item.change_summary ? '<br><span class="text-muted">' + esc(item.change_summary) + '</span>' : '') + '</div><div class="d-flex gap-1 mt-1"><button class="btn btn-sm crm-btn-secondary" data-restore-version="' + esc(item.version_number) + '" style="font-size:0.78rem;padding:0.12rem 0.45rem">' + esc(t('knowledge_page.restore', 'Восстановить')) + '</button><button class="btn btn-sm crm-btn-secondary" data-diff-version="' + esc(item.version_number) + '" style="font-size:0.78rem;padding:0.12rem 0.45rem">' + esc(t('knowledge_page.btn_diff', 'Сравнить')) + '</button></div></div>';
    }).join('');
  }
  function updateFavSubButtons() {
    isFav = current && current.is_favorited ? true : false;
    isSub = current && current.is_subscribed ? true : false;
    els.favBtn.textContent = isFav ? t('knowledge_page.favorite_remove', 'Из избранного') : t('knowledge_page.favorite_add', 'В избранное');
    els.subBtn.textContent = isSub ? t('knowledge_page.unsubscribe', 'Отписаться') : t('knowledge_page.subscribe', 'Подписаться');
  }
  async function loadTree(page) {
    if (!page || !page.space_public_id) { els.tree.innerHTML = '<div class="text-muted small">—</div>'; return; }
    try {
      var envelope = await request('api/v1/knowledge/spaces/' + encodeURIComponent(page.space_public_id) + '/tree?depth=10', { method: 'GET' });
      var items = envelope.data && envelope.data.items || [];
      els.tree.innerHTML = renderTreeNodes(items);
    } catch (e) { els.tree.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.load_error', 'Ошибка')) + '</div>'; }
  }
  function renderTreeNodes(nodes) {
    if (!nodes || !nodes.length) return '<div class="text-muted small">—</div>';
    var html = '<ul class="crm-knowledge-tree-list">';
    nodes.forEach(function (n) {
      var active = n.public_id === pageId ? ' crm-knowledge-tree-active' : '';
      html += '<li class="crm-knowledge-tree-item' + active + '"><a href="index.php?route=knowledge-page&amp;id=' + esc(n.public_id) + '">' + esc(n.title) + '</a>';
      if (n.children && n.children.length) html += renderTreeNodes(n.children);
      html += '</li>';
    });
    html += '</ul>';
    return html;
  }
  async function loadComments() {
    if (!pageId) return;
    try {
      var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/comments', { method: 'GET' });
      var items = envelope.data && envelope.data.items || [];
      renderComments(items);
    } catch (e) { els.commentsList.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.load_error', 'Ошибка')) + '</div>'; }
  }
  function renderComments(items) {
    if (!items || !items.length) {
      els.commentsList.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.comments_empty', 'Комментариев пока нет')) + '</div>';
      return;
    }
    els.commentsList.innerHTML = items.map(function (c) {
      var resolved = c.resolved_at ? ' <span class="text-success small">' + esc(t('knowledge_page.comments_resolved', ' resolved')) + '</span>' : '';
      var resolveBtn = c.resolved_at
        ? '<button class="btn btn-sm crm-btn-secondary" data-comment-reopen="' + esc(c.public_id) + '" style="font-size:0.7rem">' + esc(t('knowledge_page.comments_reopen', 'Открыть')) + '</button>'
        : '<button class="btn btn-sm crm-btn-secondary" data-comment-resolve="' + esc(c.public_id) + '" style="font-size:0.7rem">' + esc(t('knowledge_page.comments_resolve', 'Решено')) + '</button>';
      return '<div class="crm-knowledge-comment' + (c.resolved_at ? ' crm-knowledge-comment-resolved' : '') + '"><div class="crm-knowledge-comment-head"><strong>' + esc(c.user_name || t('common.unknown', 'Неизвестно')) + '</strong><span class="text-muted small">' + esc(c.created_at || '') + '</span>' + resolved + '</div><div class="crm-knowledge-comment-body">' + esc(c.body) + '</div><div class="crm-knowledge-comment-actions">' + resolveBtn + '</div></div>';
    }).join('');
  }
  async function load() {
    if (!pageId) {
      els.state.textContent = t('knowledge_page.no_id', 'Не указан материал базы знаний.');
      return;
    }
    try {
      var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId), { method: 'GET' });
      render(envelope.data && envelope.data.page || {});
      var versions = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/versions', { method: 'GET' });
      renderVersions(versions.data && versions.data.items || []);
    } catch (e) {
      els.state.textContent = t('knowledge_page.load_error', 'Не удалось загрузить материал.');
    }
  }
  async function patch(body) {
    var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId), { method: 'PATCH', body: Object.assign({ row_version: current && current.row_version }, body) });
    render(envelope.data && envelope.data.page || {});
  }
  function startAutosave() {
    if (autoTimer) clearTimeout(autoTimer);
    autoTimer = setTimeout(function () {
      if (els.editor.classList.contains('d-none')) return;
      if (els.autosaveStatus) els.autosaveStatus.textContent = t('knowledge_page.autosave_saving', 'Сохранение...');
      request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/draft', {
        method: 'POST', body: { title: els.editTitle.value, content_html: els.editContent.value }, idempotent: true
      }).then(function () {
        if (els.autosaveStatus) els.autosaveStatus.textContent = t('knowledge_page.autosave_saved', 'Сохранено');
      }).catch(function () {
        if (els.autosaveStatus) els.autosaveStatus.textContent = t('knowledge_page.autosave_error', 'Ошибка сохранения');
      });
    }, 3000);
  }
  document.getElementById('knowledgeEditBtn').addEventListener('click', function () { showEditor(true); });
  document.getElementById('knowledgeCancelEditBtn').addEventListener('click', function () { showEditor(false); });
  document.getElementById('knowledgeSaveDraftBtn').addEventListener('click', async function () {
    await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/draft', { method: 'POST', body: { title: els.editTitle.value, content_html: els.editContent.value }, idempotent: true });
  });
  els.editContent && els.editContent.addEventListener('input', startAutosave);
  els.editTitle && els.editTitle.addEventListener('input', startAutosave);
  els.editor.addEventListener('submit', async function (event) {
    event.preventDefault();
    await patch({ title: els.editTitle.value, content_html: els.editContent.value });
    showEditor(false);
  });
  document.getElementById('knowledgePublishBtn').addEventListener('click', async function () {
    var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/publish', { method: 'POST', body: {}, idempotent: true });
    render(envelope.data && envelope.data.page || {});
    load();
  });
  document.getElementById('knowledgeReviewBtn').addEventListener('click', async function () {
    await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/request-review', { method: 'POST', body: {}, idempotent: true });
    load();
  });
  document.getElementById('knowledgeArchiveBtn').addEventListener('click', async function () {
    await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/archive', { method: 'POST', body: {}, idempotent: true });
    load();
  });
  els.favBtn.addEventListener('click', async function () {
    if (isFav) {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/favorite', { method: 'DELETE', idempotent: true });
    } else {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/favorite', { method: 'POST', idempotent: true });
    }
    isFav = !isFav;
    updateFavSubButtons();
  });
  els.subBtn.addEventListener('click', async function () {
    if (isSub) {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/subscribe', { method: 'DELETE', idempotent: true });
    } else {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/subscribe', { method: 'POST', idempotent: true });
    }
    isSub = !isSub;
    updateFavSubButtons();
  });
  els.commentSend.addEventListener('click', async function () {
    var body = (els.commentInput.value || '').trim();
    if (!body) return;
    els.commentInput.disabled = true;
    try {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/comments', { method: 'POST', body: { body: body }, idempotent: true });
      els.commentInput.value = '';
      loadComments();
    } catch (e) {}
    els.commentInput.disabled = false;
    els.commentInput.focus();
  });
  els.commentInput && els.commentInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); els.commentSend.click(); }
  });
  document.addEventListener('click', function (e) {
    var restoreBtn = e.target.closest('[data-restore-version]');
    if (restoreBtn) {
      var v = restoreBtn.getAttribute('data-restore-version');
      if (v && confirm(t('knowledge_page.restore_confirm', 'Восстановить эту версию? Текущее содержимое будет заменено.'))) {
        request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/versions/' + encodeURIComponent(v) + '/restore', { method: 'POST', idempotent: true }).then(function () { load(); });
      }
      return;
    }
    var diffBtn = e.target.closest('[data-diff-version]');
    if (diffBtn) {
      var vNum = parseInt(diffBtn.getAttribute('data-diff-version'), 10);
      if (current && current.row_version) {
        request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/versions/diff?from=' + (vNum - 1) + '&to=' + vNum, { method: 'GET' }).then(function (envelope) {
          var diff = envelope.data || {};
          els.diffContainer.classList.remove('d-none');
          els.diffContent.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.diff_version', 'Версия')) + ' ' + vNum + ': ' + esc(diff.text_changed ? t('knowledge_page.diff_changed', 'Есть изменения') : t('knowledge_page.diff_unchanged', 'Без изменений')) + '</div>';
        });
      }
      return;
    }
    var resolveBtn = e.target.closest('[data-comment-resolve]');
    if (resolveBtn) {
      request('api/v1/knowledge/comments/' + encodeURIComponent(resolveBtn.getAttribute('data-comment-resolve')) + '/resolve', { method: 'POST', idempotent: true }).then(loadComments);
      return;
    }
    var reopenBtn = e.target.closest('[data-comment-reopen]');
    if (reopenBtn) {
      request('api/v1/knowledge/comments/' + encodeURIComponent(reopenBtn.getAttribute('data-comment-reopen')) + '/reopen', { method: 'POST', idempotent: true }).then(loadComments);
    }
  });
  waitForApi(load);
})();
</script>
</body>
