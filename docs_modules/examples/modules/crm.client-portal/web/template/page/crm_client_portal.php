<?php
declare(strict_types=1);
/** @var array $data */
$titleRaw = (string)($data['title'] ?? 'Клиентский портал');
?>
<body data-page="module-client-portal" data-protected="1">
<div class="crm-app">
    <aside class="crm-sidebar">
        <div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div>
        <nav class="nav flex-column crm-nav"></nav>
    </aside>
    <div class="crm-main-wrap">
        <header class="crm-topbar py-2"><div class="container-fluid"></div></header>
        <main class="crm-content crm-admin-page crm-module-page">
            <?php crm_page_head([
                ['label' => $t('admin_module_detail.link_admin', 'Администрирование'), 'href' => 'index.php?route=admin'],
                ['label' => $t('admin_module_detail.link_modules', 'Модули'), 'href' => 'index.php?route=admin-modules'],
                ['label' => $titleRaw, 'active' => true],
            ], $titleRaw, 'Рабочая очередь обращений клиентов, ответы и внутренние заметки.', '<button class="btn crm-btn-secondary" id="cportRefreshBtn" type="button">Обновить очередь</button>'); ?>

            <div id="cportMessage" class="small mt-3" role="status" aria-live="polite"></div>
            <section class="crm-card mt-3" aria-labelledby="cportQueueHeading">
                <div class="crm-card-body">
                    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
                        <div>
                            <h2 class="h5 mb-1" id="cportQueueHeading">Очередь обращений</h2>
                            <p class="text-muted mb-0">Список ограничен активным рабочим пространством и правами текущего пользователя.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <label class="visually-hidden" for="cportSearch">Поиск по теме и категории</label>
                            <input class="form-control" id="cportSearch" type="search" placeholder="Найти обращение…" autocomplete="off">
                            <label class="visually-hidden" for="cportStatusFilter">Фильтр статуса</label>
                            <select class="form-select" id="cportStatusFilter"><option value="">Все статусы</option></select>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-lg-5">
                            <div class="cport-queue-meta d-flex justify-content-between align-items-center mb-2">
                                <span class="small text-muted">Обращения</span>
                                <span class="badge text-bg-light" id="cportRequestCount">0</span>
                            </div>
                            <div class="list-group cport-request-list" id="cportRequestList" aria-label="Список обращений" aria-live="polite">
                                <div class="list-group-item text-muted">Загрузка очереди…</div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-7">
                            <section class="cport-detail-panel" aria-labelledby="cportDetailTitle">
                                <div class="cport-detail-empty" id="cportDetailEmpty">
                                    <h3 class="h6">Выберите обращение</h3>
                                    <p class="text-muted mb-0">Здесь появятся сведения, переписка и поле для ответа.</p>
                                </div>
                                <div id="cportDetail" hidden>
                                    <header class="cport-request-heading">
                                        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                                            <div>
                                                <div class="small text-muted" id="cportRequestMeta"></div>
                                                <h3 class="h5 mt-1 mb-2" id="cportDetailTitle"></h3>
                                            </div>
                                            <span class="badge text-bg-light" id="cportDetailStatus"></span>
                                        </div>
                                        <div class="small text-muted" id="cportSlaMeta"></div>
                                    </header>
                                    <div class="cport-description" id="cportDescription"></div>
                                    <div class="cport-conversation" id="cportMessages" aria-label="Переписка по обращению" aria-live="polite">
                                        <p class="text-muted mb-0">Загрузка переписки…</p>
                                    </div>
                                    <form id="cportReplyForm" class="cport-reply-form">
                                        <label class="form-label" for="cportReplyText">Ответ или заметка</label>
                                        <textarea class="form-control" id="cportReplyText" rows="3" maxlength="10000" required></textarea>
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
                                            <label class="form-check mb-0">
                                                <input class="form-check-input" id="cportClientVisible" type="checkbox" checked>
                                                <span class="form-check-label">Видно клиенту</span>
                                            </label>
                                            <button class="btn crm-btn-primary" id="cportSendReplyBtn" type="submit">Отправить</button>
                                        </div>
                                    </form>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>
