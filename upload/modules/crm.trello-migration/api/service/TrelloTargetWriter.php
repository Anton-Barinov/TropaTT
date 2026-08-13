<?php
declare(strict_types=1);

namespace Module\Crm\TrelloMigration\Service;

use Api\System\Library\Container;
use Module\Crm\TrelloMigration\Repository\TrelloMigrationRepository;
use RuntimeException;

final class TrelloTargetWriter
{
    public function __construct(
        private readonly Container $container,
        private readonly TrelloMigrationRepository $repo,
        private readonly TrelloClient $client,
    ) {
    }

    public function service(string $serviceId): mixed
    {
        return $this->container->get($serviceId);
    }

    /** @return array{target_type:string,target_public_id:string,state:string,warnings:array<int,string>} */
    public function board(array $job, array $payload, array $actor): array
    {
        $sourceId = (string)($payload['id'] ?? '');
        $mapping = $this->repo->findMapping((int)$job['connection_id'], 'board', $sourceId);
        $warnings = [];
        $target = (string)($mapping['target_public_id'] ?? '');
        $projectService = $this->container->get('service.project');
        $config = $this->repo->boardConfig((int)$job['connection_id'], $sourceId);
        if ($target !== '') {
            $existing = $projectService->get($target, $actor);
            if ($existing) {
                if (($job['mode'] ?? 'import') !== 'sync') {
                    return ['target_type' => 'project', 'target_public_id' => $target, 'state' => 'skipped', 'warnings' => []];
                }
                $updated = $projectService->update($target, ['title' => $this->title($payload), 'description' => $this->description($payload)], $actor);
                return ['target_type' => 'project', 'target_public_id' => $target, 'state' => is_array($updated) ? 'updated' : 'warning', 'warnings' => is_array($updated) ? [] : ['Project update was not applied.']];
            }
            $warnings[] = 'Stored project mapping no longer resolves; a new project was created.';
        }
        if (!empty($config['target_project_public_id'])) {
            $existing = $projectService->get((string)$config['target_project_public_id'], $actor);
            if ($existing) {
                // The selected project belongs to the operator, not to this job.
                // Keep the mapping for child tasks but never roll it back.
                $target = (string)$existing['public_id'];
                return ['target_type' => 'project', 'target_public_id' => $target, 'state' => 'reused', 'warnings' => $warnings];
            }
        }
        if ($target === '') {
            $prefix = 'TR' . strtoupper(substr(hash('sha256', $sourceId), 0, 6));
            $created = $projectService->create([
                'title' => $this->title($payload),
                'description' => $this->description($payload),
                'status' => 'active',
                'priority' => 'normal',
                'task_key_prefix' => $prefix,
            ], $actor);
            if (!is_array($created) || empty($created['public_id'])) {
                throw new RuntimeException('TRELLO_PROJECT_CREATE_FAILED');
            }
            $target = (string)$created['public_id'];
        }
        return ['target_type' => 'project', 'target_public_id' => $target, 'state' => $mapping ? 'updated' : 'imported', 'warnings' => $warnings];
    }

