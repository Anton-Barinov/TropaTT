<?php
declare(strict_types=1);

namespace Module\Crm\ActiveCollabMigration\Service;

use Module\Crm\ActiveCollabMigration\Repository\ActiveCollabMigrationRepository;
use RuntimeException;

final class ActiveCollabCrawler
{
    public function __construct(
        private readonly ActiveCollabClient $client,
        private readonly ActiveCollabMigrationRepository $repo,
    ) {
    }

    /** @return array<string,mixed> */
    public function crawl(array $job, string $token, ?callable $heartbeat = null): array
    {
        $scope = (array)($job['source_scope'] ?? []);
        $options = (array)($job['target_options'] ?? []);
        $selectedProjects = array_values(array_filter(array_map('strval', (array)($scope['project_ids'] ?? $scope['project_gids'] ?? []))));
        $includeArchived = (bool)($scope['include_archived'] ?? $options['include_archived'] ?? false);
        $includeComments = (bool)($options['include_comments'] ?? true);
        $includeAttachments = (bool)($options['include_attachments'] ?? false);
        $includeTime = (bool)($options['include_time_records'] ?? true);
        $maxTasks = max(0, (int)($options['max_tasks'] ?? 0));
        $jobId = (int)$job['id'];
        $stats = ['companies'=>0,'users'=>0,'projects'=>0,'task_lists'=>0,'tasks'=>0,'subtasks'=>0,'labels'=>0,'comments'=>0,'attachments'=>0,'time_records'=>0,'dependencies'=>0,'warnings'=>[]];
        if ($includeArchived) {
            $stats['warnings'][] = 'ActiveCollab API v1 does not document a collection endpoint for archived projects/tasks; only archived objects returned by the active routes can be preserved.';
        }

        foreach ($this->client->companies($token, $includeArchived) as $company) {
            $id = $this->sourceId($company['id'] ?? $company['company_id'] ?? null);
            if ($id === '') continue;
            $company['id'] = $id;
            $this->repo->upsertItem($jobId, 'company', $id, ['status'=>'pending','checksum'=>$this->checksum($company),'payload_json'=>$company]);
            $stats['companies']++;
        }

        foreach ($this->client->users($token) as $user) {
            $id = $this->sourceId($user['id'] ?? $user['user_id'] ?? $user['gid'] ?? null);
            if ($id === '') continue;
            $user['id'] = $id;
            $this->repo->upsertUserMapping((int)$job['connection_id'], $user);
            $stats['users']++;
        }

        $projects = $this->client->projects($token, $includeArchived);
        foreach ($projects as $project) {
            if ($heartbeat !== null && !$heartbeat()) throw new RuntimeException('ACTIVECOLLAB_JOB_LEASE_LOST');
            $projectId = $this->sourceId($project['id'] ?? $project['project_id'] ?? null);
            if ($projectId === '' || ($selectedProjects !== [] && !in_array($projectId, $selectedProjects, true))) continue;
            $project['id'] = $projectId;
            $this->repo->upsertItem($jobId, 'project', $projectId, ['source_parent_id'=>$this->sourceId($project['company_id'] ?? null) ?: null,'status'=>'pending','checksum'=>$this->checksum($project),'source_updated_at'=>$this->date($project['updated_at'] ?? $project['updated_on'] ?? null),'payload_json'=>$project]);
            $stats['projects']++;

            foreach ($this->client->taskLists($token, $projectId) as $list) {
                $listId = $this->sourceId($list['id'] ?? $list['task_list_id'] ?? null);
                if ($listId === '') continue;
                $list['id'] = $listId;
                $this->repo->upsertItem($jobId, 'task_list', $listId, ['source_project_id'=>$projectId,'status'=>'pending','checksum'=>$this->checksum($list),'payload_json'=>$list]);
                $stats['task_lists']++;
            }

            // ActiveCollab returns project and task time records together from
            // this endpoint. Queue them once per project; fetching the same
            // collection from every task both misses project-level records and
            // creates duplicate API work.
            if ($includeTime) foreach ($this->client->projectTimeRecords($token, $projectId) as $record) {
                if ($this->isTruthy($record['is_trashed'] ?? false)) continue;
                $recordId = $this->sourceId($record['id'] ?? $record['time_record_id'] ?? null);
                if ($recordId === '') continue;
                $parentType = strtolower((string)($record['parent_type'] ?? ''));
                $parentId = $parentType === 'task'
                    ? $this->sourceId($record['parent_id'] ?? $record['task_id'] ?? null)
                    : '';
                $record['_task_id'] = $parentId;
                $this->repo->upsertItem($jobId, 'time_record', $recordId, ['source_parent_id'=>$parentId !== '' ? $parentId : null,'source_project_id'=>$projectId,'status'=>'pending','checksum'=>$this->checksum($record),'payload_json'=>$record]);
                $stats['time_records']++;
            }

            foreach ($this->client->tasks($token, $projectId, $includeArchived) as $task) {
                if ($maxTasks > 0 && ($stats['tasks'] + $stats['subtasks']) >= $maxTasks) break 2;
                $this->storeTask($job, $token, $task, $projectId, $stats, $includeComments, $includeAttachments, $includeTime, $heartbeat, $maxTasks, 0, null);
            }
        }
        return $stats;
    }

