<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Чаты'; ?>
<body data-page="chat" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-chat-page">
  <div class="crm-chat-layout" id="chatRoot">
    <aside class="crm-chat-sidebar" aria-label="Список чатов">
      <div class="crm-chat-sidebar-head">
        <div>
          <h1 class="crm-chat-title">Чаты</h1>
          <div class="crm-chat-subtitle" id="chatListSummary">Загрузка...</div>
        </div>
        <button class="btn crm-btn-primary crm-chat-new-btn" type="button" id="newChatBtn" title="Создать чат" aria-label="Создать чат"><i class="fa-solid fa-plus" aria-hidden="true"></i><span>Новый</span></button>
      </div>
      <div class="crm-chat-search">
        <span aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
          <label class="visually-hidden" for="chatSearchInput">Поиск по чатам</label>
          <input id="chatSearchInput" type="search" placeholder="Поиск по чатам">
        </div>
        <button class="btn crm-btn-subtle crm-btn-compact w-100 mb-2" type="button" id="toggleArchivedBtn" aria-pressed="false" title="Показать архивные чаты" aria-label="Показать архивные чаты">Архив</button>
        <div id="chatList" class="crm-chat-list" role="list" aria-live="polite"><div class="crm-chat-list-state">Загрузка чатов...</div></div>
    </aside>

    <section class="crm-chat-conversation" id="chatArea" aria-live="polite">
      <div class="crm-chat-empty">
        <i class="fa-solid fa-comments" aria-hidden="true"></i>
        <strong>Выберите чат</strong>
        <span>Откройте существующий диалог или создайте новый.</span>
      </div>
    </section>
  </div>
</main></div></div>

<div id="newChatModal" class="crm-chat-modal" role="dialog" aria-modal="true" aria-labelledby="newChatModalTitle" aria-hidden="true">
  <div class="crm-chat-modal-panel crm-chat-modal-wide">
    <div class="crm-chat-modal-head">
      <h2 class="h5 mb-0" id="newChatModalTitle">Новый чат</h2>
      <button class="btn-close" type="button" id="closeChatModalIcon" aria-label="Закрыть"></button>
    </div>
    <div class="row g-3">
      <div class="col-12"><label class="form-label" for="newChatTitle">Название чата</label><input type="text" class="form-control" id="newChatTitle" placeholder="Введите название" maxlength="160"></div>
      <div class="col-12">
        <div class="team-participant-panel">
          <div class="team-participant-toolbar"><h6 class="team-participant-title">Участники <span class="team-participant-count" id="chatParticipantCount">0</span></h6></div>
          <div class="team-participant-search-wrap">
            <span class="crm-icon team-search-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" class="team-search-input" id="chatParticipantSearch" placeholder="Найти сотрудника...">
            <div class="team-search-dropdown" id="chatSearchResults" hidden></div>
          </div>
          <div class="team-participant-list" id="chatParticipantList" role="listbox"></div>
          <div class="team-empty-state" id="chatParticipantEmpty">
            <span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-user-plus"></i></span>
            <p>Добавьте участников</p>
            <span class="team-empty-hint">Используйте поиск для добавления</span>
          </div>
        </div>
      </div>
    </div>
    <div class="text-danger small mb-2 d-none" id="newChatError" aria-live="polite"></div>
    <div class="crm-chat-modal-actions">
      <button class="btn crm-btn-muted" type="button" id="closeChatModal">Отмена</button>
      <button class="btn crm-btn-primary" type="button" id="createChatBtn">Создать</button>
    </div>
  </div>
</div>

