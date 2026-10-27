<?php
declare(strict_types=1);

namespace Module\Crm\AsanaMigration\Service;

use Module\Crm\AsanaMigration\Repository\AsanaMigrationRepository;
use RuntimeException;

final class AsanaCrawler
{
    public function __construct(private readonly AsanaClient $client, private readonly AsanaMigrationRepository $repo)
    {
    }

    /** @return array<string,mixed> */
    public function crawl(array $job, string $token, ?callable $heartbeat = null): array
    {
        $scope = (array)($job['source_scope'] ?? []);
        $workspace = (string)($job['workspace_gid'] ?? '');
        if ($workspace === '') throw new RuntimeException('ASANA_WORKSPACE_REQUIRED');
        $options = (array)($job['target_options'] ?? []);
        $selected = array_values(array_filter(array_map('strval', (array)($scope['project_gids'] ?? []))));
        $includeArchived = (bool)($scope['include_archived_projects'] ?? $options['include_archived_projects'] ?? false);
        $maxTasks = max(0, (int)($scope['max_tasks'] ?? $options['max_tasks'] ?? 0));
        $includeComments = array_key_exists('include_comments', $scope) ? (bool)$scope['include_comments'] : (bool)($options['include_comments'] ?? true);
        $includeAttachments = array_key_exists('include_attachments', $scope) ? (bool)$scope['include_attachments'] : (bool)($options['include_attachments'] ?? false);
        $includeArchivedTasks = (bool)($scope['include_archived_tasks'] ?? $options['include_archived_tasks'] ?? false);
        $maxDepth = max(1, min(20, (int)($scope['max_subtask_depth'] ?? 10)));

        $stats = ['projects' => 0, 'sections' => 0, 'tasks' => 0, 'subtasks' => 0, 'comments' => 0, 'attachments' => 0, 'dependencies' => 0, 'users' => 0, 'tags' => 0, 'warnings' => []];

        $this->client->eachUsers($token, $workspace, function (array $user) use ($job, &$stats): void {
            if (!empty($user['gid'])) {
                $this->repo->upsertUserMapping((int)$job['connection_id'], $user);
                $stats['users']++;
            }
        });
        $this->client->eachTags($token, $workspace, function (array $tag) use ($job, &$stats): void {
            if (!empty($tag['gid'])) {
                $this->repo->upsertItem((int)$job['id'], 'tag', (string)$tag['gid'], ['status' => 'pending', 'checksum' => $this->checksum($tag), 'payload_json' => $tag]);
                $stats['tags']++;
            }
        });

        $this->client->eachProjects($token, $workspace, $includeArchived, function (array $project) use (&$stats, $selected, $job, $token, $maxTasks, $maxDepth, $includeComments, $includeAttachments, $includeArchivedTasks, $heartbeat): bool {
            if ($heartbeat !== null && !$heartbeat()) throw new RuntimeException('ASANA_JOB_LEASE_LOST');
            $projectGid = (string)($project['gid'] ?? '');
            if ($projectGid === '' || ($selected !== [] && !in_array($projectGid, $selected, true))) return true;
            $stats['projects']++;
            $this->repo->upsertItem((int)$job['id'], 'project', $projectGid, ['status' => 'pending', 'checksum' => $this->checksum($project), 'payload_json' => $project]);

            $this->client->eachSections($token, $projectGid, function (array $section) use ($job, $projectGid, &$stats): void {
                $sectionGid = (string)($section['gid'] ?? '');
                if ($sectionGid === '') return;
                $this->repo->upsertItem((int)$job['id'], 'section', $sectionGid, ['source_project_id' => $projectGid, 'status' => 'pending', 'checksum' => $this->checksum($section), 'payload_json' => $section]);
                $stats['sections']++;
            });

            try {
                $this->client->eachTasks($token, $projectGid, $includeArchivedTasks, function (array $task) use ($job, $token, $projectGid, $maxTasks, $maxDepth, &$stats, $includeComments, $includeAttachments, $heartbeat): bool {
                    if ($maxTasks > 0 && $stats['tasks'] >= $maxTasks) return false;
                    $this->storeTaskTree($job, $token, $task, $projectGid, 0, $maxDepth, $stats, $includeComments, $includeAttachments, $heartbeat, $maxTasks);
                    return !($maxTasks > 0 && $stats['tasks'] >= $maxTasks);
                });
            } catch (\Throwable $e) {
                if (in_array($e->getMessage(), ['ASANA_JOB_LEASE_LOST', 'ASANA_COLLECTION_LIMIT_EXCEEDED'], true)) throw $e;
                $stats['warnings'][] = 'Project ' . $projectGid . ' tasks could not be fully loaded.';
                $this->repo->addLog((int)$job['id'], 'warning', 'crawl', 'Project discovery failed.', ['project_gid' => $projectGid]);
            }
            return !($maxTasks > 0 && $stats['tasks'] >= $maxTasks);
        });

        return $stats;
    }

