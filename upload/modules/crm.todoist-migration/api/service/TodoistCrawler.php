<?php
declare(strict_types=1);

namespace Module\Crm\TodoistMigration\Service;

use Module\Crm\TodoistMigration\Repository\TodoistMigrationRepository;
use RuntimeException;

final class TodoistCrawler
{
    public function __construct(
        private readonly TodoistClient $client,
        private readonly TodoistMigrationRepository $repo
    ) {
    }

    /** @return array<string,mixed> */
    public function crawl(array $job, string $token, ?callable $heartbeat = null): array
    {
        $scope = (array)($job['source_scope'] ?? []);
        $options = (array)($job['target_options'] ?? []);
        $selected = array_values(array_filter(array_map('strval', (array)($scope['project_ids'] ?? []))));
        $includeArchived = (bool)($scope['include_archived'] ?? $options['include_archived'] ?? false);
        $includeCompleted = (bool)($scope['include_completed'] ?? $options['include_completed'] ?? false);
        $includeComments = array_key_exists('include_comments', $scope) ? (bool)$scope['include_comments'] : (bool)($options['include_comments'] ?? true);
        $includeAttachments = array_key_exists('include_attachments', $scope) ? (bool)$scope['include_attachments'] : (bool)($options['include_attachments'] ?? false);
        $maxTasks = max(0, (int)($scope['max_tasks'] ?? $options['max_tasks'] ?? 0));
        $since = (string)($scope['completed_since'] ?? '');
        $until = (string)($scope['completed_until'] ?? '');
        $projectsPerRun = max(1, min(20, (int)($options['projects_per_run'] ?? 1)));
        $afterProjectId = trim((string)($scope['_after_project_id'] ?? ''));
        $afterFound = $afterProjectId === '';
        $recoveringCheckpoint = !empty($scope['_checkpoint_recovery']);
        $processedProjects = 0;
        $stopAfterProject = false;

        $stats = ['projects' => 0, 'sections' => 0, 'labels' => 0, 'collaborators' => 0, 'tasks' => 0, 'completed_tasks' => 0, 'comments' => 0, 'attachments' => 0, 'warnings' => [], 'crawl_complete' => true, 'last_project_id' => null];
        if ($includeArchived) {
            $stats['warnings'][] = 'Todoist API v1 lists active projects only; archived projects were not available for import.';
        }
        if ($includeCompleted && ($since === '' || $until === '')) {
            $until = gmdate('Y-m-d\\TH:i:s\\Z');
            $since = gmdate('Y-m-d\\TH:i:s\\Z', strtotime('-90 days'));
            $stats['warnings'][] = 'Completed-task history was limited to the latest 90 days because no date range was supplied.';
        }
        if ($includeCompleted && $this->completedWindows($since, $until) === []) {
            $stats['warnings'][] = 'Completed-task history was skipped because the supplied date range is invalid.';
        }

        $this->client->eachLabels($token, function (array $label) use ($job, &$stats): void {
            $id = (string)($label['id'] ?? '');
            if ($id === '') return;
            $this->repo->upsertItem((int)$job['id'], 'label', $id, ['status' => 'pending', 'checksum' => $this->checksum($label), 'payload_json' => $label]);
            $stats['labels']++;
        });

        $this->client->eachProjects($token, $includeArchived, function (array $project) use ($job, $token, $selected, $includeComments, $includeAttachments, $maxTasks, $heartbeat, $afterProjectId, &$afterFound, &$processedProjects, &$stopAfterProject, &$stats): bool {
            if ($heartbeat !== null && !$heartbeat()) throw new RuntimeException('TODOIST_JOB_LEASE_LOST');
            $projectId = (string)($project['id'] ?? '');
            if ($projectId === '' || ($selected !== [] && !in_array($projectId, $selected, true))) return true;
            if (!$afterFound) {
                if ($projectId !== $afterProjectId) return true;
                $afterFound = true;
                return true;
            }
            if (!$includeArchived && !empty($project['is_archived'])) return true;
            $processedProjects++;
            $stats['projects']++;
            $this->repo->upsertItem((int)$job['id'], 'project', $projectId, ['status' => 'pending', 'checksum' => $this->checksum($project), 'payload_json' => $project]);

            try {
                $this->client->eachCollaborators($token, $projectId, function (array $user) use ($job, &$stats): void {
                    if (empty($user['id'])) return;
                    $this->repo->upsertUserMapping((int)$job['connection_id'], $user);
                    $stats['collaborators']++;
                });
                $this->client->eachSections($token, $projectId, function (array $section) use ($job, $projectId, &$stats): void {
                    $id = (string)($section['id'] ?? '');
                    if ($id === '') return;
                    $this->repo->upsertItem((int)$job['id'], 'section', $id, ['source_project_id' => $projectId, 'status' => 'pending', 'checksum' => $this->checksum($section), 'payload_json' => $section]);
                    $stats['sections']++;
                });
                if ($includeComments) {
                    $this->client->eachProjectComments($token, $projectId, function (array $comment) use ($job, $projectId, $includeAttachments, &$stats): void {
                        $this->storeComment($job, $comment, null, $projectId, $includeAttachments, $stats);
                    });
                }
            } catch (\Throwable $e) {
                if (in_array($e->getMessage(), ['TODOIST_JOB_LEASE_LOST', 'TODOIST_RATE_LIMITED'], true)) throw $e;
                $stats['warnings'][] = 'Project ' . $projectId . ' metadata could not be fully loaded.';
                $this->repo->addLog((int)$job['id'], 'warning', 'crawl', 'Todoist project metadata discovery failed.', ['project_id' => $projectId]);
            }

            $taskConsumer = function (array $task) use ($job, $token, $projectId, $includeComments, $includeAttachments, $maxTasks, $heartbeat, &$stats): bool {
                if ($maxTasks > 0 && $stats['tasks'] >= $maxTasks) return false;
                $this->storeTask($job, $token, $task, $projectId, $includeComments, $includeAttachments, $heartbeat, $stats);
                return !($maxTasks > 0 && $stats['tasks'] >= $maxTasks);
            };
            try {
                $this->client->eachTasks($token, $projectId, $taskConsumer);
            } catch (\Throwable $e) {
                if (in_array($e->getMessage(), ['TODOIST_JOB_LEASE_LOST', 'TODOIST_RATE_LIMITED'], true)) throw $e;
                $stats['warnings'][] = 'Project ' . $projectId . ' tasks could not be fully loaded.';
                $this->repo->addLog((int)$job['id'], 'warning', 'crawl', 'Todoist task discovery failed.', ['project_id' => $projectId]);
            }
            $stats['last_project_id'] = $projectId;
            if ($processedProjects >= $projectsPerRun) $stopAfterProject = true;
            return !($maxTasks > 0 && $stats['tasks'] >= $maxTasks) && !$stopAfterProject;
        });
        if ($afterProjectId !== '' && !$afterFound && !$recoveringCheckpoint) {
            // The source project may have been deleted or access-revoked. Restart
            // once from the beginning rather than silently skipping the suffix.
            $job['source_scope']['_after_project_id'] = '';
            $job['source_scope']['_checkpoint_recovery'] = 1;
            $recovered = $this->crawl($job, $token, $heartbeat);
            $recovered['warnings'] = array_values(array_unique(array_merge(['Crawl checkpoint project was no longer visible; discovery restarted safely.'], (array)$recovered['warnings'])));
            return $recovered;
        }

        // API v1's completion-date endpoint is account-wide and does not accept
        // project_id. Fetch it once after the project batches finish, then filter
        // by each task's project_id locally to avoid N duplicate history calls.
        if ($includeCompleted && !$stopAfterProject && !($maxTasks > 0 && $stats['tasks'] >= $maxTasks)) {
            foreach ($this->completedWindows($since, $until) as [$windowSince, $windowUntil]) {
                $this->client->eachCompletedTasks($token, null, $windowSince, $windowUntil, function (array $task) use ($job, $token, $selected, $includeComments, $includeAttachments, $maxTasks, $heartbeat, &$stats): bool {
                    $projectId = (string)($task['project_id'] ?? '');
                    if ($projectId === '' || ($selected !== [] && !in_array($projectId, $selected, true))) return true;
                    if ($maxTasks > 0 && $stats['tasks'] >= $maxTasks) return false;
                    $task['is_completed'] = true;
                    $this->storeTask($job, $token, $task, $projectId, $includeComments, $includeAttachments, $heartbeat, $stats, true);
                    $stats['completed_tasks']++;
                    return !($maxTasks > 0 && $stats['tasks'] >= $maxTasks);
                });
                if ($maxTasks > 0 && $stats['tasks'] >= $maxTasks) break;
            }
        }

        $stats['crawl_complete'] = !$stopAfterProject;
        return $stats;
    }

