<?php
declare(strict_types=1);
/** @var array $data */
$titleRaw = (string)($data['title'] ?? 'Публичные формы');
?>
<body data-page="module-public-forms" data-protected="1">
<div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-module-page">
    <?php crm_page_head([
        ['label' => $t('admin_module_detail.link_admin', 'Администрирование'), 'href' => 'index.php?route=admin'],
        ['label' => $t('admin_module_detail.link_modules', 'Модули'), 'href' => 'index.php?route=admin-modules'],
        ['label' => $titleRaw, 'active' => true],
    ], $titleRaw, 'Создавайте публичные формы заявок, публикуйте ссылку и просматривайте обращения в активном рабочем пространстве.', '<button class="btn crm-btn-primary" id="pformCreateBtn" type="button"><i class="fa-solid fa-plus" aria-hidden="true"></i> Создать форму</button>'); ?>

    <div class="alert alert-info mt-3" role="note">
        <strong>Как это работает</strong>
        <p class="mb-0">Новая форма сохраняется как черновик. После проверки полей и текста согласия опубликуйте её и скопируйте публичную ссылку. Ответы доступны только сотрудникам с правом управления клиентами.</p>
    </div>
    <div id="pformMessage" class="small my-3" role="status" aria-live="polite"></div>

    <section class="crm-card mt-3" aria-labelledby="pformListTitle">
        <div class="crm-card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div><h2 id="pformListTitle" class="h5 mb-1">Формы</h2><p class="text-muted mb-0">Формы и публикации только текущего рабочего пространства.</p></div>
                <button class="btn crm-btn-secondary btn-sm" id="pformRefreshBtn" type="button"><i class="fa-solid fa-rotate" aria-hidden="true"></i> Обновить</button>
            </div>
            <div class="table-responsive">
                <table class="table crm-table align-middle mb-0">
                    <thead><tr><th>Название и адрес</th><th>Статус</th><th>Ответы</th><th>Изменена</th><th class="text-end">Действия</th></tr></thead>
                    <tbody id="pformRows"><tr><td colspan="5" class="text-muted">Загрузка форм…</td></tr></tbody>
                </table>
            </div>
            <p id="pformPermissionHint" class="form-text mt-3" hidden>Для просмотра ответов требуется право «Управление клиентами».</p>
        </div>
    </section>

    <section class="crm-card mt-4" aria-labelledby="pformSubmissionsTitle">
        <div class="crm-card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div><h2 id="pformSubmissionsTitle" class="h5 mb-1">Последние обращения</h2><p class="text-muted mb-0">Показаны последние 50 ответов в текущем рабочем пространстве.</p></div>
                <button class="btn crm-btn-secondary btn-sm" id="pformRefreshSubmissionsBtn" type="button">Обновить ответы</button>
            </div>
            <div class="table-responsive">
                <table class="table crm-table align-middle mb-0">
                    <thead><tr><th>Форма</th><th>Ответ клиента</th><th>Получено</th></tr></thead>
                    <tbody id="pformSubmissionRows"><tr><td colspan="3" class="text-muted">Загрузка обращений…</td></tr></tbody>
                </table>
            </div>
        </div>
    </section>

    <dialog id="pformEditor" class="pform-dialog" aria-labelledby="pformEditorTitle">
        <form id="pformEditorForm" class="pform-editor-form">
            <div class="pform-dialog-head"><div><h2 class="h5 mb-1" id="pformEditorTitle">Новая форма</h2><p class="small text-muted mb-0">Обязательные поля отмечены звёздочкой.</p></div><button type="button" class="btn crm-icon-btn" id="pformCloseBtn" aria-label="Закрыть">×</button></div>
            <input type="hidden" id="pformPublicId">
            <div class="row g-3">
                <div class="col-12 col-md-6"><label class="form-label" for="pformTitle">Название формы</label><input class="form-control" id="pformTitle" maxlength="255" required></div>
                <div class="col-12 col-md-6"><label class="form-label" for="pformSlug">Короткий адрес</label><input class="form-control" id="pformSlug" maxlength="64" pattern="[a-z0-9-]+" required><div class="form-text">Латинские строчные буквы, цифры и дефисы. Он будет частью публичной ссылки.</div></div>
                <div class="col-12"><label class="form-label" for="pformDescription">Описание для посетителей</label><textarea class="form-control" id="pformDescription" rows="3" maxlength="2000" aria-describedby="pformDescriptionHelp"></textarea><div class="form-text" id="pformDescriptionHelp">Необязательный текст появится над полями публичной формы. До 2000 символов.</div></div>
                <div class="col-12"><label class="form-label" for="pformConsent">Текст согласия на обработку данных</label><textarea class="form-control" id="pformConsent" rows="2" maxlength="2000" required>Я согласен на обработку персональных данных.</textarea></div>
                <div class="col-12"><label class="form-label" for="pformType">Сценарий</label><select class="form-select" id="pformType"><option value="intake">Заявка во «Входящие»</option><option value="booking">Запись на встречу в календарь</option></select></div>
                <div id="pformRoutingConfig" class="col-12">
                    <fieldset><legend class="form-label">Назначение заявки</legend><div class="row g-3">
                        <div class="col-12 col-md-6"><label class="form-label" for="pformTargetProject">Проект</label><select class="form-select" id="pformTargetProject"><option value="">Не связывать с проектом</option></select><div class="form-text">В список входят только активные проекты текущего рабочего пространства.</div></div>
                        <div class="col-12 col-md-6"><label class="form-label" for="pformTargetAssignee">Ответственный</label><select class="form-select" id="pformTargetAssignee"><option value="">Не назначать</option></select><div class="form-text">Можно назначить только активного участника текущего рабочего пространства.</div></div>
                    </div></fieldset>
                </div>
                <div id="pformBookingConfig" class="col-12" hidden>
                    <div class="row g-3">
                        <div class="col-12 col-md-6"><label class="form-label" for="pformCalendarUser">Публичный ID участника календаря</label><select class="form-select" id="pformCalendarUser"><option value="">Выберите участника</option></select><div class="form-text">Участник должен быть активен и входить в текущее рабочее пространство.</div></div>
                        <div class="col-12 col-md-6"><label class="form-label" for="pformTimezone">Часовой пояс IANA</label><input class="form-control" id="pformTimezone" value="Europe/Moscow" maxlength="64" placeholder="Europe/Moscow"></div>
                        <div class="col-12 col-md-4"><label class="form-label" for="pformSlotDuration">Длительность, минут</label><input class="form-control" id="pformSlotDuration" type="number" min="5" max="480" step="5" value="30"></div>
                        <div class="col-12 col-md-4"><label class="form-label" for="pformBuffer">Перерыв между встречами, минут</label><input class="form-control" id="pformBuffer" type="number" min="0" max="240" step="5" value="15"></div>
                        <div class="col-12"><fieldset><legend class="form-label">Рабочие часы по дням недели</legend><div class="row g-2" id="pformScheduleEditor">
                            <?php foreach ([1=>'Понедельник',2=>'Вторник',3=>'Среда',4=>'Четверг',5=>'Пятница',6=>'Суббота',7=>'Воскресенье'] as $weekday => $dayLabel): ?>
                            <div class="col-12 col-lg-6"><div class="d-flex align-items-center gap-2"><label class="form-check-label flex-grow-1"><input class="form-check-input me-2" type="checkbox" data-schedule-enabled="<?= (int)$weekday ?>"> <?= htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8') ?></label><input class="form-control form-control-sm" type="time" aria-label="Начало рабочего дня" data-schedule-start="<?= (int)$weekday ?>" value="09:00"><span>—</span><input class="form-control form-control-sm" type="time" aria-label="Конец рабочего дня" data-schedule-end="<?= (int)$weekday ?>" value="17:00"></div></div>
                            <?php endforeach; ?>
                        </div><div class="form-text">Расписание действует в выбранном часовом поясе. Каждому дню задаётся один непрерывный интервал.</div></fieldset></div>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center gap-2 mt-4"><h3 class="h6 mb-0">Поля формы</h3><button class="btn crm-btn-secondary btn-sm" type="button" id="pformAddFieldBtn"><i class="fa-solid fa-plus" aria-hidden="true"></i> Добавить поле</button></div>
            <div id="pformFields" class="pform-fields mt-2"></div>
            <div id="pformEditorMessage" class="small mt-3" role="status" aria-live="polite"></div>
            <div class="pform-dialog-actions"><button class="btn crm-btn-secondary" id="pformCancelBtn" type="button">Отмена</button><button class="btn crm-btn-primary" id="pformSaveBtn" type="submit">Сохранить черновик</button></div>
        </form>
    </dialog>
</main></div></div>
