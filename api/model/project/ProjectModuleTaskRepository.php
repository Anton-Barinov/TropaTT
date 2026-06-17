<?php
declare(strict_types=1);

namespace Api\Model\Project;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;

final class ProjectModuleTaskRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listTasksByModuleId(int $moduleId, array $filters = []): array
    {
        $limit = min(500, max(1, (int)($filters['limit'] ?? 100)));
        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $qb = (new QueryBuilder($this->pdo))
            ->from('project_module_tasks pmt')
            ->leftJoin('tasks t', 't.id', '=', 'pmt.task_id')
            ->leftJoin('users u', 'u.id', '=', 't.assignee_user_id')
            ->select([
                'pmt.public_id AS module_task_public_id',
                'pmt.added_by_user_id',
                'pmt.added_at',
                'pmt.sort_order',
                't.public_id AS task_public_id',
                't.title AS task_title',
                't.status_code AS task_status',
                't.priority_code AS task_priority',
                't.due_at AS task_due_at',
                'u.public_id AS assignee_user_public_id',
                'u.full_name AS assignee_name',
            ])
            ->where('pmt.module_id', '=', $moduleId)
            ->whereNull('pmt.deleted_at')
            ->whereNull('t.deleted_at');

        $total = $qb->count();

        $items = (clone $qb)
            ->orderBy('pmt.sort_order', 'ASC')
            ->orderBy('pmt.added_at', 'DESC')
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

    public function addTask(array $payload): array
    {
        // Clear any existing active_key to prevent unique constraint violation
        if (!empty($payload['active_key'])) {
            (new QueryBuilder($this->pdo))
                ->from('project_module_tasks')
                ->where('active_key', '=', $payload['active_key'])
                ->whereNull('deleted_at')
                ->update(['active_key' => null]);
        }

        (new QueryBuilder($this->pdo))
            ->from('project_module_tasks')
            ->insert($payload);

        return $payload;
    }

    public function removeTask(int $moduleId, int $taskId, int $actorUserId, string $now): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('project_module_tasks')
            ->where('module_id', '=', $moduleId)
            ->where('task_id', '=', $taskId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $now,
                'removed_by_user_id' => $actorUserId,
                'removed_at' => $now,
                'updated_at' => $now,
                'active_key' => null,
            ]) > 0;
    }

    public function taskIdByPublicId(string $taskPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->select(['id', 'project_id'])
            ->where('public_id', '=', $taskPublicId)
            ->whereNull('deleted_at')
            ->first();

        return isset($row['id']) ? (int)$row['id'] : null;
    }

    public function taskIdByTaskKey(string $taskKey): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->select(['id', 'project_id'])
            ->where('task_key', '=', $taskKey)
            ->whereNull('deleted_at')
            ->first();

        return isset($row['id']) ? (int)$row['id'] : null;
    }

    public function moduleIdByPublicId(string $modulePublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('project_modules')
            ->select(['id', 'project_id', 'status'])
            ->where('public_id', '=', $modulePublicId)
            ->whereNull('deleted_at')
            ->first();

        return isset($row['id']) ? (int)$row['id'] : null;
    }

    public function taskAlreadyInModule(int $moduleId, int $taskId): bool
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('project_module_tasks')
            ->select(['id'])
            ->where('module_id', '=', $moduleId)
            ->where('task_id', '=', $taskId)
            ->whereNull('deleted_at')
            ->first();

        return $row !== false;
    }

    public function moduleSummary(int $moduleId): array
    {
        $total = (int)(new QueryBuilder($this->pdo))
            ->from('project_module_tasks')
            ->where('module_id', '=', $moduleId)
            ->whereNull('deleted_at')
            ->count();

        $statusCounts = (new QueryBuilder($this->pdo))
            ->from('project_module_tasks pmt')
            ->leftJoin('tasks t', 't.id', '=', 'pmt.task_id')
            ->select(['t.status_code', 'COUNT(*) AS cnt'])
            ->where('pmt.module_id', '=', $moduleId)
            ->whereNull('pmt.deleted_at')
            ->whereNull('t.deleted_at')
            ->groupBy('t.status_code')
            ->get();

        $priorityCounts = (new QueryBuilder($this->pdo))
            ->from('project_module_tasks pmt')
            ->leftJoin('tasks t', 't.id', '=', 'pmt.task_id')
            ->select(['t.priority_code', 'COUNT(*) AS cnt'])
            ->where('pmt.module_id', '=', $moduleId)
            ->whereNull('pmt.deleted_at')
            ->whereNull('t.deleted_at')
            ->groupBy('t.priority_code')
            ->get();

        $assigneeSummary = (new QueryBuilder($this->pdo))
            ->from('project_module_tasks pmt')
            ->leftJoin('tasks t', 't.id', '=', 'pmt.task_id')
            ->leftJoin('users u', 'u.id', '=', 't.assignee_user_id')
            ->select([
                'u.public_id AS user_public_id',
                'u.full_name AS name',
                'COUNT(*) AS total',
                "SUM(CASE WHEN t.status_code IN ('done','closed','archived') THEN 1 ELSE 0 END) AS completed",
            ])
            ->where('pmt.module_id', '=', $moduleId)
            ->whereNull('pmt.deleted_at')
            ->whereNull('t.deleted_at')
            ->groupBy(['u.public_id', 'u.full_name'])
            ->get();

        $completed = 0;
        $open = 0;
        $overdue = 0;
        $unassigned = 0;
        $now = gmdate('Y-m-d H:i:s');

        $byStatus = [];
        foreach ($statusCounts as $row) {
            $code = (string)$row['status_code'];
            $count = (int)$row['cnt'];
            $byStatus[$code] = $count;

            if (in_array($code, ['done', 'closed', 'archived'], true)) {
                $completed += $count;
            } else {
                $open += $count;
            }
        }

        $byPriority = [];
        foreach ($priorityCounts as $row) {
            $byPriority[(string)$row['priority_code']] = (int)$row['cnt'];
        }

        // Overdue
        $overdueRow = (new QueryBuilder($this->pdo))
            ->from('project_module_tasks pmt')
            ->leftJoin('tasks t', 't.id', '=', 'pmt.task_id')
            ->select(['COUNT(*) AS cnt'])
            ->where('pmt.module_id', '=', $moduleId)
            ->whereNull('pmt.deleted_at')
            ->whereNull('t.deleted_at')
            ->whereRaw("t.status_code NOT IN (?, ?, ?)", ['done', 'closed', 'archived'])
            ->whereNotNull('t.due_at')
            ->where('t.due_at', '<', $now)
            ->first();
        $overdue = (int)($overdueRow['cnt'] ?? 0);

        // Unassigned
        $unassignedRow = (new QueryBuilder($this->pdo))
            ->from('project_module_tasks pmt')
            ->leftJoin('tasks t', 't.id', '=', 'pmt.task_id')
            ->select(['COUNT(*) AS cnt'])
            ->where('pmt.module_id', '=', $moduleId)
            ->whereNull('pmt.deleted_at')
            ->whereNull('t.deleted_at')
            ->whereNull('t.assignee_user_id')
            ->first();
        $unassigned = (int)($unassignedRow['cnt'] ?? 0);

        // Members count
        $membersCount = (int)(new QueryBuilder($this->pdo))
            ->from('project_module_members')
            ->where('module_id', '=', $moduleId)
            ->whereNull('deleted_at')
            ->count();

        // Links count
        $linksCount = (int)(new QueryBuilder($this->pdo))
            ->from('project_module_links')
            ->where('module_id', '=', $moduleId)
            ->whereNull('deleted_at')
            ->count();

        return [
            'total_tasks' => $total,
            'completed_tasks' => $completed,
            'open_tasks' => $open,
            'overdue_tasks' => $overdue,
            'unassigned_tasks' => $unassigned,
            'progress_percent' => $total > 0 ? (int)round(($completed / $total) * 100) : 0,
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'by_assignee' => $assigneeSummary,
            'members_count' => $membersCount,
            'links_count' => $linksCount,
        ];
    }

    public function listModulesByTaskId(int $taskId): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('project_module_tasks pmt')
            ->leftJoin('project_modules pm', 'pm.id', '=', 'pmt.module_id')
            ->select([
                'pm.public_id',
                'pm.title',
                'pm.status',
                'pm.color',
                'pm.icon',
            ])
            ->where('pmt.task_id', '=', $taskId)
            ->whereNull('pmt.deleted_at')
            ->whereNull('pm.deleted_at')
            ->whereNull('pm.archived_at')
            ->get();
    }
}
