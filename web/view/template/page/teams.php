<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Команды и отделы'; ?>
<body data-page="teams" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-teams-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=dashboard">Главная</a></li></ol><h1 class="crm-page-title">Команды и отделы</h1><p class="crm-subtitle">Иерархическая структура команд и отделов.</p></div><div class="crm-page-actions"><button class="btn crm-btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#teamCreateModal"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span> Создать</button></div></div>

<div class="crm-card crm-section-card crm-filters-card mb-3">
  <div class="row g-2 align-items-end crm-teams-filters">
    <div class="col-lg-5 col-md-6"><label class="form-label" for="teamsFilterSearch">Поиск</label><input id="teamsFilterSearch" class="form-control" placeholder="Название команды"></div>
    <div class="col-lg-3 col-md-4"><label class="form-label" for="teamsFilterType">Тип</label><select id="teamsFilterType" class="form-select"><option value="">Все типы</option><option value="department">Отдел</option><option value="team">Команда</option></select></div>
    <div class="col-lg-auto col-md-2"><button class="btn crm-btn-muted crm-teams-reset" type="button" id="teamsFilterReset">Сбросить</button></div>
  </div>
</div>

<div class="crm-card crm-section-card p-0"><div class="crm-teams-tree-container"><div class="crm-tree-header"><span class="crm-tree-col-name">Название</span><span class="crm-tree-col-type">Тип</span><span class="crm-tree-col-manager">Менеджер</span><span class="crm-tree-col-actions">Действия</span></div><div id="teamsTree" class="crm-tree" role="tree"><div class="crm-tree-loading">Загрузка...</div></div></div></div>
</main></div></div>

