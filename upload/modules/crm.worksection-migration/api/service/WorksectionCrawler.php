<?php
declare(strict_types=1);

namespace Module\Crm\WorksectionMigration\Service;

use Module\Crm\WorksectionMigration\Repository\WorksectionMigrationRepository;
use RuntimeException;

final class WorksectionCrawler
{
    public function __construct(
        private readonly WorksectionClient $client,
        private readonly WorksectionMigrationRepository $repo,
    ) {
    }

    /** @return array<string,mixed> */
    public function crawl(array $job, string $token, ?callable $heartbeat = null): array
    {
        $scope = (array)($job['source_scope'] ?? []);
        $options = (array)($job['target_options'] ?? []);
        $selectedProjects = array_values(array_filter(array_map('strval', (array)($scope['project_ids'] ?? $scope['project_gids'] ?? []))));
        $includeArchived = (bool)($scope['include_archived'] ?? $options['include_archived'] ?? false);
        $includeCompleted = (bool)($scope['include_completed'] ?? $options['include_completed'] ?? true);
        $includeComments = (bool)($options['include_comments'] ?? true);
        $includeAttachments = (bool)($options['include_attachments'] ?? false);
        $includeTags = (bool)($options['include_tags'] ?? true);
        $includeTime = (bool)($options['include_time_records'] ?? true);
        $maxTasks = max(0, (int)($scope['max_tasks'] ?? $options['max_tasks'] ?? 0));
        $jobId = (int)$job['id'];
        $stats = ['users'=>0,'project_groups'=>0,'projects'=>0,'tasks'=>0,'subtasks'=>0,'labels'=>0,'comments'=>0,'attachments'=>0,'time_records'=>0,'dependencies'=>0,'warnings'=>[]];

        foreach ($this->client->users($token) as $user) {
            $id = $this->sourceId($user['id'] ?? $user['user_id'] ?? null);
            if ($id === '') continue;
            $user['id'] = $id;
            $this->repo->upsertUserMapping((int)$job['connection_id'], $user);
            $stats['users']++;
        }

        foreach ($this->client->projectGroups($token) as $group) {
            $id = $this->sourceId($group['id'] ?? $group['group_id'] ?? null);
            if ($id === '') continue;
            $group['id'] = $id;
            $this->repo->upsertItem($jobId, 'project_group', $id, ['status'=>'pending','checksum'=>$this->checksum($group),'payload_json'=>$group]);
            $stats['project_groups']++;
        }

        $projects = $this->client->projects($token, $includeArchived);
        foreach ($projects as $project) {
            if ($heartbeat !== null && !$heartbeat()) throw new RuntimeException('WORKSECTION_JOB_LEASE_LOST');
            $projectId = $this->sourceId($project['id'] ?? $project['project_id'] ?? null);
            if ($projectId === '' || ($selectedProjects !== [] && !in_array($projectId, $selectedProjects, true))) continue;
            $project['id'] = $projectId;
            $this->repo->upsertItem($jobId, 'project', $projectId, ['source_parent_id'=>$this->sourceId($project['group'] ?? $project['group_id'] ?? null) ?: null,'status'=>'pending','checksum'=>$this->checksum($project),'source_updated_at'=>$this->date($project['updated_at'] ?? $project['date_added'] ?? null),'payload_json'=>$project]);
            $stats['projects']++;

            if ($includeTime) foreach ($this->client->projectCosts($token, $projectId) as $cost) {
                $recordId = $this->sourceId($cost['id'] ?? $cost['cost_id'] ?? null);
                if ($recordId === '') continue;
                $taskId = $this->sourceId($cost['task_id'] ?? ($cost['task']['id'] ?? null));
                $cost['_task_id'] = $taskId;
                $this->repo->upsertItem($jobId, 'time_record', $recordId, ['source_parent_id'=>$taskId !== '' ? $taskId : null,'source_project_id'=>$projectId,'status'=>'pending','checksum'=>$this->checksum($cost),'payload_json'=>$cost]);
                $stats['time_records']++;
            }

            // search_tasks returns the flat set of all tasks (completed and
            // subtasks included) with text/files/comments/relations embedded.
            $tasks = $this->client->tasks($token, $projectId);
            foreach ($tasks as $task) {
                if ($maxTasks > 0 && ($stats['tasks'] + $stats['subtasks']) >= $maxTasks) break 2;
                $this->storeTask($job, $task, $projectId, $stats, $token, $includeComments, $includeAttachments, $includeTags, $heartbeat);
            }
        }
        if (!$includeCompleted) {
            $stats['warnings'][] = 'Worksection list endpoints hide completed tasks; include_completed=false intentionally skips them.';
        }
        return $stats;
    }

