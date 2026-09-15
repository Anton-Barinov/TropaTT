<?php
declare(strict_types=1);

namespace Api\Model\Analytics;

use Api\System\Library\Support\TaskStatusSemantics;
use PDO;

/**
 * Per-widget metric aggregates for the dashboard "insights" widgets.
 *
 * Two families live here:
 *
 *  - `personalLoad()` answers only for the acting user (the widget is personal
 *    by definition), combining assigned work, logged time and completion speed;
 *  - `actualTime()` aggregates logged time across the actor's *visible* scope;
 *    the caller resolves that scope (root => everything, otherwise actor +
 *    subordinates + team members) and passes the ids in, mirroring
 *    AnalyticsService::visibleUserIds().
 *
 * `tasks` has no "completed_at" column, so completion speed and throughput are
 * derived from `task_status_history` transitions into a terminal status, which is
 * the only durable record of when work actually finished.
 */
final class InsightsRepository
{
    /** Safety cap for in-PHP median calculations over per-task aggregates. */
    private const MAX_MEDIAN_ROWS = 2000;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Personal load and efficiency for one user.
     *
     * @param array{now:string,week_start:string,period_start:string} $window
     * @return array<string,mixed>
     */
    public function personalLoad(int $userId, array $window): array
    {
        $now = (string)$window['now'];
        $weekStart = (string)$window['week_start'];
        $periodStart = (string)$window['period_start'];

        $active = $this->countAssignedTasks($userId, false, $now);
        $overdue = $this->countAssignedTasks($userId, true, $now);
        $minutesWeek = $this->sumLoggedMinutes([$userId], $weekStart, $now);
        $completedPeriod = $this->countCompletedTasks([$userId], $periodStart, $now, false);
        $cycleDurations = $this->completedCycleMinutes([$userId], $periodStart, $now);

        $medianCycleMinutes = self::median($cycleDurations);
        $efficiency = ($completedPeriod + $overdue) > 0
            ? round($completedPeriod / ($completedPeriod + $overdue) * 100, 1)
            : 0.0;
        $loadPercent = round($minutesWeek / (40 * 60) * 100, 1);

        return [
            'active_tasks' => $active,
            'overdue_tasks' => $overdue,
            'minutes_week' => $minutesWeek,
            'completed_period' => $completedPeriod,
            'cycle_time_median_minutes' => $medianCycleMinutes,
            'efficiency_percent' => $efficiency,
            'load_percent' => $loadPercent,
            'load_signal' => self::loadSignal($loadPercent),
            'daily_minutes' => $this->dailyMinutes([$userId], $weekStart, $now),
        ];
    }

    /**
     * Actual execution time of tasks across the visible scope.
     *
     * @param int[] $userIds visible user ids; ignored when $isRoot is true
     * @return array<string,mixed>
     */
    public function actualTime(array $userIds, bool $isRoot, string $periodStart, string $now, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $perTask = $this->minutesPerTask($userIds, $isRoot, $periodStart, $now);

        usort($perTask, static function (array $a, array $b): int {
            return ((int)$b['minutes']) <=> ((int)$a['minutes']);
        });

        $durations = array_map(static fn(array $row): int => (int)$row['minutes'], array_slice($perTask, 0, self::MAX_MEDIAN_ROWS));

        // Coverage answers "how much of the work in flight has recorded time?":
        // tasks with logs over tasks with logs plus active tasks without any log.
        // (Comparing against status-change counts could exceed 100%, because a
        // task can have logs while its status never changed in the period.)
        $tasksWithLogs = count($perTask);
        $loggedTaskIds = array_column($perTask, 'task_public_id');
        $activeWithoutLogs = $this->countActiveTasksWithoutLogs($userIds, $isRoot, $now, $loggedTaskIds);
        $coverageBase = $tasksWithLogs + $activeWithoutLogs;
        $covered = $coverageBase > 0 ? round($tasksWithLogs / $coverageBase * 100, 1) : 0.0;

        return [
            'top_tasks' => array_slice($perTask, 0, $limit),
            'tasks_with_logs' => $tasksWithLogs,
            'active_without_logs' => $activeWithoutLogs,
            'covered_percent' => $covered,
            'total_minutes' => array_sum($durations),
            'median_minutes' => self::median($durations),
            'average_minutes' => $durations !== [] ? (int)round(array_sum($durations) / count($durations)) : 0,
            'by_activity' => $this->minutesByActivity($userIds, $isRoot, $periodStart, $now),
        ];
    }