    private function storeTask(array $job, string $token, array $task, string $projectId, bool $comments, bool $attachments, ?callable $heartbeat, array &$stats, bool $completed = false): void
    {
        if ($heartbeat !== null && !$heartbeat()) throw new RuntimeException('TODOIST_JOB_LEASE_LOST');
        $id = (string)($task['id'] ?? '');
        if ($id === '') return;
        $parent = (string)($task['parent_id'] ?? '');
        $type = $parent !== '' ? 'subtask' : 'task';
        $task['is_completed'] = $completed || !empty($task['is_completed']) || !empty($task['completed_at']);
        $existing = $this->repo->findItem((int)$job['id'], $type, $id);
        $this->repo->upsertItem((int)$job['id'], $type, $id, ['source_parent_id' => $parent !== '' ? $parent : null, 'source_project_id' => $projectId, 'status' => 'pending', 'checksum' => $this->checksum($task), 'source_updated_at' => $this->date((string)($task['updated_at'] ?? $task['completed_at'] ?? $task['created_at'] ?? $task['added_at'] ?? '')), 'payload_json' => $task]);
        // Active and completed endpoints may return the same task. Count only
        // the first observation so max_tasks is a unique-entity limit.
        if ($existing === null) $stats['tasks']++;
        if (!$comments) return;
        $this->client->eachComments($token, $id, function (array $comment) use ($job, $id, $projectId, $attachments, &$stats): void {
            $this->storeComment($job, $comment, $id, $projectId, $attachments, $stats);
        });
    }

