<?php
declare(strict_types=1);

namespace Api\Model\Favorite;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;

final class FavoriteRepository
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
                'f.public_id',
                'f.entity_type',
                'f.entity_public_id',
                'f.created_at',
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_name',
            ])
            ->orderBy('f.created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters, int $actorUserId, bool $actorIsRoot): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('favorites f')
            ->leftJoin('users u', 'u.id', '=', 'f.user_id');

        if (!$actorIsRoot) {
            $query->where('f.user_id', '=', $actorUserId);
        }

        if (!empty($filters['entity_type'])) {
            $query->where('f.entity_type', '=', (string)$filters['entity_type']);
        }

        if (!empty($filters['entity_public_id'])) {
            $query->where('f.entity_public_id', '=', (string)$filters['entity_public_id']);
        }

        if (!empty($filters['user_public_id'])) {
            $query->where('u.public_id', '=', (string)$filters['user_public_id']);
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('favorites f')
            ->leftJoin('users u', 'u.id', '=', 'f.user_id')
            ->select([
                'f.public_id',
                'f.entity_type',
                'f.entity_public_id',
                'f.created_at',
                'f.user_id',
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_name',
            ])
            ->where('f.public_id', '=', $publicId)
            ->first();
    }

    public function findByEntityAndUser(string $entityType, string $entityPublicId, int $userId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('favorites')
            ->select(['public_id'])
            ->where('entity_type', '=', $entityType)
            ->where('entity_public_id', '=', $entityPublicId)
            ->where('user_id', '=', $userId)
            ->first();
    }

    public function create(string $entityType, string $entityPublicId, int $userId): array
    {
        $existing = $this->findByEntityAndUser($entityType, $entityPublicId, $userId);
        if ($existing) {
            return $this->findByPublicId((string)$existing['public_id']) ?? [];
        }

        $publicId = Ulid::generate('fav');
        (new QueryBuilder($this->pdo))
            ->from('favorites')
            ->insert([
            'public_id' => $publicId,
            'user_id' => $userId,
            'entity_type' => $entityType,
            'entity_public_id' => $entityPublicId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return $this->findByPublicId($publicId) ?? [];
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('favorites')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }
}
