<?php
declare(strict_types=1);

namespace Api\Model\Project;

use Api\System\Library\Database\Builder\Expression;
use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Sync\CursorCodec;
use Api\System\Library\Support\TaskStatusSemantics;
use PDO;

final class ProjectRepository
{
    private CursorCodec $cursorCodec;
    /** @var array<string,bool> */
    private array $organizationColumns = [];

    public function __construct(private readonly PDO $pdo)
    {
        $this->cursorCodec = new CursorCodec();
    }

    public function list(array $filters, ?int $actorUserId = null, bool $actorIsRoot = false, bool $rlsScoped = false, ?int $organizationId = null): array
    {
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $sort = in_array(($filters['sort'] ?? ''), ['title', 'created_at', 'updated_at'], true) ? (string)$filters['sort'] : 'updated_at';
        $order = strtoupper((string)($filters['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $paginationMode = (($filters['pagination_mode'] ?? '') === 'cursor' || !empty($filters['cursor'])) ? 'cursor' : 'offset';

        $organizationId ??= $this->organizationIdFromFilters($filters);
        $listBuilder = $this->buildListQuery($filters, $actorUserId, $actorIsRoot, $order, $rlsScoped, $organizationId);
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
                'c.title AS client_title',
                // Live progress for the projects list: total / done tasks per
                // project. "done" mirrors ProjectSummaryRepository (status_code
                // = 'done'), and archived/deleted tasks are excluded.
                "(SELECT COUNT(*) FROM tasks tk WHERE tk.project_id = p.id AND tk.deleted_at IS NULL AND tk.archived_at IS NULL) AS total_tasks_count",
                "(SELECT COUNT(*) FROM tasks tk WHERE tk.project_id = p.id AND tk.deleted_at IS NULL AND tk.archived_at IS NULL AND tk.status_code = 'done') AS done_tasks_count",
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

        $countBuilder = $this->buildListQuery($filters, $actorUserId, $actorIsRoot, $order, $rlsScoped, $organizationId)
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

    public function create(array $payload, ?int $organizationId = null): void
    {
        $this->scopePayload($payload, $organizationId);
        (new QueryBuilder($this->pdo))
            ->from('projects')
            ->insert($payload);
    }

    public function findByPublicId(string $publicId, ?int $organizationId = null): ?array
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('projects p')
            ->leftJoin('users mu', 'mu.id', '=', 'p.manager_user_id')
            ->leftJoin('users cu', 'cu.id', '=', 'p.created_by_user_id')
            ->leftJoin('teams t', 't.public_id', '=', 'p.team_public_id')
            ->leftJoin('counterparties c', 'c.public_id', '=', 'p.client_public_id')
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
                'c.title AS client_title',
            ])
            ->where('p.public_id', '=', $publicId);
        $this->applyOrganizationScope($qb, 'p', $organizationId);
        return $qb->first();
    }

    public function updateByPublicId(string $publicId, array $set, ?int $expectedRowVersion = null, ?int $organizationId = null): bool
    {
        if ($set === []) {
            return false;
        }

        $set['row_version'] = new Expression('row_version + 1');

        $qb = (new QueryBuilder($this->pdo))
            ->from('projects')
            ->where('public_id', '=', $publicId);
        $this->applyOrganizationScope($qb, null, $organizationId);

        if ($expectedRowVersion !== null) {
            $qb->where('row_version', '=', $expectedRowVersion);
        }

        return $qb->update($set) > 0;
    }

    public function archiveByPublicId(string $publicId, string $archivedAt, ?int $organizationId = null): bool
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('projects')
            ->where('public_id', '=', $publicId)
            ->whereNull('archived_at');
        $this->applyOrganizationScope($qb, null, $organizationId);
        return $qb->update([
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

    public function projectIdByPublicId(string $projectPublicId, ?int $organizationId = null): ?int
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('projects')
            ->select(['id'])
            ->where('public_id', '=', $projectPublicId);
        $this->applyOrganizationScope($qb, null, $organizationId);
        $row = $qb->first();
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
     * A project row by numeric id.
     *
     * @return array<string,mixed>|null
     */
    public function findById(int $projectId, ?int $organizationId = null): ?array
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('projects')
            ->where('id', '=', $projectId);
        $this->applyOrganizationScope($qb, null, $organizationId);
        return $qb->first();
    }

    /**
     * Store the task-key prefix of a project, by numeric id.
     *
     * Deliberately does not bump `row_version`: this is the system filling in a
     * prefix the project never had (see TaskKeyService::projectPrefix), not a user
     * editing the project card, and bumping it would fail the optimistic lock of
     * anybody who has that project open. Uniqueness is still enforced by the
     * database through `uq_projects_task_key_prefix`, which is what the caller
     * retries on.
     */
    public function setTaskKeyPrefixById(int $projectId, string $prefix): void
    {
        (new QueryBuilder($this->pdo))
            ->from('projects')
            ->where('id', '=', $projectId)
            ->update([
                'task_key_prefix' => $prefix,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Количество открытых задач проекта (для защиты от преждевременного закрытия проекта, ТЗ 7.3).
     *
     * @param string[] $closedStatuses
     */
    public function countOpenTasksByProjectId(int $projectId, array $closedStatuses = []): int
    {
        $closed = $closedStatuses !== [] ? $closedStatuses : TaskStatusSemantics::terminalCodes($this->pdo);
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

    private function buildListQuery(array $filters, ?int $actorUserId, bool $actorIsRoot, string $order = 'DESC', bool $rlsScoped = false, ?int $organizationId = null): QueryBuilder
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('projects p')
            ->leftJoin('users mu', 'mu.id', '=', 'p.manager_user_id')
            ->leftJoin('teams t', 't.public_id', '=', 'p.team_public_id')
            ->leftJoin('counterparties c', 'c.public_id', '=', 'p.client_public_id');

        $this->applyOrganizationScope($qb, 'p', $organizationId);

        if (($filters['archived'] ?? '0') !== '1') {
            $qb->whereNull('p.archived_at');
        }

        if (!empty($filters['search'])) {
            $term = '%' . $this->escapeLikeValue((string)$filters['search']) . '%';
            $qb->whereRaw('(p.title LIKE ? OR p.description LIKE ? OR t.title LIKE ? OR c.title LIKE ?)', [$term, $term, $term, $term]);
        }

        if (!empty($filters['status'])) {
            $qb->where('p.status_code', '=', (string)$filters['status']);
        }

        if (!empty($filters['priority'])) {
            $qb->where('p.priority_code', '=', (string)$filters['priority']);
        }

        if (!empty($filters['client_public_id'])) {
            $qb->where('p.client_public_id', '=', (string)$filters['client_public_id']);
        }

        // RLS for an executor-role external guest: explicit per-project grants
        // (external_user_project_access), never a counterparty-wide filter.
        // The key's mere presence (even mapping to []) means "an executor's
        // scope is active" — an executor with zero grants must see nothing,
        // not fall through to an unscoped query.
        if (array_key_exists('executor_project_ids', $filters)) {
            $ids = array_values(array_filter(array_map('intval', (array)$filters['executor_project_ids']), static fn(int $v): bool => $v > 0));
            if ($ids === []) {
                $qb->whereRaw('1 = 0');
            } else {
                $qb->whereIn('p.id', $ids);
            }
        }

        if (!empty($filters['manager_user_public_id'])) {
            $qb->whereRaw(
                'EXISTS (SELECT 1 FROM users pmu WHERE pmu.id = p.manager_user_id AND pmu.public_id = ?)',
                [(string)$filters['manager_user_public_id']]
            );
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

        // When the service layer signals that row-level security has already
        // been applied (an external user scoped to their counterparty or granted
        // projects), skip the team/creator/manager access gate. The rlsScoped
        // flag is set exclusively by the service layer �� it MUST NOT be derived
        // from user-supplied filter keys, as that would let an internal user
        // bypass the gate by injecting client_public_id or executor_project_ids.

        if (!$actorIsRoot && !$rlsScoped && $actorUserId !== null && $actorUserId > 0) {
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

    private function organizationIdFromFilters(array $filters): ?int
    {
        $id = isset($filters['organization_id']) ? (int)$filters['organization_id'] : 0;
        return $id > 0 ? $id : null;
    }

    /** Apply an opt-in tenant predicate; null keeps pre-Organizations behavior. */
    private function applyOrganizationScope(QueryBuilder $qb, ?string $alias, ?int $organizationId): void
    {
        if ($organizationId === null || $organizationId <= 0) return;
        $column = $alias !== null ? $alias . '.organization_id' : 'organization_id';
        if (!$this->hasOrganizationColumn('projects')) {
            $qb->whereRaw('1 = 0');
            return;
        }
        $qb->where($column, '=', $organizationId);
    }

    private function scopePayload(array &$payload, ?int $organizationId): void
    {
        if ($organizationId === null || $organizationId <= 0) return;
        if (!$this->hasOrganizationColumn('projects')) {
            throw new \RuntimeException('Organization scope is unavailable for projects');
        }
        $payload['organization_id'] = $organizationId;
    }

    private function hasOrganizationColumn(string $table): bool
    {
        if (array_key_exists($table, $this->organizationColumns)) return $this->organizationColumns[$table];
        try {
            $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->query('PRAGMA table_info(' . $table . ')');
                foreach ($stmt?->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    if ((string)($row['name'] ?? '') === 'organization_id') return $this->organizationColumns[$table] = true;
                }
                return $this->organizationColumns[$table] = false;
            }
            $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = \'organization_id\' LIMIT 1');
            $stmt->execute(['table' => $table]);
            return $this->organizationColumns[$table] = $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return $this->organizationColumns[$table] = false;
        }
    }

    private function escapeLikeValue(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
