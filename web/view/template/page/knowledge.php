<?php declare(strict_types=1); ?>
<?php $title = $t('knowledge.title', 'TropaTT — База знаний'); ?>
<body data-page="knowledge" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-knowledge-page"><?php crm_page_head([
  ['label' => $t('page.home', 'Главная'), 'href' => 'index.php?route=dashboard'],
  ['label' => $t('knowledge.page_title', 'База знаний'), 'active' => true],
], $t('knowledge.page_title', 'База знаний'), $t('knowledge.subtitle', 'Регламенты, инструкции, FAQ и проектные знания команды.'), '<div class="d-flex gap-2 flex-wrap"><a class="btn crm-btn-secondary" href="index.php?route=admin-knowledge">' . htmlspecialchars($t('knowledge.admin_link', 'Настройки'), ENT_QUOTES, 'UTF-8') . '</a><button class="btn crm-btn-secondary" type="button" data-knowledge-open-space>' . htmlspecialchars($t('knowledge.btn_create_space', 'Создать раздел'), ENT_QUOTES, 'UTF-8') . '</button><button class="btn crm-btn-primary" type="button" data-knowledge-open-page>' . htmlspecialchars($t('knowledge.btn_create_page', 'Создать страницу'), ENT_QUOTES, 'UTF-8') . '</button></div>'); ?>

