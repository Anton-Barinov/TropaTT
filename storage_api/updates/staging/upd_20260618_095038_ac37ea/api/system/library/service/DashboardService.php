<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Dashboard\DashboardRepository;

final class DashboardService
{
    public function __construct(private readonly DashboardRepository $dashboard)
    {
    }

    public function summary(array $actor): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        $base = new \DateTimeImmutable(date('Y-m-d 00:00:00'));
        $weekStart = $base->modify('monday this week')->format('Y-m-d 00:00:00');
        $weekEnd = $base->modify('sunday this week')->format('Y-m-d 23:59:59');

        return $this->dashboard->summary(
            (int)$actor['id'],
            (bool)($actor['is_root'] ?? false),
            $todayStart,
            $todayEnd,
            $weekStart,
            $weekEnd
        );
    }
}