    /** @return array{target_type:string,target_public_id:string,state:string,warnings:array<int,string>} */
    public function list(array $job, array $payload, array $actor): array
    {
        $sourceId = (string)($payload['id'] ?? '');
        $config = $this->repo->boardConfig((int)$job['connection_id'], (string)($payload['idBoard'] ?? ''));
        $listMapping = (array)($config['list_mapping'] ?? []);
        $rule = (array)($listMapping[$sourceId] ?? []);
        $jobOptions = (array)($job['target_options'] ?? []);
        $mode = (string)($rule['mode'] ?? ($config['options']['default_list_mode'] ?? ($jobOptions['default_list_mode'] ?? 'status')));
        if ($mode === 'ignore') return ['target_type' => 'none', 'target_public_id' => '', 'state' => 'skipped', 'warnings' => []];
        $existing = $this->repo->findMapping((int)$job['connection_id'], 'list', $sourceId);
        if ($existing && !empty($existing['target_public_id'])) {
            return ['target_type' => (string)$existing['target_type'], 'target_public_id' => (string)$existing['target_public_id'], 'state' => 'skipped', 'warnings' => []];
        }
        if ($mode === 'module') {
            $project = $this->repo->findMapping((int)$job['connection_id'], 'board', (string)($payload['idBoard'] ?? ''));
            if (!$project || empty($project['target_public_id'])) throw new RuntimeException('TRELLO_LIST_PROJECT_NOT_READY');
            $service = $this->container->get('service.project_module');
            $created = $service->create(['project_public_id' => $project['target_public_id'], 'title' => trim((string)($payload['name'] ?? 'Untitled')), 'description' => 'Imported from Trello list ' . $sourceId, 'status' => !empty($payload['closed']) ? 'archived' : 'planned'], $actor);
            if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('TRELLO_MODULE_CREATE_FAILED');
            return ['target_type' => 'project_module', 'target_public_id' => (string)$created['public_id'], 'state' => 'imported', 'warnings' => []];
        }
        $code = 'trello_' . substr(hash('sha256', (string)$job['connection_id'] . ':' . $sourceId), 0, 24);
        $statusService = $this->container->get('service.status');
        $created = $statusService->create(['scope' => 'task', 'code' => $code, 'title' => trim((string)($payload['name'] ?? 'Untitled')), 'color' => '#64748b', 'sort_order' => (int)($payload['pos'] ?? 0)]);
        if ($created === 'STATUS_CODE_EXISTS') {
            $created = $statusService->list(['scope' => 'task', 'search' => $code, 'limit' => 10]);
            $created = $created['items'][0] ?? null;
        }
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('TRELLO_STATUS_CREATE_FAILED');
        return ['target_type' => 'status', 'target_public_id' => (string)$created['public_id'], 'state' => 'imported', 'warnings' => []];
    }

    /** @return array{target_type:string,target_public_id:string,state:string,warnings:array<int,string>} */
    public function label(array $job, array $payload): array
    {
        $sourceId = (string)($payload['id'] ?? '');
        $mapping = $this->repo->findMapping((int)$job['connection_id'], 'label', $sourceId);
        if ($mapping && !empty($mapping['target_public_id'])) return ['target_type' => 'tag', 'target_public_id' => (string)$mapping['target_public_id'], 'state' => 'skipped', 'warnings' => []];
        $tagService = $this->container->get('service.tag');
        $code = 'trello_' . substr(hash('sha256', (string)$job['connection_id'] . ':' . $sourceId), 0, 24);
        $color = $this->labelColor((string)($payload['color'] ?? ''));
        $created = $tagService->create(['code' => $code, 'title' => trim((string)($payload['name'] ?? $payload['color'] ?? 'Trello label')), 'color' => $color, 'description' => 'Imported from Trello label ' . $sourceId]);
        if ($created === 'TAG_CODE_EXISTS') {
            $existing = $tagService->list(['search' => $code, 'limit' => 10]);
            $created = $existing['items'][0] ?? null;
        }
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('TRELLO_TAG_CREATE_FAILED');
        return ['target_type' => 'tag', 'target_public_id' => (string)$created['public_id'], 'state' => 'imported', 'warnings' => []];
    }