    /** @param array<string,int> $stats */
    private function storeTask(array $job, string $token, array $task, string $projectId, array &$stats, bool $comments, bool $attachments, bool $timeRecords, ?callable $heartbeat, int $maxTasks, int $depth, ?string $knownParentId): void
    {
        if ($heartbeat !== null && !$heartbeat()) throw new RuntimeException('ACTIVECOLLAB_JOB_LEASE_LOST');
        if ($maxTasks > 0 && ($stats['tasks'] + $stats['subtasks']) >= $maxTasks) return;
        // ActiveCollab subtask payloads may expose task_id as the parent task.
        // Never use that field as the child's own ID: only the child's id is
        // stable enough for source mappings and idempotent imports.
        $id = $this->sourceId($task['id'] ?? $task['task_id_value'] ?? null);
        if ($id === '') return;
        $task['id'] = $id;
        $parentId = $knownParentId ?? $this->sourceId($task['parent_id'] ?? $task['parent_task_id'] ?? ($task['parent']['id'] ?? null) ?? ($task['task_id'] ?? null));
        $type = $parentId !== '' ? 'subtask' : 'task';
        $this->repo->upsertItem((int)$job['id'], $type, $id, ['source_parent_id'=>$parentId !== '' ? $parentId : null,'source_project_id'=>$projectId,'status'=>'pending','checksum'=>$this->checksum($task),'source_updated_at'=>$this->date($task['updated_at'] ?? $task['updated_on'] ?? null),'payload_json'=>$task]);
        $type === 'task' ? $stats['tasks']++ : $stats['subtasks']++;

        foreach ($this->labels($task) as $label) {
            $labelId = $this->sourceId($label['id'] ?? null);
            if ($labelId === '') $labelId = 'name_' . hash('sha256', strtolower((string)($label['name'] ?? '')));
            $label['id'] = $labelId;
            $this->repo->upsertItem((int)$job['id'], 'label', $labelId, ['status'=>'pending','checksum'=>$this->checksum($label),'payload_json'=>$label]);
            $stats['labels']++;
        }
        foreach ($this->dependencies($task) as $dependency) {
            $from = $this->sourceId($dependency['task_id'] ?? $id);
            $to = $this->sourceId($dependency['depends_on_task_id'] ?? $dependency['dependency_id'] ?? $dependency['id'] ?? null);
            if ($from === '' || $to === '' || $from === $to) continue;
            $sourceId = $from . ':' . $to;
            $this->repo->upsertItem((int)$job['id'], 'dependency', $sourceId, ['source_parent_id'=>$from,'source_project_id'=>$projectId,'status'=>'pending','checksum'=>$this->checksum($dependency),'payload_json'=>['source_task_id'=>$from,'depends_on_task_id'=>$to,'dependency_type'=>$dependency['type'] ?? 'FS']]);
            $stats['dependencies']++;
        }
        if ($comments) foreach ($this->client->comments($token, $projectId, $id) as $comment) {
            $commentId = $this->sourceId($comment['id'] ?? $comment['comment_id'] ?? null);
            if ($commentId === '') continue;
            $comment['_task_id'] = $id;
            $this->repo->upsertItem((int)$job['id'], 'comment', $commentId, ['source_parent_id'=>$id,'source_project_id'=>$projectId,'status'=>'pending','checksum'=>$this->checksum($comment),'payload_json'=>$comment]);
            $stats['comments']++;
            if ($attachments && isset($comment['attachments']) && is_array($comment['attachments'])) {
                foreach (array_values(array_filter($comment['attachments'], 'is_array')) as $attachment) {
                    $attachmentId = $this->sourceId($attachment['id'] ?? $attachment['attachment_id'] ?? null);
                    if ($attachmentId === '') continue;
                    $attachment['_task_id'] = $id;
                    $this->repo->upsertItem((int)$job['id'], 'attachment', $attachmentId, ['source_parent_id'=>$id,'source_project_id'=>$projectId,'status'=>'pending','checksum'=>$this->checksum($attachment),'payload_json'=>$attachment]);
                    $stats['attachments']++;
                }
            }
        }
        if ($attachments) {
            // Attachments are often embedded in the task representation. Use
            // that data when present and fall back to the documented endpoint;
            // otherwise large imports make one redundant request per task.
            $taskAttachments = isset($task['attachments']) && is_array($task['attachments'])
                ? array_values(array_filter($task['attachments'], 'is_array'))
                : $this->client->attachments($token, $projectId, $id);
            foreach ($taskAttachments as $attachment) {
                $attachmentId = $this->sourceId($attachment['id'] ?? $attachment['attachment_id'] ?? null);
                if ($attachmentId === '') continue;
                $attachment['_task_id'] = $id;
                $this->repo->upsertItem((int)$job['id'], 'attachment', $attachmentId, ['source_parent_id'=>$id,'source_project_id'=>$projectId,'status'=>'pending','checksum'=>$this->checksum($attachment),'payload_json'=>$attachment]);
                $stats['attachments']++;
            }
        }
        if ($depth >= 20) return;
        $children = [];
        foreach (['subtasks','children'] as $key) if (isset($task[$key]) && is_array($task[$key])) $children = array_merge($children, array_values(array_filter($task[$key], 'is_array')));
        if ($children === []) $children = $this->client->subtasks($token, $projectId, $id);
        foreach ($children as $child) $this->storeTask($job, $token, $child, $projectId, $stats, $comments, $attachments, $timeRecords, $heartbeat, $maxTasks, $depth + 1, $id);
    }

