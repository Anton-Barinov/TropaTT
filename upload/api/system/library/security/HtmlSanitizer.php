<?php
declare(strict_types=1);

namespace Api\System\Library\Security;

use DOMDocument;
use DOMNode;
use DOMElement;

/**
 * Sanitizes user-authored rich HTML without requiring Composer extensions.
 *
 * The browser editor performs the same kind of filtering for presentation, but
 * persisted comments must be safe even when written through REST or MCP.
 */
final class HtmlSanitizer
{
    /** @var array<string,true> */
    private const ALLOWED_TAGS = [
        'p' => true,
        'br' => true,
        'strong' => true,
        'b' => true,
        'em' => true,
        'i' => true,
        's' => true,
        'u' => true,
        'code' => true,
        'pre' => true,
        'blockquote' => true,
        'ul' => true,
        'ol' => true,
        'li' => true,
        'a' => true,
        'h1' => true,
        'h2' => true,
        'h3' => true,
        'figure' => true,
        'figcaption' => true,
        'img' => true,
        'details' => true,
        'summary' => true,
        'input' => true,
        'span' => true,
        'hr' => true,
        'table' => true,
        'thead' => true,
        'tbody' => true,
        'tr' => true,
        'td' => true,
        'th' => true,
    ];

    /** @var array<string,true> */
    private const REMOVE_WITH_CONTENT = [
        'script' => true,
        'style' => true,
        'iframe' => true,
        'object' => true,
        'embed' => true,
        'form' => true,
        'button' => true,
        'svg' => true,
        'math' => true,
        'template' => true,
        'noscript' => true,
    ];

    public function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        // Keep ordinary comments byte-for-byte compatible with the existing
        // plain-text renderer. Only parse strings that contain an HTML tag.
        if (!preg_match('/<\s*\/?\s*[a-z][^>]*>/i', $html)) {
            return $html;
        }

        // DOMDocument is available in normal PHP builds, but the CRM also
        // supports constrained shared hosting. When ext-dom is unavailable
        // or fails to load, use a robust tag-allowlist sanitizer fallback
        // instead of destroying markup and welding words together.
        if (!class_exists(DOMDocument::class)) {
            return $this->sanitizeFallback($html);
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        // Include both http-equiv and charset meta for libxml UTF-8 compatibility
        $wrapped = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><meta charset="UTF-8"></head><body><div id="crm-comment-root">'
            . $html
            . '</div></body></html>';
        $loaded = $document->loadHTML($wrapped, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $this->sanitizeFallback($html);
        }

        $roots = $document->getElementsByTagName('div');
        $root = null;
        foreach ($roots as $candidate) {
            if ($candidate instanceof DOMElement && $candidate->getAttribute('id') === 'crm-comment-root') {
                $root = $candidate;
                break;
            }
        }
        if (!$root) {
            $root = $roots->item(0);
        }
        if (!$root) {
            return $this->sanitizeFallback($html);
        }

        $this->sanitizeChildren($root);

        $result = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
    }

