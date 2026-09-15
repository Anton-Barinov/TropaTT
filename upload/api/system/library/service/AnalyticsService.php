<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Analytics\AnalyticsRepository;
use Api\Model\Analytics\InsightsRepository;
use Api\Model\Team\TeamRepository;
use Api\Model\User\UserManagementRepository;

final class AnalyticsService
{
    public function __construct(
        private readonly AnalyticsRepository $analytics,
        private readonly TeamRepository $teams,
        private readonly UserManagementRepository $userManagement,
        private readonly ?InsightsRepository $insights = null,
        private readonly ?\Api\Model\Department\DepartmentRepository $departments = null
    ) {
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

        $data = $this->insights()->personalLoad((int)($actor['id'] ?? 0), [
            'now' => $now,
            'week_start' => gmdate('Y-m-d 00:00:00', strtotime('-6 days', strtotime($now))),
            'period_start' => gmdate('Y-m-d 00:00:00', strtotime('-' . $periodDays . ' days', strtotime($now))),
        ]);

        $data['period_days'] = $periodDays;
        $data['user_public_id'] = (string)($actor['public_id'] ?? '');

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

        $data = $this->insights()->actualTime(
            $userIds,
            $isRoot,
            gmdate('Y-m-d 00:00:00', strtotime('-' . $periodDays . ' days', strtotime($now))),
            $now,
            10
        );

        $data['period_days'] = $periodDays;

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

                $capacity = count($rows) * 40 * 60;
                $loadPercent = $capacity > 0 ? round($minutes / $capacity * 100, 1) : 0.0;

                $departments[] = [
                    'public_id' => (string)($department['public_id'] ?? ''),
                    'title' => (string)($department['title'] ?? ''),
                    'members_count' => count($rows),
                    'active_tasks' => $active,
                    'overdue_tasks' => $overdue,
                    'minutes_week' => $minutes,
                    'load_percent' => $loadPercent,
                    'signal' => InsightsRepository::workloadSignal($loadPercent, $overdue),
                    'members' => $rows,
                ];
            }
        } catch (\Throwable $e) {
            $departments = [];
        }

        return $this->stripFinancialFields([
            'assignees' => $assignees,
            'departments' => $departments,
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

        $data = $this->insights()->completionVelocity($userIds, $isRoot, gmdate('Y-m-d H:i:s'), 13);
        $data['scope_users'] = $isRoot ? null : count($this->visibleUserIds($actor));

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

        return $this->stripFinancialFields([
            'assignees' => $assignees,
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

        if (!$isRoot) {
            if ($accessible === []) {
                return null;
            }
            if ($requested === '') {
                $requested = (string)$accessible[0];
            }
            if (!in_array($requested, $accessible, true)) {
                return null;
            }
        }

        if ($requested === '') {
            return ['project' => null, 'projects' => [], 'period_days' => $periodDays];
        }

        $detail = $this->insights()->projectDetail($requested, [
            'now' => $now,
            'period_start' => gmdate('Y-m-d 00:00:00', strtotime('-' . $periodDays . ' days', strtotime($now))),
        ]);
        if ($detail === []) {
            return null;
        }

        $options = $this->insights()->projectsOverview($accessible, $isRoot, [
            'now' => $now,
            'period_start' => gmdate('Y-m-d 00:00:00', strtotime('-' . $periodDays . ' days', strtotime($now))),
        ]);
        $options = array_map(static function (array $row): array {
            return ['project_public_id' => $row['project_public_id'], 'title' => $row['title']];
        }, $options);

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
