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
