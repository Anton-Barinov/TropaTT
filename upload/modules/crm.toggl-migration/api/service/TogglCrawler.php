<?php
declare(strict_types=1);

namespace Module\Crm\TogglMigration\Service;

use Module\Crm\TogglMigration\Repository\TogglMigrationRepository;
use RuntimeException;

final class TogglCrawler
{
    public function __construct(
        private readonly TogglClient $client,
        private readonly TogglMigrationRepository $repo,
    ) {
    }

    /** @return array<string,mixed> */
    public function crawl(array $job, string $token, ?callable $heartbeat = null): array
    {
        $workspaceId = trim((string)($job['workspace_gid'] ?? ''));
        if ($workspaceId === '') throw new RuntimeException('TOGGL_WORKSPACE_REQUIRED');
        $scope = (array)($job['source_scope'] ?? []);
        $options = (array)($job['target_options'] ?? []);
        $selectedProjects = array_values(array_filter(array_map('strval', (array)($scope['project_gids'] ?? []))));
        $includeArchived = (bool)($scope['include_archived'] ?? $options['include_archived'] ?? false);
        $includeArchivedTasks = (bool)($scope['include_archived_tasks'] ?? $options['include_archived_tasks'] ?? $includeArchived);
        $maxTasks = max(0, (int)($scope['max_tasks'] ?? $options['max_tasks'] ?? 0));
        $from = (string)($scope['time_entries_from'] ?? '');
        $to = (string)($scope['time_entries_to'] ?? '');
        if ($from === '' || $to === '') throw new RuntimeException('TOGGL_TIME_ENTRY_RANGE_REQUIRED');

        $stats = [
            'clients' => 0, 'projects' => 0, 'tasks' => 0, 'tags' => 0,
            'users' => 0, 'time_entries' => 0, 'warnings' => [],
        ];
        $workspace = $this->findWorkspace($this->client->workspaces($token), $workspaceId);
        $organizationId = $workspace !== null ? $this->stringId($workspace['organization_id'] ?? $workspace['organization_gid'] ?? null) : null;

        foreach ($this->client->users($token, $workspaceId, $organizationId) as $user) {
            $gid = $this->stringId($user['id'] ?? $user['user_id'] ?? $user['gid'] ?? null);
            if ($gid === '') continue;
            $user['gid'] = $gid;
            $this->repo->upsertUserMapping((int)$job['connection_id'], $user);
            ++$stats['users'];
        }

        foreach ($this->client->clients($token, $workspaceId, $includeArchived) as $client) {
            $gid = $this->stringId($client['id'] ?? $client['gid'] ?? null);
            if ($gid === '') continue;
            $client['gid'] = $gid;
            $this->repo->upsertItem((int)$job['id'], 'client', $gid, [
                'status' => 'pending', 'checksum' => $this->checksum($client), 'payload_json' => $client,
            ]);
            ++$stats['clients'];
        }

        foreach ($this->client->tags($token, $workspaceId) as $tag) {
            if (!empty($tag['deleted_at'])) {
                $stats['warnings'][] = 'Удалённая метка Toggl пропущена.';
                continue;
            }
            $gid = $this->stringId($tag['id'] ?? $tag['gid'] ?? null);
            if ($gid === '') continue;
            $tag['gid'] = $gid;
            $this->repo->upsertItem((int)$job['id'], 'tag', $gid, [
                'status' => 'pending', 'checksum' => $this->checksum($tag), 'payload_json' => $tag,
            ]);
            ++$stats['tags'];
        }

        foreach ($this->client->projects($token, $workspaceId, $includeArchived) as $project) {
            if ($heartbeat !== null && !$heartbeat()) throw new RuntimeException('TOGGL_JOB_LEASE_LOST');
            $projectId = $this->stringId($project['id'] ?? $project['gid'] ?? null);
            if ($projectId === '' || ($selectedProjects !== [] && !in_array($projectId, $selectedProjects, true))) continue;
            $project['gid'] = $projectId;
            $this->repo->upsertItem((int)$job['id'], 'project', $projectId, [
                'source_project_id' => $projectId,
                'status' => 'pending',
                'checksum' => $this->checksum($project),
                'source_updated_at' => $this->date($project['at'] ?? $project['updated_at'] ?? null),
                'payload_json' => $project,
            ]);
            ++$stats['projects'];
            try {
                $sourceTasks = $this->client->tasks($token, $workspaceId, $projectId, $includeArchivedTasks);
                $byId = [];
                foreach ($sourceTasks as $sourceTask) {
                    $sourceTaskId = $this->stringId($sourceTask['id'] ?? $sourceTask['gid'] ?? null);
                    if ($sourceTaskId !== '') $byId[$sourceTaskId] = $sourceTask;
                }
                $depthMemo = [];
                usort($sourceTasks, function (array $left, array $right) use (&$byId, &$depthMemo): int {
                    return $this->taskDepth($left, $byId, $depthMemo) <=> $this->taskDepth($right, $byId, $depthMemo);
                });
                foreach ($sourceTasks as $task) {
                    if ($maxTasks > 0 && $stats['tasks'] >= $maxTasks) break 2;
                    $taskId = $this->stringId($task['id'] ?? $task['gid'] ?? null);
                    if ($taskId === '') continue;
                    $task['gid'] = $taskId;
                    $parent = $this->stringId($task['parent_id'] ?? $task['parent'] ?? null);
                    $this->repo->upsertItem((int)$job['id'], 'task', $taskId, [
                        'source_project_id' => $projectId,
                        'source_parent_id' => $parent !== '' ? $parent : null,
                        'status' => 'pending',
                        'checksum' => $this->checksum($task),
                        'source_updated_at' => $this->date($task['at'] ?? $task['updated_at'] ?? null),
                        'payload_json' => $task,
                    ]);
                    ++$stats['tasks'];
                }
            } catch (RuntimeException $e) {
                if (in_array($e->getMessage(), ['TOGGL_JOB_LEASE_LOST', 'TOGGL_COLLECTION_LIMIT_EXCEEDED'], true)) throw $e;
                $stats['warnings'][] = 'Не удалось загрузить задачи проекта ' . $projectId . '.';
                $this->repo->addLog((int)$job['id'], 'warning', 'crawl_tasks', 'Toggl project tasks could not be loaded.', ['project_id' => $projectId]);
            }
        }

        $filters = [];
        if ($selectedProjects !== []) $filters['project_ids'] = array_map('intval', $selectedProjects);
        $timeEntryCounter = 0;
        $stats['time_entries'] = $this->client->eachTimeEntries($token, $workspaceId, $from, $to, $filters, function (array $entry) use ($job, &$stats, &$timeEntryCounter, $heartbeat): void {
            if ($heartbeat !== null && (++$timeEntryCounter % 50) === 0 && !$heartbeat()) throw new RuntimeException('TOGGL_JOB_LEASE_LOST');
            $sourceId = $this->timeEntryId($entry, (string)$job['workspace_gid']);
            if ($sourceId === '') return;
            $entry['gid'] = $sourceId;
            $projectId = $this->stringId($entry['project_id'] ?? $entry['pid'] ?? null);
            $userId = $this->stringId($entry['user_id'] ?? $entry['uid'] ?? null);
            $this->repo->upsertItem((int)$job['id'], 'time_entry', $sourceId, [
                'source_project_id' => $projectId !== '' ? $projectId : null,
                'source_parent_id' => $userId !== '' ? $userId : null,
                'status' => 'pending',
                'checksum' => $this->checksum($entry),
                'source_updated_at' => $this->date($entry['at'] ?? $entry['updated_at'] ?? $entry['start'] ?? null),
                'payload_json' => $entry,
            ]);
        });

        return $stats;
    }

