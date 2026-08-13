<?php
declare(strict_types=1);

namespace Module\Crm\TogglMigration\Service;

use Api\System\Library\Container;
use Module\Crm\TogglMigration\Repository\TogglMigrationRepository;
use RuntimeException;

final class TogglTargetWriter
{
    public function __construct(
        private readonly Container $container,
        private readonly TogglMigrationRepository $repo,
        private readonly TogglClient $client,
    ) {
    }

    public function service(string $id): mixed
    {
        return $this->container->get($id);
    }

    /** @return array{target_type:string,target_public_id:string,state:string,warnings:array<int,string>} */
    public function client(array $job, array $payload, array $actor): array
    {
        $source = (string)($payload['gid'] ?? '');
        $workspace = (string)$job['workspace_gid'];
        $mapping = $this->repo->findMapping((int)$job['connection_id'], $workspace, 'client', $source);
        if ($mapping && !empty($mapping['target_public_id']) && !$this->repo->targetExists('client', (string)$mapping['target_public_id'])) $mapping = null;
        $status = $this->isActive($payload) ? 'active' : 'archived';
        if ($mapping && !empty($mapping['target_public_id'])) {
            if (($job['mode'] ?? 'import') !== 'sync') return $this->result('client', (string)$mapping['target_public_id'], 'skipped');
            $updated = $this->service('service.client')->update((string)$mapping['target_public_id'], ['title' => $this->name($payload), 'status' => $status], $actor);
            if (!is_array($updated)) throw new RuntimeException('TOGGL_CLIENT_UPDATE_FAILED');
            return $this->result('client', (string)$mapping['target_public_id'], 'updated');
        }
        $created = $this->service('service.client')->create([
            'title' => $this->name($payload),
            'client_type' => 'legal_entity',
            'status' => $status,
            'notes' => 'Imported from Toggl client ' . $source,
            'extra_attributes' => ['source' => 'toggl', 'source_id' => $source, 'workspace_id' => $workspace],
        ], $actor);
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('TOGGL_CLIENT_CREATE_FAILED');
        return $this->result('client', (string)$created['public_id'], 'imported');
    }

    /** @return array{target_type:string,target_public_id:string,state:string,warnings:array<int,string>} */
    public function project(array $job, array $payload, array $actor): array
    {
        $source = (string)($payload['gid'] ?? '');
        $workspace = (string)$job['workspace_gid'];
        $mapping = $this->repo->findMapping((int)$job['connection_id'], $workspace, 'project', $source);
        if ($mapping && !empty($mapping['target_public_id']) && !$this->repo->targetExists('project', (string)$mapping['target_public_id'])) $mapping = null;
        if ($mapping && !empty($mapping['target_public_id'])) {
            if (($job['mode'] ?? 'import') !== 'sync') return $this->result('project', (string)$mapping['target_public_id'], 'skipped');
            $updated = $this->service('service.project')->update((string)$mapping['target_public_id'], [
                'title' => $this->name($payload), 'description' => $this->description($payload),
                'status' => $this->isActive($payload) ? 'active' : 'archived',
            ], $actor);
            if (!is_array($updated)) throw new RuntimeException('TOGGL_PROJECT_UPDATE_FAILED');
            return $this->result('project', (string)$mapping['target_public_id'], 'updated');
        }
        $clientPublicId = null;
        $clientId = $this->sourceId($payload['client_id'] ?? $payload['cid'] ?? null);
        if ($clientId !== '') {
            $clientMapping = $this->repo->findMapping((int)$job['connection_id'], $workspace, 'client', $clientId);
            $clientPublicId = !empty($clientMapping['target_public_id']) ? (string)$clientMapping['target_public_id'] : null;
        }
        $created = $this->service('service.project')->create([
            'title' => $this->name($payload),
            'description' => $this->description($payload),
            'status' => $this->isActive($payload) ? 'active' : 'archived',
            'priority' => 'normal',
            'client_public_id' => $clientPublicId,
            'task_key_prefix' => 'TG' . strtoupper(substr(hash('sha256', $workspace . ':' . $source), 0, 6)),
        ], $actor);
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('TOGGL_PROJECT_CREATE_FAILED');
        return $this->result('project', (string)$created['public_id'], 'imported');
    }

