<?php
/** @var array<string, mixed> $data */
?>
<div class="crm-page-header">
    <div class="crm-page-header-title">
        <h1 class="crm-page-title">🚀 Стартовые комплекты рабочих пространств</h1>
        <p class="crm-page-subtitle">Быстрое развёртывание типовых процессов для команд без ручной настройки статусов, шаблонов и регламентов</p>
    </div>
</div>

<div class="crm-starter-kits-container" id="starterKitsApp">
    <div class="crm-starter-kits-grid" id="kitsGrid">
        <div class="crm-loading-placeholder">Загрузка доступных комплектов...</div>
    </div>

    <!-- Modal for Preview & Diff -->
    <div class="crm-modal-backdrop" id="previewModal" style="display: none;">
        <div class="crm-modal-dialog">
            <div class="crm-modal-header">
                <h3 id="modalTitle">Предпросмотр комплекта</h3>
                <button type="button" class="crm-modal-close" onclick="closeStarterKitModal()">&times;</button>
            </div>
            <div class="crm-modal-body" id="modalBody">
                <!-- Preview details injected here -->
            </div>
            <div class="crm-modal-footer">
                <button type="button" class="crm-btn crm-btn-secondary" onclick="closeStarterKitModal()">Отмена</button>
                <button type="button" class="crm-btn crm-btn-primary" id="btnApplyKit" onclick="confirmApplyKit()">Применить к рабочему пространству</button>
            </div>
        </div>
    </div>
</div>
