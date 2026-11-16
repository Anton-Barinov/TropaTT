<?php
declare(strict_types=1);

namespace Api\Model\Search;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\LikeEscaper;
use PDO;

final class SearchRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function searchTasks(string $query, int $limit, int $actorUserId, bool $actorIsRoot, ?int $organizationId = null, bool $canManageAll = false): array
    {
        $selects = [
            't.public_id',
            't.title',
            't.status_code',
            't.priority_code',
            't.due_at',
            'p.public_id AS project_public_id',
            'p.title AS project_title',
        ];
        if ($this->hasColumn('tasks', 'task_key')) {
            $selects[] = 't.task_key';
        }

        $qb = $this->buildTasksQuery($query, $actorUserId, $actorIsRoot, $organizationId, $canManageAll)
            ->select($selects);

        $this->applyTaskSearchRelevanceOrder($qb, $query);

        return $qb
            ->orderBy('t.updated_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function searchProjects(string $query, int $limit, int $actorUserId, bool $actorIsRoot, ?int $organizationId = null, bool $canManageAll = false): array
    {
        return $this->buildProjectsQuery($query, $actorUserId, $actorIsRoot, $organizationId, $canManageAll)
            ->select([
                'p.public_id',
                'p.title',
                'p.status_code',
                'p.priority_code',
                'p.updated_at',
            ])
            ->orderBy('p.updated_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Поиск по counterparties (унифицировано: клиенты + компании).
     * @param string[]|null $typeFilter Фильтр по counterparty_type
     * @param int[] $createdByUserIds Scope-фильтр: пустой массив для root (все),
     *                                иначе — только записи этих создателей (владелец + иерархия).
     */
    public function searchCounterparties(string $query, int $limit, ?array $typeFilter = null, array $createdByUserIds = [], ?int $organizationId = null): array
    {
        $qb = $this->buildCounterpartyQuery($query, $createdByUserIds, $organizationId);

        if ($typeFilter !== null && $typeFilter !== []) {
            $placeholders = implode(',', array_fill(0, count($typeFilter), '?'));
            $qb->whereRaw('cp.counterparty_type IN (' . $placeholders . ')', $typeFilter);
        }

        return $qb
            ->select([
                'cp.public_id',
                'cp.title',
                'cp.counterparty_type',
                'cp.legal_name',
                'cp.tax_inn',
                'cp.website',
                'cp.email',
                'cp.phone',
                'cp.status',
            ])
            ->orderBy('cp.updated_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Legacy: поиск по clients (теперь ищет в counterparties с type filter).
     * @deprecated Используйте searchCounterparties()
     * @param int[] $createdByUserIds Scope-фильтр, см. searchCounterparties()
     */
    public function searchClients(string $query, int $limit, array $createdByUserIds = [], ?int $organizationId = null): array
    {
        return $this->searchCounterparties($query, $limit, ['individual', 'sole_proprietor', 'legal_entity'], $createdByUserIds, $organizationId);
    }

    /**
     * Legacy: поиск по companies (теперь ищет в counterparties с type filter).
     * @deprecated Используйте searchCounterparties()
     * @param int[] $createdByUserIds Scope-фильтр, см. searchCounterparties()
     */
    public function searchCompanies(string $query, int $limit, array $createdByUserIds = [], ?int $organizationId = null): array
    {
        return $this->searchCounterparties($query, $limit, ['organization'], $createdByUserIds, $organizationId);
    }

    /**
     * @param int[] $createdByUserIds Scope-фильтр: пустой массив для root (все),
     *                                иначе — только контакты этих создателей.
     */
    public function searchContacts(string $query, int $limit, array $createdByUserIds = [], ?int $organizationId = null): array
    {
        return $this->buildContactsQuery($query, $createdByUserIds, $organizationId)
            ->select([
                'ct.public_id',
                'ct.full_name',
                'ct.email',
                'ct.phone',
                'ct.role',
                'cp.public_id AS counterparty_public_id',
                'cp.title AS counterparty_title',
                'cp.counterparty_type',
            ])
            ->orderBy('ct.updated_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    private function buildTasksQuery(string $query, int $actorUserId, bool $actorIsRoot, ?int $organizationId = null, bool $canManageAll = false): QueryBuilder
    {
        $parsed = $this->parseSearchQuery($query);
        $hasTaskKey = $this->hasColumn('tasks', 'task_key');
        $hasSeqNum = $this->hasColumn('tasks', 'task_sequence_number');

        $conditions = [];
        $params = [];

        $term = '%' . LikeEscaper::escape($parsed['raw']) . '%';
        $conditions[] = 't.title LIKE ?';
        $params[] = $term;
        $conditions[] = 't.description LIKE ?';
        $params[] = $term;

        if ($parsed['clean'] !== '' && $parsed['clean'] !== $parsed['raw']) {
            $cleanTerm = '%' . LikeEscaper::escape($parsed['clean']) . '%';
            $conditions[] = 't.title LIKE ?';
            $params[] = $cleanTerm;
            $conditions[] = 't.description LIKE ?';
            $params[] = $cleanTerm;
        }

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
        } else {
            if ($hasTaskKey) {
                $conditions[] = 't.task_key LIKE ?';
                $params[] = $term;
            }
        }

        $qb = (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->whereNull('t.deleted_at')
            ->whereNull('t.archived_at')
            ->whereRaw('(' . implode(' OR ', $conditions) . ')', $params);

        // Permission = capability: task.manage holders search every task of
        // their workspace (the flag comes from the actor, never from input).
        if (!$actorIsRoot && !$canManageAll) {
            $qb->whereRaw(
                '(t.creator_user_id = ? OR t.assignee_user_id = ? OR p.created_by_user_id = ? OR p.manager_user_id = ?)',
                [$actorUserId, $actorUserId, $actorUserId, $actorUserId]
            );
        }

        $this->applyOrganizationScope($qb, 'tasks', 't', $organizationId);

        return $qb;
    }

    private function buildProjectsQuery(string $query, int $actorUserId, bool $actorIsRoot, ?int $organizationId = null, bool $canManageAll = false): QueryBuilder
    {
        $like = '%' . LikeEscaper::escape($query) . '%';
        $qb = (new QueryBuilder($this->pdo))
            ->from('projects p')
            ->whereNull('p.archived_at')
            ->whereRaw('(p.title LIKE ? OR p.description LIKE ?)', [$like, $like]);

        // Permission = capability: project.manage / task.manage search every
        // project of their workspace.
        if (!$actorIsRoot && !$canManageAll) {
            $qb->whereRaw(
                '(p.created_by_user_id = ? OR p.manager_user_id = ?)',
                [$actorUserId, $actorUserId]
            );
        }

        $this->applyOrganizationScope($qb, 'projects', 'p', $organizationId);

        return $qb;
    }

    /** @param int[] $createdByUserIds */
    private function buildCounterpartyQuery(string $query, array $createdByUserIds = [], ?int $organizationId = null): QueryBuilder
    {
        $like = '%' . LikeEscaper::escape($query) . '%';

        $qb = (new QueryBuilder($this->pdo))
            ->from('counterparties cp')
            ->whereRaw('(cp.title LIKE ? OR cp.legal_name LIKE ? OR cp.tax_inn LIKE ? OR cp.website LIKE ? OR cp.email LIKE ? OR cp.phone LIKE ?)', [$like, $like, $like, $like, $like, $like]);

        $this->applyCreatorScope($qb, 'cp', $createdByUserIds);
        $this->applyOrganizationScope($qb, 'counterparties', 'cp', $organizationId);

        return $qb;
    }

    public function searchKnowledge(string $query, int $limit): array
    {
        return $this->buildKnowledgeQuery($query)
            ->select([
                'kp.public_id',
                'kp.title',
                'ks.title AS space_title',
                'kp.status',
                'kp.page_type',
                'kp.updated_at',
            ])
            ->orderBy('kp.updated_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    private function buildKnowledgeQuery(string $query): QueryBuilder
    {
        $like = '%' . LikeEscaper::escape($query) . '%';

        return (new QueryBuilder($this->pdo))
            ->from('knowledge_pages kp')
            ->leftJoin('knowledge_spaces ks', 'ks.id', '=', 'kp.space_id')
            ->whereNull('kp.deleted_at')
            ->where('kp.status', '=', 'published')
            ->whereRaw('(kp.title LIKE ? OR kp.content_text LIKE ? OR ks.title LIKE ?)', [$like, $like, $like]);
    }

    /** @param int[] $createdByUserIds */
    private function buildContactsQuery(string $query, array $createdByUserIds = [], ?int $organizationId = null): QueryBuilder
    {
        $like = '%' . LikeEscaper::escape($query) . '%';

        $qb = (new QueryBuilder($this->pdo))
            ->from('contacts ct')
            ->leftJoin('counterparties cp', 'cp.id', '=', 'ct.counterparty_id')
            ->whereRaw('(ct.full_name LIKE ? OR ct.email LIKE ? OR ct.phone LIKE ?)', [$like, $like, $like]);

        $this->applyCreatorScope($qb, 'ct', $createdByUserIds);
        $this->applyOrganizationScope($qb, 'contacts', 'ct', $organizationId);

        return $qb;
    }

    /**
     * Multi-tenant workspace boundary: filters the query to the actor's own
     * organization. $organizationId === null means "no boundary to apply" and
     * is used ONLY for a true platform superadmin
     * (is_root && organization_id <= 0, see SearchService::isTrueRoot()).
     * Every other actor must always pass a value here — including a
     * non-positive sentinel (e.g. -1 for an actor with no organization),
     * which must NOT be treated as "no filter": it is applied as-is so the
     * query matches nothing, the fail-closed behaviour for an actor without a
     * valid organization (mirrors applyCreatorScope()'s -1 sentinel).
     *
     * Fail-closed: if the target table does not (yet) have an
     * organization_id column, the query is made to match nothing rather than
     * silently falling back to unscoped results (mirrors
     * TaskRepository::applyOrganizationScope()).
     */
    private function applyOrganizationScope(QueryBuilder $qb, string $table, string $alias, ?int $organizationId): void
    {
        if ($organizationId === null) {
            return;
        }
        if (!$this->hasOrganizationColumn($table)) {
            $qb->whereRaw('1 = 0');
            return;
        }
        $qb->where($alias . '.organization_id', '=', $organizationId);
    }

    private function hasColumn(string $table, string $column): bool
    {
        static $columns = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $columns)) {
            return $columns[$key];
        }
        try {
            $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->query('PRAGMA table_info(' . $table . ')');
                foreach ($stmt?->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
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

    private function hasOrganizationColumn(string $table): bool
    {
        return $this->hasColumn($table, 'organization_id');
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

    /**
     * Fail-closed object scope for search: an empty list means "no scope"
     * (used for root users who may see everything). A non-empty list restricts
     * rows to the given creators, mirroring CounterpartyService/ContactService.
     *
     * Important: a non-positive sentinel (e.g. -1 from an anonymous actor) must
     * NOT be discarded — keeping it makes the IN (...) match nothing, which is
     * the fail-closed behaviour for users without a valid identity.
     *
     * @param int[] $createdByUserIds
     */
    private function applyCreatorScope(QueryBuilder $qb, string $tableAlias, array $createdByUserIds): void
    {
        $creatorIds = array_values(array_unique(array_map('intval', $createdByUserIds)));

        if ($creatorIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($creatorIds), '?'));
        $qb->whereRaw($tableAlias . '.created_by_user_id IN (' . $placeholders . ')', $creatorIds);
    }
}
