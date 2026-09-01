/**
 * chat-widget.js — Shared chat rendering and action-binding module.
 *
 * Used by:
 *   - chat.php (full chat page)
 *   - project_detail.php (project chat tab)
 *
 * Exposes window.CRM.chat with pure rendering helpers and a
 * bindMessageActions(container, context) that wires up delegated click
 * handlers. Each page supplies a context object with page-specific
 * callbacks.
 *
 * Context shape:
 *   findMessage(id)           – look up a message object by public_id
 *   request(route, opts)      – API request wrapper
 *   showConfirmModal(opts)    – returns Promise<boolean>
 *   showImageModal(url, name) – open image lightbox
 *   showMessageHistory(id)   – open history modal
 *   copyText(text)            – clipboard API wrapper
 *   onReply(message)          – set reply state
 *   onEdit(message)           – set edit state (focus input)
 *   onAfterDelete()           – refresh after message deletion
 *   onCreateTask(messageId)   – create task from message
 *   onCreateKnowledge(msgId)  – create knowledge page from message
 *   container                 – the scrollable messages container element
 */
(function () {
  'use strict';

  var chat = {};

  /* ── helpers ─────────────────────────────────────────────── */

  chat.esc = function (value) {
    if (window.CRM && window.CRM.text && typeof window.CRM.text.escapeHtml === 'function')
      return window.CRM.text.escapeHtml(value == null ? '' : String(value));
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch] || ch;
    });
  };

  chat.formatTime = function (value) {
    if (!value) return '';
    var date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return '';
    var locale = String((window.CRM && (window.CRM.locale || window.CRM.currentLocale)) || document.documentElement.lang || 'en-GB').replace('_', '-');
    return date.toDateString() === new Date().toDateString()
      ? date.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })
      : date.toLocaleDateString(locale, { day: '2-digit', month: '2-digit' });
  };

  chat.formatFileSize = function (bytes) {
    bytes = Number(bytes || 0);
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' ' + window.CRM.i18n.t('chat.file_size_mb', 'МБ');
    if (bytes >= 1024) return Math.round(bytes / 1024) + ' ' + window.CRM.i18n.t('chat.file_size_kb', 'КБ');
    return bytes + ' ' + window.CRM.i18n.t('chat.file_size_b', 'Б');
  };

  chat.messageAgeMs = function (message) {
    var date = new Date(String(message.created_at || '').replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return Number.POSITIVE_INFINITY;
    return Math.max(0, Date.now() - date.getTime());
  };

  chat.canEditMessage = function (message) {
    if (message && message.can_edit !== undefined && message.can_edit !== null)
      return Number(message.can_edit) === 1;
    return chat.messageAgeMs(message) <= 60 * 60 * 1000;
  };

  chat.canDeleteMessage = function (message) {
    if (message && message.can_delete !== undefined && message.can_delete !== null)
      return Number(message.can_delete) === 1;
    return chat.messageAgeMs(message) <= 10 * 60 * 1000;
  };

  /* ── text rendering (mentions, stickers, knowledge cards) ── */

  chat.renderMessageText = function (text) {
    var safe = chat.esc(text).replace(/\n/g, '<br>');
    safe = safe.replace(/(^|\s)@([\p{L}\p{N}._-]{2,80})/gu, '$1<span class="crm-chat-mention">@$2</span>');
    safe = safe.replace(/\[стикер: ([^\]]+)\]/g, '<span class="crm-chat-sticker">$1</span>');
    safe = safe.replace(/\[gif: ([^\]]+)\]/g, '<span class="crm-chat-sticker">$1</span>');
    safe = safe.replace(/kb:([a-zA-Z0-9_]+):([^<]*)/g, function (match, publicId, title) {
      title = (title || '').trim() || window.CRM.i18n.t('chat.knowledge_page', 'Knowledge page');
      return '<a href="index.php?route=knowledge-page&amp;id=' + encodeURIComponent(publicId) + '" class="crm-knowledge-chat-card" target="_blank" rel="noopener" title="' + window.CRM.i18n.t('chat.open_knowledge_title', 'Open in Knowledge Base') + ': ' + chat.esc(title) + '">'
        + '<span class="crm-knowledge-chat-icon"><i class="fa-solid fa-book-open" aria-hidden="true"></i></span>'
        + '<span class="crm-knowledge-chat-info"><strong>' + chat.esc(title) + '</strong><span>' + window.CRM.i18n.t('chat.knowledge_page_subtitle', 'Knowledge base page') + '</span></span>'
        + '<span class="crm-knowledge-chat-arrow"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span></a>';
    });
    return safe;
  };

  /* ── attachments ─────────────────────────────────────────── */

  chat.renderAttachments = function (files) {
    if (!files || !files.length) return '';
    return '<div class="crm-chat-attachments">' + files.map(function (file) {
      var url = file.download_url || '#';
      if (String(file.mime_type || '').indexOf('image/') === 0)
        return '<button type="button" class="crm-chat-image-attachment" data-image-url="' + chat.esc(url) + '" data-image-name="' + chat.esc(file.original_name || window.CRM.i18n.t('chat.image_default', 'Изображение')) + '" title="' + window.CRM.i18n.t('chat.open_image_title', 'Открыть изображение: ') + chat.esc(file.original_name || window.CRM.i18n.t('chat.image_default', 'Изображение')) + '" aria-label="' + window.CRM.i18n.t('chat.open_image_aria', 'Открыть изображение: ') + chat.esc(file.original_name || window.CRM.i18n.t('chat.image_default', 'Изображение')) + '"><img src="' + chat.esc(url) + '" alt="' + chat.esc(file.original_name || window.CRM.i18n.t('chat.image_default', 'Изображение')) + '"></button>';
      return '<a class="crm-chat-file-attachment" href="' + chat.esc(url) + '" download title="' + window.CRM.i18n.t('chat.download_file_title', 'Скачать файл: ') + chat.esc(file.original_name || window.CRM.i18n.t('chat.file_default', 'Файл')) + '" aria-label="' + window.CRM.i18n.t('chat.download_file_aria', 'Скачать файл: ') + chat.esc(file.original_name || window.CRM.i18n.t('chat.file_default', 'Файл')) + '"><i class="fa-solid fa-file" aria-hidden="true"></i><span>' + chat.esc(file.original_name || window.CRM.i18n.t('chat.file_default', 'Файл')) + '</span><small>' + chat.esc(chat.formatFileSize(file.size_bytes || 0)) + '</small></a>';
    }).join('') + '</div>';
  };

  /* ── reply quote ─────────────────────────────────────────── */

  chat.renderReplyQuote = function (message, findMessage) {
    var sender = chat.esc(message.reply_sender_name || message.reply_sender_login || window.CRM.i18n.t('chat.reply_default_sender', 'Сообщение'));
    var text = message.reply_text;
    if (text != null && text !== '') return '<strong>' + sender + '</strong><span>' + chat.esc(text) + '</span>';
    var original = typeof findMessage === 'function' ? findMessage(message.reply_public_id) : null;
    if (original) {
      if (original.deleted_at) return '<strong>' + sender + '</strong><span class="crm-chat-deleted-text">' + window.CRM.i18n.t('chat.msg_deleted', 'Сообщение удалено') + '</span>';
      if (original.text && original.text.trim()) return '<strong>' + sender + '</strong><span>' + chat.esc(original.text) + '</span>';
      var attachments = Array.isArray(original.attachments) ? original.attachments : [];
      if (attachments.length) {
        var names = attachments.map(function (f) { return chat.esc(f.original_name || window.CRM.i18n.t('chat.file_default', 'Файл')); }).slice(0, 3).join(', ');
        if (attachments.length > 3) names += ' ' + window.CRM.i18n.t('chat.and_more', 'и ещё') + ' ' + (attachments.length - 3);
        return '<strong>' + sender + '</strong><span><i class="fa-solid fa-paperclip" aria-hidden="true"></i> ' + names + '</span>';
      }
    }
    return '<strong>' + sender + '</strong><span>' + window.CRM.i18n.t('chat.no_text', 'Без текста') + '</span>';
  };

  /* ── more-menu (⋮) ──────────────────────────────────────── */

  chat.renderMessageMoreMenu = function (message, canEdit, canDelete) {
    var deleted = !!message.deleted_at;
    var mid = chat.esc(message.public_id || '');
    var items = [];
    if (!deleted) {
      items.push('<button type="button" class="crm-chat-action crm-chat-more-item" role="menuitem" data-copy-message="' + mid + '" title="' + window.CRM.i18n.t('chat.btn_copy_title', 'Копировать сообщение') + '"><i class="fa-solid fa-copy" aria-hidden="true"></i><span>' + window.CRM.i18n.t('chat.btn_copy', 'Копировать') + '</span></button>');
    }
    if (canEdit) {
      items.push('<button type="button" class="crm-chat-action crm-chat-more-item" role="menuitem" data-edit-message="' + mid + '" title="' + window.CRM.i18n.t('chat.btn_edit_title', 'Изменить сообщение') + '"><i class="fa-solid fa-pen" aria-hidden="true"></i><span>' + window.CRM.i18n.t('chat.btn_edit', 'Изменить') + '</span></button>');
    }
    if (canDelete) {
      items.push('<button type="button" class="crm-chat-action crm-chat-more-item crm-chat-more-item--danger" role="menuitem" data-delete-message="' + mid + '" title="' + window.CRM.i18n.t('chat.btn_delete_title', 'Удалить сообщение') + '"><i class="fa-solid fa-trash-can" aria-hidden="true"></i><span>' + window.CRM.i18n.t('chat.btn_delete', 'Удалить') + '</span></button>');
    }
    items.push('<button type="button" class="crm-chat-action crm-chat-more-item" role="menuitem" data-create-knowledge="' + mid + '" title="' + window.CRM.i18n.t('chat.btn_create_knowledge_title', 'Создать страницу из сообщения') + '"><i class="fa-solid fa-file-lines" aria-hidden="true"></i><span>' + window.CRM.i18n.t('chat.btn_create_knowledge', 'Создать страницу') + '</span></button>');
    if (items.length === 0) return '';
    return '<div class="crm-chat-message-extra">'
      + '<button type="button" class="crm-chat-action crm-chat-more-btn" aria-haspopup="true" aria-expanded="false" aria-label="' + window.CRM.i18n.t('chat.btn_more_aria', 'Дополнительные действия') + '" title="' + window.CRM.i18n.t('chat.btn_more_aria', 'Дополнительные действия') + '" data-chat-more><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>'
      + '<div class="crm-chat-more-menu" role="menu" hidden>' + items.join('') + '</div>'
      + '</div>';
  };

  /* ── full message render ─────────────────────────────────── */

  chat.renderMessage = function (message, ctx) {
    var findMessage = ctx && ctx.findMessage ? ctx.findMessage : function () { return null; };
    var sender = message.sender_name || message.sender_login || window.CRM.i18n.t('chat.default_sender', 'Пользователь');
    var own = Number(message.is_own || 0) === 1;
    var deleted = !!message.deleted_at;
    var canEdit = own && !deleted && chat.canEditMessage(message);
    var canDelete = own && !deleted && chat.canDeleteMessage(message);
    return '<article class="crm-chat-message' + (own ? ' is-own' : '') + (deleted ? ' is-deleted' : '') + '" data-message-id="' + chat.esc(message.public_id || '') + '">'
      + '<div class="crm-chat-message-meta"><strong>' + chat.esc(sender) + '</strong><time>' + chat.esc(chat.formatTime(message.created_at)) + '</time></div>'
      + (message.reply_public_id ? '<button type="button" class="crm-chat-quote" data-scroll-message="' + chat.esc(message.reply_public_id) + '" title="' + window.CRM.i18n.t('chat.btn_scroll_title', 'Перейти к исходному сообщению') + '" aria-label="' + window.CRM.i18n.t('chat.btn_scroll_aria', 'Перейти к исходному сообщению') + '">' + chat.renderReplyQuote(message, findMessage) + '</button>' : '')
      + (deleted ? '<p class="crm-chat-deleted-text">' + window.CRM.i18n.t('chat.msg_deleted', 'Сообщение удалено') + '</p>' : '<p>' + chat.renderMessageText(message.text || '') + '</p>')
      + chat.renderAttachments(Array.isArray(message.attachments) ? message.attachments : [])
      + '<div class="crm-chat-message-foot">'
      + (message.edited_at && !deleted ? '<button type="button" class="crm-chat-edited-marker" data-history-message="' + chat.esc(message.public_id || '') + '" title="' + window.CRM.i18n.t('chat.btn_history_title', 'История изменений') + '" aria-label="' + window.CRM.i18n.t('chat.btn_history_aria', 'История изменений сообщения') + '">' + window.CRM.i18n.t('chat.msg_edited', 'изменено') + '</button>' : '')
      + '<button type="button" class="crm-chat-action crm-chat-quick-action" data-reply-message="' + chat.esc(message.public_id || '') + '" title="' + window.CRM.i18n.t('chat.btn_reply_title', 'Ответить на сообщение') + '" aria-label="' + window.CRM.i18n.t('chat.btn_reply_aria', 'Ответить на сообщение') + '"><i class="fa-solid fa-reply" aria-hidden="true"></i><span>' + window.CRM.i18n.t('chat.btn_reply', 'Ответить') + '</span></button>'
      + '<button type="button" class="crm-chat-action crm-chat-quick-action" data-create-task="' + chat.esc(message.public_id || '') + '" title="' + window.CRM.i18n.t('chat.btn_create_task_title', 'Создать задачу из сообщения') + '" aria-label="' + window.CRM.i18n.t('chat.btn_create_task_aria', 'Создать задачу из сообщения') + '"><i class="fa-solid fa-list-check" aria-hidden="true"></i><span>' + window.CRM.i18n.t('chat.btn_create_task', 'Создать задачу') + '</span></button>'
      + '</div>'
      + chat.renderMessageMoreMenu(message, canEdit, canDelete)
      + '</article>';
  };

  /* ── empty state ─────────────────────────────────────────── */

  chat.renderEmpty = function () {
    return '<div class="crm-chat-empty crm-chat-message-text--small"><strong>' + window.CRM.i18n.t('chat.messages_empty_title', 'Сообщений пока нет') + '</strong><span>' + window.CRM.i18n.t('chat.messages_empty_text', 'Напишите первое сообщение в этом диалоге.') + '</span></div>';
  };

  /* ── action binding (delegated) ──────────────────────────── */

  chat.bindMessageActions = function (container, ctx) {
    if (!container || container.getAttribute('data-chat-actions-bound') === '1') return;
    container.setAttribute('data-chat-actions-bound', '1');

    container.addEventListener('click', function (ev) {
      /* copy */
      var copyBtn = ev.target.closest('[data-copy-message]');
      if (copyBtn && ctx.copyText) {
        var msgId = copyBtn.getAttribute('data-copy-message');
        var msg = ctx.findMessage ? ctx.findMessage(msgId) : null;
        var text = msg && msg.text ? String(msg.text).trim() : '';
        if (!text) return;
        ctx.copyText(text).then(function () {
          var label = copyBtn.querySelector('span');
          var original = label ? label.textContent : copyBtn.textContent;
          if (label) label.textContent = window.CRM.i18n.t('chat.msg_copied', 'Скопировано');
          else copyBtn.textContent = window.CRM.i18n.t('chat.msg_copied', 'Скопировано');
          window.setTimeout(function () {
            if (label) label.textContent = original;
            else copyBtn.textContent = original;
          }, 1500);
        }).catch(function () {});
        return;
      }
      /* edit */
      var editBtn = ev.target.closest('[data-edit-message]');
      if (editBtn && ctx.onEdit) {
        var editId = editBtn.getAttribute('data-edit-message');
        var editMsg = ctx.findMessage ? ctx.findMessage(editId) : null;
        if (editMsg) ctx.onEdit(editMsg);
        return;
      }
      /* delete */
      var delBtn = ev.target.closest('[data-delete-message]');
      if (delBtn && ctx.showConfirmModal && ctx.request && ctx.onAfterDelete) {
        var delId = delBtn.getAttribute('data-delete-message');
        ctx.showConfirmModal({
          title: window.CRM.i18n.t('chat.confirm_delete_title', 'Удалить сообщение?'),
          body: window.CRM.i18n.t('chat.confirm_delete_body', 'Сообщение будет скрыто в чате, а действие сохранится в журнале изменений.'),
          submitText: window.CRM.i18n.t('chat.confirm_delete_submit', 'Удалить'),
          danger: true
        }).then(function (confirmed) {
          if (!confirmed) return;
          ctx.request('api/v1/chats/' + encodeURIComponent(ctx.chatId) + '/messages/' + encodeURIComponent(delId), { method: 'DELETE' })
            .then(function () { ctx.onAfterDelete(); });
        });
        return;
      }
      /* reply */
      var replyBtn = ev.target.closest('[data-reply-message]');
      if (replyBtn && ctx.onReply) {
        var replyId = replyBtn.getAttribute('data-reply-message');
        var replyMsg = ctx.findMessage ? ctx.findMessage(replyId) : null;
        if (replyMsg) ctx.onReply(replyMsg);
        return;
      }
      /* create task */
      var taskBtn = ev.target.closest('[data-create-task]');
      if (taskBtn && ctx.onCreateTask) {
        ctx.onCreateTask(taskBtn.getAttribute('data-create-task'));
        return;
      }
      /* create knowledge page */
      var knowledgeBtn = ev.target.closest('[data-create-knowledge]');
      if (knowledgeBtn && ctx.onCreateKnowledge) {
        ctx.onCreateKnowledge(knowledgeBtn.getAttribute('data-create-knowledge'));
        return;
      }
      /* history */
      var historyBtn = ev.target.closest('[data-history-message]');
      if (historyBtn && ctx.showMessageHistory) {
        ctx.showMessageHistory(historyBtn.getAttribute('data-history-message'));
        return;
      }
      /* image */
      var imageBtn = ev.target.closest('[data-image-url]');
      if (imageBtn && ctx.showImageModal) {
        ctx.showImageModal(imageBtn.getAttribute('data-image-url'), imageBtn.getAttribute('data-image-name'));
        return;
      }
      /* scroll to reply */
      var scrollBtn = ev.target.closest('[data-scroll-message]');
      if (scrollBtn) {
        var target = container.querySelector('[data-message-id="' + CSS.escape(scrollBtn.getAttribute('data-scroll-message')) + '"]');
        if (target) target.scrollIntoView({ block: 'center', behavior: 'smooth' });
        return;
      }
      /* more-menu toggle */
      var moreBtn = ev.target.closest('[data-chat-more]');
      if (moreBtn) {
        ev.preventDefault();
        ev.stopPropagation();
        chat.closeAllMoreMenus(container);
        var menuBox = moreBtn.nextElementSibling;
        var willOpen = !menuBox || menuBox.hidden;
        if (menuBox && willOpen) {
          menuBox.hidden = false;
          moreBtn.setAttribute('aria-expanded', 'true');
        } else {
          moreBtn.setAttribute('aria-expanded', 'false');
        }
        return;
      }
      /* menu item click → close menu */
      var menuItemClick = ev.target.closest('.crm-chat-more-menu [role="menuitem"]');
      if (menuItemClick) {
        chat.closeAllMoreMenus(container);
        return;
      }
    });
  };

  chat.closeAllMoreMenus = function (container) {
    if (!container) return;
    container.querySelectorAll('.crm-chat-message-extra').forEach(function (extra) {
      var menuBox = extra.querySelector('.crm-chat-more-menu');
      var btn = extra.querySelector('[data-chat-more]');
      if (menuBox) menuBox.hidden = true;
      if (btn) btn.setAttribute('aria-expanded', 'false');
    });
  };

  /* ── close menus on outside click / Escape ──────────────── */

  document.addEventListener('click', function (ev) {
    if (!ev.target.closest('.crm-chat-message-extra')) {
      document.querySelectorAll('.crm-chat-message-extra .crm-chat-more-menu').forEach(function (m) { m.hidden = true; });
      document.querySelectorAll('.crm-chat-more-btn[aria-expanded="true"]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
    }
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') {
      document.querySelectorAll('.crm-chat-message-extra .crm-chat-more-menu').forEach(function (m) { m.hidden = true; });
      document.querySelectorAll('.crm-chat-more-btn[aria-expanded="true"]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
    }
  });

  /* ── public API ──────────────────────────────────────────── */

  window.CRM = window.CRM || {};
  window.CRM.chat = chat;
})();