<!-- CREATE MODAL -->
<div class="modal fade" id="teamCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-team-edit modal-dialog-centered">
    <div class="modal-content">
      <div class="team-modal-header">
        <div class="team-modal-header-left">
          <div class="team-modal-icon"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-users"></i></span></div>
          <div class="team-modal-title-group">
            <h5 class="team-modal-title">Создать команду</h5>
            <div class="team-modal-subtitle">Заполните информацию и добавьте участников</div>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>

      <form id="teamCreateForm" autocomplete="off">
        <div class="modal-body">
          <div class="col-12 d-none" data-form-error-summary></div>
          <div class="team-modal-grid">
            <div class="team-modal-left">
              <div class="team-section-card">
                <h6 class="team-section-title">Основная информация</h6>
                <div class="team-field-group">
                  <label for="teamCreateTitle" class="team-label">Название <span class="team-required">*</span></label>
                  <input id="teamCreateTitle" class="form-control team-input" name="title" maxlength="255" required placeholder="Например: Отдел разработки">
                </div>
                <div class="team-field-row">
                  <div class="team-field-group team-field-half">
                    <label for="teamCreateCode" class="team-label">Код</label>
                    <input id="teamCreateCode" class="form-control team-input" name="code" maxlength="64" placeholder="dev-frontend">
                  </div>
                  <div class="team-field-group team-field-half">
                    <label class="team-label">Тип</label>
                    <div class="team-segmented" data-field="team_type">
                      <button type="button" class="team-segmented-btn is-active" data-value="team">Команда</button>
                      <button type="button" class="team-segmented-btn" data-value="department">Отдел</button>
                    </div>
                    <input type="hidden" name="team_type" value="team">
                  </div>
                </div>
                <div class="team-field-group">
                  <label for="teamCreateParent" class="team-label">Родительская команда</label>
                  <select id="teamCreateParent" class="form-select team-input" name="parent_public_id"><option value="">Без родителя</option></select>
                </div>
              </div>

              <div class="team-section-card">
                <h6 class="team-section-title">Управление</h6>
                <div class="team-field-group">
                  <label for="teamCreateManager" class="team-label">Менеджер команды</label>
                  <select id="teamCreateManager" class="form-select team-input" name="manager_user_public_id"><option value="">По умолчанию текущий пользователь</option></select>
                </div>
              </div>
            </div>

            <div class="team-modal-right">
              <div class="team-participant-panel">
                <div class="team-participant-toolbar">
                  <h6 class="team-participant-title">Участники <span class="team-participant-count" data-create-count>0</span></h6>
                </div>
                <div class="team-participant-search-wrap">
                  <span class="crm-icon team-search-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                  <input type="text" class="team-search-input" data-create-search placeholder="Найти сотрудника...">
                  <div class="team-search-dropdown" data-create-search-results hidden></div>
                </div>
                <div class="team-participant-list" data-create-participant-list role="listbox"></div>
                <div class="team-empty-state" data-create-empty>
                  <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-user-plus"></i></span>
                  <p>Добавьте участников</p>
                  <span class="team-empty-hint">Используйте поиск для быстрого добавления</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="team-modal-footer">
          <div class="team-footer-spacer"></div>
          <div class="team-footer-actions">
            <button class="btn team-btn-cancel" type="button" data-bs-dismiss="modal">Отмена</button>
            <button class="btn team-btn-primary" type="submit" data-create-save>
              <span data-create-save-text>Создать</span>
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
  <div class="modal-dialog modal-team-edit modal-dialog-centered">
    <div class="modal-content">
      <div class="team-modal-header">
        <div class="team-modal-header-left">
          <div class="team-modal-icon"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-users"></i></span></div>
          <div class="team-modal-title-group">
            <h5 class="team-modal-title">Редактирование команды</h5>
            <div class="team-modal-meta">
              <span class="team-modal-meta-id" data-edit-meta-id></span>
              <span class="team-modal-meta-sep"></span>
              <span class="team-modal-meta-created" data-edit-meta-created></span>
              <span class="team-modal-meta-sep"></span>
              <span class="team-modal-meta-count" data-edit-meta-count></span>
            </div>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>

      <form id="teamEditForm" autocomplete="off">
        <input type="hidden" name="public_id">
        <div class="modal-body">
          <div class="col-12 d-none" data-form-error-summary></div>
          <div class="team-modal-grid">
            <div class="team-modal-left">
              <div class="team-section-card">
                <h6 class="team-section-title">Основная информация</h6>
                <div class="team-field-group">
                  <label for="teamEditTitle" class="team-label">Название <span class="team-required">*</span></label>
                  <input id="teamEditTitle" class="form-control team-input" name="title" maxlength="255" required>
                </div>
                <div class="team-field-row">
                  <div class="team-field-group team-field-half">
                    <label for="teamEditCode" class="team-label">Код</label>
                    <input id="teamEditCode" class="form-control team-input" name="code" maxlength="64">
                  </div>
                  <div class="team-field-group team-field-half">
                    <label class="team-label">Тип</label>
                    <div class="team-segmented" data-field="team_type">
                      <button type="button" class="team-segmented-btn" data-value="team">Команда</button>
                      <button type="button" class="team-segmented-btn" data-value="department">Отдел</button>
                    </div>
                    <input type="hidden" name="team_type" value="team">
                  </div>
                </div>
                <div class="team-field-group">
                  <label for="teamEditParent" class="team-label">Родительская команда</label>
                  <select id="teamEditParent" class="form-select team-input" name="parent_public_id"><option value="">Без родителя</option></select>
                </div>
              </div>

              <div class="team-section-card">
                <h6 class="team-section-title">Управление</h6>
                <div class="team-field-group">
                  <label for="teamEditManager" class="team-label">Менеджер команды</label>
                  <select id="teamEditManager" class="form-select team-input" name="manager_user_public_id"><option value="">Не назначен</option></select>
                </div>
              </div>
            </div>

            <div class="team-modal-right">
              <div class="team-participant-panel">
                <div class="team-participant-toolbar">
                  <h6 class="team-participant-title">Участники <span class="team-participant-count" data-edit-count>0</span></h6>
                </div>
                <div class="team-participant-search-wrap">
                  <span class="crm-icon team-search-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                  <input type="text" class="team-search-input" data-edit-search placeholder="Найти сотрудника...">
                  <div class="team-search-dropdown" data-edit-search-results hidden></div>
                </div>
                <div class="team-participant-list" data-edit-participant-list role="listbox"></div>
                <div class="team-empty-state" data-edit-empty hidden>
                  <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-user-plus"></i></span>
                  <p>Нет участников</p>
                  <span class="team-empty-hint">Используйте поиск для добавления</span>
                </div>
              </div>
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
            <button class="btn team-btn-cancel" type="button" data-bs-dismiss="modal">Отмена</button>
            <button class="btn team-btn-primary" type="submit" data-edit-save>
              <span class="team-save-dot" data-edit-dirty-dot hidden></span>
              <span data-edit-save-text>Сохранить</span>
              <span class="spinner-border spinner-border-sm" data-edit-save-spinner hidden></span>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
