<?php
/** @var array<int, array<string, mixed>> $recipes */
?>
<div class="crm-page-header">
    <div class="crm-page-title">
        <h1>Интеграция с n8n и Make</h1>
        <p class="text-muted">Подключения HTTPS webhook и ручная проверка доставки</p>
    </div>
</div>
<div class="card p-4 mt-3">
    <h5>Настроенные сценарии</h5>
    <ul class="list-group list-group-flush mt-3">
        <?php if (empty($recipes)): ?>
            <li class="list-group-item text-muted">Пока нет настроенных сценариев автоматизации.</li>
        <?php else: ?>
            <?php foreach ($recipes as $r): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars((string)($r['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                        <span class="badge bg-secondary ms-2"><?= htmlspecialchars((string)($r['platform'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        <div class="small text-muted"><?= htmlspecialchars((string)($r['webhook_endpoint_host'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <span class="badge <?= $r['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                        <?= $r['is_active'] ? 'Активен' : 'Отключен' ?>
                    </span>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
</div>
