<?php
declare(strict_types=1);

namespace Module\Crm\ConfluenceMigration\Service;

final class ConfluenceTransformer
{
    private ConfluenceMacroRenderer $macroRenderer;
    private ConfluenceLinkRewriter $linkRewriter;

    public function __construct(
        ?ConfluenceMacroRenderer $macroRenderer = null,
        ?ConfluenceLinkRewriter $linkRewriter = null,
    ) {
        $this->macroRenderer = $macroRenderer ?? new ConfluenceMacroRenderer();
        $this->linkRewriter = $linkRewriter ?? new ConfluenceLinkRewriter();
    }

    /**
     * Transform Confluence body.storage HTML to safe TropaTT HTML.
     * @return array{content_html: string, content_text: string, warnings: array}
     */
    public function transform(string $storageHtml, array $pageMapping = []): array
    {
        $warnings = [];

        // 1. Render macros
        $html = $this->macroRenderer->render($storageHtml, $warnings);

        // 2. Sanitize
        $html = $this->sanitize($html);

        // 3. Rewrite links if mapping provided
        $html = $this->linkRewriter->rewrite($html, $pageMapping, $warnings);

        // 4. Populate Table of Contents (data-confluence-toc)
        $html = $this->populateToc($html);

        // 5. Extract plain text for search
        $contentText = $this->toPlainText($html);

        return [
            'content_html' => $html,
            'content_text' => $contentText,
            'warnings' => $warnings,
        ];
    }

    private function sanitize(string $html): string
    {
        // Remove scripts, styles, iframes, objects, forms, etc.
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|svg|math)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('#</?(script|style|iframe|object|embed|form|input|button|svg|math)[^>]*>#i', '', $html) ?? '';

        // Remove event handlers
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';

        // Strip javascript: links
        $html = preg_replace('/(href|src|action|formaction)\s*=\s*("|\')\s*(javascript|data|vbscript):[^"\']*("|\')/i', '$1="#"', $html) ?? '';

        // Remove meta and link tags
        $html = preg_replace('/<meta[^>]*>/i', '', $html) ?? '';
        $html = preg_replace('/<link[^>]*>/i', '', $html) ?? '';

        // Add rel="noopener noreferrer" and target="_blank" to external links
        $html = preg_replace_callback('/<a\s+([^>]*?)href="(https?:\/\/[^"]+)"([^>]*)>/i', function ($m) {
            $attrs = $m[1] . $m[3];
            if (!str_contains($attrs, 'rel=')) {
                $attrs .= ' rel="noopener noreferrer"';
            }
            if (!str_contains($attrs, 'target=')) {
                $attrs .= ' target="_blank"';
            }
            return '<a ' . $attrs . ' href="' . $m[2] . '">';
        }, $html) ?? $html;

        return trim($html);
    }

    private function toPlainText(string $html): string
    {
        $withSpaces = preg_replace('#</(p|div|section|article|h[1-6]|li|ul|ol|br)>#i', ' ', $html) ?? $html;
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($withSpaces), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function populateToc(string $html): string
    {
        if (!str_contains($html, 'data-confluence-toc="true"')) {
            return $html;
        }

        // Extract all headings with their IDs and text
        $headings = [];
        preg_match_all('/<h([1-6])([^>]*)>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $level = (int)$m[1];
            $attrs = $m[2];
            $text = strip_tags($m[3]);
            $id = '';
            if (preg_match('/id="([^"]*)"/i', $attrs, $idMatch)) {
                $id = $idMatch[1];
            }
            $headings[] = ['level' => $level, 'id' => $id, 'text' => $text];
        }

        if ($headings === []) {
            return $html;
        }

        // Build TOC HTML
        $tocHtml = '<div class="confluence-toc"><div class="confluence-toc-header"><strong>Contents</strong></div><ul class="confluence-toc-list">';
        $prevLevel = 1;
        foreach ($headings as $h) {
            $level = $h['level'];
            $text = htmlspecialchars($h['text'] ?: 'Untitled');
            $href = $h['id'] !== '' ? '#' . htmlspecialchars($h['id']) : '';

            if ($level > $prevLevel) {
                $tocHtml .= str_repeat('<ul>', $level - $prevLevel);
            } elseif ($level < $prevLevel) {
                $tocHtml .= str_repeat('</ul>', $prevLevel - $level);
            }

            if ($href !== '') {
                $tocHtml .= '<li><a href="' . $href . '">' . $text . '</a></li>';
            } else {
                $tocHtml .= '<li><span>' . $text . '</span></li>';
            }
            $prevLevel = $level;
        }
        $tocHtml .= str_repeat('</ul>', $prevLevel - 1);
        $tocHtml .= '</div>';

        // Replace TOC placeholder
        $html = preg_replace(
            '/<div[^>]*data-confluence-toc="true"[^>]*>.*?<\/div>\s*<\/div>/is',
            $tocHtml,
            $html,
            1
        ) ?? $html;

        return $html;
    }

    public function setLinkRewriter(ConfluenceLinkRewriter $rewriter): void
    {
        $this->linkRewriter = $rewriter;
    }
}
