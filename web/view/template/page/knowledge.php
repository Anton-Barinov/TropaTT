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
    <div class="d-flex flex-wrap align-items-center gap-2">
      <div class="flex-grow-1" style="min-width:220px">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-transparent border-end-0"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
          <input id="kbSearch" class="form-control border-start-0" type="search" placeholder="Поиск по статьям и разделам..." aria-label="Поиск">
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <select id="kbFilterSpace" class="form-select form-select-sm" style="width:auto;min-width:130px">
          <option value="">Все разделы</option>
        </select>
        <select id="kbFilterType" class="form-select form-select-sm" style="width:auto;min-width:120px">
          <option value="">Все типы</option>
          <option value="article">Статья</option>
          <option value="instruction">Инструкция</option>
          <option value="regulation">Регламент</option>
          <option value="faq">FAQ</option>
          <option value="checklist">Чек-лист</option>
          <option value="runbook">Runbook</option>
          <option value="meeting_note">Протокол</option>
          <option value="decision">Решение</option>
        </select>
        <select id="kbFilterTag" class="form-select form-select-sm" style="width:auto;min-width:110px">
          <option value="">Все теги</option>
        </select>
        <select id="kbFilterStatus" class="form-select form-select-sm" style="width:auto;min-width:130px">
          <option value="">Все статусы</option>
          <option value="published">Опубликовано</option>
          <option value="draft">Черновик</option>
          <option value="review">На проверке</option>
          <option value="needs_update">Требует обновления</option>
          <option value="archived">Архив</option>
        </select>
        <button id="kbFilterReset" class="btn btn-sm crm-btn-muted" type="button" style="display:none">Сбросить</button>
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
            <h2 class="h6 mb-0 fw-bold">Разделы</h2>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="btnAddSpace"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
          </div>
          <div id="kbSpaces" class="kb-spaces-list">
            <div class="text-muted small p-2">Загрузка...</div>
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
                <h2 class="h5 mb-1 fw-bold" id="kbSpaceTitle">Выберите раздел</h2>
                <p class="text-muted small mb-0" id="kbSpaceDesc">Выберите раздел слева для просмотра страниц</p>
                <div class="d-flex align-items-center gap-3 mt-1 text-muted small" id="kbSpaceMeta">
                  <span><i class="fa-regular fa-file-lines" aria-hidden="true"></i> <span id="kbSpaceCount">0</span> страниц</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Tabs -->
          <ul class="nav nav-tabs kb-tabs" id="kbTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" data-status="" type="button">Все</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-status="published" type="button">Опубликованные <span class="badge bg-success bg-opacity-10 text-success ms-1" id="kbTabPublished">0</span></button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-status="draft" type="button">Черновики <span class="badge bg-primary bg-opacity-10 text-primary ms-1" id="kbTabDrafts">0</span></button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-status="review" type="button">На проверке <span class="badge bg-warning bg-opacity-10 text-warning ms-1" id="kbTabReview">0</span></button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-status="needs_update" type="button">Требуют обновления <span class="badge bg-danger bg-opacity-10 text-danger ms-1" id="kbTabOutdated">0</span></button>
            </li>
          </ul>

          <!-- Articles table -->
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="kbArticlesTable">
              <thead>
                <tr class="text-muted small">
                  <th style="width:45%">Страница</th>
                  <th>Тип</th>
                  <th>Статус</th>
                  <th class="d-none d-md-table-cell">Обновлено</th>
                  <th class="d-none d-lg-table-cell" style="width:60px">Просм.</th>
                  <th style="width:40px"></th>
                </tr>
              </thead>
              <tbody id="kbArticlesBody">
                <tr><td colspan="6" class="text-muted small text-center py-4">Загрузка...</td></tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top small text-muted" id="kbPagination">
            <span id="kbPagInfo">0 статей</span>
            <div class="d-flex align-items-center gap-2">
              <button class="btn btn-sm btn-outline-secondary" type="button" id="kbPagPrev" disabled><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
              <span id="kbPagPage">1</span>
              <button class="btn btn-sm btn-outline-secondary" type="button" id="kbPagNext"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
            </div>
          </div>
        </div>
      </div>


    </div>

    <!-- ─── BOTTOM: Quick Links + Types + Recent ─── -->
    <div class="row g-3 mt-1">

      <!-- Quick links -->
      <div class="col-12 col-md-4">
        <div class="crm-card crm-section-card p-3">
          <h3 class="h6 mb-3 fw-bold">Быстрые ссылки</h3>
          <div class="d-flex flex-column" id="kbQuickLinks">
            <a href="#" class="kb-quick-link" data-status="draft">
              <i class="fa-regular fa-pen-to-square text-muted" aria-hidden="true"></i>
              <span>Черновики</span>
              <span class="badge bg-primary bg-opacity-10 text-primary ms-auto">0</span>
            </a>
            <a href="#" class="kb-quick-link" data-status="review">
              <i class="fa-regular fa-circle-check text-muted" aria-hidden="true"></i>
              <span>На проверке</span>
              <span class="badge bg-warning bg-opacity-10 text-warning ms-auto">0</span>
            </a>
            <a href="#" class="kb-quick-link" data-status="needs_update">
              <i class="fa-regular fa-clock text-muted" aria-hidden="true"></i>
              <span>Просрочено</span>
              <span class="badge bg-danger bg-opacity-10 text-danger ms-auto">0</span>
            </a>
            <a href="#" class="kb-quick-link" data-status="archived">
              <i class="fa-regular fa-eye text-muted" aria-hidden="true"></i>
              <span>Архив</span>
              <span class="badge bg-secondary bg-opacity-10 text-secondary ms-auto">0</span>
            </a>
          </div>
        </div>
      </div>

      <!-- Document types -->
      <div class="col-12 col-md-4">
        <div class="crm-card crm-section-card p-3">
          <h3 class="h6 mb-3 fw-bold">Типы документов</h3>
          <div class="d-flex flex-column" id="kbTypes">
            <div class="kb-type-row"><span><i class="fa-regular fa-file-lines text-muted" aria-hidden="true"></i> Регламент</span><span class="text-muted">0</span></div>
            <div class="kb-type-row"><span><i class="fa-regular fa-file-code text-muted" aria-hidden="true"></i> Инструкция</span><span class="text-muted">0</span></div>
            <div class="kb-type-row"><span><i class="fa-regular fa-file text-muted" aria-hidden="true"></i> Документ</span><span class="text-muted">0</span></div>
            <div class="kb-type-row"><span><i class="fa-solid fa-list-check text-muted" aria-hidden="true"></i> Чек-лист</span><span class="text-muted">0</span></div>
            <div class="kb-type-row"><span><i class="fa-regular fa-clone text-muted" aria-hidden="true"></i> Шаблон</span><span class="text-muted">0</span></div>
          </div>
        </div>
      </div>

      <!-- Recently updated -->
      <div class="col-12 col-md-4">
        <div class="crm-card crm-section-card p-3">
          <h3 class="h6 mb-3 fw-bold"><i class="fa-regular fa-clock text-muted me-1" aria-hidden="true"></i> Недавно обновлено</h3>
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
        <h5 class="modal-title">Новая страница</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold">Название</label>
          <input id="kbPageTitle" class="form-control" type="text" required>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Раздел</label>
            <select id="kbPageSpace" class="form-select"></select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Тип</label>
            <select id="kbPageType" class="form-select">
              <option value="article">Статья</option>
              <option value="instruction">Инструкция</option>
              <option value="regulation">Регламент</option>
              <option value="faq">FAQ</option>
              <option value="checklist">Чек-лист</option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Содержание</label>
          <textarea id="kbPageContent" class="form-control" rows="6" placeholder="Опишите решение, шаги, правила..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="button" class="btn btn-success" id="kbPageSubmit">Создать черновик</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="kbSpaceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Новый раздел</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold">Название</label>
          <input id="kbSpaceTitle" class="form-control" type="text" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Родительский раздел</label>
          <select id="kbSpaceParent" class="form-select">
            <option value="">Без родителя (корневой)</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Описание</label>
          <textarea id="kbSpaceDesc" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="button" class="btn btn-success" id="kbSpaceSubmit">Создать</button>
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

  var state = { spaces:[], activeSpace:'', activeStatus:'' };
  var flatSpaces = [];

  /* ── Cookie helpers for tree state ── */
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

    /* populate filter */
    if(sel){
      sel.innerHTML = '<option value="">Все разделы</option>' + flatSpaces.map(function(s){
        return '<option value="'+esc(s.public_id)+'">'+esc(s.title)+'</option>';
      }).join('');
    }

    /* build tree */
    var html = '<a href="javascript:void(0)" class="kb-space-item'+(!state.activeSpace?' active':'')+'" data-space="">'
      +'<span class="kb-space-name">Все разделы</span>'
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
      h += '<a href="javascript:void(0)" class="kb-space-item'+active+'" data-space="'+esc(s.public_id)+'">'
        +'<span class="kb-space-name">'+esc(s.title)+'</span>'
        +'<span class="kb-space-count">'+(s.pages_count||0)+'</span></a></div>';
      if(hasKids) h += '<div class="kb-space-children'+(isOpen?'':' kb-collapsed')+'" data-children="'+esc(s.public_id)+'">'+renderSpaceTree(s.children, depth+1)+'</div>';
    });
    return h;
  }

  /* ── Select Space ── */
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
      titleEl.textContent = 'Выберите раздел';
      descEl.textContent = 'Выберите раздел слева для просмотра страниц';
      countEl.textContent = '0';
      return;
    }
    var s = flatSpaces.find(function(x){return x.public_id===state.activeSpace;});
    if(!s) return;
    titleEl.textContent = s.title;
    descEl.textContent = s.description || '';
    countEl.textContent = s.pages_count || 0;
  }

  /* ── Load Articles ── */
  async function loadArticles(){
    var body = document.getElementById('kbArticlesBody');
    if(!body) return;
    body.innerHTML = '<tr><td colspan="6" class="text-muted small text-center py-4">Загрузка...</td></tr>';

    var params = {};
    if(state.activeSpace) params.space_public_id = state.activeSpace;
    var sf = document.getElementById('kbFilterStatus').value;
    var tf = document.getElementById('kbFilterType').value;
    var tg = document.getElementById('kbFilterTag').value;
    var q = document.getElementById('kbSearch').value.trim();
    if(sf) params.status = sf;
    if(tf) params.page_type = tf;
    if(tg) params.tag_public_id = tg;
    if(q) params.q = q;

    try {
      var r = await req('api/v1/knowledge/search', {method:'GET', query:params});
      var items = r.data && r.data.items || [];
      renderArticles(items);
      updateTabs(items);
    } catch(e){ body.innerHTML='<tr><td colspan="6" class="text-muted small text-center py-4">Ошибка загрузки</td></tr>'; }
  }

  function renderArticles(items){
    var body = document.getElementById('kbArticlesBody');
    if(!items.length){
      body.innerHTML = '<tr><td colspan="6"><div class="kb-empty-state"><i class="fa-regular fa-folder-open"></i><p>В этом разделе пока нет страниц</p><button class="btn btn-sm btn-success" onclick="document.getElementById(\'btnCreatePage\').click()"><i class="fa-solid fa-plus" aria-hidden="true"></i> Создать страницу</button></div></td></tr>';
      return;
    }
    var statusMap = {published:'kb-status-published',draft:'kb-status-draft',review:'kb-status-review',needs_update:'kb-status-needs_update',archived:'kb-status-archived'};
    var statusLabels = {published:'Опубликовано',draft:'Черновик',review:'На проверке',needs_update:'Требует обновления',archived:'Архив'};
    var typeLabels = {article:'Статья',instruction:'Инструкция',regulation:'Регламент',faq:'FAQ',checklist:'Чек-лист',runbook:'Runbook',meeting_note:'Протокол',decision:'Решение'};

    body.innerHTML = items.map(function(p){
      var st = statusMap[p.status]||'kb-status-draft';
      var stLabel = statusLabels[p.status]||p.status||'';
      var typeLabel = typeLabels[p.page_type]||p.page_type||'';
      var desc = p.content_text ? p.content_text.substring(0,80) : (p.title||'');
      var date = p.updated_at ? p.updated_at.substring(0,10) : '';
      return '<tr onclick="window.open(\'index.php?route=knowledge-page&id='+esc(p.public_id)+'\',\'_self\')">'
        +'<td><div class="d-flex align-items-center gap-2"><i class="fa-regular fa-file-lines text-muted" aria-hidden="true"></i><div><div class="kb-article-title text-truncate">'+esc(p.title)+'</div><div class="kb-article-desc">'+esc(desc)+'</div></div></div></td>'
        +'<td><span class="kb-type-badge">'+esc(typeLabel)+'</span></td>'
        +'<td><span class="kb-status-badge '+st+'">'+esc(stLabel)+'</span></td>'
        +'<td class="d-none d-md-table-cell text-muted">'+esc(date)+'</td>'
        +'<td class="d-none d-lg-table-cell text-muted">'+esc(p.views_count||0)+'</td>'
        +'<td><div class="kb-article-actions"><button class="btn btn-sm btn-light" type="button" aria-label="Действия"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button></div></td>'
        +'</tr>';
    }).join('');
  }

  function updateTabs(items){
    var counts = {published:0,draft:0,review:0,needs_update:0};
    items.forEach(function(p){ if(counts[p.status]!==undefined) counts[p.status]++; });
    document.getElementById('kbTabPublished').textContent = counts.published;
    document.getElementById('kbTabDrafts').textContent = counts.draft;
    document.getElementById('kbTabReview').textContent = counts.review;
    document.getElementById('kbTabOutdated').textContent = counts.needs_update;

    var qLinks = document.querySelectorAll('#kbQuickLinks .badge');
    if(qLinks.length>=4){
      qLinks[0].textContent = counts.draft;
      qLinks[1].textContent = counts.review;
      qLinks[2].textContent = counts.needs_update;
    }
  }

  /* ── Load Recent ── */
  async function loadRecent(){
    try {
      var r = await req('api/v1/knowledge/search', {method:'GET', query:{sort:'updated_at',order:'desc',limit:4}});
      var items = r.data && r.data.items || [];
      var el = document.getElementById('kbRecentList');
      if(!el) return;
      if(!items.length){ el.innerHTML='<div class="text-muted small p-2">Нет данных</div>'; return; }
      el.innerHTML = items.map(function(p){
        return '<a href="index.php?route=knowledge-page&id='+esc(p.public_id)+'" class="kb-side-item">'
          +'<i class="fa-regular fa-file-lines" aria-hidden="true"></i>'
          +'<div class="kb-side-item-text"><span class="kb-side-item-title">'+esc(p.title)+'</span>'
          +'<span class="kb-side-item-meta">'+esc(p.space_title||'')+' · '+esc(p.updated_at?p.updated_at.substring(0,10):'')+'</span></div></a>';
      }).join('');
    } catch(e){}
  }

  /* ── Load Types ── */
  async function loadTypes(){
    try {
      var r = await req('api/v1/knowledge/search', {method:'GET', query:{limit:200}});
      var items = r.data && r.data.items || [];
      var counts = {};
      items.forEach(function(p){ counts[p.page_type] = (counts[p.page_type]||0)+1; });
      var typeIcons = {regulation:'fa-regular fa-file-lines',instruction:'fa-regular fa-file-code',article:'fa-regular fa-file',checklist:'fa-solid fa-list-check',faq:'fa-regular fa-clone'};
      var typeNames = {regulation:'Регламент',instruction:'Инструкция',article:'Документ',checklist:'Чек-лист',faq:'Шаблон'};
      var el = document.getElementById('kbTypes');
      if(!el) return;
      var h = '';
      Object.keys(typeNames).forEach(function(k){
        h += '<div class="kb-type-row"><span><i class="'+(typeIcons[k]||'fa-regular fa-file')+' text-muted" aria-hidden="true"></i> '+esc(typeNames[k])+'</span><span class="text-muted">'+(counts[k]||0)+'</span></div>';
      });
      el.innerHTML = h;
    } catch(e){}
  }

  /* ── Events ── */
  document.getElementById('kbSpaces').addEventListener('click', function(e){
    var toggle = e.target.closest('[data-toggle]');
    if(toggle){
      var id = toggle.getAttribute('data-toggle');
      var ch = document.querySelector('[data-children="'+id+'"]');
      if(ch){
        toggle.classList.toggle('is-open');
        ch.classList.toggle('kb-collapsed');
        var openIds = [];
        document.querySelectorAll('.kb-space-toggle.is-open[data-toggle]').forEach(function(t){
          openIds.push(t.getAttribute('data-toggle'));
        });
        saveOpenSpaces(openIds);
      }
      return;
    }
    var item = e.target.closest('.kb-space-item[data-space]');
    if(item){
      e.preventDefault();
      selectSpace(item.getAttribute('data-space'));
    }
  });

  document.getElementById('kbTabs').addEventListener('click', function(e){
    var btn = e.target.closest('[data-status]');
    if(!btn) return;
    document.querySelectorAll('#kbTabs .nav-link').forEach(function(n){n.classList.remove('active');});
    btn.classList.add('active');
    state.activeStatus = btn.getAttribute('data-status')||'';
    loadArticles();
  });

  document.getElementById('kbSearch').addEventListener('input', function(){
    clearTimeout(this._t);
    this._t = setTimeout(loadArticles, 300);
  });

  document.getElementById('kbFilterStatus').addEventListener('change', loadArticles);
  document.getElementById('kbFilterType').addEventListener('change', loadArticles);
  document.getElementById('kbFilterTag').addEventListener('change', loadArticles);
  document.getElementById('kbFilterSpace').addEventListener('change', function(){
    state.activeSpace = this.value;
    updateSpaceHeader();
    loadArticles();
  });

  document.getElementById('kbFilterReset').addEventListener('click', function(){
    document.getElementById('kbSearch').value='';
    document.getElementById('kbFilterStatus').value='';
    document.getElementById('kbFilterType').value='';
    document.getElementById('kbFilterTag').value='';
    document.getElementById('kbFilterSpace').value='';
    state.activeSpace='';
    updateSpaceHeader();
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
    sel.innerHTML = '<option value="">Без родителя</option>' + flatSpaces.map(function(s){ return '<option value="'+esc(s.public_id)+'">'+esc(s.title)+'</option>'; }).join('');
    new bootstrap.Modal(document.getElementById('kbSpaceModal')).show();
  });

  document.getElementById('kbPageSubmit').addEventListener('click', async function(){
    var title = document.getElementById('kbPageTitle').value.trim();
    if(!title){ alert('Укажите название'); return; }
    try {
      await req('api/v1/knowledge/pages', {method:'POST', body:{
        title: title,
        space_public_id: document.getElementById('kbPageSpace').value,
        page_type: document.getElementById('kbPageType').value,
        content_html: document.getElementById('kbPageContent').value
      }});
      bootstrap.Modal.getInstance(document.getElementById('kbPageModal')).hide();
      loadArticles();
    } catch(e){ alert('Ошибка создания'); }
  });

  document.getElementById('kbSpaceSubmit').addEventListener('click', async function(){
    var title = document.getElementById('kbSpaceTitle').value.trim();
    if(!title){ alert('Укажите название'); return; }
    try {
      var body = {title: title, description: document.getElementById('kbSpaceDesc').value};
      var parentId = document.getElementById('kbSpaceParent').value;
      if(parentId) body.parent_public_id = parentId;
      await req('api/v1/knowledge/spaces', {method:'POST', body: body});
      bootstrap.Modal.getInstance(document.getElementById('kbSpaceModal')).hide();
      await loadSpaces();
      if(parentId) selectSpace(parentId);
    } catch(e){ alert('Ошибка создания'); }
  });

  /* ── Init ── */
  waitForApi(function(){
    loadSpaces();
    loadRecent();
    loadTypes();
    loadArticles();
  });
})();
</script>
</body>