    /** @return array{target_type:string,target_public_id:string,state:string,warnings:array<int,string>} */
    public function card(array $job, array $payload, array $actor): array
    {
        $sourceId = (string)($payload['id'] ?? '');
        $mapping = $this->repo->findMapping((int)$job['connection_id'], 'card', $sourceId);
        $boardMapping = $this->repo->findMapping((int)$job['connection_id'], 'board', (string)($payload['idBoard'] ?? ''));
        if (!$boardMapping || empty($boardMapping['target_public_id'])) throw new RuntimeException('TRELLO_CARD_PROJECT_NOT_READY');
        $listMapping = $this->repo->findMapping((int)$job['connection_id'], 'list', (string)($payload['idList'] ?? ''));
        $status = 'new';
        if ($listMapping && $listMapping['target_type'] === 'status') {
            $statusService = $this->container->get('service.status');
            $statusRow = $statusService->get((string)$listMapping['target_public_id']);
            $status = is_array($statusRow) ? (string)$statusRow['code'] : 'new';
        }
        if (!empty($payload['closed'])) $status = 'completed';
        $assignee = null;
        foreach ((array)($payload['idMembers'] ?? []) as $memberId) {
            $assignee = $this->repo->mappedUserId((int)$job['connection_id'], (string)$memberId);
            if ($assignee !== null) break;
        }
        $input = [
            'project_public_id' => (string)$boardMapping['target_public_id'],
            'title' => trim((string)($payload['name'] ?? 'Untitled')),
            'description' => $this->description($payload) . $this->customFieldsDescription($payload),
            'status' => $status,
            'priority' => 'normal',
            'due_at' => $this->date((string)($payload['due'] ?? '')),
            'start_at' => $this->date((string)($payload['start'] ?? '')),
            'assignee_user_id' => $assignee,
            'source_type' => 'trello',
            'source_id' => $sourceId,
            'source_url' => (string)($payload['url'] ?? $payload['shortUrl'] ?? ''),
            'source_payload_json' => $payload,
            'created_at' => $this->date((string)($payload['dateLastActivity'] ?? '')) ?: gmdate('Y-m-d H:i:s'),
            'updated_at' => $this->date((string)($payload['dateLastActivity'] ?? '')) ?: gmdate('Y-m-d H:i:s'),
        ];
        $taskService = $this->container->get('service.task');
        $warnings = [];
        if ($mapping && !empty($mapping['target_public_id'])) {
            $target = (string)$mapping['target_public_id'];
            if (($job['mode'] ?? 'import') === 'sync') {
                $updated = $taskService->update($target, $input, (int)($actor['id'] ?? 0), $actor);
                if (!is_array($updated)) $warnings[] = 'Target task could not be updated; source mapping was kept.';
                $state = 'updated';
            } else {
                $state = 'skipped';
            }
        } else {
            $created = $taskService->create($input, $actor);
            if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('TRELLO_TASK_CREATE_FAILED');
            $target = (string)$created['public_id'];
            $state = 'imported';
        }
        foreach ((array)($payload['idLabels'] ?? []) as $labelId) {
            $labelMapping = $this->repo->findMapping((int)$job['connection_id'], 'label', (string)$labelId);
            if ($labelMapping && !empty($labelMapping['target_public_id'])) {
                try { $this->container->get('service.tag')->attachToTask($target, (string)$labelMapping['target_public_id'], $actor); } catch (\Throwable) { $warnings[] = 'A label could not be attached.'; }
            }
        }
        if ($listMapping && $listMapping['target_type'] === 'project_module') {
            try { $this->container->get('service.project_module')->addTasks((string)$listMapping['target_public_id'], ['task_public_ids' => [$target]], $actor); } catch (\Throwable) { $warnings[] = 'The card could not be added to its project module.'; }
        }
        return ['target_type' => 'task', 'target_public_id' => $target, 'state' => $state, 'warnings' => $warnings];
    }

