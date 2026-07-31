<?php
declare(strict_types=1);

namespace Api\Model\Analytics;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class AnalyticsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function summary(int $actorUserId, bool $actorIsRoot, string $now, string $weekStart, string $weekEnd): array
    {
        return [
            'total_projects' => $this->countProjects($actorUserId, $actorIsRoot),
            'total_tasks' => $this->countTasks($actorUserId, $actorIsRoot),
            'completed_tasks' => $this->countCompletedTasks($actorUserId, $actorIsRoot),
            'overdue_tasks' => $this->countOverdueTasks($actorUserId, $actorIsRoot, $now),
            'worklog_minutes_week' => $this->sumWorklogMinutesWeek($actorUserId, $actorIsRoot, $weekStart, $weekEnd),
        ];
    }

    public function projectsBreakdown(int $actorUserId, bool $actorIsRoot, int $limit, string $now): array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('projects p')
            ->leftJoin('tasks t', 't.project_id', '=', 'p.id');

        if (!$actorIsRoot) {
            $query->whereRaw('(p.created_by_user_id = ? OR p.manager_user_id = ?)', [$actorUserId, $actorUserId]);
        }

        return $query
            ->select([
                'p.public_id',
                'p.title',
                'SUM(CASE WHEN t.id IS NOT NULL AND t.deleted_at IS NULL THEN 1 ELSE 0 END) AS total_tasks',
                "SUM(CASE WHEN t.deleted_at IS NULL AND t.status_code IN ('done','closed') THEN 1 ELSE 0 END) AS completed_tasks",
                "SUM(CASE WHEN t.deleted_at IS NULL AND t.status_code NOT IN ('done','closed','archived') AND t.due_at IS NOT NULL AND t.due_at < " . $this->pdo->quote($now) . " THEN 1 ELSE 0 END) AS overdue_tasks",
                "SUM(CASE WHEN t.deleted_at IS NULL AND t.status_code NOT IN ('done','closed','archived') THEN 1 ELSE 0 END) AS active_tasks",
            ])
            ->groupBy(['p.id', 'p.public_id', 'p.title'])
            ->orderBy('total_tasks', 'DESC')
            ->orderBy('p.title', 'ASC')
            ->limit($limit)
            ->get();
    }

    public function usersWorkload(int $actorUserId, bool $actorIsRoot, int $limit, string $now, string $weekStart, string $weekEnd): array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('users u')
            ->where('u.is_active', '=', 1);

        if (!$actorIsRoot) {
            $query->where('u.id', '=', $actorUserId);
        }

        return $query
            ->select([
                'u.public_id',
                'u.login',
                'u.full_name',
                "(SELECT COUNT(*) FROM tasks t
                    WHERE t.assignee_user_id = u.id
                      AND t.deleted_at IS NULL
                      AND t.archived_at IS NULL
                      AND t.status_code NOT IN ('done','closed','archived')) AS assigned_active_tasks",
                "(SELECT COUNT(*) FROM tasks t
                    WHERE t.assignee_user_id = u.id
                      AND t.deleted_at IS NULL
                      AND t.archived_at IS NULL
                      AND t.status_code NOT IN ('done','closed','archived')
                      AND t.due_at IS NOT NULL
                      AND t.due_at < " . $this->pdo->quote($now) . ") AS assigned_overdue_tasks",
                "(SELECT COALESCE(SUM(w.minutes_spent), 0) FROM work_logs w
                    WHERE w.user_id = u.id
                      AND w.logged_at >= " . $this->pdo->quote($weekStart) . "
                      AND w.logged_at <= " . $this->pdo->quote($weekEnd) . ") AS worklog_minutes_week",
            ])
            ->orderBy('assigned_active_tasks', 'DESC')
            ->orderBy('worklog_minutes_week', 'DESC')
            ->limit($limit)
            ->get();
    }

    private function countProjects(int $actorUserId, bool $actorIsRoot): int
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('projects p')
            ->whereNull('p.archived_at');

        if (!$actorIsRoot) {
            $query->whereRaw('(p.created_by_user_id = ? OR p.manager_user_id = ?)', [$actorUserId, $actorUserId]);
        }

        return $query->count();
    }

    private function countTasks(int $actorUserId, bool $actorIsRoot): int
    {
        return $this->buildVisibleTasksQuery($actorUserId, $actorIsRoot)->count();
    }

    private function countCompletedTasks(int $actorUserId, bool $actorIsRoot): int
    {
        return $this->buildVisibleTasksQuery($actorUserId, $actorIsRoot)
            ->whereRaw('t.status_code IN (?, ?)', ['done', 'closed'])
            ->count();
    }

    private function countOverdueTasks(int $actorUserId, bool $actorIsRoot, string $now): int
    {
        return $this->buildVisibleTasksQuery($actorUserId, $actorIsRoot)
            ->whereRaw('t.status_code NOT IN (?, ?, ?)', ['done', 'closed', 'archived'])
            ->whereNotNull('t.due_at')
            ->where('t.due_at', '<', $now)
            ->count();
    }

    private function sumWorklogMinutesWeek(int $actorUserId, bool $actorIsRoot, string $weekStart, string $weekEnd): int
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('work_logs')
            ->where('logged_at', '>=', $weekStart)
            ->where('logged_at', '<=', $weekEnd);

        if (!$actorIsRoot) {
            $query->where('user_id', '=', $actorUserId);
        }

        $row = $query
            ->select(['COALESCE(SUM(minutes_spent), 0) AS total_minutes'])
            ->first();

        return (int)($row['total_minutes'] ?? 0);
    }

    private function buildVisibleTasksQuery(int $actorUserId, bool $actorIsRoot): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->whereNull('t.deleted_at')
            ->whereNull('t.archived_at');

        if (!$actorIsRoot) {
            $query->whereRaw(
                '(t.creator_user_id = ? OR t.assignee_user_id = ? OR p.created_by_user_id = ? OR p.manager_user_id = ?)',
                [$actorUserId, $actorUserId, $actorUserId, $actorUserId]
            );
        }

        return $query;
    }
}
