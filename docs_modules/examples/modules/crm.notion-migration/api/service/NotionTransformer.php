<?php
declare(strict_types=1);

namespace Module\Crm\NotionMigration\Service;

/**
 * Преобразует дерево блоков Notion в безопасный HTML базы знаний TropaTT.
 *
 * Результат: ['content_html' => string, 'content_text' => string, 'warnings' => array].
 */
final class NotionTransformer
{
    /**
     * @param array<int, array{id: string, type: string, data: array, children: array}> $blockTree
     * @param array<string, string> $pageMapping source_id => target_public_id
     */
    public function transform(array $blockTree, array $pageMapping = []): array
    {
        $warnings = [];
        $html = $this->renderBlocks($blockTree, $pageMapping, $warnings);
        $text = $this->extractText($blockTree);

        return [
            'content_html' => $html,
            'content_text' => $text,
            'warnings' => $warnings,
        ];
    }

    private function renderBlocks(array $blocks, array $pageMapping, array &$warnings): string
    {
        $out = [];
        $listBuffer = ['type' => null, 'items' => []];

        foreach ($blocks as $block) {
            $type = $block['type'];
            $data = $block['data'];
            $children = $block['children'];

            // Группируем соседние элементы списков в один <ul>/<ol>.
            if ($type === 'bulleted_list_item' || $type === 'numbered_list_item') {
                if ($listBuffer['type'] !== null && $listBuffer['type'] !== $type) {
                    $out[] = $this->flushList($listBuffer);
                }
                $listBuffer['type'] = $type;
                $listBuffer['items'][] = $this->renderListItem($type, $data, $children, $pageMapping, $warnings);
                continue;
            }

            if ($listBuffer['type'] !== null) {
                $out[] = $this->flushList($listBuffer);
            }

            $out[] = $this->renderBlock($block, $pageMapping, $warnings);
        }

        if ($listBuffer['type'] !== null) {
            $out[] = $this->flushList($listBuffer);
        }

        return implode("\n", array_filter($out, fn($s) => $s !== ''));
    }

    private function flushList(array &$listBuffer): string
    {
        $tag = $listBuffer['type'] === 'numbered_list_item' ? 'ol' : 'ul';
        $items = implode('', $listBuffer['items']);
        $listBuffer = ['type' => null, 'items' => []];
        return "<{$tag}>{$items}</{$tag}>";
    }

    private function renderListItem(string $type, array $data, array $children, array $pageMapping, array &$warnings): string
    {
        $inner = $this->renderRichText($data['rich_text'] ?? []);
        if ($children !== []) {
            $inner .= $this->renderBlocks($children, $pageMapping, $warnings);
        }
        return '<li>' . $inner . '</li>';
    }

    private function renderBlock(array $block, array $pageMapping, array &$warnings): string
    {
        $type = $block['type'];
        $data = $block['data'];
        $children = $block['children'];

        switch ($type) {
            case 'paragraph':
                $rich = $this->renderRichText($data['rich_text'] ?? []);
                return $rich !== '' ? '<p>' . $rich . '</p>' : '';

            case 'heading_1':
            case 'heading_2':
            case 'heading_3':
                $level = (int)substr($type, -1);
                $rich = $this->renderRichText($data['rich_text'] ?? []);
                return '<h' . $level . '>' . ($rich !== '' ? $rich : '&nbsp;') . '</h' . $level . '>';

            case 'to_do':
                $checked = !empty($data['checked']);
                $rich = $this->renderRichText($data['rich_text'] ?? []);
                $checkbox = '<input type="checkbox" disabled' . ($checked ? ' checked' : '') . '>';
                return '<p class="notion-todo">' . $checkbox . ' ' . $rich . '</p>';

            case 'toggle':
                $summary = $this->renderRichText($data['rich_text'] ?? []);
                $body = $children !== [] ? $this->renderBlocks($children, $pageMapping, $warnings) : '';
                return '<details><summary>' . $summary . '</summary>' . $body . '</details>';

            case 'quote':
                $rich = $this->renderRichText($data['rich_text'] ?? []);
                return '<blockquote>' . $rich . '</blockquote>';

            case 'callout':
                $rich = $this->renderRichText($data['rich_text'] ?? []);
                return '<div class="notion-callout">' . $rich . '</div>';

            case 'code':
                $language = (string)($data['language'] ?? '');
                $code = $this->renderRichText($data['rich_text'] ?? []);
                $class = $language !== '' ? ' class="language-' . $this->escape($language) . '"' : '';
                return '<pre><code' . $class . '>' . $code . '</code></pre>';

            case 'divider':
                return '<hr>';

            case 'image':
                $url = $this->imageUrl($data);
                if ($url === '') {
                    $warnings[] = ['type' => 'image', 'message' => 'Image block without URL skipped'];
                    return '';
                }
                return '<img src="' . $this->escape($url) . '" alt="" loading="lazy">';

            case 'table':
                if ($children === []) {
                    return '';
                }
                $rows = [];
                foreach ($children as $child) {
                    if ($child['type'] === 'table_row') {
                        $rows[] = $this->renderTableRow($child['data']);
                    }
                }
                return '<table class="notion-table"><tbody>' . implode('', $rows) . '</tbody></table>';

            case 'table_row':
                return $this->renderTableRow($data);

            case 'child_page':
                $title = (string)($data['title'] ?? '');
                $pageId = (string)($block['id'] ?? '');
                $href = $this->resolveChildHref($pageId, $pageMapping);
                if ($href !== '') {
                    return '<p class="notion-child-page"><a href="' . $this->escape($href) . '">' . $this->escape($title !== '' ? $title : 'Страница') . '</a></p>';
                }
                $warnings[] = ['type' => 'child_page', 'message' => 'Unresolved child page: ' . ($title !== '' ? $title : $pageId)];
                return '<p class="notion-child-page">' . $this->escape($title !== '' ? $title : 'Страница') . '</p>';

            case 'child_database':
                $title = (string)($data['title'] ?? '');
                return '<p class="notion-child-page">' . $this->escape($title !== '' ? $title : 'База данных') . '</p>';

            case 'bookmark':
            case 'link_preview':
                $url = (string)($data['url'] ?? '');
                $caption = $this->renderRichText($data['caption'] ?? []);
                if ($url === '') {
                    return '';
                }
                return '<p class="notion-bookmark"><a href="' . $this->escape($url) . '" rel="noopener noreferrer" target="_blank">' . ($caption !== '' ? $caption : $this->escape($url)) . '</a></p>';

            case 'file':
            case 'pdf':
                $url = $this->fileUrl($data);
                $name = (string)($data['name'] ?? '');
                if ($url === '') {
                    return '';
                }
                $label = $name !== '' ? $name : $url;
                return '<p class="notion-file"><a href="' . $this->escape($url) . '" rel="noopener noreferrer" target="_blank">' . $this->escape($label) . '</a></p>';

            case 'embed':
            case 'video':
                $url = (string)($data['url'] ?? '');
                if ($url === '') {
                    return '';
                }
                return '<p class="notion-embed"><a href="' . $this->escape($url) . '" rel="noopener noreferrer" target="_blank">' . $this->escape($url) . '</a></p>';

            case 'equation':
                $expr = (string)($data['expression'] ?? '');
                return '<p class="notion-equation">' . $this->escape($expr) . '</p>';

            default:
                $warnings[] = ['type' => 'unsupported_block', 'message' => 'Unsupported block type skipped: ' . $type];
                return '';
        }
    }

