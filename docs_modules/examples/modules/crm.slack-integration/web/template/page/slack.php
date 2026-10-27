<body data-page="module-slack-integration" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page">

    <div class="crm-page-head">
        <div>
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="nav.admin"><?= htmlspecialchars($t('nav.admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li>
                <li class="breadcrumb-item active">Уведомления в Slack</li>
            </ol>
            <h1 class="crm-page-title">Уведомления в Slack</h1>
            <p class="crm-subtitle">Отправка уведомлений TropaTT в Slack-каналы через Incoming Webhooks</p>
        </div>
    </div>

    <div class="crm-card mb-3">
        <div class="crm-card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Подключения</h5>
            <button class="btn crm-btn-primary" id="addConnectionBtn"><i class="fa-solid fa-plus"></i> Добавить подключение</button>
        </div>
        <div class="crm-card-body">
            <div id="connectionsList"><div class="text-muted py-3">Загрузка...</div></div>
        </div>
    </div>

    <div class="crm-card mb-3">
        <div class="crm-card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Правила-шаблоны</h5>
            <button class="btn crm-btn-secondary" id="addRuleBtn"><i class="fa-solid fa-plus"></i> Добавить правило</button>
        </div>
        <div class="crm-card-body">
            <p class="text-muted small">Правило связывает событие с подключением и шаблоном текста. Для отправки используйте workflow-действие <code>call_webhook</code> с URL <code>.../notify?rule_public_id=...</code> или <code>.../notify?connection_public_id=...</code>. Плейсхолдеры: <code>{event}</code>, <code>{task}</code>, <code>{user}</code>, <code>{status}</code>, <code>{project}</code>.</p>
            <div id="rulesList"><div class="text-muted py-3">Загрузка...</div></div>
        </div>
    </div>

    <div class="crm-card">
        <div class="crm-card-header"><h5 class="mb-0">Последние доставки</h5></div>
        <div class="crm-card-body">
            <div id="deliveriesList"><div class="text-muted py-3">Загрузка...</div></div>
        </div>
    </div>

</main></div></div>

<!-- Connection modal -->
<div class="modal fade" id="connectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Новое подключение</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Название</label>
                    <input type="text" class="form-control" id="connName" placeholder="Канал #sales">
                </div>
                <div class="mb-3">
                    <label class="form-label">Канал (необязательно)</label>
                    <input type="text" class="form-control" id="connChannel" placeholder="#sales">
                </div>
                <div class="mb-3">
                    <label class="form-label">Webhook URL</label>
                    <input type="url" class="form-control" id="connWebhook" placeholder="https://hooks.slack.com/services/T.../B.../...">
                    <div class="form-text">Создайте Incoming Webhook в Slack (Settings → Integrations).</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button class="btn crm-btn-primary" id="saveConnectionBtn">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<!-- Rule modal -->
<div class="modal fade" id="ruleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Новое правило</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Подключение</label>
                    <select class="form-select" id="ruleConnection"></select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Событие</label>
                    <select class="form-select" id="ruleEvent">
                        <optgroup label="Задачи">
                            <option value="task.created">task.created — создана</option>
                            <option value="task.updated">task.updated — изменена</option>
                            <option value="task.status_changed">task.status_changed — сменён статус</option>
                            <option value="task.assignee_changed">task.assignee_changed — сменён исполнитель</option>
                            <option value="task.deleted">task.deleted — удалена</option>
                        </optgroup>
                        <optgroup label="Комментарии и файлы">
                            <option value="comment.added">comment.added — добавлен комментарий</option>
                            <option value="file.uploaded">file.uploaded — загружен файл</option>
                        </optgroup>
                        <optgroup label="Проекты">
                            <option value="project.created">project.created — создан</option>
                            <option value="project.updated">project.updated — изменён</option>
                            <option value="project.deleted">project.deleted — удалён</option>
                        </optgroup>
                        <optgroup label="Пользователи">
                            <option value="user.created">user.created — создан</option>
                            <option value="user.updated">user.updated — изменён</option>
                            <option value="user.deleted">user.deleted — удалён</option>
                        </optgroup>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Шаблон текста</label>
                    <textarea class="form-control" id="ruleText" rows="3" placeholder="[&#123;event&#125;] Задача &#123;task&#125; → статус &#123;status&#125;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button class="btn crm-btn-primary" id="saveRuleBtn">Сохранить</button>
            </div>
        </div>
    </div>
</div>
</body>
