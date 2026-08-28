<?php declare(strict_types=1); ?>
<?php
$adminUpdatesEnglishPath = dirname(__DIR__, 3) . '/language/en-gb.php';
$adminUpdatesEnglish = is_file($adminUpdatesEnglishPath) ? (require $adminUpdatesEnglishPath) : [];
$au = static function (string $key, string $default = '') use ($t, $adminUpdatesEnglish): string {
  $value = $t('admin_updates.' . $key, '__missing_admin_updates_key__');
  if ($value !== '__missing_admin_updates_key__') {
    return $value;
  }
  $english = is_array($adminUpdatesEnglish['admin_updates'] ?? null) ? $adminUpdatesEnglish['admin_updates'] : [];
  return is_string($english[$key] ?? null) ? $english[$key] : $default;
};
$title = $au('title', 'TropaTT - Updates');
$auJs = [
  'loadingInitial' => $au('loading_initial', 'загружаем состояние'),
  'loadingRefresh' => $au('loading_refresh', 'обновляем статус'),
  'loadingCheck' => $au('loading_check', 'проверяем обновления'),
  'loadingChanges' => $au('loading_changes', 'загружаем изменения'),
  'loadingPreflight' => $au('loading_preflight', 'проверяем безопасность'),
  'loadingDownload' => $au('loading_download', 'подготавливаем архив'),
  'loadingApply' => $au('loading_apply', 'устанавливаем обновление'),
  'loadingRollback' => $au('loading_rollback', 'восстанавливаем backup'),
  'progressStep' => $au('progress_step', 'шаг {done} из {total}'),
  'phaseBackupFiles' => $au('phase_backup_files', 'бэкап файлов'),
  'phaseApplyFiles' => $au('phase_apply_files', 'установка файлов'),
  'phaseHealth' => $au('phase_health', 'проверка состояния'),
  'phaseBackupDb' => $au('phase_backup_db', 'бэкап базы данных'),
  'phaseMigrate' => $au('phase_migrate', 'миграции БД'),
  'phaseExtract' => $au('phase_extract', 'распаковка архива'),
  'phaseRestoreDb' => $au('phase_restore_db', 'восстановление БД'),
  'phaseRestoreFiles' => $au('phase_restore_files', 'восстановление файлов'),
  'phaseFinalize' => $au('phase_finalize', 'завершение'),
  'phaseFinalized' => $au('phase_finalized', 'готово'),
  'stepWorking' => $au('step_working', 'выполняется...'),
  'statusReady' => $au('status_ready', 'CRM работает штатно'),
  'statusChecking' => $au('status_checking', 'Проверяем...'),
  'statusNoUpdates' => $au('status_no_updates', 'Обновлений нет'),
  'statusUpdateFound' => $au('status_update_found', 'Есть обновление'),
  'statusPrepared' => $au('status_prepared', 'Архив подготовлен'),
  'statusFailed' => $au('status_failed', 'Есть ошибка'),
  'statusApplied' => $au('status_applied', 'Обновление установлено'),
  'statusUnknown' => $au('status_unknown', 'Неизвестно'),
  'recommendCheckTitle' => $au('recommend_check_title', 'Проверьте наличие обновлений'),
  'recommendCheckText' => $au('recommend_check_text', 'Проверка безопасна: она только сравнит вашу CRM с готовыми архивами на сервере обновлений.'),
  'recommendLatestTitle' => $au('recommend_latest_title', 'CRM уже актуальна'),
  'recommendLatestText' => $au('recommend_latest_text', 'Устанавливать ничего не нужно. Архив обновления не требуется, рисков для текущей версии нет.'),
  'recommendFoundTitle' => $au('recommend_found_title', 'Найдено обновление'),
  'recommendFoundText' => $au('recommend_found_text', 'Сначала запустите безопасную проверку. Файлы CRM на этом шаге не меняются.'),
  'recommendPreflightTitle' => $au('recommend_preflight_title', 'Проверка пройдена'),
  'recommendPreflightText' => $au('recommend_preflight_text', 'Теперь можно подготовить архив во временной папке. Рабочие файлы CRM еще не меняются.'),
  'recommendReadyTitle' => $au('recommend_ready_title', 'Можно устанавливать'),
  'recommendReadyText' => $au('recommend_ready_text', 'Перед установкой CRM создаст backup. Запускайте установку только если готовы к короткому maintenance-окну.'),
  'recommendFailedTitle' => $au('recommend_failed_title', 'Последняя операция завершилась ошибкой'),
  'recommendFailedText' => $au('recommend_failed_text', 'Проверьте детали операции. Если CRM работает нестабильно, используйте восстановление из backup.'),
  'primaryCheck' => $au('btn_check', 'Проверить обновления'),
  'primaryCheckAgain' => $au('btn_check_again', 'Проверить еще раз'),
  'primaryPreflight' => $au('btn_preflight', 'Проверить безопасность'),
  'primaryDownload' => $au('btn_download', 'Подготовить архив'),
  'primaryApply' => $au('btn_apply', 'Установить обновление'),
  'primaryRefresh' => $au('btn_refresh', 'Обновить статус'),
  'centerOk' => $au('center_ok', 'Сервер обновлений доступен'),
  'centerWarn' => $au('center_warn', 'Сервер обновлений требует проверки'),
  'centerMissing' => $au('center_missing', 'Сервер обновлений еще не проверен'),
  'centerUnavailable' => $au('center_unavailable', 'Сервер обновлений недоступен'),
  'centerUnavailableWithUrl' => $au('center_unavailable_with_url', 'Сервер обновлений недоступен: {url}'),
  'statusCenterDown' => $au('status_center_down', 'Сервер обновлений недоступен'),
  'recommendCenterDownTitle' => $au('recommend_center_down_title', 'Сервер обновлений недоступен'),
  'recommendCenterDownText' => $au('recommend_center_down_text', 'CRM не может проверить обновления, потому что сервер {url} сейчас не отвечает или еще не настроен.'),
  'versionKnown' => $au('version_known', 'Текущая сборка: {build}'),
  'versionUnknown' => $au('version_unknown', 'Текущая сборка не принята updater'),
  'jobKnown' => $au('job_known', 'Последняя операция: {state}'),
  'jobEmpty' => $au('job_empty', 'Операций еще не было'),
  'maintenanceOn' => $au('maintenance_on', 'Maintenance включен'),
  'maintenanceOff' => $au('maintenance_off', 'CRM работает штатно'),
  'maintenanceHeldTitle' => $au('maintenance_held_title', 'Обновление не завершено: CRM в режиме обслуживания'),
  'maintenanceHeldText' => $au('maintenance_held_text', 'Обновление прервалось после изменения файлов или базы данных, поэтому CRM остаётся в режиме обслуживания, чтобы не отдавать сломанное состояние. Откатитесь из backup или повторите обновление.'),
  'kpiInstalledUnknown' => $au('kpi_installed_unknown', 'unknown'),
  'kpiInstalledMetaUnknown' => $au('kpi_installed_meta_unknown', 'Локальная сборка еще не принята updater.'),
  'kpiTargetLatest' => $au('kpi_target_latest', 'latest'),
  'kpiTargetMetaLatest' => $au('kpi_target_meta_latest', 'Новых сборок для установки нет.'),
  'kpiTargetMetaFound' => $au('kpi_target_meta_found', 'Доступно обновление с {build}.'),
  'kpiPackageNone' => $au('kpi_package_none', 'не требуется'),
  'kpiPackageMetaNone' => $au('kpi_package_meta_none', 'Архив скачивать не нужно.'),
  'kpiRiskNone' => $au('kpi_risk_none', 'нет'),
  'kpiRiskMetaNone' => $au('kpi_risk_meta_none', 'Изменений для установки нет.'),
  'riskLow' => $au('risk_low', 'низкий'),
  'riskMedium' => $au('risk_medium', 'средний'),
  'riskHigh' => $au('risk_high', 'высокий'),
  'riskCritical' => $au('risk_critical', 'критичный'),
  'detailsEmpty' => $au('details_empty', 'Данные появятся после проверки.'),
  'changesHint' => $au('changes_hint', 'Нажмите «Что изменится?», чтобы увидеть резюме.'),
  'changesEmpty' => $au('changes_empty', 'Изменений для установки нет.'),
  'changesBadge' => $au('changes_badge', '{commits} коммитов / {files} файлов'),
  'changesLoadError' => $au('changes_load_error', 'Сервер обновлений не вернул список изменений.'),
  'commitsTitle' => $au('commits_title', 'Краткая история'),
  'filesTitle' => $au('files_title', 'Затронутые файлы'),
  'noCommits' => $au('no_commits', 'Нет коммитов'),
  'noFiles' => $au('no_files', 'Нет файлов'),
  'fileCol' => $au('file_col', 'Файл'),
  'scopeCol' => $au('scope_col', 'Зона'),
  'typeCol' => $au('type_col', 'Тип'),
  'packageCol' => $au('package_col', 'Архив'),
  'preflightOk' => $au('preflight_ok', 'Проверка пройдена'),
  'checkOk' => $au('check_ok', 'OK'),
  'checkFailed' => $au('check_failed', 'FAIL'),
  'checkLabels' => [
    'update_center' => $au('check_update_center', 'Сервер обновлений'),
    'manifest_signature' => $au('check_manifest_signature', 'Подпись манифеста'),
    'package_signature' => $au('check_package_signature', 'Подпись архива'),
    'package_url_accessible' => $au('check_package_url', 'Доступность архива'),
    'package_content_length' => $au('check_package_length', 'Размер архива'),
    'package_size_limit' => $au('check_package_limit', 'Ограничение размера архива'),
    'zip_extension' => $au('check_zip', 'ZIP-расширение PHP'),
    'openssl_extension' => $au('check_openssl', 'OpenSSL-расширение PHP'),
    'storage_writable' => $au('check_storage_writable', 'Доступ к хранилищу'),
    'api_writable' => $au('check_api_writable', 'Доступ к API-файлам'),
    'web_writable' => $au('check_web_writable', 'Доступ к web-файлам'),
    'no_forbidden_paths' => $au('check_forbidden_paths', 'Защищённые пути'),
    'free_space' => $au('check_free_space', 'Свободное место'),
    'no_active_lock' => $au('check_active_lock', 'Активная блокировка'),
  ],
  'preflightFailed' => $au('preflight_failed', 'Проверка не пройдена'),
  'applyError' => $au('apply_error', 'Ошибка: {message}'),
  'confirmApply' => $au('confirm_apply', 'Для реального применения обновления введите APPLY'),
  'confirmRollback' => $au('confirm_rollback', 'Rollback восстановит файлы из backup. Введите ROLLBACK'),
  'needJobDownload' => $au('need_job_download', 'Сначала выполните проверку безопасности, чтобы получить job_id.'),
  'needJobApply' => $au('need_job_apply', 'Нет job_id. Сначала выполните проверку безопасности и подготовку архива.'),
  'needJobRollback' => $au('need_job_rollback', 'Нет job_id для восстановления.'),
  'tokenError' => $au('token_error', 'Не удалось получить одноразовый updater token.'),
  'recoveryKeyError' => $au('recovery_key_error', 'Не удалось получить ключ восстановления.'),
  'recoveryKeyAgain' => $au('recovery_key_again', 'Сгенерировать новый ключ'),
  'errVersion' => $au('err_version', 'Не удалось загрузить текущую версию CRM.'),
  'errStatus' => $au('err_status', 'Не удалось загрузить статус обновлений.'),
  'errCheck' => $au('err_check', 'Не удалось проверить обновления.'),
  'errChanges' => $au('err_changes', 'Не удалось загрузить список изменений.'),
  'errPreflight' => $au('err_preflight', 'Не удалось выполнить безопасную проверку.'),
  'errDownload' => $au('err_download', 'Не удалось подготовить архив.'),
  'errApply' => $au('err_apply', 'Не удалось установить обновление.'),
  'errRollback' => $au('err_rollback', 'Не удалось восстановить backup.'),
  'errSession' => $au('err_session', 'Сессия истекла. Обновите страницу и войдите в CRM снова.'),
  'errForbidden' => $au('err_forbidden', 'У пользователя нет прав на управление обновлениями. Нужен root/admin или право управления настройками.'),
  'errGeneric' => $au('err_generic', 'Не удалось выполнить действие.'),
  'errTimeout' => $au('err_timeout', 'Запрос превысил таймаут.'),
  'errPreflightRequired' => $au('err_preflight_required', 'Сначала успешно выполните проверку безопасности.'),
  'errNoPackage' => $au('err_no_package', 'Для этой операции пакет обновления недоступен.'),
  'fieldTarget' => $au('field_target', 'Целевая сборка'),
  'fieldHealth' => $au('field_health', 'Проверка состояния'),
  'fieldStaging' => $au('field_staging', 'Подготовка архива'),
  'fieldPreview' => $au('field_preview', 'Первые файлы'),
  'bytesB' => $au('bytes_b', 'Б'),
  'bytesKb' => $au('bytes_kb', 'КБ'),
  'bytesMb' => $au('bytes_mb', 'МБ'),
  'bytesGb' => $au('bytes_gb', 'ГБ'),
  'checks_title' => $au('checks_title', 'Проверки'),
  'field_applied_files' => $au('field_applied_files', 'Обновлено файлов'),
  'field_backup' => $au('field_backup', 'Backup'),
  'field_files' => $au('field_files', 'Файлов подготовлено'),
  'field_installed_build' => $au('field_installed_build', 'Установленная сборка'),
  'field_job_id' => $au('field_job_id', 'Job ID'),
  'field_package' => $au('field_package', 'Архив'),
  'field_state' => $au('field_state', 'Состояние'),
  'field_updated' => $au('field_updated', 'Обновлено'),
  'field_error' => $au('field_error', 'Причина ошибки'),
  'field_failed_checks' => $au('field_failed_checks', 'Не пройдены проверки'),
  'field_log' => $au('field_log', 'Журнал операции'),
  'history_empty' => $au('history_empty', 'История появится после первой операции.'),
  'no_job' => $au('no_job', 'Нет операции'),
  'no_special_requirements' => $au('no_special_requirements', 'без особых требований'),
  'none' => $au('none', 'нет'),
  'not_loaded' => $au('not_loaded', 'Не загружено'),
  'plan_not_checked' => $au('plan_not_checked', 'Не проверено'),
  'field_migrations' => $au('field_migrations', 'Миграции БД'),
  'migrationsNone' => $au('migrations_none', 'не требовались'),
  'migrationsOk' => $au('migrations_ok', 'применены'),
  'migrationsFailed' => $au('migrations_failed', 'не применены (см. детали)'),
  'migrationsExecuted' => $au('migrations_executed', 'Применено миграций: {count}'),
  'field_db_backup' => $au('field_db_backup', 'Бэкап БД'),
  'dbBackupOk' => $au('db_backup_ok', 'создан'),
  'dbBackupFailed' => $au('db_backup_failed', 'не создан'),
  'dbBackupSkipped' => $au('db_backup_skipped', 'пропущен'),
  'field_db_restore' => $au('field_db_restore', 'Восстановление БД'),
  'dbRestoreOk' => $au('db_restore_ok', 'восстановлена'),
  'dbRestoreFailed' => $au('db_restore_failed', 'не восстановлена'),
  'dbRestoreSkipped' => $au('db_restore_skipped', 'нет бэкапа БД'),
  'mig_title' => $au('mig_title', 'Миграции БД'),
  'mig_subtitle' => $au('mig_subtitle', 'Принудительный запуск накопленных миграций базы данных. Полезно после обновления кода, если миграции не были применены автоматически.'),
  'mig_check_btn' => $au('mig_check_btn', 'Проверить миграции'),
  'mig_run_btn' => $au('mig_run_btn', 'Применить миграции'),
  'mig_loading_check' => $au('mig_loading_check', 'проверяем миграции...'),
  'mig_loading_run' => $au('mig_loading_run', 'применяем миграции...'),
  'mig_none_pending' => $au('mig_none_pending', 'Все миграции уже применены. Накопленных изменений нет.'),
  'mig_pending_count' => $au('mig_pending_count', 'Ожидает применения: {count}'),
  'mig_applied_count' => $au('mig_applied_count', 'Применено: {count}'),
  'mig_applied_list' => $au('mig_applied_list', 'Применённые миграции'),
  'mig_pending_list' => $au('mig_pending_list', 'Ожидающие миграции'),
  'mig_success' => $au('mig_success', 'Миграции успешно применены'),
  'mig_error' => $au('mig_error', 'Ошибка при применении миграций'),
  'mig_err_check' => $au('mig_err_check', 'Не удалось проверить статус миграций.'),
  'mig_err_run' => $au('mig_err_run', 'Не удалось применить миграции.'),
  'mig_confirm' => $au('mig_confirm', 'Будут применены все ожидающие миграции базы данных. Это безопасная операция — миграции используют защищённые DDL-запросы. Продолжить?'),
  'forceUnlockBtn' => $au('force_unlock_btn', 'Принудительно разблокировать'),
  'forceUnlockConfirm' => $au('force_unlock_confirm', 'Это удалит файл блокировки обновлений. Используйте только если уверены, что предыдущее обновление не выполняется. Продолжить?'),
  'forceUnlockLoading' => $au('force_unlock_loading', 'снимаем блокировку...'),
  'forceUnlockOk' => $au('force_unlock_ok', 'Блокировка снята. Теперь можно запустить проверку безопасности.'),
  'forceUnlockNone' => $au('force_unlock_none', 'Блокировки не было — ничего удалять не нужно.'),
  'forceUnlockError' => $au('force_unlock_error', 'Не удалось снять блокировку. Попробуйте ещё раз.'),
  'forceUnlockHint' => $au('force_unlock_hint', 'Обновление заблокировано. Если предыдущая установка была прервана, нажмите кнопку ниже.'),
  'lockCheckLabel' => $au('check_active_lock', 'Активная блокировка'),
  'applyInProgress' => $au('apply_in_progress', 'Обновление выполняется. Не обновляйте и не закрывайте страницу.'),
  'retryInProgress' => $au('retry_in_progress', 'Сетевая ошибка, повторяем... ({attempt} из {max})'),
];
?>
<body data-page="admin-updates" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-admin-updates-page">
  <style nonce="<?= $csp_nonce ?>">
    .crm-admin-updates-page {
      --update-ok: var(--crm-success);
      --update-warn: var(--crm-warning);
      --update-danger: var(--crm-danger);
      --update-ink: var(--crm-text);
      --update-line: var(--crm-border);
    }
    .updates-shell { max-width:1180px; margin:0 auto; }
    .updates-control { border:1px solid var(--update-line); border-radius:18px; background:var(--crm-surface); box-shadow:0 12px 30px rgba(15,23,42,.05); margin-bottom:16px; overflow:hidden; }
    .updates-control-main { display:grid; grid-template-columns:72px minmax(0,1fr) auto; gap:18px; align-items:center; padding:24px; }
    .updates-state-icon { width:56px; height:56px; border-radius:16px; display:grid; place-items:center; background:var(--crm-surface-2); color:var(--crm-text-soft); font-weight:900; font-size:1.55rem; }
    .updates-control[data-kind="ok"] .updates-state-icon { background:var(--crm-success-soft); color:var(--update-ok); }
    .updates-control[data-kind="warn"] .updates-state-icon { background:var(--crm-warning-soft); color:var(--update-warn); }
    .updates-control[data-kind="danger"] .updates-state-icon { background:var(--crm-danger-soft); color:var(--update-danger); }
    .updates-control-title { margin:0; color:var(--update-ink); font-size:1.55rem; line-height:1.18; font-weight:850; letter-spacing:-.02em; }
    .updates-control-text { margin:.45rem 0 0; color:var(--crm-text-soft); line-height:1.55; max-width:760px; }
    .updates-control-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
    .updates-control-actions .btn { border-radius:10px; padding:.62rem .95rem; font-weight:750; white-space:nowrap; }
    .updates-control-meta { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); border-top:1px solid var(--update-line); background:var(--crm-surface-2); }
    .updates-meta-item { padding:14px 16px; border-right:1px solid var(--update-line); min-width:0; }
    .updates-meta-item:last-child { border-right:0; }
    .updates-kpi-label { color:var(--crm-text-soft); font-size:.77rem; font-weight:700; }
    .updates-kpi-value { margin-top:6px; font-size:1.08rem; font-weight:820; color:var(--update-ink); line-height:1.15; overflow-wrap:anywhere; }
    .updates-kpi-value.loading { color:var(--crm-text-soft); }
    .updates-kpi-value.loading::after { content:''; display:inline-block; width:1em; height:1em; border:2px solid var(--crm-border-strong); border-top-color:var(--crm-primary); border-radius:50%; animation:updates-spin .8s linear infinite; margin-left:6px; vertical-align:middle; }
    @keyframes updates-spin { to { transform:rotate(360deg); } }
    .updates-phase-bar { display:flex; gap:8px; align-items:center; margin-top:14px; padding:10px 14px; border-radius:12px; background:var(--crm-info-soft); color:var(--crm-text); font-size:.85rem; font-weight:650; }
    .updates-phase-bar .updates-phase-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; background:var(--crm-text-soft); animation:updates-pulse 1.2s ease-in-out infinite; }
    .updates-phase-bar .updates-phase-dot.done { background:var(--crm-success); animation:none; }
    .updates-phase-bar .updates-phase-dot.active { background:var(--crm-primary); animation:updates-pulse 1.2s ease-in-out infinite; }
    @keyframes updates-pulse { 0%,100%{ opacity:.4; } 50%{ opacity:1; } }
    .updates-muted { color:var(--crm-text-soft); font-size:.86rem; line-height:1.45; margin-top:4px; }
    .updates-hero { display:none; }
    .updates-panel, .updates-card { border:1px solid var(--update-line); border-radius:18px; background:var(--crm-surface); box-shadow:0 12px 30px rgba(15,23,42,.05); }
    .updates-panel { padding:20px; }
    .updates-kicker { display:none; }
    .updates-title { margin:0; color:var(--update-ink); font-size:1.15rem; line-height:1.25; font-weight:800; letter-spacing:-.01em; }
    .updates-subtitle { max-width:760px; margin:8px 0 0; color:var(--crm-text-soft); line-height:1.55; font-size:.93rem; }
    .updates-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:16px; }
    .updates-actions .btn { border-radius:10px; padding:.52rem .82rem; font-weight:700; }
    .updates-status-card { padding:20px; display:flex; flex-direction:column; justify-content:space-between; gap:14px; }
    .updates-status-label { color:var(--crm-text-soft); font-weight:700; font-size:.78rem; }
    .updates-status-title { margin:6px 0 8px; color:var(--update-ink); font-size:1.12rem; line-height:1.25; font-weight:800; letter-spacing:-.01em; }
    .updates-status-text { margin:0; color:var(--crm-text-soft); line-height:1.55; }
    .updates-status-foot { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .updates-notice { display:none; margin:0 0 16px; border-radius:14px; padding:12px 14px; border:1px solid var(--crm-warning-soft); background:var(--crm-warning-soft); color:var(--crm-warning); font-weight:700; }
    .updates-notice.show { display:block; }
    .updates-notice.danger { border-color:var(--crm-danger-soft); background:var(--crm-danger-soft); color:var(--crm-danger); }
    .updates-notice.info { border-color:var(--crm-info-soft); background:var(--crm-info-soft); color:var(--crm-info); }
    .updates-notice.ok { border-color:var(--crm-success-soft); background:var(--crm-success-soft); color:var(--crm-success); }
    .updates-metrics { display:none; }
    .updates-pill-row { display:grid; gap:8px; margin-top:14px; }
    .updates-pill { display:flex; align-items:center; gap:8px; padding:8px 0; border-bottom:1px solid var(--crm-border); color:var(--crm-text); font-size:.84rem; font-weight:650; }
    .updates-pill:last-child { border-bottom:0; }
    .updates-dot { width:8px; height:8px; border-radius:50%; background:var(--crm-text-soft); }
    .updates-dot.ok { background:var(--update-ok); }
    .updates-dot.warn { background:var(--update-warn); }
    .updates-dot.danger { background:var(--update-danger); }
    .updates-info { border-radius:16px; border:1px solid var(--crm-info-soft); background:var(--crm-info-soft); color:var(--crm-text-soft); padding:13px 14px; margin:0 0 16px; line-height:1.55; }
    .updates-info strong { color:var(--crm-text); }
    .updates-card { padding:20px; margin-bottom:14px; }
    .updates-card-head { display:flex; justify-content:space-between; gap:14px; align-items:flex-start; margin-bottom:14px; }
    .updates-card h2 { margin:0; font-size:1rem; font-weight:800; color:var(--update-ink); letter-spacing:-.01em; }
    .updates-badge { display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:5px 9px; font-size:.74rem; font-weight:750; border:1px solid var(--crm-border-strong); background:var(--crm-surface-2); color:var(--crm-text); white-space:nowrap; }
    .updates-badge.ok { color:var(--crm-success); background:var(--crm-success-soft); border-color:var(--crm-success-soft); }
    .updates-badge.warn { color:var(--crm-warning); background:var(--crm-warning-soft); border-color:var(--crm-warning-soft); }
    .updates-badge.danger { color:var(--crm-danger); background:var(--crm-danger-soft); border-color:var(--crm-danger-soft); }
    .updates-badge.neutral { color:var(--crm-text-soft); background:var(--crm-surface-2); border-color:var(--crm-border-strong); }
    .updates-details-grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(300px,.75fr); gap:14px; }
    .updates-list { display:grid; gap:0; margin:0; padding:0; list-style:none; }
    .updates-list li { display:flex; justify-content:space-between; gap:12px; padding:10px 0; border-bottom:1px solid var(--crm-border); }
    .updates-list li:last-child { border-bottom:0; }
    .updates-list code { color:var(--update-ink); font-weight:800; white-space:normal; text-align:right; }
    .updates-file-table { width:100%; border-collapse:separate; border-spacing:0; overflow:hidden; }
    .updates-file-table th, .updates-file-table td { padding:10px 9px; border-bottom:1px solid var(--crm-border); vertical-align:top; font-size:.86rem; }
    .updates-file-table th { color:var(--crm-text-soft); font-size:.73rem; letter-spacing:.04em; text-transform:uppercase; }
    .updates-file-table td:first-child { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; overflow-wrap:anywhere; }
    .updates-empty { border:1px dashed var(--crm-border-strong); border-radius:14px; padding:14px; color:var(--crm-text-soft); background:var(--crm-surface-2); }
    .updates-recovery-key { margin:12px 0 0; border:1px solid var(--crm-border-strong); border-radius:12px; background:var(--crm-code-bg); color:var(--crm-code-text); padding:12px; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:.85rem; letter-spacing:.03em; user-select:all; }
    .updates-recovery-warn { margin-top:10px; padding:10px 14px; border:1px solid var(--crm-warning-soft); border-radius:12px; background:var(--crm-warning-soft); color:var(--crm-warning); font-size:.85rem; }
    .updates-raw { margin-top:12px; border-top:1px solid var(--crm-border); padding-top:10px; }
    .updates-raw summary { cursor:pointer; color:var(--crm-text-soft); font-weight:800; font-size:.84rem; }
    .updates-raw pre { margin:10px 0 0; max-height:320px; overflow:auto; border-radius:12px; background:var(--crm-code-bg); color:var(--crm-code-text); padding:12px; font-size:.78rem; }
    .updates-danger-zone { border-color:var(--crm-danger-soft); background:linear-gradient(180deg,var(--crm-surface),var(--crm-danger-soft)); }
    .updates-danger-zone h2 { color:var(--crm-danger); }
    .crm-admin-updates-page .crm-subtitle,
    .crm-admin-updates-page .updates-control-text,
    .crm-admin-updates-page .updates-muted,
    .crm-admin-updates-page .updates-pill-row,
    .crm-admin-updates-page .updates-raw,
    .crm-admin-updates-page #nextPlanBadge,
    .crm-admin-updates-page #detailsBadge,
    .crm-admin-updates-page .updates-details-grid [data-update-action="preflight"],
    .crm-admin-updates-page .updates-details-grid [data-update-action="download"],
    .crm-admin-updates-page .updates-details-grid [data-update-action="apply"],
    .crm-admin-updates-page .updates-details-grid [data-update-action="rollback"] {
      display:none !important;
    }
    @media (max-width:1120px) { .updates-control-main { grid-template-columns:56px minmax(0,1fr); } .updates-control-actions { grid-column:1 / -1; justify-content:flex-start; } .updates-control-meta,.updates-details-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width:720px) { .updates-shell { padding:0 2px; } .updates-control-main { grid-template-columns:1fr; padding:18px; } .updates-state-icon { width:48px; height:48px; border-radius:14px; } .updates-control-title { font-size:1.3rem; } .updates-control-actions .btn { width:100%; justify-content:center; } .updates-control-meta { grid-template-columns:1fr; } .updates-meta-item { border-right:0; border-bottom:1px solid var(--update-line); } .updates-meta-item:last-child { border-bottom:0; } .updates-panel,.updates-card,.updates-status-card { border-radius:16px; padding:16px; } }
  </style>

  <div class="updates-shell">
    <div id="updatesNotice" class="updates-notice"></div>
    <div class="crm-page-head">
      <div>
        <ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="nav.admin"><?= htmlspecialchars($t('nav.admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active"><?= htmlspecialchars($au('kicker', 'Центр обновлений'), ENT_QUOTES, 'UTF-8') ?></li></ol>
        <h1 class="crm-page-title"><?= htmlspecialchars($au('headline', 'Обновления системы'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="crm-subtitle"><?= htmlspecialchars($au('subtitle', 'Проверка версии, подготовка архива и безопасная установка обновлений CRM.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div class="crm-page-actions">
        <button class="btn crm-btn-secondary" type="button" data-update-action="refresh"><?= htmlspecialchars($au('btn_refresh', 'Обновить статус'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
    <section id="updatesControl" class="updates-control" data-kind="neutral">
      <div class="updates-control-main">
        <div id="updatesStateIcon" class="updates-state-icon">?</div>
        <div>
          <h2 id="nextTitle" class="updates-control-title"><?= htmlspecialchars($au('recommend_check_title', 'Проверьте наличие обновлений'), ENT_QUOTES, 'UTF-8') ?></h2>
          <p id="nextText" class="updates-control-text"><?= htmlspecialchars($au('recommend_check_text', 'Проверка безопасна: она только сравнит вашу CRM с готовыми архивами на сервере обновлений.'), ENT_QUOTES, 'UTF-8') ?></p>
          <div class="updates-status-foot mt-3">
            <span id="nextStatusBadge" class="updates-badge neutral"><?= htmlspecialchars($au('status_checking', 'Проверяем...'), ENT_QUOTES, 'UTF-8') ?></span>
            <span id="nextPlanBadge" class="updates-badge neutral"><?= htmlspecialchars($au('plan_not_checked', 'Не проверено'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="updates-pill-row">
            <span class="updates-pill"><span id="pillCenter" class="updates-dot"></span><span id="pillCenterText"><?= htmlspecialchars($au('center_checking', 'Сервер обновлений проверяется'), ENT_QUOTES, 'UTF-8') ?></span></span>
            <span class="updates-pill"><span id="pillVersion" class="updates-dot"></span><span id="pillVersionText"><?= htmlspecialchars($au('version_checking', 'Версия пока неизвестна'), ENT_QUOTES, 'UTF-8') ?></span></span>
            <span class="updates-pill"><span id="pillJob" class="updates-dot"></span><span id="pillJobText"><?= htmlspecialchars($au('job_checking', 'Операций еще не было'), ENT_QUOTES, 'UTF-8') ?></span></span>
            <span class="updates-pill"><span id="pillMaintenance" class="updates-dot"></span><span id="pillMaintenanceText"><?= htmlspecialchars($au('maintenance_checking', 'CRM работает штатно'), ENT_QUOTES, 'UTF-8') ?></span></span>
          </div>
        </div>
        <div class="updates-control-actions">
          <button id="primaryActionBtn" class="btn crm-btn-primary" type="button" data-update-action="check"><?= htmlspecialchars($au('btn_check', 'Проверить обновления'), ENT_QUOTES, 'UTF-8') ?></button>
          <button class="btn crm-btn-secondary" type="button" data-update-action="rollback"><?= htmlspecialchars($au('btn_rollback', 'Восстановить из backup'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </div>
      <div class="updates-control-meta">
        <div class="updates-meta-item">
          <div class="updates-kpi-label"><?= htmlspecialchars($au('kpi_installed', 'Текущая версия'), ENT_QUOTES, 'UTF-8') ?></div>
          <div id="kpiInstalled" class="updates-kpi-value">...</div>
          <p id="kpiInstalledMeta" class="updates-muted mb-0"><?= htmlspecialchars($au('kpi_installed_loading', 'Загружаем состояние CRM.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="updates-meta-item">
          <div class="updates-kpi-label"><?= htmlspecialchars($au('kpi_target', 'Доступная версия'), ENT_QUOTES, 'UTF-8') ?></div>
          <div id="kpiTarget" class="updates-kpi-value">...</div>
          <p id="kpiTargetMeta" class="updates-muted mb-0"><?= htmlspecialchars($au('kpi_target_loading', 'Покажем после проверки.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="updates-meta-item">
          <div class="updates-kpi-label"><?= htmlspecialchars($au('kpi_package', 'Что скачается'), ENT_QUOTES, 'UTF-8') ?></div>
          <div id="kpiPackage" class="updates-kpi-value">...</div>
          <p id="kpiPackageMeta" class="updates-muted mb-0"><?= htmlspecialchars($au('kpi_package_loading', 'Готовый архив или ничего.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="updates-meta-item">
          <div class="updates-kpi-label"><?= htmlspecialchars($au('kpi_risk', 'Уровень риска'), ENT_QUOTES, 'UTF-8') ?></div>
          <div id="kpiRisk" class="updates-kpi-value">...</div>
          <p id="kpiRiskMeta" class="updates-muted mb-0"><?= htmlspecialchars($au('kpi_risk_loading', 'Оценим перед установкой.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </div>
      <div id="bridgeNote" class="updates-empty mt-2" hidden></div>
    </section>
    <section class="updates-card">
      <div class="updates-card-head">
        <div>
          <h2><?= htmlspecialchars($au('details_title', 'Подробности'), ENT_QUOTES, 'UTF-8') ?></h2>
          <p class="updates-muted mb-0"><?= htmlspecialchars($au('details_subtitle', 'Открывайте только то, что нужно: изменения, проверку безопасности или историю последней операции.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <span id="detailsBadge" class="updates-badge neutral"><?= htmlspecialchars($au('plan_not_checked', 'Не проверено'), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <div class="updates-details-grid">
        <div>
          <details>
            <summary><strong><?= htmlspecialchars($au('changes_title', 'Что изменится'), ENT_QUOTES, 'UTF-8') ?></strong></summary>
            <div class="mt-3">
              <div class="d-flex justify-content-between gap-2 align-items-center mb-2">
                <p class="updates-muted mb-0"><?= htmlspecialchars($au('changes_subtitle', 'Понятное резюме изменений между вашей версией и доступной сборкой.'), ENT_QUOTES, 'UTF-8') ?></p>
                <span id="changesBadge" class="updates-badge neutral"><?= htmlspecialchars($au('not_loaded', 'Не загружено'), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <div id="changesContent" class="updates-empty"><?= htmlspecialchars($au('changes_hint', 'Нажмите «Что изменится?», чтобы увидеть резюме.'), ENT_QUOTES, 'UTF-8') ?></div>
              <details class="updates-raw"><summary><?= htmlspecialchars($au('technical_changes', 'Технические данные изменений'), ENT_QUOTES, 'UTF-8') ?></summary><pre id="updatesChangesRaw">{}</pre></details>
            </div>
          </details>
          <hr>
          <details>
            <summary><strong><?= htmlspecialchars($au('safety_title', 'Проверка перед установкой'), ENT_QUOTES, 'UTF-8') ?></strong></summary>
            <div class="mt-3">
              <p class="updates-muted"><?= htmlspecialchars($au('safety_subtitle', 'CRM проверит архив, права на файлы, подписи и свободное место до любых изменений.'), ENT_QUOTES, 'UTF-8') ?></p>
              <div id="preflightContent" class="updates-empty"><?= htmlspecialchars($au('preflight_hint', 'Когда обновление будет найдено, сначала запустите безопасную проверку. Она не применяет файлы.'), ENT_QUOTES, 'UTF-8') ?></div>
              <div class="updates-actions mt-3">
                <button class="btn crm-btn-primary" type="button" data-update-action="preflight"><?= htmlspecialchars($au('btn_preflight', 'Проверить безопасность'), ENT_QUOTES, 'UTF-8') ?></button>
                <button class="btn crm-btn-secondary" type="button" data-update-action="download"><?= htmlspecialchars($au('btn_download', 'Подготовить архив'), ENT_QUOTES, 'UTF-8') ?></button>
              </div>
              <details class="updates-raw"><summary><?= htmlspecialchars($au('technical_preflight', 'Технические данные проверки'), ENT_QUOTES, 'UTF-8') ?></summary><pre id="updatesPreflightRaw">{}</pre></details>
            </div>
          </details>
        </div>
        <div>
          <details>
            <summary><strong><?= htmlspecialchars($au('last_operation_title', 'Последняя операция'), ENT_QUOTES, 'UTF-8') ?></strong></summary>
            <div class="mt-3">
              <div class="d-flex justify-content-between gap-2 align-items-center mb-2">
                <p class="updates-muted mb-0"><?= htmlspecialchars($au('last_operation_subtitle', 'Что CRM делала с обновлениями в последний раз.'), ENT_QUOTES, 'UTF-8') ?></p>
                <span id="jobBadge" class="updates-badge neutral"><?= htmlspecialchars($au('no_job', 'Нет операции'), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <div id="jobContent" class="updates-empty"><?= htmlspecialchars($au('history_empty', 'История появится после первой операции.'), ENT_QUOTES, 'UTF-8') ?></div>
              <details class="updates-raw"><summary><?= htmlspecialchars($au('technical_status', 'Технические данные состояния'), ENT_QUOTES, 'UTF-8') ?></summary><pre id="updatesStatusRaw">{}</pre></details>
              <details class="updates-raw"><summary><?= htmlspecialchars($au('technical_plan', 'Технические данные плана'), ENT_QUOTES, 'UTF-8') ?></summary><pre id="updatesPlanRaw">{}</pre></details>
            </div>
          </details>
          <hr>
          <details>
            <summary><strong><?= htmlspecialchars($au('install_title', 'Установка и восстановление'), ENT_QUOTES, 'UTF-8') ?></strong></summary>
            <div class="mt-3">
              <p class="updates-muted"><?= htmlspecialchars($au('install_subtitle', 'Установка запускается только после проверки и ручного подтверждения. Перед заменой файлов создается backup.'), ENT_QUOTES, 'UTF-8') ?></p>
              <div class="updates-actions mt-0">
                <button class="btn crm-btn-danger-soft" type="button" data-update-action="apply"><?= htmlspecialchars($au('btn_apply', 'Установить обновление'), ENT_QUOTES, 'UTF-8') ?></button>
                <button class="btn crm-btn-secondary" type="button" data-update-action="rollback"><?= htmlspecialchars($au('btn_rollback', 'Восстановить из backup'), ENT_QUOTES, 'UTF-8') ?></button>
              </div>
              <div id="applyContent" class="updates-empty mt-3"><?= htmlspecialchars($au('apply_hint', 'Установка станет доступной по смыслу после успешной проверки и подготовки архива. Для применения потребуется ввести подтверждение.'), ENT_QUOTES, 'UTF-8') ?></div>
              <details class="updates-raw"><summary><?= htmlspecialchars($au('technical_apply', 'Технические данные установки'), ENT_QUOTES, 'UTF-8') ?></summary><pre id="updatesApplyRaw">{}</pre></details>
            </div>
          </details>
          <hr>
          <details>
            <summary><strong><?= htmlspecialchars($au('btn_recovery', 'Аварийное восстановление'), ENT_QUOTES, 'UTF-8') ?></strong></summary>
            <div class="mt-3">
              <p class="updates-muted"><?= htmlspecialchars($au('recovery_text', 'Если обновление прервалось и CRM осталась в режиме обслуживания, эта страница обновлений остаётся доступной. Для аварийного входа через /updater/rescue.php нужен ключ восстановления — он показывается один раз при установке. Здесь можно получить новый ключ.'), ENT_QUOTES, 'UTF-8') ?></p>
              <p class="updates-muted mb-1" style="font-size:.82rem"><strong><?= htmlspecialchars($au('recovery_alt_title', 'Альтернативный способ:'), ENT_QUOTES, 'UTF-8') ?></strong> <?= htmlspecialchars($au('recovery_alt_text', 'Если ключ восстановления утерян, в rescue.php можно войти с помощью APP_KEY из файла .env на сервере ( cat .env | grep APP_KEY ).'), ENT_QUOTES, 'UTF-8') ?></p>
              <div class="updates-actions mt-0">
                <button class="btn crm-btn-secondary" type="button" data-update-action="recovery-key"><?= htmlspecialchars($au('recovery_key_btn', 'Показать ключ восстановления'), ENT_QUOTES, 'UTF-8') ?></button>
              </div>
              <pre id="recoveryKeyValue" class="updates-recovery-key d-none"></pre>
              <div id="recoveryKeyWarn" class="updates-recovery-warn d-none"><?= htmlspecialchars($au('recovery_key_warn', 'Сохраните ключ — после перезагрузки страницы он больше не будет показан. Ключ скопирован в буфер обмена.'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          </details>
          <hr>
          <details open>
            <summary><strong><?= htmlspecialchars($au('mig_title', 'Миграции БД'), ENT_QUOTES, 'UTF-8') ?></strong></summary>
            <div class="mt-3">
              <p class="updates-muted"><?= htmlspecialchars($au('mig_subtitle', 'Принудительный запуск накопленных миграций базы данных. Полезно после обновления кода, если миграции не были применены автоматически.'), ENT_QUOTES, 'UTF-8') ?></p>
              <div id="migrationContent" class="updates-empty"><?= htmlspecialchars($au('mig_none_pending', 'Нажмите «Проверить миграции» для получения текущего статуса.'), ENT_QUOTES, 'UTF-8') ?></div>
              <div class="updates-actions mt-3">
                <button class="btn crm-btn-secondary" type="button" data-update-action="migration-check"><?= htmlspecialchars($au('mig_check_btn', 'Проверить миграции'), ENT_QUOTES, 'UTF-8') ?></button>
                <button class="btn crm-btn-primary" type="button" data-update-action="migration-up"><?= htmlspecialchars($au('mig_run_btn', 'Применить миграции'), ENT_QUOTES, 'UTF-8') ?></button>
              </div>
              <details class="updates-raw"><summary><?= htmlspecialchars($au('technical_status', 'Технические данные'), ENT_QUOTES, 'UTF-8') ?></summary><pre id="updatesMigrationRaw">{}</pre></details>
            </div>
          </details>
        </div>
      </div>
    </section>
  </div>
</main></div></div>
<script nonce="<?= $csp_nonce ?>">
(function () {
  const i18n = <?= json_encode($auJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const state = { status: null, version: null, plan: null, changes: null, preflight: null, download: null, apply: null, lastJobId: null };
  let crmSessionPromise = null;
  const $ = (id) => document.getElementById(id);
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
  const pretty = (value) => JSON.stringify(value || {}, null, 2);
  const tr = (key, fallback, vars = {}) => {
    let text = String(i18n[key] || fallback || key);
    Object.keys(vars).forEach((name) => { text = text.replaceAll(`{${name}}`, String(vars[name] ?? '')); });
    return text;
  };
  const extractJobId = (value, depth = 0) => {
    if (!value || typeof value !== 'object' || depth > 5) return '';
    if (typeof value.job_id === 'string' && value.job_id !== '') return value.job_id;
    for (const key of ['data', 'updater', 'preflight', 'result']) {
      const found = extractJobId(value[key], depth + 1);
      if (found) return found;
    }
    for (const nested of Object.values(value)) {
      const found = extractJobId(nested, depth + 1);
      if (found) return found;
    }
    return '';
  };
  const bytes = (value) => {
    const n = Number(value || 0);
    if (!n) return `0 ${tr('bytesB', 'Б')}`;
    const units = [tr('bytesB', 'Б'), tr('bytesKb', 'КБ'), tr('bytesMb', 'МБ'), tr('bytesGb', 'ГБ')];
    const index = Math.min(units.length - 1, Math.floor(Math.log(n) / Math.log(1024)));
    return `${(n / Math.pow(1024, index)).toFixed(index ? 1 : 0)} ${units[index]}`;
  };
  const badgeClass = (kind) => `updates-badge ${kind || 'neutral'}`;
  const dotClass = (kind) => `updates-dot ${kind || ''}`;
  const actionLabels = {
    'initial load': tr('loadingInitial', 'загружаем состояние'),
    refresh: tr('loadingRefresh', 'обновляем статус'),
    check: tr('loadingCheck', 'проверяем обновления'),
    changes: tr('loadingChanges', 'загружаем изменения'),
    install: tr('loadingApply', 'устанавливаем обновление'),
    preflight: tr('loadingPreflight', 'проверяем безопасность'),
    download: tr('loadingDownload', 'подготавливаем архив'),
    apply: tr('loadingApply', 'устанавливаем обновление'),
    rollback: tr('loadingRollback', 'восстанавливаем backup'),
    'force-unlock': tr('forceUnlockLoading', 'снимаем блокировку...')
  };

  function apiRouteFromUrl(url) {
    const raw = String(url || '');
    const query = raw.includes('?') ? raw.slice(raw.indexOf('?') + 1) : '';
    const params = new URLSearchParams(query);
    return String(params.get('route') || raw.replace(/^\/+/, '')).replace(/^\/+/, '');
  }

  // Resolve install-relative entry points from the actual deployment instead
  // of hardcoding '/api/index.php' or '/updater/index.php' (which breaks
  // subdirectory installs like /crm/api/index.php). apiBaseUrl is derived by
  // the core header from SCRIPT_NAME; webBase is the install's web directory.
  function installApiUrl(rawUrl) {
    const query = String(rawUrl || '').includes('?')
      ? String(rawUrl).slice(String(rawUrl).indexOf('?'))
      : '';
    const preset = window.CRM && window.CRM.config && typeof window.CRM.config.apiBaseUrl === 'string'
      ? window.CRM.config.apiBaseUrl
      : '';
    if (preset && preset !== '') {
      return preset.replace(/\/+$/, '') + query;
    }
    const cfg = window.CRM && window.CRM.config ? window.CRM.config : {};
    const webBase = String((cfg.webBase || '') || '').trim().replace(/\/+$/, '');
    const wb = webBase.replace(/\/web$/, '');
    return (wb !== '' ? wb : '') + '/api/index.php' + query;
  }

  function installUpdaterUrl(rawUrl) {
    const query = String(rawUrl || '').includes('?')
      ? String(rawUrl).slice(String(rawUrl).indexOf('?'))
      : '';
    const cfg = window.CRM && window.CRM.config ? window.CRM.config : {};
    const webBase = String((cfg.webBase || '') || '').trim().replace(/\/+$/, '');
    const wb = webBase.replace(/\/web$/, '');
    return (wb !== '' ? wb : '') + '/updater/index.php' + query;
  }

  async function waitForCrmApi() {
    if (window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function') return window.CRM.api;
    const started = Date.now();
    while (Date.now() - started < 5000) {
      await new Promise((resolve) => setTimeout(resolve, 100));
      if (window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function') return window.CRM.api;
    }
    return null;
  }

  async function ensureCrmSession(crmApi, method) {
    const writeMethod = ['POST', 'PATCH', 'PUT', 'DELETE'].includes(String(method || 'GET').toUpperCase());
    if (!writeMethod || !crmApi || typeof crmApi.getCsrfToken !== 'function' || typeof crmApi.me !== 'function') return;
    if (crmApi.getCsrfToken()) return;
    if (!crmSessionPromise) {
      crmSessionPromise = crmApi.me().catch((err) => {
        crmSessionPromise = null;
        throw err;
      });
    }
    await crmSessionPromise;
  }

  function normalizeApiError(err) {
    const envelope = err && err.envelope ? err.envelope : null;
    if (envelope) {
      envelope.http_status = envelope.meta && envelope.meta.status ? Number(envelope.meta.status) : 0;
      return envelope;
    }
    return {success: false, code: 'API_ERROR', message: String(err && err.message ? err.message : err), http_status: 0};
  }

  async function api(url, options = {}) {
    const route = apiRouteFromUrl(url);
    if (route.indexOf('api/v1/') === 0) {
      const crmApi = await waitForCrmApi();
      if (crmApi) {
        try {
          await ensureCrmSession(crmApi, options.method || 'GET');
          let body = options.body;
          if (typeof body === 'string' && body !== '') {
            try { body = JSON.parse(body); } catch (e) {}
          }
          return await crmApi.request(route, { method: options.method || 'GET', body, headers: options.headers || {}, timeoutMs: options.timeoutMs || 30000 });
        } catch (err) {
          return normalizeApiError(err);
        }
      }
    }
    const csrfToken = (window.CRM && window.CRM.api && typeof window.CRM.api.getCsrfToken === 'function') ? window.CRM.api.getCsrfToken() : decodeURIComponent((document.cookie.match(/crm_csrf_token=([^;]+)/) || [])[1] || '');
    const headers = Object.assign({'Content-Type': 'application/json'}, options.headers || {});
    if (csrfToken && !headers['X-CSRF-Token']) headers['X-CSRF-Token'] = csrfToken;
    // Raw fetch for the local updater: enforce an explicit timeout so a
    // hanging proxy, WAF or dropped connection cannot freeze the update loop
    // forever. The updater step machine keeps every request short; this only
    // bounds the client-side wait, and runUpdaterSteps() retries the step.
    const timeoutMs = Math.max(0, Number(options.timeoutMs || 0));
    const controller = typeof AbortController !== 'undefined' && timeoutMs > 0 ? new AbortController() : null;
    const timeoutHandle = controller ? setTimeout(() => controller.abort(), timeoutMs) : null;
    let res;
    let text;
    // Rewrite hardcoded entry points to this install's real locations so a
    // subdirectory deployment (e.g. /crm/web/) talks to its own API/updater.
    const rawUrl = String(url || '');
    const fetchUrl = rawUrl.indexOf('/api/index.php') === 0
      ? installApiUrl(rawUrl)
      : (rawUrl.indexOf('/updater/index.php') === 0 ? installUpdaterUrl(rawUrl) : rawUrl);
    try {
      res = await fetch(fetchUrl, Object.assign({credentials: 'same-origin', headers, signal: controller ? controller.signal : undefined}, options));
      // The body read is inside the same try: a proxy that resets the
      // connection mid-body also surfaces as a retryable network error
      // instead of an unmarked exception that aborts the update loop.
      text = await res.text();
    } catch (fetchError) {
      if (timeoutHandle) clearTimeout(timeoutHandle);
      const aborted = controller && fetchError && fetchError.name === 'AbortError';
      const networkError = new Error(aborted ? tr('errTimeout', 'Запрос превысил таймаут.') : String((fetchError && fetchError.message) || fetchError));
      networkError.isNetwork = true;
      throw networkError;
    }
    if (timeoutHandle) clearTimeout(timeoutHandle);
    let json;
    try { json = JSON.parse(text); } catch (e) { json = {success: false, code: 'INVALID_JSON', message: text.slice(0, 300)}; }
    if (!res.ok && json.success !== false) json.success = false;
    json.http_status = res.status;
    return json;
  }

  function showNotice(message, type) {
    const box = $('updatesNotice');
    if (!box) return;
    const cls = type ? `updates-notice show ${type}` : 'updates-notice show';
    box.className = cls;
    box.textContent = message;
  }

  function clearNotice() {
    const box = $('updatesNotice');
    if (!box) return;
    box.className = 'updates-notice';
    box.textContent = '';
  }

  function errorMessage(payload, fallback) {
    const status = Number(payload && payload.http_status || 0);
    const code = String(payload && payload.code || '').toUpperCase();
    if (status === 401) return tr('errSession', 'Сессия истекла. Обновите страницу и войдите в CRM снова.');
    if (status === 403) return tr('errForbidden', 'У пользователя нет прав на управление обновлениями. Нужен root/admin или право управления настройками.');
    if (code === 'PREFLIGHT_REQUIRED') return tr('errPreflightRequired', 'Сначала успешно выполните проверку безопасности.');
    if (code === 'NO_PACKAGE') return tr('errNoPackage', 'Для этой операции пакет обновления недоступен.');
    if (code === 'PREFLIGHT_FAILED') return tr('errPreflight', 'Не удалось выполнить безопасную проверку.');
    if (code === 'DOWNLOAD_FAILED') return tr('errDownload', 'Не удалось подготовить архив.');
    if (code === 'APPLY_FAILED') return tr('errApply', 'Не удалось установить обновление.');
    if (code === 'ROLLBACK_FAILED') return tr('errRollback', 'Не удалось восстановить backup.');
    if (code === 'UPDATER_ERROR') return tr('errGeneric', 'Не удалось выполнить действие.');
    return fallback || tr('errGeneric', 'Не удалось выполнить действие.');
  }

  function jobErrorMessage(job) {
    if (!job) return '';
    const code = String(job.error_code || '').toUpperCase();
    const knownByCode = {
      PREFLIGHT_FAILED: 'errPreflight',
      DOWNLOAD_FAILED: 'errDownload',
      APPLY_FAILED: 'errApply',
      ROLLBACK_FAILED: 'errRollback',
      PREFLIGHT_REQUIRED: 'errPreflightRequired',
      NO_PACKAGE: 'errNoPackage'
    };
    if (knownByCode[code]) return tr(knownByCode[code], 'Не удалось выполнить действие.');
    const legacy = String(job.error || '');
    if (legacy === 'Preflight checks failed.') return tr('errPreflight', 'Не удалось выполнить безопасную проверку.');
    if (legacy === 'Package download failed.') return tr('errDownload', 'Не удалось подготовить архив.');
    if (legacy === 'Update package preparation failed.') return tr('errDownload', 'Не удалось подготовить архив.');
    if (legacy === 'Update apply failed.') return tr('errApply', 'Не удалось установить обновление.');
    if (legacy === 'Rollback failed.') return tr('errRollback', 'Не удалось восстановить backup.');
    return legacy ? tr('errGeneric', 'Не удалось выполнить действие.') : '';
  }

  function ensureSuccess(payload, fallback) {
    if (payload && payload.success === false) throw new Error(errorMessage(payload, fallback));
    return payload;
  }

  function setBadge(id, kind, text) {
    const el = $(id);
    if (!el) return;
    el.className = badgeClass(kind);
    el.textContent = text;
  }

  function setPrimary(action, label) {
    const btn = $('primaryActionBtn');
    if (!btn) return;
    btn.setAttribute('data-update-action', action);
    btn.textContent = label;
  }

  function renderControlState(kind) {
    const control = $('updatesControl');
    const icon = $('updatesStateIcon');
    if (!control || !icon) return;
    const normalized = kind || 'neutral';
    control.dataset.kind = normalized;
    var iconClass = normalized === 'ok' ? 'fa-circle-check' : (normalized === 'danger' ? 'fa-circle-xmark' : (normalized === 'warn' ? 'fa-triangle-exclamation' : 'fa-circle-question'));
    icon.innerHTML = '<i class="fa-solid ' + iconClass + '" aria-hidden="true"></i>';
  }

  function updateCenterStatus() {
    const statusCenter = state.status && state.status.update_center ? state.status.update_center : null;
    const planCenter = state.plan && state.plan.update_center ? state.plan.update_center : null;
    const rawPlan = state.plan && state.plan.raw && state.plan.raw.ok === false ? state.plan.raw : null;
    return statusCenter || planCenter || rawPlan;
  }

  function updateCenterUnavailable() {
    const center = updateCenterStatus();
    return !!(center && center.ok === false);
  }

  function updateCenterUrl() {
    const center = updateCenterStatus();
    const url = center && center.url ? String(center.url) : 'https://update.tropatt.com';
    return url.replace(/\/api\/v1\/.*$/, '').replace(/\/$/, '');
  }

  function latestJobIsStaged() {
    const latest = state.status && state.status.latest_job;
    if (!latest || latest.state !== 'staging_ready' || !latest.can_resume) return false;
    // Only resume when the staged package still matches the currently
    // available target. If a newer build was published while the archive was
    // staged, re-prepare instead of applying an older staged package.
    const stagedTarget = latest.plan && latest.plan.target_build;
    const planTarget = state.plan && state.plan.target_build;
    if (stagedTarget && planTarget && stagedTarget !== planTarget) return false;
    return true;
  }

  function pipelineKind() {
    const latest = state.status && state.status.latest_job;
    if (latest && latest.state === 'failed') return 'danger';
    if (updateCenterUnavailable()) return 'danger';
    if (state.plan && state.plan.update_available !== true) return 'ok';
    if (latest && latest.state === 'applied') return 'ok';
    if (state.download || latestJobIsStaged() || state.preflight) return 'warn';
    return 'neutral';
  }

  function pipelineText() {
    const latest = state.status && state.status.latest_job;
    if (updateCenterUnavailable()) return tr('statusCenterDown', 'Сервер обновлений недоступен');
    if (state.plan && state.plan.update_available !== true) return tr('statusNoUpdates', 'Обновлений нет');
    if (latest && latest.state === 'applied') return tr('statusApplied', 'Обновление установлено');
    if (state.download || latestJobIsStaged()) return tr('statusPrepared', 'Архив подготовлен');
    if (state.preflight && state.preflight.ok === true) return tr('preflightOk', 'Проверка пройдена');
    if (state.plan && state.plan.update_available === true) return tr('statusUpdateFound', 'Есть обновление');
    return tr('statusChecking', 'Проверяем...');
  }

  function failedJobBlocksNewUpdate(latest) {
    if (!latest || latest.state !== 'failed') return false;
    if (state.status && state.status.maintenance) return true;
    if (String(latest.backup_id || '').trim() !== '') return true;
    // `can_rollback` may be true on a failed apply even when the failure
    // happened before a backup was created. In that case there is nothing to
    // restore, so the failed job must not block a fresh update attempt.
    if (latest.maintenance_held === true) return true;
    const appliedCount = Number(
      latest.applied_file_count
      ?? (latest.applied && latest.applied.count)
      ?? 0
    );
    return appliedCount > 0;
  }

  function updateRecommendation() {
    const latest = state.status && state.status.latest_job;
    const plan = state.plan;
    const failedBlocksNewUpdate = failedJobBlocksNewUpdate(latest);
    if (updateCenterUnavailable()) {
      const url = updateCenterUrl();
      $('nextTitle').textContent = tr('recommendCenterDownTitle', 'Сервер обновлений недоступен');
      $('nextText').textContent = tr('recommendCenterDownText', 'CRM не может проверить обновления, потому что сервер {url} сейчас не отвечает или еще не настроен.', {url});
      setPrimary('refresh', tr('primaryRefresh', 'Обновить статус'));
    } else if (latest && latest.state === 'failed' && failedBlocksNewUpdate) {
      const heldMaintenance = !!(state.status && state.status.maintenance);
      if (heldMaintenance) {
        $('nextTitle').textContent = tr('maintenanceHeldTitle', 'Обновление не завершено: CRM в режиме обслуживания');
        $('nextText').textContent = tr('maintenanceHeldText', 'Обновление прервалось после изменения файлов или базы данных, поэтому CRM остаётся в режиме обслуживания, чтобы не отдавать сломанное состояние. Откатитесь из backup или повторите обновление.');
        showNotice(tr('maintenanceHeldText', 'Обновление прервалось после изменения файлов или базы данных, поэтому CRM остаётся в режиме обслуживания, чтобы не отдавать сломанное состояние. Откатитесь из backup или повторите обновление.'), 'danger');
      } else {
        $('nextTitle').textContent = tr('recommendFailedTitle', 'Последняя операция завершилась ошибкой');
        $('nextText').textContent = tr('recommendFailedText', 'Проверьте детали операции. Если CRM работает нестабильно, используйте восстановление из backup.');
        if (latest.error || latest.error_code) {
          showNotice(jobErrorMessage(latest));
        } else {
          clearNotice();
        }
      }
      setPrimary('refresh', tr('primaryRefresh', 'Обновить статус'));
    } else if (plan && plan.update_available === false) {
      $('nextTitle').textContent = tr('recommendLatestTitle', 'CRM уже актуальна');
      $('nextText').textContent = tr('recommendLatestText', 'Устанавливать ничего не нужно. Архив обновления не требуется, рисков для текущей версии нет.');
      setPrimary('check', tr('primaryCheckAgain', 'Проверить еще раз'));
    } else if (state.download || latestJobIsStaged()) {
      $('nextTitle').textContent = tr('recommendReadyTitle', 'Можно устанавливать');
      $('nextText').textContent = tr('recommendReadyText', 'Перед установкой CRM создаст backup. Запускайте установку только если готовы к короткому maintenance-окну.');
      setPrimary('install', tr('primaryApply', 'Установить обновление'));
    } else if (state.preflight && state.preflight.ok === true) {
      $('nextTitle').textContent = tr('recommendPreflightTitle', 'Проверка пройдена');
      $('nextText').textContent = tr('recommendPreflightText', 'Теперь можно подготовить архив во временной папке. Рабочие файлы CRM еще не меняются.');
      setPrimary('install', tr('primaryApply', 'Установить обновление'));
    } else if (plan && plan.update_available === true) {
      $('nextTitle').textContent = tr('recommendFoundTitle', 'Найдено обновление');
      $('nextText').textContent = tr('recommendFoundText', 'Сначала запустите безопасную проверку. Файлы CRM на этом шаге не меняются.');
      setPrimary('install', tr('primaryApply', 'Установить обновление'));
    } else {
      $('nextTitle').textContent = tr('recommendCheckTitle', 'Проверьте наличие обновлений');
      $('nextText').textContent = tr('recommendCheckText', 'Проверка безопасна: она только сравнит вашу CRM с готовыми архивами на сервере обновлений.');
      setPrimary('check', tr('primaryCheck', 'Проверить обновления'));
    }
    setBadge('nextStatusBadge', pipelineKind(), pipelineText());
    setBadge('nextPlanBadge', updateCenterUnavailable() ? 'danger' : (plan ? (plan.update_available === true ? 'warn' : (plan.update_available === false ? 'ok' : 'neutral')) : 'neutral'), updateCenterUnavailable() ? tr('statusCenterDown', 'Сервер обновлений недоступен') : (plan ? (plan.update_available === true ? tr('statusUpdateFound', 'Есть обновление') : (plan.update_available === false ? tr('statusNoUpdates', 'Обновлений нет') : tr('statusUnknown', 'Неизвестно'))) : tr('plan_not_checked', 'Не проверено')));
    setBadge('detailsBadge', pipelineKind(), pipelineText());
    renderControlState(pipelineKind());
  }

  function setLoading(action, loading) {
    document.querySelectorAll('[data-update-action]').forEach((btn) => {
      if (loading) {
        btn.dataset.wasDisabled = btn.disabled ? '1' : '0';
        btn.disabled = true;
      } else {
        btn.disabled = btn.dataset.wasDisabled === '1';
        delete btn.dataset.wasDisabled;
      }
    });
    setBadge('nextStatusBadge', loading ? 'warn' : pipelineKind(), loading ? actionLabels[action] || action : pipelineText());
    setBadge('detailsBadge', loading ? 'warn' : pipelineKind(), loading ? actionLabels[action] || action : pipelineText());
    renderControlState(loading ? 'warn' : pipelineKind());
    // Toggle loading spinner on KPI values
    ['kpiTarget', 'kpiPackage', 'kpiRisk'].forEach((id) => {
      const el = $(id);
      if (el && loading && !el.dataset.realValue) {
        el.dataset.realValue = el.textContent;
        el.classList.add('loading');
      } else if (el && !loading) {
        delete el.dataset.realValue;
        el.classList.remove('loading');
      }
    });
    // Show/hide loading meta text under KPI values
    ['kpiTargetMeta', 'kpiPackageMeta', 'kpiRiskMeta'].forEach((id) => {
      const el = $(id);
      if (el && loading && !el.dataset.realText) {
        el.dataset.realText = el.textContent;
        el.textContent = tr('kpi_target_loading', 'Покажем после проверки.');
      } else if (el && !loading && el.dataset.realText) {
        // Don't restore — renderPlan/renderStatus will set the real value
        delete el.dataset.realText;
      }
    });
  }

  function renderStatus() {
    const status = state.status || {};
    const version = state.version || {};
    const installed = version.state ? version : (status.installed_core || {});
    const latest = status.latest_job || null;
    const auditExists = !!status.audit;
    const auditOk = !!(status.audit && status.audit.health_ok);
    const center = updateCenterStatus();
    const centerOk = !!(center && center.ok === true);
    const centerDown = !!(center && center.ok === false);
    const centerUrl = updateCenterUrl();
    const maintenance = !!status.maintenance;
    $('pillCenter').className = dotClass(centerDown ? 'danger' : ((centerOk || auditOk) ? 'ok' : (auditExists ? 'warn' : '')));
    $('pillCenterText').textContent = centerDown ? tr('centerUnavailableWithUrl', 'Сервер обновлений недоступен: {url}', {url: centerUrl}) : ((centerOk || auditOk) ? tr('centerOk', 'Сервер обновлений доступен') : (auditExists ? tr('centerWarn', 'Сервер обновлений требует проверки') : tr('centerMissing', 'Сервер обновлений еще не проверен')));
    $('pillVersion').className = dotClass(installed.core_build ? 'ok' : 'warn');
    $('pillVersionText').textContent = installed.core_build ? tr('versionKnown', 'Текущая сборка: {build}', {build: installed.core_build}) : tr('versionUnknown', 'Текущая сборка не принята updater');
    $('pillJob').className = dotClass(latest && latest.state === 'failed' ? 'danger' : latest ? 'ok' : 'warn');
    $('pillJobText').textContent = latest ? tr('jobKnown', 'Последняя операция: {state}', {state: latest.state}) : tr('jobEmpty', 'Операций еще не было');
    $('pillMaintenance').className = dotClass(maintenance ? 'danger' : 'ok');
    $('pillMaintenanceText').textContent = maintenance ? tr('maintenanceOn', 'Maintenance включен') : tr('maintenanceOff', 'CRM работает штатно');
    // Guard 3 (maintenance hold): a failed update that left maintenance ON
    // must be surfaced prominently so the admin rolls back or retries.
    if (maintenance && latest && latest.state === 'failed') {
      showNotice(tr('maintenanceHeldText', 'Обновление прервалось после изменения файлов или базы данных, поэтому CRM остаётся в режиме обслуживания, чтобы не отдавать сломанное состояние. Откатитесь из backup или повторите обновление.'), 'danger');
    } else if (!maintenance || (latest && (latest.state === 'applied' || latest.state === 'rolled_back'))) {
      // Clear any stale progress notice when:
      //  - maintenance is off (normal state), OR
      //  - the job reached a terminal state (applied/rolled_back) —
      //    maintenance may still be momentarily ON during finalization,
      //    but the update is done and the notice must not persist.
      clearNotice();
    }
    $('kpiInstalled').textContent = installed.core_build || tr('kpiInstalledUnknown', 'unknown');
    $('kpiInstalledMeta').textContent = installed.source_sha ? `SHA ${String(installed.source_sha).slice(0, 12)}...` : tr('kpiInstalledMetaUnknown', 'Локальная сборка еще не принята updater.');
    $('updatesStatusRaw').textContent = pretty(status);
    setBadge('jobBadge', latest ? (latest.state === 'failed' ? 'danger' : 'ok') : 'neutral', latest ? latest.state : tr('no_job', 'Нет операции'));
    $('jobContent').innerHTML = latest ? list({
      [tr('field_job_id', 'Job ID')]: latest.job_id || 'n/a',
      [tr('field_state', 'Состояние')]: latest.state || 'n/a',
      [tr('field_backup', 'Backup')]: latest.backup_id || tr('none', 'нет'),
      [tr('field_files', 'Файлов подготовлено')]: latest.staged_file_count || 0,
      [tr('field_updated', 'Обновлено')]: latest.updated_at || 'n/a',
      ...((latest.error || latest.error_code) ? {[tr('field_error', 'Причина ошибки')]: jobErrorMessage(latest)} : {}),
      ...(Array.isArray(latest.failed_checks) && latest.failed_checks.length ? {[tr('field_failed_checks', 'Не пройдены проверки')]: latest.failed_checks.join(', ')} : {}),
    }) : `<div class="updates-empty">${esc(tr('history_empty', 'История появится после первой операции.'))}</div>`;
    updateRecommendation();
  }

  function renderPlan() {
    const plan = state.plan;
    $('updatesPlanRaw').textContent = pretty(plan);
    if (!plan) return;
    const pkg = plan.recommended_package;
    const hasUpdate = plan.update_available === true;
    const centerDown = updateCenterUnavailable();
    const riskMap = { low: tr('riskLow', 'низкий'), medium: tr('riskMedium', 'средний'), high: tr('riskHigh', 'высокий'), critical: tr('riskCritical', 'критичный') };
    const rawRisk = plan.summary && plan.summary.risk_level ? plan.summary.risk_level : null;
    const risk = rawRisk ? (riskMap[rawRisk] || rawRisk) : (hasUpdate ? tr('statusUnknown', 'Неизвестно') : tr('kpiRiskNone', 'нет'));
    var currentBuild = (state.version && state.version.core_build) || (state.status && state.status.installed_core && state.status.installed_core.core_build) || '';
    var displayTarget = centerDown ? tr('statusUnknown', 'Неизвестно') : (plan.target_build || (hasUpdate ? tr('statusUnknown', 'Неизвестно') : tr('kpiTargetLatest', 'latest')));
    $('kpiTarget').textContent = displayTarget;
    $('kpiTargetMeta').textContent = centerDown ? tr('recommendCenterDownText', 'CRM не может проверить обновления, потому что сервер {url} сейчас не отвечает или еще не настроен.', {url: updateCenterUrl()}) : (hasUpdate ? tr('kpiTargetMetaFound', 'Доступно обновление с {build}.', {build: currentBuild || tr('statusUnknown', 'Неизвестно')}) : tr('kpiTargetMetaLatest', 'Новых сборок для установки нет.'));
    $('kpiPackage').textContent = centerDown ? tr('statusUnknown', 'Неизвестно') : (pkg ? String(pkg.type || 'package').toUpperCase() : tr('kpiPackageNone', 'не требуется'));
    $('kpiPackageMeta').textContent = centerDown ? tr('centerUnavailableWithUrl', 'Сервер обновлений недоступен: {url}', {url: updateCenterUrl()}) : (pkg ? `${bytes(pkg.size_bytes)} | SHA ${String(pkg.sha256 || '').slice(0, 12)}...` : tr('kpiPackageMetaNone', 'Архив скачивать не нужно.'));
    $('kpiRisk').textContent = centerDown ? tr('statusUnknown', 'Неизвестно') : risk;
    $('kpiRiskMeta').textContent = centerDown ? tr('statusCenterDown', 'Сервер обновлений недоступен') : (!hasUpdate ? tr('kpiRiskMetaNone', 'Изменений для установки нет.') : (plan.requires ? [
      plan.requires.backup ? 'backup' : null,
      plan.requires.maintenance ? 'maintenance' : null,
      plan.requires.db_migration ? 'db migration' : null,
    ].filter(Boolean).join(' + ') || tr('no_special_requirements', 'без особых требований') : tr('statusUnknown', 'Неизвестно')));
    // Transition bridge: this installation predates "modules ship with core
    // updates", so the first package only updates the config and updater; the
    // build with modules arrives with the NEXT check. Two short updates,
    // both automatic - no manual steps besides clicking through twice.
    const bridgeEl = $('bridgeNote');
    if (bridgeEl) {
      const bridge = plan.bridge_update === true;
      bridgeEl.hidden = !bridge;
      bridgeEl.textContent = bridge ? tr('bridgeNote', 'Установка пройдёт в два шага: сначала обновится конфигурация и механизм обновлений, затем придет сборка с модулями. Просто повторите установку после первого обновления.') : '';
    }
    updateRecommendation();
  }

  function renderChanges() {
    const payload = state.changes;
    $('updatesChangesRaw').textContent = pretty(payload);
    if (!payload) {
      setBadge('changesBadge', 'neutral', tr('not_loaded', 'Не загружено'));
      $('changesContent').innerHTML = `<div class="updates-empty">${esc(tr('changesHint', 'Нажмите «Что изменится?», чтобы увидеть резюме.'))}</div>`;
      return;
    }
    if (payload.ok === false || Number(payload.status || 0) >= 400) {
      setBadge('changesBadge', 'danger', tr('statusFailed', 'Есть ошибка'));
      $('changesContent').innerHTML = `<div class="updates-empty">${esc(tr('changesLoadError', 'Сервер обновлений не вернул список изменений.'))}</div>`;
      return;
    }
    const data = payload && (payload.data || payload);
    if (!data || !data.summary) {
      setBadge('changesBadge', state.plan ? 'ok' : 'neutral', state.plan ? tr('statusNoUpdates', 'Обновлений нет') : tr('not_loaded', 'Не загружено'));
      $('changesContent').innerHTML = `<div class="updates-empty">${esc(state.plan ? tr('changesEmpty', 'Изменений для установки нет.') : tr('detailsEmpty', 'Данные появятся после проверки.'))}</div>`;
      return;
    }
    setBadge('changesBadge', 'ok', tr('changesBadge', '{commits} коммитов / {files} файлов', {commits: data.summary.commits || 0, files: data.summary.files || 0}));
    const commits = (data.commits || []).slice(0, 6).map((c) => `<li><span><strong>${esc(c.short_sha || '')}</strong> ${esc(c.title || '')}</span></li>`).join('');
    const messageText = Number(payload.status || 0) === 204 ? tr('changesEmpty', 'Изменений для установки нет.') : data.message;
    const message = messageText ? `<div class="updates-empty mb-3">${esc(messageText)}</div>` : '';
    $('changesContent').innerHTML = `${message}<div><h3 class="h6">${esc(tr('commitsTitle', 'Краткая история'))}</h3><ul class="updates-list">${commits || `<li><span>${esc(tr('noCommits', 'Нет коммитов'))}</span></li>`}</ul></div>`;
  }

  function renderPreflight() {
    const preflight = state.preflight;
    const download = state.download;
    $('updatesPreflightRaw').textContent = pretty({preflight, download});
    if (!preflight) return;
    const report = preflight.preflight || preflight;
    const checks = report.checks || {};
    const labels = i18n.checkLabels || {};
    const rows = Object.keys(checks).map((key) => `<li><span>${esc(labels[key] || key)}</span><span class="${checks[key] ? 'updates-badge ok' : 'updates-badge danger'}">${checks[key] ? esc(tr('checkOk', 'OK')) : esc(tr('checkFailed', 'FAIL'))}</span></li>`).join('');
    const staging = download && download.data && download.data.staging ? download.data.staging : null;
    $('preflightContent').innerHTML = `${list({[tr('field_job_id', 'Job ID')]: state.lastJobId || 'n/a', [tr('fieldTarget', 'Целевая сборка')]: report.target_build || 'n/a', [tr('field_package', 'Архив')]: report.package ? String(report.package.type).toUpperCase() : tr('none', 'нет'), [tr('field_files', 'Файлов подготовлено')]: report.manifest_report ? report.manifest_report.file_count : 'n/a'})}<h3 class="h6 mt-3">${esc(tr('checks_title', 'Проверки'))}</h3><ul class="updates-list">${rows}</ul>${checks.no_active_lock === false ? `<div class="updates-empty mt-2" style="border-color:var(--crm-warning-soft);background:var(--crm-warning-soft);color:var(--crm-warning);"><strong>🛡 ${esc(tr('lockCheckLabel', 'Активная блокировка'))} — ${esc(tr('checkFailed', 'FAIL'))}</strong><br>${esc(tr('forceUnlockHint', 'Обновление заблокировано. Если предыдущая установка была прервана, нажмите кнопку ниже.'))}<div class="updates-actions mt-2"><button class="btn crm-btn-warning" type="button" data-update-action="force-unlock">${esc(tr('forceUnlockBtn', 'Принудительно разблокировать'))}</button></div></div>` : ''}${report.modules_note ? `<div class="updates-empty mt-2">${esc(report.modules_note)}</div>` : ''}${(report.manifest_report && Array.isArray(report.manifest_report.forbidden_paths) && report.manifest_report.forbidden_paths.length) ? `<div class="updates-empty mt-2 updates-danger">${esc(tr('forbiddenPathsTitle', 'Защищённые пути в архиве:'))} ${esc(report.manifest_report.forbidden_paths.join(', '))}</div>` : ''}${staging ? `<h3 class="h6 mt-3">${esc(tr('fieldStaging', 'Подготовка архива'))}</h3>${list({[tr('field_files', 'Файлов подготовлено')]: staging.file_count, [tr('fieldPreview', 'Первые файлы')]: (staging.preview || []).join(', ')})}` : ''}`;
    updateRecommendation();
  }

  function renderApply() {
    $('updatesApplyRaw').textContent = pretty(state.apply);
    const apply = state.apply && (state.apply.data || state.apply);
    if (!apply) return;
    if (state.apply.success === false) {
      $('applyContent').innerHTML = `<div class="updates-empty">${esc(tr('applyError', 'Ошибка: {message}', {message: state.apply.message || state.apply.code || 'unknown'}))}</div>`;
      return;
    }
    const migrations = apply.migrations || null;
    const migrationsStatus = migrations === null ? tr('migrationsNone', 'не требовались') : (migrations.ok === true ? tr('migrationsOk', 'применены') : tr('migrationsFailed', 'не применены (см. детали)'));
    const migrationsDetail = migrations && migrations.ok === true && Array.isArray(migrations.executed) && migrations.executed.length
      ? tr('migrationsExecuted', 'Применено миграций: {count}', {count: migrations.executed.length})
      : (migrations && migrations.ok === false && migrations.error ? String(migrations.error) : '');
    const dbBackup = apply.db_backup || null;
    const dbRestore = apply.db_restore || null;
    const dbBackupStatus = dbBackup === null ? tr('none', 'нет') : (dbBackup.ok === true ? tr('dbBackupOk', 'создан') : (dbBackup.skipped === true ? tr('dbBackupSkipped', 'пропущен') : tr('dbBackupFailed', 'не создан')));
    const dbRestoreStatus = dbRestore === null ? tr('none', 'нет') : (dbRestore.ok === true ? tr('dbRestoreOk', 'восстановлена') : (dbRestore.skipped === true ? tr('dbRestoreSkipped', 'нет бэкапа БД') : tr('dbRestoreFailed', 'не восстановлена')));
    const applyItems = {
      [tr('field_job_id', 'Job ID')]: apply.job_id || 'n/a',
      [tr('field_applied_files', 'Обновлено файлов')]: apply.applied ? apply.applied.count : 'n/a',
      [tr('field_backup', 'Backup')]: apply.backup ? apply.backup.backup_id : 'n/a',
      [tr('fieldHealth', 'Проверка состояния')]: apply.health && apply.health.ok ? tr('checkOk', 'OK') : tr('statusUnknown', 'Неизвестно'),
      [tr('field_migrations', 'Миграции БД')]: migrationsStatus,
      [tr('field_db_backup', 'Бэкап БД')]: dbBackupStatus,
      [tr('field_db_restore', 'Восстановление БД')]: dbRestoreStatus,
      [tr('field_installed_build', 'Установленная сборка')]: apply.installed_core ? apply.installed_core.core_build : 'n/a',
    };
    $('applyContent').innerHTML = `${list(applyItems)}${migrationsDetail ? `<div class="updates-empty mt-2">${esc(migrationsDetail)}</div>` : ''}`;
    updateRecommendation();
  }

  function list(items) {
    return `<ul class="updates-list">${Object.entries(items).map(([key, value]) => `<li><span>${esc(key)}</span><code>${esc(value)}</code></li>`).join('')}</ul>`;
  }

  async function withAction(name, fn) {
    setLoading(name, true);
    try {
      clearNotice();
      await fn();
    } catch (err) {
      showNotice(String(err && err.message ? err.message : err), 'danger');
      $('updatesApplyRaw').textContent = pretty({error: String(err)});
    } finally {
      setLoading(name, false);
      updateRecommendation();
    }
  }

  async function refresh() {
    // Refresh must discard transient pipeline data and reload the plan as well
    // as local status. Otherwise a manually copied installation can retain a
    // failed job id from the previous page state and continue the wrong job.
    state.plan = null;
    state.preflight = null;
    state.download = null;
    state.apply = null;
    state.changes = null;
    state.lastJobId = null;
    await loadStatus();
    await check();
  }

  async function loadStatus() {
    // Clear any stale progress notice immediately on page load —
    // if the update already completed, the notice from the previous
    // session must not persist.
    clearNotice();
    const [version, status] = await Promise.all([
      api('/api/index.php?route=api/v1/core/version'),
      api('/api/index.php?route=api/v1/core/updates/status')
    ]);
    ensureSuccess(version, tr('errVersion', 'Не удалось загрузить текущую версию CRM.'));
    ensureSuccess(status, tr('errStatus', 'Не удалось загрузить статус обновлений.'));
    state.version = version.data || version;
    state.status = status.data || status;
    if (state.status && state.status.latest_job && state.status.latest_job.job_id) state.lastJobId = state.status.latest_job.job_id;
    renderStatus();
  }

  async function check() {
    const result = await api('/api/index.php?route=api/v1/core/updates/check', {method: 'POST', body: '{}'});
    ensureSuccess(result, tr('errCheck', 'Не удалось проверить обновления.'));
    state.plan = result.data && result.data.plan ? result.data.plan : (result.data || result);
    renderPlan();
    if (state.plan && state.plan.update_available === true) {
      changes().catch(() => {});
    }
  }

  async function changes() {
    const result = await api('/api/index.php?route=api/v1/core/updates/changes');
    ensureSuccess(result, tr('errChanges', 'Не удалось загрузить список изменений.'));
    state.changes = result.data || result;
    renderChanges();
  }

  async function preflight() {
    // Run the read-only preflight directly against the local updater. The API
    // proxy performs a synchronous request back into the same PHP application;
    // on shared hosting with a constrained PHP-FPM pool that can deadlock and
    // surface as a gateway 504. The updater already exposes this dry-run action
    // without a token and the browser request preserves the authenticated
    // same-origin session context for the page.
    const result = await api('/updater/index.php?action=preflight', {method: 'POST', body: JSON.stringify({dry_run: true}), timeoutMs: 180000});
    ensureSuccess(result, tr('errPreflight', 'Не удалось выполнить безопасную проверку.'));
    const data = result.data || result;
    state.preflight = data.preflight || data;
    state.lastJobId = extractJobId(result) || state.lastJobId;
    if (!state.lastJobId) throw new Error(tr('needJobDownload', 'Сначала выполните проверку безопасности, чтобы получить job_id.'));
    renderPreflight();
    await loadStatus();
  }

  const phaseLabels = {
    backup_files: tr('phaseBackupFiles', 'бэкап файлов'),
    apply_files: tr('phaseApplyFiles', 'установка файлов'),
    health: tr('phaseHealth', 'проверка состояния'),
    backup_db: tr('phaseBackupDb', 'бэкап базы данных'),
    migrate: tr('phaseMigrate', 'миграции БД'),
    extract: tr('phaseExtract', 'распаковка архива'),
    restore_db: tr('phaseRestoreDb', 'восстановление БД'),
    restore_files: tr('phaseRestoreFiles', 'восстановление файлов'),
    finalize: tr('phaseFinalize', 'завершение'),
    finalized: tr('phaseFinalized', 'готово')
  };

  function progressText(progress) {
    const phase = progress && progress.phase ? String(progress.phase) : '';
    const label = phaseLabels[phase] || phase || tr('stepWorking', 'выполняется...');
    const done = Number(progress && progress.done || 0);
    const total = Number(progress && progress.total || 0);
    if (phase === 'backup_db' && done > 0 && !total) return `${label}: ${done}`;
    if (total > 0) return `${label} — ${tr('progressStep', 'шаг {done} из {total}', {done, total})}`;
    return label;
  }

  function renderUpdaterProgress(progress) {
    const text = progressText(progress);
    setBadge('nextStatusBadge', 'warn', text);
    setBadge('detailsBadge', 'warn', text);
    // Show a prominent progress notice in the main hero area so the admin
    // knows the update is actively running and must not refresh the page.
    const phase = progress && progress.phase ? String(progress.phase) : '';
    const isBackup = phase === 'backup_files' || phase === 'backup_db';
    const phaseLabel = (phaseLabels[phase] || phase || '').trim();
    const noticeParts = [tr('apply_in_progress', 'Обновление выполняется. Не обновляйте и не закрывайте страницу.')];
    if (phaseLabel) noticeParts.push(phaseLabel);
    const done = Number(progress && progress.done || 0);
    const total = Number(progress && progress.total || 0);
    if (total > 0) noticeParts.push(tr('progressStep', 'шаг {done} из {total}', {done, total}));
    showNotice(noticeParts.join(' — '), 'info');
    if ($('applyContent')) {
      $('applyContent').innerHTML = `<div class="updates-empty">${esc(text)}…</div>`;
    }
  }

  async function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  // Drive a resumable updater action: the updater runs each job as many short
  // HTTP requests (each well under shared-hosting timeouts) and returns
  // {continue:true} until the job is done. This loop keeps issuing the next
  // step, showing progress, until the final response arrives.
  //
  // The one-time updater token has a 10-minute TTL that is extended on every
  // continuation step. If the browser pauses longer than that between steps
  // (idle tab, slow network, user walked away), the job fails with a token
  // error and maintenance stays held. Instead of forcing the admin to notice
  // and retry manually, refresh the token via the session endpoint and resume
  // the same job from its stored progress.
  async function runUpdaterSteps(action, body) {
    let stepBody = Object.assign({}, body);
    const maxTokenRetries = 3;
    let tokenRetries = 0;
    const maxNetworkRetries = 3;
    let networkRetries = 0;
    for (let guard = 0; guard < 2000; guard++) {
      let result;
      try {
        // The updater step machine bounds every request (~20s of work), so a
        // 90s client timeout only protects against a hung proxy/connection.
        // The download action streams the whole package in one pass; the
        // server lifts its own limit to 600s, so the client waits up to 10
        // minutes to never cut off a slow-but-working shared-host download.
        // Apply steps copy/backup files on slow shared hosting and can take
        // longer than the default 90 s. Use 180 s for apply to reduce false
        // timeout errors that scare the admin into refreshing the page.
        const stepTimeoutMs = action === 'download' ? 600000 : (action === 'apply' ? 180000 : 90000);
        try {
          result = await api(`/updater/index.php?action=${action}`, {method: 'POST', body: JSON.stringify(stepBody), timeoutMs: stepTimeoutMs});
        } catch (fetchErr) {
          // During maintenance mode the regular API endpoints return 503.
          // The updater itself is whitelisted and should still work, but a
          // transient proxy/CDN cache may briefly serve a 503 for the
          // updater URL too. Retry once after a short delay.
          if (action === 'apply' && networkRetries < maxNetworkRetries) {
            networkRetries++;
            await sleep(2000);
            continue;
          }
          throw fetchErr;
        }
        ensureSuccess(result, tr('errGeneric', 'Не удалось выполнить действие.'));
      } catch (err) {
        const message = String(err && err.message ? err.message : err || '').toLowerCase();
        const tokenProblem = message.includes('token') && (message.includes('invalid') || message.includes('expired'));
        if (stepBody.token && tokenProblem && tokenRetries < maxTokenRetries) {
          tokenRetries++;
          try {
            stepBody = Object.assign({}, stepBody, {token: await updaterSession()});
          } catch (_sessionErr) {
            // The session endpoint may be blocked during held maintenance
            // mode (old api/index.php). Retry with the same token: the
            // updater's sliding window keeps it fresh on every apply_step,
            // so it may still be valid even though the error suggested
            // expiry. If truly expired, the next iteration will re-try
            // and eventually exhaust retries or succeed.
          }
          continue;
        }
        // A request that never produced a server response (proxy timeout,
        // WAF reset, dropped connection) is transient. The updater job is
        // resumable, so re-posting the same step continues from the stored
        // progress instead of aborting the whole update. Server-side errors
        // (success:false envelopes) are not retried here.
        if (err && err.isNetwork === true && networkRetries < maxNetworkRetries) {
          networkRetries++;
          const retryLabel = tr('retry_in_progress', 'Сетевая ошибка, повторяем... ({attempt} из {max})', {attempt: networkRetries, max: maxNetworkRetries});
          showNotice(tr('apply_in_progress', 'Обновление выполняется. Не обновляйте и не закрывайте страницу.') + ' ' + retryLabel, 'info');
          await sleep(1200 * networkRetries);
          continue;
        }
        throw err;
      }
      const data = result.data || result;
      if (!data.continue) return result;
      // A completed step resets the network retry budget: a flaky link must
      // not exhaust all retries on one early step and leave later steps
      // unprotected.
      networkRetries = 0;
      renderUpdaterProgress(data.progress);
      await sleep(400);
    }
    throw new Error(tr('errGeneric', 'Не удалось выполнить действие.'));
  }

  async function download() {
    state.lastJobId = state.lastJobId || extractJobId(state.status);
    if (!state.lastJobId) throw new Error(tr('needJobDownload', 'Сначала выполните проверку безопасности, чтобы получить job_id.'));
    const result = await runUpdaterSteps('download', {dry_run: true, job_id: state.lastJobId});
    state.download = result;
    renderPreflight();
    await loadStatus();
  }

  async function updaterSession() {
    const session = await api('/api/index.php?route=api/v1/core/updates/session', {method: 'POST', body: '{}'});
    ensureSuccess(session, tr('errDownload', 'Не удалось получить одноразовый updater token.'));
    const token = session && session.data && session.data.updater_token;
    if (!token) throw new Error(tr('tokenError', 'Не удалось получить одноразовый updater token.'));
    return token;
  }

  async function applyUpdate() {
    state.lastJobId = state.lastJobId || extractJobId(state.status);
    if (!state.lastJobId) throw new Error(tr('needJobApply', 'Нет job_id. Сначала выполните проверку безопасности и подготовку архива.'));
    const token = await updaterSession();
    // runUpdaterSteps keeps posting the same job with the same token; the
    // updater treats the first call as the start (confirm_apply checked once)
    // and later calls as continuation steps of the same resumable job.
    const result = await runUpdaterSteps('apply', {job_id: state.lastJobId, confirm_apply: true, token});
    ensureSuccess(result, tr('errApply', 'Не удалось установить обновление.'));
    state.apply = result;
    // Clear the progress notice immediately after apply succeeds so it
    // does not remain visible after the page refreshes its status.
    clearNotice();
    renderApply();
    state.preflight = null;
    state.download = null;
    state.changes = null;
    await loadStatus();
    await check();
  }

  async function installUpdate() {
    if (!state.plan) await check();
    if (!state.plan || state.plan.update_available !== true) {
      await loadStatus();
      return;
    }
    // Resume a job whose package is already downloaded and staged. This is
    // the common case after a reload or a network blip mid-flow: the archive
    // is prepared, so apply continues where the previous session stopped
    // instead of re-running preflight/download (which on tricky hostings can
    // fail again and leave the update stuck at 'staging_ready').
    if (latestJobIsStaged()) {
      state.lastJobId = String(state.status.latest_job.job_id || '');
      await applyUpdate();
      return;
    }
    if (!state.preflight || !state.lastJobId) await preflight();
    if (!state.download) await download();
    await applyUpdate();
  }

  async function rollback() {
    const latest = state.status && state.status.latest_job;
    const jobId = state.lastJobId || (latest && latest.job_id);
    if (!jobId) throw new Error(tr('needJobRollback', 'Нет job_id для восстановления.'));
    let token = '';
    try {
      token = await updaterSession();
    } catch (_err) {
      // Session endpoint may be blocked during held maintenance on old
      // installs (api/index.php without the updater-recovery whitelist).
      // Proceed with an empty token: the updater will reject it, but the
      // error message guides the admin. The bootstrap update adds the
      // whitelist so this path only fires on very old installations.
    }
    const result = await runUpdaterSteps('rollback', {job_id: jobId, token});
    ensureSuccess(result, tr('errRollback', 'Не удалось восстановить backup.'));
    state.apply = result;
    renderApply();
    state.preflight = null;
    state.download = null;
    state.changes = null;
    await loadStatus();
  }

  // Rotate the updater recovery key and show the new value once. The key
  // unlocks /updater/rescue.php (last-resort recovery while maintenance mode
  // holds). It is created at installation; this button re-issues it whenever
  // the admin needs a fresh copy - e.g. after a failed update when the page
  // still works but the original key was lost.
  async function showRecoveryKey() {
    const keyEl = $('recoveryKeyValue');
    const warnEl = $('recoveryKeyWarn');
    const btn = document.querySelector('[data-update-action="recovery-key"]');
    if (btn) btn.disabled = true;
    try {
      const result = await api('/api/index.php?route=api/v1/core/updates/recovery-key', {method: 'POST', body: '{}'});
      ensureSuccess(result, tr('recoveryKeyError', 'Не удалось получить ключ восстановления.'));
      const data = result.data || result;
      const key = data && data.recovery_key ? String(data.recovery_key) : '';
      if (keyEl) {
        keyEl.textContent = key || '';
        keyEl.classList.remove('d-none');
      }
      if (warnEl) warnEl.classList.remove('d-none');
      if (btn) {
        btn.disabled = false;
        btn.textContent = tr('recoveryKeyAgain', 'Сгенерировать новый ключ');
      }
      if (key) {
        try { await navigator.clipboard.writeText(key); } catch (e) { /* clipboard may be unavailable */ }
      }
    } catch (err) {
      const envelope = err && err.envelope ? err.envelope : null;
      showNotice((envelope && envelope.message) || tr('recoveryKeyError', 'Не удалось получить ключ восстановления.'), 'danger');
      if (btn) btn.disabled = false;
    }
  }

  // ── Force unlock ──
  async function forceUnlock() {
    if (!window.confirm(tr('forceUnlockConfirm', 'Это удалит файл блокировки обновлений. Используйте только если уверены, что предыдущее обновление не выполняется. Продолжить?'))) return;
    const result = await api('/updater/index.php?action=force-unlock', {method: 'POST', body: '{}', timeoutMs: 10000});
    ensureSuccess(result, tr('forceUnlockError', 'Не удалось снять блокировку. Попробуйте ещё раз.'));
    const data = result.data || result;
    if (data.lock_removed) {
      showNotice(tr('forceUnlockOk', 'Блокировка снята. Теперь можно запустить проверку безопасности.'), 'ok');
    } else {
      showNotice(tr('forceUnlockNone', 'Блокировки не было — ничего удалять не нужно.'), 'ok');
    }
    // Re-run preflight to refresh the checks with the lock removed
    state.preflight = null;
    state.lastJobId = null;
    if (state.plan && state.plan.update_available === true) {
      await preflight();
    }
  }

  // ── Migration helpers ──
  let migrationState = { status: null };

  function renderMigration() {
    const ms = migrationState.status;
    const raw = $('updatesMigrationRaw');
    if (raw) raw.textContent = pretty(ms);
    if (!ms) {
      $('migrationContent').innerHTML = `<div class="updates-empty">${esc(tr('mig_none_pending', 'Нажмите «Проверить миграции» для получения текущего статуса.'))}</div>`;
      return;
    }
    const migrationStatus = ms.migration_status || ms;
    const pending = migrationStatus.pending || [];
    const all = migrationStatus.all || [];
    const applied = all.length - pending.length;
    if (pending.length === 0) {
      $('migrationContent').innerHTML = `<div class="updates-empty">${esc(tr('mig_none_pending', 'Все миграции уже применены. Накопленных изменений нет.'))}</div>`;
      return;
    }
    const pendingItems = pending.map((key) => `<li><code>${esc(key)}</code></li>`).join('');
    $('migrationContent').innerHTML = `
      <div class="updates-empty mb-2" style="border-color:var(--crm-warning-soft);background:var(--crm-warning-soft);color:var(--crm-warning);">
        <strong>${esc(tr('mig_pending_count', 'Ожидает применения: {count}', {count: pending.length}))}</strong>
        <span style="margin-left:12px;color:var(--crm-text-soft);">${esc(tr('mig_applied_count', 'Применено: {count}', {count: applied}))}</span>
      </div>
      <h3 class="h6">${esc(tr('mig_pending_list', 'Ожидающие миграции'))}</h3>
      <ul class="updates-list">${pendingItems}</ul>
    `;
  }

  async function migrationCheck() {
    const result = await api('/api/index.php?route=/internal/migration/status');
    ensureSuccess(result, tr('mig_err_check', 'Не удалось проверить статус миграций.'));
    migrationState.status = result.data || result;
    renderMigration();
  }

  async function migrationUp() {
    // Show confirmation if there are pending migrations
    if (migrationState.status) {
      const ms = migrationState.status.migration_status || migrationState.status;
      const pending = ms.pending || [];
      if (pending.length > 0 && !window.confirm(tr('mig_confirm', 'Будут применены все ожидающие миграции базы данных. Это безопасная операция — миграции используют защищённые DDL-запросы. Продолжить?'))) {
        return;
      }
    }
    const result = await api('/api/index.php?route=/internal/migration/up', {method: 'POST', body: '{}'});
    ensureSuccess(result, tr('mig_err_run', 'Не удалось применить миграции.'));
    const data = result.data || result;
    const executed = data.executed || [];
    if (executed.length > 0) {
      $('migrationContent').innerHTML = `<div class="updates-empty" style="border-color:var(--crm-success-soft);background:var(--crm-success-soft);color:var(--crm-success);"><strong>${esc(tr('mig_success', 'Миграции успешно применены'))}: ${esc(tr('mig_applied_count', 'Применено: {count}', {count: executed.length}))}</strong></div>`;
    } else {
      $('migrationContent').innerHTML = `<div class="updates-empty">${esc(tr('mig_none_pending', 'Все миграции уже применены. Накопленных изменений нет.'))}</div>`;
    }
    migrationState.status = data;
    renderMigration();
  }

  document.addEventListener('click', (event) => {
    const btn = event.target.closest && event.target.closest('[data-update-action]');
    if (!btn) return;
    const action = btn.getAttribute('data-update-action');
    const actions = { refresh, check, changes, install: installUpdate, preflight, download, apply: applyUpdate, rollback, 'recovery-key': showRecoveryKey, 'migration-check': migrationCheck, 'migration-up': migrationUp, 'force-unlock': forceUnlock };
    if (actions[action]) withAction(action, actions[action]);
  });

  // Phase progress bar for initial load
  function showPhaseProgress(phases) {
    let bar = document.getElementById('updatesPhaseBar');
    if (!bar) {
      bar = document.createElement('div');
      bar.id = 'updatesPhaseBar';
      bar.className = 'updates-phase-bar';
      const control = $('updatesControl');
      if (control) control.parentNode.insertBefore(bar, control.nextSibling);
    }
    const labels = { status: tr('kpi_installed', 'Текущая версия'), check: tr('kpi_target', 'Доступная версия'), changes: tr('technical_changes', 'Технические данные изменений') };
    bar.innerHTML = phases.map((p) => `<span class="updates-phase-dot ${p.state}"></span><span>${esc(labels[p.key] || p.key)}${p.state === 'active' ? '…' : (p.state === 'done' ? ' ✓' : '')}</span>`).join('<span style="color:var(--crm-text-soft);margin:0 2px;">→</span>');
  }
  function removePhaseProgress() {
    const bar = document.getElementById('updatesPhaseBar');
    if (bar) bar.remove();
  }

  withAction('initial load', async () => {
    // Show phase progress bar
    showPhaseProgress([
      {key:'status', state:'active'},
      {key:'check', state:''},
      {key:'changes', state:''},
    ]);
    // Mark KPI target as loading
    const kpiT = $('kpiTarget');
    if (kpiT && !kpiT.dataset.realValue) { kpiT.dataset.realValue = kpiT.textContent; kpiT.classList.add('loading'); }
    const kpiP = $('kpiPackage');
    if (kpiP && !kpiP.dataset.realValue) { kpiP.dataset.realValue = kpiP.textContent; kpiP.classList.add('loading'); }
    const kpiR = $('kpiRisk');
    if (kpiR && !kpiR.dataset.realValue) { kpiR.dataset.realValue = kpiR.textContent; kpiR.classList.add('loading'); }

    await loadStatus();
    showPhaseProgress([
      {key:'status', state:'done'},
      {key:'check', state:'active'},
      {key:'changes', state:''},
    ]);
    await check();
    showPhaseProgress([
      {key:'status', state:'done'},
      {key:'check', state:'done'},
      {key:'changes', state:'active'},
    ]);
    // Explicitly load changes after check — the auto-call inside check()
    // swallows errors via .catch(() => {}) which leaves the section stuck
    // on "Не загружено" when the first request fails or is slow.
    try {
      await changes();
    } catch (_) { /* rendered by renderChanges fallback */ }
    showPhaseProgress([
      {key:'status', state:'done'},
      {key:'check', state:'done'},
      {key:'changes', state:'done'},
    ]);
    // Remove phase progress bar after 1.5 seconds
    setTimeout(removePhaseProgress, 1500);
  });
})();
</script>