    /**
     * Personal KPI scorecard with period-over-period deltas.
     *
     * @param array{now:string,period_start:string,previous_start:string} $current
     * @param array{period_start:string,previous_start:string} $previous
     * @return array<string,mixed>
     */
    public function personalScorecard(int $userId, array $current, array $previous): array
    {
        $now = (string)$current['now'];
        $currentStart = (string)$current['period_start'];
        $previousStart = (string)$current['previous_start'];

        $completed = $this->countCompletedTasks([$userId], $currentStart, $now, false);
        $minutes = $this->sumLoggedMinutes([$userId], $currentStart, $now);
        $completedPrev = $this->countCompletedTasks([$userId], $previousStart, $currentStart, false);
        $minutesPrev = $this->sumLoggedMinutes([$userId], $previousStart, $currentStart);
        $overdue = $this->countAssignedTasks($userId, true, $now);

        $metrics = [
            'completed' => $completed,
            'minutes' => $minutes,
            'average_minutes_per_task' => $completed > 0 ? (int)round($minutes / $completed) : 0,
            'overdue' => $overdue,
            'stale_share_percent' => $this->staleSharePercent([$userId], $now),
            'streak_days' => $this->loggingStreakDays([$userId], $now),
        ];

        $metrics['delta_percent'] = [
            'completed' => self::deltaPercent($completed, $completedPrev),
            'minutes' => self::deltaPercent($minutes, $minutesPrev),
        ];

        return $metrics;
    }

    /**
     * Per-assignee load metrics for the visible scope.
     *
     * @param int[] $userIds empty when $isRoot is true
     * @return array<int, array<string,mixed>>
     */
    public function assigneeLoad(array $userIds, bool $isRoot, array $window): array
    {
        $now = (string)$window['now'];
        $weekStart = (string)$window['week_start'];
        $periodStart = (string)$window['period_start'];

        $sql = 'SELECT u.id AS user_id, u.public_id, u.login, u.full_name
                FROM users u
                WHERE u.is_active = 1 AND u.deleted_at IS NULL';
        $params = [];
        if (!$isRoot) {
            if ($userIds === []) {
                return [];
            }
            $sql .= ' AND u.id IN (' . implode(', ', array_fill(0, count($userIds), '?')) . ')';
            $params = $userIds;
        }
        $sql .= ' ORDER BY u.full_name ASC, u.login ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
            $userId = (int)$user['user_id'];
            $active = $this->countAssignedTasks($userId, false, $now);
            $overdue = $this->countAssignedTasks($userId, true, $now);
            $minutesWeek = $this->sumLoggedMinutes([$userId], $weekStart, $now);
            $completed = $this->countCompletedTasks([$userId], $periodStart, $now, false);
            $loadPercent = round($minutesWeek / (40 * 60) * 100, 1);
            $efficiency = ($completed + $overdue) > 0
                ? round($completed / ($completed + $overdue) * 100, 1)
                : 0.0;

            $rows[] = [
                'user_id' => $userId,
                'user_public_id' => (string)$user['public_id'],
                'login' => (string)$user['login'],
                'full_name' => (string)($user['full_name'] ?? ''),
                'active_tasks' => $active,
                'overdue_tasks' => $overdue,
                'minutes_week' => $minutesWeek,
                'completed_period' => $completed,
                'load_percent' => $loadPercent,
                'efficiency_percent' => $efficiency,
                'signal' => self::workloadSignal($loadPercent, $overdue),
            ];
        }

