<?php
declare(strict_types=1);

namespace Module\Crm\WorksectionMigration\Cron;

use Module\Crm\WorksectionMigration\Repository\WorksectionMigrationRepository;
use Module\Crm\WorksectionMigration\Service\WorksectionClient;
use Module\Crm\WorksectionMigration\Service\WorksectionCrawler;
use Module\Crm\WorksectionMigration\Service\WorksectionImportService;
use Module\Crm\WorksectionMigration\Service\WorksectionTargetWriter;

final class WorksectionWorkerHandler
{
    public static function run(): string
    {
        global $container;
        if (!$container) return json_encode(['processed' => 0], JSON_UNESCAPED_UNICODE) ?: '{}';
        $repo = new WorksectionMigrationRepository($container->get('db.pdo'));
        $job = $repo->claimNextJob();
        if ($job === null) return json_encode(['processed' => 0], JSON_UNESCAPED_UNICODE) ?: '{}';
        $lease = (string)($job['lease_token'] ?? '');
        $client = new WorksectionClient($repo);
        $crawler = new WorksectionCrawler($client, $repo);
        $writer = new WorksectionTargetWriter($container, $repo);
        $service = new WorksectionImportService($repo, $client, $crawler, $writer);
        try {
            $service->processJob((string)$job['public_id'], $lease);
            $repo->releaseLease((string)$job['public_id'], $lease);
            return json_encode(['processed' => 1, 'job' => $job['public_id']], JSON_UNESCAPED_UNICODE) ?: '{}';
        } catch (\Throwable $e) {
            $repo->addLog((int)$job['id'], 'error', 'worker', 'Worksection worker failed.');
            if ($lease === '' || $repo->ownsLease((string)$job['public_id'], $lease)) {
                try { $repo->updateJobStatus((string)$job['public_id'], 'failed', $lease !== '' ? $lease : null); } catch (\Throwable) { }
                $repo->releaseLease((string)$job['public_id'], $lease);
            }
            return json_encode(['processed' => 1, 'failed' => 1], JSON_UNESCAPED_UNICODE) ?: '{}';
        }
    }
}
