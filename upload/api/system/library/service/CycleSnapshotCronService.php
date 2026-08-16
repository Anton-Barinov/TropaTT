<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Cycle\CycleSnapshotRepository;
use Api\Model\Cycle\CycleTaskRepository;
use Api\Model\Cycle\WorkCycleRepository;
use Api\Model\Estimate\TaskEstimateRepository;
use PDO;

/**
 * Captures daily burndown snapshots for every active cycle so the chart stays
 * complete even when nobody opens the cycle. Runs from the module cron
 * scheduler (see CycleSnapshotCronHandler).
 */
final class CycleSnapshotCronService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{date:string, active_cycles:int, captured:int, with_points:int}
     */
    public function captureActiveDailySnapshots(): array
    {
        $cycles = new WorkCycleRepository($this->pdo);
        $cycleTasks = new CycleTaskRepository($this->pdo);
        $snapshots = new CycleSnapshotRepository($this->pdo);
        $estimates = new TaskEstimateRepository($this->pdo);

        $active = $cycles->listActive();
        $date = gmdate('Y-m-d');
        $captured = 0;
        $withPoints = 0;

        foreach ($active as $cycle) {
            $cycleId = (int)($cycle['id'] ?? 0);
            if ($cycleId <= 0) {
                continue;
            }

            $summary = $cycleTasks->cycleSummary($cycleId);

            $points = $estimates->chosenPointsByCycleId($cycleId);
            if ($points !== null) {
                $summary['payload'] = [
                    'total_points' => $points['total'],
                    'completed_points' => $points['completed'],
                    'estimate_set_id' => $points['set_id'] ?? null,
                    'unit_label' => $points['unit_label'] ?? '',
                ];
                $withPoints++;
            }

            $snapshots->createOrUpdateDailySnapshot($cycleId, $date, $summary);
            $captured++;
        }

        return [
            'date' => $date,
            'active_cycles' => count($active),
            'captured' => $captured,
            'with_points' => $withPoints,
        ];
    }
}
