<?php declare(strict_types=1); ?>
<?php $title = $t('admin_settings.title', 'TropaTT — Системные настройки'); ?>
<body data-page="admin-settings" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-settings-page"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="admin_settings.page_title"><?= htmlspecialchars($t('admin_settings.page_title', 'Системные настройки'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_settings.subtitle"><?= htmlspecialchars($t('admin_settings.subtitle', 'Обзор без изменения критичных данных, безопасное редактирование и политика хранения.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="d-flex gap-2"><button id="adminSettingsRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="admin_settings.refresh_btn"><?= htmlspecialchars($t('admin_settings.refresh_btn', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button></div></div>

<div class="crm-admin-settings-note mb-3" role="note" data-i18n="admin_settings.note">
  <?= htmlspecialchars($t('admin_settings.note', 'Опасные изменения требуют подтверждения. Изменения записываются в журнал аудита через API.'), ENT_QUOTES, 'UTF-8') ?>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="crm-card crm-section-card h-100">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_user_prefs_title"><?= htmlspecialchars($t('admin_settings.section_user_prefs_title', 'Пользовательские настройки'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_user_prefs_note"><?= htmlspecialchars($t('admin_settings.section_user_prefs_note', 'Персональные настройки профиля и уведомлений, без системных флагов.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div id="adminSettingsUserPrefsState" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="crm-card crm-section-card h-100">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_system_title"><?= htmlspecialchars($t('admin_settings.section_system_title', 'Системные настройки'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_system_note"><?= htmlspecialchars($t('admin_settings.section_system_note', 'Только разрешенные настройки с безопасным редактированием.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th data-i18n="admin_settings.th_key"><?= htmlspecialchars($t('admin_settings.th_key', 'Ключ'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_settings.th_value"><?= htmlspecialchars($t('admin_settings.th_value', 'Значение'), ENT_QUOTES, 'UTF-8') ?></th><th></th></tr></thead><tbody id="adminSettingsSystemBody"><tr><td colspan="3" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-7">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_retention_title"><?= htmlspecialchars($t('admin_settings.section_retention_title', 'Политики хранения данных'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_retention_note"><?= htmlspecialchars($t('admin_settings.section_retention_note', 'Настройки жизненного цикла данных. Сначала предварительный расчет, затем применение.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <table class="table table-sm crm-table mb-2"><thead><tr><th data-i18n="admin_settings.th_field"><?= htmlspecialchars($t('admin_settings.th_field', 'Поле'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_settings.th_days"><?= htmlspecialchars($t('admin_settings.th_days', 'Дней'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_settings.th_action"><?= htmlspecialchars($t('admin_settings.th_action', 'Действие'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="adminSettingsRetentionBody"><tr><td colspan="3" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table>
      <div id="adminSettingsRetentionState" class="text-muted small" data-i18n="admin_settings.retention_state_loading"><?= htmlspecialchars($t('admin_settings.retention_state_loading', 'Ожидание данных...'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_audit_title"><?= htmlspecialchars($t('admin_settings.section_audit_title', 'Последний аудит'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_audit_note"><?= htmlspecialchars($t('admin_settings.section_audit_note', 'Последние изменения настроек.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div id="adminSettingsAuditState" class="text-muted small mb-2" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
      <ul id="adminSettingsAuditList" class="list-group list-group-flush"></ul>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_cache_title"><?= htmlspecialchars($t('admin_settings.section_cache_title', 'Кэш API'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_cache_note"><?= htmlspecialchars($t('admin_settings.section_cache_note', 'Файловое кэширование ответов справочных эндпоинтов. После включения изменения вступают в силу немедленно.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div class="crm-section-body p-3" id="adminCacheSection">
        <div class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1" id="adminSystemInfoSection" style="display:none;">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_system_info_title"><?= htmlspecialchars($t('admin_settings.section_system_info_title', 'Информация о системе'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_system_info_note"><?= htmlspecialchars($t('admin_settings.section_system_info_note', 'Технические параметры окружения (только для root).'), ENT_QUOTES, 'UTF-8') ?></div></div><div class="d-flex gap-2"><button id="adminSystemInfoRefreshBtn" class="btn btn-sm crm-btn-secondary" type="button" data-i18n="admin_settings.system_info_refresh_btn"><?= htmlspecialchars($t('admin_settings.system_info_refresh_btn', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button></div></div>
      <div class="row g-3">
        <div class="col-md-3"><div class="crm-info-panel"><small class="text-muted" data-i18n="admin_settings.sysinfo_api_version"><?= htmlspecialchars($t('admin_settings.sysinfo_api_version', 'Версия API'), ENT_QUOTES, 'UTF-8') ?></small><div class="fw-semibold" id="systemInfoPhpVersion">—</div></div></div>
        <div class="col-md-3"><div class="crm-info-panel"><small class="text-muted" data-i18n="admin_settings.sysinfo_timezone"><?= htmlspecialchars($t('admin_settings.sysinfo_timezone', 'Часовой пояс'), ENT_QUOTES, 'UTF-8') ?></small><div class="fw-semibold" id="systemInfoEnv">—</div></div></div>
        <div class="col-md-3"><div class="crm-info-panel"><small class="text-muted" data-i18n="admin_settings.sysinfo_database"><?= htmlspecialchars($t('admin_settings.sysinfo_database', 'База данных'), ENT_QUOTES, 'UTF-8') ?></small><div class="fw-semibold" id="systemInfoDb">—</div></div></div>
        <div class="col-md-3"><div class="crm-info-panel"><small class="text-muted" data-i18n="admin_settings.sysinfo_generated"><?= htmlspecialchars($t('admin_settings.sysinfo_generated', 'Сформировано'), ENT_QUOTES, 'UTF-8') ?></small><div class="fw-semibold" id="systemInfoUptime">—</div></div></div>
      </div>
      <div class="row g-3 mt-2">
        <div class="col-md-4"><div class="crm-info-panel"><small class="text-muted" data-i18n="admin_settings.sysinfo_files"><?= htmlspecialchars($t('admin_settings.sysinfo_files', 'Файлы'), ENT_QUOTES, 'UTF-8') ?></small><div class="small" id="systemInfoStorage">—</div></div></div>
        <div class="col-md-4"><div class="crm-info-panel"><small class="text-muted" data-i18n="admin_settings.sysinfo_temp_data"><?= htmlspecialchars($t('admin_settings.sysinfo_temp_data', 'Временные данные'), ENT_QUOTES, 'UTF-8') ?></small><div class="small" id="systemInfoCache">—</div></div></div>
        <div class="col-md-4"><div class="crm-info-panel"><small class="text-muted" data-i18n="admin_settings.sysinfo_logs"><?= htmlspecialchars($t('admin_settings.sysinfo_logs', 'Логи'), ENT_QUOTES, 'UTF-8') ?></small><div class="small" id="systemInfoLogs">—</div></div></div>
      </div>
    </div>
  </div>
</div>

</main></div></div>
