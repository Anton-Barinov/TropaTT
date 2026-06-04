<?php
declare(strict_types=1);

namespace Api\Model\Worklog;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class WorklogRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters, int $actorUserId, bool $actorIsRoot): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($filters, $actorUserId, $actorIsRoot)->count();
        $items = $this->buildListQuery($filters, $actorUserId, $actorIsRoot)
            ->select([
                'w.public_id',
                'w.minutes_spent',
                'w.note',
                'w.logged_at',
                'w.created_at',
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_full_name',
                't.public_id AS task_public_id',
                't.title AS task_title',
            ])
            ->orderBy('w.logged_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters, int $actorUserId, bool $actorIsRoot): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('work_logs w')
            ->leftJoin('users u', 'u.id', '=', 'w.user_id')
            ->leftJoin('tasks t', 't.id', '=', 'w.task_id');

        if (!$actorIsRoot) {
            $query->where('w.user_id', '=', $actorUserId);
        }

        if (!empty($filters['user_public_id'])) {
            $query->where('u.public_id', '=', (string)$filters['user_public_id']);
        }

        if (!empty($filters['task_public_id'])) {
            $query->where('t.public_id', '=', (string)$filters['task_public_id']);
        }

        if (!empty($filters['from'])) {
            $query->where('w.logged_at', '>=', (string)$filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('w.logged_at', '<=', (string)$filters['to']);
        }

        return $query;
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('work_logs')
            ->insert($payload);
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('work_logs w')
            ->leftJoin('users u', 'u.id', '=', 'w.user_id')
            ->leftJoin('tasks t', 't.id', '=', 'w.task_id')
            ->select([
                'w.public_id',
                'w.minutes_spent',
                'w.note',
                'w.logged_at',
                'w.created_at',
                'w.user_id',
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_full_name',
                't.public_id AS task_public_id',
                't.title AS task_title',
            ])
            ->where('w.public_id', '=', $publicId)
            ->first();
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('work_logs')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('work_logs')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }
}