    private function storeComment(array $job, array $comment, ?string $taskId, string $projectId, bool $attachments, array &$stats): void
    {
        $commentId = (string)($comment['id'] ?? '');
        if ($commentId === '') return;
        $payload = $comment;
        if ($taskId !== null) $payload['_source_task_id'] = $taskId;
        $this->repo->upsertItem((int)$job['id'], 'comment', $commentId, ['source_parent_id' => $taskId ?? $projectId, 'source_project_id' => $projectId, 'status' => 'pending', 'checksum' => $this->checksum($payload), 'payload_json' => $payload]);
        $stats['comments']++;
        $attachment = $comment['attachment'] ?? $comment['file_attachment'] ?? null;
        $attachmentUrl = is_array($attachment) ? trim((string)($attachment['file_url'] ?? $attachment['url'] ?? '')) : '';
        if ($attachments && is_array($attachment) && $attachmentUrl !== '') {
            $attachmentId = $commentId . ':' . hash('sha256', $attachmentUrl);
            $attachment['file_url'] = $attachmentUrl;
            $this->repo->upsertItem((int)$job['id'], 'attachment', $attachmentId, ['source_parent_id' => $commentId, 'source_project_id' => $projectId, 'status' => 'pending', 'checksum' => $this->checksum($attachment), 'payload_json' => array_merge($attachment, ['_source_attachment_id' => $attachmentId, '_source_task_id' => $taskId, '_source_comment_id' => $commentId])]);
            $stats['attachments']++;
        }
    }

    /** @return array<int,array{0:string,1:string}> */
    private function completedWindows(string $since, string $until): array
    {
        $start = strtotime($since);
        $end = strtotime($until);
        if ($start === false || $end === false || $start > $end) return [];
        $windows = [];
        for ($cursor = $start; $cursor <= $end; $cursor = $windowEnd + 1) {
            $windowEnd = min($end, $cursor + (90 * 86400) - 1);
            $windows[] = [gmdate('Y-m-d\\TH:i:s\\Z', $cursor), gmdate('Y-m-d\\TH:i:s\\Z', $windowEnd)];
        }
        return $windows;
    }

    private function checksum(array $value): string
    {
        return hash('sha256', (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function date(string $value): ?string
    {
        if ($value === '') return null;
        $timestamp = strtotime($value);
        return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
    }
}
