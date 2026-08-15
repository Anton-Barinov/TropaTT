<body data-page="module-drawio" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page">

    <div class="crm-page-head">
        <div>
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="nav.admin"><?= htmlspecialchars($t('nav.admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?> · Диаграммы draw.io</li>
            </ol>
            <h1 class="crm-page-title">Диаграммы draw.io</h1>
            <p class="crm-subtitle">Создание и встраивание диаграмм draw.io в страницы базы знаний</p>
        </div>
    </div>

    <!-- List view -->
    <div id="drawioList">
        <div class="crm-card mb-3">
            <div class="crm-card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Диаграммы</h5>
                <button class="btn crm-btn-primary" id="newDiagramBtn">
                    <i class="fa-solid fa-plus"></i> Новая диаграмма
                </button>
            </div>
            <div class="crm-card-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="diagramSearch" placeholder="Поиск по названию...">
                </div>
                <div id="diagramsList">
                    <div class="text-muted py-3">Загрузка...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Editor view -->
    <div id="drawioEditor" class="d-none">
        <div class="crm-card mb-3">
            <div class="crm-card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button class="btn crm-btn-secondary" id="backToListBtn">
                        <i class="fa-solid fa-arrow-left"></i> Назад
                    </button>
                    <input type="text" class="form-control" id="diagramTitleInput" placeholder="Название диаграммы" style="max-width: 320px;">
                    <input type="text" class="form-control" id="diagramPageInput" placeholder="page_public_id (необязательно)" style="max-width: 320px;">
                </div>
                <div>
                    <button class="btn crm-btn-success" id="saveDiagramBtn">
                        <i class="fa-solid fa-floppy-disk"></i> Сохранить
                    </button>
                </div>
            </div>
            <div class="crm-card-body p-0">
                <div id="drawioFrameWrap" style="height: 70vh;">
                    <div class="text-muted p-4">Загрузка редактора draw.io...</div>
                </div>
            </div>
        </div>
    </div>

</main></div></div>
</body>
