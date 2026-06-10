<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Системные настройки'; ?>
<body data-page="admin-settings" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-settings-page"><div class="crm-page-head"><div><h1 class="crm-page-title">Системные настройки</h1><p class="crm-subtitle">Обзор без изменения критичных данных, безопасное редактирование и политика хранения.</p></div><div class="d-flex gap-2"><button id="adminSettingsRefreshBtn" class="btn crm-btn-secondary" type="button">Обновить</button></div></div>

<div class="crm-admin-settings-note mb-3" role="note">
  Опасные изменения требуют подтверждения. Изменения записываются в журнал аудита через API.
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="crm-card crm-section-card h-100">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Пользовательские настройки</h2><div class="crm-section-note">Персональные настройки профиля и уведомлений, без системных флагов.</div></div></div>
      <div id="adminSettingsUserPrefsState" class="text-muted">Загрузка...</div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="crm-card crm-section-card h-100">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Системные настройки</h2><div class="crm-section-note">Только разрешенные настройки с безопасным редактированием.</div></div></div>
      <table class="table table-sm crm-table mb-0"><thead><tr><th>Ключ</th><th>Значение</th><th></th></tr></thead><tbody id="adminSettingsSystemBody"><tr><td colspan="3" class="text-muted">Загрузка...</td></tr></tbody></table>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-7">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Политики хранения данных</h2><div class="crm-section-note">Настройки жизненного цикла данных. Сначала предварительный расчет, затем применение.</div></div></div>
      <table class="table table-sm crm-table mb-2"><thead><tr><th>Поле</th><th>Дней</th><th>Действие</th></tr></thead><tbody id="adminSettingsRetentionBody"><tr><td colspan="3" class="text-muted">Загрузка...</td></tr></tbody></table>
      <div id="adminSettingsRetentionState" class="text-muted small">Ожидание данных...</div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Последний аудит</h2><div class="crm-section-note">Последние изменения настроек.</div></div></div>
      <div id="adminSettingsAuditState" class="text-muted small mb-2">Загрузка...</div>
      <ul id="adminSettingsAuditList" class="list-group list-group-flush"></ul>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Кэш API</h2><div class="crm-section-note">Файловое кэширование ответов справочных эндпоинтов. После включения изменения вступают в силу немедленно.</div></div></div>
      <div class="crm-section-body p-3" id="adminCacheSection">
        <div class="text-muted">Загрузка...</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1" id="adminSystemInfoSection" style="display:none;">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Информация о системе</h2><div class="crm-section-note">Технические параметры окружения (только для root).</div></div><div class="d-flex gap-2"><button id="adminSystemInfoRefreshBtn" class="btn btn-sm crm-btn-secondary" type="button">Обновить</button></div></div>
      <div class="row g-3">
        <div class="col-md-3"><div class="crm-info-panel"><small class="text-muted">Версия API</small><div class="fw-semibold" id="systemInfoPhpVersion">—</div></div></div>
        <div class="col-md-3"><div class="crm-info-panel"><small class="text-muted">Часовой пояс</small><div class="fw-semibold" id="systemInfoEnv">—</div></div></div>
        <div class="col-md-3"><div class="crm-info-panel"><small class="text-muted">База данных</small><div class="fw-semibold" id="systemInfoDb">—</div></div></div>
        <div class="col-md-3"><div class="crm-info-panel"><small class="text-muted">Сформировано</small><div class="fw-semibold" id="systemInfoUptime">—</div></div></div>
      </div>
      <div class="row g-3 mt-2">
        <div class="col-md-4"><div class="crm-info-panel"><small class="text-muted">Файлы</small><div class="small" id="systemInfoStorage">—</div></div></div>
        <div class="col-md-4"><div class="crm-info-panel"><small class="text-muted">Временные данные</small><div class="small" id="systemInfoCache">—</div></div></div>
        <div class="col-md-4"><div class="crm-info-panel"><small class="text-muted">Логи</small><div class="small" id="systemInfoLogs">—</div></div></div>
      </div>
    </div>
  </div>
</div>

</main></div></div>
