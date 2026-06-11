<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Карточка клиента'; ?>
<body data-page="clients" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-client-detail-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=dashboard">Главная</a></li><li class="breadcrumb-item"><a href="index.php?route=clients">Клиенты</a></li><li class="breadcrumb-item active">Карточка клиента</li></ol><h1 class="crm-page-title" id="clientDetailTitle">Загрузка клиента...</h1><p class="crm-subtitle" id="clientDetailSubtitle">Загрузка параметров клиента...</p></div><div class="d-flex gap-2 flex-wrap"><button class="btn crm-btn-secondary" id="clientDetailEditBtn" type="button">Редактировать клиента</button><a class="btn crm-btn-secondary" href="index.php?route=clients">Назад к списку</a></div></div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Профиль клиента</h2><div class="crm-section-note">Основные реквизиты, контакты и юридическая информация.</div></div></div>
      <div id="clientDetailProfile"><div class="text-muted">Загрузка карточки...</div></div>
    </div>
    <div class="crm-card crm-section-card mt-3">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Связанные задачи</h2><div class="crm-section-note">Последние задачи по этому клиенту.</div></div></div>
      <div class="table-responsive"><table class="table crm-table mb-0"><thead><tr><th>Задача</th><th>Статус</th><th>Проект</th><th>Обновлена</th></tr></thead><tbody id="clientDetailTasksBody"><tr><td colspan="4" class="text-muted">Загрузка задач...</td></tr></tbody></table></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="crm-card crm-section-card mb-3" id="clientAiCard" data-requires-ai-use="1" data-ai-state="idle">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">AI по клиенту</h2><div class="crm-section-note">Сводка по клиенту с предпросмотром перед любыми действиями.</div></div></div>
      <div class="d-flex flex-wrap gap-2 mb-2">
        <button class="btn btn-sm crm-btn-primary" type="button" id="clientAiSummaryBtn">AI-сводка</button>
        <button class="btn btn-sm btn-light" type="button" id="clientAiMeetingPrepBtn">Подготовка к встрече</button>
        <button class="btn btn-sm btn-light" type="button" id="clientAiDataQualityBtn">Качество данных</button>
        <button class="btn btn-sm btn-light" type="button" id="clientAiSafeReportBtn">Client-safe отчёт</button>
        <button class="btn btn-sm btn-light" type="button" id="clientAiPreviewBtn" disabled>Предпросмотр</button>
        <button class="btn btn-sm crm-btn-muted" type="button" id="clientAiDismissBtn" disabled>Отклонить</button>
      </div>
      <div class="small text-muted mb-2" id="clientAiState">AI-сводка клиента не сформирована.</div>
      <div class="crm-metric-tile mb-2"><small class="text-muted d-block">Резюме</small><div id="clientAiSummaryText">Нажмите «AI-сводка», чтобы получить предложение.</div></div>
      <div class="crm-metric-tile mb-2"><small class="text-muted d-block">Факты из CRM</small><div id="clientAiFacts">—</div></div>
      <div class="crm-metric-tile"><small class="text-muted d-block">AI-инференсы</small><div id="clientAiInferences">—</div></div>
      <div class="crm-metric-tile mt-2 d-none" id="clientAiReportDraftWrap"><small class="text-muted d-block">Client-safe draft</small><pre class="mb-0 small" id="clientAiReportDraftText"></pre></div>
    </div>
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Сводка</h2><div class="crm-section-note">Быстрые метрики по карточке клиента.</div></div></div>
      <div id="clientDetailSummary"><div class="text-muted">Загрузка...</div></div>
    </div>
    <div class="crm-card crm-section-card mt-3">
      <div class="crm-section-head"><div><h2 class="h6 mb-0">Дополнительные поля</h2><div class="crm-section-note">Кастомные поля клиента из API.</div></div></div>
      <pre id="clientDetailExtra" class="mb-0 small crm-pre-wrap">Загрузка...</pre>
    </div>
  </div>
</div>
</main></div></div>

<div class="modal fade" id="clientDetailEditModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Редактировать клиента</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="clientDetailEditForm"><div class="modal-body"><input type="hidden" name="public_id"><div class="row g-3">
  <div class="col-md-8"><label class="form-label">Название</label><input class="form-control" name="title" maxlength="255" required></div>
  <div class="col-md-4"><label class="form-label">Тип клиента</label><select class="form-select" name="client_type"><option value="individual">Физлицо</option><option value="sole_proprietor">ИП</option><option value="legal_entity">Юрлицо</option></select></div>
  <div class="col-md-6"><label class="form-label">Юридическое наименование / ФИО ИП</label><input class="form-control" name="legal_name" maxlength="255"></div>
  <div class="col-md-3"><label class="form-label">ИНН</label><input class="form-control" name="tax_inn" maxlength="12"></div>
  <div class="col-md-3"><label class="form-label">Статус</label><input class="form-control" name="status" maxlength="64"></div>
  <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" maxlength="190"></div>
  <div class="col-md-4"><label class="form-label">Телефон</label><input class="form-control" name="phone" maxlength="64"></div>
  <div class="col-md-4"><label class="form-label">Сайт</label><input class="form-control" name="website" maxlength="2048"></div>
  <div class="col-12"><label class="form-label">Комментарий</label><textarea class="form-control" name="notes" rows="3"></textarea></div>
</div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit">Сохранить</button></div></form></div></div></div>