    /**
     * Robust regex-based fallback sanitizer for environments without ext-dom
     * or when DOMDocument::loadHTML fails on malformed input.
     * Preserves all allowed rich-text tags, structure, and safe attributes.
     */
    public function sanitizeFallback(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        // 1. Strip dangerous tags along with their contents
        $cleaned = preg_replace('/<(?:script|style|iframe|object|embed|form|button|svg|math|template|noscript)[^>]*>.*?<\/(?:script|style|iframe|object|embed|form|button|svg|math|template|noscript)>/is', '', $html);
        $cleaned = preg_replace('/<(?:script|style|iframe|object|embed|form|button|svg|math|template|noscript)[^>]*\/?>/is', '', (string)$cleaned);

        // 2. Strip comments
        $cleaned = preg_replace('/<!--.*?-->/s', '', (string)$cleaned);

        // 3. Allow-list tags (preserves allowed tags, strips disallowed while keeping inner text)
        $allowedTags = '<p><br><strong><b><em><i><s><u><code><pre><blockquote><ul><ol><li><a><h1><h2><h3><h4><h5><h6><figure><figcaption><img><details><summary><input><span><hr><table><thead><tbody><tr><td><th>';
        $cleaned = strip_tags((string)$cleaned, $allowedTags);

        // 4. Sanitize attributes on each element
        $cleaned = preg_replace_callback('/<([a-z0-9]+)([^>]*)>/i', function (array $matches): string {
            $tag = strtolower($matches[1]);
            $rawAttrs = $matches[2];
            if (trim($rawAttrs) === '') {
                return '<' . $tag . '>';
            }

            preg_match_all('/([a-z0-9_-]+)(?:\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27|([^\s>]+)))?/i', $rawAttrs, $attrMatches, PREG_SET_ORDER);
            $safeAttrs = [];
            foreach ($attrMatches as $m) {
                $attrName = strtolower($m[1]);
                $attrVal = $m[2] ?? $m[3] ?? $m[4] ?? '';
                if (str_starts_with($attrName, 'on')) {
                    continue;
                }

                if ($tag === 'a') {
                    if ($attrName === 'href') {
                        $safeHref = $this->safeUrl($attrVal, true);
                        if ($safeHref !== null) {
                            $safeAttrs[] = 'href="' . htmlspecialchars($safeHref, ENT_QUOTES, 'UTF-8') . '"';
                            if (preg_match('/^https?:\/\//i', $safeHref)) {
                                $safeAttrs[] = 'target="_blank" rel="noopener noreferrer"';
                            }
                        }
                    } elseif (in_array($attrName, ['title', 'target', 'rel'], true)) {
                        $safeAttrs[] = $attrName . '="' . htmlspecialchars($attrVal, ENT_QUOTES, 'UTF-8') . '"';
                    }
                } elseif ($tag === 'img') {
                    if ($attrName === 'src') {
                        $safeSrc = $this->safeUrl($attrVal, false);
                        if ($safeSrc !== null) {
                            $safeAttrs[] = 'src="' . htmlspecialchars($safeSrc, ENT_QUOTES, 'UTF-8') . '"';
                        }
                    } elseif (in_array($attrName, ['alt', 'title'], true)) {
                        $safeAttrs[] = $attrName . '="' . htmlspecialchars($attrVal, ENT_QUOTES, 'UTF-8') . '"';
                    }
                } elseif ($tag === 'figure') {
                    if (in_array($attrName, ['data-align', 'data-width'], true)) {
                        $safeAttrs[] = $attrName . '="' . htmlspecialchars($attrVal, ENT_QUOTES, 'UTF-8') . '"';
                    } elseif ($attrName === 'style') {
                        $safeStyle = $this->sanitizeStyleString($attrVal);
                        if ($safeStyle !== '') {
                            $safeAttrs[] = 'style="' . htmlspecialchars($safeStyle, ENT_QUOTES, 'UTF-8') . '"';
                        }
                    }
                } elseif ($tag === 'figcaption') {
                    if (in_array($attrName, ['class', 'contenteditable'], true)) {
                        $safeAttrs[] = $attrName . '="' . htmlspecialchars($attrVal, ENT_QUOTES, 'UTF-8') . '"';
                    }
                } elseif ($tag === 'span') {
                    if (in_array($attrName, ['class', 'role', 'tabindex', 'data-mention-type', 'data-mention-id', 'data-checked', 'aria-checked', 'contenteditable'], true)) {
                        $safeAttrs[] = $attrName . '="' . htmlspecialchars($attrVal, ENT_QUOTES, 'UTF-8') . '"';
                    }
                } elseif ($tag === 'input') {
                    if (in_array($attrName, ['type', 'disabled', 'data-checked', 'class'], true)) {
                        $safeAttrs[] = $attrName . '="' . htmlspecialchars($attrVal, ENT_QUOTES, 'UTF-8') . '"';
                    }
                } elseif (in_array($tag, ['table', 'thead', 'tbody', 'tr', 'td', 'th'], true)) {
                    if (in_array($attrName, ['class', 'colspan', 'rowspan'], true)) {
                        $safeAttrs[] = $attrName . '="' . htmlspecialchars($attrVal, ENT_QUOTES, 'UTF-8') . '"';
                    }
                } elseif (in_array($attrName, ['class', 'data-checked'], true)) {
                    $safeAttrs[] = $attrName . '="' . htmlspecialchars($attrVal, ENT_QUOTES, 'UTF-8') . '"';
                }
            }

            if ($tag === 'img' && !preg_grep('/^src=/i', $safeAttrs)) {
                return '';
            }

            return '<' . $tag . ($safeAttrs !== [] ? ' ' . implode(' ', $safeAttrs) : '') . '>';
        }, (string)$cleaned);

        return trim((string)$cleaned);
    }

