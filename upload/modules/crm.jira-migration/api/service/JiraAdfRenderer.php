<?php
declare(strict_types=1);

namespace Module\Crm\JiraMigration\Service;

/**
 * Handles Atlassian Document Format (ADF) conversion to plain text and HTML.
 */
final class JiraAdfRenderer
{
    /**
     * Convert ADF JSON to plain text for CRM task description.
     */
    public function toPlainText(string|array $adf): string
    {
        if (is_string($adf)) {
            $decoded = json_decode($adf, true);
            if (!is_array($decoded)) {
                return $adf;
            }
            $adf = $decoded;
        }

        if (!isset($adf['content']) || !is_array($adf['content'])) {
            return $this->extractTextFromUnknown($adf);
        }

        $parts = [];
        foreach ($adf['content'] as $node) {
            $parts[] = $this->renderNodeToText($node);
        }

        return trim(implode("\n\n", $parts));
    }

    /**
     * Convert ADF JSON to sanitized HTML for preview/optional knowledge artifacts.
     */
    public function toHtml(string|array $adf): string
    {
        if (is_string($adf)) {
            $decoded = json_decode($adf, true);
            if (!is_array($decoded)) {
                return htmlspecialchars($adf, ENT_QUOTES, 'UTF-8');
            }
            $adf = $decoded;
        }

        if (!isset($adf['content']) || !is_array($adf['content'])) {
            return htmlspecialchars($this->extractTextFromUnknown($adf), ENT_QUOTES, 'UTF-8');
        }

        $parts = [];
        foreach ($adf['content'] as $node) {
            $parts[] = $this->renderNodeToHtml($node);
        }

        return trim(implode("\n", $parts));
    }

