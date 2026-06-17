<?php declare(strict_types=1); ?>
<?php $title = $t('global_search.title', 'TropaTT — Поиск'); ?>
<body data-page="global-search" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-global-search-page"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="global_search.page_title"><?= htmlspecialchars($t('global_search.page_title', 'Поиск'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="global_search.subtitle"><?= htmlspecialchars($t('global_search.subtitle', 'Результаты поиска по всем разделам.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>

<div class="crm-card crm-section-card mb-3">
  <form id="globalSearchForm" class="p-3">
    <div class="input-group input-group-lg">
      <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
      <input class="form-control" id="globalSearchQuery" name="q" placeholder="<?= htmlspecialchars($t('global_search.placeholder_query', 'Поиск: задачи, проекты, контрагенты, контакты'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="global_search.placeholder_query" autocomplete="off" autofocus>
      <button class="btn crm-btn-primary" type="submit" data-i18n="global_search.btn_search"><?= htmlspecialchars($t('global_search.btn_search', 'Найти'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </form>
</div>

<div id="globalSearchResults" class="row g-3"></div>

</main></div></div>
