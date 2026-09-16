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

    /** Weekly capacity every load metric is measured against (40 h). */
    public const WEEK_CAPACITY_MINUTES = 2400;

    /** Longest day breakdown returned for a widget period. */
    private const MAX_DAILY_POINTS = 30;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Personal load, completion quality and backlog balance for one user.
     *
     * `active_tasks`/`overdue_tasks` are live snapshots ("now"); every other
     * counter is period-based, and `delta_percent` compares the period with the
     * equally long window that precedes it.
     *
     * @param array{now:string,week_start:string,period_start:string,previous_start?:string,period_days?:int} $window
     * @return array<string,mixed>
     */
    public function personalLoad(int $userId, array $window): array
    {
        $now = (string)$window['now'];
        $weekStart = (string)$window['week_start'];
        $periodStart = (string)$window['period_start'];
        $previousStart = (string)($window['previous_start'] ?? $periodStart);
        $periodDays = max(1, (int)($window['period_days'] ?? 30));

        $active = $this->countAssignedTasks($userId, false, $now);
        $overdue = $this->countAssignedTasks($userId, true, $now);
        $minutesWeek = $this->sumLoggedMinutes([$userId], $weekStart, $now);
        $minutesPeriod = $this->sumLoggedMinutes([$userId], $periodStart, $now);
        $completedPeriod = $this->countCompletedTasks([$userId], $periodStart, $now, false);
        $cycleDurations = $this->completedCycleMinutes([$userId], $periodStart, $now);

        $completedPrevious = $this->countCompletedTasks([$userId], $previousStart, $periodStart, false);
        $minutesPrevious = $this->sumLoggedMinutes([$userId], $previousStart, $periodStart);
        $createdPeriod = $this->countCreatedTasks([$userId], $periodStart, $now);
        $onTime = $this->onTimeCompletion([$userId], $periodStart, $now);

        $medianCycleMinutes = self::median($cycleDurations);
        // Completion ratio: finished work against work that still needs attention
        // (current) backlog + live overdue. Deliberately NOT the on-time rate -
        // `on_time_percent` answers the deadline question separately.
        $efficiency = ($completedPeriod + $overdue) > 0
            ? round($completedPeriod / ($completedPeriod + $overdue) * 100, 1)
            : 0.0;
        $loadPercent = round($minutesWeek / self::WEEK_CAPACITY_MINUTES * 100, 1);

        // Queue balance: how fast work is finished versus how fast it arrives.
        $throughputPerDay = round($completedPeriod / $periodDays, 2);
        $backlogDays = $throughputPerDay > 0 ? (int)ceil($active / $throughputPerDay) : null;

        // The bar chart covers the reported period in at most MAX_DAILY_POINTS bars;
        // `period_daily_bucket_days` tells the card how many days one bar stands for.
        $periodDailyMinutes = $this->periodDailyMinutes([$userId], $periodStart, $now, $periodDays);
        $periodDailyBucketDays = (int)($periodDailyMinutes[0]['days'] ?? 1);

        return [
            'active_tasks' => $active,
            'overdue_tasks' => $overdue,
            'minutes_week' => $minutesWeek,
            'minutes_period' => $minutesPeriod,
            'capacity_minutes_week' => self::WEEK_CAPACITY_MINUTES,
            'completed_period' => $completedPeriod,
            'cycle_time_median_minutes' => $medianCycleMinutes,
            'on_time_percent' => $onTime['percent'],
            'on_time_sample' => $onTime['sample'],
            'created_period' => $createdPeriod,
            'throughput_per_day' => $throughputPerDay,
            'backlog_days_to_clear' => $backlogDays,
            'backlog_signal' => self::backlogSignal($createdPeriod, $completedPeriod),
            'delta_percent' => [
                'completed' => self::deltaPercent($completedPeriod, $completedPrevious),
                'minutes' => self::deltaPercent($minutesPeriod, $minutesPrevious),
            ],
            'efficiency_percent' => $efficiency,
            'load_percent' => $loadPercent,
            'load_signal' => self::loadSignal($loadPercent),
            'daily_minutes' => $this->dailyMinutes([$userId], $weekStart, $now),
            'period_daily_minutes' => $periodDailyMinutes,
            'period_daily_bucket_days' => $periodDailyBucketDays,
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
        $totalMinutes = array_sum($durations);

        // Share of the period total makes the ranking readable ("эта задача — 32%
        // всего учёта") instead of leaving the user to compare raw hours.
        $topTasks = array_slice($perTask, 0, $limit);
        foreach ($topTasks as $index => $row) {
            $topTasks[$index]['share_percent'] = $totalMinutes > 0
                ? round((int)$row['minutes'] / $totalMinutes * 100, 1)
                : 0.0;
        }

        // Coverage answers "how much of the work in flight has recorded time?":
        // tasks with logs over tasks with logs plus active tasks without any log.
        // (Comparing against status-change counts could exceed 100%, because a
        // task can have logs while its status never changed in the period.)
        $tasksWithLogs = count($perTask);
        $loggedTaskIds = array_column($perTask, 'task_public_id');
        $activeWithoutLogs = $this->countActiveTasksWithoutLogs($userIds, $isRoot, $loggedTaskIds);
        $coverageBase = $tasksWithLogs + $activeWithoutLogs;
        $covered = $coverageBase > 0 ? round($tasksWithLogs / $coverageBase * 100, 1) : 0.0;

        return [
            'top_tasks' => $topTasks,
            'tasks_with_logs' => $tasksWithLogs,
            'active_without_logs' => $activeWithoutLogs,
            'active_without_logs_list' => $this->activeTasksWithoutLogsList($userIds, $isRoot, $loggedTaskIds),
            'covered_percent' => $covered,
            'total_minutes' => $totalMinutes,
            'median_minutes' => self::median($durations),
            'average_minutes' => $durations !== [] ? (int)round($totalMinutes / count($durations)) : 0,
            'by_activity' => $this->minutesByActivity($userIds, $isRoot, $periodStart, $now),
            'limit' => $limit,
        ];
    }

    /**
     * Personal KPI scorecard with period-over-period deltas.
     *
     * `overdue` and `stale_share_percent` are live snapshots: the schema keeps no
     * history of what was overdue on an arbitrary past date, so a fake delta would
     * be a guess. They are labelled as snapshots in the UI instead.
     *
     * @param array{now:string,period_start:string,previous_start:string,period_days?:int} $current
     * @param array{period_start:string,previous_start:string} $previous
     * @return array<string,mixed>
     */
    public function personalScorecard(int $userId, array $current, array $previous): array
    {
        $now = (string)$current['now'];
        $currentStart = (string)$current['period_start'];
        $previousStart = (string)$current['previous_start'];
        $periodDays = max(1, (int)($current['period_days'] ?? 30));

        $completed = $this->countCompletedTasks([$userId], $currentStart, $now, false);
        $minutes = $this->sumLoggedMinutes([$userId], $currentStart, $now);
        $completedPrev = $this->countCompletedTasks([$userId], $previousStart, $currentStart, false);
        $minutesPrev = $this->sumLoggedMinutes([$userId], $previousStart, $currentStart);
        $overdue = $this->countAssignedTasks($userId, true, $now);
        $onTime = $this->onTimeCompletion([$userId], $currentStart, $now);

        // The trend window always reaches 8 weeks back, even for a 7-day period:
        // a four-week-to-four-week comparison is the smallest interval that is not
        // dominated by one lucky day.
        $trendStart = gmdate('Y-m-d 00:00:00', strtotime('-8 weeks', strtotime($now)));
        $seriesStart = min($trendStart, $currentStart);
        $series = $this->weeklySeries([$userId], $seriesStart, $now, min(13, max(2, (int)ceil($periodDays / 7) + 8)));

        $metrics = [
            'completed' => $completed,
            'minutes' => $minutes,
            'average_minutes_per_task' => $completed > 0 ? (int)round($minutes / $completed) : 0,
            'overdue' => $overdue,
            'stale_share_percent' => $this->staleSharePercent([$userId], $now),
            'streak_days' => $this->loggingStreakDays([$userId], $now),
            'on_time_percent' => $onTime['percent'],
            'on_time_sample' => $onTime['sample'],
            'weekly' => array_slice($series, -max(2, (int)ceil($periodDays / 7))),
            'trend_percent' => self::trendPercent($series),
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
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $userIds = array_map(static fn(array $row): int => (int)$row['user_id'], $users);
        if ($userIds === []) {
            return [];
        }

        // Batched aggregates: four grouped queries for the whole page instead of
        // five per user. The ids come from the already scope-filtered user list,
        // so a non-root actor can never widen its own scope here.
        $taskCounts = $this->assignedTaskCountsByUser($userIds, $now);
        $minutesByUser = $this->loggedMinutesByUser($userIds, $weekStart, $now);
        $completedByUser = $this->completedTasksByUser($userIds, $periodStart, $now);

        $rows = [];
        foreach ($users as $user) {
            $userId = (int)$user['user_id'];
            $active = (int)($taskCounts[$userId]['active'] ?? 0);
            $overdue = (int)($taskCounts[$userId]['overdue'] ?? 0);
            $minutesWeek = (int)($minutesByUser[$userId] ?? 0);
            $completed = (int)($completedByUser[$userId] ?? 0);
            $loadPercent = round($minutesWeek / self::WEEK_CAPACITY_MINUTES * 100, 1);
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
    public function completionVelocity(array $userIds, bool $isRoot, string $now, int $weeks = 13, ?string $periodStart = null): array
    {
        $weeks = max(2, min(26, $weeks));
        $scopeSql = '';
        $scopeParams = [];
        if (!$isRoot) {
            if ($userIds === []) {
                return [
                    'weeks' => [],
                    'cycle_time_median_minutes' => 0,
                    'cycle_time_p90_minutes' => 0,
                    'cycle_time_median_period_minutes' => 0,
                    'throughput_delta_percent' => 0.0,
                    'trend_percent' => 0.0,
                    'wip' => 0,
                    'wip_trend' => 'stable',
                    'forecast' => ['average_per_week' => 0.0, 'open_tasks' => 0, 'weeks_to_finish' => null, 'finish_date' => null],
                ];
            }
            $scopeSql = ' AND t.assignee_user_id IN (' . implode(', ', array_fill(0, count($userIds), '?')) . ')';
            $scopeParams = $userIds;
        }

        $weekStartTs = strtotime('monday this week', strtotime($now));
        $buckets = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $start = $weekStartTs - $i * 604800;
            $buckets[gmdate('Y-m-d', $start)] = ['week_start' => gmdate('Y-m-d', $start), 'opened' => 0, 'completed' => 0, 'wip' => 0];
        }
        $weekKeys = array_keys($buckets);
        $windowStart = (string)$weekKeys[0] . ' 00:00:00';

        // Only transitions inside the reported window are pulled: the payload is a
        // 13-week view, so an all-history scan would be pure cost.
        $historySql = 'SELECT t.id AS task_id, MIN(h.created_at) AS finished_at, t.created_at AS created_at
                       FROM task_status_history h
                       JOIN tasks t ON t.id = h.task_id
                       WHERE h.new_status IN (' . TaskStatusSemantics::completedLiteralList($this->pdo) . ')
                         AND t.deleted_at IS NULL' . $scopeSql . '
                         AND h.created_at >= ?
                       GROUP BY t.id, t.created_at
                       ORDER BY finished_at ASC';
        $stmt = $this->pdo->prepare($historySql);
        $stmt->execute(array_merge($scopeParams, [$windowStart]));

        $durations = [];
        $periodDurations = [];
        $periodStartTs = $periodStart !== null ? strtotime($periodStart) : false;
        $completedByDay = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $finishedTs = strtotime((string)$row['finished_at']);
            $createdTs = strtotime((string)$row['created_at']);
            if ($finishedTs === false) {
                continue;
            }
            $day = substr((string)$row['finished_at'], 0, 10);
            $completedByDay[$day] = ($completedByDay[$day] ?? 0) + 1;
            if ($createdTs !== false && $finishedTs >= $createdTs) {
                $duration = (int)round(($finishedTs - $createdTs) / 60);
                $durations[] = $duration;
                if ($periodStartTs !== false && $finishedTs >= $periodStartTs) {
                    $periodDurations[] = $duration;
                }
            }
        }

        // WIP is cumulative arrivals minus cumulative departures: two day-bucketed
        // aggregates plus the counts that precede the window. Previously this walked
        // every task once per week in PHP.
        $baselineCreated = $this->countTasksBefore($userIds, $isRoot, $windowStart);
        $baselineFinished = $this->countFinishedBefore($userIds, $isRoot, $windowStart);
        $openedByDay = $this->createdTasksByDay($userIds, $isRoot, $windowStart, $now);
        $openTasks = $this->countOpenTasks($userIds, $isRoot);

        $openedByWeek = [];
        foreach ($openedByDay as $day => $count) {
            $key = $this->weekKeyFor((string)$day, $weekStartTs);
            if ($key !== null) {
                $openedByWeek[$key] = ($openedByWeek[$key] ?? 0) + $count;
            }
        }
        $completedByWeek = [];
        foreach ($completedByDay as $day => $count) {
            $key = $this->weekKeyFor((string)$day, $weekStartTs);
            if ($key !== null) {
                $completedByWeek[$key] = ($completedByWeek[$key] ?? 0) + $count;
            }
        }

        $running = max(0, $baselineCreated - $baselineFinished);
        foreach ($weekKeys as $key) {
            $running += (int)($openedByWeek[$key] ?? 0) - (int)($completedByWeek[$key] ?? 0);
            $buckets[$key]['opened'] = (int)($openedByWeek[$key] ?? 0);
            $buckets[$key]['completed'] = (int)($completedByWeek[$key] ?? 0);
            $buckets[$key]['wip'] = max(0, $running);
        }

        $weekRows = array_values($buckets);
        if ($weekRows !== []) {
            $weekRows[count($weekRows) - 1]['wip'] = $openTasks;
        }
        $throughput = array_map(static fn(array $row): int => (int)$row['completed'], $weekRows);
        $lastWip = $openTasks;
        $previousWip = count($weekRows) > 1 ? (int)$weekRows[count($weekRows) - 2]['wip'] : $openTasks;

        sort($durations);
        $p90Index = $durations !== [] ? (int)min(count($durations) - 1, floor(count($durations) * 0.9)) : 0;

        // Forecast: four-week average throughput against the open queue. Deliberately
        // blunt - the UI presents it as an estimate, not a commitment.
        $recent = array_slice($throughput, -4);
        $previousWindow = array_slice($throughput, -8, -4);
        $average = $recent !== [] ? array_sum($recent) / count($recent) : 0.0;
        $previousAverage = count($previousWindow) === 4 ? array_sum($previousWindow) / 4 : 0.0;
        $weeksToFinish = $average > 0 ? round($openTasks / $average, 1) : null;

        return [
            'weeks' => $weekRows,
            'cycle_time_median_minutes' => self::median($durations),
            'cycle_time_p90_minutes' => $durations !== [] ? (int)$durations[$p90Index] : 0,
            'cycle_time_median_period_minutes' => self::median($periodDurations),
            'throughput_delta_percent' => self::deltaPercent(
                $throughput !== [] ? (int)end($throughput) : 0,
                count($throughput) > 1 ? (int)$throughput[count($throughput) - 2] : 0
            ),
            'trend_percent' => self::deltaPercent((int)round($average), (int)round($previousAverage)),
            'wip' => $lastWip,
            'wip_trend' => $lastWip > $previousWip ? 'growing' : ($lastWip < $previousWip ? 'shrinking' : 'stable'),
            'forecast' => [
                'average_per_week' => round($average, 1),
                'open_tasks' => $openTasks,
                'weeks_to_finish' => $weeksToFinish,
                'finish_date' => $weeksToFinish !== null
                    ? gmdate('Y-m-d', strtotime($now) + (int)round($weeksToFinish * 604800))
                    : null,
            ],
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

        // One grouped pass plus two derived tables instead of six correlated
        // subqueries per project - the previous shape scaled with the project count.
        $terminalSql = TaskStatusSemantics::terminalLiteralList($this->pdo);
        $completedSql = TaskStatusSemantics::completedLiteralList($this->pdo);
        $sql = 'SELECT p.id, p.public_id, p.title, p.status_code,
                       COUNT(t.id) AS total_tasks,
                       SUM(CASE WHEN t.status_code IN (' . $completedSql . ') THEN 1 ELSE 0 END) AS completed_tasks,
                       SUM(CASE WHEN t.status_code NOT IN (' . $terminalSql . ') THEN 1 ELSE 0 END) AS active_tasks,
                       SUM(CASE WHEN t.status_code NOT IN (' . $terminalSql . ')
                                  AND t.due_at IS NOT NULL AND t.due_at < ? THEN 1 ELSE 0 END) AS overdue_tasks,
                       COUNT(DISTINCT CASE WHEN t.assignee_user_id IS NOT NULL THEN t.assignee_user_id END) AS members,
                       COALESCE(wl.minutes, 0) AS minutes_period,
                       ms.next_milestone_at
                FROM projects p
                LEFT JOIN tasks t ON t.project_id = p.id AND t.deleted_at IS NULL AND t.archived_at IS NULL
                LEFT JOIN (SELECT t.project_id AS pid, COALESCE(SUM(w.minutes_spent), 0) AS minutes
                             FROM work_logs w
                             JOIN tasks t ON t.id = w.task_id
                            WHERE w.logged_at >= ?
                            GROUP BY t.project_id) wl ON wl.pid = p.id
                LEFT JOIN (SELECT m.project_id AS pid, MIN(m.due_at) AS next_milestone_at
                             FROM milestones m
                            WHERE m.due_at IS NOT NULL AND m.due_at >= ?
                            GROUP BY m.project_id) ms ON ms.pid = p.id
                WHERE p.archived_at IS NULL';
        $params = [$now, $periodStart, $now];

        if (!$isRoot) {
            if ($accessibleProjectPublicIds === []) {
                return [];
            }
            $sql .= ' AND p.public_id IN (' . implode(', ', array_fill(0, count($accessibleProjectPublicIds), '?')) . ')';
            $params = array_merge($params, $accessibleProjectPublicIds);
        }
        $sql .= ' GROUP BY p.id, p.public_id, p.title, p.status_code, wl.minutes, ms.next_milestone_at'
            . ' ORDER BY overdue_tasks DESC, active_tasks DESC, p.title ASC';

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

    /**
     * Queue balance: is the backlog shrinking (finishing faster than work arrives),
     * stable, or growing?
     */
    public static function backlogSignal(int $created, int $completed): string
    {
        if ($created > $completed) {
            return 'growing';
        }
        if ($created < $completed) {
            return 'clearing';
        }

        return 'stable';
    }

    /**
     * Smoothed momentum: last four weekly buckets against the four before them.
     * Falls back to `deltaPercent` so an empty previous window reports growth
     * rather than a division by zero.
     *
     * @param array<int, array{completed:int}> $weeks
     */
    public static function trendPercent(array $weeks): float
    {
        $count = count($weeks);
        if ($count === 0) {
            return 0.0;
        }

        $completed = array_map(static fn(array $row): int => (int)($row['completed'] ?? 0), $weeks);
        // `-8, -4` keeps the two windows disjoint; with fewer than eight buckets the
        // previous window is unknown and `deltaPercent` reports growth from zero.
        $recent = array_slice($completed, -4);
        $previous = array_slice($completed, -8, -4);
        $currentAverage = $recent === [] ? 0 : (int)round(array_sum($recent) / count($recent));
        $previousAverage = count($previous) === 4 ? (int)round(array_sum($previous) / 4) : 0;

        return self::deltaPercent($currentAverage, $previousAverage);
    }

    /**
     * Rebalancing hint for a manager: who is overloaded, who has room, and how much
     * hand-over would bring the pair back into the normal band. Pure function over the
     * already computed assignee rows so it can be unit tested without a database.
     *
     * @param array<int, array<string,mixed>> $assignees
     * @return array<string,mixed>|null
     */
    public static function rebalanceRecommendation(array $assignees): ?array
    {
        $from = null;
        $to = null;
        foreach ($assignees as $row) {
            $load = (float)($row['load_percent'] ?? 0);
            $active = (int)($row['active_tasks'] ?? 0);
            if ($load > 110 && $active > 1 && ($from === null || $load > (float)$from['load_percent'])) {
                $from = $row;
            }
            if ($load < 50 && (int)($row['overdue_tasks'] ?? 0) === 0 && ($to === null || $load < (float)$to['load_percent'])) {
                $to = $row;
            }
        }

        if ($from === null || $to === null || (string)$from['user_public_id'] === (string)$to['user_public_id']) {
            return null;
        }

        $active = (int)$from['active_tasks'];
        $excess = (int)floor(((float)$from['load_percent'] - 100) / 100 * $active);

        return [
            'reason' => 'rebalance_overload',
            'tasks' => max(1, min($active - 1, $excess)),
            'from' => [
                'user_public_id' => (string)$from['user_public_id'],
                'name' => (string)($from['full_name'] !== '' ? $from['full_name'] : $from['login']),
                'load_percent' => (float)$from['load_percent'],
                'active_tasks' => $active,
            ],
            'to' => [
                'user_public_id' => (string)$to['user_public_id'],
                'name' => (string)($to['full_name'] !== '' ? $to['full_name'] : $to['login']),
                'load_percent' => (float)$to['load_percent'],
                'active_tasks' => (int)$to['active_tasks'],
            ],
        ];
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
                       COUNT(w.id) AS sessions,
                       MAX(w.logged_at) AS last_logged_at
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
                'last_logged_at' => $row['last_logged_at'] !== null ? (string)$row['last_logged_at'] : null,
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
    private function countActiveTasksWithoutLogs(array $userIds, bool $isRoot, array $loggedTaskPublicIds): int
    {
        [$where, $params] = $this->activeWithoutLogsWhere($userIds, $isRoot, $loggedTaskPublicIds);
        if ($where === null) {
            return 0;
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tasks t WHERE ' . $where);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Why `covered_percent` is not 100: the concrete tasks whose time was never
     * recorded, so the number is actionable instead of just alarming.
     *
     * @param int[] $userIds
     * @param string[] $loggedTaskPublicIds
     * @return array<int, array<string,mixed>>
     */
    private function activeTasksWithoutLogsList(array $userIds, bool $isRoot, array $loggedTaskPublicIds, int $limit = 5): array
    {
        [$where, $params] = $this->activeWithoutLogsWhere($userIds, $isRoot, $loggedTaskPublicIds);
        if ($where === null) {
            return [];
        }

        $limit = max(1, min(25, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT t.public_id, t.title, t.due_at, t.status_code FROM tasks t WHERE ' . $where
            . ' ORDER BY (t.due_at IS NULL), t.due_at ASC, t.created_at ASC LIMIT ' . $limit
        );
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'task_public_id' => (string)$row['public_id'],
                'title' => (string)$row['title'],
                'due_at' => $row['due_at'] !== null ? (string)$row['due_at'] : null,
                'status_code' => (string)($row['status_code'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * Shared predicate for "active work with no recorded time". Both the counter
     * and the list use it, so the number and the rows can never disagree.
     *
     * @param int[] $userIds
     * @param string[] $loggedTaskPublicIds
     * @return array{0:string|null,1:array<int,string|int>}
     */
    private function activeWithoutLogsWhere(array $userIds, bool $isRoot, array $loggedTaskPublicIds): array
    {
        $sql = 't.deleted_at IS NULL AND t.archived_at IS NULL
                AND t.status_code NOT IN (' . TaskStatusSemantics::terminalLiteralList($this->pdo) . ')';
        $params = [];

        if (!$isRoot) {
            if ($userIds === []) {
                return [null, []];
            }
            $sql .= ' AND t.assignee_user_id IN (' . implode(', ', array_fill(0, count($userIds), '?')) . ')';
            $params = array_merge($params, $userIds);
        }

        if ($loggedTaskPublicIds !== []) {
            $sql .= ' AND t.public_id NOT IN (' . implode(', ', array_fill(0, count($loggedTaskPublicIds), '?')) . ')';
            $params = array_merge($params, $loggedTaskPublicIds);
        }

        return [$sql, $params];
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
     * Zero-filled minute breakdown for the widget period.
     *
     * The window the metrics use is inclusive (`period_start` .. now), so it holds
     * one more calendar day than `period_days`; the card reports the last
     * `period_days` days, so the extra leading day is dropped here.
     *
     * A single bar per day would ship 90 bars into a 90-day card, so longer windows
     * are merged into equal contiguous buckets. The buckets always span the **whole**
     * reported period: trimming the oldest days instead would silently turn the
     * 90-day chart into a 30-day one while still claiming the full period.
     *
     * @param int[] $userIds
     * @return array<int, array{date:string,minutes:int,end_date?:string,days?:int}>
     */
    private function periodDailyMinutes(array $userIds, string $from, string $to, int $periodDays): array
    {
        $series = array_values($this->dailyMinutes($userIds, $from, $to));
        if (count($series) > $periodDays) {
            $series = array_slice($series, -$periodDays);
        }

        $days = count($series);
        if ($days <= self::MAX_DAILY_POINTS) {
            return $series;
        }

        // Equal buckets of whole days: ceil keeps the bar count inside the cap while
        // every day of the period stays accounted for.
        $bucketDays = (int)ceil($days / self::MAX_DAILY_POINTS);
        $buckets = [];
        for ($i = 0; $i < $days; $i += $bucketDays) {
            $chunk = array_slice($series, $i, $bucketDays);
            $minutes = 0;
            foreach ($chunk as $day) {
                $minutes += (int)$day['minutes'];
            }
            $buckets[] = [
                'date' => (string)$chunk[0]['date'],
                'end_date' => (string)$chunk[count($chunk) - 1]['date'],
                'days' => count($chunk),
                'minutes' => $minutes,
            ];
        }

        return $buckets;
    }

    /**
     * Deadline adherence: of the tasks finished inside the window that actually had
     * a due date, how many made it. Tasks without a deadline are excluded on purpose -
     * counting them as "on time" would let a missing deadline inflate the rate.
     *
     * @param int[] $userIds
     * @return array{percent:float|null,sample:int}
     */
    private function onTimeCompletion(array $userIds, string $from, string $to): array
    {
        if ($userIds === []) {
            return ['percent' => null, 'sample' => 0];
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total, SUM(CASE WHEN f.finished_at <= t.due_at THEN 1 ELSE 0 END) AS on_time
             FROM (' . $this->firstFinishSubquery($placeholders) . ') f
             JOIN tasks t ON t.id = f.task_id
             WHERE t.due_at IS NOT NULL AND f.finished_at >= ? AND f.finished_at <= ?'
        );
        $stmt->execute(array_merge($userIds, [$from, $to]));
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $total = (int)($row['total'] ?? 0);

        return [
            'percent' => $total > 0 ? round((int)($row['on_time'] ?? 0) / $total * 100, 1) : null,
            'sample' => $total,
        ];
    }

    /**
     * Tasks that arrived (were created) inside the window - the arrival side of the
     * queue balance.
     *
     * @param int[] $userIds
     */
    private function countCreatedTasks(array $userIds, string $from, string $to): int
    {
        if ($userIds === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM tasks t
             WHERE t.assignee_user_id IN (' . $placeholders . ')
               AND t.deleted_at IS NULL AND t.archived_at IS NULL
               AND t.created_at >= ? AND t.created_at <= ?'
        );
        $stmt->execute(array_merge($userIds, [$from, $to]));

        return (int)$stmt->fetchColumn();
    }

    /**
     * Monday-based weekly series (completed + minutes), zero-filled backwards from
     * the current week so the caller can both draw a period chart and compare the
     * last four weeks with the four before them.
     *
     * @param int[] $userIds
     * @return array<int, array{week_start:string,completed:int,minutes:int}>
     */
    private function weeklySeries(array $userIds, string $from, string $to, int $weeks): array
    {
        if ($userIds === []) {
            return [];
        }

        $weeks = max(2, min(13, $weeks));
        $mondayTs = strtotime('monday this week', strtotime($to));
        if ($mondayTs === false) {
            return [];
        }

        $buckets = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $key = gmdate('Y-m-d', $mondayTs - $i * 604800);
            $buckets[$key] = ['week_start' => $key, 'completed' => 0, 'minutes' => 0];
        }

        $seriesStart = gmdate('Y-m-d 00:00:00', strtotime((string)array_key_first($buckets) . ' 00:00:00'));
        if (strtotime($from) > strtotime($seriesStart)) {
            $seriesStart = $from;
        }

        $dayMinutes = [];
        foreach ($this->dailyMinutes($userIds, $seriesStart, $to) as $day) {
            $dayMinutes[(string)$day['date']] = (int)$day['minutes'];
        }
        $completedByDay = $this->completedByDay($userIds, $seriesStart, $to);

        foreach ($dayMinutes as $day => $minutes) {
            $key = $this->weekKeyFor($day, $mondayTs);
            if ($key !== null && isset($buckets[$key])) {
                $buckets[$key]['minutes'] += $minutes;
            }
        }
        foreach ($completedByDay as $day => $count) {
            $key = $this->weekKeyFor($day, $mondayTs);
            if ($key !== null && isset($buckets[$key])) {
                $buckets[$key]['completed'] += $count;
            }
        }

        return array_values($buckets);
    }

    /**
     * Completions per calendar day (first terminal transition).
     *
     * @param int[] $userIds
     * @return array<string,int>
     */
    private function completedByDay(array $userIds, string $from, string $to): array
    {
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT SUBSTR(f.finished_at, 1, 10) AS day, COUNT(*) AS tasks
             FROM (' . $this->firstFinishSubquery($placeholders) . ') f
             WHERE f.finished_at >= ? AND f.finished_at <= ?
             GROUP BY SUBSTR(f.finished_at, 1, 10)'
        );
        $stmt->execute(array_merge($userIds, [$from, $to]));

        $byDay = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byDay[(string)$row['day']] = (int)$row['tasks'];
        }

        return $byDay;
    }

    /**
     * First terminal transition per task for the given assignee scope. Shared by
     * the on-time, weekly-series and arrival queries so all three agree on what
     * "finished" means.
     */
    private function firstFinishSubquery(string $assigneePlaceholders): string
    {
        // An empty placeholder list means "root scope" - the condition is dropped
        // instead of emitting an invalid `IN ()`.
        $scope = $assigneePlaceholders === ''
            ? ''
            : ' AND t2.assignee_user_id IN (' . $assigneePlaceholders . ')';

        return 'SELECT h.task_id AS task_id, MIN(h.created_at) AS finished_at
                FROM task_status_history h
                JOIN tasks t2 ON t2.id = h.task_id
                WHERE h.new_status IN (' . TaskStatusSemantics::completedLiteralList($this->pdo) . ')
                  AND t2.deleted_at IS NULL' . $scope . '
                GROUP BY h.task_id';
    }

    /** Monday bucket key (Y-m-d) for a day inside the reported window. */
    private function weekKeyFor(string $day, int $mondayTs): ?string
    {
        $ts = strtotime($day . ' 00:00:00');
        if ($ts === false) {
            return null;
        }

        return gmdate('Y-m-d', (int)(floor(($ts - $mondayTs) / 604800) * 604800 + $mondayTs));
    }

    /**
     * Active/overdue task counts for many users in one query.
     *
     * @param int[] $userIds
     * @return array<int, array{active:int,overdue:int}>
     */
    private function assignedTaskCountsByUser(array $userIds, string $now): array
    {
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $terminal = TaskStatusSemantics::terminalLiteralList($this->pdo);
        $stmt = $this->pdo->prepare(
            'SELECT t.assignee_user_id AS uid,
                    SUM(CASE WHEN t.status_code NOT IN (' . $terminal . ') THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN t.status_code NOT IN (' . $terminal . ')
                              AND t.due_at IS NOT NULL AND t.due_at < ? THEN 1 ELSE 0 END) AS overdue
             FROM tasks t
             WHERE t.deleted_at IS NULL AND t.archived_at IS NULL
               AND t.assignee_user_id IN (' . $placeholders . ')
             GROUP BY t.assignee_user_id'
        );
        $stmt->execute(array_merge([$now], $userIds));

        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(int)$row['uid']] = ['active' => (int)$row['active'], 'overdue' => (int)$row['overdue']];
        }

        return $counts;
    }

    /**
     * Logged minutes for many users in one query.
     *
     * @param int[] $userIds
     * @return array<int,int>
     */
    private function loggedMinutesByUser(array $userIds, string $from, string $to): array
    {
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT w.user_id AS uid, COALESCE(SUM(w.minutes_spent), 0) AS minutes
             FROM work_logs w
             WHERE w.user_id IN (' . $placeholders . ') AND w.logged_at >= ? AND w.logged_at <= ?
             GROUP BY w.user_id'
        );
        $stmt->execute(array_merge($userIds, [$from, $to]));

        $minutes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $minutes[(int)$row['uid']] = (int)$row['minutes'];
        }

        return $minutes;
    }

    /**
     * Completed task counts for many users in one query.
     *
     * @param int[] $userIds
     * @return array<int,int>
     */
    private function completedTasksByUser(array $userIds, string $from, string $to): array
    {
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT t.assignee_user_id AS uid, COUNT(DISTINCT t.id) AS tasks
             FROM task_status_history h
             JOIN tasks t ON t.id = h.task_id
             WHERE h.new_status IN (' . TaskStatusSemantics::completedLiteralList($this->pdo) . ')
               AND h.created_at >= ? AND h.created_at <= ?
               AND t.deleted_at IS NULL
               AND t.assignee_user_id IN (' . $placeholders . ')
             GROUP BY t.assignee_user_id'
        );
        $stmt->execute(array_merge([$from, $to], $userIds));

        $completed = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $completed[(int)$row['uid']] = (int)$row['tasks'];
        }

        return $completed;
    }

    /** Tasks (any status) created before a timestamp, in scope - the WIP baseline. */
    private function countTasksBefore(array $userIds, bool $isRoot, string $before): int
    {
        $scopeSql = '';
        $params = [$before];
        if (!$isRoot) {
            if ($userIds === []) {
                return 0;
            }
            $scopeSql = ' AND t.assignee_user_id IN (' . implode(', ', array_fill(0, count($userIds), '?')) . ')';
            $params = array_merge($params, $userIds);
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM tasks t
             WHERE t.deleted_at IS NULL AND t.archived_at IS NULL AND t.created_at < ?' . $scopeSql
        );
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /** Tasks finished before a timestamp, in scope - the second WIP baseline. */
    private function countFinishedBefore(array $userIds, bool $isRoot, string $before): int
    {
        $placeholders = '';
        $params = [];
        if (!$isRoot) {
            if ($userIds === []) {
                return 0;
            }
            $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
            $params = $userIds;
        }
        // Placeholder order follows the SQL text: the scope filter lives inside the
        // derived table, the window boundary is the outer predicate.
        $params[] = $before;

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM (' . $this->firstFinishSubquery($placeholders) . ') f WHERE f.finished_at < ?'
        );
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Created tasks per day inside the window, in scope.
     *
     * @param int[] $userIds
     * @return array<string,int>
     */
    private function createdTasksByDay(array $userIds, bool $isRoot, string $from, string $to): array
    {
        $scopeSql = '';
        $params = [$from, $to];
        if (!$isRoot) {
            if ($userIds === []) {
                return [];
            }
            $scopeSql = ' AND t.assignee_user_id IN (' . implode(', ', array_fill(0, count($userIds), '?')) . ')';
            $params = array_merge($params, $userIds);
        }

        $stmt = $this->pdo->prepare(
            'SELECT SUBSTR(t.created_at, 1, 10) AS day, COUNT(*) AS tasks FROM tasks t
             WHERE t.deleted_at IS NULL AND t.archived_at IS NULL
               AND t.created_at >= ? AND t.created_at <= ?' . $scopeSql . '
             GROUP BY SUBSTR(t.created_at, 1, 10)'
        );
        $stmt->execute($params);

        $byDay = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byDay[(string)$row['day']] = (int)$row['tasks'];
        }

        return $byDay;
    }

    /** Live open queue (non-terminal tasks) in scope. */
    private function countOpenTasks(array $userIds, bool $isRoot): int
    {
        $scopeSql = '';
        $params = [];
        if (!$isRoot) {
            if ($userIds === []) {
                return 0;
            }
            $scopeSql = ' AND t.assignee_user_id IN (' . implode(', ', array_fill(0, count($userIds), '?')) . ')';
            $params = $userIds;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM tasks t
             WHERE t.deleted_at IS NULL AND t.archived_at IS NULL
               AND t.status_code NOT IN (' . TaskStatusSemantics::terminalLiteralList($this->pdo) . ')' . $scopeSql
        );
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
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
