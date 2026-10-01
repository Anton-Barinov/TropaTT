<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\System\Library\Support\AppLog;
use Api\Model\Subtask\SubtaskRepository;
use Api\System\Library\Security\ActorPermission;
use Api\System\Library\Security\HtmlSanitizer;
use Api\System\Library\Support\Ulid;

final class SubtaskService
{
    public function __construct(
        private readonly SubtaskRepository $subtasks,
        private readonly TaskService $tasks,
        private readonly ?TaskKeyService $taskKeys = null,
        private readonly ?HtmlSanitizer $htmlSanitizer = null,
        private readonly ?ProjectService $projects = null
    ) {
    }

    public function listByTask(string $taskPublicId, array $actor): ?array
    {
        $task = $this->tasks->get($taskPublicId, $actor);
        if (!$task) {
            return null;
        }

        return array_map(fn(array $item): array => $this->sanitizeSubtask($item), $this->subtasks->listByTaskPublicId($taskPublicId));
    }

    /** @return array<string,mixed>|'DESCRIPTION_TOO_LONG'|'PROJECT_NOT_FOUND'|null */
    public function create(string $taskPublicId, array $input, array $actor): array|string|null
    {
        $task = $this->tasks->get($taskPublicId, $actor);
        if (!$task) {
            return null;
        }

        $parentTask = $this->subtasks->parentTaskByPublicId($taskPublicId);
        if (!$parentTask) {
            return null;
        }

        $assigneeUserId = null;
        if (!empty($input['assignee_user_public_id'])) {
            $assigneeUserId = $this->subtasks->userIdByPublicId((string)$input['assignee_user_public_id']);
        }

        $childPublicId = Ulid::generate('tsk');
        $now = !empty($input['created_at']) ? (string)$input['created_at'] : gmdate('Y-m-d H:i:s');
        $updatedAt = !empty($input['updated_at']) ? (string)$input['updated_at'] : $now;

        $sortOrder = isset($input['sort_order'])
            ? max(0, (int)$input['sort_order'])
            : $this->subtasks->nextSortOrderForParentTaskId((int)$parentTask['id']);

        // ТЗ 4.3: subtasks must always get a key. The prefix is inherited from
        // the parent task (TASK-1 -> TASK-2, PRJ-1 -> PRJ-2), falling back to
        // the project prefix and then to the global TASK prefix. Without this a
        // subtask would be created with an empty task_key.
        $parentProjectId = (int)($parentTask['project_id'] ?? 0) ?: null;
        $childProjectId = $parentProjectId;
        // The create form offers the full project list, so the author may point
        // the subtask at another project. An explicit, accessible project wins;
        // an empty or absent field keeps the historical inheritance from the
        // parent task (the field is pre-filled with the parent's project).
        if ($this->hasExplicitProject($input)) {
            $explicitProjectId = $this->resolveProjectId((string)$input['project_public_id'], $actor);
            if ($explicitProjectId === null) {
                return 'PROJECT_NOT_FOUND';
            }
            $childProjectId = $explicitProjectId;
        }

        $taskKeyData = null;
        if ($this->taskKeys !== null) {
            // parentTaskByPublicId() selects a minimal column set, so read the
            // prefix from the fully-loaded task row (same task) instead.
            $inheritedPrefix = trim((string)($task['task_key_prefix'] ?? $parentTask['task_key_prefix'] ?? ''));
            // The parent's prefix only describes the parent's project: a subtask
            // deliberately moved elsewhere is keyed by the project it lands in.
            $keyPrefix = $childProjectId === $parentProjectId && $inheritedPrefix !== '' ? $inheritedPrefix : null;
            $taskKeyData = $this->taskKeys->assignNextTaskKey($childProjectId, $keyPrefix);
        }

        $description = $this->sanitizeDescription((string)($input['description'] ?? ''));
        if (mb_strlen($description) > 8000) {
            return 'DESCRIPTION_TOO_LONG';
        }

        $organizationId = isset($actor['organization_id']) && (int)$actor['organization_id'] > 0
            ? (int)$actor['organization_id']
            : (isset($parentTask['organization_id']) && (int)$parentTask['organization_id'] > 0 ? (int)$parentTask['organization_id'] : null);

        $this->subtasks->createTask([
            'public_id' => $childPublicId,
            'organization_id' => $organizationId,
            'project_id' => $childProjectId,
            // A subtask belongs to the same work context as its parent: the
            // project was already inherited above, and the parent's own client
            // must travel with it — otherwise the child shows up in the task
            // lists as a loose task with no client even though its parent has
            // one (the project's client is a read-time join and does not count
            // as the task's own client).
            'client_public_id' => $parentTask['client_public_id'] ?? null,
            'task_key' => $taskKeyData['task_key'] ?? null,
            'task_key_prefix' => $taskKeyData['task_key_prefix'] ?? null,
            'task_sequence_number' => $taskKeyData['task_sequence_number'] ?? null,
            'title' => trim((string)$input['title']),
            'description' => $description,
            'status_code' => (string)($input['status'] ?? 'new'),
            'priority_code' => (string)($input['priority'] ?? ($parentTask['priority_code'] ?? 'normal')),
            'due_at' => !empty($input['due_at']) ? $this->normalizeDueAt((string)$input['due_at']) : null,
            'start_at' => null,
            'end_at' => null,
            'assignee_user_id' => $assigneeUserId,
            'creator_user_id' => (int)($actor['id'] ?? 0) ?: (int)($parentTask['creator_user_id'] ?? 0),
            'archived_at' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $updatedAt,
            'row_version' => 1,
        ]);

        $childTaskId = $this->subtasks->taskIdByPublicId($childPublicId);
        if ($childTaskId === null) {
            return null;
        }

        $this->subtasks->createRelation([
            'public_id' => Ulid::generate('trl'),
            'parent_task_id' => (int)$parentTask['id'],
            'child_task_id' => $childTaskId,
            'relation_type' => 'subtask',
            'sort_order' => $sortOrder,
            'legacy_subtask_public_id' => null,
            'created_at' => $now,
            'updated_at' => $updatedAt,
        ]);

        $created = $this->subtasks->findByPublicId($childPublicId);
        return $created ? $this->sanitizeSubtask($created) : null;
    }

