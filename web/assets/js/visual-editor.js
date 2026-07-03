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
          .replace(/>/g, '&gt;');
        return '<p>' + (safe || '<br>') + '</p>';
      })
      .join('');
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
      if (window.CRM && window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.notify === 'function') {
        return window.CRM.pageApiBindings.notify(text, type);
      }
      if (typeof window.notify === 'function') {
        return window.notify(text, type);
      }
    } catch (e) { /* ignore */ }
    if (typeof console !== 'undefined' && console.warn) {
      console.warn('[VisualEditor]', text);
    }
  }

  function showErrorToast(editorInstance, message) {
    if (!editorInstance) return;
    var toast = document.createElement('div');
    toast.className = 'crm-ve-toast crm-ve-toast-error';
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
        reject(new Error('Failed to decode image'));
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
    A: true, H1: true, H2: true, H3: true, FIGURE: true, FIGCAPTION: true, IMG: true
  };

  var BLOCK_TAGS = {
    P: true, H1: true, H2: true, H3: true, BLOCKQUOTE: true, PRE: true,
    UL: true, OL: true, LI: true, FIGURE: true
  };

  function sanitizeHtml(rawHtml) {
    if (!rawHtml) return '';
    var template = document.createElement('template');
    template.innerHTML = String(rawHtml);

    function walk(node) {
      var children = Array.prototype.slice.call(node.childNodes || []);
      children.forEach(function (child) {
        if (child.nodeType === Node.ELEMENT_NODE) {
          var tag = child.tagName.toUpperCase();
          if (!ALLOWED_TAGS[tag]) {
            var parent = child.parentNode;
            while (child.firstChild) {
              parent.insertBefore(child.firstChild, child);
            }
            parent.removeChild(child);
            return;
          }

          var attrs = Array.prototype.slice.call(child.attributes || []);
          attrs.forEach(function (attr) {
            var name = String(attr.name || '').toLowerCase();
            if (name.indexOf('on') === 0) {
              child.removeAttribute(attr.name);
              return;
            }
            if (tag === 'A') {
              if (name !== 'href' && name !== 'title' && name !== 'target' && name !== 'rel') {
                child.removeAttribute(attr.name);
              }
            } else if (tag === 'IMG') {
              if (name !== 'src' && name !== 'alt') {
                child.removeAttribute(attr.name);
              }
            } else if (tag === 'FIGURE') {
              if (name !== 'data-align' && name !== 'data-width' && name !== 'style') {
                child.removeAttribute(attr.name);
              }
            } else if (tag === 'FIGCAPTION') {
              if (name !== 'contenteditable') {
                child.removeAttribute(attr.name);
              }
            } else {
              child.removeAttribute(attr.name);
            }
          });

          if (tag === 'FIGURE' && child.hasAttribute('style')) {
            var styleVal = child.getAttribute('style') || '';
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
              child.setAttribute('style', safeStyles.join(';'));
            } else {
              child.removeAttribute('style');
            }
          }

          if (tag === 'A') {
            var safeHref = sanitizeLinkHref(child.getAttribute('href'));
            if (!safeHref) {
              child.removeAttribute('href');
            } else {
              child.setAttribute('href', safeHref);
              var isExternal = /^https?:\/\//i.test(safeHref);
              if (isExternal) {
                child.setAttribute('target', '_blank');
                child.setAttribute('rel', 'noopener noreferrer');
              } else {
                child.removeAttribute('target');
                child.removeAttribute('rel');
              }
            }
          }

          if (tag === 'IMG') {
            var src = child.getAttribute('src') || '';
            if (!src || src.indexOf('data:') === 0) {
              node.removeChild(child);
              return;
            }
          }

          if (tag === 'STYLE' || tag === 'SCRIPT' || tag === 'IFRAME' || tag === 'OBJECT' || tag === 'EMBED' || tag === 'FORM' || tag === 'SVG' || tag === 'MATH') {
            node.removeChild(child);
            return;
          }

          walk(child);

        } else if (child.nodeType === Node.COMMENT_NODE) {
          node.removeChild(child);
        } else if (child.nodeType === Node.TEXT_NODE) {
          if (child.textContent === '\u200B' || child.textContent === '\uFEFF') {
            node.removeChild(child);
          }
        }
      });
    }

    walk(template.content);

    var result = template.innerHTML.trim();
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

    function push(html, immediate) {
      if (timer) {
        clearTimeout(timer);
        timer = null;
      }
      var normalized = html || '';
      if (immediate) {
        commit(normalized);
      } else {
        timer = setTimeout(function () {
          timer = null;
          commit(normalized);
        }, 800);
      }
    }

    function commit(html) {
      if (pointer < snapshots.length - 1) {
        snapshots = snapshots.slice(0, pointer + 1);
      }
      var last = snapshots[snapshots.length - 1];
      if (html === last) return;
      snapshots.push(html);
      if (snapshots.length > maxSize) {
        snapshots.shift();
      }
      pointer = snapshots.length - 1;
    }

    function undo(currentHtml) {
      if (timer) {
        clearTimeout(timer);
        timer = null;
      }
      if (pointer < 0) return null;
      if (pointer === snapshots.length - 1) {
        snapshots[pointer] = currentHtml;
      }
      pointer--;
      if (pointer < 0) return null;
      return snapshots[pointer];
    }

    function redo(currentHtml) {
      if (timer) {
        clearTimeout(timer);
        timer = null;
      }
      if (pointer >= snapshots.length - 1) return null;
      pointer++;
      return snapshots[pointer];
    }

    function reset() {
      if (timer) {
        clearTimeout(timer);
        timer = null;
      }
      snapshots = [];
      pointer = -1;
    }

    function peek() {
      return snapshots[pointer];
    }

    return { push: push, undo: undo, redo: redo, reset: reset, commit: commit, peek: peek };
  }

  // ---------------------------------------------------------------------------
  //  Image Block creation
  // ---------------------------------------------------------------------------

  function createImageBlock(src, alt, widthPercent, blockId) {
    var bid = blockId || generateId();
    var figWidth = widthPercent ? '--crm-ve-image-width:' + widthPercent + '%' : '';
    var altText = alt || '';

    var div = document.createElement('div');
    div.className = 'crm-ve-block crm-ve-image-block';
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
    figcaption.setAttribute('contenteditable', 'true');
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

  function createUploadingPlaceholder(blockId) {
    var div = document.createElement('div');
    div.className = 'crm-ve-block crm-ve-image-block crm-ve-uploading';
    div.setAttribute('data-type', 'image');
    div.setAttribute('data-block-id', blockId);
    div.setAttribute('data-align', 'center');

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

    function btn(label, title, action) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'crm-ve-image-toolbar-btn';
      b.innerHTML = label;
      b.title = title || '';
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
      function () { setImageAlign(editorInstance, 'left'); }
    ));
    toolbar.appendChild(btn(
      '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M1 3v10h14V3H1zm1 1h12v5.586l-2.293-2.293a1 1 0 0 0-1.414 0L8 9.586l-1.293-1.293a1 1 0 0 0-1.414 0L3 9.586V4zm0 7.414L4.293 10.12l1.293 1.293a1 1 0 0 0 1.414 0L8 10.12l1.293 1.293a1 1 0 0 0 1.414 0L12 10.12l2 2H3v-1.707zM13 6.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/></svg>',
      t('visual_editor.align_center', 'Center'),
      function () { setImageAlign(editorInstance, 'center'); }
    ));
    toolbar.appendChild(btn(
      '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M2 3v10h12V3H2zm1 1h10v5.586l-2.293-2.293a1 1 0 0 0-1.414 0L9 9.586 7.707 8.293a1 1 0 0 0-1.414 0L5 9.586 3.707 8.293a1 1 0 0 0-1.414 0L3 9.586V4zm0 7.414L4.293 10.12l1.293 1.293a1 1 0 0 0 1.414 0L7.707 10.12 9 11.414a1 1 0 0 0 1.414 0L11.707 10.12 14 12.414V14H3v-2.586zM11 6.5a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0z"/></svg>',
      t('visual_editor.align_right', 'Align right'),
      function () { setImageAlign(editorInstance, 'right'); }
    ));
    toolbar.appendChild(sep());

    toolbar.appendChild(btn('25%', t('visual_editor.width_25', '25% width'), function () { setImageWidth(editorInstance, 25); }));
    toolbar.appendChild(btn('50%', t('visual_editor.width_50', '50% width'), function () { setImageWidth(editorInstance, 50); }));
    toolbar.appendChild(btn('75%', t('visual_editor.width_75', '75% width'), function () { setImageWidth(editorInstance, 75); }));
    toolbar.appendChild(btn('100%', t('visual_editor.width_100', '100% width'), function () { setImageWidth(editorInstance, 100); }));
    toolbar.appendChild(sep());

    toolbar.appendChild(btn(
      'Alt',
      t('visual_editor.edit_alt', 'Edit alt text'),
      function () { editImageAlt(editorInstance); }
    ));
    toolbar.appendChild(btn(
      t('visual_editor.caption', 'Caption'),
      t('visual_editor.toggle_caption', 'Toggle caption'),
      function () { toggleImageCaption(editorInstance); }
    ));
    toolbar.appendChild(btn(
      t('visual_editor.link', 'Link'),
      t('visual_editor.edit_link', 'Edit link'),
      function () { editImageLink(editorInstance); }
    ));
    toolbar.appendChild(btn(
      t('visual_editor.replace', 'Replace'),
      t('visual_editor.replace_image', 'Replace image'),
      function () { replaceImage(editorInstance); }
    ));
    toolbar.appendChild(btn(
      t('visual_editor.delete', 'Delete'),
      t('visual_editor.delete_image', 'Delete image'),
      function () { deleteSelectedImage(editorInstance); }
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

  function uploadImage(editorInstance, file, targetBlock) {
    if (!isImageFile(file)) {
      showErrorToast(editorInstance, t('visual_editor.invalid_file_type', 'Invalid file type. Allowed: JPEG, PNG, WebP, GIF'));
      return Promise.reject(new Error('Invalid file type'));
    }

    if (file.size > editorInstance._options.maxImageSize) {
      showErrorToast(editorInstance, t('visual_editor.file_too_large', 'File too large. Max size:') + ' ' + formatFileSize(editorInstance._options.maxImageSize));
      return Promise.reject(new Error('File too large'));
    }

    return getImageDimensions(file).then(function (dims) {
      if (dims.width > 8000 || dims.height > 8000) {
        showErrorToast(editorInstance, t('visual_editor.image_too_large', 'Image resolution too high. Max: 8000\u00D78000'));
        throw new Error('Image too large');
      }
      return doUpload(editorInstance, file, targetBlock);
    }).catch(function (err) {
      if (targetBlock && targetBlock.parentNode) {
        targetBlock.parentNode.removeChild(targetBlock);
      }
      editorInstance._sync();
      editorInstance._updateEmptyState();
      showErrorToast(editorInstance, err.message || t('visual_editor.upload_error', 'Upload failed'));
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
      var src = data && (data.url || data.src || data.data && (data.data.url || data.data.src));
      if (!src) {
        throw new Error(t('visual_editor.upload_no_url', 'Upload succeeded but no URL returned'));
      }
      var alt = data.alt || file.name || '';
      var blockId = placeholderBlock ? placeholderBlock.getAttribute('data-block-id') : generateId();
      var width = data.width ? Number(data.width) : 0;
      var widthPct = width > 0 ? clamp(width, editorInstance._options.minImageWidth / editorInstance._content.clientWidth * 100, 100) : 75;

      var newBlock = createImageBlock(src, alt, widthPct, blockId);
      if (placeholderBlock && placeholderBlock.parentNode) {
        placeholderBlock.parentNode.replaceChild(newBlock, placeholderBlock);
      } else {
        editorInstance._content.appendChild(newBlock);
      }

      editorInstance._history.push(editorInstance._content.innerHTML, true);
      editorInstance._sync();
      selectImageBlock(editorInstance, newBlock);
      return newBlock;
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
      uploadUrl: textarea.getAttribute('data-crm-visual-editor-upload-url') || '/api/index.php?route=api/v1/visual-editor/upload-image',
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

    var wrapper = document.createElement('div');
    wrapper.className = 'crm-ve-editor crm-ve-wrapper';
    if (this._options.readonly) {
      wrapper.classList.add('is-readonly');
    }
    this._wrapper = wrapper;

    var toolbar = document.createElement('div');
    toolbar.className = 'crm-ve-toolbar';
    this._toolbar = toolbar;

    var content = document.createElement('div');
    content.className = 'crm-ve-content';
    content.setAttribute('contenteditable', String(!this._options.readonly));
    content.setAttribute('role', 'textbox');
    content.setAttribute('aria-multiline', 'true');
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
      for (var i = 0; i < files.length; i++) {
        var placeholder = createUploadingPlaceholder(generateId());
        if (i === 0 && target && target.parentNode) {
          target.parentNode.replaceChild(placeholder, target);
          deselectImage(self2);
        } else {
          self2._content.appendChild(placeholder);
        }
        uploadImage(self2, files[i], placeholder).catch(function () {});
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

    function addBtn(label, title, cmd, value) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'crm-ve-toolbar-btn';
      btn.innerHTML = label;
      btn.title = title || '';
      if (typeof cmd === 'string') {
        btn.setAttribute('data-cmd', cmd);
      }
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        self._content.focus();
        if (typeof cmd === 'function') {
          cmd();
        } else if (cmd === 'formatBlock') {
          document.execCommand('formatBlock', false, '<' + value + '>');
        } else if (cmd === 'createLink') {
          self._execLink();
        } else if (cmd === 'insertImage') {
          self._execImageUpload();
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

    addBtn('<strong>B</strong>', t('visual_editor.bold', 'Bold'), 'bold');
    addBtn('<em>I</em>', t('visual_editor.italic', 'Italic'), 'italic');
    addBtn('<s>S</s>', t('visual_editor.strike', 'Strikethrough'), 'strikeThrough');
    addBtn('<code>&lt;/&gt;</code>', t('visual_editor.code', 'Inline code'), 'insertHTML', '<code>' + t('visual_editor.code', 'code') + '</code>');
    addBtn(
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
      t('visual_editor.link', 'Link'),
      'createLink'
    );
    addSep();
    addBtn('H2', t('visual_editor.heading2', 'Heading 2'), 'formatBlock', 'h2');
    addBtn('H3', t('visual_editor.heading3', 'Heading 3'), 'formatBlock', 'h3');
    addSep();
    addBtn(
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
      t('visual_editor.bullet_list', 'Bullet list'),
      'insertUnorderedList'
    );
    addBtn(
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><text x="3" y="10" font-size="10" fill="currentColor">1.</text><text x="3" y="16" font-size="10" fill="currentColor">2.</text><text x="2" y="22" font-size="10" fill="currentColor">3.</text></svg>',
      t('visual_editor.ordered_list', 'Ordered list'),
      'insertOrderedList'
    );
    addBtn(
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
      t('visual_editor.quote', 'Quote'),
      'formatBlock',
      'blockquote'
    );
    addBtn(
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
      t('visual_editor.code_block', 'Code block'),
      'formatBlock',
      'pre'
    );
    addSep();
    addBtn(
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
      t('visual_editor.image', 'Image'),
      'insertImage'
    );
    addSep();
    addBtn(
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><polyline points="12 7 12 12 16 14"/></svg>',
      t('visual_editor.undo', 'Undo'),
      function () { self.undo(); }
    );
    addBtn(
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><polyline points="12 7 12 12 16 14"/></svg>',
      t('visual_editor.redo', 'Redo'),
      function () { self.redo(); }
    );
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

  Editor.prototype._bindEvents = function () {
    var self = this;
    var content = this._content;

    content.addEventListener('input', function () {
      self._sync();
      self._history.push(content.innerHTML, false);
      self._updateActiveButtons();
      self._updateEmptyState();
    });

    content.addEventListener('blur', function () {
      self._wrapper.classList.remove('is-focused');
      self._sync();
    });

    content.addEventListener('focus', function () {
      self.refreshFromTextarea(false);
      self._wrapper.classList.add('is-focused');
    });

    content.addEventListener('keydown', function (e) {
      self._onKeyDown(e);
    });

    content.addEventListener('click', function (e) {
      var imageBlock = e.target.closest('.crm-ve-image-block');
      if (imageBlock) {
        selectImageBlock(self, imageBlock);
        e.preventDefault();
        return;
      }
      deselectImage(self);
      self._updateActiveButtons();
    });

    content.addEventListener('paste', function (e) {
      self._onPaste(e);
    });

    content.addEventListener('drop', function (e) {
      self._onDrop(e);
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

  Editor.prototype._onKeyDown = function (e) {
    var self = this;

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

    var files = clipboard.files;
    if (files && files.length > 0) {
      for (var i = 0; i < files.length; i++) {
        if (isImageFile(files[i])) {
          var placeholder = createUploadingPlaceholder(generateId());
          self._content.appendChild(placeholder);
          uploadImage(self, files[i], placeholder).catch(function () {});
        }
      }
      return;
    }

    var html = clipboard.getData('text/html');
    var text = clipboard.getData('text/plain');

    if (html) {
      var containsDataImage = html.indexOf('data:image/') !== -1;
      if (containsDataImage) {
        var temp = document.createElement('div');
        temp.innerHTML = html;
        var imgs = temp.querySelectorAll('img[src^="data:image/"]');
        for (var j = 0; j < imgs.length; j++) {
          var src = imgs[j].getAttribute('src');
          if (src) {
            try {
              var blob = dataURLtoBlob(src);
              var ext = src.split(';')[0].split('/')[1] || 'png';
              var file = new File([blob], 'pasted_image.' + ext, { type: blob.type });
              var ph = createUploadingPlaceholder(generateId());
              self._content.appendChild(ph);
              uploadImage(self, file, ph).catch(function () {});
            } catch (ex) { /* skip invalid data URLs */ }
          }
        }
        imgs.forEach(function (img) { if (img.parentNode) img.parentNode.removeChild(img); });
        html = temp.innerHTML;
      }
      var sanitized = sanitizeHtml(html);
      if (sanitized) {
        document.execCommand('insertHTML', false, sanitized);
        self._convertOrphanedImages();
        self._sync();
        self._history.push(self._content.innerHTML, true);
        return;
      }
    }

    if (text) {
      var paragraphs = htmlToParagraphs(text);
      document.execCommand('insertHTML', false, paragraphs);
      self._convertOrphanedImages();
      self._sync();
      self._history.push(self._content.innerHTML, true);
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
        self._content.appendChild(placeholder);
        uploadImage(self, files[i], placeholder).catch(function () {});
      }
    }
  };

  Editor.prototype._sync = function () {
    if (this._destroyed) return;
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

  Editor.prototype._updateActiveButtons = function () {
    var buttons = this._toolbar.querySelectorAll('.crm-ve-toolbar-btn');
    buttons.forEach(function (btn) {
      var cmd = btn.getAttribute('data-cmd');
      if (!cmd) return;
      try {
        var state = document.queryCommandState(cmd);
        btn.classList.toggle('is-active', !!state);
      } catch (e) { /* queryCommandState not supported for this cmd */ }
    });
  };

  Editor.prototype._convertOrphanedImages = function () {
    var imgs = this._content.querySelectorAll('img:not(.crm-ve-image)');
    for (var i = imgs.length - 1; i >= 0; i--) {
      var img = imgs[i];
      if (img.closest('.crm-ve-image-block')) continue;
      var src = img.getAttribute('src') || '';
      if (!src || src.indexOf('data:') === 0) {
        if (img.parentNode) img.parentNode.removeChild(img);
        continue;
      }
      var alt = img.getAttribute('alt') || '';
      var block = createImageBlock(src, alt, 75, generateId());
      img.parentNode.replaceChild(block, img);
    }
  };

  // ---------------------------------------------------------------------------
  //  Output / Input conversion (image blocks <-> clean HTML)
  // ---------------------------------------------------------------------------

  Editor.prototype._toOutputHtml = function () {
    var container = document.createElement('div');
    container.innerHTML = this._content.innerHTML;

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

      var block = createImageBlock(src, alt, widthPct, bid);
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

    return container.innerHTML;
  };

  // ---------------------------------------------------------------------------
  //  Public API
  // ---------------------------------------------------------------------------

  Editor.prototype.getValue = function () {
    return this._toOutputHtml();
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
    var current = this._content.innerHTML;
    var prev = this._history.undo(current);
    if (prev !== null && prev !== undefined) {
      this._content.innerHTML = prev;
      this._sync();
      return true;
    }
    return false;
  };

  Editor.prototype.redo = function () {
    var current = this._content.innerHTML;
    var next = this._history.redo(current);
    if (next !== null && next !== undefined) {
      this._content.innerHTML = next;
      this._sync();
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
    );

    var style = document.createElement('style');
    style.id = 'crm-ve-styles';
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
      if (typeof console !== 'undefined') {
        console.error('[CRM.VisualEditor] Failed to init:', e);
      }
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
  }

  // Auto-init on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(); });
  } else {
    init();
  }

  return {
    Editor: Editor,
    init: init,
    instances: instances,
    getInstances: function () { return instances; },
    refreshEditors: function (scope, force) { refreshEditors(scope || document, !!force); },
    sanitizeHtml: sanitizeHtml,
    renderReadonly: renderReadonly
  };

})();
