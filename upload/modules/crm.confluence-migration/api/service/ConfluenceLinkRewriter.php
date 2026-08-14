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
                    return 'index.php?route=knowledge-page&id=' . $pageMapping[$pageId];
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

        // 2. Rewrite /wiki/display/{spaceKey}/{title} (old-style Confluence URLs)
        $html = preg_replace_callback(
            '#/wiki/display/([^/]+)/([a-zA-Z0-9\+%]+)#i',
            function ($match) use ($pageMapping, &$warnings) {
                $spaceKey = $match[1];
                $title = urldecode(str_replace('+', ' ', $match[2]));
                $warnings[] = [
                    'macro' => 'link',
                    'handling' => 'unresolved',
                    'message' => 'Display URL to page "' . $title . '" in space ' . $spaceKey . ' could not be resolved (try searching by title)',
                ];
                return '<em>[page: ' . htmlspecialchars($title) . ']</em>';
            },
            $html
        ) ?? $html;

        // 3. Rewrite /pages/viewpage.action?pageId={id}
        $html = preg_replace_callback(
            '#/pages/viewpage\.action\?pageId=(\d+)#i',
            function ($match) use ($pageMapping, &$warnings) {
                $pageId = $match[1];
                if (isset($pageMapping[$pageId])) {
                    return 'index.php?route=knowledge-page&id=' . $pageMapping[$pageId];
                }
                $warnings[] = [
                    'macro' => 'link',
                    'handling' => 'unresolved',
                    'message' => 'Action link to Confluence page ' . $pageId . ' could not be resolved',
                ];
                return '<em>[unresolved page link (id: ' . $pageId . ')]</em>';
            },
            $html
        ) ?? $html;

        // 4. Rewrite /wiki/spaces/{key}/blog/... URLs
        $html = preg_replace_callback(
            '#/wiki/spaces/([^/]+)/blog/(\d{4})/(\d{1,2})/(\d{1,2})/([a-zA-Z0-9\+%\-]+)#i',
            function ($match) use ($pageMapping, &$warnings) {
                $spaceKey = $match[1];
                $year = $match[2];
                $month = $match[3];
                $day = $match[4];
                $title = urldecode(str_replace('+', ' ', $match[5]));
                $warnings[] = [
                    'macro' => 'link',
                    'handling' => 'unresolved',
                    'message' => 'Blog post link "' . $title . '" in space ' . $spaceKey . ' from ' . $year . '-' . $month . '-' . $day . ' could not be resolved',
                ];
                return '<em>[blog: ' . htmlspecialchars($title) . ']</em>';
            },
            $html
        ) ?? $html;

        // 5. Rewrite [page-id:12345] placeholders (from macro renderer)
        $html = preg_replace_callback(
            '/\[page-id:(\d+)\]/i',
            function ($match) use ($pageMapping, &$warnings) {
                $pageId = $match[1];
                if (isset($pageMapping[$pageId])) {
                    return '<a href="index.php?route=knowledge-page&id=' . $pageMapping[$pageId] . '">[linked page]</a>';
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

        // 6. Rewrite [page:Title] placeholders (from macro renderer)
        $html = preg_replace_callback(
            '/\[page:([^\]]+)\]/i',
            function ($match) {
                return '<em>[' . htmlspecialchars($match[1]) . ']</em>';
            },
            $html
        ) ?? $html;

        // 7. Rewrite [attachment:filename] placeholders
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
