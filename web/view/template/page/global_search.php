<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Поиск'; ?>
<body data-page="global-search" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-global-search-page"><div class="crm-page-head"><div><h1 class="crm-page-title">Поиск</h1><p class="crm-subtitle">Результаты поиска по всем разделам.</p></div></div>

<div class="crm-card crm-section-card mb-3">
  <form id="globalSearchForm" class="p-3">
    <div class="input-group input-group-lg">
      <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
      <input class="form-control" id="globalSearchQuery" name="q" placeholder="Поиск: задачи, проекты, контрагенты, контакты" autocomplete="off" autofocus>
      <button class="btn crm-btn-primary" type="submit">Найти</button>
    </div>
  </form>
</div>

<div id="globalSearchResults" class="row g-3"></div>

</main></div></div>