    /** @param array<string,int> $stats */
    private function storeTask(array $job, array $task, string $projectId, array &$stats, string $token, bool $comments, bool $attachments, bool $tags, ?callable $heartbeat): void
    {
        if ($heartbeat !== null && !$heartbeat()) throw new RuntimeException('WORKSECTION_JOB_LEASE_LOST');
        $id = $this->sourceId($task['id'] ?? $task['task_id'] ?? null);
        if ($id === '') return;
        $task['id'] = $id;
        $parent = $this->sourceId($task['parent'] ?? $task['parent_id'] ?? null);
        if ($parent === '' && is_array($task['parent'] ?? null)) $parent = $this->sourceId($task['parent']['id'] ?? null);
        $type = $parent !== '' ? 'subtask' : 'task';
        $this->repo->upsertItem((int)$job['id'], $type, $id, ['source_parent_id'=>$parent !== '' ? $parent : null,'source_project_id'=>$projectId,'status'=>'pending','checksum'=>$this->checksum($task),'source_updated_at'=>$this->date($task['updated_at'] ?? $task['date_added'] ?? null),'payload_json'=>$task]);
        $type === 'task' ? $stats['tasks']++ : $stats['subtasks']++;

        foreach ($this->labels($task) as $label) {
            $labelId = $this->sourceId($label['id'] ?? null);
            if ($labelId === '') $labelId = 'name_' . hash('sha256', strtolower((string)($label['name'] ?? '')));
            $label['id'] = $labelId;
            $this->repo->upsertItem((int)$job['id'], 'label', $labelId, ['status'=>'pending','checksum'=>$this->checksum($label),'payload_json'=>$label]);
            $stats['labels']++;
        }

        // Task relations are queued as dependencies only when both ends are
        // known after the whole crawl (writer resolves the mappings).
        foreach ($this->relations($task) as $relation) {
            $from = $this->sourceId($relation['task_id'] ?? $relation['source_task_id'] ?? $id);
            $to = $this->sourceId($relation['depends_on_task_id'] ?? $relation['related_task_id'] ?? $relation['id'] ?? null);
            if ($from === '' || $to === '' || $from === $to) continue;
            $this->repo->upsertItem((int)$job['id'], 'dependency', $from . ':' . $to, ['source_parent_id'=>$from,'source_project_id'=>$projectId,'status'=>'pending','checksum'=>$this->checksum($relation),'payload_json'=>['source_task_id'=>$from,'depends_on_task_id'=>$to,'dependency_type'=>$this->dependencyType($relation)]]);
            $stats['dependencies']++;
        }

        $embeddedComments = $this->arrayValue($task, ['comments']);
        $embeddedFiles = $this->arrayValue($task, ['files']);
        if ($comments) {
            $commentItems = $embeddedComments !== [] ? $embeddedComments : $this->client->taskComments($token, $id);
            foreach ($commentItems as $comment) {
                $commentId = $this->sourceId($comment['id'] ?? $comment['comment_id'] ?? null);
                if ($commentId === '') continue;
                $comment['_task_id'] = $id;
                $this->repo->upsertItem((int)$job['id'], 'comment', $commentId, ['source_parent_id'=>$id,'source_project_id'=>$projectId,'status'=>'pending','checksum'=>$this->checksum($comment),'payload_json'=>$comment]);
                $stats['comments']++;
                if ($attachments) foreach ($this->arrayValue($comment, ['files', 'attachments']) as $file) $this->queueFile($job, $file, $id, $projectId, $stats);
            }
        }
        if ($attachments) {
            $fileItems = $embeddedFiles !== [] ? $embeddedFiles : $this->client->taskFiles($token, $id);
            foreach ($fileItems as $file) $this->queueFile($job, $file, $id, $projectId, $stats);
        }
        if ($tags) foreach ($this->client->taskTags($token, $id) as $tag) {
            $tagId = $this->sourceId($tag['id'] ?? null);
            if ($tagId === '') $tagId = 'name_' . hash('sha256', strtolower((string)($tag['name'] ?? '')));
            $tag['id'] = $tagId;
            $this->repo->upsertItem((int)$job['id'], 'label', $tagId, ['status'=>'pending','checksum'=>$this->checksum($tag),'payload_json'=>$tag]);
            $stats['labels']++;
        }
    }