    private function renderTableRow(array $data): string
    {
        $cells = $data['cells'] ?? [];
        $tds = [];
        foreach ($cells as $cell) {
            $tds[] = '<td>' . $this->renderRichText(is_array($cell) ? $cell : []) . '</td>';
        }
        return '<tr>' . implode('', $tds) . '</tr>';
    }

    private function renderRichText(array $richText): string
    {
        $parts = [];
        foreach ($richText as $rt) {
            $rtType = (string)($rt['type'] ?? 'text');
            if ($rtType === 'text') {
                $content = (string)($rt['text']['content'] ?? '');
                $link = $rt['text']['link']['url'] ?? null;
                $html = $this->escape($content);
                $annotations = $rt['annotations'] ?? [];
                if (!empty($annotations['bold'])) $html = '<strong>' . $html . '</strong>';
                if (!empty($annotations['italic'])) $html = '<em>' . $html . '</em>';
                if (!empty($annotations['strikethrough'])) $html = '<s>' . $html . '</s>';
                if (!empty($annotations['underline'])) $html = '<u>' . $html . '</u>';
                if (!empty($annotations['code'])) $html = '<code>' . $html . '</code>';
                if (is_string($link) && $link !== '') {
                    $html = '<a href="' . $this->escape($link) . '" rel="noopener noreferrer" target="_blank">' . $html . '</a>';
                }
                $parts[] = $html;
            } elseif ($rtType === 'mention') {
                $parts[] = $this->renderMention($rt['mention'] ?? []);
            } elseif ($rtType === 'equation') {
                $parts[] = $this->escape((string)($rt['equation']['expression'] ?? ''));
            } else {
                $parts[] = $this->escape((string)($rt['plain_text'] ?? ''));
            }
        }
        return implode('', $parts);
    }

    private function renderMention(array $mention): string
    {
        $type = (string)($mention['type'] ?? '');
        switch ($type) {
            case 'user':
                $name = (string)($mention['user']['name'] ?? '');
                return '<span class="notion-mention">@' . $this->escape($name !== '' ? $name : 'user') . '</span>';
            case 'page':
                $title = (string)($mention['page']['id'] ?? '');
                return '<span class="notion-mention">' . $this->escape('page:' . $title) . '</span>';
            case 'database':
                $title = (string)($mention['database']['id'] ?? '');
                return '<span class="notion-mention">' . $this->escape('database:' . $title) . '</span>';
            case 'date':
                $start = (string)($mention['date']['start'] ?? '');
                return '<span class="notion-mention">' . $this->escape($start) . '</span>';
            default:
                return '';
        }
    }

    private function imageUrl(array $data): string
    {
        $type = (string)($data['type'] ?? '');
        if ($type === 'external') {
            return (string)($data['external']['url'] ?? '');
        }
        if ($type === 'file') {
            return (string)($data['file']['url'] ?? '');
        }
        return '';
    }

    private function fileUrl(array $data): string
    {
        $type = (string)($data['type'] ?? '');
        if ($type === 'external') {
            return (string)($data['external']['url'] ?? '');
        }
        if ($type === 'file') {
            return (string)($data['file']['url'] ?? '');
        }
        return '';
    }

    private function resolveChildHref(string $sourceId, array $pageMapping): string
    {
        if (isset($pageMapping[$sourceId]) && $pageMapping[$sourceId] !== '') {
            return 'index.php?route=knowledge-page&id=' . $pageMapping[$sourceId];
        }
        return '';
    }

    private function extractText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            $data = $block['data'];
            $rich = $data['rich_text'] ?? [];
            foreach ($rich as $rt) {
                $plain = (string)($rt['plain_text'] ?? '');
                if ($plain !== '') {
                    $parts[] = $plain;
                }
            }
            if ($block['children'] !== []) {
                $parts[] = $this->extractText($block['children']);
            }
        }
        return trim(implode(' ', $parts));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
