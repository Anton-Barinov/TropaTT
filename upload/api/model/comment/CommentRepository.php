<?php
declare(strict_types=1);

namespace Api\Model\Comment;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class CommentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listByTaskPublicId(string $taskPublicId, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListByTaskQuery($taskPublicId)->count();
        $items = $this->buildListByTaskQuery($taskPublicId)
            ->select([
                'c.public_id',
                'c.body',
                'c.visibility',
                'c.created_at',
                'c.updated_at',
                'u.public_id AS author_public_id',
                'u.full_name AS author_name',
            ])
            ->orderBy('c.created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListByTaskQuery(string $taskPublicId): QueryBuilder
    {
        return (new QueryBuilder($this->pdo))
            ->from('comments c')
            ->join('tasks t', 't.id', '=', 'c.task_id')
            ->leftJoin('users u', 'u.id', '=', 'c.author_user_id')
            ->where('t.public_id', '=', $taskPublicId)
            ->whereNull('c.deleted_at');
    }

    public function createByTaskPublicId(string $taskPublicId, array $payload): bool
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->select(['id', 'project_id'])
            ->where('public_id', '=', $taskPublicId)
            ->first();
        if (!$row) {
            return false;
        }

        $payload['task_id'] = (int)$row['id'];
        $payload['project_id'] = $row['project_id'] !== null ? (int)$row['project_id'] : null;

        (new QueryBuilder($this->pdo))
            ->from('comments')
            ->insert($payload);

        return true;
    }

    /** @return int[] */
    public function participantUserIdsByTaskPublicId(string $taskPublicId): array
    {
        $rows = (new QueryBuilder($this->pdo))
            ->from('comments c')
            ->join('tasks t', 't.id', '=', 'c.task_id')
            ->select(['c.author_user_id'])
            ->where('t.public_id', '=', $taskPublicId)
            ->whereNull('c.deleted_at')
            ->get();

        return array_values(array_unique(array_filter(
            array_map(static fn(array $row): int => (int)($row['author_user_id'] ?? 0), $rows),
            static fn(int $userId): bool => $userId > 0
        )));
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('comments c')
            ->leftJoin('tasks t', 't.id', '=', 'c.task_id')
            ->leftJoin('projects p', 'p.id', '=', 'c.project_id')
            ->leftJoin('users u', 'u.id', '=', 'c.author_user_id')
            ->select([
                'c.public_id',
                'c.task_id',
                'c.project_id',
                'c.author_user_id',
                'c.body',
                'c.visibility',
                'c.created_at',
                'c.updated_at',
                'c.deleted_at',
                't.public_id AS task_public_id',
                'p.public_id AS project_public_id',
                'u.public_id AS author_public_id',
                'u.full_name AS author_name',
            ])
            ->where('c.public_id', '=', $publicId)
            ->first();
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('comments')
            ->where('public_id', '=', $publicId)
            ->whereNull('deleted_at')
            ->update($set) > 0;
    }

    public function softDelete(string $publicId, string $deletedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('comments')
            ->where('public_id', '=', $publicId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]) > 0;
    }
}
