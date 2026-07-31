<?php
declare(strict_types=1);

namespace Api\Model\Project;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class ProjectSummaryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function milestonesSummary(string $projectPublicId, string $nowUtc): array
    {
        $plusSevenDays = gmdate('Y-m-d H:i:s', strtotime($nowUtc . ' +7 days'));

        $row = (new QueryBuilder($this->pdo))
            ->from('milestones m')
            ->join('projects p', 'p.id', '=', 'm.project_id')
            ->select([
                'COUNT(*) AS total',
                "SUM(CASE WHEN m.status = 'done' THEN 1 ELSE 0 END) AS done",
                "SUM(CASE WHEN m.due_at IS NOT NULL AND m.due_at < '" . $nowUtc . "' AND m.status <> 'done' THEN 1 ELSE 0 END) AS overdue",
                "SUM(CASE WHEN m.due_at IS NOT NULL AND m.due_at >= '" . $nowUtc . "' AND m.due_at < '" . $plusSevenDays . "' AND m.status <> 'done' THEN 1 ELSE 0 END) AS upcoming_7_days",
            ])
            ->where('p.public_id', '=', $projectPublicId)
            ->first() ?? [];

        $next = (new QueryBuilder($this->pdo))
            ->from('milestones m')
            ->join('projects p', 'p.id', '=', 'm.project_id')
            ->select(['m.public_id', 'm.title', 'm.due_at', 'm.status'])
            ->where('p.public_id', '=', $projectPublicId)
            ->whereNotNull('m.due_at')
            ->whereRaw("m.status <> 'done'")
            ->orderBy('m.due_at', 'ASC')
            ->first();

        return [
            'total' => (int)($row['total'] ?? 0),
            'done' => (int)($row['done'] ?? 0),
            'overdue' => (int)($row['overdue'] ?? 0),
            'upcoming_7_days' => (int)($row['upcoming_7_days'] ?? 0),
            'next_due' => $next ?: null,
        ];
    }

    public function risksSummary(string $projectPublicId, string $nowUtc): array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->join('projects p', 'p.id', '=', 't.project_id')
            ->select([
                'COUNT(*) AS total_tasks',
                "SUM(CASE WHEN t.status_code = 'blocked' THEN 1 ELSE 0 END) AS blocked_tasks",
                "SUM(CASE WHEN t.due_at IS NOT NULL AND t.due_at < '" . $nowUtc . "' AND t.status_code <> 'done' THEN 1 ELSE 0 END) AS overdue_tasks",
                "SUM(CASE WHEN t.assignee_user_id IS NULL AND t.status_code <> 'done' THEN 1 ELSE 0 END) AS unassigned_tasks",
                "SUM(CASE WHEN t.priority_code = 'urgent' AND t.status_code <> 'done' THEN 1 ELSE 0 END) AS urgent_open_tasks",
            ])
            ->where('p.public_id', '=', $projectPublicId)
            ->whereNull('t.deleted_at')
            ->whereNull('t.archived_at')
            ->first() ?? [];

        $depsRow = (new QueryBuilder($this->pdo))
            ->from('task_dependencies d')
            ->join('tasks t', 't.id', '=', 'd.task_id')
            ->join('tasks td', 'td.id', '=', 'd.depends_on_task_id')
            ->join('projects p', 'p.id', '=', 't.project_id')
            ->select([
                'COUNT(DISTINCT t.id) AS dependency_blocked_tasks',
                'COUNT(*) AS dependency_edges',
            ])
            ->where('p.public_id', '=', $projectPublicId)
            ->whereNull('t.deleted_at')
            ->whereNull('t.archived_at')
            ->whereRaw("t.status_code <> 'done'")
            ->whereRaw("td.status_code <> 'done'")
            ->first() ?? [];

        return [
            'total_tasks' => (int)($row['total_tasks'] ?? 0),
            'blocked_tasks' => (int)($row['blocked_tasks'] ?? 0),
            'overdue_tasks' => (int)($row['overdue_tasks'] ?? 0),
            'unassigned_tasks' => (int)($row['unassigned_tasks'] ?? 0),
            'urgent_open_tasks' => (int)($row['urgent_open_tasks'] ?? 0),
            'dependency_blocked_tasks' => (int)($depsRow['dependency_blocked_tasks'] ?? 0),
            'dependency_edges' => (int)($depsRow['dependency_edges'] ?? 0),
        ];
    }

    public function workloadSummary(string $projectPublicId, string $nowUtc): array
    {
        $items = (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->join('projects p', 'p.id', '=', 't.project_id')
            ->leftJoin('users u', 'u.id', '=', 't.assignee_user_id')
            ->leftJoin(
                '(SELECT task_id, SUM(minutes_spent) AS minutes_sum FROM work_logs GROUP BY task_id) wla',
                'wla.task_id',
                '=',
                't.id'
            )
            ->select([
                "COALESCE(u.public_id, 'unassigned') AS assignee_user_public_id",
                "COALESCE(u.full_name, 'Unassigned') AS assignee_name",
                'COUNT(*) AS total_tasks',
                "SUM(CASE WHEN t.status_code = 'done' THEN 1 ELSE 0 END) AS done_tasks",
                "SUM(CASE WHEN t.status_code = 'blocked' THEN 1 ELSE 0 END) AS blocked_tasks",
                "SUM(CASE WHEN t.due_at IS NOT NULL AND t.due_at < '" . $nowUtc . "' AND t.status_code <> 'done' THEN 1 ELSE 0 END) AS overdue_tasks",
                'SUM(COALESCE(wla.minutes_sum, 0)) AS logged_minutes',
            ])
            ->where('p.public_id', '=', $projectPublicId)
            ->whereNull('t.deleted_at')
            ->whereNull('t.archived_at')
            ->groupBy(['t.assignee_user_id', 'u.public_id', 'u.full_name'])
            ->orderBy('total_tasks', 'DESC')
            ->orderBy('assignee_name', 'ASC')
            ->get();

        $totals = [
            'total_tasks' => 0,
            'done_tasks' => 0,
            'blocked_tasks' => 0,
            'overdue_tasks' => 0,
            'logged_minutes' => 0,
        ];

        foreach ($items as &$item) {
            $item['total_tasks'] = (int)($item['total_tasks'] ?? 0);
            $item['done_tasks'] = (int)($item['done_tasks'] ?? 0);
            $item['blocked_tasks'] = (int)($item['blocked_tasks'] ?? 0);
            $item['overdue_tasks'] = (int)($item['overdue_tasks'] ?? 0);
            $item['logged_minutes'] = (int)($item['logged_minutes'] ?? 0);

            $totals['total_tasks'] += $item['total_tasks'];
            $totals['done_tasks'] += $item['done_tasks'];
            $totals['blocked_tasks'] += $item['blocked_tasks'];
            $totals['overdue_tasks'] += $item['overdue_tasks'];
            $totals['logged_minutes'] += $item['logged_minutes'];
        }
        unset($item);

        return [
            'items' => $items,
            'totals' => $totals,
        ];
    }
}
