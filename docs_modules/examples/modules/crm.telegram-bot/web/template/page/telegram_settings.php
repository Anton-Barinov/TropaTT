<?php
/** @var array<string, mixed>|null $config */
?>
<div class="crm-page-header">
    <div class="crm-page-title">
        <h1>Интеграция с Telegram</h1>
        <p class="text-muted">Бот для клиентских заявок и рабочих уведомлений команды</p>
    </div>
</div>
<div class="card p-4 mt-3">
    <h5>Настройки Telegram Бота</h5>
    <?php if ($config): ?>
        <div class="alert alert-success mt-3">Бот настроен и активен.</div>
        <p><strong>Секретный токен Webhook:</strong> <code><?= htmlspecialchars($config['webhook_secret_token']) ?></code></p>
    <?php else: ?>
        <p class="text-muted mt-3">Бот ещё не настроен для текущей организации.</p>
    <?php endif; ?>
</div>
