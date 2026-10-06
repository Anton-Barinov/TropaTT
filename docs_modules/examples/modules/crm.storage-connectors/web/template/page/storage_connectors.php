<?php
/** @var array<string, mixed> $data */
?>
<div class="crm-page-header">
    <div class="crm-page-header-title">
        <h1 class="crm-page-title">☁️ Внешние хранилища файлов</h1>
        <p class="crm-page-subtitle">Подключение внешних S3-совместимых бакетов и Nextcloud/WebDAV с сохранением локального fallback</p>
    </div>
</div>

<div class="crm-storage-container">
    <div class="crm-card mb-4" id="preflightCard">
        <div class="crm-card-header">
            <h3>Проверка окружения хостинга (Preflight)</h3>
        </div>
        <div class="crm-card-body" id="preflightBody">
            <div class="crm-loading-placeholder">Проверка поддержки cURL, TLS и лимитов памяти...</div>
        </div>
    </div>

    <div class="crm-card" id="configCard">
        <div class="crm-card-header">
            <h3>Настройки внешнего хранилища</h3>
        </div>
        <div class="crm-card-body">
            <form id="storageForm" onsubmit="saveStorageConfig(event)">
                <div class="crm-form-group">
                    <label class="crm-form-label">Тип провайдера</label>
                    <select class="crm-form-select" id="providerType" onchange="toggleProviderFields()">
                        <option value="s3">S3-Compatible (MinIO, Selectel, Timeweb, AWS, Cloudflare R2)</option>
                        <option value="nextcloud_webdav">Nextcloud / WebDAV</option>
                    </select>
                </div>

                <div class="crm-form-group">
                    <label class="crm-form-label">Endpoint URL</label>
                    <input type="url" class="crm-form-input" id="endpointUrl" required placeholder="https://s3.example.com или https://cloud.example.com/remote.php/dav/files/user">
                </div>

                <div class="crm-form-group">
                    <label class="crm-form-label" id="bucketLabel">Имя бакета (Bucket)</label>
                    <input type="text" class="crm-form-input" id="bucketOrPath" required placeholder="crm-storage">
                </div>

                <div id="s3Fields">
                    <div class="crm-form-group">
                        <label class="crm-form-label">Регион (Region)</label>
                        <input type="text" class="crm-form-input" id="region" value="us-east-1">
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-form-label">Access Key ID</label>
                        <input type="text" class="crm-form-input" id="accessKey">
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-form-label">Secret Access Key</label>
                        <input type="password" class="crm-form-input" id="secretKey">
                    </div>
                </div>

                <div id="webdavFields" style="display: none;">
                    <div class="crm-form-group">
                        <label class="crm-form-label">WebDAV Логин</label>
                        <input type="text" class="crm-form-input" id="davUsername">
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-form-label">Пароль приложения</label>
                        <input type="password" class="crm-form-input" id="davPassword">
                    </div>
                </div>

                <div class="crm-form-actions mt-4">
                    <button type="button" class="crm-btn crm-btn-outline" onclick="testStorageConnection()">Тест соединения</button>
                    <button type="submit" class="crm-btn crm-btn-primary">Сохранить настройки</button>
                </div>
            </form>
        </div>
    </div>
</div>
