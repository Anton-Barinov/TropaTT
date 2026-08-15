<body data-page="module-raycast" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page">

    <div class="crm-page-head">
        <div>
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="nav.admin"><?= htmlspecialchars($t('nav.admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li>
                <li class="breadcrumb-item active">Raycast (MCP)</li>
            </ol>
            <h1 class="crm-page-title">Raycast (MCP)</h1>
            <p class="crm-subtitle">Подключение Raycast для macOS к MCP-серверу TropaTT</p>
        </div>
    </div>

    <div class="crm-card mb-3">
        <div class="crm-card-header"><h5 class="mb-0">Эндпоинт MCP-сервера</h5></div>
        <div class="crm-card-body">
            <div id="raycastConfig" class="text-muted py-3">Загрузка...</div>
        </div>
    </div>

    <div class="crm-card">
        <div class="crm-card-header"><h5 class="mb-0">Инструкция по настройке</h5></div>
        <div class="crm-card-body">
            <ol id="raycastInstructions" class="mb-0">
                <li>Откройте <strong>Raycast → Settings → Extensions</strong>.</li>
                <li>Найдите расширение <strong>MCP</strong> и нажмите <strong>Add Server</strong>.</li>
                <li>Вставьте URL эндпоинта (выше) и укажите <strong>Bearer-токен</strong> пользователя TropaTT (создаётся в разделе «Настройки → API-клиенты»).</li>
                <li>Сохраните сервер и выберите <strong>TropaTT</strong> в MCP-командах Raycast.</li>
            </ol>
        </div>
    </div>

</main></div></div>
</body>
