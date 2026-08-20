(function () {
    'use strict';

    const API_PREFIX = '_module/crm.drawio';
    const EDITOR_URL = 'https://embed.diagrams.net/?embed=1&ui=atlas&spin=1&modified=unsavedChanges&proto=json';
    const VIEWER_URL = 'https://embed.diagrams.net/?embed=1&ui=atlas&spin=1&proto=json&noSaveBtn=1&saveAndExit=0&noExitBtn=1';

    let state = {
        editingId: null,
        frame: null,
        pendingXml: null,
        loadedXml: null,
    };

    function isDrawioPage() {
        return Boolean(
            document.body
            && document.body.dataset
            && document.body.dataset.page === 'module-drawio'
            && document.getElementById('diagramsList')
        );
    }

    function init() {
        if (!isDrawioPage()) return;

        document.getElementById('newDiagramBtn')?.addEventListener('click', function () { openEditor(null); });
        document.getElementById('backToListBtn')?.addEventListener('click', backToList);
        document.getElementById('saveDiagramBtn')?.addEventListener('click', saveDiagram);
        document.getElementById('diagramSearch')?.addEventListener('input', filterDiagrams);

        window.addEventListener('message', onFrameMessage);

        const viewId = new URLSearchParams(window.location.search).get('view');
        if (viewId) {
            openViewer(viewId);
        } else {
            loadDiagrams();
        }
    }

    function openViewer(publicId) {
        api('/diagrams/' + publicId, 'GET').then(function (data) {
            const d = data.diagram || data || {};
            document.getElementById('drawioList').classList.add('d-none');
            document.getElementById('drawioEditor').classList.remove('d-none');
            document.getElementById('saveDiagramBtn').classList.add('d-none');
            document.getElementById('backToListBtn').classList.add('d-none');
            document.getElementById('diagramTitleInput').classList.add('d-none');
            document.getElementById('diagramPageInput').classList.add('d-none');
            mountFrame((d.xml_content || ''), true);
        }).catch(function (err) {
            document.getElementById('diagramsList').innerHTML = '<div class="text-danger py-3">' + htmlEscape(err.message) + '</div>';
        });
    }

    function api(path, method, body) {
        return window.CRM.api.request(API_PREFIX + path, { method: method, body: body })
            .then(function (env) { return env.data || {}; });
    }

    // --- List ---

    function loadDiagrams() {
        const container = document.getElementById('diagramsList');
        container.innerHTML = '<div class="text-muted py-3">Загрузка...</div>';

        api('/diagrams', 'GET').then(function (data) {
            const diagrams = data.diagrams || [];
            if (diagrams.length === 0) {
                container.innerHTML = '<div class="text-muted py-3">Нет диаграмм. Нажмите «Новая диаграмма».</div>';
                return;
            }
            container.innerHTML = '<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr>' +
                '<th>Название</th><th>Страница</th><th>Обновлено</th><th class="text-end">Действия</th>' +
                '</tr></thead><tbody>' +
                diagrams.map(function (d) {
                    return '<tr data-title="' + htmlEscape((d.title || '').toLowerCase()) + '">' +
                        '<td><strong>' + htmlEscape(d.title) + '</strong></td>' +
                        '<td><code class="text-muted">' + htmlEscape(d.page_public_id || '—') + '</code></td>' +
                        '<td class="text-muted">' + htmlEscape(d.updated_at || '') + '</td>' +
                        '<td class="text-end"><div class="btn-group">' +
                        '<button class="btn btn-sm crm-btn-secondary edit-diagram-btn" data-id="' + htmlEscape(d.public_id) + '"><i class="fa-solid fa-pen"></i></button>' +
                        '<button class="btn btn-sm crm-btn-secondary embed-diagram-btn" data-id="' + htmlEscape(d.public_id) + '" data-title="' + htmlEscape(d.title) + '"><i class="fa-solid fa-code"></i></button>' +
                        '<button class="btn btn-sm crm-btn-danger-soft delete-diagram-btn" data-id="' + htmlEscape(d.public_id) + '"><i class="fa-solid fa-trash"></i></button>' +
                        '</div></td></tr>';
                }).join('') +
                '</tbody></table></div>';

            container.querySelectorAll('.edit-diagram-btn').forEach(function (btn) {
                btn.addEventListener('click', function () { openEditor(btn.dataset.id); });
            });
            container.querySelectorAll('.embed-diagram-btn').forEach(function (btn) {
                btn.addEventListener('click', function () { showEmbedSnippet(btn.dataset.id, btn.dataset.title); });
            });
            container.querySelectorAll('.delete-diagram-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (confirm(window.CRM.i18n.t('drawio.confirm_delete_diagram', 'Удалить диаграмму?'))) {
                        api('/diagrams/' + btn.dataset.id, 'DELETE').then(function () { loadDiagrams(); })
                            .catch(function (err) { alert(err.message); });
                    }
                });
            });
        }).catch(function (err) {
            container.innerHTML = '<div class="text-danger py-3">' + htmlEscape(err.message) + '</div>';
        });
    }

    function filterDiagrams() {
        const q = document.getElementById('diagramSearch').value.toLowerCase();
        document.querySelectorAll('#diagramsList tbody tr').forEach(function (tr) {
            tr.style.display = (tr.dataset.title || '').indexOf(q) !== -1 ? '' : 'none';
        });
    }

    // --- Editor ---

    function openEditor(publicId) {
        state.editingId = publicId;
        state.pendingXml = null;
        state.loadedXml = null;
        document.getElementById('drawioList').classList.add('d-none');
        document.getElementById('drawioEditor').classList.remove('d-none');

        if (publicId) {
            api('/diagrams/' + publicId, 'GET').then(function (data) {
                const d = data.diagram || data || {};
                document.getElementById('diagramTitleInput').value = d.title || '';
                document.getElementById('diagramPageInput').value = d.page_public_id || '';
                mountFrame(d.xml_content || '');
            }).catch(function (err) {
                alert(err.message);
                backToList();
            });
        } else {
            document.getElementById('diagramTitleInput').value = '';
            document.getElementById('diagramPageInput').value = '';
            mountFrame('');
        }
    }

    function mountFrame(xml, viewOnly) {
        const wrap = document.getElementById('drawioFrameWrap');
        wrap.innerHTML = '';

        state.frame = document.createElement('iframe');
        state.frame.setAttribute('src', viewOnly ? VIEWER_URL : EDITOR_URL);
        state.frame.setAttribute('width', '100%');
        state.frame.setAttribute('height', '100%');
        state.frame.setAttribute('style', 'border:0; min-height: 70vh;');
        state.frame.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-forms allow-downloads allow-popups');
        state.frame.setAttribute('referrerpolicy', 'no-referrer');
        state.pendingXml = xml || '';
        wrap.appendChild(state.frame);
    }

    function onFrameMessage(event) {
        if (!state.frame || event.source !== state.frame.contentWindow) {
            return;
        }

        // The draw.io embed editor (proto=json) posts its messages as JSON
        // *strings* (see the official jgraph/drawio-integration example, which
        // parses with JSON.parse(evt.data)). Accept both plain objects and
        // stringified JSON so events like init/save/export are never missed.
        let raw = event.data;
        if (typeof raw === 'string' && raw.length > 0) {
            try {
                raw = JSON.parse(raw);
            } catch (e) {
                return;
            }
        }
        if (typeof raw !== 'object' || raw === null) {
            return;
        }

        const msg = raw;

        if (msg.event === 'init') {
            // Load the pending XML once the editor is ready.
            const xml = state.pendingXml || defaultDiagramXml();
            state.frame.contentWindow.postMessage(JSON.stringify({ action: 'load', xml: xml }), '*');
            return;
        }

        if (msg.event === 'save' && typeof msg.xml === 'string') {
            state.loadedXml = msg.xml;
        }

        if (msg.event === 'export') {
            // For format 'xml' the raw diagram XML comes back in msg.xml; keep
            // msg.data as a fallback for other formats.
            state.loadedXml = (typeof msg.xml === 'string' && msg.xml) ? msg.xml : (typeof msg.data === 'string' ? msg.data : '');
            persistDiagram();
        }
    }

    function saveDiagram() {
        if (!state.frame) return;
        // Ask the editor to export the current diagram as raw XML (fastest and
        // reloads cleanly back into the editor on next open).
        state.frame.contentWindow.postMessage(JSON.stringify({ action: 'export', format: 'xml', spin: 'Сохранение...' }), '*');
    }

    function persistDiagram() {
        const title = document.getElementById('diagramTitleInput').value.trim();
        const page = document.getElementById('diagramPageInput').value.trim();
        const xml = state.loadedXml || state.pendingXml || '';

        if (!title) {
            alert(window.CRM.i18n.t('drawio.title_required', 'Укажите название диаграммы.'));
            return;
        }
        if (!xml) {
            alert(window.CRM.i18n.t('drawio.diagram_empty', 'Диаграмма пуста.'));
            return;
        }

        const body = { title: title, page_public_id: page || null, xml_content: xml };

        const action = state.editingId
            ? api('/diagrams/' + state.editingId, 'PATCH', body)
            : api('/diagrams', 'POST', body);

        action.then(function () {
            backToList();
            loadDiagrams();
        }).catch(function (err) {
            alert(err.message);
        });
    }

    function backToList() {
        state.editingId = null;
        state.frame = null;
        state.pendingXml = null;
        state.loadedXml = null;
        document.getElementById('drawioEditor').classList.add('d-none');
        document.getElementById('drawioList').classList.remove('d-none');
    }

    function showEmbedSnippet(publicId, title) {
        // Build a same-site viewer URL from the current page location, then wrap
        // it in an iframe snippet the user can paste into a knowledge page.
        const base = window.location.href.split('#')[0].split('?')[0];
        const viewUrl = base + '?route=module-drawio&view=' + encodeURIComponent(publicId);
        const snippet = '<iframe src="' + viewUrl + '" width="100%" height="600" style="border:0" title="' + htmlEscape(title) + '"></iframe>';

        prompt(window.CRM.i18n.t('drawio.embed_prompt', 'Вставьте этот код в HTML-содержимое страницы базы знаний:'), snippet);
    }

    function defaultDiagramXml() {
        return '<mxfile><diagram name="Page-1"><mxGraphModel><root>' +
            '<mxCell id="0"/><mxCell id="1" parent="0"/></root></mxGraphModel></diagram></mxfile>';
    }

    function htmlEscape(str) {
        if (typeof str !== 'string') return String(str || '');
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
