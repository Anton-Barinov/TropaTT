<?php declare(strict_types=1); ?>
<?php $title = $t('time_analytics.title', 'TropaTT — Учет времени'); ?>
<body data-page="time-analytics" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-time-analytics-page"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="time_analytics.page_title"><?= htmlspecialchars($t('time_analytics.page_title', 'Учет времени'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="time_analytics.subtitle"><?= htmlspecialchars($t('time_analytics.subtitle', 'Статистика трудозатрат и заработка по пользователям.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>

<ul class="nav nav-tabs mb-3" id="timeAnalyticsTabs" role="tablist">
  <li class="nav-item"><button class="nav-link active" id="timeTabTime" data-bs-toggle="tab" data-bs-target="#timeAnalyticsTime" type="button" data-i18n="time_analytics.tab_time"><?= htmlspecialchars($t('time_analytics.tab_time', 'Затраченное время'), ENT_QUOTES, 'UTF-8') ?></button></li>
  <li class="nav-item"><button class="nav-link" id="timeTabEarnings" data-bs-toggle="tab" data-bs-target="#timeAnalyticsEarnings" type="button" data-i18n="time_analytics.tab_earnings"><?= htmlspecialchars($t('time_analytics.tab_earnings', 'Заработок'), ENT_QUOTES, 'UTF-8') ?></button></li>
  <li class="nav-item"><button class="nav-link" id="timeTabMatrix" data-bs-toggle="tab" data-bs-target="#timeAnalyticsMatrix" type="button" data-i18n="time_analytics.tab_matrix"><?= htmlspecialchars($t('time_analytics.tab_matrix', 'Общая сводка'), ENT_QUOTES, 'UTF-8') ?></button></li>
</ul>

