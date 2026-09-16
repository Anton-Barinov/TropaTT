<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Analytics\AnalyticsRepository;
use Api\Model\Analytics\InsightsRepository;
use Api\Model\Team\TeamRepository;
use Api\Model\User\UserManagementRepository;

final class AnalyticsService
{
    /**
     * Explicit weekly-capacity override, stored like the finance keys: a dotted
     * namespace inside the `system` scope, so the existing admin settings page (which
     * lists scope=system) can offer it without a new endpoint.
     */
    private const CAPACITY_SCOPE = 'system';
    private const CAPACITY_NAME = 'insights.weekly_capacity_minutes';

    /**
     * A working week shorter than this, or longer than that, is not a week a
     * human works: the value is treated as broken calendar data and ignored.
     */
    private const CAPACITY_MIN_MINUTES = 300;
    private const CAPACITY_MAX_MINUTES = 6000;

    /**
     * `working_hours` must describe at least this many days of the week before it
     * is trusted as a capacity source. A calendar that only lists one day (or
     * none) is treated as incomplete, not as a 9-hour week.
     */
    private const CAPACITY_MIN_WEEKDAYS = 3;

    /** @var array<string,mixed>|null resolved once per request */
    private ?array $weeklyCapacityCache = null;

    public function __construct(
        private readonly AnalyticsRepository $analytics,
        private readonly TeamRepository $teams,
        private readonly UserManagementRepository $userManagement,
        private readonly ?InsightsRepository $insights = null,
        private readonly ?\Api\Model\Department\DepartmentRepository $departments = null,
        private readonly ?SettingService $settings = null
    ) {
    }

    /**
     * Weekly capacity every load percentage in the insights widgets is measured
     * against, with the source it came from.
     *
     * Precedence is explicit-over-implicit: an administrator who sets the
     * `insights.weekly_capacity_minutes` setting overrides the calendar, because the
     * calendar may describe one office while the org runs a different week. A
     * calendar is used only when it describes a coherent week; otherwise the
     * historical 40-hour default applies and the payload says so, so the card can
     * write "цель 40 ч (по умолчанию)" instead of presenting a guess as a fact.
     *
     * @return array{minutes:int,source:string,calendar_public_id:?string,weekdays_covered:int}
     */
    private function weeklyCapacity(): array
    {
        if ($this->weeklyCapacityCache !== null) {
            return $this->weeklyCapacityCache;
        }

        $setting = $this->settings?->get(self::CAPACITY_SCOPE, self::CAPACITY_NAME);
        $settingValue = is_array($setting) ? ($setting['value'] ?? null) : null;
        if (is_numeric($settingValue)) {
            $minutes = (int)$settingValue;
            if ($minutes >= self::CAPACITY_MIN_MINUTES && $minutes <= self::CAPACITY_MAX_MINUTES) {
                return $this->weeklyCapacityCache = [
                    'minutes' => $minutes,
                    'source' => 'setting',
                    'calendar_public_id' => null,
                    'weekdays_covered' => 0,
                ];
            }
        }

        $calendar = $this->insights !== null ? $this->insights->calendarWeeklyCapacity() : ['minutes' => null, 'weekdays' => 0, 'calendar_public_id' => null];
        $calendarMinutes = $calendar['minutes'];
        if (
            $calendarMinutes !== null
            && (int)$calendar['weekdays'] >= self::CAPACITY_MIN_WEEKDAYS
            && $calendarMinutes >= self::CAPACITY_MIN_MINUTES
            && $calendarMinutes <= self::CAPACITY_MAX_MINUTES
        ) {
            return $this->weeklyCapacityCache = [
                'minutes' => (int)$calendarMinutes,
                'source' => 'calendar',
                'calendar_public_id' => $calendar['calendar_public_id'],
                'weekdays_covered' => (int)$calendar['weekdays'],
            ];
        }

        return $this->weeklyCapacityCache = [
            'minutes' => InsightsRepository::WEEK_CAPACITY_MINUTES,
            'source' => 'default_40h',
            'calendar_public_id' => null,
            'weekdays_covered' => (int)($calendar['weekdays'] ?? 0),
        ];
    }

