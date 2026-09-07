<?php declare(strict_types=1); ?>
<?php $title = $t('admin_knowledge.title', 'TropaTT — Настройки базы знаний'); ?>
<body data-page="admin-knowledge" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-knowledge-page"><?php crm_page_head([
  ['label' => $t('page.home', 'Главная'), 'href' => 'index.php?route=dashboard'],
  ['label' => $t('admin.title', 'Админка'), 'href' => 'index.php?route=admin'],
  ['label' => $t('admin_knowledge.page_title', 'База знаний'), 'active' => true],
], $t('admin_knowledge.page_title', 'Настройки базы знаний'), $t('admin_knowledge.subtitle', 'Разделы, шаблоны и контроль качества корпоративной wiki.'), '<a class="btn crm-btn-secondary" href="index.php?route=knowledge">' . htmlspecialchars($t('knowledge.page_title', 'База знаний'), ENT_QUOTES, 'UTF-8') . '</a><button class="btn crm-btn-secondary" type="button" id="adminKnowledgeExportBtn">' . htmlspecialchars($t('admin_knowledge.btn_export', 'Экспорт'), ENT_QUOTES, 'UTF-8') . '</button><button class="btn crm-btn-secondary" type="button" id="adminKnowledgeImportBtn">' . htmlspecialchars($t('admin_knowledge.btn_import', 'Импорт'), ENT_QUOTES, 'UTF-8') . '</button>'); ?>

<section class="crm-knowledge-stats" id="adminKnowledgeStats">
  <div class="crm-card"><strong>0</strong><span data-i18n="knowledge.stat_spaces"><?= htmlspecialchars($t('knowledge.stat_spaces', 'разделов'), ENT_QUOTES, 'UTF-8') ?></span></div>
  <div class="crm-card"><strong>0</strong><span data-i18n="knowledge.stat_pages"><?= htmlspecialchars($t('knowledge.stat_pages', 'страниц'), ENT_QUOTES, 'UTF-8') ?></span></div>
  <div class="crm-card"><strong>0</strong><span data-i18n="knowledge.stat_published"><?= htmlspecialchars($t('knowledge.stat_published', 'опубликовано'), ENT_QUOTES, 'UTF-8') ?></span></div>
  <div class="crm-card"><strong>0</strong><span data-i18n="knowledge.stat_drafts"><?= htmlspecialchars($t('knowledge.stat_drafts', 'черновиков'), ENT_QUOTES, 'UTF-8') ?></span></div>
</section>

