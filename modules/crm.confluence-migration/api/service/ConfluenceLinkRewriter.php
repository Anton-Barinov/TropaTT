<?php
declare(strict_types=1);

namespace Module\Crm\ConfluenceMigration\Service;

final class ConfluenceLinkRewriter
{
    /**
     * Rewrite Confluence links to TropaTT knowledge page links.
     *
     * @param string $html The HTML content
     * @param array<string, string> $pageMapping Confluence page ID -> TropaTT public_id
     * @param array &$warnings Collected warnings
     * @return string Modified HTML
     */
    public function rewrite(string $html, array $pageMapping, array &$warnings): string
    {
        // 1. Rewrite Confluence wiki links: /wiki/spaces/{key}/pages/{id}/...
        $html = preg_replace_callback(
            '#(/wiki/spaces/[^/]+/pages/)(\d+)#i',
            function ($match) use ($pageMapping, &$warnings) {
                $pageId = $match[2];
                if (isset($pageMapping[$pageId])) {
                    return '/web/index.php?route=knowledge-page&id=' . $pageMapping[$pageId];
                }
                $warnings[] = [
                    'macro' => 'link',
                    'handling' => 'unresolved',
                    'message' => 'Internal link to Confluence page ' . $pageId . ' could not be resolved',
                ];
                return $match[0];
            },
            $html
        ) ?? $html;

        // 2. Rewrite [page-id:12345] placeholders (from macro renderer)
        $html = preg_replace_callback(
            '/\[page-id:(\d+)\]/i',
            function ($match) use ($pageMapping, &$warnings) {
                $pageId = $match[1];
                if (isset($pageMapping[$pageId])) {
                    return '<a href="/web/index.php?route=knowledge-page&id=' . $pageMapping[$pageId] . '">[linked page]</a>';
                }
                $warnings[] = [
                    'macro' => 'link',
                    'handling' => 'unresolved',
                    'message' => 'Internal page link ' . $pageId . ' could not be resolved',
                ];
                return '<em>[unresolved page link]</em>';
            },
            $html
        ) ?? $html;

        // 3. Rewrite [page:Title] placeholders (from macro renderer)
        $html = preg_replace_callback(
            '/\[page:([^\]]+)\]/i',
            function ($match) {
                // Title-based links are ambiguous - keep as text
                return '<em>[' . htmlspecialchars($match[1]) . ']</em>';
            },
            $html
        ) ?? $html;

        // 4. Rewrite [attachment:filename] placeholders
        $html = preg_replace_callback(
            '/\[attachment:([^\]]+)\]/i',
            function ($match) {
                return '<em>[attachment: ' . htmlspecialchars($match[1]) . ']</em>';
            },
            $html
        ) ?? $html;

        return $html;
    }
}
