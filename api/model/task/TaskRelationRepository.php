<?php
declare(strict_types=1);

namespace Api\Model\Task;

use Api\System\Library\Database\Builder\Expression;
use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;

final class TaskRelationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * List all active relations for a task (as source or target).
     *
     * @return array<int,array<string,mixed>>
     */
    public function listForTaskId(int $taskId): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('task_relations_v2 r')
            ->leftJoin('tasks t_source', 't_source.id', '=', 'r.source_task_id')
            ->leftJoin('tasks t_target', 't_target.id', '=', 'r.target_task_id')
            ->leftJoin('users cu', 'cu.id', '=', 'r.created_by_user_id')
            ->select([
                'r.id',
                'r.public_id',
                'r.source_task_id',
                'r.target_task_id',
                'r.relation_type',
                'r.active_key',
                'r.note',
                'r.created_by_user_id',
                'r.created_at',
                'r.updated_at',
                'r.deleted_at',
                'r.row_version',
                't_source.public_id AS source_task_public_id',
                't_source.task_key AS source_task_key',
                't_source.title AS source_task_title',
                't_source.status_code AS source_task_status_code',
                't_target.public_id AS target_task_public_id',
                't_target.task_key AS target_task_key',
                't_target.title AS target_task_title',
                't_target.status_code AS target_task_status_code',
                'cu.public_id AS created_by_user_public_id',
                'cu.full_name AS created_by_user_name',
            ])
            ->whereRaw('(r.source_task_id = ? OR r.target_task_id = ?)', [$taskId, $taskId])
            ->whereNull('r.deleted_at')
            ->orderBy('r.created_at', 'DESC')
            ->get();
    }

    /**
     * List all active relations for a task by public_id.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listForTaskPublicId(string $taskPublicId): array
    {
        $taskId = $this->taskIdByPublicId($taskPublicId);
        if ($taskId === null) {
            return [];
        }

        return $this->listForTaskId($taskId);
    }

    /**
     * Find a relation by its public_id.
     */
    public function findByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('task_relations_v2 r')
            ->leftJoin('tasks t_source', 't_source.id', '=', 'r.source_task_id')
            ->leftJoin('tasks t_target', 't_target.id', '=', 'r.target_task_id')
            ->leftJoin('users cu', 'cu.id', '=', 'r.created_by_user_id')
            ->select([
                'r.*',
                't_source.public_id AS source_task_public_id',
                't_source.task_key AS source_task_key',
                't_source.title AS source_task_title',
                't_source.status_code AS source_task_status_code',
                't_target.public_id AS target_task_public_id',
                't_target.task_key AS target_task_key',
                't_target.title AS target_task_title',
                't_target.status_code AS target_task_status_code',
                'cu.public_id AS created_by_user_public_id',
                'cu.full_name AS created_by_user_name',
            ])
            ->where('r.public_id', '=', $publicId)
            ->first();

        return $row !== false ? $row : null;
    }

    /**
     * Create a new relation.
     */
    public function create(array $payload): array
    {
        $publicId = Ulid::generate('trl2');

        $now = gmdate('Y-m-d H:i:s');

        (new QueryBuilder($this->pdo))
            ->from('task_relations_v2')
            ->insert([
                'public_id' => $publicId,
                'source_task_id' => (int)($payload['source_task_id'] ?? 0),
                'target_task_id' => (int)($payload['target_task_id'] ?? 0),
                'relation_type' => (string)($payload['relation_type'] ?? ''),
                'active_key' => $payload['active_key'] ?? null,
                'note' => $payload['note'] ?? null,
                'created_by_user_id' => (int)($payload['created_by_user_id'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        $result = $this->findByPublicId($publicId);
        return $result ?? [];
    }

    /**
     * Soft delete a relation by public_id.
     */
    public function softDeleteByPublicId(string $publicId, string $deletedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('task_relations_v2')
            ->where('public_id', '=', $publicId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'active_key' => null,
                'updated_at' => $deletedAt,
                'row_version' => new Expression('row_version + 1'),
            ]) > 0;
    }

    /**
     * Check if a relation with given active_key already exists (non-deleted).
     */
    public function existsByActiveKey(string $activeKey): bool
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('task_relations_v2')
            ->select(['id'])
            ->where('active_key', '=', $activeKey)
            ->whereNull('deleted_at')
            ->first();

        return $row !== false;
    }

    /**
     * Find task internal ID by public_id.
     */
    public function taskIdByPublicId(string $taskPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->select(['id'])
            ->where('public_id', '=', $taskPublicId)
            ->whereNull('deleted_at')
            ->first();
        $id = $row['id'] ?? false;

        return $id !== false ? (int)$id : null;
    }

    /**
     * Find task internal ID by task_key.
     */
    public function taskIdByTaskKey(string $taskKey): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->select(['id'])
            ->where('task_key', '=', $taskKey)
            ->whereNull('deleted_at')
            ->first();
        $id = $row['id'] ?? false;

        return $id !== false ? (int)$id : null;
    }

    /**
     * Search tasks by public_id, task_key, or title.
     *
     * @return array<int,array<string,mixed>>
     */
    public function searchTasks(string $query, array $actor, int $limit = 20): array
    {
        $safeLimit = min(50, max(1, $limit));
        $term = '%' . $query . '%';

        $qb = (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->select([
                't.public_id',
                't.task_key',
                't.title',
                't.status_code',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
            ])
            ->whereNull('t.deleted_at')
            ->whereNull('t.archived_at')
            ->orderBy('t.updated_at', 'DESC')
            ->limit($safeLimit);

        // Support search by task_key, public_id, or title
        $isTaskKeySearch = preg_match('/^[A-Za-z][A-Za-z0-9]{1,9}-[0-9]+$/', $query) === 1;
        $isPublicIdSearch = preg_match('/^[a-z]{3}_/', $query) === 1;

        if ($isTaskKeySearch) {
            $normalizedKey = strtoupper($query);
            $qb->whereRaw('(t.task_key = ? OR t.title LIKE ?)', [$normalizedKey, $term]);
        } elseif ($isPublicIdSearch) {
            $qb->whereRaw('(t.public_id LIKE ? OR t.title LIKE ? OR t.task_key LIKE ?)', [$term, $term, $term]);
        } else {
            $qb->whereRaw('(t.title LIKE ? OR t.task_key LIKE ?)', [$term, $term]);
        }

        // Apply access filter similar to TaskRepository
        $actorUserId = (int)($actor['id'] ?? 0);
        $actorIsRoot = (bool)($actor['is_root'] ?? false);

        if (!$actorIsRoot && $actorUserId > 0) {
            $qb->whereRaw(
                '(t.creator_user_id = ? OR t.assignee_user_id = ? OR p.created_by_user_id = ? OR p.manager_user_id = ?)',
                [$actorUserId, $actorUserId, $actorUserId, $actorUserId]
            );
        }

        return $qb->get();
    }
}