    /** @return array{target_type:string,target_public_id:string,state:string,warnings:array<int,string>} */
    public function tag(array $job, array $payload): array
    {
        $source = (string)($payload['gid'] ?? '');
        $workspace = (string)$job['workspace_gid'];
        $mapping = $this->repo->findMapping((int)$job['connection_id'], $workspace, 'tag', $source);
        if ($mapping && !empty($mapping['target_public_id']) && $this->repo->targetExists('tag', (string)$mapping['target_public_id'])) return $this->result('tag', (string)$mapping['target_public_id'], 'skipped');
        $code = 'toggl_' . substr(hash('sha256', $workspace . ':' . $source), 0, 24);
        $created = $this->service('service.tag')->create([
            'code' => $code,
            'title' => $this->name($payload, 'Toggl tag'),
            'color' => $this->color((string)($payload['color'] ?? '')),
            'description' => 'Imported from Toggl tag ' . $source,
        ]);
        if ($created === 'TAG_CODE_EXISTS') {
            $found = $this->service('service.tag')->list(['search' => $code, 'limit' => 5]);
            $created = (array)($found['items'][0] ?? []);
        }
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('TOGGL_TAG_CREATE_FAILED');
        return $this->result('tag', (string)$created['public_id'], 'imported');
    }

    /** @return array{target_type:string,target_public_id:string,state:string,warnings:array<int,string>} */
    public function task(array $job, array $payload, array $actor): array
    {
        $source = (string)($payload['gid'] ?? '');
        $workspace = (string)$job['workspace_gid'];
        $connection = (int)$job['connection_id'];
        $mapping = $this->repo->findMapping($connection, $workspace, 'task', $source);
        if ($mapping && !empty($mapping['target_public_id']) && !$this->repo->targetExists('task', (string)$mapping['target_public_id'])) $mapping = null;
        $projectId = $this->sourceId($payload['_source_project_id'] ?? $payload['pid'] ?? $payload['project_id'] ?? null);
        $project = $this->repo->findMapping($connection, $workspace, 'project', $projectId);
        if (!$project || empty($project['target_public_id'])) throw new RuntimeException('TOGGL_TASK_PROJECT_NOT_READY');
        $warnings = [];
        $assignee = null;
        $userId = $this->sourceId($payload['user_id'] ?? $payload['uid'] ?? null);
        if ($userId !== '') $assignee = $this->repo->mappedUserId($connection, $userId);
        if ($userId !== '' && $assignee === null) $warnings[] = 'Исполнитель Toggl не сопоставлен с пользователем CRM.';
        $input = [
            'project_public_id' => (string)$project['target_public_id'],
            'title' => $this->name($payload),
            'description' => trim((string)($payload['description'] ?? $payload['notes'] ?? '')),
            'status' => !$this->isActive($payload) ? 'archived' : (!empty($payload['completed']) || !empty($payload['done']) ? 'done' : 'new'),
            'archived' => !$this->isActive($payload),
            'priority' => 'normal',
            'assignee_user_id' => $assignee,
            'source_type' => 'toggl',
            'source_id' => $source,
            'source_url' => (string)($payload['url'] ?? $payload['permalink_url'] ?? ''),
            'source_payload_json' => $payload,
            'created_at' => $this->date($payload['created_at'] ?? $payload['at'] ?? null),
            'updated_at' => $this->date($payload['updated_at'] ?? $payload['at'] ?? null),
        ];
        $parent = $this->sourceId($payload['_source_parent_id'] ?? $payload['parent_id'] ?? $payload['parent'] ?? null);
        if ($parent !== '') {
            $parentMapping = $this->repo->findMapping($connection, $workspace, 'task', $parent);
            if (!empty($parentMapping['target_public_id'])) $input['parent_task_public_id'] = (string)$parentMapping['target_public_id'];
            else throw new RuntimeException('TOGGL_TASK_PARENT_NOT_READY');
        }
        $taskService = $this->service('service.task');
        if ($mapping && !empty($mapping['target_public_id'])) {
            $target = (string)$mapping['target_public_id'];
            if (($job['mode'] ?? 'import') === 'sync') {
                $updated = $taskService->update($target, $input, (int)($actor['id'] ?? 0), $actor);
                if (!is_array($updated)) throw new RuntimeException('TOGGL_TASK_UPDATE_FAILED');
                $state = 'updated';
            } else $state = 'skipped';
        } else {
            $created = $taskService->create($input, $actor);
            if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException(is_string($created) ? 'TOGGL_' . $created : 'TOGGL_TASK_CREATE_FAILED');
            $target = (string)$created['public_id'];
            // TaskService::create persists status_code but does not consume
            // archived_at. Apply the archive through the same public service
            // path so archived Toggl tasks are hidden by CRM queries too.
            if (!$this->isActive($payload)) {
                $archived = $taskService->update($target, ['archived' => true], (int)($actor['id'] ?? 0), $actor);
                if (!is_array($archived)) throw new RuntimeException('TOGGL_TASK_ARCHIVE_FAILED');
            }
            $state = 'imported';
        }
        foreach ((array)($payload['tags'] ?? []) as $tag) {
            $tagId = $this->sourceId(is_array($tag) ? ($tag['id'] ?? $tag['gid'] ?? null) : $tag);
            $tagMapping = $this->repo->findMapping($connection, $workspace, 'tag', $tagId);
            if ($tagId !== '' && !empty($tagMapping['target_public_id']) && !$this->service('service.tag')->attachToTask($target, (string)$tagMapping['target_public_id'], $actor)) {
                $warnings[] = 'Не удалось прикрепить одну из меток.';
            }
        }
        return $this->result('task', $target, $state, $warnings);
    }

