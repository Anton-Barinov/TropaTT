<?php declare(strict_types=1); ?>
<?php $title = $t('cycles.page_title', 'Циклы'); ?>
<body data-page="cycles" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-cycles-page">
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= htmlspecialchars($t('cycles.page_title', 'Циклы'), ENT_QUOTES, 'UTF-8') ?><span class="crm-badge crm-badge-secondary-outline ms-2 small fw-normal"><?= htmlspecialchars($t('cycles.subtitle_badge', 'Спринты / Итерации'), ENT_QUOTES, 'UTF-8') ?></span></h4>
    <div>
      <button class="btn btn-sm crm-btn-primary" onclick="window.openCycleModal(null)">
        <i class="fa-solid fa-plus"></i> <?= htmlspecialchars($t('cycles.btn_create', 'Создать цикл'), ENT_QUOTES, 'UTF-8') ?>
      </button>
    </div>
  </div>

  <section class="crm-cycle-command-center d-none mb-3" id="cycleCommandCenter" aria-live="polite"></section>

  <!-- Filters -->
  <div class="crm-cycles-filterbar mb-3" aria-label="<?= htmlspecialchars($t('cycles.filters_aria', 'Фильтры циклов'), ENT_QUOTES, 'UTF-8') ?>">
    <div class="crm-cycles-filter-item crm-cycles-filter-project">
      <select class="form-select" id="cycleProjectFilter">
        <option value=""><?= htmlspecialchars($t('cycles.filter_all_projects', 'Все проекты'), ENT_QUOTES, 'UTF-8') ?></option>
      </select>
    </div>
    <div class="crm-cycles-filter-item crm-cycles-filter-status">
      <select class="form-select" id="cycleStatusFilter">
        <option value=""><?= htmlspecialchars($t('cycles.filter_all_statuses', 'Все статусы'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="planned"><?= htmlspecialchars($t('cycles.filter_planned', 'Запланированные'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="active"><?= htmlspecialchars($t('cycles.filter_active', 'Активные'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="completed"><?= htmlspecialchars($t('cycles.filter_completed', 'Завершённые'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="archived"><?= htmlspecialchars($t('cycles.filter_archived', 'Архивные'), ENT_QUOTES, 'UTF-8') ?></option>
      </select>
    </div>
    <div class="crm-cycles-filter-item crm-cycles-filter-search">
      <div class="crm-cycles-search" role="search">
        <label class="visually-hidden" for="cycleSearchInput"><?= htmlspecialchars($t('cycles.search_label', 'Поиск циклов'), ENT_QUOTES, 'UTF-8') ?></label>
        <i class="fa-solid fa-search crm-cycles-search-icon" aria-hidden="true"></i>
        <input type="search" class="form-control crm-cycles-search-input" id="cycleSearchInput" placeholder="<?= htmlspecialchars($t('cycles.search_placeholder', 'Поиск циклов...'), ENT_QUOTES, 'UTF-8') ?>">
        <button class="crm-cycles-search-clear d-none" type="button" id="cycleSearchClearBtn" aria-label="<?= htmlspecialchars($t('cycles.search_clear', 'Очистить поиск'), ENT_QUOTES, 'UTF-8') ?>">
          <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <button class="crm-cycles-search-submit" type="button" id="cycleSearchSubmitBtn" aria-label="<?= htmlspecialchars($t('cycles.search_submit', 'Найти циклы'), ENT_QUOTES, 'UTF-8') ?>">
          <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- Loading State -->
  <div id="cycleLoadingState" class="text-center py-5 d-none">
    <div class="spinner-border text-muted" role="status"><span class="visually-hidden"><?= htmlspecialchars($t('cycles.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></span></div>
    <div class="text-muted small mt-2"><?= htmlspecialchars($t('cycles.loading_cycles', 'Загрузка циклов...'), ENT_QUOTES, 'UTF-8') ?></div>
  </div>

  <!-- Empty State -->
  <div id="cycleEmptyState" class="text-center py-5 d-none">
    <i class="fa-solid fa-rotate fa-3x text-muted mb-3"></i>
    <h5 class="text-muted"><?= htmlspecialchars($t('cycles.empty_title', 'Нет циклов'), ENT_QUOTES, 'UTF-8') ?></h5>
    <p class="text-muted small"><?= htmlspecialchars($t('cycles.empty_text', 'Создайте первый цикл для планирования задач.'), ENT_QUOTES, 'UTF-8') ?></p>
    <button class="btn btn-sm crm-btn-primary" onclick="window.openCycleModal(null)">
      <i class="fa-solid fa-plus"></i> <?= htmlspecialchars($t('cycles.btn_create', 'Создать цикл'), ENT_QUOTES, 'UTF-8') ?>
    </button>
  </div>

  <!-- Error State -->
  <div id="cycleErrorState" class="text-center py-5 d-none">
    <i class="fa-solid fa-triangle-exclamation fa-3x text-danger mb-3"></i>
    <h5 class="text-danger"><?= htmlspecialchars($t('cycles.error_title', 'Ошибка загрузки'), ENT_QUOTES, 'UTF-8') ?></h5>
    <p id="cycleErrorText" class="text-muted small"><?= htmlspecialchars($t('cycles.error_text', 'Не удалось загрузить список циклов.'), ENT_QUOTES, 'UTF-8') ?></p>
    <button class="btn btn-sm crm-btn-secondary" onclick="window.loadWorkCycles(1)"><?= htmlspecialchars($t('cycles.btn_retry', 'Повторить'), ENT_QUOTES, 'UTF-8') ?></button>
  </div>

  <!-- Cycle List -->
  <div id="cycleListContainer">
    <div id="cycleList" class="crm-card-list"></div>
    <div id="cyclePagination" class="d-flex justify-content-center mt-3"></div>
  </div>
</div>

<!-- Cycle Create/Edit Modal -->
<div class="modal fade" id="cycleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cycleModalTitle"><?= htmlspecialchars($t('cycles.modal_create_title', 'Создать цикл'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('cycles.close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button>
      </div>
      <div class="modal-body">
        <div id="cycleModalAlert" class="alert alert-danger d-none"></div>
        <form id="cycleForm" onsubmit="return false;">
          <input type="hidden" id="cycleFormPublicId" value="">
          <input type="hidden" id="cycleFormRowVersion" value="">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars($t('cycles.form_label_name', 'Название'), ENT_QUOTES, 'UTF-8') ?> <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="cycleFormTitle" required maxlength="255">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars($t('cycles.form_label_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?> <span class="text-danger">*</span></label>
              <select class="form-select" id="cycleFormProject"></select>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars($t('cycles.form_label_start', 'Дата начала'), ENT_QUOTES, 'UTF-8') ?></label>
              <input type="datetime-local" class="form-control" id="cycleFormStartAt">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars($t('cycles.form_label_end', 'Дата окончания'), ENT_QUOTES, 'UTF-8') ?></label>
              <input type="datetime-local" class="form-control" id="cycleFormEndAt">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars($t('cycles.form_label_owner', 'Владелец'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="cycleFormOwner"></select>
            </div>
            <div class="col-md-6" id="cycleFormStatusField">
              <label class="form-label"><?= htmlspecialchars($t('cycles.form_label_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="cycleFormStatus">
                <option value="planned"><?= htmlspecialchars($t('cycles.form_status_planned', 'Запланирован'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="active"><?= htmlspecialchars($t('cycles.form_status_active', 'Активный'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label"><?= htmlspecialchars($t('cycles.form_label_goal', 'Цель цикла'), ENT_QUOTES, 'UTF-8') ?></label>
              <textarea class="form-control" id="cycleFormGoal" rows="2" maxlength="65535" data-crm-visual-editor="1" data-richtext-off="1"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label"><?= htmlspecialchars($t('cycles.form_label_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label>
              <textarea class="form-control" id="cycleFormDescription" rows="3" maxlength="65535" data-crm-visual-editor="1" data-richtext-off="1"></textarea>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm crm-btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($t('cycles.btn_cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm crm-btn-primary" id="cycleFormSubmit" onclick="window.saveWorkCycle()"><?= htmlspecialchars($t('cycles.btn_submit_create', 'Создать'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Cycle Detail Modal -->
<div class="modal fade" id="cycleDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cycleDetailTitle"><?= htmlspecialchars($t('cycles.modal_detail_title', 'Цикл'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('cycles.close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button>
      </div>
      <div class="modal-body">
        <ul class="nav nav-tabs mb-3" id="cycleDetailTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="cycleOverviewTab" data-bs-toggle="tab" data-bs-target="#cycleOverviewPane" type="button" role="tab"><?= htmlspecialchars($t('cycles.tab_overview', 'Обзор'), ENT_QUOTES, 'UTF-8') ?></button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="cycleTasksTab" data-bs-toggle="tab" data-bs-target="#cycleTasksPane" type="button" role="tab"><?= htmlspecialchars($t('cycles.tab_tasks', 'Задачи'), ENT_QUOTES, 'UTF-8') ?></button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="cycleBoardTab" data-bs-toggle="tab" data-bs-target="#cycleBoardPane" type="button" role="tab"><?= htmlspecialchars($t('cycles.tab_board', 'Доска'), ENT_QUOTES, 'UTF-8') ?></button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="cycleSummaryTab" data-bs-toggle="tab" data-bs-target="#cycleSummaryPane" type="button" role="tab"><?= htmlspecialchars($t('cycles.tab_statistics', 'Статистика'), ENT_QUOTES, 'UTF-8') ?></button>
          </li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade show active" id="cycleOverviewPane" role="tabpanel">
            <div id="cycleOverviewContent"></div>
          </div>
          <div class="tab-pane fade" id="cycleTasksPane" role="tabpanel">
            <div id="cycleTasksContent"></div>
          </div>
          <div class="tab-pane fade" id="cycleBoardPane" role="tabpanel">
            <div id="cycleBoardContent"></div>
          </div>
          <div class="tab-pane fade" id="cycleSummaryPane" role="tabpanel">
            <div id="cycleSummaryContent"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Tasks Modal -->
<div class="modal fade" id="addTasksModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= htmlspecialchars($t('cycles.modal_add_tasks_title', 'Добавить задачи в цикл'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('cycles.close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <input type="text" class="form-control" id="addTaskSearchInput" placeholder="<?= htmlspecialchars($t('cycles.search_tasks_placeholder', 'Поиск задач по названию или ID...'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div id="addTaskResults" class="crm-card-list" style="max-height:400px;overflow-y:auto;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm crm-btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($t('cycles.btn_close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm crm-btn-primary" id="addTasksConfirmBtn" onclick="window.confirmAddTasks()"><?= htmlspecialchars($t('cycles.btn_add_selected', 'Добавить выбранные'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Complete Cycle Modal -->
<div class="modal fade" id="completeCycleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= htmlspecialchars($t('cycles.modal_complete_title', 'Завершить цикл'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('cycles.close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button>
      </div>
      <div class="modal-body">
        <div id="completeCycleSummary"></div>
        <div class="mt-3">
          <label class="form-label"><?= htmlspecialchars($t('cycles.label_unfinished_action', 'Действие с незавершёнными задачами:'), ENT_QUOTES, 'UTF-8') ?></label>
          <select class="form-select" id="completeUnfinishedAction">
            <option value="leave"><?= htmlspecialchars($t('cycles.action_leave', 'Оставить в цикле'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="move"><?= htmlspecialchars($t('cycles.action_move', 'Перенести в другой цикл'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="remove"><?= htmlspecialchars($t('cycles.action_remove', 'Убрать из цикла'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
        </div>
        <div class="mt-2 d-none" id="completeTargetCycleContainer">
          <label class="form-label"><?= htmlspecialchars($t('cycles.label_target_cycle', 'Целевой цикл:'), ENT_QUOTES, 'UTF-8') ?></label>
          <select class="form-select" id="completeTargetCycle"></select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm crm-btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($t('cycles.btn_cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm btn-warning" id="completeCycleConfirmBtn" onclick="window.confirmCompleteCycle()"><?= htmlspecialchars($t('cycles.btn_complete', 'Завершить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<?php
$cyclesAssetsVersion = isset($assetsVersion) ? (string)$assetsVersion : '';
if ($cyclesAssetsVersion === '') {
  $cyclesAssetPath = dirname(__DIR__, 3) . '/assets/js/work-cycles.js';
  $cyclesAssetsVersion = (string)(@filemtime($cyclesAssetPath) ?: time());
}
?>
<script src="assets/js/work-cycles.js?v=<?= urlencode($cyclesAssetsVersion) ?>"></script>
<style>
.crm-cycle-card {
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 14px 16px;
  margin-bottom: 12px;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}
.crm-cycle-card:hover {
  border-color: rgba(11, 122, 98, 0.28);
  background: #fbfefd;
}
.crm-cycle-card-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(150px, 220px);
  gap: 16px;
}
.crm-cycle-command-center {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 16px;
  border: 1px solid #dbe8e4;
  border-radius: 8px;
  background: #f8fcfb;
}
.crm-cycle-command-text {
  display: grid;
  gap: 4px;
  min-width: 0;
}
.crm-cycle-command-text span {
  color: #58716b;
  font-size: 13px;
}
.crm-cycle-command-metrics {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
}
.crm-cycle-command-metric {
  display: grid;
  min-width: 92px;
  padding: 8px 10px;
  border: 1px solid #d7e4e0;
  border-radius: 8px;
  background: #fff;
  color: #13201d;
  text-align: left;
  text-decoration: none;
}
button.crm-cycle-command-metric {
  cursor: pointer;
}
button.crm-cycle-command-metric:hover {
  border-color: rgba(11, 122, 98, 0.34);
}
.crm-cycle-command-metric strong {
  font-size: 18px;
  line-height: 1;
}
.crm-cycle-command-metric span {
  color: #6d7f7a;
  font-size: 12px;
}
.crm-cycle-pagination {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px;
  border: 1px solid #dbe8e4;
  border-radius: 8px;
  background: #fff;
}
.crm-cycle-page-btn {
  min-width: 34px;
  height: 34px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: #3f5550;
  font-weight: 700;
}
.crm-cycle-page-btn:hover {
  background: #eef8f5;
  color: #0b7a62;
}
.crm-cycle-page-btn.is-active {
  background: #0b7a62;
  color: #fff;
}
.crm-cycle-title {
  appearance: none;
  border: 0;
  background: transparent;
  padding: 0;
  color: #13201d;
  font: inherit;
  font-weight: 700;
  text-align: left;
}
.crm-cycle-title:hover,
.crm-cycle-title:focus {
  color: #0b7a62;
  outline: none;
}
.crm-cycle-progress-panel {
  min-width: 150px;
  align-self: center;
  text-align: right;
}
.crm-cycle-progress {
  height: 6px;
  border-radius: 3px;
  background: #e9ecef;
}
.crm-cycle-progress-bar {
  height: 100%;
  border-radius: 3px;
  background: #0b7a62;
  transition: width 0.3s ease;
}
.crm-cycle-next-step {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  margin-top: 8px;
  color: #58716b;
  font-size: 13px;
}
.crm-cycle-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 500;
}
.crm-cycle-badge-planned { background: #e3f2fd; color: #1565c0; }
.crm-cycle-badge-active { background: #e8f5e9; color: #2e7d32; }
.crm-cycle-badge-completed { background: #f3e5f5; color: #7b1fa2; }
.crm-cycle-badge-archived { background: #f5f5f5; color: #616161; }
.crm-cycle-detail-callout,
.crm-cycle-board-link {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 14px 16px;
  border: 1px solid #dbe8e4;
  border-radius: 8px;
  background: #f8fcfb;
}
@media (max-width: 767.98px) {
  .crm-cycle-card-grid,
  .crm-cycle-command-center,
  .crm-cycle-detail-callout,
  .crm-cycle-board-link {
    display: block;
  }
  .crm-cycle-command-metrics {
    justify-content: flex-start;
    margin-top: 12px;
  }
  .crm-cycle-progress-panel {
    margin-top: 12px;
    text-align: left;
  }
  .crm-cycle-detail-callout .d-flex,
  .crm-cycle-board-link .btn {
    margin-top: 12px;
  }
}
</style>
</main></div></div>
