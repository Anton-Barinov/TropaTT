<?php
declare(strict_types=1);

namespace Module\Crm\ConfluenceMigration\Service;

final class ConfluenceMacroRenderer
{
    /**
     * Render supported Confluence macros to HTML placeholders.
     * Log unsupported macros as warnings.
     */
    public function render(string $html, array &$warnings): string
    {
        // Handle <ac:structured-macro>
        $html = preg_replace_callback(
            '/<ac:structured-macro[^>]*>.*?<\/ac:structured-macro>/is',
            function ($match) use (&$warnings) {
                return $this->renderStructuredMacro($match[0], $warnings);
            },
            $html
        ) ?? $html;

        // Handle <ac:macro> (inline macros)
        $html = preg_replace_callback(
            '/<ac:macro[^>]*>.*?<\/ac:macro>/is',
            function ($match) use (&$warnings) {
                return $this->renderInlineMacro($match[0], $warnings);
            },
            $html
        ) ?? $html;

        // Handle ac:image
        $html = preg_replace('/<ac:image[^>]*>(.*?)<\/ac:image>/is', '', $html) ?? $html;

        // Handle ac:link (page, attachment, user references)
        $html = preg_replace_callback(
            '/<ac:link[^>]*>(.*?)<\/ac:link>/is',
            function ($match) {
                // Extract ri:page references
                if (preg_match('/<ri:page[^>]*ri:content-title="([^"]*)"[^>]*\/>/is', $match[1], $titleMatch)) {
                    return '[page:' . $titleMatch[1] . ']';
                }
                if (preg_match('/<ri:page[^>]*ri:content-id="([^"]*)"[^>]*\/>/is', $match[1], $idMatch)) {
                    return '[page-id:' . $idMatch[1] . ']';
                }
                // Extract ri:attachment references
                if (preg_match('/<ri:attachment[^>]*ri:filename="([^"]*)"[^>]*\/>/is', $match[1], $fileMatch)) {
                    return '[attachment:' . $fileMatch[1] . ']';
                }
                // Extract ri:user references -> display name with @mention
                if (preg_match('/<ri:user[^>]*\/>/is', $match[1])) {
                    $accountId = '';
                    if (preg_match('/ri:account-id="([^"]*)"/i', $match[1], $accMatch)) {
                        $accountId = $accMatch[1];
                    }
                    // Extract link text (display name)
                    if (preg_match('/<ac:link-body>(.*?)<\/ac:link-body>/is', $match[1], $bodyMatch)) {
                        $displayName = strip_tags($bodyMatch[1]);
                        return '<span class="confluence-user-mention" data-account-id="' . htmlspecialchars($accountId) . '">@' . htmlspecialchars($displayName) . '</span>';
                    }
                    return '<span class="confluence-user-mention" data-account-id="' . htmlspecialchars($accountId) . '">@user</span>';
                }
                // Extract ri:group references
                if (preg_match('/<ri:group[^>]*ri:group-id="([^"]*)"[^>]*\/>/is', $match[1], $groupMatch)) {
                    if (preg_match('/<ac:link-body>(.*?)<\/ac:link-body>/is', $match[1], $bodyMatch)) {
                        return '<span class="confluence-group-mention">@' . htmlspecialchars(strip_tags($bodyMatch[1])) . '</span>';
                    }
                    return '<span class="confluence-group-mention">@group</span>';
                }
                // Extract link text from inner ac:link-body
                if (preg_match('/<ac:link-body>(.*?)<\/ac:link-body>/is', $match[1], $bodyMatch)) {
                    return strip_tags($bodyMatch[1]);
                }
                return '';
            },
            $html
        ) ?? $html;

        // Handle standalone <ri:user> mentions (not inside ac:link)
        $html = preg_replace_callback(
            '/<ri:user[^>]*\/>/is',
            function ($match) {
                $accountId = '';
                if (preg_match('/ri:account-id="([^"]*)"/i', $match[0], $accMatch)) {
                    $accountId = $accMatch[1];
                }
                if (preg_match('/ri:userkey="([^"]*)"/i', $match[0], $keyMatch)) {
                    $accountId = $keyMatch[1];
                }
                return '<span class="confluence-user-mention" data-account-id="' . htmlspecialchars($accountId) . '">@mentioned user</span>';
            },
            $html
        ) ?? $html;

        // Handle standalone <ri:group> mentions
        $html = preg_replace_callback(
            '/<ri:group[^>]*\/>/is',
            function ($match) {
                if (preg_match('/ri:group-id="([^"]*)"/i', $match[0], $gMatch)) {
                    return '<span class="confluence-group-mention">@group [' . htmlspecialchars($gMatch[1]) . ']</span>';
                }
                return '<span class="confluence-group-mention">@group</span>';
            },
            $html
        ) ?? $html;

        // Remove remaining ac: namespaced elements
        $html = preg_replace('/<\/?ac:[a-z]+[^>]*>/i', '', $html) ?? '';
        $html = preg_replace('/<\/?ri:[a-z]+[^>]*>/i', '', $html) ?? '';
        $html = preg_replace('/<\/?at:[a-z]+[^>]*>/i', '', $html) ?? '';

        return $html;
    }

