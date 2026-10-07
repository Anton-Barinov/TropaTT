<?php
/** @var array $data */
$title = $data['title'] ?? 'Zoom Видеоконференции';
?>
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800"><?= htmlspecialchars($title) ?></h1>
            <p class="text-muted mb-0">Модуль платформы TropaTT CRM (активен и готов к работе)</p>
        </div>
        <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-circle-check me-1"></i> Активен</span>
    </div>
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="card-title text-primary mb-2"><?= htmlspecialchars($title) ?></h5>
                    <p class="card-text text-secondary mb-3">Интеграционный сервис успешно подключен к ядру системы и доступен пользователям рабочего пространства.</p>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary btn-sm px-3" type="button"><i class="fa-solid fa-gear me-1"></i> Настройки</button>
                        <button class="btn btn-outline-secondary btn-sm px-3" type="button"><i class="fa-solid fa-arrows-rotate me-1"></i> Синхронизация</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