<section class="crm-card crm-section-card crm-knowledge-hero">
  <div>
    <div class="crm-eyebrow"><?= htmlspecialchars($t('knowledge.hero_eyebrow', 'Корпоративная wiki'), ENT_QUOTES, 'UTF-8') ?></div>
    <h2><?= htmlspecialchars($t('knowledge.hero_title', 'Знания, которые не теряются в чатах'), ENT_QUOTES, 'UTF-8') ?></h2>
    <p><?= htmlspecialchars($t('knowledge.hero_text', 'Собирайте инструкции, решения, стандарты и ответы на частые вопросы рядом с задачами, проектами и клиентами.'), ENT_QUOTES, 'UTF-8') ?></p>
  </div>
  <div class="crm-knowledge-search">
    <div class="input-group">
      <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
      <input id="knowledgeSearchInput" class="form-control" type="search" placeholder="<?= htmlspecialchars($t('knowledge.search_placeholder', 'Найдите регламент, инструкцию или FAQ'), ENT_QUOTES, 'UTF-8') ?>">
      <button id="knowledgeSearchButton" class="btn crm-btn-secondary" type="button"><?= htmlspecialchars($t('knowledge.btn_search', 'Найти'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div class="crm-knowledge-filters mt-2 d-flex gap-2 flex-wrap" id="knowledgeFilters">
      <select id="knowledgeFilterType" class="form-select form-select-sm" style="width:auto;min-width:120px"><option value=""><?= htmlspecialchars($t('knowledge.filter_all_types', 'Все типы'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="article"><?= htmlspecialchars($t('knowledge.type_article', 'Статья'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="instruction"><?= htmlspecialchars($t('knowledge.type_instruction', 'Инструкция'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="regulation"><?= htmlspecialchars($t('knowledge.type_regulation', 'Регламент'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="faq"><?= htmlspecialchars($t('knowledge.type_faq', 'FAQ'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="checklist"><?= htmlspecialchars($t('knowledge.type_checklist', 'Чеклист'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="runbook"><?= htmlspecialchars($t('knowledge.type_runbook', 'Runbook'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="meeting_note"><?= htmlspecialchars($t('knowledge.type_meeting_note', 'Протокол встречи'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="decision"><?= htmlspecialchars($t('knowledge.type_decision', 'Решение'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="client_note"><?= htmlspecialchars($t('knowledge.type_client_note', 'Заметка клиента'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="project_note"><?= htmlspecialchars($t('knowledge.type_project_note', 'Заметка проекта'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="onboarding"><?= htmlspecialchars($t('knowledge.type_onboarding', 'Онбординг'), ENT_QUOTES, 'UTF-8') ?></option>
      </select>
      <select id="knowledgeFilterTag" class="form-select form-select-sm" style="width:auto;min-width:140px"><option value=""><?= htmlspecialchars($t('knowledge.filter_all_tags', 'Все теги'), ENT_QUOTES, 'UTF-8') ?></option></select>
      <button id="knowledgeFilterReset" class="btn crm-btn-muted btn-sm" type="button" style="display:none"><?= htmlspecialchars($t('page.reset', 'Сбросить'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </div>
</section>

<section class="crm-knowledge-stats" id="knowledgeStats">
  <div class="crm-card"><strong>0</strong><span><?= htmlspecialchars($t('knowledge.stat_spaces', 'разделов'), ENT_QUOTES, 'UTF-8') ?></span></div>
  <div class="crm-card"><strong>0</strong><span><?= htmlspecialchars($t('knowledge.stat_pages', 'страниц'), ENT_QUOTES, 'UTF-8') ?></span></div>
  <div class="crm-card"><strong>0</strong><span><?= htmlspecialchars($t('knowledge.stat_published', 'опубликовано'), ENT_QUOTES, 'UTF-8') ?></span></div>
  <div class="crm-card"><strong>0</strong><span><?= htmlspecialchars($t('knowledge.stat_drafts', 'черновиков'), ENT_QUOTES, 'UTF-8') ?></span></div>
</section>

<div class="crm-knowledge-layout">
  <section class="crm-card crm-section-card crm-knowledge-spaces-panel">
    <div class="crm-section-head"><h2 class="h5 mb-0"><?= htmlspecialchars($t('knowledge.spaces_title', 'Разделы'), ENT_QUOTES, 'UTF-8') ?></h2><button class="btn btn-sm crm-btn-secondary" type="button" data-knowledge-open-space><i class="fa-solid fa-plus" style="margin-right:0.3rem"></i><?= htmlspecialchars($t('knowledge.btn_create_space', 'Добавить'), ENT_QUOTES, 'UTF-8') ?></button></div>
    <div id="knowledgeSpaces"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
  </section>
  <div class="crm-card crm-section-card crm-knowledge-content-panel">
    <div id="knowledgeSpaceView" class="crm-knowledge-space-view">
      <div id="knowledgeSpaceHeader"></div>
      <div id="knowledgeSpacePagesList"></div>
    </div>
    <div id="knowledgeTabView">
      <div class="crm-knowledge-tab-nav" id="knowledgeTabNav">
        <button class="crm-knowledge-tab-btn is-active" data-kb-tab="recent"><?= htmlspecialchars($t('knowledge.recent_title', 'Недавно обновлено'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="crm-knowledge-tab-btn" data-kb-tab="popular"><?= htmlspecialchars($t('knowledge.popular_title', 'Популярные'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="crm-knowledge-tab-btn" data-kb-tab="drafts"><?= htmlspecialchars($t('knowledge.drafts_title', 'Мои черновики'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="crm-knowledge-tab-btn" data-kb-tab="review"><?= htmlspecialchars($t('knowledge.review_title', 'На проверке'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="crm-knowledge-tab-btn" data-kb-tab="outdated"><?= htmlspecialchars($t('knowledge.outdated_title', 'Требуют актуализации'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div id="knowledgeSearchResultsWrap" class="crm-knowledge-tab-panel">
        <div id="knowledgeSearchInfo" class="crm-knowledge-search-info"></div>
        <div id="knowledgeSearchResults"></div>
      </div>
      <div class="crm-knowledge-tab-panel is-active" data-kb-panel="recent">
        <div id="knowledgeRecent"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </div>
      <div class="crm-knowledge-tab-panel" data-kb-panel="popular">
        <div id="knowledgePopular"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </div>
      <div class="crm-knowledge-tab-panel" data-kb-panel="drafts">
        <div id="knowledgeDrafts"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </div>
      <div class="crm-knowledge-tab-panel" data-kb-panel="review">
        <div id="knowledgeReview"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </div>
      <div class="crm-knowledge-tab-panel" data-kb-panel="outdated">
        <div id="knowledgeOutdated"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="knowledgePageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"><form class="modal-content" id="knowledgePageForm">
    <div class="modal-header"><div><h5 class="modal-title"><?= htmlspecialchars($t('knowledge.create_page_title', 'Новая страница'), ENT_QUOTES, 'UTF-8') ?></h5><p class="text-muted mb-0 small"><?= htmlspecialchars($t('knowledge.create_page_hint', 'Сначала можно сохранить черновик, потом отправить на проверку или опубликовать.'), ENT_QUOTES, 'UTF-8') ?></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button></div>
    <div class="modal-body">
      <div class="row g-3">
        <div class="col-12"><label class="crm-filter-label" for="knowledgePageTitle"><?= htmlspecialchars($t('knowledge.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input id="knowledgePageTitle" class="form-control" name="title" required></div>
        <div class="col-md-6"><label class="crm-filter-label" for="knowledgePageSpace"><?= htmlspecialchars($t('knowledge.field_space', 'Раздел'), ENT_QUOTES, 'UTF-8') ?></label><select id="knowledgePageSpace" class="form-select" name="space_public_id"></select></div>
        <div class="col-md-6"><label class="crm-filter-label" for="knowledgePageType"><?= htmlspecialchars($t('knowledge.field_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></label>
          <div class="input-group"><span class="input-group-text" id="knowledgeTypeIcon"><i class="fa-solid fa-file-lines"></i></span>
          <select id="knowledgePageType" class="form-select" name="page_type">
            <option value="article" data-icon="fa-solid fa-file-lines"><?= htmlspecialchars($t('knowledge.type_article', 'Статья'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="instruction" data-icon="fa-solid fa-list-check"><?= htmlspecialchars($t('knowledge.type_instruction', 'Инструкция'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="regulation" data-icon="fa-solid fa-scale-balanced"><?= htmlspecialchars($t('knowledge.type_regulation', 'Регламент'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="faq" data-icon="fa-solid fa-circle-question"><?= htmlspecialchars($t('knowledge.type_faq', 'FAQ'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="checklist" data-icon="fa-solid fa-check-square"><?= htmlspecialchars($t('knowledge.type_checklist', 'Чеклист'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="runbook" data-icon="fa-solid fa-book"><?= htmlspecialchars($t('knowledge.type_runbook', 'Runbook'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="meeting_note" data-icon="fa-solid fa-note-sticky"><?= htmlspecialchars($t('knowledge.type_meeting_note', 'Протокол встречи'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="decision" data-icon="fa-solid fa-gavel"><?= htmlspecialchars($t('knowledge.type_decision', 'Решение'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="client_note" data-icon="fa-solid fa-address-card"><?= htmlspecialchars($t('knowledge.type_client_note', 'Заметка клиента'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="project_note" data-icon="fa-solid fa-diagram-project"><?= htmlspecialchars($t('knowledge.type_project_note', 'Заметка проекта'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="onboarding" data-icon="fa-solid fa-person-chalkboard"><?= htmlspecialchars($t('knowledge.type_onboarding', 'Онбординг'), ENT_QUOTES, 'UTF-8') ?></option>
          </select></div></div>
        <div class="col-12" id="knowledgePageTemplateWrap" style="display:none"><label class="crm-filter-label" for="knowledgePageTemplate"><?= htmlspecialchars($t('knowledge.template_label', 'Шаблон'), ENT_QUOTES, 'UTF-8') ?></label>
          <select id="knowledgePageTemplate" class="form-select">
            <option value=""><?= htmlspecialchars($t('knowledge.template_none', 'Без шаблона (пустой)'), ENT_QUOTES, 'UTF-8') ?></option>
          </select></div>
        <div class="col-12"><label class="crm-filter-label" for="knowledgePageContent"><?= htmlspecialchars($t('knowledge.field_content', 'Содержание'), ENT_QUOTES, 'UTF-8') ?></label><textarea id="knowledgePageContent" class="form-control" name="content_html" rows="8" placeholder="<?= htmlspecialchars($t('knowledge.content_placeholder', 'Опишите решение, шаги, правила или ответ на вопрос.'), ENT_QUOTES, 'UTF-8') ?>"></textarea></div>
      </div>
      <div class="alert alert-danger d-none mt-3" id="knowledgePageError"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($t('common.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button type="submit" class="btn crm-btn-primary"><?= htmlspecialchars($t('knowledge.btn_save_draft', 'Создать черновик'), ENT_QUOTES, 'UTF-8') ?></button></div>
  </form></div>
</div>

<div class="modal fade" id="knowledgeSpaceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><form class="modal-content" id="knowledgeSpaceForm">
    <div class="modal-header"><h5 class="modal-title"><?= htmlspecialchars($t('knowledge.create_space_title', 'Новый раздел'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button></div>
    <div class="modal-body">
      <label class="crm-filter-label" for="knowledgeSpaceTitle"><?= htmlspecialchars($t('knowledge.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input id="knowledgeSpaceTitle" class="form-control mb-3" name="title" required>
      <label class="crm-filter-label" for="knowledgeSpaceParent"><?= htmlspecialchars($t('knowledge.field_parent', 'Родительский раздел'), ENT_QUOTES, 'UTF-8') ?></label><select id="knowledgeSpaceParent" class="form-select mb-3" name="parent_public_id"><option value=""><?= htmlspecialchars($t('knowledge.no_parent', 'Без родителя (корневой)'), ENT_QUOTES, 'UTF-8') ?></option></select>
      <label class="crm-filter-label" for="knowledgeSpaceDescription"><?= htmlspecialchars($t('knowledge.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label><textarea id="knowledgeSpaceDescription" class="form-control" name="description" rows="4"></textarea>
      <div class="alert alert-danger d-none mt-3" id="knowledgeSpaceError"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($t('common.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button type="submit" class="btn crm-btn-primary"><?= htmlspecialchars($t('knowledge.btn_create_space', 'Создать раздел'), ENT_QUOTES, 'UTF-8') ?></button></div>
  </form></div>
</div>

</main></div></div>
<script>
(function () {
  var i18n = window.CRM && window.CRM.i18n;
  var t = function (key, fallback) { return i18n && i18n.t ? i18n.t(key, fallback) : fallback; };
  var urlParams = new URLSearchParams(window.location.search);
  var state = {
    spaces: [],
    activeSpace: '',
    sourceEntity: {
      type: urlParams.get('entity_type') || '',
      publicId: urlParams.get('entity_public_id') || '',
      title: urlParams.get('source_title') || ''
    }
  };
  var els = {
    stats: document.getElementById('knowledgeStats'),
    spaces: document.getElementById('knowledgeSpaces'),
    recent: document.getElementById('knowledgeRecent'),
    review: document.getElementById('knowledgeReview'),
    popular: document.getElementById('knowledgePopular'),
    drafts: document.getElementById('knowledgeDrafts'),
    outdated: document.getElementById('knowledgeOutdated'),
    results: document.getElementById('knowledgeSearchResults'),
    searchInfo: document.getElementById('knowledgeSearchInfo'),
    search: document.getElementById('knowledgeSearchInput'),
    searchButton: document.getElementById('knowledgeSearchButton'),
    pageForm: document.getElementById('knowledgePageForm'),
    spaceForm: document.getElementById('knowledgeSpaceForm'),
    pageSpace: document.getElementById('knowledgePageSpace'),
    pageType: document.getElementById('knowledgePageType'),
    pageTemplateWrap: document.getElementById('knowledgePageTemplateWrap'),
    pageTemplate: document.getElementById('knowledgePageTemplate'),
    pageContent: document.getElementById('knowledgePageContent'),
    filterType: document.getElementById('knowledgeFilterType'),
    filterTag: document.getElementById('knowledgeFilterTag'),
    filterReset: document.getElementById('knowledgeFilterReset'),
    spaceView: document.getElementById('knowledgeSpaceView'),
    spaceHeader: document.getElementById('knowledgeSpaceHeader'),
    spacePagesList: document.getElementById('knowledgeSpacePagesList'),
    tabView: document.getElementById('knowledgeTabView'),
    searchResultsWrap: document.getElementById('knowledgeSearchResultsWrap')
  };
  function getApi() {
    return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
  }
  function waitForApi(callback, attempts) {
    var api = getApi();
    if (api) { callback(api); return; }
    if ((attempts || 0) > 80) {
      renderList(els.recent, [], t('knowledge.load_error', 'Не удалось загрузить базу знаний.'));
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
  function pageUrl(item) { return 'index.php?route=knowledge-page&id=' + encodeURIComponent(item.public_id || ''); }
  function renderList(target, items, emptyText, opts) {
    if (!target) return '';
    if (!items || !items.length) {
      var emptyIcon = (opts && opts.emptyIcon) || 'fa-folder-open';
      target.innerHTML = '<div class="crm-knowledge-empty"><i class="fa-solid ' + esc(emptyIcon) + '"></i><p>' + esc(emptyText) + '</p></div>';
      return '';
    }
    var showExcerpt = opts && opts.excerpt;
    var html = items.map(function (item) {
      var typeIconsMap = { article: 'fa-file-lines', instruction: 'fa-list-check', regulation: 'fa-scale-balanced', faq: 'fa-circle-question', checklist: 'fa-check-square', runbook: 'fa-book', meeting_note: 'fa-note-sticky', decision: 'fa-gavel', client_note: 'fa-address-card', project_note: 'fa-diagram-project', onboarding: 'fa-person-chalkboard' };
      var icon = typeIconsMap[item.page_type] || 'fa-file-lines';
      var typeIconHtml = '<i class="fa-solid ' + icon + '" style="margin-right:0.4rem;color:var(--crm-muted);width:1rem;text-align:center"></i>';
      var statusBadge = '';
      if (item.status) {
        var sm = { draft: 'crm-badge-secondary', review: 'crm-badge-warning', published: 'crm-badge-success', archived: 'crm-badge-light', needs_update: 'crm-badge-danger' };
        var statusLabels = { draft: t('knowledge.status_draft', 'Черновик'), review: t('knowledge.status_review', 'На проверке'), published: t('knowledge.status_published', 'Опубликовано'), archived: t('knowledge.status_archived', 'В архиве'), needs_update: t('knowledge.status_needs_update', 'Требует обновления') };
        var statusText = statusLabels[item.status] || item.status;
        statusBadge = '<span class="crm-badge ' + (sm[item.status] || 'crm-badge-secondary') + '" style="font-size:0.7rem;padding:0.15rem 0.4rem;margin-left:0.5rem">' + esc(statusText) + '</span>';
      }
      var excerptHtml = showExcerpt && item.excerpt ? '<span class="crm-knowledge-excerpt">' + esc(item.excerpt.substring(0, 120)) + '</span>' : '';
      return '<a class="crm-knowledge-list-item" href="' + esc(pageUrl(item)) + '"><span><strong>' + typeIconHtml + esc(item.title) + statusBadge + '</strong><small>' + esc(item.space_title || '') + '</small>' + excerptHtml + '</span><i class="fa-solid fa-chevron-right"></i></a>';
    }).join('');
    target.innerHTML = html;
    return html;
  }
  function renderSpaces(items) {
    state.spaces = items || [];
    var flatForSelect = flattenSpaces(state.spaces);
    var spaceOpts = flatForSelect.map(function (space) {
      return '<option value="' + esc(space.public_id) + '">' + esc(space.title) + '</option>';
    }).join('');
    if (els.pageSpace) els.pageSpace.innerHTML = spaceOpts;
    if (!els.spaces) return;
    var allHtml = '<a href="javascript:void(0)" class="crm-knowledge-space-link' + (!state.activeSpace ? ' is-active' : '') + '" data-space="">' +
      '<span class="crm-knowledge-space-info"><strong>' + esc(t('knowledge.all_spaces', 'Все разделы')) + '</strong><small>' + esc(t('knowledge.all_spaces_desc', 'Обзор всей базы знаний')) + '</small></span></a>';
    if (!state.spaces.length) {
      els.spaces.innerHTML = allHtml + '<div class="crm-knowledge-empty" style="padding:1.5rem 1rem"><i class="fa-solid fa-folder-plus"></i><p>' + esc(t('knowledge.empty_spaces', 'Разделов пока нет. Создайте первый раздел.')) + '</p></div>';
      return;
    }
    els.spaces.innerHTML = allHtml + renderSpaceTree(state.spaces, 0);
  }
  function flattenSpaces(tree) {
    var result = [];
    if (!tree || !tree.length) return result;
    tree.forEach(function (s) {
      result.push(s);
      if (s.children && s.children.length) {
        result = result.concat(flattenSpaces(s.children));
      }
    });
    return result;
  }
  function renderSpaceTree(nodes, depth) {
    if (!nodes || !nodes.length) return '';
    return nodes.map(function (space) {
      var desc = space.description || t('knowledge.no_description', 'Без описания');
      var count = space.pages_count || 0;
      var active = state.activeSpace === space.public_id ? ' is-active' : '';
      var hasChildren = space.children && space.children.length;
      var paddingLeft = depth > 0 ? ' style="padding-left:' + (depth * 18) + 'px"' : '';
      var html = '<div class="crm-knowledge-space-row"' + paddingLeft + '>';
      if (hasChildren) {
        html += '<button type="button" class="crm-knowledge-space-toggle is-open" data-space-toggle="' + esc(space.public_id) + '"><i class="fa-solid fa-chevron-right"></i></button>';
      }
      html += '<a href="javascript:void(0)" class="crm-knowledge-space-link' + active + '" data-space="' + esc(space.public_id) + '">';
      html += '<span class="crm-knowledge-space-info"><strong>' + esc(space.title) + '</strong><small>' + esc(desc) + '</small></span><span class="crm-knowledge-space-count">' + esc(count) + '</span></a>';
      html += '<button type="button" class="crm-knowledge-space-add-sub" data-add-sub="' + esc(space.public_id) + '" title="' + esc(t('knowledge.btn_add_subspace', 'Добавить подраздел')) + '"><i class="fa-solid fa-plus"></i></button>';
      html += '</div>';
      if (hasChildren) {
        html += '<div class="crm-knowledge-space-children" data-space-children="' + esc(space.public_id) + '">' + renderSpaceTree(space.children, depth + 1) + '</div>';
      }
      return html;
    }).join('');
  }
  function renderStats(totals, labels) {
    if (!els.stats) return;
    var values = [totals.spaces || 0, totals.pages || 0, totals.published || 0, totals.drafts || 0];
    var cardValues = els.stats.querySelectorAll('strong');
    var cardLabels = els.stats.querySelectorAll('span');
    cardValues.forEach(function (node, index) { node.textContent = String(values[index] || 0); });
    if (labels) {
      cardLabels.forEach(function (node, index) { node.textContent = labels[index] || ''; });
    }
  }
  var globalStatLabels = [
    t('knowledge.stat_spaces', 'разделов'),
    t('knowledge.stat_pages', 'страниц'),
    t('knowledge.stat_published', 'опубликовано'),
    t('knowledge.stat_drafts', 'черновиков')
  ];
  var spaceStatLabels = [
    t('knowledge.stat_pages_in_space', 'страниц в разделе'),
    t('knowledge.stat_published', 'опубликовано'),
    t('knowledge.stat_drafts', 'черновиков'),
    t('knowledge.stat_views', 'просмотров')
  ];
  function showTabView() {
    if (els.spaceView) els.spaceView.classList.remove('is-active');
    if (els.tabView) els.tabView.style.display = '';
    if (els.searchResultsWrap) els.searchResultsWrap.classList.remove('is-active');
    var tabPanels = document.querySelectorAll('[data-kb-panel]');
    tabPanels.forEach(function (p) { p.classList.remove('is-active'); });
    var recentPanel = document.querySelector('[data-kb-panel="recent"]');
    if (recentPanel) recentPanel.classList.add('is-active');
    document.querySelectorAll('[data-kb-tab]').forEach(function (btn) { btn.classList.toggle('is-active', btn.getAttribute('data-kb-tab') === 'recent'); });
  }
  function expandParents(spaceId) {
    var flat = flattenSpaces(state.spaces);
    var space = flat.find(function (s) { return s.public_id === spaceId; });
    if (!space) return;
    var pid = space.parent_id;
    while (pid) {
      var parent = flat.find(function (s) { return s.id === pid; });
      if (!parent) break;
      var toggle = els.spaces.querySelector('[data-space-toggle="' + parent.public_id + '"]');
      var children = els.spaces.querySelector('[data-space-children="' + parent.public_id + '"]');
      if (toggle) toggle.classList.add('is-open');
      if (children) children.classList.remove('is-collapsed');
      pid = parent.parent_id;
    }
  }
  function selectSpace(spaceId) {
    state.activeSpace = spaceId;
    els.spaces.querySelectorAll('.crm-knowledge-space-link').forEach(function (link) {
      link.classList.toggle('is-active', link.getAttribute('data-space') === spaceId);
    });
    if (spaceId) expandParents(spaceId);
    showTabView();
    load(spaceId);
  }
  async function load(spaceId) {
    try {
      if (spaceId) {
        var envelope = await request('api/v1/knowledge/search', { method: 'GET', query: { space_public_id: spaceId, limit: 200 } });
        var pages = envelope.data && envelope.data.items || [];
        var flatSpaces = flattenSpaces(state.spaces);
        var space = flatSpaces.find(function (s) { return s.public_id === spaceId; });
        var spaceTitle = space ? space.title : '';
        var recent = pages.slice().sort(function (a, b) { return (b.updated_at || '').localeCompare(a.updated_at || ''); });
        var popular = pages.slice().sort(function (a, b) { return (b.views_count || 0) - (a.views_count || 0); });
        var drafts = pages.filter(function (p) { return p.status === 'draft'; });
        var review = pages.filter(function (p) { return p.status === 'review'; });
        var outdated = pages.filter(function (p) { return p.status === 'needs_update'; });
        var publishedCount = pages.filter(function (p) { return p.status === 'published'; }).length;
        renderStats({
          spaces: pages.length,
          pages: pages.length,
          published: publishedCount,
          drafts: drafts.length
        }, spaceStatLabels);
        renderList(els.recent, recent, t('knowledge.empty_recent', 'Пока нет страниц.') + (spaceTitle ? ' ' + t('knowledge.in_space', 'в разделе') + ' «' + spaceTitle + '»' : ''), { emptyIcon: 'fa-clock-rotate-left' });
        renderList(els.popular, popular, t('knowledge.empty_popular', 'Популярных страниц пока нет.'), { emptyIcon: 'fa-fire' });
        renderList(els.drafts, drafts, t('knowledge.empty_drafts', 'Нет черновиков.'), { emptyIcon: 'fa-pencil' });
        renderList(els.review, review, t('knowledge.empty_review', 'Нет страниц на проверке.'), { emptyIcon: 'fa-clipboard-check' });
        renderList(els.outdated, outdated, t('knowledge.empty_outdated', 'Нет устаревших страниц.'), { emptyIcon: 'fa-clock' });
      } else {
        var envelope = await request('api/v1/knowledge/overview', { method: 'GET' });
        var data = envelope.data || {};
        renderStats(data.totals || {}, globalStatLabels);
        var treeItems = data.spaces || [];
        try {
          var treeEnvelope = await request('api/v1/knowledge/spaces-tree', { method: 'GET' });
          treeItems = (treeEnvelope.data && treeEnvelope.data.items) || treeItems;
        } catch (treeErr) {
          if (window.console && console.warn) console.warn('[CRM] spaces-tree failed, using flat list', treeErr);
        }
        renderSpaces(treeItems);
        renderList(els.recent, data.recent || [], t('knowledge.empty_recent', 'Пока нет страниц.'), { emptyIcon: 'fa-clock-rotate-left' });
        renderList(els.review, data.review_queue || [], t('knowledge.empty_review', 'Нет страниц на проверке.'), { emptyIcon: 'fa-clipboard-check' });
        renderList(els.popular, data.popular || [], t('knowledge.empty_popular', 'Популярных страниц пока нет.'), { emptyIcon: 'fa-fire' });
        renderList(els.drafts, data.drafts || [], t('knowledge.empty_drafts', 'Нет черновиков.'), { emptyIcon: 'fa-pencil' });
        renderList(els.outdated, data.outdated || [], t('knowledge.empty_outdated', 'Нет устаревших страниц.'), { emptyIcon: 'fa-clock' });
      }
    } catch (err) {
      renderList(els.recent, [], t('knowledge.load_error', 'Не удалось загрузить базу знаний.'), { emptyIcon: 'fa-triangle-exclamation' });
    }
  }
  async function search() {
    var query = (els.search && els.search.value || '').trim();
    var params = {};
    if (query) params.q = query;
    if (state.activeSpace) params.space_public_id = state.activeSpace;
    var tf = els.filterType && els.filterType.value || '';
    var tg = els.filterTag && els.filterTag.value || '';
    if (tf) params.page_type = tf;
    if (tg) params.tag_public_id = tg;
    var hasFilter = !!(query || tf || tg);
    if (!hasFilter && !state.activeSpace) {
      if (els.searchResultsWrap) els.searchResultsWrap.classList.remove('is-active');
      showTabView();
      return;
    }
    try {
      var envelope = await request('api/v1/knowledge/search', { method: 'GET', query: params });
      var items = envelope.data && envelope.data.items || [];
      if (els.searchInfo) {
        var countLabel = items.length + ' ' + (items.length === 1 ? t('knowledge.result_one', 'результат') : items.length < 5 ? t('knowledge.result_few', 'результата') : t('knowledge.result_many', 'результатов'));
        els.searchInfo.innerHTML = '<span>' + esc(countLabel) + (query ? ' &middot; «' + esc(query) + '»' : '') + '</span><button type="button" class="crm-knowledge-search-clear" id="knowledgeSearchClear">' + esc(t('knowledge.clear_search', 'Очистить')) + '</button>';
        document.getElementById('knowledgeSearchClear') && document.getElementById('knowledgeSearchClear').addEventListener('click', function () {
          if (els.search) els.search.value = '';
          if (els.filterType) els.filterType.value = '';
          if (els.filterTag) els.filterTag.value = '';
          if (els.filterReset) els.filterReset.style.display = 'none';
          selectSpace(state.activeSpace);
        });
      }
      renderList(els.results, items, t('knowledge.search_empty', 'Ничего не найдено.'), { excerpt: true, emptyIcon: 'fa-magnifying-glass' });
      if (els.spaceView) els.spaceView.classList.remove('is-active');
      if (els.tabView) els.tabView.style.display = 'none';
      if (els.searchResultsWrap) els.searchResultsWrap.classList.add('is-active');
      document.querySelectorAll('[data-kb-panel]').forEach(function (p) { p.classList.remove('is-active'); });
    } catch (err) {
      renderList(els.results, [], t('knowledge.search_error', 'Не удалось выполнить поиск.'), { emptyIcon: 'fa-triangle-exclamation' });
    }
  }
  var debouncedSearch = window.CRM && CRM.debounce ? CRM.debounce(search, 350) : search;
  function formPayload(form) {
    var data = {};
    new FormData(form).forEach(function (value, key) { data[key] = value; });
    return data;
  }
  document.querySelectorAll('[data-knowledge-open-page]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (els.pageForm) els.pageForm.reset();
      var titleInput = document.getElementById('knowledgePageTitle');
      if (titleInput && state.sourceEntity.title) titleInput.value = state.sourceEntity.title;
      var wrap = els.pageTemplateWrap;
      if (wrap) wrap.style.display = 'none';
      if (els.pageContent) els.pageContent.value = '';
      var modal = window.bootstrap && bootstrap.Modal.getOrCreateInstance(document.getElementById('knowledgePageModal'));
      modal.show();
      if (els.pageType) {
        updateTypeIcon(els.pageType.value);
        els.pageType.dispatchEvent(new Event('change'));
      }
    });
  });
  document.addEventListener('click', function (e) {
    var toggleBtn = e.target.closest('[data-space-toggle]');
    if (toggleBtn) {
      e.preventDefault();
      var spaceId = toggleBtn.getAttribute('data-space-toggle');
      var children = els.spaces.querySelector('[data-space-children="' + spaceId + '"]');
      if (children) {
        var isOpen = toggleBtn.classList.contains('is-open');
        toggleBtn.classList.toggle('is-open', !isOpen);
        children.classList.toggle('is-collapsed', isOpen);
      }
      return;
    }
    var addSubBtn = e.target.closest('[data-add-sub]');
    if (addSubBtn) {
      e.preventDefault();
      var parentId = addSubBtn.getAttribute('data-add-sub');
      var parentSelect = document.getElementById('knowledgeSpaceParent');
      if (parentSelect) {
        var flat = flattenSpaces(state.spaces);
        parentSelect.innerHTML = '<option value="">' + esc(t('knowledge.no_parent', 'Без родителя (корневой)')) + '</option>' + flat.map(function (s) {
          return '<option value="' + esc(s.public_id) + '"' + (s.public_id === parentId ? ' selected' : '') + '>' + esc(s.title) + '</option>';
        }).join('');
      }
      window.bootstrap && bootstrap.Modal.getOrCreateInstance(document.getElementById('knowledgeSpaceModal')).show();
      return;
    }
  });
  document.querySelectorAll('[data-knowledge-open-space]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var parentSelect = document.getElementById('knowledgeSpaceParent');
      if (parentSelect) {
        var flat = flattenSpaces(state.spaces);
        parentSelect.innerHTML = '<option value="">' + esc(t('knowledge.no_parent', 'Без родителя (корневой)')) + '</option>' + flat.map(function (s) {
          return '<option value="' + esc(s.public_id) + '">' + esc(s.title) + '</option>';
        }).join('');
      }
      window.bootstrap && bootstrap.Modal.getOrCreateInstance(document.getElementById('knowledgeSpaceModal')).show();
    });
  });
  if (els.search) {
    els.search.addEventListener('input', debouncedSearch);
    els.search.addEventListener('change', search);
    els.search.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') { event.preventDefault(); search(); }
    });
  }
  if (els.searchButton) els.searchButton.addEventListener('click', search);
  var tabBtns = document.querySelectorAll('[data-kb-tab]');
  tabBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      tabBtns.forEach(function (b) { b.classList.toggle('is-active', b === btn); });
      document.querySelectorAll('[data-kb-panel]').forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-kb-panel') === btn.getAttribute('data-kb-tab')); });
      if (els.searchResultsWrap) els.searchResultsWrap.classList.remove('is-active');
    });
  });
  if (els.spaces) {
    els.spaces.addEventListener('click', function (e) {
      var link = e.target.closest('.crm-knowledge-space-link[data-space]');
      if (!link) return;
      e.preventDefault();
      selectSpace(link.getAttribute('data-space'));
    });
  }
  var templatesCache = {};
  var typeIcons = { article: 'fa-solid fa-file-lines', instruction: 'fa-solid fa-list-check', regulation: 'fa-solid fa-scale-balanced', faq: 'fa-solid fa-circle-question', checklist: 'fa-solid fa-check-square', runbook: 'fa-solid fa-book', meeting_note: 'fa-solid fa-note-sticky', decision: 'fa-solid fa-gavel', client_note: 'fa-solid fa-address-card', project_note: 'fa-solid fa-diagram-project', onboarding: 'fa-solid fa-person-chalkboard' };
  function updateTypeIcon(type) {
    var iconEl = document.getElementById('knowledgeTypeIcon');
    if (!iconEl) return;
    iconEl.innerHTML = '<i class="' + (typeIcons[type] || 'fa-solid fa-file-lines') + '"></i>';
  }
  async function loadTemplates(pageType) {
    if (templatesCache[pageType]) return templatesCache[pageType];
    try {
      var envelope = await request('api/v1/knowledge/templates', { method: 'GET', query: { page_type: pageType } });
      templatesCache[pageType] = envelope.data && envelope.data.items || [];
    } catch (e) { templatesCache[pageType] = []; }
    return templatesCache[pageType];
  }
  if (els.pageType) els.pageType.addEventListener('change', async function () {
    var type = els.pageType.value;
    updateTypeIcon(type);
    var items = await loadTemplates(type);
    var wrap = els.pageTemplateWrap;
    var sel = els.pageTemplate;
    if (!wrap || !sel) return;
    if (!items.length) { wrap.style.display = 'none'; return; }
    sel.innerHTML = '<option value="">' + esc(t('knowledge.template_none', 'Без шаблона (пустой)')) + '</option>' + items.map(function (tmpl) {
      return '<option value="' + esc(tmpl.public_id) + '" data-content="' + esc(tmpl.content_html || '') + '">' + esc(tmpl.title) + '</option>';
    }).join('');
    wrap.style.display = '';
  });
  if (els.pageTemplate) els.pageTemplate.addEventListener('change', function () {
    if (els.pageContent) {
      var selected = els.pageTemplate.options[els.pageTemplate.selectedIndex];
      els.pageContent.value = selected && selected.dataset.content ? selected.dataset.content : '';
    }
  });
  if (els.pageForm) els.pageForm.addEventListener('submit', async function (event) {
    event.preventDefault();
    var envelope = await request('api/v1/knowledge/pages', { method: 'POST', body: formPayload(els.pageForm), idempotent: true });
    var page = envelope.data && envelope.data.page;
    if (page && page.public_id && state.sourceEntity.type && state.sourceEntity.publicId) {
      try {
        await request('api/v1/knowledge/pages/' + encodeURIComponent(page.public_id) + '/links', {
          method: 'POST', body: { entity_type: state.sourceEntity.type, entity_public_id: state.sourceEntity.publicId, relation_type: 'related' }, idempotent: true
        });
      } catch (linkError) { if (window.console && console.warn) console.warn('[CRM] Knowledge page created, but entity link failed', linkError); }
    }
    if (page && page.public_id) window.location.href = pageUrl(page);
  });
  if (els.spaceForm) els.spaceForm.addEventListener('submit', async function (event) {
    event.preventDefault();
    await request('api/v1/knowledge/spaces', { method: 'POST', body: formPayload(els.spaceForm), idempotent: true });
    window.bootstrap && bootstrap.Modal.getOrCreateInstance(document.getElementById('knowledgeSpaceModal')).hide();
    els.spaceForm.reset();
    load();
  });
  async function loadTags() {
    try {
      var envelope = await request('api/v1/tags', { method: 'GET' });
      var tags = envelope.data && envelope.data.items || [];
      if (els.filterTag) {
        els.filterTag.innerHTML = '<option value="">' + esc(t('knowledge.filter_all_tags', 'Все теги')) + '</option>' + tags.map(function (tag) {
          return '<option value="' + esc(tag.public_id) + '">' + esc(tag.title) + '</option>';
        }).join('');
      }
    } catch (e) {}
  }
  [els.filterType, els.filterTag].forEach(function (el) {
    if (el) el.addEventListener('change', function () {
      var hasFilter = (els.filterType && els.filterType.value) || (els.filterTag && els.filterTag.value);
      if (els.filterReset) els.filterReset.style.display = hasFilter || (els.search && els.search.value) ? '' : 'none';
      search();
    });
  });
  if (els.filterReset) els.filterReset.addEventListener('click', function () {
    if (els.filterType) els.filterType.value = '';
    if (els.filterTag) els.filterTag.value = '';
    if (els.search) els.search.value = '';
    if (els.filterReset) els.filterReset.style.display = 'none';
    selectSpace(state.activeSpace);
  });
  var initSpace = urlParams.get('space') || '';
  waitForApi(function () {
    if (initSpace) {
      load(initSpace);
    } else {
      load();
    }
    loadTags();
    if (initSpace) {
      window.setTimeout(function () {
        state.activeSpace = initSpace;
        els.spaces.querySelectorAll('.crm-knowledge-space-link').forEach(function (l) {
          l.classList.toggle('is-active', l.getAttribute('data-space') === initSpace);
        });
      }, 300);
    }
    if (state.sourceEntity.type && state.sourceEntity.publicId) {
      window.setTimeout(function () {
        var btn = document.querySelector('[data-knowledge-open-page]');
        if (btn) btn.click();
      }, 250);
    }
  });
})();
</script>
</body>