    public function get(string $publicId, array $actor): ?array
    {
        $item = $this->subtasks->findByPublicId($publicId);
        if (!$item) {
            return null;
        }

        $parentTask = $this->tasks->get((string)$item['parent_task_public_id'], $actor);
        if (!$parentTask) {
            return null;
        }

        return $this->sanitizeSubtask($item);
    }

    /** @return array<string,mixed>|'DESCRIPTION_TOO_LONG'|'PROJECT_NOT_FOUND'|null */
    public function update(string $publicId, array $input, array $actor): array|string|null
    {
        $current = $this->subtasks->findByPublicId($publicId);
        if (!$current) {
            return null;
        }

        $parentTask = $this->tasks->get((string)$current['parent_task_public_id'], $actor);
        if (!$parentTask) {
            return null;
        }

        if (array_key_exists('description', $input)) {
            $input['description'] = $this->sanitizeDescription((string)$input['description']);
            if (mb_strlen($input['description']) > 8000) {
                return 'DESCRIPTION_TOO_LONG';
            }
        }

        $taskSet = [];
        if (array_key_exists('title', $input)) {
            $taskSet['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('description', $input)) {
            $taskSet['description'] = trim((string)$input['description']);
        }
        if (array_key_exists('status', $input)) {
            $taskSet['status_code'] = (string)$input['status'];
        }
        if (array_key_exists('priority', $input)) {
            $taskSet['priority_code'] = (string)$input['priority'];
        }
        if (array_key_exists('due_at', $input)) {
            $taskSet['due_at'] = $input['due_at'] !== '' ? $this->normalizeDueAt((string)$input['due_at']) : null;
        }
        if (array_key_exists('assignee_user_public_id', $input)) {
            $taskSet['assignee_user_id'] = $input['assignee_user_public_id'] !== ''
                ? $this->subtasks->userIdByPublicId((string)$input['assignee_user_public_id'])
                : null;
        }
        if (array_key_exists('project_public_id', $input)) {
            $projectPublicId = trim((string)$input['project_public_id']);
            if ($projectPublicId === '') {
                // Empty puts the subtask back where it started: the parent's
                // project. A subtask is never left dangling while its parent
                // still sits in a project.
                $parentProjectId = (int)($parentTask['project_id'] ?? 0) ?: null;
                if ($parentProjectId !== null) {
                    $taskSet['project_id'] = $parentProjectId;
                }
            } else {
                $projectId = $this->resolveProjectId($projectPublicId, $actor);
                if ($projectId === null) {
                    return 'PROJECT_NOT_FOUND';
                }
                $taskSet['project_id'] = $projectId;
            }
        }
        if ($taskSet !== []) {
            $taskSet['updated_at'] = gmdate('Y-m-d H:i:s');
            $this->subtasks->updateTaskByPublicId((string)$current['public_id'], $taskSet);
        }

        $relationSet = [];
        if (array_key_exists('sort_order', $input)) {
            $relationSet['sort_order'] = max(0, (int)$input['sort_order']);
        }
        if ($relationSet !== []) {
            $relationSet['updated_at'] = gmdate('Y-m-d H:i:s');
            $this->subtasks->updateRelationByChildTaskPublicId((string)$current['public_id'], $relationSet);
        }

        $updated = $this->subtasks->findByPublicId((string)$current['public_id']);
        return $updated ? $this->sanitizeSubtask($updated) : null;
    }

    public function delete(string $publicId, array $actor): bool
    {
        $current = $this->subtasks->findByPublicId($publicId);
        if (!$current) {
            return false;
        }

        // The parent lookup authorises the call. When the parent task itself is
        // gone the subtask is an orphan left by an older build (deleting a task
        // did not cascade) — it must still be removable, otherwise it stays in
        // task lists forever. Ownership of the subtask row is the fallback check.
        $parentTask = $this->tasks->get((string)$current['parent_task_public_id'], $actor);
        if (!$parentTask && !$this->canDeleteOrphanSubtask($current, $actor)) {
            return false;
        }

        $deletedAt = gmdate('Y-m-d H:i:s');
        $this->subtasks->deleteRelationByChildTaskPublicId((string)$current['public_id']);

        return $this->subtasks->softDeleteTaskByPublicId((string)$current['public_id'], $deletedAt);
    }

    /** @param array<string,mixed> $subtask */
    /** @param array<string,mixed> $actor */
    private function canDeleteOrphanSubtask(array $subtask, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        // Permission = capability: an orphan has no parent task left to reason
        // about, so task.manage is the only sensible grant left.
        if (ActorPermission::canManageTasks($actor)) {
            return true;
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return false;
        }

        return $actorId === (int)($subtask['creator_user_id'] ?? 0)
            || $actorId === (int)($subtask['assignee_user_id'] ?? 0);
    }

    private function sanitizeDescription(string $description): string
    {
        return ($this->htmlSanitizer ?? new HtmlSanitizer())->sanitize($description);
    }

    private function hasExplicitProject(array $input): bool
    {
        return array_key_exists('project_public_id', $input)
            && trim((string)$input['project_public_id']) !== '';
    }

    /**
     * Numeric id of an explicitly chosen project, or null when it cannot be used.
     *
     * Validation mirrors TaskService::create()/update(): the project must exist,
     * belong to the actor's organization and pass ProjectService::canAccess().
     * External guests stay pinned to the parent's work context, so for them any
     * attempt to name a project fails closed instead of silently landing
     * somewhere the parent task does not belong to.
     */
    private function resolveProjectId(string $projectPublicId, array $actor): ?int
    {
        if ($this->projects === null || !empty($actor['is_external'])) {
            return null;
        }

        return $this->projects->resolveProjectIdForActor($projectPublicId, $actor);
    }

    /** @param array<string,mixed> $item */
    private function sanitizeSubtask(array $item): array
    {
        if (array_key_exists('description', $item)) {
            $item['description'] = $this->sanitizeDescription((string)$item['description']);
        }

        return $item;
    }

    private function normalizeDueAt(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        try {
            $date = new \DateTimeImmutable($value);
            return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            AppLog::error('[SubtaskService::normalizeDueAt] ' . $e->getMessage());
            return $value;
        }
    }
}
