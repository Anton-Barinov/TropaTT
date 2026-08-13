<?php
declare(strict_types=1);

namespace Module\Crm\TodoistMigration\Service;

use Api\System\Library\Container;
use Module\Crm\TodoistMigration\Repository\TodoistMigrationRepository;
use RuntimeException;

final class TodoistTargetWriter
{
    public function __construct(
        private readonly Container $container,
        private readonly TodoistMigrationRepository $repo,
        private readonly TodoistClient $client
    ) {
    }

    public function service(string $id): mixed
    {
        return $this->container->get($id);
    }

    private function mapping(array $job, string $type, string $source): ?array
    {
        return $this->repo->findMapping((int)$job['connection_id'], $type, $source);
    }

    private function title(array $payload): string
    {
        return trim((string)($payload['name'] ?? $payload['content'] ?? 'Untitled')) ?: 'Untitled';
    }

    private function date(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        $timestamp = strtotime($value);
        return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function priority(mixed $value): string
    {
        return match ((int)$value) { 4 => 'urgent', 3 => 'high', 2 => 'medium', default => 'normal' };
    }

    public function project(array $job, array $payload, array $actor): array
    {
        $source = (string)($payload['id'] ?? '');
        $map = $this->mapping($job, 'project', $source);
        if ($map && !empty($map['target_public_id'])) {
            try {
                $existing = $this->service('service.project')->get((string)$map['target_public_id'], $actor);
                if ($existing && ($job['mode'] ?? 'import') !== 'sync') return ['target_type' => 'project', 'target_public_id' => $map['target_public_id'], 'state' => 'skipped', 'warnings' => []];
                if ($existing) {
                    $updated = $this->service('service.project')->update((string)$map['target_public_id'], ['title' => $this->title($payload), 'description' => (string)($payload['description'] ?? '')], $actor);
                    if (!is_array($updated)) throw new RuntimeException('TODOIST_PROJECT_UPDATE_FAILED');
                    return ['target_type' => 'project', 'target_public_id' => $map['target_public_id'], 'state' => 'updated', 'warnings' => []];
                }
            } catch (\Throwable) {
                // A stale mapping is repaired by creating the missing target below.
            }
        }
        $created = $this->service('service.project')->create(['title' => $this->title($payload), 'description' => (string)($payload['description'] ?? ''), 'status' => !empty($payload['is_archived']) ? 'archived' : 'active', 'priority' => 'normal', 'task_key_prefix' => 'TD' . strtoupper(substr(hash('sha256', $source), 0, 4))], $actor);
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('TODOIST_PROJECT_CREATE_FAILED');
        return ['target_type' => 'project', 'target_public_id' => (string)$created['public_id'], 'state' => 'imported', 'warnings' => []];
    }

    public function section(array $job, array $payload, array $actor): array
    {
        $source = (string)($payload['id'] ?? '');
        $map = $this->mapping($job, 'section', $source);
        if ($map && !empty($map['target_public_id'])) return ['target_type' => 'project_module', 'target_public_id' => $map['target_public_id'], 'state' => 'skipped', 'warnings' => []];
        $project = $this->mapping($job, 'project', (string)($payload['_source_project_id'] ?? ''));
        if (!$project || empty($project['target_public_id'])) throw new RuntimeException('TODOIST_SECTION_PROJECT_NOT_READY');
        $created = $this->service('service.project_module')->create(['project_public_id' => $project['target_public_id'], 'title' => $this->title($payload), 'description' => 'Imported from Todoist section ' . $source, 'status' => 'planned', 'sort_order' => (int)($payload['order'] ?? 0)], $actor);
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('TODOIST_SECTION_CREATE_FAILED');
        return ['target_type' => 'project_module', 'target_public_id' => (string)$created['public_id'], 'state' => 'imported', 'warnings' => []];
    }

    public function label(array $job, array $payload): array
    {
        $source = (string)($payload['id'] ?? '');
        $map = $this->mapping($job, 'label', $source);
        if ($map && !empty($map['target_public_id'])) return ['target_type' => 'tag', 'target_public_id' => $map['target_public_id'], 'state' => 'skipped', 'warnings' => []];
        $code = 'todoist_' . substr(hash('sha256', (string)$job['connection_id'] . ':' . $source), 0, 24);
        $created = $this->service('service.tag')->create(['code' => $code, 'title' => $this->title($payload), 'color' => $this->color((string)($payload['color'] ?? '')), 'description' => 'Imported from Todoist label ' . $source]);
        if ($created === 'TAG_CODE_EXISTS') {
            $list = $this->service('service.tag')->list(['search' => $code, 'limit' => 5]);
            $created = $list['items'][0] ?? null;
        }
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('TODOIST_LABEL_CREATE_FAILED');
        return ['target_type' => 'tag', 'target_public_id' => (string)$created['public_id'], 'state' => 'imported', 'warnings' => []];
    }

    public function task(array $job, array $payload, array $actor): array
    {
        $source = (string)($payload['id'] ?? '');
        $map = $this->mapping($job, 'task', $source);
        $project = $this->mapping($job, 'project', (string)($payload['_source_project_id'] ?? $payload['project_id'] ?? ''));
        if (!$project || empty($project['target_public_id'])) throw new RuntimeException('TODOIST_TASK_PROJECT_NOT_READY');
        $warnings = [];
        $assignee = !empty($payload['assignee_id']) ? $this->repo->mappedUserId((int)$job['connection_id'], (string)$payload['assignee_id']) : null;
        $due = is_array($payload['due'] ?? null) ? $payload['due'] : [];
        $input = ['project_public_id' => $project['target_public_id'], 'title' => $this->title($payload), 'description' => (string)($payload['description'] ?? ''), 'status' => !empty($payload['is_completed']) ? 'done' : 'new', 'priority' => $this->priority($payload['priority'] ?? 1), 'due_at' => $this->date($due['datetime'] ?? $due['date'] ?? null), 'assignee_user_id' => $assignee, 'source_type' => 'todoist', 'source_id' => $source, 'source_url' => (string)($payload['url'] ?? ''), 'source_payload_json' => $payload, 'created_at' => $this->date($payload['added_at'] ?? null), 'updated_at' => $this->date($payload['completed_at'] ?? $payload['added_at'] ?? null)];
        $parent = (string)($payload['_source_parent_id'] ?? $payload['parent_id'] ?? '');
        if ($parent !== '') {
            $parentMap = $this->mapping($job, 'task', $parent);
            if (!empty($parentMap['target_public_id'])) $input['parent_task_public_id'] = $parentMap['target_public_id'];
            else throw new RuntimeException('TODOIST_PARENT_TASK_NOT_READY');
        }

        $target = '';
        $state = 'imported';
        $shouldWrite = true;
        if ($map && !empty($map['target_public_id'])) {
            $target = (string)$map['target_public_id'];
            if (($job['mode'] ?? 'import') === 'sync') {
                $updated = $this->service('service.task')->update($target, $input, (int)($actor['id'] ?? 0), $actor);
                if (!is_array($updated)) throw new RuntimeException('TODOIST_TASK_UPDATE_FAILED');
                $state = 'updated';
            } else {
                $state = 'skipped';
                $shouldWrite = false;
            }
        } else {
            $created = $this->service('service.task')->create($input, $actor);
            if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException(is_string($created) ? 'TODOIST_' . $created : 'TODOIST_TASK_CREATE_FAILED');
            $target = (string)$created['public_id'];
        }

        if ($shouldWrite) {
            foreach ((array)($payload['labels'] ?? []) as $labelName) {
                $label = $this->repo->findLabelMappingByName((int)$job['connection_id'], (string)$labelName);
                if (!empty($label['target_public_id'])) {
                    try {
                        if ($this->service('service.tag')->attachToTask($target, (string)$label['target_public_id'], $actor) !== true) {
                            $warnings[] = 'Label attachment failed.';
                        }
                    } catch (\Throwable) { $warnings[] = 'Label attachment failed.'; }
                }
            }
            $section = (string)($payload['section_id'] ?? '');
            if ($section !== '') {
                $sectionMap = $this->mapping($job, 'section', $section);
                if (!empty($sectionMap['target_public_id'])) {
                    try {
                        $sectionResult = $this->service('service.project_module')->addTasks((string)$sectionMap['target_public_id'], ['task_public_ids' => [$target]], $actor);
                        if (is_string($sectionResult) || (is_array($sectionResult) && !empty($sectionResult['errors']))) {
                            $warnings[] = 'Task could not be added to its section module.';
                        }
                    } catch (\Throwable) { $warnings[] = 'Task could not be added to its section module.'; }
                }
            }
            $recurrenceWarning = $this->writeRecurrence($target, $payload, $job);
            if ($recurrenceWarning !== null) $warnings[] = $recurrenceWarning;
        }
        return ['target_type' => 'task', 'target_public_id' => $target, 'state' => $state, 'warnings' => $warnings];
    }

    public function comment(array $job, array $payload, array $actor): array
    {
        $source = (string)($payload['id'] ?? '');
        $map = $this->mapping($job, 'comment', $source);
        if ($map && !empty($map['target_public_id'])) return ['target_type' => (string)($map['target_type'] ?? 'comment'), 'target_public_id' => $map['target_public_id'], 'state' => 'skipped', 'warnings' => []];
        $task = $this->mapping($job, 'task', (string)($payload['_source_task_id'] ?? ''));
        if ($task && !empty($task['target_public_id'])) {
            $created = $this->service('service.comment')->createByTaskImported((string)$task['target_public_id'], ['body' => (string)($payload['content'] ?? ''), 'created_at' => $payload['posted_at'] ?? null], (int)($actor['id'] ?? 0));
            if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('TODOIST_COMMENT_CREATE_FAILED');
            return ['target_type' => 'comment', 'target_public_id' => (string)$created['public_id'], 'state' => 'imported', 'warnings' => []];
        }

        // CRM comments belong to tasks, while Todoist also allows project notes.
        // Preserve project notes in the project description without marking the
        // existing project for rollback deletion.
        $project = $this->mapping($job, 'project', (string)($payload['_source_project_id'] ?? $payload['project_id'] ?? ''));
        if ($project && !empty($project['target_public_id'])) {
            $projectService = $this->service('service.project');
            $existing = $projectService->get((string)$project['target_public_id'], $actor);
            if (!is_array($existing)) throw new RuntimeException('TODOIST_COMMENT_PROJECT_NOT_FOUND');
            $marker = '[Todoist comment ' . $source . ']';
            $description = (string)($existing['description'] ?? '');
            $beforeDescription = $description;
            if (!str_contains($description, $marker)) {
                $description = rtrim($description) . "\n\n" . $marker . "\n" . trim((string)($payload['content'] ?? ''));
                $updated = $projectService->update((string)$project['target_public_id'], ['description' => $description], $actor);
                if (!is_array($updated)) throw new RuntimeException('TODOIST_COMMENT_PROJECT_UPDATE_FAILED');
            }
            return ['target_type' => 'project', 'target_public_id' => (string)$project['target_public_id'], 'state' => 'updated', 'warnings' => ['Project comments are preserved in the project description because CRM comments are task-scoped.'], 'rollback_payload' => ['project_description_before' => $beforeDescription, 'project_description_after' => $description]];
        }
        throw new RuntimeException('TODOIST_COMMENT_PARENT_NOT_READY');
    }

    public function attachment(array $job, array $payload, array $actor, string $token, int $maxBytes): array
    {
        $source = (string)($payload['_source_attachment_id'] ?? $payload['id'] ?? '');
        $map = $this->mapping($job, 'attachment', $source);
        if ($map && !empty($map['target_public_id'])) return ['target_type' => 'file', 'target_public_id' => $map['target_public_id'], 'state' => 'skipped', 'warnings' => []];
        $task = $this->mapping($job, 'task', (string)($payload['_source_task_id'] ?? ''));
        $project = $this->mapping($job, 'project', (string)($payload['_source_project_id'] ?? ''));
        $entityType = 'task';
        $entityPublicId = (string)($task['target_public_id'] ?? '');
        if ($entityPublicId === '' && !empty($project['target_public_id'])) {
            $entityType = 'project';
            $entityPublicId = (string)$project['target_public_id'];
        }
        if ($entityPublicId === '') return ['target_type' => 'file', 'target_public_id' => '', 'state' => 'skipped', 'warnings' => ['Attachment has no task or project target in CRM and was not downloaded.']];
        $url = trim((string)($payload['file_url'] ?? $payload['url'] ?? ''));
        if ($url === '') return ['target_type' => 'file', 'target_public_id' => '', 'state' => 'skipped', 'warnings' => ['Attachment has no file URL.']];
        $download = $this->client->downloadAttachment($token, $url, $maxBytes);
        $path = (string)$download['path'];
        try {
            $content = file_get_contents($path);
            if (!is_string($content)) throw new RuntimeException('TODOIST_ATTACHMENT_READ_FAILED');
            $created = $this->service('service.file')->create(['entity_type' => $entityType, 'entity_public_id' => $entityPublicId, 'name' => trim((string)($payload['file_name'] ?? $payload['title'] ?? 'attachment.bin')), 'mime_type' => (string)($download['mime_type'] ?? 'application/octet-stream'), 'content_base64' => base64_encode($content)], [], (int)($actor['id'] ?? 0), $actor);
            if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('TODOIST_FILE_CREATE_FAILED');
            return ['target_type' => 'file', 'target_public_id' => (string)$created['public_id'], 'state' => 'imported', 'warnings' => []];
        } finally { @unlink($path); }
    }

    private function writeRecurrence(string $taskPublicId, array $payload, array $job): ?string
    {
        $due = is_array($payload['due'] ?? null) ? $payload['due'] : [];
        $text = trim((string)($due['string'] ?? ''));
        if (empty($due['is_recurring']) && $text === '') return null;
        $rrule = $this->recurrenceRrule($text);
        if ($rrule === null) return 'Recurring due rule kept in source metadata; no safe RRULE equivalent was detected.';
        $service = $this->service('service.recurring');
        if (!$service->isValidRrule($rrule)) return 'Recurring due rule could not be validated and was kept in source metadata.';
        $existing = $service->list(['entity_type' => 'task', 'entity_public_id' => $taskPublicId, 'limit' => 10]);
        $items = is_array($existing) ? (array)($existing['items'] ?? []) : [];
        if ($items !== []) {
            $service->update((string)$items[0]['public_id'], ['rrule' => $rrule, 'title' => 'Todoist recurrence']);
        } else {
            $service->create(['title' => 'Todoist recurrence', 'entity_type' => 'task', 'entity_public_id' => $taskPublicId, 'rrule' => $rrule, 'is_active' => 1]);
        }
        return null;
    }

    private function recurrenceRrule(string $text): ?string
    {
        $value = mb_strtolower(trim($text));
        if ($value === '') return null;
        if (preg_match('/\bevery\s+(\d+)\s+days?\b/u', $value, $m)) return 'FREQ=DAILY;INTERVAL=' . max(1, (int)$m[1]);
        if (preg_match('/\bevery\s+day\b/u', $value)) return 'FREQ=DAILY;INTERVAL=1';
        if (preg_match('/\bevery\s+weekdays?\b/u', $value)) return 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,TU,WE,TH,FR';
        if (preg_match('/\bevery\s+(\d+)\s+weeks?\b/u', $value, $m)) return 'FREQ=WEEKLY;INTERVAL=' . max(1, (int)$m[1]);
        if (preg_match('/\bevery\s+week\b/u', $value)) return 'FREQ=WEEKLY;INTERVAL=1';
        if (preg_match('/\bevery\s+(\d+)\s+months?\b/u', $value, $m)) return 'FREQ=MONTHLY;INTERVAL=' . max(1, (int)$m[1]);
        if (preg_match('/\bevery\s+month\b/u', $value)) return 'FREQ=MONTHLY;INTERVAL=1';
        if (preg_match('/\bevery\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/u', $value, $m)) {
            $days = ['monday' => 'MO', 'tuesday' => 'TU', 'wednesday' => 'WE', 'thursday' => 'TH', 'friday' => 'FR', 'saturday' => 'SA', 'sunday' => 'SU'];
            return 'FREQ=WEEKLY;INTERVAL=1;BYDAY=' . $days[$m[1]];
        }
        return null;
    }

    private function color(string $value): string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', $value) ? $value : '#64748b';
    }
}
