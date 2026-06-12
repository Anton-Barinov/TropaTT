<?php declare(strict_types=1); ?>
<?php $title = $t('notifications.title', 'TropaTT — Уведомления'); ?>
<body data-page="notifications" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-notifications-page"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="notifications.page_title"><?= htmlspecialchars($t('notifications.page_title', 'Уведомления'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="notifications.subtitle"><?= htmlspecialchars($t('notifications.subtitle', 'События по задачам, проектам и системе.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="crm-page-actions crm-notifications-toolbar"><select id="notificationsCategoryFilter" class="form-select crm-field-w-220" aria-label="<?= htmlspecialchars($t('notifications.filter_category_aria', 'Фильтр уведомлений по категории'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="notifications.filter_category_aria"><option value="" data-i18n="notifications.opt_all_categories"><?= htmlspecialchars($t('notifications.opt_all_categories', 'Все категории'), ENT_QUOTES, 'UTF-8') ?></option></select><select id="notificationsReadFilter" class="form-select crm-field-w-220" aria-label="<?= htmlspecialchars($t('notifications.filter_read_aria', 'Фильтр уведомлений по состоянию'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="notifications.filter_read_aria"><option value="" data-i18n="notifications.opt_all"><?= htmlspecialchars($t('notifications.opt_all', 'Все уведомления'), ENT_QUOTES, 'UTF-8') ?></option><option value="unread" data-i18n="notifications.opt_unread"><?= htmlspecialchars($t('notifications.opt_unread', 'Только непрочитанные'), ENT_QUOTES, 'UTF-8') ?></option><option value="read" data-i18n="notifications.opt_read"><?= htmlspecialchars($t('notifications.opt_read', 'Только прочитанные'), ENT_QUOTES, 'UTF-8') ?></option></select><button id="notificationsMarkAllBtn" class="btn crm-btn-secondary" type="button" aria-label="<?= htmlspecialchars($t('notifications.btn_mark_all_aria', 'Отметить все уведомления прочитанными'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="notifications.btn_mark_all_aria" data-i18n="notifications.btn_mark_all"><?= htmlspecialchars($t('notifications.btn_mark_all', 'Отметить все прочитанным'), ENT_QUOTES, 'UTF-8') ?></button></div></div>
<div class="crm-notifications-layout">
  <aside class="crm-side-stack">
    <section class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="notifications.section_summary"><?= htmlspecialchars($t('notifications.section_summary', 'Сводка'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="notifications.note_summary"><?= htmlspecialchars($t('notifications.note_summary', 'Быстрый обзор текущего состояния уведомлений.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div class="crm-info-panel mb-2"><small class="text-muted" data-i18n="notifications.label_total"><?= htmlspecialchars($t('notifications.label_total', 'Всего уведомлений'), ENT_QUOTES, 'UTF-8') ?></small><div id="notificationsTotalCount">—</div></div>
      <div class="crm-info-panel mb-2"><small class="text-muted" data-i18n="notifications.label_unread"><?= htmlspecialchars($t('notifications.label_unread', 'Непрочитанных'), ENT_QUOTES, 'UTF-8') ?></small><div id="notificationsUnreadCount">—</div></div>
      <div class="crm-info-panel"><small class="text-muted" data-i18n="notifications.label_categories"><?= htmlspecialchars($t('notifications.label_categories', 'Категорий'), ENT_QUOTES, 'UTF-8') ?></small><div id="notificationsCategoryCount">—</div></div>
    </section>
    <section class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="notifications.section_priorities"><?= htmlspecialchars($t('notifications.section_priorities', 'Приоритеты'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="notifications.note_priorities"><?= htmlspecialchars($t('notifications.note_priorities', 'Сначала просматривайте непрочитанные и критичные события.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div class="crm-empty-state">
        <strong data-i18n="notifications.priorities_heading"><?= htmlspecialchars($t('notifications.priorities_heading', 'Поддерживайте ленту в чистоте'), ENT_QUOTES, 'UTF-8') ?></strong>
        <p class="mb-0" data-i18n="notifications.priorities_text"><?= htmlspecialchars($t('notifications.priorities_text', 'После обработки события помечайте его прочитанным, чтобы в центре уведомлений оставались только актуальные задачи и алерты.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </section>
    <section class="crm-card crm-section-card crm-notifications-advanced">
      <details class="crm-notifications-disclosure" open>
        <summary><span data-i18n="notifications.section_push"><?= htmlspecialchars($t('notifications.section_push', 'Push-уведомления'), ENT_QUOTES, 'UTF-8') ?></span><small data-i18n="notifications.note_push"><?= htmlspecialchars($t('notifications.note_push', 'Системные уведомления браузера при свернутой вкладке'), ENT_QUOTES, 'UTF-8') ?></small></summary>
        <div class="crm-notifications-disclosure-body">
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="notificationsPushEnable">
        <label class="form-check-label" for="notificationsPushEnable" data-i18n="notifications.label_push_enable"><?= htmlspecialchars($t('notifications.label_push_enable', 'Включить push-уведомления'), ENT_QUOTES, 'UTF-8') ?></label>
      </div>
      <div class="small text-muted mb-2" id="notificationsPushStatus" data-i18n="notifications.state_push_status"><?= htmlspecialchars($t('notifications.state_push_status', 'Статус разрешения: неизвестно'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="small text-muted mb-2" id="notificationsPushSubscriptionStatus" data-i18n="notifications.state_push_subscription"><?= htmlspecialchars($t('notifications.state_push_subscription', 'Статус подписки: неизвестно'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="d-flex gap-2 flex-wrap">
        <button id="notificationsPushPermissionBtn" class="btn crm-btn-secondary" type="button" data-i18n="notifications.btn_request_permission"><?= htmlspecialchars($t('notifications.btn_request_permission', 'Запросить разрешение'), ENT_QUOTES, 'UTF-8') ?></button>
        <button id="notificationsPushTestBtn" class="btn crm-btn-primary" type="button" data-i18n="notifications.btn_send_test"><?= htmlspecialchars($t('notifications.btn_send_test', 'Отправить тест'), ENT_QUOTES, 'UTF-8') ?></button>
        <button id="notificationsPushRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="notifications.btn_refresh_devices"><?= htmlspecialchars($t('notifications.btn_refresh_devices', 'Обновить устройства'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div class="mt-3">
        <div class="small fw-semibold mb-2" data-i18n="notifications.label_subscribed_devices"><?= htmlspecialchars($t('notifications.label_subscribed_devices', 'Подписанные устройства'), ENT_QUOTES, 'UTF-8') ?></div>
        <div id="notificationsPushDevices" class="small text-muted" data-i18n="notifications.loading_devices"><?= htmlspecialchars($t('notifications.loading_devices', 'Загрузка списка устройств...'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
        </div>
      </details>
    </section>
    <section class="crm-card crm-section-card crm-notifications-advanced">
      <details class="crm-notifications-disclosure">
        <summary><span data-i18n="notifications.section_sound"><?= htmlspecialchars($t('notifications.section_sound', 'Звук и quiet hours'), ENT_QUOTES, 'UTF-8') ?></span><small data-i18n="notifications.note_sound"><?= htmlspecialchars($t('notifications.note_sound', 'Звуковые сигналы для новых уведомлений'), ENT_QUOTES, 'UTF-8') ?></small></summary>
        <div class="crm-notifications-disclosure-body">
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="notificationsSoundEnable">
        <label class="form-check-label" for="notificationsSoundEnable" data-i18n="notifications.label_sound_enable"><?= htmlspecialchars($t('notifications.label_sound_enable', 'Включить звук уведомлений'), ENT_QUOTES, 'UTF-8') ?></label>
      </div>
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="notificationsQuietHoursEnable">
        <label class="form-check-label" for="notificationsQuietHoursEnable" data-i18n="notifications.label_quiet_hours_enable"><?= htmlspecialchars($t('notifications.label_quiet_hours_enable', 'Использовать quiet hours'), ENT_QUOTES, 'UTF-8') ?></label>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-6">
          <label class="form-label mb-1" for="notificationsQuietStart" data-i18n="notifications.label_quiet_start"><?= htmlspecialchars($t('notifications.label_quiet_start', 'С'), ENT_QUOTES, 'UTF-8') ?></label>
          <input id="notificationsQuietStart" class="form-control" type="time" value="22:00">
        </div>
        <div class="col-6">
          <label class="form-label mb-1" for="notificationsQuietEnd" data-i18n="notifications.label_quiet_end"><?= htmlspecialchars($t('notifications.label_quiet_end', 'До'), ENT_QUOTES, 'UTF-8') ?></label>
          <input id="notificationsQuietEnd" class="form-control" type="time" value="08:00">
        </div>
      </div>
      <label class="form-label mb-1" for="notificationsQuietTimezone" data-i18n="notifications.label_timezone"><?= htmlspecialchars($t('notifications.label_timezone', 'Часовой пояс'), ENT_QUOTES, 'UTF-8') ?></label>
      <input id="notificationsQuietTimezone" class="form-control mb-2" type="text" value="" placeholder="<?= htmlspecialchars($t('notifications.placeholder_timezone', 'Europe/Moscow'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="notifications.placeholder_timezone">
      <div class="small text-muted mb-2" id="notificationsSoundStatus" data-i18n="notifications.state_sound_status"><?= htmlspecialchars($t('notifications.state_sound_status', 'Статус звука: неизвестно'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="d-flex gap-2 flex-wrap">
        <button id="notificationsSoundSaveBtn" class="btn crm-btn-secondary" type="button" data-i18n="notifications.btn_save_settings"><?= htmlspecialchars($t('notifications.btn_save_settings', 'Сохранить настройки'), ENT_QUOTES, 'UTF-8') ?></button>
        <button id="notificationsSoundTestBtn" class="btn crm-btn-primary" type="button" data-i18n="notifications.btn_test_sound"><?= htmlspecialchars($t('notifications.btn_test_sound', 'Тест звука'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
        </div>
      </details>
    </section>
    <section class="crm-card crm-section-card crm-notifications-advanced">
      <details class="crm-notifications-disclosure">
        <summary><span data-i18n="notifications.section_matrix"><?= htmlspecialchars($t('notifications.section_matrix', 'Матрица каналов'), ENT_QUOTES, 'UTF-8') ?></span><small data-i18n="notifications.note_matrix"><?= htmlspecialchars($t('notifications.note_matrix', 'Настройка доставки по категориям'), ENT_QUOTES, 'UTF-8') ?></small></summary>
        <div class="crm-notifications-disclosure-body">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-2">
          <thead>
            <tr><th data-i18n="notifications.th_category"><?= htmlspecialchars($t('notifications.th_category', 'Категория'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="notifications.th_inapp"><?= htmlspecialchars($t('notifications.th_inapp', 'In-app'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="notifications.th_sound"><?= htmlspecialchars($t('notifications.th_sound', 'Звук'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="notifications.th_push"><?= htmlspecialchars($t('notifications.th_push', 'Push'), ENT_QUOTES, 'UTF-8') ?></th></tr>
          </thead>
          <tbody id="notificationsMatrixBody"></tbody>
        </table>
      </div>
      <div class="small text-muted mb-2" data-i18n="notifications.hint_security"><?= htmlspecialchars($t('notifications.hint_security', 'Security-уведомления всегда остаются в in-app для защиты от пропуска критичных событий.'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="d-flex gap-2 flex-wrap">
        <button id="notificationsMatrixSaveBtn" class="btn crm-btn-secondary" type="button" data-i18n="notifications.btn_save_matrix"><?= htmlspecialchars($t('notifications.btn_save_matrix', 'Сохранить матрицу'), ENT_QUOTES, 'UTF-8') ?></button>
        <button id="notificationsMatrixResetBtn" class="btn crm-btn-secondary" type="button" data-i18n="notifications.btn_reset_default"><?= htmlspecialchars($t('notifications.btn_reset_default', 'Сбросить по умолчанию'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
        </div>
      </details>
    </section>
  </aside>
  <section class="crm-notifications-feed" aria-label="<?= htmlspecialchars($t('notifications.feed_aria', 'Лента уведомлений'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="notifications.feed_aria">
    <div class="crm-notifications-feed-head">
      <div>
        <h2 class="h6 mb-1" data-i18n="notifications.feed_title"><?= htmlspecialchars($t('notifications.feed_title', 'Лента событий'), ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="small text-muted" id="notificationsListSummary" data-i18n="notifications.loading_feed"><?= htmlspecialchars($t('notifications.loading_feed', 'Загрузка уведомлений...'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>
    <div class="crm-notifications-list" data-notifications-list aria-live="polite" aria-busy="true">
      <div class="crm-card crm-empty-state"><strong data-i18n="notifications.loading_list"><?= htmlspecialchars($t('notifications.loading_list', 'Загрузка уведомлений'), ENT_QUOTES, 'UTF-8') ?></strong><p class="mb-0" data-i18n="notifications.loading_list_text"><?= htmlspecialchars($t('notifications.loading_list_text', 'Подготавливаем список событий и уведомлений из API.'), ENT_QUOTES, 'UTF-8') ?></p></div>
    </div>
  </section>
</div>
</main></div></div>
