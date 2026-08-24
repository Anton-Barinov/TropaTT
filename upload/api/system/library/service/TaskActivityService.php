<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Task\TaskActivityRepository;
use Api\System\Library\Support\Ulid;

final class TaskActivityService
{
    public function __construct(
        private readonly TaskActivityRepository $repository,
    )
    {
    }

    /**
     * List activity events for a task.
     * @return array|string|null
     */
    public function list(string $taskPublicId, array $filters, array $actor): array|string|null
    {
        $taskId = $this->repository->taskIdByPublicId($taskPublicId);
        if ($taskId === null) {
            return 'TASK_NOT_FOUND';
        }

        $result = $this->repository->listByTaskPublicId($taskPublicId, $filters);

        $isExternal = !empty((int)($actor['is_external'] ?? 0));

        // Enrich items with decoded payload
        $items = [];
        foreach ($result['items'] as $item) {
            $normalized = $this->normalizeItem($item);

            // H-3: external users must not see internal comment/file previews,
            // internal-user identities, or internal-activity message texts.
            if ($isExternal) {
                $normalized = $this->sanitizeForExternal($normalized);
            }

            $items[] = $normalized;
        }

        $total = (int)$result['total'];
        $limit = (int)$result['limit'];
        $page = (int)$result['page'];

        return [
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function recordTaskCreated(array $task, array $actor, array $context = []): void
    {
        $this->createEvent([
            'event_type' => 'task.created',
            'task' => $task,
            'actor' => $actor,
            'context' => $context,
            'message_text' => $this->buildActorName($actor) . ' создал задачу',
            'message_key' => 'task.activity.created',
        ]);
    }

    public function recordFieldChanged(array $task, string $field, mixed $oldValue, mixed $newValue, array $actor, array $context = []): void
    {
        $eventType = $this->fieldToEventType($field);
        if ($eventType === null) {
            return;
        }

        $oldStr = $this->stringifyValue($oldValue);
        $newStr = $this->stringifyValue($newValue);

        $oldLabel = $this->valueToLabel($field, $oldValue);
        $newLabel = $this->valueToLabel($field, $newValue);

        $messageText = $this->buildFieldChangedMessage($eventType, $actor, $oldLabel, $newLabel, $field, $newValue);
        $payload = [
            'field' => $field,
            'old_value' => $oldStr,
            'new_value' => $newStr,
            'old_label' => $oldLabel,
            'new_label' => $newLabel,
            'source' => $context['source_type'] ?? 'web',
        ];
        if ($field === 'status_code' && !empty($context['status_reason'])) {
            $payload['reason'] = (string)$context['status_reason'];
        }

        $this->createEvent([
            'event_type' => $eventType,
            'task' => $task,
            'actor' => $actor,
            'context' => $context,
            'field_name' => $field,
            'old_value' => $oldStr,
            'new_value' => $newStr,
            'old_label' => $oldLabel,
            'new_label' => $newLabel,
            'message_text' => $messageText,
            'message_key' => 'task.activity.' . str_replace('.', '_', $eventType),
            'payload_json' => $payload,
        ]);
    }

    public function recordManyFieldChanges(array $task, array $changes, array $actor, array $context = []): void
    {
        foreach ($changes as $change) {
            $this->recordFieldChanged(
                $task,
                $change['field'],
                $change['old_value'],
                $change['new_value'],
                $actor,
                $context
            );
        }
    }

    public function recordCommentAdded(array $task, array $comment, array $actor, array $context = []): void
    {
        $preview = mb_substr((string)($comment['body'] ?? ''), 0, 160);

        $this->createEvent([
            'event_type' => 'task.comment_added',
            'task' => $task,
            'actor' => $actor,
            'context' => $context,
            'related_entity_type' => 'comment',
            'related_entity_public_id' => (string)($comment['public_id'] ?? ''),
            'related_entity_label' => '',
            'message_text' => $this->buildActorName($actor) . ' добавил комментарий',
            'message_key' => 'task.activity.comment_added',
            'payload_json' => [
                'comment_public_id' => (string)($comment['public_id'] ?? ''),
                'preview' => $preview,
                'source' => $context['source_type'] ?? 'web',
            ],
        ]);
    }

    public function recordCommentUpdated(array $task, array $comment, array $actor, array $context = []): void
    {
        $this->createEvent([
            'event_type' => 'task.comment_updated',
            'task' => $task,
            'actor' => $actor,
            'context' => $context,
            'related_entity_type' => 'comment',
            'related_entity_public_id' => (string)($comment['public_id'] ?? ''),
            'message_text' => $this->buildActorName($actor) . ' изменил комментарий',
            'message_key' => 'task.activity.comment_updated',
        ]);
    }

    public function recordCommentDeleted(array $task, array $comment, array $actor, array $context = []): void
    {
        $this->createEvent([
            'event_type' => 'task.comment_deleted',
            'task' => $task,
            'actor' => $actor,
            'context' => $context,
            'related_entity_type' => 'comment',
            'related_entity_public_id' => (string)($comment['public_id'] ?? ''),
            'message_text' => $this->buildActorName($actor) . ' удалил комментарий',
            'message_key' => 'task.activity.comment_deleted',
        ]);
    }

    public function recordFileAdded(array $task, array $file, array $actor, array $context = []): void
    {
        $filename = (string)($file['original_name'] ?? $file['filename'] ?? '');

        $this->createEvent([
            'event_type' => 'task.file_added',
            'task' => $task,
            'actor' => $actor,
            'context' => $context,
            'related_entity_type' => 'file',
            'related_entity_public_id' => (string)($file['public_id'] ?? ''),
            'related_entity_label' => $filename,
            'message_text' => $this->buildActorName($actor) . ' добавил файл: ' . $filename,
            'message_key' => 'task.activity.file_added',
            'payload_json' => [
                'file_public_id' => (string)($file['public_id'] ?? ''),
                'filename' => $filename,
                'size_bytes' => (int)($file['size_bytes'] ?? 0),
                'mime_type' => (string)($file['mime_type'] ?? ''),
                'source' => $context['source_type'] ?? 'web',
            ],
        ]);
    }

    public function recordFileDeleted(array $task, array $file, array $actor, array $context = []): void
    {
        $filename = (string)($file['original_name'] ?? $file['filename'] ?? '');

        $this->createEvent([
            'event_type' => 'task.file_deleted',
            'task' => $task,
            'actor' => $actor,
            'context' => $context,
            'related_entity_type' => 'file',
            'related_entity_public_id' => (string)($file['public_id'] ?? ''),
            'related_entity_label' => $filename,
            'message_text' => $this->buildActorName($actor) . ' удалил файл: ' . $filename,
            'message_key' => 'task.activity.file_deleted',
        ]);
    }

    public function recordChecklistEvent(array $task, string $eventType, array $checklistData, array $actor, array $context = []): void
    {
        $title = (string)($checklistData['title'] ?? $checklistData['item_title'] ?? '');

        $messages = [
            'task.checklist_created' => $this->buildActorName($actor) . ' добавил чеклист: ' . $title,
            'task.checklist_updated' => $this->buildActorName($actor) . ' изменил чеклист: ' . $title,
            'task.checklist_deleted' => $this->buildActorName($actor) . ' удалил чеклист: ' . $title,
            'task.checklist_item_created' => $this->buildActorName($actor) . ' добавил пункт чеклиста: ' . $title,
            'task.checklist_item_completed' => $this->buildActorName($actor) . ' отметил пункт выполненным: ' . $title,
            'task.checklist_item_reopened' => $this->buildActorName($actor) . ' снял отметку с пункта: ' . $title,
            'task.checklist_item_deleted' => $this->buildActorName($actor) . ' удалил пункт чеклиста: ' . $title,
        ];

        $this->createEvent([
            'event_type' => $eventType,
            'task' => $task,
            'actor' => $actor,
            'context' => $context,
            'related_entity_type' => 'checklist',
            'related_entity_public_id' => (string)($checklistData['checklist_public_id'] ?? $checklistData['public_id'] ?? ''),
            'related_entity_label' => $title,
            'message_text' => $messages[$eventType] ?? $this->buildActorName($actor) . ' изменил чеклист',
            'message_key' => 'task.activity.' . str_replace('.', '_', $eventType),
            'payload_json' => [
                'checklist_public_id' => (string)($checklistData['checklist_public_id'] ?? ''),
                'item_public_id' => (string)($checklistData['item_public_id'] ?? ''),
                'title' => $title,
                'source' => $context['source_type'] ?? 'web',
            ],
        ]);
    }

    public function recordRelationEvent(array $task, string $eventType, array $relationData, array $actor, array $context = []): void
    {
        $actorName = $this->buildActorName($actor);

        $messageText = match ($eventType) {
            'task.added_to_cycle' => $actorName . ' добавил в цикл: ' . ($relationData['cycle_title'] ?? ''),
            'task.removed_from_cycle' => $actorName . ' удалил из цикла: ' . ($relationData['cycle_title'] ?? ''),
            'task.moved_to_cycle' => $actorName . ' перенес в цикл: ' . ($relationData['target_cycle_title'] ?? '') . ' (из ' . ($relationData['source_cycle_title'] ?? '') . ')',
            default => $actorName . ' ' . ($eventType === 'task.relation_added' ? 'добавил' : 'удалил') . ' связь: ' . ($relationData['relation_type'] ?? '') . ' ' . ($relationData['target_task_key'] ?? $relationData['target_task_public_id'] ?? ''),
        };

        $this->createEvent([
            'event_type' => $eventType,
            'task' => $task,
            'actor' => $actor,
            'context' => $context,
            'related_entity_type' => 'task_relation',
            'related_entity_public_id' => (string)($relationData['relation_public_id'] ?? ''),
            'related_entity_label' => (string)($relationData['target_task_key'] ?? $relationData['target_task_public_id'] ?? ''),
            'message_text' => $messageText,
            'message_key' => 'task.activity.' . str_replace('.', '_', $eventType),
            'payload_json' => $relationData + ['source' => $context['source_type'] ?? 'web'],
        ]);
    }

    public function recordDependencyEvent(array $task, string $eventType, array $dependencyData, array $actor, array $context = []): void
    {
        $this->createEvent([
            'event_type' => $eventType,
            'task' => $task,
            'actor' => $actor,
            'context' => $context,
            'related_entity_type' => 'dependency',
            'related_entity_public_id' => (string)($dependencyData['dependency_public_id'] ?? ''),
            'related_entity_label' => (string)($dependencyData['depends_on_task_public_id'] ?? ''),
            'message_text' => $this->buildActorName($actor) . ' ' . ($eventType === 'task.dependency_added' ? 'добавил' : 'удалил') . ' зависимость: ' . ($dependencyData['type'] ?? '') . ' от ' . ($dependencyData['depends_on_task_public_id'] ?? ''),
            'message_key' => 'task.activity.' . str_replace('.', '_', $eventType),
            'payload_json' => $dependencyData + ['source' => $context['source_type'] ?? 'web'],
        ]);
    }

    public function recordSystemEvent(array $task, string $eventType, array $payload, array $context = []): void
    {
        $this->createEvent([
            'event_type' => $eventType,
            'task' => $task,
            'actor' => [
                'actor_type' => 'system',
                'actor_display_name' => 'System',
            ],
            'context' => $context,
            'payload_json' => $payload,
            'source_type' => $context['source_type'] ?? 'system',
        ]);
    }

    /**
     * Detect changes between old and new task data and record appropriate events.
     * @return list<array{field:string, old_value:mixed, new_value:mixed}>
     */
    public function detectChanges(array $oldTask, array $newTask): array
    {
        $changes = [];
        $trackedFields = [
            'title' => 'title',
            'description' => 'description',
            'status_code' => 'status_code',
            'priority_code' => 'priority_code',
            'assignee_user_id' => 'assignee_user_id',
            'project_id' => 'project_id',
            'start_at' => 'start_at',
            'due_at' => 'due_at',
            'end_at' => 'end_at',
            'archived_at' => 'archived_at',
            'parent_task_public_id' => 'parent_task_public_id',
        ];

        foreach ($trackedFields as $field => $key) {
            $oldVal = $oldTask[$key] ?? null;
            $newVal = $newTask[$key] ?? null;

            // Normalize for comparison
            $oldStr = $oldVal !== null ? (string)$oldVal : '';
            $newStr = $newVal !== null ? (string)$newVal : '';

            if ($oldStr !== $newStr) {
                $changes[] = [
                    'field' => $field,
                    'old_value' => $oldVal,
                    'new_value' => $newVal,
                ];
            }
        }

        return $changes;
    }

    // ----- Private helpers -----

    private function createEvent(array $params): void
    {
        try {
            $task = $params['task'];
            $actor = $params['actor'] ?? [];
            $context = $params['context'] ?? [];

            $actorType = $actor['actor_type'] ?? 'user';
            $actorUserId = null;
            $actorPublicId = '';
            $actorDisplayName = '';

            if ($actorType === 'user') {
                $actorUserId = (int)($actor['id'] ?? 0);
                $actorPublicId = (string)($actor['public_id'] ?? '');
                $actorDisplayName = (string)($actor['full_name'] ?? $actor['login'] ?? '');
            } else {
                $actorDisplayName = (string)($actor['actor_display_name'] ?? 'System');
            }

            $this->repository->create([
                'public_id' => Ulid::generate('tac'),
                'task_id' => (int)($task['id'] ?? 0),
                'task_public_id' => (string)($task['public_id'] ?? ''),
                'actor_user_id' => $actorUserId,
                'actor_type' => $actorType,
                'actor_public_id' => $actorPublicId,
                'actor_display_name' => $actorDisplayName,
                'event_type' => (string)($params['event_type'] ?? ''),
                'field_name' => (string)($params['field_name'] ?? ''),
                'old_value' => (string)($params['old_value'] ?? ''),
                'new_value' => (string)($params['new_value'] ?? ''),
                'old_label' => (string)($params['old_label'] ?? ''),
                'new_label' => (string)($params['new_label'] ?? ''),
                'related_entity_type' => (string)($params['related_entity_type'] ?? ''),
                'related_entity_id' => $params['related_entity_id'] ?? null,
                'related_entity_public_id' => (string)($params['related_entity_public_id'] ?? ''),
                'related_entity_label' => mb_substr((string)($params['related_entity_label'] ?? ''), 0, 255),
                'message_key' => (string)($params['message_key'] ?? ''),
                'message_text' => mb_substr((string)($params['message_text'] ?? ''), 0, 1000),
                'payload_json' => $params['payload_json'] ?? null,
                'visibility' => (string)($params['visibility'] ?? 'default'),
                'request_id' => (string)($context['request_id'] ?? ''),
                'source_type' => (string)($context['source_type'] ?? ''),
                'source_ref' => (string)($context['source_ref'] ?? ''),
            ]);
        } catch (\Throwable $e) {
            error_log('[TaskActivityService] Failed to record event: ' . $e->getMessage());
        }
    }

    private function fieldToEventType(string $field): ?string
    {
        return match ($field) {
            'title' => 'task.title_changed',
            'description' => 'task.description_changed',
            'status_code' => 'task.status_changed',
            'priority_code' => 'task.priority_changed',
            'assignee_user_id' => 'task.assignee_changed',
            'project_id' => 'task.project_changed',
            'start_at' => 'task.start_at_changed',
            'due_at' => 'task.due_at_changed',
            'end_at' => 'task.end_at_changed',
            'archived_at' => 'task.archived',
            'parent_task_public_id' => 'task.subtask_added',
            default => null,
        };
    }

    private function buildFieldChangedMessage(string $eventType, array $actor, string $oldLabel, string $newLabel, string $field, mixed $newValue): string
    {
        $actorName = $this->buildActorName($actor);

        return match ($eventType) {
            'task.title_changed' => $actorName . ' изменил название',
            'task.description_changed' => $actorName . ' изменил описание',
            'task.status_changed' => $actorName . ' изменил статус: ' . ($oldLabel ?: '—') . ' → ' . ($newLabel ?: '—'),
            'task.priority_changed' => $actorName . ' изменил приоритет: ' . ($oldLabel ?: '—') . ' → ' . ($newLabel ?: '—'),
            'task.assignee_changed' => $this->buildAssigneeMessage($actorName, $oldLabel, $newLabel),
            'task.project_changed' => $actorName . ' изменил проект: ' . ($oldLabel ?: '—') . ' → ' . ($newLabel ?: '—'),
            'task.start_at_changed' => $this->buildDateMessage($actorName, 'начала', $oldLabel, $newLabel),
            'task.due_at_changed' => $this->buildDateMessage($actorName, 'срока', $oldLabel, $newLabel),
            'task.end_at_changed' => $this->buildDateMessage($actorName, 'завершения', $oldLabel, $newLabel),
            'task.archived' => $actorName . ' архивировал задачу',
            default => $actorName . ' изменил поле ' . $field,
        };
    }

    private function buildAssigneeMessage(string $actorName, string $oldLabel, string $newLabel): string
    {
        if ($oldLabel === '' && $newLabel !== '') {
            return $actorName . ' назначил исполнителя: ' . $newLabel;
        }
        if ($oldLabel !== '' && $newLabel === '') {
            return $actorName . ' снял исполнителя: ' . $oldLabel;
        }
        return $actorName . ' изменил исполнителя: ' . $oldLabel . ' → ' . $newLabel;
    }

    private function buildDateMessage(string $actorName, string $dateType, string $oldLabel, string $newLabel): string
    {
        if ($oldLabel === '' && $newLabel !== '') {
            return $actorName . ' установил дату ' . $dateType . ': ' . $newLabel;
        }
        if ($oldLabel !== '' && $newLabel === '') {
            return $actorName . ' удалил дату ' . $dateType . ': ' . $oldLabel;
        }
        return $actorName . ' изменил дату ' . $dateType . ': ' . $oldLabel . ' → ' . $newLabel;
    }

    private function buildActorName(array $actor): string
    {
        if (($actor['actor_type'] ?? 'user') !== 'user') {
            return $actor['actor_display_name'] ?? 'System';
        }

        return $actor['full_name'] ?? $actor['login'] ?? (string)($actor['public_id'] ?? '');
    }

    private function stringifyValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        return (string)$value;
    }

    private function valueToLabel(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($field) {
            'status_code' => $this->statusLabel((string)$value),
            'priority_code' => $this->priorityLabel((string)$value),
            'assignee_user_id' => '',  // Will be resolved at display time
            'project_id' => '',  // Will be resolved at display time
            default => (string)$value,
        };
    }

    private function statusLabel(string $code): string
    {
        $labels = [
            'new' => 'Новая',
            'in_progress' => 'В работе',
            'blocked' => 'Заблокирована',
            'done' => 'Выполнена',
            'cancelled' => 'Отменена',
            'on_hold' => 'Отложена',
            'review' => 'На проверке',
            'approved' => 'Утверждена',
            'rejected' => 'Отклонена',
        ];

        return $labels[$code] ?? $code;
    }

    private function priorityLabel(string $code): string
    {
        $labels = [
            'low' => 'Низкий',
            'normal' => 'Средний',
            'high' => 'Высокий',
            'urgent' => 'Срочный',
        ];

        return $labels[$code] ?? $code;
    }

    private function normalizeItem(array $item): array
    {
        $payload = null;
        if (!empty($item['payload_json'])) {
            $decoded = json_decode((string)$item['payload_json'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        return [
            'public_id' => $item['public_id'] ?? '',
            'event_type' => $item['event_type'] ?? '',
            'field_name' => $item['field_name'] ?? '',
            'old_value' => $item['old_value'] ?? '',
            'new_value' => $item['new_value'] ?? '',
            'old_label' => $item['old_label'] ?? '',
            'new_label' => $item['new_label'] ?? '',
            'message_key' => $item['message_key'] ?? '',
            'message_text' => $item['message_text'] ?? '',
            'related_entity_type' => $item['related_entity_type'] ?? '',
            'related_entity_public_id' => $item['related_entity_public_id'] ?? '',
            'related_entity_label' => $item['related_entity_label'] ?? '',
            'actor_type' => $item['actor_type'] ?? 'user',
            'actor_user_public_id' => $item['actor_public_id'] ?? '',
            'actor_display_name' => $item['actor_display_name'] ?? '',
            'payload' => $payload,
            'created_at' => $item['created_at'] ?? '',
        ];
    }

    /**
     * Strip internal-only data from an activity event for an external user.
     * Mirrors the approach of TaskService::sanitizeTask(): remove staff
     * identities, internal comment/file previews, and message texts from
     * events that originated from internal actions (H-3).
     */
    private function sanitizeForExternal(array $item): array
    {
        // Remove internal staff identities.
        $item['actor_user_public_id'] = '';
        $item['actor_display_name'] = '';

        // Events triggered by internal files or internal comments must not
        // leak previews, labels, or message texts.
        $eventType = (string)($item['event_type'] ?? '');
        $relatedType = (string)($item['related_entity_type'] ?? '');

        if ($eventType === 'task.comment_added' || $eventType === 'task.comment_updated'
            || $relatedType === 'comment'
        ) {
            unset($item['payload']['preview']);
            $item['message_text'] = '';
            $item['related_entity_label'] = '';
        }

        if ($eventType === 'task.file_added' || $eventType === 'task.file_deleted'
            || $relatedType === 'file'
        ) {
            $item['message_text'] = '';
            $item['related_entity_label'] = '';
        }

        return $item;
    }
}
