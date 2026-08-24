<?php
declare(strict_types=1);

namespace Api\Model\Department;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;
use Api\System\Library\Support\LikeEscaper;

final class DepartmentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters, ?int $actorUserId = null, bool $actorIsRoot = false): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($filters, $actorUserId, $actorIsRoot)->count();
        $items = $this->buildListQuery($filters, $actorUserId, $actorIsRoot)
            ->select(['public_id', 'title', 'manager_user_id', 'created_at', 'updated_at'])
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters, ?int $actorUserId, bool $actorIsRoot): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('departments');

        if (!empty($filters['search'])) {
            $query->where('title', 'LIKE', '%' . (string)$filters['search'] . '%');
        }

        if (!$actorIsRoot && $actorUserId !== null && $actorUserId > 0) {
            $query->where('manager_user_id', '=', $actorUserId);
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('departments')
            ->where('public_id', '=', $publicId)
            ->first();
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('departments')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('departments')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('departments')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }
}
