<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Task\TaskRelationRepository;

final class TaskRelationService
{
    public function __construct(
        private readonly TaskRelationRepository $relations,
        private readonly TaskService $tasks,
        private readonly ?TaskActivityService $activity = null
    ) {
    }

    private const VALID_TYPES = [
        'blocked_by',
        'relates_to',
        'duplicate',
        'implements',
        'caused_by',
        'parent_of',
    ];

    private const SYMMETRIC_TYPES = [
        'relates_to',
        'duplicate',
    ];

    /** Types whose alias means the user intent is reversed */
    private const REVERSE_ALIASES = [
        'blocks' => 'blocked_by',
        'implemented_by' => 'implements',
        'causes' => 'caused_by',
        'child_of' => 'parent_of',
    ];

    /**
     * List relations for a task, grouped by display groups.
     *
     * @return array<string,mixed>|string|null
     */
    public function list(string $taskPublicId, array $actor): array|string|null
    {
        $task = $this->tasks->get($taskPublicId, $actor);
        if (!$task) {
            return null;
        }

        $taskId = (int)($task['id'] ?? 0);
        if ($taskId <= 0) {
            return null;
        }

        $rows = $this->relations->listForTaskId($taskId);

        return $this->buildGroupedResponse($rows, $taskId, $actor);
    }

    /**
     * Create a relation between two tasks.
     *
     * @return array<string,mixed>|string|null
     */
    public function create(string $sourceTaskPublicId, array $input, array $actor): array|string|null
    {
        // Find source task
        $sourceTask = $this->tasks->get($sourceTaskPublicId, $actor);
        if (!$sourceTask) {
            return null;
        }

        // Find target task by public_id or task_key
        $targetPublicId = trim((string)($input['target_task_public_id'] ?? ''));
        $targetTaskKey = trim((string)($input['target_task_key'] ?? ''));

        if ($targetPublicId === '' && $targetTaskKey === '') {
            return 'TASK_RELATION_TARGET_REQUIRED';
        }

        $targetTask = null;
        if ($targetPublicId !== '') {
            $targetTask = $this->tasks->get($targetPublicId, $actor);
        } elseif ($targetTaskKey !== '') {
            $targetTask = $this->tasks->getByTaskKey($targetTaskKey, $actor);
        }

        if (!$targetTask) {
            return 'TASK_RELATION_TARGET_NOT_FOUND';
        }

        $sourceId = (int)($sourceTask['id'] ?? 0);
        $targetId = (int)($targetTask['id'] ?? 0);

        // Self-link check
        if ($sourceId === $targetId) {
            return 'TASK_RELATION_SELF_LINK_FORBIDDEN';
        }

        // Normalize relation type
        $rawType = trim((string)($input['relation_type'] ?? ''));
        $normalized = $this->normalizeRelationType($rawType);

        if ($normalized === null) {
            return 'TASK_RELATION_TYPE_INVALID';
        }

        // Check if the raw input was a reverse alias (e.g. "blocks" instead of "blocked_by")
        // If so, swap source and target: user said "A blocks B" → store "B blocked_by A"
        if ($this->isReverseAlias($rawType)) {
            [$sourceId, $targetId] = [$targetId, $sourceId];
        }

        // For symmetric types, normalize source/target order (min first)
        if ($this->isSymmetricType($normalized)) {
            $minId = min($sourceId, $targetId);
            $maxId = max($sourceId, $targetId);
            $sourceId = $minId;
            $targetId = $maxId;
        }

        // Build active key
        $activeKey = $this->buildActiveKey($normalized, $sourceId, $targetId);

        // Check duplicate
        if ($this->relations->existsByActiveKey($activeKey)) {
            return 'TASK_RELATION_ALREADY_EXISTS';
        }

        // Validate note length
        $note = (string)($input['note'] ?? '');
        if (mb_strlen($note) > self::MAX_NOTE_LENGTH) {
            return 'TASK_RELATION_NOTE_TOO_LONG';
        }

        $payload = [
            'source_task_id' => $sourceId,
            'target_task_id' => $targetId,
            'relation_type' => $normalized,
            'active_key' => $activeKey,
            'note' => $note !== '' ? $note : null,
            'created_by_user_id' => (int)($actor['id'] ?? 0),
        ];

        $created = $this->relations->create($payload);
        if (is_array($created)) {
            $sourceKey = (string)($sourceTask['task_key'] ?? $sourceTaskPublicId);
            $targetKey = (string)($targetTask['task_key'] ?? $targetPublicId);
            $this->activity?->recordRelationEvent($sourceTask, 'task.relation_added', [
                'relation_public_id' => (string)($created['public_id'] ?? ''),
                'relation_type' => $normalized,
                'source_task_public_id' => $sourceTaskPublicId,
                'target_task_public_id' => $targetPublicId,
                'source_task_key' => $sourceKey,
                'target_task_key' => $targetKey,
            ], $actor);
            // Also record on target task for its activity feed
            if ($targetTask) {
                $this->activity?->recordRelationEvent($targetTask, 'task.relation_added', [
                    'relation_public_id' => (string)($created['public_id'] ?? ''),
                    'relation_type' => $normalized,
                    'source_task_public_id' => $sourceTaskPublicId,
                    'target_task_public_id' => $targetPublicId,
                    'source_task_key' => $sourceKey,
                    'target_task_key' => $targetKey,
                ], $actor);
            }
        }
        return $created;
    }

