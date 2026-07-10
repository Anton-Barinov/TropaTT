<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Team\TeamRepository;
use Api\Model\Task\TaskRepository;
use Api\Model\Task\TaskKeyCounterRepository;
use Api\Model\Project\ProjectRepository;
use Api\System\Library\Support\Ulid;

final class TaskService
{
    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly ProjectService $projects,
        private readonly TeamRepository $teams,
        private readonly ?NotificationService $notifications = null,
        private readonly ?AiSemanticIndexService $semanticIndex = null,
        private readonly ?TaskActivityService $activity = null,
        private readonly ?TaskKeyService $taskKeys = null,
        private readonly ?TaskKeyCounterRepository $keyCounters = null,
        private readonly ?ProjectRepository $projectRepo = null
    )
    {
    }

    public function list(array $filters, array $actor): array
    {
        if (!empty($filters['project_public_id']) && !$this->projects->get((string)$filters['project_public_id'], $actor)) {
            return [
                'items' => [],
                'meta' => [
                    'pagination_mode' => 'offset',
                    'pagination' => [
                        'page' => 1,
                        'limit' => min(100, max(1, (int)($filters['limit'] ?? 20))),
                        'total' => 0,
                        'pages' => 0,
                    ],
                ],
            ];
        }

        $filters['accessible_team_public_ids'] = $this->accessibleTeamPublicIds($actor);
        $result = $this->tasks->list(
            $filters,
            (int)($actor['id'] ?? 0),
            (bool)($actor['is_root'] ?? false)
        );

        $items = (array)($result['items'] ?? []);
        $mode = (string)($result['mode'] ?? 'offset');
        $limit = (int)($result['limit'] ?? 20);
        $total = isset($result['total']) ? (int)$result['total'] : null;
        $page = (int)($result['page'] ?? 1);
        $nextCursor = isset($result['next_cursor']) ? (string)$result['next_cursor'] : null;
        $hasMore = (bool)($result['has_more'] ?? false);

        $meta = [
            'pagination_mode' => $mode,
        ];

        if ($mode === 'cursor') {
            $meta['cursor'] = [
                'next' => $nextCursor,
                'has_more' => $hasMore,
                'limit' => $limit,
            ];
        } else {
            $meta['pagination'] = [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil((int)$total / max(1, $limit)),
            ];
        }

        if (!empty($filters['updated_since'])) {
            $meta['sync'] = [
                'updated_since' => (string)$filters['updated_since'],
            ];
        }

        return [
            'items' => $items,
            'meta' => $meta,
        ];
    }

    public function create(array $input, array $actor): array|string
    {
        $publicId = Ulid::generate('tsk');
        $projectId = null;
        $creatorUserId = (int)($actor['id'] ?? 0);
        $parentTask = null;
        $parentTaskPublicId = trim((string)($input['parent_task_public_id'] ?? ''));

        if (!empty($input['project_public_id'])) {
            if (!$this->projects->get((string)$input['project_public_id'], $actor)) {
                return 'PROJECT_NOT_FOUND';
            }
            $projectId = $this->tasks->projectIdByPublicId((string)$input['project_public_id']);
        }

        if ($parentTaskPublicId !== '') {
            $parentTask = $this->get($parentTaskPublicId, $actor);
            if (!$parentTask) {
                return 'PARENT_TASK_NOT_FOUND';
            }
            $projectId ??= isset($parentTask['project_id']) ? (int)$parentTask['project_id'] : null;
        }

        // Generate task key
        $taskKeyData = $this->generateTaskKey($projectId, $input);
        $taskKey = $taskKeyData['task_key'] ?? null;
        $taskKeyPrefix = $taskKeyData['task_key_prefix'] ?? null;
        $taskSequenceNumber = $taskKeyData['task_sequence_number'] ?? null;

        $createdAt = !empty($input['created_at']) ? (string)$input['created_at'] : gmdate('Y-m-d H:i:s');
        $updatedAt = !empty($input['updated_at']) ? (string)$input['updated_at'] : $createdAt;

        $this->tasks->create([
            'public_id' => $publicId,
            'project_id' => $projectId,
            'task_key' => $taskKey,
            'task_key_prefix' => $taskKeyPrefix,
            'task_sequence_number' => $taskSequenceNumber,
            'title' => trim((string)$input['title']),
            'description' => trim((string)($input['description'] ?? '')),
            'status_code' => (string)($input['status'] ?? 'new'),
            'priority_code' => (string)($input['priority'] ?? 'normal'),
            'due_at' => !empty($input['due_at']) ? (string)$input['due_at'] : null,
            'start_at' => !empty($input['start_at']) ? (string)$input['start_at'] : null,
            'end_at' => !empty($input['end_at']) ? (string)$input['end_at'] : null,
            'assignee_user_id' => isset($input['assignee_user_id']) ? (int)$input['assignee_user_id'] : null,
            'creator_user_id' => $creatorUserId,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'row_version' => 1,
        ]);

        if ($parentTask) {
            $createdTaskId = $this->tasks->taskIdByPublicId($publicId);
            if ($createdTaskId !== null) {
                $sortOrder = isset($input['sort_order'])
                    ? max(0, (int)$input['sort_order'])
                    : $this->tasks->nextSortOrderForParentTaskId((int)$parentTask['id']);

                $this->tasks->createRelation([
                    'public_id' => Ulid::generate('trl'),
                    'parent_task_id' => (int)$parentTask['id'],
                    'child_task_id' => $createdTaskId,
                    'relation_type' => 'subtask',
                    'sort_order' => $sortOrder,
                    'legacy_subtask_public_id' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]);
            }
        }

        $createdTask = $this->tasks->findByPublicId($publicId) ?: ['public_id' => $publicId];
        if (is_array($createdTask)) {
            $this->notifications?->notifyTaskCreated($createdTask, $actor);
            $this->activity?->recordTaskCreated($createdTask, $actor, ['source_type' => $input['source_type'] ?? 'web']);
        }

        return $createdTask;
    }

    public function get(string $publicId, array $actor): ?array
    {
        $task = $this->tasks->findByPublicId($publicId);
        if (!$task) {
            return null;
        }
        if ((string)($task['deleted_at'] ?? '') !== '') {
            return null;
        }

        if (!$this->canAccess($task, $actor)) {
            return null;
        }

        return $task;
    }

    public function getByTaskKey(string $taskKey, array $actor): ?array
    {
        $normalized = strtoupper(trim($taskKey));

        // Validate format
        if (preg_match('/^[A-Z][A-Z0-9]{1,9}-[1-9][0-9]*$/', $normalized) !== 1) {
            return null;
        }

        $task = $this->tasks->findByTaskKey($normalized);
        if (!$task) {
            return null;
        }
        if ((string)($task['deleted_at'] ?? '') !== '') {
            return null;
        }

        if (!$this->canAccess($task, $actor)) {
            return null;
        }

        return $task;
    }

    /** @return array<string,mixed>|null|'ROW_VERSION_CONFLICT'|'PROJECT_NOT_FOUND'|'PARENT_TASK_NOT_FOUND'|'INVALID_PARENT_TASK'|'FORBIDDEN_TASK_IDENTITY_EDIT'|'CYCLIC_DEPENDENCY_DETECTED' */
    public function update(string $publicId, array $input, int $actorUserId, array $actor): array|string|null
    {
        $task = $this->tasks->findByPublicId($publicId);
        if (!$task) {
            return null;
        }
        if ((string)($task['deleted_at'] ?? '') !== '') {
            return null;
        }
        if (!$this->canAccess($task, $actor)) {
            return null;
        }

        $expectedRowVersion = null;
        if (array_key_exists('row_version', $input)) {
            $expected = (int)$input['row_version'];
            $current = (int)($task['row_version'] ?? 0);
            if ($expected > 0 && $expected !== $current) {
                return 'ROW_VERSION_CONFLICT';
            }
            if ($expected > 0) {
                $expectedRowVersion = $expected;
            }
        }

        $isAuthor = (int)($task['creator_user_id'] ?? 0) === $actorUserId;
        if (!$isAuthor && (array_key_exists('title', $input) || array_key_exists('description', $input))) {
            return 'FORBIDDEN_TASK_IDENTITY_EDIT';
        }

        $set = [];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('description', $input)) {
            $set['description'] = trim((string)$input['description']);
        }
        if (array_key_exists('status', $input)) {
            $set['status_code'] = (string)$input['status'];
        }
        if (array_key_exists('priority', $input)) {
            $set['priority_code'] = (string)$input['priority'];
        }
        if (array_key_exists('due_at', $input)) {
            $set['due_at'] = $input['due_at'] !== '' ? (string)$input['due_at'] : null;
        }
        if (array_key_exists('assignee_user_id', $input)) {
            $set['assignee_user_id'] = $input['assignee_user_id'] !== null ? (int)$input['assignee_user_id'] : null;
        }
        // task_key is not editable
        if (array_key_exists('task_key', $input) || array_key_exists('task_key_prefix', $input) || array_key_exists('task_sequence_number', $input)) {
            return 'TASK_KEY_FIELD_NOT_EDITABLE';
        }

        if (array_key_exists('project_public_id', $input)) {
            $projectPublicId = trim((string)$input['project_public_id']);
            if ($projectPublicId === '') {
                $set['project_id'] = null;
            } else {
                if (!$this->projects->get($projectPublicId, $actor)) {
                    return 'PROJECT_NOT_FOUND';
                }
                $projectId = $this->tasks->projectIdByPublicId($projectPublicId);
                if ($projectId === null) {
                    return 'PROJECT_NOT_FOUND';
                }
                $set['project_id'] = $projectId;
            }
        }
        if (array_key_exists('archived', $input)) {
            $set['archived_at'] = (bool)$input['archived'] ? gmdate('Y-m-d H:i:s') : null;
        }

        $parentRelationChange = null;
        if (array_key_exists('parent_task_public_id', $input)) {
            $parentTaskPublicId = trim((string)$input['parent_task_public_id']);
            if ($parentTaskPublicId === '') {
                $parentRelationChange = ['mode' => 'delete'];
            } else {
                if ($parentTaskPublicId === $publicId) {
                    return 'INVALID_PARENT_TASK';
                }
                if ($this->tasks->hasCycleAncestor((int)($task['id'] ?? 0), (int)($parentTask['id'] ?? 0))) {
                    return 'CYCLIC_DEPENDENCY_DETECTED';
                }
                $parentTask = $this->get($parentTaskPublicId, $actor);
                if (!$parentTask) {
                    return 'PARENT_TASK_NOT_FOUND';
                }
                $parentRelationChange = [
                    'mode' => 'upsert',
                    'parent_task' => $parentTask,
                    'sort_order' => array_key_exists('sort_order', $input) ? max(0, (int)$input['sort_order']) : null,
                ];
            }
        }

        $set['updated_at'] = gmdate('Y-m-d H:i:s');

        $oldStatus = (string)$task['status_code'];
        $updated = $this->tasks->updateByPublicId($publicId, $set, $expectedRowVersion);
        if (!$updated && $expectedRowVersion !== null) {
            return 'ROW_VERSION_CONFLICT';
        }
        if (!$updated) {
            return $task;
        }

        if ($parentRelationChange !== null) {
            $childTaskId = (int)($task['id'] ?? 0);
            if (($parentRelationChange['mode'] ?? '') === 'delete') {
                $this->tasks->deleteSubtaskRelationByChildTaskId($childTaskId);
            } else {
                $parentTask = (array)$parentRelationChange['parent_task'];

                $relationSet = [
                    'parent_task_id' => (int)$parentTask['id'],
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ];
                if ($parentRelationChange['sort_order'] !== null) {
                    $relationSet['sort_order'] = (int)$parentRelationChange['sort_order'];
                }

                $relationUpdated = $this->tasks->updateSubtaskRelationByChildTaskId($childTaskId, $relationSet);
                if (!$relationUpdated) {
                    $this->tasks->createRelation([
                        'public_id' => Ulid::generate('trl'),
                        'parent_task_id' => (int)$parentTask['id'],
                        'child_task_id' => $childTaskId,
                        'relation_type' => 'subtask',
                        'sort_order' => $parentRelationChange['sort_order'] !== null
                            ? (int)$parentRelationChange['sort_order']
                            : $this->tasks->nextSortOrderForParentTaskId((int)$parentTask['id']),
                        'legacy_subtask_public_id' => null,
                        'created_at' => gmdate('Y-m-d H:i:s'),
                        'updated_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
        $this->semanticIndex?->removeEntityDocument('task', $publicId);

        if (isset($set['status_code']) && $set['status_code'] !== $oldStatus) {
            $this->tasks->createStatusHistory([
                'public_id' => Ulid::generate('tsh'),
                'task_id' => (int)$task['id'],
                'old_status' => $oldStatus,
                'new_status' => (string)$set['status_code'],
                'changed_by_user_id' => $actorUserId,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }

        $updatedTask = $this->tasks->findByPublicId($publicId);
        if (!$updatedTask || !$this->canAccess($updatedTask, $actor)) {
            return null;
        }

        if (array_key_exists('status_code', $set) && (string)($updatedTask['status_code'] ?? '') !== $oldStatus) {
            $this->notifications?->notifyTaskStatusChanged($task, $updatedTask, $actor);
        }

        if (array_key_exists('assignee_user_id', $set) && (int)($task['assignee_user_id'] ?? 0) !== (int)($updatedTask['assignee_user_id'] ?? 0)) {
            $this->notifications?->notifyTaskAssignmentChanged($task, $updatedTask, $actor);
        }

        if (array_key_exists('due_at', $set) && (string)($task['due_at'] ?? '') !== (string)($updatedTask['due_at'] ?? '')) {
            $this->notifications?->notifyTaskDueChanged($task, $updatedTask, $actor);
        }

        // Record activity for relevant field changes
        if ($this->activity !== null) {
            $changes = $this->activity->detectChanges($task, $updatedTask);
            if ($changes !== []) {
                $this->activity->recordManyFieldChanges($updatedTask, $changes, $actor, ['source_type' => $input['source_type'] ?? 'web']);
            }
        }

        return $updatedTask;
    }

    public function delete(string $publicId, array $actor): bool
    {
        $task = $this->tasks->findByPublicId($publicId);
        if (!$task) {
            return false;
        }
        if ((string)($task['deleted_at'] ?? '') !== '') {
            return false;
        }
        if (!$this->canAccess($task, $actor)) {
            return false;
        }

        $deleted = $this->tasks->softDeleteByPublicId($publicId, gmdate('Y-m-d H:i:s'));
        if ($deleted) {
            $this->semanticIndex?->removeEntityDocument('task', $publicId);
            $this->activity?->recordFieldChanged($task, 'archived_at', null, gmdate('Y-m-d H:i:s'), $actor, ['source_type' => 'web']);
        }

        return $deleted;
    }

    /** @param array<string,mixed> $task */
    /** @param array<string,mixed> $actor */
    private function canAccess(array $task, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return false;
        }

        return (int)($task['creator_user_id'] ?? 0) === $actorId
            || (int)($task['assignee_user_id'] ?? 0) === $actorId
            || (int)($task['project_creator_user_id'] ?? 0) === $actorId
            || (int)($task['project_manager_user_id'] ?? 0) === $actorId
            || (int)($task['project_team_manager_user_id'] ?? 0) === $actorId
            || in_array($actorId, $this->decodeTeamMemberIds($task['project_team_member_user_ids'] ?? null), true);
    }

    /** @return string[] */
    private function accessibleTeamPublicIds(array $actor): array
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return [];
        }

        return $this->teams->listAccessiblePublicIdsForUser((int)($actor['id'] ?? 0));
    }

    /** @return int[] */
    private function decodeTeamMemberIds(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $decoded), static fn(int $value): bool => $value > 0)));
    }

    /** @return array{task_key: string|null, task_key_prefix: string|null, task_sequence_number: int|null} */
    private function generateTaskKey(?int $projectId, array $input): array
    {
        if ($this->taskKeys === null) {
            return ['task_key' => null, 'task_key_prefix' => null, 'task_sequence_number' => null];
        }

        // Determine project prefix
        $projectPrefix = null;
        if ($projectId !== null && $projectId > 0 && $this->projectRepo !== null) {
            $projectPrefix = $this->projectRepo->taskKeyPrefixById($projectId);
            if ($projectPrefix === null || $projectPrefix === '') {
                // Try to get from project
                if (!empty($input['project_public_id'])) {
                    $project = $this->projects->get((string)$input['project_public_id'], []);
                    if ($project && !empty($project['task_key_prefix'])) {
                        $projectPrefix = (string)$project['task_key_prefix'];
                    }
                }
            }
        }

        $result = $this->taskKeys->assignNextTaskKey($projectId, $projectPrefix);
        if ($result === null) {
            throw new \RuntimeException('Failed to generate task key');
        }

        return $result;
    }
}
