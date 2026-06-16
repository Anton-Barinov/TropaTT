<?php
declare(strict_types=1);

namespace Api\Model\View;

use Api\System\Library\Database\Builder\Expression;
use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;

final class SavedViewRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * List saved views with visibility rules (v2).
     *
     * @return array{items:array,meta:array}
     */
    public function list(array $filters, int $actorUserId, bool $actorIsRoot): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $sort = in_array(($filters['sort'] ?? ''), ['sort_order', 'title', 'created_at', 'updated_at', 'last_used_at'], true) ? (string)$filters['sort'] : 'sort_order';
        $order = strtoupper((string)($filters['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $qb = $this->buildListQuery($filters, $actorUserId, $actorIsRoot);

        $total = $qb->count();

        $items = $qb
            ->select([
                'v.public_id',
                'v.entity_type',
                'v.title',
                'v.description',
                'v.filters',
                'v.access_level',
                'v.display_filters',
                'v.display_properties',
                'v.rich_filters',
                'v.layout',
                'v.group_by',
                'v.order_by',
                'v.order_dir',
                'v.is_locked',
                'v.is_system',
                'v.sort_order',
                'v.archived_at',
                'v.row_version',
                'v.created_at',
                'v.updated_at',
                'v.user_id',
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_name',
                'pref.is_pinned',
                'pref.sort_order AS user_sort_order',
                'pref.last_used_at',
            ])
            ->orderBy('pref.is_pinned', 'DESC')
            ->orderBy('v.is_system', 'DESC')
            ->orderBy('v.' . $sort, $order)
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    private function buildListQuery(array $filters, int $actorUserId, bool $actorIsRoot): QueryBuilder
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('saved_views v')
            ->leftJoin('users u', 'u.id', '=', 'v.user_id')
            ->leftJoin('saved_view_user_preferences pref', function (QueryBuilder $join) use ($actorUserId): void {
                $join->on('pref.saved_view_id', '=', 'v.id')
                     ->where('pref.user_id', '=', $actorUserId);
            });

        // Visibility: private views of owner, public views, system views; root sees all
        if (!$actorIsRoot) {
            $qb->whereRaw(
                '(v.user_id = ? OR v.access_level = ? OR v.access_level = ?)',
                [$actorUserId, 'public', 'system']
            );
        }

        // Filter by entity_type (default 'task')
        $entityType = (string)($filters['entity_type'] ?? 'task');
        $qb->where('v.entity_type', '=', $entityType);

        // Filter by access_level
        if (!empty($filters['access_level'])) {
            $qb->where('v.access_level', '=', (string)$filters['access_level']);
        }

        // Filter archived
        if (($filters['archived'] ?? '0') !== '1') {
            $qb->whereNull('v.archived_at');
        } else {
            $qb->whereNotNull('v.archived_at');
        }

        // Filter by system
        if (!empty($filters['system'])) {
            $qb->where('v.is_system', '=', (int)$filters['system']);
        }

        // Filter pinned
        if (!empty($filters['pinned'])) {
            $qb->where('pref.is_pinned', '=', 1);
        }

        // Search by title
        if (!empty($filters['q'])) {
            $qb->where('v.title', 'LIKE', '%' . (string)$filters['q'] . '%');
        }

        if (!$actorIsRoot && !empty($filters['user_public_id'])) {
            $qb->where('u.public_id', '=', (string)$filters['user_public_id']);
        }

        return $qb;
    }

    /**
     * Find a saved view by public_id with all fields.
     */
    public function findByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('saved_views v')
            ->leftJoin('users u', 'u.id', '=', 'v.user_id')
            ->select([
                'v.*',
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_name',
            ])
            ->where('v.public_id', '=', $publicId)
            ->first();

        return $row !== null ? $row : null;
    }

    /**
     * Create a new saved view (v2).
     */
    public function create(array $payload): array
    {
        $publicId = Ulid::generate('viw');
        $now = gmdate('Y-m-d H:i:s');

        (new QueryBuilder($this->pdo))
            ->from('saved_views')
            ->insert([
                'public_id' => $publicId,
                'user_id' => (int)($payload['user_id'] ?? 0),
                'entity_type' => (string)($payload['entity_type'] ?? 'task'),
                'title' => (string)($payload['title'] ?? ''),
                'description' => $payload['description'] ?? null,
                'filters' => $payload['filters'] ?? '{}',
                'access_level' => (string)($payload['access_level'] ?? 'private'),
                'display_filters' => $payload['display_filters'] ?? null,
                'display_properties' => $payload['display_properties'] ?? null,
                'rich_filters' => $payload['rich_filters'] ?? null,
                'layout' => (string)($payload['layout'] ?? 'list'),
                'group_by' => $payload['group_by'] ?? null,
                'order_by' => $payload['order_by'] ?? null,
                'order_dir' => $payload['order_dir'] ?? null,
                'is_locked' => (int)($payload['is_locked'] ?? 0),
                'is_system' => (int)($payload['is_system'] ?? 0),
                'sort_order' => (int)($payload['sort_order'] ?? 65535),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        return $this->findByPublicId($publicId) ?? [];
    }

    /**
     * Update a saved view by public_id (v2).
     */
    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        $set['row_version'] = new Expression('row_version + 1');

        return (new QueryBuilder($this->pdo))
            ->from('saved_views')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    /**
     * Archive a saved view (sets archived_at instead of deleting).
     */
    public function archiveByPublicId(string $publicId, string $archivedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('saved_views')
            ->where('public_id', '=', $publicId)
            ->update([
                'archived_at' => $archivedAt,
                'updated_at' => $archivedAt,
                'row_version' => new Expression('row_version + 1'),
            ]) > 0;
    }

    /**
     * Physically delete a saved view (kept for backward compatibility).
     */
    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('saved_views')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    /**
     * Create or update a user preference for a saved view.
     */
    public function createOrUpdateUserPreference(int $savedViewId, int $userId, array $set): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $existing = $this->findUserPreference($savedViewId, $userId);

        if ($existing !== null) {
            $updateSet = [];
            if (array_key_exists('is_pinned', $set)) {
                $updateSet['is_pinned'] = (int)($set['is_pinned'] ?? 0);
            }
            if (array_key_exists('sort_order', $set)) {
                $updateSet['sort_order'] = (int)($set['sort_order'] ?? 65535);
            }
            if (array_key_exists('last_used_at', $set)) {
                $updateSet['last_used_at'] = $set['last_used_at'];
            }
            $updateSet['updated_at'] = $now;

            if ($updateSet !== []) {
                (new QueryBuilder($this->pdo))
                    ->from('saved_view_user_preferences')
                    ->where('saved_view_id', '=', $savedViewId)
                    ->where('user_id', '=', $userId)
                    ->update($updateSet);
            }

            return $this->findUserPreference($savedViewId, $userId) ?? [];
        }

        $publicId = Ulid::generate('svp');

        (new QueryBuilder($this->pdo))
            ->from('saved_view_user_preferences')
            ->insert([
                'public_id' => $publicId,
                'saved_view_id' => $savedViewId,
                'user_id' => $userId,
                'is_pinned' => (int)($set['is_pinned'] ?? 0),
                'sort_order' => (int)($set['sort_order'] ?? 65535),
                'last_used_at' => $set['last_used_at'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        return $this->findUserPreference($savedViewId, $userId) ?? [];
    }

    /**
     * Find user preference for a saved view.
     */
    public function findUserPreference(int $savedViewId, int $userId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('saved_view_user_preferences')
            ->select(['*'])
            ->where('saved_view_id', '=', $savedViewId)
            ->where('user_id', '=', $userId)
            ->first();

        return $row !== null ? $row : null;
    }

    /**
     * Touch last_used_at for a user preference.
     */
    public function touchLastUsed(int $savedViewId, int $userId, string $now): void
    {
        $this->createOrUpdateUserPreference($savedViewId, $userId, [
            'last_used_at' => $now,
        ]);
    }

    /**
     * Check if a title already exists for a user and entity_type.
     */
    public function titleExistsForUser(string $title, string $entityType, int $userId, ?string $exceptPublicId = null): bool
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('saved_views')
            ->select(['id'])
            ->where('title', '=', $title)
            ->where('entity_type', '=', $entityType)
            ->where('user_id', '=', $userId)
            ->whereNull('archived_at');

        if ($exceptPublicId !== null && $exceptPublicId !== '') {
            $qb->where('public_id', '!=', $exceptPublicId);
        }

        $row = $qb->first();
        return $row !== null;
    }
}
