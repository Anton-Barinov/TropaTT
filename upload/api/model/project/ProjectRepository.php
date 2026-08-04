<?php
declare(strict_types=1);

namespace Api\Model\Project;

use Api\System\Library\Database\Builder\Expression;
use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Sync\CursorCodec;
use PDO;

final class ProjectRepository
{
    private CursorCodec $cursorCodec;

    public function __construct(private readonly PDO $pdo)
    {
        $this->cursorCodec = new CursorCodec();
    }

    public function list(array $filters, ?int $actorUserId = null, bool $actorIsRoot = false): array
    {
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $sort = in_array(($filters['sort'] ?? ''), ['title', 'created_at', 'updated_at'], true) ? (string)$filters['sort'] : 'updated_at';
        $order = strtoupper((string)($filters['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $paginationMode = (($filters['pagination_mode'] ?? '') === 'cursor' || !empty($filters['cursor'])) ? 'cursor' : 'offset';

        $listBuilder = $this->buildListQuery($filters, $actorUserId, $actorIsRoot, $order);
        $listBuilder = $listBuilder
            ->select([
                'p.public_id',
                'p.title',
                'p.description',
                'p.status_code',
                'p.priority_code',
                'p.client_public_id',
                'p.task_key_prefix',
                'p.task_key_prefix_locked',
                'p.manager_user_id',
                'p.team_public_id',
                'p.archived_at',
                'p.created_at',
                'p.updated_at',
                'p.row_version',
                'mu.public_id AS manager_user_public_id',
                'mu.full_name AS manager_user_name',
                't.title AS team_title',
            ])
            ->orderBy('p.' . $sort, $order)
            ->orderBy('p.public_id', $order);

        if ($paginationMode === 'cursor') {
            $items = $listBuilder
                ->limit($limit + 1)
                ->get();

            $hasMore = count($items) > $limit;
            if ($hasMore) {
                array_pop($items);
            }

            $nextCursor = null;
            if ($hasMore && $items !== []) {
                $last = end($items);
                if (is_array($last)) {
                    $nextCursor = $this->cursorCodec->encode([
                        'updated_at' => (string)($last['updated_at'] ?? ''),
                        'public_id' => (string)($last['public_id'] ?? ''),
                        'order' => $order,
                    ]);
                }
            }

            return [
                'items' => $items,
                'total' => null,
                'page' => 1,
                'limit' => $limit,
                'mode' => 'cursor',
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
            ];
        }

        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $items = $listBuilder
            ->limit($limit)
            ->offset($offset)
            ->get();

        $countBuilder = $this->buildListQuery($filters, $actorUserId, $actorIsRoot, $order)
            ->select(['p.id']);
        $total = $countBuilder->count();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'mode' => 'offset',
            'has_more' => false,
            'next_cursor' => null,
        ];
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('projects')
            ->insert($payload);
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('projects p')
            ->leftJoin('users mu', 'mu.id', '=', 'p.manager_user_id')
            ->leftJoin('users cu', 'cu.id', '=', 'p.created_by_user_id')
            ->leftJoin('teams t', 't.public_id', '=', 'p.team_public_id')
            ->select([
                'p.*',
                'p.task_key_prefix',
                'p.task_key_prefix_locked',
                'mu.public_id AS manager_user_public_id',
                'mu.full_name AS manager_user_name',
                'cu.public_id AS creator_user_public_id',
                'cu.full_name AS creator_user_name',
                't.title AS team_title',
                't.manager_user_id AS team_manager_user_id',
                't.member_user_ids AS team_member_user_ids',
            ])
            ->where('p.public_id', '=', $publicId)
            ->first();
    }

    public function updateByPublicId(string $publicId, array $set, ?int $expectedRowVersion = null): bool
    {
        if ($set === []) {
            return false;
        }

        $set['row_version'] = new Expression('row_version + 1');

        $qb = (new QueryBuilder($this->pdo))
            ->from('projects')
            ->where('public_id', '=', $publicId);

        if ($expectedRowVersion !== null) {
            $qb->where('row_version', '=', $expectedRowVersion);
        }

        return $qb->update($set) > 0;
    }

    public function archiveByPublicId(string $publicId, string $archivedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('projects')
            ->where('public_id', '=', $publicId)
            ->whereNull('archived_at')
            ->update([
                'archived_at' => $archivedAt,
                'updated_at' => $archivedAt,
                'row_version' => new Expression('row_version + 1'),
            ]) > 0;
    }

    public function findByTaskKeyPrefix(string $prefix): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('projects')
            ->select(['public_id', 'id', 'title', 'task_key_prefix'])
            ->where('task_key_prefix', '=', $prefix)
            ->first();

        return $row !== null ? $row : null;
    }

    public function taskKeyPrefixExists(string $prefix, ?string $exceptPublicId = null): bool
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('projects')
            ->select(['id'])
            ->where('task_key_prefix', '=', $prefix);

        if ($exceptPublicId !== null && $exceptPublicId !== '') {
            $qb->where('public_id', '!=', $exceptPublicId);
        }

        $row = $qb->first();

        return $row !== null;
    }

