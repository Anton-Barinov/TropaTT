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
        // supports constrained shared hosting. Falling back to plain text is
        // safer than storing unfiltered markup if ext-dom is unavailable.
        if (!class_exists(DOMDocument::class)) {
            return trim(strip_tags($html));
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="crm-comment-root">'
            . $html
            . '</div></body></html>';
        $loaded = $document->loadHTML($wrapped, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return trim(strip_tags($html));
        }

        $roots = $document->getElementsByTagName('div');
        $root = $roots->item(0);
        if (!$root) {
            return trim(strip_tags($html));
        }

        $this->sanitizeChildren($root);

        $result = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
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
            'span' => ['class', 'data-mention-type', 'data-mention-id', 'contenteditable'],
            'hr' => ['style', 'class'],
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

    private function sanitizeFigureStyle(DOMElement $element): void
    {
        $style = $element->getAttribute('style');
        if ($style === '') {
            return;
        }

        $safe = [];
        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if (!in_array($property, ['width', '--crm-ve-image-width'], true)) {
                continue;
            }
            if (!preg_match('/^(\d+(?:\.\d+)?)%$/', $value, $match)) {
                continue;
            }
            $percent = min(100.0, max(10.0, (float)$match[1]));
            $safe[] = $property . ':' . rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.') . '%';
        }

        if ($safe === []) {
            $element->removeAttribute('style');
            return;
        }
        $element->setAttribute('style', implode(';', $safe));
    }

    private function safeUrl(string $raw, bool $allowMailAndTel): ?string
    {
        $value = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '' || preg_match('/[\x00-\x20]/', $value)) {
            return null;
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
