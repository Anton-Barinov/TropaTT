<?php declare(strict_types=1); ?>
<?php $title = $t('organizations.title', 'TropaTT — Рабочие пространства'); ?>
<body data-page="organizations" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="organizations.page_title"><?= htmlspecialchars($t('organizations.page_title', 'Рабочие пространства'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="organizations.subtitle"><?= htmlspecialchars($t('organizations.subtitle', 'Управление рабочими пространствами и их участниками.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="d-flex gap-2"><button id="organizationsRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="page.refresh"><?= htmlspecialchars($t('page.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button><button id="organizationsCreateBtn" class="btn crm-btn-primary" type="button" data-i18n="organizations.btn_create"><?= htmlspecialchars($t('organizations.btn_create', 'Создать рабочее пространство'), ENT_QUOTES, 'UTF-8') ?></button></div></div>

<div class="alert alert-info mb-3" role="alert">
  <span data-i18n="organizations.alert_info"><?= htmlspecialchars($t('organizations.alert_info', 'Рабочее пространство объединяет пользователей и рабочие данные. У каждого пространства свой состав участников и роли.'), ENT_QUOTES, 'UTF-8') ?></span>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head align-items-center"><div><h2 class="h6 mb-0" data-i18n="organizations.heading_list"><?= htmlspecialchars($t('organizations.heading_list', 'Рабочие пространства'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="organizations.note_list"><?= htmlspecialchars($t('organizations.note_list', 'Все рабочие пространства, которыми вы управляете.'), ENT_QUOTES, 'UTF-8') ?></div></div><label class="crm-filter-control mb-0"><span class="visually-hidden" data-i18n="organizations.search"><?= htmlspecialchars($t('organizations.search', 'Поиск'), ENT_QUOTES, 'UTF-8') ?></span><input id="organizationsSearch" class="form-control form-control-sm" type="search" autocomplete="off" placeholder="<?= htmlspecialchars($t('organizations.search_placeholder', 'Поиск рабочих пространств'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="organizations.search_placeholder"></label></div>
      <div class="crm-table-responsive"><table class="table table-sm crm-table mb-0" data-mobile-cards="true"><thead><tr><th data-i18n="organizations.th_name"><?= htmlspecialchars($t('organizations.th_name', 'Название'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="organizations.th_participants"><?= htmlspecialchars($t('organizations.th_participants', 'Участников'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="organizations.th_created"><?= htmlspecialchars($t('organizations.th_created', 'Дата создания'), ENT_QUOTES, 'UTF-8') ?></th><th><span class="visually-hidden" data-i18n="organizations.th_actions"><?= htmlspecialchars($t('organizations.th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?></span></th></tr></thead><tbody id="organizationsBody"><tr><td colspan="4" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
    </div>
  </div>
</div>

</main></div></div>