    public function projectIdByPublicId(string $projectPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('projects')
            ->select(['id'])
            ->where('public_id', '=', $projectPublicId)
            ->first();
        $id = $row['id'] ?? false;

        return $id !== false ? (int)$id : null;
    }

    public function taskKeyPrefixById(int $projectId): ?string
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('projects')
            ->select(['task_key_prefix'])
            ->where('id', '=', $projectId)
            ->first();
        $prefix = $row['task_key_prefix'] ?? false;

        return $prefix !== false ? (string)$prefix : null;
    }

    /**
     * Количество открытых задач проекта (для защиты от преждевременного закрытия проекта, ТЗ 7.3).
     *
     * @param string[] $closedStatuses
     */
    public function countOpenTasksByProjectId(int $projectId, array $closedStatuses = []): int
    {
        $closed = $closedStatuses !== [] ? $closedStatuses : ['done', 'completed', 'archived', 'cancelled', 'canceled'];
        $placeholders = implode(',', array_fill(0, count($closed), '?'));
        $params = array_merge([$projectId], array_values($closed));
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM tasks'
            . ' WHERE project_id = ? AND archived_at IS NULL AND deleted_at IS NULL'
            . ' AND status_code NOT IN (' . $placeholders . ')'
        );
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    private function buildListQuery(array $filters, ?int $actorUserId, bool $actorIsRoot, string $order = 'DESC'): QueryBuilder
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('projects p')
            ->leftJoin('users mu', 'mu.id', '=', 'p.manager_user_id')
            ->leftJoin('teams t', 't.public_id', '=', 'p.team_public_id');

        if (($filters['archived'] ?? '0') !== '1') {
            $qb->whereNull('p.archived_at');
        }

        if (!empty($filters['search'])) {
            $term = '%' . (string)$filters['search'] . '%';
            $qb->whereRaw('(p.title LIKE ? OR p.description LIKE ? OR t.title LIKE ?)', [$term, $term, $term]);
        }

        if (!empty($filters['status'])) {
            $qb->where('p.status_code', '=', (string)$filters['status']);
        }

        if (!empty($filters['team_public_id'])) {
            $qb->where('p.team_public_id', '=', (string)$filters['team_public_id']);
        }

        if (!empty($filters['updated_since'])) {
            $qb->where('p.updated_at', '>=', (string)$filters['updated_since']);
        }

        $cursor = $this->cursorCodec->decode((string)($filters['cursor'] ?? ''));
        if (is_array($cursor)) {
            $cursorUpdatedAt = (string)($cursor['updated_at'] ?? '');
            $cursorPublicId = (string)($cursor['public_id'] ?? '');
            if ($cursorUpdatedAt !== '' && $cursorPublicId !== '') {
                if ($order === 'ASC') {
                    $qb->whereRaw('(p.updated_at > ? OR (p.updated_at = ? AND p.public_id > ?))', [$cursorUpdatedAt, $cursorUpdatedAt, $cursorPublicId]);
                } else {
                    $qb->whereRaw('(p.updated_at < ? OR (p.updated_at = ? AND p.public_id < ?))', [$cursorUpdatedAt, $cursorUpdatedAt, $cursorPublicId]);
                }
            }
        }

        if (!$actorIsRoot && $actorUserId !== null && $actorUserId > 0) {
            $accessibleTeamIds = array_values(array_filter(
                array_map(static fn($value): string => trim((string)$value), (array)($filters['accessible_team_public_ids'] ?? [])),
                static fn(string $value): bool => $value !== ''
            ));

            $params = [$actorUserId, $actorUserId];
            $sql = '(p.created_by_user_id = ? OR p.manager_user_id = ?';
            if ($accessibleTeamIds !== []) {
                $placeholders = implode(', ', array_fill(0, count($accessibleTeamIds), '?'));
                $sql .= ' OR p.team_public_id IN (' . $placeholders . ')';
                $params = array_merge($params, $accessibleTeamIds);
            }
            $sql .= ')';

            $qb->whereRaw($sql, $params);
        }

        return $qb;
    }
}
