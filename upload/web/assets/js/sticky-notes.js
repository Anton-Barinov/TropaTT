/**
 * Sticky Notes Dashboard Widget
 * Auto-initializes on pages with data-page="dashboard"
 */
window.CRM = window.CRM || {};
window.CRM.stickyNotes = (function () {
  'use strict';

  var state = {
    items: [],
    colors: ['#FFD700', '#90EE90', '#87CEEB', '#FFB6C1', '#DDA0DD', '#FFA07A', '#98FB98', '#D3D3D3'],
    editingId: null
  };

  function t(key, fallback) {
    if (window.CRM.i18n && typeof window.CRM.i18n.t === 'function') {
      return window.CRM.i18n.t(key, fallback);
    }
    return fallback || key;
  }

  function esc(value) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(String(value == null ? '' : value)));
    return d.innerHTML;
  }

  function getApi() {
    return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
  }

  function waitForApi(cb, n) {
    if (getApi()) { cb(); return; }
    if ((n || 0) > 80) return;
    setTimeout(function () { waitForApi(cb, (n || 0) + 1); }, 50);
  }

  function notify(text, type) {
    if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.notify === 'function') {
      window.CRM.pageApiBindings.notify(text, type);
      return;
    }
    if (typeof window.notify === 'function') {
      window.notify(text, type);
      return;
    }
  }

  function req(route, opts) {
    var api = getApi();
    if (!api) return Promise.reject(new Error('API not ready'));
    return api.request(route, opts || {});
  }

  function loadNotes() {
    var list = document.getElementById('stickyNotesList');
    if (!list) return;
    list.innerHTML = '<div class="col-12 text-muted small">' + esc(t('dashboard.sticky_notes_loading', 'Загрузка заметок...')) + '</div>';

    req('api/v1/sticky-notes', { query: { limit: 50 } })
      .then(function (envelope) {
        state.items = (envelope.data && envelope.data.items) || [];
        renderNotes();
      })
      .catch(function () {
        list.innerHTML = '<div class="col-12 text-muted small">' + esc(t('dashboard.sticky_notes_error', 'Не удалось загрузить заметки')) + '</div>';
      });
  }

  function renderNotes() {
    var list = document.getElementById('stickyNotesList');
    if (!list) return;

    var items = state.items.filter(function (n) { return !n.is_archived; });
    if (!items.length) {
      list.innerHTML = '<div class="col-12 crm-empty-state py-2"><p class="mb-2 small">' + esc(t('dashboard.sticky_notes_empty', 'Пока нет заметок')) + '</p><button class="btn crm-btn-primary btn-sm" id="stickyNoteEmptyAddBtn">' + esc(t('dashboard.sticky_notes_add', '+ Добавить заметку')) + '</button></div>';
      var emptyBtn = document.getElementById('stickyNoteEmptyAddBtn');
      if (emptyBtn) emptyBtn.addEventListener('click', function () { openCreateModal(); });
      return;
    }

    // Sort: pinned first, then by sort_order
    items.sort(function (a, b) {
      if (a.is_pinned && !b.is_pinned) return -1;
      if (!a.is_pinned && b.is_pinned) return 1;
      return (a.sort_order || 0) - (b.sort_order || 0);
    });

    list.innerHTML = items.map(function (note) {
      var color = note.color || '#FFD700';
      var isEditing = state.editingId === note.public_id;
      var pinnedClass = note.is_pinned ? ' crm-sticky-pinned' : '';

      return '<div class="col-12 col-sm-6 col-md-4 col-lg-3" data-note-id="' + esc(note.public_id) + '">'
        + '<div class="crm-sticky-note' + pinnedClass + '" style="background:' + esc(color) + ';border-radius:8px;padding:12px;position:relative;min-height:80px;box-shadow:0 1px 3px rgba(0,0,0,0.12)">'
        + (note.is_pinned ? '<span class="crm-sticky-pin-badge" style="position:absolute;top:4px;right:4px;font-size:10px;opacity:0.6">&#128204;</span>' : '')
        + (isEditing
          ? '<textarea class="form-control form-control-sm crm-sticky-edit-textarea" data-note-id="' + esc(note.public_id) + '" rows="3" style="background:transparent;border:1px dashed var(--crm-sticky-dash);width:100%;resize:vertical;font-size:13px">' + esc(note.content) + '</textarea>'
          : '<div class="crm-sticky-content" data-note-id="' + esc(note.public_id) + '" style="cursor:text;font-size:13px;line-height:1.4;white-space:pre-wrap;word-break:break-word;min-height:40px">' + esc(note.content || '') + '</div>')
        + '<div class="crm-sticky-actions mt-2" style="display:flex;gap:4px;flex-wrap:wrap;opacity:0.7">'
        + (isEditing
          ? '<button class="btn btn-sm crm-btn-primary crm-sticky-save-btn" data-note-id="' + esc(note.public_id) + '" style="font-size:11px;padding:2px 8px">' + esc(t('page.save', 'Сохранить')) + '</button>'
            + '<button class="btn btn-sm btn-light crm-sticky-cancel-btn" data-note-id="' + esc(note.public_id) + '" style="font-size:11px;padding:2px 8px">' + esc(t('page.cancel', 'Отмена')) + '</button>'
          : '<button class="btn btn-sm crm-btn-secondary crm-sticky-edit-btn" data-note-id="' + esc(note.public_id) + '" style="font-size:11px;padding:2px 8px">' + esc(t('page.edit', 'Редакт.')) + '</button>')
        + '<button class="btn btn-sm crm-btn-secondary crm-sticky-pin-btn" data-note-id="' + esc(note.public_id) + '" data-pinned="' + (note.is_pinned ? '1' : '0') + '" style="font-size:11px;padding:2px 8px">' + (note.is_pinned ? esc(t('dashboard.sticky_notes_unpin', 'Открепить')) : esc(t('dashboard.sticky_notes_pin', 'Закрепить'))) + '</button>'
        + '<button class="btn btn-sm crm-btn-danger-soft crm-sticky-archive-btn" data-note-id="' + esc(note.public_id) + '" style="font-size:11px;padding:2px 8px">' + esc(t('dashboard.sticky_notes_archive', 'В архив')) + '</button>'
        + '</div></div></div>';
    }).join('');

    bindNoteEvents();
    initDragReorder();
  }

  function bindNoteEvents() {
    // Click on content to edit
    document.querySelectorAll('.crm-sticky-content').forEach(function (el) {
      el.addEventListener('dblclick', function () {
        var id = el.getAttribute('data-note-id');
        startEditing(id);
      });
    });

    // Edit button
    document.querySelectorAll('.crm-sticky-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-note-id');
        startEditing(id);
      });
    });

    // Save button
    document.querySelectorAll('.crm-sticky-save-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-note-id');
        var textarea = document.querySelector('.crm-sticky-edit-textarea[data-note-id="' + id + '"]');
        if (textarea) saveNote(id, textarea.value);
      });
    });

    // Cancel button
    document.querySelectorAll('.crm-sticky-cancel-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.editingId = null;
        renderNotes();
      });
    });

    // Pin/Unpin button
    document.querySelectorAll('.crm-sticky-pin-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-note-id');
        var pinned = btn.getAttribute('data-pinned') === '1';
        togglePin(id, !pinned);
      });
    });

    // Archive button
    document.querySelectorAll('.crm-sticky-archive-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-note-id');
        if (confirm(t('dashboard.sticky_notes_archive_confirm', 'Архивировать заметку?'))) {
          archiveNote(id);
        }
      });
    });
  }

  function startEditing(id) {
    state.editingId = id;
    renderNotes();
    var textarea = document.querySelector('.crm-sticky-edit-textarea[data-note-id="' + id + '"]');
    if (textarea) textarea.focus();
  }

  function saveNote(id, content) {
    if (!content || !content.trim()) {
      notify(t('dashboard.sticky_notes_content_required', 'Введите текст заметки'), 'error');
      return;
    }
    req('api/v1/sticky-notes/' + encodeURIComponent(id), {
      method: 'PATCH',
      body: { content: content.trim(), row_version: getRowVersion(id) }
    }).then(function () {
      state.editingId = null;
      notify(t('dashboard.sticky_notes_saved', 'Заметка обновлена'), 'success');
      loadNotes();
    }).catch(function (err) {
      notify(t('dashboard.sticky_notes_save_error', 'Ошибка сохранения'), 'error');
    });
  }

  function getRowVersion(id) {
    var note = state.items.find(function (n) { return n.public_id === id; });
    return note ? (note.row_version || 1) : 1;
  }

  function togglePin(id, pinned) {
    req('api/v1/sticky-notes/' + encodeURIComponent(id), {
      method: 'PATCH',
      body: { is_pinned: pinned, row_version: getRowVersion(id) }
    }).then(function () {
      loadNotes();
    }).catch(function () {
      notify(t('dashboard.sticky_notes_update_error', 'Ошибка обновления'), 'error');
    });
  }

  function archiveNote(id) {
    req('api/v1/sticky-notes/' + encodeURIComponent(id) + '/archive', {
      method: 'POST',
      body: {}
    }).then(function () {
      notify(t('dashboard.sticky_notes_archived', 'Заметка архивирована'), 'success');
      loadNotes();
    }).catch(function () {
      notify(t('dashboard.sticky_notes_archive_error', 'Ошибка архивации'), 'error');
    });
  }

  function openCreateModal() {
    var colors = state.colors;
    var colorOptions = colors.map(function (c) {
      return '<option value="' + c + '" style="background:' + c + '">' + c + '</option>';
    }).join('');

    var html = '<div class="modal fade" id="stickyNoteCreateModal" tabindex="-1" aria-hidden="true">'
      + '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">'
      + '<div class="modal-header"><h5 class="modal-title">' + esc(t('dashboard.sticky_notes_create_title', 'Новая заметка')) + '</h5>'
      + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' + esc(t('page.close', 'Закрыть')) + '"></button></div>'
      + '<div class="modal-body">'
      + '<div class="mb-3"><label class="form-label">' + esc(t('dashboard.sticky_notes_content_label', 'Текст заметки')) + '</label>'
      + '<textarea class="form-control" id="stickyNoteCreateContent" rows="4" placeholder="' + esc(t('dashboard.sticky_notes_placeholder', 'Напишите заметку...')) + '" maxlength="5000"></textarea></div>'
      + '<div class="mb-3"><label class="form-label">' + esc(t('dashboard.sticky_notes_color_label', 'Цвет')) + '</label>'
      + '<select class="form-select" id="stickyNoteCreateColor">' + colorOptions + '</select></div>'
      + '<div class="form-check"><input class="form-check-input" type="checkbox" id="stickyNoteCreatePinned">'
      + '<label class="form-check-label" for="stickyNoteCreatePinned">' + esc(t('dashboard.sticky_notes_pin', 'Закрепить')) + '</label></div>'
      + '</div>'
      + '<div class="modal-footer">'
      + '<button type="button" class="btn btn-light" data-bs-dismiss="modal">' + esc(t('page.cancel', 'Отмена')) + '</button>'
      + '<button type="button" class="btn crm-btn-primary" id="stickyNoteCreateSaveBtn">' + esc(t('page.create', 'Создать')) + '</button>'
      + '</div></div></div></div>';

    // Remove existing modal if any
    var existing = document.getElementById('stickyNoteCreateModal');
    if (existing) existing.remove();

    document.body.insertAdjacentHTML('beforeend', html);

    var modalEl = document.getElementById('stickyNoteCreateModal');
    var modal = new bootstrap.Modal(modalEl);
    modal.show();

    document.getElementById('stickyNoteCreateSaveBtn').addEventListener('click', function () {
      var content = document.getElementById('stickyNoteCreateContent').value.trim();
      if (!content) {
        notify(t('dashboard.sticky_notes_content_required', 'Введите текст заметки'), 'error');
        return;
      }
      var color = document.getElementById('stickyNoteCreateColor').value;
      var pinned = document.getElementById('stickyNoteCreatePinned').checked;

      var btn = document.getElementById('stickyNoteCreateSaveBtn');
      if (btn) btn.disabled = true;

      req('api/v1/sticky-notes', {
        method: 'POST',
        body: { content: content, color: color, is_pinned: pinned }
      }).then(function () {
        modal.hide();
        notify(t('dashboard.sticky_notes_created', 'Заметка создана'), 'success');
        loadNotes();
      }).catch(function () {
        notify(t('dashboard.sticky_notes_create_error', 'Ошибка создания'), 'error');
      }).finally(function () {
        if (btn) btn.disabled = false;
      });
    });
  }


  // ---- Drag and Drop Reorder ----
  var dragSourceId = null;

  function injectDragStyles() {
    var style = document.getElementById('crmStickyDragStyles');
    if (style) return;
    style = document.createElement('style');
    style.id = 'crmStickyDragStyles';
    // CSP style-src has no 'unsafe-inline': dynamically injected <style> needs
    // the per-request nonce or the browser blocks it (see web/index.php).
    if (window.CRM && window.CRM.config && window.CRM.config.cspNonce) {
      style.setAttribute('nonce', window.CRM.config.cspNonce);
    }
    style.textContent = '.crm-sticky-dragging { opacity: 0.5; } [data-note-id].crm-sticky-drag-over { transform: scale(1.03); box-shadow: 0 4px 12px rgba(0,0,0,0.15); } [data-note-id] { transition: transform 0.15s ease, box-shadow 0.15s ease; cursor: grab; } [data-note-id]:active { cursor: grabbing; }';
    document.head.appendChild(style);
  }

  function initDragReorder() {
    var list = document.getElementById('stickyNotesList');
    if (!list) return;
    list.querySelectorAll('[data-note-id]').forEach(function (col) {
      col.draggable = true;
      col.addEventListener('dragstart', function (e) {
        dragSourceId = col.getAttribute('data-note-id');
        col.classList.add('crm-sticky-dragging');
        e.dataTransfer.effectAllowed = 'move';
      });
      col.addEventListener('dragend', function () {
        col.classList.remove('crm-sticky-dragging');
        dragSourceId = null;
        document.querySelectorAll('[data-note-id]').forEach(function (c) {
          c.classList.remove('crm-sticky-drag-over');
        });
      });
      col.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
      });
      col.addEventListener('dragenter', function (e) {
        e.preventDefault();
        if (col.getAttribute('data-note-id') !== dragSourceId) {
          col.classList.add('crm-sticky-drag-over');
        }
      });
      col.addEventListener('dragleave', function () {
        col.classList.remove('crm-sticky-drag-over');
      });
      col.addEventListener('drop', function (e) {
        e.preventDefault();
        col.classList.remove('crm-sticky-drag-over');
        var targetId = col.getAttribute('data-note-id');
        if (dragSourceId && targetId && dragSourceId !== targetId) {
          reorderNotes(dragSourceId, targetId);
        }
      });
    });
  }

  function reorderNotes(sourceId, targetId) {
    var sourceIdx = -1;
    var targetIdx = -1;
    var active = state.items.filter(function (n) { return !n.is_archived; });
    active.sort(function (a, b) {
      if (a.is_pinned && !b.is_pinned) return -1;
      if (!a.is_pinned && b.is_pinned) return 1;
      return (a.sort_order || 0) - (b.sort_order || 0);
    });
    active.forEach(function (n, i) {
      if (n.public_id === sourceId) sourceIdx = i;
      if (n.public_id === targetId) targetIdx = i;
    });
    if (sourceIdx < 0 || targetIdx < 0) return;
    var ids = active.map(function (n) { return n.public_id; });
    ids.splice(sourceIdx, 1);
    ids.splice(targetIdx, 0, sourceId);
    req('api/v1/sticky-notes/reorder', {
      method: 'POST',
      body: { public_ids: ids }
    }).then(function () {
      notify(t('dashboard.sticky_notes_reordered', 'Порядок заметок обновлён'), 'success');
      loadNotes();
    }).catch(function () {
      notify(t('dashboard.sticky_notes_reorder_error', 'Ошибка изменения порядка'), 'error');
    });
  }


  function init() {
    injectDragStyles();
    if (document.body.getAttribute('data-page') !== 'dashboard') return;
    waitForApi(function () {
      loadNotes();
            var addBtn = document.getElementById('stickyNoteAddBtn');
      if (addBtn) addBtn.addEventListener('click', openCreateModal);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return {
    loadNotes: loadNotes,
    openCreateModal: openCreateModal,
    saveNote: saveNote,
    archiveNote: archiveNote,
    togglePin: togglePin
  };
})();
