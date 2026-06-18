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
  'versionKnown' => $au('version_known', 'Текущая сборка: {build}'),
  'versionUnknown' => $au('version_unknown', 'Текущая сборка не принята updater'),
  'jobKnown' => $au('job_known', 'Последняя операция: {state}'),
  'jobEmpty' => $au('job_empty', 'Операций еще не было'),
  'maintenanceOn' => $au('maintenance_on', 'Maintenance включен'),
  'maintenanceOff' => $au('maintenance_off', 'CRM работает штатно'),
  'kpiInstalledUnknown' => $au('kpi_installed_unknown', 'unknown'),
  'kpiInstalledMetaUnknown' => $au('kpi_installed_meta_unknown', 'Локальная сборка еще не принята updater.'),
  'kpiTargetLatest' => $au('kpi_target_latest', 'latest'),
  'kpiTargetMetaLatest' => $au('kpi_target_meta_latest', 'Новых сборок для установки нет.'),
  'kpiTargetMetaFound' => $au('kpi_target_meta_found', 'Доступно обновление с {build}.'),
  'kpiPackageNone' => $au('kpi_package_none', 'не требуется'),
  'kpiPackageMetaNone' => $au('kpi_package_meta_none', 'Архив скачивать не нужно.'),
  'kpiRiskNone' => $au('kpi_risk_none', 'нет'),
  'kpiRiskMetaNone' => $au('kpi_risk_meta_none', 'Изменений для установки нет.'),
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
  'preflightFailed' => $au('preflight_failed', 'Проверка не пройдена'),
  'applyError' => $au('apply_error', 'Ошибка: {message}'),
  'confirmApply' => $au('confirm_apply', 'Для реального применения обновления введите APPLY'),
  'confirmRollback' => $au('confirm_rollback', 'Rollback восстановит файлы из backup. Введите ROLLBACK'),
  'needJobDownload' => $au('need_job_download', 'Сначала выполните проверку безопасности, чтобы получить job_id.'),
  'needJobApply' => $au('need_job_apply', 'Нет job_id. Сначала выполните проверку безопасности и подготовку архива.'),
  'needJobRollback' => $au('need_job_rollback', 'Нет job_id для восстановления.'),
  'tokenError' => $au('token_error', 'Не удалось получить одноразовый updater token.'),
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
  'history_empty' => $au('history_empty', 'История появится после первой операции.'),
  'no_job' => $au('no_job', 'Нет операции'),
  'no_special_requirements' => $au('no_special_requirements', 'без особых требований'),
  'none' => $au('none', 'нет'),
  'not_loaded' => $au('not_loaded', 'Не загружено'),
  'plan_not_checked' => $au('plan_not_checked', 'Не проверено'),
];
?>
<body data-page="admin-updates" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-updates-page">
  <style>
    .crm-admin-updates-page {
      --update-ok:#0f766e; --update-warn:#b45309; --update-danger:#b42318; --update-ink:#101828; --update-line:#e4e7ec;
      background: radial-gradient(circle at 82% 0%, rgba(15,118,110,.08), transparent 30%), linear-gradient(180deg, #f8fafc 0%, #fff 420px);
    }
    .updates-shell { max-width:1180px; margin:0 auto; }
    .updates-hero { display:grid; grid-template-columns:minmax(0,1.15fr) minmax(300px,.85fr); gap:18px; margin-bottom:16px; }
    .updates-panel, .updates-card { border:1px solid var(--update-line); border-radius:24px; background:rgba(255,255,255,.95); box-shadow:0 18px 44px rgba(16,24,40,.07); }
    .updates-panel { padding:28px; position:relative; overflow:hidden; }
    .updates-panel:after { content:""; position:absolute; right:-80px; bottom:-90px; width:250px; height:250px; border-radius:999px; background:rgba(15,118,110,.08); }
    .updates-kicker { display:inline-flex; align-items:center; gap:8px; margin-bottom:14px; color:#344054; font-weight:800; font-size:.82rem; }
    .updates-kicker:before { content:""; width:10px; height:10px; border-radius:999px; background:var(--update-ok); box-shadow:0 0 0 5px rgba(15,118,110,.12); }
    .updates-title { margin:0; color:var(--update-ink); font-size:clamp(2rem,3vw,3.2rem); line-height:1; letter-spacing:-.045em; font-weight:900; max-width:720px; }
    .updates-subtitle { max-width:680px; margin:15px 0 0; color:#475467; line-height:1.6; font-size:1rem; }
    .updates-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:22px; }
    .updates-actions .btn { border-radius:999px; padding:.62rem 1rem; font-weight:800; }
    .updates-status-card { padding:24px; display:flex; flex-direction:column; justify-content:space-between; gap:18px; }
    .updates-status-label { color:#667085; font-weight:800; font-size:.78rem; text-transform:uppercase; letter-spacing:.08em; }
    .updates-status-title { margin:7px 0 8px; color:var(--update-ink); font-size:1.55rem; line-height:1.12; font-weight:900; letter-spacing:-.02em; }
    .updates-status-text { margin:0; color:#475467; line-height:1.55; }
    .updates-status-foot { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .updates-notice { display:none; margin:0 0 16px; border-radius:18px; padding:14px 16px; border:1px solid #fecaca; background:#fff1f2; color:#9f1239; font-weight:700; }
    .updates-notice.show { display:block; }
    .updates-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:16px; }
    .updates-metric { border:1px solid var(--update-line); border-radius:18px; background:#fff; padding:16px; min-height:126px; }
    .updates-kpi-label { color:#667085; font-size:.78rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; }
    .updates-kpi-value { margin-top:10px; font-size:1.55rem; font-weight:900; color:var(--update-ink); line-height:1.05; overflow-wrap:anywhere; }
    .updates-muted { color:#667085; font-size:.9rem; line-height:1.5; }
    .updates-pill-row { display:flex; flex-wrap:wrap; gap:8px; margin-top:20px; position:relative; z-index:1; }
    .updates-pill { display:inline-flex; align-items:center; gap:8px; padding:8px 11px; border:1px solid #d0d5dd; border-radius:999px; background:#fff; color:#344054; font-size:.82rem; font-weight:750; }
    .updates-dot { width:9px; height:9px; border-radius:50%; background:#98a2b3; box-shadow:0 0 0 4px rgba(152,162,179,.12); }
    .updates-dot.ok { background:var(--update-ok); box-shadow:0 0 0 4px rgba(15,118,110,.14); }
    .updates-dot.warn { background:var(--update-warn); box-shadow:0 0 0 4px rgba(180,83,9,.13); }
    .updates-dot.danger { background:var(--update-danger); box-shadow:0 0 0 4px rgba(180,35,24,.13); }
    .updates-info { border-radius:18px; border:1px solid #d0d5dd; background:#fff; color:#344054; padding:15px 16px; margin:0 0 16px; line-height:1.55; }
    .updates-info strong { color:#101828; }
    .updates-card { padding:20px; margin-bottom:14px; }
    .updates-card-head { display:flex; justify-content:space-between; gap:14px; align-items:flex-start; margin-bottom:14px; }
    .updates-card h2 { margin:0; font-size:1.08rem; font-weight:900; color:var(--update-ink); letter-spacing:-.01em; }
    .updates-badge { display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:6px 10px; font-size:.76rem; font-weight:850; border:1px solid #d0d5dd; background:#f9fafb; color:#344054; white-space:nowrap; }
    .updates-badge.ok { color:#0f766e; background:#ecfdf3; border-color:#abefc6; }
    .updates-badge.warn { color:#b45309; background:#fffaeb; border-color:#fedf89; }
    .updates-badge.danger { color:#b42318; background:#fef3f2; border-color:#fecdca; }
    .updates-badge.neutral { color:#475467; background:#f9fafb; border-color:#eaecf0; }
    .updates-details-grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(300px,.75fr); gap:14px; }
    .updates-list { display:grid; gap:0; margin:0; padding:0; list-style:none; }
    .updates-list li { display:flex; justify-content:space-between; gap:12px; padding:10px 0; border-bottom:1px solid #eaecf0; }
    .updates-list li:last-child { border-bottom:0; }
    .updates-list code { color:var(--update-ink); font-weight:800; white-space:normal; text-align:right; }
    .updates-file-table { width:100%; border-collapse:separate; border-spacing:0; overflow:hidden; }
    .updates-file-table th, .updates-file-table td { padding:10px 9px; border-bottom:1px solid #eaecf0; vertical-align:top; font-size:.86rem; }
    .updates-file-table th { color:#667085; font-size:.73rem; letter-spacing:.04em; text-transform:uppercase; }
    .updates-file-table td:first-child { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; overflow-wrap:anywhere; }
    .updates-empty { border:1px dashed #d0d5dd; border-radius:16px; padding:16px; color:#667085; background:#f9fafb; }
    .updates-raw { margin-top:12px; border-top:1px solid #eaecf0; padding-top:10px; }
    .updates-raw summary { cursor:pointer; color:#475467; font-weight:800; font-size:.84rem; }
    .updates-raw pre { margin:10px 0 0; max-height:320px; overflow:auto; border-radius:14px; background:#101828; color:#d1e9ff; padding:12px; font-size:.78rem; }
    .updates-danger-zone { border-color:#fecdca; background:linear-gradient(180deg,#fff,#fffbfa); }
    .updates-danger-zone h2 { color:#b42318; }
    @media (max-width:1120px) { .updates-hero,.updates-details-grid { grid-template-columns:1fr; } .updates-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width:720px) { .updates-shell { padding:0 2px; } .updates-panel,.updates-card,.updates-status-card { border-radius:18px; padding:18px; } .updates-metrics { grid-template-columns:1fr; } }
  </style>

  <div class="updates-shell">
    <div id="updatesNotice" class="updates-notice"></div>
    <section class="updates-hero">
      <div class="updates-panel">
        <div class="updates-kicker"><?= htmlspecialchars($au('kicker', 'Центр обновлений'), ENT_QUOTES, 'UTF-8') ?></div>
        <h1 class="updates-title"><?= htmlspecialchars($au('headline', 'Обновления без лишнего риска'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="updates-subtitle"><?= htmlspecialchars($au('subtitle', 'CRM проверит доступную сборку, подготовит архив и подскажет следующий безопасный шаг.'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="updates-actions">
          <button id="primaryActionBtn" class="btn crm-btn-primary" type="button" data-update-action="check"><?= htmlspecialchars($au('btn_check', 'Проверить обновления'), ENT_QUOTES, 'UTF-8') ?></button>
          <button class="btn crm-btn-secondary" type="button" data-update-action="refresh"><?= htmlspecialchars($au('btn_refresh', 'Обновить статус'), ENT_QUOTES, 'UTF-8') ?></button>
          <button class="btn crm-btn-secondary" type="button" data-update-action="changes"><?= htmlspecialchars($au('btn_changes', 'Что изменится?'), ENT_QUOTES, 'UTF-8') ?></button>
          <a class="btn crm-btn-secondary" href="/updater/rescue.php" target="_blank" rel="noopener"><?= htmlspecialchars($au('btn_recovery', 'Аварийное восстановление'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div class="updates-pill-row">
          <span class="updates-pill"><span id="pillCenter" class="updates-dot"></span><span id="pillCenterText"><?= htmlspecialchars($au('center_checking', 'Сервер обновлений проверяется'), ENT_QUOTES, 'UTF-8') ?></span></span>
          <span class="updates-pill"><span id="pillVersion" class="updates-dot"></span><span id="pillVersionText"><?= htmlspecialchars($au('version_checking', 'Версия пока неизвестна'), ENT_QUOTES, 'UTF-8') ?></span></span>
          <span class="updates-pill"><span id="pillJob" class="updates-dot"></span><span id="pillJobText"><?= htmlspecialchars($au('job_checking', 'Операций еще не было'), ENT_QUOTES, 'UTF-8') ?></span></span>
          <span class="updates-pill"><span id="pillMaintenance" class="updates-dot"></span><span id="pillMaintenanceText"><?= htmlspecialchars($au('maintenance_checking', 'CRM работает штатно'), ENT_QUOTES, 'UTF-8') ?></span></span>
        </div>
      </div>
      <aside class="updates-card updates-status-card">
        <div>
          <div class="updates-status-label"><?= htmlspecialchars($au('recommendation_label', 'Рекомендация'), ENT_QUOTES, 'UTF-8') ?></div>
          <h2 id="nextTitle" class="updates-status-title"><?= htmlspecialchars($au('recommend_check_title', 'Проверьте наличие обновлений'), ENT_QUOTES, 'UTF-8') ?></h2>
          <p id="nextText" class="updates-status-text"><?= htmlspecialchars($au('recommend_check_text', 'Проверка безопасна: она только сравнит вашу CRM с готовыми архивами на сервере обновлений.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="updates-status-foot">
          <span id="nextStatusBadge" class="updates-badge neutral"><?= htmlspecialchars($au('status_checking', 'Проверяем...'), ENT_QUOTES, 'UTF-8') ?></span>
          <span id="nextPlanBadge" class="updates-badge neutral"><?= htmlspecialchars($au('plan_not_checked', 'Не проверено'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </aside>
    </section>

    <div class="updates-info">
      <strong><?= htmlspecialchars($au('how_title', 'Как это работает:'), ENT_QUOTES, 'UTF-8') ?></strong>
      <?= htmlspecialchars($au('how_text', 'Архив обновления заранее собирается кроном на update.crm.ru. Эта CRM не генерирует архив при каждом открытии страницы, а только скачивает готовый пакет, проверяет его и применяет после вашего подтверждения.'), ENT_QUOTES, 'UTF-8') ?>
    </div>

    <section class="updates-metrics">
      <article class="updates-metric">
        <div class="updates-kpi-label"><?= htmlspecialchars($au('kpi_installed', 'Текущая версия'), ENT_QUOTES, 'UTF-8') ?></div>
        <div id="kpiInstalled" class="updates-kpi-value">...</div>
        <p id="kpiInstalledMeta" class="updates-muted mb-0"><?= htmlspecialchars($au('kpi_installed_loading', 'Загружаем состояние CRM.'), ENT_QUOTES, 'UTF-8') ?></p>
      </article>
      <article class="updates-metric">
        <div class="updates-kpi-label"><?= htmlspecialchars($au('kpi_target', 'Доступная версия'), ENT_QUOTES, 'UTF-8') ?></div>
        <div id="kpiTarget" class="updates-kpi-value">...</div>
        <p id="kpiTargetMeta" class="updates-muted mb-0"><?= htmlspecialchars($au('kpi_target_loading', 'Покажем после проверки.'), ENT_QUOTES, 'UTF-8') ?></p>
      </article>
      <article class="updates-metric">
        <div class="updates-kpi-label"><?= htmlspecialchars($au('kpi_package', 'Что скачается'), ENT_QUOTES, 'UTF-8') ?></div>
        <div id="kpiPackage" class="updates-kpi-value">...</div>
        <p id="kpiPackageMeta" class="updates-muted mb-0"><?= htmlspecialchars($au('kpi_package_loading', 'Готовый архив или ничего.'), ENT_QUOTES, 'UTF-8') ?></p>
      </article>
      <article class="updates-metric">
        <div class="updates-kpi-label"><?= htmlspecialchars($au('kpi_risk', 'Уровень риска'), ENT_QUOTES, 'UTF-8') ?></div>
        <div id="kpiRisk" class="updates-kpi-value">...</div>
        <p id="kpiRiskMeta" class="updates-muted mb-0"><?= htmlspecialchars($au('kpi_risk_loading', 'Оценим перед установкой.'), ENT_QUOTES, 'UTF-8') ?></p>
      </article>
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
          <details open>
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
          <details open>
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
        </div>
      </div>
    </section>
  </div>
</main></div></div>
<script>
(function () {
  const i18n = <?= json_encode($auJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const state = { status: null, version: null, plan: null, changes: null, preflight: null, download: null, apply: null, lastJobId: null };
  const $ = (id) => document.getElementById(id);
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
  const pretty = (value) => JSON.stringify(value || {}, null, 2);
  const tr = (key, fallback, vars = {}) => {
    let text = String(i18n[key] || fallback || key);
    Object.keys(vars).forEach((name) => { text = text.replaceAll(`{${name}}`, String(vars[name] ?? '')); });
    return text;
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
    preflight: tr('loadingPreflight', 'проверяем безопасность'),
    download: tr('loadingDownload', 'подготавливаем архив'),
    apply: tr('loadingApply', 'устанавливаем обновление'),
    rollback: tr('loadingRollback', 'восстанавливаем backup')
  };

  function apiRouteFromUrl(url) {
    const raw = String(url || '');
    const query = raw.includes('?') ? raw.slice(raw.indexOf('?') + 1) : '';
    const params = new URLSearchParams(query);
    return String(params.get('route') || raw.replace(/^\/+/, '')).replace(/^\/+/, '');
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
    const res = await fetch(url, Object.assign({credentials: 'same-origin', headers}, options));
    const text = await res.text();
    let json;
    try { json = JSON.parse(text); } catch (e) { json = {success: false, code: 'INVALID_JSON', message: text.slice(0, 300)}; }
    if (!res.ok && json.success !== false) json.success = false;
    json.http_status = res.status;
    return json;
  }

  function showNotice(message) {
    const box = $('updatesNotice');
    if (!box) return;
    box.className = 'updates-notice show';
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
    if (status === 401) return tr('errSession', 'Сессия истекла. Обновите страницу и войдите в CRM снова.');
    if (status === 403) return tr('errForbidden', 'У пользователя нет прав на управление обновлениями. Нужен root/admin или право управления настройками.');
    return String((payload && (payload.message || payload.code)) || fallback || tr('errGeneric', 'Не удалось выполнить действие.'));
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

  function pipelineKind() {
    const latest = state.status && state.status.latest_job;
    if (latest && latest.state === 'failed') return 'danger';
    if (state.plan && state.plan.update_available !== true) return 'ok';
    if (latest && latest.state === 'applied') return 'ok';
    if (state.download || state.preflight) return 'warn';
    return 'neutral';
  }

  function pipelineText() {
    const latest = state.status && state.status.latest_job;
    if (state.plan && state.plan.update_available !== true) return tr('statusNoUpdates', 'Обновлений нет');
    if (latest && latest.state === 'applied') return tr('statusApplied', 'Обновление установлено');
    if (state.download) return tr('statusPrepared', 'Архив подготовлен');
    if (state.preflight) return tr('preflightOk', 'Проверка пройдена');
    if (state.plan && state.plan.update_available === true) return tr('statusUpdateFound', 'Есть обновление');
    return tr('statusChecking', 'Проверяем...');
  }

  function updateRecommendation() {
    const latest = state.status && state.status.latest_job;
    const plan = state.plan;
    if (latest && latest.state === 'failed') {
      $('nextTitle').textContent = tr('recommendFailedTitle', 'Последняя операция завершилась ошибкой');
      $('nextText').textContent = tr('recommendFailedText', 'Проверьте детали операции. Если CRM работает нестабильно, используйте восстановление из backup.');
      setPrimary('refresh', tr('primaryRefresh', 'Обновить статус'));
    } else if (state.download) {
      $('nextTitle').textContent = tr('recommendReadyTitle', 'Можно устанавливать');
      $('nextText').textContent = tr('recommendReadyText', 'Перед установкой CRM создаст backup. Запускайте установку только если готовы к короткому maintenance-окну.');
      setPrimary('apply', tr('primaryApply', 'Установить обновление'));
    } else if (state.preflight) {
      $('nextTitle').textContent = tr('recommendPreflightTitle', 'Проверка пройдена');
      $('nextText').textContent = tr('recommendPreflightText', 'Теперь можно подготовить архив во временной папке. Рабочие файлы CRM еще не меняются.');
      setPrimary('download', tr('primaryDownload', 'Подготовить архив'));
    } else if (plan && plan.update_available === true) {
      $('nextTitle').textContent = tr('recommendFoundTitle', 'Найдено обновление');
      $('nextText').textContent = tr('recommendFoundText', 'Сначала запустите безопасную проверку. Файлы CRM на этом шаге не меняются.');
      setPrimary('preflight', tr('primaryPreflight', 'Проверить безопасность'));
    } else if (plan) {
      $('nextTitle').textContent = tr('recommendLatestTitle', 'CRM уже актуальна');
      $('nextText').textContent = tr('recommendLatestText', 'Устанавливать ничего не нужно. Архив обновления не требуется, рисков для текущей версии нет.');
      setPrimary('check', tr('primaryCheckAgain', 'Проверить еще раз'));
    } else {
      $('nextTitle').textContent = tr('recommendCheckTitle', 'Проверьте наличие обновлений');
      $('nextText').textContent = tr('recommendCheckText', 'Проверка безопасна: она только сравнит вашу CRM с готовыми архивами на сервере обновлений.');
      setPrimary('check', tr('primaryCheck', 'Проверить обновления'));
    }
    setBadge('nextStatusBadge', pipelineKind(), pipelineText());
    setBadge('nextPlanBadge', plan ? (plan.update_available === true ? 'warn' : 'ok') : 'neutral', plan ? (plan.update_available === true ? tr('statusUpdateFound', 'Есть обновление') : tr('statusNoUpdates', 'Обновлений нет')) : tr('plan_not_checked', 'Не проверено'));
    setBadge('detailsBadge', pipelineKind(), pipelineText());
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
  }

  function renderStatus() {
    const status = state.status || {};
    const version = state.version || {};
    const installed = version.state ? version : (status.installed_core || {});
    const latest = status.latest_job || null;
    const auditExists = !!status.audit;
    const auditOk = !!(status.audit && status.audit.health_ok);
    const maintenance = !!status.maintenance;
    $('pillCenter').className = dotClass(auditOk ? 'ok' : (auditExists ? 'warn' : ''));
    $('pillCenterText').textContent = auditOk ? tr('centerOk', 'Сервер обновлений доступен') : (auditExists ? tr('centerWarn', 'Сервер обновлений требует проверки') : tr('centerMissing', 'Сервер обновлений еще не проверен'));
    $('pillVersion').className = dotClass(installed.core_build ? 'ok' : 'warn');
    $('pillVersionText').textContent = installed.core_build ? tr('versionKnown', 'Текущая сборка: {build}', {build: installed.core_build}) : tr('versionUnknown', 'Текущая сборка не принята updater');
    $('pillJob').className = dotClass(latest && latest.state === 'failed' ? 'danger' : latest ? 'ok' : 'warn');
    $('pillJobText').textContent = latest ? tr('jobKnown', 'Последняя операция: {state}', {state: latest.state}) : tr('jobEmpty', 'Операций еще не было');
    $('pillMaintenance').className = dotClass(maintenance ? 'danger' : 'ok');
    $('pillMaintenanceText').textContent = maintenance ? tr('maintenanceOn', 'Maintenance включен') : tr('maintenanceOff', 'CRM работает штатно');
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
    }) : `<div class="updates-empty">${esc(tr('history_empty', 'История появится после первой операции.'))}</div>`;
    updateRecommendation();
  }

  function renderPlan() {
    const plan = state.plan;
    $('updatesPlanRaw').textContent = pretty(plan);
    if (!plan) return;
    const pkg = plan.recommended_package;
    const hasUpdate = plan.update_available === true;
    const risk = plan.summary && plan.summary.risk_level ? plan.summary.risk_level : (hasUpdate ? tr('statusUnknown', 'Неизвестно') : tr('kpiRiskNone', 'нет'));
    const displayTarget = plan.target_build || plan.current_build || (hasUpdate ? tr('statusUnknown', 'Неизвестно') : tr('kpiTargetLatest', 'latest'));
    $('kpiTarget').textContent = displayTarget;
    $('kpiTargetMeta').textContent = hasUpdate ? tr('kpiTargetMetaFound', 'Доступно обновление с {build}.', {build: plan.current_build || tr('statusUnknown', 'Неизвестно')}) : tr('kpiTargetMetaLatest', 'Новых сборок для установки нет.');
    $('kpiPackage').textContent = pkg ? String(pkg.type || 'package').toUpperCase() : tr('kpiPackageNone', 'не требуется');
    $('kpiPackageMeta').textContent = pkg ? `${bytes(pkg.size_bytes)} | SHA ${String(pkg.sha256 || '').slice(0, 12)}...` : tr('kpiPackageMetaNone', 'Архив скачивать не нужно.');
    $('kpiRisk').textContent = risk;
    $('kpiRiskMeta').textContent = !hasUpdate ? tr('kpiRiskMetaNone', 'Изменений для установки нет.') : (plan.requires ? [
      plan.requires.backup ? 'backup' : null,
      plan.requires.maintenance ? 'maintenance' : null,
      plan.requires.db_migration ? 'db migration' : null,
    ].filter(Boolean).join(' + ') || tr('no_special_requirements', 'без особых требований') : tr('statusUnknown', 'Неизвестно'));
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
    const commits = (data.commits || []).slice(0, 6).map((c) => `<li><span><strong>${esc(c.short_sha || '')}</strong> ${esc(c.title || '')}</span><span>${esc(c.committed_at || '')}</span></li>`).join('');
    const files = (data.files || []).slice(0, 12).map((f) => `<tr><td>${esc(f.path)}</td><td>${esc(f.scope)}</td><td>${esc(f.change_type)}</td><td>${f.included_in_package ? '<span class="updates-badge ok">included</span>' : '<span class="updates-badge neutral">excluded</span>'}</td></tr>`).join('');
    const messageText = Number(payload.status || 0) === 204 ? tr('changesEmpty', 'Изменений для установки нет.') : data.message;
    const message = messageText ? `<div class="updates-empty mb-3">${esc(messageText)}</div>` : '';
    $('changesContent').innerHTML = `${message}<div class="updates-details-grid"><div><h3 class="h6">${esc(tr('commitsTitle', 'Краткая история'))}</h3><ul class="updates-list">${commits || `<li><span>${esc(tr('noCommits', 'Нет коммитов'))}</span></li>`}</ul></div><div><h3 class="h6">${esc(tr('filesTitle', 'Затронутые файлы'))}</h3><table class="updates-file-table"><thead><tr><th>${esc(tr('fileCol', 'Файл'))}</th><th>${esc(tr('scopeCol', 'Зона'))}</th><th>${esc(tr('typeCol', 'Тип'))}</th><th>${esc(tr('packageCol', 'Архив'))}</th></tr></thead><tbody>${files || `<tr><td colspan="4">${esc(tr('noFiles', 'Нет файлов'))}</td></tr>`}</tbody></table></div></div>`;
  }

  function renderPreflight() {
    const preflight = state.preflight;
    const download = state.download;
    $('updatesPreflightRaw').textContent = pretty({preflight, download});
    if (!preflight) return;
    const report = preflight.preflight || preflight;
    const checks = report.checks || {};
    const rows = Object.keys(checks).map((key) => `<li><span>${esc(key)}</span><span class="${checks[key] ? 'updates-badge ok' : 'updates-badge danger'}">${checks[key] ? 'OK' : 'FAIL'}</span></li>`).join('');
    const staging = download && download.data && download.data.staging ? download.data.staging : null;
    $('preflightContent').innerHTML = `${list({[tr('field_job_id', 'Job ID')]: state.lastJobId || 'n/a', [tr('fieldTarget', 'Целевая сборка')]: report.target_build || 'n/a', [tr('field_package', 'Архив')]: report.package ? String(report.package.type).toUpperCase() : tr('none', 'нет'), [tr('field_files', 'Файлов подготовлено')]: report.manifest_report ? report.manifest_report.file_count : 'n/a'})}<h3 class="h6 mt-3">${esc(tr('checks_title', 'Проверки'))}</h3><ul class="updates-list">${rows}</ul>${staging ? `<h3 class="h6 mt-3">${esc(tr('fieldStaging', 'Подготовка архива'))}</h3>${list({[tr('field_files', 'Файлов подготовлено')]: staging.file_count, [tr('fieldPreview', 'Первые файлы')]: (staging.preview || []).join(', ')})}` : ''}`;
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
    $('applyContent').innerHTML = list({
      [tr('field_job_id', 'Job ID')]: apply.job_id || 'n/a',
      [tr('field_applied_files', 'Обновлено файлов')]: apply.applied ? apply.applied.count : 'n/a',
      [tr('field_backup', 'Backup')]: apply.backup ? apply.backup.backup_id : 'n/a',
      [tr('fieldHealth', 'Проверка состояния')]: apply.health && apply.health.ok ? 'OK' : tr('statusUnknown', 'Неизвестно'),
      [tr('field_installed_build', 'Установленная сборка')]: apply.installed_core ? apply.installed_core.core_build : 'n/a',
    });
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
      showNotice(String(err && err.message ? err.message : err));
      $('updatesApplyRaw').textContent = pretty({error: String(err)});
    } finally {
      setLoading(name, false);
      updateRecommendation();
    }
  }

  async function loadStatus() {
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
  }

  async function changes() {
    const result = await api('/api/index.php?route=api/v1/core/updates/changes');
    ensureSuccess(result, tr('errChanges', 'Не удалось загрузить список изменений.'));
    state.changes = result.data || result;
    renderChanges();
  }

  async function preflight() {
    const result = await api('/api/index.php?route=api/v1/core/updates/preflight', {method: 'POST', body: JSON.stringify({dry_run: true})});
    ensureSuccess(result, tr('errPreflight', 'Не удалось выполнить безопасную проверку.'));
    const data = result.data || result;
    state.preflight = data.preflight || data;
    state.lastJobId = data.job_id || (data.updater && data.updater.data && data.updater.data.job_id) || state.lastJobId;
    renderPreflight();
    await loadStatus();
  }

  async function download() {
    if (!state.lastJobId) throw new Error(tr('needJobDownload', 'Сначала выполните проверку безопасности, чтобы получить job_id.'));
    const result = await api('/updater/index.php?action=download', {method: 'POST', body: JSON.stringify({dry_run: true, job_id: state.lastJobId})});
    ensureSuccess(result, tr('errDownload', 'Не удалось подготовить архив.'));
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
    if (!state.lastJobId) throw new Error(tr('needJobApply', 'Нет job_id. Сначала выполните проверку безопасности и подготовку архива.'));
    const confirmation = window.prompt(tr('confirmApply', 'Для реального применения обновления введите APPLY'));
    if (confirmation !== 'APPLY') return;
    const token = await updaterSession();
    const result = await api('/updater/index.php?action=apply', {method: 'POST', body: JSON.stringify({job_id: state.lastJobId, confirm_apply: true, token})});
    ensureSuccess(result, tr('errApply', 'Не удалось установить обновление.'));
    state.apply = result;
    renderApply();
    await loadStatus();
    await check();
  }

  async function rollback() {
    const latest = state.status && state.status.latest_job;
    const jobId = state.lastJobId || (latest && latest.job_id);
    if (!jobId) throw new Error(tr('needJobRollback', 'Нет job_id для восстановления.'));
    const confirmation = window.prompt(tr('confirmRollback', 'Rollback восстановит файлы из backup. Введите ROLLBACK'));
    if (confirmation !== 'ROLLBACK') return;
    const token = await updaterSession();
    const result = await api('/updater/index.php?action=rollback', {method: 'POST', body: JSON.stringify({job_id: jobId, token})});
    ensureSuccess(result, tr('errRollback', 'Не удалось восстановить backup.'));
    state.apply = result;
    renderApply();
    await loadStatus();
  }

  document.addEventListener('click', (event) => {
    const btn = event.target.closest && event.target.closest('[data-update-action]');
    if (!btn) return;
    const action = btn.getAttribute('data-update-action');
    const actions = { refresh: () => loadStatus(), check, changes, preflight, download, apply: applyUpdate, rollback };
    if (actions[action]) withAction(action, actions[action]);
  });

  withAction('initial load', async () => {
    await loadStatus();
    await check();
  });
})();
</script>
