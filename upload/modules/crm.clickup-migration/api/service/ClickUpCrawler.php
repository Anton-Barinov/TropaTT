<?php
declare(strict_types=1);

namespace Module\Crm\ClickUpMigration\Service;

use Module\Crm\ClickUpMigration\Repository\ClickUpMigrationRepository;
use RuntimeException;

final class ClickUpCrawler
{
    public function __construct(
        private readonly ClickUpClient $client,
        private readonly ClickUpMigrationRepository $repo,
    ) {
    }

    /** @return array<string,mixed> */
    public function crawl(array $job, string $token, ?callable $heartbeat = null): array
    {
        $scope = (array)($job['source_scope'] ?? []);
        $options = (array)($job['target_options'] ?? []);
        $includeArchived = (bool)($scope['include_archived'] ?? $options['include_archived'] ?? false);
        $includeClosed = (bool)($scope['include_closed'] ?? $options['include_closed'] ?? true);
        $includeComments = array_key_exists('include_comments', $scope) ? (bool)$scope['include_comments'] : (bool)($options['include_comments'] ?? true);
        $includeAttachments = array_key_exists('include_attachments', $scope) ? (bool)$scope['include_attachments'] : (bool)($options['include_attachments'] ?? false);
        $includeTime = array_key_exists('include_time_tracking', $scope) ? (bool)$scope['include_time_tracking'] : (bool)($options['include_time_tracking'] ?? true);
        $includeGoals = (bool)($scope['include_goals'] ?? $options['include_goals'] ?? false);
        $updatedSince = trim((string)($scope['updated_since'] ?? $options['updated_since'] ?? ''));
        $completedSince = trim((string)($scope['completed_since'] ?? $options['completed_since'] ?? ''));
        $completedUntil = trim((string)($scope['completed_until'] ?? $options['completed_until'] ?? ''));
        $timeEndDate = $this->epochMilliseconds($scope['time_end_date'] ?? $options['time_end_date'] ?? null) ?? (int)(microtime(true) * 1000);
        $timeStartDate = $this->epochMilliseconds($scope['time_start_date'] ?? $options['time_start_date'] ?? null);
        if ($timeStartDate === null) {
            // ClickUp applies a short default window when no dates are sent.
            // Make the fallback explicit and visible instead of silently
            // presenting a partial time-tracking migration as complete.
            $timeStartDate = max(0, $timeEndDate - (30 * 86400 * 1000));
            $statsWarning = 'Time entries without an explicit date range are limited to the last 30 days.';
        } else {
            $statsWarning = null;
        }
        if ($timeEndDate < $timeStartDate) {
            throw new RuntimeException('CLICKUP_TIME_RANGE_INVALID');
        }
        $maxTasks = max(0, (int)($scope['max_tasks'] ?? $options['max_tasks'] ?? 0));
        $selectedTeams = array_values(array_filter(array_map('strval', (array)($scope['team_ids'] ?? []))));
        $selectedSpaces = array_values(array_filter(array_map('strval', (array)($scope['space_ids'] ?? []))));
        $spacesPerRun = max(1, min(20, (int)($options['spaces_per_run'] ?? 1)));
        $afterSpaceId = trim((string)($scope['_after_space_id'] ?? ''));
        $afterFound = $afterSpaceId === '';
        $processedSpaces = 0;
        $stopAfterSpace = false;
        $taskCount = 0;
        $stats = ['teams'=>0,'spaces'=>0,'folders'=>0,'lists'=>0,'tasks'=>0,'subtasks'=>0,'checklists'=>0,'checklist_items'=>0,'comments'=>0,'attachments'=>0,'tags'=>0,'custom_fields'=>0,'time_entries'=>0,'dependencies'=>0,'users'=>0,'goals'=>0,'warnings'=>[],'crawl_complete'=>true,'capped'=>false,'last_space_id'=>null];
        if ($statsWarning !== null) $stats['warnings'][] = $statsWarning;

        $teams = $this->client->teams($token);
        foreach ($teams as $team) {
            if ($heartbeat !== null && !$heartbeat()) throw new RuntimeException('CLICKUP_JOB_LEASE_LOST');
            $teamId = (string)($team['id'] ?? '');
            if ($teamId === '' || ($selectedTeams !== [] && !in_array($teamId, $selectedTeams, true))) continue;
            $stats['teams']++;
            $this->item($job, 'team', $teamId, null, null, $team);
            $teamMemberIds = [];
            foreach ((array)($team['members'] ?? []) as $member) {
                $user = is_array($member['user'] ?? null) ? $member['user'] : $member;
                $userId = (string)($user['id'] ?? '');
                if ($userId === '') continue;
                $teamMemberIds[] = $userId;
                $this->repo->upsertUserMapping((int)$job['connection_id'], $user);
                $this->item($job, 'user', $userId, $teamId, null, $user);
                $stats['users']++;
            }
            if ($includeGoals) {
                try {
                    foreach ($this->client->goals($token, $teamId) as $goal) {
                        $id = (string)($goal['id'] ?? '');
                        if ($id !== '') { $this->item($job, 'goal', $id, $teamId, null, $goal); $stats['goals']++; }
                    }
                } catch (\Throwable) { $stats['warnings'][] = 'Goals were not available for team ' . $teamId . '.'; }
            }
            foreach ($this->client->spaces($token, $teamId, $includeArchived) as $space) {
                if ($heartbeat !== null && !$heartbeat()) throw new RuntimeException('CLICKUP_JOB_LEASE_LOST');
                $spaceId = (string)($space['id'] ?? '');
                if ($spaceId === '' || ($selectedSpaces !== [] && !in_array($spaceId, $selectedSpaces, true))) continue;
                if (!$includeArchived && !empty($space['archived'])) continue;
                if (!$afterFound) { if ($spaceId !== $afterSpaceId) continue; $afterFound = true; continue; }
                $stats['spaces']++; $processedSpaces++; $stats['last_space_id'] = $spaceId;
                $this->item($job, 'space', $spaceId, $teamId, $spaceId, $space);
                $timeEntriesByTask = [];
                $folders = $this->client->folders($token, $spaceId, $includeArchived);
                foreach ($folders as $folder) {
                    $folderId = (string)($folder['id'] ?? '');
                    if ($folderId === '') continue;
                    $stats['folders']++;
                    $this->item($job, 'folder', $folderId, $spaceId, $spaceId, $folder);
                    $this->crawlLists($job, $token, $teamId, $spaceId, $folderId, $this->client->listsInFolder($token, $folderId, $includeArchived), $includeArchived, $includeClosed, $includeComments, $includeAttachments, $includeTime, $updatedSince, $completedSince, $completedUntil, $timeStartDate, $timeEndDate, $teamMemberIds, $timeEntriesByTask, $maxTasks, $taskCount, $stats, $heartbeat);
                    if ($maxTasks > 0 && $taskCount >= $maxTasks) { $stats['capped'] = true; break; }
                }
                $this->crawlLists($job, $token, $teamId, $spaceId, null, $this->client->folderlessLists($token, $spaceId, $includeArchived), $includeArchived, $includeClosed, $includeComments, $includeAttachments, $includeTime, $updatedSince, $completedSince, $completedUntil, $timeStartDate, $timeEndDate, $teamMemberIds, $timeEntriesByTask, $maxTasks, $taskCount, $stats, $heartbeat);
                if ($includeTime) {
                    if ($teamMemberIds === []) $stats['warnings'][] = 'ClickUp did not return workspace members; time tracking may include only the current user.';
                    try {
                        // Load after task selection so max_tasks cannot create
                        // orphan time-entry items for tasks outside this crawl.
                        $stats['time_entries'] += $this->loadTeamTimeEntries($job, $token, $teamId, $timeStartDate, $timeEndDate, $teamMemberIds, [$spaceId]);
                    } catch (\Throwable) {
                        $stats['warnings'][] = 'Workspace time entries were not fully available for team ' . $teamId . '.';
                    }
                }
                if ($maxTasks > 0 && $taskCount >= $maxTasks) { $stats['capped'] = true; $stopAfterSpace = true; break; }
                if ($processedSpaces >= $spacesPerRun) { $stopAfterSpace = true; break; }
            }
            if ($stopAfterSpace) break;
        }
        $stats['crawl_complete'] = !$stopAfterSpace && !$stats['capped'];
        if ($stats['capped']) $stats['warnings'][] = 'The configured task limit was reached; remaining source tasks were intentionally not loaded.';
        if (!$includeGoals) $stats['warnings'][] = 'Goals are optional and were not requested.';
        $stats['warnings'][] = 'ClickUp Docs are not imported: public API export is incomplete for preserving rich document structure.';
        return $stats;
    }

