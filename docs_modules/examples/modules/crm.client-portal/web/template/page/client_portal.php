<?php
/** @var array<int, array<string, mixed>> $requests */
?>
<div class="crm-page-header">
    <div class="crm-page-title">
        <h1>Сервисный портал клиента</h1>
        <p class="text-muted">Заявки, согласования и документы сервисного процесса</p>
    </div>
</div>
<div class="card p-4 mt-3">
    <h5>Активные сервисные обращения</h5>
    <div class="table-responsive mt-3">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID / Тема</th>
                    <th>Категория</th>
                    <th>Статус</th>
                    <th>SLA</th>
                    <th>Создано</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="5" class="text-muted text-center py-4">Обращений пока нет.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $req): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($req['title']) ?></strong>
                                <div class="small text-muted"><?= htmlspecialchars($req['public_id']) ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark"><?= htmlspecialchars($req['category']) ?></span></td>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($req['service_status']) ?></span></td>
                            <td><span class="badge bg-success"><?= htmlspecialchars($req['sla_status']) ?></span></td>
                            <td><?= htmlspecialchars($req['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
