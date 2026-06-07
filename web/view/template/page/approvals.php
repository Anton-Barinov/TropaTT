<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Согласования'; ?>
<body data-page="approvals" data-protected="1">
<div class="crm-app">
  <aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
  <div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
  <main class="crm-content crm-automation-page">
    <div class="crm-page-head">
      <div>
        <ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin">Админка</a></li><li class="breadcrumb-item active">Согласования</li></ol>
        <h1 class="crm-page-title">Согласования</h1>
        <p class="crm-subtitle">Запросы на утверждение задач и проектов — без переписок, без потерянных решений.</p>
      </div>
      <div class="d-flex gap-2">
        <button id="approvalsRefreshBtn" class="btn crm-btn-secondary" type="button"><i class="fa-solid fa-rotate me-1" aria-hidden="true"></i>Обновить</button>
        <button id="approvalsCreateBtn" class="btn crm-btn-primary" type="button"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Создать запрос</button>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-file-signature" aria-hidden="true"></i></span><strong>Запрос</strong><p class="mb-0">Сотрудник выбирает задачу или проект, назначает одного или нескольких согласующих. CRM сохраняет запрос и уведомляет участников.</p></div></div>
      <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span><strong>Решение</strong><p class="mb-0">Каждый согласующий видит запрос в своём интерфейсе. Одобрить или отклонить — в один клик. Результат фиксируется с датой и комментарием.</p></div></div>
      <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span><strong>Контроль</strong><p class="mb-0">Доступ ограничен ролями. Аудит решений сохраняется в истории. Никто не сможет сказать «я не видел».</p></div></div>
    </div>

    <div class="row g-3">
      <div class="col-12">
        <div class="crm-card crm-section-card crm-automation-list-toolbar">
          <div class="crm-section-head">
            <div><h2 class="h6 mb-0">Запросы</h2><div class="crm-section-note" id="approvalsCountBadge"></div></div>
            <div class="d-flex gap-2 align-items-center">
              <div class="btn-group btn-group-sm" role="group" id="approvalsStatusFilter">
                <button type="button" class="btn crm-btn-secondary active" data-approval-filter="all">Все</button>
                <button type="button" class="btn crm-btn-secondary" data-approval-filter="pending">Ожидают</button>
                <button type="button" class="btn crm-btn-secondary" data-approval-filter="approved">Одобрено</button>
                <button type="button" class="btn crm-btn-secondary" data-approval-filter="rejected">Отклонено</button>
              </div>
              <input type="search" id="approvalsSearchInput" class="form-control form-control-sm" placeholder="Поиск..." style="max-width:200px">
            </div>
          </div>
        </div>
        <div class="crm-card crm-section-card p-0 table-responsive crm-automation-table-card">
          <table class="table table-hover align-middle crm-table crm-automation-table mb-0">
            <thead><tr><th>Запрос</th><th>Сущность</th><th>Запросил</th><th>Статус</th><th>Дата</th><th class="text-end">Действия</th></tr></thead>
            <tbody id="approvalsListBody"><tr><td colspan="6" class="text-muted">Загрузка...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Create Modal -->
    <div class="modal fade" id="approvalsCreateModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header"><div><h5 class="modal-title">Создать запрос на согласование</h5><div class="crm-modal-subtitle">Выберите задачу или проект и назначьте согласующих.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>
          <form id="approvalsCreateForm">
            <div class="modal-body">
              <div class="mb-3"><label class="form-label">Название запроса</label><input class="form-control" name="title" maxlength="255" required placeholder="Например: Согласование бюджета проекта 1С:ERP"></div>
              <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Тип сущности</label><select class="form-select" name="entity_type" id="approvalsEntityType" required><option value="task">Задача</option><option value="project">Проект</option></select></div>
                <div class="col-md-8 position-relative"><label class="form-label" for="approvalsEntitySearch">Поиск задачи или проекта</label><input id="approvalsEntitySearch" class="form-control" placeholder="Введите название или public_id..." autocomplete="off"><div id="approvalsEntityResults" class="crm-autocomplete-list d-none"></div><input type="hidden" name="entity_public_id"></div>
              </div>
              <div class="mt-3">
                <label class="form-label">Согласующие</label>
                <select id="approvalsReviewersSelect" class="form-select d-none" name="reviewer_public_ids" multiple aria-hidden="true"></select>
                <div id="approvalsReviewersPicker" class="crm-check-picker" aria-live="polite"></div>
                <div id="approvalsReviewersSummary" class="crm-inline-help mt-2"><i class="fa-solid fa-users" aria-hidden="true"></i><span>Выберите одного или нескольких пользователей.</span></div>
              </div>
              <div class="mt-3"><label class="form-label">Комментарий к запросу</label><textarea class="form-control" name="comment" maxlength="1000" rows="3" placeholder="Например: проверьте бюджет, сроки и готовность к запуску"></textarea></div>
            </div>
            <div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit" id="approvalsCreateSubmitBtn">Отправить на согласование</button></div>
          </form>
        </div>
      </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="approvalsDetailModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header"><div><h5 class="modal-title" id="approvalsDetailTitle">Детали согласования</h5><div class="crm-modal-subtitle" id="approvalsDetailSubtitle"></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>
          <div class="modal-body" id="approvalsDetailBody"></div>
          <div class="modal-footer" id="approvalsDetailFooter"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Закрыть</button></div>
        </div>
      </div>
    </div>

    <!-- Approve/Reject Comment Modal -->
    <div class="modal fade" id="approvalsDecisionModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header"><div><h5 class="modal-title" id="approvalsDecisionTitle">Подтвердите решение</h5><div class="crm-modal-subtitle">Это действие нельзя отменить. Решение будет записано в историю.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>
          <form id="approvalsDecisionForm">
            <input type="hidden" name="approval_id"><input type="hidden" name="action">
            <div class="modal-body"><label class="form-label">Комментарий (необязательно)</label><textarea id="approvalsDecisionComment" class="form-control" name="comment" rows="2" placeholder="Причина решения..."></textarea></div>
            <div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit" id="approvalsDecisionSubmitBtn">Подтвердить</button></div>
          </form>
        </div>
      </div>
    </div>

  </main></div></div>
</body>
