/**
 * TropaTT frontend sanitization helpers.
 *
 * Defense-in-depth for sites that render user-supplied HTML inside
 *   data-bs-html="true" Bootstrap popovers/tooltips, or anywhere an
 *   `.innerHTML = X` assignment is unavoidable.
 *
 * Strategy:
 *   1) Parse the input through an inert <template> element (DOM parser, not regex)
 *   2) Strip dangerous elements (script, iframe, object, embed, link, meta, style, form)
 *   3) Strip dangerous attributes starting with "on" (onclick, onerror, ...)
 *      and values starting with "javascript:" / "vbscript:"
 *   4) Return the cleaned HTML string
 *
 * For very high-risk surfaces prefer DOMPurify (npm install dompurify) and
 * call DOMPurify.sanitize(dirty, { ALLOWED_TAGS, ALLOWED_ATTR }).
 */
(function (root) {
    'use strict';

    var FORBIDDEN_ELEMENTS = [
        'script', 'iframe', 'object', 'embed',
        'link', 'meta', 'style', 'form', 'input', 'button', 'svg', 'math',
        'base', 'frame', 'frameset', 'applet'
    ];
    var FORBIDDEN_ATTR_PREFIXES = ['on'];
    // Cycle-7 close-out: restored 'data:' blanket strip. Earlier "narrow
    // allowlist" iteration had a bug that left `data:` URIs unfiltered,
    // making `<a href="data:text/html,<script>...</script>">` reachable.
    // Per code-reviewer verdict (a) and protocol "reject over sanitize":
    // blanket strip here is the conservative safer default; cosmetic
    // inline-image breakage is acceptable trade-off vs the XSS bypass.
    var FORBIDDEN_ATTR_VALUE_PREFIXES = ['javascript:', 'data:', 'vbscript:'];

    function sanitizeHtml(dirty) {
        if (dirty == null) return '';
        var str = String(dirty);

        // Fast path: trusted static string with no HTML characters
        if (str.indexOf('<') === -1 && str.indexOf('&') === -1) {
            return str;
        }

        var template = document.createElement('template');
        template.innerHTML = str;

        removeForbiddenElements(template.content);
        stripDangerousAttributes(template.content);

        return template.innerHTML;
    }

    function removeForbiddenElements(rootNode) {
        var toRemove = [];
        var walker = document.createTreeWalker(rootNode, NodeFilter.SHOW_ELEMENT);
        var node = walker.currentNode;
        while (node) {
            var tag = node.tagName ? node.tagName.toLowerCase() : '';
            if (FORBIDDEN_ELEMENTS.indexOf(tag) !== -1) {
                toRemove.push(node);
            }
            node = walker.nextNode();
        }
        for (var i = 0; i < toRemove.length; i++) {
            var n = toRemove[i];
            if (n.parentNode) n.parentNode.removeChild(n);
        }
    }

    function stripDangerousAttributes(rootNode) {
        var walker = document.createTreeWalker(rootNode, NodeFilter.SHOW_ELEMENT);
        var node = walker.currentNode;
        while (node) {
            if (node.attributes) {
                var attrs = Array.prototype.slice.call(node.attributes);
                for (var i = 0; i < attrs.length; i++) {
                    var a = attrs[i];
                    var name = (a.name || '').toLowerCase();
                    var value = String(a.value || '');
                    if (FORBIDDEN_ATTR_PREFIXES.indexOf(name.substring(0, 2)) === 0) {
                        node.removeAttribute(name);
                        continue;
                    }
                    if (name === 'href' || name === 'src' || name === 'action' || name === 'formaction' || name === 'xlink:href') {
                        var trimmed = value.trim().toLowerCase();
                        for (var j = 0; j < FORBIDDEN_ATTR_VALUE_PREFIXES.length; j++) {
                            if (trimmed.indexOf(FORBIDDEN_ATTR_VALUE_PREFIXES[j]) === 0) {
                                node.removeAttribute(name);
                                break;
                            }
                        }
                    }
                }
            }
            node = walker.nextNode();
        }
    }

    /**
     * Convenience for Bootstrap popover/tooltip data-bs-content.
     * Returns sanitized HTML ready to be assigned.
     */
    function sanitizeForPopover(dirtyHtml) {
        return sanitizeHtml(dirtyHtml);
    }

    /**
     * Replace `element.innerHTML = dirtyHtml` with this helper.
     * Adds a data-sanitized attribute so future audits can find dynamically
     * sanitized surfaces versus raw innerHTML.
     */
    function setSafeHtml(element, dirtyHtml) {
        if (!element) return;
        element.innerHTML = sanitizeHtml(dirtyHtml);
        element.setAttribute('data-sanitized', '1');
    }

    root.TropaTT = root.TropaTT || {};
    root.TropaTT.sanitize = {
        sanitizeHtml: sanitizeHtml,
        sanitizeForPopover: sanitizeForPopover,
        setSafeHtml: setSafeHtml
    };
})(typeof window !== 'undefined' ? window : globalThis);
