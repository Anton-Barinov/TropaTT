<?php declare(strict_types=1); ?>
<?php $title = $t('admin_languages.title', 'TropaTT — Языки и локализация'); ?>
<body data-page="admin-languages" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-admin-languages-page">
  <div class="crm-page-head">
    <div>
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="admin_languages.link_admin"><?= htmlspecialchars($t('admin_languages.link_admin', 'Админка'), ENT_QUOTES, 'UTF-8') ?></a></li>
        <li class="breadcrumb-item active" data-i18n="admin_languages.breadcrumb"><?= htmlspecialchars($t('admin_languages.breadcrumb', 'Языки и локализация'), ENT_QUOTES, 'UTF-8') ?></li>
      </ol>
      <h1 class="crm-page-title" data-i18n="admin_languages.page_title"><?= htmlspecialchars($t('admin_languages.page_title', 'Языки и локализация'), ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="crm-subtitle" data-i18n="admin_languages.subtitle"><?= htmlspecialchars($t('admin_languages.subtitle', 'Управление доступными языками интерфейса, статусом активности и языком по умолчанию.'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="d-flex gap-2">
      <button id="adminLanguagesRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="admin_languages.refresh_btn">
        <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> <?= htmlspecialchars($t('admin_languages.refresh_btn', 'Обновить'), ENT_QUOTES, 'UTF-8') ?>
      </button>
    </div>
  </div>

  <div class="crm-admin-settings-note mb-3 d-flex align-items-start gap-2" role="note" data-i18n="admin_languages.note">
    <i class="fa-solid fa-circle-info crm-admin-settings-note-icon text-primary" aria-hidden="true"></i>
    <span><?= htmlspecialchars($t('admin_languages.note', 'Отключенные языки не удаляются из системы, но скрываются из формы входа и настроек пользователя. Язык по умолчанию отключить нельзя.'), ENT_QUOTES, 'UTF-8') ?></span>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-12">
      <div class="crm-card crm-section-card">
        <div class="crm-section-head">
          <div>
            <h2 class="h6 mb-0" data-i18n="admin_languages.section_installed_title"><?= htmlspecialchars($t('admin_languages.section_installed_title', 'Установленные языки'), ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="crm-section-note" data-i18n="admin_languages.section_installed_note"><?= htmlspecialchars($t('admin_languages.section_installed_note', 'Список доступных языковых пакетов и управление их доступностью.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-secondary-subtle text-secondary border" id="adminLanguagesCountBadge">0</span>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover crm-table align-middle mb-0">
            <thead>
              <tr>
                <th scope="col" style="width: 25%;" data-i18n="admin_languages.th_language"><?= htmlspecialchars($t('admin_languages.th_language', 'Язык'), ENT_QUOTES, 'UTF-8') ?></th>
                <th scope="col" style="width: 20%;" data-i18n="admin_languages.th_native_name"><?= htmlspecialchars($t('admin_languages.th_native_name', 'Самоназвание'), ENT_QUOTES, 'UTF-8') ?></th>
                <th scope="col" style="width: 15%;" data-i18n="admin_languages.th_direction"><?= htmlspecialchars($t('admin_languages.th_direction', 'Письмо'), ENT_QUOTES, 'UTF-8') ?></th>
                <th scope="col" style="width: 15%;" data-i18n="admin_languages.th_type"><?= htmlspecialchars($t('admin_languages.th_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></th>
                <th scope="col" style="width: 15%;" data-i18n="admin_languages.th_default"><?= htmlspecialchars($t('admin_languages.th_default', 'По умолчанию'), ENT_QUOTES, 'UTF-8') ?></th>
                <th scope="col" style="width: 10%;" class="text-end" data-i18n="admin_languages.th_status"><?= htmlspecialchars($t('admin_languages.th_status', 'Активность'), ENT_QUOTES, 'UTF-8') ?></th>
              </tr>
            </thead>
            <tbody id="adminLanguagesTableBody">
              <tr>
                <td colspan="6" class="text-center text-muted py-4" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>
</div></div>
