document.addEventListener('DOMContentLoaded', function () {
    loadPreflight();
    loadStorageConfig();
});

function loadPreflight() {
    const el = document.getElementById('preflightBody');
    if (!el) return;

    fetch('/api/v1/modules/crm.storage-connectors/preflight')
        .then(r => r.json())
        .then(res => {
            const checks = res?.data?.checks || {};
            el.innerHTML = Object.keys(checks).map(k => `
                <div class="crm-preflight-item">
                    <span><strong>${escapeHtml(k.toUpperCase())}:</strong> ${escapeHtml(checks[k].message)}</span>
                    <span class="crm-preflight-badge ${escapeHtml(checks[k].status)}">${escapeHtml(checks[k].status.toUpperCase())}</span>
                </div>
            `).join('');
        })
        .catch(() => {
            el.innerHTML = '<div class="crm-error-state">Ошибка выполнения preflight-проверки</div>';
        });
}

function loadStorageConfig() {
    fetch('/api/v1/modules/crm.storage-connectors/config')
        .then(r => r.json())
        .then(res => {
            const cfg = res?.data?.config;
            if (!cfg) return;
            document.getElementById('providerType').value = cfg.provider_type || 's3';
            document.getElementById('endpointUrl').value = cfg.endpoint_url || '';
            document.getElementById('bucketOrPath').value = cfg.bucket_or_path || '';
            document.getElementById('region').value = cfg.region || 'us-east-1';
            toggleProviderFields();
        });
}

function toggleProviderFields() {
    const type = document.getElementById('providerType').value;
    const isS3 = type === 's3';
    document.getElementById('s3Fields').style.display = isS3 ? 'block' : 'none';
    document.getElementById('webdavFields').style.display = isS3 ? 'none' : 'block';
    document.getElementById('bucketLabel').textContent = isS3 ? 'Имя бакета (Bucket)' : 'Путь на WebDAV сервере (Remote Path)';
}

function testStorageConnection() {
    fetch('/api/v1/modules/crm.storage-connectors/test-connection', {
        method: 'POST'
    })
    .then(r => r.json())
    .then(res => {
        if (res?.data?.ok) {
            alert('Успешно: ' + res.data.message);
        } else {
            alert('Ошибка соединения: ' + (res?.message || 'Не удалось подключиться'));
        }
    })
    .catch(() => {
        alert('Ошибка при выполнении сетевого теста');
    });
}

function saveStorageConfig(e) {
    e.preventDefault();
    const type = document.getElementById('providerType').value;
    const endpoint = document.getElementById('endpointUrl').value;
    const bucket = document.getElementById('bucketOrPath').value;
    const region = document.getElementById('region').value;

    let creds = {};
    if (type === 's3') {
        creds = {
            access_key: document.getElementById('accessKey').value,
            secret_key: document.getElementById('secretKey').value
        };
    } else {
        creds = {
            username: document.getElementById('davUsername').value,
            password: document.getElementById('davPassword').value
        };
    }

    fetch('/api/v1/modules/crm.storage-connectors/config', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            provider_type: type,
            endpoint_url: endpoint,
            bucket_or_path: bucket,
            region: region,
            credentials: creds
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res?.data?.public_id) {
            alert('Настройки хранилища сохранены!');
        } else {
            alert('Ошибка сохранения: ' + (res?.message || 'Проверьте поля формы'));
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>"']/g, function (m) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[m];
    });
}