<div class="tab-content">
  <div id="timeAnalyticsTime" class="tab-pane fade show active">
    <div class="crm-toolbar-surface d-flex flex-wrap gap-2 align-items-center mb-3">
      <input id="timeAnalyticsFrom" class="form-control crm-field-w-200" type="date" placeholder="<?= htmlspecialchars($t('time_analytics.placeholder_from', 'От'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="time_analytics.placeholder_from">
      <input id="timeAnalyticsTo" class="form-control crm-field-w-200" type="date" placeholder="<?= htmlspecialchars($t('time_analytics.placeholder_to', 'До'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="time_analytics.placeholder_to">
      <select id="timeAnalyticsTeamFilter" class="form-select crm-field-w-220" aria-label="<?= htmlspecialchars($t('time_analytics.filter_team_aria', 'Команда'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="time_analytics.filter_team_aria"><option value="" data-i18n="time_analytics.opt_all_teams"><?= htmlspecialchars($t('time_analytics.opt_all_teams', 'Все команды'), ENT_QUOTES, 'UTF-8') ?></option></select>
      <select id="timeAnalyticsProjectFilter" class="form-select crm-field-w-220" aria-label="<?= htmlspecialchars($t('time_analytics.filter_project_aria', 'Проект'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="time_analytics.filter_project_aria"><option value="" data-i18n="time_analytics.opt_all_projects"><?= htmlspecialchars($t('time_analytics.opt_all_projects', 'Все проекты'), ENT_QUOTES, 'UTF-8') ?></option></select>
      <select id="timeAnalyticsUserFilter" class="form-select crm-field-w-220" aria-label="<?= htmlspecialchars($t('time_analytics.filter_user_aria', 'Пользователь'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="time_analytics.filter_user_aria"><option value="" data-i18n="time_analytics.opt_all_users"><?= htmlspecialchars($t('time_analytics.opt_all_users', 'Все пользователи'), ENT_QUOTES, 'UTF-8') ?></option></select>
      <button class="btn crm-btn-primary crm-btn-compact" type="button" id="timeAnalyticsApplyBtn" data-i18n="page.apply"><?= htmlspecialchars($t('page.apply', 'Применить'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn crm-btn-ghost crm-btn-icon" type="button" id="timeAnalyticsResetBtn" title="<?= htmlspecialchars($t('time_analytics.btn_reset_filters_title', 'Сбросить фильтры'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="time_analytics.btn_reset_filters_title"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></button>
    </div>
    <div class="crm-card crm-section-card p-0 table-responsive"><table class="table table-hover align-middle mb-0 crm-table crm-matrix-table"><thead><tr><th class="crm-matrix-date-col" data-i18n="time_analytics.th_date"><?= htmlspecialchars($t('time_analytics.th_date', 'Дата'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="time_analytics.th_user"><?= htmlspecialchars($t('time_analytics.th_user', 'Пользователь'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="time_analytics.th_recorded"><?= htmlspecialchars($t('time_analytics.th_recorded', 'Записано'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="time_analytics.th_unique"><?= htmlspecialchars($t('time_analytics.th_unique', 'Уникально'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="time_analytics.th_overlap"><?= htmlspecialchars($t('time_analytics.th_overlap', 'Пересечения'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="timeAnalyticsTimeBody"><tr><td colspan="5" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка данных...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
  </div>

  <div id="timeAnalyticsEarnings" class="tab-pane fade">
    <div class="crm-toolbar-surface d-flex flex-wrap gap-2 align-items-center mb-3">
      <input id="timeAnalyticsEarningsFrom" class="form-control crm-field-w-200" type="date" placeholder="<?= htmlspecialchars($t('time_analytics.placeholder_from', 'От'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="time_analytics.placeholder_from">
      <input id="timeAnalyticsEarningsTo" class="form-control crm-field-w-200" type="date" placeholder="<?= htmlspecialchars($t('time_analytics.placeholder_to', 'До'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="time_analytics.placeholder_to">
      <select id="timeAnalyticsEarningsTeamFilter" class="form-select crm-field-w-220" aria-label="<?= htmlspecialchars($t('time_analytics.filter_team_aria', 'Команда'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="time_analytics.filter_team_aria"><option value="" data-i18n="time_analytics.opt_all_teams"><?= htmlspecialchars($t('time_analytics.opt_all_teams', 'Все команды'), ENT_QUOTES, 'UTF-8') ?></option></select>
      <select id="timeAnalyticsEarningsProjectFilter" class="form-select crm-field-w-220" aria-label="<?= htmlspecialchars($t('time_analytics.filter_project_aria', 'Проект'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="time_analytics.filter_project_aria"><option value="" data-i18n="time_analytics.opt_all_projects"><?= htmlspecialchars($t('time_analytics.opt_all_projects', 'Все проекты'), ENT_QUOTES, 'UTF-8') ?></option></select>
      <select id="timeAnalyticsEarningsUserFilter" class="form-select crm-field-w-220" aria-label="<?= htmlspecialchars($t('time_analytics.filter_user_aria', 'Пользователь'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="time_analytics.filter_user_aria"><option value="" data-i18n="time_analytics.opt_all_users"><?= htmlspecialchars($t('time_analytics.opt_all_users', 'Все пользователи'), ENT_QUOTES, 'UTF-8') ?></option></select>
      <button class="btn crm-btn-primary crm-btn-compact" type="button" id="timeAnalyticsEarningsApplyBtn" data-i18n="page.apply"><?= htmlspecialchars($t('page.apply', 'Применить'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn crm-btn-ghost crm-btn-icon" type="button" id="timeAnalyticsEarningsResetBtn" title="<?= htmlspecialchars($t('time_analytics.btn_reset_filters_title', 'Сбросить фильтры'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="time_analytics.btn_reset_filters_title"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></button>
    </div>
    <div class="crm-card crm-section-card p-0 table-responsive"><table class="table table-hover align-middle mb-0 crm-table crm-matrix-table"><thead><tr><th class="crm-matrix-date-col" data-i18n="time_analytics.th_date"><?= htmlspecialchars($t('time_analytics.th_date', 'Дата'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="time_analytics.th_user"><?= htmlspecialchars($t('time_analytics.th_user', 'Пользователь'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="time_analytics.th_recorded"><?= htmlspecialchars($t('time_analytics.th_recorded', 'Записано'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="time_analytics.th_unique"><?= htmlspecialchars($t('time_analytics.th_unique', 'Уникально'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="time_analytics.th_cost_rate"><?= htmlspecialchars($t('time_analytics.th_cost_rate', 'Ставка (себестоимость)'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="time_analytics.th_sale_rate"><?= htmlspecialchars($t('time_analytics.th_sale_rate', 'Ставка (продажа)'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="time_analytics.th_cost"><?= htmlspecialchars($t('time_analytics.th_cost', 'Себестоимость'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="time_analytics.th_sale"><?= htmlspecialchars($t('time_analytics.th_sale', 'Продажа'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="timeAnalyticsEarningsBody"><tr><td colspan="8" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка данных...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
    <div id="timeAnalyticsLocksBlock" class="crm-card crm-section-card mt-3 d-none">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="time_analytics.locks_title"><?= htmlspecialchars($t('time_analytics.locks_title', 'Закрытые периоды'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="time_analytics.locks_note"><?= htmlspecialchars($t('time_analytics.locks_note', 'Заблокированные периоды не пересчитываются: ставки зафиксированы для расчётов и выплат.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div id="timeAnalyticsLocksAutoClose" class="small text-muted mb-2"></div>
      <div id="timeAnalyticsLocksList" class="mb-3"></div>
      <form id="timeAnalyticsLockForm" class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label" data-i18n="time_analytics.locks_from"><?= htmlspecialchars($t('time_analytics.locks_from', 'С'), ENT_QUOTES, 'UTF-8') ?></label><input type="date" class="form-control" name="date_from" required></div>
        <div class="col-md-3"><label class="form-label" data-i18n="time_analytics.locks_to"><?= htmlspecialchars($t('time_analytics.locks_to', 'По'), ENT_QUOTES, 'UTF-8') ?></label><input type="date" class="form-control" name="date_to" required></div>
        <div class="col-md-auto"><button type="submit" class="btn crm-btn-primary crm-btn-compact" data-i18n="time_analytics.locks_lock_btn"><?= htmlspecialchars($t('time_analytics.locks_lock_btn', 'Заблокировать период'), ENT_QUOTES, 'UTF-8') ?></button></div>
      </form>
    </div>
  </div>

  <div id="timeAnalyticsMatrix" class="tab-pane fade">
    <div class="crm-toolbar-surface d-flex flex-wrap gap-2 align-items-center mb-3">
      <input id="timeAnalyticsMatrixFrom" class="form-control crm-field-w-200" type="date" placeholder="<?= htmlspecialchars($t('time_analytics.placeholder_from', 'От'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="time_analytics.placeholder_from">
      <input id="timeAnalyticsMatrixTo" class="form-control crm-field-w-200" type="date" placeholder="<?= htmlspecialchars($t('time_analytics.placeholder_to', 'До'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="time_analytics.placeholder_to">
      <select id="timeAnalyticsMatrixTeamFilter" class="form-select crm-field-w-220" aria-label="<?= htmlspecialchars($t('time_analytics.filter_team_aria', 'Команда'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="time_analytics.filter_team_aria"><option value="" data-i18n="time_analytics.opt_all_teams"><?= htmlspecialchars($t('time_analytics.opt_all_teams', 'Все команды'), ENT_QUOTES, 'UTF-8') ?></option></select>
      <select id="timeAnalyticsMatrixProjectFilter" class="form-select crm-field-w-220" aria-label="<?= htmlspecialchars($t('time_analytics.filter_project_aria', 'Проект'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="time_analytics.filter_project_aria"><option value="" data-i18n="time_analytics.opt_all_projects"><?= htmlspecialchars($t('time_analytics.opt_all_projects', 'Все проекты'), ENT_QUOTES, 'UTF-8') ?></option></select>
      <select id="timeAnalyticsMatrixUserFilter" class="form-select crm-field-w-220" aria-label="<?= htmlspecialchars($t('time_analytics.filter_user_aria', 'Пользователь'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="time_analytics.filter_user_aria"><option value="" data-i18n="time_analytics.opt_all_users"><?= htmlspecialchars($t('time_analytics.opt_all_users', 'Все пользователи'), ENT_QUOTES, 'UTF-8') ?></option></select>
      <button class="btn crm-btn-primary crm-btn-compact" type="button" id="timeAnalyticsMatrixApplyBtn" data-i18n="page.apply"><?= htmlspecialchars($t('page.apply', 'Применить'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn crm-btn-ghost crm-btn-icon" type="button" id="timeAnalyticsMatrixResetBtn" title="<?= htmlspecialchars($t('time_analytics.btn_reset_filters_title', 'Сбросить фильтры'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="time_analytics.btn_reset_filters_title"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></button>
    </div>
    <div class="crm-card crm-section-card p-0 table-responsive"><div id="timeAnalyticsMatrixWrap"><p class="text-muted p-3 mb-0" data-i18n="time_analytics.matrix_placeholder"><?= htmlspecialchars($t('time_analytics.matrix_placeholder', 'Выберите период и нажмите «Применить».'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
  </div>
</div>

</main></div></div>

<div class="modal fade" id="timeDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" data-i18n="time_analytics.modal_detail_title"><?= htmlspecialchars($t('time_analytics.modal_detail_title', 'Детализация времени'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close_aria"></button>
      </div>
      <div class="modal-body" id="timeDetailModalBody">
        <p class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.close"><?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>
