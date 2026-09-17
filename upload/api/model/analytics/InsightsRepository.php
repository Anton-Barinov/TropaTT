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
 *
 * **One rule for logged time: only live tasks count.** Every aggregate that reads
 * `work_logs` joins `tasks` and skips rows whose task is soft-deleted or archived,
 * so the whole widget family reports the same period. Deleted tasks keep their log
 * rows, and counting them was wrong twice over: a card ranked them (the link
 * answered 404), and their hours dominated totals nobody could explain - on the
 * demo the entire top-10 of `tasks_actual_time` came from deleted test runs, led by
 * a single 1 000 344-minute row that set the average at 83 481 minutes. The
 * trade-off is deliberate: time logged on a task that is deleted afterwards leaves
 * the task analytics (it is still in the work-log list itself).
 */
final class InsightsRepository
{
    /** Safety cap for in-PHP median calculations over per-task aggregates. */
    private const MAX_MEDIAN_ROWS = 2000;

    /** Weekly capacity every load metric is measured against (40 h). */
    public const WEEK_CAPACITY_MINUTES = 2400;

    /** Longest day breakdown returned for a widget period. */
    private const MAX_DAILY_POINTS = 30;

    /**
     * Fewest estimated-and-logged tasks before an estimate set is reported at all.
     * A "minutes per point" rate from a single task is a coin flip, not a rate.
     */
    private const MIN_ESTIMATE_SAMPLE = 3;

    /**
     * Forecast confidence thresholds, in completions observed over the window the
     * projection averages. Below `MIN` - or with fewer than two weeks that produced
     * anything at all - the payload reports "little data" and withholds the
     * projected date instead of printing one derived from noise.
     */
    private const FORECAST_MIN_SAMPLE_COMPLETIONS = 3;
    private const FORECAST_HIGH_SAMPLE_COMPLETIONS = 8;

    /**
     * Cycle statuses the sprint view treats as finished.
     * Mirrors `CycleTaskRepository::COMPLETED_OR_ARCHIVED`: an archived task counts
     * as done for its cycle, a cancelled one deliberately does not.
     */
    private const SPRINT_COMPLETED_CODES = ['done', 'completed', 'closed', 'archived'];

    /** Completion weeks a sprint projection is allowed to be scored on. */
    private const SPRINT_SAMPLE_WEEKS = 4;

    /**
     * Load bands the rebalancing advice is built on, as a share of the weekly
     * capacity. These are the historical hardcoded values: the organisation can
     * override both through settings, and the payload always reports which pair
     * was in force, so the card's legend cannot describe a threshold the numbers
     * did not use.
     */
    public const DEFAULT_OVERLOAD_PERCENT = 110;
    public const DEFAULT_UNDERLOAD_PERCENT = 50;

    /** An SLA deadline this close counts as at risk, not yet missed. */
    private const SLA_SOON_DAYS = 3;

