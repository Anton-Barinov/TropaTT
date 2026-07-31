<?php
declare(strict_types=1);

namespace Module\Crm\ConfluenceMigration\Service;

use Module\Crm\ConfluenceMigration\Repository\ConfluenceMigrationRepository;

final class ConfluenceProgressReporter
{
    private const STEP_WEIGHTS = [
        'crawl' => 5,
        'import_spaces' => 10,
        'import_page_shells' => 20,
        'import_content' => 30,
        'import_attachments' => 20,
        'import_labels' => 5,
        'import_comments' => 5,
        'publish' => 3,
        'reindex' => 2,
    ];

    public function __construct(private ConfluenceMigrationRepository $repo)
    {
    }

    public function updateProgress(string $jobPublicId, string $step, int $itemsProcessed, int $totalItems): void
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

        $stepProgress = $total > 0 ? ($processed / $total) * $currentWeight : 0;
        return min(100, round($cumulative + $stepProgress, 2));
    }
}
