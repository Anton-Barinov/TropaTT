<?php declare(strict_types=1); ?>
<?php $title = $t('admin_settings.title', 'TropaTT — Системные настройки'); ?>
<body data-page="admin-settings" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-admin-settings-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="admin_settings.link_admin"><?= htmlspecialchars($t('admin_settings.link_admin', 'Админка'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="admin_settings.breadcrumb"><?= htmlspecialchars($t('admin_settings.breadcrumb', 'Системные настройки'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" data-i18n="admin_settings.page_title"><?= htmlspecialchars($t('admin_settings.page_title', 'Системные настройки'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_settings.subtitle"><?= htmlspecialchars($t('admin_settings.subtitle', 'Обзор без изменения критичных данных, безопасное редактирование и политика хранения.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="d-flex gap-2"><button id="adminSettingsRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="admin_settings.refresh_btn"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> <?= htmlspecialchars($t('admin_settings.refresh_btn', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button></div></div>

<div class="crm-admin-settings-note mb-3 d-flex align-items-start gap-2" role="note" data-i18n="admin_settings.note">
  <i class="fa-solid fa-triangle-exclamation crm-admin-settings-note-icon" aria-hidden="true"></i>
  <span><?= htmlspecialchars($t('admin_settings.note', 'Опасные изменения требуют подтверждения. Изменения записываются в журнал аудита через API.'), ENT_QUOTES, 'UTF-8') ?></span>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="crm-card crm-section-card h-100">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_system_title"><?= htmlspecialchars($t('admin_settings.section_system_title', 'Системные настройки'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_system_note"><?= htmlspecialchars($t('admin_settings.section_system_note', 'Только разрешенные настройки с безопасным редактированием.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div class="table-responsive"><table class="table table-sm crm-table mb-0"><thead><tr><th data-i18n="admin_settings.th_key"><?= htmlspecialchars($t('admin_settings.th_key', 'Ключ'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_settings.th_value"><?= htmlspecialchars($t('admin_settings.th_value', 'Значение'), ENT_QUOTES, 'UTF-8') ?></th><th></th></tr></thead><tbody id="adminSettingsSystemBody"><tr><td colspan="3" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="crm-card crm-section-card h-100">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_user_prefs_title"><?= htmlspecialchars($t('admin_settings.section_user_prefs_title', 'Пользовательские настройки'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_user_prefs_note"><?= htmlspecialchars($t('admin_settings.section_user_prefs_note', 'Персональные настройки профиля и уведомлений, без системных флагов.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div id="adminSettingsUserPrefsState" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
      <div id="adminSettingsUserPrefsForm" style="display:none;">
        <div class="mb-3">
          <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="adminPrefsSound"><label class="form-check-label fw-semibold" for="adminPrefsSound" data-i18n="admin_settings.pref_sound_label">Звук уведомлений</label><div class="form-text" data-i18n="admin_settings.pref_sound_hint">Воспроизводить звук при новом уведомлении</div></div>
          <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="adminPrefsQuietHours"><label class="form-check-label fw-semibold" for="adminPrefsQuietHours" data-i18n="admin_settings.pref_quiet_hours_label">Тихие часы</label><div class="form-text" data-i18n="admin_settings.pref_quiet_hours_hint">Отключить звук уведомлений в указанное время</div></div>
          <div class="row g-2 mt-1" id="adminPrefsQuietHoursRow" style="display:none;">
            <div class="col-6"><label class="form-label small" data-i18n="admin_settings.pref_quiet_start">Начало</label><input type="time" class="form-control form-control-sm" id="adminPrefsQuietStart" value="22:00"></div>
            <div class="col-6"><label class="form-label small" data-i18n="admin_settings.pref_quiet_end">Окончание</label><input type="time" class="form-control form-control-sm" id="adminPrefsQuietEnd" value="08:00"></div>
          </div>
        </div>
        <button class="btn btn-sm crm-btn-primary" id="adminPrefsSaveBtn" type="button" data-i18n="common.save">Сохранить</button>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="crm-card crm-section-card h-100">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_retention_title"><?= htmlspecialchars($t('admin_settings.section_retention_title', 'Политики хранения данных'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_retention_note"><?= htmlspecialchars($t('admin_settings.section_retention_note', 'Настройки жизненного цикла данных. Сначала предварительный расчет, затем применение.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div class="table-responsive"><table class="table table-sm crm-table mb-2"><thead><tr><th data-i18n="admin_settings.th_field"><?= htmlspecialchars($t('admin_settings.th_field', 'Поле'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_settings.th_days"><?= htmlspecialchars($t('admin_settings.th_days', 'Дней'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_settings.th_action"><?= htmlspecialchars($t('admin_settings.th_action', 'Действие'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="adminSettingsRetentionBody"><tr><td colspan="3" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
      <div id="adminSettingsRetentionState" class="text-muted small" data-i18n="admin_settings.retention_state_loading"><?= htmlspecialchars($t('admin_settings.retention_state_loading', 'Ожидание данных...'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="crm-card crm-section-card h-100">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_audit_title"><?= htmlspecialchars($t('admin_settings.section_audit_title', 'Последний аудит'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_audit_note"><?= htmlspecialchars($t('admin_settings.section_audit_note', 'Последние изменения настроек.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div id="adminSettingsAuditState" class="text-muted small mb-2" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
      <ul id="adminSettingsAuditList" class="list-group list-group-flush"></ul>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="crm-card crm-section-card h-100">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_cache_title"><?= htmlspecialchars($t('admin_settings.section_cache_title', 'Кэш API'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_cache_note"><?= htmlspecialchars($t('admin_settings.section_cache_note', 'Файловое кэширование ответов справочных эндпоинтов. После включения изменения вступают в силу немедленно.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div class="crm-section-body" id="adminCacheSection">
        <div class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>
  </div>
  <div class="col-lg-6" id="adminSystemInfoSection" style="display:none;">
    <div class="crm-card crm-section-card h-100">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_system_info_title"><?= htmlspecialchars($t('admin_settings.section_system_info_title', 'Информация о системе'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_system_info_note"><?= htmlspecialchars($t('admin_settings.section_system_info_note', 'Технические параметры окружения (только для root).'), ENT_QUOTES, 'UTF-8') ?></div></div><div class="d-flex gap-2"><button id="adminSystemInfoRefreshBtn" class="btn btn-sm crm-btn-secondary" type="button" data-i18n="admin_settings.system_info_refresh_btn"><?= htmlspecialchars($t('admin_settings.system_info_refresh_btn', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button></div></div>
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

<div class="row g-3 mb-3" id="adminFinanceSettingsSection" style="display:none;">
  <div class="col-12">
    <div class="crm-card crm-section-card h-100">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="admin_settings.section_finance_title"><?= htmlspecialchars($t('admin_settings.section_finance_title', 'Финансы'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="admin_settings.section_finance_note"><?= htmlspecialchars($t('admin_settings.section_finance_note', 'Валюта организации, вывод себестоимости из вознаграждения и автозакрытие периодов.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div class="crm-section-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="financeDefaultCurrency" data-i18n="admin_settings.finance_default_currency"><?= htmlspecialchars($t('admin_settings.finance_default_currency', 'Валюта организации'), ENT_QUOTES, 'UTF-8') ?></label>
            <input class="form-control crm-field-w-200" id="financeDefaultCurrency" maxlength="8" placeholder="RUB">
            <div class="form-text" data-i18n="admin_settings.finance_default_currency_hint"><?= htmlspecialchars($t('admin_settings.finance_default_currency_hint', 'Используется, когда у прайса или записи не задана своя валюта.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="financeCostFromPayoutMarkup" data-i18n="admin_settings.finance_markup_percent"><?= htmlspecialchars($t('admin_settings.finance_markup_percent', 'Вывод себестоимости из вознаграждения, %'), ENT_QUOTES, 'UTF-8') ?></label>
            <input class="form-control crm-field-w-200" id="financeCostFromPayoutMarkup" type="number" min="0" max="1000" step="0.01" placeholder="<?= htmlspecialchars($t('admin_settings.finance_markup_empty', 'выключено'), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-text" data-i18n="admin_settings.finance_markup_hint"><?= htmlspecialchars($t('admin_settings.finance_markup_hint', 'Пусто — вывод выключен. Затрагивает только новые записи; к истории применяется явным пересчётом.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="financeAutoCloseMode" data-i18n="admin_settings.finance_auto_close_mode"><?= htmlspecialchars($t('admin_settings.finance_auto_close_mode', 'Автозакрытие периодов'), ENT_QUOTES, 'UTF-8') ?></label>
            <select class="form-select crm-field-w-200" id="financeAutoCloseMode"><option value="off" data-i18n="admin_settings.finance_mode_off"><?= htmlspecialchars($t('admin_settings.finance_mode_off', 'Выключено'), ENT_QUOTES, 'UTF-8') ?></option><option value="weekly" data-i18n="admin_settings.finance_mode_weekly"><?= htmlspecialchars($t('admin_settings.finance_mode_weekly', 'Еженедельно'), ENT_QUOTES, 'UTF-8') ?></option><option value="monthly" data-i18n="admin_settings.finance_mode_monthly"><?= htmlspecialchars($t('admin_settings.finance_mode_monthly', 'Ежемесячно'), ENT_QUOTES, 'UTF-8') ?></option></select>
            <div class="form-text" data-i18n="admin_settings.finance_auto_close_hint"><?= htmlspecialchars($t('admin_settings.finance_auto_close_hint', 'Период закрывается по расписанию через заданную задержку после его окончания.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="financeAutoCloseLagDays" data-i18n="admin_settings.finance_lag_days"><?= htmlspecialchars($t('admin_settings.finance_lag_days', 'Задержка, дней'), ENT_QUOTES, 'UTF-8') ?></label>
            <input class="form-control crm-field-w-200" id="financeAutoCloseLagDays" type="number" min="0" max="90" step="1" value="5">
            <div class="form-text" data-i18n="admin_settings.finance_lag_hint"><?= htmlspecialchars($t('admin_settings.finance_lag_hint', 'От 0 до 90 дней.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
        <button class="btn crm-btn-primary mt-3" id="adminFinanceSaveBtn" type="button" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

</main></div></div>