    private function queueFile(array $job, array $file, string $taskId, string $projectId, array &$stats): void
    {
        if (!is_array($file)) return;
        $fileId = $this->sourceId($file['id'] ?? $file['file_id'] ?? null);
        if ($fileId === '') return;
        $file['_task_id'] = $taskId;
        $this->repo->upsertItem((int)$job['id'], 'attachment', $fileId, ['source_parent_id'=>$taskId,'source_project_id'=>$projectId,'status'=>'pending','checksum'=>$this->checksum($file),'payload_json'=>$file]);
        $stats['attachments']++;
    }

    /** @return array<int,array<string,mixed>> */
    private function labels(array $task): array
    {
        foreach (['tags','labels'] as $key) if (isset($task[$key]) && is_array($task[$key])) return array_values(array_filter(array_map(static fn(mixed $value): array => is_array($value) ? $value : ['id'=>(string)$value,'name'=>(string)$value], $task[$key])));
        return [];
    }

    /** @return array<int,array<string,mixed>> */
    private function relations(array $task): array
    {
        $items = [];
        foreach (['relations','dependencies','depends_on','blocked_by'] as $key) if (isset($task[$key]) && is_array($task[$key])) foreach ($task[$key] as $value) $items[] = is_array($value) ? $value : ['id'=>$value];
        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    private function arrayValue(array $payload, array $keys): array
    {
        foreach ($keys as $key) if (isset($payload[$key]) && is_array($payload[$key])) return array_values(array_filter($payload[$key], 'is_array'));
        return [];
    }

    private function dependencyType(array $relation): string
    {
        $type = strtoupper(trim((string)($relation['type'] ?? $relation['relation_type'] ?? 'FS')));
        return in_array($type, ['FS','SS','FF','SF','BLOCKS'], true) ? $type : 'FS';
    }

    private function sourceId(mixed $value): string
    {
        if (is_array($value)) $value = $value['id'] ?? $value['user_id'] ?? $value['task_id'] ?? '';
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function date(mixed $value): ?string
    {
        if (!is_scalar($value) || trim((string)$value) === '') return null;
        $raw = trim((string)$value);
        if (is_numeric($raw) && (int)$raw >= 100000000) return gmdate('Y-m-d H:i:s', (int)$raw);
        // Worksection dates are often d.m.Y.
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$/', $raw, $m)) {
            $h = (int)($m[4] ?? 0); $i = (int)($m[5] ?? 0); $s = (int)($m[6] ?? 0);
            $timestamp = @mktime($h, $i, $s, (int)$m[2], (int)$m[1], (int)$m[3]);
            if ($timestamp !== false) return gmdate('Y-m-d H:i:s', $timestamp);
        }
        $time = strtotime($raw);
        return $time === false ? null : gmdate('Y-m-d H:i:s', $time);
    }

    private function checksum(array $payload): string
    {
        return hash('sha256', (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }
}
