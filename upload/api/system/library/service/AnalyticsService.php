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
        private readonly ?InsightsRepository $insights = null
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
