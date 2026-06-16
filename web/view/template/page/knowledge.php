<?php declare(strict_types=1); ?>
<?php $title = $t('knowledge.title', 'TropaTT — База знаний'); ?>
<body data-page="knowledge" data-protected="1">
<div class="crm-app">
  <aside class="crm-sidebar">
    <div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div>
    <nav class="nav flex-column crm-nav"></nav>
  </aside>
  <div class="crm-main-wrap">
    <header class="crm-topbar py-2"><div class="container-fluid"></div></header>

    <main class="crm-content crm-knowledge-page">
      <div class="crm-page-head">
        <div>
          <h1 class="crm-page-title" data-i18n="knowledge.page_title"><?= htmlspecialchars($t('knowledge.page_title', 'База знаний'), ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="crm-subtitle mb-0" data-i18n="knowledge.subtitle"><?= htmlspecialchars($t('knowledge.subtitle', 'Регламенты, инструкции, FAQ и проектные знания команды.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="crm-page-actions d-flex gap-2 flex-wrap">
          <a class="btn crm-btn-secondary btn-sm" href="index.php?route=admin-knowledge"><i class="fa-solid fa-gear" aria-hidden="true"></i> <?= htmlspecialchars($t('knowledge.admin_link', 'Настройки'), ENT_QUOTES, 'UTF-8') ?></a>
          <button class="btn crm-btn-secondary btn-sm" type="button" id="btnCreateSpace"><i class="fa-solid fa-folder-plus" aria-hidden="true"></i> <?= htmlspecialchars($t('knowledge.btn_create_space', 'Создать раздел'), ENT_QUOTES, 'UTF-8') ?></button>
          <button class="btn crm-btn-primary btn-sm" type="button" id="btnCreatePage"><i class="fa-regular fa-file-lines" aria-hidden="true"></i> <?= htmlspecialchars($t('knowledge.btn_create_page', 'Создать страницу'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </div>

  <!-- ─── SEARCH & FILTERS ─── -->
  <div class="crm-card crm-section-card mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2 kb-filter-bar">
      <div class="flex-grow-1" style="min-width:220px">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-transparent border-end-0"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
           <input id="kbSearch" class="form-control border-start-0" type="search" placeholder="<?= htmlspecialchars($t('knowledge.search_placeholder', 'Поиск по статьям и разделам...'), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($t('knowledge.search_aria', 'Поиск'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <select id="kbFilterSpace" class="form-select form-select-sm" style="width:auto;min-width:130px">
          <option value=""><?= htmlspecialchars($t('knowledge.filter_all_spaces', 'Все разделы'), ENT_QUOTES, 'UTF-8') ?></option>
        </select>
        <select id="kbFilterType" class="form-select form-select-sm" style="width:auto;min-width:120px">
          <option value=""><?= htmlspecialchars($t('knowledge.filter_all_types', 'Все типы'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="article"><?= htmlspecialchars($t('knowledge.type_article', 'Статья'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="instruction"><?= htmlspecialchars($t('knowledge.type_instruction', 'Инструкция'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="regulation"><?= htmlspecialchars($t('knowledge.type_regulation', 'Регламент'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="faq"><?= htmlspecialchars($t('knowledge.type_faq', 'FAQ'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="checklist"><?= htmlspecialchars($t('knowledge.type_checklist', 'Чек-лист'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="runbook"><?= htmlspecialchars($t('knowledge.type_runbook', 'Runbook'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="meeting_note"><?= htmlspecialchars($t('knowledge.type_meeting_note', 'Протокол'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="decision"><?= htmlspecialchars($t('knowledge.type_decision', 'Решение'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="client_note"><?= htmlspecialchars($t('knowledge.type_client_note', 'Заметка клиента'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="project_note"><?= htmlspecialchars($t('knowledge.type_project_note', 'Заметка проекта'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="onboarding"><?= htmlspecialchars($t('knowledge.type_onboarding', 'Онбординг'), ENT_QUOTES, 'UTF-8') ?></option>
        </select>
        <select id="kbFilterTag" class="form-select form-select-sm" style="width:auto;min-width:110px">
          <option value=""><?= htmlspecialchars($t('knowledge.filter_all_tags', 'Все теги'), ENT_QUOTES, 'UTF-8') ?></option>
        </select>
        <select id="kbFilterStatus" class="form-select form-select-sm" style="width:auto;min-width:130px">
          <option value=""><?= htmlspecialchars($t('knowledge.filter_all_statuses', 'Все статусы'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="published"><?= htmlspecialchars($t('knowledge.status_published', 'Опубликовано'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="draft"><?= htmlspecialchars($t('knowledge.status_draft', 'Черновик'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="review"><?= htmlspecialchars($t('knowledge.status_review', 'На проверке'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="needs_update"><?= htmlspecialchars($t('knowledge.status_needs_update', 'Требует обновления'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="archived"><?= htmlspecialchars($t('knowledge.status_archived', 'Архив'), ENT_QUOTES, 'UTF-8') ?></option>
        </select>
        <button id="kbFilterReset" class="btn btn-sm btn-outline-secondary" type="button" style="display:none"><i class="fa-solid fa-xmark" aria-hidden="true"></i> <?= htmlspecialchars($t('knowledge.filter_reset', 'Сбросить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>

  <!-- ─── MAIN CONTENT ─── -->
  <div class="container-fluid">
    <div class="row g-3 kb-workspace">

      <!-- LEFT: Spaces tree -->
      <div class="col-12 col-xl-4">
        <div class="kb-panel kb-spaces-panel">
          <div class="kb-panel-head d-flex align-items-center justify-content-between mb-2">
            <h2 class="h6 mb-0 fw-bold"><?= htmlspecialchars($t('knowledge.spaces_heading', 'Разделы'), ENT_QUOTES, 'UTF-8') ?></h2>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="btnAddSpace"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
          </div>
          <div id="kbSpaces" class="kb-spaces-list">
            <div class="text-muted small p-2"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
      </div>

      <!-- CENTER: Articles list -->
      <div class="col-12 col-xl-8">
        <div class="kb-panel kb-center-panel">
          <!-- Space header -->
          <div id="kbSpaceHeader" class="kb-space-header">
            <div class="d-flex align-items-start gap-3">
              <div class="kb-space-icon"><i class="fa-regular fa-folder-open"></i></div>
              <div class="flex-grow-1">
                <h2 class="h5 mb-1 fw-bold" id="kbSpaceTitle"><?= htmlspecialchars($t('knowledge.space_header_title', 'Выберите раздел'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="text-muted small mb-0" id="kbSpaceDesc"><?= htmlspecialchars($t('knowledge.space_header_desc', 'Выберите раздел слева для просмотра страниц'), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="d-flex align-items-center gap-3 mt-1 text-muted small" id="kbSpaceMeta">
                  <span><i class="fa-regular fa-file-lines" aria-hidden="true"></i> <span id="kbSpaceCount">0</span> <?= htmlspecialchars($t('knowledge.stat_pages', 'страниц'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Tabs -->
          <ul class="nav nav-tabs kb-tabs" id="kbTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" data-status="" type="button"><?= htmlspecialchars($t('knowledge.tab_all', 'Все'), ENT_QUOTES, 'UTF-8') ?></button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-status="published" type="button"><?= htmlspecialchars($t('knowledge.tab_published', 'Опубликованные'), ENT_QUOTES, 'UTF-8') ?> <span class="kb-tab-badge kb-tab-published" id="kbTabPublished">0</span></button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-status="draft" type="button"><?= htmlspecialchars($t('knowledge.tab_drafts', 'Черновики'), ENT_QUOTES, 'UTF-8') ?> <span class="kb-tab-badge kb-tab-draft" id="kbTabDrafts">0</span></button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-status="review" type="button"><?= htmlspecialchars($t('knowledge.tab_review', 'На проверке'), ENT_QUOTES, 'UTF-8') ?> <span class="kb-tab-badge kb-tab-review" id="kbTabReview">0</span></button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-status="needs_update" type="button"><?= htmlspecialchars($t('knowledge.tab_outdated', 'Требуют обновления'), ENT_QUOTES, 'UTF-8') ?> <span class="kb-tab-badge kb-tab-outdated" id="kbTabOutdated">0</span></button>
            </li>
          </ul>

          <!-- Articles table -->
          <div class="kb-articles-wrap">
            <table class="table table-hover align-middle mb-0" id="kbArticlesTable">
              <thead>
                <tr>
                  <th style="width:42%" scope="col"><?= htmlspecialchars($t('knowledge.th_page', 'Страница'), ENT_QUOTES, 'UTF-8') ?></th>
                  <th class="d-none d-md-table-cell" scope="col"><?= htmlspecialchars($t('knowledge.th_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></th>
                  <th scope="col"><?= htmlspecialchars($t('knowledge.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th>
                  <th class="d-none d-md-table-cell" scope="col"><?= htmlspecialchars($t('knowledge.th_updated', 'Обновлено'), ENT_QUOTES, 'UTF-8') ?></th>
                  <th class="d-none d-lg-table-cell" scope="col" style="width:50px"><?= htmlspecialchars($t('knowledge.th_views', 'Просм.'), ENT_QUOTES, 'UTF-8') ?></th>
                </tr>
              </thead>
              <tbody id="kbArticlesBody">
                <tr><td colspan="5" class="text-muted small text-center py-4"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top small text-muted" id="kbPagination">
            <span id="kbPagInfo"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></span>
            <div class="d-flex align-items-center gap-2">
              <button class="btn btn-sm btn-outline-secondary" type="button" id="kbPagPrev" disabled><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
              <span id="kbPagPage">1</span>
              <button class="btn btn-sm btn-outline-secondary" type="button" id="kbPagNext"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- ─── BOTTOM: Recent ─── -->
    <div class="row g-3 mt-1">

      <!-- Recently updated -->
      <div class="col-12">
        <div class="crm-card crm-section-card p-3">
          <h3 class="h6 mb-3 fw-bold"><i class="fa-regular fa-clock text-muted me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('knowledge.recent_heading', 'Недавно обновлено'), ENT_QUOTES, 'UTF-8') ?></h3>
          <div id="kbRecentList" class="kb-side-list"></div>
        </div>
      </div>

    </div>
  </div>
</main>
</div></div>

<!-- ─── MODALS ─── -->
<div class="modal fade" id="kbPageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= htmlspecialchars($t('knowledge.modal_new_page', 'Новая страница'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('knowledge.modal_close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="kbPageTitleInput" class="form-label fw-semibold"><?= htmlspecialchars($t('knowledge.modal_label_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label>
          <input id="kbPageTitleInput" class="form-control" type="text" required>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="kbPageSpace" class="form-label fw-semibold"><?= htmlspecialchars($t('knowledge.modal_label_space', 'Раздел'), ENT_QUOTES, 'UTF-8') ?></label>
            <select id="kbPageSpace" class="form-select"></select>
          </div>
          <div class="col-md-6">
            <label for="kbPageType" class="form-label fw-semibold"><?= htmlspecialchars($t('knowledge.modal_label_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></label>
            <select id="kbPageType" class="form-select">
              <option value="article"><?= htmlspecialchars($t('knowledge.type_article', 'Статья'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="instruction"><?= htmlspecialchars($t('knowledge.type_instruction', 'Инструкция'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="regulation"><?= htmlspecialchars($t('knowledge.type_regulation', 'Регламент'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="faq"><?= htmlspecialchars($t('knowledge.type_faq', 'FAQ'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="checklist"><?= htmlspecialchars($t('knowledge.type_checklist', 'Чек-лист'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="runbook"><?= htmlspecialchars($t('knowledge.type_runbook', 'Runbook'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="meeting_note"><?= htmlspecialchars($t('knowledge.type_meeting_note', 'Протокол'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="decision"><?= htmlspecialchars($t('knowledge.type_decision', 'Решение'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="client_note"><?= htmlspecialchars($t('knowledge.type_client_note', 'Заметка клиента'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="project_note"><?= htmlspecialchars($t('knowledge.type_project_note', 'Заметка проекта'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="onboarding"><?= htmlspecialchars($t('knowledge.type_onboarding', 'Онбординг'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label for="kbPageContent" class="form-label fw-semibold"><?= htmlspecialchars($t('knowledge.modal_label_content', 'Содержание'), ENT_QUOTES, 'UTF-8') ?></label>
          <textarea id="kbPageContent" class="form-control" rows="6" placeholder="<?= htmlspecialchars($t('knowledge.modal_content_placeholder', 'Опишите решение, шаги, правила...'), ENT_QUOTES, 'UTF-8') ?>"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($t('knowledge.modal_cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn crm-btn-primary" id="kbPageSubmit"><?= htmlspecialchars($t('knowledge.btn_save_draft', 'Создать черновик'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="kbSpaceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= htmlspecialchars($t('knowledge.modal_new_space', 'Новый раздел'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('knowledge.modal_close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="kbSpaceTitleInput" class="form-label fw-semibold"><?= htmlspecialchars($t('knowledge.modal_label_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label>
          <input id="kbSpaceTitleInput" class="form-control" type="text" required>
        </div>
        <div class="mb-3">
          <label for="kbSpaceParent" class="form-label fw-semibold"><?= htmlspecialchars($t('knowledge.modal_label_parent', 'Родительский раздел'), ENT_QUOTES, 'UTF-8') ?></label>
          <select id="kbSpaceParent" class="form-select">
            <option value=""><?= htmlspecialchars($t('knowledge.modal_no_parent', 'Без родителя (корневой)'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
        </div>
        <div class="mb-3">
          <label for="kbSpaceDescInput" class="form-label fw-semibold"><?= htmlspecialchars($t('knowledge.modal_label_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label>
          <textarea id="kbSpaceDescInput" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($t('knowledge.modal_cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-success" id="kbSpaceSubmit"><?= htmlspecialchars($t('knowledge.modal_create', 'Создать'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  function getApi(){ return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null; }
  function waitForApi(cb, n){
    if(getApi()){ cb(); return; }
    if((n||0)>80){ console.error('CRM API not ready'); return; }
    setTimeout(function(){ waitForApi(cb, (n||0)+1); }, 50);
  }
  function req(route, opts){
    var api = getApi();
    if(!api){ return Promise.reject(new Error('CRM API not ready')); }
    return api.request(route, opts);
  }
  function esc(s){ var d=document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }
  var _t = window.CRM && window.CRM.i18n ? window.CRM.i18n.t.bind(window.CRM.i18n) : function(k,f){return f;};

  var state = { spaces:[], activeSpace:'', activeStatus:'' };
  var flatSpaces = [];

  /* ── Cookie helpers ── */
  function getCookie(name){
    var m = document.cookie.match(new RegExp('(?:^|; )'+name+'=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : '';
  }
  function setCookie(name, val, days){
    var d = new Date(); d.setTime(d.getTime()+(days||30)*864e5);
    document.cookie = name+'='+encodeURIComponent(val)+';expires='+d.toUTCString()+';path=/';
  }
  function getOpenSpaces(){
    try { return JSON.parse(getCookie('kb_open_spaces')); } catch(e){ return []; }
  }
  function saveOpenSpaces(ids){
    setCookie('kb_open_spaces', JSON.stringify(ids), 30);
  }

  function flattenSpaces(tree, result){
    result = result || [];
    if(!tree) return result;
    tree.forEach(function(s){
      result.push(s);
      if(s.children && s.children.length) flattenSpaces(s.children, result);
    });
    return result;
  }

  function toggleSpaceNode(id){
    var toggle = document.querySelector('.kb-space-toggle[data-toggle="'+id+'"]');
    var ch = document.querySelector('[data-children="'+id+'"]');
    if(!toggle || !ch) return;
    toggle.classList.toggle('is-open');
    ch.classList.toggle('kb-collapsed');
    var openIds = [];
    document.querySelectorAll('.kb-space-toggle.is-open[data-toggle]').forEach(function(t){
      openIds.push(t.getAttribute('data-toggle'));
    });
    saveOpenSpaces(openIds);
  }

  /* ── Load Spaces Tree ── */
  async function loadSpaces(){
    try {
      var r = await req('api/v1/knowledge/spaces-tree', {method:'GET'});
      state.spaces = r.data && r.data.items || [];
      flatSpaces = flattenSpaces(state.spaces);
      renderSpaces();
    } catch(e){ console.warn('loadSpaces',e); }
  }

  function renderSpaces(){
    var el = document.getElementById('kbSpaces');
    var sel = document.getElementById('kbFilterSpace');
    if(!el) return;

    if(sel){
      sel.innerHTML = '<option value="">' + esc(_t('knowledge.filter_all_spaces', 'Все разделы')) + '</option>' + flatSpaces.map(function(s){
        return '<option value="'+esc(s.public_id)+'">'+esc(s.title)+'</option>';
      }).join('');
    }

    var html = '<a href="javascript:void(0)" class="kb-space-item'+(!state.activeSpace?' active':'')+'" data-space="">'
      +'<span class="kb-space-name">' + esc(_t('knowledge.all_spaces', 'Все разделы')) + '</span>'
      +'<span class="kb-space-count">'+flatSpaces.length+'</span></a>';

    html += renderSpaceTree(state.spaces, 0);
    el.innerHTML = html;
  }

  function renderSpaceTree(nodes, depth){
    if(!nodes || !nodes.length) return '';
    var openIds = getOpenSpaces();
    var h = '';
    nodes.forEach(function(s){
      var active = state.activeSpace === s.public_id ? ' active' : '';
      var hasKids = s.children && s.children.length;
      var isOpen = openIds.indexOf(s.public_id) !== -1;
      var indent = depth > 0 ? ' style="padding-left:'+(depth*16)+'px"' : '';
      h += '<div class="kb-space-row"'+indent+'>';
      if(hasKids) h += '<button type="button" class="kb-space-toggle'+(isOpen?' is-open':'')+'" data-toggle="'+esc(s.public_id)+'"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>';
      else h += '<span class="kb-space-toggle" style="visibility:hidden"></span>';
      h += '<a href="javascript:void(0)" class="kb-space-item'+active+'" data-space="'+esc(s.public_id)+'"'+(hasKids?' data-has-children="'+esc(s.public_id)+'"':'')+'>'
        +'<span class="kb-space-name">'+esc(s.title)+'</span>'
        +'<span class="kb-space-count">'+(s.pages_count||0)+'</span></a></div>';
      if(hasKids) h += '<div class="kb-space-children'+(isOpen?'':' kb-collapsed')+'" data-children="'+esc(s.public_id)+'">'+renderSpaceTree(s.children, depth+1)+'</div>';
    });
    return h;
  }

  function selectSpace(id){
    state.activeSpace = id;
    document.querySelectorAll('.kb-space-item').forEach(function(el){
      el.classList.toggle('active', el.getAttribute('data-space')===id);
    });
    document.getElementById('kbFilterSpace').value = id;
    updateSpaceHeader();
    loadArticles();
  }

  function updateSpaceHeader(){
    var titleEl = document.getElementById('kbSpaceTitle');
    var descEl = document.getElementById('kbSpaceDesc');
    var countEl = document.getElementById('kbSpaceCount');
    if(!state.activeSpace){
      titleEl.textContent = _t('knowledge.space_header_title', 'Выберите раздел');
      descEl.textContent = _t('knowledge.space_header_desc', 'Выберите раздел слева для просмотра страниц');
      countEl.textContent = '0';
      return;
    }
    var s = flatSpaces.find(function(x){return x.public_id===state.activeSpace;});
    if(!s) return;
    titleEl.textContent = s.title;
    descEl.textContent = s.description || '';
    countEl.textContent = s.pages_count || 0;
  }

  /* ── Load Tags ── */
  async function loadTags(){
    try {
      var r = await req('api/v1/tags', {method:'GET'});
      var items = r.data && r.data.items || [];
      var sel = document.getElementById('kbFilterTag');
      if(!sel) return;
      sel.innerHTML = '<option value="">' + esc(_t('knowledge.filter_all_tags', 'Все теги')) + '</option>' + items.map(function(t){
        return '<option value="'+esc(t.public_id)+'">'+esc(t.title)+'</option>';
      }).join('');
    } catch(e){}
  }

  /* ── Load Articles ── */
  async function loadArticles(){
    var body = document.getElementById('kbArticlesBody');
    if(!body) return;
    body.innerHTML = '<tr><td colspan="5" class="text-muted small text-center py-4">' + esc(_t('knowledge.loading', 'Загрузка...')) + '</td></tr>';

    var baseParams = {};
    if(state.activeSpace) baseParams.space_public_id = state.activeSpace;
    var tf = document.getElementById('kbFilterType').value;
    var tg = document.getElementById('kbFilterTag').value;
    var q = document.getElementById('kbSearch').value.trim();
    if(tf) baseParams.page_type = tf;
    if(tg) baseParams.tag_public_id = tg;
    if(q) baseParams.q = q;

    try {
      var r = await req('api/v1/knowledge/search', {method:'GET', query:baseParams});
      var allItems = r.data && r.data.items || [];
      updateTabs(allItems);
      var countEl = document.getElementById('kbSpaceCount');
      if(countEl) countEl.textContent = allItems.length;
      var sf = state.activeStatus || document.getElementById('kbFilterStatus').value;
      var filtered = sf ? allItems.filter(function(p){ return p.status === sf; }) : allItems;
      renderArticles(filtered);
      updatePagInfo(filtered);
    } catch(e){ body.innerHTML='<tr><td colspan="5" class="text-muted small text-center py-4">' + esc(_t('knowledge.error_loading', 'Ошибка загрузки')) + '</td></tr>'; }
  }

  function renderArticles(items){
    var body = document.getElementById('kbArticlesBody');
    if(!items.length){
      body.innerHTML = '<tr><td colspan="5"><div class="kb-empty-state"><i class="fa-regular fa-folder-open"></i><p>' + esc(_t('knowledge.empty_no_pages', 'В этом разделе пока нет страниц')) + '</p><button class="btn crm-btn-primary btn-sm" id="kbEmptyCreate"><i class="fa-solid fa-plus" aria-hidden="true"></i> ' + esc(_t('knowledge.btn_create_page', 'Создать страницу')) + '</button></div></td></tr>';
      var eb = document.getElementById('kbEmptyCreate');
      if(eb) eb.addEventListener('click', function(){ document.getElementById('btnCreatePage').click(); });
      return;
    }
    var statusMap = {published:'kb-status-published',draft:'kb-status-draft',review:'kb-status-review',needs_update:'kb-status-needs_update',archived:'kb-status-archived'};
    var statusLabels = {published:_t('knowledge.status_published','Опубликовано'),draft:_t('knowledge.status_draft','Черновик'),review:_t('knowledge.status_review','На проверке'),needs_update:_t('knowledge.status_needs_update','Требует обновления'),archived:_t('knowledge.status_archived','Архив')};
    var typeLabels = {article:_t('knowledge.type_article','Статья'),instruction:_t('knowledge.type_instruction','Инструкция'),regulation:_t('knowledge.type_regulation','Регламент'),faq:_t('knowledge.type_faq','FAQ'),checklist:_t('knowledge.type_checklist','Чек-лист'),runbook:_t('knowledge.type_runbook','Runbook'),meeting_note:_t('knowledge.type_meeting_note','Протокол'),decision:_t('knowledge.type_decision','Решение'),client_note:_t('knowledge.type_client_note','Заметка клиента'),project_note:_t('knowledge.type_project_note','Заметка проекта'),onboarding:_t('knowledge.type_onboarding','Онбординг')};
    var typeIcons = {article:'fa-regular fa-file-lines',instruction:'fa-solid fa-list-check',regulation:'fa-solid fa-shield-halved',faq:'fa-regular fa-circle-question',checklist:'fa-regular fa-square-check',runbook:'fa-solid fa-book',meeting_note:'fa-regular fa-calendar-check',decision:'fa-solid fa-gavel',client_note:'fa-regular fa-address-card',project_note:'fa-solid fa-diagram-project',onboarding:'fa-solid fa-user-plus'};

    body.innerHTML = items.map(function(p){
      var st = statusMap[p.status]||'kb-status-draft';
      var stLabel = statusLabels[p.status]||p.status||'';
      var typeLabel = typeLabels[p.page_type]||p.page_type||'';
      var typeIcon = typeIcons[p.page_type]||'fa-regular fa-file';
      var desc = p.content_text ? p.content_text.substring(0,60) : '';
      var date = p.updated_at ? p.updated_at.substring(0,10) : '';
      return '<tr class="kb-article-row" data-id="'+esc(p.public_id)+'">'
        +'<td><div class="d-flex align-items-center gap-2"><i class="'+typeIcon+' text-muted" aria-hidden="true"></i><div class="min-w-0"><div class="kb-article-title text-truncate">'+esc(p.title)+'</div>'+(desc?'<div class="kb-article-desc">'+esc(desc)+'</div>':'')+'</div></div></td>'
        +'<td class="d-none d-md-table-cell"><span class="kb-type-badge">'+esc(typeLabel)+'</span></td>'
        +'<td><span class="kb-status-badge '+st+'">'+esc(stLabel)+'</span></td>'
        +'<td class="d-none d-md-table-cell text-muted kb-col-date">'+esc(date)+'</td>'
        +'<td class="d-none d-lg-table-cell text-muted kb-col-views">'+esc(p.views_count||0)+'</td>'
        +'</tr>';
    }).join('');

    body.querySelectorAll('.kb-article-row').forEach(function(row){
      row.addEventListener('click', function(){
        window.location.href = 'index.php?route=knowledge-page&id='+this.getAttribute('data-id');
      });
    });
  }

  function updateTabs(items){
    var counts = {published:0,draft:0,review:0,needs_update:0,archived:0};
    items.forEach(function(p){ if(counts[p.status]!==undefined) counts[p.status]++; });
    document.getElementById('kbTabPublished').textContent = counts.published;
    document.getElementById('kbTabDrafts').textContent = counts.draft;
    document.getElementById('kbTabReview').textContent = counts.review;
    document.getElementById('kbTabOutdated').textContent = counts.needs_update;
  }

  function updatePagInfo(items){
    var el = document.getElementById('kbPagInfo');
    if(el) el.textContent = items.length + ' ' + declension(items.length, _t('knowledge.pages_count_one', 'страница'), _t('knowledge.pages_count_few', 'страницы'), _t('knowledge.pages_count_many', 'страниц'));
  }

  function declension(n, one, few, many){
    var abs = Math.abs(n) % 100;
    var last = abs % 10;
    if(abs > 10 && abs < 20) return many;
    if(last > 1 && last < 5) return few;
    if(last === 1) return one;
    return many;
  }

  /* ── Load Recent ── */
  async function loadRecent(){
    try {
      var r = await req('api/v1/knowledge/search', {method:'GET', query:{sort:'updated_at',order:'desc',limit:4}});
      var items = r.data && r.data.items || [];
      var el = document.getElementById('kbRecentList');
      if(!el) return;
      if(!items.length){ el.innerHTML='<div class="text-muted small p-2">' + esc(_t('knowledge.empty_no_data', 'Нет данных')) + '</div>'; return; }
      el.innerHTML = items.map(function(p){
        return '<a href="index.php?route=knowledge-page&id='+esc(p.public_id)+'" class="kb-side-item">'
          +'<i class="fa-regular fa-file-lines" aria-hidden="true"></i>'
          +'<div class="kb-side-item-text"><span class="kb-side-item-title">'+esc(p.title)+'</span>'
          +'<span class="kb-side-item-meta">'+esc(p.space_title||'')+' · '+esc(p.updated_at?p.updated_at.substring(0,10):'')+'</span></div></a>';
      }).join('');
    } catch(e){}
  }

  /* ── Events ── */

  /* 1. Space tree: click on name toggles children AND selects space */
  document.getElementById('kbSpaces').addEventListener('click', function(e){
    var toggle = e.target.closest('.kb-space-toggle[data-toggle]');
    if(toggle){
      e.stopPropagation();
      toggleSpaceNode(toggle.getAttribute('data-toggle'));
      return;
    }
    var item = e.target.closest('.kb-space-item[data-space]');
    if(item){
      e.preventDefault();
      var spaceId = item.getAttribute('data-space');
      selectSpace(spaceId);
      var childAttr = item.getAttribute('data-has-children');
      if(childAttr) toggleSpaceNode(childAttr);
    }
  });

  /* 2. btnAddSpace → same modal as btnCreateSpace */
  document.getElementById('btnAddSpace').addEventListener('click', function(){
    document.getElementById('btnCreateSpace').click();
  });

  /* Tabs */
  document.getElementById('kbTabs').addEventListener('click', function(e){
    var btn = e.target.closest('[data-status]');
    if(!btn) return;
    document.querySelectorAll('#kbTabs .nav-link').forEach(function(n){n.classList.remove('active');});
    btn.classList.add('active');
    state.activeStatus = btn.getAttribute('data-status')||'';
    document.getElementById('kbFilterStatus').value = state.activeStatus;
    checkFilters();
    loadArticles();
  });

  /* 7. Filter reset visibility */
  function checkFilters(){
    var active = document.getElementById('kbSearch').value.trim()
      || document.getElementById('kbFilterStatus').value
      || document.getElementById('kbFilterType').value
      || document.getElementById('kbFilterTag').value
      || document.getElementById('kbFilterSpace').value
      || state.activeStatus;
    document.getElementById('kbFilterReset').style.display = active ? '' : 'none';
  }

  /* Search */
  document.getElementById('kbSearch').addEventListener('input', function(){
    clearTimeout(this._t);
    this._t = setTimeout(function(){ checkFilters(); loadArticles(); }, 300);
  });

  /* Filters */
  document.getElementById('kbFilterStatus').addEventListener('change', function(){
    state.activeStatus = this.value;
    document.querySelectorAll('#kbTabs .nav-link').forEach(function(n){
      n.classList.toggle('active', n.getAttribute('data-status')===state.activeStatus);
    });
    checkFilters();
    loadArticles();
  });
  document.getElementById('kbFilterType').addEventListener('change', function(){ checkFilters(); loadArticles(); });
  document.getElementById('kbFilterTag').addEventListener('change', function(){ checkFilters(); loadArticles(); });
  document.getElementById('kbFilterSpace').addEventListener('change', function(){
    state.activeSpace = this.value;
    updateSpaceHeader();
    checkFilters();
    loadArticles();
  });

  document.getElementById('kbFilterReset').addEventListener('click', function(){
    document.getElementById('kbSearch').value='';
    document.getElementById('kbFilterStatus').value='';
    document.getElementById('kbFilterType').value='';
    document.getElementById('kbFilterTag').value='';
    document.getElementById('kbFilterSpace').value='';
    document.querySelectorAll('.kb-filter-bar .crm-searchable-input').forEach(function(inp){
      inp.value='';
      var clear = inp.parentElement.querySelector('.crm-searchable-clear');
      if(clear) clear.style.display='none';
    });
    state.activeSpace='';
    document.querySelectorAll('#kbTabs .nav-link').forEach(function(n){
      n.classList.toggle('active', n.getAttribute('data-status')==='');
    });
    state.activeStatus='';
    updateSpaceHeader();
    checkFilters();
    loadArticles();
  });

  /* ── Modals ── */
  document.getElementById('btnCreatePage').addEventListener('click', function(){
    var sel = document.getElementById('kbPageSpace');
    sel.innerHTML = flatSpaces.map(function(s){ return '<option value="'+esc(s.public_id)+'">'+esc(s.title)+'</option>'; }).join('');
    new bootstrap.Modal(document.getElementById('kbPageModal')).show();
  });

  document.getElementById('btnCreateSpace').addEventListener('click', function(){
    var sel = document.getElementById('kbSpaceParent');
    sel.innerHTML = '<option value="">' + esc(_t('knowledge.no_parent', 'Без родителя')) + '</option>' + flatSpaces.map(function(s){ return '<option value="'+esc(s.public_id)+'">'+esc(s.title)+'</option>'; }).join('');
    document.getElementById('kbSpaceTitleInput').value='';
    document.getElementById('kbSpaceDescInput').value='';
    new bootstrap.Modal(document.getElementById('kbSpaceModal')).show();
  });

  document.getElementById('kbPageSubmit').addEventListener('click', async function(){
    var title = document.getElementById('kbPageTitleInput').value.trim();
    if(!title){ alert(_t('knowledge.alert_title_required', 'Укажите название')); return; }
    try {
      await req('api/v1/knowledge/pages', {method:'POST', body:{
        title: title,
        space_public_id: document.getElementById('kbPageSpace').value,
        page_type: document.getElementById('kbPageType').value,
        content_html: document.getElementById('kbPageContent').value
      }});
      bootstrap.Modal.getInstance(document.getElementById('kbPageModal')).hide();
      document.getElementById('kbPageTitleInput').value='';
      document.getElementById('kbPageContent').value='';
      loadArticles();
    } catch(e){ alert(_t('knowledge.alert_create_error', 'Ошибка создания')); }
  });

  document.getElementById('kbSpaceSubmit').addEventListener('click', async function(){
    var title = document.getElementById('kbSpaceTitleInput').value.trim();
    if(!title){ alert(_t('knowledge.alert_title_required', 'Укажите название')); return; }
    try {
      var body = {title: title, description: document.getElementById('kbSpaceDescInput').value};
      var parentId = document.getElementById('kbSpaceParent').value;
      if(parentId) body.parent_public_id = parentId;
      await req('api/v1/knowledge/spaces', {method:'POST', body: body});
      bootstrap.Modal.getInstance(document.getElementById('kbSpaceModal')).hide();
      await loadSpaces();
      if(parentId) selectSpace(parentId);
    } catch(e){ alert(_t('knowledge.alert_create_error', 'Ошибка создания')); }
  });

  /* ── Init ── */
  waitForApi(function(){
    loadSpaces();
    loadRecent();
    loadArticles();
    loadTags();
  });
})();
</script>
</body>
