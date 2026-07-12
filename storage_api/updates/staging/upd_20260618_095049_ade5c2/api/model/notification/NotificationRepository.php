<?php
declare(strict_types=1);

namespace Api\Model\Notification;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class NotificationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listByUser(int $userId, array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $countQuery = $this->buildListQuery($userId, $filters);
        $total = $countQuery->count();

        $items = $this->buildListQuery($userId, $filters)
            ->select([
                'n.public_id',
                'n.category',
                'n.title',
                'n.body',
                'n.entity_type',
                'n.entity_public_id',
                'n.action_code',
                'n.actor_user_id',
                'n.actor_public_id',
                'n.actor_name',
                'n.link',
                'n.payload_json',
                'n.is_read',
                'n.created_at',
                'n.read_at',
            ])
            ->orderBy('n.created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    /** @return array<int,array<string,mixed>> */
    public function listForUserAfterId(int $userId, int $afterId, int $limit = 50): array
    {
        $safeLimit = min(200, max(1, $limit));

        return (new QueryBuilder($this->pdo))
            ->from('notifications n')
            ->select([
                'n.id',
                'n.public_id',
                'n.category',
                'n.title',
                'n.body',
                'n.entity_type',
                'n.entity_public_id',
                'n.action_code',
                'n.actor_user_id',
                'n.actor_public_id',
                'n.actor_name',
                'n.link',
                'n.payload_json',
                'n.is_read',
                'n.created_at',
                'n.read_at',
            ])
            ->where('n.user_id', '=', $userId)
            ->where('n.id', '>', $afterId)
            ->orderBy('n.id', 'ASC')
            ->limit($safeLimit)
            ->get();
    }

    public function latestInternalIdByUser(int $userId): int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->select(['id'])
            ->where('user_id', '=', $userId)
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->first();

        return $row ? (int)($row['id'] ?? 0) : 0;
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->insert($payload);
    }

    public function findByPublicIdForUser(string $publicId, int $userId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->select([
                'public_id',
                'category',
                'title',
                'body',
                'entity_type',
                'entity_public_id',
                'action_code',
                'actor_user_id',
                'actor_public_id',
                'actor_name',
                'link',
                'payload_json',
                'is_read',
                'created_at',
                'read_at',
            ])
            ->where('public_id', '=', $publicId)
            ->where('user_id', '=', $userId)
            ->first();
    }

    public function markRead(string $publicId, int $userId, string $readAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->where('public_id', '=', $publicId)
            ->where('user_id', '=', $userId)
            ->where('is_read', '=', 0)
            ->update([
                'is_read' => 1,
                'read_at' => $readAt,
            ]) > 0;
    }

    public function markUnread(string $publicId, int $userId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->where('public_id', '=', $publicId)
            ->where('user_id', '=', $userId)
            ->where('is_read', '=', 1)
            ->update([
                'is_read' => 0,
                'read_at' => null,
            ]) > 0;
    }

    public function markAllRead(int $userId, ?string $category, string $readAt): int
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->where('user_id', '=', $userId)
            ->where('is_read', '=', 0);

        if ($category !== null && $category !== '') {
            $query->where('category', '=', $category);
        }

        return $query->update([
            'is_read' => 1,
            'read_at' => $readAt,
        ]);
    }

    public function countersByUser(int $userId): array
    {
        $total = (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->where('user_id', '=', $userId)
            ->count();
        $unread = (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->where('user_id', '=', $userId)
            ->where('is_read', '=', 0)
            ->count();

        $rows = (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->select(['category', 'COUNT(*) AS unread_count'])
            ->where('user_id', '=', $userId)
            ->where('is_read', '=', 0)
            ->groupBy('category')
            ->get();

        $byCategory = [];
        foreach ($rows as $row) {
            $key = (string)($row['category'] ?? 'system');
            $byCategory[$key] = (int)($row['unread_count'] ?? 0);
        }

        return [
            'total' => $total,
            'unread' => $unread,
            'by_category' => $byCategory,
        ];
    }

    public function stateHashByUser(int $userId): string
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->select([
                'MAX(created_at) AS max_created_at',
                'MAX(read_at) AS max_read_at',
                'COUNT(*) AS total_count',
                'SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread_count',
            ])
            ->where('user_id', '=', $userId)
            ->first();

        if (!$row) {
            return 'empty:0:0';
        }

        $maxCreatedAt = (string)($row['max_created_at'] ?? '');
        $maxReadAt = (string)($row['max_read_at'] ?? '');
        $total = (int)($row['total_count'] ?? 0);
        $unread = (int)($row['unread_count'] ?? 0);

        return sha1($maxCreatedAt . '|' . $maxReadAt . '|' . $total . '|' . $unread);
    }

    public function hasActionForUserEntitySince(
        int $userId,
        string $actionCode,
        string $entityType,
        string $entityPublicId,
        string $since
    ): bool {
        if ($userId <= 0 || $actionCode === '' || $entityType === '' || $entityPublicId === '') {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->where('user_id', '=', $userId)
            ->where('action_code', '=', $actionCode)
            ->where('entity_type', '=', $entityType)
            ->where('entity_public_id', '=', $entityPublicId)
            ->where('created_at', '>=', $since)
            ->count() > 0;
    }

    private function buildListQuery(int $userId, array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('notifications n')
            ->where('n.user_id', '=', $userId);

        if (array_key_exists('is_read', $filters) && $filters['is_read'] !== '' && $filters['is_read'] !== null) {
            $isRead = ((string)$filters['is_read'] === '1' || (string)$filters['is_read'] === 'true') ? 1 : 0;
            $query->where('n.is_read', '=', $isRead);
        }

        if (!empty($filters['category'])) {
            $query->where('n.category', '=', trim((string)$filters['category']));
        }

        return $query;
    }
}