    /** @return array<int,array<string,mixed>> */
    private function labels(array $task): array
    {
        foreach (['labels','tags'] as $key) if (isset($task[$key]) && is_array($task[$key])) return array_values(array_filter(array_map(static fn(mixed $value): array => is_array($value) ? $value : ['id'=>(string)$value,'name'=>(string)$value], $task[$key])));
        return [];
    }

    /** @return array<int,array<string,mixed>> */
    private function dependencies(array $task): array
    {
        $items = [];
        foreach (['dependencies','depends_on','blocked_by'] as $key) if (isset($task[$key]) && is_array($task[$key])) foreach ($task[$key] as $value) $items[] = is_array($value) ? $value : ['id'=>$value];
        return $items;
    }

    private function sourceId(mixed $value): string
    {
        if (is_array($value)) $value = $value['id'] ?? $value['user_id'] ?? '';
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function date(mixed $value): ?string
    {
        if (!is_scalar($value) || trim((string)$value) === '') return null;
        // ActiveCollab v1 returns created_on/updated_on as Unix seconds.
        if (is_numeric($value) && (int)$value >= 100000000) return gmdate('Y-m-d H:i:s', (int)$value);
        $time = strtotime((string)$value);
        return $time === false ? null : gmdate('Y-m-d H:i:s', $time);
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return (int)$value !== 0;
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function checksum(array $payload): string
    {
        return hash('sha256', (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }
}
