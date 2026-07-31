<?php
declare(strict_types=1);

namespace Api\Model\Comment;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;

final class MentionRepository
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
                'm.public_id',
                'm.entity_type',
                'm.entity_public_id',
                'm.created_at',
                'u.public_id AS mentioned_user_public_id',
                'u.login AS mentioned_user_login',
                'u.full_name AS mentioned_user_name',
            ])
            ->orderBy('m.created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters, int $actorUserId, bool $actorIsRoot): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('mentions m')
            ->leftJoin('users u', 'u.id', '=', 'm.mentioned_user_id');

        if (!$actorIsRoot) {
            $query->where('m.mentioned_user_id', '=', $actorUserId);
        }

        if (!empty($filters['entity_type'])) {
            $query->where('m.entity_type', '=', (string)$filters['entity_type']);
        }

        if (!empty($filters['entity_public_id'])) {
            $query->where('m.entity_public_id', '=', (string)$filters['entity_public_id']);
        }

        if (!empty($filters['mentioned_user_public_id'])) {
            $query->where('u.public_id', '=', (string)$filters['mentioned_user_public_id']);
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('mentions m')
            ->leftJoin('users u', 'u.id', '=', 'm.mentioned_user_id')
            ->select([
                'm.public_id',
                'm.entity_type',
                'm.entity_public_id',
                'm.created_at',
                'm.mentioned_user_id',
                'u.public_id AS mentioned_user_public_id',
                'u.login AS mentioned_user_login',
                'u.full_name AS mentioned_user_name',
            ])
            ->where('m.public_id', '=', $publicId)
            ->first();
    }

    public function findExisting(string $entityType, string $entityPublicId, int $mentionedUserId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('mentions')
            ->select(['public_id'])
            ->where('entity_type', '=', $entityType)
            ->where('entity_public_id', '=', $entityPublicId)
            ->where('mentioned_user_id', '=', $mentionedUserId)
            ->first();
    }

    public function create(string $entityType, string $entityPublicId, int $mentionedUserId): array
    {
        $existing = $this->findExisting($entityType, $entityPublicId, $mentionedUserId);
        if ($existing) {
            return $this->findByPublicId((string)$existing['public_id']) ?? [];
        }

        $publicId = Ulid::generate('mnt');
        (new QueryBuilder($this->pdo))
            ->from('mentions')
            ->insert([
            'public_id' => $publicId,
            'entity_type' => $entityType,
            'entity_public_id' => $entityPublicId,
            'mentioned_user_id' => $mentionedUserId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return $this->findByPublicId($publicId) ?? [];
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('mentions')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }
}
