<?php
declare(strict_types=1);

namespace Api\Model\Company;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class CompanyRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters): array
    {
        $creatorIds = is_array($filters['created_by_user_ids'] ?? null)
            ? array_values(array_filter(array_map('intval', $filters['created_by_user_ids']), static fn(int $id): bool => $id > 0))
            : [];

        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($filters, $creatorIds)->count();
        $items = $this->buildListQuery($filters, $creatorIds)
            ->select(['public_id', 'title', 'created_by_user_id', 'created_at', 'updated_at'])
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    /** @param array<int,int> $creatorIds */
    private function buildListQuery(array $filters, array $creatorIds): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('companies');

        if (!empty($filters['search'])) {
            $query->where('title', 'LIKE', '%' . (string)$filters['search'] . '%');
        }

        if ($creatorIds !== []) {
            if (!empty($filters['include_unowned'])) {
                $placeholders = implode(',', array_fill(0, count($creatorIds), '?'));
                $query->whereRaw('(created_by_user_id IS NULL OR created_by_user_id IN (' . $placeholders . '))', $creatorIds);
            } else {
                $query->whereIn('created_by_user_id', $creatorIds);
            }
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('companies')
            ->where('public_id', '=', $publicId)
            ->first();
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('companies')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('companies')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('companies')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }
}
