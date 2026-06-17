<?php
declare(strict_types=1);

namespace Api\Model\Project;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class ProjectModuleRepository
{
    private const ALLOWED_SORT = ['sort_order', 'title', 'status', 'target_at', 'created_at', 'updated_at'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters, int $actorUserId, bool $isRoot): array
    {
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $sort = in_array(($filters['sort'] ?? ''), self::ALLOWED_SORT, true) ? (string)$filters['sort'] : 'sort_order';
        $order = strtoupper((string)($filters['order'] ?? '')) === 'DESC' ? 'DESC' : 'ASC';

        $qb = (new QueryBuilder($this->pdo))
            ->from('project_modules pm')
            ->leftJoin('users u', 'u.id', '=', 'pm.lead_user_id')
            ->leftJoin('projects p', 'p.id', '=', 'pm.project_id')
            ->select([
                'pm.*',
                'u.public_id AS lead_user_public_id',
                'u.full_name AS lead_name',
                'u.email AS lead_email',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
            ])
            ->whereNull('pm.deleted_at');

        // Filter by project
        if (!empty($filters['project_public_id'])) {
            $projectId = $this->projectIdByPublicId((string)$filters['project_public_id']);
            if ($projectId !== null) {
                $qb->where('pm.project_id', '=', $projectId);
            }
        }

        // Filter by status
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'archived') {
                $qb->whereNotNull('pm.archived_at');
            } elseif ($filters['status'] === 'active') {
                $qb->whereNull('pm.archived_at');
            } else {
                $qb->where('pm.status', '=', (string)$filters['status']);
            }
        }

        // Filter by lead
        if (!empty($filters['lead_user_public_id'])) {
            $leadId = $this->userIdByPublicId((string)$filters['lead_user_public_id']);
            if ($leadId !== null) {
                $qb->where('pm.lead_user_id', '=', $leadId);
            }
        }

        // Search
        if (!empty($filters['q'])) {
            $q = '%' . (string)$filters['q'] . '%';
            $qb->whereRaw('(pm.title LIKE ? OR pm.description LIKE ?)', [$q, $q]);
        }

        // Date filters
        if (!empty($filters['target_from'])) {
            $qb->where('pm.target_at', '>=', (string)$filters['target_from']);
        }
        if (!empty($filters['target_to'])) {
            $qb->where('pm.target_at', '<=', (string)$filters['target_to']);
        }

        // Archived filter
        $archivedFilter = $filters['archived'] ?? null;
        if ($archivedFilter === '1' || $archivedFilter === 'true') {
            $qb->whereNotNull('pm.archived_at');
        } elseif ($archivedFilter === '0' || $archivedFilter === 'false' || $archivedFilter === null) {
            $qb->whereNull('pm.archived_at');
        }

        $total = $qb->count();

        $items = (clone $qb)
            ->orderBy("pm.{$sort}", $order)
            ->orderBy('pm.id', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        // Enrich with counts
        foreach ($items as &$item) {
            $moduleId = (int)$item['id'];
            $item['tasks_count'] = (int)(new QueryBuilder($this->pdo))
                ->from('project_module_tasks')
                ->where('module_id', '=', $moduleId)
                ->whereNull('deleted_at')
                ->count();
            $item['completed_tasks_count'] = (int)(new QueryBuilder($this->pdo))
                ->from('project_module_tasks pmt')
                ->leftJoin('tasks t', 't.id', '=', 'pmt.task_id')
                ->where('pmt.module_id', '=', $moduleId)
                ->whereNull('pmt.deleted_at')
                ->whereNull('t.deleted_at')
                ->whereRaw('t.status_code IN (?, ?, ?)', ['done', 'closed', 'archived'])
                ->count();
            $item['members_count'] = (int)(new QueryBuilder($this->pdo))
                ->from('project_module_members')
                ->where('module_id', '=', $moduleId)
                ->whereNull('deleted_at')
                ->count();
            $item['links_count'] = (int)(new QueryBuilder($this->pdo))
                ->from('project_module_links')
                ->where('module_id', '=', $moduleId)
                ->whereNull('deleted_at')
                ->count();
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function findByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('project_modules pm')
            ->leftJoin('users u', 'u.id', '=', 'pm.lead_user_id')
            ->leftJoin('projects p', 'p.id', '=', 'pm.project_id')
            ->select([
                'pm.*',
                'u.public_id AS lead_user_public_id',
                'u.full_name AS lead_name',
                'u.email AS lead_email',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
            ])
            ->where('pm.public_id', '=', $publicId)
            ->whereNull('pm.deleted_at')
            ->first();

        if ($row === null || $row === false) {
            return null;
        }

        $moduleId = (int)$row['id'];

        $row['tasks_count'] = (int)(new QueryBuilder($this->pdo))
            ->from('project_module_tasks')
            ->where('module_id', '=', $moduleId)
            ->whereNull('deleted_at')
            ->count();

        $row['completed_tasks_count'] = (int)(new QueryBuilder($this->pdo))
            ->from('project_module_tasks pmt')
            ->leftJoin('tasks t', 't.id', '=', 'pmt.task_id')
            ->where('pmt.module_id', '=', $moduleId)
            ->whereNull('pmt.deleted_at')
            ->whereNull('t.deleted_at')
            ->whereRaw('t.status_code IN (?, ?, ?)', ['done', 'closed', 'archived'])
            ->count();

        $row['members_count'] = (int)(new QueryBuilder($this->pdo))
            ->from('project_module_members')
            ->where('module_id', '=', $moduleId)
            ->whereNull('deleted_at')
            ->count();

        $row['links_count'] = (int)(new QueryBuilder($this->pdo))
            ->from('project_module_links')
            ->where('module_id', '=', $moduleId)
            ->whereNull('deleted_at')
            ->count();

        return $row;
    }

    public function findById(int $id): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('project_modules')
            ->where('id', '=', $id)
            ->whereNull('deleted_at')
            ->first();

        return $row !== false ? $row : null;
    }

    public function create(array $payload): array
    {
        (new QueryBuilder($this->pdo))
            ->from('project_modules')
            ->insert($payload);

        return $payload;
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('project_modules')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function archiveByPublicId(string $publicId, string $archivedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('project_modules')
            ->where('public_id', '=', $publicId)
            ->whereNull('deleted_at')
            ->update([
                'archived_at' => $archivedAt,
                'status' => 'archived',
                'updated_at' => $archivedAt,
            ]) > 0;
    }

    public function softDeleteByPublicId(string $publicId, string $deletedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('project_modules')
            ->where('public_id', '=', $publicId)
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]) > 0;
    }

    public function projectIdByPublicId(string $projectPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('projects')
            ->select(['id'])
            ->where('public_id', '=', $projectPublicId)
            ->whereNull('archived_at')
            ->first();

        return isset($row['id']) ? (int)$row['id'] : null;
    }

    public function userIdByPublicId(string $userPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['id'])
            ->where('public_id', '=', $userPublicId)
            ->whereNull('deleted_at')
            ->first();

        return isset($row['id']) ? (int)$row['id'] : null;
    }
}
