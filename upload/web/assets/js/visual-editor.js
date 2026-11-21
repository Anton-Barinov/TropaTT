window.CRM = window.CRM || {};
window.CRM.VisualEditor = (function () {
  'use strict';

  // ---------------------------------------------------------------------------
  //  Helpers
  // ---------------------------------------------------------------------------

  function generateId() {
    return 'b_' + Math.random().toString(36).substring(2, 10) + Date.now().toString(36);
  }

  function clamp(val, min, max) {
    return Math.min(Math.max(val, min), max);
  }

  function sanitizeLinkHref(href) {
    var value = String(href || '').trim();
    if (!value) return '';
    if (value[0] === '#') return value;
    if (value[0] === '/') return value;
    if (/^https?:\/\//i.test(value)) return value;
    if (/^mailto:/i.test(value)) return value;
    if (/^tel:/i.test(value)) return value;
    return '';
  }

  function formatFileSize(bytes) {
    if (!bytes || bytes < 0) return '0 B';
    var units = ['B', 'KB', 'MB', 'GB'];
    var i = 0;
    var size = bytes;
    while (size >= 1024 && i < units.length - 1) {
      size /= 1024;
      i++;
    }
    return size.toFixed(i > 0 ? 1 : 0) + ' ' + units[i];
  }

  function htmlToParagraphs(text) {
    if (!text) return '';
    return String(text)
      .split('\n')
      .map(function (line) {
        var safe = String(line || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/^ +/g, function (m) { return '\u00A0'.repeat(m.length); })
          .replace(/ +$/g, function (m) { return '\u00A0'.repeat(m.length); })
          .replace(/ {2,}/g, function (m) { return ' ' + '\u00A0'.repeat(m.length - 1); });
        return '<p>' + (safe || '<br>') + '</p>';
      })
      .join('');
  }

  function escapeHtml(text) {
    if (!text) return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function updateTodoAriaLabels(container) {
    if (!container) return;
    var items = container.querySelectorAll('.crm-ve-todo-list li, li.crm-ve-todo-item, .crm-ve-todo li');
    for (var i = 0; i < items.length; i++) {
      var li = items[i];
      var cb = li.querySelector('.crm-ve-todo-cb');
      if (!cb) continue;
      var textEl = li.querySelector('.crm-ve-todo-text') || li;
      var clone = textEl.cloneNode(true);
      var innerCb = clone.querySelector('.crm-ve-todo-cb');
      if (innerCb) innerCb.remove();
      var txt = (clone.textContent || '').replace(/[\u200B\uFEFF]/g, '').trim();
      var label = t('visual_editor.todo_item', 'Пункт чек-листа') + (txt ? ': ' + txt : '');
      cb.setAttribute('aria-label', label);
      cb.setAttribute('role', 'checkbox');
      if (!cb.hasAttribute('tabindex')) cb.setAttribute('tabindex', '0');
      var isChecked = li.classList.contains('is-checked') || cb.getAttribute('data-checked') === 'true';
      cb.setAttribute('aria-checked', isChecked ? 'true' : 'false');
    }
  }

  function getVisibleViewportBounds(wrapper) {
    var viewBottom = window.innerHeight;
    var viewTop = 0;

    if (wrapper) {
      var modalRoot = wrapper.closest('.modal, [role="dialog"], .modal-dialog, .modal-content') || wrapper.closest('.modal-body');
      if (modalRoot) {
        var topContainer = wrapper.closest('.modal, [role="dialog"]') || modalRoot.closest('.modal, [role="dialog"]') || modalRoot;
        var modalBody = topContainer.querySelector('.modal-body') || topContainer;
        var modalFooter = topContainer.querySelector('.modal-footer, .modal-foot, .crm-modal-footer');
        var bodyRect = modalBody.getBoundingClientRect();
        if (bodyRect.bottom > 0) {
          viewBottom = Math.min(viewBottom, bodyRect.bottom);
        }
        if (modalFooter) {
          var footRect = modalFooter.getBoundingClientRect();
          if (footRect.top > 0) {
            viewBottom = Math.min(viewBottom, footRect.top);
          }
        }
        if (bodyRect.top > 0) {
          viewTop = Math.max(viewTop, bodyRect.top);
        }
      }

      var p = wrapper.parentElement;
      while (p && p !== document.body && p !== document.documentElement) {
        var style = window.getComputedStyle(p);
        if (/auto|scroll|hidden/i.test(style.overflowY)) {
          var pRect = p.getBoundingClientRect();
          if (pRect.bottom > 0) viewBottom = Math.min(viewBottom, pRect.bottom);
          if (pRect.top > 0) viewTop = Math.max(viewTop, pRect.top);
        }
        p = p.parentElement;
      }
    }

    return { top: viewTop, bottom: viewBottom };
  }

  function scrollIntoMenu(container, item) {
    if (!container || !item) return;
    var itemTop = item.offsetTop;
    var itemBottom = itemTop + item.offsetHeight;
    var containerTop = container.scrollTop;
    var containerBottom = containerTop + container.clientHeight;

    if (itemTop < containerTop) {
      container.scrollTop = itemTop;
    } else if (itemBottom > containerBottom) {
      container.scrollTop = itemBottom - container.clientHeight;
    }
  }

  function updateStickyToolbarOffset(editorInstance) {
    if (!editorInstance || !editorInstance._toolbar || !editorInstance._textarea) return;
    var toolbar = editorInstance._toolbar;
    var inModal = !!editorInstance._textarea.closest('.modal, .modal-dialog, .modal-content, [role="dialog"], .crm-modal');
    if (inModal) {
      toolbar.style.top = '0px';
      return;
    }
    var topbar = document.querySelector('.crm-topbar, .topbar, .navbar, header');
    var h = 56;
    if (topbar) {
      var rect = topbar.getBoundingClientRect();
      h = Math.max(topbar.offsetHeight || 0, Math.round(rect.height || 0), 56);
    }
    toolbar.style.top = h + 'px';
    document.documentElement.style.setProperty('--crm-topbar-height', h + 'px');
  }

  function getCsrfToken() {
    if (window.CRM && window.CRM.api && typeof window.CRM.api.getCsrfToken === 'function') {
      return window.CRM.api.getCsrfToken();
    }
    var match = document.cookie.match(/crm_csrf_token=([^;]+)/);
    if (match) return decodeURIComponent(match[1]);
    match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    if (match) return decodeURIComponent(match[1]);
    return '';
  }

  function t(key, fallback) {
    if (window.CRM && window.CRM.i18n && typeof window.CRM.i18n.t === 'function') {
      return window.CRM.i18n.t(key, fallback);
    }
    return fallback || key;
  }

  function notify(text, type) {
    try {
      if (window.CRM && window.CRM.ui && typeof window.CRM.ui.showNotice === 'function' && window.CRM.ui.showNotice(text, type)) {
        return true;
      }
      if (window.CRM && window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.notify === 'function') {
        window.CRM.pageApiBindings.notify(text, type);
        return true;
      }
      if (typeof window.notify === 'function') {
        window.notify(text, type);
        return true;
      }
    } catch (e) { /* ignore */ }
    return false;
  }

  function showErrorToast(editorInstance, message) {
    if (!editorInstance) return;
    var toast = document.createElement('div');
    toast.className = 'crm-ve-toast is-visible is-error';
    toast.setAttribute('role', 'alert');
    toast.textContent = message;
    toast.style.cssText = (
      'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);'
      + 'background:#dc2626;color:#fff;padding:12px 24px;border-radius:8px;'
      + 'font-size:14px;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,0.3);'
      + 'max-width:90vw;text-align:center;transition:opacity 0.3s;'
    );
    document.body.appendChild(toast);
    setTimeout(function () {
      toast.style.opacity = '0';
      setTimeout(function () { toast.remove(); }, 300);
    }, 5000);
  }

  function isImageFile(file) {
    if (!file || !file.type) return false;
    return ['image/jpeg', 'image/png', 'image/webp', 'image/gif'].indexOf(file.type) !== -1;
  }

  function readFileAsDataURL(file) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onload = function () { resolve(reader.result); };
      reader.onerror = function () { reject(new Error('File read failed')); };
      reader.readAsDataURL(file);
    });
  }

  function getImageDimensions(file) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () {
        URL.revokeObjectURL(url);
        resolve({ width: img.width, height: img.height });
      };
      img.onerror = function () {
        URL.revokeObjectURL(url);
        reject(new Error(t('visual_editor.upload_error', 'Could not upload image')));
      };
      img.src = url;
    });
  }

  function dataURLtoBlob(dataURL) {
    var parts = dataURL.split(',');
    var mime = parts[0].match(/:(.*?);/);
    var mimeType = mime ? mime[1] : 'image/png';
    var binary = atob(parts[1]);
    var arr = new Uint8Array(binary.length);
    for (var i = 0; i < binary.length; i++) {
      arr[i] = binary.charCodeAt(i);
    }
    return new Blob([arr], { type: mimeType });
  }

  // ---------------------------------------------------------------------------
  //  Sanitizer
  // ---------------------------------------------------------------------------

  var ALLOWED_TAGS = {
    P: true, BR: true, STRONG: true, B: true, EM: true, I: true, S: true, U: true,
    CODE: true, PRE: true, BLOCKQUOTE: true, UL: true, OL: true, LI: true,
    A: true, H1: true, H2: true, H3: true, FIGURE: true, FIGCAPTION: true, IMG: true,
    DETAILS: true, SUMMARY: true, INPUT: true, TABLE: true, TBODY: true,
    TR: true, TD: true, TH: true, THEAD: true, CAPTION: true, COLGROUP: true, COL: true,
    SPAN: true, HR: true
  };

  // Tags that must be removed entirely (including their content), never
  // unwrapped: their children are untrusted code, not readable text.
  var DANGEROUS_TAGS = {
    SCRIPT: true, STYLE: true, IFRAME: true, OBJECT: true, EMBED: true,
    FORM: true, SVG: true, MATH: true, TEMPLATE: true, NOSCRIPT: true,
    APPLET: true, FRAME: true, FRAMESET: true, BASE: true, META: true, LINK: true
  };

  var BLOCK_TAGS = {
    P: true, H1: true, H2: true, H3: true, BLOCKQUOTE: true, PRE: true,
    UL: true, OL: true, LI: true, FIGURE: true, DETAILS: true, TABLE: true,
    TBODY: true, TR: true, TD: true, TH: true
  };

  function sanitizeHtml(rawHtml) {
    if (!rawHtml) return '';
    var template = document.createElement('template');
    template.innerHTML = String(rawHtml);

    function sanitizeAttributes(el, tag) {
      var attrs = Array.prototype.slice.call(el.attributes || []);
      attrs.forEach(function (attr) {
        var name = String(attr.name || '').toLowerCase();
        if (name.indexOf('on') === 0) {
          el.removeAttribute(attr.name);
          return;
        }

        // Never allow foreign contenteditable attributes inside the editor.
        if (name === 'contenteditable') {
          var isMention = tag === 'SPAN' && (el.getAttribute('class') || '').indexOf('crm-ve-mention') !== -1;
          var isTodoCb = tag === 'SPAN' && (el.getAttribute('class') || '').indexOf('crm-ve-todo-cb') !== -1;
          var isTodoText = tag === 'SPAN' && (el.getAttribute('class') || '').indexOf('crm-ve-todo-text') !== -1;
          var isFigcaption = tag === 'FIGCAPTION';
          if (!isMention && !isTodoCb && !isTodoText && !isFigcaption) {
            el.removeAttribute(attr.name);
            return;
          }
        }

        if (tag === 'A') {
          if (name !== 'href' && name !== 'title' && name !== 'target' && name !== 'rel') {
            el.removeAttribute(attr.name);
          }
        } else if (tag === 'IMG') {
          if (name !== 'src' && name !== 'alt' && name !== 'title') {
            el.removeAttribute(attr.name);
          }
        } else if (tag === 'FIGURE') {
          var figCls = el.getAttribute('class') || '';
          if (figCls.indexOf('crm-ve-uploading-ph') !== -1) {
            if (name !== 'data-align' && name !== 'data-width' && name !== 'style' && name !== 'class' && name !== 'data-block-id') {
              el.removeAttribute(attr.name);
            }
          } else {
            if (name !== 'data-align' && name !== 'data-width' && name !== 'style') {
              el.removeAttribute(attr.name);
            }
          }
        } else if (tag === 'FIGCAPTION') {
          if (name !== 'contenteditable') {
            el.removeAttribute(attr.name);
          }
        } else if (tag === 'DETAILS' || tag === 'SUMMARY') {
          if (name !== 'class') {
            el.removeAttribute(attr.name);
          }
        } else if (tag === 'INPUT') {
          if (name !== 'type' && name !== 'disabled' && name !== 'data-checked') {
            el.removeAttribute(attr.name);
          }
        } else if (tag === 'TABLE' || tag === 'THEAD' || tag === 'TBODY' || tag === 'TR' || tag === 'TD' || tag === 'TH' || tag === 'COLGROUP' || tag === 'COL' || tag === 'CAPTION') {
          // Table elements: allow class and colspan/rowspan
          if (name !== 'class' && name !== 'colspan' && name !== 'rowspan') {
            el.removeAttribute(attr.name);
          }
        } else if (tag === 'OL') {
          // Preserve list start index (e.g. continuing numbered lists from Jira/Word) and type
          if (name !== 'start' && name !== 'type' && name !== 'class') {
            el.removeAttribute(attr.name);
          } else if (name === 'start') {
            var startVal = parseInt(el.getAttribute('start'), 10);
            if (isNaN(startVal) || startVal < 1) {
              el.removeAttribute('start');
            } else {
              el.setAttribute('start', String(startVal));
            }
          }
        } else if (tag === 'UL') {
          if (name !== 'class') {
            el.removeAttribute(attr.name);
          }
        } else if (tag === 'LI') {
          if (name !== 'class' && name !== 'data-checked') {
            el.removeAttribute(attr.name);
          }
        } else if (tag === 'CODE' || tag === 'PRE') {
          if (name !== 'class') {
            el.removeAttribute(attr.name);
          }
        } else if (tag === 'SPAN') {
          // Allow mention spans, todo checkboxes/text, and general formatting
          var cls = el.getAttribute('class') || '';
          if (cls.indexOf('crm-ve-mention') !== -1) {
            // Mention: allow data-mention-type, data-mention-id, contenteditable, class
            if (name !== 'class' && name !== 'data-mention-type' && name !== 'data-mention-id' && name !== 'contenteditable') {
              el.removeAttribute(attr.name);
            }
          } else if (cls.indexOf('crm-ve-todo-cb') !== -1) {
            // Todo checkbox: allow class, data-checked, role, tabindex, aria-checked, aria-label, contenteditable
            if (name !== 'class' && name !== 'data-checked' && name !== 'role' && name !== 'tabindex' && name !== 'aria-checked' && name !== 'aria-label' && name !== 'contenteditable') {
              el.removeAttribute(attr.name);
            }
          } else if (cls.indexOf('crm-ve-todo-text') !== -1) {
            if (name !== 'class' && name !== 'contenteditable') {
              el.removeAttribute(attr.name);
            }
          } else {
            el.removeAttribute(attr.name);
          }
        } else if (tag === 'HR') {
          // HR: allow style for custom styling
          if (name !== 'style' && name !== 'class') {
            el.removeAttribute(attr.name);
          }
        } else {
          el.removeAttribute(attr.name);
        }
      });

      if (tag === 'FIGURE' && el.hasAttribute('style')) {
        var styleVal = el.getAttribute('style') || '';
        var safeStyles = [];
        styleVal.split(';').forEach(function (s) {
          var parts = s.split(':');
          var prop = String(parts.shift() || '').trim().toLowerCase();
          var val = String(parts.join(':') || '').trim();
          var match = val.match(/^(\d+(?:\.\d+)?)%$/);
          if ((prop === 'width' || prop === '--crm-ve-image-width') && match) {
            var pct = clamp(parseFloat(match[1]), 10, 100);
            safeStyles.push(prop + ':' + pct + '%');
          }
        });
        if (safeStyles.length) {
          el.setAttribute('style', safeStyles.join(';'));
        } else {
          el.removeAttribute('style');
        }
      }

      if (tag === 'A') {
        var safeHref = sanitizeLinkHref(el.getAttribute('href'));
        if (!safeHref) {
          el.removeAttribute('href');
        } else {
          el.setAttribute('href', safeHref);
          var isExternal = /^https?:\/\//i.test(safeHref);
          if (isExternal) {
            el.setAttribute('target', '_blank');
            el.setAttribute('rel', 'noopener noreferrer');
          } else {
            el.removeAttribute('target');
            el.removeAttribute('rel');
          }
        }
      }

      if (tag === 'IMG') {
        var src = el.getAttribute('src') || '';
        if (!src || src.indexOf('data:') === 0 || /^blob:/i.test(src)) {
          if (el.parentNode) el.parentNode.removeChild(el);
          return;
        }
      }
    }

    function cleanNode(node) {
      if (node.nodeType === Node.ELEMENT_NODE) {
        var tag = node.tagName.toUpperCase();
        if (DANGEROUS_TAGS[tag]) {
          if (node.parentNode) node.parentNode.removeChild(node);
          return;
        }

        // Convert inline styles on spans/elements to semantic formatting tags before stripping styles
        if (node.hasAttribute('style')) {
          var styleStr = node.getAttribute('style') || '';
          var styleObj = node.style || null;
          var fw = (styleObj && styleObj.fontWeight) ? styleObj.fontWeight : '';
          if (!fw) {
            var mFw = styleStr.match(/font-weight\s*:\s*([^;]+)/i);
            if (mFw) fw = mFw[1].trim();
          }
          var isBold = fw === 'bold' || fw === 'bolder' || parseInt(fw, 10) >= 600;

          var fs = (styleObj && styleObj.fontStyle) ? styleObj.fontStyle : '';
          if (!fs) {
            var mFs = styleStr.match(/font-style\s*:\s*([^;]+)/i);
            if (mFs) fs = mFs[1].trim();
          }
          var isItalic = fs === 'italic' || fs === 'oblique';

          var td = (styleObj && (styleObj.textDecoration || styleObj.textDecorationLine)) ? (styleObj.textDecoration || styleObj.textDecorationLine) : '';
          if (!td) {
            var mTd = styleStr.match(/text-decoration(?:-line)?\s*:\s*([^;]+)/i);
            if (mTd) td = mTd[1].trim();
          }
          var isStrike = td.indexOf('line-through') !== -1;

          if (isBold && tag !== 'B' && tag !== 'STRONG' && tag !== 'H1' && tag !== 'H2' && tag !== 'H3') {
            var b = document.createElement('b');
            while (node.firstChild) {
              b.appendChild(node.firstChild);
            }
            node.appendChild(b);
            if (styleObj) styleObj.fontWeight = '';
          }
          if (isItalic && tag !== 'I' && tag !== 'EM') {
            var it = document.createElement('i');
            while (node.firstChild) {
              it.appendChild(node.firstChild);
            }
            node.appendChild(it);
            if (styleObj) styleObj.fontStyle = '';
          }
          if (isStrike && tag !== 'S' && tag !== 'DEL' && tag !== 'STRIKE') {
            var s = document.createElement('s');
            while (node.firstChild) {
              s.appendChild(node.firstChild);
            }
            node.appendChild(s);
            if (styleObj) styleObj.textDecoration = '';
          }
        }

        if (!ALLOWED_TAGS[tag]) {
          var parent = node.parentNode;
          if (!parent) return;
          var moved = [];
          while (node.firstChild) {
            var lifted = node.firstChild;
            parent.insertBefore(lifted, node);
            moved.push(lifted);
          }
          parent.removeChild(node);
          // Recursively clean all lifted nodes so nested non-allowed tags (e.g. nested <div>)
          // are also unwrapped and their attributes sanitized.
          moved.forEach(function (n) {
            cleanNode(n);
          });
          return;
        }

        // Unwrap spans that contain only whitespace (paste artifacts)
        if (tag === 'SPAN') {
          var spanCls = node.getAttribute('class') || '';
          if (spanCls.indexOf('crm-ve-mention') === -1 && spanCls.indexOf('crm-ve-todo-cb') === -1 && (node.textContent || '').trim() === '') {
            var spanParent = node.parentNode;
            if (spanParent) {
              var spanMoved = [];
              while (node.firstChild) {
                var sLifted = node.firstChild;
                spanParent.insertBefore(sLifted, node);
                spanMoved.push(sLifted);
              }
              spanParent.removeChild(node);
              spanMoved.forEach(function (sm) {
                cleanNode(sm);
              });
              return;
            }
          }
        }

        sanitizeAttributes(node, tag);

        // Recursively clean all children of allowed node
        var children = Array.prototype.slice.call(node.childNodes || []);
        children.forEach(function (child) {
          cleanNode(child);
        });
        return;
      }

      if (node.nodeType === Node.COMMENT_NODE) {
        if (node.parentNode) node.parentNode.removeChild(node);
        return;
      }

      if (node.nodeType === Node.TEXT_NODE) {
        if (node.textContent === '\u200B' || node.textContent === '\uFEFF') {
          if (node.parentNode) node.parentNode.removeChild(node);
        }
      }
    }

    var rootChildren = Array.prototype.slice.call(template.content.childNodes || []);
    rootChildren.forEach(function (child) {
      cleanNode(child);
    });

    // Leftover dangerous blocks pasted from Word/other apps (e.g. a <script>
    // moved up by an unwrap) must not survive as text either.
    var result = template.innerHTML.trim();
    result = result.replace(/<\/?(?:script|style|iframe|object|embed|form|svg|math|template|noscript|applet|frame|frameset|base|meta|link)\b[^>]*>/gi, '');
    result = result.replace(/\u200B/g, '');
    result = result.replace(/\uFEFF/g, '');
    result = result.replace(/<br\s*\/?>\s*<br\s*\/?>/gi, '<br>');
    result = result.replace(/(<p>\s*<\/p>)+/gi, '<p><br></p>');

    return result;
  }

  // ---------------------------------------------------------------------------
  //  History (Undo / Redo)
  // ---------------------------------------------------------------------------

  function createHistory() {
    var snapshots = [];
    var pointer = -1;
    var maxSize = 100;
    var timer = null;
    var pending = null;

    function flush() {
      if (timer !== null) {
        clearTimeout(timer);
        timer = null;
      }
      if (pending !== null) {
        commit(pending);
        pending = null;
      }
    }

    function push(html, immediate) {
      var snapshot = html || '';
      // A restored snapshot has no live request or retry handler.
      if (/crm-ve-upload(?:ing|-error)/.test(snapshot)) {
        var template = document.createElement('template');
        template.innerHTML = snapshot;
        template.content.querySelectorAll('.crm-ve-uploading, .crm-ve-uploading-ph, .crm-ve-upload-error').forEach(function (block) {
          block.remove();
        });
        snapshot = template.innerHTML;
      }
      // Disabled state follows the selection and is recomputed on restore.
      snapshot = snapshot.replace(/(<button\b[^>]* data-table-action="del-(?:row|col)"[^>]*?) disabled=""/g, '$1');
      if (immediate) {
        flush();
        commit(snapshot);
      } else {
        if (timer !== null) clearTimeout(timer);
        pending = snapshot;
        timer = setTimeout(flush, 800);
      }
    }

    function commit(html) {
      if (html === snapshots[pointer]) return;
      if (pointer < snapshots.length - 1) {
        snapshots = snapshots.slice(0, pointer + 1);
      }
      snapshots.push(html);
      if (snapshots.length > maxSize) {
        snapshots.shift();
      }
      pointer = snapshots.length - 1;
    }

    function undo() {
      flush();
      if (pointer <= 0) return null;
      pointer--;
      return snapshots[pointer];
    }

    function redo() {
      flush();
      if (pointer >= snapshots.length - 1) return null;
      pointer++;
      return snapshots[pointer];
    }

    function reset() {
      if (timer !== null) {
        clearTimeout(timer);
        timer = null;
      }
      pending = null;
      snapshots = [];
      pointer = -1;
    }

    return { push: push, undo: undo, redo: redo, reset: reset };
  }

  // ---------------------------------------------------------------------------
  //  Image Block creation
  // ---------------------------------------------------------------------------

  function createImageBlock(src, alt, widthPercent, blockId, readonly) {
    var bid = blockId || generateId();
    var figWidth = widthPercent ? '--crm-ve-image-width:' + widthPercent + '%' : '';
    var altText = alt || '';

    var div = document.createElement('div');
    div.className = 'crm-ve-block crm-ve-image-block';
    div.setAttribute('contenteditable', 'false');
    div.setAttribute('data-type', 'image');
    div.setAttribute('data-block-id', bid);
    div.setAttribute('data-align', 'center');
    if (widthPercent) {
      div.setAttribute('data-width', String(widthPercent));
    }

    var figure = document.createElement('figure');
    figure.className = 'crm-ve-image-figure';
    if (figWidth) {
      figure.style.setProperty('--crm-ve-image-width', widthPercent + '%');
    }

    var img = document.createElement('img');
    img.className = 'crm-ve-image';
    img.src = src || '';
    img.alt = altText;
    img.setAttribute('draggable', 'false');

    var figcaption = document.createElement('figcaption');
    figcaption.className = 'crm-ve-image-caption';
    if (!readonly) {
      figcaption.setAttribute('contenteditable', 'true');
    }
    figcaption.textContent = altText;

    var frame = document.createElement('div');
    frame.className = 'crm-ve-image-frame';

    var hl = document.createElement('button');
    hl.type = 'button';
    hl.className = 'crm-ve-resize-handle crm-ve-resize-handle-left';
    hl.setAttribute('data-handle', 'left');

    var hr = document.createElement('button');
    hr.type = 'button';
    hr.className = 'crm-ve-resize-handle crm-ve-resize-handle-right';
    hr.setAttribute('data-handle', 'right');

    var hbl = document.createElement('button');
    hbl.type = 'button';
    hbl.className = 'crm-ve-resize-handle crm-ve-resize-handle-bottom-left';
    hbl.setAttribute('data-handle', 'bottom-left');

    var hbr = document.createElement('button');
    hbr.type = 'button';
    hbr.className = 'crm-ve-resize-handle crm-ve-resize-handle-bottom-right';
    hbr.setAttribute('data-handle', 'bottom-right');

    frame.appendChild(hl);
    frame.appendChild(hr);
    frame.appendChild(hbl);
    frame.appendChild(hbr);

    figure.appendChild(img);
    figure.appendChild(figcaption);
    figure.appendChild(frame);
    div.appendChild(figure);

    return div;
  }

  function createUploadingPlaceholder(blockId, align, widthPercent, customProps) {
    var div = document.createElement('div');
    div.className = 'crm-ve-block crm-ve-image-block crm-ve-uploading';
    div.setAttribute('contenteditable', 'false');
    div.setAttribute('data-type', 'image');
    div.setAttribute('data-block-id', blockId);
    div.setAttribute('data-align', align || 'center');
    if (widthPercent) {
      div.setAttribute('data-width', String(widthPercent));
    }
    if (customProps) {
      if (customProps.alt !== undefined) div._preservedAlt = customProps.alt;
      if (customProps.caption !== undefined) div._preservedCaption = customProps.caption;
      if (customProps.link !== undefined) div._preservedLink = customProps.link;
    }

    var progress = document.createElement('div');
    progress.className = 'crm-ve-upload-progress';

    var bar = document.createElement('div');
    bar.className = 'crm-ve-upload-bar';

    var label = document.createElement('span');
    label.className = 'crm-ve-upload-label';
    label.textContent = t('visual_editor.uploading', 'Uploading...');

    var pct = document.createElement('span');
    pct.className = 'crm-ve-upload-percent';
    pct.textContent = '0%';

    progress.appendChild(bar);
    div.appendChild(label);
    div.appendChild(pct);
    div.appendChild(progress);

    return div;
  }

  // ---------------------------------------------------------------------------
  //  Image Toolbar
  // ---------------------------------------------------------------------------

  function createImageToolbar(editorInstance) {
    var toolbar = document.createElement('div');
    toolbar.className = 'crm-ve-image-toolbar';
    toolbar.style.cssText = (
      'display:none;position:absolute;z-index:999;background:#1e293b;'
      + 'border-radius:8px;padding:4px 6px;gap:2px;flex-wrap:wrap;'
      + 'box-shadow:0 4px 16px rgba(0,0,0,0.4);align-items:center;'
      + 'max-width:calc(100vw - 32px);'
    );

    function btn(label, title, action, attrs) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'crm-ve-image-toolbar-btn';
      b.innerHTML = label;
      b.title = title || '';
      if (attrs) {
        Object.keys(attrs).forEach(function (name) {
          b.setAttribute(name, attrs[name]);
        });
      }
      b.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        action();
      });
      return b;
    }

    function sep() {
      var s = document.createElement('span');
      s.className = 'crm-ve-image-toolbar-sep';
      s.style.cssText = 'display:inline-block;width:1px;height:20px;background:#475569;margin:0 4px;';
      return s;
    }

    toolbar.appendChild(btn(
      '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M2 3v10h12V3H2zm1 1h10v5.586l-2.293-2.293a1 1 0 0 0-1.414 0L7 9.586 5.707 8.293a1 1 0 0 0-1.414 0L3 9.586V4zm0 7.414L4.293 10.12l1.293 1.293a1 1 0 0 0 1.414 0L9 10.12l3 3H3v-1.707zM12 6.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/></svg>',
      t('visual_editor.align_left', 'Align left'),
      function () { setImageAlign(editorInstance, 'left'); },
      { 'data-align': 'left' }
    ));
    toolbar.appendChild(btn(
      '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M1 3v10h14V3H1zm1 1h12v5.586l-2.293-2.293a1 1 0 0 0-1.414 0L8 9.586l-1.293-1.293a1 1 0 0 0-1.414 0L3 9.586V4zm0 7.414L4.293 10.12l1.293 1.293a1 1 0 0 0 1.414 0L8 10.12l1.293 1.293a1 1 0 0 0 1.414 0L12 10.12l2 2H3v-1.707zM13 6.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/></svg>',
      t('visual_editor.align_center', 'Center'),
      function () { setImageAlign(editorInstance, 'center'); },
      { 'data-align': 'center' }
    ));
    toolbar.appendChild(btn(
      '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M2 3v10h12V3H2zm1 1h10v5.586l-2.293-2.293a1 1 0 0 0-1.414 0L9 9.586 7.707 8.293a1 1 0 0 0-1.414 0L5 9.586 3.707 8.293a1 1 0 0 0-1.414 0L3 9.586V4zm0 7.414L4.293 10.12l1.293 1.293a1 1 0 0 0 1.414 0L7.707 10.12 9 11.414a1 1 0 0 0 1.414 0L11.707 10.12 14 12.414V14H3v-2.586zM11 6.5a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0z"/></svg>',
      t('visual_editor.align_right', 'Align right'),
      function () { setImageAlign(editorInstance, 'right'); },
      { 'data-align': 'right' }
    ));
    toolbar.appendChild(sep());

    toolbar.appendChild(btn('25%', t('visual_editor.width_25', '25% width'), function () { setImageWidth(editorInstance, 25); }, { 'data-width': '25' }));
    toolbar.appendChild(btn('50%', t('visual_editor.width_50', '50% width'), function () { setImageWidth(editorInstance, 50); }, { 'data-width': '50' }));
    toolbar.appendChild(btn('75%', t('visual_editor.width_75', '75% width'), function () { setImageWidth(editorInstance, 75); }, { 'data-width': '75' }));
    toolbar.appendChild(btn('100%', t('visual_editor.width_100', '100% width'), function () { setImageWidth(editorInstance, 100); }, { 'data-width': '100' }));
    toolbar.appendChild(sep());

    toolbar.appendChild(btn(
      '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/><path d="M6.5 3a.5.5 0 0 1 .5.5V6h2.5a.5.5 0 0 1 0 1H7v2.5a.5.5 0 0 1-1 0V7H3.5a.5.5 0 0 1 0-1H6V3.5a.5.5 0 0 1 .5-.5z"/></svg>',
      t('visual_editor.zoom_image', 'Увеличить / Просмотр'),
      function () {
        var block = getSelectedImageBlock(editorInstance);
        if (!block) return;
        var img = block.querySelector('.crm-ve-image');
        if (!img || !img.src) return;
        var capEl = block.querySelector('.crm-ve-image-caption');
        var caption = (capEl && capEl.textContent) ? capEl.textContent.trim() : (img.alt || '');
        var showViewer = (window.CRM && (window.CRM.showImageViewer || window.CRM.showImageLightbox));
        if (typeof showViewer === 'function') {
          showViewer(img.src, caption);
        } else {
          window.open(img.src, '_blank');
        }
      },
      { 'data-action': 'zoom' }
    ));

    toolbar.appendChild(btn(
      'Alt',
      t('visual_editor.edit_alt', 'Edit alt text'),
      function () { editImageAlt(editorInstance); },
      { 'data-action': 'alt' }
    ));
    toolbar.appendChild(btn(
      t('visual_editor.caption', 'Caption'),
      t('visual_editor.toggle_caption', 'Toggle caption'),
      function () { toggleImageCaption(editorInstance); },
      { 'data-action': 'caption' }
    ));
    toolbar.appendChild(btn(
      t('visual_editor.link', 'Link'),
      t('visual_editor.edit_link', 'Edit link'),
      function () { editImageLink(editorInstance); },
      { 'data-action': 'link' }
    ));
    toolbar.appendChild(btn(
      t('visual_editor.replace', 'Replace'),
      t('visual_editor.replace_image', 'Replace image'),
      function () { replaceImage(editorInstance); },
      { 'data-action': 'replace' }
    ));
    toolbar.appendChild(btn(
      t('visual_editor.delete', 'Delete'),
      t('visual_editor.delete_image', 'Delete image'),
      function () { deleteSelectedImage(editorInstance); },
      { 'data-action': 'delete' }
    ));

    return toolbar;
  }

  // ---------------------------------------------------------------------------
  //  Image operations
  // ---------------------------------------------------------------------------

  function getSelectedImageBlock(editorInstance) {
    if (!editorInstance) return null;
    var sel = editorInstance._selectedImageBlock;
    if (sel && document.body.contains(sel)) return sel;
    editorInstance._selectedImageBlock = null;
    return null;
  }

  function selectImageBlock(editorInstance, block) {
    if (editorInstance._selectedImageBlock === block) return;
    deselectImage(editorInstance);
    if (!block) return;
    editorInstance._selectedImageBlock = block;
    var figure = block.querySelector('.crm-ve-image-figure');
    if (figure) figure.classList.add('is-selected');
    showImageToolbar(editorInstance, block);
  }

  function deselectImage(editorInstance) {
    var prev = editorInstance._selectedImageBlock;
    if (prev) {
      var fig = prev.querySelector('.crm-ve-image-figure');
      if (fig) fig.classList.remove('is-selected');
    }
    editorInstance._selectedImageBlock = null;
    hideImageToolbar(editorInstance);
  }

  function setImageAlign(editorInstance, align) {
    var block = getSelectedImageBlock(editorInstance);
    if (!block) return;
    block.setAttribute('data-align', align);
    editorInstance._history.push(editorInstance._content.innerHTML, true);
    editorInstance._sync();
  }

  function setImageWidth(editorInstance, percent) {
    var block = getSelectedImageBlock(editorInstance);
    if (!block) return;
    var figure = block.querySelector('.crm-ve-image-figure');
    if (!figure) return;
    var clamped = clamp(percent, editorInstance._options.minImageWidth / editorInstance._content.clientWidth * 100, 100);
    figure.style.setProperty('--crm-ve-image-width', clamped + '%');
    figure.parentNode.setAttribute('data-width', String(Math.round(clamped)));
    editorInstance._history.push(editorInstance._content.innerHTML, true);
    editorInstance._sync();
    updateImageToolbarPosition(editorInstance);
  }

  function editImageAlt(editorInstance) {
    var block = getSelectedImageBlock(editorInstance);
    if (!block) return;
    var img = block.querySelector('.crm-ve-image');
    if (!img) return;
    var current = img.getAttribute('alt') || '';
    var newAlt = window.prompt(t('visual_editor.alt_prompt', 'Enter alt text for the image:'), current);
    if (newAlt === null) return;
    img.setAttribute('alt', newAlt);
    var caption = block.querySelector('.crm-ve-image-caption');
    if (caption && caption.textContent === current) {
      caption.textContent = newAlt;
    }
    editorInstance._history.push(editorInstance._content.innerHTML, true);
    editorInstance._sync();
  }

  function toggleImageCaption(editorInstance) {
    var block = getSelectedImageBlock(editorInstance);
    if (!block) return;
    var figcaption = block.querySelector('.crm-ve-image-caption');
    if (figcaption) {
      if (figcaption.style.display === 'none') {
        figcaption.style.display = '';
      } else {
        figcaption.style.display = 'none';
      }
    }
    editorInstance._sync();
  }

  function editImageLink(editorInstance) {
    var block = getSelectedImageBlock(editorInstance);
    if (!block) return;
    var img = block.querySelector('.crm-ve-image');
    if (!img) return;
    var figure = block.querySelector('.crm-ve-image-figure');
    var existingLink = figure ? figure.querySelector('a') : null;
    var currentHref = existingLink ? existingLink.getAttribute('href') || '' : '';
    var newHref = window.prompt(t('visual_editor.link_prompt', 'Enter image link URL:'), currentHref);
    if (newHref === null) return;
    var safeHref = sanitizeLinkHref(newHref);
    if (existingLink) {
      if (safeHref) {
        existingLink.setAttribute('href', safeHref);
      } else {
        var parent = existingLink.parentNode;
        while (existingLink.firstChild) {
          parent.insertBefore(existingLink.firstChild, existingLink);
        }
        parent.removeChild(existingLink);
      }
    } else if (safeHref && figure) {
      var anchor = document.createElement('a');
      anchor.setAttribute('href', safeHref);
      if (/^https?:\/\//i.test(safeHref)) {
        anchor.setAttribute('target', '_blank');
        anchor.setAttribute('rel', 'noopener noreferrer');
      }
      var imgEl = figure.querySelector('.crm-ve-image');
      if (imgEl) {
        imgEl.parentNode.insertBefore(anchor, imgEl);
        anchor.appendChild(imgEl);
      }
    }
    editorInstance._history.push(editorInstance._content.innerHTML, true);
    editorInstance._sync();
  }

  function replaceImage(editorInstance) {
    if (!editorInstance._fileInput) return;
    editorInstance._fileInput.click();
    editorInstance._replaceTarget = getSelectedImageBlock(editorInstance);
  }

  function deleteSelectedImage(editorInstance) {
    var block = getSelectedImageBlock(editorInstance);
    if (!block) return;
    deselectImage(editorInstance);
    block.parentNode.removeChild(block);
    editorInstance._history.push(editorInstance._content.innerHTML, true);
    editorInstance._sync();
    editorInstance._content.focus();
  }

  function showImageToolbar(editorInstance, block) {
    var tbar = editorInstance._imageToolbar;
    if (!tbar) return;
    tbar.style.display = 'flex';
    tbar.classList.add('is-visible');
    editorInstance._imageToolbarTarget = block;
    updateImageToolbarPosition(editorInstance);
  }

  function hideImageToolbar(editorInstance) {
    var tbar = editorInstance._imageToolbar;
    if (!tbar) return;
    tbar.style.display = 'none';
    tbar.classList.remove('is-visible');
    editorInstance._imageToolbarTarget = null;
  }

  function updateImageToolbarPosition(editorInstance) {
    var tbar = editorInstance._imageToolbar;
    var block = editorInstance._imageToolbarTarget;
    if (!tbar || !block) return;
    if (tbar.style.display === 'none') return;

    var wrapper = editorInstance._wrapper;
    var wrapperRect = wrapper.getBoundingClientRect();
    var figure = block.querySelector('.crm-ve-image-figure') || block;
    var figureRect = figure.getBoundingClientRect();

    var top = figureRect.bottom - wrapperRect.top - tbar.offsetHeight - 14;
    if (top < 4) {
      top = figureRect.top - wrapperRect.top + 8;
    }

    var left = figureRect.left - wrapperRect.left + (figureRect.width / 2) - (tbar.offsetWidth / 2);
    left = clamp(left, 4, wrapperRect.width - tbar.offsetWidth - 4);

    tbar.style.top = top + 'px';
    tbar.style.left = left + 'px';
  }

  // ---------------------------------------------------------------------------
  //  Image resize (Pointer Events)
  // ---------------------------------------------------------------------------

  function initImageResize(editorInstance) {
    var state = null;

    function onPointerDown(e) {
      var handle = e.target.closest('.crm-ve-resize-handle');
      if (!handle) return;
      var block = handle.closest('.crm-ve-image-block');
      if (!block) return;
      e.preventDefault();
      var figure = block.querySelector('.crm-ve-image-figure');
      if (!figure) return;

      var container = block.parentNode;
      var containerRect = container.getBoundingClientRect();
      var figureRect = figure.getBoundingClientRect();
      var currentWidthPx = figureRect.width;
      var containerWidthPx = containerRect.width;

      var align = block.getAttribute('data-align') || 'center';
      var handleType = handle.getAttribute('data-handle') || 'right';

      state = {
        startX: e.clientX,
        startWidthPx: currentWidthPx,
        containerWidth: containerWidthPx,
        containerLeft: containerRect.left,
        align: align,
        handleType: handleType,
        figure: figure,
        block: block,
        minWidth: editorInstance._options.minImageWidth,
        lastWidthPx: currentWidthPx,
        handle: handle,
        altHeld: false,
        shiftHeld: false
      };

      figure.classList.add('is-resizing');
      handle.classList.add('is-active');
      handle.setPointerCapture(e.pointerId);
      createResizeBadge(editorInstance);
    }

    function onPointerMove(e) {
      if (!state) return;
      e.preventDefault();

      state.altHeld = e.altKey;
      state.shiftHeld = e.shiftKey;

      var dx = e.clientX - state.startX;
      var delta = calcResizeDelta(dx, state.handleType, state.align);
      var newWidthPx = state.startWidthPx + delta;
      newWidthPx = clamp(newWidthPx, state.minWidth, state.containerWidth);

      state.lastWidthPx = newWidthPx;
      var pct = (newWidthPx / state.containerWidth) * 100;

      if (!state.altHeld) {
        var snapPoints = [25, 33.33, 50, 66.67, 75, 100];
        if (state.shiftHeld) {
          var rounded = Math.round(pct / 5) * 5;
          if (Math.abs(pct - rounded) <= 5) {
            pct = rounded;
            newWidthPx = (pct / 100) * state.containerWidth;
          }
        } else {
          for (var i = 0; i < snapPoints.length; i++) {
            if (Math.abs(pct - snapPoints[i]) <= 2) {
              pct = snapPoints[i];
              newWidthPx = (pct / 100) * state.containerWidth;
              break;
            }
          }
        }
      }

      state.figure.style.setProperty('--crm-ve-image-width', pct + '%');
      state.lastWidthPx = newWidthPx;
      updateResizeBadge(pct, newWidthPx, state.containerWidth);
    }

    function onPointerUp(e) {
      if (!state) return;
      state.figure.classList.remove('is-resizing');
      if (state.handle) state.handle.classList.remove('is-active');
      if (e.target && typeof e.target.releasePointerCapture === 'function') {
        try { e.target.releasePointerCapture(e.pointerId); } catch (ex) { /* ignore */ }
      }

      var finalPct = (state.lastWidthPx / state.containerWidth) * 100;
      finalPct = clamp(finalPct, (state.minWidth / state.containerWidth) * 100, 100);
      finalPct = Math.round(finalPct * 100) / 100;
      state.figure.style.setProperty('--crm-ve-image-width', finalPct + '%');
      state.block.setAttribute('data-width', String(finalPct));

      hideResizeBadge(editorInstance);
      editorInstance._history.push(editorInstance._content.innerHTML, true);
      editorInstance._sync();
      state = null;
    }

    editorInstance._content.addEventListener('pointerdown', onPointerDown);
    editorInstance._resizeCleanup = function () {
      editorInstance._content.removeEventListener('pointerdown', onPointerDown);
      if (state) {
        state = null;
        hideResizeBadge(editorInstance);
      }
    };

    document.addEventListener('pointermove', onPointerMove);
    document.addEventListener('pointerup', onPointerUp);
    editorInstance._resizeDocCleanup = function () {
      document.removeEventListener('pointermove', onPointerMove);
      document.removeEventListener('pointerup', onPointerUp);
    };
  }

  function calcResizeDelta(dx, handleType, align) {
    var isRight = handleType === 'right' || handleType === 'bottom-right';
    var sign = isRight ? 1 : -1;
    var multiplier = align === 'center' ? 2 : 1;
    return dx * sign * multiplier;
  }

  // ---------------------------------------------------------------------------
  //  Resize badge
  // ---------------------------------------------------------------------------

  var _resizeBadge = null;

  function createResizeBadge(editorInstance) {
    hideResizeBadge(editorInstance);
    var badge = document.createElement('div');
    badge.className = 'crm-ve-resize-badge';
    badge.style.cssText = (
      'position:absolute;z-index:9999;background:#1e293b;color:#fff;'
      + 'padding:4px 10px;border-radius:6px;font-size:12px;'
      + 'font-family:monospace;pointer-events:none;white-space:nowrap;'
      + 'box-shadow:0 2px 8px rgba(0,0,0,0.3);'
    );
    badge.setAttribute('data-crm-ve-badge', '1');
    editorInstance._wrapper.appendChild(badge);
    _resizeBadge = badge;
  }

  function updateResizeBadge(pct, px, containerWidth) {
    if (!_resizeBadge) return;
    _resizeBadge.textContent = Math.round(px) + ' px \u00B7 ' + Math.round(pct) + '%';
    var wrapper = _resizeBadge.parentNode;
    if (wrapper) {
      var wr = wrapper.getBoundingClientRect();
      var bw = _resizeBadge.offsetWidth;
      _resizeBadge.style.left = Math.round((wr.width - bw) / 2) + 'px';
      _resizeBadge.style.top = '8px';
    }
  }

  function hideResizeBadge() {
    if (_resizeBadge) {
      _resizeBadge.remove();
      _resizeBadge = null;
    }
  }

  // ---------------------------------------------------------------------------
  //  Image upload
  // ---------------------------------------------------------------------------

  function ownsUploadPlaceholder(editorInstance, block) {
    return !editorInstance._destroyed && editorInstance._content && editorInstance._content.contains(block);
  }

  function uploadImage(editorInstance, source, targetBlock) {
    var uploadError = t('visual_editor.upload_error', 'Could not upload image');
    return Promise.resolve().then(function () {
      return typeof source === 'string' ? readPastedBlobImage(source) : source;
    }).then(function (file) {
      if (!isImageFile(file)) {
        throw new Error(t('visual_editor.invalid_file_type', 'Invalid file type. Allowed: JPEG, PNG, WebP, GIF'));
      }
      if (file.size > editorInstance._options.maxImageSize) {
        throw new Error(t('visual_editor.file_too_large', 'File too large. Max size:') + ' ' + formatFileSize(editorInstance._options.maxImageSize));
      }
      return getImageDimensions(file).then(function (dims) {
        if (dims.width > 8000 || dims.height > 8000) {
          throw new Error(t('visual_editor.image_too_large', 'Image resolution too high. Max: 8000\u00D78000'));
        }
        if (!ownsUploadPlaceholder(editorInstance, targetBlock)) return null;
        return doUpload(editorInstance, file, targetBlock);
      });
    }).catch(function (err) {
      if (!ownsUploadPlaceholder(editorInstance, targetBlock)) return null;

      var message = err instanceof TypeError || err instanceof SyntaxError ? uploadError : (err.message || uploadError);
      var removeLabel = t('visual_editor.delete', 'Удалить');
      targetBlock.className = 'crm-ve-block crm-ve-image-block crm-ve-upload-error';
      targetBlock.innerHTML = '<div class="crm-ve-upload-error-wrap"><span role="status"></span>'
        + '<button type="button" class="crm-ve-upload-retry-btn"></button>'
        + '<button type="button" class="crm-ve-upload-remove-btn">\u2715</button></div>';
      targetBlock.querySelector('[role="status"]').textContent = message;

      var retryBtn = targetBlock.querySelector('.crm-ve-upload-retry-btn');
      retryBtn.textContent = t('visual_editor.retry', 'Повторить');
      retryBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var newPlaceholder = createUploadingPlaceholder(
          targetBlock.getAttribute('data-block-id'),
          targetBlock.getAttribute('data-align'),
          targetBlock.getAttribute('data-width'),
          { alt: targetBlock._preservedAlt, caption: targetBlock._preservedCaption, link: targetBlock._preservedLink }
        );
        targetBlock.replaceWith(newPlaceholder);
        uploadImage(editorInstance, source, newPlaceholder).catch(function () {});
      });

      var removeBtn = targetBlock.querySelector('.crm-ve-upload-remove-btn');
      removeBtn.title = removeLabel;
      removeBtn.setAttribute('aria-label', removeLabel);
      removeBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        targetBlock.remove();
        editorInstance._sync();
        editorInstance._content.focus();
      });

      editorInstance._sync();
      showErrorToast(editorInstance, message);
      throw err;
    });
  }

  function doUpload(editorInstance, file, placeholderBlock) {
    var formData = new FormData();
    formData.append('file', file);

    var csrf = getCsrfToken();
    if (csrf) {
      formData.append('_csrf_token', csrf);
    }

    return fetch(editorInstance._options.uploadUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: csrf ? { 'X-CSRF-Token': csrf } : {}
    }).then(function (response) {
      return response.json().then(function (data) {
        if (!response.ok) {
          var msg = data && (data.message || data.error);
          throw new Error(msg || t('visual_editor.upload_error', 'Upload failed'));
        }
        return data;
      });
    }).then(function (data) {
      if (!ownsUploadPlaceholder(editorInstance, placeholderBlock)) return null;
      var src = data && (data.url || data.src || data.data && (data.data.url || data.data.src));
      if (!src) {
        throw new Error(t('visual_editor.upload_no_url', 'Upload succeeded but no URL returned'));
      }
      var preservedAlt = placeholderBlock && placeholderBlock._preservedAlt;
      var alt = preservedAlt !== undefined && preservedAlt !== null ? preservedAlt : (data.alt || file.name || '');
      var blockId = placeholderBlock ? placeholderBlock.getAttribute('data-block-id') : generateId();
      var widthAttr = placeholderBlock ? placeholderBlock.getAttribute('data-width') : null;
      var width = data.width ? Number(data.width) : 0;
      var widthPct = widthAttr ? parseFloat(widthAttr) : (width > 0 ? clamp(width, editorInstance._options.minImageWidth / editorInstance._content.clientWidth * 100, 100) : 75);
      var align = (placeholderBlock && placeholderBlock.getAttribute('data-align')) || 'center';

      var newBlock = createImageBlock(src, alt, widthPct, blockId, editorInstance._options.readonly);
      newBlock.setAttribute('data-align', align);

      if (placeholderBlock && placeholderBlock._preservedCaption !== undefined) {
        var capEl = newBlock.querySelector('.crm-ve-image-caption');
        if (capEl) {
          capEl.innerHTML = placeholderBlock._preservedCaption;
          capEl.style.display = placeholderBlock._preservedCaption ? '' : 'none';
        }
      }

      if (placeholderBlock && placeholderBlock._preservedLink) {
        var fig = newBlock.querySelector('.crm-ve-image-figure');
        var imgEl = newBlock.querySelector('.crm-ve-image');
        if (fig && imgEl) {
          var a = document.createElement('a');
          a.setAttribute('href', placeholderBlock._preservedLink);
          if (/^https?:\/\//i.test(placeholderBlock._preservedLink)) {
            a.setAttribute('target', '_blank');
            a.setAttribute('rel', 'noopener noreferrer');
          }
          imgEl.parentNode.insertBefore(a, imgEl);
          a.appendChild(imgEl);
        }
      }
      placeholderBlock.replaceWith(newBlock);

      editorInstance._history.push(editorInstance._content.innerHTML, true);
      editorInstance._sync();
      selectImageBlock(editorInstance, newBlock);
      return newBlock;
    });
  }

  // Collect image files from a paste event. Prefer clipboardData.items (the
  // reliable source for screenshots and images copied from web pages —
  // clipboardData.files is often empty for those) and fall back to .files.
  function getPastedImageFiles(clipboard) {
    var files = [];
    var items = clipboard && clipboard.items;
    if (items && items.length) {
      for (var i = 0; i < items.length; i++) {
        var item = items[i];
        if (item.kind === 'file' && item.type && item.type.indexOf('image/') === 0) {
          try {
            var file = item.getAsFile();
            if (file) files.push(file);
          } catch (e) { /* ignore */ }
        }
      }
      if (files.length) return files;
    }
    var list = clipboard && clipboard.files;
    if (list && list.length) {
      for (var k = 0; k < list.length; k++) {
        if (isImageFile(list[k])) files.push(list[k]);
      }
    }
    return files;
  }

  // Upload an image that arrived as an ephemeral blob: URL in pasted HTML.
  // blob: URLs are only valid for the current document, so persisting them
  // would produce a broken image after save/reload — upload instead.
  function readPastedBlobImage(url) {
    var uploadError = t('visual_editor.upload_error', 'Could not upload image');
    if (!/^blob:/i.test(url)) return Promise.reject(new Error(uploadError));
    return fetch(url).then(function (resp) {
      if (!resp.ok) throw new Error(uploadError);
      return resp.blob();
    }).then(function (blob) {
      var type = blob && blob.type && blob.type.indexOf('image/') === 0 ? blob.type : 'image/png';
      var ext = type.split('/')[1] || 'png';
      if (ext === 'jpeg') ext = 'jpg';
      return new File([blob], 'pasted_image.' + ext, { type: type });
    });
  }

  // ---------------------------------------------------------------------------
  //  Editor
  // ---------------------------------------------------------------------------

  function Editor(textarea, options) {
    if (!textarea || textarea.tagName !== 'TEXTAREA') {
      throw new Error('CRM.VisualEditor.Editor requires a textarea element');
    }

    this._textarea = textarea;
    this._options = {
      textarea: textarea,
      uploadUrl: textarea.getAttribute('data-crm-visual-editor-upload-url') || (window.CRM && window.CRM.api && window.CRM.api.buildUrl ? window.CRM.api.buildUrl('api/v1/visual-editor/upload-image') : '/api/index.php?route=api/v1/visual-editor/upload-image'),
      placeholder: textarea.getAttribute('data-crm-visual-editor-placeholder') || textarea.getAttribute('placeholder') || '',
      readonly: false,
      minImageWidth: parseInt(textarea.getAttribute('data-crm-visual-editor-min-image-width') || '120', 10) || 120,
      maxImageSize: 10 * 1024 * 1024
    };

    if (options) {
      if (options.uploadUrl !== undefined) this._options.uploadUrl = options.uploadUrl;
      if (options.placeholder !== undefined) this._options.placeholder = options.placeholder;
      if (options.readonly !== undefined) this._options.readonly = options.readonly;
      if (options.minImageWidth !== undefined) this._options.minImageWidth = options.minImageWidth;
      if (options.maxImageSize !== undefined) this._options.maxImageSize = options.maxImageSize;
    }

    this._history = createHistory();
    this._selectedImageBlock = null;
    this._imageToolbarTarget = null;
    this._replaceTarget = null;
    this._destroyed = false;
    this._debouceTimer = null;
    this._lastTextareaValue = textarea.value || '';

    this._build();
    this._bindEvents();
    this._syncEditorFromTextarea();
    this._cleanPastedArtifacts();
    this._updateEmptyState();

    this._history.push(this._content.innerHTML, true);
  }

  Editor.prototype._build = function () {
    var textarea = this._textarea;
    var self = this;

    textarea.style.cssText = (
      'opacity:0;position:absolute;width:1px;height:1px;overflow:hidden;'
      + 'clip:rect(0,0,0,0);border:0;padding:0;margin:-1px;'
    );
    textarea.setAttribute('tabindex', '-1');
    textarea.setAttribute('aria-hidden', 'true');
    textarea.tabIndex = -1;

    var wrapper = document.createElement('div');
    wrapper.className = 'crm-ve-editor crm-ve-wrapper';
    if (this._options.readonly) {
      wrapper.classList.add('is-readonly');
    }
    this._wrapper = wrapper;

    var toolbar = document.createElement('div');
    toolbar.className = 'crm-ve-toolbar';
    toolbar.setAttribute('role', 'toolbar');
    toolbar.setAttribute('aria-label', t('visual_editor.toolbar_aria', 'Панель форматирования'));
    this._toolbar = toolbar;
    updateStickyToolbarOffset(this);

    var onResizeOrScroll = function () {
      updateStickyToolbarOffset(self);
    };
    window.addEventListener('resize', onResizeOrScroll);
    window.addEventListener('scroll', onResizeOrScroll, { passive: true });
    this._onResizeOrScroll = onResizeOrScroll;

    var content = document.createElement('div');
    content.className = 'crm-ve-content';
    content.setAttribute('contenteditable', String(!this._options.readonly));
    content.setAttribute('role', 'textbox');
    content.setAttribute('aria-multiline', 'true');

    var labelText = textarea.getAttribute('aria-label') || '';
    var labelEl = null;
    var labelledBy = textarea.getAttribute('aria-labelledby');
    if (labelledBy) {
      labelEl = document.getElementById(labelledBy);
    }
    if (!labelEl && textarea.id) {
      labelEl = document.querySelector('label[for="' + textarea.id + '"]');
    }
    if (!labelEl) {
      var parentLabel = textarea.closest('label');
      if (parentLabel) {
        labelEl = parentLabel;
      }
    }
    if (!labelEl) {
      var fg = textarea.closest('.form-group, .mb-3, .form-floating, .crm-form-group');
      if (fg && !fg.matches('form, body')) {
        var candLabel = fg.querySelector(':scope > label, :scope > .form-label, label, .form-label');
        if (candLabel) {
          var candFor = candLabel.getAttribute('for');
          if (!candFor || (textarea.id && candFor === textarea.id)) {
            var otherInputs = fg.querySelectorAll('input, select, textarea');
            if (otherInputs.length <= 1) {
              labelEl = candLabel;
            }
          }
        }
      }
    }
    if (labelEl) {
      var forTarget = labelEl.getAttribute('for');
      if (forTarget && textarea.id && forTarget !== textarea.id) {
        labelEl = null;
      }
    }
    if (labelEl) {
      if (!labelEl.id) labelEl.id = 'crm-ve-label-' + generateId();
      content.setAttribute('aria-labelledby', labelEl.id);
      labelText = labelEl.textContent.trim();
    }
    if (!labelText) {
      var nameAttr = (textarea.getAttribute('name') || '').toLowerCase();
      if (nameAttr === 'description' || nameAttr.indexOf('description') >= 0) {
        labelText = t('visual_editor.description', 'Описание');
      } else if (nameAttr === 'content' || nameAttr === 'body' || nameAttr.indexOf('content') >= 0) {
        labelText = t('visual_editor.content', 'Содержимое');
      } else if (nameAttr === 'comment' || nameAttr.indexOf('comment') >= 0) {
        labelText = t('visual_editor.comment', 'Комментарий');
      } else {
        labelText = textarea.getAttribute('placeholder') || t('visual_editor.content', 'Содержимое');
      }
    }
    if (labelText) content.setAttribute('aria-label', labelText);
    if (this._options.placeholder) {
      content.setAttribute('data-placeholder', this._options.placeholder);
    }
    this._content = content;

    var emptyActions = document.createElement('div');
    emptyActions.className = 'crm-ve-empty-actions';
    emptyActions.innerHTML = ''
      + '<button type="button" class="crm-ve-quick-chip" data-crm-ve-quick="image">' + t('visual_editor.quick_image', '+ Image') + '</button>'
      + '<button type="button" class="crm-ve-quick-chip" data-crm-ve-quick="heading">' + t('visual_editor.quick_heading', '# Heading') + '</button>'
      + '<button type="button" class="crm-ve-quick-chip" data-crm-ve-quick="list">' + t('visual_editor.quick_list', '• List') + '</button>'
      + '<button type="button" class="crm-ve-quick-chip" data-crm-ve-quick="paste">' + t('visual_editor.quick_paste', 'Ctrl+V Screenshot') + '</button>';
    this._emptyActions = emptyActions;

    var imageToolbar = createImageToolbar(this);
    this._imageToolbar = imageToolbar;

    var fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = 'image/jpeg,image/png,image/webp,image/gif';
    fileInput.multiple = true;
    fileInput.style.cssText = 'display:none';
    this._fileInput = fileInput;
    var self2 = this;
    fileInput.addEventListener('change', function () {
      var files = fileInput.files;
      if (!files || !files.length) return;
      var target = self2._replaceTarget;
      self2._replaceTarget = null;
      var preservedAlign = target ? (target.getAttribute('data-align') || 'center') : 'center';
      var preservedWidth = target ? target.getAttribute('data-width') : null;
      var targetImg = target ? target.querySelector('.crm-ve-image') : null;
      var targetCap = target ? target.querySelector('.crm-ve-image-caption') : null;
      var targetLink = target ? target.querySelector('a') : null;
      var preservedProps = target ? {
        alt: targetImg ? targetImg.getAttribute('alt') : '',
        caption: targetCap && targetCap.style.display !== 'none' ? targetCap.innerHTML : '',
        link: targetLink ? targetLink.getAttribute('href') : ''
      } : null;

      for (var i = 0; i < files.length; i++) {
        var placeholder;
        if (i === 0 && target && target.parentNode) {
          placeholder = createUploadingPlaceholder(generateId(), preservedAlign, preservedWidth, preservedProps);
          target.parentNode.replaceChild(placeholder, target);
          deselectImage(self2);
        } else {
          placeholder = createUploadingPlaceholder(generateId());
          self2._insertAtCaret(placeholder);
        }
        (function (tBlock, origTarget) {
          uploadImage(self2, files[i], tBlock).catch(function () {
            if (origTarget && tBlock && tBlock.parentNode) {
              tBlock.parentNode.replaceChild(origTarget, tBlock);
              self2._sync();
            }
          });
        })(placeholder, i === 0 ? target : null);
      }
      fileInput.value = '';
    });

    this._buildToolbar();

    wrapper.appendChild(toolbar);
    wrapper.appendChild(content);
    wrapper.appendChild(emptyActions);
    wrapper.appendChild(imageToolbar);
    wrapper.appendChild(fileInput);

    emptyActions.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-crm-ve-quick]');
      if (!btn) return;
      e.preventDefault();
      var action = btn.getAttribute('data-crm-ve-quick');
      self2._content.focus();
      if (action === 'image') {
        self2._execImageUpload();
      } else if (action === 'heading') {
        document.execCommand('formatBlock', false, '<h2>');
      } else if (action === 'list') {
        document.execCommand('insertUnorderedList', false, null);
      } else if (action === 'paste') {
        // The chip is a hint: focus the editor so the user can press Ctrl+V
        // to paste a screenshot from the clipboard.
        notify(t('visual_editor.paste_hint', 'Скопируйте скриншот, затем нажмите Ctrl+V в редакторе'), 'info');
      }
      self2._sync();
      self2._updateEmptyState();
    });

    textarea.parentNode.insertBefore(wrapper, textarea.nextSibling);

    initImageResize(this);
  };

  Editor.prototype._buildToolbar = function () {
    var toolbar = this._toolbar;
    var self = this;

    function addBtn(label, title, cmd, value, isPrimary) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'crm-ve-toolbar-btn' + (isPrimary ? '' : ' crm-ve-btn-secondary');
      btn.innerHTML = label;
      btn.title = title || '';
      btn.setAttribute('aria-label', title || '');
      if (typeof cmd === 'string') {
        btn.setAttribute('data-cmd', cmd);
        if (['bold', 'italic', 'strikeThrough', 'formatBlock', 'quote', 'inlineCode', 'spoiler', 'codeBlock', 'insertUnorderedList', 'insertOrderedList', 'todoList'].indexOf(cmd) !== -1) {
          btn.setAttribute('aria-pressed', 'false');
        }
      }
      if (value) {
        btn.setAttribute('data-value', String(value).toLowerCase());
      }
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        self._content.focus();
        if (typeof cmd === 'function') {
          cmd();
        } else if (cmd === 'removeFormat') {
          self._execRemoveFormat();
        } else if (cmd === 'quote') {
          self._execQuote();
        } else if (cmd === 'formatBlock') {
          var targetVal = String(value || 'p').toLowerCase();
          var currentBlock = self._getCurrentBlockTag();
          if (targetVal !== 'p' && currentBlock === targetVal) {
            document.execCommand('formatBlock', false, '<p>');
          } else {
            document.execCommand('formatBlock', false, '<' + targetVal + '>');
          }
        } else if (cmd === 'createLink') {
          self._execLink();
        } else if (cmd === 'insertImage') {
          self._execImageUpload();
        } else if (cmd === 'inlineCode') {
          self._execInlineCode();
        } else if (cmd === 'spoiler') {
          self._execSpoiler();
        } else if (cmd === 'codeBlock') {
          self._execCodeBlock();
        } else if (cmd === 'todoList') {
          self._execTodoList();
        } else if (cmd === 'insertTable') {
          self._execTable(3, 3);
        } else {
          document.execCommand(cmd, false, value || null);
        }
        self._sync();
        self._history.push(self._content.innerHTML, false);
        self._updateActiveButtons();
      });
      toolbar.appendChild(btn);
      return btn;
    }

    function addSep() {
      var sep = document.createElement('span');
      sep.className = 'crm-ve-toolbar-sep';
      sep.textContent = ' ';
      toolbar.appendChild(sep);
    }

    addBtn('<strong>B</strong>', t('visual_editor.bold', 'Bold'), 'bold', null, true);
    addBtn('<em>I</em>', t('visual_editor.italic', 'Italic'), 'italic', null, true);
    addBtn('<s>S</s>', t('visual_editor.strike', 'Strikethrough'), 'strikeThrough', null, false);
    addBtn('<code>&lt;/&gt;</code>', t('visual_editor.code', 'Inline code'), 'inlineCode', null, false);
    addBtn(
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 15l-3-3 3-3M9 21h6M19 15l3-3-3-3M12 3v18"/></svg>',
      t('visual_editor.spoiler', 'Spoiler'),
      'spoiler',
      null,
      false
    );
    addBtn(
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
      t('visual_editor.link', 'Link'),
      'createLink',
      null,
      true
    );
    addBtn(
      '<i class="fa-solid fa-remove-format" aria-hidden="true"></i>',
      t('visual_editor.remove_format', 'Отмена форматирования'),
      'removeFormat',
      null,
      false
    );
    addSep();
    addBtn('P', t('visual_editor.paragraph', 'Обычный текст'), 'formatBlock', 'p', false);
    addBtn('H2', t('visual_editor.heading2', 'Heading 2'), 'formatBlock', 'h2', true);
    addBtn('H3', t('visual_editor.heading3', 'Heading 3'), 'formatBlock', 'h3', false);
    addSep();
    addBtn(
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
      t('visual_editor.bullet_list', 'Bullet list'),
      'insertUnorderedList',
      null,
      true
    );
    addBtn(
      '<i class="fa-solid fa-list-ol" aria-hidden="true"></i>',
      t('visual_editor.ordered_list', 'Ordered list'),
      'insertOrderedList',
      null,
      false
    );
    addBtn(
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><path d="M3 14h7v7"/></svg>',
      t('visual_editor.todo_list', 'To-do list'),
      'todoList',
      null,
      false
    );
    addBtn(
      '<i class="fa-solid fa-table-cells" aria-hidden="true"></i>',
      t('visual_editor.table', 'Table'),
      'insertTable',
      null,
      false
    );
    addBtn(
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
      t('visual_editor.quote', 'Quote'),
      'quote',
      null,
      false
    );
    addBtn(
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
      t('visual_editor.code_block', 'Code block'),
      'codeBlock',
      null,
      false
    );
    addSep();
    addBtn(
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
      t('visual_editor.image', 'Image'),
      'insertImage',
      null,
      false
    );
    addSep();
    addBtn(
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><polyline points="12 7 12 12 16 14"/></svg>',
      t('visual_editor.undo', 'Undo'),
      function () { self.undo(); },
      null,
      false
    );
    addBtn(
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><polyline points="12 7 12 12 16 14"/></svg>',
      t('visual_editor.redo', 'Redo'),
      function () { self.redo(); },
      null,
      false
    );

    // Responsive "More" button and dropdown menu
    var moreBtn = document.createElement('button');
    moreBtn.type = 'button';
    moreBtn.className = 'crm-ve-toolbar-btn crm-ve-more-btn';
    moreBtn.innerHTML = t('visual_editor.more', 'Ещё') + ' \u25BE';
    moreBtn.title = t('visual_editor.more', 'Ещё');
    moreBtn.setAttribute('aria-label', t('visual_editor.more', 'Ещё'));
    moreBtn.setAttribute('aria-haspopup', 'true');
    moreBtn.setAttribute('aria-expanded', 'false');

    var moreMenu = document.createElement('div');
    moreMenu.className = 'crm-ve-more-menu';
    moreMenu.setAttribute('role', 'menu');
    this._moreMenu = moreMenu;
    this._moreBtn = moreBtn;

    function addMoreItem(iconHtml, text, actionFn) {
      var item = document.createElement('button');
      item.type = 'button';
      item.className = 'crm-ve-more-item';
      item.setAttribute('role', 'menuitem');
      item.innerHTML = '<span class="crm-ve-more-item-icon">' + iconHtml + '</span><span>' + text + '</span>';
      item.addEventListener('mousedown', function (e) {
        e.preventDefault();
      });
      item.addEventListener('click', function (e) {
        e.preventDefault();
        self._hideMoreMenu();
        self._restoreSavedSelection();
        self._content.focus();
        actionFn();
        self._sync();
        self._history.push(self._content.innerHTML, false);
        self._updateActiveButtons();
      });
      moreMenu.appendChild(item);
    }

    addMoreItem('<s>S</s>', t('visual_editor.strike', 'Зачеркнутый'), function () { document.execCommand('strikeThrough'); });
    addMoreItem('<code>&lt;/&gt;</code>', t('visual_editor.code', 'Код'), function () { self._execInlineCode(); });
    addMoreItem('\u25BD', t('visual_editor.spoiler', 'Разворачивание'), function () { self._execSpoiler(); });
    addMoreItem('H3', t('visual_editor.heading3', 'Заголовок H3'), function () { document.execCommand('formatBlock', false, '<h3>'); });
    addMoreItem('P', t('visual_editor.paragraph', 'Обычный текст'), function () { document.execCommand('formatBlock', false, '<p>'); });
    addMoreItem('Tx', t('visual_editor.remove_format', 'Отмена форматирования'), function () { self._execRemoveFormat(); });
    addMoreItem('1.', t('visual_editor.ordered_list', 'Нумерованный список'), function () { document.execCommand('insertOrderedList'); });
    addMoreItem('\u2611', t('visual_editor.todo_list', 'Список задач'), function () { self._execTodoList(); });
    addMoreItem('\u25A6', t('visual_editor.table', 'Таблица'), function () { self._execTable(3, 3); });
    addMoreItem('\u201C', t('visual_editor.quote', 'Цитата'), function () { self._execQuote(); });
    addMoreItem('&lt;/&gt;', t('visual_editor.code_block', 'Блок кода'), function () { self._execCodeBlock(); });
    addMoreItem('\uD83D\uDDBC', t('visual_editor.image', 'Изображение'), function () { self._execImageUpload(); });
    addMoreItem('\u21A9', t('visual_editor.undo', 'Отменить'), function () { self.undo(); });
    addMoreItem('\u21AA', t('visual_editor.redo', 'Повторить'), function () { self.redo(); });

    moreBtn.addEventListener('mousedown', function (e) {
      e.preventDefault();
      self._saveCurrentSelection();
    });

    moreBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (self._moreMenuActive) {
        self._hideMoreMenu();
      } else {
        self._saveCurrentSelection();
        self._showMoreMenu();
      }
    });

    toolbar.appendChild(moreBtn);
    toolbar.appendChild(moreMenu);
  };

  Editor.prototype._saveCurrentSelection = function () {
    var sel = window.getSelection();
    if (sel && sel.rangeCount) {
      var range = sel.getRangeAt(0);
      if (this._content && this._content.contains(range.startContainer)) {
        this._savedRange = range.cloneRange();
        return;
      }
    }
    this._savedRange = null;
  };

  Editor.prototype._restoreSavedSelection = function () {
    if (this._savedRange) {
      var sel = window.getSelection();
      if (sel) {
        sel.removeAllRanges();
        sel.addRange(this._savedRange);
      }
    }
  };

  Editor.prototype._showMoreMenu = function () {
    if (!this._moreMenu) return;
    this._moreMenuActive = true;
    this._moreMenu.style.display = 'block';
    if (this._moreBtn) this._moreBtn.setAttribute('aria-expanded', 'true');
  };

  Editor.prototype._hideMoreMenu = function () {
    if (!this._moreMenu) return;
    this._moreMenuActive = false;
    this._moreMenu.style.display = 'none';
    if (this._moreBtn) this._moreBtn.setAttribute('aria-expanded', 'false');
  };

  Editor.prototype._execLink = function () {
    var selection = window.getSelection();
    var href = window.prompt(t('visual_editor.link_prompt', 'Enter link URL:'), 'https://');
    if (!href) return;
    var safeHref = sanitizeLinkHref(href);
    if (!safeHref) return;

    var range = selection && selection.rangeCount ? selection.getRangeAt(0) : null;
    if (!range || range.collapsed) {
      var text = prompt(t('visual_editor.link_text_prompt', 'Enter link text:'), '');
      if (!text) return;
      var anchor = document.createElement('a');
      anchor.setAttribute('href', safeHref);
      if (/^https?:\/\//i.test(safeHref)) {
        anchor.setAttribute('target', '_blank');
        anchor.setAttribute('rel', 'noopener noreferrer');
      }
      anchor.textContent = text;
      range.insertNode(anchor);
      range.setStartAfter(anchor);
      range.setEndAfter(anchor);
      selection.removeAllRanges();
      selection.addRange(range);
    } else {
      document.execCommand('createLink', false, safeHref);
      var sel = window.getSelection();
      if (sel.rangeCount) {
        var r = sel.getRangeAt(0);
        var container = r.commonAncestorContainer;
        var link = container.nodeType === 3 ? container.parentNode : container;
        while (link && link.tagName !== 'A') {
          link = link.parentNode;
        }
        if (link && link.tagName === 'A') {
          link.setAttribute('href', safeHref);
          if (/^https?:\/\//i.test(safeHref)) {
            link.setAttribute('target', '_blank');
            link.setAttribute('rel', 'noopener noreferrer');
          } else {
            link.removeAttribute('target');
            link.removeAttribute('rel');
          }
        }
      }
    }
  };

  Editor.prototype._execImageUpload = function () {
    this._replaceTarget = null;
    this._fileInput.click();
  };

  // Converts a selected fragment to plain text for a code block, preserving
  // line breaks across <br> and block-level elements.
  function fragmentToCodeText(fragment) {
    var out = [];
    function walk(node) {
      Array.prototype.slice.call(node.childNodes || []).forEach(function (child) {
        if (child.nodeType === 3) {
          out.push(child.nodeValue || '');
        } else if (child.nodeType === 1) {
          var tag = child.tagName.toUpperCase();
          if (tag === 'BR') {
            out.push('\n');
          } else {
            var block = BLOCK_TAGS[tag] === true;
            if (block) out.push('\n');
            walk(child);
            if (block) out.push('\n');
          }
        }
      });
    }
    walk(fragment);
    var text = out.join('').replace(/\u00a0/g, ' ').replace(/\r\n?/g, '\n');
    text = text.replace(/\n{2,}/g, '\n').replace(/^\n+/, '').replace(/\n+$/, '');
    return text;
  }

  Editor.prototype._execQuote = function () {
    var sel = window.getSelection();
    if (!sel || !sel.rangeCount) return;
    var node = sel.anchorNode;
    if (node && node.nodeType === 3) node = node.parentNode;
    var bq = node ? node.closest('blockquote') : null;
    if (bq && this._content.contains(bq)) {
      var parent = bq.parentNode;
      var frag = document.createDocumentFragment();
      while (bq.firstChild) {
        var child = bq.firstChild;
        if (child.nodeType === 1 && (child.tagName === 'P' || child.tagName === 'DIV')) {
          frag.appendChild(child);
        } else if (child.nodeType === 3 && !child.textContent.trim()) {
          bq.removeChild(child);
        } else {
          var p = document.createElement('p');
          p.appendChild(child);
          frag.appendChild(p);
        }
      }
      if (!frag.childNodes.length) {
        var emptyP = document.createElement('p');
        emptyP.innerHTML = '<br>';
        frag.appendChild(emptyP);
      }
      var firstP = frag.firstChild;
      parent.replaceChild(frag, bq);
      if (firstP) {
        var range = document.createRange();
        range.selectNodeContents(firstP);
        range.collapse(false);
        sel.removeAllRanges();
        sel.addRange(range);
      }
    } else {
      document.execCommand('formatBlock', false, '<blockquote>');
    }
    this._sync();
    this._history.push(this._content.innerHTML, true);
    this._updateActiveButtons();
  };

  // Spoiler (details/summary): wraps the current selection inside the spoiler,
  // or inserts an empty spoiler when there is no selection.
  Editor.prototype._execSpoiler = function () {
    var summaryText = (window.CRM && window.CRM.i18n)
      ? window.CRM.i18n.t('visual_editor.spoiler_summary', 'Разворачивание')
      : 'Разворачивание';
    var selection = window.getSelection();
    var range = selection && selection.rangeCount ? selection.getRangeAt(0) : null;

    var details = document.createElement('details');
    details.className = 'crm-ve-spoiler';
    var summary = document.createElement('summary');
    summary.textContent = summaryText;
    details.appendChild(summary);

    if (range && !range.collapsed) {
      // Wrap the selected content inside the spoiler instead of replacing it.
      var fragment = range.extractContents();
      var body = document.createElement('div');
      body.appendChild(fragment);
      // Inline-only selections get a paragraph so the spoiler body stays a
      // proper block; block selections already carry their own structure.
      if (!body.querySelector('p, h1, h2, h3, blockquote, pre, ul, ol, figure, details')) {
        var inlineParagraph = document.createElement('p');
        while (body.firstChild) inlineParagraph.appendChild(body.firstChild);
        body.appendChild(inlineParagraph);
      }
      while (body.firstChild) details.appendChild(body.firstChild);
      var curP = range.startContainer.nodeType === 1 ? range.startContainer.closest('p') : (range.startContainer.parentNode ? range.startContainer.parentNode.closest('p') : null);
      if (curP && this._content.contains(curP)) {
        if (curP.nextSibling) curP.parentNode.insertBefore(details, curP.nextSibling);
        else curP.parentNode.appendChild(details);
        if (curP.textContent.trim() === '' && !curP.querySelector('img, figure')) curP.remove();
      } else {
        range.insertNode(details);
      }
      var afterRange = document.createRange();
      afterRange.setStartAfter(details);
      afterRange.collapse(true);
      selection.removeAllRanges();
      selection.addRange(afterRange);
    } else {
      var emptyParagraph = document.createElement('p');
      emptyParagraph.innerHTML = '<br>';
      details.appendChild(emptyParagraph);
      details.open = true;
      if (range) {
        var curP2 = range.startContainer.nodeType === 1 ? range.startContainer.closest('p') : (range.startContainer.parentNode ? range.startContainer.parentNode.closest('p') : null);
        if (curP2 && this._content.contains(curP2)) {
          var pText2 = (curP2.textContent || '').replace(/[\u200B\uFEFF]/g, '').trim();
          if (!pText2 && !curP2.querySelector('img, figure')) {
            curP2.parentNode.replaceChild(details, curP2);
          } else {
            if (curP2.nextSibling) curP2.parentNode.insertBefore(details, curP2.nextSibling);
            else curP2.parentNode.appendChild(details);
          }
        } else {
          range.insertNode(details);
        }
      } else {
        this._content.appendChild(details);
      }
      var r = document.createRange();
      r.setStart(emptyParagraph, 0);
      r.collapse(true);
      selection.removeAllRanges();
      selection.addRange(r);
    }

    this._sync();
    this._history.push(this._content.innerHTML, true);
    this._updateEmptyState();
  };

  Editor.prototype._execCodeBlock = function () {
    var selection = window.getSelection();
    var range = selection && selection.rangeCount ? selection.getRangeAt(0) : null;
    if (!range) return;

    var pre = document.createElement('pre');
    var code = document.createElement('code');

    if (range.collapsed) {
      code.appendChild(document.createElement('br'));
      pre.appendChild(code);
      var startNode = range.startContainer;
      var curP = startNode ? (startNode.nodeType === 1 ? startNode : startNode.parentNode).closest('p') : null;
      if (curP && curP.parentNode === this._content && curP.textContent.trim() === '') {
        curP.parentNode.replaceChild(pre, curP);
      } else {
        range.insertNode(pre);
      }
      var caret = document.createRange();
      caret.setStartBefore(code.firstChild);
      caret.collapse(true);
      selection.removeAllRanges();
      selection.addRange(caret);
    } else {
      var fragment = range.extractContents();
      code.textContent = fragmentToCodeText(fragment);
      if (!code.textContent) code.appendChild(document.createElement('br'));
      pre.appendChild(code);
      range.insertNode(pre);
      var after = document.createRange();
      after.setStartAfter(pre);
      after.collapse(true);
      selection.removeAllRanges();
      selection.addRange(after);
    }

    this._sync();
    this._history.push(this._content.innerHTML, true);
    this._updateEmptyState();
  };

  // ---------------------------------------------------------------------------
  //  Todo list
  // ---------------------------------------------------------------------------

  // Creates a single todo list item: <li><span class="crm-ve-todo-cb" data-checked=""><span>text</span></li>
  function createTodoItem(text, checked) {
    var li = document.createElement('li');
    li.className = 'crm-ve-todo-item' + (checked ? ' is-checked' : '');
    if (checked) li.setAttribute('data-checked', 'true');

    var cb = document.createElement('span');
    cb.className = 'crm-ve-todo-cb';
    cb.setAttribute('role', 'checkbox');
    cb.setAttribute('tabindex', '0');
    cb.setAttribute('contenteditable', 'false');
    cb.setAttribute('aria-checked', checked ? 'true' : 'false');
    if (checked) cb.setAttribute('data-checked', 'true');

    var span = document.createElement('span');
    span.className = 'crm-ve-todo-text';
    span.contentEditable = 'true';
    span.textContent = text || '';
    if (!text) span.appendChild(document.createElement('br'));

    var itemLabel = (t('visual_editor.todo_item', 'Пункт чек-листа') + ': ' + (text || '').trim()).trim();
    cb.setAttribute('aria-label', itemLabel);

    li.appendChild(cb);
    li.appendChild(span);
    return li;
  }

  Editor.prototype._execTodoList = function () {
    var selection = window.getSelection();
    var range = selection && selection.rangeCount ? selection.getRangeAt(0) : null;
    if (!range || !this._content.contains(range.startContainer)) {
      var fallbackUl = document.createElement('ul');
      fallbackUl.className = 'crm-ve-todo-list';
      fallbackUl.appendChild(createTodoItem('', false));
      this._content.appendChild(fallbackUl);
      this._sync();
      this._history.push(this._content.innerHTML, true);
      this._updateEmptyState();
      var fSpan = fallbackUl.querySelector('.crm-ve-todo-text');
      if (fSpan && selection) {
        var rFallback = document.createRange();
        rFallback.setStart(fSpan, 0);
        rFallback.collapse(true);
        selection.removeAllRanges();
        selection.addRange(rFallback);
      }
      return;
    }

    var ul = document.createElement('ul');
    ul.className = 'crm-ve-todo-list';

    if (range.collapsed) {
      ul.appendChild(createTodoItem('', false));
      range.insertNode(ul);
      // Place caret inside the todo text span
      var firstLi = ul.querySelector('li');
      var textSpan = firstLi ? firstLi.querySelector('.crm-ve-todo-text') : null;
      if (textSpan) {
        var caretRange = document.createRange();
        caretRange.setStart(textSpan, 0);
        caretRange.collapse(true);
        selection.removeAllRanges();
        selection.addRange(caretRange);
      }
    } else {
      // Wrap selected content in todo items (one per line)
      var fragment = range.extractContents();
      var tempDiv = document.createElement('div');
      tempDiv.appendChild(fragment);
      var lines = tempDiv.textContent.split('\n').map(function (l) { return l.trim(); }).filter(Boolean);
      if (!lines.length) lines = [''];
      lines.forEach(function (line) {
        ul.appendChild(createTodoItem(line, false));
      });
      range.insertNode(ul);
    }

    this._sync();
    this._history.push(this._content.innerHTML, true);
    this._updateEmptyState();
  };

  // Toggle a todo checkbox
  Editor.prototype._toggleTodo = function (checkbox) {
    if (!checkbox) return;
    this._history.push(this._content.innerHTML, true);
    var isChecked = checkbox.getAttribute('data-checked') === 'true' || checkbox.getAttribute('aria-checked') === 'true';
    var nextChecked = !isChecked;
    if (nextChecked) {
      checkbox.setAttribute('data-checked', 'true');
      checkbox.setAttribute('aria-checked', 'true');
    } else {
      checkbox.removeAttribute('data-checked');
      checkbox.setAttribute('aria-checked', 'false');
    }
    var li = checkbox.closest('.crm-ve-todo-item');
    if (li) {
      li.classList.toggle('is-checked', nextChecked);
      if (nextChecked) {
        li.setAttribute('data-checked', 'true');
      } else {
        li.removeAttribute('data-checked');
      }
    }
    this._sync();
    this._history.push(this._content.innerHTML, true);
  };

  // Handle Enter/Backspace/Tab/Space inside todo items
  Editor.prototype._handleTodoKeydown = function (e) {
    var sel = window.getSelection();
    var node = sel && sel.rangeCount ? sel.anchorNode : null;
    if (node && node.nodeType === 3) node = node.parentNode;
    var activeEl = document.activeElement;

    var focusedCb = (activeEl && activeEl.closest) ? activeEl.closest('.crm-ve-todo-cb') : null;
    if (!focusedCb && node && node.closest) {
      focusedCb = node.closest('.crm-ve-todo-cb');
    }

    var li = focusedCb ? focusedCb.closest('.crm-ve-todo-item') : (node ? node.closest('.crm-ve-todo-item') : null);
    if (!li) return false;
    var ul = li.closest('.crm-ve-todo-list');
    if (!ul || !this._content.contains(ul)) return false;

    var todoText = li.querySelector('.crm-ve-todo-text');
    var cb = li.querySelector('.crm-ve-todo-cb');

    // Case 1: Focus is directly on the checkbox
    if (focusedCb) {
      if (e.key === ' ') {
        e.preventDefault();
        this._toggleTodo(focusedCb);
        return true;
      }
      if (e.key === 'Tab') {
        if (!e.shiftKey) {
          // Tab moves focus to todoText of this item
          e.preventDefault();
          if (todoText) {
            todoText.focus();
            var r = document.createRange();
            r.selectNodeContents(todoText);
            r.collapse(true);
            sel.removeAllRanges();
            sel.addRange(r);
          }
          return true;
        } else {
          // Shift+Tab moves focus to previous item's text, or exits backwards
          var prevLi = li.previousElementSibling;
          if (prevLi) {
            e.preventDefault();
            var prevText = prevLi.querySelector('.crm-ve-todo-text');
            if (prevText) {
              prevText.focus();
              var rPrev = document.createRange();
              rPrev.selectNodeContents(prevText);
              rPrev.collapse(false);
              sel.removeAllRanges();
              sel.addRange(rPrev);
            }
            return true;
          } else {
            // First item: exit checklist backwards
            var prevBlock = ul.previousElementSibling;
            if (prevBlock) {
              e.preventDefault();
              var rExitPrev = document.createRange();
              rExitPrev.selectNodeContents(prevBlock);
              rExitPrev.collapse(false);
              sel.removeAllRanges();
              sel.addRange(rExitPrev);
              return true;
            } else {
              e.preventDefault();
              var pPrev = document.createElement('p');
              pPrev.innerHTML = '<br>';
              ul.parentNode.insertBefore(pPrev, ul);
              var rPPrev = document.createRange();
              rPPrev.setStart(pPrev, 0);
              rPPrev.collapse(true);
              sel.removeAllRanges();
              sel.addRange(rPPrev);
              return true;
            }
          }
        }
      }
      if (e.key === 'Enter') {
        e.preventDefault();
        if (todoText) todoText.focus();
        return true;
      }
      return false;
    }

    // Case 2: Focus is inside todoText
    if (e.key === 'Tab') {
      if (e.shiftKey) {
        // Shift+Tab returns focus to this item's checkbox
        e.preventDefault();
        if (cb) {
          cb.focus();
        }
        return true;
      } else {
        // Tab moves focus to next item's checkbox
        var nextLi = li.nextElementSibling;
        if (nextLi) {
          e.preventDefault();
          var nextCb = nextLi.querySelector('.crm-ve-todo-cb');
          if (nextCb) {
            nextCb.focus();
          }
          return true;
        } else {
          // Last item: exit checklist forwards
          var nextBlock = ul.nextElementSibling;
          if (nextBlock) {
            e.preventDefault();
            var rNext = document.createRange();
            rNext.selectNodeContents(nextBlock);
            rNext.collapse(true);
            sel.removeAllRanges();
            sel.addRange(rNext);
            return true;
          } else {
            e.preventDefault();
            var pNext = document.createElement('p');
            pNext.innerHTML = '<br>';
            ul.parentNode.appendChild(pNext);
            var rP = document.createRange();
            rP.setStart(pNext, 0);
            rP.collapse(true);
            sel.removeAllRanges();
            sel.addRange(rP);
            this._sync();
            return true;
          }
        }
      }
    }

    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      var rawText = (todoText ? todoText.textContent : li.textContent || '').replace(/[\u200B\uFEFF]/g, '').trim();

      // If empty item: exit checklist!
      if (rawText === '') {
        li.remove();
        var newP = document.createElement('p');
        newP.innerHTML = '<br>';
        if (ul.nextSibling) {
          ul.parentNode.insertBefore(newP, ul.nextSibling);
        } else {
          ul.parentNode.appendChild(newP);
        }
        if (!ul.children.length) {
          ul.remove();
        }
        var rExit = document.createRange();
        rExit.setStart(newP, 0);
        rExit.collapse(true);
        sel.removeAllRanges();
        sel.addRange(rExit);
        this._sync();
        this._history.push(this._content.innerHTML, true);
        this._updateActiveButtons();
        return true;
      }

      // Non-empty item: create new todo item after current one
      var newLi = createTodoItem('', false);
      if (li.nextSibling) {
        ul.insertBefore(newLi, li.nextSibling);
      } else {
        ul.appendChild(newLi);
      }
      var newSpan = newLi.querySelector('.crm-ve-todo-text');
      var rNew = document.createRange();
      rNew.setStart(newSpan, 0);
      rNew.collapse(true);
      sel.removeAllRanges();
      sel.addRange(rNew);
      this._sync();
      this._history.push(this._content.innerHTML, true);
      this._updateActiveButtons();
      return true;
    }

    if (e.key === 'Backspace') {
      var rawText2 = (todoText ? todoText.textContent : li.textContent || '').replace(/[\u200B\uFEFF]/g, '').trim();
      var range = sel.getRangeAt(0);
      if (range.collapsed && range.startOffset === 0 && rawText2 === '') {
        e.preventDefault();
        var prevLi = li.previousElementSibling;
        li.remove();
        if (!ul.children.length) {
          var pReplace = document.createElement('p');
          pReplace.innerHTML = '<br>';
          ul.parentNode.replaceChild(pReplace, ul);
          var rP = document.createRange();
          rP.setStart(pReplace, 0);
          rP.collapse(true);
          sel.removeAllRanges();
          sel.addRange(rP);
        } else if (prevLi) {
          var prevSpan = prevLi.querySelector('.crm-ve-todo-text') || prevLi;
          var rPrev = document.createRange();
          rPrev.selectNodeContents(prevSpan);
          rPrev.collapse(false);
          sel.removeAllRanges();
          sel.addRange(rPrev);
        }
        this._sync();
        this._history.push(this._content.innerHTML, true);
        this._updateActiveButtons();
        return true;
      }
    }

    return false;
  };

  // Convert `[] ` at the start of a paragraph into a todo list
  Editor.prototype._convertBracketsToTodo = function () {
    var sel = window.getSelection();
    if (!sel || !sel.rangeCount) return false;
    var range = sel.getRangeAt(0);
    if (!range.collapsed) return false;
    var node = range.startContainer;
    if (node.nodeType !== 3) return false;
    var text = node.textContent || '';
    var offset = range.startOffset;
    // Check if the text before cursor ends with `[] ` or `[]`
    if (offset < 2) return false;
    var before = text.substring(0, offset);
    var match = before.match(/(\[\]\s?)$/);
    if (!match) return false;
    // Remove the `[] ` text
    var removeLen = match[0].length;
    node.textContent = text.substring(0, offset - removeLen) + text.substring(offset);
    // Adjust range
    range.setStart(node, offset - removeLen);
    range.collapse(true);
    sel.removeAllRanges();
    sel.addRange(range);
    // Insert todo list
    this._execTodoList();
    return true;
  };

  // ---------------------------------------------------------------------------
  //  Table
  // ---------------------------------------------------------------------------

  function createTableControls() {
    var controls = document.createElement('div');
    controls.className = 'crm-ve-table-controls';
    controls.setAttribute('contenteditable', 'false');
    controls.setAttribute('tabindex', '-1');
    controls.innerHTML = '<button type="button" class="crm-ve-table-ctrl-btn" data-table-action="add-row" title="' + t('visual_editor.table_add_row', 'Добавить строку') + '">\u2795 ' + t('visual_editor.table_row', 'Строка') + '</button>'
      + '<button type="button" class="crm-ve-table-ctrl-btn" data-table-action="add-col" title="' + t('visual_editor.table_add_col', 'Добавить столбец') + '">\u2795 ' + t('visual_editor.table_col', 'Столбец') + '</button>'
      + '<button type="button" class="crm-ve-table-ctrl-btn" data-table-action="del-row" disabled title="' + t('visual_editor.table_del_row', 'Удалить строку') + '">\u2796 ' + t('visual_editor.table_row', 'Строка') + '</button>'
      + '<button type="button" class="crm-ve-table-ctrl-btn" data-table-action="del-col" disabled title="' + t('visual_editor.table_del_col', 'Удалить столбец') + '">\u2796 ' + t('visual_editor.table_col', 'Столбец') + '</button>';
    return controls;
  }

  function getSelectedTableCell(selection, content) {
    if (!selection || !selection.rangeCount || !content.contains(selection.anchorNode)) return null;
    var anchor = selection.anchorNode;
    if (anchor.nodeType === 3) anchor = anchor.parentElement;
    var cell = anchor.closest && anchor.closest('td, th');
    return cell && cell.contains(selection.focusNode) ? cell : null;
  }

  function updateTableControls(content, selectedCell) {
    var selectedTable = selectedCell && selectedCell.closest('.crm-ve-table');
    content.querySelectorAll('.crm-ve-table-controls').forEach(function (controls) {
      var table = controls.previousElementSibling;
      var active = table && table === selectedTable;
      var body = active && (table.tBodies[0] || table);
      var firstRow = body && body.rows[0];
      var rowButton = controls.querySelector('[data-table-action="del-row"]');
      var colButton = controls.querySelector('[data-table-action="del-col"]');
      if (rowButton) rowButton.disabled = !(body && body.rows.length > 1);
      if (colButton) colButton.disabled = !(firstRow && firstRow.cells.length > 1);
    });
  }

  Editor.prototype._execTable = function (rows, cols) {
    rows = rows || 2;
    cols = cols || 2;
    var selection = window.getSelection();
    var range = selection && selection.rangeCount ? selection.getRangeAt(0) : null;
    if (!range) return;

    var table = document.createElement('table');
    table.className = 'crm-ve-table';
    var tbody = document.createElement('tbody');

    for (var r = 0; r < rows; r++) {
      var tr = document.createElement('tr');
      for (var c = 0; c < cols; c++) {
        var td = document.createElement('td');
        td.innerHTML = '<br>';
        tr.appendChild(td);
      }
      tbody.appendChild(tr);
    }
    table.appendChild(tbody);    // Wrap in a scrollable container
    var wrapper = document.createElement('div');
    wrapper.className = 'crm-ve-table-wrap';
    wrapper.appendChild(table);

    wrapper.appendChild(createTableControls());

    var curP = range.startContainer.nodeType === 1 ? range.startContainer.closest('p') : (range.startContainer.parentNode ? range.startContainer.parentNode.closest('p') : null);
    if (curP && this._content.contains(curP)) {
      var pText = (curP.textContent || '').replace(/[\u200B\uFEFF]/g, '').trim();
      if (!pText && !curP.querySelector('img, figure')) {
        curP.parentNode.replaceChild(wrapper, curP);
      } else {
        if (curP.nextSibling) curP.parentNode.insertBefore(wrapper, curP.nextSibling);
        else curP.parentNode.appendChild(wrapper);
      }
    } else {
      range.deleteContents();
      range.insertNode(wrapper);
    }

    // Ensure there is a following paragraph after the table for seamless typing/pasting
    var nextEl = wrapper.nextElementSibling;
    if (!nextEl || nextEl.tagName !== 'P') {
      var afterP = document.createElement('p');
      afterP.innerHTML = '<br>';
      if (wrapper.nextSibling) {
        wrapper.parentNode.insertBefore(afterP, wrapper.nextSibling);
      } else {
        wrapper.parentNode.appendChild(afterP);
      }
    }

    // Place caret in first cell
    var firstTd = table.querySelector('td');
    if (firstTd) {
      var caretRange = document.createRange();
      caretRange.setStart(firstTd, 0);
      caretRange.collapse(true);
      selection.removeAllRanges();
      selection.addRange(caretRange);
    }

    this._sync();
    this._history.push(this._content.innerHTML, true);
    this._updateActiveButtons();
    this._updateEmptyState();
  };

  // Table control button handler
  Editor.prototype._handleTableControl = function (e) {
    var btn = e.target.closest('.crm-ve-table-ctrl-btn');
    if (!btn) return false;
    e.preventDefault();
    if (btn.disabled) return true;
    var action = btn.getAttribute('data-table-action');
    var wrap = btn.closest('.crm-ve-table-wrap');
    if (!wrap) return false;
    var table = wrap.querySelector('.crm-ve-table');
    if (!table) return false;
    var tbody = table.querySelector('tbody') || table;
    if (!tbody) return false;

    var sel = window.getSelection();
    var currentTd = getSelectedTableCell(sel, this._content);

    // Check if the current cell belongs specifically to THIS table
    var isCellInThisTable = currentTd && currentTd.closest('.crm-ve-table') === table;
    var currentRow = isCellInThisTable ? currentTd.closest('tr') : null;
    var colIndex = (isCellInThisTable && currentRow) ? Array.from(currentRow.children).indexOf(currentTd) : -1;

    // For delete actions: user MUST have a selected cell inside THIS table
    if (action === 'del-row' || action === 'del-col') {
      if (!isCellInThisTable) {
        notify(t('visual_editor.table_select_cell_first', 'Сначала выберите ячейку в этой таблице'), 'warning');
        return true;
      }
    }

    if (action === 'add-row') {
      this._history.push(this._content.innerHTML, true);
      var cols = tbody.children[0] ? tbody.children[0].children.length : 2;
      var newTr = document.createElement('tr');
      for (var c = 0; c < cols; c++) {
        var newTd = document.createElement('td');
        newTd.innerHTML = '<br>';
        newTr.appendChild(newTd);
      }
      if (isCellInThisTable && currentRow) {
        currentRow.parentNode.insertBefore(newTr, currentRow.nextSibling);
      } else {
        tbody.appendChild(newTr);
      }
      var targetCell = newTr.children[0];
      if (targetCell && sel) {
        var r = document.createRange();
        r.selectNodeContents(targetCell);
        r.collapse(true);
        sel.removeAllRanges();
        sel.addRange(r);
      }
    } else if (action === 'add-col') {
      this._history.push(this._content.innerHTML, true);
      var allRows = tbody.querySelectorAll('tr');
      allRows.forEach(function (tr) {
        var newTd = document.createElement('td');
        newTd.innerHTML = '<br>';
        if (isCellInThisTable && colIndex >= 0 && colIndex < tr.children.length - 1) {
          tr.insertBefore(newTd, tr.children[colIndex + 1]);
        } else {
          tr.appendChild(newTd);
        }
      });
      var focusRow = currentRow || allRows[0];
      var focusCol = isCellInThisTable && colIndex >= 0 ? colIndex + 1 : (focusRow ? focusRow.children.length - 1 : 0);
      var targetCell2 = focusRow ? focusRow.children[focusCol] : null;
      if (targetCell2 && sel) {
        var r2 = document.createRange();
        r2.selectNodeContents(targetCell2);
        r2.collapse(true);
        sel.removeAllRanges();
        sel.addRange(r2);
      }
    } else if (action === 'del-row') {
      if (tbody.children.length <= 1) {
        notify(t('visual_editor.table_cannot_del_last_row', 'Нельзя удалить последнюю строку таблицы'), 'warning');
        return true;
      }
      this._history.push(this._content.innerHTML, true);
      var targetRow = currentRow.nextElementSibling || currentRow.previousElementSibling;
      var targetColIdx = Math.max(0, colIndex);
      var targetTd = targetRow ? targetRow.children[Math.min(targetColIdx, targetRow.children.length - 1)] : null;
      currentRow.remove();
      if (targetTd && sel) {
        var rDelRow = document.createRange();
        rDelRow.selectNodeContents(targetTd);
        rDelRow.collapse(true);
        sel.removeAllRanges();
        sel.addRange(rDelRow);
      }
    } else if (action === 'del-col') {
      var allRows2 = tbody.querySelectorAll('tr');
      var firstRowCols = allRows2[0] ? allRows2[0].children.length : 0;
      if (firstRowCols <= 1) {
        notify(t('visual_editor.table_cannot_del_last_col', 'Нельзя удалить последний столбец таблицы'), 'warning');
        return true;
      }
      this._history.push(this._content.innerHTML, true);
      var targetColIdx2 = colIndex < firstRowCols - 1 ? colIndex : colIndex - 1;
      allRows2.forEach(function (tr) {
        if (tr.children[colIndex]) tr.children[colIndex].remove();
      });
      var targetRow2 = currentRow || allRows2[0];
      var targetTd2 = targetRow2 ? targetRow2.children[Math.max(0, targetColIdx2)] : null;
      if (targetTd2 && sel) {
        var rDelCol = document.createRange();
        rDelCol.selectNodeContents(targetTd2);
        rDelCol.collapse(true);
        sel.removeAllRanges();
        sel.addRange(rDelCol);
      }
    }
    this._sync();
    this._history.push(this._content.innerHTML, true);
    this._updateActiveButtons();
    return true;
  };

  // Tab navigation and exit from table
  Editor.prototype._handleTableKeydown = function (e) {
    var sel = window.getSelection();
    if (!sel || !sel.rangeCount) return false;
    var node = sel.anchorNode;
    if (node && node.nodeType === 3) node = node.parentNode;
    var td = node ? node.closest('td, th') : null;
    if (!td) return false;
    var table = td.closest('.crm-ve-table');
    if (!table || !this._content.contains(table)) return false;

    var wrap = table.closest('.crm-ve-table-wrap') || table;
    var tbody = table.querySelector('tbody') || table;
    var row = td.parentNode;
    var rows = Array.from(tbody.querySelectorAll('tr'));
    var cells = Array.from(row.children);
    var colIdx = cells.indexOf(td);
    var rowIdx = rows.indexOf(row);

    if (e.key === 'ArrowDown') {
      if (rowIdx === rows.length - 1) {
        e.preventDefault();
        var nextBlock = wrap.nextElementSibling;
        if (!nextBlock || nextBlock.tagName !== 'P') {
          nextBlock = document.createElement('p');
          nextBlock.innerHTML = '<br>';
          if (wrap.nextSibling) wrap.parentNode.insertBefore(nextBlock, wrap.nextSibling);
          else wrap.parentNode.appendChild(nextBlock);
        }
        var rNext = document.createRange();
        rNext.selectNodeContents(nextBlock);
        rNext.collapse(true);
        sel.removeAllRanges();
        sel.addRange(rNext);
        this._updateActiveButtons();
        return true;
      }
    }

    if (e.key === 'Tab') {
      e.preventDefault();
      var nextTd = null;
      if (e.shiftKey) {
        // Shift+Tab: previous cell
        if (colIdx > 0) {
          nextTd = cells[colIdx - 1];
        } else if (rowIdx > 0) {
          var prevRow = rows[rowIdx - 1];
          nextTd = prevRow.children[prevRow.children.length - 1];
        } else {
          // At first cell: exit table backwards
          var prevBlock = wrap.previousElementSibling;
          if (prevBlock) {
            var rPrev = document.createRange();
            rPrev.selectNodeContents(prevBlock);
            rPrev.collapse(false);
            sel.removeAllRanges();
            sel.addRange(rPrev);
            return true;
          }
          return false;
        }
      } else {
        // Tab: next cell
        if (colIdx < cells.length - 1) {
          nextTd = cells[colIdx + 1];
        } else if (rowIdx < rows.length - 1) {
          var nextRow = rows[rowIdx + 1];
          nextTd = nextRow.children[0];
        } else {
          // Last cell of last row: add new row!
          var colsCount = cells.length;
          var newTr = document.createElement('tr');
          for (var c = 0; c < colsCount; c++) {
            var newTdCell = document.createElement('td');
            newTdCell.innerHTML = '<br>';
            newTr.appendChild(newTdCell);
          }
          tbody.appendChild(newTr);
          nextTd = newTr.children[0];
          this._sync();
          this._history.push(this._content.innerHTML, true);
        }
      }
      if (nextTd) {
        var range = document.createRange();
        range.selectNodeContents(nextTd);
        range.collapse(false);
        sel.removeAllRanges();
        sel.addRange(range);
      }
      return true;
    }

    return false;
  };

  // ---------------------------------------------------------------------------
  //  Slash menu (/)
  // ---------------------------------------------------------------------------

  function getSlashCommands() {
    return [
      {
        id: 'paragraph',
        label: t('visual_editor.slash_paragraph', 'Обычный текст'),
        icon: 'P',
        desc: t('visual_editor.slash_paragraph_desc', 'Простой текст абзаца'),
        keywords: 'paragraph text body p normal обычный текст абзац параграф',
        action: 'formatBlock',
        value: 'p',
        group: t('visual_editor.slash_group_blocks', 'Блоки')
      },
      {
        id: 'clear_format',
        label: t('visual_editor.slash_clear_format', 'Очистить форматирование'),
        icon: 'Tx',
        desc: t('visual_editor.slash_clear_format_desc', 'Сбросить оформление до обычного текста'),
        keywords: 'clear format remove plain normal сброс очистить форматирование отмена',
        action: 'removeFormat',
        group: t('visual_editor.slash_group_formatting', 'Форматирование')
      },
      {
        id: 'h2',
        label: t('visual_editor.slash_h2', 'Заголовок 2'),
        icon: 'H2',
        desc: t('visual_editor.slash_h2_desc', 'Основной раздел'),
        keywords: 'heading h2 title заголовок h2 заг раздел',
        action: 'formatBlock',
        value: 'h2',
        group: t('visual_editor.slash_group_blocks', 'Блоки')
      },
      {
        id: 'h3',
        label: t('visual_editor.slash_h3', 'Заголовок 3'),
        icon: 'H3',
        desc: t('visual_editor.slash_h3_desc', 'Подраздел'),
        keywords: 'heading h3 subtitle подзаголовок h3 заг подраздел',
        action: 'formatBlock',
        value: 'h3',
        group: t('visual_editor.slash_group_blocks', 'Блоки')
      },
      {
        id: 'bullet',
        label: t('visual_editor.slash_bullet', 'Маркированный список'),
        icon: '\u2022',
        desc: t('visual_editor.slash_bullet_desc', 'Список с маркерами'),
        keywords: 'list bullet unordered список маркер маркированный спис ul',
        action: 'insertUnorderedList',
        group: t('visual_editor.slash_group_lists', 'Списки')
      },
      {
        id: 'ordered',
        label: t('visual_editor.slash_ordered', 'Нумерованный список'),
        icon: '1.',
        desc: t('visual_editor.slash_ordered_desc', 'Список с номерами'),
        keywords: 'list number ordered нумерованный список числа цифры ol спис',
        action: 'insertOrderedList',
        group: t('visual_editor.slash_group_lists', 'Списки')
      },
      {
        id: 'todo',
        label: t('visual_editor.slash_todo', 'Чек-лист'),
        icon: '\u2611',
        desc: t('visual_editor.slash_todo_desc', 'Список задач с чекбоксами'),
        keywords: 'todo checkbox task check список задач чеклист чек-лист чек',
        action: 'todoList',
        group: t('visual_editor.slash_group_lists', 'Списки')
      },
      {
        id: 'quote',
        label: t('visual_editor.slash_quote', 'Цитата'),
        icon: '\u201C',
        desc: t('visual_editor.slash_quote_desc', 'Блок цитаты'),
        keywords: 'quote blockquote цитата цит',
        action: 'quote',
        group: t('visual_editor.slash_group_blocks', 'Блоки')
      },
      {
        id: 'code',
        label: t('visual_editor.slash_code', 'Блок кода'),
        icon: '</>',
        desc: t('visual_editor.slash_code_desc', 'Код с подсветкой'),
        keywords: 'code pre block код блок кода',
        action: 'codeBlock',
        group: t('visual_editor.slash_group_blocks', 'Блоки')
      },
      {
        id: 'table',
        label: t('visual_editor.slash_table', 'Таблица'),
        icon: '\u25A6',
        desc: t('visual_editor.slash_table_desc', 'Сетка 3\u00D73'),
        keywords: 'table grid таблица таб',
        action: 'insertTable',
        group: t('visual_editor.slash_group_blocks', 'Блоки')
      },
      {
        id: 'divider',
        label: t('visual_editor.slash_divider', 'Разделитель'),
        icon: '\u2014',
        desc: t('visual_editor.slash_divider_desc', 'Горизонтальная черта'),
        keywords: 'divider horizontal rule line separator разделитель линия черта hr',
        action: 'divider',
        group: t('visual_editor.slash_group_blocks', 'Блоки')
      }
    ];
  }

  function createSlashMenuEl() {
    var menu = document.createElement('div');
    menu.className = 'crm-ve-slash-menu';
    return menu;
  }

  function renderSlashMenuItems(menu, filter, activeIndex) {
    var query = String(filter || '').toLowerCase().trim();
    var allCommands = getSlashCommands();
    var items = allCommands.filter(function (cmd) {
      if (!query) return true;
      return cmd.label.toLowerCase().indexOf(query) !== -1
        || cmd.keywords.toLowerCase().indexOf(query) !== -1
        || (cmd.desc && cmd.desc.toLowerCase().indexOf(query) !== -1);
    });
    menu.innerHTML = '';
    if (!items.length) {
      menu.style.display = 'none';
      return items;
    }
    // Group items
    var lastGroup = '';
    items.forEach(function (cmd, i) {
      if (cmd.group && cmd.group !== lastGroup && !query) {
        lastGroup = cmd.group;
        var groupLabel = document.createElement('div');
        groupLabel.className = 'crm-ve-slash-group-label';
        groupLabel.textContent = cmd.group;
        menu.appendChild(groupLabel);
      }
      var item = document.createElement('div');
      item.className = 'crm-ve-slash-item';
      item.setAttribute('data-slash-cmd', cmd.id);
      if (i === activeIndex) item.classList.add('is-active');
      var iconSpan = document.createElement('span');
      iconSpan.className = 'crm-ve-slash-icon';
      iconSpan.textContent = cmd.icon;
      var textWrap = document.createElement('span');
      textWrap.style.cssText = 'flex:1;min-width:0;';
      var labelSpan = document.createElement('span');
      labelSpan.textContent = cmd.label;
      labelSpan.style.cssText = 'display:block;font-weight:500;';
      textWrap.appendChild(labelSpan);
      if (cmd.desc) {
        var descSpan = document.createElement('span');
        descSpan.className = 'crm-ve-slash-item-desc';
        descSpan.textContent = cmd.desc;
        textWrap.appendChild(descSpan);
      }
      item.appendChild(iconSpan);
      item.appendChild(textWrap);
      menu.appendChild(item);
    });
    var activeItem = menu.querySelector('.crm-ve-slash-item.is-active');
    if (activeItem) {
      scrollIntoMenu(menu, activeItem);
    }
    return items;
  }

  function positionSlashMenu(editorInstance) {
    var menu = editorInstance._slashMenu;
    if (!menu) return;
    var wrapper = editorInstance._wrapper;
    var wrapperRect = wrapper.getBoundingClientRect();

    var sel = window.getSelection();
    var rect = null;
    if (sel && sel.rangeCount && editorInstance._content && editorInstance._content.contains(sel.anchorNode)) {
      var range = sel.getRangeAt(0);
      rect = range.getBoundingClientRect();
    }
    if (!rect || (rect.width === 0 && rect.height === 0 && rect.top === 0 && rect.bottom === 0)) {
      var focused = editorInstance._content ? (editorInstance._content.querySelector('.is-focused') || editorInstance._content.lastElementChild || editorInstance._content) : wrapper;
      rect = focused ? focused.getBoundingClientRect() : wrapperRect;
    }

    var bounds = getVisibleViewportBounds(wrapper);
    var spaceBelow = bounds.bottom - rect.bottom;
    var spaceAbove = rect.top - bounds.top;

    var availableHeight = Math.max(80, bounds.bottom - bounds.top);
    var maxH = Math.min(260, availableHeight - 16);
    menu.style.maxHeight = maxH + 'px';
    menu.style.overflowY = 'auto';

    var menuHeight = menu.offsetHeight || maxH;
    var shouldFlipAbove = spaceBelow < menuHeight + 12 && spaceAbove > spaceBelow;

    var targetViewportTop;
    if (shouldFlipAbove) {
      targetViewportTop = rect.top - menuHeight - 4;
      menu.classList.add('is-above');
    } else {
      targetViewportTop = rect.bottom + 4;
      menu.classList.remove('is-above');
    }

    if (targetViewportTop + menuHeight > bounds.bottom - 4) {
      targetViewportTop = bounds.bottom - menuHeight - 4;
    }
    if (targetViewportTop < bounds.top + 4) {
      targetViewportTop = bounds.top + 4;
    }

    var top = targetViewportTop - wrapperRect.top;
    var left = rect.left - wrapperRect.left;
    left = Math.max(4, Math.min(left, wrapperRect.width - 240));

    menu.style.top = top + 'px';
    menu.style.left = left + 'px';
  }

  Editor.prototype._showSlashMenu = function () {
    if (!this._slashMenu) {
      this._slashMenu = createSlashMenuEl();
      this._wrapper.appendChild(this._slashMenu);
    }
    this._slashMenuActive = true;
    this._slashMenuFilter = '';
    this._slashMenuIndex = 0;
    var items = renderSlashMenuItems(this._slashMenu, '', 0);
    this._slashMenu.style.display = items.length ? 'block' : 'none';
    this._slashMenuItems = items;
    positionSlashMenu(this);
  };

  Editor.prototype._hideSlashMenu = function () {
    if (this._slashMenu) this._slashMenu.style.display = 'none';
    this._slashMenuActive = false;
    this._slashMenuFilter = '';
    this._slashMenuIndex = 0;
    this._slashMenuItems = [];
  };

  Editor.prototype._selectSlashItem = function (cmdId) {
    var allCommands = getSlashCommands();
    var cmd = allCommands.find(function (c) { return c.id === cmdId; });
    if (!cmd) return;
    // Remove the slash and filter text from the current line
    var sel = window.getSelection();
    if (sel && sel.rangeCount) {
      var range = sel.getRangeAt(0);
      var node = range.startContainer;
      if (node.nodeType === 3) {
        var text = node.textContent || '';
        var offset = range.startOffset;
        var before = text.substring(0, offset);
        var slashIdx = before.lastIndexOf('/');
        if (slashIdx >= 0) {
          node.textContent = text.substring(0, slashIdx) + text.substring(offset);
          range.setStart(node, slashIdx);
          range.collapse(true);
          sel.removeAllRanges();
          sel.addRange(range);
        }
      }
    }
    this._hideSlashMenu();
    // Execute the command
    if (cmd.action === 'divider') {
      var hr = document.createElement('hr');
      hr.style.cssText = 'border:none;border-top:1px solid #e5e7eb;margin:12px 0;';
      this._insertAtCaret(hr);
    } else if (cmd.action === 'todoList') {
      this._execTodoList();
    } else if (cmd.action === 'quote') {
      this._execQuote();
    } else if (cmd.action === 'insertTable') {
      this._execTable(3, 3);
    } else if (cmd.action === 'codeBlock') {
      this._execCodeBlock();
    } else if (cmd.action === 'removeFormat') {
      this._execRemoveFormat();
    } else if (cmd.action === 'formatBlock') {
      var curBlock = node ? (node.nodeType === 1 ? node : node.parentNode).closest('p, h1, h2, h3, div') : null;
      if (curBlock && curBlock.parentNode && curBlock !== this._content) {
        var newHeading = document.createElement(cmd.value);
        newHeading.innerHTML = '<br>';
        curBlock.parentNode.replaceChild(newHeading, curBlock);
        var rH = document.createRange();
        rH.setStart(newHeading, 0);
        rH.collapse(true);
        sel.removeAllRanges();
        sel.addRange(rH);
      } else {
        document.execCommand('formatBlock', false, '<' + cmd.value + '>');
      }
    } else {
      document.execCommand(cmd.action, false, cmd.value || null);
    }
    this._sync();
    this._history.push(this._content.innerHTML, true);
  };

  Editor.prototype._handleSlashInput = function (typedChar) {
    if (!this._slashMenuActive) {
      if (typedChar === '/') {
        // Only activate at start of line or after whitespace
        var sel = window.getSelection();
        if (sel && sel.rangeCount) {
          var range = sel.getRangeAt(0);
          var node = range.startContainer;
          if (node.nodeType === 3) {
            var text = node.textContent || '';
            var offset = range.startOffset;
            var before = text.substring(0, Math.max(0, offset - 1));
            if (before === '' || /\s$/.test(before)) {
              this._showSlashMenu();
            }
          } else if (node.nodeType === 1) {
            if (range.startOffset <= 1 || !node.textContent.replace('/', '').trim()) {
              this._showSlashMenu();
            }
          }
        }
      }
      return;
    }
    // Update filter
    if (typedChar === 'Escape' || (typedChar === 'Backspace' && this._slashMenuFilter === '')) {
      this._hideSlashMenu();
      return;
    }
    if (typedChar === 'Backspace') {
      this._slashMenuFilter = this._slashMenuFilter.slice(0, -1);
    } else if (typedChar.length === 1) {
      this._slashMenuFilter += typedChar;
    }
    this._slashMenuIndex = 0;
    var items = renderSlashMenuItems(this._slashMenu, this._slashMenuFilter, 0);
    this._slashMenuItems = items;
    if (!items.length) {
      this._hideSlashMenu();
    }
  };

  Editor.prototype._handleSlashKeydown = function (e) {
    if (!this._slashMenuActive) return false;
    var items = this._slashMenuItems || [];
    if (e.key === 'Escape') {
      e.preventDefault();
      e.stopPropagation();
      this._hideSlashMenu();
      return true;
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      this._slashMenuIndex = Math.min(this._slashMenuIndex + 1, items.length - 1);
      renderSlashMenuItems(this._slashMenu, this._slashMenuFilter, this._slashMenuIndex);
      return true;
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      this._slashMenuIndex = Math.max(this._slashMenuIndex - 1, 0);
      renderSlashMenuItems(this._slashMenu, this._slashMenuFilter, this._slashMenuIndex);
      return true;
    }
    if (e.key === 'Enter') {
      e.preventDefault();
      if (items[this._slashMenuIndex]) {
        this._selectSlashItem(items[this._slashMenuIndex].id);
      }
      return true;
    }
    if (e.key === 'Tab') {
      e.preventDefault();
      if (items[this._slashMenuIndex]) {
        this._selectSlashItem(items[this._slashMenuIndex].id);
      }
      return true;
    }
    return false;
  };

  // Clear formatting / return to normal text:
  // Strips inline formatting (bold, italic, strike, colors, links, code, spoilers)
  // and resets block formatting to standard paragraph <p>.
  Editor.prototype._execRemoveFormat = function () {
    this._content.focus();
    var sel = window.getSelection();
    if (!sel || !sel.rangeCount) return;

    var range = sel.getRangeAt(0);

    // 1. Native removeFormat strips standard inline formatting (bold, italic, strike, font, etc.)
    document.execCommand('removeFormat', false, null);

    // 2. Unlink any links in the selection
    document.execCommand('unlink', false, null);

    // 3. Reset block heading / quote / code back to normal paragraph <p>
    var currentBlock = this._getCurrentBlockTag();
    if (currentBlock && currentBlock !== 'p') {
      document.execCommand('formatBlock', false, '<p>');
    }

    // 4. Convert any heading / blockquote / pre elements contained in selection
    var container = range.commonAncestorContainer;
    if (container && container.nodeType === 3) container = container.parentNode;
    if (container && this._content.contains(container)) {
      if (container.querySelectorAll) {
        var headings = container.querySelectorAll('h1, h2, h3, blockquote, pre');
        for (var h = 0; h < headings.length; h++) {
          var hEl = headings[h];
          if (sel.containsNode(hEl, true)) {
            var p = document.createElement('p');
            while (hEl.firstChild) p.appendChild(hEl.firstChild);
            if (hEl.parentNode) hEl.parentNode.replaceChild(p, hEl);
          }
        }

        // Unwrap code, mark, details.crm-ve-spoiler, and strip inline styles
        var inlines = container.querySelectorAll('code, mark, details.crm-ve-spoiler, [style]');
        for (var i = 0; i < inlines.length; i++) {
          var el = inlines[i];
          if (range.collapsed || sel.containsNode(el, true)) {
            if (el.tagName === 'CODE' || el.tagName === 'MARK') {
              var parentNode = el.parentNode;
              if (parentNode) {
                while (el.firstChild) parentNode.insertBefore(el.firstChild, el);
                parentNode.removeChild(el);
              }
            } else if (el.tagName === 'DETAILS' && el.classList.contains('crm-ve-spoiler')) {
              var summary = el.querySelector('summary');
              if (summary) el.removeChild(summary);
              var parentSpoiler = el.parentNode;
              if (parentSpoiler) {
                while (el.firstChild) parentSpoiler.insertBefore(el.firstChild, el);
                parentSpoiler.removeChild(el);
              }
            } else if (el.hasAttribute('style')) {
              el.removeAttribute('style');
              if (el.tagName === 'SPAN' && !el.attributes.length && el.parentNode) {
                var parentSpan = el.parentNode;
                while (el.firstChild) parentSpan.insertBefore(el.firstChild, el);
                parentSpan.removeChild(el);
              }
            }
          }
        }
      }
    }

    this._sync();
    this._history.push(this._content.innerHTML, true);
    this._updateActiveButtons();
  };

  // Inline code (</>): wraps the current selection in <code> or, if already inside <code>,
  // unwraps it back to normal text.
  Editor.prototype._execInlineCode = function () {
    var selection = window.getSelection();
    var range = selection && selection.rangeCount ? selection.getRangeAt(0) : null;
    if (!range) return;

    var node = range.startContainer;
    if (node && node.nodeType === 3) node = node.parentNode;
    var existingCode = node ? node.closest('code') : null;
    if (existingCode && existingCode.closest('pre')) {
      existingCode = null;
    }

    if (existingCode) {
      // Toggle off: unwrap existing code element
      var parent = existingCode.parentNode;
      var lastNode = null;
      while (existingCode.firstChild) {
        lastNode = existingCode.firstChild;
        parent.insertBefore(lastNode, existingCode);
      }
      parent.removeChild(existingCode);
      if (lastNode) {
        var afterR = document.createRange();
        afterR.setStartAfter(lastNode);
        afterR.collapse(true);
        selection.removeAllRanges();
        selection.addRange(afterR);
      }
      this._sync();
      this._history.push(this._content.innerHTML, true);
      this._updateActiveButtons();
      return;
    }

    if (range.collapsed) {
      var emptyCode = document.createElement('code');
      emptyCode.appendChild(document.createTextNode('\u200B'));
      range.insertNode(emptyCode);
      var caretRange = document.createRange();
      caretRange.setStart(emptyCode.firstChild, 0);
      caretRange.setEnd(emptyCode.firstChild, 1);
      selection.removeAllRanges();
      selection.addRange(caretRange);
      this._sync();
      this._updateActiveButtons();
      return;
    }

    // Selection spanning block-level content: fall back to a <pre> code block
    var fragment = range.cloneContents();
    if (fragment.querySelector('p, div, h1, h2, h3, h4, li, pre, blockquote, ul, ol')) {
      document.execCommand('formatBlock', false, '<pre>');
      this._sync();
      this._history.push(this._content.innerHTML, true);
      this._updateActiveButtons();
      return;
    }

    // Inline selection: wrap the selected text in <code>, preserving content.
    var code = document.createElement('code');
    code.appendChild(range.extractContents());
    range.insertNode(code);
    var afterRange = document.createRange();
    afterRange.selectNodeContents(code);
    selection.removeAllRanges();
    selection.addRange(afterRange);
    this._sync();
    this._history.push(this._content.innerHTML, true);
    this._updateActiveButtons();
  };

  // ---------------------------------------------------------------------------
  //  @Mentions
  // ---------------------------------------------------------------------------

  var _mentionUsersCache = null;
  var _mentionUsersPromise = null;
  var _mentionPagesCache = null;
  var _mentionPagesPromise = null;

  function fetchMentionUsers() {
    if (_mentionUsersCache) return Promise.resolve(_mentionUsersCache);
    if (_mentionUsersPromise) return _mentionUsersPromise;
    var url = window.CRM && window.CRM.api && window.CRM.api.buildUrl
      ? window.CRM.api.buildUrl('api/v1/users', { limit: 200 })
      : '/api/index.php?route=api/v1/users&limit=200';
    _mentionUsersPromise = fetch(url, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var items = (data && data.data && data.data.items) || data.items || [];
      _mentionUsersCache = items.map(function (u) {
        return {
          id: u.public_id || u.id || '',
          name: u.full_name || u.login || '',
          login: u.login || '',
          type: 'user'
        };
      }).filter(function (u) { return u.id && u.name; });
      return _mentionUsersCache;
    })
    .catch(function () {
      _mentionUsersCache = [];
      return _mentionUsersCache;
    });
    return _mentionUsersPromise;
  }

  function fetchMentionPages() {
    if (_mentionPagesCache) return Promise.resolve(_mentionPagesCache);
    if (_mentionPagesPromise) return _mentionPagesPromise;
    var url = window.CRM && window.CRM.api && window.CRM.api.buildUrl
      ? window.CRM.api.buildUrl('api/v1/knowledge/pages', { limit: 100 })
      : '/api/index.php?route=api/v1/knowledge/pages&limit=100';
    _mentionPagesPromise = fetch(url, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var items = (data && data.data && data.data.items) || data.items || [];
      _mentionPagesCache = items.map(function (p) {
        return {
          id: p.public_id || p.id || '',
          name: p.title || '',
          type: 'page'
        };
      }).filter(function (p) { return p.id && p.name; });
      return _mentionPagesCache;
    })
    .catch(function () {
      _mentionPagesCache = [];
      return _mentionPagesCache;
    });
    return _mentionPagesPromise;
  }

  function fetchMentionAll() {
    return Promise.all([fetchMentionUsers(), fetchMentionPages()]).then(function (results) {
      return results[0].concat(results[1]);
    });
  }

  function createMentionMenuEl() {
    var menu = document.createElement('div');
    menu.className = 'crm-ve-mention-menu';
    return menu;
  }

  function renderMentionMenuItems(menu, allItems, filter, activeIndex) {
    var query = String(filter || '').toLowerCase().trim();
    var filtered = allItems.filter(function (u) {
      if (!query) return true;
      return u.name.toLowerCase().indexOf(query) !== -1
        || (u.login && u.login.toLowerCase().indexOf(query) !== -1);
    }).slice(0, 12);
    menu.innerHTML = '';
    if (!filtered.length) {
      menu.style.display = 'none';
      return filtered;
    }
    // Group by type
    var users = filtered.filter(function (i) { return i.type === 'user'; });
    var pages = filtered.filter(function (i) { return i.type === 'page'; });
    var idx = 0;

    function renderItem(item) {
      var el = document.createElement('div');
      el.className = 'crm-ve-mention-item';
      el.setAttribute('data-mention-id', item.id);
      el.setAttribute('data-mention-name', item.name);
      el.setAttribute('data-mention-type', item.type);
      if (idx === activeIndex) el.classList.add('is-active');
      var icon = document.createElement('span');
      icon.className = item.type === 'page' ? 'crm-ve-mention-page-icon' : 'crm-ve-mention-user-icon';
      icon.textContent = item.type === 'page' ? '\uD83D\uDCC4' : (item.name || '?').charAt(0).toUpperCase();
      var nameSpan = document.createElement('span');
      nameSpan.style.cssText = 'flex:1;min-width:0;';
      nameSpan.textContent = item.name;
      if (item.login) {
        var loginSpan = document.createElement('span');
        loginSpan.style.cssText = 'color:#9ca3af;font-size:12px;margin-left:4px;';
        loginSpan.textContent = '@' + item.login;
        nameSpan.appendChild(loginSpan);
      }
      var typeSpan = document.createElement('span');
      typeSpan.className = 'crm-ve-mention-item-type';
      typeSpan.textContent = item.type === 'page' ? t('visual_editor.mention_type_page', 'Page') : t('visual_editor.mention_type_user', 'User');
      el.appendChild(icon);
      el.appendChild(nameSpan);
      el.appendChild(typeSpan);
      menu.appendChild(el);
      idx++;
    }

    if (users.length) {
      var userLabel = document.createElement('div');
      userLabel.className = 'crm-ve-mention-group-label';
      userLabel.textContent = t('visual_editor.mention_group_users', 'Users');
      menu.appendChild(userLabel);
      users.forEach(renderItem);
    }
    if (pages.length) {
      var pageLabel = document.createElement('div');
      pageLabel.className = 'crm-ve-mention-group-label';
      pageLabel.textContent = t('visual_editor.mention_group_pages', 'Knowledge pages');
      menu.appendChild(pageLabel);
      pages.forEach(renderItem);
    }
    var activeMention = menu.querySelector('.crm-ve-mention-item.is-active');
    if (activeMention) {
      scrollIntoMenu(menu, activeMention);
    }
    return filtered;
  }

  function positionMentionMenu(editorInstance) {
    var menu = editorInstance._mentionMenu;
    if (!menu) return;
    var wrapper = editorInstance._wrapper;
    var wrapperRect = wrapper.getBoundingClientRect();

    var sel = window.getSelection();
    var rect = null;
    if (sel && sel.rangeCount && editorInstance._content && editorInstance._content.contains(sel.anchorNode)) {
      var range = sel.getRangeAt(0);
      rect = range.getBoundingClientRect();
    }
    if (!rect || (rect.width === 0 && rect.height === 0 && rect.top === 0 && rect.bottom === 0)) {
      var focused = editorInstance._content ? (editorInstance._content.querySelector('.is-focused') || editorInstance._content.lastElementChild || editorInstance._content) : wrapper;
      rect = focused ? focused.getBoundingClientRect() : wrapperRect;
    }

    var bounds = getVisibleViewportBounds(wrapper);
    var spaceBelow = bounds.bottom - rect.bottom;
    var spaceAbove = rect.top - bounds.top;

    var availableHeight = Math.max(80, bounds.bottom - bounds.top);
    var maxH = Math.min(240, availableHeight - 16);
    menu.style.maxHeight = maxH + 'px';
    menu.style.overflowY = 'auto';

    var menuHeight = menu.offsetHeight || maxH;
    var shouldFlipAbove = spaceBelow < menuHeight + 12 && spaceAbove > spaceBelow;

    var targetViewportTop;
    if (shouldFlipAbove) {
      targetViewportTop = rect.top - menuHeight - 4;
      menu.classList.add('is-above');
    } else {
      targetViewportTop = rect.bottom + 4;
      menu.classList.remove('is-above');
    }

    if (targetViewportTop + menuHeight > bounds.bottom - 4) {
      targetViewportTop = bounds.bottom - menuHeight - 4;
    }
    if (targetViewportTop < bounds.top + 4) {
      targetViewportTop = bounds.top + 4;
    }

    var top = targetViewportTop - wrapperRect.top;
    var left = rect.left - wrapperRect.left;
    left = Math.max(4, Math.min(left, wrapperRect.width - 220));

    menu.style.top = top + 'px';
    menu.style.left = left + 'px';
  }

  Editor.prototype._showMentionMenu = function () {
    var self = this;
    if (!this._mentionMenu) {
      this._mentionMenu = createMentionMenuEl();
      this._wrapper.appendChild(this._mentionMenu);
    }
    this._mentionMenuActive = true;
    this._mentionMenuFilter = '';
    this._mentionMenuIndex = 0;
    // Fetch users and pages, show menu
    fetchMentionAll().then(function (allItems) {
      if (!self._mentionMenuActive) return;
      self._mentionUsers = allItems;
      var items = renderMentionMenuItems(self._mentionMenu, allItems, '', 0);
      self._mentionMenu.style.display = items.length ? 'block' : 'none';
      self._mentionMenuItems = items;
      positionMentionMenu(self);
    });
  };

  Editor.prototype._hideMentionMenu = function () {
    if (this._mentionMenu) this._mentionMenu.style.display = 'none';
    this._mentionMenuActive = false;
    this._mentionMenuFilter = '';
    this._mentionMenuIndex = 0;
    this._mentionMenuItems = [];
  };

  Editor.prototype._selectMentionItem = function (itemId, itemName, itemType) {
    // Remove the @ and filter text
    var sel = window.getSelection();
    if (sel && sel.rangeCount) {
      var range = sel.getRangeAt(0);
      var node = range.startContainer;
      if (node.nodeType === 3) {
        var text = node.textContent || '';
        var offset = range.startOffset;
        var before = text.substring(0, offset);
        var atIdx = before.lastIndexOf('@');
        if (atIdx >= 0) {
          node.textContent = text.substring(0, atIdx) + text.substring(offset);
          range.setStart(node, atIdx);
          range.collapse(true);
          sel.removeAllRanges();
          sel.addRange(range);
        }
      }
    }
    this._hideMentionMenu();
    // Insert mention chip
    var mention = document.createElement('span');
    mention.className = itemType === 'page' ? 'crm-ve-mention crm-ve-mention-page' : 'crm-ve-mention';
    mention.setAttribute('data-mention-type', itemType || 'user');
    mention.setAttribute('data-mention-id', itemId);
    mention.setAttribute('contenteditable', 'false');
    mention.textContent = (itemType === 'page' ? '\uD83D\uDCC4 ' : '@') + itemName;
    this._insertAtCaret(mention);
    // Add a space after the mention
    var space = document.createTextNode('\u00A0');
    if (mention.nextSibling) {
      mention.parentNode.insertBefore(space, mention.nextSibling);
    } else {
      mention.parentNode.appendChild(space);
    }
    // Move caret after the space
    var afterRange = document.createRange();
    afterRange.setStart(space, 1);
    afterRange.collapse(true);
    sel.removeAllRanges();
    sel.addRange(afterRange);
    this._content.focus();
    this._sync();
    this._history.push(this._content.innerHTML, true);
  };

  Editor.prototype._handleMentionInput = function (typedChar) {
    if (!this._mentionMenuActive) {
      if (typedChar === '@') {
        var sel = window.getSelection();
        if (sel && sel.rangeCount) {
          var range = sel.getRangeAt(0);
          var node = range.startContainer;
          if (node.nodeType === 3) {
            var text = node.textContent || '';
            var offset = range.startOffset;
            var before = text.substring(0, Math.max(0, offset - 1));
            if (before === '' || /\s$/.test(before)) {
              this._showMentionMenu();
            }
          } else if (node.nodeType === 1) {
            if (range.startOffset <= 1 || !node.textContent.replace('@', '').trim()) {
              this._showMentionMenu();
            }
          }
        }
      }
      return;
    }
    if (typedChar === 'Escape' || (typedChar === 'Backspace' && this._mentionMenuFilter === '')) {
      this._hideMentionMenu();
      return;
    }
    if (typedChar === 'Backspace') {
      this._mentionMenuFilter = this._mentionMenuFilter.slice(0, -1);
    } else if (typedChar.length === 1 && typedChar !== '@') {
      this._mentionMenuFilter += typedChar;
    }
    this._mentionMenuIndex = 0;
    var items = renderMentionMenuItems(this._mentionMenu, this._mentionUsers || [], this._mentionMenuFilter, 0);
    this._mentionMenuItems = items;
    if (!items.length) {
      this._hideMentionMenu();
    }
  };

  Editor.prototype._handleMentionKeydown = function (e) {
    if (!this._mentionMenuActive) return false;
    var items = this._mentionMenuItems || [];
    if (e.key === 'Escape') {
      e.preventDefault();
      e.stopPropagation();
      this._hideMentionMenu();
      return true;
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      this._mentionMenuIndex = Math.min(this._mentionMenuIndex + 1, items.length - 1);
      renderMentionMenuItems(this._mentionMenu, this._mentionUsers || [], this._mentionMenuFilter, this._mentionMenuIndex);
      return true;
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      this._mentionMenuIndex = Math.max(this._mentionMenuIndex - 1, 0);
      renderMentionMenuItems(this._mentionMenu, this._mentionUsers || [], this._mentionMenuFilter, this._mentionMenuIndex);
      return true;
    }
    if (e.key === 'Enter' || e.key === 'Tab') {
      e.preventDefault();
      if (items[this._mentionMenuIndex]) {
        var item = items[this._mentionMenuIndex];
        this._selectMentionItem(item.id, item.name, item.type);
      }
      return true;
    }
    return false;
  };

  Editor.prototype._bindEvents = function () {
    var self = this;
    var content = this._content;

    content.addEventListener('input', function (e) {
      // Auto-convert `[] ` at start of line into a todo list
      self._convertBracketsToTodo();
      // Slash menu: track typed characters
      var inputType = e.inputType || '';
      var char = '';
      if (inputType === 'insertText' && e.data) {
        char = e.data;
      } else if (inputType === 'deleteContentBackward') {
        char = 'Backspace';
      }
      if (char) {
        self._handleSlashInput(char);
        self._handleMentionInput(char);
      }
      updateTodoAriaLabels(self._content);
      self._sync();
      if (!self._pasting) self._history.push(content.innerHTML, false);
      self._updateActiveButtons();
      self._updateEmptyState();
    });

    content.addEventListener('keyup', function () {
      updateTodoAriaLabels(self._content);
    });

    var parentForm = this._textarea.closest('form');
    this._parentForm = parentForm;

    var formSubmitGuard = function (e) {
      if (self._blockSaveForUploads()) {
        e.preventDefault();
        e.stopImmediatePropagation();
        return;
      }
      self._sync();
    };

    if (parentForm) {
      parentForm.addEventListener('submit', formSubmitGuard, true);
      self._formSubmitGuard = formSubmitGuard;
    }
    // Global capture-phase Escape listener: stops event immediately so parent
    // Bootstrap modal or page listeners never close host modals when dismissing popup menus!
    var escapeCaptureHandler = function (e) {
      if (e.key === 'Escape' && (self._slashMenuActive || self._mentionMenuActive || self._moreMenuActive)) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        if (self._slashMenuActive) self._hideSlashMenu();
        if (self._mentionMenuActive) self._hideMentionMenu();
        if (self._moreMenuActive) self._hideMoreMenu();
      }
    };
    window.addEventListener('keydown', escapeCaptureHandler, true);
    self._escapeCaptureHandler = escapeCaptureHandler;

    var outsideClickHandler = function (e) {
      if (self._moreMenuActive) {
        if (!e.target.closest('.crm-ve-more-menu') && !e.target.closest('.crm-ve-more-btn')) {
          self._hideMoreMenu();
        }
      }
    };
    document.addEventListener('click', outsideClickHandler);
    self._outsideClickHandler = outsideClickHandler;

    content.addEventListener('blur', function () {
      self._wrapper.classList.remove('is-focused');
      self._hideSlashMenu();
      self._hideMentionMenu();
      self._sync();
    });

    content.addEventListener('focusout', function (e) {
      if (!content.contains(e.relatedTarget)) updateTableControls(content, null);
    });

    content.addEventListener('focus', function () {
      self.refreshFromTextarea(false);
      self._wrapper.classList.add('is-focused');
      self._updateActiveButtons();
    });

    content.addEventListener('keydown', function (e) {
      self._onKeyDown(e);
    });

    content.addEventListener('mousedown', function (e) {
      if (e.button === 0 && e.target.closest('.crm-ve-table-ctrl-btn')) e.preventDefault();
    });

    content.addEventListener('click', function (e) {
      // Mention menu item click
      var mentionItem = e.target.closest('.crm-ve-mention-item');
      if (mentionItem) {
        e.preventDefault();
        var userId = mentionItem.getAttribute('data-mention-id');
        var userName = mentionItem.getAttribute('data-mention-name');
        var userType = mentionItem.getAttribute('data-mention-type');
        if (userId && userName) self._selectMentionItem(userId, userName, userType);
        return;
      }
      // Slash menu item click
      var slashItem = e.target.closest('.crm-ve-slash-item');
      if (slashItem) {
        e.preventDefault();
        var cmdId = slashItem.getAttribute('data-slash-cmd');
        if (cmdId) self._selectSlashItem(cmdId);
        return;
      }
      // Close menus on click elsewhere
      if (self._slashMenuActive) {
        self._hideSlashMenu();
      }
      if (self._mentionMenuActive) {
        self._hideMentionMenu();
      }
      // Todo checkbox toggle
      var todoCb = e.target.closest('.crm-ve-todo-cb');
      if (todoCb) {
        e.preventDefault();
        self._toggleTodo(todoCb);
        return;
      }
      // Table control buttons
      if (self._handleTableControl(e)) return;
      var imageBlock = e.target.closest('.crm-ve-image-block');
      if (imageBlock) {
        var caption = e.target.closest('.crm-ve-image-caption');
        if (caption) {
          // Allow caption editing directly
          return;
        }
        if (self._options.readonly) {
          var img = imageBlock.querySelector('.crm-ve-image');
          var showViewer = (window.CRM && (window.CRM.showImageViewer || window.CRM.showImageLightbox));
          if (img && img.src && typeof showViewer === 'function') {
            var capEl = imageBlock.querySelector('.crm-ve-image-caption');
            var capText = (capEl && capEl.textContent) ? capEl.textContent.trim() : (img.alt || '');
            showViewer(img.src, capText);
            e.preventDefault();
            return;
          }
        }
        selectImageBlock(self, imageBlock);
        e.preventDefault();
        return;
      }
      deselectImage(self);
      self._updateActiveButtons();

      if (e.target === content) {
        var lastChild = content.lastElementChild;
        if (lastChild) {
          var rect = lastChild.getBoundingClientRect();
          if (e.clientY > rect.bottom) {
            var targetP = null;
            if (lastChild.tagName === 'P') {
              var pText = (lastChild.textContent || '').replace(/[\u200B\uFEFF]/g, '').trim();
              if (pText === '') targetP = lastChild;
            }
            if (!targetP) {
              targetP = document.createElement('p');
              targetP.innerHTML = '<br>';
              content.appendChild(targetP);
            }
            var range = document.createRange();
            var sel = window.getSelection();
            if (sel) {
              range.setStart(targetP, 0);
              range.collapse(true);
              sel.removeAllRanges();
              sel.addRange(range);
            }
          }
        }
      }
    });

    content.addEventListener('paste', function (e) {
      // execCommand sends a synchronous input event before paste cleanup.
      self._pasting = true;
      try {
        self._onPaste(e);
      } finally {
        self._pasting = false;
      }
    });

    content.addEventListener('drop', function (e) {
      self._onDrop(e);
    });

    content.addEventListener('dblclick', function (e) {
      var imageBlock = e.target.closest('.crm-ve-image-block');
      if (imageBlock) {
        var caption = e.target.closest('.crm-ve-image-caption');
        if (caption) return;
        var img = imageBlock.querySelector('.crm-ve-image');
        var showViewer = (window.CRM && (window.CRM.showImageViewer || window.CRM.showImageLightbox));
        if (img && img.src && typeof showViewer === 'function') {
          var capEl = imageBlock.querySelector('.crm-ve-image-caption');
          var capText = (capEl && capEl.textContent) ? capEl.textContent.trim() : (img.alt || '');
          showViewer(img.src, capText);
          e.preventDefault();
        }
      }
    });

    content.addEventListener('dragover', function (e) {
      e.preventDefault();
      content.classList.add('crm-ve-dragover');
      self._wrapper.classList.add('is-dragover');
    });

    content.addEventListener('dragleave', function () {
      content.classList.remove('crm-ve-dragover');
      self._wrapper.classList.remove('is-dragover');
    });

    content.addEventListener('dragend', function () {
      content.classList.remove('crm-ve-dragover');
      self._wrapper.classList.remove('is-dragover');
    });

    window.addEventListener('scroll', function () {
      updateImageToolbarPosition(self);
    }, true);

    window.addEventListener('resize', function () {
      updateImageToolbarPosition(self);
    });

    document.addEventListener('selectionchange', function () {
      if (self._destroyed) return;
      updateImageToolbarPosition(self);
      self._updateActiveButtons();
    });
  };

  Editor.prototype._handleBlockquoteKeydown = function (e) {
    var sel = window.getSelection();
    if (!sel || !sel.rangeCount) return false;
    var node = sel.anchorNode;
    if (node && node.nodeType === 3) node = node.parentNode;
    var bq = node ? node.closest('blockquote') : null;
    if (!bq || !this._content.contains(bq)) return false;

    if (e.key === 'Enter' && !e.shiftKey) {
      var curBlock = node;
      while (curBlock && curBlock.parentNode !== bq && curBlock !== bq) {
        curBlock = curBlock.parentNode;
      }
      var blockText = (curBlock.textContent || '').replace(/[\u200B\uFEFF]/g, '').trim();
      var isLastInBq = curBlock === bq.lastElementChild || curBlock === bq;

      if (blockText === '' && isLastInBq) {
        e.preventDefault();
        if (curBlock !== bq) curBlock.remove();
        var newP = document.createElement('p');
        newP.innerHTML = '<br>';
        if (bq.nextSibling) {
          bq.parentNode.insertBefore(newP, bq.nextSibling);
        } else {
          bq.parentNode.appendChild(newP);
        }
        var bqRemaining = (bq.textContent || '').replace(/[\u200B\uFEFF]/g, '').trim();
        if (!bqRemaining && !bq.querySelector('img')) bq.remove();
        var range = document.createRange();
        range.setStart(newP, 0);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
        this._sync();
        this._history.push(this._content.innerHTML, true);
        this._updateActiveButtons();
        return true;
      }

      e.preventDefault();
      var range2 = sel.getRangeAt(0);
      range2.deleteContents();
      var targetP = document.createElement('p');
      targetP.innerHTML = '<br>';
      if (curBlock && curBlock.parentNode === bq) {
        curBlock.parentNode.insertBefore(targetP, curBlock.nextSibling);
      } else {
        bq.appendChild(targetP);
      }
      var r = document.createRange();
      r.setStart(targetP, 0);
      r.collapse(true);
      sel.removeAllRanges();
      sel.addRange(r);
      this._sync();
      this._history.push(this._content.innerHTML, true);
      this._updateActiveButtons();
      return true;
    }

    if (e.key === 'ArrowDown') {
      var lastElem = bq.lastElementChild || bq;
      var curB = node;
      while (curB && curB.parentNode !== bq && curB !== bq) curB = curB.parentNode;
      if (curB === lastElem) {
        var rEnd = sel.getRangeAt(0);
        var remainingAfter = (curB.textContent || '').substring(rEnd.endOffset).trim();
        if (!remainingAfter || rEnd.endOffset >= (curB.textContent || '').length) {
          e.preventDefault();
          var nextBlock = bq.nextElementSibling;
          if (!nextBlock) {
            nextBlock = document.createElement('p');
            nextBlock.innerHTML = '<br>';
            bq.parentNode.appendChild(nextBlock);
          }
          var rNext = document.createRange();
          rNext.selectNodeContents(nextBlock);
          rNext.collapse(true);
          sel.removeAllRanges();
          sel.addRange(rNext);
          this._updateActiveButtons();
          return true;
        }
      }
    }

    return false;
  };

  Editor.prototype._handleCodeBlockKeydown = function (e) {
    var sel = window.getSelection();
    if (!sel || !sel.rangeCount) return false;
    var node = sel.anchorNode;
    if (node && node.nodeType === 3) node = node.parentNode;
    var pre = node ? node.closest('pre') : null;
    if (!pre || !this._content.contains(pre)) return false;

    if (e.key === 'Enter' && !e.shiftKey) {
      var code = pre.querySelector('code') || pre;
      var inner = (code.innerText || code.textContent || '').replace(/\r/g, '');
      var range = sel.getRangeAt(0);

      var testRange = document.createRange();
      testRange.selectNodeContents(code);
      testRange.setStart(range.endContainer, range.endOffset);
      var textAfter = testRange.toString().replace(/[\r\n]/g, '');
      var atEnd = textAfter.length === 0;

      // If at end and previous enter left an empty trailing line (or block is completely empty), exit!
      if (atEnd && (inner.endsWith('\n') || inner === '' || code.innerHTML.endsWith('<br><br>') || code.innerHTML.endsWith('<br>'))) {
        e.preventDefault();
        while (code.lastChild && (code.lastChild.nodeName === 'BR' || code.lastChild.textContent === '\n' || code.lastChild.textContent === '')) {
          code.removeChild(code.lastChild);
        }
        var newP = document.createElement('p');
        newP.innerHTML = '<br>';
        if (pre.parentNode && pre.parentNode.tagName === 'P' && pre.parentNode !== this._content) {
          var pPar = pre.parentNode;
          pPar.parentNode.insertBefore(pre, pPar.nextSibling);
          pPar.parentNode.insertBefore(newP, pre.nextSibling);
          if (pPar.textContent.trim() === '') pPar.remove();
        } else if (pre.nextSibling) {
          pre.parentNode.insertBefore(newP, pre.nextSibling);
        } else {
          pre.parentNode.appendChild(newP);
        }
        var r = document.createRange();
        r.setStart(newP, 0);
        r.collapse(true);
        sel.removeAllRanges();
        sel.addRange(r);
        this._sync();
        this._history.push(this._content.innerHTML, true);
        this._updateActiveButtons();
        return true;
      }

      e.preventDefault();
      document.execCommand('insertLineBreak');
      this._sync();
      return true;
    }

    if (e.key === 'ArrowDown') {
      var code2 = pre.querySelector('code') || pre;
      var range2 = sel.getRangeAt(0);
      var testRange2 = document.createRange();
      testRange2.selectNodeContents(code2);
      testRange2.setStart(range2.endContainer, range2.endOffset);
      var afterText2 = testRange2.toString();
      if (afterText2.indexOf('\n') === -1) {
        e.preventDefault();
        var nextBlock = pre.nextElementSibling;
        if (!nextBlock) {
          nextBlock = document.createElement('p');
          nextBlock.innerHTML = '<br>';
          pre.parentNode.appendChild(nextBlock);
        }
        var rNext = document.createRange();
        rNext.selectNodeContents(nextBlock);
        rNext.collapse(true);
        sel.removeAllRanges();
        sel.addRange(rNext);
        this._updateActiveButtons();
        return true;
      }
    }

    return false;
  };

  Editor.prototype._handleSpoilerKeydown = function (e) {
    var sel = window.getSelection();
    if (!sel || !sel.rangeCount) return false;
    var node = sel.anchorNode;
    if (node && node.nodeType === 3) node = node.parentNode;
    var spoiler = node ? node.closest('details.crm-ve-spoiler') : null;
    if (!spoiler || !this._content.contains(spoiler)) return false;
    if (node && node.closest('summary')) return false;

    if (e.key === 'Enter' && !e.shiftKey) {
      var curBlock = node;
      while (curBlock && curBlock.parentNode !== spoiler && curBlock !== spoiler) {
        curBlock = curBlock.parentNode;
      }
      var isLastChild = curBlock === spoiler.lastElementChild;
      var blockText = (curBlock.textContent || '').replace(/[\u200B\uFEFF]/g, '').trim();

      if (isLastChild && blockText === '') {
        e.preventDefault();
        curBlock.remove();
        var newP = document.createElement('p');
        newP.innerHTML = '<br>';
        if (spoiler.nextSibling) {
          spoiler.parentNode.insertBefore(newP, spoiler.nextSibling);
        } else {
          spoiler.parentNode.appendChild(newP);
        }
        var r = document.createRange();
        r.setStart(newP, 0);
        r.collapse(true);
        sel.removeAllRanges();
        sel.addRange(r);
        this._sync();
        this._history.push(this._content.innerHTML, true);
        this._updateActiveButtons();
        return true;
      }
    }

    if (e.key === 'ArrowDown') {
      var curBlock2 = node;
      while (curBlock2 && curBlock2.parentNode !== spoiler && curBlock2 !== spoiler) {
        curBlock2 = curBlock2.parentNode;
      }
      if (curBlock2 === spoiler.lastElementChild) {
        var nextBlock = spoiler.nextElementSibling;
        if (!nextBlock || nextBlock.tagName !== 'P') {
          e.preventDefault();
          nextBlock = document.createElement('p');
          nextBlock.innerHTML = '<br>';
          if (spoiler.nextSibling) {
            spoiler.parentNode.insertBefore(nextBlock, spoiler.nextSibling);
          } else {
            spoiler.parentNode.appendChild(nextBlock);
          }
          var rNext = document.createRange();
          rNext.selectNodeContents(nextBlock);
          rNext.collapse(true);
          sel.removeAllRanges();
          sel.addRange(rNext);
          this._updateActiveButtons();
          return true;
        } else {
          e.preventDefault();
          var rNext2 = document.createRange();
          rNext2.selectNodeContents(nextBlock);
          rNext2.collapse(true);
          sel.removeAllRanges();
          sel.addRange(rNext2);
          this._updateActiveButtons();
          return true;
        }
      }
    }

    return false;
  };

  Editor.prototype._onKeyDown = function (e) {
    var self = this;

    // Slash menu: intercept arrow/enter/tab/escape when active
    if (self._slashMenuActive) {
      if (self._handleSlashKeydown(e)) return;
    }
    // Mention menu: intercept arrow/enter/tab/escape when active
    if (self._mentionMenuActive) {
      if (self._handleMentionKeydown(e)) return;
    }
    // Todo list: Enter/Backspace/Tab inside todo items
    if (self._handleTodoKeydown(e)) return;
    // Table: Tab between cells
    if (self._handleTableKeydown(e)) return;
    // Blockquote: Enter/ArrowDown exit
    if (self._handleBlockquoteKeydown(e)) return;
    // Code block: Enter newline / double-enter exit / ArrowDown exit
    if (self._handleCodeBlockKeydown(e)) return;
    // Spoiler: double-enter exit / ArrowDown exit
    if (self._handleSpoilerKeydown(e)) return;

    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
      e.preventDefault();
      self.undo();
      return;
    }

    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && e.shiftKey) {
      e.preventDefault();
      self.redo();
      return;
    }

    if ((e.ctrlKey || e.metaKey) && e.key === 'y') {
      e.preventDefault();
      self.redo();
      return;
    }

    if (e.key === 'Escape') {
      deselectImage(self);
      return;
    }

    if (e.key === 'Delete' || e.key === 'Backspace') {
      var selBlock = getSelectedImageBlock(self);
      if (selBlock) {
        e.preventDefault();
        deleteSelectedImage(self);
        return;
      }
    }

    if (e.key === 'Enter' && !e.shiftKey) {
      var selBlock2 = getSelectedImageBlock(self);
      if (selBlock2) {
        e.preventDefault();
        var p = document.createElement('p');
        p.innerHTML = '<br>';
        selBlock2.parentNode.insertBefore(p, selBlock2.nextSibling);
        deselectImage(self);
        self._sync();
        self._history.push(self._content.innerHTML, true);
        var range = document.createRange();
        range.setStart(p, 0);
        range.collapse(true);
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
        self._content.focus();
        return;
      }

      setTimeout(function () { self._sync(); }, 0);
      return;
    }

    if (e.key === 'Enter' && e.shiftKey) {
      document.execCommand('insertLineBreak');
      e.preventDefault();
      return;
    }

    if (e.key === 'Backspace') {
      var sel = window.getSelection();
      if (sel.rangeCount) {
        var range = sel.getRangeAt(0);
        if (range.collapsed && range.startOffset === 0) {
          var node = range.startContainer;
          if (node.nodeType === 3) node = node.parentNode;
          while (node && node.parentNode && node.parentNode !== self._content) {
            node = node.parentNode;
          }
          if (node && node.previousSibling === null && node === self._content.firstChild) {
            e.preventDefault();
            return;
          }
        }
      }
    }
  };

  Editor.prototype._onPaste = function (e) {
    e.preventDefault();
    var self = this;
    var clipboard = e.clipboardData || window.clipboardData;

    if (!clipboard) return;
    this._history.push(this._content.innerHTML, true);

    var imageFiles = getPastedImageFiles(clipboard);
    if (imageFiles.length > 0) {
      for (var i = 0; i < imageFiles.length; i++) {
        var pId = generateId();
        var placeholder = createUploadingPlaceholder(pId);
        self._insertAtCaret(placeholder);
        uploadImage(self, imageFiles[i], placeholder).catch(function () {});
      }
      return;
    }

    var html = clipboard.getData('text/html');
    var text = clipboard.getData('text/plain');

    var uploadQueue = [];

    if (html) {
      var temp = document.createElement('div');
      temp.innerHTML = html;
      var imgs = temp.querySelectorAll('img[src]');
      for (var j = 0; j < imgs.length; j++) {
        var imgNode = imgs[j];
        var src = imgNode.getAttribute('src');
        if (!src) continue;

        var pId = generateId();
        var phMarker = document.createElement('figure');
        phMarker.className = 'crm-ve-uploading-ph';
        phMarker.setAttribute('data-block-id', pId);
        var align = imgNode.getAttribute('data-align') || 'center';
        phMarker.setAttribute('data-align', align);

        var alt = imgNode.getAttribute('alt') || '';
        var caption = '';
        var link = '';
        var parentA = imgNode.closest('a');
        if (parentA) link = parentA.getAttribute('href') || '';
        var parentFig = imgNode.closest('figure');
        if (parentFig) {
          var capEl = parentFig.querySelector('figcaption');
          if (capEl) caption = capEl.innerHTML;
        }
        var customProps = { alt: alt, caption: caption, link: link };

        if (src.indexOf('data:image/') === 0) {
          try {
            var blob = dataURLtoBlob(src);
            var ext = src.split(';')[0].split('/')[1] || 'png';
            var file = new File([blob], 'pasted_image.' + ext, { type: blob.type });
            uploadQueue.push({ file: file, id: pId, align: align, customProps: customProps });
            imgNode.parentNode.replaceChild(phMarker, imgNode);
          } catch (ex) { /* skip */ }
        } else if (/^blob:/i.test(src)) {
          uploadQueue.push({ url: src, id: pId, align: align, customProps: customProps });
          imgNode.parentNode.replaceChild(phMarker, imgNode);
        }
      }
      html = temp.innerHTML;
    }

    var sanitized = html ? sanitizeHtml(html) : '';

    var sel = window.getSelection();
    var anchor = sel && sel.rangeCount ? sel.anchorNode : null;
    if (anchor && anchor.nodeType === 3) anchor = anchor.parentNode;
    var range = (sel && sel.rangeCount) ? sel.getRangeAt(0) : null;

    // Check if paste happened inside or on table controls
    if (range) {
      var startNode = range.startContainer;
      var inControls = (startNode.nodeType === 1 ? startNode : (startNode.parentNode || null));
      var ctrlParent = inControls && inControls.closest ? inControls.closest('.crm-ve-table-controls, .crm-ve-table-ctrl-btn, [data-table-action]') : null;
      if (ctrlParent) {
        var tWrap = ctrlParent.closest('.crm-ve-table-wrap');
        if (tWrap) {
          var afterP = tWrap.nextElementSibling;
          if (!afterP || afterP.tagName !== 'P') {
            afterP = document.createElement('p');
            afterP.innerHTML = '<br>';
            if (tWrap.parentNode) {
              if (tWrap.nextSibling) tWrap.parentNode.insertBefore(afterP, tWrap.nextSibling);
              else tWrap.parentNode.appendChild(afterP);
            }
          }
          var newRange = document.createRange();
          newRange.selectNodeContents(afterP);
          newRange.collapse(true);
          if (sel) {
            sel.removeAllRanges();
            sel.addRange(newRange);
          }
          range = newRange;
          anchor = afterP;
        }
      }
    }

    var li = anchor ? anchor.closest('li') : null;
    var list = li ? li.closest('ul, ol') : null;
    var heading = anchor ? anchor.closest('h1, h2, h3') : null;

    if (sanitized || text) {
      var isPlainText = !sanitized && !!text;
      if (li && list && self._content.contains(list)) {
        // Scenario 1: Inside list item (LI)
        var items = [];
        if (sanitized) {
          var checkDiv = document.createElement('div');
          checkDiv.innerHTML = sanitized;
          var pastedLis = checkDiv.querySelectorAll('li');
          if (pastedLis.length > 0) {
            items = Array.from(pastedLis).map(function (item) { return item.innerHTML; });
          } else {
            var childNodes = Array.from(checkDiv.childNodes);
            var collected = [];
            for (var c = 0; c < childNodes.length; c++) {
              var cn = childNodes[c];
              if (cn.nodeType === 3) {
                var txt = cn.textContent;
                if (txt && txt.trim()) {
                  collected.push(escapeHtml(txt));
                }
              } else if (cn.nodeType === 1) {
                if (cn.matches('figure, img, .crm-ve-uploading-ph')) {
                  collected.push(cn.outerHTML);
                } else if (cn.matches('p, h1, h2, h3, div, blockquote, pre')) {
                  collected.push(cn.innerHTML);
                } else {
                  collected.push(cn.outerHTML || cn.innerHTML);
                }
              }
            }
            if (collected.length > 0) {
              items = collected;
            } else {
              items = [sanitized];
            }
          }
        } else {
          items = text.split(/\r?\n+/);
        }

        if (range) range.deleteContents();
        var rAfter = range ? range.cloneRange() : document.createRange();
        rAfter.setEndAfter(li.lastChild || li);
        var afterFrag = rAfter.extractContents();

        var isTodoList = list.classList.contains('crm-ve-todo-list');
        var curLi = li;

        items.forEach(function (itemContent, idx) {
          if (idx === 0) {
            if (isTodoList) {
              var tSpan = curLi.querySelector('.crm-ve-todo-text');
              if (tSpan) {
                if (isPlainText) {
                  tSpan.appendChild(document.createTextNode(itemContent));
                } else {
                  var frag0 = range ? range.createContextualFragment(itemContent) : document.createRange().createContextualFragment(itemContent);
                  tSpan.appendChild(frag0);
                }
              } else {
                if (isPlainText) curLi.textContent = itemContent;
                else curLi.innerHTML = itemContent;
              }
            } else {
              if (isPlainText) {
                curLi.appendChild(document.createTextNode(itemContent));
              } else {
                var frag0 = range ? range.createContextualFragment(itemContent) : document.createRange().createContextualFragment(itemContent);
                curLi.appendChild(frag0);
              }
            }
          } else {
            var newLi;
            if (isTodoList) {
              newLi = createTodoItem('', false);
              var newSpan = newLi.querySelector('.crm-ve-todo-text');
              if (newSpan) {
                if (isPlainText) newSpan.textContent = itemContent;
                else newSpan.innerHTML = itemContent;
              }
            } else {
              newLi = document.createElement('li');
              if (isPlainText) newLi.textContent = itemContent;
              else newLi.innerHTML = itemContent;
            }
            if (curLi.nextSibling) {
              list.insertBefore(newLi, curLi.nextSibling);
            } else {
              list.appendChild(newLi);
            }
            curLi = newLi;
          }
        });

        if (afterFrag) {
          if (isTodoList) {
            var lastSpan = curLi.querySelector('.crm-ve-todo-text');
            if (lastSpan) {
              lastSpan.appendChild(afterFrag);
            } else {
              curLi.appendChild(afterFrag);
            }
          } else {
            curLi.appendChild(afterFrag);
          }
        }

        var rFinal = document.createRange();
        if (isTodoList) {
          var finalSpan = curLi.querySelector('.crm-ve-todo-text');
          if (finalSpan) {
            rFinal.selectNodeContents(finalSpan);
            rFinal.collapse(false);
          } else {
            rFinal.selectNodeContents(curLi);
            rFinal.collapse(false);
          }
        } else {
          rFinal.selectNodeContents(curLi);
          rFinal.collapse(false);
        }
        if (sel) {
          sel.removeAllRanges();
          sel.addRange(rFinal);
        }

      } else if (heading && self._content.contains(heading)) {
        // Scenario 2: Inside Heading (H1, H2, H3)
        var isPlainTextHeading = !sanitized && !!text;
        var tag = heading.tagName.toLowerCase();
        var headingText = (heading.textContent || '').trim();
        var selText = sel ? sel.toString().trim() : '';
        var isWholeHeadingSelected = (selText === headingText && headingText.length > 0)
          || (range && range.startContainer === heading && range.startOffset === 0 && range.endOffset === heading.childNodes.length);

        var contentHtml;
        if (isPlainTextHeading) {
          if (text.indexOf('\n') !== -1) {
            contentHtml = htmlToParagraphs(text);
          } else {
            contentHtml = escapeHtml(text);
          }
        } else {
          contentHtml = sanitized;
        }
        var frag = range ? range.createContextualFragment(contentHtml) : document.createRange().createContextualFragment(contentHtml);

        if (isWholeHeadingSelected || headingText === '') {
          heading.parentNode.replaceChild(frag, heading);
        } else {
          var rAfter2 = range.cloneRange();
          rAfter2.setEndAfter(heading.lastChild || heading);
          var afterFrag2 = rAfter2.extractContents();

          var nextHeading = document.createElement(tag);
          nextHeading.appendChild(afterFrag2);

          var insertRef = heading.nextSibling;
          var lastFragChild = frag.lastChild;
          heading.parentNode.insertBefore(frag, insertRef);

          if (nextHeading.textContent.trim() !== '' || nextHeading.querySelector('img')) {
            heading.parentNode.insertBefore(nextHeading, insertRef);
          }
          if (heading.textContent.trim() === '' && !heading.querySelector('img')) {
            heading.remove();
          }

          if (lastFragChild && sel) {
            var rHeadingFinal = document.createRange();
            rHeadingFinal.selectNodeContents(lastFragChild);
            rHeadingFinal.collapse(false);
            sel.removeAllRanges();
            sel.addRange(rHeadingFinal);
          }
        }

      } else {
        // Scenario 3: Standard Insertion
        if (sanitized) {
          document.execCommand('insertHTML', false, sanitized);
        } else if (text) {
          if (text.indexOf('\n') === -1) {
            document.execCommand('insertText', false, text);
          } else {
            document.execCommand('insertHTML', false, htmlToParagraphs(text));
          }
        }
      }

      // Convert any uploading markers to interactive placeholder blocks
      var markers = self._content.querySelectorAll('figure.crm-ve-uploading-ph');
      for (var m = 0; m < markers.length; m++) {
        var phMarker = markers[m];
        var bid = phMarker.getAttribute('data-block-id');
        var tAlign = phMarker.getAttribute('data-align') || 'center';
        var tTask = uploadQueue.find(function (q) { return q.id === bid; });
        var placeholderEl = createUploadingPlaceholder(bid, tAlign, null, tTask ? tTask.customProps : null);
        phMarker.parentNode.replaceChild(placeholderEl, phMarker);
      }

      uploadQueue.forEach(function (task) {
        var placeholderBlock = self._content.querySelector('.crm-ve-uploading[data-block-id="' + task.id + '"]');
        if (placeholderBlock) uploadImage(self, task.file || task.url, placeholderBlock).catch(function () {});
      });

      self._convertOrphanedImages();
      self._cleanPastedArtifacts();
      self._sync();
      self._history.push(self._content.innerHTML, true);
    }
  };

  // Insert a node at the current caret position inside the editor. Falls back
  // to appending at the end when there is no usable selection.
  // Image blocks are block-level <div>s: when the caret sits inside a <p>,
  // insertNode would nest the <div> inside the <p> (invalid HTML the browser
  // will not auto-split). We pull it out to the block level and keep a
  // paragraph before and after so typing continues on its own line.
  Editor.prototype._insertAtCaret = function (node) {
    var content = this._content;
    var sel = window.getSelection();
    var inserted = false;
    if (sel && sel.rangeCount && content.contains(sel.anchorNode)) {
      var range = sel.getRangeAt(0);
      if (range) {
        range.deleteContents();
        range.insertNode(node);

        // Keep block-level images as siblings of <p>, never nested inside one.
        var parent = node.parentNode;
        if (parent && parent !== content && parent.tagName === 'P') {
          parent.parentNode.insertBefore(node, parent.nextSibling);
          if (!node.nextSibling || node.nextSibling.nodeType !== Node.ELEMENT_NODE) {
            var p2 = document.createElement('p');
            p2.innerHTML = '<br>';
            node.parentNode.insertBefore(p2, node.nextSibling);
          }
          if (parent.innerHTML.trim() === '' || !parent.querySelector('br')) {
            parent.appendChild(document.createElement('br'));
          }
        }

        var after = document.createRange();
        after.setStartAfter(node);
        after.collapse(true);
        sel.removeAllRanges();
        sel.addRange(after);
        inserted = true;
      }
    }
    if (!inserted) {
      content.appendChild(node);
    }
  };

  Editor.prototype._onDrop = function (e) {
    e.preventDefault();
    this._content.classList.remove('crm-ve-dragover');
    this._wrapper.classList.remove('is-dragover');

    var files = e.dataTransfer && e.dataTransfer.files;
    if (!files || !files.length) return;

    var self = this;
    for (var i = 0; i < files.length; i++) {
      if (isImageFile(files[i])) {
        var placeholder = createUploadingPlaceholder(generateId());
        self._insertAtCaret(placeholder);
        uploadImage(self, files[i], placeholder).catch(function () {});
      }
    }
  };

  Editor.prototype._sync = function () {
    if (this._destroyed) return;
    updateTodoAriaLabels(this._content);
    var html = this._toOutputHtml();
    this._textarea.value = html;
    this._lastTextareaValue = html;
    this._textarea.dispatchEvent(new Event('change', { bubbles: true }));
    this._updateEmptyState();
  };

  Editor.prototype._syncEditorFromTextarea = function () {
    var value = this._textarea.value || '';
    if (!value) {
      this._content.innerHTML = '<p><br></p>';
    } else if (/<[a-z][\s\S]*>/i.test(value)) {
      this._content.innerHTML = this._fromOutputHtml(value);
      this._cleanPastedArtifacts();
    } else {
      this._content.innerHTML = htmlToParagraphs(value);
    }
    this._lastTextareaValue = value;
    this._updateEmptyState();
  };

  Editor.prototype.refreshFromTextarea = function (force) {
    if (this._destroyed) return false;
    var value = this._textarea.value || '';
    if (!force && value === this._lastTextareaValue) return false;
    if (!force && this._wrapper && this._wrapper.classList.contains('is-focused')) return false;
    this._syncEditorFromTextarea();
    this._history.push(this._content.innerHTML, true);
    return true;
  };

  Editor.prototype._isEmpty = function () {
    if (!this._content) return true;
    if (this._content.querySelector('.crm-ve-image-block, img, figure')) return false;
    var text = String(this._content.textContent || '')
      .replace(/\u200B/g, '')
      .replace(/\uFEFF/g, '')
      .trim();
    return text === '';
  };

  Editor.prototype._updateEmptyState = function () {
    if (!this._wrapper || !this._emptyActions) return;
    this._wrapper.classList.toggle('is-empty', this._isEmpty());
  };

  Editor.prototype._getCurrentBlockTag = function () {
    var sel = window.getSelection();
    if (!sel || !sel.rangeCount) return null;
    var node = sel.anchorNode;
    if (!node) return null;
    if (node.nodeType === 3) node = node.parentNode;
    if (!this._content.contains(node)) return null;

    if (node.closest('pre')) return 'pre';
    if (node.closest('blockquote')) return 'blockquote';
    if (node.closest('h1')) return 'h1';
    if (node.closest('h2')) return 'h2';
    if (node.closest('h3')) return 'h3';
    if (node.closest('ul.crm-ve-todo-list')) return 'todo';
    if (node.closest('ul')) return 'ul';
    if (node.closest('ol')) return 'ol';
    if (node.closest('table')) return 'table';
    if (node.closest('details.crm-ve-spoiler')) return 'spoiler';
    if (node.closest('p')) return 'p';
    return null;
  };

  Editor.prototype._updateActiveButtons = function () {
    var self = this;
    if (!this._toolbar || !this._content) return;
    var sel = window.getSelection();
    var tableCell = this._content.contains(document.activeElement) ? getSelectedTableCell(sel, this._content) : null;
    updateTableControls(this._content, tableCell);
    var hasFocus = Boolean(sel && sel.rangeCount && this._content.contains(sel.anchorNode));
    var currentBlock = hasFocus ? self._getCurrentBlockTag() : null;
    var isParagraph = !currentBlock || currentBlock === 'p';

    var anchorNode = hasFocus && sel && sel.rangeCount ? sel.anchorNode : null;
    if (anchorNode && anchorNode.nodeType === 3) anchorNode = anchorNode.parentNode;
    var isInlineCode = Boolean(anchorNode && anchorNode.closest && anchorNode.closest('code') && !anchorNode.closest('pre'));

    var buttons = this._toolbar.querySelectorAll('.crm-ve-toolbar-btn');
    buttons.forEach(function (btn) {
      var cmd = btn.getAttribute('data-cmd');
      var val = (btn.getAttribute('data-value') || '').toLowerCase();
      if (!cmd) return;

      var isActive = false;

      if (cmd === 'formatBlock' && val) {
        if (hasFocus) {
          isActive = val === 'p' ? isParagraph : currentBlock === val;
        }
      } else if (cmd === 'quote') {
        isActive = hasFocus && currentBlock === 'blockquote';
      } else if (cmd === 'codeBlock') {
        isActive = hasFocus && currentBlock === 'pre';
      } else if (cmd === 'todoList') {
        isActive = hasFocus && currentBlock === 'todo';
      } else if (cmd === 'insertUnorderedList') {
        isActive = hasFocus && currentBlock === 'ul';
      } else if (cmd === 'insertOrderedList') {
        isActive = hasFocus && currentBlock === 'ol';
      } else if (cmd === 'inlineCode') {
        isActive = hasFocus && isInlineCode;
      } else if (!hasFocus) {
        isActive = false;
      } else {
        try {
          var state = document.queryCommandState(cmd);
          if (cmd === 'bold' && (currentBlock === 'h1' || currentBlock === 'h2' || currentBlock === 'h3')) {
            var isExplicitBold = false;
            if (sel && sel.rangeCount) {
              var n = sel.anchorNode;
              if (n && n.nodeType === 3) n = n.parentNode;
              while (n && n !== self._content && n.tagName !== 'H1' && n.tagName !== 'H2' && n.tagName !== 'H3') {
                if (n.tagName === 'B' || n.tagName === 'STRONG') {
                  isExplicitBold = true;
                  break;
                }
                n = n.parentNode;
              }
            }
            isActive = isExplicitBold;
          } else {
            isActive = Boolean(state);
          }
        } catch (e) {
          isActive = false;
        }
      }

      btn.classList.toggle('is-active', isActive);
      if (btn.hasAttribute('aria-pressed')) {
        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      }
    });
  };

  Editor.prototype._convertOrphanedImages = function () {
    var imgs = this._content.querySelectorAll('img:not(.crm-ve-image)');
    for (var i = imgs.length - 1; i >= 0; i--) {
      var img = imgs[i];
      if (img.closest('.crm-ve-image-block')) continue;
      var src = img.getAttribute('src') || '';
      if (!src || src.indexOf('data:') === 0 || /^blob:/i.test(src)) {
        // data: and blob: URLs are ephemeral/unsafe to persist — drop them.
        if (img.parentNode) img.parentNode.removeChild(img);
        continue;
      }
      var alt = img.getAttribute('alt') || '';
      var block = createImageBlock(src, alt, 75, generateId(), this._options.readonly);
      var parent = img.parentNode;
      if (parent && parent.tagName === 'P' && parent !== this._content) {
        var otherText = (parent.textContent || '').trim();
        var otherElements = Array.prototype.slice.call(parent.children).filter(function (c) {
          return c !== img && c.tagName !== 'BR';
        });
        if (!otherText && otherElements.length === 0) {
          parent.parentNode.replaceChild(block, parent);
        } else {
          parent.parentNode.insertBefore(block, parent.nextSibling);
          img.parentNode.removeChild(img);
        }
      } else if (parent) {
        parent.replaceChild(block, img);
      }
    }
  };

  Editor.prototype._cleanPastedArtifacts = function () {
    if (!this._content) return;

    // 1. Ensure no unauthorized elements have contenteditable inside the editor
    var editables = this._content.querySelectorAll('[contenteditable]');
    for (var i = 0; i < editables.length; i++) {
      var el = editables[i];
      if (el === this._content) continue;
      var isMention = el.classList.contains('crm-ve-mention');
      var isCaption = el.classList.contains('crm-ve-image-caption');
      var isTodoText = el.classList.contains('crm-ve-todo-text');
      if (!isMention && !isCaption && !isTodoText) {
        el.removeAttribute('contenteditable');
      }
    }

    // 2. Unwrap any rogue <div> elements not belonging to internal editor blocks
    var strayDivs = this._content.querySelectorAll('div:not(.crm-ve-block):not(.crm-ve-image-frame):not(.crm-ve-upload-progress):not(.crm-ve-upload-bar):not(.crm-ve-table-wrap):not(.crm-ve-table-controls)');
    for (var d = strayDivs.length - 1; d >= 0; d--) {
      var div = strayDivs[d];
      if (div === this._content) continue;
      if (div.closest('.crm-ve-image-block')) continue;
      if (div.closest('.crm-ve-table-wrap') || div.closest('.crm-ve-table-controls')) continue;
      if (div.classList.contains('crm-ve-table-wrap') || div.classList.contains('crm-ve-table-controls')) continue;
      var divParent = div.parentNode;
      if (divParent) {
        while (div.firstChild) {
          divParent.insertBefore(div.firstChild, div);
        }
        divParent.removeChild(div);
      }
    }

    // 3. Lift block-level elements out of headings (h1, h2, h3)
    var headingBlocks = this._content.querySelectorAll('h1 p, h1 ul, h1 ol, h1 blockquote, h1 pre, h1 div, h1 table, h2 p, h2 ul, h2 ol, h2 blockquote, h2 pre, h2 div, h2 table, h3 p, h3 ul, h3 ol, h3 blockquote, h3 pre, h3 div, h3 table');
    for (var hb = headingBlocks.length - 1; hb >= 0; hb--) {
      var nBlock = headingBlocks[hb];
      var parentHeading = nBlock.closest('h1, h2, h3');
      if (parentHeading && parentHeading.parentNode) {
        parentHeading.parentNode.insertBefore(nBlock, parentHeading.nextSibling);
      }
    }

    // 3b. Lift block-level elements out of paragraphs
    var pBlocks = this._content.querySelectorAll('p pre, p blockquote, p table, p details, p ul, p ol, p figure, p > p, p .crm-ve-table-wrap');
    for (var pb = pBlocks.length - 1; pb >= 0; pb--) {
      var pBlock = pBlocks[pb];
      var parentP = pBlock.closest('p');
      if (parentP && parentP.parentNode) {
        parentP.parentNode.insertBefore(pBlock, parentP.nextSibling);
        if (parentP.textContent.trim() === '' && !parentP.querySelector('img, figure')) {
          parentP.remove();
        }
      }
    }

    // 3c. Clean up any rogue table control buttons outside .crm-ve-table-controls
    var rogueButtons = this._content.querySelectorAll('.crm-ve-table-ctrl-btn, [data-table-action]');
    for (var rb = 0; rb < rogueButtons.length; rb++) {
      var rogueBtn = rogueButtons[rb];
      if (!rogueBtn.closest('.crm-ve-table-controls')) {
        rogueBtn.remove();
      }
    }

    // 3d. Clean up empty/orphaned .crm-ve-table-controls or .crm-ve-table-wrap
    var orphanedControls = this._content.querySelectorAll('.crm-ve-table-controls');
    for (var oc = 0; oc < orphanedControls.length; oc++) {
      var ocEl = orphanedControls[oc];
      var parentWrap = ocEl.closest('.crm-ve-table-wrap');
      if (!parentWrap || !parentWrap.querySelector('table')) {
        ocEl.remove();
      }
    }
    var orphanedWraps = this._content.querySelectorAll('.crm-ve-table-wrap');
    for (var ow = 0; ow < orphanedWraps.length; ow++) {
      var owEl = orphanedWraps[ow];
      if (!owEl.querySelector('table')) {
        owEl.remove();
      }
    }

    // 3e. Ensure all tables are wrapped in .crm-ve-table-wrap and have exactly one .crm-ve-table-controls
    var allTables = this._content.querySelectorAll('table');
    for (var ti = 0; ti < allTables.length; ti++) {
      var tbl = allTables[ti];
      tbl.classList.add('crm-ve-table');
      var wrap = tbl.closest('.crm-ve-table-wrap');
      if (!wrap) {
        wrap = document.createElement('div');
        wrap.className = 'crm-ve-table-wrap';
        if (tbl.parentNode) {
          tbl.parentNode.insertBefore(wrap, tbl);
          wrap.appendChild(tbl);
        }
      }
      var existingControls = wrap.querySelectorAll('.crm-ve-table-controls');
      if (existingControls.length > 1) {
        for (var c = 1; c < existingControls.length; c++) {
          existingControls[c].remove();
        }
      } else if (existingControls.length === 0) {
        wrap.appendChild(createTableControls());
      }
      if (!wrap.nextElementSibling) {
        var followP = document.createElement('p');
        followP.innerHTML = '<br>';
        wrap.parentNode.appendChild(followP);
      }
    }

    updateTodoAriaLabels(this._content);

    // 4. Clean empty inline code tags
    var emptyCodes = this._content.querySelectorAll('code');
    for (var ec = 0; ec < emptyCodes.length; ec++) {
      var cNode = emptyCodes[ec];
      if (cNode.closest('pre')) continue;
      var cText = (cNode.textContent || '').replace(/[\u200B\uFEFF]/g, '').trim();
      if (!cText && !cNode.children.length) {
        cNode.remove();
      }
    }

    // 5. Convert styled spans/elements to semantic formatting tags and strip blocking styles
    var styled = this._content.querySelectorAll('[style]');
    for (var s = 0; s < styled.length; s++) {
      var sel = styled[s];
      if (sel.classList.contains('crm-ve-image-figure') || sel.tagName === 'FIGURE') continue;
      var style = sel.style;
      var fw = style.fontWeight;
      if (fw === 'bold' || fw === 'bolder' || parseInt(fw, 10) >= 600) {
        if (sel.tagName !== 'B' && sel.tagName !== 'STRONG' && sel.tagName !== 'H1' && sel.tagName !== 'H2' && sel.tagName !== 'H3') {
          var b = document.createElement('b');
          while (sel.firstChild) b.appendChild(sel.firstChild);
          sel.appendChild(b);
          style.fontWeight = '';
        }
      }
      var fs = style.fontStyle;
      if (fs === 'italic' || fs === 'oblique') {
        if (sel.tagName !== 'I' && sel.tagName !== 'EM') {
          var it = document.createElement('i');
          while (sel.firstChild) it.appendChild(sel.firstChild);
          sel.appendChild(it);
          style.fontStyle = '';
        }
      }
      var styleStr = sel.getAttribute('style') || '';
      if (/user-select|pointer-events|display\s*:\s*none|visibility\s*:\s*hidden/i.test(styleStr)) {
        sel.style.userSelect = '';
        sel.style.pointerEvents = '';
        if (/display\s*:\s*none/i.test(styleStr)) sel.style.display = '';
        if (/visibility\s*:\s*hidden/i.test(styleStr)) sel.style.visibility = '';
      }
      if (!sel.getAttribute('style')) sel.removeAttribute('style');
    }
  };

  // ---------------------------------------------------------------------------
  //  Output / Input conversion (image blocks <-> clean HTML)
  // ---------------------------------------------------------------------------

  Editor.prototype._toOutputHtml = function () {
    var container = document.createElement('div');
    container.innerHTML = this._content.innerHTML;

    // Strip table controls and buttons from output
    var tableControls = container.querySelectorAll('.crm-ve-table-controls, .crm-ve-table-ctrl-btn, [data-table-action]');
    for (var tc = 0; tc < tableControls.length; tc++) {
      tableControls[tc].remove();
    }

    // Strip upload placeholders and errors from output so temporary statuses never leak into saved content
    var uploadingBlocks = container.querySelectorAll('.crm-ve-uploading, .crm-ve-uploading-ph, .crm-ve-upload-error, .crm-ve-upload-progress, .crm-ve-upload-bar, .crm-ve-upload-spinner, .crm-ve-upload-label');
    for (var ub = 0; ub < uploadingBlocks.length; ub++) {
      uploadingBlocks[ub].remove();
    }

    // Strip empty figure placeholders without img
    var emptyFigures = container.querySelectorAll('figure.crm-ve-uploading-ph, figure.crm-ve-image-figure');
    for (var ef = 0; ef < emptyFigures.length; ef++) {
      if (!emptyFigures[ef].querySelector('img')) {
        emptyFigures[ef].remove();
      }
    }

    var blocks = container.querySelectorAll('.crm-ve-image-block');
    for (var i = blocks.length - 1; i >= 0; i--) {
      var block = blocks[i];
      var figure = block.querySelector('.crm-ve-image-figure');
      if (!figure) continue;

      var align = block.getAttribute('data-align') || 'center';
      if (['left', 'center', 'right'].indexOf(align) === -1) align = 'center';
      var widthCss = figure.style.getPropertyValue('--crm-ve-image-width').trim();
      var widthNum = parseFloat(widthCss) || parseFloat(block.getAttribute('data-width') || '') || 75;
      widthNum = Math.round(clamp(widthNum, 10, 100) * 100) / 100;

      var outFigure = document.createElement('figure');
      outFigure.setAttribute('data-align', align);
      outFigure.setAttribute('data-width', String(widthNum));
      outFigure.style.width = widthNum + '%';

      var img = figure.querySelector('.crm-ve-image');
      if (img) {
        var link = img.closest('a');
        var outImg = document.createElement('img');
        outImg.src = img.getAttribute('src') || '';
        outImg.alt = img.getAttribute('alt') || '';

        if (link) {
          var anchor = document.createElement('a');
          anchor.href = link.getAttribute('href') || '';
          if (link.getAttribute('target') === '_blank') {
            anchor.setAttribute('target', '_blank');
            anchor.setAttribute('rel', 'noopener noreferrer');
          }
          anchor.appendChild(outImg);
          outFigure.appendChild(anchor);
        } else {
          outFigure.appendChild(outImg);
        }
      }

      var caption = figure.querySelector('.crm-ve-image-caption');
      if (caption && caption.style.display !== 'none' && caption.innerHTML.trim()) {
        var outCaption = document.createElement('figcaption');
        outCaption.innerHTML = caption.innerHTML;
        outFigure.appendChild(outCaption);
      }

      block.parentNode.replaceChild(outFigure, block);
    }

    return sanitizeHtml(container.innerHTML);
  };

  Editor.prototype._fromOutputHtml = function (html) {
    if (!html || !/<[a-z][\s\S]*>/i.test(html)) return html;

    var container = document.createElement('div');
    container.innerHTML = sanitizeHtml(html);

    var figures = container.querySelectorAll('figure');
    for (var i = figures.length - 1; i >= 0; i--) {
      var fig = figures[i];
      var imgEl = fig.querySelector('img');
      if (!imgEl) continue;

      var align = fig.getAttribute('data-align') || 'center';
      var widthStr = fig.getAttribute('data-width') || '';
      var widthPct = widthStr ? parseFloat(widthStr) : 75;
      if (!widthStr) {
        var styleW = fig.style.width || '';
        if (styleW) widthPct = parseFloat(styleW) || 75;
      }
      widthPct = Math.round(clamp(widthPct, 10, 100) * 100) / 100;

      var alt = imgEl.getAttribute('alt') || '';
      var src = imgEl.getAttribute('src') || '';
      var bid = generateId();

      var block = createImageBlock(src, alt, widthPct, bid, this._options.readonly);
      block.setAttribute('data-align', align);
      block.setAttribute('data-width', String(widthPct));

      var blockFigure = block.querySelector('.crm-ve-image-figure');
      if (blockFigure && widthPct) {
        blockFigure.style.setProperty('--crm-ve-image-width', widthPct + '%');
      }

      var caption = fig.querySelector('figcaption');
      var blockCaption = block.querySelector('.crm-ve-image-caption');
      if (blockCaption) {
        if (caption && caption.innerHTML.trim()) {
          blockCaption.innerHTML = caption.innerHTML;
          blockCaption.style.display = '';
        } else {
          blockCaption.style.display = 'none';
        }
      }

      var link = fig.querySelector('a');
      if (link && link.querySelector('img')) {
        var blockImg = block.querySelector('.crm-ve-image');
        if (blockImg && blockFigure) {
          var anchor = document.createElement('a');
          anchor.href = link.getAttribute('href') || '';
          if (link.getAttribute('target') === '_blank') {
            anchor.setAttribute('target', '_blank');
            anchor.setAttribute('rel', 'noopener noreferrer');
          }
          blockImg.parentNode.insertBefore(anchor, blockImg);
          anchor.appendChild(blockImg);
        }
      }

      fig.parentNode.replaceChild(block, fig);
    }

    // Convert todo lists: ensure classes and data-checked attributes
    var todoLists = container.querySelectorAll('ul.crm-ve-todo-list, ul');
    todoLists.forEach(function (ul) {
      var isTodoList = ul.classList.contains('crm-ve-todo-list') || ul.querySelector('.crm-ve-todo-cb') || ul.querySelector('input[type="checkbox"]');
      if (!isTodoList) return;
      ul.className = 'crm-ve-todo-list';
      ul.querySelectorAll('li').forEach(function (li) {
        li.className = 'crm-ve-todo-item';
        var cb = li.querySelector('.crm-ve-todo-cb');
        var inputCb = li.querySelector('input[type="checkbox"]');
        var isChecked = li.getAttribute('data-checked') === 'true' || li.classList.contains('is-checked') || (inputCb && inputCb.checked) || (cb && cb.hasAttribute('data-checked'));
        if (inputCb) inputCb.remove();
        if (!cb) {
          cb = document.createElement('span');
          cb.className = 'crm-ve-todo-cb';
          li.insertBefore(cb, li.firstChild);
        }
        cb.setAttribute('role', 'checkbox');
        cb.setAttribute('tabindex', '0');
        cb.setAttribute('contenteditable', 'false');
        cb.setAttribute('aria-checked', isChecked ? 'true' : 'false');
        if (isChecked) {
          cb.setAttribute('data-checked', 'true');
          li.setAttribute('data-checked', 'true');
          li.classList.add('is-checked');
        } else {
          cb.removeAttribute('data-checked');
          li.removeAttribute('data-checked');
          li.classList.remove('is-checked');
        }
        var span = li.querySelector('.crm-ve-todo-text');
        if (!span) {
          span = document.createElement('span');
          span.className = 'crm-ve-todo-text';
          var textNodes = [];
          li.childNodes.forEach(function (n) {
            if (n !== cb) textNodes.push(n);
          });
          textNodes.forEach(function (n) { span.appendChild(n); });
          if (!span.textContent) span.innerHTML = '<br>';
          li.appendChild(span);
        }
        span.contentEditable = 'true';
        var itemLabel = (t('visual_editor.todo_item', 'Пункт чек-листа') + ': ' + (span.textContent || '').trim()).trim();
        cb.setAttribute('aria-label', itemLabel);
      });
    });

    // Ensure tables are wrapped in .crm-ve-table-wrap with controls
    var tables = container.querySelectorAll('table');
    tables.forEach(function (table) {
      table.classList.add('crm-ve-table');
      var wrap = table.closest('.crm-ve-table-wrap');
      if (!wrap) {
        wrap = document.createElement('div');
        wrap.className = 'crm-ve-table-wrap';
        if (table.parentNode) {
          table.parentNode.insertBefore(wrap, table);
          wrap.appendChild(table);
        }
      }
      if (!wrap.querySelector('.crm-ve-table-controls')) {
        wrap.appendChild(createTableControls());
      }
    });

    return container.innerHTML;
  };

  // ---------------------------------------------------------------------------
  //  Public API
  // ---------------------------------------------------------------------------

  Editor.prototype.getValue = function () {
    return this._toOutputHtml();
  };

  Editor.prototype.hasPendingUploads = function () {
    if (!this._content) return false;
    return !!this._content.querySelector('.crm-ve-uploading, .crm-ve-uploading-ph, .crm-ve-upload-progress');
  };

  Editor.prototype.hasUploadErrors = function () {
    if (!this._content) return false;
    return !!this._content.querySelector('.crm-ve-upload-error');
  };

  Editor.prototype._blockSaveForUploads = function () {
    var message;
    if (this.hasPendingUploads()) {
      message = t('visual_editor.wait_for_uploads', 'Пожалуйста, дождитесь окончания загрузки изображений...');
    } else if (this.hasUploadErrors()) {
      message = t('visual_editor.resolve_upload_errors', 'Пожалуйста, повторите загрузку или удалите ошибочное изображение');
    }
    if (!message) return false;
    if (!notify(message, 'warning')) showErrorToast(this, message);
    return true;
  };

  Editor.prototype.setValue = function (html) {
    if (typeof html !== 'string') return;
    if (/<[a-z][\s\S]*>/i.test(html)) {
      this._content.innerHTML = this._fromOutputHtml(html);
    } else {
      this._content.innerHTML = htmlToParagraphs(html);
    }
    this._sync();
    this._history.push(this._content.innerHTML, true);
  };

  function renderReadonly(root) {
    if (!root || !root.querySelectorAll) return;
    // Mark the container so rendered rich text (code blocks, spoilers, quotes)
    // picks up the read-only styles outside the editor as well.
    if (root.classList) root.classList.add('crm-ve-rendered');

    // Render todo lists: ensure classes and checked state
    root.querySelectorAll('ul.crm-ve-todo-list, ul').forEach(function (ul) {
      var isTodoList = ul.classList.contains('crm-ve-todo-list') || ul.querySelector('.crm-ve-todo-cb') || ul.querySelector('input[type="checkbox"]');
      if (!isTodoList) return;
      ul.classList.add('crm-ve-todo-list');
      ul.querySelectorAll('li').forEach(function (li) {
        li.classList.add('crm-ve-todo-item');
        var cb = li.querySelector('.crm-ve-todo-cb');
        var inputCb = li.querySelector('input[type="checkbox"]');
        var isChecked = li.getAttribute('data-checked') === 'true' || li.classList.contains('is-checked') || (inputCb && inputCb.checked) || (cb && cb.hasAttribute('data-checked'));
        if (inputCb) inputCb.remove();
        if (!cb) {
          cb = document.createElement('span');
          cb.className = 'crm-ve-todo-cb';
          li.insertBefore(cb, li.firstChild);
        }
        cb.setAttribute('role', 'checkbox');
        cb.setAttribute('tabindex', '0');
        cb.setAttribute('contenteditable', 'false');
        cb.setAttribute('aria-checked', isChecked ? 'true' : 'false');
        if (isChecked) {
          cb.setAttribute('data-checked', 'true');
          li.setAttribute('data-checked', 'true');
          li.classList.add('is-checked');
        } else {
          cb.removeAttribute('data-checked');
          li.removeAttribute('data-checked');
          li.classList.remove('is-checked');
        }
        var spanEl = li.querySelector('.crm-ve-todo-text');
        if (!spanEl) {
          spanEl = document.createElement('span');
          spanEl.className = 'crm-ve-todo-text';
          var textContent = '';
          li.childNodes.forEach(function (n) {
            if (n !== cb && n.nodeType === 3) textContent += n.textContent;
          });
          spanEl.textContent = textContent;
          if (!textContent) spanEl.innerHTML = '<br>';
          li.appendChild(spanEl);
        }
        var itemLabel2 = (t('visual_editor.todo_item', 'Пункт чек-листа') + ': ' + (spanEl.textContent || '').trim()).trim();
        cb.setAttribute('aria-label', itemLabel2);
      });
    });

    var figures = root.querySelectorAll('figure[data-width], figure[data-align]');
    figures.forEach(function (figure) {
      if (figure.closest('.crm-ve-readonly-image-block')) return;
      var img = figure.querySelector('img');
      if (!img) return;
      var align = figure.getAttribute('data-align') || 'center';
      if (['left', 'center', 'right'].indexOf(align) === -1) align = 'center';
      var width = parseFloat(figure.getAttribute('data-width') || '') || parseFloat(figure.style.width || '') || 100;
      width = Math.round(clamp(width, 10, 100) * 100) / 100;
      figure.classList.add('crm-ve-readonly-figure');
      figure.style.setProperty('--crm-ve-image-width', width + '%');
      figure.style.width = 'var(--crm-ve-image-width)';
      var wrapper = document.createElement('div');
      wrapper.className = 'crm-ve-readonly-image-block';
      wrapper.setAttribute('data-align', align);
      figure.parentNode.insertBefore(wrapper, figure);
      wrapper.appendChild(figure);
    });

    root.querySelectorAll('img').forEach(function (img) {
      if (img.dataset.crmVeFallbackAttached) return;
      img.dataset.crmVeFallbackAttached = '1';
      var handleImgError = function () {
        if (img.dataset.crmVeFailed) return;
        img.dataset.crmVeFailed = '1';
        var placeholder = document.createElement('div');
        placeholder.className = 'crm-ve-image-fallback alert alert-secondary d-flex align-items-center gap-2 p-2 my-2';
        placeholder.style.maxWidth = '100%';
        placeholder.style.fontSize = '0.875rem';
        var label = (img.alt ? img.alt : 'image.png') + ' (' + (window.CRM && window.CRM.i18n ? window.CRM.i18n.t('visual_editor.image_unavailable', 'недоступно') : 'недоступно') + ')';
        placeholder.textContent = '🖼️ ' + label;
        if (img.parentNode) {
          img.parentNode.insertBefore(placeholder, img);
          img.style.display = 'none';
        }
      };
      if (img.complete && img.naturalWidth === 0 && img.src) {
        handleImgError();
      } else {
        img.addEventListener('error', handleImgError);
      }
    });
  }

  Editor.prototype.clear = function () {
    this._content.innerHTML = '<p><br></p>';
    deselectImage(this);
    this._sync();
    this._history.reset();
    this._history.push(this._content.innerHTML, true);
  };

  Editor.prototype.focus = function () {
    this._content.focus();
    if (window.getSelection) {
      var sel = window.getSelection();
      var range = document.createRange();
      range.setStart(this._content, 0);
      range.collapse(true);
      sel.removeAllRanges();
      sel.addRange(range);
    }
  };

  Editor.prototype.destroy = function () {
    this._destroyed = true;
    deselectImage(this);

    if (this._onResizeOrScroll) {
      window.removeEventListener('resize', this._onResizeOrScroll);
      window.removeEventListener('scroll', this._onResizeOrScroll);
      this._onResizeOrScroll = null;
    }
    if (this._formSubmitGuard && this._parentForm) {
      this._parentForm.removeEventListener('submit', this._formSubmitGuard, true);
      this._formSubmitGuard = null;
    }
    if (this._escapeCaptureHandler) {
      window.removeEventListener('keydown', this._escapeCaptureHandler, true);
      this._escapeCaptureHandler = null;
    }
    if (this._outsideClickHandler) {
      document.removeEventListener('click', this._outsideClickHandler);
      this._outsideClickHandler = null;
    }

    if (typeof this._resizeCleanup === 'function') this._resizeCleanup();
    if (typeof this._resizeDocCleanup === 'function') this._resizeDocCleanup();

    var textarea = this._textarea;
    textarea.style.cssText = '';
    textarea.removeAttribute('data-crm-ve-ready');

    if (this._wrapper && this._wrapper.parentNode) {
      this._wrapper.parentNode.removeChild(this._wrapper);
    }

    this._toolbar = null;
    this._content = null;
    this._wrapper = null;
    this._imageToolbar = null;
    this._imageToolbarTarget = null;
    this._fileInput = null;
    this._selectedImageBlock = null;
    this._history = null;
  };

  Editor.prototype.undo = function () {
    var prev = this._history.undo();
    if (prev !== null && prev !== undefined) {
      this._content.innerHTML = prev;
      this._sync();
      this._updateActiveButtons();
      this._updateEmptyState();
      return true;
    }
    return false;
  };

  Editor.prototype.redo = function () {
    var next = this._history.redo();
    if (next !== null && next !== undefined) {
      this._content.innerHTML = next;
      this._sync();
      this._updateActiveButtons();
      this._updateEmptyState();
      return true;
    }
    return false;
  };

  // ---------------------------------------------------------------------------
  //  CSS injection
  // ---------------------------------------------------------------------------

  function injectStyles() {
    if (document.getElementById('crm-ve-styles')) return;
    if (document.querySelector('link[href*="visual-editor.css"]')) return;

    var css = (
      '/* Visual Editor */\n'
      + '.crm-ve-wrapper {\n'
      + '  position: relative;\n'
      + '  border: 1px solid #d1d5db;\n'
      + '  border-radius: 8px;\n'
      + '  background: #fff;\n'
      + '  display: flex;\n'
      + '  flex-direction: column;\n'
      + '  min-height: 200px;\n'
      + '}\n'
      + '.crm-ve-toolbar {\n'
      + '  display: flex;\n'
      + '  flex-wrap: wrap;\n'
      + '  align-items: center;\n'
      + '  gap: 2px;\n'
      + '  padding: 6px 8px;\n'
      + '  border-bottom: 1px solid #e5e7eb;\n'
      + '  background: #f9fafb;\n'
      + '  border-radius: 8px 8px 0 0;\n'
      + '  user-select: none;\n'
      + '}\n'
      + '.crm-ve-toolbar-btn {\n'
      + '  display: inline-flex;\n'
      + '  align-items: center;\n'
      + '  justify-content: center;\n'
      + '  width: 32px;\n'
      + '  height: 30px;\n'
      + '  border: none;\n'
      + '  background: transparent;\n'
      + '  border-radius: 4px;\n'
      + '  cursor: pointer;\n'
      + '  color: #374151;\n'
      + '  font-size: 13px;\n'
      + '  padding: 0;\n'
      + '  transition: background 0.15s, color 0.15s;\n'
      + '}\n'
      + '.crm-ve-toolbar-btn:hover {\n'
      + '  background: #e5e7eb;\n'
      + '  color: #111827;\n'
      + '}\n'
      + '.crm-ve-toolbar-btn.is-active {\n'
      + '  background: #e0e7ff;\n'
      + '  color: #4338ca;\n'
      + '}\n'
      + '.crm-ve-toolbar-btn svg {\n'
      + '  display: block;\n'
      + '}\n'
      + '.crm-ve-toolbar-sep {\n'
      + '  display: inline-block;\n'
      + '  width: 1px;\n'
      + '  height: 20px;\n'
      + '  background: #d1d5db;\n'
      + '  margin: 0 4px;\n'
      + '  flex-shrink: 0;\n'
      + '}\n'
      + '.crm-ve-content {\n'
      + '  flex: 1;\n'
      + '  padding: 12px 16px;\n'
      + '  min-height: 180px;\n'
      + '  outline: none;\n'
      + '  line-height: 1.6;\n'
      + '  color: #1f2937;\n'
      + '  font-size: 15px;\n'
      + '}\n'
      + '.crm-ve-content:empty:before,\n'
      + '.crm-ve-content[data-placeholder]:empty:before {\n'
      + '  content: attr(data-placeholder);\n'
      + '  color: #9ca3af;\n'
      + '  pointer-events: none;\n'
      + '}\n'
      + '.crm-ve-content p {\n'
      + '  margin: 0 0 8px;\n'
      + '}\n'
      + '.crm-ve-content p:last-child {\n'
      + '  margin-bottom: 0;\n'
      + '}\n'
      + '.crm-ve-content h2 {\n'
      + '  font-size: 20px;\n'
      + '  font-weight: 700;\n'
      + '  margin: 16px 0 8px;\n'
      + '  line-height: 1.3;\n'
      + '}\n'
      + '.crm-ve-content h3 {\n'
      + '  font-size: 17px;\n'
      + '  font-weight: 600;\n'
      + '  margin: 12px 0 6px;\n'
      + '  line-height: 1.4;\n'
      + '}\n'
      + '.crm-ve-content blockquote {\n'
      + '  border-left: 3px solid #d1d5db;\n'
      + '  margin: 8px 0;\n'
      + '  padding: 4px 12px;\n'
      + '  color: #6b7280;\n'
      + '  font-style: italic;\n'
      + '}\n'
      + '.crm-ve-content pre {\n'
      + '  background: #f3f4f6;\n'
      + '  border: 1px solid #e5e7eb;\n'
      + '  border-radius: 6px;\n'
      + '  padding: 12px;\n'
      + '  font-family: "SF Mono", Monaco, "Cascadia Code", "Consolas", monospace;\n'
      + '  font-size: 13px;\n'
      + '  overflow-x: auto;\n'
      + '  margin: 8px 0;\n'
      + '}\n'
      + '.crm-ve-content pre code {\n'
      + '  background: none;\n'
      + '  padding: 0;\n'
      + '  border: none;\n'
      + '  color: inherit;\n'
      + '}\n'
      + '.crm-ve-content code {\n'
      + '  background: #f3f4f6;\n'
      + '  padding: 2px 6px;\n'
      + '  border-radius: 4px;\n'
      + '  font-size: 0.875em;\n'
      + '  font-family: "SF Mono", Monaco, "Cascadia Code", "Consolas", monospace;\n'
      + '}\n'
      + '.crm-ve-content ul, .crm-ve-content ol {\n'
      + '  margin: 4px 0;\n'
      + '  padding-left: 24px;\n'
      + '}\n'
      + '.crm-ve-content li {\n'
      + '  margin-bottom: 2px;\n'
      + '}\n'
      + '.crm-ve-content a {\n'
      + '  color: #2563eb;\n'
      + '  text-decoration: underline;\n'
      + '}\n'
      + '.crm-ve-content img {\n'
      + '  max-width: 100%;\n'
      + '  height: auto;\n'
      + '  display: block;\n'
      + '}\n'
      + '.crm-ve-dragover {\n'
      + '  outline: 2px dashed #3b82f6;\n'
      + '  outline-offset: -2px;\n'
      + '  background: #f0f7ff;\n'
      + '}\n'
      + '/* Image blocks */\n'
      + '.crm-ve-image-block {\n'
      + '  display: flex;\n'
      + '  margin: 12px 0;\n'
      + '  position: relative;\n'
      + '}\n'
      + '.crm-ve-image-block[data-align="left"] {\n'
      + '  justify-content: flex-start;\n'
      + '}\n'
      + '.crm-ve-image-block[data-align="center"] {\n'
      + '  justify-content: center;\n'
      + '}\n'
      + '.crm-ve-image-block[data-align="right"] {\n'
      + '  justify-content: flex-end;\n'
      + '}\n'
      + '.crm-ve-image-figure {\n'
      + '  position: relative;\n'
      + '  margin: 0;\n'
      + '  display: inline-flex;\n'
      + '  flex-direction: column;\n'
      + '  align-items: center;\n'
      + '  width: var(--crm-ve-image-width, 75%);\n'
      + '  min-width: 120px;\n'
      + '  max-width: 100%;\n'
      + '  border-radius: 6px;\n'
      + '  transition: box-shadow 0.2s;\n'
      + '}\n'
      + '.crm-ve-image-figure.is-selected {\n'
      + '  box-shadow: 0 0 0 2px #3b82f6;\n'
      + '}\n'
      + '.crm-ve-image-figure.is-resizing {\n'
      + '  box-shadow: 0 0 0 2px #f59e0b;\n'
      + '  cursor: ew-resize;\n'
      + '}\n'
      + '.crm-ve-image {\n'
      + '  display: block;\n'
      + '  width: 100%;\n'
      + '  height: auto;\n'
      + '  border-radius: 4px;\n'
      + '  user-select: none;\n'
      + '  pointer-events: none;\n'
      + '}\n'
      + '.crm-ve-image-caption {\n'
      + '  font-size: 13px;\n'
      + '  color: #6b7280;\n'
      + '  text-align: center;\n'
      + '  padding: 4px 8px;\n'
      + '  outline: none;\n'
      + '  min-height: 20px;\n'
      + '  width: 100%;\n'
      + '  cursor: text;\n'
      + '}\n'
      + '.crm-ve-image-caption:empty:before {\n'
      + '  content: attr(data-placeholder);\n'
      + '  color: #9ca3af;\n'
      + '}\n'
      + '.crm-ve-image-frame {\n'
      + '  position: absolute;\n'
      + '  inset: 0;\n'
      + '  pointer-events: none;\n'
      + '}\n'
      + '.crm-ve-image-figure.is-selected .crm-ve-image-frame,\n'
      + '.crm-ve-image-figure.is-resizing .crm-ve-image-frame {\n'
      + '  pointer-events: auto;\n'
      + '}\n'
      + '.crm-ve-resize-handle {\n'
      + '  position: absolute;\n'
      + '  width: 10px;\n'
      + '  height: 10px;\n'
      + '  background: #fff;\n'
      + '  border: 2px solid #3b82f6;\n'
      + '  border-radius: 50%;\n'
      + '  cursor: ew-resize;\n'
      + '  padding: 0;\n'
      + '  display: none;\n'
      + '  z-index: 5;\n'
      + '}\n'
      + '.crm-ve-image-figure.is-selected .crm-ve-resize-handle,\n'
      + '.crm-ve-image-figure.is-resizing .crm-ve-resize-handle {\n'
      + '  display: block;\n'
      + '}\n'
      + '.crm-ve-resize-handle-left {\n'
      + '  left: -5px;\n'
      + '  top: 50%;\n'
      + '  transform: translateY(-50%);\n'
      + '}\n'
      + '.crm-ve-resize-handle-right {\n'
      + '  right: -5px;\n'
      + '  top: 50%;\n'
      + '  transform: translateY(-50%);\n'
      + '}\n'
      + '.crm-ve-resize-handle-bottom-left {\n'
      + '  left: -5px;\n'
      + '  bottom: -5px;\n'
      + '}\n'
      + '.crm-ve-resize-handle-bottom-right {\n'
      + '  right: -5px;\n'
      + '  bottom: -5px;\n'
      + '}\n'
      + '.crm-ve-resize-handle:hover {\n'
      + '  background: #3b82f6;\n'
      + '}\n'
      + '/* Image toolbar */\n'
      + '.crm-ve-image-toolbar-btn {\n'
      + '  display: inline-flex;\n'
      + '  align-items: center;\n'
      + '  justify-content: center;\n'
      + '  background: transparent;\n'
      + '  border: none;\n'
      + '  color: #e2e8f0;\n'
      + '  padding: 4px 8px;\n'
      + '  border-radius: 4px;\n'
      + '  cursor: pointer;\n'
      + '  font-size: 12px;\n'
      + '  line-height: 1;\n'
      + '  white-space: nowrap;\n'
      + '  transition: background 0.15s;\n'
      + '}\n'
      + '.crm-ve-image-toolbar-btn:hover {\n'
      + '  background: #334155;\n'
      + '}\n'
      + '.crm-ve-image-toolbar-btn svg {\n'
      + '  display: block;\n'
      + '}\n'
      + '/* Upload placeholder */\n'
      + '.crm-ve-uploading {\n'
      + '  padding: 24px;\n'
      + '  text-align: center;\n'
      + '  background: #f9fafb;\n'
      + '  border: 1px dashed #d1d5db;\n'
      + '  border-radius: 8px;\n'
      + '  color: #6b7280;\n'
      + '  font-size: 14px;\n'
      + '}\n'
      + '.crm-ve-upload-progress {\n'
      + '  margin-top: 8px;\n'
      + '  height: 4px;\n'
      + '  background: #e5e7eb;\n'
      + '  border-radius: 2px;\n'
      + '  overflow: hidden;\n'
      + '}\n'
      + '.crm-ve-upload-bar {\n'
      + '  height: 100%;\n'
      + '  width: 30%;\n'
      + '  background: #3b82f6;\n'
      + '  border-radius: 2px;\n'
      + '  animation: crm-ve-upload-pulse 1.2s ease-in-out infinite;\n'
      + '}\n'
      + '@keyframes crm-ve-upload-pulse {\n'
      + '  0% { width: 30%; }\n'
      + '  50% { width: 70%; }\n'
      + '  100% { width: 30%; }\n'
      + '}\n'
      + '.crm-ve-upload-label {\n'
      + '  display: block;\n'
      + '  margin-bottom: 4px;\n'
      + '}\n'
      + '.crm-ve-upload-percent {\n'
      + '  font-family: monospace;\n'
      + '  font-size: 12px;\n'
      + '  color: #9ca3af;\n'
      + '}\n'
      + '/* Todo list */\n'
      + '.crm-ve-todo-list {\n'
      + '  list-style: none;\n'
      + '  padding-left: 0;\n'
      + '  margin: 0 0 8px;\n'
      + '}\n'
      + '.crm-ve-todo-item {\n'
      + '  display: flex;\n'
      + '  align-items: flex-start;\n'
      + '  gap: 8px;\n'
      + '  padding: 2px 0;\n'
      + '  line-height: 1.6;\n'
      + '}\n'
      + '.crm-ve-todo-cb {\n'
      + '  flex-shrink: 0;\n'
      + '  width: 16px;\n'
      + '  height: 16px;\n'
      + '  margin-top: 4px;\n'
      + '  cursor: pointer;\n'
      + '  accent-color: #4f46e5;\n'
      + '}\n'
      + '.crm-ve-todo-text {\n'
      + '  flex: 1;\n'
      + '}\n'
      + '.crm-ve-todo-item.is-checked .crm-ve-todo-text {\n'
      + '  text-decoration: line-through;\n'
      + '  color: #9ca3af;\n'
      + '}\n'
      + '/* Rendered todo (read-only) */\n'
      + '.crm-ve-rendered .crm-ve-todo-list {\n'
      + '  list-style: none;\n'
      + '  padding-left: 0;\n'
      + '  margin: 0 0 8px;\n'
      + '}\n'
      + '.crm-ve-rendered .crm-ve-todo-item {\n'
      + '  display: flex;\n'
      + '  align-items: flex-start;\n'
      + '  gap: 8px;\n'
      + '  padding: 2px 0;\n'
      + '  line-height: 1.6;\n'
      + '}\n'
      + '.crm-ve-rendered .crm-ve-todo-cb {\n'
      + '  flex-shrink: 0;\n'
      + '  width: 16px;\n'
      + '  height: 16px;\n'
      + '  margin-top: 4px;\n'
      + '  accent-color: #4f46e5;\n'
      + '}\n'
      + '.crm-ve-rendered .crm-ve-todo-item.is-checked .crm-ve-todo-text {\n'
      + '  text-decoration: line-through;\n'
      + '  color: #9ca3af;\n'
      + '}\n'
      + '/* Table */\n'
      + '.crm-ve-table-wrap {\n'
      + '  overflow-x: auto;\n'
      + '  margin: 0 0 8px;\n'
      + '  border: 1px solid #e5e7eb;\n'
      + '  border-radius: 6px;\n'
      + '}\n'
      + '.crm-ve-table {\n'
      + '  width: 100%;\n'
      + '  border-collapse: collapse;\n'
      + '  font-size: 14px;\n'
      + '}\n'
      + '.crm-ve-table td, .crm-ve-table th {\n'
      + '  border: 1px solid #e5e7eb;\n'
      + '  padding: 8px 12px;\n'
      + '  text-align: left;\n'
      + '  vertical-align: top;\n'
      + '  min-width: 80px;\n'
      + '}\n'
      + '.crm-ve-table th {\n'
      + '  background: #f9fafb;\n'
      + '  font-weight: 600;\n'
      + '}\n'
      + '.crm-ve-table td:focus, .crm-ve-table th:focus {\n'
      + '  outline: 2px solid #4f46e5;\n'
      + '  outline-offset: -2px;\n'
      + '}\n'
      + '/* Slash menu */\n'
      + '.crm-ve-slash-menu[style*="display: block"] {\n'
      + '  display: block !important;\n'
      + '}\n'
      + '.crm-ve-slash-item:hover, .crm-ve-slash-item.is-active {\n'
      + '  background: #f3f4f6;\n'
      + '}\n'
      + '/* Mentions */\n'
      + '.crm-ve-mention {\n'
      + '  display: inline;\n'
      + '  background: #e0e7ff;\n'
      + '  color: #4f46e5;\n'
      + '  padding: 1px 6px;\n'
      + '  border-radius: 4px;\n'
      + '  font-weight: 500;\n'
      + '  cursor: default;\n'
      + '  user-select: all;\n'
      + '}\n'
      + '.crm-ve-mention-menu[style*="display: block"] {\n'
      + '  display: block !important;\n'
      + '}\n'
      + '.crm-ve-mention-item:hover, .crm-ve-mention-item.is-active {\n'
      + '  background: #f3f4f6;\n'
      + '}\n'
    );

    var style = document.createElement('style');
    style.id = 'crm-ve-styles';
    // CSP style-src has no 'unsafe-inline': dynamically injected <style> needs
    // the per-request nonce or the browser blocks it (see web/index.php).
    if (window.CRM && window.CRM.config && window.CRM.config.cspNonce) {
      style.setAttribute('nonce', window.CRM.config.cspNonce);
    }
    style.textContent = css;
    document.head.appendChild(style);
  }

  // ---------------------------------------------------------------------------
  //  Instances tracker
  // ---------------------------------------------------------------------------

  var instances = [];

  // ---------------------------------------------------------------------------
  //  Auto-init
  // ---------------------------------------------------------------------------

  function initScope(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;
    var textareas = root.querySelectorAll('textarea[data-crm-visual-editor="1"]:not([data-crm-ve-ready])');
    textareas.forEach(function (ta) { initTextarea(ta); });
  }

  function initTextarea(textarea) {
    if (textarea.hasAttribute('data-crm-ve-ready')) return;
    try {
      var editor = new Editor(textarea);
      textarea.setAttribute('data-crm-ve-ready', '1');
      instances.push(editor);
    } catch (e) {
      /* VisualEditor init failed */
    }
  }

  function isVisible(el) {
    if (!el) return false;
    var rect = el.getBoundingClientRect();
    return !!(rect.width || rect.height || el.getClientRects().length);
  }

  function refreshEditors(scope, force) {
    var root = scope && scope.querySelectorAll ? scope : document;
    instances.forEach(function (editor) {
      if (!editor || editor._destroyed) return;
      if (root !== document && !root.contains(editor._textarea)) return;
      if (!force && !isVisible(editor._wrapper)) return;
      editor.refreshFromTextarea(!!force);
    });
  }

  function scheduleRefresh(scope) {
    var delays = [0, 120, 400, 900, 1600];
    delays.forEach(function (delay) {
      window.setTimeout(function () {
        refreshEditors(scope || document, false);
      }, delay);
    });
  }

  // ---------------------------------------------------------------------------
  //  Public API
  // ---------------------------------------------------------------------------

  var _initialized = false;

  function init() {
    if (_initialized) return;
    _initialized = true;
    injectStyles();
    initScope(document);

    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (!(node instanceof HTMLElement)) return;
          if (node.matches && node.matches('textarea[data-crm-visual-editor="1"]')) {
            initTextarea(node);
            return;
          }
          if (node.querySelector) {
            var found = node.querySelectorAll('textarea[data-crm-visual-editor="1"]:not([data-crm-ve-ready])');
            found.forEach(function (ta) { initTextarea(ta); });
          }
        });
      });
    });
    observer.observe(document.body, { childList: true, subtree: true });

    document.addEventListener('shown.bs.modal', function (e) {
      scheduleRefresh(e.target || document);
    });

    document.addEventListener('click', function (e) {
      var trigger = e.target && e.target.closest && e.target.closest('[data-open-modal], [data-bs-toggle="modal"], button, a');
      if (!trigger) return;
      scheduleRefresh(document);
    }, true);

    // Ensure Enter inside the editor produces <p> blocks rather than <div>.
    // The sanitizer strips <div>, which would silently merge separate lines
    // into a single paragraph on save. Document-global, so set it once here
    // (idempotent; all editors on the page share this behavior).
    try {
      document.execCommand('defaultParagraphSeparator', false, 'p');
    } catch (e) { /* older browsers: fall back to native behavior */ }
  }

  // Auto-init on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(); });
  } else {
    init();
  }

  function editorsInScope(scope) {
    if (!scope) return instances;
    return instances.filter(function (editor) {
      return editor._textarea && (scope === editor._textarea || scope === editor._wrapper
        || (scope.contains && (scope.contains(editor._textarea) || scope.contains(editor._wrapper))));
    });
  }

  function hasPendingUploads(scope) {
    return editorsInScope(scope).some(function (ed) {
      return ed && typeof ed.hasPendingUploads === 'function' && ed.hasPendingUploads();
    });
  }

  function hasUploadErrors(scope) {
    return editorsInScope(scope).some(function (ed) {
      return ed && typeof ed.hasUploadErrors === 'function' && ed.hasUploadErrors();
    });
  }

  // Explicit guard for actions that save content without submitting a form.
  function blockSaveForUploads(scope) {
    return !!scope && editorsInScope(scope).some(function (editor) {
      return editor._blockSaveForUploads();
    });
  }

  return {
    Editor: Editor,
    init: init,
    initScope: initScope,
    instances: instances,
    getInstances: function () { return instances; },
    refreshEditors: function (scope, force) { refreshEditors(scope || document, !!force); },
    sanitizeHtml: sanitizeHtml,
    renderReadonly: renderReadonly,
    hasPendingUploads: hasPendingUploads,
    hasUploadErrors: hasUploadErrors,
    blockSaveForUploads: blockSaveForUploads
  };

})();
