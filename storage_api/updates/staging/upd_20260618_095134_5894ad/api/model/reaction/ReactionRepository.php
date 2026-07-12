<?php
declare(strict_types=1);

namespace Api\Model\Reaction;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;

final class ReactionRepository
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
                'r.public_id',
                'r.entity_type',
                'r.entity_public_id',
                'r.reaction',
                'r.created_at',
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_name',
            ])
            ->orderBy('r.created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters, int $actorUserId, bool $actorIsRoot): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('reactions r')
            ->leftJoin('users u', 'u.id', '=', 'r.user_id');

        if (!$actorIsRoot) {
            $query->where('r.user_id', '=', $actorUserId);
        }

        if (!empty($filters['entity_type'])) {
            $query->where('r.entity_type', '=', (string)$filters['entity_type']);
        }

        if (!empty($filters['entity_public_id'])) {
            $query->where('r.entity_public_id', '=', (string)$filters['entity_public_id']);
        }

        if (!empty($filters['reaction'])) {
            $query->where('r.reaction', '=', (string)$filters['reaction']);
        }

        if (!empty($filters['user_public_id'])) {
            $query->where('u.public_id', '=', (string)$filters['user_public_id']);
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('reactions r')
            ->leftJoin('users u', 'u.id', '=', 'r.user_id')
            ->select([
                'r.public_id',
                'r.entity_type',
                'r.entity_public_id',
                'r.reaction',
                'r.created_at',
                'r.user_id',
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_name',
            ])
            ->where('r.public_id', '=', $publicId)
            ->first();
    }

    public function findByEntityAndUser(string $entityType, string $entityPublicId, int $userId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('reactions')
            ->select(['public_id'])
            ->where('entity_type', '=', $entityType)
            ->where('entity_public_id', '=', $entityPublicId)
            ->where('user_id', '=', $userId)
            ->first();
    }

    public function upsert(string $entityType, string $entityPublicId, int $userId, string $reaction): array
    {
        $existing = $this->findByEntityAndUser($entityType, $entityPublicId, $userId);
        if ($existing) {
            (new QueryBuilder($this->pdo))
                ->from('reactions')
                ->where('public_id', '=', (string)$existing['public_id'])
                ->update(['reaction' => $reaction]);

            return $this->findByPublicId((string)$existing['public_id']) ?? [];
        }

        $publicId = Ulid::generate('rct');
        (new QueryBuilder($this->pdo))
            ->from('reactions')
            ->insert([
            'public_id' => $publicId,
            'entity_type' => $entityType,
            'entity_public_id' => $entityPublicId,
            'user_id' => $userId,
            'reaction' => $reaction,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return $this->findByPublicId($publicId) ?? [];
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('reactions')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }
}
