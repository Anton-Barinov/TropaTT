<?php
declare(strict_types=1);

namespace Api\Model\Search;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class SearchRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function searchTasks(string $query, int $limit, int $actorUserId, bool $actorIsRoot): array
    {
        return $this->buildTasksQuery($query, $actorUserId, $actorIsRoot)
            ->select([
                't.public_id',
                't.title',
                't.status_code',
                't.priority_code',
                't.due_at',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
            ])
            ->orderBy('t.updated_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function searchProjects(string $query, int $limit, int $actorUserId, bool $actorIsRoot): array
    {
        return $this->buildProjectsQuery($query, $actorUserId, $actorIsRoot)
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
     */
    public function searchCounterparties(string $query, int $limit, ?array $typeFilter = null): array
    {
        $qb = $this->buildCounterpartyQuery($query);

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
     */
    public function searchClients(string $query, int $limit): array
    {
        return $this->searchCounterparties($query, $limit, ['individual', 'sole_proprietor', 'legal_entity']);
    }

    /**
     * Legacy: поиск по companies (теперь ищет в counterparties с type filter).
     * @deprecated Используйте searchCounterparties()
     */
    public function searchCompanies(string $query, int $limit): array
    {
        return $this->searchCounterparties($query, $limit, ['organization']);
    }

    public function searchContacts(string $query, int $limit): array
    {
        return $this->buildContactsQuery($query)
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

    private function buildTasksQuery(string $query, int $actorUserId, bool $actorIsRoot): QueryBuilder
    {
        $like = '%' . $query . '%';
        $qb = (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->whereNull('t.deleted_at')
            ->whereNull('t.archived_at')
            ->whereRaw('(t.title LIKE ? OR t.description LIKE ?)', [$like, $like]);

        if (!$actorIsRoot) {
            $qb->whereRaw(
                '(t.creator_user_id = ? OR t.assignee_user_id = ? OR p.created_by_user_id = ? OR p.manager_user_id = ?)',
                [$actorUserId, $actorUserId, $actorUserId, $actorUserId]
            );
        }

        return $qb;
    }

    private function buildProjectsQuery(string $query, int $actorUserId, bool $actorIsRoot): QueryBuilder
    {
        $like = '%' . $query . '%';
        $qb = (new QueryBuilder($this->pdo))
            ->from('projects p')
            ->whereNull('p.archived_at')
            ->whereRaw('(p.title LIKE ? OR p.description LIKE ?)', [$like, $like]);

        if (!$actorIsRoot) {
            $qb->whereRaw(
                '(p.created_by_user_id = ? OR p.manager_user_id = ?)',
                [$actorUserId, $actorUserId]
            );
        }

        return $qb;
    }

    private function buildCounterpartyQuery(string $query): QueryBuilder
    {
        $like = '%' . $query . '%';

        return (new QueryBuilder($this->pdo))
            ->from('counterparties cp')
            ->whereRaw('(cp.title LIKE ? OR cp.legal_name LIKE ? OR cp.tax_inn LIKE ? OR cp.website LIKE ? OR cp.email LIKE ? OR cp.phone LIKE ?)', [$like, $like, $like, $like, $like, $like]);
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
        $like = '%' . $query . '%';

        return (new QueryBuilder($this->pdo))
            ->from('knowledge_pages kp')
            ->leftJoin('knowledge_spaces ks', 'ks.id', '=', 'kp.space_id')
            ->whereNull('kp.deleted_at')
            ->where('kp.status', '=', 'published')
            ->whereRaw('(kp.title LIKE ? OR kp.content_text LIKE ? OR ks.title LIKE ?)', [$like, $like, $like]);
    }

    private function buildContactsQuery(string $query): QueryBuilder
    {
        $like = '%' . $query . '%';

        return (new QueryBuilder($this->pdo))
            ->from('contacts ct')
            ->leftJoin('counterparties cp', 'cp.id', '=', 'ct.counterparty_id')
            ->whereRaw('(ct.full_name LIKE ? OR ct.email LIKE ? OR ct.phone LIKE ?)', [$like, $like, $like]);
    }
}
