<?php
declare(strict_types=1);

namespace Module\Crm\JiraMigration\Service;

use Module\Crm\JiraMigration\Repository\JiraMigrationRepository;

final class JiraProgressReporter
{
    private const STEP_WEIGHTS = [
        'crawl' => 5,
        'create_projects' => 10,
        'create_skeleton_tasks' => 25,
        'resolve_hierarchy' => 30,
        'import_fields' => 40,
        'import_comments' => 50,
        'import_attachments' => 60,
        'import_worklogs' => 70,
        'import_relations' => 80,
        'import_sprints' => 88,
        'import_versions_components' => 95,
        'report' => 100,
    ];

    public function __construct(private JiraMigrationRepository $repo)
    {
    }

    public function updateProgress(string $jobPublicId, string $step, int $itemsProcessed = 0, int $totalItems = 0): void
    {
        $percent = $this->calculatePercent($step, $itemsProcessed, $totalItems);
        $stats = $this->repo->countJobItemsByStatus(
            (int)$this->repo->getJob($jobPublicId)['id']
        );
        $this->repo->updateJobProgress($jobPublicId, $step, $percent, $stats);
    }

    private function calculatePercent(string $currentStep, int $processed, int $total): float
    {
        $cumulative = 0;
        $currentWeight = 0;
        $reached = false;

        foreach (self::STEP_WEIGHTS as $step => $weight) {
            if (!$reached) {
                if ($step === $currentStep) {
                    $currentWeight = $weight;
                    $reached = true;
                } else {
                    $cumulative += $weight;
                }
            }
        }

        if ($reached && $currentWeight > 0) {
            $prevWeight = $cumulative;
            $stepProgress = $total > 0 ? ($processed / $total) * ($currentWeight - $prevWeight) : 0;
            return min(100, round($prevWeight + $stepProgress, 2));
        }

        return min(100, round($cumulative, 2));
    }
}
