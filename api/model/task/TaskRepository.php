<?php
declare(strict_types=1);

namespace Api\Model\Task;

use Api\System\Library\Database\Builder\Expression;
use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Sync\CursorCodec;
use PDO;

final class TaskRepository
{
    private CursorCodec $cursorCodec;

    public function __construct(private readonly PDO $pdo)
    {
        $this->cursorCodec = new CursorCodec();
    }

    public function list(array $filters, ?int $actorUserId = null, bool $actorIsRoot = false): array
    {
        $sort = in_array(($filters['sort'] ?? ''), ['title', 'due_at', 'created_at', 'updated_at', 'status_code', 'priority_code'], true) ? (string)$filters['sort'] : 'updated_at';
        $order = strtoupper((string)($filters['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $paginationMode = (($filters['pagination_mode'] ?? '') === 'cursor' || !empty($filters['cursor'])) ? 'cursor' : 'offset';
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));

        $builder = $this->buildListQuery($filters, $actorUserId, $actorIsRoot, $order)
            ->select([
                't.public_id',
                't.title',
                't.description',
                't.status_code',
                't.priority_code',
                't.due_at',
                't.created_at',
                't.updated_at',
                't.row_version',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
                'p.client_public_id AS client_public_id',
                'p.team_public_id AS project_team_public_id',
                'pt.title AS project_team_title',
                "(SELECT parent_task.public_id
                    FROM task_relations trp
                    INNER JOIN tasks parent_task ON parent_task.id = trp.parent_task_id
                    WHERE trp.child_task_id = t.id
                      AND trp.relation_type = 'subtask'
                    LIMIT 1) AS parent_task_public_id",
                "(SELECT parent_task.title
                    FROM task_relations trp
                    INNER JOIN tasks parent_task ON parent_task.id = trp.parent_task_id
                    WHERE trp.child_task_id = t.id
                      AND trp.relation_type = 'subtask'
                    LIMIT 1) AS parent_task_title",
                "(SELECT trp.sort_order
                    FROM task_relations trp
                    WHERE trp.child_task_id = t.id
                      AND trp.relation_type = 'subtask'
                    LIMIT 1) AS parent_relation_sort_order",
                "EXISTS(
                    SELECT 1
                    FROM task_relations trc
                    WHERE trc.parent_task_id = t.id
                      AND trc.relation_type = 'subtask'
                ) AS has_subtasks",
            ])
            ->orderBy('t.' . $sort, $order)
            ->orderBy('t.public_id', $order);

        if ($paginationMode === 'cursor') {
            $items = $builder
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

        $items = $builder
            ->limit($limit)
            ->offset($offset)
            ->get();

        $total = $this->buildListQuery($filters, $actorUserId, $actorIsRoot, $order)->count();

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

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->leftJoin('users cu', 'cu.id', '=', 't.creator_user_id')
            ->leftJoin('users au', 'au.id', '=', 't.assignee_user_id')
            ->leftJoin('users pm', 'pm.id', '=', 'p.manager_user_id')
            ->leftJoin('teams pt', 'pt.public_id', '=', 'p.team_public_id')
            ->select([
                't.*',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
                'p.created_by_user_id AS project_creator_user_id',
                'p.manager_user_id AS project_manager_user_id',
                'p.team_public_id AS project_team_public_id',
                'pt.title AS project_team_title',
                'pt.manager_user_id AS project_team_manager_user_id',
                'pt.member_user_ids AS project_team_member_user_ids',
                'cu.public_id AS creator_user_public_id',
                'au.public_id AS assignee_user_public_id',
                'au.full_name AS assignee_name',
                'pm.public_id AS project_manager_user_public_id',
                'pm.full_name AS project_manager_name',
                "(SELECT parent_task.public_id
                    FROM task_relations trp
                    INNER JOIN tasks parent_task ON parent_task.id = trp.parent_task_id
                    WHERE trp.child_task_id = t.id
                      AND trp.relation_type = 'subtask'
                    LIMIT 1) AS parent_task_public_id",
                "(SELECT parent_task.title
                    FROM task_relations trp
                    INNER JOIN tasks parent_task ON parent_task.id = trp.parent_task_id
                    WHERE trp.child_task_id = t.id
                      AND trp.relation_type = 'subtask'
                    LIMIT 1) AS parent_task_title",
                "(SELECT trp.sort_order
                    FROM task_relations trp
                    WHERE trp.child_task_id = t.id
                      AND trp.relation_type = 'subtask'
                    LIMIT 1) AS parent_relation_sort_order",
            ])
            ->where('t.public_id', '=', $publicId)
            ->first();
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->insert($payload);
    }

    public function taskIdByPublicId(string $taskPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->select(['id'])
            ->where('public_id', '=', $taskPublicId)
            ->whereNull('deleted_at')
            ->first();
        $id = $row['id'] ?? false;

        return $id !== false ? (int)$id : null;
    }

    public function createRelation(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('task_relations')
            ->insert($payload);
    }

    public function updateSubtaskRelationByChildTaskId(int $childTaskId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('task_relations')
            ->where('child_task_id', '=', $childTaskId)
            ->where('relation_type', '=', 'subtask')
            ->update($set) > 0;
    }

    public function deleteSubtaskRelationByChildTaskId(int $childTaskId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('task_relations')
            ->where('child_task_id', '=', $childTaskId)
            ->where('relation_type', '=', 'subtask')
            ->delete() > 0;
    }

    public function nextSortOrderForParentTaskId(int $parentTaskId): int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('task_relations')
            ->select(['MAX(sort_order) AS max_sort_order'])
            ->where('parent_task_id', '=', $parentTaskId)
            ->where('relation_type', '=', 'subtask')
            ->first();

        return max(0, (int)($row['max_sort_order'] ?? 0)) + 10;
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        $set['row_version'] = new Expression('row_version + 1');

        return (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
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

    public function createStatusHistory(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('task_status_history')
            ->insert($payload);
    }

    public function softDeleteByPublicId(string $publicId, string $deletedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->where('public_id', '=', $publicId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
                'row_version' => new Expression('row_version + 1'),
            ]) > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public function boardItems(array $filters, ?int $actorUserId = null, bool $actorIsRoot = false, int $limit = 500): array
    {
        $sort = in_array(($filters['sort'] ?? ''), ['updated_at', 'due_at', 'priority_code', 'title'], true) ? (string)$filters['sort'] : 'updated_at';
        $order = strtoupper((string)($filters['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->buildListQuery($filters, $actorUserId, $actorIsRoot)
            ->leftJoin('users u', 'u.id', '=', 't.assignee_user_id')
            ->select([
                't.public_id',
                't.title',
                't.description',
                't.status_code',
                't.priority_code',
                't.start_at',
                't.due_at',
                't.end_at',
                't.updated_at',
                't.row_version',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
                'u.public_id AS assignee_user_public_id',
                'u.full_name AS assignee_name',
            ]);

        if (!empty($filters['assignee_user_public_id'])) {
            $qb->where('u.public_id', '=', (string)$filters['assignee_user_public_id']);
        }

        if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
            $statuses = array_values(array_filter(array_map(static fn($v): string => trim((string)$v), $filters['statuses']), static fn(string $v): bool => $v !== ''));
            if ($statuses !== []) {
                $qb->whereIn('t.status_code', $statuses);
            }
        }

        return $qb
            ->orderBy('t.' . $sort, $order)
            ->limit(min(1000, max(1, $limit)))
            ->get();
    }

    /** @return array<int,array<string,mixed>> */
    public function listAssignedOverdueOpenTasks(int $assigneeUserId, string $nowUtc, int $limit = 100): array
    {
        if ($assigneeUserId <= 0) {
            return [];
        }

        $safeLimit = min(300, max(1, $limit));

        return (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->select([
                't.public_id',
                't.title',
                't.due_at',
                't.status_code',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
            ])
            ->where('t.assignee_user_id', '=', $assigneeUserId)
            ->whereNull('t.deleted_at')
            ->whereNull('t.archived_at')
            ->whereRaw("t.status_code NOT IN (?, ?, ?)", ['done', 'closed', 'archived'])
            ->whereNotNull('t.due_at')
            ->where('t.due_at', '<', $nowUtc)
            ->orderBy('t.due_at', 'ASC')
            ->limit($safeLimit)
            ->get();
    }

    /** @return array{total:int,items:array<int,array<string,mixed>>} */
    public function managerOverdueDigest(int $managerUserId, string $nowUtc, int $limitProjects = 5): array
    {
        if ($managerUserId <= 0) {
            return ['total' => 0, 'items' => []];
        }

        $safeLimit = min(20, max(1, $limitProjects));
        $base = (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->leftJoin('teams pt', 'pt.public_id', '=', 'p.team_public_id')
            ->whereNull('t.deleted_at')
            ->whereNull('t.archived_at')
            ->whereRaw("t.status_code NOT IN (?, ?, ?)", ['done', 'closed', 'archived'])
            ->whereNotNull('t.due_at')
            ->where('t.due_at', '<', $nowUtc)
            ->whereRaw('(p.manager_user_id = ? OR pt.manager_user_id = ?)', [$managerUserId, $managerUserId]);

        $total = $base->count();
        $items = (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->leftJoin('teams pt', 'pt.public_id', '=', 'p.team_public_id')
            ->select([
                'p.public_id AS project_public_id',
                'p.title AS project_title',
                'COUNT(*) AS overdue_count',
            ])
            ->whereNull('t.deleted_at')
            ->whereNull('t.archived_at')
            ->whereRaw("t.status_code NOT IN (?, ?, ?)", ['done', 'closed', 'archived'])
            ->whereNotNull('t.due_at')
            ->where('t.due_at', '<', $nowUtc)
            ->whereRaw('(p.manager_user_id = ? OR pt.manager_user_id = ?)', [$managerUserId, $managerUserId])
            ->groupBy('p.public_id')
            ->groupBy('p.title')
            ->orderBy('overdue_count', 'DESC')
            ->limit($safeLimit)
            ->get();

        return [
            'total' => (int)$total,
            'items' => $items,
        ];
    }

    private function buildListQuery(array $filters, ?int $actorUserId, bool $actorIsRoot, string $order = 'DESC'): QueryBuilder
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->leftJoin('teams pt', 'pt.public_id', '=', 'p.team_public_id');

        if (($filters['archived'] ?? '0') !== '1') {
            $qb->whereNull('t.archived_at')
                ->whereNull('t.deleted_at');
        }

        if (!empty($filters['status'])) {
            $qb->where('t.status_code', '=', (string)$filters['status']);
        }

        if (!empty($filters['priority'])) {
            $qb->where('t.priority_code', '=', (string)$filters['priority']);
        }

        if (!empty($filters['tag_public_id'])) {
            $qb->whereRaw(
                'EXISTS (SELECT 1 FROM entity_tags et INNER JOIN tags tg ON tg.id = et.tag_id WHERE et.entity_type = ? AND et.entity_public_id = t.public_id AND tg.public_id = ?)',
                ['task', (string)$filters['tag_public_id']]
            );
        }

        if (!empty($filters['project_public_id'])) {
            $qb->where('p.public_id', '=', (string)$filters['project_public_id']);
        }

        if (!empty($filters['client_public_id'])) {
            $qb->where('p.client_public_id', '=', (string)$filters['client_public_id']);
        }

        if (!empty($filters['team_public_id'])) {
            $qb->where('p.team_public_id', '=', (string)$filters['team_public_id']);
        }

        if (!empty($filters['search'])) {
            $term = '%' . (string)$filters['search'] . '%';
            $qb->whereRaw('(t.title LIKE ? OR t.description LIKE ?)', [$term, $term]);
        }

        if (!empty($filters['updated_since'])) {
            $qb->where('t.updated_at', '>=', (string)$filters['updated_since']);
        }

        if (!empty($filters['due_at'])) {
            $day = (string)$filters['due_at'];
            $qb->where('t.due_at', '>=', $day . ' 00:00:00')
                ->where('t.due_at', '<=', $day . ' 23:59:59');
        }

        if (!empty($filters['due_at_from'])) {
            $qb->where('t.due_at', '>=', (string)$filters['due_at_from'] . ' 00:00:00');
        }

        if (!empty($filters['due_at_to'])) {
            $qb->where('t.due_at', '<=', (string)$filters['due_at_to'] . ' 23:59:59');
        }

        $cursor = $this->cursorCodec->decode((string)($filters['cursor'] ?? ''));
        if (is_array($cursor)) {
            $cursorUpdatedAt = (string)($cursor['updated_at'] ?? '');
            $cursorPublicId = (string)($cursor['public_id'] ?? '');
            if ($cursorUpdatedAt !== '' && $cursorPublicId !== '') {
                if ($order === 'ASC') {
                    $qb->whereRaw('(t.updated_at > ? OR (t.updated_at = ? AND t.public_id > ?))', [$cursorUpdatedAt, $cursorUpdatedAt, $cursorPublicId]);
                } else {
                    $qb->whereRaw('(t.updated_at < ? OR (t.updated_at = ? AND t.public_id < ?))', [$cursorUpdatedAt, $cursorUpdatedAt, $cursorPublicId]);
                }
            }
        }

        if (!$actorIsRoot && $actorUserId !== null && $actorUserId > 0) {
            $accessibleTeamIds = array_values(array_filter(
                array_map(static fn($value): string => trim((string)$value), (array)($filters['accessible_team_public_ids'] ?? [])),
                static fn(string $value): bool => $value !== ''
            ));

            $params = [$actorUserId, $actorUserId, $actorUserId, $actorUserId];
            $sql = '(t.creator_user_id = ? OR t.assignee_user_id = ? OR p.created_by_user_id = ? OR p.manager_user_id = ?';
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