    private function renderStructuredMacro(string $macroXml, array &$warnings): string
    {
        // Extract macro name
        preg_match('/<ac:structured-macro[^>]*ac:name="([^"]*)"[^>]*>/i', $macroXml, $nameMatch);
        $macroName = $nameMatch[1] ?? 'unknown';

        // Extract macro parameters for richer rendering
        $params = [];
        preg_match_all('/<ac:parameter ac:name="([^"]*)">(.*?)<\/ac:parameter>/is', $macroXml, $paramMatches, PREG_SET_ORDER);
        foreach ($paramMatches as $pm) {
            $params[strtolower($pm[1])] = strip_tags($pm[2]);
        }

        // Extract body if present
        $body = '';
        if (preg_match('/<ac:rich-text-body>(.*?)<\/ac:rich-text-body>/is', $macroXml, $bodyMatch)) {
            $body = $bodyMatch[1];
        } elseif (preg_match('/<ac:plain-text-body>(.*?)<\/ac:plain-text-body>/is', $macroXml, $bodyMatch)) {
            $body = htmlspecialchars($bodyMatch[1]);
        }

        switch (strtolower($macroName)) {
            case 'info':
            case 'note':
            case 'warning':
            case 'tip':
                $icon = match (strtolower($macroName)) {
                    'info' => '&#x2139;',
                    'note' => '&#x1F4DD;',
                    'warning' => '&#x26A0;',
                    'tip' => '&#x1F4A1;',
                    default => '',
                };
                $content = $body !== '' ? $body : ($params['text'] ?? '');
                return '<blockquote class="callout callout-' . strtolower($macroName) . '"><p><strong>' . $icon . ' ' . ucfirst($macroName) . '</strong></p>' . $content . '</blockquote>';

            case 'code':
                $language = $params['language'] ?? $params['lang'] ?? '';
                $content = $body !== '' ? htmlspecialchars($body) : ($params['text'] ?? '');
                $langAttr = $language !== '' ? ' class="language-' . htmlspecialchars($language) . '"' : '';
                return '<pre' . $langAttr . '><code>' . $content . '</code></pre>';

            case 'panel':
                $content = $body !== '' ? $body : ($params['text'] ?? '');
                return '<blockquote class="callout callout-panel">' . $content . '</blockquote>';

            case 'expand':
                $title = htmlspecialchars($params['title'] ?? 'Expand');
                $content = $body !== '' ? $body : '';
                return '<details><summary>' . $title . '</summary>' . $content . '</details>';

            case 'toc':
                $style = $params['style'] ?? 'list';
                $warnings[] = ['macro' => 'toc', 'handling' => 'structured', 'message' => 'Table of contents rendered from page headings'];
                return '<div class="confluence-toc" data-confluence-toc="true">'
                    . '<div class="confluence-toc-header"><strong>Contents</strong></div>'
                    . '<div class="confluence-toc-body"><em class="text-muted">[Auto-generated from page headings]</em></div>'
                    . '</div>';

            case 'children':
                $warnings[] = ['macro' => 'children', 'handling' => 'structured', 'message' => 'Child pages listing — will be resolved during content processing'];
                return '<div class="confluence-children" data-confluence-children="true">'
                    . '<div class="confluence-children-header"><strong>Child pages</strong></div>'
                    . '<div class="confluence-children-body"><em class="text-muted">[Child pages — imported separately]</em></div>'
                    . '</div>';

            case 'excerpt':
                return $body !== '' ? $body : '<div class="confluence-macro-placeholder"><em>[Excerpt — not migrated]</em></div>';

            case 'include':
                $warnings[] = ['macro' => 'include', 'handling' => 'placeholder', 'message' => 'Page includes are converted to static snapshots.'];
                return $body !== '' ? $body : '<div class="confluence-macro-placeholder"><em>[Included page — content merged where possible]</em></div>';

            case 'jira':
                $warnings[] = ['macro' => 'jira', 'handling' => 'placeholder', 'message' => 'Jira issues replaced with link.'];
                $issueKey = $params['key'] ?? '';
                $url = $params['url'] ?? $params['server'] ?? '';
                if ($issueKey !== '') {
                    return '<p><a href="' . htmlspecialchars($url) . '" rel="noopener noreferrer" target="_blank">' . htmlspecialchars($issueKey) . '</a> <em>(Jira issue — view in Jira)</em></p>';
                }
                return '<div class="confluence-macro-placeholder"><em>[Jira issue — not migrated]</em></div>';

            case 'status':
                $colour = $params['colour'] ?? $params['color'] ?? 'grey';
                $title = $params['title'] ?? '';
                $colourMap = ['green' => 'success', 'yellow' => 'warning', 'red' => 'danger', 'blue' => 'info', 'grey' => 'secondary'];
                $bsColour = $colourMap[strtolower($colour)] ?? 'secondary';
                return '<span class="crm-badge crm-badge-' . $bsColour . '">' . htmlspecialchars($title) . '</span>';

            case 'livesearch':
            case 'listlabels':
            case 'recentlyupdated':
            case 'pagetreesearch':
            case 'contributors':
            case 'contentbylabel':
            case 'blog-posts':
                $warnings[] = ['macro' => $macroName, 'handling' => 'placeholder', 'message' => 'Dynamic macro not supported in static export.'];
                return '<div class="confluence-macro-placeholder"><em>[' . $macroName . ' — not migrated]</em></div>';

            default:
                $warnings[] = ['macro' => $macroName, 'handling' => 'placeholder', 'message' => 'Unsupported macro: ' . $macroName];
                return '<div class="confluence-macro-placeholder"><em>[Macro: ' . htmlspecialchars($macroName) . ' — not supported]</em></div>';
        }
    }

    private function renderInlineMacro(string $macroXml, array &$warnings): string
    {
        preg_match('/<ac:macro[^>]*ac:name="([^"]*)"[^>]*>/i', $macroXml, $nameMatch);
        $macroName = $nameMatch[1] ?? 'unknown';

        $params = [];
        preg_match_all('/<ac:parameter ac:name="([^"]*)">(.*?)<\/ac:parameter>/is', $macroXml, $paramMatches, PREG_SET_ORDER);
        foreach ($paramMatches as $pm) {
            $params[strtolower($pm[1])] = strip_tags($pm[2]);
        }

        switch (strtolower($macroName)) {
            default:
                $warnings[] = ['macro' => $macroName, 'handling' => 'placeholder', 'message' => 'Unsupported inline macro: ' . $macroName];
                return '<em>[' . htmlspecialchars($macroName) . ']</em>';
        }
    }
}