    /** @return array<int,string> */
    public function children(array $job, array $payload, array $actor): array
    {
        $warnings = [];
        $taskMapping = $this->repo->findMapping((int)$job['connection_id'], 'card', (string)($payload['id'] ?? ''));
        $taskId = (string)($taskMapping['target_public_id'] ?? '');
        if ($taskId === '') return ['Card target missing.'];
        $checklistService = $this->container->get('service.checklist');
        foreach ((array)($payload['checklists'] ?? []) as $checklist) {
            $checklistId = (string)($checklist['id'] ?? '');
            if ($checklistId === '') continue;
            $mapping = $this->repo->findMapping((int)$job['connection_id'], 'checklist', $checklistId);
            $targetChecklist = (string)($mapping['target_public_id'] ?? '');
            if ($targetChecklist === '') {
                $created = $checklistService->createImported($taskId, ['title' => (string)($checklist['name'] ?? 'Checklist'), 'created_at' => (string)($payload['dateLastActivity'] ?? '')], $actor);
                if (!is_array($created) || empty($created['public_id'])) { $warnings[] = 'Checklist could not be created.'; continue; }
                $targetChecklist = (string)$created['public_id'];
                $checklistChecksum = hash('sha256', (string)json_encode($checklist, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $this->repo->upsertMapping((int)$job['connection_id'], 'checklist', $checklistId, [
                    'target_type' => 'checklist',
                    'target_public_id' => $targetChecklist,
                    'source_checksum' => $checklistChecksum,
                    'created_by_job_id' => (int)$job['id'],
                ]);
                $this->repo->upsertItem((int)$job['id'], 'checklist', $checklistId, [
                    'source_parent_id' => (string)($payload['id'] ?? ''),
                    'target_type' => 'checklist',
                    'target_public_id' => $targetChecklist,
                    'created_by_job' => 1,
                    'status' => 'imported',
                    'checksum' => $checklistChecksum,
                ]);
            }
            foreach ((array)($checklist['checkItems'] ?? []) as $item) {
                $itemId = (string)($item['id'] ?? '');
                if ($itemId === '') {
                    $itemId = hash('sha256', $checklistId . ':' . (string)($item['name'] ?? '') . ':' . (string)($item['pos'] ?? ''));
                }
                $itemChecksum = hash('sha256', (string)json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $itemMapping = $this->repo->findMapping((int)$job['connection_id'], 'checklist_item', $itemId);
                $targetItem = (string)($itemMapping['target_public_id'] ?? '');
                $itemInput = [
                    'title' => (string)($item['name'] ?? 'Item'),
                    'is_done' => !empty($item['state']) && $item['state'] === 'complete',
                    'sort_order' => (int)($item['pos'] ?? 0),
                    'created_at' => (string)($payload['dateLastActivity'] ?? ''),
                ];
                if ($targetItem !== '') {
                    if (($job['mode'] ?? 'import') === 'sync') {
                        $updatedItem = $checklistService->updateItem($targetItem, $itemInput, $actor);
                        if (!is_array($updatedItem)) $warnings[] = 'Checklist item could not be updated.';
                    }
                    continue;
                }
                $createdItem = $checklistService->createItemImported($targetChecklist, $itemInput, $actor);
                if (!is_array($createdItem) || empty($createdItem['public_id'])) {
                    $warnings[] = 'Checklist item could not be created.';
                    continue;
                }
                $targetItem = (string)$createdItem['public_id'];
                $this->repo->upsertMapping((int)$job['connection_id'], 'checklist_item', $itemId, [
                    'source_parent_id' => $checklistId,
                    'target_type' => 'checklist_item',
                    'target_public_id' => $targetItem,
                    'source_checksum' => $itemChecksum,
                    'created_by_job_id' => (int)$job['id'],
                ]);
                $this->repo->upsertItem((int)$job['id'], 'checklist_item', $itemId, [
                    'source_parent_id' => $checklistId,
                    'target_type' => 'checklist_item',
                    'target_public_id' => $targetItem,
                    'created_by_job' => 1,
                    'status' => 'imported',
                    'checksum' => $itemChecksum,
                ]);
            }
        }
        $seenComments = [];
        foreach ((array)($payload['trello_actions'] ?? []) as $action) {
            if ((string)($action['type'] ?? '') !== 'commentCard') continue;
            $actionId = (string)($action['id'] ?? '');
            if ($actionId === '' || isset($seenComments[$actionId])) continue;
            $seenComments[$actionId] = true;
            if ($this->repo->findMapping((int)$job['connection_id'], 'comment', $actionId)) continue;
            $text = trim((string)($action['data']['text'] ?? ''));
            if ($text === '') continue;
            $member = (string)($action['memberCreator']['fullName'] ?? $action['idMemberCreator'] ?? 'Trello user');
            $commentAuthorId = $this->repo->mappedUserId((int)$job['connection_id'], (string)($action['idMemberCreator'] ?? '')) ?? (int)($actor['id'] ?? 0);
            $commentInput = [
                'body' => '[Trello: ' . $member . "]\n" . $text,
                'visibility' => 'internal',
                'author_user_id' => $commentAuthorId,
                'created_at' => (string)($action['date'] ?? ''),
            ];
            try {
                $comment = $this->container->get('service.comment')->createByTaskImported($taskId, $commentInput, (int)($actor['id'] ?? 0));
                if (is_array($comment) && !empty($comment['public_id'])) {
                    $commentChecksum = hash('sha256', $text);
                    $this->repo->upsertMapping((int)$job['connection_id'], 'comment', $actionId, ['target_type' => 'comment', 'target_public_id' => (string)$comment['public_id'], 'source_checksum' => $commentChecksum, 'created_by_job_id' => (int)$job['id']]);
                    $this->repo->upsertItem((int)$job['id'], 'comment', $actionId, [
                        'source_parent_id' => (string)($payload['id'] ?? ''),
                        'target_type' => 'comment',
                        'target_public_id' => (string)$comment['public_id'],
                        'created_by_job' => 1,
                        'status' => 'imported',
                        'checksum' => $commentChecksum,
                    ]);
                }
            } catch (\Throwable) { $warnings[] = 'A comment could not be imported.'; }
        }
        return $warnings;
    }

    /** @return array<int,string> */
    public function attachments(array $job, array $payload, array $actor, string $apiKey, string $token, int $maxBytes): array
    {
        $warnings = [];
        $taskMapping = $this->repo->findMapping((int)$job['connection_id'], 'card', (string)($payload['id'] ?? ''));
        $taskId = (string)($taskMapping['target_public_id'] ?? '');
        if ($taskId === '') return ['Attachment target task missing.'];
        foreach ((array)($payload['attachments'] ?? []) as $attachment) {
            $sourceId = (string)($attachment['id'] ?? '');
            $url = trim((string)($attachment['url'] ?? ''));
            if ($sourceId === '' || $url === '') continue;
            if ($this->repo->findMapping((int)$job['connection_id'], 'attachment', $sourceId)) continue;
            try {
                $download = $this->client->downloadAttachment($apiKey, $token, $url, $maxBytes);
                $bytes = file_get_contents($download['path']);
                @unlink($download['path']);
                if (!is_string($bytes)) throw new RuntimeException('attachment-read');
                $file = $this->container->get('service.file')->create([
                    'entity_type' => 'task',
                    'entity_public_id' => $taskId,
                    'name' => (string)($attachment['name'] ?? 'trello-attachment.bin'),
                    'mime_type' => (string)$download['mime_type'],
                    'content_base64' => base64_encode($bytes),
                ], [], (int)($actor['id'] ?? 0), $actor);
                if (is_array($file) && !empty($file['public_id'])) {
                    $this->repo->upsertMapping((int)$job['connection_id'], 'attachment', $sourceId, [
                        'target_type' => 'file',
                        'target_public_id' => (string)$file['public_id'],
                        'source_checksum' => hash('sha256', $bytes),
                        'created_by_job_id' => (int)$job['id'],
                    ]);
                    $this->repo->upsertItem((int)$job['id'], 'attachment', $sourceId, [
                        'source_parent_id' => (string)($payload['id'] ?? ''),
                        'target_type' => 'file',
                        'target_public_id' => (string)$file['public_id'],
                        'created_by_job' => 1,
                        'status' => 'imported',
                        'checksum' => hash('sha256', $bytes),
                    ]);
                }
            } catch (\Throwable) { $warnings[] = 'Attachment ' . $sourceId . ' was skipped.'; }
        }
        return $warnings;
    }

    private function title(array $payload): string
    {
        return trim((string)($payload['name'] ?? 'Trello board')) ?: 'Trello board';
    }

    private function description(array $payload): string
    {
        $description = trim((string)($payload['desc'] ?? ''));
        $url = trim((string)($payload['url'] ?? $payload['shortUrl'] ?? ''));
        return trim($description . ($url !== '' ? "\n\nИсточник Trello: " . $url : ''));
    }

    private function customFieldsDescription(array $payload): string
    {
        $items = (array)($payload['customFieldItems'] ?? []);
        if ($items === []) return '';
        $definitions = [];
        foreach ((array)($payload['trello_custom_field_definitions'] ?? []) as $definition) {
            if (!empty($definition['id'])) $definitions[(string)$definition['id']] = (string)($definition['name'] ?? $definition['id']);
        }
        $lines = [];
        foreach ($items as $item) {
            $id = (string)($item['idCustomField'] ?? '');
            if ($id === '') continue;
            $value = (array)($item['value'] ?? []);
            $display = (string)($value['text'] ?? $value['number'] ?? $item['idValue'] ?? '');
            if ($display === '') continue;
            $lines[] = '- ' . ($definitions[$id] ?? $id) . ': ' . $display;
        }
        return $lines === [] ? '' : "\n\nПоля Trello:\n" . implode("\n", $lines);
    }

    private function date(string $value): ?string
    {
        if ($value === '') return null;
        $time = strtotime($value);
        return $time === false ? null : gmdate('Y-m-d H:i:s', $time);
    }

    private function labelColor(string $color): string
    {
        return match (strtolower($color)) {
            'yellow' => '#f59e0b', 'orange' => '#f97316', 'red' => '#ef4444', 'purple' => '#8b5cf6', 'blue' => '#3b82f6', 'sky' => '#38bdf8', 'lime' => '#84cc16', 'pink' => '#ec4899', 'black' => '#111827', 'green' => '#22c55e', default => '#64748b',
        };
    }
}
