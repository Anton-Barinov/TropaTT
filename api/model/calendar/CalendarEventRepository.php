<?php
declare(strict_types=1);

namespace Api\Model\Calendar;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class CalendarEventRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listByUser(int $userId, bool $isRoot, array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListByUserQuery($userId, $isRoot, $filters)->count();
        $items = $this->buildListByUserQuery($userId, $isRoot, $filters)
            ->select([
                'e.public_id',
                'e.owner_user_id',
                'e.title',
                'e.description',
                'e.starts_at',
                'e.ends_at',
                'e.created_at',
                'e.updated_at',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
                't.public_id AS task_public_id',
                't.title AS task_title',
            ])
            ->orderBy('e.starts_at', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListByUserQuery(int $userId, bool $isRoot, array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('calendar_events e')
            ->leftJoin('projects p', 'p.id', '=', 'e.project_id')
            ->leftJoin('tasks t', 't.id', '=', 'e.task_id');

        if (!$isRoot) {
            $query->where('e.owner_user_id', '=', $userId);
        }

        if (!empty($filters['from'])) {
            $query->where('e.starts_at', '>=', (string)$filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('e.ends_at', '<=', (string)$filters['to']);
        }

        return $query;
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('calendar_events')
            ->insert($payload);
    }

    public function findByPublicId(string $publicId, int $userId, bool $isRoot): ?array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('calendar_events e')
            ->leftJoin('projects p', 'p.id', '=', 'e.project_id')
            ->leftJoin('tasks t', 't.id', '=', 'e.task_id')
            ->select([
                'e.public_id',
                'e.owner_user_id',
                'e.title',
                'e.description',
                'e.starts_at',
                'e.ends_at',
                'e.created_at',
                'e.updated_at',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
                't.public_id AS task_public_id',
                't.title AS task_title',
            ])
            ->where('e.public_id', '=', $publicId);

        if (!$isRoot) {
            $query->where('e.owner_user_id', '=', $userId);
        }

        return $query->first();
    }

    public function updateByPublicId(string $publicId, int $userId, bool $isRoot, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        $query = (new QueryBuilder($this->pdo))
            ->from('calendar_events')
            ->where('public_id', '=', $publicId);

        if (!$isRoot) {
            $query->where('owner_user_id', '=', $userId);
        }

        return $query->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId, int $userId, bool $isRoot): bool
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('calendar_events')
            ->where('public_id', '=', $publicId);

        if (!$isRoot) {
            $query->where('owner_user_id', '=', $userId);
        }

        return $query->delete() > 0;
    }

    public function listInRange(int $userId, bool $isRoot, string $startAt, string $endAt): array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('calendar_events e')
            ->leftJoin('projects p', 'p.id', '=', 'e.project_id')
            ->leftJoin('tasks t', 't.id', '=', 'e.task_id')
            ->select([
                'e.public_id',
                'e.owner_user_id',
                'e.title',
                'e.description',
                'e.starts_at',
                'e.ends_at',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
                't.public_id AS task_public_id',
                't.title AS task_title',
            ])
            ->where('e.starts_at', '<=', $endAt)
            ->where('e.ends_at', '>=', $startAt);

        if (!$isRoot) {
            $query->where('e.owner_user_id', '=', $userId);
        }

        return $query
            ->orderBy('e.starts_at', 'ASC')
            ->get();
    }

    public function listTasksDueInRange(int $userId, bool $isRoot, string $startAt, string $endAt): array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->select([
                't.public_id',
                't.title',
                't.status_code',
                't.priority_code',
                't.due_at',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
            ])
            ->whereNull('t.deleted_at')
            ->whereNull('t.archived_at')
            ->whereNotNull('t.due_at')
            ->where('t.due_at', '>=', $startAt)
            ->where('t.due_at', '<=', $endAt);

        if (!$isRoot) {
            $query->whereRaw(
                '(t.creator_user_id = ? OR t.assignee_user_id = ? OR p.created_by_user_id = ? OR p.manager_user_id = ?)',
                [$userId, $userId, $userId, $userId]
            );
        }

        return $query
            ->orderBy('t.due_at', 'ASC')
            ->get();
    }
}
