<?php declare(strict_types=1); ?>
<?php $title = $t('teams.title', 'TropaTT — Команды и отделы'); ?>
<body data-page="teams" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-teams-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=dashboard" data-i18n="page.home"><?= htmlspecialchars($t('page.home', 'Главная'), ENT_QUOTES, 'UTF-8') ?></a></li></ol><h1 class="crm-page-title" data-i18n="teams.page_title"><?= htmlspecialchars($t('teams.page_title', 'Команды и отделы'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="teams.subtitle"><?= htmlspecialchars($t('teams.subtitle', 'Иерархическая структура команд и отделов.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="crm-page-actions"><button class="btn crm-btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#teamCreateModal"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span> <span data-i18n="teams.btn_create"><?= htmlspecialchars($t('teams.btn_create', 'Создать'), ENT_QUOTES, 'UTF-8') ?></span></button></div></div>

<div class="crm-card crm-section-card crm-filters-card mb-3">
  <div class="row g-2 align-items-end crm-teams-filters">
    <div class="col-lg-5 col-md-6"><label class="form-label" for="teamsFilterSearch" data-i18n="teams.filter_search_label"><?= htmlspecialchars($t('teams.filter_search_label', 'Поиск'), ENT_QUOTES, 'UTF-8') ?></label><input id="teamsFilterSearch" class="form-control" placeholder="<?= htmlspecialchars($t('teams.placeholder_search', 'Название команды'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="teams.placeholder_search"></div>
    <div class="col-lg-3 col-md-4"><label class="form-label" for="teamsFilterType" data-i18n="teams.filter_type_label"><?= htmlspecialchars($t('teams.filter_type_label', 'Тип'), ENT_QUOTES, 'UTF-8') ?></label><select id="teamsFilterType" class="form-select"><option value="" data-i18n="teams.opt_all_types"><?= htmlspecialchars($t('teams.opt_all_types', 'Все типы'), ENT_QUOTES, 'UTF-8') ?></option><option value="department" data-i18n="teams.opt_department"><?= htmlspecialchars($t('teams.opt_department', 'Отдел'), ENT_QUOTES, 'UTF-8') ?></option><option value="team" data-i18n="teams.opt_team"><?= htmlspecialchars($t('teams.opt_team', 'Команда'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
    <div class="col-lg-auto col-md-2"><button class="btn crm-btn-muted crm-teams-reset" type="button" id="teamsFilterReset" data-i18n="page.reset"><?= htmlspecialchars($t('page.reset', 'Сбросить'), ENT_QUOTES, 'UTF-8') ?></button></div>
  </div>
</div>

<div class="crm-card crm-section-card p-0"><div class="crm-teams-tree-container"><div class="crm-tree-header"><span class="crm-tree-col-name" data-i18n="teams.th_name"><?= htmlspecialchars($t('teams.th_name', 'Название'), ENT_QUOTES, 'UTF-8') ?></span><span class="crm-tree-col-type" data-i18n="teams.th_type"><?= htmlspecialchars($t('teams.th_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></span><span class="crm-tree-col-manager" data-i18n="teams.th_manager"><?= htmlspecialchars($t('teams.th_manager', 'Менеджер'), ENT_QUOTES, 'UTF-8') ?></span><span class="crm-tree-col-actions" data-i18n="teams.th_actions"><?= htmlspecialchars($t('teams.th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?></span></div><div id="teamsTree" class="crm-tree" role="tree"><div class="crm-tree-loading" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div></div></div>
</main></div></div>

<!-- CREATE MODAL -->
<div class="modal fade" id="teamCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-team-edit modal-dialog-centered">
    <div class="modal-content">
      <div class="team-modal-header">
        <div class="team-modal-header-left">
          <div class="team-modal-icon"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-users"></i></span></div>
          <div class="team-modal-title-group">
            <h5 class="team-modal-title" data-i18n="teams.modal_create_title"><?= htmlspecialchars($t('teams.modal_create_title', 'Создать команду'), ENT_QUOTES, 'UTF-8') ?></h5>
            <div class="team-modal-subtitle" data-i18n="teams.modal_create_subtitle"><?= htmlspecialchars($t('teams.modal_create_subtitle', 'Заполните информацию и добавьте участников'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close_aria"></button>
      </div>

      <form id="teamCreateForm" autocomplete="off">
        <div class="modal-body">
          <div class="col-12 d-none" data-form-error-summary></div>
          <div class="team-modal-grid">
            <div class="team-modal-left">
              <div class="team-section-card">
                <h6 class="team-section-title" data-i18n="teams.section_basic_info"><?= htmlspecialchars($t('teams.section_basic_info', 'Основная информация'), ENT_QUOTES, 'UTF-8') ?></h6>
                <div class="team-field-group">
                  <label for="teamCreateTitle" class="team-label"><span data-i18n="teams.field_title"><?= htmlspecialchars($t('teams.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></span> <span class="team-required">*</span></label>
                  <input id="teamCreateTitle" class="form-control team-input" name="title" maxlength="255" required placeholder="<?= htmlspecialchars($t('teams.placeholder_title', 'Например: Отдел разработки'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="teams.placeholder_title">
                </div>
                <div class="team-field-row">
                  <div class="team-field-group team-field-half">
                    <label for="teamCreateCode" class="team-label" data-i18n="teams.field_code"><?= htmlspecialchars($t('teams.field_code', 'Код'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="teamCreateCode" class="form-control team-input" name="code" maxlength="64" placeholder="<?= htmlspecialchars($t('teams.placeholder_code', 'dev-frontend'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="teams.placeholder_code">
                  </div>
                  <div class="team-field-group team-field-half">
                    <label class="team-label" data-i18n="teams.field_type"><?= htmlspecialchars($t('teams.field_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></label>
                    <div class="team-segmented" data-field="team_type">
                      <button type="button" class="team-segmented-btn is-active" data-value="team" data-i18n="teams.opt_team"><?= htmlspecialchars($t('teams.opt_team', 'Команда'), ENT_QUOTES, 'UTF-8') ?></button>
                      <button type="button" class="team-segmented-btn" data-value="department" data-i18n="teams.opt_department"><?= htmlspecialchars($t('teams.opt_department', 'Отдел'), ENT_QUOTES, 'UTF-8') ?></button>
                    </div>
                    <input type="hidden" name="team_type" value="team">
                  </div>
                </div>
                <div class="team-field-group">
                  <label for="teamCreateParent" class="team-label" data-i18n="teams.field_parent"><?= htmlspecialchars($t('teams.field_parent', 'Родительская команда'), ENT_QUOTES, 'UTF-8') ?></label>
                  <select id="teamCreateParent" class="form-select team-input" name="parent_public_id"><option value="" data-i18n="teams.opt_no_parent"><?= htmlspecialchars($t('teams.opt_no_parent', 'Без родителя'), ENT_QUOTES, 'UTF-8') ?></option></select>
                </div>
              </div>

              <div class="team-section-card">
                <h6 class="team-section-title" data-i18n="teams.section_management"><?= htmlspecialchars($t('teams.section_management', 'Управление'), ENT_QUOTES, 'UTF-8') ?></h6>
                <div class="team-field-group">
                  <label for="teamCreateManager" class="team-label" data-i18n="teams.field_manager"><?= htmlspecialchars($t('teams.field_manager', 'Менеджер команды'), ENT_QUOTES, 'UTF-8') ?></label>
                  <select id="teamCreateManager" class="form-select team-input" name="manager_user_public_id"><option value="" data-i18n="teams.opt_default_current_user"><?= htmlspecialchars($t('teams.opt_default_current_user', 'По умолчанию текущий пользователь'), ENT_QUOTES, 'UTF-8') ?></option></select>
                </div>
              </div>

              <div class="team-section-card team-knowledge-card">
                <div class="team-knowledge-head">
                  <h6 class="team-section-title team-knowledge-title" data-i18n="teams.section_knowledge">
                    <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-book-open"></i></span>
                    <?= htmlspecialchars($t('teams.section_knowledge', 'Материалы команды'), ENT_QUOTES, 'UTF-8') ?>
                  </h6>
                  <button class="btn btn-sm crm-btn-subtle" type="button" id="teamCreateKnowledgeAttachBtn" data-i18n="teams.btn_attach_knowledge"><?= htmlspecialchars($t('teams.btn_attach_knowledge', 'Прикрепить статью'), ENT_QUOTES, 'UTF-8') ?></button>
                </div>
                <div class="team-knowledge-hint" data-i18n="teams.knowledge_create_hint"><?= htmlspecialchars($t('teams.knowledge_create_hint', 'Выбранные материалы будут привязаны к команде после её создания.'), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="team-knowledge-list" id="teamCreateKnowledgeList">
                  <div class="team-knowledge-empty"><p><?= htmlspecialchars($t('teams.knowledge_empty', 'Нет материалов команды'), ENT_QUOTES, 'UTF-8') ?></p></div>
                </div>
              </div>
            </div>

            <div class="team-modal-right">
              <div class="team-participant-panel">
                <div class="team-participant-toolbar">
                  <h6 class="team-participant-title"><span data-i18n="teams.participant_title"><?= htmlspecialchars($t('teams.participant_title', 'Участники'), ENT_QUOTES, 'UTF-8') ?></span> <span class="team-participant-count" data-create-count>0</span></h6>
                </div>
                <div class="team-participant-search-wrap">
                  <span class="crm-icon team-search-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                  <input type="text" class="team-search-input" data-create-search placeholder="<?= htmlspecialchars($t('teams.placeholder_search_participant', 'Найти сотрудника...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="teams.placeholder_search_participant">
                  <div class="team-search-dropdown" data-create-search-results hidden></div>
                </div>
                <div class="team-participant-list" data-create-participant-list role="listbox"></div>
                <div class="team-empty-state" data-create-empty>
                  <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-user-plus"></i></span>
                  <p data-i18n="teams.empty_add_participants"><?= htmlspecialchars($t('teams.empty_add_participants', 'Добавьте участников'), ENT_QUOTES, 'UTF-8') ?></p>
                  <span class="team-empty-hint" data-i18n="teams.hint_search_add"><?= htmlspecialchars($t('teams.hint_search_add', 'Используйте поиск для быстрого добавления'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="team-modal-footer">
          <div class="team-footer-spacer"></div>
          <div class="team-footer-actions">
            <button class="btn btn crm-btn-secondary crm-btn-compact" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
            <button class="btn btn crm-btn-primary crm-btn-compact" type="submit" data-create-save>
              <span data-create-save-text data-i18n="page.create"><?= htmlspecialchars($t('page.create', 'Создать'), ENT_QUOTES, 'UTF-8') ?></span>
              <span class="spinner-border spinner-border-sm" data-create-save-spinner hidden></span>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="teamEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-team-edit modal-dialog-centered" style="max-width:820px">
    <div class="modal-content">
      <div class="team-modal-header">
        <div class="team-modal-header-left">
          <div class="team-modal-icon"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-users"></i></span></div>
          <div class="team-modal-title-group">
            <h5 class="team-modal-title" data-i18n="teams.modal_edit_title"><?= htmlspecialchars($t('teams.modal_edit_title', 'Редактирование команды'), ENT_QUOTES, 'UTF-8') ?></h5>
            <div class="team-modal-meta">
              <span class="team-modal-meta-id" data-edit-meta-id></span>
              <span class="team-modal-meta-sep"></span>
              <span class="team-modal-meta-created" data-edit-meta-created></span>
              <span class="team-modal-meta-sep"></span>
              <span class="team-modal-meta-count" data-edit-meta-count></span>
            </div>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close_aria"></button>
      </div>

      <form id="teamEditForm" autocomplete="off">
        <input type="hidden" name="public_id">
        <div class="modal-body">
          <div class="col-12 d-none" data-form-error-summary></div>
          <div class="team-modal-grid">
            <div class="team-modal-left">
              <div class="team-section-card">
                <h6 class="team-section-title" data-i18n="teams.section_basic_info"><?= htmlspecialchars($t('teams.section_basic_info', 'Основная информация'), ENT_QUOTES, 'UTF-8') ?></h6>
                <div class="team-field-group">
                  <label for="teamEditTitle" class="team-label"><span data-i18n="teams.field_title"><?= htmlspecialchars($t('teams.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></span> <span class="team-required">*</span></label>
                  <input id="teamEditTitle" class="form-control team-input" name="title" maxlength="255" required>
                </div>
                <div class="team-field-row">
                  <div class="team-field-group team-field-half">
                    <label for="teamEditCode" class="team-label" data-i18n="teams.field_code"><?= htmlspecialchars($t('teams.field_code', 'Код'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="teamEditCode" class="form-control team-input" name="code" maxlength="64">
                  </div>
                  <div class="team-field-group team-field-half">
                    <label class="team-label" data-i18n="teams.field_type"><?= htmlspecialchars($t('teams.field_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></label>
                    <div class="team-segmented" data-field="team_type">
                      <button type="button" class="team-segmented-btn" data-value="team" data-i18n="teams.opt_team"><?= htmlspecialchars($t('teams.opt_team', 'Команда'), ENT_QUOTES, 'UTF-8') ?></button>
                      <button type="button" class="team-segmented-btn" data-value="department" data-i18n="teams.opt_department"><?= htmlspecialchars($t('teams.opt_department', 'Отдел'), ENT_QUOTES, 'UTF-8') ?></button>
                    </div>
                    <input type="hidden" name="team_type" value="team">
                  </div>
                </div>
                <div class="team-field-group">
                  <label for="teamEditParent" class="team-label" data-i18n="teams.field_parent"><?= htmlspecialchars($t('teams.field_parent', 'Родительская команда'), ENT_QUOTES, 'UTF-8') ?></label>
                  <select id="teamEditParent" class="form-select team-input" name="parent_public_id"><option value="" data-i18n="teams.opt_no_parent"><?= htmlspecialchars($t('teams.opt_no_parent', 'Без родителя'), ENT_QUOTES, 'UTF-8') ?></option></select>
                </div>
              </div>

              <div class="team-section-card">
                <h6 class="team-section-title" data-i18n="teams.section_management"><?= htmlspecialchars($t('teams.section_management', 'Управление'), ENT_QUOTES, 'UTF-8') ?></h6>
                <div class="team-field-group">
                  <label for="teamEditManager" class="team-label" data-i18n="teams.field_manager"><?= htmlspecialchars($t('teams.field_manager', 'Менеджер команды'), ENT_QUOTES, 'UTF-8') ?></label>
                  <select id="teamEditManager" class="form-select team-input" name="manager_user_public_id"><option value="" data-i18n="teams.opt_no_manager"><?= htmlspecialchars($t('teams.opt_no_manager', 'Не назначен'), ENT_QUOTES, 'UTF-8') ?></option></select>
                </div>
              </div>

              <div class="team-section-card team-knowledge-card">
                <div class="team-knowledge-head">
                  <h6 class="team-section-title team-knowledge-title" data-i18n="teams.section_knowledge">
                    <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-book-open"></i></span>
                    <?= htmlspecialchars($t('teams.section_knowledge', 'Материалы команды'), ENT_QUOTES, 'UTF-8') ?>
                  </h6>
                  <div class="team-knowledge-actions">
                    <button class="btn btn-sm crm-btn-subtle" type="button" id="teamKnowledgeAttachBtn" data-i18n="teams.btn_attach_knowledge"><?= htmlspecialchars($t('teams.btn_attach_knowledge', 'Прикрепить статью'), ENT_QUOTES, 'UTF-8') ?></button>
                    <a class="btn btn-sm crm-btn-subtle" href="index.php?route=knowledge" id="teamKnowledgeLink" data-i18n="teams.btn_knowledge">
                      <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>
                      <?= htmlspecialchars($t('teams.btn_knowledge', 'Перейти в базу знаний'), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                  </div>
                </div>
                <div class="team-knowledge-list" id="teamKnowledgeList">
                  <div class="team-knowledge-empty"><p><?= htmlspecialchars($t('teams.knowledge_empty', 'Нет материалов команды'), ENT_QUOTES, 'UTF-8') ?></p></div>
                </div>
              </div>
            </div>

            <div class="team-modal-right">
              <div class="team-participant-panel">
                <div class="team-participant-toolbar">
                  <h6 class="team-participant-title"><span data-i18n="teams.participant_title"><?= htmlspecialchars($t('teams.participant_title', 'Участники'), ENT_QUOTES, 'UTF-8') ?></span> <span class="team-participant-count" data-edit-count>0</span></h6>
                </div>
                <div class="team-participant-search-wrap">
                  <span class="crm-icon team-search-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                  <input type="text" class="team-search-input" data-edit-search placeholder="<?= htmlspecialchars($t('teams.placeholder_search_participant', 'Найти сотрудника...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="teams.placeholder_search_participant">
                  <div class="team-search-dropdown" data-edit-search-results hidden></div>
                </div>
                <div class="team-participant-list" data-edit-participant-list role="listbox"></div>
                <div class="team-empty-state" data-edit-empty hidden>
                  <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-user-plus"></i></span>
                  <p data-i18n="teams.empty_no_participants"><?= htmlspecialchars($t('teams.empty_no_participants', 'Нет участников'), ENT_QUOTES, 'UTF-8') ?></p>
                  <span class="team-empty-hint" data-i18n="teams.hint_search_add"><?= htmlspecialchars($t('teams.hint_search_add', 'Используйте поиск для добавления'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
              </div>
            </div>
          </div>

        </div>

        <div class="team-modal-footer">
          <div class="team-footer-danger">
            <button class="btn btn crm-btn-danger-icon crm-btn-compact" type="button" data-team-delete>
              <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-trash-can"></i></span>
            </button>
          </div>
          <div class="team-footer-actions">
            <button class="btn btn crm-btn-secondary crm-btn-compact" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
            <button class="btn btn crm-btn-primary crm-btn-compact" type="submit" data-edit-save>
              <span class="team-save-dot" data-edit-dirty-dot hidden></span>
              <span data-edit-save-text data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></span>
              <span class="spinner-border spinner-border-sm" data-edit-save-spinner hidden></span>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- TEAM KNOWLEDGE ATTACH MODAL -->
<div class="modal fade" id="teamKnowledgeAttachModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="teams.attach_knowledge_title"><?= htmlspecialchars($t('teams.attach_knowledge_title', 'Прикрепить статью базы знаний'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="teamKnowledgeSearch" data-i18n="teams.attach_knowledge_search_label"><?= htmlspecialchars($t('teams.attach_knowledge_search_label', 'Поиск статьи'), ENT_QUOTES, 'UTF-8') ?></label>
          <input id="teamKnowledgeSearch" class="form-control" type="search" autocomplete="off" placeholder="<?= htmlspecialchars($t('teams.attach_knowledge_search_placeholder', 'Введите название статьи...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="teams.attach_knowledge_search_placeholder">
        </div>
        <div id="teamKnowledgeAttachResults"><div class="text-muted small" data-i18n="teams.attach_knowledge_loading"><?= htmlspecialchars($t('teams.attach_knowledge_loading', 'Загрузка статей...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button></div>
    </div>
  </div>
</div>

<script>
(function () {
  var editModalEl = document.getElementById('teamEditModal');
  var createModalEl = document.getElementById('teamCreateModal');
  if (!editModalEl && !createModalEl) return;

  function getApi() {
    return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
  }
  function t(key, fallback) {
    return window.CRM && window.CRM.i18n && typeof window.CRM.i18n.t === 'function'
      ? window.CRM.i18n.t(key, fallback)
      : fallback;
  }
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
  function notify(text, type) {
    try {
      if (window.CRM && window.CRM.br1 && typeof window.CRM.br1.notify === 'function') {
        return window.CRM.br1.notify(text, type);
      }
      if (typeof window.notify === 'function') return window.notify(text, type);
    } catch (e) {}
  }

  var attachModalEl = document.getElementById('teamKnowledgeAttachModal');
  var attachSearch = document.getElementById('teamKnowledgeSearch');
  var attachResults = document.getElementById('teamKnowledgeAttachResults');
  function getAttachModal() {
    return attachModalEl && window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getOrCreateInstance(attachModalEl) : null;
  }

  var attachMode = 'edit';
  var currentTeamId = '';
  var linkedIds = {};
  var searchTimer = null;
  var loadReqId = 0;

  var editKnowledgeList = document.getElementById('teamKnowledgeList');
  var editAttachBtn = document.getElementById('teamKnowledgeAttachBtn');
  var editKnowledgeLink = document.getElementById('teamKnowledgeLink');

  var createKnowledgeList = document.getElementById('teamCreateKnowledgeList');
  var createAttachBtn = document.getElementById('teamCreateKnowledgeAttachBtn');
  var createSelectedPages = [];

  function pageRow(p, detach) {
    var detachBtn = detach
      ? '<button type="button" class="btn btn-sm crm-btn-subtle" data-detach="' + esc(p.public_id) + '" data-link="' + esc(detach) + '" title="' + esc(t('teams.knowledge_detach', 'Открепить')) + '"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-xmark"></i></span></button>'
      : '';
    var openBtn = '<a class="btn btn-sm crm-btn-subtle" href="index.php?route=knowledge-page&amp;id=' + encodeURIComponent(p.public_id) + '"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span></a>';
    return '<div class="team-knowledge-row"><div class="team-knowledge-row-main"><span class="team-knowledge-row-icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span><span class="team-knowledge-row-title">' + esc(p.title || '') + '</span></div><div class="team-knowledge-row-actions">' + detachBtn + openBtn + '</div></div>';
  }

  function renderCreateList() {
    if (!createKnowledgeList) return;
    if (!createSelectedPages.length) {
      createKnowledgeList.innerHTML = '<div class="team-knowledge-empty"><p>' + esc(t('teams.knowledge_empty', 'Нет материалов команды')) + '</p></div>';
      return;
    }
    createKnowledgeList.innerHTML = createSelectedPages.map(function (p) {
      return '<div class="team-knowledge-row"><div class="team-knowledge-row-main"><span class="team-knowledge-row-icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span><span class="team-knowledge-row-title">' + esc(p.title || '') + '</span></div><div class="team-knowledge-row-actions"><button type="button" class="btn btn-sm crm-btn-subtle" data-create-remove="' + esc(p.public_id) + '" title="' + esc(t('teams.knowledge_detach', 'Открепить')) + '"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-xmark"></i></span></button></div></div>';
    }).join('');
    createKnowledgeList.querySelectorAll('[data-create-remove]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-create-remove');
        createSelectedPages = createSelectedPages.filter(function (p) { return p.public_id !== id; });
        renderCreateList();
      });
    });
  }

  function renderEditList(items) {
    if (!editKnowledgeList) return;
    if (!items.length) {
      editKnowledgeList.innerHTML = '<div class="team-knowledge-empty"><p>' + esc(t('teams.knowledge_empty', 'Нет материалов команды')) + '</p><p>' + esc(t('teams.knowledge_empty_hint', 'Добавьте страницы в базе знаний и привяжите их к команде.')) + '</p></div>';
      return;
    }
    editKnowledgeList.innerHTML = items.map(function (p) {
      return pageRow(p, String(p.link_public_id || ''));
    }).join('');
    editKnowledgeList.querySelectorAll('[data-detach]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        detachPage(btn.getAttribute('data-detach'), btn.getAttribute('data-link'));
      });
    });
  }

  async function loadEditPages() {
    var api = getApi();
    if (!api || !editKnowledgeList || !currentTeamId) return;
    editKnowledgeList.innerHTML = '<div class="d-flex align-items-center gap-2 text-muted small"><span class="spinner-border spinner-border-sm" role="status"></span>' + esc(t('page.loading', 'Загрузка...')) + '</div>';
    try {
      var envelope = await api.request('api/v1/knowledge/entities/team/' + encodeURIComponent(currentTeamId) + '/pages', { method: 'GET' });
      var items = envelope.data && envelope.data.items || [];
      renderEditList(items);
    } catch (e) {
      editKnowledgeList.innerHTML = '<div class="text-muted small">—</div>';
    }
  }

  async function detachPage(pageId, linkId) {
    var api = getApi();
    if (!api || !pageId || !linkId) return;
    if (!window.confirm(t('teams.knowledge_detach_confirm', 'Открепить эту страницу от команды?'))) return;
    try {
      await api.request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/links/' + encodeURIComponent(linkId), { method: 'DELETE' });
      loadEditPages();
    } catch (e) {
      notify(t('teams.attach_knowledge_error', 'Не удалось выполнить операцию'), 'error');
    }
  }

  function setAttachMessage(message, className) {
    if (attachResults) attachResults.innerHTML = '<div class="text-muted small ' + (className || '') + '">' + esc(message) + '</div>';
  }

  async function loadLinkedIds() {
    linkedIds = {};
    var api = getApi();
    if (!api || !currentTeamId) return;
    try {
      var envelope = await api.request('api/v1/knowledge/entities/team/' + encodeURIComponent(currentTeamId) + '/pages', { method: 'GET' });
      var items = envelope.data && envelope.data.items || [];
      items.forEach(function (item) {
        var id = String(item && item.public_id || '').trim();
        if (id) linkedIds[id] = true;
      });
    } catch (e) {}
  }

  async function loadArticles() {
    var api = getApi();
    if (!api || !attachResults) return;
    var requestId = ++loadReqId;
    var query = String(attachSearch && attachSearch.value || '').trim();
    setAttachMessage(t('teams.attach_knowledge_loading', 'Загрузка статей...'));
    try {
      var envelope = await api.request('api/v1/knowledge/pages', {
        method: 'GET',
        query: { limit: 50, q: query, min_access: 'view' }
      });
      if (requestId !== loadReqId) return;
      var items = envelope.data && envelope.data.items || [];
      var available = items.filter(function (item) {
        if (!item || !item.public_id) return false;
        var id = String(item.public_id);
        if (attachMode === 'edit') return !linkedIds[id];
        return !createSelectedPages.some(function (p) { return p.public_id === id; });
      });
      if (!available.length) {
        setAttachMessage(t('teams.attach_knowledge_empty', 'Статьи не найдены'));
        return;
      }
      attachResults.innerHTML = available.map(function (item) {
        var id = encodeURIComponent(String(item.public_id));
        var title = esc(item.title || '');
        var meta = [item.space_title, item.status].filter(Boolean).map(esc).join(' · ');
        return '<button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-3" data-attach-page-id="' + id + '"><span class="text-start"><strong class="d-block">' + title + '</strong>' + (meta ? '<span class="small text-muted">' + meta + '</span>' : '') + '</span><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-link"></i></span></button>';
      }).join('');
    } catch (e) {
      setAttachMessage(t('teams.attach_knowledge_error', 'Не удалось выполнить операцию'), 'text-danger');
    }
  }

  async function openAttachModal(mode, teamId) {
    attachMode = mode;
    currentTeamId = teamId || '';
    if (attachSearch) attachSearch.value = '';
    if (attachResults) attachResults.innerHTML = '';
    if (mode === 'edit' && teamId) await loadLinkedIds();
    var am = getAttachModal();
    if (am) am.show();
    loadReqId++;
    loadArticles();
  }

  if (attachResults) {
    attachResults.addEventListener('click', async function (event) {
      var btn = event.target.closest('[data-attach-page-id]');
      if (!btn) return;
      var pageId = decodeURIComponent(btn.getAttribute('data-attach-page-id') || '');
      btn.disabled = true;
      try {
        if (attachMode === 'create') {
          if (!createSelectedPages.some(function (p) { return p.public_id === pageId; })) {
            var titleEl = btn.querySelector('strong');
            createSelectedPages.push({ public_id: pageId, title: titleEl ? titleEl.textContent : pageId });
            renderCreateList();
          }
        } else {
          await linkPageToTeam(pageId, currentTeamId);
          loadEditPages();
        }
        var am2 = getAttachModal();
        if (am2) am2.hide();
      } catch (e) {
        btn.disabled = false;
        setAttachMessage(t('teams.attach_knowledge_error', 'Не удалось выполнить операцию'), 'text-danger');
      }
    });
  }

  if (attachSearch) {
    attachSearch.addEventListener('input', function () {
      if (searchTimer) window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(loadArticles, 250);
    });
  }

  async function linkPageToTeam(pageId, teamId) {
    var api = getApi();
    if (!api) throw new Error('no api');
    await api.request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/links', {
      method: 'POST',
      body: { entity_type: 'team', entity_public_id: teamId, relation_type: 'related' },
      idempotent: true
    });
  }

  if (editModalEl) {
    editModalEl.addEventListener('show.bs.modal', function () {
      var teamIdInput = document.querySelector('#teamEditForm input[name="public_id"]');
      var teamId = teamIdInput ? teamIdInput.value : '';
      currentTeamId = teamId || '';
      if (editKnowledgeLink) {
        editKnowledgeLink.href = teamId
          ? 'index.php?route=knowledge&entity_type=team&entity_public_id=' + encodeURIComponent(teamId)
          : 'index.php?route=knowledge';
      }
      if (teamId) loadEditPages();
      else if (editKnowledgeList) editKnowledgeList.innerHTML = '<div class="team-knowledge-empty"><p>' + esc(t('teams.knowledge_empty', 'Нет материалов команды')) + '</p></div>';
    });
  }

  if (editAttachBtn) {
    editAttachBtn.addEventListener('click', function () {
      if (!currentTeamId) return;
      openAttachModal('edit', currentTeamId);
    });
  }

  if (createModalEl) {
    createModalEl.addEventListener('show.bs.modal', function () {
      createSelectedPages = [];
      renderCreateList();
    });
  }

  if (createAttachBtn) {
    createAttachBtn.addEventListener('click', function () {
      openAttachModal('create', '');
    });
  }

  window.__teamKnowledge = {
    attachPendingPages: async function (teamId) {
      if (!teamId || !createSelectedPages.length) return;
      var api = getApi();
      if (!api) return;
      var pages = createSelectedPages.slice();
      for (var i = 0; i < pages.length; i += 1) {
        try {
          await api.request('api/v1/knowledge/pages/' + encodeURIComponent(pages[i].public_id) + '/links', {
            method: 'POST',
            body: { entity_type: 'team', entity_public_id: teamId, relation_type: 'related' },
            idempotent: true
          });
        } catch (e) {}
      }
      createSelectedPages = [];
    }
  };
})();
</script>


</body>
</html>