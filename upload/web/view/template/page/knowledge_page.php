<?php declare(strict_types=1); ?>
<?php $title = $t('knowledge_page.title', 'TropaTT — Материал базы знаний'); ?>
<?php $pageId = (string)($_GET['id'] ?? ''); ?>
<body data-page="knowledge-page" data-protected="1" data-knowledge-page-id="<?= htmlspecialchars($pageId, ENT_QUOTES, 'UTF-8') ?>"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-knowledge-page-detail kb-page">

<div class="kb-page-header">
  <div class="kb-page-header-info">
    <nav aria-label="Breadcrumb">
      <ol class="breadcrumb mb-1 small">
        <li class="breadcrumb-item"><a href="index.php?route=dashboard"><?= htmlspecialchars($t('page.home', 'Главная'), ENT_QUOTES, 'UTF-8') ?></a></li>
        <li class="breadcrumb-item"><a href="index.php?route=knowledge"><?= htmlspecialchars($t('knowledge.page_title', 'База знаний'), ENT_QUOTES, 'UTF-8') ?></a></li>
        <li class="breadcrumb-item active" id="knowledgePageSpace"><?= htmlspecialchars($t('knowledge_page.page_title', 'Материал'), ENT_QUOTES, 'UTF-8') ?></li>
      </ol>
    </nav>
    <h1 id="knowledgePageTitle" class="kb-page-title h3 fw-bold mb-1"><?= htmlspecialchars($t('knowledge_page.page_title', 'Материал'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="kb-description small text-muted mb-0"><?= htmlspecialchars($t('knowledge_page.subtitle', 'Просмотр, редактирование и публикация знаний команды.'), ENT_QUOTES, 'UTF-8') ?></p>
  </div>
  <div class="kb-header-actions">
    <a class="btn crm-btn-secondary btn-sm" href="index.php?route=knowledge"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <?= htmlspecialchars($t('knowledge.back_to_list', 'К базе знаний'), ENT_QUOTES, 'UTF-8') ?></a>
    <button class="btn crm-btn-secondary btn-sm" type="button" id="knowledgeEditBtn"><i class="fa-solid fa-pen" aria-hidden="true"></i> <?= htmlspecialchars($t('knowledge_page.btn_edit', 'Редактировать'), ENT_QUOTES, 'UTF-8') ?></button>
    <div class="dropdown">
      <button class="btn crm-btn-secondary btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= htmlspecialchars($t('common.more', 'Ещё'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><button class="dropdown-item" type="button" id="knowledgeCopyLinkBtn"><i class="fa-regular fa-copy" aria-hidden="true"></i> <?= htmlspecialchars($t('knowledge_page.copy_link', 'Копировать ссылку'), ENT_QUOTES, 'UTF-8') ?></button></li>
      </ul>
    </div>
  </div>
</div>

<section class="card kb-meta-card mb-3">
  <div class="card-body py-2 px-3">
    <div class="kb-meta-grid">
      <div class="kb-meta-item">
        <i class="fa-regular fa-folder" aria-hidden="true"></i>
        <div class="kb-meta-text">
          <span class="kb-meta-label"><?= htmlspecialchars($t('knowledge.field_space', 'Раздел'), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="kb-meta-value" id="knowledgeMetaSpace">—</span>
        </div>
      </div>
      <div class="kb-meta-divider"></div>
      <div class="kb-meta-item">
        <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
        <div class="kb-meta-text">
          <span class="kb-meta-label"><?= htmlspecialchars($t('knowledge.field_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="kb-meta-value" id="knowledgeMetaType">—</span>
        </div>
      </div>
      <div class="kb-meta-divider"></div>
      <div class="kb-meta-item">
        <i class="fa-regular fa-circle-check" aria-hidden="true"></i>
        <div class="kb-meta-text">
          <span class="kb-meta-label"><?= htmlspecialchars($t('knowledge_page.status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="kb-meta-value kb-meta-status"><span id="knowledgePageStatus" class="crm-badge">—</span></span>
        </div>
      </div>
      <div class="kb-meta-divider"></div>
      <div class="kb-meta-item">
        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
        <div class="kb-meta-text">
          <span class="kb-meta-label"><?= htmlspecialchars($t('knowledge_page.updated_at', 'Обновлено'), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="kb-meta-value" id="knowledgeMetaUpdated">—</span>
        </div>
      </div>
      <div class="kb-meta-divider"></div>
      <div class="kb-meta-item">
        <i class="fa-regular fa-user" aria-hidden="true"></i>
        <div class="kb-meta-text">
          <span class="kb-meta-label"><?= htmlspecialchars($t('knowledge_page.author', 'Автор'), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="kb-meta-value" id="knowledgeMetaAuthor">—</span>
        </div>
      </div>
      <div class="kb-meta-divider"></div>
      <div class="kb-meta-item">
        <i class="fa-regular fa-eye" aria-hidden="true"></i>
        <div class="kb-meta-text">
          <span class="kb-meta-label"><?= htmlspecialchars($t('knowledge_page.views', 'Просмотры'), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="kb-meta-value" id="knowledgeMetaViews">0</span>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="crm-knowledge-detail-layout row g-3">

  <div class="col-12 col-xl-8 col-xxl-9">

    <div id="knowledgePageState" class="text-muted p-4"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>

    <section class="card kb-toc-card mb-3 d-none" id="knowledgeTocContainer">
      <div class="card-body p-3">
        <h5 class="card-title h6 mb-2 d-flex align-items-center gap-2">
          <i class="fa-solid fa-list-ul" aria-hidden="true"></i> <?= htmlspecialchars($t('knowledge_page.toc_title', 'Содержание'), ENT_QUOTES, 'UTF-8') ?>
        </h5>
        <nav id="knowledgeToc" class="crm-knowledge-toc"></nav>
      </div>
    </section>

    <article class="card kb-article-card mb-3 d-none" id="knowledgePageView">
      <div class="card-body p-4">
        <div class="article-content" id="knowledgePageContent"></div>
      </div>
    </article>

    <form id="knowledgePageEditor" class="d-none kb-editor-form" data-dirty-guard>
      <div class="card mb-3">
        <div class="crm-knowledge-writing-topbar">
          <div>
            <div class="crm-knowledge-writing-label"><?= htmlspecialchars($t('knowledge_page.editor_mode_title', 'Редактирование материала'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="small text-muted" id="knowledgeAutosaveStatus"></div>
          </div>
          <div class="crm-knowledge-writing-actions">
            <button class="btn crm-btn-secondary btn-sm" type="button" id="knowledgeCancelEditBtn"><?= htmlspecialchars($t('common.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
            <button class="btn crm-btn-secondary btn-sm" type="button" id="knowledgeSaveDraftBtn"><?= htmlspecialchars($t('knowledge_page.btn_save_draft', 'Сохранить черновик'), ENT_QUOTES, 'UTF-8') ?></button>
            <button class="btn crm-btn-primary btn-sm" type="submit"><?= htmlspecialchars($t('knowledge_page.btn_save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
        </div>
        <div class="crm-knowledge-edit-meta p-3">
          <input id="knowledgeEditTitle" class="form-control form-control-lg fw-bold border-0 px-0" name="title" required placeholder="<?= htmlspecialchars($t('knowledge.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?>">
          <div class="crm-knowledge-edit-review mt-2">
            <label class="small text-muted"><?= htmlspecialchars($t('knowledge_page.review_due_at_label', 'Проверка до'), ENT_QUOTES, 'UTF-8') ?></label>
            <input id="knowledgeEditReviewDue" class="form-control form-control-sm" type="date" name="review_due_at" style="width:200px">
          </div>
        </div>
        <div class="crm-knowledge-toolbar d-none" role="toolbar" aria-label="<?= htmlspecialchars($t('knowledge_page.editor_toolbar', 'Панель форматирования'), ENT_QUOTES, 'UTF-8') ?>">
          <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="bold" title="<?= htmlspecialchars($t('knowledge_page.bold', 'Жирный'), ENT_QUOTES, 'UTF-8') ?>"><b>B</b></button>
          <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="italic" title="<?= htmlspecialchars($t('knowledge_page.italic', 'Курсив'), ENT_QUOTES, 'UTF-8') ?>"><i>I</i></button>
          <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="h2" title="<?= htmlspecialchars($t('knowledge_page.h2', 'Заголовок H2'), ENT_QUOTES, 'UTF-8') ?>">H2</button>
          <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="h3" title="<?= htmlspecialchars($t('knowledge_page.h3', 'Заголовок H3'), ENT_QUOTES, 'UTF-8') ?>">H3</button>
          <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="ul" title="<?= htmlspecialchars($t('knowledge_page.ul', 'Список'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-list-ul" aria-hidden="true"></i></button>
          <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="ol" title="<?= htmlspecialchars($t('knowledge_page.ol', 'Нумерованный список'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-list-ol" aria-hidden="true"></i></button>
          <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="blockquote" title="<?= htmlspecialchars($t('knowledge_page.blockquote', 'Цитата'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-quote-right" aria-hidden="true"></i></button>
          <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="code" title="<?= htmlspecialchars($t('knowledge_page.code', 'Код'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-code" aria-hidden="true"></i></button>
          <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="link" title="<?= htmlspecialchars($t('knowledge_page.link', 'Ссылка'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-link" aria-hidden="true"></i></button>
          <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="checklist" title="<?= htmlspecialchars($t('knowledge_page.checklist', 'Чеклист'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-check-square" aria-hidden="true"></i></button>
        </div>
        <div class="crm-knowledge-visual-wrap">
          <textarea id="knowledgeEditContent" class="crm-knowledge-editor-source" name="content_html" rows="12" placeholder="<?= htmlspecialchars($t('knowledge_page.editor_placeholder', 'Начните писать материал...'), ENT_QUOTES, 'UTF-8') ?>" data-crm-visual-editor="1" data-richtext-off="1"></textarea>
        </div>
      </div>
    </form>

    <div class="card kb-bottom-panels">
      <div class="card-header bg-transparent border-bottom p-0">
        <nav class="crm-knowledge-tab-nav" role="tablist">
          <button class="crm-knowledge-tab-btn is-active" id="kb-tab-comments" data-bs-toggle="tab" data-bs-target="#kb-panel-comments" type="button" role="tab" aria-controls="kb-panel-comments" aria-selected="true">
            <i class="fa-regular fa-comments" aria-hidden="true"></i>
            <?= htmlspecialchars($t('knowledge_page.comments_title', 'Комментарии'), ENT_QUOTES, 'UTF-8') ?>
          </button>
          <button class="crm-knowledge-tab-btn" id="kb-tab-files" data-bs-toggle="tab" data-bs-target="#kb-panel-files" type="button" role="tab" aria-controls="kb-panel-files" aria-selected="false">
            <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
            <?= htmlspecialchars($t('knowledge_page.attachments_title', 'Файлы'), ENT_QUOTES, 'UTF-8') ?>
          </button>
        </nav>
      </div>
      <div class="tab-content">
        <div class="crm-knowledge-tab-panel is-active p-3" id="kb-panel-comments" role="tabpanel" aria-labelledby="kb-tab-comments">
          <div id="knowledgeCommentsList"><div class="text-muted small"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="mt-2">
            <div id="knowledgeCommentReplyIndicator" class="small text-muted d-none mb-1">
              <span id="knowledgeCommentReplyLabel"></span>
              <button type="button" class="btn crm-btn-secondary btn-sm" id="knowledgeCommentCancelReply"><?= htmlspecialchars($t('knowledge_page.comments_cancel_reply', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
            <div class="crm-knowledge-comment-compose">
              <textarea id="knowledgeCommentInput" class="form-control form-control-sm" rows="2" placeholder="<?= htmlspecialchars($t('knowledge_page.comments_placeholder', 'Напишите комментарий...'), ENT_QUOTES, 'UTF-8') ?>" data-crm-visual-editor="1" data-richtext-off="1"></textarea>
              <div class="crm-knowledge-comment-actions">
                <button class="btn crm-btn-primary btn-sm" type="button" id="knowledgeCommentSendBtn"><?= htmlspecialchars($t('knowledge_page.comments_send', 'Отправить'), ENT_QUOTES, 'UTF-8') ?></button>
              </div>
            </div>
          </div>
        </div>
        <div class="crm-knowledge-tab-panel p-3" id="kb-panel-files" role="tabpanel" aria-labelledby="kb-tab-files">
          <div id="knowledgeAttachmentsList"><div class="text-muted small"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="mt-2">
            <div class="crm-file-upload-row">
              <input type="file" id="knowledgeFileInput" class="form-control form-control-sm crm-file-input" multiple>
              <button class="btn crm-btn-primary btn-sm flex-shrink-0" type="button" id="knowledgeFileUploadBtn"><?= htmlspecialchars($t('knowledge_page.attachments_upload', 'Загрузить'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
            <div class="small text-muted mt-1"><?= htmlspecialchars($t('knowledge_page.attachments_drag_hint', 'или перетащите файл сюда'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
      </div>
    </div>

  </div>

  <aside class="col-12 col-xl-4 col-xxl-3">
    <div class="kb-side-stack">

      <section class="card kb-side-card">
        <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-2 px-3">
          <i class="fa-solid fa-list-check" aria-hidden="true"></i>
          <h5 class="h6 mb-0"><?= htmlspecialchars($t('knowledge_page.actions_title', 'Действия'), ENT_QUOTES, 'UTF-8') ?></h5>
        </div>
        <div class="card-body p-3">
          <div class="d-grid gap-2">
            <button class="btn crm-btn-primary btn-sm" type="button" id="knowledgePublishBtn"><i class="fa-regular fa-circle-check" aria-hidden="true"></i> <?= htmlspecialchars($t('knowledge_page.btn_publish', 'Опубликовать'), ENT_QUOTES, 'UTF-8') ?></button>
            <button class="btn crm-btn-secondary btn-sm" type="button" id="knowledgeReviewBtn"><i class="fa-regular fa-paper-plane" aria-hidden="true"></i> <?= htmlspecialchars($t('knowledge_page.btn_request_review', 'Отправить на проверку'), ENT_QUOTES, 'UTF-8') ?></button>
            <div class="dropdown">
              <button class="btn crm-btn-secondary btn-sm w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-download" aria-hidden="true"></i> <?= htmlspecialchars($t('knowledge_page.btn_export', 'Экспорт'), ENT_QUOTES, 'UTF-8') ?></button>
              <ul class="dropdown-menu dropdown-menu-end w-100">
                <li><a class="dropdown-item" href="#" data-export-format="json">JSON</a></li>
                <li><a class="dropdown-item" href="#" data-export-format="markdown">Markdown</a></li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      <section class="card kb-side-card">
        <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-2 px-3">
          <i class="fa-solid fa-tags" aria-hidden="true"></i>
          <h5 class="h6 mb-0"><?= htmlspecialchars($t('knowledge_page.tags_title', 'Теги'), ENT_QUOTES, 'UTF-8') ?></h5>
        </div>
        <div class="card-body py-2 px-3">
          <div id="knowledgeTagsList" class="kb-tags-list"><span class="text-muted kb-tags-empty"><?= htmlspecialchars($t('knowledge_page.no_tags', 'Теги не добавлены'), ENT_QUOTES, 'UTF-8') ?></span></div>
          <div class="kb-tag-add-row">
            <select id="knowledgeTagSelect" class="form-select form-select-sm"><option value=""><?= htmlspecialchars($t('knowledge_page.tag_select_hint', 'Добавить тег...'), ENT_QUOTES, 'UTF-8') ?></option></select>
            <button class="btn btn-sm crm-btn-primary kb-tag-add-btn" type="button" id="knowledgeTagAddBtn" disabled aria-label="<?= htmlspecialchars($t('knowledge_page.tag_add', 'Добавить тег'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
          </div>
        </div>
      </section>

      <section class="card kb-side-card">
        <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-2 px-3">
          <i class="fa-regular fa-bell" aria-hidden="true"></i>
          <h5 class="h6 mb-0"><?= htmlspecialchars($t('knowledge_page.subscribe_title', 'Подписка'), ENT_QUOTES, 'UTF-8') ?></h5>
        </div>
        <div class="card-body p-3">
          <div class="d-flex gap-2">
            <button class="btn crm-btn-secondary btn-sm flex-fill" type="button" id="knowledgeFavBtn"><i class="fa-regular fa-star" aria-hidden="true"></i> <span id="knowledgeFavLabel"><?= htmlspecialchars($t('knowledge_page.favorite_add', 'В избранное'), ENT_QUOTES, 'UTF-8') ?></span></button>
            <button class="btn crm-btn-secondary btn-sm flex-fill" type="button" id="knowledgeSubBtn"><i class="fa-regular fa-bell" aria-hidden="true"></i> <span id="knowledgeSubLabel"><?= htmlspecialchars($t('knowledge_page.subscribe', 'Подписаться'), ENT_QUOTES, 'UTF-8') ?></span></button>
          </div>
        </div>
      </section>

      <section class="card kb-side-card" id="knowledgeAiSection">
        <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-2 px-3">
          <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
          <h5 class="h6 mb-0"><?= htmlspecialchars($t('knowledge_page.ai_title', 'AI-инструменты'), ENT_QUOTES, 'UTF-8') ?></h5>
        </div>
        <div class="card-body p-2">
          <div class="kb-action-list">
            <button class="kb-action-row" type="button" id="knowledgeAiSummaryBtn">
              <span class="kb-action-icon"><i class="fa-regular fa-message" aria-hidden="true"></i></span>
              <span class="kb-action-text"><?= htmlspecialchars($t('knowledge_page.btn_ai_summary', 'Краткое содержание'), ENT_QUOTES, 'UTF-8') ?></span>
              <i class="fa-solid fa-chevron-right kb-action-arrow" aria-hidden="true"></i>
            </button>
            <button class="kb-action-row" type="button" id="knowledgeAiExplainBtn">
              <span class="kb-action-icon"><i class="fa-regular fa-lightbulb" aria-hidden="true"></i></span>
              <span class="kb-action-text"><?= htmlspecialchars($t('knowledge_page.btn_ai_explain', 'Объяснить проще'), ENT_QUOTES, 'UTF-8') ?></span>
              <i class="fa-solid fa-chevron-right kb-action-arrow" aria-hidden="true"></i>
            </button>
            <button class="kb-action-row" type="button" id="knowledgeAiChecklistBtn">
              <span class="kb-action-icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span>
              <span class="kb-action-text"><?= htmlspecialchars($t('knowledge_page.btn_ai_checklist', 'Чеклист'), ENT_QUOTES, 'UTF-8') ?></span>
              <i class="fa-solid fa-chevron-right kb-action-arrow" aria-hidden="true"></i>
            </button>
            <button class="kb-action-row" type="button" id="knowledgeAiSimilarBtn">
              <span class="kb-action-icon"><i class="fa-regular fa-copy" aria-hidden="true"></i></span>
              <span class="kb-action-text"><?= htmlspecialchars($t('knowledge_page.btn_ai_similar', 'Похожие страницы'), ENT_QUOTES, 'UTF-8') ?></span>
              <i class="fa-solid fa-chevron-right kb-action-arrow" aria-hidden="true"></i>
            </button>
            <button class="kb-action-row" type="button" id="knowledgeAiFaqBtn">
              <span class="kb-action-icon"><i class="fa-regular fa-circle-question" aria-hidden="true"></i></span>
              <span class="kb-action-text"><?= htmlspecialchars($t('knowledge_page.btn_ai_faq', 'FAQ из комментариев'), ENT_QUOTES, 'UTF-8') ?></span>
              <i class="fa-solid fa-chevron-right kb-action-arrow" aria-hidden="true"></i>
            </button>
          </div>
          <div id="knowledgeAiResult" class="kb-ai-result d-none mt-2 p-2 rounded bg-light small">
            <div id="knowledgeAiResultTitle" class="fw-bold small mb-1"></div>
            <div id="knowledgeAiResultBody" class="text-muted"></div>
          </div>
        </div>
      </section>

      <section class="card kb-side-card">
        <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-2 px-3">
          <i class="fa-solid fa-code-compare" aria-hidden="true"></i>
          <h5 class="h6 mb-0"><?= htmlspecialchars($t('knowledge_page.versions_title', 'Версии'), ENT_QUOTES, 'UTF-8') ?></h5>
        </div>
        <div class="card-body p-3">
          <div id="knowledgeVersions" class="kb-version-list"><div class="text-muted small"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div id="knowledgeDiffContainer" class="d-none mt-2">
            <h6 class="small fw-bold mb-1"><?= htmlspecialchars($t('knowledge_page.diff_title', 'Сравнение'), ENT_QUOTES, 'UTF-8') ?></h6>
            <div id="knowledgeDiffContent" class="small text-muted p-2 bg-light rounded"></div>
          </div>
        </div>
      </section>

      <section class="card kb-side-card">
        <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-2 px-3">
          <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
          <h5 class="h6 mb-0"><?= htmlspecialchars($t('knowledge_page.permissions_title', 'Доступ'), ENT_QUOTES, 'UTF-8') ?></h5>
        </div>
        <div class="card-body p-0">
          <button class="kb-action-row border-0 w-100 rounded-0" type="button" id="knowledgePermissionsBtn">
            <span class="kb-action-icon"><i class="fa-solid fa-users-gear" aria-hidden="true"></i></span>
            <span class="kb-action-text"><?= htmlspecialchars($t('knowledge_page.btn_permissions', 'Управление доступом к материалу'), ENT_QUOTES, 'UTF-8') ?></span>
            <i class="fa-solid fa-chevron-right kb-action-arrow" aria-hidden="true"></i>
          </button>
        </div>
      </section>

      <section class="card kb-side-card">
        <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-2 px-3">
          <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
          <h5 class="h6 mb-0"><?= htmlspecialchars($t('knowledge_page.tree_title', 'Страницы раздела'), ENT_QUOTES, 'UTF-8') ?></h5>
        </div>
        <div class="card-body p-2">
          <div id="knowledgeTree" class="kb-section-pages"><div class="text-muted small p-1"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="mt-1 px-1 pt-1 border-top">
            <a href="index.php?route=knowledge" class="small text-decoration-none"><?= htmlspecialchars($t('knowledge_page.show_all_pages', 'Показать все страницы раздела'), ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-chevron-right" style="font-size:0.6rem" aria-hidden="true"></i></a>
          </div>
        </div>
      </section>

      <section class="card kb-side-card border-danger">
        <div class="card-header bg-transparent border-bottom border-danger d-flex align-items-center gap-2 py-2 px-3">
          <i class="fa-regular fa-trash-can text-danger" aria-hidden="true"></i>
          <h5 class="h6 mb-0 text-danger"><?= htmlspecialchars($t('knowledge_page.archive_title', 'Архив'), ENT_QUOTES, 'UTF-8') ?></h5>
        </div>
        <div class="card-body p-3">
            <button class="btn crm-btn-danger-soft btn-sm w-100" type="button" id="knowledgeArchiveBtn"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> <?= htmlspecialchars($t('knowledge_page.btn_archive', 'Переместить материал в архив'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </section>

    </div>
  </aside>

</div>

<div class="d-none" id="knowledgeMetaReviewDue"></div>

</main></div></div>

<div class="modal fade" id="knowledgePagePermissionsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><?= htmlspecialchars($t('knowledge_page.permissions_title', 'Права доступа к материалу'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button></div>
    <div class="modal-body">
      <div id="knowledgePagePermList" class="mb-3"><div class="text-muted"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      <hr>
      <h6><?= htmlspecialchars($t('admin_knowledge.permissions_add_title', 'Добавить доступ'), ENT_QUOTES, 'UTF-8') ?></h6>
      <div class="row g-1">
        <div class="col-md-4">
          <select id="knowledgePagePermSubjectType" class="form-select form-select-sm">
            <option value="user"><?= htmlspecialchars($t('admin_knowledge.permissions_subject_user', 'Пользователь'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="role"><?= htmlspecialchars($t('admin_knowledge.permissions_subject_role', 'Роль'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="team"><?= htmlspecialchars($t('admin_knowledge.permissions_subject_team', 'Команда'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="department"><?= htmlspecialchars($t('admin_knowledge.permissions_subject_department', 'Отдел'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
        </div>
        <div class="col-md-4">
          <select id="knowledgePagePermSubjectId" class="form-select form-select-sm" style="max-width:100%"><option value=""><?= htmlspecialchars($t('admin_knowledge.permissions_select_subject', 'Выберите...'), ENT_QUOTES, 'UTF-8') ?></option></select>
        </div>
        <div class="col-md-2">
          <select id="knowledgePagePermAccessLevel" class="form-select form-select-sm">
            <option value="view"><?= htmlspecialchars($t('admin_knowledge.permissions_level_view', 'Просмотр'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="comment"><?= htmlspecialchars($t('admin_knowledge.permissions_level_comment', 'Комментирование'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="edit"><?= htmlspecialchars($t('admin_knowledge.permissions_level_edit', 'Редактирование'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="manage"><?= htmlspecialchars($t('admin_knowledge.permissions_level_manage', 'Управление'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-sm crm-btn-primary w-100" type="button" id="knowledgePagePermAddBtn"><?= htmlspecialchars($t('common.add', 'Добавить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?></button></div>
  </div></div>
</div>

<!-- AI Result Modal -->
<div class="modal fade" id="knowledgeAiModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="knowledgeAiModalTitle"><?= htmlspecialchars($t('knowledge_page.ai_title', 'AI'), ENT_QUOTES, 'UTF-8') ?></h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button>
    </div>
    <div class="modal-body">
      <div id="knowledgeAiModalLoader" class="crm-ai-loader d-none">
        <div class="crm-ai-loader-spinner"><div class="crm-spinner"></div></div>
        <div class="crm-ai-loader-text"><?= htmlspecialchars($t('knowledge_page.ai_loading_title', 'AI обрабатывает...'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div id="knowledgeAiModalBody" class="crm-knowledge-ai-modal-body"></div>
    </div>
    <div class="modal-footer" id="knowledgeAiModalFooter">
      <button class="btn crm-btn-secondary" type="button" id="knowledgeAiModalCopyBtn"><i class="fa-regular fa-clipboard" style="margin-right:0.3rem" aria-hidden="true"></i><?= htmlspecialchars($t('knowledge_page.ai_copy', 'Копировать'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn crm-btn-secondary" type="button" id="knowledgeAiModalInsertBtn" style="display:none"><i class="fa-solid fa-file-import" style="margin-right:0.3rem" aria-hidden="true"></i><?= htmlspecialchars($t('knowledge_page.ai_insert', 'Вставить в материал'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn crm-btn-secondary" type="button" id="knowledgeAiModalRefreshBtn"><i class="fa-solid fa-arrows-rotate" style="margin-right:0.3rem" aria-hidden="true"></i><?= htmlspecialchars($t('knowledge_page.ai_refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
      <button type="button" class="btn crm-btn-primary" data-bs-dismiss="modal"><?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </div></div>
</div>

<script nonce="<?= $csp_nonce ?>">
(function () {
  var pageId = document.body.getAttribute('data-knowledge-page-id') || '';
  var i18n = window.CRM && window.CRM.i18n;
  var t = function (key, fallback) { return i18n && i18n.t ? i18n.t(key, fallback) : fallback; };
  var current = null, savedEditorRange = null;
  var isFav = false, isSub = false, autoTimer = null;
  var els = {
    state: document.getElementById('knowledgePageState'),
    view: document.getElementById('knowledgePageView'),
    editor: document.getElementById('knowledgePageEditor'),
    title: document.getElementById('knowledgePageTitle'),
    space: document.getElementById('knowledgePageSpace'),
    status: document.getElementById('knowledgePageStatus'),
    content: document.getElementById('knowledgePageContent'),
    editTitle: document.getElementById('knowledgeEditTitle'),
    editContent: document.getElementById('knowledgeEditContent'),
    editReviewDue: document.getElementById('knowledgeEditReviewDue'),
    metaReviewDue: document.getElementById('knowledgeMetaReviewDue'),
    visualEditor: document.getElementById('knowledgeVisualEditor'),
    blockAddBtn: document.getElementById('knowledgeBlockAddBtn'),
    blockMenu: document.getElementById('knowledgeBlockMenu'),
    blockSearch: document.getElementById('knowledgeBlockSearch'),
    metaSpace: document.getElementById('knowledgeMetaSpace'),
    metaType: document.getElementById('knowledgeMetaType'),
    metaUpdated: document.getElementById('knowledgeMetaUpdated'),
    metaViews: document.getElementById('knowledgeMetaViews'),
    metaAuthor: document.getElementById('knowledgeMetaAuthor'),
    versions: document.getElementById('knowledgeVersions'),
    tree: document.getElementById('knowledgeTree'),
    favBtn: document.getElementById('knowledgeFavBtn'),
    subBtn: document.getElementById('knowledgeSubBtn'),
    commentsList: document.getElementById('knowledgeCommentsList'),
    commentInput: document.getElementById('knowledgeCommentInput'),
    commentSend: document.getElementById('knowledgeCommentSendBtn'),
    diffContainer: document.getElementById('knowledgeDiffContainer'),
    diffContent: document.getElementById('knowledgeDiffContent'),
    autosaveStatus: document.getElementById('knowledgeAutosaveStatus'),
    attachList: document.getElementById('knowledgeAttachmentsList'),
    attachInput: document.getElementById('knowledgeFileInput'),
    attachUploadBtn: document.getElementById('knowledgeFileUploadBtn'),
    tagsList: document.getElementById('knowledgeTagsList'),
    tagSelect: document.getElementById('knowledgeTagSelect'),
    tagAddBtn: document.getElementById('knowledgeTagAddBtn'),
    tocContainer: document.getElementById('knowledgeTocContainer'),
    toc: document.getElementById('knowledgeToc'),
    permBtn: document.getElementById('knowledgePermissionsBtn'),
    permModal: document.getElementById('knowledgePagePermissionsModal'),
    permList: document.getElementById('knowledgePagePermList'),
    permSubjectType: document.getElementById('knowledgePagePermSubjectType'),
    permSubjectId: document.getElementById('knowledgePagePermSubjectId'),
    permAccessLevel: document.getElementById('knowledgePagePermAccessLevel'),
    permAddBtn: document.getElementById('knowledgePagePermAddBtn')
  };
  function editorWrapTag(ta, before, after) {
    var start = ta.selectionStart, end = ta.selectionEnd;
    var text = ta.value;
    var selected = text.substring(start, end) || '';
    ta.value = text.substring(0, start) + before + selected + after + text.substring(end);
    ta.selectionStart = ta.selectionEnd = start + before.length + selected.length;
    ta.focus();
    ta.dispatchEvent(new Event('input', {bubbles: true}));
  }
  function editorInsertLineBefore(ta, prefix) {
    var start = ta.selectionStart;
    var text = ta.value;
    var lineStart = text.lastIndexOf('\n', start - 1) + 1;
    var lineEnd = text.indexOf('\n', start);
    if (lineEnd === -1) lineEnd = text.length;
    var line = text.substring(lineStart, lineEnd);
    ta.value = text.substring(0, lineStart) + prefix + line + '\n' + text.substring(lineEnd);
    ta.selectionStart = ta.selectionEnd = lineStart + prefix.length + line.length + 1;
    ta.focus();
    ta.dispatchEvent(new Event('input', {bubbles: true}));
  }
  function syncHiddenContent() {
    if (!els.editContent) return;
    var editor = getVisualEditorInstance();
    if (editor && typeof editor.getValue === 'function') {
      els.editContent.value = editor.getValue();
      return;
    }
    if (els.visualEditor) {
      els.editContent.value = sanitizePreviewHtml(els.visualEditor.innerHTML || '');
    }
  }
  function getVisualEditorInstance() {
    if (!els.editContent || !window.CRM.VisualEditor || typeof window.CRM.VisualEditor.getInstances !== 'function') return null;
    var instances = window.CRM.VisualEditor.getInstances();
    for (var i = 0; i < instances.length; i += 1) {
      if (instances[i] && instances[i]._textarea === els.editContent) return instances[i];
    }
    return null;
  }
  function getTextareaVisualEditorValue(textarea) {
    if (!textarea) return '';
    if (window.CRM.VisualEditor && typeof window.CRM.VisualEditor.getInstances === 'function') {
      var instances = window.CRM.VisualEditor.getInstances();
      for (var i = 0; i < instances.length; i += 1) {
        if (instances[i] && instances[i]._textarea === textarea && typeof instances[i].getValue === 'function') {
          return String(instances[i].getValue() || '');
        }
      }
    }
    return String(textarea.value || '');
  }
  function refreshKnowledgeVisualEditor(force) {
    if (window.CRM.VisualEditor && typeof window.CRM.VisualEditor.refreshEditors === 'function') {
      window.CRM.VisualEditor.refreshEditors(els.editor || document, !!force);
    }
  }
  function setKnowledgeEditorContent(html) {
    if (!els.editContent) return;
    els.editContent.value = sanitizePreviewHtml(html || '');
    var editor = getVisualEditorInstance();
    if (editor && typeof editor.setValue === 'function') {
      editor.setValue(els.editContent.value);
    } else {
      refreshKnowledgeVisualEditor(true);
      window.setTimeout(function () { refreshKnowledgeVisualEditor(true); }, 120);
    }
  }
  function rememberEditorSelection() {
    if (!els.visualEditor || !window.getSelection) return;
    var selection = window.getSelection();
    if (!selection || !selection.rangeCount) return;
    var range = selection.getRangeAt(0);
    if (els.visualEditor.contains(range.commonAncestorContainer)) savedEditorRange = range.cloneRange();
  }
  function restoreEditorSelection() {
    if (!savedEditorRange || !window.getSelection) return;
    var selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(savedEditorRange);
  }
  function focusVisualEditor() {
    var editor = getVisualEditorInstance();
    if (editor && editor._content) {
      editor._content.focus();
      return;
    }
    if (!els.visualEditor) return;
    els.visualEditor.focus();
    restoreEditorSelection();
  }
  function openBlockMenu() {
    if (!els.blockMenu) return;
    rememberEditorSelection();
    els.blockMenu.classList.remove('d-none');
    if (els.blockSearch) {
      els.blockSearch.value = '';
      filterBlockMenu('');
      window.setTimeout(function () { els.blockSearch.focus(); }, 20);
    }
  }
  function closeBlockMenu() {
    if (els.blockMenu) els.blockMenu.classList.add('d-none');
    focusVisualEditor();
  }
  function filterBlockMenu(query) {
    if (!els.blockMenu) return;
    var needle = String(query || '').toLowerCase().trim();
    els.blockMenu.querySelectorAll('[data-editor-block]').forEach(function (btn) {
      var text = (btn.textContent || '').toLowerCase();
      btn.style.display = !needle || text.indexOf(needle) !== -1 ? '' : 'none';
    });
  }
  function insertVisualHtml(html) {
    var editor = getVisualEditorInstance();
    if (editor && typeof editor.setValue === 'function' && typeof editor.getValue === 'function') {
      editor.setValue((editor.getValue() || '') + html);
      syncHiddenContent();
      startAutosave();
      return;
    }
    focusVisualEditor();
    document.execCommand('insertHTML', false, html);
    syncHiddenContent();
    startAutosave();
  }
  function runVisualCommand(cmd) {
    if (!els.visualEditor) return false;
    focusVisualEditor();
    switch (cmd) {
      case 'bold': document.execCommand('bold', false, null); break;
      case 'italic': document.execCommand('italic', false, null); break;
      case 'h2': document.execCommand('formatBlock', false, 'h2'); break;
      case 'h3': document.execCommand('formatBlock', false, 'h3'); break;
      case 'ul': document.execCommand('insertUnorderedList', false, null); break;
      case 'ol': document.execCommand('insertOrderedList', false, null); break;
      case 'blockquote': document.execCommand('formatBlock', false, 'blockquote'); break;
      case 'code': insertVisualHtml('<code>' + esc(window.getSelection ? window.getSelection().toString() || t('knowledge_page.code', 'Код') : t('knowledge_page.code', 'Код')) + '</code>'); return true;
      case 'link': {
        var url = prompt(t('knowledge_page.link_prompt', 'Введите URL:'), 'https://');
        if (url) document.execCommand('createLink', false, url);
        break;
      }
      case 'checklist': insertVisualHtml('<p><label><input type="checkbox"> ' + esc(t('knowledge_page.checklist', 'Чеклист')) + '</label></p>'); return true;
      default: return false;
    }
    syncHiddenContent();
    startAutosave();
    updateToolbarState();
    return true;
  }
  function updateToolbarState() {
    if (!els.visualEditor || els.editor.classList.contains('d-none')) return;
    var map = {
      bold: 'bold',
      italic: 'italic',
      ul: 'insertUnorderedList',
      ol: 'insertOrderedList'
    };
    document.querySelectorAll('[data-editor-cmd]').forEach(function (btn) {
      var cmd = btn.getAttribute('data-editor-cmd');
      var active = false;
      try {
        if (map[cmd]) active = document.queryCommandState(map[cmd]);
        if (cmd === 'h2' || cmd === 'h3' || cmd === 'blockquote') {
          var block = String(document.queryCommandValue('formatBlock') || '').toLowerCase().replace(/[<>]/g, '');
          active = block === cmd || (cmd === 'blockquote' && block === 'blockquote');
        }
      } catch (e) {}
      btn.classList.toggle('is-active', active);
    });
  }
  document.querySelectorAll('[data-editor-cmd]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var cmd = btn.getAttribute('data-editor-cmd');
      if (runVisualCommand(cmd)) return;
      if (!els.editContent) return;
      var ta = els.editContent;
      switch (cmd) {
        case 'bold': editorWrapTag(ta, '<strong>', '</strong>'); break;
        case 'italic': editorWrapTag(ta, '<em>', '</em>'); break;
        case 'h2': editorInsertLineBefore(ta, '<h2>'); editorWrapTag(ta, '', '</h2>'); break;
        case 'h3': editorInsertLineBefore(ta, '<h3>'); editorWrapTag(ta, '', '</h3>'); break;
        case 'ul': editorInsertLineBefore(ta, '<ul><li>'); editorWrapTag(ta, '', '</li></ul>'); break;
        case 'ol': editorInsertLineBefore(ta, '<ol><li>'); editorWrapTag(ta, '', '</li></ol>'); break;
        case 'blockquote': editorInsertLineBefore(ta, '<blockquote><p>'); editorWrapTag(ta, '', '</p></blockquote>'); break;
        case 'code': editorWrapTag(ta, '<code>', '</code>'); break;
        case 'link': {
          var url = prompt(t('knowledge_page.link_prompt', 'Введите URL:'), 'https://');
          if (url) editorWrapTag(ta, '<a href="' + esc(url) + '">', '</a>');
          break;
        }
        case 'checklist': editorInsertLineBefore(ta, '<p><input type="checkbox"> '); editorWrapTag(ta, '', '</p>'); break;
      }
    });
  });
  function getApi() {
    return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
  }
  function waitForApi(callback, attempts) {
    if (getApi()) {
      callback();
      return;
    }
    if ((attempts || 0) > 80) {
      els.state.textContent = t('knowledge_page.load_error', 'Не удалось загрузить материал.');
      return;
    }
    window.setTimeout(function () { waitForApi(callback, (attempts || 0) + 1); }, 50);
  }
  async function request(route, options) {
    var api = getApi();
    if (!api) throw new Error('CRM_API_NOT_READY');
    return api.request(route, options);
  }
  function buildUrl(route) {
    var api = getApi();
    if (api && typeof api.buildUrl === 'function') {
      return api.buildUrl(route);
    }
    return '/' + String(route || '').replace(/^\/+/, '');
  }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
    });
  }
  function sanitizePreviewHtml(html) {
    var template = document.createElement('template');
    template.innerHTML = String(html || '');
    template.content.querySelectorAll('script, iframe, object, embed').forEach(function (node) { node.remove(); });
    template.content.querySelectorAll('*').forEach(function (node) {
      Array.prototype.slice.call(node.attributes || []).forEach(function (attr) {
        var name = attr.name.toLowerCase();
        var value = String(attr.value || '');
        if (name.indexOf('on') === 0 || ((name === 'href' || name === 'src') && /^\s*javascript:/i.test(value))) {
          node.removeAttribute(attr.name);
        }
      });
    });
    return template.innerHTML;
  }
  function updateEditorPreview() {
    if (!els.editPreview || !els.editContent) return;
    var html = els.editContent.value || '<p class="text-muted">' + esc(t('knowledge_page.empty_content', 'Содержание пока не заполнено.')) + '</p>';
    els.editPreview.innerHTML = sanitizePreviewHtml(html);
  }
  function renderPermissionList(items) {
    if (!els.permList) return;
    if (!items || !items.length) {
      els.permList.innerHTML = '<div class="text-muted">' + esc(t('admin_knowledge.permissions_empty', 'Нет назначенных прав доступа')) + '</div>';
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
    els.permList.innerHTML = '<table class="table crm-table mb-0"><thead><tr><th>' + esc(t('admin_knowledge.permissions_th_subject', 'Субъект')) + '</th><th>' + esc(t('admin_knowledge.permissions_th_level', 'Уровень')) + '</th><th>' + esc(t('admin_knowledge.permissions_th_created', 'Добавлено')) + '</th><th></th></tr></thead><tbody>' + items.map(function (p) {
      var label = p.user_name || p.role_title || p.team_title || p.department_title || p.user_public_id || p.role_public_id || p.team_public_id || p.department_public_id || p.subject_id;
      var permissionId = p.permission_key || '';
      var lev = levelMap[p.access_level] || { label: esc(p.access_level), cls: 'crm-badge crm-badge-secondary' };
      var dateStr = p.created_at ? p.created_at.substring(0, 10) : '';
      return '<tr><td><strong>' + esc(typeMap[p.subject_type] || p.subject_type || '') + ': ' + esc(label) + '</strong></td><td><span class="' + lev.cls + '" style="font-size:0.75rem">' + lev.label + '</span></td><td class="small text-muted">' + esc(dateStr) + '</td><td><button class="btn btn-sm crm-btn-danger-soft" data-page-perm-delete="' + esc(permissionId) + '">' + esc(t('common.delete', 'Удалить')) + '</button></td></tr>';
    }).join('') + '</tbody></table>';
  }
  async function loadPagePermissions() {
    if (!els.permList || !pageId) return;
    els.permList.innerHTML = '<div class="text-muted">' + esc(t('knowledge.loading', 'Загрузка...')) + '</div>';
    try {
      var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/permissions', { method: 'GET' });
      renderPermissionList(envelope.data && envelope.data.items || []);
    } catch (e) {
      els.permList.innerHTML = '<div class="text-muted">' + esc(t('knowledge.load_error', 'Не удалось загрузить базу знаний.')) + '</div>';
    }
  }
  async function loadPagePermissionSubjects(type) {
    if (!els.permSubjectId) return;
    els.permSubjectId.innerHTML = '<option value="">' + esc(t('admin_knowledge.permissions_loading_subjects', 'Загрузка...')) + '</option>';
    try {
      var envelope, items;
      if (type === 'user') {
        envelope = await request('api/v1/users', { method: 'GET', query: { limit: 200 } });
        items = envelope.data && envelope.data.items || [];
        els.permSubjectId.innerHTML = '<option value="">' + esc(t('admin_knowledge.permissions_select_user', 'Выберите пользователя...')) + '</option>' + items.map(function (u) {
          var label = u.name || u.full_name || u.login || u.email || u.public_id || '';
          return '<option value="' + esc(u.public_id || u.id || '') + '">' + esc(label) + '</option>';
        }).join('');
      } else if (type === 'role') {
        envelope = await request('api/v1/roles', { method: 'GET', query: { limit: 50 } });
        items = envelope.data && envelope.data.items || [];
        els.permSubjectId.innerHTML = '<option value="">' + esc(t('admin_knowledge.permissions_select_role', 'Выберите роль...')) + '</option>' + items.map(function (r) {
          return '<option value="' + esc(r.public_id || r.id || '') + '">' + esc(r.title || r.code || r.public_id || '') + '</option>';
        }).join('');
      } else if (type === 'team') {
        envelope = await request('api/v1/teams', { method: 'GET', query: { limit: 200 } });
        items = envelope.data && envelope.data.items || [];
        els.permSubjectId.innerHTML = '<option value="">' + esc(t('admin_knowledge.permissions_select_team', 'Выберите команду...')) + '</option>' + items.map(function (team) {
          return '<option value="' + esc(team.public_id || team.id || '') + '">' + esc(team.title || team.public_id || '') + '</option>';
        }).join('');
      } else if (type === 'department') {
        envelope = await request('api/v1/departments', { method: 'GET', query: { limit: 200 } });
        items = envelope.data && envelope.data.items || [];
        els.permSubjectId.innerHTML = '<option value="">' + esc(t('admin_knowledge.permissions_select_department', 'Выберите отдел...')) + '</option>' + items.map(function (department) {
          return '<option value="' + esc(department.public_id || department.id || '') + '">' + esc(department.title || department.public_id || '') + '</option>';
        }).join('');
      }
    } catch (e) {
      els.permSubjectId.innerHTML = '<option value="">' + esc(t('knowledge.load_error', 'Ошибка')) + '</option>';
    }
  }
  async function addPagePermission() {
    if (!els.permSubjectType || !els.permSubjectId || !els.permAccessLevel || !pageId) return;
    var rawSubjectId = els.permSubjectId.value;
    if (!rawSubjectId) return;
    var btn = els.permAddBtn;
    if (btn) btn.disabled = true;
    var subjectId = parseInt(rawSubjectId, 10);
    var body = { subject_type: els.permSubjectType.value, access_level: els.permAccessLevel.value };
    if (/^\d+$/.test(rawSubjectId) && subjectId > 0) {
      body.subject_id = subjectId;
    } else {
      body.subject_public_id = rawSubjectId;
    }
    try {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/permissions', { method: 'POST', body: body, idempotent: true });
      await loadPagePermissions();
    } catch (e) {}
    if (btn) btn.disabled = false;
  }
  function showEditor(show) {
    var layout = document.querySelector('.crm-knowledge-detail-layout');
    if (layout) layout.classList.toggle('is-editing', show);
    document.body.classList.toggle('crm-knowledge-writing-mode', show);
    els.editor.classList.toggle('d-none', !show);
    els.view.classList.toggle('d-none', show);
    if (show && current) {
      els.editTitle.value = current.title || '';
      if (els.editReviewDue) els.editReviewDue.value = current.review_due_at ? current.review_due_at.substring(0, 10) : '';
      setKnowledgeEditorContent(current.content_html || '<p></p>');
      if (els.visualEditor) {
        els.visualEditor.innerHTML = sanitizePreviewHtml(current.content_html || '<p></p>');
      }
      updateEditorPreview();
      window.setTimeout(focusVisualEditor, 80);
    }
  }
  function render(page) {
    current = page;
    els.state.classList.add('d-none');
    els.view.classList.remove('d-none');
    els.title.textContent = page.title || '';
    els.space.textContent = page.space_title || '';
    var statusMap = { draft: 'crm-badge-secondary', review: 'crm-badge-warning', published: 'crm-badge-success', archived: 'crm-badge-light', needs_update: 'crm-badge-danger' };
    var statusLabels = { draft: t('knowledge.status_draft', 'Черновик'), review: t('knowledge.status_review', 'На проверке'), published: t('knowledge.status_published', 'Опубликовано'), archived: t('knowledge.status_archived', 'В архиве'), needs_update: t('knowledge.status_needs_update', 'Требует обновления') };
    els.status.className = 'crm-badge ' + (statusMap[page.status] || 'crm-badge-secondary');
    els.status.textContent = statusLabels[page.status] || page.status || '';
    var publishBtn = document.getElementById('knowledgePublishBtn');
    var pageStatus = String(page.status || '').toLowerCase().trim();
    var canPublish = pageStatus !== 'published' && pageStatus !== 'archived';
    if (publishBtn) {
      publishBtn.hidden = !canPublish;
      publishBtn.style.display = canPublish ? '' : 'none';
      publishBtn.setAttribute('aria-hidden', canPublish ? 'false' : 'true');
    }
    els.content.innerHTML = renderVisualEditorHtml(page.content_html || '<p class="text-muted">' + esc(t('knowledge_page.empty_content', 'Содержание пока не заполнено.')) + '</p>');
    hydrateVisualEditorReadonly(els.content);
    renderToc();
    els.metaSpace.textContent = page.space_title || '—';
    var typeLabels = { article: t('knowledge.type_article', 'Статья'), instruction: t('knowledge.type_instruction', 'Инструкция'), regulation: t('knowledge.type_regulation', 'Регламент'), faq: t('knowledge.type_faq', 'FAQ'), checklist: t('knowledge.type_checklist', 'Чек-лист'), runbook: t('knowledge.type_runbook', 'Runbook'), meeting_note: t('knowledge.type_meeting_note', 'Протокол'), decision: t('knowledge.type_decision', 'Решение'), client_note: t('knowledge.type_client_note', 'Заметка клиента'), project_note: t('knowledge.type_project_note', 'Заметка проекта'), onboarding: t('knowledge.type_onboarding', 'Онбординг') };
    els.metaType.textContent = typeLabels[page.page_type] || page.page_type || '—';
    els.metaUpdated.textContent = page.updated_at || '—';
    els.metaViews.textContent = String(page.views_count || 0);
    if (els.metaAuthor) els.metaAuthor.textContent = page.author_name || page.created_by_name || page.author || '—';
    els.metaReviewDue.textContent = page.review_due_at ? page.review_due_at.substring(0, 10) : '—';
    updateFavSubButtons();
    loadTree(page);
    loadComments();
    loadAttachments();
  }
  function renderToc() {
    if (!els.tocContainer || !els.toc) return;
    var headings = els.content.querySelectorAll('h2, h3');
    if (!headings.length) {
      els.tocContainer.classList.add('d-none');
      return;
    }
    var html = [];
    headings.forEach(function (h) {
      if (!h.id) h.id = 'toc-heading-' + Math.random().toString(36).substring(2, 8);
      var level = h.tagName === 'H2' ? 'toc-h2' : 'toc-h3';
      html.push('<a class="' + level + '" href="#' + h.id + '">' + esc(h.textContent || '') + '</a>');
    });
    els.toc.innerHTML = html.join('');
    els.tocContainer.classList.remove('d-none');
    els.toc.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        var target = document.getElementById(this.getAttribute('href').substring(1));
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
    initTocScroll();
  }
  function renderVersions(items) {
    if (!items || !items.length) {
      els.versions.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.no_versions', 'Версий пока нет.')) + '</div>';
      return;
    }
    els.versions.innerHTML = items.map(function (item) {
      return '<div class="crm-knowledge-version"><div><strong>v' + esc(item.version_number) + '</strong><span>' + esc(item.created_at || '') + '</span>' + (item.change_summary ? '<br><span class="text-muted">' + esc(item.change_summary) + '</span>' : '') + '</div><div class="d-flex gap-1 mt-1"><button class="btn btn-sm crm-btn-secondary" data-restore-version="' + esc(item.version_number) + '" style="font-size:0.78rem;padding:0.12rem 0.45rem">' + esc(t('knowledge_page.restore', 'Восстановить')) + '</button><button class="btn btn-sm crm-btn-secondary" data-diff-version="' + esc(item.version_number) + '" style="font-size:0.78rem;padding:0.12rem 0.45rem">' + esc(t('knowledge_page.btn_diff', 'Сравнить')) + '</button></div></div>';
    }).join('');
  }
  function setKnowledgeBottomTab(targetPanelId) {
    var buttons = document.querySelectorAll('.crm-knowledge-tab-btn');
    var panels = document.querySelectorAll('.crm-knowledge-tab-panel');
    buttons.forEach(function (btn) {
      var active = btn.getAttribute('data-bs-target') === targetPanelId;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    panels.forEach(function (panel) {
      var active = ('#' + panel.id) === targetPanelId;
      panel.classList.toggle('is-active', active);
      panel.hidden = !active;
    });
  }
  document.querySelectorAll('.crm-knowledge-tab-btn').forEach(function (btn) {
    btn.addEventListener('click', function (event) {
      event.preventDefault();
      setKnowledgeBottomTab(btn.getAttribute('data-bs-target') || '#kb-panel-comments');
    });
  });
  setKnowledgeBottomTab('#kb-panel-comments');
  function updateFavSubButtons() {
    isFav = current && current.is_favorited ? true : false;
    isSub = current && current.is_subscribed ? true : false;
    var favLabel = document.getElementById('knowledgeFavLabel');
    var subLabel = document.getElementById('knowledgeSubLabel');
    if (favLabel) favLabel.textContent = isFav ? t('knowledge_page.favorite_remove', 'Из избранного') : t('knowledge_page.favorite_add', 'В избранное');
    if (subLabel) subLabel.textContent = isSub ? t('knowledge_page.unsubscribe', 'Отписаться') : t('knowledge_page.subscribe', 'Подписаться');
  }
  async function loadTree(page) {
    if (!page || !page.space_public_id) { els.tree.innerHTML = '<div class="text-muted small">—</div>'; return; }
    try {
      var envelope = await request('api/v1/knowledge/spaces/' + encodeURIComponent(page.space_public_id) + '/tree', { method: 'GET', query: { depth: 10 } });
      var items = envelope.data && envelope.data.items || [];
      els.tree.innerHTML = renderTreeNodes(items);
      initTreeToggles();
    } catch (e) { els.tree.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.load_error', 'Ошибка')) + '</div>'; }
  }
  function initTreeToggles() {
    if (!els.tree) return;
    els.tree.querySelectorAll('[data-tree-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var li = btn.closest('.crm-knowledge-tree-item');
        if (!li) return;
        var isOpen = btn.classList.contains('is-open');
        btn.classList.toggle('is-open', !isOpen);
        li.classList.toggle('is-collapsed', isOpen);
      });
    });
    els.tree.querySelectorAll('.crm-knowledge-tree-item').forEach(function (li) {
      var active = li.querySelector('.crm-knowledge-tree-active');
      if (!active) return;
      var parent = li.parentElement;
      while (parent && parent !== els.tree) {
        if (parent.classList && parent.classList.contains('crm-knowledge-tree-item')) {
          parent.classList.remove('is-collapsed');
          var toggle = parent.querySelector(':scope > .crm-knowledge-tree-item-row > [data-tree-toggle]');
          if (toggle) toggle.classList.add('is-open');
        }
        parent = parent.parentElement;
      }
    });
  }
  function renderTreeNodes(nodes, depth) {
    if (!nodes || !nodes.length) return '';
    depth = depth || 0;
    var html = '<ul class="crm-knowledge-tree-list">';
    nodes.forEach(function (n) {
      var active = n.public_id === pageId ? ' crm-knowledge-tree-active' : '';
      var hasChildren = n.children && n.children.length;
      var toggleHtml = hasChildren ? '<button type="button" class="crm-knowledge-tree-toggle is-open" data-tree-toggle aria-label="' + esc(t('knowledge_page.tree_toggle_aria', 'Toggle')) + '"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>' : '';
      html += '<li class="crm-knowledge-tree-item' + active + '">';
      html += '<div class="crm-knowledge-tree-item-row">' + toggleHtml + '<a href="index.php?route=knowledge-page&amp;id=' + esc(n.public_id) + '">' + esc(n.title) + '</a></div>';
      if (hasChildren) html += renderTreeNodes(n.children, depth + 1);
      html += '</li>';
    });
    html += '</ul>';
    return html;
  }
  async function loadComments() {
    if (!pageId) return;
    try {
      var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/comments', { method: 'GET' });
      var items = envelope.data && envelope.data.items || [];
      renderComments(items);
    } catch (e) { els.commentsList.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.load_error', 'Ошибка')) + '</div>'; }
  }
  var replyToId = null;
  var replyToName = '';
  function renderComments(items) {
    if (!items || !items.length) {
      els.commentsList.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.comments_empty', 'Комментариев пока нет')) + '</div>';
      return;
    }
    var byParent = {};
    items.forEach(function (c) { var pid = c.parent_id || 'root'; if (!byParent[pid]) byParent[pid] = []; byParent[pid].push(c); });
    function renderThread(parentId, depth) {
      var kids = byParent[parentId] || [];
      if (!kids.length) return '';
      return kids.map(function (c) {
        var hasChildren = byParent[c.id] && byParent[c.id].length > 0;
        var resolved = c.resolved_at ? ' <span class="text-success small">' + esc(t('knowledge_page.comments_resolved', ' resolved')) + '</span>' : '';
        var resolveBtn = c.resolved_at
          ? '<button class="btn btn-sm crm-btn-secondary" data-comment-reopen="' + esc(c.public_id) + '" style="font-size:0.7rem">' + esc(t('knowledge_page.comments_reopen', 'Открыть')) + '</button>'
          : '<button class="btn btn-sm crm-btn-secondary" data-comment-resolve="' + esc(c.public_id) + '" style="font-size:0.7rem">' + esc(t('knowledge_page.comments_resolve', 'Решено')) + '</button>';
        var replyBtn = '<button class="btn btn-sm crm-btn-secondary" data-comment-reply="' + esc(c.public_id) + '" data-comment-reply-name="' + esc(c.user_name || '') + '" style="font-size:0.7rem">' + esc(t('knowledge_page.comments_reply', 'Ответить')) + '</button>';
        var childrenHtml = renderThread(c.id, depth + 1);
        var replyTo = depth > 0 && c.parent_user_name ? ' <span class="crm-comment-reply-to">' + esc(t('knowledge_page.comments_reply_to', 'reply to')) + ' <span class="crm-comment-reply-name">@' + esc(c.parent_user_name) + '</span></span>' : '';
        var cls = 'crm-knowledge-comment' + (c.resolved_at ? ' crm-knowledge-comment-resolved' : '') + (hasChildren ? ' crm-knowledge-comment-has-children' : '') + (depth > 0 ? ' crm-knowledge-comment-nested' : '');
        return '<div class="' + cls + '" data-depth="' + depth + '"><div class="crm-knowledge-comment-inner"><div class="crm-knowledge-comment-head"><strong>' + esc(c.user_name || t('common.unknown', 'Неизвестно')) + '</strong><span class="text-muted small">' + esc(c.created_at || '') + '</span>' + replyTo + resolved + '</div><div class="crm-knowledge-comment-body">' + renderVisualEditorHtml(c.body) + '</div><div class="crm-knowledge-comment-actions">' + replyBtn + resolveBtn + '</div></div>' + childrenHtml + '</div>';
      }).join('');
    }
    els.commentsList.innerHTML = renderThread('root', 0);
    hydrateVisualEditorReadonly(els.commentsList);
  }
  function renderVisualEditorHtml(value) {
    var text = String(value || '').trim();
    if (!text) return '';
    if (/<[a-z][\s\S]*>/i.test(text) && window.CRM.VisualEditor && typeof window.CRM.VisualEditor.sanitizeHtml === 'function') {
      return window.CRM.VisualEditor.sanitizeHtml(text);
    }
    return esc(text).replace(/\n/g, '<br>');
  }
  function hydrateVisualEditorReadonly(root) {
    if (window.CRM.VisualEditor && typeof window.CRM.VisualEditor.renderReadonly === 'function') {
      window.CRM.VisualEditor.renderReadonly(root);
    }
  }
  async function loadAttachments() {
    if (!pageId) return;
    try {
      var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/files', { method: 'GET' });
      renderAttachments(envelope.data && envelope.data.items || []);
    } catch (e) { els.attachList.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.load_error', 'Ошибка')) + '</div>'; }
  }
  function renderAttachments(items) {
    if (!items || !items.length) {
      els.attachList.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.attachments_empty', 'Файлов пока нет')) + '</div>';
      return;
    }
    els.attachList.innerHTML = '<ul class="crm-knowledge-attach-list">' + items.map(function (f) {
      var isImage = (f.mime_type || '').indexOf('image/') === 0;
      var sizeLabel = f.size_bytes >= 1048576 ? (f.size_bytes / 1048576).toFixed(1) + ' MB' : f.size_bytes >= 1024 ? (f.size_bytes / 1024).toFixed(0) + ' KB' : f.size_bytes + ' B';
      var fileUrl = buildUrl('api/v1/files/' + encodeURIComponent(String(f.public_id || '')) + '/download');
      var preview = isImage ? '<a href="' + esc(fileUrl) + '" target="_blank" rel="noopener"><img src="' + esc(fileUrl) + '" alt="' + esc(f.original_name) + '" style="max-width:120px;max-height:80px;border-radius:4px;object-fit:cover;display:block;margin-bottom:4px" loading="lazy"></a>' : '';
      return '<li>' + preview + '<div class="crm-knowledge-attach-info"><a href="' + esc(fileUrl) + '" target="_blank" rel="noopener">' + esc(f.original_name) + '</a> <span class="text-muted small">(' + sizeLabel + ')</span> <button class="btn btn-sm crm-btn-danger-soft" data-attach-delete="' + esc(f.public_id) + '" style="font-size:0.7rem">' + esc(t('knowledge_page.attachments_delete', 'Удалить')) + '</button></div></li>';
    }).join('') + '</ul>';
  }
  async function uploadFile(file) {
    var formData = new FormData();
    formData.append('file', file);
    formData.append('entity_type', 'knowledge_page');
    formData.append('entity_public_id', pageId);
    try {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/files', { method: 'POST', body: formData, idempotent: true });
      await loadAttachments();
    } catch (e) {}
  }
  async function load() {
    if (!pageId) {
      els.state.textContent = t('knowledge_page.no_id', 'Не указан материал базы знаний.');
      return;
    }
    try {
      var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId), { method: 'GET' });
      render(envelope.data && envelope.data.page || {});
      var versions = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/versions', { method: 'GET' });
      renderVersions(versions.data && versions.data.items || []);
    } catch (e) {
      els.state.textContent = t('knowledge_page.load_error', 'Не удалось загрузить материал.');
    }
  }
  async function patch(body) {
    var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId), { method: 'PATCH', body: Object.assign({ row_version: current && current.row_version }, body) });
    render(envelope.data && envelope.data.page || {});
  }
  function startAutosave() {
    if (autoTimer) clearTimeout(autoTimer);
    autoTimer = setTimeout(function () {
      if (els.editor.classList.contains('d-none')) return;
      syncHiddenContent();
      if (els.autosaveStatus) els.autosaveStatus.textContent = t('knowledge_page.autosave_saving', 'Сохранение...');
      var draftBody = { title: els.editTitle.value, content_html: getTextareaVisualEditorValue(els.editContent) };
      if (els.editReviewDue) {
        var rv = els.editReviewDue.value;
        if (rv) draftBody.review_due_at = rv;
      }
      request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/draft', {
        method: 'POST', body: draftBody, idempotent: true
      }).then(function () {
        if (els.autosaveStatus) els.autosaveStatus.textContent = t('knowledge_page.autosave_saved', 'Сохранено');
      }).catch(function () {
        if (els.autosaveStatus) els.autosaveStatus.textContent = t('knowledge_page.autosave_error', 'Ошибка сохранения');
      });
    }, 3000);
  }
  document.getElementById('knowledgeEditBtn').addEventListener('click', function () { showEditor(true); });
  document.getElementById('knowledgeCancelEditBtn').addEventListener('click', function () { showEditor(false); });
  document.getElementById('knowledgeSaveDraftBtn').addEventListener('click', async function () {
    syncHiddenContent();
    var draftBody = { title: els.editTitle.value, content_html: getTextareaVisualEditorValue(els.editContent) };
    if (els.editReviewDue) {
      var rv = els.editReviewDue.value;
      if (rv) draftBody.review_due_at = rv;
    }
    await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/draft', { method: 'POST', body: draftBody, idempotent: true });
    if (els.autosaveStatus) els.autosaveStatus.textContent = t('knowledge_page.autosave_saved', 'Сохранено');
  });
  els.editContent && els.editContent.addEventListener('input', function () { updateEditorPreview(); startAutosave(); });
  els.editContent && els.editContent.addEventListener('change', function () { updateEditorPreview(); startAutosave(); });
  els.visualEditor && els.visualEditor.addEventListener('input', function () { syncHiddenContent(); startAutosave(); });
  els.visualEditor && els.visualEditor.addEventListener('keyup', function () { rememberEditorSelection(); updateToolbarState(); });
  els.visualEditor && els.visualEditor.addEventListener('mouseup', function () { rememberEditorSelection(); updateToolbarState(); });
  els.visualEditor && els.visualEditor.addEventListener('focus', function () { rememberEditorSelection(); updateToolbarState(); });
  els.visualEditor && els.visualEditor.addEventListener('keydown', function (e) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      document.getElementById('knowledgeSaveDraftBtn').click();
      return;
    }
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
      e.preventDefault();
      els.editor.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
      return;
    }
    if (e.key === '/') {
      e.preventDefault();
      openBlockMenu();
      return;
    }
    if (e.key === 'Escape') {
      closeBlockMenu();
    }
  });
  els.editTitle && els.editTitle.addEventListener('input', startAutosave);
  els.blockAddBtn && els.blockAddBtn.addEventListener('click', function () {
    if (!els.blockMenu) return;
    if (els.blockMenu.classList.contains('d-none')) openBlockMenu();
    else closeBlockMenu();
  });
  els.blockSearch && els.blockSearch.addEventListener('input', function () { filterBlockMenu(els.blockSearch.value); });
  els.blockSearch && els.blockSearch.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      e.preventDefault();
      closeBlockMenu();
      return;
    }
    if (e.key === 'Enter') {
      e.preventDefault();
      var firstVisible = Array.prototype.slice.call(els.blockMenu.querySelectorAll('[data-editor-block]')).find(function (btn) {
        return btn.style.display !== 'none';
      });
      if (firstVisible) firstVisible.click();
    }
  });
  document.addEventListener('click', function (e) {
    if (!els.blockMenu || els.blockMenu.classList.contains('d-none')) return;
    if (e.target.closest('#knowledgeBlockMenu') || e.target.closest('#knowledgeBlockAddBtn')) return;
    els.blockMenu.classList.add('d-none');
  });
  document.querySelectorAll('[data-editor-block]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var type = btn.getAttribute('data-editor-block');
      if (els.blockMenu) els.blockMenu.classList.add('d-none');
      if (type === 'p') insertVisualHtml('<p>' + esc(t('knowledge_page.block_text', 'Текст')) + '</p>');
      if (type === 'h2') insertVisualHtml('<h2>' + esc(t('knowledge_page.block_h2', 'Подзаголовок H2')) + '</h2>');
      if (type === 'h3') insertVisualHtml('<h3>' + esc(t('knowledge_page.block_h3', 'Подзаголовок H3')) + '</h3>');
      if (type === 'ul') insertVisualHtml('<ul><li>' + esc(t('knowledge_page.ul', 'Список')) + '</li></ul>');
      if (type === 'blockquote') insertVisualHtml('<blockquote><p>' + esc(t('knowledge_page.blockquote', 'Цитата')) + '</p></blockquote>');
      if (type === 'link') {
        var url = prompt(t('knowledge_page.link_prompt', 'Введите URL:'), 'https://');
        if (url) insertVisualHtml('<p><a href="' + esc(url) + '">' + esc(url) + '</a></p>');
      }
    });
  });
  document.addEventListener('selectionchange', updateToolbarState);
  els.editor.addEventListener('submit', async function (event) {
    event.preventDefault();
    syncHiddenContent();
    var patchBody = { title: els.editTitle.value, content_html: getTextareaVisualEditorValue(els.editContent) };
    if (els.editReviewDue) {
      var rv = els.editReviewDue.value;
      if (rv) patchBody.review_due_at = rv;
    }
    await patch(patchBody);
    showEditor(false);
  });
  document.getElementById('knowledgePublishBtn').addEventListener('click', async function () {
    var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/publish', { method: 'POST', body: {}, idempotent: true });
    render(envelope.data && envelope.data.page || {});
    load();
  });
  document.addEventListener('click', function (e) {
    var exportItem = e.target.closest('[data-export-format]');
    if (exportItem && current) {
      e.preventDefault();
      var fmt = exportItem.getAttribute('data-export-format') || 'json';
      (async function () {
        try {
          var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/export?format=' + fmt, { method: 'GET' });
          var data = envelope.data || {};
          var filename = data.filename || (current.slug || pageId) + '.' + fmt;
          var blobContent = fmt === 'json' ? JSON.stringify(data, null, 2) : (data.content || '');
          var blob = new Blob([blobContent], { type: fmt === 'json' ? 'application/json' : 'text/markdown' });
          var url = URL.createObjectURL(blob);
          var a = document.createElement('a');
          a.href = url; a.download = filename;
          document.body.appendChild(a); a.click();
          document.body.removeChild(a);
          URL.revokeObjectURL(url);
        } catch (e) { alert(t('knowledge_page.export_error', 'Ошибка экспорта')); }
      })();
      return;
    }
  });
  document.getElementById('knowledgeReviewBtn').addEventListener('click', async function () {
    await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/request-review', { method: 'POST', body: {}, idempotent: true });
    load();
  });
  document.getElementById('knowledgeArchiveBtn').addEventListener('click', async function () {
    await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/archive', { method: 'POST', body: {}, idempotent: true });
    load();
  });
  els.favBtn.addEventListener('click', async function () {
    if (isFav) {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/favorite', { method: 'DELETE', idempotent: true });
    } else {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/favorite', { method: 'POST', idempotent: true });
    }
    isFav = !isFav;
    updateFavSubButtons();
  });
  els.subBtn.addEventListener('click', async function () {
    if (isSub) {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/subscribe', { method: 'DELETE', idempotent: true });
    } else {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/subscribe', { method: 'POST', idempotent: true });
    }
    isSub = !isSub;
    updateFavSubButtons();
  });
  els.commentSend.addEventListener('click', async function () {
    var body = getTextareaVisualEditorValue(els.commentInput).trim();
    if (!body) return;
    els.commentInput.disabled = true;
    try {
      var payload = { body: body };
      if (replyToId) payload.parent_public_id = replyToId;
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/comments', { method: 'POST', body: payload, idempotent: true });
      els.commentInput.value = '';
      if (window.CRM.VisualEditor && typeof window.CRM.VisualEditor.refreshEditors === 'function') {
        window.CRM.VisualEditor.refreshEditors(document.getElementById('kb-panel-comments') || document, true);
      }
      replyToId = null; replyToName = '';
      updateReplyIndicator();
      loadComments();
    } catch (e) {}
    els.commentInput.disabled = false;
    els.commentInput.focus();
  });
  function updateReplyIndicator() {
    var indicator = document.getElementById('knowledgeCommentReplyIndicator');
    var label = document.getElementById('knowledgeCommentReplyLabel');
    if (!indicator || !label) return;
    if (replyToId) {
      indicator.classList.remove('d-none');
      label.textContent = t('knowledge_page.comments_reply_to', 'Ответ на') + ' ' + esc(replyToName);
    } else {
      indicator.classList.add('d-none');
    }
  }
  document.getElementById('knowledgeCommentCancelReply').addEventListener('click', function () {
    replyToId = null; replyToName = '';
    updateReplyIndicator();
    els.commentInput.focus();
  });
  els.commentInput && els.commentInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); els.commentSend.click(); }
  });
  els.attachUploadBtn && els.attachUploadBtn.addEventListener('click', async function () {
    var files = els.attachInput && els.attachInput.files;
    if (!files || !files.length) return;
    for (var i = 0; i < files.length; i++) { await uploadFile(files[i]); }
    els.attachInput.value = '';
  });
  document.addEventListener('click', function (e) {
    var restoreBtn = e.target.closest('[data-restore-version]');
    if (restoreBtn) {
      var v = restoreBtn.getAttribute('data-restore-version');
      if (v && confirm(t('knowledge_page.restore_confirm', 'Восстановить эту версию? Текущее содержимое будет заменено.'))) {
        request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/versions/' + encodeURIComponent(v) + '/restore', { method: 'POST', idempotent: true }).then(function () { load(); });
      }
      return;
    }
    var diffBtn = e.target.closest('[data-diff-version]');
    if (diffBtn) {
      var vNum = parseInt(diffBtn.getAttribute('data-diff-version'), 10);
      if (current) {
        request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/versions/diff', { method: 'GET', query: { from: vNum - 1, to: vNum } }).then(function (envelope) {
          var diff = envelope.data || {};
          els.diffContainer.classList.remove('d-none');
          els.diffContent.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.diff_version', 'Версия')) + ' ' + vNum + ': ' + esc(diff.text_changed ? t('knowledge_page.diff_changed', 'Есть изменения') : t('knowledge_page.diff_unchanged', 'Без изменений')) + '</div>';
        }).catch(function () {
          els.diffContainer.classList.remove('d-none');
          els.diffContent.innerHTML = '<div class="text-danger small">' + esc(t('knowledge_page.diff_error', 'Ошибка загрузки сравнения')) + '</div>';
        });
      }
      return;
    }
    var resolveBtn = e.target.closest('[data-comment-resolve]');
    if (resolveBtn) {
      request('api/v1/knowledge/comments/' + encodeURIComponent(resolveBtn.getAttribute('data-comment-resolve')) + '/resolve', { method: 'POST', idempotent: true }).then(loadComments);
      return;
    }
    var reopenBtn = e.target.closest('[data-comment-reopen]');
    if (reopenBtn) {
      request('api/v1/knowledge/comments/' + encodeURIComponent(reopenBtn.getAttribute('data-comment-reopen')) + '/reopen', { method: 'POST', idempotent: true }).then(loadComments);
      return;
    }
    var replyBtn = e.target.closest('[data-comment-reply]');
    if (replyBtn) {
      replyToId = replyBtn.getAttribute('data-comment-reply');
      replyToName = replyBtn.getAttribute('data-comment-reply-name') || '';
      updateReplyIndicator();
      els.commentInput.focus();
      return;
    }
    var delBtn = e.target.closest('[data-attach-delete]');
    if (delBtn) {
      var fId = delBtn.getAttribute('data-attach-delete');
      if (fId && confirm(t('knowledge_page.attachments_delete_confirm', 'Удалить этот файл?'))) {
        request('api/v1/knowledge/files/' + encodeURIComponent(fId), { method: 'DELETE', idempotent: true }).then(loadAttachments);
      }
      return;
    }
    var pagePermDelBtn = e.target.closest('[data-page-perm-delete]');
    if (pagePermDelBtn) {
      var permId = pagePermDelBtn.getAttribute('data-page-perm-delete');
      if (permId && confirm(t('admin_knowledge.permissions_delete_confirm', 'Удалить это право доступа?'))) {
        request('api/v1/knowledge/page-permissions/' + encodeURIComponent(permId), { method: 'DELETE', idempotent: true }).then(loadPagePermissions);
      }
    }
  });

  // ===== AI SECTION =====
  var aiBtnIds = {
    summary: 'knowledgeAiSummaryBtn',
    explain: 'knowledgeAiExplainBtn',
    checklist: 'knowledgeAiChecklistBtn',
    similar: 'knowledgeAiSimilarBtn',
    'faq-from-comments': 'knowledgeAiFaqBtn'
  };
  var aiBtnLabels = {
    summary: function () { return t('knowledge_page.btn_ai_summary', 'Краткое содержание'); },
    explain: function () { return t('knowledge_page.btn_ai_explain', 'Объяснить проще'); },
    checklist: function () { return t('knowledge_page.btn_ai_checklist', 'Чеклист'); },
    similar: function () { return t('knowledge_page.btn_ai_similar', 'Похожие страницы'); },
    'faq-from-comments': function () { return t('knowledge_page.btn_ai_faq', 'FAQ из комментариев'); }
  };
  var aiBtnOriginalHtml = {};
  var aiBtnIcons = {
    summary: 'fa-regular fa-message',
    explain: 'fa-regular fa-lightbulb',
    checklist: 'fa-solid fa-list-check',
    similar: 'fa-regular fa-copy',
    'faq-from-comments': 'fa-regular fa-circle-question'
  };
  var aiResultTitles = {
    summary: function () { return t('knowledge_page.ai_summary_title', 'Краткое содержание'); },
    explain: function () { return t('knowledge_page.ai_explain_title', 'Объяснение'); },
    checklist: function () { return t('knowledge_page.ai_checklist_title', 'Чеклист'); },
    similar: function () { return t('knowledge_page.ai_similar_title', 'Похожие страницы'); },
    'faq-from-comments': function () { return t('knowledge_page.ai_faq_title', 'FAQ из комментариев'); }
  };
  var aiActionTypes = {
    summary: 'text',
    explain: 'text',
    checklist: 'checklist',
    similar: 'links',
    'faq-from-comments': 'faq'
  };

  var modalEl = document.getElementById('knowledgeAiModal');
  var modalTitleEl = document.getElementById('knowledgeAiModalTitle');
  var modalBodyEl = document.getElementById('knowledgeAiModalBody');
  var modalLoaderEl = document.getElementById('knowledgeAiModalLoader');
  var sidebarResultEl = document.getElementById('knowledgeAiResult');
  var sidebarTitleEl = document.getElementById('knowledgeAiResultTitle');
  var sidebarBodyEl = document.getElementById('knowledgeAiResultBody');

  var lastAiData = null;
  var lastAiAction = null;
  var aiInProgress = false;

  function showAiLoading(show) {
    if (show) {
      if (modalLoaderEl) modalLoaderEl.classList.remove('d-none');
      if (modalBodyEl) modalBodyEl.classList.add('d-none');
    } else {
      if (modalLoaderEl) modalLoaderEl.classList.add('d-none');
      if (modalBodyEl) modalBodyEl.classList.remove('d-none');
    }
  }

  function renderAiInSidebar(action, data) {
    if (!sidebarResultEl || !sidebarTitleEl || !sidebarBodyEl) return;
    sidebarResultEl.classList.remove('d-none');
    sidebarTitleEl.textContent = (aiResultTitles[action] || function () { return ''; })();

    if (action === 'similar') {
      var items = data.items || [];
      if (!items.length) {
        sidebarBodyEl.innerHTML = '<em>' + esc(t('knowledge_page.ai_no_similar', 'Похожих страниц не найдено')) + '</em>';
      } else {
        sidebarBodyEl.innerHTML = items.slice(0, 5).map(function (item) {
          return '<div class="mb-1"><a href="index.php?route=knowledge-page&amp;id=' + esc(item.public_id) + '" class="text-decoration-none">' + esc(item.title) + '</a></div>';
        }).join('') + (items.length > 5 ? '<div class="text-muted small">+' + (items.length - 5) + ' ' + esc(t('knowledge_page.ai_more', 'ещё')) + '</div>' : '');
      }
    } else if (action === 'checklist') {
      var checklistItems = data.items || [];
      if (!checklistItems.length) {
        sidebarBodyEl.innerHTML = '<em>' + esc(t('knowledge_page.ai_no_result', 'Нет результата')) + '</em>';
      } else {
        sidebarBodyEl.innerHTML = '<ul class="mb-0 ps-3">' + checklistItems.slice(0, 4).map(function (item) {
          return '<li style="font-size:0.75rem"><label><input type="checkbox" class="me-1">' + esc(item) + '</label></li>';
        }).join('') + (checklistItems.length > 4 ? '<li class="text-muted small">+' + (checklistItems.length - 4) + ' ' + esc(t('knowledge_page.ai_more', 'ещё')) + '</li>' : '') + '</ul>';
      }
    } else if (action === 'faq-from-comments') {
      var faqItems = data.items || [];
      if (!faqItems.length) {
        sidebarBodyEl.innerHTML = '<em>' + esc(t('knowledge_page.ai_no_result', 'Нет результата')) + '</em>';
      } else {
        sidebarBodyEl.innerHTML = '<div class="small">' + faqItems.slice(0, 2).map(function (item) {
          return '<div class="mb-1"><strong>' + esc(item.question || '') + '</strong></div>';
        }).join('') + (faqItems.length > 2 ? '<div class="text-muted">+' + (faqItems.length - 2) + ' ' + esc(t('knowledge_page.ai_more', 'ещё')) + '</div>' : '') + '</div>';
      }
    } else {
      var text = data.summary || data.explanation || data.text || '';
      var isFallbackOrError = data.mode === 'fallback' || data.mode === 'error';
      sidebarBodyEl.innerHTML = text ? '<div style="font-size:0.75rem;line-height:1.4">' + (isFallbackOrError ? '<span class="text-muted" style="font-size:0.65rem">' + (data.mode === 'error' ? esc(t('knowledge_page.ai_error_short', 'Ошибка AI')) : esc(t('knowledge_page.ai_fallback_note_short', 'Структура документа'))) + '</span><br>' : '') + textToHtml(text.substring(0, 300)) + (text.length > 300 ? '<span class="text-muted">…</span>' : '') + '</div>' : '<em>' + esc(t('knowledge_page.ai_no_result', 'Нет результата')) + '</em>';
    }
  }

  function textToHtml(text) {
    if (!text) return '';
    // Escape HTML first
    var safe = esc(text);
    // Convert markdown-style formatting
    // Bold: **text** or __text__
    safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    safe = safe.replace(/__(.+?)__/g, '<strong>$1</strong>');
    // Italic: *text* or _text_ (bold already converted, so remaining * are italic)
    safe = safe.replace(/\*(\S(?:.*?\S)?)\*/g, '<em>$1</em>');
    safe = safe.replace(/_(\S(?:.*?\S)?)_/g, '<em>$1</em>');
    // Inline code: `code`
    safe = safe.replace(/`([^`]+)`/g, '<code>$1</code>');
    // URLs
    safe = safe.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');

    var lines = safe.split('\n');
    var html = [];
    var inUl = false;
    var inOl = false;
    var inParagraph = false;

    function closeUl() { if (inUl) { html.push('</ul>'); inUl = false; } }
    function closeOl() { if (inOl) { html.push('</ol>'); inOl = false; } }
    function closeParagraph() { if (inParagraph) { html.push('</p>'); inParagraph = false; } }

    for (var i = 0; i < lines.length; i++) {
      var line = lines[i];
      var trimmed = line.trim();

      // Empty line = paragraph break
      if (trimmed === '') {
        closeUl(); closeOl(); closeParagraph();
        continue;
      }

      // Unordered list item
      if (/^[-*]\s/.test(trimmed)) {
        closeOl(); closeParagraph();
        if (!inUl) { html.push('<ul>'); inUl = true; }
        html.push('<li>' + trimmed.substring(2).trim() + '</li>');
        continue;
      }

      // Ordered list item
      if (/^\d+\.\s/.test(trimmed)) {
        closeUl(); closeParagraph();
        if (!inOl) { html.push('<ol>'); inOl = true; }
        html.push('<li>' + trimmed.replace(/^\d+\.\s*/, '') + '</li>');
        continue;
      }

      // Heading-like: ## or ### or **text** as heading
      if (/^#{1,3}\s/.test(trimmed)) {
        closeUl(); closeOl(); closeParagraph();
        var level = trimmed.match(/^#+/)[0].length;
        var hContent = trimmed.replace(/^#+\s*/, '');
        html.push('<h' + Math.min(level + 1, 4) + '>' + hContent + '</h' + Math.min(level + 1, 4) + '>');
        continue;
      }

      // Regular paragraph line
      closeUl(); closeOl();
      if (!inParagraph) { html.push('<p>'); inParagraph = true; }
      else { html.push('<br>'); }
      html.push(line);
    }

    closeUl(); closeOl(); closeParagraph();
    return html.join('\n');
  }

  function renderAiInModal(action, data) {
    if (!modalBodyEl) return;
    showAiLoading(false);
    var title = (aiResultTitles[action] || function () { return ''; })();
    if (modalTitleEl) modalTitleEl.textContent = title;

    if (action === 'similar') {
      var items = data.items || [];
      if (!items.length) {
        modalBodyEl.innerHTML = '<div class="crm-ai-empty"><i class="fa-regular fa-copy" aria-hidden="true"></i><p>' + esc(t('knowledge_page.ai_no_similar', 'Похожих страниц не найдено')) + '</p></div>';
      } else {
        modalBodyEl.innerHTML = '<div class="crm-ai-similar-list">' + items.map(function (item) {
          return '<a class="crm-ai-similar-item" href="index.php?route=knowledge-page&amp;id=' + esc(item.public_id) + '"><strong>' + esc(item.title) + '</strong><span class="crm-badge crm-badge-secondary">' + esc(item.page_type) + '</span><span class="crm-ai-similar-space">' + esc(item.space_title || '') + '</span></a>';
        }).join('') + '</div>';
      }
    } else if (action === 'checklist') {
      var checklistItems = data.items || [];
      if (!checklistItems.length) {
        modalBodyEl.innerHTML = '<div class="crm-ai-empty"><i class="fa-solid fa-list-check" aria-hidden="true"></i><p>' + esc(t('knowledge_page.ai_no_result', 'Нет результата')) + '</p></div>';
      } else {
        modalBodyEl.innerHTML = '<div class="crm-ai-checklist"><ul>' + checklistItems.map(function (item) {
          return '<li><label><input type="checkbox" class="me-2">' + esc(item) + '</label></li>';
        }).join('') + '</ul></div>';
      }
    } else if (action === 'faq-from-comments') {
      var faqItems = data.items || [];
      if (!faqItems.length) {
        modalBodyEl.innerHTML = '<div class="crm-ai-empty"><i class="fa-solid fa-circle-question" aria-hidden="true"></i><p>' + esc(t('knowledge_page.ai_no_result', 'Нет результата')) + '</p></div>';
      } else {
        modalBodyEl.innerHTML = '<div class="crm-ai-faq">' + faqItems.map(function (item) {
          return '<details class="crm-ai-faq-item" open><summary><strong>' + esc(item.question || '') + '</strong></summary><p>' + esc(item.answer || '') + '</p></details>';
        }).join('') + '</div>';
      }
    } else if (data.mode === 'error' && data.error_details) {
      var errDetails = data.error_details;
      var errCode = errDetails.code || '';
      var errMsg = errDetails.message || '';
      var errCategory = errDetails.category || '';
      var errHttpStatus = errDetails.http_status || 0;
      var retryable = errDetails.retryable !== false;

      var categoryLabels = {
        'configuration': t('knowledge_page.ai_error_config', 'Ошибка конфигурации'),
        'auth': t('knowledge_page.ai_error_auth', 'Ошибка авторизации'),
        'billing': t('knowledge_page.ai_error_billing', 'Проблема с оплатой'),
        'rate_limited': t('knowledge_page.ai_error_rate', 'Превышен лимит запросов'),
        'network': t('knowledge_page.ai_error_network', 'Ошибка сети'),
        'provider_error': t('knowledge_page.ai_error_provider', 'Ошибка провайдера'),
        'http_error': t('knowledge_page.ai_error_http', 'HTTP ошибка'),
        'invalid_response': t('knowledge_page.ai_error_response', 'Некорректный ответ')
      };
      var categoryLabel = categoryLabels[errCategory] || errCategory || t('knowledge_page.ai_error_unknown', 'Неизвестная ошибка');

      var errorHtml = '<div class="crm-ai-error">';
      errorHtml += '<div class="crm-ai-error-icon"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i></div>';
      errorHtml += '<h4>' + esc(categoryLabel) + '</h4>';
      if (errMsg) errorHtml += '<p class="crm-ai-error-msg">' + esc(errMsg) + '</p>';
      errorHtml += '<div class="crm-ai-error-details small text-muted">';
      if (errCode) errorHtml += '<span class="crm-ai-error-code">' + esc(t('knowledge_page.ai_error_code_prefix', 'Код: ')) + esc(errCode) + '</span>';
      if (errHttpStatus) errorHtml += '<span class="crm-ai-error-http">HTTP: ' + errHttpStatus + '</span>';
      if (!retryable) errorHtml += '<span class="crm-ai-error-nonretryable">' + esc(t('knowledge_page.ai_error_nonretryable', 'Требуется настройка')) + '</span>';
      errorHtml += '</div>';
      errorHtml += '<div class="crm-ai-error-actions mt-2">';
      errorHtml += '<a href="index.php?route=admin-ai" class="btn btn-sm crm-btn-secondary"><i class="fa-solid fa-gear" aria-hidden="true"></i> ' + esc(t('knowledge_page.ai_check_settings', 'Проверить настройки AI')) + '</a>';
      errorHtml += '</div></div>';
      modalBodyEl.innerHTML = errorHtml;

      // Hide copy/insert buttons, show fallback note
      var insertBtn = document.getElementById('knowledgeAiModalInsertBtn');
      if (insertBtn) insertBtn.style.display = 'none';
    } else {
      var text = data.summary || data.explanation || data.text || '';
      if (text) {
        var fallbackNote = '';
        if (data.mode === 'fallback') {
          fallbackNote = '<div class="crm-ai-fallback-note"><i class="fa-solid fa-info-circle" aria-hidden="true"></i> ' + esc(t('knowledge_page.ai_fallback_note', 'AI временно недоступен. Показана структура документа.')) + '</div>';
        }
        modalBodyEl.innerHTML = fallbackNote + '<div class="crm-ai-text-content">' + textToHtml(text) + '</div>';
      } else {
        modalBodyEl.innerHTML = '<div class="crm-ai-empty"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><p>' + esc(t('knowledge_page.ai_no_result', 'Нет результата')) + '</p></div>';
      }
    }
  }

  function getAiPlainText(action, data) {
    if (!data) return '';
    if (action === 'similar') {
      var items = data.items || [];
      return items.map(function (item) { return item.title + ' (' + item.page_type + ')'; }).join('\n');
    } else if (action === 'checklist') {
      var items = data.items || [];
      return items.map(function (item) { return '[ ] ' + item; }).join('\n');
    } else if (action === 'faq-from-comments') {
      var items = data.items || [];
      return items.map(function (item) { return 'Q: ' + (item.question || '') + '\nA: ' + (item.answer || ''); }).join('\n\n');
    }
    return data.summary || data.explanation || data.text || '';
  }

  function getAiInsertHtml(action, data) {
    if (!data) return '';
    if (action === 'checklist') {
      var items = data.items || [];
      return items.map(function (item) { return '<p><label><input type="checkbox"> ' + esc(item) + '</label></p>'; }).join('\n');
    } else if (action === 'faq-from-comments') {
      var items = data.items || [];
      return items.map(function (item) {
        return '<h3>' + esc(item.question || '') + '</h3><p>' + esc(item.answer || '') + '</p>';
      }).join('\n');
    } else if (action === 'summary') {
      var text = data.summary || data.text || '';
      return text ? '<blockquote><p><strong>' + esc(t('knowledge_page.ai_summary_title', 'Краткое содержание')) + '</strong></p><p>' + esc(text) + '</p></blockquote>' : '';
    } else if (action === 'explain') {
      var text = data.explanation || data.text || '';
      return text ? '<blockquote><p><strong>' + esc(t('knowledge_page.ai_explain_title', 'Объяснение')) + '</strong></p><p>' + esc(text) + '</p></blockquote>' : '';
    }
    return '';
  }

  async function handleAiAction(action) {
    if (aiInProgress) return;
    aiInProgress = true;

    var btn = document.getElementById(aiBtnIds[action] || '');
    if (btn) {
      if (!aiBtnOriginalHtml[action]) aiBtnOriginalHtml[action] = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="crm-ai-btn-spinner" style="display:inline-flex;align-items:center;justify-content:center;width:100%"></span>';
    }

    if (modalEl && modalBodyEl) {
      modalBodyEl.innerHTML = '';
      modalBodyEl.classList.add('d-none');
      showAiLoading(true);
      if (modalTitleEl) modalTitleEl.textContent = t('knowledge_page.ai_loading_title', 'AI обрабатывает…');
      var modal = window.bootstrap && bootstrap.Modal.getOrCreateInstance(modalEl);
      if (modal) modal.show();
    }

    try {
      var url = 'api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/ai/' + action;
      var envelope = await request(url, { method: 'POST', body: {} });
      var data = envelope.data || {};

      lastAiData = data;
      lastAiAction = action;

      renderAiInModal(action, data);
      renderAiInSidebar(action, data);

      // Update insert button visibility
      var insertBtn = document.getElementById('knowledgeAiModalInsertBtn');
      if (insertBtn) {
        var isEditing = els.editor && !els.editor.classList.contains('d-none');
        var canInsert = action === 'checklist' || action === 'faq-from-comments' || action === 'summary' || action === 'explain';
        insertBtn.style.display = (isEditing && canInsert) ? '' : 'none';
      }
    } catch (e) {
      lastAiData = null;
      lastAiAction = null;
      var errMsg = e && e.message || '';
      var errStatus = e && e.status || '';
      if (modalBodyEl) {
        showAiLoading(false);
        modalBodyEl.innerHTML = '<div class="crm-ai-empty" style="color:var(--crm-danger)"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><p>' + esc(t('knowledge_page.ai_error', 'Ошибка AI')) + '</p>' + (errMsg ? '<p class="small text-muted">' + esc(errMsg) + (errStatus ? ' (HTTP ' + errStatus + ')' : '') + '</p>' : '<p class="small text-muted">' + esc(t('knowledge_page.ai_error_unknown', 'Неизвестная ошибка. Проверьте настройки AI-провайдера.')) + '</p>') + '<a href="index.php?route=admin-ai" class="btn btn-sm crm-btn-secondary mt-2"><i class="fa-solid fa-gear" aria-hidden="true"></i> ' + esc(t('knowledge_page.ai_check_settings', 'Проверить настройки AI')) + '</a></div>';
      }
      if (sidebarBodyEl) {
        sidebarBodyEl.innerHTML = '<em class="text-danger">' + esc(t('knowledge_page.ai_error', 'Ошибка AI')) + (errMsg ? ': ' + esc(errMsg) : '') + '</em>';
      }
    }

    if (btn) {
      btn.disabled = false;
      btn.innerHTML = aiBtnOriginalHtml[action] || btn.innerHTML;
    }
    aiInProgress = false;
  }

  // AI button click handlers
  document.getElementById('knowledgeAiSummaryBtn') && document.getElementById('knowledgeAiSummaryBtn').addEventListener('click', function () { handleAiAction('summary'); });
  document.getElementById('knowledgeAiExplainBtn') && document.getElementById('knowledgeAiExplainBtn').addEventListener('click', function () { handleAiAction('explain'); });
  document.getElementById('knowledgeAiChecklistBtn') && document.getElementById('knowledgeAiChecklistBtn').addEventListener('click', function () { handleAiAction('checklist'); });
  document.getElementById('knowledgeAiSimilarBtn') && document.getElementById('knowledgeAiSimilarBtn').addEventListener('click', function () { handleAiAction('similar'); });
  document.getElementById('knowledgeAiFaqBtn') && document.getElementById('knowledgeAiFaqBtn').addEventListener('click', function () { handleAiAction('faq-from-comments'); });

  // Modal copy button
  document.getElementById('knowledgeAiModalCopyBtn') && document.getElementById('knowledgeAiModalCopyBtn').addEventListener('click', function () {
    var text = getAiPlainText(lastAiAction, lastAiData);
    if (!text) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        var btn = document.getElementById('knowledgeAiModalCopyBtn');
        if (btn) { btn.innerHTML = '<i class="fa-regular fa-check-circle" style="margin-right:0.3rem" aria-hidden="true"></i>' + esc(t('knowledge_page.ai_copied', 'Скопировано')); }
        window.setTimeout(function () {
          if (btn) btn.innerHTML = '<i class="fa-regular fa-clipboard" style="margin-right:0.3rem" aria-hidden="true"></i>' + esc(t('knowledge_page.ai_copy', 'Копировать'));
        }, 2000);
      });
    } else {
      // Fallback
      var ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    }
  });

  // Modal insert button
  document.getElementById('knowledgeAiModalInsertBtn') && document.getElementById('knowledgeAiModalInsertBtn').addEventListener('click', function () {
    var html = getAiInsertHtml(lastAiAction, lastAiData);
    if (!html) return;
    if (els.visualEditor) {
      insertVisualHtml(html);
    } else if (els.editContent) {
      els.editContent.value += '\n' + html;
      els.editContent.dispatchEvent(new Event('input', { bubbles: true }));
    }
    var btn = document.getElementById('knowledgeAiModalInsertBtn');
    if (btn) { btn.innerHTML = '<i class="fa-regular fa-check-circle" style="margin-right:0.3rem" aria-hidden="true"></i>' + esc(t('knowledge_page.ai_inserted', 'Вставлено')); }
    window.setTimeout(function () {
      if (btn) btn.style.display = 'none';
    }, 1500);
  });

  // Modal refresh button
  document.getElementById('knowledgeAiModalRefreshBtn') && document.getElementById('knowledgeAiModalRefreshBtn').addEventListener('click', function () {
    if (lastAiAction) handleAiAction(lastAiAction);
  });

  var allTags = [];
  async function loadTagOptions() {
    try {
      var env = await request('api/v1/tags', { method: 'GET' });
      allTags = env.data && env.data.items || [];
    } catch (e) { allTags = []; }
  }
  async function loadTags() {
    if (!els.tagsList || !pageId) return;
    try {
      var env = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/tags', { method: 'GET' });
      var items = env.data && env.data.items || [];
      if (!items.length) {
        els.tagsList.innerHTML = '<span class="text-muted kb-tags-empty"><?= htmlspecialchars($t('knowledge_page.no_tags', 'Теги не добавлены'), ENT_QUOTES, 'UTF-8') ?></span>';
      } else {
        els.tagsList.innerHTML = items.map(function (tag) {
          var color = tag.color || '#6c757d';
          return '<span class="crm-badge" style="background:' + color + ';color:#fff;cursor:default">' + esc(tag.title || tag.code || '') + ' <i class="fa-solid fa-xmark" style="cursor:pointer;margin-left:2px" data-tag-id="' + esc(tag.public_id || '') + '" title="' + esc(t('knowledge_page.tag_remove', 'Удалить тег')) + '" aria-hidden="true"></i></span>';
        }).join('');
      }
      var usedIds = {};
      items.forEach(function (tag) { usedIds[tag.public_id] = true; });
      if (els.tagSelect) {
        els.tagSelect.innerHTML = '<option value="">' + esc(t('knowledge_page.tag_select_hint', 'Выбрать тег...')) + '</option>'
          + allTags.filter(function (tag) { return !usedIds[tag.public_id]; }).map(function (tag) {
            return '<option value="' + esc(tag.public_id) + '">' + esc(tag.title || tag.code || '') + '</option>';
          }).join('');
        els.tagAddBtn.disabled = true;
      }
    } catch (e) { els.tagsList.innerHTML = '<span class="text-muted kb-tags-empty"><?= htmlspecialchars($t('knowledge_page.no_tags', 'Теги не добавлены'), ENT_QUOTES, 'UTF-8') ?></span>'; }
  }
  if (els.tagSelect) els.tagSelect.addEventListener('change', function () {
    els.tagAddBtn.disabled = !els.tagSelect.value;
  });
  if (els.tagAddBtn) els.tagAddBtn.addEventListener('click', async function () {
    var tagId = els.tagSelect && els.tagSelect.value;
    if (!tagId || !pageId) return;
    try {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/tags/' + encodeURIComponent(tagId), { method: 'POST', idempotent: true });
      await loadTags();
    } catch (e) {}
  });
  if (els.tagsList) els.tagsList.addEventListener('click', async function (e) {
    var delIcon = e.target.closest('[data-tag-id]');
    if (!delIcon || !pageId) return;
    var tagId = delIcon.getAttribute('data-tag-id');
    if (!tagId) return;
    try {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/tags/' + encodeURIComponent(tagId), { method: 'DELETE', idempotent: true });
      await loadTags();
    } catch (e) {}
  });
  if (els.permBtn) els.permBtn.addEventListener('click', function () {
    loadPagePermissions();
    loadPagePermissionSubjects(els.permSubjectType ? els.permSubjectType.value : 'user');
    var modal = window.bootstrap && bootstrap.Modal.getOrCreateInstance(els.permModal);
    modal && modal.show();
  });
  if (els.permSubjectType) els.permSubjectType.addEventListener('change', function () {
    loadPagePermissionSubjects(this.value);
  });
  if (els.permAddBtn) els.permAddBtn.addEventListener('click', function () {
    addPagePermission().catch(function () {});
  });
  var tocScrollHandler = null;
  function initTocScroll() {
    if (tocScrollHandler) document.removeEventListener('scroll', tocScrollHandler);
    if (!els.toc) return;
    var links = els.toc.querySelectorAll('a');
    if (!links.length) return;
    tocScrollHandler = function () {
      var active = '';
      links.forEach(function (link) {
        var id = link.getAttribute('href') && link.getAttribute('href').substring(1);
        if (!id) return;
        var el = document.getElementById(id);
        if (el) {
          var rect = el.getBoundingClientRect();
          if (rect.top < 120) active = id;
        }
      });
      links.forEach(function (link) {
        var id = link.getAttribute('href') && link.getAttribute('href').substring(1);
        link.classList.toggle('toc-active', id === active);
      });
    };
    document.addEventListener('scroll', tocScrollHandler, { passive: true });
    tocScrollHandler();
  }
  waitForApi(async function () {
    await loadTagOptions();
    await load();
    await loadTags();
  });
})();
</script>
</body>