    private function findWorkspace(array $workspaces, string $id): ?array
    {
        foreach ($workspaces as $workspace) {
            $candidate = $this->stringId($workspace['id'] ?? $workspace['gid'] ?? null);
            if ($candidate === $id) return $workspace;
        }
        return null;
    }

    private function taskDepth(array $task, array &$byId, array &$memo, array $path = []): int
    {
        $id = $this->stringId($task['id'] ?? $task['gid'] ?? null);
        if ($id === '' || isset($memo[$id])) return (int)($memo[$id] ?? 0);
        if (isset($path[$id])) return 0;
        $path[$id] = true;
        $parent = $this->stringId($task['parent_id'] ?? $task['parent'] ?? null);
        if ($parent === '' || !isset($byId[$parent])) return $memo[$id] = 0;
        return $memo[$id] = min(100, 1 + $this->taskDepth((array)$byId[$parent], $byId, $memo, $path));
    }

    private function timeEntryId(array $entry, string $workspaceId = ''): string
    {
        $id = $this->stringId($entry['id'] ?? $entry['time_entry_id'] ?? null);
        if ($id !== '') return $id;
        $workspace = $workspaceId !== '' ? $workspaceId : (string)($entry['workspace_id'] ?? $entry['wid'] ?? '');
        $project = (string)($entry['project_id'] ?? $entry['pid'] ?? '');
        $task = (string)($entry['task_id'] ?? $entry['tid'] ?? '');
        $user = (string)($entry['user_id'] ?? $entry['uid'] ?? '');
        $start = (string)($entry['start'] ?? '');
        $stop = (string)($entry['stop'] ?? $entry['end'] ?? '');
        $duration = (string)($entry['duration'] ?? $entry['seconds'] ?? '');
        $description = (string)($entry['description'] ?? '');
        $tags = (array)($entry['tags'] ?? []);
        if (trim($workspace . $project . $task . $user . $start . $stop . $duration . $description) === '' && $tags === []) return '';
        $stable = implode('|', [
            $workspace, $project, $task, $user, $start, $stop, $duration,
            !empty($entry['billable']) ? '1' : '0', $description,
            (string)json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        return 'hash_' . hash('sha256', $stable);
    }

    private function stringId(mixed $value): string
    {
        if (is_array($value)) $value = $value['id'] ?? $value['gid'] ?? '';
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function date(mixed $value): ?string
    {
        if (!is_scalar($value) || trim((string)$value) === '') return null;
        $time = strtotime((string)$value);
        return $time === false ? null : gmdate('Y-m-d H:i:s', $time);
    }

    private function checksum(array $payload): string
    {
        return hash('sha256', (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }
}