    /**
     * Delete a relation by its public_id.
     */
    public function delete(string $relationPublicId, array $actor): bool|string
    {
        $relation = $this->relations->findByPublicId($relationPublicId);
        if (!$relation) {
            return false;
        }

        // Check access to at least one of the tasks
        $sourcePublicId = (string)($relation['source_task_public_id'] ?? '');
        $targetPublicId = (string)($relation['target_task_public_id'] ?? '');

        $sourceTask = $sourcePublicId !== '' ? $this->tasks->get($sourcePublicId, $actor) : null;
        $targetTask = $targetPublicId !== '' ? $this->tasks->get($targetPublicId, $actor) : null;

        if (!$sourceTask && !$targetTask) {
            return 'TASK_RELATION_FORBIDDEN';
        }

        $now = gmdate('Y-m-d H:i:s');
        $deleted = $this->relations->softDeleteByPublicId($relationPublicId, $now);
        if ($deleted) {
            if ($sourceTask) {
                $this->activity?->recordRelationEvent($sourceTask, 'task.relation_deleted', [
                    'relation_public_id' => $relationPublicId,
                    'relation_type' => (string)($relation['relation_type'] ?? ''),
                    'source_task_public_id' => $sourcePublicId,
                    'target_task_public_id' => $targetPublicId,
                ], $actor);
            }
            if ($targetTask) {
                $this->activity?->recordRelationEvent($targetTask, 'task.relation_deleted', [
                    'relation_public_id' => $relationPublicId,
                    'relation_type' => (string)($relation['relation_type'] ?? ''),
                    'source_task_public_id' => $sourcePublicId,
                    'target_task_public_id' => $targetPublicId,
                ], $actor);
            }
        }
        return $deleted;
    }

    /**
     * Search tasks for the relation picker.
     */
    public function searchTasks(array $filters, array $actor): array
    {
        $query = trim((string)($filters['q'] ?? ''));
        if ($query === '' || mb_strlen($query) < 2) {
            return [];
        }

        $limit = min(50, max(1, (int)($filters['limit'] ?? 20)));

        return $this->relations->searchTasks($query, $actor, $limit);
    }

    /**
     * Normalize a relation type string.
     */
    public function normalizeRelationType(string $raw): ?string
    {
        $normalized = strtolower(trim($raw));

        // Direct matches
        if (in_array($normalized, self::VALID_TYPES, true)) {
            return $normalized;
        }

        // Aliases
        return match ($normalized) {
            'blocks' => 'blocked_by',
            'related' => 'relates_to',
            'duplicates' => 'duplicate',
            'implemented_by' => 'implements',
            'causes' => 'caused_by',
            'child_of' => 'parent_of',
            default => null,
        };
    }

    /**
     * Check if the raw input was a reverse alias that requires swapping source/target.
     */
    public function isReverseAlias(string $raw): bool
    {
        return isset(self::REVERSE_ALIASES[strtolower(trim($raw))]);
    }

    /**
     * Check if a type is symmetric (direction doesn't matter).
     */
    public function isSymmetricType(string $type): bool
    {
        return in_array($type, self::SYMMETRIC_TYPES, true);
    }

    /**
     * Build the active_key for a relation.
     */
    public function buildActiveKey(string $type, int $sourceId, int $targetId): string
    {
        if ($this->isSymmetricType($type)) {
            $minId = min($sourceId, $targetId);
            $maxId = max($sourceId, $targetId);
            return $type . ':' . $minId . ':' . $maxId;
        }

        return $type . ':' . $sourceId . ':' . $targetId;
    }

