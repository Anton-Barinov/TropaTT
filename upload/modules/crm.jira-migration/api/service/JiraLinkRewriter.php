<?php
declare(strict_types=1);

namespace Module\Crm\JiraMigration\Service;

/**
 * Rewrites Jira links found in comments and descriptions to CRM task links.
 */
final class JiraLinkRewriter
{
    /**
     * Rewrite Jira issue keys in text to CRM task links.
     * @param string $text The text containing Jira issue references
     * @param array<string, string> $issueMapping Jira issue key -> CRM task public_id
     * @return string Text with Jira issue keys replaced by CRM links
     */
    public function rewriteIssueKeys(string $text, array $issueMapping): string
    {
        // Match Jira issue keys like PROJECT-123
        return preg_replace_callback(
            '/\b([A-Z][A-Z0-9]{1,9}-[1-9][0-9]*)\b/',
            function ($match) use ($issueMapping) {
                $key = $match[1];
                if (isset($issueMapping[$key])) {
                    $url = 'index.php?route=task-detail&task_public_id=' . rawurlencode($issueMapping[$key]);
                    return '<a href="' . $url . '" class="jira-imported-link">' . $key . '</a>';
                }
                return $key;
            },
            $text
        ) ?? $text;
    }

    /**
     * Rewrite Jira browse URLs (/browse/PROJECT-123) to CRM task links.
     */
    public function rewriteBrowseUrls(string $html, array $issueMapping): string
    {
        return preg_replace_callback(
            '#/browse/([A-Z][A-Z0-9]{1,9}-[1-9][0-9]*)#i',
            function ($match) use ($issueMapping) {
                $key = strtoupper($match[1]);
                if (isset($issueMapping[$key])) {
                    return 'index.php?route=task-detail&task_public_id=' . rawurlencode($issueMapping[$key]);
                }
                return $match[0];
            },
            $html
        ) ?? $html;
    }
}
