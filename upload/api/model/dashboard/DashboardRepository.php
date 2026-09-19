<?php
declare(strict_types=1);

namespace Api\Model\Dashboard;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\TaskStatusSemantics;
use PDO;

final class DashboardRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param string[] $accessibleTeamPublicIds */
    public function summary(int $userId, bool $isRoot, string $todayStart, string $todayEnd, string $weekStart, string $weekEnd, array $accessibleTeamPublicIds = [], ?int $organizationId = null): array
    {
        return [
            'active_tasks' => $this->countActiveTasks($userId, $isRoot, $accessibleTeamPublicIds, $organizationId),
            'tasks_today' => $this->countTasksDueInRange($userId, $isRoot, $todayStart, $todayEnd, $accessibleTeamPublicIds, $organizationId),
            'overdue_tasks' => $this->countOverdueTasks($userId, $isRoot, $todayStart, $accessibleTeamPublicIds, $organizationId),
            'active_projects' => $this->countActiveProjects($userId, $isRoot, $accessibleTeamPublicIds, $organizationId),
            'events_today' => $this->countEventsInRange($userId, $isRoot, $todayStart, $todayEnd, $organizationId),
            'unread_notifications' => $this->countUnreadNotifications($userId, $organizationId),
            'reminders_due' => $this->countRemindersDue($userId, $todayEnd, $organizationId),
            'worklog_minutes_week' => $this->sumWorklogMinutes($userId, $isRoot, $weekStart, $weekEnd, $organizationId),
        ];
    }

    private function countActiveTasks(int $userId, bool $isRoot, array $accessibleTeamPublicIds, ?int $organizationId = null): int
    {
        [$sql, $params] = TaskStatusSemantics::notTerminalSql($this->pdo, 't.status_code');

        return $this->buildVisibleTasksQuery($userId, $isRoot, $accessibleTeamPublicIds, $organizationId)
            ->whereRaw($sql, $params)
            ->count();
    }

    private function countTasksDueInRange(int $userId, bool $isRoot, string $from, string $to, array $accessibleTeamPublicIds, ?int $organizationId = null): int
    {
        [$sql, $params] = TaskStatusSemantics::notTerminalSql($this->pdo, 't.status_code');

        return $this->buildVisibleTasksQuery($userId, $isRoot, $accessibleTeamPublicIds, $organizationId)
            ->whereRaw($sql, $params)
            ->whereNotNull('t.due_at')
            ->where('t.due_at', '>=', $from)
            ->where('t.due_at', '<=', $to)
            ->count();
    }

    private function countOverdueTasks(int $userId, bool $isRoot, string $todayStart, array $accessibleTeamPublicIds, ?int $organizationId = null): int
    {
        [$sql, $params] = TaskStatusSemantics::notTerminalSql($this->pdo, 't.status_code');

        return $this->buildVisibleTasksQuery($userId, $isRoot, $accessibleTeamPublicIds, $organizationId)
            ->whereRaw($sql, $params)
            ->whereNotNull('t.due_at')
            ->where('t.due_at', '<', $todayStart)
            ->count();
    }

    private function countActiveProjects(int $userId, bool $isRoot, array $accessibleTeamPublicIds, ?int $organizationId = null): int
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('projects')
            ->whereNull('archived_at');
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);

        if (!$isRoot) {
            $params = [$userId, $userId];
            $sql = '(created_by_user_id = ? OR manager_user_id = ?';
            if ($accessibleTeamPublicIds !== []) {
                $placeholders = implode(', ', array_fill(0, count($accessibleTeamPublicIds), '?'));
                $sql .= ' OR team_public_id IN (' . $placeholders . ')';
                $params = array_merge($params, $accessibleTeamPublicIds);
            }
            $query->whereRaw($sql . ')', $params);
        }

        return $query->count();
    }

    private function countEventsInRange(int $userId, bool $isRoot, string $from, string $to, ?int $organizationId = null): int
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('calendar_events')
            ->where('starts_at', '<=', $to)
            ->where('ends_at', '>=', $from);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);

        if (!$isRoot) {
            $query->where('owner_user_id', '=', $userId);
        } else {
            $query->whereRaw("(source_type IS NULL OR source_type NOT IN ('google_calendar', 'yandex_calendar') OR source_owner_user_id = ?)", [$userId]);
        }

        return $query->count();
    }

    private function countUnreadNotifications(int $userId, ?int $organizationId = null): int
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('notifications')
            ->where('user_id', '=', $userId)
            ->where('is_read', '=', 0);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);
        return $query->count();
    }

    private function countRemindersDue(int $userId, string $until, ?int $organizationId = null): int
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('reminders')
            ->where('user_id', '=', $userId)
            ->whereIn('status', ['new', 'pending'])
            ->where('remind_at', '<=', $until);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);
        return $query->count();
    }

    private function sumWorklogMinutes(int $userId, bool $isRoot, string $from, string $to, ?int $organizationId = null): int
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('work_logs')
            ->where('logged_at', '>=', $from)
            ->where('logged_at', '<=', $to);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);

        if (!$isRoot) {
            $query->where('user_id', '=', $userId);
        }

        $row = $query
            ->select(['COALESCE(SUM(minutes_spent), 0) AS total_minutes'])
            ->first();

        return (int)($row['total_minutes'] ?? 0);
    }

    private function buildVisibleTasksQuery(int $userId, bool $isRoot, array $accessibleTeamPublicIds = [], ?int $organizationId = null): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->whereNull('p.archived_at')
            ->whereNull('t.deleted_at')
            ->whereNull('t.archived_at');
        if ($organizationId !== null && $organizationId > 0) $query->where('t.organization_id', '=', $organizationId);

        if (!$isRoot) {
            $params = [$userId, $userId, $userId, $userId];
            $sql = '(t.creator_user_id = ? OR t.assignee_user_id = ? OR p.created_by_user_id = ? OR p.manager_user_id = ?';
            if ($accessibleTeamPublicIds !== []) {
                $placeholders = implode(', ', array_fill(0, count($accessibleTeamPublicIds), '?'));
                $sql .= ' OR p.team_public_id IN (' . $placeholders . ')';
                $params = array_merge($params, $accessibleTeamPublicIds);
            }
            $query->whereRaw($sql . ')', $params);
        }

        return $query;
    }
}