    /** @return array{target_type:string,target_public_id:string,state:string,warnings:array<int,string>} */
    public function timeEntry(array $job, array $payload, array $actor): array
    {
        $source = (string)($payload['gid'] ?? '');
        $workspace = (string)$job['workspace_gid'];
        $connection = (int)$job['connection_id'];
        $mapping = $this->repo->findMapping($connection, $workspace, 'time_entry', $source);
        if ($mapping && !empty($mapping['target_public_id']) && !$this->repo->worklogExists((string)$mapping['target_public_id'])) $mapping = null;
        $start = $this->timestamp($payload['start'] ?? null);
        $stop = $this->timestamp($payload['stop'] ?? $payload['end'] ?? null);
        // Reports API v3 returns `seconds`; Core API v9 returns `duration` in
        // seconds. Do not consume the legacy Reports v2 `dur` field because
        // its unit differs across proxy responses.
        $rawDuration = $payload['seconds'] ?? $payload['duration'] ?? null;
        $duration = is_numeric($rawDuration) ? (int)$rawDuration : 0;
        if ($duration < 0) return $this->result('worklog', '', 'skipped', ['Запущенная запись времени пропущена: у неё нет stop.']);
        if ($start === null) throw new RuntimeException('TOGGL_TIME_ENTRY_INVALID');
        if ($stop === null && $duration <= 0) return $this->result('worklog', '', 'skipped', ['Запись времени без stop и duration пропущена.']);
        if ($stop === null) $stop = $start + $duration;
        if ($duration <= 0) $duration = $stop - $start;
        if ($duration <= 0 || $stop <= $start) throw new RuntimeException('TOGGL_TIME_ENTRY_INVALID_INTERVAL');
        $sourceUser = $this->sourceId($payload['user_id'] ?? $payload['uid'] ?? $payload['_source_user_id'] ?? null);
        $userId = $sourceUser !== '' ? $this->repo->mappedUserId($connection, $sourceUser) : null;
        $userPublicId = $sourceUser !== '' ? $this->repo->mappedUserPublicId($connection, $sourceUser) : null;
        if ($userId === null || $userPublicId === null) throw new RuntimeException('TOGGL_TIME_ENTRY_USER_UNMAPPED');
        $taskPublicId = null;
        $sourceTask = $this->sourceId($payload['task_id'] ?? $payload['tid'] ?? null);
        if ($sourceTask !== '') {
            $taskMapping = $this->repo->findMapping($connection, $workspace, 'task', $sourceTask);
            if (!empty($taskMapping['target_public_id'])) $taskPublicId = (string)$taskMapping['target_public_id'];
            if ($taskPublicId === null) throw new RuntimeException('TOGGL_TIME_ENTRY_TASK_NOT_READY');
        }
        if (empty($actor['is_root']) && (int)($actor['id'] ?? 0) !== $userId) throw new RuntimeException('TOGGL_TIME_ENTRY_USER_FORBIDDEN');
        $minutes = max(1, (int)round($duration / 60));
        $note = trim((string)($payload['description'] ?? ''));
        $note .= ($note !== '' ? "\n" : '') . '[Toggl] billable: ' . (!empty($payload['billable']) ? 'yes' : 'no');
        $tagNames = [];
        foreach ((array)($payload['tags'] ?? []) as $tag) $tagNames[] = is_array($tag) ? (string)($tag['name'] ?? $tag['id'] ?? '') : (string)$tag;
        if ($tagNames !== []) $note .= "\n[Toggl] tags: " . implode(', ', array_filter($tagNames));
        $data = [
            'user_public_id' => $userPublicId, 'task_public_id' => $taskPublicId, 'minutes_spent' => $minutes,
            'note' => mb_substr($note, 0, 65000), 'logged_at' => gmdate('Y-m-d H:i:s', $start),
            'started_at' => gmdate('Y-m-d H:i:s', $start), 'ended_at' => gmdate('Y-m-d H:i:s', $stop),
        ];
        $worklogService = $this->service('service.worklog');
        if ($mapping && !empty($mapping['target_public_id'])) {
            $target = (string)$mapping['target_public_id'];
            if (($job['mode'] ?? 'import') === 'sync') {
                $updated = $worklogService->update($target, $data, $actor);
                if (!is_array($updated)) throw new RuntimeException('TOGGL_TIME_ENTRY_UPDATE_FAILED');
                return $this->result('worklog', $target, 'updated');
            }
            return $this->result('worklog', $target, 'skipped');
        }
        $created = $worklogService->create($data, $actor);
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException(is_string($created) ? 'TOGGL_' . $created : 'TOGGL_TIME_ENTRY_CREATE_FAILED');
        return $this->result('worklog', (string)$created['public_id'], 'imported');
    }