        return $rows;
    }

    /**
     * Weekly throughput, WIP and cycle time for the visible scope.
     *
     * @param int[] $userIds empty when $isRoot is true
     * @return array<string,mixed>
     */
    public function completionVelocity(array $userIds, bool $isRoot, string $now, int $weeks = 13): array
    {
        $weeks = max(2, min(26, $weeks));
        $scopeSql = '';
        $scopeParams = [];
        if (!$isRoot) {
            if ($userIds === []) {
                return ['weeks' => [], 'cycle_time_median_minutes' => 0, 'cycle_time_p90_minutes' => 0, 'throughput_delta_percent' => 0.0, 'wip' => 0];
            }
            $scopeSql = ' AND t.assignee_user_id IN (' . implode(', ', array_fill(0, count($userIds), '?')) . ')';
            $scopeParams = $userIds;
        }

        $historySql = 'SELECT h.task_id, h.created_at AS finished_at, t.created_at AS created_at
                       FROM task_status_history h
                       JOIN tasks t ON t.id = h.task_id
                       WHERE h.new_status IN (' . TaskStatusSemantics::completedLiteralList($this->pdo) . ')
                         AND t.deleted_at IS NULL' . $scopeSql . '
                       ORDER BY h.created_at ASC';
        $stmt = $this->pdo->prepare($historySql);
        $stmt->execute($scopeParams);

        $firstFinish = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $taskId = (int)$row['task_id'];
            if (!isset($firstFinish[$taskId])) {
                $firstFinish[$taskId] = [(string)$row['finished_at'], (string)$row['created_at']];
            }
        }

        $tasksSql = 'SELECT t.id, t.created_at, t.status_code FROM tasks t
                     WHERE t.deleted_at IS NULL AND t.archived_at IS NULL' . $scopeSql;
        $stmt = $this->pdo->prepare($tasksSql);
        $stmt->execute($scopeParams);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $weekStartTs = strtotime('monday this week', strtotime($now));
        $buckets = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $start = $weekStartTs - $i * 604800;
            $buckets[gmdate('Y-m-d', $start)] = ['week_start' => gmdate('Y-m-d', $start), 'completed' => 0, 'wip' => 0];
        }
        $weekKeys = array_keys($buckets);

        $durations = [];
        foreach ($firstFinish as $taskId => [$finishedAt, $createdAt]) {
            $finishedTs = strtotime($finishedAt);
            $createdTs = strtotime($createdAt);
            if ($finishedTs !== false && $createdTs !== false && $finishedTs >= $createdTs) {
                $durations[] = (int)round(($finishedTs - $createdTs) / 60);
            }
            if ($finishedTs === false) {
                continue;
            }
            $bucket = gmdate('Y-m-d', (int)(floor(($finishedTs - $weekStartTs) / 604800) * 604800 + $weekStartTs));
            if (isset($buckets[$bucket])) {
                $buckets[$bucket]['completed']++;
            }
        }

        foreach ($tasks as $task) {
            $createdTs = strtotime((string)$task['created_at']);
            if ($createdTs === false) {
                continue;
            }
            $taskId = (int)$task['id'];
            $finishedTs = isset($firstFinish[$taskId]) ? strtotime($firstFinish[$taskId][0]) : null;
            foreach ($weekKeys as $key) {
                $weekEnd = strtotime($key) + 604799;
                if ($createdTs > $weekEnd) {
                    continue;
                }
                if ($finishedTs !== null && $finishedTs !== false && $finishedTs <= $weekEnd) {
                    continue;
                }
                $buckets[$key]['wip']++;
            }
        }

        $throughput = array_map(static fn(array $row): int => (int)$row['completed'], array_values($buckets));
        sort($durations);
        $p90Index = $durations !== [] ? (int)min(count($durations) - 1, floor(count($durations) * 0.9)) : 0;

        return [
            'weeks' => array_values($buckets),
            'cycle_time_median_minutes' => self::median($durations),
            'cycle_time_p90_minutes' => $durations !== [] ? (int)$durations[$p90Index] : 0,
            'throughput_delta_percent' => self::deltaPercent(
                $throughput !== [] ? (int)end($throughput) : 0,
                count($throughput) > 1 ? (int)$throughput[count($throughput) - 2] : 0
            ),
            'wip' => $throughput !== [] ? (int)($buckets[$weekKeys[count($weekKeys) - 1]]['wip'] ?? 0) : 0,
        ];
    }

    /**
     * Per-project metrics for the accessible scope.
     *
     * @param string[] $accessibleProjectPublicIds empty when $isRoot is true
     * @return array<int, array<string,mixed>>
     */
    public function projectsOverview(array $accessibleProjectPublicIds, bool $isRoot, array $window): array
    {
        $now = (string)$window['now'];
        $periodStart = (string)$window['period_start'];

        $sql = 'SELECT p.id, p.public_id, p.title, p.status_code,
                       (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL AND t.archived_at IS NULL) AS total_tasks,
                       (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL AND t.archived_at IS NULL
                          AND t.status_code IN (' . TaskStatusSemantics::completedLiteralList($this->pdo) . ')) AS completed_tasks,
                       (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL AND t.archived_at IS NULL
                          AND t.status_code NOT IN (' . TaskStatusSemantics::terminalLiteralList($this->pdo) . ')) AS active_tasks,
                       (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL AND t.archived_at IS NULL
                          AND t.status_code NOT IN (' . TaskStatusSemantics::terminalLiteralList($this->pdo) . ')
                          AND t.due_at IS NOT NULL AND t.due_at < ' . $this->pdo->quote($now) . ') AS overdue_tasks,
                       (SELECT MIN(m.due_at) FROM milestones m WHERE m.project_id = p.id AND m.due_at IS NOT NULL AND m.due_at >= ' . $this->pdo->quote($now) . ') AS next_milestone_at,
                       (SELECT COALESCE(SUM(w.minutes_spent), 0) FROM work_logs w
                          JOIN tasks t ON t.id = w.task_id
                          WHERE t.project_id = p.id AND w.logged_at >= ' . $this->pdo->quote($periodStart) . ') AS minutes_period,
                       (SELECT COUNT(DISTINCT t.assignee_user_id) FROM tasks t
                          WHERE t.project_id = p.id AND t.assignee_user_id IS NOT NULL AND t.deleted_at IS NULL) AS members
                FROM projects p
                WHERE p.archived_at IS NULL';
        $params = [];

        if (!$isRoot) {
            if ($accessibleProjectPublicIds === []) {
                return [];
            }
            $sql .= ' AND p.public_id IN (' . implode(', ', array_fill(0, count($accessibleProjectPublicIds), '?')) . ')';
            $params = $accessibleProjectPublicIds;
        }
        $sql .= ' ORDER BY overdue_tasks DESC, active_tasks DESC, p.title ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $total = (int)$row['total_tasks'];
            $completed = (int)$row['completed_tasks'];
            $active = (int)$row['active_tasks'];
            $overdue = (int)$row['overdue_tasks'];
            $progress = $total > 0 ? round($completed / $total * 100, 1) : 0.0;

            $rows[] = [
                'project_public_id' => (string)$row['public_id'],
                'title' => (string)$row['title'],
                'progress_percent' => $progress,
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'active_tasks' => $active,
                'overdue_tasks' => $overdue,
                'minutes_period' => (int)$row['minutes_period'],
                'members' => (int)$row['members'],
                'next_milestone_at' => $row['next_milestone_at'] !== null ? (string)$row['next_milestone_at'] : null,
                'health' => self::projectHealth($active, $overdue, $row['next_milestone_at'] !== null ? (string)$row['next_milestone_at'] : null, $now),
            ];
        }

        return $rows;
    }

    /**
     * Accessible project public ids for a non-root actor.
     *
     * @param string[] $accessibleTeamPublicIds
     * @return string[]
     */
    public function accessibleProjectPublicIds(int $actorUserId, array $accessibleTeamPublicIds = []): array
    {
        $sql = 'SELECT p.public_id FROM projects p
                WHERE p.archived_at IS NULL
                  AND (p.created_by_user_id = ? OR p.manager_user_id = ?';
        $params = [$actorUserId, $actorUserId];

        if ($accessibleTeamPublicIds !== []) {
            $sql .= ' OR p.team_public_id IN (' . implode(', ', array_fill(0, count($accessibleTeamPublicIds), '?')) . ')';
            $params = array_merge($params, $accessibleTeamPublicIds);
        }
        $sql .= ')';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(static fn(array $row): string => (string)$row['public_id'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Detailed metrics for a single project.
     *
     * @return array<string,mixed>
     */
    public function projectDetail(string $projectPublicId, array $window): array
    {
        $now = (string)$window['now'];
        $periodStart = (string)$window['period_start'];

        $stmt = $this->pdo->prepare('SELECT id, public_id, title, status_code, manager_user_id FROM projects WHERE public_id = ? LIMIT 1');
        $stmt->execute([$projectPublicId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($project === false) {
            return [];
        }
        $projectId = (int)$project['id'];

        $count = function (string $extra) use ($projectId, $now): int {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM tasks t
                 WHERE t.project_id = ? AND t.deleted_at IS NULL AND t.archived_at IS NULL ' . $extra
            );
            $stmt->execute([$projectId]);
            return (int)$stmt->fetchColumn();
        };

        $total = $count('');
        $completed = $count('AND t.status_code IN (' . TaskStatusSemantics::completedLiteralList($this->pdo) . ')');
        $active = $count('AND t.status_code NOT IN (' . TaskStatusSemantics::terminalLiteralList($this->pdo) . ')');
        $overdue = $count('AND t.status_code NOT IN (' . TaskStatusSemantics::terminalLiteralList($this->pdo) . ')
                            AND t.due_at IS NOT NULL AND t.due_at < ' . $this->pdo->quote($now));

        $statusRows = $this->pdo->prepare(
            'SELECT t.status_code, COUNT(*) AS tasks FROM tasks t
             WHERE t.project_id = ? AND t.deleted_at IS NULL AND t.archived_at IS NULL
             GROUP BY t.status_code ORDER BY tasks DESC'
        );
        $statusRows->execute([$projectId]);
        $byStatus = [];
        foreach ($statusRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byStatus[] = ['status_code' => (string)$row['status_code'], 'tasks' => (int)$row['tasks']];
        }

        $membersStmt = $this->pdo->prepare(
            'SELECT u.public_id, u.full_name, u.login, COALESCE(SUM(w.minutes_spent), 0) AS minutes
             FROM work_logs w
             JOIN tasks t ON t.id = w.task_id
             JOIN users u ON u.id = w.user_id
             WHERE t.project_id = ? AND w.logged_at >= ?
             GROUP BY u.id, u.public_id, u.full_name, u.login
             ORDER BY minutes DESC LIMIT 5'
        );
        $membersStmt->execute([$projectId, $periodStart]);
        $members = [];
        foreach ($membersStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $members[] = [
                'user_public_id' => (string)$row['public_id'],
                'full_name' => (string)($row['full_name'] ?? $row['login'] ?? ''),
                'minutes' => (int)$row['minutes'],
            ];
        }

        $milestonesStmt = $this->pdo->prepare(
            'SELECT public_id, title, due_at, status FROM milestones
             WHERE project_id = ? AND due_at IS NOT NULL ORDER BY due_at ASC LIMIT 3'
        );
        $milestonesStmt->execute([$projectId]);
        $milestones = [];
        foreach ($milestonesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $milestones[] = [
                'public_id' => (string)$row['public_id'],
                'title' => (string)$row['title'],
                'due_at' => (string)$row['due_at'],
                'status' => (string)$row['status'],
            ];
        }

        $overdueStmt = $this->pdo->prepare(
            'SELECT t.public_id, t.title, t.due_at FROM tasks t
             WHERE t.project_id = ? AND t.deleted_at IS NULL AND t.archived_at IS NULL
               AND t.status_code NOT IN (' . TaskStatusSemantics::terminalLiteralList($this->pdo) . ')
               AND t.due_at IS NOT NULL AND t.due_at < ' . $this->pdo->quote($now) . '
             ORDER BY t.due_at ASC LIMIT 5'
        );
        $overdueStmt->execute([$projectId]);
        $overdueTasks = [];
        foreach ($overdueStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $overdueTasks[] = [
                'task_public_id' => (string)$row['public_id'],
                'title' => (string)$row['title'],
                'due_at' => (string)$row['due_at'],
            ];
        }

        $historyStmt = $this->pdo->prepare(
            'SELECT h.created_at FROM task_status_history h
             JOIN tasks t ON t.id = h.task_id
             WHERE t.project_id = ? AND h.new_status IN (' . TaskStatusSemantics::completedLiteralList($this->pdo) . ')
             ORDER BY h.created_at ASC'
        );
        $historyStmt->execute([$projectId]);
        $weekStartTs = strtotime('monday this week', strtotime($now));
        $throughput = [];
        for ($i = 7; $i >= 0; $i--) {
            $key = gmdate('Y-m-d', $weekStartTs - $i * 604800);
            $throughput[$key] = ['week_start' => $key, 'completed' => 0];
        }
        foreach ($historyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $finishedTs = strtotime((string)$row['created_at']);
            if ($finishedTs === false) {
                continue;
            }
            $bucket = gmdate('Y-m-d', (int)(floor(($finishedTs - $weekStartTs) / 604800) * 604800 + $weekStartTs));
            if (isset($throughput[$bucket])) {
                $throughput[$bucket]['completed']++;
            }
        }

        $cycleDurations = $this->projectCycleMinutes($projectId, $periodStart, $now);

        return [
            'project_public_id' => (string)$project['public_id'],
            'title' => (string)$project['title'],
            'progress_percent' => $total > 0 ? round($completed / $total * 100, 1) : 0.0,
            'total_tasks' => $total,
            'completed_tasks' => $completed,
            'active_tasks' => $active,
            'overdue_tasks' => $overdue,
            'throughput_weeks' => array_values($throughput),
            'cycle_time_median_minutes' => self::median($cycleDurations),
            'by_status' => $byStatus,
            'top_members' => $members,
            'milestones' => $milestones,
            'overdue_list' => $overdueTasks,
            'health' => self::projectHealth(
                $active,
                $overdue,
                $milestones !== [] ? (string)$milestones[0]['due_at'] : null,
                $now
            ),
        ];
    }

    /**
     * @return int[]
     */
    private function projectCycleMinutes(int $projectId, string $from, string $to): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.created_at AS created_at, MIN(h.created_at) AS finished_at
             FROM tasks t
             JOIN task_status_history h ON h.task_id = t.id
             WHERE t.project_id = ?
               AND h.new_status IN (' . TaskStatusSemantics::completedLiteralList($this->pdo) . ')
               AND h.created_at >= ? AND h.created_at <= ?
             GROUP BY t.id, t.created_at'
        );
        $stmt->execute([$projectId, $from, $to]);

        $durations = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $created = strtotime((string)$row['created_at']);
            $finished = strtotime((string)$row['finished_at']);
            if ($created !== false && $finished !== false && $finished >= $created) {
                $durations[] = (int)round(($finished - $created) / 60);
            }
        }

        return $durations;
    }

    /**
     * Share of active tasks without a status change or worklog for 7 days.
     *
     * @param int[] $userIds
     */
    private function staleSharePercent(array $userIds, string $now): float
    {
        if ($userIds === []) {
            return 0.0;
        }
        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $threshold = gmdate('Y-m-d H:i:s', strtotime('-7 days', strtotime($now)));

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM tasks t
             WHERE t.assignee_user_id IN (' . $placeholders . ')
               AND t.deleted_at IS NULL AND t.archived_at IS NULL
               AND t.status_code NOT IN (' . TaskStatusSemantics::terminalLiteralList($this->pdo) . ')
               AND NOT EXISTS (SELECT 1 FROM task_status_history h WHERE h.task_id = t.id AND h.created_at >= ?)
               AND NOT EXISTS (SELECT 1 FROM work_logs w WHERE w.task_id = t.id AND w.logged_at >= ?)'
        );
        $stmt->execute(array_merge($userIds, [$threshold, $threshold]));
        $stale = (int)$stmt->fetchColumn();

        $total = 0;
        foreach ($userIds as $userId) {
            $total += $this->countAssignedTasks((int)$userId, false, $now);
        }

        return $total > 0 ? round($stale / $total * 100, 1) : 0.0;
    }

    /**
     * Consecutive days (ending today) with at least one worklog entry.
     *
     * @param int[] $userIds
     */
    private function loggingStreakDays(array $userIds, string $now): int
    {
        if ($userIds === []) {
            return 0;
        }
        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $from = gmdate('Y-m-d 00:00:00', strtotime('-120 days', strtotime($now)));

        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT SUBSTR(logged_at, 1, 10) AS day FROM work_logs
             WHERE user_id IN (' . $placeholders . ') AND logged_at >= ?'
        );
        $stmt->execute(array_merge($userIds, [$from]));
        $days = array_flip(array_map(static fn(array $row): string => (string)$row['day'], $stmt->fetchAll(PDO::FETCH_ASSOC)));

        $streak = 0;
        $cursor = strtotime(substr($now, 0, 10) . ' 00:00:00');
        for ($i = 0; $i < 121; $i++) {
            $day = gmdate('Y-m-d', $cursor);
            if (!isset($days[$day])) {
                break;
            }
            $streak++;
            $cursor -= 86400;
        }

        return $streak;
    }

    public static function deltaPercent(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    public static function workloadSignal(float $loadPercent, int $overdue): string
    {
        if ($loadPercent > 110) {
            return 'overload';
        }
        if ($loadPercent < 50 && $overdue === 0) {
            return 'underload';
        }

        return $overdue > 0 ? 'risk' : 'normal';
    }

    public static function projectHealth(int $active, int $overdue, ?string $nextMilestoneAt, string $now): string
    {
        $overdueShare = $active > 0 ? $overdue / $active : 0.0;
        if ($overdueShare > 0.2) {
            return 'critical';
        }
        if ($nextMilestoneAt !== null && $nextMilestoneAt < $now) {
            return 'critical';
        }
        if ($overdue > 0) {
            return 'risk';
        }
        if ($nextMilestoneAt !== null && strtotime($nextMilestoneAt) !== false && strtotime($nextMilestoneAt) <= strtotime('+7 days', strtotime($now))) {
            return 'risk';
        }

        return 'ok';
    }

    private function countAssignedTasks(int $userId, bool $overdueOnly, string $now): int
    {
        $sql = 'SELECT COUNT(*) FROM tasks
                WHERE assignee_user_id = ?
                  AND deleted_at IS NULL
                  AND archived_at IS NULL
                  AND status_code NOT IN (' . TaskStatusSemantics::terminalLiteralList($this->pdo) . ')';
        $params = [$userId];

        if ($overdueOnly) {
            $sql .= ' AND due_at IS NOT NULL AND due_at < ?';
            $params[] = $now;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * @param int[] $userIds
     */
    private function sumLoggedMinutes(array $userIds, string $from, string $to): int
    {
        if ($userIds === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(minutes_spent), 0) FROM work_logs
             WHERE user_id IN ({$placeholders}) AND logged_at >= ? AND logged_at <= ?"
        );
        $stmt->execute(array_merge($userIds, [$from, $to]));

        return (int)$stmt->fetchColumn();
    }

    /**
     * 7-day breakdown, oldest day first, zero-filled.
     *
     * @param int[] $userIds
     * @return array<int, array{date:string,minutes:int}>
     */
    private function dailyMinutes(array $userIds, string $from, string $to): array
    {
        $byDate = [];
        if ($userIds !== []) {
            $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT SUBSTR(logged_at, 1, 10) AS day, COALESCE(SUM(minutes_spent), 0) AS minutes
                 FROM work_logs
                 WHERE user_id IN ({$placeholders}) AND logged_at >= ? AND logged_at <= ?
                 GROUP BY SUBSTR(logged_at, 1, 10)"
            );
            $stmt->execute(array_merge($userIds, [$from, $to]));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $byDate[(string)$row['day']] = (int)$row['minutes'];
            }
        }

        $days = [];
        $cursor = strtotime(substr($from, 0, 10) . ' 00:00:00');
        $end = strtotime(substr($to, 0, 10) . ' 00:00:00');
        while ($cursor !== false && $end !== false && $cursor <= $end) {
            $day = gmdate('Y-m-d', $cursor);
            $days[] = ['date' => $day, 'minutes' => $byDate[$day] ?? 0];
            $cursor += 86400;
        }

        return $days;
    }

    /**
     * @param int[] $userIds
     */
    private function countCompletedTasks(array $userIds, string $from, string $to, bool $isRoot): int
    {
        $sql = 'SELECT COUNT(DISTINCT h.task_id) FROM task_status_history h
                JOIN tasks t ON t.id = h.task_id
                WHERE h.new_status IN (' . TaskStatusSemantics::completedLiteralList($this->pdo) . ')
                  AND h.created_at >= ? AND h.created_at <= ?';
        $params = [$from, $to];

        if (!$isRoot) {
            if ($userIds === []) {
                return 0;
            }
            $sql .= ' AND t.assignee_user_id IN (' . implode(', ', array_fill(0, count($userIds), '?')) . ')';
            $params = array_merge($params, $userIds);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Minutes between task creation and the first terminal transition.
     *
     * @param int[] $userIds
     * @return int[]
     */
    private function completedCycleMinutes(array $userIds, string $from, string $to): array
    {
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT t.created_at AS created_at, MIN(h.created_at) AS finished_at
             FROM tasks t
             JOIN task_status_history h ON h.task_id = t.id
             WHERE t.assignee_user_id IN (' . $placeholders . ')
               AND h.new_status IN (' . TaskStatusSemantics::completedLiteralList($this->pdo) . ')
               AND h.created_at >= ? AND h.created_at <= ?
             GROUP BY t.id, t.created_at'
        );
        $stmt->execute(array_merge($userIds, [$from, $to]));

        $durations = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $created = strtotime((string)$row['created_at']);
            $finished = strtotime((string)$row['finished_at']);
            if ($created === false || $finished === false || $finished < $created) {
                continue;
            }
            $durations[] = (int)round(($finished - $created) / 60);
        }

        return $durations;
    }

    /**
     * @param int[] $userIds
     * @return array<int, array<string,mixed>>
     */
    private function minutesPerTask(array $userIds, bool $isRoot, string $from, string $to): array
    {
        $sql = 'SELECT t.public_id, t.title, p.title AS project_title,
                       COALESCE(SUM(w.minutes_spent), 0) AS minutes,
                       COUNT(w.id) AS sessions
                FROM work_logs w
                JOIN tasks t ON t.id = w.task_id
                LEFT JOIN projects p ON p.id = t.project_id
                WHERE w.logged_at >= ? AND w.logged_at <= ?';
        $params = [$from, $to];

        if (!$isRoot) {
            if ($userIds === []) {
                return [];
            }
            $sql .= ' AND w.user_id IN (' . implode(', ', array_fill(0, count($userIds), '?')) . ')';
            $params = array_merge($params, $userIds);
        }

        $sql .= ' GROUP BY t.id, t.public_id, t.title, p.title ORDER BY minutes DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'task_public_id' => (string)$row['public_id'],
                'title' => (string)$row['title'],
                'project_title' => (string)($row['project_title'] ?? ''),
                'minutes' => (int)$row['minutes'],
                'sessions' => (int)$row['sessions'],
            ];
        }

        return $rows;
    }

    /**
     * Active tasks in scope that have no logged time in the period.
     *
     * @param int[] $userIds
     * @param string[] $loggedTaskPublicIds
     */
    private function countActiveTasksWithoutLogs(array $userIds, bool $isRoot, string $now, array $loggedTaskPublicIds): int
    {
        $sql = 'SELECT COUNT(*) FROM tasks t
                WHERE t.deleted_at IS NULL AND t.archived_at IS NULL
                  AND t.status_code NOT IN (' . TaskStatusSemantics::terminalLiteralList($this->pdo) . ')';
        $params = [];

        if (!$isRoot) {
            if ($userIds === []) {
                return 0;
            }
            $sql .= ' AND t.assignee_user_id IN (' . implode(', ', array_fill(0, count($userIds), '?')) . ')';
            $params = array_merge($params, $userIds);
        }

        if ($loggedTaskPublicIds !== []) {
            $sql .= ' AND t.public_id NOT IN (' . implode(', ', array_fill(0, count($loggedTaskPublicIds), '?')) . ')';
            $params = array_merge($params, $loggedTaskPublicIds);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * @param int[] $userIds
     * @return array<int, array{activity_code:string,minutes:int}>
     */
    private function minutesByActivity(array $userIds, bool $isRoot, string $from, string $to): array
    {
        $sql = 'SELECT activity_code, COALESCE(SUM(minutes_spent), 0) AS minutes
                FROM work_logs
                WHERE logged_at >= ? AND logged_at <= ?';
        $params = [$from, $to];

        if (!$isRoot) {
            if ($userIds === []) {
                return [];
            }
            $sql .= ' AND user_id IN (' . implode(', ', array_fill(0, count($userIds), '?')) . ')';
            $params = array_merge($params, $userIds);
        }

        $sql .= ' GROUP BY activity_code ORDER BY minutes DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $code = trim((string)($row['activity_code'] ?? ''));
            $rows[] = [
                'activity_code' => $code !== '' ? $code : 'unassigned',
                'minutes' => (int)$row['minutes'],
            ];
        }

        return $rows;
    }

    /**
     * @param int[] $values
     */
    public static function median(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (int)$values[$middle];
        }

        return (int)round(($values[$middle - 1] + $values[$middle]) / 2);
    }

    public static function loadSignal(float $loadPercent): string
    {
        if ($loadPercent > 110) {
            return 'overload';
        }
        if ($loadPercent < 50) {
            return 'underload';
        }

        return 'normal';
    }
}