    /** @param array<int,array<string,mixed>> $lists */
    private function crawlLists(array $job, string $token, string $teamId, string $spaceId, ?string $folderId, array $lists, bool $includeArchived, bool $includeClosed, bool $includeComments, bool $includeAttachments, bool $includeTime, string $updatedSince, string $completedSince, string $completedUntil, int $timeStartDate, int $timeEndDate, array $assigneeIds, array &$timeEntriesByTask, int $maxTasks, int &$taskCount, array &$stats, ?callable $heartbeat): void
    {
        foreach ($lists as $list) {
            $listId = (string)($list['id'] ?? '');
            if ($listId === '' || (!$includeArchived && !empty($list['archived']))) continue;
            $stats['lists']++;
            $this->item($job, 'list', $listId, $folderId ?: $spaceId, $spaceId, $list + ['_team_id' => $teamId, '_folder_id' => $folderId]);
            try {
                foreach ($this->client->fields($token, $listId) as $field) {
                    $fieldId = (string)($field['id'] ?? '');
                    if ($fieldId !== '') { $this->item($job, 'custom_field', $listId . ':' . $fieldId, $listId, $spaceId, $field + ['_list_id' => $listId]); $stats['custom_fields']++; }
                }
            } catch (\Throwable) { $stats['warnings'][] = 'Custom fields were not available for list ' . $listId . '.'; }
            for ($page = 0; $page < 10000; $page++) {
                $tasks = $this->client->tasks($token, $listId, $includeArchived, $includeClosed, $page, $updatedSince, $completedSince, $completedUntil);
                if ($tasks === []) break;
                foreach ($tasks as $summary) {
                    if ($heartbeat !== null && !$heartbeat()) throw new RuntimeException('CLICKUP_JOB_LEASE_LOST');
                    if ($maxTasks > 0 && $taskCount >= $maxTasks) return;
                    $taskId = (string)($summary['id'] ?? '');
                    if ($taskId === '') continue;
                    $task = $summary;
                    try { $task = $this->client->task($token, $taskId); } catch (\Throwable) { $stats['warnings'][] = 'Task ' . $taskId . ' details were not fully loaded.'; }
                    $task['_list_id'] = $listId; $task['_space_id'] = $spaceId; $task['_team_id'] = $teamId;
                    $parent = (string)($task['parent'] ?? $summary['parent'] ?? '');
                    $task['parent_id'] = $parent;
                    $this->item($job, 'task', $taskId, $parent !== '' ? $parent : null, $spaceId, $task);
                    $stats['tasks']++; $taskCount++;
                    if ($parent !== '') $stats['subtasks']++;
                    $this->storeTaskChildren($job, $task, $teamId, $spaceId, $includeComments, $includeAttachments, $includeTime, $timeStartDate, $timeEndDate, $assigneeIds, $timeEntriesByTask, $stats, $token);
                    if ($maxTasks > 0 && $taskCount >= $maxTasks) return;
                }
                if (count($tasks) < 100) break;
            }
        }
    }

