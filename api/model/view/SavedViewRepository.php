<?php
declare(strict_types=1);

namespace Api\Model\View;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;

final class SavedViewRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters, int $actorUserId, bool $actorIsRoot): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(200, max(1, (int)($filters['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($filters, $actorUserId, $actorIsRoot)->count();
        $items = $this->buildListQuery($filters, $actorUserId, $actorIsRoot)
            ->select([
                'v.public_id',
                'v.entity_type',
                'v.title',
                'v.filters',
                'v.created_at',
                'v.updated_at',
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_name',
            ])
            ->orderBy('v.updated_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters, int $actorUserId, bool $actorIsRoot): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('saved_views v')
            ->leftJoin('users u', 'u.id', '=', 'v.user_id');

        if (!$actorIsRoot) {
            $query->where('v.user_id', '=', $actorUserId);
        }

        if (!empty($filters['entity_type'])) {
            $query->where('v.entity_type', '=', (string)$filters['entity_type']);
        }

        if (!empty($filters['search'])) {
            $query->where('v.title', 'LIKE', '%' . (string)$filters['search'] . '%');
        }

        if (!empty($filters['user_public_id'])) {
            $query->where('u.public_id', '=', (string)$filters['user_public_id']);
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('saved_views v')
            ->leftJoin('users u', 'u.id', '=', 'v.user_id')
            ->select([
                'v.public_id',
                'v.entity_type',
                'v.title',
                'v.filters',
                'v.created_at',
                'v.updated_at',
                'v.user_id',
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_name',
            ])
            ->where('v.public_id', '=', $publicId)
            ->first();
    }

    public function create(int $userId, string $entityType, string $title, string $filtersJson): array
    {
        $publicId = Ulid::generate('viw');
        $now = gmdate('Y-m-d H:i:s');

        (new QueryBuilder($this->pdo))
            ->from('saved_views')
            ->insert([
            'public_id' => $publicId,
            'user_id' => $userId,
            'entity_type' => $entityType,
            'title' => $title,
            'filters' => $filtersJson,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findByPublicId($publicId) ?? [];
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        $set['updated_at'] = gmdate('Y-m-d H:i:s');

        return (new QueryBuilder($this->pdo))
            ->from('saved_views')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('saved_views')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }
}
