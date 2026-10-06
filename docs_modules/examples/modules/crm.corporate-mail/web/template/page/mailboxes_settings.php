<?php
/** @var array<int, array<string, mixed>> $mailboxes */
?>
<div class="crm-page-header">
    <div class="crm-page-title">
        <h1>Корпоративная почта</h1>
        <p class="text-muted">Общие почтовые ящики команды и преобразование писем в обращения CRM</p>
    </div>
</div>
<div class="card p-4 mt-3">
    <h5>Подключённые ящики</h5>
    <div class="table-responsive mt-3">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Email / Название</th>
                    <th>Протокол</th>
                    <th>Статус синхронизации</th>
                    <th>Последняя проверка</th>
                    <th>Активен</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($mailboxes)): ?>
                    <tr>
                        <td colspan="5" class="text-muted text-center py-4">Почтовых ящиков пока не подключено.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($mailboxes as $m): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($m['display_name']) ?></strong>
                                <div class="small text-muted"><?= htmlspecialchars($m['email_address']) ?></div>
                            </td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($m['protocol_adapter']) ?></span></td>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($m['sync_status']) ?></span></td>
                            <td><?= htmlspecialchars($m['last_synced_at'] ?? 'Никогда') ?></td>
                            <td><span class="badge <?= $m['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $m['is_active'] ? 'Да' : 'Нет' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