    private function storeTaskChildren(array $job, array $task, string $teamId, string $spaceId, bool $comments, bool $attachments, bool $includeTime, int $timeStartDate, int $timeEndDate, array $assigneeIds, array &$timeEntriesByTask, array &$stats, string $token): void
    {
        $taskId = (string)($task['id'] ?? '');
        foreach ((array)($task['tags'] ?? []) as $tag) {
            $name = trim((string)($tag['name'] ?? '')); if ($name === '') continue;
            $tagId = $spaceId . ':' . hash('sha256', mb_strtolower($name));
            $this->item($job, 'tag', $tagId, $taskId, $spaceId, $tag + ['id' => $tagId, '_task_id' => $taskId]); $stats['tags']++;
        }
        foreach ((array)($task['checklists'] ?? []) as $checklist) {
            $checkId = (string)($checklist['id'] ?? ''); if ($checkId === '') continue;
            $this->item($job, 'checklist', $checkId, $taskId, $spaceId, $checklist + ['_task_id' => $taskId]); $stats['checklists']++;
            foreach ((array)($checklist['items'] ?? $checklist['checklist_items'] ?? []) as $item) {
                $itemId = (string)($item['id'] ?? hash('sha256', $checkId . ':' . (string)($item['name'] ?? '')));
                $this->item($job, 'checklist_item', $itemId, $checkId, $spaceId, $item + ['_checklist_id' => $checkId]); $stats['checklist_items']++;
            }
        }
        foreach ((array)($task['dependencies'] ?? []) as $dependency) {
            $depends = (string)($dependency['depends_on'] ?? $dependency['depends_on_task_id'] ?? $dependency['task_id'] ?? '');
            if ($depends === '') continue;
            $id = $taskId . ':' . $depends . ':' . (string)($dependency['type'] ?? 'FS');
            $this->item($job, 'dependency', $id, $taskId, $spaceId, $dependency + ['task_id' => $taskId, 'depends_on_task_id' => $depends]); $stats['dependencies']++;
        }
        if ($includeAttachments) {
            foreach ((array)($task['attachments'] ?? []) as $attachment) {
                if (!is_array($attachment)) continue;
                $id = (string)($attachment['id'] ?? '');
                if ($id === '') continue;
                $this->item($job, 'attachment', $id, $taskId, $spaceId, $attachment + ['_task_id' => $taskId]);
                $stats['attachments']++;
            }
        }
        if ($comments) {
            try { $this->client->comments($token, $taskId, function (array $comment) use ($job, $taskId, $spaceId, $attachments, &$stats): bool {
                $id = (string)($comment['id'] ?? ''); if ($id === '') return true;
                $this->item($job, 'comment', $id, $taskId, $spaceId, $comment + ['_task_id' => $taskId]); $stats['comments']++;
                foreach ((array)($comment['attachments'] ?? (isset($comment['attachment']) ? [$comment['attachment']] : [])) as $attachment) {
                    if (!$attachments || !is_array($attachment)) continue;
                    $aid = (string)($attachment['id'] ?? ''); if ($aid === '') $aid = $id . ':' . hash('sha256', (string)($attachment['url'] ?? ''));
                    $this->item($job, 'attachment', $aid, $id, $spaceId, $attachment + ['_task_id' => $taskId, '_comment_id' => $id]); $stats['attachments']++;
                }
                return true;
            }); } catch (\Throwable) { $stats['warnings'][] = 'Comments were not fully loaded for task ' . $taskId . '.'; }
        }
        if ($includeTime) {
            foreach ((array)($timeEntriesByTask[$taskId] ?? []) as $entry) {
                $id = (string)($entry['id'] ?? ''); if ($id === '') $id = hash('sha256', $taskId . ':' . json_encode($entry));
                $this->item($job, 'time_entry', $id, $taskId, $spaceId, $entry + ['_task_id' => $taskId]); $stats['time_entries']++;
            }
        }
    }