    /**
     * Convert a single ADF text value to plain text (handles ADF inline format).
     */
    public function toPlainTextInline(string|array|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_string($value)) {
            return $value;
        }
        return $this->toPlainText($value);
    }

    private function renderNodeToText(array $node): string
    {
        $type = $node['type'] ?? 'unknown';
        $content = $node['content'] ?? [];

        switch ($type) {
            case 'doc':
            case 'paragraph':
                return $this->renderInlineContent($content);

            case 'heading':
                return $this->renderInlineContent($content);

            case 'bulletList':
            case 'orderedList':
                $items = [];
                foreach ($content as $item) {
                    $items[] = '  • ' . $this->renderInlineContent($item['content'] ?? []);
                }
                return implode("\n", $items);

            case 'listItem':
                return $this->renderInlineContent($content);

            case 'codeBlock':
                return $this->renderInlineContent($content);

            case 'blockquote':
            case 'panel':
                return $this->renderInlineContent($content);

            case 'rule':
                return '---';

            case 'table':
                $rows = [];
                foreach ($content as $row) {
                    $cells = [];
                    foreach ($row['content'] ?? [] as $cell) {
                        $cells[] = $this->renderInlineContent($cell['content'] ?? []);
                    }
                    $rows[] = implode(' | ', $cells);
                }
                return implode("\n", $rows);

            case 'tableRow':
                $cells = [];
                foreach ($content as $cell) {
                    $cells[] = $this->renderInlineContent($cell['content'] ?? []);
                }
                return implode(' | ', $cells);

            default:
                return $this->renderInlineContent($content);
        }
    }

    private function renderInlineContent(array $content): string
    {
        $parts = [];
        foreach ($content as $node) {
            $parts[] = $this->renderInlineNode($node);
        }
        return trim(implode('', $parts));
    }

    private function renderInlineNode(array $node): string
    {
        $type = $node['type'] ?? 'text';
        $text = $node['text'] ?? '';
        $content = $node['content'] ?? [];
        $marks = $node['marks'] ?? [];

        switch ($type) {
            case 'text':
                return $text;

            case 'inlineCard':
            case 'applicationCard':
                return isset($node['attrs']['url']) ? $node['attrs']['url'] : '[link]';

            case 'hardBreak':
                return "\n";

            case 'mention':
                return '@' . ($node['attrs']['text'] ?? $node['attrs']['id'] ?? 'mentioned-user');

            case 'emoji':
                return $node['attrs']['text'] ?? '';

            case 'date':
                return $node['attrs']['text'] ?? '';

            case 'status':
                return '[' . ($node['attrs']['text'] ?? 'status') . ']';

            case 'placeholder':
                return '[' . ($node['attrs']['text'] ?? 'placeholder') . ']';

            case 'compound':
            case 'doc':
                return $this->renderInlineContent($content);

            default:
                return $this->renderInlineContent($content);
        }
    }

    private function renderNodeToHtml(array $node): string
    {
        $type = $node['type'] ?? 'unknown';
        $content = $node['content'] ?? [];
        $attrs = $node['attrs'] ?? [];

        switch ($type) {
            case 'doc':
                return $this->renderChildrenHtml($content);

            case 'paragraph':
                return '<p>' . $this->renderInlineHtml($content) . '</p>';

            case 'heading':
                $level = min(6, max(1, (int)($attrs['level'] ?? 1)));
                return "<h{$level}>" . $this->renderInlineHtml($content) . "</h{$level}>";

            case 'bulletList':
                $items = '';
                foreach ($content as $item) {
                    $items .= '<li>' . $this->renderInlineHtml($item['content'] ?? []) . '</li>';
                }
                return '<ul>' . $items . '</ul>';

            case 'orderedList':
                $order = isset($attrs['order']) ? ' start="' . (int)$attrs['order'] . '"' : '';
                $items = '';
                foreach ($content as $item) {
                    $items .= '<li>' . $this->renderInlineHtml($item['content'] ?? []) . '</li>';
                }
                return '<ol' . $order . '>' . $items . '</ol>';

            case 'listItem':
                return '<li>' . $this->renderInlineHtml($content) . '</li>';

            case 'codeBlock':
                $lang = isset($attrs['language']) ? ' class="language-' . htmlspecialchars($attrs['language']) . '"' : '';
                return '<pre' . $lang . '><code>' . htmlspecialchars($this->renderInlineContent($content)) . '</code></pre>';

            case 'blockquote':
                return '<blockquote>' . $this->renderChildrenHtml($content) . '</blockquote>';

            case 'rule':
                return '<hr>';

            case 'table':
                return '<table>' . $this->renderChildrenHtml($content) . '</table>';

            case 'tableRow':
                return '<tr>' . $this->renderChildrenHtml($content) . '</tr>';

            case 'tableCell':
            case 'tableHeader':
                $tag = $type === 'tableHeader' ? 'th' : 'td';
                return "<{$tag}>" . $this->renderChildrenHtml($content) . "</{$tag}>";

            case 'panel':
                $panelType = $attrs['panelType'] ?? 'info';
                return '<blockquote class="callout callout-' . htmlspecialchars($panelType) . '">' . $this->renderChildrenHtml($content) . '</blockquote>';

            case 'expand':
                $title = htmlspecialchars($attrs['title'] ?? 'Expand');
                return '<details><summary>' . $title . '</summary>' . $this->renderChildrenHtml($content) . '</details>';

            case 'mediaGroup':
            case 'media':
                // Media nodes - render as placeholder
                return '';

            default:
                return $this->renderInlineHtml($content);
        }
    }

    private function renderInlineHtml(array $content): string
    {
        $parts = [];
        foreach ($content as $node) {
            $parts[] = $this->renderInlineNodeHtml($node);
        }
        return implode('', $parts);
    }

    private function renderInlineNodeHtml(array $node): string
    {
        $type = $node['type'] ?? 'text';
        $text = $node['text'] ?? '';
        $content = $node['content'] ?? [];
        $marks = $node['marks'] ?? [];

        $result = '';

        switch ($type) {
            case 'text':
                $result = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
                break;

            case 'inlineCard':
            case 'applicationCard':
                $url = htmlspecialchars($node['attrs']['url'] ?? '#', ENT_QUOTES, 'UTF-8');
                return '<a href="' . $url . '" rel="noopener noreferrer" target="_blank">' . $url . '</a>';

            case 'hardBreak':
                return '<br>';

            case 'mention':
                $id = htmlspecialchars($node['attrs']['id'] ?? '', ENT_QUOTES, 'UTF-8');
                $text = htmlspecialchars($node['attrs']['text'] ?? 'mentioned-user', ENT_QUOTES, 'UTF-8');
                return '<span class="confluence-mention" data-account-id="' . $id . '">@' . $text . '</span>';

            case 'emoji':
                return $node['attrs']['text'] ?? '';

            case 'date':
                return $node['attrs']['text'] ?? '';

            case 'status':
                $style = htmlspecialchars($node['attrs']['style'] ?? 'grey', ENT_QUOTES, 'UTF-8');
                $statusText = htmlspecialchars($node['attrs']['text'] ?? 'status', ENT_QUOTES, 'UTF-8');
                return '<span class="confluence-status confluence-status-' . $style . '">' . $statusText . '</span>';

            case 'placeholder':
                $pText = htmlspecialchars($node['attrs']['text'] ?? 'placeholder', ENT_QUOTES, 'UTF-8');
                return '<span class="confluence-placeholder">[' . $pText . ']</span>';

            default:
                $result = $this->renderInlineHtml($content);
                break;
        }

        // Apply marks
        foreach ($marks as $mark) {
            $markType = $mark['type'] ?? '';
            $markAttrs = $mark['attrs'] ?? [];
            switch ($markType) {
                case 'strong':
                    $result = '<strong>' . $result . '</strong>';
                    break;
                case 'em':
                    $result = '<em>' . $result . '</em>';
                    break;
                case 'underline':
                    $result = '<u>' . $result . '</u>';
                    break;
                case 'strike':
                    $result = '<s>' . $result . '</s>';
                    break;
                case 'code':
                    $result = '<code>' . $result . '</code>';
                    break;
                case 'link':
                    $href = htmlspecialchars($markAttrs['href'] ?? '#', ENT_QUOTES, 'UTF-8');
                    $result = '<a href="' . $href . '" rel="noopener noreferrer" target="_blank">' . $result . '</a>';
                    break;
                case 'subsup':
                    $tag = ($markAttrs['type'] ?? 'sub') === 'sup' ? 'sup' : 'sub';
                    $result = "<{$tag}>" . $result . "</{$tag}>";
                    break;
                case 'textColor':
                    $color = htmlspecialchars($markAttrs['color'] ?? 'inherit', ENT_QUOTES, 'UTF-8');
                    $result = '<span style="color:' . $color . '">' . $result . '</span>';
                    break;
                case 'backgroundColor':
                    $bgColor = htmlspecialchars($markAttrs['color'] ?? 'inherit', ENT_QUOTES, 'UTF-8');
                    $result = '<span style="background-color:' . $bgColor . '">' . $result . '</span>';
                    break;
                default:
                    break;
            }
        }

        return $result;
    }

    private function renderChildrenHtml(array $content): string
    {
        $result = '';
        foreach ($content as $child) {
            $result .= $this->renderNodeToHtml($child);
        }
        return $result;
    }

    private function extractTextFromUnknown(array $data): string
    {
        $parts = [];
        array_walk_recursive($data, function ($value, $key) use (&$parts) {
            if (is_string($value) && !in_array($key, ['type', 'attrs'], true)) {
                $parts[] = $value;
            }
        });
        return implode(' ', $parts);
    }
}
