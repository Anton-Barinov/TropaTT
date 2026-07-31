<?php
declare(strict_types=1);

namespace Api\Model\Client;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class ClientCabinetRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listProjects(string $clientPublicId, array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $sort = in_array((string)($filters['sort'] ?? ''), ['title', 'created_at', 'updated_at'], true)
            ? (string)$filters['sort']
            : 'updated_at';
        $order = strtoupper((string)($filters['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $total = $this->buildProjectsListQuery($clientPublicId, $filters)->count();
        $items = $this->buildProjectsListQuery($clientPublicId, $filters)
            ->select([
                'p.public_id',
                'p.title',
                'p.description',
                'p.status_code',
                'p.priority_code',
                'p.client_public_id',
                'p.archived_at',
                'p.created_at',
                'p.updated_at',
                'p.row_version',
            ])
            ->orderBy('p.' . $sort, $order)
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    public function findProjectByPublicId(string $projectPublicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('projects')
            ->select(['public_id', 'title', 'description', 'status_code', 'priority_code', 'client_public_id', 'archived_at', 'created_at', 'updated_at', 'row_version'])
            ->where('public_id', '=', $projectPublicId)
            ->first();

        return $row ?: null;
    }

    public function listProjectTasks(string $projectPublicId, array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $sort = in_array((string)($filters['sort'] ?? ''), ['title', 'due_at', 'created_at', 'updated_at', 'status_code', 'priority_code'], true)
            ? (string)$filters['sort']
            : 'updated_at';
        $order = strtoupper((string)($filters['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $total = $this->buildProjectTasksListQuery($projectPublicId, $filters)->count();
        $items = $this->buildProjectTasksListQuery($projectPublicId, $filters)
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
            ])
            ->orderBy('t.' . $sort, $order)
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildProjectsListQuery(string $clientPublicId, array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('projects p')
            ->where('p.client_public_id', '=', $clientPublicId);

        if (($filters['archived'] ?? '0') !== '1') {
            $query->whereNull('p.archived_at');
        }

        if (!empty($filters['status'])) {
            $query->where('p.status_code', '=', (string)$filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . (string)$filters['search'] . '%';
            $query->whereRaw('(p.title LIKE ? OR p.description LIKE ?)', [$search, $search]);
        }

        return $query;
    }

    private function buildProjectTasksListQuery(string $projectPublicId, array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->join('projects p', 'p.id', '=', 't.project_id')
            ->where('p.public_id', '=', $projectPublicId)
            ->whereNull('t.deleted_at');

        if (($filters['archived'] ?? '0') !== '1') {
            $query->whereNull('t.archived_at');
        }

        if (!empty($filters['status'])) {
            $query->where('t.status_code', '=', (string)$filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->where('t.priority_code', '=', (string)$filters['priority']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . (string)$filters['search'] . '%';
            $query->whereRaw('(t.title LIKE ? OR t.description LIKE ?)', [$search, $search]);
        }

        return $query;
    }
}
