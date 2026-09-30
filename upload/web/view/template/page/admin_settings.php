<?php declare(strict_types=1); ?>
<?php $title = $t('admin_settings.title', 'TropaTT — Системные настройки'); ?>
<body data-page="admin-settings" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-admin-settings-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="admin_settings.link_admin"><?= htmlspecialchars($t('admin_settings.link_admin', 'Админка'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="admin_settings.breadcrumb"><?= htmlspecialchars($t('admin_settings.breadcrumb', 'Системные настройки'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" data-i18n="admin_settings.page_title"><?= htmlspecialchars($t('admin_settings.page_title', 'Системные настройки'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_settings.subtitle"><?= htmlspecialchars($t('admin_settings.subtitle', 'Обзор без изменения критичных данных, безопасное редактирование и политика хранения.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="d-flex gap-2"><button id="adminSettingsRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="admin_settings.refresh_btn"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> <?= htmlspecialchars($t('admin_settings.refresh_btn', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button></div></div>

<div class="crm-admin-settings-note mb-3 d-flex align-items-start gap-2" role="note" data-i18n="admin_settings.note">
  <i class="fa-solid fa-triangle-exclamation crm-admin-settings-note-icon" aria-hidden="true"></i>
  <span><?= htmlspecialchars($t('admin_settings.note', 'Опасные изменения требуют подтверждения. Изменения записываются в журнал аудита через API.'), ENT_QUOTES, 'UTF-8') ?></span>
</div>

<nav class="crm-admin-settings-nav" aria-label="<?= htmlspecialchars($t('admin_settings.nav_label', 'Категории настроек'), ENT_QUOTES, 'UTF-8') ?>">
  <button type="button" class="crm-admin-settings-nav-btn active" data-settings-category="all">
    <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
    <span data-i18n="admin_settings.tab_all"><?= htmlspecialchars($t('admin_settings.tab_all', 'Все настройки'), ENT_QUOTES, 'UTF-8') ?></span>
  </button>
  <button type="button" class="crm-admin-settings-nav-btn" data-settings-category="system">
    <i class="fa-solid fa-sliders" aria-hidden="true"></i>
    <span data-i18n="admin_settings.tab_system"><?= htmlspecialchars($t('admin_settings.tab_system', 'Система и кэш'), ENT_QUOTES, 'UTF-8') ?></span>
  </button>
  <button type="button" class="crm-admin-settings-nav-btn" data-settings-category="tasks">
    <i class="fa-solid fa-list-check" aria-hidden="true"></i>
    <span data-i18n="admin_settings.tab_tasks"><?= htmlspecialchars($t('admin_settings.tab_tasks', 'Политики задач'), ENT_QUOTES, 'UTF-8') ?></span>
  </button>
  <button type="button" class="crm-admin-settings-nav-btn" data-settings-category="finance">
    <i class="fa-solid fa-wallet" aria-hidden="true"></i>
    <span data-i18n="admin_settings.tab_finance"><?= htmlspecialchars($t('admin_settings.tab_finance', 'Финансы'), ENT_QUOTES, 'UTF-8') ?></span>
  </button>
  <button type="button" class="crm-admin-settings-nav-btn" data-settings-category="retention">
    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
    <span data-i18n="admin_settings.tab_retention"><?= htmlspecialchars($t('admin_settings.tab_retention', 'Хранение и аудит'), ENT_QUOTES, 'UTF-8') ?></span>
  </button>
  <button type="button" class="crm-admin-settings-nav-btn" data-settings-category="sysinfo">
    <i class="fa-solid fa-server" aria-hidden="true"></i>
    <span data-i18n="admin_settings.tab_sysinfo"><?= htmlspecialchars($t('admin_settings.tab_sysinfo', 'Окружение'), ENT_QUOTES, 'UTF-8') ?></span>
  </button>
  <div class="crm-admin-settings-search-wrap">
    <div class="input-group input-group-sm">
      <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
      <input type="search" class="form-control" id="adminSettingsSearchInput" placeholder="<?= htmlspecialchars($t('admin_settings.search_placeholder', 'Поиск по ключу или названию...'), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($t('admin_settings.search_placeholder', 'Поиск по ключу или названию...'), ENT_QUOTES, 'UTF-8') ?>">
    </div>
  </div>
</nav>
<div id="adminSettingsFilterEmptyState" class="crm-admin-settings-filter-empty" role="status" aria-live="polite" hidden>
  <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
  <strong data-i18n="admin_settings.filter_empty_title"><?= htmlspecialchars($t('admin_settings.filter_empty_title', 'Ничего не найдено'), ENT_QUOTES, 'UTF-8') ?></strong>
  <span data-i18n="admin_settings.filter_empty_body"><?= htmlspecialchars($t('admin_settings.filter_empty_body', 'Измените запрос или выберите другую категорию.'), ENT_QUOTES, 'UTF-8') ?></span>
</div>

<div class="row g-3 mb-3" data-settings-group="system">
  <div class="col-lg-8">
    <div class="crm-card crm-section-card h-100" id="adminSystemSettingsSectionCard" data-settings-scope="system-table">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_system_title"><?= htmlspecialchars($t('admin_settings.section_system_title', 'Системные настройки'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_system_note"><?= htmlspecialchars($t('admin_settings.section_system_note', 'Только разрешенные настройки с безопасным редактированием.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div class="table-responsive"><table class="table table-sm crm-table crm-admin-settings-table-stacked mb-0"><thead><tr><th data-i18n="admin_settings.th_key"><?= htmlspecialchars($t('admin_settings.th_key', 'Ключ'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_settings.th_value"><?= htmlspecialchars($t('admin_settings.th_value', 'Значение'), ENT_QUOTES, 'UTF-8') ?></th><th class="text-end"></th></tr></thead><tbody id="adminSettingsSystemBody"><tr><td colspan="3" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="crm-card crm-section-card h-100" id="adminUserPrefsSectionCard" data-settings-scope="user-preferences">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_user_prefs_title"><?= htmlspecialchars($t('admin_settings.section_user_prefs_title', 'Пользовательские настройки'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_user_prefs_note"><?= htmlspecialchars($t('admin_settings.section_user_prefs_note', 'Персональные настройки профиля и уведомлений, без системных флагов.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div id="adminSettingsUserPrefsState" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
      <div id="adminSettingsUserPrefsForm" style="display:none;">
        <div class="mb-3">
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="adminPrefsSound">
            <label class="form-check-label fw-semibold" for="adminPrefsSound" data-i18n="admin_settings.pref_sound_label"><?= htmlspecialchars($t('admin_settings.pref_sound_label', 'Звук уведомлений'), ENT_QUOTES, 'UTF-8') ?></label>
            <div class="form-text" data-i18n="admin_settings.pref_sound_hint"><?= htmlspecialchars($t('admin_settings.pref_sound_hint', 'Воспроизводить звук при новом уведомлении'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="adminPrefsQuietHours">
            <label class="form-check-label fw-semibold" for="adminPrefsQuietHours" data-i18n="admin_settings.pref_quiet_hours_label"><?= htmlspecialchars($t('admin_settings.pref_quiet_hours_label', 'Тихие часы'), ENT_QUOTES, 'UTF-8') ?></label>
            <div class="form-text" data-i18n="admin_settings.pref_quiet_hours_hint"><?= htmlspecialchars($t('admin_settings.pref_quiet_hours_hint', 'Отключить звук уведомлений в указанное время'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="row g-2 mt-1" id="adminPrefsQuietHoursRow" style="display:none;">
            <div class="col-6">
              <label class="form-label small" data-i18n="admin_settings.pref_quiet_start"><?= htmlspecialchars($t('admin_settings.pref_quiet_start', 'Начало'), ENT_QUOTES, 'UTF-8') ?></label>
              <input type="time" class="form-control form-control-sm" id="adminPrefsQuietStart" value="22:00">
            </div>
            <div class="col-6">
              <label class="form-label small" data-i18n="admin_settings.pref_quiet_end"><?= htmlspecialchars($t('admin_settings.pref_quiet_end', 'Окончание'), ENT_QUOTES, 'UTF-8') ?></label>
              <input type="time" class="form-control form-control-sm" id="adminPrefsQuietEnd" value="08:00">
            </div>
          </div>
        </div>
        <button class="btn btn-sm crm-btn-primary" id="adminPrefsSaveBtn" type="button" data-i18n="common.save"><?= htmlspecialchars($t('common.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3" data-settings-group="retention">
  <div class="col-lg-7">
    <div class="crm-card crm-section-card h-100" id="adminRetentionSectionCard" data-settings-scope="retention-table">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_retention_title"><?= htmlspecialchars($t('admin_settings.section_retention_title', 'Политики хранения данных'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_retention_note"><?= htmlspecialchars($t('admin_settings.section_retention_note', 'Настройки жизненного цикла данных. Сначала предварительный расчет, затем применение.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div class="table-responsive"><table class="table table-sm crm-table crm-admin-settings-table-stacked mb-2"><thead><tr><th data-i18n="admin_settings.th_field"><?= htmlspecialchars($t('admin_settings.th_field', 'Поле'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_settings.th_days"><?= htmlspecialchars($t('admin_settings.th_days', 'Дней'), ENT_QUOTES, 'UTF-8') ?></th><th class="text-end" data-i18n="admin_settings.th_action"><?= htmlspecialchars($t('admin_settings.th_action', 'Действие'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="adminSettingsRetentionBody"><tr><td colspan="3" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
      <div id="adminSettingsRetentionState" class="text-muted small" data-i18n="admin_settings.retention_state_loading"><?= htmlspecialchars($t('admin_settings.retention_state_loading', 'Ожидание данных...'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="crm-card crm-section-card h-100" id="adminAuditSectionCard" data-settings-scope="audit">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_audit_title"><?= htmlspecialchars($t('admin_settings.section_audit_title', 'Последний аудит'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_audit_note"><?= htmlspecialchars($t('admin_settings.section_audit_note', 'Последние изменения настроек.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div id="adminSettingsAuditState" class="text-muted small mb-2" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
      <ul id="adminSettingsAuditList" class="list-group list-group-flush"></ul>
    </div>
  </div>
</div>

<div class="row g-3 mb-3" data-settings-group="cache">
  <div class="col-lg-6">
    <div class="crm-card crm-section-card h-100" id="adminCacheSectionCard" data-settings-scope="cache">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_cache_title"><?= htmlspecialchars($t('admin_settings.section_cache_title', 'Кэш API'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_cache_note"><?= htmlspecialchars($t('admin_settings.section_cache_note', 'Файловое кэширование ответов справочных эндпоинтов. После включения изменения вступают в силу немедленно.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div class="crm-section-body" id="adminCacheSection">
        <div class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>
  </div>
  <div class="col-lg-6" id="adminSystemInfoSection" style="display:none;" data-settings-group="sysinfo">
    <div class="crm-card crm-section-card h-100" id="adminSystemInfoSectionCard" data-settings-scope="system-info">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_system_info_title"><?= htmlspecialchars($t('admin_settings.section_system_info_title', 'Информация о системе'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_system_note_env"><?= htmlspecialchars($t('admin_settings.section_system_info_note', 'Технические параметры окружения (только для root).'), ENT_QUOTES, 'UTF-8') ?></div></div><div class="d-flex gap-2"><button id="adminSystemInfoRefreshBtn" class="btn btn-sm crm-btn-secondary" type="button" data-i18n="admin_settings.system_info_refresh_btn"><?= htmlspecialchars($t('admin_settings.system_info_refresh_btn', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button></div></div>
      <div class="crm-admin-settings-sysinfo-grid">
        <div class="crm-admin-settings-sysinfo-item"><small class="text-muted" data-i18n="admin_settings.sysinfo_api_version"><?= htmlspecialchars($t('admin_settings.sysinfo_api_version', 'Версия API'), ENT_QUOTES, 'UTF-8') ?></small><div class="fw-semibold" id="systemInfoPhpVersion">—</div></div>
        <div class="crm-admin-settings-sysinfo-item"><small class="text-muted" data-i18n="admin_settings.sysinfo_timezone"><?= htmlspecialchars($t('admin_settings.sysinfo_timezone', 'Часовой пояс'), ENT_QUOTES, 'UTF-8') ?></small><div class="fw-semibold" id="systemInfoEnv">—</div></div>
        <div class="crm-admin-settings-sysinfo-item"><small class="text-muted" data-i18n="admin_settings.sysinfo_database"><?= htmlspecialchars($t('admin_settings.sysinfo_database', 'База данных'), ENT_QUOTES, 'UTF-8') ?></small><div class="fw-semibold" id="systemInfoDb">—</div></div>
        <div class="crm-admin-settings-sysinfo-item"><small class="text-muted" data-i18n="admin_settings.sysinfo_generated"><?= htmlspecialchars($t('admin_settings.sysinfo_generated', 'Сформировано'), ENT_QUOTES, 'UTF-8') ?></small><div class="fw-semibold" id="systemInfoUptime">—</div></div>
        <div class="crm-admin-settings-sysinfo-item"><small class="text-muted" data-i18n="admin_settings.sysinfo_files"><?= htmlspecialchars($t('admin_settings.sysinfo_files', 'Файлы'), ENT_QUOTES, 'UTF-8') ?></small><div class="small" id="systemInfoStorage">—</div></div>
        <div class="crm-admin-settings-sysinfo-item"><small class="text-muted" data-i18n="admin_settings.sysinfo_temp_data"><?= htmlspecialchars($t('admin_settings.sysinfo_temp_data', 'Временные данные'), ENT_QUOTES, 'UTF-8') ?></small><div class="small" id="systemInfoCache">—</div></div>
        <div class="crm-admin-settings-sysinfo-item"><small class="text-muted" data-i18n="admin_settings.sysinfo_logs"><?= htmlspecialchars($t('admin_settings.sysinfo_logs', 'Логи'), ENT_QUOTES, 'UTF-8') ?></small><div class="small" id="systemInfoLogs">—</div></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3" id="adminFinanceSettingsSection" style="display:none;" data-settings-group="finance">
  <div class="col-12">
    <div class="crm-card crm-section-card h-100" id="adminFinanceSectionCard" data-settings-scope="finance">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_finance_title"><?= htmlspecialchars($t('admin_settings.section_finance_title', 'Финансы'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_finance_note"><?= htmlspecialchars($t('admin_settings.section_finance_note', 'Валюта рабочего пространства, вывод себестоимости из вознаграждения и автозакрытие периодов.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div class="crm-section-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="financeDefaultCurrency" data-i18n="admin_settings.finance_default_currency"><?= htmlspecialchars($t('admin_settings.finance_default_currency', 'Валюта организации'), ENT_QUOTES, 'UTF-8') ?></label>
            <input class="form-control crm-field-responsive" id="financeDefaultCurrency" maxlength="8" placeholder="RUB">
            <div class="form-text" data-i18n="admin_settings.finance_default_currency_hint"><?= htmlspecialchars($t('admin_settings.finance_default_currency_hint', 'Используется, когда у прайса или записи не задана своя валюта.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="financeCostFromPayoutMarkup" data-i18n="admin_settings.finance_markup_percent"><?= htmlspecialchars($t('admin_settings.finance_markup_percent', 'Вывод себестоимости из вознаграждения, %'), ENT_QUOTES, 'UTF-8') ?></label>
            <input class="form-control crm-field-responsive" id="financeCostFromPayoutMarkup" type="number" min="0" max="1000" step="0.01" placeholder="<?= htmlspecialchars($t('admin_settings.finance_markup_empty', 'выключено'), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-text" data-i18n="admin_settings.finance_markup_hint"><?= htmlspecialchars($t('admin_settings.finance_markup_hint', 'Пусто — вывод выключен. Затрагивает только новые записи; к истории применяется явным пересчётом.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="financeAutoCloseMode" data-i18n="admin_settings.finance_auto_close_mode"><?= htmlspecialchars($t('admin_settings.finance_auto_close_mode', 'Автозакрытие периодов'), ENT_QUOTES, 'UTF-8') ?></label>
            <select class="form-select crm-field-responsive" id="financeAutoCloseMode"><option value="off" data-i18n="admin_settings.finance_mode_off"><?= htmlspecialchars($t('admin_settings.finance_mode_off', 'Выключено'), ENT_QUOTES, 'UTF-8') ?></option><option value="weekly" data-i18n="admin_settings.finance_mode_weekly"><?= htmlspecialchars($t('admin_settings.finance_mode_weekly', 'Еженедельно'), ENT_QUOTES, 'UTF-8') ?></option><option value="monthly" data-i18n="admin_settings.finance_mode_monthly"><?= htmlspecialchars($t('admin_settings.finance_mode_monthly', 'Ежемесячно'), ENT_QUOTES, 'UTF-8') ?></option></select>
            <div class="form-text" data-i18n="admin_settings.finance_auto_close_hint"><?= htmlspecialchars($t('admin_settings.finance_auto_close_hint', 'Период закрывается по расписанию через заданную задержку после его окончания.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="financeAutoCloseLagDays" data-i18n="admin_settings.finance_lag_days"><?= htmlspecialchars($t('admin_settings.finance_lag_days', 'Задержка, дней'), ENT_QUOTES, 'UTF-8') ?></label>
            <input class="form-control crm-field-responsive" id="financeAutoCloseLagDays" type="number" min="0" max="90" step="1" value="5">
            <div class="form-text" data-i18n="admin_settings.finance_lag_hint"><?= htmlspecialchars($t('admin_settings.finance_lag_hint', 'От 0 до 90 дней.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
        <button class="btn crm-btn-primary mt-3" id="adminFinanceSaveBtn" type="button" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3" id="adminTaskSettingsSection" data-settings-group="tasks">
  <div class="col-12">
    <div class="crm-card crm-section-card h-100" id="adminTaskSectionCard" data-settings-scope="tasks">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_tasks_title"><?= htmlspecialchars($t('admin_settings.section_tasks_title', 'Политики задач и учёта времени'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_tasks_note"><?= htmlspecialchars($t('admin_settings.section_tasks_note', 'Управление правилами трекинга времени и редактирования названий и описаний задач.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div class="crm-section-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="tasksWorklogPolicy" data-i18n="admin_settings.tasks_worklog_policy"><?= htmlspecialchars($t('admin_settings.tasks_worklog_policy', 'Политика трекинга времени в задачи'), ENT_QUOTES, 'UTF-8') ?></label>
            <div class="crm-pretty-select" data-pretty-select="tasksWorklogPolicy">
              <button type="button" class="crm-pretty-select-trigger" id="tasksWorklogPolicyTrigger" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="tasksWorklogPolicy" aria-controls="tasksWorklogPolicyMenu">
                <span class="crm-pretty-select-value" id="tasksWorklogPolicyValue"><?= htmlspecialchars($t('admin_settings.tasks_worklog_policy_all', 'Все участники проекта (совместная работа)'), ENT_QUOTES, 'UTF-8') ?></span>
                <i class="fa-solid fa-chevron-down crm-pretty-select-chevron" aria-hidden="true"></i>
              </button>
              <div class="crm-pretty-select-menu" id="tasksWorklogPolicyMenu" role="listbox" aria-labelledby="tasksWorklogPolicy" hidden>
                <button type="button" class="crm-pretty-select-option is-selected" role="option" aria-selected="true" data-value="all_project_members"><?= htmlspecialchars($t('admin_settings.tasks_worklog_policy_all', 'Все участники проекта (совместная работа)'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="crm-pretty-select-option" role="option" aria-selected="false" data-value="assignee_only"><?= htmlspecialchars($t('admin_settings.tasks_worklog_policy_assignee', 'Только назначенный исполнитель (строгий режим)'), ENT_QUOTES, 'UTF-8') ?></button>
              </div>
              <select class="form-select crm-field-responsive crm-pretty-select-native" id="tasksWorklogPolicy" tabindex="-1" aria-hidden="true">
                <option value="all_project_members" data-i18n="admin_settings.tasks_worklog_policy_all"><?= htmlspecialchars($t('admin_settings.tasks_worklog_policy_all', 'Все участники проекта (совместная работа)'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="assignee_only" data-i18n="admin_settings.tasks_worklog_policy_assignee"><?= htmlspecialchars($t('admin_settings.tasks_worklog_policy_assignee', 'Только назначенный исполнитель (строгий режим)'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="form-text" data-i18n="admin_settings.tasks_worklog_policy_hint"><?= htmlspecialchars($t('admin_settings.tasks_worklog_policy_hint', 'В строгом режиме списывать время могут только назначенные исполнители (или менеджеры). Если исполнитель не назначен (общие задачи), время могут списывать любые участники проекта.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label d-block" data-i18n="admin_settings.tasks_allow_assignee_edit_identity"><?= htmlspecialchars($t('admin_settings.tasks_allow_assignee_edit_identity', 'Разрешить исполнителю менять название и описание задачи'), ENT_QUOTES, 'UTF-8') ?></label>
            <div class="form-check form-switch mt-2">
              <input class="form-check-input" type="checkbox" role="switch" id="tasksAllowAssigneeEditIdentity">
              <label class="form-check-label fw-semibold" for="tasksAllowAssigneeEditIdentity" data-i18n="admin_settings.tasks_allow_assignee_edit_identity"><?= htmlspecialchars($t('admin_settings.tasks_allow_assignee_edit_identity', 'Разрешить исполнителю менять название и описание задачи'), ENT_QUOTES, 'UTF-8') ?></label>
            </div>
            <div class="form-text" data-i18n="admin_settings.tasks_allow_assignee_edit_identity_hint"><?= htmlspecialchars($t('admin_settings.tasks_allow_assignee_edit_identity_hint', 'По умолчанию менять название и описание могут только создатель задачи и менеджеры. Включение опции позволяет назначенному исполнителю обновлять заголовок и формулировку задачи.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
        <button class="btn crm-btn-primary mt-3" id="adminTaskSettingsSaveBtn" type="button" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Edit System Setting -->
<div class="modal fade" id="adminSettingEditModal" tabindex="-1" aria-labelledby="adminSettingEditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="adminSettingEditModalLabel" data-i18n="admin_settings.modal_edit_title"><?= htmlspecialchars($t('admin_settings.modal_edit_title', 'Редактирование настройки'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
          <span class="crm-admin-settings-key-badge" id="adminSettingModalKeyBadge"></span>
          <span class="badge bg-light text-muted border" id="adminSettingModalTypeBadge"></span>
        </div>
        <div id="adminSettingModalHint" class="small text-muted mb-3"></div>
        <div id="adminSettingModalError" class="alert alert-danger py-2 small mb-3" style="display:none;" role="alert"></div>
        <div id="adminSettingModalInputWrap"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm crm-btn-secondary" data-bs-dismiss="modal" data-i18n="admin_settings.modal_edit_cancel"><?= htmlspecialchars($t('admin_settings.modal_edit_cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm crm-btn-primary" id="adminSettingModalSaveBtn" data-i18n="admin_settings.modal_edit_save">
          <span class="spinner-border spinner-border-sm me-1" style="display:none;" aria-hidden="true"></span>
          <span class="btn-text"><?= htmlspecialchars($t('admin_settings.modal_edit_save', 'Сохранить изменения'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: View JSON -->
<div class="modal fade" id="adminSettingJsonModal" tabindex="-1" aria-labelledby="adminSettingJsonModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="adminSettingJsonModalLabel" data-i18n="admin_settings.modal_view_json"><?= htmlspecialchars($t('admin_settings.modal_view_json', 'Просмотр JSON'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2 d-flex justify-content-between align-items-center">
          <span class="crm-admin-settings-key-badge" id="adminSettingJsonKeyBadge"></span>
          <button type="button" class="btn btn-sm crm-btn-secondary" id="adminSettingJsonCopyBtn">
            <i class="fa-regular fa-copy me-1" aria-hidden="true"></i>
            <span id="adminSettingJsonCopyText" data-i18n="admin_settings.modal_copy_json"><?= htmlspecialchars($t('admin_settings.modal_copy_json', 'Копировать'), ENT_QUOTES, 'UTF-8') ?></span>
          </button>
        </div>
        <pre class="crm-admin-settings-json-pre mb-0" id="adminSettingJsonContent"></pre>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm crm-btn-secondary" data-bs-dismiss="modal" data-i18n="common.close"><?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Confirm admin settings action -->
<div class="modal fade" id="adminSettingsConfirmModal" tabindex="-1" aria-labelledby="adminSettingsConfirmModalLabel" aria-describedby="adminSettingsConfirmModalMessage" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="adminSettingsConfirmModalLabel" data-i18n="admin_settings.confirm_title"><?= htmlspecialchars($t('admin_settings.confirm_title', 'Подтвердите действие'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0" id="adminSettingsConfirmModalMessage"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm crm-btn-secondary" id="adminSettingsConfirmCancelBtn" data-bs-dismiss="modal" data-i18n="admin_settings.confirm_cancel"><?= htmlspecialchars($t('admin_settings.confirm_cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm crm-btn-primary" id="adminSettingsConfirmSubmitBtn" data-i18n="admin_settings.confirm_submit"><?= htmlspecialchars($t('admin_settings.confirm_submit', 'Продолжить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

</main></div></div>