    /** @param array<string,int> $stats */
    private function storeTaskTree(array $job, string $token, array $task, string $projectGid, int $depth, int $maxDepth, array &$stats, bool $comments, bool $attachments, ?callable $heartbeat, int $maxTasks): void
    {
        if ($heartbeat !== null && !$heartbeat()) throw new RuntimeException('ASANA_JOB_LEASE_LOST');
        $gid = (string)($task['gid'] ?? '');
        if ($gid === '' || ($maxTasks > 0 && $stats['tasks'] >= $maxTasks)) return;
        $parentGid = is_array($task['parent'] ?? null) ? (string)($task['parent']['gid'] ?? '') : '';
        $type = $parentGid !== '' ? 'subtask' : 'task';
        $this->repo->upsertItem((int)$job['id'], $type, $gid, ['source_parent_id' => $parentGid !== '' ? $parentGid : null, 'source_project_id' => $projectGid, 'status' => 'pending', 'checksum' => $this->checksum($task), 'source_updated_at' => $this->date((string)($task['modified_at'] ?? '')), 'payload_json' => $task]);
        $stats['tasks']++;
        if ($type === 'subtask') $stats['subtasks']++;

        foreach ((array)($task['dependencies'] ?? []) as $dependency) {
            $dependencyGid = is_array($dependency) ? (string)($dependency['gid'] ?? '') : (string)$dependency;
            if ($dependencyGid === '' || $dependencyGid === $gid) continue;
            $this->repo->upsertItem((int)$job['id'], 'dependency', $gid . ':' . $dependencyGid, ['source_parent_id' => $gid, 'source_project_id' => $projectGid, 'status' => 'pending', 'checksum' => $this->checksum(['task' => $gid, 'depends_on' => $dependencyGid]), 'payload_json' => ['source_task_gid' => $gid, 'depends_on_task_gid' => $dependencyGid, 'dependency_type' => 'FS']]);
            $stats['dependencies']++;
        }
        if ($comments) $this->client->eachStories($token, $gid, function (array $story) use ($job, $gid, &$stats): void {
            if ((string)($story['resource_subtype'] ?? '') !== 'comment_added') return;
            $storyGid = (string)($story['gid'] ?? '');
            if ($storyGid === '') return;
            $this->repo->upsertItem((int)$job['id'], 'comment', $storyGid, ['source_parent_id' => $gid, 'status' => 'pending', 'checksum' => $this->checksum($story), 'payload_json' => $story]);
            $stats['comments']++;
        });
        if ($attachments) $this->client->eachAttachments($token, $gid, function (array $attachment) use ($job, $gid, &$stats): void {
            $attachmentGid = (string)($attachment['gid'] ?? '');
            if ($attachmentGid === '') return;
            $this->repo->upsertItem((int)$job['id'], 'attachment', $attachmentGid, ['source_parent_id' => $gid, 'status' => 'pending', 'checksum' => $this->checksum($attachment), 'payload_json' => $attachment]);
            $stats['attachments']++;
        });
        if ($depth >= $maxDepth || ($maxTasks > 0 && $stats['tasks'] >= $maxTasks)) return;
        $this->client->eachSubtasks($token, $gid, function (array $child) use ($job, $token, $projectGid, $depth, $maxDepth, &$stats, $comments, $attachments, $heartbeat, $maxTasks): bool {
            if ($maxTasks > 0 && $stats['tasks'] >= $maxTasks) return false;
            $this->storeTaskTree($job, $token, $child, $projectGid, $depth + 1, $maxDepth, $stats, $comments, $attachments, $heartbeat, $maxTasks);
            return !($maxTasks > 0 && $stats['tasks'] >= $maxTasks);
        });
    }

    private function checksum(array $payload): string { return hash('sha256', (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)); }
    private function date(string $value): ?string { if ($value === '') return null; $time = strtotime($value); return $time === false ? null : gmdate('Y-m-d H:i:s', $time); }
}
