<?php declare(strict_types=1); ?>
<?php $title = $t('mentions.title', 'TropaTT — Упоминания'); ?>
<body data-page="mentions" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="mentions.page_title"><?= htmlspecialchars($t('mentions.page_title', 'Упоминания'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="mentions.subtitle"><?= htmlspecialchars($t('mentions.subtitle', 'Задачи и комментарии, где вы были упомянуты.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="d-flex gap-2"><button id="mentionsRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="mentions.btn_refresh"><?= htmlspecialchars($t('mentions.btn_refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button></div></div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="mentions.heading_my"><?= htmlspecialchars($t('mentions.heading_my', 'Мои упоминания'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="mentions.list_note"><?= htmlspecialchars($t('mentions.list_note', 'Список упоминаний.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th data-i18n="mentions.th_entity"><?= htmlspecialchars($t('mentions.th_entity', 'Сущность'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="mentions.th_context"><?= htmlspecialchars($t('mentions.th_context', 'Контекст'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="mentions.th_mentioned_by"><?= htmlspecialchars($t('mentions.th_mentioned_by', 'Упомянул'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="mentions.th_date"><?= htmlspecialchars($t('mentions.th_date', 'Дата'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="mentionsBody"><tr><td colspan="4" class="text-muted" data-i18n="mentions.loading"><?= htmlspecialchars($t('mentions.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table>
    </div>
  </div>
</div>

</main></div></div>
