<?php
declare(strict_types=1);

namespace Api\Model\Priority;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class PriorityRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($filters)->count();
        $items = $this->buildListQuery($filters)
            ->select(['public_id', 'code', 'title', 'weight', 'color', 'created_at', 'updated_at'])
            ->orderBy('weight', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('priorities');

        if (!empty($filters['search'])) {
            $search = '%' . (string)$filters['search'] . '%';
            $query->whereRaw('(code LIKE ? OR title LIKE ?)', [$search, $search]);
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('priorities')
            ->where('public_id', '=', $publicId)
            ->first();
    }

    public function findByCode(string $code): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('priorities')
            ->where('code', '=', $code)
            ->first();
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('priorities')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('priorities')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('priorities')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }
}
