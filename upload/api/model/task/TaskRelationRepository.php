<?php
declare(strict_types=1);

namespace Api\Model\Task;

use Api\System\Library\Database\Builder\Expression;
use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;
use Api\System\Library\Support\LikeEscaper;
use Api\System\Library\Security\ActorPermission;

final class TaskRelationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * List all active relations for a task (as source or target).
     *
     * @return array<int,array<string,mixed>>
     */
    public function getTaskStatusDictionary(): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT code, title, color FROM statuses WHERE scope = :scope AND is_active = 1');
            $stmt->execute([':scope' => 'task']);
            $dict = [];
            foreach (($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) as $row) {
                $code = strtolower(trim((string)($row['code'] ?? '')));
                if ($code !== '') {
                    $dict[$code] = [
                        'title' => (string)($row['title'] ?? ''),
                        'color' => (string)($row['color'] ?? ''),
                    ];
                }
            }
            return $dict;
        } catch (\Throwable) {
            return [];
        }
    }

    public function listForTaskId(int $taskId): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('task_relations_v2 r')
            ->leftJoin('tasks t_source', 't_source.id', '=', 'r.source_task_id')
            ->leftJoin('tasks t_target', 't_target.id', '=', 'r.target_task_id')
            ->leftJoin('users cu', 'cu.id', '=', 'r.created_by_user_id')
            ->select([
                'r.id',
                'r.public_id',
                'r.source_task_id',
                'r.target_task_id',
                'r.relation_type',
                'r.active_key',
                'r.note',
                'r.created_by_user_id',
                'r.created_at',
                'r.updated_at',
                'r.deleted_at',
                'r.row_version',
                't_source.public_id AS source_task_public_id',
                't_source.task_key AS source_task_key',
                't_source.title AS source_task_title',
                't_source.status_code AS source_task_status_code',
                't_target.public_id AS target_task_public_id',
                't_target.task_key AS target_task_key',
                't_target.title AS target_task_title',
                't_target.status_code AS target_task_status_code',
                'cu.public_id AS created_by_user_public_id',
                'cu.full_name AS created_by_user_name',
            ])
            ->whereRaw('(r.source_task_id = ? OR r.target_task_id = ?)', [$taskId, $taskId])
            ->whereNull('r.deleted_at')
            ->orderBy('r.created_at', 'DESC')
            ->get();
    }

    /**
     * List all active relations for a task by public_id.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listForTaskPublicId(string $taskPublicId): array
    {
        $taskId = $this->taskIdByPublicId($taskPublicId);
        if ($taskId === null) {
            return [];
        }

        return $this->listForTaskId($taskId);
    }

    /**
     * Find a relation by its public_id.
     */
    public function findByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('task_relations_v2 r')
            ->leftJoin('tasks t_source', 't_source.id', '=', 'r.source_task_id')
            ->leftJoin('tasks t_target', 't_target.id', '=', 'r.target_task_id')
            ->leftJoin('users cu', 'cu.id', '=', 'r.created_by_user_id')
            ->select([
                'r.*',
                't_source.public_id AS source_task_public_id',
                't_source.task_key AS source_task_key',
                't_source.title AS source_task_title',
                't_source.status_code AS source_task_status_code',
                't_target.public_id AS target_task_public_id',
                't_target.task_key AS target_task_key',
                't_target.title AS target_task_title',
                't_target.status_code AS target_task_status_code',
                'cu.public_id AS created_by_user_public_id',
                'cu.full_name AS created_by_user_name',
            ])
            ->where('r.public_id', '=', $publicId)
            ->first();

        return $row;
    }

    /**
     * Create a new relation.
     */
    public function create(array $payload): array
    {
        $publicId = Ulid::generate('trl2');

        $now = gmdate('Y-m-d H:i:s');

        (new QueryBuilder($this->pdo))
            ->from('task_relations_v2')
            ->insert([
                'public_id' => $publicId,
                'source_task_id' => (int)($payload['source_task_id'] ?? 0),
                'target_task_id' => (int)($payload['target_task_id'] ?? 0),
                'relation_type' => (string)($payload['relation_type'] ?? ''),
                'active_key' => $payload['active_key'] ?? null,
                'note' => $payload['note'] ?? null,
                'created_by_user_id' => (int)($payload['created_by_user_id'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        $result = $this->findByPublicId($publicId);
        return $result ?? [];
    }

    /**
     * Soft delete a relation by public_id.
     */
    public function softDeleteByPublicId(string $publicId, string $deletedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('task_relations_v2')
            ->where('public_id', '=', $publicId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'active_key' => null,
                'updated_at' => $deletedAt,
                'row_version' => new Expression('row_version + 1'),
            ]) > 0;
    }

    /**
     * Soft-delete active relations attached to a task being deleted.
     *
     * Keeping these rows active would leave invisible relations behind: once
     * both endpoint tasks are soft-deleted, normal object-level checks prevent
     * anyone from deleting the relation individually.
     */
    public function softDeleteForTaskId(int $taskId, string $deletedAt): int
    {
        if ($taskId <= 0) {
            return 0;
        }

        return (new QueryBuilder($this->pdo))
            ->from('task_relations_v2')
            ->whereRaw('(source_task_id = ? OR target_task_id = ?)', [$taskId, $taskId])
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'active_key' => null,
                'updated_at' => $deletedAt,
                'row_version' => new Expression('row_version + 1'),
            ]);
    }

    /**
     * Check if a relation with given active_key already exists (non-deleted).
     */
    public function existsByActiveKey(string $activeKey): bool
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('task_relations_v2')
            ->select(['id'])
            ->where('active_key', '=', $activeKey)
            ->whereNull('deleted_at')
            ->first();

        return $row !== null;
    }

    /**
     * Can `$fromId` already reach `$toId` via active relations of `$type`?
     *
     * Used to refuse a new directed edge that would close a cycle (e.g. A
     * blocked_by B while B is already blocked_by A, directly or transitively).
     * Relations are stored directed source_task_id -> target_task_id, so the
     * new edge is safe unless $toId already reaches $fromId.
     */
    public function reachesByType(int $fromId, int $toId, string $type): bool
    {
        if ($fromId === $toId) {
            return true;
        }

        $visited = [];
        $stack = [$fromId];
        while ($stack !== []) {
            $current = (int)array_pop($stack);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            foreach ($this->outgoingIdsByType($current, $type) as $next) {
                if ($next === $toId) {
                    return true;
                }
                if (!isset($visited[$next])) {
                    $stack[] = $next;
                }
            }
        }

        return false;
    }

    /** @return list<int> */
    private function outgoingIdsByType(int $taskId, string $type): array
    {
        $rows = (new QueryBuilder($this->pdo))
            ->from('task_relations_v2')
            ->select(['target_task_id'])
            ->where('source_task_id', '=', $taskId)
            ->where('relation_type', '=', $type)
            ->whereNull('deleted_at')
            ->get();

        return array_map(static fn(array $row): int => (int)$row['target_task_id'], $rows);
    }

    /**
     * Find task internal ID by public_id.
     */
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

    /**
     * Find task internal ID by task_key.
     */
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

    /**
     * Search tasks by public_id, task_key, or title.
     *
     * @return array<int,array<string,mixed>>
     */
    public function searchTasks(string $query, array $actor, int $limit = 20, string $projectPublicId = ''): array
    {
        $safeLimit = min(50, max(1, $limit));
        $parsed = $this->parseSearchQuery($query);
        $hasTaskKey = $this->hasColumn('tasks', 'task_key');
        $hasSeqNum = $this->hasColumn('tasks', 'task_sequence_number');

        $selects = [
            't.public_id',
            't.title',
            't.status_code',
            'p.public_id AS project_public_id',
            'p.title AS project_title',
        ];
        if ($hasTaskKey) {
            $selects[] = 't.task_key';
        }

        $qb = (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->select($selects)
            ->whereNull('t.deleted_at')
            ->whereNull('t.archived_at');

        if ($projectPublicId !== '') {
            $qb->where('p.public_id', '=', $projectPublicId);
        }

        $conditions = [];
        $params = [];
        $term = '%' . LikeEscaper::escape($parsed['raw']) . '%';
        $conditions[] = 't.title LIKE ?';
        $params[] = $term;

        if ($parsed['clean'] !== '' && $parsed['clean'] !== $parsed['raw']) {
            $cleanTerm = '%' . LikeEscaper::escape($parsed['clean']) . '%';
            $conditions[] = 't.title LIKE ?';
            $params[] = $cleanTerm;
        }

        $isPublicIdSearch = preg_match('/^[a-z]{3}_/', $parsed['raw']) === 1;

        if ($parsed['canonical_key'] !== null) {
            if ($hasTaskKey) {
                $conditions[] = 't.task_key = ?';
                $params[] = $parsed['canonical_key'];
                $conditions[] = 't.task_key LIKE ?';
                $params[] = $parsed['canonical_key'] . '%';
            }
            if ($hasSeqNum && $parsed['prefix'] !== null && $parsed['sequence'] !== null) {
                $conditions[] = '(t.task_key_prefix = ? AND t.task_sequence_number = ?)';
                $params[] = $parsed['prefix'];
                $params[] = $parsed['sequence'];
            }
        } elseif ($parsed['numeric_id'] !== null) {
            $conditions[] = 't.id = ?';
            $params[] = $parsed['numeric_id'];
            if ($hasSeqNum) {
                $conditions[] = 't.task_sequence_number = ?';
                $params[] = $parsed['numeric_id'];
            }
            if ($hasTaskKey) {
                $conditions[] = 't.task_key = ?';
                $params[] = (string)$parsed['numeric_id'];
                $conditions[] = 't.task_key LIKE ?';
                $params[] = '%-' . $parsed['numeric_id'];
            }
        } elseif ($isPublicIdSearch) {
            $conditions[] = 't.public_id LIKE ?';
            $params[] = $term;
            if ($hasTaskKey) {
                $conditions[] = 't.task_key LIKE ?';
                $params[] = $term;
            }
        } else {
            if ($hasTaskKey) {
                $conditions[] = 't.task_key LIKE ?';
                $params[] = $term;
            }
        }

        $qb->whereRaw('(' . implode(' OR ', $conditions) . ')', $params);

        // Apply access filter similar to TaskRepository
        $actorUserId = (int)($actor['id'] ?? 0);
        $actorIsRoot = (bool)($actor['is_root'] ?? false);

        // Permission = capability: task.manage holders pick any task of their
        // workspace as a relation target (the flag comes from the actor only).
        if (!$actorIsRoot && !ActorPermission::canManageTasks($actor) && $actorUserId > 0) {
            $qb->whereRaw(
                '(t.creator_user_id = ? OR t.assignee_user_id = ? OR p.created_by_user_id = ? OR p.manager_user_id = ?)',
                [$actorUserId, $actorUserId, $actorUserId, $actorUserId]
            );
        }

        // Workspace boundary: rows predating organization scoping stay visible,
        // an actor bound to a workspace never sees a foreign one.
        $organizationId = (int)($actor['organization_id'] ?? 0);
        if ($organizationId > 0) {
            $qb->whereRaw('(t.organization_id IS NULL OR t.organization_id = ?)', [$organizationId]);
        } elseif (!\Api\System\Library\Security\ActorPermission::isPrivileged($actor)) {
            $qb->whereRaw('1 = 0');
        }

        $this->applyTaskSearchRelevanceOrder($qb, $query);

        return $qb
            ->orderBy('t.updated_at', 'DESC')
            ->limit($safeLimit)
            ->get();
    }

    /**
     * List subtasks for a parent task from the intrinsic hierarchy table (task_relations).
     *
     * @return array<int,array<string,mixed>>
     */
    public function listHierarchySubtasks(int $parentTaskId): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('task_relations tr')
            ->join('tasks t', 't.id', '=', 'tr.child_task_id')
            ->leftJoin('users u', 'u.id', '=', 't.assignee_user_id')
            ->select([
                't.id',
                't.public_id',
                't.task_key',
                't.title',
                't.status_code',
                't.priority_code',
                'u.full_name AS assignee_name',
                'u.public_id AS assignee_user_public_id',
                't.created_at',
                'tr.public_id AS relation_public_id',
            ])
            ->where('tr.parent_task_id', '=', $parentTaskId)
            ->where('tr.relation_type', '=', 'subtask')
            ->whereNull('t.deleted_at')
            ->orderBy('tr.sort_order', 'ASC')
            ->orderBy('t.created_at', 'ASC')
            ->get();
    }

    /**
     * Find parent task for a subtask from the intrinsic hierarchy table (task_relations).
     *
     * @return array<string,mixed>|null
     */
    public function findHierarchyParent(int $childTaskId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('task_relations tr')
            ->join('tasks t', 't.id', '=', 'tr.parent_task_id')
            ->leftJoin('users u', 'u.id', '=', 't.assignee_user_id')
            ->select([
                't.id',
                't.public_id',
                't.task_key',
                't.title',
                't.status_code',
                't.priority_code',
                'u.full_name AS assignee_name',
                'u.public_id AS assignee_user_public_id',
                't.created_at',
                'tr.public_id AS relation_public_id',
            ])
            ->where('tr.child_task_id', '=', $childTaskId)
            ->where('tr.relation_type', '=', 'subtask')
            ->whereNull('t.deleted_at')
            ->first();

        return $row ?? null;
    }

    /**
     * Insert a parent-child hierarchy link into task_relations if not present.
     */
    public function createHierarchyRelation(int $parentId, int $childId, ?int $orgId = null): void
    {
        $now = gmdate('Y-m-d H:i:s');
        (new QueryBuilder($this->pdo))
            ->from('task_relations')
            ->insert([
                'public_id' => Ulid::generate('trl'),
                'parent_task_id' => $parentId,
                'child_task_id' => $childId,
                'relation_type' => 'subtask',
                'sort_order' => 100,
                'created_at' => $now,
                'updated_at' => $now,
                'organization_id' => $orgId,
            ]);
    }

    private function hasColumn(string $table, string $column): bool
    {
        static $columns = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $columns)) {
            return $columns[$key];
        }
        try {
            $driver = (string)$this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->query('PRAGMA table_info(' . $table . ')');
                foreach ($stmt?->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                    if ((string)($row['name'] ?? '') === $column) {
                        return $columns[$key] = true;
                    }
                }
                return $columns[$key] = false;
            }
            $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :col LIMIT 1');
            $stmt->execute(['table' => $table, 'col' => $column]);
            return $columns[$key] = $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return $columns[$key] = false;
        }
    }

    /**
     * @return array{
     *   raw: string,
     *   clean: string,
     *   canonical_key: ?string,
     *   prefix: ?string,
     *   sequence: ?int,
     *   numeric_id: ?int
     * }
     */
    private function parseSearchQuery(string $search): array
    {
        $raw = trim($search);
        // Strip leading # or № and optional space
        $clean = preg_replace('/^[#№]\s*/u', '', $raw) ?? $raw;
        $clean = trim($clean);

        $canonicalKey = null;
        $prefix = null;
        $sequence = null;
        $numericId = null;

        // Structured key pattern: e.g. "CRM-123", "CRM 123", "CRM_123", "crm-123"
        if (preg_match('/^([A-Za-z][A-Za-z0-9]{0,9})[\s\-_#]+([0-9]+)$/u', $clean, $matches)) {
            $prefix = strtoupper($matches[1]);
            $sequence = (int)$matches[2];
            $canonicalKey = $prefix . '-' . $sequence;
        } elseif (preg_match('/^[A-Za-z][A-Za-z0-9]{0,9}-[0-9]+$/', $clean)) {
            $canonicalKey = strtoupper($clean);
            $parts = explode('-', $canonicalKey, 2);
            $prefix = $parts[0];
            $sequence = (int)$parts[1];
        } elseif (ctype_digit($clean)) {
            $numericId = (int)$clean;
            $sequence = $numericId;
        }

        return [
            'raw' => $raw,
            'clean' => $clean,
            'canonical_key' => $canonicalKey,
            'prefix' => $prefix,
            'sequence' => $sequence,
            'numeric_id' => $numericId,
        ];
    }

    private function applyTaskSearchRelevanceOrder(QueryBuilder $builder, string $search): void
    {
        $parsed = $this->parseSearchQuery($search);
        $hasTaskKey = $this->hasColumn('tasks', 'task_key');
        $hasSeqNum = $this->hasColumn('tasks', 'task_sequence_number');

        if ($parsed['canonical_key'] !== null) {
            $quotedKey = $this->pdo->quote($parsed['canonical_key']);
            $quotedPrefix = $this->pdo->quote($parsed['canonical_key'] . '%');
            $cases = [];
            if ($hasTaskKey) {
                $cases[] = "WHEN t.task_key = {$quotedKey} THEN 0";
                $cases[] = "WHEN t.task_key LIKE {$quotedPrefix} THEN 1";
            }
            if ($hasSeqNum && $parsed['prefix'] !== null && $parsed['sequence'] !== null) {
                $quotedPrefixCol = $this->pdo->quote($parsed['prefix']);
                $seq = (int)$parsed['sequence'];
                $cases[] = "WHEN t.task_key_prefix = {$quotedPrefixCol} AND t.task_sequence_number = {$seq} THEN 0";
            }
            if ($cases !== []) {
                $builder->orderByRaw("CASE " . implode(' ', $cases) . " ELSE 2 END ASC");
            }
        } elseif ($parsed['numeric_id'] !== null) {
            $num = (int)$parsed['numeric_id'];
            $quotedKeyEnds = $this->pdo->quote('%-' . $num);
            $cases = [];
            if ($hasSeqNum) {
                $cases[] = "WHEN t.task_sequence_number = {$num} THEN 0";
            }
            $cases[] = "WHEN t.id = {$num} THEN 1";
            if ($hasTaskKey) {
                $cases[] = "WHEN t.task_key LIKE {$quotedKeyEnds} THEN 2";
            }
            if ($cases !== []) {
                $builder->orderByRaw("CASE " . implode(' ', $cases) . " ELSE 3 END ASC");
            }
        } else {
            $clean = trim($parsed['clean']);
            if ($clean !== '') {
                $quotedExact = $this->pdo->quote($clean);
                $quotedPrefix = $this->pdo->quote($clean . '%');
                $builder->orderByRaw("CASE WHEN t.title = {$quotedExact} THEN 0 WHEN t.title LIKE {$quotedPrefix} THEN 1 ELSE 2 END ASC");
            }
        }
    }
}
