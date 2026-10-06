document.addEventListener('DOMContentLoaded', function () {
    loadStarterKits();
});

let currentKitId = null;

function loadStarterKits() {
    const grid = document.getElementById('kitsGrid');
    if (!grid) return;

    fetch('/api/v1/modules/crm.starter-kits/kits')
        .then(res => res.json())
        .then(data => {
            const kits = data?.data?.kits || [];
            if (kits.length === 0) {
                grid.innerHTML = '<div class="crm-empty-state">Нет доступных комплектов</div>';
                return;
            }
            grid.innerHTML = kits.map(k => `
                <div class="crm-kit-card">
                    <div>
                        <div class="crm-kit-card-title">${escapeHtml(k.title)}</div>
                        <div class="crm-kit-card-desc">${escapeHtml(k.description)}</div>
                        <div class="crm-kit-badges">
                            <span class="crm-kit-badge">Статусов: ${k.components_count.statuses}</span>
                            <span class="crm-kit-badge">Ролей: ${k.components_count.roles}</span>
                            <span class="crm-kit-badge">Шаблонов проектов: ${k.components_count.project_templates}</span>
                            <span class="crm-kit-badge">Шаблонов задач: ${k.components_count.task_templates}</span>
                            <span class="crm-kit-badge">База знаний: ${k.components_count.knowledge_spaces}</span>
                        </div>
                    </div>
                    <div>
                        <button type="button" class="crm-btn crm-btn-outline w-100" onclick="openStarterKitPreview('${escapeHtml(k.id)}')">Предпросмотр и настройка</button>
                    </div>
                </div>
            `).join('');
        })
        .catch(() => {
            grid.innerHTML = '<div class="crm-error-state">Ошибка загрузки комплектов</div>';
        });
}

function openStarterKitPreview(kitId) {
    currentKitId = kitId;
    const modal = document.getElementById('previewModal');
    const body = document.getElementById('modalBody');
    if (!modal || !body) return;

    body.innerHTML = '<div class="crm-loading-placeholder">Сверка с текущим рабочим пространством...</div>';
    modal.style.display = 'flex';

    fetch(`/api/v1/modules/crm.starter-kits/preview?kit_id=${encodeURIComponent(kitId)}`)
        .then(res => res.json())
        .then(res => {
            const p = res?.data?.preview;
            if (!p) {
                body.innerHTML = '<div class="crm-error-state">Ошибка получения данных предпросмотра</div>';
                return;
            }
            document.getElementById('modalTitle').textContent = `Предпросмотр: ${p.title}`;

            let conflictsHtml = '';
            const conflictCount = (p.conflicts.statuses.length + p.conflicts.roles.length + p.conflicts.project_templates.length + p.conflicts.task_templates.length + p.conflicts.knowledge_spaces.length);
            if (conflictCount > 0) {
                conflictsHtml = `
                    <div class="crm-conflict-alert">
                        <strong>Внимание:</strong> обнаружено ${conflictCount} совпадений с существующими объектами. Они не будут перезаписаны (пропуск дублей).
                    </div>
                `;
            }

            body.innerHTML = `
                ${conflictsHtml}
                <div class="crm-preview-section">
                    <h4>Новые статусы задач (${p.will_create.statuses.length}):</h4>
                    <p class="text-muted">${p.will_create.statuses.map(s => escapeHtml(s.title)).join(', ') || 'Нет новых'}</p>
                </div>
                <div class="crm-preview-section">
                    <h4>Роли доступа (${p.will_create.roles.length}):</h4>
                    <p class="text-muted">${p.will_create.roles.map(r => escapeHtml(r.title)).join(', ') || 'Нет новых'}</p>
                </div>
                <div class="crm-preview-section">
                    <h4>Шаблоны проектов и задач (${p.will_create.project_templates.length + p.will_create.task_templates.length}):</h4>
                    <p class="text-muted">${[...p.will_create.project_templates, ...p.will_create.task_templates].map(t => escapeHtml(t.title)).join(', ') || 'Нет новых'}</p>
                </div>
                <div class="crm-preview-section">
                    <h4>База знаний (${p.will_create.knowledge_spaces.length}):</h4>
                    <p class="text-muted">${p.will_create.knowledge_spaces.map(s => escapeHtml(s.title)).join(', ') || 'Нет новых'}</p>
                </div>
            `;
        })
        .catch(() => {
            body.innerHTML = '<div class="crm-error-state">Ошибка загрузки предпросмотра</div>';
        });
}

function closeStarterKitModal() {
    const modal = document.getElementById('previewModal');
    if (modal) modal.style.display = 'none';
}

function confirmApplyKit() {
    if (!currentKitId) return;
    const btn = document.getElementById('btnApplyKit');
    if (btn) btn.disabled = true;

    fetch('/api/v1/modules/crm.starter-kits/apply', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({kit_id: currentKitId})
    })
    .then(res => res.json())
    .then(res => {
        if (res?.data?.ok) {
            alert('Комплект успешно применён к рабочему пространству!');
            closeStarterKitModal();
            loadStarterKits();
        } else {
            alert('Ошибка применения комплекта: ' + (res?.message || 'Неизвестная ошибка'));
        }
    })
    .finally(() => {
        if (btn) btn.disabled = false;
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>"']/g, function (m) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[m];
    });
}
