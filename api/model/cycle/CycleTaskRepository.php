<?php
declare(strict_types=1);

namespace Api\Model\Cycle;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class CycleTaskRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listTasksByCycleId(int $cycleId, array $filters = []): array
    {
        $limit = min(500, max(1, (int)($filters['limit'] ?? 100)));
        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $qb = (new QueryBuilder($this->pdo))
            ->from('cycle_tasks ct')
            ->leftJoin('tasks t', 't.id', '=', 'ct.task_id')
            ->leftJoin('users u', 'u.id', '=', 't.assignee_user_id')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->select([
                'ct.public_id AS cycle_task_public_id',
                'ct.added_by_user_id',
                'ct.added_at',
                'ct.sort_order',
                't.public_id AS task_public_id',
                't.title AS task_title',
                't.status_code AS task_status',
                't.priority_code AS task_priority',
                't.due_at AS task_due_at',
                't.start_at AS task_start_at',
                't.end_at AS task_end_at',
                'u.public_id AS assignee_user_public_id',
                'u.full_name AS assignee_name',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
            ])
            ->where('ct.cycle_id', '=', $cycleId)
            ->whereNull('ct.deleted_at')
            ->whereNull('t.deleted_at');

        $total = $qb->count();

        $items = (clone $qb)
            ->orderBy('ct.sort_order', 'ASC')
            ->orderBy('ct.added_at', 'DESC')
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
        // Clear the active_key on any existing non-deleted cycle_task for this
        // task to avoid unique constraint violation (uq_cycle_tasks_active_key).
        // This happens when a task was in a cycle that was soft-deleted or
        // completed without explicit removal — the old row's active_key
        // remains, preventing re-adding the task to a new cycle.
        if (!empty($payload['active_key'])) {
            (new QueryBuilder($this->pdo))
                ->from('cycle_tasks')
                ->where('active_key', '=', $payload['active_key'])
                ->whereNull('deleted_at')
                ->update(['active_key' => null]);
        }

        (new QueryBuilder($this->pdo))
            ->from('cycle_tasks')
            ->insert($payload);

        return $payload;
    }

    public function removeTask(int $cycleId, int $taskId, int $actorUserId, string $now): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('cycle_tasks')
            ->where('cycle_id', '=', $cycleId)
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

    public function removeTaskByPublicId(string $cycleTaskPublicId, int $actorUserId, string $now): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('cycle_tasks')
            ->where('public_id', '=', $cycleTaskPublicId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $now,
                'removed_by_user_id' => $actorUserId,
                'removed_at' => $now,
                'updated_at' => $now,
                'active_key' => null,
            ]) > 0;
    }

    public function taskAlreadyInActiveCycle(int $taskId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('cycle_tasks ct')
            ->leftJoin('work_cycles wc', 'wc.id', '=', 'ct.cycle_id')
            ->select([
                'ct.*',
                'wc.public_id AS cycle_public_id',
                'wc.title AS cycle_title',
                'wc.status AS cycle_status',
            ])
            ->where('ct.task_id', '=', $taskId)
            ->whereNull('ct.deleted_at')
            ->whereNull('wc.deleted_at')
            ->whereIn('wc.status', ['planned', 'active'])
            ->first();

        return $row !== false ? $row : null;
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

    public function cycleIdByPublicId(string $cyclePublicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('work_cycles')
            ->select(['id', 'project_id', 'status'])
            ->where('public_id', '=', $cyclePublicId)
            ->whereNull('deleted_at')
            ->first();
    }

    public function listUnfinishedTasks(int $cycleId): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('cycle_tasks ct')
            ->leftJoin('tasks t', 't.id', '=', 'ct.task_id')
            ->select([
                'ct.id AS cycle_task_id',
                't.id AS task_id',
                't.public_id AS task_public_id',
                't.title AS task_title',
                't.status_code',
            ])
            ->where('ct.cycle_id', '=', $cycleId)
            ->whereNull('ct.deleted_at')
            ->whereNull('t.deleted_at')
            ->whereRaw("t.status_code NOT IN (?, ?, ?)", ['done', 'closed', 'archived'])
            ->get();
    }

    public function moveUnfinishedTasks(int $sourceCycleId, int $targetCycleId, int $actorUserId): array
    {
        $unfinished = $this->listUnfinishedTasks($sourceCycleId);
        $now = gmdate('Y-m-d H:i:s');
        $moved = [];

        foreach ($unfinished as $task) {
            $taskId = (int)$task['task_id'];

            // Remove from source
            $this->removeTask($sourceCycleId, $taskId, $actorUserId, $now);

            // Add to target
            $publicId = \Api\System\Library\Support\Ulid::generate('ctl');
            $activeKey = 'task:' . $taskId;

            $this->addTask([
                'public_id' => $publicId,
                'cycle_id' => $targetCycleId,
                'task_id' => $taskId,
                'active_key' => $activeKey,
                'added_by_user_id' => $actorUserId,
                'added_at' => $now,
                'sort_order' => 65535,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $moved[] = $task['task_public_id'];
        }

        return $moved;
    }

    public function cycleSummary(int $cycleId): array
    {
        $total = (int)(new QueryBuilder($this->pdo))
            ->from('cycle_tasks')
            ->where('cycle_id', '=', $cycleId)
            ->whereNull('deleted_at')
            ->count();

        $statusCounts = (new QueryBuilder($this->pdo))
            ->from('cycle_tasks ct')
            ->leftJoin('tasks t', 't.id', '=', 'ct.task_id')
            ->select(['t.status_code', 'COUNT(*) AS cnt'])
            ->where('ct.cycle_id', '=', $cycleId)
            ->whereNull('ct.deleted_at')
            ->whereNull('t.deleted_at')
            ->groupBy('t.status_code')
            ->get();

        $priorityCounts = (new QueryBuilder($this->pdo))
            ->from('cycle_tasks ct')
            ->leftJoin('tasks t', 't.id', '=', 'ct.task_id')
            ->select(['t.priority_code', 'COUNT(*) AS cnt'])
            ->where('ct.cycle_id', '=', $cycleId)
            ->whereNull('ct.deleted_at')
            ->whereNull('t.deleted_at')
            ->groupBy('t.priority_code')
            ->get();

        $assigneeSummary = (new QueryBuilder($this->pdo))
            ->from('cycle_tasks ct')
            ->leftJoin('tasks t', 't.id', '=', 'ct.task_id')
            ->leftJoin('users u', 'u.id', '=', 't.assignee_user_id')
            ->select([
                'u.public_id AS user_public_id',
                'u.full_name AS name',
                'COUNT(*) AS total',
                "SUM(CASE WHEN t.status_code IN ('done','closed','archived') THEN 1 ELSE 0 END) AS completed",
            ])
            ->where('ct.cycle_id', '=', $cycleId)
            ->whereNull('ct.deleted_at')
            ->whereNull('t.deleted_at')
            ->groupBy('u.public_id')
            ->groupBy('u.full_name')
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

        // Count overdue and unassigned
        $overdueRows = (new QueryBuilder($this->pdo))
            ->from('cycle_tasks ct')
            ->leftJoin('tasks t', 't.id', '=', 'ct.task_id')
            ->select(['COUNT(*) AS cnt'])
            ->where('ct.cycle_id', '=', $cycleId)
            ->whereNull('ct.deleted_at')
            ->whereNull('t.deleted_at')
            ->whereRaw("t.status_code NOT IN (?, ?, ?)", ['done', 'closed', 'archived'])
            ->whereNotNull('t.due_at')
            ->where('t.due_at', '<', $now)
            ->first();
        $overdue = (int)($overdueRows['cnt'] ?? 0);

        $unassignedRows = (new QueryBuilder($this->pdo))
            ->from('cycle_tasks ct')
            ->leftJoin('tasks t', 't.id', '=', 'ct.task_id')
            ->select(['COUNT(*) AS cnt'])
            ->where('ct.cycle_id', '=', $cycleId)
            ->whereNull('ct.deleted_at')
            ->whereNull('t.deleted_at')
            ->whereNull('t.assignee_user_id')
            ->first();
        $unassigned = (int)($unassignedRows['cnt'] ?? 0);

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
        ];
    }
}