    /**
     * Build the grouped response from raw relation rows.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    public function buildGroupedResponse(array $rows, int $currentTaskId, array $actor): array
    {
        $groups = [
            'blocking' => [],
            'blocked_by' => [],
            'duplicates' => [],
            'relates_to' => [],
            'implements' => [],
            'implemented_by' => [],
            'causes' => [],
            'caused_by' => [],
            'parent_of' => [],
            'child_of' => [],
        ];

        $otherTaskPublicIds = [];
        foreach ($rows as $row) {
            $isSource = (int)($row['source_task_id'] ?? 0) === $currentTaskId;
            $otherPublicId = $isSource ? (string)($row['target_task_public_id'] ?? '') : (string)($row['source_task_public_id'] ?? '');
            if ($otherPublicId !== '') {
                $otherTaskPublicIds[$otherPublicId] = true;
            }
        }

        // Batch-load accessible other tasks
        $accessibleIds = [];
        foreach (array_keys($otherTaskPublicIds) as $pid) {
            $otherTask = $this->tasks->get($pid, $actor);
            if ($otherTask) {
                $accessibleIds[$pid] = $otherTask;
            }
        }

        foreach ($rows as $row) {
            $type = (string)($row['relation_type'] ?? '');

            // Determine if current task is source or target
            $isSource = (int)($row['source_task_id'] ?? 0) === $currentTaskId;
            $isTarget = (int)($row['target_task_id'] ?? 0) === $currentTaskId;

            // The "other" task info
            $otherPublicId = $isSource ? (string)($row['target_task_public_id'] ?? '') : (string)($row['source_task_public_id'] ?? '');

            // Check access to the other task (use cached batch result)
            if ($otherPublicId !== '' && !isset($accessibleIds[$otherPublicId])) {
                continue;
            }

            $otherTask = $accessibleIds[$otherPublicId] ?? null;
            $otherTaskKey = $otherTask !== null ? (string)($otherTask['task_key'] ?? '') : ($isSource ? (string)($row['target_task_key'] ?? '') : (string)($row['source_task_key'] ?? ''));
            $otherTitle = $otherTask !== null ? (string)($otherTask['title'] ?? '') : ($isSource ? (string)($row['target_task_title'] ?? '') : (string)($row['source_task_title'] ?? ''));
            $otherStatusCode = $otherTask !== null ? (string)($otherTask['status_code'] ?? '') : ($isSource ? (string)($row['target_task_status_code'] ?? '') : (string)($row['source_task_status_code'] ?? ''));

            $item = [
                'relation_public_id' => (string)($row['public_id'] ?? ''),
                'task_public_id' => $otherPublicId,
                'task_key' => $otherTaskKey,
                'title' => $otherTitle,
                'status_code' => $otherStatusCode,
                'relation_type' => $this->getDisplayType($type, $isSource, $isTarget),
                'note' => (string)($row['note'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];

            $groupKey = $this->getGroupKey($type, $isSource, $isTarget);
            if ($groupKey !== null && isset($groups[$groupKey])) {
                $groups[$groupKey][] = $item;
            }
        }

        $meta = [
            'blocked' => $groups['blocked_by'] !== [],
            'blocking_count' => count($groups['blocking']),
            'blocked_by_count' => count($groups['blocked_by']),
            'duplicates_count' => count($groups['duplicates']),
            'relates_to_count' => count($groups['relates_to']),
        ];

        return [
            'groups' => $groups,
            'meta' => $meta,
        ];
    }

    /**
     * Get the display relation type for a stored relation.
     */
    private function getDisplayType(string $storedType, bool $isSource, bool $isTarget): string
    {
        if ($this->isSymmetricType($storedType)) {
            return $storedType;
        }

        if ($isSource) {
            return match ($storedType) {
                'blocked_by' => 'blocked_by',
                'implements' => 'implements',
                'caused_by' => 'caused_by',
                'parent_of' => 'parent_of',
                default => $storedType,
            };
        }

        // Current task is the target, show reverse
        return match ($storedType) {
            'blocked_by' => 'blocks',
            'implements' => 'implemented_by',
            'caused_by' => 'causes',
            'parent_of' => 'child_of',
            default => $storedType,
        };
    }

    /**
     * Get the group key for a relation based on stored type and direction.
     */
    private function getGroupKey(string $storedType, bool $isSource, bool $isTarget): ?string
    {
        if ($this->isSymmetricType($storedType)) {
            return match ($storedType) {
                'relates_to' => 'relates_to',
                'duplicate' => 'duplicates',
                default => null,
            };
        }

        if ($isSource) {
            return match ($storedType) {
                'blocked_by' => 'blocked_by',
                'implements' => 'implements',
                'caused_by' => 'caused_by',
                'parent_of' => 'parent_of',
                default => null,
            };
        }

        // Current task is target, show reverse
        return match ($storedType) {
            'blocked_by' => 'blocking',
            'implements' => 'implemented_by',
            'caused_by' => 'causes',
            'parent_of' => 'child_of',
            default => null,
        };
    }
}