    public function summary(array $actor): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $weekStart = gmdate('Y-m-d 00:00:00', strtotime('monday this week'));
        $weekEnd = gmdate('Y-m-d 23:59:59', strtotime('sunday this week'));
        $teamIds = $this->accessibleTeamPublicIds($actor);

        $data = $this->analytics->summary(
            (int)($actor['id'] ?? 0),
            (bool)($actor['is_root'] ?? false),
            $now,
            $weekStart,
            $weekEnd,
            $teamIds
        );

        $totalTasks = (int)($data['total_tasks'] ?? 0);
        $completedTasks = (int)($data['completed_tasks'] ?? 0);
        $data['completion_rate_percent'] = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0.0;

        return $data;
    }

    public function projects(array $actor, array $filters): array
    {
        $limit = min(200, max(1, (int)($filters['limit'] ?? 50)));
        $now = gmdate('Y-m-d H:i:s');

        $items = $this->analytics->projectsBreakdown(
            (int)($actor['id'] ?? 0),
            (bool)($actor['is_root'] ?? false),
            $limit,
            $now,
            $this->accessibleTeamPublicIds($actor)
        );

        foreach ($items as &$item) {
            $item['total_tasks'] = (int)($item['total_tasks'] ?? 0);
            $item['completed_tasks'] = (int)($item['completed_tasks'] ?? 0);
            $item['overdue_tasks'] = (int)($item['overdue_tasks'] ?? 0);
            $item['active_tasks'] = (int)($item['active_tasks'] ?? 0);
        }
        unset($item);

        return $items;
    }

    public function users(array $actor, array $filters): array
    {
        $limit = min(200, max(1, (int)($filters['limit'] ?? 50)));
        $now = gmdate('Y-m-d H:i:s');
        $weekStart = gmdate('Y-m-d 00:00:00', strtotime('monday this week'));
        $weekEnd = gmdate('Y-m-d 23:59:59', strtotime('sunday this week'));

        $items = $this->analytics->usersWorkload(
            (int)($actor['id'] ?? 0),
            (bool)($actor['is_root'] ?? false),
            $limit,
            $now,
            $weekStart,
            $weekEnd,
            $this->visibleUserIds($actor),
            $this->accessibleTeamPublicIds($actor)
        );

        foreach ($items as &$item) {
            $item['assigned_active_tasks'] = (int)($item['assigned_active_tasks'] ?? 0);
            $item['assigned_overdue_tasks'] = (int)($item['assigned_overdue_tasks'] ?? 0);
            $item['worklog_minutes_week'] = (int)($item['worklog_minutes_week'] ?? 0);
        }
        unset($item);

        return $items;
    }

    /**
     * Personal load/efficiency metrics for the dashboard widget.
     *
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function personalLoad(array $actor, array $filters): array
    {
        $periodDays = self::normalizePeriodDays($filters['period'] ?? 30);
        $now = gmdate('Y-m-d H:i:s');

        $weekStart = gmdate('Y-m-d 00:00:00', strtotime('-6 days', strtotime($now)));
        $capacity = $this->weeklyCapacity();

        $data = $this->insights()->personalLoad((int)($actor['id'] ?? 0), [
            'now' => $now,
            'week_start' => $weekStart,
            'period_start' => gmdate('Y-m-d 00:00:00', strtotime('-' . $periodDays . ' days', strtotime($now))),
            'previous_start' => gmdate('Y-m-d 00:00:00', strtotime('-' . ($periodDays * 2) . ' days', strtotime($now))),
            'period_days' => $periodDays,
            'capacity_minutes_week' => $capacity['minutes'],
        ]);

        // How the actor's week compares with the people they can see. The median
        // (not the average) is deliberate: one person logging 200 h must not move
        // the line everyone else is measured against.
        $isRoot = (bool)($actor['is_root'] ?? false);
        $scopeIds = $isRoot ? [] : $this->visibleUserIds($actor);
        $median = $this->insights()->loadPercentMedian(
            $scopeIds === [] && !$isRoot ? [-1] : $scopeIds,
            $isRoot,
            $weekStart,
            $now,
            $capacity['minutes']
        );

        $data['period_days'] = $periodDays;
        $data['user_public_id'] = (string)($actor['public_id'] ?? '');
        $data['capacity_source'] = $capacity['source'];
        $data['capacity_calendar_public_id'] = $capacity['calendar_public_id'];
        $data['scope_median_load_percent'] = $median['median'];
        $data['scope_sample'] = $median['sample'];
        $data['load_vs_scope_median_percent'] = $median['median'] === null
            ? null
            : round((float)$data['load_percent'] - (float)$median['median'], 1);

        return $this->stripFinancialFields($data);
    }

    /**
     * Actual execution time across the actor's visible scope.
     *
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function taskActualTime(array $actor, array $filters): array
    {
        $periodDays = self::normalizePeriodDays($filters['period'] ?? 30);
        $now = gmdate('Y-m-d H:i:s');
        $isRoot = (bool)($actor['is_root'] ?? false);

        // Fail closed: a non-root actor with an empty visible set must never fall
        // through to the "root sees everything" branch of the repository.
        $userIds = $isRoot ? [] : $this->visibleUserIds($actor);
        if (!$isRoot && $userIds === []) {
            $userIds = [-1];
        }

        // Two optional narrowings, both resolved against the actor's own visibility
        // before they reach the repository. A filter the actor cannot see must only
        // ever return less data, never more: an inaccessible project collapses the
        // scope to nothing and the payload says so through `project_filter` being null.
        $projectFilter = null;
        $requestedProject = trim((string)($filters['project_public_id'] ?? ''));
        if ($requestedProject !== '') {
            $allowed = $isRoot || in_array(
                $requestedProject,
                $this->insights()->accessibleProjectPublicIds((int)($actor['id'] ?? 0), $this->accessibleTeamPublicIds($actor)),
                true
            );
            if ($allowed) {
                $projectFilter = $requestedProject;
            } else {
                $userIds = [-1];
            }
        }

        $requestedAssignee = trim((string)($filters['assignee_user_public_id'] ?? ''));
        if ($requestedAssignee !== '') {
            $assignee = $this->userManagement->findByPublicId($requestedAssignee);
            $assigneeId = (int)($assignee['id'] ?? 0);
            if ($assigneeId <= 0) {
                $userIds = [-1];
            } elseif ($isRoot) {
                // Narrowing a root actor to one person is still a narrowing: keep the
                // "sees everything" branch out of it so the filter cannot be ignored.
                $isRoot = false;
                $userIds = [$assigneeId];
            } else {
                // A non-root actor may only narrow to somebody they already see; the ids
                // come from the same resolver the rest of the widget family uses.
                $userIds = in_array($assigneeId, $this->visibleUserIds($actor), true) ? [$assigneeId] : [-1];
            }
        }

        // The picker offers what the *actor* can reach, so it is built from the actor's
        // original role rather than from `$isRoot`, which the assignee narrowing above
        // may have flipped to false to keep the repository out of its "sees everything"
        // branch. Otherwise a root user who narrowed to one person would be offered
        // only the projects that person happens to manage.
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        $accessibleProjects = $actorIsRoot ? [] : $this->insights()->accessibleProjectPublicIds(
            (int)($actor['id'] ?? 0),
            $this->accessibleTeamPublicIds($actor)
        );

        $data = $this->insights()->actualTime(
            $userIds,
            $isRoot,
            gmdate('Y-m-d 00:00:00', strtotime('-' . $periodDays . ' days', strtotime($now))),
            $now,
            10,
            $projectFilter
        );

        // A narrowing the actor asked for but cannot have must not be silently
        // ignored: the payload distinguishes "no filter" from "a filter that could
        // not be applied" through `project_filter`/`project_filter_denied`, and the
        // picker only ever offers projects of the actor's own scope.
        $data['period_days'] = $periodDays;
        $data['assignee_filter'] = $requestedAssignee !== '' && ($userIds !== [-1]) ? $requestedAssignee : null;
        $data['project_filter_denied'] = $requestedProject !== '' && $projectFilter === null;
        $data['projects'] = $this->insights()->projectOptions($accessibleProjects, $actorIsRoot);

        return $this->stripFinancialFields($data);
    }

    /**
     * Personal KPI scorecard with deltas against the previous period.
     *
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function personalScorecard(array $actor, array $filters): array
    {
        $periodDays = self::normalizePeriodDays($filters['period'] ?? 30);
        $now = gmdate('Y-m-d H:i:s');
        $currentStart = gmdate('Y-m-d 00:00:00', strtotime('-' . $periodDays . ' days', strtotime($now)));
        $previousStart = gmdate('Y-m-d 00:00:00', strtotime('-' . ($periodDays * 2) . ' days', strtotime($now)));

        $data = $this->insights()->personalScorecard((int)($actor['id'] ?? 0), [
            'now' => $now,
            'period_start' => $currentStart,
            'previous_start' => $previousStart,
        ], ['period_start' => $currentStart, 'previous_start' => $previousStart]);

        $data['period_days'] = $periodDays;

        return $this->stripFinancialFields($data);
    }

    /**
     * Assignee load aggregated per department.
     *
     * A department has no member list of its own, so it is composed from its
     * manager: the manager, their hierarchy descendants and the members of teams
     * they manage. Metrics are summed per department; a user can contribute to
     * more than one department when they belong to several.
     *
     * `departments` has no membership column of its own, so a department without a
     * manager cannot be measured at all. Such rows are still returned - with
     * `no_manager: true` and zero members - instead of being dropped silently,
     * because "the org has three departments and the widget shows none" is exactly
     * the confusion this card caused before.
     *
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function assigneeDepartmentLoad(array $actor, array $filters): array
    {
        $periodDays = self::normalizePeriodDays($filters['period'] ?? 30);
        $now = gmdate('Y-m-d H:i:s');
        $window = [
            'now' => $now,
            'week_start' => gmdate('Y-m-d 00:00:00', strtotime('-6 days', strtotime($now))),
            'period_start' => gmdate('Y-m-d 00:00:00', strtotime('-' . $periodDays . ' days', strtotime($now))),
        ];

        $isRoot = (bool)($actor['is_root'] ?? false);
        $userIds = $isRoot ? [] : $this->visibleUserIds($actor);
        if (!$isRoot && $userIds === []) {
            $userIds = [-1];
        }

        $capacity = $this->weeklyCapacity();
        $window['capacity_minutes_week'] = $capacity['minutes'];

        $assignees = $this->insights()->assigneeLoad($userIds, $isRoot, $window);
        $byUserId = [];
        foreach ($assignees as $row) {
            $byUserId[(string)$row['user_public_id']] = $row;
        }

        $departments = [];
        try {
            [$items] = $this->departments->list(['limit' => 200], null, true);
            foreach ($items as $department) {
                $managerId = (int)($department['manager_user_id'] ?? 0);
                if ($managerId <= 0) {
                    $departments[] = [
                        'public_id' => (string)($department['public_id'] ?? ''),
                        'title' => (string)($department['title'] ?? ''),
                        'members_count' => 0,
                        'active_tasks' => 0,
                        'overdue_tasks' => 0,
                        'minutes_week' => 0,
                        'load_percent' => 0.0,
                        'signal' => 'no_data',
                        'no_manager' => true,
                        'members' => [],
                    ];
                    continue;
                }

                $memberIds = array_values(array_unique(array_merge(
                    [$managerId],
                    $this->userManagement->descendantIds($managerId),
                    $this->teams->findMemberIdsByManager($managerId)
                )));

                $rows = [];
                $active = 0;
                $overdue = 0;
                $minutes = 0;
                foreach ($memberIds as $memberId) {
                    $member = $this->findAssigneeRowById($assignees, $memberId);
                    if ($member === null) {
                        continue;
                    }
                    $rows[] = $member;
                    $active += (int)$member['active_tasks'];
                    $overdue += (int)$member['overdue_tasks'];
                    $minutes += (int)$member['minutes_week'];
                }

                // Capacity follows the organisation's week (calendar or explicit
                // setting), not a hardcoded 40 h per head.
                $departmentCapacity = count($rows) * $capacity['minutes'];
                $loadPercent = $departmentCapacity > 0 ? round($minutes / $departmentCapacity * 100, 1) : 0.0;

                $departments[] = [
                    'public_id' => (string)($department['public_id'] ?? ''),
                    'title' => (string)($department['title'] ?? ''),
                    'members_count' => count($rows),
                    'active_tasks' => $active,
                    'overdue_tasks' => $overdue,
                    'minutes_week' => $minutes,
                    'load_percent' => $loadPercent,
                    'signal' => count($rows) === 0
                        ? 'no_data'
                        : InsightsRepository::workloadSignal($loadPercent, $overdue),
                    'no_manager' => false,
                    'members' => $rows,
                ];
            }
        } catch (\Throwable $e) {
            $departments = [];
        }

        return $this->stripFinancialFields([
            'assignees' => $assignees,
            'departments' => $departments,
            'departments_source' => 'manager_hierarchy',
            'capacity_minutes_week' => $capacity['minutes'],
            'capacity_source' => $capacity['source'],
            'capacity_calendar_public_id' => $capacity['calendar_public_id'],
            'period_days' => $periodDays,
        ]);
    }

    /**
     * Weekly throughput, WIP and cycle time.
     *
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function completionVelocity(array $actor, array $filters): array
    {
        $isRoot = (bool)($actor['is_root'] ?? false);
        $userIds = $isRoot ? [] : $this->visibleUserIds($actor);
        if (!$isRoot && $userIds === []) {
            $userIds = [-1];
        }

        $periodDays = self::normalizePeriodDays($filters['period'] ?? 30);
        $now = gmdate('Y-m-d H:i:s');
        $data = $this->insights()->completionVelocity(
            $userIds,
            $isRoot,
            $now,
            13,
            gmdate('Y-m-d 00:00:00', strtotime('-' . $periodDays . ' days', strtotime($now)))
        );
        $data['scope_users'] = $isRoot ? null : count($userIds);
        $data['period_days'] = $periodDays;

        return $this->stripFinancialFields($data);
    }

    /**
     * Management view over the visible scope: load, efficiency and signals.
     *
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function workloadManagement(array $actor, array $filters): array
    {
        $load = $this->assigneeDepartmentLoad($actor, $filters);
        $assignees = $load['assignees'];

        $summary = ['overload' => 0, 'underload' => 0, 'risk' => 0, 'normal' => 0];
        $loadSum = 0.0;
        $efficiencySum = 0.0;
        foreach ($assignees as $row) {
            $signal = (string)($row['signal'] ?? 'normal');
            $summary[$signal] = ($summary[$signal] ?? 0) + 1;
            $loadSum += (float)$row['load_percent'];
            $efficiencySum += (float)$row['efficiency_percent'];
        }
        $count = count($assignees);

        // Department aggregates already exist in the load payload; the member lists
        // are dropped here because this widget renders the summary rows only.
        $departments = [];
        foreach (($load['departments'] ?? []) as $department) {
            unset($department['members']);
            $departments[] = $department;
        }

        return $this->stripFinancialFields([
            'assignees' => $assignees,
            'departments' => $departments,
            'recommendation' => InsightsRepository::rebalanceRecommendation($assignees),
            'capacity_minutes_week' => $load['capacity_minutes_week'],
            'capacity_source' => $load['capacity_source'] ?? 'default_40h',
            'summary' => $summary + [
                'people' => $count,
                'average_load_percent' => $count > 0 ? round($loadSum / $count, 1) : 0.0,
                'average_efficiency_percent' => $count > 0 ? round($efficiencySum / $count, 1) : 0.0,
            ],
            'period_days' => $load['period_days'],
        ]);
    }

    /**
     * Project ("stream") overview for the accessible scope.
     *
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function streamsOverview(array $actor, array $filters): array
    {
        $periodDays = self::normalizePeriodDays($filters['period'] ?? 30);
        $now = gmdate('Y-m-d H:i:s');
        $isRoot = (bool)($actor['is_root'] ?? false);
        $projectIds = $isRoot ? [] : $this->insights()->accessibleProjectPublicIds(
            (int)($actor['id'] ?? 0),
            $this->accessibleTeamPublicIds($actor)
        );

        $items = $this->insights()->projectsOverview($projectIds, $isRoot, [
            'now' => $now,
            'period_start' => gmdate('Y-m-d 00:00:00', strtotime('-' . $periodDays . ' days', strtotime($now))),
        ]);

        return $this->stripFinancialFields([
            'projects' => $items,
            // Rolled up from the same scoped rows: the portfolio line must never see
            // a project the actor could not open.
            'aggregates' => InsightsRepository::portfolioAggregates($items),
            'period_days' => $periodDays,
        ]);
    }

    /**
     * Detailed metrics for one project, or null when the actor cannot see it.
     *
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $filters
     * @return array<string,mixed>|null
     */
    public function streamDetail(array $actor, array $filters): ?array
    {
        $periodDays = self::normalizePeriodDays($filters['period'] ?? 30);
        $now = gmdate('Y-m-d H:i:s');
        $isRoot = (bool)($actor['is_root'] ?? false);
        $requested = trim((string)($filters['project_public_id'] ?? ''));

        $accessible = $isRoot ? [] : $this->insights()->accessibleProjectPublicIds(
            (int)($actor['id'] ?? 0),
            $this->accessibleTeamPublicIds($actor)
        );

        // A user whose scope holds no project at all has nothing to detail: that is an
        // empty card, not a permission error, and the card must not paint "данные
        // недоступны" over a simply empty dashboard. Only an explicit request for a
        // project outside the scope is refused (checked below), so nothing outside the
        // actor's scope can be reached either way.
        if (!$isRoot && $accessible === [] && $requested !== '') {
            return null;
        }

        $window = [
            'now' => $now,
            'period_start' => gmdate('Y-m-d 00:00:00', strtotime('-' . $periodDays . ' days', strtotime($now))),
        ];
        $options = array_map(static function (array $row): array {
            return ['project_public_id' => $row['project_public_id'], 'title' => $row['title']];
        }, $this->insights()->projectsOverview($accessible, $isRoot, $window));

        if (!$isRoot && $requested !== '' && !in_array($requested, $accessible, true)) {
            return null;
        }

        // No stored choice yet: open on the first stream of the same risk-ranked
        // list the streams widget shows. The card used to answer an empty payload
        // on its very first load, so a root user saw "Нет доступных потоков" with a
        // picker that had nothing in it - and no way left to choose anything.
        if ($requested === '') {
            $requested = (string)($options[0]['project_public_id'] ?? '');
        }
        if ($requested === '') {
            return ['project' => null, 'projects' => $options, 'period_days' => $periodDays];
        }

        $detail = $this->insights()->projectDetail($requested, $window);
        if ($detail === []) {
            return null;
        }

        return $this->stripFinancialFields([
            'project' => $detail,
            'projects' => $options,
            'period_days' => $periodDays,
        ]);
    }

    /**
     * @param array<int, array<string,mixed>> $rows
     * @return array<string,mixed>|null
     */
    private function findAssigneeRowById(array $rows, int $userId): ?array
    {
        foreach ($rows as $row) {
            if ((int)($row['user_id'] ?? 0) === $userId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Defence in depth: widget payloads never carry money, even if a repository
     * later starts selecting rate/cost snapshots (AGENTS.md financial rule).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function stripFinancialFields(array $payload): array
    {
        foreach (['cost_rate', 'bill_rate', 'cost_amount', 'bill_amount', 'payout_rate'] as $field) {
            unset($payload[$field]);
        }

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->stripFinancialFields($value);
            }
        }

        return $payload;
    }

    private static function normalizePeriodDays(mixed $period): int
    {
        $days = (int)$period;

        return in_array($days, [7, 30, 90], true) ? $days : 30;
    }

    private function insights(): InsightsRepository
    {
        if ($this->insights === null) {
            throw new \RuntimeException('Insights repository is not configured');
        }

        return $this->insights;
    }

    /** @return string[] */
    private function accessibleTeamPublicIds(array $actor): array
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return [];
        }

        return $this->teams->listAccessiblePublicIdsForUser((int)($actor['id'] ?? 0));
    }

    /** @return int[] */
    private function visibleUserIds(array $actor): array
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return [];
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return [-1];
        }

        // Same visibility model as WorklogService::getVisibleUserIds(): actor +
        // users created by them (hierarchy) + members of teams where the actor
        // is the manager. Keeps the workload and worklog widget scopes consistent.
        return array_values(array_unique(array_merge(
            [$actorId],
            $this->userManagement->descendantIds($actorId),
            $this->teams->findMemberIdsByManager($actorId)
        )));
    }
}
