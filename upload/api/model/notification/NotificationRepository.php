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

    public function listByUser(int $userId, array $filters, ?int $organizationId = null): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $countQuery = $this->buildListQuery($userId, $filters, $organizationId);
        $total = $countQuery->count();

        $items = $this->buildListQuery($userId, $filters, $organizationId)
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
    public function listForUserAfterId(int $userId, int $afterId, int $limit = 50, ?int $organizationId = null): array
    {
        $safeLimit = min(200, max(1, $limit));

        $query = (new QueryBuilder($this->pdo))
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
            ->where('n.id', '>', $afterId);
        if ($organizationId !== null && $organizationId > 0) $query->where('n.organization_id', '=', $organizationId);
        return $query->orderBy('n.id', 'ASC')->limit($safeLimit)->get();
    }

    public function latestInternalIdByUser(int $userId, ?int $organizationId = null): int
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->select(['id'])
            ->where('user_id', '=', $userId);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);
        $row = $query->orderBy('id', 'DESC')->limit(1)->first();

        return $row ? (int)($row['id'] ?? 0) : 0;
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->insert($payload);
    }

    public function findByPublicIdForUser(string $publicId, int $userId, ?int $organizationId = null): ?array
    {
        $query = (new QueryBuilder($this->pdo))
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
            ->where('user_id', '=', $userId);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);
        return $query->first();
    }

    public function markRead(string $publicId, int $userId, string $readAt, ?int $organizationId = null): bool
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->where('public_id', '=', $publicId)
            ->where('user_id', '=', $userId)
            ->where('is_read', '=', 0);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);
        return $query->update([
                'is_read' => 1,
                'read_at' => $readAt,
            ]) > 0;
    }

    public function markUnread(string $publicId, int $userId, ?int $organizationId = null): bool
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->where('public_id', '=', $publicId)
            ->where('user_id', '=', $userId)
            ->where('is_read', '=', 1);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);
        return $query->update([
                'is_read' => 0,
                'read_at' => null,
            ]) > 0;
    }

    public function markAllRead(int $userId, ?string $category, string $readAt, ?int $organizationId = null): int
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->where('user_id', '=', $userId)
            ->where('is_read', '=', 0);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);

        if ($category !== null && $category !== '') {
            $query->where('category', '=', $category);
        }

        return $query->update([
            'is_read' => 1,
            'read_at' => $readAt,
        ]);
    }

    public function countersByUser(int $userId, ?int $organizationId = null): array
    {
        $totalQuery = (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->where('user_id', '=', $userId);
        $unreadQuery = (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->where('user_id', '=', $userId)
            ->where('is_read', '=', 0);
        if ($organizationId !== null && $organizationId > 0) { $totalQuery->where('organization_id', '=', $organizationId); $unreadQuery->where('organization_id', '=', $organizationId); }
        $total = $totalQuery->count();
        $unread = $unreadQuery->count();

        $rowsQuery = (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->select(['category', 'COUNT(*) AS unread_count'])
            ->where('user_id', '=', $userId)
            ->where('is_read', '=', 0);
        if ($organizationId !== null && $organizationId > 0) $rowsQuery->where('organization_id', '=', $organizationId);
        $rows = $rowsQuery->groupBy('category')->get();

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

    public function stateHashByUser(int $userId, ?int $organizationId = null): string
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->select([
                'MAX(created_at) AS max_created_at',
                'MAX(read_at) AS max_read_at',
                'COUNT(*) AS total_count',
                'SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread_count',
            ])
            ->where('user_id', '=', $userId);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);
        $row = $query->first();

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

    private function buildListQuery(int $userId, array $filters, ?int $organizationId = null): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('notifications n')
            ->where('n.user_id', '=', $userId);
        if ($organizationId !== null && $organizationId > 0) $query->where('n.organization_id', '=', $organizationId);

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