<script>
(function () {
  var selectedChatId = new URLSearchParams(window.location.search).get('id') || '';
  var urlChatId = selectedChatId;
  var renderedChatId = '';
  var currentChat = null;
  var currentMessages = [];
  var replyToMessage = null;
  var editingMessage = null;
  var chats = [];
  var chatSearch = '';
  var chatParticipants = [];
  var chatSearchTimer = null;
  var showArchived = false;
  var allUsers = [];
  var pollTimer = null;
  var loadingMessages = false;
  var pollingMessages = false;
  var pollingChats = false;
  var lastMessageId = 0;
  var messageRevisionTick = 0;
  var chatListPollTick = 0;
  var emojiSet = ['👍','👌','🙏','🔥','✅','⚡','🙂','🤝','💡','📌','👀','🚀'];
  var stickerSet = [
    { label: 'Сделано', text: '[стикер: сделано ✅]' },
    { label: 'Принято', text: '[стикер: принято 🤝]' },
    { label: 'Внимание', text: '[стикер: нужно внимание 📌]' },
    { label: 'Отлично', text: '[стикер: отлично 🚀]' },
    { label: 'GIF: аплодисменты', text: '[gif: аплодисменты 👏]' },
    { label: 'GIF: в работе', text: '[gif: в работе ⚙️]' }
  ];

  function esc(value) {
    if (window.CRM && window.CRM.text && typeof window.CRM.text.escapeHtml === 'function') return window.CRM.text.escapeHtml(value == null ? '' : String(value));
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]; });
  }

  function request(route, options) { return window.CRM.api.request(route, options || {}); }

  function formatTime(value) {
    if (!value) return '';
    var date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return '';
    return date.toDateString() === new Date().toDateString()
      ? date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
      : date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' });
  }

  function plural(count, one, few, many) {
    var mod10 = count % 10, mod100 = count % 100;
    if (mod10 === 1 && mod100 !== 11) return one;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return few;
    return many;
  }

  function chatTitle(chat) {
    var title = String(chat.title || '').trim();
    var participants = String(chat.participant_names || '').split(',').map(function (item) { return item.trim(); }).filter(Boolean);
    if ((chat.type === 'project' || chat.type === 'team') && title) return title;
    if (title && title.toLowerCase() !== 'чат') return title;
    if (participants.length) return participants.join(', ');
    return title || ('Чат #' + String(chat.public_id || '').slice(-6));
  }

  function chatTypeLabel(chat) {
    var type = String(chat && chat.type || 'direct');
    if (type === 'project') return 'Проект';
    if (type === 'team') return 'Команда';
    if (type === 'group') return 'Группа';
    return 'Личный';
  }

  function isMuted(chat) {
    var raw = chat && chat.muted_until ? String(chat.muted_until) : '';
    if (!raw) return false;
    var date = new Date(raw.replace(' ', 'T'));
    return raw.indexOf('9999') === 0 || (!Number.isNaN(date.getTime()) && date > new Date());
  }

  function lastMessageText(chat) {
    if (String(chat.last_message_type || '') === 'attachment') return 'Файл или изображение';
    return chat.last_message || 'Сообщений пока нет';
  }

  function updateListSummary() {
    var node = document.getElementById('chatListSummary');
    if (!node) return;
    var unread = chats.reduce(function (sum, chat) { return sum + (Number(chat.unread || 0) || 0); }, 0);
    node.textContent = chats.length ? chats.length + ' ' + plural(chats.length, 'диалог', 'диалога', 'диалогов') + (unread ? ' · ' + unread + ' непрочит.' : '') : 'Нет диалогов';
  }

  function filteredChats() {
    var query = chatSearch.trim().toLowerCase();
    if (!query) return chats;
    return chats.filter(function (chat) {
      return [chatTitle(chat), lastMessageText(chat), chat.last_sender || '', chat.public_id || ''].join(' ').toLowerCase().indexOf(query) !== -1;
    });
  }

  function renderChatList() {
    var list = document.getElementById('chatList');
    if (!list) return;
    updateListSummary();
    var items = filteredChats();
    if (!chats.length) {
      list.innerHTML = '<div class="crm-chat-list-state"><strong>Чатов пока нет</strong><span>Создайте первый диалог с коллегой.</span></div>';
      return;
    }
    if (!items.length) {
      list.innerHTML = '<div class="crm-chat-list-state"><strong>Ничего не найдено</strong><span>Измените поисковый запрос.</span></div>';
      return;
    }
    list.innerHTML = items.map(function (chat) {
      var id = String(chat.public_id || '');
      var unread = Number(chat.unread || 0) || 0;
      var active = id && id === selectedChatId;
      var title = chatTitle(chat);
      return '<button type="button" class="crm-chat-item' + (active ? ' is-active' : '') + (unread ? ' has-unread' : '') + (Number(chat.is_favorite || 0) ? ' is-favorite' : '') + '" data-chat-id="' + esc(id) + '" role="listitem" aria-current="' + (active ? 'true' : 'false') + '" title="Открыть чат: ' + esc(title) + '" aria-label="Открыть чат: ' + esc(title) + '">'
        + '<span class="crm-chat-item-main"><span class="crm-chat-item-kicker">' + esc(chatTypeLabel(chat)) + (Number(chat.is_favorite || 0) ? ' · избранный' : '') + (isMuted(chat) ? ' · без уведомлений' : '') + '</span><strong>' + esc(chatTitle(chat)) + '</strong><small>' + esc(lastMessageText(chat)) + '</small></span>'
        + '<span class="crm-chat-item-meta">' + (formatTime(chat.last_message_at) ? '<small>' + esc(formatTime(chat.last_message_at)) + '</small>' : '') + (unread ? '<b>' + unread + '</b>' : '') + (Number(chat.is_favorite || 0) ? '<i class="fa-solid fa-star" aria-hidden="true"></i>' : '') + '</span>'
        + '</button>';
    }).join('');
  }

  function setSelectedChatId(id, updateUrl) {
    selectedChatId = String(id || '');
    if (updateUrl !== false) {
      var url = new URL(window.location.href);
      if (selectedChatId) url.searchParams.set('id', selectedChatId); else url.searchParams.delete('id');
      window.history.replaceState({}, '', url.toString());
    }
    renderChatList();
  }

  async function loadChats(options) {
    options = options || {};
    if (!window.CRM || !window.CRM.api) { window.setTimeout(function () { loadChats(options); }, 200); return; }
    var list = document.getElementById('chatList');
    if (list && !options.silent) list.innerHTML = '<div class="crm-chat-list-state">Загрузка чатов...</div>';
    try {
      var env = await request('api/v1/chats', { method: 'GET' });
      chats = (env.data && env.data.items) || [];
      if (selectedChatId && selectedChatId !== urlChatId && !chats.some(function (chat) { return String(chat.public_id) === selectedChatId; })) setSelectedChatId('', true);
      if (!selectedChatId && chats.length && !chatSearch) {
        setSelectedChatId(String(chats[0].public_id || ''), true);
        loadMessages(selectedChatId, { initial: true });
      }
      renderChatList();
      if (options.initial && selectedChatId) selectChat(selectedChatId, { updateUrl: false });
    } catch (error) {
      if (list) list.innerHTML = '<div class="crm-chat-list-state is-error"><strong>Не удалось загрузить чаты</strong><span>Обновите страницу или повторите позже.</span></div>';
    }
  }

  function renderConversationShell(chat) {
    var area = document.getElementById('chatArea');
    if (!area) return;
    currentChat = chat;
    var participants = Array.isArray(chat.participants) ? chat.participants : [];
    var participantText = participants.length + ' ' + plural(participants.length, 'участник', 'участника', 'участников');
    var isStandalone = (String(chat.created_by_user_id || '') !== '');
    var isArchived = (chat.is_archived || !!chat.archived_at);
    area.innerHTML = '<div class="crm-chat-conversation-head">'
      + '<div class="crm-chat-head-main"><div class="crm-chat-conversation-type">' + esc(chatTypeLabel(chat)) + (isArchived ? ' · Архив' : '') + '</div><h2>' + esc(chatTitle(chat)) + '</h2><button type="button" class="crm-chat-participants-link" id="chatParticipantsBtn" title="Показать участников чата" aria-label="Показать участников чата">' + esc(participantText) + '</button></div>'
      + '<div class="crm-chat-head-actions">'
      + (isStandalone ? (isArchived ? '<button class="btn crm-btn-primary crm-btn-compact" type="button" id="restoreChatBtn" title="Восстановить чат" aria-label="Восстановить чат"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span class="d-none d-md-inline"> Восстановить</span></button>' : '<button class="btn crm-btn-danger-soft crm-btn-compact" type="button" id="archiveChatBtn" title="Архивировать чат" aria-label="Архивировать чат"><i class="fa-solid fa-box-archive" aria-hidden="true"></i><span class="d-none d-md-inline"> В архив</span></button>') : '')
      + '<button class="btn crm-icon-btn" type="button" id="favoriteChatBtn" title="' + (Number(chat.is_favorite || 0) ? 'Убрать из избранного' : 'В избранное') + '" aria-label="' + (Number(chat.is_favorite || 0) ? 'Убрать чат из избранного' : 'Добавить чат в избранное') + '"><i class="' + (Number(chat.is_favorite || 0) ? 'fa-solid' : 'fa-regular') + ' fa-star" aria-hidden="true"></i></button>'
      + '<button class="btn crm-icon-btn" type="button" id="muteChatBtn" title="' + (isMuted(chat) ? 'Включить уведомления' : 'Отключить уведомления') + '" aria-label="' + (isMuted(chat) ? 'Включить уведомления чата' : 'Отключить уведомления чата') + '"><i class="fa-solid ' + (isMuted(chat) ? 'fa-bell-slash' : 'fa-bell') + '" aria-hidden="true"></i></button>'
      + '<button class="btn crm-btn-muted d-md-none" type="button" id="backToChatsBtn" aria-label="Вернуться к списку чатов">К списку</button></div>'
      + '</div>'
      + '<div class="crm-chat-messages" id="msgArea" aria-live="polite"><div class="crm-chat-list-state">Загрузка сообщений...</div></div>'
      + (isArchived ? '' : '<div class="crm-chat-compose"><div class="text-danger small d-none" id="chatSendError" aria-live="polite"></div><div class="crm-chat-reply-preview d-none" id="replyPreview"></div><div id="mentionPopup" class="crm-chat-mention-popup d-none" role="listbox" aria-label="Упомянуть участника"></div><div class="crm-chat-picker d-none" id="emojiPicker"></div>'
      + '<div class="crm-chat-compose-row"><button class="btn crm-icon-btn" type="button" id="attachChatFileBtn" aria-label="Прикрепить файл" title="Прикрепить файл"><i class="fa-solid fa-paperclip"></i></button><button class="btn crm-icon-btn" type="button" id="emojiChatBtn" aria-label="Эмоджи и стикеры" title="Эмоджи и стикеры" aria-expanded="false" aria-controls="emojiPicker"><i class="fa-regular fa-face-smile"></i></button><input class="d-none" type="file" id="chatFileInput" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,image/jpeg,image/png,image/gif,image/webp,application/pdf,text/plain,text/csv,application/zip"><label class="visually-hidden" for="msgInput">Сообщение</label><textarea class="form-control crm-chat-message-input" id="msgInput" rows="1" maxlength="4000" placeholder="Сообщение..."></textarea><button class="btn crm-btn-primary crm-chat-send-btn" type="button" id="sendChatMessageBtn" disabled aria-label="Отправить сообщение" title="Отправить сообщение"><i class="fa-solid fa-paper-plane"></i></button></div><div class="crm-chat-compose-hint">Enter — отправить, Shift+Enter — новая строка. @логин — упоминание.</div></div>')
      + '</div>';
    renderedChatId = selectedChatId;
    bindComposer();
    bindChatHeader(chat);
    var back = document.getElementById('backToChatsBtn');
    if (back) back.addEventListener('click', function () { document.getElementById('chatRoot').classList.remove('is-conversation-open'); });
  }

  function renderReplyQuote(message) {
    var sender = esc(message.reply_sender_name || message.reply_sender_login || 'Сообщение');
    var text = message.reply_text;
    if (text != null && text !== '') return '<strong>' + sender + '</strong><span>' + esc(text) + '</span>';
    var original = findMessage(message.reply_public_id);
    if (original) {
      if (original.deleted_at) return '<strong>' + sender + '</strong><span class="crm-chat-deleted-text">Сообщение удалено</span>';
      if (original.text && original.text.trim()) return '<strong>' + sender + '</strong><span>' + esc(original.text) + '</span>';
      var attachments = Array.isArray(original.attachments) ? original.attachments : [];
      if (attachments.length) {
        var names = attachments.map(function (f) { return esc(f.original_name || 'Файл'); }).slice(0, 3).join(', ');
        if (attachments.length > 3) names += ' и ещё ' + (attachments.length - 3);
        return '<strong>' + sender + '</strong><span><i class="fa-solid fa-paperclip" aria-hidden="true"></i> ' + names + '</span>';
      }
    }
    return '<strong>' + sender + '</strong><span>Без текста</span>';
  }

  function messageNumericId(message) {
    return Number(message && (message.message_seq || message.id) ? (message.message_seq || message.id) : 0) || 0;
  }

  function updateLastMessageId(messages) {
    lastMessageId = (messages || []).reduce(function (max, message) {
      return Math.max(max, messageNumericId(message));
    }, lastMessageId || 0);
  }

  function messageRevisionKey(message) {
    return [
      message && message.public_id || '',
      message && message.text || '',
      message && message.edited_at || '',
      message && message.deleted_at || '',
      message && message.reply_public_id || '',
      JSON.stringify(message && message.attachments || [])
    ].join('|');
  }

  function isNearBottom(box) {
    return !box || (box.scrollHeight - box.scrollTop - box.clientHeight) < 96;
  }

  function renderMessage(message) {
      var sender = message.sender_name || message.sender_login || 'Пользователь';
      var own = Number(message.is_own || 0) === 1;
      var deleted = !!message.deleted_at;
      var canEdit = own && !deleted && canModifyMessage(message);
      return '<article class="crm-chat-message' + (own ? ' is-own' : '') + (deleted ? ' is-deleted' : '') + '" data-message-id="' + esc(message.public_id || '') + '">'
        + '<div class="crm-chat-message-meta"><strong>' + esc(sender) + '</strong><time>' + esc(formatTime(message.created_at)) + '</time></div>'
         + (message.reply_public_id ? '<button type="button" class="crm-chat-quote" data-scroll-message="' + esc(message.reply_public_id) + '" title="Перейти к исходному сообщению" aria-label="Перейти к исходному сообщению">' + renderReplyQuote(message) + '</button>' : '')
        + (deleted ? '<p class="crm-chat-deleted-text">Сообщение удалено</p>' : '<p>' + renderMessageText(message.text || '') + '</p>')
        + renderAttachments(Array.isArray(message.attachments) ? message.attachments : [])
        + '<div class="crm-chat-message-foot">' + (message.edited_at && !deleted ? '<span>изменено</span>' : '') + '<button type="button" data-reply-message="' + esc(message.public_id || '') + '" title="Ответить на сообщение" aria-label="Ответить на сообщение">Ответить</button>' + (canEdit ? '<button type="button" data-edit-message="' + esc(message.public_id || '') + '" title="Изменить сообщение" aria-label="Изменить сообщение">Изменить</button><button type="button" data-delete-message="' + esc(message.public_id || '') + '" title="Удалить сообщение" aria-label="Удалить сообщение">Удалить</button>' : '') + '</div></article>';
  }

  function renderMessages(messages) {
    var box = document.getElementById('msgArea');
    if (!box) return;
    currentMessages = messages;
    lastMessageId = 0;
    updateLastMessageId(messages);
    if (!messages.length) {
      box.innerHTML = '<div class="crm-chat-empty crm-chat-empty--small"><strong>Сообщений пока нет</strong><span>Напишите первое сообщение в этом диалоге.</span></div>';
      return;
    }
    box.innerHTML = messages.map(renderMessage).join('');
    bindMessageActions();
    box.scrollTop = box.scrollHeight;
  }

  function appendMessages(messages) {
    var box = document.getElementById('msgArea');
    if (!box || !messages.length) return;
    var known = new Set(currentMessages.map(function (message) { return String(message.public_id || ''); }));
    var fresh = messages.filter(function (message) {
      var publicId = String(message.public_id || '');
      return publicId && !known.has(publicId);
    });
    if (!fresh.length) return;
    if (!currentMessages.length || box.querySelector('.crm-chat-empty')) {
      renderMessages(currentMessages.concat(fresh));
      return;
    }
    var shouldStick = isNearBottom(box);
    currentMessages = currentMessages.concat(fresh);
    updateLastMessageId(fresh);
    box.insertAdjacentHTML('beforeend', fresh.map(renderMessage).join(''));
    bindMessageActions(box);
    if (shouldStick) box.scrollTop = box.scrollHeight;
  }

  function syncVisibleMessages(messages) {
    var box = document.getElementById('msgArea');
    if (!box || !messages.length) return;
    var byId = new Map(currentMessages.map(function (message) { return [String(message.public_id || ''), message]; }));
    var changed = [];
    messages.forEach(function (message) {
      var id = String(message.public_id || '');
      if (!id) return;
      var current = byId.get(id);
      if (!current) {
        changed.push(message);
        return;
      }
      if (messageRevisionKey(current) !== messageRevisionKey(message)) {
        byId.set(id, message);
        changed.push(message);
      }
    });
    if (!changed.length) return;

    var shouldStick = isNearBottom(box);
    var fresh = [];
    changed.forEach(function (message) {
      var id = String(message.public_id || '');
      var existing = box.querySelector('[data-message-id="' + CSS.escape(id) + '"]');
      if (!existing) {
        fresh.push(message);
        return;
      }
      existing.outerHTML = renderMessage(message);
      var replacement = box.querySelector('[data-message-id="' + CSS.escape(id) + '"]');
      if (replacement) bindMessageActions(replacement);
    });
    currentMessages = currentMessages.map(function (message) {
      return byId.get(String(message.public_id || '')) || message;
    });
    if (fresh.length) appendMessages(fresh);
    updateLastMessageId(messages);
    if (shouldStick) box.scrollTop = box.scrollHeight;
  }

  async function syncMessagesAfterLocalChange(mode) {
    if (!selectedChatId) return;
    if (mode === 'append' && lastMessageId > 0) {
      var afterEnv = await request('api/v1/chats/' + encodeURIComponent(selectedChatId) + '/messages', { method: 'GET', query: { after_id: lastMessageId, limit: 50 } });
      var fresh = (afterEnv.data && afterEnv.data.items) || [];
      if (fresh.length) {
        appendMessages(fresh);
        return;
      }
    }
    await refreshVisibleMessageState(selectedChatId);
  }

  function renderMessageText(text) {
    var safe = esc(text).replace(/\n/g, '<br>');
    safe = safe.replace(/(^|\s)@([\p{L}\p{N}._-]{2,80})/gu, '$1<span class="crm-chat-mention">@$2</span>');
    safe = safe.replace(/\[стикер: ([^\]]+)\]/g, '<span class="crm-chat-sticker">$1</span>');
    safe = safe.replace(/\[gif: ([^\]]+)\]/g, '<span class="crm-chat-sticker">$1</span>');
    return safe;
  }

  function renderAttachments(files) {
    if (!files.length) return '';
    return '<div class="crm-chat-attachments">' + files.map(function (file) {
      var url = file.download_url || '#';
      if (String(file.mime_type || '').indexOf('image/') === 0) return '<button type="button" class="crm-chat-image-attachment" data-image-url="' + esc(url) + '" data-image-name="' + esc(file.original_name || 'Изображение') + '" title="Открыть изображение: ' + esc(file.original_name || 'Изображение') + '" aria-label="Открыть изображение: ' + esc(file.original_name || 'Изображение') + '"><img src="' + esc(url) + '" alt="' + esc(file.original_name || 'Изображение') + '"></button>';
      return '<a class="crm-chat-file-attachment" href="' + esc(url) + '" download title="Скачать файл: ' + esc(file.original_name || 'Файл') + '" aria-label="Скачать файл: ' + esc(file.original_name || 'Файл') + '"><i class="fa-solid fa-file"></i><span>' + esc(file.original_name || 'Файл') + '</span><small>' + esc(formatFileSize(file.size_bytes || 0)) + '</small></a>';
    }).join('') + '</div>';
  }

  function formatFileSize(bytes) {
    bytes = Number(bytes || 0);
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' МБ';
    if (bytes >= 1024) return Math.round(bytes / 1024) + ' КБ';
    return bytes + ' Б';
  }

  function canModifyMessage(message) {
    var date = new Date(String(message.created_at || '').replace(' ', 'T'));
    return !Number.isNaN(date.getTime()) && (Date.now() - date.getTime()) <= 30 * 60 * 1000;
  }

  async function selectChat(id, options) {
    options = options || {};
    if (!id || loadingMessages) return;
    if (pollTimer) { window.clearTimeout(pollTimer); pollTimer = null; }
    setSelectedChatId(id, options.updateUrl !== false);
    document.getElementById('chatRoot').classList.add('is-conversation-open');
    await loadMessages(id, options);
  }

  async function loadMessages(id, options) {
    options = options || {};
    if (!id) return;
    if (loadingMessages) return;
    loadingMessages = true;
    try {
      var detailPromise = request('api/v1/chats/' + encodeURIComponent(id), { method: 'GET' });
      var messagesPromise = request('api/v1/chats/' + encodeURIComponent(id) + '/messages', { method: 'GET', query: { limit: 80 } });
      var detailEnv = await detailPromise;
      var chat = detailEnv.data.chat || {};
      renderConversationShell(chat);
      if (chat.is_archived || chat.archived_at) {
        var archivedBox = document.getElementById('msgArea');
        if (archivedBox) archivedBox.innerHTML = '<div class="crm-chat-empty crm-chat-empty--small"><strong>Чат в архиве</strong><span>Восстановите чат, чтобы продолжить переписку.</span></div>';
        currentMessages = [];
        lastMessageId = 0;
        return;
      }
      var messagesEnv = await messagesPromise;
      renderMessages((messagesEnv.data && messagesEnv.data.items) || []);
      markRead();
      loadChats({ silent: true });
    } catch (error) {
      var area = document.getElementById('chatArea');
      if (area) area.innerHTML = '<div class="crm-chat-empty"><strong>Не удалось открыть чат</strong><span>Проверьте доступ или попробуйте позже.</span></div>';
    } finally {
      loadingMessages = false;
    }
  }

  async function pollMessagesIncrementally() {
    if (!selectedChatId || pollingMessages || loadingMessages || !lastMessageId) return;
    var idAtStart = selectedChatId;
    pollingMessages = true;
    try {
      var messagesEnv = await request('api/v1/chats/' + encodeURIComponent(idAtStart) + '/messages', { method: 'GET', query: { after_id: lastMessageId, limit: 50 } });
      if (idAtStart !== selectedChatId) return;
      var items = (messagesEnv.data && messagesEnv.data.items) || [];
      if (items.length) {
        appendMessages(items);
        messageRevisionTick = 0;
        await markRead();
        await loadChats({ silent: true });
        return;
      }
      messageRevisionTick += 1;
      if (messageRevisionTick >= 5) {
        messageRevisionTick = 0;
        await refreshVisibleMessageState(idAtStart);
      }
    } catch (error) {
    } finally {
      pollingMessages = false;
    }
  }

  async function refreshVisibleMessageState(idAtStart) {
    if (!idAtStart || idAtStart !== selectedChatId) return;
    var messagesEnv = await request('api/v1/chats/' + encodeURIComponent(idAtStart) + '/messages', { method: 'GET', query: { limit: 80 } });
    if (idAtStart !== selectedChatId) return;
    syncVisibleMessages((messagesEnv.data && messagesEnv.data.items) || []);
  }

  function setSendError(text) {
    var el = document.getElementById('chatSendError');
    if (!el) return;
    el.textContent = text || '';
    el.classList.toggle('d-none', !text);
  }

  function bindComposer() {
    var input = document.getElementById('msgInput');
    var button = document.getElementById('sendChatMessageBtn');
    if (!input || !button) return;
    function sync() {
      button.disabled = !input.value.trim();
      input.style.height = input.value ? 'auto' : '44px';
      if (input.value) input.style.height = Math.min(Math.max(44, input.scrollHeight), 120) + 'px';
      if (input.value.trim()) setSendError('');
    }
    input.addEventListener('input', sync);
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMsg();
      }
    });
    button.addEventListener('click', sendMsg);
    document.getElementById('attachChatFileBtn').addEventListener('click', function () { document.getElementById('chatFileInput').click(); });
    document.getElementById('chatFileInput').addEventListener('change', uploadChatFile);
    document.getElementById('emojiChatBtn').addEventListener('click', toggleEmojiPicker);
    renderReplyPreview();
    renderEmojiPicker();
    bindMentionAutocomplete(input);
    sync();
  }

  window.sendMsg = async function () {
    var input = document.getElementById('msgInput');
    var button = document.getElementById('sendChatMessageBtn');
    if (!input || !button) return;
    var text = input.value.trim();
    if (!text) { setSendError('Напишите сообщение.'); button.disabled = true; return; }
    if (!selectedChatId) { setSendError('Выберите чат.'); return; }
    input.disabled = true;
    button.disabled = true;
    setSendError('');
    try {
      if (editingMessage) {
        await request('api/v1/chats/' + encodeURIComponent(selectedChatId) + '/messages/' + encodeURIComponent(editingMessage.public_id), { method: 'PATCH', body: { text: text } });
        editingMessage = null;
        await syncMessagesAfterLocalChange('sync');
      } else {
        await request('api/v1/chats/' + encodeURIComponent(selectedChatId) + '/messages', { method: 'POST', body: { text: text, reply_to_message_public_id: replyToMessage ? replyToMessage.public_id : '' } });
        replyToMessage = null;
        await syncMessagesAfterLocalChange('append');
      }
      input.value = '';
      input.style.height = 'auto';
      renderReplyPreview();
      await loadChats({ silent: true });
    } catch (error) {
      setSendError('Не удалось отправить сообщение. Попробуйте еще раз.');
    } finally {
      input.disabled = false;
      input.focus();
      button.disabled = !input.value.trim();
    }
  };

  async function uploadChatFile(event) {
    var input = event.target;
    var file = input.files && input.files[0];
    if (!file || !selectedChatId) return;
    var data = new FormData();
    data.append('file', file);
    try {
      await request('api/v1/chats/' + encodeURIComponent(selectedChatId) + '/attachments', { method: 'POST', body: data });
      input.value = '';
      await syncMessagesAfterLocalChange('append');
      await loadChats({ silent: true });
    } catch (error) {
      setSendError('Не удалось отправить файл.');
      input.value = '';
    }
  }

  function bindChatHeader(chat) {
    document.getElementById('favoriteChatBtn').addEventListener('click', async function () {
      await request('api/v1/chats/' + encodeURIComponent(selectedChatId) + '/settings', { method: 'PATCH', body: { is_favorite: !(Number(chat.is_favorite || 0) === 1) } });
      await loadMessages(selectedChatId, { silent: true });
      await loadChats({ silent: true });
    });
    document.getElementById('muteChatBtn').addEventListener('click', async function () {
      var muted = isMuted(chat);
      if (!muted && !await showConfirmModal({
        title: 'Отключить уведомления?',
        body: 'Уведомления этого чата будут скрыты. Включить их обратно можно этой же кнопкой.',
        submitText: 'Отключить',
        danger: false
      })) return;
      await request('api/v1/chats/' + encodeURIComponent(selectedChatId) + '/settings', { method: 'PATCH', body: { is_muted: !muted } });
      await loadMessages(selectedChatId, { silent: true });
      await loadChats({ silent: true });
    });
    document.getElementById('chatParticipantsBtn').addEventListener('click', showParticipantsModal);

    var archiveBtn = document.getElementById('archiveChatBtn');
    if (archiveBtn) {
      archiveBtn.addEventListener('click', async function () {
        if (!await showConfirmModal({
          title: 'Архивировать чат?',
          body: 'Чат пропадет у всех участников. Восстановить его сможет создатель из списка архивных чатов.',
          submitText: 'В архив',
          danger: true
        })) return;
        archiveBtn.disabled = true;
        var chatToArchive = selectedChatId;
        setSelectedChatId('', true);
        if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
        try {
          await request('api/v1/chats/' + encodeURIComponent(chatToArchive) + '/archive', { method: 'POST' });
        } catch (e) {}
        document.getElementById('chatArea').innerHTML = '<div class="crm-chat-empty"><i class="fa-solid fa-box-archive" aria-hidden="true"></i><strong>Чат в архиве</strong><span>Вы можете восстановить его из списка архивных чатов.</span></div>';
        await loadChats({ silent: true });
        startPolling();
        archiveBtn.disabled = false;
      });
    }

    var restoreBtn = document.getElementById('restoreChatBtn');
    if (restoreBtn) {
      restoreBtn.addEventListener('click', async function () {
        restoreBtn.disabled = true;
        if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
        try {
          await request('api/v1/chats/' + encodeURIComponent(selectedChatId) + '/restore', { method: 'POST' });
          await loadMessages(selectedChatId, { silent: true });
          await loadChats({ silent: true });
        } catch (e) {}
        startPolling();
        restoreBtn.disabled = false;
      });
    }
  }

  function bindMessageActions(scope) {
    var box = document.getElementById('msgArea');
    if (!box) return;
    var root = scope || box;
    root.querySelectorAll('[data-reply-message]:not([data-chat-bound])').forEach(function (btn) {
      btn.setAttribute('data-chat-bound', '1');
      btn.addEventListener('click', function () {
        replyToMessage = findMessage(btn.getAttribute('data-reply-message'));
        editingMessage = null;
        renderReplyPreview();
        document.getElementById('msgInput').focus();
      });
    });
    root.querySelectorAll('[data-edit-message]:not([data-chat-bound])').forEach(function (btn) {
      btn.setAttribute('data-chat-bound', '1');
      btn.addEventListener('click', function () {
        editingMessage = findMessage(btn.getAttribute('data-edit-message'));
        replyToMessage = null;
        document.getElementById('msgInput').value = editingMessage ? editingMessage.text || '' : '';
        renderReplyPreview();
        document.getElementById('msgInput').focus();
      });
    });
    root.querySelectorAll('[data-delete-message]:not([data-chat-bound])').forEach(function (btn) {
      btn.setAttribute('data-chat-bound', '1');
      btn.addEventListener('click', async function () {
        if (!await showConfirmModal({
          title: 'Удалить сообщение?',
          body: 'Сообщение будет скрыто в чате, а действие сохранится в журнале изменений.',
          submitText: 'Удалить',
          danger: true
        })) return;
        await request('api/v1/chats/' + encodeURIComponent(selectedChatId) + '/messages/' + encodeURIComponent(btn.getAttribute('data-delete-message')), { method: 'DELETE' });
        await syncMessagesAfterLocalChange('sync');
        await loadChats({ silent: true });
      });
    });
    root.querySelectorAll('[data-scroll-message]:not([data-chat-bound])').forEach(function (btn) {
      btn.setAttribute('data-chat-bound', '1');
      btn.addEventListener('click', function () {
        var target = box.querySelector('[data-message-id="' + CSS.escape(btn.getAttribute('data-scroll-message')) + '"]');
        if (target) target.scrollIntoView({ block: 'center', behavior: 'smooth' });
      });
    });
    root.querySelectorAll('[data-image-url]:not([data-chat-bound])').forEach(function (btn) {
      btn.setAttribute('data-chat-bound', '1');
      btn.addEventListener('click', function () { showImageModal(btn.getAttribute('data-image-url'), btn.getAttribute('data-image-name')); });
    });
  }

  function findMessage(id) {
    return currentMessages.find(function (message) { return String(message.public_id || '') === String(id || ''); }) || null;
  }

  function renderReplyPreview() {
    var node = document.getElementById('replyPreview');
    if (!node) return;
    var source = editingMessage || replyToMessage;
    node.classList.toggle('d-none', !source);
    if (!source) { node.innerHTML = ''; return; }
    node.innerHTML = '<div><strong>' + (editingMessage ? 'Редактирование' : 'Ответ') + '</strong><span>' + esc(source.text || 'Сообщение') + '</span></div><button type="button" aria-label="Отменить"><i class="fa-solid fa-xmark"></i></button>';
    node.querySelector('button').addEventListener('click', function () {
      replyToMessage = null;
      editingMessage = null;
      document.getElementById('msgInput').value = '';
      renderReplyPreview();
    });
  }

  function renderEmojiPicker() {
    var picker = document.getElementById('emojiPicker');
    if (!picker) return;
    picker.innerHTML = '<div class="crm-chat-picker-section" aria-label="Эмоджи">' + emojiSet.map(function (item) { return '<button type="button" data-emoji="' + esc(item) + '" title="Добавить эмоджи ' + esc(item) + '" aria-label="Добавить эмоджи ' + esc(item) + '">' + esc(item) + '</button>'; }).join('') + '</div><div class="crm-chat-picker-section crm-chat-sticker-list" aria-label="Стикеры">' + stickerSet.map(function (item, index) { return '<button type="button" data-sticker="' + index + '" title="Отправить ' + esc(item.label) + '" aria-label="Отправить ' + esc(item.label) + '">' + esc(item.label) + '</button>'; }).join('') + '</div>';
    picker.querySelectorAll('[data-emoji]').forEach(function (btn) { btn.addEventListener('click', function () { insertAtCursor(btn.getAttribute('data-emoji')); }); });
    picker.querySelectorAll('[data-sticker]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var item = stickerSet[Number(btn.getAttribute('data-sticker'))];
        if (item) { document.getElementById('msgInput').value = item.text; sendMsg(); }
      });
    });
  }

  function toggleEmojiPicker() {
    var picker = document.getElementById('emojiPicker');
    var button = document.getElementById('emojiChatBtn');
    if (picker) {
      picker.classList.toggle('d-none');
      if (button) button.setAttribute('aria-expanded', String(!picker.classList.contains('d-none')));
    }
  }

  function insertAtCursor(text) {
    var input = document.getElementById('msgInput');
    if (!input) return;
    var start = input.selectionStart || input.value.length;
    var end = input.selectionEnd || input.value.length;
    input.value = input.value.slice(0, start) + text + input.value.slice(end);
    input.focus();
    input.selectionStart = input.selectionEnd = start + text.length;
    input.dispatchEvent(new Event('input'));
  }

  function showParticipantsModal() {
    var participants = (currentChat && Array.isArray(currentChat.participants)) ? currentChat.participants : [];
    showInfoModal('Участники чата', '<div class="crm-chat-participants-list">' + participants.map(function (user) {
      var role = String(user.role || '') === 'admin' ? 'Администратор' : 'Участник';
      return '<div><strong>' + esc(user.full_name || user.login || 'Пользователь') + '</strong><span>' + esc(role + (user.login ? ' · @' + user.login : '')) + '</span></div>';
    }).join('') + '</div>');
  }

  function showImageModal(url, name) {
    showInfoModal(name || 'Изображение', '<img class="crm-chat-modal-image" src="' + esc(url) + '" alt="' + esc(name || 'Изображение') + '">');
  }

  function showInfoModal(title, body) {
    var old = document.getElementById('chatInfoModal');
    if (old) old.remove();
    var modal = document.createElement('div');
    modal.id = 'chatInfoModal';
    modal.className = 'crm-chat-modal is-open';
    modal.innerHTML = '<div class="crm-chat-modal-panel crm-chat-info-panel"><div class="crm-chat-modal-head"><h2 class="h5 mb-0">' + esc(title) + '</h2><button class="btn-close" type="button" aria-label="Закрыть"></button></div>' + body + '</div>';
    document.body.appendChild(modal);
    modal.querySelector('.btn-close').addEventListener('click', function () { modal.remove(); });
    modal.addEventListener('click', function (event) { if (event.target === modal) modal.remove(); });
  }

  function showConfirmModal(options) {
    options = options || {};
    return new Promise(function (resolve) {
      var old = document.getElementById('chatConfirmModal');
      if (old) old.remove();
      var modal = document.createElement('div');
      modal.id = 'chatConfirmModal';
      modal.className = 'crm-chat-modal is-open';
      modal.setAttribute('role', 'dialog');
      modal.setAttribute('aria-modal', 'true');
      modal.setAttribute('aria-labelledby', 'chatConfirmTitle');
      modal.innerHTML = '<div class="crm-chat-modal-panel crm-chat-confirm-panel">'
        + '<div class="crm-chat-modal-head"><h2 class="h5 mb-0" id="chatConfirmTitle">' + esc(options.title || 'Подтвердите действие') + '</h2><button class="btn-close" type="button" aria-label="Закрыть"></button></div>'
        + '<p class="crm-chat-confirm-text">' + esc(options.body || 'Продолжить?') + '</p>'
        + '<div class="crm-chat-modal-actions"><button class="btn crm-btn-muted" type="button" data-chat-confirm-cancel>Отмена</button><button class="btn ' + (options.danger ? 'crm-btn-danger-soft' : 'crm-btn-primary') + '" type="button" data-chat-confirm-submit>' + esc(options.submitText || 'Подтвердить') + '</button></div>'
        + '</div>';
      function close(value) {
        modal.remove();
        resolve(value);
      }
      document.body.appendChild(modal);
      modal.querySelector('.btn-close').addEventListener('click', function () { close(false); });
      modal.querySelector('[data-chat-confirm-cancel]').addEventListener('click', function () { close(false); });
      modal.querySelector('[data-chat-confirm-submit]').addEventListener('click', function () { close(true); });
      modal.addEventListener('click', function (event) { if (event.target === modal) close(false); });
      modal.querySelector('[data-chat-confirm-cancel]').focus();
    });
  }

  function markRead() {
    if (!selectedChatId) return;
    return request('api/v1/chats/' + encodeURIComponent(selectedChatId) + '/read', { method: 'POST' }).catch(function () {});
  }

  function setChatError(text) {
    var el = document.getElementById('newChatError');
    if (!el) return;
    el.textContent = text || '';
    el.classList.toggle('d-none', !text);
  }

  function openNewChatModal() {
    var modal = document.getElementById('newChatModal');
    setChatError('');
    document.getElementById('newChatTitle').value = '';
    chatParticipants = [];
    renderChatParticipants();
    loadAllUsers();
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    window.setTimeout(function () { var input = document.getElementById('chatParticipantSearch'); if (input) input.focus(); }, 0);
  }

  function closeNewChatModal() {
    var modal = document.getElementById('newChatModal');
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    setChatError('');
    document.getElementById('chatParticipantSearch').value = '';
    document.getElementById('chatSearchResults').hidden = true;
  }

  async function createChat() {
    var title = document.getElementById('newChatTitle').value.trim();
    var button = document.getElementById('createChatBtn');
    if (chatParticipants.length === 0) { setChatError('Добавьте хотя бы одного участника.'); return; }
    if (!title) { setChatError('Укажите название чата.'); return; }
    button.disabled = true;
    setChatError('');
    var newPublicId;
    try {
      var env = await request('api/v1/chats', { method: 'POST', body: {
        type: 'group',
        title: title,
        participant_public_ids: chatParticipants.map(function(p) { return p.public_id; })
      }});
      newPublicId = env.data ? env.data.public_id : null;
    } catch (error) {
      var msg = (error.data && error.data.message) || 'Не удалось создать чат. Проверьте права доступа.';
      setChatError(msg);
      button.disabled = false;
      return;
    }
    if (newPublicId) {
      document.getElementById('chatRoot').classList.add('is-conversation-open');
      await selectChat(newPublicId, { updateUrl: true });
      closeNewChatModal();
    } else {
      setChatError('Не удалось создать чат.');
    }
    button.disabled = false;
  }

  function bindPage() {
    document.getElementById('newChatBtn').addEventListener('click', openNewChatModal);
    document.getElementById('closeChatModal').addEventListener('click', closeNewChatModal);
    document.getElementById('closeChatModalIcon').addEventListener('click', closeNewChatModal);
    document.getElementById('createChatBtn').addEventListener('click', createChat);
    document.getElementById('newChatModal').addEventListener('click', function (event) { if (event.target === this) closeNewChatModal(); });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && document.getElementById('newChatModal').classList.contains('is-open')) closeNewChatModal();
      var info = document.getElementById('chatInfoModal');
      if (event.key === 'Escape' && info) info.remove();
    });
    document.getElementById('chatList').addEventListener('click', function (event) {
      var item = event.target.closest('[data-chat-id]');
      if (!item) return;
      selectChat(item.getAttribute('data-chat-id'), { updateUrl: true });
    });
    document.getElementById('chatSearchInput').addEventListener('input', function (event) {
      chatSearch = event.target.value || '';
      renderChatList();
    });
    document.getElementById('toggleArchivedBtn').addEventListener('click', async function () {
      showArchived = !showArchived;
      var btn = document.getElementById('toggleArchivedBtn');
      btn.setAttribute('aria-pressed', String(showArchived));
      btn.textContent = showArchived ? 'Активные' : 'Архив';
      btn.setAttribute('title', showArchived ? 'Показать активные чаты' : 'Показать архивные чаты');
      btn.setAttribute('aria-label', showArchived ? 'Показать активные чаты' : 'Показать архивные чаты');
      if (showArchived) {
        try {
          var env = await request('api/v1/chats', { method: 'GET', query: { archived: '1' } });
          var items = (env.data && env.data.items) || [];
          var list = document.getElementById('chatList');
          if (!items.length) {
            list.innerHTML = '<div class="crm-chat-list-state"><strong>Нет архивных чатов</strong><span>Архивированные чаты появятся здесь.</span></div>';
            return;
          }
          list.innerHTML = items.map(function (chat) {
            var id = String(chat.public_id || '');
            var title = chatTitle(chat);
            return '<button type="button" class="crm-chat-item has-unread" data-chat-id="' + esc(id) + '" role="listitem" title="Открыть архивный чат: ' + esc(title) + '" aria-label="Открыть архивный чат: ' + esc(title) + '">'
              + '<span class="crm-chat-item-main"><strong>' + esc(chatTitle(chat)) + '</strong><small>' + esc(lastMessageText(chat)) + '</small></span>'
              + '<span class="crm-chat-item-meta"><small>Архив</small></span>'
              + '</button>';
          }).join('');
        } catch (e) {
          document.getElementById('chatList').innerHTML = '<div class="crm-chat-list-state is-error"><strong>Ошибка загрузки архива</strong></div>';
        }
      } else {
        setSelectedChatId('', true);
        renderChatList();
      }
    });
    bindChatParticipantSearch();
  }

  function pollingDelay() {
    return document.hidden ? 20000 : 6000;
  }

  async function runPollTick() {
    var input = document.getElementById('msgInput');
    var userTyping = input && document.activeElement === input && input.value.trim();
    try {
      chatListPollTick += 1;
      var shouldRefreshChatList = !selectedChatId || chatListPollTick >= 3;
      if (!showArchived && shouldRefreshChatList && !pollingChats) {
        chatListPollTick = 0;
        pollingChats = true;
        try {
          await loadChats({ silent: true });
        } finally {
          pollingChats = false;
        }
      }
      if (!showArchived && selectedChatId && !userTyping) await pollMessagesIncrementally();
    } finally {
      startPolling();
    }
  }

  function startPolling() {
    if (pollTimer) window.clearTimeout(pollTimer);
    pollTimer = window.setTimeout(runPollTick, pollingDelay());
  }

  function bindMentionAutocomplete(input) {
    var popup = document.getElementById('mentionPopup');
    if (!input || !popup) return;
    var activeIdx = -1;
    var filterText = '';
    var atPos = -1;

    function hidePopup() {
      popup.classList.add('d-none');
      activeIdx = -1;
      filterText = '';
      atPos = -1;
    }

    function getParticipants() {
      var p = (currentChat && Array.isArray(currentChat.participants)) ? currentChat.participants : [];
      var ownLogin = '';
      if (window.CRM && window.CRM.user) {
        ownLogin = String(window.CRM.user.login || '');
      }
      if (!ownLogin) return p;
      return p.filter(function(u) { return String(u.login || '') !== ownLogin; });
    }

    function showPopup(items) {
      if (!items.length) { hidePopup(); return; }
      activeIdx = 0;
      popup.innerHTML = items.map(function (u, i) {
        var login = u.login || '';
        return '<button type="button" class="crm-chat-mention-item' + (i === 0 ? ' is-active' : '') + '" data-idx="' + i + '" role="option"><strong>@' + esc(login) + '</strong><span>' + esc(u.full_name || '') + '</span></button>';
      }).join('');
      popup.classList.remove('d-none');
      popup.querySelectorAll('[data-idx]').forEach(function (btn) {
        btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
        btn.addEventListener('mouseenter', function () {
          activeIdx = parseInt(btn.getAttribute('data-idx'), 10);
          popup.querySelectorAll('[data-idx]').forEach(function (b, i) { b.classList.toggle('is-active', i === activeIdx); });
        });
        btn.addEventListener('click', function () {
          insertMention(btn);
        });
      });
    }

    function insertMention(btn) {
      if (!btn) return;
      var login = (btn.querySelector('strong') || {}).textContent || '';
      login = login.replace(/^@/, '').trim();
      if (!login) return;
      var before = input.value.slice(0, atPos);
      var after = input.value.slice(input.selectionStart);
      var spaceBefore = before.length > 0 && !/\s$/.test(before) ? ' ' : '';
      input.value = before + spaceBefore + '@' + login + ' ' + after;
      input.focus();
      var cursor = (before + spaceBefore + '@' + login + ' ').length;
      input.selectionStart = input.selectionEnd = cursor;
      input.dispatchEvent(new Event('input'));
      hidePopup();
    }

    input.addEventListener('input', function () {
      var val = input.value;
      var cursor = input.selectionStart;
      var beforeCursor = val.slice(0, cursor);
      var atIdx = beforeCursor.lastIndexOf('@');

      if (atIdx === -1 || atIdx < cursor - 1 && beforeCursor.slice(atIdx + 1).indexOf(' ') !== -1) {
        hidePopup();
        return;
      }

      filterText = beforeCursor.slice(atIdx + 1).toLowerCase();
      if (filterText.indexOf(' ') !== -1) { hidePopup(); return; }

      atPos = atIdx;
      var participants = getParticipants();
      if (filterText) {
        participants = participants.filter(function (u) {
          return (u.login || '').toLowerCase().indexOf(filterText) === 0
            || (u.full_name || '').toLowerCase().indexOf(filterText) !== -1;
        });
      }
      if (participants.length === 0) { hidePopup(); return; }
      showPopup(participants.slice(0, 8));
    });

    input.addEventListener('keydown', function (e) {
      if (popup.classList.contains('d-none')) return;
      var items = popup.querySelectorAll('[data-idx]');
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIdx = Math.min(activeIdx + 1, items.length - 1);
        items.forEach(function (b, i) { b.classList.toggle('is-active', i === activeIdx); });
        items[activeIdx].scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIdx = Math.max(activeIdx - 1, 0);
        items.forEach(function (b, i) { b.classList.toggle('is-active', i === activeIdx); });
        items[activeIdx].scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'Enter' || e.key === 'Tab') {
        e.preventDefault();
        insertMention(items[activeIdx]);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        hidePopup();
      }
    });

    input.addEventListener('blur', function () {
      window.setTimeout(hidePopup, 150);
    });
  }

  function getUserInitials(name) {
    var parts = (name || '').trim().split(/\s+/);
    return parts.length >= 2 ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase() : (parts[0] || '??').slice(0, 2).toUpperCase();
  }

  function renderChatParticipants() {
    var list = document.getElementById('chatParticipantList');
    var empty = document.getElementById('chatParticipantEmpty');
    var count = document.getElementById('chatParticipantCount');
    if (count) count.textContent = String(chatParticipants.length);
    if (empty) empty.hidden = chatParticipants.length > 0;
    if (!list) return;
    list.innerHTML = chatParticipants.map(function (p) {
      var initials = getUserInitials(p.full_name || p.login);
      return '<div class="team-participant-row is-adding" data-user-id="' + esc(p.public_id) + '">'
        + '<div class="team-participant-avatar">' + esc(initials) + '</div>'
        + '<div class="team-participant-info">'
        + '<div class="team-participant-name">' + esc(p.full_name || p.login || p.public_id) + '</div>'
        + '<div class="team-participant-detail">' + esc(p.login || '') + '</div>'
        + '</div>'
        + '<button type="button" class="team-participant-remove" data-remove="' + esc(p.public_id) + '" aria-label="Удалить"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-xmark"></i></span></button>'
        + '</div>';
    }).join('');
    list.querySelectorAll('.team-participant-remove').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var uid = btn.getAttribute('data-remove');
        chatParticipants = chatParticipants.filter(function (p) { return p.public_id !== uid; });
        renderChatParticipants();
      });
    });
  }

  function addChatParticipant(user) {
    if (chatParticipants.some(function (p) { return p.public_id === user.public_id; })) return;
    chatParticipants.push(user);
    renderChatParticipants();
    var dropdown = document.getElementById('chatSearchResults');
    if (dropdown) dropdown.hidden = true;
    document.getElementById('chatParticipantSearch').value = '';
  }

  function showChatSearchResults(items, query) {
    var dropdown = document.getElementById('chatSearchResults');
    if (!dropdown) return;
    if (!items.length) {
      dropdown.innerHTML = '<div class="team-search-no-results"><span class="crm-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>Ничего не найдено по запросу «' + esc(query) + '»</div>';
      dropdown.hidden = false;
      return;
    }
    dropdown.innerHTML = items.map(function (u) {
      var initials = getUserInitials(u.full_name || u.login);
      var added = chatParticipants.some(function (p) { return p.public_id === u.public_id; });
      return '<div class="team-search-item' + (added ? ' is-added' : '') + '" data-user-id="' + esc(u.public_id) + '">'
        + '<div class="team-search-item-avatar">' + esc(initials) + '</div>'
        + '<div class="team-search-item-info">'
        + '<div class="team-search-item-name">' + esc(u.full_name || u.login || u.public_id) + '</div>'
        + '<div class="team-search-item-detail">' + esc(u.login || '') + '</div>'
        + '</div>'
        + '<div class="team-search-item-action">' + (added ? '<span class="crm-chip">Добавлен</span>' : '<button type="button" class="btn crm-btn-primary crm-btn-compact">+ Добавить</button>') + '</div>'
        + '</div>';
    }).join('');
    dropdown.hidden = false;
    dropdown.querySelectorAll('.team-search-item:not(.is-added)').forEach(function (item) {
      item.addEventListener('click', function () {
        var uid = item.getAttribute('data-user-id');
        var user = allUsers.find(function (u) { return u.public_id === uid; });
        if (user) addChatParticipant(user);
      });
    });
  }

  function filterUsers(query) {
    var q = query.toLowerCase();
    return allUsers.filter(function (u) {
      var name = (u.full_name || '').toLowerCase();
      var login = (u.login || '').toLowerCase();
      return name.indexOf(q) !== -1 || login.indexOf(q) !== -1;
    }).slice(0, 15);
  }

  function loadAllUsers(cb) {
    if (allUsers.length) { if (cb) cb(); return; }
    request('api/v1/users', { method: 'GET' }).then(function (env) {
      allUsers = Array.isArray(env.data) ? env.data : (env.data && env.data.items) || [];
      if (cb) cb();
    }).catch(function () { if (cb) cb(); });
  }

  function bindChatParticipantSearch() {
    var input = document.getElementById('chatParticipantSearch');
    if (!input) return;
    input.addEventListener('input', function () {
      if (chatSearchTimer) clearTimeout(chatSearchTimer);
      chatSearchTimer = setTimeout(function () {
        var q = input.value.trim();
        var dropdown = document.getElementById('chatSearchResults');
        if (!q) { if (dropdown) dropdown.hidden = true; return; }
        if (!allUsers.length) {
          loadAllUsers(function () { showChatSearchResults(filterUsers(q), q); });
          return;
        }
        showChatSearchResults(filterUsers(q), q);
      }, 250);
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { input.value = ''; var d = document.getElementById('chatSearchResults'); if (d) d.hidden = true; }
    });
    document.addEventListener('click', function (e) {
      var dropdown = document.getElementById('chatSearchResults');
      if (dropdown && !dropdown.hidden && !dropdown.contains(e.target) && e.target !== input) {
        dropdown.hidden = true;
      }
    });
  }

  bindPage();
  document.addEventListener('visibilitychange', startPolling);
  loadChats({ initial: true });
  startPolling();
})();
</script>
</body>
