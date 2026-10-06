<?php
/** @var array<int, array<string, mixed>> $conversations */
?>
<div class="crm-page-header">
    <div class="crm-page-title">
        <h1>Диалоги WhatsApp Business</h1>
        <p class="text-muted">Единая очередь клиентских переписок через официальный Meta Cloud API</p>
    </div>
</div>
<div class="card p-4 mt-3">
    <h5>Активные переписки</h5>
    <div class="table-responsive mt-3">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Клиент / Телефон</th>
                    <th>Заявка Intake</th>
                    <th>Ответственный</th>
                    <th>Последнее сообщение</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($conversations)): ?>
                    <tr>
                        <td colspan="5" class="text-muted text-center py-4">Диалогов пока нет.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($conversations as $c): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($c['contact_name'] ?? 'Без имени') ?></strong>
                                <div class="small text-muted"><?= htmlspecialchars($c['contact_phone']) ?></div>
                            </td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($c['intake_public_id'] ?? '—') ?></span></td>
                            <td><?= htmlspecialchars($c['assigned_user_public_id'] ?? 'Не назначен') ?></td>
                            <td><?= htmlspecialchars($c['last_message_at']) ?></td>
                            <td><span class="badge bg-success"><?= htmlspecialchars($c['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
