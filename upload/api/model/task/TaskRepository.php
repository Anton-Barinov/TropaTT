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

    // Keep in sync with TASK_SORT_KEYS in page-api-bindings.js (web side).
    private const SORT_ALLOWLIST = ['title', 'task_key', 'project_title', 'due_at', 'created_at', 'updated_at', 'status_code', 'priority_code'];
    private const SORT_MAX_LEVELS = 4;

    /**
     * Parse a multi-level sort spec into an ordered list of [key, direction]
     * pairs. Accepts "key:DIR,key2:DIR2" chains (up to 4 levels) and the legacy
     * single "key" form combined with $fallbackOrder. Unknown keys are skipped.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function parseSortPairs(string $raw, string $fallbackOrder): array
    {
        $pairs = [];
        $raw = trim($raw);
        if ($raw === '') {
            return $pairs;
        }
        if (str_contains($raw, ':') || str_contains($raw, ',')) {
            foreach (explode(',', $raw) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                [$key, $dir] = array_pad(explode(':', $part, 2), 2, 'ASC');
                $key = trim($key);
                $dir = strtoupper(trim($dir)) === 'DESC' ? 'DESC' : 'ASC';
                if ($key !== '' && in_array($key, self::SORT_ALLOWLIST, true)) {
                    $pairs[] = [$key, $dir];
                    if (count($pairs) >= self::SORT_MAX_LEVELS) {
                        break;
                    }
                }
            }
            return $pairs;
        }
        // Legacy single-key form: "sort=title" + "order=ASC|DESC".
        if (in_array($raw, self::SORT_ALLOWLIST, true)) {
            return [[$raw, $fallbackOrder]];
        }
        return $pairs;
    }

    public function list(array $filters, ?int $actorUserId = null, bool $actorIsRoot = false, bool $rlsScoped = false): array
    {
        // Multi-level sort: filters['sort'] accepts a comma-separated chain of
        // "key:DIR" pairs (e.g. "title:ASC,priority_code:DESC"), up to 4 levels.
        $order = strtoupper((string)($filters['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $sortPairs = $this->parseSortPairs((string)($filters['sort'] ?? ''), $order);
        if ($sortPairs === []) {
            $sortPairs = [['updated_at', 'DESC']];
        }
        $paginationMode = (($filters['pagination_mode'] ?? '') === 'cursor' || !empty($filters['cursor'])) ? 'cursor' : 'offset';
        $requestedLimit = (int)($filters['limit'] ?? 20);
        // 0 = unlimited (offset mode only; cursor mode always keeps a positive page size).
        $limit = $requestedLimit === 0 && $paginationMode !== 'cursor' ? 0 : min(500, max(1, $requestedLimit));

        $builder = $this->buildListQuery($filters, $actorUserId, $actorIsRoot, $order, $rlsScoped)
            ->leftJoin('users au', 'au.id', '=', 't.assignee_user_id')
            ->leftJoin('users pm', 'pm.id', '=', 'p.manager_user_id')
            ->leftJoin('users tm', 'tm.id', '=', 'pt.manager_user_id')
            ->select([
                't.public_id',
                't.task_key',
                't.task_key_prefix',
                't.task_sequence_number',
                't.title',
                't.description',
                't.status_code',
                't.priority_code',
                't.due_at',
                't.start_at',
                't.end_at',
                't.created_at',
                't.updated_at',
                't.row_version',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
                'p.client_public_id AS client_public_id',
                'c.title AS client_title',
                'tc.public_id AS task_client_public_id',
                'tc.title AS task_client_title',
                'p.team_public_id AS project_team_public_id',
                'pt.title AS project_team_title',
                'au.public_id AS assignee_user_public_id',
                'au.full_name AS assignee_name',
                'au.login AS assignee_login',
                'pm.public_id AS project_manager_user_public_id',
                'pm.full_name AS project_manager_name',
                'pm.login AS project_manager_login',
                'tm.public_id AS project_team_manager_user_public_id',
                'tm.full_name AS project_team_manager_name',
                'tm.login AS project_team_manager_login',
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
                "(SELECT CONCAT('[', GROUP_CONCAT(JSON_OBJECT('public_id', tg.public_id, 'code', tg.code, 'title', tg.title, 'color', tg.color) SEPARATOR ','), ']')
                  FROM entity_tags et
                  INNER JOIN tags tg ON tg.id = et.tag_id
                  WHERE et.entity_type = 'task' AND et.entity_public_id = t.public_id
                ) AS tags",
                "(SELECT COUNT(*) FROM knowledge_entity_links kel WHERE kel.entity_type = 'task' AND kel.entity_public_id COLLATE utf8mb4_unicode_ci = t.public_id) AS knowledge_links_count",
                "(SELECT COUNT(*) FROM task_dependencies td
                    INNER JOIN tasks blocker ON blocker.id = td.depends_on_task_id
                    WHERE td.task_id = t.id
                      AND blocker.deleted_at IS NULL
                      AND blocker.status_code NOT IN ('done','cancelled')
                ) AS blocked_by_count",
                "(SELECT wc.public_id FROM cycle_tasks ct INNER JOIN work_cycles wc ON wc.id = ct.cycle_id WHERE ct.task_id = t.id AND ct.deleted_at IS NULL AND wc.deleted_at IS NULL AND wc.status IN ('planned','active') LIMIT 1) AS cycle_public_id",
                "(SELECT wc.title FROM cycle_tasks ct INNER JOIN work_cycles wc ON wc.id = ct.cycle_id WHERE ct.task_id = t.id AND ct.deleted_at IS NULL AND wc.deleted_at IS NULL AND wc.status IN ('planned','active') LIMIT 1) AS cycle_title",
                "(SELECT wc.status FROM cycle_tasks ct INNER JOIN work_cycles wc ON wc.id = ct.cycle_id WHERE ct.task_id = t.id AND ct.deleted_at IS NULL AND wc.deleted_at IS NULL AND wc.status IN ('planned','active') LIMIT 1) AS cycle_status",
                "(SELECT CONCAT('[', GROUP_CONCAT(JSON_OBJECT('public_id', pm.public_id, 'title', pm.title, 'status', pm.status) SEPARATOR ','), ']')
                  FROM project_module_tasks pmt
                  INNER JOIN project_modules pm ON pm.id = pmt.module_id
                  WHERE pmt.task_id = t.id AND pmt.deleted_at IS NULL AND pm.deleted_at IS NULL
                ) AS modules",
            ]);

        // Each sort level becomes its own ORDER BY term so the chain reads exactly
        // as the user built it (e.g. title ASC, priority DESC). project_title
        // lives on the joined projects row, not on tasks.
        foreach ($sortPairs as [$sortKey, $sortDir]) {
            $sortColumn = $sortKey === 'project_title' ? 'p.title' : 't.' . $sortKey;
            $builder->orderBy($sortColumn, $sortDir);
        }
        // Deterministic tiebreaker keeps pagination stable across pages.
        $builder->orderBy('t.public_id', $sortPairs[0][1] ?? 'DESC');

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

        if ($limit > 0) {
            // QueryBuilder always emits LIMIT + OFFSET together; a bare OFFSET without
            // LIMIT is invalid MySQL, so both are skipped for unlimited (limit=0) lists.
            $builder->limit($limit)->offset($offset);
        }
        $items = $builder->get();

        $total = $this->buildListQuery($filters, $actorUserId, $actorIsRoot, $order, $rlsScoped)->count();

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

    /**
     * Full per-status counts for the task set visible to the actor under $filters.
     * Ignores pagination/limit — used by the kanban board to render real column
     * counters while tasks are still being chunk-loaded in the background.
     * Reuses buildListQuery(), so access scoping is identical to list().
     *
     * @return array<string,int> status_code => count
     */
    public function countByStatus(array $filters, ?int $actorUserId = null, bool $actorIsRoot = false, bool $rlsScoped = false): array
    {
        $rows = $this->buildListQuery($filters, $actorUserId, $actorIsRoot, 'DESC', $rlsScoped)
            ->select([
                't.status_code AS status_code',
                'COUNT(*) AS task_count',
            ])
            ->groupBy(['t.status_code'])
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string)($row['status_code'] ?? '')] = (int)($row['task_count'] ?? 0);
        }

        return $counts;
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->leftJoin('counterparties c', 'c.public_id', '=', 'p.client_public_id')
            ->leftJoin('counterparties tc', 'tc.public_id', '=', 't.client_public_id')
            ->leftJoin('users cu', 'cu.id', '=', 't.creator_user_id')
            ->leftJoin('users au', 'au.id', '=', 't.assignee_user_id')
            ->leftJoin('users pm', 'pm.id', '=', 'p.manager_user_id')
            ->leftJoin('teams pt', 'pt.public_id', '=', 'p.team_public_id')
            ->select([
                't.*',
                't.task_key',
                't.task_key_prefix',
                't.task_sequence_number',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
                'p.client_public_id AS client_public_id',
                'c.title AS client_title',
                'tc.public_id AS task_client_public_id',
                'tc.title AS task_client_title',
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
                "(SELECT CONCAT('[', GROUP_CONCAT(JSON_OBJECT('public_id', tg.public_id, 'code', tg.code, 'title', tg.title, 'color', tg.color) SEPARATOR ','), ']')
                  FROM entity_tags et
                  INNER JOIN tags tg ON tg.id = et.tag_id
                  WHERE et.entity_type = 'task' AND et.entity_public_id = t.public_id
                ) AS tags",
                "(SELECT wc.public_id FROM cycle_tasks ct INNER JOIN work_cycles wc ON wc.id = ct.cycle_id WHERE ct.task_id = t.id AND ct.deleted_at IS NULL AND wc.deleted_at IS NULL AND wc.status IN ('planned','active') LIMIT 1) AS cycle_public_id",
                "(SELECT wc.title FROM cycle_tasks ct INNER JOIN work_cycles wc ON wc.id = ct.cycle_id WHERE ct.task_id = t.id AND ct.deleted_at IS NULL AND wc.deleted_at IS NULL AND wc.status IN ('planned','active') LIMIT 1) AS cycle_title",
                "(SELECT wc.status FROM cycle_tasks ct INNER JOIN work_cycles wc ON wc.id = ct.cycle_id WHERE ct.task_id = t.id AND ct.deleted_at IS NULL AND wc.deleted_at IS NULL AND wc.status IN ('planned','active') LIMIT 1) AS cycle_status",
                "(SELECT CONCAT('[', GROUP_CONCAT(JSON_OBJECT('public_id', pm.public_id, 'title', pm.title, 'status', pm.status) SEPARATOR ','), ']')
                  FROM project_module_tasks pmt
                  INNER JOIN project_modules pm ON pm.id = pmt.module_id
                  WHERE pmt.task_id = t.id AND pmt.deleted_at IS NULL AND pm.deleted_at IS NULL
                ) AS modules",
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

    public function hasCycleAncestor(int $childTaskId, int $candidateParentId): bool
    {
        $visited = [$candidateParentId];
        $currentId = $candidateParentId;
        $depth = 0;
        $maxDepth = 50;

        while ($depth < $maxDepth) {
            $row = (new QueryBuilder($this->pdo))
                ->from('task_relations')
                ->select(['parent_task_id'])
                ->where('child_task_id', '=', $currentId)
                ->where('relation_type', '=', 'subtask')
                ->first();

            if ($row === null) {
                return false;
            }

            $parentId = (int)$row['parent_task_id'];
            if ($parentId === $childTaskId) {
                return true;
            }

            if (in_array($parentId, $visited, true)) {
                return false;
            }

            $visited[] = $parentId;
            $currentId = $parentId;
            $depth++;
        }

        return false;
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

    public function updateByPublicId(string $publicId, array $set, ?int $expectedRowVersion = null): bool
    {
        if ($set === []) {
            return false;
        }

        $set['row_version'] = new Expression('row_version + 1');

        $qb = (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->where('public_id', '=', $publicId);

        if ($expectedRowVersion !== null) {
            $qb->where('row_version', '=', $expectedRowVersion);
        }

        return $qb->update($set) > 0;
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

    public function findByTaskKey(string $taskKey): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->leftJoin('counterparties c', 'c.public_id', '=', 'p.client_public_id')
            ->leftJoin('counterparties tc', 'tc.public_id', '=', 't.client_public_id')
            ->leftJoin('users cu', 'cu.id', '=', 't.creator_user_id')
            ->leftJoin('users au', 'au.id', '=', 't.assignee_user_id')
            ->leftJoin('users pm', 'pm.id', '=', 'p.manager_user_id')
            ->leftJoin('teams pt', 'pt.public_id', '=', 'p.team_public_id')
            ->select([
                't.*',
                't.task_key',
                't.task_key_prefix',
                't.task_sequence_number',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
                'p.client_public_id AS client_public_id',
                'c.title AS client_title',
                'tc.public_id AS task_client_public_id',
                'tc.title AS task_client_title',
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
            ])
            ->where('t.task_key', '=', $taskKey)
            ->first();
    }

    public function taskKeyExists(string $taskKey): bool
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->select(['id'])
            ->where('task_key', '=', $taskKey)
            ->whereNull('deleted_at')
            ->first();

        return $row !== null;
    }

    public function taskIdByTaskKey(string $taskKey): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->select(['id'])
            ->where('task_key', '=', $taskKey)
            ->whereNull('deleted_at')
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
    public function boardItems(array $filters, ?int $actorUserId = null, bool $actorIsRoot = false, int $limit = 500, bool $rlsScoped = false): array
    {
        $sort = in_array(($filters['sort'] ?? ''), ['updated_at', 'due_at', 'priority_code', 'title'], true) ? (string)$filters['sort'] : 'updated_at';
        $order = strtoupper((string)($filters['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->buildListQuery($filters, $actorUserId, $actorIsRoot, $order, $rlsScoped)
            ->leftJoin('users u', 'u.id', '=', 't.assignee_user_id')
            ->select([
                't.public_id',
                't.task_key',
                't.task_key_prefix',
                't.task_sequence_number',
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
                "(SELECT CONCAT('[', GROUP_CONCAT(JSON_OBJECT('public_id', tg.public_id, 'code', tg.code, 'title', tg.title, 'color', tg.color) SEPARATOR ','), ']')
                  FROM entity_tags et
                  INNER JOIN tags tg ON tg.id = et.tag_id
                  WHERE et.entity_type = 'task' AND et.entity_public_id = t.public_id
                ) AS tags",
                "(SELECT wc.public_id FROM cycle_tasks ct INNER JOIN work_cycles wc ON wc.id = ct.cycle_id WHERE ct.task_id = t.id AND ct.deleted_at IS NULL AND wc.deleted_at IS NULL AND wc.status IN ('planned','active') LIMIT 1) AS cycle_public_id",
                "(SELECT wc.title FROM cycle_tasks ct INNER JOIN work_cycles wc ON wc.id = ct.cycle_id WHERE ct.task_id = t.id AND ct.deleted_at IS NULL AND wc.deleted_at IS NULL AND wc.status IN ('planned','active') LIMIT 1) AS cycle_title",
                "(SELECT wc.status FROM cycle_tasks ct INNER JOIN work_cycles wc ON wc.id = ct.cycle_id WHERE ct.task_id = t.id AND ct.deleted_at IS NULL AND wc.deleted_at IS NULL AND wc.status IN ('planned','active') LIMIT 1) AS cycle_status",
                "(SELECT CONCAT('[', GROUP_CONCAT(JSON_OBJECT('public_id', pm.public_id, 'title', pm.title, 'status', pm.status) SEPARATOR ','), ']')
                  FROM project_module_tasks pmt
                  INNER JOIN project_modules pm ON pm.id = pmt.module_id
                  WHERE pmt.task_id = t.id AND pmt.deleted_at IS NULL AND pm.deleted_at IS NULL
                ) AS modules",
            ]);

        // Unassigned/empty values are handled inside buildListQuery (whereNull);
        // only apply the join-based filter for real user ids.
        if (!empty($filters['assignee_user_public_id'])
            && !in_array(strtolower(trim((string)$filters['assignee_user_public_id'])), ['none', 'unassigned', 'empty'], true)
        ) {
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
                't.task_key',
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

    private function buildListQuery(array $filters, ?int $actorUserId, bool $actorIsRoot, string $order = 'DESC', bool $rlsScoped = false): QueryBuilder
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->leftJoin('teams pt', 'pt.public_id', '=', 'p.team_public_id')
            ->leftJoin('counterparties c', 'c.public_id', '=', 'p.client_public_id')
            ->leftJoin('counterparties tc', 'tc.public_id', '=', 't.client_public_id');

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

        if (!empty($filters['exclude_statuses'])) {
            $excludeStatuses = $this->splitFilterList($filters['exclude_statuses']);
            if ($excludeStatuses !== []) {
                $placeholders = implode(', ', array_fill(0, count($excludeStatuses), '?'));
                $qb->whereRaw('t.status_code NOT IN (' . $placeholders . ')', $excludeStatuses);
            }
        }

        if (!empty($filters['tag_public_id'])) {
            $tagParts = $this->splitFilterList($filters['tag_public_id']);
            $wantsNone = $this->listWantsNone($tagParts);
            $ids = $this->listWithoutNone($tagParts);
            if ($wantsNone && $ids === []) {
                $qb->whereRaw(
                    'NOT EXISTS (SELECT 1 FROM entity_tags etn WHERE etn.entity_type = ? AND etn.entity_public_id = t.public_id)',
                    ['task']
                );
            } elseif ($wantsNone) {
                $placeholders = implode(', ', array_fill(0, count($ids), '?'));
                $qb->whereRaw(
                    '(NOT EXISTS (SELECT 1 FROM entity_tags etn WHERE etn.entity_type = ? AND etn.entity_public_id = t.public_id)'
                    . ' OR EXISTS (SELECT 1 FROM entity_tags et INNER JOIN tags tg ON tg.id = et.tag_id WHERE et.entity_type = ? AND et.entity_public_id = t.public_id AND tg.public_id IN (' . $placeholders . ')))',
                    array_merge(['task', 'task'], $ids)
                );
            } elseif ($ids !== []) {
                $placeholders = implode(', ', array_fill(0, count($ids), '?'));
                $qb->whereRaw(
                    'EXISTS (SELECT 1 FROM entity_tags et INNER JOIN tags tg ON tg.id = et.tag_id WHERE et.entity_type = ? AND et.entity_public_id = t.public_id AND tg.public_id IN (' . $placeholders . '))',
                    array_merge(['task'], $ids)
                );
            }
            // else: only commas/whitespace were given — nothing to filter by.
        }

        if (!empty($filters['project_public_id'])) {
            $projectParts = $this->splitFilterList($filters['project_public_id']);
            $wantsNone = $this->listWantsNone($projectParts);
            $ids = $this->listWithoutNone($projectParts);
            if ($wantsNone && $ids === []) {
                $qb->whereNull('p.public_id');
            } elseif ($wantsNone) {
                $placeholders = implode(', ', array_fill(0, count($ids), '?'));
                $qb->whereRaw('(p.public_id IS NULL OR p.public_id IN (' . $placeholders . '))', $ids);
            } elseif ($ids !== []) {
                $qb->whereIn('p.public_id', $ids);
            }
            // else: only commas/whitespace were given — nothing to filter by.
        }

        if (!empty($filters['assignee_user_public_id'])) {
            $assigneeParts = $this->splitFilterList($filters['assignee_user_public_id']);
            $wantsNone = $this->listWantsNone($assigneeParts);
            $ids = $this->listWithoutNone($assigneeParts);
            if ($wantsNone && $ids === []) {
                $qb->whereNull('t.assignee_user_id');
            } elseif ($wantsNone) {
                $placeholders = implode(', ', array_fill(0, count($ids), '?'));
                $qb->whereRaw(
                    '(t.assignee_user_id IS NULL OR EXISTS (SELECT 1 FROM users au2 WHERE au2.id = t.assignee_user_id AND au2.public_id IN (' . $placeholders . ')))',
                    $ids
                );
            } elseif ($ids !== []) {
                $placeholders = implode(', ', array_fill(0, count($ids), '?'));
                $qb->whereRaw(
                    'EXISTS (SELECT 1 FROM users au2 WHERE au2.id = t.assignee_user_id AND au2.public_id IN (' . $placeholders . '))',
                    $ids
                );
            }
        }

        if (!empty($filters['manager_user_public_id'])) {
            $managerParts = $this->splitFilterList($filters['manager_user_public_id']);
            $wantsNone = $this->listWantsNone($managerParts);
            $ids = $this->listWithoutNone($managerParts);
            if ($wantsNone && $ids === []) {
                $qb->whereNull('p.manager_user_id');
            } elseif ($wantsNone) {
                $placeholders = implode(', ', array_fill(0, count($ids), '?'));
                $qb->whereRaw(
                    '(p.manager_user_id IS NULL OR EXISTS (SELECT 1 FROM projects pm2 JOIN users pmu ON pmu.id = pm2.manager_user_id WHERE pm2.id = t.project_id AND pmu.public_id IN (' . $placeholders . ')))',
                    $ids
                );
            } elseif ($ids !== []) {
                $placeholders = implode(', ', array_fill(0, count($ids), '?'));
                $qb->whereRaw(
                    'EXISTS (SELECT 1 FROM projects pm2 JOIN users pmu ON pmu.id = pm2.manager_user_id WHERE pm2.id = t.project_id AND pmu.public_id IN (' . $placeholders . '))',
                    $ids
                );
            }
        }

        if (!empty($filters['cycle_public_id'])) {
            $cycleParts = $this->splitFilterList($filters['cycle_public_id']);
            $wantsNone = $this->listWantsNone($cycleParts);
            $ids = $this->listWithoutNone($cycleParts);
            if ($wantsNone && $ids === []) {
                $qb->whereRaw(
                    'NOT EXISTS (SELECT 1 FROM cycle_tasks ct INNER JOIN work_cycles wc ON wc.id = ct.cycle_id WHERE ct.task_id = t.id AND ct.deleted_at IS NULL AND wc.deleted_at IS NULL AND wc.status IN (\'planned\',\'active\'))'
                );
            } elseif ($wantsNone) {
                $placeholders = implode(', ', array_fill(0, count($ids), '?'));
                $qb->whereRaw(
                    '(NOT EXISTS (SELECT 1 FROM cycle_tasks ct INNER JOIN work_cycles wc ON wc.id = ct.cycle_id WHERE ct.task_id = t.id AND ct.deleted_at IS NULL AND wc.deleted_at IS NULL AND wc.status IN (\'planned\',\'active\')) OR EXISTS (SELECT 1 FROM cycle_tasks ct INNER JOIN work_cycles wc ON wc.id = ct.cycle_id WHERE ct.task_id = t.id AND ct.deleted_at IS NULL AND wc.public_id IN (' . $placeholders . ') AND wc.deleted_at IS NULL))',
                    $ids
                );
            } elseif ($ids !== []) {
                $placeholders = implode(', ', array_fill(0, count($ids), '?'));
                $qb->whereRaw(
                    'EXISTS (SELECT 1 FROM cycle_tasks ct INNER JOIN work_cycles wc ON wc.id = ct.cycle_id WHERE ct.task_id = t.id AND ct.deleted_at IS NULL AND wc.public_id IN (' . $placeholders . ') AND wc.deleted_at IS NULL)',
                    $ids
                );
            }
        }

        if (!empty($filters['module_public_id'])) {
            $qb->whereRaw(
                'EXISTS (SELECT 1 FROM project_module_tasks pmt INNER JOIN project_modules pm ON pm.id = pmt.module_id WHERE pmt.task_id = t.id AND pmt.deleted_at IS NULL AND pm.public_id = ? AND pm.deleted_at IS NULL)',
                [(string)$filters['module_public_id']]
            );
        }

        if (!empty($filters['client_public_id'])) {
            $clientPublicId = (string)$filters['client_public_id'];
            $qb->whereRaw('(p.client_public_id = ? OR t.client_public_id = ?)', [$clientPublicId, $clientPublicId]);
        }

        // RLS for an executor-role external guest: scope tasks to the
        // project(s) they've been explicitly granted (external_user_project_
        // access), never by counterparty. Grants are project-level, so a
        // task is in scope purely by living in one of those projects —
        // mirrors the observer's "everything for my counterparty" breadth,
        // just narrowed to specific projects instead. Key presence (even []
        // ) means the executor scope is active; zero grants must return zero
        // rows, not an unscoped query.
        if (array_key_exists('executor_project_ids', $filters)) {
            $ids = array_values(array_filter(array_map('intval', (array)$filters['executor_project_ids']), static fn(int $v): bool => $v > 0));
            if ($ids === []) {
                $qb->whereRaw('1 = 0');
            } else {
                $qb->whereIn('p.id', $ids);
            }
        }

        if (!empty($filters['team_public_id'])) {
            $qb->where('p.team_public_id', '=', (string)$filters['team_public_id']);
        }

        if (!empty($filters['search'])) {
            $search = (string)$filters['search'];
            $term = '%' . $search . '%';
            // Check if search looks like a task key (e.g. CRM-123)
            $isTaskKeySearch = preg_match('/^[A-Za-z][A-Za-z0-9]{1,9}-[0-9]+$/', $search) === 1;
            $normalizedKey = strtoupper($search);
            if ($isTaskKeySearch) {
                $qb->whereRaw('(t.title LIKE ? OR t.description LIKE ? OR t.task_key = ?)', [$term, $term, $normalizedKey]);
            } else {
                $qb->whereRaw('(t.title LIKE ? OR t.description LIKE ? OR t.task_key LIKE ?)', [$term, $term, $term]);
            }
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
            // A plain date ('Y-m-d') means the start of that day; a full
            // timestamp ('Y-m-d H:i:s') is used as-is (client-computed, e.g.
            // for the overdue preset resolved in the user's own timezone).
            $dueFrom = trim((string)$filters['due_at_from']);
            $qb->where('t.due_at', '>=', strpos($dueFrom, ':') !== false ? $dueFrom : $dueFrom . ' 00:00:00');
        }

        if (!empty($filters['due_at_to'])) {
            $dueTo = trim((string)$filters['due_at_to']);
            $qb->where('t.due_at', '<=', strpos($dueTo, ':') !== false ? $dueTo : $dueTo . ' 23:59:59');
        }

        if (!empty($filters['due'])) {
            // Presets resolve in the app timezone (APP_TIMEZONE, default
            // Europe/Moscow) so they match what users see in the browser.
            $duePreset = strtolower(trim((string)$filters['due']));
            if ($duePreset === 'overdue') {
                // Due in the past and not finished (matches the old client-side logic).
                $qb->whereNotNull('t.due_at')
                    ->where('t.due_at', '<', date('Y-m-d H:i:s'))
                    ->whereRaw("t.status_code NOT IN ('done','completed','closed','archived')");
            } elseif ($duePreset === 'today') {
                $day = date('Y-m-d');
                $qb->whereNotNull('t.due_at')
                    ->where('t.due_at', '>=', $day . ' 00:00:00')
                    ->where('t.due_at', '<=', $day . ' 23:59:59');
            } elseif ($duePreset === 'week') {
                $day = date('Y-m-d');
                $weekEnd = date('Y-m-d', strtotime($day . ' +6 days'));
                $qb->whereNotNull('t.due_at')
                    ->where('t.due_at', '>=', $day . ' 00:00:00')
                    ->where('t.due_at', '<=', $weekEnd . ' 23:59:59');
            }
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

        // When the service layer signals that row-level security has already
        // been applied (an external user scoped to their counterparty or granted
        // projects), skip the team/creator/manager access gate. The rlsScoped
        // flag is set exclusively by the service layer — it MUST NOT be derived
        // from user-supplied filter keys, as that would let an internal user
        // bypass the gate by injecting client_public_id or executor_project_ids.

        if (!$actorIsRoot && !$rlsScoped && $actorUserId !== null && $actorUserId > 0) {
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

    /**
     * Splits a comma-separated filter value into a trimmed, non-empty list.
     * Supports both single values ('p1') and multi-select lists ('p1,p2').
     *
     * @return list<string>
     */
    private function splitFilterList(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $parts = array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn(string $part): bool => $part !== ''
        ));
        return $parts;
    }

    /**
     * True when a filter list contains a '__none'/'none' marker meaning "items
     * without a value" (e.g. tasks without an assignee/project/cycle/manager).
     *
     * @param list<string> $parts
     */
    private function listWantsNone(array $parts): bool
    {
        foreach ($parts as $part) {
            if (in_array(strtolower($part), ['none', 'unassigned', 'empty', '__none'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Filter list without the '__none'/'none' markers, keeping only real ids.
     *
     * @param list<string> $parts
     * @return list<string>
     */
    private function listWithoutNone(array $parts): array
    {
        return array_values(array_filter(
            $parts,
            static fn(string $part): bool => !in_array(strtolower($part), ['none', 'unassigned', 'empty', '__none'], true)
        ));
    }
}