<div class="crm-knowledge-tabs">
  <ul class="nav nav-tabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="kb-tab-spaces" data-bs-toggle="tab" data-bs-target="#kb-panel-spaces" type="button" role="tab" aria-controls="kb-panel-spaces" aria-selected="true" data-i18n="admin_knowledge.spaces_title"><?= htmlspecialchars($t('admin_knowledge.spaces_title', 'Разделы'), ENT_QUOTES, 'UTF-8') ?></button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="kb-tab-templates" data-bs-toggle="tab" data-bs-target="#kb-panel-templates" type="button" role="tab" aria-controls="kb-panel-templates" aria-selected="false" data-i18n="admin_knowledge.templates_title"><?= htmlspecialchars($t('admin_knowledge.templates_title', 'Шаблоны'), ENT_QUOTES, 'UTF-8') ?></button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="kb-tab-review" data-bs-toggle="tab" data-bs-target="#kb-panel-review" type="button" role="tab" aria-controls="kb-panel-review" aria-selected="false" data-i18n="admin_knowledge.review_title"><?= htmlspecialchars($t('admin_knowledge.review_title', 'Очередь проверки'), ENT_QUOTES, 'UTF-8') ?></button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="kb-tab-outdated" data-bs-toggle="tab" data-bs-target="#kb-panel-outdated" type="button" role="tab" aria-controls="kb-panel-outdated" aria-selected="false" data-i18n="admin_knowledge.outdated_title"><?= htmlspecialchars($t('admin_knowledge.outdated_title', 'Требуют ревью'), ENT_QUOTES, 'UTF-8') ?></button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="kb-tab-analytics" data-bs-toggle="tab" data-bs-target="#kb-panel-analytics" type="button" role="tab" aria-controls="kb-panel-analytics" aria-selected="false" data-i18n="admin_knowledge.analytics_title"><?= htmlspecialchars($t('admin_knowledge.analytics_title', 'Аналитика'), ENT_QUOTES, 'UTF-8') ?></button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="kb-tab-ai" data-bs-toggle="tab" data-bs-target="#kb-panel-ai" type="button" role="tab" aria-controls="kb-panel-ai" aria-selected="false" data-i18n="admin_knowledge.ai_title"><?= htmlspecialchars($t('admin_knowledge.ai_title', 'AI'), ENT_QUOTES, 'UTF-8') ?></button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="kb-tab-page-types" data-bs-toggle="tab" data-bs-target="#kb-panel-page-types" type="button" role="tab" aria-controls="kb-panel-page-types" aria-selected="false" data-i18n="admin_knowledge.page_types_title"><?= htmlspecialchars($t('admin_knowledge.page_types_title', 'Типы страниц'), ENT_QUOTES, 'UTF-8') ?></button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="kb-tab-indexation" data-bs-toggle="tab" data-bs-target="#kb-panel-indexation" type="button" role="tab" aria-controls="kb-panel-indexation" aria-selected="false" data-i18n="admin_knowledge.indexation_title"><?= htmlspecialchars($t('admin_knowledge.indexation_title', 'Индексация'), ENT_QUOTES, 'UTF-8') ?></button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="kb-tab-settings" data-bs-toggle="tab" data-bs-target="#kb-panel-settings" type="button" role="tab" aria-controls="kb-panel-settings" aria-selected="false" data-i18n="admin_knowledge.settings_title"><?= htmlspecialchars($t('admin_knowledge.settings_title', 'Настройки'), ENT_QUOTES, 'UTF-8') ?></button>
    </li>
  </ul>
  <div class="tab-content mt-3">
    <div class="tab-pane fade show active" id="kb-panel-spaces" role="tabpanel" aria-labelledby="kb-tab-spaces">
      <section class="crm-card crm-section-card">
        <div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="admin_knowledge.spaces_title"><?= htmlspecialchars($t('admin_knowledge.spaces_title', 'Разделы'), ENT_QUOTES, 'UTF-8') ?></h2><p class="text-muted mb-0 small" data-i18n="admin_knowledge.spaces_hint"><?= htmlspecialchars($t('admin_knowledge.spaces_hint', 'Управляйте областями знаний и их видимостью.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
        <div class="table-responsive">
          <table class="table crm-table align-middle mb-0"><thead><tr><th><?= htmlspecialchars($t('knowledge.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars($t('knowledge.visibility', 'Видимость'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars($t('knowledge.stat_pages', 'Страниц'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars($t('admin_knowledge.th_permissions', 'Доступ'), ENT_QUOTES, 'UTF-8') ?></th><th></th></tr></thead><tbody id="adminKnowledgeSpaces"><tr><td colspan="5" class="text-muted"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table>
        </div>
      </section>
    </div>
    <div class="tab-pane fade" id="kb-panel-templates" role="tabpanel" aria-labelledby="kb-tab-templates">
      <section class="crm-card crm-section-card">
        <div class="crm-section-head"><div><h2 class="h5 mb-0"><?= htmlspecialchars($t('admin_knowledge.templates_title', 'Шаблоны'), ENT_QUOTES, 'UTF-8') ?></h2><p class="text-muted mb-0 small"><?= htmlspecialchars($t('admin_knowledge.templates_hint', 'Типовые структуры для инструкций, FAQ и регламентов.'), ENT_QUOTES, 'UTF-8') ?></p></div><button class="btn crm-btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#knowledgeTemplateModal"><?= htmlspecialchars($t('admin_knowledge.btn_create_template', 'Создать шаблон'), ENT_QUOTES, 'UTF-8') ?></button></div>
        <div class="crm-knowledge-list" id="adminKnowledgeTemplates"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </section>
    </div>
    <div class="tab-pane fade" id="kb-panel-review" role="tabpanel" aria-labelledby="kb-tab-review">
      <section class="crm-card crm-section-card">
        <div class="crm-section-head"><h2 class="h5 mb-0"><?= htmlspecialchars($t('admin_knowledge.review_title', 'Очередь проверки'), ENT_QUOTES, 'UTF-8') ?></h2></div>
        <div class="crm-knowledge-list" id="adminKnowledgeReview"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </section>
    </div>
    <div class="tab-pane fade" id="kb-panel-outdated" role="tabpanel" aria-labelledby="kb-tab-outdated">
      <section class="crm-card crm-section-card">
        <div class="crm-section-head"><h2 class="h5 mb-0"><?= htmlspecialchars($t('admin_knowledge.outdated_title', 'Требуют ревью'), ENT_QUOTES, 'UTF-8') ?></h2></div>
        <div class="crm-knowledge-list" id="adminKnowledgeOutdated"><div class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </section>
    </div>
    <div class="tab-pane fade" id="kb-panel-analytics" role="tabpanel" aria-labelledby="kb-tab-analytics">
      <section class="crm-card crm-section-card" id="adminKnowledgeAnalyticsSection">
        <div class="crm-section-head"><h2 class="h5 mb-0"><?= htmlspecialchars($t('admin_knowledge.analytics_title', 'Аналитика'), ENT_QUOTES, 'UTF-8') ?></h2></div>
        <div id="adminKnowledgeAnalytics" class="row g-3 p-3">
    <div class="col-md-3 col-6"><div class="crm-analytics-card"><strong id="analyticsTotalPages">—</strong><span><?= htmlspecialchars($t('admin_knowledge.analytics_total_pages', 'Всего страниц'), ENT_QUOTES, 'UTF-8') ?></span></div></div>
    <div class="col-md-3 col-6"><div class="crm-analytics-card"><strong id="analyticsPublished">—</strong><span><?= htmlspecialchars($t('admin_knowledge.analytics_published', 'Опубликовано'), ENT_QUOTES, 'UTF-8') ?></span></div></div>
    <div class="col-md-3 col-6"><div class="crm-analytics-card"><strong id="analyticsDrafts">—</strong><span><?= htmlspecialchars($t('admin_knowledge.analytics_drafts', 'Черновиков'), ENT_QUOTES, 'UTF-8') ?></span></div></div>
    <div class="col-md-3 col-6"><div class="crm-analytics-card"><strong id="analyticsReview">—</strong><span><?= htmlspecialchars($t('admin_knowledge.analytics_review', 'На проверке'), ENT_QUOTES, 'UTF-8') ?></span></div></div>
    <div class="col-md-3 col-6"><div class="crm-analytics-card"><strong id="analyticsArchived">—</strong><span><?= htmlspecialchars($t('admin_knowledge.analytics_archived', 'В архиве'), ENT_QUOTES, 'UTF-8') ?></span></div></div>
    <div class="col-md-3 col-6"><div class="crm-analytics-card"><strong id="analyticsSpaces">—</strong><span><?= htmlspecialchars($t('admin_knowledge.analytics_spaces', 'Активных разделов'), ENT_QUOTES, 'UTF-8') ?></span></div></div>
    <div class="col-md-3 col-6"><div class="crm-analytics-card"><strong id="analyticsComments">—</strong><span><?= htmlspecialchars($t('admin_knowledge.analytics_comments', 'Комментариев'), ENT_QUOTES, 'UTF-8') ?></span></div></div>
    <div class="col-md-3 col-6"><div class="crm-analytics-card"><strong id="analyticsVersions">—</strong><span><?= htmlspecialchars($t('admin_knowledge.analytics_versions', 'Версий'), ENT_QUOTES, 'UTF-8') ?></span></div></div>
  </div>
</section>
</div>
    <div class="tab-pane fade" id="kb-panel-ai" role="tabpanel" aria-labelledby="kb-tab-ai">
      <section class="crm-card crm-section-card">
        <div class="crm-section-head"><h2 class="h5 mb-0"><?= htmlspecialchars($t('admin_knowledge.ai_title', 'AI'), ENT_QUOTES, 'UTF-8') ?></h2></div>
        <div class="p-3">
          <div class="row g-3">
            <div class="col-md-4">
              <div class="crm-card p-3">
                <h6 class="fw-bold"><?= htmlspecialchars($t('admin_knowledge.ai_duplicates_title', 'Поиск дублей'), ENT_QUOTES, 'UTF-8') ?></h6>
                <p class="small text-muted"><?= htmlspecialchars($t('admin_knowledge.ai_duplicates_hint', 'Найти страницы с похожими названиями и содержимым.'), ENT_QUOTES, 'UTF-8') ?></p>
                <button class="btn btn-sm crm-btn-secondary" type="button" id="adminAiDuplicatesBtn"><?= htmlspecialchars($t('admin_knowledge.ai_find_btn', 'Найти дубли'), ENT_QUOTES, 'UTF-8') ?></button>
                <div id="adminAiDuplicatesResult" class="mt-2 small"></div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="crm-card p-3">
                <h6 class="fw-bold"><?= htmlspecialchars($t('admin_knowledge.ai_orphans_title', 'Страницы без владельца'), ENT_QUOTES, 'UTF-8') ?></h6>
                <p class="small text-muted"><?= htmlspecialchars($t('admin_knowledge.ai_orphans_hint', 'Страницы, у которых не назначен ответственный.'), ENT_QUOTES, 'UTF-8') ?></p>
                <button class="btn btn-sm crm-btn-secondary" type="button" id="adminAiOrphansBtn"><?= htmlspecialchars($t('admin_knowledge.ai_find_btn', 'Найти'), ENT_QUOTES, 'UTF-8') ?></button>
                <div id="adminAiOrphansResult" class="mt-2 small"></div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="crm-card p-3">
                <h6 class="fw-bold"><?= htmlspecialchars($t('admin_knowledge.ai_structure_title', 'Структура раздела'), ENT_QUOTES, 'UTF-8') ?></h6>
                <p class="small text-muted"><?= htmlspecialchars($t('admin_knowledge.ai_structure_hint', 'Рекомендовать группировку материалов.'), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="input-group input-group-sm mb-1">
                  <select id="adminAiStructureSpace" class="form-select"><option value=""><?= htmlspecialchars($t('admin_knowledge.permissions_select_subject', 'Выберите раздел...'), ENT_QUOTES, 'UTF-8') ?></option></select>
                  <button class="btn crm-btn-secondary" type="button" id="adminAiStructureBtn"><?= htmlspecialchars($t('admin_knowledge.ai_analyze_btn', 'Анализ'), ENT_QUOTES, 'UTF-8') ?></button>
                </div>
                <div id="adminAiStructureResult" class="mt-1 small"></div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
    <div class="tab-pane fade" id="kb-panel-page-types" role="tabpanel" aria-labelledby="kb-tab-page-types">
      <section class="crm-card crm-section-card">
        <div class="crm-section-head"><h2 class="h5 mb-0"><?= htmlspecialchars($t('admin_knowledge.page_types_title', 'Типы страниц'), ENT_QUOTES, 'UTF-8') ?></h2><p class="text-muted mb-0 small"><?= htmlspecialchars($t('admin_knowledge.page_types_hint', 'Доступные типы страниц, используемые в базе знаний.'), ENT_QUOTES, 'UTF-8') ?></p></div>
        <div class="table-responsive">
          <table class="table crm-table align-middle mb-0"><thead><tr><th><?= htmlspecialchars($t('knowledge.field_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars($t('knowledge.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars($t('knowledge.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="adminKnowledgePageTypes"><tr><td colspan="3" class="text-muted"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table>
        </div>
      </section>
    </div>
    <div class="tab-pane fade" id="kb-panel-indexation" role="tabpanel" aria-labelledby="kb-tab-indexation">
      <section class="crm-card crm-section-card">
        <div class="crm-section-head"><h2 class="h5 mb-0"><?= htmlspecialchars($t('admin_knowledge.indexation_title', 'Индексация'), ENT_QUOTES, 'UTF-8') ?></h2><p class="text-muted mb-0 small"><?= htmlspecialchars($t('admin_knowledge.indexation_hint', 'Перестроить поисковый индекс, кэш прав и очистить старые черновики.'), ENT_QUOTES, 'UTF-8') ?></p></div>
        <div class="p-3">
          <div class="row g-3">
            <div class="col-md-4">
              <div class="crm-card p-3">
                <h6 class="fw-bold"><?= htmlspecialchars($t('admin_knowledge.btn_reindex', 'Перестроить поиск'), ENT_QUOTES, 'UTF-8') ?></h6>
                <p class="small text-muted"><?= htmlspecialchars($t('admin_knowledge.indexation_hint', 'Перестроить FULLTEXT поисковый индекс по всем страницам.'), ENT_QUOTES, 'UTF-8') ?></p>
                <button class="btn btn-sm crm-btn-secondary" type="button" id="adminReindexBtn"><?= htmlspecialchars($t('admin_knowledge.btn_reindex', 'Перестроить поиск'), ENT_QUOTES, 'UTF-8') ?></button>
                <div id="adminReindexResult" class="mt-2 small"></div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="crm-card p-3">
                <h6 class="fw-bold"><?= htmlspecialchars($t('admin_knowledge.btn_rebuild_permissions', 'Перестроить права'), ENT_QUOTES, 'UTF-8') ?></h6>
                <p class="small text-muted"><?= htmlspecialchars($t('admin_knowledge.permissions_rebuilt', 'Обновить версию прав доступа для всех разделов.'), ENT_QUOTES, 'UTF-8') ?></p>
                <button class="btn btn-sm crm-btn-secondary" type="button" id="adminRebuildPermsBtn"><?= htmlspecialchars($t('admin_knowledge.btn_rebuild_permissions', 'Перестроить права'), ENT_QUOTES, 'UTF-8') ?></button>
                <div id="adminRebuildPermsResult" class="mt-2 small"></div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="crm-card p-3">
                <h6 class="fw-bold"><?= htmlspecialchars($t('admin_knowledge.btn_cleanup_drafts', 'Очистить черновики'), ENT_QUOTES, 'UTF-8') ?></h6>
                <p class="small text-muted"><?= htmlspecialchars($t('admin_knowledge.cleanup_done', 'Удалить черновики старше 90 дней.'), ENT_QUOTES, 'UTF-8') ?></p>
                <button class="btn btn-sm crm-btn-secondary" type="button" id="adminCleanupDraftsBtn"><?= htmlspecialchars($t('admin_knowledge.btn_cleanup_drafts', 'Очистить черновики'), ENT_QUOTES, 'UTF-8') ?></button>
                <div id="adminCleanupDraftsResult" class="mt-2 small"></div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
    <div class="tab-pane fade" id="kb-panel-settings" role="tabpanel" aria-labelledby="kb-tab-settings">
      <section class="crm-card crm-section-card">
        <div class="crm-section-head"><h2 class="h5 mb-0"><?= htmlspecialchars($t('admin_knowledge.settings_title', 'Настройки'), ENT_QUOTES, 'UTF-8') ?></h2><p class="text-muted mb-0 small"><?= htmlspecialchars($t('admin_knowledge.settings_hint', 'Настройте поведение базы знаний.'), ENT_QUOTES, 'UTF-8') ?></p></div>
        <div class="p-3">
          <form id="adminKnowledgeSettingsForm">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="crm-filter-label" for="settingsDefaultReviewDays"><?= htmlspecialchars($t('admin_knowledge.settings_default_review_days', 'Интервал проверки (дней)'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="number" id="settingsDefaultReviewDays" class="form-control" min="1" max="365" value="90">
                <div class="form-text text-muted small"><?= htmlspecialchars($t('admin_knowledge.settings_default_review_days_hint', 'Через сколько дней после публикации страница должна быть проверена.'), ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>
            <div class="mt-3">
              <button type="submit" class="btn crm-btn-primary" id="adminSettingsSaveBtn"><?= htmlspecialchars($t('admin_knowledge.settings_save', 'Сохранить настройки'), ENT_QUOTES, 'UTF-8') ?></button>
              <span id="adminSettingsResult" class="ms-2 small"></span>
            </div>
          </form>
        </div>
      </section>
    </div>
</div>
</div>

<div class="modal fade" id="knowledgePermissionsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><span id="knowledgePermModalTitle"><?= htmlspecialchars($t('admin_knowledge.permissions_title', 'Права доступа'), ENT_QUOTES, 'UTF-8') ?></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button></div>
    <div class="modal-body">
      <div id="knowledgePermList" class="mb-3"><div class="text-muted"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      <hr>
      <h6><?= htmlspecialchars($t('admin_knowledge.permissions_add_title', 'Добавить доступ'), ENT_QUOTES, 'UTF-8') ?></h6>
      <div class="row g-1">
        <div class="col-md-4">
          <select id="knowledgePermSubjectType" class="form-select form-select-sm">
            <option value="user"><?= htmlspecialchars($t('admin_knowledge.permissions_subject_user', 'Пользователь'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="role"><?= htmlspecialchars($t('admin_knowledge.permissions_subject_role', 'Роль'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="team"><?= htmlspecialchars($t('admin_knowledge.permissions_subject_team', 'Команда'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="department"><?= htmlspecialchars($t('admin_knowledge.permissions_subject_department', 'Отдел'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
        </div>
        <div class="col-md-4">
          <select id="knowledgePermSubjectId" class="form-select form-select-sm" style="max-width:100%"><option value=""><?= htmlspecialchars($t('admin_knowledge.permissions_select_subject', 'Выберите...'), ENT_QUOTES, 'UTF-8') ?></option></select>
        </div>
        <div class="col-md-2">
          <select id="knowledgePermAccessLevel" class="form-select form-select-sm">
            <option value="view"><?= htmlspecialchars($t('admin_knowledge.permissions_level_view', 'Просмотр'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="comment"><?= htmlspecialchars($t('admin_knowledge.permissions_level_comment', 'Комментирование'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="edit"><?= htmlspecialchars($t('admin_knowledge.permissions_level_edit', 'Редактирование'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="manage"><?= htmlspecialchars($t('admin_knowledge.permissions_level_manage', 'Управление'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-sm crm-btn-primary w-100" type="button" id="knowledgePermAddBtn"><?= htmlspecialchars($t('common.add', 'Добавить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?></button></div>
  </div></div>
</div>

<div class="modal fade" id="knowledgeTemplateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"><form class="modal-content" id="knowledgeTemplateForm">
    <div class="modal-header"><h5 class="modal-title"><?= htmlspecialchars($t('admin_knowledge.create_template_title', 'Новый шаблон'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button></div>
    <div class="modal-body">
      <div class="row g-3">
        <div class="col-md-8"><label class="crm-filter-label" for="knowledgeTemplateTitle"><?= htmlspecialchars($t('knowledge.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input id="knowledgeTemplateTitle" class="form-control" name="title" required></div>
        <div class="col-md-4"><label class="crm-filter-label" for="knowledgeTemplateType"><?= htmlspecialchars($t('knowledge.field_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></label><select id="knowledgeTemplateType" class="form-select" name="page_type"><option value="article"><?= htmlspecialchars($t('knowledge.type_article', 'Статья'), ENT_QUOTES, 'UTF-8') ?></option><option value="instruction"><?= htmlspecialchars($t('knowledge.type_instruction', 'Инструкция'), ENT_QUOTES, 'UTF-8') ?></option><option value="regulation"><?= htmlspecialchars($t('knowledge.type_regulation', 'Регламент'), ENT_QUOTES, 'UTF-8') ?></option><option value="faq"><?= htmlspecialchars($t('knowledge.type_faq', 'FAQ'), ENT_QUOTES, 'UTF-8') ?></option><option value="checklist"><?= htmlspecialchars($t('knowledge.type_checklist', 'Чеклист'), ENT_QUOTES, 'UTF-8') ?></option><option value="runbook"><?= htmlspecialchars($t('knowledge.type_runbook', 'Runbook'), ENT_QUOTES, 'UTF-8') ?></option><option value="meeting_note"><?= htmlspecialchars($t('knowledge.type_meeting_note', 'Протокол встречи'), ENT_QUOTES, 'UTF-8') ?></option><option value="decision"><?= htmlspecialchars($t('knowledge.type_decision', 'Решение'), ENT_QUOTES, 'UTF-8') ?></option><option value="client_note"><?= htmlspecialchars($t('knowledge.type_client_note', 'Заметка клиента'), ENT_QUOTES, 'UTF-8') ?></option><option value="project_note"><?= htmlspecialchars($t('knowledge.type_project_note', 'Заметка проекта'), ENT_QUOTES, 'UTF-8') ?></option><option value="onboarding"><?= htmlspecialchars($t('knowledge.type_onboarding', 'Онбординг'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
        <div class="col-12"><label class="crm-filter-label" for="knowledgeTemplateDescription"><?= htmlspecialchars($t('knowledge.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label><input id="knowledgeTemplateDescription" class="form-control" name="description"></div>
        <div class="col-12"><label class="crm-filter-label" for="knowledgeTemplateContent"><?= htmlspecialchars($t('knowledge.field_content', 'Содержание'), ENT_QUOTES, 'UTF-8') ?></label><textarea id="knowledgeTemplateContent" class="form-control" name="content_html" rows="8"></textarea></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($t('common.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button type="submit" class="btn crm-btn-primary"><?= htmlspecialchars($t('common.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button></div>
  </form></div>
</div>

</main></div></div>
<script nonce="<?= $csp_nonce ?>">
(function () {
  var i18n = window.CRM && window.CRM.i18n;
  var t = function (key, fallback) { return i18n && i18n.t ? i18n.t(key, fallback) : fallback; };
  var statsEl = document.getElementById('adminKnowledgeStats');
  function getApi() {
    return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
  }
  function waitForApi(callback, attempts) {
    if (getApi()) {
      callback();
      return;
    }
    if ((attempts || 0) > 240) {
      document.getElementById('adminKnowledgeSpaces').innerHTML = '<tr><td colspan="5" class="text-muted">' + esc(t('knowledge.load_error', 'Не удалось загрузить базу знаний.')) + '</td></tr>';
      return;
    }
    window.setTimeout(function () { waitForApi(callback, (attempts || 0) + 1); }, 50);
  }
  async function request(route, options) {
    var api = getApi();
    if (!api) throw new Error('CRM_API_NOT_READY');
    return api.request(route, options);
  }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
    });
  }
  function pageLink(item) { return 'index.php?route=knowledge-page&id=' + encodeURIComponent(item.public_id || ''); }
  function list(target, items, empty) {
    if (!items || !items.length) {
      target.innerHTML = '<div class="text-muted p-3">' + esc(empty) + '</div>';
      return;
    }
    target.innerHTML = items.map(function (item) {
      var statusBadge = '';
      if (item.status) {
        var sm = { draft: 'crm-badge-secondary', review: 'crm-badge-warning', published: 'crm-badge-success', archived: 'crm-badge-light', needs_update: 'crm-badge-danger' };
        statusBadge = '<span class="crm-badge ' + (sm[item.status] || 'crm-badge-secondary') + '" style="font-size:0.7rem;padding:0.15rem 0.4rem;margin-left:0.5rem">' + esc(item.status) + '</span>';
      }
      return '<a class="crm-knowledge-list-item" href="' + esc(pageLink(item)) + '"><span><strong>' + esc(item.title) + statusBadge + '</strong><small>' + esc(item.space_title || item.page_type || '') + '</small></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>';
    }).join('');
  }
  async function load() {
    var overview = await request('api/v1/knowledge/overview', { method: 'GET' });
    var data = overview.data || {};
    var totals = data.totals || {};
    [totals.spaces || 0, totals.pages || 0, totals.published || 0, totals.drafts || 0].forEach(function (value, index) {
      statsEl.querySelectorAll('strong')[index].textContent = String(value);
    });
    document.getElementById('adminKnowledgeSpaces').innerHTML = (data.spaces || []).map(function (space) {
      var arch = space.is_archived ? 1 : 0;
      var actionBtn = arch
        ? '<button class="btn btn-sm crm-btn-secondary" data-space-restore="' + esc(space.public_id) + '">' + esc(t('admin_knowledge.btn_restore_space', 'Восстановить')) + '</button>'
        : '<button class="btn btn-sm crm-btn-danger-soft" data-space-archive="' + esc(space.public_id) + '">' + esc(t('admin_knowledge.btn_archive_space', 'Архивировать')) + '</button>';
      var archLabel = arch ? '<span class="crm-badge crm-badge-light">' + esc(t('admin_knowledge.archived', 'Архив')) + '</span>' : '';
      return '<tr><td><strong>' + esc(space.title) + ' ' + archLabel + '</strong><div class="text-muted small">' + esc(space.description || '') + '</div></td><td>' + esc(space.visibility || '') + '</td><td>' + esc(space.pages_count || 0) + '</td><td><button class="btn btn-sm crm-btn-secondary" data-space-permissions="' + esc(space.public_id) + '" data-space-title="' + esc(space.title) + '">' + esc(t('admin_knowledge.btn_permissions', 'Доступ')) + '</button></td><td class="crm-table-actions">' + actionBtn + '</td></tr>';
    }).join('') || '<tr><td colspan="5" class="text-muted">' + esc(t('knowledge.empty_spaces', 'Разделов пока нет.')) + '</td></tr>';
    list(document.getElementById('adminKnowledgeReview'), data.review_queue || [], t('knowledge.empty_review', 'Нет страниц на проверке.'));
    list(document.getElementById('adminKnowledgeOutdated'), data.outdated || [], t('admin_knowledge.empty_outdated', 'Нет просроченных ревью.'));
    try {
      var templates = await request('api/v1/knowledge/templates', { method: 'GET' });
      document.getElementById('adminKnowledgeTemplates').innerHTML = (templates.data && templates.data.items || []).map(function (tpl) {
        return '<div class="crm-knowledge-list-item"><span><strong>' + esc(tpl.title) + '</strong><small>' + esc(tpl.description || tpl.page_type || '') + '</small></span><span class="crm-badge">' + esc(tpl.page_type || '') + '</span></div>';
      }).join('') || '<div class="text-muted p-3">' + esc(t('admin_knowledge.empty_templates', 'Шаблонов пока нет.')) + '</div>';
    } catch (e) {
      document.getElementById('adminKnowledgeTemplates').innerHTML = '<div class="text-muted p-3">' + esc(t('knowledge.load_error', 'Не удалось загрузить базу знаний.')) + '</div>';
    }
    try {
      var analyticsRes = await request('api/v1/knowledge/analytics', { method: 'GET' });
      var analyticsStats = analyticsRes.data && analyticsRes.data.stats || {};
      var map = { analyticsTotalPages: analyticsStats.total_pages, analyticsPublished: analyticsStats.published, analyticsDrafts: analyticsStats.drafts, analyticsReview: analyticsStats.review_queue, analyticsArchived: analyticsStats.archived, analyticsSpaces: analyticsStats.active_spaces, analyticsComments: analyticsStats.total_comments, analyticsVersions: analyticsStats.total_versions };
      Object.keys(map).forEach(function (id) { var el = document.getElementById(id); if (el) el.textContent = map[id] != null ? String(map[id]) : '—'; });
    } catch (e) {}
  }
  document.getElementById('knowledgeTemplateForm').addEventListener('submit', async function (event) {
    event.preventDefault();
    var body = {};
    new FormData(event.currentTarget).forEach(function (value, key) { body[key] = value; });
    await request('api/v1/knowledge/templates', { method: 'POST', body: body, idempotent: true });
    window.bootstrap && bootstrap.Modal.getOrCreateInstance(document.getElementById('knowledgeTemplateModal')).hide();
    event.currentTarget.reset();
    load();
  });
  var currentPermSpaceId = null;

  function renderPermList(items) {
    var listEl = document.getElementById('knowledgePermList');
    if (!items || !items.length) {
      listEl.innerHTML = '<div class="text-muted">' + esc(t('admin_knowledge.permissions_empty', 'Нет назначенных прав доступа')) + '</div>';
      return;
    }
    var typeMap = {
      user: t('admin_knowledge.permissions_subject_user', 'Пользователь'),
      role: t('admin_knowledge.permissions_subject_role', 'Роль'),
      team: t('admin_knowledge.permissions_subject_team', 'Команда'),
      department: t('admin_knowledge.permissions_subject_department', 'Отдел')
    };
    var levelMap = {
      view: { label: t('admin_knowledge.permissions_level_view', 'Просмотр'), cls: 'crm-badge crm-badge-secondary' },
      comment: { label: t('admin_knowledge.permissions_level_comment', 'Комментирование'), cls: 'crm-badge crm-badge-info' },
      edit: { label: t('admin_knowledge.permissions_level_edit', 'Редактирование'), cls: 'crm-badge crm-badge-success' },
      manage: { label: t('admin_knowledge.permissions_level_manage', 'Управление'), cls: 'crm-badge crm-badge-warning' },
      owner: { label: t('admin_knowledge.permissions_level_owner', 'Владелец'), cls: 'crm-badge crm-badge-danger' }
    };
    listEl.innerHTML = '<table class="table crm-table mb-0"><thead><tr><th>' + esc(t('admin_knowledge.permissions_th_subject', 'Субъект')) + '</th><th>' + esc(t('admin_knowledge.permissions_th_level', 'Уровень')) + '</th><th>' + esc(t('admin_knowledge.permissions_th_created', 'Добавлено')) + '</th><th></th></tr></thead><tbody>' + items.map(function (p) {
      var label = p.user_name || p.role_title || p.team_title || p.department_title || p.user_public_id || p.role_public_id || p.team_public_id || p.department_public_id || p.subject_id;
      var typeLabel = esc(typeMap[p.subject_type] || p.subject_type || '') + ':';
      var permissionId = p.permission_key || p.permission_id || p.id || '';
      var lev = levelMap[p.access_level] || { label: esc(p.access_level), cls: 'crm-badge crm-badge-secondary' };
      var dateStr = p.created_at ? p.created_at.substring(0, 10) : '';
      return '<tr><td><strong>' + typeLabel + ' ' + esc(label) + '</strong></td><td><span class="' + lev.cls + '" style="font-size:0.75rem">' + lev.label + '</span></td><td class="small text-muted">' + esc(dateStr) + '</td><td><button class="btn btn-sm crm-btn-danger-soft" data-perm-delete="' + esc(permissionId) + '">' + esc(t('common.delete', 'Удалить')) + '</button></td></tr>';
    }).join('') + '</tbody></table>';
  }

  async function loadPermModal(spaceId) {
    currentPermSpaceId = spaceId;
    document.getElementById('knowledgePermList').innerHTML = '<div class="text-muted">' + esc(t('knowledge.loading', 'Загрузка...')) + '</div>';
    try {
      var envelope = await request('api/v1/knowledge/spaces/' + encodeURIComponent(spaceId) + '/permissions', { method: 'GET' });
      renderPermList(envelope.data && envelope.data.items || []);
    } catch (e) {
      document.getElementById('knowledgePermList').innerHTML = '<div class="text-muted">' + esc(t('knowledge.load_error', 'Ошибка')) + '</div>';
    }
  }

  async function loadSubjectOptions(type) {
    var sel = document.getElementById('knowledgePermSubjectId');
    sel.innerHTML = '<option value="">' + esc(t('admin_knowledge.permissions_loading_subjects', 'Загрузка...')) + '</option>';
    try {
      if (type === 'user') {
        var envelope = await request('api/v1/users', { method: 'GET', query: { limit: 200 } });
        var items = envelope.data && envelope.data.items || [];
        sel.innerHTML = '<option value="">' + esc(t('admin_knowledge.permissions_select_user', 'Выберите пользователя...')) + '</option>' + items.map(function (u) {
          var label = u.name || u.full_name || u.login || u.email || u.public_id || '';
          return '<option value="' + esc(u.public_id || u.id || '') + '">' + esc(label) + '</option>';
        }).join('');
      } else if (type === 'role') {
        var envelope = await request('api/v1/roles', { method: 'GET', query: { limit: 50 } });
        var items = envelope.data && envelope.data.items || [];
        sel.innerHTML = '<option value="">' + esc(t('admin_knowledge.permissions_select_role', 'Выберите роль...')) + '</option>' + items.map(function (r) {
          return '<option value="' + esc(r.public_id || r.id || '') + '">' + esc(r.title || r.code || r.public_id || '') + '</option>';
        }).join('');
      } else if (type === 'team') {
        var envelope = await request('api/v1/teams', { method: 'GET', query: { limit: 200 } });
        var items = envelope.data && envelope.data.items || [];
        sel.innerHTML = '<option value="">' + esc(t('admin_knowledge.permissions_select_team', 'Выберите команду...')) + '</option>' + items.map(function (team) {
          return '<option value="' + esc(team.public_id || team.id || '') + '">' + esc(team.title || team.public_id || '') + '</option>';
        }).join('');
      } else if (type === 'department') {
        var envelope = await request('api/v1/departments', { method: 'GET', query: { limit: 200 } });
        var items = envelope.data && envelope.data.items || [];
        sel.innerHTML = '<option value="">' + esc(t('admin_knowledge.permissions_select_department', 'Выберите отдел...')) + '</option>' + items.map(function (department) {
          return '<option value="' + esc(department.public_id || department.id || '') + '">' + esc(department.title || department.public_id || '') + '</option>';
        }).join('');
      }
    } catch (e) {
      sel.innerHTML = '<option value="">' + esc(t('knowledge.load_error', 'Ошибка')) + '</option>';
    }
  }

  document.addEventListener('click', function (e) {
    var archiveBtn = e.target.closest('[data-space-archive]');
    if (archiveBtn) {
      request('api/v1/knowledge/spaces/' + encodeURIComponent(archiveBtn.getAttribute('data-space-archive')) + '/archive', { method: 'POST', idempotent: true }).then(load);
      return;
    }
    var restoreBtn = e.target.closest('[data-space-restore]');
    if (restoreBtn) {
      request('api/v1/knowledge/spaces/' + encodeURIComponent(restoreBtn.getAttribute('data-space-restore')) + '/restore', { method: 'POST', idempotent: true }).then(load);
      return;
    }
    var permBtn = e.target.closest('[data-space-permissions]');
    if (permBtn) {
      var spaceId = permBtn.getAttribute('data-space-permissions');
      var spaceTitle = permBtn.getAttribute('data-space-title');
      document.getElementById('knowledgePermModalTitle').textContent = esc(t('admin_knowledge.permissions_title', 'Права доступа')) + ': ' + esc(spaceTitle);
      loadPermModal(spaceId).then(function () {
        var modal = window.bootstrap && bootstrap.Modal.getOrCreateInstance(document.getElementById('knowledgePermissionsModal'));
        modal && modal.show();
      });
      return;
    }
    var permDelBtn = e.target.closest('[data-perm-delete]');
    if (permDelBtn) {
      var permId = permDelBtn.getAttribute('data-perm-delete');
      if (permId && currentPermSpaceId && confirm(t('admin_knowledge.permissions_delete_confirm', 'Удалить это право доступа?'))) {
        request('api/v1/knowledge/permissions/' + encodeURIComponent(permId), { method: 'DELETE', idempotent: true }).then(function () {
          loadPermModal(currentPermSpaceId);
        });
      }
    }
  });

  document.getElementById('knowledgePermSubjectType').addEventListener('change', function () {
    loadSubjectOptions(this.value);
  });

  document.getElementById('knowledgePermAddBtn').addEventListener('click', async function () {
    if (!currentPermSpaceId) return;
    var type = document.getElementById('knowledgePermSubjectType').value;
    var rawSubjectId = document.getElementById('knowledgePermSubjectId').value;
    var subjectId = parseInt(rawSubjectId, 10);
    var level = document.getElementById('knowledgePermAccessLevel').value;
    if (!rawSubjectId) return;
    var btn = this;
    btn.disabled = true;
    var body = { subject_type: type, access_level: level };
    if (/^\d+$/.test(rawSubjectId) && subjectId > 0) {
      body.subject_id = subjectId;
    } else {
      body.subject_public_id = rawSubjectId;
    }
    try {
      await request('api/v1/knowledge/spaces/' + encodeURIComponent(currentPermSpaceId) + '/permissions', {
        method: 'POST', body: body, idempotent: true
      });
      loadPermModal(currentPermSpaceId);
    } catch (e) {}
    btn.disabled = false;
  });

  document.getElementById('knowledgePermissionsModal').addEventListener('show.bs.modal', function () {
    loadSubjectOptions(document.getElementById('knowledgePermSubjectType').value);
  });
  waitForApi(function () { load().catch(function () {
    document.getElementById('adminKnowledgeSpaces').innerHTML = '<tr><td colspan="5" class="text-muted">' + esc(t('knowledge.load_error', 'Не удалось загрузить базу знаний.')) + '</td></tr>';
  }); });

  // ── Export modal ──
  var exportBtn = document.getElementById('adminKnowledgeExportBtn');
  var exportModal = document.getElementById('adminKnowledgeExportModal');
  var exportSpace = document.getElementById('adminKnowledgeExportSpace');
  var exportStart = document.getElementById('adminKnowledgeExportStartBtn');
  if (exportBtn && exportModal) {
    exportBtn.addEventListener('click', async function () {
      if (!exportSpace) return;
      try {
        var env = await request('api/v1/knowledge/spaces', { method: 'GET' });
        var spaces = env.data && env.data.items || [];
        exportSpace.innerHTML = '<option value="">' + esc(t('admin_knowledge.export_all_spaces', 'Все разделы')) + '</option>'
          + spaces.map(function (s) { return '<option value="' + esc(s.public_id) + '">' + esc(s.title) + '</option>'; }).join('');
      } catch (e) { exportSpace.innerHTML = '<option value="">' + esc(t('knowledge.load_error', 'Ошибка')) + '</option>'; }
      exportStart.disabled = true;
      var modal = window.bootstrap && bootstrap.Modal.getOrCreateInstance(exportModal);
      modal && modal.show();
    });
    if (exportSpace) exportSpace.addEventListener('change', function () { exportStart.disabled = false; });
    if (exportStart) {
      exportStart.addEventListener('click', async function () {
        var spaceId = exportSpace ? exportSpace.value : '';
        var fmtEl = document.querySelector('input[name="exportFormat"]:checked');
        var fmt = fmtEl ? fmtEl.value : 'json';
        exportStart.disabled = true;
        exportStart.textContent = t('admin_knowledge.export_all_progress', 'Экспорт...');
        try {
          var url, envelope;
          if (spaceId) {
            envelope = await request('api/v1/knowledge/spaces/' + encodeURIComponent(spaceId) + '/export?format=' + fmt, { method: 'GET' });
          } else {
            envelope = await request('api/v1/knowledge/export?format=' + fmt, { method: 'GET' });
          }
          var data = envelope.data || {};
          var filename = data.filename || (spaceId || 'knowledge-base') + '.' + fmt;
          var blobContent = fmt === 'json' ? JSON.stringify(data, null, 2) : (data.content || '');
          var blob = new Blob([blobContent], { type: fmt === 'json' ? 'application/json' : 'text/markdown' });
          var dlUrl = URL.createObjectURL(blob);
          var a = document.createElement('a');
          a.href = dlUrl; a.download = filename;
          document.body.appendChild(a); a.click();
          document.body.removeChild(a);
          URL.revokeObjectURL(dlUrl);
          bootstrap.Modal.getOrCreateInstance(exportModal).hide();
        } catch (e) { alert(t('admin_knowledge.export_error', 'Ошибка экспорта')); }
        exportStart.disabled = false;
        exportStart.textContent = t('admin_knowledge.export_start', 'Экспортировать');
      });
    }
  }

  // ── Import modal ──
  var importBtn = document.getElementById('adminKnowledgeImportBtn');
  var importModal = document.getElementById('adminKnowledgeImportModal');
  var importFile = document.getElementById('adminKnowledgeImportFile');
  var importSpace = document.getElementById('adminKnowledgeImportSpace');
  var importStart = document.getElementById('adminKnowledgeImportStartBtn');
  var importResult = document.getElementById('adminKnowledgeImportResult');
  if (importBtn && importModal) {
    importBtn.addEventListener('click', async function () {
      if (importSpace) {
        try {
          var env = await request('api/v1/knowledge/spaces', { method: 'GET' });
          var spaces = env.data && env.data.items || [];
          importSpace.innerHTML = '<option value="">' + esc(t('admin_knowledge.import_auto_space', 'Автоматически')) + '</option>'
            + spaces.map(function (s) { return '<option value="' + esc(s.public_id) + '">' + esc(s.title) + '</option>'; }).join('');
        } catch (e) { importSpace.innerHTML = '<option value="">' + esc(t('knowledge.load_error', 'Ошибка')) + '</option>'; }
      }
      importStart.disabled = true;
      if (importResult) importResult.classList.add('d-none');
      if (importFile) importFile.value = '';
      bootstrap.Modal.getOrCreateInstance(importModal).show();
    });
    if (importFile) importFile.addEventListener('change', function () { importStart.disabled = !importFile.files || !importFile.files.length; });
    if (importStart) {
      importStart.addEventListener('click', async function () {
        if (!importFile || !importFile.files || !importFile.files.length) return;
        var file = importFile.files[0];
        var text = await file.text();
        var spaceId = importSpace ? importSpace.value : '';
        var body = { space_public_id: spaceId };
        // Try JSON first, fall back to markdown
        var parsed = null;
        try { parsed = JSON.parse(text); } catch (e) { parsed = null; }
        if (parsed !== null && typeof parsed === 'object') {
          body.format = 'json';
          body.data = parsed;
        } else {
          body.format = 'markdown';
          body.data = { content: text, title: file.name.replace(/\.\w+$/i, '') };
        }
        try {
          var envelope = await request('api/v1/knowledge/pages/import', { method: 'POST', body: body, idempotent: true });
          var res = envelope.data || {};
          if (importResult) {
            importResult.classList.remove('d-none');
            importResult.innerHTML = '<div class="alert alert-success mb-0">'
              + esc(t('admin_knowledge.import_done', 'Импорт завершён')) + ': ' + esc(res.imported || 0) + ' ' + esc(t('knowledge.stat_pages', 'страниц'))
              + (res.errors && res.errors.length ? '<br><span class="text-danger">' + esc(t('admin_knowledge.import_errors', 'Ошибки')) + ': ' + esc(res.errors.join(', ')) + '</span>' : '')
              + '</div>';
          }
          importStart.disabled = true;
          load();
        } catch (e) {
          if (importResult) {
            importResult.classList.remove('d-none');
            importResult.innerHTML = '<div class="alert alert-danger mb-0">' + esc(t('admin_knowledge.import_error', 'Ошибка импорта')) + '</div>';
          }
        }
      });
    }
  }

  // Admin AI features
  var adminAi = {
    duplicatesBtn: document.getElementById('adminAiDuplicatesBtn'),
    duplicatesResult: document.getElementById('adminAiDuplicatesResult'),
    orphansBtn: document.getElementById('adminAiOrphansBtn'),
    orphansResult: document.getElementById('adminAiOrphansResult'),
    structureSpace: document.getElementById('adminAiStructureSpace'),
    structureBtn: document.getElementById('adminAiStructureBtn'),
    structureResult: document.getElementById('adminAiStructureResult'),
  };
  if (adminAi.duplicatesBtn) {
    adminAi.duplicatesBtn.addEventListener('click', async function () {
      if (!adminAi.duplicatesResult) return;
      adminAi.duplicatesBtn.disabled = true;
      adminAi.duplicatesResult.innerHTML = '<em>' + esc(t('knowledge.loading', 'Загрузка...')) + '</em>';
      try {
        var env = await request('api/v1/knowledge/ai/admin/find-duplicates', { method: 'POST', body: { threshold: 0.5 } });
        var items = (env.data || {}).items || [];
        if (!items.length) {
          adminAi.duplicatesResult.innerHTML = '<em class="text-success">' + esc(t('admin_knowledge.ai_no_duplicates', 'Дублей не найдено')) + '</em>';
        } else {
          var html = '<div class="fw-bold mb-1">' + esc(t('admin_knowledge.ai_duplicates_found', 'Найдено дублей')) + ': ' + items.length + '</div><ul class="small mb-0 ps-3">';
          items.forEach(function (d) {
            html += '<li class="mb-1"><a href="index.php?route=knowledge-page&amp;id=' + esc(d.page_1.public_id) + '">' + esc(d.page_1.title) + '</a> ↔ <a href="index.php?route=knowledge-page&amp;id=' + esc(d.page_2.public_id) + '">' + esc(d.page_2.title) + '</a> <span class="text-muted">(' + esc(d.space_title) + ')</span></li>';
          });
          html += '</ul>';
          adminAi.duplicatesResult.innerHTML = html;
        }
      } catch (e) {
        adminAi.duplicatesResult.innerHTML = '<em class="text-danger">' + esc(t('knowledge_page.ai_error', 'AI error')) + '</em>';
      }
      adminAi.duplicatesBtn.disabled = false;
    });
  }
  if (adminAi.orphansBtn) {
    adminAi.orphansBtn.addEventListener('click', async function () {
      if (!adminAi.orphansResult) return;
      adminAi.orphansBtn.disabled = true;
      adminAi.orphansResult.innerHTML = '<em>' + esc(t('knowledge.loading', 'Загрузка...')) + '</em>';
      try {
        var env = await request('api/v1/knowledge/ai/admin/find-orphans', { method: 'GET' });
        var items = (env.data || {}).items || [];
        if (!items.length) {
          adminAi.orphansResult.innerHTML = '<em class="text-success">' + esc(t('admin_knowledge.ai_no_orphans', 'Страниц без владельца нет')) + '</em>';
        } else {
          var html = '<div class="fw-bold mb-1">' + items.length + ' ' + esc(t('admin_knowledge.ai_orphans_found', 'страниц без владельца')) + '</div><ul class="small mb-0 ps-3">';
          items.forEach(function (p) {
            html += '<li class="mb-1"><a href="index.php?route=knowledge-page&amp;id=' + esc(p.public_id) + '">' + esc(p.title) + '</a> <span class="text-muted">(' + esc(p.space_title) + ', ' + esc(p.status) + ')</span></li>';
          });
          html += '</ul>';
          adminAi.orphansResult.innerHTML = html;
        }
      } catch (e) {
        adminAi.orphansResult.innerHTML = '<em class="text-danger">' + esc(t('knowledge_page.ai_error', 'AI error')) + '</em>';
      }
      adminAi.orphansBtn.disabled = false;
    });
  }
  if (adminAi.structureSpace) {
    // Load spaces for structure select
    (async function loadStructureSpaces() {
      try {
        var env = await request('api/v1/knowledge/spaces', { method: 'GET' });
        var spaces = (env.data || {}).items || [];
        adminAi.structureSpace.innerHTML = '<option value="">' + esc(t('admin_knowledge.permissions_select_subject', 'Выберите раздел...')) + '</option>';
        spaces.forEach(function (s) {
          var opt = document.createElement('option');
          opt.value = s.public_id;
          opt.textContent = s.title;
          adminAi.structureSpace.appendChild(opt);
        });
      } catch (e) {}
    })();
  }
  if (adminAi.structureBtn && adminAi.structureSpace) {
    adminAi.structureBtn.addEventListener('click', async function () {
      var spaceId = adminAi.structureSpace.value;
      if (!spaceId || !adminAi.structureResult) return;
      adminAi.structureBtn.disabled = true;
      adminAi.structureResult.innerHTML = '<em>' + esc(t('knowledge.loading', 'Загрузка...')) + '</em>';
      try {
        var env = await request('api/v1/knowledge/ai/admin/suggest-structure/' + encodeURIComponent(spaceId), { method: 'POST', body: {} });
        var data = env.data || {};
        var suggestion = data.suggestion || [];
        if (!suggestion.length) {
          adminAi.structureResult.innerHTML = '<em class="text-muted">' + esc(data.suggestion || t('admin_knowledge.ai_no_structure', 'Недостаточно данных для рекомендации')) + '</em>';
        } else {
          var html = '<div class="fw-bold mb-1">' + esc(t('admin_knowledge.ai_structure_result', 'Рекомендуемая структура')) + '</div>';
          suggestion.forEach(function (g) {
            html += '<div class="mb-1"><span class="badge bg-light text-dark me-1">' + esc(g.title) + '</span> <span class="text-muted">' + g.count + ' ' + esc(t('knowledge.stat_pages', 'страниц')) + '</span></div>';
          });
          adminAi.structureResult.innerHTML = html;
        }
      } catch (e) {
        adminAi.structureResult.innerHTML = '<em class="text-danger">' + esc(t('knowledge_page.ai_error', 'AI error')) + '</em>';
      }
      adminAi.structureBtn.disabled = false;
    });
  }

  // ── Page types tab ──
  var pageTypes = [
    { id: 'article', icon: 'fa-file-lines', title: t('knowledge.type_article', 'Article'), desc: t('admin_knowledge.page_types_hint', 'General informational article.') },
    { id: 'instruction', icon: 'fa-list-check', title: t('knowledge.type_instruction', 'Instruction'), desc: t('knowledge.desc_instruction', 'Step-by-step guide.') },
    { id: 'regulation', icon: 'fa-scale-balanced', title: t('knowledge.type_regulation', 'Regulation'), desc: t('knowledge.desc_regulation', 'Official policy or rule.') },
    { id: 'faq', icon: 'fa-circle-question', title: t('knowledge.type_faq', 'FAQ'), desc: t('knowledge.desc_faq', 'Frequently asked questions.') },
    { id: 'checklist', icon: 'fa-check-square', title: t('knowledge.type_checklist', 'Checklist'), desc: t('knowledge.desc_checklist', 'Action item checklist.') },
    { id: 'runbook', icon: 'fa-book-open', title: t('knowledge.type_runbook', 'Runbook'), desc: t('knowledge.desc_runbook', 'Operational runbook.') },
    { id: 'meeting_note', icon: 'fa-clipboard', title: t('knowledge.type_meeting_note', 'Meeting note'), desc: t('knowledge.desc_meeting_note', 'Meeting minutes and notes.') },
    { id: 'decision', icon: 'fa-gavel', title: t('knowledge.type_decision', 'Decision'), desc: t('knowledge.desc_decision', 'Recorded decision log.') },
    { id: 'client_note', icon: 'fa-user', title: t('knowledge.type_client_note', 'Client note'), desc: t('knowledge.desc_client_note', 'Client-specific notes.') },
    { id: 'project_note', icon: 'fa-diagram-project', title: t('knowledge.type_project_note', 'Project note'), desc: t('knowledge.desc_project_note', 'Project documentation.') },
    { id: 'onboarding', icon: 'fa-graduation-cap', title: t('knowledge.type_onboarding', 'Onboarding'), desc: t('knowledge.desc_onboarding', 'New member onboarding.') },
  ];
  var ptEl = document.getElementById('adminKnowledgePageTypes');
  if (ptEl) {
    ptEl.innerHTML = pageTypes.map(function (pt) {
      return '<tr><td><i class="fa-solid ' + pt.icon + ' me-2 crm-text-muted" aria-hidden="true"></i><code>' + esc(pt.id) + '</code></td><td><strong>' + esc(pt.title) + '</strong></td><td class="text-muted small">' + esc(pt.desc) + '</td></tr>';
    }).join('');
  }

  // ── Indexation tab ──
  var reindexBtn = document.getElementById('adminReindexBtn');
  var rebuildPermsBtn = document.getElementById('adminRebuildPermsBtn');
  var cleanupDraftsBtn = document.getElementById('adminCleanupDraftsBtn');
  if (reindexBtn) {
    reindexBtn.addEventListener('click', async function () {
      reindexBtn.disabled = true;
      var resEl = document.getElementById('adminReindexResult');
      if (resEl) resEl.innerHTML = '<em>' + esc(t('knowledge.loading', 'Loading...')) + '</em>';
      try {
        await request('api/v1/admin/knowledge/reindex', { method: 'POST', idempotent: true });
        if (resEl) resEl.innerHTML = '<em class="text-success">' + esc(t('admin_knowledge.reindex_done', 'Search index rebuilt')) + '</em>';
      } catch (e) {
        if (resEl) resEl.innerHTML = '<em class="text-danger">' + esc(t('knowledge.load_error', 'Error')) + '</em>';
      }
      reindexBtn.disabled = false;
    });
  }
  if (rebuildPermsBtn) {
    rebuildPermsBtn.addEventListener('click', async function () {
      rebuildPermsBtn.disabled = true;
      var resEl = document.getElementById('adminRebuildPermsResult');
      if (resEl) resEl.innerHTML = '<em>' + esc(t('knowledge.loading', 'Loading...')) + '</em>';
      try {
        await request('api/v1/admin/knowledge/rebuild-permissions', { method: 'POST', idempotent: true });
        if (resEl) resEl.innerHTML = '<em class="text-success">' + esc(t('admin_knowledge.permissions_rebuilt', 'Permissions version bumped')) + '</em>';
      } catch (e) {
        if (resEl) resEl.innerHTML = '<em class="text-danger">' + esc(t('knowledge.load_error', 'Error')) + '</em>';
      }
      rebuildPermsBtn.disabled = false;
    });
  }
  if (cleanupDraftsBtn) {
    cleanupDraftsBtn.addEventListener('click', async function () {
      cleanupDraftsBtn.disabled = true;
      var resEl = document.getElementById('adminCleanupDraftsResult');
      if (resEl) resEl.innerHTML = '<em>' + esc(t('knowledge.loading', 'Loading...')) + '</em>';
      try {
        var env = await request('api/v1/admin/knowledge/cleanup-drafts', { method: 'POST', idempotent: true });
        var deleted = (env.data || {}).deleted_count || 0;
        if (resEl) resEl.innerHTML = '<em class="text-success">' + esc(t('admin_knowledge.cleanup_done', 'Old drafts cleaned').replace('%d', String(deleted))) + '</em>';
      } catch (e) {
        if (resEl) resEl.innerHTML = '<em class="text-danger">' + esc(t('knowledge.load_error', 'Error')) + '</em>';
      }
      cleanupDraftsBtn.disabled = false;
    });
  }

  // ── Settings tab ──
  var settingsForm = document.getElementById('adminKnowledgeSettingsForm');
  if (settingsForm) {
    // Load current settings
    (async function loadSettings() {
      try {
        var env = await request('api/v1/admin/knowledge/settings', { method: 'GET' });
        var settings = (env.data || {}).settings || {};
        var reviewDays = document.getElementById('settingsDefaultReviewDays');
        if (reviewDays && settings.default_review_days != null) {
          reviewDays.value = String(settings.default_review_days);
        }
      } catch (e) {}
    })();
    settingsForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      var saveBtn = document.getElementById('adminSettingsSaveBtn');
      var resultEl = document.getElementById('adminSettingsResult');
      if (saveBtn) saveBtn.disabled = true;
      if (resultEl) resultEl.innerHTML = '<em>' + esc(t('knowledge.loading', 'Loading...')) + '</em>';
      try {
        var reviewDays = document.getElementById('settingsDefaultReviewDays');
        var body = {};
        if (reviewDays) body.default_review_days = parseInt(reviewDays.value, 10) || 90;
        await request('api/v1/admin/knowledge/settings', { method: 'PATCH', body: body, idempotent: true });
        if (resultEl) resultEl.innerHTML = '<span class="text-success">' + esc(t('admin_knowledge.settings_saved', 'Settings saved')) + '</span>';
      } catch (e) {
        if (resultEl) resultEl.innerHTML = '<span class="text-danger">' + esc(t('knowledge.load_error', 'Error')) + '</span>';
      }
      if (saveBtn) saveBtn.disabled = false;
    });
  }

})();
</script>
<div class="modal fade" id="adminKnowledgeImportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><?= htmlspecialchars($t('admin_knowledge.import_title', 'Импорт базы знаний'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button></div>
    <div class="modal-body">
      <p class="text-muted small"><?= htmlspecialchars($t('admin_knowledge.import_hint', 'Загрузите JSON или Markdown файл, экспортированный из базы знаний.'), ENT_QUOTES, 'UTF-8') ?></p>
      <div class="mb-3">
        <label class="crm-filter-label" for="adminKnowledgeImportSpace"><?= htmlspecialchars($t('admin_knowledge.import_target_space', 'Целевой раздел'), ENT_QUOTES, 'UTF-8') ?></label>
        <select id="adminKnowledgeImportSpace" class="form-select"><option value=""><?= htmlspecialchars($t('admin_knowledge.import_auto_space', 'Автоматически'), ENT_QUOTES, 'UTF-8') ?></option></select>
      </div>
      <div class="mb-3">
        <label class="crm-filter-label" for="adminKnowledgeImportFile"><?= htmlspecialchars($t('admin_knowledge.import_file_label', 'Файл импорта'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="file" id="adminKnowledgeImportFile" class="form-control crm-file-input" accept=".json,.md,.markdown,.txt">
      </div>
      <div id="adminKnowledgeImportResult" class="d-none"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?></button>
      <button type="button" class="btn crm-btn-primary" id="adminKnowledgeImportStartBtn" disabled><?= htmlspecialchars($t('admin_knowledge.import_start', 'Начать импорт'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </div></div>
</div>

<div class="modal fade" id="adminKnowledgeExportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><?= htmlspecialchars($t('admin_knowledge.export_title', 'Экспорт базы знаний'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button></div>
    <div class="modal-body">
      <p class="text-muted small"><?= htmlspecialchars($t('admin_knowledge.export_hint', 'Выберите раздел и формат для экспорта.'), ENT_QUOTES, 'UTF-8') ?></p>
      <div class="mb-3">
        <label class="crm-filter-label" for="adminKnowledgeExportSpace"><?= htmlspecialchars($t('admin_knowledge.export_select_space', 'Раздел'), ENT_QUOTES, 'UTF-8') ?></label>
        <select id="adminKnowledgeExportSpace" class="form-select"><option value=""><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></option></select>
      </div>
      <div class="mb-3">
        <label class="crm-filter-label"><?= htmlspecialchars($t('admin_knowledge.export_format', 'Формат'), ENT_QUOTES, 'UTF-8') ?></label>
        <div class="d-flex gap-3">
          <label><input type="radio" name="exportFormat" value="json" checked> JSON</label>
          <label><input type="radio" name="exportFormat" value="markdown"> Markdown</label>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?></button>
      <button type="button" class="btn crm-btn-primary" id="adminKnowledgeExportStartBtn" disabled><?= htmlspecialchars($t('admin_knowledge.export_start', 'Экспортировать'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </div></div>
</div>

</body>
