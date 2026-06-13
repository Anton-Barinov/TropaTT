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
    <label class="crm-filter-label" for="knowledgeSearchInput"><?= htmlspecialchars($t('knowledge.search_label', 'Поиск по базе знаний'), ENT_QUOTES, 'UTF-8') ?></label>
    <div class="input-group">
      <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
      <input id="knowledgeSearchInput" class="form-control" type="search" placeholder="<?= htmlspecialchars($t('knowledge.search_placeholder', 'Найдите регламент, инструкцию или FAQ'), ENT_QUOTES, 'UTF-8') ?>">
      <button id="knowledgeSearchButton" class="btn crm-btn-secondary" type="button"><?= htmlspecialchars($t('knowledge.btn_search', 'Найти'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </div>
</section>

<section class="crm-knowledge-stats" id="knowledgeStats">
  <div class="crm-card"><strong>0</strong><span><?= htmlspecialchars($t('knowledge.stat_spaces', 'разделов'), ENT_QUOTES, 'UTF-8') ?></span></div>
  <div class="crm-card"><strong>0</strong><span><?= htmlspecialchars($t('knowledge.stat_pages', 'страниц'), ENT_QUOTES, 'UTF-8') ?></span></div>
  <div class="crm-card"><strong>0</strong><span><?= htmlspecialchars($t('knowledge.stat_published', 'опубликовано'), ENT_QUOTES, 'UTF-8') ?></span></div>
  <div class="crm-card"><strong>0</strong><span><?= htmlspecialchars($t('knowledge.stat_drafts', 'черновиков'), ENT_QUOTES, 'UTF-8') ?></span></div>
</section>

<div class="crm-knowledge-grid">
  <section class="crm-card crm-section-card">
    <div class="crm-section-head"><h2 class="h5 mb-0"><?= htmlspecialchars($t('knowledge.spaces_title', 'Разделы'), ENT_QUOTES, 'UTF-8') ?></h2></div>
    <div class="crm-knowledge-space-list" id="knowledgeSpaces"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
  </section>
  <section class="crm-card crm-section-card">
    <div class="crm-section-head"><h2 class="h5 mb-0"><?= htmlspecialchars($t('knowledge.recent_title', 'Недавно обновлено'), ENT_QUOTES, 'UTF-8') ?></h2></div>
    <div class="crm-knowledge-list" id="knowledgeRecent"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
  </section>
  <section class="crm-card crm-section-card">
    <div class="crm-section-head"><h2 class="h5 mb-0"><?= htmlspecialchars($t('knowledge.search_results_title', 'Результаты поиска'), ENT_QUOTES, 'UTF-8') ?></h2></div>
    <div class="crm-knowledge-list" id="knowledgeSearchResults"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.search_empty_hint', 'Введите запрос, чтобы найти материалы.'), ENT_QUOTES, 'UTF-8') ?></div></div>
  </section>
  <section class="crm-card crm-section-card">
    <div class="crm-section-head"><h2 class="h5 mb-0"><?= htmlspecialchars($t('knowledge.review_title', 'На проверке'), ENT_QUOTES, 'UTF-8') ?></h2></div>
    <div class="crm-knowledge-list" id="knowledgeReview"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
  </section>
  <section class="crm-card crm-section-card">
    <div class="crm-section-head"><h2 class="h5 mb-0"><?= htmlspecialchars($t('knowledge.popular_title', 'Популярные'), ENT_QUOTES, 'UTF-8') ?></h2></div>
    <div class="crm-knowledge-list" id="knowledgePopular"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
  </section>
  <section class="crm-card crm-section-card">
    <div class="crm-section-head"><h2 class="h5 mb-0"><?= htmlspecialchars($t('knowledge.drafts_title', 'Мои черновики'), ENT_QUOTES, 'UTF-8') ?></h2></div>
    <div class="crm-knowledge-list" id="knowledgeDrafts"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
  </section>
  <section class="crm-card crm-section-card">
    <div class="crm-section-head"><h2 class="h5 mb-0"><?= htmlspecialchars($t('knowledge.outdated_title', 'Требуют актуализации'), ENT_QUOTES, 'UTF-8') ?></h2></div>
    <div class="crm-knowledge-list" id="knowledgeOutdated"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
  </section>
</div>

<div class="modal fade" id="knowledgePageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"><form class="modal-content" id="knowledgePageForm">
    <div class="modal-header"><div><h5 class="modal-title"><?= htmlspecialchars($t('knowledge.create_page_title', 'Новая страница'), ENT_QUOTES, 'UTF-8') ?></h5><p class="text-muted mb-0 small"><?= htmlspecialchars($t('knowledge.create_page_hint', 'Сначала можно сохранить черновик, потом отправить на проверку или опубликовать.'), ENT_QUOTES, 'UTF-8') ?></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button></div>
    <div class="modal-body">
      <div class="row g-3">
        <div class="col-12"><label class="crm-filter-label" for="knowledgePageTitle"><?= htmlspecialchars($t('knowledge.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input id="knowledgePageTitle" class="form-control" name="title" required></div>
        <div class="col-md-6"><label class="crm-filter-label" for="knowledgePageSpace"><?= htmlspecialchars($t('knowledge.field_space', 'Раздел'), ENT_QUOTES, 'UTF-8') ?></label><select id="knowledgePageSpace" class="form-select" name="space_public_id"></select></div>
        <div class="col-md-6"><label class="crm-filter-label" for="knowledgePageType"><?= htmlspecialchars($t('knowledge.field_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></label><select id="knowledgePageType" class="form-select" name="page_type"><option value="article"><?= htmlspecialchars($t('knowledge.type_article', 'Статья'), ENT_QUOTES, 'UTF-8') ?></option><option value="instruction"><?= htmlspecialchars($t('knowledge.type_instruction', 'Инструкция'), ENT_QUOTES, 'UTF-8') ?></option><option value="regulation"><?= htmlspecialchars($t('knowledge.type_regulation', 'Регламент'), ENT_QUOTES, 'UTF-8') ?></option><option value="faq"><?= htmlspecialchars($t('knowledge.type_faq', 'FAQ'), ENT_QUOTES, 'UTF-8') ?></option><option value="checklist"><?= htmlspecialchars($t('knowledge.type_checklist', 'Чеклист'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
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
  var state = { spaces: [] };
  var els = {
    stats: document.getElementById('knowledgeStats'),
    spaces: document.getElementById('knowledgeSpaces'),
    recent: document.getElementById('knowledgeRecent'),
    review: document.getElementById('knowledgeReview'),
    popular: document.getElementById('knowledgePopular'),
    drafts: document.getElementById('knowledgeDrafts'),
    outdated: document.getElementById('knowledgeOutdated'),
    results: document.getElementById('knowledgeSearchResults'),
    search: document.getElementById('knowledgeSearchInput'),
    searchButton: document.getElementById('knowledgeSearchButton'),
    pageForm: document.getElementById('knowledgePageForm'),
    spaceForm: document.getElementById('knowledgeSpaceForm'),
    pageSpace: document.getElementById('knowledgePageSpace')
  };
  function getApi() {
    return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
  }
  function waitForApi(callback, attempts) {
    var api = getApi();
    if (api) {
      callback(api);
      return;
    }
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
  function renderList(target, items, emptyText) {
    if (!target) return;
    if (!items || !items.length) {
      target.innerHTML = '<div class="text-muted p-3">' + esc(emptyText) + '</div>';
      return;
    }
    target.innerHTML = items.map(function (item) {
      var statusBadge = '';
      if (item.status) {
        var sm = { draft: 'crm-badge-secondary', review: 'crm-badge-warning', published: 'crm-badge-success', archived: 'crm-badge-light', needs_update: 'crm-badge-danger' };
        statusBadge = '<span class="crm-badge ' + (sm[item.status] || 'crm-badge-secondary') + '" style="font-size:0.7rem;padding:0.15rem 0.4rem;margin-left:0.5rem">' + esc(item.status) + '</span>';
      }
      return '<a class="crm-knowledge-list-item" href="' + esc(pageUrl(item)) + '"><span><strong>' + esc(item.title) + statusBadge + '</strong><small>' + esc(item.space_title || '') + '</small></span><i class="fa-solid fa-chevron-right"></i></a>';
    }).join('');
  }
  function renderSpaces(items) {
    state.spaces = items || [];
    if (els.pageSpace) {
      els.pageSpace.innerHTML = state.spaces.map(function (space) {
        return '<option value="' + esc(space.public_id) + '">' + esc(space.title) + '</option>';
      }).join('');
    }
    if (!els.spaces) return;
    if (!state.spaces.length) {
      els.spaces.innerHTML = '<div class="text-muted p-3">' + esc(t('knowledge.empty_spaces', 'Разделов пока нет.')) + '</div>';
      return;
    }
    els.spaces.innerHTML = state.spaces.map(function (space) {
      return '<button type="button" class="crm-knowledge-space" data-space="' + esc(space.public_id) + '"><span class="crm-knowledge-space-mark"></span><span><strong>' + esc(space.title) + '</strong><small>' + esc(space.description || t('knowledge.no_description', 'Без описания')) + '</small></span><em>' + esc(space.pages_count || 0) + '</em></button>';
    }).join('');
  }
  function renderStats(totals) {
    if (!els.stats) return;
    var values = [totals.spaces || 0, totals.pages || 0, totals.published || 0, totals.drafts || 0];
    els.stats.querySelectorAll('strong').forEach(function (node, index) { node.textContent = String(values[index] || 0); });
  }
  async function load() {
    try {
      var envelope = await request('api/v1/knowledge/overview', { method: 'GET' });
      var data = envelope.data || {};
      renderStats(data.totals || {});
      renderSpaces(data.spaces || []);
      renderList(els.recent, data.recent || [], t('knowledge.empty_recent', 'Пока нет страниц.'));
      renderList(els.review, data.review_queue || [], t('knowledge.empty_review', 'Нет страниц на проверке.'));
      renderList(els.popular, data.popular || [], t('knowledge.empty_popular', 'Популярных страниц пока нет.'));
      renderList(els.drafts, data.drafts || [], t('knowledge.empty_drafts', 'Нет черновиков.'));
      renderList(els.outdated, data.outdated || [], t('knowledge.empty_outdated', 'Нет устаревших страниц.'));
    } catch (err) {
      renderList(els.recent, [], t('knowledge.load_error', 'Не удалось загрузить базу знаний.'));
    }
  }
  async function search() {
    var query = (els.search && els.search.value || '').trim();
    if (!query) {
      renderList(els.results, [], t('knowledge.search_empty_hint', 'Введите запрос, чтобы найти материалы.'));
      return;
    }
    try {
      var envelope = await request('api/v1/knowledge/search', { method: 'GET', query: { q: query } });
      renderList(els.results, envelope.data && envelope.data.items || [], t('knowledge.search_empty', 'Ничего не найдено.'));
    } catch (err) {
      renderList(els.results, [], t('knowledge.search_error', 'Не удалось выполнить поиск.'));
      if (window.console && console.warn) console.warn('[CRM] Knowledge search failed', err);
    }
  }
  var debouncedSearch = window.CRM && CRM.debounce ? CRM.debounce(search, 350) : search;
  function formPayload(form) {
    var data = {};
    new FormData(form).forEach(function (value, key) { data[key] = value; });
    return data;
  }
  document.querySelectorAll('[data-knowledge-open-page]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.bootstrap && bootstrap.Modal.getOrCreateInstance(document.getElementById('knowledgePageModal')).show(); });
  });
  document.querySelectorAll('[data-knowledge-open-space]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.bootstrap && bootstrap.Modal.getOrCreateInstance(document.getElementById('knowledgeSpaceModal')).show(); });
  });
  if (els.search) {
    els.search.addEventListener('input', debouncedSearch);
    els.search.addEventListener('change', search);
    els.search.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        search();
      }
    });
  }
  if (els.searchButton) els.searchButton.addEventListener('click', search);
  if (els.pageForm) els.pageForm.addEventListener('submit', async function (event) {
    event.preventDefault();
    var envelope = await request('api/v1/knowledge/pages', { method: 'POST', body: formPayload(els.pageForm), idempotent: true });
    var page = envelope.data && envelope.data.page;
    if (page && page.public_id) window.location.href = pageUrl(page);
  });
  if (els.spaceForm) els.spaceForm.addEventListener('submit', async function (event) {
    event.preventDefault();
    await request('api/v1/knowledge/spaces', { method: 'POST', body: formPayload(els.spaceForm), idempotent: true });
    window.bootstrap && bootstrap.Modal.getOrCreateInstance(document.getElementById('knowledgeSpaceModal')).hide();
    els.spaceForm.reset();
    load();
  });
  waitForApi(load);
})();
</script>
</body>
