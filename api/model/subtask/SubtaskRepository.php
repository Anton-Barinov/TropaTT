<?php
declare(strict_types=1);

namespace Api\Model\Subtask;

use Api\System\Library\Database\Builder\Expression;
use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class SubtaskRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listByTaskPublicId(string $taskPublicId): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('task_relations r')
            ->join('tasks pt', 'pt.id', '=', 'r.parent_task_id')
            ->join('tasks st', 'st.id', '=', 'r.child_task_id')
            ->leftJoin('users su', 'su.id', '=', 'st.assignee_user_id')
            ->leftJoin('users cu', 'cu.id', '=', 'st.creator_user_id')
            ->leftJoin('projects p', 'p.id', '=', 'st.project_id')
            ->select($this->subtaskSelect())
            ->where('r.relation_type', '=', 'subtask')
            ->where('pt.public_id', '=', $taskPublicId)
            ->whereNull('st.deleted_at')
            ->orderBy('r.sort_order', 'ASC')
            ->orderBy('st.created_at', 'ASC')
            ->get();
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('task_relations r')
            ->join('tasks pt', 'pt.id', '=', 'r.parent_task_id')
            ->join('tasks st', 'st.id', '=', 'r.child_task_id')
            ->leftJoin('users su', 'su.id', '=', 'st.assignee_user_id')
            ->leftJoin('users cu', 'cu.id', '=', 'st.creator_user_id')
            ->leftJoin('projects p', 'p.id', '=', 'st.project_id')
            ->select($this->subtaskSelect())
            ->where('r.relation_type', '=', 'subtask')
            ->whereRaw('(st.public_id = ? OR r.legacy_subtask_public_id = ?)', [$publicId, $publicId])
            ->whereNull('st.deleted_at')
            ->first();
    }

    public function createTask(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->insert($payload);
    }

    public function createRelation(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('task_relations')
            ->insert($payload);
    }

    public function updateTaskByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        $set['row_version'] = new Expression('row_version + 1');

        return (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->where('public_id', '=', $publicId)
            ->whereNull('deleted_at')
            ->update($set) > 0;
    }

    public function updateRelationByChildTaskPublicId(string $childTaskPublicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        $childId = $this->taskIdByPublicId($childTaskPublicId);
        if ($childId === null) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('task_relations')
            ->where('child_task_id', '=', $childId)
            ->where('relation_type', '=', 'subtask')
            ->update($set) > 0;
    }

    public function deleteRelationByChildTaskPublicId(string $childTaskPublicId): bool
    {
        $childId = $this->taskIdByPublicId($childTaskPublicId);
        if ($childId === null) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('task_relations')
            ->where('child_task_id', '=', $childId)
            ->where('relation_type', '=', 'subtask')
            ->delete() > 0;
    }

    public function softDeleteTaskByPublicId(string $publicId, string $deletedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->where('public_id', '=', $publicId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
                'row_version' => new Expression('row_version + 1'),
            ]) > 0;
    }

    public function parentTaskByPublicId(string $taskPublicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->select(['id', 'public_id', 'project_id', 'priority_code', 'creator_user_id'])
            ->where('public_id', '=', $taskPublicId)
            ->whereNull('deleted_at')
            ->first();
    }

    public function nextSortOrderForParentTaskId(int $parentTaskId): int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('task_relations')
            ->select(['MAX(sort_order) AS max_sort_order'])
            ->where('parent_task_id', '=', $parentTaskId)
            ->where('relation_type', '=', 'subtask')
            ->first();

        return max(0, (int)($row['max_sort_order'] ?? 0)) + 10;
    }

    public function taskIdByPublicId(string $taskPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->select(['id'])
            ->where('public_id', '=', $taskPublicId)
            ->first();
        $id = $row['id'] ?? false;

        return $id !== false ? (int)$id : null;
    }

    public function userIdByPublicId(string $userPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['id'])
            ->where('public_id', '=', $userPublicId)
            ->first();
        $id = $row['id'] ?? false;

        return $id !== false ? (int)$id : null;
    }

    private function subtaskSelect(): array
    {
        return [
            'st.id AS child_task_id',
            'st.public_id',
            'st.title',
            'st.description',
            'st.status_code',
            'st.priority_code',
            'st.start_at',
            'st.due_at',
            'st.end_at',
            'st.assignee_user_id',
            'st.creator_user_id',
            'st.row_version',
            'st.created_at',
            'st.updated_at',
            'r.sort_order',
            'r.public_id AS relation_public_id',
            'r.legacy_subtask_public_id',
            'pt.id AS parent_task_id',
            'pt.public_id AS parent_task_public_id',
            'pt.public_id AS task_public_id',
            'su.public_id AS assignee_user_public_id',
            'su.full_name AS assignee_name',
            'cu.public_id AS creator_user_public_id',
            'cu.full_name AS creator_name',
            'p.public_id AS project_public_id',
            'p.title AS project_title',
        ];
    }
}
