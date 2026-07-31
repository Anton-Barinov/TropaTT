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
            <button class="btn team-btn-cancel" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
            <button class="btn team-btn-primary" type="submit" data-create-save>
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

          <div class="mt-3 pt-3 border-top" id="teamKnowledgeSection">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <h6 class="mb-0 d-flex align-items-center gap-2">
                <span class="crm-icon text-muted" aria-hidden="true"><i class="fa-solid fa-book-open"></i></span>
                <?= htmlspecialchars($t('teams.section_knowledge', 'Материалы команды'), ENT_QUOTES, 'UTF-8') ?>
              </h6>
              <a class="btn btn-sm crm-btn-subtle" href="index.php?route=knowledge" id="teamKnowledgeLink" data-i18n="teams.btn_knowledge">
                <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>
                <?= htmlspecialchars($t('teams.btn_knowledge', 'Перейти в базу знаний'), ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
            <div class="crm-section-card p-3" id="teamKnowledgeList">
              <div class="text-muted small">—</div>
            </div>
          </div>

        </div>

        <div class="team-modal-footer">
          <div class="team-footer-danger">
            <button class="btn team-btn-danger" type="button" data-team-delete>
              <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-trash-can"></i></span>
            </button>
          </div>
          <div class="team-footer-actions">
            <button class="btn team-btn-cancel" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
            <button class="btn team-btn-primary" type="submit" data-edit-save>
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

<script>
(function () {
  var editModal = document.getElementById('teamEditModal');
  if (!editModal) return;
  var knowledgeSection = document.getElementById('teamKnowledgeSection');
  var knowledgeList = document.getElementById('teamKnowledgeList');
  if (!knowledgeSection || !knowledgeList) return;
  editModal.addEventListener('show.bs.modal', function () {
    var teamIdInput = document.querySelector('#teamEditForm input[name=\"public_id\"]');
    var teamId = teamIdInput ? teamIdInput.value : '';
    if (!teamId) { knowledgeList.innerHTML = '<div class=\"text-muted small\">—</div>'; return; }
    var link = document.getElementById('teamKnowledgeLink');
    if (link) link.href = 'index.php?route=knowledge&entity_type=team&entity_public_id=' + encodeURIComponent(teamId);
    knowledgeList.innerHTML = '<div class=\"d-flex align-items-center gap-2 text-muted small\"><span class=\"spinner-border spinner-border-sm\" role=\"status\"></span><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>';
    (async function () {
      try {
        var api = window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
        if (!api) return;
        var envelope = await api.request('api/v1/knowledge/entities/team/' + encodeURIComponent(teamId) + '/pages', { method: 'GET' });
        var items = envelope.data && envelope.data.items || [];
        if (!items.length) {
          knowledgeList.innerHTML = '<div class=\"text-center py-3\"><div class=\"crm-icon mb-2\" style=\"font-size:1.5rem;opacity:0.35\" aria-hidden=\"true\"><i class=\"fa-solid fa-book-open\"></i></div><p class=\"text-muted small mb-0\"><?= htmlspecialchars($t('teams.knowledge_empty', 'Нет материалов команды'), ENT_QUOTES, 'UTF-8') ?></p><p class=\"text-muted small mb-0\"><?= htmlspecialchars($t('teams.knowledge_empty_hint', 'Добавьте страницы в базе знаний и привяжите их к команде.'), ENT_QUOTES, 'UTF-8') ?></p></div>';
        } else {
          knowledgeList.innerHTML = items.map(function (p) {
            return '<div class=\"team-section-card d-flex align-items-center justify-content-between\" style=\"padding:10px 14px;margin-bottom:6px\"><div class=\"d-flex align-items-center gap-2\" style=\"min-width:0\"><span class=\"crm-icon\" style=\"color:var(--crm-primary);flex-shrink:0;font-size:0.9rem\" aria-hidden=\"true\"><i class=\"fa-solid fa-file-lines\"></i></span><span class=\"text-truncate\" style=\"font-size:13px\">' + (function esc(v) { return String(v == null ? '' : v).replace(/[&<>\\\"']/g, function(ch) { var m = {'&':'&amp;','<':'&lt;','>':'&gt;','\\\"':'&quot;'}; m["'"]='&#039;'; return m[ch] || ch; }); })(p.title || '') + '</span></div><a class=\"btn btn-sm crm-btn-subtle\" href=\"index.php?route=knowledge-page&amp;id=' + encodeURIComponent(p.public_id) + '\" style=\"flex-shrink:0;padding:4px 8px\"><span class=\"crm-icon\" aria-hidden=\"true\"><i class=\"fa-solid fa-arrow-right\"></i></span></a></div>';
          }).join('');
        }
      } catch (e) {
        knowledgeList.innerHTML = '<div class=\"text-muted small\">—</div>';
      }
    })();
  });
})();
</script>
</body>
</html>