    /**
     * Converts rich HTML into clean, readable plain text.
     * Unlike bare strip_tags(), block element boundaries are preserved as newlines
     * and figures/placeholders are cleanly excised without leaking internal filenames.
     */
    public static function toPlainText(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        // Remove script, style, and figure (including figcaption) so image placeholders don't leak
        $cleaned = preg_replace('/<(?:script|style|figure)[^>]*>.*?<\/(?:script|style|figure)>/is', '', $html);
        $cleaned = preg_replace('/<(?:script|style|figure)[^>]*\/?>/is', '', (string)$cleaned);

        // Replace closing block tags with newline
        $cleaned = preg_replace('/<\/(?:p|div|h[1-6]|li|tr|blockquote|pre)>\s*/iu', "\n", (string)$cleaned);
        $cleaned = preg_replace('/<br\s*\/?>/iu', "\n", (string)$cleaned);

        $plain = strip_tags((string)$cleaned);
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/[ \t]+/u', ' ', $plain);
        $plain = preg_replace('/\n{3,}/u', "\n\n", (string)$plain);
        return trim((string)$plain);
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        $children = iterator_to_array($parent->childNodes);
        foreach ($children as $child) {
            if ($child->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (isset(self::REMOVE_WITH_CONTENT[$tag])) {
                $parent->removeChild($child);
                continue;
            }

            if (!isset(self::ALLOWED_TAGS[$tag])) {
                $this->sanitizeChildren($child);
                while ($child->firstChild) {
                    $parent->insertBefore($child->firstChild, $child);
                }
                $parent->removeChild($child);
                continue;
            }

            // Unwrap spans that contain only whitespace (paste artifacts), but preserve todo checkboxes and mentions
            $spanClass = $child->getAttribute('class');
            if ($tag === 'span' && trim($child->textContent) === '' && !str_contains($spanClass, 'crm-ve-todo-cb') && !str_contains($spanClass, 'crm-ve-mention')) {
                while ($child->firstChild) {
                    $parent->insertBefore($child->firstChild, $child);
                }
                $parent->removeChild($child);
                continue;
            }

            $this->sanitizeAttributes($child, $tag);
            $this->sanitizeChildren($child);
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = match ($tag) {
            'a' => ['href', 'title', 'target', 'rel'],
            'img' => ['src', 'alt'],
            'figure' => ['data-align', 'data-width', 'style'],
            'figcaption' => ['class', 'contenteditable'],
            'details', 'summary' => ['class'],
            'input' => ['type', 'disabled', 'data-checked', 'class'],
            'span' => ['class', 'role', 'tabindex', 'data-mention-type', 'data-mention-id', 'data-checked', 'aria-checked', 'contenteditable'],
            'ul', 'ol', 'li' => ['class', 'data-checked'],
            'pre', 'code', 'blockquote', 'p', 'h1', 'h2', 'h3' => ['class'],
            'hr' => ['class'],
            'table', 'thead', 'tbody', 'tr', 'td', 'th' => ['class', 'colspan', 'rowspan'],
            default => [],
        };

        $attributes = iterator_to_array($element->attributes);
        foreach ($attributes as $attribute) {
            $name = strtolower($attribute->name);
            if (!in_array($name, $allowed, true) || str_starts_with($name, 'on')) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($tag === 'a') {
            $href = $this->safeUrl($element->getAttribute('href'), true);
            if ($href === null) {
                $element->removeAttribute('href');
                $element->removeAttribute('target');
                $element->removeAttribute('rel');
            } else {
                $element->setAttribute('href', $href);
                if (preg_match('/^https?:\/\//i', $href)) {
                    $element->setAttribute('target', '_blank');
                    $element->setAttribute('rel', 'noopener noreferrer');
                } else {
                    $element->removeAttribute('target');
                    $element->removeAttribute('rel');
                }
            }
        } elseif ($tag === 'img') {
            $src = $this->safeUrl($element->getAttribute('src'), false);
            if ($src === null) {
                $element->parentNode?->removeChild($element);
                return;
            }
            $element->setAttribute('src', $src);
        } elseif ($tag === 'figure') {
            $align = $element->getAttribute('data-align');
            if (!in_array($align, ['left', 'center', 'right'], true)) {
                $element->removeAttribute('data-align');
            }

            $width = $element->getAttribute('data-width');
            if ($width !== '' && !preg_match('/^\d+(?:\.\d+)?$/', $width)) {
                $element->removeAttribute('data-width');
            } elseif ($width !== '') {
                $widthValue = (float)$width;
                if ($widthValue < 10 || $widthValue > 100) {
                    $element->removeAttribute('data-width');
                }
            }
            $this->sanitizeFigureStyle($element);
        }
    }

    public function sanitizeStyleString(string $style): string
    {
        if (trim($style) === '') {
            return '';
        }

        $safe = [];
        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if (!in_array($property, ['width', '--crm-ve-image-width', 'max-width'], true)) {
                continue;
            }
            if (preg_match('/^(\d+(?:\.\d+)?)%$/', $value, $match)) {
                $percent = min(100.0, max(10.0, (float)$match[1]));
                $safe[] = $property . ':' . rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.') . '%';
            } elseif (preg_match('/^(\d+(?:\.\d+)?)px$/', $value, $match)) {
                $pixels = min(2000, max(10, (int)$match[1]));
                $safe[] = $property . ':' . $pixels . 'px';
            }
        }

        return implode(';', $safe);
    }

    private function sanitizeFigureStyle(DOMElement $element): void
    {
        $style = $element->getAttribute('style');
        if ($style === '') {
            return;
        }

        $safeStyle = $this->sanitizeStyleString($style);
        if ($safeStyle === '') {
            $element->removeAttribute('style');
            return;
        }
        $element->setAttribute('style', $safeStyle);
    }

    private function safeUrl(string $raw, bool $allowMailAndTel): ?string
    {
        $value = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '' || preg_match('/[\x00-\x20]/', $value)) {
            return null;
        }

        // Allow data:image base64 URLs for embedded images
        if (preg_match('/^data:image\/(?:png|jpe?g|webp|gif);base64,[A-Za-z0-9+\/=\s]+$/i', $value)) {
            return preg_replace('/\s+/', '', $value);
        }

        if ($value[0] === '#' || ($value[0] === '/' && !str_starts_with($value, '//'))) {
            return $value;
        }
        if (preg_match('/^https?:\/\//i', $value)) {
            return $value;
        }
        if ($allowMailAndTel && preg_match('/^(?:mailto|tel):/i', $value)) {
            return $value;
        }

        // Relative URLs may not contain a scheme separator. This rejects
        // javascript:, data:, vbscript:, and obfuscated custom schemes.
        if (!str_starts_with($value, '//') && !str_contains($value, ':')) {
            return $value;
        }

        return null;
    }
}
