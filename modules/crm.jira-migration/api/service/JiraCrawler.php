<?php
declare(strict_types=1);

namespace Module\Crm\JiraMigration\Service;

use Module\Crm\JiraMigration\Repository\JiraMigrationRepository;

final class JiraCrawler
{
    private JiraClient $client;
    private JiraMigrationRepository $repo;

    public function __construct(
        JiraClient $client,
        JiraMigrationRepository $repo,
    ) {
        $this->client = $client;
        $this->repo = $repo;
    }

    /**
     * Build snapshot of projects and issues for dry-run or import.
     * @return array{total_projects: int, total_issues: int, total_subtasks: int, total_epics: int, total_comments_estimate: int, total_attachments_estimate: int, total_worklogs_estimate: int, warnings: array}
     */
    public function crawlProjects(array $job, string $siteUrl, string $email, string $token): array
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $scope = json_decode((string)($job['source_scope_json'] ?? '[]'), true) ?? [];
        $projectKeys = $scope['project_keys'] ?? [];
        $mode = (string)$job['mode'];
        $warnings = [];

        $totalProjects = 0;
        $totalIssues = 0;
        $totalSubtasks = 0;
        $totalEpics = 0;
        $totalCommentsEstimate = 0;
        $totalAttachmentsEstimate = 0;
        $totalWorklogsEstimate = 0;

        // Get projects
        $projects = $this->client->getProjects($siteUrl, $email, $token, $projectKeys !== [] ? $projectKeys : null);

        foreach ($projects as $project) {
            $projectKey = $project['key'];
            $projectId = $project['id'];

            $totalProjects++;

            // Create job item for project
            $this->repo->upsertJobItem($jobId, 'project', $projectId, [
                'source_key' => $projectKey,
                'status' => $mode === 'dry_run' ? 'skipped' : 'pending',
                'payload_json' => [
                    'name' => $project['name'],
                    'key' => $projectKey,
                ],
            ]);

            // JQL to get all issues in the project
            $jql = "project = \"{$projectKey}\" ORDER BY created ASC";

            try {
                if ($mode === 'dry_run') {
                    // Count issues using JQL search with minimal fields
                    try {
                        $countResult = $this->client->searchIssues($siteUrl, $email, $token, $jql, ['id'], 1);
                        $totalIssues += $countResult[0]['total'] ?? count($countResult);
                    } catch (\Throwable $e) {
                        error_log('[JiraCrawler::crawlProjects] Failed to count issues for ' . $projectKey . ': ' . $e->getMessage());
                        $warnings[] = "Failed to count issues for {$projectKey}. Check server logs for details.";
                    }
                    continue;
                }

                // Full crawl for import mode
                $issues = $this->client->searchIssues($siteUrl, $email, $token, $jql, ['id', 'key', 'issuetype', 'parent', 'summary', 'status', 'priority', 'assignee', 'created', 'updated', 'duedate', 'labels', 'components', 'fixVersions', 'customfield_*', 'description']);

                foreach ($issues as $issue) {
                    $issueKey = (string)($issue['key'] ?? '');
                    $issueId = (string)($issue['id'] ?? '');
                    $fields = $issue['fields'] ?? [];
                    $issueType = $fields['issuetype']['name'] ?? '';
                    $parentId = null;

                    if (!empty($fields['parent'])) {
                        $parentId = (string)($fields['parent']['id'] ?? null);
                    }

                    // Count by type
                    if ($issueType === 'Sub-task' || !empty($fields['issuetype']['subtask'])) {
                        $totalSubtasks++;
                    } elseif ($issueType === 'Epic') {
                        $totalEpics++;
                    }
                    $totalIssues++;

                    $this->repo->upsertJobItem($jobId, 'issue', $issueId, [
                        'source_key' => $issueKey,
                        'source_parent_id' => $parentId,
                        'status' => 'pending',
                        'source_updated_at' => $fields['updated'] ?? null,
                        'payload_json' => [
                            'key' => $issueKey,
                            'summary' => $fields['summary'] ?? '',
                            'issue_type' => $issueType,
                            'project_key' => $projectKey,
                        ],
                    ]);

                    // Count comments estimate
                    if (!empty($fields['comment']['total'])) {
                        $totalCommentsEstimate += (int)$fields['comment']['total'];
                    }

                    // Count attachments estimate
                    if (!empty($fields['attachment'])) {
                        $totalAttachmentsEstimate += count($fields['attachment']);
                    }

                    // Count worklogs estimate
                    if (!empty($fields['worklog']['total'])) {
                        $totalWorklogsEstimate += (int)$fields['worklog']['total'];
                    }
                }

                // Get versions for this project
                try {
                    $versions = $this->client->getProjectVersions($siteUrl, $email, $token, $projectKey);
                    foreach ($versions as $version) {
                        $this->repo->upsertJobItem($jobId, 'version', $projectKey . ':' . $version['id'], [
                            'source_key' => $version['name'],
                            'source_parent_id' => $projectId,
                            'status' => $mode === 'dry_run' ? 'skipped' : 'pending',
                            'payload_json' => $version,
                        ]);
                    }
                } catch (\Throwable $e) {
                    error_log('[JiraCrawler::crawlProjects] Failed to get versions for ' . $projectKey . ': ' . $e->getMessage());
                }

            } catch (\Throwable $e) {
                error_log('[JiraCrawler::crawlProjects] Failed to crawl project ' . $projectKey . ': ' . $e->getMessage());
                $warnings[] = "Failed to crawl project {$projectKey}. Check server logs for details.";
                $this->repo->addJobLog($jobPublicId, 'warning', 'crawl', 'Failed to crawl project ' . $projectKey . '. Check server logs for details.');
            }
        }

        if ($mode === 'dry_run') {
            $this->repo->addJobLog($jobPublicId, 'info', 'crawl', 'Dry run: discovered ' . $totalProjects . ' projects, ~' . $totalIssues . ' issues');
        }

        return [
            'total_projects' => $totalProjects,
            'total_issues' => $totalIssues,
            'total_subtasks' => $totalSubtasks,
            'total_epics' => $totalEpics,
            'total_comments_estimate' => $totalCommentsEstimate,
            'total_attachments_estimate' => $totalAttachmentsEstimate,
            'total_worklogs_estimate' => $totalWorklogsEstimate,
            'warnings' => $warnings,
        ];
    }
}