    /**
     * How close a deadline has to be before a stream is flagged `soon`.
     * Seven days is the window the project milestones summary already calls
     * "upcoming", so the two screens agree on what "на этой неделе" means.
     */
    private const SCHEDULE_SOON_DAYS = 7;

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
     * The weekly capacity the load percentage is measured against is supplied by
     * the caller (`capacity_minutes_week`) because the organisation's working week
     * is a service-level fact (setting or business calendar); the repository only
     * falls back to the 40-hour default when nobody told it otherwise.
     *
     * @param array{now:string,week_start:string,period_start:string,previous_start?:string,period_days?:int,capacity_minutes_week?:int,load_thresholds?:array<string,int>} $window
     * @return array<string,mixed>
     */
    public function personalLoad(int $userId, array $window): array
    {
        $now = (string)$window['now'];
        $weekStart = (string)$window['week_start'];
        $periodStart = (string)$window['period_start'];
        $previousStart = (string)($window['previous_start'] ?? $periodStart);
        $periodDays = max(1, (int)($window['period_days'] ?? 30));
        $capacityMinutes = self::normalizeCapacity($window['capacity_minutes_week'] ?? null);

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
        $loadPercent = round($minutesWeek / $capacityMinutes * 100, 1);

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
            'capacity_minutes_week' => $capacityMinutes,
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
            'load_signal' => self::loadSignal($loadPercent, self::bands($window['load_thresholds'] ?? null)),
            'daily_minutes' => $this->dailyMinutes([$userId], $weekStart, $now),
            'period_daily_minutes' => $periodDailyMinutes,
            'period_daily_bucket_days' => $periodDailyBucketDays,
        ];
    }

    /**
     * Actual execution time of tasks across the visible scope.
     *
     * `p90_minutes` and `max_minutes` sit next to the median because the average
     * alone is not honest here: a single task carrying a million logged minutes (real
     * data on the demo) pulls the mean to 83 481 minutes against a median of 135. The
     * three numbers together say "typical", "bad case" and "the outlier you have".
     *
     * Estimates are deliberately **not** compared with minutes. `task_estimates`
     * stores story points, t-shirt sizes, complexity, risk or bug severity - never
     * hours - so "estimation vs fact" as minutes cannot be computed without inventing
     * a conversion. What the data does support is reported instead: how many logged
     * tasks carry an estimate at all, and per estimate set either the cooling rate
     * (minutes per point) or, for a set whose unit really is hours, the overrun.
     * Sets priced in a currency are skipped: points of money cannot be divided by time.
     *
     * @param int[] $userIds visible user ids; ignored when $isRoot is true
     * @param string|null $projectPublicId narrows every query to one project; the caller
     *        must have checked access, because the repository only trusts the id it is given
     * @return array<string,mixed>
     */
    public function actualTime(
        array $userIds,
        bool $isRoot,
        string $periodStart,
        string $now,
        int $limit = 10,
        ?string $projectPublicId = null
    ): array {
        $limit = max(1, min(50, $limit));
        $projectPublicId = $projectPublicId !== null && trim($projectPublicId) !== '' ? trim($projectPublicId) : null;
        $perTask = $this->minutesPerTask($userIds, $isRoot, $periodStart, $now, $projectPublicId);

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
        $activeWithoutLogs = $this->countActiveTasksWithoutLogs($userIds, $isRoot, $loggedTaskIds, $projectPublicId);
        $coverageBase = $tasksWithLogs + $activeWithoutLogs;
        $covered = $coverageBase > 0 ? round($tasksWithLogs / $coverageBase * 100, 1) : 0.0;

        $minutesByTask = [];
        foreach ($perTask as $row) {
            $minutesByTask[(string)$row['task_public_id']] = (int)$row['minutes'];
        }
        $estimatedTaskIds = $this->taskIdsWithActiveEstimate($loggedTaskIds);
        $estimateCoverage = $tasksWithLogs > 0
            ? round(count($estimatedTaskIds) / $tasksWithLogs * 100, 1)
            : null;

        // Computed once: the card shows the five worst overruns while the head says how
        // many there are in total, so the number and the list can never disagree.
        $overruns = $this->estimateOverruns($minutesByTask);

        return [
            'top_tasks' => $topTasks,
            'tasks_with_logs' => $tasksWithLogs,
            'active_without_logs' => $activeWithoutLogs,
            'active_without_logs_list' => $this->activeTasksWithoutLogsList($userIds, $isRoot, $loggedTaskIds, 5, $projectPublicId),
            'covered_percent' => $covered,
            'total_minutes' => $totalMinutes,
            'median_minutes' => self::median($durations),
            'average_minutes' => $durations !== [] ? (int)round($totalMinutes / count($durations)) : 0,
            'p90_minutes' => self::percentile($durations, 90),
            'max_minutes' => $durations !== [] ? max($durations) : 0,
            'estimate_coverage_percent' => $estimateCoverage,
            'estimated_tasks' => count($estimatedTaskIds),
            // The list is capped; the total is not, so the card can say "5 of 23"
            // instead of implying the scope only holds five unestimated tasks.
            'tasks_without_estimate_total' => max(0, $tasksWithLogs - count($estimatedTaskIds)),
            'tasks_without_estimate' => $this->tasksWithoutEstimate($loggedTaskIds, $estimatedTaskIds, $minutesByTask),
            'estimate_sets' => $this->estimateCalibration($minutesByTask),
            // "Where did we overrun the plan?" is the question the card could not answer.
            // A task-level answer only exists for estimates whose unit really is time, so
            // the list is built from those sets and never from a points-to-hours guess.
            'estimate_overruns' => array_slice($overruns, 0, 5),
            'estimate_overruns_total' => count($overruns),
            'by_activity' => $this->minutesByActivity($userIds, $isRoot, $periodStart, $now, $projectPublicId),
            'limit' => $limit,
            'project_filter' => $projectPublicId,
        ];
    }

    /**
     * Percentile of a series of per-task minutes.
     *
     * Nearest-rank on the ascending series, computed on the full set the caller passes
     * in (never on a truncated list) so the number describes the period rather than the
     * rendered page. Empty input answers 0 instead of failing.
     *
     * @param int[] $values
     */
    public static function percentile(array $values, int $percent): int
    {
        if ($values === []) {
            return 0;
        }
        sort($values);
        $percent = max(1, min(100, $percent));
        $index = (int)ceil(count($values) * $percent / 100) - 1;

        return (int)$values[max(0, min(count($values) - 1, $index))];
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
        $capacityMinutes = self::normalizeCapacity($window['capacity_minutes_week'] ?? null);
        $bands = self::bands($window['load_thresholds'] ?? null);

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
        // SLA risk is what turns "this person has a lot of work" into "this person
        // is about to miss a commitment", which is what a hand-over has to be
        // justified with.
        $slaRiskByUser = $this->slaRiskTaskCountsByUser($userIds, $now);

        $rows = [];
        foreach ($users as $user) {
            $userId = (int)$user['user_id'];
            $active = (int)($taskCounts[$userId]['active'] ?? 0);
            $overdue = (int)($taskCounts[$userId]['overdue'] ?? 0);
            $minutesWeek = (int)($minutesByUser[$userId] ?? 0);
            $completed = (int)($completedByUser[$userId] ?? 0);
            $loadPercent = round($minutesWeek / $capacityMinutes * 100, 1);
            $efficiency = ($completed + $overdue) > 0
                ? round($completed / ($completed + $overdue) * 100, 1)
                : 0.0;
            // Someone with no work and no logs is not "underloaded" - there is
            // simply nothing to measure yet. The card needs to tell those apart.
            $hasData = $minutesWeek > 0 || $active > 0 || $completed > 0;

            $rows[] = [
                'user_id' => $userId,
                'user_public_id' => (string)$user['public_id'],
                'login' => (string)$user['login'],
                'full_name' => (string)($user['full_name'] ?? ''),
                'active_tasks' => $active,
                'overdue_tasks' => $overdue,
                'sla_risk_tasks' => (int)($slaRiskByUser[$userId] ?? 0),
                'minutes_week' => $minutesWeek,
                'completed_period' => $completed,
                'load_percent' => $loadPercent,
                'efficiency_percent' => $efficiency,
                'has_data' => $hasData,
                'signal' => $hasData ? self::workloadSignal($loadPercent, $overdue, $bands) : 'no_data',
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
                    'blocked_count' => 0,
                    'forecast' => [
                        'average_per_week' => 0.0,
                        'open_tasks' => 0,
                        'weeks_to_finish' => null,
                        'finish_date' => null,
                        'confidence' => 'none',
                        'sample_completions' => 0,
                        'sample_weeks' => 0,
                    ],
                ];
            }
            $scopeSql = ' AND t.assignee_user_id IN (' . implode(', ', array_fill(0, count($userIds), '?')) . ')';
            $scopeParams = $userIds;
        }

        $weekStartTs = $this->mondayTsUtc($now);
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
        // blunt - the UI presents it as an estimate, not a commitment - and honest
        // about the sample behind it: a projection drawn from one completion in four
        // weeks used to print a confident «60 нед · финиш 2027-11-10», so the date is
        // now withheld until the history supports it (`forecast.confidence`).
        $recent = array_slice($throughput, -4);
        $previousWindow = array_slice($throughput, -8, -4);
        $average = $recent !== [] ? array_sum($recent) / count($recent) : 0.0;
        $previousAverage = count($previousWindow) === 4 ? array_sum($previousWindow) / 4 : 0.0;
        $weeksToFinish = $average > 0 ? round($openTasks / $average, 1) : null;
        $sampleCompletions = array_sum($recent);
        $sampleWeeks = count(array_filter($recent, static fn(int $completed): bool => $completed > 0));
        $confidence = self::forecastConfidence($sampleCompletions, $sampleWeeks);
        $trustworthy = $confidence === 'medium' || $confidence === 'high';

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
            // Blocked work is not "in progress": the WIP tile says how much of the
            // queue cannot move, so an open but stuck task stops reading as work.
            'blocked_count' => $this->countBlockedOpenTasks($userIds, $isRoot),
            'forecast' => [
                'average_per_week' => round($average, 1),
                'open_tasks' => $openTasks,
                'weeks_to_finish' => $trustworthy ? $weeksToFinish : null,
                'finish_date' => $trustworthy && $weeksToFinish !== null
                    ? gmdate('Y-m-d', strtotime($now) + (int)round($weeksToFinish * 604800))
                    : null,
                'confidence' => $confidence,
                'sample_completions' => $sampleCompletions,
                'sample_weeks' => $sampleWeeks,
            ],
        ];
    }

    /**
     * Active sprints in the actor's scope, with the metrics the velocity card needs
     * to answer «успеем ли в этом спринте?».
     *
     * Weekly throughput answers a planning question six weeks out; the sprint view
     * answers the one a team asks on Monday. Each active cycle reports what it holds,
     * what finished, what is still open (and how much of that is blocked), and a
     * projection over the cycle's own completion history - under the same confidence
     * rule as the weekly forecast, so a sprint that produced two completions does not
     * print a finish date either.
     *
     * Visibility is applied twice on purpose: cycles come from the actor's accessible
     * projects, and their tasks from the actor's visible users, so neither another
     * team's sprint nor a stranger's task can reach the payload.
     *
     * @param string[] $accessibleProjectPublicIds empty when $isRoot is true
     * @param int[] $userIds empty when $isRoot is true
     * @return array{active_cycles:int,cycles:array<int,array<string,mixed>>}
     */
    public function currentSprintVelocity(array $accessibleProjectPublicIds, bool $isRoot, array $userIds, string $now): array
    {
        $empty = ['active_cycles' => 0, 'cycles' => []];
        if (!$isRoot && ($accessibleProjectPublicIds === [] || $userIds === [])) {
            return $empty;
        }

        $where = "c.status = 'active' AND c.archived_at IS NULL AND c.deleted_at IS NULL";
        $params = [];
        if (!$isRoot) {
            $where .= ' AND p.public_id IN (' . implode(', ', array_fill(0, count($accessibleProjectPublicIds), '?')) . ')';
            $params = array_values($accessibleProjectPublicIds);
        }

        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.public_id, c.title, c.start_at, c.end_at,
                    p.public_id AS project_public_id, p.title AS project_title
             FROM work_cycles c
             JOIN projects p ON p.id = c.project_id
             WHERE ' . $where . '
             ORDER BY c.end_at IS NULL ASC, c.end_at ASC'
        );
        $stmt->execute($params);
        $cycles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($cycles === []) {
            return $empty;
        }

        $cycleIds = array_map(static fn(array $row): int => (int)$row['id'], $cycles);
        $idPlaceholders = implode(', ', array_fill(0, count($cycleIds), '?'));
        $scopeSql = '';
        $scopeParams = [];
        if (!$isRoot) {
            $scopeSql = ' AND t.assignee_user_id IN (' . implode(', ', array_fill(0, count($userIds), '?')) . ')';
            $scopeParams = array_values($userIds);
        }

        // One pass for every active cycle instead of a query per card refresh.
        $taskStmt = $this->pdo->prepare(
            'SELECT ct.cycle_id, t.id AS task_id, t.status_code, t.created_at
             FROM cycle_tasks ct
             JOIN tasks t ON t.id = ct.task_id
             WHERE ct.deleted_at IS NULL AND ct.cycle_id IN (' . $idPlaceholders . ')
               AND t.deleted_at IS NULL AND t.archived_at IS NULL' . $scopeSql
        );
        $taskStmt->execute(array_merge($cycleIds, $scopeParams));

        // Completion timestamps come from history (`tasks` has no completed_at) and are
        // bucketed into weeks in PHP, so the sprint projection is scored on exactly the
        // same confidence rule as the weekly one.
        $historyStmt = $this->pdo->prepare(
            'SELECT ct.cycle_id, h.task_id, MIN(h.created_at) AS finished_at
             FROM task_status_history h
             JOIN cycle_tasks ct ON ct.task_id = h.task_id AND ct.deleted_at IS NULL
             JOIN tasks t ON t.id = h.task_id
             WHERE ct.cycle_id IN (' . $idPlaceholders . ')
               AND h.new_status IN (' . TaskStatusSemantics::completedLiteralList($this->pdo) . ')
               AND t.deleted_at IS NULL AND t.archived_at IS NULL' . $scopeSql . '
             GROUP BY ct.cycle_id, h.task_id'
        );
        $historyStmt->execute(array_merge($cycleIds, $scopeParams));

        $completionWeeks = [];
        foreach ($historyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $finishedTs = strtotime((string)$row['finished_at']);
            if ($finishedTs === false) {
                continue;
            }
            $week = gmdate('Y-m-d', $this->mondayTsUtc(gmdate('Y-m-d H:i:s', $finishedTs)));
            $completionWeeks[(int)$row['cycle_id']][$week] = true;
        }

        $tasksByCycle = [];
        foreach ($taskStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $tasksByCycle[(int)$row['cycle_id']][] = $row;
        }

        $nowTs = strtotime($now) ?: time();
        $out = [];
        foreach ($cycles as $cycle) {
            $cycleId = (int)$cycle['id'];
            $startTs = $cycle['start_at'] !== null ? strtotime((string)$cycle['start_at']) : false;
            $endTs = $cycle['end_at'] !== null ? strtotime((string)$cycle['end_at']) : false;

            $total = 0;
            $completed = 0;
            $open = 0;
            $blocked = 0;
            $created = 0;
            foreach (($tasksByCycle[$cycleId] ?? []) as $task) {
                $total++;
                $code = strtolower(trim((string)$task['status_code']));
                if (in_array($code, self::SPRINT_COMPLETED_CODES, true)) {
                    $completed++;
                } else {
                    $open++;
                    if ($code === 'blocked') {
                        $blocked++;
                    }
                }
                if ($startTs !== false) {
                    $createdTs = strtotime((string)$task['created_at']);
                    if ($createdTs !== false && $createdTs >= $startTs) {
                        $created++;
                    }
                }
            }

            $sampleWeeks = min(self::SPRINT_SAMPLE_WEEKS, count($completionWeeks[$cycleId] ?? []));
            $confidence = self::forecastConfidence($completed, $sampleWeeks);
            $trustworthy = $confidence === 'medium' || $confidence === 'high';
            $weeksElapsed = $startTs !== false ? max(0.5, ($nowTs - $startTs) / 604800) : 1.0;
            $average = $completed / $weeksElapsed;
            $weeksToFinish = $average > 0 ? round($open / $average, 1) : null;

            $out[] = [
                'cycle_public_id' => (string)$cycle['public_id'],
                'title' => (string)($cycle['title'] ?? ''),
                'project_public_id' => (string)$cycle['project_public_id'],
                'project_title' => (string)($cycle['project_title'] ?? ''),
                'start_at' => $cycle['start_at'],
                'end_at' => $cycle['end_at'],
                'days_left' => $endTs !== false ? (int)floor(($endTs - $nowTs) / 86400) : null,
                'days_elapsed' => $startTs !== false ? max(0, (int)floor(($nowTs - $startTs) / 86400)) : null,
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'created_tasks' => $created,
                'wip' => $open,
                'blocked_tasks' => $blocked,
                'progress_percent' => $total > 0 ? round($completed / $total * 100, 1) : 0.0,
                'forecast' => [
                    'average_per_week' => round($average, 1),
                    'remaining' => $open,
                    'weeks_to_finish' => $trustworthy ? $weeksToFinish : null,
                    'finish_date' => $trustworthy && $weeksToFinish !== null
                        ? gmdate('Y-m-d', $nowTs + (int)round($weeksToFinish * 604800))
                        : null,
                    'confidence' => $confidence,
                    'sample_completions' => $completed,
                    'sample_weeks' => $sampleWeeks,
                ],
            ];
        }

        return ['active_cycles' => count($out), 'cycles' => $out];
    }

    /**
     * How much a projection can be trusted, from the sample it was built on.
     *
     * `none` (nothing completed) and `low` (a handful of completions, or a single
     * productive week) mean the caller withholds the date and says why instead of
     * presenting a straight line through noise as a plan.
     */
    public static function forecastConfidence(int $completions, int $sampleWeeks): string
    {
        if ($completions <= 0) {
            return 'none';
        }
        if ($completions < self::FORECAST_MIN_SAMPLE_COMPLETIONS || $sampleWeeks < 2) {
            return 'low';
        }

        return $completions < self::FORECAST_HIGH_SAMPLE_COMPLETIONS ? 'medium' : 'high';
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
                       p.team_public_id, p.client_public_id,
                       tm.title AS team_title,
                       cp.title AS client_title,
                       COUNT(t.id) AS total_tasks,
                       SUM(CASE WHEN t.status_code IN (' . $completedSql . ') THEN 1 ELSE 0 END) AS completed_tasks,
                       SUM(CASE WHEN t.status_code NOT IN (' . $terminalSql . ') THEN 1 ELSE 0 END) AS active_tasks,
                       SUM(CASE WHEN t.status_code NOT IN (' . $terminalSql . ')
                                  AND t.due_at IS NOT NULL AND t.due_at < ? THEN 1 ELSE 0 END) AS overdue_tasks,
                       COUNT(DISTINCT CASE WHEN t.assignee_user_id IS NOT NULL THEN t.assignee_user_id END) AS members,
                       COALESCE(wl.minutes, 0) AS minutes_period,
                       ms.next_milestone_at,
                       ms.overdue_milestones,
                       ms.earliest_overdue_milestone_at,
                       dl.next_task_due_at,
                       dl.earliest_overdue_task_at
                FROM projects p
                LEFT JOIN teams tm ON tm.public_id = p.team_public_id
                LEFT JOIN counterparties cp ON cp.public_id = p.client_public_id
                LEFT JOIN tasks t ON t.project_id = p.id AND t.deleted_at IS NULL AND t.archived_at IS NULL
                LEFT JOIN (SELECT t.project_id AS pid, COALESCE(SUM(w.minutes_spent), 0) AS minutes
                             FROM work_logs w
                             JOIN tasks t ON t.id = w.task_id AND t.deleted_at IS NULL AND t.archived_at IS NULL
                            WHERE w.logged_at >= ?
                            GROUP BY t.project_id) wl ON wl.pid = p.id
                -- One pass per source: a completed milestone is not a deadline any more,
                -- so `status <> \'done\'` (the same rule as ProjectSummaryRepository) is
                -- what makes both "next" and "missed" honest.
                LEFT JOIN (SELECT m.project_id AS pid,
                                  MIN(CASE WHEN m.due_at >= ? THEN m.due_at END) AS next_milestone_at,
                                  SUM(CASE WHEN m.due_at < ? THEN 1 ELSE 0 END) AS overdue_milestones,
                                  MIN(CASE WHEN m.due_at < ? THEN m.due_at END) AS earliest_overdue_milestone_at
                             FROM milestones m
                            WHERE m.due_at IS NOT NULL AND m.status <> \'done\'
                            GROUP BY m.project_id) ms ON ms.pid = p.id
                LEFT JOIN (SELECT t2.project_id AS pid,
                                  MIN(CASE WHEN t2.due_at >= ? THEN t2.due_at END) AS next_task_due_at,
                                  MIN(CASE WHEN t2.due_at < ? THEN t2.due_at END) AS earliest_overdue_task_at
                             FROM tasks t2
                            WHERE t2.deleted_at IS NULL AND t2.archived_at IS NULL
                              AND t2.due_at IS NOT NULL
                              AND t2.status_code NOT IN (' . $terminalSql . ')
                            GROUP BY t2.project_id) dl ON dl.pid = p.id
                WHERE p.archived_at IS NULL';
        $params = [$now, $periodStart, $now, $now, $now, $now, $now];

        if (!$isRoot) {
            if ($accessibleProjectPublicIds === []) {
                return [];
            }
            $sql .= ' AND p.public_id IN (' . implode(', ', array_fill(0, count($accessibleProjectPublicIds), '?')) . ')';
            $params = array_merge($params, $accessibleProjectPublicIds);
        }
        $sql .= ' GROUP BY p.id, p.public_id, p.title, p.status_code, p.team_public_id, p.client_public_id,'
            . ' tm.title, cp.title, wl.minutes, ms.next_milestone_at, ms.overdue_milestones,'
            . ' ms.earliest_overdue_milestone_at, dl.next_task_due_at, dl.earliest_overdue_task_at'
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

            $nextMilestoneAt = self::nonEmptyString($row['next_milestone_at'] ?? null);
            $variance = self::scheduleVariance([
                'next_milestone_at' => $nextMilestoneAt,
                'next_task_due_at' => self::nonEmptyString($row['next_task_due_at'] ?? null),
                'overdue_milestones' => (int)($row['overdue_milestones'] ?? 0),
                'earliest_overdue_milestone_at' => self::nonEmptyString($row['earliest_overdue_milestone_at'] ?? null),
                'overdue_tasks' => $overdue,
                'earliest_overdue_task_at' => self::nonEmptyString($row['earliest_overdue_task_at'] ?? null),
            ], $now);

            $rows[] = [
                'project_public_id' => (string)$row['public_id'],
                'title' => (string)$row['title'],
                'team_public_id' => self::nonEmptyString($row['team_public_id'] ?? null),
                'team_title' => self::nonEmptyString($row['team_title'] ?? null),
                'client_public_id' => self::nonEmptyString($row['client_public_id'] ?? null),
                'client_title' => self::nonEmptyString($row['client_title'] ?? null),
                'progress_percent' => $progress,
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'active_tasks' => $active,
                'overdue_tasks' => $overdue,
                'minutes_period' => (int)$row['minutes_period'],
                'members' => (int)$row['members'],
                'next_milestone_at' => $nextMilestoneAt,
                'overdue_milestones' => $variance['overdue_milestones'],
                'next_deadline_at' => $variance['next_deadline_at'],
                'next_deadline_source' => $variance['next_deadline_source'],
                'next_future_deadline_at' => $variance['next_future_deadline_at'],
                'days_to_deadline' => $variance['days_to_deadline'],
                'schedule_lag_days' => $variance['schedule_lag_days'],
                'schedule_state' => $variance['schedule_state'],
                'health' => self::projectHealth($active, $overdue, $nextMilestoneAt, $now),
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
     * Lightweight id/title list of the projects a filter may narrow to.
     *
     * `projectsOverview()` cannot be reused for a picker: it runs three grouped
     * passes over tasks, work logs and milestones, and a filter control needs none
     * of those numbers. This reads one indexed row per project, so the cost stays
     * with the project count rather than with the amount of work in them.
     *
     * @param string[] $accessibleProjectPublicIds empty when $isRoot is true
     * @return array<int, array{project_public_id:string,title:string}>
     */
    public function projectOptions(array $accessibleProjectPublicIds, bool $isRoot): array
    {
        $sql = 'SELECT p.public_id, p.title FROM projects p WHERE p.archived_at IS NULL';
        $params = [];
        if (!$isRoot) {
            if ($accessibleProjectPublicIds === []) {
                return [];
            }
            $sql .= ' AND p.public_id IN (' . implode(', ', array_fill(0, count($accessibleProjectPublicIds), '?')) . ')';
            $params = $accessibleProjectPublicIds;
        }
        $sql .= ' ORDER BY p.title ASC, p.public_id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(static function (array $row): array {
            return [
                'project_public_id' => (string)$row['public_id'],
                'title' => (string)$row['title'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
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
             JOIN tasks t ON t.id = w.task_id AND t.deleted_at IS NULL AND t.archived_at IS NULL
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
            'SELECT DISTINCT SUBSTR(w.logged_at, 1, 10) AS day
               FROM work_logs w
               JOIN tasks t ON t.id = w.task_id AND t.deleted_at IS NULL AND t.archived_at IS NULL
              WHERE w.user_id IN (' . $placeholders . ') AND w.logged_at >= ?'
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
    public static function rebalanceRecommendation(array $assignees, array $bands = []): ?array
    {
        $bands = self::bands($bands);
        $from = null;
        $to = null;
        foreach ($assignees as $row) {
            $load = (float)($row['load_percent'] ?? 0);
            $active = (int)($row['active_tasks'] ?? 0);
            if ($load > $bands['overload_percent'] && $active > 1 && ($from === null || $load > (float)$from['load_percent'])) {
                $from = $row;
            }
            // A recipient has to be able to take the work: nobody below the band who
            // is already late, and nobody whose own SLA commitments are burning. The
            // advice is a plan, not a way to move a problem sideways.
            $toEligible = (int)($row['overdue_tasks'] ?? 0) === 0 && (int)($row['sla_risk_tasks'] ?? 0) === 0;
            if ($load < $bands['underload_percent'] && $toEligible && ($to === null || $load < (float)$to['load_percent'])) {
                $to = $row;
            }
        }

        if ($from === null || $to === null || (string)$from['user_public_id'] === (string)$to['user_public_id']) {
            return null;
        }

        $active = (int)$from['active_tasks'];
        $excess = (int)floor(((float)$from['load_percent'] - 100) / 100 * $active);
        $fromMetrics = self::personMetrics($from);
        $toMetrics = self::personMetrics($to);

        // The factors are what makes the advice defensible: overload alone used to be
        // the whole story, so a person drowning in late work and broken SLA promises
        // looked exactly like somebody merely busy.
        $factors = ['overload', 'backlog'];
        if ($fromMetrics['overdue_tasks'] > 0) {
            $factors[] = 'overdue';
        }
        if ($fromMetrics['sla_risk_tasks'] > 0) {
            $factors[] = 'sla_risk';
        }
        $factors[] = 'spare_capacity';

        return [
            'reason' => 'rebalance_overload',
            // `reason_summary` is the plain-language version for logs and API consumers;
            // the card renders its own localised sentence from the factors and metrics.
            'reason_summary' => sprintf(
                'load %.1f%% (band >%d%%), %d active tasks, %d overdue, %d SLA at risk; recipient at %.1f%% with spare capacity',
                $fromMetrics['load_percent'],
                $bands['overload_percent'],
                $fromMetrics['active_tasks'],
                $fromMetrics['overdue_tasks'],
                $fromMetrics['sla_risk_tasks'],
                $toMetrics['load_percent']
            ),
            'reason_factors' => $factors,
            'reason_metrics' => [
                'overload_percent' => $bands['overload_percent'],
                'underload_percent' => $bands['underload_percent'],
                'from_load_percent' => $fromMetrics['load_percent'],
                'from_active_tasks' => $fromMetrics['active_tasks'],
                'from_overdue_tasks' => $fromMetrics['overdue_tasks'],
                'from_sla_risk_tasks' => $fromMetrics['sla_risk_tasks'],
                'to_load_percent' => $toMetrics['load_percent'],
                'to_active_tasks' => $toMetrics['active_tasks'],
                'to_overdue_tasks' => $toMetrics['overdue_tasks'],
                'to_sla_risk_tasks' => $toMetrics['sla_risk_tasks'],
            ],
            'tasks' => max(1, min($active - 1, $excess)),
            'from' => ['user_public_id' => (string)$from['user_public_id']] + $fromMetrics,
            'to' => ['user_public_id' => (string)$to['user_public_id']] + $toMetrics,
        ];
    }

    /**
     * The bottleneck the card falls back to when no hand-over can be advised.
     *
     * The recommendation used to be the only thing the block could say, and it is
     * silent whenever the pair is missing - which is most of the time. "Nobody is
     * overloaded" and "somebody is overloaded but there is nobody to hand work to"
     * are different answers, and the manager needs to see which one applies, plus
     * the busiest and the most overdue person even when there is no advice.
     *
     * @param array<int,array<string,mixed>> $assignees
     * @return array{most_loaded:?array<string,mixed>,most_overdue:?array<string,mixed>,blocked_reason:?string,overload_percent:int,underload_percent:int}
     */
    public static function bottleneck(array $assignees, array $bands = []): array
    {
        $bands = self::bands($bands);
        $loaded = null;
        $overdue = null;
        $overloaded = null;
        $recipient = null;
        foreach ($assignees as $row) {
            if (($row['has_data'] ?? true) === false) {
                continue;
            }
            $load = (float)($row['load_percent'] ?? 0);
            $late = (int)($row['overdue_tasks'] ?? 0);
            if ($loaded === null || $load > (float)$loaded['load_percent']) {
                $loaded = $row;
            }
            if ($late > 0 && ($overdue === null || $late > (int)$overdue['overdue_tasks'])) {
                $overdue = $row;
            }
            if ($load > $bands['overload_percent'] && (int)($row['active_tasks'] ?? 0) > 1) {
                $overloaded = true;
            }
            if ($load < $bands['underload_percent'] && $late === 0 && (int)($row['sla_risk_tasks'] ?? 0) === 0) {
                $recipient = true;
            }
        }

        // Why the advice is missing, so the card explains itself instead of going quiet.
        $blockedReason = null;
        if ($loaded === null) {
            $blockedReason = 'no_data';
        } elseif ($overloaded !== true) {
            $blockedReason = 'no_overload';
        } elseif ($recipient !== true) {
            $blockedReason = 'no_spare_capacity';
        } else {
            $blockedReason = 'same_person';
        }

        return [
            'most_loaded' => $loaded !== null ? self::personMetrics($loaded) : null,
            'most_overdue' => $overdue !== null ? self::personMetrics($overdue) : null,
            'blocked_reason' => $blockedReason,
            'overload_percent' => $bands['overload_percent'],
            'underload_percent' => $bands['underload_percent'],
        ];
    }

    /**
     * The readable half of an assignee row: numbers plus who the person is.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function personMetrics(array $row): array
    {
        return [
            'name' => (string)(($row['full_name'] ?? '') !== '' ? $row['full_name'] : ($row['login'] ?? '')),
            'load_percent' => (float)($row['load_percent'] ?? 0),
            'active_tasks' => (int)($row['active_tasks'] ?? 0),
            'overdue_tasks' => (int)($row['overdue_tasks'] ?? 0),
            'sla_risk_tasks' => (int)($row['sla_risk_tasks'] ?? 0),
        ];
    }

    public static function workloadSignal(float $loadPercent, int $overdue, array $bands = []): string
    {
        $bands = self::bands($bands);
        if ($loadPercent > $bands['overload_percent']) {
            return 'overload';
        }
        if ($loadPercent < $bands['underload_percent'] && $overdue === 0) {
            return 'underload';
        }

        return $overdue > 0 ? 'risk' : 'normal';
    }

    /**
     * Normalise a load band pair, falling back to the historical constants.
     *
     * Written as a reader rather than a validator: a broken band that reaches this
     * far must not turn every signal into an error or divide by nonsense, so an
     * unusable pair is discarded and the default applies (the service validates the
     * human-facing input where it is entered).
     *
     * @param mixed $bands
     * @return array{overload_percent:int,underload_percent:int}
     */
    public static function bands($bands): array
    {
        $overload = self::DEFAULT_OVERLOAD_PERCENT;
        $underload = self::DEFAULT_UNDERLOAD_PERCENT;
        if (!is_array($bands)) {
            return ['overload_percent' => $overload, 'underload_percent' => $underload];
        }

        $inputOverload = $bands['overload_percent'] ?? null;
        $inputUnderload = $bands['underload_percent'] ?? null;
        // Any positive band is a legitimate business choice: an organisation that
        // wants the alarm at 90 % (people also answer mail) may set it.
        if (is_numeric($inputOverload) && (int)$inputOverload >= 1) {
            $overload = (int)$inputOverload;
        }
        if (is_numeric($inputUnderload) && (int)$inputUnderload >= 0 && (int)$inputUnderload < $overload) {
            $underload = (int)$inputUnderload;
        }

        return ['overload_percent' => $overload, 'underload_percent' => $underload];
    }

    /**
     * Deadline and lag for one project row.
     *
     * The card could say "просрочено" and "ближайшая веха" but never "мы успеваем?":
     * a milestone is only one kind of deadline, and a project whose milestones are all
     * behind it still has task due dates ahead. Both sources are folded into a single
     * nearest deadline with its origin kept (`next_deadline_source`), plus how far away
     * it is and how long ago the earliest missed deadline was.
     *
     * `schedule_state` is deliberately explicit about the no-data case: `none` means
     * "this project has nothing scheduled", which is an answer, not a blank column.
     *
     * `next_deadline_at` is the nearest deadline to now in either direction, so a
     * project that has already missed its only deadline still names that date (with a
     * negative `days_to_deadline` and the lag beside it) instead of going blank.
     *
     * @param array<string,mixed> $row
     * @return array{next_deadline_at:?string,next_deadline_source:?string,days_to_deadline:?int,schedule_lag_days:?int,schedule_state:string,overdue_milestones:int}
     */
    public static function scheduleVariance(array $row, string $now): array
    {
        $milestoneAt = self::nonEmptyString($row['next_milestone_at'] ?? null);
        $taskDueAt = self::nonEmptyString($row['next_task_due_at'] ?? null);
        $overdueMilestones = (int)($row['overdue_milestones'] ?? 0);
        $overdueTasks = (int)($row['overdue_tasks'] ?? 0);

        // The milestone wins a tie: it is the commitment a client sees, while a task
        // due date is usually the internal plan that serves it.
        $futureDeadlineAt = null;
        $futureDeadlineSource = null;
        if ($milestoneAt !== null && ($taskDueAt === null || $milestoneAt <= $taskDueAt)) {
            $futureDeadlineAt = $milestoneAt;
            $futureDeadlineSource = 'milestone';
        } elseif ($taskDueAt !== null) {
            $futureDeadlineAt = $taskDueAt;
            $futureDeadlineSource = 'task';
        }
        $nextDeadlineAt = $futureDeadlineAt;
        $nextDeadlineSource = $futureDeadlineSource;

        $nowTs = strtotime($now);

        // Lag answers "how late are we", so it is measured from the earliest deadline
        // already missed - a milestone when there is one, a task due date otherwise.
        $earliestOverdueAt = $overdueMilestones > 0
            ? self::nonEmptyString($row['earliest_overdue_milestone_at'] ?? null)
            : null;
        $earliestOverdueSource = $earliestOverdueAt === null ? null : 'milestone';
        if ($earliestOverdueAt === null && $overdueTasks > 0) {
            $earliestOverdueAt = self::nonEmptyString($row['earliest_overdue_task_at'] ?? null);
            $earliestOverdueSource = $earliestOverdueAt === null ? null : 'task';
        }

        // Which deadline binds the project *first* is the earliest one still unresolved,
        // past or future: a row that prints "отставание 5 дн." without naming the date it
        // missed leaves the reader without the fact they came for. A missed deadline on
        // its own (nothing left ahead) is therefore named here too - and the payload
        // also carries the nearest date still ahead, which is what a portfolio line
        // answers "what is due next" from.
        if ($earliestOverdueAt !== null && ($nextDeadlineAt === null || $earliestOverdueAt < $nextDeadlineAt)) {
            $nextDeadlineAt = $earliestOverdueAt;
            $nextDeadlineSource = $earliestOverdueSource;
        }

        $lagDays = null;
        if ($earliestOverdueAt !== null && $nowTs !== false) {
            $overdueTs = strtotime($earliestOverdueAt);
            if ($overdueTs !== false) {
                $lagDays = max(0, (int)floor(($nowTs - $overdueTs) / 86400));
            }
        }

        // Measured after the missed deadline has taken over: that date is now the
        // nearest one, so its distance is negative rather than absent.
        $daysToDeadline = null;
        if ($nextDeadlineAt !== null && $nowTs !== false) {
            $deadlineTs = strtotime($nextDeadlineAt);
            if ($deadlineTs !== false) {
                $daysToDeadline = (int)floor(($deadlineTs - $nowTs) / 86400);
            }
        }

        if ($lagDays !== null || $overdueMilestones > 0 || $overdueTasks > 0) {
            $scheduleState = 'overdue';
        } elseif ($daysToDeadline !== null) {
            $scheduleState = $daysToDeadline <= self::SCHEDULE_SOON_DAYS ? 'soon' : 'ok';
        } else {
            $scheduleState = 'none';
        }

        return [
            'next_deadline_at' => $nextDeadlineAt,
            'next_deadline_source' => $nextDeadlineSource,
            'next_future_deadline_at' => $futureDeadlineAt,
            'days_to_deadline' => $daysToDeadline,
            'schedule_lag_days' => $lagDays,
            'schedule_state' => $scheduleState,
            'overdue_milestones' => $overdueMilestones,
        ];
    }

    /**
     * Portfolio roll-up over the rows the actor is allowed to see.
     *
     * The per-project table answered "which stream is late" but not "how late are we
     * as a whole", which is the question that decides whether the portfolio needs
     * attention. Computed from the already scoped rows, so it can never widen them.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array{projects:int,critical:int,risk:int,ok:int,overdue_tasks:int,overdue_milestones:int,without_deadline:int,next_deadline_at:?string,progress_percent:float}
     */
    public static function portfolioAggregates(array $rows): array
    {
        $aggregates = [
            'projects' => count($rows),
            'critical' => 0,
            'risk' => 0,
            'ok' => 0,
            'overdue_tasks' => 0,
            'overdue_milestones' => 0,
            'without_deadline' => 0,
            'next_deadline_at' => null,
            'progress_percent' => 0.0,
        ];
        $completed = 0;
        $tasks = 0;

        foreach ($rows as $row) {
            $health = (string)($row['health'] ?? 'ok');
            if (isset($aggregates[$health]) && is_int($aggregates[$health])) {
                $aggregates[$health]++;
            }
            $aggregates['overdue_tasks'] += (int)($row['overdue_tasks'] ?? 0);
            $aggregates['overdue_milestones'] += (int)($row['overdue_milestones'] ?? 0);
            if ((string)($row['schedule_state'] ?? 'none') === 'none') {
                $aggregates['without_deadline']++;
            }

            // The nearest date still *ahead*, not the earliest missed one: this tile
            // answers "what is due next", while work that is already late is reported as
            // late (the tile above counts it) and each row names its own missed date.
            $deadline = self::nonEmptyString($row['next_future_deadline_at'] ?? null);
            if ($deadline === null) {
                // A row from before the field existed carries one date only, and it is
                // trusted just as far as it is not in the past.
                $legacy = self::nonEmptyString($row['next_deadline_at'] ?? null);
                $days = $row['days_to_deadline'] ?? null;
                if ($legacy !== null && ($days === null || (int)$days >= 0)) {
                    $deadline = $legacy;
                }
            }
            if ($deadline !== null
                && ($aggregates['next_deadline_at'] === null || $deadline < $aggregates['next_deadline_at'])) {
                $aggregates['next_deadline_at'] = $deadline;
            }

            $completed += (int)($row['completed_tasks'] ?? 0);
            $tasks += (int)($row['total_tasks'] ?? 0);
        }

        // Weighted by tasks, not averaged over projects: a 3-task project must not
        // weigh as much as a 300-task one when the row says "прогресс портфеля".
        $aggregates['progress_percent'] = $tasks > 0 ? round($completed / $tasks * 100, 1) : 0.0;

        return $aggregates;
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string)$value);

        return $text === '' ? null : $text;
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
            "SELECT COALESCE(SUM(w.minutes_spent), 0)
               FROM work_logs w
               JOIN tasks t ON t.id = w.task_id AND t.deleted_at IS NULL AND t.archived_at IS NULL
              WHERE w.user_id IN ({$placeholders}) AND w.logged_at >= ? AND w.logged_at <= ?"
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
                "SELECT SUBSTR(w.logged_at, 1, 10) AS day, COALESCE(SUM(w.minutes_spent), 0) AS minutes
                   FROM work_logs w
                   JOIN tasks t ON t.id = w.task_id AND t.deleted_at IS NULL AND t.archived_at IS NULL
                  WHERE w.user_id IN ({$placeholders}) AND w.logged_at >= ? AND w.logged_at <= ?
                  GROUP BY SUBSTR(w.logged_at, 1, 10)"
            );
            $stmt->execute(array_merge($userIds, [$from, $to]));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $byDate[(string)$row['day']] = (int)$row['minutes'];
            }
        }

        // The day list has to carry the same UTC dates the query grouped by. Both ends
        // used to be parsed as *local* midnight and then labelled with gmdate(), which
        // on any server east of UTC shifted the range to [from - 1 day, to - 1 day]:
        // the window's last day - today - was never emitted, so minutes logged on the
        // current day did not reach the weekly series at all and a weekly goal read 0 %.
        $days = [];
        $cursor = strtotime(substr($from, 0, 10) . ' 00:00:00 UTC');
        $end = strtotime(substr($to, 0, 10) . ' 00:00:00 UTC');
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
    private function minutesPerTask(array $userIds, bool $isRoot, string $from, string $to, ?string $projectPublicId = null): array
    {
        // A deleted or archived task is not part of the work anyone can look at any
        // more: listing it produces a row whose link answers 404, and its hours push
        // the period totals and the percentiles around. On the demo every one of the
        // top ten rows came from an unfinished test run that had been deleted
        // afterwards - including the 1 000 344-minute outlier that dominated the
        // average. The filter matches every other task-level aggregate in this
        // repository, so the widget's task list and the rest of the dashboard agree
        // on what "the period" contains.
        $sql = 'SELECT t.public_id, t.title, p.title AS project_title,
                       COALESCE(SUM(w.minutes_spent), 0) AS minutes,
                       COUNT(w.id) AS sessions,
                       MAX(w.logged_at) AS last_logged_at
                FROM work_logs w
                JOIN tasks t ON t.id = w.task_id
                LEFT JOIN projects p ON p.id = t.project_id
                WHERE w.logged_at >= ? AND w.logged_at <= ?
                  AND t.deleted_at IS NULL AND t.archived_at IS NULL';
        $params = [$from, $to];

        if (!$isRoot) {
            if ($userIds === []) {
                return [];
            }
            $sql .= ' AND w.user_id IN (' . implode(', ', array_fill(0, count($userIds), '?')) . ')';
            $params = array_merge($params, $userIds);
        }

        $sql .= $this->projectFilterClause($projectPublicId, $params);
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
    private function countActiveTasksWithoutLogs(array $userIds, bool $isRoot, array $loggedTaskPublicIds, ?string $projectPublicId = null): int
    {
        [$where, $params] = $this->activeWithoutLogsWhere($userIds, $isRoot, $loggedTaskPublicIds, $projectPublicId);
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
    private function activeTasksWithoutLogsList(
        array $userIds,
        bool $isRoot,
        array $loggedTaskPublicIds,
        int $limit = 5,
        ?string $projectPublicId = null
    ): array {
        [$where, $params] = $this->activeWithoutLogsWhere($userIds, $isRoot, $loggedTaskPublicIds, $projectPublicId);
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
    private function activeWithoutLogsWhere(
        array $userIds,
        bool $isRoot,
        array $loggedTaskPublicIds,
        ?string $projectPublicId = null
    ): array {
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

        $sql .= $this->projectFilterClause($projectPublicId, $params);


        return [$sql, $params];
    }

    /**
     * @param int[] $userIds
     * @return array<int, array{activity_code:string,minutes:int}>
     */
    /**
     * Optional project narrowing shared by the actual-time queries.
     *
     * The check that the actor may see the project belongs to the service (it owns
     * visibility); this only turns the id into SQL. An empty or missing id means "no
     * narrowing", never "match nothing".
     *
     * @param array<int,string|int> $params appended in place
     */
    private function projectFilterClause(?string $projectPublicId, array &$params): string
    {
        $id = trim((string)$projectPublicId);
        if ($id === '') {
            return '';
        }
        $params[] = $id;

        return ' AND t.project_id IN (SELECT id FROM projects WHERE public_id = ?)';
    }

    /**
     * Which of the given tasks carry a live estimate.
     *
     * @param string[] $taskPublicIds
     * @return array<int,string> the subset that is estimated
     */
    private function taskIdsWithActiveEstimate(array $taskPublicIds): array
    {
        if ($taskPublicIds === []) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($taskPublicIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT task_public_id FROM task_estimates
             WHERE deleted_at IS NULL AND task_public_id IN (' . $placeholders . ')'
        );
        $stmt->execute($taskPublicIds);

        return array_map(static fn(array $row): string => (string)$row['task_public_id'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Logged tasks with no estimate, worst first.
     *
     * Ordered by minutes so the list points at the work where a missing plan costs the
     * most, not at the alphabetically first row. Titles are resolved in one extra query
     * for the capped list only: a bare public id is not clickable-by-title for a human
     * and the card is meant to be actionable.
     *
     * @param string[] $taskPublicIds all logged tasks
     * @param array<int,string> $estimatedTaskIds
     * @param array<string,int> $minutesByTask
     * @return array<int, array{task_public_id:string,title:string,minutes:int}>
     */
    private function tasksWithoutEstimate(array $taskPublicIds, array $estimatedTaskIds, array $minutesByTask, int $limit = 5): array
    {
        $estimated = array_flip($estimatedTaskIds);
        $missing = [];
        foreach ($taskPublicIds as $taskPublicId) {
            $taskPublicId = (string)$taskPublicId;
            if (isset($estimated[$taskPublicId])) {
                continue;
            }
            $missing[] = ['task_public_id' => $taskPublicId, 'minutes' => (int)($minutesByTask[$taskPublicId] ?? 0)];
        }
        usort($missing, static fn(array $a, array $b): int => $b['minutes'] <=> $a['minutes']);
        $missing = array_slice($missing, 0, max(1, min(25, $limit)));

        if ($missing === []) {
            return [];
        }
        $ids = array_column($missing, 'task_public_id');
        $stmt = $this->pdo->prepare(
            'SELECT public_id, title FROM tasks WHERE public_id IN (' . implode(', ', array_fill(0, count($ids), '?')) . ')'
        );
        $stmt->execute($ids);
        $titles = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $titles[(string)$row['public_id']] = (string)$row['title'];
        }
        foreach ($missing as $index => $row) {
            $missing[$index]['title'] = $titles[$row['task_public_id']] ?? '';
        }

        return $missing;
    }

    /**
     * What the estimate sets in use actually say about the logged time.
     *
     * An `estimate_sets` row describes its own scale: `estimate_type` (story points,
     * t-shirt, complexity, risk, bug severity, custom) with an optional `unit_label`.
     * Only sets with at least `MIN_ESTIMATE_SAMPLE` estimated tasks that were logged in
     * the period are reported - a rate computed from one task is noise dressed as a
     * measurement - and money sets are skipped outright.
     *
     * For a set whose unit is time the classic overrun is computable and reported as
     * `overrun_percent`; for every other unit the honest metric is the cooling rate
     * (`minutes_per_point`): how many logged minutes one point of that scale costs.
     *
     * @param array<string,int> $minutesByTask task public id => minutes inside the period
     * @return array<int, array<string,mixed>>
     */
    private function estimateCalibration(array $minutesByTask): array
    {
        if ($minutesByTask === []) {
            return [];
        }
        $taskIds = array_keys($minutesByTask);
        $placeholders = implode(', ', array_fill(0, count($taskIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT te.task_public_id, te.numeric_value, es.name, es.unit_label, es.estimate_type, es.currency_code
             FROM task_estimates te
             INNER JOIN estimate_sets es ON es.id = te.estimate_set_id
             WHERE te.deleted_at IS NULL AND te.numeric_value IS NOT NULL
               AND te.task_public_id IN (' . $placeholders . ')'
        );
        $stmt->execute($taskIds);

        $bySet = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (($row['currency_code'] ?? null) !== null && (string)$row['currency_code'] !== '') {
                continue; // a price is not a unit of work
            }
            $taskPublicId = (string)$row['task_public_id'];
            $key = (string)$row['name'] . '|' . (string)($row['unit_label'] ?? '');
            if (!isset($bySet[$key])) {
                $bySet[$key] = [
                    'set_name' => (string)$row['name'],
                    'unit_label' => $row['unit_label'] !== null ? (string)$row['unit_label'] : null,
                    'estimate_type' => (string)$row['estimate_type'],
                    'points' => 0.0,
                    'minutes' => 0,
                    'tasks' => [],
                ];
            }
            $bySet[$key]['points'] += (float)$row['numeric_value'];
            if (!isset($bySet[$key]['tasks'][$taskPublicId])) {
                // One estimate per task per set: a task is never counted twice, so the
                // rate cannot be distorted by a duplicated row.
                $bySet[$key]['tasks'][$taskPublicId] = true;
                $bySet[$key]['minutes'] += (int)($minutesByTask[$taskPublicId] ?? 0);
            }
        }

        $out = [];
        foreach ($bySet as $entry) {
            $taskCount = count($entry['tasks']);
            if ($taskCount < self::MIN_ESTIMATE_SAMPLE || $entry['points'] <= 0) {
                continue;
            }
            $row = [
                'set_name' => $entry['set_name'],
                'unit_label' => $entry['unit_label'],
                'estimate_type' => $entry['estimate_type'],
                'tasks' => $taskCount,
                'points' => round($entry['points'], 2),
                'minutes' => (int)$entry['minutes'],
                'is_time_unit' => self::isTimeEstimateUnit($entry['estimate_type'], $entry['unit_label']),
            ];
            if ($row['is_time_unit']) {
                $estimatedMinutes = $entry['points'] * 60;
                $row['estimated_minutes'] = (int)round($estimatedMinutes);
                $row['overrun_percent'] = round(($entry['minutes'] - $estimatedMinutes) / $estimatedMinutes * 100, 1);
            } else {
                $row['minutes_per_point'] = round($entry['minutes'] / $entry['points'], 2);
            }
            $out[] = $row;
        }
        usort($out, static fn(array $a, array $b): int => $b['tasks'] <=> $a['tasks']);

        return $out;
    }

    /**
     * Tasks whose logged time beat their own estimate, worst overrun first.
     *
     * Only estimates measured in time take part: for a story-point task an "overrun"
     * would require a points-to-hours conversion that the schema does not hold, and
     * inventing one is exactly what this widget refuses to do. A task counts once per
     * set - the largest estimate wins when a task carries several - so the same work
     * cannot be reported twice.
     *
     * Rows carry their titles because the card links them; hours under the estimate are
     * not overruns and are left out, so the list stays a to-do list rather than noise.
     *
     * @param array<string,int> $minutesByTask task public id => minutes inside the period
     * @return array<int, array{task_public_id:string,title:string,estimated_minutes:int,minutes:int,overrun_percent:float}>
     */
    private function estimateOverruns(array $minutesByTask): array
    {
        if ($minutesByTask === []) {
            return [];
        }
        $taskIds = array_keys($minutesByTask);
        $stmt = $this->pdo->prepare(
            'SELECT te.task_public_id, te.numeric_value, es.name, es.unit_label, es.estimate_type, es.currency_code
             FROM task_estimates te
             INNER JOIN estimate_sets es ON es.id = te.estimate_set_id
             WHERE te.deleted_at IS NULL AND te.numeric_value IS NOT NULL
               AND te.task_public_id IN (' . implode(', ', array_fill(0, count($taskIds), '?')) . ')'
        );
        $stmt->execute($taskIds);

        $worst = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (($row['currency_code'] ?? null) !== null && (string)$row['currency_code'] !== '') {
                continue;
            }
            if (!self::isTimeEstimateUnit((string)$row['estimate_type'], $row['unit_label'] !== null ? (string)$row['unit_label'] : null)) {
                continue;
            }
            $estimateMinutes = (int)round((float)$row['numeric_value'] * 60);
            if ($estimateMinutes <= 0) {
                continue;
            }
            $taskPublicId = (string)$row['task_public_id'];
            $minutes = (int)($minutesByTask[$taskPublicId] ?? 0);
            if ($minutes <= $estimateMinutes) {
                continue;
            }
            // A second set for the same task only replaces the first when it promises
            // more hours: the card must not list one task twice with two verdicts.
            if (isset($worst[$taskPublicId]) && $worst[$taskPublicId]['estimated_minutes'] >= $estimateMinutes) {
                continue;
            }
            $worst[$taskPublicId] = [
                'task_public_id' => $taskPublicId,
                'title' => '',
                'estimated_minutes' => $estimateMinutes,
                'minutes' => $minutes,
                'overrun_percent' => round(($minutes - $estimateMinutes) / $estimateMinutes * 100, 1),
            ];
        }

        if ($worst === []) {
            return [];
        }
        // Sort first, then read the ids off the sorted list: `usort` reindexes, so doing
        // it the other way round silently fetches titles for tasks "0" and "1".
        usort($worst, static fn(array $a, array $b): int => $b['overrun_percent'] <=> $a['overrun_percent']);

        $rows = array_values($worst);
        $ids = array_column($rows, 'task_public_id');
        $titleStmt = $this->pdo->prepare(
            'SELECT public_id, title FROM tasks WHERE public_id IN (' . implode(', ', array_fill(0, count($ids), '?')) . ')'
        );
        $titleStmt->execute($ids);
        $titles = [];
        foreach ($titleStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $titles[(string)$row['public_id']] = (string)$row['title'];
        }
        foreach ($rows as $index => $row) {
            $rows[$index]['title'] = $titles[$row['task_public_id']] ?? '';
        }

        return $rows;
    }

    /** Whether an estimate set measures time (an hour estimate) rather than an abstract scale. */
    private static function isTimeEstimateUnit(string $estimateType, ?string $unitLabel): bool
    {
        $type = strtolower(trim($estimateType));
        if (in_array($type, ['hours', 'hour', 'time', 'estimate_hours'], true)) {
            return true;
        }
        $unit = strtolower(trim((string)$unitLabel));

        return in_array($unit, ['h', 'hr', 'hrs', 'hour', 'hours', 'ч', 'час', 'часы', '小时'], true);
    }

    private function minutesByActivity(array $userIds, bool $isRoot, string $from, string $to, ?string $projectPublicId = null): array
    {
        $projectFilter = trim((string)$projectPublicId) !== ''
            ? ' AND t.project_id IN (SELECT id FROM projects WHERE public_id = ?)'
            : '';
        // The split has to describe the same work as `top_tasks` and `total_minutes`,
        // so it joins tasks unconditionally and applies the same live-task filter: a
        // deleted task's hours used to be counted here but not there, and the chips
        // then summed to more than the card's own total.
        $sql = 'SELECT w.activity_code, COALESCE(SUM(w.minutes_spent), 0) AS minutes
                FROM work_logs w
                JOIN tasks t ON t.id = w.task_id
                WHERE w.logged_at >= ? AND w.logged_at <= ?
                  AND t.deleted_at IS NULL AND t.archived_at IS NULL';
        $params = [$from, $to];
        if ($projectFilter !== '') {
            $sql .= $projectFilter;
            $params[] = trim((string)$projectPublicId);
        }

        if (!$isRoot) {
            if ($userIds === []) {
                return [];
            }
            $sql .= ' AND w.user_id IN (' . implode(', ', array_fill(0, count($userIds), '?')) . ')';
            $params = array_merge($params, $userIds);
        }

        $sql .= ' GROUP BY w.activity_code ORDER BY minutes DESC';

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
        $mondayTs = $this->mondayTsUtc($to);
        if ($mondayTs <= 0) {
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
    /**
     * Monday 00:00 UTC of the week holding the given "Y-m-d H:i:s" UTC stamp.
     *
     * Every timestamp these aggregates bucket (`logged_at`, `tasks.created_at`,
     * `task_status_history.created_at`) is stored in UTC, so the week arithmetic has
     * to be UTC as well. `strtotime('monday this week', ...)` resolves in the
     * *server's* timezone instead, which on a UTC+3 host returns Sunday 21:00 UTC: the
     * `week_start` labels came out a day early on the card and work done on the local
     * Sunday evening was bucketed into the week before.
     */
    private function mondayTsUtc(string $utcStamp): int
    {
        $day = substr($utcStamp, 0, 10);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
            return 0;
        }

        $ts = (int)strtotime($day . ' 00:00:00 UTC');
        $weekday = (int)gmdate('N', $ts); // 1 = Monday ... 7 = Sunday

        return $ts - ($weekday - 1) * 86400;
    }

    private function weekKeyFor(string $day, int $mondayTs): ?string
    {
        // UTC, like the timestamps being bucketed - see mondayTsUtc().
        $ts = strtotime($day . ' 00:00:00 UTC');
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
    /**
     * Open tasks per user whose SLA commitment is already missed or about to be.
     *
     * The task row carries the SLA snapshot written when a policy was assigned
     * (`sla_policy_id`, `sla_response_deadline`, `sla_resolve_deadline`,
     * `sla_breached`), so the risk is read from `tasks` itself; `sla_policies` only
     * holds the definition. A deadline counts when it has passed or falls inside the
     * next `SLA_SOON_DAYS`, and a task already flagged as breached counts even if it
     * no longer carries a deadline.
     *
     * @param int[] $userIds
     * @return array<int,int> user id => tasks at risk
     */
    private function slaRiskTaskCountsByUser(array $userIds, string $now): array
    {
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $terminal = TaskStatusSemantics::terminalLiteralList($this->pdo);
        $soon = gmdate('Y-m-d H:i:s', strtotime('+' . self::SLA_SOON_DAYS . ' days', strtotime($now)) ?: time());
        $stmt = $this->pdo->prepare(
            'SELECT t.assignee_user_id AS uid, COUNT(*) AS at_risk
             FROM tasks t
             WHERE t.deleted_at IS NULL AND t.archived_at IS NULL
               AND t.status_code NOT IN (' . $terminal . ')
               AND t.assignee_user_id IN (' . $placeholders . ')
               AND (
                   t.sla_breached = 1
                   OR (t.sla_response_deadline IS NOT NULL AND t.sla_response_deadline <= ?)
                   OR (t.sla_resolve_deadline IS NOT NULL AND t.sla_resolve_deadline <= ?)
               )
             GROUP BY t.assignee_user_id'
        );
        // The `IN (...)` placeholder comes first in the statement, so the user ids are
        // bound before the two deadline bounds.
        $stmt->execute(array_merge($userIds, [$soon, $soon]));

        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(int)$row['uid']] = (int)$row['at_risk'];
        }

        return $counts;
    }

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
             JOIN tasks t ON t.id = w.task_id AND t.deleted_at IS NULL AND t.archived_at IS NULL
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

    /**
     * Open tasks that are explicitly blocked.
     *
     * `blocked` is the dictionary status a task carries when somebody decided it
     * cannot move - a dependency is waiting, an answer is missing - and such a task
     * nonetheless counts as WIP everywhere else. The WIP tile has to be able to say
     * how much of the queue is stuck rather than in flight.
     */
    private function countBlockedOpenTasks(array $userIds, bool $isRoot): int
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
            "SELECT COUNT(*) FROM tasks t
             WHERE t.deleted_at IS NULL AND t.archived_at IS NULL
               AND LOWER(t.status_code) = 'blocked'
               AND t.status_code NOT IN (" . TaskStatusSemantics::terminalLiteralList($this->pdo) . ')' . $scopeSql
        );
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
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

    public static function loadSignal(float $loadPercent, array $bands = []): string
    {
        $bands = self::bands($bands);
        if ($loadPercent > $bands['overload_percent']) {
            return 'overload';
        }
        if ($loadPercent < $bands['underload_percent']) {
            return 'underload';
        }

        return 'normal';
    }

    /**
     * A capacity the repository is willing to divide by.
     *
     * The service resolves the organisation's week; anything non-numeric or
     * non-positive that reaches here (a broken setting, a null column) must not
     * turn every load percentage into an error or a divide-by-zero, so the
     * historical 40-hour default applies instead.
     *
     * @param mixed $value
     */
    private static function normalizeCapacity($value): int
    {
        if (is_numeric($value)) {
            $minutes = (int)$value;
            if ($minutes > 0) {
                return $minutes;
            }
        }

        return self::WEEK_CAPACITY_MINUTES;
    }

    /**
     * Weekly minutes described by the best business calendar, if any.
     *
     * Picks the calendar covering the most weekdays (ties: the oldest row) and sums
     * one span per weekday, taking the longest span when a calendar lists the same
     * day twice. The join is deliberate: orphaned `working_hours` rows - of which
     * the demo database has fifteen, pointing at calendars that no longer exist - do
     * not describe anybody's week and must not become a capacity.
     *
     * Coverage is returned alongside the minutes so the caller can decide whether
     * the calendar is complete enough to be trusted.
     *
     * @return array{minutes:?int,weekdays:int,calendar_public_id:?string}
     */
    public function calendarWeeklyCapacity(): array
    {
        $sql = 'SELECT c.id AS calendar_id, c.public_id AS calendar_public_id, w.weekday, w.start_time, w.end_time
                FROM working_hours w
                INNER JOIN business_calendars c ON c.id = w.calendar_id
                WHERE w.weekday IS NOT NULL'
            . ' ORDER BY c.id ASC';

        $stmt = $this->pdo->query($sql);
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byCalendar = [];
        foreach ($rows as $row) {
            $calendarId = (int)$row['calendar_id'];
            $weekday = (int)$row['weekday'];
            $span = self::minutesBetween((string)($row['start_time'] ?? ''), (string)($row['end_time'] ?? ''));
            if ($span <= 0) {
                continue;
            }
            if (!isset($byCalendar[$calendarId])) {
                $byCalendar[$calendarId] = [
                    'calendar_public_id' => (string)$row['calendar_public_id'],
                    'weekdays' => [],
                ];
            }
            $byCalendar[$calendarId]['weekdays'][$weekday] = max(
                $byCalendar[$calendarId]['weekdays'][$weekday] ?? 0,
                $span
            );
        }

        $best = null;
        foreach ($byCalendar as $entry) {
            $weekdays = count($entry['weekdays']);
            if ($weekdays === 0) {
                continue;
            }
            if ($best === null || $weekdays > $best['weekdays']) {
                $best = [
                    'minutes' => (int)array_sum($entry['weekdays']),
                    'weekdays' => $weekdays,
                    'calendar_public_id' => $entry['calendar_public_id'],
                ];
            }
        }

        return $best ?? ['minutes' => null, 'weekdays' => 0, 'calendar_public_id' => null];
    }

    /**
     * Median load percentage across the supplied scope, plus its sample size.
     *
     * Answers "is my week heavy compared with the people I can see?" without ever
     * leaving the caller's scope: when `$isRoot` is false only the ids passed in are
     * read, so a limited actor cannot learn the organisation's shape from a median.
     * The median is taken over users, not over worklogs, and users with no logs count
     * as zero - an idle colleague is part of the distribution.
     *
     * @param int[] $userIds ignored when $isRoot is true
     * @return array{median:?float,sample:int,capacity_minutes_week:int}
     */
    public function loadPercentMedian(array $userIds, bool $isRoot, string $from, string $to, int $capacityMinutes): array
    {
        $capacity = self::normalizeCapacity($capacityMinutes);
        $ids = $userIds;
        if ($isRoot) {
            $stmt = $this->pdo->query('SELECT id FROM users WHERE is_active = 1 AND deleted_at IS NULL');
            $ids = array_map(static fn(array $row): int => (int)$row['id'], $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
        if ($ids === []) {
            return ['median' => null, 'sample' => 0, 'capacity_minutes_week' => $capacity];
        }

        $minutes = $this->loggedMinutesByUser($ids, $from, $to);
        $loads = [];
        foreach ($ids as $id) {
            $loads[] = (float)($minutes[$id] ?? 0) / $capacity * 100;
        }
        sort($loads);

        return [
            'median' => self::medianFloat($loads),
            'sample' => count($loads),
            'capacity_minutes_week' => $capacity,
        ];
    }

    /**
     * Per-person median of the two scorecard metrics, over the caller's own scope.
     *
     * The widget could say "you completed 6 tasks" but not whether 6 is a lot, and
     * a company average cannot answer that either: one person closing 90 tasks
     * would move the line everyone else is judged by. Medians are taken over users
     * (an idle colleague counts as zero completed, which is part of the shape of
     * the team) and never over a wider scope than the caller was given - with
     * `$isRoot` false only the passed ids are read.
     *
     * On-time has no meaningful value for a user with no dated work, so those users
     * are left out of that distribution and `on_time_sample` reports how many
     * people actually contributed a percentage.
     *
     * @param int[] $userIds ignored when $isRoot is true
     * @return array{completed_median:?int,on_time_median:?float,sample:int,on_time_sample:int}
     */
    public function personalScorecardScope(array $userIds, bool $isRoot, string $from, string $to): array
    {
        $ids = $userIds;
        if ($isRoot) {
            $stmt = $this->pdo->query('SELECT id FROM users WHERE is_active = 1 AND deleted_at IS NULL');
            $ids = array_map(static fn(array $row): int => (int)$row['id'], $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
        if ($ids === []) {
            return ['completed_median' => null, 'on_time_median' => null, 'sample' => 0, 'on_time_sample' => 0];
        }

        $completedByUser = $this->completedTasksByUser($ids, $from, $to);
        $completedSeries = [];
        foreach ($ids as $id) {
            $completedSeries[] = (int)($completedByUser[$id] ?? 0);
        }

        $onTime = $this->onTimePercentByUser($ids, $from, $to);
        $onTimeSeries = array_values($onTime['percents']);

        return [
            'completed_median' => self::median($completedSeries),
            'on_time_median' => self::medianFloat($onTimeSeries),
            'sample' => count($ids),
            'on_time_sample' => count($onTimeSeries),
        ];
    }

    /**
     * On-time percentage per user, over the tasks that were finished in the window.
     *
     * Same first-finish semantics as `onTimeCompletion()`: only the first terminal
     * transition of a task counts, and only tasks carrying a due date can be late.
     *
     * @param int[] $userIds
     * @return array{percents:array<int,float>,samples:array<int,int>}
     */
    private function onTimePercentByUser(array $userIds, string $from, string $to): array
    {
        if ($userIds === []) {
            return ['percents' => [], 'samples' => []];
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT t.assignee_user_id AS uid,
                    COUNT(*) AS total,
                    SUM(CASE WHEN f.finished_at <= t.due_at THEN 1 ELSE 0 END) AS on_time
               FROM (' . $this->firstFinishSubquery($placeholders) . ') f
               JOIN tasks t ON t.id = f.task_id
              WHERE t.due_at IS NOT NULL AND f.finished_at >= ? AND f.finished_at <= ?
              GROUP BY t.assignee_user_id'
        );
        $stmt->execute(array_merge($userIds, [$from, $to]));

        $percents = [];
        $samples = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $userId = (int)$row['uid'];
            $total = (int)($row['total'] ?? 0);
            if ($userId <= 0 || $total <= 0) {
                continue;
            }
            $percents[$userId] = round((int)($row['on_time'] ?? 0) / $total * 100, 1);
            $samples[$userId] = $total;
        }

        return ['percents' => $percents, 'samples' => $samples];
    }

    /**
     * The projects a single person's period was spent on, by completed tasks and
     * logged time.
     *
     * Two scopes at once, and both have to hold: the rows are that person's *own*
     * tasks and own work logs, and the projects are the ones that person can reach
     * (`accessibleProjectPublicIds` - created by, managed by or on a team of theirs,
     * exactly the list the stream widgets and the project pickers use). The split
     * therefore can never name a project the actor has no access to, and never
     * reports work somebody else did. A non-root actor with no accessible project
     * gets an empty list rather than the organisation's projects - the same
     * fail-closed rule the other project-scoped widgets follow.
     *
     * Ordering is completed-first because that is the metric the widget ranks on;
     * a project the person only logged time on still appears (dropping it would
     * hide a real slice of the period).
     *
     * Both halves use the very same definitions as the tiles above them, so the rows
     * reconcile with the card: completed is `countCompletedTasks` (no
     * archived/deleted filter, plus the accessible-project filter above) and hours
     * are `sumLoggedMinutes` (archived tasks excluded - the same rule that produced
     * the "Часы" tile). The access scope can still make the rows add up to less than
     * the tile directly above them, because work done on a project the actor cannot
     * reach is not named here. That is the deliberate trade: the block may
     * under-report the actor's own time, but it can never disclose a project to
     * somebody who has no access to it.
     *
     * @param string[] $accessibleProjectPublicIds empty when $isRoot is true
     * @return array{projects:list<array{project_public_id:string,title:string,completed:int,minutes:int}>,total:int}
     */
    public function completedByProject(
        int $userId,
        array $accessibleProjectPublicIds,
        bool $isRoot,
        string $from,
        string $to,
        int $limit
    ): array {
        if ($userId <= 0) {
            return ['projects' => [], 'total' => 0];
        }

        $scopeSql = '';
        $scopeParams = [];
        if (!$isRoot) {
            if ($accessibleProjectPublicIds === []) {
                return ['projects' => [], 'total' => 0];
            }
            $scopeSql = ' AND p.public_id IN (' . implode(', ', array_fill(0, count($accessibleProjectPublicIds), '?')) . ')';
            $scopeParams = $accessibleProjectPublicIds;
        }

        $completedStmt = $this->pdo->prepare(
            'SELECT p.public_id AS project_public_id, p.title AS title, COUNT(DISTINCT t.id) AS completed
               FROM task_status_history h
               JOIN tasks t ON t.id = h.task_id
               JOIN projects p ON p.id = t.project_id
              WHERE h.new_status IN (' . TaskStatusSemantics::completedLiteralList($this->pdo) . ')
                AND h.created_at >= ? AND h.created_at <= ?
                AND t.assignee_user_id = ?' . $scopeSql . '
              GROUP BY p.public_id, p.title'
        );
        $completedStmt->execute(array_merge([$from, $to, $userId], $scopeParams));

        $minutesStmt = $this->pdo->prepare(
            'SELECT p.public_id AS project_public_id, p.title AS title, COALESCE(SUM(w.minutes_spent), 0) AS minutes
               FROM work_logs w
               JOIN tasks t ON t.id = w.task_id AND t.deleted_at IS NULL AND t.archived_at IS NULL
               JOIN projects p ON p.id = t.project_id
              WHERE w.user_id = ? AND w.logged_at >= ? AND w.logged_at <= ?' . $scopeSql . '
              GROUP BY p.public_id, p.title'
        );
        $minutesStmt->execute(array_merge([$userId, $from, $to], $scopeParams));

        $rows = [];
        foreach ($completedStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string)$row['project_public_id'];
            $rows[$key] = [
                'project_public_id' => $key,
                'title' => (string)($row['title'] ?? ''),
                'completed' => (int)($row['completed'] ?? 0),
                'minutes' => 0,
            ];
        }
        foreach ($minutesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string)$row['project_public_id'];
            if (!isset($rows[$key])) {
                $rows[$key] = [
                    'project_public_id' => $key,
                    'title' => (string)($row['title'] ?? ''),
                    'completed' => 0,
                    'minutes' => 0,
                ];
            }
            $rows[$key]['minutes'] = (int)($row['minutes'] ?? 0);
        }

        $list = array_values($rows);
        usort($list, static function (array $a, array $b): int {
            if ($a['completed'] !== $b['completed']) {
                return $b['completed'] <=> $a['completed'];
            }
            if ($a['minutes'] !== $b['minutes']) {
                return $b['minutes'] <=> $a['minutes'];
            }

            return strcmp($a['title'], $b['title']);
        });

        return [
            'projects' => array_slice($list, 0, max(1, $limit)),
            'total' => count($list),
        ];
    }

    /**
     * Minutes between two `HH:MM[:SS]` clock times, tolerating a span that crosses
     * midnight (a night shift ends on the next day).
     */
    private static function minutesBetween(string $start, string $end): int
    {
        $startMinutes = self::clockMinutes($start);
        $endMinutes = self::clockMinutes($end);
        if ($startMinutes === null || $endMinutes === null) {
            return 0;
        }
        if ($endMinutes < $startMinutes) {
            $endMinutes += 24 * 60;
        }

        return $endMinutes - $startMinutes;
    }

    private static function clockMinutes(string $time): ?int
    {
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($time), $m) !== 1) {
            return null;
        }
        $hours = (int)$m[1];
        $minutes = (int)$m[2];
        if ($hours > 24 || $minutes > 59) {
            return null;
        }

        return $hours * 60 + $minutes;
    }

    /**
     * Median of a float series (the integer `median()` above is for cycle minutes).
     *
     * @param float[] $values
     */
    private static function medianFloat(array $values): ?float
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);
        $median = $count % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;

        return round($median, 1);
    }
}