    private function loadTeamTimeEntries(array $job, string $token, string $teamId, int $startDate, int $endDate, array $assigneeIds, array $selectedSpaces): int
    {
        $count = 0;
        $seen = [];
        $window = 90 * 86400 * 1000;
        $assignees = array_values(array_unique(array_filter(array_map('strval', $assigneeIds), static fn(string $id): bool => $id !== '')));
        if ($assignees === []) $assignees = [null];
        foreach ($assignees as $assigneeId) {
            for ($from = $startDate; $from <= $endDate; $from = $to + 1) {
                $to = min($endDate, $from + $window - 1);
                foreach ($this->client->timeEntries($token, $teamId, $from, $to, null, $assigneeId) as $entry) {
                    $entryId = (string)($entry['id'] ?? '');
                    $taskId = (string)($entry['task']['id'] ?? $entry['task_id'] ?? '');
                    if ($taskId === '') continue;
                    if ($this->repo->findItem((int)$job['id'], 'task', $taskId) === null) {
                        // A sync may receive a time entry for an unchanged task
                        // filtered out by updated_since; an existing source map
                        // is sufficient in that mode. A capped initial import
                        // must remain strictly limited to crawled tasks.
                        $mapped = ($job['mode'] ?? 'import') === 'sync'
                            ? $this->repo->findMapping((int)$job['connection_id'], 'task', $taskId)
                            : null;
                        if ($mapped === null || empty($mapped['target_public_id'])) continue;
                    }
                    $spaceId = (string)($entry['task_location']['space_id'] ?? '');
                    if ($selectedSpaces !== [] && ($spaceId === '' || !in_array($spaceId, $selectedSpaces, true))) continue;
                    if ($entryId !== '' && isset($seen[$entryId])) continue;
                    if ($entryId !== '') $seen[$entryId] = true;
                    $this->item(
                        $job,
                        'time_entry',
                        $entryId !== '' ? $entryId : hash('sha256', $taskId . ':' . json_encode($entry)),
                        $taskId,
                        $spaceId,
                        $entry + ['_task_id' => $taskId, '_team_id' => $teamId]
                    );
                    ++$count;
                }
                if ($to >= $endDate) break;
            }
        }
        return $count;
    }

    private function item(array $job, string $type, string $id, ?string $parent, ?string $project, array $payload): void
    {
        $this->repo->upsertItem((int)$job['id'], $type, $id, ['source_parent_id' => $parent, 'source_project_id' => $project, 'status' => 'pending', 'checksum' => hash('sha256', (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)), 'source_updated_at' => $this->date($payload['date_updated'] ?? $payload['dateUpdated'] ?? $payload['updated_at'] ?? null), 'payload_json' => $payload]);
    }

    private function date(mixed $value): ?string
    {
        $raw = trim((string)$value); if ($raw === '') return null;
        if (is_numeric($raw)) { $n = (int)$raw; if ($n > 100000000000) $n = (int)floor($n / 1000); return gmdate('Y-m-d H:i:s', $n); }
        $ts = strtotime($raw); return $ts === false ? null : gmdate('Y-m-d H:i:s', $ts);
    }

    private function epochMilliseconds(mixed $value): ?int
    {
        $raw = trim((string)$value);
        if ($raw === '') return null;
        if (is_numeric($raw)) {
            $number = (int)$raw;
            return $number > 100000000000 ? $number : $number * 1000;
        }
        $timestamp = strtotime($raw . (str_contains($raw, 'T') || str_contains($raw, ' ') ? '' : ' 00:00:00 UTC'));
        return $timestamp === false ? null : $timestamp * 1000;
    }
}