    /** @return array{target_type:string,target_public_id:string,state:string,warnings:array<int,string>} */
    private function result(string $type, string $target, string $state, array $warnings = []): array
    {
        return ['target_type' => $type, 'target_public_id' => $target, 'state' => $state, 'warnings' => $warnings];
    }

    private function name(array $payload, string $fallback = 'Без названия'): string
    {
        return mb_substr(trim((string)($payload['name'] ?? $payload['title'] ?? $fallback)) ?: $fallback, 0, 255);
    }

    private function description(array $payload): string { return trim((string)($payload['notes'] ?? $payload['description'] ?? '')); }

    private function isActive(array $payload): bool
    {
        if (array_key_exists('active', $payload)) {
            $value = $this->booleanValue($payload['active']);
            if ($value !== null) return $value;
        }
        if (array_key_exists('archived', $payload)) {
            $value = $this->booleanValue($payload['archived']);
            if ($value !== null) return !$value;
        }
        $status = strtolower(trim((string)($payload['status'] ?? '')));
        return !in_array($status, ['archived', 'inactive', 'deleted'], true);
    }

    private function booleanValue(mixed $value): ?bool
    {
        if (is_bool($value)) return $value;
        if (is_int($value) || is_float($value)) return (int)$value !== 0;
        if (!is_string($value)) return null;
        return match (strtolower(trim($value))) {
            '1', 'true', 'yes', 'on', 'active' => true,
            '0', 'false', 'no', 'off', 'inactive' => false,
            default => null,
        };
    }

    private function color(string $value): string { return preg_match('/^#[0-9a-f]{6}$/i', $value) ? $value : '#64748b'; }

    private function sourceId(mixed $value): string
    {
        if (is_array($value)) $value = $value['id'] ?? $value['gid'] ?? '';
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function timestamp(mixed $value): ?int
    {
        if (!is_scalar($value) || trim((string)$value) === '') return null;
        $time = strtotime((string)$value);
        return $time === false ? null : $time;
    }

    private function date(mixed $value): ?string
    {
        $time = $this->timestamp($value);
        return $time === null ? null : gmdate('Y-m-d H:i:s', $time);
    }
}
