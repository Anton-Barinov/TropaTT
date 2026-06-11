<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Уведомления'; ?>
<body data-page="notifications" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-notifications-page"><div class="crm-page-head"><div><h1 class="crm-page-title">Уведомления</h1><p class="crm-subtitle">События по задачам, проектам и системе.</p></div><div class="crm-page-actions crm-notifications-toolbar"><select id="notificationsCategoryFilter" class="form-select crm-field-w-220" aria-label="Фильтр уведомлений по категории"><option value="">Все категории</option></select><select id="notificationsReadFilter" class="form-select crm-field-w-220" aria-label="Фильтр уведомлений по состоянию"><option value="">Все уведомления</option><option value="unread">Только непрочитанные</option><option value="read">Только прочитанные</option></select><button id="notificationsMarkAllBtn" class="btn crm-btn-secondary" type="button" aria-label="Отметить все уведомления прочитанными">Отметить все прочитанным</button></div></div>
<div class="crm-notifications-layout">
  <aside class="crm-side-stack">
    <section class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Сводка</h2><div class="crm-section-note">Быстрый обзор текущего состояния уведомлений.</div></div></div>
      <div class="crm-info-panel mb-2"><small class="text-muted">Всего уведомлений</small><div id="notificationsTotalCount">—</div></div>
      <div class="crm-info-panel mb-2"><small class="text-muted">Непрочитанных</small><div id="notificationsUnreadCount">—</div></div>
      <div class="crm-info-panel"><small class="text-muted">Категорий</small><div id="notificationsCategoryCount">—</div></div>
    </section>
    <section class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Приоритеты</h2><div class="crm-section-note">Сначала просматривайте непрочитанные и критичные события.</div></div></div>
      <div class="crm-empty-state">
        <strong>Поддерживайте ленту в чистоте</strong>
        <p class="mb-0">После обработки события помечайте его прочитанным, чтобы в центре уведомлений оставались только актуальные задачи и алерты.</p>
      </div>
    </section>
    <section class="crm-card crm-section-card crm-notifications-advanced">
      <details class="crm-notifications-disclosure" open>
        <summary><span>Push-уведомления</span><small>Системные уведомления браузера при свернутой вкладке</small></summary>
        <div class="crm-notifications-disclosure-body">
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="notificationsPushEnable">
        <label class="form-check-label" for="notificationsPushEnable">Включить push-уведомления</label>
      </div>
      <div class="small text-muted mb-2" id="notificationsPushStatus">Статус разрешения: неизвестно</div>
      <div class="small text-muted mb-2" id="notificationsPushSubscriptionStatus">Статус подписки: неизвестно</div>
      <div class="d-flex gap-2 flex-wrap">
        <button id="notificationsPushPermissionBtn" class="btn crm-btn-secondary" type="button">Запросить разрешение</button>
        <button id="notificationsPushTestBtn" class="btn crm-btn-primary" type="button">Отправить тест</button>
        <button id="notificationsPushRefreshBtn" class="btn crm-btn-secondary" type="button">Обновить устройства</button>
      </div>
      <div class="mt-3">
        <div class="small fw-semibold mb-2">Подписанные устройства</div>
        <div id="notificationsPushDevices" class="small text-muted">Загрузка списка устройств...</div>
      </div>
        </div>
      </details>
    </section>
    <section class="crm-card crm-section-card crm-notifications-advanced">
      <details class="crm-notifications-disclosure">
        <summary><span>Звук и quiet hours</span><small>Звуковые сигналы для новых уведомлений</small></summary>
        <div class="crm-notifications-disclosure-body">
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="notificationsSoundEnable">
        <label class="form-check-label" for="notificationsSoundEnable">Включить звук уведомлений</label>
      </div>
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="notificationsQuietHoursEnable">
        <label class="form-check-label" for="notificationsQuietHoursEnable">Использовать quiet hours</label>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-6">
          <label class="form-label mb-1" for="notificationsQuietStart">С</label>
          <input id="notificationsQuietStart" class="form-control" type="time" value="22:00">
        </div>
        <div class="col-6">
          <label class="form-label mb-1" for="notificationsQuietEnd">До</label>
          <input id="notificationsQuietEnd" class="form-control" type="time" value="08:00">
        </div>
      </div>
      <label class="form-label mb-1" for="notificationsQuietTimezone">Часовой пояс</label>
      <input id="notificationsQuietTimezone" class="form-control mb-2" type="text" value="" placeholder="Europe/Moscow">
      <div class="small text-muted mb-2" id="notificationsSoundStatus">Статус звука: неизвестно</div>
      <div class="d-flex gap-2 flex-wrap">
        <button id="notificationsSoundSaveBtn" class="btn crm-btn-secondary" type="button">Сохранить настройки</button>
        <button id="notificationsSoundTestBtn" class="btn crm-btn-primary" type="button">Тест звука</button>
      </div>
        </div>
      </details>
    </section>
    <section class="crm-card crm-section-card crm-notifications-advanced">
      <details class="crm-notifications-disclosure">
        <summary><span>Матрица каналов</span><small>Настройка доставки по категориям</small></summary>
        <div class="crm-notifications-disclosure-body">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-2">
          <thead>
            <tr><th>Категория</th><th>In-app</th><th>Звук</th><th>Push</th></tr>
          </thead>
          <tbody id="notificationsMatrixBody"></tbody>
        </table>
      </div>
      <div class="small text-muted mb-2">Security-уведомления всегда остаются в in-app для защиты от пропуска критичных событий.</div>
      <div class="d-flex gap-2 flex-wrap">
        <button id="notificationsMatrixSaveBtn" class="btn crm-btn-secondary" type="button">Сохранить матрицу</button>
        <button id="notificationsMatrixResetBtn" class="btn crm-btn-secondary" type="button">Сбросить по умолчанию</button>
      </div>
        </div>
      </details>
    </section>
  </aside>
  <section class="crm-notifications-feed" aria-label="Лента уведомлений">
    <div class="crm-notifications-feed-head">
      <div>
        <h2 class="h6 mb-1">Лента событий</h2>
        <div class="small text-muted" id="notificationsListSummary">Загрузка уведомлений...</div>
      </div>
    </div>
    <div class="crm-notifications-list" data-notifications-list aria-live="polite" aria-busy="true">
      <div class="crm-card crm-empty-state"><strong>Загрузка уведомлений</strong><p class="mb-0">Подготавливаем список событий и уведомлений из API.</p></div>
    </div>
  </section>
</div>
</main></div></div>
